<?php
/**
 * Shared High-Performance Catalog Cache
 * Provides sub-millisecond cached catalog and search data across all application views.
 */

if (!function_exists('getCatalogData')) {
    function getCatalogData($currentLanguage = null) {
        global $pdo;
        if ($currentLanguage === null) {
            $currentLanguage = function_exists('getCurrentLanguage') ? getCurrentLanguage() : 'km';
        }

        $cacheDir = sys_get_temp_dir();
        $cacheFile = $cacheDir . '/kouprey_catalog_' . md5($currentLanguage) . '.cache';

        // Check if cache file exists, is less than 1 hour old, and newer than this script
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 3600) && (filemtime($cacheFile) >= filemtime(__FILE__)) && !isset($_GET['flush_cache'])) {
            $cached = @unserialize(@file_get_contents($cacheFile));
            if (is_array($cached) && !empty($cached['products']) && !empty($cached['searchProducts'])) {
                return $cached;
            }
        }

        if (!isset($pdo)) {
            require_once __DIR__ . '/database.php';
        }

        try {
            // Fetch all products with rating stats
            $stmt = $pdo->prepare("
                SELECT
                    p.*,
                    c.base_category_id,
                    COALESCE(review_stats.avg_rating, 0) as avg_rating,
                    COALESCE(review_stats.review_count, 0) as review_count
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                LEFT JOIN (
                    SELECT
                        base_product_id,
                        AVG(r.rating) as avg_rating,
                        COUNT(r.id) as review_count
                    FROM reviews r
                    JOIN products pr ON r.product_id = pr.id
                    GROUP BY pr.base_product_id
                ) review_stats ON p.base_product_id = review_stats.base_product_id
                WHERE p.enabled = 1
                ORDER BY (
                    CASE 
                        WHEN c.base_category_id = '19' OR p.name LIKE '%Syrup%' OR p.name LIKE '%ស៊ីរ៉ូ%' OR p.name LIKE '%សុីរ៉ូ%' THEN 1
                        WHEN c.base_category_id = '13' OR p.name LIKE '%Powder%' OR p.name LIKE '%ម្សៅ%' OR p.name LIKE '%Matcha%' THEN 2
                        ELSE 3
                    END
                ) ASC, p.sort_order ASC, p.featured DESC, p.id DESC
            ");
            $stmt->execute();
            $allProducts = $stmt->fetchAll();

            // Group products by base_product_id
            $productsByBaseId = [];
            foreach ($allProducts as $product) {
                $baseId = $product['base_product_id'] ?? $product['id'];
                if (!isset($productsByBaseId[$baseId])) {
                    $productsByBaseId[$baseId] = [];
                }
                $productsByBaseId[$baseId][$product['language']] = $product;
            }

            // For display, use current language products, fallback to English
            $products = [];
            foreach ($productsByBaseId as $baseId => $langVersions) {
                if (isset($langVersions[$currentLanguage])) {
                    $products[] = $langVersions[$currentLanguage];
                } elseif (isset($langVersions['en'])) {
                    $products[] = $langVersions['en'];
                } else {
                    $products[] = reset($langVersions);
                }
            }

            // Sort products so Syrup (base_category_id 19) and Powder (base_category_id 13) appear first
            usort($products, function($a, $b) {
                $priority = function($p) {
                    $baseCatId = (string)($p['base_category_id'] ?? '');
                    $name = $p['name'] ?? '';
                    if ($baseCatId === '19' || preg_match('/syrup|ស៊ីរ៉ូ|សុីរ៉ូ/iu', $name)) {
                        return 1;
                    }
                    if ($baseCatId === '13' || preg_match('/powder|matcha|ម្សៅ/iu', $name)) {
                        return 2;
                    }
                    return 3;
                };

                $pA = $priority($a);
                $pB = $priority($b);
                if ($pA !== $pB) {
                    return $pA <=> $pB;
                }
                $featA = !empty($a['featured']) ? 1 : 0;
                $featB = !empty($b['featured']) ? 1 : 0;
                if ($featA !== $featB) {
                    return $featB <=> $featA;
                }
                $soA = (int)($a['sort_order'] ?? 0);
                $soB = (int)($b['sort_order'] ?? 0);
                if ($soA !== $soB) {
                    return $soA <=> $soB;
                }
                return (int)($b['id'] ?? 0) <=> (int)($a['id'] ?? 0);
            });
            $allAvailableProducts = $products;

            // Create search index with all language versions
            $searchProducts = [];
            foreach ($productsByBaseId as $baseId => $langVersions) {
                $searchProduct = [
                    'base_product_id' => $baseId,
                    'languages' => $langVersions,
                    'all_names' => '',
                    'all_descriptions' => '',
                    'all_categories' => '',
                    'featured' => 0,
                    'best_seller' => 0,
                    'avg_rating' => 0,
                    'review_count' => 0,
                    'id' => null,
                    'image' => null,
                    'name' => '',
                    'category_id' => null
                ];

                $names = [];
                $descriptions = [];
                $categoriesNames = [];

                foreach ($langVersions as $lang => $product) {
                    if (!empty($product['name'])) $names[] = $product['name'];
                    if (!empty($product['description'])) $descriptions[] = $product['description'];
                    if (!empty($product['detailed_description'])) $descriptions[] = $product['detailed_description'];
                    if (!empty($product['category_name'])) $categoriesNames[] = $product['category_name'];

                    if (!empty($product['featured'])) $searchProduct['featured'] = 1;
                    if (!empty($product['best_seller'])) $searchProduct['best_seller'] = 1;
                    if (!empty($product['avg_rating'])) $searchProduct['avg_rating'] = $product['avg_rating'];
                    if (!empty($product['review_count'])) $searchProduct['review_count'] = $product['review_count'];

                    if ($lang === $currentLanguage || empty($searchProduct['id'])) {
                        $searchProduct['id'] = $product['id'];
                        $searchProduct['image'] = $product['image'];
                        $searchProduct['name'] = $product['name'];
                        $searchProduct['category_id'] = $product['category_id'];
                    }
                }

                $searchProduct['all_names'] = implode(' ', array_unique($names));
                $searchProduct['all_descriptions'] = implode(' ', array_unique($descriptions));
                $searchProduct['all_categories'] = implode(' ', array_unique($categoriesNames));

                $searchProducts[] = $searchProduct;
            }

            // Fetch categories for current language, prioritizing Syrup (19) and Powder (13) at the top
            $catStmt = $pdo->prepare("
                SELECT * FROM categories 
                WHERE language = ? 
                ORDER BY (
                    CASE 
                        WHEN base_category_id = '19' OR name LIKE '%Syrup%' OR name LIKE '%សុីរ៉ូ%' OR name LIKE '%ស៊ីរ៉ូ%' THEN 1
                        WHEN base_category_id = '13' OR name LIKE '%Powder%' OR name LIKE '%ម្សៅ%' THEN 2
                        ELSE 3
                    END
                ) ASC, name ASC
            ");
            $catStmt->execute([$currentLanguage]);
            $categories = $catStmt->fetchAll();

            // Fallback sort for categories to guarantee Syrup (1) and Powder (2) appear first
            usort($categories, function($a, $b) {
                $pA = ($a['base_category_id'] == '19' || preg_match('/syrup|ស៊ីរ៉ូ|សុីរ៉ូ/iu', $a['name'])) ? 1 : (($a['base_category_id'] == '13' || preg_match('/powder|matcha|ម្សៅ/iu', $a['name'])) ? 2 : 3);
                $pB = ($b['base_category_id'] == '19' || preg_match('/syrup|ស៊ីរ៉ូ|សុីរ៉ូ/iu', $b['name'])) ? 1 : (($b['base_category_id'] == '13' || preg_match('/powder|matcha|ម្សៅ/iu', $b['name'])) ? 2 : 3);
                if ($pA !== $pB) return $pA <=> $pB;
                return strcmp($a['name'] ?? '', $b['name'] ?? '');
            });

            // Normalize Khmer spelling on categories
            foreach ($categories as &$c) {
                if (function_exists('normalizeKhmerSpelling')) {
                    $c['name'] = normalizeKhmerSpelling($c['name'] ?? '');
                    if (!empty($c['description'])) $c['description'] = normalizeKhmerSpelling($c['description']);
                } else {
                    $c['name'] = str_replace(["\xE1\x9E\x9F\xE1\x9E\xBB\xE1\x9E\xB8", "សុី"], "ស៊ី", $c['name'] ?? '');
                }
            }
            unset($c);

            // Normalize Khmer spelling on products
            foreach ($products as &$p) {
                if (function_exists('normalizeKhmerSpelling')) {
                    $p['name'] = normalizeKhmerSpelling($p['name'] ?? '');
                    if (!empty($p['category_name'])) $p['category_name'] = normalizeKhmerSpelling($p['category_name']);
                } else {
                    $p['name'] = str_replace(["\xE1\x9E\x9F\xE1\x9E\xBB\xE1\x9E\xB8", "សុី"], "ស៊ី", $p['name'] ?? '');
                    if (!empty($p['category_name'])) $p['category_name'] = str_replace(["\xE1\x9E\x9F\xE1\x9E\xBB\xE1\x9E\xB8", "សុី"], "ស៊ី", $p['category_name']);
                }
            }
            unset($p);

            $allAvailableProducts = $products;

            $catalogData = [
                'products' => $products,
                'allAvailableProducts' => $allAvailableProducts,
                'searchProducts' => $searchProducts,
                'categories' => $categories,
                'productsByBaseId' => $productsByBaseId
            ];

            // Persist to cache file
            @file_put_contents($cacheFile, serialize($catalogData), LOCK_EX);

            return $catalogData;

        } catch (Exception $e) {
            error_log("Catalog cache error: " . $e->getMessage());
            return [
                'products' => [],
                'allAvailableProducts' => [],
                'searchProducts' => [],
                'categories' => [],
                'productsByBaseId' => []
            ];
        }
    }
}

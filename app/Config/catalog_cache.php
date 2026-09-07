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

        // Check if cache file exists and is less than 5 minutes old
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 300)) {
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
                ORDER BY p.sort_order ASC, p.featured DESC, p.id DESC
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

            // Fetch categories for current language
            $catStmt = $pdo->prepare("SELECT * FROM categories WHERE language = ? ORDER BY name ASC");
            $catStmt->execute([$currentLanguage]);
            $categories = $catStmt->fetchAll();

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

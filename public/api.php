<?php
// Public REST API endpoint for AJAX and React Frontend
require_once __DIR__ . '/../app/Config/database.php';
require_once __DIR__ . '/../app/Config/settings.php';
require_once __DIR__ . '/../app/Controllers/ProductController.php';

// Enable CORS
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

$controller = new ProductController();
$action = $_GET['action'] ?? '';
$language = $_GET['language'] ?? $_GET['lang'] ?? getCurrentLanguage();

switch ($action) {
    case 'get_bootstrap':
        try {
            // Load all settings for requested language (with English fallback)
            loadAllSettingsIntoCache($language);
            $settings = getAllSettings($language);
            
            // Also merge English settings if missing in requested language
            if ($language !== 'en') {
                $enSettings = getAllSettings('en');
                foreach ($enSettings as $k => $v) {
                    if (!isset($settings[$k]) || $settings[$k] === '') {
                        $settings[$k] = $v;
                    }
                }
            }

            // Post-process settings like company_logo, site title, etc.
            if (empty($settings['company_logo']) && !empty($settings['site_logo'])) {
                $settings['company_logo'] = $settings['site_logo'];
            }

            // Fetch categories for language
            $catStmt = $pdo->prepare("
                SELECT c.*, COUNT(p.id) as product_count
                FROM categories c
                LEFT JOIN products p ON p.category_id = c.id AND p.enabled = 1
                WHERE c.language = ?
                GROUP BY c.id
                ORDER BY c.name ASC
            ");
            $catStmt->execute([$language]);
            $categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch banners from uploads directory
            $heroFiles = glob(__DIR__ . '/uploads/hero-bg-*.png');
            $heroImages = array_map(function($f) {
                return '/uploads/' . basename($f);
            }, $heroFiles ?: []);

            $banner1Files = glob(__DIR__ . '/uploads/banners/banner-1-*.*');
            $banner2Files = glob(__DIR__ . '/uploads/banners/banner-2-*.*');
            $banners = array_merge(
                array_map(fn($f) => '/uploads/banners/' . basename($f), $banner1Files ?: []),
                array_map(fn($f) => '/uploads/banners/' . basename($f), $banner2Files ?: [])
            );

            echo json_encode([
                'success' => true,
                'language' => $language,
                'settings' => $settings,
                'categories' => $categories,
                'hero_images' => $heroImages,
                'banners' => $banners
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'get_products':
        try {
            $categoryId = $_GET['category_id'] ?? null;
            $baseCategoryId = $_GET['base_category_id'] ?? null;
            $search = trim($_GET['search'] ?? '');
            $featured = isset($_GET['featured']) ? (int)$_GET['featured'] : null;
            $bestSeller = isset($_GET['best_seller']) ? (int)$_GET['best_seller'] : null;

            $where = ["p.language = ?", "p.enabled = 1"];
            $params = [$language];

            if ($baseCategoryId) {
                $where[] = "c.base_category_id = ?";
                $params[] = $baseCategoryId;
            } elseif ($categoryId) {
                $where[] = "p.category_id = ?";
                $params[] = $categoryId;
            }

            if ($featured !== null) {
                $where[] = "p.featured = ?";
                $params[] = $featured;
            }

            if ($bestSeller !== null) {
                $where[] = "p.best_seller = ?";
                $params[] = $bestSeller;
            }

            if ($search !== '') {
                $where[] = "(p.name LIKE ? OR p.short_description LIKE ? OR p.detailed_description LIKE ?)";
                $searchTerm = "%$search%";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }

            $whereSql = implode(' AND ', $where);

            $sql = "
                SELECT
                    p.*,
                    c.name as category_name,
                    c.base_category_id,
                    COALESCE(review_stats.avg_rating, 0) as avg_rating,
                    COALESCE(review_stats.review_count, 0) as review_count
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                LEFT JOIN (
                    SELECT
                        pr.base_product_id,
                        AVG(r.rating) as avg_rating,
                        COUNT(r.id) as review_count
                    FROM reviews r
                    JOIN products pr ON r.product_id = pr.id
                    GROUP BY pr.base_product_id
                ) review_stats ON p.base_product_id = review_stats.base_product_id
                WHERE $whereSql
                ORDER BY p.sort_order ASC, p.featured DESC, p.id DESC
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Normalize fields
            foreach ($products as &$prod) {
                $prod['avg_rating'] = round((float)$prod['avg_rating'], 1);
                $prod['review_count'] = (int)$prod['review_count'];
                $prod['price'] = (float)$prod['price'];
                if (!empty($prod['original_price'])) {
                    $prod['original_price'] = (float)$prod['original_price'];
                }
            }

            echo json_encode([
                'success' => true,
                'count' => count($products),
                'products' => $products
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'get_product_detail':
    case 'get_product_modal_data':
        $baseProductId = $_GET['base_id'] ?? $_GET['base_product_id'] ?? $_GET['id'] ?? 0;
        
        // Try getting by base_product_id
        $productResult = $controller->getProductByBaseId($baseProductId, $language);
        
        // If not found, check if it was a primary key id
        if ((!$productResult['success'] || !$productResult['product']) && $baseProductId) {
            $pkResult = $controller->getProductById($baseProductId);
            if ($pkResult['success'] && !empty($pkResult['product']['base_product_id'])) {
                $baseProductId = $pkResult['product']['base_product_id'];
                $productResult = $controller->getProductByBaseId($baseProductId, $language);
            }
        }
        
        if (!$productResult['success'] || !$productResult['product']) {
            echo json_encode(['success' => false, 'error' => 'Product not found']);
            break;
        }
        
        $product = $productResult['product'];
        $product['price'] = (float)$product['price'];
        if (!empty($product['original_price'])) {
            $product['original_price'] = (float)$product['original_price'];
        }
        
        // Reviews
        $reviewsResult = $controller->getReviews($product['id']);
        
        // Related products
        $relatedResult = $controller->getRelatedProducts($product['base_product_id'], $language);
        
        echo json_encode([
            'success' => true,
            'product' => $product,
            'reviews' => $reviewsResult['reviews'] ?? [],
            'avg_rating' => $reviewsResult['avg_rating'] ?? 0,
            'total_reviews' => $reviewsResult['total_reviews'] ?? 0,
            'related_products' => $relatedResult['related_products'] ?? []
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        break;

    case 'get_reviews':
        $productId = $_GET['product_id'] ?? 0;
        echo json_encode($controller->getReviews($productId), JSON_UNESCAPED_UNICODE);
        break;

    case 'get_all_reviews':
        try {
            $stmt = $pdo->query("
                SELECT r.*, p.name as product_name, p.image as product_image, p.base_product_id
                FROM reviews r
                LEFT JOIN products p ON r.product_id = p.id
                ORDER BY r.created_at DESC
            ");
            $allReviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calculate overall rating stats
            $total = count($allReviews);
            $sum = 0;
            $ratingCounts = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
            foreach ($allReviews as $r) {
                $rating = (int)$r['rating'];
                $sum += $rating;
                if (isset($ratingCounts[$rating])) {
                    $ratingCounts[$rating]++;
                }
            }
            $avg = $total > 0 ? round($sum / $total, 1) : 5.0;

            echo json_encode([
                'success' => true,
                'reviews' => $allReviews,
                'total_reviews' => $total,
                'avg_rating' => $avg,
                'rating_counts' => $ratingCounts
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'add_review':
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data && !empty($_POST)) {
            $data = $_POST;
        }
        echo json_encode($controller->addReview($data), JSON_UNESCAPED_UNICODE);
        break;

    case 'get_related_products':
        $baseProductId = $_GET['base_product_id'] ?? 0;
        echo json_encode($controller->getRelatedProducts($baseProductId, $language), JSON_UNESCAPED_UNICODE);
        break;

    case 'get_features':
        try {
            $stmt = $pdo->prepare("SELECT * FROM features ORDER BY base_feature_id, language");
            $stmt->execute();
            $allFeatures = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $featuresGrouped = [];
            foreach ($allFeatures as $f) {
                $bId = $f['base_feature_id'] ?? $f['id'];
                $featuresGrouped[$bId][$f['language']] = $f;
            }
            $features = array_values(array_map(function($group) use ($language) {
                return $group[$language] ?? ($group['en'] ?? reset($group));
            }, $featuresGrouped));

            echo json_encode(['success' => true, 'features' => $features], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage(), 'features' => []]);
        }
        break;

    case 'get_page_content':
        $page = $_GET['page'] ?? '';
        try {
            switch ($page) {
                case 'features':
                    $stmt = $pdo->prepare("SELECT * FROM features ORDER BY base_feature_id, language");
                    $stmt->execute();
                    $allFeatures = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $featuresGrouped = [];
                    foreach ($allFeatures as $f) {
                        $bId = $f['base_feature_id'] ?? $f['id'];
                        $featuresGrouped[$bId][$f['language']] = $f;
                    }
                    $features = array_values(array_map(function($group) use ($language) {
                        return $group[$language] ?? ($group['en'] ?? reset($group));
                    }, $featuresGrouped));

                    echo json_encode(['success' => true, 'page' => 'features', 'features' => $features], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    break;

                case 'about':
                    $stmt = $pdo->query("SELECT * FROM about ORDER BY id DESC LIMIT 1");
                    $about = $stmt->fetch(PDO::FETCH_ASSOC);
                    echo json_encode(['success' => true, 'page' => 'about', 'about' => $about ?: []], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    break;

                case 'privacy_policy':
                    loadAllSettingsIntoCache($language);
                    $title = getSetting('privacy_policy_title', $language === 'km' ? 'គោលការណ៍ឯកជនភាព' : 'Privacy Policy', $language);
                    $content = getSetting('privacy_policy', '', $language);
                    if (empty($content)) {
                        $content = getSetting('privacy_policy_content', '', $language);
                    }
                    echo json_encode(['success' => true, 'page' => 'privacy_policy', 'title' => $title, 'content' => $content], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    break;

                case 'terms_of_service':
                    loadAllSettingsIntoCache($language);
                    $title = getSetting('terms_of_service_title', $language === 'km' ? 'លក្ខខណ្ឌប្រើប្រាស់' : 'Terms of Service', $language);
                    $content = getSetting('terms_of_service', '', $language);
                    if (empty($content)) {
                        $content = getSetting('terms_of_service_content', '', $language);
                    }
                    echo json_encode(['success' => true, 'page' => 'terms_of_service', 'title' => $title, 'content' => $content], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    break;
                    echo json_encode(['success' => true, 'page' => 'terms_of_service', 'title' => $title, 'content' => $content], JSON_UNESCAPED_UNICODE);
                    break;

                default:
                    echo json_encode(['success' => false, 'error' => 'Page not found']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'track_visit':
        try {
            require_once __DIR__ . '/../app/Config/visitor_tracker.php';
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action. Supported: get_bootstrap, get_products, get_product_detail, get_reviews, get_all_reviews, add_review, get_related_products, get_page_content, track_visit']);
}
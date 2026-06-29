<?php
// Public API endpoint for AJAX requests
require_once __DIR__ . '/../app/Config/database.php';
require_once __DIR__ . '/../app/Controllers/ProductController.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$controller = new ProductController();

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_reviews':
        $productId = $_GET['product_id'] ?? 0;
        echo json_encode($controller->getReviews($productId));
        break;

    case 'add_review':
        $data = json_decode(file_get_contents('php://input'), true);
        echo json_encode($controller->addReview($data));
        break;

    case 'get_related_products':
        $baseProductId = $_GET['base_product_id'] ?? 0;
        $language = $_GET['language'] ?? null;
        echo json_encode($controller->getRelatedProducts($baseProductId, $language));
        break;

    case 'get_product_modal_data':
        $baseProductId = $_GET['base_product_id'] ?? 0;
        $language = $_GET['language'] ?? 'en';
        
        // Get product data
        $productResult = $controller->getProductByBaseId($baseProductId, $language);
        
        if (!$productResult['success'] || !$productResult['product']) {
            echo json_encode(['success' => false, 'error' => 'Product not found']);
            break;
        }
        
        $product = $productResult['product'];
        
        // Get reviews
        $reviewsResult = $controller->getReviews($product['id']);
        
        // Get related products
        $relatedResult = $controller->getRelatedProducts($baseProductId, $language);
        
        echo json_encode([
            'success' => true,
            'product' => $product,
            'reviews' => $reviewsResult['reviews'] ?? [],
            'avg_rating' => $reviewsResult['avg_rating'] ?? 0,
            'total_reviews' => $reviewsResult['total_reviews'] ?? 0,
            'related_products' => $relatedResult['related_products'] ?? []
        ]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
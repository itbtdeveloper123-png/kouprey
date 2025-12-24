<?php
require_once __DIR__ . '/../Config/database.php';
require_once __DIR__ . '/../Config/settings.php';

class ProductController {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function getReviews($productId) {
        try {
            // Get base_product_id for this product
            $stmt = $this->pdo->prepare("SELECT base_product_id FROM products WHERE id = ?");
            $stmt->execute([$productId]);
            $product = $stmt->fetch();
            
            if (!$product) {
                return [
                    'success' => false,
                    'error' => 'Product not found',
                    'reviews' => [],
                    'avg_rating' => 0,
                    'total_reviews' => 0
                ];
            }
            
            $baseProductId = $product['base_product_id'];
            
            // Get reviews for all language versions of this product
            $stmt = $this->pdo->prepare("
                SELECT r.name, r.review, r.rating, r.created_at
                FROM reviews r
                JOIN products p ON r.product_id = p.id
                WHERE p.base_product_id = ?
                ORDER BY r.created_at DESC
            ");
            $stmt->execute([$baseProductId]);
            $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get average rating and total reviews
            $statsStmt = $this->pdo->prepare("
                SELECT
                    COALESCE(AVG(r.rating), 0) as avg_rating,
                    COUNT(*) as total_reviews
                FROM reviews r
                JOIN products p ON r.product_id = p.id
                WHERE p.base_product_id = ?
            ");
            $statsStmt->execute([$baseProductId]);
            $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'reviews' => $reviews,
                'avg_rating' => round($stats['avg_rating'], 1),
                'total_reviews' => $stats['total_reviews']
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'reviews' => [],
                'avg_rating' => 0,
                'total_reviews' => 0
            ];
        }
    }

    public function getProducts($language = 'en', $limit = null, $featured = false, $bestSeller = false) {
        try {
            $whereConditions = ["p.language = ?"];
            $params = [$language];

            if ($featured) {
                $whereConditions[] = "p.featured = 1";
            }

            if ($bestSeller) {
                $whereConditions[] = "p.best_seller = 1";
            }

            $whereClause = implode(" AND ", $whereConditions);
            $orderClause = "ORDER BY p.sort_order ASC, p.id DESC";

            $limitClause = $limit ? "LIMIT ?" : "";
            if ($limit) {
                $params[] = $limit;
            }

            $stmt = $this->pdo->prepare("
                SELECT p.*, c.name as category_name
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id AND c.language = p.language
                WHERE $whereClause
                $orderClause
                $limitClause
            ");
            $stmt->execute($params);

            return [
                'success' => true,
                'products' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'products' => []
            ];
        }
    }

    public function addReview($data) {
        try {
            // Validate input
            if (empty($data['product_id']) || empty($data['name']) || empty($data['review']) || !isset($data['rating'])) {
                return ['success' => false, 'error' => 'All fields are required'];
            }

            if ($data['rating'] < 1 || $data['rating'] > 5) {
                return ['success' => false, 'error' => 'Invalid rating'];
            }

            // Insert review
            $stmt = $this->pdo->prepare("
                INSERT INTO reviews (product_id, name, review, rating)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['product_id'],
                htmlspecialchars($data['name']),
                htmlspecialchars($data['review']),
                $data['rating']
            ]);

            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getRelatedProducts($baseProductId, $language = null) {
        try {
            if ($language === null) {
                $language = getCurrentLanguage();
            }

            // Get regular related products (for the requested language)
            $stmt = $this->pdo->prepare("\n                SELECT p.id, p.base_product_id, p.name, p.price, p.image, p.detailed_description, pr.custom_image, pr.custom_image_url, pr.custom_url, '' as custom_name\n                FROM product_related pr\n                JOIN products p ON pr.related_product_id = p.id\n                WHERE pr.product_id = ? AND p.language = ?\n                ORDER BY pr.sort_order ASC, p.name ASC\n            ");
            $stmt->execute([$baseProductId, $language]);
            $relatedProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get custom related products (these are stored in product_related table)
            $stmt2 = $this->pdo->prepare("\n                SELECT pr.id, '' as base_product_id, pr.custom_name as name, 0 as price, pr.custom_image as image, '' as detailed_description, pr.custom_image as custom_image, pr.custom_image_url, pr.custom_url, pr.custom_name\n                FROM product_related pr\n                WHERE pr.product_id = ? AND pr.related_product_id IS NULL\n                ORDER BY pr.sort_order ASC, pr.custom_name ASC\n            ");
            $stmt2->execute([$baseProductId]);
            $customProducts = $stmt2->fetchAll(PDO::FETCH_ASSOC);

            // Combine them
            $allRelated = array_merge($relatedProducts, $customProducts);

            return [
                'success' => true,
                'related_products' => $allRelated
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'related_products' => []
            ];
        }
    }

    // Get single product by base_product_id and language
    public function getProductByBaseId($baseProductId, $language = 'en') {
        try {
            $stmt = $this->pdo->prepare("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id AND c.language = p.language WHERE p.base_product_id = ? AND p.language = ? LIMIT 1");
            $stmt->execute([$baseProductId, $language]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$product) {
                // Fallback to English
                $stmt = $this->pdo->prepare("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id AND c.language = p.language WHERE p.base_product_id = ? AND p.language = 'en' LIMIT 1");
                $stmt->execute([$baseProductId]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            return ['success' => true, 'product' => $product ?: null];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage(), 'product' => null];
        }
    }
}
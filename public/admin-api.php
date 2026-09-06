<?php
/**
 * KouPrey Admin REST API
 * Secured endpoint for React Admin Panel
 * All actions (except login) require a valid admin session.
 */

// CORS - allow admin panel dev server and production
$allowedOrigins = ['http://localhost:5175', 'http://localhost:5174', 'http://localhost:5173', 'https://www.kouprey.asia', 'https://kouprey.asia'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Credentials: true');
}
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../app/Config/database.php';
require_once __DIR__ . '/../app/Config/settings.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ─────────────────────────────────────────────────
//  PUBLIC ACTIONS (no session required)
// ─────────────────────────────────────────────────
if ($action === 'login') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'error' => 'POST required']);
        exit;
    }
    $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $username = trim($data['username'] ?? '');
    $password = $data['password'] ?? '';

    if (empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'error' => 'Username and password are required']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id, username, password, full_name, status FROM admin_users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        if ($user['status'] === 'active') {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $user['id'];
            $_SESSION['admin_username'] = $user['username'];
            $_SESSION['admin_name'] = $user['full_name'];

            $pdo->prepare("UPDATE admin_users SET last_login = CURRENT_TIMESTAMP WHERE id = ?")->execute([$user['id']]);

            echo json_encode([
                'success' => true,
                'admin' => ['id' => $user['id'], 'username' => $user['username'], 'name' => $user['full_name']]
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Account is inactive']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid username or password']);
    }
    exit;
}

if ($action === 'check_session') {
    if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in']) {
        echo json_encode(['success' => true, 'admin' => ['id' => $_SESSION['admin_id'], 'username' => $_SESSION['admin_username'], 'name' => $_SESSION['admin_name']]]);
    } else {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    }
    exit;
}

// ─────────────────────────────────────────────────
//  AUTHENTICATION GUARD — all actions below require login
// ─────────────────────────────────────────────────
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized. Please login.']);
    exit;
}

// ─────────────────────────────────────────────────
//  PROTECTED ACTIONS
// ─────────────────────────────────────────────────
switch ($action) {

    // ── LOGOUT ──────────────────────────────────
    case 'logout':
        session_destroy();
        echo json_encode(['success' => true]);
        break;

    // ── DASHBOARD STATS ─────────────────────────
    case 'get_stats':
        try {
            $productCount    = $pdo->query("SELECT COUNT(*) FROM products WHERE language='km'")->fetchColumn();
            $reviewCount     = $pdo->query("SELECT COUNT(*) FROM reviews")->fetchColumn();
            $categoryCount   = $pdo->query("SELECT COUNT(*) FROM categories WHERE language='km'")->fetchColumn();
            $adminCount      = $pdo->query("SELECT COUNT(*) FROM admin_users WHERE status='active'")->fetchColumn();
            $pendingReviews  = $pdo->query("SELECT COUNT(*) FROM reviews WHERE status='pending' OR status IS NULL OR status=''")->fetchColumn();
            echo json_encode(['success' => true, 'stats' => compact('productCount','reviewCount','categoryCount','adminCount','pendingReviews')]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    // ── PRODUCTS ────────────────────────────────
    case 'get_all_products':
        try {
            $lang = $_GET['lang'] ?? 'km';
            $search = trim($_GET['search'] ?? '');
            $catId = $_GET['category_id'] ?? null;

            $where = ["p.language = ?"];
            $params = [$lang];

            if ($catId) { $where[] = "p.category_id = ?"; $params[] = $catId; }
            if ($search) {
                $where[] = "(p.name LIKE ? OR p.short_description LIKE ?)";
                $params[] = "%$search%"; $params[] = "%$search%";
            }

            $sql = "SELECT p.*, c.name as category_name, c.base_category_id,
                        COALESCE(rs.avg_rating,0) as avg_rating, COALESCE(rs.review_count,0) as review_count
                    FROM products p
                    LEFT JOIN categories c ON p.category_id = c.id
                    LEFT JOIN (
                        SELECT pr.base_product_id, AVG(r.rating) avg_rating, COUNT(r.id) review_count
                        FROM reviews r JOIN products pr ON r.product_id = pr.id
                        GROUP BY pr.base_product_id
                    ) rs ON p.base_product_id = rs.base_product_id
                    WHERE " . implode(' AND ', $where) . "
                    ORDER BY p.sort_order ASC, p.id DESC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'count' => count($products), 'products' => $products], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'save_product':
        try {
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $id = intval($data['id'] ?? 0);
            $base_product_id = intval($data['base_product_id'] ?? 0);

            $fields = [
                'name' => trim($data['name'] ?? ''),
                'category_id' => intval($data['category_id'] ?? 0) ?: null,
                'price' => floatval($data['price'] ?? 0),
                'original_price' => !empty($data['original_price']) ? floatval($data['original_price']) : null,
                'short_description' => trim($data['short_description'] ?? ''),
                'detailed_description' => trim($data['detailed_description'] ?? ''),
                'featured' => intval($data['featured'] ?? 0),
                'best_seller' => intval($data['best_seller'] ?? 0),
                'enabled' => intval($data['enabled'] ?? 1),
                'sort_order' => intval($data['sort_order'] ?? 0),
                'language' => $data['language'] ?? 'km',
            ];
            if (!empty($data['image'])) $fields['image'] = trim($data['image']);

            if ($id > 0) {
                // UPDATE
                $setClauses = implode(', ', array_map(fn($k) => "$k = ?", array_keys($fields)));
                $stmt = $pdo->prepare("UPDATE products SET $setClauses WHERE id = ?");
                $stmt->execute([...array_values($fields), $id]);
                echo json_encode(['success' => true, 'action' => 'updated', 'id' => $id]);
            } else {
                // INSERT
                if ($base_product_id === 0) {
                    $maxBase = $pdo->query("SELECT COALESCE(MAX(base_product_id),0) FROM products")->fetchColumn();
                    $base_product_id = $maxBase + 1;
                }
                $fields['base_product_id'] = $base_product_id;
                $cols = implode(', ', array_keys($fields));
                $placeholders = implode(', ', array_fill(0, count($fields), '?'));
                $stmt = $pdo->prepare("INSERT INTO products ($cols) VALUES ($placeholders)");
                $stmt->execute(array_values($fields));
                $newId = $pdo->lastInsertId();
                echo json_encode(['success' => true, 'action' => 'created', 'id' => $newId, 'base_product_id' => $base_product_id]);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'delete_product':
        try {
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $base_product_id = intval($data['base_product_id'] ?? 0);
            $id = intval($data['id'] ?? 0);
            if ($base_product_id) {
                $pdo->prepare("DELETE FROM products WHERE base_product_id = ?")->execute([$base_product_id]);
                echo json_encode(['success' => true, 'deleted' => 'all_languages']);
            } elseif ($id) {
                $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$id]);
                echo json_encode(['success' => true, 'deleted' => 'single']);
            } else {
                echo json_encode(['success' => false, 'error' => 'No id provided']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    // ── CATEGORIES ──────────────────────────────
    case 'get_categories':
        try {
            $lang = $_GET['lang'] ?? 'km';
            $stmt = $pdo->prepare("SELECT * FROM categories WHERE language = ? ORDER BY name ASC");
            $stmt->execute([$lang]);
            echo json_encode(['success' => true, 'categories' => $stmt->fetchAll()], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'save_category':
        try {
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $id = intval($data['id'] ?? 0);
            $fields = [
                'name' => trim($data['name'] ?? ''),
                'language' => $data['language'] ?? 'km',
                'description' => trim($data['description'] ?? ''),
            ];
            if ($id > 0) {
                $set = implode(', ', array_map(fn($k) => "$k = ?", array_keys($fields)));
                $pdo->prepare("UPDATE categories SET $set WHERE id = ?")->execute([...array_values($fields), $id]);
                echo json_encode(['success' => true, 'action' => 'updated']);
            } else {
                $maxBase = $pdo->query("SELECT COALESCE(MAX(base_category_id),0) FROM categories")->fetchColumn();
                $fields['base_category_id'] = $maxBase + 1;
                $cols = implode(', ', array_keys($fields));
                $ph = implode(', ', array_fill(0, count($fields), '?'));
                $pdo->prepare("INSERT INTO categories ($cols) VALUES ($ph)")->execute(array_values($fields));
                echo json_encode(['success' => true, 'action' => 'created', 'id' => $pdo->lastInsertId()]);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'delete_category':
        try {
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $id = intval($data['id'] ?? 0);
            $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$id]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    // ── REVIEWS ─────────────────────────────────
    case 'get_all_reviews_admin':
        try {
            $stmt = $pdo->query("SELECT r.*, p.name as product_name, p.image as product_image, p.base_product_id
                FROM reviews r LEFT JOIN products p ON r.product_id = p.id
                ORDER BY r.created_at DESC");
            $reviews = $stmt->fetchAll();
            $total = count($reviews);
            $avg = $total > 0 ? round(array_sum(array_column($reviews, 'rating')) / $total, 1) : 0;
            echo json_encode(['success' => true, 'reviews' => $reviews, 'total' => $total, 'avg_rating' => $avg], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'update_review':
        try {
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $id = intval($data['id'] ?? 0);
            $status = $data['status'] ?? 'approved';
            $pdo->prepare("UPDATE reviews SET status = ? WHERE id = ?")->execute([$status, $id]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'delete_review':
        try {
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $id = intval($data['id'] ?? 0);
            $pdo->prepare("DELETE FROM reviews WHERE id = ?")->execute([$id]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    // ── FEATURES ────────────────────────────────
    case 'get_features_admin':
        try {
            $lang = $_GET['lang'] ?? 'km';
            $stmt = $pdo->prepare("SELECT * FROM features WHERE language = ? ORDER BY base_feature_id ASC");
            $stmt->execute([$lang]);
            echo json_encode(['success' => true, 'features' => $stmt->fetchAll()], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'save_feature':
        try {
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $id = intval($data['id'] ?? 0);
            $fields = [
                'title'       => trim($data['title'] ?? ''),
                'description' => trim($data['description'] ?? ''),
                'icon'        => trim($data['icon'] ?? ''),
                'image'       => trim($data['image'] ?? ''),
                'language'    => $data['language'] ?? 'km',
            ];
            if ($id > 0) {
                $set = implode(', ', array_map(fn($k) => "$k = ?", array_keys($fields)));
                $pdo->prepare("UPDATE features SET $set WHERE id = ?")->execute([...array_values($fields), $id]);
                echo json_encode(['success' => true, 'action' => 'updated']);
            } else {
                $maxBase = $pdo->query("SELECT COALESCE(MAX(base_feature_id),0) FROM features")->fetchColumn();
                $fields['base_feature_id'] = $maxBase + 1;
                $cols = implode(', ', array_keys($fields));
                $ph = implode(', ', array_fill(0, count($fields), '?'));
                $pdo->prepare("INSERT INTO features ($cols) VALUES ($ph)")->execute(array_values($fields));
                echo json_encode(['success' => true, 'action' => 'created', 'id' => $pdo->lastInsertId()]);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'delete_feature':
        try {
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $id = intval($data['id'] ?? 0);
            $pdo->prepare("DELETE FROM features WHERE id = ?")->execute([$id]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    // ── SETTINGS ────────────────────────────────
    case 'get_all_settings':
        try {
            $lang = $_GET['lang'] ?? null;
            if ($lang) {
                $stmt = $pdo->prepare("SELECT * FROM settings WHERE language = ? OR language = 'en' ORDER BY language DESC");
                $stmt->execute([$lang]);
            } else {
                $stmt = $pdo->query("SELECT * FROM settings ORDER BY language, setting_key");
            }
            $rows = $stmt->fetchAll();
            // Build key => value map
            $map = [];
            foreach ($rows as $r) {
                $map[$r['language']][$r['setting_key']] = $r['setting_value'];
            }
            echo json_encode(['success' => true, 'settings' => $map, 'raw' => $rows], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'save_setting':
        try {
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $key   = trim($data['key'] ?? '');
            $value = $data['value'] ?? '';
            $lang  = $data['language'] ?? $data['lang'] ?? 'km';
            if (empty($key)) { echo json_encode(['success' => false, 'error' => 'Key required']); break; }

            $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value, language) VALUES (?,?,?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $stmt->execute([$key, $value, $lang]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'save_settings_bulk':
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            // data = [ { key, value, language }, ... ]
            if (!is_array($data) || empty($data)) { echo json_encode(['success' => false, 'error' => 'No data']); break; }
            $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value, language) VALUES (?,?,?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $pdo->beginTransaction();
            foreach ($data as $item) {
                $key  = trim($item['key'] ?? '');
                $val  = $item['value'] ?? '';
                $lang = $item['language'] ?? 'km';
                if ($key) $stmt->execute([$key, $val, $lang]);
            }
            $pdo->commit();
            echo json_encode(['success' => true, 'saved' => count($data)]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    // ── ABOUT ───────────────────────────────────
    case 'get_about':
        try {
            $stmt = $pdo->query("SELECT * FROM about ORDER BY id DESC LIMIT 1");
            $about = $stmt->fetch();
            echo json_encode(['success' => true, 'about' => $about ?: []], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'save_about':
        try {
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $id = intval($data['id'] ?? 0);
            $fields = [];
            $allowed = ['title','content','purpose_title','purpose_content','mission_title','mission_content','image','person_image','language'];
            foreach ($allowed as $k) {
                if (isset($data[$k])) $fields[$k] = $data[$k];
            }
            if ($id > 0) {
                $set = implode(', ', array_map(fn($k) => "$k = ?", array_keys($fields)));
                $pdo->prepare("UPDATE about SET $set WHERE id = ?")->execute([...array_values($fields), $id]);
                echo json_encode(['success' => true, 'action' => 'updated']);
            } else {
                $cols = implode(', ', array_keys($fields));
                $ph = implode(', ', array_fill(0, count($fields), '?'));
                $pdo->prepare("INSERT INTO about ($cols) VALUES ($ph)")->execute(array_values($fields));
                echo json_encode(['success' => true, 'action' => 'created', 'id' => $pdo->lastInsertId()]);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    // ── ADMIN USERS ─────────────────────────────
    case 'get_admin_users':
        try {
            $stmt = $pdo->query("SELECT id, username, full_name, email, status, last_login, created_at FROM admin_users ORDER BY id ASC");
            echo json_encode(['success' => true, 'admins' => $stmt->fetchAll()], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'save_admin_user':
        try {
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $id = intval($data['id'] ?? 0);
            $fields = [
                'username'  => trim($data['username'] ?? ''),
                'full_name' => trim($data['full_name'] ?? ''),
                'email'     => trim($data['email'] ?? ''),
                'status'    => in_array($data['status'] ?? '', ['active','inactive']) ? $data['status'] : 'active',
            ];
            if (!empty($data['password'])) {
                $fields['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
            }
            if ($id > 0) {
                $set = implode(', ', array_map(fn($k) => "$k = ?", array_keys($fields)));
                $pdo->prepare("UPDATE admin_users SET $set WHERE id = ?")->execute([...array_values($fields), $id]);
                echo json_encode(['success' => true, 'action' => 'updated']);
            } else {
                if (empty($fields['password'])) { echo json_encode(['success' => false, 'error' => 'Password required for new admin']); break; }
                $cols = implode(', ', array_keys($fields));
                $ph = implode(', ', array_fill(0, count($fields), '?'));
                $pdo->prepare("INSERT INTO admin_users ($cols) VALUES ($ph)")->execute(array_values($fields));
                echo json_encode(['success' => true, 'action' => 'created', 'id' => $pdo->lastInsertId()]);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'delete_admin_user':
        try {
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $id = intval($data['id'] ?? 0);
            if ($id === (int)$_SESSION['admin_id']) {
                echo json_encode(['success' => false, 'error' => 'Cannot delete your own account']);
                break;
            }
            $pdo->prepare("DELETE FROM admin_users WHERE id = ?")->execute([$id]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    // ── IMAGE UPLOAD ────────────────────────────
    case 'upload_image':
        try {
            if (!isset($_FILES['image'])) { echo json_encode(['success' => false, 'error' => 'No file uploaded']); break; }
            $file = $_FILES['image'];
            $allowedTypes = ['image/jpeg','image/jpg','image/png','image/gif','image/webp'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mimeType, $allowedTypes)) {
                echo json_encode(['success' => false, 'error' => 'Invalid file type. Allowed: JPG, PNG, GIF, WebP']);
                break;
            }
            if ($file['size'] > 10 * 1024 * 1024) {
                echo json_encode(['success' => false, 'error' => 'File too large (max 10MB)']);
                break;
            }

            $type = $_POST['type'] ?? 'product'; // product, logo, banner
            $ext = match($mimeType) {
                'image/jpeg', 'image/jpg' => 'jpg',
                'image/png'  => 'png',
                'image/gif'  => 'gif',
                'image/webp' => 'webp',
                default      => 'jpg'
            };

            $uploadDir = match($type) {
                'logo'   => __DIR__ . '/uploads/',
                'banner' => __DIR__ . '/uploads/banners/',
                default  => __DIR__ . '/uploads/',
            };

            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

            $prefix = match($type) {
                'logo'   => 'company-logo-',
                'banner' => 'banner-',
                default  => 'product-',
            };

            $filename = $prefix . time() . '.' . $ext;
            $targetPath = $uploadDir . $filename;

            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                // Compress if image_utils available and large
                if (file_exists(__DIR__ . '/../app/Config/image_utils.php') && $file['size'] > 100 * 1024) {
                    require_once __DIR__ . '/../app/Config/image_utils.php';
                    if (function_exists('compressImage')) {
                        compressImage($targetPath, $targetPath, 85, 1920, 1920);
                    }
                }
                $relPath = match($type) {
                    'logo'   => '/kouprey/public/uploads/' . $filename,
                    'banner' => '/kouprey/public/uploads/banners/' . $filename,
                    default  => '/kouprey/public/uploads/' . $filename,
                };
                echo json_encode(['success' => true, 'path' => $relPath, 'filename' => $filename]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to save file']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'error' => "Unknown action: $action"]);
}

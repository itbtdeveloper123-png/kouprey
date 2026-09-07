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
            $catId = !empty($_GET['category_id']) ? intval($_GET['category_id']) : null;

            $sql = "
                SELECT
                    COALESCE(p_curr.id, p_other.id) as id,
                    bp.base_product_id,
                    COALESCE(p_curr.name, p_other.name) as name,
                    p_en.name as name_en,
                    p_km.name as name_km,
                    COALESCE(p_curr.description, p_other.description) as description,
                    p_en.description as description_en,
                    p_km.description as description_km,
                    COALESCE(p_curr.price, p_other.price, 0) as price,
                    COALESCE(p_curr.featured, p_other.featured, 0) as featured,
                    COALESCE(p_curr.best_seller, p_other.best_seller, 0) as best_seller,
                    COALESCE(p_curr.enabled, p_other.enabled, 1) as enabled,
                    COALESCE(p_curr.image, p_other.image, '') as image,
                    COALESCE(p_curr.category_id, p_other.category_id) as category_id,
                    c.name as category_name,
                    c.base_category_id,
                    COALESCE(p_curr.custom_fields, p_other.custom_fields, '{}') as custom_fields,
                    COALESCE(p_curr.sort_order, p_other.sort_order, 0) as sort_order,
                    COALESCE(p_curr.weight, p_other.weight, '') as weight,
                    COALESCE(p_curr.roast_level, p_other.roast_level, '') as roast_level,
                    COALESCE(p_curr.detailed_description, p_other.detailed_description, '') as detailed_description,
                    COALESCE(p_curr.ingredients, p_other.ingredients, '') as ingredients,
                    COALESCE(p_curr.origin, p_other.origin, '') as origin,
                    COALESCE(p_curr.brewing_instructions, p_other.brewing_instructions, '') as brewing_instructions,
                    COALESCE(p_curr.tasting_notes, p_other.tasting_notes, '') as tasting_notes,
                    COALESCE(rs.avg_rating, 0) as avg_rating,
                    COALESCE(rs.review_count, 0) as review_count
                FROM (
                    SELECT DISTINCT base_product_id
                    FROM products
                    WHERE base_product_id IS NOT NULL AND base_product_id > 0
                ) bp
                LEFT JOIN products p_curr ON bp.base_product_id = p_curr.base_product_id AND p_curr.language = ?
                LEFT JOIN products p_other ON bp.base_product_id = p_other.base_product_id AND p_other.language != ?
                LEFT JOIN products p_en ON bp.base_product_id = p_en.base_product_id AND p_en.language = 'en'
                LEFT JOIN products p_km ON bp.base_product_id = p_km.base_product_id AND p_km.language = 'km'
                LEFT JOIN categories c ON COALESCE(p_curr.category_id, p_other.category_id) = c.id
                LEFT JOIN (
                    SELECT pr.base_product_id, AVG(r.rating) avg_rating, COUNT(r.id) review_count
                    FROM reviews r JOIN products pr ON r.product_id = pr.id
                    GROUP BY pr.base_product_id
                ) rs ON bp.base_product_id = rs.base_product_id
            ";

            $where = [];
            $params = [$lang, $lang];

            if ($catId) {
                $where[] = "(p_curr.category_id = ? OR p_other.category_id = ?)";
                $params[] = $catId;
                $params[] = $catId;
            }
            if ($search) {
                $where[] = "(p_curr.name LIKE ? OR p_curr.description LIKE ? OR p_other.name LIKE ? OR p_other.description LIKE ?)";
                $searchPattern = "%$search%";
                $params[] = $searchPattern;
                $params[] = $searchPattern;
                $params[] = $searchPattern;
                $params[] = $searchPattern;
            }

            if (!empty($where)) {
                $sql .= " WHERE " . implode(' AND ', $where);
            }

            $sql .= " ORDER BY COALESCE(p_curr.sort_order, p_other.sort_order, 0) ASC, bp.base_product_id DESC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($products as &$p) {
                $cf = json_decode($p['custom_fields'] ?? '{}', true);
                if (!is_array($cf)) $cf = [];
                $p['show_in_collection'] = $cf['show_in_collection'] ?? true;
                $p['parsed_custom_fields'] = $cf;
            }
            unset($p);

            echo json_encode(['success' => true, 'count' => count($products), 'products' => $products], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'get_product_data':
        try {
            $base_product_id = intval($_GET['base_product_id'] ?? $_POST['base_product_id'] ?? 0);
            if (!$base_product_id) {
                echo json_encode(['success' => false, 'error' => 'base_product_id required']);
                break;
            }
            
            $stmt = $pdo->prepare("SELECT * FROM products WHERE (base_product_id = ? OR id = ?) AND language = 'en'");
            $stmt->execute([$base_product_id, $base_product_id]);
            $en = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

            $stmt = $pdo->prepare("SELECT * FROM products WHERE (base_product_id = ? OR id = ?) AND language = 'km'");
            $stmt->execute([$base_product_id, $base_product_id]);
            $km = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

            if (!$en && !$km) {
                $s = $pdo->prepare("SELECT * FROM products WHERE id = ?");
                $s->execute([$base_product_id]);
                $single = $s->fetch(PDO::FETCH_ASSOC);
                if ($single) {
                    if ($single['language'] === 'en') $en = $single;
                    else $km = $single;
                }
            }

            $stmt = $pdo->prepare("
                SELECT pr.*, p.name as product_name, p.price, p.image
                FROM product_related pr
                LEFT JOIN products p ON pr.related_product_id = p.id AND p.language = 'en'
                WHERE pr.product_id = ?
                ORDER BY pr.sort_order ASC, pr.id ASC
            ");
            $stmt->execute([$base_product_id]);
            $related = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            echo json_encode([
                'success' => true,
                'base_product_id' => $base_product_id,
                'en' => $en,
                'km' => $km,
                'related' => $related
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'save_product_full':
        try {
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $base_product_id = intval($data['base_product_id'] ?? 0);
            $is_new = ($base_product_id <= 0);

            if ($is_new) {
                $maxBase = $pdo->query("SELECT COALESCE(MAX(base_product_id), 0) FROM products")->fetchColumn();
                $base_product_id = $maxBase + 1;
            }

            $price = floatval($data['price'] ?? 0);
            $base_category_id = !empty($data['base_category_id']) ? intval($data['base_category_id']) : null;
            $category_id = !empty($data['category_id']) ? intval($data['category_id']) : null;

            if ($category_id && !$base_category_id) {
                $catStmt = $pdo->prepare("SELECT base_category_id FROM categories WHERE id = ?");
                $catStmt->execute([$category_id]);
                $c = $catStmt->fetch();
                if ($c && !empty($c['base_category_id'])) {
                    $base_category_id = intval($c['base_category_id']);
                }
            }

            $getCatIdForLang = function($base_cat_id, $lang) use ($pdo) {
                if (!$base_cat_id) return null;
                $s = $pdo->prepare("SELECT id FROM categories WHERE base_category_id = ? AND language = ? LIMIT 1");
                $s->execute([$base_cat_id, $lang]);
                $r = $s->fetch();
                return $r ? $r['id'] : null;
            };

            $featured = !empty($data['featured']) ? 1 : 0;
            $best_seller = !empty($data['best_seller']) ? 1 : 0;
            $enabled = isset($data['enabled']) ? intval($data['enabled']) : 1;
            $image = trim($data['image'] ?? '');
            $roast_level = trim($data['roast_level'] ?? '');

            $custom_fields = $data['custom_fields'] ?? [];
            if (!is_array($custom_fields)) {
                $custom_fields = json_decode($custom_fields, true) ?: [];
            }
            if (isset($data['show_in_collection'])) {
                $custom_fields['show_in_collection'] = (bool)$data['show_in_collection'];
            }
            $custom_fields_json = json_encode($custom_fields, JSON_UNESCAPED_UNICODE);

            $en_name = trim($data['name_en'] ?? $data['name'] ?? '');
            $en_desc = trim($data['description_en'] ?? $data['description'] ?? '');
            $en_detailed = trim($data['detailed_description_en'] ?? '');
            $en_ingredients = trim($data['ingredients_en'] ?? '');
            $en_origin = trim($data['origin_en'] ?? '');
            $en_brewing = trim($data['brewing_instructions_en'] ?? '');
            $en_tasting = trim($data['tasting_notes_en'] ?? '');
            $en_weight = trim($data['weight_en'] ?? '');

            $km_name = trim($data['name_km'] ?? '') ?: $en_name;
            $km_desc = trim($data['description_km'] ?? '') ?: $en_desc;
            $km_detailed = trim($data['detailed_description_km'] ?? '') ?: $en_detailed;
            $km_ingredients = trim($data['ingredients_km'] ?? '') ?: $en_ingredients;
            $km_origin = trim($data['origin_km'] ?? '') ?: $en_origin;
            $km_brewing = trim($data['brewing_instructions_km'] ?? '') ?: $en_brewing;
            $km_tasting = trim($data['tasting_notes_km'] ?? '') ?: $en_tasting;
            $km_weight = trim($data['weight_km'] ?? '') ?: $en_weight;

            $cat_id_en = $getCatIdForLang($base_category_id, 'en');
            $cat_id_km = $getCatIdForLang($base_category_id, 'km');

            // Upsert EN
            $stmt = $pdo->prepare("SELECT id, image FROM products WHERE base_product_id = ? AND language = 'en'");
            $stmt->execute([$base_product_id]);
            $row_en = $stmt->fetch();
            $final_image = $image ?: ($row_en['image'] ?? '');

            if ($row_en) {
                $up = $pdo->prepare("
                    UPDATE products SET
                        name = ?, description = ?, price = ?, category_id = ?, featured = ?, best_seller = ?, enabled = ?,
                        image = ?, detailed_description = ?, ingredients = ?, origin = ?, brewing_instructions = ?,
                        tasting_notes = ?, weight = ?, roast_level = ?, custom_fields = ?
                    WHERE id = ?
                ");
                $up->execute([
                    $en_name, $en_desc, $price, $cat_id_en, $featured, $best_seller, $enabled,
                    $final_image, $en_detailed, $en_ingredients, $en_origin, $en_brewing,
                    $en_tasting, $en_weight, $roast_level, $custom_fields_json, $row_en['id']
                ]);
            } else {
                $ins = $pdo->prepare("
                    INSERT INTO products (
                        name, description, price, category_id, featured, best_seller, enabled,
                        image, detailed_description, ingredients, origin, brewing_instructions,
                        tasting_notes, weight, roast_level, custom_fields, language, base_product_id
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'en', ?)
                ");
                $ins->execute([
                    $en_name, $en_desc, $price, $cat_id_en, $featured, $best_seller, $enabled,
                    $final_image, $en_detailed, $en_ingredients, $en_origin, $en_brewing,
                    $en_tasting, $en_weight, $roast_level, $custom_fields_json, $base_product_id
                ]);
            }

            // Upsert KM
            $stmt = $pdo->prepare("SELECT id FROM products WHERE base_product_id = ? AND language = 'km'");
            $stmt->execute([$base_product_id]);
            $row_km = $stmt->fetch();

            if ($row_km) {
                $up = $pdo->prepare("
                    UPDATE products SET
                        name = ?, description = ?, price = ?, category_id = ?, featured = ?, best_seller = ?, enabled = ?,
                        image = ?, detailed_description = ?, ingredients = ?, origin = ?, brewing_instructions = ?,
                        tasting_notes = ?, weight = ?, roast_level = ?, custom_fields = ?
                    WHERE id = ?
                ");
                $up->execute([
                    $km_name, $km_desc, $price, $cat_id_km, $featured, $best_seller, $enabled,
                    $final_image, $km_detailed, $km_ingredients, $km_origin, $km_brewing,
                    $km_tasting, $km_weight, $roast_level, $custom_fields_json, $row_km['id']
                ]);
            } else {
                $ins = $pdo->prepare("
                    INSERT INTO products (
                        name, description, price, category_id, featured, best_seller, enabled,
                        image, detailed_description, ingredients, origin, brewing_instructions,
                        tasting_notes, weight, roast_level, custom_fields, language, base_product_id
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'km', ?)
                ");
                $ins->execute([
                    $km_name, $km_desc, $price, $cat_id_km, $featured, $best_seller, $enabled,
                    $final_image, $km_detailed, $km_ingredients, $km_origin, $km_brewing,
                    $km_tasting, $km_weight, $roast_level, $custom_fields_json, $base_product_id
                ]);
            }

            // Process related products if provided
            if (isset($data['related_products'])) {
                $pdo->prepare("DELETE FROM product_related WHERE product_id = ?")->execute([$base_product_id]);
                $relItems = is_array($data['related_products']) ? $data['related_products'] : json_decode($data['related_products'], true);
                if (is_array($relItems)) {
                    $sort = 0;
                    foreach ($relItems as $rel) {
                        $sort++;
                        $rel_base_id = $rel['base_id'] ?? $rel['base_product_id'] ?? $rel['related_product_id'] ?? null;
                        $custom_url = $rel['custom_url'] ?? '';
                        $custom_name = $rel['custom_name'] ?? $rel['name'] ?? '';
                        $custom_image = $rel['custom_image'] ?? '';
                        $custom_image_url = $rel['custom_image_url'] ?? '';

                        if ($rel_base_id && strpos((string)$rel_base_id, 'custom_') === 0) {
                            $pdo->prepare("
                                INSERT INTO product_related (product_id, related_product_id, custom_image, custom_image_url, custom_url, custom_name, sort_order)
                                VALUES (?, NULL, ?, ?, ?, ?, ?)
                            ")->execute([$base_product_id, $custom_image, $custom_image_url, $custom_url, $custom_name, $sort]);
                        } else if ($rel_base_id) {
                            $relStmt = $pdo->prepare("SELECT id FROM products WHERE base_product_id = ? AND language = 'en' LIMIT 1");
                            $relStmt->execute([$rel_base_id]);
                            $relProduct = $relStmt->fetch();
                            if ($relProduct) {
                                $pdo->prepare("
                                    INSERT INTO product_related (product_id, related_product_id, custom_image, custom_image_url, custom_url, sort_order)
                                    VALUES (?, ?, ?, ?, ?, ?)
                                ")->execute([$base_product_id, $relProduct['id'], $custom_image, $custom_image_url, $custom_url, $sort]);
                            }
                        }
                    }
                }
            }

            echo json_encode(['success' => true, 'base_product_id' => $base_product_id, 'is_new' => $is_new]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'toggle_product_status':
        try {
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $id = intval($data['id'] ?? 0);
            $base_product_id = intval($data['base_product_id'] ?? 0);
            $field = trim($data['field'] ?? '');

            if (!$base_product_id && $id) {
                $stmt = $pdo->prepare("SELECT base_product_id FROM products WHERE id = ?");
                $stmt->execute([$id]);
                $base_product_id = $stmt->fetchColumn() ?: 0;
            }

            if (!$base_product_id) {
                echo json_encode(['success' => false, 'error' => 'Product not found']);
                break;
            }

            if ($field === 'featured') {
                $stmt = $pdo->prepare("UPDATE products SET featured = CASE WHEN featured = 1 THEN 0 ELSE 1 END WHERE base_product_id = ?");
                $stmt->execute([$base_product_id]);
                $newVal = $pdo->query("SELECT featured FROM products WHERE base_product_id = {$base_product_id} LIMIT 1")->fetchColumn();
                echo json_encode(['success' => true, 'base_product_id' => $base_product_id, 'field' => 'featured', 'value' => (int)$newVal]);
            } elseif ($field === 'best_seller') {
                $stmt = $pdo->prepare("UPDATE products SET best_seller = CASE WHEN best_seller = 1 THEN 0 ELSE 1 END WHERE base_product_id = ?");
                $stmt->execute([$base_product_id]);
                $newVal = $pdo->query("SELECT best_seller FROM products WHERE base_product_id = {$base_product_id} LIMIT 1")->fetchColumn();
                echo json_encode(['success' => true, 'base_product_id' => $base_product_id, 'field' => 'best_seller', 'value' => (int)$newVal]);
            } elseif ($field === 'enabled') {
                $stmt = $pdo->prepare("UPDATE products SET enabled = CASE WHEN enabled = 1 THEN 0 ELSE 1 END WHERE base_product_id = ?");
                $stmt->execute([$base_product_id]);
                $newVal = $pdo->query("SELECT enabled FROM products WHERE base_product_id = {$base_product_id} LIMIT 1")->fetchColumn();
                echo json_encode(['success' => true, 'base_product_id' => $base_product_id, 'field' => 'enabled', 'value' => (int)$newVal]);
            } elseif ($field === 'collection') {
                $stmt = $pdo->prepare("SELECT custom_fields FROM products WHERE base_product_id = ? LIMIT 1");
                $stmt->execute([$base_product_id]);
                $cf = json_decode($stmt->fetchColumn() ?: '{}', true) ?: [];
                $cur = $cf['show_in_collection'] ?? true;
                $cf['show_in_collection'] = !$cur;
                $newJson = json_encode($cf, JSON_UNESCAPED_UNICODE);
                $pdo->prepare("UPDATE products SET custom_fields = ? WHERE base_product_id = ?")->execute([$newJson, $base_product_id]);
                echo json_encode(['success' => true, 'base_product_id' => $base_product_id, 'field' => 'collection', 'value' => $cf['show_in_collection']]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Invalid field']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'reorder_products':
        try {
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $order = $data['order'] ?? [];
            if (is_array($order)) {
                $pdo->beginTransaction();
                foreach ($order as $item) {
                    $baseId = intval($item['base_product_id'] ?? 0);
                    $sort = intval($item['sort_order'] ?? 0);
                    if ($baseId > 0) {
                        $pdo->prepare("UPDATE products SET sort_order = ? WHERE base_product_id = ?")->execute([$sort, $baseId]);
                    }
                }
                $pdo->commit();
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Invalid order array']);
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
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
                $pdo->prepare("DELETE FROM product_related WHERE product_id = ?")->execute([$base_product_id]);
                echo json_encode(['success' => true, 'deleted' => 'all_languages']);
            } elseif ($id) {
                $stmt = $pdo->prepare("SELECT base_product_id FROM products WHERE id = ?");
                $stmt->execute([$id]);
                $bp = $stmt->fetchColumn();
                if ($bp) {
                    $pdo->prepare("DELETE FROM products WHERE base_product_id = ?")->execute([$bp]);
                    $pdo->prepare("DELETE FROM product_related WHERE product_id = ?")->execute([$bp]);
                } else {
                    $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$id]);
                }
                echo json_encode(['success' => true, 'deleted' => 'all_languages']);
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
            $stmt = $pdo->prepare("
                SELECT c.*, COALESCE(pc.product_count, 0) as product_count
                FROM categories c
                LEFT JOIN (
                    SELECT category_id, COUNT(*) as product_count
                    FROM products
                    GROUP BY category_id
                ) pc ON c.id = pc.category_id
                WHERE c.language = ?
                ORDER BY c.name ASC
            ");
            $stmt->execute([$lang]);
            echo json_encode(['success' => true, 'categories' => $stmt->fetchAll(PDO::FETCH_ASSOC)], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'get_category_data':
        try {
            $base_category_id = intval($_GET['base_category_id'] ?? $_POST['base_category_id'] ?? 0);
            $id = intval($_GET['id'] ?? $_POST['id'] ?? 0);
            if (!$base_category_id && $id) {
                $stmt = $pdo->prepare("SELECT base_category_id FROM categories WHERE id = ?");
                $stmt->execute([$id]);
                $base_category_id = $stmt->fetchColumn() ?: 0;
            }

            $en = null; $km = null;
            if ($base_category_id) {
                $s = $pdo->prepare("SELECT * FROM categories WHERE base_category_id = ? AND language = 'en'");
                $s->execute([$base_category_id]);
                $en = $s->fetch(PDO::FETCH_ASSOC);

                $s = $pdo->prepare("SELECT * FROM categories WHERE base_category_id = ? AND language = 'km'");
                $s->execute([$base_category_id]);
                $km = $s->fetch(PDO::FETCH_ASSOC);
            }

            echo json_encode([
                'success' => true,
                'base_category_id' => $base_category_id,
                'en' => $en,
                'km' => $km
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'save_category_full':
        try {
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $base_category_id = intval($data['base_category_id'] ?? 0);
            $name_en = trim($data['name_en'] ?? $data['name'] ?? '');
            $name_km = trim($data['name_km'] ?? '') ?: $name_en;
            $description_en = trim($data['description_en'] ?? $data['description'] ?? '');
            $description_km = trim($data['description_km'] ?? '') ?: $description_en;
            $image = trim($data['image'] ?? '');

            if ($base_category_id <= 0) {
                // New category
                $stmt = $pdo->prepare("INSERT INTO categories (name, description, image, language, base_category_id) VALUES (?, ?, ?, 'en', NULL)");
                $stmt->execute([$name_en, $description_en, $image]);
                $base_category_id = $pdo->lastInsertId();

                $pdo->prepare("UPDATE categories SET base_category_id = ? WHERE id = ?")->execute([$base_category_id, $base_category_id]);

                $stmt = $pdo->prepare("INSERT INTO categories (name, description, image, language, base_category_id) VALUES (?, ?, ?, 'km', ?)");
                $stmt->execute([$name_km, $description_km, $image, $base_category_id]);

                echo json_encode(['success' => true, 'action' => 'created', 'base_category_id' => $base_category_id]);
            } else {
                // Update English
                $stmt = $pdo->prepare("SELECT id FROM categories WHERE base_category_id = ? AND language = 'en'");
                $stmt->execute([$base_category_id]);
                $en_id = $stmt->fetchColumn();

                if ($en_id) {
                    $pdo->prepare("UPDATE categories SET name = ?, description = ?, image = ? WHERE id = ?")->execute([$name_en, $description_en, $image, $en_id]);
                } else {
                    $pdo->prepare("INSERT INTO categories (name, description, image, language, base_category_id) VALUES (?, ?, ?, 'en', ?)")->execute([$name_en, $description_en, $image, $base_category_id]);
                }

                // Update Khmer
                $stmt = $pdo->prepare("SELECT id FROM categories WHERE base_category_id = ? AND language = 'km'");
                $stmt->execute([$base_category_id]);
                $km_id = $stmt->fetchColumn();

                if ($km_id) {
                    $pdo->prepare("UPDATE categories SET name = ?, description = ?, image = ? WHERE id = ?")->execute([$name_km, $description_km, $image, $km_id]);
                } else {
                    $pdo->prepare("INSERT INTO categories (name, description, image, language, base_category_id) VALUES (?, ?, ?, 'km', ?)")->execute([$name_km, $description_km, $image, $base_category_id]);
                }

                echo json_encode(['success' => true, 'action' => 'updated', 'base_category_id' => $base_category_id]);
            }
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
            if (!empty($data['image'])) $fields['image'] = trim($data['image']);

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
            $base_category_id = intval($data['base_category_id'] ?? 0);

            if (!$base_category_id && $id) {
                $stmt = $pdo->prepare("SELECT base_category_id FROM categories WHERE id = ?");
                $stmt->execute([$id]);
                $base_category_id = $stmt->fetchColumn() ?: 0;
            }

            if ($base_category_id) {
                $pdo->prepare("UPDATE products SET category_id = NULL WHERE category_id IN (SELECT id FROM categories WHERE base_category_id = ?)")->execute([$base_category_id]);
                $pdo->prepare("DELETE FROM categories WHERE base_category_id = ?")->execute([$base_category_id]);
                echo json_encode(['success' => true, 'deleted' => 'all_languages']);
            } elseif ($id) {
                $pdo->prepare("UPDATE products SET category_id = NULL WHERE category_id = ?")->execute([$id]);
                $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$id]);
                echo json_encode(['success' => true, 'deleted' => 'single']);
            } else {
                echo json_encode(['success' => false, 'error' => 'No category ID provided']);
            }
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

            // Fetch actual existing columns in features table
            $existingCols = [];
            try {
                $chk = $pdo->query("SHOW COLUMNS FROM features");
                if ($chk) {
                    $existingCols = $chk->fetchAll(PDO::FETCH_COLUMN);
                }
            } catch (Exception $e) {}

            // Auto-migrate: Attempt to add icon and image columns if database permissions allow
            if (!empty($existingCols)) {
                if (!in_array('icon', $existingCols)) {
                    try {
                        $pdo->exec("ALTER TABLE features ADD COLUMN icon VARCHAR(255) NULL DEFAULT ''");
                        $existingCols[] = 'icon';
                    } catch (Exception $ignored) {}
                }
                if (!in_array('image', $existingCols)) {
                    try {
                        $pdo->exec("ALTER TABLE features ADD COLUMN image VARCHAR(255) NULL DEFAULT ''");
                        $existingCols[] = 'image';
                    } catch (Exception $ignored) {}
                }
            }

            $candidateFields = [
                'title'       => trim($data['title'] ?? ''),
                'description' => trim($data['description'] ?? ''),
                'icon'        => trim($data['icon'] ?? ''),
                'image'       => trim($data['image'] ?? ''),
                'language'    => $data['language'] ?? 'km',
            ];

            // Filter candidate fields to ONLY include columns that actually exist in the table
            $fields = [];
            foreach ($candidateFields as $col => $val) {
                if (empty($existingCols)) {
                    // Fallback to safe core columns if schema inspection failed
                    if (!in_array($col, ['icon', 'image'])) {
                        $fields[$col] = $val;
                    }
                } elseif (in_array($col, $existingCols)) {
                    $fields[$col] = $val;
                }
            }

            if ($id > 0) {
                $set = implode(', ', array_map(fn($k) => "$k = ?", array_keys($fields)));
                $pdo->prepare("UPDATE features SET $set WHERE id = ?")->execute([...array_values($fields), $id]);
                echo json_encode(['success' => true, 'action' => 'updated']);
            } else {
                if (empty($existingCols) || in_array('base_feature_id', $existingCols)) {
                    $baseId = intval($data['base_feature_id'] ?? 0);
                    if ($baseId <= 0) {
                        $maxBase = $pdo->query("SELECT COALESCE(MAX(base_feature_id),0) FROM features")->fetchColumn();
                        $baseId = $maxBase + 1;
                    }
                    $fields['base_feature_id'] = $baseId;
                }
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
            if ($id > 0) {
                try {
                    $pdo->prepare("DELETE FROM feature_products WHERE feature_id = ?")->execute([$id]);
                } catch (Exception $ignored) {}
                $pdo->prepare("DELETE FROM features WHERE id = ?")->execute([$id]);
            }
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    // ── SETTINGS ────────────────────────────────
    case 'get_all_settings':
        try {
            $stmt = $pdo->query("SELECT * FROM settings ORDER BY category, setting_key");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Build key => value map and category-grouped structure
            $map = ['km' => [], 'en' => []];
            $grouped = [];
            foreach ($rows as $r) {
                $lang = $r['language'] ?? 'km';
                $key = $r['setting_key'];
                $cat = !empty($r['category']) ? $r['category'] : 'general';
                $val = $r['setting_value'] ?? '';

                $map[$lang][$key] = $val;

                if (!isset($grouped[$cat][$key])) {
                    $grouped[$cat][$key] = [
                        'key' => $key,
                        'category' => $cat,
                        'type' => $r['setting_type'] ?? 'text',
                        'description' => $r['description'] ?? '',
                        'values' => ['en' => '', 'km' => '']
                    ];
                }
                $grouped[$cat][$key]['values'][$lang] = $val;
            }

            echo json_encode(['success' => true, 'settings' => $map, 'grouped' => $grouped, 'raw' => $rows], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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
            // data = [ { key, value, language, category }, ... ]
            if (!is_array($data) || empty($data)) { echo json_encode(['success' => false, 'error' => 'No data']); break; }
            
            $hasCat = false;
            try {
                $chk = $pdo->query("SHOW COLUMNS FROM settings LIKE 'category'");
                $hasCat = ($chk && $chk->rowCount() > 0);
            } catch (Exception $e) {}

            if ($hasCat) {
                $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value, language, category) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), category = IF(VALUES(category) != '', VALUES(category), category)");
            } else {
                $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value, language) VALUES (?,?,?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            }

            $pdo->beginTransaction();
            foreach ($data as $item) {
                $key  = trim($item['key'] ?? '');
                $val  = $item['value'] ?? '';
                $lang = $item['language'] ?? 'km';
                $cat  = trim($item['category'] ?? '');
                if ($key) {
                    if ($hasCat) {
                        $stmt->execute([$key, $val, $lang, $cat]);
                    } else {
                        $stmt->execute([$key, $val, $lang]);
                    }
                }
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
            if ($file['size'] > 15 * 1024 * 1024) {
                echo json_encode(['success' => false, 'error' => 'File too large (max 15MB)']);
                break;
            }

            $type = $_POST['type'] ?? 'product'; // product, logo, banner
            $removeBgParam = $_POST['remove_bg'] ?? null;
            $removeBg = ($removeBgParam !== null)
                ? filter_var($removeBgParam, FILTER_VALIDATE_BOOLEAN)
                : ($type === 'product');

            $ext = match($mimeType) {
                'image/jpeg', 'image/jpg' => 'jpg',
                'image/png'  => 'png',
                'image/gif'  => 'gif',
                'image/webp' => 'webp',
                default      => 'jpg'
            };

            $uploadDir = match($type) {
                'logo'     => __DIR__ . '/uploads/',
                'banner'   => __DIR__ . '/uploads/banners/',
                'product'  => __DIR__ . '/assets/images/products/',
                default    => __DIR__ . '/uploads/',
            };

            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

            $prefix = match($type) {
                'logo'     => 'company-logo-',
                'banner'   => 'banner-',
                'product'  => 'product-',
                default    => 'file-',
            };

            $uniqueId = uniqid();
            $timestamp = time();
            $filename = $prefix . $uniqueId . '_' . $timestamp . '.' . $ext;
            $targetPath = $uploadDir . $filename;

            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                require_once __DIR__ . '/../app/Config/image_utils.php';

                $bgRemoved = false;
                $bgWarning = null;
                $webpFilename = $prefix . $uniqueId . '_' . $timestamp . '.webp';
                $webpPath = $uploadDir . $webpFilename;

                if ($removeBg && function_exists('removeBgAndConvertToWebp')) {
                    $bgResult = removeBgAndConvertToWebp($targetPath, $webpPath, null, [
                        'size' => 'auto',
                        'type' => ($type === 'product' ? 'product' : 'auto'),
                        'quality' => 90
                    ]);

                    if (!empty($bgResult['success']) && file_exists($webpPath) && filesize($webpPath) > 0) {
                        if ($targetPath !== $webpPath && file_exists($targetPath)) {
                            @unlink($targetPath);
                        }
                        $filename = $webpFilename;
                        $targetPath = $webpPath;
                        $bgRemoved = true;
                    } else {
                        $bgWarning = $bgResult['error'] ?? 'Remove.bg failed';
                        error_log("Remove.bg failed for {$targetPath}: {$bgWarning}. Falling back to standard WebP.");
                        
                        // Fallback: convert original to WebP
                        if ($ext !== 'webp' && function_exists('imagewebp')) {
                            if (function_exists('compressImage') && compressImage($targetPath, $webpPath, 88, 1920, 1920)) {
                                if (file_exists($targetPath) && $targetPath !== $webpPath) {
                                    @unlink($targetPath);
                                }
                                $filename = $webpFilename;
                                $targetPath = $webpPath;
                            }
                        }
                    }
                } else {
                    // Standard WebP conversion if not removing BG
                    if ($ext !== 'webp' && function_exists('imagewebp')) {
                        if (function_exists('compressImage') && compressImage($targetPath, $webpPath, 88, 1920, 1920)) {
                            if (file_exists($targetPath) && $targetPath !== $webpPath) {
                                @unlink($targetPath);
                            }
                            $filename = $webpFilename;
                            $targetPath = $webpPath;
                        }
                    }
                }

                $relPath = match($type) {
                    'logo'     => '/uploads/' . $filename,
                    'banner'   => '/uploads/banners/' . $filename,
                    'product'  => '/kouprey/public/assets/images/products/' . $filename,
                    default    => '/uploads/' . $filename,
                };

                echo json_encode([
                    'success'    => true,
                    'path'       => $relPath,
                    'filename'   => $filename,
                    'bg_removed' => $bgRemoved,
                    'warning'    => $bgWarning,
                    'format'     => 'webp'
                ]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to save file']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    // ── CHECK REMOVE.BG CREDITS ─────────────────
    case 'check_remove_bg_credits':
        try {
            require_once __DIR__ . '/../app/Config/image_utils.php';
            $res = checkRemoveBgAccount();
            echo json_encode($res);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    // ── FILE MANAGER (Multi-folder Hosting Support) ──────────
    case 'get_file_manager_images':
        try {
            $folder = $_GET['folder'] ?? 'products';
            
            $folderMap = [
                'products'   => ['dir' => __DIR__ . '/assets/images/products/', 'url' => '/kouprey/public/assets/images/products/', 'name' => 'Products'],
                'banner'     => ['dir' => __DIR__ . '/assets/images/banner/', 'url' => '/kouprey/public/assets/images/banner/', 'name' => 'Banners (Assets)'],
                'banners'    => ['dir' => __DIR__ . '/uploads/banners/', 'url' => '/uploads/banners/', 'name' => 'Banners (Uploads)'],
                'categories' => ['dir' => __DIR__ . '/assets/images/categories/', 'url' => '/kouprey/public/assets/images/categories/', 'name' => 'Categories'],
                'uploads'    => ['dir' => __DIR__ . '/uploads/', 'url' => '/uploads/', 'name' => 'Uploads Root'],
                'showcase'   => ['dir' => __DIR__ . '/uploads/showcase/', 'url' => '/uploads/showcase/', 'name' => 'Showcase'],
                'related'    => ['dir' => __DIR__ . '/uploads/related/', 'url' => '/uploads/related/', 'name' => 'Related Products'],
            ];

            $availableFolders = [
                ['id' => 'products', 'label' => 'Products (assets/images/products)'],
                ['id' => 'banner', 'label' => 'Banners (assets/images/banner)'],
                ['id' => 'banners', 'label' => 'Banners (uploads/banners)'],
                ['id' => 'categories', 'label' => 'Categories (assets/images/categories)'],
                ['id' => 'showcase', 'label' => 'Showcase (uploads/showcase)'],
                ['id' => 'related', 'label' => 'Related (uploads/related)'],
                ['id' => 'uploads', 'label' => 'Uploads Root (uploads/)'],
            ];

            $targetConfig = $folderMap[$folder] ?? $folderMap['products'];
            $dir = $targetConfig['dir'];
            $urlPrefix = $targetConfig['url'];

            if (!is_dir($dir)) @mkdir($dir, 0755, true);

            $files = glob($dir . '*.{jpg,jpeg,png,gif,webp,JPG,JPEG,PNG,GIF,WEBP}', GLOB_BRACE) ?: [];
            usort($files, fn($a, $b) => filemtime($b) - filemtime($a));

            $items = array_map(function($path) use ($urlPrefix, $folder) {
                $basename = basename($path);
                return [
                    'filename' => $basename,
                    'url'      => $urlPrefix . $basename,
                    'size'     => round(filesize($path) / 1024, 1) . ' KB',
                    'bytes'    => filesize($path),
                    'time'     => filemtime($path),
                    'folder'   => $folder
                ];
            }, $files);

            echo json_encode([
                'success' => true,
                'current_folder' => $folder,
                'available_folders' => $availableFolders,
                'images' => $items
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'delete_file_manager':
        try {
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $folder = $data['folder'] ?? $_GET['folder'] ?? 'products';
            $filenames = $data['filenames'] ?? [];
            if (!empty($data['filename'])) $filenames[] = $data['filename'];

            $folderMap = [
                'products'   => __DIR__ . '/assets/images/products/',
                'banner'     => __DIR__ . '/assets/images/banner/',
                'banners'    => __DIR__ . '/uploads/banners/',
                'categories' => __DIR__ . '/assets/images/categories/',
                'uploads'    => __DIR__ . '/uploads/',
                'showcase'   => __DIR__ . '/uploads/showcase/',
                'related'    => __DIR__ . '/uploads/related/',
            ];
            $dir = $folderMap[$folder] ?? $folderMap['products'];

            $deleted = 0;
            foreach ($filenames as $fn) {
                $safe = basename($fn);
                $p = $dir . $safe;
                if (file_exists($p) && is_file($p)) {
                    if (unlink($p)) $deleted++;
                }
            }
            echo json_encode(['success' => true, 'deleted' => $deleted]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'upload_file_manager':
        try {
            if (!isset($_FILES['images']) && !isset($_FILES['file'])) {
                echo json_encode(['success' => false, 'error' => 'No files uploaded']);
                break;
            }
            $folder = $_POST['folder'] ?? $_GET['folder'] ?? 'products';
            $folderMap = [
                'products'   => ['dir' => __DIR__ . '/assets/images/products/', 'url' => '/kouprey/public/assets/images/products/'],
                'banner'     => ['dir' => __DIR__ . '/assets/images/banner/', 'url' => '/kouprey/public/assets/images/banner/'],
                'banners'    => ['dir' => __DIR__ . '/uploads/banners/', 'url' => '/kouprey/public/uploads/banners/'],
                'categories' => ['dir' => __DIR__ . '/assets/images/categories/', 'url' => '/kouprey/public/assets/images/categories/'],
                'uploads'    => ['dir' => __DIR__ . '/uploads/', 'url' => '/kouprey/public/uploads/'],
                'showcase'   => ['dir' => __DIR__ . '/uploads/showcase/', 'url' => '/kouprey/public/uploads/showcase/'],
                'related'    => ['dir' => __DIR__ . '/uploads/related/', 'url' => '/kouprey/public/uploads/related/'],
            ];
            $target = $folderMap[$folder] ?? $folderMap['products'];
            $dir = $target['dir'];
            $urlPrefix = $target['url'];

            if (!is_dir($dir)) @mkdir($dir, 0755, true);

            $files = $_FILES['images'] ?? $_FILES['file'];
            $names = is_array($files['name']) ? $files['name'] : [$files['name']];
            $tmps  = is_array($files['tmp_name']) ? $files['tmp_name'] : [$files['tmp_name']];
            $errs  = is_array($files['error']) ? $files['error'] : [$files['error']];

            $removeBgParam = $_POST['remove_bg'] ?? null;
            $removeBg = ($removeBgParam !== null) ? filter_var($removeBgParam, FILTER_VALIDATE_BOOLEAN) : false;

            $uploaded = 0;
            $uploadedFiles = [];
            foreach ($names as $i => $name) {
                if ($errs[$i] === 0) {
                    $ext = pathinfo($name, PATHINFO_EXTENSION);
                    $safe = preg_replace('/[^a-zA-Z0-9._-]/', '', basename($name));
                    if (empty($safe) || $safe === '.' || $safe === '..') $safe = uniqid() . '.' . $ext;
                    $dest = $dir . $safe;
                    if (move_uploaded_file($tmps[$i], $dest)) {
                        $lowExt = strtolower($ext);
                        // Auto-convert to WebP on server to ensure consistent WebP format
                        if (file_exists(__DIR__ . '/../app/Config/image_utils.php')) {
                            require_once __DIR__ . '/../app/Config/image_utils.php';
                            $baseNameNoExt = pathinfo($safe, PATHINFO_FILENAME);
                            $webpName = $baseNameNoExt . '.webp';
                            $webpDest = $dir . $webpName;

                            if ($removeBg && $folder === 'products' && function_exists('removeBgAndConvertToWebp')) {
                                $bgRes = removeBgAndConvertToWebp($dest, $webpDest, null, ['size' => 'auto', 'type' => 'product', 'quality' => 90]);
                                if (!empty($bgRes['success']) && file_exists($webpDest)) {
                                    if (file_exists($dest) && $dest !== $webpDest) @unlink($dest);
                                    $safe = $webpName;
                                } elseif (function_exists('compressImage') && compressImage($dest, $webpDest, 88, 1920, 1920)) {
                                    if (file_exists($dest) && $dest !== $webpDest) @unlink($dest);
                                    $safe = $webpName;
                                }
                            } elseif ($lowExt !== 'webp' && function_exists('compressImage')) {
                                if (compressImage($dest, $webpDest, 88, 1920, 1920)) {
                                    if (file_exists($dest) && $dest !== $webpDest) @unlink($dest);
                                    $safe = $webpName;
                                }
                            }
                        }
                        $uploaded++;
                        $uploadedFiles[] = [
                            'filename' => $safe,
                            'url' => $urlPrefix . $safe,
                            'folder' => $folder
                        ];
                    }
                }
            }
            echo json_encode(['success' => true, 'uploaded' => $uploaded, 'files' => $uploadedFiles]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'convert_all_webp':
        try {
            @set_time_limit(300);
            @ini_set('memory_limit', '512M');

            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $folderParam = trim($_GET['folder'] ?? $data['folder'] ?? '');

            $folderMap = [
                'products'   => ['dir' => __DIR__ . '/assets/images/products/', 'url' => '/kouprey/public/assets/images/products/'],
                'banner'     => ['dir' => __DIR__ . '/assets/images/banner/', 'url' => '/kouprey/public/assets/images/banner/'],
                'banners'    => ['dir' => __DIR__ . '/uploads/banners/', 'url' => '/uploads/banners/'],
                'categories' => ['dir' => __DIR__ . '/assets/images/categories/', 'url' => '/kouprey/public/assets/images/categories/'],
                'uploads'    => ['dir' => __DIR__ . '/uploads/', 'url' => '/uploads/'],
                'showcase'   => ['dir' => __DIR__ . '/uploads/showcase/', 'url' => '/uploads/showcase/'],
                'related'    => ['dir' => __DIR__ . '/uploads/related/', 'url' => '/uploads/related/'],
            ];

            if (!empty($folderParam) && $folderParam !== 'all' && isset($folderMap[$folderParam])) {
                $targetFolders = [$folderParam => $folderMap[$folderParam]];
            } else {
                $targetFolders = $folderMap;
            }

            $converted = 0;
            $dbUpdates = 0;

            if (file_exists(__DIR__ . '/../app/Config/image_utils.php')) {
                require_once __DIR__ . '/../app/Config/image_utils.php';
                $settings = function_exists('getCompressionSettings') ? getCompressionSettings('product') : ['quality' => 88, 'maxWidth' => 1920, 'maxHeight' => 1920];

                foreach ($targetFolders as $fKey => $fInfo) {
                    $dir = $fInfo['dir'];
                    if (!is_dir($dir)) continue;

                    $images = glob($dir . '*.{jpg,jpeg,png,gif,JPG,JPEG,PNG,GIF}', GLOB_BRACE) ?: [];
                    foreach ($images as $img) {
                        if (!is_file($img)) continue;
                        $info = pathinfo($img);
                        $oldBasename = $info['basename'];
                        $newFn = $info['filename'] . '.webp';
                        $newPath = $dir . $newFn;

                        if (function_exists('compressImage') && compressImage($img, $newPath, $settings['quality'], $settings['maxWidth'], $settings['maxHeight'])) {
                            $converted++;
                            // Delete old non-webp file if new webp file exists and has size
                            if ($newPath !== $img && file_exists($newPath) && filesize($newPath) > 0) {
                                @unlink($img);
                            }

                            $oldRel = $fInfo['url'] . $oldBasename;
                            $newRel = $fInfo['url'] . $newFn;

                            $oldPatterns = [
                                $oldBasename,
                                $oldRel,
                                '/uploads/' . $oldBasename,
                                '/uploads/banners/' . $oldBasename,
                                '/uploads/showcase/' . $oldBasename,
                                '/uploads/related/' . $oldBasename,
                                '/kouprey/public/uploads/' . $oldBasename,
                                '/kouprey/public/uploads/banners/' . $oldBasename,
                                '/kouprey/public/uploads/showcase/' . $oldBasename,
                                '/kouprey/public/uploads/related/' . $oldBasename,
                                '/kouprey/public/assets/images/products/' . $oldBasename,
                                '/kouprey/public/assets/images/categories/' . $oldBasename,
                                '/kouprey/public/assets/images/banner/' . $oldBasename,
                            ];

                            foreach (array_unique($oldPatterns) as $oldP) {
                                $newP = str_replace($oldBasename, $newFn, $oldP);

                                try {
                                    $stmt = $pdo->prepare("UPDATE products SET image = ? WHERE image = ?");
                                    $stmt->execute([$newP, $oldP]);
                                    $dbUpdates += $stmt->rowCount();
                                } catch (Exception $e) {}

                                try {
                                    $stmt = $pdo->prepare("UPDATE categories SET image = ? WHERE image = ?");
                                    $stmt->execute([$newP, $oldP]);
                                    $dbUpdates += $stmt->rowCount();
                                } catch (Exception $e) {}

                                try {
                                    $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_value = ?");
                                    $stmt->execute([$newP, $oldP]);
                                    $dbUpdates += $stmt->rowCount();
                                } catch (Exception $e) {}

                                try {
                                    $stmt = $pdo->prepare("UPDATE about SET image = ? WHERE image = ?");
                                    $stmt->execute([$newP, $oldP]);
                                    $dbUpdates += $stmt->rowCount();
                                } catch (Exception $e) {}

                                try {
                                    $stmt = $pdo->prepare("UPDATE about SET person_image = ? WHERE person_image = ?");
                                    $stmt->execute([$newP, $oldP]);
                                    $dbUpdates += $stmt->rowCount();
                                } catch (Exception $e) {}

                                try {
                                    $stmt = $pdo->prepare("UPDATE product_related SET custom_image = ? WHERE custom_image = ?");
                                    $stmt->execute([$newP, $oldP]);
                                    $dbUpdates += $stmt->rowCount();
                                } catch (Exception $e) {}

                                try {
                                    $stmt = $pdo->prepare("UPDATE features SET image = ? WHERE image = ?");
                                    $stmt->execute([$newP, $oldP]);
                                    $dbUpdates += $stmt->rowCount();
                                } catch (Exception $e) {}
                            }
                        }
                    }
                }
            }
            echo json_encode(['success' => true, 'converted' => $converted, 'db_updates' => $dbUpdates]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'error' => "Unknown action: $action"]);
}

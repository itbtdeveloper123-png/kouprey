<?php
ini_set('error_log', __DIR__ . '/debug.log');
session_start();
require_once '../app/Config/database.php';
require_once '../app/Config/settings.php';

// Ensure UTF-8 encoding for proper Khmer character handling
header('Content-Type: text/html; charset=UTF-8');
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

// Get current language from GET or default to 'en'
$currentLanguage = isset($_GET['lang']) ? $_GET['lang'] : 'en';
$supportedLanguages = ['en' => 'English', 'km' => 'Khmer'];
$_SESSION['language'] = $currentLanguage;

// Handle get product data for editing
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['get_product_data'])) {
    $base_product_id = $_POST['base_product_id'];
    $language = $_POST['language'];

    $stmt = $pdo->prepare("SELECT * FROM products WHERE base_product_id = ? AND language = ?");
    $stmt->execute([$base_product_id, $language]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($product) {
        echo json_encode(['success' => true, 'product' => $product]);
    } else {
        // Return empty product data for this language
        echo json_encode(['success' => true, 'product' => [
            'id' => '',
            'name' => '',
            'description' => '',
            'detailed_description' => '',
            'ingredients' => '',
            'origin' => '',
            'brewing_instructions' => '',
            'tasting_notes' => '',
            'weight' => '',
            'roast_level' => '',
            'custom_fields' => '{}'
        ]]);
    }
    exit;
}

// Handle get category data for editing
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['get_category_data'])) {
    error_log('Get category data request received: base_category_id=' . $_POST['base_category_id'] . ', language=' . $_POST['language']);
    $base_category_id = $_POST['base_category_id'];
    $language = $_POST['language'];

    $stmt = $pdo->prepare("SELECT * FROM categories WHERE base_category_id = ? AND language = ?");
    $stmt->execute([$base_category_id, $language]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($category) {
        error_log('Category data found: ' . json_encode($category));
        echo json_encode(['success' => true, 'category' => $category]);
    } else {
        error_log('No category data found for base_category_id=' . $base_category_id . ', language=' . $language);
        // Return empty category data for this language
        echo json_encode(['success' => true, 'category' => [
            'id' => '',
            'name' => '',
            'description' => '',
            'image' => ''
        ]]);
    }
    exit;
}

// Handle Ajax delete product
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ajax_delete_product'])) {
    $product_id = $_POST['product_id'];

    // First get the base_product_id for this record
    $stmt = $pdo->prepare("SELECT base_product_id FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();

    if ($product) {
        // Delete all language versions of this product
        $stmt = $pdo->prepare("DELETE FROM products WHERE base_product_id = ?");
        $result = $stmt->execute([$product['base_product_id']]);

        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Product deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete product']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Product not found']);
    }
    exit;
}

// Handle Ajax delete category
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ajax_delete_category'])) {
    $category_id = $_POST['category_id'];

    // First get the base_category_id for this record
    $stmt = $pdo->prepare("SELECT base_category_id FROM categories WHERE id = ?");
    $stmt->execute([$category_id]);
    $category = $stmt->fetch();

    if ($category) {
        // Set category_id to NULL for all products in this category (both languages)
        $stmt = $pdo->prepare("UPDATE products SET category_id = NULL WHERE category_id IN (SELECT id FROM categories WHERE base_category_id = ?)");
        $stmt->execute([$category['base_category_id']]);

        // Delete all language versions of this category
        $stmt = $pdo->prepare("DELETE FROM categories WHERE base_category_id = ?");
        $result = $stmt->execute([$category['base_category_id']]);

        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Category deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete category']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Category not found']);
    }
    exit;
}

// Handle Ajax toggle featured
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ajax_toggle_featured'])) {
    $product_id = $_POST['product_id'];

    // Get current featured status
    $stmt = $pdo->prepare("SELECT featured FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();

    if ($product) {
        $new_featured = $product['featured'] ? 0 : 1;

        // Update featured status for the current language version
        $stmt = $pdo->prepare("UPDATE products SET featured = ? WHERE id = ?");
        $result = $stmt->execute([$new_featured, $product_id]);

        if ($result) {
            echo json_encode(['success' => true, 'featured' => $new_featured]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update featured status']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Product not found']);
    }
    exit;
}

// Handle Ajax toggle best seller
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ajax_toggle_best_seller'])) {
    $product_id = $_POST['product_id'];

    // Get current best_seller status
    $stmt = $pdo->prepare("SELECT best_seller FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();

    if ($product) {
        $new_best_seller = $product['best_seller'] ? 0 : 1;

        // Update best_seller status for the current language version
        $stmt = $pdo->prepare("UPDATE products SET best_seller = ? WHERE id = ?");
        $result = $stmt->execute([$new_best_seller, $product_id]);

        if ($result) {
            echo json_encode(['success' => true, 'best_seller' => $new_best_seller]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update best seller status']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Product not found']);
    }
    exit;
}

// Handle get all products for related products selection
if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['action']) && $_GET['action'] == 'get_all_products') {
    $stmt = $pdo->prepare("
        SELECT p.id, p.base_product_id, p.name, p.price, p.image, p.language
        FROM products p
        WHERE p.language = 'en'
        ORDER BY p.name ASC
    ");
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'products' => $products]);
    exit;
}

// Handle get related products
if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['action']) && $_GET['action'] == 'get_related_products') {
    $base_product_id = $_GET['base_product_id'];
    
    $stmt = $pdo->prepare("
        SELECT p.id, p.base_product_id, p.name, p.price, p.image, pr.custom_image, pr.custom_url
        FROM product_related pr
        JOIN products p ON pr.related_product_id = p.id
        WHERE pr.product_id = ? AND p.language = 'en'
        ORDER BY pr.sort_order ASC, p.name ASC
    ");
    $stmt->execute([$base_product_id]);
    $related_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'related_products' => $related_products]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_product'])) {
    error_log('Update product request received');
    error_log('All POST data: ' . print_r($_POST, true));
    $base_product_id = $_POST['base_product_id'];
    
    // Check if this is a detailed-only update (from modal) or full update (from sidebar)
    $is_detailed_only = !isset($_POST['edit_name_en']);
    
    if (!$is_detailed_only) {
        // Full update from sidebar
        $price = $_POST['edit_price'];
        $category_id = !empty($_POST['edit_category_id']) ? $_POST['edit_category_id'] : null;
        
        // Get base_category_id if category is selected
        $base_category_id = null;
        if ($category_id) {
            $stmt = $pdo->prepare("SELECT base_category_id FROM categories WHERE id = ?");
            $stmt->execute([$category_id]);
            $cat = $stmt->fetch();
            $base_category_id = $cat ? $cat['base_category_id'] : null;
        }
        
        $featured = isset($_POST['edit_featured']) ? 1 : 0;
        $best_seller = isset($_POST['edit_best_seller']) ? 1 : 0;
        $image_url = $_POST['edit_image_url'] ?? '';

        // Handle file upload
        $uploaded_image = '';
        if (isset($_FILES['edit_image']) && $_FILES['edit_image']['error'] == 0) {
            $upload_dir = '../public/assets/images/products/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $file_extension = pathinfo($_FILES['edit_image']['name'], PATHINFO_EXTENSION);
            $file_name = uniqid() . '.' . $file_extension;
            $target_file = $upload_dir . $file_name;

            if (move_uploaded_file($_FILES['edit_image']['tmp_name'], $target_file)) {
                $uploaded_image = '/kouprey/public/assets/images/products/' . $file_name;
            }
        }

        // Use uploaded image if available, otherwise use URL, otherwise keep existing
        $stmt = $pdo->prepare("SELECT image FROM products WHERE base_product_id = ? AND language = 'en'");
        $stmt->execute([$base_product_id]);
        $current_product = $stmt->fetch();

        $final_image = $uploaded_image ?: ($image_url ?: ($current_product ? $current_product['image'] : ''));

        // Process custom fields
        $custom_fields_json = $_POST['custom_fields_data_edit'] ?? '{}';
        $custom_fields = json_decode($custom_fields_json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $custom_fields = [];
        }
    }

    // Function to get category_id for a language
    function getCategoryIdForLanguageEdit($base_category_id, $language, $pdo) {
        if (!$base_category_id) return null;
        $stmt = $pdo->prepare("SELECT id FROM categories WHERE base_category_id = ? AND language = ?");
        $stmt->execute([$base_category_id, $language]);
        $cat = $stmt->fetch();
        return $cat ? $cat['id'] : null;
    }

    // Update English version
    $stmt = $pdo->prepare("SELECT id FROM products WHERE base_product_id = ? AND language = 'en'");
    $stmt->execute([$base_product_id]);
    $existing_en = $stmt->fetch();

    if ($existing_en) {
        if ($is_detailed_only) {
            // Update only detailed fields for EN
            $stmt = $pdo->prepare("UPDATE products SET detailed_description = ?, ingredients = ?, origin = ?, brewing_instructions = ?, tasting_notes = ?, weight = ?, roast_level = ?, custom_fields = ? WHERE id = ?");
            $stmt->execute([$_POST['edit_detailed_description_en'] ?? '', $_POST['edit_ingredients_en'] ?? '', $_POST['edit_origin_en'] ?? '', $_POST['edit_brewing_instructions_en'] ?? '', $_POST['edit_tasting_notes_en'] ?? '', $_POST['edit_weight_en'] ?? '', $_POST['edit_roast_level_en'] ?? '', json_encode($custom_fields, JSON_UNESCAPED_UNICODE), $existing_en['id']]);
        } else {
            // Full update for EN
            $stmt = $pdo->prepare("UPDATE products SET name = ?, description = ?, price = ?, category_id = ?, featured = ?, best_seller = ?, image = ?, detailed_description = ?, ingredients = ?, origin = ?, brewing_instructions = ?, tasting_notes = ?, weight = ?, roast_level = ?, custom_fields = ? WHERE id = ?");
            $stmt->execute([$_POST['edit_name_en'], $_POST['edit_description_en'], $price, getCategoryIdForLanguageEdit($base_category_id, 'en', $pdo), $featured, $best_seller, $final_image, $_POST['edit_detailed_description_en'] ?? '', $_POST['edit_ingredients_en'] ?? '', $_POST['edit_origin_en'] ?? '', $_POST['edit_brewing_instructions_en'] ?? '', $_POST['edit_tasting_notes_en'] ?? '', $_POST['edit_weight_en'] ?? '', $_POST['edit_roast_level_en'] ?? '', json_encode($custom_fields, JSON_UNESCAPED_UNICODE), $existing_en['id']]);
        }
    } else {
        if (!$is_detailed_only) {
            // Insert new for EN
            $stmt = $pdo->prepare("INSERT INTO products (name, description, price, category_id, featured, best_seller, image, detailed_description, ingredients, origin, brewing_instructions, tasting_notes, weight, roast_level, custom_fields, language, base_product_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'en', ?)");
            $stmt->execute([$_POST['edit_name_en'], $_POST['edit_description_en'], $price, getCategoryIdForLanguageEdit($base_category_id, 'en', $pdo), $featured, $best_seller, $final_image, $_POST['edit_detailed_description_en'] ?? '', $_POST['edit_ingredients_en'] ?? '', $_POST['edit_origin_en'] ?? '', $_POST['edit_brewing_instructions_en'] ?? '', $_POST['edit_tasting_notes_en'] ?? '', $_POST['edit_weight_en'] ?? '', $_POST['edit_roast_level_en'] ?? '', json_encode($custom_fields, JSON_UNESCAPED_UNICODE), $base_product_id]);
        }
    }

    // Update Khmer version
    $stmt = $pdo->prepare("SELECT id FROM products WHERE base_product_id = ? AND language = 'km'");
    $stmt->execute([$base_product_id]);
    $existing_km = $stmt->fetch();

    if ($existing_km) {
        if ($is_detailed_only) {
            // Update only detailed fields for KM
            $stmt = $pdo->prepare("UPDATE products SET detailed_description = ?, ingredients = ?, origin = ?, brewing_instructions = ?, tasting_notes = ?, weight = ?, roast_level = ?, custom_fields = ? WHERE id = ?");
            $stmt->execute([$_POST['edit_detailed_description_km'] ?? '', $_POST['edit_ingredients_km'] ?? '', $_POST['edit_origin_km'] ?? '', $_POST['edit_brewing_instructions_km'] ?? '', $_POST['edit_tasting_notes_km'] ?? '', $_POST['edit_weight_km'] ?? '', $_POST['edit_roast_level_km'] ?? '', json_encode($custom_fields, JSON_UNESCAPED_UNICODE), $existing_km['id']]);
        } else {
            // Full update for KM
            $stmt = $pdo->prepare("UPDATE products SET name = ?, description = ?, price = ?, category_id = ?, featured = ?, best_seller = ?, image = ?, detailed_description = ?, ingredients = ?, origin = ?, brewing_instructions = ?, tasting_notes = ?, weight = ?, roast_level = ?, custom_fields = ? WHERE id = ?");
            $stmt->execute([
                !empty($_POST['edit_name_km']) ? $_POST['edit_name_km'] : $_POST['edit_name_en'],
                !empty($_POST['edit_description_km']) ? $_POST['edit_description_km'] : $_POST['edit_description_en'],
                $price,
                getCategoryIdForLanguageEdit($base_category_id, 'km', $pdo),
                $featured,
                $best_seller,
                $final_image,
                !empty($_POST['edit_detailed_description_km']) ? $_POST['edit_detailed_description_km'] : ($_POST['edit_detailed_description_en'] ?? ''),
                !empty($_POST['edit_ingredients_km']) ? $_POST['edit_ingredients_km'] : ($_POST['edit_ingredients_en'] ?? ''),
                !empty($_POST['edit_origin_km']) ? $_POST['edit_origin_km'] : ($_POST['edit_origin_en'] ?? ''),
                !empty($_POST['edit_brewing_instructions_km']) ? $_POST['edit_brewing_instructions_km'] : ($_POST['edit_brewing_instructions_en'] ?? ''),
                !empty($_POST['edit_tasting_notes_km']) ? $_POST['edit_tasting_notes_km'] : ($_POST['edit_tasting_notes_en'] ?? ''),
                !empty($_POST['edit_weight_km']) ? $_POST['edit_weight_km'] : ($_POST['edit_weight_en'] ?? ''),
                !empty($_POST['edit_roast_level_km']) ? $_POST['edit_roast_level_km'] : ($_POST['edit_roast_level_en'] ?? ''),
                json_encode($custom_fields, JSON_UNESCAPED_UNICODE),
                $existing_km['id']
            ]);
        }
    } else {
        if (!$is_detailed_only) {
            // Insert new for KM
            $stmt = $pdo->prepare("INSERT INTO products (name, description, price, category_id, featured, best_seller, image, detailed_description, ingredients, origin, brewing_instructions, tasting_notes, weight, roast_level, custom_fields, language, base_product_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'km', ?)");
            $stmt->execute([
                !empty($_POST['edit_name_km']) ? $_POST['edit_name_km'] : $_POST['edit_name_en'],
                !empty($_POST['edit_description_km']) ? $_POST['edit_description_km'] : $_POST['edit_description_en'],
                $price,
                getCategoryIdForLanguageEdit($base_category_id, 'km', $pdo),
                $featured,
                $best_seller,
                $final_image,
                !empty($_POST['edit_detailed_description_km']) ? $_POST['edit_detailed_description_km'] : ($_POST['edit_detailed_description_en'] ?? ''),
                !empty($_POST['edit_ingredients_km']) ? $_POST['edit_ingredients_km'] : ($_POST['edit_ingredients_en'] ?? ''),
                !empty($_POST['edit_origin_km']) ? $_POST['edit_origin_km'] : ($_POST['edit_origin_en'] ?? ''),
                !empty($_POST['edit_brewing_instructions_km']) ? $_POST['edit_brewing_instructions_km'] : ($_POST['edit_brewing_instructions_en'] ?? ''),
                !empty($_POST['edit_tasting_notes_km']) ? $_POST['edit_tasting_notes_km'] : ($_POST['edit_tasting_notes_en'] ?? ''),
                !empty($_POST['edit_weight_km']) ? $_POST['edit_weight_km'] : ($_POST['edit_weight_en'] ?? ''),
                !empty($_POST['edit_roast_level_km']) ? $_POST['edit_roast_level_km'] : ($_POST['edit_roast_level_en'] ?? ''),
                '{}',
                $base_product_id
            ]);
        }
    }

    // Process related products for update
    if (!$is_detailed_only && isset($_POST['related_products_data_edit'])) {
        // First, remove all existing related products for this product
        $stmt = $pdo->prepare("DELETE FROM product_related WHERE product_id = ?");
        $stmt->execute([$base_product_id]);

        // Then add the new ones
        $related_products = json_decode($_POST['related_products_data_edit'], true);
        if (is_array($related_products)) {
            foreach ($related_products as $index => $related) {
                $related_base_id = $related['base_id'];
                $custom_url = $related['custom_url'] ?? '';
                $custom_name = $related['name'] ?? '';
                $custom_image_url = $related['custom_image_url'] ?? '';
                $custom_image = '';

                // Handle file upload for custom image
                $file_key = "custom_image_edit_{$index}";
                if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] == UPLOAD_ERR_OK) {
                    $upload_dir = __DIR__ . '/../public/uploads/related/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    $file_name = uniqid() . '_' . basename($_FILES[$file_key]['name']);
                    $file_path = $upload_dir . $file_name;
                    if (move_uploaded_file($_FILES[$file_key]['tmp_name'], $file_path)) {
                        $custom_image = '/kouprey/public/uploads/related/' . $file_name;
                    }
                }

                if (strpos($related_base_id, 'custom_') === 0) {
                    // Custom related product
                    $stmt = $pdo->prepare("INSERT INTO product_related (product_id, related_product_id, custom_image, custom_image_url, custom_url, custom_name) VALUES (?, NULL, ?, ?, ?, ?)");
                    $stmt->execute([$base_product_id, $custom_image, $custom_image_url, $custom_url, $custom_name]);
                } else {
                    // Existing product
                    // Get the English version ID of the related product
                    $stmt = $pdo->prepare("SELECT id FROM products WHERE base_product_id = ? AND language = 'en'");
                    $stmt->execute([$related_base_id]);
                    $related_product = $stmt->fetch();
                    if ($related_product) {
                        $stmt = $pdo->prepare("INSERT INTO product_related (product_id, related_product_id, custom_image, custom_image_url, custom_url) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([$base_product_id, $related_product['id'], $custom_image, $custom_image_url, $custom_url]);
                    }
                }
            }
        }
    }

    $message = $is_detailed_only ? "Product details updated successfully!" : "Product updated successfully!";
}

// Handle add product
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_product'])) {
    error_log('Add product request received');
    error_log('All POST data: ' . print_r($_POST, true));
    $price = $_POST['price'];
    $category_id = !empty($_POST['category_id']) ? $_POST['category_id'] : null;
    $featured = isset($_POST['featured']) ? 1 : 0;
    $best_seller = isset($_POST['best_seller']) ? 1 : 0;
    $image_url = $_POST['image_url'] ?? '';

    // Handle file upload
    $uploaded_image = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $upload_dir = '../public/assets/images/products/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $file_name = uniqid() . '.' . $file_extension;
        $target_file = $upload_dir . $file_name;

        if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
            $uploaded_image = '/kouprey/public/assets/images/products/' . $file_name;
        }
    }

    // Use uploaded image if available, otherwise use URL
    $final_image = $uploaded_image ?: $image_url;

    // Get base_category_id if category is selected
    $base_category_id = null;
    if ($category_id) {
        $stmt = $pdo->prepare("SELECT base_category_id FROM categories WHERE id = ?");
        $stmt->execute([$category_id]);
        $cat = $stmt->fetch();
        $base_category_id = $cat ? $cat['base_category_id'] : null;
    }

    // Process custom fields
    $custom_fields_json = $_POST['custom_fields_data_add'] ?? '{}';
    $custom_fields = json_decode($custom_fields_json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $custom_fields = [];
    }

    // Function to get category_id for a language
    function getCategoryIdForLanguage($base_category_id, $language, $pdo) {
        if (!$base_category_id) return null;
        $stmt = $pdo->prepare("SELECT id FROM categories WHERE base_category_id = ? AND language = ?");
        $stmt->execute([$base_category_id, $language]);
        $cat = $stmt->fetch();
        return $cat ? $cat['id'] : null;
    }

    // Insert English version first to get base_product_id
    try {
        $stmt = $pdo->prepare("INSERT INTO products (name, description, price, category_id, featured, best_seller, image, detailed_description, ingredients, origin, brewing_instructions, tasting_notes, weight, roast_level, custom_fields, language, base_product_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'en', NULL)");
        $stmt->execute([
            $_POST['name_en'],
            $_POST['description_en'],
            $price,
            getCategoryIdForLanguage($base_category_id, 'en', $pdo),
            $featured,
            $best_seller,
            $final_image,
            $_POST['detailed_description_en'] ?? '',
            $_POST['ingredients_en'] ?? '',
            $_POST['origin_en'] ?? '',
            $_POST['brewing_instructions_en'] ?? '',
            $_POST['tasting_notes_en'] ?? '',
            $_POST['weight_en'] ?? '',
            $_POST['roast_level_en'] ?? '',
            json_encode($custom_fields, JSON_UNESCAPED_UNICODE)
        ]);
        $base_product_id = $pdo->lastInsertId();

        // Update base_product_id for the English version
        $stmt = $pdo->prepare("UPDATE products SET base_product_id = ? WHERE id = ?");
        $stmt->execute([$base_product_id, $base_product_id]);
    } catch (Exception $e) {
        error_log('Failed to insert English product: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to add English product version']);
        exit;
    }

    // Insert Khmer version
    try {
        $stmt = $pdo->prepare("INSERT INTO products (name, description, price, category_id, featured, best_seller, image, detailed_description, ingredients, origin, brewing_instructions, tasting_notes, weight, roast_level, custom_fields, language, base_product_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'km', ?)");
        $stmt->execute([
            !empty($_POST['name_km']) ? $_POST['name_km'] : $_POST['name_en'],
            !empty($_POST['description_km']) ? $_POST['description_km'] : $_POST['description_en'],
            $price,
            getCategoryIdForLanguage($base_category_id, 'km', $pdo),
            $featured,
            $best_seller,
            $final_image,
            !empty($_POST['detailed_description_km']) ? $_POST['detailed_description_km'] : ($_POST['detailed_description_en'] ?? ''),
            !empty($_POST['ingredients_km']) ? $_POST['ingredients_km'] : ($_POST['ingredients_en'] ?? ''),
            !empty($_POST['origin_km']) ? $_POST['origin_km'] : ($_POST['origin_en'] ?? ''),
            !empty($_POST['brewing_instructions_km']) ? $_POST['brewing_instructions_km'] : ($_POST['brewing_instructions_en'] ?? ''),
            !empty($_POST['tasting_notes_km']) ? $_POST['tasting_notes_km'] : ($_POST['tasting_notes_en'] ?? ''),
            !empty($_POST['weight_km']) ? $_POST['weight_km'] : ($_POST['weight_en'] ?? ''),
            !empty($_POST['roast_level_km']) ? $_POST['roast_level_km'] : ($_POST['roast_level_en'] ?? ''),
            json_encode($custom_fields, JSON_UNESCAPED_UNICODE),
            $base_product_id
        ]);
    } catch (Exception $e) {
        error_log('Failed to insert Khmer product: ' . $e->getMessage());
        // Continue, as English version is already inserted
    }

    // Process related products
    if (isset($_POST['related_products_data_add']) && !empty($_POST['related_products_data_add'])) {
        $related_products = json_decode($_POST['related_products_data_add'], true);
        if (is_array($related_products)) {
            foreach ($related_products as $index => $related) {
                $related_base_id = $related['base_id'];
                $custom_url = $related['custom_url'] ?? '';
                $custom_name = $related['name'] ?? '';
                $custom_image_url = $related['custom_image_url'] ?? '';
                $custom_image = '';

                // Handle file upload for custom image
                $file_key = "custom_image_add_{$index}";
                if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] == UPLOAD_ERR_OK) {
                    $upload_dir = __DIR__ . '/../public/uploads/related/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    $file_name = uniqid() . '_' . basename($_FILES[$file_key]['name']);
                    $file_path = $upload_dir . $file_name;
                    if (move_uploaded_file($_FILES[$file_key]['tmp_name'], $file_path)) {
                        $custom_image = '/kouprey/public/uploads/related/' . $file_name;
                    }
                }

                if (strpos($related_base_id, 'custom_') === 0) {
                    // Custom related product
                    $stmt = $pdo->prepare("INSERT INTO product_related (product_id, related_product_id, custom_image, custom_image_url, custom_url, custom_name) VALUES (?, NULL, ?, ?, ?, ?)");
                    $stmt->execute([$base_product_id, $custom_image, $custom_image_url, $custom_url, $custom_name]);
                } else {
                    // Existing product
                    // Get the English version ID of the related product
                    $stmt = $pdo->prepare("SELECT id FROM products WHERE base_product_id = ? AND language = 'en'");
                    $stmt->execute([$related_base_id]);
                    $related_product = $stmt->fetch();
                    if ($related_product) {
                        $stmt = $pdo->prepare("INSERT INTO product_related (product_id, related_product_id, custom_image, custom_image_url, custom_url) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([$base_product_id, $related_product['id'], $custom_image, $custom_image_url, $custom_url]);
                    }
                }
            }
        }
    }

    error_log('Product added successfully with base_product_id: ' . $base_product_id);
    $message = "Product added successfully!";
}

// Handle toggle featured status
if (isset($_GET['toggle_featured'])) {
    $id = $_GET['toggle_featured'];
    $stmt = $pdo->prepare("UPDATE products SET featured = CASE WHEN featured = 1 THEN 0 ELSE 1 END WHERE id = ? AND language = ?");
    $stmt->execute([$id, $currentLanguage]);
    $message = "Featured status updated!";
}

// Handle toggle best seller status
if (isset($_GET['toggle_best_seller'])) {
    $id = $_GET['toggle_best_seller'];
    $stmt = $pdo->prepare("UPDATE products SET best_seller = CASE WHEN best_seller = 1 THEN 0 ELSE 1 END WHERE id = ? AND language = ?");
    $stmt->execute([$id, $currentLanguage]);
    $message = "Best seller status updated!";
}

// Handle delete product
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];

    // First get the base_product_id for this record
    $stmt = $pdo->prepare("SELECT base_product_id FROM products WHERE id = ?");
    $stmt->execute([$id]);
    $product = $stmt->fetch();

    if ($product) {
        // Delete all language versions of this product
        $stmt = $pdo->prepare("DELETE FROM products WHERE base_product_id = ?");
        $stmt->execute([$product['base_product_id']]);
        $message = "Product deleted successfully!";
    } else {
        $message = "Product not found!";
    }
}

// Handle update product order
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_order'])) {
    $order = json_decode($_POST['order'], true);
    if (is_array($order)) {
        try {
            $pdo->beginTransaction();
            foreach ($order as $item) {
                if (isset($item['base_product_id']) && isset($item['sort_order'])) {
                    $stmt = $pdo->prepare("UPDATE products SET sort_order = ? WHERE base_product_id = ?");
                    $stmt->execute([$item['sort_order'], $item['base_product_id']]);
                }
            }
            $pdo->commit();
            echo json_encode(['success' => true]);
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }
}

// Handle add category
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_category'])) {
    error_log('Add category request received');
    error_log('All POST data: ' . print_r($_POST, true));
    $name_en = trim($_POST['category_name_en']);
    $name_km = trim($_POST['category_name_km']);
    $description_en = trim($_POST['category_description_en']);
    $description_km = trim($_POST['category_description_km']);
    $image_url = $_POST['category_image_url'] ?? '';

    // Handle file upload for category
    $uploaded_image = '';
    if (isset($_FILES['category_image']) && $_FILES['category_image']['error'] == 0) {
        $upload_dir = '../public/assets/images/categories/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file_extension = pathinfo($_FILES['category_image']['name'], PATHINFO_EXTENSION);
        $file_name = uniqid() . '.' . $file_extension;
        $target_file = $upload_dir . $file_name;

        if (move_uploaded_file($_FILES['category_image']['tmp_name'], $target_file)) {
            $uploaded_image = '/kouprey/public/assets/images/categories/' . $file_name;
        }
    }

    $final_image = $uploaded_image ?: $image_url;

    // Create base entry (English)
    $stmt = $pdo->prepare("INSERT INTO categories (name, description, image, language, base_category_id) VALUES (?, ?, ?, 'en', NULL)");
    $stmt->execute([$name_en, $description_en, $final_image]);

    // Set base_category_id to the new id
    $new_base_id = $pdo->lastInsertId();
    $stmt = $pdo->prepare("UPDATE categories SET base_category_id = ? WHERE id = ?");
    $stmt->execute([$new_base_id, $new_base_id]);

    // Create Khmer entry
    $stmt = $pdo->prepare("INSERT INTO categories (name, description, image, language, base_category_id) VALUES (?, ?, ?, 'km', ?)");
    $stmt->execute([$name_km, $description_km, $final_image, $new_base_id]);

    $message = "Category added successfully!";
}

// Handle update category
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_category'])) {
    error_log('Update category request received');
    error_log('All POST data: ' . print_r($_POST, true));
    $id = $_POST['edit_category_id'];
    $name_en = trim($_POST['edit_category_name_en']);
    $name_km = trim($_POST['edit_category_name_km']);
    $description_en = trim($_POST['edit_category_description_en']);
    $description_km = trim($_POST['edit_category_description_km']);
    $image_url = $_POST['edit_category_image_url'] ?? '';

    // Handle file upload for category
    $uploaded_image = '';
    if (isset($_FILES['edit_category_image']) && $_FILES['edit_category_image']['error'] == 0) {
        $upload_dir = '../public/assets/images/categories/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file_extension = pathinfo($_FILES['edit_category_image']['name'], PATHINFO_EXTENSION);
        $file_name = uniqid() . '.' . $file_extension;
        $target_file = $upload_dir . $file_name;

        if (move_uploaded_file($_FILES['edit_category_image']['tmp_name'], $target_file)) {
            $uploaded_image = '/kouprey/public/assets/images/categories/' . $file_name;
        }
    }

    // Get base_category_id for this category
    $stmt = $pdo->prepare("SELECT base_category_id FROM categories WHERE id = ?");
    $stmt->execute([$id]);
    $category = $stmt->fetch();
    if (!$category) {
        error_log('Category not found for ID: ' . $id);
        $message = "Error: Category not found!";
        return;
    }
    $base_category_id = $category['base_category_id'];

    // Use uploaded image if available, otherwise use URL, otherwise keep existing
    $stmt = $pdo->prepare("SELECT image FROM categories WHERE id = ?");
    $stmt->execute([$id]);
    $current_category = $stmt->fetch();
    if (!$current_category) {
        error_log('Current category not found for ID: ' . $id);
        $message = "Error: Category not found!";
        return;
    }

    $final_image = $uploaded_image ?: ($image_url ?: $current_category['image']);

    // Update English version
    $stmt = $pdo->prepare("SELECT id FROM categories WHERE base_category_id = ? AND language = 'en'");
    $stmt->execute([$base_category_id]);
    $existing_en = $stmt->fetch();

    if ($existing_en) {
        $stmt = $pdo->prepare("UPDATE categories SET name = ?, description = ?, image = ? WHERE id = ?");
        $stmt->execute([$name_en, $description_en, $final_image, $existing_en['id']]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO categories (name, description, image, language, base_category_id) VALUES (?, ?, ?, 'en', ?)");
        $stmt->execute([$name_en, $description_en, $final_image, $base_category_id]);
    }

    // Update Khmer version
    $stmt = $pdo->prepare("SELECT id FROM categories WHERE base_category_id = ? AND language = 'km'");
    $stmt->execute([$base_category_id]);
    $existing_km = $stmt->fetch();

    if ($existing_km) {
        $stmt = $pdo->prepare("UPDATE categories SET name = ?, description = ?, image = ? WHERE id = ?");
        $stmt->execute([$name_km, $description_km, $final_image, $existing_km['id']]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO categories (name, description, image, language, base_category_id) VALUES (?, ?, ?, 'km', ?)");
        $stmt->execute([$name_km, $description_km, $final_image, $base_category_id]);
    }

    $message = "Category updated successfully!";
}

// Handle delete category
if (isset($_GET['delete_category'])) {
    $id = $_GET['delete_category'];

    // First get the base_category_id for this record
    $stmt = $pdo->prepare("SELECT base_category_id FROM categories WHERE id = ?");
    $stmt->execute([$id]);
    $category = $stmt->fetch();

    if ($category) {
        // Set category_id to NULL for all products in this category (both languages)
        $stmt = $pdo->prepare("UPDATE products SET category_id = NULL WHERE category_id IN (SELECT id FROM categories WHERE base_category_id = ?)");
        $stmt->execute([$category['base_category_id']]);

        // Delete all language versions of this category
        $stmt = $pdo->prepare("DELETE FROM categories WHERE base_category_id = ?");
        $stmt->execute([$category['base_category_id']]);
        $message = "Category deleted successfully!";
    } else {
        $message = "Category not found!";
    }
}

// Pagination settings
$productsPerPage = 20;
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($currentPage - 1) * $productsPerPage;

// Get total count for pagination
$stmt = $pdo->prepare("SELECT COUNT(DISTINCT base_product_id) as total FROM products");
$stmt->execute();
$result = $stmt->fetch();
$totalProducts = $result ? $result['total'] : 0;
$totalPages = ceil($totalProducts / $productsPerPage);

// Fetch all products grouped by base_product_id, showing current language version if available (with pagination)
$query = "
    SELECT *
    FROM (
        SELECT
            COALESCE(p_en.id, p_km.id) as id,
            COALESCE(p_en.base_product_id, p_km.base_product_id) as base_product_id,
            COALESCE(p_en.name, p_km.name) as name,
            COALESCE(p_en.description, p_km.description) as description,
            COALESCE(p_en.price, p_km.price) as price,
            COALESCE(p_en.featured, p_km.featured, 0) as featured,
            COALESCE(p_en.best_seller, p_km.best_seller, 0) as best_seller,
            COALESCE(p_en.image, p_km.image) as image,
            COALESCE(p_en.category_id, p_km.category_id) as category_id,
            COALESCE(p_en.sort_order, p_km.sort_order, 0) as sort_order,
            c.name as category_name,
            CASE
                WHEN p_en.id IS NOT NULL AND p_km.id IS NOT NULL THEN 'en,km'
                WHEN p_en.id IS NOT NULL THEN 'en'
                ELSE 'km'
            END as available_languages
        FROM (
            SELECT DISTINCT base_product_id
            FROM products
        ) bp
        LEFT JOIN products p_en ON bp.base_product_id = p_en.base_product_id AND p_en.language = 'en'
        LEFT JOIN products p_km ON bp.base_product_id = p_km.base_product_id AND p_km.language = 'km'
        LEFT JOIN categories c ON COALESCE(p_en.category_id, p_km.category_id) = c.id AND c.language = ?
        ORDER BY COALESCE(p_en.sort_order, p_km.sort_order, 0) ASC, COALESCE(p_en.id, p_km.id) DESC
    ) paginated_products
    LIMIT " . (int)$productsPerPage . " OFFSET " . (int)$offset;

$stmt = $pdo->prepare($query);
$stmt->execute([$currentLanguage]);
$products = $stmt->fetchAll();

// Fetch all categories for current language with product counts
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
$stmt->execute([$currentLanguage]);
$categories = $stmt->fetchAll();
?>

<?php ob_start(); ?>

    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="mb-0">
                <i class="bi bi-box-seam me-2"></i>Product Management
            </h1>
            
        </div>

        <?php if (isset($message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i><?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Nav Tabs -->
        <div class="card shadow-sm">
            <div class="card-header bg-white border-bottom-0">
                <ul class="nav nav-tabs card-header-tabs" id="productTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="products-tab" data-bs-toggle="tab" data-bs-target="#products" type="button" role="tab" aria-controls="products" aria-selected="true">
                            <i class="bi bi-box me-2"></i>Products
                            <span class="badge bg-primary ms-2"><?php echo count($products); ?></span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="categories-tab" data-bs-toggle="tab" data-bs-target="#categories" type="button" role="tab" aria-controls="categories" aria-selected="false">
                            <i class="bi bi-tags me-2"></i>Categories
                            <span class="badge bg-secondary ms-2"><?php echo count($categories); ?></span>
                        </button>
                    </li>
                </ul>
            </div>

            <div class="card-body p-0">
                <div class="tab-content" id="productTabsContent">
                    <!-- Products Tab -->
                    <div class="tab-pane fade show active" id="products" role="tabpanel" aria-labelledby="products-tab">
                        <div class="p-4">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h3 class="mb-0 text-primary">
                                    <i class="bi bi-box me-2"></i>Manage Products
                                </h3>
                                <button type="button" class="btn btn-primary" id="addProductBtn">
                                    <i class="bi bi-plus-circle me-2"></i>Add New Product
                                </button>
                            </div>

                            <div class="card shadow-sm">
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0" id="productsTable">
                                            <thead class="table-light">
                                                <tr>
                                                    <th class="border-0 fw-semibold" style="width: 50px;"><i class="bi bi-grip-vertical me-1"></i></th>
                                                    <th class="border-0 fw-semibold"><i class="bi bi-hash me-1"></i>ID</th>
                                                    <th class="border-0 fw-semibold"><i class="bi bi-image me-1"></i>Image</th>
                                                    <th class="border-0 fw-semibold"><i class="bi bi-tag me-1"></i>Name</th>
                                                    <th class="border-0 fw-semibold"><i class="bi bi-file-text me-1"></i>Description</th>
                                                    <th class="border-0 fw-semibold"><i class="bi bi-tags me-1"></i>Category</th>
                                                    <th class="border-0 fw-semibold"><i class="bi bi-cash me-1"></i>Price</th>
                                                    <th class="border-0 fw-semibold"><i class="bi bi-star me-1"></i>Featured</th>
                                                    <th class="border-0 fw-semibold"><i class="bi bi-trophy me-1"></i>Best Seller</th>
                                                    
                                                    <th class="border-0 fw-semibold"><i class="bi bi-gear me-1"></i>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody id="sortableProducts">
                                                <?php foreach ($products as $product): ?>
                                                    <tr data-base-product-id="<?php echo $product['base_product_id']; ?>">
                                                        <td class="text-center">
                                                            <i class="bi bi-grip-vertical text-muted sortable-handle" style="cursor: grab;"></i>
                                                        </td>
                                                        <td class="fw-medium"><?php echo $product['id']; ?></td>
                                                        <td>
                                                            <?php if ($product['image']): ?>
                                                                <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="Product" class="rounded lazy" style="width: 50px; height: 50px; object-fit: cover; border: 1px solid #e5e7eb;" loading="lazy">
                                                            <?php else: ?>
                                                                <div class="bg-light rounded d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                                                    <i class="bi bi-image text-muted"></i>
                                                                </div>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="fw-medium"><?php echo htmlspecialchars($product['name']); ?></td>
                                                        <td class="text-truncate" style="max-width: 200px;" title="<?php echo htmlspecialchars($product['description']); ?>">
                                                            <?php echo htmlspecialchars($product['description']); ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($product['category_name']): ?>
                                                                <span class="badge bg-info"><?php echo htmlspecialchars($product['category_name']); ?></span>
                                                            <?php else: ?>
                                                                <span class="badge bg-light text-muted">No category</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="fw-medium text-success">$<?php echo htmlspecialchars($product['price']); ?></td>
                                                        <td>
                                                            <span class="badge <?php echo $product['featured'] ? 'bg-warning text-dark' : 'bg-light text-muted'; ?>">
                                                                <i class="bi <?php echo $product['featured'] ? 'bi-star-fill' : 'bi-star'; ?> me-1"></i>
                                                                <?php echo $product['featured'] ? 'Yes' : 'No'; ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span class="badge <?php echo $product['best_seller'] ? 'bg-success' : 'bg-light text-muted'; ?>">
                                                                <i class="bi <?php echo $product['best_seller'] ? 'bi-trophy-fill' : 'bi-trophy'; ?> me-1"></i>
                                                                <?php echo $product['best_seller'] ? 'Yes' : 'No'; ?>
                                                            </span>
                                                        </td>
                                                        
                                                        <td>
                                                            <div class="btn-group" role="group">
                                                                <button class="btn btn-outline-warning btn-sm toggle-featured-btn" data-product-id="<?php echo $product['id']; ?>" title="<?php echo $product['featured'] ? 'Remove from featured' : 'Make featured'; ?>">
                                                                    <i class="bi <?php echo $product['featured'] ? 'bi-star-fill' : 'bi-star'; ?>"></i>
                                                                </button>
                                                                <button class="btn btn-outline-success btn-sm toggle-best-seller-btn" data-product-id="<?php echo $product['id']; ?>" title="<?php echo $product['best_seller'] ? 'Remove from best sellers' : 'Make best seller'; ?>">
                                                                    <i class="bi <?php echo $product['best_seller'] ? 'bi-trophy-fill' : 'bi-trophy'; ?>"></i>
                                                                </button>
                                                                <button class="btn btn-outline-info btn-sm settings-product-btn" data-base-product-id="<?php echo $product['base_product_id']; ?>" title="Edit detailed information">
                                                                    <i class="bi bi-gear"></i>
                                                                </button>
                                                                <button class="btn btn-outline-primary btn-sm edit-product-btn" data-base-product-id="<?php echo $product['base_product_id']; ?>" title="Edit product">
                                                                    <i class="bi bi-pencil"></i>
                                                                </button>
                                                                <button class="btn btn-outline-danger btn-sm delete-product-btn" data-product-id="<?php echo $product['id']; ?>" title="Delete product">
                                                                    <i class="bi bi-trash"></i>
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                <?php if (empty($products)): ?>
                                                    <tr>
                                                        <td colspan="10" class="text-center py-5">
                                                            <div class="text-muted">
                                                                <i class="bi bi-box display-4 mb-3 d-block"></i>
                                                                <h5>No products found</h5>
                                                                <p>Create your first product to get started.</p>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>

                                    <!-- Pagination -->
                                    <?php if ($totalPages > 1): ?>
                                    <div class="d-flex justify-content-between align-items-center mt-3 px-3">
                                        <div class="text-muted">
                                            Showing <?php echo ($offset + 1) . ' to ' . min($offset + $productsPerPage, $totalProducts) . ' of ' . $totalProducts; ?> products
                                        </div>
                                        <nav aria-label="Products pagination">
                                            <ul class="pagination pagination-sm mb-0">
                                                <?php if ($currentPage > 1): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?lang=<?php echo $currentLanguage; ?>&page=<?php echo $currentPage - 1; ?>">
                                                        <i class="bi bi-chevron-left"></i>
                                                    </a>
                                                </li>
                                                <?php endif; ?>

                                                <?php
                                                $startPage = max(1, $currentPage - 2);
                                                $endPage = min($totalPages, $currentPage + 2);

                                                if ($startPage > 1): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?lang=<?php echo $currentLanguage; ?>&page=1">1</a>
                                                </li>
                                                <?php if ($startPage > 2): ?>
                                                <li class="page-item disabled"><span class="page-link">...</span></li>
                                                <?php endif; ?>
                                                <?php endif; ?>

                                                <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                                <li class="page-item <?php echo $i === $currentPage ? 'active' : ''; ?>">
                                                    <a class="page-link" href="?lang=<?php echo $currentLanguage; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                                </li>
                                                <?php endfor; ?>

                                                <?php if ($endPage < $totalPages): ?>
                                                <?php if ($endPage < $totalPages - 1): ?>
                                                <li class="page-item disabled"><span class="page-link">...</span></li>
                                                <?php endif; ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?lang=<?php echo $currentLanguage; ?>&page=<?php echo $totalPages; ?>"><?php echo $totalPages; ?></a>
                                                </li>
                                                <?php endif; ?>

                                                <?php if ($currentPage < $totalPages): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?lang=<?php echo $currentLanguage; ?>&page=<?php echo $currentPage + 1; ?>">
                                                        <i class="bi bi-chevron-right"></i>
                                                    </a>
                                                </li>
                                                <?php endif; ?>
                                            </ul>
                                        </nav>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Categories Tab -->
                    <div class="tab-pane fade" id="categories" role="tabpanel" aria-labelledby="categories-tab">
                        <div class="p-4">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h3 class="mb-0 text-primary">
                                    <i class="bi bi-tags me-2"></i>Manage Categories
                                </h3>
                                <button type="button" class="btn btn-success" id="addCategoryBtn">
                                    <i class="bi bi-plus-circle me-2"></i>Add New Category
                                </button>
                            </div>

                            <div class="card shadow-sm">
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th class="border-0 fw-semibold"><i class="bi bi-hash me-1"></i>ID</th>
                                                    <th class="border-0 fw-semibold"><i class="bi bi-tag me-1"></i>Name</th>
                                                    <th class="border-0 fw-semibold"><i class="bi bi-file-text me-1"></i>Description</th>
                                                    <th class="border-0 fw-semibold"><i class="bi bi-image me-1"></i>Image</th>
                                                    <th class="border-0 fw-semibold"><i class="bi bi-box me-1"></i>Products Count</th>
                                                    <th class="border-0 fw-semibold"><i class="bi bi-gear me-1"></i>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($categories as $category): ?>
                                                    <tr>
                                                        <td class="fw-medium"><?php echo $category['id']; ?></td>
                                                        <td class="fw-medium"><?php echo htmlspecialchars($category['name']); ?></td>
                                                        <td class="text-truncate" style="max-width: 250px;" title="<?php echo htmlspecialchars($category['description']); ?>">
                                                            <?php echo htmlspecialchars(substr($category['description'], 0, 50)) . (strlen($category['description']) > 50 ? '...' : ''); ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($category['image']): ?>
                                                                <img src="<?php echo htmlspecialchars($category['image']); ?>" alt="<?php echo htmlspecialchars($category['name']); ?>" class="rounded" style="width: 50px; height: 50px; object-fit: cover; border: 1px solid #e5e7eb;">
                                                            <?php else: ?>
                                                                <div class="bg-light rounded d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                                                    <i class="bi bi-image text-muted"></i>
                                                                </div>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-primary"><?php echo $category['product_count']; ?> products</span>
                                                        </td>
                                                        <td>
                                                            <div class="btn-group" role="group">
                                                                <button class="btn btn-outline-primary btn-sm edit-category-btn" data-category-id="<?php echo $category['id']; ?>" title="Edit category">
                                                                    <i class="bi bi-pencil"></i>
                                                                </button>
                                                                <button class="btn btn-outline-danger btn-sm delete-category-btn" data-category-id="<?php echo $category['id']; ?>" title="Delete category">
                                                                    <i class="bi bi-trash"></i>
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                <?php if (empty($categories)): ?>
                                                    <tr>
                                                        <td colspan="6" class="text-center py-5">
                                                            <div class="text-muted">
                                                                <i class="bi bi-tags display-4 mb-3 d-block"></i>
                                                                <h5>No categories found</h5>
                                                                <p>Create your first category to organize your products.</p>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Embed product data for JavaScript -->
    <script>
        <?php
        $productsJson = json_encode($products, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
        $categoriesJson = json_encode($categories, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);

        // Validate JSON
        if (json_last_error() !== JSON_ERROR_NONE) {
            $productsJson = '[]';
            $categoriesJson = '[]';
        }
        ?>
        const allProductsData = <?php echo $productsJson; ?>;
        const allCategoriesData = <?php echo $categoriesJson; ?>;
    </script>

    <!-- Edit Product Sidebar -->
    <div class="product-sidebar" id="editProductSidebar">
        <div class="sidebar-header">
            <h5 class="sidebar-title">
                <i class="bi bi-pencil-square"></i> Edit Product
            </h5>
            <button type="button" class="btn-close-sidebar" id="closeEditProductSidebar">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="sidebar-content">
            <form method="POST" enctype="multipart/form-data" id="editProductForm">
                <input type="hidden" id="base_product_id" name="base_product_id" value="">
                <input type="hidden" id="custom_fields_data_edit" name="custom_fields_data_edit" value="">
                <!-- Language Tabs -->
                <ul class="nav nav-tabs mb-3" id="editProductLanguageTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="edit-en-tab" data-bs-toggle="tab" data-bs-target="#edit-en-fields" type="button" role="tab" aria-controls="edit-en-fields" aria-selected="true">English</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="edit-km-tab" data-bs-toggle="tab" data-bs-target="#edit-km-fields" type="button" role="tab" aria-controls="edit-km-fields" aria-selected="false">Khmer</button>
                    </li>
                </ul>
                <div class="tab-content" id="editProductLanguageTabsContent">
                    <!-- English Fields -->
                    <div class="tab-pane fade show active" id="edit-en-fields" role="tabpanel" aria-labelledby="edit-en-tab">
                        <div class="mb-3">
                            <label for="edit_name_en" class="form-label fw-bold">Product Name (EN) *</label>
                            <input type="text" class="form-control" id="edit_name_en" name="edit_name_en" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_description_en" class="form-label fw-bold">Description (EN) *</label>
                            <textarea class="form-control" id="edit_description_en" name="edit_description_en" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="edit_detailed_description_en" class="form-label">Detailed Description (EN)</label>
                            <textarea class="form-control" id="edit_detailed_description_en" name="edit_detailed_description_en" rows="4" placeholder="Provide a detailed description of the product..."></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="edit_ingredients_en" class="form-label">Ingredients (EN)</label>
                            <textarea class="form-control" id="edit_ingredients_en" name="edit_ingredients_en" rows="3" placeholder="List the main ingredients..."></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_origin_en" class="form-label">Origin (EN)</label>
                                <input type="text" class="form-control" id="edit_origin_en" name="edit_origin_en" placeholder="e.g., Ethiopia, Colombia">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_weight_en" class="form-label">Package Weight (EN)</label>
                                <input type="text" class="form-control" id="edit_weight_en" name="edit_weight_en" placeholder="e.g., 250g, 1kg">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_roast_level_en" class="form-label">Roast Level (EN)</label>
                                <select class="form-control" id="edit_roast_level_en" name="edit_roast_level_en">
                                    <option value="">Select roast level</option>
                                    <option value="Light">Light</option>
                                    <option value="Medium-Light">Medium-Light</option>
                                    <option value="Medium">Medium</option>
                                    <option value="Medium-Dark">Medium-Dark</option>
                                    <option value="Dark">Dark</option>
                                    <option value="French">French (Very Dark)</option>
                                    <option value="Unroasted">Unroasted/Green</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_tasting_notes_en" class="form-label">Tasting Notes (EN)</label>
                                <input type="text" class="form-control" id="edit_tasting_notes_en" name="edit_tasting_notes_en" placeholder="e.g., Chocolate, Caramel, Citrus">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_brewing_instructions_en" class="form-label">Brewing Instructions (EN)</label>
                            <textarea class="form-control" id="edit_brewing_instructions_en" name="edit_brewing_instructions_en" rows="3" placeholder="How to brew or prepare this product..."></textarea>
                        </div>
                    </div>
                    <!-- Khmer Fields -->
                    <div class="tab-pane fade" id="edit-km-fields" role="tabpanel" aria-labelledby="edit-km-tab">
                        <div class="mb-3">
                            <label for="edit_name_km" class="form-label fw-bold">Product Name (KM) *</label>
                            <input type="text" class="form-control" id="edit_name_km" name="edit_name_km" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_description_km" class="form-label fw-bold">Description (KM) *</label>
                            <textarea class="form-control" id="edit_description_km" name="edit_description_km" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="edit_detailed_description_km" class="form-label">Detailed Description (KM)</label>
                            <textarea class="form-control" id="edit_detailed_description_km" name="edit_detailed_description_km" rows="4" placeholder="Provide a detailed description of the product..."></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="edit_ingredients_km" class="form-label">Ingredients (KM)</label>
                            <textarea class="form-control" id="edit_ingredients_km" name="edit_ingredients_km" rows="3" placeholder="List the main ingredients..."></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_origin_km" class="form-label">Origin (KM)</label>
                                <input type="text" class="form-control" id="edit_origin_km" name="edit_origin_km" placeholder="e.g., Ethiopia, Colombia">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_weight_km" class="form-label">Package Weight (KM)</label>
                                <input type="text" class="form-control" id="edit_weight_km" name="edit_weight_km" placeholder="e.g., 250g, 1kg">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_roast_level_km" class="form-label">Roast Level (KM)</label>
                                <select class="form-control" id="edit_roast_level_km" name="edit_roast_level_km">
                                    <option value="">Select roast level</option>
                                    <option value="Light">Light</option>
                                    <option value="Medium-Light">Medium-Light</option>
                                    <option value="Medium">Medium</option>
                                    <option value="Medium-Dark">Medium-Dark</option>
                                    <option value="Dark">Dark</option>
                                    <option value="French">French (Very Dark)</option>
                                    <option value="Unroasted">Unroasted/Green</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_tasting_notes_km" class="form-label">Tasting Notes (KM)</label>
                                <input type="text" class="form-control" id="edit_tasting_notes_km" name="edit_tasting_notes_km" placeholder="e.g., Chocolate, Caramel, Citrus">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_brewing_instructions_km" class="form-label">Brewing Instructions (KM)</label>
                            <textarea class="form-control" id="edit_brewing_instructions_km" name="edit_brewing_instructions_km" rows="3" placeholder="How to brew or prepare this product..."></textarea>
                        </div>
                    </div>
                </div>

                <!-- Shared Information -->
                <div class="row">
                    <div class="col-md-8">
                        <!-- Price is shared -->
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="edit_price" class="form-label fw-bold">Price ($) *</label>
                            <input type="number" step="0.01" class="form-control" id="edit_price" name="edit_price" required>
                        </div>
                    </div>
                </div>

                <!-- Category Selection -->
                <div class="mb-3">
                    <label for="edit_category_id" class="form-label fw-bold">Category</label>
                    <select class="form-control" id="edit_category_id" name="edit_category_id">
                        <option value="">Select a category (optional)</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Choose a category for this product to help with organization and filtering</div>
                </div>

                <!-- Current Image Display -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-image"></i> Current Image</h6>
                    </div>
                    <div class="card-body text-center">
                        <img id="current_image_preview" src="" alt="Current product image" class="img-thumbnail" style="max-width: 150px; max-height: 150px;">
                    </div>
                </div>

                <!-- Image Upload -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-upload"></i> Update Image (Optional)</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="edit_image" class="form-label">Upload New Image File</label>
                            <input type="file" class="form-control" id="edit_image" name="edit_image" accept="image/*">
                            <div class="form-text">Upload a new JPG, PNG, or GIF file to replace the current image</div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_image_url" class="form-label">Or New Image URL</label>
                            <input type="url" class="form-control" id="edit_image_url" name="edit_image_url" placeholder="https://example.com/image.jpg">
                            <div class="form-text">Alternatively, provide a direct URL to replace the current image</div>
                        </div>
                    </div>
                </div>

                <!-- Custom Fields Section -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-plus-circle"></i> Custom Fields</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="form-text">Add custom fields to provide additional product information. These fields will be displayed in the product details.</div>
                            <button type="button" class="btn btn-success btn-sm" id="addCustomFieldEdit">
                                <i class="bi bi-plus"></i> Add Field
                            </button>
                        </div>
                        <div id="customFieldsContainerEdit">
                            <!-- Custom fields will be added here dynamically -->
                        </div>
                    </div>
                </div>

                <!-- Related Products Section -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-link"></i> Related Products</h6>
                    </div>
                    <div class="card-body">
                        <div class="form-text mb-3">Select products that are related to this one. These will be displayed as small product cards in the product detail view.</div>
                        <div class="mb-3">
                            <label for="related_products_search_edit" class="form-label">Search Products</label>
                            <input type="text" class="form-control" id="related_products_search_edit" placeholder="Type to search for products...">
                        </div>
                        <div class="mb-3">
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="add_custom_related_edit">
                                <i class="bi bi-plus"></i> Add Custom Related Product
                            </button>
                        </div>
                        <div id="related_products_list_edit" class="border rounded p-3" style="max-height: 300px; overflow-y: auto;">
                            <!-- Available products will be loaded here -->
                        </div>
                        <div class="mt-3">
                            <h6 class="mb-2">Selected Related Products:</h6>
                            <div id="selected_related_products_edit" class="d-flex flex-wrap gap-2">
                                <!-- Selected products will appear here -->
                            </div>
                        </div>
                        <input type="hidden" id="related_products_data_edit" name="related_products_data_edit" value="">
                    </div>
                </div>

                <!-- Product Status -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-star"></i> Product Status</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="edit_featured" name="edit_featured" value="1">
                                    <label class="form-check-label" for="edit_featured">
                                        <i class="bi bi-star-fill text-warning"></i> Mark as Featured Product
                                    </label>
                                    <div class="form-text">Will appear in the featured products carousel</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="edit_best_seller" name="edit_best_seller" value="1">
                                    <label class="form-check-label" for="edit_best_seller">
                                        <i class="bi bi-trophy-fill text-success"></i> Mark as Best Seller
                                    </label>
                                    <div class="form-text">Will appear in the best sellers section</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <div class="sidebar-footer">
            <button type="button" class="btn btn-secondary" id="cancelEditProduct">
                <i class="bi bi-x-circle"></i> Cancel
            </button>
            <button type="submit" form="editProductForm" name="update_product" class="btn btn-warning">
                <i class="bi bi-check-circle"></i> Update Product
            </button>
        </div>
    </div>

    <!-- Add Product Sidebar -->
    <div class="product-sidebar" id="addProductSidebar">
        <div class="sidebar-header">
            <h5 class="sidebar-title">
                <i class="bi bi-plus-circle"></i> Add New Product
            </h5>
            <button type="button" class="btn-close-sidebar" id="closeAddProductSidebar">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="sidebar-content">
            <form method="POST" enctype="multipart/form-data" id="addProductForm">
                <input type="hidden" id="custom_fields_data_add" name="custom_fields_data_add" value="">
                <!-- Language Tabs -->
                <ul class="nav nav-tabs mb-3" id="addProductLanguageTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="en-tab" data-bs-toggle="tab" data-bs-target="#en-fields" type="button" role="tab" aria-controls="en-fields" aria-selected="true">English</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="km-tab" data-bs-toggle="tab" data-bs-target="#km-fields" type="button" role="tab" aria-controls="km-fields" aria-selected="false">Khmer</button>
                    </li>
                </ul>
                <div class="tab-content" id="addProductLanguageTabsContent">
                    <!-- English Fields -->
                    <div class="tab-pane fade show active" id="en-fields" role="tabpanel" aria-labelledby="en-tab">
                        <div class="mb-3">
                            <label for="sidebar_name_en" class="form-label fw-bold">Product Name (EN) *</label>
                            <input type="text" class="form-control" id="sidebar_name_en" name="name_en" required>
                        </div>
                        <div class="mb-3">
                            <label for="sidebar_description_en" class="form-label fw-bold">Description (EN) *</label>
                            <textarea class="form-control" id="sidebar_description_en" name="description_en" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="sidebar_detailed_description_en" class="form-label">Detailed Description (EN)</label>
                            <textarea class="form-control" id="sidebar_detailed_description_en" name="detailed_description_en" rows="4" placeholder="Provide a detailed description of the product..."></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="sidebar_ingredients_en" class="form-label">Ingredients (EN)</label>
                            <textarea class="form-control" id="sidebar_ingredients_en" name="ingredients_en" rows="3" placeholder="List the main ingredients..."></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="sidebar_origin_en" class="form-label">Origin (EN)</label>
                                <input type="text" class="form-control" id="sidebar_origin_en" name="origin_en" placeholder="e.g., Ethiopia, Colombia">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="sidebar_weight_en" class="form-label">Package Weight (EN)</label>
                                <input type="text" class="form-control" id="sidebar_weight_en" name="weight_en" placeholder="e.g., 250g, 1kg">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="sidebar_roast_level_en" class="form-label">Roast Level (EN)</label>
                                <select class="form-control" id="sidebar_roast_level_en" name="roast_level_en">
                                    <option value="">Select roast level</option>
                                    <option value="Light">Light</option>
                                    <option value="Medium-Light">Medium-Light</option>
                                    <option value="Medium">Medium</option>
                                    <option value="Medium-Dark">Medium-Dark</option>
                                    <option value="Dark">Dark</option>
                                    <option value="French">French (Very Dark)</option>
                                    <option value="Unroasted">Unroasted/Green</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="sidebar_tasting_notes_en" class="form-label">Tasting Notes (EN)</label>
                                <input type="text" class="form-control" id="sidebar_tasting_notes_en" name="tasting_notes_en" placeholder="e.g., Chocolate, Caramel, Citrus">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="sidebar_brewing_instructions_en" class="form-label">Brewing Instructions (EN)</label>
                            <textarea class="form-control" id="sidebar_brewing_instructions_en" name="brewing_instructions_en" rows="3" placeholder="How to brew or prepare this product..."></textarea>
                        </div>
                    </div>
                    <!-- Khmer Fields -->
                    <div class="tab-pane fade" id="km-fields" role="tabpanel" aria-labelledby="km-tab">
                        <div class="mb-3">
                            <label for="sidebar_name_km" class="form-label fw-bold">Product Name (KM) *</label>
                            <input type="text" class="form-control" id="sidebar_name_km" name="name_km" required>
                        </div>
                        <div class="mb-3">
                            <label for="sidebar_description_km" class="form-label fw-bold">Description (KM) *</label>
                            <textarea class="form-control" id="sidebar_description_km" name="description_km" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="sidebar_detailed_description_km" class="form-label">Detailed Description (KM)</label>
                            <textarea class="form-control" id="sidebar_detailed_description_km" name="detailed_description_km" rows="4" placeholder="Provide a detailed description of the product..."></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="sidebar_ingredients_km" class="form-label">Ingredients (KM)</label>
                            <textarea class="form-control" id="sidebar_ingredients_km" name="ingredients_km" rows="3" placeholder="List the main ingredients..."></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="sidebar_origin_km" class="form-label">Origin (KM)</label>
                                <input type="text" class="form-control" id="sidebar_origin_km" name="origin_km" placeholder="e.g., Ethiopia, Colombia">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="sidebar_weight_km" class="form-label">Package Weight (KM)</label>
                                <input type="text" class="form-control" id="sidebar_weight_km" name="weight_km" placeholder="e.g., 250g, 1kg">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="sidebar_roast_level_km" class="form-label">Roast Level (KM)</label>
                                <select class="form-control" id="sidebar_roast_level_km" name="roast_level_km">
                                    <option value="">Select roast level</option>
                                    <option value="Light">Light</option>
                                    <option value="Medium-Light">Medium-Light</option>
                                    <option value="Medium">Medium</option>
                                    <option value="Medium-Dark">Medium-Dark</option>
                                    <option value="Dark">Dark</option>
                                    <option value="French">French (Very Dark)</option>
                                    <option value="Unroasted">Unroasted/Green</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="sidebar_tasting_notes_km" class="form-label">Tasting Notes (KM)</label>
                                <input type="text" class="form-control" id="sidebar_tasting_notes_km" name="tasting_notes_km" placeholder="e.g., Chocolate, Caramel, Citrus">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="sidebar_brewing_instructions_km" class="form-label">Brewing Instructions (KM)</label>
                            <textarea class="form-control" id="sidebar_brewing_instructions_km" name="brewing_instructions_km" rows="3" placeholder="How to brew or prepare this product..."></textarea>
                        </div>
                    </div>
                </div>

                <!-- Shared Information -->
                <div class="row">
                    <div class="col-md-8">
                        <!-- Price is shared -->
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="sidebar_price" class="form-label fw-bold">Price ($) *</label>
                            <input type="number" step="0.01" class="form-control" id="sidebar_price" name="price" required>
                        </div>
                    </div>
                </div>

                <!-- Category Selection -->
                <div class="mb-3">
                    <label for="sidebar_category" class="form-label fw-bold">Category</label>
                    <select class="form-control" id="sidebar_category" name="category_id">
                        <option value="">Select a category (optional)</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Choose a category for this product to help with organization and filtering</div>
                </div>

                <!-- Image Upload -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-image"></i> Product Image</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="sidebar_image" class="form-label">Upload Image File</label>
                            <input type="file" class="form-control" id="sidebar_image" name="image" accept="image/*">
                            <div class="form-text">Upload a JPG, PNG, or GIF file</div>
                        </div>
                        <div class="mb-3">
                            <label for="sidebar_image_url" class="form-label">Or Image URL</label>
                            <input type="url" class="form-control" id="sidebar_image_url" name="image_url" placeholder="https://example.com/image.jpg">
                            <div class="form-text">Alternatively, provide a direct URL to an image</div>
                        </div>
                    </div>
                </div>

                <!-- Custom Fields Section -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-plus-circle"></i> Custom Fields</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="form-text">Add custom fields to provide additional product information. These fields will be displayed in the product details.</div>
                            <button type="button" class="btn btn-success btn-sm" id="addCustomFieldAdd">
                                <i class="bi bi-plus"></i> Add Field
                            </button>
                        </div>
                        <div id="customFieldsContainerAdd">
                            <!-- Custom fields will be added here dynamically -->
                        </div>
                    </div>
                </div>

                <!-- Related Products Section -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-link"></i> Related Products</h6>
                    </div>
                    <div class="card-body">
                        <div class="form-text mb-3">Select products that are related to this one. These will be displayed as small product cards in the product detail view.</div>
                        <div class="mb-3">
                            <label for="related_products_search_add" class="form-label">Search Products</label>
                            <input type="text" class="form-control" id="related_products_search_add" placeholder="Type to search for products...">
                        </div>
                        <div class="mb-3">
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="add_custom_related_add">
                                <i class="bi bi-plus"></i> Add Custom Related Product
                            </button>
                        </div>
                        <div id="related_products_list_add" class="border rounded p-3" style="max-height: 300px; overflow-y: auto;">
                            <!-- Available products will be loaded here -->
                        </div>
                        <div class="mt-3">
                            <h6 class="mb-2">Selected Related Products:</h6>
                            <div id="selected_related_products_add" class="d-flex flex-wrap gap-2">
                                <!-- Selected products will appear here -->
                            </div>
                        </div>
                        <input type="hidden" id="related_products_data_add" name="related_products_data_add" value="">
                    </div>
                </div>

                <!-- Product Status -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-star"></i> Product Status</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="sidebar_featured" name="featured" value="1">
                                    <label class="form-check-label" for="sidebar_featured">
                                        <i class="bi bi-star-fill text-warning"></i> Mark as Featured Product
                                    </label>
                                    <div class="form-text">Will appear in the featured products carousel</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="sidebar_best_seller" name="best_seller" value="1">
                                    <label class="form-check-label" for="sidebar_best_seller">
                                        <i class="bi bi-trophy-fill text-success"></i> Mark as Best Seller
                                    </label>
                                    <div class="form-text">Will appear in the best sellers section</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <div class="sidebar-footer">
            <button type="button" class="btn btn-secondary" id="cancelAddProduct">
                <i class="bi bi-x-circle"></i> Cancel
            </button>
            <button type="submit" form="addProductForm" name="add_product" class="btn btn-primary">
                <i class="bi bi-check-circle"></i> Add Product
            </button>
        </div>
    </div>

    <!-- Add Category Sidebar -->
    <div class="category-sidebar" id="addCategorySidebar">
        <div class="sidebar-header">
            <h5 class="sidebar-title">
                <i class="bi bi-plus-circle"></i> Add New Category
            </h5>
            <button type="button" class="btn-close-sidebar" id="closeAddCategorySidebar">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="sidebar-content">
            <form method="POST" enctype="multipart/form-data" id="addCategoryForm">
                <!-- Language Tabs -->
                <ul class="nav nav-tabs mb-3" id="addCategoryLanguageTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="category-en-tab" data-bs-toggle="tab" data-bs-target="#category-en-fields" type="button" role="tab" aria-controls="category-en-fields" aria-selected="true">English</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="category-km-tab" data-bs-toggle="tab" data-bs-target="#category-km-fields" type="button" role="tab" aria-controls="category-km-fields" aria-selected="false">Khmer</button>
                    </li>
                </ul>
                <div class="tab-content" id="addCategoryLanguageTabsContent">
                    <!-- English Fields -->
                    <div class="tab-pane fade show active" id="category-en-fields" role="tabpanel" aria-labelledby="category-en-tab">
                        <div class="mb-3">
                            <label for="sidebar_category_name_en" class="form-label fw-bold">Category Name (EN) *</label>
                            <input type="text" class="form-control" id="sidebar_category_name_en" name="category_name_en" required>
                            <div class="form-text">Enter a unique name for the category in English</div>
                        </div>

                        <div class="mb-3">
                            <label for="sidebar_category_description_en" class="form-label fw-bold">Description (EN)</label>
                            <textarea class="form-control" id="sidebar_category_description_en" name="category_description_en" rows="3" placeholder="Describe this category in English..."></textarea>
                            <div class="form-text">Optional description for the category</div>
                        </div>
                    </div>

                    <!-- Khmer Fields -->
                    <div class="tab-pane fade" id="category-km-fields" role="tabpanel" aria-labelledby="category-km-tab">
                        <div class="mb-3">
                            <label for="sidebar_category_name_km" class="form-label fw-bold">Category Name (KM) *</label>
                            <input type="text" class="form-control" id="sidebar_category_name_km" name="category_name_km" required>
                            <div class="form-text">បញ្ចូលឈ្មោះប្រភេទតែមួយគត់ជាភាសាខ្មែរ</div>
                        </div>

                        <div class="mb-3">
                            <label for="sidebar_category_description_km" class="form-label fw-bold">Description (KM)</label>
                            <textarea class="form-control" id="sidebar_category_description_km" name="category_description_km" rows="3" placeholder="ពិពណ៌នាប្រភេទនេះជាភាសាខ្មែរ..."></textarea>
                            <div class="form-text">ការពិពណ៌នាជាជម្រើសសម្រាប់ប្រភេទ</div>
                        </div>
                    </div>
                </div>

                <!-- Shared Image Upload Section -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-image"></i> Category Image (Optional)</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="sidebar_category_image" class="form-label">Upload Image File</label>
                            <input type="file" class="form-control" id="sidebar_category_image" name="category_image" accept="image/*">
                            <div class="form-text">Upload a JPG, PNG, or GIF file for the category</div>
                        </div>
                        <div class="mb-3">
                            <label for="sidebar_category_image_url" class="form-label">Or Image URL</label>
                            <input type="url" class="form-control" id="sidebar_category_image_url" name="category_image_url" placeholder="https://example.com/image.jpg">
                            <div class="form-text">Alternatively, provide a direct URL to an image</div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <div class="sidebar-footer">
            <button type="button" class="btn btn-secondary" id="cancelAddCategory">
                <i class="bi bi-x-circle"></i> Cancel
            </button>
            <button type="submit" form="addCategoryForm" name="add_category" class="btn btn-success">
                <i class="bi bi-check-circle"></i> Add Category
            </button>
        </div>
    </div>

    <!-- Edit Category Modal -->
    <div class="modal fade" id="editCategoryModal" tabindex="-1" aria-labelledby="editCategoryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title" id="editCategoryModalLabel">
                        <i class="bi bi-pencil-square"></i> Edit Category
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" id="edit_category_id" name="edit_category_id" value="">
                    <div class="modal-body">
                        <!-- Language Tabs -->
                        <ul class="nav nav-tabs mb-3" id="editCategoryLanguageTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="edit-category-en-tab" data-bs-toggle="tab" data-bs-target="#edit-category-en-fields" type="button" role="tab" aria-controls="edit-category-en-fields" aria-selected="true">English</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="edit-category-km-tab" data-bs-toggle="tab" data-bs-target="#edit-category-km-fields" type="button" role="tab" aria-controls="edit-category-km-fields" aria-selected="false">Khmer</button>
                            </li>
                        </ul>
                        <div class="tab-content" id="editCategoryLanguageTabsContent">
                            <!-- English Fields -->
                            <div class="tab-pane fade show active" id="edit-category-en-fields" role="tabpanel" aria-labelledby="edit-category-en-tab">
                                <div class="mb-3">
                                    <label for="edit_category_name_en" class="form-label fw-bold">Category Name (EN) *</label>
                                    <input type="text" class="form-control" id="edit_category_name_en" name="edit_category_name_en" required>
                                    <div class="form-text">Enter a unique name for the category in English</div>
                                </div>

                                <div class="mb-3">
                                    <label for="edit_category_description_en" class="form-label fw-bold">Description (EN)</label>
                                    <textarea class="form-control" id="edit_category_description_en" name="edit_category_description_en" rows="3" placeholder="Describe this category in English..."></textarea>
                                    <div class="form-text">Optional description for the category</div>
                                </div>
                            </div>

                            <!-- Khmer Fields -->
                            <div class="tab-pane fade" id="edit-category-km-fields" role="tabpanel" aria-labelledby="edit-category-km-tab">
                                <div class="mb-3">
                                    <label for="edit_category_name_km" class="form-label fw-bold">Category Name (KM) *</label>
                                    <input type="text" class="form-control" id="edit_category_name_km" name="edit_category_name_km" required>
                                    <div class="form-text">បញ្ចូលឈ្មោះប្រភេទតែមួយគត់ជាភាសាខ្មែរ</div>
                                </div>

                                <div class="mb-3">
                                    <label for="edit_category_description_km" class="form-label fw-bold">Description (KM)</label>
                                    <textarea class="form-control" id="edit_category_description_km" name="edit_category_description_km" rows="3" placeholder="ពិពណ៌នាប្រភេទនេះជាភាសាខ្មែរ..."></textarea>
                                    <div class="form-text">ការពិពណ៌នាជាជម្រើសសម្រាប់ប្រភេទ</div>
                                </div>
                            </div>
                        </div>

                        <!-- Current Image Display -->
                        <div class="card mb-3">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="bi bi-image"></i> Current Image</h6>
                            </div>
                            <div class="card-body text-center">
                                <img id="current_category_image_preview" src="" alt="Current category image" class="img-thumbnail" style="max-width: 150px; max-height: 150px;">
                            </div>
                        </div>

                        <!-- Image Upload -->
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="bi bi-upload"></i> Update Image (Optional)</h6>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label for="edit_category_image" class="form-label">Upload New Image File</label>
                                    <input type="file" class="form-control" id="edit_category_image" name="edit_category_image" accept="image/*">
                                    <div class="form-text">Upload a new JPG, PNG, or GIF file to replace the current image</div>
                                </div>
                                <div class="mb-3">
                                    <label for="edit_category_image_url" class="form-label">Or New Image URL</label>
                                    <input type="url" class="form-control" id="edit_category_image_url" name="edit_category_image_url" placeholder="https://example.com/image.jpg">
                                    <div class="form-text">Alternatively, provide a direct URL to replace the current image</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle"></i> Cancel
                        </button>
                        <button type="submit" name="update_category" class="btn btn-warning">
                            <i class="bi bi-check-circle"></i> Update Category
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Detailed Product Information Modal -->
    <div class="modal fade" id="detailedProductModal" tabindex="-1" aria-labelledby="detailedProductModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="detailedProductModalLabel">
                        <i class="bi bi-gear"></i> <?php echo getSetting('admin_detailed_product_modal_title', 'Detailed Product Information', $currentLanguage); ?>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="detailedProductForm" accept-charset="UTF-8">
                    <input type="hidden" id="detailed_base_product_id" name="base_product_id" value="">
                    <input type="hidden" id="custom_fields_data" name="custom_fields_data" value="">
                    <div class="modal-body">
                        <!-- Standard Fields -->
                        <div class="mb-4">
                            <h6 class="fw-bold text-primary mb-3"><i class="bi bi-info-circle"></i> Standard Information</h6>

                            <div class="mb-3">
                                <label for="detailed_detailed_description" class="form-label fw-bold">Detailed Description</label>
                                <textarea class="form-control" id="detailed_detailed_description" name="edit_detailed_description" rows="4" placeholder="Provide a detailed description of the product..."></textarea>
                                <div class="form-text">This will be shown in the product details modal on the frontend</div>
                            </div>

                            <div class="mb-3">
                                <label for="detailed_ingredients" class="form-label fw-bold">Ingredients</label>
                                <textarea class="form-control" id="detailed_ingredients" name="edit_ingredients" rows="3" placeholder="List the main ingredients..."></textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="detailed_origin" class="form-label fw-bold">Origin</label>
                                    <input type="text" class="form-control" id="detailed_origin" name="edit_origin" placeholder="e.g., Ethiopia, Colombia">
                                    <div class="form-text">Country or region of origin</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="detailed_weight" class="form-label fw-bold">Package Weight</label>
                                    <input type="text" class="form-control" id="detailed_weight" name="edit_weight" placeholder="e.g., 250g, 1kg">
                                    <div class="form-text">Weight or size of the package</div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="detailed_roast_level" class="form-label fw-bold">Roast Level</label>
                                    <select class="form-control" id="detailed_roast_level" name="edit_roast_level">
                                        <option value="">Select roast level</option>
                                        <option value="Light">Light</option>
                                        <option value="Medium-Light">Medium-Light</option>
                                        <option value="Medium">Medium</option>
                                        <option value="Medium-Dark">Medium-Dark</option>
                                        <option value="Dark">Dark</option>
                                        <option value="French">French (Very Dark)</option>
                                        <option value="Unroasted">Unroasted/Green</option>
                                    </select>
                                    <div class="form-text">Coffee roast level</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="detailed_tasting_notes" class="form-label fw-bold">Tasting Notes</label>
                                    <input type="text" class="form-control" id="detailed_tasting_notes" name="edit_tasting_notes" placeholder="e.g., Chocolate, Caramel, Citrus">
                                    <div class="form-text">Flavor profile notes</div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="detailed_brewing_instructions" class="form-label fw-bold">Brewing Instructions</label>
                                <textarea class="form-control" id="detailed_brewing_instructions" name="edit_brewing_instructions" rows="3" placeholder="How to brew or prepare this product..."></textarea>
                            </div>
                        </div>

                        <!-- Custom Fields Section -->
                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="fw-bold text-success mb-0"><i class="bi bi-plus-circle"></i> Custom Fields</h6>
                                <button type="button" class="btn btn-success btn-sm" id="addCustomField">
                                    <i class="bi bi-plus"></i> Add Field
                                </button>
                            </div>

                            <div id="customFieldsContainer">
                                <!-- Custom fields will be added here dynamically -->
                            </div>

                            <div class="form-text">Add custom fields to provide additional product information. These fields will be displayed in the product details.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle"></i> Cancel
                        </button>
                        <button type="submit" name="update_product" class="btn btn-info">
                            <i class="bi bi-check-circle"></i> Update Details
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Product Sidebar Functionality
        const addProductBtn = document.getElementById('addProductBtn');
        const addProductSidebar = document.getElementById('addProductSidebar');
        const closeAddProductSidebar = document.getElementById('closeAddProductSidebar');
        const cancelAddProduct = document.getElementById('cancelAddProduct');
        const sidebarOverlay = document.createElement('div');
        sidebarOverlay.className = 'sidebar-overlay';

        // Add overlay to body
        document.body.appendChild(sidebarOverlay);

        // Open sidebar
        addProductBtn.addEventListener('click', function() {
            addProductSidebar.classList.add('open');
            sidebarOverlay.classList.add('show');
            document.body.style.overflow = 'hidden';
        });

        // Close sidebar functions
        function closeSidebar() {
            addProductSidebar.classList.remove('open');
            sidebarOverlay.classList.remove('show');
            document.body.style.overflow = '';
            // Reset form
            document.getElementById('addProductForm').reset();
            // Clear custom fields
            document.getElementById('customFieldsContainerAdd').innerHTML = '';
        }

        closeAddProductSidebar.addEventListener('click', closeSidebar);
        cancelAddProduct.addEventListener('click', closeSidebar);
        sidebarOverlay.addEventListener('click', function() {
            if (addProductSidebar.classList.contains('open')) {
                closeSidebar();
            } else if (editProductSidebar.classList.contains('open')) {
                closeEditSidebar();
            }
        });

        // Close sidebar on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && addProductSidebar.classList.contains('open')) {
                closeSidebar();
            }
        });

        // Auto-dismiss alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                try { const bsAlert = new bootstrap.Alert(alert); bsAlert.close(); } catch(e) {}
            });
        }, 5000);

        // Form validation for the sidebar
        (function(){
            const sidebarForm = document.getElementById('addProductForm');
            if (sidebarForm) {
                sidebarForm.addEventListener('submit', function(e) {
                    const nameEn = document.getElementById('sidebar_name_en').value.trim();
                    const nameKm = document.getElementById('sidebar_name_km').value.trim();
                    const descriptionEn = document.getElementById('sidebar_description_en').value.trim();
                    const descriptionKm = document.getElementById('sidebar_description_km').value.trim();
                    const price = document.getElementById('sidebar_price').value;

                    if (!nameEn || !nameKm || !descriptionEn || !descriptionKm || !price) {
                        e.preventDefault();
                        alert('Please fill in all required fields (Name and Description for both English and Khmer, and Price).');
                        return false;
                    }

                    if (parseFloat(price) <= 0) {
                        e.preventDefault();
                        alert('Price must be greater than 0.');
                        return false;
                    }

                    // Collect custom fields
                    const customFields = collectCustomFields('customFieldsContainerAdd');
                    document.getElementById('custom_fields_data_add').value = JSON.stringify(customFields);
                });
            }
        })();

        // Edit Product Sidebar Functionality
        const editProductSidebar = document.getElementById('editProductSidebar');
        const closeEditProductSidebar = document.getElementById('closeEditProductSidebar');
        const cancelEditProduct = document.getElementById('cancelEditProduct');

        // Close edit sidebar functions
        function closeEditSidebar() {
            editProductSidebar.classList.remove('open');
            sidebarOverlay.classList.remove('show');
            document.body.style.overflow = '';
            // Reset form
            document.getElementById('editProductForm').reset();
            // Clear custom fields
            document.getElementById('customFieldsContainerEdit').innerHTML = '';
        }

        closeEditProductSidebar.addEventListener('click', closeEditSidebar);
        cancelEditProduct.addEventListener('click', closeEditSidebar);

        // Close edit sidebar on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && editProductSidebar.classList.contains('open')) {
                closeEditSidebar();
            }
        });

        // Form validation for the edit sidebar
        (function(){
            const editSidebarForm = document.getElementById('editProductForm');
            if (editSidebarForm) {
                editSidebarForm.addEventListener('submit', function(e) {
                    const nameEn = document.getElementById('edit_name_en').value.trim();
                    const nameKm = document.getElementById('edit_name_km').value.trim();
                    const descriptionEn = document.getElementById('edit_description_en').value.trim();
                    const descriptionKm = document.getElementById('edit_description_km').value.trim();
                    const price = document.getElementById('edit_price').value;

                    if (!nameEn || !nameKm || !descriptionEn || !descriptionKm || !price) {
                        e.preventDefault();
                        alert('Please fill in all required fields (Name and Description for both English and Khmer, and Price).');
                        return false;
                    }

                    if (parseFloat(price) <= 0) {
                        e.preventDefault();
                        alert('Price must be greater than 0.');
                        return false;
                    }

                    // Collect custom fields
                    const customFields = collectCustomFields('customFieldsContainerEdit');
                    document.getElementById('custom_fields_data_edit').value = JSON.stringify(customFields);
                });
            }
        })();

        // Category Sidebar Functionality
        const addCategoryBtn = document.getElementById('addCategoryBtn');
        const addCategorySidebar = document.getElementById('addCategorySidebar');
        const closeAddCategorySidebar = document.getElementById('closeAddCategorySidebar');
        const cancelAddCategory = document.getElementById('cancelAddCategory');

        // Open category sidebar
        addCategoryBtn.addEventListener('click', function() {
            addCategorySidebar.classList.add('open');
            sidebarOverlay.classList.add('show');
            document.body.style.overflow = 'hidden';
        });

        // Close category sidebar functions
        function closeCategorySidebar() {
            addCategorySidebar.classList.remove('open');
            sidebarOverlay.classList.remove('show');
            document.body.style.overflow = '';
            // Reset form
            document.getElementById('addCategoryForm').reset();
        }

        closeAddCategorySidebar.addEventListener('click', closeCategorySidebar);
        cancelAddCategory.addEventListener('click', closeCategorySidebar);

        // Close category sidebar on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && addCategorySidebar.classList.contains('open')) {
                closeCategorySidebar();
            }
        });

        // Form validation for category sidebar
        (function(){
            const categorySidebarForm = document.getElementById('addCategoryForm');
            if (categorySidebarForm) {
                categorySidebarForm.addEventListener('submit', function(e) {
                    const nameEn = document.getElementById('sidebar_category_name_en').value.trim();
                    const nameKm = document.getElementById('sidebar_category_name_km').value.trim();

                    if (!nameEn) {
                        e.preventDefault();
                        alert('Please enter a category name in English.');
                        return false;
                    }

                    if (!nameKm) {
                        e.preventDefault();
                        alert('Please enter a category name in Khmer.');
                        return false;
                    }
                });
            }
        })();

        function showNotification(message, type) {
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `alert alert-${type === 'success' ? 'success' : 'danger'} alert-dismissible fade show position-fixed`;
            notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            notification.innerHTML = `
                <i class="bi bi-${type === 'success' ? 'check-circle' : 'exclamation-triangle'}-fill me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;

            document.body.appendChild(notification);

            // Auto remove after 5 seconds
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 5000);
        }

        // Handle all button clicks in a single event listener
        document.addEventListener('click', function(e) {
            // Handle edit product button clicks
            if (e.target.closest('.edit-product-btn')) {
                e.preventDefault();
                const button = e.target.closest('.edit-product-btn');
                const baseProductId = button.getAttribute('data-base-product-id');

                // Find the product data from the table
                const row = button.closest('tr');
                const productData = {
                    base_product_id: baseProductId,
                    name: row.cells[3] ? row.cells[3].textContent.trim() : '',
                    price: row.cells[6] ? row.cells[6].textContent.replace('$', '').trim() : '',
                    featured: row.cells[7] && row.cells[7].querySelector('.badge') ? row.cells[7].querySelector('.badge').textContent.includes('Yes') ? 1 : 0 : 0,
                    best_seller: row.cells[8] && row.cells[8].querySelector('.badge') ? row.cells[8].querySelector('.badge').textContent.includes('Yes') ? 1 : 0 : 0,
                    image: row.cells[2] && row.cells[2].querySelector('img') ? row.cells[2].querySelector('img').src : ''
                };

                populateEditModal(productData);
            }

            // Handle settings product button clicks
            if (e.target.closest('.settings-product-btn')) {
                e.preventDefault();
                const button = e.target.closest('.settings-product-btn');
                const baseProductId = button.getAttribute('data-base-product-id');

                // Fetch the current language version of this product
                fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'get_product_data=1&base_product_id=' + baseProductId + '&language=' + encodeURIComponent('<?php echo addslashes($currentLanguage); ?>')
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const product = data.product;

                        document.getElementById('detailed_base_product_id').value = baseProductId;
                        document.getElementById('detailed_detailed_description').value = product.detailed_description || '';
                        document.getElementById('detailed_ingredients').value = product.ingredients || '';
                        document.getElementById('detailed_origin').value = product.origin || '';
                        document.getElementById('detailed_brewing_instructions').value = product.brewing_instructions || '';
                        document.getElementById('detailed_tasting_notes').value = product.tasting_notes || '';
                        document.getElementById('detailed_weight').value = product.weight || '';
                        document.getElementById('detailed_roast_level').value = product.roast_level || '';

                        // Load custom fields
                        loadCustomFields(product.custom_fields || '{}');

                        const detailedModal = new bootstrap.Modal(document.getElementById('detailedProductModal'));
                        detailedModal.show();
                    }
                })
                .catch(error => {
                    console.error('Error fetching product data:', error);
                });
            }

            // Handle edit category button clicks
            if (e.target.closest('.edit-category-btn')) {
                e.preventDefault();
                const button = e.target.closest('.edit-category-btn');
                const categoryId = button.getAttribute('data-category-id');
                const categoryData = allCategoriesData.find(category => category.id == categoryId);
                console.log('Edit category clicked, categoryId:', categoryId, 'categoryData:', categoryData);
                if (categoryData) populateEditCategoryModal(categoryData);
                else console.error('Category data not found for ID:', categoryId);
            }
        });

        function populateEditModal(productData) {
            // Fetch English version
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'get_product_data=1&base_product_id=' + productData.base_product_id + '&language=en'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const product = data.product;

                    document.getElementById('base_product_id').value = productData.base_product_id;
                    document.getElementById('edit_name_en').value = product.name || '';
                    document.getElementById('edit_description_en').value = product.description || '';
                    document.getElementById('edit_price').value = product.price || productData.price || '';
                    document.getElementById('edit_featured').checked = (product.featured == 1) || (productData.featured == 1);
                    document.getElementById('edit_best_seller').checked = (product.best_seller == 1) || (productData.best_seller == 1);

                    const imagePreview = document.getElementById('current_image_preview');
                    const imageSrc = product.image || productData.image;
                    if (imageSrc) {
                        imagePreview.src = imageSrc;
                        imagePreview.style.display = 'block';
                    } else {
                        imagePreview.style.display = 'none';
                    }

                    document.getElementById('edit_image').value = '';
                    document.getElementById('edit_image_url').value = '';

                    document.getElementById('edit_detailed_description_en').value = product.detailed_description || '';
                    document.getElementById('edit_ingredients_en').value = product.ingredients || '';
                    document.getElementById('edit_origin_en').value = product.origin || '';
                    document.getElementById('edit_brewing_instructions_en').value = product.brewing_instructions || '';
                    document.getElementById('edit_tasting_notes_en').value = product.tasting_notes || '';
                    document.getElementById('edit_weight_en').value = product.weight || '';
                    document.getElementById('edit_roast_level_en').value = product.roast_level || '';

                    document.getElementById('edit_category_id').value = product.category_id || productData.category_id || '';

                    // Load custom fields from English version
                    loadCustomFields(product.custom_fields || '{}', 'customFieldsContainerEdit');
                }
            })
            .catch(error => {
                console.error('Error fetching English product data:', error);
            });

            // Fetch Khmer version
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'get_product_data=1&base_product_id=' + productData.base_product_id + '&language=km'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const product = data.product;

                    document.getElementById('edit_name_km').value = product.name || '';
                    document.getElementById('edit_description_km').value = product.description || '';
                    document.getElementById('edit_detailed_description_km').value = product.detailed_description || '';
                    document.getElementById('edit_ingredients_km').value = product.ingredients || '';
                    document.getElementById('edit_origin_km').value = product.origin || '';
                    document.getElementById('edit_brewing_instructions_km').value = product.brewing_instructions || '';
                    document.getElementById('edit_tasting_notes_km').value = product.tasting_notes || '';
                    document.getElementById('edit_weight_km').value = product.weight || '';
                    document.getElementById('edit_roast_level_km').value = product.roast_level || '';
                }
            })
            .catch(error => {
                console.error('Error fetching Khmer product data:', error);
            });

            // Open the edit sidebar
            const editSidebar = document.getElementById('editProductSidebar');
            const sidebarOverlay = document.querySelector('.sidebar-overlay');
            editSidebar.classList.add('open');
            sidebarOverlay.classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        // Custom Fields Management Functions
        function loadCustomFields(customFieldsJson, containerId = 'customFieldsContainer') {
            const container = document.getElementById(containerId);
            container.innerHTML = '';

            try {
                const customFields = JSON.parse(customFieldsJson);
                // Handle both old format (simple object) and new format (with translations)
                if (customFields && typeof customFields === 'object') {
                    Object.keys(customFields).forEach(key => {
                        const fieldData = customFields[key];
                        if (typeof fieldData === 'string') {
                            // Old format: convert to new format
                            addCustomField({
                                name: { en: key.replace(':', ''), km: '' },
                                value: { en: fieldData, km: '' }
                            }, containerId);
                        } else if (fieldData && fieldData.name && fieldData.value) {
                            // New format with translations
                            addCustomField(fieldData, containerId);
                        }
                    });
                }
            } catch (e) {
                console.error('Error parsing custom fields:', e);
            }
        }

        function addCustomField(fieldData = null, containerId = 'customFieldsContainer') {
            const container = document.getElementById(containerId);
            const fieldId = 'custom_field_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);

            // Default values
            let fieldNameEn = '';
            let fieldNameKm = '';
            let fieldValueEn = '';
            let fieldValueKm = '';
            let positionValue = 'end';

            // If fieldData is provided, extract translations
            if (fieldData && typeof fieldData === 'object' && fieldData.name && fieldData.value) {
                fieldNameEn = fieldData.name.en || '';
                fieldNameKm = fieldData.name.km || '';
                fieldValueEn = fieldData.value.en || '';
                fieldValueKm = fieldData.value.km || '';
                if (fieldData.position_after) positionValue = fieldData.position_after;
            }

            const fieldHtml = `
                <div class="custom-field-item mb-3 border rounded p-3 bg-light" data-field-id="${fieldId}">
                    <div class="mb-3">
                        <h6 class="fw-bold text-primary mb-3">Field Name</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label">English</label>
                                <input type="text" class="form-control field-name-en" placeholder="e.g., Daily enjoyment" value="${fieldNameEn}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Khmer</label>
                                <input type="text" class="form-control field-name-km" placeholder="e.g., ការរីករាយប្រចាំថ្ងៃ" value="${fieldNameKm}">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <h6 class="fw-bold text-success mb-3">Field Value</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label">English</label>
                                <textarea class="form-control field-value-en" rows="2" placeholder="e.g., Add matcha to ice cream..." required>${fieldValueEn}</textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Khmer</label>
                                <textarea class="form-control field-value-km" rows="2" placeholder="e.g., បន្ថែមម៉ាត់ឆាទៅក្នុងការ៉េម...">${fieldValueKm}</textarea>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-end">
                        <div class="me-2">
                            <label class="form-label mb-1">Insert After</label>
                            <select class="form-select form-select-sm field-position">
                                <option value="end" ${positionValue === 'end' ? 'selected' : ''}>End (default)</option>
                                <option value="detailed_description" ${positionValue === 'detailed_description' ? 'selected' : ''}>Detailed Description</option>
                                <option value="ingredients" ${positionValue === 'ingredients' ? 'selected' : ''}>Ingredients</option>
                                <option value="origin" ${positionValue === 'origin' ? 'selected' : ''}>Origin</option>
                                <option value="brewing_instructions" ${positionValue === 'brewing_instructions' ? 'selected' : ''}>Brewing Instructions</option>
                                <option value="tasting_notes" ${positionValue === 'tasting_notes' ? 'selected' : ''}>Tasting Notes</option>
                                <option value="weight" ${positionValue === 'weight' ? 'selected' : ''}>Weight</option>
                                <option value="roast_level" ${positionValue === 'roast_level' ? 'selected' : ''}>Roast Level</option>
                            </select>
                        </div>
                        <button type="button" class="btn btn-outline-danger btn-sm remove-field" title="Remove field">
                            <i class="bi bi-trash"></i> Remove Field
                        </button>
                    </div>
                </div>
            `;

            container.insertAdjacentHTML('beforeend', fieldHtml);
        }

        function addCustomFieldToContainer(containerId) {
            addCustomField(null, containerId);
        }

        function collectCustomFields(containerId = 'customFieldsContainer') {
            const customFields = {};
            const fieldItems = document.querySelectorAll(`#${containerId} .custom-field-item`);
            console.log('Found field items in', containerId, ':', fieldItems.length);

            fieldItems.forEach((item, index) => {
                console.log('Processing field item', index);
                const nameEnInput = item.querySelector('.field-name-en');
                const nameKmInput = item.querySelector('.field-name-km');
                const valueEnInput = item.querySelector('.field-value-en');
                const valueKmInput = item.querySelector('.field-value-km');

                if (nameEnInput && valueEnInput && nameEnInput.value.trim() && valueEnInput.value.trim()) {
                    const fieldId = 'field_' + Date.now() + '_' + index;
                    const positionSelect = item.querySelector('.field-position');
                    const positionValue = positionSelect ? positionSelect.value : 'end';
                    customFields[fieldId] = {
                        name: {
                            en: nameEnInput.value.trim(),
                            km: nameKmInput ? nameKmInput.value.trim() : ''
                        },
                        value: {
                            en: valueEnInput.value.trim(),
                            km: valueKmInput ? valueKmInput.value.trim() : ''
                        },
                        position_after: positionValue
                    };
                    console.log('Added field:', fieldId, customFields[fieldId]);
                } else {
                    console.log('Skipped field - missing required inputs or empty values');
                }
            });

            console.log('Final custom fields object:', customFields);
            return customFields;
        }

        // Add custom field button handlers
        document.getElementById('addCustomField').addEventListener('click', function() {
            addCustomField();
        });

        document.getElementById('addCustomFieldAdd').addEventListener('click', function() {
            addCustomFieldToContainer('customFieldsContainerAdd');
        });

        document.getElementById('addCustomFieldEdit').addEventListener('click', function() {
            addCustomFieldToContainer('customFieldsContainerEdit');
        });

        // Remove field handler (delegated)
        document.addEventListener('click', function(e) {
            if (e.target.closest('.remove-field')) {
                e.target.closest('.custom-field-item').remove();
            }
        });

        // Handle detailed product form submission
        document.getElementById('detailedProductForm').addEventListener('submit', function(e) {
            e.preventDefault();
            console.log('Form submission triggered');

            // Collect custom fields data
            const customFields = collectCustomFields();
            console.log('Collected custom fields:', customFields);
            document.getElementById('custom_fields_data').value = JSON.stringify(customFields);
            console.log('Hidden input value set to:', document.getElementById('custom_fields_data').value);
            // Continue with form submission for testing
            const formData = new FormData(this);
            console.log('FormData contents:');
            for (let [key, value] of formData.entries()) {
                console.log(key + ':', value);
            }

            formData.append('update_product', '1');

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Response status:', response.status);
                return response.text();
            })
            .then(data => {
                console.log('Response data:', data);
                // Close the modal
                const detailedModal = bootstrap.Modal.getInstance(document.getElementById('detailedProductModal'));
                detailedModal.hide();
                
                // Reload the page to show updated data
                location.reload();
            })
            .catch(error => {
                console.error('Error updating product details:', error);
                alert('Error updating product details. Please try again.');
            });
        });

        // Reset detailed modal form when closed
        document.getElementById('detailedProductModal').addEventListener('hidden.bs.modal', function () {
            document.getElementById('detailedProductForm').reset();
            // Clear custom fields
            document.getElementById('customFieldsContainer').innerHTML = '';
        });

        function populateEditCategoryModal(categoryData) {
            console.log('Populating edit category modal with data:', categoryData);
            // Fetch English version
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'get_category_data=1&base_category_id=' + categoryData.base_category_id + '&language=en'
            })
            .then(response => response.json())
            .then(data => {
                console.log('English category data received:', data);
                if (data.success) {
                    const category = data.category;
                    document.getElementById('edit_category_name_en').value = category.name || '';
                    document.getElementById('edit_category_description_en').value = category.description || '';
                }
            })
            .catch(error => {
                console.error('Error fetching English category data:', error);
            });

            // Fetch Khmer version
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'get_category_data=1&base_category_id=' + categoryData.base_category_id + '&language=km'
            })
            .then(response => response.json())
            .then(data => {
                console.log('Khmer category data received:', data);
                if (data.success) {
                    const category = data.category;
                    document.getElementById('edit_category_name_km').value = category.name || '';
                    document.getElementById('edit_category_description_km').value = category.description || '';
                }
            })
            .catch(error => {
                console.error('Error fetching Khmer category data:', error);
            });

            document.getElementById('edit_category_id').value = categoryData.id;

            const imagePreview = document.getElementById('current_category_image_preview');
            if (categoryData.image) { imagePreview.src = categoryData.image; imagePreview.style.display = 'block'; }
            else { imagePreview.style.display = 'none'; }

            document.getElementById('edit_category_image').value = '';
            document.getElementById('edit_category_image_url').value = '';

            const editModal = new bootstrap.Modal(document.getElementById('editCategoryModal'));
            editModal.show();
        }

        document.getElementById('editCategoryModal').addEventListener('hidden.bs.modal', function () { const form = this.querySelector('form'); form.reset(); });
    </script>

    

    <!-- Sortable.js for drag and drop -->
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <style>
        .sortable-ghost {
            opacity: 0.4;
            background-color: #e3f2fd !important;
        }
        .sortable-chosen {
            background-color: #f8f9fa !important;
        }
        .sortable-drag {
            transform: rotate(5deg);
        }
        .sortable-handle {
            transition: color 0.2s;
        }
        .sortable-handle:hover {
            color: #0d6efd !important;
        }
        #sortableProducts tr {
            transition: all 0.2s;
        }
        #sortableProducts tr:hover .sortable-handle {
            color: #0d6efd !important;
        }

        /* Loading spinner animation */
        .bi-spin {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sortableProducts = document.getElementById('sortableProducts');

            if (sortableProducts) {
                const sortable = Sortable.create(sortableProducts, {
                    handle: '.sortable-handle',
                    animation: 150,
                    ghostClass: 'sortable-ghost',
                    chosenClass: 'sortable-chosen',
                    dragClass: 'sortable-drag',
                    onEnd: function(evt) {
                        // Get new order with correct sort_order values
                        const rows = sortableProducts.querySelectorAll('tr[data-base-product-id]');
                        const order = Array.from(rows).map((row, index) => ({
                            base_product_id: parseInt(row.getAttribute('data-base-product-id')),
                            sort_order: <?php echo $offset; ?> + index + 1
                        }));

                        // Send to server
                        fetch(window.location.href, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: 'update_order=1&order=' + encodeURIComponent(JSON.stringify(order))
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Show success message
                                showNotification('Product order updated successfully!', 'success');
                            } else {
                                showNotification('Error updating order: ' + (data.error || 'Unknown error'), 'error');
                                // Reload page to restore original order
                                location.reload();
                            }
                        })
                        .catch(error => {
                            showNotification('Error updating order: ' + error.message, 'error');
                            location.reload();
                        });
                    }
                });
            }

            // Handle all button clicks with event delegation
            document.addEventListener('click', function(e) {
                // Handle delete product button clicks
                if (e.target.closest('.delete-product-btn')) {
                    e.preventDefault();
                    const button = e.target.closest('.delete-product-btn');
                    const productId = button.getAttribute('data-product-id');

                    if (confirm('Are you sure you want to delete this product?')) {
                        // Show loading state
                        button.disabled = true;
                        button.innerHTML = '<i class="bi bi-spinner bi-spin"></i>';

                        fetch(window.location.href, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: 'ajax_delete_product=1&product_id=' + productId
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Remove the row from the table
                                const row = button.closest('tr');
                                row.remove();
                                showNotification('Product deleted successfully!', 'success');
                            } else {
                                showNotification('Error deleting product: ' + (data.message || 'Unknown error'), 'error');
                            }
                        })
                        .catch(error => {
                            console.error('Error deleting product:', error);
                            showNotification('Error deleting product. Please try again.', 'error');
                        })
                        .finally(() => {
                            // Reset button state
                            button.disabled = false;
                            button.innerHTML = '<i class="bi bi-trash"></i>';
                        });
                    }
                }

                // Handle delete category button clicks
                if (e.target.closest('.delete-category-btn')) {
                    e.preventDefault();
                    const button = e.target.closest('.delete-category-btn');
                    const categoryId = button.getAttribute('data-category-id');

                    if (confirm('Are you sure you want to delete this category? All products in this category will be uncategorized.')) {
                        // Show loading state
                        button.disabled = true;
                        button.innerHTML = '<i class="bi bi-spinner bi-spin"></i>';

                        fetch(window.location.href, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: 'ajax_delete_category=1&category_id=' + categoryId
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Remove the row from the table
                                const row = button.closest('tr');
                                row.remove();
                                showNotification('Category deleted successfully!', 'success');
                                // Refresh the page to update product counts
                                setTimeout(() => {
                                    window.location.reload();
                                }, 1000);
                            } else {
                                showNotification('Error deleting category: ' + (data.message || 'Unknown error'), 'error');
                            }
                        })
                        .catch(error => {
                            console.error('Error deleting category:', error);
                            showNotification('Error deleting category. Please try again.', 'error');
                        })
                        .finally(() => {
                            // Reset button state
                            button.disabled = false;
                            button.innerHTML = '<i class="bi bi-trash"></i>';
                        });
                    }
                }

                // Handle toggle featured button clicks
                if (e.target.closest('.toggle-featured-btn')) {
                    e.preventDefault();
                    const button = e.target.closest('.toggle-featured-btn');
                    const productId = button.getAttribute('data-product-id');

                    // Show loading state
                    button.disabled = true;
                    const originalIcon = button.innerHTML;
                    button.innerHTML = '<i class="bi bi-spinner bi-spin"></i>';

                    fetch(window.location.href, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'ajax_toggle_featured=1&product_id=' + productId
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Update the button icon and title
                            const isFeatured = data.featured == 1;
                            button.innerHTML = `<i class="bi ${isFeatured ? 'bi-star-fill' : 'bi-star'}"></i>`;
                            button.setAttribute('title', isFeatured ? 'Remove from featured' : 'Make featured');

                            // Update the badge in the table
                            const row = button.closest('tr');
                            const badgeCell = row.cells[7]; // Featured column
                            if (badgeCell) {
                                const badge = badgeCell.querySelector('.badge');
                                if (badge) {
                                    badge.className = `badge ${isFeatured ? 'bg-warning' : 'bg-light text-muted'}`;
                                    badge.innerHTML = `<i class="bi ${isFeatured ? 'bi-star-fill' : 'bi-star'} me-1"></i>${isFeatured ? 'Yes' : 'No'}`;
                                }
                            }

                            showNotification('Featured status updated!', 'success');
                        } else {
                            showNotification('Error updating featured status: ' + (data.message || 'Unknown error'), 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error toggling featured:', error);
                        showNotification('Error updating featured status. Please try again.', 'error');
                    })
                    .finally(() => {
                        // Reset button state
                        button.disabled = false;
                    });
                }

                // Handle toggle best seller button clicks
                if (e.target.closest('.toggle-best-seller-btn')) {
                    e.preventDefault();
                    const button = e.target.closest('.toggle-best-seller-btn');
                    const productId = button.getAttribute('data-product-id');

                    // Show loading state
                    button.disabled = true;
                    const originalIcon = button.innerHTML;
                    button.innerHTML = '<i class="bi bi-spinner bi-spin"></i>';

                    fetch(window.location.href, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'ajax_toggle_best_seller=1&product_id=' + productId
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Update the button icon and title
                            const isBestSeller = data.best_seller == 1;
                            button.innerHTML = `<i class="bi ${isBestSeller ? 'bi-trophy-fill' : 'bi-trophy'}"></i>`;
                            button.setAttribute('title', isBestSeller ? 'Remove from best sellers' : 'Make best seller');

                            // Update the badge in the table
                            const row = button.closest('tr');
                            const badgeCell = row.cells[8]; // Best seller column
                            if (badgeCell) {
                                const badge = badgeCell.querySelector('.badge');
                                if (badge) {
                                    badge.className = `badge ${isBestSeller ? 'bg-success' : 'bg-light text-muted'}`;
                                    badge.innerHTML = `<i class="bi ${isBestSeller ? 'bi-trophy-fill' : 'bi-trophy'} me-1"></i>${isBestSeller ? 'Yes' : 'No'}`;
                                }
                            }

                            showNotification('Best seller status updated!', 'success');
                        } else {
                            showNotification('Error updating best seller status: ' + (data.message || 'Unknown error'), 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error toggling best seller:', error);
                        showNotification('Error updating best seller status. Please try again.', 'error');
                    })
                    .finally(() => {
                        // Reset button state
                        button.disabled = false;
                    });
                }
            });
        });

        // Related Products functionality
        function updateCustomImageFile(formType, index, file) {
            const handler = formType === 'add' ? addRelatedProductsHandler : editRelatedProductsHandler;
            if (handler && handler.updateCustomImageFile) {
                handler.updateCustomImageFile(index, file);
            }
        }

        function updateCustomUrl(formType, index, value) {
            const handler = formType === 'add' ? addRelatedProductsHandler : editRelatedProductsHandler;
            if (handler && handler.updateCustomUrl) {
                handler.updateCustomUrl(index, value);
            }
        }

        function updateCustomName(formType, index, value) {
            const handler = formType === 'add' ? addRelatedProductsHandler : editRelatedProductsHandler;
            if (handler && handler.updateCustomName) {
                handler.updateCustomName(index, value);
            }
        }

        function updateCustomImageUrl(formType, index, value) {
            const handler = formType === 'add' ? addRelatedProductsHandler : editRelatedProductsHandler;
            if (handler && handler.updateCustomImageUrl) {
                handler.updateCustomImageUrl(index, value);
            }
        }

        window.removeSelectedProductGlobal = function(baseId) {
            const handler = addRelatedProductsHandler || editRelatedProductsHandler;
            if (handler && handler.removeSelectedProduct) {
                handler.removeSelectedProduct(baseId);
            }
        };

        function updateCustomImageUrl(formType, index, value) {
            const handler = formType === 'add' ? addRelatedProductsHandler : editRelatedProductsHandler;
            if (handler && handler.updateCustomImageUrl) {
                handler.updateCustomImageUrl(index, value);
            }
        }

        function initializeRelatedProducts(formType) {
            const searchInput = document.getElementById(`related_products_search_${formType}`);
            const productsList = document.getElementById(`related_products_list_${formType}`);
            const selectedContainer = document.getElementById(`selected_related_products_${formType}`);
            const dataInput = document.getElementById(`related_products_data_${formType}`);
            let selectedProducts = [];
            let allProducts = [];

            // Load all products
            function loadProducts() {
                fetch('../public/api.php?action=get_all_products')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            allProducts = data.products;
                            renderProductsList('');
                        }
                    })
                    .catch(error => console.error('Error loading products:', error));
            }

            // Render products list
            function renderProductsList(searchTerm) {
                const filtered = allProducts.filter(product => 
                    product.name.toLowerCase().includes(searchTerm.toLowerCase()) &&
                    !selectedProducts.find(p => p.base_id == product.base_product_id)
                );

                productsList.innerHTML = filtered.map(product => `
                    <div class="d-flex align-items-center justify-content-between p-2 border-bottom">
                        <div class="d-flex align-items-center">
                            <img src="${product.image || '/kouprey/public/assets/images/placeholder.png'}" 
                                 alt="${product.name}" class="rounded me-2" style="width: 40px; height: 40px; object-fit: cover;">
                            <div>
                                <div class="fw-bold">${product.name}</div>
                                <small class="text-muted">$${product.price}</small>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary add-related-btn" 
                                data-base-id="${product.base_product_id}" data-name="${product.name}" data-image="${product.image}">
                            <i class="bi bi-plus"></i> Add
                        </button>
                    </div>
                `).join('');

                // Add event listeners to add buttons
                productsList.querySelectorAll('.add-related-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const baseId = this.getAttribute('data-base-id');
                        const name = this.getAttribute('data-name');
                        const image = this.getAttribute('data-image');
                        addSelectedProduct(baseId, name, image);
                    });
                });
            }

            // Add selected product
            function addSelectedProduct(baseId, name, image) {
                if (!selectedProducts.find(p => p.base_id == baseId)) {
                    selectedProducts.push({base_id: baseId, name: name, image: image, custom_image: '', custom_url: '', custom_image_file: null, custom_image_url: ''});
                    updateSelectedDisplay();
                    renderProductsList(searchInput.value);
                    updateDataInput();
                }
            }

            // Remove selected product
            function removeSelectedProduct(baseId) {
                selectedProducts = selectedProducts.filter(p => p.base_id != baseId);
                updateSelectedDisplay();
                renderProductsList(searchInput.value);
                updateDataInput();
            }

            // Update selected products display
            function updateSelectedDisplay() {
                selectedContainer.innerHTML = selectedProducts.map((product, index) => `
                    <div class="d-inline-block m-1 text-center" style="width: 80px;">
                        <img src="${product.custom_image_url || product.custom_image || product.image || '/kouprey/public/assets/images/placeholder.png'}" alt="${product.name}" class="rounded mb-1" style="width: 60px; height: 60px; object-fit: cover; cursor: pointer;" title="${product.name}">
                        <input type="file" class="form-control form-control-sm mb-1" name="custom_image_${formType}_${index}" accept="image/*" onchange="updateCustomImageFile('${formType}', ${index}, this.files[0])" style="font-size: 10px;">
                        ${product.base_id.startsWith('custom_') ? `<input type="text" class="form-control form-control-sm mb-1" placeholder="Image URL" value="${product.custom_image_url}" onchange="updateCustomImageUrl('${formType}', ${index}, this.value)" style="font-size: 10px;">` : ''}
                        <input type="text" class="form-control form-control-sm mb-1" placeholder="URL" value="${product.custom_url}" onchange="updateCustomUrl('${formType}', ${index}, this.value)" style="font-size: 10px;">
                        ${product.base_id.startsWith('custom_') ? `<input type="text" class="form-control form-control-sm mb-1" placeholder="Name" value="${product.name}" onchange="updateCustomName('${formType}', ${index}, this.value)" style="font-size: 10px;">` : ''}
                        <button type="button" class="btn btn-sm btn-danger" onclick="removeSelectedProductGlobal('${product.base_id}')" style="font-size: 10px; padding: 2px 5px;"><i class="bi bi-x"></i></button>
                    </div>
                `).join('');
            }

            // Update hidden input
            function updateDataInput() {
                const data = selectedProducts.map(p => ({
                    base_id: p.base_id,
                    name: p.name,
                    image: p.image,
                    custom_image: p.custom_image,
                    custom_url: p.custom_url,
                    custom_image_url: p.custom_image_url
                }));
                dataInput.value = JSON.stringify(data);
            }

            // Search functionality
            searchInput.addEventListener('input', function() {
                renderProductsList(this.value);
            });

            // Load products on initialization
            loadProducts();

            // Return functions for external use
            return {
                setSelectedProducts: function(products) {
                    selectedProducts = products.map(p => ({...p, custom_image_file: null}));
                    updateSelectedDisplay();
                    updateDataInput();
                    renderProductsList(searchInput.value);
                },
                updateCustomImageFile: function(index, file) {
                    selectedProducts[index].custom_image_file = file;
                    // Show preview if file selected
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            const img = selectedContainer.children[index].querySelector('img');
                            if (img) img.src = e.target.result;
                        };
                        reader.readAsDataURL(file);
                    }
                    updateDataInput();
                },
                updateCustomUrl: function(index, value) {
                    selectedProducts[index].custom_url = value;
                    updateDataInput();
                },
                updateCustomName: function(index, value) {
                    selectedProducts[index].name = value;
                    updateDataInput();
                },
                updateCustomImageUrl: function(index, value) {
                    selectedProducts[index].custom_image_url = value;
                    updateSelectedDisplay();
                    updateDataInput();
                },
                addCustomProduct: function() {
                    const customId = 'custom_' + Date.now();
                    selectedProducts.push({base_id: customId, name: 'Custom Product', image: '', custom_image: '', custom_url: '', custom_image_file: null, custom_image_url: ''});
                    updateSelectedDisplay();
                    updateDataInput();
                },
                removeSelectedProduct: removeSelectedProduct
            };
        }

        // Initialize related products for add and edit forms
        let addRelatedProductsHandler, editRelatedProductsHandler;

        // Add Product Modal
        document.getElementById('addProductBtn').addEventListener('click', function() {
            // Reset form
            document.getElementById('addProductForm').reset();
            document.getElementById('customFieldsContainerAdd').innerHTML = '';
            document.getElementById('selected_related_products_add').innerHTML = '';
            document.getElementById('related_products_data_add').value = '';
            
            // Initialize related products
            addRelatedProductsHandler = initializeRelatedProducts('add');
            
            // Add custom related product button listener
            document.getElementById('add_custom_related_add').addEventListener('click', function() {
                if (addRelatedProductsHandler && addRelatedProductsHandler.addCustomProduct) {
                    addRelatedProductsHandler.addCustomProduct();
                }
            });
            
            // Show modal
            document.getElementById('addProductSidebar').classList.add('active');
        });

        // Edit Product Modal
        document.addEventListener('click', function(e) {
            if (e.target.closest('.edit-product-btn')) {
                const button = e.target.closest('.edit-product-btn');
                const baseProductId = button.getAttribute('data-base-product-id');
                
                // Load product data
                fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `get_product_data=1&base_product_id=${baseProductId}&language=en`
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            populateEditForm(data.product, baseProductId);
                            // Initialize related products
                            editRelatedProductsHandler = initializeRelatedProducts('edit');
                            // Load existing related products
                            loadRelatedProducts(baseProductId);
                            
                            // Add custom related product button listener
                            document.getElementById('add_custom_related_edit').addEventListener('click', function() {
                                if (editRelatedProductsHandler && editRelatedProductsHandler.addCustomProduct) {
                                    editRelatedProductsHandler.addCustomProduct();
                                }
                            });
                        }
                    })
                    .catch(error => console.error('Error loading product data:', error));
                
                document.getElementById('editProductSidebar').classList.add('active');
            }
        });

        function loadRelatedProducts(baseProductId) {
            fetch(`../public/api.php?action=get_related_products&base_product_id=${baseProductId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && editRelatedProductsHandler) {
                        const selected = data.related_products.map(p => {
                            const base_id = p.base_product_id || ('custom_' + Date.now() + '_' + Math.random());
                            return {base_id: base_id, name: p.name, image: p.image, custom_image: p.custom_image || '', custom_url: p.custom_url || '', custom_image_file: null, custom_image_url: p.custom_image_url || ''};
                        });
                        editRelatedProductsHandler.setSelectedProducts(selected);
                    }
                })
                .catch(error => console.error('Error loading related products:', error));
        }

        function populateEditForm(product, baseProductId) {
            document.getElementById('base_product_id').value = baseProductId;
            // Populate other fields...
            // (existing code for populating form fields)
        }
    </script>

<?php
$pageContent = ob_get_clean();
$pageTitle = 'Manage Products';
$aFieldsctiveNav = 'management';
include 'layout.php';
?>
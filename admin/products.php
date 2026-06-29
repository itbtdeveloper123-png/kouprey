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

// Handle Ajax toggle enabled
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ajax_toggle_enabled'])) {
    $product_id = $_POST['product_id'];

    // Get current enabled status
    $stmt = $pdo->prepare("SELECT enabled FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();

    if ($product) {
        $new_enabled = $product['enabled'] ? 0 : 1;

        // Update enabled status for the current language version
        $stmt = $pdo->prepare("UPDATE products SET enabled = ? WHERE id = ?");
        $result = $stmt->execute([$new_enabled, $product_id]);

        if ($result) {
            echo json_encode(['success' => true, 'enabled' => $new_enabled]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update enabled status']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Product not found']);
    }
    exit;
}

// Handle Ajax toggle collection visibility
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ajax_toggle_collection_visibility'])) {
    $product_id = $_POST['product_id'];

    $stmt = $pdo->prepare("SELECT custom_fields FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();

    if ($product) {
        $custom_fields = json_decode($product['custom_fields'] ?? '{}', true);
        if (!is_array($custom_fields)) $custom_fields = [];
        
        // Toggle the value (default is true if not set)
        $current_status = $custom_fields['show_in_collection'] ?? true;
        $custom_fields['show_in_collection'] = !$current_status;
        
        $new_custom_fields_json = json_encode($custom_fields, JSON_UNESCAPED_UNICODE);

        // Update for this specific record
        $stmt = $pdo->prepare("UPDATE products SET custom_fields = ? WHERE id = ?");
        $result = $stmt->execute([$new_custom_fields_json, $product_id]);

        if ($result) {
            echo json_encode(['success' => true, 'show_in_collection' => $custom_fields['show_in_collection']]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update status']);
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
            // Add timestamp to filename for cache busting
            $file_name = uniqid() . '_' . time() . '.' . $file_extension;
            $target_file = $upload_dir . $file_name;

            if (move_uploaded_file($_FILES['edit_image']['tmp_name'], $target_file)) {
                // Compress the uploaded image
                require_once '../app/Config/image_utils.php';
                $settings = getCompressionSettings('product');
                compressImage($target_file, $target_file, $settings['quality'], $settings['maxWidth'], $settings['maxHeight']);
                
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
                    // Add timestamp to filename for cache busting
                    $file_extension = pathinfo($_FILES[$file_key]['name'], PATHINFO_EXTENSION);
                    $file_name = uniqid() . '_' . time() . '.' . $file_extension;
                    $file_path = $upload_dir . $file_name;
                    if (move_uploaded_file($_FILES[$file_key]['tmp_name'], $file_path)) {
                        // Compress the uploaded image
                        require_once '../app/Config/image_utils.php';
                        $settings = getCompressionSettings('thumbnail');
                        compressImage($file_path, $file_path, $settings['quality'], $settings['maxWidth'], $settings['maxHeight']);
                        
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

    $message = $is_detailed_only ? "Product details updated successfully! Please refresh the frontend pages to see the image changes." : "Product updated successfully! Please refresh the frontend pages to see the image changes.";
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
        // Add timestamp to filename for cache busting
        $file_name = uniqid() . '_' . time() . '.' . $file_extension;
        $target_file = $upload_dir . $file_name;

        if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
            // Compress the uploaded image
            require_once '../app/Config/image_utils.php';
            $settings = getCompressionSettings('product');
            compressImage($target_file, $target_file, $settings['quality'], $settings['maxWidth'], $settings['maxHeight']);
            
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
                    // Add timestamp to filename for cache busting
                    $file_extension = pathinfo($_FILES[$file_key]['name'], PATHINFO_EXTENSION);
                    $file_name = uniqid() . '_' . time() . '.' . $file_extension;
                    $file_path = $upload_dir . $file_name;
                    if (move_uploaded_file($_FILES[$file_key]['tmp_name'], $file_path)) {
                        // Compress the uploaded image
                        require_once '../app/Config/image_utils.php';
                        $settings = getCompressionSettings('thumbnail');
                        compressImage($file_path, $file_path, $settings['quality'], $settings['maxWidth'], $settings['maxHeight']);
                        
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
    $message = "Product added successfully! Please refresh the frontend pages to see the new product.";
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

// Handle toggle enabled status
if (isset($_GET['toggle_enabled'])) {
    $id = $_GET['toggle_enabled'];
    $stmt = $pdo->prepare("UPDATE products SET enabled = CASE WHEN enabled = 1 THEN 0 ELSE 1 END WHERE id = ? AND language = ?");
    $stmt->execute([$id, $currentLanguage]);
    $message = "Enabled status updated!";
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
        // Add timestamp to filename for cache busting
        $file_name = uniqid() . '_' . time() . '.' . $file_extension;
        $target_file = $upload_dir . $file_name;

        if (move_uploaded_file($_FILES['category_image']['tmp_name'], $target_file)) {
            // Compress the uploaded image
            require_once '../app/Config/image_utils.php';
            $settings = getCompressionSettings('product');
            compressImage($target_file, $target_file, $settings['quality'], $settings['maxWidth'], $settings['maxHeight']);
            
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

    $message = "Category added successfully! Please refresh the frontend pages to see the category image changes.";
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
        // Add timestamp to filename for cache busting
        $file_name = uniqid() . '_' . time() . '.' . $file_extension;
        $target_file = $upload_dir . $file_name;

        if (move_uploaded_file($_FILES['edit_category_image']['tmp_name'], $target_file)) {
            // Compress the uploaded image
            require_once '../app/Config/image_utils.php';
            $settings = getCompressionSettings('product');
            compressImage($target_file, $target_file, $settings['quality'], $settings['maxWidth'], $settings['maxHeight']);
            
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

    $message = "Category updated successfully! Please refresh the frontend pages to see the image changes.";
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
$productsPerPageOptions = [10, 20, 50, 100];
$productsPerPage = isset($_GET['per_page']) && in_array((int)$_GET['per_page'], $productsPerPageOptions) ? (int)$_GET['per_page'] : 20;
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($currentPage - 1) * $productsPerPage;

// Get total count for pagination
// Category filter
$categoryFilter = isset($_GET['category_id']) && $_GET['category_id'] !== '' ? $_GET['category_id'] : null;

// Get total count for pagination
if ($categoryFilter) {
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT base_product_id) as total FROM products WHERE category_id = ?");
    $stmt->execute([$categoryFilter]);
} else {
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT base_product_id) as total FROM products");
    $stmt->execute();
}
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
            COALESCE(p_en.custom_fields, p_km.custom_fields, '{}') as custom_fields,
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
            " . ($categoryFilter ? "WHERE category_id = ?" : "") . "
        ) bp
        LEFT JOIN products p_en ON bp.base_product_id = p_en.base_product_id AND p_en.language = 'en'
        LEFT JOIN products p_km ON bp.base_product_id = p_km.base_product_id AND p_km.language = 'km'
        LEFT JOIN categories c ON COALESCE(p_en.category_id, p_km.category_id) = c.id AND c.language = ?
        ORDER BY COALESCE(p_en.sort_order, p_km.sort_order, 0) ASC, COALESCE(p_en.id, p_km.id) DESC
    ) paginated_products
    LIMIT " . (int)$productsPerPage . " OFFSET " . (int)$offset;

$stmt = $pdo->prepare($query);
$params = [$currentLanguage];
if ($categoryFilter) {
    array_unshift($params, $categoryFilter);
}
$stmt->execute($params);
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

                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <div class="input-group">
                                        <span class="input-group-text bg-white"><i class="bi bi-filter"></i> Filter by Category</span>
                                        <select class="form-select" id="categoryFilterSelect" onchange="filterByCategory(this.value)">
                                            <option value="">All Categories</option>
                                            <?php foreach ($categories as $cat): ?>
                                                <option value="<?php echo $cat['id']; ?>" <?php echo (isset($categoryFilter) && $categoryFilter == $cat['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($cat['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
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
                                                    <th class="border-0 fw-semibold"><i class="bi bi-eye me-1"></i>Enabled</th>
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
                                                                <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="Product" class="rounded lazy" style="width: 50px; height: 50px; object-fit: contain; border: 1px solid #e5e7eb; background-color: #f8f9fa;" loading="lazy">
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
                                                            <span class="badge <?php echo isset($product['enabled']) && $product['enabled'] ? 'bg-primary text-white' : 'bg-light text-muted'; ?>">
                                                                <i class="bi <?php echo isset($product['enabled']) && $product['enabled'] ? 'bi-eye-fill' : 'bi-eye'; ?> me-1"></i>
                                                                <?php echo isset($product['enabled']) && $product['enabled'] ? 'Yes' : 'No'; ?>
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
                                                                <?php 
                                                                    $custom_fields = json_decode($product['custom_fields'] ?? '{}', true);
                                                                    $show_in_collection = $custom_fields['show_in_collection'] ?? true;
                                                                ?>
                                                                <button class="btn btn-outline-dark btn-sm toggle-collection-btn" data-product-id="<?php echo $product['id']; ?>" title="<?php echo $show_in_collection ? 'Remove from collection list (Syrup/Powder)' : 'Show in collection list'; ?>">
                                                                    <i class="bi <?php echo $show_in_collection ? 'bi-collection-fill' : 'bi-collection'; ?>"></i>
                                                                </button>
                                                                <button class="btn btn-outline-info btn-sm settings-product-btn" data-base-product-id="<?php echo $product['base_product_id']; ?>" title="Edit detailed information">
                                                                    <i class="bi bi-gear"></i>
                                                                </button>
                                                                <button class="btn btn-outline-primary btn-sm edit-product-btn" data-base-product-id="<?php echo $product['base_product_id']; ?>" title="Edit product">
                                                                    <i class="bi bi-pencil"></i>
                                                                </button>
                                                                <button class="btn btn-outline-secondary btn-sm toggle-enabled-btn" data-product-id="<?php echo $product['id']; ?>" title="Toggle enabled">
                                                                    <i class="bi <?php echo isset($product['enabled']) && $product['enabled'] ? 'bi-eye-fill' : 'bi-eye'; ?>"></i>
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
                                                        <td colspan="11" class="text-center py-5">
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
                                    <?php if ($totalPages > 1 || $totalProducts > $productsPerPageOptions[0]): 
                                        $pageUrl = "?lang=$currentLanguage&per_page=$productsPerPage" . ($categoryFilter ? "&category_id=$categoryFilter" : "");
                                    ?>
                                    <div class="d-flex justify-content-between align-items-center mt-3 px-3">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="text-muted">
                                                Showing <?php echo ($offset + 1) . ' to ' . min($offset + $productsPerPage, $totalProducts) . ' of ' . $totalProducts; ?> products
                                            </div>
                                            <div class="d-flex align-items-center gap-2">
                                                <label for="perPageSelect" class="text-muted mb-0 small">Show:</label>
                                                <select id="perPageSelect" class="form-select form-select-sm" style="width: auto;" onchange="changePerPage(this.value)">
                                                    <?php foreach ($productsPerPageOptions as $option): ?>
                                                    <option value="<?php echo $option; ?>" <?php echo $productsPerPage == $option ? 'selected' : ''; ?>><?php echo $option; ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <span class="text-muted small">per page</span>
                                            </div>
                                        </div>
                                        <nav aria-label="Products pagination">
                                            <ul class="pagination pagination-sm mb-0">
                                                <?php if ($currentPage > 1): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="<?php echo $pageUrl; ?>&page=<?php echo $currentPage - 1; ?>">
                                                        <i class="bi bi-chevron-left"></i>
                                                    </a>
                                                </li>
                                                <?php endif; ?>

                                                <?php
                                                $startPage = max(1, $currentPage - 2);
                                                $endPage = min($totalPages, $currentPage + 2);

                                                if ($startPage > 1): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="<?php echo $pageUrl; ?>&page=1">1</a>
                                                </li>
                                                <?php if ($startPage > 2): ?>
                                                <li class="page-item disabled"><span class="page-link">...</span></li>
                                                <?php endif; ?>
                                                <?php endif; ?>

                                                <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                                <li class="page-item <?php echo $i === $currentPage ? 'active' : ''; ?>">
                                                    <a class="page-link" href="<?php echo $pageUrl; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                                </li>
                                                <?php endfor; ?>

                                                <?php if ($endPage < $totalPages): ?>
                                                <?php if ($endPage < $totalPages - 1): ?>
                                                <li class="page-item disabled"><span class="page-link">...</span></li>
                                                <?php endif; ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="<?php echo $pageUrl; ?>&page=<?php echo $totalPages; ?>"><?php echo $totalPages; ?></a>
                                                </li>
                                                <?php endif; ?>

                                                <?php if ($currentPage < $totalPages): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="<?php echo $pageUrl; ?>&page=<?php echo $currentPage + 1; ?>">
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
                                                                <img src="<?php echo htmlspecialchars($category['image']); ?>" alt="<?php echo htmlspecialchars($category['name']); ?>" class="rounded" style="width: 50px; height: 50px; object-fit: contain; border: 1px solid #e5e7eb; background-color: #f8f9fa;">
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
        var allProductsData = <?php echo $productsJson; ?>;
        var allCategoriesData = <?php echo $categoriesJson; ?>;

        function filterByCategory(categoryId) {
            const url = new URL(window.location);
            if (categoryId) {
                url.searchParams.set('category_id', categoryId);
            } else {
                url.searchParams.delete('category_id');
            }
            url.searchParams.set('page', '1'); // Reset to first page
            window.location.href = url.toString();
        }

        // Function to handle per page change
        function changePerPage(perPage) {
            const url = new URL(window.location);
            url.searchParams.set('per_page', perPage);
            url.searchParams.set('page', '1'); // Reset to first page when changing per page
            window.location.href = url.toString();
        }
    </script>

    <!-- Edit Product Sidebar -->
    <div class="product-sidebar" id="editProductSidebar">
        <div class="sidebar-header">
            <h5 class="sidebar-title">
                <i class="bi bi-pencil-square me-2 text-warning"></i>Edit Product
            </h5>
            <button type="button" class="btn-close-sidebar" id="closeEditProductSidebar">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="sidebar-content">
            <form method="POST" enctype="multipart/form-data" id="editProductForm">
                <input type="hidden" id="base_product_id" name="base_product_id" value="">
                <input type="hidden" id="custom_fields_data_edit" name="custom_fields_data_edit" value="">
                
                <!-- Language Navigation -->
                <div class="mb-4">
                    <label class="form-label fw-bold text-muted small text-uppercase mb-2">Content Language</label>
                    <ul class="nav nav-tabs-premium" id="editProductLanguageTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="edit-en-tab" data-bs-toggle="tab" data-bs-target="#edit-en-fields" type="button" role="tab">
                                <img src="https://img.freepik.com/premium-photo/flag-great-britain_406939-4606.jpg?w=32" class="rounded-circle me-1" width="16" height="16"> English
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="edit-km-tab" data-bs-toggle="tab" data-bs-target="#edit-km-fields" type="button" role="tab">
                                <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/8/83/Flag_of_Cambodia.svg/32px-Flag_of_Cambodia.svg.png" class="rounded-circle me-1" width="16" height="16"> Khmer
                            </button>
                        </li>
                    </ul>
                </div>

                <div class="tab-content" id="editProductLanguageTabsContent">
                    <!-- English Fields -->
                    <div class="tab-pane fade show active" id="edit-en-fields" role="tabpanel">
                        <div class="card mb-4">
                            <div class="card-header bg-white"><i class="bi bi-info-circle me-2"></i>Basic Info (EN)</div>
                            <div class="card-body">
                                <div class="form-floating-custom">
                                    <label for="edit_name_en">Product Name *</label>
                                    <input type="text" class="form-control form-control-premium" id="edit_name_en" name="edit_name_en" required placeholder="Enter product name">
                                </div>
                                <div class="form-floating-custom">
                                    <label for="edit_description_en">Short Description *</label>
                                    <textarea class="form-control form-control-premium" id="edit_description_en" name="edit_description_en" rows="3" required placeholder="Brief summary..."></textarea>
                                </div>
                                <div class="form-floating-custom">
                                    <label for="edit_weight_en">Weight (EN)</label>
                                    <input type="text" class="form-control form-control-premium" id="edit_weight_en" name="edit_weight_en" placeholder="e.g. 250g">
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Khmer Fields -->
                    <div class="tab-pane fade" id="edit-km-fields" role="tabpanel">
                        <div class="card mb-4">
                            <div class="card-header bg-white"><i class="bi bi-info-circle me-2"></i>Basic Info (KM)</div>
                            <div class="card-body">
                                <div class="form-floating-custom">
                                    <label for="edit_name_km">ឈ្មោះផលិតផល (KM) *</label>
                                    <input type="text" class="form-control form-control-premium" id="edit_name_km" name="edit_name_km" placeholder="បញ្ចូលឈ្មោះផលិតផល">
                                </div>
                                <div class="form-floating-custom">
                                    <label for="edit_description_km">ការពិពណ៌នាសង្ខេប (KM) *</label>
                                    <textarea class="form-control form-control-premium" id="edit_description_km" name="edit_description_km" rows="3" placeholder="សេចក្តីសង្ខេប..."></textarea>
                                </div>
                                <div class="form-floating-custom">
                                    <label for="edit_weight_km">ទម្ងន់ (KM)</label>
                                    <input type="text" class="form-control form-control-premium" id="edit_weight_km" name="edit_weight_km" placeholder="ឧದಾហរណ៍ៈ ២៥០ក្រាម">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Shared Settings -->
                <div class="card mb-4">
                    <div class="card-header bg-white"><i class="bi bi-gear me-2"></i>Product Settings</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-floating-custom">
                                    <label for="edit_price">Price ($) *</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-0 rounded-start-pill px-3">$</span>
                                        <input type="number" step="0.01" class="form-control form-control-premium rounded-end-pill" id="edit_price" name="edit_price" required>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating-custom">
                                    <label for="edit_category_id">Category</label>
                                    <select class="form-select form-control-premium rounded-pill" id="edit_category_id" name="edit_category_id">
                                        <option value="">No Category</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Current Image Preview -->
                <div class="card mb-4">
                    <div class="card-header bg-white"><i class="bi bi-image me-2 text-primary"></i>Current Product Image</div>
                    <div class="card-body text-center p-4">
                        <div class="image-preview-wrapper bg-light rounded-4 p-3 d-inline-block shadow-sm">
                            <img id="current_image_preview" src="" alt="Current product" class="img-fluid rounded-3" style="max-height: 200px; object-fit: contain;">
                        </div>
                    </div>
                </div>

                <!-- Image Selection -->
                <div class="card mb-4">
                    <div class="card-header bg-white"><i class="bi bi-upload me-2 text-primary"></i>Update Image (Optional)</div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label small text-muted text-uppercase fw-bold">Upload New Image</label>
                            <div class="input-group">
                                <input type="file" class="form-control form-control-premium" id="edit_image" name="edit_image" accept="image/*">
                            </div>
                        </div>
                        <div class="text-center my-2">
                            <span class="badge bg-light text-muted">OR</span>
                        </div>
                        <div class="form-floating-custom">
                            <label for="edit_image_url">Image URL</label>
                            <input type="url" class="form-control form-control-premium" id="edit_image_url" name="edit_image_url" placeholder="https://...">
                        </div>
                    </div>
                </div>

                <!-- Custom Fields -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center bg-white">
                        <span><i class="bi bi-list-stars me-2 text-success"></i>Custom Fields</span>
                        <div class="btn-group">
                            <button type="button" class="btn btn-sm btn-outline-primary" id="addCustomFieldEdit"><i class="bi bi-plus"></i></button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="copyCustomFieldsEdit"><i class="bi bi-files"></i></button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div id="customFieldsContainerEdit" class="p-3">
                            <!-- Dynamically added -->
                        </div>
                    </div>
                </div>

                <!-- Related Products Section -->
                <div class="card mb-4">
                    <div class="card-header bg-white">
                        <h6 class="mb-0"><i class="bi bi-link-45deg me-2 text-info"></i>Related Products</h6>
                    </div>
                    <div class="card-body">
                        <div class="form-text mb-3">Select products that are related to this one.</div>
                        <div class="form-floating-custom mb-3">
                            <label for="related_products_search_edit">Search Products</label>
                            <input type="text" class="form-control form-control-premium" id="related_products_search_edit" placeholder="Type to search...">
                        </div>
                        <div class="mb-3">
                            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" id="add_custom_related_edit">
                                <i class="bi bi-plus"></i> Add Custom Related
                            </button>
                        </div>
                        <div id="related_products_list_edit" class="border rounded-4 p-3 mb-3 bg-light" style="max-height: 250px; overflow-y: auto;">
                            <!-- Available products loaded here -->
                        </div>
                        <div>
                            <label class="small text-muted fw-bold mb-2">SELECTED PRODUCTS</label>
                            <div id="selected_related_products_edit" class="d-flex flex-wrap gap-2">
                                <!-- Selected products appear here -->
                            </div>
                        </div>
                        <input type="hidden" id="related_products_data_edit" name="related_products_data_edit" value="">
                    </div>
                </div>

                <!-- Status Checkboxes -->
                <div class="card mb-4 bg-light border-0">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="edit_featured" name="edit_featured" value="1">
                                    <label class="form-check-label fw-bold" for="edit_featured">Featured</label>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="edit_best_seller" name="edit_best_seller" value="1">
                                    <label class="form-check-label fw-bold" for="edit_best_seller">Best Seller</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <div class="sidebar-footer">
            <button type="button" class="btn btn-light rounded-pill px-4" id="cancelEditProduct">Cancel</button>
            <button type="submit" form="editProductForm" name="update_product" class="btn btn-warning rounded-pill px-4 shadow">
                <i class="bi bi-check2-circle me-1"></i>Update Product
            </button>
        </div>
    </div>

    <!-- Add Product Sidebar -->
    <div class="product-sidebar" id="addProductSidebar">
        <div class="sidebar-header">
            <h5 class="sidebar-title">
                <i class="bi bi-plus-circle me-2 text-primary"></i>Add New Product
            </h5>
            <button type="button" class="btn-close-sidebar" id="closeAddProductSidebar">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="sidebar-content">
            <form method="POST" enctype="multipart/form-data" id="addProductForm">
                <input type="hidden" id="custom_fields_data_add" name="custom_fields_data_add" value="">
                
                <!-- Language Navigation -->
                <div class="mb-4">
                    <label class="form-label fw-bold text-muted small text-uppercase mb-2">Content Language</label>
                    <ul class="nav nav-tabs-premium" id="addProductLanguageTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="add-en-tab" data-bs-toggle="tab" data-bs-target="#add-en-fields" type="button" role="tab">
                                <img src="https://img.freepik.com/premium-photo/flag-great-britain_406939-4606.jpg?w=32" class="rounded-circle me-1" width="16" height="16"> English
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="add-km-tab" data-bs-toggle="tab" data-bs-target="#add-km-fields" type="button" role="tab">
                                <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/8/83/Flag_of_Cambodia.svg/32px-Flag_of_Cambodia.svg.png" class="rounded-circle me-1" width="16" height="16"> Khmer
                            </button>
                        </li>
                    </ul>
                </div>

                <div class="tab-content" id="addProductLanguageTabsContent">
                    <!-- English Fields -->
                    <div class="tab-pane fade show active" id="add-en-fields" role="tabpanel">
                        <div class="card mb-4">
                            <div class="card-header bg-white"><i class="bi bi-info-circle me-2"></i>Basic Info (EN)</div>
                            <div class="card-body">
                                <div class="form-floating-custom">
                                    <label for="sidebar_name_en">Product Name *</label>
                                    <input type="text" class="form-control form-control-premium" id="sidebar_name_en" name="name_en" required placeholder="Enter product name">
                                </div>
                                <div class="form-floating-custom">
                                    <label for="sidebar_description_en">Short Description *</label>
                                    <textarea class="form-control form-control-premium" id="sidebar_description_en" name="description_en" rows="3" required placeholder="Brief summary..."></textarea>
                                </div>
                                <div class="form-floating-custom">
                                    <label for="sidebar_weight_en">Weight (EN)</label>
                                    <input type="text" class="form-control form-control-premium" id="sidebar_weight_en" name="weight_en" placeholder="e.g. 250g">
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Khmer Fields -->
                    <div class="tab-pane fade" id="add-km-fields" role="tabpanel">
                        <div class="card mb-4">
                            <div class="card-header bg-white"><i class="bi bi-info-circle me-2"></i>Basic Info (KM)</div>
                            <div class="card-body">
                                <div class="form-floating-custom">
                                    <label for="sidebar_name_km">ឈ្មោះផលិតផល (KM) *</label>
                                    <input type="text" class="form-control form-control-premium" id="sidebar_name_km" name="name_km" required placeholder="បញ្ចូលឈ្មោះផលិតផល">
                                </div>
                                <div class="form-floating-custom">
                                    <label for="sidebar_description_km">ការពិពណ៌នាសង្ខេប (KM) *</label>
                                    <textarea class="form-control form-control-premium" id="sidebar_description_km" name="description_km" rows="3" required placeholder="សេចក្តីសង្ខេប..."></textarea>
                                </div>
                                <div class="form-floating-custom">
                                    <label for="sidebar_weight_km">ទម្ងន់ (KM)</label>
                                    <input type="text" class="form-control form-control-premium" id="sidebar_weight_km" name="weight_km" placeholder="ឧದಾហរណ៍ៈ ២៥០ក្រាម">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Shared Settings -->
                <div class="card mb-4">
                    <div class="card-header bg-white"><i class="bi bi-gear me-2"></i>Product Settings</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-floating-custom">
                                    <label for="sidebar_price">Price ($) *</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-0 rounded-start-pill px-3">$</span>
                                        <input type="number" step="0.01" class="form-control form-control-premium rounded-end-pill" id="sidebar_price" name="price" required>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating-custom">
                                    <label for="sidebar_category">Category</label>
                                    <select class="form-select form-control-premium rounded-pill" id="sidebar_category" name="category_id">
                                        <option value="">No Category</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Image Upload -->
                <div class="card mb-4">
                    <div class="card-header bg-white"><i class="bi bi-image me-2"></i>Product Image</div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label small text-muted text-uppercase fw-bold">Upload Image</label>
                            <input type="file" class="form-control form-control-premium" id="sidebar_image" name="image" accept="image/*">
                            <div class="mt-3 text-center">
                                <img id="sidebar_image_preview" src="" alt="Preview" class="img-thumbnail rounded-3 shadow-sm mx-auto" style="display: none; max-width: 120px; max-height: 120px; object-fit: contain;">
                            </div>
                        </div>
                        <div class="text-center my-2">
                            <span class="badge bg-light text-muted">OR</span>
                        </div>
                        <div class="form-floating-custom">
                            <label for="sidebar_image_url">Image URL</label>
                            <input type="url" class="form-control form-control-premium" id="sidebar_image_url" name="image_url" placeholder="https://...">
                        </div>
                    </div>
                </div>

                <!-- Custom Fields -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center bg-white">
                        <span><i class="bi bi-list-stars me-2 text-success"></i>Custom Fields</span>
                        <div class="btn-group">
                            <button type="button" class="btn btn-sm btn-outline-success" id="addCustomFieldAdd"><i class="bi bi-plus"></i></button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="copyCustomFieldsAdd"><i class="bi bi-files"></i></button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div id="customFieldsContainerAdd" class="p-3">
                            <!-- Dynamically added -->
                        </div>
                    </div>
                </div>

                <!-- Related Products Section -->
                <div class="card mb-4">
                    <div class="card-header bg-white">
                        <h6 class="mb-0"><i class="bi bi-link-45deg me-2 text-info"></i>Related Products</h6>
                    </div>
                    <div class="card-body">
                        <div class="form-text mb-3">Select products that are related to this one.</div>
                        <div class="form-floating-custom mb-3">
                            <label for="related_products_search_add">Search Products</label>
                            <input type="text" class="form-control form-control-premium" id="related_products_search_add" placeholder="Type to search...">
                        </div>
                        <div class="mb-3">
                            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" id="add_custom_related_add">
                                <i class="bi bi-plus"></i> Add Custom Related
                            </button>
                        </div>
                        <div id="related_products_list_add" class="border rounded-4 p-3 mb-3 bg-light" style="max-height: 250px; overflow-y: auto;">
                            <!-- Available products loaded here -->
                        </div>
                        <div>
                            <label class="small text-muted fw-bold mb-2">SELECTED PRODUCTS</label>
                            <div id="selected_related_products_add" class="d-flex flex-wrap gap-2">
                                <!-- Selected products appear here -->
                            </div>
                        </div>
                        <input type="hidden" id="related_products_data_add" name="related_products_data_add" value="">
                    </div>
                </div>

                <!-- Status Checkboxes -->
                <div class="card mb-4 bg-light border-0">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="sidebar_featured" name="featured" value="1">
                                    <label class="form-check-label fw-bold" for="sidebar_featured">Featured</label>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="sidebar_best_seller" name="best_seller" value="1">
                                    <label class="form-check-label fw-bold" for="sidebar_best_seller">Best Seller</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <div class="sidebar-footer">
            <button type="button" class="btn btn-light rounded-pill px-4" id="cancelAddProduct">Cancel</button>
            <button type="submit" form="addProductForm" name="add_product" class="btn btn-primary rounded-pill px-4 shadow">
                <i class="bi bi-plus-circle me-1"></i>Add Product
            </button>
        </div>
    </div>

    <!-- Add Category Sidebar -->
    <!-- Add Category Sidebar -->
    <div class="category-sidebar" id="addCategorySidebar">
        <div class="sidebar-header">
            <h5 class="sidebar-title">
                <i class="bi bi-tag-fill me-2 text-success"></i>Add New Category
            </h5>
            <button type="button" class="btn-close-sidebar" id="closeAddCategorySidebar">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="sidebar-content">
            <form method="POST" enctype="multipart/form-data" id="addCategoryForm">
                <!-- Language Navigation -->
                <div class="mb-4">
                    <label class="form-label fw-bold text-muted small text-uppercase mb-2">Category Language</label>
                    <ul class="nav nav-tabs-premium" id="addCategoryLanguageTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="category-en-tab" data-bs-toggle="tab" data-bs-target="#category-en-fields" type="button" role="tab">
                                EN
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="category-km-tab" data-bs-toggle="tab" data-bs-target="#category-km-fields" type="button" role="tab">
                                KM
                            </button>
                        </li>
                    </ul>
                </div>

                <div class="tab-content" id="addCategoryLanguageTabsContent">
                    <!-- English Fields -->
                    <div class="tab-pane fade show active" id="category-en-fields" role="tabpanel">
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-body p-4">
                                <div class="form-floating-custom">
                                    <label for="sidebar_category_name_en">Category Name (EN) *</label>
                                    <input type="text" class="form-control form-control-premium" id="sidebar_category_name_en" name="category_name_en" required placeholder="e.g. Coffee Beans">
                                </div>
                                <div class="form-floating-custom">
                                    <label for="sidebar_category_description_en">Description (EN)</label>
                                    <textarea class="form-control form-control-premium" id="sidebar_category_description_en" name="category_description_en" rows="3"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Khmer Fields -->
                    <div class="tab-pane fade" id="category-km-fields" role="tabpanel">
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-body p-4">
                                <div class="form-floating-custom">
                                    <label for="sidebar_category_name_km">Category Name (KM) *</label>
                                    <input type="text" class="form-control form-control-premium" id="sidebar_category_name_km" name="category_name_km" required>
                                </div>
                                <div class="form-floating-custom">
                                    <label for="sidebar_category_description_km">Description (KM)</label>
                                    <textarea class="form-control form-control-premium" id="sidebar_category_description_km" name="category_description_km" rows="3"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Image Upload Section -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-bottom py-3 fw-bold">
                        <i class="bi bi-image me-2 text-primary"></i>Category Image
                    </div>
                    <div class="card-body p-4">
                        <div class="mb-3">
                            <label class="small text-muted fw-bold mb-2">Upload File</label>
                            <input type="file" class="form-control form-control-premium" id="sidebar_category_image" name="category_image" accept="image/*">
                            <div class="mt-2 text-center">
                                <img id="sidebar_category_image_preview" src="" alt="Preview" class="img-thumbnail rounded-3 shadow-sm mx-auto" style="display: none; max-width: 120px; max-height: 120px; object-fit: contain;">
                            </div>
                        </div>
                        <div class="text-center my-2">
                            <span class="badge bg-light text-muted">OR</span>
                        </div>
                        <div class="form-floating-custom">
                            <label class="small text-muted fw-bold mb-2">Image URL</label>
                            <input type="url" class="form-control form-control-premium" id="sidebar_category_image_url" name="category_image_url" placeholder="https://...">
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <div class="sidebar-footer">
            <button type="button" class="btn btn-light rounded-pill px-4" id="cancelAddCategory">Cancel</button>
            <button type="submit" form="addCategoryForm" name="add_category" class="btn btn-success rounded-pill px-4 shadow">
                <i class="bi bi-plus-circle me-1"></i>Add Category
            </button>
        </div>
    </div>

    <!-- Edit Category Modal -->
    <div class="modal fade" id="editCategoryModal" tabindex="-1" aria-labelledby="editCategoryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg overflow-hidden">
                <div class="modal-header bg-dark text-white p-4">
                    <h5 class="modal-title d-flex align-items-center" id="editCategoryModalLabel">
                        <i class="bi bi-pencil-square me-2 text-warning"></i> 
                        <span class="fw-bold">Edit Category</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" id="edit_category_id" name="edit_category_id" value="">
                    <div class="modal-body p-4 bg-light">
                        <!-- Language Navigation -->
                        <div class="mb-4 text-center">
                            <div class="btn-group p-1 bg-white rounded-pill shadow-sm" role="group">
                                <button type="button" class="btn btn-outline-warning rounded-pill px-4 active" id="edit-cat-en-btn">EN</button>
                                <button type="button" class="btn btn-outline-warning rounded-pill px-4" id="edit-cat-km-btn">KM</button>
                            </div>
                        </div>

                        <div id="edit-cat-lang-en">
                            <div class="card border-0 shadow-sm mb-4">
                                <div class="card-body p-4">
                                    <div class="form-floating-custom">
                                        <label class="small text-muted fw-bold">CATEGORY NAME (EN) *</label>
                                        <input type="text" class="form-control form-control-premium" id="edit_category_name_en" name="edit_category_name_en" required>
                                    </div>
                                    <div class="form-floating-custom">
                                        <label class="small text-muted fw-bold">DESCRIPTION (EN)</label>
                                        <textarea class="form-control form-control-premium" id="edit_category_description_en" name="edit_category_description_en" rows="3"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="edit-cat-lang-km" style="display:none;">
                            <div class="card border-0 shadow-sm mb-4">
                                <div class="card-body p-4">
                                    <div class="form-floating-custom">
                                        <label class="small text-muted fw-bold">CATEGORY NAME (KM) *</label>
                                        <input type="text" class="form-control form-control-premium" id="edit_category_name_km" name="edit_category_name_km" required>
                                    </div>
                                    <div class="form-floating-custom">
                                        <label class="small text-muted fw-bold">DESCRIPTION (KM)</label>
                                        <textarea class="form-control form-control-premium" id="edit_category_description_km" name="edit_category_description_km" rows="3"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card border-0 shadow-sm">
                            <div class="card-body p-4">
                                <div class="row align-items-center">
                                    <div class="col-md-4 text-center mb-3 mb-md-0">
                                        <label class="small text-muted fw-bold d-block mb-2">CURRENT IMAGE</label>
                                        <img id="current_category_image_preview" src="" alt="Category image" class="img-thumbnail rounded-3 shadow-sm mx-auto" style="max-width: 120px; max-height: 120px; object-fit: contain;">
                                    </div>
                                    <div class="col-md-8">
                                        <div class="mb-3">
                                            <label class="small text-muted fw-bold mb-2">UPLOAD NEW</label>
                                            <input type="file" class="form-control form-control-premium" id="edit_category_image" name="edit_category_image" accept="image/*">
                                        </div>
                                        <div class="form-floating-custom">
                                            <label class="small text-muted fw-bold mb-2">OR IMAGE URL</label>
                                            <input type="url" class="form-control form-control-premium" id="edit_category_image_url" name="edit_category_image_url">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-white border-0 p-4">
                        <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_category" class="btn btn-warning rounded-pill px-4 shadow fw-bold">
                            <i class="bi bi-save me-1"></i>Update Category
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Detailed Product Information Modal -->
    <!-- Detailed Product Information Modal -->
    <div class="modal fade" id="detailedProductModal" tabindex="-1" aria-labelledby="detailedProductModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg overflow-hidden">
                <div class="modal-header bg-dark text-white p-4">
                    <h5 class="modal-title d-flex align-items-center" id="detailedProductModalLabel">
                        <i class="bi bi-gear-fill me-2 text-info"></i> 
                        <span class="fw-bold"><?php echo getSetting('admin_detailed_product_modal_title', 'Detailed Product Information', $currentLanguage); ?></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="detailedProductForm" accept-charset="UTF-8">
                    <input type="hidden" id="detailed_base_product_id" name="base_product_id" value="">
                    <input type="hidden" id="custom_fields_data" name="custom_fields_data" value="">
                    <div class="modal-body p-4 bg-light">
                        <!-- Language Navigation for Modal -->
                        <div class="mb-4 text-center">
                            <div class="btn-group p-1 bg-white rounded-pill shadow-sm" role="group">
                                <button type="button" class="btn btn-outline-primary rounded-pill px-4 active" id="modal-en-btn">EN</button>
                                <button type="button" class="btn btn-outline-primary rounded-pill px-4" id="modal-km-btn">KM</button>
                            </div>
                        </div>

                        <div id="modal-lang-en">
                            <div class="card border-0 shadow-sm mb-4">
                                <div class="card-header bg-white border-bottom fw-bold py-3">
                                    <i class="bi bi-info-circle me-2 text-primary"></i>ENGLISH CONTENT
                                </div>
                                <div class="card-body p-4">
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <div class="form-floating-custom">
                                                <label class="small text-muted fw-bold">DETAILED DESCRIPTION (EN)</label>
                                                <textarea class="form-control form-control-premium" id="detailed_detailed_description_en" name="edit_detailed_description_en" rows="4" placeholder="Rich description..."></textarea>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-floating-custom">
                                                <label class="small text-muted fw-bold">INGREDIENTS (EN)</label>
                                                <textarea class="form-control form-control-premium" id="detailed_ingredients_en" name="edit_ingredients_en" rows="2" placeholder="List components..."></textarea>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-floating-custom">
                                                <label class="small text-muted fw-bold">BREWING (EN)</label>
                                                <textarea class="form-control form-control-premium" id="detailed_brewing_instructions_en" name="edit_brewing_instructions_en" rows="2" placeholder="Instructions..."></textarea>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-floating-custom">
                                                <label class="small text-muted fw-bold">ORIGIN (EN)</label>
                                                <input type="text" class="form-control form-control-premium" id="detailed_origin_en" name="edit_origin_en">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-floating-custom">
                                                <label class="small text-muted fw-bold">WEIGHT (EN)</label>
                                                <input type="text" class="form-control form-control-premium" id="detailed_weight_en" name="edit_weight_en">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-floating-custom">
                                                <label class="small text-muted fw-bold">TASTING NOTES (EN)</label>
                                                <input type="text" class="form-control form-control-premium" id="detailed_tasting_notes_en" name="edit_tasting_notes_en">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="modal-lang-km" style="display:none;">
                            <div class="card border-0 shadow-sm mb-4">
                                <div class="card-header bg-white border-bottom fw-bold py-3">
                                    <i class="bi bi-info-circle me-2 text-primary"></i>KHMER CONTENT
                                </div>
                                <div class="card-body p-4">
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <div class="form-floating-custom">
                                                <label class="small text-muted fw-bold">ការពិពណ៌នាលម្អិត (KM)</label>
                                                <textarea class="form-control form-control-premium" id="detailed_detailed_description_km" name="edit_detailed_description_km" rows="4"></textarea>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-floating-custom">
                                                <label class="small text-muted fw-bold">គ្រឿងផ្សំ (KM)</label>
                                                <textarea class="form-control form-control-premium" id="detailed_ingredients_km" name="edit_ingredients_km" rows="2"></textarea>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-floating-custom">
                                                <label class="small text-muted fw-bold">ការណែនាំអំពីការឆុង (KM)</label>
                                                <textarea class="form-control form-control-premium" id="detailed_brewing_instructions_km" name="edit_brewing_instructions_km" rows="2"></textarea>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-floating-custom">
                                                <label class="small text-muted fw-bold">ប្រភព (KM)</label>
                                                <input type="text" class="form-control form-control-premium" id="detailed_origin_km" name="edit_origin_km">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-floating-custom">
                                                <label class="small text-muted fw-bold">ទម្ងន់ (KM)</label>
                                                <input type="text" class="form-control form-control-premium" id="detailed_weight_km" name="edit_weight_km">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-floating-custom">
                                                <label class="small text-muted fw-bold">កំណត់ចំណាំរសជាតិ (KM)</label>
                                                <input type="text" class="form-control form-control-premium" id="detailed_tasting_notes_km" name="edit_tasting_notes_km">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Roast Level Shared -->
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-body p-4">
                                <div class="form-floating-custom">
                                    <label class="small text-muted fw-bold">ROAST LEVEL</label>
                                    <select class="form-select form-control-premium" id="detailed_roast_level" name="edit_roast_level">
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
                            </div>
                        </div>

                        <!-- Custom Fields Section -->
                        <div class="card border-0 shadow-sm">
                            <div class="card-header d-flex justify-content-between align-items-center bg-white border-bottom py-3">
                                <h6 class="fw-bold text-dark mb-0"><i class="bi bi-list-stars me-2 text-success"></i>Custom Fields</h6>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-success btn-sm rounded-pill px-3" id="addCustomField">
                                        <i class="bi bi-plus"></i> Add
                                    </button>
                                    <button type="button" class="btn btn-primary btn-sm rounded-pill px-3" id="copyCustomFields">
                                        <i class="bi bi-files"></i> Copy
                                    </button>
                                </div>
                            </div>
                            <div class="card-body p-3">
                                <div id="customFieldsContainer">
                                    <!-- Custom fields dynamically added -->
                                </div>
                                <div class="form-text text-center mt-2">Additional technical specifications or information</div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-white border-0 p-4">
                        <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_product" class="btn btn-info rounded-pill px-4 shadow text-white fw-bold">
                            <i class="bi bi-save me-1"></i>Save All Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal for copying custom fields -->
    <div class="modal fade" id="copyFieldsModal" tabindex="-1" aria-hidden="true" style="z-index: 2050;">
        <div class="modal-dialog">
            <div class="modal-content shadow-lg">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-files"></i> Copy Custom Fields</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Select Product to copy fields from:</label>
                        <select id="copyFieldsProductSelect" class="form-select">
                            <option value="">-- Select a product --</option>
                        </select>
                        <div class="form-text mt-2 text-warning">
                            <i class="bi bi-exclamation-triangle"></i> Warning: This will append the copied custom fields to your current list.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirmCopyFields">
                        <i class="bi bi-check-circle"></i> Copy Now
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Product Sidebar Functionality
        var addProductBtn = document.getElementById('addProductBtn');
        var addProductSidebar = document.getElementById('addProductSidebar');
        var closeAddProductSidebar = document.getElementById('closeAddProductSidebar');
        var cancelAddProduct = document.getElementById('cancelAddProduct');
        var sidebarOverlay = document.createElement('div');
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
            var alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                try { var bsAlert = new bootstrap.Alert(alert); bsAlert.close(); } catch(e) {}
            });
        }, 5000);

        // Form validation for the sidebar
        (function(){
var sidebarForm = document.getElementById('addProductForm');
            if (sidebarForm) {
                sidebarForm.addEventListener('submit', function(e) {
                    var nameEn = document.getElementById('sidebar_name_en').value.trim();
                    var nameKm = document.getElementById('sidebar_name_km').value.trim();
                    var descriptionEn = document.getElementById('sidebar_description_en').value.trim();
                    var descriptionKm = document.getElementById('sidebar_description_km').value.trim();
                    var price = document.getElementById('sidebar_price').value;

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
                    var customFields = collectCustomFields('customFieldsContainerAdd');
                    document.getElementById('custom_fields_data_add').value = JSON.stringify(customFields);
                });
            }
        })();

        // Edit Product Sidebar Functionality
        var editProductSidebar = document.getElementById('editProductSidebar');
        var closeEditProductSidebar = document.getElementById('closeEditProductSidebar');
        var cancelEditProduct = document.getElementById('cancelEditProduct');

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
var editSidebarForm = document.getElementById('editProductForm');
            if (editSidebarForm) {
                editSidebarForm.addEventListener('submit', function(e) {
                    var nameEn = document.getElementById('edit_name_en').value.trim();
                    var nameKm = document.getElementById('edit_name_km').value.trim();
                    var descriptionEn = document.getElementById('edit_description_en').value.trim();
                    var descriptionKm = document.getElementById('edit_description_km').value.trim();
                    var price = document.getElementById('edit_price').value;

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
                    var customFields = collectCustomFields('customFieldsContainerEdit');
                    document.getElementById('custom_fields_data_edit').value = JSON.stringify(customFields);
                });
            }
        })();

        // Category Sidebar Functionality
        var addCategoryBtn = document.getElementById('addCategoryBtn');
        var addCategorySidebar = document.getElementById('addCategorySidebar');
        var closeAddCategorySidebar = document.getElementById('closeAddCategorySidebar');
        var cancelAddCategory = document.getElementById('cancelAddCategory');

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
            var categorySidebarForm = document.getElementById('addCategoryForm');
            if (categorySidebarForm) {
                categorySidebarForm.addEventListener('submit', function(e) {
                    var nameEn = document.getElementById('sidebar_category_name_en').value.trim();
                    var nameKm = document.getElementById('sidebar_category_name_km').value.trim();

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
            var notification = document.createElement('div');
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
                var button = e.target.closest('.edit-product-btn');
                var baseProductId = button.getAttribute('data-base-product-id');

                // Find the product data from the table
                var row = button.closest('tr');
                var productData = {
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
                var button = e.target.closest('.settings-product-btn');
                var baseProductId = button.getAttribute('data-base-product-id');

                // Clear previous data
                document.getElementById('detailed_base_product_id').value = baseProductId;
                
                // Fetch English version
                var fetchEn = fetch(window.location.href, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'get_product_data=1&base_product_id=' + baseProductId + '&language=en'
                }).then(r => r.json());

                // Fetch Khmer version
                var fetchKm = fetch(window.location.href, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'get_product_data=1&base_product_id=' + baseProductId + '&language=km'
                }).then(r => r.json());

                Promise.all([fetchEn, fetchKm]).then(([enData, kmData]) => {
                    if (enData.success) {
                        var p = enData.product;
                        document.getElementById('detailed_detailed_description_en').value = p.detailed_description || '';
                        document.getElementById('detailed_ingredients_en').value = p.ingredients || '';
                        document.getElementById('detailed_origin_en').value = p.origin || '';
                        document.getElementById('detailed_brewing_instructions_en').value = p.brewing_instructions || '';
                        document.getElementById('detailed_tasting_notes_en').value = p.tasting_notes || '';
                        document.getElementById('detailed_weight_en').value = p.weight || '';
                        document.getElementById('detailed_roast_level').value = p.roast_level || '';
                        // Load custom fields from English version
                        loadCustomFields(p.custom_fields || '{}');
                    }
                    if (kmData.success) {
                        var p = kmData.product;
                        document.getElementById('detailed_detailed_description_km').value = p.detailed_description || '';
                        document.getElementById('detailed_ingredients_km').value = p.ingredients || '';
                        document.getElementById('detailed_origin_km').value = p.origin || '';
                        document.getElementById('detailed_brewing_instructions_km').value = p.brewing_instructions || '';
                        document.getElementById('detailed_tasting_notes_km').value = p.tasting_notes || '';
                        document.getElementById('detailed_weight_km').value = p.weight || '';
                        // Roast level is shared
                    }

                    var detailedModal = new bootstrap.Modal(document.getElementById('detailedProductModal'));
                    detailedModal.show();
                }).catch(err => {
                    console.error('Error fetching dual-lang product data:', err);
                    showNotification('Error loading product details.', 'error');
                });
            }

            // Handle edit category button clicks
            if (e.target.closest('.edit-category-btn')) {
                e.preventDefault();
                var button = e.target.closest('.edit-category-btn');
                var categoryId = button.getAttribute('data-category-id');
                var categoryData = allCategoriesData.find(function(category) { return category.id == categoryId; });
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
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    var product = data.product;

                    document.getElementById('base_product_id').value = productData.base_product_id;
                    document.getElementById('edit_name_en').value = product.name || '';
                    document.getElementById('edit_description_en').value = product.description || '';
                    document.getElementById('edit_price').value = product.price || productData.price || '';
                    document.getElementById('edit_weight_en').value = product.weight || '';
                    document.getElementById('edit_featured').checked = (product.featured == 1) || (productData.featured == 1);
                    document.getElementById('edit_best_seller').checked = (product.best_seller == 1) || (productData.best_seller == 1);

                    var imagePreview = document.getElementById('current_image_preview');
                    var imageSrc = product.image || productData.image;
                    if (imageSrc) {
                        imagePreview.src = imageSrc;
                        imagePreview.style.display = 'block';
                    } else {
                        imagePreview.style.display = 'none';
                    }

                    document.getElementById('edit_image').value = '';
                    document.getElementById('edit_image_url').value = '';

                    document.getElementById('edit_category_id').value = product.category_id || productData.category_id || '';

                    // Load custom fields from English version
                    loadCustomFields(product.custom_fields || '{}', 'customFieldsContainerEdit');
                }
            })
            .catch(function(error) {
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
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    var product = data.product;

                    document.getElementById('edit_name_km').value = product.name || '';
                    document.getElementById('edit_description_km').value = product.description || '';
                    document.getElementById('edit_weight_km').value = product.weight || '';
                }
            })
            .catch(function(error) {
                console.error('Error fetching Khmer product data:', error);
            });

            // Open the edit sidebar
            var editSidebar = document.getElementById('editProductSidebar');
            var sidebarOverlay = document.querySelector('.sidebar-overlay');
            editSidebar.classList.add('open');
            sidebarOverlay.classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        // Custom Fields Management Functions
        function loadCustomFields(customFieldsJson, containerId = 'customFieldsContainer') {
            var container = document.getElementById(containerId);
            container.innerHTML = '';

            try {
                var customFields = JSON.parse(customFieldsJson);
                // Handle both old format (simple object) and new format (with translations)
                if (customFields && typeof customFields === 'object') {
                        Object.keys(customFields).forEach(function(key) {
                        var fieldData = customFields[key];
                            if (typeof fieldData === 'string') {
                                // Old format: convert to new format
                                fieldData = {
                                    name: { en: key.replace(':', ''), km: '' },
                                    value: { en: fieldData, km: '' },
                                    type: 'text'
                                };
                            }

                            // Normalize table rows/value-pairs to expected shape
                            if (fieldData && fieldData.type && fieldData.type === 'table' && Array.isArray(fieldData.value)) {
                                fieldData.value = fieldData.value.map(function(r) {
                                    // Normalize label
                                    var label = (r && r.label) ? r.label : { en: '', km: '' };
                                    var normLabel = {
                                        en: (label.en !== undefined) ? label.en : (label[Object.keys(label)[0]] || ''),
                                        km: (label.km !== undefined) ? label.km : ''
                                    };

                                    // Normalize values array
                                    var valuesArray = Array.isArray(r.value) ? r.value : (r.value ? [r.value] : []);
                                    var normValues = valuesArray.map(function(vp) {
                                        if (!vp) return { en: '', km: '' };
                                        if (typeof vp === 'string') return { en: vp, km: '' };
                                        return {
                                            en: vp.en !== undefined ? vp.en : (vp[Object.keys(vp)[0]] || ''),
                                            km: vp.km !== undefined ? vp.km : ''
                                        };
                                    });

                                    return { label: normLabel, value: normValues };
                                });
                            }

                            if (fieldData && fieldData.name && fieldData.value) {
                                try { console.log('Loading custom field for editor:', key, fieldData); } catch(e) {}
                                addCustomField(fieldData, containerId, key);
                            }
                        });
                }
            } catch (e) {
                console.error('Error parsing custom fields:', e);
            }
        }

        function addCustomField(fieldData = null, containerId = 'customFieldsContainer', existingKey = null) {
            var container = document.getElementById(containerId);
            var fieldId = existingKey || ('custom_field_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9));

            // Default values
            var fieldNameEn = '';
            var fieldNameKm = '';
            var fieldValueEn = '';
            var fieldValueKm = '';
            var fieldType = 'text';
            var tableRows = [];

            // If fieldData is provided, extract translations
            if (fieldData && typeof fieldData === 'object' && fieldData.name && fieldData.value) {
                fieldNameEn = fieldData.name.en || '';
                fieldNameKm = fieldData.name.km || '';
                // If this is a table-type field, value may be an array of rows
                if (fieldData.type && fieldData.type === 'table' && Array.isArray(fieldData.value)) {
                    fieldType = 'table';
                    tableRows = fieldData.value;
                } else {
                    fieldValueEn = fieldData.value.en || '';
                    fieldValueKm = fieldData.value.km || '';
                }
                if (fieldData.type) fieldType = fieldData.type;
            }

            // Pre-build table rows HTML to avoid nested template-literal complexity
            var tableRowsHtml = '';
            if (tableRows && tableRows.length) {
                tableRows.forEach(function(r, ri) {
                    tableRowsHtml += '<div class="table-row mb-2" data-row-index="' + ri + '">';
                    tableRowsHtml += '<div class="d-flex gap-2 align-items-start mb-2">';
                    tableRowsHtml += '<input class="form-control form-control-sm row-label-en" placeholder="Label (EN)" value="' + (r.label && r.label.en ? ('' + r.label.en).replace(/"/g, '&quot;') : '') + '">';
                    tableRowsHtml += '<input class="form-control form-control-sm row-label-km" placeholder="Label (KM)" value="' + (r.label && r.label.km ? ('' + r.label.km).replace(/"/g, '&quot;') : '') + '">';
                    tableRowsHtml += '<div class="ms-auto d-flex gap-2">';
                    tableRowsHtml += '<button type="button" class="btn btn-sm btn-outline-secondary move-row-up" title="Move row up">↑</button>';
                    tableRowsHtml += '<button type="button" class="btn btn-sm btn-outline-secondary move-row-down" title="Move row down">↓</button>';
                    tableRowsHtml += '<button type="button" class="btn btn-sm btn-outline-secondary add-value-pair">Add Value</button>';
                    tableRowsHtml += '<button type="button" class="btn btn-sm btn-outline-danger remove-table-row">Remove</button>';
                    tableRowsHtml += '</div></div>';
                    tableRowsHtml += '<div class="value-pairs">';
                    if (r.value && Array.isArray(r.value)) {
                        r.value.forEach(function(vp) {
                            tableRowsHtml += '<div class="value-pair d-flex gap-2 align-items-start mb-1 flex-nowrap">';
                            tableRowsHtml += '<input class="form-control form-control-sm row-value-en" placeholder="Value (EN)" value="' + (vp && vp.en ? ('' + vp.en).replace(/"/g, '&quot;') : '') + '">';
                            tableRowsHtml += '<input class="form-control form-control-sm row-value-km" placeholder="Value (KM)" value="' + (vp && vp.km ? ('' + vp.km).replace(/"/g, '&quot;') : '') + '">';
                            tableRowsHtml += '<button type="button" class="btn btn-sm btn-outline-danger remove-value-pair">Remove</button>';
                            tableRowsHtml += '</div>';
                        });
                    } else {
                        tableRowsHtml += '<div class="value-pair d-flex gap-2 align-items-start mb-1 flex-nowrap">';
                        tableRowsHtml += '<input class="form-control form-control-sm row-value-en" placeholder="Value (EN)">';
                        tableRowsHtml += '<input class="form-control form-control-sm row-value-km" placeholder="Value (KM)">';
                        tableRowsHtml += '<button type="button" class="btn btn-sm btn-outline-danger remove-value-pair">Remove</button>';
                        tableRowsHtml += '</div>';
                    }
                    tableRowsHtml += '</div></div>';
                });
            }

            var fieldHtml = `
                <div class="custom-field-item mb-3 border rounded p-3 bg-light" data-field-id="${fieldId}">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <h6 class="fw-bold text-primary mb-0">Field Name</h6>
                        <div class="d-flex gap-2">
                            <span class="drag-handle" style="cursor: grab; font-size: 1.2em;">⋮⋮</span>
                            <button type="button" class="btn btn-outline-danger btn-sm remove-field" title="Remove field">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                    <div class="mb-3">
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
                    <div class="mb-3">
                        <label class="form-label">Field Type</label>
                        <select class="form-select form-select-sm field-type">
                            <option value="text" ${fieldType === 'text' ? 'selected' : ''}>Text</option>
                            <option value="table" ${fieldType === 'table' ? 'selected' : ''}>Nutrition Table</option>
                        </select>
                    </div>
                    
                    <div class="table-editor mt-3" style="display: ${fieldType === 'table' ? 'block' : 'none'};">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="mb-0">Nutrition Table Rows</h6>
                            <button type="button" class="btn btn-sm btn-outline-secondary add-table-row">Add Row</button>
                        </div>
                        <div class="table-rows-container">
                            <!-- rows will be inserted here -->
                            ${ tableRowsHtml }
                        </div>
                    </div>
                </div>
            `;

            container.insertAdjacentHTML('beforeend', fieldHtml);
        }

        function addCustomFieldToContainer(containerId) {
            addCustomField(null, containerId);
        }

        function collectCustomFields(containerId = 'customFieldsContainer') {
            var customFields = {};
            var fieldItems = document.querySelectorAll(`#${containerId} .custom-field-item`);
            console.log('Found field items in', containerId, ':', fieldItems.length);

            fieldItems.forEach(function(item, index) {
                console.log('Processing field item', index);
                var nameEnInput = item.querySelector('.field-name-en');
                var nameKmInput = item.querySelector('.field-name-km');
                var valueEnInput = item.querySelector('.field-value-en');
                var valueKmInput = item.querySelector('.field-value-km');

                if (nameEnInput && ((valueEnInput && nameEnInput.value.trim() && valueEnInput.value.trim()) || item.querySelector('.field-type') && item.querySelector('.field-type').value === 'table')) {
                    var existingFieldId = item.dataset.fieldId;
                    var fieldId = existingFieldId || ('field_' + Date.now() + '_' + index);
                    var typeSelect = item.querySelector('.field-type');
                    var typeValue = typeSelect ? typeSelect.value : 'text';

                    // If table, collect rows
                        if (typeValue === 'table') {
                            var rows = [];
                            item.querySelectorAll('.table-row').forEach(function(r, ri) {
                                var labelEn = r.querySelector('.row-label-en') ? r.querySelector('.row-label-en').value.trim() : '';
                                var labelKm = r.querySelector('.row-label-km') ? r.querySelector('.row-label-km').value.trim() : '';
                                var values = [];
                                r.querySelectorAll('.value-pair').forEach(function(vp) {
                                    var valueEn = vp.querySelector('.row-value-en') ? vp.querySelector('.row-value-en').value.trim() : '';
                                    var valueKm = vp.querySelector('.row-value-km') ? vp.querySelector('.row-value-km').value.trim() : '';
                                    if (valueEn || valueKm) values.push({ en: valueEn, km: valueKm });
                                });
                                if (labelEn || values.length) {
                                    rows.push({ label: { en: labelEn, km: labelKm }, value: values });
                                }
                            });

                                    customFields[fieldId] = {
                                name: { en: nameEnInput.value.trim(), km: nameKmInput ? nameKmInput.value.trim() : '' },
                                value: rows,
                                type: 'table'
                            };
                        } else {
                                customFields[fieldId] = {
                            name: {
                                en: nameEnInput.value.trim(),
                                km: nameKmInput ? nameKmInput.value.trim() : ''
                            },
                            value: {
                                en: valueEnInput ? valueEnInput.value.trim() : '',
                                km: valueKmInput ? valueKmInput.value.trim() : ''
                            },
                            type: 'text'
                        };
                    }
                    console.log('Added field:', fieldId, customFields[fieldId]);
                } else {
                    console.log('Skipped field - missing required inputs or empty values');
                }
            });

            console.log('Final custom fields object:', customFields);
            return customFields;
        }

        // Copy Custom Fields Logic
        var currentCopyTargetContainer = '';
        var allProductsList = [];

        function loadProductsForCopy() {
            if (allProductsList.length > 0) return Promise.resolve(allProductsList);
            
            return fetch(window.location.href + (window.location.href.includes('?') ? '&' : '?') + 'action=get_all_products')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        allProductsList = data.products;
                        const select = document.getElementById('copyFieldsProductSelect');
                        select.innerHTML = '<option value="">-- Select a product --</option>';
                        allProductsList.forEach(p => {
                            const option = document.createElement('option');
                            option.value = p.base_product_id;
                            option.textContent = p.name + ' ($' + p.price + ')';
                            select.appendChild(option);
                        });
                        return allProductsList;
                    }
                    return [];
                })
                .catch(err => {
                    console.error('Error loading products for copy:', err);
                    return [];
                });
        }

        function openCopyModal(containerId) {
            currentCopyTargetContainer = containerId;
            loadProductsForCopy().then(() => {
                const modalElement = document.getElementById('copyFieldsModal');
                const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
                // Ensure z-index is high enough if it opens over another modal
                modalElement.style.zIndex = '2100';
                modal.show();
            });
        }

        // Add event listeners for the copy buttons
        document.addEventListener('click', function(e) {
            var btn = e.target.closest('#copyCustomFieldsAdd, #copyCustomFieldsEdit, #copyCustomFields');
            if (btn) {
                var target = '';
                if (btn.id === 'copyCustomFieldsAdd') target = 'customFieldsContainerAdd';
                else if (btn.id === 'copyCustomFieldsEdit') target = 'customFieldsContainerEdit';
                else if (btn.id === 'copyCustomFields') target = 'customFieldsContainer';
                
                if (target) openCopyModal(target);
            }
        });

        document.getElementById('confirmCopyFields').addEventListener('click', function() {
            const baseProductId = document.getElementById('copyFieldsProductSelect').value;
            if (!baseProductId) {
                alert('Please select a product.');
                return;
            }

            const confirmBtn = this;
            const originalHtml = confirmBtn.innerHTML;
            confirmBtn.disabled = true;
            confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...';

            // Function to process and add fields
            const processFields = (customFieldsJson) => {
                try {
                    const customFields = typeof customFieldsJson === 'string' ? JSON.parse(customFieldsJson) : customFieldsJson;
                    if (!customFields || Object.keys(customFields).length === 0 || (Object.keys(customFields).length === 1 && customFields.show_in_collection !== undefined)) {
                        return 0;
                    }

                    let count = 0;
                    Object.keys(customFields).forEach(key => {
                        if (key === 'show_in_collection') return;
                        
                        let fieldData = customFields[key];
                        // Normalization (support old format)
                        if (typeof fieldData === 'string') {
                            fieldData = {
                                name: { en: key.replace(':', ''), km: '' },
                                value: { en: fieldData, km: '' },
                                type: 'text'
                            };
                        } else if (fieldData && fieldData.type === 'table' && Array.isArray(fieldData.value)) {
                            // Basic table normalization if needed
                        }

                        if (fieldData && typeof fieldData === 'object') {
                            const newKey = 'field_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
                            addCustomField(fieldData, currentCopyTargetContainer, newKey);
                            count++;
                        }
                    });
                    return count;
                } catch (e) {
                    console.error('Error in processFields:', e);
                    return -1;
                }
            };

            // Fetch custom fields (try current lang, then fallbacks)
            const fetchAndCopy = (lang) => {
                return fetch(window.location.href, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `get_product_data=1&base_product_id=${baseProductId}&language=${lang}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.product && data.product.custom_fields && data.product.custom_fields !== '{}') {
                        const count = processFields(data.product.custom_fields);
                        if (count > 0) {
                            const modalElement = document.getElementById('copyFieldsModal');
                            const modal = bootstrap.Modal.getInstance(modalElement);
                            if (modal) modal.hide();
                            showNotification(count + ' custom fields copied successfully!', 'success');
                            return true;
                        }
                    }
                    return false;
                });
            };

            // Try current language, then fallback to English, then Khmer
            const currentLang = '<?php echo addslashes($currentLanguage); ?>';
            fetchAndCopy(currentLang)
                .then(success => {
                    if (!success && currentLang !== 'en') return fetchAndCopy('en');
                    return success;
                })
                .then(success => {
                    if (!success && currentLang !== 'km') return fetchAndCopy('km');
                    return success;
                })
                .then(success => {
                    if (!success) {
                        alert('This product has no custom fields to copy in any language.');
                    }
                })
                .catch(err => {
                    console.error('Error copying fields:', err);
                    alert('An error occurred while copying fields.');
                })
                .finally(() => {
                    confirmBtn.disabled = false;
                    confirmBtn.innerHTML = originalHtml;
                });
        });

                // Update the "Insert After" select options inside the given container
                function updatePositionOptions(containerId = 'customFieldsContainer') {
                    var container = document.getElementById(containerId);
                    if (!container) return;

                    // Gather current custom fields in order
                    var items = Array.from(container.querySelectorAll('.custom-field-item'));
                    var customFieldOptions = items.map(function(it) {
                        var id = it.dataset.fieldId;
                        var nameInput = it.querySelector('.field-name-en');
                        var name = (nameInput && nameInput.value.trim()) ? nameInput.value.trim() : 'Unnamed Field';
                        return { id: id, name: name };
                    });

                    // Build options HTML: standard positions first
                    var standardOptions = [
                        { value: 'end', label: 'End (default)' },
                        { value: 'detailed_description', label: 'Detailed Description' },
                        { value: 'ingredients', label: 'Ingredients' },
                        { value: 'origin', label: 'Origin' },
                        { value: 'brewing_instructions', label: 'Brewing Instructions' },
                        { value: 'tasting_notes', label: 'Tasting Notes' },
                        { value: 'weight', label: 'Weight' },
                        { value: 'roast_level', label: 'Roast Level' }
                    ];

                    // For each position select, rebuild options preserving selected value when possible
                    container.querySelectorAll('.field-position').forEach(function(select) {
                        var current = select.value;
                        var html = '';
                        standardOptions.forEach(function(opt) {
                            html += `<option value="${opt.value}" ${current === opt.value ? 'selected' : ''}>${opt.label}</option>`;
                        });
                        // Then add custom-field-specific options
                        customFieldOptions.forEach(function(cf) {
                            var val = 'field:' + cf.id;
                            html += `<option value="${val}" ${current === cf.id || current === val ? 'selected' : ''}>After: ${cf.name}</option>`;
                        });
                        select.innerHTML = html;
                    });
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
                var item = e.target.closest('.custom-field-item');
                if (item) item.remove();
            }
            // Add table row
            if (e.target.closest('.add-table-row')) {
                var btn = e.target.closest('.add-table-row');
                var container = btn.closest('.table-editor').querySelector('.table-rows-container');
                var idx = container.querySelectorAll('.table-row').length;
                var rowHtml = `
                    <div class="table-row mb-2" data-row-index="${idx}">
                        <div class="d-flex gap-2 align-items-start mb-2">
                            <input class="form-control form-control-sm row-label-en" placeholder="Label (EN)">
                            <input class="form-control form-control-sm row-label-km" placeholder="Label (KM)">
                            <div class="ms-auto d-flex gap-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary move-row-up" title="Move row up">↑</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary move-row-down" title="Move row down">↓</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary add-value-pair">Add Value</button>
                                <button type="button" class="btn btn-sm btn-outline-danger remove-table-row">Remove</button>
                            </div>
                        </div>
                        <div class="value-pairs">
                            <div class="value-pair d-flex gap-2 align-items-start mb-1 flex-nowrap">
                                <input class="form-control form-control-sm row-value-en" placeholder="Value (EN)">
                                <input class="form-control form-control-sm row-value-km" placeholder="Value (KM)">
                                <button type="button" class="btn btn-sm btn-outline-danger remove-value-pair">Remove</button>
                            </div>
                        </div>
                    </div>
                `;
                container.insertAdjacentHTML('beforeend', rowHtml);
            }

            // Remove table row
            if (e.target.closest('.remove-table-row')) {
                var row = e.target.closest('.table-row');
                if (row) row.remove();
            }
            // Move row up
            if (e.target.closest('.move-row-up')) {
                var row = e.target.closest('.table-row');
                if (row && row.previousElementSibling) {
                    row.parentNode.insertBefore(row, row.previousElementSibling);
                }
            }

            // Move row down
            if (e.target.closest('.move-row-down')) {
                var row = e.target.closest('.table-row');
                if (row && row.nextElementSibling) {
                    row.parentNode.insertBefore(row.nextElementSibling, row);
                }
            }

            // Add value pair inside a row
            if (e.target.closest('.add-value-pair')) {
                var btn = e.target.closest('.add-value-pair');
                var row = btn.closest('.table-row');
                var container = row.querySelector('.value-pairs');
                var pairHtml = `
                    <div class="value-pair d-flex gap-2 align-items-start mb-1">
                        <input class="form-control form-control-sm row-value-en" placeholder="Value (EN)">
                        <input class="form-control form-control-sm row-value-km" placeholder="Value (KM)">
                        <button type="button" class="btn btn-sm btn-outline-danger remove-value-pair">Remove</button>
                    </div>
                `;
                container.insertAdjacentHTML('beforeend', pairHtml);
            }

            // Remove value pair
            if (e.target.closest('.remove-value-pair')) {
                var vp = e.target.closest('.value-pair');
                if (vp) vp.remove();
            }
        });

        // Toggle Collection Visibility - Moved outside the other click listener to prevent issues
        $(document).on('click', '.toggle-collection-btn', function(e) {
            e.preventDefault(); // Prevent default button behavior
            var btn = $(this);
            var productId = btn.data('product-id');
            var icon = btn.find('i');

            // Disable button
            btn.prop('disabled', true);

            $.ajax({
                url: 'products.php',
                type: 'POST',
                data: {
                    ajax_toggle_collection_visibility: 1,
                    product_id: productId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        if (response.show_in_collection) {
                            icon.removeClass('bi-collection').addClass('bi-collection-fill');
                            btn.attr('title', 'Remove from collection list (Syrup/Powder)');
                        } else {
                            icon.removeClass('bi-collection-fill').addClass('bi-collection');
                            btn.attr('title', 'Show in collection list');
                        }
                        showNotification('Collection visibility updated!', 'success');
                    } else {
                        showNotification('Error: ' + response.message, 'error');
                    }
                },
                error: function() {
                    showNotification('An error occurred. Please try again.', 'error');
                },
                complete: function() {
                    btn.prop('disabled', false);
                }
            });
        });

        // Continue with other delegated listeners...
        document.addEventListener('click', function(e) {

            // Toggle table editor visibility when type changes
            if (e.target.closest('.field-type')) {
                var select = e.target.closest('.field-type');
                var item = select.closest('.custom-field-item');
                var editor = item.querySelector('.table-editor');
                if (editor) {
                    editor.style.display = select.value === 'table' ? 'block' : 'none';
                }
            }
        });
        
        // Modal Language Toggles
        document.getElementById('modal-en-btn').addEventListener('click', function() {
            this.classList.add('active');
            document.getElementById('modal-km-btn').classList.remove('active');
            document.getElementById('modal-lang-en').style.display = 'block';
            document.getElementById('modal-lang-km').style.display = 'none';
        });

        document.getElementById('modal-km-btn').addEventListener('click', function() {
            this.classList.add('active');
            document.getElementById('modal-en-btn').classList.remove('active');
            document.getElementById('modal-lang-km').style.display = 'block';
            document.getElementById('modal-lang-en').style.display = 'none';
        });

        // Edit Category Modal Language Toggles
        document.getElementById('edit-cat-en-btn').addEventListener('click', function() {
            this.classList.add('active');
            document.getElementById('edit-cat-km-btn').classList.remove('active');
            document.getElementById('edit-cat-lang-en').style.display = 'block';
            document.getElementById('edit-cat-lang-km').style.display = 'none';
        });

        document.getElementById('edit-cat-km-btn').addEventListener('click', function() {
            this.classList.add('active');
            document.getElementById('edit-cat-en-btn').classList.remove('active');
            document.getElementById('edit-cat-lang-km').style.display = 'block';
            document.getElementById('edit-cat-lang-en').style.display = 'none';
        });

        // Handle detailed product form submission
        document.getElementById('detailedProductForm').addEventListener('submit', function(e) {
            e.preventDefault();
            console.log('Form submission triggered');

            // Collect custom fields data
            var customFields = collectCustomFields();
            console.log('Collected custom fields:', customFields);
            document.getElementById('custom_fields_data').value = JSON.stringify(customFields);
            console.log('Hidden input value set to:', document.getElementById('custom_fields_data').value);
            // Continue with form submission for testing
            var formData = new FormData(this);
            console.log('FormData contents:');
            for (var pair of formData.entries()) {
                console.log(pair[0] + ':', pair[1]);
            }

            formData.append('update_product', '1');

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(function(response) {
                console.log('Response status:', response.status);
                return response.text();
            })
            .then(function(data) {
                console.log('Response data:', data);
                // Close the modal
                var detailedModal = bootstrap.Modal.getInstance(document.getElementById('detailedProductModal'));
                detailedModal.hide();
                
                // Reload the page to show updated data
                location.reload();
            })
            .catch(function(error) {
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
            .then(function(response) { return response.json(); })
            .then(function(data) {
                console.log('English category data received:', data);
                if (data.success) {
                    var category = data.category;
                    document.getElementById('edit_category_name_en').value = category.name || '';
                    document.getElementById('edit_category_description_en').value = category.description || '';
                }
            })
            .catch(function(error) {
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
            .then(function(response) { return response.json(); })
            .then(function(data) {
                console.log('Khmer category data received:', data);
                if (data.success) {
                    var category = data.category;
                    document.getElementById('edit_category_name_km').value = category.name || '';
                    document.getElementById('edit_category_description_km').value = category.description || '';
                }
            })
            .catch(function(error) {
                console.error('Error fetching Khmer category data:', error);
            });

            document.getElementById('edit_category_id').value = categoryData.id;

            var imagePreview = document.getElementById('current_category_image_preview');
            if (categoryData.image) { imagePreview.src = categoryData.image; imagePreview.style.display = 'block'; }
            else { imagePreview.style.display = 'none'; }

            document.getElementById('edit_category_image').value = '';
            document.getElementById('edit_category_image_url').value = '';

            var editModal = new bootstrap.Modal(document.getElementById('editCategoryModal'));
            editModal.show();
        }

        document.getElementById('editCategoryModal').addEventListener('hidden.bs.modal', function () { var form = this.querySelector('form'); form.reset(); });
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

        .custom-field-item {
            transition: all 0.2s ease;
        }
        .drag-handle {
            user-select: none;
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var sortableProducts = document.getElementById('sortableProducts');

            if (sortableProducts) {
                var sortable = Sortable.create(sortableProducts, {
                    handle: '.sortable-handle',
                    animation: 150,
                    ghostClass: 'sortable-ghost',
                    chosenClass: 'sortable-chosen',
                    dragClass: 'sortable-drag',
                    onEnd: function(evt) {
                        // Get new order with correct sort_order values
                        var rows = sortableProducts.querySelectorAll('tr[data-base-product-id]');
                        var order = Array.from(rows).map(function(row, index) {
                            return {
                                base_product_id: parseInt(row.getAttribute('data-base-product-id')),
                                sort_order: <?php echo $offset; ?> + index + 1
                            };
                        });

                        // Send to server
                        fetch(window.location.href, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: 'update_order=1&order=' + encodeURIComponent(JSON.stringify(order))
                        })
                        .then(function(response) { return response.json(); })
                        .then(function(data) {
                            if (data.success) {
                                // Show success message
                                showNotification('Product order updated successfully!', 'success');
                            } else {
                                showNotification('Error updating order: ' + (data.error || 'Unknown error'), 'error');
                                // Reload page to restore original order
                                location.reload();
                            }
                        })
                        .catch(function(error) {
                            showNotification('Error updating order: ' + error.message, 'error');
                            location.reload();
                        });
                    }
                });
            }

            // Initialize sortable for custom fields
            ['customFieldsContainer', 'customFieldsContainerEdit', 'customFieldsContainerAdd'].forEach(function(id) {
                var container = document.getElementById(id);
                if (container) {
                    Sortable.create(container, {
                        handle: '.drag-handle',
                        animation: 150,
                        onEnd: function(evt) {
                            // Update position options after reordering
                            updatePositionOptions(id);
                        }
                    });
                }
            });

            // Handle all button clicks with event delegation
            document.addEventListener('click', function(e) {
                // Handle delete product button clicks
                if (e.target.closest('.delete-product-btn')) {
                    e.preventDefault();
                    var button = e.target.closest('.delete-product-btn');
                    var productId = button.getAttribute('data-product-id');

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
                        .then(function(response) { return response.json(); })
                        .then(function(data) {
                            if (data.success) {
                                // Remove the row from the table
                                var row = button.closest('tr');
                                row.remove();
                                showNotification('Product deleted successfully!', 'success');
                            } else {
                                showNotification('Error deleting product: ' + (data.message || 'Unknown error'), 'error');
                            }
                        })
                        .catch(function(error) {
                            console.error('Error deleting product:', error);
                            showNotification('Error deleting product. Please try again.', 'error');
                        })
                        .finally(function() {
                            // Reset button state
                            button.disabled = false;
                            button.innerHTML = '<i class="bi bi-trash"></i>';
                        });
                    }
                }

                // Handle delete category button clicks
                if (e.target.closest('.delete-category-btn')) {
                    e.preventDefault();
                    var button = e.target.closest('.delete-category-btn');
                    var categoryId = button.getAttribute('data-category-id');

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
                        .then(function(response) { return response.json(); })
                        .then(function(data) {
                            if (data.success) {
                                // Remove the row from the table
                                var row = button.closest('tr');
                                row.remove();
                                showNotification('Category deleted successfully!', 'success');
                                // Refresh the page to update product counts
                                setTimeout(function() {
                                    window.location.reload();
                                }, 1000);
                            } else {
                                showNotification('Error deleting category: ' + (data.message || 'Unknown error'), 'error');
                            }
                        })
                        .catch(function(error) {
                            console.error('Error deleting category:', error);
                            showNotification('Error deleting category. Please try again.', 'error');
                        })
                        .finally(function() {
                            // Reset button state
                            button.disabled = false;
                            button.innerHTML = '<i class="bi bi-trash"></i>';
                        });
                    }
                }

                // Handle toggle featured button clicks
                if (e.target.closest('.toggle-featured-btn')) {
                    e.preventDefault();
                    var button = e.target.closest('.toggle-featured-btn');
                    var productId = button.getAttribute('data-product-id');

                    // Show loading state
                    button.disabled = true;
                    var originalIcon = button.innerHTML;
                    button.innerHTML = '<i class="bi bi-spinner bi-spin"></i>';

                    fetch(window.location.href, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'ajax_toggle_featured=1&product_id=' + productId
                    })
                    .then(function(response) { return response.json(); })
                    .then(function(data) {
                        if (data.success) {
                            // Update the button icon and title
                            var isFeatured = data.featured == 1;
                            button.innerHTML = `<i class="bi ${isFeatured ? 'bi-star-fill' : 'bi-star'}"></i>`;
                            button.setAttribute('title', isFeatured ? 'Remove from featured' : 'Make featured');

                            // Update the badge in the table
                            var row = button.closest('tr');
                            var badgeCell = row.cells[7]; // Featured column
                            if (badgeCell) {
                                var badge = badgeCell.querySelector('.badge');
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
                    .catch(function(error) {
                        console.error('Error toggling featured:', error);
                        showNotification('Error updating featured status. Please try again.', 'error');
                    })
                    .finally(function() {
                        // Reset button state
                        button.disabled = false;
                    });
                }
                
                // Handle toggle enabled button clicks
                if (e.target.closest('.toggle-enabled-btn')) {
                    e.preventDefault();
                    var button = e.target.closest('.toggle-enabled-btn');
                    var productId = button.getAttribute('data-product-id');

                    // Show loading state
                    button.disabled = true;
                    var originalIcon = button.innerHTML;
                    button.innerHTML = '<i class="bi bi-spinner bi-spin"></i>';

                    fetch(window.location.href, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'ajax_toggle_enabled=1&product_id=' + productId
                    })
                    .then(function(response) { return response.json(); })
                    .then(function(data) {
                        if (data.success) {
                            var isEnabled = data.enabled == 1;
                            button.innerHTML = `<i class="bi ${isEnabled ? 'bi-eye-fill' : 'bi-eye'}"></i>`;
                            button.setAttribute('title', isEnabled ? 'Disable product' : 'Enable product');

                            // Update the badge in the table
                            var row = button.closest('tr');
                            var badgeCell = row.cells[9]; // Enabled column
                            if (badgeCell) {
                                var badge = badgeCell.querySelector('.badge');
                                if (badge) {
                                    badge.className = `badge ${isEnabled ? 'bg-primary text-white' : 'bg-light text-muted'}`;
                                    badge.innerHTML = `<i class="bi ${isEnabled ? 'bi-eye-fill' : 'bi-eye'} me-1"></i>${isEnabled ? 'Yes' : 'No'}`;
                                }
                            }

                            showNotification('Product visibility updated!', 'success');
                        } else {
                            showNotification('Error updating product visibility: ' + (data.message || 'Unknown error'), 'error');
                        }
                    })
                    .catch(function(error) {
                        console.error('Error toggling enabled:', error);
                        showNotification('Error updating product visibility. Please try again.', 'error');
                    })
                    .finally(function() {
                        // Reset button state
                        button.disabled = false;
                    });
                }

                // Handle toggle best seller button clicks
                if (e.target.closest('.toggle-best-seller-btn')) {
                    e.preventDefault();
                    var button = e.target.closest('.toggle-best-seller-btn');
                    var productId = button.getAttribute('data-product-id');

                    // Show loading state
                    button.disabled = true;
                    var originalIcon = button.innerHTML;
                    button.innerHTML = '<i class="bi bi-spinner bi-spin"></i>';

                    fetch(window.location.href, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'ajax_toggle_best_seller=1&product_id=' + productId
                    })
                    .then(function(response) { return response.json(); })
                    .then(function(data) {
                        if (data.success) {
                            // Update the button icon and title
                            var isBestSeller = data.best_seller == 1;
                            button.innerHTML = `<i class="bi ${isBestSeller ? 'bi-trophy-fill' : 'bi-trophy'}"></i>`;
                            button.setAttribute('title', isBestSeller ? 'Remove from best sellers' : 'Make best seller');

                            // Update the badge in the table
                            var row = button.closest('tr');
                            var badgeCell = row.cells[8]; // Best seller column
                            if (badgeCell) {
                                var badge = badgeCell.querySelector('.badge');
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
                    .catch(function(error) {
                        console.error('Error toggling best seller:', error);
                        showNotification('Error updating best seller status. Please try again.', 'error');
                    })
                    .finally(function() {
                        // Reset button state
                        button.disabled = false;
                    });
                }
            });
        });

        // Related Products functionality
        function updateCustomImageFile(formType, index, file) {
            var handler = formType === 'add' ? addRelatedProductsHandler : editRelatedProductsHandler;
            if (handler && handler.updateCustomImageFile) {
                handler.updateCustomImageFile(index, file);
            }
        }

        function updateCustomUrl(formType, index, value) {
            var handler = formType === 'add' ? addRelatedProductsHandler : editRelatedProductsHandler;
            if (handler && handler.updateCustomUrl) {
                handler.updateCustomUrl(index, value);
            }
        }

        function updateCustomName(formType, index, value) {
            var handler = formType === 'add' ? addRelatedProductsHandler : editRelatedProductsHandler;
            if (handler && handler.updateCustomName) {
                handler.updateCustomName(index, value);
            }
        }

        function updateCustomImageUrl(formType, index, value) {
            var handler = formType === 'add' ? addRelatedProductsHandler : editRelatedProductsHandler;
            if (handler && handler.updateCustomImageUrl) {
                handler.updateCustomImageUrl(index, value);
            }
        }

        window.removeSelectedProductGlobal = function(baseId) {
            var handler = addRelatedProductsHandler || editRelatedProductsHandler;
            if (handler && handler.removeSelectedProduct) {
                handler.removeSelectedProduct(baseId);
            }
        };

        function updateCustomImageUrl(formType, index, value) {
            var handler = formType === 'add' ? addRelatedProductsHandler : editRelatedProductsHandler;
            if (handler && handler.updateCustomImageUrl) {
                handler.updateCustomImageUrl(index, value);
            }
        }

        function initializeRelatedProducts(formType) {
            var searchInput = document.getElementById(`related_products_search_${formType}`);
            var productsList = document.getElementById(`related_products_list_${formType}`);
            var selectedContainer = document.getElementById(`selected_related_products_${formType}`);
            var dataInput = document.getElementById(`related_products_data_${formType}`);
            var selectedProducts = [];
            var allProducts = [];

            // Load all products
            function loadProducts() {
                fetch('../public/api.php?action=get_all_products')
                    .then(function(response) { return response.json(); })
                    .then(function(data) {
                        if (data.success) {
                            allProducts = data.products;
                            renderProductsList('');
                        }
                    })
                    .catch(function(error) { console.error('Error loading products:', error); });
            }

            // Render products list
            function renderProductsList(searchTerm) {
                var filtered = allProducts.filter(function(product) {
                    return product.name.toLowerCase().includes(searchTerm.toLowerCase()) &&
                        !selectedProducts.find(function(p) { return p.base_id == product.base_product_id; });
                });

                productsList.innerHTML = filtered.map(function(product) {
                    return '<div class="d-flex align-items-center justify-content-between p-2 border-bottom">' +
                        '<div class="d-flex align-items-center">' +
                            '<img src="' + (product.image || '/kouprey/public/assets/images/placeholder.png') + '" ' +
                                 'alt="' + (product.name || '') + '" class="rounded me-2" style="width: 40px; height: 40px; object-fit: contain; background-color: #f8f9fa;">' +
                            '<div>' +
                                '<div class="fw-bold">' + (product.name || '') + '</div>' +
                                '<small class="text-muted">$' + (product.price || '') + '</small>' +
                            '</div>' +
                        '</div>' +
                        '<button type="button" class="btn btn-sm btn-outline-primary add-related-btn" ' +
                                'data-base-id="' + (product.base_product_id || '') + '" data-name="' + (product.name || '').replace(/"/g, '&quot;') + '" data-image="' + (product.image || '') + '">' +
                            '<i class="bi bi-plus"></i> Add' +
                        '</button>' +
                    '</div>';
                }).join('');

                // Add event listeners to add buttons
                productsList.querySelectorAll('.add-related-btn').forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        var baseId = this.getAttribute('data-base-id');
                        var name = this.getAttribute('data-name');
                        var image = this.getAttribute('data-image');
                        addSelectedProduct(baseId, name, image);
                    });
                });
            }

            // Add selected product
            function addSelectedProduct(baseId, name, image) {
                if (!selectedProducts.find(function(p) { return p.base_id == baseId; })) {
                    selectedProducts.push({base_id: baseId, name: name, image: image, custom_image: '', custom_url: '', custom_image_file: null, custom_image_url: ''});
                    updateSelectedDisplay();
                    renderProductsList(searchInput.value);
                    updateDataInput();
                }
            }

            // Remove selected product
            function removeSelectedProduct(baseId) {
                selectedProducts = selectedProducts.filter(function(p) { return p.base_id != baseId; });
                updateSelectedDisplay();
                renderProductsList(searchInput.value);
                updateDataInput();
            }

            // Update selected products display
            function updateSelectedDisplay() {
                    selectedContainer.innerHTML = selectedProducts.map(function(product, index) {
                        return `
                        <div class="d-inline-block m-1 text-center" style="width: 80px;">
                            <img src="${product.custom_image_url || product.custom_image || product.image || '/kouprey/public/assets/images/placeholder.png'}" alt="${product.name}" class="rounded mb-1" style="width: 60px; height: 60px; object-fit: contain; background-color: #f8f9fa; cursor: pointer;" title="${product.name}">
                            <input type="file" class="form-control form-control-sm mb-1" name="custom_image_${formType}_${index}" accept="image/*" onchange="updateCustomImageFile('${formType}', ${index}, this.files[0])" style="font-size: 10px;">
                            ${product.base_id.startsWith('custom_') ? `<input type="text" class="form-control form-control-sm mb-1" placeholder="Image URL" value="${product.custom_image_url}" onchange="updateCustomImageUrl('${formType}', ${index}, this.value)" style="font-size: 10px;">` : ''}
                            <input type="text" class="form-control form-control-sm mb-1" placeholder="URL" value="${product.custom_url}" onchange="updateCustomUrl('${formType}', ${index}, this.value)" style="font-size: 10px;">
                            ${product.base_id.startsWith('custom_') ? `<input type="text" class="form-control form-control-sm mb-1" placeholder="Name" value="${product.name}" onchange="updateCustomName('${formType}', ${index}, this.value)" style="font-size: 10px;">` : ''}
                            <button type="button" class="btn btn-sm btn-danger" onclick="removeSelectedProductGlobal('${product.base_id}')" style="font-size: 10px; padding: 2px 5px;"><i class="bi bi-x"></i></button>
                        </div>
                    `;
                    }).join('');
                }

            // Update hidden input
            function updateDataInput() {
                var data = selectedProducts.map(function(p) {
                    return {
                        base_id: p.base_id,
                        name: p.name,
                        image: p.image,
                        custom_image: p.custom_image,
                        custom_url: p.custom_url,
                        custom_image_url: p.custom_image_url
                    };
                });
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
                        var reader = new FileReader();
                        reader.onload = function(e) {
                            var img = selectedContainer.children[index].querySelector('img');
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
                    var customId = 'custom_' + Date.now();
                    selectedProducts.push({base_id: customId, name: 'Custom Product', image: '', custom_image: '', custom_url: '', custom_image_file: null, custom_image_url: ''});
                    updateSelectedDisplay();
                    updateDataInput();
                },
                removeSelectedProduct: removeSelectedProduct
            };
        }

        // Initialize related products for add and edit forms
        var addRelatedProductsHandler, editRelatedProductsHandler;

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
                var button = e.target.closest('.edit-product-btn');
                var baseProductId = button.getAttribute('data-base-product-id');
                
                // Load product data
                fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `get_product_data=1&base_product_id=${baseProductId}&language=en`
                })
                    .then(function(response) { return response.json(); })
                    .then(function(data) {
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
                    .catch(function(error) { console.error('Error loading product data:', error); });
                
                document.getElementById('editProductSidebar').classList.add('active');
            }
        });

        function loadRelatedProducts(baseProductId) {
            fetch(`../public/api.php?action=get_related_products&base_product_id=${baseProductId}`)
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    if (data.success && editRelatedProductsHandler) {
                        var selected = data.related_products.map(function(p) {
                            var base_id = p.base_product_id || ('custom_' + Date.now() + '_' + Math.random());
                            return {base_id: base_id, name: p.name, image: p.image, custom_image: p.custom_image || '', custom_url: p.custom_url || '', custom_image_file: null, custom_image_url: p.custom_image_url || ''};
                        });
                        editRelatedProductsHandler.setSelectedProducts(selected);
                    }
                })
                .catch(function(error) { console.error('Error loading related products:', error); });
        }

        function populateEditForm(product, baseProductId) {
            document.getElementById('base_product_id').value = baseProductId;
            // Populate other fields...
            // (existing code for populating form fields)
        }

        // Image Preview Logic
        function setupImagePreview(inputId, previewId) {
            const input = document.getElementById(inputId);
            const preview = document.getElementById(previewId);
            
            if (input && preview) {
                input.addEventListener('change', function() {
                    if (this.files && this.files[0]) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            preview.src = e.target.result;
                            preview.style.display = 'block';
                        };
                        reader.readAsDataURL(this.files[0]);
                    }
                });
            }
        }

        setupImagePreview('sidebar_image', 'sidebar_image_preview'); // You might need to add this ID to the HTML
        setupImagePreview('edit_image', 'current_image_preview');
        setupImagePreview('sidebar_category_image', 'sidebar_category_image_preview');
        setupImagePreview('edit_category_image', 'current_category_image_preview');

    </script>

<?php
$pageContent = ob_get_clean();
$pageTitle = 'Manage Products';
$aFieldsctiveNav = 'management';
include 'layout.php';
?>
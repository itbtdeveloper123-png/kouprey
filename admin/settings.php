<?php
session_start();
require_once '../app/Config/database.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

// Get current language from GET or default to 'en'
$currentLanguage = isset($_GET['lang']) ? $_GET['lang'] : 'en';
$supportedLanguages = ['en' => 'English', 'km' => 'Khmer'];
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'general';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Handle file uploads first
    if (isset($_FILES['hero_background_image']) && $_FILES['hero_background_image']['error'] == 0) {
        $uploadDir = '../public/uploads/';
        $fileName = 'hero-bg-' . time() . '.' . pathinfo($_FILES['hero_background_image']['name'], PATHINFO_EXTENSION);
        $uploadPath = $uploadDir . $fileName;

        // Validate file type
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (in_array($_FILES['hero_background_image']['type'], $allowedTypes)) {
            if (move_uploaded_file($_FILES['hero_background_image']['tmp_name'], $uploadPath)) {
                // Compress the uploaded image
                require_once '../app/Config/image_utils.php';
                $settings = getCompressionSettings('hero');
                compressImage($uploadPath, $uploadPath, $settings['quality'], $settings['maxWidth'], $settings['maxHeight']);
                
                $_POST['hero_background_image'] = '/kouprey/public/uploads/' . $fileName;
            }
        }
    }

    // Handle company logo upload
    if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] == 0) {
        $uploadDir = '../public/uploads/';
        $fileName = 'company-logo-' . time() . '.' . pathinfo($_FILES['company_logo']['name'], PATHINFO_EXTENSION);
        $uploadPath = $uploadDir . $fileName;

        // Validate file type
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (in_array($_FILES['company_logo']['type'], $allowedTypes)) {
            if (move_uploaded_file($_FILES['company_logo']['tmp_name'], $uploadPath)) {
                // Compress the uploaded image
                require_once '../app/Config/image_utils.php';
                $settings = getCompressionSettings('logo');
                compressImage($uploadPath, $uploadPath, $settings['quality'], $settings['maxWidth'], $settings['maxHeight']);
                
                $_POST['company_logo'] = '/kouprey/public/uploads/' . $fileName;
            }
        }
    }







    // Handle hero image deletion
    if (isset($_POST['delete_hero_image'])) {
        // Get current hero background image
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'hero_background_image' AND language = ?");
        $stmt->execute([$currentLanguage]);
        $currentImage = $stmt->fetchColumn();
        
        if ($currentImage) {
            // Delete the file from server
            $filePath = '../public/uploads/' . basename($currentImage);
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
        
        $_POST['hero_background_image'] = '';
    }

    // Handle company logo deletion
    if (isset($_POST['delete_company_logo'])) {
        // Get current company logo
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'company_logo' AND language = ?");
        $stmt->execute([$currentLanguage]);
        $currentLogo = $stmt->fetchColumn();
        
        if ($currentLogo) {
            // Delete the file from server
            $filePath = '../public/uploads/' . basename($currentLogo);
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
        
        $_POST['company_logo'] = '';
    }

    // Handle individual hero file deletion
    if (isset($_POST['delete_hero_file'])) {
        $fileToDelete = $_POST['delete_hero_file'];
        $filePath = '../public/uploads/' . $fileToDelete;
        
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        
        // Check if this was the current setting and clear it
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'hero_background_image' AND language = ?");
        $stmt->execute([$currentLanguage]);
        $currentSetting = $stmt->fetchColumn();
        
        if ($currentSetting === '/kouprey/public/uploads/' . $fileToDelete) {
            $_POST['hero_background_image'] = '';
        }
    }

    // Handle File Manager: Bulk Delete
    if (isset($_POST['delete_file_manager_bulk'])) {
        $filesToDelete = json_decode($_POST['delete_file_manager_bulk'], true);
        if (is_array($filesToDelete)) {
            $targetDir = '../public/assets/images/products/';
            $deletedCount = 0;
            foreach ($filesToDelete as $file) {
                $filePath = $targetDir . basename($file);
                if (file_exists($filePath)) {
                    unlink($filePath);
                    $deletedCount++;
                }
            }
            if ($deletedCount > 0) $message = "$deletedCount files deleted successfully!";
        }
    }

    // Handle File Manager: Delete File
    if (isset($_POST['delete_file_manager'])) {
        $fileToDelete = basename($_POST['delete_file_manager']);
        $targetDir = '../public/assets/images/products/';
        $filePath = $targetDir . $fileToDelete;
        
        if (file_exists($filePath)) {
            unlink($filePath);
            $message = "File deleted successfully!";
        }
    }

    // Handle File Manager: Upload File
    if (isset($_POST['upload_file_manager']) && isset($_FILES['file_manager_upload'])) {
        $uploadDir = '../public/assets/images/products/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        
        $files = $_FILES['file_manager_upload'];
        $fileNames = is_array($files['name']) ? $files['name'] : [$files['name']];
        $fileTmps = is_array($files['tmp_name']) ? $files['tmp_name'] : [$files['tmp_name']];
        $fileErrors = is_array($files['error']) ? $files['error'] : [$files['error']];
        
        $successCount = 0;
        foreach ($fileNames as $i => $name) {
             if ($fileErrors[$i] == 0) {
                  $ext = pathinfo($name, PATHINFO_EXTENSION);
                  // Use original name to allow direct replacement/overwriting
                  $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '', basename($name));
                  if (empty($safeName) || $safeName === '.' || $safeName === '..') $safeName = uniqid() . '.' . $ext;
                  
                  $targetFile = $uploadDir . $safeName;
                  
                  if (move_uploaded_file($fileTmps[$i], $targetFile)) {
                      require_once '../app/Config/image_utils.php';
                      $settings = getCompressionSettings('product');
                      compressImage($targetFile, $targetFile, $settings['quality'], $settings['maxWidth'], $settings['maxHeight']);
                      $successCount++;
                  }
             }
        }
        if ($successCount > 0) $message = "$successCount files uploaded/replaced successfully!";
    }

    // Handle File Manager: Convert All to WebP
    if (isset($_POST['convert_webp_all'])) {
        $targetDir = '../public/assets/images/products/';
        $images = glob($targetDir . '*.{jpg,jpeg,png,gif,JPG,JPEG,PNG,GIF}', GLOB_BRACE);
        
        // Remove existing WebP from list to avoid re-converting
        // Actually glob above doesn't include webp, so we are good.
        
        $convertedCount = 0;
        $dbUpdates = 0;
        
        if ($images) {
            require_once '../app/Config/image_utils.php';
            $compressionSettings = getCompressionSettings('product');
            
            foreach ($images as $imagePath) {
                // Double check it's a file
                if (!is_file($imagePath)) continue;
                
                $pathInfo = pathinfo($imagePath);
                $newFileName = $pathInfo['filename'] . '.webp';
                $newFilePath = $targetDir . $newFileName;
                
                // Convert to WebP
                // We pass the new file path ending in .webp, compressImage will detect this and save as WebP
                if (compressImage($imagePath, $newFilePath, $compressionSettings['quality'], $compressionSettings['maxWidth'], $compressionSettings['maxHeight'])) {
                    $convertedCount++;
                    
                    // Update Database References
                    // We assume the DB stores the relative path or full path.
                    // We will search for both just in case, or rely on what we see.
                    // Based on product_detail.php, it likely uses paths like '/kouprey/public/assets/images/products/filename.jpg' or just 'filename.jpg' if prepended elsewhere.
                    // The safer bet is to replace the exact filename string if it occurs in the image column.
                    
                    $oldBasename = $pathInfo['basename']; // e.g. image.jpg
                    
                    // Update exact matches and path matches
                    // We only want to update the 'image' column in 'products' table, and potentially 'features' if images are there?
                    // Let's stick to 'products' table 'image' column for now.
                    
                    // Check if product uses this image
                    // We use REPLACE() to safe-swap the extension
                    // Case 1: Full path match
                    $sql = "UPDATE products SET image = REPLACE(image, ?, ?) WHERE image LIKE ?";
                    // Match /kouprey/public/assets/images/products/image.jpg
                    $searchPath = '/kouprey/public/assets/images/products/' . $oldBasename;
                    $replacePath = '/kouprey/public/assets/images/products/' . $newFileName;
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$oldBasename, $newFileName, "%$oldBasename"]); // This is risky if filename is 'image.jpg' and we have 'myimage.jpg'. 
                    
                    // Better approach:
                    // Update where image ends with the filename
                    // But we might have full URL.
                    // Let's try to update standard paths we know of.
                    
                    // Pattern 1: '/kouprey/public/assets/images/products/old.jpg'
                    $p1_old = '/kouprey/public/assets/images/products/' . $oldBasename;
                    $p1_new = '/kouprey/public/assets/images/products/' . $newFileName;
                    // Pattern 2: 'old.jpg' (if stored as filename)
                    // We'll just do a precise update for Pattern 1 as that's what we saw in the code.
                    
                    $stmt = $pdo->prepare("UPDATE products SET image = ? WHERE image = ?");
                    $stmt->execute([$p1_new, $p1_old]);
                    $dbUpdates += $stmt->rowCount();
                    
                    // Also generic replace if we are confident (maybe backup first?)
                    // Let's stick to safe path updates.
                    
                    // Support for 'image' being just filename? 
                    // Let's check if any product has just the filename
                    $stmt = $pdo->prepare("UPDATE products SET image = ? WHERE image = ?");
                    $stmt->execute([$newFileName, $oldBasename]);
                    $dbUpdates += $stmt->rowCount();
                    
                    // Optional: Delete original file
                    // unlink($imagePath); 
                }
            }
        }
        $message = "Success! Converted $convertedCount images to WebP. Updated $dbUpdates database records.";
    }

    foreach ($_FILES as $key => $file) {
        if (strpos($key, 'file_manager_replace_') === 0 && $file['error'] == 0) {
            $originalNameWithUnderscores = substr($key, 21); // Remove prefix
            // We need to match this back to a real file on disk.
            // Since dots are replaced by underscores in $_FILES keys, we have to look for the file.
            // A simple heuristic is to look for the file in the directory.
            
            $targetDir = '../public/assets/images/products/';
            $foundFile = null;
            
            // Try to find the matching file. This is tricky because extension dot turned to underscore.
            // We iterate files to find the match.
            $files = scandir($targetDir);
            foreach ($files as $f) {
                if ($f === '.' || $f === '..') continue;
                if (str_replace('.', '_', $f) === $originalNameWithUnderscores) {
                    $foundFile = $f;
                    break;
                }
            }
            
            if ($foundFile) {
                $targetFile = $targetDir . $foundFile;
                // Move and overwrite
                if (move_uploaded_file($file['tmp_name'], $targetFile)) {
                    require_once '../app/Config/image_utils.php';
                    $settings = getCompressionSettings('product');
                    compressImage($targetFile, $targetFile, $settings['quality'], $settings['maxWidth'], $settings['maxHeight']);
                    $message = "File '$foundFile' replaced successfully!";
                }
            }
        }
    }

    if (isset($_POST['active_tab'])) {
        $activeTab = $_POST['active_tab'];
    }

    foreach ($_POST as $key => $value) {
        if ($key !== 'update_settings' && $key !== 'language' && $key !== 'delete_hero_image' && $key !== 'delete_hero_file' && $key !== 'delete_company_logo' && $key !== 'active_tab' && $key !== 'delete_file_manager' && $key !== 'upload_file_manager' && $key !== 'convert_webp_all' && $key !== 'delete_file_manager_bulk' && strpos($key, 'file_manager_replace_') !== 0) {
            // If value is an array (dynamic fields), JSON encode it
            $saveValue = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value;
            
            // Update or insert setting for current language
            $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value, language) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $stmt->execute([$key, $saveValue, $currentLanguage]);
        }
    }
    $message = "Settings updated successfully!";
}

// If not English, ensure all settings from English exist for the current language
if ($currentLanguage !== 'en') {
    $stmt = $pdo->prepare("INSERT IGNORE INTO settings (setting_key, setting_value, setting_type, category, language, description)
                           SELECT setting_key, setting_value, setting_type, category, ?, description FROM settings WHERE language = 'en'");
    $stmt->execute([$currentLanguage]);
}

// Fetch settings for current language
$stmt = $pdo->prepare("SELECT * FROM settings WHERE language = ? ORDER BY category, setting_key");
$stmt->execute([$currentLanguage]);
$settings = $stmt->fetchAll();

// Fetch all categories for collections
$stmt = $pdo->prepare("SELECT id, name, base_category_id FROM categories WHERE language = ?");
$stmt->execute([$currentLanguage]);
$allCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Identify Syrup and Powder categories
$syrupBaseId = null;
$powderBaseId = null;
$stmt = $pdo->prepare("SELECT base_category_id, name FROM categories WHERE language = 'en' AND (name LIKE '%Syrup%' OR name LIKE '%Powder%')");
$stmt->execute();
$enCats = $stmt->fetchAll();
foreach ($enCats as $ec) {
    if (stripos($ec['name'], 'Syrup') !== false) $syrupBaseId = $ec['base_category_id'];
    if (stripos($ec['name'], 'Powder') !== false) $powderBaseId = $ec['base_category_id'];
}

// Fetch all products
$stmt = $pdo->prepare("SELECT id, name, base_product_id, category_id FROM products WHERE language = ?");
$stmt->execute([$currentLanguage]);
$allProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group settings by category manually
$groupedSettings = [];
foreach ($settings as $setting) {
    $groupedSettings[$setting['category']][] = $setting;
}

ob_start();
?>
    <style>
    /* Critical: Force settings tabs horizontal */
    ul#settingsTabs{display:flex!important;flex-direction:row!important;flex-wrap:nowrap!important;list-style:none!important;padding:0!important;margin:0!important;overflow-x:auto!important;gap:4px}
    ul#settingsTabs>li{list-style:none!important;flex-shrink:0}
    ul#settingsTabs>li>button.nav-link{display:inline-block!important;white-space:nowrap!important;padding:8px 18px!important;border-radius:50px!important;font-weight:600!important;font-size:14px!important;background:#fff;color:#64748b;border:1px solid #e2e8f0;cursor:pointer;transition:all .2s}
    ul#settingsTabs>li>button.nav-link:hover{background:#f1f5f9;color:#4f46e5}
    ul#settingsTabs>li>button.nav-link.active{background:#4f46e5!important;color:#fff!important;border-color:#4f46e5!important}
    /* Hide inactive tab panes */
    #settingsTabContent>.tab-pane{display:none!important}
    #settingsTabContent>.tab-pane.show.active{display:block!important}
    #settingsTabContent>.tab-pane.show.active.fade{display:block!important}
    </style>
    <div class="container-fluid py-4">
        <?php if (isset($message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i><?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Language Selector -->
        <div class="row mb-5 align-items-center">
            <div class="col-md-6">
                <h1 class="h2 fw-800 text-dark mb-1">System Settings</h1>
                <p class="text-secondary">Configure your website behavior, content, and integrations.</p>
            </div>
            <div class="col-md-6 text-md-end">
                <div class="d-inline-flex align-items-center bg-white p-2 rounded-pill shadow-sm border">
                    <label for="languageSelect" class="ms-3 me-2 small fw-bold text-muted">LANGUAGE</label>
                    <select id="languageSelect" class="form-select border-0 bg-transparent fw-bold" style="width: auto;" onchange="changeLanguage(this.value)">
                        <?php foreach ($supportedLanguages as $code => $name): ?>
                            <option value="<?php echo $code; ?>" <?php echo $code === $currentLanguage ? 'selected' : ''; ?>><?php echo $name; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <?php
        $categories = [
            'general' => ['icon' => 'bi-globe', 'title' => 'General Settings', 'description' => 'Basic website information and branding'],
            'contact' => ['icon' => 'bi-telephone', 'title' => 'Contact Information', 'description' => 'Company contact details'],
            'hero' => ['icon' => 'bi-image', 'title' => 'Hero Section', 'description' => 'Main banner and call-to-action content'],

            'about' => ['icon' => 'bi-info-circle', 'title' => 'About Page', 'description' => 'About page content and sections'],
            'newsletter' => ['icon' => 'bi-envelope', 'title' => 'Newsletter', 'description' => 'Newsletter subscription settings'],
            'footer' => ['icon' => 'bi-file-text', 'title' => 'Footer', 'description' => 'Footer content and copyright'],
            'social' => ['icon' => 'bi-share', 'title' => 'Social Media', 'description' => 'Social media links and profiles'],
            'features' => ['icon' => 'bi-toggle-on', 'title' => 'Features & Content', 'description' => 'Website features and page content'],
            'product' => ['icon' => 'bi-box-seam', 'title' => 'Product Information', 'description' => 'Product details and specifications'],
            'collections' => ['icon' => 'bi-collection', 'title' => 'Product Collections', 'description' => 'Manage Syrup & Powder collection texts'],
            'reviews' => ['icon' => 'bi-star', 'title' => 'Reviews Section', 'description' => 'Customer reviews page content'],
            'policies' => ['icon' => 'bi-file-earmark-text', 'title' => 'Policies & Legal', 'description' => 'Privacy Policy, Terms of Service, and Contact Us content'],
            'pagination' => ['icon' => 'bi-list', 'title' => 'Pagination', 'description' => 'Content display settings'],
            'pagination' => ['icon' => 'bi-list', 'title' => 'Pagination', 'description' => 'Content display settings'],
            'navigation' => ['icon' => 'bi-compass', 'title' => 'Navigation', 'description' => 'Navigation menu labels'],
            'file_manager' => ['icon' => 'bi-folder', 'title' => 'File Manager', 'description' => 'Manage uploaded product images']
        ];
        ?>

        <form id="settingsForm" method="POST" enctype="multipart/form-data" action="?lang=<?php echo $currentLanguage; ?>">
            <input type="hidden" name="active_tab" id="activeTabInput" value="<?php echo htmlspecialchars($activeTab); ?>">
            
            <!-- Nav tabs -->
            <div class="mb-4">
                <ul class="nav nav-pills premium-pills bg-white p-2 rounded-4 shadow-sm border overflow-auto flex-nowrap" id="settingsTabs" role="tablist">
                    <?php foreach ($categories as $category => $categoryInfo): ?>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link rounded-pill px-4 py-2 me-2 <?php echo ($category === $activeTab) ? 'active' : ''; ?>" id="<?php echo $category; ?>-tab" data-bs-toggle="tab" data-bs-target="#<?php echo $category; ?>" type="button" role="tab">
                                <i class="bi <?php echo $categoryInfo['icon']; ?> me-2"></i><?php echo $categoryInfo['title']; ?>
                            </button>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- Tab content -->
            <div class="tab-content" id="settingsTabContent">
                <?php foreach ($categories as $category => $categoryInfo): ?>
                <?php if (!isset($groupedSettings[$category]) && !in_array($category, ['collections', 'contact', 'about', 'file_manager', 'policies', 'social'])) continue; ?>
                    <div class="tab-pane fade <?php echo ($category === $activeTab) ? 'show active' : ''; ?>" id="<?php echo $category; ?>" role="tabpanel">
                        <div class="card border-0 shadow-premium overflow-hidden" style="border-radius: 28px;">
                            <div class="card-header bg-white border-bottom p-4">
                                <div class="d-flex align-items-center">
                                    <div class="stat-icon-box bg-primary-soft me-3 mb-0">
                                        <i class="bi <?php echo $categoryInfo['icon']; ?>"></i>
                                    </div>
                                    <div>
                                        <h4 class="fw-800 mb-1 text-dark"><?php echo $categoryInfo['title']; ?></h4>
                                        <p class="text-secondary small mb-0"><?php echo $categoryInfo['description']; ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body p-5 bg-white">


                                <?php if ($category === 'collections'): ?>
                                    <?php
                                    // Fetch current collection settings
                                    $colSettings = [];
                                    $keys = [
                                        'syrup_collection_title', 'syrup_collection_description', 'syrup_collection_features', 'syrup_collection_products',
                                        'powder_selection_title', 'powder_selection_description', 'powder_selection_features', 'powder_selection_products'
                                    ];
                                    foreach ($keys as $k) {
                                        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ? AND language = ?");
                                        $stmt->execute([$k, $currentLanguage]);
                                        $val = $stmt->fetchColumn();
                                        
                                        // If empty features, check for legacy feature_1, 2, 3
                                        if (empty($val) && ($k === 'syrup_collection_features' || $k === 'powder_selection_features')) {
                                            $prefix = ($k === 'syrup_collection_features') ? 'syrup_collection_feature_' : 'powder_selection_feature_';
                                            $legacyFeatures = [];
                                            for ($i = 1; $i <= 3; $i++) {
                                                $s = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ? AND language = ?");
                                                $s->execute([$prefix . $i, $currentLanguage]);
                                                $v = $s->fetchColumn();
                                                if ($v) $legacyFeatures[] = $v;
                                            }
                                            $val = !empty($legacyFeatures) ? json_encode($legacyFeatures) : '[]';
                                        }
                                        
                                        $colSettings[$k] = $val;
                                    }
                                    
                                    $syrupFeatures = json_decode($colSettings['syrup_collection_features'] ?? '[]', true) ?: [];
                                    $powderFeatures = json_decode($colSettings['powder_selection_features'] ?? '[]', true) ?: [];
                                    ?>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="card h-100">
                                                <div class="card-header bg-light fw-bold"><i class="bi bi-droplet me-2"></i>Syrup Collection</div>
                                                <div class="card-body">
                                                    <div class="mb-3">
                                                        <label class="form-label">Collection Title</label>
                                                        <input type="text" name="syrup_collection_title" class="form-control" value="<?php echo htmlspecialchars($colSettings['syrup_collection_title'] ?? ''); ?>" placeholder="e.g. Syrup Collection">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Description</label>
                                                        <textarea name="syrup_collection_description" class="form-control" rows="4" placeholder="Description for Syrup Collection section..."><?php echo htmlspecialchars($colSettings['syrup_collection_description'] ?? ''); ?></textarea>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label d-flex justify-content-between">
                                                            Products to Display
                                                            <span class="badge bg-secondary">Total: <?php echo count(array_filter($allProducts, function($p) use ($allCategories, $syrupBaseId) {
                                                                foreach ($allCategories as $c) if ($c['id'] == $p['category_id'] && $c['base_category_id'] == $syrupBaseId) return true;
                                                                return false;
                                                            })); ?></span>
                                                        </label>
                                                        <div class="border rounded p-3 bg-white" style="max-height: 200px; overflow-y: auto;">
                                                            <?php 
                                                            $selectedProducts = json_decode($colSettings['syrup_collection_products'] ?? '[]', true) ?: [];
                                                            $syrupProductFound = false;
                                                            foreach ($allProducts as $p): 
                                                                $cat = null;
                                                                foreach ($allCategories as $c) if ($c['id'] == $p['category_id']) $cat = $c;
                                                                if ($cat && $cat['base_category_id'] == $syrupBaseId):
                                                                    $syrupProductFound = true;
                                                            ?>
                                                                <div class="form-check">
                                                                    <input class="form-check-input" type="checkbox" name="syrup_collection_products[]" value="<?php echo $p['base_product_id']; ?>" id="syrup_p_<?php echo $p['id']; ?>" <?php echo in_array($p['base_product_id'], $selectedProducts) ? 'checked' : ''; ?>>
                                                                    <label class="form-check-label" for="syrup_p_<?php echo $p['id']; ?>">
                                                                        <?php echo htmlspecialchars($p['name']); ?>
                                                                    </label>
                                                                </div>
                                                            <?php endif; endforeach; ?>
                                                            <?php if (!$syrupProductFound): ?>
                                                                <small class="text-muted">No products found in Syrup category.</small>
                                                            <?php endif; ?>
                                                        </div>
                                                        <small class="text-muted mt-1 d-block italic">Selected products will appear in the collection carousel.</small>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label d-flex justify-content-between">
                                                            Key Features (Display with checkmarks)
                                                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="addFeature('syrup')"><i class="bi bi-plus"></i> Add</button>
                                                        </label>
                                                        <div id="syrup-features-container" class="feature-list">
                                                            <?php foreach ($syrupFeatures as $feat): ?>
                                                                <div class="input-group mb-2 feature-item">
                                                                    <input type="text" name="syrup_collection_features[]" class="form-control" value="<?php echo htmlspecialchars($feat); ?>">
                                                                    <button class="btn btn-outline-danger" type="button" onclick="this.parentElement.remove()"><i class="bi bi-trash"></i></button>
                                                                </div>
                                                            <?php endforeach; ?>
                                                            <?php if (empty($syrupFeatures)): ?>
                                                                <div class="input-group mb-2 feature-item">
                                                                    <input type="text" name="syrup_collection_features[]" class="form-control" placeholder="e.g. Natural ingredients">
                                                                    <button class="btn btn-outline-danger" type="button" onclick="this.parentElement.remove()"><i class="bi bi-trash"></i></button>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="card h-100">
                                                <div class="card-header bg-light fw-bold"><i class="bi bi-snow me-2"></i>Powder Selection</div>
                                                <div class="card-body">
                                                    <div class="mb-3">
                                                        <label class="form-label">Selection Title</label>
                                                        <input type="text" name="powder_selection_title" class="form-control" value="<?php echo htmlspecialchars($colSettings['powder_selection_title'] ?? ''); ?>" placeholder="e.g. Powder Selection">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Description</label>
                                                        <textarea name="powder_selection_description" class="form-control" rows="4" placeholder="Description for Powder Selection section..."><?php echo htmlspecialchars($colSettings['powder_selection_description'] ?? ''); ?></textarea>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label d-flex justify-content-between">
                                                            Products to Display
                                                            <span class="badge bg-secondary">Total: <?php echo count(array_filter($allProducts, function($p) use ($allCategories, $powderBaseId) {
                                                                foreach ($allCategories as $c) if ($c['id'] == $p['category_id'] && $c['base_category_id'] == $powderBaseId) return true;
                                                                return false;
                                                            })); ?></span>
                                                        </label>
                                                        <div class="border rounded p-3 bg-white" style="max-height: 200px; overflow-y: auto;">
                                                            <?php 
                                                            $selectedProducts = json_decode($colSettings['powder_selection_products'] ?? '[]', true) ?: [];
                                                            $powderProductFound = false;
                                                            foreach ($allProducts as $p): 
                                                                $cat = null;
                                                                foreach ($allCategories as $c) if ($c['id'] == $p['category_id']) $cat = $c;
                                                                if ($cat && $cat['base_category_id'] == $powderBaseId):
                                                                    $powderProductFound = true;
                                                            ?>
                                                                <div class="form-check">
                                                                    <input class="form-check-input" type="checkbox" name="powder_selection_products[]" value="<?php echo $p['base_product_id']; ?>" id="powder_p_<?php echo $p['id']; ?>" <?php echo in_array($p['base_product_id'], $selectedProducts) ? 'checked' : ''; ?>>
                                                                    <label class="form-check-label" for="powder_p_<?php echo $p['id']; ?>">
                                                                        <?php echo htmlspecialchars($p['name']); ?>
                                                                    </label>
                                                                </div>
                                                            <?php endif; endforeach; ?>
                                                            <?php if (!$powderProductFound): ?>
                                                                <small class="text-muted">No products found in Powder category.</small>
                                                            <?php endif; ?>
                                                        </div>
                                                        <small class="text-muted mt-1 d-block italic">Selected products will appear in the selection carousel.</small>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label d-flex justify-content-between">
                                                            Key Features (Display with checkmarks)
                                                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="addFeature('powder')"><i class="bi bi-plus"></i> Add</button>
                                                        </label>
                                                        <div id="powder-features-container" class="feature-list">
                                                            <?php foreach ($powderFeatures as $feat): ?>
                                                                <div class="input-group mb-2 feature-item">
                                                                    <input type="text" name="powder_selection_features[]" class="form-control" value="<?php echo htmlspecialchars($feat); ?>">
                                                                    <button class="btn btn-outline-danger" type="button" onclick="this.parentElement.remove()"><i class="bi bi-trash"></i></button>
                                                                </div>
                                                            <?php endforeach; ?>
                                                            <?php if (empty($powderFeatures)): ?>
                                                                <div class="input-group mb-2 feature-item">
                                                                    <input type="text" name="powder_selection_features[]" class="form-control" placeholder="e.g. Premium quality">
                                                                    <button class="btn btn-outline-danger" type="button" onclick="this.parentElement.remove()"><i class="bi bi-trash"></i></button>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <script>
                                        function addFeature(type) {
                                            const container = document.getElementById(type + '-features-container');
                                            const div = document.createElement('div');
                                            div.className = 'input-group mb-2 feature-item';
                                            div.innerHTML = `
                                                <input type="text" name="${type === 'syrup' ? 'syrup_collection_features' : 'powder_selection_features'}[]" class="form-control" placeholder="New feature...">
                                                <button class="btn btn-outline-danger" type="button" onclick="this.parentElement.remove()"><i class="bi bi-trash"></i></button>
                                            `;
                                            container.appendChild(div);
                                            div.querySelector('input').focus();
                                        }
                                    </script>

                                <?php elseif ($category === 'about'): ?>
                                    <?php
                                    // Fetch current about settings
                                    $aboutSettings = [];
                                    $keys = [
                                        'about_title', 'about_content',
                                        'about_purpose_title', 'about_purpose_content',
                                        'about_mission_title', 'about_mission_content'
                                    ];
                                    foreach ($keys as $k) {
                                        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ? AND language = ?");
                                        $stmt->execute([$k, $currentLanguage]);
                                        $aboutSettings[$k] = $stmt->fetchColumn();
                                    }
                                    ?>
                                    <div class="row">
                                        <!-- About Header/Intro -->
                                        <div class="col-12 mb-4">
                                            <div class="card bg-light border-0">
                                                <div class="card-body">
                                                    <h5 class="fw-bold mb-3"><i class="bi bi-info-circle me-2"></i>About Intro</h5>
                                                    <div class="mb-3">
                                                        <label class="form-label">Page Title</label>
                                                        <input type="text" name="about_title" class="form-control" value="<?php echo htmlspecialchars($aboutSettings['about_title'] ?? ''); ?>" placeholder="e.g. About KouPrey Coffee">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Intro Content</label>
                                                        <textarea name="about_content" class="form-control" rows="4" placeholder="Main introduction text..."><?php echo htmlspecialchars($aboutSettings['about_content'] ?? ''); ?></textarea>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Our Purpose Section -->
                                        <div class="col-md-6 mb-4">
                                            <div class="card h-100 border">
                                                <div class="card-header bg-white fw-bold"><i class="bi bi-bullseye me-2"></i>Our Purpose</div>
                                                <div class="card-body">
                                                    <div class="mb-3">
                                                        <label class="form-label">Section Title</label>
                                                        <input type="text" name="about_purpose_title" class="form-control" value="<?php echo htmlspecialchars($aboutSettings['about_purpose_title'] ?? ''); ?>" placeholder="e.g. Our Purpose">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Purpose Content</label>
                                                        <textarea name="about_purpose_content" class="form-control" rows="4" placeholder="Detail your purpose..."><?php echo htmlspecialchars($aboutSettings['about_purpose_content'] ?? ''); ?></textarea>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Our Mission Section -->
                                        <div class="col-md-6 mb-4">
                                            <div class="card h-100 border">
                                                <div class="card-header bg-white fw-bold"><i class="bi bi-flag me-2"></i>Our Mission</div>
                                                <div class="card-body">
                                                    <div class="mb-3">
                                                        <label class="form-label">Section Title</label>
                                                        <input type="text" name="about_mission_title" class="form-control" value="<?php echo htmlspecialchars($aboutSettings['about_mission_title'] ?? ''); ?>" placeholder="e.g. Our Mission">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Mission Content</label>
                                                        <textarea name="about_mission_content" class="form-control" rows="4" placeholder="Detail your mission..."><?php echo htmlspecialchars($aboutSettings['about_mission_content'] ?? ''); ?></textarea>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                <?php elseif ($category === 'contact'): ?>
                                    <?php
                                    // Fetch current contact settings
                                    $contactSettings = [];
                                    $keys = [
                                        'company_address', 'company_phone', 'company_email', 'company_hours'
                                    ];
                                    foreach ($keys as $k) {
                                        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ? AND language = ?");
                                        $stmt->execute([$k, $currentLanguage]);
                                        $contactSettings[$k] = $stmt->fetchColumn();
                                    }
                                    ?>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Company Address</label>
                                            <textarea name="company_address" class="form-control" rows="3" placeholder="Enter company address..."><?php echo htmlspecialchars($contactSettings['company_address'] ?? ''); ?></textarea>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Contact Phone</label>
                                            <input type="text" name="company_phone" class="form-control" value="<?php echo htmlspecialchars($contactSettings['company_phone'] ?? ''); ?>" placeholder="e.g. +855 12 345 678">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Contact Email</label>
                                            <input type="email" name="company_email" class="form-control" value="<?php echo htmlspecialchars($contactSettings['company_email'] ?? ''); ?>" placeholder="e.g. info@kouprey.com">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Business Hours</label>
                                            <input type="text" name="company_hours" class="form-control" value="<?php echo htmlspecialchars($contactSettings['company_hours'] ?? ''); ?>" placeholder="e.g. Mon-Fri: 9AM-6PM, Sat-Sun: 10AM-4PM">
                                        </div>
                                    </div>

                                <?php elseif ($category === 'file_manager'): ?>
                                    <div class="row">
                                        <div class="col-12 mb-4">
                                            <div class="card shadow-sm">
                                                <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                                                    <h5 class="mb-0 fw-bold text-primary"><i class="bi bi-images me-2"></i>Product Images Library</h5>
                                                    <div class="d-flex gap-2">
                                                        <button type="button" id="deleteSelectedBtn" class="btn btn-danger d-none" onclick="bulkDeleteFiles()">
                                                            <i class="bi bi-trash3 me-2"></i>Delete Selected (<span id="selectedCount">0</span>)
                                                        </button>
                                                        <button type="submit" name="convert_webp_all" value="1" class="btn btn-warning text-white" onclick="return confirm('Convert all images to WebP to improve website speed? This keeps quality high but significantly reduces file size. Existing images will be optimized.')">
                                                            <i class="bi bi-magic me-2"></i>Convert All to WebP
                                                        </button>
                                                        <button type="button" class="btn btn-primary" onclick="document.getElementById('fileManagerUpload').click()">
                                                            <i class="bi bi-cloud-upload me-2"></i>Upload New Images
                                                        </button>
                                                    </div>
                                                </div>
                                                <div class="card-body bg-light">
                                                    <!-- Bulk Delete Hidden Input -->
                                                    <input type="hidden" name="delete_file_manager_bulk" id="bulkDeleteInput">
                                                    
                                                    <div class="alert alert-info py-2 small mb-3">
                                                        <i class="bi bi-info-circle me-2"></i>Hold <strong>Ctrl</strong> key and click to select multiple images for bulk deletion.
                                                    </div>
                                                    <!-- Upload Form (Hidden) -->
                                                    <div class="d-none">
                                                        <input type="file" id="fileManagerUpload" name="file_manager_upload[]" multiple accept="image/*" onchange="if(confirm('Upload selected files? This may replace existing files with the same name.')) { this.form.submit(); }">
                                                        <input type="hidden" name="upload_file_manager" value="1">
                                                    </div>

                                                    <div class="row g-3">
                                                        <?php
                                                        $productImages = glob('../public/assets/images/products/*.{jpg,jpeg,png,gif,webp,JPG,JPEG,PNG,GIF,WEBP}', GLOB_BRACE);
                                                        if ($productImages):
                                                            // Sort by filemtime desc
                                                            usort($productImages, function($a, $b) {
                                                                return filemtime($b) - filemtime($a);
                                                            });
                                                            
                                                            foreach ($productImages as $img):
                                                                $basename = basename($img);
                                                                $url = '/kouprey/public/assets/images/products/' . $basename;
                                                                $size = round(filesize($img) / 1024, 1) . ' KB';
                                                        ?>
                                                            <div class="col-6 col-md-4 col-lg-3 col-xl-2">
                                                                <div class="card h-100 shadow-sm border-2 product-image-card selectable-image" data-filename="<?php echo htmlspecialchars($basename); ?>" onclick="toggleImageSelection(event, this)">
                                                                    <div class="position-relative ratio ratio-1x1 bg-white rounded-top overflow-hidden border-bottom">
                                                                        <img src="<?php echo htmlspecialchars($url); ?>" class="object-fit-contain w-100 h-100 p-2" alt="Product Image" loading="lazy">
                                                                        <div class="position-absolute top-0 start-0 m-2 selection-indicator d-none">
                                                                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center shadow-sm" style="width: 24px; height: 24px;">
                                                                                <i class="bi bi-check-lg"></i>
                                                                            </div>
                                                                        </div>
                                                                        <div class="position-absolute top-0 end-0 p-1">
                                                                            <span class="badge bg-dark bg-opacity-50"><?php echo $size; ?></span>
                                                                        </div>
                                                                    </div>
                                                                    <div class="card-body p-2 bg-white rounded-bottom">
                                                                        <div class="mb-2 text-truncate small fw-bold text-dark" title="<?php echo htmlspecialchars($basename); ?>">
                                                                            <?php echo htmlspecialchars($basename); ?>
                                                                        </div>
                                                                        <div class="d-grid gap-2">
                                                                            <!-- Individual buttons still work but won't trigger if clicked with Ctrl -->
                                                                            <button type="button" class="btn btn-outline-primary btn-sm stop-prop" onclick="event.stopPropagation(); document.getElementById('replace_<?php echo md5($basename); ?>').click()">
                                                                                <i class="bi bi-arrow-repeat"></i> Replace
                                                                            </button>
                                                                            <button type="submit" name="delete_file_manager" value="<?php echo htmlspecialchars($basename); ?>" class="btn btn-outline-danger btn-sm stop-prop" onclick="event.stopPropagation(); return confirm('Delete \'<?php echo htmlspecialchars($basename); ?>\'? This cannot be undone.')">
                                                                                <i class="bi bi-trash"></i> Delete
                                                                            </button>
                                                                            <!-- Hidden replace input for this specific file -->
                                                                            <div class="d-none">
                                                                                <input type="file" id="replace_<?php echo md5($basename); ?>" name="file_manager_replace_<?php echo str_replace('.', '_', $basename); ?>" accept="image/*" onchange="if(confirm('Replace \'<?php echo htmlspecialchars($basename); ?>\' with this file?')) { this.form.submit(); }">
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        <?php 
                                                            endforeach;
                                                        else:
                                                        ?>
                                                            <div class="col-12 text-center py-5 text-muted">
                                                                <i class="bi bi-folder2-open display-1 text-secondary opacity-25"></i>
                                                                <p class="mt-3 fs-5">No product images found.</p>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                <?php elseif ($category === 'social'): ?>
                                    <!-- Social Media with Preview -->
                                    <?php
                                    $socialBannerText = '';
                                    foreach ($groupedSettings['social'] as $s) {
                                        if ($s['setting_key'] === 'social_banner_text') $socialBannerText = $s['setting_value'] ?? '';
                                    }
                                    $fbUrl = getSetting('social_facebook', '', $currentLanguage);
                                    $igUrl = getSetting('social_instagram', '', $currentLanguage);
                                    $ttUrl = getSetting('social_tiktok', '', $currentLanguage);
                                    $tgUrl = getSetting('social_telegram', '', $currentLanguage);
                                    ?>
                                    <div class="row">
                                        <div class="col-lg-8">
                                            <!-- Social Banner Heading (Rich Text) -->
                                            <div class="card border mb-4">
                                                <div class="card-header bg-light">
                                                    <span class="badge bg-dark me-2"><i class="bi bi-megaphone"></i> Banner Heading</span>
                                                    <small class="text-muted">Social media banner text (supports rich formatting, icons, headings)</small>
                                                </div>
                                                <div class="card-body p-0">
                                                    <div class="rte-toolbar" data-editor="social_banner_editor">
                                                        <button type="button" data-cmd="bold" title="Bold"><b>B</b></button>
                                                        <button type="button" data-cmd="italic" title="Italic"><i>I</i></button>
                                                        <button type="button" data-cmd="underline" title="Underline"><u>U</u></button>
                                                        <span class="rte-sep"></span>
                                                        <select data-cmd="formatBlock" title="Heading">
                                                            <option value="p">Paragraph</option>
                                                            <option value="h2">Heading 2</option>
                                                            <option value="h3">Heading 3</option>
                                                        </select>
                                                        <span class="rte-sep"></span>
                                                        <input type="color" data-cmd="foreColor" title="Text Color" value="#ffffff">
                                                        <span class="rte-sep"></span>
                                                        <button type="button" data-cmd="justifyLeft" title="Left">≡◁</button>
                                                        <button type="button" data-cmd="justifyCenter" title="Center">≡◌</button>
                                                        <button type="button" data-cmd="justifyRight" title="Right">▷≡</button>
                                                        <span class="rte-sep"></span>
                                                        <button type="button" class="rte-emoji-btn" title="Insert Icon">😊</button>
                                                        <button type="button" data-cmd="removeFormat" title="Clear" style="color:#dc3545;">✕</button>
                                                    </div>
                                                    <div class="rte-emoji-panel" data-editor="social_banner_editor">
                                                        <?php
                                                        $emojiList = ['📌','🔴','🟢','🔵','⭐','✅','💡','🔥','🎯','📝','💬','📧','📞','📍','🌐','💻','📱','🛒','📦','💰','🎉','❤️','👍','➡️','⬅️','⬆️','⬇️','▶️','®','©','™','°','•'];
                                                        foreach ($emojiList as $emoji) {
                                                            echo '<span onclick="insertEmoji(\'social_banner_editor\', \'' . $emoji . '\')">' . $emoji . '</span>';
                                                        }
                                                        ?>
                                                    </div>
                                                    <div id="social_banner_editor" class="rte-editor" contenteditable="true" data-textarea="social_banner_text"><?php echo $socialBannerText; ?></div>
                                                    <textarea name="social_banner_text" id="social_banner_text_textarea" style="display:none;"><?php echo htmlspecialchars($socialBannerText); ?></textarea>
                                                </div>
                                            </div>

                                            <!-- Social Media URLs -->
                                            <div class="card border mb-4">
                                                <div class="card-header bg-light">
                                                    <span class="badge bg-primary me-2"><i class="bi bi-link-45deg"></i> Social Links</span>
                                                    <small class="text-muted">Add your social media profile URLs</small>
                                                </div>
                                                <div class="card-body">
                                                    <div class="row g-3">
                                                        <div class="col-md-6">
                                                            <label class="form-label fw-bold">
                                                                <i class="fab fa-facebook-f text-primary me-2"></i>Facebook Page URL
                                                            </label>
                                                            <input type="url" name="social_facebook" class="form-control" value="<?php echo htmlspecialchars($fbUrl); ?>" placeholder="https://www.facebook.com/yourpage">
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label fw-bold">
                                                                <i class="fab fa-instagram text-danger me-2"></i>Instagram Page URL
                                                            </label>
                                                            <input type="url" name="social_instagram" class="form-control" value="<?php echo htmlspecialchars($igUrl); ?>" placeholder="https://instagram.com/yourprofile">
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label fw-bold">
                                                                <i class="fab fa-tiktok text-dark me-2"></i>TikTok Page URL
                                                            </label>
                                                            <input type="url" name="social_tiktok" class="form-control" value="<?php echo htmlspecialchars($ttUrl); ?>" placeholder="https://tiktok.com/@yourhandle">
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label fw-bold">
                                                                <i class="fab fa-telegram-plane text-info me-2"></i>Telegram URL
                                                            </label>
                                                            <input type="url" name="social_telegram" class="form-control" value="<?php echo htmlspecialchars($tgUrl); ?>" placeholder="https://t.me/yourchannel">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Preview Panel -->
                                        <div class="col-lg-4">
                                            <div class="card border shadow-sm sticky-top" style="top: 20px; z-index: 10;">
                                                <div class="card-header bg-dark text-white">
                                                    <h5 class="mb-0"><i class="bi bi-eye-fill me-2"></i>Live Preview</h5>
                                                </div>
                                                <div class="card-body">
                                                    <!-- Banner Preview -->
                                                    <div style="background: #111; border-radius: 12px; padding: 20px; text-align: center; margin-bottom: 16px;">
                                                        <div id="socialBannerPreview" style="color: #fff; font-family: Hanuman, serif; font-size: 14px; line-height: 1.6; margin-bottom: 12px;">
                                                            <?php echo $socialBannerText ?: '<span style="opacity:0.5;">Banner text will appear here</span>'; ?>
                                                        </div>
                                                        <div style="display: flex; justify-content: center; gap: 12px;">
                                                            <span class="social-icon-preview fb"><i class="fab fa-facebook-f"></i></span>
                                                            <span class="social-icon-preview ig"><i class="fab fa-instagram"></i></span>
                                                            <span class="social-icon-preview tt"><i class="fab fa-tiktok"></i></span>
                                                            <span class="social-icon-preview tg"><i class="fab fa-telegram-plane"></i></span>
                                                        </div>
                                                    </div>
                                                    <small class="text-muted d-block text-center">↑ Front-end social banner preview</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <script>
                                    // Live update social banner preview
                                    (function() {
                                        var bannerEditor = document.getElementById('social_banner_editor');
                                        var bannerPreview = document.getElementById('socialBannerPreview');
                                        if (bannerEditor && bannerPreview) {
                                            var updateBannerPreview = function() {
                                                var html = bannerEditor.innerHTML.trim();
                                                bannerPreview.innerHTML = html || '<span style="opacity:0.5;">Banner text will appear here</span>';
                                            };
                                            bannerEditor.addEventListener('input', updateBannerPreview);
                                            bannerEditor.addEventListener('blur', updateBannerPreview);
                                            // Also update on toolbar actions
                                            bannerEditor.addEventListener('keyup', updateBannerPreview);
                                        }
                                    })();
                                    </script>

                                <?php elseif ($category === 'policies'): ?>
                                    <!-- Policies & Legal with Custom Rich Text Editor and Preview -->

                                    <div class="row">
                                        <div class="col-lg-7">
                                            <?php
                                            $policyFields = [
                                                ['key' => 'contact_us', 'label' => 'Contact Us', 'badge' => 'bg-primary', 'icon' => 'bi-telephone'],
                                                ['key' => 'privacy_policy', 'label' => 'Privacy Policy', 'badge' => 'bg-info', 'icon' => 'bi-shield-check'],
                                                ['key' => 'terms_of_service', 'label' => 'Terms of Service', 'badge' => 'bg-success', 'icon' => 'bi-file-earmark-text'],
                                            ];
                                            foreach ($policyFields as $pf):
                                                $content = '';
                                                foreach ($groupedSettings['policies'] as $s) {
                                                    if ($s['setting_key'] === $pf['key']) $content = $s['setting_value'] ?? '';
                                                }
                                            ?>
                                            <div class="card border mb-4">
                                                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <span class="badge <?php echo $pf['badge']; ?> me-2"><?php echo $pf['label']; ?></span>
                                                        <small class="text-muted"><?php echo $pf['label']; ?> content</small>
                                                    </div>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary preview-btn" data-target="<?php echo $pf['key']; ?>">
                                                        <i class="bi bi-eye"></i> Preview
                                                    </button>
                                                </div>
                                                <div class="card-body p-0">
                                                    <!-- Toolbar -->
                                                    <div class="rte-toolbar" data-editor="<?php echo $pf['key']; ?>_editor">
                                                        <button type="button" data-cmd="bold" title="Bold (Ctrl+B)"><b>B</b></button>
                                                        <button type="button" data-cmd="italic" title="Italic (Ctrl+I)"><i>I</i></button>
                                                        <button type="button" data-cmd="underline" title="Underline (Ctrl+U)"><u>U</u></button>
                                                        <button type="button" data-cmd="strikeThrough" title="Strikethrough"><s>S</s></button>
                                                        <span class="rte-sep"></span>
                                                        <select data-cmd="formatBlock" title="Heading / Paragraph">
                                                            <option value="p">Paragraph</option>
                                                            <option value="h1">Heading 1</option>
                                                            <option value="h2">Heading 2</option>
                                                            <option value="h3">Heading 3</option>
                                                        </select>
                                                        <span class="rte-sep"></span>
                                                        <input type="color" data-cmd="foreColor" title="Text Color" value="#333333">
                                                        <button type="button" data-cmd="hiliteColor" title="Highlight" style="background:#fff3cd;">🖌</button>
                                                        <span class="rte-sep"></span>
                                                        <button type="button" data-cmd="insertUnorderedList" title="Bullet List">•≡</button>
                                                        <button type="button" data-cmd="insertOrderedList" title="Numbered List">1≡</button>
                                                        <span class="rte-sep"></span>
                                                        <button type="button" data-cmd="justifyLeft" title="Align Left">≡◁</button>
                                                        <button type="button" data-cmd="justifyCenter" title="Align Center">≡◌</button>
                                                        <button type="button" data-cmd="justifyRight" title="Align Right">▷≡</button>
                                                        <span class="rte-sep"></span>
                                                        <button type="button" data-cmd="createLink" title="Insert Link">🔗</button>
                                                        <button type="button" data-cmd="unlink" title="Remove Link">✂</button>
                                                        <span class="rte-sep"></span>
                                                        <button type="button" class="rte-emoji-btn" title="Insert Icon / Emoji">😊</button>
                                                        <button type="button" data-cmd="removeFormat" title="Clear Formatting" style="color:#dc3545;">✕</button>
                                                    </div>
                                                    <!-- Emoji Panel -->
                                                    <div class="rte-emoji-panel" data-editor="<?php echo $pf['key']; ?>_editor">
                                                        <?php
                                                        $emojiList = ['📌','🔴','🟢','🔵','🟡','⭐','✅','❌','⚠️','ℹ️','💡','🔥','🎯','📝','📋','📎','🔒','🔑','💬','📧','📞','📍','🏠','🌐','💻','📱','🛒','📦','🚚','💰','💳','📊','📈','🏆','🎉','❤️','👍','👎','➡️','⬅️','⬆️','⬇️','▶️','⏸️','⏹️','🔄','➕','➖','✖️','➗','®','©','™','°','•','§','¶'];
                                                        foreach ($emojiList as $emoji) {
                                                            echo '<span onclick="insertEmoji(\'' . $pf['key'] . '_editor\', \'' . $emoji . '\')">' . $emoji . '</span>';
                                                        }
                                                        ?>
                                                    </div>
                                                    <!-- Editor -->
                                                    <div id="<?php echo $pf['key']; ?>_editor" class="rte-editor" contenteditable="true" data-textarea="<?php echo $pf['key']; ?>"><?php echo $content; ?></div>
                                                    <!-- Hidden textarea for form submission -->
                                                    <textarea name="<?php echo $pf['key']; ?>" id="<?php echo $pf['key']; ?>_textarea" style="display:none;"><?php echo htmlspecialchars($content); ?></textarea>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>

                                        <!-- Preview Panel -->
                                        <div class="col-lg-5">
                                            <div class="card border shadow-sm sticky-top" style="top: 20px; z-index: 10;">
                                                <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                                                    <h5 class="mb-0"><i class="bi bi-eye-fill me-2"></i>Front-End Preview</h5>
                                                    <span id="previewLabel" class="badge bg-light text-dark">Select a section</span>
                                                </div>
                                                <div class="card-body p-0">
                                                    <iframe id="previewFrame" style="width: 100%; height: 500px; border: none;" srcdoc="<html><body style='font-family:Hanuman,serif;padding:20px;color:#333;background:#fff;'><p style='color:#999;text-align:center;margin-top:200px;'>Click <strong>Preview</strong> on any section to see how it looks on the front-end</p></body></html>"></iframe>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Custom Rich Text Editor JavaScript -->
                                    <script>
                                    document.addEventListener('DOMContentLoaded', function() {
                                        // ===== Toolbar Button Handlers =====
                                        document.querySelectorAll('.rte-toolbar').forEach(function(toolbar) {
                                            var editorId = toolbar.getAttribute('data-editor');

                                            toolbar.querySelectorAll('button[data-cmd]').forEach(function(btn) {
                                                btn.addEventListener('click', function(e) {
                                                    e.preventDefault();
                                                    var cmd = this.getAttribute('data-cmd');
                                                    var editor = document.getElementById(editorId);
                                                    editor.focus();

                                                    if (cmd === 'createLink') {
                                                        var url = prompt('Enter URL:', 'https://');
                                                        if (url) document.execCommand('createLink', false, url);
                                                    } else if (cmd === 'hiliteColor') {
                                                        document.execCommand('hiliteColor', false, '#FFF3CD');
                                                    } else {
                                                        document.execCommand(cmd, false, null);
                                                    }
                                                    syncTextarea(editorId);
                                                });
                                            });

                                            toolbar.querySelector('select[data-cmd="formatBlock"]').addEventListener('change', function() {
                                                var editor = document.getElementById(editorId);
                                                editor.focus();
                                                document.execCommand('formatBlock', false, '<' + this.value + '>');
                                                syncTextarea(editorId);
                                            });

                                            toolbar.querySelector('input[type="color"]').addEventListener('input', function() {
                                                var editor = document.getElementById(editorId);
                                                editor.focus();
                                                document.execCommand('foreColor', false, this.value);
                                                syncTextarea(editorId);
                                            });
                                        });

                                        // ===== Emoji Button Toggle =====
                                        document.querySelectorAll('.rte-emoji-btn').forEach(function(btn) {
                                            btn.addEventListener('click', function(e) {
                                                e.preventDefault();
                                                e.stopPropagation();
                                                var panel = this.parentElement.nextElementSibling;
                                                // Close all panels first
                                                document.querySelectorAll('.rte-emoji-panel').forEach(function(p) {
                                                    if (p !== panel) p.classList.remove('show');
                                                });
                                                panel.classList.toggle('show');
                                            });
                                        });

                                        // Close emoji panel on outside click
                                        document.addEventListener('click', function(e) {
                                            if (!e.target.closest('.rte-emoji-btn') && !e.target.closest('.rte-emoji-panel')) {
                                                document.querySelectorAll('.rte-emoji-panel').forEach(function(p) {
                                                    p.classList.remove('show');
                                                });
                                            }
                                        });

                                        // ===== Sync contenteditable → hidden textarea =====
                                        function syncTextarea(editorId) {
                                            var editor = document.getElementById(editorId);
                                            if (!editor) return;
                                            var textareaId = editor.getAttribute('data-textarea') + '_textarea';
                                            var textarea = document.getElementById(textareaId);
                                            if (textarea) {
                                                textarea.value = editor.innerHTML;
                                            }
                                            // Auto-update preview (only if previewFrame exists)
                                            var previewFrame = document.getElementById('previewFrame');
                                            if (previewFrame) {
                                                var activePreview = previewFrame.getAttribute('data-active');
                                                var target = editor.getAttribute('data-textarea');
                                                if (activePreview && target && activePreview === target) {
                                                    updatePreview(target);
                                                }
                                            }
                                        }

                                        window.insertEmoji = function(editorId, emoji) {
                                            var editor = document.getElementById(editorId);
                                            editor.focus();
                                            // Insert at cursor
                                            var sel = window.getSelection();
                                            if (sel.rangeCount) {
                                                var range = sel.getRangeAt(0);
                                                range.deleteContents();
                                                var textNode = document.createTextNode(emoji + ' ');
                                                range.insertNode(textNode);
                                                range.setStartAfter(textNode);
                                                range.collapse(true);
                                                sel.removeAllRanges();
                                                sel.addRange(range);
                                            }
                                            syncTextarea(editorId);
                                            // Close panel
                                            document.querySelectorAll('.rte-emoji-panel').forEach(function(p) { p.classList.remove('show'); });
                                        };

                                        // Sync on input/blur
                                        document.querySelectorAll('.rte-editor').forEach(function(editor) {
                                            editor.addEventListener('input', function() {
                                                syncTextarea(this.id);
                                            });
                                            editor.addEventListener('blur', function() {
                                                syncTextarea(this.id);
                                            });
                                            // Keyboard shortcuts
                                            editor.addEventListener('keydown', function(e) {
                                                if (e.ctrlKey || e.metaKey) {
                                                    switch (e.key) {
                                                        case 'b': e.preventDefault(); document.execCommand('bold'); syncTextarea(this.id); break;
                                                        case 'i': e.preventDefault(); document.execCommand('italic'); syncTextarea(this.id); break;
                                                        case 'u': e.preventDefault(); document.execCommand('underline'); syncTextarea(this.id); break;
                                                        case '1': case '2': case '3':
                                                            e.preventDefault();
                                                            document.execCommand('formatBlock', false, '<h' + e.key + '>');
                                                            syncTextarea(this.id);
                                                            break;
                                                        case '0':
                                                            e.preventDefault();
                                                            document.execCommand('formatBlock', false, '<p>');
                                                            syncTextarea(this.id);
                                                            break;
                                                    }
                                                }
                                            });
                                        });

                                        // ===== Preview Functionality =====
                                        function updatePreview(target) {
                                            var editor = document.getElementById(target + '_editor');
                                            var content = editor ? editor.innerHTML : '';

                                            var labelMap = {
                                                'contact_us': 'Contact Us',
                                                'privacy_policy': 'Privacy Policy',
                                                'terms_of_service': 'Terms of Service'
                                            };
                                            var previewLabel = document.getElementById('previewLabel');
                                            if (previewLabel) previewLabel.textContent = labelMap[target] || target;

                                            var previewFrame = document.getElementById('previewFrame');
                                            if (!previewFrame) return;
                                            previewFrame.setAttribute('data-active', target);

                                            var borderColor = target === 'privacy_policy' ? '#3B82F6' : (target === 'terms_of_service' ? '#10B981' : '#F59E0B');
                                            var previewHTML = '<!DOCTYPE html><html lang="km"><head><meta charset="UTF-8">' +
                                                '<link href="https://fonts.googleapis.com/css2?family=Hanuman:wght@400;700&display=swap" rel="stylesheet">' +
                                                '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">' +
                                                '<style>' +
                                                'body { font-family: Hanuman, serif; font-size: 16px; line-height: 1.9; color: #333; padding: 25px; background: #fff; }' +
                                                'h1 { font-size: 1.8rem; color: #1E3A5F; margin-bottom: 1rem; }' +
                                                'h2 { font-size: 1.4rem; color: #1E3A5F; margin: 1.5rem 0 0.8rem; display: flex; align-items: center; gap: 0.5rem; }' +
                                                'h3 { font-size: 1.2rem; color: #374151; margin: 1.2rem 0 0.6rem; }' +
                                                'p { margin-bottom: 1rem; color: #4B5563; }' +
                                                'ul, ol { margin-bottom: 1rem; padding-left: 1.5rem; }' +
                                                'li { padding: 0.3rem 0; color: #4B5563; }' +
                                                'strong { font-weight: 700; }' +
                                                '.content-section { border-left: 4px solid ' + borderColor + '; padding-left: 1.5rem; margin: 1.5rem 0; }' +
                                                'img { max-width: 100%; height: auto; border-radius: 8px; }' +
                                                'table { border-collapse: collapse; width: 100%; margin: 1rem 0; }' +
                                                'table td, table th { border: 1px solid #ddd; padding: 8px 12px; }' +
                                                '</style></head><body><div class="content-section">' + content + '</div></body></html>';

                                            previewFrame.srcdoc = previewHTML;
                                        }

                                        // Preview button handlers
                                        document.querySelectorAll('.preview-btn').forEach(function(btn) {
                                            btn.addEventListener('click', function(e) {
                                                e.preventDefault();
                                                var target = this.getAttribute('data-target');
                                                updatePreview(target);
                                            });
                                        });

                                        // Sync all on form submit
                                        document.getElementById('settingsForm').addEventListener('submit', function() {
                                            document.querySelectorAll('.rte-editor').forEach(function(editor) {
                                                syncTextarea(editor.id);
                                            });
                                        });

                                        // Initial sync
                                        document.querySelectorAll('.rte-editor').forEach(function(editor) {
                                            syncTextarea(editor.id);
                                        });
                                    });
                                    </script>

                                <?php else: ?>
                                    <!-- Default handling for other categories -->
                                    <div class="row">
                                        <?php foreach ($groupedSettings[$category] as $setting): ?>
                                            <div class="col-md-6">
                                                <div class="setting-item">
                                                    <label class="setting-label">
                                                        <?php 
                                                        $label = ucfirst(str_replace('_', ' ', $setting['setting_key']));
                                                        echo htmlspecialchars($label); 
                                                        ?>
                                                    </label>
                                                    <?php if (isset($setting['description']) && $setting['description'] && strcasecmp(trim($setting['description']), trim($label)) !== 0): ?>
                                                        <div class="setting-description"><?php echo htmlspecialchars($setting['description']); ?></div>
                                                    <?php endif; ?>

                                                    <?php if ($setting['setting_key'] === 'hero_background_image'): ?>
                                                        <div class="mb-2">
                                                            <input type="file" class="form-control" name="hero_background_image" accept="image/*">
                                                            <?php
                                                            // Get all hero background images from uploads folder
                                                            $heroImages = glob('../public/uploads/hero-bg-*.*');
                                                            if (!empty($heroImages)):
                                                            ?>
                                                                <div class="mt-3">
                                                                    <small class="text-muted">Existing hero images:</small>
                                                                    <div class="row mt-2">
                                                                        <?php foreach ($heroImages as $imagePath): 
                                                                            $imageFile = basename($imagePath);
                                                                            $imageUrl = '/kouprey/public/uploads/' . $imageFile;
                                                                            $isCurrent = ($setting['setting_value'] === $imageUrl);
                                                                        ?>
                                                                            <div class="col-md-4 mb-3">
                                                                                <div class="card">
                                                                                    <div class="card-body p-2">
                                                                                        <img src="<?php echo htmlspecialchars($imageUrl); ?>" alt="Hero image" class="img-fluid rounded" style="max-height: 100px; width: 100%; object-fit: cover;">
                                                                                        <div class="mt-2 text-center">
                                                                                            <?php if ($isCurrent): ?>
                                                                                                <span class="badge bg-success">Current</span>
                                                                                            <?php endif; ?>
                                                                                            <button type="submit" name="delete_hero_file" value="<?php echo htmlspecialchars($imageFile); ?>" class="btn btn-outline-danger btn-sm mt-1" onclick="return confirm('Are you sure you want to delete this hero image?')">
                                                                                                <i class="bi bi-trash"></i> Delete
                                                                                            </button>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        <?php endforeach; ?>
                                                                    </div>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php elseif ($setting['setting_key'] === 'company_logo'): ?>
                                                        <div class="mb-2">
                                                            <input type="file" class="form-control" name="company_logo" accept="image/*">
                                                            <?php if (!empty($setting['setting_value'])): ?>
                                                                <div class="mt-2">
                                                                    <small class="text-muted">Current logo:</small><br>
                                                                    <img src="<?php echo htmlspecialchars($setting['setting_value']); ?>" alt="Company logo" style="max-width: 200px; max-height: 100px; border-radius: 8px; border: 2px solid #e5e7eb;">
                                                                    <button type="submit" name="delete_company_logo" class="btn btn-outline-danger btn-sm mt-2" onclick="return confirm('Are you sure you want to delete the company logo?')">
                                                                        <i class="bi bi-trash"></i> Delete Logo
                                                                    </button>
                                                                </div>
                                                            <?php endif; ?>
                                                            <div class="mt-2">
                                                                <small class="text-muted">Recommended size: 600x200px (higher quality). Supported formats: JPG, PNG, GIF, WebP</small>
                                                            </div>
                                                        </div>

                                                    <?php elseif ($setting['setting_type'] === 'textarea'): ?>
                                                        <textarea class="form-control" name="<?php echo $setting['setting_key']; ?>" rows="3"><?php echo htmlspecialchars($setting['setting_value'] ?? ''); ?></textarea>
                                                    <?php elseif ($setting['setting_type'] === 'boolean'): ?>
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" type="checkbox" name="<?php echo $setting['setting_key']; ?>" value="1" <?php echo ($setting['setting_value'] == '1') ? 'checked' : ''; ?>>
                                                            <label class="form-check-label">Enable</label>
                                                        </div>
                                                    <?php elseif ($setting['setting_type'] === 'number'): ?>
                                                        <input type="number" class="form-control" name="<?php echo $setting['setting_key']; ?>" value="<?php echo htmlspecialchars($setting['setting_value'] ?? ''); ?>">
                                                    <?php elseif ($setting['setting_type'] === 'email'): ?>
                                                        <input type="email" class="form-control" name="<?php echo $setting['setting_key']; ?>" value="<?php echo htmlspecialchars($setting['setting_value'] ?? ''); ?>">
                                                    <?php elseif ($setting['setting_type'] === 'url'): ?>
                                                        <input type="url" class="form-control" name="<?php echo $setting['setting_key']; ?>" value="<?php echo htmlspecialchars($setting['setting_value'] ?? ''); ?>">
                                                    <?php else: ?>
                                                        <input type="text" class="form-control" name="<?php echo $setting['setting_key']; ?>" value="<?php echo htmlspecialchars($setting['setting_value'] ?? ''); ?>">
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="floating-save-btn">
                <button type="submit" name="update_settings" class="btn btn-primary d-flex align-items-center px-4 py-3 shadow-premium rounded-pill fw-bold">
                    <i class="bi bi-save2-fill me-2 fs-5"></i> Save Changes
                </button>
            </div>
        </form>
    </div>

    <script>
        function changeLanguage(lang) {
            var activeTab = document.getElementById('activeTabInput').value;
            window.location.href = '?lang=' + lang + '&tab=' + activeTab;
        }

        // Multi-select File Manager logic
        let selectedFiles = new Set();

        function toggleImageSelection(event, card) {
            // Only trigger multi-select if Ctrl is held OR if we are already in multi-select mode
            if (event.ctrlKey || selectedFiles.size > 0) {
                const filename = card.dataset.filename;
                const indicator = card.querySelector('.selection-indicator');
                
                if (selectedFiles.has(filename)) {
                    selectedFiles.delete(filename);
                    card.classList.remove('border-primary', 'bg-primary', 'bg-opacity-10');
                    indicator.classList.add('d-none');
                } else {
                    selectedFiles.add(filename);
                    card.classList.add('border-primary', 'bg-primary', 'bg-opacity-10');
                    indicator.classList.remove('d-none');
                }
                
                updateManagementUI();
            }
        }

        function updateManagementUI() {
            const btn = document.getElementById('deleteSelectedBtn');
            const countSpan = document.getElementById('selectedCount');
            const bulkInput = document.getElementById('bulkDeleteInput');
            
            if (selectedFiles.size > 0) {
                btn.classList.remove('d-none');
                countSpan.textContent = selectedFiles.size;
                bulkInput.value = JSON.stringify(Array.from(selectedFiles));
            } else {
                btn.classList.add('d-none');
                bulkInput.value = '';
            }
        }

        function bulkDeleteFiles() {
            if (confirm(`Are you sure you want to delete ${selectedFiles.size} selected files?`)) {
                // Submit the parent form
                document.getElementById('settingsForm').submit();
            }
        }

        // Keep track of active tab
        document.addEventListener('DOMContentLoaded', function() {
            var tabs = document.querySelectorAll('button[data-bs-toggle="tab"]');
            tabs.forEach(function(tab) {
                tab.addEventListener('shown.bs.tab', function(event) {
                    var targetId = event.target.getAttribute('data-bs-target').replace('#', '');
                    var input = document.getElementById('activeTabInput');
                    if (input) input.value = targetId;
                });
            });
        });
    </script>

    <style>
        .selectable-image {
            cursor: pointer;
            transition: all 0.2s ease;
            border-color: transparent;
        }
        .selectable-image:hover {
            border-color: #dee2e6;
        }
        .selectable-image.border-primary {
            border-width: 2px !important;
        }
        .product-image-card img {
            transition: transform 0.3s ease;
        }
        .selectable-image:hover img {
            transform: scale(1.05);
        }
    </style>

<?php
$pageTitle = 'Settings';
$activeNav = 'settings';
$pageContent = ob_get_clean();
include 'layout.php';

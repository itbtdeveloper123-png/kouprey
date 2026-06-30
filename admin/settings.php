<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
session_start();
require_once '../app/Config/database.php';
require_once '../app/Config/settings.php';

// Database Self-Healing: Repair invalid setting categories and remove duplicates
try {
    $keyCategoryMap = [
        'contact_us' => 'policies',
        'privacy_policy' => 'policies',
        'terms_of_service' => 'policies',
        'social_banner_text' => 'social',
        'social_facebook' => 'social',
        'social_instagram' => 'social',
        'social_tiktok' => 'social',
        'social_telegram' => 'social',
        'company_logo' => 'contact',
        'company_email' => 'contact',
        'company_phone' => 'contact',
        'company_address' => 'contact',
        'working_hours' => 'contact',
        'google_maps_embed' => 'contact',
        'about_banner_title' => 'about',
        'about_banner_desc' => 'about',
        'about_story_title' => 'about',
        'about_story_desc' => 'about',
        'about_mission' => 'about',
        'about_vision' => 'about',
        'hero_title' => 'collections',
        'hero_description' => 'collections',
        'hero_background_image' => 'collections',
        'syrup_title' => 'collections',
        'syrup_description' => 'collections',
        'powder_title' => 'collections',
        'powder_description' => 'collections',
    ];

    $stmt = $pdo->query("SELECT id, setting_key, setting_value, language, category FROM settings WHERE category IS NULL OR category = '' OR category = 'grid' OR category = 'general'");
    $invalidRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($invalidRows as $row) {
        $key = $row['setting_key'];
        $lang = $row['language'];
        $val = $row['setting_value'];
        
        if (isset($keyCategoryMap[$key])) {
            $correctCat = $keyCategoryMap[$key];
            
            $checkStmt = $pdo->prepare("SELECT id FROM settings WHERE setting_key = ? AND language = ? AND category = ?");
            $checkStmt->execute([$key, $lang, $correctCat]);
            $existingRow = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existingRow) {
                $updateStmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE id = ?");
                $updateStmt->execute([$val, $existingRow['id']]);
                
                $deleteStmt = $pdo->prepare("DELETE FROM settings WHERE id = ?");
                $deleteStmt->execute([$row['id']]);
            } else {
                $updateStmt = $pdo->prepare("UPDATE settings SET category = ? WHERE id = ?");
                $updateStmt->execute([$correctCat, $row['id']]);
            }
        }
    }
} catch (Exception $e) {
    // Fail silently in case of table structure differences
}


if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

// Get current language from GET or default to 'en'
$currentLanguage = isset($_GET['lang']) ? $_GET['lang'] : 'en';
$supportedLanguages = ['en' => 'English', 'km' => 'Khmer'];
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'grid';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 1. Handle file uploads for both languages
    foreach (['en', 'km'] as $lang) {
        // Hero Background Image
        $heroField = 'hero_background_image_' . $lang;
        if (isset($_FILES[$heroField]) && $_FILES[$heroField]['error'] == 0) {
            $uploadDir = '../public/uploads/';
            $fileName = 'hero-bg-' . $lang . '-' . time() . '.' . pathinfo($_FILES[$heroField]['name'], PATHINFO_EXTENSION);
            $uploadPath = $uploadDir . $fileName;

            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (in_array($_FILES[$heroField]['type'], $allowedTypes)) {
                if (move_uploaded_file($_FILES[$heroField]['tmp_name'], $uploadPath)) {
                    require_once '../app/Config/image_utils.php';
                    $settings = getCompressionSettings('hero');
                    compressImage($uploadPath, $uploadPath, $settings['quality'], $settings['maxWidth'], $settings['maxHeight']);
                    
                    $_POST['hero_background_image_' . $lang] = '/kouprey/public/uploads/' . $fileName;
                }
            }
        }

        // Company Logo
        $logoField = 'company_logo_' . $lang;
        if (isset($_FILES[$logoField]) && $_FILES[$logoField]['error'] == 0) {
            $uploadDir = '../public/uploads/';
            $fileName = 'company-logo-' . $lang . '-' . time() . '.' . pathinfo($_FILES[$logoField]['name'], PATHINFO_EXTENSION);
            $uploadPath = $uploadDir . $fileName;

            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (in_array($_FILES[$logoField]['type'], $allowedTypes)) {
                if (move_uploaded_file($_FILES[$logoField]['tmp_name'], $uploadPath)) {
                    require_once '../app/Config/image_utils.php';
                    $settings = getCompressionSettings('logo');
                    compressImage($uploadPath, $uploadPath, $settings['quality'], $settings['maxWidth'], $settings['maxHeight']);
                    
                    $_POST['company_logo_' . $lang] = '/kouprey/public/uploads/' . $fileName;
                }
            }
        }
    }

    // 2. Handle image/file deletions
    foreach (['en', 'km'] as $lang) {
        if (isset($_POST['delete_hero_image_' . $lang])) {
            $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'hero_background_image' AND language = ?");
            $stmt->execute([$lang]);
            $currentImage = $stmt->fetchColumn();
            
            if ($currentImage) {
                $filePath = '../public/uploads/' . basename($currentImage);
                if (file_exists($filePath)) unlink($filePath);
            }
            $_POST['hero_background_image_' . $lang] = '';
        }

        if (isset($_POST['delete_company_logo_' . $lang])) {
            $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'company_logo' AND language = ?");
            $stmt->execute([$lang]);
            $currentLogo = $stmt->fetchColumn();
            
            if ($currentLogo) {
                $filePath = '../public/uploads/' . basename($currentLogo);
                if (file_exists($filePath)) unlink($filePath);
            }
            $_POST['company_logo_' . $lang] = '';
        }
    }

    // Handle individual hero file deletion
    if (isset($_POST['delete_hero_file'])) {
        $fileToDelete = $_POST['delete_hero_file'];
        $filePath = '../public/uploads/' . $fileToDelete;
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        $stmt = $pdo->prepare("UPDATE settings SET setting_value = '' WHERE setting_key = 'hero_background_image' AND setting_value LIKE ?");
        $stmt->execute(['%' . $fileToDelete]);
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
        $convertedCount = 0;
        $dbUpdates = 0;
        
        if ($images) {
            require_once '../app/Config/image_utils.php';
            $compressionSettings = getCompressionSettings('product');
            
            foreach ($images as $imagePath) {
                if (!is_file($imagePath)) continue;
                
                $pathInfo = pathinfo($imagePath);
                $newFileName = $pathInfo['filename'] . '.webp';
                $newFilePath = $targetDir . $newFileName;
                
                if (compressImage($imagePath, $newFilePath, $compressionSettings['quality'], $compressionSettings['maxWidth'], $compressionSettings['maxHeight'])) {
                    $convertedCount++;
                    $oldBasename = $pathInfo['basename'];
                    
                    $p1_old = '/kouprey/public/assets/images/products/' . $oldBasename;
                    $p1_new = '/kouprey/public/assets/images/products/' . $newFileName;
                    
                    $stmt = $pdo->prepare("UPDATE products SET image = ? WHERE image = ?");
                    $stmt->execute([$p1_new, $p1_old]);
                    $dbUpdates += $stmt->rowCount();
                    
                    $stmt = $pdo->prepare("UPDATE products SET image = ? WHERE image = ?");
                    $stmt->execute([$newFileName, $oldBasename]);
                    $dbUpdates += $stmt->rowCount();
                }
            }
        }
        $message = "Success! Converted $convertedCount images to WebP. Updated $dbUpdates database records.";
    }

    // Handle File manager replaces
    foreach ($_FILES as $key => $file) {
        if (strpos($key, 'file_manager_replace_') === 0 && $file['error'] == 0) {
            $originalNameWithUnderscores = substr($key, 21);
            $targetDir = '../public/assets/images/products/';
            $foundFile = null;
            
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

    // Fetch existing keys/categories/types to preserve them
    $existingSettings = [];
    try {
        $stmt = $pdo->query("SELECT setting_key, category, setting_type FROM settings");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $existingSettings[$row['setting_key']] = [
                'category' => $row['category'],
                'setting_type' => $row['setting_type']
            ];
        }
    } catch (Exception $e) {
        // fallback
    }

    // Save inputs
    foreach ($_POST as $key => $value) {
        if (in_array($key, ['update_settings', 'language', 'active_tab', 'delete_file_manager', 'upload_file_manager', 'convert_webp_all', 'delete_file_manager_bulk', 'delete_hero_file']) || strpos($key, 'file_manager_replace_') === 0) {
            continue;
        }

        // Check if language specific suffix _en or _km
        if (preg_match('/^(.*)_(en|km)$/', $key, $matches)) {
            $settingKey = $matches[1];
            $lang = $matches[2];
            
            if (strpos($settingKey, 'delete_') === 0) continue;
            
            $saveValue = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value;
            
            $catVal = (isset($existingSettings[$settingKey]) && !empty($existingSettings[$settingKey]['category'])) ? $existingSettings[$settingKey]['category'] : (($activeTab && $activeTab !== 'grid') ? $activeTab : 'general');
            $typeVal = (isset($existingSettings[$settingKey]) && !empty($existingSettings[$settingKey]['setting_type'])) ? $existingSettings[$settingKey]['setting_type'] : 'text';
            
            $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value, language, category, setting_type) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), category = VALUES(category), setting_type = VALUES(setting_type)");
            $stmt->execute([$settingKey, $saveValue, $lang, $catVal, $typeVal]);
        } else {
            // Global value - save for both languages
            $saveValue = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value;
            
            $catVal = (isset($existingSettings[$key]) && !empty($existingSettings[$key]['category'])) ? $existingSettings[$key]['category'] : (($activeTab && $activeTab !== 'grid') ? $activeTab : 'general');
            $typeVal = (isset($existingSettings[$key]) && !empty($existingSettings[$key]['setting_type'])) ? $existingSettings[$key]['setting_type'] : 'text';
            
            foreach (['en', 'km'] as $lang) {
                $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value, language, category, setting_type) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), category = VALUES(category), setting_type = VALUES(setting_type)");
                $stmt->execute([$key, $saveValue, $lang, $catVal, $typeVal]);
            }
        }
    }
    $message = "Settings updated successfully!";
}

// Fetch settings for both languages
$stmt = $pdo->query("SELECT * FROM settings ORDER BY category, setting_key");
$allSettingsRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Map settings by [category][setting_key]
$groupedSettings = [];
foreach ($allSettingsRaw as $s) {
    $cat = $s['category'];
    $key = $s['setting_key'];
    $lang = $s['language'];
    
    if (!isset($groupedSettings[$cat][$key])) {
        $groupedSettings[$cat][$key] = [
            'setting_key' => $key,
            'setting_type' => $s['setting_type'],
            'category' => $cat,
            'description' => $s['description'],
            'values' => ['en' => '', 'km' => '']
        ];
    }
    $groupedSettings[$cat][$key]['values'][$lang] = $s['setting_value'];
}

// Populate missing language fallbacks
foreach ($groupedSettings as $cat => &$keys) {
    foreach ($keys as $key => &$details) {
        if (empty($details['values']['km']) && !empty($details['values']['en'])) {
            $details['values']['km'] = $details['values']['en'];
        }
        if (empty($details['values']['en']) && !empty($details['values']['km'])) {
            $details['values']['en'] = $details['values']['km'];
        }
    }
}

// Helper function to get setting values easily
function getSettingVal($category, $key, $lang, $default = '') {
    global $groupedSettings;
    return isset($groupedSettings[$category][$key]['values'][$lang]) ? $groupedSettings[$category][$key]['values'][$lang] : $default;
}

// Fetch all categories for collections (using English categories as reference)
$stmt = $pdo->prepare("SELECT id, name, base_category_id FROM categories WHERE language = 'en'");
$stmt->execute();
$allCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);

$syrupBaseId = null;
$powderBaseId = null;
foreach ($allCategories as $ec) {
    if (stripos($ec['name'], 'Syrup') !== false) $syrupBaseId = $ec['base_category_id'];
    if (stripos($ec['name'], 'Powder') !== false) $powderBaseId = $ec['base_category_id'];
}

// Fetch all products
$stmt = $pdo->prepare("SELECT id, name, base_product_id, category_id FROM products WHERE language = 'en'");
$stmt->execute();
$allProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>
    <style>
    /* Workflow Grid Layout & Card Styles */
    .card-workflow-item {
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        border-radius: 20px !important;
        background: #ffffff;
        border: 1.5px solid #f1f5f9 !important;
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.02) !important;
    }
    .card-workflow-item:hover {
        transform: translateY(-5px);
        box-shadow: 0 20px 25px -5px rgba(79, 70, 229, 0.08), 0 10px 10px -5px rgba(79, 70, 229, 0.03) !important;
        border-color: rgba(79, 70, 229, 0.3) !important;
    }
    .card-workflow-item:hover .stat-icon-box {
        background: var(--primary) !important;
        color: #fff !important;
        transform: scale(1.08) rotate(5deg);
    }
    .card-workflow-item .stat-icon-box {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        width: 50px;
        height: 50px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
    }
    /* Hide inactive tab panes */
    #settingsTabContent>.tab-pane {
        display: none !important;
    }
    #settingsTabContent>.tab-pane.show.active {
        display: block !important;
    }
    </style>
    <div class="container-fluid py-4">
        <?php if (isset($message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i><?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Dynamic Header -->
        <div class="row mb-5 align-items-center">
            <div class="col-md-8 d-flex align-items-center">
                <button type="button" id="backToGridBtn" class="btn btn-light rounded-pill px-4 py-2 me-3 shadow-sm border d-none" onclick="showSettingsGrid()">
                    <i class="bi bi-arrow-left me-2"></i> Back to Settings
                </button>
                <div>
                    <h1 class="h2 fw-800 text-dark mb-1" id="settingsPageTitle">System Settings</h1>
                    <p class="text-secondary mb-0" id="settingsPageDesc">Configure your website behavior, content, and integrations.</p>
                </div>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
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
            'navigation' => ['icon' => 'bi-compass', 'title' => 'Navigation', 'description' => 'Navigation menu labels'],
            'file_manager' => ['icon' => 'bi-folder', 'title' => 'File Manager', 'description' => 'Manage uploaded product images']
        ];
        ?>

        <form id="settingsForm" method="POST" enctype="multipart/form-data" action="?lang=<?php echo $currentLanguage; ?>">
            <input type="hidden" name="active_tab" id="activeTabInput" value="<?php echo htmlspecialchars($activeTab); ?>">
            
            <!-- Cards Workflow Grid -->
            <div id="settingsGrid" class="mb-5 <?php echo ($activeTab !== 'grid') ? 'd-none' : ''; ?>">
                <div class="row g-4">
                    <?php foreach ($categories as $category => $categoryInfo): ?>
                    <?php if (!isset($groupedSettings[$category]) && !in_array($category, ['collections', 'contact', 'about', 'file_manager', 'policies', 'social'])) continue; ?>
                        <div class="col-xl-3 col-lg-4 col-md-6 animate-fade-in">
                            <div class="card h-100 border-0 card-workflow-item" onclick="switchSettingsTab(this, '<?php echo $category; ?>')">
                                <div class="card-body p-4 d-flex flex-column justify-content-between">
                                    <div>
                                        <div class="stat-icon-box bg-primary-soft text-primary mb-3">
                                            <i class="bi <?php echo $categoryInfo['icon']; ?>"></i>
                                        </div>
                                        <h5 class="fw-bold text-dark mb-2"><?php echo $categoryInfo['title']; ?></h5>
                                        <p class="text-secondary small mb-0" style="line-height: 1.4;"><?php echo $categoryInfo['description']; ?></p>
                                    </div>
                                    <div class="mt-4 pt-3 border-top d-flex align-items-center justify-content-between text-primary fw-bold small">
                                        <span>Configure Section</span>
                                        <i class="bi bi-arrow-right-short fs-5 transition-all"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
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
                                    $colSettings = [];
                                    $keys = [
                                        'syrup_collection_title', 'syrup_collection_description', 'syrup_collection_features', 'syrup_collection_products',
                                        'powder_selection_title', 'powder_selection_description', 'powder_selection_features', 'powder_selection_products'
                                    ];
                                    foreach ($keys as $k) {
                                        $colSettings[$k]['en'] = getSettingVal('collections', $k, 'en');
                                        $colSettings[$k]['km'] = getSettingVal('collections', $k, 'km');
                                        
                                        if (($k === 'syrup_collection_features' || $k === 'powder_selection_features')) {
                                            foreach (['en', 'km'] as $lang) {
                                                if (empty($colSettings[$k][$lang])) {
                                                    $prefix = ($k === 'syrup_collection_features') ? 'syrup_collection_feature_' : 'powder_selection_feature_';
                                                    $legacyFeatures = [];
                                                    for ($i = 1; $i <= 3; $i++) {
                                                        $v = getSettingVal('collections', $prefix . $i, $lang);
                                                        if ($v) $legacyFeatures[] = $v;
                                                    }
                                                    $colSettings[$k][$lang] = !empty($legacyFeatures) ? json_encode($legacyFeatures, JSON_UNESCAPED_UNICODE) : '[]';
                                                }
                                            }
                                        }
                                    }
                                    
                                    $syrupFeaturesEn = json_decode($colSettings['syrup_collection_features']['en'] ?? '[]', true) ?: [];
                                    $syrupFeaturesKm = json_decode($colSettings['syrup_collection_features']['km'] ?? '[]', true) ?: [];
                                    $powderFeaturesEn = json_decode($colSettings['powder_selection_features']['en'] ?? '[]', true) ?: [];
                                    $powderFeaturesKm = json_decode($colSettings['powder_selection_features']['km'] ?? '[]', true) ?: [];
                                    ?>
                                    <div class="row">
                                        <div class="col-md-6 mb-4">
                                            <div class="card h-100 border">
                                                <div class="card-header bg-light fw-bold"><i class="bi bi-droplet me-2"></i>Syrup Collection</div>
                                                <div class="card-body">
                                                    <div class="mb-3">
                                                        <label class="form-label fw-bold">Collection Title</label>
                                                        <div class="row">
                                                            <div class="col-md-6 mb-2">
                                                                <div class="input-group">
                                                                    <span class="input-group-text bg-light text-secondary small fw-bold">EN</span>
                                                                    <input type="text" name="syrup_collection_title_en" class="form-control" value="<?php echo htmlspecialchars($colSettings['syrup_collection_title']['en'] ?? ''); ?>" placeholder="Syrup Collection">
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6 mb-2">
                                                                <div class="input-group">
                                                                    <span class="input-group-text bg-light text-primary small fw-bold">KM</span>
                                                                    <input type="text" name="syrup_collection_title_km" class="form-control" value="<?php echo htmlspecialchars($colSettings['syrup_collection_title']['km'] ?? ''); ?>" placeholder="ស៊ីរ៉ូ">
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label fw-bold">Description</label>
                                                        <div class="row">
                                                            <div class="col-md-6 mb-2">
                                                                <div class="border rounded p-2 bg-light">
                                                                    <span class="badge bg-secondary mb-1">English</span>
                                                                    <textarea name="syrup_collection_description_en" class="form-control" rows="4" placeholder="Description in English..."><?php echo htmlspecialchars($colSettings['syrup_collection_description']['en'] ?? ''); ?></textarea>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6 mb-2">
                                                                <div class="border rounded p-2 bg-light">
                                                                    <span class="badge bg-primary mb-1">Khmer</span>
                                                                    <textarea name="syrup_collection_description_km" class="form-control" rows="4" placeholder="Description in Khmer..."><?php echo htmlspecialchars($colSettings['syrup_collection_description']['km'] ?? ''); ?></textarea>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label d-flex justify-content-between fw-bold">
                                                            Products to Display
                                                            <span class="badge bg-secondary">Total: <?php echo count(array_filter($allProducts, function($p) use ($allCategories, $syrupBaseId) {
                                                                foreach ($allCategories as $c) if ($c['id'] == $p['category_id'] && $c['base_category_id'] == $syrupBaseId) return true;
                                                                return false;
                                                            })); ?></span>
                                                        </label>
                                                        <div class="border rounded p-3 bg-white" style="max-height: 200px; overflow-y: auto;">
                                                            <?php 
                                                            $selectedProducts = json_decode($colSettings['syrup_collection_products']['en'] ?? '[]', true) ?: [];
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
                                                    </div>

                                                    <div class="mb-3">
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <label class="form-label d-flex justify-content-between fw-bold">
                                                                    Features (EN)
                                                                    <button type="button" class="btn btn-xs btn-outline-primary py-0" onclick="addFeature('syrup', 'en')"><i class="bi bi-plus"></i></button>
                                                                </label>
                                                                <div id="syrup-features-container-en">
                                                                    <?php foreach ($syrupFeaturesEn as $feat): ?>
                                                                        <div class="input-group mb-2 feature-item">
                                                                            <input type="text" name="syrup_collection_features_en[]" class="form-control" value="<?php echo htmlspecialchars($feat); ?>">
                                                                            <button class="btn btn-outline-danger" type="button" onclick="this.parentElement.remove()"><i class="bi bi-trash"></i></button>
                                                                        </div>
                                                                    <?php endforeach; ?>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label d-flex justify-content-between fw-bold">
                                                                    Features (KM)
                                                                    <button type="button" class="btn btn-xs btn-outline-primary py-0" onclick="addFeature('syrup', 'km')"><i class="bi bi-plus"></i></button>
                                                                </label>
                                                                <div id="syrup-features-container-km">
                                                                    <?php foreach ($syrupFeaturesKm as $feat): ?>
                                                                        <div class="input-group mb-2 feature-item">
                                                                            <input type="text" name="syrup_collection_features_km[]" class="form-control" value="<?php echo htmlspecialchars($feat); ?>">
                                                                            <button class="btn btn-outline-danger" type="button" onclick="this.parentElement.remove()"><i class="bi bi-trash"></i></button>
                                                                        </div>
                                                                    <?php endforeach; ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-md-6 mb-4">
                                            <div class="card h-100 border">
                                                <div class="card-header bg-light fw-bold"><i class="bi bi-snow me-2"></i>Powder Selection</div>
                                                <div class="card-body">
                                                    <div class="mb-3">
                                                        <label class="form-label fw-bold">Selection Title</label>
                                                        <div class="row">
                                                            <div class="col-md-6 mb-2">
                                                                <div class="input-group">
                                                                    <span class="input-group-text bg-light text-secondary small fw-bold">EN</span>
                                                                    <input type="text" name="powder_selection_title_en" class="form-control" value="<?php echo htmlspecialchars($colSettings['powder_selection_title']['en'] ?? ''); ?>" placeholder="Powder Selection">
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6 mb-2">
                                                                <div class="input-group">
                                                                    <span class="input-group-text bg-light text-primary small fw-bold">KM</span>
                                                                    <input type="text" name="powder_selection_title_km" class="form-control" value="<?php echo htmlspecialchars($colSettings['powder_selection_title']['km'] ?? ''); ?>" placeholder="ម្សៅ">
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label fw-bold">Description</label>
                                                        <div class="row">
                                                            <div class="col-md-6 mb-2">
                                                                <div class="border rounded p-2 bg-light">
                                                                    <span class="badge bg-secondary mb-1">English</span>
                                                                    <textarea name="powder_selection_description_en" class="form-control" rows="4" placeholder="Description in English..."><?php echo htmlspecialchars($colSettings['powder_selection_description']['en'] ?? ''); ?></textarea>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6 mb-2">
                                                                <div class="border rounded p-2 bg-light">
                                                                    <span class="badge bg-primary mb-1">Khmer</span>
                                                                    <textarea name="powder_selection_description_km" class="form-control" rows="4" placeholder="Description in Khmer..."><?php echo htmlspecialchars($colSettings['powder_selection_description']['km'] ?? ''); ?></textarea>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label d-flex justify-content-between fw-bold">
                                                            Products to Display
                                                            <span class="badge bg-secondary">Total: <?php echo count(array_filter($allProducts, function($p) use ($allCategories, $powderBaseId) {
                                                                foreach ($allCategories as $c) if ($c['id'] == $p['category_id'] && $c['base_category_id'] == $powderBaseId) return true;
                                                                return false;
                                                            })); ?></span>
                                                        </label>
                                                        <div class="border rounded p-3 bg-white" style="max-height: 200px; overflow-y: auto;">
                                                            <?php 
                                                            $selectedProducts = json_decode($colSettings['powder_selection_products']['en'] ?? '[]', true) ?: [];
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
                                                    </div>

                                                    <div class="mb-3">
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <label class="form-label d-flex justify-content-between fw-bold">
                                                                    Features (EN)
                                                                    <button type="button" class="btn btn-xs btn-outline-primary py-0" onclick="addFeature('powder', 'en')"><i class="bi bi-plus"></i></button>
                                                                </label>
                                                                <div id="powder-features-container-en">
                                                                    <?php foreach ($powderFeaturesEn as $feat): ?>
                                                                        <div class="input-group mb-2 feature-item">
                                                                            <input type="text" name="powder_selection_features_en[]" class="form-control" value="<?php echo htmlspecialchars($feat); ?>">
                                                                            <button class="btn btn-outline-danger" type="button" onclick="this.parentElement.remove()"><i class="bi bi-trash"></i></button>
                                                                        </div>
                                                                    <?php endforeach; ?>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label d-flex justify-content-between fw-bold">
                                                                    Features (KM)
                                                                    <button type="button" class="btn btn-xs btn-outline-primary py-0" onclick="addFeature('powder', 'km')"><i class="bi bi-plus"></i></button>
                                                                </label>
                                                                <div id="powder-features-container-km">
                                                                    <?php foreach ($powderFeaturesKm as $feat): ?>
                                                                        <div class="input-group mb-2 feature-item">
                                                                            <input type="text" name="powder_selection_features_km[]" class="form-control" value="<?php echo htmlspecialchars($feat); ?>">
                                                                            <button class="btn btn-outline-danger" type="button" onclick="this.parentElement.remove()"><i class="bi bi-trash"></i></button>
                                                                        </div>
                                                                    <?php endforeach; ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <script>
                                        function addFeature(type, lang) {
                                            const container = document.getElementById(type + '-features-container-' + lang);
                                            const div = document.createElement('div');
                                            div.className = 'input-group mb-2 feature-item';
                                            div.innerHTML = `
                                                <input type="text" name="${type === 'syrup' ? 'syrup_collection_features' : 'powder_selection_features'}_${lang}[]" class="form-control" placeholder="New feature...">
                                                <button class="btn btn-outline-danger" type="button" onclick="this.parentElement.remove()"><i class="bi bi-trash"></i></button>
                                            `;
                                            container.appendChild(div);
                                            div.querySelector('input').focus();
                                        }
                                    </script>

                                <?php elseif ($category === 'about'): ?>
                                    <?php
                                    $aboutSettings = [];
                                    $keys = [
                                        'about_title', 'about_content',
                                        'about_purpose_title', 'about_purpose_content',
                                        'about_mission_title', 'about_mission_content'
                                    ];
                                    foreach ($keys as $k) {
                                        $aboutSettings[$k]['en'] = getSettingVal('about', $k, 'en');
                                        $aboutSettings[$k]['km'] = getSettingVal('about', $k, 'km');
                                    }
                                    ?>
                                    <div class="row">
                                        <!-- About Header/Intro -->
                                        <div class="col-12 mb-4">
                                            <div class="card bg-light border-0">
                                                <div class="card-body">
                                                    <h5 class="fw-bold mb-3"><i class="bi bi-info-circle me-2"></i>About Intro</h5>
                                                    <div class="mb-3">
                                                        <label class="form-label fw-bold">Page Title</label>
                                                        <div class="row">
                                                            <div class="col-md-6 mb-2">
                                                                <div class="input-group">
                                                                    <span class="input-group-text bg-light text-secondary small fw-bold">EN</span>
                                                                    <input type="text" name="about_title_en" class="form-control" value="<?php echo htmlspecialchars($aboutSettings['about_title']['en'] ?? ''); ?>" placeholder="e.g. About KouPrey Coffee">
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6 mb-2">
                                                                <div class="input-group">
                                                                    <span class="input-group-text bg-light text-primary small fw-bold">KM</span>
                                                                    <input type="text" name="about_title_km" class="form-control" value="<?php echo htmlspecialchars($aboutSettings['about_title']['km'] ?? ''); ?>" placeholder="e.g. អំពីកាហ្វេគោកព្រៃ">
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label fw-bold">Intro Content</label>
                                                        <div class="row">
                                                            <div class="col-md-6 mb-2">
                                                                <div class="border rounded p-2 bg-white">
                                                                    <span class="badge bg-secondary mb-1">English</span>
                                                                    <textarea name="about_content_en" class="form-control" rows="4" placeholder="Main introduction text in English..."><?php echo htmlspecialchars($aboutSettings['about_content']['en'] ?? ''); ?></textarea>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6 mb-2">
                                                                <div class="border rounded p-2 bg-white">
                                                                    <span class="badge bg-primary mb-1">Khmer</span>
                                                                    <textarea name="about_content_km" class="form-control" rows="4" placeholder="Main introduction text in Khmer..."><?php echo htmlspecialchars($aboutSettings['about_content']['km'] ?? ''); ?></textarea>
                                                                </div>
                                                            </div>
                                                        </div>
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
                                                        <label class="form-label fw-bold">Section Title</label>
                                                        <div class="row">
                                                            <div class="col-md-6 mb-2">
                                                                <div class="input-group">
                                                                    <span class="input-group-text bg-light text-secondary small fw-bold">EN</span>
                                                                    <input type="text" name="about_purpose_title_en" class="form-control" value="<?php echo htmlspecialchars($aboutSettings['about_purpose_title']['en'] ?? ''); ?>" placeholder="Our Purpose">
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6 mb-2">
                                                                <div class="input-group">
                                                                    <span class="input-group-text bg-light text-primary small fw-bold">KM</span>
                                                                    <input type="text" name="about_purpose_title_km" class="form-control" value="<?php echo htmlspecialchars($aboutSettings['about_purpose_title']['km'] ?? ''); ?>" placeholder="គោលបំណងរបស់យើង">
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label fw-bold">Purpose Content</label>
                                                        <div class="row">
                                                            <div class="col-md-12 mb-2">
                                                                <div class="border rounded p-2 bg-light mb-2">
                                                                    <span class="badge bg-secondary mb-1">English</span>
                                                                    <textarea name="about_purpose_content_en" class="form-control" rows="4" placeholder="Purpose details in English..."><?php echo htmlspecialchars($aboutSettings['about_purpose_content']['en'] ?? ''); ?></textarea>
                                                                </div>
                                                                <div class="border rounded p-2 bg-light">
                                                                    <span class="badge bg-primary mb-1">Khmer</span>
                                                                    <textarea name="about_purpose_content_km" class="form-control" rows="4" placeholder="Purpose details in Khmer..."><?php echo htmlspecialchars($aboutSettings['about_purpose_content']['km'] ?? ''); ?></textarea>
                                                                </div>
                                                            </div>
                                                        </div>
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
                                                        <label class="form-label fw-bold">Section Title</label>
                                                        <div class="row">
                                                            <div class="col-md-6 mb-2">
                                                                <div class="input-group">
                                                                    <span class="input-group-text bg-light text-secondary small fw-bold">EN</span>
                                                                    <input type="text" name="about_mission_title_en" class="form-control" value="<?php echo htmlspecialchars($aboutSettings['about_mission_title']['en'] ?? ''); ?>" placeholder="Our Mission">
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6 mb-2">
                                                                <div class="input-group">
                                                                    <span class="input-group-text bg-light text-primary small fw-bold">KM</span>
                                                                    <input type="text" name="about_mission_title_km" class="form-control" value="<?php echo htmlspecialchars($aboutSettings['about_mission_title']['km'] ?? ''); ?>" placeholder="បេសកកម្មរបស់យើង">
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label fw-bold">Mission Content</label>
                                                        <div class="row">
                                                            <div class="col-md-12 mb-2">
                                                                <div class="border rounded p-2 bg-light mb-2">
                                                                    <span class="badge bg-secondary mb-1">English</span>
                                                                    <textarea name="about_mission_content_en" class="form-control" rows="4" placeholder="Mission details in English..."><?php echo htmlspecialchars($aboutSettings['about_mission_content']['en'] ?? ''); ?></textarea>
                                                                </div>
                                                                <div class="border rounded p-2 bg-light">
                                                                    <span class="badge bg-primary mb-1">Khmer</span>
                                                                    <textarea name="about_mission_content_km" class="form-control" rows="4" placeholder="Mission details in Khmer..."><?php echo htmlspecialchars($aboutSettings['about_mission_content']['km'] ?? ''); ?></textarea>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                <?php elseif ($category === 'contact'): ?>
                                    <?php
                                    $contactSettings = [];
                                    $keys = [
                                        'company_address', 'company_phone', 'company_email', 'company_hours'
                                    ];
                                    foreach ($keys as $k) {
                                        $contactSettings[$k]['en'] = getSettingVal('contact', $k, 'en');
                                        $contactSettings[$k]['km'] = getSettingVal('contact', $k, 'km');
                                    }
                                    ?>
                                    <div class="row">
                                        <div class="col-md-6 mb-4">
                                            <div class="card h-100 border">
                                                <div class="card-header bg-white fw-bold"><i class="bi bi-geo-alt me-2"></i>Address & Contact</div>
                                                <div class="card-body">
                                                    <div class="mb-3">
                                                        <label class="form-label fw-bold">Company Address</label>
                                                        <div class="border rounded p-2 bg-light mb-2">
                                                            <span class="badge bg-secondary mb-1">English</span>
                                                            <textarea name="company_address_en" class="form-control" rows="3" placeholder="Enter address in English..."><?php echo htmlspecialchars($contactSettings['company_address']['en'] ?? ''); ?></textarea>
                                                        </div>
                                                        <div class="border rounded p-2 bg-light">
                                                            <span class="badge bg-primary mb-1">Khmer</span>
                                                            <textarea name="company_address_km" class="form-control" rows="3" placeholder="Enter address in Khmer..."><?php echo htmlspecialchars($contactSettings['company_address']['km'] ?? ''); ?></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label fw-bold">Contact Phone</label>
                                                        <div class="row">
                                                            <div class="col-md-6 mb-2">
                                                                <div class="input-group">
                                                                    <span class="input-group-text bg-light text-secondary small fw-bold">EN</span>
                                                                    <input type="text" name="company_phone_en" class="form-control" value="<?php echo htmlspecialchars($contactSettings['company_phone']['en'] ?? ''); ?>" placeholder="+855 12 345 678">
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6 mb-2">
                                                                <div class="input-group">
                                                                    <span class="input-group-text bg-light text-primary small fw-bold">KM</span>
                                                                    <input type="text" name="company_phone_km" class="form-control" value="<?php echo htmlspecialchars($contactSettings['company_phone']['km'] ?? ''); ?>" placeholder="+855 12 345 678">
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-md-6 mb-4">
                                            <div class="card h-100 border">
                                                <div class="card-header bg-white fw-bold"><i class="bi bi-envelope me-2"></i>Email & Business Hours</div>
                                                <div class="card-body">
                                                    <div class="mb-3">
                                                        <label class="form-label fw-bold">Contact Email</label>
                                                        <div class="row">
                                                            <div class="col-md-6 mb-2">
                                                                <div class="input-group">
                                                                    <span class="input-group-text bg-light text-secondary small fw-bold">EN</span>
                                                                    <input type="email" name="company_email_en" class="form-control" value="<?php echo htmlspecialchars($contactSettings['company_email']['en'] ?? ''); ?>" placeholder="info@kouprey.com">
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6 mb-2">
                                                                <div class="input-group">
                                                                    <span class="input-group-text bg-light text-primary small fw-bold">KM</span>
                                                                    <input type="email" name="company_email_km" class="form-control" value="<?php echo htmlspecialchars($contactSettings['company_email']['km'] ?? ''); ?>" placeholder="info@kouprey.com">
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label fw-bold">Business Hours</label>
                                                        <div class="row">
                                                            <div class="col-md-6 mb-2">
                                                                <div class="input-group">
                                                                    <span class="input-group-text bg-light text-secondary small fw-bold">EN</span>
                                                                    <input type="text" name="company_hours_en" class="form-control" value="<?php echo htmlspecialchars($contactSettings['company_hours']['en'] ?? ''); ?>" placeholder="e.g. Mon-Sun: 7:30AM-6:00PM">
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6 mb-2">
                                                                <div class="input-group">
                                                                    <span class="input-group-text bg-light text-primary small fw-bold">KM</span>
                                                                    <input type="text" name="company_hours_km" class="form-control" value="<?php echo htmlspecialchars($contactSettings['company_hours']['km'] ?? ''); ?>" placeholder="e.g. ចន្ទ-អាទិត្យ: 7:30ព្រឹក-6:00ល្ងាច">
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
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
                                    $socialBannerTextEn = getSettingVal('social', 'social_banner_text', 'en');
                                    $socialBannerTextKm = getSettingVal('social', 'social_banner_text', 'km');
                                    
                                    $fbUrl = getSettingVal('social', 'social_facebook', 'en');
                                    $igUrl = getSettingVal('social', 'social_instagram', 'en');
                                    $ttUrl = getSettingVal('social', 'social_tiktok', 'en');
                                    $tgUrl = getSettingVal('social', 'social_telegram', 'en');
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
                                                    <div class="row g-0 border-bottom">
                                                        <!-- English Editor -->
                                                        <div class="col-md-6 border-end">
                                                            <div class="bg-light p-2 border-bottom fw-bold text-secondary small d-flex justify-content-between align-items-center">
                                                                <span>English Banner</span>
                                                                <div class="rte-toolbar d-inline-block p-0 border-0" data-editor="social_banner_editor_en">
                                                                    <button type="button" data-cmd="bold" title="Bold"><b>B</b></button>
                                                                    <button type="button" data-cmd="italic" title="Italic"><i>I</i></button>
                                                                    <button type="button" data-cmd="underline" title="Underline"><u>U</u></button>
                                                                    <span class="rte-sep"></span>
                                                                    <button type="button" class="rte-emoji-btn" title="Insert Icon">😊</button>
                                                                </div>
                                                            </div>
                                                            <div class="rte-emoji-panel" data-editor="social_banner_editor_en">
                                                                <?php
                                                                $emojiList = ['📌','🔴','🟢','🔵','⭐','✅','💡','🔥','🎯','📝','💬','📧','📞','📍','🌐','💻','📱','🛒','📦','💰','🎉','❤️','👍','➡️','⬅️','•'];
                                                                foreach ($emojiList as $emoji) {
                                                                    echo '<span onclick="insertEmoji(\'social_banner_editor_en\', \'' . $emoji . '\')">' . $emoji . '</span>';
                                                                }
                                                                ?>
                                                            </div>
                                                            <div id="social_banner_editor_en" class="rte-editor" contenteditable="true" data-textarea="social_banner_text_en"><?php echo $socialBannerTextEn; ?></div>
                                                            <textarea name="social_banner_text_en" id="social_banner_text_en_textarea" style="display:none;"><?php echo htmlspecialchars($socialBannerTextEn); ?></textarea>
                                                        </div>
                                                        <!-- Khmer Editor -->
                                                        <div class="col-md-6">
                                                            <div class="bg-light p-2 border-bottom fw-bold text-primary small d-flex justify-content-between align-items-center">
                                                                <span>Khmer Banner</span>
                                                                <div class="rte-toolbar d-inline-block p-0 border-0" data-editor="social_banner_editor_km">
                                                                    <button type="button" data-cmd="bold" title="Bold"><b>B</b></button>
                                                                    <button type="button" data-cmd="italic" title="Italic"><i>I</i></button>
                                                                    <button type="button" data-cmd="underline" title="Underline"><u>U</u></button>
                                                                    <span class="rte-sep"></span>
                                                                    <button type="button" class="rte-emoji-btn" title="Insert Icon">😊</button>
                                                                </div>
                                                            </div>
                                                            <div class="rte-emoji-panel" data-editor="social_banner_editor_km">
                                                                <?php
                                                                foreach ($emojiList as $emoji) {
                                                                    echo '<span onclick="insertEmoji(\'social_banner_editor_km\', \'' . $emoji . '\')">' . $emoji . '</span>';
                                                                }
                                                                ?>
                                                            </div>
                                                            <div id="social_banner_editor_km" class="rte-editor" contenteditable="true" data-textarea="social_banner_text_km"><?php echo $socialBannerTextKm; ?></div>
                                                            <textarea name="social_banner_text_km" id="social_banner_text_km_textarea" style="display:none;"><?php echo htmlspecialchars($socialBannerTextKm); ?></textarea>
                                                        </div>
                                                    </div>
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
                                                            <?php echo $socialBannerTextEn ?: '<span style="opacity:0.5;">Banner text will appear here</span>'; ?>
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
                                        var bannerEditor = document.getElementById('social_banner_editor_en');
                                        var bannerPreview = document.getElementById('socialBannerPreview');
                                        if (bannerEditor && bannerPreview) {
                                            var updateBannerPreview = function() {
                                                var html = bannerEditor.innerHTML.trim();
                                                bannerPreview.innerHTML = html || '<span style="opacity:0.5;">Banner text will appear here</span>';
                                            };
                                            bannerEditor.addEventListener('input', updateBannerPreview);
                                            bannerEditor.addEventListener('blur', updateBannerPreview);
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
                                                $contentEn = getSettingVal('policies', $pf['key'], 'en');
                                                $contentKm = getSettingVal('policies', $pf['key'], 'km');
                                            ?>
                                            <div class="card border mb-4">
                                                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <span class="badge <?php echo $pf['badge']; ?> me-2"><?php echo $pf['label']; ?></span>
                                                        <small class="text-muted"><?php echo $pf['label']; ?> content (Side-by-side EN & KM)</small>
                                                    </div>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary preview-btn" data-target="<?php echo $pf['key']; ?>_en">
                                                        <i class="bi bi-eye"></i> Preview (EN)
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-primary preview-btn ms-2" data-target="<?php echo $pf['key']; ?>_km">
                                                        <i class="bi bi-eye"></i> Preview (KM)
                                                    </button>
                                                </div>
                                                <div class="card-body p-0">
                                                    <div class="row g-0">
                                                        <!-- English Editor -->
                                                        <div class="col-md-6 border-end">
                                                            <div class="bg-light p-2 border-bottom fw-bold text-secondary small d-flex justify-content-between align-items-center">
                                                                <span>English Version</span>
                                                                <div class="rte-toolbar d-inline-block p-0 border-0" data-editor="<?php echo $pf['key']; ?>_en_editor">
                                                                    <button type="button" data-cmd="bold" title="Bold"><b>B</b></button>
                                                                    <button type="button" data-cmd="italic" title="Italic"><i>I</i></button>
                                                                    <button type="button" data-cmd="underline" title="Underline"><u>U</u></button>
                                                                    <span class="rte-sep"></span>
                                                                    <button type="button" class="rte-emoji-btn" title="Emoji">😊</button>
                                                                </div>
                                                            </div>
                                                            <div class="rte-emoji-panel" data-editor="<?php echo $pf['key']; ?>_en_editor">
                                                                <?php
                                                                $emojiList = ['📌','🔴','🟢','🔵','⭐','✅','💡','🔥','🎯','📝','💬','📧','📞','📍','🌐','💻','📱','🛒','📦','💰','🎉','❤️','👍','➡️','⬅️','•'];
                                                                foreach ($emojiList as $emoji) {
                                                                    echo '<span onclick="insertEmoji(\'' . $pf['key'] . '_en_editor\', \'' . $emoji . '\')">' . $emoji . '</span>';
                                                                }
                                                                ?>
                                                            </div>
                                                            <div id="<?php echo $pf['key']; ?>_en_editor" class="rte-editor" contenteditable="true" data-textarea="<?php echo $pf['key']; ?>_en" style="min-height: 180px;"><?php echo $contentEn; ?></div>
                                                            <textarea name="<?php echo $pf['key']; ?>_en" id="<?php echo $pf['key']; ?>_en_textarea" style="display:none;"><?php echo htmlspecialchars($contentEn); ?></textarea>
                                                        </div>
                                                        <!-- Khmer Editor -->
                                                        <div class="col-md-6">
                                                            <div class="bg-light p-2 border-bottom fw-bold text-primary small d-flex justify-content-between align-items-center">
                                                                <span>Khmer Version</span>
                                                                <div class="rte-toolbar d-inline-block p-0 border-0" data-editor="<?php echo $pf['key']; ?>_km_editor">
                                                                    <button type="button" data-cmd="bold" title="Bold"><b>B</b></button>
                                                                    <button type="button" data-cmd="italic" title="Italic"><i>I</i></button>
                                                                    <button type="button" data-cmd="underline" title="Underline"><u>U</u></button>
                                                                    <span class="rte-sep"></span>
                                                                    <button type="button" class="rte-emoji-btn" title="Emoji">😊</button>
                                                                </div>
                                                            </div>
                                                            <div class="rte-emoji-panel" data-editor="<?php echo $pf['key']; ?>_km_editor">
                                                                <?php
                                                                foreach ($emojiList as $emoji) {
                                                                    echo '<span onclick="insertEmoji(\'' . $pf['key'] . '_km_editor\', \'' . $emoji . '\')">' . $emoji . '</span>';
                                                                }
                                                                ?>
                                                            </div>
                                                            <div id="<?php echo $pf['key']; ?>_km_editor" class="rte-editor" contenteditable="true" data-textarea="<?php echo $pf['key']; ?>_km" style="min-height: 180px;"><?php echo $contentKm; ?></div>
                                                            <textarea name="<?php echo $pf['key']; ?>_km" id="<?php echo $pf['key']; ?>_km_textarea" style="display:none;"><?php echo htmlspecialchars($contentKm); ?></textarea>
                                                        </div>
                                                    </div>
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

                                            var formatSelect = toolbar.querySelector('select[data-cmd="formatBlock"]');
                                            if (formatSelect) {
                                                formatSelect.addEventListener('change', function() {
                                                    var editor = document.getElementById(editorId);
                                                    editor.focus();
                                                    document.execCommand('formatBlock', false, '<' + this.value + '>');
                                                    syncTextarea(editorId);
                                                });
                                            }

                                            var colorInput = toolbar.querySelector('input[type="color"]');
                                            if (colorInput) {
                                                colorInput.addEventListener('input', function() {
                                                    var editor = document.getElementById(editorId);
                                                    editor.focus();
                                                    document.execCommand('foreColor', false, this.value);
                                                    syncTextarea(editorId);
                                                });
                                            }
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
                                            
                                            var cleanTarget = target.replace(/_(en|km)$/, '');
                                            var langLabel = target.endsWith('_km') ? 'KM' : 'EN';
                                            
                                            var previewLabel = document.getElementById('previewLabel');
                                            if (previewLabel) {
                                                var baseLabel = labelMap[cleanTarget] || cleanTarget;
                                                previewLabel.textContent = baseLabel + ' (' + langLabel + ')';
                                            }

                                            var previewFrame = document.getElementById('previewFrame');
                                            if (!previewFrame) return;
                                            previewFrame.setAttribute('data-active', target);

                                            var borderColor = cleanTarget === 'privacy_policy' ? '#3B82F6' : (cleanTarget === 'terms_of_service' ? '#10B981' : '#F59E0B');
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
                                    });
                                    </script>
                                <?php else: ?>
                                    <div class="row">
                                        <?php foreach ($groupedSettings[$category] as $settingKey => $setting): ?>
                                            <div class="col-md-12 mb-4">
                                                <div class="setting-item border rounded p-4 bg-white shadow-sm">
                                                    <label class="setting-label fw-bold text-dark fs-6 mb-1">
                                                        <?php 
                                                        $label = ucfirst(str_replace('_', ' ', $setting['setting_key']));
                                                        $desc = isset($setting['description']) ? trim((string)$setting['description']) : '';
                                                        echo htmlspecialchars($label);
                                                        ?>
                                                    </label>
                                                    <?php if ($desc !== '' && strcasecmp($desc, $label) !== 0): ?>
                                                        <div class="setting-description text-muted small mb-3"><?php echo htmlspecialchars($desc); ?></div>
                                                    <?php endif; ?>

                                                    <?php if ($setting['setting_key'] === 'hero_background_image'): ?>
                                                        <div class="row g-4">
                                                            <?php foreach (['en' => 'English', 'km' => 'Khmer'] as $lang => $langLabel): 
                                                                $val = $setting['values'][$lang] ?? '';
                                                            ?>
                                                                <div class="col-md-6 border-end">
                                                                    <span class="badge bg-<?php echo $lang === 'en' ? 'secondary' : 'primary'; ?> mb-2"><?php echo $langLabel; ?> Hero Background</span>
                                                                    <input type="file" class="form-control mb-2" name="hero_background_image_<?php echo $lang; ?>" accept="image/*">
                                                                    
                                                                    <?php if (!empty($val)): ?>
                                                                        <div class="mt-2 card p-2 bg-light">
                                                                            <small class="text-muted">Current <?php echo $langLabel; ?> Banner:</small>
                                                                            <img src="<?php echo htmlspecialchars($val); ?>" class="img-fluid rounded mt-1" style="max-height: 120px; object-fit: cover; width: 100%;">
                                                                            <div class="mt-2 text-end">
                                                                                <button type="submit" name="delete_hero_image_<?php echo $lang; ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('Are you sure you want to delete this hero image?')">
                                                                                    <i class="bi bi-trash"></i> Remove Image
                                                                                </button>
                                                                            </div>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>

                                                    <?php elseif ($setting['setting_key'] === 'company_logo'): ?>
                                                        <div class="row g-4">
                                                            <?php foreach (['en' => 'English', 'km' => 'Khmer'] as $lang => $langLabel): 
                                                                $val = $setting['values'][$lang] ?? '';
                                                            ?>
                                                                <div class="col-md-6 border-end">
                                                                    <span class="badge bg-<?php echo $lang === 'en' ? 'secondary' : 'primary'; ?> mb-2"><?php echo $langLabel; ?> Logo</span>
                                                                    <input type="file" class="form-control mb-2" name="company_logo_<?php echo $lang; ?>" accept="image/*">
                                                                    
                                                                    <?php if (!empty($val)): ?>
                                                                        <div class="mt-2 card p-2 bg-light">
                                                                            <small class="text-muted">Current <?php echo $langLabel; ?> Logo:</small><br>
                                                                            <img src="<?php echo htmlspecialchars($val); ?>" style="max-width: 150px; max-height: 60px; object-fit: contain;" class="rounded border p-1 bg-white">
                                                                            <div class="mt-2 text-end">
                                                                                <button type="submit" name="delete_company_logo_<?php echo $lang; ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('Are you sure you want to delete this company logo?')">
                                                                                    <i class="bi bi-trash"></i> Remove Logo
                                                                                </button>
                                                                            </div>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>

                                                    <?php elseif ($setting['setting_type'] === 'textarea'): ?>
                                                        <div class="row">
                                                            <div class="col-md-6 mb-2">
                                                                <div class="border rounded p-2 bg-light">
                                                                    <span class="badge bg-secondary mb-1">English</span>
                                                                    <textarea class="form-control" name="<?php echo $setting['setting_key']; ?>_en" rows="4"><?php echo htmlspecialchars($setting['values']['en'] ?? ''); ?></textarea>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6 mb-2">
                                                                <div class="border rounded p-2 bg-light">
                                                                    <span class="badge bg-primary mb-1">Khmer</span>
                                                                    <textarea class="form-control" name="<?php echo $setting['setting_key']; ?>_km" rows="4"><?php echo htmlspecialchars($setting['values']['km'] ?? ''); ?></textarea>
                                                                </div>
                                                            </div>
                                                        </div>

                                                    <?php elseif ($setting['setting_type'] === 'boolean'): ?>
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <div class="form-check form-switch bg-light p-3 rounded border">
                                                                    <input class="form-check-input ms-0 me-2" type="checkbox" name="<?php echo $setting['setting_key']; ?>_en" value="1" <?php echo ($setting['values']['en'] == '1') ? 'checked' : ''; ?>>
                                                                    <label class="form-check-label fw-bold text-secondary">English Enabled</label>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <div class="form-check form-switch bg-light p-3 rounded border">
                                                                    <input class="form-check-input ms-0 me-2" type="checkbox" name="<?php echo $setting['setting_key']; ?>_km" value="1" <?php echo ($setting['values']['km'] == '1') ? 'checked' : ''; ?>>
                                                                    <label class="form-check-label fw-bold text-primary">Khmer Enabled</label>
                                                                </div>
                                                            </div>
                                                        </div>

                                                    <?php elseif ($setting['setting_type'] === 'number'): ?>
                                                        <div class="row">
                                                            <div class="col-md-6 mb-2">
                                                                <div class="input-group">
                                                                    <span class="input-group-text bg-light text-secondary small fw-bold">EN</span>
                                                                    <input type="number" class="form-control" name="<?php echo $setting['setting_key']; ?>_en" value="<?php echo htmlspecialchars($setting['values']['en'] ?? ''); ?>">
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6 mb-2">
                                                                <div class="input-group">
                                                                    <span class="input-group-text bg-light text-primary small fw-bold">KM</span>
                                                                    <input type="number" class="form-control" name="<?php echo $setting['setting_key']; ?>_km" value="<?php echo htmlspecialchars($setting['values']['km'] ?? ''); ?>">
                                                                </div>
                                                            </div>
                                                        </div>

                                                    <?php elseif ($setting['setting_type'] === 'email'): ?>
                                                        <div class="row">
                                                            <div class="col-md-6 mb-2">
                                                                <div class="input-group">
                                                                    <span class="input-group-text bg-light text-secondary small fw-bold">EN</span>
                                                                    <input type="email" class="form-control" name="<?php echo $setting['setting_key']; ?>_en" value="<?php echo htmlspecialchars($setting['values']['en'] ?? ''); ?>">
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6 mb-2">
                                                                <div class="input-group">
                                                                    <span class="input-group-text bg-light text-primary small fw-bold">KM</span>
                                                                    <input type="email" class="form-control" name="<?php echo $setting['setting_key']; ?>_km" value="<?php echo htmlspecialchars($setting['values']['km'] ?? ''); ?>">
                                                                </div>
                                                            </div>
                                                        </div>

                                                    <?php elseif ($setting['setting_type'] === 'url'): ?>
                                                        <div class="row">
                                                            <div class="col-md-6 mb-2">
                                                                <div class="input-group">
                                                                    <span class="input-group-text bg-light text-secondary small fw-bold">EN</span>
                                                                    <input type="url" class="form-control" name="<?php echo $setting['setting_key']; ?>_en" value="<?php echo htmlspecialchars($setting['values']['en'] ?? ''); ?>">
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6 mb-2">
                                                                <div class="input-group">
                                                                    <span class="input-group-text bg-light text-primary small fw-bold">KM</span>
                                                                    <input type="url" class="form-control" name="<?php echo $setting['setting_key']; ?>_km" value="<?php echo htmlspecialchars($setting['values']['km'] ?? ''); ?>">
                                                                </div>
                                                            </div>
                                                        </div>

                                                    <?php else: ?>
                                                        <div class="row">
                                                            <div class="col-md-6 mb-2">
                                                                <div class="input-group">
                                                                    <span class="input-group-text bg-light text-secondary small fw-bold">EN</span>
                                                                    <input type="text" class="form-control" name="<?php echo $setting['setting_key']; ?>_en" value="<?php echo htmlspecialchars($setting['values']['en'] ?? ''); ?>">
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6 mb-2">
                                                                <div class="input-group">
                                                                    <span class="input-group-text bg-light text-primary small fw-bold">KM</span>
                                                                    <input type="text" class="form-control" name="<?php echo $setting['setting_key']; ?>_km" value="<?php echo htmlspecialchars($setting['values']['km'] ?? ''); ?>">
                                                                </div>
                                                            </div>
                                                        </div>
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

        // Category details for dynamic header update
        const categoryDetails = {
            'general': { title: 'General Settings', desc: 'Basic website information and branding' },
            'contact': { title: 'Contact Information', desc: 'Company contact details' },
            'hero': { title: 'Hero Section', desc: 'Main banner and call-to-action content' },
            'about': { title: 'About Page', desc: 'About page content and sections' },
            'newsletter': { title: 'Newsletter Settings', desc: 'Newsletter subscription settings' },
            'footer': { title: 'Footer Settings', desc: 'Footer content and copyright' },
            'social': { title: 'Social Media', desc: 'Social media links and profiles' },
            'features': { title: 'Features & Content', desc: 'Website features and page content' },
            'product': { title: 'Product Information', desc: 'Product details and specifications' },
            'collections': { title: 'Product Collections', desc: 'Manage Syrup & Powder collection texts' },
            'reviews': { title: 'Reviews Section', desc: 'Customer reviews page content' },
            'policies': { title: 'Policies & Legal', desc: 'Privacy Policy, Terms of Service, and Contact Us content' },
            'pagination': { title: 'Pagination Settings', desc: 'Content display settings' },
            'navigation': { title: 'Navigation Menu', desc: 'Navigation menu labels' },
            'file_manager': { title: 'File Manager', desc: 'Manage uploaded product images' }
        };

        // Go back to the grid workflow overview
        function showSettingsGrid() {
            var input = document.getElementById('activeTabInput');
            if (input) input.value = 'grid';

            // Show grid, hide content, hide back button, hide save button
            document.getElementById('settingsGrid').classList.remove('d-none');
            document.getElementById('settingsTabContent').classList.add('d-none');
            document.getElementById('backToGridBtn').classList.add('d-none');
            
            const saveBtn = document.querySelector('.floating-save-btn');
            if (saveBtn) saveBtn.classList.add('d-none');

            // Reset headers
            document.getElementById('settingsPageTitle').textContent = 'System Settings';
            document.getElementById('settingsPageDesc').textContent = 'Configure your website behavior, content, and integrations.';

            // Remove active classes
            document.querySelectorAll('#settingsTabContent .tab-pane').forEach(function(p) {
                p.classList.remove('show', 'active');
            });
            
            // Push history state to keep clean url state without tabs if possible
            const url = new URL(window.location);
            url.searchParams.set('tab', 'grid');
            window.history.pushState({}, '', url);
        }

        // Switch to detail category view
        function switchSettingsTab(btn, tabId) {
            // Update hidden activeTab input for form submission
            var input = document.getElementById('activeTabInput');
            if (input) input.value = tabId;

            // Hide grid, show content, show back button, show save button
            document.getElementById('settingsGrid').classList.add('d-none');
            document.getElementById('settingsTabContent').classList.remove('d-none');
            document.getElementById('backToGridBtn').classList.remove('d-none');
            
            const saveBtn = document.querySelector('.floating-save-btn');
            if (saveBtn) saveBtn.classList.remove('d-none');

            // Update dynamic header
            if (categoryDetails[tabId]) {
                document.getElementById('settingsPageTitle').textContent = categoryDetails[tabId].title;
                document.getElementById('settingsPageDesc').textContent = categoryDetails[tabId].desc;
            }

            // Hide all tab panes
            document.querySelectorAll('#settingsTabContent .tab-pane').forEach(function(p) {
                p.classList.remove('show', 'active');
            });
            // Show target pane
            var targetPane = document.getElementById(tabId);
            if (targetPane) {
                targetPane.classList.add('show', 'active');
            }

            // Push history state
            const url = new URL(window.location);
            url.searchParams.set('tab', tabId);
            window.history.pushState({}, '', url);
        }

        // Auto-run on load to initialize correct state
        document.addEventListener('DOMContentLoaded', function() {
            var activeTab = document.getElementById('activeTabInput').value;
            if (!activeTab || activeTab === 'grid') {
                showSettingsGrid();
            } else {
                switchSettingsTab(null, activeTab);
            }
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

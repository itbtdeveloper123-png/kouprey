<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
session_start();
require_once '../app/Config/database.php';
require_once '../app/Config/settings.php';

// Helper function to download and localize external images inside rich text content
if (!function_exists('localizeExternalImages')) {
    function localizeExternalImages($html) {
        if (empty($html) || !is_string($html)) return $html;
        if (strpos($html, 'http') === false) return $html;
        
        $downloadedUrls = [];
        
        $downloadHelper = function($url) use (&$downloadedUrls) {
            if (isset($downloadedUrls[$url])) {
                return $downloadedUrls[$url];
            }
            
            $currentDomain = $_SERVER['HTTP_HOST'] ?? 'localhost';
            if (strpos($url, $currentDomain) !== false || strpos($url, '/kouprey/') !== false) {
                return $url;
            }
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
            $data = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            curl_close($ch);
            
            if ($httpCode === 200 && !empty($data)) {
                $ext = 'png';
                if (strpos($contentType, 'image/jpeg') !== false || strpos($contentType, 'image/jpg') !== false) {
                    $ext = 'jpg';
                } elseif (strpos($contentType, 'image/gif') !== false) {
                    $ext = 'gif';
                } elseif (strpos($contentType, 'image/webp') !== false) {
                    $ext = 'webp';
                } elseif (strpos($contentType, 'image/svg+xml') !== false || strpos($url, '.svg') !== false) {
                    $ext = 'svg';
                } else {
                    $pathExt = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);
                    if (!empty($pathExt)) {
                        $ext = strtolower($pathExt);
                    }
                }
                
                $safeName = 'downloaded-' . uniqid() . '.' . $ext;
                $uploadDir = '../public/assets/images/products/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                
                $targetPath = $uploadDir . $safeName;
                if (file_put_contents($targetPath, $data) !== false) {
                    // Only compress if the file is NOT an SVG and is larger than 150KB
                    if ($ext !== 'svg' && strlen($data) > 150 * 1024) {
                        require_once '../app/Config/image_utils.php';
                        $settings = getCompressionSettings('product');
                        compressImage($targetPath, $targetPath, $settings['quality'], $settings['maxWidth'], $settings['maxHeight']);
                    }
                    $localUrl = '/kouprey/public/assets/images/products/' . $safeName;
                    $downloadedUrls[$url] = $localUrl;
                    return $localUrl;
                }
            }
            
            return $url;
        };
        
        // 1. Match src/data-src attributes
        $pattern = '/(src|data-src)=["\'](https?:\/\/[^"\']+)["\']/i';
        $html = preg_replace_callback($pattern, function($matches) use ($downloadHelper) {
            $attr = $matches[1];
            $url = $matches[2];
            $localUrl = $downloadHelper($url);
            return $attr . '="' . $localUrl . '"';
        }, $html);
        
        // 2. Match url('...') inside style attributes
        $stylePattern = '/url\([\'"]?(https?:\/\/[^\'")]+)[\'"]?\)/i';
        $html = preg_replace_callback($stylePattern, function($matches) use ($downloadHelper) {
            $url = $matches[1];
            $localUrl = $downloadHelper($url);
            return "url('" . $localUrl . "')";
        }, $html);
        
        return $html;
    }
}

// Database Self-Healing: Repair invalid setting categories and remove duplicates
try {
    $keyCategoryMap = [
        'contact_us' => 'policies',
        'privacy_policy' => 'policies',
        'terms_of_service' => 'policies',
        'privacy_policy_title' => 'policies',
        'privacy_policy_desc' => 'policies',
        'terms_of_service_title' => 'policies',
        'terms_of_service_desc' => 'policies',
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
        'nav_home' => 'navigation',
        'nav_product' => 'navigation',
        'nav_products' => 'navigation',
        'nav_features' => 'navigation',
        'nav_reviews' => 'navigation',
        'nav_about' => 'navigation',
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
    
    // 2. Localize any historic external image URLs in existing settings values
    $stmt = $pdo->query("SELECT id, setting_value FROM settings WHERE setting_value LIKE '%http%'");
    $rowsToLocalize = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rowsToLocalize as $row) {
        $origValue = $row['setting_value'];
        $newValue = localizeExternalImages($origValue);
        if ($origValue !== $newValue) {
            $updateStmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE id = ?");
            $updateStmt->execute([$newValue, $row['id']]);
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
                      // Only compress if the file is NOT an SVG and is larger than 150KB
                      if (strtolower($ext) !== 'svg' && filesize($targetFile) > 150 * 1024) {
                          require_once '../app/Config/image_utils.php';
                          $settings = getCompressionSettings('product');
                          compressImage($targetFile, $targetFile, $settings['quality'], $settings['maxWidth'], $settings['maxHeight']);
                      }
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
        
        if (is_string($value)) {
            $value = localizeExternalImages($value);
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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Hanuman:wght@400;700&family=Kantumruy+Pro:wght@400;700&family=Moul&family=Siemreap&family=Freeman&display=swap" rel="stylesheet">
    <style>
    @font-face {
        font-family: 'Superspace Bold';
        src: url('/kouprey/public/fonts/Superspace Bold ver 1.00.ttf') format('truetype'),
             url('../public/fonts/Superspace Bold ver 1.00.ttf') format('truetype');
    }
    .rte-editor b, .rte-editor strong {
        font-weight: bold !important;
    }
    .rte-editor i, .rte-editor em {
        font-style: italic !important;
    }
    .rte-editor u {
        text-decoration: underline !important;
    }
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
    
    /* Settings layout spacing override for full-width view */
    .hide-sidebar-layout .content-container {
        padding: 1.5rem 1rem !important;
    }
    .hide-sidebar-layout .container-fluid {
        padding-left: 0.5rem !important;
        padding-right: 0.5rem !important;
    }
    .hide-sidebar-layout .tab-content .card-body {
        padding: 2rem 1.5rem !important;
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
            'file_manager' => ['icon' => 'bi-folder', 'title' => 'File Manager', 'description' => 'Manage uploaded product images'],
            'flaticon' => ['icon' => 'bi-search', 'title' => 'Flaticon Browser', 'description' => 'Clone view of Flaticon.com to copy icons']
        ];
        ?>

        <form id="settingsForm" method="POST" enctype="multipart/form-data" action="?lang=<?php echo $currentLanguage; ?>">
            <input type="hidden" name="active_tab" id="activeTabInput" value="<?php echo htmlspecialchars($activeTab); ?>">
            
            <!-- Cards Workflow Grid -->
            <div id="settingsGrid" class="mb-5 <?php echo ($activeTab !== 'grid') ? 'd-none' : ''; ?>">
                <div class="row g-4">
                    <?php foreach ($categories as $category => $categoryInfo): ?>
                    <?php if (!isset($groupedSettings[$category]) && !in_array($category, ['collections', 'contact', 'about', 'file_manager', 'policies', 'social', 'flaticon'])) continue; ?>
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
                <?php if (!isset($groupedSettings[$category]) && !in_array($category, ['collections', 'contact', 'about', 'file_manager', 'policies', 'social', 'flaticon'])) continue; ?>
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
                                                                    <div class="position-relative ratio ratio-1x1 bg-white rounded-top overflow-hidden border-bottom copy-img-card" data-url="<?php echo htmlspecialchars($url); ?>">
                                                                        <div class="copy-overlay position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center" style="background: rgba(15, 23, 42, 0.6); opacity: 0; transition: opacity 0.2s ease; cursor: pointer; z-index: 10;">
                                                                            <span class="badge bg-primary px-3 py-2 rounded-pill shadow fw-bold"><i class="bi bi-clipboard me-1"></i> Copy URL</span>
                                                                        </div>
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

                                <?php elseif ($category === 'flaticon'): ?>
                                    <!-- Flaticon Web Browser Clone with Fallback -->
                                    <div class="row g-4">
                                        <div class="col-lg-5">
                                            <div class="card border-0 shadow-sm p-4 h-100" style="background: #f8fafc; border-radius: 20px;">
                                                <h5 class="fw-bold text-dark mb-3"><i class="bi bi-info-circle text-primary me-2"></i>របៀបស្វែងរក និងទាញយក Icon</h5>
                                                <p class="text-secondary small mb-4">គេហទំព័រ Flaticon មានប្រព័ន្ធការពារសុវត្ថិភាពខ្ពស់ (Cloudflare) ដែលមិនអនុញ្ញាតឱ្យបើកបញ្ជូលក្នុងផ្ទាំង (Embedded iframe) ឡើយ ហេតុនេះវាបង្ហាញកូដ 403។ សូមអនុវត្តតាមជំហានងាយៗខាងក្រោម៖</p>
                                                
                                                <div class="d-flex flex-column gap-3 mb-4">
                                                    <div class="d-flex gap-3 align-items-start">
                                                        <span class="badge bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 24px; height: 24px; min-width: 24px;">1</span>
                                                        <div>
                                                            <h6 class="fw-bold mb-1 text-dark">បើកគេហទំព័រ Flaticon</h6>
                                                            <p class="text-muted small mb-0">ចុចលើប៊ូតុងពណ៌ខៀវខាងក្រោម ដើម្បីបើក Flaticon ក្នុងផ្ទាំងថ្មីមួយដោយសុវត្ថិភាព។</p>
                                                        </div>
                                                    </div>
                                                    <div class="d-flex gap-3 align-items-start">
                                                        <span class="badge bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 24px; height: 24px; min-width: 24px;">2</span>
                                                        <div>
                                                            <h6 class="fw-bold mb-1 text-dark">ស្វែងរក និងចម្លង Link (Copy)</h6>
                                                            <p class="text-muted small mb-0">ស្វែងរក Icon ដែលចង់បាន រួចចុចស្តាំ (Right-Click) លើរូបនោះ ហើយជ្រើសរើស <strong>"Copy image address"</strong> (ឬ Copy image link)។</p>
                                                        </div>
                                                    </div>
                                                    <div class="d-flex gap-3 align-items-start">
                                                        <span class="badge bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 24px; height: 24px; min-width: 24px;">3</span>
                                                        <div>
                                                            <h6 class="fw-bold mb-1 text-dark">យកមកប្រើប្រាស់</h6>
                                                            <p class="text-muted small mb-0">បិទទំព័រនោះវិញ រួចយក Link មក Paste ចូលក្នុងប្រអប់ Image URL នៃកម្មវិធីនិពន្ធ រួចចុច Apply ជាការស្រេច!</p>
                                                        </div>
                                                    </div>
                                                </div>

                                                <button type="button" class="btn btn-primary w-100 py-2.5 rounded-pill fw-bold shadow-sm" onclick="window.open('https://www.flaticon.com', 'FlaticonWindow', 'width=1200,height=800,scrollbars=yes')">
                                                    <i class="bi bi-box-arrow-up-right me-2"></i> បើកគេហទំព័រ Flaticon
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <div class="col-lg-7">
                                            <div style="height: 550px; border-radius: 20px; overflow: hidden; border: 1px solid #e2e8f0; background: #fff; display: flex; flex-direction: column;">
                                                <div class="bg-light p-3 border-bottom d-flex justify-content-between align-items-center">
                                                    <div class="d-flex align-items-center gap-2">
                                                        <span class="badge bg-primary"><i class="bi bi-globe"></i> Embedded Clone</span>
                                                    </div>
                                                    <div class="d-flex align-items-center gap-2" style="width: 65%;">
                                                        <input type="text" id="browserUrlInput" class="form-control form-control-sm" value="https://www.flaticon.com/" placeholder="Enter URL...">
                                                        <button type="button" class="btn btn-primary btn-sm px-3" onclick="navigateBrowser()"><i class="bi bi-arrow-right"></i> Go</button>
                                                    </div>
                                                </div>
                                                <div style="flex: 1; position: relative; background: #fff;">
                                                    <iframe id="settingsBrowserFrame" src="flaticon_browser.php?url=https%3A%2F%2Fwww.flaticon.com%2F" style="width: 100%; height: 100%; border: none;"></iframe>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <script>
                                    function navigateBrowser() {
                                        var url = document.getElementById('browserUrlInput').value.trim();
                                        if (url) {
                                            if (url.indexOf('http') !== 0) url = 'https://' + url;
                                            document.getElementById('settingsBrowserFrame').src = 'flaticon_browser.php?url=' + encodeURIComponent(url);
                                        }
                                    }
                                    </script>

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
                                                                    <select data-cmd="fontFamily" class="rte-font-family-select" style="width: auto; height: 28px; padding: 2px 6px; border: 1px solid #dee2e6; border-radius: 4px; font-size: 12px; cursor: pointer; background: #fff; vertical-align: middle; margin-right: 4px;" title="Font Family">
                                                                        <option value="">Font</option>
                                                                        <option value="'Superspace Bold', sans-serif">Superspace (Brand)</option>
                                                                        <option value="'Inter', sans-serif">Inter (EN)</option>
                                                                        <option value="'Outfit', sans-serif">Outfit (EN)</option>
                                                                        <option value="'Freeman', sans-serif">Freeman (EN)</option>
                                                                        <option value="'Hanuman', serif">Hanuman (KM)</option>
                                                                        <option value="'Kantumruy Pro', sans-serif">Kantumruy (KM)</option>
                                                                        <option value="'Moul', cursive">Moul (KM Heading)</option>
                                                                        <option value="'Siemreap', sans-serif">Siemreap (KM)</option>
                                                                    </select>
                                                                    <select data-cmd="fontSize" class="rte-font-size-select" style="width: auto; height: 28px; padding: 2px 6px; border: 1px solid #dee2e6; border-radius: 4px; font-size: 12px; cursor: pointer; background: #fff; vertical-align: middle; margin-right: 4px;" title="Font Size">
                                                                        <option value="">Size</option>
                                                                        <option value="12px">12px</option>
                                                                        <option value="14px">14px</option>
                                                                        <option value="16px">16px</option>
                                                                        <option value="18px">18px</option>
                                                                        <option value="20px">20px</option>
                                                                        <option value="24px">24px</option>
                                                                        <option value="28px">28px</option>
                                                                        <option value="32px">32px</option>
                                                                        <option value="36px">36px</option>
                                                                        <option value="40px">40px</option>
                                                                        <option value="48px">48px</option>
                                                                        <option value="custom">Custom...</option>
                                                                    </select>
                                                                    <input type="color" data-cmd="foreColor" class="rte-color-picker" style="width: 28px; height: 28px; border: 1px solid #dee2e6; border-radius: 4px; cursor: pointer; padding: 1px; vertical-align: middle; margin-right: 4px;" title="Text Color">
                                                                    <input type="color" data-cmd="hiliteColor" class="rte-highlight-picker" value="#FFFF00" style="width: 28px; height: 28px; border: 2px solid #f59e0b; border-radius: 4px; cursor: pointer; padding: 1px; vertical-align: middle; margin-right: 4px; background: #fef08a;" title="Highlight Color">
                                                                    <button type="button" data-cmd="insertIcon" title="Insert Font Awesome Icon"><i class="fas fa-icons"></i></button>
                                                                    <button type="button" data-cmd="insertImageLink" title="Insert Image from URL"><i class="fas fa-image"></i></button>
                                                                    <button type="button" data-cmd="insertHorizontalRule" title="Insert Horizontal Line"><i class="fas fa-minus"></i></button>
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
                                                            <div id="social_banner_editor_en" class="rte-editor" contenteditable="true" data-textarea="social_banner_text_en" style="background: #111827; color: #ffffff;"><?php echo $socialBannerTextEn; ?></div>
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
                                                                    <select data-cmd="fontFamily" class="rte-font-family-select" style="width: auto; height: 28px; padding: 2px 6px; border: 1px solid #dee2e6; border-radius: 4px; font-size: 12px; cursor: pointer; background: #fff; vertical-align: middle; margin-right: 4px;" title="Font Family">
                                                                        <option value="">Font</option>
                                                                        <option value="'Superspace Bold', sans-serif">Superspace (Brand)</option>
                                                                        <option value="'Inter', sans-serif">Inter (EN)</option>
                                                                        <option value="'Outfit', sans-serif">Outfit (EN)</option>
                                                                        <option value="'Freeman', sans-serif">Freeman (EN)</option>
                                                                        <option value="'Hanuman', serif">Hanuman (KM)</option>
                                                                        <option value="'Kantumruy Pro', sans-serif">Kantumruy (KM)</option>
                                                                        <option value="'Moul', cursive">Moul (KM Heading)</option>
                                                                        <option value="'Siemreap', sans-serif">Siemreap (KM)</option>
                                                                    </select>
                                                                    <select data-cmd="fontSize" class="rte-font-size-select" style="width: auto; height: 28px; padding: 2px 6px; border: 1px solid #dee2e6; border-radius: 4px; font-size: 12px; cursor: pointer; background: #fff; vertical-align: middle; margin-right: 4px;" title="Font Size">
                                                                        <option value="">Size</option>
                                                                        <option value="12px">12px</option>
                                                                        <option value="14px">14px</option>
                                                                        <option value="16px">16px</option>
                                                                        <option value="18px">18px</option>
                                                                        <option value="20px">20px</option>
                                                                        <option value="24px">24px</option>
                                                                        <option value="28px">28px</option>
                                                                        <option value="32px">32px</option>
                                                                        <option value="36px">36px</option>
                                                                        <option value="40px">40px</option>
                                                                        <option value="48px">48px</option>
                                                                        <option value="custom">Custom...</option>
                                                                    </select>
                                                                    <input type="color" data-cmd="foreColor" class="rte-color-picker" style="width: 28px; height: 28px; border: 1px solid #dee2e6; border-radius: 4px; cursor: pointer; padding: 1px; vertical-align: middle; margin-right: 4px;" title="Text Color">
                                                                    <input type="color" data-cmd="hiliteColor" class="rte-highlight-picker" value="#FFFF00" style="width: 28px; height: 28px; border: 2px solid #f59e0b; border-radius: 4px; cursor: pointer; padding: 1px; vertical-align: middle; margin-right: 4px; background: #fef08a;" title="Highlight Color">
                                                                    <button type="button" data-cmd="insertIcon" title="Insert Font Awesome Icon"><i class="fas fa-icons"></i></button>
                                                                    <button type="button" data-cmd="insertImageLink" title="Insert Image from URL"><i class="fas fa-image"></i></button>
                                                                    <button type="button" data-cmd="insertHorizontalRule" title="Insert Horizontal Line"><i class="fas fa-minus"></i></button>
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
                                                            <div id="social_banner_editor_km" class="rte-editor" contenteditable="true" data-textarea="social_banner_text_km" style="background: #111827; color: #ffffff;"><?php echo $socialBannerTextKm; ?></div>
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
                                                                    <select data-cmd="fontFamily" class="rte-font-family-select" style="width: auto; height: 28px; padding: 2px 6px; border: 1px solid #dee2e6; border-radius: 4px; font-size: 12px; cursor: pointer; background: #fff; vertical-align: middle; margin-right: 4px;" title="Font Family">
                                                                        <option value="">Font</option>
                                                                        <option value="'Superspace Bold', sans-serif">Superspace (Brand)</option>
                                                                        <option value="'Inter', sans-serif">Inter (EN)</option>
                                                                        <option value="'Outfit', sans-serif">Outfit (EN)</option>
                                                                        <option value="'Freeman', sans-serif">Freeman (EN)</option>
                                                                        <option value="'Hanuman', serif">Hanuman (KM)</option>
                                                                        <option value="'Kantumruy Pro', sans-serif">Kantumruy (KM)</option>
                                                                        <option value="'Moul', cursive">Moul (KM Heading)</option>
                                                                        <option value="'Siemreap', sans-serif">Siemreap (KM)</option>
                                                                    </select>
                                                                    <select data-cmd="fontSize" class="rte-font-size-select" style="width: auto; height: 28px; padding: 2px 6px; border: 1px solid #dee2e6; border-radius: 4px; font-size: 12px; cursor: pointer; background: #fff; vertical-align: middle; margin-right: 4px;" title="Font Size">
                                                                        <option value="">Size</option>
                                                                        <option value="12px">12px</option>
                                                                        <option value="14px">14px</option>
                                                                        <option value="16px">16px</option>
                                                                        <option value="18px">18px</option>
                                                                        <option value="20px">20px</option>
                                                                        <option value="24px">24px</option>
                                                                        <option value="28px">28px</option>
                                                                        <option value="32px">32px</option>
                                                                        <option value="36px">36px</option>
                                                                        <option value="40px">40px</option>
                                                                        <option value="48px">48px</option>
                                                                        <option value="custom">Custom...</option>
                                                                    </select>
                                                                    <input type="color" data-cmd="foreColor" class="rte-color-picker" style="width: 28px; height: 28px; border: 1px solid #dee2e6; border-radius: 4px; cursor: pointer; padding: 1px; vertical-align: middle; margin-right: 4px;" title="Text Color">
                                                                    <button type="button" data-cmd="insertIcon" title="Insert Font Awesome Icon"><i class="fas fa-icons"></i></button>
                                                                    <button type="button" data-cmd="insertImageLink" title="Insert Image from URL"><i class="fas fa-image"></i></button>
                                                                    <button type="button" data-cmd="insertHorizontalRule" title="Insert Horizontal Line"><i class="fas fa-minus"></i></button>
                                                                    <button type="button" data-cmd="insertTextBox" title="Insert Text Box"><i class="fas fa-border-style"></i></button>
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
                                                                    <select data-cmd="fontFamily" class="rte-font-family-select" style="width: auto; height: 28px; padding: 2px 6px; border: 1px solid #dee2e6; border-radius: 4px; font-size: 12px; cursor: pointer; background: #fff; vertical-align: middle; margin-right: 4px;" title="Font Family">
                                                                        <option value="">Font</option>
                                                                        <option value="'Superspace Bold', sans-serif">Superspace (Brand)</option>
                                                                        <option value="'Inter', sans-serif">Inter (EN)</option>
                                                                        <option value="'Outfit', sans-serif">Outfit (EN)</option>
                                                                        <option value="'Freeman', sans-serif">Freeman (EN)</option>
                                                                        <option value="'Hanuman', serif">Hanuman (KM)</option>
                                                                        <option value="'Kantumruy Pro', sans-serif">Kantumruy (KM)</option>
                                                                        <option value="'Moul', cursive">Moul (KM Heading)</option>
                                                                        <option value="'Siemreap', sans-serif">Siemreap (KM)</option>
                                                                    </select>
                                                                    <select data-cmd="fontSize" class="rte-font-size-select" style="width: auto; height: 28px; padding: 2px 6px; border: 1px solid #dee2e6; border-radius: 4px; font-size: 12px; cursor: pointer; background: #fff; vertical-align: middle; margin-right: 4px;" title="Font Size">
                                                                        <option value="">Size</option>
                                                                        <option value="12px">12px</option>
                                                                        <option value="14px">14px</option>
                                                                        <option value="16px">16px</option>
                                                                        <option value="18px">18px</option>
                                                                        <option value="20px">20px</option>
                                                                        <option value="24px">24px</option>
                                                                        <option value="28px">28px</option>
                                                                        <option value="32px">32px</option>
                                                                        <option value="36px">36px</option>
                                                                        <option value="40px">40px</option>
                                                                        <option value="48px">48px</option>
                                                                        <option value="custom">Custom...</option>
                                                                    </select>
                                                                    <input type="color" data-cmd="foreColor" class="rte-color-picker" style="width: 28px; height: 28px; border: 1px solid #dee2e6; border-radius: 4px; cursor: pointer; padding: 1px; vertical-align: middle; margin-right: 4px;" title="Text Color">
                                                                    <input type="color" data-cmd="hiliteColor" class="rte-highlight-picker" value="#FFFF00" style="width: 28px; height: 28px; border: 2px solid #f59e0b; border-radius: 4px; cursor: pointer; padding: 1px; vertical-align: middle; margin-right: 4px; background: #fef08a;" title="Highlight Color">
                                                                    <button type="button" data-cmd="insertIcon" title="Insert Font Awesome Icon"><i class="fas fa-icons"></i></button>
                                                                    <button type="button" data-cmd="insertImageLink" title="Insert Image from URL"><i class="fas fa-image"></i></button>
                                                                    <button type="button" data-cmd="insertHorizontalRule" title="Insert Horizontal Line"><i class="fas fa-minus"></i></button>
                                                                    <button type="button" data-cmd="insertTextBox" title="Insert Text Box"><i class="fas fa-border-style"></i></button>
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

                                            <!-- Policies Page Header Titles & Descriptions -->
                                            <div class="card border mb-4">
                                                <div class="card-header bg-light">
                                                    <span class="badge bg-secondary me-2"><i class="bi bi-card-heading"></i> Page Titles & Header Descriptions</span>
                                                    <small class="text-muted">Edit titles and short descriptions shown in the header of legal pages</small>
                                                </div>
                                                <div class="card-body">
                                                    <?php
                                                    $descFields = [
                                                        [
                                                            'title_key' => 'privacy_policy_title', 
                                                            'title_label' => 'Privacy Policy Title', 
                                                            'title_default' => 'Privacy Policy', 
                                                            'title_default_km' => 'គោលការណ៍ឯកជនភាព',
                                                            'desc_key' => 'privacy_policy_desc', 
                                                            'desc_label' => 'Privacy Policy Description', 
                                                            'desc_default' => 'We respect your privacy and are committed to protecting your personal information.'
                                                        ],
                                                        [
                                                            'title_key' => 'terms_of_service_title', 
                                                            'title_label' => 'Terms of Service Title', 
                                                            'title_default' => 'Terms of Service', 
                                                            'title_default_km' => 'លក្ខខណ្ឌប្រើប្រាស់',
                                                            'desc_key' => 'terms_of_service_desc', 
                                                            'desc_label' => 'Terms of Service Description', 
                                                            'desc_default' => 'Please read these Terms of Service carefully before using our services.'
                                                        ],
                                                    ];
                                                    foreach ($descFields as $df):
                                                        $titleEn = getSettingVal('policies', $df['title_key'], 'en', $df['title_default']);
                                                        $titleKm = getSettingVal('policies', $df['title_key'], 'km', $df['title_default_km']);
                                                        $valEn = getSettingVal('policies', $df['desc_key'], 'en', $df['desc_default']);
                                                        $valKm = getSettingVal('policies', $df['desc_key'], 'km', $df['desc_default']);
                                                    ?>
                                                    <div class="mb-4 pb-3 border-bottom last-no-border">
                                                        <div class="row g-3">
                                                            <!-- English Column -->
                                                            <div class="col-md-6 border-end">
                                                                <span class="badge bg-secondary mb-2">English</span>
                                                                <div class="mb-2">
                                                                    <label class="form-label small fw-bold text-dark mb-1"><?php echo $df['title_label']; ?></label>
                                                                    <input type="text" name="<?php echo $df['title_key']; ?>_en" class="form-control" value="<?php echo htmlspecialchars($titleEn); ?>">
                                                                </div>
                                                                <div>
                                                                    <label class="form-label small fw-bold text-dark mb-1"><?php echo $df['desc_label']; ?></label>
                                                                    <textarea name="<?php echo $df['desc_key']; ?>_en" class="form-control" rows="2"><?php echo htmlspecialchars($valEn); ?></textarea>
                                                                </div>
                                                            </div>
                                                            <!-- Khmer Column -->
                                                            <div class="col-md-6">
                                                                <span class="badge bg-primary mb-2">Khmer</span>
                                                                <div class="mb-2">
                                                                    <label class="form-label small fw-bold text-dark mb-1"><?php echo $df['title_label']; ?></label>
                                                                    <input type="text" name="<?php echo $df['title_key']; ?>_km" class="form-control" value="<?php echo htmlspecialchars($titleKm); ?>">
                                                                </div>
                                                                <div>
                                                                    <label class="form-label small fw-bold text-dark mb-1"><?php echo $df['desc_label']; ?></label>
                                                                    <textarea name="<?php echo $df['desc_key']; ?>_km" class="form-control" rows="2"><?php echo htmlspecialchars($valKm); ?></textarea>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
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
                                    <!-- Custom Rich Text Editor Styles -->
                                     <style>
                                     /* Ruler Styles */
                                     .rte-ruler-wrapper {
                                         position: relative;
                                         margin: 4px 0 0 0;
                                         background: #f8f9fa;
                                         border: 1px solid #dee2e6;
                                         border-bottom: none;
                                         border-radius: 4px 4px 0 0;
                                         height: 28px;
                                         user-select: none;
                                         box-shadow: inset 0 1px 2px rgba(0,0,0,0.05);
                                         overflow: visible;
                                     }
                                     .rte-ruler-ticks {
                                         position: absolute;
                                         top: 0;
                                         left: 40px; /* offset for left margin boundary */
                                         right: 40px; /* offset for right margin boundary */
                                         bottom: 0;
                                         background-image: 
                                             repeating-linear-gradient(90deg, #adb5bd 0, #adb5bd 1px, transparent 1px, transparent 10px),
                                             repeating-linear-gradient(90deg, #495057 0, #495057 1px, transparent 1px, transparent 50px);
                                         background-size: 100% 6px, 100% 12px;
                                         background-repeat: no-repeat;
                                         background-position: 0 bottom;
                                         opacity: 0.6;
                                     }
                                     .rte-ruler-number {
                                         position: absolute;
                                         bottom: 12px;
                                         font-size: 9px;
                                         font-family: monospace, sans-serif;
                                         color: #6c757d;
                                         transform: translateX(-50%);
                                         pointer-events: none;
                                     }
                                     .rte-ruler-marker {
                                         position: absolute;
                                         width: 12px;
                                         height: 14px;
                                         cursor: ew-resize;
                                         z-index: 15;
                                         transition: filter 0.1s;
                                     }
                                     .rte-ruler-marker:hover {
                                         filter: brightness(1.2);
                                     }
                                     /* First Line Indent: Top triangle pointing down */
                                     .rte-ruler-marker-firstline {
                                         top: 0px;
                                         background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='10' viewBox='0 0 12 10'%3E%3Cpolygon points='0,0 12,0 6,10' fill='%234f46e5'/%3E%3C/svg%3E");
                                         background-size: contain;
                                         background-repeat: no-repeat;
                                         transform: translateX(-50%);
                                     }
                                     /* Left Indent: Bottom triangle pointing up + small square handle */
                                     .rte-ruler-marker-left {
                                         bottom: 0px;
                                         background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='14' viewBox='0 0 12 14'%3E%3Cpolygon points='6,0 0,10 12,10' fill='%2306b6d4'/%3E%3Crect x='2' y='10' width='8' height='4' fill='%2306b6d4'/%3E%3C/svg%3E");
                                         background-size: contain;
                                         background-repeat: no-repeat;
                                         transform: translateX(-50%);
                                     }
                                     /* Right Indent: Bottom triangle pointing up on the right side */
                                     .rte-ruler-marker-right {
                                         bottom: 0px;
                                         background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='14' viewBox='0 0 12 14'%3E%3Cpolygon points='6,0 0,10 12,10' fill='%23f97316'/%3E%3Crect x='2' y='10' width='8' height='4' fill='%23f97316'/%3E%3C/svg%3E");
                                         background-size: contain;
                                         background-repeat: no-repeat;
                                         transform: translateX(50%);
                                     }
                                     /* Guide vertical line that overlays the editor during dragging */
                                     .rte-ruler-guide {
                                         position: absolute;
                                         top: 28px;
                                         width: 1px;
                                         border-left: 1px dashed #4f46e5;
                                         pointer-events: none;
                                         z-index: 1000;
                                         display: none;
                                     }
                                     /* Adjust editor border radius so it connects smoothly to the ruler */
                                     .rte-editor {
                                         border-top-left-radius: 0 !important;
                                         border-top-right-radius: 0 !important;
                                     }
                                      /* Quick Icon Inserter Styles */
                                      .rte-floating-trigger {
                                          position: absolute;
                                          width: 24px;
                                          height: 24px;
                                          background: #4f46e5;
                                          color: #fff;
                                          border-radius: 50%;
                                          display: none;
                                          align-items: center;
                                          justify-content: center;
                                          font-size: 11px;
                                          cursor: pointer;
                                          z-index: 9999;
                                          box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
                                          transition: transform 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275);
                                      }
                                      .rte-floating-trigger:hover {
                                          transform: scale(1.2) rotate(15deg);
                                          background: #4338ca;
                                      }
                                      .rte-quick-icon-popover {
                                          position: absolute;
                                          width: 260px;
                                          background: rgba(255, 255, 255, 0.95);
                                          backdrop-filter: blur(15px);
                                          -webkit-backdrop-filter: blur(15px);
                                          border: 1px solid rgba(0, 0, 0, 0.08);
                                          box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
                                          border-radius: 12px;
                                          z-index: 10000;
                                          display: none;
                                          flex-direction: column;
                                          padding: 10px;
                                          font-family: 'Inter', sans-serif;
                                      }
                                      .rte-quick-icon-header {
                                          font-size: 11px;
                                          font-weight: 700;
                                          text-transform: uppercase;
                                          color: #6b7280;
                                          margin-bottom: 8px;
                                          display: flex;
                                          justify-content: space-between;
                                          align-items: center;
                                      }
                                      .rte-quick-icon-search {
                                          width: 100%;
                                          height: 28px;
                                          padding: 4px 8px;
                                          font-size: 11px;
                                          border: 1px solid #dee2e6;
                                          border-radius: 6px;
                                          margin-bottom: 8px;
                                          outline: none;
                                          background: #fff;
                                      }
                                      .rte-quick-icon-search:focus {
                                          border-color: #4f46e5;
                                          box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.15);
                                      }
                                      .rte-quick-icon-grid {
                                          display: grid;
                                          grid-template-columns: repeat(5, 1fr);
                                          gap: 6px;
                                          max-height: 140px;
                                          overflow-y: auto;
                                          padding-right: 4px;
                                      }
                                      .rte-quick-icon-item {
                                          width: 38px;
                                          height: 38px;
                                          display: flex;
                                          align-items: center;
                                          justify-content: center;
                                          border-radius: 8px;
                                          border: 1px solid #f1f3f5;
                                          background: #fff;
                                          cursor: pointer;
                                          transition: all 0.2s;
                                      }
                                      .rte-quick-icon-item:hover {
                                          transform: scale(1.15);
                                          border-color: #4f46e5;
                                          background: #eff6ff;
                                          box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
                                      }
                                      .rte-quick-icon-item img {
                                          width: 24px;
                                          height: 24px;
                                          object-fit: contain;
                                      }
                                       .rte-editor hr {
                                           border: 0;
                                           border-top: 1px solid #dee2e6;
                                           margin: 1rem 0;
                                           display: block;
                                           height: 1px;
                                       }
                                       </style>
                                     <script>
                                    var availableIcons = <?php 
                                          $pImages = glob('../public/assets/images/products/*.{jpg,jpeg,png,gif,webp,svg,JPG,JPEG,PNG,GIF,WEBP,SVG}', GLOB_BRACE) ?: [];
                                          $pImages = array_filter($pImages, function($img) {
                                              $filename = basename($img); return strpos($filename, 'downloaded-') === 0 || strpos($filename, 'icon-') === 0 || @filesize($img) < 30 * 1024;
                                          });
                                          $urls = array_map(function($img) {
                                              return '/kouprey/public/assets/images/products/' . basename($img);
                                          }, $pImages);
                                          echo json_encode(array_values($urls));
                                      ?>;
                                     document.addEventListener('DOMContentLoaded', function() {
                                        // ===== Selection Saving / Restoring =====
                                        var savedRange = null;
                                        function saveSelection(editorId) {
                                            var sel = window.getSelection();
                                            if (sel.rangeCount > 0) {
                                                var range = sel.getRangeAt(0);
                                                var container = range.commonAncestorContainer;
                                                if (container.nodeType === 3) container = container.parentNode;
                                                if (container.closest('#' + editorId)) {
                                                    savedRange = range.cloneRange();
                                                } else {
                                                    savedRange = null;
                                                }
                                            } else {
                                                savedRange = null;
                                            }
                                        }
                                        function restoreSelection() {
                                            if (savedRange) {
                                                var sel = window.getSelection();
                                                sel.removeAllRanges();
                                                sel.addRange(savedRange);
                                            }
                                        }
                                        function getDefaultLinkUrl(selectedText) {
                                            if (!selectedText) return 'https://';
                                            // Check if email
                                            if (/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(selectedText)) {
                                                return 'mailto:' + selectedText;
                                            }
                                            // Check if phone number
                                            if (/^\+?[0-9\s\-]{8,20}$/.test(selectedText)) {
                                                return 'tel:' + selectedText.replace(/[\s\-]/g, '');
                                            }
                                            // Check if valid URL or domain
                                            if (/^https?:\/\//i.test(selectedText)) {
                                                return selectedText;
                                            }
                                            if (/^www\./i.test(selectedText)) {
                                                return 'https://' + selectedText;
                                            }
                                            if (/^[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/.test(selectedText)) {
                                                return 'https://' + selectedText;
                                            }
                                            return 'https://';
                                        }
                                        function formatRteLinkUrl(url) {
                                            url = url.trim();
                                            if (!url) return '';
                                            // If it's already mailto:, tel:, http:, or https:, leave it
                                            if (/^(mailto:|tel:|https?:\/\/)/i.test(url)) {
                                                return url;
                                            }
                                            // Check if email
                                            if (/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(url)) {
                                                return 'mailto:' + url;
                                            }
                                            // Check if phone number
                                            if (/^\+?[0-9\s\-]{8,20}$/.test(url)) {
                                                return 'tel:' + url.replace(/[\s\-]/g, '');
                                            }
                                            // Default to adding https:// if it looks like a domain or www
                                            if (/^www\./i.test(url)) {
                                                return 'https://' + url;
                                            }
                                            return url;
                                        }

                                        function applyCustomFontSize(editor, size) {
                                            var sel = window.getSelection();
                                            if (!sel.rangeCount) return;
                                            document.execCommand('fontSize', false, '7');
                                            var fontTags = editor.getElementsByTagName('font');
                                            for (var i = fontTags.length - 1; i >= 0; i--) {
                                                if (fontTags[i].getAttribute('size') === '7') {
                                                    fontTags[i].removeAttribute('size');
                                                    fontTags[i].style.fontSize = size;
                                                }
                                            }
                                        }

                                        function applyCustomFontFamily(editor, fontFamily) {
                                            var sel = window.getSelection();
                                            if (!sel.rangeCount) return;
                                            document.execCommand('fontName', false, 'temp-font');
                                            var fontTags = editor.getElementsByTagName('font');
                                            for (var i = fontTags.length - 1; i >= 0; i--) {
                                                if (fontTags[i].getAttribute('face') === 'temp-font') {
                                                    fontTags[i].removeAttribute('face');
                                                    fontTags[i].style.fontFamily = fontFamily;
                                                }
                                            }
                                        }

                                        // ===== Custom Modal Dialog Logic =====
                                        window.closeRteModal = function() {
                                            document.getElementById('rteModal').style.display = 'none';
                                        };
                                        
                                        window.showRteModal = function(title, fields, onSubmit) {
                                            var modal = document.getElementById('rteModal');
                                            var modalTitle = document.getElementById('rteModalTitle');
                                            var modalBody = document.getElementById('rteModalBody');
                                            var submitBtn = document.getElementById('rteModalSubmit');
                                            
                                            if (title === 'Insert Image from URL') {
                                                modalTitle.innerHTML = title + ' <button type="button" class="btn btn-xs btn-outline-primary ms-2 py-0 px-2 fw-bold" style="font-size:10px; border-radius:12px; vertical-align:middle; line-height:1.2; position:relative; top:-1px;" onclick="openFlaticonBrowser()"><i class="bi bi-globe"></i> Search Flaticon</button>';
                                            } else {
                                                modalTitle.textContent = title;
                                            }
                                            modalBody.innerHTML = '';
                                            
                                            fields.forEach(function(field) {
                                                var group = document.createElement('div');
                                                group.className = 'rte-form-group';
                                                
                                                var label = document.createElement('label');
                                                label.textContent = field.label;
                                                group.appendChild(label);
                                                
                                                if (field.type === 'select') {
                                                    var select = document.createElement('select');
                                                    select.className = 'rte-form-control';
                                                    select.id = field.id;
                                                    field.options.forEach(function(opt) {
                                                        var option = document.createElement('option');
                                                        option.value = opt.value;
                                                        option.textContent = opt.text;
                                                        if (opt.value === field.value) option.selected = true;
                                                        select.appendChild(option);
                                                    });
                                                    group.appendChild(select);
                                                } else {
                                                    var input = document.createElement('input');
                                                    input.type = field.type || 'text';
                                                    input.className = 'rte-form-control';
                                                    input.id = field.id;
                                                    input.value = field.value || '';
                                                    if (field.placeholder) input.placeholder = field.placeholder;
                                                    group.appendChild(input);
                                                }
                                                
                                                modalBody.appendChild(group);
                                            });
                                            
                                            submitBtn.onclick = function(e) {
                                                e.preventDefault();
                                                var values = {};
                                                fields.forEach(function(field) {
                                                    values[field.id] = document.getElementById(field.id).value;
                                                });
                                                onSubmit(values);
                                                closeRteModal();
                                            };
                                            
                                            modal.style.display = 'flex';
                                        };

                                         // ===== Microsoft Word-style Ruler Logic =====
                                         function getActiveBlock(editor) {
                                             var sel = window.getSelection();
                                             if (!sel.rangeCount) return null;
                                             var node = sel.getRangeAt(0).startContainer;
                                             
                                             while (node && node !== editor) {
                                                 if (node.nodeType === 1 && /^(P|DIV|LI|BLOCKQUOTE|H[1-6]|TD|TH)$/i.test(node.tagName)) {
                                                     return node;
                                                 }
                                                 node = node.parentNode;
                                             }
                                             return null;
                                         }

                                         function updateActiveBlockStyle(editor, property, value) {
                                             var block = getActiveBlock(editor);
                                             if (!block) {
                                                 var sel = window.getSelection();
                                                 if (sel.rangeCount) {
                                                     var range = sel.getRangeAt(0);
                                                     var container = range.startContainer;
                                                     if (container.nodeType === 3 && container.parentNode === editor) {
                                                         var p = document.createElement('p');
                                                         container.parentNode.insertBefore(p, container);
                                                         p.appendChild(container);
                                                         
                                                         var newRange = document.createRange();
                                                         newRange.selectNodeContents(p);
                                                         newRange.collapse(false);
                                                         sel.removeAllRanges();
                                                         sel.addRange(newRange);
                                                         
                                                         block = p;
                                                     } else if (container === editor) {
                                                         var p = document.createElement('p');
                                                         p.innerHTML = '<br>';
                                                         editor.appendChild(p);
                                                         block = p;
                                                     }
                                                 }
                                             }
                                             
                                             if (block) {
                                                 block.style[property] = value;
                                             }
                                         }

                                         function updateRulerNumbers(wrapper) {
                                             var ticks = wrapper.querySelector('.rte-ruler-ticks');
                                             if (!ticks) return;
                                             
                                             wrapper.querySelectorAll('.rte-ruler-number').forEach(function(n) { n.remove(); });
                                             
                                             var ticksWidth = ticks.offsetWidth;
                                             var step = 50; // Every 50px
                                             var count = Math.floor(ticksWidth / step);
                                             
                                             for (var i = 1; i <= count; i++) {
                                                 var num = document.createElement('div');
                                                 num.className = 'rte-ruler-number';
                                                 num.innerText = i;
                                                 num.style.left = (40 + i * step) + 'px';
                                                 wrapper.appendChild(num);
                                             }
                                         }

                                         function syncRulerMarkers(editor) {
                                             var wrapper = document.querySelector('.rte-ruler-wrapper[data-editor="' + editor.id + '"]');
                                             if (!wrapper) return;
                                             
                                             var ticks = wrapper.querySelector('.rte-ruler-ticks');
                                             var firstlineMarker = wrapper.querySelector('.rte-ruler-marker-firstline');
                                             var leftMarker = wrapper.querySelector('.rte-ruler-marker-left');
                                             var rightMarker = wrapper.querySelector('.rte-ruler-marker-right');
                                             
                                             if (!ticks || !firstlineMarker || !leftMarker || !rightMarker) return;
                                             
                                             var ticksLeft = ticks.offsetLeft;
                                             var ticksWidth = ticks.offsetWidth;
                                             
                                             var block = getActiveBlock(editor);
                                             var marginLeft = 0;
                                             var textIndent = 0;
                                             var marginRight = 0;
                                             
                                             if (block) {
                                                 marginLeft = parseInt(window.getComputedStyle(block).marginLeft) || 0;
                                                 textIndent = parseInt(window.getComputedStyle(block).textIndent) || 0;
                                                 marginRight = parseInt(window.getComputedStyle(block).marginRight) || 0;
                                             }
                                             
                                             // Left marker position
                                             var leftPos = ticksLeft + marginLeft;
                                             leftMarker.style.left = leftPos + 'px';
                                             
                                             // First-line marker (relative to left indent position)
                                             var firstlinePos = leftPos + textIndent;
                                             firstlineMarker.style.left = firstlinePos + 'px';
                                             
                                             // Right marker position
                                             var rightPos = ticksLeft + ticksWidth - marginRight;
                                             rightMarker.style.left = rightPos + 'px';
                                         }

                                         // Initialize Ruler UI for each editor
                                         document.querySelectorAll('.rte-editor').forEach(function(editor) {
                                             var editorId = editor.id;
                                             var wrapper = document.createElement('div');
                                             wrapper.className = 'rte-ruler-wrapper';
                                             wrapper.setAttribute('data-editor', editorId);
                                             
                                             var guide = document.createElement('div');
                                             guide.className = 'rte-ruler-guide';
                                             wrapper.appendChild(guide);
                                             
                                             var ticks = document.createElement('div');
                                             ticks.className = 'rte-ruler-ticks';
                                             wrapper.appendChild(ticks);
                                             
                                             var firstlineMarker = document.createElement('div');
                                             firstlineMarker.className = 'rte-ruler-marker rte-ruler-marker-firstline';
                                             firstlineMarker.title = 'First Line Indent';
                                             wrapper.appendChild(firstlineMarker);
                                             
                                             var leftMarker = document.createElement('div');
                                             leftMarker.className = 'rte-ruler-marker rte-ruler-marker-left';
                                             leftMarker.title = 'Left Indent';
                                             wrapper.appendChild(leftMarker);
                                             
                                             var rightMarker = document.createElement('div');
                                             rightMarker.className = 'rte-ruler-marker rte-ruler-marker-right';
                                             rightMarker.title = 'Right Indent';
                                             wrapper.appendChild(rightMarker);
                                             
                                             // Insert right before editor
                                             editor.parentNode.insertBefore(wrapper, editor);
                                             
                                             // Update ticks numbers
                                             setTimeout(function() {
                                                 updateRulerNumbers(wrapper);
                                                 syncRulerMarkers(editor);
                                             }, 100);

                                             // Bind cursor/selection triggers to sync ruler handles
                                             editor.addEventListener('mouseup', function() { syncRulerMarkers(editor); });
                                             editor.addEventListener('keyup', function() { syncRulerMarkers(editor); });
                                             editor.addEventListener('focus', function() { syncRulerMarkers(editor); });
                                         });

                                         // Global Drag State
                                         var activeDrag = null;
                                         document.addEventListener('mousedown', function(e) {
                                             if (e.target.classList.contains('rte-ruler-marker')) {
                                                 e.preventDefault();
                                                 var marker = e.target;
                                                 var wrapper = marker.closest('.rte-ruler-wrapper');
                                                 var editorId = wrapper.getAttribute('data-editor');
                                                 var editor = document.getElementById(editorId);
                                                 var ticks = wrapper.querySelector('.rte-ruler-ticks');
                                                 var guide = wrapper.querySelector('.rte-ruler-guide');
                                                 
                                                 if (guide && editor) {
                                                     guide.style.height = editor.offsetHeight + 'px';
                                                     guide.style.display = 'block';
                                                 }
                                                 
                                                 activeDrag = {
                                                     marker: marker,
                                                     wrapper: wrapper,
                                                     editor: editor,
                                                     ticks: ticks,
                                                     guide: guide,
                                                     type: marker.classList.contains('rte-ruler-marker-firstline') ? 'firstline' :
                                                           marker.classList.contains('rte-ruler-marker-left') ? 'left' : 'right',
                                                     startX: e.clientX,
                                                     startLeft: marker.offsetLeft,
                                                     ticksLeft: ticks.offsetLeft,
                                                     ticksWidth: ticks.offsetWidth
                                                 };
                                             }
                                         });

                                         document.addEventListener('mousemove', function(e) {
                                             if (!activeDrag) return;
                                             
                                             var d = activeDrag;
                                             var deltaX = e.clientX - d.startX;
                                             var newLeft = d.startLeft + deltaX;
                                             
                                             var minX = d.ticksLeft;
                                             var maxX = d.ticksLeft + d.ticksWidth;
                                             
                                             if (d.type === 'right') {
                                                 newLeft = Math.max(d.ticksLeft + d.ticksWidth / 2, Math.min(newLeft, maxX));
                                                 d.marker.style.left = newLeft + 'px';
                                                 if (d.guide) d.guide.style.left = newLeft + 'px';
                                                 
                                                 var rightVal = maxX - newLeft;
                                                 updateActiveBlockStyle(d.editor, 'margin-right', rightVal + 'px');
                                             } else {
                                                 newLeft = Math.max(minX, Math.min(newLeft, d.ticksLeft + d.ticksWidth / 2));
                                                 d.marker.style.left = newLeft + 'px';
                                                 if (d.guide) d.guide.style.left = newLeft + 'px';
                                                 
                                                 if (d.type === 'left') {
                                                     var leftVal = newLeft - d.ticksLeft;
                                                     updateActiveBlockStyle(d.editor, 'margin-left', leftVal + 'px');
                                                 } else if (d.type === 'firstline') {
                                                     var leftMarker = d.wrapper.querySelector('.rte-ruler-marker-left');
                                                     var firstLineVal = newLeft - leftMarker.offsetLeft;
                                                     updateActiveBlockStyle(d.editor, 'text-indent', firstLineVal + 'px');
                                                 }
                                             }
                                         });

                                         document.addEventListener('mouseup', function() {
                                             if (activeDrag) {
                                                 if (activeDrag.guide) {
                                                     activeDrag.guide.style.display = 'none';
                                                 }
                                                 syncTextarea(activeDrag.editor.id);
                                                 activeDrag = null;
                                             }
                                         });

                                         window.addEventListener('resize', function() {
                                             document.querySelectorAll('.rte-ruler-wrapper').forEach(function(wrapper) {
                                                 updateRulerNumbers(wrapper);
                                                 var editorId = wrapper.getAttribute('data-editor');
                                                 var editor = document.getElementById(editorId);
                                                 if (editor) {
                                                     syncRulerMarkers(editor);
                                                 }
                                             });
                                         });

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
                                                         var selectedText = window.getSelection().toString().trim();
                                                         var defaultUrl = getDefaultLinkUrl(selectedText);
                                                        saveSelection(editorId);
                                                        showRteModal('Insert Link', [
                                                            { id: 'linkUrl', label: 'Link URL', value: defaultUrl, placeholder: 'e.g. https://example.com' }
                                                        ], function(values) {
                                                            var url = formatRteLinkUrl(values.linkUrl);
                                                            if (url) {
                                                                restoreSelection();
                                                                document.execCommand('createLink', false, url);
                                                                syncTextarea(editorId);
                                                            }
                                                        });
                                                    } else if (cmd === 'hiliteColor') {
                                                        // handled via color input change, not button click
                                                    } else if (cmd === 'insertIcon') {
                                                        saveSelection(editorId);
                                                        showRteModal('Insert Font Awesome Icon', [
                                                            { id: 'iconClass', label: 'Font Awesome Icon Class', value: 'fas fa-coffee', placeholder: 'e.g. fas fa-coffee, fab fa-facebook' }
                                                        ], function(values) {
                                                            var iconClass = values.iconClass;
                                                            if (iconClass) {
                                                                restoreSelection();
                                                                var sel = window.getSelection();
                                                                if (sel.rangeCount) {
                                                                    var range = sel.getRangeAt(0);
                                                                    range.deleteContents();
                                                                    var el = document.createElement('i');
                                                                    el.className = iconClass;
                                                                    el.innerHTML = ' ';
                                                                    range.insertNode(el);
                                                                    var space = document.createTextNode(' ');
                                                                    range.insertNode(space);
                                                                    range.setStartAfter(space);
                                                                    range.collapse(true);
                                                                    sel.removeAllRanges();
                                                                    sel.addRange(range);
                                                                }
                                                                syncTextarea(editorId);
                                                            }
                                                        });
                                                    } else if (cmd === 'insertImageLink') {
                                                         saveSelection(editorId);
                                                         showRteModal('Insert Image from URL', [
                                                             { id: 'imgUrl', label: 'Image URL', value: 'https://', placeholder: 'e.g., https://domain.com/icon.png' },
                                                             { id: 'imgWidth', label: 'Image Width', value: '24px', placeholder: 'e.g., 24px, 50px, 100%' },
                                                             { id: 'imgStyle', label: 'Style Preset', type: 'select', value: 'original', options: [
                                                                 { value: 'original', text: 'Original Color' },
                                                                 { value: 'custom', text: 'Custom Color (choose below)' }
                                                             ]},
                                                             { id: 'imgColor', label: 'Custom Color Picker', type: 'color', value: '#ffffff' }
                                                         ], function(values) {
                                                             var url = values.imgUrl;
                                                             var width = values.imgWidth || '24px';
                                                             if (url) {
                                                                 restoreSelection();
                                                                 var sel = window.getSelection();
                                                                 if (sel.rangeCount) {
                                                                     var range = sel.getRangeAt(0);
                                                                     range.deleteContents();
                                                                     
                                                                     var el = document.createElement('img');
                                                                     el.style.width = width;
                                                                     el.style.height = 'auto';
                                                                     el.style.verticalAlign = 'middle';
                                                                     el.style.display = 'inline-block';
                                                                     el.className = 'rte-inserted-img';
                                                                     
                                                                     if (values.imgStyle === 'original') {
                                                                         el.src = url;
                                                                     } else {
                                                                         el.setAttribute('data-src', url);
                                                                         el.src = "data:image/svg+xml;charset=utf-8,%3Csvg xmlns='http://www.w3.org/2000/svg' width='1' height='1'%3E%3C/svg%3E";
                                                                         el.style.backgroundColor = values.imgColor || '#ffffff';
                                                                         el.style.webkitMaskImage = "url('" + url + "')";
                                                                         el.style.maskImage = "url('" + url + "')";
                                                                         el.style.webkitMaskSize = "contain";
                                                                         el.style.maskSize = "contain";
                                                                         el.style.webkitMaskRepeat = "no-repeat";
                                                                         el.style.maskRepeat = "no-repeat";
                                                                         el.style.display = "inline-block";
                                                                     }
                                                                     
                                                                     range.insertNode(el);
                                                                     
                                                                     var space = document.createTextNode(' ');
                                                                     range.insertNode(space);
                                                                     
                                                                     range.setStartAfter(space);
                                                                     range.collapse(true);
                                                                     sel.removeAllRanges();
                                                                     sel.addRange(range);
                                                                 }
                                                                 syncTextarea(editorId);
                                                             }
                                                         });
                                                     } else if (cmd === 'insertTextBox') {
                                                         saveSelection(editorId);
                                                         showRteModal('Insert Text Box', [
                                                             { id: 'tbWidth', label: 'Width (e.g., 100%, 300px, 50%)', value: '100%' },
                                                             { id: 'tbAlign', label: 'Alignment', type: 'select', value: 'full', options: [
                                                                 { value: 'full', text: 'Full Width (100%)' },
                                                                 { value: 'left', text: 'Float Left' },
                                                                 { value: 'center', text: 'Center' },
                                                                 { value: 'right', text: 'Float Right' }
                                                             ]},
                                                             { id: 'tbBorderStyle', label: 'Border Style', type: 'select', value: 'solid', options: [
                                                                 { value: 'solid', text: 'Solid' },
                                                                 { value: 'dashed', text: 'Dashed' },
                                                                 { value: 'dotted', text: 'Dotted' },
                                                                 { value: 'double', text: 'Double' },
                                                                 { value: 'none', text: 'None' }
                                                             ]},
                                                             { id: 'tbBorderWidth', label: 'Border Width', value: '2px' },
                                                             { id: 'tbBorderColor', label: 'Border Color', type: 'color', value: '#3b82f6' },
                                                             { id: 'tbBgColor', label: 'Background Color', type: 'color', value: '#f8fafc' },
                                                             { id: 'tbPadding', label: 'Padding', value: '12px' }
                                                         ], function(values) {
                                                             restoreSelection();
                                                             var sel = window.getSelection();
                                                             if (sel.rangeCount) {
                                                                 var range = sel.getRangeAt(0);
                                                                 range.deleteContents();
                                                                 
                                                                 var tb = document.createElement('div');
                                                                 tb.className = 'rte-textbox';
                                                                 
                                                                 var align = values.tbAlign;
                                                                 var width = values.tbWidth || '100%';
                                                                 var styleStr = 'border: ' + values.tbBorderWidth + ' ' + values.tbBorderStyle + ' ' + values.tbBorderColor + '; ';
                                                                 styleStr += 'background-color: ' + values.tbBgColor + '; ';
                                                                 styleStr += 'padding: ' + values.tbPadding + '; ';
                                                                 styleStr += 'border-radius: 8px; ';
                                                                 
                                                                 if (align === 'full') {
                                                                     styleStr += 'width: 100%; display: block; clear: both; margin: 12px 0;';
                                                                 } else if (align === 'left') {
                                                                     styleStr += 'width: ' + (width === '100%' ? '300px' : width) + '; float: left; margin: 8px 16px 8px 0; clear: left;';
                                                                 } else if (align === 'center') {
                                                                     styleStr += 'width: ' + (width === '100%' ? '350px' : width) + '; margin: 12px auto; display: block;';
                                                                 } else if (align === 'right') {
                                                                     styleStr += 'width: ' + (width === '100%' ? '300px' : width) + '; float: right; margin: 8px 0 8px 16px; clear: right;';
                                                                 }
                                                                 
                                                                 tb.setAttribute('style', styleStr);
                                                                 tb.innerHTML = '<p>Type text box content here...</p>';
                                                                 
                                                                 range.insertNode(tb);
                                                                 
                                                                 if (align !== 'full') {
                                                                     var emptyP = document.createElement('p');
                                                                     emptyP.innerHTML = '<br>';
                                                                     tb.parentNode.insertBefore(emptyP, tb.nextSibling);
                                                                 }
                                                                 
                                                                 syncTextarea(editorId);
                                                                 if (typeof syncRulerMarkers === 'function') {
                                                                     syncRulerMarkers(editor);
                                                                 }
                                                             }
                                                         });
                                                     } else {
                                                         if (cmd === 'insertHorizontalRule') {
                                                              saveSelection(editorId);
                                                              showRteModal('Insert Horizontal Line', [
                                                                  { id: 'hrWidth', label: 'Width (e.g. 100%, 50%, 300px)', value: '100%' },
                                                                  { id: 'hrThickness', label: 'Thickness (e.g. 1px, 3px, 5px)', value: '1px' },
                                                                  { id: 'hrStyle', label: 'Style', type: 'select', value: 'solid', options: [
                                                                      { value: 'solid', text: 'Solid' },
                                                                      { value: 'dashed', text: 'Dashed' },
                                                                      { value: 'dotted', text: 'Dotted' }
                                                                  ]},
                                                                  { id: 'hrColor', label: 'Line Color', type: 'color', value: '#dee2e6' },
                                                                  { id: 'hrSpacing', label: 'Spacing (margin top/bottom)', value: '24px' }
                                                              ], function(values) {
                                                                  restoreSelection();
                                                                  var sel = window.getSelection();
                                                                  if (sel.rangeCount) {
                                                                      var range = sel.getRangeAt(0);
                                                                      range.deleteContents();
                                                                      var hr = document.createElement('hr');
                                                                      var hrSt = 'border: 0; border-top: ' + values.hrThickness + ' ' + values.hrStyle + ' ' + values.hrColor + '; width: ' + values.hrWidth + '; margin: ' + values.hrSpacing + ' auto; height: 0; display: block; clear: both;';
                                                                      hr.setAttribute('style', hrSt);
                                                                                                                                             var container = range.commonAncestorContainer;
                                                                       if (container.nodeType === 3) container = container.parentNode;
                                                                       if (container && container.closest('#' + editorId)) {
                                                                           range.insertNode(hr);
                                                                       } else {
                                                                           var editor = document.getElementById(editorId);
                                                                           editor.appendChild(hr);
                                                                       }
                                                                      var p = document.createElement('p');
                                                                      p.innerHTML = '<br>';
                                                                      hr.parentNode.insertBefore(p, hr.nextSibling);
                                                                      var nr = document.createRange();
                                                                      nr.selectNodeContents(p);
                                                                      nr.collapse(true);
                                                                      sel.removeAllRanges();
                                                                      sel.addRange(nr);
                                                                  }
                                                                  syncTextarea(editorId);
                                                              });
                                                          } else {
                                                              document.execCommand(cmd, false, null);
                                                          }
                                                     }
                                                     syncTextarea(editorId);
                                                 });
                                             });
 
                                             var fontSizeSelect = toolbar.querySelector('select[data-cmd="fontSize"]');
                                             if (fontSizeSelect) {
                                                 fontSizeSelect.addEventListener('change', function() {
                                                     var editor = document.getElementById(editorId);
                                                     editor.focus();
                                                     var val = this.value;
                                                     if (!val) return;
                                                     
                                                     if (val === 'custom') {
                                                         saveSelection(editorId);
                                                         showRteModal('Enter Font Size', [
                                                             { id: 'customSize', label: 'Font Size (e.g., 14px, 24px, 1.5rem)', value: '24px', placeholder: 'e.g. 24px' }
                                                         ], function(values) {
                                                             var size = values.customSize;
                                                             if (size) {
                                                                 restoreSelection();
                                                                 applyCustomFontSize(editor, size);
                                                             }
                                                         });
                                                     } else {
                                                         applyCustomFontSize(editor, val);
                                                     }
                                                     
                                                     this.value = "";
                                                     syncTextarea(editorId);
                                                 });
                                             }

                                             var fontFamilySelect = toolbar.querySelector('select[data-cmd="fontFamily"]');
                                             if (fontFamilySelect) {
                                                 fontFamilySelect.addEventListener('change', function() {
                                                     var editor = document.getElementById(editorId);
                                                     editor.focus();
                                                     var val = this.value;
                                                     if (val) {
                                                         applyCustomFontFamily(editor, val);
                                                     }
                                                     this.value = "";
                                                     syncTextarea(editorId);
                                                 });
                                             }
 
                                             var formatSelect = toolbar.querySelector('select[data-cmd="formatBlock"]');
                                             if (formatSelect) {
                                                 formatSelect.addEventListener('change', function() {
                                                     var editor = document.getElementById(editorId);
                                                     editor.focus();
                                                     document.execCommand('formatBlock', false, '<' + this.value + '>');
                                                     syncTextarea(editorId);
                                                 });
                                             }
 
                                             var colorInput = toolbar.querySelector('input.rte-color-picker');
                                             if (colorInput) {
                                                 colorInput.addEventListener('input', function() {
                                                     var editor = document.getElementById(editorId);
                                                     editor.focus();
                                                     document.execCommand('foreColor', false, this.value);
                                                     syncTextarea(editorId);
                                                 });
                                             }

                                             var highlightInput = toolbar.querySelector('input.rte-highlight-picker');
                                             if (highlightInput) {
                                                 highlightInput.addEventListener('input', function() {
                                                     var editor = document.getElementById(editorId);
                                                     editor.focus();
                                                     document.execCommand('hiliteColor', false, this.value);
                                                     this.style.borderColor = this.value;
                                                     syncTextarea(editorId);
                                                 });
                                             }
 
                                             // Double click on image to resize and style
                                             var editor = document.getElementById(editorId);
                                              if (editor) {
                                                  // Keyboard shortcut for link (Ctrl+K)
                                                  editor.addEventListener('keydown', function(e) {
                                                      if ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K')) {
                                                          e.preventDefault();
                                                          var selectedText = window.getSelection().toString().trim();
                                                          var defaultUrl = getDefaultLinkUrl(selectedText);
                                                          saveSelection(editorId);
                                                          showRteModal('Insert Link', [
                                                              { id: 'linkUrl', label: 'Link URL', value: defaultUrl, placeholder: 'e.g. https://example.com' }
                                                          ], function(values) {
                                                              var url = formatRteLinkUrl(values.linkUrl);
                                                              if (url) {
                                                                  restoreSelection();
                                                                  document.execCommand('createLink', false, url);
                                                                  syncTextarea(editorId);
                                                              }
                                                          });
                                                      }
                                                  });

                                                 editor.addEventListener('dblclick', function(e) {
                                                     if (e.target && e.target.tagName === 'IMG') {
                                                         e.preventDefault();
                                                         var targetImg = e.target;
                                                         var currentWidth = targetImg.style.width || targetImg.width || '24px';
                                                         var currentUrl = targetImg.getAttribute('data-src') || targetImg.src || '';
                                                         
                                                         // Check if masked
                                                         var isMasked = targetImg.style.maskImage || targetImg.style.webkitMaskImage;
                                                         var currentStyleType = isMasked ? 'custom' : 'original';
                                                         var currentHexColor = targetImg.style.backgroundColor || '#ffffff';
                                                         // convert rgb to hex if needed
                                                         if (currentHexColor.indexOf('rgb') === 0) {
                                                             var rgbParts = currentHexColor.match(/\d+/g);
                                                             if (rgbParts && rgbParts.length >= 3) {
                                                                 var r = parseInt(rgbParts[0]).toString(16).padStart(2, '0');
                                                                 var g = parseInt(rgbParts[1]).toString(16).padStart(2, '0');
                                                                 var b = parseInt(rgbParts[2]).toString(16).padStart(2, '0');
                                                                 currentHexColor = '#' + r + g + b;
                                                             }
                                                         }
 
                                                         showRteModal('Edit Image Icon', [
                                                             { id: 'editUrl', label: 'Image URL / Link', value: currentUrl, placeholder: 'e.g. https://domain.com/icon.png' },
                                                             { id: 'editWidth', label: 'Width (e.g. 24px, 50px, 100%)', value: currentWidth },
                                                             { id: 'editStyle', label: 'Style Preset', type: 'select', value: currentStyleType, options: [
                                                                 { value: 'original', text: 'Original Color' },
                                                                 { value: 'custom', text: 'Custom Color (choose below)' }
                                                             ]},
                                                             { id: 'editColor', label: 'Custom Color Picker', type: 'color', value: currentHexColor }
                                                         ], function(values) {
                                                             var updatedUrl = values.editUrl || '';
                                                             var editWidth = values.editWidth || '24px';
                                                             var styleChoice = values.editStyle;
                                                             var editColor = values.editColor || '#ffffff';
                                                             
                                                             function applyImageUpdates(img) {
                                                                 if (editWidth) {
                                                                     img.style.width = editWidth;
                                                                     img.style.height = 'auto';
                                                                 }
                                                                 if (styleChoice === 'original') {
                                                                     img.src = updatedUrl;
                                                                     img.setAttribute('data-src', updatedUrl);
                                                                     img.style.backgroundColor = '';
                                                                     img.style.webkitMaskImage = '';
                                                                     img.style.maskImage = '';
                                                                     img.style.webkitMaskSize = '';
                                                                     img.style.maskSize = '';
                                                                     img.style.webkitMaskRepeat = '';
                                                                     img.style.maskRepeat = '';
                                                             } else {
                                                                     img.setAttribute('data-src', updatedUrl);
                                                                     img.src = "data:image/svg+xml;charset=utf-8,%3Csvg xmlns='http://www.w3.org/2000/svg' width='1' height='1'%3E%3C/svg%3E";
                                                                     img.style.backgroundColor = editColor;
                                                                     img.style.webkitMaskImage = "url('" + updatedUrl + "')";
                                                                     img.style.maskImage = "url('" + updatedUrl + "')";
                                                                     img.style.webkitMaskSize = "contain";
                                                                     img.style.maskSize = "contain";
                                                                     img.style.webkitMaskRepeat = "no-repeat";
                                                                     img.style.maskRepeat = "no-repeat";
                                                                     img.style.display = "inline-block";
                                                                     img.style.verticalAlign = "middle";
                                                                 }
                                                             }
                                                             
                                                             // Apply to target
                                                             applyImageUpdates(targetImg);
                                                              syncTextarea(editorId);
                                                              return;
                                                             
                                                             // Sync with twin editor (EN <-> KM)
                                                             var twinEditorId = null;
                                                             if (editorId.endsWith('_en_editor')) {
                                                                 twinEditorId = editorId.replace('_en_editor', '_km_editor');
                                                             } else if (editorId.endsWith('_km_editor')) {
                                                                 twinEditorId = editorId.replace('_km_editor', '_en_editor');
                                                             } else if (editorId.endsWith('_editor_en')) {
                                                                 twinEditorId = editorId.replace('_editor_en', '_editor_km');
                                                             } else if (editorId.endsWith('_editor_km')) {
                                                                 twinEditorId = editorId.replace('_editor_km', '_editor_en');
                                                             } else if (editorId.endsWith('_en')) {
                                                                 twinEditorId = editorId.replace('_en', '_km');
                                                             } else if (editorId.endsWith('_km')) {
                                                                 twinEditorId = editorId.replace('_km', '_en');
                                                             }
                                                             
                                                             if (twinEditorId) {
                                                                 var twinEditor = document.getElementById(twinEditorId);
                                                                 if (twinEditor) {
                                                                     var twinImgs = twinEditor.getElementsByTagName('img');
                                                                     
                                                                     // Find index of targetImg in the current editor
                                                                     var currentImgs = editor.getElementsByTagName('img');
                                                                     var targetIndex = -1;
                                                                     for (var i = 0; i < currentImgs.length; i++) {
                                                                         if (currentImgs[i] === targetImg) {
                                                                             targetIndex = i;
                                                                             break;
                                                                         }
                                                                     }
                                                                     
                                                                     var updatedTwin = false;
                                                                     // Update the image at the same index if it exists in the twin editor
                                                                     if (targetIndex !== -1 && targetIndex < twinImgs.length) {
                                                                         applyImageUpdates(twinImgs[targetIndex]);
                                                                         updatedTwin = true;
                                                                      }
                                                     // Also sync any other image in the twin editor that matches the original URL
                                                                     for (var i = 0; i < twinImgs.length; i++) {
                                                                         if (updatedTwin && i === targetIndex) continue;
                                                                         var tImg = twinImgs[i];
                                                                         var tUrl = tImg.getAttribute('data-src') || tImg.src || '';
                                                                         var clean1 = tUrl.replace(/^(https?:\/\/[^\/]+)?/, '');
                                                                         var clean2 = currentUrl.replace(/^(https?:\/\/[^\/]+)?/, '');
                                                                         if (clean1 === clean2 && clean2 !== '') {
                                                                             applyImageUpdates(tImg);
                                                                         }
                                                                     }
                                                                     syncTextarea(twinEditorId);
                                                                 }
                                                             }
                                                             
                                                             syncTextarea(editorId);
                                                         });
                                                      } else {
                                                          var targetTb = e.target.closest('.rte-textbox');
                                                          if (targetTb) {
                                                              e.preventDefault();
                                                              
                                                              var computedStyle = window.getComputedStyle(targetTb);
                                                              var currentBorderColor = targetTb.style.borderColor || computedStyle.borderColor || '#3b82f6';
                                                              var currentBgColor = targetTb.style.backgroundColor || computedStyle.backgroundColor || '#f8fafc';
                                                              var currentPadding = targetTb.style.padding || computedStyle.padding || '12px';
                                                              var currentWidth = targetTb.style.width || computedStyle.width || '100%';
                                                              var currentBorderWidth = targetTb.style.borderWidth || computedStyle.borderWidth || '2px';
                                                              var currentBorderStyle = targetTb.style.borderStyle || computedStyle.borderStyle || 'solid';
                                                              
                                                              function rgbToHex(rgb) {
                                                                  if (!rgb || rgb.indexOf('rgb') !== 0) return rgb;
                                                                  var parts = rgb.match(/\d+/g);
                                                                  if (parts && parts.length >= 3) {
                                                                      var r = parseInt(parts[0]).toString(16).padStart(2, '0');
                                                                      var g = parseInt(parts[1]).toString(16).padStart(2, '0');
                                                                      var b = parseInt(parts[2]).toString(16).padStart(2, '0');
                                                                      return '#' + r + g + b;
                                                                  }
                                                                  return rgb;
                                                              }
                                                              
                                                              currentBorderColor = rgbToHex(currentBorderColor);
                                                              currentBgColor = rgbToHex(currentBgColor);
                                                              
                                                              var currentAlign = 'full';
                                                              var styleAttr = targetTb.getAttribute('style') || '';
                                                              if (styleAttr.indexOf('float: left') !== -1 || styleAttr.indexOf('float:left') !== -1) {
                                                                  currentAlign = 'left';
                                                              } else if (styleAttr.indexOf('margin: 12px auto') !== -1 || styleAttr.indexOf('margin:12px auto') !== -1) {
                                                                  currentAlign = 'center';
                                                              } else if (styleAttr.indexOf('float: right') !== -1 || styleAttr.indexOf('float:right') !== -1) {
                                                                  currentAlign = 'right';
                                                              }
                                                              
                                                              showRteModal('Edit Text Box', [
                                                                  { id: 'editTbWidth', label: 'Width (e.g., 100%, 300px, 50%)', value: currentWidth },
                                                                  { id: 'editTbAlign', label: 'Alignment', type: 'select', value: currentAlign, options: [
                                                                      { value: 'full', text: 'Full Width (100%)' },
                                                                      { value: 'left', text: 'Float Left' },
                                                                      { value: 'center', text: 'Center' },
                                                                      { value: 'right', text: 'Float Right' }
                                                                  ]},
                                                                  { id: 'editTbBorderStyle', label: 'Border Style', type: 'select', value: currentBorderStyle, options: [
                                                                      { value: 'solid', text: 'Solid' },
                                                                      { value: 'dashed', text: 'Dashed' },
                                                                      { value: 'dotted', text: 'Dotted' },
                                                                      { value: 'double', text: 'Double' },
                                                                      { value: 'none', text: 'None' }
                                                                  ]},
                                                                  { id: 'editTbBorderWidth', label: 'Border Width', value: currentBorderWidth },
                                                                  { id: 'editTbBorderColor', label: 'Border Color', type: 'color', value: currentBorderColor },
                                                                  { id: 'editTbBgColor', label: 'Background Color', type: 'color', value: currentBgColor },
                                                                  { id: 'editTbPadding', label: 'Padding', value: currentPadding }
                                                              ], function(values) {
                                                                  var align = values.editTbAlign;
                                                                  var width = values.editTbWidth || '100%';
                                                                  var styleStr = 'border: ' + values.editTbBorderWidth + ' ' + values.editTbBorderStyle + ' ' + values.editTbBorderColor + '; ';
                                                                  styleStr += 'background-color: ' + values.editTbBgColor + '; ';
                                                                  styleStr += 'padding: ' + values.editTbPadding + '; ';
                                                                  styleStr += 'border-radius: 8px; ';
                                                                  
                                                                  if (align === 'full') {
                                                                      styleStr += 'width: 100%; display: block; clear: both; margin: 12px 0;';
                                                                  } else if (align === 'left') {
                                                                      styleStr += 'width: ' + (width === '100%' ? '300px' : width) + '; float: left; margin: 8px 16px 8px 0; clear: left;';
                                                                  } else if (align === 'center') {
                                                                      styleStr += 'width: ' + (width === '100%' ? '350px' : width) + '; margin: 12px auto; display: block;';
                                                                  } else if (align === 'right') {
                                                                      styleStr += 'width: ' + (width === '100%' ? '300px' : width) + '; float: right; margin: 8px 0 8px 16px; clear: right;';
                                                                  }
                                                                  
                                                                  targetTb.setAttribute('style', styleStr);
                                                                  syncTextarea(editorId);
                                                              });
                                                          }
                                                     }
                                                 });
                                             }
                                         });

                                        editor.addEventListener('dblclick', function(e) {
                                                      if (e.target && e.target.tagName === 'HR') {
                                                          e.preventDefault();
                                                          var targetHr = e.target;
                                                          var hrCurStyle = targetHr.getAttribute('style') || '';
                                                          var hrCW = '100%', hrCT = '1px', hrCS = 'solid', hrCC = '#dee2e6', hrCSp = '24px';
                                                          var hrWM = hrCurStyle.match(/width:\s*([^;]+)/i); if (hrWM) hrCW = hrWM[1].trim();
                                                          var hrMM = hrCurStyle.match(/margin:\s*([^;]+)/i); if (hrMM) { var hrMP = hrMM[1].trim().split(/\s+/); hrCSp = hrMP[0]; }
                                                          var hrBM = hrCurStyle.match(/border-top:\s*([^;]+)/i);
                                                          if (hrBM) { var hrBP = hrBM[1].trim().split(/\s+/); if (hrBP.length>=1) hrCT=hrBP[0]; if (hrBP.length>=2) hrCS=hrBP[1]; if (hrBP.length>=3) hrCC=hrBP[2]; }
                                                          if (hrCC.indexOf('rgb')===0) { var hrRP=hrCC.match(/\d+/g); if (hrRP&&hrRP.length>=3) hrCC='#'+parseInt(hrRP[0]).toString(16).padStart(2,'0')+parseInt(hrRP[1]).toString(16).padStart(2,'0')+parseInt(hrRP[2]).toString(16).padStart(2,'0'); }
                                                          showRteModal('Edit Horizontal Line', [
                                                              { id: 'eHrWidth', label: 'Width (e.g. 100%, 50%, 300px)', value: hrCW },
                                                              { id: 'eHrThick', label: 'Thickness (e.g. 1px, 3px)', value: hrCT },
                                                              { id: 'eHrStyle', label: 'Style', type: 'select', value: hrCS, options: [
                                                                  { value: 'solid', text: 'Solid' },
                                                                  { value: 'dashed', text: 'Dashed' },
                                                                  { value: 'dotted', text: 'Dotted' }
                                                              ]},
                                                              { id: 'eHrColor', label: 'Line Color', type: 'color', value: hrCC },
                                                              { id: 'eHrSpacing', label: 'Spacing (margin top/bottom)', value: hrCSp }
                                                          ], function(values) {
                                                              function applyHrSt(hr) {
                                                                  hr.setAttribute('style', 'border: 0; border-top: ' + values.eHrThick + ' ' + values.eHrStyle + ' ' + values.eHrColor + '; width: ' + values.eHrWidth + '; margin: ' + values.eHrSpacing + ' auto; height: 0; display: block; clear: both;');
                                                              }
                                                              applyHrSt(targetHr);
                                                              syncTextarea(editorId);
                                                              return;
                                                              var twinHrId = null;
                                                              if (editorId.endsWith('_en_editor')) twinHrId = editorId.replace('_en_editor','_km_editor');
                                                              else if (editorId.endsWith('_km_editor')) twinHrId = editorId.replace('_km_editor','_en_editor');
                                                              else if (editorId.endsWith('_editor_en')) twinHrId = editorId.replace('_editor_en','_editor_km');
                                                              else if (editorId.endsWith('_editor_km')) twinHrId = editorId.replace('_editor_km','_editor_en');
                                                              else if (editorId.endsWith('_en')) twinHrId = editorId.replace('_en','_km');
                                                              else if (editorId.endsWith('_km')) twinHrId = editorId.replace('_km','_en');
                                                              if (twinHrId) {
                                                                  var twinEd = document.getElementById(twinHrId);
                                                                  if (twinEd) {
                                                                      var srcHrs = editor.getElementsByTagName('hr');
                                                                      var hrIdx = -1;
                                                                      for (var hi=0; hi<srcHrs.length; hi++) { if (srcHrs[hi]===targetHr) { hrIdx=hi; break; } }
                                                                      if (hrIdx !== -1) { var tHrs = twinEd.getElementsByTagName('hr'); if (tHrs[hrIdx]) applyHrSt(tHrs[hrIdx]); }
                                                                      syncTextarea(twinHrId);
                                                                  }
                                                              }
                                                              syncTextarea(editorId);
                                                          });
                                                      }
                                                  });

                                         // ===== Emoji Button Toggle =====
                                        document.querySelectorAll('.rte-emoji-btn').forEach(function(btn) {
                                            btn.addEventListener('click', function(e) {
                                                e.preventDefault();
                                                e.stopPropagation();
                                                
                                                var toolbar = this.closest('.rte-toolbar');
                                                var editorId = toolbar ? toolbar.getAttribute('data-editor') : null;
                                                var panel = editorId ? document.querySelector('.rte-emoji-panel[data-editor="' + editorId + '"]') : null;
                                                
                                                if (panel) {
                                                    // Close all panels first
                                                    document.querySelectorAll('.rte-emoji-panel').forEach(function(p) {
                                                        if (p !== panel) p.classList.remove('show');
                                                    });
                                                    panel.classList.toggle('show');
                                                }
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

                                        // ===== Auto-adjust Editor Background logic =====
                                        function isLightColorString(colorStr) {
                                            colorStr = colorStr.trim().toLowerCase();
                                            if (colorStr === 'white' || colorStr === 'yellow' || colorStr === '#fff' || colorStr === '#ffff00') {
                                                return true;
                                            }
                                            if (colorStr.indexOf('rgb') === 0) {
                                                var parts = colorStr.match(/\d+/g);
                                                if (parts && parts.length >= 3) {
                                                    var r = parseInt(parts[0]);
                                                    var g = parseInt(parts[1]);
                                                    var b = parseInt(parts[2]);
                                                    return ((r * 299 + g * 587 + b * 114) / 1000) > 180;
                                                }
                                            }
                                            if (colorStr.indexOf('#') === 0) {
                                                var hex = colorStr;
                                                if (hex.length === 4) {
                                                    hex = '#' + hex[1] + hex[1] + hex[2] + hex[2] + hex[3] + hex[3];
                                                }
                                                if (hex.length === 7) {
                                                    var r = parseInt(hex.substring(1, 3), 16);
                                                    var g = parseInt(hex.substring(3, 5), 16);
                                                    var b = parseInt(hex.substring(5, 7), 16);
                                                    return ((r * 299 + g * 587 + b * 114) / 1000) > 180;
                                                }
                                            }
                                            return false;
                                        }

                                        function adjustEditorBackground(editor) {
                                            // For the social media banner, it is always dark by design
                                            if (editor.id === 'social_banner_editor_en' || editor.id === 'social_banner_editor_km') {
                                                editor.style.setProperty('background-color', '#111827', 'important');
                                                editor.style.setProperty('background', '#111827', 'important');
                                                editor.style.setProperty('color', '#ffffff', 'important');
                                                return;
                                            }
                                            
                                            var hasLightColor = false;
                                            
                                            // Check font elements
                                            var fontTags = editor.getElementsByTagName('font');
                                            for (var i = 0; i < fontTags.length; i++) {
                                                var color = fontTags[i].getAttribute('color');
                                                if (color && isLightColorString(color)) {
                                                    hasLightColor = true;
                                                    break;
                                                }
                                            }
                                            
                                            // Check span style colors
                                            if (!hasLightColor) {
                                                var spanTags = editor.getElementsByTagName('span');
                                                for (var i = 0; i < spanTags.length; i++) {
                                                    var color = spanTags[i].style.color;
                                                    if (color && isLightColorString(color)) {
                                                        hasLightColor = true;
                                                        break;
                                                    }
                                                }
                                            }
                                            
                                            // Check background color of images/icons (which represents custom tinted icon colors!)
                                            if (!hasLightColor) {
                                                var imgTags = editor.getElementsByTagName('img');
                                                for (var i = 0; i < imgTags.length; i++) {
                                                    var bgColor = imgTags[i].style.backgroundColor;
                                                    if (bgColor && isLightColorString(bgColor)) {
                                                        hasLightColor = true;
                                                        break;
                                                    }
                                                }
                                            }
                                            
                                            if (hasLightColor) {
                                                editor.style.setProperty('background-color', '#111827', 'important');
                                                editor.style.setProperty('background', '#111827', 'important');
                                                editor.style.setProperty('color', '#ffffff', 'important');
                                            } else {
                                                editor.style.setProperty('background-color', '#ffffff', 'important');
                                                editor.style.setProperty('background', '#ffffff', 'important');
                                                editor.style.setProperty('color', '#333333', 'important');
                                            }
                                        }

                                        // ===== Sync contenteditable → hidden textarea =====
                                        function syncTextarea(editorId) {
                                            var editor = document.getElementById(editorId);
                                            if (!editor) return;
                                            
                                            // Automatically adjust background based on contents
                                            adjustEditorBackground(editor);
                                            
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
                                            adjustEditorBackground(editor);
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
                                                'hr { border: 0; border-top: 1px solid #e5e7eb; margin: 1.5rem 0; }' +
                                                '</style></head><body><div class="content-section">' + content + '</div></body></html>';

                                            previewFrame.srcdoc = previewHTML;
                                        }

                                         // ===== Floating Quick Icon Inserter Popover =====
                                         var activeEditor = null;
                                         var floatingTrigger = document.createElement('div');
                                         floatingTrigger.id = 'rteFloatingTrigger';
                                         floatingTrigger.className = 'rte-floating-trigger';
                                         floatingTrigger.title = 'Quick Icon Inserter';
                                         floatingTrigger.innerHTML = '<i class="fas fa-image"></i>';
                                         document.body.appendChild(floatingTrigger);

                                         var quickIconPopover = document.createElement('div');
                                         quickIconPopover.id = 'rteQuickIconPopover';
                                         quickIconPopover.className = 'rte-quick-icon-popover';
                                         quickIconPopover.innerHTML = 
                                             '<div class="rte-quick-icon-header">' +
                                             '    <span>Quick Icons</span>' +
                                             '    <i class="fas fa-times" style="cursor:pointer;" onclick="document.getElementById(\'rteQuickIconPopover\').style.display=\'none\'"></i>' +
                                             '</div>' +
                                             '<input type="text" class="rte-quick-icon-search" placeholder="Search icons...">' +
                                             '<div class="rte-quick-icon-grid"></div>';
                                         document.body.appendChild(quickIconPopover);

                                         var searchInput = quickIconPopover.querySelector('.rte-quick-icon-search');
                                         var iconGrid = quickIconPopover.querySelector('.rte-quick-icon-grid');

                                         function renderGrid(filterText) {
                                             iconGrid.innerHTML = '';
                                             var icons = availableIcons || [];
                                             var count = 0;
                                             icons.forEach(function(url) {
                                                 var filename = url.substring(url.lastIndexOf('/') + 1).toLowerCase();
                                                 if (!filterText || filename.indexOf(filterText.toLowerCase()) !== -1) {
                                                     var item = document.createElement('div');
                                                     item.className = 'rte-quick-icon-item';
                                                     item.title = filename;
                                                     item.innerHTML = '<img src="' + url + '">';
                                                     item.addEventListener('click', function(e) {
                                                         e.preventDefault();
                                                         e.stopPropagation();
                                                         insertQuickIcon(url);
                                                     });
                                                     iconGrid.appendChild(item);
                                                     count++;
                                                 }
                                             });
                                             if (count === 0) {
                                                 iconGrid.innerHTML = '<div style="grid-column: span 5; font-size:10px; color:#9ca3af; text-align:center; padding:10px;">No icons</div>';
                                             }
                                         }

                                         searchInput.addEventListener('input', function() {
                                             renderGrid(this.value);
                                         });

                                         function insertQuickIcon(url) {
                                             if (!activeEditor) return;
                                             restoreSelection();
                                             var sel = window.getSelection();
                                             if (sel.rangeCount) {
                                                 var range = sel.getRangeAt(0);
                                                 range.deleteContents();
                                                 
                                                 var img = document.createElement('img');
                                                 img.src = url;
                                                 img.style.width = '24px';
                                                 img.style.height = 'auto';
                                                 img.style.verticalAlign = 'middle';
                                                 img.style.display = 'inline-block';
                                                 img.className = 'rte-inserted-img';
                                                 
                                                 range.insertNode(img);
                                                 
                                                 var space = document.createTextNode(' ');
                                                 img.parentNode.insertBefore(space, img.nextSibling);
                                                 
                                                 var newRange = document.createRange();
                                                 newRange.setStartAfter(space);
                                                 newRange.collapse(true);
                                                 sel.removeAllRanges();
                                                 sel.addRange(newRange);
                                             }
                                             syncTextarea(activeEditor.id);
                                             quickIconPopover.style.display = 'none';
                                             floatingTrigger.style.display = 'none';
                                         }

                                         floatingTrigger.addEventListener('click', function(e) {
                                             e.preventDefault();
                                             e.stopPropagation();
                                             
                                             var rect = floatingTrigger.getBoundingClientRect();
                                             var scrollTop = window.pageYOffset || document.documentElement.scrollTop;
                                             var scrollLeft = window.pageXOffset || document.documentElement.scrollLeft;
                                             
                                             quickIconPopover.style.top = (rect.bottom + scrollTop + 4) + 'px';
                                             quickIconPopover.style.left = (rect.left + scrollLeft - 120) + 'px';
                                             quickIconPopover.style.display = 'flex';
                                             
                                             searchInput.value = '';
                                             renderGrid('');
                                             searchInput.focus();
                                         });

                                         function handleSelectionChange() {
                                             var sel = window.getSelection();
                                             if (!sel.rangeCount) {
                                                 hideTrigger();
                                                 return;
                                             }
                                             
                                             var node = sel.anchorNode;
                                             var editor = null;
                                             while (node) {
                                                 if (node.nodeType === 1 && node.classList.contains('rte-editor')) {
                                                     editor = node;
                                                     break;
                                                 }
                                                 node = node.parentNode;
                                             }
                                             
                                             if (!editor) {
                                                 hideTrigger();
                                                 return;
                                             }
                                             
                                             activeEditor = editor;
                                             saveSelection(editor.id);
                                             
                                             var range = sel.getRangeAt(0);
                                             var rects = range.getClientRects();
                                             var rect = rects.length > 0 ? rects[0] : null;
                                             
                                             if (!rect) {
                                                 var parent = range.startContainer;
                                                 if (parent.nodeType === 3) parent = parent.parentNode;
                                                 if (parent && parent !== editor) {
                                                     rect = parent.getBoundingClientRect();
                                                 }
                                             }
                                             
                                             if (rect && rect.top > 0) {
                                                 var scrollTop = window.pageYOffset || document.documentElement.scrollTop;
                                                 var scrollLeft = window.pageXOffset || document.documentElement.scrollLeft;
                                                 
                                                 floatingTrigger.style.top = (rect.top + scrollTop - 26) + 'px';
                                                 floatingTrigger.style.left = (rect.left + scrollLeft + (rect.width / 2) - 12) + 'px';
                                                 
                                                 if (quickIconPopover.style.display !== 'flex') {
                                                     floatingTrigger.style.display = 'flex';
                                                 }
                                             } else {
                                                 hideTrigger();
                                             }
                                         }

                                         function hideTrigger() {
                                             setTimeout(function() {
                                                 var activeEl = document.activeElement;
                                                 if (activeEl === searchInput || quickIconPopover.style.display === 'flex') {
                                                     return;
                                                 }
                                                 floatingTrigger.style.display = 'none';
                                             }, 200);
                                         }

                                         document.addEventListener('selectionchange', function() {
                                             handleSelectionChange();
                                         });

                                         document.addEventListener('mousedown', function(e) {
                                             if (!quickIconPopover.contains(e.target) && e.target !== floatingTrigger && !floatingTrigger.contains(e.target)) {
                                                 quickIconPopover.style.display = 'none';
                                             }
                                         });
                                                
                                                

                                            
                                        

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

            // File Manager image URL copy logic
            document.querySelectorAll('.copy-overlay').forEach(function(overlay) {
                overlay.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    var parent = this.closest('.copy-img-card');
                    if (!parent) return;
                    
                    var url = parent.getAttribute('data-url');
                    var absUrl = window.location.origin + url;
                    
                    navigator.clipboard.writeText(absUrl).then(function() {
                        var toast = document.createElement('div');
                        toast.style.position = 'fixed';
                        toast.style.bottom = '20px';
                        toast.style.right = '20px';
                        toast.style.background = '#10b981';
                        toast.style.color = '#fff';
                        toast.style.padding = '12px 24px';
                        toast.style.borderRadius = '30px';
                        toast.style.zIndex = '99999';
                        toast.style.fontWeight = 'bold';
                        toast.style.boxShadow = '0 10px 15px -3px rgba(0,0,0,0.1)';
                        toast.innerHTML = '<i class="bi bi-check-circle-fill me-2"></i> Copied URL to clipboard!';
                        document.body.appendChild(toast);
                        
                        setTimeout(function() {
                            toast.style.opacity = '0';
                            toast.style.transition = 'opacity 0.5s ease';
                            setTimeout(function() { toast.remove(); }, 500);
                        }, 2000);
                        
                        var imgUrlInput = document.getElementById('imgUrl');
                        if (imgUrlInput) {
                            imgUrlInput.value = absUrl;
                            imgUrlInput.style.borderColor = '#10b981';
                            setTimeout(function() { imgUrlInput.style.borderColor = ''; }, 1000);
                        }
                    });
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
        .copy-img-card:hover .copy-overlay {
            opacity: 1 !important;
        }
    </style>

    <!-- Rich Text Editor Custom Modal Overlay -->
    <div id="rteModal" class="rte-modal" style="display:none; position:fixed; z-index:9999; left:0; top:0; width:100%; height:100%; overflow:auto; background-color:rgba(15, 23, 42, 0.4); backdrop-filter:blur(4px); align-items:center; justify-content:center;">
        <div class="rte-modal-content" style="background-color:#fff; border-radius:16px; width:90%; max-width:420px; box-shadow:0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04); border:1px solid #e2e8f0; overflow:hidden; animation: rteSlideIn 0.2s cubic-bezier(0.16, 1, 0.3, 1);">
            <!-- Header -->
            <div class="rte-modal-header" style="padding:16px 20px; border-bottom:1px solid #f1f5f9; display:flex; align-items:center; justify-content:space-between; background-color:#f8fafc;">
                <h5 id="rteModalTitle" style="margin:0; font-size:1rem; font-weight:700; color:#0f172a; font-family: 'Inter', sans-serif;">Modal Title</h5>
                <button type="button" onclick="closeRteModal()" style="background:none; border:none; font-size:1.25rem; cursor:pointer; color:#64748b; transition:color 0.15s; padding:0; display:flex; align-items:center; justify-content:center;"><i class="bi bi-x-lg" style="font-size:1.1rem;"></i></button>
            </div>
            <!-- Body -->
            <div id="rteModalBody" style="padding:20px 20px 12px 20px;">
                <!-- Form fields go here dynamically -->
            </div>
            <!-- Footer -->
            <div class="rte-modal-footer" style="padding:12px 20px; background-color:#f8fafc; border-top:1px solid #f1f5f9; display:flex; justify-content:end; gap:8px;">
                <button type="button" class="btn btn-light btn-sm px-3 rounded-pill fw-bold" style="border:1px solid #cbd5e1; background-color:#fff; color:#475569;" onclick="closeRteModal()">Cancel</button>
                <button type="button" id="rteModalSubmit" class="btn btn-primary btn-sm px-4 rounded-pill fw-bold" style="background-color:#2563eb; border-color:#2563eb; color:#fff;">Apply</button>
            </div>
        </div>
    </div>

    <!-- Flaticon Web Browser Modal -->
    <div id="flaticonBrowserModal" class="rte-modal" style="display:none; position:fixed; z-index:99999; left:0; top:0; width:100%; height:100%; overflow:auto; background-color:rgba(15, 23, 42, 0.5); backdrop-filter:blur(6px); align-items:center; justify-content:center;">
        <div class="rte-modal-content" style="background-color:#fff; border-radius:20px; width:92%; max-width:1200px; height:85%; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25); border:1px solid #e2e8f0; overflow:hidden; display:flex; flex-direction:column; animation: rteSlideIn 0.25s cubic-bezier(0.16, 1, 0.3, 1);">
            <!-- Header -->
            <div class="rte-modal-header" style="padding:16px 24px; border-bottom:1px solid #f1f5f9; display:flex; align-items:center; justify-content:space-between; background-color:#f8fafc;">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary-soft text-primary p-2 fs-6" style="border-radius: 8px;"><i class="bi bi-globe"></i></span>
                    <h5 style="margin:0; font-size:1.1rem; font-weight:800; color:#0f172a; font-family: 'Inter', sans-serif;">Flaticon Browser Clone</h5>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <small class="text-secondary d-none d-md-inline-block">Search icons, right click to copy image address, then close and paste link</small>
                    <button type="button" onclick="closeFlaticonBrowser()" style="background:none; border:none; font-size:1.25rem; cursor:pointer; color:#64748b; transition:color 0.15s; padding:0; display:flex; align-items:center; justify-content:center;"><i class="bi bi-x-lg" style="font-size:1.2rem;"></i></button>
                </div>
            </div>
            <!-- Body (Split Screen / Fallback Guide) -->
            <div style="flex:1; display:flex; background:#fff; height:calc(100% - 60px);">
                <!-- Left panel: Guide & Direct Link Button -->
                <div style="width:320px; min-width:320px; background:#f8fafc; border-right:1px solid #e2e8f0; padding:24px; display:flex; flex-direction:column; justify-content:space-between; overflow-y:auto;">
                    <div>
                        <h6 style="margin:0 0 12px 0; font-weight:700; color:#0f172a; font-family:'Inter',sans-serif;"><i class="bi bi-info-circle text-primary me-2"></i>ណែនាំពីការចម្លង Icon</h6>
                        <p style="margin:0 0 20px 0; font-size:0.8rem; color:#64748b; line-height:1.4;">ដោយសារ Cloudflare Block ផ្ទាំងបញ្ជូនខាងស្តាំ (403 error) សូមចុចប៊ូតុងខាងក្រោមដើម្បីបើកស្វែងរកដោយផ្ទាល់៖</p>
                        
                        <div style="display:flex; flex-direction:column; gap:16px; margin-bottom:24px;">
                            <div style="display:flex; gap:10px;">
                                <span style="background:#2563eb; color:#fff; font-size:0.75rem; font-weight:bold; border-radius:50%; width:20px; height:20px; display:flex; align-items:center; justify-content:center; flex-shrink:0; margin-top:2px;">1</span>
                                <span style="font-size:0.8rem; color:#334155; line-height:1.4;">ចុចបើកវេបសាយ Flaticon ក្នុងផ្ទាំងថ្មី។</span>
                            </div>
                            <div style="display:flex; gap:10px;">
                                <span style="background:#2563eb; color:#fff; font-size:0.75rem; font-weight:bold; border-radius:50%; width:20px; height:20px; display:flex; align-items:center; justify-content:center; flex-shrink:0; margin-top:2px;">2</span>
                                <span style="font-size:0.8rem; color:#334155; line-height:1.4;">ចុចស្តាំលើរូប Icon ដែលចង់បាន រួចយក <strong>"Copy image address"</strong>។</span>
                            </div>
                            <div style="display:flex; gap:10px;">
                                <span style="background:#2563eb; color:#fff; font-size:0.75rem; font-weight:bold; border-radius:50%; width:20px; height:20px; display:flex; align-items:center; justify-content:center; flex-shrink:0; margin-top:2px;">3</span>
                                <span style="font-size:0.8rem; color:#334155; line-height:1.4;">បិទទំព័រនោះ រួចយក Link មក Paste ក្នុងប្រអប់បញ្ចូលរូបភាព។</span>
                            </div>
                        </div>
                    </div>
                    
                    <button type="button" class="btn btn-primary btn-sm w-100 py-2.5 rounded-pill fw-bold" style="background:#2563eb; border-color:#2563eb; color:#fff;" onclick="window.open('https://www.flaticon.com', 'FlaticonWindow', 'width=1200,height=800,scrollbars=yes')">
                        <i class="bi bi-box-arrow-up-right me-2"></i> បើកក្នុងផ្ទាំងថ្មី (New Window)
                    </button>
                </div>
                <!-- Right panel: Embedded Iframe -->
                <div style="flex:1; position:relative;">
                    <iframe id="modalBrowserIframe" src="" style="width:100%; height:100%; border:none;"></iframe>
                </div>
            </div>
        </div>
    </div>

    <script>
    function openFlaticonBrowser() {
        var iframe = document.getElementById('modalBrowserIframe');
        if (!iframe.src || iframe.src.indexOf('flaticon_browser.php') === -1) {
            iframe.src = 'flaticon_browser.php?url=https%3A%2F%2Fwww.flaticon.com%2F';
        }
        document.getElementById('flaticonBrowserModal').style.display = 'flex';
    }
    function closeFlaticonBrowser() {
        document.getElementById('flaticonBrowserModal').style.display = 'none';
    }

    // Auto clipboard-detector helper
    function checkAndPasteClipboard() {
        var modal = document.getElementById('rteModal');
        if (modal && modal.style.display === 'flex') {
            var imgUrlInput = document.getElementById('imgUrl');
            if (imgUrlInput) {
                navigator.clipboard.readText().then(function(text) {
                    text = text.trim();
                    // Validate if copied content is a URL and is an image or flaticon cdn link
                    if (text.indexOf('http') === 0 && (text.indexOf('cdn-icons') !== -1 || text.match(/\.(png|jpe?g|gif|svg|webp)/i))) {
                        if (imgUrlInput.value !== text) {
                            imgUrlInput.value = text;
                            // Flash visual feedback (green)
                            imgUrlInput.style.borderColor = '#10b981';
                            imgUrlInput.style.backgroundColor = '#ecfdf5';
                            setTimeout(function() {
                                imgUrlInput.style.borderColor = '';
                                imgUrlInput.style.backgroundColor = '';
                            }, 1200);
                        }
                    }
                }).catch(function(e) {
                    // Ignore clipboard reading errors
                });
            }
        }
    }
    // Trigger on both window focus and any click/mousedown on the window to bypass browser user gesture restrictions
    window.addEventListener('focus', checkAndPasteClipboard);
    window.addEventListener('click', checkAndPasteClipboard);
    </script>

    <style>
    @keyframes rteSlideIn {
        from { transform: scale(0.95) translateY(-10px); opacity: 0; }
        to { transform: scale(1) translateY(0); opacity: 1; }
    }
    .rte-form-group { margin-bottom: 14px; }
    .rte-form-group label { display: block; font-size: 0.8rem; font-weight: 700; color: #475569; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.05em; font-family: 'Inter', sans-serif; }
    .rte-form-control { width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.9rem; color: #0f172a; outline: none; transition: all 0.15s; background-color:#fff; }
    .rte-form-control:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.15); }
    </style>

<?php
$pageTitle = 'Settings';
$activeNav = 'settings';
$pageContent = ob_get_clean();
include 'layout.php';

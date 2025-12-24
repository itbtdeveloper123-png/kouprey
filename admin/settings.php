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
                $_POST['hero_background_image'] = '/kouprey/public/uploads/' . $fileName;
            }
        }
    }

    // Handle banner uploads - support dynamic banner IDs
    if (isset($_FILES['banner_image'])) {
        foreach ($_FILES['banner_image']['name'] as $bannerId => $fileName) {
            if ($_FILES['banner_image']['error'][$bannerId] == 0) {
                $uploadDir = '../public/uploads/banners/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
                $newFileName = 'banner-' . $bannerId . '-' . time() . '.' . $fileExtension;
                $uploadPath = $uploadDir . $newFileName;

                // Validate file type
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (in_array($_FILES['banner_image']['type'][$bannerId], $allowedTypes)) {
                    if (move_uploaded_file($_FILES['banner_image']['tmp_name'][$bannerId], $uploadPath)) {
                        $_POST['banner_' . $bannerId . '_image'] = $newFileName;
                    }
                }
            }
        }
    }

    // Handle banner management (add/remove)
    if (isset($_POST['add_banner'])) {
        // Find the next available banner ID
        $stmt = $pdo->prepare("SELECT setting_key FROM settings WHERE setting_key LIKE 'banner_%_title' AND language = ?");
        $stmt->execute([$currentLanguage]);
        $existingBanners = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $bannerIds = [];
        foreach ($existingBanners as $bannerKey) {
            if (preg_match('/banner_(\d+)_title/', $bannerKey, $matches)) {
                $bannerIds[] = (int)$matches[1];
            }
        }

        $nextId = empty($bannerIds) ? 1 : max($bannerIds) + 1;

        // Add default banner settings
        $defaultSettings = [
            "banner_{$nextId}_title" => "New Banner",
            "banner_{$nextId}_description" => "Banner description",
            "banner_{$nextId}_button_text" => "Learn More",
            "banner_{$nextId}_button_link" => "#",
            "banner_{$nextId}_image" => ""
        ];

        foreach ($defaultSettings as $key => $value) {
            $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value, language, category) VALUES (?, ?, ?, 'banners') ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $stmt->execute([$key, $value, $currentLanguage]);
        }
    }

    if (isset($_POST['remove_banner']) && isset($_POST['banner_id'])) {
        $bannerId = (int)$_POST['banner_id'];
        $bannerKeys = [
            "banner_{$bannerId}_title",
            "banner_{$bannerId}_description",
            "banner_{$bannerId}_button_text",
            "banner_{$bannerId}_button_link",
            "banner_{$bannerId}_image"
        ];

        foreach ($bannerKeys as $key) {
            $stmt = $pdo->prepare("DELETE FROM settings WHERE setting_key = ? AND language = ?");
            $stmt->execute([$key, $currentLanguage]);
        }
    }

    foreach ($_POST as $key => $value) {
        if ($key !== 'update_settings' && $key !== 'language' && $key !== 'add_banner' && $key !== 'remove_banner' && $key !== 'banner_id') {
            // Update or insert setting for current language
            $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value, language) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $stmt->execute([$key, $value, $currentLanguage]);
        }
    }
    $message = "Settings updated successfully!";
}

// Fetch settings for current language
$stmt = $pdo->prepare("SELECT * FROM settings WHERE language = ? ORDER BY category, setting_key");
$stmt->execute([$currentLanguage]);
$settings = $stmt->fetchAll();

// If no settings for this language, copy from English
if (empty($settings) && $currentLanguage !== 'en') {
    $stmt = $pdo->prepare("INSERT IGNORE INTO settings (setting_key, setting_value, setting_type, category, language, description)
                           SELECT setting_key, setting_value, setting_type, category, ?, description FROM settings WHERE language = 'en'");
    $stmt->execute([$currentLanguage]);
    // Re-fetch
    $stmt = $pdo->prepare("SELECT * FROM settings WHERE language = ? ORDER BY category, setting_key");
    $stmt->execute([$currentLanguage]);
    $settings = $stmt->fetchAll();
}

// Group settings by category manually
$groupedSettings = [];
foreach ($settings as $setting) {
    $groupedSettings[$setting['category']][] = $setting;
}

ob_start();
?>
    <div class="container-fluid py-4">
        <?php if (isset($message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i><?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Language Selector -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">Settings</h1>
            <div class="d-flex align-items-center">
                <label for="languageSelect" class="me-2">Language:</label>
                <select id="languageSelect" class="form-select" style="width: auto;" onchange="changeLanguage(this.value)">
                    <?php foreach ($supportedLanguages as $code => $name): ?>
                        <option value="<?php echo $code; ?>" <?php echo $code === $currentLanguage ? 'selected' : ''; ?>><?php echo $name; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <?php
        $categories = [
            'general' => ['icon' => 'bi-globe', 'title' => 'General Settings', 'description' => 'Basic website information and branding'],
            'contact' => ['icon' => 'bi-telephone', 'title' => 'Contact Information', 'description' => 'Company contact details'],
            'hero' => ['icon' => 'bi-image', 'title' => 'Hero Section', 'description' => 'Main banner and call-to-action content'],
            'banners' => ['icon' => 'bi-images', 'title' => 'Product Slide Banners', 'description' => 'Upload and manage banner images for the image slider'],
            'about' => ['icon' => 'bi-info-circle', 'title' => 'About Page', 'description' => 'About page content and sections'],
            'newsletter' => ['icon' => 'bi-envelope', 'title' => 'Newsletter', 'description' => 'Newsletter subscription settings'],
            'footer' => ['icon' => 'bi-file-text', 'title' => 'Footer', 'description' => 'Footer content and copyright'],
            'social' => ['icon' => 'bi-share', 'title' => 'Social Media', 'description' => 'Social media links and profiles'],
            'features' => ['icon' => 'bi-toggle-on', 'title' => 'Features & Content', 'description' => 'Website features and page content'],
            'product' => ['icon' => 'bi-box-seam', 'title' => 'Product Information', 'description' => 'Product details and specifications'],
            'reviews' => ['icon' => 'bi-star', 'title' => 'Reviews Section', 'description' => 'Customer reviews page content'],
            'pagination' => ['icon' => 'bi-list', 'title' => 'Pagination', 'description' => 'Content display settings'],
            'navigation' => ['icon' => 'bi-compass', 'title' => 'Navigation', 'description' => 'Navigation menu labels']
        ];
        ?>

        <form method="POST" enctype="multipart/form-data" action="?lang=<?php echo $currentLanguage; ?>">
            <!-- Nav tabs -->
            <ul class="nav nav-tabs" id="settingsTabs" role="tablist">
                <?php foreach ($categories as $category => $categoryInfo): ?>
                    <?php if (!isset($groupedSettings[$category])) continue; ?>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo ($category === 'general') ? 'active' : ''; ?>" id="<?php echo $category; ?>-tab" data-bs-toggle="tab" data-bs-target="#<?php echo $category; ?>" type="button" role="tab" aria-controls="<?php echo $category; ?>" aria-selected="<?php echo ($category === 'general') ? 'true' : 'false'; ?>">
                            <i class="bi <?php echo $categoryInfo['icon']; ?> me-1"></i><?php echo $categoryInfo['title']; ?>
                        </button>
                    </li>
                <?php endforeach; ?>
            </ul>

            <!-- Tab content -->
            <div class="tab-content" id="settingsTabContent">
                <?php foreach ($categories as $category => $categoryInfo): ?>
                    <?php if (!isset($groupedSettings[$category])) continue; ?>
                    <div class="tab-pane fade <?php echo ($category === 'general') ? 'show active' : ''; ?>" id="<?php echo $category; ?>" role="tabpanel" aria-labelledby="<?php echo $category; ?>-tab">
                        <div class="settings-card mt-4">
                            <div class="settings-header">
                                <div class="d-flex align-items-center">
                                    <div class="category-icon">
                                        <i class="bi <?php echo $categoryInfo['icon']; ?> text-white"></i>
                                    </div>
                                    <div>
                                        <h3 class="settings-title"><?php echo $categoryInfo['title']; ?></h3>
                                        <p class="settings-subtitle"><?php echo $categoryInfo['description']; ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body p-4">
                                <?php if ($category === 'banners'): ?>
                                    <!-- Special handling for banners -->
                                    <div class="alert alert-info mb-4">
                                        <i class="bi bi-info-circle me-2"></i>
                                        <strong>Image-Only Banners:</strong> These banners will display as full-width images in the product page slider. Only the image and alt text are used - no text or buttons will appear on the banners.
                                    </div>

                                    <?php
                                    // Group banner settings by banner ID
                                    $bannerGroups = [];
                                    foreach ($groupedSettings[$category] as $setting) {
                                        if (preg_match('/banner_(\d+)_(.+)/', $setting['setting_key'], $matches)) {
                                            $bannerId = $matches[1];
                                            $fieldType = $matches[2];
                                            $bannerGroups[$bannerId][$fieldType] = $setting;
                                        }
                                    }

                                    // Count active banners (those with images)
                                    $activeBanners = 0;
                                    foreach ($bannerGroups as $bannerId => $bannerFields) {
                                        if (!empty($bannerFields['image']['setting_value'])) {
                                            $activeBanners++;
                                        }
                                    }
                                    ?>

                                    <div class="row mb-4 banner-summary">
                                        <div class="col-12 col-sm-6 col-md-6">
                                            <div class="card bg-primary text-white">
                                                <div class="card-body text-center">
                                                    <h3 class="card-title mb-0"><?php echo count($bannerGroups); ?></h3>
                                                    <p class="card-text">Total Banners</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12 col-sm-6 col-md-6">
                                            <div class="card bg-success text-white">
                                                <div class="card-body text-center">
                                                    <h3 class="card-title mb-0"><?php echo $activeBanners; ?></h3>
                                                    <p class="card-text">Active Banners (with images)</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-4">
                                        <button type="submit" name="add_banner" class="btn btn-success btn-lg">
                                            <i class="bi bi-plus-circle-fill me-2"></i>Add New Banner
                                        </button>
                                        <small class="text-muted ms-2">Add up to 20 banners for your image slider</small>
                                    </div>

                                    <div class="row">
                                        <?php foreach ($bannerGroups as $bannerId => $bannerFields): ?>
                                            <div class="col-12 col-md-6 col-lg-6 col-xl-4 mb-4">
                                                <div class="card banner-card h-100">
                                                    <div class="card-header bg-light">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <h6 class="card-title mb-0">
                                                                <i class="bi bi-image me-2"></i>Banner <?php echo $bannerId; ?>
                                                            </h6>
                                                            <button type="submit" name="remove_banner" value="<?php echo $bannerId; ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('Are you sure you want to remove Banner <?php echo $bannerId; ?>?')">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                            <input type="hidden" name="banner_id" value="<?php echo $bannerId; ?>">
                                                        </div>
                                                    </div>
                                                    <div class="card-body">
                                                        <?php if (!empty($bannerFields['image']['setting_value'])): ?>
                                                            <div class="banner-preview mb-3">
                                                                <img src="<?php echo '/kouprey/public/uploads/banners/' . htmlspecialchars($bannerFields['image']['setting_value']); ?>" alt="Banner <?php echo $bannerId; ?>" class="img-fluid rounded shadow-sm" style="width: 100%; height: 150px; object-fit: cover;">
                                                            </div>
                                                        <?php else: ?>
                                                            <div class="banner-preview mb-3 text-center">
                                                                <div class="bg-light rounded d-flex align-items-center justify-content-center" style="width: 100%; height: 150px;">
                                                                    <i class="bi bi-image text-muted" style="font-size: 3rem;"></i>
                                                                </div>
                                                            </div>
                                                        <?php endif; ?>

                                                        <div class="mb-3">
                                                            <label class="form-label">Banner Image</label>
                                                            <input type="file" name="banner_image[<?php echo $bannerId; ?>]" class="form-control" accept="image/*">
                                                            <small class="text-muted">Upload a high-quality image (JPG, PNG, GIF). Recommended size: 1920x600px</small>
                                                        </div>

                                                        <div class="mb-3">
                                                            <label class="form-label">Alt Text</label>
                                                            <input type="text" name="banner_<?php echo $bannerId; ?>_title" value="<?php echo htmlspecialchars($bannerFields['title']['setting_value'] ?? ''); ?>" class="form-control" placeholder="Describe the banner image for accessibility">
                                                        </div>

                                                        <?php if (!empty($bannerFields['image']['setting_value'])): ?>
                                                            <div class="alert alert-success py-2">
                                                                <small><i class="bi bi-check-circle me-1"></i>This banner is active and will appear in the slider</small>
                                                            </div>
                                                        <?php else: ?>
                                                            <div class="alert alert-warning py-2">
                                                                <small><i class="bi bi-exclamation-triangle me-1"></i>No image uploaded - this banner will not be displayed</small>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>

                                <?php else: ?>
                                    <!-- Default handling for other categories -->
                                    <div class="row">
                                        <?php foreach ($groupedSettings[$category] as $setting): ?>
                                            <div class="col-md-6">
                                                <div class="setting-item">
                                                    <label class="setting-label">
                                                        <?php echo htmlspecialchars($setting['description'] ?? ucfirst(str_replace('_', ' ', $setting['setting_key']))); ?>
                                                    </label>
                                                    <?php if (isset($setting['description']) && $setting['description']): ?>
                                                        <div class="setting-description"><?php echo htmlspecialchars($setting['description']); ?></div>
                                                    <?php endif; ?>

                                                    <?php if ($setting['setting_key'] === 'hero_background_image'): ?>
                                                        <div class="mb-2">
                                                            <input type="file" class="form-control" name="hero_background_image" accept="image/*">
                                                            <?php if (!empty($setting['setting_value'])): ?>
                                                                <div class="mt-2">
                                                                    <small class="text-muted">Current image:</small><br>
                                                                    <img src="<?php echo htmlspecialchars($setting['setting_value']); ?>" alt="Current hero image" style="max-width: 200px; max-height: 100px; border-radius: 8px;">
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php elseif (in_array($setting['setting_key'], ['banner_free_shipping_image', 'banner_new_arrivals_image', 'banner_quality_image', 'banner_special_offer_image'])): ?>
                                                        <div class="mb-2">
                                                            <input type="file" class="form-control" name="<?php echo $setting['setting_key']; ?>" accept="image/*">
                                                            <?php if (!empty($setting['setting_value'])): ?>
                                                                <div class="mt-2">
                                                                    <small class="text-muted">Current banner:</small><br>
                                                                    <img src="<?php echo htmlspecialchars($setting['setting_value']); ?>" alt="Current banner" style="max-width: 200px; max-height: 100px; border-radius: 8px; border: 2px solid #e5e7eb;">
                                                                </div>
                                                            <?php endif; ?>
                                                            <div class="mt-2">
                                                                <small class="text-muted">Recommended size: 1200x400px. Supported formats: JPG, PNG, GIF, WebP</small>
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

            <div class="text-center mt-4">
                <button type="submit" name="update_settings" class="btn btn-primary btn-save">
                    <i class="bi bi-save me-2"></i>Save All Settings
                </button>
            </div>
        </form>
    </div>

    <script>
        function changeLanguage(lang) {
            window.location.href = '?lang=' + lang;
        }
    </script>

<?php
$pageTitle = 'Settings';
$activeNav = 'settings';
$pageContent = ob_get_clean();
include 'layout.php';

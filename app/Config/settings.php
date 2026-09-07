<?php
/**
 * Settings Helper Functions
 * Provides easy access to website settings stored in database
 */

require_once __DIR__ . '/database.php';

// Global memory cache for settings
$GLOBALS['__SETTINGS_CACHE__'] = [];
$GLOBALS['__SETTINGS_CATEGORY_CACHE__'] = [];

/**
 * Replace occurrences of 'កាហ្វេ' with 'គ្រឿងបន្ថែមរស់ជាតិ' and specific customized phrases
 */
function replaceCoffeeKhmer($data) {
    if (is_string($data)) {
        // Direct phrase replacements requested by user
        $data = str_replace(
            [
                'ស្វែងរកកម្រងផលិតផលសុីហ្វេគុណភាពពេញលេញរបស់យើង',
                'ស្វែងរកកម្រងផលិតផលកាហ្វេគុណភាពពេញលេញរបស់យើង',
                'ស្វែងយល់ពីបណ្តុំផលិតផលគ្រឿងបន្ថែមរស់ជាតិ និងតែបៃតងលំដាប់ពិសេសរបស់យើង',
                'ស្វែងយល់ពីបណ្តុំផលិតផលកាហ្វេ និងតែបៃតងលំដាប់ពិសេសរបស់យើង',
                'គ្រាប់គ្រឿងបន្ថែមរស់ជាតិពិសេស និងដំណោះស្រាយការបង្កើតដែលមានចីរភាព',
                'គ្រាប់កាហ្វេពិសេស និងដំណោះស្រាយការបង្កើតដែលមានចីរភាព'
            ],
            [
                'ស្វែងរកផលិតផលទាំងអស់របស់យើង',
                'ស្វែងរកផលិតផលទាំងអស់របស់យើង',
                'ស្វែងរកផលិតផលទាំងអស់របស់យើង',
                'ស្វែងរកផលិតផលទាំងអស់របស់យើង',
                'ធ្វើឱ្យគ្រឿងភេសជ្ជៈរបស់អ្នកកាន់តែមានរស់ជាតិ',
                'ធ្វើឱ្យគ្រឿងភេសជ្ជៈរបស់អ្នកកាន់តែមានរស់ជាតិ'
            ],
            $data
        );
        return str_replace('កាហ្វេ', 'គ្រឿងបន្ថែមរស់ជាតិ', $data);
    }
    if (is_array($data)) {
        foreach ($data as $k => $v) {
            $data[$k] = replaceCoffeeKhmer($v);
        }
    }
    return $data;
}

// Include shared catalog cache
require_once __DIR__ . '/catalog_cache.php';

/**
 * Load all settings for requested language (and English fallback) in 1 query
 */
function loadAllSettingsIntoCache($language = null) {
    global $pdo;
    if ($language === null) {
        $language = getCurrentLanguage();
    }

    if (isset($GLOBALS['__SETTINGS_CACHE__'][$language]) && !empty($GLOBALS['__SETTINGS_CACHE__'][$language])) {
        return;
    }

    if (!isset($GLOBALS['__SETTINGS_CACHE__'][$language])) {
        $GLOBALS['__SETTINGS_CACHE__'][$language] = [];
    }
    if (!isset($GLOBALS['__SETTINGS_CATEGORY_CACHE__'][$language])) {
        $GLOBALS['__SETTINGS_CATEGORY_CACHE__'][$language] = [];
    }

    // Try reading persistent settings cache file (expires in 1 hour or on admin save)
    $settingsCacheFile = sys_get_temp_dir() . '/kouprey_settings_' . md5($language) . '.cache';
    if (file_exists($settingsCacheFile) && (time() - filemtime($settingsCacheFile) < 3600)) {
        $cached = @unserialize(@file_get_contents($settingsCacheFile));
        if (is_array($cached) && isset($cached['settings']) && isset($cached['categories'])) {
            $GLOBALS['__SETTINGS_CACHE__'][$language] = $cached['settings'];
            $GLOBALS['__SETTINGS_CATEGORY_CACHE__'][$language] = $cached['categories'];
            return;
        }
    }

    try {
        $langs = array_values(array_unique([$language, 'en']));
        $inClause = implode(',', array_fill(0, count($langs), '?'));

        $stmt = $pdo->prepare("SELECT setting_key, setting_value, category, language FROM settings WHERE language IN ($inClause)");
        $stmt->execute($langs);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $lang = $row['language'];
            $key = $row['setting_key'];
            $val = replaceCoffeeKhmer($row['setting_value']);
            $cat = !empty($row['category']) ? $row['category'] : 'general';

            $GLOBALS['__SETTINGS_CACHE__'][$lang][$key] = $val;
            $GLOBALS['__SETTINGS_CATEGORY_CACHE__'][$lang][$cat][$key] = $val;
        }

        // Persist to file cache
        @file_put_contents($settingsCacheFile, serialize([
            'settings' => $GLOBALS['__SETTINGS_CACHE__'][$language],
            'categories' => $GLOBALS['__SETTINGS_CATEGORY_CACHE__'][$language]
        ]), LOCK_EX);

    } catch (Exception $e) {
        error_log("Settings cache error: " . $e->getMessage());
    }
}

/**
 * Clear memory cache (used when settings are saved in admin)
 */
function clearSettingsCache() {
    $GLOBALS['__SETTINGS_CACHE__'] = [];
    $GLOBALS['__SETTINGS_CATEGORY_CACHE__'] = [];
    // Invalidate product catalog cache and settings cache files
    $cacheDir = sys_get_temp_dir();
    $files = glob($cacheDir . '/kouprey_*.cache');
    if ($files) {
        foreach ($files as $f) {
            @unlink($f);
        }
    }
}

/**
 * Get a single setting value
 */
function getSetting($key, $default = '', $language = null) {
    if ($language === null) {
        $language = getCurrentLanguage();
    }

    loadAllSettingsIntoCache($language);

    $val = null;

    if (isset($GLOBALS['__SETTINGS_CACHE__'][$language][$key])) {
        $candidate = $GLOBALS['__SETTINGS_CACHE__'][$language][$key];
        // If requested language is English, but the database value contains Khmer characters,
        // treat it as incorrect translation/fallback and use default English value.
        if ($language === 'en' && preg_match('/[\x{1780}-\x{17FF}]/u', $candidate)) {
            $val = $default;
        } else {
            $val = $candidate;
        }
    } elseif ($language !== 'en' && isset($GLOBALS['__SETTINGS_CACHE__']['en'][$key])) {
        // Fallback to English
        $val = $GLOBALS['__SETTINGS_CACHE__']['en'][$key];
    }

    if ($language === 'km') {
        if ($key === 'company_name') {
            return 'ហ្គោ ហ្គោ';
        }
        if ($key === 'our_products_description' && ($val === null || empty($val) || $val === $default || strpos($val, 'Discover') !== false)) {
            $val = 'ស្វែងរកផលិតផលទាំងអស់របស់យើង';
        }
        if ($key === 'site_description' && ($val === null || empty($val) || $val === $default || strpos($val, 'Premium coffee') !== false)) {
            $val = 'ធ្វើឱ្យគ្រឿងភេសជ្ជៈរបស់អ្នកកាន់តែមានរស់ជាតិ';
        }
        if ($key === 'footer_text') {
            $val = str_replace('គោព្រៃ', 'ហ្គោ ហ្គោ', $val ?? $default);
        }
    }

    if ($val === null) {
        $val = $default;
    }

    if (($key === 'company_name' || $key === 'footer_text') && is_string($val)) {
        $val = str_replace('គោព្រៃ', 'ហ្គោ ហ្គោ', $val);
    }

    // Post-processing overrides for Rich Text Editor elements on Front-end (CDN Tailwind bypass)
    if ($key === 'social_banner_text') {
        // Strip block-level wrapper tags like <p> and <div> that cause layout breaks inside headings
        $val = preg_replace('/<\/?(p|div)[^>]*>/i', '', $val);

        if (strpos($val, '<img') !== false) {
            $val = preg_replace_callback('/<img([^>]+)>/i', function($matches) {
                $attrs = $matches[1];
                if (preg_match('/style\s*=\s*["\']([^"\']+)["\']/i', $attrs, $styleMatches)) {
                    $style = $styleMatches[1];
                    if (strpos($style, 'display') === false) {
                        $style .= '; display: inline-block !important;';
                    } else {
                        $style = preg_replace('/display\s*:\s*[^;]+/i', 'display: inline-block !important', $style);
                    }
                    $attrs = str_replace($styleMatches[0], 'style="' . $style . '"', $attrs);
                } else {
                    $attrs .= ' style="display: inline-block !important; vertical-align: middle;"';
                }
                return '<img' . $attrs . '>';
            }, $val);
        }
    }

    return replaceCoffeeKhmer($val);
}

/**
 * Get current language from session or cookie
 */
function getCurrentLanguage() {
    if (isset($_SESSION['site_lang'])) {
        return $_SESSION['site_lang'];
    }
    if (isset($_COOKIE['site_lang'])) {
        return $_COOKIE['site_lang'];
    }
    return 'km'; // default checked
}

/**
 * Set current language
 */
function setCurrentLanguage($language) {
    $_SESSION['site_lang'] = $language;
    setcookie('site_lang', $language, time() + (30 * 24 * 60 * 60), "/"); // 30 days, root path
}

/**
 * Get multiple settings by category
 */
function getSettingsByCategory($category, $language = null) {
    if ($language === null) {
        $language = getCurrentLanguage();
    }

    loadAllSettingsIntoCache($language);

    return $GLOBALS['__SETTINGS_CATEGORY_CACHE__'][$language][$category] ?? [];
}

/**
 * Get all settings
 */
function getAllSettings($language = null) {
    if ($language === null) {
        $language = getCurrentLanguage();
    }

    loadAllSettingsIntoCache($language);

    return $GLOBALS['__SETTINGS_CACHE__'][$language] ?? [];
}

/**
 * Check if a boolean setting is enabled
 */
function isSettingEnabled($key) {
    return getSetting($key, '0') === '1';
}

/**
 * Clear all persistent file caches (catalog and settings)
 */
if (!function_exists('clearAllKoupreyCaches')) {
    function clearAllKoupreyCaches() {
        $cacheDir = sys_get_temp_dir();
        $patterns = [
            $cacheDir . '/kouprey_catalog_*.cache',
            $cacheDir . '/kouprey_settings_*.cache'
        ];
        foreach ($patterns as $pattern) {
            $files = glob($pattern);
            if ($files) {
                foreach ($files as $f) {
                    @unlink($f);
                }
            }
        }
        $GLOBALS['__SETTINGS_CACHE__'] = [];
        $GLOBALS['__SETTINGS_CATEGORY_CACHE__'] = [];
    }
}
?>
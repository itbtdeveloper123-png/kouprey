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
 * Replace occurrences of 'កាហ្វេ' with 'គ្រឿងបន្ថែមរស់ជាតិ'
 */
function replaceCoffeeKhmer($data) {
    if (is_string($data)) {
        return str_replace('កាហ្វេ', 'គ្រឿងបន្ថែមរស់ជាតិ', $data);
    }
    if (is_array($data)) {
        foreach ($data as $k => $v) {
            $data[$k] = replaceCoffeeKhmer($v);
        }
    }
    return $data;
}

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

    if ($val === null) {
        return replaceCoffeeKhmer($default);
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
?>
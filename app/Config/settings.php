<?php
/**
 * Settings Helper Functions
 * Provides easy access to website settings stored in database
 */

require_once __DIR__ . '/database.php';

/**
 * Get a single setting value
 */
function getSetting($key, $default = '', $language = null) {
    global $pdo;

    if ($language === null) {
        $language = getCurrentLanguage();
    }

    $val = null;
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ? AND language = ?");
        $stmt->execute([$key, $language]);
        $result = $stmt->fetch();

        if ($result) {
            $candidate = $result['setting_value'];
            // If requested language is English, but the database value contains Khmer characters,
            // we treat it as an incorrect translation/fallback and use the default English value.
            if ($language === 'en' && preg_match('/[\x{1780}-\x{17FF}]/u', $candidate)) {
                $val = $default;
            } else {
                $val = $candidate;
            }
        } else {
            // Fallback to English if not found
            if ($language !== 'en') {
                $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ? AND language = 'en'");
                $stmt->execute([$key]);
                $result = $stmt->fetch();
                if ($result) {
                    $val = $result['setting_value'];
                }
            }
        }
    } catch (Exception $e) {
        return $default;
    }

    if ($val === null) {
        return $default;
    }

    // Post-processing overrides for Rich Text Editor elements on Front-end (CDN Tailwind bypass)
    if ($key === 'social_banner_text' && strpos($val, '<img') !== false) {
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

    return $val;
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
    global $pdo;

    if ($language === null) {
        $language = getCurrentLanguage();
    }

    try {
        $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE category = ? AND language = ?");
        $stmt->execute([$category, $language]);
        $results = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        return $results;
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Get all settings
 */
function getAllSettings($language = null) {
    global $pdo;

    if ($language === null) {
        $language = getCurrentLanguage();
    }

    try {
        $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE language = ?");
        $stmt->execute([$language]);
        $results = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        return $results;
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Check if a boolean setting is enabled
 */
function isSettingEnabled($key) {
    return getSetting($key, '0') === '1';
}
?>
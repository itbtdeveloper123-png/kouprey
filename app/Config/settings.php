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

    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ? AND language = ?");
        $stmt->execute([$key, $language]);
        $result = $stmt->fetch();

        if ($result) {
            return $result['setting_value'];
        }

        // Fallback to English if not found
        if ($language !== 'en') {
            $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ? AND language = 'en'");
            $stmt->execute([$key]);
            $result = $stmt->fetch();
            return $result ? $result['setting_value'] : $default;
        }

        return $default;
    } catch (Exception $e) {
        return $default;
    }
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
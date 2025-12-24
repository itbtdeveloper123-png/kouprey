<?php
require_once 'app/Config/database.php';
require_once 'app/Config/settings.php';

try {
    // Test settings table
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM settings');
    $result = $stmt->fetch();
    echo "Settings table exists with {$result['count']} records!\n";

    // Test getting a setting
    $siteTitle = getSetting('site_title');
    echo "Site title: $siteTitle\n";

    // Test boolean setting
    $newsletterEnabled = isSettingEnabled('enable_newsletter');
    echo "Newsletter enabled: " . ($newsletterEnabled ? 'Yes' : 'No') . "\n";

    echo "Settings system is working correctly!";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
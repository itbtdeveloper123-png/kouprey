<?php
/**
 * Clean up incorrectly saved settings from the database.
 * Run once: php cleanup_wrong_settings.php
 * Then delete this file.
 */

require_once 'app/Config/database.php';

echo "Cleaning up wrong settings...\n";

// Settings keys that are form action names, NOT real settings
$wrongKeys = [
    'convert_webp_all',
    'delete_file_manager_bulk',
];

// Delete exact matches
foreach ($wrongKeys as $key) {
    $stmt = $pdo->prepare("DELETE FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    echo "Deleted '$key': " . $stmt->rowCount() . " rows\n";
}

// Delete file_manager_replace_* entries
$stmt = $pdo->prepare("DELETE FROM settings WHERE setting_key LIKE 'file_manager_replace_%'");
$stmt->execute();
echo "Deleted 'file_manager_replace_*': " . $stmt->rowCount() . " rows\n";

echo "\nCleanup complete!\n";
echo "You can now delete this file (cleanup_wrong_settings.php).\n";

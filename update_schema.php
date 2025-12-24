<?php
require_once __DIR__ . '/app/Config/database.php';
global $pdo;

try {
    // Check existing indexes
    $result = $pdo->query('SHOW INDEX FROM settings');
    $indexes = $result->fetchAll(PDO::FETCH_ASSOC);
    echo "Existing indexes:\n";
    foreach ($indexes as $index) {
        echo "- " . $index['Key_name'] . " on " . $index['Column_name'] . " (" . ($index['Non_unique'] ? 'not unique' : 'unique') . ")\n";
    }

    // Find the unique index on setting_key
    $uniqueIndex = null;
    foreach ($indexes as $index) {
        if ($index['Column_name'] == 'setting_key' && $index['Non_unique'] == 0) {
            $uniqueIndex = $index['Key_name'];
            break;
        }
    }

    if ($uniqueIndex) {
        $pdo->exec("ALTER TABLE settings DROP INDEX `$uniqueIndex`");
        echo "Dropped unique index `$uniqueIndex` on setting_key.\n";
    } else {
        echo "No unique index on setting_key found.\n";
    }

    // Add new unique index on (setting_key, language)
    $pdo->exec('ALTER TABLE settings ADD UNIQUE KEY unique_setting_lang (setting_key, language)');
    echo "Added new unique index on (setting_key, language).\n";

    echo "Database schema updated successfully.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
<?php
require_once('./app/Config/database.php');
try {
    $pdo->exec("ALTER TABLE products ADD COLUMN detailed_description TEXT DEFAULT NULL");
    $pdo->exec("ALTER TABLE products ADD COLUMN ingredients TEXT DEFAULT NULL");
    $pdo->exec("ALTER TABLE products ADD COLUMN origin VARCHAR(100) DEFAULT NULL");
    $pdo->exec("ALTER TABLE products ADD COLUMN brewing_instructions TEXT DEFAULT NULL");
    $pdo->exec("ALTER TABLE products ADD COLUMN tasting_notes TEXT DEFAULT NULL");
    $pdo->exec("ALTER TABLE products ADD COLUMN weight VARCHAR(50) DEFAULT NULL");
    $pdo->exec("ALTER TABLE products ADD COLUMN roast_level VARCHAR(50) DEFAULT NULL");

    echo 'Detailed product fields added successfully!' . PHP_EOL;
} catch(Exception $e) {
    echo 'Failed to add fields: ' . $e->getMessage() . PHP_EOL;
}
?>
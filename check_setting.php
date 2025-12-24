<?php
require_once 'app/Config/database.php';
global $pdo;
try {
    $stmt = $pdo->prepare('SELECT * FROM settings WHERE setting_key = ? AND language = ?');
    $stmt->execute(['admin_detailed_product_info', 'km']);
    $result = $stmt->fetch();
    if ($result) {
        echo 'Found: ' . $result['setting_value'] . '\n';
    } else {
        echo 'Not found\n';
    }
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . '\n';
}
?>
<?php
require_once 'app/Config/database.php';
global $pdo;
try {
    $stmt = $pdo->prepare('INSERT INTO settings (setting_key, setting_value, setting_type, category, language, description) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
    $stmt->execute(['admin_detailed_product_info', 'ព័ត៌មានលម្អិតអំពីផលិតផល (ស្រេចចិត្ត)', 'text', 'admin', 'km', 'ចំណងជើងផ្នែកព័ត៌មានលម្អិតផលិតផល']);
    $stmt->execute(['admin_detailed_product_modal_title', 'ព័ត៌មានលម្អិតអំពីផលិតផល', 'text', 'admin', 'km', 'ចំណងជើងម៉ូដាលព័ត៌មានលម្អិតផលិតផល']);
    echo 'Khmer translations added\n';
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . '\n';
}
?>
<?php
require_once 'app/Config/database.php';
global $pdo;
$result = $pdo->query("SELECT setting_key FROM settings WHERE language = 'km' ORDER BY setting_key");
$existing = [];
while ($row = $result->fetch()) {
    $existing[] = $row['setting_key'];
}
echo 'Existing Khmer translations: ' . count($existing) . "\n";
echo 'Sample: ' . implode(', ', array_slice($existing, 0, 15)) . "\n";
?>
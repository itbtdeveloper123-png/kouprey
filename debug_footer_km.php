<?php
require_once 'app/Config/database.php';
$stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE language = 'km' AND setting_key LIKE 'footer_%'");
$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

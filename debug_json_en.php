<?php
require_once 'app/Config/database.php';
$ids = [5, 6, 73];
foreach($ids as $id) {
    $stmt = $pdo->prepare("SELECT custom_fields FROM products WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo $id . " | " . $row['custom_fields'] . "\n";
}

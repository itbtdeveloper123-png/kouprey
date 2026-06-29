<?php
require_once 'app/Config/database.php';
$stmt = $pdo->query("SELECT id, name, language FROM categories");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['id'] . ' | ' . $row['name'] . ' | ' . $row['language'] . "\n";
}

echo "\nProducts:\n";
$stmt = $pdo->query("SELECT id, name, category_id, language, custom_fields FROM products");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['id'] . ' | ' . $row['name'] . ' | ' . $row['category_id'] . ' | ' . $row['language'] . ' | ' . $row['custom_fields'] . "\n";
}

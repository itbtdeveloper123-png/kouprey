<?php
require_once 'app/Config/database.php';
echo "CATEGORIES:\n";
$stmt = $pdo->query("SELECT id, name, language, base_category_id FROM categories");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['id'] . ' | ' . $row['name'] . ' | ' . $row['language'] . ' | ' . $row['base_category_id'] . "\n";
}

echo "\nPOWDER PRODUCTS (EN):\n";
$stmt = $pdo->query("SELECT p.id, p.name, p.category_id, c.name as cat_name FROM products p JOIN categories c ON p.category_id = c.id WHERE c.name LIKE '%Powder%' AND p.language = 'en'");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['id'] . ' | ' . $row['name'] . ' | ' . $row['category_id'] . ' | ' . $row['cat_name'] . "\n";
}

echo "\nPOWDER PRODUCTS (KM):\n";
$stmt = $pdo->query("SELECT p.id, p.name, p.category_id, c.name as cat_name FROM products p JOIN categories c ON p.category_id = c.id WHERE c.name LIKE '%ម្សៅ%' AND p.language = 'km'");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['id'] . ' | ' . $row['name'] . ' | ' . $row['category_id'] . ' | ' . $row['cat_name'] . "\n";
}

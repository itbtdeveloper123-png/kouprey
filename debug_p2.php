<?php
require_once 'app/Config/database.php';
$stmt = $pdo->query("SELECT id, name, language, category_id, base_product_id, custom_fields FROM products WHERE base_product_id IN (5, 6, 73, 581, 582, 583)"); // Added some high IDs just in case
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['language'] . ' | ' . $row['id'] . ' | ' . $row['name'] . ' | ' . $row['category_id'] . ' | ' . $row['base_product_id'] . ' | ' . $row['custom_fields'] . "\n";
}

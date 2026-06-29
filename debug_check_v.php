<?php
require_once 'app/Config/database.php';
$stmt = $pdo->query("SELECT id, name, language, base_product_id FROM products WHERE base_product_id IN (5, 6, 73)");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['language'] . ' | ' . $row['id'] . ' | ' . $row['name'] . ' | ' . $row['base_product_id'] . "\n";
}

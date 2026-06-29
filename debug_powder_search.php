<?php
require_once 'app/Config/database.php';
$stmt = $pdo->query("SELECT id, name, language FROM categories WHERE name LIKE '%Powder%' OR name LIKE '%ម្សៅ%'");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['id'] . " | " . $row['name'] . " | " . $row['language'] . "\n";
}

<?php
require_once 'app/Config/database.php';
$stmt = $pdo->query("SELECT id, name, enabled FROM products WHERE id IN (24, 12, 74, 5, 6, 73)");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['id'] . " | " . $row['name'] . " | " . $row['enabled'] . "\n";
}

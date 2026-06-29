<?php
require_once 'app/Config/database.php';
$stmt = $pdo->query("SELECT id, name, language, base_category_id FROM categories");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['id'] . " | " . $row['name'] . " | " . $row['language'] . " | " . $row['base_category_id'] . "\n";
}

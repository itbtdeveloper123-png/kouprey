<?php
require_once 'app/Config/database.php';
$stmt = $pdo->query("SELECT id, name, category_id, custom_fields, language FROM products");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $cf = json_decode($row['custom_fields'] ?? '{}', true);
    if(($cf['show_in_collection'] ?? false) === true) {
        echo $row['language'] . ' | ' . $row['id'] . ' | ' . $row['name'] . ' | ' . $row['category_id'] . "\n";
    }
}

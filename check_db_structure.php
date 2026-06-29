<?php
require_once 'app/Config/database.php';

function getTableInfo($pdo, $table) {
    $stmt = $pdo->query("DESCRIBE $table");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->query("SELECT * FROM $table LIMIT 2");
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return ['columns' => $columns, 'data' => $data];
}

$info = [
    'categories' => getTableInfo($pdo, 'categories'),
    'products' => getTableInfo($pdo, 'products')
];

echo json_encode($info, JSON_PRETTY_PRINT);

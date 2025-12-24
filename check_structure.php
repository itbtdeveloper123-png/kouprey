<?php
require_once('./app/Config/database.php');
try {
    $stmt = $pdo->query('DESCRIBE products');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo 'Current products table structure:' . PHP_EOL;
    foreach ($columns as $column) {
        echo '- ' . $column['Field'] . ' (' . $column['Type'] . ')' . PHP_EOL;
    }
} catch(Exception $e) {
    echo 'Query failed: ' . $e->getMessage() . PHP_EOL;
}
?>
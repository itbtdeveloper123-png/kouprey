<?php
require_once '../app/Config/database.php';

echo "<h2>Settings Table Description</h2>";
try {
    $stmt = $pdo->query("DESCRIBE settings");
    $fields = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<table border='1' cellpadding='5' style='border-collapse:collapse;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($fields as $f) {
        echo "<tr>";
        foreach ($f as $k => $v) {
            echo "<td>" . htmlspecialchars($v ?? 'NULL') . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h2>Settings Indexes</h2>";
    $stmt = $pdo->query("SHOW INDEX FROM settings");
    $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<table border='1' cellpadding='5' style='border-collapse:collapse;'>";
    echo "<tr><th>Table</th><th>Non_unique</th><th>Key_name</th><th>Seq_in_index</th><th>Column_name</th></tr>";
    foreach ($indexes as $idx) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($idx['Table']) . "</td>";
        echo "<td>" . htmlspecialchars($idx['Non_unique']) . "</td>";
        echo "<td>" . htmlspecialchars($idx['Key_name']) . "</td>";
        echo "<td>" . htmlspecialchars($idx['Seq_in_index']) . "</td>";
        echo "<td>" . htmlspecialchars($idx['Column_name']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

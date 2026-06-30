<?php
require_once '../app/Config/database.php';

try {
    $stmt = $pdo->query("SELECT setting_key, category, language FROM settings ORDER BY category, setting_key");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<h2>All Setting Keys and Categories in Database:</h2>";
    echo "<table border='1' cellpadding='5' style='border-collapse:collapse;'>";
    echo "<tr><th>setting_key</th><th>category</th><th>language</th></tr>";
    foreach ($rows as $r) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($r['setting_key']) . "</td>";
        echo "<td>" . htmlspecialchars($r['category'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($r['language']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

<?php
require_once __DIR__ . '/app/Config/database.php';

try {
    $stmt = $pdo->prepare("UPDATE about SET hero_image = ?, person_image = ? WHERE id = 9");
    $stmt->execute([
        '/uploads/hero-bg-1765512788.png',
        'https://via.placeholder.com/400x300/8B4513/FFFFFF?text=Person'
    ]);
    echo "Test images set successfully!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
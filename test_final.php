<?php
require_once __DIR__ . '/app/Config/database.php';

try {
    // Get the latest about record
    $stmt = $pdo->query("SELECT * FROM about ORDER BY id DESC LIMIT 1");
    $about = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "Before update:\n";
    echo "ID: " . $about['id'] . "\n";
    echo "Title: " . $about['title'] . "\n";
    echo "Hero Image: " . ($about['hero_image'] ?: 'NULL') . "\n";
    echo "Person Image: " . ($about['person_image'] ?: 'NULL') . "\n\n";

    // Simulate updating only the title (should preserve images)
    $newTitle = $about['title'] . " (Test Update)";
    $stmt = $pdo->prepare("UPDATE about SET title = ?, content = ?, hero_image = ?, person_image = ? WHERE id = ?");
    $stmt->execute([$newTitle, $about['content'], $about['hero_image'], $about['person_image'], $about['id']]);

    // Check the result
    $stmt = $pdo->query("SELECT * FROM about ORDER BY id DESC LIMIT 1");
    $about = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "After update (preserving images):\n";
    echo "ID: " . $about['id'] . "\n";
    echo "Title: " . $about['title'] . "\n";
    echo "Hero Image: " . ($about['hero_image'] ?: 'NULL') . "\n";
    echo "Person Image: " . ($about['person_image'] ?: 'NULL') . "\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
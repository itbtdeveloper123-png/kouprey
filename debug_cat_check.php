<?php
require_once 'app/Config/database.php';
require_once 'app/Config/settings.php';

$currentLanguage = getCurrentLanguage();
echo "Current Language: $currentLanguage\n\n";

echo "Categories for $currentLanguage:\n";
$stmt = $pdo->prepare("SELECT id, name, base_category_id FROM categories WHERE language = ?");
$stmt->execute([$currentLanguage]);
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($categories);

echo "\nProducts (first 10) and their category_id:\n";
// This logic mimics product.php initialization
$stmt = $pdo->query("SELECT id, name, category_id, language, base_product_id FROM products WHERE enabled = 1");
$allProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$productsByBaseId = [];
foreach ($allProducts as $product) {
    $baseId = $product['base_product_id'];
    if (!isset($productsByBaseId[$baseId])) {
        $productsByBaseId[$baseId] = [];
    }
    $productsByBaseId[$baseId][$product['language']] = $product;
}

$displayProducts = [];
foreach ($productsByBaseId as $baseId => $langVersions) {
    if (isset($langVersions[$currentLanguage])) {
        $displayProducts[] = $langVersions[$currentLanguage];
    } elseif (isset($langVersions['en'])) {
        $displayProducts[] = $langVersions['en'];
    } else {
        $displayProducts[] = reset($langVersions);
    }
}

foreach (array_slice($displayProducts, 0, 10) as $p) {
    echo "Product: {$p['name']} (Language: {$p['language']}), Category ID: {$p['category_id']}\n";
}

echo "\nCategory IDs in Products table:\n";
$stmt = $pdo->query("SELECT DISTINCT category_id FROM products");
print_r($stmt->fetchAll(PDO::FETCH_COLUMN));

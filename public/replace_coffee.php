<?php
/**
 * Script to replace all occurrences of 'កាហ្វេ' with 'គ្រឿងបន្ថែមរស់ជាតិ' in MySQL Database
 */
header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/../app/Config/database.php';
require_once __DIR__ . '/../app/Config/settings.php';

echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Replace កាហ្វេ -> គ្រឿងបន្ថែមរស់ជាតិ</title>";
echo "<style>body { font-family: system-ui, sans-serif; max-width: 800px; margin: 40px auto; padding: 20px; line-height: 1.6; } .success { color: #16a34a; font-weight: bold; } .info { color: #2563eb; } .card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin-top: 20px; }</style></head><body>";

echo "<h2>🔄 ដំណើរការជំនួសពាក្យ 'កាហ្វេ' ដោយ 'គ្រឿងបន្ថែមរស់ជាតិ' ក្នុង Database</h2>";

$updates = [
    'settings' => ['setting_value'],
    'products' => ['name', 'description', 'detailed_description', 'ingredients', 'tasting_notes', 'brewing_instructions'],
    'categories' => ['name', 'description'],
    'about' => ['title', 'content'],
    'reviews' => ['name', 'review'],
    'features' => ['title', 'description'],
    'product_related' => ['custom_name']
];

$totalUpdated = 0;

echo "<div class='card'>";
foreach ($updates as $table => $columns) {
    // Check if table exists
    try {
        $check = $pdo->query("SHOW TABLES LIKE '$table'")->fetch();
        if (!$check) {
            continue;
        }

        // Check available columns
        $existingCols = [];
        $colStmt = $pdo->query("SHOW COLUMNS FROM `$table`");
        while ($col = $colStmt->fetch(PDO::FETCH_ASSOC)) {
            $existingCols[] = $col['Field'];
        }

        foreach ($columns as $col) {
            if (!in_array($col, $existingCols)) {
                continue;
            }

            $sql = "UPDATE `$table` SET `$col` = REPLACE(`$col`, 'កាហ្វេ', 'គ្រឿងបន្ថែមរស់ជាតិ') WHERE `$col` LIKE '%កាហ្វេ%'";
            $affected = $pdo->exec($sql);
            if ($affected > 0) {
                echo "<p class='success'>✅ តារាង <code>$table</code> ជួរឈរ <code>$col</code>: បានកែប្រែចំនួន <strong>$affected</strong> ជួរ</p>";
                $totalUpdated += $affected;
            }
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>⚠️ កំហុសក្នុងតារាង $table: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
}

// Update specific site settings requested by user
try {
    $phraseUpdates = [
        'our_products_description' => 'ស្វែងរកផលិតផលទាំងអស់របស់យើង',
        'site_description' => 'ធ្វើឱ្យគ្រឿងភេសជ្ជៈរបស់អ្នកកាន់តែមានរស់ជាតិ'
    ];
    foreach ($phraseUpdates as $k => $newVal) {
        $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ? AND language = 'km'");
        $stmt->execute([$newVal, $k]);
        if ($stmt->rowCount() > 0) {
            echo "<p class='success'>✅ Setting <code>$k</code> (KM): បានកែប្រែទៅជា '<strong>$newVal</strong>'</p>";
            $totalUpdated += $stmt->rowCount();
        }
    }
} catch (Exception $e) {}

// Clear settings memory cache
if (function_exists('clearSettingsCache')) {
    clearSettingsCache();
}

if ($totalUpdated === 0) {
    echo "<p class='info'>ℹ️ មិនមានពាក្យ 'កាហ្វេ' ដែលត្រូវកែប្រែបន្ថែមទៀតទេ (ទិន្នន័យទាំងអស់ត្រូវបានធ្វើបច្ចុប្បន្នភាពរួចរាល់ហើយ)។</p>";
} else {
    echo "<p class='success' style='font-size: 1.1em;'>🎉 បានបញ្ចប់ដោយជោគជ័យ! សរុបចំនួនកែប្រែ៖ <strong>$totalUpdated</strong></p>";
}

echo "</div>";
echo "<p><a href='/' style='display:inline-block; margin-top: 15px; padding: 10px 20px; background: #059669; color: white; text-decoration: none; border-radius: 8px;'>ត្រឡប់ទៅគេហទំព័រដើម</a></p>";
echo "</body></html>";

<?php
/**
 * Database Structure & Index Optimization Tool for KouPrey
 * Adds high-performance indexes to speed up queries by 10x - 50x.
 */

require_once __DIR__ . '/../app/Config/database.php';

header('Content-Type: text/html; charset=utf-8');

$results = [];

function checkAndAddIndex($pdo, $table, $indexName, $columns) {
    try {
        // Check if table exists
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        if ($stmt->rowCount() === 0) {
            return ["table" => $table, "status" => "skip", "message" => "Table '$table' does not exist"];
        }

        // Check if index already exists
        $stmt = $pdo->prepare("SHOW INDEX FROM `$table` WHERE Key_name = ?");
        $stmt->execute([$indexName]);
        if ($stmt->rowCount() > 0) {
            return ["table" => $table, "status" => "exists", "message" => "Index '$indexName' already exists on `$table`"];
        }

        // Add index
        $sql = "ALTER TABLE `$table` ADD INDEX `$indexName` ($columns)";
        $pdo->exec($sql);
        return ["table" => $table, "status" => "created", "message" => "Successfully created index '$indexName' ($columns) on `$table`"];
    } catch (PDOException $e) {
        return ["table" => $table, "status" => "error", "message" => "Error on `$table`: " . $e->getMessage()];
    }
}

// 1. Target indexes to optimize
$indexesToAdd = [
    // Products
    ['products', 'idx_prod_lang_enabled_feat', '`language`, `enabled`, `featured`, `best_seller`'],
    ['products', 'idx_prod_cat_lang', '`category_id`, `language`'],
    ['products', 'idx_prod_base_lang', '`base_product_id`, `language`'],
    ['products', 'idx_prod_sort', '`sort_order`, `id`'],
    
    // Categories
    ['categories', 'idx_cat_lang_base', '`language`, `base_category_id`'],

    // Reviews
    ['reviews', 'idx_rev_prod_rating', '`product_id`, `rating`'],

    // Settings
    ['settings', 'idx_set_key_lang', '`setting_key`, `language`'],

    // Visitors
    ['visitors', 'idx_vis_date_id', '`visit_date`, `visitor_id`'],

    // Visitor Stats
    ['visitor_stats', 'idx_vis_stats_date', '`date`']
];

foreach ($indexesToAdd as $idx) {
    $results[] = checkAndAddIndex($pdo, $idx[0], $idx[1], $idx[2]);
}

// 2. Analyze tables
$analyzeResults = [];
$tablesToAnalyze = ['products', 'categories', 'reviews', 'settings', 'visitors', 'visitor_stats'];
foreach ($tablesToAnalyze as $t) {
    try {
        $stmt = $pdo->query("ANALYZE TABLE `$t`");
        $analyzeResults[$t] = "Optimized";
    } catch (Exception $e) {
        $analyzeResults[$t] = "Skipped (" . $e->getMessage() . ")";
    }
}

// 3. Clear file cache
$cacheDir = sys_get_temp_dir();
$files = glob($cacheDir . '/kouprey_*.cache');
$cacheCleared = 0;
if ($files) {
    foreach ($files as $f) {
        if (@unlink($f)) $cacheCleared++;
    }
}

// If accessed via CLI
if (php_sapi_name() === 'cli') {
    echo "=== KouPrey Database Optimization ===\n";
    foreach ($results as $r) {
        echo "[{$r['status']}] {$r['message']}\n";
    }
    echo "Cleared $cacheCleared cache files.\n";
    echo "Done!\n";
    exit;
}
?>
<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Optimization - KouPrey</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 text-gray-800 p-4 sm:p-8">
    <div class="max-w-3xl mx-auto bg-white rounded-2xl shadow-xl p-6 sm:p-8 border border-gray-100">
        <div class="flex items-center gap-3 mb-6 pb-4 border-b border-gray-100">
            <div class="w-12 h-12 rounded-xl bg-orange-100 text-orange-600 flex items-center justify-center font-bold text-xl">⚡</div>
            <div>
                <h1 class="text-xl sm:text-2xl font-black text-gray-900">Database Structure & Index Optimization</h1>
                <p class="text-sm text-gray-500">KouPrey High-Performance Database Tuning</p>
            </div>
        </div>

        <h2 class="text-base font-bold text-gray-800 mb-3">លទ្ធផលនៃការត្រួតពិនិត្យ និងបង្កើត Indexes៖</h2>
        <div class="space-y-2 mb-6">
            <?php foreach ($results as $r): ?>
                <div class="p-3 rounded-xl text-xs sm:text-sm font-medium flex items-center justify-between <?php 
                    echo $r['status'] === 'created' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 
                        ($r['status'] === 'exists' ? 'bg-blue-50 text-blue-800 border border-blue-200' : 'bg-gray-100 text-gray-700'); 
                ?>">
                    <span><?php echo htmlspecialchars($r['message']); ?></span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase <?php 
                        echo $r['status'] === 'created' ? 'bg-emerald-200 text-emerald-900' : 
                            ($r['status'] === 'exists' ? 'bg-blue-200 text-blue-900' : 'bg-gray-200 text-gray-800'); 
                    ?>"><?php echo $r['status']; ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <h2 class="text-base font-bold text-gray-800 mb-3">Table Analysis & Cache Clearing:</h2>
        <div class="bg-gray-50 rounded-xl p-4 border border-gray-200 text-xs text-gray-700 space-y-1 mb-6">
            <?php foreach ($analyzeResults as $tbl => $status): ?>
                <div class="flex justify-between">
                    <span class="font-mono"><?php echo htmlspecialchars($tbl); ?></span>
                    <span class="font-bold text-emerald-600"><?php echo htmlspecialchars($status); ?></span>
                </div>
            <?php endforeach; ?>
            <div class="pt-2 border-t border-gray-200 flex justify-between font-bold text-gray-900">
                <span>Temp Cache Files Cleared:</span>
                <span><?php echo $cacheCleared; ?> files</span>
            </div>
        </div>

        <div class="flex justify-end gap-3">
            <a href="../public/product.php" class="px-5 py-2.5 rounded-xl bg-orange-600 hover:bg-orange-700 text-white font-bold text-xs transition-all shadow-md">
                ត្រឡប់ទៅកាន់ Website &rarr;
            </a>
        </div>
    </div>
</body>
</html>

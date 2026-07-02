<?php
// public/patch_run.php
$filePath = dirname(__DIR__) . '/admin/settings.php';
$content = file_get_contents($filePath);

echo "<h3>All execCommand occurrences:</h3><pre>";
$offset = 0;
while (($pos = strpos($content, 'execCommand', $offset)) !== false) {
    echo "Found execCommand at position $pos: \n";
    echo htmlspecialchars(substr($content, max(0, $pos - 40), 100)) . "\n\n";
    $offset = $pos + 11;
}
echo "</pre>";

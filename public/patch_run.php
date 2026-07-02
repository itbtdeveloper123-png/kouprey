<?php
// public/patch_run.php
$filePath = dirname(__DIR__) . '/admin/settings.php';
$content = file_get_contents($filePath);

$pos = strpos($content, 'document.execCommand(cmd');
if ($pos !== false) {
    echo "Found document.execCommand(cmd at position $pos.<br>";
    $start = max(0, $pos - 150);
    $length = 300;
    $slice = substr($content, $start, $length);
    echo "Slice:<br><pre>" . htmlspecialchars($slice) . "</pre><br>";
    echo "Hex representation of slice:<br><pre>";
    for ($i = 0; $i < strlen($slice); $i++) {
        $c = $slice[$i];
        echo sprintf("%02X ", ord($c));
        if (($i + 1) % 16 === 0) echo "\n";
    }
    echo "</pre>";
} else {
    echo "document.execCommand(cmd not found in settings.php";
}

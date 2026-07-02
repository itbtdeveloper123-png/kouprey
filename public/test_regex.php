<?php
// public/test_regex.php
$filePath = dirname(__DIR__) . '/admin/settings.php';
$content = file_get_contents($filePath);

$pattern = '/\}\s*else\s*\{\s*document\.execCommand\(\s*cmd\s*,\s*false\s*,\s*null\s*\);\s*\}/i';

if (preg_match($pattern, $content, $matches)) {
    echo "Found match!<pre>";
    print_r($matches);
    echo "</pre>";
} else {
    echo "No match found.";
    // Let's print out what is around lines 2545-2555 in settings.php
    $lines = explode("\n", str_replace("\r\n", "\n", $content));
    echo "Lines 2545-2555:<pre>";
    for ($i = 2540; $i <= 2560; $i++) {
        if (isset($lines[$i])) {
            echo ($i + 1) . ": " . htmlspecialchars($lines[$i]) . "\n";
        }
    }
    echo "</pre>";
}

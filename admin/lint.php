<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Kouprey Settings Syntax Check</h2>";

// Check if PHP can compile settings.php by loading it
$filename = 'settings.php';
if (!file_exists($filename)) {
    die("File $filename not found.");
}

$code = file_get_contents($filename);

// 1. Run PHP -l command (if shell_exec is available)
if (function_exists('shell_exec')) {
    $output = shell_exec("php -l " . escapeshellarg($filename) . " 2>&1");
    echo "<h3>Command line check (php -l):</h3>";
    echo "<pre>" . htmlspecialchars($output) . "</pre>";
}

// 2. Token-based syntax checker
echo "<h3>PHP Token validation:</h3>";
try {
    $tokens = token_get_all($code);
    echo "Token parsing: OK (All tokens parsed without triggering parser crash).<br>";
} catch (Throwable $e) {
    echo "<b style='color:red;'>Token parser error:</b> " . htmlspecialchars($e->getMessage()) . " on line " . $e->getLine() . "<br>";
}

// 3. Braces check
echo "<h3>Brackets & Braces check:</h3>";
$braces = 0;
$parentheses = 0;
$squares = 0;
$len = strlen($code);
$inString = false;
$stringChar = '';
$inComment = false;
$commentType = '';

for ($i = 0; $i < $len; $i++) {
    $char = $code[$i];
    $nextChar = ($i + 1 < $len) ? $code[$i+1] : '';
    
    // Handle comments
    if ($inComment) {
        if ($commentType === 'single' && ($char === "\n" || $char === "\r")) {
            $inComment = false;
        } elseif ($commentType === 'multi' && $char === '*' && $nextChar === '/') {
            $inComment = false;
            $i++;
        }
        continue;
    }
    
    // Handle strings
    if ($inString) {
        if ($char === $stringChar && $code[$i-1] !== '\\') {
            $inString = false;
        }
        continue;
    }
    
    // Check start of comment/string
    if ($char === '/' && $nextChar === '/') {
        $inComment = true;
        $commentType = 'single';
        $i++;
        continue;
    }
    if ($char === '/' && $nextChar === '*') {
        $inComment = true;
        $commentType = 'multi';
        $i++;
        continue;
    }
    if ($char === '#' && $nextChar !== '[') { // PHP single line comment (excluding php8 attribute)
        $inComment = true;
        $commentType = 'single';
        continue;
    }
    if ($char === '"' || $char === "'") {
        $inString = true;
        $stringChar = $char;
        continue;
    }
    
    // Bracket counting
    if ($char === '{') $braces++;
    if ($char === '}') $braces--;
    if ($char === '(') $parentheses++;
    if ($char === ')') $parentheses--;
    if ($char === '[') $squares++;
    if ($char === ']') $squares--;
}

echo "Braces balance: " . ($braces === 0 ? "OK" : "<b style='color:red;'>Unbalanced ($braces)</b>") . "<br>";
echo "Parentheses balance: " . ($parentheses === 0 ? "OK" : "<b style='color:red;'>Unbalanced ($parentheses)</b>") . "<br>";
echo "Square brackets balance: " . ($squares === 0 ? "OK" : "<b style='color:red;'>Unbalanced ($squares)</b>") . "<br>";

// Safe lint end

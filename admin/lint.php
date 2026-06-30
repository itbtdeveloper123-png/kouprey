<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Kouprey Settings Syntax Check</h2>";

$filename = 'settings.php';
if (!file_exists($filename)) {
    die("File $filename not found.");
}

$code = file_get_contents($filename);

// 1. Run PHP -l command
if (function_exists('shell_exec')) {
    $output = shell_exec("php -l " . escapeshellarg($filename) . " 2>&1");
    echo "<h3>Command line check (php -l):</h3>";
    echo "<pre>" . htmlspecialchars($output) . "</pre>";
}

// 2. Brackets, parentheses and braces matching line by line
echo "<h3>Line by line Braces/Parentheses matching:</h3>";

$lines = explode("\n", $code);
$bracesStack = [];
$parenthesesStack = [];
$inString = false;
$stringChar = '';
$inComment = false;
$commentType = '';

for ($lineIdx = 0; $lineIdx < count($lines); $lineIdx++) {
    $lineNum = $lineIdx + 1;
    $line = $lines[$lineIdx];
    $len = strlen($line);
    
    // Single line comments reset at end of line
    if ($inComment && $commentType === 'single') {
        $inComment = false;
    }
    
    for ($i = 0; $i < $len; $i++) {
        $char = $line[$i];
        $nextChar = ($i + 1 < $len) ? $line[$i+1] : '';
        
        if ($inComment) {
            if ($commentType === 'multi' && $char === '*' && $nextChar === '/') {
                $inComment = false;
                $i++;
            }
            continue;
        }
        
        if ($inString) {
            if ($char === $stringChar && ($i === 0 || $line[$i-1] !== '\\')) {
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
        if ($char === '#' && $nextChar !== '[') {
            $inComment = true;
            $commentType = 'single';
            continue;
        }
        if ($char === '"' || $char === "'") {
            $inString = true;
            $stringChar = $char;
            continue;
        }
        
        // Brackets matching
        if ($char === '{') {
            $bracesStack[] = ['char' => '{', 'line' => $lineNum, 'pos' => $i];
        } elseif ($char === '}') {
            if (empty($bracesStack)) {
                echo "<b style='color:red;'>Extra } found on line $lineNum</b><br>";
            } else {
                array_pop($bracesStack);
            }
        }
        
        if ($char === '(') {
            $parenthesesStack[] = ['char' => '(', 'line' => $lineNum, 'pos' => $i];
        } elseif ($char === ')') {
            if (empty($parenthesesStack)) {
                echo "<b style='color:red;'>Extra ) found on line $lineNum</b><br>";
            } else {
                array_pop($parenthesesStack);
            }
        }
    }
}

echo "<h4>Unclosed open brackets:</h4>";
if (empty($bracesStack) && empty($parenthesesStack)) {
    echo "<p style='color:green;'>No unclosed brackets/parentheses!</p>";
} else {
    foreach ($bracesStack as $b) {
        echo "<b style='color:red;'>Unclosed { opened on line {$b['line']} (pos {$b['pos']})</b><br>";
    }
    foreach ($parenthesesStack as $p) {
        echo "<b style='color:red;'>Unclosed ( opened on line {$p['line']} (pos {$p['pos']})</b><br>";
    }
}

<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Kouprey Settings Syntax Check</h2>";

// DB Dump check
try {
    require_once '../app/Config/database.php';
    echo "<h3>Database Settings for 'contact_us':</h3>";
    $stmt = $pdo->prepare("SELECT * FROM settings WHERE setting_key = 'contact_us'");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<table border='1' cellpadding='5' style='border-collapse:collapse;'>";
    echo "<tr><th>id</th><th>setting_key</th><th>setting_value</th><th>language</th><th>setting_type</th><th>category</th></tr>";
    foreach ($rows as $r) {
        echo "<tr>";
        foreach ($r as $k => $v) {
            echo "<td>" . htmlspecialchars($v ?? 'NULL') . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "DB Error: " . $e->getMessage() . "<br>";
}

$filename = 'settings.php';
if (!file_exists($filename)) {
    die("File $filename not found.");
}

$code = file_get_contents($filename);

// 1. Run PHP -l command with display_errors forced to on
if (function_exists('shell_exec')) {
    $output = shell_exec("php -d display_errors=on -l " . escapeshellarg($filename) . " 2>&1");
    echo "<h3>Command line check (php -l):</h3>";
    echo "<pre style='background:#f8f9fa;border:1px solid #ddd;padding:15px;color:#dc3545;font-weight:bold;'>" . htmlspecialchars($output) . "</pre>";
}

// 2. Token-based syntax checker
echo "<h3>PHP Token validation:</h3>";
try {
    $tokens = token_get_all($code);
    echo "Token parsing: OK (All tokens parsed without triggering parser crash).<br>";
} catch (Throwable $e) {
    echo "<b style='color:red;'>Token parser error:</b> " . htmlspecialchars($e->getMessage()) . " on line " . $e->getLine() . "<br>";
}

// 3. Line-by-line parenthesis/braces matching for diagnostic output
echo "<h3>Parenthesis/Braces Diagnostics:</h3>";
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
        
        if ($char === '{') {
            $bracesStack[] = ['line' => $lineNum, 'pos' => $i];
        } elseif ($char === '}') {
            if (empty($bracesStack)) {
                echo "Extra } on line $lineNum<br>";
            } else {
                array_pop($bracesStack);
            }
        }
        
        if ($char === '(') {
            $parenthesesStack[] = ['line' => $lineNum, 'pos' => $i];
        } elseif ($char === ')') {
            if (empty($parenthesesStack)) {
                echo "Extra ) on line $lineNum<br>";
            } else {
                array_pop($parenthesesStack);
            }
        }
    }
}

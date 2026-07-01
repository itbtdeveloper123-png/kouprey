<?php
echo "<h2>Git Diff of admin/settings.php</h2>";
if (function_exists('shell_exec')) {
    $output = shell_exec("git diff 0955b77 b055b3e admin/settings.php 2>&1");
    echo "<pre style='background:#f8f9fa;border:1px solid #ddd;padding:15px;font-family:monospace;'>" . htmlspecialchars($output) . "</pre>";
    
    echo "<h2>Git Diff of recent changes</h2>";
    $output2 = shell_exec("git diff HEAD~3 HEAD admin/settings.php 2>&1");
    echo "<pre style='background:#f8f9fa;border:1px solid #ddd;padding:15px;font-family:monospace;'>" . htmlspecialchars($output2) . "</pre>";
} else {
    echo "shell_exec is disabled.";
}

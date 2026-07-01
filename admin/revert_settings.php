<?php
// revert_settings.php
// Revert settings.php to git clean state
$out1 = shell_exec("git checkout admin/settings.php 2>&1");
echo "<h3>Git Checkout Output:</h3><pre>" . htmlspecialchars($out1) . "</pre>";
unlink(__FILE__);

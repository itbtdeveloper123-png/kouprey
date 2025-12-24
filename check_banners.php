<?php
require_once 'app/Config/database.php';
require_once 'app/Config/settings.php';
for ($i = 1; $i <= 3; $i++) {
    $image = getSetting('banner_' . $i . '_image');
    $title = getSetting('banner_' . $i . '_title');
    echo "Banner $i - Image: '$image', Title: '$title'\n";
}
?>
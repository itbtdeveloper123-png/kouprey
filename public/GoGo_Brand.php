<?php
// Prevent aggressive caching inside Telegram WebApp
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

// Enable output buffering with gzip compression if supported
if (!ob_get_level() && !headers_sent()) {
    if (extension_loaded('zlib') && !ini_get('zlib.output_compression')) {
        @ob_start('ob_gzhandler');
    } else {
        @ob_start();
    }
}
// Public entry point for GoGo Brand Mini App
require __DIR__ . '/../app/Views/GoGo_Brand.php';


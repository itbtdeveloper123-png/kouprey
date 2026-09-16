<?php
// Smart caching: Cache for 60s, revalidate in background for instant opening
header("Cache-Control: private, max-age=60, stale-while-revalidate=300");

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


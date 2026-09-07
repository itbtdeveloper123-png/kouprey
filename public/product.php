<?php
// Enable output buffering with gzip compression if supported
if (!ob_get_level() && !headers_sent()) {
    if (extension_loaded('zlib') && !ini_get('zlib.output_compression')) {
        @ob_start('ob_gzhandler');
    } else {
        @ob_start();
    }
}
// Public entry to display the product view
require __DIR__ . '/../app/Views/product.php';

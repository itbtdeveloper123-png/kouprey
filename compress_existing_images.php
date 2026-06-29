<?php
/**
 * Batch image compression script for KouPrey Coffee website
 * This script compresses all existing images in the upload directories
 */

require_once __DIR__ . '/app/Config/image_utils.php';

echo "Starting batch image compression...\n\n";

$directories = [
    __DIR__ . '/public/assets/images/products/',
    __DIR__ . '/public/assets/images/categories/',
    __DIR__ . '/public/uploads/',
    __DIR__ . '/public/uploads/banners/',
    __DIR__ . '/public/uploads/related/'
];

$totalProcessed = 0;
$totalSaved = 0;

foreach ($directories as $directory) {
    if (!is_dir($directory)) {
        echo "Directory does not exist: $directory\n";
        continue;
    }

    echo "Processing directory: $directory\n";

    $files = glob($directory . '*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE);
    $processed = 0;
    $saved = 0;

    foreach ($files as $file) {
        $fileName = basename($file);
        $filePath = $directory . $fileName;

        // Skip if it's already compressed (you can add logic here to check)
        // For now, we'll compress all images

        // Determine compression settings based on directory and file size
        $useCase = 'product'; // default
        if (strpos($directory, 'banners') !== false) {
            $useCase = 'banner';
        } elseif (strpos($directory, 'related') !== false) {
            $useCase = 'thumbnail';
        } elseif (strpos($directory, 'uploads') !== false && strpos($fileName, 'hero') !== false) {
            $useCase = 'hero';
        }

        // Use aggressive settings for large files (> 500KB)
        $fileSizeKB = $originalSize / 1024;
        if ($fileSizeKB > 500) {
            $settings = getAggressiveCompressionSettings($useCase);
        } else {
            $settings = getCompressionSettings($useCase);
        }
        $originalSize = filesize($filePath);

        if (compressImage($filePath, $filePath, $settings['quality'], $settings['maxWidth'], $settings['maxHeight'])) {
            $newSize = filesize($filePath);
            $savedBytes = $originalSize - $newSize;
            $saved += $savedBytes;
            $processed++;

            echo "  ✓ Compressed: $fileName (" . round($originalSize/1024, 1) . "KB → " . round($newSize/1024, 1) . "KB, saved " . round($savedBytes/1024, 1) . "KB)\n";
        } else {
            echo "  ✗ Failed to compress: $fileName\n";
        }
    }

    echo "  Directory complete: $processed images processed, " . round($saved/1024, 1) . "KB saved\n\n";

    $totalProcessed += $processed;
    $totalSaved += $saved;
}

echo "Batch compression complete!\n";
echo "Total images processed: $totalProcessed\n";
echo "Total space saved: " . round($totalSaved/1024, 1) . "KB (" . round($totalSaved/1024/1024, 1) . "MB)\n";
?>
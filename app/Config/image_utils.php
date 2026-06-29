<?php
/**
 * Image compression utilities for KouPrey Coffee website
 */

/**
 * Compress an image file to reduce file size while maintaining quality
 *
 * @param string $sourcePath Path to the source image file
 * @param string $targetPath Path to save the compressed image (can be same as source)
 * @param int $quality JPEG quality (0-100, default 85)
 * @param int $maxWidth Maximum width in pixels (0 = no limit)
 * @param int $maxHeight Maximum height in pixels (0 = no limit)
 * @return bool True on success, false on failure
 */
function compressImage($sourcePath, $targetPath = null, $quality = 85, $maxWidth = 0, $maxHeight = 0) {
    if ($targetPath === null) {
        $targetPath = $sourcePath;
    }

    // Check if file exists
    if (!file_exists($sourcePath)) {
        error_log("Image compression failed: Source file does not exist: $sourcePath");
        return false;
    }

    // Get image info
    $imageInfo = getimagesize($sourcePath);
    if (!$imageInfo) {
        error_log("Image compression failed: Invalid image file: $sourcePath");
        return false;
    }

    $mime = $imageInfo['mime'];
    $width = $imageInfo[0];
    $height = $imageInfo[1];

    // Create image resource based on type
    switch ($mime) {
        case 'image/jpeg':
            $image = imagecreatefromjpeg($sourcePath);
            break;
        case 'image/png':
            $image = imagecreatefrompng($sourcePath);
            break;
        case 'image/gif':
            $image = imagecreatefromgif($sourcePath);
            break;
        case 'image/webp':
            $image = imagecreatefromwebp($sourcePath);
            break;
        default:
            error_log("Image compression failed: Unsupported image type: $mime");
            return false;
    }

    if (!$image) {
        error_log("Image compression failed: Could not create image resource");
        return false;
    }

    // Resize if needed
    if ($maxWidth > 0 || $maxHeight > 0) {
        $newWidth = $width;
        $newHeight = $height;

        if ($maxWidth > 0 && $width > $maxWidth) {
            $newWidth = $maxWidth;
            $newHeight = ($height * $maxWidth) / $width;
        }

        if ($maxHeight > 0 && $newHeight > $maxHeight) {
            $newWidth = ($newWidth * $maxHeight) / $newHeight;
            $newHeight = $maxHeight;
        }

        if ($newWidth != $width || $newHeight != $height) {
            $resizedImage = imagecreatetruecolor($newWidth, $newHeight);

            // Preserve transparency for PNG/GIF
            if ($mime == 'image/png' || $mime == 'image/gif') {
                imagecolortransparent($resizedImage, imagecolorallocatealpha($resizedImage, 0, 0, 0, 127));
                imagealphablending($resizedImage, false);
                imagesavealpha($resizedImage, true);
            }

            imagecopyresampled($resizedImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagedestroy($image);
            $image = $resizedImage;
            $width = $newWidth;
            $height = $newHeight;
        }
    }

    // Save compressed image
    $success = false;
    // Determine output type based on target extension
    $targetExtension = strtolower(pathinfo($targetPath, PATHINFO_EXTENSION));
    
    // Map extension to mime type
    $outputMime = $mime; // Default to source mime
    switch ($targetExtension) {
        case 'jpg':
        case 'jpeg':
            $outputMime = 'image/jpeg';
            break;
        case 'png':
            $outputMime = 'image/png';
            break;
        case 'gif':
            $outputMime = 'image/gif';
            break;
        case 'webp':
            $outputMime = 'image/webp';
            break;
    }

    // Save compressed image
    $success = false;
    switch ($outputMime) {
        case 'image/jpeg':
            $success = imagejpeg($image, $targetPath, $quality);
            break;
        case 'image/png':
            // For PNG, quality is compression level (0-9)
            $pngQuality = 9 - min(9, floor($quality / 11.11)); // Convert 0-100 to 9-0
            $success = imagepng($image, $targetPath, $pngQuality);
            break;
        case 'image/gif':
            $success = imagegif($image, $targetPath);
            break;
        case 'image/webp':
            $success = imagewebp($image, $targetPath, $quality);
            break;
    }

    imagedestroy($image);

    if (!$success) {
        error_log("Image compression failed: Could not save compressed image to: $targetPath");
        return false;
    }

    // Log compression results
    $originalSize = filesize($sourcePath);
    $compressedSize = filesize($targetPath);
    $compressionRatio = $originalSize > 0 ? round(($originalSize - $compressedSize) / $originalSize * 100, 2) : 0;

    error_log("Image compressed: {$sourcePath} -> {$targetPath} | Original: " . round($originalSize/1024, 2) . "KB | Compressed: " . round($compressedSize/1024, 2) . "KB | Saved: {$compressionRatio}%");

    return true;
}

/**
 * Compress uploaded image file
 *
 * @param array $file $_FILES array element
 * @param string $targetPath Path to save the compressed image
 * @param int $quality JPEG quality (0-100, default 85)
 * @param int $maxWidth Maximum width in pixels (0 = no limit)
 * @param int $maxHeight Maximum height in pixels (0 = no limit)
 * @return bool True on success, false on failure
 */
function compressUploadedImage($file, $targetPath, $quality = 85, $maxWidth = 0, $maxHeight = 0) {
    // First move the uploaded file to target path
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        error_log("Failed to move uploaded file to: $targetPath");
        return false;
    }

    // Then compress it
    return compressImage($targetPath, $targetPath, $quality, $maxWidth, $maxHeight);
}

/**
 * Get optimal compression settings based on image type and intended use
 *
 * @param string $useCase 'product', 'banner', 'hero', 'thumbnail'
 * @return array Array with quality, maxWidth, maxHeight
 */
function getCompressionSettings($useCase = 'product') {
    switch ($useCase) {
        case 'thumbnail':
            return ['quality' => 80, 'maxWidth' => 300, 'maxHeight' => 300];
        case 'product':
            return ['quality' => 90, 'maxWidth' => 1200, 'maxHeight' => 1600];
        case 'banner':
            return ['quality' => 90, 'maxWidth' => 1920, 'maxHeight' => 1080];
        case 'hero':
            return ['quality' => 90, 'maxWidth' => 1920, 'maxHeight' => 1080];
        case 'logo':
            return ['quality' => 95, 'maxWidth' => 600, 'maxHeight' => 200];
        default:
            return ['quality' => 85, 'maxWidth' => 0, 'maxHeight' => 0];
    }
}

/**
 * Get aggressive compression settings for existing large images
 *
 * @param string $useCase 'product', 'banner', 'hero', 'thumbnail'
 * @return array Array with quality, maxWidth, maxHeight
 */
function getAggressiveCompressionSettings($useCase = 'product') {
    switch ($useCase) {
        case 'thumbnail':
            return ['quality' => 75, 'maxWidth' => 300, 'maxHeight' => 300];
        case 'product':
            return ['quality' => 80, 'maxWidth' => 1000, 'maxHeight' => 1000];
        case 'banner':
            return ['quality' => 85, 'maxWidth' => 1400, 'maxHeight' => 700];
        case 'hero':
            return ['quality' => 85, 'maxWidth' => 1400, 'maxHeight' => 900];
        default:
            return ['quality' => 80, 'maxWidth' => 1000, 'maxHeight' => 1000];
    }
}
?>
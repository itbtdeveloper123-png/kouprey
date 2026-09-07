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

            // Preserve transparency for PNG/GIF/WebP
            if ($mime == 'image/png' || $mime == 'image/gif' || $mime == 'image/webp') {
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
            imagealphablending($image, false);
            imagesavealpha($image, true);
            // For PNG, quality is compression level (0-9)
            $pngQuality = 9 - min(9, floor($quality / 11.11)); // Convert 0-100 to 9-0
            $success = imagepng($image, $targetPath, $pngQuality);
            break;
        case 'image/gif':
            imagealphablending($image, false);
            imagesavealpha($image, true);
            $success = imagegif($image, $targetPath);
            break;
        case 'image/webp':
            imagealphablending($image, false);
            imagesavealpha($image, true);
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

if (!defined('DEFAULT_REMOVE_BG_API_KEY')) {
    define('DEFAULT_REMOVE_BG_API_KEY', 'Q9jdVLq5EYCem7AE5mTbJrik');
}

/**
 * Get active Remove.bg API key from settings or default
 */
function getRemoveBgApiKey() {
    if (function_exists('getSetting')) {
        $key = getSetting('remove_bg_api_key', '');
        if (!empty($key)) return trim($key);
    }
    return DEFAULT_REMOVE_BG_API_KEY;
}

/**
 * Remove background of an image using Remove.bg API and convert directly to WebP with alpha transparency
 *
 * @param string $sourcePath Path to input image
 * @param string $targetWebpPath Path to save the final transparent WebP image
 * @param string|null $apiKey Remove.bg API key (optional)
 * @param array $options Additional options: ['size' => 'auto', 'type' => 'product', 'quality' => 90]
 * @return array ['success' => bool, 'error' => string|null, 'bg_removed' => bool, 'target' => string|null]
 */
function removeBgAndConvertToWebp($sourcePath, $targetWebpPath, $apiKey = null, $options = []) {
    if (empty($apiKey)) {
        $apiKey = getRemoveBgApiKey();
    }

    if (empty($apiKey)) {
        return ['success' => false, 'error' => 'Remove.bg API key is missing', 'bg_removed' => false];
    }

    if (!file_exists($sourcePath)) {
        return ['success' => false, 'error' => 'Source image file does not exist: ' . $sourcePath, 'bg_removed' => false];
    }

    if (!function_exists('curl_init')) {
        return ['success' => false, 'error' => 'cURL PHP extension is not installed', 'bg_removed' => false];
    }

    $size = $options['size'] ?? 'auto';
    $type = $options['type'] ?? 'product';
    $quality = $options['quality'] ?? 90;

    $ch = curl_init();
    $mimeType = function_exists('mime_content_type') ? mime_content_type($sourcePath) : 'image/jpeg';
    $cfile = new CURLFile($sourcePath, $mimeType, basename($sourcePath));

    $postFields = [
        'image_file' => $cfile,
        'size' => $size,
        'type' => $type,
        'format' => 'png',
    ];

    curl_setopt($ch, CURLOPT_URL, 'https://api.remove.bg/v1.0/removebg');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-Api-Key: ' . $apiKey,
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false || !empty($curlError)) {
        return ['success' => false, 'error' => 'cURL Error: ' . $curlError, 'bg_removed' => false];
    }

    if ($httpCode !== 200) {
        $json = json_decode($response, true);
        $errMsg = 'Remove.bg error (HTTP ' . $httpCode . ')';
        if (!empty($json['errors']) && is_array($json['errors'])) {
            $errTitles = array_column($json['errors'], 'title');
            $errMsg = implode(', ', $errTitles);
        }
        return ['success' => false, 'error' => $errMsg, 'bg_removed' => false];
    }

    // Convert PNG binary response to WebP with preserved alpha transparency
    if (!function_exists('imagewebp') || !function_exists('imagecreatefromstring')) {
        $pngPath = preg_replace('/\.webp$/i', '.png', $targetWebpPath);
        file_put_contents($pngPath, $response);
        return ['success' => true, 'target' => $pngPath, 'bg_removed' => true, 'format' => 'png'];
    }

    $image = @imagecreatefromstring($response);
    if (!$image) {
        return ['success' => false, 'error' => 'Failed to decode image from Remove.bg', 'bg_removed' => false];
    }

    // Preserve transparency
    imagealphablending($image, false);
    imagesavealpha($image, true);

    $targetDir = dirname($targetWebpPath);
    if (!is_dir($targetDir)) {
        @mkdir($targetDir, 0755, true);
    }

    $saved = imagewebp($image, $targetWebpPath, $quality);
    imagedestroy($image);

    if (!$saved) {
        return ['success' => false, 'error' => 'Failed to save WebP image to: ' . $targetWebpPath, 'bg_removed' => false];
    }

    return [
        'success' => true,
        'target' => $targetWebpPath,
        'bg_removed' => true,
        'format' => 'webp',
    ];
}

/**
 * Check Remove.bg account status and credits
 */
function checkRemoveBgAccount($apiKey = null) {
    if (empty($apiKey)) {
        $apiKey = getRemoveBgApiKey();
    }
    if (empty($apiKey)) {
        return ['success' => false, 'error' => 'API key is missing'];
    }
    if (!function_exists('curl_init')) {
        return ['success' => false, 'error' => 'cURL PHP extension is not installed'];
    }

    $ch = curl_init('https://api.remove.bg/v1.0/account');
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-Api-Key: ' . $apiKey]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false || !empty($curlError)) {
        return ['success' => false, 'error' => 'cURL error: ' . $curlError];
    }

    $json = json_decode($response, true);
    if ($httpCode !== 200) {
        $errMsg = 'HTTP ' . $httpCode;
        if (!empty($json['errors'])) {
            $errMsg = implode(', ', array_column($json['errors'], 'title'));
        }
        return ['success' => false, 'error' => $errMsg];
    }

    return ['success' => true, 'data' => $json['data'] ?? $json];
}

/**
 * Format bytes into human-readable string (KB, MB)
 */
function formatFileSize($bytes) {
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return round($bytes / 1024, 1) . ' KB';
    }
    return $bytes . ' B';
}

/**
 * Detect if an image already has a transparent background
 * Checks mime type (JPEGs are never transparent) and samples alpha channels in PNG/WebP/GIF
 *
 * @param string $filePath Path to local image file
 * @return bool True if image already has transparent background, false otherwise
 */
function isImageAlreadyTransparent($filePath) {
    if (!file_exists($filePath)) return false;

    $info = @getimagesize($filePath);
    if (!$info) return false;
    $mime = $info['mime'];

    // JPEGs can never have transparency
    if ($mime === 'image/jpeg' || $mime === 'image/jpg') {
        return false;
    }

    $img = null;
    if ($mime === 'image/png' && function_exists('imagecreatefrompng')) {
        $img = @imagecreatefrompng($filePath);
    } elseif ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) {
        $img = @imagecreatefromwebp($filePath);
    } elseif ($mime === 'image/gif' && function_exists('imagecreatefromgif')) {
        $img = @imagecreatefromgif($filePath);
    }

    if (!$img) return false;

    $width = imagesx($img);
    $height = imagesy($img);

    if ($width <= 0 || $height <= 0) {
        imagedestroy($img);
        return false;
    }

    // Check corners: in GD, ($rgba >> 24) & 0x7F gives alpha from 0 (opaque) to 127 (fully transparent)
    $corners = [
        [0, 0],
        [$width - 1, 0],
        [0, $height - 1],
        [$width - 1, $height - 1],
    ];

    $transparentCorners = 0;
    foreach ($corners as $pt) {
        $color = imagecolorat($img, $pt[0], $pt[1]);
        $alpha = ($color >> 24) & 0x7F;
        if ($alpha > 40) {
            $transparentCorners++;
        }
    }

    // If at least 2 corners are transparent, it's definitely a transparent cutout
    if ($transparentCorners >= 2) {
        imagedestroy($img);
        return true;
    }

    // Sample perimeter borders
    $transparentPerimeter = 0;
    $perimeterPoints = [
        [(int)($width * 0.25), 0],
        [(int)($width * 0.5), 0],
        [(int)($width * 0.75), 0],
        [(int)($width * 0.25), $height - 1],
        [(int)($width * 0.5), $height - 1],
        [(int)($width * 0.75), $height - 1],
        [0, (int)($height * 0.25)],
        [0, (int)($height * 0.5)],
        [0, (int)($height * 0.75)],
        [$width - 1, (int)($height * 0.25)],
        [$width - 1, (int)($height * 0.5)],
        [$width - 1, (int)($height * 0.75)],
    ];

    foreach ($perimeterPoints as $pt) {
        $color = imagecolorat($img, $pt[0], $pt[1]);
        $alpha = ($color >> 24) & 0x7F;
        if ($alpha > 40) {
            $transparentPerimeter++;
        }
    }

    imagedestroy($img);

    return ($transparentPerimeter >= 4);
}
?>
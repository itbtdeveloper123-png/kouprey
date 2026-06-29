# KouPrey Coffee - Image Compression Feature

## Overview
This feature automatically compresses images uploaded to the KouPrey Coffee website to improve performance and reduce loading times.

## How It Works

### Automatic Compression on Upload
When images are uploaded through the admin panel, they are automatically compressed using the following settings:

- **Product Images**: Max 800x800px, 85% quality
- **Banner Images**: Max 1200x600px, 90% quality
- **Hero Images**: Max 1200x800px, 90% quality
- **Thumbnails/Related Images**: Max 300x300px, 80% quality

### Supported Formats
- JPEG/JPG
- PNG
- GIF
- WebP

### Files Modified
- `app/Config/image_utils.php` - Core compression functions
- `admin/products.php` - Product and category image uploads
- `admin/about.php` - Hero and person image uploads
- `admin/settings.php` - Banner and hero background uploads

## Batch Compression
To compress existing images, run:
```bash
php compress_existing_images.php
```

This script will:
- Process all images in upload directories
- Apply appropriate compression settings based on image type
- Use more aggressive compression for large files (>500KB)
- Log compression results

## Benefits
- Faster website loading times
- Reduced bandwidth usage
- Better user experience on mobile devices
- Improved SEO performance
- Automatic optimization without manual intervention

## Technical Details

### Compression Settings
The system uses PHP's GD library for image processing and applies:
- Quality compression (JPEG/WebP)
- PNG compression level optimization
- Automatic resizing to prevent oversized images
- Preservation of transparency for PNG/GIF

### Error Handling
- Invalid images are logged but don't break the upload process
- Compression failures are logged with details
- Original images are preserved if compression fails

## Maintenance
- Monitor error logs for compression issues
- Run batch compression periodically for existing images
- Adjust compression settings in `image_utils.php` if needed

## Future Enhancements
- WebP conversion for better compression
- Lazy loading integration
- CDN optimization
- Progressive JPEG support
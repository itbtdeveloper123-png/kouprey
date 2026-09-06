/**
 * Client-side Image Compression Utility
 * Converts images to modern, high-performance WebP format while preserving 100% alpha transparency.
 * Prevents network stalls on large uploads while avoiding any background color attachment.
 */

export async function compressImageClient(file, maxWidth = 1600, maxHeight = 1600, quality = 0.88) {
  if (!file || !file.type || !file.type.startsWith('image/')) {
    return file;
  }

  // Preserve SVGs and animated GIFs
  if (file.type === 'image/svg+xml' || file.type === 'image/gif') {
    return file;
  }

  // If already a small WebP (under 300 KB), keep original
  if (file.type === 'image/webp' && file.size < 300 * 1024) {
    return file;
  }

  return new Promise((resolve) => {
    const reader = new FileReader();
    reader.onload = (e) => {
      const img = new Image();
      img.onload = () => {
        let width = img.width;
        let height = img.height;

        // Calculate proportional dimensions
        if (width > maxWidth || height > maxHeight) {
          if (width > height) {
            height = Math.round((height * maxWidth) / width);
            width = maxWidth;
          } else {
            width = Math.round((width * maxHeight) / height);
            height = maxHeight;
          }
        }

        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;
        const ctx = canvas.getContext('2d');

        if (!ctx) {
          return resolve(file);
        }

        // Keep canvas completely transparent (NO white background)
        ctx.clearRect(0, 0, width, height);
        ctx.drawImage(img, 0, 0, width, height);

        // Export to WebP format (supports alpha transparency & high compression)
        canvas.toBlob(
          (blob) => {
            if (blob && (blob.type === 'image/webp' || blob.size < file.size || file.type !== 'image/webp')) {
              const newName = file.name.replace(/\.[^.]+$/, '.webp');
              const compressedFile = new File([blob], newName, {
                type: 'image/webp',
                lastModified: Date.now(),
              });
              resolve(compressedFile);
            } else {
              // Fallback to PNG if WebP export is not supported by browser (preserves transparency)
              canvas.toBlob(
                (pngBlob) => {
                  if (pngBlob) {
                    const pngName = file.name.replace(/\.[^.]+$/, '.png');
                    resolve(new File([pngBlob], pngName, { type: 'image/png', lastModified: Date.now() }));
                  } else {
                    resolve(file);
                  }
                },
                'image/png'
              );
            }
          },
          'image/webp',
          quality
        );
      };

      img.onerror = () => resolve(file);
      img.src = e.target.result;
    };

    reader.onerror = () => resolve(file);
    reader.readAsDataURL(file);
  });
}

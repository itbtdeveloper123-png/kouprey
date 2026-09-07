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

/**
 * Format bytes into readable string (e.g., 2.4 MB, 140 KB)
 */
export function formatFileSize(bytes) {
  if (!bytes || isNaN(bytes)) return '0 B';
  if (bytes >= 1024 * 1024) {
    return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
  }
  if (bytes >= 1024) {
    return (bytes / 1024).toFixed(1) + ' KB';
  }
  return bytes + ' B';
}

/**
 * Detect if an image already has an alpha/transparent background
 * Checks corners and perimeter alpha channels in Canvas
 * @param {File} file
 * @returns {Promise<boolean>}
 */
export async function checkImageTransparency(file) {
  if (!file || !file.type) return false;
  if (file.type === 'image/jpeg' || file.type === 'image/jpg') {
    return false; // JPEGs can never have transparency
  }

  return new Promise((resolve) => {
    const reader = new FileReader();
    reader.onload = (e) => {
      const img = new Image();
      img.onload = () => {
        try {
          const canvas = document.createElement('canvas');
          const maxDim = 80; // Small size for instant detection
          const scale = Math.min(maxDim / img.width, maxDim / img.height, 1);
          canvas.width = Math.max(1, Math.round(img.width * scale));
          canvas.height = Math.max(1, Math.round(img.height * scale));
          const ctx = canvas.getContext('2d');
          if (!ctx) return resolve(false);

          ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
          const imgData = ctx.getImageData(0, 0, canvas.width, canvas.height);
          const data = imgData.data;

          // Corner alpha checks
          const corners = [
            0,
            (canvas.width - 1) * 4,
            (canvas.height - 1) * canvas.width * 4,
            (data.length - 4)
          ];
          let transparentCorners = 0;
          corners.forEach((idx) => {
            if (data[idx + 3] < 160) transparentCorners++;
          });

          if (transparentCorners >= 2) {
            return resolve(true);
          }

          // Sample perimeter pixels
          let transparentSamples = 0;
          let totalSamples = 0;
          for (let i = 3; i < data.length; i += 16) {
            totalSamples++;
            if (data[i] < 160) {
              transparentSamples++;
            }
          }

          const ratio = totalSamples > 0 ? (transparentSamples / totalSamples) : 0;
          resolve(ratio > 0.06);
        } catch {
          resolve(false);
        }
      };
      img.onerror = () => resolve(false);
      img.src = e.target.result;
    };
    reader.onerror = () => resolve(false);
    reader.readAsDataURL(file);
  });
}


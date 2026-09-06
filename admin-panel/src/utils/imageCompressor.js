/**
 * Client-side Image Compression Utility
 * Resizes large camera/phone photos before uploading to hosting server
 * Reduces upload time and network stalls dramatically (e.g. 3MB -> 100KB)
 */

export async function compressImageClient(file, maxWidth = 1200, maxHeight = 1200, quality = 0.85) {
  if (!file || !file.type || !file.type.startsWith('image/')) {
    return file;
  }

  // If already small (under 200 KB), keep original
  if (file.size < 200 * 1024 && !file.type.includes('png')) {
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

        // Fill with white background for transparent PNG converted to JPEG
        ctx.fillStyle = '#FFFFFF';
        ctx.fillRect(0, 0, width, height);
        ctx.drawImage(img, 0, 0, width, height);

        // Convert to WebP or JPEG
        canvas.toBlob(
          (blob) => {
            if (blob && blob.size < file.size) {
              const newName = file.name.replace(/\.[^.]+$/, '.jpg');
              const compressedFile = new File([blob], newName, {
                type: 'image/jpeg',
                lastModified: Date.now(),
              });
              resolve(compressedFile);
            } else {
              resolve(file);
            }
          },
          'image/jpeg',
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

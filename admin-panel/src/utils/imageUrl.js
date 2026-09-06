/**
 * Universal Image URL resolver for KouPrey Coffee
 * Ensures all local and hosting relative paths resolve properly to https://www.kouprey.asia
 */

export const HOSTING_ORIGIN = 'https://www.kouprey.asia';

export const PLACEHOLDER_IMAGE =
  'data:image/svg+xml;charset=UTF-8,%3Csvg%20width%3D%22200%22%20height%3D%22200%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%20200%20200%22%20preserveAspectRatio%3D%22none%22%3E%3Cdefs%3E%3Cstyle%20type%3D%22text%2Fcss%22%3E%23holder_text%20%7B%20fill%3A%239ca3af%3Bfont-weight%3Abold%3Bfont-family%3AArial%2C%20sans-serif%3Bfont-size%3A14pt%20%7D%20%3C%2Fstyle%3E%3C%2Fdefs%3E%3Cg%20id%3D%22holder%22%3E%3Crect%20width%3D%22200%22%20height%3D%22200%22%20fill%3D%22%23f3f4f6%22%3E%3C%2Frect%3E%3Cg%3E%3Ctext%20id%3D%22holder_text%22%20x%3D%2250%25%22%20y%3D%2250%25%22%20text-anchor%3D%22middle%22%20dy%3D%22.3em%22%3ENo%20Image%3C%2Ftext%3E%3C%2Fg%3E%3C%2Fg%3E%3C%2Fsvg%3E';

/**
 * Resolves any image URL to a full, working URL
 * @param {string} url - Image path or URL
 * @param {string} [defaultFolder='products'] - Default subfolder if only a filename is passed
 * @returns {string} - Resolved absolute image URL
 */
export function formatImageUrl(url, defaultFolder = 'products') {
  if (!url || typeof url !== 'string' || !url.trim()) {
    return PLACEHOLDER_IMAGE;
  }

  const clean = url.trim();

  // Data URLs or Blobs can be displayed immediately as-is
  if (clean.startsWith('data:') || clean.startsWith('blob:')) {
    return clean;
  }

  const isLocal = typeof window !== 'undefined' && (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1');

  // Absolute HTTP/HTTPS URLs
  if (clean.startsWith('http://') || clean.startsWith('https://')) {
    // In local dev, if an absolute URL points to kouprey.asia, convert to local proxy path to avoid TLS renegotiation / CORS
    if (isLocal && (clean.includes('kouprey.asia/kouprey/public/') || clean.includes('kouprey.asia/uploads/'))) {
      const match = clean.match(/https?:\/\/[^\/]+(\/.*)/);
      if (match && match[1]) {
        return match[1];
      }
    }
    return clean;
  }

  // Protocol-relative URLs
  if (clean.startsWith('//')) {
    return `https:${clean}`;
  }

  // When running locally on Vite dev server, use relative path so Vite proxies /kouprey and /uploads smoothly
  const prefix = isLocal ? '' : HOSTING_ORIGIN;

  // CRITICAL: Any uploads path (e.g. /kouprey/public/uploads/... or /uploads/...)
  // MUST be routed directly as /uploads/ because /kouprey/public/uploads/ triggers
  // a 52-second rewrite loop / stall on the hosting server, while /uploads/ loads in 1.3s!
  if (clean.includes('/uploads/')) {
    const uploadSubpath = clean.substring(clean.indexOf('/uploads/'));
    return `${prefix}${uploadSubpath}`;
  }
  if (clean.startsWith('uploads/')) {
    return `${prefix}/${clean}`;
  }

  // Already prefixed with /kouprey/public/ (for assets)
  if (clean.startsWith('/kouprey/public/')) {
    return `${prefix}${clean}`;
  }
  if (clean.startsWith('kouprey/public/')) {
    return `${prefix}/${clean}`;
  }

  // Assets path
  if (clean.startsWith('/assets/')) {
    return `${prefix}/kouprey/public${clean}`;
  }
  if (clean.startsWith('assets/')) {
    return `${prefix}/kouprey/public/${clean}`;
  }

  // Just a filename (e.g. "coffee-blend.png")
  if (!clean.includes('/')) {
    if (defaultFolder === 'banner' || defaultFolder === 'banners') {
      return `${prefix}/uploads/banners/${clean}`;
    }
    if (defaultFolder === 'categories') {
      return `${prefix}/kouprey/public/assets/images/categories/${clean}`;
    }
    if (defaultFolder === 'showcase') {
      return `${prefix}/uploads/showcase/${clean}`;
    }
    if (defaultFolder === 'related') {
      return `${prefix}/uploads/related/${clean}`;
    }
    if (defaultFolder === 'uploads') {
      return `${prefix}/uploads/${clean}`;
    }
    return `${prefix}/kouprey/public/assets/images/products/${clean}`;
  }

  // Fallback for any other relative path
  const normalized = clean.startsWith('/') ? clean : `/${clean}`;
  return `${prefix}/kouprey/public${normalized}`;
}

/**
 * Robust image error handler with automatic fallback attempts
 * Tries relative proxy, alternate folders, and finally inline SVG placeholder
 */
export function handleImageError(e, fallbackUrl = '') {
  const target = e.currentTarget || e.target;
  if (!target) return;

  const currentSrc = target.getAttribute('src') || target.src || '';

  // Prevent infinite loop if already using placeholder
  if (currentSrc.startsWith('data:image/svg') || currentSrc.includes('data:image/svg')) {
    return;
  }

  // Step 1: If URL contains /kouprey/public/uploads/, switch to direct /uploads/
  if (currentSrc.includes('/kouprey/public/uploads/')) {
    const filename = currentSrc.split('/kouprey/public/uploads/')[1];
    if (filename) {
      target.src = `/uploads/${filename}`;
      return;
    }
  }

  // Step 2: If failed with /uploads/ on localhost, try direct https://www.kouprey.asia/uploads/...
  if (currentSrc.includes('/uploads/') && !currentSrc.includes('www.kouprey.asia')) {
    const filename = currentSrc.split('/uploads/')[1];
    if (filename) {
      target.src = `https://www.kouprey.asia/uploads/${filename}`;
      return;
    }
  }

  // Step 3: If failed with absolute https://www.kouprey.asia/kouprey/public/assets/..., try relative
  if (currentSrc.includes('www.kouprey.asia/kouprey/public/')) {
    const rel = currentSrc.substring(currentSrc.indexOf('/kouprey/public/'));
    target.src = rel;
    return;
  }

  // Step 4: Try explicit fallbackUrl if provided
  if (fallbackUrl && currentSrc !== fallbackUrl) {
    target.src = fallbackUrl;
    return;
  }

  // Step 5: Final fallback to self-contained SVG placeholder (never fails over network!)
  target.src = PLACEHOLDER_IMAGE;
}

/**
 * Parses and replaces relative image src URLs inside HTML strings
 * @param {string} html - Raw HTML markup
 * @returns {string} - HTML with resolved image URLs
 */
export function formatHtmlImages(html) {
  if (!html || typeof html !== 'string') return '';
  return html.replace(/src=["'](\/[^"']+|assets\/[^"']+|uploads\/[^"']+)["']/gi, (match, p1) => {
    return `src="${formatImageUrl(p1)}"`;
  });
}

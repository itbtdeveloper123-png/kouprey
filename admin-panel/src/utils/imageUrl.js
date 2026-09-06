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

  // Data URLs, Blobs, or absolute HTTP/HTTPS URLs
  if (clean.startsWith('data:') || clean.startsWith('blob:') || clean.startsWith('http://') || clean.startsWith('https://')) {
    return clean;
  }

  // Protocol-relative URLs
  if (clean.startsWith('//')) {
    return `https:${clean}`;
  }

  // Already prefixed with /kouprey/public/
  if (clean.startsWith('/kouprey/public/')) {
    return `${HOSTING_ORIGIN}${clean}`;
  }
  if (clean.startsWith('kouprey/public/')) {
    return `${HOSTING_ORIGIN}/${clean}`;
  }

  // Assets path
  if (clean.startsWith('/assets/')) {
    return `${HOSTING_ORIGIN}/kouprey/public${clean}`;
  }
  if (clean.startsWith('assets/')) {
    return `${HOSTING_ORIGIN}/kouprey/public/${clean}`;
  }

  // Uploads path
  if (clean.startsWith('/uploads/')) {
    return `${HOSTING_ORIGIN}/kouprey/public${clean}`;
  }
  if (clean.startsWith('uploads/')) {
    return `${HOSTING_ORIGIN}/kouprey/public/${clean}`;
  }

  // Just a filename (e.g. "coffee-blend.png")
  if (!clean.includes('/')) {
    if (defaultFolder === 'banner' || defaultFolder === 'banners') {
      return `${HOSTING_ORIGIN}/kouprey/public/assets/images/banner/${clean}`;
    }
    if (defaultFolder === 'categories') {
      return `${HOSTING_ORIGIN}/kouprey/public/assets/images/categories/${clean}`;
    }
    if (defaultFolder === 'showcase') {
      return `${HOSTING_ORIGIN}/kouprey/public/uploads/showcase/${clean}`;
    }
    if (defaultFolder === 'related') {
      return `${HOSTING_ORIGIN}/kouprey/public/uploads/related/${clean}`;
    }
    if (defaultFolder === 'uploads') {
      return `${HOSTING_ORIGIN}/kouprey/public/uploads/${clean}`;
    }
    return `${HOSTING_ORIGIN}/kouprey/public/assets/images/products/${clean}`;
  }

  // Fallback for any other relative path
  const normalized = clean.startsWith('/') ? clean : `/${clean}`;
  return `${HOSTING_ORIGIN}/kouprey/public${normalized}`;
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

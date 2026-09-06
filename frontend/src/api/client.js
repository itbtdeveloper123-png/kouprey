// API Client for KouPrey React Frontend
const BASE_URL = import.meta.env.VITE_API_URL || '';

export function getImageUrl(path) {
  if (!path) return '';
  if (path.startsWith('http://') || path.startsWith('https://')) {
    return path;
  }
  // Clean leading slashes
  const cleanPath = path.startsWith('/') ? path : `/${path}`;
  return `${BASE_URL}${cleanPath}`;
}

export async function fetchBootstrap(lang = 'km') {
  try {
    const res = await fetch(`${BASE_URL}/api.php?action=get_bootstrap&lang=${encodeURIComponent(lang)}`);
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const data = await res.json();
    return data;
  } catch (err) {
    console.warn('API fetchBootstrap fallback:', err);
    return {
      success: true,
      language: lang,
      settings: {
        company_name: lang === 'km' ? 'គោព្រៃ KouPrey' : 'KouPrey',
        site_title: 'KouPrey Coffee & Matcha',
        phone: '012 345 678',
        email: 'info@kouprey.com',
        address: lang === 'km' ? 'រាជធានីភ្នំពេញ ប្រទេសកម្ពុជា' : 'Phnom Penh, Cambodia',
        social_facebook: 'https://facebook.com',
        social_telegram: 'https://t.me',
        social_tiktok: 'https://tiktok.com'
      },
      categories: [
        { id: 1, base_category_id: 1, name: lang === 'km' ? 'កាហ្វេ (Coffee)' : 'Coffee' },
        { id: 2, base_category_id: 2, name: lang === 'km' ? 'តែបៃតង (Matcha)' : 'Matcha' },
        { id: 3, base_category_id: 3, name: lang === 'km' ? 'គ្រឿងផ្សំ (Ingredients)' : 'Ingredients' }
      ],
      hero_images: [],
      banners: []
    };
  }
}

export async function fetchProducts({ lang = 'km', categoryId = null, baseCategoryId = null, search = '', featured = null, bestSeller = null } = {}) {
  try {
    const params = new URLSearchParams({
      action: 'get_products',
      lang
    });
    if (baseCategoryId) params.append('base_category_id', baseCategoryId);
    else if (categoryId) params.append('category_id', categoryId);
    if (search) params.append('search', search);
    if (featured !== null) params.append('featured', featured);
    if (bestSeller !== null) params.append('best_seller', bestSeller);

    const res = await fetch(`${BASE_URL}/api.php?${params.toString()}`);
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const data = await res.json();
    return data.products || [];
  } catch (err) {
    console.warn('API fetchProducts fallback:', err);
    return [];
  }
}

export async function fetchProductDetail(baseProductId, lang = 'km') {
  try {
    const res = await fetch(`${BASE_URL}/api.php?action=get_product_detail&base_product_id=${encodeURIComponent(baseProductId)}&lang=${encodeURIComponent(lang)}`);
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return await res.json();
  } catch (err) {
    console.warn('API fetchProductDetail fallback:', err);
    return { success: false, error: err.message };
  }
}

export async function fetchPageContent(page, lang = 'km') {
  try {
    const res = await fetch(`${BASE_URL}/api.php?action=get_page_content&page=${encodeURIComponent(page)}&lang=${encodeURIComponent(lang)}`);
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return await res.json();
  } catch (err) {
    console.warn('API fetchPageContent fallback:', err);
    return { success: false, error: err.message };
  }
}

export async function fetchAllReviews() {
  try {
    const res = await fetch(`${BASE_URL}/api.php?action=get_all_reviews`);
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return await res.json();
  } catch (err) {
    console.warn('API fetchAllReviews fallback:', err);
    return { success: true, reviews: [], avg_rating: 5.0, total_reviews: 0 };
  }
}

export async function submitReview(productId, name, review, rating) {
  try {
    const res = await fetch(`${BASE_URL}/api.php?action=add_review`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ product_id: productId, name, review, rating })
    });
    return await res.json();
  } catch (err) {
    return { success: false, error: err.message };
  }
}

export async function trackVisit() {
  try {
    fetch(`${BASE_URL}/api.php?action=track_visit`).catch(() => {});
  } catch (_) {}
}

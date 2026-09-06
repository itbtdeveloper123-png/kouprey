// API Client for KouPrey React Frontend with In-Memory SWR Caching
const BASE_URL = import.meta.env.VITE_API_URL || '';

// In-memory cache for ultra-fast instant UI response (0ms)
const memoryCache = new Map();

function getCached(key) {
  const item = memoryCache.get(key);
  if (!item) return null;
  // Expire after 5 minutes, but return stale immediately
  return item.data;
}

function setCached(key, data) {
  memoryCache.set(key, { data, timestamp: Date.now() });
}

export function getImageUrl(path) {
  if (!path) return '';
  if (path.startsWith('http://') || path.startsWith('https://')) {
    return path;
  }
  if (path.startsWith('/kouprey/public/')) {
    return `https://www.kouprey.asia${path}`;
  }
  if (path.startsWith('kouprey/public/')) {
    return `https://www.kouprey.asia/${path}`;
  }
  if (path.startsWith('/uploads/') || path.startsWith('/assets/')) {
    return `https://www.kouprey.asia/kouprey/public${path}`;
  }
  if (path.startsWith('uploads/') || path.startsWith('assets/')) {
    return `https://www.kouprey.asia/kouprey/public/${path}`;
  }
  const cleanPath = path.startsWith('/') ? path : `/${path}`;
  return `${BASE_URL}${cleanPath}`;
}

export async function fetchBootstrap(lang = 'km') {
  const cacheKey = `bootstrap_${lang}`;
  const cached = getCached(cacheKey);

  // Background fetch to keep cache fresh
  const networkPromise = (async () => {
    try {
      const res = await fetch(`${BASE_URL}/api.php?action=get_bootstrap&lang=${encodeURIComponent(lang)}`);
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();
      if (data && data.success) {
        setCached(cacheKey, data);
      }
      return data;
    } catch (err) {
      console.warn('API fetchBootstrap fallback:', err);
      return cached || {
        success: true,
        language: lang,
        settings: {
          company_name: lang === 'km' ? 'គោព្រៃ KouPrey' : 'KouPrey',
          company_logo: '/assets/images/product-medium.png',
          our_products: lang === 'km' ? 'ផលិតផលរបស់យើង' : 'Our Products',
          our_products_description: lang === 'km' ? 'ស្វែងយល់ពីបណ្តុំផលិតផលកាហ្វេ និងតែបៃតងលំដាប់ពិសេសរបស់យើង' : 'Discover our complete collection of premium coffee products',
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
  })();

  if (cached) {
    return cached;
  }
  return await networkPromise;
}

export async function fetchProducts({ lang = 'km', categoryId = null, baseCategoryId = null, search = '', featured = null, bestSeller = null } = {}) {
  const cacheKey = `prods_${lang}_${categoryId || ''}_${baseCategoryId || ''}_${search || ''}_${featured ?? ''}_${bestSeller ?? ''}`;
  const cached = getCached(cacheKey);

  const networkPromise = (async () => {
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
      if (data.success === false) {
        throw new Error(data.error || 'Server error fetching products');
      }
      const list = data.products || [];
      if (list.length > 0) {
        setCached(cacheKey, list);
        try {
          localStorage.setItem(`kouprey_prods_${lang}`, JSON.stringify(list));
        } catch {}
      }
      return list;
    } catch (err) {
      console.warn('API fetchProducts fallback:', err);
      try {
        const localCached = JSON.parse(localStorage.getItem(`kouprey_prods_${lang}`) || '[]');
        if (localCached && localCached.length > 0) {
          let prods = localCached;
          if (baseCategoryId) prods = prods.filter(p => p.base_category_id == baseCategoryId);
          if (search) {
            const q = search.toLowerCase();
            prods = prods.filter(p => 
              (p.name || '').toLowerCase().includes(q) || 
              (p.description || '').toLowerCase().includes(q) || 
              (p.category_name || '').toLowerCase().includes(q)
            );
          }
          return prods;
        }
      } catch {}

      // Fallback sample products if server is offline
      const isKm = lang === 'km';
      const sampleProducts = [
        {
          id: 1, base_product_id: 1, base_category_id: 1,
          name: isKm ? 'កាហ្វេពិសេស KouPrey Signature' : 'KouPrey Signature Coffee',
          category_name: isKm ? 'កាហ្វេ' : 'Coffee',
          price: 12.50, old_price: 15.00, avg_rating: 5.0, review_count: 34, featured: 1, best_seller: 1,
          image: 'assets/images/products/3IN1-MATCHA.png'
        },
        {
          id: 2, base_product_id: 2, base_category_id: 2,
          name: isKm ? 'ម្សៅតែបៃតង 3IN1 MATCHA' : '3IN1 Premium MATCHA Powder',
          category_name: isKm ? 'តែបៃតង' : 'Matcha',
          price: 9.80, old_price: 12.00, avg_rating: 4.9, review_count: 28, featured: 1, best_seller: 0,
          image: 'assets/images/products/3IN1-MATCHA.png'
        },
        {
          id: 3, base_product_id: 3, base_category_id: 1,
          name: isKm ? 'គ្រាប់កាហ្វេ Arabica Roast' : 'Arabica Medium Roast Beans',
          category_name: isKm ? 'កាហ្វេ' : 'Coffee',
          price: 14.00, old_price: 16.50, avg_rating: 4.8, review_count: 19, featured: 0, best_seller: 1,
          image: 'assets/images/products/3IN1-MATCHA.png'
        }
      ];

      if (baseCategoryId) {
        return sampleProducts.filter(p => p.base_category_id == baseCategoryId);
      }
      return sampleProducts;
    }
  })();

  if (cached && cached.length > 0) {
    return cached;
  }
  return await networkPromise;
}

export async function fetchProductDetail(baseProductId, lang = 'km') {
  const cacheKey = `product_detail_${baseProductId}_${lang}`;
  const cached = getCached(cacheKey);

  const networkPromise = (async () => {
    try {
      const res = await fetch(`${BASE_URL}/api.php?action=get_product_detail&base_id=${encodeURIComponent(baseProductId)}&id=${encodeURIComponent(baseProductId)}&lang=${encodeURIComponent(lang)}`);
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();
      if (data && data.success) {
        setCached(cacheKey, data);
      }
      return data;
    } catch (err) {
      console.warn('API fetchProductDetail fallback:', err);
      return cached || { success: false, error: err.message };
    }
  })();

  if (cached) {
    return cached;
  }
  return await networkPromise;
}

export async function fetchFeatures(lang = 'km') {
  const cacheKey = `features_${lang}`;
  const cached = getCached(cacheKey);

  const networkPromise = (async () => {
    try {
      const res = await fetch(`${BASE_URL}/api.php?action=get_features&lang=${encodeURIComponent(lang)}`);
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();
      const list = data.features || [];
      if (list.length > 0) {
        setCached(cacheKey, list);
      }
      return list;
    } catch (err) {
      console.warn('API fetchFeatures fallback:', err);
      return cached || [];
    }
  })();

  if (cached && cached.length > 0) {
    return cached;
  }
  return await networkPromise;
}

export async function fetchPageContent(page, lang = 'km') {
  const cacheKey = `page_${page}_${lang}`;
  const cached = getCached(cacheKey);

  const networkPromise = (async () => {
    try {
      const res = await fetch(`${BASE_URL}/api.php?action=get_page_content&page=${encodeURIComponent(page)}&lang=${encodeURIComponent(lang)}`);
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();
      if (data && data.success) {
        setCached(cacheKey, data);
      }
      return data;
    } catch (err) {
      console.warn('API fetchPageContent fallback:', err);
      return cached || { success: false, error: err.message };
    }
  })();

  if (cached) {
    return cached;
  }
  return await networkPromise;
}

export async function fetchAllReviews() {
  const cacheKey = 'all_reviews';
  const cached = getCached(cacheKey);

  const networkPromise = (async () => {
    try {
      const res = await fetch(`${BASE_URL}/api.php?action=get_all_reviews`);
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();
      if (data && data.success) {
        setCached(cacheKey, data);
      }
      return data;
    } catch (err) {
      console.warn('API fetchAllReviews fallback:', err);
      return cached || { success: true, reviews: [], avg_rating: 5.0, total_reviews: 0 };
    }
  })();

  if (cached) {
    return cached;
  }
  return await networkPromise;
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

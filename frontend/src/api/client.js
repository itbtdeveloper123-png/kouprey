// API Client for KouPrey React Frontend
const BASE_URL = import.meta.env.VITE_API_URL || '';

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
    if (data.success === false) {
      throw new Error(data.error || 'Server error fetching products');
    }
    return data.products || [];
  } catch (err) {
    console.warn('API fetchProducts fallback:', err);
    try {
      const cached = JSON.parse(localStorage.getItem(`kouprey_prods_${lang}`) || '[]');
      if (cached && cached.length > 0) {
        let prods = cached;
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

    // 18 Sample products to demonstrate full 9-item pagination when local server is offline
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
      },
      {
        id: 4, base_product_id: 4, base_category_id: 3,
        name: isKm ? 'ទឹកស៊ីរ៉ូ Vanilla Syrup' : 'Artisan Vanilla Syrup',
        category_name: isKm ? 'គ្រឿងផ្សំ' : 'Ingredients',
        price: 8.50, old_price: 10.00, avg_rating: 4.7, review_count: 15, featured: 0, best_seller: 0,
        image: 'assets/images/products/3IN1-MATCHA.png'
      },
      {
        id: 5, base_product_id: 5, base_category_id: 1,
        name: isKm ? 'កាហ្វេទឹកដោះគោ Espresso Blend' : 'Classic Espresso Milk Blend',
        category_name: isKm ? 'កាហ្វេ' : 'Coffee',
        price: 11.00, old_price: 13.50, avg_rating: 4.9, review_count: 42, featured: 1, best_seller: 1,
        image: 'assets/images/products/3IN1-MATCHA.png'
      },
      {
        id: 6, base_product_id: 6, base_category_id: 2,
        name: isKm ? 'តែបៃតង Uji Ceremonial Matcha' : 'Uji Ceremonial Grade Matcha',
        category_name: isKm ? 'តែបៃតង' : 'Matcha',
        price: 18.00, old_price: 22.00, avg_rating: 5.0, review_count: 56, featured: 1, best_seller: 0,
        image: 'assets/images/products/3IN1-MATCHA.png'
      },
      {
        id: 7, base_product_id: 7, base_category_id: 1,
        name: isKm ? 'កាហ្វេត្រជាក់ Cold Brew Blend' : 'Cold Brew Artisan Blend',
        category_name: isKm ? 'កាហ្វេ' : 'Coffee',
        price: 13.20, old_price: 15.00, avg_rating: 4.8, review_count: 22, featured: 0, best_seller: 1,
        image: 'assets/images/products/3IN1-MATCHA.png'
      },
      {
        id: 8, base_product_id: 8, base_category_id: 3,
        name: isKm ? 'ទឹកស៊ីរ៉ូ Caramel Drizzle' : 'Caramel Flavor Drizzle Syrup',
        category_name: isKm ? 'គ្រឿងផ្សំ' : 'Ingredients',
        price: 8.90, old_price: 11.00, avg_rating: 4.6, review_count: 11, featured: 0, best_seller: 0,
        image: 'assets/images/products/3IN1-MATCHA.png'
      },
      {
        id: 9, base_product_id: 9, base_category_id: 2,
        name: isKm ? 'ម្សៅតែបៃតង Matcha Latte Mix' : 'Matcha Latte Smooth Powder',
        category_name: isKm ? 'តែបៃតង' : 'Matcha',
        price: 10.50, old_price: 12.50, avg_rating: 4.9, review_count: 31, featured: 1, best_seller: 1,
        image: 'assets/images/products/3IN1-MATCHA.png'
      },
      {
        id: 10, base_product_id: 10, base_category_id: 1,
        name: isKm ? 'កាហ្វេខ្មៅ Dark Roast Special' : 'Dark Roast Special Beans',
        category_name: isKm ? 'កាហ្វេ' : 'Coffee',
        price: 13.90, old_price: 16.00, avg_rating: 4.8, review_count: 18, featured: 0, best_seller: 0,
        image: 'assets/images/products/3IN1-MATCHA.png'
      },
      {
        id: 11, base_product_id: 11, base_category_id: 3,
        name: isKm ? 'ម្សៅ Frappe Powder Base' : 'Creamy Frappe Base Powder',
        category_name: isKm ? 'គ្រឿងផ្សំ' : 'Ingredients',
        price: 9.50, old_price: 11.50, avg_rating: 4.7, review_count: 14, featured: 0, best_seller: 0,
        image: 'assets/images/products/3IN1-MATCHA.png'
      },
      {
        id: 12, base_product_id: 12, base_category_id: 1,
        name: isKm ? 'គ្រាប់កាហ្វេ Robusta Premium' : 'Robusta Bold Premium',
        category_name: isKm ? 'កាហ្វេ' : 'Coffee',
        price: 11.50, old_price: 13.00, avg_rating: 4.7, review_count: 25, featured: 0, best_seller: 1,
        image: 'assets/images/products/3IN1-MATCHA.png'
      },
      {
        id: 13, base_product_id: 13, base_category_id: 2,
        name: isKm ? 'តែបៃតង Roasted Hojicha' : 'Roasted Hojicha Tea Powder',
        category_name: isKm ? 'តែបៃតង' : 'Matcha',
        price: 12.00, old_price: 14.50, avg_rating: 4.9, review_count: 37, featured: 1, best_seller: 0,
        image: 'assets/images/products/3IN1-MATCHA.png'
      },
      {
        id: 14, base_product_id: 14, base_category_id: 3,
        name: isKm ? 'ទឹកស៊ីរ៉ូ Hazelnut Syrup' : 'Roasted Hazelnut Syrup',
        category_name: isKm ? 'គ្រឿងផ្សំ' : 'Ingredients',
        price: 8.50, old_price: 10.00, avg_rating: 4.6, review_count: 9, featured: 0, best_seller: 0,
        image: 'assets/images/products/3IN1-MATCHA.png'
      },
      {
        id: 15, base_product_id: 15, base_category_id: 1,
        name: isKm ? 'កាហ្វេ Drip Coffee Pack' : 'Single Drip Coffee Packets',
        category_name: isKm ? 'កាហ្វេ' : 'Coffee',
        price: 7.90, old_price: 9.50, avg_rating: 4.8, review_count: 20, featured: 0, best_seller: 1,
        image: 'assets/images/products/3IN1-MATCHA.png'
      },
      {
        id: 16, base_product_id: 16, base_category_id: 2,
        name: isKm ? 'តែបៃតង Organic Genmaicha' : 'Organic Genmaicha Powder',
        category_name: isKm ? 'តែបៃតង' : 'Matcha',
        price: 11.20, old_price: 13.50, avg_rating: 4.7, review_count: 16, featured: 0, best_seller: 0,
        image: 'assets/images/products/3IN1-MATCHA.png'
      },
      {
        id: 17, base_product_id: 17, base_category_id: 3,
        name: isKm ? 'ទឹកឃ្មុំធម្មជាតិ Wild Honey' : 'Natural Wild Honey Syrup',
        category_name: isKm ? 'គ្រឿងផ្សំ' : 'Ingredients',
        price: 9.90, old_price: 12.00, avg_rating: 5.0, review_count: 30, featured: 1, best_seller: 1,
        image: 'assets/images/products/3IN1-MATCHA.png'
      },
      {
        id: 18, base_product_id: 18, base_category_id: 1,
        name: isKm ? 'កាហ្វេ French Vanilla Roast' : 'French Vanilla Roast Coffee',
        category_name: isKm ? 'កាហ្វេ' : 'Coffee',
        price: 13.50, old_price: 15.50, avg_rating: 4.9, review_count: 27, featured: 1, best_seller: 0,
        image: 'assets/images/products/3IN1-MATCHA.png'
      }
    ];

    if (baseCategoryId) {
      return sampleProducts.filter(p => p.base_category_id == baseCategoryId);
    }
    return sampleProducts;
  }
}

export async function fetchProductDetail(baseProductId, lang = 'km') {
  try {
    const res = await fetch(`${BASE_URL}/api.php?action=get_product_detail&base_id=${encodeURIComponent(baseProductId)}&id=${encodeURIComponent(baseProductId)}&lang=${encodeURIComponent(lang)}`);
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return await res.json();
  } catch (err) {
    console.warn('API fetchProductDetail fallback:', err);
    return { success: false, error: err.message };
  }
}

export async function fetchFeatures(lang = 'km') {
  try {
    const res = await fetch(`${BASE_URL}/api.php?action=get_features&lang=${encodeURIComponent(lang)}`);
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const data = await res.json();
    return data.features || [];
  } catch (err) {
    console.warn('API fetchFeatures fallback:', err);
    return [];
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

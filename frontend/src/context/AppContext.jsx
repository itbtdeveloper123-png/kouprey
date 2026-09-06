import React, { createContext, useContext, useState, useEffect } from 'react';
import { fetchBootstrap, trackVisit } from '../api/client';

const AppContext = createContext();

export const UI_STRINGS = {
  km: {
    home: 'ទំព័រដើម',
    products: 'ផលិតផល',
    features: 'លក្ខណៈពិសេស',
    about: 'អំពីយើង',
    reviews: 'មតិយោបល់',
    privacy: 'គោលការណ៍ភាពឯកជន',
    terms: 'លក្ខខណ្ឌប្រើប្រាស់',
    search_placeholder: 'ស្វែងរកផលិតផលតាមឈ្មោះ...',
    all_categories: 'ទាំងអស់',
    add_to_cart: 'ដាក់ក្នុងកន្ត្រក',
    buy_now: 'ទិញឥឡូវនេះ',
    quick_view: 'មើលលម្អិត',
    reviews_count: 'ការវាយតម្លៃ',
    stars: 'ផ្កាយ',
    in_stock: 'មានក្នុងស្តុក',
    featured: 'ពេញនិយម',
    best_seller: 'លក់ដាច់បំផុត',
    related_products: 'ផលិតផលដែលទាក់ទង',
    write_review: 'សរសេរមតិវាយតម្លៃ',
    your_name: 'ឈ្មោះរបស់អ្នក',
    your_review: 'មតិយោបល់របស់អ្នក',
    rating: 'ពិន្ទុ',
    submit: 'បញ្ជូន',
    cancel: 'បោះបង់',
    no_products_found: 'រកមិនឃើញផលិតផលដែលត្រូវនឹងការស្វែងរកទេ',
    contact_us: 'ទំនាក់ទំនង',
    cart_empty: 'កន្ត្រករបស់អ្នកនៅទទេ',
    added_to_cart: 'បានបន្ថែមទៅកន្ត្រក!',
    view_details: 'មើលព័ត៌មានលម្អិត'
  },
  en: {
    home: 'Home',
    products: 'Products',
    features: 'Features',
    about: 'About Us',
    reviews: 'Reviews',
    privacy: 'Privacy Policy',
    terms: 'Terms of Service',
    search_placeholder: 'Search products by name...',
    all_categories: 'All',
    add_to_cart: 'Add to Cart',
    buy_now: 'Buy Now',
    quick_view: 'Quick View',
    reviews_count: 'reviews',
    stars: 'stars',
    in_stock: 'In Stock',
    featured: 'Featured',
    best_seller: 'Best Seller',
    related_products: 'Related Products',
    write_review: 'Write a Review',
    your_name: 'Your Name',
    your_review: 'Your Review',
    rating: 'Rating',
    submit: 'Submit Review',
    cancel: 'Cancel',
    no_products_found: 'No products found matching your search',
    contact_us: 'Contact Us',
    cart_empty: 'Your cart is empty',
    added_to_cart: 'Added to cart!',
    view_details: 'View Details'
  }
};

export function AppProvider({ children }) {
  const [language, setLanguage] = useState(() => {
    return localStorage.getItem('site_lang') || 'km';
  });
  const [settings, setSettings] = useState({});
  const [categories, setCategories] = useState([]);
  const [banners, setBanners] = useState([]);
  const [heroImages, setHeroImages] = useState([]);
  const [loading, setLoading] = useState(true);

  // Modals & UI States
  const [isSearchOpen, setIsSearchOpen] = useState(false);
  const [selectedProduct, setSelectedProduct] = useState(null);
  const [reviewProduct, setReviewProduct] = useState(null);
  
  // Cart
  const [cart, setCart] = useState(() => {
    try {
      return JSON.parse(localStorage.getItem('kouprey_cart')) || [];
    } catch {
      return [];
    }
  });

  const [toastMessage, setToastMessage] = useState(null);

  // Track visit once on mount
  useEffect(() => {
    trackVisit();
  }, []);

  // Save cart
  useEffect(() => {
    localStorage.setItem('kouprey_cart', JSON.stringify(cart));
  }, [cart]);

  // Load bootstrap settings & categories
  useEffect(() => {
    let isMounted = true;
    setLoading(true);
    fetchBootstrap(language).then((data) => {
      if (!isMounted) return;
      if (data && data.success) {
        setSettings(data.settings || {});
        setCategories(data.categories || []);
        setBanners(data.banners || []);
        setHeroImages(data.hero_images || []);
      }
      setLoading(false);
    });
    return () => { isMounted = false; };
  }, [language]);

  const switchLanguage = (lang) => {
    setLanguage(lang);
    localStorage.setItem('site_lang', lang);
    document.documentElement.lang = lang;
  };

  const showToast = (msg) => {
    setToastMessage(msg);
    setTimeout(() => {
      setToastMessage(null);
    }, 3000);
  };

  const addToCart = (product) => {
    setCart((prev) => {
      const existing = prev.find((item) => item.id === product.id);
      if (existing) {
        return prev.map((item) =>
          item.id === product.id ? { ...item, quantity: item.quantity + 1 } : item
        );
      }
      return [...prev, { ...product, quantity: 1 }];
    });
    showToast(`${t.added_to_cart}: ${product.name}`);
  };

  const t = UI_STRINGS[language] || UI_STRINGS.km;

  return (
    <AppContext.Provider
      value={{
        language,
        switchLanguage,
        t,
        settings,
        categories,
        banners,
        heroImages,
        loading,
        isSearchOpen,
        setIsSearchOpen,
        selectedProduct,
        setSelectedProduct,
        reviewProduct,
        setReviewProduct,
        cart,
        addToCart,
        toastMessage
      }}
    >
      {children}
    </AppContext.Provider>
  );
}

export function useApp() {
  const context = useContext(AppContext);
  if (!context) {
    throw new Error('useApp must be used within an AppProvider');
  }
  return context;
}

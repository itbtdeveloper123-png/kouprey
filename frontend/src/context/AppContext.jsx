import React, { createContext, useContext, useState, useEffect } from 'react';
import { fetchBootstrap, trackVisit } from '../api/client';
import { UI_STRINGS } from './strings';

const AppContext = createContext();

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

import React, { createContext, useContext, useState, useEffect } from 'react';
import { fetchBootstrap, trackVisit } from '../api/client';
import { UI_STRINGS } from './strings';

const AppContext = createContext();

const DEFAULT_SETTINGS = {
  company_logo: '/kouprey/public/uploads/company-logo-1769389302.png',
  company_name: 'គោព្រៃ',
  company_phone: '+855 93 839 883',
  company_email: 'info@kouprey.asia',
  company_address: '120408 សង្កាត់បឹងកក់ 2 ខណ្ឌទួលគោក រាជធានីភ្នំពេញ ប្រទេសកម្ពុជា។',
  site_description: 'គ្រាប់កាហ្វេពិសេស និងដំណោះស្រាយការបង្កើតដែលមានចីរភាព',
  footer_quick_links: 'តំណភ្ជាប់រហ័ស',
  footer_home: 'ទំព័រដើម',
  footer_products: 'ផលិតផល',
  footer_about_us: 'អំពីយើង',
  footer_reviews: 'មតិយោបល់',
  footer_admin: 'អ្នកគ្រប់គ្រង',
  social_banner_text: 'ប្រព័ន្ធបណ្តាញសង្គម',
  social_facebook: 'https://www.facebook.com/bossauveli98',
  social_instagram: 'https://instagram.com/bossauveli',
  social_telegram: 'https://t.me/Bos_Sauveli98',
  footer_text: '© 2026 គោព្រៃ. All rights reserved.',
  footer_privacy_policy: 'គោលការណ៍ឯកជនភាព',
  footer_terms_of_service: 'លក្ខខណ្ឌប្រើប្រាស់',
};

export function AppProvider({ children }) {
  const [language, setLanguage] = useState(() => {
    return localStorage.getItem('site_lang') || 'km';
  });

  const [settings, setSettings] = useState(() => {
    try {
      const cached = localStorage.getItem(`kouprey_settings_${language}`);
      if (cached) {
        return { ...DEFAULT_SETTINGS, ...JSON.parse(cached) };
      }
    } catch {}
    return {
      ...DEFAULT_SETTINGS,
      company_name: language === 'km' ? 'គោព្រៃ' : 'KouPrey',
      company_address: language === 'km'
        ? '120408 សង្កាត់បឹងកក់ 2 ខណ្ឌទួលគោក រាជធានីភ្នំពេញ ប្រទេសកម្ពុជា។'
        : '120408 Sangkat Boeung Kak 2, Khan Tuol Kouk, Phnom Penh, Cambodia.',
      site_description: language === 'km'
        ? 'គ្រាប់កាហ្វេពិសេស និងដំណោះស្រាយការបង្កើតដែលមានចីរភាព'
        : 'Premium coffee beans and sustainable brewing solutions',
      footer_quick_links: language === 'km' ? 'តំណភ្ជាប់រហ័ស' : 'Quick Links',
      footer_home: language === 'km' ? 'ទំព័រដើម' : 'Home',
      footer_products: language === 'km' ? 'ផលិតផល' : 'Products',
      footer_about_us: language === 'km' ? 'អំពីយើង' : 'About Us',
      footer_reviews: language === 'km' ? 'មតិយោបល់' : 'Reviews',
      footer_admin: language === 'km' ? 'អ្នកគ្រប់គ្រង' : 'Admin',
      social_banner_text: language === 'km' ? 'ប្រព័ន្ធបណ្តាញសង្គម' : 'Social Media',
      footer_text: language === 'km' ? '© 2026 គោព្រៃ. All rights reserved.' : '© 2026 KouPrey. All rights reserved.',
      footer_privacy_policy: language === 'km' ? 'គោលការណ៍ឯកជនភាព' : 'Privacy Policy',
      footer_terms_of_service: language === 'km' ? 'លក្ខខណ្ឌប្រើប្រាស់' : 'Terms of Service',
    };
  });

  const [categories, setCategories] = useState(() => {
    try {
      const cached = localStorage.getItem(`kouprey_categories_${language}`);
      return cached ? JSON.parse(cached) : [];
    } catch {
      return [];
    }
  });

  const [banners, setBanners] = useState([]);
  const [heroImages, setHeroImages] = useState([]);
  const [loading, setLoading] = useState(false);

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
    fetchBootstrap(language).then((data) => {
      if (!isMounted) return;
      if (data && data.success) {
        const mergedSettings = { ...DEFAULT_SETTINGS, ...(data.settings || {}) };
        setSettings(mergedSettings);
        try {
          localStorage.setItem(`kouprey_settings_${language}`, JSON.stringify(data.settings || {}));
          if (data.categories) {
            localStorage.setItem(`kouprey_categories_${language}`, JSON.stringify(data.categories));
          }
        } catch {}
        setCategories(data.categories || []);
        setBanners(data.banners || []);
        setHeroImages(data.hero_images || []);
      }
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

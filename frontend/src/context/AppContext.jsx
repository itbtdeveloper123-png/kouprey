import React, { createContext, useContext, useState, useEffect } from 'react';
import { fetchBootstrap, trackVisit } from '../api/client';
import { UI_STRINGS } from './strings';

const AppContext = createContext();

const DEFAULT_SETTINGS_KM = {
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
  our_products: 'ផលិតផលរបស់យើង',
  our_products_description: 'ស្វែងយល់ពីបណ្តុំផលិតផលកាហ្វេ និងតែបៃតងលំដាប់ពិសេសរបស់យើង',
  featured_products: 'ផលិតផលពិសេស',
  explore_more: 'ស្វែងយល់បន្ថែម',
  reviews_text: 'ការពិនិត្យ',
  modal_customer_reviews: 'ការពិនិត្យពីអតិថិជន',
  modal_write_review: 'សរសេរការពិនិត្យ',
  modal_weight: 'ទម្ងន់',
  modal_roast_level: 'កម្រិតលីង',
  modal_detailed_description: 'ព័ត៌មានលម្អិតអំពីផលិតផល',
  nav_home: 'ទំព័រដើម',
  nav_product: 'ផលិតផល',
  nav_features: 'លក្ខណៈពិសេស',
  nav_reviews: 'ការពិនិត្យ',
  nav_about: 'អំពីយើង',
  about_title: 'អំពី កូព្រៃ កាហ្វេ (KouPrey)',
  about_content: 'នេះជាការចាប់ផ្តើមនៃ KouPrey។ បន្ទាប់ពីបានភ្លក់រសជាតិកាហ្វេដ៏បរិសុទ្ធបំផុតនៅលើជម្រាលភ្នំ យើងបានប្រឹងប្រែងស្វែងរកអ្វីដែលស្រដៀងគ្នានេះនៅពេលត្រឡប់មកវិញ — ដូច្នេះហើយយើងបានសម្រេចចិត្តបង្កើតវាដោយខ្លួនឯង។',
  about_explore_button: 'ស្វែងរក',
  about_purpose_title: 'គោលបំណងរបស់យើង',
  about_purpose_content: 'នៅ KouPrey យើងធ្វើអ្វីៗខុសពីគេ — ដោយមានគោលបំណង។ គោលដៅរបស់យើងសាមញ្ញ៖ ធ្វើឱ្យកាហ្វេសរីរាង្គ មានសុខភាព និងឆ្ងាញ់អាចរកបានសម្រាប់មនុស្សជាច្រើនតាមដែលអាចធ្វើទៅបាន។ យើងប្តេជ្ញាចិត្តផ្តល់កាហ្វេដែលល្អជាងសម្រាប់អ្នក សហគមន៍ និងភពផែនដីរបស់យើង។',
  about_mission_title: 'រឿងរ៉ាវរបស់យើង',
  about_mission_content: 'យើងចាប់ផ្តើមដោយស្រឡាញ់កាហ្វេស្អាត និងបំណងចង់ចែករំលែកវា។ ក្នុងរយៈពេលជាច្រើនឆ្នាំ យើងបានសហការជាមួយអ្នកផ្តល់ បង្កើតការដុតរបស់យើង និងពង្រីកជួររបស់យើង — ទាំងអស់នេះខណៈពេលដែលរក្សាគុណភាព និងចីរភាពនៅខ្លឹមសារនៃអ្វីៗទាំងអស់ដែលយើងធ្វើ។',
  features_title: 'លក្ខណៈពិសេស',
  features_description: 'ស្វែងរកអ្វីដែលធ្វើឱ្យកាហ្វេរបស់យើងពិសេស — ចាប់ពីការស្វែងរក ដល់ការដុត និងការវេចខ្ចប់។',
  reviews_title: 'ពិនិត្យរបស់អតិថិជន',
  reviews_description: 'អានពីរបៀបដែលអតិថិជនចូលចិត្តកាហ្វេ និងផលិតផលរបស់យើង។',
  privacy_policy_title: 'គោលការណ៍ឯកជនភាព',
  privacy_policy_desc: 'យើងគោរពភាពឯកជនរបស់អ្នក និងប្តេជ្ញាការពារព័ត៌មានផ្ទាល់ខ្លួនរបស់អ្នក។',
  terms_of_service_title: 'លក្ខខណ្ឌប្រើប្រាស់',
  terms_of_service_desc: 'សូមអានលក្ខខណ្ឌនៃសេវាកម្មទាំងនេះដោយប្រុងប្រយ័ត្នមុនពេលប្រើប្រាស់សេវាកម្មរបស់យើង។',
  hero_title: 'រសជាតិដ៏ឈ្ងុយឆ្ងាញ់ គុណភាពខ្ពស់ពិតៗ',
  hero_subtitle: 'ផលិតផលកាហ្វេ និងតែបៃតងធម្មជាតិ លំដាប់ពិសេស ផ្តល់នូវថាមពល និងភាពស្រស់ស្រាយពេញមួយថ្ងៃ។',
  syrup_collection_title: 'បណ្តុំសុីរ៉ូសម្រិតសម្រាំង',
  syrup_collection_desc: 'បង្កើនឱជារសភេសជ្ជៈរបស់អ្នកជាមួយសុីរ៉ូធម្មជាតិដ៏ឈ្ងុយឆ្ងាញ់របស់យើង។ បង្កើតឡើងយ៉ាងផ្ចិតផ្ចង់ ដើម្បីនាំមកនូវភាពស្រស់បំព្រងដល់កាហ្វេ ស្រាក្រឡុក និងបង្អែមគ្រប់មុខ។',
  explore_syrups: 'ស្វែងរកសុីរ៉ូ',
  powder_selection_title: 'បណ្តុំម្សៅគុណភាពខ្ពស់',
  powder_selection_desc: 'បង្កើតនូវភេសជ្ជៈដ៏ឈ្ងុយឆ្ងាញ់ និងមានរសជាតិរលូនជាមួយម្សៅតែបៃតង និងគ្រីមម៉ាធម្មជាតិរបស់យើង។ ស័ក្តិសមទាំងសម្រាប់ហាងកាហ្វេអាជីព និងការឆុងទទួលទាននៅផ្ទះ។',
  explore_powders: 'ស្វែងរកម្សៅ',
  related_products_title: 'ផលិតផលដែលពាក់ព័ន្ធ',
  no_reviews: 'មិនទាន់មានការពិនិត្យនៅឡើយទេ។ ជាអ្នកដំបូងដែលផ្តល់មតិវាយតម្លៃ!',
  feature_organic_title: '100% សរីរាង្គ',
  feature_organic_desc: 'យើងប្រមូលគ្រាប់ពីចំការដែលមានវិញ្ញាបនបត្រសរីរាង្គ។',
  feature_low_acid_title: 'ជម្រើសទាបកម្ម៉ាស៊ីត',
  feature_low_acid_desc: 'ដុតសម្រាប់ក្រពះរសើប និងរសជាតិទន់ល្មើយ។',
  feature_sustainable_title: 'ការវេចខ្ចប់ប្រកបដោយចីរភាព',
  feature_sustainable_desc: 'សម្ភារៈមិត្តភាពបរិស្ថាន និងកាត់បន្ថយសំរាមអប្បបរមា។',
  feature_small_batch_title: 'ដុតតូចចំនួន',
  feature_small_batch_desc: 'ដុតដែលត្រូវគ្រប់គ្រងសម្រាប់ភាពស៊ីសង់នៃរសជាតិ។',
  feature_direct_trade_title: 'ពាណិជ្ជកម្មដោយផ្ទាល់',
  feature_direct_trade_desc: 'យើងបង់តម្លៃយុត្តិធម៌ដោយផ្ទាល់ទៅកសិករ។',
  feature_flavor_title: 'ពូជរសជាតិសម្បូរបែប',
  feature_flavor_desc: 'ពីកំណត់ព្រៃទៅកូឡាតេ រុករកជួរផលិតផលរបស់យើង។',
  location_subtitle_tag: 'VISIT US',
  location_title: 'ទីតាំងរបស់យើង',
  location_desc: 'សូមអញ្ជើញមកទទួលយកបទពិសោធន៍ក្លិនក្រអូប និងរសជាតិកាហ្វេគុណភាពខ្ពស់របស់យើងដោយផ្ទាល់។',
  location_store_name: 'KouPrey HQ',
  location_address_label: 'ហាងរបស់យើង',
  location_hours_label: 'ម៉ោងបើកដំណើរការ',
  company_hours: 'រៀងរាល់ថ្ងៃ៖ ម៉ោង ៧:០០ ព្រឹក - ៨:០០ យប់',
  company_map_embed: 'https://www.google.com/maps/embed?pb=!1m14!1m12!1m3!1d292.34896165878865!2d104.91197826608598!3d11.55083956811418!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!5e1!3m2!1sen!2skh!4v1767834278383!5m2!1sen!2skh',
  company_map_link: 'https://maps.app.goo.gl/v88Vyavc1UoykzgNA',
  location_btn_text: 'Get Directions'
};

const DEFAULT_SETTINGS_EN = {
  company_logo: '/kouprey/public/uploads/company-logo-1769389302.png',
  company_name: 'KouPrey Coffee',
  company_phone: '+855 93 839 883',
  company_email: 'info@kouprey.asia',
  company_address: '120408 Sangkat Boeung Kak 2, Khan Tuol Kouk, Phnom Penh, Cambodia.',
  site_description: 'Premium coffee beans and sustainable brewing solutions',
  footer_quick_links: 'Quick Links',
  footer_home: 'Home',
  footer_products: 'Products',
  footer_about_us: 'About Us',
  footer_reviews: 'Reviews',
  footer_admin: 'Admin',
  social_banner_text: 'Social Media',
  social_facebook: 'https://www.facebook.com/bossauveli98',
  social_instagram: 'https://instagram.com/bossauveli',
  social_telegram: 'https://t.me/Bos_Sauveli98',
  footer_text: '© 2026 KouPrey. All rights reserved.',
  footer_privacy_policy: 'Privacy Policy',
  footer_terms_of_service: 'Terms of Service',
  our_products: 'Our Products',
  our_products_description: 'Discover our complete collection of premium coffee and matcha products',
  featured_products: 'Featured Products',
  explore_more: 'Explore More',
  reviews_text: 'Reviews',
  modal_customer_reviews: 'Customer Reviews',
  modal_write_review: 'Write a Review',
  modal_weight: 'Weight',
  modal_roast_level: 'Roast Level',
  modal_detailed_description: 'Product Information',
  nav_home: 'Home',
  nav_product: 'Product',
  nav_features: 'Features',
  nav_reviews: 'Reviews',
  nav_about: 'About',
  about_title: 'About KouPrey Coffee',
  about_content: 'This is how KouPrey was born. Having experienced the cleanest, purest coffee on a mountainside in Peru, we struggled to find something like it after coming home — so we made it ourselves.',
  about_explore_button: 'Explore',
  about_purpose_title: 'Our Purpose',
  about_purpose_content: 'At KouPrey we do things differently — with purpose. Our goal is simple: make 100% organic, healthy and delicious coffee accessible to as many people as possible. We are committed to delivering coffee that is better for you, the community, and our planet.',
  about_mission_title: 'Our Story & Mission',
  about_mission_content: 'We began with a love for clean coffee and a desire to share it. Over the years we have partnered with growers, refined our roasting, and expanded our blends — all while keeping quality and sustainability at the center of everything we do.',
  features_title: 'Features',
  features_description: 'Discover what makes our coffee and matcha unique — from origin to roasting and sustainability.',
  reviews_title: 'Customer Reviews',
  reviews_description: 'Discover why coffee lovers across the country choose KouPrey for their daily caffeine ritual.',
  privacy_policy_title: 'Privacy Policy',
  privacy_policy_desc: 'We respect your privacy and are committed to protecting your personal information.',
  terms_of_service_title: 'Terms of Service',
  terms_of_service_desc: 'Please read these terms and conditions carefully before using our services.',
  hero_title: 'Exceptional Flavor, Truly Premium Quality',
  hero_subtitle: 'Specialty organic coffee and premium matcha to energize and refresh your entire day.',
  syrup_collection_title: 'Curated Syrup Collection',
  syrup_collection_desc: 'Elevate your beverages with our all-natural artisanal syrups. Crafted with care to bring vibrant flavors to coffees, cocktails, and desserts alike.',
  explore_syrups: 'Explore Syrups',
  powder_selection_title: 'Premium Powder Selection',
  powder_selection_desc: 'Create smooth, creamy drinks with our organic green tea powders and creamers. Perfect for specialty coffee shops and cozy home brewing.',
  explore_powders: 'Explore Powders',
  related_products_title: 'Related Products',
  no_reviews: 'No customer reviews yet. Be the first to share your thoughts!',
  feature_organic_title: '100% Organic',
  feature_organic_desc: 'Carefully sourced from certified organic farms.',
  feature_low_acid_title: 'Low Acid Options',
  feature_low_acid_desc: 'Specially roasted for sensitive stomachs and smooth finish.',
  feature_sustainable_title: 'Sustainable Packaging',
  feature_sustainable_desc: 'Eco-friendly materials that respect the planet.',
  feature_small_batch_title: 'Small Batch Roasting',
  feature_small_batch_desc: 'Artisan batches for optimal freshness and control.',
  feature_direct_trade_title: 'Direct Trade',
  feature_direct_trade_desc: 'Fair compensation directly empowering coffee farmers.',
  feature_flavor_title: 'Flavor Diversity',
  feature_flavor_desc: 'Rich profiles from dark roasts to ceremonial matcha.',
  location_subtitle_tag: 'VISIT US',
  location_title: 'Our Locations',
  location_desc: 'Come experience the aroma and taste of our premium coffee in person.',
  location_store_name: 'KouPrey HQ',
  location_address_label: 'Our Store',
  location_hours_label: 'Opening Hours',
  company_hours: 'Daily: 7:00 AM - 8:00 PM',
  company_map_embed: 'https://www.google.com/maps/embed?pb=!1m14!1m12!1m3!1d292.34896165878865!2d104.91197826608598!3d11.55083956811418!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!5e1!3m2!1sen!2skh!4v1767834278383!5m2!1sen!2skh',
  company_map_link: 'https://maps.app.goo.gl/v88Vyavc1UoykzgNA',
  location_btn_text: 'Get Directions'
};

function cleanSettings(rawSettings, lang) {
  const defaults = lang === 'en' ? DEFAULT_SETTINGS_EN : DEFAULT_SETTINGS_KM;
  const result = { ...defaults };
  if (!rawSettings || typeof rawSettings !== 'object') return result;

  for (const [key, val] of Object.entries(rawSettings)) {
    if (val === null || val === undefined) continue;
    const str = String(val).trim();
    if (str === '' || /^\?{2,}$/.test(str)) {
      continue;
    }
    // If language is English, do not allow Khmer characters in settings!
    if (lang === 'en' && /[\u1780-\u17FF]/.test(str)) {
      continue;
    }
    result[key] = val;
  }
  return result;
}

export function AppProvider({ children }) {
  const [language, setLanguage] = useState(() => {
    return localStorage.getItem('site_lang') || 'km';
  });

  const [settings, setSettings] = useState(() => {
    try {
      const cached = localStorage.getItem(`kouprey_settings_${language}`);
      if (cached) {
        return cleanSettings(JSON.parse(cached), language);
      }
    } catch {}
    return cleanSettings({}, language);
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

  // Keep html lang attribute in sync
  useEffect(() => {
    document.documentElement.lang = language;
  }, [language]);

  // Load bootstrap settings & categories
  useEffect(() => {
    let isMounted = true;
    fetchBootstrap(language).then((data) => {
      if (!isMounted) return;
      if (data && data.success) {
        const cleaned = cleanSettings(data.settings, language);
        setSettings(cleaned);
        try {
          localStorage.setItem(`kouprey_settings_${language}`, JSON.stringify(cleaned));
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
    try {
      const cached = localStorage.getItem(`kouprey_settings_${lang}`);
      if (cached) {
        setSettings(cleanSettings(JSON.parse(cached), lang));
      } else {
        setSettings(cleanSettings({}, lang));
      }
    } catch {
      setSettings(cleanSettings({}, lang));
    }
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

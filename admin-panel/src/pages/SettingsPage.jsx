import React, { useState, useEffect, useMemo } from 'react';
import { useSearchParams } from 'react-router-dom';
import {
  Globe,
  Image as ImageIcon,
  Layers,
  Info,
  ShieldCheck,
  Share2,
  Sparkles,
  Package,
  Star,
  Compass,
  FileText,
  Mail,
  ListOrdered,
  ArrowRight,
  ArrowLeft,
  Search,
  Save,
  Loader2,
  CheckCircle2,
  AlertCircle,
  Plus,
  Trash2
} from 'lucide-react';
import { adminApi } from '../api/adminClient';
import ImageUpload from '../components/ImageUpload';

// Categories config matching admin/settings.php
const CATEGORIES_CONFIG = {
  general: {
    id: 'general',
    title: 'General Settings',
    titleKm: 'ការកំណត់ទូទៅ (General)',
    description: 'Basic website information, site title and branding',
    descriptionKm: 'ព័ត៌មានមូលដ្ឋាន ឈ្មោះគេហទំព័រ ឡូហ្គោ និងម៉ាកយីហោ',
    icon: Globe,
    color: 'from-blue-600 to-indigo-600',
    iconBg: 'bg-blue-50 text-blue-700 border-blue-200',
  },
  hero: {
    id: 'hero',
    title: 'Hero Section',
    titleKm: 'ផ្នែកខាងលើ Hero & Banners',
    description: 'Main banner, hero background image and call-to-action content',
    descriptionKm: 'ផ្ទាំងផ្សាយធំ រូបភាពផ្ទៃខាងក្រោយ និងប៊ូតុងសកម្មភាពលើទំព័រដើម',
    icon: ImageIcon,
    color: 'from-emerald-600 to-teal-600',
    iconBg: 'bg-emerald-50 text-emerald-700 border-emerald-200',
  },
  collections: {
    id: 'collections',
    title: 'Product Collections',
    titleKm: 'កម្រងផលិតផល (Curated Collections)',
    description: 'Manage Syrup & Powder collection titles, descriptions and feature lists',
    descriptionKm: 'គ្រប់គ្រងចំណងជើង អត្ថបទ និងលក្ខណៈពិសេសរបស់ Syrup & Powder',
    icon: Layers,
    color: 'from-amber-600 to-orange-600',
    iconBg: 'bg-amber-50 text-amber-700 border-amber-200',
  },
  about: {
    id: 'about',
    title: 'About Page',
    titleKm: 'ទំព័រអំពីយើង (About)',
    description: 'About page story, vision, purpose, and mission sections',
    descriptionKm: 'ខ្លឹមសាររៀបរាប់ គោលបំណង ចក្ខុវិស័យ និងបេសកកម្ម',
    icon: Info,
    color: 'from-purple-600 to-pink-600',
    iconBg: 'bg-purple-50 text-purple-700 border-purple-200',
  },
  policies: {
    id: 'policies',
    title: 'Policies & Legal',
    titleKm: 'គោលការណ៍ & ច្បាប់ (Policies)',
    description: 'Privacy Policy and Terms of Service full content',
    descriptionKm: 'ខ្លឹមសារគោលការណ៍ឯកជនភាព និងលក្ខខណ្ឌប្រើប្រាស់',
    icon: ShieldCheck,
    color: 'from-rose-600 to-red-600',
    iconBg: 'bg-rose-50 text-rose-700 border-rose-200',
  },
  social: {
    id: 'social',
    title: 'Social Media',
    titleKm: 'បណ្តាញសង្គម (Social Media)',
    description: 'Social media links (Telegram, Facebook, TikTok) and banner text',
    descriptionKm: 'តំណភ្ជាប់បណ្តាញសង្គម និងអត្ថបទផ្សាយពាណិជ្ជកម្ម Social Banner',
    icon: Share2,
    color: 'from-sky-600 to-cyan-600',
    iconBg: 'bg-sky-50 text-sky-700 border-sky-200',
  },
  features: {
    id: 'features',
    title: 'Features & Content',
    titleKm: 'លក្ខណៈពិសេស & ខ្លឹមសារ (Features)',
    description: 'Website features titles, descriptions and promotional content',
    descriptionKm: 'ចំណងជើង និងអត្ថបទលក្ខណៈពិសេសនៅលើគេហទំព័រ',
    icon: Sparkles,
    color: 'from-yellow-600 to-amber-600',
    iconBg: 'bg-yellow-50 text-yellow-700 border-yellow-200',
  },
  product: {
    id: 'product',
    title: 'Product Information',
    titleKm: 'ព័ត៌មានផលិតផល & Modal',
    description: 'Product details, specifications, brewing guides, and modal labels',
    descriptionKm: 'ស្លាកសញ្ញា ព័ត៌មានផ្សំ និងអត្ថបទបង្ហាញក្នុង Pop-up ផលិតផល',
    icon: Package,
    color: 'from-teal-600 to-emerald-600',
    iconBg: 'bg-teal-50 text-teal-700 border-teal-200',
  },
  reviews: {
    id: 'reviews',
    title: 'Reviews Section',
    titleKm: 'ផ្នែកការវាយតម្លៃ (Reviews)',
    description: 'Customer reviews titles, empty states and submission messages',
    descriptionKm: 'ចំណងជើង និងសារឆ្លើយតបនៃការវាយតម្លៃអតិថិជន',
    icon: Star,
    color: 'from-amber-500 to-yellow-600',
    iconBg: 'bg-amber-50 text-amber-700 border-amber-200',
  },
  navigation: {
    id: 'navigation',
    title: 'Navigation',
    titleKm: 'ម៉ឺនុយរុករក (Navigation)',
    description: 'Navigation menu labels (Home, Products, Features, Reviews, About)',
    descriptionKm: 'ស្លាកឈ្មោះមឺនុយលើ Header គេហទំព័រ',
    icon: Compass,
    color: 'from-indigo-600 to-blue-600',
    iconBg: 'bg-indigo-50 text-indigo-700 border-indigo-200',
  },
  footer: {
    id: 'footer',
    title: 'Footer',
    titleKm: 'ផ្នែកខាងក្រោម (Footer)',
    description: 'Footer content, contact details, quick links and copyright',
    descriptionKm: 'ព័ត៌មានខាងក្រោមគេហទំព័រ តំណរហ័ស និងសិទ្ធិអ្នកនិពន្ធ',
    icon: FileText,
    color: 'from-slate-600 to-gray-800',
    iconBg: 'bg-slate-100 text-slate-700 border-slate-300',
  },
  newsletter: {
    id: 'newsletter',
    title: 'Newsletter',
    titleKm: 'ព្រឹត្តិបត្រព័ត៌មាន (Newsletter)',
    description: 'Newsletter subscription settings, input placeholders and button text',
    descriptionKm: 'អត្ថបទ និងប៊ូតុងចុះឈ្មោះទទួលព័ត៌មានប្រចាំខែ',
    icon: Mail,
    color: 'from-violet-600 to-purple-600',
    iconBg: 'bg-violet-50 text-violet-700 border-violet-200',
  },
  pagination: {
    id: 'pagination',
    title: 'Pagination',
    titleKm: 'ការបែងចែកទំព័រ (Pagination)',
    description: 'Content display limits, products per page and page controls',
    descriptionKm: 'ចំនួនកំណត់បង្ហាញទំនិញ និងទិន្នន័យក្នុងមួយទំព័រ',
    icon: ListOrdered,
    color: 'from-stone-600 to-neutral-700',
    iconBg: 'bg-stone-100 text-stone-700 border-stone-300',
  },
};

// Known key to category mapping fallback
const KEY_CATEGORY_MAP = {
  privacy_policy: 'policies',
  terms_of_service: 'policies',
  privacy_policy_title: 'policies',
  privacy_policy_desc: 'policies',
  terms_of_service_title: 'policies',
  terms_of_service_desc: 'policies',
  social_banner_text: 'social',
  social_facebook: 'social',
  social_instagram: 'social',
  social_tiktok: 'social',
  social_telegram: 'social',
  about_banner_title: 'about',
  about_banner_desc: 'about',
  about_story_title: 'about',
  about_story_desc: 'about',
  about_title: 'about',
  about_content: 'about',
  about_purpose_title: 'about',
  about_purpose_content: 'about',
  about_mission_title: 'about',
  about_mission_content: 'about',
  about_vision: 'about',
  hero_title: 'hero',
  hero_description: 'hero',
  hero_highlight: 'hero',
  hero_background_image: 'hero',
  banner_1_title: 'hero',
  banner_1_desc: 'hero',
  banner_1_image: 'hero',
  banner_2_title: 'hero',
  banner_2_desc: 'hero',
  banner_2_image: 'hero',
  syrup_collection_title: 'collections',
  syrup_collection_description: 'collections',
  syrup_collection_features: 'collections',
  powder_selection_title: 'collections',
  powder_selection_description: 'collections',
  powder_selection_features: 'collections',
  collection_syrup_title: 'collections',
  collection_powder_title: 'collections',
  syrup_title: 'collections',
  syrup_description: 'collections',
  powder_title: 'collections',
  powder_description: 'collections',
  nav_home: 'navigation',
  nav_product: 'navigation',
  nav_products: 'navigation',
  nav_features: 'navigation',
  nav_reviews: 'navigation',
  nav_about: 'navigation',
};

export default function SettingsPage() {
  const [searchParams, setSearchParams] = useSearchParams();
  const activeTab = searchParams.get('tab') || 'grid';

  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');
  const [toast, setToast] = useState(null);

  // Settings stored as: { [category]: { [key]: { en: '', km: '', type: '', description: '' } } }
  const [groupedSettings, setGroupedSettings] = useState({});

  const showToast = (message, type = 'success') => {
    setToast({ message, type });
    setTimeout(() => setToast(null), 3500);
  };

  const loadSettings = async () => {
    setLoading(true);
    try {
      const res = await adminApi.getSettings();
      if (res.success) {
        // Use server grouped if available or build from raw/map
        if (res.grouped && Object.keys(res.grouped).length > 0) {
          setGroupedSettings(res.grouped);
        } else if (res.raw && Array.isArray(res.raw)) {
          const built = {};
          res.raw.forEach((r) => {
            const key = r.setting_key;
            const lang = r.language || 'km';
            const cat = r.category || KEY_CATEGORY_MAP[key] || 'general';
            if (!built[cat]) built[cat] = {};
            if (!built[cat][key]) {
              built[cat][key] = {
                key,
                category: cat,
                type: r.setting_type || 'text',
                description: r.description || '',
                values: { en: '', km: '' },
              };
            }
            built[cat][key].values[lang] = r.setting_value || '';
          });
          setGroupedSettings(built);
        }
      }
    } catch (err) {
      showToast(err.message || 'Failed to load settings', 'error');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadSettings();
  }, []);

  const switchTab = (tab) => {
    setSearchParams({ tab });
  };

  const updateSettingVal = (category, key, lang, value) => {
    setGroupedSettings((prev) => {
      const catObj = prev[category] || {};
      const keyObj = catObj[key] || {
        key,
        category,
        type: 'text',
        description: '',
        values: { en: '', km: '' },
      };

      return {
        ...prev,
        [category]: {
          ...catObj,
          [key]: {
            ...keyObj,
            values: {
              ...keyObj.values,
              [lang]: value,
            },
          },
        },
      };
    });
  };

  const handleSaveCategory = async (category) => {
    setSaving(true);
    try {
      const catSettings = groupedSettings[category] || {};
      const payload = [];

      Object.entries(catSettings).forEach(([key, details]) => {
        ['en', 'km'].forEach((lang) => {
          payload.push({
            key,
            value: details.values?.[lang] ?? '',
            language: lang,
            category,
          });
        });
      });

      const res = await adminApi.saveSettingsBulk(payload);
      if (res.success) {
        showToast(`បានរក្សាទុកផ្នែក «${CATEGORIES_CONFIG[category]?.titleKm || category}» ជោគជ័យ!`);
      } else {
        showToast(res.error || 'បរាជ័យក្នុងការរក្សាទុក', 'error');
      }
    } catch (err) {
      showToast(err.message || 'Error saving settings', 'error');
    } finally {
      setSaving(false);
    }
  };

  // Helper for Collections feature lists (JSON arrays)
  const getFeaturesList = (category, key, lang) => {
    const raw = groupedSettings[category]?.[key]?.values?.[lang] || '[]';
    try {
      const parsed = JSON.parse(raw);
      return Array.isArray(parsed) ? parsed : [];
    } catch {
      return raw ? [raw] : [];
    }
  };

  const setFeaturesList = (category, key, lang, list) => {
    updateSettingVal(category, key, lang, JSON.stringify(list));
  };

  // Filtered categories for grid view
  const filteredCategories = useMemo(() => {
    const allCatKeys = Array.from(
      new Set([...Object.keys(CATEGORIES_CONFIG), ...Object.keys(groupedSettings)])
    );

    if (!searchQuery.trim()) return allCatKeys;

    const q = searchQuery.toLowerCase();
    return allCatKeys.filter((cat) => {
      const cfg = CATEGORIES_CONFIG[cat];
      if (
        cat.toLowerCase().includes(q) ||
        cfg?.title.toLowerCase().includes(q) ||
        cfg?.titleKm.toLowerCase().includes(q) ||
        cfg?.description.toLowerCase().includes(q)
      ) {
        return true;
      }
      // Check if any setting key in this category matches
      const catSettings = groupedSettings[cat] || {};
      return Object.keys(catSettings).some((k) => k.toLowerCase().includes(q));
    });
  }, [searchQuery, groupedSettings]);

  if (loading) {
    return (
      <div className="flex flex-col items-center justify-center min-h-[450px] text-gray-500 gap-3">
        <Loader2 className="animate-spin text-emerald-600" size={38} />
        <p className="text-sm font-medium">កំពុងទាញយកការកំណត់ទាំងអស់ (Loading Settings Grid)...</p>
      </div>
    );
  }

  const isGridView = activeTab === 'grid';
  const currentCatConfig = CATEGORIES_CONFIG[activeTab] || {
    id: activeTab,
    title: activeTab.toUpperCase(),
    titleKm: activeTab,
    description: 'Custom settings category',
    descriptionKm: 'ការកំណត់សម្រាប់ផ្នែក ' + activeTab,
    icon: Globe,
    color: 'from-gray-600 to-gray-800',
    iconBg: 'bg-gray-100 text-gray-700 border-gray-300',
  };

  return (
    <div className="space-y-6">
      {/* Toast Notification */}
      {toast && (
        <div
          className={`fixed top-5 right-5 z-50 px-4 py-3 rounded-xl shadow-lg flex items-center gap-2.5 text-sm font-medium animate-fade-in ${
            toast.type === 'error' ? 'bg-red-600 text-white' : 'bg-emerald-600 text-white'
          }`}
        >
          {toast.type === 'error' ? <AlertCircle size={18} /> : <CheckCircle2 size={18} />}
          <span>{toast.message}</span>
        </div>
      )}

      {/* ─────────────────────────────────────────────────── */}
      {/* 1. GRID WORKFLOW VIEW (admin/settings.php?tab=grid)  */}
      {/* ─────────────────────────────────────────────────── */}
      {isGridView ? (
        <div className="space-y-6">
          {/* Header */}
          <div className="bg-white rounded-2xl border border-gray-200/80 p-6 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
              <div className="flex items-center gap-2.5">
                <div className="p-2 rounded-xl bg-emerald-50 text-emerald-700 border border-emerald-200">
                  <Layers size={22} />
                </div>
                <div>
                  <h2 className="text-xl sm:text-2xl font-bold text-gray-900">
                    ផ្ទាំងការកំណត់គេហទំព័រ (Settings Grid)
                  </h2>
                  <p className="text-xs text-gray-500 mt-0.5">
                    ជ្រើសរើសផ្នែកណាមួយខាងក្រោមដើម្បីកែប្រែទិន្នន័យ (ដូចក្នុង PHP Admin Settings)
                  </p>
                </div>
              </div>
            </div>

            {/* Search Box */}
            <div className="w-full md:w-80 relative">
              <Search size={16} className="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400" />
              <input
                type="text"
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                placeholder="ស្វែងរកការកំណត់ (Search setting)..."
                className="w-full pl-9 pr-4 py-2.5 text-xs sm:text-sm rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 bg-gray-50/50"
              />
            </div>
          </div>

          {/* Cards Workflow Grid */}
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
            {filteredCategories.map((catKey) => {
              const cfg = CATEGORIES_CONFIG[catKey] || {
                id: catKey,
                title: catKey.toUpperCase(),
                titleKm: catKey,
                description: 'Configure section settings',
                descriptionKm: 'ការកំណត់ផ្នែក ' + catKey,
                icon: Globe,
                iconBg: 'bg-gray-50 text-gray-700 border-gray-200',
              };
              const Icon = cfg.icon;
              const count = Object.keys(groupedSettings[catKey] || {}).length;

              return (
                <div
                  key={catKey}
                  onClick={() => switchTab(catKey)}
                  className="bg-white rounded-2xl border border-gray-200/80 p-5 shadow-xs hover:shadow-lg hover:border-emerald-400 hover:-translate-y-1 transition duration-200 cursor-pointer flex flex-col justify-between group"
                >
                  <div>
                    <div className="flex items-center justify-between mb-3">
                      <div className={`w-11 h-11 rounded-xl flex items-center justify-center border shadow-xs transition group-hover:scale-110 ${cfg.iconBg}`}>
                        <Icon size={20} />
                      </div>
                      <span className="text-[11px] font-semibold text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full">
                        {count > 0 ? `${count} កំណត់` : 'ថ្មី'}
                      </span>
                    </div>

                    <h3 className="font-bold text-gray-900 text-base group-hover:text-emerald-700 transition">
                      {cfg.title}
                    </h3>
                    <p className="text-xs text-emerald-800 font-medium mt-0.5">
                      {cfg.titleKm}
                    </p>
                    <p className="text-xs text-gray-500 mt-2 line-clamp-2 leading-relaxed">
                      {cfg.descriptionKm || cfg.description}
                    </p>
                  </div>

                  <div className="mt-5 pt-3 border-t border-gray-100 flex items-center justify-between text-xs font-semibold text-emerald-700 group-hover:text-emerald-800 transition">
                    <span>ចូលកែប្រែ (Configure)</span>
                    <ArrowRight size={15} className="group-hover:translate-x-1 transition" />
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      ) : (
        /* ─────────────────────────────────────────────────── */
        /* 2. CATEGORY CONFIGURATION VIEW (Detail)             */
        /* ─────────────────────────────────────────────────── */
        <div className="space-y-6">
          {/* Header with Back Button */}
          <div className="bg-white rounded-2xl border border-gray-200/80 p-5 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div className="flex items-center gap-4">
              <button
                onClick={() => switchTab('grid')}
                className="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs sm:text-sm font-semibold transition cursor-pointer"
              >
                <ArrowLeft size={16} />
                <span>ត្រឡប់ទៅ Grid (Back)</span>
              </button>

              <div className="flex items-center gap-3">
                <div className={`w-10 h-10 rounded-xl flex items-center justify-center border shadow-xs ${currentCatConfig.iconBg}`}>
                  {React.createElement(currentCatConfig.icon, { size: 20 })}
                </div>
                <div>
                  <h2 className="text-lg sm:text-xl font-bold text-gray-900 flex items-center gap-2">
                    <span>{currentCatConfig.title}</span>
                    <span className="text-sm font-normal text-emerald-700">({currentCatConfig.titleKm})</span>
                  </h2>
                  <p className="text-xs text-gray-500">
                    {currentCatConfig.descriptionKm || currentCatConfig.description}
                  </p>
                </div>
              </div>
            </div>

            <div className="flex items-center gap-3">
              {/* Category selector dropdown */}
              <select
                value={activeTab}
                onChange={(e) => switchTab(e.target.value)}
                className="text-xs sm:text-sm border border-gray-200 rounded-xl px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500"
              >
                {Object.keys(CATEGORIES_CONFIG).map((ck) => (
                  <option key={ck} value={ck}>
                    {CATEGORIES_CONFIG[ck].title} ({CATEGORIES_CONFIG[ck].titleKm})
                  </option>
                ))}
              </select>

              <button
                onClick={() => handleSaveCategory(activeTab)}
                disabled={saving}
                className="inline-flex items-center gap-2 px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs sm:text-sm font-semibold rounded-xl shadow-xs transition cursor-pointer disabled:opacity-60"
              >
                {saving ? <Loader2 size={16} className="animate-spin" /> : <Save size={16} />}
                <span>រក្សាទុកទាំងអស់ (Save)</span>
              </button>
            </div>
          </div>

          {/* ─────────────────────────────────────────────────── */}
          {/* SPECIAL CATEGORY: COLLECTIONS (Syrup & Powder)      */}
          {/* ─────────────────────────────────────────────────── */}
          {activeTab === 'collections' && (
            <div className="space-y-6">
              <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {/* 1. Syrup Collection Card */}
                <div className="bg-white rounded-2xl border border-gray-200/80 p-6 shadow-xs space-y-4">
                  <div className="flex items-center justify-between pb-3 border-b border-gray-100">
                    <h3 className="font-bold text-emerald-800 text-base flex items-center gap-2">
                      <span className="w-3 h-3 rounded-full bg-emerald-500 inline-block" />
                      Syrup Collection (កម្រងស៊ីរ៉ូ)
                    </h3>
                  </div>

                  {/* Title */}
                  <div>
                    <label className="block text-xs font-semibold text-gray-700 uppercase mb-1">
                      ចំណងជើង (Collection Title)
                    </label>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                      <div className="relative">
                        <span className="absolute left-3 top-1/2 -translate-y-1/2 text-[10px] font-bold text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded">EN</span>
                        <input
                          type="text"
                          value={groupedSettings.collections?.syrup_collection_title?.values?.en || ''}
                          onChange={(e) => updateSettingVal('collections', 'syrup_collection_title', 'en', e.target.value)}
                          placeholder="Syrup Collection"
                          className="w-full pl-11 pr-3 py-2 text-xs sm:text-sm rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                        />
                      </div>
                      <div className="relative">
                        <span className="absolute left-3 top-1/2 -translate-y-1/2 text-[10px] font-bold text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded">KM</span>
                        <input
                          type="text"
                          value={groupedSettings.collections?.syrup_collection_title?.values?.km || ''}
                          onChange={(e) => updateSettingVal('collections', 'syrup_collection_title', 'km', e.target.value)}
                          placeholder="ស៊ីរ៉ូ"
                          className="w-full pl-11 pr-3 py-2 text-xs sm:text-sm rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                        />
                      </div>
                    </div>
                  </div>

                  {/* Description */}
                  <div>
                    <label className="block text-xs font-semibold text-gray-700 uppercase mb-1">
                      ការពិពណ៌នា (Description)
                    </label>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                      <div>
                        <span className="text-[11px] font-semibold text-gray-500 mb-1 block">English</span>
                        <textarea
                          rows={4}
                          value={groupedSettings.collections?.syrup_collection_description?.values?.en || ''}
                          onChange={(e) => updateSettingVal('collections', 'syrup_collection_description', 'en', e.target.value)}
                          placeholder="Description in English..."
                          className="w-full p-2.5 text-xs sm:text-sm rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                        />
                      </div>
                      <div>
                        <span className="text-[11px] font-semibold text-emerald-700 mb-1 block">Khmer (ភាសាខ្មែរ)</span>
                        <textarea
                          rows={4}
                          value={groupedSettings.collections?.syrup_collection_description?.values?.km || ''}
                          onChange={(e) => updateSettingVal('collections', 'syrup_collection_description', 'km', e.target.value)}
                          placeholder="ការពិពណ៌នាជាភាសាខ្មែរ..."
                          className="w-full p-2.5 text-xs sm:text-sm rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                        />
                      </div>
                    </div>
                  </div>

                  {/* Features List */}
                  <div>
                    <label className="block text-xs font-semibold text-gray-700 uppercase mb-1.5">
                      លក្ខណៈពិសេស (Feature Highlights)
                    </label>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                      {/* EN Features */}
                      <div className="space-y-2 bg-gray-50 p-3 rounded-xl border border-gray-100">
                        <div className="flex items-center justify-between mb-1">
                          <span className="text-xs font-bold text-gray-600">Features (EN)</span>
                          <button
                            type="button"
                            onClick={() => {
                              const list = getFeaturesList('collections', 'syrup_collection_features', 'en');
                              setFeaturesList('collections', 'syrup_collection_features', 'en', [...list, '']);
                            }}
                            className="p-1 rounded-lg bg-white border border-gray-200 hover:bg-emerald-50 text-emerald-700 text-xs font-semibold flex items-center gap-1"
                          >
                            <Plus size={13} /> Add
                          </button>
                        </div>
                        {getFeaturesList('collections', 'syrup_collection_features', 'en').map((feat, idx) => (
                          <div key={idx} className="flex items-center gap-1.5">
                            <input
                              type="text"
                              value={feat}
                              onChange={(e) => {
                                const list = [...getFeaturesList('collections', 'syrup_collection_features', 'en')];
                                list[idx] = e.target.value;
                                setFeaturesList('collections', 'syrup_collection_features', 'en', list);
                              }}
                              placeholder="Feature point..."
                              className="flex-1 px-2.5 py-1.5 text-xs rounded-lg border border-gray-200 bg-white"
                            />
                            <button
                              type="button"
                              onClick={() => {
                                const list = getFeaturesList('collections', 'syrup_collection_features', 'en').filter((_, i) => i !== idx);
                                setFeaturesList('collections', 'syrup_collection_features', 'en', list);
                              }}
                              className="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg"
                            >
                              <Trash2 size={13} />
                            </button>
                          </div>
                        ))}
                      </div>

                      {/* KM Features */}
                      <div className="space-y-2 bg-gray-50 p-3 rounded-xl border border-gray-100">
                        <div className="flex items-center justify-between mb-1">
                          <span className="text-xs font-bold text-emerald-800">Features (KM)</span>
                          <button
                            type="button"
                            onClick={() => {
                              const list = getFeaturesList('collections', 'syrup_collection_features', 'km');
                              setFeaturesList('collections', 'syrup_collection_features', 'km', [...list, '']);
                            }}
                            className="p-1 rounded-lg bg-white border border-gray-200 hover:bg-emerald-50 text-emerald-700 text-xs font-semibold flex items-center gap-1"
                          >
                            <Plus size={13} /> Add
                          </button>
                        </div>
                        {getFeaturesList('collections', 'syrup_collection_features', 'km').map((feat, idx) => (
                          <div key={idx} className="flex items-center gap-1.5">
                            <input
                              type="text"
                              value={feat}
                              onChange={(e) => {
                                const list = [...getFeaturesList('collections', 'syrup_collection_features', 'km')];
                                list[idx] = e.target.value;
                                setFeaturesList('collections', 'syrup_collection_features', 'km', list);
                              }}
                              placeholder="លក្ខណៈពិសេស..."
                              className="flex-1 px-2.5 py-1.5 text-xs rounded-lg border border-gray-200 bg-white"
                            />
                            <button
                              type="button"
                              onClick={() => {
                                const list = getFeaturesList('collections', 'syrup_collection_features', 'km').filter((_, i) => i !== idx);
                                setFeaturesList('collections', 'syrup_collection_features', 'km', list);
                              }}
                              className="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg"
                            >
                              <Trash2 size={13} />
                            </button>
                          </div>
                        ))}
                      </div>
                    </div>
                  </div>
                </div>

                {/* 2. Powder Selection Card */}
                <div className="bg-white rounded-2xl border border-gray-200/80 p-6 shadow-xs space-y-4">
                  <div className="flex items-center justify-between pb-3 border-b border-gray-100">
                    <h3 className="font-bold text-amber-800 text-base flex items-center gap-2">
                      <span className="w-3 h-3 rounded-full bg-amber-500 inline-block" />
                      Powder Selection (កម្រងម្សៅ)
                    </h3>
                  </div>

                  {/* Title */}
                  <div>
                    <label className="block text-xs font-semibold text-gray-700 uppercase mb-1">
                      ចំណងជើង (Selection Title)
                    </label>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                      <div className="relative">
                        <span className="absolute left-3 top-1/2 -translate-y-1/2 text-[10px] font-bold text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded">EN</span>
                        <input
                          type="text"
                          value={groupedSettings.collections?.powder_selection_title?.values?.en || ''}
                          onChange={(e) => updateSettingVal('collections', 'powder_selection_title', 'en', e.target.value)}
                          placeholder="Powder Selection"
                          className="w-full pl-11 pr-3 py-2 text-xs sm:text-sm rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                        />
                      </div>
                      <div className="relative">
                        <span className="absolute left-3 top-1/2 -translate-y-1/2 text-[10px] font-bold text-amber-700 bg-amber-50 px-1.5 py-0.5 rounded">KM</span>
                        <input
                          type="text"
                          value={groupedSettings.collections?.powder_selection_title?.values?.km || ''}
                          onChange={(e) => updateSettingVal('collections', 'powder_selection_title', 'km', e.target.value)}
                          placeholder="ម្សៅ"
                          className="w-full pl-11 pr-3 py-2 text-xs sm:text-sm rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                        />
                      </div>
                    </div>
                  </div>

                  {/* Description */}
                  <div>
                    <label className="block text-xs font-semibold text-gray-700 uppercase mb-1">
                      ការពិពណ៌នា (Description)
                    </label>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                      <div>
                        <span className="text-[11px] font-semibold text-gray-500 mb-1 block">English</span>
                        <textarea
                          rows={4}
                          value={groupedSettings.collections?.powder_selection_description?.values?.en || ''}
                          onChange={(e) => updateSettingVal('collections', 'powder_selection_description', 'en', e.target.value)}
                          placeholder="Description in English..."
                          className="w-full p-2.5 text-xs sm:text-sm rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                        />
                      </div>
                      <div>
                        <span className="text-[11px] font-semibold text-amber-700 mb-1 block">Khmer (ភាសាខ្មែរ)</span>
                        <textarea
                          rows={4}
                          value={groupedSettings.collections?.powder_selection_description?.values?.km || ''}
                          onChange={(e) => updateSettingVal('collections', 'powder_selection_description', 'km', e.target.value)}
                          placeholder="ការពិពណ៌នាជាភាសាខ្មែរ..."
                          className="w-full p-2.5 text-xs sm:text-sm rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                        />
                      </div>
                    </div>
                  </div>

                  {/* Features List */}
                  <div>
                    <label className="block text-xs font-semibold text-gray-700 uppercase mb-1.5">
                      លក្ខណៈពិសេស (Feature Highlights)
                    </label>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                      {/* EN Features */}
                      <div className="space-y-2 bg-gray-50 p-3 rounded-xl border border-gray-100">
                        <div className="flex items-center justify-between mb-1">
                          <span className="text-xs font-bold text-gray-600">Features (EN)</span>
                          <button
                            type="button"
                            onClick={() => {
                              const list = getFeaturesList('collections', 'powder_selection_features', 'en');
                              setFeaturesList('collections', 'powder_selection_features', 'en', [...list, '']);
                            }}
                            className="p-1 rounded-lg bg-white border border-gray-200 hover:bg-amber-50 text-amber-700 text-xs font-semibold flex items-center gap-1"
                          >
                            <Plus size={13} /> Add
                          </button>
                        </div>
                        {getFeaturesList('collections', 'powder_selection_features', 'en').map((feat, idx) => (
                          <div key={idx} className="flex items-center gap-1.5">
                            <input
                              type="text"
                              value={feat}
                              onChange={(e) => {
                                const list = [...getFeaturesList('collections', 'powder_selection_features', 'en')];
                                list[idx] = e.target.value;
                                setFeaturesList('collections', 'powder_selection_features', 'en', list);
                              }}
                              placeholder="Feature point..."
                              className="flex-1 px-2.5 py-1.5 text-xs rounded-lg border border-gray-200 bg-white"
                            />
                            <button
                              type="button"
                              onClick={() => {
                                const list = getFeaturesList('collections', 'powder_selection_features', 'en').filter((_, i) => i !== idx);
                                setFeaturesList('collections', 'powder_selection_features', 'en', list);
                              }}
                              className="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg"
                            >
                              <Trash2 size={13} />
                            </button>
                          </div>
                        ))}
                      </div>

                      {/* KM Features */}
                      <div className="space-y-2 bg-gray-50 p-3 rounded-xl border border-gray-100">
                        <div className="flex items-center justify-between mb-1">
                          <span className="text-xs font-bold text-amber-800">Features (KM)</span>
                          <button
                            type="button"
                            onClick={() => {
                              const list = getFeaturesList('collections', 'powder_selection_features', 'km');
                              setFeaturesList('collections', 'powder_selection_features', 'km', [...list, '']);
                            }}
                            className="p-1 rounded-lg bg-white border border-gray-200 hover:bg-amber-50 text-amber-700 text-xs font-semibold flex items-center gap-1"
                          >
                            <Plus size={13} /> Add
                          </button>
                        </div>
                        {getFeaturesList('collections', 'powder_selection_features', 'km').map((feat, idx) => (
                          <div key={idx} className="flex items-center gap-1.5">
                            <input
                              type="text"
                              value={feat}
                              onChange={(e) => {
                                const list = [...getFeaturesList('collections', 'powder_selection_features', 'km')];
                                list[idx] = e.target.value;
                                setFeaturesList('collections', 'powder_selection_features', 'km', list);
                              }}
                              placeholder="លក្ខណៈពិសេស..."
                              className="flex-1 px-2.5 py-1.5 text-xs rounded-lg border border-gray-200 bg-white"
                            />
                            <button
                              type="button"
                              onClick={() => {
                                const list = getFeaturesList('collections', 'powder_selection_features', 'km').filter((_, i) => i !== idx);
                                setFeaturesList('collections', 'powder_selection_features', 'km', list);
                              }}
                              className="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg"
                            >
                              <Trash2 size={13} />
                            </button>
                          </div>
                        ))}
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          )}

          {/* ─────────────────────────────────────────────────── */}
          {/* STANDARD CATEGORY FIELDS (Side-by-side Dual Lang)   */}
          {/* ─────────────────────────────────────────────────── */}
          <div className="bg-white rounded-2xl border border-gray-200/80 p-6 shadow-xs space-y-6">
            <div className="border-b border-gray-100 pb-4">
              <h3 className="text-base font-bold text-gray-900">
                តារាងការកំណត់លម្អិត ({currentCatConfig.title})
              </h3>
              <p className="text-xs text-gray-500 mt-0.5">
                បំពេញទិន្នន័យជាភាសាអង់គ្លេស (EN) និង ភាសាខ្មែរ (KM) ឱ្យស៊ីគ្នា
              </p>
            </div>

            {Object.keys(groupedSettings[activeTab] || {}).length === 0 ? (
              <div className="text-center py-12 text-gray-400">
                <Info size={36} className="mx-auto mb-2 opacity-40" />
                <p className="text-sm font-medium">មិនមានទិន្នន័យការកំណត់ក្នុងផ្នែកនេះនៅឡើយទេ</p>
              </div>
            ) : (
              <div className="space-y-6 divide-y divide-gray-100">
                {Object.entries(groupedSettings[activeTab] || {}).map(([key, item]) => {
                  const isImage =
                    key.includes('image') ||
                    key.includes('logo') ||
                    key.includes('banner_') ||
                    item.type === 'image';
                  const isLongText =
                    key.includes('content') ||
                    key.includes('policy') ||
                    key.includes('service') ||
                    key.includes('description') ||
                    item.type === 'textarea' ||
                    item.type === 'editor';

                  return (
                    <div key={key} className="pt-5 first:pt-0 space-y-2">
                      <div className="flex flex-wrap items-center justify-between gap-2">
                        <div>
                          <span className="text-xs font-bold text-gray-800 font-mono tracking-wide">
                            {key}
                          </span>
                          {item.description && (
                            <span className="text-xs text-gray-400 ml-2">
                              — {item.description}
                            </span>
                          )}
                        </div>
                        <span className="text-[10px] font-semibold text-gray-400 uppercase bg-gray-100 px-2 py-0.5 rounded">
                          {item.type || 'text'}
                        </span>
                      </div>

                      {/* Image Field Type */}
                      {isImage ? (
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-1">
                          <ImageUpload
                            value={item.values?.en || ''}
                            onChange={(path) => updateSettingVal(activeTab, key, 'en', path)}
                            type={key.includes('logo') ? 'logo' : 'banner'}
                            label="រូបភាព (EN)"
                          />
                          <ImageUpload
                            value={item.values?.km || ''}
                            onChange={(path) => updateSettingVal(activeTab, key, 'km', path)}
                            type={key.includes('logo') ? 'logo' : 'banner'}
                            label="រូបភាព (KM)"
                          />
                        </div>
                      ) : isLongText ? (
                        /* Textarea / Long text */
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 pt-1">
                          <div>
                            <div className="flex items-center justify-between text-xs font-semibold text-gray-500 mb-1">
                              <span>English (EN)</span>
                              <span className="text-[10px] text-gray-400">{(item.values?.en || '').length} chars</span>
                            </div>
                            <textarea
                              rows={5}
                              value={item.values?.en || ''}
                              onChange={(e) => updateSettingVal(activeTab, key, 'en', e.target.value)}
                              placeholder={`Enter ${key} in English...`}
                              className="w-full p-3 text-xs sm:text-sm rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 font-sans"
                            />
                          </div>
                          <div>
                            <div className="flex items-center justify-between text-xs font-semibold text-emerald-700 mb-1">
                              <span>ភាសាខ្មែរ (KM)</span>
                              <span className="text-[10px] text-gray-400">{(item.values?.km || '').length} chars</span>
                            </div>
                            <textarea
                              rows={5}
                              value={item.values?.km || ''}
                              onChange={(e) => updateSettingVal(activeTab, key, 'km', e.target.value)}
                              placeholder={`បញ្ចូល ${key} ជាភាសាខ្មែរ...`}
                              className="w-full p-3 text-xs sm:text-sm rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 font-sans"
                            />
                          </div>
                        </div>
                      ) : (
                        /* Standard Single-line Input */
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-3 pt-1">
                          <div className="relative">
                            <span className="absolute left-3 top-1/2 -translate-y-1/2 text-[10px] font-bold text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded">
                              EN
                            </span>
                            <input
                              type="text"
                              value={item.values?.en || ''}
                              onChange={(e) => updateSettingVal(activeTab, key, 'en', e.target.value)}
                              placeholder="English value..."
                              className="w-full pl-11 pr-3 py-2 text-xs sm:text-sm rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                            />
                          </div>
                          <div className="relative">
                            <span className="absolute left-3 top-1/2 -translate-y-1/2 text-[10px] font-bold text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded">
                              KM
                            </span>
                            <input
                              type="text"
                              value={item.values?.km || ''}
                              onChange={(e) => updateSettingVal(activeTab, key, 'km', e.target.value)}
                              placeholder="តម្លៃភាសាខ្មែរ..."
                              className="w-full pl-11 pr-3 py-2 text-xs sm:text-sm rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                            />
                          </div>
                        </div>
                      )}
                    </div>
                  );
                })}
              </div>
            )}

            {/* Bottom Save Bar */}
            <div className="pt-6 border-t border-gray-100 flex items-center justify-between">
              <button
                type="button"
                onClick={() => switchTab('grid')}
                className="px-4 py-2 text-xs sm:text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-xl transition cursor-pointer"
              >
                ត្រឡប់ទៅ Grid
              </button>

              <button
                type="button"
                onClick={() => handleSaveCategory(activeTab)}
                disabled={saving}
                className="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs sm:text-sm font-semibold rounded-xl shadow-xs transition cursor-pointer flex items-center gap-2 disabled:opacity-60"
              >
                {saving ? <Loader2 size={16} className="animate-spin" /> : <Save size={16} />}
                <span>រក្សាទុកការកែប្រែ ({currentCatConfig.title})</span>
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

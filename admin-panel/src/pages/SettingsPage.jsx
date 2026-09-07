import React, { useState, useEffect, useMemo, useRef } from 'react';
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
  FolderOpen,
  Search,
  ArrowRight,
  ArrowLeft,
  Save,
  Loader2,
  CheckCircle2,
  AlertCircle,
  Plus,
  Trash2,
  Eye,
  Droplet,
  Snowflake,
  Target,
  Flag,
  Send,
  ExternalLink,
  Smile,
  Copy,
  Check,
  RefreshCw,
  UploadCloud,
  Wand2,
  CheckSquare,
  Square,
  MapPin,
  Clock,
  Store,
  Navigation
} from 'lucide-react';

const FacebookIcon = ({ size = 16, className = "" }) => (
  <svg width={size} height={size} viewBox="0 0 24 24" fill="currentColor" className={className}>
    <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
  </svg>
);

const InstagramIcon = ({ size = 16, className = "" }) => (
  <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className={className}>
    <rect width="20" height="20" x="2" y="2" rx="5" ry="5"/>
    <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/>
    <line x1="17.5" x2="17.51" y1="6.5" y2="6.5"/>
  </svg>
);
import { adminApi } from '../api/adminClient';
import ImageUpload from '../components/ImageUpload';
import PolicyRichEditor from '../components/PolicyRichEditor';
import { formatImageUrl } from '../utils/imageUrl';
import { compressImageClient } from '../utils/imageCompressor';

// All 15 Categories matching admin/settings.php lines 615-631
const CATEGORIES_METADATA = [
  {
    id: 'general',
    title: 'General Settings',
    titleKm: 'ការកំណត់ទូទៅ (General)',
    description: 'Basic website information and branding',
    descriptionKm: 'ព័ត៌មានមូលដ្ឋាន ឈ្មោះគេហទំព័រ ឡូហ្គោ និងម៉ាកយីហោ',
    icon: Globe,
    badgeColor: 'bg-blue-100 text-blue-800 border-blue-200',
  },
  {
    id: 'hero',
    title: 'Hero Section',
    titleKm: 'ផ្នែកខាងលើ Hero & Banners',
    description: 'Main banner and call-to-action content',
    descriptionKm: 'ផ្ទាំងផ្សាយធំ រូបភាពផ្ទៃខាងក្រោយ និងប៊ូតុងសកម្មភាព',
    icon: ImageIcon,
    badgeColor: 'bg-emerald-100 text-emerald-800 border-emerald-200',
  },
  {
    id: 'about',
    title: 'About Page',
    titleKm: 'ទំព័រអំពីយើង (About)',
    description: 'About page content and sections',
    descriptionKm: 'ខ្លឹមសាររៀបរាប់ គោលបំណង ចក្ខុវិស័យ និងបេសកកម្ម',
    icon: Info,
    badgeColor: 'bg-purple-100 text-purple-800 border-purple-200',
  },
  {
    id: 'newsletter',
    title: 'Newsletter',
    titleKm: 'ព្រឹត្តិបត្រព័ត៌មាន (Newsletter)',
    description: 'Newsletter subscription settings',
    descriptionKm: 'អត្ថបទ និងប៊ូតុងចុះឈ្មោះទទួលព័ត៌មានប្រចាំខែ',
    icon: Mail,
    badgeColor: 'bg-violet-100 text-violet-800 border-violet-200',
  },
  {
    id: 'footer',
    title: 'Footer',
    titleKm: 'ផ្នែកបាតគេហទំព័រ (Footer)',
    description: 'Footer content and copyright',
    descriptionKm: 'ព័ត៌មានខាងក្រោមគេហទំព័រ តំណរហ័ស និងសិទ្ធិអ្នកនិពន្ធ',
    icon: FileText,
    badgeColor: 'bg-slate-200 text-slate-800 border-slate-300',
  },
  {
    id: 'social',
    title: 'Social Media',
    titleKm: 'បណ្តាញសង្គម (Social Media)',
    description: 'Social media links and profiles',
    descriptionKm: 'តំណភ្ជាប់ Facebook, Telegram, TikTok, Instagram',
    icon: Share2,
    badgeColor: 'bg-sky-100 text-sky-800 border-sky-200',
  },
  {
    id: 'features',
    title: 'Features & Content',
    titleKm: 'លក្ខណៈពិសេស & ខ្លឹមសារ (Features)',
    description: 'Website features and page content',
    descriptionKm: 'ចំណងជើង និងអត្ថបទលក្ខណៈពិសេសនៅលើគេហទំព័រ',
    icon: Sparkles,
    badgeColor: 'bg-yellow-100 text-yellow-800 border-yellow-200',
  },
  {
    id: 'product',
    title: 'Product Information',
    titleKm: 'ព័ត៌មានលម្អិតផលិតផល (Product)',
    description: 'Product details and specifications',
    descriptionKm: 'ស្លាកសញ្ញា ព័ត៌មានផ្សំ និងអត្ថបទ Pop-up ផលិតផល',
    icon: Package,
    badgeColor: 'bg-teal-100 text-teal-800 border-teal-200',
  },
  {
    id: 'collections',
    title: 'Product Collections',
    titleKm: 'កម្រងផលិតផល (Collections)',
    description: 'Manage Syrup & Powder collection texts',
    descriptionKm: 'គ្រប់គ្រងអត្ថបទ និងលក្ខណៈពិសេសរបស់ Syrup & Powder',
    icon: Layers,
    badgeColor: 'bg-amber-100 text-amber-800 border-amber-200',
  },
  {
    id: 'location',
    title: 'Store Location & Map',
    titleKm: 'ទីតាំងហាង & ផែនទី (Location & Map)',
    description: 'Store address, opening hours, Google Maps embed and directions',
    descriptionKm: 'អាសយដ្ឋានហាង ម៉ោងបើកដំណើរការ តំណភ្ជាប់ Google Maps និងផែនទីទីតាំង',
    icon: MapPin,
    badgeColor: 'bg-emerald-100 text-emerald-800 border-emerald-200',
  },
  {
    id: 'reviews',
    title: 'Reviews Section',
    titleKm: 'ផ្នែកការវាយតម្លៃ (Reviews)',
    description: 'Customer reviews page content',
    descriptionKm: 'ចំណងជើង និងសារឆ្លើយតបនៃការវាយតម្លៃអតិថិជន',
    icon: Star,
    badgeColor: 'bg-orange-100 text-orange-800 border-orange-200',
  },
  {
    id: 'policies',
    title: 'Policies & Legal',
    titleKm: 'គោលការណ៍ & ច្បាប់ (Policies)',
    description: 'Privacy Policy and Terms of Service content',
    descriptionKm: 'គោលការណ៍ឯកជនភាព និងលក្ខខណ្ឌប្រើប្រាស់',
    icon: ShieldCheck,
    badgeColor: 'bg-rose-100 text-rose-800 border-rose-200',
  },
  {
    id: 'pagination',
    title: 'Pagination',
    titleKm: 'ការបែងចែកទំព័រ (Pagination)',
    description: 'Content display settings',
    descriptionKm: 'ចំនួនកំណត់បង្ហាញទំនិញ និងទិន្នន័យក្នុងមួយទំព័រ',
    icon: ListOrdered,
    badgeColor: 'bg-stone-200 text-stone-800 border-stone-300',
  },
  {
    id: 'navigation',
    title: 'Navigation',
    titleKm: 'ម៉ឺនុយរុករក (Navigation)',
    description: 'Navigation menu labels',
    descriptionKm: 'ស្លាកឈ្មោះមឺនុយលើ Header គេហទំព័រ',
    icon: Compass,
    badgeColor: 'bg-indigo-100 text-indigo-800 border-indigo-200',
  },
  {
    id: 'file_manager',
    title: 'File Manager',
    titleKm: 'គ្រប់គ្រងរូបភាព (File Manager)',
    description: 'Manage uploaded product images',
    descriptionKm: 'បណ្ណាល័យរូបភាពផលិតផល ចម្លង URL បង្ហាប់ WebP និងលុបរូបភាព',
    icon: FolderOpen,
    badgeColor: 'bg-cyan-100 text-cyan-800 border-cyan-200',
  },
  {
    id: 'flaticon',
    title: 'Flaticon Browser',
    titleKm: 'ស្វែងរក Icon (Flaticon)',
    description: 'Clone view of Flaticon.com to copy icons',
    descriptionKm: 'វិធីសាស្រ្តស្វែងរក និងចម្លង Icon ពីគេហទំព័រ Flaticon',
    icon: Search,
    badgeColor: 'bg-pink-100 text-pink-800 border-pink-200',
  },
];

const DEFAULT_LOCATION_SETTINGS = {
  location_subtitle_tag: { en: 'VISIT US', km: 'VISIT US', type: 'text', description: 'Section top subtitle tag' },
  location_title: { en: 'Our Locations', km: 'ទីតាំងរបស់យើង', type: 'text', description: 'Main section heading' },
  location_desc: {
    en: 'Come experience the aroma and taste of our premium coffee in person.',
    km: 'សូមអញ្ជើញមកទទួលយកបទពិសោធន៍ក្លិនក្រអូប និងរសជាតិគ្រឿងបន្ថែមរស់ជាតិគុណភាពខ្ពស់របស់យើងដោយផ្ទាល់។',
    type: 'textarea',
    description: 'Section description'
  },
  location_store_name: { en: 'KouPrey HQ', km: 'KouPrey HQ', type: 'text', description: 'Store branch name' },
  location_address_label: { en: 'Our Store', km: 'ហាងរបស់យើង', type: 'text', description: 'Address badge label' },
  company_address: {
    en: '120408 Sangkat Boeung Kak 2, Khan Tuol Kouk, Phnom Penh, Cambodia.',
    km: '120408 សង្កាត់បឹងកក់ 2 ខណ្ឌទួលគោក រាជធានីភ្នំពេញ ប្រទេសកម្ពុជា។',
    type: 'textarea',
    description: 'Store address'
  },
  location_hours_label: { en: 'Opening Hours', km: 'ម៉ោងបើកដំណើរការ', type: 'text', description: 'Schedule badge label' },
  company_hours: {
    en: 'Daily: 7:00 AM - 8:00 PM',
    km: 'រៀងរាល់ថ្ងៃ៖ ម៉ោង ៧:០០ ព្រឹក - ៨:០០ យប់',
    type: 'textarea',
    description: 'Opening hours / schedule'
  },
  company_map_embed: {
    en: 'https://www.google.com/maps/embed?pb=!1m14!1m12!1m3!1d292.34896165878865!2d104.91197826608598!3d11.55083956811418!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!5e1!3m2!1sen!2skh!4v1767834278383!5m2!1sen!2skh',
    km: 'https://www.google.com/maps/embed?pb=!1m14!1m12!1m3!1d292.34896165878865!2d104.91197826608598!3d11.55083956811418!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!5e1!3m2!1sen!2skh!4v1767834278383!5m2!1sen!2skh',
    type: 'url',
    description: 'Google Maps embed URL (iframe src)'
  },
  company_map_link: {
    en: 'https://maps.app.goo.gl/v88Vyavc1UoykzgNA',
    km: 'https://maps.app.goo.gl/v88Vyavc1UoykzgNA',
    type: 'url',
    description: 'Get Directions link'
  },
  location_btn_text: { en: 'Get Directions', km: 'Get Directions', type: 'text', description: 'Directions button text' }
};

const EMOJI_LIST = ['📌','🔴','🟢','🔵','⭐','✅','💡','🔥','🎯','📝','💬','📧','📞','📍','🌐','💻','📱','🛒','📦','💰','🎉','❤️','👍','➡️','⬅️','•'];

// Clean up corrupted HTML tags or broken CSS snippets from legacy RTE editors
export const cleanSocialBannerText = (str) => {
  if (!str || typeof str !== 'string') return '';
  let clean = str
    .replace(/&quot;/gi, '"')
    .replace(/&amp;/gi, '&')
    .replace(/&lt;/gi, '<')
    .replace(/&gt;/gi, '>')
    .replace(/&nbsp;/gi, ' ');
  clean = clean.replace(/<[^>]*>/g, '');
  clean = clean.replace(/background-color\s*:[^;]+;?/gi, '');
  clean = clean.replace(/mask-image\s*:[^;]+;?/gi, '');
  clean = clean.replace(/mask-size\s*:[^;]+;?/gi, '');
  clean = clean.replace(/mask-repeat\s*:[^;]+;?/gi, '');
  clean = clean.replace(/data-src\s*=\s*["'][^"']*["']/gi, '');
  clean = clean.replace(/style\s*=\s*["'][^"']*["']/gi, '');
  clean = clean.replace(/src\s*=\s*["'][^"']*["']/gi, '');
  clean = clean.replace(/class\s*=\s*["'][^"']*["']/gi, '');
  clean = clean.replace(/['"]?>/g, '');
  clean = clean.replace(/\s+/g, ' ').trim();
  return clean;
};

export default function SettingsPage() {
  const [searchParams, setSearchParams] = useSearchParams();
  const activeTab = searchParams.get('tab') || 'grid';

  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');
  const [toast, setToast] = useState(null);

  // Grouped settings: { [category]: { [key]: { key, category, type, description, values: { en: '', km: '' } } } }
  const [groupedSettings, setGroupedSettings] = useState({});

  // Products and Categories for Collections tab
  const [allProducts, setAllProducts] = useState([]);
  const [allCategories, setAllCategories] = useState([]);

  // File manager state
  const [fileManagerImages, setFileManagerImages] = useState([]);
  const [fileManagerFolder, setFileManagerFolder] = useState('products');
  const [loadingImages, setLoadingImages] = useState(false);
  const [selectedFiles, setSelectedFiles] = useState([]);
  const [convertingWebp, setConvertingWebp] = useState(false);
  const [copiedUrl, setCopiedUrl] = useState(null);
  const fileUploadInputRef = useRef(null);

  // Policy preview state
  const [activePolicyPreview, setActivePolicyPreview] = useState('privacy_policy_km');

  // Location preview state
  const [locationPreviewLang, setLocationPreviewLang] = useState('km');

  // Flaticon browser state
  const [flaticonUrl, setFlaticonUrl] = useState('https://www.flaticon.com/');

  // Remove.bg integration state
  const [removeBgStatus, setRemoveBgStatus] = useState(null);
  const [checkingRemoveBg, setCheckingRemoveBg] = useState(false);

  const handleCheckRemoveBg = async () => {
    setCheckingRemoveBg(true);
    try {
      const res = await adminApi.checkRemoveBgCredits();
      if (res.success && res.data) {
        setRemoveBgStatus(res.data);
        showToast('ពិនិត្យ Remove.bg API ជោគជ័យ!');
      } else {
        showToast(res.error || 'Failed to check Remove.bg credits', 'error');
      }
    } catch (err) {
      showToast(err.message || 'Error connecting to Remove.bg', 'error');
    } finally {
      setCheckingRemoveBg(false);
    }
  };

  const showToast = (message, type = 'success') => {
    setToast({ message, type });
    setTimeout(() => setToast(null), 3500);
  };

  const loadSettings = async () => {
    setLoading(true);
    try {
      const res = await adminApi.getSettings();
      if (res.success) {
        let built = {};
        if (res.grouped && Object.keys(res.grouped).length > 0) {
          built = { ...res.grouped };
        } else if (res.raw && Array.isArray(res.raw)) {
          res.raw.forEach((r) => {
            const key = r.setting_key;
            const lang = r.language || 'km';
            const cat = r.category || 'general';
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
        }

        // Initialize location category with default fallbacks and existing address/hours
        if (!built.location) built.location = {};
        Object.entries(DEFAULT_LOCATION_SETTINGS).forEach(([k, def]) => {
          if (!built.location[k]) {
            const fromGeneralEn = built.general?.[k]?.values?.en;
            const fromGeneralKm = built.general?.[k]?.values?.km;
            built.location[k] = {
              key: k,
              category: 'location',
              type: def.type,
              description: def.description,
              values: {
                en: fromGeneralEn || def.en,
                km: fromGeneralKm || def.km,
              },
            };
          }
        });

        // Auto-clean any legacy corrupted HTML in social_banner_text
        if (built.social?.social_banner_text?.values) {
          const rawEn = built.social.social_banner_text.values.en || '';
          const rawKm = built.social.social_banner_text.values.km || '';
          if (rawEn.includes('<') || rawEn.includes('mask-image') || rawEn.includes('background-color') || rawEn.includes('&quot;')) {
            built.social.social_banner_text.values.en = cleanSocialBannerText(rawEn) || 'Social Media';
          }
          if (rawKm.includes('<') || rawKm.includes('mask-image') || rawKm.includes('background-color') || rawKm.includes('&quot;')) {
            built.social.social_banner_text.values.km = cleanSocialBannerText(rawKm) || 'ប្រព័ន្ធបណ្តាញសង្គម';
          }
        }

        setGroupedSettings(built);
      }
    } catch (err) {
      showToast(err.message || 'Failed to load settings', 'error');
    } finally {
      setLoading(false);
    }
  };

  // Load products & categories for collections
  const loadCollectionsData = async () => {
    try {
      const [prodRes, catRes] = await Promise.all([
        adminApi.getProducts({ lang: 'en' }),
        adminApi.getCategories('en')
      ]);
      if (prodRes.success) setAllProducts(prodRes.products || []);
      if (catRes.success) setAllCategories(catRes.categories || []);
    } catch (e) {
      console.warn('Could not fetch products for collections', e);
    }
  };

  // Load file manager images
  const loadFileManager = async (folder = fileManagerFolder) => {
    setLoadingImages(true);
    try {
      const res = await adminApi.getFileManagerImages(folder);
      if (res.success) {
        setFileManagerImages(res.images || []);
      }
    } catch (e) {
      console.warn('Could not load file manager images', e);
    } finally {
      setLoadingImages(false);
    }
  };

  useEffect(() => {
    loadSettings();
    loadCollectionsData();
  }, []);

  useEffect(() => {
    if (activeTab === 'file_manager') {
      loadFileManager(fileManagerFolder);
    }
  }, [activeTab, fileManagerFolder]);

  const switchTab = (tab) => {
    setSearchParams({ tab });
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  const getVal = (category, key, lang, fallback = '') => {
    let val = groupedSettings[category]?.[key]?.values?.[lang] ?? fallback;
    if (key === 'social_banner_text' && typeof val === 'string' && (val.includes('<') || val.includes('mask-image') || val.includes('background-color') || val.includes('&quot;'))) {
      val = cleanSocialBannerText(val);
    }
    return val;
  };

  const setVal = (category, key, lang, value) => {
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

  // Preview frame document generator matching admin/settings.php previewFrame exactly
  const previewSrcDoc = useMemo(() => {
    const isPrivacy = activePolicyPreview.startsWith('privacy_policy');
    const isKm = activePolicyPreview.endsWith('_km');
    const targetKey = isPrivacy ? 'privacy_policy' : 'terms_of_service';
    const lang = isKm ? 'km' : 'en';

    const content = getVal('policies', targetKey, lang) || '<p style="color:#999;text-align:center;margin-top:160px;">ចុចប៊ូតុង <strong>Preview</strong> ដើម្បីមើលទិដ្ឋភាពជាក់ស្តែង</p>';
    const title = isPrivacy
      ? (isKm ? getVal('policies', 'privacy_policy_title', 'km', 'គោលការណ៍ឯកជនភាព') : getVal('policies', 'privacy_policy_title', 'en', 'Privacy Policy'))
      : (isKm ? getVal('policies', 'terms_of_service_title', 'km', 'លក្ខខណ្ឌប្រើប្រាស់') : getVal('policies', 'terms_of_service_title', 'en', 'Terms of Service'));
    const desc = isPrivacy
      ? (isKm ? getVal('policies', 'privacy_policy_desc', 'km') : getVal('policies', 'privacy_policy_desc', 'en'))
      : (isKm ? getVal('policies', 'terms_of_service_desc', 'km') : getVal('policies', 'terms_of_service_desc', 'en'));

    const borderColor = isPrivacy ? '#3B82F6' : '#10B981';

    return `<!DOCTYPE html>
<html lang="${lang}">
<head>
  <meta charset="UTF-8">
  <link href="https://fonts.googleapis.com/css2?family=Hanuman:wght@400;700&family=Kantumruy+Pro:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    * { box-sizing: border-box; }
    body {
      font-family: 'Hanuman', serif;
      font-size: 15px;
      line-height: 1.9;
      color: #333;
      padding: 24px;
      background: #fff;
      margin: 0;
    }
    .preview-header {
      margin-bottom: 1.5rem;
      padding-bottom: 1rem;
      border-bottom: 1px solid #e5e7eb;
    }
    .preview-title {
      font-size: 1.6rem;
      font-weight: 700;
      color: #1E3A5F;
      margin: 0 0 0.4rem;
    }
    .preview-desc {
      font-size: 0.875rem;
      color: #6b7280;
      margin: 0;
      line-height: 1.6;
    }
    h1 { font-size: 1.7rem; color: #1E3A5F; margin-bottom: 1rem; }
    h2 { font-size: 1.35rem; color: #1E3A5F; margin: 1.4rem 0 0.6rem; display: flex; align-items: center; gap: 0.5rem; }
    h3 { font-size: 1.15rem; color: #374151; margin: 1.1rem 0 0.5rem; }
    p { margin-bottom: 0.8rem; color: #4B5563; }
    ul, ol { margin-bottom: 0.8rem; padding-left: 1.5rem; }
    li { padding: 0.2rem 0; color: #4B5563; }
    strong, b { font-weight: 700; }
    .content-section {
      border-left: 4px solid ${borderColor};
      padding-left: 1.25rem;
      margin: 1.25rem 0;
    }
    img { max-width: 100%; height: auto; border-radius: 8px; }
    table { border-collapse: collapse; width: 100%; margin: 1rem 0; }
    table td, table th { border: 1px solid #ddd; padding: 8px 12px; }
    hr { border: 0; border-top: 1px solid #e5e7eb; margin: 1.5rem 0; }
  </style>
</head>
<body>
  <div class="content-section">
    ${content}
  </div>
</body>
</html>`;
  }, [activePolicyPreview, groupedSettings]);

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
        const catMeta = CATEGORIES_METADATA.find((c) => c.id === category);
        showToast(`បានរក្សាទុកផ្នែក «${catMeta?.titleKm || category}» ជោគជ័យ!`);
      } else {
        showToast(res.error || 'បរាជ័យក្នុងការរក្សាទុក', 'error');
      }
    } catch (err) {
      showToast(err.message || 'Error saving settings', 'error');
    } finally {
      setSaving(false);
    }
  };

  // Helper for JSON array feature lists
  const getFeaturesList = (category, key, lang) => {
    const raw = getVal(category, key, lang, '[]');
    try {
      const parsed = JSON.parse(raw);
      return Array.isArray(parsed) ? parsed : [];
    } catch {
      return raw ? [raw] : [];
    }
  };

  const setFeaturesList = (category, key, lang, list) => {
    setVal(category, key, lang, JSON.stringify(list));
  };

  // Helper for JSON array of selected product IDs
  const getSelectedProductIds = (key) => {
    const raw = getVal('collections', key, 'en', '[]');
    try {
      const parsed = JSON.parse(raw);
      return Array.isArray(parsed) ? parsed.map((id) => Number(id)) : [];
    } catch {
      return [];
    }
  };

  const toggleProductSelection = (key, productId) => {
    const current = getSelectedProductIds(key);
    const numId = Number(productId);
    let next;
    if (current.includes(numId)) {
      next = current.filter((id) => id !== numId);
    } else {
      next = [...current, numId];
    }
    const jsonStr = JSON.stringify(next);
    setVal('collections', key, 'en', jsonStr);
    setVal('collections', key, 'km', jsonStr);
  };

  // Category IDs for syrup and powder
  const syrupBaseCatId = useMemo(() => {
    const found = allCategories.find((c) => c.name?.toLowerCase().includes('syrup'));
    return found ? (found.base_category_id || found.id) : null;
  }, [allCategories]);

  const powderBaseCatId = useMemo(() => {
    const found = allCategories.find((c) => c.name?.toLowerCase().includes('powder'));
    return found ? (found.base_category_id || found.id) : null;
  }, [allCategories]);

  const syrupProducts = useMemo(() => {
    return allProducts.filter((p) => {
      const cat = allCategories.find((c) => c.id === p.category_id);
      return cat && syrupBaseCatId && (cat.base_category_id === syrupBaseCatId || cat.id === syrupBaseCatId);
    });
  }, [allProducts, allCategories, syrupBaseCatId]);

  const powderProducts = useMemo(() => {
    return allProducts.filter((p) => {
      const cat = allCategories.find((c) => c.id === p.category_id);
      return cat && powderBaseCatId && (cat.base_category_id === powderBaseCatId || cat.id === powderBaseCatId);
    });
  }, [allProducts, allCategories, powderBaseCatId]);

  // File Manager Handlers
  const handleCopyUrl = (url) => {
    const fullUrl = formatImageUrl(url);
    navigator.clipboard.writeText(fullUrl).then(() => {
      setCopiedUrl(url);
      showToast('បានចម្លង URL ទៅកាន់ Clipboard រួចរាល់!');
      setTimeout(() => setCopiedUrl(null), 2500);
    });
  };

  const handleToggleFileSelection = (filename) => {
    setSelectedFiles((prev) =>
      prev.includes(filename) ? prev.filter((f) => f !== filename) : [...prev, filename]
    );
  };

  const handleDeleteSelectedFiles = async () => {
    if (selectedFiles.length === 0) return;
    if (!confirm(`តើអ្នកប្រាកដថាចង់លុបរូបភាព ${selectedFiles.length} ដែលបានជ្រើសរើសទេ?`)) return;

    try {
      const res = await adminApi.deleteFileManager(selectedFiles, fileManagerFolder);
      if (res.success) {
        showToast(`បានលុបរូបភាព ${res.deleted || selectedFiles.length} ដោយជោគជ័យ!`);
        setSelectedFiles([]);
        loadFileManager(fileManagerFolder);
      } else {
        showToast(res.error || 'បរាជ័យក្នុងការលុប', 'error');
      }
    } catch (e) {
      showToast(e.message || 'Error deleting files', 'error');
    }
  };

  const handleDeleteSingleFile = async (filename) => {
    if (!confirm(`តើអ្នកប្រាកដថាចង់លុបរូបភាព «${filename}» ទេ?`)) return;
    try {
      const res = await adminApi.deleteFileManager([filename], fileManagerFolder);
      if (res.success) {
        showToast(`បានលុបរូបភាព «${filename}» រួចរាល់!`);
        loadFileManager(fileManagerFolder);
      } else {
        showToast(res.error || 'Error deleting file', 'error');
      }
    } catch (e) {
      showToast(e.message || 'Error deleting file', 'error');
    }
  };

  const handleUploadNewImages = async (e) => {
    const rawFiles = e.target.files;
    if (!rawFiles || rawFiles.length === 0) return;
    setConvertingWebp(true);
    try {
      showToast('កំពុងបម្លែង និងបញ្ចូលរូបភាព WebP...', 'info');
      const convertedFiles = await Promise.all(
        Array.from(rawFiles).map((f) => compressImageClient(f))
      );
      const res = await adminApi.uploadFileManager(convertedFiles, fileManagerFolder);
      if (res.success) {
        showToast(`បានបញ្ចូលរូបភាព WebP ចំនួន ${res.uploaded} ជោគជ័យ!`);
        loadFileManager(fileManagerFolder);
      } else {
        showToast(res.error || 'បរាជ័យក្នុងការបញ្ចូលរូបភាព', 'error');
      }
    } catch (e) {
      showToast(e.message || 'Error uploading files', 'error');
    } finally {
      setConvertingWebp(false);
      if (fileUploadInputRef.current) fileUploadInputRef.current.value = '';
    }
  };

  const handleConvertAllWebp = async (targetFolder = '') => {
    setConvertingWebp(true);
    const folderName = targetFolder || fileManagerFolder;
    try {
      showToast(`កំពុងបម្លែងរូបភាពទៅជា WebP (${folderName === 'all' ? 'គ្រប់ Folder' : folderName})...`, 'info');
      const res = await adminApi.convertAllWebp(folderName);
      if (res.success) {
        showToast(`ជោគជ័យ! បានបម្លែងរូបភាព ${res.converted || 0} ទៅជា WebP (កែប្រែ Database ${res.db_updates || 0} កន្លែង)។`);
        loadFileManager(fileManagerFolder);
      } else {
        showToast(res.error || 'បរាជ័យក្នុងការបម្លែង', 'error');
      }
    } catch (e) {
      showToast(e.message || 'Error converting to WebP', 'error');
    } finally {
      setConvertingWebp(false);
    }
  };

  const currentCatMeta = CATEGORIES_METADATA.find((c) => c.id === activeTab) || {
    id: activeTab,
    title: activeTab.toUpperCase(),
    titleKm: activeTab,
    description: 'Custom settings category',
    descriptionKm: 'ការកំណត់ផ្នែក ' + activeTab,
    icon: Globe,
    badgeColor: 'bg-gray-100 text-gray-800 border-gray-200',
  };

  const formatLabel = (key) => {
    return key
      .split('_')
      .map((w) => w.charAt(0).toUpperCase() + w.slice(1))
      .join(' ');
  };

  if (loading) {
    return (
      <div className="flex flex-col items-center justify-center min-h-[450px] text-gray-500 gap-3">
        <Loader2 className="animate-spin text-emerald-600" size={38} />
        <p className="text-sm font-medium">កំពុងទាញយកការកំណត់ (Loading Settings)...</p>
      </div>
    );
  }

  const isGridView = activeTab === 'grid';

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

      {/* ───────────────────────────────────────────────────────── */}
      {/* 1. GRID WORKFLOW VIEW (admin/settings.php?tab=grid)      */}
      {/* ───────────────────────────────────────────────────────── */}
      {isGridView ? (
        <div className="space-y-6">
          {/* Header */}
          <div className="bg-white rounded-2xl border border-gray-200/80 p-6 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
              <div className="flex items-center gap-3">
                <div className="p-2.5 rounded-xl bg-emerald-50 text-emerald-700 border border-emerald-200">
                  <Layers size={24} />
                </div>
                <div>
                  <h2 className="text-xl sm:text-2xl font-bold text-gray-900">
                    ផ្ទាំងការកំណត់គេហទំព័រ (Settings Grid)
                  </h2>
                  <p className="text-xs text-gray-500 mt-0.5">
                    ចុចលើ Card ណាមួយខាងក្រោមដើម្បីចូលទៅកែប្រែទិន្នន័យ (ដូចគ្នានឹង admin/settings.php?tab=grid)
                  </p>
                </div>
              </div>
            </div>

            {/* Quick Search */}
            <div className="w-full md:w-80 relative">
              <Search size={16} className="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400" />
              <input
                type="text"
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                placeholder="ស្វែងរកការកំណត់ (Search)..."
                className="w-full pl-9 pr-4 py-2.5 text-xs sm:text-sm rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 bg-gray-50/50"
              />
            </div>
          </div>

          {/* Cards Grid: All 15 Categories */}
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
            {CATEGORIES_METADATA.map((cat) => {
              const Icon = cat.icon;
              const count = Object.keys(groupedSettings[cat.id] || {}).length;

              if (searchQuery.trim()) {
                const q = searchQuery.toLowerCase();
                const matches =
                  cat.id.includes(q) ||
                  cat.title.toLowerCase().includes(q) ||
                  cat.titleKm.toLowerCase().includes(q) ||
                  cat.description.toLowerCase().includes(q);
                const hasMatchingKey = Object.keys(groupedSettings[cat.id] || {}).some((k) =>
                  k.toLowerCase().includes(q)
                );
                if (!matches && !hasMatchingKey) return null;
              }

              return (
                <div
                  key={cat.id}
                  onClick={() => switchTab(cat.id)}
                  className="bg-white rounded-2xl border border-gray-200/80 p-5 shadow-xs hover:shadow-lg hover:border-emerald-400 hover:-translate-y-1 transition duration-200 cursor-pointer flex flex-col justify-between group"
                >
                  <div>
                    <div className="flex items-center justify-between mb-3">
                      <div className={`w-11 h-11 rounded-xl flex items-center justify-center border shadow-xs transition group-hover:scale-110 ${cat.badgeColor}`}>
                        <Icon size={20} />
                      </div>
                      <span className="text-[11px] font-semibold text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full">
                        {count > 0 ? `${count} កំណត់` : 'ពិសេស'}
                      </span>
                    </div>

                    <h3 className="font-bold text-gray-900 text-base group-hover:text-emerald-700 transition">
                      {cat.title}
                    </h3>
                    <p className="text-xs text-emerald-800 font-medium mt-0.5">
                      {cat.titleKm}
                    </p>
                    <p className="text-xs text-gray-500 mt-2 line-clamp-2 leading-relaxed">
                      {cat.descriptionKm || cat.description}
                    </p>
                  </div>

                  <div className="mt-5 pt-3 border-t border-gray-100 flex items-center justify-between text-xs font-semibold text-emerald-700 group-hover:text-emerald-800 transition">
                    <span>Configure Section</span>
                    <ArrowRight size={15} className="group-hover:translate-x-1 transition" />
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      ) : (
        /* ───────────────────────────────────────────────────────── */
        /* 2. SUB-PAGE DETAIL VIEW (Matching admin/settings.php tabs) */
        /* ───────────────────────────────────────────────────────── */
        <div className="space-y-6">
          {/* Top Bar with Back Button, Category Switcher, & Save */}
          <div className="bg-white rounded-2xl border border-gray-200/80 p-5 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div className="flex items-center gap-4">
              <button
                onClick={() => switchTab('grid')}
                className="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs sm:text-sm font-semibold transition cursor-pointer shrink-0 shadow-xs"
              >
                <ArrowLeft size={16} />
                <span>← Back to Settings</span>
              </button>

              <div className="flex items-center gap-3">
                <div className={`w-10 h-10 rounded-xl flex items-center justify-center border shadow-xs ${currentCatMeta.badgeColor}`}>
                  {React.createElement(currentCatMeta.icon, { size: 20 })}
                </div>
                <div>
                  <h2 className="text-lg sm:text-xl font-bold text-gray-900 flex items-center gap-2">
                    <span>{currentCatMeta.title}</span>
                    <span className="text-sm font-normal text-emerald-700">({currentCatMeta.titleKm})</span>
                  </h2>
                  <p className="text-xs text-gray-500">
                    {currentCatMeta.descriptionKm || currentCatMeta.description}
                  </p>
                </div>
              </div>
            </div>

            <div className="flex items-center gap-2.5">
              {/* Category Switcher Dropdown */}
              <select
                value={activeTab}
                onChange={(e) => switchTab(e.target.value)}
                className="text-xs sm:text-sm border border-gray-200 rounded-xl px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500 cursor-pointer"
              >
                {CATEGORIES_METADATA.map((c) => (
                  <option key={c.id} value={c.id}>
                    {c.title} ({c.titleKm})
                  </option>
                ))}
              </select>

              {activeTab !== 'file_manager' && activeTab !== 'flaticon' && (
                <button
                  onClick={() => handleSaveCategory(activeTab)}
                  disabled={saving}
                  className="inline-flex items-center gap-2 px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs sm:text-sm font-semibold rounded-xl shadow-xs transition cursor-pointer disabled:opacity-60 shrink-0"
                >
                  {saving ? <Loader2 size={16} className="animate-spin" /> : <Save size={16} />}
                  <span>រក្សាទុក (Save)</span>
                </button>
              )}
            </div>
          </div>

          {/* ───────────────────────────────────────────────────────── */}
          {/* TAB 1: PRODUCT COLLECTIONS (admin/settings.php collections) */}
          {/* ───────────────────────────────────────────────────────── */}
          {activeTab === 'collections' && (
            <div className="space-y-6">
              <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {/* 1. Syrup Collection Card */}
                <div className="bg-white rounded-2xl border border-gray-200/80 p-6 shadow-xs space-y-4">
                  <div className="flex items-center gap-2 pb-3 border-b border-gray-100 text-emerald-800 font-bold text-base">
                    <Droplet size={18} className="text-emerald-600" />
                    <span>Syrup Collection (កម្រងស៊ីរ៉ូ)</span>
                  </div>

                  <div>
                    <label className="block text-xs font-semibold text-gray-700 uppercase mb-1">
                      ចំណងជើង (Collection Title)
                    </label>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                      <div className="relative">
                        <span className="absolute left-3 top-1/2 -translate-y-1/2 text-[10px] font-bold text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded">EN</span>
                        <input
                          type="text"
                          value={getVal('collections', 'syrup_collection_title', 'en')}
                          onChange={(e) => setVal('collections', 'syrup_collection_title', 'en', e.target.value)}
                          placeholder="Syrup Collection"
                          className="w-full pl-11 pr-3 py-2 text-xs sm:text-sm rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                        />
                      </div>
                      <div className="relative">
                        <span className="absolute left-3 top-1/2 -translate-y-1/2 text-[10px] font-bold text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded">KM</span>
                        <input
                          type="text"
                          value={getVal('collections', 'syrup_collection_title', 'km')}
                          onChange={(e) => setVal('collections', 'syrup_collection_title', 'km', e.target.value)}
                          placeholder="ស៊ីរ៉ូរសជាតិពិសេស"
                          className="w-full pl-11 pr-3 py-2 text-xs sm:text-sm rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                        />
                      </div>
                    </div>
                  </div>

                  <div>
                    <label className="block text-xs font-semibold text-gray-700 uppercase mb-1">
                      ការពិពណ៌នា (Description)
                    </label>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                      <div className="border border-gray-200 rounded-xl p-2.5 bg-gray-50/50">
                        <span className="text-[11px] font-semibold text-gray-500 mb-1 block">English</span>
                        <textarea
                          rows={4}
                          value={getVal('collections', 'syrup_collection_description', 'en')}
                          onChange={(e) => setVal('collections', 'syrup_collection_description', 'en', e.target.value)}
                          placeholder="Syrup description in English..."
                          className="w-full p-2 text-xs sm:text-sm rounded-lg border border-gray-200 bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500"
                        />
                      </div>
                      <div className="border border-gray-200 rounded-xl p-2.5 bg-emerald-50/30">
                        <span className="text-[11px] font-semibold text-emerald-700 mb-1 block">Khmer (ភាសាខ្មែរ)</span>
                        <textarea
                          rows={4}
                          value={getVal('collections', 'syrup_collection_description', 'km')}
                          onChange={(e) => setVal('collections', 'syrup_collection_description', 'km', e.target.value)}
                          placeholder="ការពិពណ៌នាស៊ីរ៉ូជាភាសាខ្មែរ..."
                          className="w-full p-2 text-xs sm:text-sm rounded-lg border border-gray-200 bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500"
                        />
                      </div>
                    </div>
                  </div>

                  {/* Products to Display Checkbox list */}
                  <div>
                    <div className="flex items-center justify-between mb-1.5">
                      <label className="text-xs font-semibold text-gray-700 uppercase">
                        Products to Display (ទំនិញបង្ហាញក្នុងប្លុក)
                      </label>
                      <span className="text-[11px] font-semibold bg-emerald-100 text-emerald-800 px-2 py-0.5 rounded-full">
                        Total: {syrupProducts.length}
                      </span>
                    </div>
                    <div className="border border-gray-200 rounded-xl p-3 bg-white max-h-52 overflow-y-auto space-y-1.5">
                      {syrupProducts.length === 0 ? (
                        <p className="text-xs text-gray-400 py-2 text-center">មិនមានទំនិញក្នុងប្រភេទ Syrup ឡើយ</p>
                      ) : (
                        syrupProducts.map((p) => {
                          const baseId = p.base_product_id || p.id;
                          const isChecked = getSelectedProductIds('syrup_collection_products').includes(baseId);
                          return (
                            <label
                              key={p.id}
                              className="flex items-center gap-2 p-1.5 rounded-lg hover:bg-gray-50 cursor-pointer text-xs"
                            >
                              <input
                                type="checkbox"
                                checked={isChecked}
                                onChange={() => toggleProductSelection('syrup_collection_products', baseId)}
                                className="w-4 h-4 text-emerald-600 rounded"
                              />
                              <span className="text-gray-800 font-medium">{p.name}</span>
                            </label>
                          );
                        })
                      )}
                    </div>
                  </div>

                  {/* Features List */}
                  <div>
                    <label className="block text-xs font-semibold text-gray-700 uppercase mb-1.5">
                      លក្ខណៈពិសេស (Feature Points)
                    </label>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                      <div className="space-y-2 bg-gray-50 p-3 rounded-xl border border-gray-100">
                        <div className="flex items-center justify-between mb-1">
                          <span className="text-xs font-bold text-gray-600">Features (EN)</span>
                          <button
                            type="button"
                            onClick={() => {
                              const list = getFeaturesList('collections', 'syrup_collection_features', 'en');
                              setFeaturesList('collections', 'syrup_collection_features', 'en', [...list, '']);
                            }}
                            className="p-1 rounded-lg bg-white border border-gray-200 hover:bg-emerald-50 text-emerald-700 text-xs font-semibold flex items-center gap-1 cursor-pointer"
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
                              className="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg cursor-pointer"
                            >
                              <Trash2 size={13} />
                            </button>
                          </div>
                        ))}
                      </div>

                      <div className="space-y-2 bg-gray-50 p-3 rounded-xl border border-gray-100">
                        <div className="flex items-center justify-between mb-1">
                          <span className="text-xs font-bold text-emerald-800">Features (KM)</span>
                          <button
                            type="button"
                            onClick={() => {
                              const list = getFeaturesList('collections', 'syrup_collection_features', 'km');
                              setFeaturesList('collections', 'syrup_collection_features', 'km', [...list, '']);
                            }}
                            className="p-1 rounded-lg bg-white border border-gray-200 hover:bg-emerald-50 text-emerald-700 text-xs font-semibold flex items-center gap-1 cursor-pointer"
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
                              className="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg cursor-pointer"
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
                  <div className="flex items-center gap-2 pb-3 border-b border-gray-100 text-amber-800 font-bold text-base">
                    <Snowflake size={18} className="text-amber-600" />
                    <span>Powder Selection (កម្រងម្សៅ)</span>
                  </div>

                  <div>
                    <label className="block text-xs font-semibold text-gray-700 uppercase mb-1">
                      ចំណងជើង (Selection Title)
                    </label>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                      <div className="relative">
                        <span className="absolute left-3 top-1/2 -translate-y-1/2 text-[10px] font-bold text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded">EN</span>
                        <input
                          type="text"
                          value={getVal('collections', 'powder_selection_title', 'en')}
                          onChange={(e) => setVal('collections', 'powder_selection_title', 'en', e.target.value)}
                          placeholder="Powder Selection"
                          className="w-full pl-11 pr-3 py-2 text-xs sm:text-sm rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                        />
                      </div>
                      <div className="relative">
                        <span className="absolute left-3 top-1/2 -translate-y-1/2 text-[10px] font-bold text-amber-700 bg-amber-50 px-1.5 py-0.5 rounded">KM</span>
                        <input
                          type="text"
                          value={getVal('collections', 'powder_selection_title', 'km')}
                          onChange={(e) => setVal('collections', 'powder_selection_title', 'km', e.target.value)}
                          placeholder="ម្សៅឆុងភេសជ្ជៈ"
                          className="w-full pl-11 pr-3 py-2 text-xs sm:text-sm rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                        />
                      </div>
                    </div>
                  </div>

                  <div>
                    <label className="block text-xs font-semibold text-gray-700 uppercase mb-1">
                      ការពិពណ៌នា (Description)
                    </label>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                      <div className="border border-gray-200 rounded-xl p-2.5 bg-gray-50/50">
                        <span className="text-[11px] font-semibold text-gray-500 mb-1 block">English</span>
                        <textarea
                          rows={4}
                          value={getVal('collections', 'powder_selection_description', 'en')}
                          onChange={(e) => setVal('collections', 'powder_selection_description', 'en', e.target.value)}
                          placeholder="Powder description in English..."
                          className="w-full p-2 text-xs sm:text-sm rounded-lg border border-gray-200 bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500"
                        />
                      </div>
                      <div className="border border-gray-200 rounded-xl p-2.5 bg-amber-50/30">
                        <span className="text-[11px] font-semibold text-amber-700 mb-1 block">Khmer (ភាសាខ្មែរ)</span>
                        <textarea
                          rows={4}
                          value={getVal('collections', 'powder_selection_description', 'km')}
                          onChange={(e) => setVal('collections', 'powder_selection_description', 'km', e.target.value)}
                          placeholder="ការពិពណ៌នាម្សៅជាភាសាខ្មែរ..."
                          className="w-full p-2 text-xs sm:text-sm rounded-lg border border-gray-200 bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500"
                        />
                      </div>
                    </div>
                  </div>

                  {/* Products to Display Checkbox list */}
                  <div>
                    <div className="flex items-center justify-between mb-1.5">
                      <label className="text-xs font-semibold text-gray-700 uppercase">
                        Products to Display (ទំនិញបង្ហាញក្នុងប្លុក)
                      </label>
                      <span className="text-[11px] font-semibold bg-amber-100 text-amber-800 px-2 py-0.5 rounded-full">
                        Total: {powderProducts.length}
                      </span>
                    </div>
                    <div className="border border-gray-200 rounded-xl p-3 bg-white max-h-52 overflow-y-auto space-y-1.5">
                      {powderProducts.length === 0 ? (
                        <p className="text-xs text-gray-400 py-2 text-center">មិនមានទំនិញក្នុងប្រភេទ Powder ឡើយ</p>
                      ) : (
                        powderProducts.map((p) => {
                          const baseId = p.base_product_id || p.id;
                          const isChecked = getSelectedProductIds('powder_selection_products').includes(baseId);
                          return (
                            <label
                              key={p.id}
                              className="flex items-center gap-2 p-1.5 rounded-lg hover:bg-gray-50 cursor-pointer text-xs"
                            >
                              <input
                                type="checkbox"
                                checked={isChecked}
                                onChange={() => toggleProductSelection('powder_selection_products', baseId)}
                                className="w-4 h-4 text-emerald-600 rounded"
                              />
                              <span className="text-gray-800 font-medium">{p.name}</span>
                            </label>
                          );
                        })
                      )}
                    </div>
                  </div>

                  {/* Features List */}
                  <div>
                    <label className="block text-xs font-semibold text-gray-700 uppercase mb-1.5">
                      លក្ខណៈពិសេស (Feature Points)
                    </label>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                      <div className="space-y-2 bg-gray-50 p-3 rounded-xl border border-gray-100">
                        <div className="flex items-center justify-between mb-1">
                          <span className="text-xs font-bold text-gray-600">Features (EN)</span>
                          <button
                            type="button"
                            onClick={() => {
                              const list = getFeaturesList('collections', 'powder_selection_features', 'en');
                              setFeaturesList('collections', 'powder_selection_features', 'en', [...list, '']);
                            }}
                            className="p-1 rounded-lg bg-white border border-gray-200 hover:bg-amber-50 text-amber-700 text-xs font-semibold flex items-center gap-1 cursor-pointer"
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
                              className="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg cursor-pointer"
                            >
                              <Trash2 size={13} />
                            </button>
                          </div>
                        ))}
                      </div>

                      <div className="space-y-2 bg-gray-50 p-3 rounded-xl border border-gray-100">
                        <div className="flex items-center justify-between mb-1">
                          <span className="text-xs font-bold text-amber-800">Features (KM)</span>
                          <button
                            type="button"
                            onClick={() => {
                              const list = getFeaturesList('collections', 'powder_selection_features', 'km');
                              setFeaturesList('collections', 'powder_selection_features', 'km', [...list, '']);
                            }}
                            className="p-1 rounded-lg bg-white border border-gray-200 hover:bg-amber-50 text-amber-700 text-xs font-semibold flex items-center gap-1 cursor-pointer"
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
                              className="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg cursor-pointer"
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

          {/* ───────────────────────────────────────────────────────── */}
          {/* TAB 2: ABOUT PAGE (admin/settings.php about tab)          */}
          {/* ───────────────────────────────────────────────────────── */}
          {activeTab === 'about' && (
            <div className="space-y-6">
              {/* About Intro */}
              <div className="bg-white rounded-2xl border border-gray-200/80 p-6 shadow-xs space-y-4">
                <h3 className="font-bold text-gray-900 text-base flex items-center gap-2 pb-3 border-b border-gray-100">
                  <Info size={18} className="text-purple-600" />
                  <span>About Intro (ព័ត៌មានទូទៅអំពី KouPrey)</span>
                </h3>

                <div>
                  <label className="block text-xs font-semibold text-gray-700 uppercase mb-1">
                    Page Title (ចំណងជើងទំព័រ)
                  </label>
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div className="relative">
                      <span className="absolute left-3 top-1/2 -translate-y-1/2 text-[10px] font-bold text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded">EN</span>
                      <input
                        type="text"
                        value={getVal('about', 'about_title', 'en')}
                        onChange={(e) => setVal('about', 'about_title', 'en', e.target.value)}
                        placeholder="e.g. About KouPrey Coffee"
                        className="w-full pl-11 pr-3 py-2 text-xs sm:text-sm rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                      />
                    </div>
                    <div className="relative">
                      <span className="absolute left-3 top-1/2 -translate-y-1/2 text-[10px] font-bold text-purple-700 bg-purple-50 px-1.5 py-0.5 rounded">KM</span>
                      <input
                        type="text"
                        value={getVal('about', 'about_title', 'km')}
                        onChange={(e) => setVal('about', 'about_title', 'km', e.target.value)}
                        placeholder="e.g. អំពីគ្រឿងបន្ថែមរស់ជាតិគោកព្រៃ"
                        className="w-full pl-11 pr-3 py-2 text-xs sm:text-sm rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                      />
                    </div>
                  </div>
                </div>

                <div>
                  <label className="block text-xs font-semibold text-gray-700 uppercase mb-1">
                    Intro Content (ខ្លឹមសាររៀបរាប់ដើម)
                  </label>
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div className="border border-gray-200 rounded-xl p-3 bg-gray-50/50">
                      <span className="text-[11px] font-semibold text-gray-500 mb-1 block">English</span>
                      <textarea
                        rows={5}
                        value={getVal('about', 'about_content', 'en')}
                        onChange={(e) => setVal('about', 'about_content', 'en', e.target.value)}
                        placeholder="Main introduction text in English..."
                        className="w-full p-2.5 text-xs sm:text-sm rounded-lg border border-gray-200 bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500"
                      />
                    </div>
                    <div className="border border-gray-200 rounded-xl p-3 bg-purple-50/30">
                      <span className="text-[11px] font-semibold text-purple-700 mb-1 block">Khmer (ភាសាខ្មែរ)</span>
                      <textarea
                        rows={5}
                        value={getVal('about', 'about_content', 'km')}
                        onChange={(e) => setVal('about', 'about_content', 'km', e.target.value)}
                        placeholder="ខ្លឹមសាររៀបរាប់ជាភាសាខ្មែរ..."
                        className="w-full p-2.5 text-xs sm:text-sm rounded-lg border border-gray-200 bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500"
                      />
                    </div>
                  </div>
                </div>
              </div>

              {/* Purpose & Mission */}
              <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {/* Our Purpose */}
                <div className="bg-white rounded-2xl border border-gray-200/80 p-6 shadow-xs space-y-4">
                  <div className="flex items-center gap-2 pb-3 border-b border-gray-100 text-purple-800 font-bold text-base">
                    <Target size={18} className="text-purple-600" />
                    <span>Our Purpose (គោលបំណងរបស់យើង)</span>
                  </div>

                  <div>
                    <label className="block text-xs font-semibold text-gray-700 uppercase mb-1">Section Title</label>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                      <input
                        type="text"
                        value={getVal('about', 'about_purpose_title', 'en')}
                        onChange={(e) => setVal('about', 'about_purpose_title', 'en', e.target.value)}
                        placeholder="Our Purpose"
                        className="w-full px-3 py-2 text-xs sm:text-sm rounded-xl border border-gray-200"
                      />
                      <input
                        type="text"
                        value={getVal('about', 'about_purpose_title', 'km')}
                        onChange={(e) => setVal('about', 'about_purpose_title', 'km', e.target.value)}
                        placeholder="គោលបំណងរបស់យើង"
                        className="w-full px-3 py-2 text-xs sm:text-sm rounded-xl border border-gray-200"
                      />
                    </div>
                  </div>

                  <div>
                    <label className="block text-xs font-semibold text-gray-700 uppercase mb-1">Purpose Content</label>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                      <textarea
                        rows={4}
                        value={getVal('about', 'about_purpose_content', 'en')}
                        onChange={(e) => setVal('about', 'about_purpose_content', 'en', e.target.value)}
                        placeholder="Purpose details in English..."
                        className="w-full p-2.5 text-xs sm:text-sm rounded-xl border border-gray-200"
                      />
                      <textarea
                        rows={4}
                        value={getVal('about', 'about_purpose_content', 'km')}
                        onChange={(e) => setVal('about', 'about_purpose_content', 'km', e.target.value)}
                        placeholder="ខ្លឹមសារគោលបំណងជាភាសាខ្មែរ..."
                        className="w-full p-2.5 text-xs sm:text-sm rounded-xl border border-gray-200"
                      />
                    </div>
                  </div>
                </div>

                {/* Our Mission */}
                <div className="bg-white rounded-2xl border border-gray-200/80 p-6 shadow-xs space-y-4">
                  <div className="flex items-center gap-2 pb-3 border-b border-gray-100 text-purple-800 font-bold text-base">
                    <Flag size={18} className="text-purple-600" />
                    <span>Our Mission (បេសកកម្មរបស់យើង)</span>
                  </div>

                  <div>
                    <label className="block text-xs font-semibold text-gray-700 uppercase mb-1">Section Title</label>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                      <input
                        type="text"
                        value={getVal('about', 'about_mission_title', 'en')}
                        onChange={(e) => setVal('about', 'about_mission_title', 'en', e.target.value)}
                        placeholder="Our Mission"
                        className="w-full px-3 py-2 text-xs sm:text-sm rounded-xl border border-gray-200"
                      />
                      <input
                        type="text"
                        value={getVal('about', 'about_mission_title', 'km')}
                        onChange={(e) => setVal('about', 'about_mission_title', 'km', e.target.value)}
                        placeholder="បេសកកម្មរបស់យើង"
                        className="w-full px-3 py-2 text-xs sm:text-sm rounded-xl border border-gray-200"
                      />
                    </div>
                  </div>

                  <div>
                    <label className="block text-xs font-semibold text-gray-700 uppercase mb-1">Mission Content</label>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                      <textarea
                        rows={4}
                        value={getVal('about', 'about_mission_content', 'en')}
                        onChange={(e) => setVal('about', 'about_mission_content', 'en', e.target.value)}
                        placeholder="Mission details in English..."
                        className="w-full p-2.5 text-xs sm:text-sm rounded-xl border border-gray-200"
                      />
                      <textarea
                        rows={4}
                        value={getVal('about', 'about_mission_content', 'km')}
                        onChange={(e) => setVal('about', 'about_mission_content', 'km', e.target.value)}
                        placeholder="ខ្លឹមសារបេសកកម្មជាភាសាខ្មែរ..."
                        className="w-full p-2.5 text-xs sm:text-sm rounded-xl border border-gray-200"
                      />
                    </div>
                  </div>
                </div>
              </div>
            </div>
          )}

          {/* ───────────────────────────────────────────────────────── */}
          {/* TAB 3: SOCIAL MEDIA (admin/settings.php social tab)       */}
          {/* ───────────────────────────────────────────────────────── */}
          {activeTab === 'social' && (
            <div className="space-y-6">
              <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div className="lg:col-span-2 space-y-6">
                  {/* Social Banner Rich Text */}
                  <div className="bg-white rounded-2xl border border-gray-200/80 p-6 shadow-xs space-y-4">
                    <div className="flex items-center justify-between pb-3 border-b border-gray-100">
                      <h3 className="font-bold text-gray-900 text-base flex items-center gap-2">
                        <Share2 size={18} className="text-sky-600" />
                        <span>Social Banner Text (ផ្ទាំងផ្សាយ Social លើទំព័រដើម)</span>
                      </h3>
                      <button
                        type="button"
                        onClick={() => {
                          const enVal = cleanSocialBannerText(getVal('social', 'social_banner_text', 'en')) || 'Social Media';
                          const kmVal = cleanSocialBannerText(getVal('social', 'social_banner_text', 'km')) || 'ប្រព័ន្ធបណ្តាញសង្គម';
                          setVal('social', 'social_banner_text', 'en', enVal);
                          setVal('social', 'social_banner_text', 'km', kmVal);
                          showToast('បានសម្អាតកូដ និងកំណត់អក្សរស្អាតឡើងវិញ!');
                        }}
                        className="text-xs px-3 py-1.5 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 rounded-xl font-medium flex items-center gap-1.5 transition border border-emerald-200 shadow-2xs cursor-pointer"
                        title="សម្អាតកូដ HTML ឲ្យទៅជាអក្សរធម្មតា"
                      >
                        <Sparkles size={14} />
                        <span>✨ សម្អាតកូដ (Clean Text)</span>
                      </button>
                    </div>

                    {/* Emoji Quick Picker */}
                    <div className="flex flex-wrap items-center gap-1.5 p-2 bg-gray-50 rounded-xl border border-gray-100 text-sm">
                      <span className="text-xs text-gray-500 font-semibold mr-1 flex items-center gap-1">
                        <Smile size={14} /> Emoji:
                      </span>
                      {EMOJI_LIST.map((em) => (
                        <button
                          key={em}
                          type="button"
                          onClick={() => {
                            const cur = getVal('social', 'social_banner_text', 'km');
                            setVal('social', 'social_banner_text', 'km', cur + ' ' + em);
                          }}
                          className="hover:scale-125 transition cursor-pointer p-0.5"
                        >
                          {em}
                        </button>
                      ))}
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                      <div>
                        <span className="text-xs font-semibold text-gray-600 mb-1 block">English Banner Text</span>
                        <textarea
                          rows={4}
                          value={getVal('social', 'social_banner_text', 'en')}
                          onChange={(e) => setVal('social', 'social_banner_text', 'en', e.target.value)}
                          placeholder="Follow us on social media..."
                          className="w-full p-3 text-xs sm:text-sm rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 font-sans"
                        />
                      </div>
                      <div>
                        <span className="text-xs font-semibold text-sky-700 mb-1 block">Khmer Banner Text</span>
                        <textarea
                          rows={4}
                          value={getVal('social', 'social_banner_text', 'km')}
                          onChange={(e) => setVal('social', 'social_banner_text', 'km', e.target.value)}
                          placeholder="តាមដានពួកយើងនៅលើបណ្តាញសង្គម..."
                          className="w-full p-3 text-xs sm:text-sm rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 font-sans"
                        />
                      </div>
                    </div>
                  </div>

                  {/* Social URLs */}
                  <div className="bg-white rounded-2xl border border-gray-200/80 p-6 shadow-xs space-y-4">
                    <h3 className="font-bold text-gray-900 text-base pb-3 border-b border-gray-100">
                      តំណភ្ជាប់បណ្តាញសង្គម (Social Profile URLs)
                    </h3>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                      <div>
                        <label className="block text-xs font-semibold text-gray-700 uppercase mb-1 flex items-center gap-1.5">
                          <FacebookIcon size={14} className="text-blue-600" />
                          <span>Facebook Page URL</span>
                        </label>
                        <input
                          type="url"
                          value={getVal('social', 'social_facebook', 'km') || getVal('social', 'social_facebook', 'en')}
                          onChange={(e) => {
                            setVal('social', 'social_facebook', 'km', e.target.value);
                            setVal('social', 'social_facebook', 'en', e.target.value);
                          }}
                          placeholder="https://facebook.com/kouprey"
                          className="w-full px-3.5 py-2 text-xs sm:text-sm rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                        />
                      </div>

                      <div>
                        <label className="block text-xs font-semibold text-gray-700 uppercase mb-1 flex items-center gap-1.5">
                          <Send size={14} className="text-sky-500" />
                          <span>Telegram Channel / Contact</span>
                        </label>
                        <input
                          type="url"
                          value={getVal('social', 'social_telegram', 'km') || getVal('social', 'social_telegram', 'en')}
                          onChange={(e) => {
                            setVal('social', 'social_telegram', 'km', e.target.value);
                            setVal('social', 'social_telegram', 'en', e.target.value);
                          }}
                          placeholder="https://t.me/kouprey"
                          className="w-full px-3.5 py-2 text-xs sm:text-sm rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                        />
                      </div>

                      <div>
                        <label className="block text-xs font-semibold text-gray-700 uppercase mb-1 flex items-center gap-1.5">
                          <InstagramIcon size={14} className="text-rose-500" />
                          <span>Instagram Page URL</span>
                        </label>
                        <input
                          type="url"
                          value={getVal('social', 'social_instagram', 'km') || getVal('social', 'social_instagram', 'en')}
                          onChange={(e) => {
                            setVal('social', 'social_instagram', 'km', e.target.value);
                            setVal('social', 'social_instagram', 'en', e.target.value);
                          }}
                          placeholder="https://instagram.com/kouprey"
                          className="w-full px-3.5 py-2 text-xs sm:text-sm rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                        />
                      </div>

                      <div>
                        <label className="block text-xs font-semibold text-gray-700 uppercase mb-1">
                          TikTok Page URL
                        </label>
                        <input
                          type="url"
                          value={getVal('social', 'social_tiktok', 'km') || getVal('social', 'social_tiktok', 'en')}
                          onChange={(e) => {
                            setVal('social', 'social_tiktok', 'km', e.target.value);
                            setVal('social', 'social_tiktok', 'en', e.target.value);
                          }}
                          placeholder="https://tiktok.com/@kouprey"
                          className="w-full px-3.5 py-2 text-xs sm:text-sm rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                        />
                      </div>
                    </div>
                  </div>
                </div>

                {/* Live Preview Card */}
                <div className="bg-white rounded-2xl border border-gray-200/80 p-6 shadow-xs h-fit space-y-4">
                  <div className="flex items-center gap-2 pb-3 border-b border-gray-100 text-gray-900 font-bold text-sm">
                    <Eye size={16} />
                    <span>Live Preview (ផ្ទាំងបង្ហាញជាក់ស្តែង)</span>
                  </div>

                  <div className="bg-gray-950 text-white p-6 rounded-2xl text-center space-y-4">
                    <p className="text-xs sm:text-sm leading-relaxed text-gray-200">
                      {cleanSocialBannerText(getVal('social', 'social_banner_text', 'km')) || cleanSocialBannerText(getVal('social', 'social_banner_text', 'en')) || 'Social Media'}
                    </p>
                    <div className="flex items-center justify-center gap-3 pt-2">
                      <span className="w-8 h-8 rounded-full bg-blue-600/80 flex items-center justify-center text-white"><FacebookIcon size={14} /></span>
                      <span className="w-8 h-8 rounded-full bg-sky-500/80 flex items-center justify-center text-white"><Send size={14} /></span>
                      <span className="w-8 h-8 rounded-full bg-rose-600/80 flex items-center justify-center text-white"><InstagramIcon size={14} /></span>
                    </div>
                  </div>
                  <small className="text-[11px] text-gray-400 block text-center">
                    ↑ ទិដ្ឋភាពពិតប្រាកដដែលនឹងបង្ហាញនៅលើគេហទំព័រ
                  </small>
                </div>
              </div>
            </div>
          )}

          {/* ───────────────────────────────────────────────────────── */}
          {/* TAB 4: POLICIES & LEGAL (admin/settings.php policies tab)  */}
          {/* ───────────────────────────────────────────────────────── */}
          {activeTab === 'policies' && (
            <div className="space-y-6">
              <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                {/* Left Column: Form & Rich Editors (7 cols) */}
                <div className="lg:col-span-7 space-y-6">
                  {/* Privacy Policy Card */}
                  <div className="bg-white rounded-2xl border border-gray-200 shadow-xs overflow-hidden">
                    <div className="bg-gray-50/90 px-5 py-3.5 border-b border-gray-200 flex flex-wrap items-center justify-between gap-2">
                      <div className="flex items-center gap-2.5">
                        <span className="px-2.5 py-1 text-xs font-bold rounded-lg bg-blue-100 text-blue-800 border border-blue-200 flex items-center gap-1.5">
                          <ShieldCheck size={14} />
                          Privacy Policy
                        </span>
                        <span className="text-xs text-gray-500 hidden sm:inline">
                          Privacy Policy content (Side-by-side EN & KM)
                        </span>
                      </div>
                      <div className="flex items-center gap-2">
                        <button
                          type="button"
                          onClick={() => setActivePolicyPreview('privacy_policy_en')}
                          className={`text-xs px-3 py-1.5 rounded-lg border font-medium transition flex items-center gap-1.5 ${
                            activePolicyPreview === 'privacy_policy_en'
                              ? 'bg-blue-600 text-white border-blue-600 shadow-xs'
                              : 'bg-white text-gray-700 border-gray-200 hover:bg-gray-50'
                          }`}
                        >
                          <Eye size={13} /> Preview (EN)
                        </button>
                        <button
                          type="button"
                          onClick={() => setActivePolicyPreview('privacy_policy_km')}
                          className={`text-xs px-3 py-1.5 rounded-lg border font-medium transition flex items-center gap-1.5 ${
                            activePolicyPreview === 'privacy_policy_km'
                              ? 'bg-blue-600 text-white border-blue-600 shadow-xs'
                              : 'bg-white text-blue-700 border-blue-200 hover:bg-blue-50'
                          }`}
                        >
                          <Eye size={13} /> Preview (KM)
                        </button>
                      </div>
                    </div>

                    <div className="p-5">
                      <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <PolicyRichEditor
                          id="privacy_policy_en_editor"
                          label="English Version"
                          labelColor="text-gray-700"
                          value={getVal('policies', 'privacy_policy', 'en')}
                          onChange={(val) => setVal('policies', 'privacy_policy', 'en', val)}
                          placeholder="Privacy policy content in English..."
                          minHeight="260px"
                        />
                        <PolicyRichEditor
                          id="privacy_policy_km_editor"
                          label="Khmer Version (ភាសាខ្មែរ)"
                          labelColor="text-blue-700"
                          value={getVal('policies', 'privacy_policy', 'km')}
                          onChange={(val) => setVal('policies', 'privacy_policy', 'km', val)}
                          placeholder="ខ្លឹមសារគោលការណ៍ឯកជនភាពជាភាសាខ្មែរ..."
                          minHeight="260px"
                        />
                      </div>
                    </div>
                  </div>

                  {/* Terms of Service Card */}
                  <div className="bg-white rounded-2xl border border-gray-200 shadow-xs overflow-hidden">
                    <div className="bg-gray-50/90 px-5 py-3.5 border-b border-gray-200 flex flex-wrap items-center justify-between gap-2">
                      <div className="flex items-center gap-2.5">
                        <span className="px-2.5 py-1 text-xs font-bold rounded-lg bg-emerald-100 text-emerald-800 border border-emerald-200 flex items-center gap-1.5">
                          <FileText size={14} />
                          Terms of Service
                        </span>
                        <span className="text-xs text-gray-500 hidden sm:inline">
                          Terms of Service content (Side-by-side EN & KM)
                        </span>
                      </div>
                      <div className="flex items-center gap-2">
                        <button
                          type="button"
                          onClick={() => setActivePolicyPreview('terms_of_service_en')}
                          className={`text-xs px-3 py-1.5 rounded-lg border font-medium transition flex items-center gap-1.5 ${
                            activePolicyPreview === 'terms_of_service_en'
                              ? 'bg-emerald-600 text-white border-emerald-600 shadow-xs'
                              : 'bg-white text-gray-700 border-gray-200 hover:bg-gray-50'
                          }`}
                        >
                          <Eye size={13} /> Preview (EN)
                        </button>
                        <button
                          type="button"
                          onClick={() => setActivePolicyPreview('terms_of_service_km')}
                          className={`text-xs px-3 py-1.5 rounded-lg border font-medium transition flex items-center gap-1.5 ${
                            activePolicyPreview === 'terms_of_service_km'
                              ? 'bg-emerald-600 text-white border-emerald-600 shadow-xs'
                              : 'bg-white text-emerald-700 border-emerald-200 hover:bg-emerald-50'
                          }`}
                        >
                          <Eye size={13} /> Preview (KM)
                        </button>
                      </div>
                    </div>

                    <div className="p-5">
                      <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <PolicyRichEditor
                          id="terms_of_service_en_editor"
                          label="English Version"
                          labelColor="text-gray-700"
                          value={getVal('policies', 'terms_of_service', 'en')}
                          onChange={(val) => setVal('policies', 'terms_of_service', 'en', val)}
                          placeholder="Terms of service content in English..."
                          minHeight="260px"
                        />
                        <PolicyRichEditor
                          id="terms_of_service_km_editor"
                          label="Khmer Version (ភាសាខ្មែរ)"
                          labelColor="text-emerald-700"
                          value={getVal('policies', 'terms_of_service', 'km')}
                          onChange={(val) => setVal('policies', 'terms_of_service', 'km', val)}
                          placeholder="ខ្លឹមសារលក្ខខណ្ឌប្រើប្រាស់ជាភាសាខ្មែរ..."
                          minHeight="260px"
                        />
                      </div>
                    </div>
                  </div>

                  {/* Page Titles & Header Descriptions */}
                  <div className="bg-white rounded-2xl border border-gray-200 shadow-xs overflow-hidden">
                    <div className="bg-gray-50/90 px-5 py-3.5 border-b border-gray-200 flex items-center gap-2">
                      <span className="px-2.5 py-1 text-xs font-bold rounded-lg bg-gray-200 text-gray-800 border border-gray-300 flex items-center gap-1.5">
                        <Sparkles size={14} className="text-amber-600" />
                        Page Titles & Header Descriptions
                      </span>
                      <span className="text-xs text-gray-500 hidden sm:inline">
                        Edit titles and short descriptions shown in the header of legal pages
                      </span>
                    </div>

                    <div className="p-5 space-y-6">
                      {/* Privacy Policy Header block */}
                      <div className="pb-5 border-b border-gray-100 last:border-b-0 space-y-3">
                        <div className="flex items-center gap-2">
                          <span className="w-2.5 h-2.5 rounded-full bg-blue-500" />
                          <h4 className="text-xs font-bold text-gray-800 uppercase tracking-wide">
                            Privacy Policy Header (ក្បាលទំព័រគោលការណ៍ឯកជនភាព)
                          </h4>
                        </div>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                          {/* English */}
                          <div className="p-3.5 rounded-xl bg-gray-50/70 border border-gray-200 space-y-3">
                            <span className="px-2 py-0.5 text-[10px] font-bold uppercase rounded bg-gray-200 text-gray-700">
                              English
                            </span>
                            <div>
                              <label className="text-[11px] font-semibold text-gray-600 mb-1 block">Title (English)</label>
                              <input
                                type="text"
                                value={getVal('policies', 'privacy_policy_title', 'en')}
                                onChange={(e) => setVal('policies', 'privacy_policy_title', 'en', e.target.value)}
                                placeholder="Privacy Policy"
                                className="w-full px-3 py-1.5 text-xs rounded-lg border border-gray-200 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                              />
                            </div>
                            <div>
                              <label className="text-[11px] font-semibold text-gray-600 mb-1 block">Description (English)</label>
                              <textarea
                                rows={2}
                                value={getVal('policies', 'privacy_policy_desc', 'en')}
                                onChange={(e) => setVal('policies', 'privacy_policy_desc', 'en', e.target.value)}
                                placeholder="We respect your privacy and are committed to protecting your personal information."
                                className="w-full p-2 text-xs rounded-lg border border-gray-200 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                              />
                            </div>
                          </div>

                          {/* Khmer */}
                          <div className="p-3.5 rounded-xl bg-blue-50/40 border border-blue-100 space-y-3">
                            <span className="px-2 py-0.5 text-[10px] font-bold uppercase rounded bg-blue-200 text-blue-800">
                              ភាសាខ្មែរ (Khmer)
                            </span>
                            <div>
                              <label className="text-[11px] font-semibold text-blue-900 mb-1 block">ចំណងជើង (ភាសាខ្មែរ)</label>
                              <input
                                type="text"
                                value={getVal('policies', 'privacy_policy_title', 'km')}
                                onChange={(e) => setVal('policies', 'privacy_policy_title', 'km', e.target.value)}
                                placeholder="គោលការណ៍ឯកជនភាព"
                                className="w-full px-3 py-1.5 text-xs rounded-lg border border-gray-200 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 font-serif"
                              />
                            </div>
                            <div>
                              <label className="text-[11px] font-semibold text-blue-900 mb-1 block">ការពិពណ៌នាសង្ខេប (ភាសាខ្មែរ)</label>
                              <textarea
                                rows={2}
                                value={getVal('policies', 'privacy_policy_desc', 'km')}
                                onChange={(e) => setVal('policies', 'privacy_policy_desc', 'km', e.target.value)}
                                placeholder="យើងគោរពភាពឯកជនរបស់អ្នក និងប្តេជ្ញាការពារព័ត៌មានផ្ទាល់ខ្លួនរបស់អ្នក។"
                                className="w-full p-2 text-xs rounded-lg border border-gray-200 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 font-serif"
                              />
                            </div>
                          </div>
                        </div>
                      </div>

                      {/* Terms of Service Header block */}
                      <div className="space-y-3">
                        <div className="flex items-center gap-2">
                          <span className="w-2.5 h-2.5 rounded-full bg-emerald-500" />
                          <h4 className="text-xs font-bold text-gray-800 uppercase tracking-wide">
                            Terms of Service Header (ក្បាលទំព័រលក្ខខណ្ឌប្រើប្រាស់)
                          </h4>
                        </div>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                          {/* English */}
                          <div className="p-3.5 rounded-xl bg-gray-50/70 border border-gray-200 space-y-3">
                            <span className="px-2 py-0.5 text-[10px] font-bold uppercase rounded bg-gray-200 text-gray-700">
                              English
                            </span>
                            <div>
                              <label className="text-[11px] font-semibold text-gray-600 mb-1 block">Title (English)</label>
                              <input
                                type="text"
                                value={getVal('policies', 'terms_of_service_title', 'en')}
                                onChange={(e) => setVal('policies', 'terms_of_service_title', 'en', e.target.value)}
                                placeholder="Terms of Service"
                                className="w-full px-3 py-1.5 text-xs rounded-lg border border-gray-200 bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500"
                              />
                            </div>
                            <div>
                              <label className="text-[11px] font-semibold text-gray-600 mb-1 block">Description (English)</label>
                              <textarea
                                rows={2}
                                value={getVal('policies', 'terms_of_service_desc', 'en')}
                                onChange={(e) => setVal('policies', 'terms_of_service_desc', 'en', e.target.value)}
                                placeholder="Please read these Terms of Service carefully before using our services."
                                className="w-full p-2 text-xs rounded-lg border border-gray-200 bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500"
                              />
                            </div>
                          </div>

                          {/* Khmer */}
                          <div className="p-3.5 rounded-xl bg-emerald-50/40 border border-emerald-100 space-y-3">
                            <span className="px-2 py-0.5 text-[10px] font-bold uppercase rounded bg-emerald-200 text-emerald-800">
                              ភាសាខ្មែរ (Khmer)
                            </span>
                            <div>
                              <label className="text-[11px] font-semibold text-emerald-900 mb-1 block">ចំណងជើង (ភាសាខ្មែរ)</label>
                              <input
                                type="text"
                                value={getVal('policies', 'terms_of_service_title', 'km')}
                                onChange={(e) => setVal('policies', 'terms_of_service_title', 'km', e.target.value)}
                                placeholder="លក្ខខណ្ឌប្រើប្រាស់"
                                className="w-full px-3 py-1.5 text-xs rounded-lg border border-gray-200 bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500 font-serif"
                              />
                            </div>
                            <div>
                              <label className="text-[11px] font-semibold text-emerald-900 mb-1 block">ការពិពណ៌នាសង្ខេប (ភាសាខ្មែរ)</label>
                              <textarea
                                rows={2}
                                value={getVal('policies', 'terms_of_service_desc', 'km')}
                                onChange={(e) => setVal('policies', 'terms_of_service_desc', 'km', e.target.value)}
                                placeholder="សូមអានលក្ខខណ្ឌប្រើប្រាស់ទាំងនេះដោយយកចិត្តទុកដាក់ មុនពេលប្រើប្រាស់សេវាកម្មរបស់យើង។"
                                className="w-full p-2 text-xs rounded-lg border border-gray-200 bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500 font-serif"
                              />
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                {/* Right Column: Front-End Preview (5 cols) matching admin/settings.php previewFrame */}
                <div className="lg:col-span-5">
                  <div className="bg-white rounded-2xl border border-gray-200 shadow-md sticky top-24 overflow-hidden">
                    <div className="bg-gray-900 text-white px-4 py-3 flex items-center justify-between">
                      <div className="flex items-center gap-2">
                        <Eye size={15} className="text-blue-400" />
                        <span className="text-sm font-bold tracking-tight">Front-End Live Preview</span>
                      </div>
                      <span className="text-xs bg-gray-800 text-gray-200 border border-gray-700 px-2.5 py-0.5 rounded-full font-medium font-sans">
                        {activePolicyPreview === 'privacy_policy_km' && 'Privacy Policy (KM)'}
                        {activePolicyPreview === 'privacy_policy_en' && 'Privacy Policy (EN)'}
                        {activePolicyPreview === 'terms_of_service_km' && 'Terms of Service (KM)'}
                        {activePolicyPreview === 'terms_of_service_en' && 'Terms of Service (EN)'}
                      </span>
                    </div>

                    <iframe
                      key={activePolicyPreview}
                      srcDoc={previewSrcDoc}
                      title="Front-End Preview Frame"
                      className="w-full border-none bg-white"
                      style={{ height: '620px' }}
                    />
                  </div>
                </div>
              </div>
            </div>
          )}

          {/* ───────────────────────────────────────────────────────── */}
          {/* TAB 5: FILE MANAGER (Product Images Library)               */}
          {/* ───────────────────────────────────────────────────────── */}
          {activeTab === 'file_manager' && (
            <div className="bg-white rounded-2xl border border-gray-200/80 p-6 shadow-xs space-y-5">
              {/* Remove.bg Integration & Credits Card */}
              <div className="rounded-2xl border border-amber-200/80 bg-gradient-to-r from-amber-50/70 via-orange-50/40 to-amber-50/50 p-4 shadow-xs">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                  <div className="flex items-start gap-3">
                    <div className="p-2.5 rounded-xl bg-amber-500 text-white shadow-sm shadow-amber-500/20 mt-0.5">
                      <Sparkles size={20} />
                    </div>
                    <div>
                      <div className="flex items-center gap-2">
                        <h4 className="font-bold text-gray-900 text-sm">Remove.bg AI Background Removal & Auto WebP</h4>
                        <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                          <span className="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span> Active
                        </span>
                      </div>
                      <p className="text-xs text-gray-600 mt-0.5">
                        រាល់ការ Upload រូបភាពផលិតផល នឹងត្រូវ auto remove background តាម Remove.bg API និងបម្លែងជា WebP គុណភាពខ្ពស់។
                      </p>
                      <div className="flex flex-wrap items-center gap-2 mt-2 text-xs text-gray-600 font-mono">
                        <span className="bg-white/80 border border-gray-200/80 px-2 py-0.5 rounded-md text-[11px]">
                          Key: <strong className="text-gray-900 font-mono">Q9jdVLq5...Jrik</strong>
                        </span>
                        {removeBgStatus?.attributes?.api && (
                          <span className="bg-emerald-50 border border-emerald-200 text-emerald-800 px-2 py-0.5 rounded-md text-[11px] font-sans font-semibold">
                            Free API Calls: <strong>{removeBgStatus.attributes.api.free_calls}</strong> / month
                          </span>
                        )}
                        {removeBgStatus?.attributes?.credits && (
                          <span className="bg-amber-100/70 border border-amber-200 text-amber-900 px-2 py-0.5 rounded-md text-[11px] font-sans font-semibold">
                            Credits: <strong>{removeBgStatus.attributes.credits.total}</strong>
                          </span>
                        )}
                      </div>
                    </div>
                  </div>

                  <button
                    type="button"
                    onClick={handleCheckRemoveBg}
                    disabled={checkingRemoveBg}
                    className="self-start sm:self-center px-3.5 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-semibold flex items-center gap-1.5 cursor-pointer shadow-sm shadow-amber-600/20 transition active:scale-95 disabled:opacity-50"
                  >
                    {checkingRemoveBg ? <Loader2 size={13} className="animate-spin" /> : <Sparkles size={13} />}
                    <span>{checkingRemoveBg ? 'កំពុងពិនិត្យ...' : 'ពិនិត្យ Credits'}</span>
                  </button>
                </div>
              </div>

              {/* Actions Header */}
              <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-gray-100">
                <div className="flex items-center gap-2.5">
                  <div className="p-2 rounded-xl bg-cyan-50 text-cyan-700 border border-cyan-200">
                    <FolderOpen size={20} />
                  </div>
                  <div>
                    <h3 className="font-bold text-gray-900 text-base">Hosting Media Library</h3>
                    <p className="text-xs text-gray-500">បណ្ណាល័យរូបភាពលើ Hosting Server តាម Folder ផ្សេងៗ</p>
                  </div>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                  {selectedFiles.length > 0 && (
                    <button
                      type="button"
                      onClick={handleDeleteSelectedFiles}
                      className="px-3 py-2 bg-red-600 hover:bg-red-700 text-white rounded-xl text-xs font-semibold flex items-center gap-1.5 cursor-pointer shadow-xs transition"
                    >
                      <Trash2 size={14} /> លុបដែលបានជ្រើសរើស ({selectedFiles.length})
                    </button>
                  )}

                  <button
                    type="button"
                    onClick={() => handleConvertAllWebp(fileManagerFolder)}
                    disabled={convertingWebp}
                    className="px-3.5 py-2 bg-amber-500 hover:bg-amber-600 active:scale-95 text-white rounded-xl text-xs font-semibold flex items-center gap-1.5 cursor-pointer shadow-xs transition disabled:opacity-50"
                    title="បម្លែងរូបភាពក្នុង Folder បច្ចុប្បន្នទៅជា WebP"
                  >
                    {convertingWebp ? <Loader2 size={14} className="animate-spin" /> : <Wand2 size={14} />}
                    <span>{convertingWebp ? 'កំពុងបម្លែង...' : 'Convert Folder នេះ'}</span>
                  </button>

                  <button
                    type="button"
                    onClick={() => handleConvertAllWebp('all')}
                    disabled={convertingWebp}
                    className="px-3 py-2 bg-gray-800 hover:bg-black active:scale-95 text-white rounded-xl text-xs font-semibold flex items-center gap-1.5 cursor-pointer shadow-xs transition disabled:opacity-50"
                    title="បម្លែងរូបភាពគ្រប់ Folder ទាំងអស់ក្នុងប្រព័ន្ធ"
                  >
                    <RefreshCw size={13} className={convertingWebp ? "animate-spin" : ""} />
                    <span>Convert គ្រប់ Folder</span>
                  </button>

                  <button
                    type="button"
                    onClick={() => fileUploadInputRef.current?.click()}
                    className="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-semibold flex items-center gap-1.5 cursor-pointer shadow-xs transition"
                  >
                    <UploadCloud size={15} /> Upload ទៅ Folder នេះ
                  </button>
                  <input
                    ref={fileUploadInputRef}
                    type="file"
                    multiple
                    accept="image/*"
                    onChange={handleUploadNewImages}
                    className="hidden"
                  />
                </div>
              </div>

              {/* Folder Selector Pills */}
              <div className="flex items-center gap-1.5 overflow-x-auto pb-2 border-b border-gray-100">
                {[
                  { id: 'products', name: 'Products (assets/images/products)' },
                  { id: 'banner', name: 'Banners (assets/images/banner)' },
                  { id: 'banners', name: 'Banners (uploads/banners)' },
                  { id: 'categories', name: 'Categories (assets/images/categories)' },
                  { id: 'showcase', name: 'Showcase (uploads/showcase)' },
                  { id: 'related', name: 'Related (uploads/related)' },
                  { id: 'uploads', name: 'Uploads Root (uploads/)' },
                ].map((f) => (
                  <button
                    key={f.id}
                    type="button"
                    onClick={() => {
                      setFileManagerFolder(f.id);
                      setSelectedFiles([]);
                    }}
                    className={`px-3 py-1.5 rounded-xl text-xs font-bold transition-all whitespace-nowrap cursor-pointer ${
                      fileManagerFolder === f.id
                        ? 'bg-cyan-600 text-white shadow-xs'
                        : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                    }`}
                  >
                    {f.name}
                  </button>
                ))}
              </div>

              {/* Multi-select hint */}
              <div className="p-3 bg-blue-50 border border-blue-200 rounded-xl text-xs text-blue-800 flex items-center gap-2">
                <Info size={16} className="shrink-0 text-blue-600" />
                <span>ចុចលើរូបភាពណាមួយដើម្បីជ្រើសរើស (Select) ឬចុចប៊ូតុង Copy URL ដើម្បីចម្លង Link រូបភាព Hosting។</span>
              </div>

              {/* Images Grid */}
              {loadingImages ? (
                <div className="flex flex-col items-center justify-center py-16 text-gray-400 gap-2">
                  <Loader2 size={32} className="animate-spin text-cyan-600" />
                  <span className="text-xs">កំពុងទាញយករូបភាព...</span>
                </div>
              ) : fileManagerImages.length === 0 ? (
                <div className="text-center py-16 text-gray-400">
                  <FolderOpen size={48} className="mx-auto mb-2 opacity-30" />
                  <p className="text-sm font-medium">មិនមានរូបភាពក្នុង Folder នេះឡើយ</p>
                </div>
              ) : (
                <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
                  {fileManagerImages.map((img) => {
                    const isSelected = selectedFiles.includes(img.filename);
                    return (
                      <div
                        key={img.filename}
                        onClick={() => handleToggleFileSelection(img.filename)}
                        className={`group relative bg-white border rounded-2xl overflow-hidden shadow-xs hover:shadow-md transition cursor-pointer flex flex-col justify-between ${
                          isSelected ? 'border-2 border-cyan-500 ring-2 ring-cyan-200' : 'border-gray-200/80 hover:border-cyan-300'
                        }`}
                      >
                        {/* Image Thumbnail Container */}
                        <div className="relative aspect-square bg-gray-50 flex items-center justify-center p-3 overflow-hidden">
                          <img
                            src={formatImageUrl(img.url)}
                            alt={img.filename}
                            className="w-full h-full object-contain group-hover:scale-105 transition duration-200"
                            loading="lazy"
                            onError={(e) => {
                              e.target.src = 'https://placehold.co/150x150?text=No+Img';
                            }}
                          />

                          {/* Hover Copy Overlay */}
                          <div className="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition flex items-center justify-center gap-2 p-2">
                            <button
                              type="button"
                              onClick={(e) => {
                                e.stopPropagation();
                                handleCopyUrl(img.url);
                              }}
                              className="px-2.5 py-1.5 bg-white text-gray-900 rounded-lg text-[11px] font-bold shadow flex items-center gap-1 hover:bg-cyan-50 hover:text-cyan-700"
                            >
                              {copiedUrl === img.url ? <Check size={12} className="text-emerald-600" /> : <Copy size={12} />}
                              <span>{copiedUrl === img.url ? 'Copied' : 'Copy URL'}</span>
                            </button>
                          </div>

                          {/* Top-right Size Badge */}
                          <span className="absolute top-2 right-2 text-[10px] font-mono bg-black/60 text-white px-1.5 py-0.5 rounded">
                            {img.size}
                          </span>

                          {/* Selection Checkbox indicator */}
                          <div className="absolute top-2 left-2">
                            {isSelected ? (
                              <span className="w-5 h-5 rounded-full bg-cyan-600 text-white flex items-center justify-center shadow">
                                <Check size={12} />
                              </span>
                            ) : (
                              <span className="w-5 h-5 rounded-full bg-white/80 border border-gray-300 opacity-0 group-hover:opacity-100 transition" />
                            )}
                          </div>
                        </div>

                        {/* File details & Delete action */}
                        <div className="p-2.5 bg-white border-t border-gray-100 flex items-center justify-between gap-1.5">
                          <span className="text-[11px] font-medium text-gray-700 truncate" title={img.filename}>
                            {img.filename}
                          </span>
                          <button
                            type="button"
                            onClick={(e) => {
                              e.stopPropagation();
                              handleDeleteSingleFile(img.filename);
                            }}
                            className="text-gray-400 hover:text-red-600 p-1 hover:bg-red-50 rounded transition cursor-pointer"
                            title="លុបឯកសារនេះ"
                          >
                            <Trash2 size={13} />
                          </button>
                        </div>
                      </div>
                    );
                  })}
                </div>
              )}
            </div>
          )}

          {/* ───────────────────────────────────────────────────────── */}
          {/* TAB 6: FLATICON BROWSER CLONE (admin/settings.php flaticon)*/}
          {/* ───────────────────────────────────────────────────────── */}
          {activeTab === 'flaticon' && (
            <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
              {/* Left Column: 3-step Guide */}
              <div className="lg:col-span-5 bg-white rounded-2xl border border-gray-200/80 p-6 shadow-xs space-y-5">
                <div className="flex items-center gap-2.5 pb-3 border-b border-gray-100">
                  <div className="p-2 rounded-xl bg-pink-50 text-pink-700 border border-pink-200">
                    <Search size={18} />
                  </div>
                  <h3 className="font-bold text-gray-900 text-base">របៀបស្វែងរក និងទាញយក Icon</h3>
                </div>

                <p className="text-xs text-gray-600 leading-relaxed">
                  គេហទំព័រ Flaticon មានប្រព័ន្ធការពារសុវត្ថិភាពខ្ពស់ (Cloudflare) ដែលមិនអនុញ្ញាតឱ្យបើកបញ្ជូលក្នុងផ្ទាំង (Embedded iframe) ដោយសេរីឡើយ។ សូមអនុវត្តតាមជំហានងាយៗទាំង ៣ ខាងក្រោម៖
                </p>

                <div className="space-y-4">
                  <div className="flex items-start gap-3">
                    <span className="w-6 h-6 rounded-full bg-emerald-600 text-white flex items-center justify-center text-xs font-bold shrink-0 mt-0.5">
                      1
                    </span>
                    <div>
                      <h4 className="text-xs font-bold text-gray-900">បើកគេហទំព័រ Flaticon</h4>
                      <p className="text-[11px] text-gray-500 mt-0.5">
                        ចុចលើប៊ូតុងពណ៌ខៀវខាងក្រោមដើម្បីបើក Flaticon ក្នុងផ្ទាំងថ្មីមួយដោយសុវត្ថិភាព។
                      </p>
                    </div>
                  </div>

                  <div className="flex items-start gap-3">
                    <span className="w-6 h-6 rounded-full bg-emerald-600 text-white flex items-center justify-center text-xs font-bold shrink-0 mt-0.5">
                      2
                    </span>
                    <div>
                      <h4 className="text-xs font-bold text-gray-900">ស្វែងរក និងចម្លង Link (Copy)</h4>
                      <p className="text-[11px] text-gray-500 mt-0.5">
                        ស្វែងរក Icon ដែលចង់បាន រួចចុចស្តាំ (Right-Click) លើរូបនោះ ហើយជ្រើសរើស <strong>"Copy image address"</strong> (ឬ Copy image link)។
                      </p>
                    </div>
                  </div>

                  <div className="flex items-start gap-3">
                    <span className="w-6 h-6 rounded-full bg-emerald-600 text-white flex items-center justify-center text-xs font-bold shrink-0 mt-0.5">
                      3
                    </span>
                    <div>
                      <h4 className="text-xs font-bold text-gray-900">យកមកប្រើប្រាស់</h4>
                      <p className="text-[11px] text-gray-500 mt-0.5">
                        បិទទំព័រនោះវិញ រួចយក Link មក Paste ចូលក្នុងប្រអប់ Image URL នៃទំព័រគ្រប់គ្រង រួចចុច Save ជាការស្រេច!
                      </p>
                    </div>
                  </div>
                </div>

                <div className="pt-3">
                  <a
                    href="https://www.flaticon.com"
                    target="_blank"
                    rel="noreferrer"
                    className="w-full py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-bold text-xs sm:text-sm flex items-center justify-center gap-2 shadow-xs transition"
                  >
                    <ExternalLink size={16} /> បើកគេហទំព័រ Flaticon ក្នុងផ្ទាំងថ្មី
                  </a>
                </div>
              </div>

              {/* Right Column: Embedded Clone Preview */}
              <div className="lg:col-span-7 bg-white rounded-2xl border border-gray-200/80 shadow-xs overflow-hidden flex flex-col h-[550px]">
                {/* Browser bar */}
                <div className="bg-gray-100 px-4 py-2.5 border-b border-gray-200 flex items-center gap-2">
                  <span className="w-3 h-3 rounded-full bg-red-400 inline-block" />
                  <span className="w-3 h-3 rounded-full bg-amber-400 inline-block" />
                  <span className="w-3 h-3 rounded-full bg-emerald-400 inline-block" />
                  <div className="flex-1 ml-2 relative">
                    <input
                      type="text"
                      value={flaticonUrl}
                      onChange={(e) => setFlaticonUrl(e.target.value)}
                      className="w-full pl-3 pr-8 py-1 text-xs rounded-lg border border-gray-300 bg-white"
                    />
                  </div>
                  <a
                    href={flaticonUrl}
                    target="_blank"
                    rel="noreferrer"
                    className="p-1.5 text-gray-500 hover:text-blue-600 hover:bg-white rounded-lg transition"
                  >
                    <ExternalLink size={14} />
                  </a>
                </div>

                {/* Browser Frame */}
                <div className="flex-1 bg-white relative">
                  <iframe
                    src={flaticonUrl}
                    title="Flaticon Browser"
                    className="w-full h-full border-none"
                    sandbox="allow-scripts allow-same-origin allow-popups"
                  />
                </div>
              </div>
            </div>
          )}

          {/* ───────────────────────────────────────────────────────── */}
          {/* TAB: STORE LOCATION & MAP (Visit Us Section on Frontend)  */}
          {/* ───────────────────────────────────────────────────────── */}
          {activeTab === 'location' && (
            <div className="space-y-6">
              {/* Header Box */}
              <div className="bg-gradient-to-r from-emerald-50 via-teal-50/50 to-white rounded-2xl border border-emerald-200/80 p-6 shadow-xs flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div className="flex items-center gap-3">
                  <div className="w-12 h-12 rounded-xl bg-emerald-600 text-white flex items-center justify-center shadow-md shadow-emerald-600/20 flex-shrink-0">
                    <MapPin size={24} />
                  </div>
                  <div>
                    <h3 className="text-lg font-bold text-gray-900">
                      Store Location & Map (ទីតាំងហាង & ផែនទី)
                    </h3>
                    <p className="text-xs text-gray-500 mt-0.5">
                      គ្រប់គ្រងផ្នែក Visit Us ដែលបង្ហាញលើទំព័រដើម / ផលិតផល រួមមាន អាសយដ្ឋាន ម៉ោងបើកដំណើរការ និងផែនទី Google Maps
                    </p>
                  </div>
                </div>
                <a
                  href="http://localhost:5173/#location"
                  target="_blank"
                  rel="noopener noreferrer"
                  className="inline-flex items-center gap-2 px-4 py-2 bg-white hover:bg-emerald-50 text-emerald-800 text-xs font-bold rounded-xl border border-emerald-200 shadow-2xs transition"
                >
                  <Eye size={15} />
                  <span>មើលលើគេហទំព័រ</span>
                  <ExternalLink size={13} />
                </a>
              </div>

              {/* Grid: 2 Columns for Forms */}
              <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {/* 1. Section Headers */}
                <div className="bg-white rounded-2xl border border-gray-200/80 p-6 shadow-xs space-y-4">
                  <div className="border-b border-gray-100 pb-3">
                    <h4 className="font-bold text-gray-900 text-sm flex items-center gap-2">
                      <Target size={17} className="text-orange-500" />
                      <span>១. ចំណងជើងផ្នែក (Section Header Texts)</span>
                    </h4>
                    <p className="text-xs text-gray-400 mt-0.5">កំណត់ចំណងជើងធំ និងពាក្យរៀបរាប់ខាងលើផ្នែក</p>
                  </div>

                  {/* Subtitle Tag */}
                  <div>
                    <label className="text-xs font-semibold text-gray-700 block mb-1">
                      ស្លាកខាងលើ (Subtitle Tag)
                    </label>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                      <div className="relative">
                        <span className="absolute left-3 top-1/2 -translate-y-1/2 text-[10px] font-bold text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded">EN</span>
                        <input
                          type="text"
                          value={getVal('location', 'location_subtitle_tag', 'en', 'VISIT US')}
                          onChange={(e) => setVal('location', 'location_subtitle_tag', 'en', e.target.value)}
                          placeholder="VISIT US"
                          className="w-full pl-11 pr-3 py-2 text-xs sm:text-sm rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                        />
                      </div>
                      <div className="relative">
                        <span className="absolute left-3 top-1/2 -translate-y-1/2 text-[10px] font-bold text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded">KM</span>
                        <input
                          type="text"
                          value={getVal('location', 'location_subtitle_tag', 'km', 'VISIT US')}
                          onChange={(e) => setVal('location', 'location_subtitle_tag', 'km', e.target.value)}
                          placeholder="VISIT US"
                          className="w-full pl-11 pr-3 py-2 text-xs sm:text-sm rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                        />
                      </div>
                    </div>
                  </div>

                  {/* Main Title */}
                  <div>
                    <label className="text-xs font-semibold text-gray-700 block mb-1">
                      ចំណងជើងធំ (Main Heading)
                    </label>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                      <div className="relative">
                        <span className="absolute left-3 top-1/2 -translate-y-1/2 text-[10px] font-bold text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded">EN</span>
                        <input
                          type="text"
                          value={getVal('location', 'location_title', 'en', 'Our Locations')}
                          onChange={(e) => setVal('location', 'location_title', 'en', e.target.value)}
                          placeholder="Our Locations"
                          className="w-full pl-11 pr-3 py-2 text-xs sm:text-sm rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                        />
                      </div>
                      <div className="relative">
                        <span className="absolute left-3 top-1/2 -translate-y-1/2 text-[10px] font-bold text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded">KM</span>
                        <input
                          type="text"
                          value={getVal('location', 'location_title', 'km', 'ទីតាំងរបស់យើង')}
                          onChange={(e) => setVal('location', 'location_title', 'km', e.target.value)}
                          placeholder="ទីតាំងរបស់យើង"
                          className="w-full pl-11 pr-3 py-2 text-xs sm:text-sm rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                        />
                      </div>
                    </div>
                  </div>

                  {/* Description */}
                  <div>
                    <label className="text-xs font-semibold text-gray-700 block mb-1">
                      ការពិពណ៌នាសង្ខេប (Description)
                    </label>
                    <div className="space-y-2">
                      <div className="border border-gray-200 rounded-xl p-2.5 bg-gray-50/50">
                        <span className="text-[11px] font-semibold text-gray-500 mb-1 block">English</span>
                        <textarea
                          rows={2}
                          value={getVal('location', 'location_desc', 'en', 'Come experience the aroma and taste of our premium coffee in person.')}
                          onChange={(e) => setVal('location', 'location_desc', 'en', e.target.value)}
                          placeholder="Description in English..."
                          className="w-full p-2 text-xs rounded-lg border border-gray-200 bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500"
                        />
                      </div>
                      <div className="border border-gray-200 rounded-xl p-2.5 bg-emerald-50/30">
                        <span className="text-[11px] font-semibold text-emerald-800 mb-1 block">Khmer (ភាសាខ្មែរ)</span>
                        <textarea
                          rows={2}
                          value={getVal('location', 'location_desc', 'km', 'សូមអញ្ជើញមកទទួលយកបទពិសោធន៍ក្លិនក្រអូប និងរសជាតិគ្រឿងបន្ថែមរស់ជាតិគុណភាពខ្ពស់របស់យើងដោយផ្ទាល់។')}
                          onChange={(e) => setVal('location', 'location_desc', 'km', e.target.value)}
                          placeholder="ការពិពណ៌នាជាភាសាខ្មែរ..."
                          className="w-full p-2 text-xs rounded-lg border border-gray-200 bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500"
                        />
                      </div>
                    </div>
                  </div>
                </div>

                {/* 2. Store Info & Schedule */}
                <div className="bg-white rounded-2xl border border-gray-200/80 p-6 shadow-xs space-y-4">
                  <div className="border-b border-gray-100 pb-3">
                    <h4 className="font-bold text-gray-900 text-sm flex items-center gap-2">
                      <Store size={17} className="text-emerald-600" />
                      <span>២. ព័ត៌មានហាង & ម៉ោងបើក (Store Details & Schedule)</span>
                    </h4>
                    <p className="text-xs text-gray-400 mt-0.5">ឈ្មោះសាខាហាង អាសយដ្ឋាន និងម៉ោងបម្រើសេវាកម្ម</p>
                  </div>

                  {/* Store Name */}
                  <div>
                    <label className="text-xs font-semibold text-gray-700 block mb-1">
                      ឈ្មោះហាង (Store Branch Name)
                    </label>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                      <div className="relative">
                        <span className="absolute left-3 top-1/2 -translate-y-1/2 text-[10px] font-bold text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded">EN</span>
                        <input
                          type="text"
                          value={getVal('location', 'location_store_name', 'en', 'KouPrey HQ')}
                          onChange={(e) => setVal('location', 'location_store_name', 'en', e.target.value)}
                          placeholder="KouPrey HQ"
                          className="w-full pl-11 pr-3 py-2 text-xs sm:text-sm rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                        />
                      </div>
                      <div className="relative">
                        <span className="absolute left-3 top-1/2 -translate-y-1/2 text-[10px] font-bold text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded">KM</span>
                        <input
                          type="text"
                          value={getVal('location', 'location_store_name', 'km', 'KouPrey HQ')}
                          onChange={(e) => setVal('location', 'location_store_name', 'km', e.target.value)}
                          placeholder="KouPrey HQ"
                          className="w-full pl-11 pr-3 py-2 text-xs sm:text-sm rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                        />
                      </div>
                    </div>
                  </div>

                  {/* Address Section */}
                  <div>
                    <div className="flex items-center justify-between mb-1">
                      <label className="text-xs font-semibold text-gray-700">អាសយដ្ឋាន (Company Address)</label>
                      <span className="text-[10px] text-gray-400 font-mono">company_address</span>
                    </div>
                    <div className="space-y-2">
                      <div className="border border-gray-200 rounded-xl p-2.5 bg-gray-50/50">
                        <span className="text-[11px] font-semibold text-gray-500 mb-1 block">English Address</span>
                        <textarea
                          rows={2}
                          value={getVal('location', 'company_address', 'en', '120408 Sangkat Boeung Kak 2, Khan Tuol Kouk, Phnom Penh, Cambodia.')}
                          onChange={(e) => {
                            setVal('location', 'company_address', 'en', e.target.value);
                            setVal('general', 'company_address', 'en', e.target.value);
                          }}
                          placeholder="English Address..."
                          className="w-full p-2 text-xs rounded-lg border border-gray-200 bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500"
                        />
                      </div>
                      <div className="border border-gray-200 rounded-xl p-2.5 bg-emerald-50/30">
                        <span className="text-[11px] font-semibold text-emerald-800 mb-1 block">Khmer Address (អាសយដ្ឋានខ្មែរ)</span>
                        <textarea
                          rows={2}
                          value={getVal('location', 'company_address', 'km', '120408 សង្កាត់បឹងកក់ 2 ខណ្ឌទួលគោក រាជធានីភ្នំពេញ ប្រទេសកម្ពុជា។')}
                          onChange={(e) => {
                            setVal('location', 'company_address', 'km', e.target.value);
                            setVal('general', 'company_address', 'km', e.target.value);
                          }}
                          placeholder="អាសយដ្ឋានជាភាសាខ្មែរ..."
                          className="w-full p-2 text-xs rounded-lg border border-gray-200 bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500"
                        />
                      </div>
                    </div>
                  </div>

                  {/* Hours Section */}
                  <div>
                    <div className="flex items-center justify-between mb-1">
                      <label className="text-xs font-semibold text-gray-700">ម៉ោងបើកដំណើរការ (Opening Hours)</label>
                      <span className="text-[10px] text-gray-400 font-mono">company_hours</span>
                    </div>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                      <div className="relative">
                        <span className="absolute left-3 top-1/2 -translate-y-1/2 text-[10px] font-bold text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded">EN</span>
                        <input
                          type="text"
                          value={getVal('location', 'company_hours', 'en', 'Daily: 7:00 AM - 8:00 PM')}
                          onChange={(e) => {
                            setVal('location', 'company_hours', 'en', e.target.value);
                            setVal('general', 'company_hours', 'en', e.target.value);
                          }}
                          placeholder="Daily: 7:00 AM - 8:00 PM"
                          className="w-full pl-11 pr-3 py-2 text-xs sm:text-sm rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                        />
                      </div>
                      <div className="relative">
                        <span className="absolute left-3 top-1/2 -translate-y-1/2 text-[10px] font-bold text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded">KM</span>
                        <input
                          type="text"
                          value={getVal('location', 'company_hours', 'km', 'រៀងរាល់ថ្ងៃ៖ ម៉ោង ៧:០០ ព្រឹក - ៨:០០ យប់')}
                          onChange={(e) => {
                            setVal('location', 'company_hours', 'km', e.target.value);
                            setVal('general', 'company_hours', 'km', e.target.value);
                          }}
                          placeholder="រៀងរាល់ថ្ងៃ៖ ម៉ោង ៧:០០ ព្រឹក - ៨:០០ យប់"
                          className="w-full pl-11 pr-3 py-2 text-xs sm:text-sm rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                        />
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              {/* Card 3: Google Maps & Navigation Link */}
              <div className="bg-white rounded-2xl border border-gray-200/80 p-6 shadow-xs space-y-4">
                <div className="border-b border-gray-100 pb-3">
                  <h4 className="font-bold text-gray-900 text-sm flex items-center gap-2">
                    <Navigation size={17} className="text-sky-600" />
                    <span>៣. ការកំណត់ Google Maps & ប៊ូតុងទិសដៅ (Maps Integration)</span>
                  </h4>
                  <p className="text-xs text-gray-400 mt-0.5">តំណភ្ជាប់ Google Maps Embed Iframe និងតំណភ្ជាប់ផ្ទាល់សម្រាប់ប៊ូតុង "Get Directions"</p>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  {/* Map Embed URL */}
                  <div className="space-y-1">
                    <label className="text-xs font-semibold text-gray-700 block">
                      Google Maps Embed Iframe URL
                    </label>
                    <input
                      type="url"
                      value={getVal('location', 'company_map_embed', 'en', 'https://www.google.com/maps/embed?pb=!1m14!1m12!1m3!1d292.34896165878865!2d104.91197826608598!3d11.55083956811418!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!5e1!3m2!1sen!2skh!4v1767834278383!5m2!1sen!2skh')}
                      onChange={(e) => {
                        let val = e.target.value.trim();
                        const match = val.match(/src=["']([^"']+)["']/);
                        if (match) val = match[1];
                        setVal('location', 'company_map_embed', 'en', val);
                        setVal('location', 'company_map_embed', 'km', val);
                      }}
                      placeholder="https://www.google.com/maps/embed?pb=..."
                      className="w-full px-3 py-2 text-xs sm:text-sm font-mono rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                    />
                    <p className="text-[11px] text-gray-400">
                      💡 គន្លឹះ៖ ចូល Google Maps &gt; Share &gt; Embed a map &gt; ចម្លង URL ក្នុង <code>src="..."</code>
                    </p>
                  </div>

                  {/* Get Directions Link */}
                  <div className="space-y-1">
                    <label className="text-xs font-semibold text-gray-700 block">
                      Google Maps Direct Link (Get Directions URL)
                    </label>
                    <input
                      type="url"
                      value={getVal('location', 'company_map_link', 'en', 'https://maps.app.goo.gl/v88Vyavc1UoykzgNA')}
                      onChange={(e) => {
                        setVal('location', 'company_map_link', 'en', e.target.value.trim());
                        setVal('location', 'company_map_link', 'km', e.target.value.trim());
                      }}
                      placeholder="https://maps.app.goo.gl/..."
                      className="w-full px-3 py-2 text-xs sm:text-sm font-mono rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                    />
                    <p className="text-[11px] text-gray-400">
                      🔗 តំណភ្ជាប់ដែលបើក Google Maps App ដោយផ្ទាល់ពេលអតិថិជនចុច
                    </p>
                  </div>
                </div>

                {/* Button Text */}
                <div className="pt-2">
                  <label className="text-xs font-semibold text-gray-700 block mb-1">
                    អក្សរលើប៊ូតុងទិសដៅ (Button Text)
                  </label>
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div className="relative">
                      <span className="absolute left-3 top-1/2 -translate-y-1/2 text-[10px] font-bold text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded">EN</span>
                      <input
                        type="text"
                        value={getVal('location', 'location_btn_text', 'en', 'Get Directions')}
                        onChange={(e) => setVal('location', 'location_btn_text', 'en', e.target.value)}
                        placeholder="Get Directions"
                        className="w-full pl-11 pr-3 py-2 text-xs sm:text-sm rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                      />
                    </div>
                    <div className="relative">
                      <span className="absolute left-3 top-1/2 -translate-y-1/2 text-[10px] font-bold text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded">KM</span>
                      <input
                        type="text"
                        value={getVal('location', 'location_btn_text', 'km', 'Get Directions')}
                        onChange={(e) => setVal('location', 'location_btn_text', 'km', e.target.value)}
                        placeholder="Get Directions"
                        className="w-full pl-11 pr-3 py-2 text-xs sm:text-sm rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                      />
                    </div>
                  </div>
                </div>
              </div>

              {/* Card 4: Interactive Live Preview */}
              <div className="bg-white rounded-2xl border border-gray-200/80 p-6 shadow-xs space-y-4">
                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 pb-3">
                  <div className="flex items-center gap-2">
                    <Eye size={17} className="text-emerald-600" />
                    <h4 className="font-bold text-gray-900 text-sm">
                      ទិដ្ឋភាពជាក់ស្តែង (Live Interactive Preview)
                    </h4>
                  </div>
                  <div className="inline-flex p-1 bg-gray-100 rounded-xl text-xs font-bold">
                    <button
                      type="button"
                      onClick={() => setLocationPreviewLang('km')}
                      className={`px-3 py-1 rounded-lg transition cursor-pointer ${
                        locationPreviewLang === 'km' ? 'bg-white text-emerald-700 shadow-xs' : 'text-gray-500 hover:text-gray-900'
                      }`}
                    >
                      ភាសាខ្មែរ (KM)
                    </button>
                    <button
                      type="button"
                      onClick={() => setLocationPreviewLang('en')}
                      className={`px-3 py-1 rounded-lg transition cursor-pointer ${
                        locationPreviewLang === 'en' ? 'bg-white text-emerald-700 shadow-xs' : 'text-gray-500 hover:text-gray-900'
                      }`}
                    >
                      English (EN)
                    </button>
                  </div>
                </div>

                {/* Rendered Preview Card */}
                <div className="p-4 sm:p-8 bg-gray-50/80 rounded-2xl border border-gray-100 overflow-hidden">
                  <div className="text-center mb-8">
                    <span className="text-orange-600 font-bold tracking-widest uppercase text-xs mb-2 block">
                      {getVal('location', 'location_subtitle_tag', locationPreviewLang, 'VISIT US')}
                    </span>
                    <h3 className="text-2xl sm:text-3xl font-black text-gray-900 mb-2">
                      {getVal('location', 'location_title', locationPreviewLang, locationPreviewLang === 'km' ? 'ទីតាំងរបស់យើង' : 'Our Locations')}
                    </h3>
                    <p className="text-gray-500 max-w-xl mx-auto text-xs sm:text-sm">
                      {getVal('location', 'location_desc', locationPreviewLang, locationPreviewLang === 'km' ? 'សូមអញ្ជើញមកទទួលយកបទពិសោធន៍ក្លិនក្រអូប និងរសជាតិគ្រឿងបន្ថែមរស់ជាតិគុណភាពខ្ពស់របស់យើងដោយផ្ទាល់។' : 'Come experience the aroma and taste of our premium coffee in person.')}
                    </p>
                  </div>

                  <div className="bg-white rounded-3xl shadow-xl overflow-hidden flex flex-col md:flex-row border border-gray-100">
                    {/* Left Details */}
                    <div className="w-full md:w-1/2 p-6 flex flex-col justify-center space-y-4">
                      <h4 className="text-lg font-bold text-gray-900 flex items-center gap-2">
                        <span className="w-7 h-7 rounded-full bg-orange-500 flex items-center justify-center text-white text-xs">
                          <Store size={14} />
                        </span>
                        <span>{getVal('location', 'location_store_name', locationPreviewLang, 'KouPrey HQ')}</span>
                      </h4>

                      <div className="space-y-3 text-xs">
                        <div className="flex items-start gap-3 p-3 rounded-xl bg-gray-50">
                          <div className="w-9 h-9 rounded-lg bg-orange-100 text-orange-600 flex items-center justify-center flex-shrink-0">
                            <MapPin size={18} />
                          </div>
                          <div>
                            <span className="text-[10px] font-bold text-gray-400 uppercase tracking-wider block">Address</span>
                            <span className="font-bold text-gray-900 block">
                              {getVal('location', 'location_address_label', locationPreviewLang, locationPreviewLang === 'km' ? 'ហាងរបស់យើង' : 'Our Store')}
                            </span>
                            <p className="text-gray-600 text-[11px] leading-relaxed mt-0.5">
                              {getVal('location', 'company_address', locationPreviewLang, locationPreviewLang === 'km' ? '120408 សង្កាត់បឹងកក់ 2 ខណ្ឌទួលគោក រាជធានីភ្នំពេញ ប្រទេសកម្ពុជា។' : '120408 Sangkat Boeung Kak 2, Khan Tuol Kouk, Phnom Penh, Cambodia.')}
                            </p>
                          </div>
                        </div>

                        <div className="flex items-start gap-3 p-3 rounded-xl bg-gray-50">
                          <div className="w-9 h-9 rounded-lg bg-blue-100 text-blue-600 flex items-center justify-center flex-shrink-0">
                            <Clock size={18} />
                          </div>
                          <div>
                            <span className="text-[10px] font-bold text-gray-400 uppercase tracking-wider block">Schedule</span>
                            <span className="font-bold text-gray-900 block">
                              {getVal('location', 'location_hours_label', locationPreviewLang, locationPreviewLang === 'km' ? 'ម៉ោងបើកដំណើរការ' : 'Opening Hours')}
                            </span>
                            <p className="text-gray-600 text-[11px] leading-relaxed mt-0.5">
                              {getVal('location', 'company_hours', locationPreviewLang, locationPreviewLang === 'km' ? 'រៀងរាល់ថ្ងៃ៖ ម៉ោង ៧:០០ ព្រឹក - ៨:០០ យប់' : 'Daily: 7:00 AM - 8:00 PM')}
                            </p>
                          </div>
                        </div>
                      </div>

                      <div className="pt-2">
                        <div className="inline-flex items-center gap-2 bg-gray-900 text-white py-2.5 px-5 rounded-xl font-bold text-xs shadow-md">
                          <Navigation size={14} />
                          <span>{getVal('location', 'location_btn_text', locationPreviewLang, 'Get Directions')}</span>
                        </div>
                      </div>
                    </div>

                    {/* Right Map */}
                    <div className="w-full md:w-1/2 h-[280px] md:h-auto min-h-[260px] relative bg-gray-200">
                      <div className="absolute top-3 left-3 z-10 bg-white/95 text-gray-800 text-[11px] font-bold px-2.5 py-1 rounded-md shadow-xs flex items-center gap-1 border border-gray-200">
                        <span>Open in Maps</span>
                        <ExternalLink size={11} />
                      </div>
                      <iframe
                        src={getVal('location', 'company_map_embed', 'en', 'https://www.google.com/maps/embed?pb=!1m14!1m12!1m3!1d292.34896165878865!2d104.91197826608598!3d11.55083956811418!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!5e1!3m2!1sen!2skh!4v1767834278383!5m2!1sen!2skh')}
                        className="w-full h-full border-0 contrast-[1.05]"
                        allowFullScreen=""
                        loading="lazy"
                        title="Map Preview"
                      />
                    </div>
                  </div>
                </div>
              </div>
            </div>
          )}

          {/* ───────────────────────────────────────────────────────── */}
          {/* STANDARD CATEGORIES (General, Hero, Footer, Newsletter, etc) */}
          {/* ───────────────────────────────────────────────────────── */}
          {activeTab !== 'collections' &&
           activeTab !== 'about' &&
           activeTab !== 'social' &&
           activeTab !== 'policies' &&
           activeTab !== 'file_manager' &&
           activeTab !== 'flaticon' &&
           activeTab !== 'location' && (
            <div className="bg-white rounded-2xl border border-gray-200/80 p-6 shadow-xs space-y-6">
              <div className="border-b border-gray-100 pb-4">
                <h3 className="text-base font-bold text-gray-900">
                  {currentCatMeta.title} ({currentCatMeta.titleKm})
                </h3>
                <p className="text-xs text-gray-500 mt-0.5">
                  បំពេញទិន្នន័យជាភាសាអង់គ្លេស (English) និង ភាសាខ្មែរ (Khmer)
                </p>
              </div>

              {Object.keys(groupedSettings[activeTab] || {}).length === 0 ? (
                <div className="text-center py-12 text-gray-400">
                  <Info size={36} className="mx-auto mb-2 opacity-40" />
                  <p className="text-sm font-medium">មិនមានទិន្នន័យក្នុងផ្នែកនេះនៅឡើយទេ</p>
                </div>
              ) : (
                <div className="space-y-6">
                  {Object.entries(groupedSettings[activeTab] || {}).map(([key, item]) => {
                    const isHeroBg = key === 'hero_background_image';
                    const isLogo = key === 'company_logo';
                    const isImage =
                      isHeroBg ||
                      isLogo ||
                      key.includes('image') ||
                      key.includes('banner_') ||
                      item.type === 'image';
                    const isLongText =
                      key.includes('content') ||
                      key.includes('desc') ||
                      item.type === 'textarea' ||
                      item.type === 'editor';
                    const isBoolean = item.type === 'boolean';

                    return (
                      <div key={key} className="border border-gray-200/80 rounded-2xl p-5 bg-white shadow-xs space-y-3">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                          <div>
                            <h4 className="text-sm font-bold text-gray-900">
                              {formatLabel(key)}
                            </h4>
                            <span className="text-[11px] font-mono text-gray-400">
                              Key: {key}
                            </span>
                            {item.description && (
                              <p className="text-xs text-gray-500 mt-0.5">{item.description}</p>
                            )}
                          </div>
                          <span className="text-[10px] font-semibold text-gray-500 uppercase bg-gray-100 px-2 py-0.5 rounded">
                            {item.type || 'text'}
                          </span>
                        </div>

                        {/* Image Uploader */}
                        {isImage ? (
                          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-1">
                            <div>
                              <span className="text-xs font-semibold text-gray-600 mb-1.5 block">
                                English {isLogo ? 'Logo' : isHeroBg ? 'Hero Background' : 'Image'}
                              </span>
                              <ImageUpload
                                value={getVal(activeTab, key, 'en')}
                                onChange={(path) => setVal(activeTab, key, 'en', path)}
                                type={isLogo ? 'logo' : 'banner'}
                                label="Upload English Image"
                              />
                            </div>
                            <div>
                              <span className="text-xs font-semibold text-emerald-800 mb-1.5 block">
                                Khmer {isLogo ? 'Logo' : isHeroBg ? 'Hero Background' : 'Image'}
                              </span>
                              <ImageUpload
                                value={getVal(activeTab, key, 'km')}
                                onChange={(path) => setVal(activeTab, key, 'km', path)}
                                type={isLogo ? 'logo' : 'banner'}
                                label="Upload Khmer Image"
                              />
                            </div>
                          </div>
                        ) : isLongText ? (
                          /* Textarea */
                          <div className="grid grid-cols-1 md:grid-cols-2 gap-3 pt-1">
                            <div className="border border-gray-200 rounded-xl p-3 bg-gray-50/50">
                              <span className="text-xs font-semibold text-gray-600 mb-1.5 block">English</span>
                              <textarea
                                rows={3}
                                value={getVal(activeTab, key, 'en')}
                                onChange={(e) => setVal(activeTab, key, 'en', e.target.value)}
                                placeholder={`Enter ${formatLabel(key)} in English...`}
                                className="w-full p-2 text-xs sm:text-sm rounded-lg border border-gray-200 bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500"
                              />
                            </div>
                            <div className="border border-gray-200 rounded-xl p-3 bg-emerald-50/30">
                              <span className="text-xs font-semibold text-emerald-800 mb-1.5 block">Khmer (ភាសាខ្មែរ)</span>
                              <textarea
                                rows={3}
                                value={getVal(activeTab, key, 'km')}
                                onChange={(e) => setVal(activeTab, key, 'km', e.target.value)}
                                placeholder={`បញ្ចូល ${formatLabel(key)} ជាភាសាខ្មែរ...`}
                                className="w-full p-2 text-xs sm:text-sm rounded-lg border border-gray-200 bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500"
                              />
                            </div>
                          </div>
                        ) : isBoolean ? (
                          /* Boolean switches */
                          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                            <label className="flex items-center gap-2 p-3 bg-gray-50 rounded-xl border border-gray-200 cursor-pointer">
                              <input
                                type="checkbox"
                                checked={getVal(activeTab, key, 'en') === '1'}
                                onChange={(e) => setVal(activeTab, key, 'en', e.target.checked ? '1' : '0')}
                                className="w-4 h-4 text-emerald-600 rounded"
                              />
                              <span className="text-xs font-semibold text-gray-700">English Enabled</span>
                            </label>
                            <label className="flex items-center gap-2 p-3 bg-emerald-50/50 rounded-xl border border-emerald-200 cursor-pointer">
                              <input
                                type="checkbox"
                                checked={getVal(activeTab, key, 'km') === '1'}
                                onChange={(e) => setVal(activeTab, key, 'km', e.target.checked ? '1' : '0')}
                                className="w-4 h-4 text-emerald-600 rounded"
                              />
                              <span className="text-xs font-semibold text-emerald-800">Khmer Enabled</span>
                            </label>
                          </div>
                        ) : (
                          /* Standard Single Line Input */
                          <div className="grid grid-cols-1 md:grid-cols-2 gap-3 pt-1">
                            <div className="relative">
                              <span className="absolute left-3 top-1/2 -translate-y-1/2 text-[10px] font-bold text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded">
                                EN
                              </span>
                              <input
                                type={item.type === 'number' ? 'number' : item.type === 'email' ? 'email' : item.type === 'url' ? 'url' : 'text'}
                                value={getVal(activeTab, key, 'en')}
                                onChange={(e) => setVal(activeTab, key, 'en', e.target.value)}
                                placeholder="English value..."
                                className="w-full pl-11 pr-3 py-2 text-xs sm:text-sm rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                              />
                            </div>
                            <div className="relative">
                              <span className="absolute left-3 top-1/2 -translate-y-1/2 text-[10px] font-bold text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded">
                                KM
                              </span>
                              <input
                                type={item.type === 'number' ? 'number' : item.type === 'email' ? 'email' : item.type === 'url' ? 'url' : 'text'}
                                value={getVal(activeTab, key, 'km')}
                                onChange={(e) => setVal(activeTab, key, 'km', e.target.value)}
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
            </div>
          )}

          {/* Bottom Floating/Sticky Save Action Bar */}
          {activeTab !== 'file_manager' && activeTab !== 'flaticon' && (
            <div className="bg-white rounded-2xl border border-gray-200/80 p-4 shadow-xs flex items-center justify-between">
              <button
                type="button"
                onClick={() => switchTab('grid')}
                className="px-4 py-2 text-xs sm:text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-xl transition cursor-pointer"
              >
                ← ត្រឡប់ទៅ Grid (Back)
              </button>

              <button
                type="button"
                onClick={() => handleSaveCategory(activeTab)}
                disabled={saving}
                className="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs sm:text-sm font-semibold rounded-xl shadow-xs transition cursor-pointer flex items-center gap-2 disabled:opacity-60"
              >
                {saving ? <Loader2 size={16} className="animate-spin" /> : <Save size={16} />}
                <span>រក្សាទុកការកែប្រែ ({currentCatMeta.title})</span>
              </button>
            </div>
          )}
        </div>
      )}
    </div>
  );
}

import React, { useState, useEffect } from 'react';
import {
  Settings,
  Save,
  Globe,
  Building,
  Phone,
  Image as ImageIcon,
  CheckCircle2,
  AlertCircle,
  Loader2
} from 'lucide-react';
import { adminApi } from '../api/adminClient';
import ImageUpload from '../components/ImageUpload';

export default function SettingsPage() {
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [lang, setLang] = useState('km');
  const [activeTab, setActiveTab] = useState('company'); // company, contact, banners, collections
  const [toast, setToast] = useState(null);

  // Settings map: { km: { key: value }, en: { key: value } }
  const [settingsMap, setSettingsMap] = useState({ km: {}, en: {} });

  const showToast = (message, type = 'success') => {
    setToast({ message, type });
    setTimeout(() => setToast(null), 3000);
  };

  const loadSettings = async () => {
    setLoading(true);
    try {
      const res = await adminApi.getSettings();
      if (res.success && res.settings) {
        setSettingsMap({
          km: res.settings.km || {},
          en: res.settings.en || {},
        });
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

  const getVal = (key, fallback = '') => {
    return settingsMap[lang]?.[key] ?? settingsMap.km?.[key] ?? fallback;
  };

  const setVal = (key, value) => {
    setSettingsMap((prev) => ({
      ...prev,
      [lang]: {
        ...prev[lang],
        [key]: value,
      },
    }));
  };

  const handleSaveAll = async (e) => {
    e.preventDefault();
    setSaving(true);
    try {
      const currentEntries = Object.entries(settingsMap[lang] || {});
      const payload = currentEntries.map(([key, value]) => ({
        key,
        value: value ?? '',
        language: lang,
      }));

      const res = await adminApi.saveSettingsBulk(payload);
      if (res.success) {
        showToast(`បានរក្សាទុកការកំណត់ (${lang.toUpperCase()}) ជោគជ័យ!`);
      } else {
        showToast(res.error || 'បរាជ័យក្នុងការរក្សាទុក', 'error');
      }
    } catch (err) {
      showToast(err.message || 'Error saving settings', 'error');
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return (
      <div className="flex flex-col items-center justify-center min-h-[400px] text-gray-500 gap-3">
        <Loader2 className="animate-spin text-emerald-600" size={36} />
        <p className="text-sm font-medium">កំពុងទាញយកការកំណត់ (Loading Settings)...</p>
      </div>
    );
  }

  const tabs = [
    { id: 'company', label: 'ក្រុមហ៊ុន & ឡូហ្គោ', icon: Building },
    { id: 'contact', label: 'ទំនាក់ទំនង & បណ្តាញសង្គម', icon: Phone },
    { id: 'banners', label: 'ផ្ទាំងផ្សាយពាណិជ្ជកម្ម (Banners)', icon: ImageIcon },
    { id: 'collections', label: 'ចំណងជើងផ្នែកលើទំព័រដើម', icon: Globe },
  ];

  return (
    <div className="space-y-6">
      {/* Toast */}
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

      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-5 rounded-2xl border border-gray-200/80 shadow-xs">
        <div>
          <h2 className="text-xl font-bold text-gray-900">ការកំណត់គេហទំព័រ (Website Settings)</h2>
          <p className="text-xs text-gray-500 mt-0.5">
            កែប្រែព័ត៌មានក្រុមហ៊ុន ទំនាក់ទំនង ឡូហ្គោ និងផ្ទាំងផ្សាយ
          </p>
        </div>

        <div className="flex items-center gap-3">
          {/* Language switcher */}
          <div className="flex bg-gray-100 p-1 rounded-xl border border-gray-200 text-xs font-semibold">
            <button
              onClick={() => setLang('km')}
              className={`px-3 py-1.5 rounded-lg transition ${
                lang === 'km' ? 'bg-white text-emerald-700 shadow-xs' : 'text-gray-600 hover:text-gray-900'
              }`}
            >
              ភាសាខ្មែរ (KM)
            </button>
            <button
              onClick={() => setLang('en')}
              className={`px-3 py-1.5 rounded-lg transition ${
                lang === 'en' ? 'bg-white text-emerald-700 shadow-xs' : 'text-gray-600 hover:text-gray-900'
              }`}
            >
              English (EN)
            </button>
          </div>

          <button
            onClick={handleSaveAll}
            disabled={saving}
            className="inline-flex items-center gap-2 px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs sm:text-sm font-semibold rounded-xl shadow-xs transition cursor-pointer disabled:opacity-60"
          >
            {saving ? <Loader2 size={16} className="animate-spin" /> : <Save size={16} />}
            <span>រក្សាទុកទាំងអស់ ({lang.toUpperCase()})</span>
          </button>
        </div>
      </div>

      {/* Tabs */}
      <div className="flex flex-wrap gap-2 border-b border-gray-200 pb-3">
        {tabs.map((tab) => {
          const Icon = tab.icon;
          const isActive = activeTab === tab.id;
          return (
            <button
              key={tab.id}
              onClick={() => setActiveTab(tab.id)}
              className={`flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs sm:text-sm font-medium transition cursor-pointer ${
                isActive
                  ? 'bg-emerald-600 text-white shadow-xs font-semibold'
                  : 'bg-white text-gray-600 hover:bg-gray-100 border border-gray-200'
              }`}
            >
              <Icon size={16} />
              <span>{tab.label}</span>
            </button>
          );
        })}
      </div>

      {/* Form Content */}
      <form onSubmit={handleSaveAll} className="bg-white rounded-2xl border border-gray-200/80 p-6 shadow-xs space-y-6">
        {/* TAB 1: COMPANY & LOGO */}
        {activeTab === 'company' && (
          <div className="space-y-5">
            <h3 className="text-base font-bold text-gray-900 pb-2 border-b border-gray-100">
              ព័ត៌មានក្រុមហ៊ុន & ឡូហ្គោ (Company & Brand)
            </h3>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
              <div>
                <label className="block text-xs font-semibold text-gray-700 uppercase mb-1.5">
                  ឈ្មោះក្រុមហ៊ុន (Company Name)
                </label>
                <input
                  type="text"
                  value={getVal('company_name')}
                  onChange={(e) => setVal('company_name', e.target.value)}
                  placeholder="KouPrey Coffee & Syrups"
                  className="w-full px-3.5 py-2.5 text-sm rounded-xl border border-gray-300 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-gray-700 uppercase mb-1.5">
                  ពាក្យស្លោក (Company Slogan / Tagline)
                </label>
                <input
                  type="text"
                  value={getVal('company_slogan')}
                  onChange={(e) => setVal('company_slogan', e.target.value)}
                  placeholder="រសជាតិពិត កាហ្វេពិត..."
                  className="w-full px-3.5 py-2.5 text-sm rounded-xl border border-gray-300 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                />
              </div>

              <div className="md:col-span-2">
                <ImageUpload
                  value={getVal('company_logo')}
                  onChange={(path) => setVal('company_logo', path)}
                  type="logo"
                  label="ឡូហ្គោក្រុមហ៊ុន (Header & Footer Logo)"
                />
              </div>

              <div className="md:col-span-2">
                <label className="block text-xs font-semibold text-gray-700 uppercase mb-1.5">
                  ការពិពណ៌នាសង្ខេបខាងក្រោម Footer (Footer Description)
                </label>
                <textarea
                  rows={3}
                  value={getVal('footer_description')}
                  onChange={(e) => setVal('footer_description', e.target.value)}
                  placeholder="ការពិពណ៌នាខ្លីសម្រាប់ footer..."
                  className="w-full px-3.5 py-2 text-sm rounded-xl border border-gray-300 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                />
              </div>
            </div>
          </div>
        )}

        {/* TAB 2: CONTACT & SOCIAL */}
        {activeTab === 'contact' && (
          <div className="space-y-5">
            <h3 className="text-base font-bold text-gray-900 pb-2 border-b border-gray-100">
              ព័ត៌មានទំនាក់ទំនង & បណ្តាញសង្គម (Contact & Social Links)
            </h3>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
              <div>
                <label className="block text-xs font-semibold text-gray-700 uppercase mb-1.5">
                  លេខទូរស័ព្ទ (Contact Phone)
                </label>
                <input
                  type="text"
                  value={getVal('contact_phone')}
                  onChange={(e) => setVal('contact_phone', e.target.value)}
                  placeholder="+855 12 345 678"
                  className="w-full px-3.5 py-2.5 text-sm rounded-xl border border-gray-300 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-gray-700 uppercase mb-1.5">
                  អ៊ីមែល (Contact Email)
                </label>
                <input
                  type="email"
                  value={getVal('contact_email')}
                  onChange={(e) => setVal('contact_email', e.target.value)}
                  placeholder="contact@kouprey.asia"
                  className="w-full px-3.5 py-2.5 text-sm rounded-xl border border-gray-300 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                />
              </div>

              <div className="md:col-span-2">
                <label className="block text-xs font-semibold text-gray-700 uppercase mb-1.5">
                  អាសយដ្ឋាន (Address)
                </label>
                <textarea
                  rows={2}
                  value={getVal('contact_address')}
                  onChange={(e) => setVal('contact_address', e.target.value)}
                  placeholder="រាជធានីភ្នំពេញ កម្ពុជា..."
                  className="w-full px-3.5 py-2 text-sm rounded-xl border border-gray-300 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-gray-700 uppercase mb-1.5">
                  Telegram Link
                </label>
                <input
                  type="text"
                  value={getVal('telegram_link')}
                  onChange={(e) => setVal('telegram_link', e.target.value)}
                  placeholder="https://t.me/kouprey"
                  className="w-full px-3.5 py-2.5 text-sm rounded-xl border border-gray-300 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-gray-700 uppercase mb-1.5">
                  Facebook Link
                </label>
                <input
                  type="text"
                  value={getVal('facebook_link')}
                  onChange={(e) => setVal('facebook_link', e.target.value)}
                  placeholder="https://facebook.com/kouprey"
                  className="w-full px-3.5 py-2.5 text-sm rounded-xl border border-gray-300 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-gray-700 uppercase mb-1.5">
                  Instagram Link
                </label>
                <input
                  type="text"
                  value={getVal('instagram_link')}
                  onChange={(e) => setVal('instagram_link', e.target.value)}
                  placeholder="https://instagram.com/kouprey"
                  className="w-full px-3.5 py-2.5 text-sm rounded-xl border border-gray-300 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-gray-700 uppercase mb-1.5">
                  TikTok Link
                </label>
                <input
                  type="text"
                  value={getVal('tiktok_link')}
                  onChange={(e) => setVal('tiktok_link', e.target.value)}
                  placeholder="https://tiktok.com/@kouprey"
                  className="w-full px-3.5 py-2.5 text-sm rounded-xl border border-gray-300 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                />
              </div>
            </div>
          </div>
        )}

        {/* TAB 3: BANNERS */}
        {activeTab === 'banners' && (
          <div className="space-y-6">
            <h3 className="text-base font-bold text-gray-900 pb-2 border-b border-gray-100">
              ផ្ទាំងផ្សាយពាណិជ្ជកម្មលើទំព័រដើម (Homepage Hero Banners)
            </h3>

            {/* Banner 1 */}
            <div className="p-5 rounded-2xl bg-gray-50 border border-gray-200 space-y-4">
              <h4 className="font-bold text-emerald-800 text-sm">Banner ទី ១ (Banner 1)</h4>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-semibold text-gray-700 uppercase mb-1">
                    ចំណងជើង (Title)
                  </label>
                  <input
                    type="text"
                    value={getVal('banner_1_title')}
                    onChange={(e) => setVal('banner_1_title', e.target.value)}
                    className="w-full px-3.5 py-2 text-sm rounded-xl border border-gray-300 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                  />
                </div>
                <div>
                  <label className="block text-xs font-semibold text-gray-700 uppercase mb-1">
                    ការពិពណ៌នា (Description)
                  </label>
                  <input
                    type="text"
                    value={getVal('banner_1_desc')}
                    onChange={(e) => setVal('banner_1_desc', e.target.value)}
                    className="w-full px-3.5 py-2 text-sm rounded-xl border border-gray-300 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                  />
                </div>
                <div className="md:col-span-2">
                  <ImageUpload
                    value={getVal('banner_1_image')}
                    onChange={(path) => setVal('banner_1_image', path)}
                    type="banner"
                    label="រូបភាព Banner 1"
                  />
                </div>
              </div>
            </div>

            {/* Banner 2 */}
            <div className="p-5 rounded-2xl bg-gray-50 border border-gray-200 space-y-4">
              <h4 className="font-bold text-emerald-800 text-sm">Banner ទី ២ (Banner 2)</h4>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-semibold text-gray-700 uppercase mb-1">
                    ចំណងជើង (Title)
                  </label>
                  <input
                    type="text"
                    value={getVal('banner_2_title')}
                    onChange={(e) => setVal('banner_2_title', e.target.value)}
                    className="w-full px-3.5 py-2 text-sm rounded-xl border border-gray-300 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                  />
                </div>
                <div>
                  <label className="block text-xs font-semibold text-gray-700 uppercase mb-1">
                    ការពិពណ៌នា (Description)
                  </label>
                  <input
                    type="text"
                    value={getVal('banner_2_desc')}
                    onChange={(e) => setVal('banner_2_desc', e.target.value)}
                    className="w-full px-3.5 py-2 text-sm rounded-xl border border-gray-300 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                  />
                </div>
                <div className="md:col-span-2">
                  <ImageUpload
                    value={getVal('banner_2_image')}
                    onChange={(path) => setVal('banner_2_image', path)}
                    type="banner"
                    label="រូបភាព Banner 2"
                  />
                </div>
              </div>
            </div>
          </div>
        )}

        {/* TAB 4: COLLECTIONS & HEADINGS */}
        {activeTab === 'collections' && (
          <div className="space-y-5">
            <h3 className="text-base font-bold text-gray-900 pb-2 border-b border-gray-100">
              ចំណងជើងកម្រងផលិតផលលើទំព័រដើម (Curated Collections Headings)
            </h3>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
              <div>
                <label className="block text-xs font-semibold text-gray-700 uppercase mb-1.5">
                  ចំណងជើងកម្រងស៊ីរ៉ូ (Syrup Collection Title)
                </label>
                <input
                  type="text"
                  value={getVal('collection_syrup_title')}
                  onChange={(e) => setVal('collection_syrup_title', e.target.value)}
                  placeholder="ស៊ីរ៉ូរសជាតិពិសេស..."
                  className="w-full px-3.5 py-2.5 text-sm rounded-xl border border-gray-300 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-gray-700 uppercase mb-1.5">
                  ចំណងជើងកម្រងម្សៅ (Powder Collection Title)
                </label>
                <input
                  type="text"
                  value={getVal('collection_powder_title')}
                  onChange={(e) => setVal('collection_powder_title', e.target.value)}
                  placeholder="ម្សៅឆុងភេសជ្ជៈគុណភាពខ្ពស់..."
                  className="w-full px-3.5 py-2.5 text-sm rounded-xl border border-gray-300 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-gray-700 uppercase mb-1.5">
                  ចំណងជើងផ្នែកលក្ខណៈពិសេស (Features Section Title)
                </label>
                <input
                  type="text"
                  value={getVal('features_title')}
                  onChange={(e) => setVal('features_title', e.target.value)}
                  placeholder="ហេតុអ្វីត្រូវជ្រើសរើស KouPrey..."
                  className="w-full px-3.5 py-2.5 text-sm rounded-xl border border-gray-300 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-gray-700 uppercase mb-1.5">
                  ចំណងជើងផ្នែកការវាយតម្លៃ (Reviews Section Title)
                </label>
                <input
                  type="text"
                  value={getVal('reviews_title')}
                  onChange={(e) => setVal('reviews_title', e.target.value)}
                  placeholder="មតិពីអតិថិជនរបស់យើង..."
                  className="w-full px-3.5 py-2.5 text-sm rounded-xl border border-gray-300 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                />
              </div>
            </div>
          </div>
        )}

        <div className="pt-4 border-t border-gray-100 flex items-center justify-end gap-3">
          <button
            type="submit"
            disabled={saving}
            className="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs sm:text-sm font-semibold rounded-xl transition shadow-xs flex items-center gap-2 disabled:opacity-60 cursor-pointer"
          >
            {saving ? <Loader2 size={16} className="animate-spin" /> : <Save size={16} />}
            <span>រក្សាទុក ({lang.toUpperCase()})</span>
          </button>
        </div>
      </form>
    </div>
  );
}

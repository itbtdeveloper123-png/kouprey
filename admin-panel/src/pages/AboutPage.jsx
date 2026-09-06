import React, { useState, useEffect } from 'react';
import { Info, Save, Loader2, CheckCircle2, AlertCircle, Image as ImageIcon } from 'lucide-react';
import { adminApi } from '../api/adminClient';
import ImageUpload from '../components/ImageUpload';

export default function AboutPage() {
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [formData, setFormData] = useState({
    id: 0,
    title: '',
    content: '',
    purpose_title: '',
    purpose_content: '',
    mission_title: '',
    mission_content: '',
    image: '',
    person_image: '',
    language: 'km',
  });
  const [toast, setToast] = useState(null);

  const showToast = (message, type = 'success') => {
    setToast({ message, type });
    setTimeout(() => setToast(null), 3000);
  };

  const loadAbout = async () => {
    setLoading(true);
    try {
      const res = await adminApi.getAbout();
      if (res.success && res.about) {
        setFormData({
          id: res.about.id || 0,
          title: res.about.title || '',
          content: res.about.content || '',
          purpose_title: res.about.purpose_title || '',
          purpose_content: res.about.purpose_content || '',
          mission_title: res.about.mission_title || '',
          mission_content: res.about.mission_content || '',
          image: res.about.image || '',
          person_image: res.about.person_image || '',
          language: res.about.language || 'km',
        });
      }
    } catch (err) {
      showToast(err.message || 'Error loading about page', 'error');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadAbout();
  }, []);

  const handleSave = async (e) => {
    e.preventDefault();
    setSaving(true);
    try {
      const res = await adminApi.saveAbout(formData);
      if (res.success) {
        showToast('បានរក្សាទុកព័ត៌មានអំពីយើងជោគជ័យ!');
      } else {
        showToast(res.error || 'បរាជ័យក្នុងការរក្សាទុក', 'error');
      }
    } catch (err) {
      showToast(err.message || 'Error saving about', 'error');
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return (
      <div className="flex flex-col items-center justify-center min-h-[400px] text-gray-500 gap-3">
        <Loader2 className="animate-spin text-emerald-600" size={36} />
        <p className="text-sm font-medium">កំពុងទាញយកទិន្នន័យ (Loading About)...</p>
      </div>
    );
  }

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
          <h2 className="text-xl font-bold text-gray-900">គ្រប់គ្រងទំព័រអំពីយើង (About Us)</h2>
          <p className="text-xs text-gray-500 mt-0.5">
            កែប្រែប្រវត្តិ គោលបំណង បេសកកម្ម និងរូបភាពតំណាង
          </p>
        </div>

        <button
          onClick={handleSave}
          disabled={saving}
          className="inline-flex items-center gap-2 px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs sm:text-sm font-semibold rounded-xl shadow-xs transition cursor-pointer disabled:opacity-60"
        >
          {saving ? <Loader2 size={16} className="animate-spin" /> : <Save size={16} />}
          <span>រក្សាទុក (Save About)</span>
        </button>
      </div>

      <form onSubmit={handleSave} className="space-y-6">
        {/* Main story */}
        <div className="bg-white rounded-2xl border border-gray-200/80 p-6 shadow-xs space-y-4">
          <h3 className="text-base font-bold text-gray-900 pb-2 border-b border-gray-100 flex items-center gap-2">
            <Info size={18} className="text-emerald-600" />
            <span>ព័ត៌មានទូទៅអំពី KouPrey</span>
          </h3>

          <div>
            <label className="block text-xs font-semibold text-gray-700 uppercase mb-1">
              ចំណងជើងធំ (Main Title)
            </label>
            <input
              type="text"
              value={formData.title}
              onChange={(e) => setFormData({ ...formData, title: e.target.value })}
              placeholder="អំពី KouPrey Coffee & Syrups..."
              className="w-full px-3.5 py-2.5 text-sm rounded-xl border border-gray-300 focus:outline-none focus:ring-2 focus:ring-emerald-500"
            />
          </div>

          <div>
            <label className="block text-xs font-semibold text-gray-700 uppercase mb-1">
              ខ្លឹមសាររៀបរាប់ (Main Content)
            </label>
            <textarea
              rows={5}
              value={formData.content}
              onChange={(e) => setFormData({ ...formData, content: e.target.value })}
              placeholder="រៀបរាប់ពីប្រវត្តិ ការកកើត និងដំណើរដើមទង..."
              className="w-full px-3.5 py-2 text-sm rounded-xl border border-gray-300 focus:outline-none focus:ring-2 focus:ring-emerald-500"
            />
          </div>
        </div>

        {/* Purpose & Mission */}
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div className="bg-white rounded-2xl border border-gray-200/80 p-6 shadow-xs space-y-4">
            <h3 className="text-base font-bold text-gray-900 pb-2 border-b border-gray-100">
              គោលបំណង (Our Purpose)
            </h3>
            <div>
              <label className="block text-xs font-semibold text-gray-700 uppercase mb-1">
                ចំណងជើង
              </label>
              <input
                type="text"
                value={formData.purpose_title}
                onChange={(e) => setFormData({ ...formData, purpose_title: e.target.value })}
                placeholder="គោលបំណងរបស់យើង..."
                className="w-full px-3.5 py-2 text-sm rounded-xl border border-gray-300 focus:outline-none focus:ring-2 focus:ring-emerald-500"
              />
            </div>
            <div>
              <label className="block text-xs font-semibold text-gray-700 uppercase mb-1">
                ខ្លឹមសារ
              </label>
              <textarea
                rows={4}
                value={formData.purpose_content}
                onChange={(e) => setFormData({ ...formData, purpose_content: e.target.value })}
                placeholder="ខ្លឹមសារគោលបំណង..."
                className="w-full px-3.5 py-2 text-sm rounded-xl border border-gray-300 focus:outline-none focus:ring-2 focus:ring-emerald-500"
              />
            </div>
          </div>

          <div className="bg-white rounded-2xl border border-gray-200/80 p-6 shadow-xs space-y-4">
            <h3 className="text-base font-bold text-gray-900 pb-2 border-b border-gray-100">
              បេសកកម្ម (Our Mission)
            </h3>
            <div>
              <label className="block text-xs font-semibold text-gray-700 uppercase mb-1">
                ចំណងជើង
              </label>
              <input
                type="text"
                value={formData.mission_title}
                onChange={(e) => setFormData({ ...formData, mission_title: e.target.value })}
                placeholder="បេសកកម្មរបស់យើង..."
                className="w-full px-3.5 py-2 text-sm rounded-xl border border-gray-300 focus:outline-none focus:ring-2 focus:ring-emerald-500"
              />
            </div>
            <div>
              <label className="block text-xs font-semibold text-gray-700 uppercase mb-1">
                ខ្លឹមសារ
              </label>
              <textarea
                rows={4}
                value={formData.mission_content}
                onChange={(e) => setFormData({ ...formData, mission_content: e.target.value })}
                placeholder="ខ្លឹមសារបេសកកម្ម..."
                className="w-full px-3.5 py-2 text-sm rounded-xl border border-gray-300 focus:outline-none focus:ring-2 focus:ring-emerald-500"
              />
            </div>
          </div>
        </div>

        {/* Images */}
        <div className="bg-white rounded-2xl border border-gray-200/80 p-6 shadow-xs space-y-5">
          <h3 className="text-base font-bold text-gray-900 pb-2 border-b border-gray-100 flex items-center gap-2">
            <ImageIcon size={18} className="text-emerald-600" />
            <span>រូបភាពទំព័រអំពីយើង</span>
          </h3>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <ImageUpload
              value={formData.image}
              onChange={(path) => setFormData({ ...formData, image: path })}
              type="product"
              label="រូបភាពធំលើទំព័រ (Main Banner Image)"
            />

            <ImageUpload
              value={formData.person_image}
              onChange={(path) => setFormData({ ...formData, person_image: path })}
              type="product"
              label="រូបភាពស្ថាបនិក ឬ អ្នកជំនាញ (Person / Founder Image)"
            />
          </div>
        </div>
      </form>
    </div>
  );
}

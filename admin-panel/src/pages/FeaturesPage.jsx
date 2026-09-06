import React, { useState, useEffect } from 'react';
import { Sparkles, Plus, Edit2, Trash2, Loader2, X, CheckCircle2, AlertCircle } from 'lucide-react';
import { adminApi } from '../api/adminClient';
import ImageUpload from '../components/ImageUpload';

export default function FeaturesPage() {
  const [features, setFeatures] = useState([]);
  const [loading, setLoading] = useState(true);
  const [lang, setLang] = useState('km');
  const [modalOpen, setModalOpen] = useState(false);
  const [editingFeature, setEditingFeature] = useState(null);
  const [formData, setFormData] = useState({
    id: 0,
    base_feature_id: 0,
    title: '',
    description: '',
    icon: '',
    image: '',
    language: 'km',
  });
  const [saving, setSaving] = useState(false);
  const [toast, setToast] = useState(null);
  const [deleteConfirm, setDeleteConfirm] = useState(null);

  const showToast = (message, type = 'success') => {
    setToast({ message, type });
    setTimeout(() => setToast(null), 3000);
  };

  const loadFeatures = async () => {
    setLoading(true);
    try {
      const res = await adminApi.getFeatures(lang);
      if (res.success) {
        setFeatures(res.features || []);
      }
    } catch (err) {
      showToast(err.message || 'Failed to load features', 'error');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadFeatures();
  }, [lang]);

  const openAddModal = () => {
    setEditingFeature(null);
    setFormData({
      id: 0,
      base_feature_id: 0,
      title: '',
      description: '',
      icon: '',
      image: '',
      language: lang,
    });
    setModalOpen(true);
  };

  const openEditModal = (f) => {
    setEditingFeature(f);
    setFormData({
      id: f.id,
      base_feature_id: f.base_feature_id || 0,
      title: f.title || '',
      description: f.description || '',
      icon: f.icon || '',
      image: f.image || '',
      language: f.language || lang,
    });
    setModalOpen(true);
  };

  const handleSave = async (e) => {
    e.preventDefault();
    if (!formData.title.trim()) {
      showToast('សូមបញ្ចូលចំណងជើង (Title required)', 'error');
      return;
    }
    setSaving(true);
    try {
      const res = await adminApi.saveFeature(formData);
      if (res.success) {
        showToast(editingFeature ? 'កែប្រែលក្ខណៈពិសេសជោគជ័យ!' : 'បន្ថែមលក្ខណៈពិសេសជោគជ័យ!');
        setModalOpen(false);
        loadFeatures();
      } else {
        showToast(res.error || 'Failed to save', 'error');
      }
    } catch (err) {
      showToast(err.message || 'Error saving feature', 'error');
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async (id) => {
    try {
      const res = await adminApi.deleteFeature(id);
      if (res.success) {
        showToast('បានលុបលក្ខណៈពិសេសជោគជ័យ!');
        setDeleteConfirm(null);
        loadFeatures();
      } else {
        showToast(res.error || 'Failed to delete', 'error');
      }
    } catch (err) {
      showToast(err.message || 'Error deleting feature', 'error');
    }
  };

  const formatImageUrl = (url) => {
    if (!url) return '';
    if (url.startsWith('http')) return url;
    return `https://www.kouprey.asia${url.startsWith('/') ? '' : '/'}${url}`;
  };

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
          <h2 className="text-xl font-bold text-gray-900">គ្រប់គ្រងលក្ខណៈពិសេស (Features Highlights)</h2>
          <p className="text-xs text-gray-500 mt-0.5">
            បង្ហាញលើទំព័រដើមគេហទំព័រ: <span className="font-semibold text-emerald-700">{features.length} ធាតុ</span>
          </p>
        </div>

        <div className="flex items-center gap-3">
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
            onClick={openAddModal}
            className="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs sm:text-sm font-semibold rounded-xl shadow-xs transition cursor-pointer"
          >
            <Plus size={16} />
            បន្ថែមលក្ខណៈពិសេស
          </button>
        </div>
      </div>

      {/* Features Grid */}
      {loading ? (
        <div className="p-12 text-center text-gray-500 flex flex-col items-center gap-2">
          <Loader2 className="animate-spin text-emerald-600" size={32} />
          <span className="text-xs font-medium">កំពុងទាញយកទិន្នន័យ...</span>
        </div>
      ) : features.length === 0 ? (
        <div className="bg-white rounded-2xl border border-gray-200/80 p-12 text-center text-gray-400">
          <Sparkles size={40} className="mx-auto mb-2 opacity-40" />
          <p className="text-sm font-medium">មិនទាន់មានលក្ខណៈពិសេសនៅឡើយទេ</p>
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
          {features.map((f) => (
            <div
              key={f.id}
              className="bg-white rounded-2xl border border-gray-200/80 p-5 shadow-xs hover:shadow-md hover:border-emerald-300 transition flex flex-col justify-between"
            >
              <div>
                <div className="flex items-start justify-between gap-3">
                  <div className="w-12 h-12 rounded-xl bg-emerald-50 border border-emerald-100 flex items-center justify-center text-emerald-700 overflow-hidden">
                    {f.image ? (
                      <img src={formatImageUrl(f.image)} alt="" className="w-full h-full object-cover" />
                    ) : (
                      <Sparkles size={22} />
                    )}
                  </div>

                  <div className="flex items-center gap-1">
                    <button
                      onClick={() => openEditModal(f)}
                      className="p-1.5 text-gray-500 hover:text-emerald-700 hover:bg-emerald-50 rounded-lg transition"
                      title="កែប្រែ"
                    >
                      <Edit2 size={15} />
                    </button>
                    <button
                      onClick={() => setDeleteConfirm(f)}
                      className="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition"
                      title="លុប"
                    >
                      <Trash2 size={15} />
                    </button>
                  </div>
                </div>

                <h3 className="font-bold text-gray-900 text-base mt-4">{f.title}</h3>
                <p className="text-xs text-gray-600 mt-1.5 line-clamp-3 leading-relaxed">
                  {f.description || 'គ្មានការពិពណ៌នា'}
                </p>
              </div>

              <div className="mt-4 pt-3 border-t border-gray-100 flex items-center justify-between text-[11px] text-gray-400">
                <span>ភាសា: {f.language?.toUpperCase() || 'KM'}</span>
                <span>ID: {f.id}</span>
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Modal */}
      {modalOpen && (
        <div className="fixed inset-0 z-50 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl animate-fade-in my-8 max-h-[90vh] overflow-y-auto">
            <div className="flex items-center justify-between pb-4 border-b border-gray-100">
              <h3 className="text-base font-bold text-gray-900">
                {editingFeature ? 'កែប្រែលក្ខណៈពិសេស' : 'បន្ថែមលក្ខណៈពិសេសថ្មី'}
              </h3>
              <button
                onClick={() => setModalOpen(false)}
                className="p-1.5 text-gray-400 hover:text-gray-700 rounded-lg hover:bg-gray-100"
              >
                <X size={18} />
              </button>
            </div>

            <form onSubmit={handleSave} className="mt-4 space-y-4">
              <div>
                <label className="block text-xs font-semibold text-gray-700 uppercase mb-1">
                  ចំណងជើង (Title) *
                </label>
                <input
                  type="text"
                  value={formData.title}
                  onChange={(e) => setFormData({ ...formData, title: e.target.value })}
                  placeholder="ឧ. គុណភាពខ្ពស់ជាប់ចិត្ត..."
                  className="w-full px-3.5 py-2 text-sm rounded-xl border border-gray-300 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                  required
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-gray-700 uppercase mb-1">
                  ភាសា (Language)
                </label>
                <select
                  value={formData.language}
                  onChange={(e) => setFormData({ ...formData, language: e.target.value })}
                  className="w-full px-3.5 py-2 text-sm rounded-xl border border-gray-300 focus:outline-none focus:ring-2 focus:ring-emerald-500 bg-white"
                >
                  <option value="km">ភាសាខ្មែរ (Khmer)</option>
                  <option value="en">English</option>
                </select>
              </div>

              <div>
                <label className="block text-xs font-semibold text-gray-700 uppercase mb-1">
                  ការពិពណ៌នា (Description)
                </label>
                <textarea
                  rows={3}
                  value={formData.description}
                  onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                  placeholder="ព័ត៌មានលម្អិត..."
                  className="w-full px-3.5 py-2 text-sm rounded-xl border border-gray-300 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                />
              </div>

              <div>
                <ImageUpload
                  value={formData.image}
                  onChange={(path) => setFormData({ ...formData, image: path })}
                  type="product"
                  label="រូបភាព ឬ Icon"
                />
              </div>

              <div className="pt-4 border-t border-gray-100 flex items-center justify-end gap-3">
                <button
                  type="button"
                  onClick={() => setModalOpen(false)}
                  className="px-4 py-2 text-xs sm:text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-xl"
                >
                  បោះបង់
                </button>
                <button
                  type="submit"
                  disabled={saving}
                  className="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs sm:text-sm font-semibold rounded-xl transition flex items-center gap-2 disabled:opacity-60"
                >
                  {saving && <Loader2 size={16} className="animate-spin" />}
                  <span>{editingFeature ? 'រក្សាទុក' : 'បង្កើត'}</span>
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Delete confirm */}
      {deleteConfirm && (
        <div className="fixed inset-0 z-50 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-2xl max-w-sm w-full p-6 shadow-2xl animate-fade-in text-center">
            <div className="w-12 h-12 rounded-full bg-red-100 text-red-600 flex items-center justify-center mx-auto mb-4">
              <Trash2 size={24} />
            </div>
            <h3 className="text-base font-bold text-gray-900">តើអ្នកពិតជាចង់លុបធាតុនេះ?</h3>
            <p className="text-xs text-gray-500 mt-2">
              «<span className="font-semibold text-gray-800">{deleteConfirm.title}</span>» នឹងត្រូវលុបចេញពីប្រព័ន្ធ។
            </p>
            <div className="flex items-center justify-center gap-3 mt-6">
              <button
                onClick={() => setDeleteConfirm(null)}
                className="px-4 py-2 text-xs sm:text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-xl"
              >
                បោះបង់
              </button>
              <button
                onClick={() => handleDelete(deleteConfirm.id)}
                className="px-5 py-2 bg-red-600 hover:bg-red-700 text-white text-xs sm:text-sm font-semibold rounded-xl"
              >
                យល់ព្រមលុប
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

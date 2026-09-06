import React, { useState, useEffect } from 'react';
import { Tags, Plus, Edit2, Trash2, Loader2, X, CheckCircle2, AlertCircle } from 'lucide-react';
import { adminApi } from '../api/adminClient';

export default function CategoriesPage() {
  const [categories, setCategories] = useState([]);
  const [loading, setLoading] = useState(true);
  const [lang, setLang] = useState('km');
  const [modalOpen, setModalOpen] = useState(false);
  const [editingCategory, setEditingCategory] = useState(null);
  const [formData, setFormData] = useState({ id: 0, name: '', description: '', language: 'km' });
  const [saving, setSaving] = useState(false);
  const [toast, setToast] = useState(null);
  const [deleteConfirm, setDeleteConfirm] = useState(null);

  const showToast = (message, type = 'success') => {
    setToast({ message, type });
    setTimeout(() => setToast(null), 3000);
  };

  const loadCategories = async () => {
    setLoading(true);
    try {
      const res = await adminApi.getCategories(lang);
      if (res.success) {
        setCategories(res.categories || []);
      }
    } catch (err) {
      showToast(err.message || 'Error loading categories', 'error');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadCategories();
  }, [lang]);

  const openAddModal = () => {
    setEditingCategory(null);
    setFormData({ id: 0, name: '', description: '', language: lang });
    setModalOpen(true);
  };

  const openEditModal = (cat) => {
    setEditingCategory(cat);
    setFormData({
      id: cat.id,
      name: cat.name || '',
      description: cat.description || '',
      language: cat.language || lang,
    });
    setModalOpen(true);
  };

  const handleSave = async (e) => {
    e.preventDefault();
    if (!formData.name.trim()) {
      showToast('សូមបញ្ចូលឈ្មោះប្រភេទ (Name required)', 'error');
      return;
    }
    setSaving(true);
    try {
      const res = await adminApi.saveCategory(formData);
      if (res.success) {
        showToast(editingCategory ? 'កែប្រែប្រភេទជោគជ័យ!' : 'បន្ថែមប្រភេទថ្មីជោគជ័យ!');
        setModalOpen(false);
        loadCategories();
      } else {
        showToast(res.error || 'បរាជ័យក្នុងការរក្សាទុក', 'error');
      }
    } catch (err) {
      showToast(err.message || 'Error saving category', 'error');
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async (id) => {
    try {
      const res = await adminApi.deleteCategory(id);
      if (res.success) {
        showToast('បានលុបប្រភេទជោគជ័យ!');
        setDeleteConfirm(null);
        loadCategories();
      } else {
        showToast(res.error || 'លុបបរាជ័យ', 'error');
      }
    } catch (err) {
      showToast(err.message || 'Error deleting category', 'error');
    }
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
          <h2 className="text-xl font-bold text-gray-900">គ្រប់គ្រងប្រភេទផលិតផល (Categories)</h2>
          <p className="text-xs text-gray-500 mt-0.5">
            ចំនួនសរុប: <span className="font-semibold text-emerald-700">{categories.length}</span>
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
            បន្ថែមប្រភេទថ្មី
          </button>
        </div>
      </div>

      {/* Categories Table */}
      <div className="bg-white rounded-2xl border border-gray-200/80 overflow-hidden shadow-xs">
        {loading ? (
          <div className="p-12 text-center text-gray-500 flex flex-col items-center gap-2">
            <Loader2 className="animate-spin text-emerald-600" size={32} />
            <span className="text-xs font-medium">កំពុងទាញយកទិន្នន័យ...</span>
          </div>
        ) : categories.length === 0 ? (
          <div className="p-12 text-center text-gray-400">
            <Tags size={40} className="mx-auto mb-2 opacity-40" />
            <p className="text-sm font-medium">មិនទាន់មានប្រភេទនៅឡើយទេ</p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left border-collapse text-xs sm:text-sm">
              <thead>
                <tr className="bg-gray-50/80 text-gray-500 border-b border-gray-200 uppercase tracking-wider text-[11px] font-semibold">
                  <th className="py-3.5 px-4 w-16">ID</th>
                  <th className="py-3.5 px-4">ឈ្មោះប្រភេទ (Category Name)</th>
                  <th className="py-3.5 px-4">ការពិពណ៌នា</th>
                  <th className="py-3.5 px-4">ភាសា</th>
                  <th className="py-3.5 px-4 text-right">សកម្មភាព</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100">
                {categories.map((c) => (
                  <tr key={c.id} className="hover:bg-emerald-50/20 transition">
                    <td className="py-3.5 px-4 text-gray-400 font-mono text-xs">{c.id}</td>
                    <td className="py-3.5 px-4 font-bold text-gray-900">{c.name}</td>
                    <td className="py-3.5 px-4 text-gray-600 max-w-xs truncate">
                      {c.description || '-'}
                    </td>
                    <td className="py-3.5 px-4">
                      <span className="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-gray-100 text-gray-700">
                        {c.language?.toUpperCase() || 'KM'}
                      </span>
                    </td>
                    <td className="py-3.5 px-4 text-right">
                      <div className="inline-flex items-center gap-1">
                        <button
                          onClick={() => openEditModal(c)}
                          className="p-1.5 text-gray-600 hover:text-emerald-700 rounded-lg hover:bg-emerald-50 transition cursor-pointer"
                          title="កែសម្រួល"
                        >
                          <Edit2 size={16} />
                        </button>
                        <button
                          onClick={() => setDeleteConfirm(c)}
                          className="p-1.5 text-gray-400 hover:text-red-600 rounded-lg hover:bg-red-50 transition cursor-pointer"
                          title="លុប"
                        >
                          <Trash2 size={16} />
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Modal Add / Edit */}
      {modalOpen && (
        <div className="fixed inset-0 z-50 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl animate-fade-in">
            <div className="flex items-center justify-between pb-4 border-b border-gray-100">
              <h3 className="text-base font-bold text-gray-900">
                {editingCategory ? 'កែប្រែប្រភេទ (Edit Category)' : 'បន្ថែមប្រភេទថ្មី (Add Category)'}
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
                  ឈ្មោះប្រភេទ (Name) *
                </label>
                <input
                  type="text"
                  value={formData.name}
                  onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                  placeholder="ឧ. ស៊ីរ៉ូរសជាតិ (Syrup)..."
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
                  placeholder="ព័ត៌មានបន្ថែមអំពីប្រភេទ..."
                  className="w-full px-3.5 py-2 text-sm rounded-xl border border-gray-300 focus:outline-none focus:ring-2 focus:ring-emerald-500"
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
                  <span>{editingCategory ? 'រក្សាទុក' : 'បង្កើត'}</span>
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Delete confirmation */}
      {deleteConfirm && (
        <div className="fixed inset-0 z-50 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-2xl max-w-sm w-full p-6 shadow-2xl animate-fade-in text-center">
            <div className="w-12 h-12 rounded-full bg-red-100 text-red-600 flex items-center justify-center mx-auto mb-4">
              <Trash2 size={24} />
            </div>
            <h3 className="text-base font-bold text-gray-900">តើអ្នកពិតជាចង់លុបប្រភេទនេះ?</h3>
            <p className="text-xs text-gray-500 mt-2">
              ប្រភេទ «<span className="font-semibold text-gray-800">{deleteConfirm.name}</span>» នឹងត្រូវលុបចេញពីប្រព័ន្ធ។
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

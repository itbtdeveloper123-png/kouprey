import React, { useState, useEffect } from 'react';
import {
  Package,
  Plus,
  Search,
  Filter,
  Edit2,
  Trash2,
  CheckCircle2,
  XCircle,
  Star,
  Loader2,
  X,
  Sparkles,
  Award,
  AlertCircle
} from 'lucide-react';
import { adminApi } from '../api/adminClient';
import ImageUpload from '../components/ImageUpload';

export default function ProductsPage() {
  const [products, setProducts] = useState([]);
  const [categories, setCategories] = useState([]);
  const [loading, setLoading] = useState(true);
  const [lang, setLang] = useState('km');
  const [search, setSearch] = useState('');
  const [selectedCategory, setSelectedCategory] = useState('');
  
  // Drawer / Modal for Add / Edit
  const [modalOpen, setModalOpen] = useState(false);
  const [editingProduct, setEditingProduct] = useState(null);
  const [formData, setFormData] = useState({
    id: 0,
    base_product_id: 0,
    name: '',
    category_id: '',
    price: '',
    original_price: '',
    short_description: '',
    detailed_description: '',
    image: '',
    featured: 0,
    best_seller: 0,
    enabled: 1,
    sort_order: 0,
    language: 'km',
  });
  const [saving, setSaving] = useState(false);
  const [toast, setToast] = useState(null);
  const [deleteConfirm, setDeleteConfirm] = useState(null);

  const showToast = (message, type = 'success') => {
    setToast({ message, type });
    setTimeout(() => setToast(null), 3500);
  };

  const loadData = async () => {
    setLoading(true);
    try {
      const [prodsRes, catsRes] = await Promise.all([
        adminApi.getProducts({ lang, search, category_id: selectedCategory }),
        adminApi.getCategories(lang),
      ]);
      if (prodsRes.success) setProducts(prodsRes.products || []);
      if (catsRes.success) setCategories(catsRes.categories || []);
    } catch (err) {
      showToast(err.message || 'Failed to load products', 'error');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadData();
  }, [lang, selectedCategory]);

  const handleSearch = (e) => {
    e.preventDefault();
    loadData();
  };

  const openAddModal = () => {
    setEditingProduct(null);
    setFormData({
      id: 0,
      base_product_id: 0,
      name: '',
      category_id: categories[0]?.id || '',
      price: '',
      original_price: '',
      short_description: '',
      detailed_description: '',
      image: '',
      featured: 0,
      best_seller: 0,
      enabled: 1,
      sort_order: 0,
      language: lang,
    });
    setModalOpen(true);
  };

  const openEditModal = (product) => {
    setEditingProduct(product);
    setFormData({
      id: product.id,
      base_product_id: product.base_product_id || 0,
      name: product.name || '',
      category_id: product.category_id || '',
      price: product.price || '',
      original_price: product.original_price || '',
      short_description: product.short_description || '',
      detailed_description: product.detailed_description || '',
      image: product.image || '',
      featured: Number(product.featured) || 0,
      best_seller: Number(product.best_seller) || 0,
      enabled: Number(product.enabled) !== 0 ? 1 : 0,
      sort_order: product.sort_order || 0,
      language: product.language || lang,
    });
    setModalOpen(true);
  };

  const handleSave = async (e) => {
    e.preventDefault();
    if (!formData.name.trim()) {
      showToast('សូមបញ្ចូលឈ្មោះផលិតផល (Name required)', 'error');
      return;
    }
    setSaving(true);
    try {
      const res = await adminApi.saveProduct({
        ...formData,
        price: parseFloat(formData.price) || 0,
        original_price: formData.original_price ? parseFloat(formData.original_price) : null,
      });
      if (res.success) {
        showToast(editingProduct ? 'កែប្រែផលិតផលជោគជ័យ!' : 'បានបន្ថែមផលិតផលថ្មីជោគជ័យ!');
        setModalOpen(false);
        loadData();
      } else {
        showToast(res.error || 'បរាជ័យក្នុងការរក្សាទុក', 'error');
      }
    } catch (err) {
      showToast(err.message || 'Error saving product', 'error');
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async (product) => {
    try {
      const res = await adminApi.deleteProduct(product.id, product.base_product_id);
      if (res.success) {
        showToast('បានលុបផលិតផលជោគជ័យ!');
        setDeleteConfirm(null);
        loadData();
      } else {
        showToast(res.error || 'លុបបរាជ័យ', 'error');
      }
    } catch (err) {
      showToast(err.message || 'Error deleting product', 'error');
    }
  };

  const formatImageUrl = (url) => {
    if (!url) return '/placeholder-coffee.png';
    if (url.startsWith('http')) return url;
    return `https://www.kouprey.asia${url.startsWith('/') ? '' : '/'}${url}`;
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

      {/* Header Actions */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-5 rounded-2xl border border-gray-200/80 shadow-xs">
        <div>
          <h2 className="text-xl font-bold text-gray-900">កាតាឡុកផលិតផល (Products Catalog)</h2>
          <p className="text-xs text-gray-500 mt-0.5">
            ចំនួនផលិតផលសរុប: <span className="font-semibold text-emerald-700">{products.length}</span>
          </p>
        </div>

        <div className="flex flex-wrap items-center gap-2.5">
          {/* Language toggle */}
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
            បន្ថែមផលិតផល (Add Product)
          </button>
        </div>
      </div>

      {/* Filter & Search Bar */}
      <div className="bg-white p-4 rounded-2xl border border-gray-200/80 shadow-xs flex flex-col md:flex-row gap-3 items-center justify-between">
        <form onSubmit={handleSearch} className="w-full md:w-96 relative">
          <Search size={17} className="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400" />
          <input
            type="text"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="ស្វែងរកតាមឈ្មោះផលិតផល..."
            className="w-full pl-10 pr-4 py-2 text-xs sm:text-sm rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-500"
          />
        </form>

        <div className="w-full md:w-auto flex items-center gap-3">
          <div className="flex items-center gap-2 text-xs text-gray-500 font-medium">
            <Filter size={15} />
            <span>ប្រភេទ:</span>
          </div>
          <select
            value={selectedCategory}
            onChange={(e) => setSelectedCategory(e.target.value)}
            className="text-xs sm:text-sm border border-gray-200 rounded-xl px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500"
          >
            <option value="">ទាំងអស់ (All Categories)</option>
            {categories.map((c) => (
              <option key={c.id} value={c.id}>
                {c.name}
              </option>
            ))}
          </select>
        </div>
      </div>

      {/* Products Table */}
      <div className="bg-white rounded-2xl border border-gray-200/80 overflow-hidden shadow-xs">
        {loading ? (
          <div className="p-12 text-center text-gray-500 flex flex-col items-center gap-2">
            <Loader2 className="animate-spin text-emerald-600" size={32} />
            <span className="text-xs font-medium">កំពុងទាញយកបញ្ជីផលិតផល...</span>
          </div>
        ) : products.length === 0 ? (
          <div className="p-12 text-center text-gray-400">
            <Package size={40} className="mx-auto mb-2 opacity-40" />
            <p className="text-sm font-medium">មិនមានផលិតផលនៅឡើយទេ</p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left border-collapse text-xs sm:text-sm">
              <thead>
                <tr className="bg-gray-50/80 text-gray-500 border-b border-gray-200 uppercase tracking-wider text-[11px] font-semibold">
                  <th className="py-3.5 px-4">រូបភាព</th>
                  <th className="py-3.5 px-4">ឈ្មោះផលិតផល</th>
                  <th className="py-3.5 px-4">ប្រភេទ</th>
                  <th className="py-3.5 px-4">តម្លៃ</th>
                  <th className="py-3.5 px-4">ស្លាក (Badges)</th>
                  <th className="py-3.5 px-4">ស្ថានភាព</th>
                  <th className="py-3.5 px-4 text-right">សកម្មភាព</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100">
                {products.map((p) => (
                  <tr key={p.id} className="hover:bg-emerald-50/20 transition">
                    <td className="py-3 px-4">
                      <div className="w-12 h-12 rounded-xl bg-gray-50 border border-gray-100 overflow-hidden flex items-center justify-center">
                        <img
                          src={formatImageUrl(p.image)}
                          alt={p.name}
                          className="w-full h-full object-contain p-1"
                          onError={(e) => {
                            e.target.src = 'https://placehold.co/100x100?text=No+Image';
                          }}
                        />
                      </div>
                    </td>

                    <td className="py-3 px-4 font-semibold text-gray-900 max-w-[220px]">
                      <div className="truncate">{p.name}</div>
                      <div className="text-[11px] text-gray-400 font-normal truncate mt-0.5">
                        {p.short_description || 'No description'}
                      </div>
                    </td>

                    <td className="py-3 px-4 text-gray-600">
                      <span className="px-2.5 py-1 bg-gray-100 rounded-lg text-xs font-medium text-gray-700">
                        {p.category_name || 'គ្មានប្រភេទ'}
                      </span>
                    </td>

                    <td className="py-3 px-4 font-bold text-emerald-800">
                      ${Number(p.price).toFixed(2)}
                      {p.original_price && (
                        <span className="ml-1.5 text-[11px] line-through text-gray-400 font-normal">
                          ${Number(p.original_price).toFixed(2)}
                        </span>
                      )}
                    </td>

                    <td className="py-3 px-4">
                      <div className="flex flex-wrap gap-1">
                        {Number(p.featured) === 1 && (
                          <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-100 text-amber-800">
                            <Sparkles size={10} />
                            Featured
                          </span>
                        )}
                        {Number(p.best_seller) === 1 && (
                          <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-rose-100 text-rose-800">
                            <Award size={10} />
                            Best Seller
                          </span>
                        )}
                      </div>
                    </td>

                    <td className="py-3 px-4">
                      {Number(p.enabled) !== 0 ? (
                        <span className="inline-flex items-center gap-1 text-emerald-700 font-medium text-xs">
                          <CheckCircle2 size={14} /> បង្ហាញ
                        </span>
                      ) : (
                        <span className="inline-flex items-center gap-1 text-gray-400 font-medium text-xs">
                          <XCircle size={14} /> លាក់
                        </span>
                      )}
                    </td>

                    <td className="py-3 px-4 text-right">
                      <div className="inline-flex items-center gap-1">
                        <button
                          onClick={() => openEditModal(p)}
                          className="p-1.5 text-gray-600 hover:text-emerald-700 rounded-lg hover:bg-emerald-50 transition cursor-pointer"
                          title="កែសម្រួល"
                        >
                          <Edit2 size={16} />
                        </button>
                        <button
                          onClick={() => setDeleteConfirm(p)}
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

      {/* Add / Edit Modal Drawer */}
      {modalOpen && (
        <div className="fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-2xl max-w-2xl w-full p-6 shadow-2xl relative animate-fade-in my-8 max-h-[90vh] overflow-y-auto">
            <div className="flex items-center justify-between pb-4 border-b border-gray-100">
              <h3 className="text-lg font-bold text-gray-900">
                {editingProduct ? 'កែប្រែផលិតផល (Edit Product)' : 'បន្ថែមផលិតផលថ្មី (Add Product)'}
              </h3>
              <button
                onClick={() => setModalOpen(false)}
                className="p-1.5 text-gray-400 hover:text-gray-700 rounded-lg hover:bg-gray-100"
              >
                <X size={18} />
              </button>
            </div>

            <form onSubmit={handleSave} className="mt-5 space-y-4">
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div className="sm:col-span-2">
                  <label className="block text-xs font-semibold text-gray-700 uppercase mb-1">
                    ឈ្មោះផលិតផល (Product Name) *
                  </label>
                  <input
                    type="text"
                    value={formData.name}
                    onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                    placeholder="ឈ្មោះផលិតផល..."
                    className="w-full px-3.5 py-2 text-sm rounded-xl border border-gray-300 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                    required
                  />
                </div>

                <div>
                  <label className="block text-xs font-semibold text-gray-700 uppercase mb-1">
                    ប្រភេទ (Category)
                  </label>
                  <select
                    value={formData.category_id}
                    onChange={(e) => setFormData({ ...formData, category_id: e.target.value })}
                    className="w-full px-3.5 py-2 text-sm rounded-xl border border-gray-300 focus:outline-none focus:ring-2 focus:ring-emerald-500 bg-white"
                  >
                    <option value="">ជ្រើសរើសប្រភេទ...</option>
                    {categories.map((c) => (
                      <option key={c.id} value={c.id}>
                        {c.name}
                      </option>
                    ))}
                  </select>
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
                    តម្លៃលក់ (Price $) *
                  </label>
                  <input
                    type="number"
                    step="0.01"
                    value={formData.price}
                    onChange={(e) => setFormData({ ...formData, price: e.target.value })}
                    placeholder="0.00"
                    className="w-full px-3.5 py-2 text-sm rounded-xl border border-gray-300 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                    required
                  />
                </div>

                <div>
                  <label className="block text-xs font-semibold text-gray-700 uppercase mb-1">
                    តម្លៃដើម (Original Price $)
                  </label>
                  <input
                    type="number"
                    step="0.01"
                    value={formData.original_price}
                    onChange={(e) => setFormData({ ...formData, original_price: e.target.value })}
                    placeholder="0.00"
                    className="w-full px-3.5 py-2 text-sm rounded-xl border border-gray-300 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                  />
                </div>

                <div className="sm:col-span-2">
                  <label className="block text-xs font-semibold text-gray-700 uppercase mb-1">
                    ការពិពណ៌នាសង្ខេប (Short Description)
                  </label>
                  <textarea
                    rows={2}
                    value={formData.short_description}
                    onChange={(e) => setFormData({ ...formData, short_description: e.target.value })}
                    placeholder="ព័ត៌មានសង្ខេបអំពីផលិតផល..."
                    className="w-full px-3.5 py-2 text-sm rounded-xl border border-gray-300 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                  />
                </div>

                <div className="sm:col-span-2">
                  <label className="block text-xs font-semibold text-gray-700 uppercase mb-1">
                    ការពិពណ៌នាលម្អិត (Detailed Description)
                  </label>
                  <textarea
                    rows={4}
                    value={formData.detailed_description}
                    onChange={(e) => setFormData({ ...formData, detailed_description: e.target.value })}
                    placeholder="ព័ត៌មានលម្អិត បច្ចេកទេស របៀបប្រើប្រាស់..."
                    className="w-full px-3.5 py-2 text-sm rounded-xl border border-gray-300 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                  />
                </div>

                <div className="sm:col-span-2">
                  <ImageUpload
                    value={formData.image}
                    onChange={(path) => setFormData({ ...formData, image: path })}
                    type="product"
                    label="រូបភាពផលិតផល (Product Image)"
                  />
                </div>

                <div>
                  <label className="block text-xs font-semibold text-gray-700 uppercase mb-1">
                    លំដាប់តម្រៀប (Sort Order)
                  </label>
                  <input
                    type="number"
                    value={formData.sort_order}
                    onChange={(e) => setFormData({ ...formData, sort_order: parseInt(e.target.value) || 0 })}
                    className="w-full px-3.5 py-2 text-sm rounded-xl border border-gray-300 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                  />
                </div>

                <div className="sm:col-span-2 flex flex-wrap gap-5 pt-2">
                  <label className="flex items-center gap-2 cursor-pointer text-xs sm:text-sm font-medium text-gray-700">
                    <input
                      type="checkbox"
                      checked={formData.featured === 1}
                      onChange={(e) => setFormData({ ...formData, featured: e.target.checked ? 1 : 0 })}
                      className="w-4 h-4 text-emerald-600 rounded"
                    />
                    <span>ដាក់ជា Featured (លេចធ្លោ)</span>
                  </label>

                  <label className="flex items-center gap-2 cursor-pointer text-xs sm:text-sm font-medium text-gray-700">
                    <input
                      type="checkbox"
                      checked={formData.best_seller === 1}
                      onChange={(e) => setFormData({ ...formData, best_seller: e.target.checked ? 1 : 0 })}
                      className="w-4 h-4 text-emerald-600 rounded"
                    />
                    <span>ដាក់ជា Best Seller (លក់ដាច់)</span>
                  </label>

                  <label className="flex items-center gap-2 cursor-pointer text-xs sm:text-sm font-medium text-gray-700">
                    <input
                      type="checkbox"
                      checked={formData.enabled === 1}
                      onChange={(e) => setFormData({ ...formData, enabled: e.target.checked ? 1 : 0 })}
                      className="w-4 h-4 text-emerald-600 rounded"
                    />
                    <span>បង្ហាញលើគេហទំព័រ (Enabled)</span>
                  </label>
                </div>
              </div>

              <div className="pt-5 border-t border-gray-100 flex items-center justify-end gap-3">
                <button
                  type="button"
                  onClick={() => setModalOpen(false)}
                  className="px-4 py-2 text-xs sm:text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-xl transition"
                >
                  បោះបង់
                </button>
                <button
                  type="submit"
                  disabled={saving}
                  className="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs sm:text-sm font-semibold rounded-xl transition flex items-center gap-2 disabled:opacity-60"
                >
                  {saving && <Loader2 size={16} className="animate-spin" />}
                  <span>{editingProduct ? 'រក្សាទុកការកែប្រែ' : 'បង្កើតផលិតផល'}</span>
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Delete Confirmation Modal */}
      {deleteConfirm && (
        <div className="fixed inset-0 z-50 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl animate-fade-in text-center">
            <div className="w-12 h-12 rounded-full bg-red-100 text-red-600 flex items-center justify-center mx-auto mb-4">
              <Trash2 size={24} />
            </div>
            <h3 className="text-base font-bold text-gray-900">តើអ្នកពិតជាចង់លុបផលិតផលនេះមែនទេ?</h3>
            <p className="text-xs sm:text-sm text-gray-500 mt-2">
              ផលិតផល «<span className="font-semibold text-gray-800">{deleteConfirm.name}</span>» នឹងត្រូវលុបទាំងពីរភាសា (ខ្មែរ និង អង់គ្លេស)។ សកម្មភាពនេះមិនអាចត្រឡប់វិញបានទេ។
            </p>
            <div className="flex items-center justify-center gap-3 mt-6">
              <button
                onClick={() => setDeleteConfirm(null)}
                className="px-4 py-2 text-xs sm:text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-xl transition cursor-pointer"
              >
                បោះបង់
              </button>
              <button
                onClick={() => handleDelete(deleteConfirm)}
                className="px-5 py-2 bg-red-600 hover:bg-red-700 text-white text-xs sm:text-sm font-semibold rounded-xl transition shadow-xs cursor-pointer"
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

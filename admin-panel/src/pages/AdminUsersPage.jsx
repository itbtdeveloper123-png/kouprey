import React, { useState, useEffect } from 'react';
import { Users, Plus, Edit2, Trash2, Loader2, X, CheckCircle2, AlertCircle, ShieldCheck, Key } from 'lucide-react';
import { adminApi } from '../api/adminClient';
import { useAuth } from '../context/AuthContext';

export default function AdminUsersPage() {
  const [admins, setAdmins] = useState([]);
  const [loading, setLoading] = useState(true);
  const [modalOpen, setModalOpen] = useState(false);
  const [editingAdmin, setEditingAdmin] = useState(null);
  const [formData, setFormData] = useState({
    id: 0,
    username: '',
    full_name: '',
    email: '',
    password: '',
    status: 'active',
  });
  const [saving, setSaving] = useState(false);
  const [toast, setToast] = useState(null);
  const [deleteConfirm, setDeleteConfirm] = useState(null);
  const { admin: currentAdmin } = useAuth();

  const showToast = (message, type = 'success') => {
    setToast({ message, type });
    setTimeout(() => setToast(null), 3000);
  };

  const loadAdmins = async () => {
    setLoading(true);
    try {
      const res = await adminApi.getAdminUsers();
      if (res.success) {
        setAdmins(res.admins || []);
      }
    } catch (err) {
      showToast(err.message || 'Failed to load admins', 'error');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadAdmins();
  }, []);

  const openAddModal = () => {
    setEditingAdmin(null);
    setFormData({
      id: 0,
      username: '',
      full_name: '',
      email: '',
      password: '',
      status: 'active',
    });
    setModalOpen(true);
  };

  const openEditModal = (u) => {
    setEditingAdmin(u);
    setFormData({
      id: u.id,
      username: u.username || '',
      full_name: u.full_name || '',
      email: u.email || '',
      password: '', // Blank unless changing
      status: u.status || 'active',
    });
    setModalOpen(true);
  };

  const handleSave = async (e) => {
    e.preventDefault();
    if (!formData.username.trim() || !formData.full_name.trim()) {
      showToast('សូមបញ្ចូលឈ្មោះគណនី និងឈ្មោះពេញ (Username & Full Name required)', 'error');
      return;
    }
    if (!editingAdmin && !formData.password) {
      showToast('សូមបញ្ចូលលេខសំងាត់ (Password required for new user)', 'error');
      return;
    }
    setSaving(true);
    try {
      const res = await adminApi.saveAdminUser(formData);
      if (res.success) {
        showToast(editingAdmin ? 'កែប្រែគណនីជោគជ័យ!' : 'បង្កើតអ្នកគ្រប់គ្រងថ្មីជោគជ័យ!');
        setModalOpen(false);
        loadAdmins();
      } else {
        showToast(res.error || 'បរាជ័យក្នុងការរក្សាទុក', 'error');
      }
    } catch (err) {
      showToast(err.message || 'Error saving admin user', 'error');
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async (id) => {
    try {
      const res = await adminApi.deleteAdminUser(id);
      if (res.success) {
        showToast('បានលុបគណនីជោគជ័យ!');
        setDeleteConfirm(null);
        loadAdmins();
      } else {
        showToast(res.error || 'លុបបរាជ័យ', 'error');
      }
    } catch (err) {
      showToast(err.message || 'Error deleting admin user', 'error');
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
          <h2 className="text-xl font-bold text-gray-900">ក្រុមការងារគ្រប់គ្រង (Admin Team)</h2>
          <p className="text-xs text-gray-500 mt-0.5">
            ចំនួនគណនីសរុប: <span className="font-semibold text-emerald-700">{admins.length} នាក់</span>
          </p>
        </div>

        <button
          onClick={openAddModal}
          className="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs sm:text-sm font-semibold rounded-xl shadow-xs transition cursor-pointer"
        >
          <Plus size={16} />
          បន្ថែម Admin ថ្មី
        </button>
      </div>

      {/* Admins Table */}
      <div className="bg-white rounded-2xl border border-gray-200/80 overflow-hidden shadow-xs">
        {loading ? (
          <div className="p-12 text-center text-gray-500 flex flex-col items-center gap-2">
            <Loader2 className="animate-spin text-emerald-600" size={32} />
            <span className="text-xs font-medium">កំពុងទាញយកបញ្ជី...</span>
          </div>
        ) : admins.length === 0 ? (
          <div className="p-12 text-center text-gray-400">
            <Users size={40} className="mx-auto mb-2 opacity-40" />
            <p className="text-sm font-medium">មិនមានគណនីនៅឡើយទេ</p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left border-collapse text-xs sm:text-sm">
              <thead>
                <tr className="bg-gray-50/80 text-gray-500 border-b border-gray-200 uppercase tracking-wider text-[11px] font-semibold">
                  <th className="py-3.5 px-4">ឈ្មោះពេញ</th>
                  <th className="py-3.5 px-4">គណនី (Username)</th>
                  <th className="py-3.5 px-4">អ៊ីមែល</th>
                  <th className="py-3.5 px-4">ស្ថានភាព</th>
                  <th className="py-3.5 px-4">ចូលចុងក្រោយ</th>
                  <th className="py-3.5 px-4 text-right">សកម្មភាព</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100">
                {admins.map((u) => {
                  const isCurrent = Number(u.id) === Number(currentAdmin?.id);
                  return (
                    <tr key={u.id} className="hover:bg-emerald-50/20 transition">
                      <td className="py-3.5 px-4 font-bold text-gray-900 flex items-center gap-2.5">
                        <div className="w-8 h-8 rounded-full bg-emerald-100 text-emerald-800 font-bold flex items-center justify-center text-xs">
                          {u.full_name ? u.full_name.charAt(0).toUpperCase() : 'A'}
                        </div>
                        <div>
                          <span>{u.full_name}</span>
                          {isCurrent && (
                            <span className="ml-2 text-[10px] font-semibold bg-emerald-100 text-emerald-800 px-1.5 py-0.5 rounded">
                              គណនីអ្នក
                            </span>
                          )}
                        </div>
                      </td>

                      <td className="py-3.5 px-4 font-mono text-xs text-gray-600">
                        @{u.username}
                      </td>

                      <td className="py-3.5 px-4 text-gray-600">{u.email || '-'}</td>

                      <td className="py-3.5 px-4">
                        <span
                          className={`px-2.5 py-1 rounded-full text-[11px] font-semibold ${
                            u.status === 'active'
                              ? 'bg-emerald-100 text-emerald-800'
                              : 'bg-gray-100 text-gray-600'
                          }`}
                        >
                          {u.status === 'active' ? 'Active' : 'Inactive'}
                        </span>
                      </td>

                      <td className="py-3.5 px-4 text-xs text-gray-400">
                        {u.last_login ? new Date(u.last_login).toLocaleString() : 'មិនធ្លាប់'}
                      </td>

                      <td className="py-3.5 px-4 text-right">
                        <div className="inline-flex items-center gap-1">
                          <button
                            onClick={() => openEditModal(u)}
                            className="p-1.5 text-gray-600 hover:text-emerald-700 rounded-lg hover:bg-emerald-50 transition cursor-pointer"
                            title="កែប្រែ"
                          >
                            <Edit2 size={16} />
                          </button>
                          {!isCurrent && (
                            <button
                              onClick={() => setDeleteConfirm(u)}
                              className="p-1.5 text-gray-400 hover:text-red-600 rounded-lg hover:bg-red-50 transition cursor-pointer"
                              title="លុប"
                            >
                              <Trash2 size={16} />
                            </button>
                          )}
                        </div>
                      </td>
                    </tr>
                  );
                })}
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
                {editingAdmin ? 'កែប្រែគណនី (Edit Admin)' : 'បន្ថែម Admin ថ្មី (New Admin)'}
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
                  ឈ្មោះគណនី (Username) *
                </label>
                <input
                  type="text"
                  value={formData.username}
                  onChange={(e) => setFormData({ ...formData, username: e.target.value })}
                  placeholder="admin_name"
                  className="w-full px-3.5 py-2 text-sm rounded-xl border border-gray-300 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                  required
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-gray-700 uppercase mb-1">
                  ឈ្មោះពេញ (Full Name) *
                </label>
                <input
                  type="text"
                  value={formData.full_name}
                  onChange={(e) => setFormData({ ...formData, full_name: e.target.value })}
                  placeholder="John Doe"
                  className="w-full px-3.5 py-2 text-sm rounded-xl border border-gray-300 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                  required
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-gray-700 uppercase mb-1">
                  អ៊ីមែល (Email)
                </label>
                <input
                  type="email"
                  value={formData.email}
                  onChange={(e) => setFormData({ ...formData, email: e.target.value })}
                  placeholder="admin@kouprey.asia"
                  className="w-full px-3.5 py-2 text-sm rounded-xl border border-gray-300 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-gray-700 uppercase mb-1">
                  លេខសំងាត់ (Password) {editingAdmin ? '(ទុកទទេបើមិនចង់ប្តូរ)' : '*'}
                </label>
                <input
                  type="password"
                  value={formData.password}
                  onChange={(e) => setFormData({ ...formData, password: e.target.value })}
                  placeholder={editingAdmin ? '•••••••• (មិនផ្លាស់ប្តូរ)' : '••••••••'}
                  className="w-full px-3.5 py-2 text-sm rounded-xl border border-gray-300 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                  required={!editingAdmin}
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-gray-700 uppercase mb-1">
                  ស្ថានភាព (Status)
                </label>
                <select
                  value={formData.status}
                  onChange={(e) => setFormData({ ...formData, status: e.target.value })}
                  className="w-full px-3.5 py-2 text-sm rounded-xl border border-gray-300 focus:outline-none focus:ring-2 focus:ring-emerald-500 bg-white"
                >
                  <option value="active">សកម្ម (Active)</option>
                  <option value="inactive">អសកម្ម (Inactive)</option>
                </select>
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
                  <span>{editingAdmin ? 'រក្សាទុក' : 'បង្កើត Admin'}</span>
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
            <h3 className="text-base font-bold text-gray-900">តើអ្នកពិតជាចង់លុបគណនីនេះ?</h3>
            <p className="text-xs text-gray-500 mt-2">
              គណនី «<span className="font-semibold text-gray-800">{deleteConfirm.full_name}</span>» (@{deleteConfirm.username}) នឹងត្រូវលុបជាអចិន្ត្រៃយ៍។
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

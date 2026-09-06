import React, { useState, useEffect } from 'react';
import {
  Star,
  Check,
  X,
  Trash2,
  Loader2,
  AlertCircle,
  CheckCircle2,
  Clock,
  ShieldCheck,
  EyeOff
} from 'lucide-react';
import { adminApi } from '../api/adminClient';

export default function ReviewsPage() {
  const [reviews, setReviews] = useState([]);
  const [loading, setLoading] = useState(true);
  const [filter, setFilter] = useState('all'); // all, pending, approved, rejected
  const [toast, setToast] = useState(null);
  const [deleteConfirm, setDeleteConfirm] = useState(null);

  const showToast = (message, type = 'success') => {
    setToast({ message, type });
    setTimeout(() => setToast(null), 3000);
  };

  const loadReviews = async () => {
    setLoading(true);
    try {
      const res = await adminApi.getReviews();
      if (res.success) {
        setReviews(res.reviews || []);
      }
    } catch (err) {
      showToast(err.message || 'Failed to load reviews', 'error');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadReviews();
  }, []);

  const handleUpdateStatus = async (id, status) => {
    try {
      const res = await adminApi.updateReview(id, status);
      if (res.success) {
        showToast(
          status === 'approved'
            ? 'បានអនុម័តការវាយតម្លៃជោគជ័យ!'
            : 'បានប្តូរស្ថានភាពការវាយតម្លៃ!'
        );
        loadReviews();
      } else {
        showToast(res.error || 'Failed to update review', 'error');
      }
    } catch (err) {
      showToast(err.message || 'Error updating status', 'error');
    }
  };

  const handleDelete = async (id) => {
    try {
      const res = await adminApi.deleteReview(id);
      if (res.success) {
        showToast('បានលុបការវាយតម្លៃជោគជ័យ!');
        setDeleteConfirm(null);
        loadReviews();
      } else {
        showToast(res.error || 'Failed to delete review', 'error');
      }
    } catch (err) {
      showToast(err.message || 'Error deleting review', 'error');
    }
  };

  const filteredReviews = reviews.filter((r) => {
    const s = (r.status || 'pending').toLowerCase();
    if (filter === 'pending') return s === 'pending' || s === '' || !r.status;
    if (filter === 'approved') return s === 'approved';
    if (filter === 'rejected') return s === 'rejected';
    return true;
  });

  const pendingCount = reviews.filter(
    (r) => !r.status || r.status === 'pending' || r.status === ''
  ).length;

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
          <h2 className="text-xl font-bold text-gray-900">គ្រប់គ្រងការវាយតម្លៃ (Customer Reviews)</h2>
          <p className="text-xs text-gray-500 mt-0.5">
            ការវាយតម្លៃសរុប: <span className="font-semibold text-emerald-700">{reviews.length}</span>
            {pendingCount > 0 && (
              <span className="ml-2 px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 font-semibold text-[11px]">
                {pendingCount} រង់ចាំការអនុម័ត
              </span>
            )}
          </p>
        </div>

        {/* Filter buttons */}
        <div className="flex bg-gray-100 p-1 rounded-xl border border-gray-200 text-xs font-semibold">
          <button
            onClick={() => setFilter('all')}
            className={`px-3 py-1.5 rounded-lg transition ${
              filter === 'all' ? 'bg-white text-emerald-700 shadow-xs' : 'text-gray-600 hover:text-gray-900'
            }`}
          >
            ទាំងអស់ ({reviews.length})
          </button>
          <button
            onClick={() => setFilter('pending')}
            className={`px-3 py-1.5 rounded-lg transition ${
              filter === 'pending' ? 'bg-white text-amber-700 shadow-xs' : 'text-gray-600 hover:text-gray-900'
            }`}
          >
            រង់ចាំពិនិត្យ ({pendingCount})
          </button>
          <button
            onClick={() => setFilter('approved')}
            className={`px-3 py-1.5 rounded-lg transition ${
              filter === 'approved' ? 'bg-white text-emerald-700 shadow-xs' : 'text-gray-600 hover:text-gray-900'
            }`}
          >
            បានអនុម័ត
          </button>
        </div>
      </div>

      {/* Reviews List */}
      <div className="bg-white rounded-2xl border border-gray-200/80 overflow-hidden shadow-xs">
        {loading ? (
          <div className="p-12 text-center text-gray-500 flex flex-col items-center gap-2">
            <Loader2 className="animate-spin text-emerald-600" size={32} />
            <span className="text-xs font-medium">កំពុងទាញយកការវាយតម្លៃ...</span>
          </div>
        ) : filteredReviews.length === 0 ? (
          <div className="p-12 text-center text-gray-400">
            <Star size={40} className="mx-auto mb-2 opacity-40" />
            <p className="text-sm font-medium">មិនមានការវាយតម្លៃក្នុងក្រុមនេះទេ</p>
          </div>
        ) : (
          <div className="divide-y divide-gray-100">
            {filteredReviews.map((r) => {
              const isApproved = r.status === 'approved';
              const isPending = !r.status || r.status === 'pending' || r.status === '';

              return (
                <div
                  key={r.id}
                  className="p-4 sm:p-5 hover:bg-gray-50/70 transition flex flex-col md:flex-row md:items-center justify-between gap-4"
                >
                  <div className="space-y-1.5 flex-1 max-w-2xl">
                    <div className="flex flex-wrap items-center gap-2">
                      <span className="font-bold text-gray-900 text-sm">{r.author || 'អនាមិក'}</span>
                      {r.product_name && (
                        <span className="px-2 py-0.5 rounded-lg text-[11px] font-medium bg-emerald-50 text-emerald-800 border border-emerald-200">
                          លើផលិតផល: {r.product_name}
                        </span>
                      )}
                      <span className="text-xs text-gray-400">
                        {r.created_at ? new Date(r.created_at).toLocaleDateString() : ''}
                      </span>
                    </div>

                    {/* Rating stars */}
                    <div className="flex items-center gap-1 text-amber-400">
                      {[...Array(5)].map((_, i) => (
                        <Star
                          key={i}
                          size={14}
                          className={i < (r.rating || 5) ? 'fill-amber-400' : 'text-gray-200'}
                        />
                      ))}
                      <span className="text-xs text-gray-500 font-semibold ml-1">
                        ({r.rating || 5}/5)
                      </span>
                    </div>

                    <p className="text-xs sm:text-sm text-gray-700 leading-relaxed bg-gray-50 p-2.5 rounded-xl border border-gray-100">
                      {r.content || r.comment || 'គ្មានមតិ'}
                    </p>
                  </div>

                  {/* Status & Actions */}
                  <div className="flex items-center gap-2 shrink-0 self-end md:self-center">
                    {isApproved ? (
                      <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                        <ShieldCheck size={13} />
                        បានបង្ហាញ (Approved)
                      </span>
                    ) : (
                      <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">
                        <Clock size={13} />
                        រង់ចាំអនុម័ត (Pending)
                      </span>
                    )}

                    <div className="flex items-center gap-1 ml-2">
                      {!isApproved ? (
                        <button
                          onClick={() => handleUpdateStatus(r.id, 'approved')}
                          className="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-semibold flex items-center gap-1 transition cursor-pointer"
                          title="អនុម័តឱ្យបង្ហាញលើគេហទំព័រ"
                        >
                          <Check size={14} />
                          អនុម័ត
                        </button>
                      ) : (
                        <button
                          onClick={() => handleUpdateStatus(r.id, 'rejected')}
                          className="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-xs font-medium flex items-center gap-1 transition cursor-pointer"
                          title="លាក់ការវាយតម្លៃ"
                        >
                          <EyeOff size={14} />
                          លាក់
                        </button>
                      )}

                      <button
                        onClick={() => setDeleteConfirm(r)}
                        className="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-xl transition cursor-pointer"
                        title="លុបចោល"
                      >
                        <Trash2 size={16} />
                      </button>
                    </div>
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </div>

      {/* Delete confirm modal */}
      {deleteConfirm && (
        <div className="fixed inset-0 z-50 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-2xl max-w-sm w-full p-6 shadow-2xl animate-fade-in text-center">
            <div className="w-12 h-12 rounded-full bg-red-100 text-red-600 flex items-center justify-center mx-auto mb-4">
              <Trash2 size={24} />
            </div>
            <h3 className="text-base font-bold text-gray-900">តើអ្នកពិតជាចង់លុបការវាយតម្លៃនេះ?</h3>
            <p className="text-xs text-gray-500 mt-2">
              ការវាយតម្លៃរបស់ <span className="font-semibold text-gray-800">{deleteConfirm.author}</span> នឹងត្រូវលុបជាអចិន្ត្រៃយ៍។
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

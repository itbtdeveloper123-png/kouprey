import React, { useState } from 'react';
import { X, Star, Send } from 'lucide-react';
import { useApp } from '../context/AppContext';
import { submitReview } from '../api/client';

export default function ReviewModal() {
  const { reviewProduct, setReviewProduct, t } = useApp();
  const [rating, setRating] = useState(5);
  const [hoverRating, setHoverRating] = useState(0);
  const [name, setName] = useState('');
  const [comment, setComment] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [submitted, setSubmitted] = useState(false);

  if (!reviewProduct) return null;

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!name.trim() || !comment.trim()) return;

    setSubmitting(true);
    const res = await submitReview(reviewProduct.id, name, comment, rating);
    setSubmitting(false);

    if (res && res.success) {
      setSubmitted(true);
      setTimeout(() => {
        setSubmitted(false);
        setReviewProduct(null);
        setName('');
        setComment('');
      }, 1500);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs animate-modal-fade">
      <div
        className="relative w-full max-w-md bg-white rounded-3xl shadow-2xl overflow-hidden p-6 md:p-8 animate-modal-slide"
        onClick={(e) => e.stopPropagation()}
      >
        <button
          onClick={() => setReviewProduct(null)}
          className="absolute top-4 right-4 p-2 text-gray-400 hover:text-gray-700 rounded-full transition-colors"
          aria-label="Close"
        >
          <X className="w-5 h-5" />
        </button>

        <h3 className="text-xl font-bold text-gray-900 mb-1">
          {t.write_review}
        </h3>
        <p className="text-xs text-gray-500 mb-5">
          {reviewProduct.name}
        </p>

        {submitted ? (
          <div className="text-center py-8 space-y-2 text-emerald-700">
            <div className="w-12 h-12 rounded-full bg-emerald-100 flex items-center justify-center mx-auto text-emerald-700 font-bold text-2xl">
              ✓
            </div>
            <p className="font-bold text-lg">សូមអរគុណ!</p>
            <p className="text-xs text-gray-500">មតិវាយតម្លៃរបស់អ្នកត្រូវបានកត់ត្រាទុកដោយជោគជ័យ។</p>
          </div>
        ) : (
          <form onSubmit={handleSubmit} className="space-y-4">
            {/* Star selector */}
            <div>
              <label className="block text-xs font-semibold text-gray-700 mb-1.5">
                {t.rating}
              </label>
              <div className="flex gap-1.5">
                {[1, 2, 3, 4, 5].map((star) => (
                  <button
                    key={star}
                    type="button"
                    onClick={() => setRating(star)}
                    onMouseEnter={() => setHoverRating(star)}
                    onMouseLeave={() => setHoverRating(0)}
                    className="p-1 focus:outline-hidden"
                  >
                    <Star
                      className={`w-7 h-7 transition-colors ${
                        star <= (hoverRating || rating)
                          ? 'fill-amber-400 text-amber-400'
                          : 'text-gray-200'
                      }`}
                    />
                  </button>
                ))}
              </div>
            </div>

            {/* Name */}
            <div>
              <label className="block text-xs font-semibold text-gray-700 mb-1">
                {t.your_name} *
              </label>
              <input
                type="text"
                required
                value={name}
                onChange={(e) => setName(e.target.value)}
                placeholder="ឧ. សុខ ចាន់ដារ៉ា"
                className="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:border-emerald-600 focus:outline-hidden"
              />
            </div>

            {/* Review Comment */}
            <div>
              <label className="block text-xs font-semibold text-gray-700 mb-1">
                {t.your_review} *
              </label>
              <textarea
                required
                rows={4}
                value={comment}
                onChange={(e) => setComment(e.target.value)}
                placeholder="សរសេរមតិ ឬចំណាប់អារម្មណ៍របស់អ្នកអំពីផលិតផលនេះ..."
                className="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:border-emerald-600 focus:outline-hidden"
              />
            </div>

            {/* Submit */}
            <div className="pt-2 flex gap-3">
              <button
                type="button"
                onClick={() => setReviewProduct(null)}
                className="flex-1 px-4 py-2.5 rounded-xl border border-gray-200 text-gray-600 text-sm font-semibold hover:bg-gray-50"
              >
                {t.cancel}
              </button>
              <button
                type="submit"
                disabled={submitting}
                className="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold shadow-md hover:shadow-emerald-700/20 transition-all disabled:opacity-50"
              >
                <Send className="w-4 h-4" />
                <span>{submitting ? '...' : t.submit}</span>
              </button>
            </div>
          </form>
        )}
      </div>
    </div>
  );
}

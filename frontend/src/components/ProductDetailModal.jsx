import React from 'react';
import { useNavigate } from 'react-router-dom';
import { X, Star, MessageSquare, ShieldCheck, ArrowRight, Send } from 'lucide-react';
import { useApp } from '../context/AppContext';
import { getImageUrl } from '../api/client';

export default function ProductDetailModal() {
  const navigate = useNavigate();
  const { selectedProduct, setSelectedProduct, setReviewProduct, settings, t, language } = useApp();

  if (!selectedProduct) return null;

  const imageUrl = getImageUrl(selectedProduct.image);
  const rating = Number(selectedProduct.avg_rating) || 5.0;
  const price = Number(selectedProduct.price) || 0;
  const originalPrice = Number(selectedProduct.original_price) || 0;

  const handleOpenReview = () => {
    setReviewProduct(selectedProduct);
    setSelectedProduct(null);
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs animate-modal-fade">
      <div
        className="relative w-full max-w-3xl bg-white rounded-3xl shadow-2xl overflow-hidden animate-modal-slide max-h-[90vh] flex flex-col md:flex-row"
        onClick={(e) => e.stopPropagation()}
      >
        {/* Close Button */}
        <button
          onClick={() => setSelectedProduct(null)}
          className="absolute top-4 right-4 z-10 p-2 text-gray-400 hover:text-gray-700 bg-white/80 hover:bg-white rounded-full shadow-xs transition-colors"
          aria-label="Close"
        >
          <X className="w-6 h-6" />
        </button>

        {/* Product Image Section */}
        <div className="md:w-1/2 bg-gray-50 flex items-center justify-center p-6 md:p-8 border-b md:border-b-0 md:border-r border-gray-100">
          <img
            src={imageUrl}
            alt={selectedProduct.name}
            className="max-h-[300px] md:max-h-[380px] w-auto object-contain drop-shadow-md"
            onError={(e) => {
              e.target.src = 'https://images.unsplash.com/photo-1541167760496-1628856ab772?auto=format&fit=crop&w=600&q=80';
            }}
          />
        </div>

        {/* Product Details Section */}
        <div className="md:w-1/2 p-6 md:p-8 flex flex-col justify-between overflow-y-auto">
          <div className="space-y-4">
            {selectedProduct.category_name && (
              <span className="text-xs font-bold uppercase tracking-wider text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-full">
                {selectedProduct.category_name}
              </span>
            )}

            <h2 className="text-2xl font-extrabold text-gray-900 leading-tight">
              {selectedProduct.name}
            </h2>

            {/* Rating and Reviews */}
            <div className="flex items-center gap-3">
              <div className="flex items-center text-amber-500 gap-1">
                {[...Array(5)].map((_, i) => (
                  <Star
                    key={i}
                    className={`w-4 h-4 ${
                      i < Math.floor(rating)
                        ? 'fill-amber-400 text-amber-400'
                        : 'text-gray-200'
                    }`}
                  />
                ))}
                <span className="text-sm font-bold text-gray-800 ml-1">
                  {rating.toFixed(1)}
                </span>
              </div>
              <span className="text-gray-300">|</span>
              <button
                onClick={handleOpenReview}
                className="text-xs font-semibold text-emerald-700 hover:underline flex items-center gap-1"
              >
                <MessageSquare className="w-3.5 h-3.5" />
                <span>{selectedProduct.review_count || 0} {t.reviews_count}</span>
              </button>
            </div>

            {/* Price */}
            <div className="flex items-baseline gap-3 pt-2">
              <span className="text-3xl font-extrabold text-emerald-800">
                ${price.toFixed(2)}
              </span>
              {originalPrice > price && (
                <span className="text-base text-gray-400 line-through">
                  ${originalPrice.toFixed(2)}
                </span>
              )}
            </div>

            {/* Description */}
            <div className="text-sm text-gray-600 space-y-2 leading-relaxed max-h-40 overflow-y-auto pr-1">
              <p>{selectedProduct.detailed_description || selectedProduct.short_description}</p>
            </div>

            {/* Quality badge */}
            <div className="flex items-center gap-2 text-xs text-emerald-800 bg-emerald-50/60 p-2.5 rounded-xl">
              <ShieldCheck className="w-4 h-4 text-emerald-600 flex-shrink-0" />
              <span>{language === 'km' ? 'គុណភាពស្តង់ដារខ្ពស់ ធានាភាពស្រស់ និងរសជាតិឆ្ងាញ់ពិតៗ' : 'Premium Standard Quality, Guaranteed Freshness & Authentic Flavor'}</span>
            </div>
          </div>

          {/* Actions */}
          <div className="pt-6 flex flex-wrap items-center gap-3">
            <button
              onClick={() => {
                const prodId = selectedProduct.base_product_id || selectedProduct.id;
                setSelectedProduct(null);
                navigate(`/product/${prodId}`);
              }}
              className="flex-1 inline-flex items-center justify-center gap-2 bg-gradient-to-r from-yellow-400 to-orange-500 hover:from-yellow-500 hover:to-orange-600 active:scale-95 text-gray-950 font-bold py-3.5 px-6 rounded-2xl shadow-lg shadow-orange-500/20 transition-all text-sm cursor-pointer"
            >
              <span>{language === 'km' ? 'មើលព័ត៌មានលម្អិត' : 'View Full Details'}</span>
              <ArrowRight className="w-4 h-4" />
            </button>

            {settings?.social_telegram && (
              <a
                href={settings.social_telegram}
                target="_blank"
                rel="noopener noreferrer"
                className="inline-flex items-center justify-center gap-2 bg-blue-500 hover:bg-blue-600 active:scale-95 text-white font-bold py-3.5 px-5 rounded-2xl shadow-lg shadow-blue-500/20 transition-all text-sm"
              >
                <Send className="w-4 h-4" />
                <span>Telegram</span>
              </a>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}

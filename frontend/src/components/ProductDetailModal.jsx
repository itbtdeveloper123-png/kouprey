import React, { useState } from 'react';
import { X, Star, ShoppingBag, MessageSquare, Check, ShieldCheck } from 'lucide-react';
import { useApp } from '../context/AppContext';
import { getImageUrl } from '../api/client';

export default function ProductDetailModal() {
  const { selectedProduct, setSelectedProduct, setReviewProduct, addToCart, t, language } = useApp();
  const [quantity, setQuantity] = useState(1);

  if (!selectedProduct) return null;

  const imageUrl = getImageUrl(selectedProduct.image);
  const rating = Number(selectedProduct.avg_rating) || 5.0;
  const price = Number(selectedProduct.price) || 0;
  const originalPrice = Number(selectedProduct.original_price) || 0;

  const handleAdd = () => {
    for (let i = 0; i < quantity; i++) {
      addToCart(selectedProduct);
    }
    setSelectedProduct(null);
  };

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

          {/* Quantity and Actions */}
          <div className="pt-6 space-y-3">
            <div className="flex items-center gap-3">
              <div className="flex items-center border border-gray-200 rounded-xl overflow-hidden">
                <button
                  onClick={() => setQuantity(Math.max(1, quantity - 1))}
                  className="px-3.5 py-2 text-gray-600 hover:bg-gray-100 font-bold"
                >
                  -
                </button>
                <span className="px-4 py-2 text-sm font-bold text-gray-800 min-w-10 text-center">
                  {quantity}
                </span>
                <button
                  onClick={() => setQuantity(quantity + 1)}
                  className="px-3.5 py-2 text-gray-600 hover:bg-gray-100 font-bold"
                >
                  +
                </button>
              </div>

              <button
                onClick={handleAdd}
                className="flex-1 inline-flex items-center justify-center gap-2 bg-emerald-700 hover:bg-emerald-800 active:scale-95 text-white font-bold py-3 px-6 rounded-xl shadow-lg hover:shadow-emerald-700/20 transition-all text-sm"
              >
                <ShoppingBag className="w-4 h-4" />
                <span>{t.add_to_cart} (${(price * quantity).toFixed(2)})</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

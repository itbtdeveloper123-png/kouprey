import React from 'react';
import { useNavigate } from 'react-router-dom';
import { Star, Flame, ArrowRight, Plus } from 'lucide-react';
import { useApp } from '../context/AppContext';
import { getImageUrl } from '../api/client';

export default function ProductCard({ product }) {
  const navigate = useNavigate();
  const { setSelectedProduct } = useApp();

  const imgPath = product.image || '';
  const imageUrl = getImageUrl(imgPath);
  const rating = Number(product.avg_rating) || 5.0;
  const reviewCount = product.review_count || 0;
  const price = Number(product.price) || 0;
  const originalPrice = Number(product.original_price || product.old_price) || 0;

  const handleClick = () => {
    navigate(`/product/${product.base_product_id || product.id}`, { state: { product } });
  };

  const handleQuickView = (e) => {
    e.stopPropagation();
    setSelectedProduct(product);
  };

  return (
    <article
      onClick={handleClick}
      className="product-item bg-white rounded-[2rem] p-3 md:p-5 transition-all duration-300 group cursor-pointer flex flex-col h-full hover:shadow-2xl border border-gray-100/60 relative overflow-hidden"
    >
      {/* Hover Gradient Blob (Desktop) */}
      <div className="hidden md:block absolute -top-10 -right-10 w-48 h-48 bg-yellow-100/80 rounded-full blur-3xl opacity-0 group-hover:opacity-60 transition-opacity pointer-events-none" />

      {/* Badges */}
      <div className="absolute top-4 left-4 flex flex-col gap-2 z-20">
        {product.featured == 1 ? (
          <span className="bg-yellow-500 text-white px-3 py-1 rounded-xl text-[11px] font-bold shadow-xs flex items-center gap-1.5 backdrop-blur-xs bg-opacity-95">
            <Star className="w-2.5 h-2.5 fill-white" />
            <span>FEATURED</span>
          </span>
        ) : (product.best_seller == 1 ? (
          <span className="bg-red-500 text-white px-3 py-1 rounded-xl text-[11px] font-bold shadow-xs flex items-center gap-1.5 backdrop-blur-xs bg-opacity-95">
            <Flame className="w-2.5 h-2.5 fill-white" />
            <span>HOT</span>
          </span>
        ) : null)}
      </div>

      {/* Image Container */}
      <div className="product-image-container relative mb-5 pt-[100%] rounded-3xl bg-gray-50/50 md:bg-transparent overflow-hidden">
        <div className="absolute inset-0 flex items-center justify-center p-2">
          <img
            src={imageUrl}
            alt={product.name}
            loading="lazy"
            className="main-img w-full h-full object-contain transform transition-transform duration-700 group-hover:scale-110 drop-shadow-2xl"
            style={{ filter: 'drop-shadow(0 15px 25px rgba(0,0,0,0.12))' }}
            onError={(e) => {
              e.target.src = 'https://images.unsplash.com/photo-1541167760496-1628856ab772?auto=format&fit=crop&w=400&q=80';
            }}
          />
        </div>

        {/* Mobile Quick Add/View Button (Overlay) */}
        <button
          onClick={handleQuickView}
          className="md:hidden absolute bottom-3 right-3 w-10 h-10 rounded-full bg-white shadow-lg flex items-center justify-center text-yellow-600 active:scale-90 transition-transform z-30 border border-gray-100"
          title="Quick View"
          aria-label="Quick View"
        >
          <Plus className="w-5 h-5" />
        </button>
      </div>

      {/* Content */}
      <div className="product-info flex-1 flex flex-col pt-1">
        {/* Title */}
        <h4 className="product-title text-base md:text-lg font-bold text-gray-900 mb-2 line-clamp-2 leading-snug min-h-[44px] group-hover:text-orange-600 transition-colors">
          {product.name}
        </h4>

        {/* Rating */}
        <div className="flex items-center gap-1.5 mb-3 md:mb-5">
          <div className="flex text-xs text-yellow-400">
            <Star className="w-3.5 h-3.5 fill-yellow-400 text-yellow-400" />
          </div>
          <span className="text-xs md:text-sm text-gray-500 font-medium">
            {rating.toFixed(1)} ({reviewCount})
          </span>
        </div>

        {/* Price & Action Button (Desktop) */}
        <div className="mt-auto flex items-center justify-between pt-1">
          <div className="flex flex-col">
            {originalPrice > price && (
              <span className="text-xs text-gray-400 line-through">
                ${originalPrice.toFixed(2)}
              </span>
            )}
            <span className="text-lg md:text-2xl font-black text-gray-900">
              ${price.toFixed(2)}
            </span>
          </div>

          {/* Desktop View Arrow Button */}
          <div className="hidden md:flex w-11 h-11 rounded-full bg-yellow-50 items-center justify-center text-yellow-600 group-hover:bg-yellow-500 group-hover:text-white transition-all duration-300 shadow-2xs">
            <ArrowRight className="w-4 h-4" />
          </div>
        </div>
      </div>
    </article>
  );
}

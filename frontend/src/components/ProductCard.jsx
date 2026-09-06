import React from 'react';
import { Link } from 'react-router-dom';
import { Star, Eye, ShoppingCart, Check } from 'lucide-react';
import { useApp } from '../context/AppContext';
import { getImageUrl } from '../api/client';

export default function ProductCard({ product }) {
  const { t, setSelectedProduct, addToCart } = useApp();

  const imgPath = product.image || '';
  const imageUrl = getImageUrl(imgPath);
  const rating = Number(product.avg_rating) || 5.0;
  const reviewCount = product.review_count || 0;
  const price = Number(product.price) || 0;
  const originalPrice = Number(product.original_price) || 0;

  return (
    <div className="group bg-white rounded-2xl border border-gray-100 shadow-xs hover:shadow-xl transition-all duration-300 flex flex-col overflow-hidden transform hover:-translate-y-1">
      {/* Image Container */}
      <div className="relative aspect-square w-full overflow-hidden bg-gray-50 flex items-center justify-center p-4">
        {/* Badges */}
        <div className="absolute top-3 left-3 z-10 flex flex-col gap-1.5">
          {product.featured == 1 && (
            <span className="bg-amber-500 text-white text-[11px] font-bold px-2.5 py-0.5 rounded-full shadow-xs">
              {t.featured}
            </span>
          )}
          {product.best_seller == 1 && (
            <span className="bg-rose-500 text-white text-[11px] font-bold px-2.5 py-0.5 rounded-full shadow-xs">
              {t.best_seller}
            </span>
          )}
        </div>

        {/* Product Image */}
        <img
          src={imageUrl}
          alt={product.name}
          loading="lazy"
          className="w-full h-full object-contain transform group-hover:scale-108 transition-transform duration-500"
          onError={(e) => {
            e.target.src = 'https://images.unsplash.com/photo-1541167760496-1628856ab772?auto=format&fit=crop&w=400&q=80';
          }}
        />

        {/* Quick View Floating Action */}
        <div className="absolute inset-0 bg-black/20 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2">
          <button
            onClick={() => setSelectedProduct(product)}
            className="p-3 bg-white text-gray-800 rounded-full shadow-lg hover:bg-emerald-600 hover:text-white transition-all transform hover:scale-110 active:scale-95"
            title={t.quick_view}
            aria-label="Quick View"
          >
            <Eye className="w-5 h-5" />
          </button>
        </div>
      </div>

      {/* Card Body */}
      <div className="p-4 md:p-5 flex-1 flex flex-col justify-between space-y-3">
        <div>
          {/* Category */}
          {product.category_name && (
            <p className="text-[11px] font-bold text-emerald-700 uppercase tracking-wider mb-1">
              {product.category_name}
            </p>
          )}

          {/* Name */}
          <Link
            to={`/product/${product.base_product_id || product.id}`}
            className="font-bold text-gray-900 hover:text-emerald-700 transition-colors text-base line-clamp-1 group-hover:text-emerald-700"
          >
            {product.name}
          </Link>

          {/* Short description */}
          {product.short_description && (
            <p className="text-xs text-gray-500 line-clamp-2 mt-1 leading-relaxed">
              {product.short_description}
            </p>
          )}
        </div>

        {/* Rating & Stock */}
        <div className="flex items-center justify-between text-xs pt-1 border-t border-gray-50">
          <div className="flex items-center text-amber-500 font-bold gap-1">
            <Star className="w-3.5 h-3.5 fill-amber-400 text-amber-400" />
            <span>{rating.toFixed(1)}</span>
            <span className="text-gray-400 font-normal">({reviewCount})</span>
          </div>

          <span className="text-emerald-600 text-[11px] font-medium flex items-center gap-1">
            <span className="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse" />
            {t.in_stock}
          </span>
        </div>

        {/* Price & Add to Cart */}
        <div className="flex items-center justify-between pt-1">
          <div className="flex items-baseline gap-1.5">
            <span className="text-lg font-extrabold text-emerald-800">
              ${price.toFixed(2)}
            </span>
            {originalPrice > price && (
              <span className="text-xs text-gray-400 line-through">
                ${originalPrice.toFixed(2)}
              </span>
            )}
          </div>

          <button
            onClick={() => addToCart(product)}
            className="inline-flex items-center gap-1.5 bg-emerald-700 hover:bg-emerald-800 active:scale-95 text-white text-xs font-bold px-3.5 py-2 rounded-xl shadow-xs hover:shadow-emerald-700/20 transition-all"
            aria-label="Add to cart"
          >
            <ShoppingCart className="w-3.5 h-3.5" />
            <span>{t.add_to_cart}</span>
          </button>
        </div>
      </div>
    </div>
  );
}

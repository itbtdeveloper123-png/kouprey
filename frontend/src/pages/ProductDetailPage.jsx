import React, { useState, useEffect } from 'react';
import { useParams, Link } from 'react-router-dom';
import { Star, ShoppingBag, ArrowLeft, MessageSquare, ShieldCheck, Heart } from 'lucide-react';
import { useApp } from '../context/AppContext';
import { fetchProductDetail, getImageUrl } from '../api/client';
import ProductCard from '../components/ProductCard';

export default function ProductDetailPage() {
  const { id } = useParams();
  const { language, t, addToCart, setReviewProduct } = useApp();
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [quantity, setQuantity] = useState(1);

  useEffect(() => {
    let isMounted = true;
    setLoading(true);
    fetchProductDetail(id, language).then((res) => {
      if (!isMounted) return;
      if (res && res.success) {
        setData(res);
      }
      setLoading(false);
    });
    return () => { isMounted = false; };
  }, [id, language]);

  if (loading) {
    return (
      <div className="max-w-6xl mx-auto px-4 md:px-6 py-12">
        <div className="bg-gray-100 rounded-3xl h-96 animate-pulse" />
      </div>
    );
  }

  if (!data || !data.product) {
    return (
      <div className="max-w-6xl mx-auto px-4 md:px-6 py-20 text-center space-y-4">
        <h2 className="text-2xl font-bold text-gray-800">រកមិនឃើញផលិតផលនេះទេ</h2>
        <Link to="/" className="inline-flex items-center gap-2 text-emerald-700 font-bold hover:underline">
          <ArrowLeft className="w-4 h-4" />
          <span>ត្រឡប់ទៅទំព័រដើមវិញ</span>
        </Link>
      </div>
    );
  }

  const { product, reviews = [], avg_rating = 5.0, total_reviews = 0, related_products = [] } = data;
  const imageUrl = getImageUrl(product.image);
  const price = Number(product.price) || 0;
  const originalPrice = Number(product.original_price) || 0;

  const handleAdd = () => {
    for (let i = 0; i < quantity; i++) {
      addToCart(product);
    }
  };

  return (
    <div className="max-w-6xl mx-auto px-4 md:px-6 py-6 space-y-12">
      {/* Breadcrumb */}
      <div className="flex items-center gap-2 text-xs font-semibold text-gray-500">
        <Link to="/" className="hover:text-emerald-700 transition-colors">
          {t.home}
        </Link>
        <span>/</span>
        <span className="text-emerald-800 line-clamp-1">{product.name}</span>
      </div>

      {/* Main Product Card Detail */}
      <div className="bg-white rounded-3xl border border-gray-100 shadow-xl overflow-hidden grid grid-cols-1 md:grid-cols-2">
        {/* Product Image */}
        <div className="bg-gray-50 p-8 md:p-12 flex items-center justify-center border-b md:border-b-0 md:border-r border-gray-100">
          <img
            src={imageUrl}
            alt={product.name}
            className="max-h-[380px] md:max-h-[460px] w-auto object-contain drop-shadow-md hover:scale-105 transition-transform duration-500"
            onError={(e) => {
              e.target.src = 'https://images.unsplash.com/photo-1541167760496-1628856ab772?auto=format&fit=crop&w=600&q=80';
            }}
          />
        </div>

        {/* Product Info */}
        <div className="p-8 md:p-12 flex flex-col justify-between space-y-6">
          <div className="space-y-4">
            {product.category_name && (
              <span className="text-xs font-bold uppercase tracking-wider text-emerald-700 bg-emerald-50 px-3 py-1 rounded-full">
                {product.category_name}
              </span>
            )}

            <h1 className="text-2xl md:text-4xl font-extrabold text-gray-900 leading-tight">
              {product.name}
            </h1>

            {/* Rating Stars */}
            <div className="flex items-center gap-3">
              <div className="flex items-center text-amber-500 gap-1">
                {[...Array(5)].map((_, i) => (
                  <Star
                    key={i}
                    className={`w-4 h-4 ${
                      i < Math.floor(avg_rating)
                        ? 'fill-amber-400 text-amber-400'
                        : 'text-gray-200'
                    }`}
                  />
                ))}
                <span className="text-sm font-bold text-gray-800 ml-1">
                  {Number(avg_rating).toFixed(1)}
                </span>
              </div>
              <span className="text-gray-300">|</span>
              <button
                onClick={() => setReviewProduct(product)}
                className="text-xs font-semibold text-emerald-700 hover:underline flex items-center gap-1"
              >
                <MessageSquare className="w-3.5 h-3.5" />
                <span>{total_reviews} {t.reviews_count}</span>
              </button>
            </div>

            {/* Price */}
            <div className="flex items-baseline gap-3 pt-2">
              <span className="text-3xl md:text-4xl font-extrabold text-emerald-800">
                ${price.toFixed(2)}
              </span>
              {originalPrice > price && (
                <span className="text-lg text-gray-400 line-through">
                  ${originalPrice.toFixed(2)}
                </span>
              )}
            </div>

            {/* Description */}
            <div className="text-sm text-gray-600 leading-relaxed space-y-3 pt-2">
              <p>{product.detailed_description || product.short_description}</p>
            </div>

            {/* Badges */}
            <div className="pt-2">
              <div className="flex items-center gap-2 text-xs text-emerald-800 bg-emerald-50 p-3 rounded-2xl">
                <ShieldCheck className="w-5 h-5 text-emerald-600 flex-shrink-0" />
                <span>ធានាគុណភាពខ្ពស់ ផលិតផលសុទ្ធ ១០០% និងសេវាកម្មដឹកជញ្ជូនរហ័ស</span>
              </div>
            </div>
          </div>

          {/* Action Row */}
          <div className="pt-6 border-t border-gray-100 flex items-center gap-4">
            <div className="flex items-center border border-gray-200 rounded-2xl overflow-hidden">
              <button
                onClick={() => setQuantity(Math.max(1, quantity - 1))}
                className="px-4 py-3 text-gray-600 hover:bg-gray-100 font-bold"
              >
                -
              </button>
              <span className="px-5 py-3 text-base font-bold text-gray-800 min-w-12 text-center">
                {quantity}
              </span>
              <button
                onClick={() => setQuantity(quantity + 1)}
                className="px-4 py-3 text-gray-600 hover:bg-gray-100 font-bold"
              >
                +
              </button>
            </div>

            <button
              onClick={handleAdd}
              className="flex-1 inline-flex items-center justify-center gap-2.5 bg-emerald-700 hover:bg-emerald-800 active:scale-95 text-white font-bold py-3.5 px-6 rounded-2xl shadow-lg hover:shadow-emerald-700/20 transition-all text-sm md:text-base"
            >
              <ShoppingBag className="w-5 h-5" />
              <span>{t.add_to_cart} (${(price * quantity).toFixed(2)})</span>
            </button>
          </div>
        </div>
      </div>

      {/* Customer Reviews Section */}
      <section className="space-y-6 pt-4">
        <div className="flex items-center justify-between border-b border-gray-100 pb-4">
          <h3 className="text-xl md:text-2xl font-extrabold text-gray-900">
            {t.reviews} ({reviews.length})
          </h3>
          <button
            onClick={() => setReviewProduct(product)}
            className="inline-flex items-center gap-2 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 text-xs font-bold px-4 py-2.5 rounded-full transition-colors"
          >
            <MessageSquare className="w-3.5 h-3.5" />
            <span>{t.write_review}</span>
          </button>
        </div>

        {reviews.length === 0 ? (
          <p className="text-sm text-gray-500 py-6 text-center">
            មិនទាន់មានមតិវាយតម្លៃសម្រាប់ផលិតផលនេះនៅឡើយទេ។ សូមក្លាយជាអ្នកដំបូងដែលសរសេរមតិវាយតម្លៃ!
          </p>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            {reviews.map((rev, idx) => (
              <div key={idx} className="bg-gray-50 p-5 rounded-2xl space-y-2 border border-gray-100">
                <div className="flex items-center justify-between">
                  <span className="font-bold text-sm text-gray-900">{rev.name}</span>
                  <div className="flex items-center text-amber-500 gap-0.5">
                    {[...Array(Number(rev.rating) || 5)].map((_, i) => (
                      <Star key={i} className="w-3.5 h-3.5 fill-amber-400 text-amber-400" />
                    ))}
                  </div>
                </div>
                <p className="text-xs text-gray-600 leading-relaxed">{rev.review}</p>
                {rev.created_at && (
                  <p className="text-[10px] text-gray-400">{rev.created_at}</p>
                )}
              </div>
            ))}
          </div>
        )}
      </section>

      {/* Related Products Section */}
      {related_products.length > 0 && (
        <section className="space-y-6 pt-6">
          <h3 className="text-xl md:text-2xl font-extrabold text-gray-900 border-b border-gray-100 pb-4">
            {t.related_products}
          </h3>
          <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 md:gap-6">
            {related_products.map((rp, idx) => (
              <ProductCard key={rp.id || idx} product={rp} />
            ))}
          </div>
        </section>
      )}
    </div>
  );
}

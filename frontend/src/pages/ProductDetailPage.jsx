import React, { useState, useEffect } from 'react';
import { useParams, Link } from 'react-router-dom';
import { useApp } from '../context/AppContext';
import { fetchProductDetail, getImageUrl } from '../api/client';
import ProductCard from '../components/ProductCard';
import { 
  Home, 
  Package, 
  Tag, 
  Coffee, 
  Star, 
  Scale, 
  Flame, 
  ShieldCheck, 
  Info, 
  Play, 
  MessageSquare, 
  ArrowLeft,
  Share2,
  PhoneCall,
  Send,
  CheckCircle,
  Clock
} from 'lucide-react';

export default function ProductDetailPage() {
  const { id } = useParams();
  const { language, settings, t, setReviewProduct, addToCart } = useApp();
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [activeTab, setActiveTab] = useState('details');

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
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div className="animate-pulse space-y-8">
          <div className="h-6 bg-gray-100 rounded-lg w-1/3" />
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-12">
            <div className="h-[480px] bg-gray-100 rounded-3xl" />
            <div className="space-y-4">
              <div className="h-10 bg-gray-100 rounded-xl w-3/4" />
              <div className="h-5 bg-gray-100 rounded-lg w-1/2" />
              <div className="h-32 bg-gray-100 rounded-2xl w-full" />
            </div>
          </div>
        </div>
      </div>
    );
  }

  if (!data || !data.product) {
    return (
      <div className="max-w-7xl mx-auto px-4 py-20 text-center space-y-4">
        <div className="w-16 h-16 bg-orange-50 text-orange-500 rounded-full flex items-center justify-center mx-auto">
          <Coffee className="w-8 h-8" />
        </div>
        <h2 className="text-2xl font-bold text-gray-800">
          {language === 'km' ? 'រកមិនឃើញព័ត៌មានផលិតផលនេះទេ' : 'Product Not Found'}
        </h2>
        <Link
          to="/?page=1&category=all"
          className="inline-flex items-center gap-2 bg-orange-500 hover:bg-orange-600 text-white font-bold px-6 py-2.5 rounded-full transition-all shadow-md"
        >
          <ArrowLeft className="w-4 h-4" />
          <span>{language === 'km' ? 'ត្រឡប់ទៅបញ្ជីផលិតផល' : 'Back to Products'}</span>
        </Link>
      </div>
    );
  }

  const { product, reviews = [], avg_rating = 0, total_reviews = 0, related_products = [] } = data;
  const imageUrl = getImageUrl(product.image) || '/assets/images/product-medium.png';
  const price = Number(product.price) || 0;
  const originalPrice = Number(product.original_price) || 0;

  // Parse custom fields (JSON)
  let customFields = {};
  try {
    if (typeof product.custom_fields === 'string') {
      customFields = JSON.parse(product.custom_fields || '{}');
    } else if (typeof product.custom_fields === 'object' && product.custom_fields !== null) {
      customFields = product.custom_fields;
    }
  } catch (e) {
    customFields = {};
  }

  // Parse YouTube video embed if available
  const getEmbedUrl = (url) => {
    if (!url) return null;
    const match = url.match(/(?:youtu\.be\/|youtube\.com\/(?:embed\/|v\/|watch\?v=|watch\?.+&v=))([\w-]{11})/);
    return match ? `https://www.youtube.com/embed/${match[1]}` : null;
  };
  const embedUrl = getEmbedUrl(product.video_url);

  return (
    <div className="min-h-screen bg-white pb-20 pt-20 md:pt-28">
      <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 pb-16">
        
        {/* Breadcrumb matching PHP product_detail.php */}
        <nav className="flex text-sm text-gray-500 mb-6 overflow-x-auto whitespace-nowrap pb-2 items-center space-x-2">
          <Link to="/?page=1&category=all" className="hover:text-orange-600 transition-colors flex items-center gap-1.5 font-medium">
            <Home className="w-3.5 h-3.5" />
            <span>{settings?.nav_home || (language === 'km' ? 'ទំព័រដើម' : 'Home')}</span>
          </Link>
          <span className="text-gray-300">/</span>
          <Link to="/?page=1&category=all#products" className="hover:text-orange-600 transition-colors flex items-center gap-1.5 font-medium">
            <Package className="w-3.5 h-3.5" />
            <span>{settings?.nav_product || (language === 'km' ? 'ផលិតផល' : 'Products')}</span>
          </Link>
          {product.category_name && (
            <>
              <span className="text-gray-300">/</span>
              <Link 
                to={`/?page=1&category=${product.base_category_id || product.category_id}#products`} 
                className="hover:text-orange-600 transition-colors flex items-center gap-1.5 font-medium"
              >
                <Tag className="w-3.5 h-3.5" />
                <span>{product.category_name}</span>
              </Link>
            </>
          )}
          <span className="text-gray-300">/</span>
          <span className="text-gray-800 font-bold truncate max-w-[200px] md:max-w-xs flex items-center gap-1.5">
            <Coffee className="w-3.5 h-3.5 text-orange-600 flex-shrink-0" />
            <span className="truncate">{product.name}</span>
          </span>
        </nav>

        {/* Product Hero 2-Column Grid */}
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-16 items-start">
          
          {/* Left: Product Image Frame & Quick Badges */}
          <div className="space-y-6 lg:sticky lg:top-24">
            <div 
              className="p-8 flex justify-center items-center shadow-inner aspect-square overflow-hidden relative rounded-[32px]"
              style={{ background: 'radial-gradient(circle, #fdfbf7 0%, #f5f5f5 100%)', minHeight: '420px', maxHeight: '520px' }}
            >
              <div className="w-full h-full flex items-center justify-center relative overflow-hidden">
                <img
                  src={imageUrl}
                  alt={product.name}
                  className="w-full h-full object-contain filter drop-shadow-2xl transition-transform duration-700 hover:scale-105"
                  onError={(e) => {
                    e.target.src = 'https://images.unsplash.com/photo-1541167760496-1628856ab772?auto=format&fit=crop&w=600&q=80';
                  }}
                />
              </div>
            </div>

            {/* Quick Info Grid Badges */}
            <div className="grid grid-cols-2 sm:grid-cols-3 gap-3 mt-6">
              <div className="bg-orange-50/50 border border-orange-100 rounded-2xl p-3.5 flex flex-col items-center text-center shadow-xs">
                <div className="w-10 h-10 bg-orange-500 rounded-xl flex items-center justify-center text-white mb-2 shadow-xs">
                  <Scale className="w-5 h-5" />
                </div>
                <span className="text-[10px] uppercase font-bold text-orange-600 tracking-wider">
                  {settings?.modal_weight || (language === 'km' ? 'ទម្ងន់' : 'Weight')}
                </span>
                <span className="text-sm font-bold text-gray-800 mt-0.5">
                  {product.weight || '500g'}
                </span>
              </div>

              {product.roast_level && (
                <div className="bg-amber-50/50 border border-amber-100 rounded-2xl p-3.5 flex flex-col items-center text-center shadow-xs">
                  <div className="w-10 h-10 bg-amber-700 rounded-xl flex items-center justify-center text-white mb-2 shadow-xs">
                    <Flame className="w-5 h-5" />
                  </div>
                  <span className="text-[10px] uppercase font-bold text-amber-700 tracking-wider">
                    {settings?.modal_roast_level || (language === 'km' ? 'កម្រិតលីង' : 'Roast')}
                  </span>
                  <span className="text-sm font-bold text-gray-800 mt-0.5">
                    {product.roast_level}
                  </span>
                </div>
              )}

              <div className="bg-green-50/50 border border-green-100 rounded-2xl p-3.5 flex flex-col items-center text-center shadow-xs">
                <div className="w-10 h-10 bg-green-500 rounded-xl flex items-center justify-center text-white mb-2 shadow-xs">
                  <ShieldCheck className="w-5 h-5" />
                </div>
                <span className="text-[10px] uppercase font-bold text-green-600 tracking-wider">
                  {language === 'km' ? 'គុណភាព' : 'Quality'}
                </span>
                <span className="text-sm font-bold text-gray-800 mt-0.5">
                  {product.status === 'active' ? (language === 'km' ? 'កម្រិតខ្ពស់' : 'Premium') : 'Standard'}
                </span>
              </div>
            </div>
          </div>

          {/* Right: Product Details */}
          <div className="space-y-6">
            <div>
              {product.featured == 1 && (
                <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-blue-50 text-blue-600 mb-3 tracking-wider uppercase border border-blue-100">
                  <Star className="w-3.5 h-3.5 fill-blue-500 text-blue-500" />
                  <span>{settings?.featured_products || 'Featured Product'}</span>
                </span>
              )}

              <h1 className="text-3xl md:text-4xl font-bold text-gray-900 mb-3 leading-tight font-freeman">
                {product.name}
              </h1>

              {/* Star Rating & Reviews Count */}
              <div className="flex items-center gap-3 mb-4">
                <div className="flex items-center text-amber-400 gap-1">
                  {[1, 2, 3, 4, 5].map((s) => (
                    <Star
                      key={s}
                      className={`w-4 h-4 ${
                        s <= Math.round(Number(avg_rating) || 5)
                          ? 'fill-amber-400 text-amber-400'
                          : 'text-gray-200'
                      }`}
                    />
                  ))}
                  <span className="ml-2 text-sm text-gray-600 font-medium">
                    {Number(avg_rating).toFixed(1)} ({total_reviews} {settings?.reviews_text || (language === 'km' ? 'ការពិនិត្យ' : 'Reviews')})
                  </span>
                </div>
              </div>

              {/* Price Row */}
              <div className="flex items-baseline gap-3 py-3 border-y border-gray-100 my-4">
                <span className="text-3xl font-extrabold text-orange-600">
                  ${price.toFixed(2)}
                </span>
                {originalPrice > price && (
                  <span className="text-lg text-gray-400 line-through">
                    ${originalPrice.toFixed(2)}
                  </span>
                )}
                {product.category_name && (
                  <span className="ml-auto text-xs font-bold uppercase tracking-wider text-gray-500 bg-gray-100 px-3 py-1 rounded-full">
                    {product.category_name}
                  </span>
                )}
              </div>

              {/* Short Description */}
              {product.description && (
                <p className="text-base text-gray-600 leading-relaxed whitespace-pre-line mb-6">
                  {product.description}
                </p>
              )}

              {/* Action Buttons: Add to Cart & Direct Contact */}
              <div className="flex flex-wrap items-center gap-4 pt-2">
                <button
                  onClick={() => addToCart(product)}
                  className="flex-1 min-w-[200px] h-12 bg-orange-500 hover:bg-orange-600 active:scale-98 text-white font-bold rounded-2xl flex items-center justify-center gap-2 shadow-lg shadow-orange-500/20 transition-all cursor-pointer"
                >
                  <Package className="w-5 h-5" />
                  <span>{t.add_to_cart}</span>
                </button>

                {settings?.social_telegram && (
                  <a
                    href={settings.social_telegram}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="h-12 px-6 bg-blue-50 hover:bg-blue-100 text-blue-600 font-bold rounded-2xl flex items-center justify-center gap-2 border border-blue-200/60 transition-all active:scale-98"
                  >
                    <Send className="w-4 h-4" />
                    <span>Telegram</span>
                  </a>
                )}

                {settings?.company_phone && (
                  <a
                    href={`tel:${settings.company_phone}`}
                    className="h-12 px-5 bg-gray-50 hover:bg-gray-100 text-gray-700 font-bold rounded-2xl flex items-center justify-center gap-2 border border-gray-200 transition-all active:scale-98"
                  >
                    <PhoneCall className="w-4 h-4 text-emerald-600" />
                    <span>{settings.company_phone}</span>
                  </a>
                )}
              </div>
            </div>

            {/* Detailed Description Card */}
            {product.detailed_description && (
              <div className="bg-white rounded-2xl border border-gray-100 shadow-xs p-5 space-y-2 mt-6">
                <h3 className="text-sm font-bold text-gray-900 flex items-center gap-2">
                  <Info className="w-4 h-4 text-blue-500" />
                  <span>{settings?.modal_detailed_description || (language === 'km' ? 'ព័ត៌មានលម្អិតអំពីផលិតផល' : 'Detailed Information')}</span>
                </h3>
                <div className="text-sm text-gray-600 leading-relaxed whitespace-pre-line">
                  {product.detailed_description}
                </div>
              </div>
            )}

            {/* Custom Fields (Nutrition Facts Tables & Ingredients Cards) */}
            {Object.keys(customFields).length > 0 && (
              <div className="space-y-6 pt-6 border-t border-gray-100">
                {Object.entries(customFields).map(([fKey, fData]) => {
                  if (!fData || typeof fData !== 'object') return null;
                  const fName = fData.name?.[language] || fData.name?.en || fKey;

                  // 1. Table Type (Nutrition Facts)
                  if (fData.type === 'table' && Array.isArray(fData.value)) {
                    return (
                      <div key={fKey} className="space-y-2.5">
                        <h4 className="text-base font-bold text-gray-800 flex items-center gap-2">
                          <span className="w-2 h-2 rounded-full bg-orange-500" />
                          <span>{fName}</span>
                        </h4>
                        <div className="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-xs divide-y divide-gray-100">
                          {fData.value.map((row, rIdx) => {
                            const rLabel = row.label?.[language] || row.label?.en || '';
                            const rValues = Array.isArray(row.value)
                              ? row.value.map(v => v[language] || v.en || '').filter(Boolean)
                              : [];
                            return (
                              <div key={rIdx} className="flex justify-between items-center py-3 px-4 hover:bg-gray-50/70 transition-colors">
                                <span className="text-sm text-gray-600 font-medium">{rLabel}</span>
                                <div className="flex items-center gap-4 text-sm font-bold text-gray-900">
                                  {rValues.map((v, vIdx) => (
                                    <span key={vIdx} className="ml-2">{v}</span>
                                  ))}
                                </div>
                              </div>
                            );
                          })}
                        </div>
                      </div>
                    );
                  }

                  // 2. Text Type (Ingredients, Directions, Storage, etc.)
                  const fVal = typeof fData.value === 'object'
                    ? (fData.value?.[language] || fData.value?.en || '')
                    : String(fData.value || '');

                  if (!fVal.trim()) return null;

                  return (
                    <div key={fKey} className="bg-white rounded-2xl border border-gray-100 p-5 shadow-xs space-y-2">
                      <h4 className="text-sm font-bold text-gray-900 flex items-center gap-2">
                        <CheckCircle className="w-4 h-4 text-orange-500" />
                        <span>{fName}</span>
                      </h4>
                      <p className="text-sm text-gray-600 leading-relaxed whitespace-pre-line">
                        {fVal}
                      </p>
                    </div>
                  );
                })}
              </div>
            )}

            {/* Video Player Embed if present */}
            {embedUrl && (
              <div className="space-y-3 pt-6 border-t border-gray-100">
                <h4 className="text-base font-bold text-gray-800 flex items-center gap-2">
                  <Play className="w-4 h-4 text-red-500 fill-red-500" />
                  <span>{language === 'km' ? 'វីដេអូបង្ហាញពីផលិតផល' : 'Product Video'}</span>
                </h4>
                <div className="aspect-video w-full rounded-2xl overflow-hidden shadow-lg border border-gray-200">
                  <iframe
                    src={embedUrl}
                    title={product.name}
                    className="w-full h-full"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                    allowFullScreen
                  />
                </div>
              </div>
            )}
          </div>
        </div>

        {/* Customer Reviews Section */}
        <section className="mt-20 pt-12 border-t border-gray-100 space-y-8">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
              <h2 className="text-2xl md:text-3xl font-bold text-gray-900 font-freeman">
                {settings?.modal_customer_reviews || (language === 'km' ? 'ការពិនិត្យពីអតិថិជន' : 'Customer Reviews')}
              </h2>
              <p className="text-sm text-gray-500 mt-1">
                {total_reviews} {settings?.reviews_text || (language === 'km' ? 'មតិវាយតម្លៃសម្រាប់ផលិតផលនេះ' : 'reviews for this product')}
              </p>
            </div>
            <button
              onClick={() => setReviewProduct(product)}
              className="inline-flex items-center justify-center gap-2 bg-gray-900 hover:bg-black text-white font-bold px-6 py-3 rounded-2xl transition-all shadow-md active:scale-98 cursor-pointer"
            >
              <MessageSquare className="w-4 h-4 text-orange-400" />
              <span>{settings?.modal_write_review || (language === 'km' ? 'សរសេរការពិនិត្យ' : 'Write a Review')}</span>
            </button>
          </div>

          {reviews.length === 0 ? (
            <div className="bg-gray-50/60 rounded-3xl p-10 text-center space-y-3 border border-gray-100">
              <MessageSquare className="w-8 h-8 text-gray-300 mx-auto" />
              <p className="text-gray-500 text-sm">
                {settings?.no_reviews || (language === 'km' ? 'មិនទាន់មានការពិនិត្យនៅឡើយទេ។ ជាអ្នកដំបូងដែលផ្តល់មតិវាយតម្លៃ!' : 'No reviews yet. Be the first to review this product!')}
              </p>
            </div>
          ) : (
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              {reviews.map((rev) => (
                <div key={rev.id} className="bg-white rounded-3xl border border-gray-100 p-6 shadow-xs space-y-4">
                  <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                      <div className="w-10 h-10 rounded-xl bg-orange-100 text-orange-600 font-bold flex items-center justify-center text-sm shadow-xs">
                        {rev.name ? rev.name.charAt(0).toUpperCase() : 'U'}
                      </div>
                      <div>
                        <h4 className="font-bold text-gray-900 text-sm">{rev.name}</h4>
                        <span className="text-[10px] text-gray-400 font-medium">
                          {rev.created_at ? new Date(rev.created_at).toLocaleDateString() : 'Recent'}
                        </span>
                      </div>
                    </div>
                    <div className="flex items-center text-amber-400 gap-0.5">
                      {[1, 2, 3, 4, 5].map((s) => (
                        <Star
                          key={s}
                          className={`w-3.5 h-3.5 ${
                            s <= rev.rating ? 'fill-amber-400 text-amber-400' : 'text-gray-200'
                          }`}
                        />
                      ))}
                    </div>
                  </div>
                  <p className="text-sm text-gray-600 leading-relaxed italic">
                    "{rev.review}"
                  </p>
                </div>
              ))}
            </div>
          )}
        </section>

        {/* Related Products Section */}
        {related_products.length > 0 && (
          <section className="mt-20 pt-12 border-t border-gray-100 space-y-8">
            <div className="flex items-center justify-between">
              <div>
                <h2 className="text-2xl md:text-3xl font-bold text-gray-900 font-freeman">
                  {settings?.related_products_title || (language === 'km' ? 'ផលិតផលដែលពាក់ព័ន្ធ' : 'Related Products')}
                </h2>
                <p className="text-sm text-gray-500 mt-1">
                  {language === 'km' ? 'ផលិតផលក្នុងជួរស្រដៀងគ្នាដែលអ្នកអាចនឹងចាប់អារម្មណ៍' : 'Other items in the same collection you might like'}
                </p>
              </div>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
              {related_products.slice(0, 4).map((rel) => (
                <ProductCard key={rel.id || rel.base_product_id} product={rel} />
              ))}
            </div>
          </section>
        )}
      </main>
    </div>
  );
}

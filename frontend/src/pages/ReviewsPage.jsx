import React, { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { useApp } from '../context/AppContext';
import { fetchAllReviews } from '../api/client';
import { 
  Star, 
  MessageSquare, 
  CheckCircle2, 
  ArrowRight, 
  Quote, 
  Sparkles,
  Plus
} from 'lucide-react';

export default function ReviewsPage() {
  const { language, settings, t, setReviewProduct } = useApp();
  const [data, setData] = useState({ reviews: [], total_reviews: 0, avg_rating: 5.0, rating_counts: {} });
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let isMounted = true;
    setLoading(true);
    fetchAllReviews().then((res) => {
      if (!isMounted) return;
      if (res && res.success) {
        setData(res);
      }
      setLoading(false);
    });
    return () => { isMounted = false; };
  }, []);

  const { reviews = [], total_reviews = 0, avg_rating = 5.0, rating_counts = {} } = data;

  // Group reviews by product_id or product_name
  const reviewsByProduct = {};
  reviews.forEach((r) => {
    const prodId = r.base_product_id || r.product_id || 'general';
    const prodName = r.product_name || (language === 'km' ? 'ផលិតផលទូទៅ' : 'KouPrey Products');
    const prodImg = r.product_image || '';

    if (!reviewsByProduct[prodId]) {
      reviewsByProduct[prodId] = {
        product_id: prodId,
        product_name: prodName,
        product_image: prodImg,
        reviews: []
      };
    }
    reviewsByProduct[prodId].reviews.push(r);
  });

  return (
    <div className="min-h-screen bg-white pb-24">
      <main className="max-w-6xl mx-auto px-4 md:px-6 py-12 md:py-20 space-y-16">
        
        {/* Header Section matching reviews.php */}
        <div className="text-center mb-16 space-y-4">
          <h1 className="text-4xl md:text-6xl font-bold bg-gradient-to-r from-gray-900 to-gray-600 bg-clip-text text-transparent font-freeman">
            {settings?.reviews_title || (language === 'km' ? 'ពិនិត្យរបស់អតិថិជន' : 'Customer Reviews')}
          </h1>
          <div className="w-24 h-1.5 bg-gradient-to-r from-orange-400 to-orange-600 mx-auto rounded-full" />
          <p className="text-gray-500 text-lg md:text-xl max-w-2xl mx-auto leading-relaxed">
            {settings?.reviews_description || (language === 'km'
              ? 'អានពីរបៀបដែលអតិថិជនចូលចិត្តកាហ្វេ និងផលិតផលរបស់យើង។'
              : 'Discover why coffee lovers across the country choose KouPrey for their daily caffeine ritual.')}
          </p>
        </div>

        {/* Overall Rating Score & Breakdown Bar */}
        <div className="bg-white rounded-3xl border border-gray-100 shadow-xl p-8 md:p-10 grid grid-cols-1 md:grid-cols-3 gap-8 items-center">
          <div className="text-center md:border-r border-gray-100 md:pr-8 space-y-2">
            <div className="text-5xl md:text-6xl font-extrabold text-gray-900 font-freeman">
              {Number(avg_rating).toFixed(1)}
            </div>
            <div className="flex items-center justify-center text-amber-400 gap-1">
              {[1, 2, 3, 4, 5].map((s) => (
                <Star
                  key={s}
                  className={`w-5 h-5 ${
                    s <= Math.round(Number(avg_rating) || 5)
                      ? 'fill-amber-400 text-amber-400'
                      : 'text-gray-200'
                  }`}
                />
              ))}
            </div>
            <p className="text-xs text-gray-500 font-medium">
              {total_reviews} {settings?.reviews_text || (language === 'km' ? 'ការពិនិត្យសរុប' : 'total reviews')}
            </p>
          </div>

          <div className="space-y-2.5 md:col-span-2">
            {[5, 4, 3, 2, 1].map((star) => {
              const count = rating_counts[star] || 0;
              const pct = total_reviews > 0 ? (count / total_reviews) * 100 : (star === 5 ? 100 : 0);
              return (
                <div key={star} className="flex items-center gap-3 text-xs">
                  <span className="w-12 font-bold text-gray-700 flex items-center gap-1">
                    {star} <Star className="w-3.5 h-3.5 fill-amber-400 text-amber-400" />
                  </span>
                  <div className="flex-1 h-3 bg-gray-100 rounded-full overflow-hidden">
                    <div
                      className="h-full bg-orange-500 rounded-full transition-all duration-700"
                      style={{ width: `${pct}%` }}
                    />
                  </div>
                  <span className="w-8 text-right text-gray-400 font-bold">
                    {count}
                  </span>
                </div>
              );
            })}
          </div>
        </div>

        {/* Grouped Reviews by Product */}
        {loading ? (
          <div className="space-y-8">
            {[1, 2].map((i) => (
              <div key={i} className="bg-gray-100 rounded-3xl h-64 animate-pulse" />
            ))}
          </div>
        ) : Object.keys(reviewsByProduct).length === 0 ? (
          <div className="bg-gray-50 rounded-3xl p-12 text-center space-y-4 border border-gray-100">
            <MessageSquare className="w-12 h-12 text-gray-300 mx-auto" />
            <h3 className="text-lg font-bold text-gray-700">
              {settings?.no_reviews || (language === 'km' ? 'មិនទាន់មានការពិនិត្យនៅឡើយទេ។' : 'No customer reviews yet.')}
            </h3>
            <Link
              to="/?page=1&category=all"
              className="inline-flex items-center gap-2 bg-orange-500 text-white font-bold px-6 py-2.5 rounded-full shadow-md hover:bg-orange-600 transition-all"
            >
              <span>{language === 'km' ? 'ស្វែងរកផលិតផលដើម្បីពិនិត្យ' : 'Explore Products to Review'}</span>
              <ArrowRight className="w-4 h-4" />
            </Link>
          </div>
        ) : (
          <div className="space-y-16">
            {Object.values(reviewsByProduct).map((productGroup) => (
              <div key={productGroup.product_id} className="space-y-6">
                {/* Product Group Header matching reviews.php */}
                <div className="bg-orange-50/50 p-6 rounded-3xl flex flex-col md:flex-row md:items-center justify-between gap-4 shadow-xs border border-orange-100">
                  <div>
                    <h3 className="text-2xl font-bold text-gray-800 font-freeman">
                      {productGroup.product_name}
                    </h3>
                    <p className="text-gray-500 text-sm mt-1">
                      {productGroup.reviews.length} {language === 'km' ? 'មតិវាយតម្លៃសម្រាប់ផលិតផលនេះ' : 'total reviews for this blend'}
                    </p>
                  </div>
                  {productGroup.product_id !== 'general' && (
                    <Link
                      to={`/product/${productGroup.product_id}`}
                      className="text-orange-600 font-bold text-sm hover:text-orange-700 flex items-center gap-2 group transition-colors"
                    >
                      <span>{language === 'km' ? 'មើលព័ត៌មានផលិតផល' : 'View Product'}</span>
                      <ArrowRight className="w-4 h-4 group-hover:translate-x-1 transition-transform" />
                    </Link>
                  )}
                </div>

                {/* Grid of Reviews for this product */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                  {productGroup.reviews.map((rev) => (
                    <div
                      key={rev.id}
                      className="bg-white rounded-3xl p-8 border border-gray-100 shadow-xs hover:shadow-md transition-all flex flex-col justify-between space-y-6 relative overflow-hidden"
                    >
                      <div className="flex items-center justify-between">
                        <div className="flex items-center gap-4">
                          <div className="w-12 h-12 bg-gradient-to-br from-orange-50 to-orange-100 rounded-2xl flex items-center justify-center text-orange-600 font-bold text-lg shadow-inner">
                            {rev.name ? rev.name.charAt(0).toUpperCase() : 'U'}
                          </div>
                          <div>
                            <div className="flex items-center gap-2">
                              <h4 className="font-bold text-gray-900 text-sm">{rev.name}</h4>
                              <span className="inline-flex items-center gap-1 text-[11px] font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-100">
                                <CheckCircle2 className="w-3 h-3" />
                                <span>Verified</span>
                              </span>
                            </div>
                            <div className="text-[10px] text-gray-400 font-bold uppercase tracking-widest mt-0.5">
                              {rev.created_at ? new Date(rev.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : 'Recent'}
                            </div>
                          </div>
                        </div>

                        <div className="flex text-amber-400 text-xs">
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

                      <p className="text-gray-600 leading-relaxed italic relative text-sm md:text-base">
                        "{rev.review}"
                      </p>
                    </div>
                  ))}
                </div>
              </div>
            ))}
          </div>
        )}
      </main>
    </div>
  );
}

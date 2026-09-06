import React, { useState, useEffect } from 'react';
import { useApp } from '../context/AppContext';
import { fetchAllReviews } from '../api/client';
import { Star, MessageSquare, Plus, ThumbsUp } from 'lucide-react';

export default function ReviewsPage() {
  const { language, t, setReviewProduct } = useApp();
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

  return (
    <div className="max-w-6xl mx-auto px-4 md:px-6 py-8 space-y-12">
      {/* Header */}
      <div className="text-center max-w-2xl mx-auto space-y-3">
        <div className="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-emerald-50 text-emerald-700 text-xs font-bold">
          <MessageSquare className="w-4 h-4 text-emerald-600" />
          <span>{t.reviews}</span>
        </div>
        <h1 className="text-3xl md:text-5xl font-extrabold text-gray-900 tracking-tight">
          {language === 'km' ? 'មតិយោបល់ពីអតិថិជនពិត' : 'Customer Reviews'}
        </h1>
        <p className="text-sm md:text-base text-gray-600 leading-relaxed">
          {language === 'km'
            ? 'ស្តាប់នូវការចែករំលែក និងបទពិសោធន៍ផ្ទាល់ពីអតិថិជនដែលបានទទួលទានផលិតផល KouPrey។'
            : 'Read genuine experiences and testimonials from our community of coffee & matcha lovers.'}
        </p>
      </div>

      {/* Ratings Overview Card */}
      <div className="bg-white rounded-3xl border border-gray-100 shadow-xl p-8 md:p-10 grid grid-cols-1 md:grid-cols-3 gap-8 items-center">
        {/* Score */}
        <div className="text-center md:border-r border-gray-100 md:pr-8 space-y-2">
          <div className="text-5xl md:text-6xl font-extrabold text-emerald-800">
            {Number(avg_rating).toFixed(1)}
          </div>
          <div className="flex items-center justify-center text-amber-500 gap-1">
            {[...Array(5)].map((_, i) => (
              <Star
                key={i}
                className={`w-5 h-5 ${
                  i < Math.floor(avg_rating)
                    ? 'fill-amber-400 text-amber-400'
                    : 'text-gray-200'
                }`}
              />
            ))}
          </div>
          <p className="text-xs text-gray-500">
            {total_reviews} {t.reviews_count}
          </p>
        </div>

        {/* Rating Breakdown Bars */}
        <div className="space-y-2 md:col-span-2">
          {[5, 4, 3, 2, 1].map((star) => {
            const count = rating_counts[star] || 0;
            const pct = total_reviews > 0 ? (count / total_reviews) * 100 : (star === 5 ? 100 : 0);
            return (
              <div key={star} className="flex items-center gap-3 text-xs">
                <span className="w-12 font-bold text-gray-700 flex items-center gap-1">
                  {star} <Star className="w-3 h-3 fill-amber-400 text-amber-400" />
                </span>
                <div className="flex-1 h-3 bg-gray-100 rounded-full overflow-hidden">
                  <div
                    className="h-full bg-emerald-600 rounded-full transition-all duration-500"
                    style={{ width: `${pct}%` }}
                  />
                </div>
                <span className="w-8 text-right text-gray-400 font-medium">
                  {count}
                </span>
              </div>
            );
          })}
        </div>
      </div>

      {/* Reviews Grid */}
      {loading ? (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          {[...Array(6)].map((_, i) => (
            <div key={i} className="bg-gray-100 rounded-3xl h-44 animate-pulse" />
          ))}
        </div>
      ) : reviews.length === 0 ? (
        <div className="text-center py-16 bg-gray-50 rounded-3xl border border-gray-100 space-y-2">
          <p className="text-gray-500 text-sm">មិនទាន់មានមតិវាយតម្លៃនៅឡើយទេ។</p>
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          {reviews.map((rev, idx) => (
            <div
              key={rev.id || idx}
              className="bg-white rounded-3xl border border-gray-100 p-6 shadow-xs hover:shadow-lg transition-all space-y-3 flex flex-col justify-between"
            >
              <div className="space-y-2">
                <div className="flex items-center justify-between">
                  <h4 className="font-bold text-sm text-gray-900">{rev.name}</h4>
                  <div className="flex items-center text-amber-500 gap-0.5">
                    {[...Array(Number(rev.rating) || 5)].map((_, i) => (
                      <Star key={i} className="w-3.5 h-3.5 fill-amber-400 text-amber-400" />
                    ))}
                  </div>
                </div>

                {rev.product_name && (
                  <p className="text-[11px] font-semibold text-emerald-700 bg-emerald-50 px-2.5 py-0.5 rounded-full inline-block">
                    {rev.product_name}
                  </p>
                )}

                <p className="text-xs text-gray-600 leading-relaxed italic">
                  "{rev.review}"
                </p>
              </div>

              {rev.created_at && (
                <p className="text-[10px] text-gray-400 pt-2 border-t border-gray-50">
                  {rev.created_at}
                </p>
              )}
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

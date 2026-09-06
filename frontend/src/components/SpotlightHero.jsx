import React, { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { Star, Flame, ArrowRight, Play } from 'lucide-react';
import { useApp } from '../context/AppContext';
import { getImageUrl } from '../api/client';

export default function SpotlightHero({ products = [] }) {
  const { language, settings } = useApp();
  const [currentIndex, setCurrentIndex] = useState(0);

  // Pick top/featured products for spotlight slider
  const spotlightProducts = products.length > 0 ? products.slice(0, 5) : [
    {
      id: 1,
      name: language === 'km' ? 'កាហ្វេពិសេស KouPrey Premium' : 'KouPrey Signature Coffee',
      short_description: language === 'km' ? 'រសជាតិឈ្ងុយឆ្ងាញ់ដិតជាប់មាត់ ផលិតពីគ្រាប់កាហ្វេធម្មជាតិ ១០០%។' : 'Rich and smooth flavor crafted from 100% natural premium coffee beans.',
      price: 12.50,
      avg_rating: 5.0,
      review_count: 28,
      featured: 1,
      image: '/kouprey/public/assets/images/product-medium.png'
    }
  ];

  useEffect(() => {
    if (spotlightProducts.length <= 1) return;
    const interval = setInterval(() => {
      setCurrentIndex((prev) => (prev + 1) % spotlightProducts.length);
    }, 6000);
    return () => clearInterval(interval);
  }, [spotlightProducts.length]);

  const current = spotlightProducts[currentIndex] || spotlightProducts[0];
  const imageUrl = current.image ? getImageUrl(current.image) : '/kouprey/public/assets/images/product-medium.png';

  return (
    <section className="relative w-full pt-24 pb-12 md:pt-32 md:pb-20 overflow-hidden bg-white">
      <div className="max-w-7xl mx-auto px-4 md:px-6 relative z-10">
        <div className="flex flex-col md:flex-row items-center gap-8 md:gap-16 lg:gap-24">
          
          {/* Product Image Showcase (Left on Desktop) */}
          <div className="w-full md:w-1/2 relative group">
            <div className="relative z-10 flex justify-center items-center">
              {/* Circular background behind image */}
              <div className="absolute w-[280px] h-[280px] md:w-[450px] md:h-[450px] bg-gradient-to-tr from-gray-100 to-gray-50 rounded-full z-0 transform transition-transform duration-700 group-hover:scale-105" />

              {/* Main Product Image */}
              <Link to={`/product/${current.base_product_id || current.id}`}>
                <img
                  src={imageUrl}
                  alt={current.name}
                  className="relative z-10 w-64 md:w-96 max-w-full drop-shadow-2xl transform transition-all duration-500 group-hover:-rotate-3 group-hover:scale-110 cursor-pointer object-contain"
                  onError={(e) => {
                    e.target.src = 'https://images.unsplash.com/photo-1541167760496-1628856ab772?auto=format&fit=crop&w=600&q=80';
                  }}
                />
              </Link>

              {/* Floating Stats Card */}
              <div
                className="absolute -bottom-4 md:bottom-10 -right-2 md:right-10 bg-white/90 backdrop-blur-md p-3 md:p-4 rounded-2xl shadow-xl z-20 border border-white/50 animate-bounce"
                style={{ animationDuration: '3s' }}
              >
                <div className="flex items-center gap-3">
                  <div className="bg-orange-100 p-2 rounded-full text-orange-600">
                    <Flame className="w-5 h-5 text-orange-600" />
                  </div>
                  <div>
                    <p className="text-[11px] text-gray-500 font-semibold uppercase tracking-wider">
                      Popularity
                    </p>
                    <p className="text-sm md:text-base font-bold text-gray-800">
                      {current.featured ? 'Featured Item' : (current.avg_rating >= 4.5 ? 'Top Rated' : 'Customer Favorite')}
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          {/* Product Content (Right on Desktop) */}
          <div className="w-full md:w-1/2 text-center md:text-left">
            {/* Signature Collection Badge */}
            <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-orange-100 text-orange-700 text-xs md:text-sm font-bold mb-4 md:mb-6">
              <span className="bg-orange-500 w-2 h-2 rounded-full animate-ping" />
              <span>{language === 'km' ? 'បណ្តុំផលិតផលពេញនិយម' : 'Signature Collection'}</span>
            </div>

            {/* Product Title */}
            <h2 className="text-3xl md:text-5xl lg:text-6xl font-bold text-gray-900 mb-4 md:mb-6 font-freeman leading-tight">
              {current.name}
            </h2>

            {/* Rating & Global Reviews */}
            <div className="flex items-center justify-center md:justify-start gap-3 mb-6">
              <div className="flex text-yellow-400 gap-1">
                {[...Array(5)].map((_, i) => (
                  <Star key={i} className="w-4 h-4 md:w-5 md:h-5 fill-yellow-400 text-yellow-400" />
                ))}
              </div>
              <span className="text-gray-300">|</span>
              <span className="text-gray-600 text-sm md:text-base">
                {current.review_count || 0} {language === 'km' ? 'ការពិនិត្យសកល' : 'Global Reviews'}
              </span>
            </div>

            {/* Short Description */}
            <p className="text-gray-600 text-base md:text-lg mb-8 leading-relaxed max-w-xl mx-auto md:mx-0">
              {current.short_description || current.description || 'រសជាតិឈ្ងុយឆ្ងាញ់ដិតជាប់មាត់ ផលិតពីគ្រាប់កាហ្វេ និងស្លឹកតែធម្មជាតិគុណភាពខ្ពស់។'}
            </p>

            {/* Action Buttons */}
            <div className="flex flex-col sm:flex-row gap-4 justify-center md:justify-start items-center">
              <a
                href="#products"
                className="bg-gradient-to-r from-orange-500 to-red-500 text-white px-8 py-3.5 md:py-4 rounded-full font-bold shadow-lg hover:shadow-orange-500/30 transform hover:-translate-y-1 transition-all duration-300 flex items-center gap-2 w-full sm:w-auto justify-center text-center cursor-pointer"
              >
                <span>{language === 'km' ? 'មើលផលិតផល' : 'View Product'}</span>
                <ArrowRight className="w-4 h-4" />
              </a>

              <Link
                to="/features"
                className="group flex items-center gap-2 text-gray-500 hover:text-orange-600 font-medium transition-colors px-6 py-3"
              >
                <div className="w-10 h-10 rounded-full border-2 border-gray-200 group-hover:border-orange-500 flex items-center justify-center transition-colors">
                  <Play className="w-3.5 h-3.5 text-gray-500 group-hover:text-orange-600 fill-current ml-0.5" />
                </div>
                <span>{language === 'km' ? 'ទស្សនាវីដេអូ' : 'Watch Video'}</span>
              </Link>
            </div>

            {/* Mini Features Grid */}
            <div className="grid grid-cols-3 gap-4 mt-8 md:mt-12 pt-6 md:pt-8 border-t border-gray-100 w-full">
              <div className="text-center md:text-left">
                <h4 className="font-bold text-gray-900 text-lg md:text-xl">100%</h4>
                <p className="text-xs text-gray-500 uppercase tracking-wider">Organic</p>
              </div>
              <div className="text-center md:text-left">
                <h4 className="font-bold text-gray-900 text-lg md:text-xl">Premium</h4>
                <p className="text-xs text-gray-500 uppercase tracking-wider">Quality</p>
              </div>
              <div className="text-center md:text-left">
                <h4 className="font-bold text-gray-900 text-lg md:text-xl">Fast</h4>
                <p className="text-xs text-gray-500 uppercase tracking-wider">Delivery</p>
              </div>
            </div>
          </div>
        </div>

        {/* Carousel Pagination Dots */}
        {spotlightProducts.length > 1 && (
          <div className="flex justify-center gap-2 pt-10">
            {spotlightProducts.map((_, idx) => (
              <button
                key={idx}
                onClick={() => setCurrentIndex(idx)}
                className={`h-2.5 rounded-full transition-all duration-300 ${
                  idx === currentIndex
                    ? 'w-8 bg-orange-500 shadow-sm shadow-orange-500/30'
                    : 'w-2.5 bg-gray-300 hover:bg-gray-400'
                }`}
                aria-label={`Slide ${idx + 1}`}
              />
            ))}
          </div>
        )}
      </div>
    </section>
  );
}

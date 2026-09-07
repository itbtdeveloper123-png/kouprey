import React, { useState, useEffect } from 'react';
import { ChevronLeft, ChevronRight, Sparkles, ArrowRight } from 'lucide-react';
import { useApp } from '../context/AppContext';
import { getImageUrl } from '../api/client';

export default function HeroBanner() {
  const { banners, heroImages, settings, t, language } = useApp();
  const [currentIndex, setCurrentIndex] = useState(0);

  // Combine available banner images
  const allImages = [...banners, ...heroImages].filter(Boolean);

  // If no uploaded banner is returned, provide tasteful gradient fallback
  const slides = allImages.length > 0 ? allImages : [null];

  useEffect(() => {
    if (slides.length <= 1) return;
    const interval = setInterval(() => {
      setCurrentIndex((prev) => (prev + 1) % slides.length);
    }, 5000);
    return () => clearInterval(interval);
  }, [slides.length]);

  const prevSlide = () => {
    setCurrentIndex((prev) => (prev - 1 + slides.length) % slides.length);
  };

  const nextSlide = () => {
    setCurrentIndex((prev) => (prev + 1) % slides.length);
  };

  return (
    <div className="relative max-w-6xl mx-auto px-4 md:px-6 pt-4 pb-8">
      <div className="relative rounded-3xl overflow-hidden shadow-xl bg-gradient-to-r from-emerald-950 via-emerald-900 to-teal-950 min-h-[360px] md:min-h-[440px] flex items-center">
        
        {/* Background Slide Image */}
        {slides[currentIndex] ? (
          <div className="absolute inset-0 z-0">
            <img
              src={getImageUrl(slides[currentIndex])}
              alt="Hero Banner"
              className="w-full h-full object-cover opacity-35 transition-opacity duration-700 ease-in-out"
            />
            <div className="absolute inset-0 bg-gradient-to-r from-emerald-950/90 via-emerald-900/60 to-transparent" />
          </div>
        ) : (
          <div className="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(16,185,129,0.2),transparent_50%)]" />
        )}

        {/* Content Overlay */}
        <div className="relative z-10 p-6 md:p-12 max-w-2xl text-white space-y-4">
          <div className="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-emerald-500/20 text-emerald-300 text-xs md:text-sm font-semibold backdrop-blur-md border border-emerald-400/30">
            <Sparkles className="w-4 h-4 text-emerald-300" />
            <span>{settings.company_name || 'KouPrey Coffee & Matcha'}</span>
          </div>

          <h1 className="text-3xl md:text-5xl font-extrabold tracking-tight leading-tight text-white drop-shadow-sm">
            {settings.hero_title || (language === 'km' ? 'រសជាតិដ៏ឈ្ងុយឆ្ងាញ់ គុណភាពខ្ពស់ពិតៗ' : 'Exceptional Flavor, Truly Premium Quality')}
          </h1>

          <p className="text-sm md:text-base text-emerald-100/90 leading-relaxed max-w-lg">
            {settings.hero_subtitle || (language === 'km' ? 'ផលិតផលគ្រឿងបន្ថែមរស់ជាតិ និងតែបៃតងធម្មជាតិ លំដាប់ពិសេស ផ្តល់នូវថាមពល និងភាពស្រស់ស្រាយពេញមួយថ្ងៃ។' : 'Specialty organic coffee and premium matcha to energize and refresh your entire day.')}
          </p>

          <div className="pt-2 flex flex-wrap gap-3 items-center">
            <a
              href="#products-section"
              className="inline-flex items-center gap-2 bg-emerald-500 hover:bg-emerald-400 text-emerald-950 font-bold px-6 py-3 rounded-full text-sm shadow-lg hover:shadow-emerald-500/30 transition-all transform active:scale-95"
            >
              <span>{t.products}</span>
              <ArrowRight className="w-4 h-4" />
            </a>

            {settings.social_telegram && (
              <a
                href={settings.social_telegram}
                target="_blank"
                rel="noreferrer"
                className="inline-flex items-center gap-2 bg-white/15 hover:bg-white/25 text-white font-medium px-5 py-3 rounded-full text-sm backdrop-blur-md transition-all"
              >
                <span>{t.contact_us}</span>
              </a>
            )}
          </div>
        </div>

        {/* Navigation Arrows */}
        {slides.length > 1 && (
          <>
            <button
              onClick={prevSlide}
              className="absolute left-3 md:left-5 top-1/2 -translate-y-1/2 z-20 p-2.5 rounded-full bg-black/30 hover:bg-black/50 text-white backdrop-blur-xs transition-colors"
              aria-label="Previous Slide"
            >
              <ChevronLeft className="w-5 h-5" />
            </button>
            <button
              onClick={nextSlide}
              className="absolute right-3 md:right-5 top-1/2 -translate-y-1/2 z-20 p-2.5 rounded-full bg-black/30 hover:bg-black/50 text-white backdrop-blur-xs transition-colors"
              aria-label="Next Slide"
            >
              <ChevronRight className="w-5 h-5" />
            </button>

            {/* Pagination Dots */}
            <div className="absolute bottom-4 left-1/2 -translate-x-1/2 z-20 flex gap-2">
              {slides.map((_, idx) => (
                <button
                  key={idx}
                  onClick={() => setCurrentIndex(idx)}
                  className={`h-2 rounded-full transition-all ${
                    idx === currentIndex
                      ? 'w-7 bg-emerald-400'
                      : 'w-2 bg-white/40 hover:bg-white/70'
                  }`}
                  aria-label={`Go to slide ${idx + 1}`}
                />
              ))}
            </div>
          </>
        )}
      </div>
    </div>
  );
}

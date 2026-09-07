import React, { useState, useEffect } from 'react';
import { useSearchParams } from 'react-router-dom';
import SpotlightHero from '../components/SpotlightHero';
import ZigzagSpotlight from '../components/ZigzagSpotlight';
import ProductCard from '../components/ProductCard';
import { useApp } from '../context/AppContext';
import { fetchProducts } from '../api/client';
import { Coffee, Tag, LayoutGrid, ChevronLeft, ChevronRight, MapPin, Clock, Store, Navigation, ExternalLink } from 'lucide-react';

export default function HomePage() {
  const { language, categories, settings } = useApp();
  const [searchParams, setSearchParams] = useSearchParams();

  // Parse page from query string: default 1
  const currentPage = Math.max(1, parseInt(searchParams.get('page') || '1', 10));
  // Normalize selected category: strip 'category-' prefix if present
  const cleanSelectedCategory = (searchParams.get('category') || 'all').replace(/^category-/, '');

  const [allProducts, setAllProducts] = useState(() => {
    try {
      const cached = localStorage.getItem(`kouprey_prods_${language}`);
      return cached ? JSON.parse(cached) : [];
    } catch {
      return [];
    }
  });
  const [loading, setLoading] = useState(() => allProducts.length === 0);

  const ITEMS_PER_PAGE = 9;

  // Fetch all products for language
  useEffect(() => {
    let isMounted = true;
    if (allProducts.length === 0) {
      setLoading(true);
    }
    fetchProducts({ lang: language }).then((prods) => {
      if (!isMounted) return;
      if (prods && prods.length > 0) {
        setAllProducts(prods);
        try {
          localStorage.setItem(`kouprey_prods_${language}`, JSON.stringify(prods));
        } catch {}
      }
      setLoading(false);
    });
    return () => { isMounted = false; };
  }, [language]);

  // Filter products by category (matching base_category_id or category_id or name fallback)
  const filteredProducts = allProducts.filter((product) => {
    if (cleanSelectedCategory === 'all') return true;
    if (cleanSelectedCategory === 'featured') return product.featured == 1;
    return (
      product.base_category_id == cleanSelectedCategory ||
      product.category_id == cleanSelectedCategory ||
      (cleanSelectedCategory === '19' && /syrup|ស៊ីរ៉ូ|សុីរ៉ូ/i.test(product.name || '')) ||
      (cleanSelectedCategory === '13' && /powder|matcha|ម្សៅ/i.test(product.name || ''))
    );
  });

  const totalProducts = filteredProducts.length;
  const totalPages = Math.max(1, Math.ceil(totalProducts / ITEMS_PER_PAGE));
  const validPage = Math.min(currentPage, totalPages);
  const startIndex = (validPage - 1) * ITEMS_PER_PAGE;
  const pagedProducts = filteredProducts.slice(startIndex, startIndex + ITEMS_PER_PAGE);

  const handleSelectCategory = (catId) => {
    const cleanId = catId.toString().replace(/^category-/, '');
    const params = new URLSearchParams(searchParams);
    if (cleanId === 'all') {
      params.delete('category');
    } else {
      params.set('category', cleanId);
    }
    params.set('page', '1');
    setSearchParams(params);

    const elem = document.getElementById('products');
    if (elem) {
      const rect = elem.getBoundingClientRect();
      if (rect.top < -100 || rect.top > window.innerHeight) {
        elem.scrollIntoView({ behavior: 'auto', block: 'start' });
      }
    }
  };

  const handlePageChange = (page) => {
    if (page < 1 || page > totalPages) return;
    const params = new URLSearchParams(searchParams);
    params.set('page', page.toString());
    setSearchParams(params);

    const elem = document.getElementById('products');
    if (elem) {
      const rect = elem.getBoundingClientRect();
      if (rect.top < -100 || rect.top > window.innerHeight) {
        elem.scrollIntoView({ behavior: 'auto', block: 'start' });
      }
    }
  };

  // Build pagination window
  const getPageNumbers = () => {
    const pages = [];
    const maxPagesToShow = 5;
    let startPage = Math.max(1, validPage - 2);
    let endPage = Math.min(totalPages, validPage + 2);

    if (endPage - startPage < maxPagesToShow - 1) {
      if (startPage === 1) {
        endPage = Math.min(totalPages, maxPagesToShow);
      } else {
        startPage = Math.max(1, totalPages - maxPagesToShow + 1);
      }
    }

    for (let i = startPage; i <= endPage; i++) {
      pages.push(i);
    }
    return { pages, startPage, endPage };
  };

  const { pages, startPage, endPage } = getPageNumbers();

  return (
    <div className="min-h-screen bg-white pb-20">
      {/* 1. Spotlight Showcase Hero Slider (Draggable / Swipeable) */}
      <SpotlightHero products={allProducts} />

      {/* 2. Category Zigzag Feature Showcase with Draggable Swipers */}
      <ZigzagSpotlight products={allProducts} onSelectCategory={handleSelectCategory} />

      {/* 3. Main Product Catalog Section with Sidebar & Grid */}
      <section id="products" className="px-3 py-10 md:px-6 md:py-20 lg:py-24 bg-white">
        <div className="max-w-7xl mx-auto">
          {/* Section Header */}
          <div className="text-center mb-10 md:mb-16">
            <h3 className="text-3xl md:text-5xl lg:text-6xl font-black text-gray-900 mb-4 flex items-center justify-center tracking-tight">
              <Coffee className="w-8 h-8 md:w-12 md:h-12 text-yellow-500 mr-3 md:mr-4 inline-block" />
              <span>{settings.our_products || (language === 'km' ? 'ផលិតផលរបស់យើង' : 'Our Products')}</span>
            </h3>
            <p className="text-gray-500 text-base md:text-xl max-w-2xl mx-auto">
              {settings.our_products_description || (language === 'km'
                ? 'ស្វែងរកផលិតផលទាំងអស់របស់យើង'
                : 'Discover our complete collection of premium coffee products')}
            </p>
          </div>

          {/* Sidebar Layout */}
          <div className="flex flex-col lg:flex-row gap-8">
            
            {/* Categories Sidebar (Desktop) */}
            <div className="hidden lg:block lg:w-1/4">
              <div className="bg-white rounded-2xl p-8 shadow-lg border border-gray-100 sticky top-24">
                <h4 className="text-xl font-bold text-gray-900 mb-6 flex items-center gap-3">
                  <Tag className="w-5 h-5 text-indigo-600" />
                  <span>{language === 'km' ? 'ប្រភេទផលិតផល' : 'Product Categories'}</span>
                </h4>

                <div className="max-h-96 overflow-y-auto pr-1 space-y-3">
                  {/* All Products Button */}
                  <button
                    onClick={() => handleSelectCategory('all')}
                    className={`w-full text-left px-5 py-4 rounded-xl transition-all duration-300 flex items-center justify-between group cursor-pointer ${
                      cleanSelectedCategory === 'all'
                        ? 'bg-yellow-50 text-black font-semibold shadow-xs'
                        : 'hover:bg-indigo-50 hover:shadow-md text-gray-700'
                    }`}
                  >
                    <span className="flex items-center gap-3">
                      <LayoutGrid className={`w-5 h-5 transition-transform group-hover:scale-110 ${cleanSelectedCategory === 'all' ? 'text-black' : 'text-gray-500'}`} />
                      <span className="font-medium text-sm">
                        {language === 'km' ? 'ផលិតផលទាំងអស់' : 'All Products'}
                      </span>
                    </span>
                    <span className="bg-black/15 text-black px-3 py-1 rounded-full text-xs font-semibold">
                      {allProducts.length}
                    </span>
                  </button>

                  {/* Category Buttons from Database */}
                  {categories.map((category) => {
                    const catId = (category.base_category_id || category.id).toString();
                    const isSelected = cleanSelectedCategory === catId;
                    const catCount = allProducts.filter(
                      p => p.base_category_id == category.base_category_id ||
                           p.category_id == category.id ||
                           (catId === '19' && /syrup|ស៊ីរ៉ូ|សុីរ៉ូ/i.test(p.name || '')) ||
                           (catId === '13' && /powder|matcha|ម្សៅ/i.test(p.name || ''))
                    ).length;

                    return (
                      <button
                        key={category.id || catId}
                        onClick={() => handleSelectCategory(catId)}
                        className={`w-full text-left px-5 py-4 rounded-xl transition-all duration-300 flex items-center justify-between group cursor-pointer ${
                          isSelected
                            ? 'bg-yellow-50 text-black font-semibold shadow-xs'
                            : 'hover:bg-indigo-50 hover:shadow-md text-gray-700'
                        }`}
                      >
                        <span className="flex items-center gap-3">
                          <Tag className="w-4 h-4 text-indigo-500 group-hover:text-indigo-600 transition-colors" />
                          <span className="font-medium text-sm group-hover:text-gray-900">
                            {category.name}
                          </span>
                        </span>
                        <span className="bg-indigo-100 text-indigo-700 px-3 py-1 rounded-full text-xs font-semibold group-hover:bg-indigo-200 transition-colors">
                          {catCount}
                        </span>
                      </button>
                    );
                  })}
                </div>
              </div>
            </div>

            {/* Products Grid Column (Desktop 3/4) */}
            <div className="w-full lg:w-3/4">
              {loading ? (
                <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-6 md:gap-8">
                  {[...Array(9)].map((_, i) => (
                    <div key={i} className="bg-gray-100 rounded-[2rem] h-80 animate-pulse" />
                  ))}
                </div>
              ) : pagedProducts.length === 0 ? (
                <div className="text-center py-20 bg-gray-50 rounded-[2rem] border border-gray-100 space-y-3">
                  <Coffee className="w-16 h-16 text-gray-300 mx-auto" />
                  <p className="text-gray-500 text-lg">
                    {language === 'km' ? 'មិនមានផលិតផលនៅពេលនេះទេ។' : 'No products available at the moment.'}
                  </p>
                </div>
              ) : (
                <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-6 md:gap-8">
                  {pagedProducts.map((product) => (
                    <ProductCard key={product.id} product={product} />
                  ))}
                </div>
              )}

              {/* iOS Style Pagination Box (Matching product.php lines 2146-2195) */}
              {totalPages > 1 && (
                <div className="mt-14 flex justify-center">
                  <nav className="inline-flex items-center bg-gray-100/90 backdrop-blur-md p-1.5 rounded-[1.5rem] border border-gray-200/60 shadow-xs">
                    {/* Previous Page */}
                    <button
                      onClick={() => handlePageChange(validPage - 1)}
                      disabled={validPage <= 1}
                      className={`w-10 h-10 flex items-center justify-center rounded-full transition-all duration-200 cursor-pointer ${
                        validPage <= 1
                          ? 'text-gray-300 cursor-not-allowed'
                          : 'text-gray-500 hover:bg-white hover:text-orange-600'
                      }`}
                      aria-label="Previous Page"
                    >
                      <ChevronLeft className="w-5 h-5" />
                    </button>

                    {/* Page Numbers */}
                    <div className="flex items-center px-2 gap-1">
                      {startPage > 1 && (
                        <>
                          <button
                            onClick={() => handlePageChange(1)}
                            className="w-10 h-10 flex items-center justify-center rounded-full text-sm font-bold text-gray-500 hover:bg-white hover:text-orange-600 transition-all cursor-pointer"
                          >
                            1
                          </button>
                          {startPage > 2 && (
                            <span className="px-1.5 text-gray-400 text-xs italic">...</span>
                          )}
                        </>
                      )}

                      {pages.map((p) => {
                        const isCurrent = p === validPage;
                        return (
                          <button
                            key={p}
                            onClick={() => handlePageChange(p)}
                            className={`w-10 h-10 flex items-center justify-center rounded-full text-sm font-bold transition-all duration-300 cursor-pointer ${
                              isCurrent
                                ? 'bg-orange-500 text-white shadow-lg shadow-orange-500/30 scale-110'
                                : 'text-gray-500 hover:bg-white hover:text-orange-600'
                            }`}
                          >
                            {p}
                          </button>
                        );
                      })}

                      {endPage < totalPages && (
                        <>
                          {endPage < totalPages - 1 && (
                            <span className="px-1.5 text-gray-400 text-xs italic">...</span>
                          )}
                          <button
                            onClick={() => handlePageChange(totalPages)}
                            className="w-10 h-10 flex items-center justify-center rounded-full text-sm font-bold text-gray-500 hover:bg-white hover:text-orange-600 transition-all cursor-pointer"
                          >
                            {totalPages}
                          </button>
                        </>
                      )}
                    </div>

                    {/* Next Page */}
                    <button
                      onClick={() => handlePageChange(validPage + 1)}
                      disabled={validPage >= totalPages}
                      className={`w-10 h-10 flex items-center justify-center rounded-full transition-all duration-200 cursor-pointer ${
                        validPage >= totalPages
                          ? 'text-gray-300 cursor-not-allowed'
                          : 'text-gray-500 hover:bg-white hover:text-orange-600'
                      }`}
                      aria-label="Next Page"
                    >
                      <ChevronRight className="w-5 h-5" />
                    </button>
                  </nav>
                </div>
              )}

            </div>
          </div>
        </div>
      </section>

      {/* ───────────────────────────────────────────────────────── */}
      {/* Visit Us / Location Map Section (Matching product.php lines 2740-2818) */}
      {/* ───────────────────────────────────────────────────────── */}
      <section id="location" className="py-20 md:py-32 relative overflow-hidden bg-gray-50/50">
        {/* Decorative Ambient Blurs */}
        <div className="absolute top-0 left-0 w-full h-full pointer-events-none opacity-30">
          <div className="absolute top-[-10%] right-[-5%] w-[500px] h-[500px] bg-orange-200/40 rounded-full blur-[100px]" />
          <div className="absolute bottom-[-10%] left-[-5%] w-[400px] h-[400px] bg-blue-200/40 rounded-full blur-[100px]" />
        </div>

        <div className="max-w-7xl mx-auto px-4 md:px-6 relative z-10">
          {/* Header */}
          <div className="text-center mb-12 md:mb-16">
            <span className="text-orange-600 font-bold tracking-widest uppercase text-xs md:text-sm mb-3 block">
              {settings.location_subtitle_tag || 'VISIT US'}
            </span>
            <h2 className="text-4xl md:text-6xl font-black text-gray-900 mb-6 drop-shadow-xs">
              {settings.location_title || (language === 'km' ? 'ទីតាំងរបស់យើង' : 'Our Locations')}
            </h2>
            <p className="text-gray-500 max-w-2xl mx-auto text-base md:text-lg leading-relaxed">
              {settings.location_desc || (language === 'km' ? 'សូមអញ្ជើញមកទទួលយកបទពិសោធន៍ក្លិនក្រអូប និងរសជាតិគ្រឿងបន្ថែមរស់ជាតិគុណភាពខ្ពស់របស់យើងដោយផ្ទាល់។' : 'Come experience the aroma and taste of our premium coffee in person.')}
            </p>
          </div>

          {/* Dual-Pane Card */}
          <div className="bg-white rounded-[2.5rem] shadow-2xl overflow-hidden flex flex-col lg:flex-row min-h-[580px] border border-gray-100 hover:shadow-[0_20px_60px_rgba(0,0,0,0.12)] transition-shadow duration-500">
            {/* Info Side */}
            <div className="w-full lg:w-2/5 p-8 md:p-12 lg:p-16 flex flex-col justify-center relative bg-white">
              {/* Decorative Corner Shape */}
              <div className="absolute right-0 top-0 w-32 h-32 bg-orange-50 rounded-bl-[100%] opacity-50 pointer-events-none" />

              <h3 className="text-2xl font-bold text-gray-900 mb-8 md:mb-10 flex items-center gap-3">
                <span className="w-8 h-8 rounded-full bg-orange-500 flex items-center justify-center text-white text-sm shadow-sm flex-shrink-0">
                  <Store className="w-4 h-4" />
                </span>
                <span>{settings.location_store_name || 'KouPrey HQ'}</span>
              </h3>

              <div className="space-y-6 relative z-10">
                {/* Address Card */}
                <div className="group flex items-start gap-4 md:gap-5 p-4 rounded-2xl hover:bg-gray-50 transition-colors duration-300 cursor-default border border-transparent hover:border-gray-100">
                  <div className="w-14 h-14 rounded-2xl bg-orange-100/50 group-hover:bg-orange-100 flex items-center justify-center text-orange-600 flex-shrink-0 transition-colors shadow-xs">
                    <MapPin className="w-7 h-7" />
                  </div>
                  <div>
                    <span className="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1 block">Address</span>
                    <h4 className="font-bold text-gray-900 text-lg mb-1">
                      {settings.location_address_label || (language === 'km' ? 'ហាងរបស់យើង' : 'Our Store')}
                    </h4>
                    <p className="text-gray-600 leading-relaxed font-medium text-sm md:text-base">
                      {settings.company_address || (language === 'km' ? '120408 សង្កាត់បឹងកក់ 2 ខណ្ឌទួលគោក រាជធានីភ្នំពេញ ប្រទេសកម្ពុជា។' : '120408 Sangkat Boeung Kak 2, Khan Tuol Kouk, Phnom Penh, Cambodia.')}
                    </p>
                  </div>
                </div>

                {/* Hours Card */}
                <div className="group flex items-start gap-4 md:gap-5 p-4 rounded-2xl hover:bg-gray-50 transition-colors duration-300 cursor-default border border-transparent hover:border-gray-100">
                  <div className="w-14 h-14 rounded-2xl bg-blue-100/50 group-hover:bg-blue-100 flex items-center justify-center text-blue-600 flex-shrink-0 transition-colors shadow-xs">
                    <Clock className="w-7 h-7" />
                  </div>
                  <div>
                    <span className="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1 block">Schedule</span>
                    <h4 className="font-bold text-gray-900 text-lg mb-1">
                      {settings.location_hours_label || (language === 'km' ? 'ម៉ោងបើកដំណើរការ' : 'Opening Hours')}
                    </h4>
                    <p className="text-gray-600 leading-relaxed font-medium text-sm md:text-base">
                      {settings.company_hours || (language === 'km' ? 'រៀងរាល់ថ្ងៃ៖ ម៉ោង ៧:០០ ព្រឹក - ៨:០០ យប់' : 'Daily: 7:00 AM - 8:00 PM')}
                    </p>
                  </div>
                </div>
              </div>

              {/* Get Directions Button */}
              <a
                href={settings.company_map_link || 'https://maps.app.goo.gl/v88Vyavc1UoykzgNA'}
                target="_blank"
                rel="noopener noreferrer"
                className="mt-8 md:mt-10 group relative flex items-center justify-center gap-3 bg-gray-900 text-white py-4 md:py-5 px-8 rounded-2xl font-bold overflow-hidden transition-all hover:bg-orange-600 shadow-xl hover:shadow-orange-500/30 transform hover:-translate-y-0.5 cursor-pointer"
              >
                <Navigation className="w-5 h-5 group-hover:rotate-45 transition-transform duration-300" />
                <span>{settings.location_btn_text || (language === 'km' ? 'Get Directions' : 'Get Directions')}</span>
              </a>
            </div>

            {/* Map Side */}
            <div className="w-full lg:w-3/5 h-[420px] sm:h-[480px] lg:h-auto min-h-[420px] relative bg-gray-100">
              {/* Open in Maps Overlay */}
              <a
                href={settings.company_map_link || 'https://maps.app.goo.gl/v88Vyavc1UoykzgNA'}
                target="_blank"
                rel="noopener noreferrer"
                className="absolute top-4 left-4 z-10 bg-white/95 backdrop-blur-md text-gray-800 text-xs font-bold px-3.5 py-2 rounded-xl shadow-md hover:bg-white hover:text-orange-600 flex items-center gap-1.5 border border-gray-200/80 transition-all cursor-pointer"
              >
                <span>Open in Maps</span>
                <ExternalLink className="w-3.5 h-3.5" />
              </a>

              <iframe
                src={settings.company_map_embed || 'https://www.google.com/maps/embed?pb=!1m14!1m12!1m3!1d292.34896165878865!2d104.91197826608598!3d11.55083956811418!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!5e1!3m2!1sen!2skh!4v1767834278383!5m2!1sen!2skh'}
                className="absolute inset-0 w-full h-full border-0 contrast-[1.05]"
                allowFullScreen=""
                loading="lazy"
                title="KouPrey Coffee Location"
              />
            </div>
          </div>
        </div>
      </section>

    </div>
  );
}

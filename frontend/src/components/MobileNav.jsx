import React, { useState } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { Coffee, Zap, Star, User, Filter, X, Grid, RotateCcw } from 'lucide-react';
import { useApp } from '../context/AppContext';

export default function MobileNav({ categories: propCategories, selectedCategory = 'all', onSelectCategory }) {
  const { language, categories: appCategories } = useApp();
  const location = useLocation();
  const navigate = useNavigate();
  const [filterModalOpen, setFilterModalOpen] = useState(false);

  const categories = propCategories && propCategories.length > 0 ? propCategories : (appCategories || []);

  const isActive = (path, isAnchor = false) => {
    if (isAnchor && (location.pathname === '/' || location.pathname.startsWith('/product'))) return true;
    if (!isAnchor && location.pathname.startsWith(path)) return true;
    return false;
  };

  const handleCategoryClick = (catId) => {
    if (onSelectCategory) {
      onSelectCategory(catId);
    } else {
      const cleanId = String(catId).replace(/^category-/, '');
      const targetUrl = cleanId === 'all' ? '/#products' : `/?category=${cleanId}#products`;
      navigate(targetUrl);
    }
    setFilterModalOpen(false);
  };

  return (
    <>
      {/* Floating Filter Button (Mobile Only) */}
      <div className="fixed bottom-24 right-4 z-40 md:hidden">
        <button
          onClick={() => setFilterModalOpen(true)}
          className="w-14 h-14 rounded-full flex items-center justify-center bg-gradient-to-r from-yellow-400 to-orange-500 hover:from-yellow-500 hover:to-orange-600 text-white shadow-2xl border border-white/20 backdrop-blur-xs transition-transform duration-300 transform active:scale-90"
          title="Filters"
          aria-label="Open Filters"
        >
          <Filter className="w-5 h-5" />
        </button>
      </div>

      {/* Floating Island Mobile Bottom Nav */}
      <nav className="md:hidden fixed bottom-6 left-5 right-5 bg-white/75 backdrop-blur-[30px] saturate-180 border border-white/40 shadow-[0_20px_50px_rgba(0,0,0,0.12)] rounded-[2.5rem] z-40 px-2 overflow-hidden">
        <div className="flex items-center justify-around py-1">
          {/* Products */}
          <Link
            to="/"
            className="relative group flex flex-col items-center justify-center py-3 px-2 min-w-0 flex-1 transition-all"
          >
            {isActive('/', true) && (
              <div className="absolute inset-x-2 top-1.5 bottom-1.5 bg-[#92adc5]/20 rounded-[1.5rem] -z-10 shadow-[inset_0_0_0_1px_rgba(146,173,197,0.2)]" />
            )}
            <Coffee
              className={`w-5 h-5 mb-1 transition-all duration-300 ${
                isActive('/', true) ? 'text-[#92adc5] scale-110' : 'text-gray-400'
              }`}
            />
            <span
              className={`text-[10px] font-bold tracking-wider uppercase text-center ${
                isActive('/', true) ? 'text-[#92adc5]' : 'text-gray-400'
              }`}
            >
              {language === 'km' ? 'ផលិតផល' : 'Product'}
            </span>
          </Link>

          {/* Features */}
          <Link
            to="/features"
            className="relative group flex flex-col items-center justify-center py-3 px-2 min-w-0 flex-1 transition-all"
          >
            {isActive('/features') && (
              <div className="absolute inset-x-2 top-1.5 bottom-1.5 bg-[#92adc5]/20 rounded-[1.5rem] -z-10 shadow-[inset_0_0_0_1px_rgba(146,173,197,0.2)]" />
            )}
            <Zap
              className={`w-5 h-5 mb-1 transition-all duration-300 ${
                isActive('/features') ? 'text-[#92adc5] scale-110' : 'text-gray-400'
              }`}
            />
            <span
              className={`text-[10px] font-bold tracking-wider uppercase text-center ${
                isActive('/features') ? 'text-[#92adc5]' : 'text-gray-400'
              }`}
            >
              {language === 'km' ? 'លក្ខណៈពិសេស' : 'Features'}
            </span>
          </Link>

          {/* Reviews */}
          <Link
            to="/reviews"
            className="relative group flex flex-col items-center justify-center py-3 px-2 min-w-0 flex-1 transition-all"
          >
            {isActive('/reviews') && (
              <div className="absolute inset-x-2 top-1.5 bottom-1.5 bg-[#92adc5]/20 rounded-[1.5rem] -z-10 shadow-[inset_0_0_0_1px_rgba(146,173,197,0.2)]" />
            )}
            <Star
              className={`w-5 h-5 mb-1 transition-all duration-300 ${
                isActive('/reviews') ? 'text-[#92adc5] scale-110' : 'text-gray-400'
              }`}
            />
            <span
              className={`text-[10px] font-bold tracking-wider uppercase text-center ${
                isActive('/reviews') ? 'text-[#92adc5]' : 'text-gray-400'
              }`}
            >
              {language === 'km' ? 'ការពិនិត្យ' : 'Reviews'}
            </span>
          </Link>

          {/* About */}
          <Link
            to="/about"
            className="relative group flex flex-col items-center justify-center py-3 px-2 min-w-0 flex-1 transition-all"
          >
            {isActive('/about') && (
              <div className="absolute inset-x-2 top-1.5 bottom-1.5 bg-[#92adc5]/20 rounded-[1.5rem] -z-10 shadow-[inset_0_0_0_1px_rgba(146,173,197,0.2)]" />
            )}
            <User
              className={`w-5 h-5 mb-1 transition-all duration-300 ${
                isActive('/about') ? 'text-[#92adc5] scale-110' : 'text-gray-400'
              }`}
            />
            <span
              className={`text-[10px] font-bold tracking-wider uppercase text-center ${
                isActive('/about') ? 'text-[#92adc5]' : 'text-gray-400'
              }`}
            >
              {language === 'km' ? 'អំពីយើង' : 'About'}
            </span>
          </Link>
        </div>
      </nav>

      {/* Mobile Categories Bottom Sheet Modal */}
      {filterModalOpen && (
        <div className="fixed inset-0 z-50 md:hidden animate-modal-fade">
          <div
            className="absolute inset-0 bg-black/50 backdrop-blur-xs"
            onClick={() => setFilterModalOpen(false)}
          />
          <div className="absolute bottom-0 left-0 right-0 bg-white rounded-t-3xl max-h-[85vh] overflow-y-auto p-6 space-y-5 animate-modal-slide">
            {/* Drag Handle */}
            <div className="w-12 h-1.5 bg-gray-300 rounded-full mx-auto" />

            <div className="flex justify-between items-center">
              <h3 className="text-xl font-bold text-gray-800 flex items-center gap-2">
                <Filter className="w-5 h-5 text-yellow-500" />
                <span>{language === 'km' ? 'តម្រងស្វែងរក' : 'Filters'}</span>
              </h3>
              <button
                onClick={() => setFilterModalOpen(false)}
                className="p-2 text-gray-400 hover:text-gray-600 rounded-full"
              >
                <X className="w-6 h-6" />
              </button>
            </div>

            {/* Quick Actions Row */}
            <div className="grid grid-cols-2 gap-3">
              <button
                onClick={() => handleCategoryClick('all')}
                className={`p-4 rounded-2xl flex flex-col items-center justify-center transition-all cursor-pointer ${
                  selectedCategory === 'all'
                    ? 'bg-yellow-50 text-black shadow-md'
                    : 'bg-gray-100 text-gray-700'
                }`}
              >
                <Grid className="w-6 h-6 mb-1.5" />
                <span className="font-semibold text-sm">
                  {language === 'km' ? 'ផលិតផលទាំងអស់' : 'All Products'}
                </span>
              </button>

              <button
                onClick={() => handleCategoryClick('all')}
                className="bg-gradient-to-r from-gray-500 to-gray-600 text-white p-4 rounded-2xl flex flex-col items-center justify-center shadow-md cursor-pointer"
              >
                <RotateCcw className="w-6 h-6 mb-1.5" />
                <span className="font-semibold text-sm">
                  {language === 'km' ? 'កំណត់ឡើងវិញ' : 'Reset'}
                </span>
              </button>
            </div>

            {/* Categories List */}
            <div className="bg-gray-50 rounded-2xl p-3 space-y-2">
              {categories.map((category) => {
                const catId = category.base_category_id || category.id;
                const isSelected = selectedCategory === catId;
                return (
                  <button
                    key={category.id || catId}
                    onClick={() => handleCategoryClick(catId)}
                    className={`w-full text-left px-4 py-3 rounded-xl transition-all flex items-center justify-between cursor-pointer ${
                      isSelected
                        ? 'bg-yellow-50 text-black font-bold shadow-xs'
                        : 'hover:bg-white text-gray-700'
                    }`}
                  >
                    <span>{category.name}</span>
                    {category.product_count !== undefined && (
                      <span className="bg-indigo-100 text-indigo-700 px-2.5 py-0.5 rounded-full text-xs font-semibold">
                        {category.product_count}
                      </span>
                    )}
                  </button>
                );
              })}
            </div>
          </div>
        </div>
      )}
    </>
  );
}

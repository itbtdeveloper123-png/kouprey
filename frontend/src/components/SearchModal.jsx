import React, { useState, useEffect } from 'react';
import { Search, X, Star, ArrowRight } from 'lucide-react';
import { useApp } from '../context/AppContext';
import { fetchProducts, getImageUrl } from '../api/client';

export default function SearchModal() {
  const { isSearchOpen, setIsSearchOpen, language, setSelectedProduct, t } = useApp();
  const [searchTerm, setSearchTerm] = useState('');
  const [results, setResults] = useState([]);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    if (!isSearchOpen) {
      setSearchTerm('');
      setResults([]);
      return;
    }
  }, [isSearchOpen]);

  useEffect(() => {
    if (!searchTerm.trim()) {
      setResults([]);
      return;
    }

    const timer = setTimeout(() => {
      setLoading(true);
      fetchProducts({ lang: language, search: searchTerm.trim() }).then((prods) => {
        setResults(prods || []);
        setLoading(false);
      });
    }, 250);

    return () => clearTimeout(timer);
  }, [searchTerm, language]);

  if (!isSearchOpen) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-start justify-center pt-16 md:pt-24 p-4 bg-black/60 backdrop-blur-xs animate-modal-fade">
      <div
        className="relative w-full max-w-2xl bg-white rounded-3xl shadow-2xl overflow-hidden animate-modal-slide"
        onClick={(e) => e.stopPropagation()}
      >
        {/* Search Input Bar */}
        <div className="flex items-center px-6 py-4 border-b border-gray-100 gap-3">
          <Search className="w-5 h-5 text-gray-400 flex-shrink-0" />
          <input
            type="text"
            autoFocus
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            placeholder={t.search_placeholder}
            className="w-full text-base md:text-lg text-gray-800 placeholder-gray-400 bg-transparent focus:outline-hidden"
          />
          {searchTerm && (
            <button
              onClick={() => setSearchTerm('')}
              className="p-1 text-gray-400 hover:text-gray-600 rounded-full"
            >
              <X className="w-4 h-4" />
            </button>
          )}
          <button
            onClick={() => setIsSearchOpen(false)}
            className="p-2 text-gray-400 hover:text-gray-700 rounded-full"
            aria-label="Close search"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Search Results */}
        <div className="max-h-[60vh] overflow-y-auto p-4 space-y-2">
          {loading && (
            <div className="text-center py-10 text-gray-400 text-sm animate-pulse">
              កំពុងស្វែងរក...
            </div>
          )}

          {!loading && searchTerm && results.length === 0 && (
            <div className="text-center py-10 text-gray-500 text-sm">
              {t.no_products_found}
            </div>
          )}

          {!loading && results.map((product) => {
            const price = Number(product.price) || 0;
            const rating = Number(product.avg_rating) || 5.0;

            return (
              <div
                key={product.id}
                onClick={() => {
                  setSelectedProduct(product);
                  setIsSearchOpen(false);
                }}
                className="flex items-center justify-between p-3 rounded-2xl hover:bg-emerald-50/60 cursor-pointer transition-colors group"
              >
                <div className="flex items-center gap-3.5">
                  <div className="w-14 h-14 rounded-xl bg-gray-100 flex items-center justify-center p-1.5 overflow-hidden flex-shrink-0">
                    <img
                      src={getImageUrl(product.image)}
                      alt={product.name}
                      className="w-full h-full object-contain"
                      onError={(e) => {
                        e.target.src = 'https://images.unsplash.com/photo-1541167760496-1628856ab772?auto=format&fit=crop&w=150&q=80';
                      }}
                    />
                  </div>
                  <div>
                    <h4 className="text-sm font-bold text-gray-900 group-hover:text-emerald-700 transition-colors line-clamp-1">
                      {product.name}
                    </h4>
                    <div className="flex items-center gap-2 text-xs text-gray-500 mt-0.5">
                      <span>{product.category_name}</span>
                      <span>•</span>
                      <div className="flex items-center text-amber-500 font-semibold gap-0.5">
                        <Star className="w-3 h-3 fill-amber-400 text-amber-400" />
                        <span>{rating.toFixed(1)}</span>
                      </div>
                    </div>
                  </div>
                </div>

                <div className="flex items-center gap-3 pl-3">
                  <span className="font-extrabold text-emerald-800 text-sm">
                    ${price.toFixed(2)}
                  </span>
                  <ArrowRight className="w-4 h-4 text-gray-400 group-hover:text-emerald-700 group-hover:translate-x-0.5 transition-all" />
                </div>
              </div>
            );
          })}
        </div>
      </div>
    </div>
  );
}

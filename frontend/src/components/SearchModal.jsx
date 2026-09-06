import React, { useState, useEffect, useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import { Search, X, Star, ArrowRight } from 'lucide-react';
import { useApp } from '../context/AppContext';
import { fetchProducts, getImageUrl } from '../api/client';

export default function SearchModal() {
  const { isSearchOpen, setIsSearchOpen, language, t } = useApp();
  const navigate = useNavigate();
  const [searchTerm, setSearchTerm] = useState('');
  const [apiResults, setApiResults] = useState([]);
  const [loading, setLoading] = useState(false);

  // Load any cached products for instantaneous 0ms search
  const cachedPool = useMemo(() => {
    try {
      const pKm = JSON.parse(localStorage.getItem('kouprey_prods_km') || '[]');
      const pEn = JSON.parse(localStorage.getItem('kouprey_prods_en') || '[]');
      const map = new Map();
      [...pKm, ...pEn].forEach((p) => {
        const key = p.base_product_id || p.id;
        if (!map.has(key)) {
          map.set(key, p);
        }
      });
      return Array.from(map.values());
    } catch {
      return [];
    }
  }, [isSearchOpen]);

  useEffect(() => {
    if (!isSearchOpen) {
      setSearchTerm('');
      setApiResults([]);
      return;
    }
  }, [isSearchOpen]);

  // Client-side instant filter across name, category, and description
  const clientFiltered = useMemo(() => {
    const q = searchTerm.trim().toLowerCase();
    if (!q) return [];
    return cachedPool.filter((p) => {
      const name = (p.name || '').toLowerCase();
      const cat = (p.category_name || '').toLowerCase();
      const desc = (p.description || p.short_description || '').toLowerCase();
      return name.includes(q) || cat.includes(q) || desc.includes(q);
    });
  }, [searchTerm, cachedPool]);

  // Server-side debounced search fetch
  useEffect(() => {
    const q = searchTerm.trim();
    if (!q) {
      setApiResults([]);
      return;
    }

    const timer = setTimeout(() => {
      setLoading(true);
      fetchProducts({ lang: language, search: q })
        .then((prods) => {
          setApiResults(Array.isArray(prods) ? prods : []);
          setLoading(false);
        })
        .catch(() => {
          setLoading(false);
        });
    }, 200);

    return () => clearTimeout(timer);
  }, [searchTerm, language]);

  // Merge client and server results without duplicates
  const finalResults = useMemo(() => {
    const map = new Map();
    // Prioritize API results first
    apiResults.forEach((p) => {
      const key = p.base_product_id || p.id;
      map.set(key, p);
    });
    // Add client-filtered matches
    clientFiltered.forEach((p) => {
      const key = p.base_product_id || p.id;
      if (!map.has(key)) {
        map.set(key, p);
      }
    });
    return Array.from(map.values());
  }, [apiResults, clientFiltered]);

  if (!isSearchOpen) return null;

  return (
    <div
      className="fixed inset-0 z-50 flex items-start justify-center pt-16 md:pt-24 p-4 bg-black/60 backdrop-blur-xs animate-modal-fade"
      onClick={() => setIsSearchOpen(false)}
    >
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
              className="p-1 text-gray-400 hover:text-gray-600 rounded-full cursor-pointer"
            >
              <X className="w-4 h-4" />
            </button>
          )}
          <button
            onClick={() => setIsSearchOpen(false)}
            className="p-2 text-gray-400 hover:text-gray-700 rounded-full cursor-pointer"
            aria-label="Close search"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Search Results */}
        <div className="max-h-[60vh] overflow-y-auto p-4 space-y-2">
          {loading && finalResults.length === 0 && (
            <div className="text-center py-10 text-gray-400 text-sm animate-pulse">
              {language === 'km' ? 'កំពុងស្វែងរក...' : 'Searching products...'}
            </div>
          )}

          {!loading && searchTerm && finalResults.length === 0 && (
            <div className="text-center py-10 text-gray-500 text-sm">
              {t.no_products_found}
            </div>
          )}

          {finalResults.map((product) => {
            const price = Number(product.price) || 0;
            const rating = Number(product.avg_rating) || 5.0;
            const prodTargetId = product.base_product_id || product.id;

            return (
              <div
                key={product.id || prodTargetId}
                onClick={() => {
                  setIsSearchOpen(false);
                  navigate(`/product/${prodTargetId}`);
                }}
                className="flex items-center justify-between p-3 rounded-2xl hover:bg-orange-50/60 cursor-pointer transition-colors group"
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
                    <h4 className="text-sm font-bold text-gray-900 group-hover:text-orange-600 transition-colors line-clamp-1">
                      {product.name}
                    </h4>
                    <div className="flex items-center gap-2 text-xs text-gray-500 mt-0.5">
                      {product.category_name && <span>{product.category_name}</span>}
                      {product.category_name && <span>•</span>}
                      <div className="flex items-center text-amber-500 font-semibold gap-0.5">
                        <Star className="w-3 h-3 fill-amber-400 text-amber-400" />
                        <span>{rating.toFixed(1)}</span>
                      </div>
                    </div>
                  </div>
                </div>

                <div className="flex items-center gap-3 pl-3">
                  <span className="font-extrabold text-orange-600 text-sm">
                    ${price.toFixed(2)}
                  </span>
                  <ArrowRight className="w-4 h-4 text-gray-400 group-hover:text-orange-600 group-hover:translate-x-0.5 transition-all" />
                </div>
              </div>
            );
          })}
        </div>
      </div>
    </div>
  );
}

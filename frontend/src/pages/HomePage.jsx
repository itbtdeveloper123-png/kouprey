import React, { useState, useEffect } from 'react';
import HeroBanner from '../components/HeroBanner';
import CategoryFilter from '../components/CategoryFilter';
import ProductCard from '../components/ProductCard';
import { useApp } from '../context/AppContext';
import { fetchProducts } from '../api/client';
import { Coffee, Award, Truck, HeartHandshake } from 'lucide-react';

export default function HomePage() {
  const { language, categories, t } = useApp();
  const [selectedCategory, setSelectedCategory] = useState(null);
  const [products, setProducts] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let isMounted = true;
    setLoading(true);
    fetchProducts({
      lang: language,
      baseCategoryId: selectedCategory
    }).then((prods) => {
      if (!isMounted) return;
      setProducts(prods || []);
      setLoading(false);
    });
    return () => { isMounted = false; };
  }, [language, selectedCategory]);

  return (
    <div className="space-y-8 md:space-y-12">
      {/* Hero Banner Section */}
      <HeroBanner />

      {/* Main Catalog Section */}
      <section id="products-section" className="max-w-6xl mx-auto px-4 md:px-6 space-y-6">
        <div className="flex flex-col md:flex-row md:items-end justify-between gap-4 border-b border-gray-100 pb-4">
          <div>
            <span className="text-xs font-bold text-emerald-700 uppercase tracking-wider">
              {t.products}
            </span>
            <h2 className="text-2xl md:text-3xl font-extrabold text-gray-900 mt-1">
              {language === 'km' ? 'ផលិតផលកាហ្វេ & តែបៃតងពិសេស' : 'Premium Coffee & Matcha'}
            </h2>
          </div>

          {/* Category Filter */}
          <CategoryFilter
            categories={categories}
            selectedCategoryId={selectedCategory}
            onSelectCategory={setSelectedCategory}
          />
        </div>

        {/* Product Grid */}
        {loading ? (
          <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 md:gap-6 py-12">
            {[...Array(8)].map((_, i) => (
              <div key={i} className="bg-gray-100 rounded-2xl h-72 animate-pulse" />
            ))}
          </div>
        ) : products.length === 0 ? (
          <div className="text-center py-20 bg-gray-50 rounded-3xl border border-gray-100 space-y-3">
            <Coffee className="w-12 h-12 text-emerald-700/40 mx-auto" />
            <p className="text-gray-600 font-medium">{t.no_products_found}</p>
          </div>
        ) : (
          <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 md:gap-6">
            {products.map((product) => (
              <ProductCard key={product.id} product={product} />
            ))}
          </div>
        )}
      </section>

      {/* Highlights / Value Proposition Section */}
      <section className="max-w-6xl mx-auto px-4 md:px-6 pt-6">
        <div className="bg-gradient-to-br from-emerald-900 to-emerald-950 text-white rounded-3xl p-8 md:p-12 shadow-xl">
          <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div className="flex items-start gap-4">
              <div className="w-12 h-12 rounded-2xl bg-emerald-800/80 border border-emerald-700/50 flex items-center justify-center flex-shrink-0 text-emerald-300">
                <Award className="w-6 h-6" />
              </div>
              <div>
                <h3 className="font-bold text-lg text-white mb-1">
                  {language === 'km' ? 'គុណភាពស្តង់ដារ ១០០%' : '100% Premium Quality'}
                </h3>
                <p className="text-xs text-emerald-200/80 leading-relaxed">
                  {language === 'km'
                    ? 'ជ្រើសរើសគ្រាប់កាហ្វេ និងស្លឹកតែល្អបំផុត ពីកសិដ្ឋានធម្មជាតិពិតៗ។'
                    : 'Crafted from the finest natural coffee beans and premium matcha leaves.'}
                </p>
              </div>
            </div>

            <div className="flex items-start gap-4">
              <div className="w-12 h-12 rounded-2xl bg-emerald-800/80 border border-emerald-700/50 flex items-center justify-center flex-shrink-0 text-emerald-300">
                <Truck className="w-6 h-6" />
              </div>
              <div>
                <h3 className="font-bold text-lg text-white mb-1">
                  {language === 'km' ? 'ដឹកជញ្ជូនរហ័សទាន់ចិត្ត' : 'Fast Delivery'}
                </h3>
                <p className="text-xs text-emerald-200/80 leading-relaxed">
                  {language === 'km'
                    ? 'សេវាកម្មដឹកជញ្ជូនរហ័សទូទាំងរាជធានីភ្នំពេញ និងតាមបណ្តាខេត្ត។'
                    : 'Fast and reliable delivery service across Phnom Penh and provinces.'}
                </p>
              </div>
            </div>

            <div className="flex items-start gap-4">
              <div className="w-12 h-12 rounded-2xl bg-emerald-800/80 border border-emerald-700/50 flex items-center justify-center flex-shrink-0 text-emerald-300">
                <HeartHandshake className="w-6 h-6" />
              </div>
              <div>
                <h3 className="font-bold text-lg text-white mb-1">
                  {language === 'km' ? 'សេវាកម្មរួសរាយរាក់ទាក់' : 'Friendly Support'}
                </h3>
                <p className="text-xs text-emerald-200/80 leading-relaxed">
                  {language === 'km'
                    ? 'ក្រុមការងារតែងតែត្រៀមខ្លួនជួយប្រឹក្សា និងឆ្លើយតបរាល់ចម្ងល់របស់អ្នក។'
                    : 'Our team is always ready to assist and ensure total customer satisfaction.'}
                </p>
              </div>
            </div>
          </div>
        </div>
      </section>
    </div>
  );
}

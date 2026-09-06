import React from 'react';
import { Award, Leaf, Check, ArrowRight } from 'lucide-react';
import { useApp } from '../context/AppContext';
import { getImageUrl } from '../api/client';

export default function ZigzagSpotlight({ products = [] }) {
  const { language, settings } = useApp();

  // Find products that match powder/syrup/bean or use first products
  const powderProduct = products.find(p => /powder|matcha|tea/i.test(p.name || '')) || products[1] || products[0];
  const beanProduct = products.find(p => /bean|coffee|arabica/i.test(p.name || '')) || products[2] || products[0];

  if (!powderProduct && !beanProduct) return null;

  return (
    <section className="py-12 md:py-16 bg-white space-y-16 md:space-y-24 max-w-7xl mx-auto px-4 md:px-6">
      
      {/* Section 1: Powder / Matcha (Right Image, Left Text on Desktop) */}
      {powderProduct && (
        <div className="flex flex-col md:flex-row-reverse items-center gap-8 md:gap-16 group">
          {/* Image */}
          <div className="w-full md:w-1/2 relative">
            <div className="aspect-[4/3] rounded-3xl relative bg-gray-50 flex items-center justify-center p-6 overflow-hidden">
              <img
                src={getImageUrl(powderProduct.image)}
                alt={powderProduct.name}
                className="w-full h-full object-contain transform transition-transform duration-700 group-hover:scale-105 drop-shadow-xl"
                onError={(e) => {
                  e.target.src = 'https://images.unsplash.com/photo-1576092768241-dec231879fc3?auto=format&fit=crop&w=600&q=80';
                }}
              />
              <div className="absolute bottom-6 right-6 bg-white/95 backdrop-blur-md px-5 py-2.5 rounded-full shadow-lg z-20 pointer-events-none flex items-center gap-2 text-xs font-bold text-amber-900">
                <Award className="w-4 h-4 text-amber-600" />
                <span>Premium Grade</span>
              </div>
            </div>
          </div>

          {/* Text Content */}
          <div className="w-full md:w-1/2 text-center md:text-left md:pl-4">
            <h2 className="text-3xl md:text-4xl font-bold text-gray-900 mb-4 font-freeman">
              {language === 'km' ? 'បណ្តុំម្សៅតែបៃតងពិសេស' : 'Powder & Matcha Selection'}
            </h2>
            <p className="text-gray-600 text-base md:text-lg leading-relaxed mb-6">
              {language === 'km'
                ? 'បង្កើតនូវភេសជ្ជៈដ៏ឈ្ងុយឆ្ងាញ់ និងមានរសជាតិរលូនជាមួយម្សៅតែបៃតងធម្មជាតិរបស់យើង។ ស័ក្តិសមទាំងសម្រាប់ហាងកាហ្វេអាជីព និងការឆុងទទួលទាននៅផ្ទះ។'
                : 'Create smooth, velvety frappes and creamy signature drinks with our premium powders. Designed for professionals and loved by everyone.'}
            </p>

            <ul className="space-y-3 mb-8 text-left max-w-md mx-auto md:mx-0">
              {[
                language === 'km' ? 'ស្លឹកតែធម្មជាតិ ១០០%' : '100% Organic Matcha Leaves',
                language === 'km' ? 'ក្លិនឈ្ងុយដិត និងរសជាតិផ្អែមស្រាល' : 'Rich Aroma & Smooth Texture',
                language === 'km' ? 'សម្បូរដោយសារធាតុប្រឆាំងអុកស៊ីតកម្ម' : 'Rich in Natural Antioxidants'
              ].map((text, i) => (
                <li key={i} className="flex items-center gap-3 text-gray-700 text-sm">
                  <div className="w-6 h-6 rounded-full bg-emerald-50 border border-emerald-200 flex items-center justify-center text-emerald-600 flex-shrink-0">
                    <Check className="w-3.5 h-3.5 stroke-[3]" />
                  </div>
                  <span className="font-medium">{text}</span>
                </li>
              ))}
            </ul>

            <a
              href="#products"
              className="inline-flex items-center gap-2 px-8 py-3.5 bg-gray-900 text-white rounded-full font-bold hover:bg-orange-600 transition-colors shadow-lg text-sm"
            >
              <span>{language === 'km' ? 'ស្វែងរកម្សៅតែបៃតង' : 'Explore Powders'}</span>
              <ArrowRight className="w-4 h-4" />
            </a>
          </div>
        </div>
      )}

      {/* Section 2: Beans / Coffee (Left Image, Right Text on Desktop) */}
      {beanProduct && (
        <div className="flex flex-col md:flex-row items-center gap-8 md:gap-16 group">
          {/* Image */}
          <div className="w-full md:w-1/2 relative">
            <div className="aspect-[4/3] rounded-3xl relative bg-gray-50 flex items-center justify-center p-6 overflow-hidden">
              <img
                src={getImageUrl(beanProduct.image)}
                alt={beanProduct.name}
                className="w-full h-full object-contain transform transition-transform duration-700 group-hover:scale-105 drop-shadow-xl"
                onError={(e) => {
                  e.target.src = 'https://images.unsplash.com/photo-1514432324607-a09d9b4aefdd?auto=format&fit=crop&w=600&q=80';
                }}
              />
              <div className="absolute top-6 left-6 bg-white/95 backdrop-blur-md px-5 py-2.5 rounded-full shadow-lg z-20 pointer-events-none flex items-center gap-2 text-xs font-bold text-orange-600">
                <Leaf className="w-4 h-4" />
                <span>100% Arabica</span>
              </div>
            </div>
          </div>

          {/* Text Content */}
          <div className="w-full md:w-1/2 text-center md:text-left">
            <h2 className="text-3xl md:text-4xl font-bold text-gray-900 mb-4 font-freeman">
              {language === 'km' ? 'គ្រាប់កាហ្វេលំដាប់ពិសេស' : 'Coffee Bean Collection'}
            </h2>
            <p className="text-gray-600 text-base md:text-lg leading-relaxed mb-6">
              {language === 'km'
                ? 'ស្វែងយល់ពីព្រលឹងនៃកាហ្វេពិតប្រាកដជាមួយគ្រាប់កាហ្វេគុណភាពខ្ពស់របស់យើង។ លីងយ៉ាងម៉ត់ចត់ ដើម្បីរក្សាបាននូវក្លិនក្រអូបឈ្ងុយ និងរសជាតិដ៏រស់រវើក។'
                : 'Discover the soul of our coffee with our premium beans. Sourced from the finest altitudes and roasted to perfection for an unforgettable ritual.'}
            </p>

            <div className="flex flex-wrap gap-2.5 justify-center md:justify-start mb-8">
              <span className="px-4 py-2 bg-orange-50 rounded-xl text-xs font-bold text-orange-700 border border-orange-100">
                Freshly Roasted
              </span>
              <span className="px-4 py-2 bg-orange-50 rounded-xl text-xs font-bold text-orange-700 border border-orange-100">
                Artisan Blends
              </span>
              <span className="px-4 py-2 bg-orange-50 rounded-xl text-xs font-bold text-orange-700 border border-orange-100">
                Single Origin
              </span>
            </div>

            <a
              href="#products"
              className="inline-flex items-center gap-2 px-8 py-3.5 bg-gray-900 text-white rounded-full font-bold hover:bg-orange-600 transition-colors shadow-lg text-sm"
            >
              <span>{language === 'km' ? 'ស្វែងរកគ្រាប់កាហ្វេ' : 'Explore Beans'}</span>
              <ArrowRight className="w-4 h-4" />
            </a>
          </div>
        </div>
      )}

    </section>
  );
}

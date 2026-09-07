import React from 'react';
import { Link } from 'react-router-dom';
import { Award, Droplets, Check, ArrowRight } from 'lucide-react';
import { Swiper, SwiperSlide } from 'swiper/react';
import { Autoplay, Pagination, EffectCreative } from 'swiper/modules';
import { useApp } from '../context/AppContext';
import { getImageUrl } from '../api/client';

import 'swiper/css';
import 'swiper/css/pagination';
import 'swiper/css/effect-creative';

export default function ZigzagSpotlight({ products = [], onSelectCategory }) {
  const { language, settings } = useApp();

  // 1. Resolve Category Products
  // Syrups (base_category_id: 19 or name matches Syrup)
  let syrupProducts = products.filter(
    p => p.base_category_id == 19 || /syrup|ស៊ីរ៉ូ|សុីរ៉ូ/i.test(p.name || '')
  );

  // Powders (base_category_id: 13 or name matches Powder/Matcha)
  let powderProducts = products.filter(
    p => p.base_category_id == 13 || /powder|matcha|ម្សៅ/i.test(p.name || '')
  );

  // Helper to ensure enough slides for infinite swiper loop
  const ensureLoopBuffer = (arr, min = 4) => {
    if (arr.length === 0) return [];
    let res = [...arr];
    while (res.length < min) {
      res = [...res, ...arr];
    }
    return res;
  };

  const bufferedSyrups = ensureLoopBuffer(syrupProducts);
  const bufferedPowders = ensureLoopBuffer(powderProducts);

  const handleExplore = (catId) => {
    if (onSelectCategory) {
      onSelectCategory(catId);
    }
    const elem = document.getElementById('products');
    if (elem) {
      elem.scrollIntoView({ behavior: 'smooth' });
    }
  };

  const creativeEffectConfig = {
    perspective: 1000,
    prev: {
      shadow: false,
      translate: [0, '110%', -300],
      rotate: [20, 0, 0],
      scale: 0.6,
      opacity: 0,
    },
    next: {
      translate: [0, '110%', -300],
      rotate: [20, 0, 0],
      scale: 0.6,
      opacity: 0,
    },
  };

  return (
    <section className="py-16 md:py-24 bg-white relative overflow-hidden">
      <div className="max-w-7xl mx-auto px-4 md:px-6 relative z-10">
        
        {/* Section Header */}
        <div className="text-center mb-16">
          <span className="text-orange-600 font-bold tracking-wider uppercase text-xs md:text-sm mb-2 block">
            {language === 'km' ? 'បណ្តុំផលិតផលសម្រិតសម្រាំង' : 'Curated Collections'}
          </span>
          <h3 className="text-3xl md:text-5xl font-bold text-gray-900 font-freeman">
            {language === 'km' ? 'ស្វែងយល់អំពីផលិតផលពិសេសៗរបស់យើង' : 'Discover Our Specialties'}
          </h3>
        </div>

        <div className="flex flex-col gap-16 md:gap-32">
          
          {/* 1. SYRUP COLLECTION (Left Carousel, Right Text) */}
          {bufferedSyrups.length > 0 && (
            <div className="flex flex-col md:flex-row items-center gap-8 md:gap-16 group">
              {/* Swiper Column */}
              <div className="w-full md:w-1/2 relative">
                <div className="aspect-[4/3] rounded-3xl relative bg-gradient-to-br from-amber-50/50 to-orange-50/30 border border-orange-100/40 p-4 overflow-hidden">
                  <Swiper
                    modules={[Autoplay, Pagination, EffectCreative]}
                    effect="creative"
                    creativeEffect={creativeEffectConfig}
                    loop={bufferedSyrups.length > 1}
                    speed={900}
                    grabCursor={true}
                    simulateTouch={true}
                    autoplay={{
                      delay: 4000,
                      disableOnInteraction: false,
                      pauseOnMouseEnter: true,
                    }}
                    pagination={{
                      clickable: true,
                      dynamicBullets: true,
                    }}
                    className="category-swiper-syrup h-full w-full"
                  >
                    {bufferedSyrups.map((p, idx) => (
                      <SwiperSlide key={`${p.id}-${idx}`} className="cursor-pointer pb-12 flex items-center justify-center">
                        <Link to={`/product/${p.base_product_id || p.id}`} className="w-full h-full flex items-center justify-center relative">
                          <img
                            src={getImageUrl(p.image)}
                            alt={p.name}
                            className="w-full h-full object-contain object-center transform transition-transform duration-700 group-hover:scale-105 p-3 md:p-6 drop-shadow-xl"
                            style={{ filter: 'drop-shadow(0 15px 25px rgba(0,0,0,0.15))' }}
                          />
                          <div className="absolute bottom-14 left-0 right-0 text-center opacity-0 group-hover:opacity-100 transition-opacity z-20">
                            <span className="bg-black/75 backdrop-blur-xs text-white px-4 py-1.5 rounded-full text-xs md:text-sm font-bold shadow-lg whitespace-nowrap">
                              {p.name}
                            </span>
                          </div>
                        </Link>
                      </SwiperSlide>
                    ))}
                  </Swiper>

                  {/* Floating Top Badge */}
                  <div className="absolute top-6 left-6 bg-white/95 backdrop-blur-md px-5 py-2.5 rounded-full shadow-lg z-20 pointer-events-none flex items-center gap-2">
                    <Droplets className="w-4 h-4 text-orange-600" />
                    <span className="text-orange-600 font-bold text-xs">Top Quality</span>
                  </div>
                </div>
              </div>

              {/* Text Column */}
              <div className="w-full md:w-1/2 text-center md:text-left">
                <h2 className="text-3xl md:text-4xl font-bold text-gray-900 mb-6 font-freeman">
                  {settings.syrup_collection_title || (language === 'km' ? 'បណ្តុំសុីរ៉ូសម្រិតសម្រាំង' : 'Syrup Collection')}
                </h2>
                <p className="text-gray-600 text-base md:text-lg leading-relaxed mb-8">
                  {settings.syrup_collection_description || (language === 'km'
                    ? 'បង្កើនឱជារសភេសជ្ជៈរបស់អ្នកជាមួយសុីរ៉ូធម្មជាតិដ៏ឈ្ងុយឆ្ងាញ់របស់យើង។ បង្កើតឡើងយ៉ាងផ្ចិតផ្ចង់ ដើម្បីនាំមកនូវភាពស្រស់បំព្រងដល់គ្រឿងបន្ថែមរស់ជាតិ ស្រាក្រឡុក និងបង្អែមគ្រប់មុខ។'
                    : 'Enhance your beverages with our rich, flavorful syrups. Crafted for perfection, our collection brings a new dimension of taste to your coffee, cocktails, and desserts.')}
                </p>

                <ul className="space-y-4 mb-8 text-left max-w-md mx-auto md:mx-0">
                  {[
                    language === 'km' ? 'គ្រឿងផ្សំធម្មជាតិ និងរសជាតិពិតៗ' : 'Natural ingredients & authentic flavors',
                    language === 'km' ? 'កំហាប់ល្អឥតខ្ចោះងាយស្រួលក្នុងការក្រឡុក' : 'Perfect consistency for mixing',
                    language === 'km' ? 'ជម្រើសរសជាតិសម្បូរបែបទាំងក្លាសិក និងផ្លែឈើស្រស់' : 'Wide variety of classic & exotic options'
                  ].map((text, i) => (
                    <li key={i} className="flex items-start gap-3 text-gray-700">
                      <div className="flex-shrink-0 w-6 h-6 rounded-full bg-emerald-50 border border-emerald-200 flex items-center justify-center text-emerald-600 text-[10px] mt-0.5 shadow-xs">
                        <Check className="w-3.5 h-3.5 stroke-[3]" />
                      </div>
                      <span className="text-sm font-medium">{text}</span>
                    </li>
                  ))}
                </ul>

                <button
                  onClick={() => handleExplore('19')}
                  className="px-8 py-3.5 bg-gray-900 text-white rounded-full font-bold hover:bg-orange-600 transition-colors shadow-lg flex items-center gap-2 mx-auto md:mx-0 cursor-pointer text-sm"
                >
                  <span>{settings.explore_syrups || (language === 'km' ? 'ស្វែងរកសុីរ៉ូ' : 'Explore Syrups')}</span>
                  <ArrowRight className="w-4 h-4" />
                </button>
              </div>
            </div>
          )}

          {/* 2. POWDER SELECTION (Right Carousel, Left Text) */}
          {bufferedPowders.length > 0 && (
            <div className="flex flex-col md:flex-row-reverse items-center gap-8 md:gap-16 group">
              {/* Swiper Column */}
              <div className="w-full md:w-1/2 relative">
                <div className="aspect-[4/3] rounded-3xl relative bg-gradient-to-bl from-green-50/50 to-emerald-50/30 border border-emerald-100/40 p-4 overflow-hidden">
                  <Swiper
                    modules={[Autoplay, Pagination, EffectCreative]}
                    effect="creative"
                    creativeEffect={creativeEffectConfig}
                    loop={bufferedPowders.length > 1}
                    speed={900}
                    grabCursor={true}
                    simulateTouch={true}
                    autoplay={{
                      delay: 4500,
                      disableOnInteraction: false,
                      pauseOnMouseEnter: true,
                    }}
                    pagination={{
                      clickable: true,
                      dynamicBullets: true,
                    }}
                    className="category-swiper-powder h-full w-full"
                  >
                    {bufferedPowders.map((p, idx) => (
                      <SwiperSlide key={`${p.id}-${idx}`} className="cursor-pointer pb-12 flex items-center justify-center">
                        <Link to={`/product/${p.base_product_id || p.id}`} className="w-full h-full flex items-center justify-center relative">
                          <img
                            src={getImageUrl(p.image)}
                            alt={p.name}
                            className="w-full h-full object-contain object-center transform transition-transform duration-700 group-hover:scale-105 p-3 md:p-6 drop-shadow-xl"
                            style={{ filter: 'drop-shadow(0 15px 25px rgba(0,0,0,0.15))' }}
                          />
                          <div className="absolute bottom-14 left-0 right-0 text-center opacity-0 group-hover:opacity-100 transition-opacity z-20">
                            <span className="bg-black/75 backdrop-blur-xs text-white px-4 py-1.5 rounded-full text-xs md:text-sm font-bold shadow-lg whitespace-nowrap">
                              {p.name}
                            </span>
                          </div>
                        </Link>
                      </SwiperSlide>
                    ))}
                  </Swiper>

                  {/* Floating Bottom Badge */}
                  <div className="absolute bottom-6 right-6 bg-white/95 backdrop-blur-md px-5 py-2.5 rounded-full shadow-lg z-20 pointer-events-none flex items-center gap-2">
                    <Award className="w-4 h-4 text-amber-700" />
                    <span className="text-amber-800 font-bold text-xs">Premium Grade</span>
                  </div>
                </div>
              </div>

              {/* Text Column */}
              <div className="w-full md:w-1/2 text-center md:text-left md:pl-4">
                <h2 className="text-3xl md:text-4xl font-bold text-gray-900 mb-6 font-freeman">
                  {settings.powder_selection_title || (language === 'km' ? 'បណ្តុំម្សៅគុណភាពខ្ពស់' : 'Powder Selection')}
                </h2>
                <p className="text-gray-600 text-base md:text-lg leading-relaxed mb-8">
                  {settings.powder_selection_description || (language === 'km'
                    ? 'បង្កើតនូវភេសជ្ជៈដ៏ឈ្ងុយឆ្ងាញ់ និងមានរសជាតិរលូនជាមួយម្សៅតែបៃតង និងគ្រីមម៉ាធម្មជាតិរបស់យើង។ ស័ក្តិសមទាំងសម្រាប់ហាងគ្រឿងបន្ថែមរស់ជាតិអាជីព និងការឆុងទទួលទាននៅផ្ទះ។'
                    : 'Create smooth, velvety frappes and creamy signature drinks with our premium powders. Designed for professionals, loved by everyone.')}
                </p>

                <ul className="space-y-4 mb-8 text-left max-w-md mx-auto md:mx-0">
                  {[
                    language === 'km' ? 'ម្សៅម៉ាត់ឆាធម្មជាតិ 5A សម្បូរសារធាតុប្រឆាំងអុកស៊ីតកម្ម' : 'Natural Matcha 5A rich in antioxidants',
                    language === 'km' ? 'ម្សៅគ្រីមឈីសទន់ម៉ដ្ឋ ផ្តល់នូវរសជាតិឈ្ងុយឆ្ងាញ់' : 'Velvety cream cheese powder with rich aroma',
                    language === 'km' ? 'ងាយស្រួលរលាយ មិនកកដុំ សន្សំសំចៃពេលវេលា' : 'Easily dissolves without clumping, fast preparation'
                  ].map((text, i) => (
                    <li key={i} className="flex items-start gap-3 text-gray-700">
                      <div className="flex-shrink-0 w-6 h-6 rounded-full bg-emerald-50 border border-emerald-200 flex items-center justify-center text-emerald-600 text-[10px] mt-0.5 shadow-xs">
                        <Check className="w-3.5 h-3.5 stroke-[3]" />
                      </div>
                      <span className="text-sm font-medium">{text}</span>
                    </li>
                  ))}
                </ul>

                <button
                  onClick={() => handleExplore('13')}
                  className="px-8 py-3.5 bg-gray-900 text-white rounded-full font-bold hover:bg-orange-600 transition-colors shadow-lg flex items-center gap-2 mx-auto md:mx-0 cursor-pointer text-sm"
                >
                  <span>{settings.explore_powders || (language === 'km' ? 'ស្វែងរកម្សៅ' : 'Explore Powders')}</span>
                  <ArrowRight className="w-4 h-4" />
                </button>
              </div>
            </div>
          )}

        </div>
      </div>
    </section>
  );
}

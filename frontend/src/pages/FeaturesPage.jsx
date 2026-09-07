import React, { useState, useEffect } from 'react';
import { useApp } from '../context/AppContext';
import { fetchFeatures } from '../api/client';
import { 
  Sparkles, 
  Leaf, 
  Flame, 
  Award, 
  Coffee, 
  CheckCircle2, 
  Globe2, 
  Heart,
  X,
  ChevronRight
} from 'lucide-react';

export default function FeaturesPage() {
  const { language, settings } = useApp();
  const [features, setFeatures] = useState([]);
  const [loading, setLoading] = useState(true);
  const [selectedFeature, setSelectedFeature] = useState(null);

  // Default features from settings if DB list is empty
  const defaultFeatures = [
    {
      title: settings?.feature_organic_title || (language === 'km' ? '100% សរីរាង្គ' : '100% Organic'),
      description: settings?.feature_organic_desc || (language === 'km' ? 'យើងប្រមូលគ្រាប់ពីចំការដែលមានវិញ្ញាបនបត្រសរីរាង្គ។' : 'Carefully sourced from certified organic farms.')
    },
    {
      title: settings?.feature_low_acid_title || (language === 'km' ? 'ជម្រើសទាបកម្ម៉ាស៊ីត' : 'Low Acid Options'),
      description: settings?.feature_low_acid_desc || (language === 'km' ? 'ដុតសម្រាប់ក្រពះរសើប និងរសជាតិទន់ល្មើយ។' : 'Specially roasted for sensitive stomachs and smooth finish.')
    },
    {
      title: settings?.feature_sustainable_title || (language === 'km' ? 'ការវេចខ្ចប់ប្រកបដោយចីរភាព' : 'Sustainable Packaging'),
      description: settings?.feature_sustainable_desc || (language === 'km' ? 'សម្ភារៈមិត្តភាពបរិស្ថាន និងកាត់បន្ថយសំរាមអប្បបរមា។' : 'Eco-friendly materials that respect the planet.')
    },
    {
      title: settings?.feature_small_batch_title || (language === 'km' ? 'ដុតតូចចំនួន' : 'Small Batch Roasting'),
      description: settings?.feature_small_batch_desc || (language === 'km' ? 'ដុតដែលត្រូវគ្រប់គ្រងសម្រាប់ភាពស៊ីសង់នៃរសជាតិ។' : 'Artisan batches for optimal freshness and control.')
    },
    {
      title: settings?.feature_direct_trade_title || (language === 'km' ? 'ពាណិជ្ជកម្មដោយផ្ទាល់' : 'Direct Trade'),
      description: settings?.feature_direct_trade_desc || (language === 'km' ? 'យើងបង់តម្លៃយុត្តិធម៌ដោយផ្ទាល់ទៅកសិករ។' : 'Fair compensation directly empowering coffee farmers.')
    },
    {
      title: settings?.feature_flavor_title || (language === 'km' ? 'ពូជរសជាតិសម្បូរបែប' : 'Flavor Diversity'),
      description: settings?.feature_flavor_desc || (language === 'km' ? 'ពីកំណត់ព្រៃទៅកូឡាតេ រុករកជួរផលិតផលរបស់យើង។' : 'Rich profiles from dark roasts to ceremonial matcha.')
    }
  ];

  useEffect(() => {
    let isMounted = true;
    setLoading(true);
    fetchFeatures(language).then((feats) => {
      if (!isMounted) return;
      if (feats && feats.length > 0) {
        setFeatures(feats);
      } else {
        setFeatures(defaultFeatures);
      }
      setLoading(false);
    });
    return () => { isMounted = false; };
  }, [language, settings]);

  const displayFeatures = features.length > 0 ? features : defaultFeatures;

  const ICONS = [Leaf, Flame, Award, Coffee, CheckCircle2, Globe2, Heart, Sparkles];
  const GRADIENTS = [
    'from-green-400 to-green-600',
    'from-orange-400 to-red-500',
    'from-blue-400 to-blue-600',
    'from-yellow-400 to-yellow-600',
    'from-purple-400 to-purple-600',
    'from-teal-400 to-teal-600'
  ];

  return (
    <div className="min-h-screen bg-white pb-24 pt-24 md:pt-32">
      <main className="max-w-6xl mx-auto px-4 md:px-6 py-6 md:py-10">
        
        {/* Header matching features.php */}
        <div className="text-center mb-16 md:mb-24 space-y-4">
          <h1 className="text-4xl md:text-6xl font-bold bg-gradient-to-r from-gray-900 to-gray-600 bg-clip-text text-transparent font-freeman">
            {settings?.features_title || (language === 'km' ? 'លក្ខណៈពិសេស' : 'Features')}
          </h1>
          <div className="w-24 h-1.5 bg-gradient-to-r from-orange-400 to-orange-600 mx-auto rounded-full" />
          <p className="text-gray-500 text-lg md:text-xl max-w-2xl mx-auto leading-relaxed">
            {settings?.features_description || (language === 'km'
              ? 'ស្វែងរកអ្វីដែលធ្វើឱ្យគ្រឿងបន្ថែមរស់ជាតិរបស់យើងពិសេស — ចាប់ពីការស្វែងរក ដល់ការដុត និងការវេចខ្ចប់។'
              : 'Discover what makes our coffee and matcha unique — from origin to roasting and sustainability.')}
          </p>
        </div>

        {/* Alternating Vertical Timeline Section */}
        <div className="relative">
          {/* Center Vertical Timeline Bar */}
          <div className="absolute left-8 md:left-1/2 top-0 bottom-0 w-1 bg-gradient-to-b from-yellow-200 via-orange-200 to-transparent transform md:-translate-x-1/2 rounded-full z-0" />

          {/* Steps Loop */}
          <div className="relative z-10 space-y-12 md:space-y-0">
            {displayFeatures.map((feature, idx) => {
              const IconComponent = ICONS[idx % ICONS.length];
              const gradientClass = GRADIENTS[idx % GRADIENTS.length];
              const isEven = idx % 2 === 0;
              const stepNum = String(idx + 1).padStart(2, '0');

              return (
                <div
                  key={feature.id || idx}
                  className={`relative flex flex-col md:flex-row items-center ${
                    isEven ? 'md:flex-row-reverse' : ''
                  } group w-full md:mb-24 last:mb-0`}
                >
                  {/* Desktop Spacer */}
                  <div className="hidden md:block w-1/2" />

                  {/* Center Node / Number */}
                  <div className="absolute left-8 md:left-1/2 transform -translate-x-1/2 flex items-center justify-center">
                    <div className="w-16 h-16 rounded-full bg-white border-4 border-gray-50 shadow-xl z-20 flex items-center justify-center group-hover:scale-110 transition-transform duration-300 relative cursor-pointer">
                      <div className={`absolute inset-0 rounded-full bg-gradient-to-br ${gradientClass} opacity-20 animate-pulse`} />
                      <span className="font-freeman text-xl font-bold text-gray-800 relative z-10">
                        {stepNum}
                      </span>
                    </div>
                  </div>

                  {/* Content Card */}
                  <div className={`ml-20 md:ml-0 md:w-1/2 ${isEven ? 'md:pr-16 md:text-right' : 'md:pl-16 md:text-left'} w-[calc(100%-5rem)] md:w-1/2`}>
                    <div
                      onClick={() => setSelectedFeature(feature)}
                      className="bg-white rounded-3xl p-6 md:p-8 shadow-md hover:shadow-xl transition-all duration-300 border border-gray-100 relative overflow-hidden group-hover:-translate-y-1 cursor-pointer"
                    >
                      {/* Decorative Background Blob */}
                      <div className={`absolute top-0 ${isEven ? 'right-0' : 'right-0 md:left-0'} w-32 h-32 bg-gradient-to-br ${gradientClass} opacity-10 rounded-full blur-2xl -mt-10 -mr-10`} />

                      <div className="relative z-10">
                        <div className={`flex items-center gap-3 mb-4 ${isEven ? 'md:justify-end' : 'md:justify-start'}`}>
                          <div className={`w-10 h-10 rounded-xl bg-gradient-to-br ${gradientClass} flex items-center justify-center text-white shadow-md flex-shrink-0`}>
                            <IconComponent className="w-5 h-5" />
                          </div>
                          <h3 className="text-xl font-bold text-gray-800 group-hover:text-yellow-600 transition-colors font-freeman">
                            {feature.title}
                          </h3>
                        </div>

                        <p className="text-gray-600 leading-relaxed text-sm md:text-base mb-4">
                          {feature.description}
                        </p>

                        <div className={`flex items-center text-xs font-bold text-orange-500 gap-1 group-hover:translate-x-1 transition-transform ${isEven ? 'md:justify-end' : 'md:justify-start'}`}>
                          <span>{settings?.explore_more || (language === 'km' ? 'ស្វែងយល់បន្ថែម' : 'Learn More')}</span>
                          <ChevronRight className="w-3.5 h-3.5" />
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              );
            })}
          </div>
        </div>

        {/* Feature Detail Modal */}
        {selectedFeature && (
          <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm animate-fade-in">
            <div className="bg-white rounded-3xl max-w-lg w-full p-8 shadow-2xl relative animate-modal-slide">
              <button
                onClick={() => setSelectedFeature(null)}
                className="absolute top-6 right-6 w-9 h-9 rounded-full bg-gray-100 hover:bg-gray-200 text-gray-600 flex items-center justify-center transition-colors cursor-pointer"
              >
                <X className="w-5 h-5" />
              </button>

              <div className="space-y-4">
                <div className="w-14 h-14 rounded-2xl bg-gradient-to-br from-orange-400 to-orange-600 text-white flex items-center justify-center shadow-lg">
                  <Sparkles className="w-7 h-7" />
                </div>
                <h3 className="text-2xl font-bold text-gray-900 font-freeman">
                  {selectedFeature.title}
                </h3>
                <p className="text-base text-gray-600 leading-relaxed whitespace-pre-line">
                  {selectedFeature.description}
                </p>
                <div className="pt-4 border-t border-gray-100">
                  <button
                    onClick={() => setSelectedFeature(null)}
                    className="w-full py-3 bg-gray-900 hover:bg-black text-white font-bold rounded-2xl transition-all"
                  >
                    {language === 'km' ? 'បិទ' : 'Close'}
                  </button>
                </div>
              </div>
            </div>
          </div>
        )}
      </main>
    </div>
  );
}

import React, { useState, useEffect } from 'react';
import { useApp } from '../context/AppContext';
import { fetchPageContent, getImageUrl } from '../api/client';
import { Sparkles, CheckCircle2 } from 'lucide-react';

export default function FeaturesPage() {
  const { language, t } = useApp();
  const [features, setFeatures] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let isMounted = true;
    setLoading(true);
    fetchPageContent('features', language).then((res) => {
      if (!isMounted) return;
      if (res && res.success) {
        setFeatures(res.features || []);
      }
      setLoading(false);
    });
    return () => { isMounted = false; };
  }, [language]);

  return (
    <div className="max-w-6xl mx-auto px-4 md:px-6 py-8 space-y-12">
      {/* Header */}
      <div className="text-center max-w-2xl mx-auto space-y-3">
        <div className="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-emerald-50 text-emerald-700 text-xs font-bold">
          <Sparkles className="w-4 h-4 text-emerald-600" />
          <span>{t.features}</span>
        </div>
        <h1 className="text-3xl md:text-5xl font-extrabold text-gray-900 tracking-tight">
          {language === 'km' ? 'លក្ខណៈពិសេសនៃ KouPrey' : 'Why Choose KouPrey'}
        </h1>
        <p className="text-sm md:text-base text-gray-600 leading-relaxed">
          {language === 'km'
            ? 'ស្វែងយល់ពីចំណុចពិសេស និងស្តង់ដារគុណភាពខ្ពស់ ដែលធ្វើឱ្យផលិតផលរបស់យើងទទួលបានការគាំទ្រយ៉ាងខ្លាំង។'
            : 'Discover the distinct qualities and uncompromising standards that make our coffee and matcha exceptional.'}
        </p>
      </div>

      {/* Features Grid */}
      {loading ? (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          {[...Array(6)].map((_, i) => (
            <div key={i} className="bg-gray-100 rounded-3xl h-64 animate-pulse" />
          ))}
        </div>
      ) : features.length === 0 ? (
        <div className="grid grid-cols-1 md:grid-cols-3 gap-8 py-10">
          <div className="bg-white p-8 rounded-3xl border border-gray-100 shadow-md space-y-4">
            <div className="w-12 h-12 rounded-2xl bg-emerald-100 text-emerald-800 flex items-center justify-center font-bold text-xl">
              01
            </div>
            <h3 className="font-bold text-xl text-gray-900">
              {language === 'km' ? 'ធម្មជាតិ ១០០%' : '100% Organic'}
            </h3>
            <p className="text-xs text-gray-600 leading-relaxed">
              ផលិតផលគ្មានជាតិគីមី មិនបន្ថែមសារធាតុរក្សាទុកយូរ ធានាសុខភាពល្អដល់អ្នកទទួលទាន។
            </p>
          </div>
          <div className="bg-white p-8 rounded-3xl border border-gray-100 shadow-md space-y-4">
            <div className="w-12 h-12 rounded-2xl bg-emerald-100 text-emerald-800 flex items-center justify-center font-bold text-xl">
              02
            </div>
            <h3 className="font-bold text-xl text-gray-900">
              {language === 'km' ? 'រសជាតិដើមពិតៗ' : 'Authentic Taste'}
            </h3>
            <p className="text-xs text-gray-600 leading-relaxed">
              រូបមន្តពិសេសប្រកបដោយតុល្យភាពរវាងក្លិនឈ្ងុយ និងរសជាតិដិតជាប់មាត់មិនអាចបំភ្លេចបាន។
            </p>
          </div>
          <div className="bg-white p-8 rounded-3xl border border-gray-100 shadow-md space-y-4">
            <div className="w-12 h-12 rounded-2xl bg-emerald-100 text-emerald-800 flex items-center justify-center font-bold text-xl">
              03
            </div>
            <h3 className="font-bold text-xl text-gray-900">
              {language === 'km' ? 'ស្តង់ដារអនាម័យខ្ពស់' : 'Hygienic Standards'}
            </h3>
            <p className="text-xs text-gray-600 leading-relaxed">
              ផលិត និងវេចខ្ចប់តាមស្តង់ដារអន្តរជាតិ ដើម្បីរក្សាភាពស្រស់ និងគុណភាពរហូតដល់ដៃអ្នក។
            </p>
          </div>
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          {features.map((f, idx) => (
            <div
              key={f.id || idx}
              className="bg-white rounded-3xl border border-gray-100 p-6 md:p-8 shadow-xs hover:shadow-xl transition-all flex flex-col justify-between space-y-4 group hover:-translate-y-1"
            >
              {f.image && (
                <div className="h-44 w-full rounded-2xl overflow-hidden bg-gray-50 flex items-center justify-center">
                  <img
                    src={getImageUrl(f.image)}
                    alt={f.title}
                    className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                    onError={(e) => { e.target.style.display = 'none'; }}
                  />
                </div>
              )}
              <div className="space-y-2">
                <div className="flex items-center gap-2">
                  <CheckCircle2 className="w-5 h-5 text-emerald-600 flex-shrink-0" />
                  <h3 className="font-bold text-lg md:text-xl text-gray-900 group-hover:text-emerald-700 transition-colors">
                    {f.title}
                  </h3>
                </div>
                <p className="text-xs text-gray-600 leading-relaxed">
                  {f.description}
                </p>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

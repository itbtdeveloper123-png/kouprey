import React, { useState, useEffect } from 'react';
import { useApp } from '../context/AppContext';
import { fetchPageContent, getImageUrl } from '../api/client';
import { Heart, Coffee, Users, Target, Award } from 'lucide-react';

export default function AboutPage() {
  const { language, settings, t } = useApp();
  const [about, setAbout] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let isMounted = true;
    setLoading(true);
    fetchPageContent('about', language).then((res) => {
      if (!isMounted) return;
      if (res && res.success && res.about) {
        setAbout(res.about);
      }
      setLoading(false);
    });
    return () => { isMounted = false; };
  }, [language]);

  return (
    <div className="max-w-6xl mx-auto px-4 md:px-6 py-8 space-y-16">
      {/* Hero / Title */}
      <div className="text-center max-w-2xl mx-auto space-y-3">
        <div className="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-emerald-50 text-emerald-700 text-xs font-bold">
          <Heart className="w-4 h-4 text-emerald-600" />
          <span>{t.about}</span>
        </div>
        <h1 className="text-3xl md:text-5xl font-extrabold text-gray-900 tracking-tight">
          {language === 'km' ? 'អំពីយើងខ្ញុំ - KouPrey' : 'About KouPrey'}
        </h1>
        <p className="text-sm md:text-base text-gray-600 leading-relaxed">
          {language === 'km'
            ? 'ដំណើរដើមទង និងគោលបំណងចម្បងក្នុងការនាំយកកាហ្វេ និងតែបៃតងធម្មជាតិគុណភាពល្អបំផុតជូនអតិថិជន។'
            : 'Our journey and mission to bring the authentic flavors of quality coffee and matcha to everyone.'}
        </p>
      </div>

      {/* Main Story Card */}
      <div className="bg-white rounded-3xl border border-gray-100 shadow-xl overflow-hidden grid grid-cols-1 md:grid-cols-2">
        <div className="p-8 md:p-12 space-y-6 flex flex-col justify-center">
          <span className="text-xs font-bold text-emerald-700 uppercase tracking-wider">
            {language === 'km' ? 'ដំណើររឿងរបស់យើង' : 'Our Story'}
          </span>
          <h2 className="text-2xl md:text-3xl font-extrabold text-gray-900 leading-snug">
            {about?.title || (language === 'km' ? 'រសជាតិពិតពីធម្មជាតិ បង្កើតឡើងដោយក្តីស្រឡាញ់' : 'True Natural Taste, Made with Passion')}
          </h2>
          <div className="text-sm text-gray-600 leading-relaxed space-y-4">
            {about?.content ? (
              <div dangerouslySetInnerHTML={{ __html: about.content }} />
            ) : (
              <>
                <p>
                  KouPrey ត្រូវបានបង្កើតឡើងដោយក្តីស្រឡាញ់ និងការប្តេជ្ញាចិត្តខ្ពស់ក្នុងការស្វែងរកគ្រាប់កាហ្វេ និងតែបៃតងធម្មជាតិដែលមានគុណភាពល្អឥតខ្ចោះ។ យើងជឿជាក់ថាកែវកាហ្វេមួយកែវមិនគ្រាន់តែជាភេសជ្ជៈនោះទេ ប៉ុន្តែជាប្រភពនៃកម្លាំងចិត្ត និងភាពរីករាយ។
                </p>
                <p>
                  តាមរយៈការជ្រើសរើសវត្ថុធាតុដើមយ៉ាងផ្ចិតផ្ចង់ និងការកែច្នៃប្រកបដោយអនាម័យខ្ពស់ ផលិតផល KouPrey នីមួយៗធានាបាននូវរសជាតិដិតជាប់មាត់ និងផ្តល់អត្ថប្រយោជន៍ល្អបំផុតសម្រាប់សុខភាព។
                </p>
              </>
            )}
          </div>
        </div>

        <div className="bg-emerald-900 text-white p-8 md:p-12 flex flex-col justify-center space-y-6">
          <h3 className="text-xl font-bold text-emerald-200">
            {language === 'km' ? 'គុណតម្លៃស្នូលរបស់យើង' : 'Our Core Values'}
          </h3>
          <div className="space-y-4">
            <div className="flex items-start gap-4">
              <div className="w-10 h-10 rounded-xl bg-emerald-800 flex items-center justify-center flex-shrink-0 text-emerald-300">
                <Target className="w-5 h-5" />
              </div>
              <div>
                <h4 className="font-bold text-sm text-white">គុណភាពជាចម្បង (Quality First)</h4>
                <p className="text-xs text-emerald-100/80 leading-relaxed">
                  មិនដែលសម្របសម្រួលលើគុណភាពនៃវត្ថុធាតុដើម និងស្តង់ដារផលិតឡើយ។
                </p>
              </div>
            </div>

            <div className="flex items-start gap-4">
              <div className="w-10 h-10 rounded-xl bg-emerald-800 flex items-center justify-center flex-shrink-0 text-emerald-300">
                <Users className="w-5 h-5" />
              </div>
              <div>
                <h4 className="font-bold text-sm text-white">អតិថិជនជាបេះដូង (Customer-Centric)</h4>
                <p className="text-xs text-emerald-100/80 leading-relaxed">
                  ផ្តល់សេវាកម្មដ៏កក់ក្តៅ និងស្តាប់រាល់មតិយោបល់របស់អតិថិជនគ្រប់ពេលវេលា។
                </p>
              </div>
            </div>

            <div className="flex items-start gap-4">
              <div className="w-10 h-10 rounded-xl bg-emerald-800 flex items-center justify-center flex-shrink-0 text-emerald-300">
                <Award className="w-5 h-5" />
              </div>
              <div>
                <h4 className="font-bold text-sm text-white">ការទទួលខុសត្រូវ (Integrity & Trust)</h4>
                <p className="text-xs text-emerald-100/80 leading-relaxed">
                  ស្មោះត្រង់ចំពោះតម្លៃ សុខភាពរបស់អ្នកទទួលទាន និងបរិស្ថានធម្មជាតិ។
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

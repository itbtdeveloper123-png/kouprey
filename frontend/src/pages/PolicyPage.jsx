import React, { useState, useEffect } from 'react';
import { useLocation, Link } from 'react-router-dom';
import { useApp } from '../context/AppContext';
import { fetchPageContent } from '../api/client';
import { Shield, Scale, ArrowLeft, Check, Calendar, Info, Database, Cog, Lock } from 'lucide-react';

export default function PolicyPage() {
  const { language, settings } = useApp();
  const location = useLocation();
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);

  // Check if current route is terms or privacy
  const isTerms = location.pathname.includes('terms');
  const pageType = isTerms ? 'terms_of_service' : 'privacy_policy';

  useEffect(() => {
    let isMounted = true;
    setLoading(true);

    // If already in settings from bootstrap, use it immediately
    const settingContent = isTerms
      ? (settings?.terms_of_service || settings?.terms_of_service_content)
      : (settings?.privacy_policy || settings?.privacy_policy_content);

    const settingTitle = isTerms
      ? (settings?.terms_of_service_title || (language === 'km' ? 'លក្ខខណ្ឌប្រើប្រាស់' : 'Terms of Service'))
      : (settings?.privacy_policy_title || (language === 'km' ? 'គោលការណ៍ឯកជនភាព' : 'Privacy Policy'));

    if (settingContent) {
      setData({ success: true, title: settingTitle, content: settingContent });
      setLoading(false);
    }

    // Also fetch fresh from API
    fetchPageContent(pageType, language).then((res) => {
      if (!isMounted) return;
      if (res && res.success && res.content) {
        setData(res);
      }
      setLoading(false);
    });

    return () => { isMounted = false; };
  }, [pageType, language, settings]);

  const pageTitle = isTerms
    ? (data?.title || settings?.terms_of_service_title || (language === 'km' ? 'លក្ខខណ្ឌប្រើប្រាស់' : 'Terms of Service'))
    : (data?.title || settings?.privacy_policy_title || (language === 'km' ? 'គោលការណ៍ឯកជនភាព' : 'Privacy Policy'));

  const pageDesc = isTerms
    ? (settings?.terms_of_service_desc || (language === 'km' ? 'សូមអានលក្ខខណ្ឌនៃសេវាកម្មទាំងនេះដោយប្រុងប្រយ័ត្នមុនពេលប្រើប្រាស់សេវាកម្មរបស់យើង។' : 'Please read these terms and conditions carefully before using our services.'))
    : (settings?.privacy_policy_desc || (language === 'km' ? 'យើងគោរពភាពឯកជនរបស់អ្នក និងប្តេជ្ញាការពារព័ត៌មានផ្ទាល់ខ្លួនរបស់អ្នក។' : 'We respect your privacy and are committed to protecting your personal information.'));

  // Fix image paths in rich text so they load through live URL if relative
  const processHtml = (html) => {
    if (!html) return '';
    return html
      .replace(/src="\/kouprey\/public\//g, 'src="https://www.kouprey.asia/kouprey/public/')
      .replace(/src='\/kouprey\/public\//g, "src='https://www.kouprey.asia/kouprey/public/")
      .replace(/src="\/assets\//g, 'src="https://www.kouprey.asia/kouprey/public/assets/')
      .replace(/src='\/assets\//g, "src='https://www.kouprey.asia/kouprey/public/assets/");
  };

  const rawContent = data?.content || (isTerms ? settings?.terms_of_service : settings?.privacy_policy);
  const cleanHtml = processHtml(rawContent);

  return (
    <div className="min-h-screen bg-white pb-24 pt-24 md:pt-32">
      <main className="max-w-4xl mx-auto px-4 md:px-6 py-6 md:py-12 space-y-12">
        
        {/* Header matching privacy_policy.php / terms_of_service.php */}
        <div className="text-center mb-12 space-y-4">
          <div className={`inline-flex items-center justify-center w-20 h-20 rounded-full mx-auto shadow-sm ${
            isTerms ? 'bg-amber-100 text-amber-600' : 'bg-blue-100 text-blue-600'
          }`}>
            {isTerms ? <Scale className="w-10 h-10" /> : <Shield className="w-10 h-10" />}
          </div>

          <h1 className="text-3xl md:text-5xl font-extrabold text-gray-900 leading-tight font-freeman">
            {pageTitle}
          </h1>

          <p className="text-gray-500 text-base md:text-lg max-w-2xl mx-auto leading-relaxed">
            {pageDesc}
          </p>

          <div className={`w-20 h-1.5 mx-auto rounded-full bg-gradient-to-r ${
            isTerms ? 'from-orange-400 to-amber-500' : 'from-blue-400 to-purple-500'
          }`} />
        </div>

        {/* Content Box */}
        <div className="bg-white rounded-3xl border border-gray-100 shadow-xl p-6 sm:p-10 md:p-14">
          {loading && !cleanHtml ? (
            <div className="space-y-4 py-8 animate-pulse">
              <div className="h-5 bg-gray-100 rounded-md w-3/4" />
              <div className="h-5 bg-gray-100 rounded-md w-full" />
              <div className="h-5 bg-gray-100 rounded-md w-5/6" />
              <div className="h-32 bg-gray-100 rounded-2xl w-full mt-4" />
            </div>
          ) : cleanHtml ? (
            /* Dynamic Content from Settings / Database */
            <div
              className="content-section dynamic-policy-section prose prose-blue max-w-none text-gray-800 leading-relaxed space-y-4"
              dangerouslySetInnerHTML={{ __html: cleanHtml }}
            />
          ) : (
            /* Default structured layout matching PHP fallback */
            <div className="space-y-8">
              <div className="text-center mb-8">
                <span className="inline-block bg-blue-50 text-blue-700 px-5 py-2 rounded-full text-sm font-semibold border border-blue-100">
                  <Calendar className="w-4 h-4 inline mr-2" />
                  <span>
                    {language === 'km' ? 'ចូលជាធរមាន៖ ថ្ងៃទី ០១ ខែមករា ឆ្នាំ២០២៦' : 'Effective Date: January 01, 2026'}
                  </span>
                </span>
              </div>

              {/* Section 1 */}
              <div className="border-l-4 border-blue-500 pl-6 space-y-3">
                <h2 className="text-xl font-bold text-gray-900 flex items-center gap-2">
                  <span className="w-8 h-8 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-sm font-bold">
                    <Info className="w-4 h-4" />
                  </span>
                  <span>{language === 'km' ? '១. សេចក្តីផ្តើម' : '1. Introduction'}</span>
                </h2>
                <p className="text-gray-600 leading-relaxed">
                  {language === 'km'
                    ? 'សូមស្វាគមន៍មកកាន់ KouPrey។ គោលការណ៍នេះពន្យល់ពីរបៀបដែលយើងប្រមូល ប្រើប្រាស់ រក្សាទុក និងការពារព័ត៌មានរបស់អ្នក នៅពេលដែលអ្នកចូលមើលគេហទំព័រ ឬប្រើប្រាស់សេវាកម្មរបស់យើង។'
                    : 'Welcome to KouPrey. This policy explains how we handle, process, and protect your information when using our services.'}
                </p>
              </div>

              {/* Section 2 */}
              <div className="border-l-4 border-indigo-500 pl-6 space-y-3">
                <h2 className="text-xl font-bold text-gray-900 flex items-center gap-2">
                  <span className="w-8 h-8 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center text-sm font-bold">
                    <Database className="w-4 h-4" />
                  </span>
                  <span>{language === 'km' ? '២. ព័ត៌មានដែលយើងប្រមូល' : '2. Information We Collect'}</span>
                </h2>
                <ul className="space-y-2 text-gray-600 text-sm">
                  <li className="flex items-start gap-2">
                    <Check className="w-4 h-4 text-emerald-500 mt-0.5 flex-shrink-0" />
                    <span><strong>{language === 'km' ? 'ព័ត៌មានផ្ទាល់ខ្លួន៖' : 'Personal Info:'}</strong> {language === 'km' ? 'ឈ្មោះ លេខទូរស័ព្ទ និងអាសយដ្ឋានដឹកជញ្ជូន។' : ' Name, phone number, and delivery address.'}</span>
                  </li>
                  <li className="flex items-start gap-2">
                    <Check className="w-4 h-4 text-emerald-500 mt-0.5 flex-shrink-0" />
                    <span><strong>{language === 'km' ? 'ព័ត៌មានការបញ្ជាទិញ៖' : 'Order Info:'}</strong> {language === 'km' ? 'បញ្ជីទំនិញ និងព័ត៌មានលម្អិតអំពីការទូទាត់។' : ' Item list and payment details.'}</span>
                  </li>
                </ul>
              </div>

              {/* Section 3 */}
              <div className="border-l-4 border-emerald-500 pl-6 space-y-3">
                <h2 className="text-xl font-bold text-gray-900 flex items-center gap-2">
                  <span className="w-8 h-8 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center text-sm font-bold">
                    <Lock className="w-4 h-4" />
                  </span>
                  <span>{language === 'km' ? '៣. ការការពារសុវត្ថិភាព' : '3. Security Protection'}</span>
                </h2>
                <p className="text-gray-600 leading-relaxed">
                  {language === 'km'
                    ? 'យើងអនុវត្តវិធានការសុវត្ថិភាពត្រឹមត្រូវដើម្បីការពារព័ត៌មានផ្ទាល់ខ្លួនរបស់អ្នកពីការចូលប្រើ ការផ្លាស់ប្តូរ ឬការបង្ហាញដោយគ្មានការអនុញ្ញាត។'
                    : 'We implement rigorous security standards to safeguard your data against unauthorized access.'}
                </p>
              </div>
            </div>
          )}

          {/* Back Link */}
          <div className="pt-8 mt-10 border-t border-gray-100">
            <Link
              to="/?page=1&category=all"
              className="inline-flex items-center gap-2 text-sm font-bold text-gray-700 hover:text-orange-600 transition-colors"
            >
              <ArrowLeft className="w-4 h-4" />
              <span>{language === 'km' ? 'ត្រឡប់ទៅទំព័រដើម' : 'Back to Home'}</span>
            </Link>
          </div>
        </div>
      </main>
    </div>
  );
}

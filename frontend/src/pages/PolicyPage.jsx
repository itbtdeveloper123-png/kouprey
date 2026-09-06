import React, { useState, useEffect } from 'react';
import { useLocation, Link } from 'react-router-dom';
import { useApp } from '../context/AppContext';
import { fetchPageContent } from '../api/client';
import { ShieldCheck, FileText, ArrowLeft } from 'lucide-react';

export default function PolicyPage({ type = 'privacy_policy' }) {
  const { language, t } = useApp();
  const location = useLocation();
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);

  // Determine type based on path if not passed
  const pageType = location.pathname.includes('terms') ? 'terms_of_service' : 'privacy_policy';

  useEffect(() => {
    let isMounted = true;
    setLoading(true);
    fetchPageContent(pageType, language).then((res) => {
      if (!isMounted) return;
      if (res && res.success) {
        setData(res);
      }
      setLoading(false);
    });
    return () => { isMounted = false; };
  }, [pageType, language]);

  const isPrivacy = pageType === 'privacy_policy';
  const defaultTitle = isPrivacy
    ? (language === 'km' ? 'គោលការណ៍ភាពឯកជន' : 'Privacy Policy')
    : (language === 'km' ? 'លក្ខខណ្ឌនៃការប្រើប្រាស់' : 'Terms of Service');

  return (
    <div className="max-w-4xl mx-auto px-4 md:px-6 py-8 space-y-8">
      {/* Breadcrumb */}
      <div className="flex items-center gap-2 text-xs font-semibold text-gray-500">
        <Link to="/" className="hover:text-emerald-700 transition-colors">
          {t.home}
        </Link>
        <span>/</span>
        <span className="text-emerald-800">{data?.title || defaultTitle}</span>
      </div>

      {/* Main Content Box */}
      <div className="bg-white rounded-3xl border border-gray-100 shadow-xl p-8 md:p-12 space-y-6">
        <div className="flex items-center gap-3 border-b border-gray-100 pb-5">
          <div className="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-700 flex items-center justify-center flex-shrink-0">
            {isPrivacy ? <ShieldCheck className="w-6 h-6" /> : <FileText className="w-6 h-6" />}
          </div>
          <div>
            <h1 className="text-2xl md:text-3xl font-extrabold text-gray-900">
              {data?.title || defaultTitle}
            </h1>
            <p className="text-xs text-gray-400 mt-0.5">
              ធ្វើបច្ចុប្បន្នភាពចុងក្រោយ៖ ឆ្នាំ ២០២៦
            </p>
          </div>
        </div>

        {loading ? (
          <div className="space-y-4 py-8">
            <div className="h-4 bg-gray-100 rounded-md w-3/4 animate-pulse" />
            <div className="h-4 bg-gray-100 rounded-md w-full animate-pulse" />
            <div className="h-4 bg-gray-100 rounded-md w-5/6 animate-pulse" />
          </div>
        ) : (
          <div className="prose prose-emerald max-w-none text-sm text-gray-700 leading-relaxed space-y-4">
            {data?.content ? (
              <div dangerouslySetInnerHTML={{ __html: data.content }} />
            ) : (
              <div className="space-y-4">
                <p>
                  សូមស្វាគមន៍មកកាន់គេហទំព័ររបស់យើងខ្ញុំ។ គោលការណ៍នេះត្រូវបានរៀបចំឡើងដើម្បីការពារសិទ្ធិ និងផលប្រយោជន៍របស់អ្នកប្រើប្រាស់ទាំងអស់។
                </p>
                <h3 className="font-bold text-base text-gray-900 pt-2">១. ការប្រមូលទិន្នន័យ</h3>
                <p>
                  យើងប្រមូលព័ត៌មានចាំបាច់មួយចំនួនដូចជា ឈ្មោះ លេខទូរស័ព្ទ និងអាសយដ្ឋានដឹកជញ្ជូន នៅពេលដែលអ្នកធ្វើការបញ្ជាទិញ ដើម្បីធានាបាននូវការដឹកជញ្ជូនទាន់ពេលវេលា និងត្រឹមត្រូវ។
                </p>
                <h3 className="font-bold text-base text-gray-900 pt-2">២. សុវត្ថិភាពទិន្នន័យ</h3>
                <p>
                  ទិន្នន័យផ្ទាល់ខ្លួនរបស់អ្នកត្រូវបានរក្សាទុកដោយសុវត្ថិភាពខ្ពស់ និងមិនត្រូវបានចែករំលែកទៅកាន់ភាគីទីបីដោយគ្មានការអនុញ្ញាតឡើយ។
                </p>
              </div>
            )}
          </div>
        )}

        <div className="pt-6 border-t border-gray-100">
          <Link
            to="/"
            className="inline-flex items-center gap-2 text-sm font-bold text-emerald-700 hover:text-emerald-800 transition-colors"
          >
            <ArrowLeft className="w-4 h-4" />
            <span>ត្រឡប់ទៅទំព័រដើម</span>
          </Link>
        </div>
      </div>
    </div>
  );
}

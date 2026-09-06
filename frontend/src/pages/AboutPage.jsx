import React, { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { useApp } from '../context/AppContext';
import { fetchPageContent, getImageUrl } from '../api/client';
import { Coffee, ArrowRight, Heart, Target, Sparkles } from 'lucide-react';

export default function AboutPage() {
  const { language, settings } = useApp();
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

  const aboutTitle = settings?.about_title || (language === 'km' ? 'អំពី កូព្រៃ កាហ្វេ (KouPrey)' : 'About KouPrey Coffee');
  const aboutContent = settings?.about_content || (language === 'km'
    ? 'នេះជាការចាប់ផ្តើមនៃ KouPrey។ បន្ទាប់ពីបានភ្លក់រសជាតិកាហ្វេដ៏បរិសុទ្ធបំផុតនៅលើជម្រាលភ្នំ យើងបានប្រឹងប្រែងស្វែងរកអ្វីដែលស្រដៀងគ្នានេះនៅពេលត្រឡប់មកវិញ — ដូច្នេះហើយយើងបានសម្រេចចិត្តបង្កើតវាដោយខ្លួនឯង។'
    : 'This is how KouPrey was born. Having experienced the cleanest, purest coffee on a mountainside in Peru, we struggled to find something like it after coming home — so we made it ourselves.');

  const exploreButtonText = settings?.about_explore_button || (language === 'km' ? 'ស្វែងរក' : 'Explore More');

  const purposeTitle = settings?.about_purpose_title || (language === 'km' ? 'គោលបំណងរបស់យើង' : 'Our Purpose');
  const purposeContent = settings?.about_purpose_content || (language === 'km'
    ? 'នៅ KouPrey យើងធ្វើអ្វីៗខុសពីគេ — ដោយមានគោលបំណង។ គោលដៅរបស់យើងសាមញ្ញ៖ ធ្វើឱ្យកាហ្វេសរីរាង្គ មានសុខភាព និងឆ្ងាញ់អាចរកបានសម្រាប់មនុស្សជាច្រើនតាមដែលអាចធ្វើទៅបាន។ យើងប្តេជ្ញាចិត្តផ្តល់កាហ្វេដែលល្អជាងសម្រាប់អ្នក សហគមន៍ និងភពផែនដីរបស់យើង។'
    : 'At KouPrey we do things differently — with purpose. Our goal is simple: make 100% organic, healthy and delicious coffee accessible to as many people as possible. We are committed to delivering coffee that is better for you, the community, and our planet.');

  const storyTitle = settings?.about_story_title || settings?.about_mission_title || (language === 'km' ? 'រឿងរ៉ាវរបស់យើង' : 'Our Story');
  const storyContent = settings?.about_story_content || settings?.about_mission_content || (language === 'km'
    ? 'យើងចាប់ផ្តើមដោយស្រឡាញ់កាហ្វេស្អាត និងបំណងចង់ចែករំលែកវា។ ក្នុងរយៈពេលជាច្រើនឆ្នាំ យើងបានសហការជាមួយអ្នកផ្តល់ បង្កើតការដុតរបស់យើង និងពង្រីកជួររបស់យើង — ទាំងអស់នេះខណៈពេលដែលរក្សាគុណភាព និងចីរភាពនៅខ្លឹមសារនៃអ្វីៗទាំងអស់ដែលយើងធ្វើ។'
    : 'We began with a love for clean coffee and a desire to share it. Over the years we have partnered with growers, refined our roasting, and expanded our blends — all while keeping quality and sustainability at the center of everything we do.');

  const heroImage = about?.hero_image
    ? getImageUrl(about.hero_image)
    : 'https://images.unsplash.com/photo-1498804103079-a6351b050096?auto=format&fit=crop&w=800&q=80';

  const personImage = about?.person_image
    ? getImageUrl(about.person_image)
    : 'https://images.unsplash.com/photo-1514432324607-a09d9b4aefdd?auto=format&fit=crop&w=800&q=80';

  return (
    <div className="min-h-screen bg-white pb-24 pt-24 md:pt-32">
      <main className="max-w-6xl mx-auto px-4 md:px-6 py-6 md:py-12 space-y-20">
        
        {/* Section 1: Hero Story matching about.php */}
        <section className="grid grid-cols-1 md:grid-cols-2 gap-10 lg:gap-16 items-center">
          <div className="space-y-6">
            <span className="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full bg-orange-50 text-orange-600 text-xs font-bold uppercase tracking-wider border border-orange-100">
              <Coffee className="w-3.5 h-3.5" />
              <span>{language === 'km' ? 'ដំណើរដើមទង' : 'Our Origins'}</span>
            </span>

            <h1 className="text-3xl md:text-5xl font-extrabold text-gray-900 leading-tight font-freeman">
              {aboutTitle}
            </h1>

            <p className="text-gray-600 leading-relaxed text-base md:text-lg whitespace-pre-line">
              {aboutContent}
            </p>

            {exploreButtonText && (
              <div>
                <a
                  href="#purpose"
                  className="inline-flex items-center gap-2 bg-yellow-400 hover:bg-yellow-500 text-gray-900 font-bold px-7 py-3.5 rounded-2xl shadow-md transition-all active:scale-98"
                >
                  <span>{exploreButtonText}</span>
                  <ArrowRight className="w-4 h-4" />
                </a>
              </div>
            )}
          </div>

          <div className="flex justify-center">
            <div className="relative w-full max-w-lg aspect-4/3 rounded-3xl overflow-hidden shadow-2xl border-4 border-white">
              <img
                src={heroImage}
                alt="About KouPrey Coffee"
                className="w-full h-full object-cover"
                onError={(e) => {
                  e.target.src = 'https://images.unsplash.com/photo-1498804103079-a6351b050096?auto=format&fit=crop&w=800&q=80';
                }}
              />
              <div className="absolute inset-0 bg-gradient-to-t from-black/30 via-transparent to-transparent" />
            </div>
          </div>
        </section>

        {/* Section 2: Purpose matching #purpose in about.php */}
        <section id="purpose" className="grid grid-cols-1 md:grid-cols-2 gap-10 lg:gap-16 items-center pt-8">
          <div className="order-2 md:order-1 flex justify-center">
            <div className="relative w-full max-w-lg aspect-4/3 rounded-3xl overflow-hidden shadow-2xl border-4 border-white">
              <img
                src={personImage}
                alt="KouPrey Purpose"
                className="w-full h-full object-cover"
                onError={(e) => {
                  e.target.src = 'https://images.unsplash.com/photo-1514432324607-a09d9b4aefdd?auto=format&fit=crop&w=800&q=80';
                }}
              />
            </div>
          </div>

          <div className="order-1 md:order-2 space-y-6">
            <span className="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full bg-emerald-50 text-emerald-700 text-xs font-bold uppercase tracking-wider border border-emerald-100">
              <Target className="w-3.5 h-3.5" />
              <span>{purposeTitle}</span>
            </span>

            <h2 className="text-2xl md:text-4xl font-bold text-gray-900 leading-tight font-freeman">
              {purposeTitle}
            </h2>

            <p className="text-gray-600 leading-relaxed text-base md:text-lg whitespace-pre-line">
              {purposeContent}
            </p>

            {exploreButtonText && (
              <div>
                <a
                  href="#mission"
                  className="inline-flex items-center gap-2 bg-yellow-400 hover:bg-yellow-500 text-gray-900 font-bold px-7 py-3.5 rounded-2xl shadow-md transition-all active:scale-98"
                >
                  <span>{exploreButtonText}</span>
                  <ArrowRight className="w-4 h-4" />
                </a>
              </div>
            )}
          </div>
        </section>

        {/* Section 3: Mission / Story Statement matching about.php */}
        <section id="mission" className="bg-gradient-to-br from-amber-50/60 to-orange-50/40 rounded-3xl p-8 md:p-14 border border-orange-100 shadow-xs space-y-4">
          <span className="inline-flex items-center gap-1.5 px-3.5 py-1 rounded-full bg-orange-100 text-orange-700 text-xs font-bold uppercase tracking-wider">
            <Sparkles className="w-3.5 h-3.5" />
            <span>{storyTitle}</span>
          </span>

          <h3 className="text-2xl md:text-3xl font-bold text-gray-900 font-freeman">
            {storyTitle}
          </h3>

          <p className="text-gray-600 leading-relaxed text-base md:text-lg whitespace-pre-line max-w-4xl">
            {storyContent}
          </p>
        </section>
      </main>
    </div>
  );
}

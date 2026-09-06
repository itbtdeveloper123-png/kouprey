import React, { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { useApp } from '../context/AppContext';
import { fetchPageContent, getImageUrl } from '../api/client';
import { 
  Coffee, 
  ArrowRight, 
  Target, 
  Sparkles, 
  ShieldCheck, 
  Leaf, 
  Globe2, 
  Flame, 
  Award,
  Users,
  Star,
  Clock
} from 'lucide-react';

export default function AboutPage() {
  const { language, settings } = useApp();
  const [about, setAbout] = useState(null);
  const [loading, setLoading] = useState(true);

  const isEn = language === 'en';

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

  // Clean strings based on current language
  const aboutTitle = isEn
    ? (settings?.about_title && !/[\u1780-\u17FF]/.test(settings.about_title) ? settings.about_title : 'About KouPrey Coffee')
    : (settings?.about_title || 'អំពី កូព្រៃ កាហ្វេ (KouPrey)');

  const aboutContent = isEn
    ? (settings?.about_content && !/[\u1780-\u17FF]/.test(settings.about_content)
        ? settings.about_content
        : 'This is how KouPrey was born. Having experienced the cleanest, purest coffee on a mountainside in Peru, we struggled to find something like it after coming home — so we made it ourselves with unmatched passion and craft.')
    : (settings?.about_content ||
        'នេះជាការចាប់ផ្តើមនៃ KouPrey។ បន្ទាប់ពីបានភ្លក់រសជាតិកាហ្វេដ៏បរិសុទ្ធបំផុតនៅលើជម្រាលភ្នំ យើងបានប្រឹងប្រែងស្វែងរកអ្វីដែលស្រដៀងគ្នានេះនៅពេលត្រឡប់មកវិញ — ដូច្នេះហើយយើងបានសម្រេចចិត្តបង្កើតវាដោយខ្លួនឯង។');

  const exploreButtonText = isEn
    ? (settings?.about_explore_button && !/[\u1780-\u17FF]/.test(settings.about_explore_button) ? settings.about_explore_button : 'Explore Our Story')
    : (settings?.about_explore_button || 'ស្វែងយល់បន្ថែម');

  const purposeTitle = isEn
    ? (settings?.about_purpose_title && !/[\u1780-\u17FF]/.test(settings.about_purpose_title) ? settings.about_purpose_title : 'Our Purpose')
    : (settings?.about_purpose_title || 'គោលបំណងរបស់យើង');

  const purposeContent = isEn
    ? (settings?.about_purpose_content && !/[\u1780-\u17FF]/.test(settings.about_purpose_content)
        ? settings.about_purpose_content
        : 'At KouPrey we do things differently — with purpose. Our goal is simple: make 100% organic, healthy, and delicious coffee accessible to as many people as possible. We are committed to delivering coffee that is better for you, the community, and our planet.')
    : (settings?.about_purpose_content ||
        'នៅ KouPrey យើងធ្វើអ្វីៗខុសពីគេ — ដោយមានគោលបំណង។ គោលដៅរបស់យើងសាមញ្ញ៖ ធ្វើឱ្យកាហ្វេសរីរាង្គ មានសុខភាព និងឆ្ងាញ់អាចរកបានសម្រាប់មនុស្សជាច្រើនតាមដែលអាចធ្វើទៅបាន។ យើងប្តេជ្ញាចិត្តផ្តល់កាហ្វេដែលល្អជាងសម្រាប់អ្នក សហគមន៍ និងភពផែនដីរបស់យើង។');

  const storyTitle = isEn
    ? (settings?.about_mission_title && !/[\u1780-\u17FF]/.test(settings.about_mission_title) ? settings.about_mission_title : 'Our Story & Mission')
    : (settings?.about_mission_title || settings?.about_story_title || 'រឿងរ៉ាវរបស់យើង');

  const storyContent = isEn
    ? (settings?.about_mission_content && !/[\u1780-\u17FF]/.test(settings.about_mission_content)
        ? settings.about_mission_content
        : 'We began with a love for clean coffee and a desire to share it. Over the years we have partnered with growers, refined our roasting, and expanded our blends — all while keeping quality and sustainability at the center of everything we do.')
    : (settings?.about_mission_content || settings?.about_story_content ||
        'យើងចាប់ផ្តើមដោយស្រឡាញ់កាហ្វេស្អាត និងបំណងចង់ចែករំលែកវា។ ក្នុងរយៈពេលជាច្រើនឆ្នាំ យើងបានសហការជាមួយអ្នកផ្តល់ បង្កើតការដុតរបស់យើង និងពង្រីកជួររបស់យើង — ទាំងអស់នេះខណៈពេលដែលរក្សាគុណភាព និងចីរភាពនៅខ្លឹមសារនៃអ្វីៗទាំងអស់ដែលយើងធ្វើ។');

  const heroImage = about?.hero_image
    ? getImageUrl(about.hero_image)
    : 'https://images.unsplash.com/photo-1498804103079-a6351b050096?auto=format&fit=crop&w=800&q=80';

  const personImage = about?.person_image
    ? getImageUrl(about.person_image)
    : 'https://images.unsplash.com/photo-1514432324607-a09d9b4aefdd?auto=format&fit=crop&w=800&q=80';

  // Core Pillars
  const pillars = [
    {
      icon: Leaf,
      title: isEn ? '100% Organic Sourcing' : 'ប្រភពសរីរាង្គ ១០០%',
      desc: isEn ? 'Handpicked from pesticide-free, high-altitude mountain farms.' : 'ប្រមូលផលពីចំការភ្នំខ្ពស់ធម្មជាតិ គ្មានសារធាតុគីមី។'
    },
    {
      icon: Globe2,
      title: isEn ? 'Fair & Direct Trade' : 'ពាណិជ្ជកម្មដោយផ្ទាល់',
      desc: isEn ? 'Empowering smallholder farmers with fair and sustainable wages.' : 'គាំទ្រកសិករក្នុងស្រុកដោយផ្ទាល់ជាមួយនឹងតម្លៃយុត្តិធម៌។'
    },
    {
      icon: ShieldCheck,
      title: isEn ? 'Sustainable Packaging' : 'ការវេចខ្ចប់ប្រកបដោយចីរភាព',
      desc: isEn ? 'Eco-friendly, biodegradable materials protecting our planet.' : 'សម្ភារៈមិត្តភាពបរិស្ថាន ងាយរលាយ និងកាត់បន្ថយសំរាម។'
    }
  ];

  // Core Values Grid
  const values = [
    {
      icon: Flame,
      title: isEn ? 'Artisan Roasting' : 'ការដុតកាហ្វេបែបសិល្បៈ',
      desc: isEn ? 'Roasted in small, precise batches to lock in every delicate note.' : 'ដុតក្នុងបរិមាណតូចៗយ៉ាងផ្ចិតផ្ចង់ ដើម្បីរក្សាក្លិន និងរសជាតិដិតជាប់។'
    },
    {
      icon: Award,
      title: isEn ? 'Gold Standard Quality' : 'គុណភាពស្តង់ដារមាស',
      desc: isEn ? 'Rigorous quality checks from green bean selection to final cup.' : 'ត្រួតពិនិត្យយ៉ាងហ្មត់ចត់ ចាប់ពីគ្រាប់ឆៅរហូតដល់ពែងកាហ្វេរបស់អ្នក។'
    },
    {
      icon: Users,
      title: isEn ? 'Community First' : 'សហគមន៍ជាចម្បង',
      desc: isEn ? 'Bringing coffee enthusiasts and passionate baristas together.' : 'ភ្ជាប់ទំនាក់ទំនងអ្នកស្រឡាញ់កាហ្វេ និងបារីស្តាប្រកបដោយក្តីស្រឡាញ់។'
    },
    {
      icon: Clock,
      title: isEn ? 'Peak Freshness' : 'ភាពស្រស់ថ្មីជានិច្ច',
      desc: isEn ? 'Sealed right after roasting to ensure supreme aroma in every brew.' : 'វេចខ្ចប់ភ្លាមៗក្រោយការដុត ដើម្បីធានាក្លិនឈ្ងុយស្រស់គ្រប់ពេលវេលា។'
    }
  ];

  return (
    <div className="min-h-screen bg-white pb-24 pt-24 md:pt-32">
      <main className="max-w-6xl mx-auto px-4 md:px-6 py-6 md:py-12 space-y-24">
        
        {/* Section 1: Hero Story */}
        <section className="grid grid-cols-1 md:grid-cols-2 gap-10 lg:gap-16 items-center">
          <div className="space-y-6">
            <span className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-orange-50 text-orange-600 text-xs font-bold uppercase tracking-wider border border-orange-100/80 shadow-xs">
              <span className="w-2 h-2 rounded-full bg-orange-500 animate-ping" />
              <Coffee className="w-3.5 h-3.5" />
              <span>{isEn ? 'Our Origins' : 'ដំណើរដើមទង'}</span>
            </span>

            <h1 className="text-3xl md:text-5xl lg:text-6xl font-extrabold text-gray-900 leading-tight font-freeman">
              {aboutTitle}
            </h1>

            <p className="text-gray-600 leading-relaxed text-base md:text-lg whitespace-pre-line">
              {aboutContent}
            </p>

            <div className="flex flex-wrap items-center gap-4 pt-2">
              <a
                href="#purpose"
                className="inline-flex items-center gap-2 bg-gradient-to-r from-yellow-400 to-orange-500 hover:from-yellow-500 hover:to-orange-600 text-gray-950 font-bold px-7 py-3.5 rounded-2xl shadow-lg shadow-orange-500/20 transition-all active:scale-95 text-sm"
              >
                <span>{exploreButtonText}</span>
                <ArrowRight className="w-4 h-4" />
              </a>

              <Link
                to="/#products"
                className="inline-flex items-center gap-2 bg-gray-50 hover:bg-gray-100 text-gray-800 font-bold px-6 py-3.5 rounded-2xl border border-gray-200/80 transition-all active:scale-95 text-sm"
              >
                <span>{isEn ? 'View Products' : 'មើលផលិតផល'}</span>
              </Link>
            </div>
          </div>

          <div className="flex justify-center">
            <div className="relative w-full max-w-lg aspect-4/3 rounded-3xl overflow-hidden shadow-2xl border-4 border-white group">
              <img
                src={heroImage}
                alt={aboutTitle}
                className="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105"
                onError={(e) => {
                  e.target.src = 'https://images.unsplash.com/photo-1498804103079-a6351b050096?auto=format&fit=crop&w=800&q=80';
                }}
              />
              <div className="absolute inset-0 bg-gradient-to-t from-black/40 via-transparent to-transparent" />

              {/* Floating Badge */}
              <div className="absolute bottom-4 left-4 right-4 bg-white/90 backdrop-blur-md p-4 rounded-2xl border border-white/60 shadow-lg flex items-center justify-between">
                <div>
                  <p className="text-xs text-gray-500 font-bold uppercase tracking-wider">
                    {isEn ? 'Artisan Sourced' : 'ប្រភពសម្រិតសម្រាំង'}
                  </p>
                  <p className="text-sm font-bold text-gray-900">
                    {isEn ? '100% Pure Mountain Coffee' : 'កាហ្វេជម្រាលភ្នំធម្មជាតិសុទ្ធ ១០០%'}
                  </p>
                </div>
                <div className="w-10 h-10 rounded-xl bg-orange-500 flex items-center justify-center text-white shadow-md">
                  <Star className="w-5 h-5 fill-white" />
                </div>
              </div>
            </div>
          </div>
        </section>

        {/* Stats Strip */}
        <section className="bg-gradient-to-r from-gray-900 via-gray-800 to-black text-white rounded-3xl p-8 md:p-12 shadow-xl">
          <div className="grid grid-cols-2 md:grid-cols-4 gap-8 text-center divide-y md:divide-y-0 md:divide-x divide-gray-700/60">
            <div className="space-y-1">
              <div className="text-3xl md:text-4xl font-extrabold text-yellow-400 font-freeman">10,000+</div>
              <p className="text-xs md:text-sm text-gray-300 font-medium">
                {isEn ? 'Coffee Enthusiasts' : 'អតិថិជនពេញចិត្ត'}
              </p>
            </div>
            <div className="space-y-1 pt-4 md:pt-0">
              <div className="text-3xl md:text-4xl font-extrabold text-orange-400 font-freeman">100%</div>
              <p className="text-xs md:text-sm text-gray-300 font-medium">
                {isEn ? 'Organic Certified' : 'សរីរាង្គសុទ្ធ ១០០%'}
              </p>
            </div>
            <div className="space-y-1 pt-4 md:pt-0">
              <div className="text-3xl md:text-4xl font-extrabold text-yellow-400 font-freeman">15+</div>
              <p className="text-xs md:text-sm text-gray-300 font-medium">
                {isEn ? 'Signature Blends' : 'រូបមន្តពិសេស'}
              </p>
            </div>
            <div className="space-y-1 pt-4 md:pt-0">
              <div className="text-3xl md:text-4xl font-extrabold text-orange-400 font-freeman">4.9 ★</div>
              <p className="text-xs md:text-sm text-gray-300 font-medium">
                {isEn ? 'Customer Rating' : 'ពិន្ទុវាយតម្លៃជាមធ្យម'}
              </p>
            </div>
          </div>
        </section>

        {/* Section 2: Purpose */}
        <section id="purpose" className="grid grid-cols-1 md:grid-cols-2 gap-10 lg:gap-16 items-center pt-6">
          <div className="order-2 md:order-1 flex justify-center">
            <div className="relative w-full max-w-lg aspect-4/3 rounded-3xl overflow-hidden shadow-2xl border-4 border-white group">
              <img
                src={personImage}
                alt={purposeTitle}
                className="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105"
                onError={(e) => {
                  e.target.src = 'https://images.unsplash.com/photo-1514432324607-a09d9b4aefdd?auto=format&fit=crop&w=800&q=80';
                }}
              />
              <div className="absolute inset-0 bg-gradient-to-t from-black/40 via-transparent to-transparent" />
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

            {/* Pillars Checklist */}
            <div className="space-y-4 pt-2">
              {pillars.map((item, idx) => {
                const Icon = item.icon;
                return (
                  <div key={idx} className="flex items-start gap-4 p-3.5 rounded-2xl bg-gray-50/70 border border-gray-100">
                    <div className="w-10 h-10 rounded-xl bg-white shadow-xs border border-gray-200/60 flex items-center justify-center text-emerald-600 flex-shrink-0 mt-0.5">
                      <Icon className="w-5 h-5" />
                    </div>
                    <div>
                      <h4 className="font-bold text-gray-900 text-sm">{item.title}</h4>
                      <p className="text-xs md:text-sm text-gray-500 mt-0.5">{item.desc}</p>
                    </div>
                  </div>
                );
              })}
            </div>
          </div>
        </section>

        {/* Section 3: Mission / Story Statement */}
        <section id="mission" className="bg-gradient-to-br from-amber-500/10 via-orange-500/5 to-amber-50/50 rounded-3xl p-8 md:p-14 border border-amber-200/60 shadow-md space-y-6 relative overflow-hidden">
          <div className="absolute -right-10 -bottom-10 w-64 h-64 bg-amber-400/10 rounded-full blur-3xl pointer-events-none" />

          <div className="relative z-10 space-y-4">
            <span className="inline-flex items-center gap-1.5 px-3.5 py-1 rounded-full bg-orange-100 text-orange-700 text-xs font-bold uppercase tracking-wider">
              <Sparkles className="w-3.5 h-3.5" />
              <span>{storyTitle}</span>
            </span>

            <h3 className="text-2xl md:text-4xl font-bold text-gray-900 font-freeman">
              {storyTitle}
            </h3>

            <blockquote className="text-gray-700 leading-relaxed text-base md:text-xl italic max-w-4xl border-l-4 border-orange-500 pl-6 my-4">
              "{storyContent}"
            </blockquote>

            <p className="text-xs text-gray-500 font-semibold uppercase tracking-wider pt-2">
              {isEn ? 'The KouPrey Coffee Philosophy • Sustainable • Organic • Pure' : 'ទស្សនវិជ្ជា KouPrey Coffee • ចីរភាព • សរីរាង្គ • ភាពបរិសុទ្ធ'}
            </p>
          </div>
        </section>

        {/* Section 4: Core Values Grid */}
        <section className="space-y-8">
          <div className="text-center space-y-3">
            <span className="text-orange-600 font-bold uppercase tracking-wider text-xs md:text-sm">
              {isEn ? 'What We Stand For' : 'គោលការណ៍គុណតម្លៃរបស់យើង'}
            </span>
            <h3 className="text-2xl md:text-4xl font-bold text-gray-900 font-freeman">
              {isEn ? 'Our Core Commitments' : 'ការប្តេជ្ញាចិត្តដ៏ខ្ពង់ខ្ពស់របស់យើង'}
            </h3>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            {values.map((v, i) => {
              const VIcon = v.icon;
              return (
                <div
                  key={i}
                  className="bg-white rounded-3xl p-6 border border-gray-100 shadow-xs hover:shadow-xl transition-all duration-300 hover:-translate-y-1 space-y-3"
                >
                  <div className="w-12 h-12 rounded-2xl bg-orange-50 text-orange-600 flex items-center justify-center shadow-inner">
                    <VIcon className="w-6 h-6" />
                  </div>
                  <h4 className="font-bold text-gray-900 text-base font-freeman">
                    {v.title}
                  </h4>
                  <p className="text-sm text-gray-600 leading-relaxed">
                    {v.desc}
                  </p>
                </div>
              );
            })}
          </div>
        </section>

      </main>
    </div>
  );
}

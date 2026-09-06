import React, { useState, useEffect } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { Search } from 'lucide-react';
import { useApp } from '../context/AppContext';
import { getImageUrl } from '../api/client';

const DEFAULT_LOGO = 'https://www.kouprey.asia/kouprey/public/uploads/company-logo-1769389302.png';
const FALLBACK_LOGO = 'https://i.ibb.co/KJNYks2/Logo-Koprey-Photoroom.png';

export default function Navbar() {
  const { language, switchLanguage, t, settings, setIsSearchOpen } = useApp();
  const [scrolled, setScrolled] = useState(false);
  const location = useLocation();

  const logoUrl = settings.company_logo ? getImageUrl(settings.company_logo) : DEFAULT_LOGO;

  useEffect(() => {
    const handleScroll = () => {
      setScrolled(window.scrollY > 10);
    };
    window.addEventListener('scroll', handleScroll);
    return () => window.removeEventListener('scroll', handleScroll);
  }, []);

  const navLinks = [
    { to: '/', label: language === 'km' ? 'ផលិតផល' : 'Product', isAnchor: true },
    { to: '/features', label: language === 'km' ? 'លក្ខណៈពិសេស' : 'Features' },
    { to: '/reviews', label: language === 'km' ? 'ការពិនិត្យ' : 'Reviews' },
    { to: '/about', label: language === 'km' ? 'អំពីយើង' : 'About' },
  ];

  const isActive = (path, isAnchor) => {
    if (isAnchor && (location.pathname === '/' || location.pathname.startsWith('/product'))) return true;
    if (!isAnchor && location.pathname.startsWith(path)) return true;
    return false;
  };

  const toggleLanguage = () => {
    switchLanguage(language === 'km' ? 'en' : 'km');
  };

  return (
    <header
      className={`w-full fixed top-0 left-0 z-50 transition-all duration-500 px-4 py-4 md:px-6 md:py-3 ${
        scrolled
          ? 'bg-white/80 shadow-md backdrop-blur-[30px] saturate-180'
          : 'bg-white/40 backdrop-blur-[30px] saturate-180 border-b border-white/30'
      }`}
    >
      <div className="flex items-center justify-between max-w-6xl mx-auto h-full">
        {/* Logo - Always image, never text */}
        <div className="flex items-center">
          <Link to="/" className="flex items-center transform active:scale-95 transition-transform">
            <img
              src={logoUrl}
              alt="KouPrey"
              className="h-14 w-auto object-contain"
              style={{ height: '56px' }}
              onError={(e) => {
                if (e.target.src !== FALLBACK_LOGO) {
                  e.target.src = FALLBACK_LOGO;
                }
              }}
            />
          </Link>
        </div>

        {/* Desktop Navigation */}
        <nav className="hidden md:flex items-center space-x-4">
          {navLinks.map((link) => {
            const active = isActive(link.to, link.isAnchor);
            return (
              <Link
                key={link.to}
                to={link.to}
                className={`transition-all flex items-center h-10 px-4 py-2 rounded-xl text-sm font-bold ${
                  active
                    ? 'text-[#92adc5] bg-[#92adc5]/10'
                    : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50/50'
                }`}
              >
                {link.label}
              </Link>
            );
          })}
        </nav>

        {/* Action Buttons */}
        <div className="flex items-center gap-3">
          {/* Language Switcher Pill */}
          <button
            onClick={toggleLanguage}
            className="flex items-center gap-2 bg-gray-50 hover:bg-gray-100 rounded-full px-4 py-2 transition-all active:scale-95 border border-gray-100 shadow-xs cursor-pointer"
            title={language === 'km' ? 'ប្តូរភាសា (Switch Language)' : 'Switch Language'}
          >
            <img
              src={
                language === 'en'
                  ? 'https://img.freepik.com/premium-photo/flag-great-britain_406939-4606.jpg?semt=ais_hybrid&w=740&q=80'
                  : 'https://cdn-icons-png.flaticon.com/512/16022/16022033.png'
              }
              alt={language === 'en' ? 'English' : 'Khmer'}
              className="w-6 h-6 object-cover rounded-full shadow-2xs"
            />
            <span className="font-bold text-sm text-gray-700">
              {language === 'en' ? 'EN' : 'KM'}
            </span>
          </button>

          {/* Search Button */}
          <button
            onClick={() => setIsSearchOpen(true)}
            className="w-11 h-11 flex items-center justify-center text-gray-600 hover:text-white hover:bg-black rounded-full transition-all active:scale-90 bg-gray-50 border border-gray-100 shadow-xs cursor-pointer"
            title={language === 'km' ? 'ស្វែងរក' : 'Search'}
            aria-label="Search"
          >
            <Search className="w-5 h-5 stroke-[2.5]" />
          </button>
        </div>
      </div>
    </header>
  );
}

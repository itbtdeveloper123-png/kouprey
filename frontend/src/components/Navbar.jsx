import React, { useState } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { Search, ShoppingBag, Menu, X, Globe } from 'lucide-react';
import { useApp } from '../context/AppContext';
import { getImageUrl } from '../api/client';

export default function Navbar() {
  const { language, switchLanguage, t, settings, cart, setIsSearchOpen } = useApp();
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
  const location = useLocation();

  const logoUrl = settings.company_logo ? getImageUrl(settings.company_logo) : '';
  const totalCartCount = cart.reduce((sum, i) => sum + (i.quantity || 1), 0);

  const navLinks = [
    { to: '/', label: t.home },
    { to: '/features', label: t.features },
    { to: '/about', label: t.about },
    { to: '/reviews', label: t.reviews },
  ];

  const isActive = (path) => {
    if (path === '/' && location.pathname === '/') return true;
    if (path !== '/' && location.pathname.startsWith(path)) return true;
    return false;
  };

  return (
    <>
      {/* Top Notice / Social Banner if present */}
      {settings.social_banner_text && (
        <div className="bg-emerald-800 text-white text-xs md:text-sm py-1.5 px-4 text-center font-medium shadow-inner">
          <div
            className="max-w-6xl mx-auto flex items-center justify-center gap-2"
            dangerouslySetInnerHTML={{ __html: settings.social_banner_text }}
          />
        </div>
      )}

      {/* Main Sticky Navbar */}
      <header className="sticky top-0 z-40 bg-white/95 backdrop-blur-md border-b border-gray-100 shadow-xs transition-all">
        <div className="max-w-6xl mx-auto px-4 md:px-6 h-18 flex items-center justify-between">
          
          {/* Brand Logo */}
          <Link to="/" className="flex items-center gap-3 group">
            {logoUrl ? (
              <img
                src={logoUrl}
                alt={settings.company_name || 'KouPrey'}
                className="h-12 w-auto object-contain transition-transform group-hover:scale-105"
                onError={(e) => {
                  e.target.style.display = 'none';
                }}
              />
            ) : null}
            <span className="font-bold text-xl md:text-2xl text-emerald-800 tracking-tight">
              {settings.company_name || 'KouPrey'}
            </span>
          </Link>

          {/* Desktop Navigation Links */}
          <nav className="hidden md:flex items-center space-x-1 lg:space-x-2">
            {navLinks.map((link) => {
              const active = isActive(link.to);
              return (
                <Link
                  key={link.to}
                  to={link.to}
                  className={`px-4 py-2 rounded-full text-sm font-medium transition-all ${
                    active
                      ? 'text-emerald-700 bg-emerald-50 font-bold'
                      : 'text-gray-600 hover:text-emerald-700 hover:bg-gray-50'
                  }`}
                >
                  {link.label}
                </Link>
              );
            })}
          </nav>

          {/* Actions: Search, Lang Switcher, Cart, Mobile Menu */}
          <div className="flex items-center space-x-2 md:space-x-3">
            
            {/* Search Trigger */}
            <button
              onClick={() => setIsSearchOpen(true)}
              className="p-2.5 text-gray-600 hover:text-emerald-700 hover:bg-emerald-50 rounded-full transition-colors"
              title={t.search_placeholder}
              aria-label="Search"
            >
              <Search className="w-5 h-5" />
            </button>

            {/* Language Switcher */}
            <div className="flex items-center bg-gray-100 p-1 rounded-full text-xs font-semibold">
              <button
                onClick={() => switchLanguage('km')}
                className={`px-2.5 py-1 rounded-full transition-all ${
                  language === 'km'
                    ? 'bg-emerald-700 text-white shadow-xs'
                    : 'text-gray-600 hover:text-gray-900'
                }`}
              >
                ខ្មែរ
              </button>
              <button
                onClick={() => switchLanguage('en')}
                className={`px-2.5 py-1 rounded-full transition-all ${
                  language === 'en'
                    ? 'bg-emerald-700 text-white shadow-xs'
                    : 'text-gray-600 hover:text-gray-900'
                }`}
              >
                EN
              </button>
            </div>

            {/* Cart Button */}
            <button
              onClick={() => setIsSearchOpen(true)}
              className="relative p-2.5 text-gray-700 hover:text-emerald-700 hover:bg-emerald-50 rounded-full transition-colors"
              aria-label="Shopping Cart"
            >
              <ShoppingBag className="w-5 h-5" />
              {totalCartCount > 0 && (
                <span className="absolute top-1 right-1 bg-emerald-600 text-white text-[10px] font-bold w-4 h-4 rounded-full flex items-center justify-center animate-pulse">
                  {totalCartCount}
                </span>
              )}
            </button>

            {/* Mobile Hamburger Toggle */}
            <button
              onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
              className="md:hidden p-2 text-gray-700 hover:text-emerald-700 rounded-lg"
              aria-label="Toggle Menu"
            >
              {mobileMenuOpen ? <X className="w-6 h-6" /> : <Menu className="w-6 h-6" />}
            </button>
          </div>
        </div>

        {/* Mobile Dropdown Menu */}
        {mobileMenuOpen && (
          <div className="md:hidden border-t border-gray-100 bg-white px-4 py-4 space-y-2 shadow-lg animate-modal-slide">
            {navLinks.map((link) => (
              <Link
                key={link.to}
                to={link.to}
                onClick={() => setMobileMenuOpen(false)}
                className={`block px-4 py-2.5 rounded-xl text-base font-medium ${
                  isActive(link.to)
                    ? 'text-emerald-700 bg-emerald-50 font-bold'
                    : 'text-gray-700 hover:bg-gray-50'
                }`}
              >
                {link.label}
              </Link>
            ))}
          </div>
        )}
      </header>
    </>
  );
}

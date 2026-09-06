import React from 'react';
import { Link } from 'react-router-dom';
import { MapPin, Phone, Mail, Home, Coffee, Info, Star, Settings as Cog, Share2 } from 'lucide-react';
import { useApp } from '../context/AppContext';
import { getImageUrl } from '../api/client';

const DEFAULT_LOGO = 'https://www.kouprey.asia/kouprey/public/uploads/company-logo-1769389302.png';
const FALLBACK_LOGO = 'https://i.ibb.co/zT8QwG1h/Untitled-1-Recovered-3-Recovered-Recovered.png';

function FacebookIcon(props) {
  return (
    <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" {...props}>
      <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" />
    </svg>
  );
}

function InstagramIcon(props) {
  return (
    <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" {...props}>
      <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z" />
    </svg>
  );
}

function TelegramIcon(props) {
  return (
    <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" {...props}>
      <path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.894 8.221l-1.97 9.28c-.145.658-.537.818-1.084.508l-3-2.21-1.446 1.394c-.16.16-.295.295-.605.295l.213-3.053 5.56-5.023c.242-.213-.054-.333-.373-.121l-6.871 4.326-2.962-.924c-.643-.204-.657-.643.136-.953l11.57-4.461c.537-.194 1.006.131.832.942z" />
    </svg>
  );
}

export default function Footer() {
  const { language, settings } = useApp();

  const logoUrl = settings.company_logo ? getImageUrl(settings.company_logo) : DEFAULT_LOGO;
  const companyName = settings.company_name || (language === 'km' ? 'គោព្រៃ' : 'KouPrey');
  const companyAddress = settings.company_address || (language === 'km' ? '120408 សង្កាត់បឹងកក់ 2 ខណ្ឌទួលគោក រាជធានីភ្នំពេញ ប្រទេសកម្ពុជា។' : '120408 Sangkat Boeung Kak 2, Khan Tuol Kouk, Phnom Penh, Cambodia.');
  const companyPhone = settings.company_phone || '+855 93 839 883';
  const companyEmail = settings.company_email || 'info@kouprey.asia';
  const siteDescription = settings.site_description || (language === 'km' ? 'គ្រាប់កាហ្វេពិសេស និងដំណោះស្រាយការបង្កើតដែលមានចីរភាព' : 'Premium coffee beans and sustainable brewing solutions');
  
  const quickLinksTitle = settings.footer_quick_links || (language === 'km' ? 'តំណភ្ជាប់រហ័ស' : 'Quick Links');
  const homeText = settings.footer_home || (language === 'km' ? 'ទំព័រដើម' : 'Home');
  const productsText = settings.footer_products || (language === 'km' ? 'ផលិតផល' : 'Products');
  const aboutText = settings.footer_about_us || (language === 'km' ? 'អំពីយើង' : 'About Us');
  const reviewsText = settings.footer_reviews || (language === 'km' ? 'មតិយោបល់' : 'Reviews');
  const adminText = settings.footer_admin || (language === 'km' ? 'អ្នកគ្រប់គ្រង' : 'Admin');

  const socialTitle = language === 'km' ? 'ប្រព័ន្ធបណ្តាញសង្គម' : 'Social Media';
  const footerText = settings.footer_text || (language === 'km' ? '© 2026 គោព្រៃ. All rights reserved.' : '© 2026 KouPrey. All rights reserved.');
  const privacyText = settings.footer_privacy_policy || (language === 'km' ? 'គោលការណ៍ឯកជនភាព' : 'Privacy Policy');
  const termsText = settings.footer_terms_of_service || (language === 'km' ? 'លក្ខខណ្ឌប្រើប្រាស់' : 'Terms of Service');

  return (
    <footer className="mt-auto bg-[#0b1329] text-white py-12 border-t border-gray-800">
      <div className="max-w-6xl mx-auto px-4 md:px-6">
        <div className="grid grid-cols-1 md:grid-cols-4 gap-8 pb-10">
          
          {/* Column 1: Company Information (col-span-1 md:col-span-2) */}
          <div className="col-span-1 md:col-span-2 space-y-4">
            <div className="flex items-center gap-3">
              <img
                src={logoUrl}
                alt={companyName}
                className="h-9 w-auto object-contain brightness-110"
                onError={(e) => {
                  if (e.target.src !== FALLBACK_LOGO) {
                    e.target.src = FALLBACK_LOGO;
                  }
                }}
              />
              <span className="text-xl font-bold text-white tracking-wide font-freeman">
                {companyName}
              </span>
            </div>

            <p className="text-gray-300 text-sm leading-relaxed max-w-md">
              {siteDescription}
            </p>

            <div className="space-y-2.5 pt-1 text-sm text-gray-300">
              <div className="flex items-start gap-3">
                <MapPin className="w-4 h-4 text-yellow-400 mt-1 flex-shrink-0" />
                <span className="leading-relaxed">{companyAddress}</span>
              </div>
              <div className="flex items-center gap-3">
                <Phone className="w-4 h-4 text-yellow-400 flex-shrink-0" />
                <a href={`tel:${companyPhone.replace(/\s+/g, '')}`} className="hover:text-yellow-400 transition-colors">
                  {companyPhone}
                </a>
              </div>
              <div className="flex items-center gap-3">
                <Mail className="w-4 h-4 text-yellow-400 flex-shrink-0" />
                <a href={`mailto:${companyEmail}`} className="hover:text-yellow-400 transition-colors">
                  {companyEmail}
                </a>
              </div>
            </div>
          </div>

          {/* Column 2: Quick Links (Desktop list, Mobile flex cards) */}
          <div className="space-y-4">
            <h3 className="text-base font-bold text-white tracking-wide">
              {quickLinksTitle}
            </h3>

            {/* Desktop Version */}
            <ul className="hidden md:block space-y-2.5 text-sm text-gray-300">
              <li>
                <Link to="/" className="hover:text-yellow-400 transition-colors">
                  {homeText}
                </Link>
              </li>
              <li>
                <a href="/#products" className="hover:text-yellow-400 transition-colors">
                  {productsText}
                </a>
              </li>
              <li>
                <Link to="/about" className="hover:text-yellow-400 transition-colors">
                  {aboutText}
                </Link>
              </li>
              <li>
                <Link to="/reviews" className="hover:text-yellow-400 transition-colors">
                  {reviewsText}
                </Link>
              </li>
              <li>
                <a
                  href="https://www.kouprey.asia/admin/login.php"
                  target="_blank"
                  rel="noreferrer"
                  className="hover:text-yellow-400 transition-colors"
                >
                  {adminText}
                </a>
              </li>
            </ul>

            {/* Mobile Version: Glass Cards */}
            <div className="md:hidden grid grid-cols-2 gap-2.5">
              <Link
                to="/"
                className="bg-white/10 hover:bg-white/20 rounded-xl p-3 text-center transition-all border border-white/15 flex flex-col items-center justify-center gap-1.5"
              >
                <Home className="w-5 h-5 text-yellow-400" />
                <span className="text-xs font-medium text-white">{homeText}</span>
              </Link>
              <a
                href="/#products"
                className="bg-white/10 hover:bg-white/20 rounded-xl p-3 text-center transition-all border border-white/15 flex flex-col items-center justify-center gap-1.5"
              >
                <Coffee className="w-5 h-5 text-yellow-400" />
                <span className="text-xs font-medium text-white">{productsText}</span>
              </a>
              <Link
                to="/about"
                className="bg-white/10 hover:bg-white/20 rounded-xl p-3 text-center transition-all border border-white/15 flex flex-col items-center justify-center gap-1.5"
              >
                <Info className="w-5 h-5 text-yellow-400" />
                <span className="text-xs font-medium text-white">{aboutText}</span>
              </Link>
              <Link
                to="/reviews"
                className="bg-white/10 hover:bg-white/20 rounded-xl p-3 text-center transition-all border border-white/15 flex flex-col items-center justify-center gap-1.5"
              >
                <Star className="w-5 h-5 text-yellow-400" />
                <span className="text-xs font-medium text-white">{reviewsText}</span>
              </Link>
              <a
                href="https://www.kouprey.asia/admin/login.php"
                target="_blank"
                rel="noreferrer"
                className="col-span-2 bg-white/10 hover:bg-white/20 rounded-xl p-3 text-center transition-all border border-white/15 flex flex-col items-center justify-center gap-1.5"
              >
                <Cog className="w-5 h-5 text-yellow-400" />
                <span className="text-xs font-medium text-white">{adminText}</span>
              </a>
            </div>
          </div>

          {/* Column 3: Social Media */}
          <div className="space-y-4">
            <h3 className="text-base font-bold text-white tracking-wide flex items-center gap-2">
              <Share2 className="w-4 h-4 text-yellow-400" />
              <span>{socialTitle}</span>
            </h3>

            <div className="flex items-center gap-3 pt-1">
              {settings.social_facebook && (
                <a
                  href={settings.social_facebook}
                  target="_blank"
                  rel="noreferrer"
                  className="w-10 h-10 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition-transform active:scale-95 border border-white/15"
                  aria-label="Facebook"
                >
                  <FacebookIcon className="w-4 h-4" />
                </a>
              )}
              {settings.social_instagram && (
                <a
                  href={settings.social_instagram}
                  target="_blank"
                  rel="noreferrer"
                  className="w-10 h-10 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition-transform active:scale-95 border border-white/15"
                  aria-label="Instagram"
                >
                  <InstagramIcon className="w-4 h-4" />
                </a>
              )}
              {settings.social_telegram && (
                <a
                  href={settings.social_telegram}
                  target="_blank"
                  rel="noreferrer"
                  className="w-10 h-10 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition-transform active:scale-95 border border-white/15"
                  aria-label="Telegram"
                >
                  <TelegramIcon className="w-4 h-4" />
                </a>
              )}
            </div>
          </div>

        </div>

        {/* Bottom Bar: Copyright & Legal */}
        <div className="border-t border-gray-800/80 pt-6 flex flex-col md:flex-row justify-between items-center text-xs text-gray-400 gap-4">
          <p>{footerText}</p>
          <div className="flex items-center gap-6">
            <Link to="/privacy-policy" className="hover:text-yellow-400 transition-colors">
              {privacyText}
            </Link>
            <Link to="/terms-of-service" className="hover:text-yellow-400 transition-colors">
              {termsText}
            </Link>
          </div>
        </div>

      </div>
    </footer>
  );
}

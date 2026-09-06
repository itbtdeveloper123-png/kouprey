import React from 'react';
import { Link } from 'react-router-dom';
import { Phone, Mail, MapPin, Send, MessageCircle } from 'lucide-react';
import { useApp } from '../context/AppContext';
import { getImageUrl } from '../api/client';

function FacebookIcon(props) {
  return (
    <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" {...props}>
      <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" />
    </svg>
  );
}

export default function Footer() {
  const { settings, t } = useApp();

  const logoUrl = settings.company_logo ? getImageUrl(settings.company_logo) : '';
  const currentYear = new Date().getFullYear();

  return (
    <footer className="bg-gray-900 text-gray-300 border-t border-gray-800 pt-16 pb-12 mt-20">
      <div className="max-w-6xl mx-auto px-4 md:px-6">
        <div className="grid grid-cols-1 md:grid-cols-4 gap-10 pb-12 border-b border-gray-800">
          
          {/* Brand Col */}
          <div className="space-y-4 md:col-span-1">
            <Link to="/" className="flex items-center gap-3">
              {logoUrl ? (
                <img
                  src={logoUrl}
                  alt={settings.company_name || 'KouPrey'}
                  className="h-10 w-auto object-contain brightness-110"
                  onError={(e) => { e.target.style.display = 'none'; }}
                />
              ) : null}
              <span className="font-bold text-xl text-white tracking-tight">
                {settings.company_name || 'KouPrey'}
              </span>
            </Link>
            <p className="text-xs text-gray-400 leading-relaxed">
              {settings.footer_about_text || 'ម៉ាកសញ្ញាកាហ្វេ និងតែបៃតងដ៏ពេញនិយម ផ្តោតសំខាន់លើគុណភាព រសជាតិ និងសុខភាពរបស់អ្នកទទួលទាន។'}
            </p>

            {/* Social Media Links */}
            <div className="flex items-center gap-3 pt-2">
              {settings.social_facebook && (
                <a
                  href={settings.social_facebook}
                  target="_blank"
                  rel="noreferrer"
                  className="w-9 h-9 rounded-full bg-gray-800 hover:bg-emerald-600 text-gray-300 hover:text-white flex items-center justify-center transition-colors"
                  aria-label="Facebook"
                >
                  <FacebookIcon className="w-4 h-4" />
                </a>
              )}
              {settings.social_telegram && (
                <a
                  href={settings.social_telegram}
                  target="_blank"
                  rel="noreferrer"
                  className="w-9 h-9 rounded-full bg-gray-800 hover:bg-emerald-600 text-gray-300 hover:text-white flex items-center justify-center transition-colors"
                  aria-label="Telegram"
                >
                  <Send className="w-4 h-4" />
                </a>
              )}
            </div>
          </div>

          {/* Quick Links */}
          <div className="space-y-3">
            <h4 className="text-white font-bold text-sm tracking-wider uppercase">
              {t.home} & {t.products}
            </h4>
            <ul className="space-y-2 text-xs">
              <li>
                <Link to="/" className="hover:text-emerald-400 transition-colors">
                  {t.home}
                </Link>
              </li>
              <li>
                <Link to="/features" className="hover:text-emerald-400 transition-colors">
                  {t.features}
                </Link>
              </li>
              <li>
                <Link to="/about" className="hover:text-emerald-400 transition-colors">
                  {t.about}
                </Link>
              </li>
              <li>
                <Link to="/reviews" className="hover:text-emerald-400 transition-colors">
                  {t.reviews}
                </Link>
              </li>
            </ul>
          </div>

          {/* Legal / Policy Links */}
          <div className="space-y-3">
            <h4 className="text-white font-bold text-sm tracking-wider uppercase">
              គោលការណ៍ច្បាប់
            </h4>
            <ul className="space-y-2 text-xs">
              <li>
                <Link to="/privacy-policy" className="hover:text-emerald-400 transition-colors">
                  {t.privacy}
                </Link>
              </li>
              <li>
                <Link to="/terms-of-service" className="hover:text-emerald-400 transition-colors">
                  {t.terms}
                </Link>
              </li>
            </ul>
          </div>

          {/* Contact Details */}
          <div className="space-y-3">
            <h4 className="text-white font-bold text-sm tracking-wider uppercase">
              {t.contact_us}
            </h4>
            <ul className="space-y-2.5 text-xs text-gray-400">
              {settings.phone && (
                <li className="flex items-center gap-2.5">
                  <Phone className="w-4 h-4 text-emerald-500 flex-shrink-0" />
                  <span>{settings.phone}</span>
                </li>
              )}
              {settings.email && (
                <li className="flex items-center gap-2.5">
                  <Mail className="w-4 h-4 text-emerald-500 flex-shrink-0" />
                  <span>{settings.email}</span>
                </li>
              )}
              {settings.address && (
                <li className="flex items-start gap-2.5">
                  <MapPin className="w-4 h-4 text-emerald-500 flex-shrink-0 mt-0.5" />
                  <span className="leading-relaxed">{settings.address}</span>
                </li>
              )}
            </ul>
          </div>

        </div>

        {/* Copyright */}
        <div className="pt-8 flex flex-col md:flex-row items-center justify-between text-xs text-gray-500 gap-4">
          <p>
            © {currentYear} {settings.company_name || 'KouPrey'}. រក្សាសិទ្ធិគ្រប់យ៉ាង។
          </p>
          <div className="flex items-center space-x-4">
            <Link to="/privacy-policy" className="hover:text-gray-400 transition-colors">
              {t.privacy}
            </Link>
            <span>•</span>
            <Link to="/terms-of-service" className="hover:text-gray-400 transition-colors">
              {t.terms}
            </Link>
          </div>
        </div>
      </div>
    </footer>
  );
}

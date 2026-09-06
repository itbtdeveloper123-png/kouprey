import React from 'react';
import { NavLink } from 'react-router-dom';
import {
  LayoutDashboard,
  Package,
  Tags,
  Star,
  Sparkles,
  Settings,
  Info,
  Users,
  ExternalLink,
  LogOut,
  Coffee
} from 'lucide-react';
import { useAuth } from '../context/AuthContext';

const navItems = [
  { path: '/', label: 'ផ្ទាំងដើម', sub: 'Dashboard', icon: LayoutDashboard },
  { path: '/products', label: 'ផលិតផល', sub: 'Products', icon: Package },
  { path: '/categories', label: 'ប្រភេទ', sub: 'Categories', icon: Tags },
  { path: '/reviews', label: 'ការវាយតម្លៃ', sub: 'Reviews', icon: Star },
  { path: '/features', label: 'លក្ខណៈពិសេស', sub: 'Features', icon: Sparkles },
  { path: '/settings', label: 'ការកំណត់', sub: 'Settings', icon: Settings },
  { path: '/about', label: 'អំពីយើង', sub: 'About Page', icon: Info },
  { path: '/admins', label: 'អ្នកគ្រប់គ្រង', sub: 'Admin Team', icon: Users },
];

export default function Sidebar({ isOpen, onClose }) {
  const { logout, admin } = useAuth();

  return (
    <>
      {/* Mobile backdrop */}
      {isOpen && (
        <div
          className="fixed inset-0 z-40 bg-black/40 backdrop-blur-xs lg:hidden"
          onClick={onClose}
        />
      )}

      <aside
        className={`fixed top-0 left-0 bottom-0 z-50 w-64 bg-emerald-950 text-emerald-100 flex flex-col transition-transform duration-300 ease-in-out lg:translate-x-0 ${
          isOpen ? 'translate-x-0' : '-translate-x-full'
        } border-r border-emerald-900/50 shadow-xl`}
      >
        {/* Brand Header */}
        <div className="h-16 px-6 flex items-center gap-3 border-b border-emerald-900/60 bg-emerald-950/80">
          <div className="w-10 h-10 rounded-xl bg-gradient-to-tr from-emerald-600 to-teal-400 flex items-center justify-center text-white shadow-md shadow-emerald-950/50">
            <Coffee size={22} className="stroke-[2.2]" />
          </div>
          <div>
            <span className="font-bold text-lg text-white tracking-wide block leading-tight">
              KouPrey
            </span>
            <span className="text-[11px] text-emerald-400 font-medium tracking-wider uppercase">
              Admin Portal
            </span>
          </div>
        </div>

        {/* Navigation links */}
        <nav className="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
          {navItems.map((item) => {
            const Icon = item.icon;
            return (
              <NavLink
                key={item.path}
                to={item.path}
                end={item.path === '/'}
                onClick={onClose}
                className={({ isActive }) =>
                  `flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition-all group ${
                    isActive
                      ? 'bg-emerald-600 text-white shadow-md shadow-emerald-900/40 font-semibold'
                      : 'text-emerald-200/80 hover:bg-emerald-900/50 hover:text-white'
                  }`
                }
              >
                {({ isActive }) => (
                  <>
                    <Icon
                      size={20}
                      className={isActive ? 'text-white' : 'text-emerald-400 group-hover:text-emerald-300'}
                    />
                    <div className="flex-1">
                      <div className="leading-tight">{item.label}</div>
                      <div className="text-[10px] opacity-70 font-normal">{item.sub}</div>
                    </div>
                  </>
                )}
              </NavLink>
            );
          })}
        </nav>

        {/* Footer Area */}
        <div className="p-3 border-t border-emerald-900/60 bg-emerald-950/60 space-y-2">
          <a
            href="https://www.kouprey.asia"
            target="_blank"
            rel="noreferrer"
            className="flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium text-emerald-300 hover:bg-emerald-900/40 transition"
          >
            <span className="flex items-center gap-2">
              <ExternalLink size={15} />
              បើកគេហទំព័រ (Live Site)
            </span>
          </a>

          <div className="flex items-center justify-between px-3 py-2 rounded-lg bg-emerald-900/40">
            <div className="overflow-hidden pr-2">
              <p className="text-xs font-semibold text-white truncate">
                {admin?.name || admin?.username || 'Admin'}
              </p>
              <p className="text-[10px] text-emerald-400 truncate">@{admin?.username}</p>
            </div>
            <button
              onClick={logout}
              title="Logout"
              className="p-1.5 rounded-lg text-emerald-300 hover:text-red-300 hover:bg-red-500/20 transition cursor-pointer"
            >
              <LogOut size={16} />
            </button>
          </div>
        </div>
      </aside>
    </>
  );
}

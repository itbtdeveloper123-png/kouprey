import React from 'react';
import { Menu, LogOut, User, ShieldCheck } from 'lucide-react';
import { useAuth } from '../context/AuthContext';

export default function Topbar({ onOpenSidebar, title }) {
  const { admin, logout } = useAuth();

  return (
    <header className="h-16 bg-white border-b border-gray-200 sticky top-0 z-30 px-4 sm:px-6 flex items-center justify-between shadow-xs">
      <div className="flex items-center gap-3">
        <button
          onClick={onOpenSidebar}
          className="lg:hidden p-2 rounded-lg text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition cursor-pointer"
        >
          <Menu size={20} />
        </button>
        <h1 className="text-lg sm:text-xl font-bold text-gray-900 flex items-center gap-2">
          {title}
        </h1>
      </div>

      <div className="flex items-center gap-4">
        <div className="hidden sm:flex items-center gap-2 px-3 py-1 bg-emerald-50 text-emerald-700 rounded-full border border-emerald-200 text-xs font-medium">
          <ShieldCheck size={14} />
          <span>Active Admin Session</span>
        </div>

        <div className="flex items-center gap-3 pl-3 border-l border-gray-200">
          <div className="w-8 h-8 rounded-full bg-emerald-100 text-emerald-800 font-bold flex items-center justify-center text-xs">
            {admin?.name ? admin.name.charAt(0).toUpperCase() : 'A'}
          </div>
          <div className="hidden md:block text-left text-xs">
            <span className="font-semibold text-gray-800 block truncate max-w-[120px]">
              {admin?.name || admin?.username}
            </span>
            <span className="text-gray-500">Administrator</span>
          </div>
          <button
            onClick={logout}
            className="p-1.5 text-gray-400 hover:text-red-600 rounded-lg hover:bg-red-50 transition cursor-pointer"
            title="ចាកចេញ (Logout)"
          >
            <LogOut size={18} />
          </button>
        </div>
      </div>
    </header>
  );
}

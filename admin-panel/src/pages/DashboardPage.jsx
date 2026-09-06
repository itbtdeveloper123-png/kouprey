import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import {
  Package,
  Tags,
  Star,
  Users,
  AlertCircle,
  PlusCircle,
  ExternalLink,
  Settings,
  Sparkles,
  ArrowUpRight,
  Loader2
} from 'lucide-react';
import { adminApi } from '../api/adminClient';

export default function DashboardPage() {
  const [stats, setStats] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const fetchStats = async () => {
    setLoading(true);
    setError('');
    try {
      const res = await adminApi.getStats();
      if (res.success && res.stats) {
        setStats(res.stats);
      }
    } catch (err) {
      setError(err.message || 'Failed to load stats');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchStats();
  }, []);

  if (loading) {
    return (
      <div className="flex flex-col items-center justify-center min-h-[400px] text-gray-500 gap-3">
        <Loader2 className="animate-spin text-emerald-600" size={36} />
        <p className="text-sm font-medium">កំពុងទាញយកទិន្នន័យ (Loading Dashboard)...</p>
      </div>
    );
  }

  const statCards = [
    {
      title: 'ផលិតផលសរុប (Products)',
      value: stats?.productCount ?? 0,
      icon: Package,
      color: 'bg-emerald-500 text-white',
      link: '/products',
      sub: 'គ្រប់គ្រងកាតាឡុកទំនិញ',
    },
    {
      title: 'ប្រភេទផលិតផល (Categories)',
      value: stats?.categoryCount ?? 0,
      icon: Tags,
      color: 'bg-blue-500 text-white',
      link: '/categories',
      sub: 'ចំណាត់ថ្នាក់ទំនិញ',
    },
    {
      title: 'ការវាយតម្លៃ (Reviews)',
      value: stats?.reviewCount ?? 0,
      badge: stats?.pendingReviews > 0 ? `${stats.pendingReviews} រង់ចាំអនុម័ត` : null,
      icon: Star,
      color: 'bg-amber-500 text-white',
      link: '/reviews',
      sub: 'មតិកែលម្អពីអតិថិជន',
    },
    {
      title: 'អ្នកគ្រប់គ្រង (Admins)',
      value: stats?.adminCount ?? 0,
      icon: Users,
      color: 'bg-purple-500 text-white',
      link: '/admins',
      sub: 'គណនីមានសិទ្ធិប្រើប្រាស់',
    },
  ];

  return (
    <div className="space-y-8">
      {/* Welcome Banner */}
      <div className="bg-gradient-to-r from-emerald-800 to-teal-900 rounded-2xl p-6 sm:p-8 text-white shadow-lg relative overflow-hidden">
        <div className="relative z-10 max-w-2xl">
          <h2 className="text-2xl sm:text-3xl font-bold tracking-tight">
            សូមស្វាគមន៍មកកាន់ KouPrey Admin
          </h2>
          <p className="text-emerald-100/90 text-sm sm:text-base mt-2">
            គ្រប់គ្រងផលិតផល ការវាយតម្លៃ អត្ថបទ និងការកំណត់ទាំងអស់នៃហាង KouPrey Coffee & Syrups យ៉ាងងាយស្រួល។
          </p>
          <div className="flex flex-wrap gap-3 mt-6">
            <Link
              to="/products"
              className="inline-flex items-center gap-2 px-4 py-2 bg-white text-emerald-900 rounded-xl font-semibold text-xs sm:text-sm hover:bg-emerald-50 transition shadow"
            >
              <PlusCircle size={16} />
              បន្ថែមផលិតផលថ្មី
            </Link>
            <a
              href="https://www.kouprey.asia"
              target="_blank"
              rel="noreferrer"
              className="inline-flex items-center gap-2 px-4 py-2 bg-emerald-700/80 hover:bg-emerald-700 text-white rounded-xl font-medium text-xs sm:text-sm transition border border-emerald-500/40"
            >
              <ExternalLink size={16} />
              មើលគេហទំព័រ Live
            </a>
          </div>
        </div>
      </div>

      {error && (
        <div className="p-4 bg-red-50 border border-red-200 rounded-xl text-red-700 text-sm flex items-center gap-2">
          <AlertCircle size={18} />
          <span>{error}</span>
        </div>
      )}

      {/* Stats Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        {statCards.map((card, i) => {
          const Icon = card.icon;
          return (
            <Link
              key={i}
              to={card.link}
              className="bg-white rounded-2xl p-5 border border-gray-200/80 shadow-xs hover:shadow-md hover:border-emerald-300 transition group flex flex-col justify-between"
            >
              <div>
                <div className="flex items-center justify-between">
                  <span className="text-xs font-medium text-gray-500">{card.title}</span>
                  <div className={`w-10 h-10 rounded-xl flex items-center justify-center ${card.color} shadow-xs`}>
                    <Icon size={20} />
                  </div>
                </div>
                <div className="mt-3 flex items-baseline gap-2">
                  <span className="text-3xl font-extrabold text-gray-900">{card.value}</span>
                  {card.badge && (
                    <span className="px-2 py-0.5 text-[11px] font-semibold bg-amber-100 text-amber-800 rounded-full border border-amber-300 animate-pulse">
                      {card.badge}
                    </span>
                  )}
                </div>
              </div>
              <div className="mt-4 pt-3 border-t border-gray-100 flex items-center justify-between text-xs text-gray-400 group-hover:text-emerald-700 transition font-medium">
                <span>{card.sub}</span>
                <ArrowUpRight size={14} />
              </div>
            </Link>
          );
        })}
      </div>

      {/* Quick Access Tiles */}
      <div className="bg-white rounded-2xl border border-gray-200/80 p-6 shadow-xs">
        <h3 className="text-base font-bold text-gray-900 mb-4">ផ្លូវកាត់សំខាន់ៗ (Quick Management)</h3>
        <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
          <Link
            to="/products"
            className="p-4 rounded-xl border border-gray-100 bg-gray-50/70 hover:bg-emerald-50/60 hover:border-emerald-300 transition text-center group"
          >
            <div className="w-10 h-10 mx-auto rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center group-hover:scale-110 transition">
              <Package size={20} />
            </div>
            <span className="text-xs font-semibold text-gray-800 block mt-2">គ្រប់គ្រងផលិតផល</span>
          </Link>

          <Link
            to="/categories"
            className="p-4 rounded-xl border border-gray-100 bg-gray-50/70 hover:bg-blue-50/60 hover:border-blue-300 transition text-center group"
          >
            <div className="w-10 h-10 mx-auto rounded-lg bg-blue-100 text-blue-700 flex items-center justify-center group-hover:scale-110 transition">
              <Tags size={20} />
            </div>
            <span className="text-xs font-semibold text-gray-800 block mt-2">ប្រភេទ និងមឺនុយ</span>
          </Link>

          <Link
            to="/reviews"
            className="p-4 rounded-xl border border-gray-100 bg-gray-50/70 hover:bg-amber-50/60 hover:border-amber-300 transition text-center group"
          >
            <div className="w-10 h-10 mx-auto rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center group-hover:scale-110 transition">
              <Star size={20} />
            </div>
            <span className="text-xs font-semibold text-gray-800 block mt-2">អនុម័តការវាយតម្លៃ</span>
          </Link>

          <Link
            to="/features"
            className="p-4 rounded-xl border border-gray-100 bg-gray-50/70 hover:bg-teal-50/60 hover:border-teal-300 transition text-center group"
          >
            <div className="w-10 h-10 mx-auto rounded-lg bg-teal-100 text-teal-700 flex items-center justify-center group-hover:scale-110 transition">
              <Sparkles size={20} />
            </div>
            <span className="text-xs font-semibold text-gray-800 block mt-2">លក្ខណៈពិសេស</span>
          </Link>

          <Link
            to="/settings"
            className="p-4 rounded-xl border border-gray-100 bg-gray-50/70 hover:bg-gray-200/60 hover:border-gray-400 transition text-center group"
          >
            <div className="w-10 h-10 mx-auto rounded-lg bg-gray-200 text-gray-700 flex items-center justify-center group-hover:scale-110 transition">
              <Settings size={20} />
            </div>
            <span className="text-xs font-semibold text-gray-800 block mt-2">ការកំណត់គេហទំព័រ</span>
          </Link>

          <Link
            to="/about"
            className="p-4 rounded-xl border border-gray-100 bg-gray-50/70 hover:bg-indigo-50/60 hover:border-indigo-300 transition text-center group"
          >
            <div className="w-10 h-10 mx-auto rounded-lg bg-indigo-100 text-indigo-700 flex items-center justify-center group-hover:scale-110 transition">
              <Package size={20} />
            </div>
            <span className="text-xs font-semibold text-gray-800 block mt-2">ទំព័រអំពីយើង (About)</span>
          </Link>
        </div>
      </div>
    </div>
  );
}

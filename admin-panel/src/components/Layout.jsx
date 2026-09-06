import React, { useState } from 'react';
import { Outlet, useLocation } from 'react-router-dom';
import Sidebar from './Sidebar';
import Topbar from './Topbar';

const titlesMap = {
  '/': 'ផ្ទាំងសង្ខេប (Dashboard)',
  '/products': 'គ្រប់គ្រងផលិតផល (Products Management)',
  '/categories': 'គ្រប់គ្រងប្រភេទ (Categories)',
  '/reviews': 'គ្រប់គ្រងការវាយតម្លៃ (Customer Reviews)',
  '/features': 'គ្រប់គ្រងលក្ខណៈពិសេស (Feature Highlights)',
  '/settings': 'ការកំណត់គេហទំព័រ (Site Settings)',
  '/about': 'គ្រប់គ្រងទំព័រអំពីយើង (About Us Page)',
  '/admins': 'គ្រប់គ្រងអ្នកប្រើប្រាស់ (Admin Users)',
};

export default function Layout() {
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const location = useLocation();
  const currentTitle = titlesMap[location.pathname] || 'ផ្ទាំងគ្រប់គ្រង (Admin)';

  return (
    <div className="min-h-screen bg-gray-50 flex">
      <Sidebar isOpen={sidebarOpen} onClose={() => setSidebarOpen(false)} />

      <div className="flex-1 lg:pl-64 flex flex-col min-w-0">
        <Topbar
          onOpenSidebar={() => setSidebarOpen(true)}
          title={currentTitle}
        />

        <main className="flex-1 p-4 sm:p-6 lg:p-8 max-w-7xl w-full mx-auto animate-fade-in">
          <Outlet />
        </main>
      </div>
    </div>
  );
}

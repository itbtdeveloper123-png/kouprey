import React from 'react';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { AppProvider, useApp } from './context/AppContext';
import Navbar from './components/Navbar';
import Footer from './components/Footer';
import ProductDetailModal from './components/ProductDetailModal';
import ReviewModal from './components/ReviewModal';
import SearchModal from './components/SearchModal';

// Pages
import HomePage from './pages/HomePage';
import ProductDetailPage from './pages/ProductDetailPage';
import FeaturesPage from './pages/FeaturesPage';
import AboutPage from './pages/AboutPage';
import ReviewsPage from './pages/ReviewsPage';
import PolicyPage from './pages/PolicyPage';

function Toast() {
  const { toastMessage } = useApp();
  if (!toastMessage) return null;

  return (
    <div className="fixed bottom-6 right-6 z-50 bg-emerald-800 text-white text-sm font-semibold px-5 py-3 rounded-2xl shadow-2xl flex items-center gap-2.5 animate-modal-slide">
      <span className="w-2 h-2 rounded-full bg-emerald-400 animate-ping" />
      <span>{toastMessage}</span>
    </div>
  );
}

function MainLayout() {
  return (
    <div className="flex flex-col min-h-screen bg-white text-gray-800">
      <Navbar />
      <main className="flex-1">
        <Routes>
          <Route path="/" element={<HomePage />} />
          <Route path="/product/:id" element={<ProductDetailPage />} />
          <Route path="/features" element={<FeaturesPage />} />
          <Route path="/about" element={<AboutPage />} />
          <Route path="/reviews" element={<ReviewsPage />} />
          <Route path="/privacy-policy" element={<PolicyPage />} />
          <Route path="/terms-of-service" element={<PolicyPage />} />
          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </main>
      <Footer />

      {/* Global Modals */}
      <ProductDetailModal />
      <ReviewModal />
      <SearchModal />
      <Toast />
    </div>
  );
}

export default function App() {
  return (
    <BrowserRouter>
      <AppProvider>
        <MainLayout />
      </AppProvider>
    </BrowserRouter>
  );
}

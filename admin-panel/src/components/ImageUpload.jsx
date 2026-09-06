import React, { useState, useRef } from 'react';
import { Upload, X, Image as ImageIcon, Loader2 } from 'lucide-react';
import { adminApi } from '../api/adminClient';

export default function ImageUpload({ value, onChange, type = 'product', label = 'រូបភាព (Image)' }) {
  const [uploading, setUploading] = useState(false);
  const [error, setError] = useState('');
  const fileInputRef = useRef(null);

  const handleFile = async (file) => {
    if (!file) return;
    setError('');
    setUploading(true);

    try {
      const res = await adminApi.uploadImage(file, type);
      if (res.success && res.path) {
        onChange(res.path);
      } else {
        setError(res.error || 'Upload failed');
      }
    } catch (err) {
      setError(err.message || 'Upload error');
    } finally {
      setUploading(false);
    }
  };

  const handleDrop = (e) => {
    e.preventDefault();
    if (e.dataTransfer.files && e.dataTransfer.files[0]) {
      handleFile(e.dataTransfer.files[0]);
    }
  };

  const formatImageUrl = (url) => {
    if (!url) return '';
    if (url.startsWith('http')) return url;
    return `https://www.kouprey.asia${url.startsWith('/') ? '' : '/'}${url}`;
  };

  return (
    <div className="space-y-2">
      {label && <label className="block text-sm font-semibold text-gray-700">{label}</label>}

      {value ? (
        <div className="relative group rounded-xl border border-gray-200 overflow-hidden bg-gray-50 flex items-center justify-center h-48 w-full max-w-xs shadow-sm">
          <img
            src={formatImageUrl(value)}
            alt="Preview"
            className="w-full h-full object-contain p-2"
          />
          <button
            type="button"
            onClick={() => onChange('')}
            className="absolute top-2 right-2 p-1.5 bg-red-600 text-white rounded-full opacity-0 group-hover:opacity-100 transition shadow hover:bg-red-700"
            title="Remove Image"
          >
            <X size={16} />
          </button>
        </div>
      ) : (
        <div
          onDragOver={(e) => e.preventDefault()}
          onDrop={handleDrop}
          onClick={() => fileInputRef.current?.click()}
          className={`border-2 border-dashed rounded-xl p-6 flex flex-col items-center justify-center cursor-pointer transition max-w-xs h-40 ${
            uploading
              ? 'border-emerald-400 bg-emerald-50/40 cursor-wait'
              : 'border-gray-300 hover:border-emerald-600 hover:bg-emerald-50/20'
          }`}
        >
          <input
            ref={fileInputRef}
            type="file"
            accept="image/*"
            className="hidden"
            onChange={(e) => handleFile(e.target.files?.[0])}
            disabled={uploading}
          />
          {uploading ? (
            <div className="flex flex-col items-center gap-2 text-emerald-700">
              <Loader2 className="animate-spin" size={28} />
              <span className="text-xs font-medium">កំពុងផ្ទុកឡើង...</span>
            </div>
          ) : (
            <div className="flex flex-col items-center gap-1.5 text-gray-500 text-center">
              <div className="p-2.5 rounded-full bg-gray-100 text-gray-600 mb-1">
                <Upload size={20} />
              </div>
              <span className="text-xs font-medium text-gray-700">ចុច ឬ ទម្លាក់រូបភាពនៅទីនេះ</span>
              <span className="text-[11px] text-gray-400">JPG, PNG, WebP (អតិបរមា 10MB)</span>
            </div>
          )}
        </div>
      )}

      {error && <p className="text-xs text-red-600 font-medium">{error}</p>}
    </div>
  );
}

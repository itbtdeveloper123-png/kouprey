import React, { useState, useRef } from 'react';
import { Upload, X, Image as ImageIcon, Loader2, Sparkles } from 'lucide-react';
import { adminApi } from '../api/adminClient';
import { formatImageUrl, handleImageError } from '../utils/imageUrl';
import { compressImageClient } from '../utils/imageCompressor';

export default function ImageUpload({
  value,
  onChange,
  type = 'product',
  label = 'រូបភាព (Image)',
  allowRemoveBg = null // defaults to true for product, false for others
}) {
  const [uploading, setUploading] = useState(false);
  const [localPreview, setLocalPreview] = useState('');
  const [error, setError] = useState('');
  const [removeBg, setRemoveBg] = useState(allowRemoveBg !== null ? allowRemoveBg : (type === 'product'));
  const fileInputRef = useRef(null);

  const handleFile = async (rawFile) => {
    if (!rawFile) return;
    setError('');
    const previewUrl = URL.createObjectURL(rawFile);
    setLocalPreview(previewUrl);
    setUploading(true);

    try {
      const file = await compressImageClient(rawFile);
      const res = await adminApi.uploadImage(file, type, { removeBg });
      if (res.success && res.path) {
        const cleanPath = res.path.includes('/uploads/')
          ? res.path.substring(res.path.indexOf('/uploads/'))
          : res.path;
        onChange(cleanPath);
        if (res.warning) {
          setError(`ចំណាំ: ${res.warning}`);
        }
      } else {
        setError(res.error || 'Upload failed');
        setLocalPreview('');
      }
    } catch (err) {
      setError(err.message || 'Upload error');
      setLocalPreview('');
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

  const displaySrc = localPreview || (value ? formatImageUrl(value, type === 'banner' ? 'banner' : 'products') : '');

  return (
    <div className="space-y-2">
      {label && <label className="block text-sm font-semibold text-gray-700">{label}</label>}

      {displaySrc ? (
        <div className="relative group rounded-xl border border-gray-200 overflow-hidden bg-gray-50 flex items-center justify-center h-48 w-full max-w-xs shadow-sm">
          {uploading && (
            <div className="absolute inset-0 bg-white/85 backdrop-blur-[1px] flex flex-col items-center justify-center z-10 px-2 text-center">
              <Loader2 size={24} className="animate-spin text-amber-600 mb-1" />
              <span className="text-xs font-semibold text-amber-800">
                {removeBg ? 'កំពុង Remove BG & WebP...' : 'កំពុង Upload...'}
              </span>
            </div>
          )}
          <img
            key={displaySrc}
            src={displaySrc}
            alt="Preview"
            className="w-full h-full object-contain p-2"
            onError={handleImageError}
          />
          <button
            type="button"
            onClick={() => {
              setLocalPreview('');
              onChange('');
            }}
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
            <div className="flex flex-col items-center gap-2 text-emerald-700 text-center px-2">
              <Loader2 className="animate-spin" size={28} />
              <span className="text-xs font-medium">
                {removeBg ? 'កំពុង Remove Background & WebP...' : 'កំពុងផ្ទុកឡើង...'}
              </span>
            </div>
          ) : (
            <div className="flex flex-col items-center gap-1.5 text-gray-500 text-center">
              <div className="p-2.5 rounded-full bg-gray-100 text-gray-600 mb-1">
                <Upload size={20} />
              </div>
              <span className="text-xs font-medium text-gray-700">ចុច ឬ ទម្លាក់រូបភាពនៅទីនេះ</span>
              <span className="text-[11px] text-gray-400">JPG, PNG, WebP (អតិបរមា 15MB)</span>
            </div>
          )}
        </div>
      )}

      {/* Remove BG Toggle */}
      {allowRemoveBg !== false && (
        <div className="pt-0.5">
          <label className="inline-flex items-center gap-1.5 cursor-pointer select-none text-xs text-gray-600 hover:text-amber-800 transition">
            <input
              type="checkbox"
              checked={removeBg}
              onChange={(e) => setRemoveBg(e.target.checked)}
              className="w-3.5 h-3.5 text-amber-600 rounded focus:ring-amber-500 cursor-pointer"
            />
            <span className="flex items-center gap-1 text-[11px] font-medium text-gray-700">
              <Sparkles size={12} className="text-amber-600" />
              Auto Remove Background (AI)
            </span>
          </label>
        </div>
      )}

      {error && <p className="text-xs text-red-600 font-medium">{error}</p>}
    </div>
  );
}

import React, { useState, useEffect } from 'react';
import { adminApi } from '../api/adminClient';
import { formatImageUrl } from '../utils/imageUrl';

const FOLDERS = [
  { id: 'products', name: 'Products', path: 'assets/images/products', icon: 'bi-box-seam' },
  { id: 'banner', name: 'Banners (Assets)', path: 'assets/images/banner', icon: 'bi-image' },
  { id: 'banners', name: 'Banners (Uploads)', path: 'uploads/banners', icon: 'bi-images' },
  { id: 'categories', name: 'Categories', path: 'assets/images/categories', icon: 'bi-tags' },
  { id: 'showcase', name: 'Showcase', path: 'uploads/showcase', icon: 'bi-stars' },
  { id: 'related', name: 'Related', path: 'uploads/related', icon: 'bi-link-45deg' },
  { id: 'uploads', name: 'Uploads Root', path: 'uploads', icon: 'bi-folder' },
];

export default function MediaBrowserModal({ isOpen, onClose, onSelectImage, title = 'ជ្រើសរើសរូបភាពពី Hosting Media' }) {
  const [activeFolder, setActiveFolder] = useState('products');
  const [images, setImages] = useState([]);
  const [loading, setLoading] = useState(false);
  const [search, setSearch] = useState('');
  const [selectedImage, setSelectedImage] = useState(null);
  const [uploading, setUploading] = useState(false);
  const [autoRemoveBg, setAutoRemoveBg] = useState(true);
  const [copySuccess, setCopySuccess] = useState('');

  useEffect(() => {
    if (isOpen) {
      loadImages(activeFolder);
    }
  }, [isOpen, activeFolder]);

  const loadImages = async (folder) => {
    setLoading(true);
    try {
      const res = await adminApi.getFileManagerImages(folder);
      if (res.success) {
        setImages(res.images || []);
      }
    } catch (err) {
      console.error('Failed to load images for folder', folder, err);
    } finally {
      setLoading(false);
    }
  };

  const handleUpload = async (e) => {
    const files = e.target.files;
    if (!files || files.length === 0) return;
    setUploading(true);
    try {
      await adminApi.uploadFileManager(files, activeFolder, { removeBg: autoRemoveBg });
      await loadImages(activeFolder);
    } catch (err) {
      alert('Upload failed: ' + (err.message || 'Unknown error'));
    } finally {
      setUploading(false);
      e.target.value = '';
    }
  };

  const handleSelect = () => {
    if (!selectedImage) return;
    if (onSelectImage) {
      onSelectImage(selectedImage.url);
    }
    onClose();
  };

  const handleCopyUrl = (url) => {
    const fullUrl = formatImageUrl(url);
    navigator.clipboard.writeText(fullUrl);
    setCopySuccess('បានចម្លង URL!');
    setTimeout(() => setCopySuccess(''), 2000);
  };

  if (!isOpen) return null;

  const filteredImages = images.filter((img) =>
    img.filename.toLowerCase().includes(search.toLowerCase())
  );

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4 animate-fade-in">
      <div className="bg-white rounded-2xl shadow-2xl w-full max-w-5xl h-[85vh] flex flex-col overflow-hidden border border-gray-100">
        
        {/* Header */}
        <div className="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-gradient-to-r from-amber-500/10 via-amber-600/5 to-transparent">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-amber-500 text-white flex items-center justify-center shadow-md shadow-amber-500/20">
              <i className="bi bi-folder-fill text-lg"></i>
            </div>
            <div>
              <h3 className="font-bold text-gray-900 text-lg flex items-center gap-2">
                {title}
                <span className="text-xs bg-amber-100 text-amber-800 px-2 py-0.5 rounded-full font-medium">Hosting Images</span>
              </h3>
              <p className="text-xs text-gray-500">រុករក និងជ្រើសរើសរូបភាពដែលមានស្រាប់នៅលើ Hosting Server</p>
            </div>
          </div>
          <button
            onClick={onClose}
            className="w-9 h-9 flex items-center justify-center rounded-xl bg-gray-100 hover:bg-gray-200 text-gray-500 hover:text-gray-800 transition-colors"
          >
            <i className="bi bi-x-lg text-lg"></i>
          </button>
        </div>

        {/* Folder Navigation Bar */}
        <div className="bg-gray-50/80 px-6 py-2.5 border-b border-gray-200/60 flex items-center justify-between gap-4 overflow-x-auto">
          <div className="flex items-center gap-1.5 min-w-max">
            {FOLDERS.map((f) => (
              <button
                key={f.id}
                onClick={() => {
                  setActiveFolder(f.id);
                  setSelectedImage(null);
                }}
                className={`flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all ${
                  activeFolder === f.id
                    ? 'bg-amber-600 text-white shadow-sm shadow-amber-600/30'
                    : 'bg-white hover:bg-gray-100 text-gray-700 border border-gray-200/80'
                }`}
              >
                <i className={`bi ${f.icon}`}></i>
                <span>{f.name}</span>
              </button>
            ))}
          </div>

          <div className="flex items-center gap-2 min-w-max">
            {activeFolder === 'products' && (
              <label className="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg bg-amber-50 border border-amber-200 text-amber-900 text-xs font-medium cursor-pointer select-none hover:bg-amber-100 transition-all">
                <input
                  type="checkbox"
                  checked={autoRemoveBg}
                  onChange={(e) => setAutoRemoveBg(e.target.checked)}
                  className="w-3.5 h-3.5 text-amber-600 rounded"
                />
                <span>✨ Auto Remove BG (AI)</span>
              </label>
            )}

            {/* Upload Button into this folder */}
            <label className="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-medium cursor-pointer shadow-sm shadow-emerald-600/20 transition-all">
              <i className="bi bi-cloud-arrow-up-fill"></i>
              <span>{uploading ? 'កំពុងផ្ទុកឡើង...' : 'Upload រូបភាពថ្មី'}</span>
              <input
                type="file"
                multiple
                accept="image/*"
                onChange={handleUpload}
                disabled={uploading}
                className="hidden"
              />
            </label>
          </div>
        </div>

        {/* Search & Stats Bar */}
        <div className="px-6 py-2.5 bg-white border-b border-gray-100 flex items-center justify-between gap-4">
          <div className="relative flex-1 max-w-md">
            <i className="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
            <input
              type="text"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="ស្វែងរកឈ្មោះរូបភាពក្នុង Folder នេះ..."
              className="w-full pl-8 pr-4 py-1.5 text-xs bg-gray-50 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all"
            />
          </div>
          <div className="text-xs text-gray-500 flex items-center gap-3">
            <span>ចំនួនសរុប: <strong className="text-gray-800">{filteredImages.length}</strong> រូប</span>
            {copySuccess && (
              <span className="text-emerald-600 font-medium animate-bounce flex items-center gap-1">
                <i className="bi bi-check-circle-fill"></i> {copySuccess}
              </span>
            )}
          </div>
        </div>

        {/* Body: Images Grid & Preview */}
        <div className="flex-1 flex overflow-hidden">
          {/* Main Grid */}
          <div className="flex-1 p-6 overflow-y-auto bg-gray-50/50">
            {loading ? (
              <div className="h-full flex flex-col items-center justify-center text-gray-400">
                <div className="w-8 h-8 border-3 border-amber-500 border-t-transparent rounded-full animate-spin mb-2"></div>
                <p className="text-xs font-medium">កំពុងផ្ទុករូបភាពពី Hosting...</p>
              </div>
            ) : filteredImages.length === 0 ? (
              <div className="h-full flex flex-col items-center justify-center text-gray-400">
                <i className="bi bi-images text-4xl mb-2 text-gray-300"></i>
                <p className="text-sm font-medium text-gray-600">គ្មានរូបភាពក្នុង Folder នេះទេ</p>
                <p className="text-xs text-gray-400 mt-1">អ្នកអាច Upload រូបភាពថ្មីចូល Folder នេះបាន</p>
              </div>
            ) : (
              <div className="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 gap-3">
                {filteredImages.map((img, idx) => {
                  const isSelected = selectedImage?.url === img.url;
                  const fullUrl = formatImageUrl(img.url);

                  return (
                    <div
                      key={idx}
                      onClick={() => setSelectedImage(img)}
                      onDoubleClick={() => {
                        setSelectedImage(img);
                        if (onSelectImage) onSelectImage(img.url);
                        onClose();
                      }}
                      className={`group relative bg-white rounded-xl border p-2 cursor-pointer transition-all hover:shadow-md flex flex-col ${
                        isSelected
                          ? 'border-amber-500 ring-2 ring-amber-500/20 shadow-md bg-amber-50/30'
                          : 'border-gray-200 hover:border-amber-300'
                      }`}
                    >
                      {/* Image Thumbnail */}
                      <div className="aspect-square w-full rounded-lg overflow-hidden bg-gray-100 flex items-center justify-center relative mb-2">
                        <img
                          src={fullUrl}
                          alt={img.filename}
                          loading="lazy"
                          className="w-full h-full object-contain transition-transform duration-300 group-hover:scale-105"
                          onError={(e) => {
                            e.target.src = 'https://placehold.co/150x150?text=No+Image';
                          }}
                        />
                        {isSelected && (
                          <div className="absolute top-1.5 right-1.5 bg-amber-500 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs shadow">
                            <i className="bi bi-check-bold"></i>
                          </div>
                        )}
                      </div>

                      {/* Info */}
                      <div className="mt-auto">
                        <p className="text-[11px] font-medium text-gray-700 truncate" title={img.filename}>
                          {img.filename}
                        </p>
                        <p className="text-[10px] text-gray-400 mt-0.5">{img.size || ''}</p>
                      </div>
                    </div>
                  );
                })}
              </div>
            )}
          </div>

          {/* Right Preview Drawer (if selected) */}
          {selectedImage && (
            <div className="w-72 bg-white border-l border-gray-100 p-5 flex flex-col overflow-y-auto">
              <h4 className="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">ព័ត៌មានរូបភាព</h4>
              
              <div className="aspect-square w-full bg-gray-50 border border-gray-200 rounded-xl overflow-hidden mb-4 flex items-center justify-center">
                <img
                  src={formatImageUrl(selectedImage.url)}
                  alt={selectedImage.filename}
                  className="max-h-full max-w-full object-contain"
                />
              </div>

              <div className="space-y-3 text-xs flex-1">
                <div>
                  <label className="text-gray-400 block mb-1">ឈ្មោះឯកសារ</label>
                  <p className="font-semibold text-gray-800 break-all">{selectedImage.filename}</p>
                </div>
                <div>
                  <label className="text-gray-400 block mb-1">ទំហំ</label>
                  <p className="font-semibold text-gray-800">{selectedImage.size || 'N/A'}</p>
                </div>
                <div>
                  <label className="text-gray-400 block mb-1">Relative Path</label>
                  <p className="font-mono text-[10px] text-gray-600 bg-gray-50 p-1.5 rounded border break-all">
                    {selectedImage.url}
                  </p>
                </div>
                <div>
                  <label className="text-gray-400 block mb-1">Hosting Absolute URL</label>
                  <p className="font-mono text-[10px] text-gray-600 bg-gray-50 p-1.5 rounded border break-all">
                    {formatImageUrl(selectedImage.url)}
                  </p>
                </div>
              </div>

              <div className="pt-4 mt-auto border-t border-gray-100 space-y-2">
                <button
                  type="button"
                  onClick={() => handleCopyUrl(selectedImage.url)}
                  className="w-full py-2 px-3 rounded-lg border border-gray-200 hover:bg-gray-50 text-gray-700 text-xs font-medium flex items-center justify-center gap-1.5 transition-colors"
                >
                  <i className="bi bi-clipboard"></i> ចម្លង URL រូបភាព
                </button>
                <button
                  type="button"
                  onClick={handleSelect}
                  className="w-full py-2.5 px-4 rounded-xl bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold shadow-md shadow-amber-500/20 flex items-center justify-center gap-1.5 transition-all"
                >
                  <i className="bi bi-check2-circle text-base"></i> ជ្រើសរើសរូបភាពនេះ
                </button>
              </div>
            </div>
          )}
        </div>

        {/* Footer */}
        <div className="px-6 py-3.5 bg-gray-50 border-t border-gray-100 flex items-center justify-between">
          <div className="text-xs text-gray-500">
            {selectedImage ? (
              <span>បានជ្រើសរើស: <strong className="text-gray-800">{selectedImage.filename}</strong></span>
            ) : (
              <span>សូមចុចលើរូបភាពណាមួយដើម្បីជ្រើសរើស ឬចុច Double Click ដើម្បីយកភ្លាមៗ</span>
            )}
          </div>
          <div className="flex items-center gap-2">
            <button
              onClick={onClose}
              className="px-4 py-2 rounded-xl text-xs font-medium text-gray-600 hover:bg-gray-200 transition-colors"
            >
              បោះបង់ (Cancel)
            </button>
            <button
              onClick={handleSelect}
              disabled={!selectedImage}
              className={`px-5 py-2 rounded-xl text-xs font-bold transition-all shadow-sm ${
                selectedImage
                  ? 'bg-amber-500 hover:bg-amber-600 text-white shadow-amber-500/20'
                  : 'bg-gray-200 text-gray-400 cursor-not-allowed'
              }`}
            >
              ជ្រើសរើសរូបភាព (Use Selected)
            </button>
          </div>
        </div>

      </div>
    </div>
  );
}

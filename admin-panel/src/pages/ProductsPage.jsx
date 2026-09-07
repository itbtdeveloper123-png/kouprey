import React, { useState, useEffect, useMemo } from 'react';
import {
  Package,
  Tags,
  Plus,
  Search,
  Filter,
  Edit2,
  Trash2,
  CheckCircle2,
  XCircle,
  Star,
  Award,
  Layers,
  Settings,
  Eye,
  EyeOff,
  GripVertical,
  ChevronLeft,
  ChevronRight,
  X,
  Upload,
  Image as ImageIcon,
  FolderOpen,
  Copy,
  ExternalLink,
  AlertCircle,
  RotateCcw,
  Sparkles,
  ChevronDown,
  ChevronUp,
  Table as TableIcon,
  FileText,
  ListPlus,
  ArrowRightLeft,
  Check,
  Loader2
} from 'lucide-react';
import { adminApi } from '../api/adminClient';
import { formatImageUrl, handleImageError } from '../utils/imageUrl';
import { compressImageClient, checkImageTransparency } from '../utils/imageCompressor';
import MediaBrowserModal from '../components/MediaBrowserModal';

// ──────────────────────────────────────────────
// CUSTOM FIELDS HELPERS (100% Parity with admin/products.php)
// ──────────────────────────────────────────────
function parseCustomFields(raw) {
  if (!raw) return [];
  let obj = raw;
  if (typeof raw === 'string') {
    try {
      obj = JSON.parse(raw);
    } catch {
      return [];
    }
  }
  if (!obj || typeof obj !== 'object' || Array.isArray(obj)) return [];

  return Object.entries(obj).map(([key, item], index) => {
    if (item && typeof item === 'object' && !Array.isArray(item) && (item.name || item.type || item.value)) {
      const nameEn = typeof item.name === 'object' ? (item.name?.en || '') : (item.name || '');
      const nameKm = typeof item.name === 'object' ? (item.name?.km || '') : '';
      const type = item.type === 'table' ? 'table' : 'text';

      let valueEn = '';
      let valueKm = '';
      let tableRows = [];

      if (type === 'table' && Array.isArray(item.value)) {
        tableRows = item.value.map((row, rIdx) => {
          const lblEn = typeof row.label === 'object' ? (row.label?.en || '') : (row.label || '');
          const lblKm = typeof row.label === 'object' ? (row.label?.km || '') : '';
          let valPairs = [];
          if (Array.isArray(row.value)) {
            valPairs = row.value.map((vp) => ({
              en: typeof vp === 'object' ? (vp?.en || '') : String(vp || ''),
              km: typeof vp === 'object' ? (vp?.km || '') : '',
            }));
          } else if (row.value) {
            valPairs = [{ en: String(row.value), km: '' }];
          }
          if (valPairs.length === 0) {
            valPairs = [{ en: '', km: '' }];
          }
          return {
            id: `row_${rIdx}_${Date.now()}_${Math.random().toString(36).substr(2, 4)}`,
            label_en: lblEn,
            label_km: lblKm,
            values: valPairs,
          };
        });
      } else if (typeof item.value === 'object' && item.value !== null) {
        valueEn = item.value.en || '';
        valueKm = item.value.km || '';
      } else {
        valueEn = String(item.value || '');
        valueKm = '';
      }

      return {
        id: key,
        name_en: nameEn,
        name_km: nameKm,
        type,
        value_en: valueEn,
        value_km: valueKm,
        table_rows: tableRows,
        is_simple: false,
      };
    } else {
      // Legacy simple boolean / string flags like show_in_collection
      return {
        id: key,
        name_en: key,
        name_km: '',
        type: 'simple',
        value_en: String(item),
        value_km: '',
        table_rows: [],
        is_simple: true,
      };
    }
  });
}

function serializeCustomFields(cfList) {
  const result = {};
  (cfList || []).forEach((field, index) => {
    if (field.is_simple) {
      const k = (field.name_en || field.id || '').trim();
      if (k) {
        result[k] = field.value_en === 'true' ? true : field.value_en === 'false' ? false : field.value_en;
      }
      return;
    }

    const fieldId = field.id || `field_${Date.now()}_${Math.random().toString(36).substr(2, 7)}`;
    const nameEn = (field.name_en || '').trim();
    const nameKm = (field.name_km || '').trim();

    if (!nameEn && !nameKm && !field.value_en && !field.value_km && (!field.table_rows || field.table_rows.length === 0)) {
      return;
    }

    const nameObj = {
      en: nameEn,
      km: nameKm || nameEn,
    };

    if (field.type === 'table') {
      const rows = (field.table_rows || []).map((r) => ({
        label: { en: (r.label_en || '').trim(), km: (r.label_km || '').trim() },
        value: (r.values || []).map((v) => ({ en: (v.en || '').trim(), km: (v.km || '').trim() })),
      }));
      result[fieldId] = {
        name: nameObj,
        type: 'table',
        value: rows,
      };
    } else {
      result[fieldId] = {
        name: nameObj,
        type: 'text',
        value: {
          en: (field.value_en || '').trim(),
          km: (field.value_km || '').trim(),
        },
      };
    }
  });
  return result;
}

export default function ProductsPage() {
  // Top Tabs: 'products' | 'categories'
  const [activeTab, setActiveTab] = useState('products');

  // Main Data
  const [products, setProducts] = useState([]);
  const [categories, setCategories] = useState([]);
  const [allProductsForRelated, setAllProductsForRelated] = useState([]);
  const [loading, setLoading] = useState(true);
  const [lang, setLang] = useState('km'); // Display language filter

  // Filters & Pagination
  const [search, setSearch] = useState('');
  const [selectedCategory, setSelectedCategory] = useState('');
  const [currentPage, setCurrentPage] = useState(1);
  const [perPage, setPerPage] = useState(10);

  // Toast notification
  const [toast, setToast] = useState(null);
  const showToast = (message, type = 'success') => {
    setToast({ message, type });
    setTimeout(() => setToast(null), 4000);
  };

  // Media Browser Modal state
  const [mediaModalOpen, setMediaModalOpen] = useState(false);
  const [mediaTargetCallback, setMediaTargetCallback] = useState(null);
  const [mediaModalTitle, setMediaModalTitle] = useState('ជ្រើសរើសរូបភាពពី Hosting Media');

  const openMediaBrowser = (callback, title = 'ជ្រើសរើសរូបភាពពី Hosting Media') => {
    setMediaTargetCallback(() => callback);
    setMediaModalTitle(title);
    setMediaModalOpen(true);
  };

  // ──────────────────────────────────────────────
  // PRODUCT DRAWER (Slide-out Add / Edit)
  // ──────────────────────────────────────────────
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [drawerMode, setDrawerMode] = useState('add'); // 'add' | 'edit'
  const [drawerTab, setDrawerTab] = useState('en'); // 'en' | 'km'
  const [showDetailedSpecs, setShowDetailedSpecs] = useState(false);
  const [imagePreview, setImagePreview] = useState('');
  const [uploadingImage, setUploadingImage] = useState(false);
  const [autoRemoveBg, setAutoRemoveBg] = useState(true);
  const [uploadProgressText, setUploadProgressText] = useState('');
  const [imageCompressionStats, setImageCompressionStats] = useState(null);
  const [productForm, setProductForm] = useState({
    base_product_id: 0,
    price: '',
    category_id: '',
    image: '',
    featured: 0,
    best_seller: 0,
    enabled: 1,
    // EN fields
    name_en: '',
    description_en: '',
    weight_en: '',
    detailed_description_en: '',
    ingredients_en: '',
    origin_en: '',
    brewing_instructions_en: '',
    tasting_notes_en: '',
    // KM fields
    name_km: '',
    description_km: '',
    weight_km: '',
    detailed_description_km: '',
    ingredients_km: '',
    origin_km: '',
    brewing_instructions_km: '',
    tasting_notes_km: '',
    // Shared specs
    roast_level: '',
    custom_fields: [], // [{ key, value }]
    related_products: [], // [{ base_id, name, custom_url, custom_image, custom_image_url }]
  });
  const [relatedSearch, setRelatedSearch] = useState('');
  const [savingProduct, setSavingProduct] = useState(false);
  const [drawerLoading, setDrawerLoading] = useState(false);
  const [copyModalOpen, setCopyModalOpen] = useState(false);
  const [copyProductSearch, setCopyProductSearch] = useState('');

  // ──────────────────────────────────────────────
  // DETAILED SPECS MODAL (Gear ⚙️ Button)
  // ──────────────────────────────────────────────
  const [detailedModalOpen, setDetailedModalOpen] = useState(false);
  const [detailedModalTab, setDetailedModalTab] = useState('en');
  const [detailedForm, setDetailedForm] = useState(null);
  const [savingDetailed, setSavingDetailed] = useState(false);

  // ──────────────────────────────────────────────
  // CATEGORIES MANAGEMENT MODAL
  // ──────────────────────────────────────────────
  const [catModalOpen, setCatModalOpen] = useState(false);
  const [catModalMode, setCatModalMode] = useState('add');
  const [catModalTab, setCatModalTab] = useState('en');
  const [catForm, setCatForm] = useState({
    base_category_id: 0,
    id: 0,
    name_en: '',
    description_en: '',
    name_km: '',
    description_km: '',
    image: '',
  });
  const [savingCategory, setSavingCategory] = useState(false);

  // Delete Confirm Dialog
  const [deleteConfirm, setDeleteConfirm] = useState(null);

  // ──────────────────────────────────────────────
  // LOAD DATA
  // ──────────────────────────────────────────────
  const loadData = async () => {
    setLoading(true);
    try {
      const [prodsRes, catsRes] = await Promise.all([
        adminApi.getProducts({ lang, search, category_id: selectedCategory }),
        adminApi.getCategories(lang),
      ]);
      if (prodsRes.success) {
        setProducts(prodsRes.products || []);
      }
      if (catsRes.success) {
        setCategories(catsRes.categories || []);
      }
    } catch (err) {
      showToast(err.message || 'បរាជ័យក្នុងការទាញយកទិន្នន័យ', 'error');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadData();
  }, [lang, selectedCategory]);

  const handleSearchSubmit = (e) => {
    e.preventDefault();
    loadData();
  };

  // ──────────────────────────────────────────────
  // OPEN ADD PRODUCT DRAWER
  // ──────────────────────────────────────────────
  const openAddProduct = () => {
    setDrawerMode('add');
    setDrawerTab('en');
    setImagePreview('');
    setUploadingImage(false);
    setProductForm({
      base_product_id: 0,
      price: '',
      category_id: categories[0]?.id || '',
      image: '',
      featured: 0,
      best_seller: 0,
      enabled: 1,
      name_en: '',
      description_en: '',
      weight_en: '',
      detailed_description_en: '',
      ingredients_en: '',
      origin_en: '',
      brewing_instructions_en: '',
      tasting_notes_en: '',
      name_km: '',
      description_km: '',
      weight_km: '',
      detailed_description_km: '',
      ingredients_km: '',
      origin_km: '',
      brewing_instructions_km: '',
      tasting_notes_km: '',
      roast_level: '',
      custom_fields: [],
      related_products: [],
    });
    if (typeof setShowDetailedSpecs === 'function') setShowDetailedSpecs(false);
    setDrawerOpen(true);
  };

  // ──────────────────────────────────────────────
  // OPEN EDIT PRODUCT DRAWER (Instant 0ms with SWR)
  // ──────────────────────────────────────────────
  const openEditProduct = async (product) => {
    setDrawerMode('edit');
    setDrawerTab('en');
    setImagePreview('');
    setUploadingImage(false);
    const baseId = product.base_product_id || product.id;

    // 1. INSTANT OPEN (0ms): Prefill from row data immediately!
    setProductForm({
      base_product_id: baseId,
      price: product.price || '',
      category_id: product.category_id || '',
      image: product.image || '',
      featured: Number(product.featured || 0),
      best_seller: Number(product.best_seller || 0),
      enabled: Number(product.enabled ?? 1),
      name_en: product.name || '',
      description_en: product.description || '',
      weight_en: product.weight || '',
      detailed_description_en: product.detailed_description || '',
      ingredients_en: product.ingredients || '',
      origin_en: product.origin || '',
      brewing_instructions_en: product.brewing_instructions || '',
      tasting_notes_en: product.tasting_notes || '',
      name_km: product.name || '',
      description_km: product.description || '',
      weight_km: product.weight || '',
      detailed_description_km: product.detailed_description || '',
      ingredients_km: product.ingredients || '',
      origin_km: product.origin || '',
      brewing_instructions_km: product.brewing_instructions || '',
      tasting_notes_km: product.tasting_notes || '',
      roast_level: product.roast_level || '',
      custom_fields: parseCustomFields(product.custom_fields),
      related_products: [],
    });
    if (typeof setShowDetailedSpecs === 'function') setShowDetailedSpecs(false);
    setDrawerOpen(true);
    setDrawerLoading(true);

    // 2. Fetch full bilingual & related data in background
    try {
      const res = await adminApi.getProductData(baseId);
      if (res.success) {
        const en = res.en || {};
        const km = res.km || {};

        const cfRaw = en.custom_fields || km.custom_fields || product.custom_fields || '{}';
        const cfList = parseCustomFields(cfRaw);

        const relList = (res.related || []).map((r) => ({
          base_id: r.related_product_id || `custom_${r.id}`,
          name: r.product_name || r.custom_name || '',
          custom_url: r.custom_url || '',
          custom_image: r.custom_image || '',
          custom_image_url: r.custom_image_url || '',
        }));

        setProductForm((prev) => ({
          ...prev,
          base_product_id: baseId,
          price: en.price || km.price || prev.price || '',
          category_id: en.category_id || km.category_id || prev.category_id || '',
          image: en.image || km.image || prev.image || '',
          featured: Number(en.featured ?? km.featured ?? prev.featured),
          best_seller: Number(en.best_seller ?? km.best_seller ?? prev.best_seller),
          enabled: Number(en.enabled ?? km.enabled ?? prev.enabled),
          // EN
          name_en: en.name || prev.name_en || '',
          description_en: en.description || prev.description_en || '',
          weight_en: en.weight || prev.weight_en || '',
          detailed_description_en: en.detailed_description || prev.detailed_description_en || '',
          ingredients_en: en.ingredients || prev.ingredients_en || '',
          origin_en: en.origin || prev.origin_en || '',
          brewing_instructions_en: en.brewing_instructions || prev.brewing_instructions_en || '',
          tasting_notes_en: en.tasting_notes || prev.tasting_notes_en || '',
          // KM
          name_km: km.name || prev.name_km || '',
          description_km: km.description || prev.description_km || '',
          weight_km: km.weight || prev.weight_km || '',
          detailed_description_km: km.detailed_description || prev.detailed_description_km || '',
          ingredients_km: km.ingredients || prev.ingredients_km || '',
          origin_km: km.origin || prev.origin_km || '',
          brewing_instructions_km: km.brewing_instructions || prev.brewing_instructions_km || '',
          tasting_notes_km: km.tasting_notes || prev.tasting_notes_km || '',
          roast_level: en.roast_level || km.roast_level || prev.roast_level || '',
          custom_fields: cfList,
          related_products: relList,
        }));
      }
    } catch (err) {
      console.warn('Could not fetch extra product data:', err);
    } finally {
      setDrawerLoading(false);
    }
  };

  // ──────────────────────────────────────────────
  // SAVE PRODUCT (Bilingual Full Save with Custom Fields)
  // ──────────────────────────────────────────────
  const handleSaveProduct = async (e) => {
    e.preventDefault();
    if (!productForm.name_en.trim() && !productForm.name_km.trim()) {
      showToast('សូមបញ្ចូលឈ្មោះផលិតផល (Product name required)', 'error');
      return;
    }
    if (!productForm.price || parseFloat(productForm.price) <= 0) {
      showToast('សូមបញ្ចូលតម្លៃផលិតផលឱ្យបានត្រឹមត្រូវ (Price must be > 0)', 'error');
      return;
    }

    setSavingProduct(true);
    try {
      // Reassemble custom fields into structured object
      const cfObj = serializeCustomFields(productForm.custom_fields);

      const payload = {
        base_product_id: productForm.base_product_id,
        price: parseFloat(productForm.price),
        category_id: productForm.category_id || null,
        image: productForm.image,
        featured: productForm.featured ? 1 : 0,
        best_seller: productForm.best_seller ? 1 : 0,
        enabled: productForm.enabled ? 1 : 0,
        roast_level: productForm.roast_level,
        custom_fields: cfObj,
        // EN
        name_en: productForm.name_en,
        description_en: productForm.description_en,
        weight_en: productForm.weight_en,
        detailed_description_en: productForm.detailed_description_en,
        ingredients_en: productForm.ingredients_en,
        origin_en: productForm.origin_en,
        brewing_instructions_en: productForm.brewing_instructions_en,
        tasting_notes_en: productForm.tasting_notes_en,
        // KM
        name_km: productForm.name_km,
        description_km: productForm.description_km,
        weight_km: productForm.weight_km,
        detailed_description_km: productForm.detailed_description_km,
        ingredients_km: productForm.ingredients_km,
        origin_km: productForm.origin_km,
        brewing_instructions_km: productForm.brewing_instructions_km,
        tasting_notes_km: productForm.tasting_notes_km,
        related_products: productForm.related_products,
      };

      const res = await adminApi.saveProductFull(payload);
      if (res.success) {
        showToast(drawerMode === 'add' ? 'បានបន្ថែមផលិតផលថ្មីជោគជ័យ!' : 'បានកែប្រែផលិតផលជោគជ័យ!');
        setDrawerOpen(false);
        loadData();
      } else {
        showToast(res.error || 'បរាជ័យក្នុងការរក្សាទុក', 'error');
      }
    } catch (err) {
      showToast(err.message || 'Error saving product', 'error');
    } finally {
      setSavingProduct(false);
    }
  };

  // ──────────────────────────────────────────────
  // DETAILED SPECS MODAL (Gear button)
  // ──────────────────────────────────────────────
  const openDetailedModal = async (product) => {
    try {
      const baseId = product.base_product_id || product.id;
      const res = await adminApi.getProductData(baseId);
      if (res.success) {
        const en = res.en || {};
        const km = res.km || {};

        const cfRaw = en.custom_fields || km.custom_fields || product.custom_fields || '{}';
        const cfList = parseCustomFields(cfRaw);

        setDetailedForm({
          base_product_id: baseId,
          price: en.price || km.price,
          category_id: en.category_id || km.category_id,
          image: en.image || km.image,
          featured: en.featured || km.featured || 0,
          best_seller: en.best_seller || km.best_seller || 0,
          enabled: en.enabled || km.enabled || 1,
          name_en: en.name || '',
          name_km: km.name || '',
          description_en: en.description || '',
          description_km: km.description || '',
          // Detailed specs
          detailed_description_en: en.detailed_description || '',
          ingredients_en: en.ingredients || '',
          origin_en: en.origin || '',
          brewing_instructions_en: en.brewing_instructions || '',
          tasting_notes_en: en.tasting_notes || '',
          weight_en: en.weight || '',
          detailed_description_km: km.detailed_description || '',
          ingredients_km: km.ingredients || '',
          origin_km: km.origin || '',
          brewing_instructions_km: km.brewing_instructions || '',
          tasting_notes_km: km.tasting_notes || '',
          weight_km: km.weight || '',
          roast_level: en.roast_level || km.roast_level || '',
          custom_fields: cfList,
        });
        setDetailedModalTab('en');
        setDetailedModalOpen(true);
      }
    } catch (err) {
      showToast('បរាជ័យក្នុងការទាញយកព័ត៌មានលម្អិត: ' + err.message, 'error');
    }
  };

  const handleSaveDetailed = async (e) => {
    e.preventDefault();
    if (!detailedForm) return;
    setSavingDetailed(true);
    try {
      const cfObj = serializeCustomFields(detailedForm.custom_fields);

      const payload = {
        ...detailedForm,
        custom_fields: cfObj,
      };

      const res = await adminApi.saveProductFull(payload);
      if (res.success) {
        showToast('បានរក្សាទុកព័ត៌មានលម្អិតជោគជ័យ!');
        setDetailedModalOpen(false);
        loadData(true);
      } else {
        showToast(res.error || 'បរាជ័យក្នុងការរក្សាទុក', 'error');
      }
    } catch (err) {
      showToast(err.message || 'Error saving specs', 'error');
    } finally {
      setSavingDetailed(false);
    }
  };

  // ──────────────────────────────────────────────
  // STATUS TOGGLES (⭐ Featured, 🏆 Best Seller, 📦 Collection, 👁️ Enabled)
  // OPTIMISTIC UPDATE: Responds in 0ms!
  // ──────────────────────────────────────────────
  const handleToggleStatus = async (product, field) => {
    const baseId = product.base_product_id || product.id;
    const curVal = field === 'collection' ? product.show_in_collection : product[field];
    const newVal = (curVal == 1 || curVal === true) ? 0 : 1;

    // 1. Instant UI toggle (0ms)
    setProducts((prev) =>
      prev.map((p) => {
        if (p.base_product_id === baseId || p.id === product.id) {
          if (field === 'featured') return { ...p, featured: newVal };
          if (field === 'best_seller') return { ...p, best_seller: newVal };
          if (field === 'enabled') return { ...p, enabled: newVal };
          if (field === 'collection') return { ...p, show_in_collection: Boolean(newVal) };
        }
        return p;
      })
    );

    // 2. Perform backend toggle in background
    try {
      const res = await adminApi.toggleProductStatus(product.id, baseId, field);
      if (!res.success) {
        throw new Error(res.error || 'បរាជ័យក្នុងការផ្លាស់ប្តូរស្ថានភាព');
      }
    } catch (err) {
      // Revert if failed
      setProducts((prev) =>
        prev.map((p) => {
          if (p.base_product_id === baseId || p.id === product.id) {
            if (field === 'featured') return { ...p, featured: curVal };
            if (field === 'best_seller') return { ...p, best_seller: curVal };
            if (field === 'enabled') return { ...p, enabled: curVal };
            if (field === 'collection') return { ...p, show_in_collection: curVal };
          }
          return p;
        })
      );
      showToast(err.message || 'បរាជ័យក្នុងការផ្លាស់ប្តូរស្ថានភាព', 'error');
    }
  };

  // ──────────────────────────────────────────────
  // DELETE PRODUCT
  // ──────────────────────────────────────────────
  const handleDeleteProduct = async (product) => {
    try {
      const res = await adminApi.deleteProduct(product.id, product.base_product_id);
      if (res.success) {
        showToast('បានលុបផលិតផលជោគជ័យ!');
        setDeleteConfirm(null);
        loadData();
      }
    } catch (err) {
      showToast(err.message || 'បរាជ័យក្នុងការលុប', 'error');
    }
  };

  // ──────────────────────────────────────────────
  // CATEGORIES MANAGEMENT
  // ──────────────────────────────────────────────
  const openAddCategory = () => {
    setCatModalMode('add');
    setCatModalTab('en');
    setCatForm({
      base_category_id: 0,
      id: 0,
      name_en: '',
      description_en: '',
      name_km: '',
      description_km: '',
      image: '',
    });
    setCatModalOpen(true);
  };

  const openEditCategory = async (cat) => {
    setCatModalMode('edit');
    setCatModalTab('en');
    try {
      const res = await adminApi.getCategoryData(cat.base_category_id, cat.id);
      if (res.success) {
        const en = res.en || {};
        const km = res.km || {};
        setCatForm({
          base_category_id: cat.base_category_id || cat.id,
          id: cat.id,
          name_en: en.name || '',
          description_en: en.description || '',
          name_km: km.name || '',
          description_km: km.description || '',
          image: en.image || km.image || '',
        });
        setCatModalOpen(true);
      }
    } catch (err) {
      showToast('បរាជ័យក្នុងការទាញយកទិន្នន័យប្រភេទ: ' + err.message, 'error');
    }
  };

  const handleSaveCategory = async (e) => {
    e.preventDefault();
    if (!catForm.name_en.trim() && !catForm.name_km.trim()) {
      showToast('សូមបញ្ចូលឈ្មោះប្រភេទ (Category name required)', 'error');
      return;
    }
    setSavingCategory(true);
    try {
      const res = await adminApi.saveCategoryFull(catForm);
      if (res.success) {
        showToast(catModalMode === 'add' ? 'បានបន្ថែមប្រភេទថ្មីជោគជ័យ!' : 'បានកែប្រែប្រភេទជោគជ័យ!');
        setCatModalOpen(false);
        loadData();
      }
    } catch (err) {
      showToast(err.message || 'Error saving category', 'error');
    } finally {
      setSavingCategory(false);
    }
  };

  const handleDeleteCategory = async (cat) => {
    try {
      const res = await adminApi.deleteCategory(cat.id, cat.base_category_id);
      if (res.success) {
        showToast('បានលុបប្រភេទផលិតផលជោគជ័យ!');
        setDeleteConfirm(null);
        loadData();
      }
    } catch (err) {
      showToast(err.message || 'បរាជ័យក្នុងការលុប', 'error');
    }
  };

  // ──────────────────────────────────────────────
  // PAGINATION COMPUTATION
  // ──────────────────────────────────────────────
  const paginatedProducts = useMemo(() => {
    const start = (currentPage - 1) * perPage;
    return products.slice(start, start + perPage);
  }, [products, currentPage, perPage]);

  const totalPages = Math.ceil(products.length / perPage) || 1;

  return (
    <div className="space-y-6 animate-fade-in">
      {/* Toast Notification */}
      {toast && (
        <div
          className={`fixed top-5 right-5 z-50 px-4 py-3 rounded-2xl shadow-xl flex items-center gap-3 text-sm font-semibold border transition-all animate-bounce ${
            toast.type === 'error'
              ? 'bg-red-500 text-white border-red-400'
              : 'bg-emerald-600 text-white border-emerald-500'
          }`}
        >
          {toast.type === 'error' ? <AlertCircle size={20} /> : <CheckCircle2 size={20} />}
          <span>{toast.message}</span>
        </div>
      )}

      {/* Main Header with Top Tab Switcher */}
      <div className="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-black text-gray-900 flex items-center gap-2.5">
            <Package className="text-amber-500" size={28} />
            <span>ការគ្រប់គ្រងផលិតផល (Product Management)</span>
          </h1>
          <p className="text-xs text-gray-500 mt-1">
            គ្រប់គ្រងកាតាឡុកទំនិញ ប្រភេទ និងរូបភាព Hosting Media ទាំងភាសាខ្មែរ និងអង់គ្លេស
          </p>
        </div>

        {/* Top Tab Switcher (Products vs Categories) */}
        <div className="flex items-center gap-2 bg-gray-100 p-1.5 rounded-xl border border-gray-200">
          <button
            onClick={() => setActiveTab('products')}
            className={`flex items-center gap-2 px-4 py-2 rounded-lg text-xs font-bold transition-all ${
              activeTab === 'products'
                ? 'bg-amber-600 text-white shadow-sm shadow-amber-600/30'
                : 'text-gray-600 hover:text-gray-900 hover:bg-gray-200/60'
            }`}
          >
            <Package size={16} />
            <span>ផលិតផល (Products)</span>
            <span
              className={`px-1.5 py-0.5 rounded-full text-[10px] font-black ${
                activeTab === 'products' ? 'bg-amber-700 text-white' : 'bg-gray-200 text-gray-700'
              }`}
            >
              {products.length}
            </span>
          </button>

          <button
            onClick={() => setActiveTab('categories')}
            className={`flex items-center gap-2 px-4 py-2 rounded-lg text-xs font-bold transition-all ${
              activeTab === 'categories'
                ? 'bg-amber-600 text-white shadow-sm shadow-amber-600/30'
                : 'text-gray-600 hover:text-gray-900 hover:bg-gray-200/60'
            }`}
          >
            <Tags size={16} />
            <span>ប្រភេទ (Categories)</span>
            <span
              className={`px-1.5 py-0.5 rounded-full text-[10px] font-black ${
                activeTab === 'categories' ? 'bg-amber-700 text-white' : 'bg-gray-200 text-gray-700'
              }`}
            >
              {categories.length}
            </span>
          </button>
        </div>
      </div>

      {/* ────────────────────────────────────────────── */}
      {/* TAB 1: PRODUCTS TABLE                          */}
      {/* ────────────────────────────────────────────── */}
      {activeTab === 'products' && (
        <div className="space-y-4">
          {/* Action Bar: Filter, Search, Language, Add Product */}
          <div className="bg-white p-4 rounded-2xl border border-gray-200 shadow-sm flex flex-col lg:flex-row gap-3 items-center justify-between">
            {/* Search Form */}
            <form onSubmit={handleSearchSubmit} className="w-full lg:w-80 relative">
              <Search size={16} className="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400" />
              <input
                type="text"
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                placeholder="ស្វែងរកតាមឈ្មោះផលិតផល..."
                className="w-full pl-9 pr-4 py-2 text-xs bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all"
              />
            </form>

            {/* Category Filter */}
            <div className="w-full lg:w-auto flex flex-wrap items-center gap-2.5">
              <div className="flex items-center gap-1.5 text-xs text-gray-500 font-medium">
                <Filter size={14} />
                <span>ប្រភេទ:</span>
              </div>
              <select
                value={selectedCategory}
                onChange={(e) => {
                  setSelectedCategory(e.target.value);
                  setCurrentPage(1);
                }}
                className="text-xs bg-gray-50 border border-gray-200 rounded-xl px-3 py-2 text-gray-700 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500"
              >
                <option value="">ទាំងអស់ (All Categories)</option>
                {categories.map((c) => (
                  <option key={c.id} value={c.id}>
                    {c.name}
                  </option>
                ))}
              </select>

              {/* Language display switcher */}
              <div className="flex bg-gray-100 p-1 rounded-xl border border-gray-200 text-xs font-semibold">
                <button
                  type="button"
                  onClick={() => setLang('km')}
                  className={`px-2.5 py-1 rounded-lg transition-all ${
                    lang === 'km' ? 'bg-white text-amber-700 shadow-xs' : 'text-gray-500 hover:text-gray-800'
                  }`}
                >
                  🇰🇭 KM
                </button>
                <button
                  type="button"
                  onClick={() => setLang('en')}
                  className={`px-2.5 py-1 rounded-lg transition-all ${
                    lang === 'en' ? 'bg-white text-amber-700 shadow-xs' : 'text-gray-500 hover:text-gray-800'
                  }`}
                >
                  🇬🇧 EN
                </button>
              </div>

              {/* Add New Product Button */}
              <button
                type="button"
                onClick={openAddProduct}
                className="flex items-center gap-2 px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-xl text-xs font-bold shadow-sm shadow-amber-500/20 transition-all ml-auto cursor-pointer"
              >
                <Plus size={16} />
                <span>បន្ថែមផលិតផលថ្មី</span>
              </button>
            </div>
          </div>

          {/* Table Container */}
          <div className="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div className="overflow-x-auto">
              <table className="w-full text-left text-xs border-collapse">
                <thead className="bg-gray-50/80 border-b border-gray-200/80 text-gray-500 font-bold uppercase tracking-wider text-[11px]">
                  <tr>
                    <th className="py-3 px-2 w-8 text-center">
                      <GripVertical size={13} className="mx-auto text-gray-400" />
                    </th>
                    <th className="py-3 px-2 w-12 text-center">ID</th>
                    <th className="py-3 px-2 w-16 text-center">រូបភាព</th>
                    <th className="py-3 px-3 min-w-[140px]">ឈ្មោះផលិតផល</th>
                    <th className="py-3 px-3 max-w-[200px]">ការពិពណ៌នា</th>
                    <th className="py-3 px-2.5">ប្រភេទ</th>
                    <th className="py-3 px-2.5 whitespace-nowrap">តម្លៃ ($)</th>
                    <th className="py-3 px-2 text-center whitespace-nowrap">Featured</th>
                    <th className="py-3 px-2 text-center whitespace-nowrap">Best Seller</th>
                    <th className="py-3 px-2 text-center whitespace-nowrap">ស្ថានភាព</th>
                    <th className="py-3 px-3 text-center whitespace-nowrap">សកម្មភាព</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-100 text-gray-700">
                  {loading ? (
                    <tr>
                      <td colSpan="11" className="py-12 text-center text-gray-400">
                        <div className="w-7 h-7 border-2 border-amber-500 border-t-transparent rounded-full animate-spin mx-auto mb-2"></div>
                        <p className="text-xs">កំពុងផ្ទុកទិន្នន័យផលិតផល...</p>
                      </td>
                    </tr>
                  ) : paginatedProducts.length === 0 ? (
                    <tr>
                      <td colSpan="11" className="py-12 text-center text-gray-400">
                        <Package size={40} className="mx-auto mb-2 text-gray-300" />
                        <p className="font-semibold text-gray-600">មិនមានផលិតផលនៅឡើយទេ</p>
                        <p className="text-[11px] text-gray-400 mt-0.5">ចុច "បន្ថែមផលិតផលថ្មី" ដើម្បីបង្កើតផលិតផលដំបូងរបស់អ្នក</p>
                      </td>
                    </tr>
                  ) : (
                    paginatedProducts.map((p) => {
                      const imgUrl = formatImageUrl(p.image, 'products');

                      return (
                        <tr key={p.id} className="hover:bg-amber-50/20 transition-colors group">
                          {/* Drag handle */}
                          <td className="py-2.5 px-2 text-center text-gray-300 group-hover:text-gray-400 cursor-grab">
                            <GripVertical size={13} className="mx-auto" />
                          </td>

                          {/* ID */}
                          <td className="py-2.5 px-2 text-center font-mono font-bold text-gray-500">#{p.id}</td>

                          {/* Thumbnail Image with hover zoom */}
                          <td className="py-2.5 px-2 text-center">
                            <div className="w-11 h-11 rounded-xl border border-gray-200 bg-gray-50 p-1 flex items-center justify-center overflow-hidden relative shadow-xs mx-auto">
                              <img
                                src={imgUrl}
                                alt={p.name}
                                loading="lazy"
                                referrerPolicy="no-referrer"
                                className="w-full h-full object-contain transition-transform duration-300 group-hover:scale-110"
                                onError={handleImageError}
                              />
                            </div>
                          </td>

                          {/* Name (Bilingual preview) */}
                          <td className="py-2.5 px-3">
                            <div className="font-bold text-gray-900 text-sm">
                              {p.name || p.name_km || p.name_en || 'គ្មានឈ្មោះ'}
                            </div>
                            <div className="text-[11px] text-gray-400 flex items-center gap-1.5 mt-0.5">
                              <span className="text-gray-500 font-medium">EN:</span>
                              <span className="truncate max-w-[130px]">{p.name_en || '-'}</span>
                              <span>•</span>
                              <span className="text-gray-500 font-medium">KM:</span>
                              <span className="truncate max-w-[130px]">{p.name_km || '-'}</span>
                            </div>
                          </td>

                          {/* Description */}
                          <td className="py-2.5 px-3 max-w-[200px] truncate text-gray-500" title={p.description}>
                            {p.description || '-'}
                          </td>

                          {/* Category Badge */}
                          <td className="py-2.5 px-2.5">
                            {p.category_name ? (
                              <span className="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800">
                                {p.category_name}
                              </span>
                            ) : (
                              <span className="text-gray-400 text-[11px]">គ្មានប្រភេទ</span>
                            )}
                          </td>

                          {/* Price */}
                          <td className="py-2.5 px-2.5 font-bold text-emerald-600 text-sm whitespace-nowrap">
                            ${parseFloat(p.price || 0).toFixed(2)}
                          </td>

                          {/* Featured Badge */}
                          <td className="py-2.5 px-2 text-center whitespace-nowrap">
                            <span
                              className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold ${
                                Number(p.featured) === 1
                                  ? 'bg-amber-100 text-amber-800 border border-amber-200'
                                  : 'bg-gray-100 text-gray-400'
                              }`}
                            >
                              <Star size={11} className={Number(p.featured) === 1 ? 'fill-amber-500 text-amber-500' : ''} />
                              {Number(p.featured) === 1 ? 'Yes' : 'No'}
                            </span>
                          </td>

                          {/* Best Seller Badge */}
                          <td className="py-2.5 px-2 text-center whitespace-nowrap">
                            <span
                              className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold ${
                                Number(p.best_seller) === 1
                                  ? 'bg-emerald-100 text-emerald-800 border border-emerald-200'
                                  : 'bg-gray-100 text-gray-400'
                              }`}
                            >
                              <Award size={11} className={Number(p.best_seller) === 1 ? 'fill-emerald-500 text-emerald-500' : ''} />
                              {Number(p.best_seller) === 1 ? 'Yes' : 'No'}
                            </span>
                          </td>

                          {/* Enabled Badge */}
                          <td className="py-2.5 px-2 text-center whitespace-nowrap">
                            <span
                              className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold ${
                                Number(p.enabled) !== 0
                                  ? 'bg-blue-100 text-blue-800 border border-blue-200'
                                  : 'bg-gray-100 text-gray-400'
                              }`}
                            >
                              <Eye size={11} />
                              {Number(p.enabled) !== 0 ? 'Yes' : 'No'}
                            </span>
                          </td>

                          {/* Actions */}
                          <td className="py-2.5 px-3 text-center whitespace-nowrap">
                            <div className="inline-flex items-center gap-1 bg-gray-50 p-1 rounded-xl border border-gray-200/80 shadow-2xs">
                              {/* Toggle Featured */}
                              <button
                                type="button"
                                onClick={() => handleToggleStatus(p, 'featured')}
                                title={Number(p.featured) === 1 ? 'ដកចេញពី Featured' : 'ដាក់ជា Featured'}
                                className={`w-7 h-7 flex items-center justify-center rounded-lg transition-colors ${
                                  Number(p.featured) === 1
                                    ? 'bg-amber-100 text-amber-600 hover:bg-amber-200'
                                    : 'text-gray-400 hover:text-amber-600 hover:bg-gray-100'
                                }`}
                              >
                                <Star size={13} className={Number(p.featured) === 1 ? 'fill-amber-500' : ''} />
                              </button>

                              {/* Toggle Best Seller */}
                              <button
                                type="button"
                                onClick={() => handleToggleStatus(p, 'best_seller')}
                                title={Number(p.best_seller) === 1 ? 'ដកចេញពី Best Seller' : 'ដាក់ជា Best Seller'}
                                className={`w-7 h-7 flex items-center justify-center rounded-lg transition-colors ${
                                  Number(p.best_seller) === 1
                                    ? 'bg-emerald-100 text-emerald-600 hover:bg-emerald-200'
                                    : 'text-gray-400 hover:text-emerald-600 hover:bg-gray-100'
                                }`}
                              >
                                <Award size={13} className={Number(p.best_seller) === 1 ? 'fill-emerald-500' : ''} />
                              </button>

                              {/* Toggle Collection */}
                              <button
                                type="button"
                                onClick={() => handleToggleStatus(p, 'collection')}
                                title={p.show_in_collection ? 'ដកចេញពី Collection' : 'បង្ហាញក្នុង Collection (Syrup/Powder)'}
                                className={`w-7 h-7 flex items-center justify-center rounded-lg transition-colors ${
                                  p.show_in_collection
                                    ? 'bg-purple-100 text-purple-600 hover:bg-purple-200'
                                    : 'text-gray-400 hover:text-purple-600 hover:bg-gray-100'
                                }`}
                              >
                                <Layers size={13} />
                              </button>

                              {/* Detailed Specs Modal (Gear button) */}
                              <button
                                type="button"
                                onClick={() => openDetailedModal(p)}
                                title="កែប្រែព័ត៌មានលម្អិត (Detailed Product Specs)"
                                className="w-7 h-7 flex items-center justify-center rounded-lg text-cyan-600 hover:bg-cyan-50 transition-colors"
                              >
                                <Settings size={13} />
                              </button>

                              {/* Edit Drawer */}
                              <button
                                type="button"
                                onClick={() => openEditProduct(p)}
                                title="កែប្រែផលិតផល (Edit Product)"
                                className="w-7 h-7 flex items-center justify-center rounded-lg text-blue-600 hover:bg-blue-50 transition-colors"
                              >
                                <Edit2 size={13} />
                              </button>

                              {/* Toggle Enabled */}
                              <button
                                type="button"
                                onClick={() => handleToggleStatus(p, 'enabled')}
                                title={Number(p.enabled) !== 0 ? 'បិទដំណើរការ (Disable)' : 'បើកដំណើរការ (Enable)'}
                                className={`w-7 h-7 flex items-center justify-center rounded-lg transition-colors ${
                                  Number(p.enabled) !== 0
                                    ? 'text-gray-500 hover:text-gray-800'
                                    : 'bg-red-50 text-red-500 hover:bg-red-100'
                                }`}
                              >
                                {Number(p.enabled) !== 0 ? <Eye size={13} /> : <EyeOff size={13} />}
                              </button>

                              {/* Delete Product */}
                              <button
                                type="button"
                                onClick={() => setDeleteConfirm({ type: 'product', item: p })}
                                title="លុបផលិតផល"
                                className="w-7 h-7 flex items-center justify-center rounded-lg text-red-500 hover:bg-red-50 transition-colors"
                              >
                                <Trash2 size={13} />
                              </button>
                            </div>
                          </td>
                        </tr>
                      );
                    })
                  )}
                </tbody>
              </table>
            </div>

            {/* Pagination & Per Page Selector */}
            <div className="px-6 py-3.5 bg-gray-50/80 border-t border-gray-100 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-gray-500">
              <div className="flex items-center gap-3">
                <span>
                  បង្ហាញ {products.length > 0 ? (currentPage - 1) * perPage + 1 : 0} ដល់{' '}
                  {Math.min(currentPage * perPage, products.length)} នៃសរុប {products.length} ផលិតផល
                </span>
                <div className="flex items-center gap-1.5">
                  <span>បង្ហាញ:</span>
                  <select
                    value={perPage}
                    onChange={(e) => {
                      setPerPage(Number(e.target.value));
                      setCurrentPage(1);
                    }}
                    className="bg-white border border-gray-200 rounded-lg px-2 py-1 text-gray-700"
                  >
                    <option value={10}>10</option>
                    <option value={25}>25</option>
                    <option value={50}>50</option>
                    <option value={100}>100</option>
                  </select>
                </div>
              </div>

              {totalPages > 1 && (
                <div className="flex items-center gap-1">
                  <button
                    onClick={() => setCurrentPage((p) => Math.max(1, p - 1))}
                    disabled={currentPage === 1}
                    className="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 bg-white hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed"
                  >
                    <ChevronLeft size={14} />
                  </button>
                  {Array.from({ length: totalPages }, (_, i) => i + 1).map((page) => (
                    <button
                      key={page}
                      onClick={() => setCurrentPage(page)}
                      className={`w-8 h-8 rounded-lg text-xs font-bold transition-all ${
                        currentPage === page
                          ? 'bg-amber-600 text-white shadow-xs'
                          : 'bg-white border border-gray-200 hover:bg-gray-100 text-gray-700'
                      }`}
                    >
                      {page}
                    </button>
                  ))}
                  <button
                    onClick={() => setCurrentPage((p) => Math.min(totalPages, p + 1))}
                    disabled={currentPage === totalPages}
                    className="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 bg-white hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed"
                  >
                    <ChevronRight size={14} />
                  </button>
                </div>
              )}
            </div>
          </div>
        </div>
      )}

      {/* ────────────────────────────────────────────── */}
      {/* TAB 2: CATEGORIES TABLE                        */}
      {/* ────────────────────────────────────────────── */}
      {activeTab === 'categories' && (
        <div className="space-y-4">
          <div className="bg-white p-4 rounded-2xl border border-gray-200 shadow-sm flex items-center justify-between gap-4">
            <div>
              <h3 className="text-base font-bold text-gray-900 flex items-center gap-2">
                <Tags className="text-emerald-600" size={18} />
                <span>គ្រប់គ្រងប្រភេទផលិតផល (Manage Categories)</span>
              </h3>
              <p className="text-xs text-gray-500 mt-0.5">បង្កើត និងកែប្រែប្រភេទផលិតផលសម្រាប់កាតាឡុកទំនិញ</p>
            </div>
            <button
              onClick={openAddCategory}
              className="flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-sm shadow-emerald-600/20 transition-all cursor-pointer"
            >
              <Plus size={16} />
              <span>បន្ថែមប្រភេទថ្មី (Add Category)</span>
            </button>
          </div>

          <div className="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <table className="w-full text-left text-xs border-collapse">
              <thead className="bg-gray-50/80 border-b border-gray-200/80 text-gray-500 font-bold uppercase tracking-wider">
                <tr>
                  <th className="py-3.5 px-4 w-16">ID</th>
                  <th className="py-3.5 px-4">ឈ្មោះប្រភេទ</th>
                  <th className="py-3.5 px-4">ការពិពណ៌នា</th>
                  <th className="py-3.5 px-4 w-20">រូបភាព</th>
                  <th className="py-3.5 px-4 text-center">ចំនួនផលិតផល</th>
                  <th className="py-3.5 px-4 text-center w-28">សកម្មភាព</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100 text-gray-700">
                {categories.length === 0 ? (
                  <tr>
                    <td colSpan="6" className="py-12 text-center text-gray-400">
                      <Tags size={36} className="mx-auto mb-2 text-gray-300" />
                      <p className="font-semibold text-gray-600">គ្មានប្រភេទផលិតផលនៅឡើយទេ</p>
                    </td>
                  </tr>
                ) : (
                  categories.map((cat) => (
                    <tr key={cat.id} className="hover:bg-amber-50/20 transition-colors">
                      <td className="py-3 px-4 font-mono font-bold text-gray-500">#{cat.id}</td>
                      <td className="py-3 px-4 font-bold text-gray-900 text-sm">{cat.name}</td>
                      <td className="py-3 px-4 text-gray-500 max-w-sm truncate">{cat.description || '-'}</td>
                      <td className="py-3 px-4">
                        <div className="w-10 h-10 rounded-lg border border-gray-200 bg-gray-50 p-1 flex items-center justify-center overflow-hidden shadow-xs">
                          <img
                            src={formatImageUrl(cat.image, 'categories')}
                            alt={cat.name}
                            className="w-full h-full object-contain"
                            onError={handleImageError}
                          />
                        </div>
                      </td>
                      <td className="py-3 px-4 text-center">
                        <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-black bg-blue-100 text-blue-800">
                          {cat.product_count || 0} ផលិតផល
                        </span>
                      </td>
                      <td className="py-3 px-4 text-center">
                        <div className="inline-flex items-center gap-1.5">
                          <button
                            onClick={() => openEditCategory(cat)}
                            title="កែប្រែ"
                            className="w-8 h-8 flex items-center justify-center rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-100 transition-colors"
                          >
                            <Edit2 size={14} />
                          </button>
                          <button
                            onClick={() => setDeleteConfirm({ type: 'category', item: cat })}
                            title="លុប"
                            className="w-8 h-8 flex items-center justify-center rounded-lg bg-red-50 text-red-600 hover:bg-red-100 transition-colors"
                          >
                            <Trash2 size={14} />
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* ────────────────────────────────────────────── */}
      {/* SLIDE-OUT PRODUCT DRAWER (Add / Edit)          */}
      {/* ────────────────────────────────────────────── */}
      {drawerOpen && (
        <div className="fixed inset-0 z-50 flex justify-end bg-black/50 backdrop-blur-xs animate-fade-in">
          <div className="bg-white w-full max-w-3xl lg:max-w-4xl h-full shadow-2xl flex flex-col overflow-hidden animate-slide-left border-l border-gray-200">
            {/* Drawer Header */}
            <div className="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-gradient-to-r from-amber-500/10 via-amber-600/5 to-transparent">
              <div className="flex items-center gap-3">
                <div className="w-10 h-10 rounded-xl bg-amber-500 text-white flex items-center justify-center shadow-md shadow-amber-500/20">
                  <Package size={20} />
                </div>
                <div>
                  <div className="flex items-center gap-2">
                    <h3 className="font-bold text-gray-900 text-lg">
                      {drawerMode === 'add' ? 'បន្ថែមផលិតផលថ្មី (Add New Product)' : 'កែប្រែផលិតផល (Edit Product)'}
                    </h3>
                    {drawerLoading && (
                      <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold bg-amber-100 text-amber-800 animate-pulse">
                        <RotateCcw size={10} className="animate-spin" /> កំពុងផ្ទុកទិន្នន័យ...
                      </span>
                    )}
                  </div>
                  <p className="text-xs text-gray-500">គាំទ្រការកែប្រែទិន្នន័យទំនិញ ភាសាអង់គ្លេស និងខ្មែរព្រមគ្នា ដូច admin/products.php</p>
                </div>
              </div>
              <button
                onClick={() => setDrawerOpen(false)}
                className="w-8 h-8 flex items-center justify-center rounded-xl bg-gray-100 hover:bg-gray-200 text-gray-500 hover:text-gray-800 transition-colors"
              >
                <X size={18} />
              </button>
            </div>

            {/* Language Tabs Bar inside Drawer */}
            <div className="bg-gray-50 px-6 py-2.5 border-b border-gray-200/80 flex items-center justify-between">
              <span className="text-xs font-bold text-gray-400 uppercase tracking-wider">ភាសាព័ត៌មាន (Content Language)</span>
              <div className="flex bg-white p-1 rounded-xl border border-gray-200 shadow-2xs">
                <button
                  type="button"
                  onClick={() => setDrawerTab('en')}
                  className={`flex items-center gap-1.5 px-3 py-1 rounded-lg text-xs font-bold transition-all cursor-pointer ${
                    drawerTab === 'en'
                      ? 'bg-amber-600 text-white shadow-xs'
                      : 'text-gray-600 hover:text-gray-900'
                  }`}
                >
                  <span>🇬🇧</span> English (EN)
                </button>
                <button
                  type="button"
                  onClick={() => setDrawerTab('km')}
                  className={`flex items-center gap-1.5 px-3 py-1 rounded-lg text-xs font-bold transition-all cursor-pointer ${
                    drawerTab === 'km'
                      ? 'bg-amber-600 text-white shadow-xs'
                      : 'text-gray-600 hover:text-gray-900'
                  }`}
                >
                  <span>🇰🇭</span> ភាសាខ្មែរ (KM)
                </button>
              </div>
            </div>

            {/* Form Content */}
            <form onSubmit={handleSaveProduct} id="productDrawerForm" className="flex-1 overflow-y-auto p-6 space-y-6">
              {/* Bilingual Inputs Pane */}
              <div className="bg-amber-50/40 p-4 rounded-2xl border border-amber-200/60 space-y-4">
                <div className="flex items-center justify-between text-xs font-bold text-amber-900">
                  <div className="flex items-center gap-2">
                    <Sparkles size={14} className="text-amber-600" />
                    <span>ព័ត៌មានតាមភាសា: {drawerTab === 'en' ? 'English Content (EN)' : 'ខ្លឹមសារភាសាខ្មែរ (KM)'}</span>
                  </div>
                  <span className="text-[11px] text-amber-700 bg-amber-100/80 px-2 py-0.5 rounded-md">
                    {drawerTab === 'en' ? 'EN Mode' : 'KM Mode'}
                  </span>
                </div>

                {drawerTab === 'en' ? (
                  <div className="space-y-3">
                    <div>
                      <label className="block text-xs font-bold text-gray-700 mb-1">
                        Product Name (EN) <span className="text-red-500">*</span>
                      </label>
                      <input
                        type="text"
                        value={productForm.name_en}
                        onChange={(e) => setProductForm({ ...productForm, name_en: e.target.value })}
                        placeholder="e.g. KouPrey Signature Blend"
                        required
                        className="w-full px-3.5 py-2 text-xs bg-white border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500"
                      />
                    </div>
                    <div>
                      <label className="block text-xs font-bold text-gray-700 mb-1">
                        Short Description (EN)
                      </label>
                      <textarea
                        value={productForm.description_en}
                        onChange={(e) => setProductForm({ ...productForm, description_en: e.target.value })}
                        rows={2}
                        placeholder="Brief summary for product card..."
                        className="w-full px-3.5 py-2 text-xs bg-white border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500"
                      />
                    </div>
                    <div>
                      <label className="block text-xs font-bold text-gray-700 mb-1">Weight / Size (EN)</label>
                      <input
                        type="text"
                        value={productForm.weight_en}
                        onChange={(e) => setProductForm({ ...productForm, weight_en: e.target.value })}
                        placeholder="e.g. 750ml, 250g or 1kg"
                        className="w-full px-3.5 py-2 text-xs bg-white border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500"
                      />
                    </div>
                  </div>
                ) : (
                  <div className="space-y-3">
                    <div>
                      <label className="block text-xs font-bold text-gray-700 mb-1">
                        ឈ្មោះផលិតផល (KM) <span className="text-red-500">*</span>
                      </label>
                      <input
                        type="text"
                        value={productForm.name_km}
                        onChange={(e) => setProductForm({ ...productForm, name_km: e.target.value })}
                        placeholder="ឧ. គ្រឿងបន្ថែមរស់ជាតិគោកព្រៃ ពិសេស"
                        className="w-full px-3.5 py-2 text-xs bg-white border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500"
                      />
                    </div>
                    <div>
                      <label className="block text-xs font-bold text-gray-700 mb-1">
                        ការពិពណ៌នាសង្ខេប (KM)
                      </label>
                      <textarea
                        value={productForm.description_km}
                        onChange={(e) => setProductForm({ ...productForm, description_km: e.target.value })}
                        rows={2}
                        placeholder="សេចក្តីសង្ខេបសម្រាប់បង្ហាញលើកាត..."
                        className="w-full px-3.5 py-2 text-xs bg-white border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500"
                      />
                    </div>
                    <div>
                      <label className="block text-xs font-bold text-gray-700 mb-1">ទម្ងន់ / ទំហំ (KM)</label>
                      <input
                        type="text"
                        value={productForm.weight_km}
                        onChange={(e) => setProductForm({ ...productForm, weight_km: e.target.value })}
                        placeholder="ឧ. ៧៥០ មីលីលីត្រ, ២៥០ ក្រាម ឬ ១ គីឡូក្រាម"
                        className="w-full px-3.5 py-2 text-xs bg-white border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500"
                      />
                    </div>
                  </div>
                )}

                {/* Collapsible Detailed Specifications (Parity with admin/products.php) */}
                <div className="pt-2 border-t border-amber-200/50">
                  <button
                    type="button"
                    onClick={() => {
                      if (typeof setShowDetailedSpecs === 'function') {
                        setShowDetailedSpecs((prev) => !prev);
                      }
                    }}
                    className="w-full flex items-center justify-between px-3 py-2 rounded-xl bg-amber-100/70 hover:bg-amber-100 text-amber-900 text-xs font-bold transition cursor-pointer"
                  >
                    <div className="flex items-center gap-2">
                      <Settings size={13} className="text-amber-700" />
                      <span>លក្ខណៈបច្ចេកទេសលម្អិត (Detailed Specifications & Notes)</span>
                    </div>
                    {showDetailedSpecs ? <ChevronUp size={14} /> : <ChevronDown size={14} />}
                  </button>

                  {showDetailedSpecs && (
                    <div className="mt-3 p-3 bg-white rounded-xl border border-amber-200/70 space-y-3">
                      {drawerTab === 'en' ? (
                        <>
                          <div>
                            <label className="block text-xs font-bold text-gray-700 mb-1">Detailed Description (EN)</label>
                            <textarea
                              value={productForm.detailed_description_en}
                              onChange={(e) => setProductForm({ ...productForm, detailed_description_en: e.target.value })}
                              rows={3}
                              placeholder="Full story, details, tasting notes..."
                              className="w-full px-3 py-1.5 text-xs bg-gray-50 border border-gray-200 rounded-lg"
                            />
                          </div>
                          <div className="grid grid-cols-2 gap-3">
                            <div>
                              <label className="block text-xs font-bold text-gray-700 mb-1">Ingredients (EN)</label>
                              <textarea
                                value={productForm.ingredients_en}
                                onChange={(e) => setProductForm({ ...productForm, ingredients_en: e.target.value })}
                                rows={2}
                                placeholder="e.g. 100% Arabica beans..."
                                className="w-full px-3 py-1.5 text-xs bg-gray-50 border border-gray-200 rounded-lg"
                              />
                            </div>
                            <div>
                              <label className="block text-xs font-bold text-gray-700 mb-1">Brewing Instructions (EN)</label>
                              <textarea
                                value={productForm.brewing_instructions_en}
                                onChange={(e) => setProductForm({ ...productForm, brewing_instructions_en: e.target.value })}
                                rows={2}
                                placeholder="e.g. 15g coffee per 250ml..."
                                className="w-full px-3 py-1.5 text-xs bg-gray-50 border border-gray-200 rounded-lg"
                              />
                            </div>
                          </div>
                          <div className="grid grid-cols-2 gap-3">
                            <div>
                              <label className="block text-xs font-bold text-gray-700 mb-1">Origin (EN)</label>
                              <input
                                type="text"
                                value={productForm.origin_en}
                                onChange={(e) => setProductForm({ ...productForm, origin_en: e.target.value })}
                                placeholder="e.g. Mondulkiri, Cambodia"
                                className="w-full px-3 py-1.5 text-xs bg-gray-50 border border-gray-200 rounded-lg"
                              />
                            </div>
                            <div>
                              <label className="block text-xs font-bold text-gray-700 mb-1">Tasting Notes (EN)</label>
                              <input
                                type="text"
                                value={productForm.tasting_notes_en}
                                onChange={(e) => setProductForm({ ...productForm, tasting_notes_en: e.target.value })}
                                placeholder="e.g. Caramel, Chocolate, Floral"
                                className="w-full px-3 py-1.5 text-xs bg-gray-50 border border-gray-200 rounded-lg"
                              />
                            </div>
                          </div>
                        </>
                      ) : (
                        <>
                          <div>
                            <label className="block text-xs font-bold text-gray-700 mb-1">ការពិពណ៌នាលម្អិត (KM)</label>
                            <textarea
                              value={productForm.detailed_description_km}
                              onChange={(e) => setProductForm({ ...productForm, detailed_description_km: e.target.value })}
                              rows={3}
                              placeholder="ព័ត៌មានលម្អិតបន្ថែមអំពីរឿងរ៉ាវផលិតផល..."
                              className="w-full px-3 py-1.5 text-xs bg-gray-50 border border-gray-200 rounded-lg"
                            />
                          </div>
                          <div className="grid grid-cols-2 gap-3">
                            <div>
                              <label className="block text-xs font-bold text-gray-700 mb-1">គ្រឿងផ្សំ (KM)</label>
                              <textarea
                                value={productForm.ingredients_km}
                                onChange={(e) => setProductForm({ ...productForm, ingredients_km: e.target.value })}
                                rows={2}
                                placeholder="គ្រឿងផ្សំសំខាន់ៗ..."
                                className="w-full px-3 py-1.5 text-xs bg-gray-50 border border-gray-200 rounded-lg"
                              />
                            </div>
                            <div>
                              <label className="block text-xs font-bold text-gray-700 mb-1">ការណែនាំអំពីការឆុង (KM)</label>
                              <textarea
                                value={productForm.brewing_instructions_km}
                                onChange={(e) => setProductForm({ ...productForm, brewing_instructions_km: e.target.value })}
                                rows={2}
                                placeholder="របៀបឆុង ឬលាយភេសជ្ជៈ..."
                                className="w-full px-3 py-1.5 text-xs bg-gray-50 border border-gray-200 rounded-lg"
                              />
                            </div>
                          </div>
                          <div className="grid grid-cols-2 gap-3">
                            <div>
                              <label className="block text-xs font-bold text-gray-700 mb-1">ប្រភពដើម (KM)</label>
                              <input
                                type="text"
                                value={productForm.origin_km}
                                onChange={(e) => setProductForm({ ...productForm, origin_km: e.target.value })}
                                placeholder="ឧ. ខេត្តមណ្ឌលគិរី"
                                className="w-full px-3 py-1.5 text-xs bg-gray-50 border border-gray-200 rounded-lg"
                              />
                            </div>
                            <div>
                              <label className="block text-xs font-bold text-gray-700 mb-1">កំណត់ចំណាំរសជាតិ (KM)</label>
                              <input
                                type="text"
                                value={productForm.tasting_notes_km}
                                onChange={(e) => setProductForm({ ...productForm, tasting_notes_km: e.target.value })}
                                placeholder="ឧ. ការ៉ាមែល, សូកូឡា, ផ្អែមស្រទន់"
                                className="w-full px-3 py-1.5 text-xs bg-gray-50 border border-gray-200 rounded-lg"
                              />
                            </div>
                          </div>
                        </>
                      )}
                    </div>
                  )}
                </div>
              </div>

              {/* General Settings: Price, Category, Roast Level */}
              <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                  <label className="block text-xs font-bold text-gray-700 mb-1">
                    តម្លៃ Price ($) <span className="text-red-500">*</span>
                  </label>
                  <div className="relative">
                    <span className="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 font-bold">$</span>
                    <input
                      type="number"
                      step="0.01"
                      value={productForm.price}
                      onChange={(e) => setProductForm({ ...productForm, price: e.target.value })}
                      placeholder="0.00"
                      required
                      className="w-full pl-8 pr-4 py-2 text-xs bg-gray-50 border border-gray-200 rounded-xl font-bold text-gray-900 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500"
                    />
                  </div>
                </div>

                <div>
                  <label className="block text-xs font-bold text-gray-700 mb-1">ប្រភេទ (Category)</label>
                  <select
                    value={productForm.category_id}
                    onChange={(e) => setProductForm({ ...productForm, category_id: e.target.value })}
                    className="w-full px-3.5 py-2 text-xs bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500"
                  >
                    <option value="">គ្មានប្រភេទ (No Category)</option>
                    {categories.map((c) => (
                      <option key={c.id} value={c.id}>
                        {c.name}
                      </option>
                    ))}
                  </select>
                </div>

                <div>
                  <label className="block text-xs font-bold text-gray-700 mb-1">កម្រិតលីង (Roast Level)</label>
                  <select
                    value={productForm.roast_level}
                    onChange={(e) => setProductForm({ ...productForm, roast_level: e.target.value })}
                    className="w-full px-3.5 py-2 text-xs bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500"
                  >
                    <option value="">គ្មាន / មិនជ្រើសរើស</option>
                    <option value="Light">Light Roast</option>
                    <option value="Medium-Light">Medium-Light</option>
                    <option value="Medium">Medium</option>
                    <option value="Medium-Dark">Medium-Dark</option>
                    <option value="Dark">Dark Roast</option>
                    <option value="French">French (Very Dark)</option>
                    <option value="Unroasted">Unroasted / Green</option>
                  </select>
                </div>
              </div>

              {/* Product Image Section with Hosting Media Browser */}
              <div className="bg-gray-50/80 p-4 rounded-2xl border border-gray-200/80 space-y-3">
                <div className="flex items-center justify-between">
                  <label className="block text-xs font-bold text-gray-800">រូបភាពផលិតផល (Product Image)</label>
                  <button
                    type="button"
                    onClick={() =>
                      openMediaBrowser((selectedUrl) => {
                        setImagePreview('');
                        setProductForm((prev) => ({ ...prev, image: selectedUrl }));
                        showToast('បានជ្រើសរើសរូបភាពជោគជ័យ!');
                      }, 'ជ្រើសរើសរូបភាពផលិតផលពី Hosting Media')
                    }
                    className="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold shadow-xs transition-all cursor-pointer"
                  >
                    <FolderOpen size={13} />
                    <span>រុករករូបភាព Hosting Media</span>
                  </button>
                </div>

                <div className="flex items-center gap-4">
                  {/* Thumbnail Preview */}
                  <div className="relative w-20 h-20 rounded-xl border-2 border-dashed border-gray-300 bg-white p-1 flex items-center justify-center overflow-hidden flex-shrink-0 shadow-xs">
                    {uploadingImage && (
                      <div className="absolute inset-0 bg-white/90 backdrop-blur-[1px] flex flex-col items-center justify-center z-10 px-1 text-center">
                        <Loader2 size={18} className="animate-spin text-amber-600 mb-0.5" />
                        <span className="text-[8.5px] font-bold text-amber-800 leading-tight">
                          {uploadProgressText || 'កំពុង Upload...'}
                        </span>
                      </div>
                    )}
                    <img
                      key={imagePreview || productForm.image || 'empty-preview'}
                      src={imagePreview || formatImageUrl(productForm.image, 'products')}
                      alt="Preview"
                      referrerPolicy="no-referrer"
                      className="max-h-full max-w-full object-contain"
                      onError={handleImageError}
                    />
                  </div>

                  <div className="flex-1 space-y-2">
                    <input
                      type="text"
                      value={productForm.image}
                      onChange={(e) => {
                        setImagePreview('');
                        setProductForm({ ...productForm, image: e.target.value });
                      }}
                      placeholder="e.g. /uploads/product-coffee.jpg"
                      className="w-full px-3 py-1.5 text-xs bg-white border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 font-mono"
                    />
                    <div className="flex flex-wrap items-center gap-2">
                      <label className={`inline-flex items-center gap-1.5 px-2.5 py-1 bg-white border border-gray-200 hover:bg-gray-100 rounded-lg text-xs font-semibold text-gray-700 cursor-pointer transition shadow-2xs ${uploadingImage ? 'opacity-60 pointer-events-none' : ''}`}>
                        {uploadingImage ? (
                          <Loader2 size={12} className="animate-spin text-amber-600" />
                        ) : (
                          <Upload size={12} />
                        )}
                        <span>{uploadingImage ? (uploadProgressText || 'កំពុង Upload...') : 'Upload ថ្មី'}</span>
                        <input
                          type="file"
                          accept="image/*"
                          disabled={uploadingImage}
                          className="hidden"
                          onChange={async (e) => {
                            const rawFile = e.target.files?.[0];
                            if (rawFile) {
                              const localUrl = URL.createObjectURL(rawFile);
                              setImagePreview(localUrl);
                              setUploadingImage(true);
                              setImageCompressionStats(null);

                              // 1. Detect if image is already transparent to avoid wasting API Key credits
                              const isAlreadyTrans = await checkImageTransparency(rawFile);
                              if (isAlreadyTrans) {
                                setUploadProgressText('កំពុងបម្លែងជា WebP...');
                              } else if (autoRemoveBg) {
                                setUploadProgressText('Remove BG & WebP...');
                              } else {
                                setUploadProgressText('កំពុង Upload & WebP...');
                              }

                              try {
                                const file = await compressImageClient(rawFile);
                                const res = await adminApi.uploadImage(file, 'product', {
                                  removeBg: autoRemoveBg && !isAlreadyTrans,
                                  alreadyTransparent: isAlreadyTrans,
                                });

                                if (res.success && res.path) {
                                  const cleanPath = res.path.includes('/uploads/')
                                    ? res.path.substring(res.path.indexOf('/uploads/'))
                                    : res.path;
                                  setProductForm((prev) => ({ ...prev, image: cleanPath }));

                                  setImageCompressionStats({
                                    original: res.original_size_formatted || '',
                                    compressed: res.compressed_size_formatted || '',
                                    savedPercent: res.saved_percent || 0,
                                    alreadyTransparent: Boolean(res.already_transparent || isAlreadyTrans),
                                    bgRemoved: Boolean(res.bg_removed),
                                  });

                                  if (res.already_transparent || isAlreadyTrans) {
                                    showToast(`រូបភាព Transparent ស្រាប់! បានបម្លែងជា WebP (${res.compressed_size_formatted || ''}) ដោយមិនខាត API Key 💡`);
                                  } else if (res.bg_removed) {
                                    showToast(`បាន Remove Background & Compress ជា WebP ជោគជ័យ! (${res.saved_percent}% សន្សំ) 🎉`);
                                  } else if (res.warning && res.warning !== 'already_transparent') {
                                    showToast(`បាន Upload ជា WebP (${res.warning})`, 'warning');
                                  } else {
                                    showToast('បានផ្ទុកឡើងរូបភាព (WebP) ជោគជ័យ!');
                                  }
                                } else {
                                  showToast(res.error || 'Upload failed', 'error');
                                  setImagePreview('');
                                }
                              } catch (err) {
                                showToast('Upload failed: ' + err.message, 'error');
                                setImagePreview('');
                              } finally {
                                setUploadingImage(false);
                                setUploadProgressText('');
                                e.target.value = '';
                              }
                            }
                          }}
                        />
                      </label>
                      <span className="text-[11px] text-gray-400">ឬជ្រើសរើសពី Hosting Media ខាងលើ</span>
                    </div>

                    {/* Auto Remove BG Toggle Switch */}
                    <div className="pt-0.5">
                      <label className="inline-flex items-center gap-2 cursor-pointer select-none bg-amber-50/80 border border-amber-200/90 px-2.5 py-1 rounded-lg hover:bg-amber-100/70 transition">
                        <input
                          type="checkbox"
                          checked={autoRemoveBg}
                          onChange={(e) => setAutoRemoveBg(e.target.checked)}
                          className="w-3.5 h-3.5 text-amber-600 rounded focus:ring-amber-500 cursor-pointer"
                        />
                        <span className="text-[11px] font-semibold text-amber-950 flex items-center gap-1.5">
                          <Sparkles size={13} className="text-amber-600 animate-pulse" />
                          <span>Auto Remove Background (Remove.bg AI) & WebP</span>
                        </span>
                      </label>
                    </div>

                    {/* Auto Compression & Transparency Metrics Badge */}
                    {imageCompressionStats && (
                      <div className="flex flex-wrap items-center gap-2 pt-1">
                        <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-md bg-emerald-50 text-emerald-800 border border-emerald-200 text-[11px] font-medium">
                          ⚡ WebP: <strong>{imageCompressionStats.compressed}</strong>
                          {imageCompressionStats.savedPercent > 0 && (
                            <span className="text-emerald-600 font-bold">(កាត់បន្ថយ {imageCompressionStats.savedPercent}% ពី {imageCompressionStats.original})</span>
                          )}
                        </span>
                        {imageCompressionStats.alreadyTransparent && (
                          <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-md bg-sky-50 text-sky-800 border border-sky-200 text-[11px] font-medium">
                            🛡️ Transparent ស្រាប់ (មិនខាត API Key)
                          </span>
                        )}
                        {imageCompressionStats.bgRemoved && (
                          <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-md bg-purple-50 text-purple-800 border border-purple-200 text-[11px] font-medium">
                            ✨ Remove.bg កាត់ BG រួចរាល់
                          </span>
                        )}
                      </div>
                    )}
                  </div>
                </div>
              </div>

              {/* Status Switches */}
              <div className="grid grid-cols-3 gap-3 bg-gray-50 p-4 rounded-2xl border border-gray-200">
                <label className="flex items-center gap-2 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={Boolean(productForm.featured)}
                    onChange={(e) => setProductForm({ ...productForm, featured: e.target.checked ? 1 : 0 })}
                    className="w-4 h-4 text-amber-600 rounded focus:ring-amber-500"
                  />
                  <span className="text-xs font-bold text-gray-800">Featured (លេចធ្លោ)</span>
                </label>

                <label className="flex items-center gap-2 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={Boolean(productForm.best_seller)}
                    onChange={(e) => setProductForm({ ...productForm, best_seller: e.target.checked ? 1 : 0 })}
                    className="w-4 h-4 text-emerald-600 rounded focus:ring-emerald-500"
                  />
                  <span className="text-xs font-bold text-gray-800">Best Seller (លក់ដាច់)</span>
                </label>

                <label className="flex items-center gap-2 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={Boolean(productForm.enabled)}
                    onChange={(e) => setProductForm({ ...productForm, enabled: e.target.checked ? 1 : 0 })}
                    className="w-4 h-4 text-blue-600 rounded focus:ring-blue-500"
                  />
                  <span className="text-xs font-bold text-gray-800">Enabled (បង្ហាញលើ Web)</span>
                </label>
              </div>

              {/* ────────────────────────────────────────────── */}
              {/* CUSTOM FIELDS SECTION (100% Identical to admin/products.php) */}
              {/* ────────────────────────────────────────────── */}
              <div className="bg-white p-4 sm:p-5 rounded-2xl border border-gray-200 shadow-2xs space-y-4">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-3 border-b border-gray-100">
                  <div className="flex items-center gap-2">
                    <div className="w-7 h-7 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center">
                      <ListPlus size={16} />
                    </div>
                    <div>
                      <h4 className="text-xs font-bold text-gray-900">Custom Fields (វាលទិន្នន័យបន្ថែម)</h4>
                      <p className="text-[11px] text-gray-400">គាំទ្រទាំងប្រភេទ Text និង Nutrition Table (តារាងអាហារូបត្ថម្ភ) ដូច admin/products.php</p>
                    </div>
                  </div>

                  <div className="flex items-center gap-1.5 flex-wrap">
                    {/* Add Text Field */}
                    <button
                      type="button"
                      onClick={() => {
                        const newField = {
                          id: `field_${Date.now()}_${Math.random().toString(36).substr(2, 7)}`,
                          name_en: '',
                          name_km: '',
                          type: 'text',
                          value_en: '',
                          value_km: '',
                          table_rows: [],
                          is_simple: false,
                        };
                        setProductForm({
                          ...productForm,
                          custom_fields: [...productForm.custom_fields, newField],
                        });
                      }}
                      className="px-2.5 py-1 text-xs font-bold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 rounded-lg flex items-center gap-1 cursor-pointer transition"
                    >
                      <Plus size={13} /> Text
                    </button>

                    {/* Add Table Field */}
                    <button
                      type="button"
                      onClick={() => {
                        const newField = {
                          id: `field_${Date.now()}_${Math.random().toString(36).substr(2, 7)}`,
                          name_en: 'Nutrition Facts',
                          name_km: 'អាហារូបត្ថម្ភ',
                          type: 'table',
                          value_en: '',
                          value_km: '',
                          table_rows: [
                            {
                              id: `row_0_${Date.now()}`,
                              label_en: 'Energy',
                              label_km: 'ថាមពល',
                              values: [{ en: '', km: '' }, { en: '', km: '' }],
                            },
                          ],
                          is_simple: false,
                        };
                        setProductForm({
                          ...productForm,
                          custom_fields: [...productForm.custom_fields, newField],
                        });
                      }}
                      className="px-2.5 py-1 text-xs font-bold text-purple-700 bg-purple-50 hover:bg-purple-100 border border-purple-200 rounded-lg flex items-center gap-1 cursor-pointer transition"
                    >
                      <TableIcon size={13} /> Nutrition Table
                    </button>

                    {/* Copy Custom Fields */}
                    <button
                      type="button"
                      onClick={() => {
                        setCopyProductSearch('');
                        setCopyModalOpen(true);
                      }}
                      className="px-2.5 py-1 text-xs font-bold text-blue-700 bg-blue-50 hover:bg-blue-100 border border-blue-200 rounded-lg flex items-center gap-1 cursor-pointer transition"
                      title="ចម្លង Custom Fields ពីផលិតផលផ្សេង"
                    >
                      <Copy size={13} /> ចម្លង (Copy)
                    </button>
                  </div>
                </div>

                {/* Custom Fields List */}
                {productForm.custom_fields.length === 0 ? (
                  <div className="py-6 text-center border-2 border-dashed border-gray-200 rounded-xl bg-gray-50/50">
                    <p className="text-xs text-gray-500 font-medium">មិនទាន់មាន Custom Fields នៅឡើយទេ</p>
                    <p className="text-[11px] text-gray-400 mt-1">ចុចប៊ូតុងខាងលើដើម្បីបន្ថែមវាលថ្មី (Text ឬ Nutrition Table) ឬចម្លងពីផលិតផលផ្សេង</p>
                  </div>
                ) : (
                  <div className="space-y-4">
                    {productForm.custom_fields.map((cf, idx) => (
                      <div
                        key={cf.id || idx}
                        className="bg-gray-50/80 rounded-xl border border-gray-200 p-3.5 space-y-3 relative group hover:border-gray-300 transition"
                      >
                        {/* Field Header */}
                        <div className="flex items-center justify-between pb-2 border-b border-gray-200/80 text-xs">
                          <div className="flex items-center gap-2">
                            <span className="font-mono text-[10px] text-gray-400 bg-white px-1.5 py-0.5 rounded border border-gray-200">
                              #{idx + 1}
                            </span>
                            <span className="font-mono text-[11px] text-gray-500 font-bold truncate max-w-[150px]">
                              {cf.id}
                            </span>
                            <span
                              className={`px-2 py-0.5 rounded text-[10px] font-bold ${
                                cf.type === 'table'
                                  ? 'bg-purple-100 text-purple-700'
                                  : cf.is_simple
                                  ? 'bg-gray-100 text-gray-700'
                                  : 'bg-emerald-100 text-emerald-700'
                              }`}
                            >
                              {cf.type === 'table' ? 'Nutrition Table' : cf.is_simple ? 'Flag' : 'Text Field'}
                            </span>
                          </div>

                          <div className="flex items-center gap-2">
                            {/* Switch Type */}
                            {!cf.is_simple && (
                              <select
                                value={cf.type}
                                onChange={(e) => {
                                  const updated = [...productForm.custom_fields];
                                  const newType = e.target.value;
                                  updated[idx].type = newType;
                                  if (newType === 'table' && (!updated[idx].table_rows || updated[idx].table_rows.length === 0)) {
                                    updated[idx].table_rows = [
                                      {
                                        id: `row_0_${Date.now()}`,
                                        label_en: '',
                                        label_km: '',
                                        values: [{ en: '', km: '' }],
                                      },
                                    ];
                                  }
                                  setProductForm({ ...productForm, custom_fields: updated });
                                }}
                                className="text-[11px] bg-white border border-gray-200 rounded px-2 py-0.5 text-gray-700"
                              >
                                <option value="text">Text (អត្ថបទ)</option>
                                <option value="table">Nutrition Table</option>
                              </select>
                            )}

                            {/* Remove Field */}
                            <button
                              type="button"
                              onClick={() => {
                                const updated = productForm.custom_fields.filter((_, i) => i !== idx);
                                setProductForm({ ...productForm, custom_fields: updated });
                              }}
                              className="w-6 h-6 flex items-center justify-center text-red-500 hover:bg-red-50 rounded-lg transition"
                              title="លុបវាលនេះ"
                            >
                              <Trash2 size={13} />
                            </button>
                          </div>
                        </div>

                        {/* Legacy Simple Field */}
                        {cf.is_simple ? (
                          <div className="flex items-center gap-2">
                            <input
                              type="text"
                              value={cf.name_en || cf.id}
                              onChange={(e) => {
                                const updated = [...productForm.custom_fields];
                                updated[idx].name_en = e.target.value;
                                setProductForm({ ...productForm, custom_fields: updated });
                              }}
                              placeholder="Key"
                              className="w-1/3 px-3 py-1.5 text-xs bg-white border border-gray-200 rounded-lg font-mono"
                            />
                            <input
                              type="text"
                              value={cf.value_en}
                              onChange={(e) => {
                                const updated = [...productForm.custom_fields];
                                updated[idx].value_en = e.target.value;
                                setProductForm({ ...productForm, custom_fields: updated });
                              }}
                              placeholder="Value"
                              className="flex-1 px-3 py-1.5 text-xs bg-white border border-gray-200 rounded-lg font-mono"
                            />
                          </div>
                        ) : (
                          <>
                            {/* Field Name (Bilingual) */}
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-2">
                              <div>
                                <label className="block text-[11px] font-bold text-gray-600 mb-1">
                                  Field Name (English)
                                </label>
                                <input
                                  type="text"
                                  value={cf.name_en}
                                  onChange={(e) => {
                                    const updated = [...productForm.custom_fields];
                                    updated[idx].name_en = e.target.value;
                                    setProductForm({ ...productForm, custom_fields: updated });
                                  }}
                                  placeholder="e.g. Ingredients: or Shelf life:"
                                  className="w-full px-3 py-1.5 text-xs bg-white border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500/20"
                                />
                              </div>
                              <div>
                                <label className="block text-[11px] font-bold text-gray-600 mb-1">
                                  ឈ្មោះវាល (ភាសាខ្មែរ)
                                </label>
                                <input
                                  type="text"
                                  value={cf.name_km}
                                  onChange={(e) => {
                                    const updated = [...productForm.custom_fields];
                                    updated[idx].name_km = e.target.value;
                                    setProductForm({ ...productForm, custom_fields: updated });
                                  }}
                                  placeholder="ឧ. គ្រឿងផ្សំសំខាន់ៗ៖ ឬ អាយុកាល៖"
                                  className="w-full px-3 py-1.5 text-xs bg-white border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500/20"
                                />
                              </div>
                            </div>

                            {/* Field Value: TEXT TYPE */}
                            {cf.type === 'text' && (
                              <div className="grid grid-cols-1 md:grid-cols-2 gap-2 pt-1">
                                <div>
                                  <label className="block text-[11px] font-bold text-gray-600 mb-1">
                                    Value (English)
                                  </label>
                                  <textarea
                                    value={cf.value_en}
                                    onChange={(e) => {
                                      const updated = [...productForm.custom_fields];
                                      updated[idx].value_en = e.target.value;
                                      setProductForm({ ...productForm, custom_fields: updated });
                                    }}
                                    rows={2}
                                    placeholder="Enter description or content in English..."
                                    className="w-full px-3 py-1.5 text-xs bg-white border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500/20"
                                  />
                                </div>
                                <div>
                                  <label className="block text-[11px] font-bold text-gray-600 mb-1">
                                    តម្លៃ / ការពិពណ៌នា (ភាសាខ្មែរ)
                                  </label>
                                  <textarea
                                    value={cf.value_km}
                                    onChange={(e) => {
                                      const updated = [...productForm.custom_fields];
                                      updated[idx].value_km = e.target.value;
                                      setProductForm({ ...productForm, custom_fields: updated });
                                    }}
                                    rows={2}
                                    placeholder="បញ្ចូលការពិពណ៌នាជាភាសាខ្មែរ..."
                                    className="w-full px-3 py-1.5 text-xs bg-white border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500/20"
                                  />
                                </div>
                              </div>
                            )}

                            {/* Field Value: NUTRITION TABLE TYPE */}
                            {cf.type === 'table' && (
                              <div className="bg-white rounded-xl border border-purple-200/80 p-3 space-y-3">
                                <div className="flex items-center justify-between pb-2 border-b border-gray-100">
                                  <span className="text-[11px] font-bold text-purple-900 flex items-center gap-1.5">
                                    <TableIcon size={12} className="text-purple-600" />
                                    <span>Nutrition Table Rows (ជួរទិន្នន័យតារាង)</span>
                                  </span>
                                  <button
                                    type="button"
                                    onClick={() => {
                                      const updated = [...productForm.custom_fields];
                                      const rows = updated[idx].table_rows || [];
                                      updated[idx].table_rows = [
                                        ...rows,
                                        {
                                          id: `row_${rows.length}_${Date.now()}`,
                                          label_en: '',
                                          label_km: '',
                                          values: [{ en: '', km: '' }, { en: '', km: '' }],
                                        },
                                      ];
                                      setProductForm({ ...productForm, custom_fields: updated });
                                    }}
                                    className="px-2 py-0.5 text-[11px] font-bold text-purple-700 bg-purple-50 hover:bg-purple-100 border border-purple-200 rounded flex items-center gap-1 cursor-pointer transition"
                                  >
                                    <Plus size={11} /> បន្ថែមជួរ (Add Row)
                                  </button>
                                </div>

                                {/* Rows */}
                                <div className="space-y-2">
                                  {(cf.table_rows || []).map((row, rIdx) => (
                                    <div
                                      key={row.id || rIdx}
                                      className="p-2 bg-gray-50 rounded-lg border border-gray-200 flex flex-col sm:flex-row items-start sm:items-center gap-2 text-xs"
                                    >
                                      {/* Row Labels */}
                                      <div className="flex items-center gap-1 flex-1 w-full sm:w-auto">
                                        <input
                                          type="text"
                                          value={row.label_en}
                                          onChange={(e) => {
                                            const updated = [...productForm.custom_fields];
                                            updated[idx].table_rows[rIdx].label_en = e.target.value;
                                            setProductForm({ ...productForm, custom_fields: updated });
                                          }}
                                          placeholder="Label EN (e.g. Energy)"
                                          className="flex-1 px-2 py-1 text-xs bg-white border border-gray-200 rounded"
                                        />
                                        <input
                                          type="text"
                                          value={row.label_km}
                                          onChange={(e) => {
                                            const updated = [...productForm.custom_fields];
                                            updated[idx].table_rows[rIdx].label_km = e.target.value;
                                            setProductForm({ ...productForm, custom_fields: updated });
                                          }}
                                          placeholder="Label KM (e.g. ថាមពល)"
                                          className="flex-1 px-2 py-1 text-xs bg-white border border-gray-200 rounded"
                                        />
                                      </div>

                                      {/* Value pairs */}
                                      <div className="flex items-center gap-1 flex-1 w-full sm:w-auto flex-wrap">
                                        {(row.values || []).map((vp, vIdx) => (
                                          <div key={vIdx} className="flex items-center gap-1">
                                            <input
                                              type="text"
                                              value={vp.en}
                                              onChange={(e) => {
                                                const updated = [...productForm.custom_fields];
                                                updated[idx].table_rows[rIdx].values[vIdx].en = e.target.value;
                                                setProductForm({ ...productForm, custom_fields: updated });
                                              }}
                                              placeholder={`Val ${vIdx + 1} (e.g. 867KJ)`}
                                              className="w-24 px-2 py-1 text-xs bg-white border border-gray-200 rounded font-mono"
                                            />
                                            {row.values.length > 1 && (
                                              <button
                                                type="button"
                                                onClick={() => {
                                                  const updated = [...productForm.custom_fields];
                                                  updated[idx].table_rows[rIdx].values = row.values.filter(
                                                    (_, i) => i !== vIdx
                                                  );
                                                  setProductForm({ ...productForm, custom_fields: updated });
                                                }}
                                                className="text-gray-400 hover:text-red-500 p-0.5"
                                                title="Remove column"
                                              >
                                                <X size={11} />
                                              </button>
                                            )}
                                          </div>
                                        ))}
                                        <button
                                          type="button"
                                          onClick={() => {
                                            const updated = [...productForm.custom_fields];
                                            updated[idx].table_rows[rIdx].values.push({ en: '', km: '' });
                                            setProductForm({ ...productForm, custom_fields: updated });
                                          }}
                                          className="px-1.5 py-0.5 text-[10px] text-gray-500 bg-white border border-gray-200 rounded hover:bg-gray-100"
                                          title="Add value column (e.g. NRV%)"
                                        >
                                          + Col
                                        </button>
                                      </div>

                                      {/* Remove Row Button */}
                                      <button
                                        type="button"
                                        onClick={() => {
                                          const updated = [...productForm.custom_fields];
                                          updated[idx].table_rows = updated[idx].table_rows.filter(
                                            (_, i) => i !== rIdx
                                          );
                                          setProductForm({ ...productForm, custom_fields: updated });
                                        }}
                                        className="text-red-400 hover:text-red-600 p-1 self-end sm:self-center"
                                        title="Remove row"
                                      >
                                        <X size={13} />
                                      </button>
                                    </div>
                                  ))}
                                </div>
                              </div>
                            )}
                          </>
                        )}
                      </div>
                    ))}
                  </div>
                )}
              </div>

              {/* ────────────────────────────────────────────── */}
              {/* RELATED PRODUCTS SECTION                       */}
              {/* ────────────────────────────────────────────── */}
              <div className="bg-white p-4 rounded-2xl border border-gray-200 space-y-3">
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <Package size={14} className="text-blue-600" />
                    <label className="text-xs font-bold text-gray-800">ផលិតផលពាក់ព័ន្ធ (Related Products)</label>
                  </div>
                  <span className="text-[11px] text-gray-400">
                    {productForm.related_products.length} ផលិតផលដែលបានជ្រើស
                  </span>
                </div>

                {/* Search & Add */}
                <div className="relative">
                  <input
                    type="text"
                    value={relatedSearch}
                    onChange={(e) => setRelatedSearch(e.target.value)}
                    placeholder="ស្វែងរកឈ្មោះផលិតផលដើម្បីភ្ជាប់ពាក់ព័ន្ធ..."
                    className="w-full px-3 py-1.5 text-xs bg-gray-50 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                  />
                  {relatedSearch.trim() && (
                    <div className="absolute left-0 right-0 top-full mt-1 bg-white border border-gray-200 rounded-xl shadow-lg z-30 max-h-48 overflow-y-auto divide-y divide-gray-100">
                      {products
                        .filter((p) => {
                          const name = (p.name || '').toLowerCase();
                          const q = relatedSearch.toLowerCase();
                          const isSelf = (p.base_product_id || p.id) === productForm.base_product_id;
                          const isAlready = productForm.related_products.some(
                            (r) => r.base_id == (p.base_product_id || p.id)
                          );
                          return !isSelf && !isAlready && name.includes(q);
                        })
                        .slice(0, 8)
                        .map((p) => (
                          <button
                            key={p.id}
                            type="button"
                            onClick={() => {
                              const newRel = {
                                base_id: p.base_product_id || p.id,
                                name: p.name,
                                custom_url: '',
                                custom_image: p.image || '',
                                custom_image_url: '',
                              };
                              setProductForm({
                                ...productForm,
                                related_products: [...productForm.related_products, newRel],
                              });
                              setRelatedSearch('');
                            }}
                            className="w-full px-3 py-2 text-left hover:bg-amber-50/50 flex items-center justify-between text-xs transition"
                          >
                            <span className="font-medium text-gray-800">{p.name}</span>
                            <span className="text-gray-400 text-[11px]">${Number(p.price).toFixed(2)}</span>
                          </button>
                        ))}
                    </div>
                  )}
                </div>

                {/* Selected Related Products Chips */}
                {productForm.related_products.length > 0 ? (
                  <div className="flex flex-wrap gap-2 pt-1">
                    {productForm.related_products.map((rel, rIdx) => (
                      <div
                        key={rIdx}
                        className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-blue-50 border border-blue-200 text-blue-900 text-xs font-medium"
                      >
                        <span>{rel.name || `Product #${rel.base_id}`}</span>
                        <button
                          type="button"
                          onClick={() => {
                            const updated = productForm.related_products.filter((_, i) => i !== rIdx);
                            setProductForm({ ...productForm, related_products: updated });
                          }}
                          className="w-4 h-4 flex items-center justify-center rounded-full hover:bg-blue-200 text-blue-700 transition"
                        >
                          <X size={11} />
                        </button>
                      </div>
                    ))}
                  </div>
                ) : (
                  <p className="text-[11px] text-gray-400">មិនទាន់មានផលិតផលពាក់ព័ន្ធត្រូវបានជ្រើសរើសនៅឡើយ</p>
                )}
              </div>
            </form>

            {/* Drawer Footer */}
            <div className="px-6 py-4 bg-gray-50 border-t border-gray-100 flex items-center justify-end gap-3">
              <button
                type="button"
                onClick={() => setDrawerOpen(false)}
                className="px-4 py-2 rounded-xl text-xs font-semibold text-gray-600 hover:bg-gray-200 transition-colors cursor-pointer"
              >
                បោះបង់ (Cancel)
              </button>
              <button
                type="submit"
                form="productDrawerForm"
                disabled={savingProduct}
                className="flex items-center gap-2 px-6 py-2 rounded-xl bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold shadow-md shadow-amber-500/20 transition-all cursor-pointer disabled:opacity-50"
              >
                {savingProduct && <div className="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin"></div>}
                <span>{drawerMode === 'add' ? 'បន្ថែមផលិតផល (Add Product)' : 'ធ្វើបច្ចុប្បន្នភាព (Update Product)'}</span>
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ────────────────────────────────────────────── */}
      {/* COPY CUSTOM FIELDS MODAL                       */}
      {/* ────────────────────────────────────────────── */}
      {copyModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4 animate-fade-in">
          <div className="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden border border-gray-200 animate-scale-up">
            <div className="px-5 py-4 border-b border-gray-100 flex items-center justify-between bg-gradient-to-r from-blue-500/10 to-transparent">
              <div className="flex items-center gap-2.5">
                <div className="w-8 h-8 rounded-lg bg-blue-600 text-white flex items-center justify-center shadow-sm">
                  <Copy size={16} />
                </div>
                <div>
                  <h4 className="font-bold text-gray-900 text-sm">ចម្លង Custom Fields ពីផលិតផលផ្សេង</h4>
                  <p className="text-[11px] text-gray-500">ជ្រើសរើសផលិតផលដើម្បីចម្លងវាលទិន្នន័យ (Copy Fields)</p>
                </div>
              </div>
              <button
                type="button"
                onClick={() => setCopyModalOpen(false)}
                className="w-7 h-7 flex items-center justify-center rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-500"
              >
                <X size={16} />
              </button>
            </div>

            <div className="p-4 space-y-3">
              <div className="relative">
                <Search size={14} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                <input
                  type="text"
                  value={copyProductSearch}
                  onChange={(e) => setCopyProductSearch(e.target.value)}
                  placeholder="ស្វែងរកតាមឈ្មោះផលិតផល..."
                  className="w-full pl-9 pr-3 py-2 text-xs bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                />
              </div>

              <div className="max-h-64 overflow-y-auto space-y-1 divide-y divide-gray-50">
                {products
                  .filter((p) => {
                    const isSelf = (p.base_product_id || p.id) === productForm.base_product_id;
                    const matches = (p.name || '').toLowerCase().includes(copyProductSearch.toLowerCase());
                    return !isSelf && matches;
                  })
                  .map((p) => (
                    <div
                      key={p.id}
                      className="p-2.5 rounded-xl hover:bg-blue-50/50 flex items-center justify-between transition"
                    >
                      <div className="flex items-center gap-2.5">
                        <img
                          src={formatImageUrl(p.image, 'products')}
                          alt={p.name}
                          className="w-8 h-8 rounded-lg object-contain bg-gray-100 border border-gray-200 p-0.5"
                          onError={handleImageError}
                        />
                        <div>
                          <p className="text-xs font-bold text-gray-800">{p.name}</p>
                          <p className="text-[10px] text-gray-400">ID #{p.base_product_id || p.id} • ${Number(p.price).toFixed(2)}</p>
                        </div>
                      </div>

                      <button
                        type="button"
                        onClick={async () => {
                          try {
                            const baseId = p.base_product_id || p.id;
                            const res = await adminApi.getProductData(baseId);
                            if (res.success) {
                              const cfRaw = res.en?.custom_fields || res.km?.custom_fields || p.custom_fields || '{}';
                              const parsed = parseCustomFields(cfRaw);
                              if (parsed.length === 0) {
                                showToast('ផលិតផលនេះគ្មាន Custom Fields សម្រាប់ចម្លងទេ', 'error');
                                return;
                              }
                              setProductForm((prev) => ({
                                ...prev,
                                custom_fields: parsed,
                              }));
                              setCopyModalOpen(false);
                              showToast(`បានចម្លង ${parsed.length} Custom Fields ពី "${p.name}" ជោគជ័យ!`);
                            }
                          } catch (err) {
                            showToast('បរាជ័យក្នុងការចម្លង: ' + err.message, 'error');
                          }
                        }}
                        className="px-3 py-1 rounded-lg text-xs font-bold bg-blue-600 hover:bg-blue-700 text-white shadow-xs cursor-pointer transition flex items-center gap-1"
                      >
                        <Copy size={11} /> ចម្លង
                      </button>
                    </div>
                  ))}
              </div>
            </div>

            <div className="px-4 py-3 bg-gray-50 border-t border-gray-100 flex justify-end">
              <button
                type="button"
                onClick={() => setCopyModalOpen(false)}
                className="px-4 py-1.5 rounded-xl text-xs font-semibold text-gray-600 hover:bg-gray-200 transition cursor-pointer"
              >
                បិទ (Close)
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ────────────────────────────────────────────── */}
      {/* DETAILED SPECS MODAL (Gear button)             */}
      {/* ────────────────────────────────────────────── */}
      {detailedModalOpen && detailedForm && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4 animate-fade-in">
          <div className="bg-white rounded-2xl shadow-2xl w-full max-w-3xl max-h-[90vh] flex flex-col overflow-hidden border border-gray-200">
            {/* Modal Header */}
            <div className="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-gray-900 text-white">
              <div className="flex items-center gap-3">
                <div className="w-9 h-9 rounded-xl bg-cyan-500 text-white flex items-center justify-center shadow">
                  <Settings size={18} />
                </div>
                <div>
                  <h3 className="font-bold text-base">ព័ត៌មានលម្អិតផលិតផល (Detailed Product Information)</h3>
                  <p className="text-xs text-gray-400">
                    {detailedForm.name_en || detailedForm.name_km} (#{detailedForm.base_product_id})
                  </p>
                </div>
              </div>
              <button
                onClick={() => setDetailedModalOpen(false)}
                className="w-8 h-8 flex items-center justify-center rounded-xl bg-gray-800 hover:bg-gray-700 text-gray-300 hover:text-white"
              >
                <X size={16} />
              </button>
            </div>

            {/* Language Switcher */}
            <div className="bg-gray-100 px-6 py-2.5 border-b border-gray-200 flex items-center justify-center">
              <div className="flex bg-white p-1 rounded-xl shadow-2xs">
                <button
                  type="button"
                  onClick={() => setDetailedModalTab('en')}
                  className={`px-4 py-1.5 rounded-lg text-xs font-bold transition-all ${
                    detailedModalTab === 'en' ? 'bg-cyan-600 text-white shadow-xs' : 'text-gray-600'
                  }`}
                >
                  🇬🇧 English (EN)
                </button>
                <button
                  type="button"
                  onClick={() => setDetailedModalTab('km')}
                  className={`px-4 py-1.5 rounded-lg text-xs font-bold transition-all ${
                    detailedModalTab === 'km' ? 'bg-cyan-600 text-white shadow-xs' : 'text-gray-600'
                  }`}
                >
                  🇰🇭 ភាសាខ្មែរ (KM)
                </button>
              </div>
            </div>

            {/* Modal Body */}
            <form onSubmit={handleSaveDetailed} id="detailedSpecsForm" className="flex-1 overflow-y-auto p-6 space-y-4">
              {detailedModalTab === 'en' ? (
                <div className="space-y-4">
                  <div>
                    <label className="block text-xs font-bold text-gray-700 mb-1">Detailed Description (EN)</label>
                    <textarea
                      rows={3}
                      value={detailedForm.detailed_description_en}
                      onChange={(e) => setDetailedForm({ ...detailedForm, detailed_description_en: e.target.value })}
                      placeholder="Rich description..."
                      className="w-full px-3.5 py-2 text-xs bg-gray-50 border border-gray-200 rounded-xl"
                    />
                  </div>
                  <div className="grid grid-cols-2 gap-3">
                    <div>
                      <label className="block text-xs font-bold text-gray-700 mb-1">Ingredients (EN)</label>
                      <input
                        type="text"
                        value={detailedForm.ingredients_en}
                        onChange={(e) => setDetailedForm({ ...detailedForm, ingredients_en: e.target.value })}
                        placeholder="e.g. 100% Arabica Beans"
                        className="w-full px-3 py-1.5 text-xs bg-gray-50 border border-gray-200 rounded-xl"
                      />
                    </div>
                    <div>
                      <label className="block text-xs font-bold text-gray-700 mb-1">Origin (EN)</label>
                      <input
                        type="text"
                        value={detailedForm.origin_en}
                        onChange={(e) => setDetailedForm({ ...detailedForm, origin_en: e.target.value })}
                        placeholder="e.g. Mondulkiri, Cambodia"
                        className="w-full px-3 py-1.5 text-xs bg-gray-50 border border-gray-200 rounded-xl"
                      />
                    </div>
                    <div>
                      <label className="block text-xs font-bold text-gray-700 mb-1">Brewing Instructions (EN)</label>
                      <input
                        type="text"
                        value={detailedForm.brewing_instructions_en}
                        onChange={(e) => setDetailedForm({ ...detailedForm, brewing_instructions_en: e.target.value })}
                        placeholder="e.g. Pour over, 92°C water"
                        className="w-full px-3 py-1.5 text-xs bg-gray-50 border border-gray-200 rounded-xl"
                      />
                    </div>
                    <div>
                      <label className="block text-xs font-bold text-gray-700 mb-1">Tasting Notes (EN)</label>
                      <input
                        type="text"
                        value={detailedForm.tasting_notes_en}
                        onChange={(e) => setDetailedForm({ ...detailedForm, tasting_notes_en: e.target.value })}
                        placeholder="e.g. Caramel, nutty, chocolate"
                        className="w-full px-3 py-1.5 text-xs bg-gray-50 border border-gray-200 rounded-xl"
                      />
                    </div>
                  </div>
                </div>
              ) : (
                <div className="space-y-4">
                  <div>
                    <label className="block text-xs font-bold text-gray-700 mb-1">ការពិពណ៌នាលម្អិត (KM)</label>
                    <textarea
                      rows={3}
                      value={detailedForm.detailed_description_km}
                      onChange={(e) => setDetailedForm({ ...detailedForm, detailed_description_km: e.target.value })}
                      placeholder="ការពិពណ៌នាលម្អិត..."
                      className="w-full px-3.5 py-2 text-xs bg-gray-50 border border-gray-200 rounded-xl"
                    />
                  </div>
                  <div className="grid grid-cols-2 gap-3">
                    <div>
                      <label className="block text-xs font-bold text-gray-700 mb-1">គ្រឿងផ្សំ (KM)</label>
                      <input
                        type="text"
                        value={detailedForm.ingredients_km}
                        onChange={(e) => setDetailedForm({ ...detailedForm, ingredients_km: e.target.value })}
                        placeholder="ឧ. គ្រឿងបន្ថែមរស់ជាតិអារ៉ាប៊ីកា ១០០%"
                        className="w-full px-3 py-1.5 text-xs bg-gray-50 border border-gray-200 rounded-xl"
                      />
                    </div>
                    <div>
                      <label className="block text-xs font-bold text-gray-700 mb-1">ប្រភពដើម (KM)</label>
                      <input
                        type="text"
                        value={detailedForm.origin_km}
                        onChange={(e) => setDetailedForm({ ...detailedForm, origin_km: e.target.value })}
                        placeholder="ឧ. ខេត្តមណ្ឌលគិរី"
                        className="w-full px-3 py-1.5 text-xs bg-gray-50 border border-gray-200 rounded-xl"
                      />
                    </div>
                    <div>
                      <label className="block text-xs font-bold text-gray-700 mb-1">ការណែនាំឆុង (KM)</label>
                      <input
                        type="text"
                        value={detailedForm.brewing_instructions_km}
                        onChange={(e) => setDetailedForm({ ...detailedForm, brewing_instructions_km: e.target.value })}
                        placeholder="ឧ. ទឹកក្តៅ ៩២ អង្សាសេ"
                        className="w-full px-3 py-1.5 text-xs bg-gray-50 border border-gray-200 rounded-xl"
                      />
                    </div>
                    <div>
                      <label className="block text-xs font-bold text-gray-700 mb-1">កំណត់ចំណាំរសជាតិ (KM)</label>
                      <input
                        type="text"
                        value={detailedForm.tasting_notes_km}
                        onChange={(e) => setDetailedForm({ ...detailedForm, tasting_notes_km: e.target.value })}
                        placeholder="ឧ. ក្លិនការ៉ាមែល ផ្អែមឈ្ងុយ"
                        className="w-full px-3 py-1.5 text-xs bg-gray-50 border border-gray-200 rounded-xl"
                      />
                    </div>
                  </div>
                </div>
              )}

              {/* Roast Level Dropdown */}
              <div className="pt-2">
                <label className="block text-xs font-bold text-gray-700 mb-1">កម្រិតលីង (Roast Level)</label>
                <select
                  value={detailedForm.roast_level}
                  onChange={(e) => setDetailedForm({ ...detailedForm, roast_level: e.target.value })}
                  className="w-full px-3 py-2 text-xs bg-gray-50 border border-gray-200 rounded-xl"
                >
                  <option value="">ជ្រើសរើស Roast Level</option>
                  <option value="Light">Light</option>
                  <option value="Medium">Medium</option>
                  <option value="Medium-Dark">Medium-Dark</option>
                  <option value="Dark">Dark</option>
                </select>
              </div>
            </form>

            {/* Modal Footer */}
            <div className="px-6 py-4 bg-gray-50 border-t border-gray-100 flex items-center justify-end gap-3">
              <button
                type="button"
                onClick={() => setDetailedModalOpen(false)}
                className="px-4 py-2 rounded-xl text-xs font-semibold text-gray-600 hover:bg-gray-200"
              >
                បោះបង់
              </button>
              <button
                type="submit"
                form="detailedSpecsForm"
                disabled={savingDetailed}
                className="flex items-center gap-2 px-6 py-2 rounded-xl bg-cyan-600 hover:bg-cyan-700 text-white text-xs font-bold shadow-md"
              >
                {savingDetailed && <div className="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin"></div>}
                <span>រក្សាទុកព័ត៌មានលម្អិត</span>
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ────────────────────────────────────────────── */}
      {/* CATEGORY ADD / EDIT MODAL                     */}
      {/* ────────────────────────────────────────────── */}
      {catModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4 animate-fade-in">
          <div className="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden border border-gray-200">
            <div className="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-emerald-600 text-white">
              <h3 className="font-bold text-base">
                {catModalMode === 'add' ? 'បន្ថែមប្រភេទថ្មី (Add Category)' : 'កែប្រែប្រភេទ (Edit Category)'}
              </h3>
              <button onClick={() => setCatModalOpen(false)} className="text-white hover:text-gray-200">
                <X size={18} />
              </button>
            </div>

            <form onSubmit={handleSaveCategory} className="p-6 space-y-4">
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-bold text-gray-700 mb-1">
                    Category Name (EN) <span className="text-red-500">*</span>
                  </label>
                  <input
                    type="text"
                    value={catForm.name_en}
                    onChange={(e) => setCatForm({ ...catForm, name_en: e.target.value })}
                    placeholder="e.g. Whole Bean Coffee"
                    required
                    className="w-full px-3 py-2 text-xs bg-gray-50 border border-gray-200 rounded-xl"
                  />
                </div>
                <div>
                  <label className="block text-xs font-bold text-gray-700 mb-1">
                    ឈ្មោះប្រភេទ (KM) <span className="text-red-500">*</span>
                  </label>
                  <input
                    type="text"
                    value={catForm.name_km}
                    onChange={(e) => setCatForm({ ...catForm, name_km: e.target.value })}
                    placeholder="ឧ. គ្រឿងបន្ថែមរស់ជាតិសុទ្ធ"
                    className="w-full px-3 py-2 text-xs bg-gray-50 border border-gray-200 rounded-xl"
                  />
                </div>
              </div>

              <div>
                <label className="block text-xs font-bold text-gray-700 mb-1">Description (EN)</label>
                <textarea
                  rows={2}
                  value={catForm.description_en}
                  onChange={(e) => setCatForm({ ...catForm, description_en: e.target.value })}
                  className="w-full px-3 py-2 text-xs bg-gray-50 border border-gray-200 rounded-xl"
                />
              </div>

              <div>
                <label className="block text-xs font-bold text-gray-700 mb-1">ការពិពណ៌នា (KM)</label>
                <textarea
                  rows={2}
                  value={catForm.description_km}
                  onChange={(e) => setCatForm({ ...catForm, description_km: e.target.value })}
                  className="w-full px-3 py-2 text-xs bg-gray-50 border border-gray-200 rounded-xl"
                />
              </div>

              <div>
                <div className="flex items-center justify-between mb-1">
                  <label className="block text-xs font-bold text-gray-700">រូបភាពប្រភេទ (Category Image)</label>
                  <button
                    type="button"
                    onClick={() =>
                      openMediaBrowser((url) => {
                        setCatForm((prev) => ({ ...prev, image: url }));
                      }, 'ជ្រើសរើសរូបភាពប្រភេទពី Hosting')
                    }
                    className="text-xs text-emerald-600 hover:text-emerald-700 font-bold flex items-center gap-1"
                  >
                    <FolderOpen size={13} /> រុករក Hosting Media
                  </button>
                </div>
                <input
                  type="text"
                  value={catForm.image}
                  onChange={(e) => setCatForm({ ...catForm, image: e.target.value })}
                  placeholder="/kouprey/public/assets/images/categories/..."
                  className="w-full px-3 py-2 text-xs bg-gray-50 border border-gray-200 rounded-xl font-mono"
                />
              </div>

              <div className="pt-3 flex items-center justify-end gap-3">
                <button
                  type="button"
                  onClick={() => setCatModalOpen(false)}
                  className="px-4 py-2 rounded-xl text-xs font-semibold text-gray-600 hover:bg-gray-100"
                >
                  បោះបង់
                </button>
                <button
                  type="submit"
                  disabled={savingCategory}
                  className="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-md cursor-pointer"
                >
                  {savingCategory ? 'កំពុងរក្សាទុក...' : 'រក្សាទុកប្រភេទ'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* ────────────────────────────────────────────── */}
      {/* DELETE CONFIRMATION DIALOG                     */}
      {/* ────────────────────────────────────────────── */}
      {deleteConfirm && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4 animate-fade-in">
          <div className="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 border border-gray-200 text-center">
            <div className="w-12 h-12 rounded-full bg-red-100 text-red-600 flex items-center justify-center mx-auto mb-3">
              <Trash2 size={24} />
            </div>
            <h4 className="font-bold text-gray-900 text-base mb-1">
              តើអ្នកពិតជាចង់លុប {deleteConfirm.type === 'product' ? 'ផលិតផល' : 'ប្រភេទ'} នេះមែនទេ?
            </h4>
            <p className="text-xs text-gray-500 mb-6">
              សកម្មភាពនេះនឹងលុបទិន្នន័យទាំងពីរភាសា (EN & KM) ហើយមិនអាចត្រឡប់ក្រោយវិញបានឡើយ។
            </p>
            <div className="flex items-center justify-center gap-3">
              <button
                onClick={() => setDeleteConfirm(null)}
                className="px-4 py-2 rounded-xl text-xs font-semibold text-gray-600 hover:bg-gray-100"
              >
                បោះបង់ (Cancel)
              </button>
              <button
                onClick={() => {
                  if (deleteConfirm.type === 'product') {
                    handleDeleteProduct(deleteConfirm.item);
                  } else {
                    handleDeleteCategory(deleteConfirm.item);
                  }
                }}
                className="px-5 py-2 rounded-xl bg-red-600 hover:bg-red-700 text-white text-xs font-bold shadow-md cursor-pointer"
              >
                យល់ព្រមលុប (Yes, Delete)
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ────────────────────────────────────────────── */}
      {/* REUSABLE MEDIA BROWSER MODAL (Hosting Media)  */}
      {/* ────────────────────────────────────────────── */}
      <MediaBrowserModal
        isOpen={mediaModalOpen}
        title={mediaModalTitle}
        onClose={() => setMediaModalOpen(false)}
        onSelectImage={(url) => {
          if (mediaTargetCallback) {
            mediaTargetCallback(url);
          }
        }}
      />
    </div>
  );
}

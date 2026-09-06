/**
 * KouPrey Admin API Client
 * Handles authenticated API calls to /admin-api.php with credentials
 */

const API_BASE = '/admin-api.php';

async function request(action, options = {}) {
  const { method = 'GET', body = null, isFormData = false, params = {} } = options;

  const url = new URL(API_BASE, window.location.origin);
  url.searchParams.set('action', action);
  Object.entries(params).forEach(([k, v]) => {
    if (v !== undefined && v !== null && v !== '') {
      url.searchParams.set(k, v);
    }
  });

  const headers = {};
  if (!isFormData) {
    headers['Content-Type'] = 'application/json';
  }

  const response = await fetch(url.toString(), {
    method,
    headers,
    credentials: 'include', // Send and receive session cookies
    body: isFormData ? body : (body ? JSON.stringify(body) : null),
  });

  const text = await response.text();
  let data;
  try {
    data = JSON.parse(text);
  } catch {
    if (!response.ok) {
      throw new Error(`Server error (${response.status}): Server returned HTML or unexpected error. Please make sure admin-api.php is deployed on the server.`);
    }
    throw new Error(`Unexpected server response: ${text.substring(0, 100)}...`);
  }

  if (!response.ok && !data.success) {
    throw new Error(data.error || `HTTP error ${response.status}`);
  }
  return data;
}

export const adminApi = {
  // Auth
  login: (username, password) =>
    request('login', { method: 'POST', body: { username, password } }),
  
  checkSession: () =>
    request('check_session'),

  logout: () =>
    request('logout', { method: 'POST' }),

  // Stats
  getStats: () =>
    request('get_stats'),

  // Products
  getProducts: (params = {}) =>
    request('get_all_products', { params }),

  saveProduct: (product) =>
    request('save_product', { method: 'POST', body: product }),

  deleteProduct: (id, base_product_id) =>
    request('delete_product', { method: 'POST', body: { id, base_product_id } }),

  // Categories
  getCategories: (lang = 'km') =>
    request('get_categories', { params: { lang } }),

  saveCategory: (category) =>
    request('save_category', { method: 'POST', body: category }),

  deleteCategory: (id) =>
    request('delete_category', { method: 'POST', body: { id } }),

  // Reviews
  getReviews: () =>
    request('get_all_reviews_admin'),

  updateReview: (id, status) =>
    request('update_review', { method: 'POST', body: { id, status } }),

  deleteReview: (id) =>
    request('delete_review', { method: 'POST', body: { id } }),

  // Features
  getFeatures: (lang = 'km') =>
    request('get_features_admin', { params: { lang } }),

  saveFeature: (feature) =>
    request('save_feature', { method: 'POST', body: feature }),

  deleteFeature: (id) =>
    request('delete_feature', { method: 'POST', body: { id } }),

  // Settings
  getSettings: (lang) =>
    request('get_all_settings', { params: lang ? { lang } : {} }),

  saveSetting: (key, value, language = 'km') =>
    request('save_setting', { method: 'POST', body: { key, value, language } }),

  saveSettingsBulk: (items) =>
    request('save_settings_bulk', { method: 'POST', body: items }),

  // About
  getAbout: () =>
    request('get_about'),

  saveAbout: (aboutData) =>
    request('save_about', { method: 'POST', body: aboutData }),

  // Admin Users
  getAdminUsers: () =>
    request('get_admin_users'),

  saveAdminUser: (userData) =>
    request('save_admin_user', { method: 'POST', body: userData }),

  deleteAdminUser: (id) =>
    request('delete_admin_user', { method: 'POST', body: { id } }),

  // Image Upload
  uploadImage: async (file, type = 'product') => {
    const formData = new FormData();
    formData.append('image', file);
    formData.append('type', type);
    return request('upload_image', {
      method: 'POST',
      body: formData,
      isFormData: true,
    });
  },

  // File Manager
  getFileManagerImages: () => request('get_file_manager_images'),

  deleteFileManager: (filenames) =>
    request('delete_file_manager', {
      method: 'POST',
      body: { filenames: Array.isArray(filenames) ? filenames : [filenames] },
    }),

  uploadFileManager: async (files) => {
    const formData = new FormData();
    Array.from(files).forEach((f) => formData.append('images[]', f));
    return request('upload_file_manager', {
      method: 'POST',
      body: formData,
      isFormData: true,
    });
  },

  convertAllWebp: () =>
    request('convert_all_webp', { method: 'POST' }),
};

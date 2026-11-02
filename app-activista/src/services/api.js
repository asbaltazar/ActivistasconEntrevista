import axios from 'axios';

export function getDeviceUuid() {
  let id = localStorage.getItem('rct_device_uuid');
  if (!id) {
    id = 'dev-' + Math.random().toString(36).slice(2) + '-' + Date.now().toString(36);
    localStorage.setItem('rct_device_uuid', id);
  }
  return id;
}

const api = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '/api/v1',
  timeout: 25000,
});

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('rct_activista_token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  config.headers['X-Device-UUID'] = getDeviceUuid();
  return config;
});

export default api;


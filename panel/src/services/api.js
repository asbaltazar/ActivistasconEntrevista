import axios from 'axios';

const api = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '/api/v1',
  timeout: 25000,
});

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('rct_panel_token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response && error.response.status === 401) {
      localStorage.removeItem('rct_panel_token');
      localStorage.removeItem('rct_panel_user');
      if (!window.location.pathname.endsWith('/login')) {
        window.location.href = import.meta.env.BASE_URL + 'login';
      }
    }
    return Promise.reject(error);
  }
);

export default api;

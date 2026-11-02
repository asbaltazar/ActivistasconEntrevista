import { defineStore } from 'pinia';
import api from '@/services/api';

export const useAuthStore = defineStore('panelAuth', {
  state: () => ({
    token: localStorage.getItem('rct_panel_token') || null,
    user: JSON.parse(localStorage.getItem('rct_panel_user') || 'null'),
    catalogs: null,
  }),
  getters: {
    isAuthenticated: (state) => !!state.token && !!state.user,
    isSuperAdmin: (state) => state.user?.role === 'superadmin',
    canWrite: (state) => state.user?.role === 'superadmin',
  },
  actions: {
    async login(username, password) {
      const { data: res } = await api.post('/auth/login', { username, password });
      const payload = res.data;
      if (!['superadmin', 'consulta'].includes(payload.user.role)) {
        throw new Error('Acceso restringido: esta cuenta no tiene permisos de Panel de Control.');
      }
      this.token = payload.token;
      this.user = payload.user;
      localStorage.setItem('rct_panel_token', payload.token);
      localStorage.setItem('rct_panel_user', JSON.stringify(payload.user));
      return payload;
    },
    async logout() {
      try {
        await api.post('/auth/logout');
      } catch {
        // Ignorar error de red al cerrar sesión
      }
      this.token = null;
      this.user = null;
      localStorage.removeItem('rct_panel_token');
      localStorage.removeItem('rct_panel_user');
    },
    async fetchCatalogs() {
      if (this.catalogs) return this.catalogs;
      const { data: res } = await api.get('/catalogs?scope=all');
      this.catalogs = res.data;
      return this.catalogs;
    },
  },
});


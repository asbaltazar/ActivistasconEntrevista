import { describe, it, expect, beforeEach } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import { useAuthStore } from '../src/stores/auth';

describe('Panel de Control — Store de Autenticación y Permisos', () => {
  beforeEach(() => {
    localStorage.clear();
    setActivePinia(createPinia());
  });

  it('inicia sin sesión activa y distingue permisos entre superadmin y consulta', () => {
    const store = useAuthStore();
    expect(store.isAuthenticated).toBe(false);

    store.token = 'tok-123';
    store.user = { id: 1, username: 'superadmin', role: 'superadmin' };
    expect(store.isAuthenticated).toBe(true);
    expect(store.isSuperAdmin).toBe(true);
    expect(store.canWrite).toBe(true);

    store.user = { id: 2, username: 'consulta', role: 'consulta' };
    expect(store.isSuperAdmin).toBe(false);
    expect(store.canWrite).toBe(false);
  });
});


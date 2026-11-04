import { defineStore } from 'pinia';
import api, { getDeviceUuid } from '@/services/api';
import { db, requestPersistentStorage } from '@/services/db';
import { encryptLocalPayload, decryptLocalPayload, hashPin } from '@/services/crypto';

export const useActivistStore = defineStore('activistStore', {
  state: () => ({
    token: localStorage.getItem('rct_activista_token') || null,
    user: JSON.parse(localStorage.getItem('rct_activista_user') || 'null'),
    pinHash: localStorage.getItem('rct_activista_pin_hash') || null,
    sessionPin:
      (typeof sessionStorage !== 'undefined' && sessionStorage.getItem('rct_session_pin')) ||
      localStorage.getItem('rct_last_pin') ||
      '0000',
    unlocked: !localStorage.getItem('rct_activista_pin_hash'),
    isOnline: typeof navigator !== 'undefined' ? navigator.onLine : true,
    syncInProgress: false,
    sessionExpired: false,
    lastSyncError: null,
    progress: {
      meta: 150,
      meta_conector: 50,
      meta_experto: 50,
      meta_vendedor: 50,
      registrados: 0,
      validados: 0,
      pendientes_validacion: 0,
      observados: 0,
      rechazados: 0,
      porcentaje: 0,
      telefono_verificado: true,
      por_clasificacion: {
        Conector: { registrados: 0, meta: 50, porcentaje: 0 },
        Experto: { registrados: 0, meta: 50, porcentaje: 0 },
        Vendedor: { registrados: 0, meta: 50, porcentaje: 0 },
      },
    },
    queueCounts: {
      borrador: 0,
      pendiente: 0,
      enviando: 0,
      sincronizado: 0,
      error: 0,
    },
    catalogs: {
      clasificaciones: ['Conector', 'Experto', 'Vendedor'],
      entrevista_preguntas: {
        pregunta_1: 'Pregunta1',
        pregunta_2: 'Pregunta2',
        pregunta_3: 'Pregunta3',
      },
      aviso_privacidad: {
        version: '1.0',
        texto: 'Red Ciudadana de Talentos trata sus datos personales conforme a la LFPDPPP.',
      },
    },
  }),
  getters: {
    isAuthenticated: (state) => !!state.token && !!state.user,
    mustChangePassword: (state) => !!state.user?.must_change_password,
    hasPendingOffline: (state) =>
      state.queueCounts.borrador + state.queueCounts.pendiente + state.queueCounts.enviando + state.queueCounts.error > 0,
  },
  actions: {
    initNetworkListeners() {
      requestPersistentStorage();
      if (typeof window !== 'undefined') {
        window.addEventListener('online', () => {
          this.isOnline = true;
          this.syncPendingQueue();
        });
        window.addEventListener('offline', () => {
          this.isOnline = false;
        });
      }
      this.refreshQueueCounts();
      this.loadCatalogsFromIndexedDb().then(() => {
        if (this.isOnline && this.token) {
          this.downloadCatalogsForOffline();
        }
      });
    },

    async login(username, password) {
      const { data: res } = await api.post('/auth/login', {
        username,
        password,
        device_uuid: getDeviceUuid(),
      });
      const payload = res.data;
      this.token = payload.token;
      this.user = payload.user;
      this.sessionExpired = false;
      this.lastSyncError = null;
      localStorage.setItem('rct_activista_token', payload.token);
      localStorage.setItem('rct_activista_user', JSON.stringify(payload.user));
      await this.downloadCatalogsForOffline();
      await this.syncPendingQueue();
      await this.fetchProgress();
      return payload;
    },

    async renewSession(username, password) {
      return await this.login(username, password);
    },

    async setOfflinePin(pin) {
      const h = await hashPin(pin);
      this.pinHash = h;
      this.sessionPin = pin;
      this.unlocked = true;
      localStorage.setItem('rct_activista_pin_hash', h);
      localStorage.setItem('rct_last_pin', pin);
      if (typeof sessionStorage !== 'undefined') {
        sessionStorage.setItem('rct_session_pin', pin);
      }
    },

    async unlockWithPin(pin) {
      const h = await hashPin(pin);
      if (this.pinHash && h !== this.pinHash) {
        throw new Error('PIN incorrecto.');
      }
      this.sessionPin = pin;
      this.unlocked = true;
      localStorage.setItem('rct_last_pin', pin);
      if (typeof sessionStorage !== 'undefined') {
        sessionStorage.setItem('rct_session_pin', pin);
      }
      return true;
    },

    lockApp() {
      if (this.pinHash) {
        this.unlocked = false;
      }
    },

    async changePassword(newPassword, currentPassword = '') {
      await api.post('/auth/password', {
        new_password: newPassword,
        current_password: currentPassword,
      });
      if (this.user) {
        this.user.must_change_password = false;
        localStorage.setItem('rct_activista_user', JSON.stringify(this.user));
      }
    },

    async downloadCatalogsForOffline() {
      try {
        const { data: res } = await api.get('/catalogs');
        if (res.data) {
          this.catalogs = {
            ...this.catalogs,
            ...res.data,
          };
          await db.catalogs.put({ key: 'config', data: this.catalogs });
        }
      } catch {
        await this.loadCatalogsFromIndexedDb();
      }
    },

    async loadCatalogsFromIndexedDb() {
      const cached = await db.catalogs.get('config');
      if (cached?.data) {
        this.catalogs = {
          ...this.catalogs,
          ...cached.data,
        };
      }
    },

    async fetchProgress() {
      await this.refreshQueueCounts();
      if (!this.isOnline || !this.token) return;
      try {
        const { data: res } = await api.get('/me/progress');
        if (res.data) {
          this.progress = res.data;
          this.sessionExpired = false;
        }
      } catch (err) {
        if (err.response?.status === 401) {
          this.sessionExpired = true;
          this.lastSyncError =
            'Tu sesión expiró (por reinicio del servidor/base de datos). Ingresa tu contraseña para reactivar la sesión y sincronizar.';
        }
      }
    },

    async refreshQueueCounts() {
      const all = await db.talentsQueue.toArray();
      const counts = { borrador: 0, pendiente: 0, enviando: 0, sincronizado: 0, error: 0 };
      let latestErr = null;
      for (const item of all) {
        if (counts[item.status] !== undefined) {
          counts[item.status]++;
        }
        if (item.status === 'error' && item.lastError) {
          latestErr = item.lastError;
        }
      }
      this.queueCounts = counts;
      if (latestErr) {
        this.lastSyncError = latestErr;
        if (latestErr.toLowerCase().includes('sesión') || latestErr.toLowerCase().includes('expirad')) {
          this.sessionExpired = true;
        }
      } else if (counts.error === 0) {
        this.lastSyncError = null;
      }
    },

    /**
     * Verifica si clave_elector, CURP o teléfono ya existe en la cola local de IndexedDB (RF-A7).
     */
    async checkLocalDuplicates({ uuid, clave_elector, curp, telefono }) {
      const all = await db.talentsQueue.toArray();
      const warnings = [];
      let hardDuplicate = null;

      const normClave = String(clave_elector || '').trim().toUpperCase();
      const normCurp = String(curp || '').trim().toUpperCase();
      const normTel = String(telefono || '').replace(/\D/g, '');

      for (const item of all) {
        if (item.uuid === uuid) continue;
        if (normClave && item.clave_elector === normClave) {
          hardDuplicate = `La Clave de Elector ${normClave} ya fue capturada en este dispositivo.`;
        }
        if (normCurp && item.curp === normCurp) {
          warnings.push(`La CURP ${normCurp} ya existe en otro registro local.`);
        }
        if (normTel && item.telefono === normTel) {
          warnings.push(`El teléfono ${normTel} ya fue capturado en otro registro local.`);
        }
      }
      return { hardDuplicate, warnings };
    },

    /**
     * Guarda un Talento en IndexedDB cifrado con AES-GCM y lo encola para sincronización (RF-A5).
     */
    async enqueueTalent(talentPayload, status = 'pendiente') {
      const encrypted = await encryptLocalPayload(talentPayload, this.sessionPin || '0000');
      const record = {
        uuid: talentPayload.uuid,
        status,
        clasificacion: talentPayload.clasificacion || 'Conector',
        clave_elector: String(talentPayload.clave_elector || '').trim().toUpperCase(),
        curp: String(talentPayload.curp || '').trim().toUpperCase(),
        telefono: String(talentPayload.telefono || '').replace(/\D/g, ''),
        nombre_completo: `${talentPayload.nombre} ${talentPayload.ap_paterno} ${talentPayload.ap_materno || ''}`.trim(),
        seccion: Number(talentPayload.seccion || 0),
        encryptedPayload: encrypted,
        retries: 0,
        lastError: null,
        updatedAt: new Date().toISOString(),
      };

      await db.talentsQueue.put(record);
      await this.refreshQueueCounts();

      if (status === 'pendiente' && this.isOnline) {
        this.syncPendingQueue();
      }
      return record;
    },

    /**
     * Procesa la cola de sincronización con backoff exponencial; nunca borra locales hasta confirmación 200/201 (RF-A5).
     */
    async syncPendingQueue() {
      if (this.syncInProgress || !this.isOnline || !this.token) {
        return;
      }

      this.syncInProgress = true;
      try {
        const candidates = await db.talentsQueue
          .where('status')
          .anyOf(['pendiente', 'error'])
          .toArray();

        for (const item of candidates) {
          await db.talentsQueue.update(item.uuid, { status: 'enviando', lastError: null });
          await this.refreshQueueCounts();

          try {
            const plainPayload = await decryptLocalPayload(item.encryptedPayload, this.sessionPin || '0000');
            const response = await api.post('/sync/talents', {
              ...plainPayload,
              device_uuid: getDeviceUuid(),
            });

            if (response.status === 200 || response.status === 201) {
              await db.talentsQueue.update(item.uuid, {
                status: 'sincronizado',
                lastError: null,
                updatedAt: new Date().toISOString(),
              });
            }
          } catch (err) {
            const errMsg =
              err.response?.data?.errors?.[0] ||
              err.message ||
              'Error de conexión al sincronizar';
            const retries = (item.retries || 0) + 1;

            if (err.response?.status === 401) {
              this.sessionExpired = true;
            }
            this.lastSyncError = errMsg;

            await db.talentsQueue.update(item.uuid, {
              status: 'error',
              retries,
              lastError: errMsg,
              updatedAt: new Date().toISOString(),
            });

            if (err.response?.status === 401) {
              break;
            }

            if (!err.response) {
              const delayMs = Math.min(8000, 500 * Math.pow(2, retries));
              await new Promise((r) => setTimeout(r, delayMs));
            }
          }
        }
      } finally {
        this.syncInProgress = false;
        await this.refreshQueueCounts();
        await this.fetchProgress();
      }
    },

    async logout(force = false) {
      await this.refreshQueueCounts();
      if (this.hasPendingOffline && !force) {
        throw new Error('PENDING_RECORDS_WARNING');
      }
      try {
        if (this.isOnline) await api.post('/auth/logout');
      } catch {
        // Ignorar
      }
      this.token = null;
      this.user = null;
      localStorage.removeItem('rct_activista_token');
      localStorage.removeItem('rct_activista_user');
    },
  },
});

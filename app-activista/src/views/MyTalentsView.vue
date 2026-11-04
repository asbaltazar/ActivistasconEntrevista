<template>
  <div class="p-4 space-y-4">
    <!-- Buscador y pestañas Lista / Mapa de Mis Talentos (RF-A6) -->
    <div class="bg-white rounded-2xl p-3.5 border border-slate-200 shadow-xs space-y-3">
      <div class="flex items-center justify-between gap-2">
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Buscar por nombre, clasificación, sección o teléfono..."
          class="flex-1 px-3.5 py-2.5 rounded-xl border border-slate-300 text-xs"
        />
        <button
          type="button"
          class="px-3 py-2.5 rounded-xl text-xs font-bold cursor-pointer"
          :class="showMap ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700'"
          @click="showMap = !showMap"
        >
          <i class="pi" :class="showMap ? 'pi-list' : 'pi-map'"></i>
          {{ showMap ? 'Ver Lista' : 'Mapa' }}
        </button>
      </div>
    </div>

    <!-- Vista de Mapa de Mis Talentos -->
    <div v-if="showMap" class="bg-slate-900 rounded-2xl p-4 text-white space-y-2 shadow">
      <div class="text-xs font-bold text-emerald-400">Mapa de Mis Talentos Capturados ({{ filteredList.length }})</div>
      <svg viewBox="-99.2 19.35 0.15 0.15" class="w-full h-64 bg-slate-950 rounded-xl border border-slate-800">
        <circle
          v-for="item in filteredList"
          :key="item.uuid"
          :cx="item.lng || -99.133"
          :cy="38.86 - (item.lat || 19.432)"
          r="0.004"
          fill="#10b981"
          stroke="#ffffff"
          stroke-width="0.001"
        />
      </svg>
    </div>

    <!-- Banner de reactivación de sesión si expiró el token -->
    <div
      v-if="store.sessionExpired"
      class="bg-rose-50 rounded-2xl p-4 border border-rose-200 shadow-xs space-y-2.5 text-xs text-rose-900"
    >
      <div class="font-bold text-rose-800">
        Tu sesión expiró (por reinicio de la base de datos). Confirma tus credenciales para renovar la sesión y sincronizar tus Talentos guardados:
      </div>
      <form class="space-y-2" @submit.prevent="handleRenewAndSync">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
          <input
            v-model="renewUsername"
            type="text"
            required
            placeholder="Usuario (ej. activista1)"
            class="px-3 py-2 rounded-lg border border-rose-300 bg-white text-slate-900 text-xs font-medium"
          />
          <input
            v-model="renewPassword"
            type="password"
            required
            placeholder="Contraseña"
            class="px-3 py-2 rounded-lg border border-rose-300 bg-white text-slate-900 text-xs"
          />
        </div>
        <div v-if="renewError" class="text-[11px] font-bold text-rose-700">{{ renewError }}</div>
        <button
          type="submit"
          :disabled="renewLoading"
          class="w-full py-2.5 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-xs cursor-pointer"
        >
          {{ renewLoading ? 'Renovando sesión y sincronizando...' : 'Reactivar Sesión y Sincronizar Ahora' }}
        </button>
      </form>
    </div>

    <!-- Cola local en IndexedDB (Pendientes / Sincronizados / Error) -->
    <div class="space-y-2.5">
      <div class="flex items-center justify-between px-1">
        <h3 class="text-xs font-extrabold uppercase text-slate-500">
          Registros en este Dispositivo y Servidor ({{ filteredList.length }})
        </h3>
        <button
          v-if="store.queueCounts.pendiente + store.queueCounts.error > 0"
          type="button"
          :disabled="!store.isOnline || store.syncInProgress"
          class="px-3 py-1.5 rounded-lg bg-slate-900 text-white text-[11px] font-bold cursor-pointer disabled:opacity-40"
          @click="retrySyncAll"
        >
          <i class="pi pi-sync mr-1" :class="{ 'pi-spin': store.syncInProgress }"></i>
          Sincronizar ahora
        </button>
      </div>

      <div
        v-for="item in filteredList"
        :key="item.uuid"
        class="bg-white rounded-2xl p-4 border border-slate-200 shadow-xs flex items-center justify-between gap-3"
      >
        <div class="min-w-0">
          <div class="flex items-center gap-2">
            <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-700 text-[10px] font-extrabold uppercase">
              {{ item.clasificacion || 'Conector' }}
            </span>
            <div class="font-extrabold text-sm text-slate-900 truncate">{{ item.nombre_completo }}</div>
          </div>
          <div class="text-xs text-slate-500 font-mono mt-0.5">
            Sección {{ item.seccion }} · Tel: {{ item.telefono }}
          </div>
          <div v-if="item.lastError" class="text-[11px] text-rose-600 font-bold mt-1">
            {{ item.lastError }}
          </div>
        </div>

        <div class="flex flex-col items-end gap-1.5 shrink-0">
          <span
            class="px-2.5 py-1 rounded-full text-[11px] font-extrabold uppercase"
            :class="{
              'bg-amber-100 text-amber-800': item.status === 'pendiente' || item.status === 'enviando',
              'bg-emerald-100 text-emerald-800': item.status === 'sincronizado' || item.status === 'validado',
              'bg-rose-100 text-rose-800': item.status === 'error',
              'bg-slate-100 text-slate-700': item.status === 'borrador',
            }"
          >
            {{ item.status }}
          </span>
        </div>
      </div>

      <div v-if="filteredList.length === 0" class="bg-white rounded-2xl p-8 text-center text-xs text-slate-400 border border-slate-200">
        Aún no tienes Talentos registrados. Usa el botón "Nuevo Talento" para comenzar.
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import api from '@/services/api';
import { db } from '@/services/db';
import { useActivistStore } from '@/stores/activist';

const store = useActivistStore();
const searchQuery = ref('');
const showMap = ref(false);
const localItems = ref([]);
const serverItems = ref([]);
const renewUsername = ref(store.user?.username || 'activista1');
const renewPassword = ref('');
const renewLoading = ref(false);
const renewError = ref('');

async function retrySyncAll() {
  await store.syncPendingQueue();
  await loadTalents();
}

async function handleRenewAndSync() {
  renewError.value = '';
  renewLoading.value = true;
  try {
    await store.renewSession(renewUsername.value, renewPassword.value);
    renewPassword.value = '';
    await loadTalents();
  } catch (err) {
    renewError.value = err.response?.data?.errors?.[0] || 'Credenciales inválidas. Verifica usuario y contraseña.';
  } finally {
    renewLoading.value = false;
  }
}

const filteredList = computed(() => {
  const byUuid = new Map();
  for (const l of localItems.value) {
    byUuid.set(l.uuid, l);
  }
  for (const s of serverItems.value) {
    if (!byUuid.has(s.uuid)) {
      byUuid.set(s.uuid, {
        uuid: s.uuid,
        clasificacion: s.clasificacion || 'Conector',
        nombre_completo: `${s.nombre} ${s.ap_paterno} ${s.ap_materno || ''}`.trim(),
        seccion: s.seccion,
        telefono: s.telefono,
        lat: s.lat,
        lng: s.lng,
        status: s.validacion === 'validado' ? 'validado' : 'sincronizado',
        lastError: s.observaciones,
      });
    }
  }
  const combined = Array.from(byUuid.values());
  const q = searchQuery.value.trim().toLowerCase();
  if (!q) return combined;
  return combined.filter(
    (i) =>
      i.nombre_completo.toLowerCase().includes(q) ||
      String(i.clasificacion || '').toLowerCase().includes(q) ||
      String(i.seccion).includes(q) ||
      String(i.telefono).includes(q)
  );
});

async function loadTalents() {
  await store.refreshQueueCounts();
  localItems.value = await db.talentsQueue.reverse().toArray();
  if (store.isOnline) {
    try {
      const { data: res } = await api.get('/me/talents');
      serverItems.value = res.data || [];
    } catch {
      // Usa registros locales en IndexedDB
    }
  }
}

onMounted(loadTalents);
</script>

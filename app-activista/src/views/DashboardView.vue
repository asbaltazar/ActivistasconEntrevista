<template>
  <div class="p-4 space-y-4">
    <!-- Tarjeta de Avance de Meta Personal (RF-A2) -->
    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-xs space-y-3">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-xs font-bold uppercase text-slate-500">Mi Meta de Captura</p>
          <h2 class="text-2xl font-extrabold text-slate-900">
            {{ store.progress.registrados }} / {{ store.progress.meta }} Talentos
          </h2>
        </div>
        <span class="px-3 py-1 rounded-full bg-emerald-100 text-emerald-800 text-sm font-extrabold">
          {{ store.progress.porcentaje }}%
        </span>
      </div>

      <div class="w-full bg-slate-200 h-3.5 rounded-full overflow-hidden">
        <div
          class="bg-emerald-600 h-full transition-all duration-300"
          :style="{ width: `${store.progress.porcentaje}%` }"
        ></div>
      </div>

      <div class="grid grid-cols-3 gap-2 pt-2 text-center">
        <div class="p-2.5 rounded-xl bg-emerald-50 border border-emerald-200">
          <div class="text-lg font-extrabold text-emerald-800">{{ store.progress.validados }}</div>
          <div class="text-[11px] font-bold text-emerald-700">Validados</div>
        </div>
        <div class="p-2.5 rounded-xl bg-amber-50 border border-amber-200">
          <div class="text-lg font-extrabold text-amber-800">
            {{ store.queueCounts.pendiente + store.queueCounts.enviando + store.queueCounts.error }}
          </div>
          <div class="text-[11px] font-bold text-amber-700">Por Sincronizar</div>
        </div>
        <div class="p-2.5 rounded-xl bg-rose-50 border border-rose-200">
          <div class="text-lg font-extrabold text-rose-800">
            {{ store.progress.observados + store.progress.rechazados }}
          </div>
          <div class="text-[11px] font-bold text-rose-700">Observados</div>
        </div>
      </div>
    </div>

    <!-- Botón Principal de Alta de Talento (< 90 s) -->
    <router-link
      to="/captura"
      class="w-full py-4 px-5 rounded-2xl bg-emerald-700 hover:bg-emerald-800 text-white font-extrabold text-base shadow-lg flex items-center justify-between"
    >
      <div class="flex items-center gap-3">
        <i class="pi pi-camera text-2xl"></i>
        <div>
          <div>Registrar Nuevo Talento</div>
          <div class="text-xs font-normal text-emerald-200">Asistente INE + Selfie + Ubicación (Funciona sin red)</div>
        </div>
      </div>
      <i class="pi pi-chevron-right"></i>
    </router-link>

    <!-- Estado de la Cola Offline en IndexedDB (RF-A5) -->
    <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-xs space-y-3">
      <div class="flex items-center justify-between">
        <div>
          <h3 class="text-sm font-bold text-slate-800">Cola Local (IndexedDB + AES-GCM)</h3>
          <p class="text-xs text-slate-500">0 % pérdida de registros en modo avión</p>
        </div>
        <button
          type="button"
          :disabled="!store.isOnline || store.syncInProgress"
          class="px-3.5 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 disabled:opacity-40 text-white text-xs font-bold cursor-pointer"
          @click="store.syncPendingQueue()"
        >
          <i class="pi pi-sync mr-1" :class="{ 'pi-spin': store.syncInProgress }"></i>
          {{ store.syncInProgress ? 'Sincronizando...' : 'Sincronizar ahora' }}
        </button>
      </div>

      <div class="grid grid-cols-4 gap-2 text-center text-xs">
        <div class="p-2 rounded-lg bg-slate-50">
          <div class="font-extrabold text-slate-700">{{ store.queueCounts.borrador }}</div>
          <div class="text-[10px] text-slate-500">Borrador</div>
        </div>
        <div class="p-2 rounded-lg bg-amber-50">
          <div class="font-extrabold text-amber-800">{{ store.queueCounts.pendiente }}</div>
          <div class="text-[10px] text-amber-700">Pendientes</div>
        </div>
        <div class="p-2 rounded-lg bg-rose-50">
          <div class="font-extrabold text-rose-800">{{ store.queueCounts.error }}</div>
          <div class="text-[10px] text-rose-700">Reintento</div>
        </div>
        <div class="p-2 rounded-lg bg-emerald-50">
          <div class="font-extrabold text-emerald-800">{{ store.queueCounts.sincronizado }}</div>
          <div class="text-[10px] text-emerald-700">Sincronizados</div>
        </div>
      </div>
    </div>

    <!-- Cerrar sesión con advertencia si hay pendientes (RF-A5) -->
    <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-xs space-y-2">
      <div v-if="logoutWarning" class="p-3 rounded-xl bg-amber-50 border border-amber-300 text-amber-900 text-xs space-y-2">
        <p class="font-bold">¡Atención! Tienes registros pendientes de sincronizar en este dispositivo.</p>
        <p>
          Tus registros locales están protegidos en IndexedDB y no se borrarán, pero se recomienda sincronizar antes de salir.
        </p>
        <button
          type="button"
          class="px-3 py-1.5 rounded-lg bg-rose-600 text-white font-bold text-xs cursor-pointer"
          @click="confirmForceLogout"
        >
          Cerrar sesión de todas formas (Conservar en IndexedDB)
        </button>
      </div>

      <button
        type="button"
        class="w-full py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold cursor-pointer"
        @click="attemptLogout"
      >
        Cerrar sesión de Activista
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useActivistStore } from '@/stores/activist';

const store = useActivistStore();
const router = useRouter();
const logoutWarning = ref(false);

onMounted(() => {
  store.fetchProgress();
});

async function attemptLogout() {
  try {
    await store.logout(false);
    router.push('/login');
  } catch (err) {
    if (err.message === 'PENDING_RECORDS_WARNING') {
      logoutWarning.value = true;
    }
  }
}

async function confirmForceLogout() {
  await store.logout(true);
  router.push('/login');
}
</script>

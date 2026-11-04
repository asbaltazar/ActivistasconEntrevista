<template>
  <div class="p-4 space-y-4">
    <!-- Tarjeta de Avance de Meta Personal y Desglose por Clasificación (50 Conector, 50 Experto, 50 Vendedor) -->
    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-xs space-y-4">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-xs font-bold uppercase text-slate-500">Mi Meta Total de Captura</p>
          <h2 class="text-2xl font-extrabold text-slate-900">
            {{ store.progress.registrados }} / {{ store.progress.meta || 150 }} Talentos
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

      <!-- Seguimiento por Clasificación: 50 Conector, 50 Experto, 50 Vendedor -->
      <div class="pt-2 border-t border-slate-100 space-y-2.5">
        <div class="flex items-center justify-between text-xs font-bold text-slate-600 uppercase">
          <span>Avance por Clasificación</span>
          <span>Meta: 50 por tipo</span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
          <!-- Conector -->
          <div class="p-3 rounded-xl bg-blue-50/70 border border-blue-200 space-y-1.5">
            <div class="flex items-center justify-between text-xs">
              <span class="font-extrabold text-blue-900">Conector</span>
              <span class="font-mono font-bold text-blue-800">
                {{ clasifProgress('Conector').registrados }} / {{ clasifProgress('Conector').meta }}
              </span>
            </div>
            <div class="w-full bg-blue-200/70 h-2 rounded-full overflow-hidden">
              <div
                class="bg-blue-600 h-full transition-all duration-300"
                :style="{ width: `${clasifProgress('Conector').porcentaje}%` }"
              ></div>
            </div>
            <div class="text-[11px] text-blue-700 font-semibold text-right">
              {{ clasifProgress('Conector').porcentaje }}%
            </div>
          </div>

          <!-- Experto -->
          <div class="p-3 rounded-xl bg-purple-50/70 border border-purple-200 space-y-1.5">
            <div class="flex items-center justify-between text-xs">
              <span class="font-extrabold text-purple-900">Experto</span>
              <span class="font-mono font-bold text-purple-800">
                {{ clasifProgress('Experto').registrados }} / {{ clasifProgress('Experto').meta }}
              </span>
            </div>
            <div class="w-full bg-purple-200/70 h-2 rounded-full overflow-hidden">
              <div
                class="bg-purple-600 h-full transition-all duration-300"
                :style="{ width: `${clasifProgress('Experto').porcentaje}%` }"
              ></div>
            </div>
            <div class="text-[11px] text-purple-700 font-semibold text-right">
              {{ clasifProgress('Experto').porcentaje }}%
            </div>
          </div>

          <!-- Vendedor -->
          <div class="p-3 rounded-xl bg-amber-50/70 border border-amber-200 space-y-1.5">
            <div class="flex items-center justify-between text-xs">
              <span class="font-extrabold text-amber-900">Vendedor</span>
              <span class="font-mono font-bold text-amber-800">
                {{ clasifProgress('Vendedor').registrados }} / {{ clasifProgress('Vendedor').meta }}
              </span>
            </div>
            <div class="w-full bg-amber-200/70 h-2 rounded-full overflow-hidden">
              <div
                class="bg-amber-600 h-full transition-all duration-300"
                :style="{ width: `${clasifProgress('Vendedor').porcentaje}%` }"
              ></div>
            </div>
            <div class="text-[11px] text-amber-700 font-semibold text-right">
              {{ clasifProgress('Vendedor').porcentaje }}%
            </div>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-3 gap-2 pt-1 text-center">
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

    <!-- Botón Principal de Alta de Talento -->
    <router-link
      to="/captura"
      class="w-full py-4 px-5 rounded-2xl bg-emerald-700 hover:bg-emerald-800 text-white font-extrabold text-base shadow-lg flex items-center justify-between"
    >
      <div class="flex items-center gap-3">
        <i class="pi pi-camera text-2xl"></i>
        <div>
          <div>Registrar Nuevo Talento</div>
          <div class="text-xs font-normal text-emerald-200">Clasificación + Entrevista + Selfie + INE Frente</div>
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

      <!-- Mensaje de error de sincronización y reactivación rápida de sesión -->
      <div
        v-if="store.lastSyncError || store.sessionExpired"
        class="p-3.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-900 text-xs space-y-2.5"
      >
        <div class="font-bold flex items-center gap-1.5 text-rose-800">
          <i class="pi pi-exclamation-triangle"></i>
          <span>Detalle de sincronización: {{ store.lastSyncError || 'Sesión inválida o expirada.' }}</span>
        </div>

        <form v-if="store.sessionExpired" class="space-y-2 pt-1 border-t border-rose-200/80" @submit.prevent="handleRenewAndSync">
          <p class="text-[11px] text-rose-700">
            Tu registro local está seguro en este dispositivo. Confirma tus credenciales de Activista para renovar el token y sincronizar inmediatamente:
          </p>
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
const renewUsername = ref(store.user?.username || 'activista1');
const renewPassword = ref('');
const renewLoading = ref(false);
const renewError = ref('');

async function handleRenewAndSync() {
  renewError.value = '';
  renewLoading.value = true;
  try {
    await store.renewSession(renewUsername.value, renewPassword.value);
    renewPassword.value = '';
  } catch (err) {
    renewError.value = err.response?.data?.errors?.[0] || 'Credenciales inválidas. Verifica usuario y contraseña.';
  } finally {
    renewLoading.value = false;
  }
}

function clasifProgress(tipo) {
  return (
    store.progress.por_clasificacion?.[tipo] || {
      registrados: 0,
      meta: 50,
      porcentaje: 0,
    }
  );
}

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

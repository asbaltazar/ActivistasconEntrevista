<template>
  <div class="min-h-screen bg-slate-100 text-slate-900 flex flex-col max-w-lg mx-auto shadow-xl">
    <!-- Barra superior de estado de red y bloqueo PIN -->
    <header v-if="store.isAuthenticated" class="bg-emerald-800 text-white px-4 py-3 flex items-center justify-between shadow-md">
      <div class="flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg bg-white text-emerald-800 font-extrabold flex items-center justify-center text-sm">
          RC
        </div>
        <div>
          <h1 class="text-sm font-bold leading-tight">Red Ciudadana</h1>
          <p class="text-[11px] text-emerald-200">
            {{ store.user?.activist?.nombre || store.user?.username }}
          </p>
        </div>
      </div>

      <div class="flex items-center gap-2">
        <span
          class="px-2.5 py-1 rounded-full text-[11px] font-bold flex items-center gap-1.5"
          :class="store.isOnline ? 'bg-emerald-600 text-white' : 'bg-amber-500 text-slate-950'"
        >
          <span class="w-2 h-2 rounded-full" :class="store.isOnline ? 'bg-emerald-200' : 'bg-slate-950'"></span>
          {{ store.isOnline ? 'EN LÍNEA' : 'MODO OFFLINE' }}
        </span>
        <button
          v-if="store.pinHash"
          type="button"
          title="Bloquear con PIN"
          class="p-1.5 rounded-lg bg-emerald-700 hover:bg-emerald-600 text-white cursor-pointer"
          @click="store.lockApp()"
        >
          <i class="pi pi-lock text-xs"></i>
        </button>
      </div>
    </header>

    <!-- Pantalla de desbloqueo con PIN offline (RF-A1) -->
    <div
      v-if="store.isAuthenticated && !store.unlocked"
      class="flex-1 flex flex-col items-center justify-center p-6 bg-slate-900 text-white space-y-4"
    >
      <div class="w-14 h-14 rounded-2xl bg-emerald-600 flex items-center justify-center text-2xl">
        <i class="pi pi-lock"></i>
      </div>
      <h2 class="text-lg font-bold">Desbloqueo Offline con PIN</h2>
      <p class="text-xs text-slate-400 text-center">
        Ingresa tu PIN local de 4 a 6 dígitos para descifrar tus registros locales en IndexedDB.
      </p>
      <div v-if="pinError" class="p-2.5 rounded bg-rose-500/20 text-rose-300 text-xs">{{ pinError }}</div>
      <input
        v-model="pinInput"
        type="password"
        inputmode="numeric"
        maxlength="6"
        placeholder="••••"
        class="w-48 px-4 py-3 rounded-xl bg-slate-800 border border-slate-700 text-center text-2xl tracking-widest"
        @keyup.enter="handleUnlock"
      />
      <button
        type="button"
        class="w-48 py-3 rounded-xl bg-emerald-600 hover:bg-emerald-500 font-bold text-sm cursor-pointer"
        @click="handleUnlock"
      >
        Desbloquear App
      </button>
    </div>

    <!-- Vista activa -->
    <main v-else class="flex-1 overflow-y-auto pb-20">
      <router-view />
    </main>

    <!-- Navegación inferior para uso con una mano en exterior -->
    <nav
      v-if="store.isAuthenticated && store.unlocked && !store.mustChangePassword"
      class="fixed bottom-0 left-0 right-0 max-w-lg mx-auto bg-white border-t border-slate-200 grid grid-cols-3 text-xs font-bold shadow-lg z-40"
    >
      <router-link
        to="/"
        class="py-3 flex flex-col items-center gap-1"
        :class="$route.path === '/' ? 'text-emerald-700' : 'text-slate-500'"
      >
        <i class="pi pi-home text-base"></i>
        <span>Mi Tablero</span>
      </router-link>
      <router-link
        to="/captura"
        class="py-3 flex flex-col items-center gap-1 bg-emerald-700 text-white"
      >
        <i class="pi pi-plus-circle text-base"></i>
        <span>Nuevo Talento</span>
      </router-link>
      <router-link
        to="/mis-talentos"
        class="py-3 flex flex-col items-center gap-1"
        :class="$route.path === '/mis-talentos' ? 'text-emerald-700' : 'text-slate-500'"
      >
        <i class="pi pi-users text-base"></i>
        <span>Mis Talentos</span>
      </router-link>
    </nav>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useActivistStore } from '@/stores/activist';

const store = useActivistStore();
const pinInput = ref('');
const pinError = ref('');

onMounted(() => {
  store.initNetworkListeners();
});

async function handleUnlock() {
  pinError.value = '';
  try {
    await store.unlockWithPin(pinInput.value);
    pinInput.value = '';
  } catch (err) {
    pinError.value = err.message || 'PIN inválido';
  }
}
</script>


<template>
  <div class="min-h-screen flex bg-slate-100 text-slate-900">
    <!-- Barra lateral -->
    <aside class="w-64 bg-slate-900 text-slate-100 flex flex-col shrink-0 shadow-xl">
      <div class="p-5 border-b border-slate-800">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-emerald-500 flex items-center justify-center font-bold text-slate-950 text-lg">
            RC
          </div>
          <div>
            <h1 class="font-bold text-sm leading-tight text-white">Red Ciudadana</h1>
            <p class="text-xs text-slate-400">Panel de Control Nacional</p>
          </div>
        </div>
      </div>

      <nav class="flex-1 p-3 space-y-1">
        <router-link
          v-for="item in navItems"
          :key="item.to"
          :to="item.to"
          class="flex items-center gap-3 px-3.5 py-2.5 rounded-lg text-sm font-medium transition-colors"
          :class="$route.path === item.to ? 'bg-emerald-600 text-white shadow' : 'text-slate-300 hover:bg-slate-800 hover:text-white'"
        >
          <i :class="item.icon"></i>
          <span>{{ item.label }}</span>
        </router-link>
      </nav>

      <div class="p-4 border-t border-slate-800 bg-slate-950/60">
        <div class="flex items-center justify-between mb-3">
          <div class="truncate">
            <p class="text-xs font-semibold text-white truncate">{{ auth.user?.username }}</p>
            <span class="inline-block text-[11px] px-2 py-0.5 rounded bg-emerald-500/20 text-emerald-300 uppercase">
              {{ auth.user?.role }}
            </span>
          </div>
        </div>
        <button
          type="button"
          class="w-full py-2 px-3 rounded-lg bg-slate-800 hover:bg-rose-600 text-xs font-semibold text-slate-200 hover:text-white transition flex items-center justify-center gap-2 cursor-pointer"
          @click="handleLogout"
        >
          <i class="pi pi-sign-out"></i>
          <span>Cerrar sesión</span>
        </button>
      </div>
    </aside>

    <!-- Contenido principal -->
    <div class="flex-1 flex flex-col min-w-0">
      <header class="h-16 bg-white border-b border-slate-200 px-6 flex items-center justify-between">
        <div>
          <h2 class="text-lg font-bold text-slate-800">{{ currentTitle }}</h2>
        </div>
        <div class="flex items-center gap-4 text-xs text-slate-500">
          <span class="flex items-center gap-1.5">
            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
            Alcance Nacional (32 entidades)
          </span>
          <span class="font-medium text-slate-700">activistas.solytecmx.com</span>
        </div>
      </header>

      <main class="flex-1 p-6 overflow-y-auto">
        <router-view />
      </main>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();

const navItems = [
  { to: '/', label: 'Monitoreo de Metas', icon: 'pi pi-chart-bar' },
  { to: '/activistas', label: 'Gestión de Activistas', icon: 'pi pi-users' },
  { to: '/talentos', label: 'Padrón de Talentos', icon: 'pi pi-id-card' },
  { to: '/mapa', label: 'Mapa Nacional', icon: 'pi pi-map-marker' },
  { to: '/reportes', label: 'Reportes y Exportación', icon: 'pi pi-file-excel' },
  { to: '/sms', label: 'Avisos SMS (Twilio)', icon: 'pi pi-send' },
  { to: '/auditoria', label: 'Auditoría y Ajustes', icon: 'pi pi-shield' },
];

const currentTitle = computed(() => {
  const found = navItems.find((item) => item.to === route.path);
  return found ? found.label : 'Panel de Control';
});

onMounted(() => {
  auth.fetchCatalogs().catch(() => {});
});

async function handleLogout() {
  await auth.logout();
  router.push('/login');
}
</script>

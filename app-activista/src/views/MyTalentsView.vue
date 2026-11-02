<template>
  <div class="p-4 space-y-4">
    <!-- Buscador y pestañas Lista / Mapa de Mis Talentos (RF-A6) -->
    <div class="bg-white rounded-2xl p-3.5 border border-slate-200 shadow-xs space-y-3">
      <div class="flex items-center justify-between gap-2">
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Buscar por nombre, sección o teléfono..."
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

    <!-- Cola local en IndexedDB (Pendientes / Sincronizados / Error) -->
    <div class="space-y-2.5">
      <h3 class="text-xs font-extrabold uppercase text-slate-500 px-1">
        Registros en este Dispositivo y Servidor ({{ filteredList.length }})
      </h3>

      <div
        v-for="item in filteredList"
        :key="item.uuid"
        class="bg-white rounded-2xl p-4 border border-slate-200 shadow-xs flex items-center justify-between gap-3"
      >
        <div class="min-w-0">
          <div class="font-extrabold text-sm text-slate-900 truncate">{{ item.nombre_completo }}</div>
          <div class="text-xs text-slate-500 font-mono">
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

const filteredList = computed(() => {
  const byUuid = new Map();
  for (const l of localItems.value) {
    byUuid.set(l.uuid, l);
  }
  for (const s of serverItems.value) {
    if (!byUuid.has(s.uuid)) {
      byUuid.set(s.uuid, {
        uuid: s.uuid,
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
      String(i.seccion).includes(q) ||
      String(i.telefono).includes(q)
  );
});

async function loadTalents() {
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


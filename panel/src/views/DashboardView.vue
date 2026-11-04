<template>
  <div class="space-y-6">
    <!-- KPIs principales (RF-P3) -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-xs">
        <p class="text-xs font-semibold uppercase text-slate-500">Total Talentos Registrados</p>
        <div class="mt-2 flex items-baseline justify-between">
          <span class="text-3xl font-extrabold text-slate-900">{{ dash.kpis.total }}</span>
          <span class="text-xs font-semibold px-2 py-0.5 rounded bg-emerald-100 text-emerald-800">
            Meta: {{ dash.kpis.meta_global }}
          </span>
        </div>
        <div class="mt-3 w-full bg-slate-100 h-2 rounded-full overflow-hidden">
          <div class="bg-emerald-600 h-full transition-all" :style="{ width: `${dash.kpis.porcentaje_global}%` }"></div>
        </div>
        <p class="text-xs text-slate-500 mt-1.5">
          {{ dash.kpis.porcentaje_global }}% de avance ({{ dash.kpis.meta_por_activista || 150 }} por activista)
        </p>
      </div>

      <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-xs">
        <p class="text-xs font-semibold uppercase text-slate-500">Capturas Hoy / 7 Días</p>
        <div class="mt-2 flex items-baseline gap-3">
          <span class="text-3xl font-extrabold text-emerald-600">+{{ dash.kpis.hoy }}</span>
          <span class="text-sm text-slate-500">hoy ({{ dash.kpis.semana }} esta semana)</span>
        </div>
        <p class="text-xs text-slate-500 mt-4">Monitoreo en tiempo casi real (&le; 5 min)</p>
      </div>

      <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-xs">
        <p class="text-xs font-semibold uppercase text-slate-500">Validación de Padrón</p>
        <div class="mt-2 flex items-baseline justify-between">
          <span class="text-3xl font-extrabold text-blue-600">{{ dash.kpis.validados }}</span>
          <span class="text-xs text-amber-700 bg-amber-100 px-2 py-0.5 rounded font-medium">
            {{ dash.kpis.pendientes }} pendientes
          </span>
        </div>
        <p class="text-xs text-slate-500 mt-4">
          {{ dash.kpis.observados }} observados · {{ dash.kpis.rechazados }} rechazados
        </p>
      </div>

      <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-xs">
        <p class="text-xs font-semibold uppercase text-slate-500">Semáforo de Activistas ({{ dash.kpis.total_activistas }})</p>
        <div class="mt-3 flex items-center gap-3">
          <span class="px-2.5 py-1 rounded-lg bg-emerald-100 text-emerald-800 text-xs font-bold">
            🟢 {{ dash.semaforo_resumen.verde }} En ritmo
          </span>
          <span class="px-2.5 py-1 rounded-lg bg-amber-100 text-amber-800 text-xs font-bold">
            🟡 {{ dash.semaforo_resumen.amarillo }} Alerta
          </span>
          <span class="px-2.5 py-1 rounded-lg bg-rose-100 text-rose-800 text-xs font-bold">
            🔴 {{ dash.semaforo_resumen.rojo }} Rezago
          </span>
        </div>
        <p class="text-xs text-slate-500 mt-3">
          {{ dash.inactivos_48h.length }} activistas sin sincronizar &gt; 48 h
        </p>
      </div>
    </div>

    <!-- Tarjetas de Seguimiento de Metas por Clasificación (50 Conector, 50 Experto, 50 Vendedor por Activista) -->
    <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-xs space-y-4">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <div>
          <h3 class="text-sm font-bold text-slate-800">
            Seguimiento de Metas por Clasificación de Talento
          </h3>
          <p class="text-xs text-slate-500">
            Meta requerida por cada Activista:
            <strong>{{ dash.kpis.meta_conector_default || 50 }} Conector</strong> ·
            <strong>{{ dash.kpis.meta_experto_default || 50 }} Experto</strong> ·
            <strong>{{ dash.kpis.meta_vendedor_default || 50 }} Vendedor</strong>
            ({{ dash.kpis.meta_por_activista || 150 }} en total por Activista)
          </p>
        </div>
        <router-link
          to="/auditoria"
          class="text-xs font-semibold text-emerald-700 hover:underline"
        >
          <i class="pi pi-cog mr-1"></i>Ajustar metas en Configuración
        </router-link>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <!-- Conector -->
        <div class="p-4 rounded-xl bg-blue-50/60 border border-blue-200 space-y-2">
          <div class="flex items-center justify-between">
            <span class="text-xs font-extrabold uppercase text-blue-900">Tipo Conector</span>
            <span class="px-2 py-0.5 rounded bg-blue-100 text-blue-800 text-xs font-bold">
              {{ dash.clasificaciones_resumen.Conector.porcentaje }}%
            </span>
          </div>
          <div class="flex items-baseline justify-between">
            <span class="text-2xl font-extrabold text-blue-950">
              {{ dash.clasificaciones_resumen.Conector.registrados }} / {{ dash.clasificaciones_resumen.Conector.meta_global }}
            </span>
            <span class="text-xs text-blue-700">
              Meta por activista: <strong>{{ dash.clasificaciones_resumen.Conector.meta_por_activista }}</strong>
            </span>
          </div>
          <div class="w-full bg-blue-200/70 h-2.5 rounded-full overflow-hidden">
            <div
              class="bg-blue-600 h-full transition-all"
              :style="{ width: `${dash.clasificaciones_resumen.Conector.porcentaje}%` }"
            ></div>
          </div>
        </div>

        <!-- Experto -->
        <div class="p-4 rounded-xl bg-purple-50/60 border border-purple-200 space-y-2">
          <div class="flex items-center justify-between">
            <span class="text-xs font-extrabold uppercase text-purple-900">Tipo Experto</span>
            <span class="px-2 py-0.5 rounded bg-purple-100 text-purple-800 text-xs font-bold">
              {{ dash.clasificaciones_resumen.Experto.porcentaje }}%
            </span>
          </div>
          <div class="flex items-baseline justify-between">
            <span class="text-2xl font-extrabold text-purple-950">
              {{ dash.clasificaciones_resumen.Experto.registrados }} / {{ dash.clasificaciones_resumen.Experto.meta_global }}
            </span>
            <span class="text-xs text-purple-700">
              Meta por activista: <strong>{{ dash.clasificaciones_resumen.Experto.meta_por_activista }}</strong>
            </span>
          </div>
          <div class="w-full bg-purple-200/70 h-2.5 rounded-full overflow-hidden">
            <div
              class="bg-purple-600 h-full transition-all"
              :style="{ width: `${dash.clasificaciones_resumen.Experto.porcentaje}%` }"
            ></div>
          </div>
        </div>

        <!-- Vendedor -->
        <div class="p-4 rounded-xl bg-amber-50/60 border border-amber-200 space-y-2">
          <div class="flex items-center justify-between">
            <span class="text-xs font-extrabold uppercase text-amber-900">Tipo Vendedor</span>
            <span class="px-2 py-0.5 rounded bg-amber-100 text-amber-800 text-xs font-bold">
              {{ dash.clasificaciones_resumen.Vendedor.porcentaje }}%
            </span>
          </div>
          <div class="flex items-baseline justify-between">
            <span class="text-2xl font-extrabold text-amber-950">
              {{ dash.clasificaciones_resumen.Vendedor.registrados }} / {{ dash.clasificaciones_resumen.Vendedor.meta_global }}
            </span>
            <span class="text-xs text-amber-700">
              Meta por activista: <strong>{{ dash.clasificaciones_resumen.Vendedor.meta_por_activista }}</strong>
            </span>
          </div>
          <div class="w-full bg-amber-200/70 h-2.5 rounded-full overflow-hidden">
            <div
              class="bg-amber-600 h-full transition-all"
              :style="{ width: `${dash.clasificaciones_resumen.Vendedor.porcentaje}%` }"
            ></div>
          </div>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Ranking de Activistas y Semáforo -->
      <div class="lg:col-span-2 bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
          <h3 class="font-bold text-slate-800 text-sm">Ranking de Activistas y Avance por Clasificación</h3>
          <button
            type="button"
            class="text-xs text-emerald-600 hover:underline font-semibold cursor-pointer"
            @click="loadDashboard"
          >
            <i class="pi pi-refresh mr-1"></i>Actualizar
          </button>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse text-sm">
            <thead>
              <tr class="bg-slate-50 text-slate-600 text-xs uppercase border-b border-slate-200">
                <th class="py-3 px-4">Activista</th>
                <th class="py-3 px-4">Desglose (Conector / Experto / Vendedor)</th>
                <th class="py-3 px-4">Avance Total</th>
                <th class="py-3 px-4">Semáforo</th>
                <th class="py-3 px-4">Última Sincronización</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="act in dash.ranking" :key="act.id" class="hover:bg-slate-50">
                <td class="py-3 px-4">
                  <div class="font-semibold text-slate-900">{{ act.nombre_completo }}</div>
                  <div class="text-xs text-slate-400">@{{ act.username }} · {{ act.estado_nombre }}</div>
                </td>
                <td class="py-3 px-4">
                  <div class="flex flex-wrap items-center gap-1.5 text-[11px] font-semibold">
                    <span class="px-2 py-0.5 rounded bg-blue-50 text-blue-800 border border-blue-200">
                      Conector: {{ act.conector_registrados ?? 0 }}/{{ act.meta_conector ?? 50 }}
                    </span>
                    <span class="px-2 py-0.5 rounded bg-purple-50 text-purple-800 border border-purple-200">
                      Experto: {{ act.experto_registrados ?? 0 }}/{{ act.meta_experto ?? 50 }}
                    </span>
                    <span class="px-2 py-0.5 rounded bg-amber-50 text-amber-800 border border-amber-200">
                      Vendedor: {{ act.vendedor_registrados ?? 0 }}/{{ act.meta_vendedor ?? 50 }}
                    </span>
                  </div>
                </td>
                <td class="py-3 px-4">
                  <div class="flex items-center justify-between text-xs font-semibold mb-1">
                    <span>{{ act.registrados }} / {{ act.meta }}</span>
                    <span>{{ act.porcentaje }}%</span>
                  </div>
                  <div class="w-36 bg-slate-100 h-2 rounded-full overflow-hidden">
                    <div
                      class="h-full"
                      :class="{
                        'bg-emerald-500': act.semaforo === 'verde',
                        'bg-amber-500': act.semaforo === 'amarillo',
                        'bg-rose-500': act.semaforo === 'rojo',
                      }"
                      :style="{ width: `${act.porcentaje}%` }"
                    ></div>
                  </div>
                </td>
                <td class="py-3 px-4">
                  <span
                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold"
                    :class="{
                      'bg-emerald-100 text-emerald-800': act.semaforo === 'verde',
                      'bg-amber-100 text-amber-800': act.semaforo === 'amarillo',
                      'bg-rose-100 text-rose-800': act.semaforo === 'rojo',
                    }"
                  >
                    <span
                      class="w-2 h-2 rounded-full"
                      :class="{
                        'bg-emerald-500': act.semaforo === 'verde',
                        'bg-amber-500': act.semaforo === 'amarillo',
                        'bg-rose-500': act.semaforo === 'rojo',
                      }"
                    ></span>
                    {{ act.semaforo.toUpperCase() }}
                  </span>
                </td>
                <td class="py-3 px-4 text-xs text-slate-500">
                  {{ act.last_sync_at || 'Sin capturas aún' }}
                </td>
              </tr>
              <tr v-if="dash.ranking.length === 0">
                <td colspan="5" class="py-6 text-center text-slate-400 text-sm">
                  No hay activistas registrados.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Alertas de inactividad > 48 h -->
      <div class="bg-white rounded-xl border border-slate-200 shadow-xs p-5 flex flex-col justify-between">
        <div>
          <h3 class="font-bold text-slate-800 text-sm mb-3 flex items-center gap-2">
            <i class="pi pi-exclamation-triangle text-amber-500"></i>
            Sin sincronizar &gt; 48 horas ({{ dash.inactivos_48h.length }})
          </h3>
          <div v-if="dash.inactivos_48h.length === 0" class="p-4 rounded-lg bg-emerald-50 text-emerald-700 text-xs">
            Todos los activistas han sincronizado en las últimas 48 horas.
          </div>
          <ul v-else class="space-y-2.5 max-h-72 overflow-y-auto">
            <li
              v-for="inact in dash.inactivos_48h"
              :key="inact.id"
              class="p-3 rounded-lg bg-amber-50/70 border border-amber-200 text-xs flex items-center justify-between"
            >
              <div>
                <p class="font-bold text-slate-800">{{ inact.nombre_completo }}</p>
                <p class="text-slate-500">{{ inact.estado_nombre }} · Última: {{ inact.last_sync_at || 'Nunca' }}</p>
              </div>
              <router-link
                to="/activistas"
                class="px-2.5 py-1 rounded bg-amber-600 text-white font-semibold hover:bg-amber-700"
              >
                Revisar
              </router-link>
            </li>
          </ul>
        </div>

        <div class="mt-6 pt-4 border-t border-slate-100">
          <h4 class="text-xs font-bold uppercase text-slate-500 mb-2">Tendencia Últimos 7 Días</h4>
          <div v-if="dash.tendencia_diaria.length === 0" class="text-xs text-slate-400">
            Sin capturas recientes en los últimos 7 días.
          </div>
          <div v-else class="space-y-1.5">
            <div
              v-for="d in dash.tendencia_diaria"
              :key="d.fecha"
              class="flex items-center justify-between text-xs"
            >
              <span class="text-slate-600 font-medium">{{ d.fecha }}</span>
              <span class="font-bold text-emerald-700">{{ d.total }} registros</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { reactive, onMounted } from 'vue';
import api from '@/services/api';

const dash = reactive({
  kpis: {
    total: 0,
    hoy: 0,
    semana: 0,
    meta_global: 150,
    meta_por_activista: 150,
    meta_conector_default: 50,
    meta_experto_default: 50,
    meta_vendedor_default: 50,
    porcentaje_global: 0,
    validados: 0,
    pendientes: 0,
    observados: 0,
    rechazados: 0,
    total_activistas: 0,
  },
  clasificaciones_resumen: {
    Conector: { registrados: 0, meta_por_activista: 50, meta_global: 50, porcentaje: 0 },
    Experto: { registrados: 0, meta_por_activista: 50, meta_global: 50, porcentaje: 0 },
    Vendedor: { registrados: 0, meta_por_activista: 50, meta_global: 50, porcentaje: 0 },
  },
  semaforo_resumen: { verde: 0, amarillo: 0, rojo: 0 },
  ranking: [],
  inactivos_48h: [],
  tendencia_diaria: [],
});

async function loadDashboard() {
  try {
    const { data: res } = await api.get('/admin/dashboard');
    if (res.data) {
      Object.assign(dash, res.data);
    }
  } catch {
    // Mantiene estado por defecto si aún no hay conexión
  }
}

onMounted(loadDashboard);
</script>

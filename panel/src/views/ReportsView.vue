<template>
  <div class="space-y-6">
    <!-- Barra de Filtros Globales: Regiones de Trabajo y Activistas (Uno, Varios o Todos) -->
    <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-xs space-y-4">
      <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-3">
        <div>
          <h3 class="text-sm font-bold text-slate-800">Filtros de Reportes y Exportación</h3>
          <p class="text-xs text-slate-500">
            Selecciona una, varias o todas las Regiones de Trabajo y uno, varios o todos los Activistas. Estos filtros se aplican tanto a las gráficas como a los archivos exportados.
          </p>
        </div>
        <button
          v-if="selectedRegiones.length > 0 || selectedActivistIds.length > 0"
          type="button"
          class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold cursor-pointer"
          @click="clearAllFilters"
        >
          <i class="pi pi-filter-slash mr-1"></i>Restablecer (Todos)
        </button>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <!-- Filtro por Región de Trabajo (Una, varias o todas) -->
        <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200 space-y-2.5">
          <div class="flex items-center justify-between">
            <label class="text-xs font-bold uppercase text-slate-700">
              <i class="pi pi-map mr-1 text-emerald-600"></i>Región de Trabajo
            </label>
            <span class="text-[11px] font-semibold text-emerald-700">
              {{ selectedRegiones.length === 0 ? 'Todas las Regiones' : `${selectedRegiones.length} seleccionada(s)` }}
            </span>
          </div>

          <div class="flex flex-wrap gap-1.5">
            <button
              type="button"
              class="px-3 py-1.5 rounded-lg text-xs font-semibold border transition cursor-pointer"
              :class="
                selectedRegiones.length === 0
                  ? 'bg-emerald-600 text-white border-emerald-600 shadow-2xs'
                  : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-100'
              "
              @click="selectAllRegiones"
            >
              Todas las Regiones
            </button>

            <button
              v-for="reg in availableRegiones"
              :key="reg"
              type="button"
              class="px-3 py-1.5 rounded-lg text-xs font-semibold border transition cursor-pointer flex items-center gap-1.5"
              :class="
                selectedRegiones.includes(reg)
                  ? 'bg-emerald-600 text-white border-emerald-600 shadow-2xs'
                  : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-100'
              "
              @click="toggleRegion(reg)"
            >
              <i class="pi text-[10px]" :class="selectedRegiones.includes(reg) ? 'pi-check-square' : 'pi-stop'"></i>
              <span>Región {{ reg }}</span>
            </button>
          </div>
        </div>

        <!-- Filtro por Activista (Uno, varios o todos) -->
        <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200 space-y-2.5">
          <div class="flex items-center justify-between gap-2">
            <label class="text-xs font-bold uppercase text-slate-700">
              <i class="pi pi-users mr-1 text-blue-600"></i>Activistas
            </label>
            <span class="text-[11px] font-semibold text-blue-700">
              {{ selectedActivistIds.length === 0 ? 'Todos los Activistas' : `${selectedActivistIds.length} seleccionado(s)` }}
            </span>
          </div>

          <div class="flex items-center gap-2">
            <button
              type="button"
              class="px-3 py-1.5 rounded-lg text-xs font-semibold border transition cursor-pointer shrink-0"
              :class="
                selectedActivistIds.length === 0
                  ? 'bg-blue-600 text-white border-blue-600 shadow-2xs'
                  : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-100'
              "
              @click="selectAllActivists"
            >
              Todos los Activistas
            </button>
            <input
              v-model="activistSearch"
              type="text"
              placeholder="Filtrar lista de activistas por nombre o usuario..."
              class="w-full px-2.5 py-1.5 rounded-lg border border-slate-300 bg-white text-xs"
            />
          </div>

          <div class="max-h-28 overflow-y-auto grid grid-cols-1 sm:grid-cols-2 gap-1.5 pr-1">
            <label
              v-for="act in filteredActivistsCatalog"
              :key="act.id"
              class="flex items-center gap-2 px-2.5 py-1.5 rounded-lg border text-xs cursor-pointer transition"
              :class="
                selectedActivistIds.includes(Number(act.id))
                  ? 'bg-blue-50 border-blue-400 text-blue-900 font-semibold'
                  : 'bg-white border-slate-200 text-slate-700 hover:bg-slate-100'
              "
            >
              <input
                type="checkbox"
                class="rounded text-blue-600"
                :checked="selectedActivistIds.includes(Number(act.id))"
                @change="toggleActivist(Number(act.id))"
              />
              <span class="truncate">{{ act.nombre_completo }}</span>
              <span class="ml-auto px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-800 text-[10px] font-bold shrink-0">
                R{{ act.region_trabajo ?? 1 }}
              </span>
            </label>
            <div v-if="filteredActivistsCatalog.length === 0" class="col-span-2 text-xs text-slate-400 py-2 text-center">
              No se encontraron activistas con ese criterio.
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Selector de Reportes y Generador de Exportación (RF-P5 y RF-P6) -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Reportes con Gráficas Chart.js -->
      <div class="lg:col-span-2 bg-white rounded-xl border border-slate-200 p-5 shadow-xs space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3">
          <div>
            <h3 class="text-sm font-bold text-slate-800">Reportes Analíticos del Padrón</h3>
            <p class="text-xs text-slate-500">Agrupaciones por clasificación, activista, región de trabajo, territorio y sección</p>
          </div>
          <select v-model="reportType" class="px-3 py-2 rounded-lg border border-slate-300 text-xs font-semibold" @change="loadReport">
            <option value="clasificacion">Por Clasificación (Conector / Experto / Vendedor)</option>
            <option value="activista">Por Activista</option>
            <option value="region">Por Región de Trabajo</option>
            <option value="territorial">Por Clave de Estado y Municipio</option>
            <option value="seccion">Por Sección Electoral</option>
            <option value="cp">Por Código Postal</option>
            <option value="fecha">Por Fecha de Captura</option>
            <option value="cobertura">Cobertura de Secciones INE</option>
            <option value="vigencias">Credenciales con Vigencia Vencida</option>
            <option value="duplicados">Detección de Duplicados</option>
          </select>
        </div>

        <!-- Gráfica Chart.js -->
        <div v-if="chartData.labels.length > 0" class="h-56">
          <Bar :data="chartData" :options="chartOptions" />
        </div>

        <!-- Tabla de resultados del reporte -->
        <div class="overflow-x-auto max-h-80">
          <table class="w-full text-left border-collapse text-xs">
            <thead>
              <tr class="bg-slate-50 text-slate-600 uppercase border-b border-slate-200">
                <th v-for="col in tableColumns" :key="col" class="py-2.5 px-3">{{ col }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="(row, idx) in reportRows" :key="idx" class="hover:bg-slate-50">
                <td v-for="col in tableColumns" :key="col" class="py-2 px-3">
                  {{ formatCell(row[col]) }}
                </td>
              </tr>
              <tr v-if="reportRows.length === 0">
                <td :colspan="Math.max(1, tableColumns.length)" class="py-6 text-center text-slate-400">
                  No hay datos para este reporte.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Módulo de Exportación Auditada (RF-P6) -->
      <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-xs flex flex-col justify-between space-y-4">
        <div class="space-y-4">
          <div>
            <h3 class="text-sm font-bold text-slate-800">Exportar Padrón y Cartografía</h3>
            <p class="text-xs text-slate-500">
              Formatos disponibles: Excel (.xlsx), CSV, PDF, KML y GeoJSON. Toda descarga queda registrada en auditoría.
            </p>
          </div>

          <form class="space-y-3 text-xs" @submit.prevent="generateExport">
            <div>
              <label class="font-semibold text-slate-600 block mb-1">Formato de archivo</label>
              <select v-model="exportForm.formato" class="w-full px-3 py-2 rounded-lg border border-slate-300 font-semibold">
                <option value="xlsx">Excel (.xlsx — PhpSpreadsheet)</option>
                <option value="csv">CSV UTF-8 (.csv)</option>
                <option value="pdf">Reporte PDF (.pdf — Dompdf)</option>
                <option value="geojson">GeoJSON para SIG (.geojson)</option>
                <option value="kml">Google Earth (.kml)</option>
              </select>
            </div>

            <div class="grid grid-cols-2 gap-2">
              <div>
                <label class="font-semibold text-slate-600 block mb-1">Sección (opcional)</label>
                <input v-model="exportForm.filtros.seccion" type="number" placeholder="Todas" class="w-full px-3 py-2 rounded-lg border border-slate-300" />
              </div>
              <div>
                <label class="font-semibold text-slate-600 block mb-1">Estatus</label>
                <select v-model="exportForm.filtros.validacion" class="w-full px-3 py-2 rounded-lg border border-slate-300">
                  <option value="">Todos</option>
                  <option value="validado">Solo Validados</option>
                  <option value="pendiente">Pendientes</option>
                </select>
              </div>
            </div>

            <div class="p-2.5 rounded-lg bg-slate-50 border border-slate-200 text-[11px] text-slate-600 space-y-1">
              <div class="flex items-center justify-between">
                <span class="font-semibold">Regiones a exportar:</span>
                <span class="font-bold text-emerald-700">
                  {{ selectedRegiones.length === 0 ? 'Todas' : selectedRegiones.map((r) => `Región ${r}`).join(', ') }}
                </span>
              </div>
              <div class="flex items-center justify-between">
                <span class="font-semibold">Activistas a exportar:</span>
                <span class="font-bold text-blue-700">
                  {{ selectedActivistIds.length === 0 ? 'Todos' : `${selectedActivistIds.length} seleccionado(s)` }}
                </span>
              </div>
              <div class="text-[10px] text-slate-400">
                * El reporte exportado incluye el nombre del Activista y su Región de Trabajo en cada registro.
              </div>
            </div>

            <button
              type="submit"
              :disabled="exportLoading"
              class="w-full py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-semibold shadow-xs cursor-pointer"
            >
              <i class="pi pi-download mr-1"></i>
              {{ exportLoading ? 'Generando archivo...' : 'Generar y Descargar Exportación' }}
            </button>
          </form>

          <!-- Historial reciente de exportaciones -->
          <div class="pt-3 border-t border-slate-100">
            <h4 class="text-xs font-bold uppercase text-slate-500 mb-2">Bitácora de Exportaciones Recientes</h4>
            <ul class="space-y-2 max-h-56 overflow-y-auto text-xs">
              <li
                v-for="exp in exportsList"
                :key="exp.id"
                class="p-2.5 rounded-lg bg-slate-50 border border-slate-200 flex items-center justify-between"
              >
                <div>
                  <span class="font-bold uppercase text-slate-800">{{ exp.formato }}</span>
                  <span class="text-slate-500 ml-1">({{ exp.total_rows }} filas)</span>
                  <div class="text-[11px] text-slate-400">{{ exp.created_at }} · {{ exp.username }}</div>
                </div>
                <a
                  :href="`/api/v1/admin/exports/${exp.id}?token=${auth.token}`"
                  target="_blank"
                  class="px-2.5 py-1 rounded bg-slate-800 hover:bg-slate-700 text-white font-semibold"
                >
                  Descargar
                </a>
              </li>
              <li v-if="exportsList.length === 0" class="text-slate-400">Sin exportaciones previas.</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { Bar } from 'vue-chartjs';
import {
  Chart as ChartJS,
  Title,
  Tooltip,
  Legend,
  BarElement,
  CategoryScale,
  LinearScale,
} from 'chart.js';
import api from '@/services/api';
import { useAuthStore } from '@/stores/auth';

ChartJS.register(Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale);

const auth = useAuthStore();
const reportType = ref('clasificacion');
const reportRows = ref([]);
const exportsList = ref([]);
const exportLoading = ref(false);

const availableActivists = ref([]);
const availableRegiones = ref([1]);
const selectedActivistIds = ref([]);
const selectedRegiones = ref([]);
const activistSearch = ref('');

const filteredActivistsCatalog = computed(() => {
  const q = activistSearch.value.trim().toLowerCase();
  return availableActivists.value.filter((act) => {
    if (selectedRegiones.value.length > 0 && !selectedRegiones.value.includes(Number(act.region_trabajo ?? 1))) {
      return false;
    }
    if (!q) return true;
    const full = `${act.nombre_completo || ''} ${act.username || ''}`.toLowerCase();
    return full.includes(q);
  });
});

const exportForm = reactive({
  formato: 'xlsx',
  filtros: {
    seccion: '',
    validacion: '',
    activist_ids: [],
    regiones: [],
  },
});

const tableColumns = computed(() => {
  if (reportRows.value.length === 0) return [];
  return Object.keys(reportRows.value[0]);
});

const chartData = computed(() => {
  const rows = reportRows.value.slice(0, 10);
  if (rows.length === 0) return { labels: [], datasets: [] };

  const labels = rows.map(
    (r) =>
      r.clasificacion ||
      (r.region_trabajo !== undefined ? `Región ${r.region_trabajo}` : null) ||
      r.seccion ||
      r.estado ||
      r.nombre ||
      r.codigo_postal ||
      r.fecha ||
      r.criterio ||
      'Item'
  );
  const values = rows.map(
    (r) => Number(r.total ?? r.registrados ?? r.talentos_registrados ?? r.repeticiones ?? 1)
  );

  return {
    labels,
    datasets: [
      {
        label: 'Talentos Registrados',
        backgroundColor: '#10b981',
        borderRadius: 6,
        data: values,
      },
    ],
  };
});

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: false },
  },
};

function formatCell(val) {
  if (Array.isArray(val)) return `${val.length} registros`;
  if (val && typeof val === 'object') return JSON.stringify(val);
  return val ?? '-';
}

function toggleRegion(reg) {
  const num = Number(reg);
  const idx = selectedRegiones.value.indexOf(num);
  if (idx >= 0) {
    selectedRegiones.value.splice(idx, 1);
  } else {
    selectedRegiones.value.push(num);
  }
  loadReport();
}

function selectAllRegiones() {
  selectedRegiones.value = [];
  loadReport();
}

function toggleActivist(id) {
  const num = Number(id);
  const idx = selectedActivistIds.value.indexOf(num);
  if (idx >= 0) {
    selectedActivistIds.value.splice(idx, 1);
  } else {
    selectedActivistIds.value.push(num);
  }
  loadReport();
}

function selectAllActivists() {
  selectedActivistIds.value = [];
  loadReport();
}

function clearAllFilters() {
  selectedRegiones.value = [];
  selectedActivistIds.value = [];
  activistSearch.value = '';
  loadReport();
}

async function loadReport() {
  const params = {};
  if (selectedActivistIds.value.length > 0) {
    params.activist_ids = selectedActivistIds.value.join(',');
  }
  if (selectedRegiones.value.length > 0) {
    params.regiones = selectedRegiones.value.join(',');
  }
  const { data: res } = await api.get(`/admin/reports/${reportType.value}`, { params });
  reportRows.value = res.data || [];
  if (res.meta?.catalogs) {
    availableActivists.value = res.meta.catalogs.activists || [];
    if (Array.isArray(res.meta.catalogs.regiones) && res.meta.catalogs.regiones.length > 0) {
      availableRegiones.value = res.meta.catalogs.regiones.map((r) => Number(r));
    }
  }
}

async function loadExports() {
  const { data: res } = await api.get('/admin/exports');
  exportsList.value = res.data || [];
}

async function generateExport() {
  exportLoading.value = true;
  try {
    const payload = {
      formato: exportForm.formato,
      filtros: {
        seccion: exportForm.filtros.seccion,
        validacion: exportForm.filtros.validacion,
        activist_ids: selectedActivistIds.value,
        regiones: selectedRegiones.value,
      },
    };
    const { data: res } = await api.post('/admin/exports', payload);
    await loadExports();
    if (res.data?.id) {
      window.open(`/api/v1/admin/exports/${res.data.id}?token=${auth.token}`, '_blank');
    }
  } finally {
    exportLoading.value = false;
  }
}

onMounted(async () => {
  await loadReport();
  await loadExports();
});
</script>

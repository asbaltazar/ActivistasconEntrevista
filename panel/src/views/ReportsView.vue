<template>
  <div class="space-y-6">
    <!-- Selector de Reportes y Generador de Exportación (RF-P5 y RF-P6) -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Reportes con Gráficas Chart.js -->
      <div class="lg:col-span-2 bg-white rounded-xl border border-slate-200 p-5 shadow-xs space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3">
          <div>
            <h3 class="text-sm font-bold text-slate-800">Reportes Analíticos del Padrón</h3>
            <p class="text-xs text-slate-500">Agrupaciones por territorio, sección electoral, vigencias y duplicados</p>
          </div>
          <select v-model="reportType" class="px-3 py-2 rounded-lg border border-slate-300 text-xs font-semibold" @change="loadReport">
            <option value="activista">Por Activista</option>
            <option value="territorial">Por Estado y Municipio</option>
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
const reportType = ref('seccion');
const reportRows = ref([]);
const exportsList = ref([]);
const exportLoading = ref(false);

const exportForm = reactive({
  formato: 'xlsx',
  filtros: {
    seccion: '',
    validacion: '',
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
    (r) => r.seccion || r.estado || r.nombre || r.codigo_postal || r.fecha || r.criterio || 'Item'
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

async function loadReport() {
  const { data: res } = await api.get(`/admin/reports/${reportType.value}`);
  reportRows.value = res.data || [];
}

async function loadExports() {
  const { data: res } = await api.get('/admin/exports');
  exportsList.value = res.data || [];
}

async function generateExport() {
  exportLoading.value = true;
  try {
    const { data: res } = await api.post('/admin/exports', exportForm);
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

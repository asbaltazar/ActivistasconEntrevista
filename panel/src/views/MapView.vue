<template>
  <div class="space-y-4">
    <!-- Controles y filtros de mapa (RF-P4) -->
    <div class="bg-white rounded-xl p-4 border border-slate-200 shadow-xs flex flex-wrap items-center justify-between gap-3">
      <div class="flex flex-wrap items-center gap-3 text-sm">
        <select v-model="filters.estado_id" class="px-3 py-2 rounded-lg border border-slate-300" @change="loadMapPoints">
          <option value="0">Todo México (32 entidades)</option>
          <option v-for="e in estados" :key="e.id" :value="e.id">{{ e.nombre }}</option>
        </select>

        <input
          v-model="filters.seccion"
          type="number"
          placeholder="Sección electoral"
          class="px-3 py-2 rounded-lg border border-slate-300 w-40"
          @keyup.enter="loadMapPoints"
        />

        <select v-model="filters.validacion" class="px-3 py-2 rounded-lg border border-slate-300" @change="loadMapPoints">
          <option value="">Todos los estatus</option>
          <option value="validado">Validados</option>
          <option value="pendiente">Pendientes</option>
          <option value="observado">Observados</option>
        </select>

        <label class="flex items-center gap-1.5 text-xs font-semibold text-slate-700 cursor-pointer">
          <input v-model="showPolygons" type="checkbox" @change="loadMapPoints" />
          <span>Capa GeoJSON Secciones INE</span>
        </label>

        <label class="flex items-center gap-1.5 text-xs font-semibold text-slate-700 cursor-pointer">
          <input v-model="heatmapMode" type="checkbox" @change="renderVisualMap" />
          <span>Mapa de Calor</span>
        </label>
      </div>

      <div class="text-xs font-semibold text-slate-600">
        Puntos en vista: <span class="text-emerald-700 font-bold">{{ points.length }}</span>
        <span v-if="polygons.length" class="ml-2 text-blue-700">· {{ polygons.length }} polígonos INE</span>
      </div>
    </div>

    <!-- Contenedor principal del mapa -->
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-4">
      <div class="lg:col-span-3 bg-slate-900 rounded-2xl border border-slate-300 overflow-hidden relative h-[520px] shadow-md">
        <!-- Div para Google Maps JS API cuando hay llave de navegador -->
        <div v-show="googleLoaded" ref="mapContainer" class="w-full h-full"></div>

        <!-- Mapa interactivo vectorial de respaldo (MarkerCluster + Heatmap + GeoJSON) cuando no hay VITE_GOOGLE_MAPS_BROWSER_KEY -->
        <div v-if="!googleLoaded" class="w-full h-full flex flex-col justify-between p-4 bg-gradient-to-br from-slate-900 via-slate-800 to-slate-950 text-white relative">
          <div class="flex items-center justify-between z-10">
            <span class="px-2.5 py-1 rounded bg-emerald-500/20 border border-emerald-400/30 text-emerald-300 text-xs font-semibold">
              Cartografía Electoral Nacional · Clustering Activo ({{ clusters.length }} clústeres)
            </span>
            <span class="text-[11px] text-slate-400">
              Configure VITE_GOOGLE_MAPS_BROWSER_KEY en .env.local para teselas satelitales de Google
            </span>
          </div>

          <svg viewBox="-118 14 32 19" class="w-full h-[410px] border border-slate-700/60 rounded-xl bg-slate-950/60">
            <!-- Polígonos GeoJSON de secciones -->
            <g v-if="showPolygons">
              <circle
                v-for="poly in polygons"
                :key="poly.seccion"
                :cx="poly.geometry?.coordinates?.[0]?.[0]?.[0] || -99.13"
                :cy="47 - (poly.geometry?.coordinates?.[0]?.[0]?.[1] || 19.43)"
                r="0.45"
                fill="rgba(59, 130, 246, 0.25)"
                stroke="#60a5fa"
                stroke-width="0.08"
              />
            </g>

            <!-- Puntos / Clústeres / Heatmap -->
            <g v-for="(cl, idx) in clusters" :key="idx" class="cursor-pointer" @click="selectedCluster = cl">
              <circle
                v-if="heatmapMode"
                :cx="cl.lng"
                :cy="47 - cl.lat"
                :r="Math.min(2.2, 0.4 + cl.count * 0.18)"
                fill="rgba(239, 68, 68, 0.35)"
              />
              <circle
                :cx="cl.lng"
                :cy="47 - cl.lat"
                :r="cl.count > 1 ? 0.55 : 0.32"
                :fill="cl.color"
                stroke="#ffffff"
                stroke-width="0.07"
              />
              <text
                v-if="cl.count > 1"
                :x="cl.lng"
                :y="47 - cl.lat + 0.14"
                text-anchor="middle"
                fill="#ffffff"
                font-size="0.42"
                font-weight="bold"
              >
                {{ cl.count }}
              </text>
            </g>
          </svg>

          <div class="flex items-center justify-between text-xs text-slate-400 z-10">
            <span>Haz clic en un marcador o clúster para inspeccionar los Talentos de la sección</span>
            <span>Coordenadas DECIMAL(10,7) indexadas en MariaDB 10.5</span>
          </div>
        </div>
      </div>

      <!-- Panel lateral de leyenda por Activista y detalle de punto seleccionado -->
      <div class="bg-white rounded-xl border border-slate-200 p-4 flex flex-col justify-between space-y-4">
        <div>
          <h3 class="text-sm font-bold text-slate-800 mb-2">Colores por Activista</h3>
          <div class="space-y-2 max-h-56 overflow-y-auto text-xs">
            <div
              v-for="act in activistLegend"
              :key="act.activist_id"
              class="flex items-center justify-between p-2 rounded-lg bg-slate-50"
            >
              <div class="flex items-center gap-2">
                <span class="w-3 h-3 rounded-full shrink-0" :style="{ backgroundColor: act.color }"></span>
                <span class="font-medium text-slate-700 truncate">{{ act.name }}</span>
              </div>
              <span class="font-bold text-slate-900">{{ act.count }}</span>
            </div>
            <div v-if="activistLegend.length === 0" class="text-slate-400">Sin puntos cargados.</div>
          </div>
        </div>

        <div v-if="selectedCluster" class="p-3 rounded-xl bg-emerald-50 border border-emerald-200 text-xs space-y-1.5">
          <div class="font-bold text-emerald-900">Detalle de Ubicación ({{ selectedCluster.count }} registros)</div>
          <div v-for="item in selectedCluster.items.slice(0, 5)" :key="item.id" class="border-t border-emerald-200/60 pt-1">
            <div class="font-semibold text-slate-800">{{ item.nombre }} (Sec. {{ item.seccion }})</div>
            <div class="text-slate-500">Col. {{ item.colonia }} · CP {{ item.cp }} · {{ item.validacion }}</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { Loader } from '@googlemaps/js-api-loader';
import { MarkerClusterer } from '@googlemaps/markerclusterer';
import api from '@/services/api';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const estados = ref([]);
const points = ref([]);
const polygons = ref([]);
const showPolygons = ref(true);
const heatmapMode = ref(false);
const selectedCluster = ref(null);
const mapContainer = ref(null);
const googleLoaded = ref(false);

let gmap = null;
let clusterer = null;

const palette = ['#10b981', '#3b82f6', '#f59e0b', '#8b5cf6', '#ec4899', '#14b8a6', '#f97316'];

const filters = reactive({
  estado_id: 0,
  seccion: '',
  validacion: '',
});

function colorForActivist(activistId) {
  return palette[Number(activistId || 0) % palette.length];
}

const activistLegend = computed(() => {
  const map = new Map();
  for (const p of points.value) {
    const key = p.activist_id;
    if (!map.has(key)) {
      map.set(key, {
        activist_id: key,
        name: p.activist || `Activista #${key}`,
        color: colorForActivist(key),
        count: 0,
      });
    }
    map.get(key).count++;
  }
  return Array.from(map.values());
});

const clusters = computed(() => {
  const bucketMap = new Map();
  for (const p of points.value) {
    const key = `${p.lat.toFixed(1)}_${p.lng.toFixed(1)}`;
    if (!bucketMap.has(key)) {
      bucketMap.set(key, {
        lat: p.lat,
        lng: p.lng,
        count: 0,
        color: colorForActivist(p.activist_id),
        items: [],
      });
    }
    const b = bucketMap.get(key);
    b.count++;
    b.items.push(p);
  }
  return Array.from(bucketMap.values());
});

async function loadMapPoints() {
  const params = { include_polygons: showPolygons.value ? 1 : 0 };
  if (filters.estado_id > 0) params.estado_id = filters.estado_id;
  if (filters.seccion) params.seccion = filters.seccion;
  if (filters.validacion) params.validacion = filters.validacion;

  const { data: res } = await api.get('/admin/map/points', { params });
  points.value = res.data?.points || [];
  polygons.value = res.data?.polygons || [];
  renderVisualMap();
}

async function initGoogleMapIfKeyAvailable() {
  const browserKey = import.meta.env.VITE_GOOGLE_MAPS_BROWSER_KEY;
  if (!browserKey || !mapContainer.value) return;

  try {
    const loader = new Loader({
      apiKey: browserKey,
      version: 'weekly',
      libraries: ['visualization'],
    });
    const google = await loader.load();
    gmap = new google.maps.Map(mapContainer.value, {
      center: { lat: 23.6345, lng: -102.5528 },
      zoom: 5,
    });
    googleLoaded.value = true;
    renderVisualMap();
  } catch {
    googleLoaded.value = false;
  }
}

function renderVisualMap() {
  if (!googleLoaded.value || !gmap || !window.google) return;
  if (clusterer) clusterer.clearMarkers();

  const markers = points.value.map(
    (p) =>
      new window.google.maps.Marker({
        position: { lat: p.lat, lng: p.lng },
        title: `${p.nombre} (Sección ${p.seccion})`,
      })
  );
  clusterer = new MarkerClusterer({ map: gmap, markers });
}

onMounted(async () => {
  const cats = await auth.fetchCatalogs();
  estados.value = cats.estados || [];
  await loadMapPoints();
  await initGoogleMapIfKeyAvailable();
});
</script>

<template>
  <div class="space-y-5">
    <!-- Filtros avanzados de Talentos (RF-P2) -->
    <div class="bg-white rounded-xl p-4 border border-slate-200 shadow-xs space-y-3">
      <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
        <input
          v-model="filters.q"
          type="text"
          placeholder="Nombre, clave elector, CURP, tel..."
          class="px-3 py-2 rounded-lg border border-slate-300 text-sm"
          @keyup.enter="loadTalents"
        />
        <select v-model="filters.estado_id" class="px-3 py-2 rounded-lg border border-slate-300 text-sm" @change="loadTalents">
          <option value="0">Todas las entidades</option>
          <option v-for="e in estados" :key="e.id" :value="e.id">{{ e.nombre }}</option>
        </select>
        <input
          v-model="filters.seccion"
          type="number"
          placeholder="Sección (ej. 4812)"
          class="px-3 py-2 rounded-lg border border-slate-300 text-sm"
        />
        <input
          v-model="filters.codigo_postal"
          type="text"
          maxlength="5"
          placeholder="Código Postal"
          class="px-3 py-2 rounded-lg border border-slate-300 text-sm"
        />
        <select v-model="filters.validacion" class="px-3 py-2 rounded-lg border border-slate-300 text-sm" @change="loadTalents">
          <option value="">Todos los estatus</option>
          <option value="pendiente">Pendientes</option>
          <option value="validado">Validados</option>
          <option value="observado">Observados</option>
          <option value="rechazado">Rechazados</option>
        </select>
        <div class="flex items-center gap-2">
          <label class="flex items-center gap-1.5 text-xs text-slate-700 cursor-pointer">
            <input v-model="filters.vigencia_vencida" type="checkbox" @change="loadTalents" />
            <span>INE vencida</span>
          </label>
          <button
            type="button"
            class="ml-auto px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold cursor-pointer"
            @click="loadTalents"
          >
            Buscar
          </button>
        </div>
      </div>
    </div>

    <!-- Tabla de Talentos -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden">
      <div class="px-5 py-3 border-b border-slate-200 flex items-center justify-between text-xs text-slate-600">
        <span>Mostrando <strong>{{ talents.length }}</strong> de <strong>{{ meta.total }}</strong> Talentos</span>
        <button
          v-if="auth.canWrite"
          type="button"
          class="text-blue-600 hover:underline font-semibold cursor-pointer"
          @click="showMergeModal = true"
        >
          <i class="pi pi-clone mr-1"></i>Fusionar Duplicados
        </button>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-sm">
          <thead>
            <tr class="bg-slate-50 text-slate-600 text-xs uppercase border-b border-slate-200">
              <th class="py-3 px-4">ID / Ciudadano</th>
              <th class="py-3 px-4">Clave Elector / CURP</th>
              <th class="py-3 px-4">Ubicación / Sección</th>
              <th class="py-3 px-4">Teléfono</th>
              <th class="py-3 px-4">Activista</th>
              <th class="py-3 px-4">Validación</th>
              <th class="py-3 px-4 text-right">Detalle / Acción</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="t in talents" :key="t.id" class="hover:bg-slate-50">
              <td class="py-3 px-4">
                <div class="font-bold text-slate-900">#{{ t.id }} · {{ t.nombre_completo }}</div>
                <div class="text-xs text-slate-400 font-mono">UUID: {{ t.uuid.slice(0, 13) }}...</div>
              </td>
              <td class="py-3 px-4 font-mono text-xs">
                <div class="font-semibold text-slate-800">{{ t.clave_elector }}</div>
                <div class="text-slate-500">{{ t.curp || 'Sin CURP' }} · Vig: {{ t.vigencia }}</div>
                <span v-if="t.vigencia_vencida" class="inline-block px-1.5 py-0.5 rounded bg-rose-100 text-rose-700 text-[10px] font-sans font-bold">
                  CREDENCIAL VENCIDA
                </span>
              </td>
              <td class="py-3 px-4 text-xs">
                <div class="font-semibold text-slate-800">Sección {{ t.seccion }} · CP {{ t.codigo_postal }}</div>
                <div class="text-slate-500">{{ t.colonia }}, {{ t.municipio_nombre }}, {{ t.estado_nombre }}</div>
              </td>
              <td class="py-3 px-4 font-mono text-xs text-slate-800">
                {{ t.telefono }}
              </td>
              <td class="py-3 px-4 text-xs text-slate-600">
                {{ t.activist_nombre || `ID #${t.activist_id}` }}
              </td>
              <td class="py-3 px-4">
                <span
                  class="px-2.5 py-1 rounded-full text-xs font-semibold"
                  :class="{
                    'bg-amber-100 text-amber-800': t.validacion === 'pendiente',
                    'bg-emerald-100 text-emerald-800': t.validacion === 'validado',
                    'bg-blue-100 text-blue-800': t.validacion === 'observado',
                    'bg-rose-100 text-rose-800': t.validacion === 'rechazado',
                  }"
                >
                  {{ t.validacion }}
                </span>
              </td>
              <td class="py-3 px-4 text-right space-x-1">
                <button
                  type="button"
                  class="px-3 py-1.5 rounded bg-slate-900 hover:bg-slate-700 text-white text-xs font-semibold cursor-pointer"
                  @click="openDetail(t.id)"
                >
                  Ver INE / Validar
                </button>
                <button
                  v-if="auth.canWrite"
                  type="button"
                  title="Eliminar lógicamente"
                  class="px-2 py-1.5 rounded bg-rose-50 hover:bg-rose-100 text-rose-700 text-xs cursor-pointer"
                  @click="deleteTalent(t.id)"
                >
                  <i class="pi pi-trash"></i>
                </button>
              </td>
            </tr>
            <tr v-if="talents.length === 0">
              <td colspan="7" class="py-8 text-center text-slate-400">No se encontraron Talentos con los criterios indicados.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Modal Detalle con Visor Autenticado de INE y Selfie -->
    <div v-if="selectedTalent" class="fixed inset-0 bg-slate-950/70 flex items-center justify-center z-50 p-4 overflow-y-auto">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl p-6 space-y-5 my-8">
        <div class="flex items-center justify-between border-b border-slate-200 pb-3">
          <div>
            <h3 class="text-lg font-bold text-slate-900">
              Expediente de Talento #{{ selectedTalent.id }} — {{ selectedTalent.nombre_completo }}
            </h3>
            <p class="text-xs text-slate-500">
              Consentimiento LFPDPPP: {{ selectedTalent.consentimiento_at }} (Versión {{ selectedTalent.aviso_version }}) · UUID: {{ selectedTalent.uuid }}
            </p>
          </div>
          <button type="button" class="text-slate-400 hover:text-slate-700 text-xl cursor-pointer" @click="selectedTalent = null">
            &times;
          </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <!-- Datos editables y validación -->
          <div class="space-y-3 text-xs">
            <div class="grid grid-cols-3 gap-2">
              <div>
                <label class="font-semibold text-slate-600">Nombre</label>
                <input v-model="selectedTalent.nombre" class="w-full px-2.5 py-1.5 rounded border border-slate-300 mt-0.5" />
              </div>
              <div>
                <label class="font-semibold text-slate-600">Ap. Paterno</label>
                <input v-model="selectedTalent.ap_paterno" class="w-full px-2.5 py-1.5 rounded border border-slate-300 mt-0.5" />
              </div>
              <div>
                <label class="font-semibold text-slate-600">Ap. Materno</label>
                <input v-model="selectedTalent.ap_materno" class="w-full px-2.5 py-1.5 rounded border border-slate-300 mt-0.5" />
              </div>
            </div>

            <div class="grid grid-cols-2 gap-2">
              <div>
                <label class="font-semibold text-slate-600">Clave de Elector (Cifrada en BD)</label>
                <input v-model="selectedTalent.clave_elector" class="w-full px-2.5 py-1.5 rounded border border-slate-300 font-mono mt-0.5" />
              </div>
              <div>
                <label class="font-semibold text-slate-600">CURP</label>
                <input v-model="selectedTalent.curp" class="w-full px-2.5 py-1.5 rounded border border-slate-300 font-mono mt-0.5" />
              </div>
            </div>

            <div class="grid grid-cols-3 gap-2">
              <div class="col-span-2">
                <label class="font-semibold text-slate-600">Calle y Número</label>
                <input v-model="selectedTalent.calle_numero" class="w-full px-2.5 py-1.5 rounded border border-slate-300 mt-0.5" />
              </div>
              <div>
                <label class="font-semibold text-slate-600">Colonia</label>
                <input v-model="selectedTalent.colonia" class="w-full px-2.5 py-1.5 rounded border border-slate-300 mt-0.5" />
              </div>
            </div>

            <div class="grid grid-cols-4 gap-2">
              <div>
                <label class="font-semibold text-slate-600">CP</label>
                <input v-model="selectedTalent.codigo_postal" class="w-full px-2.5 py-1.5 rounded border border-slate-300 mt-0.5" />
              </div>
              <div>
                <label class="font-semibold text-slate-600">Sección</label>
                <input v-model.number="selectedTalent.seccion" type="number" class="w-full px-2.5 py-1.5 rounded border border-slate-300 mt-0.5" />
              </div>
              <div>
                <label class="font-semibold text-slate-600">Vigencia</label>
                <input v-model.number="selectedTalent.vigencia" type="number" class="w-full px-2.5 py-1.5 rounded border border-slate-300 mt-0.5" />
              </div>
              <div>
                <label class="font-semibold text-slate-600">Teléfono</label>
                <input v-model="selectedTalent.telefono" class="w-full px-2.5 py-1.5 rounded border border-slate-300 font-mono mt-0.5" />
              </div>
            </div>

            <div class="p-3 rounded-lg bg-slate-50 border border-slate-200 space-y-2">
              <div class="font-bold text-slate-700">Dictamen de Validación</div>
              <div class="grid grid-cols-2 gap-2">
                <select v-model="selectedTalent.validacion" class="px-2.5 py-1.5 rounded border border-slate-300 font-semibold">
                  <option value="pendiente">Pendiente</option>
                  <option value="validado">Validado (Bloquea edición en App)</option>
                  <option value="observado">Observado (Solicita corrección)</option>
                  <option value="rechazado">Rechazado</option>
                </select>
                <input
                  v-model="selectedTalent.observaciones"
                  placeholder="Observaciones para el activista..."
                  class="px-2.5 py-1.5 rounded border border-slate-300"
                />
              </div>
            </div>
          </div>

          <!-- Evidencias fotográficas servidas fuera de public_html -->
          <div class="space-y-3">
            <h4 class="text-xs font-bold uppercase text-slate-600">
              Evidencias Fotográficas Autenticadas (Acceso registrado en Auditoría)
            </h4>
            <div v-if="!selectedTalent.files || selectedTalent.files.length === 0" class="p-6 rounded-xl bg-slate-50 border border-dashed border-slate-300 text-center text-xs text-slate-400">
              No hay archivos adjuntos para este registro.
            </div>
            <div v-else class="grid grid-cols-2 gap-3">
              <div
                v-for="f in selectedTalent.files"
                :key="f.id"
                class="p-2 rounded-xl border border-slate-200 bg-slate-50"
              >
                <div class="text-[11px] font-bold uppercase text-slate-600 mb-1">{{ f.tipo }}</div>
                <img
                  :src="`${f.url}?token=${auth.token}`"
                  :alt="f.tipo"
                  class="w-full h-32 object-cover rounded-lg border border-slate-200 bg-slate-900"
                />
              </div>
            </div>
            <div class="text-[11px] text-slate-500">
              Coordenadas domicilio: {{ selectedTalent.lat }}, {{ selectedTalent.lng }} ({{ selectedTalent.geocode_precision }}) ·
              GPS evidencia campo: {{ selectedTalent.captura_lat || 'N/A' }}, {{ selectedTalent.captura_lng || 'N/A' }}
            </div>
          </div>
        </div>

        <div class="flex justify-end gap-2 pt-3 border-t border-slate-200">
          <button
            type="button"
            class="px-4 py-2 rounded-lg bg-slate-100 text-slate-700 text-xs font-semibold cursor-pointer"
            @click="selectedTalent = null"
          >
            Cerrar
          </button>
          <button
            v-if="auth.canWrite"
            type="button"
            class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold cursor-pointer"
            @click="saveTalentDetail"
          >
            Guardar Cambios y Dictamen
          </button>
        </div>
      </div>
    </div>

    <!-- Modal Fusión de Duplicados -->
    <div v-if="showMergeModal" class="fixed inset-0 bg-slate-950/60 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6 space-y-4">
        <h3 class="text-base font-bold text-slate-900">Fusionar Registros Duplicados</h3>
        <p class="text-xs text-slate-600">
          Transfiere las evidencias faltantes al registro primario y da de baja lógica el registro secundario.
        </p>
        <div class="grid grid-cols-2 gap-3 text-xs">
          <div>
            <label class="font-semibold text-slate-600">ID Primario (Conservar)</label>
            <input v-model.number="mergeForm.primary_id" type="number" class="w-full px-3 py-2 rounded border border-slate-300 mt-1" />
          </div>
          <div>
            <label class="font-semibold text-slate-600">ID Secundario (Absorber)</label>
            <input v-model.number="mergeForm.secondary_id" type="number" class="w-full px-3 py-2 rounded border border-slate-300 mt-1" />
          </div>
        </div>
        <div class="flex justify-end gap-2 pt-2">
          <button type="button" class="px-3 py-1.5 rounded bg-slate-100 text-xs font-semibold cursor-pointer" @click="showMergeModal = false">
            Cancelar
          </button>
          <button type="button" class="px-4 py-1.5 rounded bg-blue-600 text-white text-xs font-semibold cursor-pointer" @click="submitMerge">
            Fusionar
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import api from '@/services/api';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const talents = ref([]);
const estados = ref([]);
const meta = reactive({ page: 1, per_page: 25, total: 0 });
const selectedTalent = ref(null);
const showMergeModal = ref(false);
const mergeForm = reactive({ primary_id: null, secondary_id: null });

const filters = reactive({
  q: '',
  estado_id: 0,
  seccion: '',
  codigo_postal: '',
  validacion: '',
  vigencia_vencida: false,
});

async function loadTalents() {
  const params = {};
  if (filters.q) params.q = filters.q;
  if (filters.estado_id > 0) params.estado_id = filters.estado_id;
  if (filters.seccion) params.seccion = filters.seccion;
  if (filters.codigo_postal) params.codigo_postal = filters.codigo_postal;
  if (filters.validacion) params.validacion = filters.validacion;
  if (filters.vigencia_vencida) params.vigencia_vencida = 1;

  const { data: res } = await api.get('/admin/talents', { params });
  talents.value = res.data || [];
  if (res.meta) Object.assign(meta, res.meta);
}

async function openDetail(id) {
  const { data: res } = await api.get(`/admin/talents/${id}`);
  selectedTalent.value = res.data;
}

async function saveTalentDetail() {
  if (!selectedTalent.value) return;
  await api.put(`/admin/talents/${selectedTalent.value.id}`, selectedTalent.value);
  selectedTalent.value = null;
  await loadTalents();
}

async function deleteTalent(id) {
  await api.delete(`/admin/talents/${id}`);
  await loadTalents();
}

async function submitMerge() {
  await api.post('/admin/talents/merge', mergeForm);
  showMergeModal.value = false;
  await loadTalents();
}

onMounted(async () => {
  const cats = await auth.fetchCatalogs();
  estados.value = cats.estados || [];
  await loadTalents();
});
</script>


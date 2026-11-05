<template>
  <div class="space-y-5">
    <!-- Barra de filtros y alta -->
    <div class="bg-white rounded-xl p-4 border border-slate-200 shadow-xs flex flex-wrap items-center justify-between gap-3">
      <div class="flex flex-wrap items-center gap-3">
        <input
          v-model="filters.q"
          type="text"
          placeholder="Buscar por nombre, usuario, domicilio o tel..."
          class="px-3 py-2 rounded-lg border border-slate-300 text-sm w-64"
          @keyup.enter="loadActivists"
        />
        <select v-model="filters.status" class="px-3 py-2 rounded-lg border border-slate-300 text-sm" @change="loadActivists">
          <option value="">Todos los estatus</option>
          <option value="activo">Activos</option>
          <option value="suspendido">Suspendidos</option>
        </select>
        <input
          v-model.number="filters.region_trabajo"
          type="number"
          min="0"
          placeholder="Región de Trabajo"
          class="px-3 py-2 rounded-lg border border-slate-300 text-sm w-40"
          @keyup.enter="loadActivists"
        />
        <input
          v-model.number="filters.estado_id"
          type="number"
          min="0"
          max="32"
          placeholder="Clave Estado (1-32)"
          class="px-3 py-2 rounded-lg border border-slate-300 text-sm w-44"
          @keyup.enter="loadActivists"
        />
        <button
          type="button"
          class="px-3.5 py-2 rounded-lg bg-slate-800 text-white text-sm font-medium hover:bg-slate-700 cursor-pointer"
          @click="loadActivists"
        >
          Filtrar
        </button>
      </div>

      <button
        v-if="auth.canWrite"
        type="button"
        class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold shadow-xs flex items-center gap-2 cursor-pointer"
        @click="openCreateModal"
      >
        <i class="pi pi-user-plus"></i>
        <span>Nuevo Activista</span>
      </button>
    </div>

    <!-- Tabla de Activistas -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-sm">
          <thead>
            <tr class="bg-slate-50 text-slate-600 text-xs uppercase border-b border-slate-200">
              <th class="py-3 px-4">Activista / Usuario</th>
              <th class="py-3 px-4">Teléfono</th>
              <th class="py-3 px-4">Región / Zona / Domicilio</th>
              <th class="py-3 px-4">Avance Meta (50 Con · 50 Exp · 50 Ven)</th>
              <th class="py-3 px-4">Estatus</th>
              <th class="py-3 px-4 text-right">Acciones</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="act in activists" :key="act.id" class="hover:bg-slate-50">
              <td class="py-3 px-4">
                <div class="font-bold text-slate-900">{{ act.nombre_completo }}</div>
                <div class="text-xs text-slate-500">Usuario: <span class="font-mono font-semibold">{{ act.username }}</span> · {{ act.active_devices }} disp.</div>
              </td>
              <td class="py-3 px-4 font-mono font-semibold text-slate-800">
                {{ act.telefono }}
              </td>
              <td class="py-3 px-4 text-xs text-slate-700 space-y-0.5">
                <div class="flex items-center gap-1.5">
                  <span class="px-2 py-0.5 rounded bg-emerald-100 text-emerald-800 font-bold text-[11px]">
                    Región {{ act.region_trabajo ?? 1 }}
                  </span>
                  <span class="font-semibold">{{ act.estado_nombre }}</span>
                  <span class="text-slate-400">· {{ act.municipio_nombre || 'Estatal' }}</span>
                </div>
                <div class="text-slate-500 text-[11px] max-w-xs truncate" :title="act.domicilio || ''">
                  <i class="pi pi-map-marker text-[10px] mr-1"></i>{{ act.domicilio || 'Sin domicilio registrado' }}
                </div>
              </td>
              <td class="py-3 px-4">
                <div class="text-xs font-semibold mb-1">
                  Total: {{ act.registrados }} / {{ act.meta }} ({{ act.porcentaje }}%)
                </div>
                <div class="w-40 bg-slate-100 h-2 rounded-full overflow-hidden mb-1.5">
                  <div class="bg-emerald-600 h-full" :style="{ width: `${act.porcentaje}%` }"></div>
                </div>
                <div class="flex flex-wrap gap-1 text-[10px] font-semibold">
                  <span class="px-1.5 py-0.5 rounded bg-blue-50 text-blue-800 border border-blue-200">
                    Con: {{ act.conector_registrados ?? 0 }}/{{ act.meta_conector ?? 50 }}
                  </span>
                  <span class="px-1.5 py-0.5 rounded bg-purple-50 text-purple-800 border border-purple-200">
                    Exp: {{ act.experto_registrados ?? 0 }}/{{ act.meta_experto ?? 50 }}
                  </span>
                  <span class="px-1.5 py-0.5 rounded bg-amber-50 text-amber-800 border border-amber-200">
                    Ven: {{ act.vendedor_registrados ?? 0 }}/{{ act.meta_vendedor ?? 50 }}
                  </span>
                </div>
                <div class="text-[11px] text-slate-400 mt-0.5">Límite: {{ act.fecha_limite || 'N/A' }}</div>
              </td>
              <td class="py-3 px-4">
                <span
                  class="px-2.5 py-1 rounded-full text-xs font-semibold"
                  :class="act.status === 'activo' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
                >
                  {{ act.status }}
                </span>
              </td>
              <td class="py-3 px-4 text-right space-x-1">
                <button
                  v-if="auth.canWrite"
                  type="button"
                  title="Editar Activista"
                  class="px-2.5 py-1.5 rounded bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold cursor-pointer"
                  @click="openEditModal(act)"
                >
                  <i class="pi pi-pencil"></i>
                </button>
                <button
                  v-if="auth.canWrite"
                  type="button"
                  title="Restablecer contraseña temporal"
                  class="px-2.5 py-1.5 rounded bg-blue-50 hover:bg-blue-100 text-blue-700 text-xs font-semibold cursor-pointer"
                  @click="handleResetPassword(act)"
                >
                  <i class="pi pi-key"></i>
                </button>
                <button
                  v-if="auth.canWrite"
                  type="button"
                  title="Revocar sesiones y dispositivos"
                  class="px-2.5 py-1.5 rounded bg-rose-50 hover:bg-rose-100 text-rose-700 text-xs font-semibold cursor-pointer"
                  @click="handleRevokeDevices(act)"
                >
                  <i class="pi pi-ban"></i>
                </button>
              </td>
            </tr>
            <tr v-if="activists.length === 0">
              <td colspan="6" class="py-8 text-center text-slate-400">No se encontraron activistas con los filtros seleccionados.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Modal Crear / Editar Activista -->
    <div v-if="showModal" class="fixed inset-0 bg-slate-950/60 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg p-6">
        <h3 class="text-lg font-bold text-slate-900 mb-4">
          {{ form.id ? 'Editar Activista' : 'Registrar Nuevo Activista' }}
        </h3>

        <div v-if="modalError" class="mb-4 p-3 rounded-lg bg-rose-50 text-rose-700 text-xs border border-rose-200">
          {{ modalError }}
        </div>

        <form class="space-y-3.5 text-sm" @submit.prevent="saveActivist">
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
              <label class="block text-xs font-semibold text-slate-600 mb-1">Nombre(s) *</label>
              <input v-model="form.nombre" required class="w-full px-3 py-2 rounded-lg border border-slate-300" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-slate-600 mb-1">Apellido Paterno *</label>
              <input v-model="form.ap_paterno" required class="w-full px-3 py-2 rounded-lg border border-slate-300" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-slate-600 mb-1">Apellido Materno</label>
              <input v-model="form.ap_materno" class="w-full px-3 py-2 rounded-lg border border-slate-300" />
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-semibold text-slate-600 mb-1">Teléfono Celular (10 dígitos MX) *</label>
              <input
                v-model="form.telefono"
                required
                maxlength="10"
                pattern="\d{10}"
                placeholder="5512345678"
                class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono"
              />
            </div>
            <div>
              <label class="block text-xs font-semibold text-slate-600 mb-1">Correo Electrónico (Opcional)</label>
              <input v-model="form.email" type="email" class="w-full px-3 py-2 rounded-lg border border-slate-300" />
            </div>
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Domicilio del Activista *</label>
            <input
              v-model="form.domicilio"
              required
              maxlength="300"
              placeholder="Calle, número, colonia, código postal y municipio"
              class="w-full px-3 py-2 rounded-lg border border-slate-300"
            />
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
              <label class="block text-xs font-semibold text-emerald-700 mb-1">Región de Trabajo (Entero) *</label>
              <input
                v-model.number="form.region_trabajo"
                type="number"
                min="1"
                step="1"
                required
                placeholder="Ej. 1"
                class="w-full px-3 py-2 rounded-lg border border-emerald-300 bg-emerald-50/40 font-mono font-bold"
              />
            </div>
            <div>
              <label class="block text-xs font-semibold text-slate-600 mb-1">Clave Estado (1-32) *</label>
              <input
                v-model.number="form.estado_id"
                type="number"
                min="1"
                max="32"
                required
                placeholder="Ej. 9"
                class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono"
              />
            </div>
            <div>
              <label class="block text-xs font-semibold text-slate-600 mb-1">Clave Municipio (Opcional)</label>
              <input
                v-model.number="form.municipio_id"
                type="number"
                min="1"
                placeholder="Ej. 15"
                class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono"
              />
            </div>
          </div>

          <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 space-y-2">
            <div class="flex items-center justify-between text-xs font-bold text-slate-700">
              <span>Metas por Clasificación</span>
              <span>Meta Total: {{ Number(form.meta_conector || 0) + Number(form.meta_experto || 0) + Number(form.meta_vendedor || 0) }} Talentos</span>
            </div>
            <div class="grid grid-cols-3 gap-2">
              <div>
                <label class="block text-xs font-semibold text-blue-700 mb-1">Conector *</label>
                <input v-model.number="form.meta_conector" type="number" min="1" required class="w-full px-2.5 py-1.5 rounded-lg border border-slate-300 bg-white font-mono" />
              </div>
              <div>
                <label class="block text-xs font-semibold text-purple-700 mb-1">Experto *</label>
                <input v-model.number="form.meta_experto" type="number" min="1" required class="w-full px-2.5 py-1.5 rounded-lg border border-slate-300 bg-white font-mono" />
              </div>
              <div>
                <label class="block text-xs font-semibold text-amber-700 mb-1">Vendedor *</label>
                <input v-model.number="form.meta_vendedor" type="number" min="1" required class="w-full px-2.5 py-1.5 rounded-lg border border-slate-300 bg-white font-mono" />
              </div>
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-semibold text-slate-600 mb-1">Fecha Límite *</label>
              <input v-model="form.fecha_limite" type="date" required class="w-full px-3 py-2 rounded-lg border border-slate-300" />
            </div>
            <div v-if="form.id">
              <label class="block text-xs font-semibold text-slate-600 mb-1">Estatus</label>
              <select v-model="form.status" class="w-full px-3 py-2 rounded-lg border border-slate-300">
                <option value="activo">Activo</option>
                <option value="suspendido">Suspendido</option>
              </select>
            </div>
          </div>

          <div class="flex justify-end gap-2 pt-4 border-t border-slate-100">
            <button
              type="button"
              class="px-4 py-2 rounded-lg bg-slate-100 text-slate-700 text-xs font-semibold cursor-pointer"
              @click="showModal = false"
            >
              Cancelar
            </button>
            <button
              type="submit"
              class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold cursor-pointer"
            >
              {{ form.id ? 'Guardar Cambios' : 'Crear Activista' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal Credenciales Generadas -->
    <div v-if="credentialsModal" class="fixed inset-0 bg-slate-950/60 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 space-y-4">
        <div class="flex items-center gap-3 text-emerald-700">
          <i class="pi pi-check-circle text-2xl"></i>
          <h3 class="text-lg font-bold">Credenciales del Activista</h3>
        </div>
        <p class="text-xs text-slate-600">
          Entregue estas credenciales al Activista. En su primer acceso deberá cambiar su contraseña.
        </p>
        <div class="p-4 rounded-xl bg-slate-100 font-mono text-sm space-y-1">
          <div><span class="text-slate-500">Usuario:</span> <strong>{{ credentialsModal.username }}</strong></div>
          <div><span class="text-slate-500">Contraseña temporal:</span> <strong>{{ credentialsModal.temporary_password }}</strong></div>
        </div>
        <div class="flex flex-wrap gap-2">
          <button
            type="button"
            class="flex-1 py-2 px-3 rounded-lg bg-slate-800 text-white text-xs font-semibold cursor-pointer"
            @click="copyCredentials"
          >
            <i class="pi pi-copy mr-1"></i>Copiar
          </button>
          <a
            :href="credentialsModal.whatsapp_url"
            target="_blank"
            rel="noopener"
            class="flex-1 py-2 px-3 rounded-lg bg-emerald-600 text-white text-xs font-semibold text-center"
          >
            <i class="pi pi-whatsapp mr-1"></i>Enviar por WhatsApp
          </a>
        </div>
        <div class="flex justify-end pt-2">
          <button
            type="button"
            class="px-4 py-2 rounded-lg bg-slate-200 text-slate-800 text-xs font-semibold cursor-pointer"
            @click="credentialsModal = null"
          >
            Cerrar
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
const activists = ref([]);

const filters = reactive({
  q: '',
  status: '',
  region_trabajo: 0,
  estado_id: 0,
});

const showModal = ref(false);
const modalError = ref('');
const credentialsModal = ref(null);

const form = reactive({
  id: null,
  nombre: '',
  ap_paterno: '',
  ap_materno: '',
  telefono: '',
  email: '',
  domicilio: '',
  region_trabajo: 1,
  estado_id: 9,
  municipio_id: null,
  meta: 150,
  meta_conector: 50,
  meta_experto: 50,
  meta_vendedor: 50,
  fecha_limite: new Date(Date.now() + 30 * 86400000).toISOString().slice(0, 10),
  status: 'activo',
});

async function loadActivists() {
  const params = {};
  if (filters.q) params.q = filters.q;
  if (filters.status) params.status = filters.status;
  if (filters.region_trabajo > 0) params.region_trabajo = filters.region_trabajo;
  if (filters.estado_id > 0) params.estado_id = filters.estado_id;
  const { data: res } = await api.get('/admin/activists', { params });
  activists.value = res.data || [];
}

function openCreateModal() {
  Object.assign(form, {
    id: null,
    nombre: '',
    ap_paterno: '',
    ap_materno: '',
    telefono: '',
    email: '',
    domicilio: '',
    region_trabajo: 1,
    estado_id: 9,
    municipio_id: null,
    meta: 150,
    meta_conector: 50,
    meta_experto: 50,
    meta_vendedor: 50,
    fecha_limite: new Date(Date.now() + 30 * 86400000).toISOString().slice(0, 10),
    status: 'activo',
  });
  modalError.value = '';
  showModal.value = true;
}

function openEditModal(act) {
  Object.assign(form, {
    id: act.id,
    nombre: act.nombre,
    ap_paterno: act.ap_paterno,
    ap_materno: act.ap_materno || '',
    telefono: act.telefono,
    email: act.email || '',
    domicilio: act.domicilio || '',
    region_trabajo: act.region_trabajo ?? 1,
    estado_id: act.estado_id,
    municipio_id: act.municipio_id,
    meta: act.meta || 150,
    meta_conector: act.meta_conector ?? 50,
    meta_experto: act.meta_experto ?? 50,
    meta_vendedor: act.meta_vendedor ?? 50,
    fecha_limite: act.fecha_limite || '',
    status: act.status,
  });
  modalError.value = '';
  showModal.value = true;
}

async function saveActivist() {
  modalError.value = '';
  form.meta = Number(form.meta_conector || 50) + Number(form.meta_experto || 50) + Number(form.meta_vendedor || 50);
  try {
    if (form.id) {
      await api.put(`/admin/activists/${form.id}`, form);
      showModal.value = false;
      await loadActivists();
    } else {
      const { data: res } = await api.post('/admin/activists', form);
      showModal.value = false;
      credentialsModal.value = res.data;
      await loadActivists();
    }
  } catch (err) {
    modalError.value = err.response?.data?.errors?.[0] || 'Error al guardar el activista.';
  }
}

async function handleResetPassword(act) {
  const { data: res } = await api.post(`/admin/activists/${act.id}/reset-password`);
  credentialsModal.value = res.data;
}

async function handleRevokeDevices(act) {
  await api.post(`/admin/activists/${act.id}/revoke-devices`);
  await loadActivists();
}

function copyCredentials() {
  if (!credentialsModal.value) return;
  const text = `Usuario: ${credentialsModal.value.username}\nContraseña temporal: ${credentialsModal.value.temporary_password}`;
  navigator.clipboard?.writeText(text);
}

onMounted(async () => {
  await loadActivists();
});
</script>

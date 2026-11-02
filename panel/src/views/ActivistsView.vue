<template>
  <div class="space-y-5">
    <!-- Barra de filtros y alta -->
    <div class="bg-white rounded-xl p-4 border border-slate-200 shadow-xs flex flex-wrap items-center justify-between gap-3">
      <div class="flex flex-wrap items-center gap-3">
        <input
          v-model="filters.q"
          type="text"
          placeholder="Buscar por nombre, usuario o teléfono..."
          class="px-3 py-2 rounded-lg border border-slate-300 text-sm w-64"
          @keyup.enter="loadActivists"
        />
        <select v-model="filters.status" class="px-3 py-2 rounded-lg border border-slate-300 text-sm" @change="loadActivists">
          <option value="">Todos los estatus</option>
          <option value="activo">Activos (Verificados)</option>
          <option value="pendiente_verificacion">Pendientes de Verificación OTP</option>
          <option value="suspendido">Suspendidos</option>
        </select>
        <select v-model="filters.estado_id" class="px-3 py-2 rounded-lg border border-slate-300 text-sm" @change="loadActivists">
          <option value="0">Todas las entidades (32)</option>
          <option v-for="est in estados" :key="est.id" :value="est.id">{{ est.nombre }}</option>
        </select>
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
              <th class="py-3 px-4">Teléfono (OTP)</th>
              <th class="py-3 px-4">Zona Operativa</th>
              <th class="py-3 px-4">Avance Meta</th>
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
              <td class="py-3 px-4">
                <div class="font-mono font-semibold text-slate-800">{{ act.telefono }}</div>
                <div v-if="act.telefono_verificado_at" class="text-[11px] text-emerald-700 font-medium flex items-center gap-1">
                  <i class="pi pi-check-circle"></i> Verificado ({{ act.telefono_verificado_at.slice(0, 10) }})
                </div>
                <div v-else class="text-[11px] text-amber-700 font-semibold flex items-center gap-1">
                  <i class="pi pi-Clock"></i> Sin verificar OTP (bloquea sync)
                </div>
              </td>
              <td class="py-3 px-4 text-xs text-slate-700">
                <div class="font-semibold">{{ act.estado_nombre }}</div>
                <div class="text-slate-400">{{ act.municipio_nombre || 'Estatal' }}</div>
              </td>
              <td class="py-3 px-4">
                <div class="text-xs font-semibold mb-1">{{ act.registrados }} / {{ act.meta }} ({{ act.porcentaje }}%)</div>
                <div class="w-32 bg-slate-100 h-2 rounded-full overflow-hidden">
                  <div class="bg-emerald-600 h-full" :style="{ width: `${act.porcentaje}%` }"></div>
                </div>
                <div class="text-[11px] text-slate-400 mt-0.5">Límite: {{ act.fecha_limite || 'N/A' }}</div>
              </td>
              <td class="py-3 px-4">
                <span
                  class="px-2.5 py-1 rounded-full text-xs font-semibold"
                  :class="{
                    'bg-emerald-100 text-emerald-800': act.status === 'activo',
                    'bg-amber-100 text-amber-800': act.status === 'pendiente_verificacion',
                    'bg-rose-100 text-rose-800': act.status === 'suspendido',
                  }"
                >
                  {{ act.status }}
                </span>
              </td>
              <td class="py-3 px-4 text-right space-x-1">
                <button
                  v-if="auth.canWrite && !act.telefono_verificado_at"
                  type="button"
                  title="Verificar teléfono con código OTP (Twilio Verify)"
                  class="px-2.5 py-1.5 rounded bg-amber-500 hover:bg-amber-600 text-white text-xs font-semibold cursor-pointer"
                  @click="openOtpModal(act)"
                >
                  <i class="pi pi-mobile"></i> Verificar OTP
                </button>
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
              <span class="text-[11px] text-slate-400">Se enviará código OTP vía Twilio Verify</span>
            </div>
            <div>
              <label class="block text-xs font-semibold text-slate-600 mb-1">Correo Electrónico (Opcional)</label>
              <input v-model="form.email" type="email" class="w-full px-3 py-2 rounded-lg border border-slate-300" />
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-semibold text-slate-600 mb-1">Entidad de Operación *</label>
              <select v-model="form.estado_id" required class="w-full px-3 py-2 rounded-lg border border-slate-300">
                <option v-for="est in estados" :key="est.id" :value="est.id">{{ est.nombre }}</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-semibold text-slate-600 mb-1">Municipio</label>
              <select v-model="form.municipio_id" class="w-full px-3 py-2 rounded-lg border border-slate-300">
                <option :value="null">-- Estatal / Todos --</option>
                <option v-for="mun in filteredMunicipios" :key="mun.id" :value="mun.id">{{ mun.nombre }}</option>
              </select>
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
              <label class="block text-xs font-semibold text-slate-600 mb-1">Meta de Talentos (n) *</label>
              <input v-model.number="form.meta" type="number" min="1" required class="w-full px-3 py-2 rounded-lg border border-slate-300" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-slate-600 mb-1">Fecha Límite *</label>
              <input v-model="form.fecha_limite" type="date" required class="w-full px-3 py-2 rounded-lg border border-slate-300" />
            </div>
            <div v-if="form.id">
              <label class="block text-xs font-semibold text-slate-600 mb-1">Estatus</label>
              <select v-model="form.status" class="w-full px-3 py-2 rounded-lg border border-slate-300">
                <option value="activo">Activo</option>
                <option value="pendiente_verificacion">Pendiente Verificación</option>
                <option value="suspendido">Suspendido</option>
              </select>
            </div>
          </div>

          <label v-if="!form.id" class="flex items-center gap-2 text-xs text-slate-700 pt-1">
            <input v-model="form.send_sms" type="checkbox" class="rounded border-slate-300" />
            <span>Enviar usuario y contraseña temporal por SMS (Twilio) al crear</span>
          </label>

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
              {{ form.id ? 'Guardar Cambios' : 'Crear y Enviar OTP' }}
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
          <div v-if="credentialsModal.otp?.demo_code" class="text-amber-700 pt-1">
            <span class="text-slate-500">Código OTP (Demo/Local):</span> <strong>{{ credentialsModal.otp.demo_code }}</strong>
          </div>
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

    <!-- Modal Verificación OTP del Teléfono del Activista (RF-P1.1) -->
    <div v-if="otpModal.show" class="fixed inset-0 bg-slate-950/60 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 space-y-4">
        <h3 class="text-lg font-bold text-slate-900">Verificación OTP del Activista (Twilio Verify)</h3>
        <p class="text-xs text-slate-600">
          Capture el código de 6 dígitos enviado por SMS al celular <strong>{{ otpModal.telefono }}</strong> de
          <strong>{{ otpModal.nombre }}</strong>. Hasta verificarse, el Activista no puede sincronizar Talentos.
        </p>

        <div v-if="otpModal.demoCode" class="p-2.5 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-xs font-mono">
          Modo desarrollo — Código OTP generado: <strong>{{ otpModal.demoCode }}</strong>
        </div>

        <div v-if="otpModal.error" class="p-2.5 rounded-lg bg-rose-50 border border-rose-200 text-rose-700 text-xs">
          {{ otpModal.error }}
        </div>

        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1">Código de 6 dígitos</label>
          <input
            v-model="otpModal.code"
            type="text"
            maxlength="6"
            placeholder="123456"
            class="w-full px-4 py-2.5 rounded-lg border border-slate-300 text-center text-xl font-mono tracking-widest"
          />
        </div>

        <div class="flex items-center justify-between pt-2">
          <button
            type="button"
            class="text-xs text-emerald-700 hover:underline font-semibold cursor-pointer"
            @click="resendOtp"
          >
            Reenviar código SMS (cada 60 s)
          </button>
          <div class="flex gap-2">
            <button
              type="button"
              class="px-3.5 py-2 rounded-lg bg-slate-100 text-slate-700 text-xs font-semibold cursor-pointer"
              @click="otpModal.show = false"
            >
              Cerrar
            </button>
            <button
              type="button"
              class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold cursor-pointer"
              @click="verifyOtpCode"
            >
              Verificar Celular
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import api from '@/services/api';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const activists = ref([]);
const estados = ref([]);
const municipios = ref([]);

const filters = reactive({
  q: '',
  status: '',
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
  estado_id: 9,
  municipio_id: null,
  meta: 50,
  fecha_limite: new Date(Date.now() + 30 * 86400000).toISOString().slice(0, 10),
  status: 'pendiente_verificacion',
  send_sms: true,
});

const otpModal = reactive({
  show: false,
  activistId: null,
  nombre: '',
  telefono: '',
  code: '',
  demoCode: '',
  error: '',
});

const filteredMunicipios = computed(() =>
  municipios.value.filter((m) => Number(m.estado_id) === Number(form.estado_id))
);

async function loadActivists() {
  const params = {};
  if (filters.q) params.q = filters.q;
  if (filters.status) params.status = filters.status;
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
    estado_id: 9,
    municipio_id: null,
    meta: 50,
    fecha_limite: new Date(Date.now() + 30 * 86400000).toISOString().slice(0, 10),
    status: 'pendiente_verificacion',
    send_sms: true,
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
    estado_id: act.estado_id,
    municipio_id: act.municipio_id,
    meta: act.meta,
    fecha_limite: act.fecha_limite || '',
    status: act.status,
    send_sms: false,
  });
  modalError.value = '';
  showModal.value = true;
}

async function saveActivist() {
  modalError.value = '';
  try {
    if (form.id) {
      const { data: res } = await api.put(`/admin/activists/${form.id}`, form);
      showModal.value = false;
      await loadActivists();
      if (res.data?.phone_changed) {
        otpModal.activistId = form.id;
        otpModal.nombre = `${form.nombre} ${form.ap_paterno}`;
        otpModal.telefono = form.telefono;
        otpModal.code = '';
        otpModal.demoCode = res.data?.otp?.demo_code || '';
        otpModal.error = '';
        otpModal.show = true;
      }
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

async function openOtpModal(act) {
  otpModal.activistId = act.id;
  otpModal.nombre = act.nombre_completo;
  otpModal.telefono = act.telefono;
  otpModal.code = '';
  otpModal.demoCode = '';
  otpModal.error = '';
  otpModal.show = true;
  await resendOtp();
}

async function resendOtp() {
  otpModal.error = '';
  try {
    const { data: res } = await api.post(`/admin/activists/${otpModal.activistId}/verify-phone`);
    otpModal.demoCode = res.data?.demo_code || '';
  } catch (err) {
    otpModal.error = err.response?.data?.errors?.[0] || 'No se pudo enviar el código OTP.';
  }
}

async function verifyOtpCode() {
  otpModal.error = '';
  try {
    await api.post(`/admin/activists/${otpModal.activistId}/verify-phone/check`, {
      code: otpModal.code,
    });
    otpModal.show = false;
    await loadActivists();
  } catch (err) {
    otpModal.error = err.response?.data?.errors?.[0] || 'Código OTP incorrecto.';
  }
}

async function handleResetPassword(act) {
  const { data: res } = await api.post(`/admin/activists/${act.id}/reset-password`, { send_sms: true });
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
  const cats = await auth.fetchCatalogs();
  estados.value = cats.estados || [];
  municipios.value = cats.municipios || [];
  await loadActivists();
});
</script>

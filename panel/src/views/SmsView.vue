<template>
  <div class="space-y-6">
    <!-- Constructor de Campañas SMS Masivas Segmentadas (RF-P11) -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-xs space-y-4">
        <div>
          <h3 class="text-sm font-bold text-slate-800">Nueva Campaña SMS (Twilio Programmable Messaging)</h3>
          <p class="text-xs text-slate-500 mt-0.5">
            Se excluyen automáticamente los teléfonos en lista de baja (STOP/BAJA). Mensajes normalizados a GSM-7 (&le; 160 caracteres).
          </p>
        </div>

        <div v-if="msgAlert" class="p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs">
          {{ msgAlert }}
        </div>

        <form class="space-y-3 text-xs" @submit.prevent="sendCampaign">
          <div>
            <label class="font-semibold text-slate-600 block mb-1">Nombre de la Campaña *</label>
            <input
              v-model="form.nombre"
              required
              placeholder="Ej. Convocatoria Asamblea Sección 4812"
              class="w-full px-3 py-2 rounded-lg border border-slate-300"
            />
          </div>

          <div class="grid grid-cols-2 gap-2">
            <div>
              <label class="font-semibold text-slate-600 block mb-1">Filtrar Entidad</label>
              <select v-model="form.filtros.estado_id" class="w-full px-3 py-2 rounded-lg border border-slate-300">
                <option value="">Todas las entidades</option>
                <option v-for="e in estados" :key="e.id" :value="e.id">{{ e.nombre }}</option>
              </select>
            </div>
            <div>
              <label class="font-semibold text-slate-600 block mb-1">Filtrar Sección</label>
              <input
                v-model="form.filtros.seccion"
                type="number"
                placeholder="Todas"
                class="w-full px-3 py-2 rounded-lg border border-slate-300"
              />
            </div>
          </div>

          <div>
            <div class="flex items-center justify-between mb-1">
              <label class="font-semibold text-slate-600">Plantilla del Mensaje (Variables: {nombre}, {seccion}) *</label>
              <span :class="form.plantilla.length > 160 ? 'text-rose-600 font-bold' : 'text-slate-400'">
                {{ form.plantilla.length }} / 160 GSM-7
              </span>
            </div>
            <textarea
              v-model="form.plantilla"
              rows="3"
              maxlength="160"
              required
              class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono"
            ></textarea>
          </div>

          <button
            v-if="auth.canWrite"
            type="submit"
            :disabled="sending"
            class="w-full py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-semibold cursor-pointer"
          >
            <i class="pi pi-send mr-1"></i>
            {{ sending ? 'Procesando lote SMS...' : 'Enviar Campaña Segmentada' }}
          </button>
        </form>

        <div class="pt-3 border-t border-slate-100 text-xs text-slate-500 flex items-center justify-between">
          <span>Enviados hoy: <strong>{{ stats.sent_today }}</strong> / {{ stats.daily_limit }}</span>
          <span>Bajas (STOP/BAJA): <strong>{{ stats.optouts_total }}</strong></span>
        </div>
      </div>

      <!-- Historial de Campañas y Lista de Exclusión (Opt-Outs) -->
      <div class="lg:col-span-2 space-y-6">
        <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-xs">
          <h3 class="text-sm font-bold text-slate-800 mb-3">Historial de Campañas Enviadas</h3>
          <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
              <thead>
                <tr class="bg-slate-50 text-slate-600 uppercase border-b border-slate-200">
                  <th class="py-2.5 px-3">ID / Campaña</th>
                  <th class="py-2.5 px-3">Plantilla</th>
                  <th class="py-2.5 px-3">Destinatarios</th>
                  <th class="py-2.5 px-3">Estatus</th>
                  <th class="py-2.5 px-3">Fecha</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-for="c in campaigns" :key="c.id" class="hover:bg-slate-50">
                  <td class="py-2.5 px-3 font-bold text-slate-800">#{{ c.id }} · {{ c.nombre }}</td>
                  <td class="py-2.5 px-3 font-mono text-slate-600 max-w-xs truncate">{{ c.plantilla }}</td>
                  <td class="py-2.5 px-3 font-semibold">{{ c.enviados }} / {{ c.total }}</td>
                  <td class="py-2.5 px-3">
                    <span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 font-semibold">
                      {{ c.status }}
                    </span>
                  </td>
                  <td class="py-2.5 px-3 text-slate-400">{{ c.created_at }}</td>
                </tr>
                <tr v-if="campaigns.length === 0">
                  <td colspan="5" class="py-6 text-center text-slate-400">No se han registrado campañas masivas aún.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-xs">
          <h3 class="text-sm font-bold text-slate-800 mb-2">
            Lista de Exclusión Automática STOP / BAJA (`sms_optouts`)
          </h3>
          <div class="flex flex-wrap gap-2">
            <span
              v-for="opt in optouts"
              :key="opt.telefono"
              class="px-2.5 py-1 rounded-lg bg-rose-50 border border-rose-200 text-rose-800 text-xs font-mono"
            >
              {{ opt.telefono }} ({{ opt.motivo }} · {{ opt.created_at?.slice(0, 10) }})
            </span>
            <span v-if="optouts.length === 0" class="text-xs text-slate-400">
              Ningún número ha solicitado baja por SMS hasta el momento.
            </span>
          </div>
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
const estados = ref([]);
const campaigns = ref([]);
const optouts = ref([]);
const sending = ref(false);
const msgAlert = ref('');

const stats = reactive({
  optouts_total: 0,
  sent_today: 0,
  daily_limit: 5000,
});

const form = reactive({
  nombre: '',
  plantilla: 'Hola {nombre}, te invitamos a la reunion vecinal de la Seccion {seccion}. Responde BAJA para cancelar.',
  filtros: {
    estado_id: '',
    seccion: '',
  },
});

async function loadData() {
  const [campRes, optRes] = await Promise.all([
    api.get('/admin/sms/campaigns'),
    api.get('/admin/sms/optouts'),
  ]);
  campaigns.value = campRes.data.data || [];
  if (campRes.data.meta) Object.assign(stats, campRes.data.meta);
  optouts.value = optRes.data.data || [];
}

async function sendCampaign() {
  sending.value = true;
  msgAlert.value = '';
  try {
    const { data: res } = await api.post('/admin/sms/campaigns', form);
    msgAlert.value = `Campaña "${res.data.nombre}" creada: ${res.data.enviados} de ${res.data.total} SMS enviados.`;
    form.nombre = '';
    await loadData();
  } finally {
    sending.value = false;
  }
}

onMounted(async () => {
  const cats = await auth.fetchCatalogs();
  estados.value = cats.estados || [];
  await loadData();
});
</script>

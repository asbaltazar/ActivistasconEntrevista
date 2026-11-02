<template>
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Bitácora de Auditoría (RF-P9) -->
    <div class="lg:col-span-2 bg-white rounded-xl border border-slate-200 p-5 shadow-xs space-y-4">
      <div class="flex items-center justify-between border-b border-slate-100 pb-3">
        <div>
          <h3 class="text-sm font-bold text-slate-800">Bitácora de Auditoría (`audit_log`)</h3>
          <p class="text-xs text-slate-500">Registro inmutable de inicios de sesión, CRUD, exportaciones y vistas de imágenes INE</p>
        </div>
        <input
          v-model="actionFilter"
          type="text"
          placeholder="Filtrar acción (ej. file.view_ine)"
          class="px-3 py-1.5 rounded-lg border border-slate-300 text-xs"
          @keyup.enter="loadAudit"
        />
      </div>

      <div class="overflow-x-auto max-h-[460px]">
        <table class="w-full text-left border-collapse text-xs">
          <thead>
            <tr class="bg-slate-50 text-slate-600 uppercase border-b border-slate-200">
              <th class="py-2.5 px-3">Fecha / Hora</th>
              <th class="py-2.5 px-3">Usuario</th>
              <th class="py-2.5 px-3">Acción</th>
              <th class="py-2.5 px-3">Entidad</th>
              <th class="py-2.5 px-3">IP / Detalle</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="log in auditLogs" :key="log.id" class="hover:bg-slate-50">
              <td class="py-2 px-3 font-mono text-slate-500">{{ log.created_at }}</td>
              <td class="py-2 px-3 font-semibold text-slate-800">{{ log.username || 'Sistema' }}</td>
              <td class="py-2 px-3">
                <span class="px-2 py-0.5 rounded bg-slate-100 font-mono font-semibold text-slate-800">
                  {{ log.action }}
                </span>
              </td>
              <td class="py-2 px-3 text-slate-600">{{ log.entity }} #{{ log.entity_id || '-' }}</td>
              <td class="py-2 px-3 font-mono text-[11px] text-slate-500 max-w-xs truncate">
                {{ log.ip }} · {{ log.payload || '' }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Configuración Legal LFPDPPP y Parámetros (RF-P10) -->
    <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-xs space-y-4">
      <div>
        <h3 class="text-sm font-bold text-slate-800">Configuración y Aviso de Privacidad (LFPDPPP)</h3>
        <p class="text-xs text-slate-500 mt-0.5">
          Las llaves de API (Google Cloud, Twilio) se gestionan exclusivamente en `.env` fuera del repositorio.
        </p>
      </div>

      <div v-if="savedMsg" class="p-2.5 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs">
        {{ savedMsg }}
      </div>

      <form class="space-y-3 text-xs" @submit.prevent="saveSettings">
        <div class="grid grid-cols-2 gap-2">
          <div>
            <label class="font-semibold text-slate-600 block mb-1">Versión del Aviso</label>
            <input v-model="settings.aviso_privacidad_version" class="w-full px-3 py-2 rounded-lg border border-slate-300" />
          </div>
          <div>
            <label class="font-semibold text-slate-600 block mb-1">Meta Global Nacional</label>
            <input v-model="settings.meta_global_default" type="number" class="w-full px-3 py-2 rounded-lg border border-slate-300" />
          </div>
        </div>

        <div>
          <label class="font-semibold text-slate-600 block mb-1">Texto del Aviso de Privacidad (Mostrado en App)</label>
          <textarea
            v-model="settings.aviso_privacidad_texto"
            rows="5"
            class="w-full px-3 py-2 rounded-lg border border-slate-300"
          ></textarea>
        </div>

        <div>
          <label class="font-semibold text-slate-600 block mb-1">Plantilla SMS de Bienvenida Automático</label>
          <textarea
            v-model="settings.sms_bienvenida_plantilla"
            rows="3"
            class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono"
          ></textarea>
        </div>

        <button
          v-if="auth.canWrite"
          type="submit"
          class="w-full py-2.5 rounded-lg bg-slate-900 hover:bg-slate-800 text-white font-semibold cursor-pointer"
        >
          Guardar Configuración
        </button>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import api from '@/services/api';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const auditLogs = ref([]);
const actionFilter = ref('');
const savedMsg = ref('');

const settings = reactive({
  aviso_privacidad_version: '1.0',
  meta_global_default: '2500',
  aviso_privacidad_texto: '',
  sms_bienvenida_plantilla: '',
});

async function loadAudit() {
  const params = {};
  if (actionFilter.value) params.action = actionFilter.value;
  const { data: res } = await api.get('/admin/audit', { params });
  auditLogs.value = res.data || [];
}

async function loadSettings() {
  const { data: res } = await api.get('/admin/settings');
  if (res.data) Object.assign(settings, res.data);
}

async function saveSettings() {
  savedMsg.value = '';
  await api.put('/admin/settings', settings);
  savedMsg.value = 'Parámetros actualizados correctamente.';
  await loadAudit();
}

onMounted(async () => {
  await Promise.all([loadAudit(), loadSettings()]);
});
</script>

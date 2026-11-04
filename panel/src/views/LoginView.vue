<template>
  <div class="min-h-screen flex items-center justify-center bg-slate-900 px-4">
    <div class="w-full max-w-md bg-white rounded-2xl shadow-2xl p-8">
      <div class="text-center mb-6">
        <div class="w-14 h-14 rounded-2xl bg-emerald-600 text-white font-bold text-2xl flex items-center justify-center mx-auto mb-3 shadow-lg">
          RC
        </div>
        <h1 class="text-2xl font-bold text-slate-900">Red Municipal de Talentos</h1>
        <p class="text-sm text-slate-500 mt-1">Panel de Administración y Control Territorial</p>
      </div>

      <div v-if="errorMsg" class="mb-4 p-3 rounded-lg bg-rose-50 border border-rose-200 text-rose-700 text-sm">
        {{ errorMsg }}
      </div>

      <form class="space-y-4" @submit.prevent="submitLogin">
        <div>
          <label class="block text-xs font-semibold uppercase text-slate-600 mb-1">Usuario</label>
          <input
            v-model="username"
            type="text"
            required
            autocomplete="username"
            placeholder="superadmin"
            class="w-full px-3.5 py-2.5 rounded-lg border border-slate-300 focus:border-emerald-600 focus:outline-none text-sm"
          />
        </div>

        <div>
          <label class="block text-xs font-semibold uppercase text-slate-600 mb-1">Contraseña</label>
          <input
            v-model="password"
            type="password"
            required
            autocomplete="current-password"
            placeholder="••••••••"
            class="w-full px-3.5 py-2.5 rounded-lg border border-slate-300 focus:border-emerald-600 focus:outline-none text-sm"
          />
        </div>

        <button
          type="submit"
          :disabled="loading"
          class="w-full py-3 rounded-lg bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white font-semibold text-sm shadow transition cursor-pointer"
        >
          {{ loading ? 'Verificando acceso...' : 'Iniciar sesión en el Panel' }}
        </button>
      </form>

      <div class="mt-6 pt-4 border-t border-slate-100 text-xs text-slate-400 text-center">
        Cumplimiento LFPDPPP · Acceso auditado en bitácora
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const router = useRouter();

const username = ref('');
const password = ref('');
const loading = ref(false);
const errorMsg = ref('');

async function submitLogin() {
  loading.value = true;
  errorMsg.value = '';
  try {
    await auth.login(username.value, password.value);
    router.push('/');
  } catch (err) {
    errorMsg.value = err.response?.data?.errors?.[0] || err.message || 'Error al iniciar sesión.';
  } finally {
    loading.value = false;
  }
}
</script>


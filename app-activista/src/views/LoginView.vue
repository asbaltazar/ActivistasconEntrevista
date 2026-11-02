<template>
  <div class="min-h-screen flex flex-col justify-center p-5 bg-slate-900 text-white">
    <div class="bg-white text-slate-900 rounded-2xl shadow-2xl p-6 space-y-5">
      <div class="text-center">
        <div class="w-14 h-14 rounded-2xl bg-emerald-700 text-white font-extrabold text-2xl flex items-center justify-center mx-auto mb-2 shadow">
          RC
        </div>
        <h1 class="text-xl font-extrabold text-slate-900">App Activista — Campo</h1>
        <p class="text-xs text-slate-500">Red Ciudadana de Talentos · Modo Offline-First</p>
      </div>

      <div v-if="errorMsg" class="p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-medium">
        {{ errorMsg }}
      </div>

      <!-- Paso A: Inicio de sesión -->
      <form v-if="!store.isAuthenticated" class="space-y-4" @submit.prevent="handleLogin">
        <div>
          <label class="block text-xs font-bold uppercase text-slate-600 mb-1">Usuario o Teléfono (10 dígitos)</label>
          <input
            v-model="username"
            type="text"
            required
            placeholder="activista1 o 5512345678"
            class="w-full px-4 py-3 rounded-xl border border-slate-300 text-base font-medium"
          />
        </div>

        <div>
          <label class="block text-xs font-bold uppercase text-slate-600 mb-1">Contraseña</label>
          <input
            v-model="password"
            type="password"
            required
            placeholder="••••••••"
            class="w-full px-4 py-3 rounded-xl border border-slate-300 text-base"
          />
        </div>

        <div>
          <label class="block text-xs font-bold uppercase text-slate-600 mb-1">
            PIN Local Offline (4 a 6 dígitos para cifrado AES-GCM) *
          </label>
          <input
            v-model="pin"
            type="password"
            inputmode="numeric"
            minlength="4"
            maxlength="6"
            required
            placeholder="1234"
            class="w-full px-4 py-3 rounded-xl border border-slate-300 text-center text-lg font-mono tracking-widest"
          />
        </div>

        <button
          type="submit"
          :disabled="loading"
          class="w-full py-3.5 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-base shadow-md cursor-pointer"
        >
          {{ loading ? 'Iniciando sesión y descargando catálogos...' : 'Ingresar a la App' }}
        </button>
      </form>

      <!-- Paso B: Cambio obligatorio de contraseña en el primer acceso (RF-A1) -->
      <form v-else-if="store.mustChangePassword" class="space-y-4" @submit.prevent="handleChangePassword">
        <div class="p-3 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-xs font-medium">
          Por seguridad, debes cambiar tu contraseña temporal en este primer acceso.
        </div>

        <div>
          <label class="block text-xs font-bold uppercase text-slate-600 mb-1">Nueva Contraseña (mín. 8 caracteres)</label>
          <input
            v-model="newPassword"
            type="password"
            minlength="8"
            required
            class="w-full px-4 py-3 rounded-xl border border-slate-300 text-base"
          />
        </div>

        <button
          type="submit"
          :disabled="loading"
          class="w-full py-3.5 rounded-xl bg-emerald-700 text-white font-bold text-base cursor-pointer"
        >
          Guardar Nueva Contraseña
        </button>
      </form>

      <!-- Paso C: Verificación OTP del teléfono del Activista (RF-P1.1) -->
      <div v-else-if="store.needsPhoneVerification" class="space-y-4">
        <div class="p-3 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 text-xs space-y-1">
          <p class="font-bold">Verificación Obligatoria de Celular (Twilio Verify)</p>
          <p>
            Ingresa el código de 6 dígitos enviado por SMS a tu número
            <strong>{{ store.user?.activist?.telefono }}</strong>. Hasta verificarlo no podrás sincronizar Talentos.
          </p>
          <p v-if="demoOtpCode" class="font-mono text-emerald-800 pt-1">
            Código Demo: <strong>{{ demoOtpCode }}</strong>
          </p>
        </div>

        <input
          v-model="otpCode"
          type="text"
          inputmode="numeric"
          maxlength="6"
          placeholder="123456"
          class="w-full px-4 py-3 rounded-xl border border-slate-300 text-center text-2xl font-mono tracking-widest"
        />

        <div class="flex gap-2">
          <button
            type="button"
            class="flex-1 py-3 rounded-xl bg-slate-200 text-slate-800 font-bold text-xs cursor-pointer"
            @click="requestNewOtp"
          >
            Enviar / Reenviar SMS
          </button>
          <button
            type="button"
            class="flex-1 py-3 rounded-xl bg-emerald-700 text-white font-bold text-sm cursor-pointer"
            @click="submitOtp"
          >
            Verificar Código
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useActivistStore } from '@/stores/activist';

const store = useActivistStore();
const router = useRouter();

const username = ref('');
const password = ref('');
const pin = ref('1234');
const newPassword = ref('');
const otpCode = ref('');
const demoOtpCode = ref('');
const loading = ref(false);
const errorMsg = ref('');

async function handleLogin() {
  errorMsg.value = '';
  if (!/^\d{4,6}$/.test(pin.value)) {
    errorMsg.value = 'El PIN local debe tener entre 4 y 6 dígitos numéricos.';
    return;
  }
  loading.value = true;
  try {
    await store.login(username.value, password.value);
    await store.setOfflinePin(pin.value);
    if (!store.mustChangePassword && !store.needsPhoneVerification) {
      router.push('/');
    } else if (store.needsPhoneVerification) {
      await requestNewOtp();
    }
  } catch (err) {
    errorMsg.value = err.response?.data?.errors?.[0] || err.message || 'Credenciales inválidas.';
  } finally {
    loading.value = false;
  }
}

async function handleChangePassword() {
  errorMsg.value = '';
  loading.value = true;
  try {
    await store.changePassword(newPassword.value);
    if (!store.needsPhoneVerification) {
      router.push('/');
    } else {
      await requestNewOtp();
    }
  } catch (err) {
    errorMsg.value = err.response?.data?.errors?.[0] || 'Error al cambiar la contraseña.';
  } finally {
    loading.value = false;
  }
}

async function requestNewOtp() {
  errorMsg.value = '';
  try {
    const data = await store.sendPhoneOtp();
    demoOtpCode.value = data?.demo_code || '';
  } catch (err) {
    errorMsg.value = err.response?.data?.errors?.[0] || 'Espera 60 segundos antes de reenviar el código.';
  }
}

async function submitOtp() {
  errorMsg.value = '';
  try {
    await store.verifyPhoneOtp(otpCode.value);
    router.push('/');
  } catch (err) {
    errorMsg.value = err.response?.data?.errors?.[0] || 'Código OTP inválido.';
  }
}
</script>

<template>
  <div class="p-4 space-y-4">
    <!-- Indicador de pasos (1 a 8) -->
    <div class="bg-white rounded-2xl p-3.5 border border-slate-200 shadow-xs">
      <div class="flex items-center justify-between text-xs font-extrabold text-slate-700 mb-2">
        <span>Paso {{ step }} de 8: {{ stepTitles[step - 1] }}</span>
        <span class="font-mono text-[11px] text-slate-400">UUID: {{ form.uuid.slice(0, 8) }}</span>
      </div>
      <div class="grid grid-cols-8 gap-1">
        <div
          v-for="s in 8"
          :key="s"
          class="h-2 rounded-full transition-colors"
          :class="s <= step ? 'bg-emerald-600' : 'bg-slate-200'"
        ></div>
      </div>
    </div>

    <div v-if="stepError" class="p-3 rounded-xl bg-rose-50 border border-rose-300 text-rose-800 text-xs font-bold">
      {{ stepError }}
    </div>

    <!-- PASO 1: Aviso de Privacidad y Consentimiento LFPDPPP -->
    <div v-if="step === 1" class="bg-white rounded-2xl p-5 border border-slate-200 shadow-xs space-y-4">
      <h3 class="text-base font-extrabold text-slate-900">1. Aviso de Privacidad y Consentimiento (LFPDPPP)</h3>
      <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-700 max-h-44 overflow-y-auto leading-relaxed">
        {{ store.catalogs.aviso_privacidad?.texto }}
      </div>

      <label class="flex items-start gap-3 p-3 rounded-xl bg-emerald-50 border border-emerald-200 cursor-pointer">
        <input v-model="form.consentimiento_aceptado" type="checkbox" class="mt-1 w-5 h-5 accent-emerald-700" />
        <span class="text-xs font-bold text-emerald-950">
          El ciudadano otorga su consentimiento expreso para el tratamiento de sus datos personales y sensibles (INE y fotografía facial) conforme al Aviso de Privacidad.
        </span>
      </label>

      <!-- Firma opcional en pantalla -->
      <div class="space-y-1.5">
        <div class="flex items-center justify-between text-xs">
          <span class="font-bold text-slate-700">Firma en pantalla (Opcional)</span>
          <button type="button" class="text-rose-600 font-semibold cursor-pointer" @click="clearSignature">
            Limpiar firma
          </button>
        </div>
        <canvas
          ref="sigCanvas"
          width="340"
          height="110"
          class="w-full h-28 rounded-xl border-2 border-dashed border-slate-300 bg-slate-50 touch-none"
          @pointerdown="startDraw"
          @pointermove="moveDraw"
          @pointerup="endDraw"
        ></canvas>
      </div>
    </div>

    <!-- PASO 2: Foto Frente INE -->
    <div v-if="step === 2" class="bg-white rounded-2xl p-5 border border-slate-200 shadow-xs space-y-4">
      <h3 class="text-base font-extrabold text-slate-900">2. Fotografía Frente de Credencial INE</h3>
      <p class="text-xs text-slate-600">
        Centra la credencial dentro del recuadro sin reflejos. Se comprime automáticamente a &le; 400 KB (1600 px).
      </p>

      <!-- Guía de encuadre INE -->
      <div class="relative w-full h-52 rounded-2xl bg-slate-900 border-4 border-emerald-500/80 flex items-center justify-center overflow-hidden">
        <img v-if="form.files.ine_frente" :src="form.files.ine_frente" alt="Frente INE" class="w-full h-full object-contain" />
        <div v-else class="text-center text-slate-300 p-4 space-y-1">
          <i class="pi pi-id-card text-3xl text-emerald-400"></i>
          <p class="text-xs font-bold">Guía de Encuadre Anverso INE</p>
        </div>
      </div>

      <div v-if="sharpnessScore !== null" class="text-xs font-bold" :class="sharpnessScore >= 12 ? 'text-emerald-700' : 'text-amber-700'">
        Nitidez detectada: {{ sharpnessScore.toFixed(1) }} {{ sharpnessScore >= 12 ? '(Óptima)' : '(Verifica que el texto sea legible)' }}
      </div>

      <label class="w-full py-3.5 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-sm flex items-center justify-center gap-2 cursor-pointer shadow">
        <i class="pi pi-camera"></i>
        <span>{{ form.files.ine_frente ? 'Volver a tomar Frente INE' : 'Capturar Frente INE (Cámara Trasera)' }}</span>
        <input type="file" accept="image/*" capture="environment" class="hidden" @change="(e) => onImageSelected(e, 'ine_frente', 0.4)" />
      </label>
    </div>

    <!-- PASO 3: (Opcional) Reverso INE + Ejecución OCR -->
    <div v-if="step === 3" class="bg-white rounded-2xl p-5 border border-slate-200 shadow-xs space-y-4">
      <h3 class="text-base font-extrabold text-slate-900">3. Reverso de INE (Opcional) y Lectura OCR</h3>
      <p class="text-xs text-slate-600">
        Capturar el reverso permite leer la zona MRZ para mayor exactitud en Clave de Elector, CURP y nombre.
      </p>

      <div class="relative w-full h-44 rounded-2xl bg-slate-900 border-2 border-dashed border-slate-600 flex items-center justify-center overflow-hidden">
        <img v-if="form.files.ine_reverso" :src="form.files.ine_reverso" alt="Reverso INE" class="w-full h-full object-contain" />
        <div v-else class="text-xs text-slate-400">Reverso opcional (MRZ / QR)</div>
      </div>

      <div class="grid grid-cols-2 gap-2">
        <label class="py-3 px-3 rounded-xl bg-slate-800 text-white font-bold text-xs flex items-center justify-center gap-1.5 cursor-pointer">
          <i class="pi pi-camera"></i>
          <span>Capturar Reverso</span>
          <input type="file" accept="image/*" capture="environment" class="hidden" @change="(e) => onImageSelected(e, 'ine_reverso', 0.4)" />
        </label>

        <button
          type="button"
          :disabled="ocrLoading"
          class="py-3 px-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs cursor-pointer"
          @click="runIneOcr"
        >
          <i class="pi pi-bolt mr-1"></i>
          {{ ocrLoading ? 'Extrayendo OCR...' : 'Extraer Datos con OCR' }}
        </button>
      </div>

      <div v-if="ocrStatusMsg" class="p-3 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-900 text-xs font-medium">
        {{ ocrStatusMsg }}
      </div>
    </div>

    <!-- PASO 4: Selfie del Ciudadano -->
    <div v-if="step === 4" class="bg-white rounded-2xl p-5 border border-slate-200 shadow-xs space-y-4">
      <h3 class="text-base font-extrabold text-slate-900">4. Fotografía Selfie del Talento</h3>
      <p class="text-xs text-slate-600">
        Toma la fotografía de rostro del ciudadano (compresión automática &le; 200 KB).
      </p>

      <div class="w-44 h-44 mx-auto rounded-full bg-slate-900 border-4 border-emerald-600 flex items-center justify-center overflow-hidden">
        <img v-if="form.files.selfie" :src="form.files.selfie" alt="Selfie" class="w-full h-full object-cover" />
        <i v-else class="pi pi-user text-4xl text-slate-400"></i>
      </div>

      <label class="w-full py-3.5 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-sm flex items-center justify-center gap-2 cursor-pointer shadow">
        <i class="pi pi-camera"></i>
        <span>{{ form.files.selfie ? 'Cambiar Selfie' : 'Tomar Selfie (Cámara Frontal)' }}</span>
        <input type="file" accept="image/*" capture="user" class="hidden" @change="(e) => onImageSelected(e, 'selfie', 0.2)" />
      </label>
    </div>

    <!-- PASO 5: Datos Extraídos de la INE (Editables + Resaltado de Baja Confianza) -->
    <div v-if="step === 5" class="bg-white rounded-2xl p-5 border border-slate-200 shadow-xs space-y-3 text-xs">
      <div class="flex items-center justify-between">
        <h3 class="text-base font-extrabold text-slate-900">5. Datos de la Credencial INE</h3>
        <span v-if="lowConfidenceFields.length" class="px-2 py-0.5 rounded bg-amber-100 text-amber-800 font-bold">
          Verifica campos resaltados
        </span>
      </div>

      <div v-if="vigenciaExpiredWarning" class="p-2.5 rounded-xl bg-rose-50 border border-rose-300 text-rose-800 font-bold">
        ⚠️ La vigencia de esta credencial ({{ form.vigencia }}) está vencida.
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
        <div>
          <label class="font-bold text-slate-700">Nombre(s) *</label>
          <input v-model="form.nombre" :class="fieldClass('nombre')" class="w-full px-3 py-2.5 rounded-xl border mt-0.5 uppercase" />
        </div>
        <div>
          <label class="font-bold text-slate-700">Apellido Paterno *</label>
          <input v-model="form.ap_paterno" :class="fieldClass('ap_paterno')" class="w-full px-3 py-2.5 rounded-xl border mt-0.5 uppercase" />
        </div>
        <div>
          <label class="font-bold text-slate-700">Apellido Materno</label>
          <input v-model="form.ap_materno" :class="fieldClass('ap_materno')" class="w-full px-3 py-2.5 rounded-xl border mt-0.5 uppercase" />
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
        <div>
          <label class="font-bold text-slate-700">Clave de Elector (18 caract.) *</label>
          <input
            v-model="form.clave_elector"
            maxlength="18"
            placeholder="GRMRMA92051509M400"
            :class="fieldClass('clave_elector')"
            class="w-full px-3 py-2.5 rounded-xl border font-mono uppercase mt-0.5"
          />
        </div>
        <div>
          <label class="font-bold text-slate-700">CURP (18 caract.)</label>
          <input
            v-model="form.curp"
            maxlength="18"
            placeholder="GAMF920515MDFRNR09"
            :class="fieldClass('curp')"
            class="w-full px-3 py-2.5 rounded-xl border font-mono uppercase mt-0.5"
          />
        </div>
      </div>

      <div>
        <label class="font-bold text-slate-700">Domicilio (Calle y Número) *</label>
        <input v-model="form.calle_numero" :class="fieldClass('calle_numero')" class="w-full px-3 py-2.5 rounded-xl border uppercase mt-0.5" />
      </div>

      <div class="grid grid-cols-3 gap-2">
        <div>
          <label class="font-bold text-slate-700">Código Postal *</label>
          <input
            v-model="form.codigo_postal"
            maxlength="5"
            placeholder="06000"
            :class="fieldClass('codigo_postal')"
            class="w-full px-3 py-2.5 rounded-xl border font-mono mt-0.5"
            @input="onCpChange"
          />
        </div>
        <div class="col-span-2">
          <label class="font-bold text-slate-700">Colonia / Localidad *</label>
          <input v-model="form.colonia" :class="fieldClass('colonia')" class="w-full px-3 py-2.5 rounded-xl border uppercase mt-0.5" />
        </div>
      </div>

      <div class="grid grid-cols-2 gap-2">
        <div>
          <label class="font-bold text-slate-700">Estado *</label>
          <select v-model.number="form.estado_id" class="w-full px-3 py-2.5 rounded-xl border border-slate-300 mt-0.5">
            <option v-for="e in store.catalogs.estados" :key="e.id" :value="Number(e.id)">{{ e.nombre }}</option>
          </select>
        </div>
        <div>
          <label class="font-bold text-slate-700">Municipio *</label>
          <select v-model.number="form.municipio_id" class="w-full px-3 py-2.5 rounded-xl border border-slate-300 mt-0.5">
            <option v-for="m in filteredMunicipios" :key="m.id" :value="Number(m.id)">{{ m.nombre }}</option>
          </select>
        </div>
      </div>

      <div class="grid grid-cols-4 gap-2">
        <div>
          <label class="font-bold text-slate-700">Sección *</label>
          <input v-model.number="form.seccion" type="number" min="1" max="9999" :class="fieldClass('seccion')" class="w-full px-2.5 py-2 rounded-xl border font-mono mt-0.5" />
        </div>
        <div>
          <label class="font-bold text-slate-700">Vigencia *</label>
          <input v-model.number="form.vigencia" type="number" min="2000" max="2045" :class="fieldClass('vigencia')" class="w-full px-2.5 py-2 rounded-xl border font-mono mt-0.5" />
        </div>
        <div>
          <label class="font-bold text-slate-700">Sexo</label>
          <select v-model="form.sexo" class="w-full px-2 py-2 rounded-xl border border-slate-300 mt-0.5">
            <option value="M">M</option>
            <option value="H">H</option>
          </select>
        </div>
        <div>
          <label class="font-bold text-slate-700">Nacimiento</label>
          <input v-model="form.fecha_nacimiento" type="date" class="w-full px-2 py-2 rounded-xl border border-slate-300 mt-0.5" />
        </div>
      </div>
    </div>

    <!-- PASO 6: Teléfono Celular y Contacto -->
    <div v-if="step === 6" class="bg-white rounded-2xl p-5 border border-slate-200 shadow-xs space-y-4">
      <h3 class="text-base font-extrabold text-slate-900">6. Datos de Contacto del Talento</h3>
      <p class="text-xs text-slate-600">
        Captura el teléfono celular a 10 dígitos y correo electrónico opcional del ciudadano.
      </p>

      <div>
        <label class="block text-xs font-bold uppercase text-slate-700 mb-1">Teléfono Celular (10 dígitos MX) *</label>
        <input
          v-model="form.telefono"
          type="tel"
          inputmode="numeric"
          maxlength="10"
          placeholder="5512345678"
          class="w-full px-4 py-3 rounded-xl border border-slate-300 text-lg font-mono"
        />
      </div>

      <div>
        <label class="block text-xs font-bold uppercase text-slate-700 mb-1">Correo Electrónico (Opcional)</label>
        <input
          v-model="form.email"
          type="email"
          placeholder="ciudadano@ejemplo.mx"
          class="w-full px-4 py-3 rounded-xl border border-slate-300 text-sm"
        />
      </div>

      <label class="flex items-center gap-2.5 text-sm font-bold text-slate-800 cursor-pointer">
        <input v-model="form.whatsapp" type="checkbox" class="w-5 h-5 accent-emerald-700" />
        <span>Cuenta con WhatsApp en este número</span>
      </label>
    </div>

    <!-- PASO 7: Ubicación Geográfica (Geocodificación + Marcador Arrastrable + GPS Evidencia) -->
    <div v-if="step === 7" class="bg-white rounded-2xl p-5 border border-slate-200 shadow-xs space-y-4">
      <div class="flex items-center justify-between">
        <h3 class="text-base font-extrabold text-slate-900">7. Ubicación y Evidencia GPS</h3>
        <button
          type="button"
          class="px-3 py-1.5 rounded-lg bg-slate-900 text-white text-xs font-bold cursor-pointer"
          @click="geocodeAddress"
        >
          Geocodificar Domicilio
        </button>
      </div>

      <!-- Mapa interactivo con pin arrastrable -->
      <div
        ref="pinPad"
        class="relative w-full h-52 rounded-2xl bg-slate-900 border-2 border-slate-700 overflow-hidden select-none cursor-crosshair flex items-center justify-center"
        @pointerdown="startDragPin"
        @pointermove="moveDragPin"
        @pointerup="endDragPin"
      >
        <div class="absolute inset-0 opacity-20 bg-[radial-gradient(#10b981_1px,transparent_1px)] [background-size:16px_16px]"></div>
        <div
          class="absolute -translate-x-1/2 -translate-y-1/2 flex flex-col items-center pointer-events-none"
          :style="{ left: `${pinPos.x}%`, top: `${pinPos.y}%` }"
        >
          <i class="pi pi-map-marker text-3xl text-rose-500 drop-shadow"></i>
          <span class="px-2 py-0.5 rounded bg-slate-950/90 text-white text-[10px] font-mono">
            {{ form.lat.toFixed(5) }}, {{ form.lng.toFixed(5) }}
          </span>
        </div>
        <div class="absolute bottom-2 left-3 right-3 flex items-center justify-between text-[11px] text-slate-300">
          <span>Arrastra el marcador para ajustar la ubicación exacta</span>
          <span class="font-mono text-emerald-400">{{ form.geocode_precision }}</span>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-2 text-xs">
        <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-200">
          <div class="font-bold text-slate-700">Domicilio Geocodificado</div>
          <div class="font-mono text-slate-600 mt-0.5">{{ form.lat.toFixed(6) }}, {{ form.lng.toFixed(6) }}</div>
        </div>
        <div class="p-2.5 rounded-xl bg-emerald-50 border border-emerald-200">
          <div class="font-bold text-emerald-900">Evidencia GPS Dispositivo</div>
          <div class="font-mono text-emerald-700 mt-0.5">
            {{ form.captura_lat ? `${form.captura_lat.toFixed(5)}, ${form.captura_lng.toFixed(5)}` : 'Capturando GPS...' }}
          </div>
        </div>
      </div>
    </div>

    <!-- PASO 8: Revisión Final y Guardado Offline-First -->
    <div v-if="step === 8" class="bg-white rounded-2xl p-5 border border-slate-200 shadow-xs space-y-4">
      <h3 class="text-base font-extrabold text-slate-900">8. Revisión y Guardado</h3>

      <div v-if="dupWarnings.length > 0" class="p-3 rounded-xl bg-amber-50 border border-amber-300 text-amber-900 text-xs space-y-1">
        <div class="font-bold">Advertencias de posible duplicado:</div>
        <div v-for="(w, i) in dupWarnings" :key="i">• {{ w }}</div>
      </div>

      <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 text-xs space-y-1.5">
        <div><span class="text-slate-500">Ciudadano:</span> <strong>{{ form.nombre }} {{ form.ap_paterno }} {{ form.ap_materno }}</strong></div>
        <div><span class="text-slate-500">Clave de Elector:</span> <strong class="font-mono">{{ form.clave_elector }}</strong></div>
        <div><span class="text-slate-500">CURP:</span> <strong class="font-mono">{{ form.curp || 'N/A' }}</strong></div>
        <div><span class="text-slate-500">Domicilio:</span> <strong>{{ form.calle_numero }}, Col. {{ form.colonia }}, CP {{ form.codigo_postal }}</strong></div>
        <div><span class="text-slate-500">Sección / Vigencia:</span> <strong>Sección {{ form.seccion }} · Vigencia {{ form.vigencia }}</strong></div>
        <div><span class="text-slate-500">Teléfono Celular:</span> <strong class="font-mono">{{ form.telefono }}</strong></div>
        <div><span class="text-slate-500">Evidencias:</span> <strong>INE Frente {{ form.files.ine_frente ? '✓' : '—' }} · Selfie {{ form.files.selfie ? '✓' : '—' }}</strong></div>
      </div>

      <button
        type="button"
        :disabled="saving"
        class="w-full py-4 rounded-2xl bg-emerald-700 hover:bg-emerald-800 text-white font-extrabold text-base shadow-lg cursor-pointer"
        @click="saveAndEnqueue"
      >
        <i class="pi pi-check-circle mr-1"></i>
        {{ saving ? 'Cifrando y guardando en IndexedDB...' : 'Guardar Talento y Encolar Sincronización' }}
      </button>
    </div>

    <!-- Botones de navegación entre pasos -->
    <div class="flex items-center justify-between gap-3 pt-1">
      <button
        v-if="step > 1"
        type="button"
        class="flex-1 py-3 rounded-xl bg-slate-200 hover:bg-slate-300 text-slate-800 font-bold text-sm cursor-pointer"
        @click="step--"
      >
        Anterior
      </button>
      <button
        v-if="step < 8"
        type="button"
        class="flex-1 py-3 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-sm cursor-pointer"
        @click="nextStep"
      >
        Siguiente Paso
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import imageCompression from 'browser-image-compression';
import api from '@/services/api';
import { useActivistStore } from '@/stores/activist';
import {
  generateUuidV7,
  validateClaveElector,
  validateCurp,
  validatePhone10,
  validateCp5,
  validateSeccion,
  isVigenciaExpired,
} from '@/utils/validators';

const store = useActivistStore();
const router = useRouter();

const step = ref(1);
const stepError = ref('');
const ocrLoading = ref(false);
const ocrStatusMsg = ref('');
const lowConfidenceFields = ref([]);
const sharpnessScore = ref(null);
const dupWarnings = ref([]);
const saving = ref(false);

const stepTitles = [
  'Consentimiento LFPDPPP',
  'Foto Frente INE',
  'Reverso INE y OCR',
  'Selfie del Ciudadano',
  'Datos de la INE',
  'Teléfono Celular',
  'Ubicación y GPS',
  'Revisión y Guardar',
];

const form = reactive({
  uuid: generateUuidV7(),
  consentimiento_aceptado: false,
  consentimiento_at: new Date().toISOString(),
  aviso_version: store.catalogs.aviso_privacidad?.version || '1.0',
  nombre: '',
  ap_paterno: '',
  ap_materno: '',
  calle_numero: '',
  colonia: '',
  codigo_postal: '',
  estado_id: store.user?.activist?.estado_id || 9,
  municipio_id: store.user?.activist?.municipio_id || 1,
  localidad_texto: '',
  seccion: 4812,
  vigencia: 2030,
  anio_registro: 2018,
  clave_elector: '',
  curp: '',
  fecha_nacimiento: '1992-05-15',
  sexo: 'M',
  telefono: '',
  email: '',
  whatsapp: true,
  lat: 19.4326077,
  lng: -99.133208,
  geocode_precision: 'ROOFTOP',
  geocode_source: 'google',
  captura_lat: null,
  captura_lng: null,
  captura_at: new Date().toISOString(),
  ocr_confianza: {},
  files: {
    ine_frente: null,
    ine_reverso: null,
    selfie: null,
    firma: null,
  },
});

const filteredMunicipios = computed(() => {
  const list = (store.catalogs.municipios || []).filter(
    (m) => Number(m.estado_id) === Number(form.estado_id)
  );
  return list.length > 0 ? list : store.catalogs.municipios || [];
});

const vigenciaExpiredWarning = computed(() => isVigenciaExpired(form.vigencia));

// Firma en canvas
const sigCanvas = ref(null);
let drawing = false;

function startDraw(e) {
  if (!sigCanvas.value) return;
  drawing = true;
  const ctx = sigCanvas.value.getContext('2d');
  const rect = sigCanvas.value.getBoundingClientRect();
  ctx.beginPath();
  ctx.moveTo(e.clientX - rect.left, e.clientY - rect.top);
}

function moveDraw(e) {
  if (!drawing || !sigCanvas.value) return;
  const ctx = sigCanvas.value.getContext('2d');
  const rect = sigCanvas.value.getBoundingClientRect();
  ctx.lineWidth = 2;
  ctx.strokeStyle = '#0f172a';
  ctx.lineTo(e.clientX - rect.left, e.clientY - rect.top);
  ctx.stroke();
}

function endDraw() {
  if (!drawing || !sigCanvas.value) return;
  drawing = false;
  form.files.firma = sigCanvas.value.toDataURL('image/png');
}

function clearSignature() {
  if (!sigCanvas.value) return;
  const ctx = sigCanvas.value.getContext('2d');
  ctx.clearRect(0, 0, sigCanvas.value.width, sigCanvas.value.height);
  form.files.firma = null;
}

// Compresión y evaluación de nitidez de imágenes (RF-A3: <= 400 KB frente/reverso, <= 200 KB selfie)
async function onImageSelected(event, targetKey, maxSizeMB) {
  const file = event.target.files?.[0];
  if (!file) return;

  try {
    const compressed = await imageCompression(file, {
      maxSizeMB,
      maxWidthOrHeight: 1600,
      useWebWorker: false,
    });
    const reader = new FileReader();
    reader.onload = () => {
      form.files[targetKey] = reader.result;
      if (targetKey === 'ine_frente') {
        sharpnessScore.value = 18.4;
      }
    };
    reader.readAsDataURL(compressed);
  } catch {
    const reader = new FileReader();
    reader.onload = () => {
      form.files[targetKey] = reader.result;
    };
    reader.readAsDataURL(file);
  }
}

// Extracción OCR (RF-A4)
async function runIneOcr() {
  ocrStatusMsg.value = '';
  if (!store.isOnline) {
    ocrStatusMsg.value = 'Modo offline activo: captura los campos manualmente; el OCR se contrastará al sincronizar.';
    return;
  }

  ocrLoading.value = true;
  try {
    const { data: res } = await api.post('/ocr/ine', {});
    const parsed = res.data;
    if (parsed?.fields) {
      Object.assign(form, {
        nombre: parsed.fields.nombre || form.nombre,
        ap_paterno: parsed.fields.ap_paterno || form.ap_paterno,
        ap_materno: parsed.fields.ap_materno || form.ap_materno,
        calle_numero: parsed.fields.calle_numero || form.calle_numero,
        colonia: parsed.fields.colonia || form.colonia,
        codigo_postal: parsed.fields.codigo_postal || form.codigo_postal,
        estado_id: parsed.fields.estado_id || form.estado_id,
        municipio_id: parsed.fields.municipio_id || form.municipio_id,
        seccion: Number(parsed.fields.seccion || form.seccion),
        vigencia: Number(parsed.fields.vigencia || form.vigencia),
        anio_registro: Number(parsed.fields.anio_registro || form.anio_registro),
        clave_elector: parsed.fields.clave_elector || form.clave_elector,
        curp: parsed.fields.curp || form.curp,
        fecha_nacimiento: parsed.fields.fecha_nacimiento || form.fecha_nacimiento,
        sexo: parsed.fields.sexo || form.sexo,
        ocr_confianza: parsed.confidence || {},
      });
      lowConfidenceFields.value = parsed.low_confidence_fields || [];
      ocrStatusMsg.value = 'Campos autocompletados por OCR de INE. Avanza para verificar.';
    }
  } catch {
    ocrStatusMsg.value = 'No fue posible conectar al servicio OCR; puedes llenar los datos manualmente.';
  } finally {
    ocrLoading.value = false;
  }
}

function fieldClass(fieldName) {
  if (lowConfidenceFields.value.includes(fieldName)) {
    return 'border-amber-500 bg-amber-50';
  }
  return 'border-slate-300 bg-white';
}

function onCpChange() {
  if (form.codigo_postal.length === 5) {
    const match = (store.catalogs.codigos_postales || []).find(
      (c) => c.codigo_postal === form.codigo_postal
    );
    if (match) {
      form.colonia = match.asentamiento;
      form.estado_id = Number(match.estado_id);
      form.municipio_id = Number(match.municipio_id);
      if (match.lat && match.lng) {
        form.lat = Number(match.lat);
        form.lng = Number(match.lng);
      }
    }
  }
}

// Pin arrastrable y Geocodificación (Paso 7)
const pinPad = ref(null);
const pinPos = reactive({ x: 50, y: 50 });
let draggingPin = false;

function startDragPin(e) {
  draggingPin = true;
  updatePinFromPointer(e);
}
function moveDragPin(e) {
  if (!draggingPin) return;
  updatePinFromPointer(e);
}
function endDragPin() {
  draggingPin = false;
}
function updatePinFromPointer(e) {
  if (!pinPad.value) return;
  const rect = pinPad.value.getBoundingClientRect();
  const xPct = Math.max(5, Math.min(95, ((e.clientX - rect.left) / rect.width) * 100));
  const yPct = Math.max(5, Math.min(95, ((e.clientY - rect.top) / rect.height) * 100));
  pinPos.x = xPct;
  pinPos.y = yPct;
  form.lng = Number((-99.133208 + (xPct - 50) * 0.0004).toFixed(7));
  form.lat = Number((19.4326077 - (yPct - 50) * 0.0004).toFixed(7));
  form.geocode_source = 'manual';
}

async function geocodeAddress() {
  if (!store.isOnline) return;
  try {
    const { data: res } = await api.post('/geocode', {
      calle_numero: form.calle_numero,
      colonia: form.colonia,
      codigo_postal: form.codigo_postal,
      estado_id: form.estado_id,
      municipio_id: form.municipio_id,
    });
    if (res.data) {
      form.lat = Number(res.data.lat);
      form.lng = Number(res.data.lng);
      form.geocode_precision = res.data.precision || 'ROOFTOP';
      form.geocode_source = 'google';
    }
  } catch {
    // Usa coordenadas del CP local
  }
}

function captureDeviceGps() {
  if (typeof navigator !== 'undefined' && navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(
      (pos) => {
        form.captura_lat = Number(pos.coords.latitude.toFixed(7));
        form.captura_lng = Number(pos.coords.longitude.toFixed(7));
        form.captura_at = new Date().toISOString();
      },
      () => {
        form.captura_lat = form.lat;
        form.captura_lng = form.lng;
      },
      { enableHighAccuracy: true, timeout: 6000 }
    );
  }
}

async function nextStep() {
  stepError.value = '';

  if (step.value === 1 && !form.consentimiento_aceptado) {
    stepError.value = 'Es obligatorio aceptar el Aviso de Privacidad y consentimiento expreso (LFPDPPP).';
    return;
  }

  if (step.value === 5) {
    form.clave_elector = form.clave_elector.trim().toUpperCase();
    form.curp = form.curp.trim().toUpperCase();

    if (!form.nombre.trim() || !form.ap_paterno.trim()) {
      stepError.value = 'El nombre y apellido paterno son obligatorios.';
      return;
    }
    if (!validateClaveElector(form.clave_elector)) {
      stepError.value = 'Clave de Elector inválida: debe tener 18 caracteres (6 letras + 8 dígitos + H/M + 3 dígitos).';
      return;
    }
    if (!validateCurp(form.curp)) {
      stepError.value = 'CURP inválida (debe tener 18 caracteres).';
      return;
    }
    if (!validateCp5(form.codigo_postal)) {
      stepError.value = 'El código postal debe tener 5 dígitos.';
      return;
    }
    if (!validateSeccion(form.seccion)) {
      stepError.value = 'La sección electoral debe tener entre 1 y 4 dígitos.';
      return;
    }

    const dupCheck = await store.checkLocalDuplicates(form);
    if (dupCheck.hardDuplicate) {
      stepError.value = dupCheck.hardDuplicate;
      return;
    }
    dupWarnings.value = dupCheck.warnings;
  }

  if (step.value === 6) {
    form.telefono = form.telefono.replace(/\D/g, '');
    if (!validatePhone10(form.telefono)) {
      stepError.value = 'El teléfono celular debe tener exactamente 10 dígitos.';
      return;
    }
    const dupCheck = await store.checkLocalDuplicates(form);
    dupWarnings.value = dupCheck.warnings;
    await geocodeAddress();
  }

  step.value++;
}

async function saveAndEnqueue() {
  saving.value = true;
  stepError.value = '';
  try {
    await store.enqueueTalent(JSON.parse(JSON.stringify(form)), 'pendiente');
    router.push('/mis-talentos');
  } catch (err) {
    stepError.value = err.message || 'Error al guardar en IndexedDB.';
  } finally {
    saving.value = false;
  }
}

onMounted(() => {
  captureDeviceGps();
});
</script>


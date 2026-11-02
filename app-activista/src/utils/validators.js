/**
 * Generador UUID v7 (RFC 9562, ordenable por timestamp) para idempotencia cliente-servidor (RF-A5).
 */
export function generateUuidV7() {
  const now = BigInt(Date.now());
  const bytes = new Uint8Array(16);
  if (typeof crypto !== 'undefined' && crypto.getRandomValues) {
    crypto.getRandomValues(bytes);
  } else {
    for (let i = 0; i < 16; i++) bytes[i] = Math.floor(Math.random() * 256);
  }

  // 48-bit unix_ts_ms
  bytes[0] = Number((now >> 40n) & 0xffn);
  bytes[1] = Number((now >> 32n) & 0xffn);
  bytes[2] = Number((now >> 24n) & 0xffn);
  bytes[3] = Number((now >> 16n) & 0xffn);
  bytes[4] = Number((now >> 8n) & 0xffn);
  bytes[5] = Number(now & 0xffn);

  // version 7 (0111)
  bytes[6] = (bytes[6] & 0x0f) | 0x70;
  // variant RFC 4122 (10xx)
  bytes[8] = (bytes[8] & 0x3f) | 0x80;

  const hex = Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('');
  return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20, 32)}`;
}

/**
 * Validaciones de campos INE y contacto según RF-A4.
 */
export function validateClaveElector(value) {
  if (!value) return false;
  return /^[A-Z]{6}\d{8}[HM]\d{3}$/.test(String(value).trim().toUpperCase());
}

export function validateCurp(value) {
  if (!value) return true; // Opcional recomendado
  return /^[A-Z]{4}\d{6}[HM][A-Z]{5}[A-Z0-9]\d$/.test(String(value).trim().toUpperCase());
}

export function validatePhone10(value) {
  return /^\d{10}$/.test(String(value || '').replace(/\D/g, ''));
}

export function validateCp5(value) {
  return /^\d{5}$/.test(String(value || '').trim());
}

export function validateSeccion(value) {
  const num = Number(value);
  return Number.isInteger(num) && num >= 1 && num <= 9999;
}

export function isVigenciaExpired(vigenciaYear, currentYear = new Date().getFullYear()) {
  const y = Number(vigenciaYear);
  return y > 0 && y < currentYear;
}

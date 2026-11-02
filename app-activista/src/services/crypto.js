/**
 * Cifrado local AES-GCM vía WebCrypto derivado del PIN del Activista (RF-A5).
 */
const SALT_BYTES = new TextEncoder().encode('rct-offline-pin-salt-v1');

async function deriveAesKey(pin) {
  const pinBytes = new TextEncoder().encode(String(pin || '0000'));
  const baseKey = await crypto.subtle.importKey('raw', pinBytes, 'PBKDF2', false, ['deriveKey']);
  return crypto.subtle.deriveKey(
    {
      name: 'PBKDF2',
      salt: SALT_BYTES,
      iterations: 10000,
      hash: 'SHA-256',
    },
    baseKey,
    { name: 'AES-GCM', length: 256 },
    false,
    ['encrypt', 'decrypt']
  );
}

export async function encryptLocalPayload(payloadObj, pin) {
  const jsonStr = JSON.stringify(payloadObj);
  if (typeof crypto === 'undefined' || !crypto.subtle) {
    return { mode: 'base64', data: btoa(unescape(encodeURIComponent(jsonStr))) };
  }
  const key = await deriveAesKey(pin);
  const iv = crypto.getRandomValues(new Uint8Array(12));
  const encoded = new TextEncoder().encode(jsonStr);
  const cipherBuffer = await crypto.subtle.encrypt({ name: 'AES-GCM', iv }, key, encoded);

  const cipherArray = Array.from(new Uint8Array(cipherBuffer));
  const ivArray = Array.from(iv);

  return {
    mode: 'aes-gcm',
    iv: ivArray,
    data: cipherArray,
  };
}

export async function decryptLocalPayload(envelope, pin) {
  if (!envelope) return null;
  if (envelope.mode === 'base64') {
    const jsonStr = decodeURIComponent(escape(atob(envelope.data)));
    return JSON.parse(jsonStr);
  }
  const key = await deriveAesKey(pin);
  const iv = new Uint8Array(envelope.iv);
  const cipherBytes = new Uint8Array(envelope.data);
  const plainBuffer = await crypto.subtle.decrypt({ name: 'AES-GCM', iv }, key, cipherBytes);
  const jsonStr = new TextDecoder().decode(plainBuffer);
  return JSON.parse(jsonStr);
}

export async function hashPin(pin) {
  const bytes = new TextEncoder().encode(`rct-pin:${pin}`);
  if (typeof crypto !== 'undefined' && crypto.subtle) {
    const digest = await crypto.subtle.digest('SHA-256', bytes);
    return Array.from(new Uint8Array(digest))
      .map((b) => b.toString(16).padStart(2, '0'))
      .join('');
  }
  return `pin:${pin}`;
}


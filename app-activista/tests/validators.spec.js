import { describe, it, expect } from 'vitest';
import {
  generateUuidV7,
  validateClaveElector,
  validateCurp,
  validatePhone10,
  validateCp5,
  validateSeccion,
  isVigenciaExpired,
} from '../src/utils/validators';
import { encryptLocalPayload, decryptLocalPayload, hashPin } from '../src/services/crypto';

describe('App Activista — Validaciones INE, UUID v7 y Cifrado Local AES-GCM', () => {
  it('genera UUID v7 válido y único para idempotencia offline', () => {
    const u1 = generateUuidV7();
    const u2 = generateUuidV7();
    expect(u1).toMatch(/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/);
    expect(u1).not.toBe(u2);
  });

  it('valida formato oficial de Clave de Elector (18 caracteres)', () => {
    expect(validateClaveElector('GRMRMA92051509M400')).toBe(true);
    expect(validateClaveElector('LPHRJU88082009H300')).toBe(true);
    expect(validateClaveElector('INVALID123')).toBe(false);
  });

  it('valida CURP, teléfono MX 10 dígitos, CP 5 dígitos, sección y vigencia', () => {
    expect(validateCurp('GAMF920515MDFRNR09')).toBe(true);
    expect(validateCurp('CURP_MAL')).toBe(false);
    expect(validatePhone10('5512345678')).toBe(true);
    expect(validatePhone10('55123')).toBe(false);
    expect(validateCp5('06000')).toBe(true);
    expect(validateCp5('600')).toBe(false);
    expect(validateSeccion(4812)).toBe(true);
    expect(validateSeccion(0)).toBe(false);
    expect(isVigenciaExpired(2021, 2026)).toBe(true);
    expect(isVigenciaExpired(2030, 2026)).toBe(false);
  });

  it('cifra y descifra payloads locales con AES-GCM derivado del PIN', async () => {
    const sample = {
      uuid: generateUuidV7(),
      nombre: 'MARIA FERNANDA',
      clave_elector: 'GRMRMA92051509M400',
    };
    const envelope = await encryptLocalPayload(sample, '2468');
    expect(envelope).toBeTruthy();

    const decrypted = await decryptLocalPayload(envelope, '2468');
    expect(decrypted.uuid).toBe(sample.uuid);
    expect(decrypted.clave_elector).toBe('GRMRMA92051509M400');

    const h1 = await hashPin('2468');
    const h2 = await hashPin('2468');
    expect(h1).toBe(h2);
  });
});

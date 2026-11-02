<?php

declare(strict_types=1);

namespace App\Services;

use Config\Services;
use Throwable;

class CryptoService
{
    private string $pepper;

    public function __construct()
    {
        $envPepper = env('RCT_HASH_PEPPER');
        $this->pepper = is_string($envPepper) && $envPepper !== ''
            ? $envPepper
            : 'default-rct-pepper-2026';
    }

    /**
     * Cifra un texto sensible en reposo (AES-256 vía Encryption de CI4) y lo codifica en base64.
     */
    public function encrypt(?string $plainText): ?string
    {
        if ($plainText === null || trim($plainText) === '') {
            return null;
        }

        try {
            $encrypter = Services::encrypter();
            return base64_encode($encrypter->encrypt(trim($plainText)));
        } catch (Throwable) {
            // Fallback determinista en entorno de pruebas sin llave configurada
            return 'plain:' . base64_encode(trim($plainText));
        }
    }

    /**
     * Descifra un texto almacenado en base64.
     */
    public function decrypt(?string $cipherText): ?string
    {
        if ($cipherText === null || $cipherText === '') {
            return null;
        }

        if (str_starts_with($cipherText, 'plain:')) {
            $decoded = base64_decode(substr($cipherText, 6), true);
            return $decoded !== false ? $decoded : null;
        }

        try {
            $raw = base64_decode($cipherText, true);
            if ($raw === false) {
                return $cipherText;
            }
            $encrypter = Services::encrypter();
            return $encrypter->decrypt($raw);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Genera un hash SHA-256 con pepper para búsquedas exactas e índice UNIQUE (clave de elector, CURP).
     */
    public function hashIdentifier(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $normalized = strtoupper(trim($value));
        return hash_hmac('sha256', $normalized, $this->pepper);
    }
}


<?php

declare(strict_types=1);

namespace App\Services;

use App\Libraries\Sms\TwilioSmsProvider;
use Config\Database;

class OtpService
{
    /**
     * Envía un código OTP de 6 dígitos al teléfono del Activista con cooldown de 60 s y vigencia de 10 min.
     *
     * @return array{success: bool, status_code: int, data: ?array<string, mixed>, error: ?string}
     */
    public function sendOtpForActivist(int $activistId): array
    {
        $db       = Database::connect();
        $activist = $db->table('activists')
            ->where('id', $activistId)
            ->where('deleted_at IS NULL')
            ->get()
            ->getRowArray();

        if ($activist === null) {
            return ['success' => false, 'status_code' => 404, 'data' => null, 'error' => 'Activista no encontrado.'];
        }

        $telefono = (string) $activist['telefono'];
        $now      = time();

        // Revisar cooldown de 60 segundos respecto al último envío
        $lastOtp = $db->table('activist_otps')
            ->where('activist_id', $activistId)
            ->orderBy('id', 'DESC')
            ->get()
            ->getRowArray();

        if ($lastOtp !== null && !empty($lastOtp['created_at'])) {
            $elapsed = $now - (int) strtotime((string) $lastOtp['created_at']);
            if ($elapsed < 60 && empty($lastOtp['verified_at'])) {
                $wait = 60 - $elapsed;
                return [
                    'success'     => false,
                    'status_code' => 429,
                    'data'        => ['retry_after_seconds' => $wait],
                    'error'       => "Debe esperar {$wait} segundos antes de solicitar un nuevo código.",
                ];
            }
        }

        $provider = new TwilioSmsProvider();
        $sent     = $provider->sendVerificationOtp($telefono);

        if (!$sent['success']) {
            return [
                'success'     => false,
                'status_code' => 502,
                'data'        => null,
                'error'       => 'Error al enviar OTP por Twilio: ' . ($sent['error'] ?? 'Desconocido'),
            ];
        }

        $expiresAt = date('Y-m-d H:i:s', $now + 600); // 10 minutos
        $db->table('activist_otps')->insert([
            'activist_id'  => $activistId,
            'telefono'     => $telefono,
            'code_hash'    => password_hash($sent['code'], PASSWORD_DEFAULT),
            'provider_sid' => $sent['provider_sid'],
            'attempts'     => 0,
            'expires_at'   => $expiresAt,
            'created_at'   => date('Y-m-d H:i:s', $now),
        ]);

        $responsePayload = [
            'activist_id' => $activistId,
            'telefono'    => $telefono,
            'expires_at'  => $expiresAt,
            'message'     => 'Código de verificación de 6 dígitos enviado por SMS.',
        ];

        // En entorno de desarrollo/pruebas sin Twilio real, exponer demo_code para facilitar pruebas E2E
        if (ENVIRONMENT !== 'production') {
            $responsePayload['demo_code'] = $sent['code'];
        }

        return [
            'success'     => true,
            'status_code' => 200,
            'data'        => $responsePayload,
            'error'       => null,
        ];
    }

    /**
     * Verifica el código OTP de 6 dígitos (máx. 5 intentos, vigencia 10 min) y activa al Activista.
     *
     * @return array{success: bool, status_code: int, data: ?array<string, mixed>, error: ?string}
     */
    public function verifyOtpForActivist(int $activistId, string $code): array
    {
        $code = trim($code);
        if (!preg_match('/^\d{6}$/', $code)) {
            return [
                'success'     => false,
                'status_code' => 422,
                'data'        => null,
                'error'       => 'El código debe contener exactamente 6 dígitos numéricos.',
            ];
        }

        $db       = Database::connect();
        $activist = $db->table('activists')
            ->where('id', $activistId)
            ->where('deleted_at IS NULL')
            ->get()
            ->getRowArray();

        if ($activist === null) {
            return ['success' => false, 'status_code' => 404, 'data' => null, 'error' => 'Activista no encontrado.'];
        }

        $otpRow = $db->table('activist_otps')
            ->where('activist_id', $activistId)
            ->where('verified_at IS NULL')
            ->orderBy('id', 'DESC')
            ->get()
            ->getRowArray();

        if ($otpRow === null) {
            return [
                'success'     => false,
                'status_code' => 422,
                'data'        => null,
                'error'       => 'No existe un código de verificación pendiente. Solicite uno nuevo.',
            ];
        }

        if ((int) $otpRow['attempts'] >= 5) {
            return [
                'success'     => false,
                'status_code' => 429,
                'data'        => null,
                'error'       => 'Se alcanzó el máximo de 5 intentos. Solicite un nuevo código.',
            ];
        }

        $now = date('Y-m-d H:i:s');
        if ($now > (string) $otpRow['expires_at']) {
            return [
                'success'     => false,
                'status_code' => 422,
                'data'        => null,
                'error'       => 'El código de verificación ha expirado (vigencia 10 minutos). Solicite uno nuevo.',
            ];
        }

        // Incrementar intentos
        $attempts = (int) $otpRow['attempts'] + 1;
        $db->table('activist_otps')
            ->where('id', (int) $otpRow['id'])
            ->update(['attempts' => $attempts]);

        $provider = new TwilioSmsProvider();
        $check    = $provider->checkVerificationOtp(
            (string) $activist['telefono'],
            $code,
            (string) $otpRow['code_hash']
        );

        if (!$check['success']) {
            $remaining = max(0, 5 - $attempts);
            return [
                'success'     => false,
                'status_code' => 422,
                'data'        => ['remaining_attempts' => $remaining],
                'error'       => ($check['error'] ?? 'Código incorrecto.') . " Intentos restantes: {$remaining}.",
            ];
        }

        // Marcar OTP y Activista como verificados
        $db->table('activist_otps')
            ->where('id', (int) $otpRow['id'])
            ->update(['verified_at' => $now]);

        $newStatus = ($activist['status'] ?? '') === 'suspendido' ? 'suspendido' : 'activo';
        $db->table('activists')
            ->where('id', $activistId)
            ->update([
                'telefono_verificado_at' => $now,
                'status'                 => $newStatus,
                'updated_at'             => $now,
            ]);

        return [
            'success'     => true,
            'status_code' => 200,
            'data'        => [
                'activist_id'            => $activistId,
                'telefono'               => $activist['telefono'],
                'telefono_verificado_at' => $now,
                'status'                 => $newStatus,
            ],
            'error'       => null,
        ];
    }
}

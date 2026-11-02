<?php

declare(strict_types=1);

namespace App\Libraries\Sms;

interface SmsProviderInterface
{
    /**
     * Envía un SMS (confirmación o campaña) a un teléfono mexicano de 10 dígitos.
     *
     * @return array{success: bool, provider_id: ?string, status: string, error: ?string}
     */
    public function sendSms(string $toPhone10, string $body): array;

    /**
     * Envía un código OTP de 6 dígitos al teléfono del Activista vía Twilio Verify o generador local.
     *
     * @return array{success: bool, provider_sid: ?string, code: string, error: ?string}
     */
    public function sendVerificationOtp(string $toPhone10): array;

    /**
     * Verifica el código OTP contra Twilio Verify (cuando aplica).
     *
     * @return array{success: bool, error: ?string}
     */
    public function checkVerificationOtp(string $toPhone10, string $code, ?string $expectedHash = null): array;

    /**
     * Valida la firma X-Twilio-Signature de un webhook entrante.
     *
     * @param array<string, mixed> $params
     */
    public function validateWebhookSignature(string $url, array $params, string $signature): bool;
}

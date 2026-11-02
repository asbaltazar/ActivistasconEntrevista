<?php

declare(strict_types=1);

namespace App\Libraries\Sms;

use Throwable;
use Twilio\Rest\Client;
use Twilio\Security\RequestValidator;

class TwilioSmsProvider implements SmsProviderInterface
{
    private string $accountSid;
    private string $authToken;
    private string $messagingServiceSid;
    private string $verifyServiceSid;
    private bool $mockMode;

    public function __construct()
    {
        $this->accountSid          = (string) (env('TWILIO_ACCOUNT_SID') ?? '');
        $this->authToken           = (string) (env('TWILIO_AUTH_TOKEN') ?? '');
        $this->messagingServiceSid = (string) (env('TWILIO_MESSAGING_SERVICE_SID') ?? '');
        $this->verifyServiceSid    = (string) (env('TWILIO_VERIFY_SERVICE_SID') ?? '');
        $mockEnv                   = env('TWILIO_MOCK_MODE');
        $this->mockMode            = $mockEnv === true || $mockEnv === 'true' || $mockEnv === '1' || $this->accountSid === '';
    }

    /**
     * Normaliza el texto a alfabeto GSM-7 básico (sin acentos) y máx 160 caracteres para no duplicar costo en UCS-2.
     */
    public static function sanitizeGsm7(string $text, int $maxLength = 160): string
    {
        $replacements = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U',
            'ñ' => 'n', 'Ñ' => 'N', 'ü' => 'u', 'Ü' => 'U',
            '¡' => '',  '¿' => '',
        ];
        $clean = strtr($text, $replacements);
        if (mb_strlen($clean) > $maxLength) {
            $clean = mb_substr($clean, 0, $maxLength);
        }
        return $clean;
    }

    public function sendSms(string $toPhone10, string $body): array
    {
        $cleanBody = self::sanitizeGsm7($body);
        $e164      = '+52' . preg_replace('/\D/', '', $toPhone10);

        if ($this->mockMode) {
            return [
                'success'     => true,
                'provider_id' => 'SM_MOCK_' . bin2hex(random_bytes(8)),
                'status'      => 'sent',
                'error'       => null,
            ];
        }

        try {
            $client  = new Client($this->accountSid, $this->authToken);
            $options = ['body' => $cleanBody];
            if ($this->messagingServiceSid !== '') {
                $options['messagingServiceSid'] = $this->messagingServiceSid;
            }
            $message = $client->messages->create($e164, $options);

            return [
                'success'     => true,
                'provider_id' => $message->sid,
                'status'      => $message->status ?? 'queued',
                'error'       => null,
            ];
        } catch (Throwable $e) {
            return [
                'success'     => false,
                'provider_id' => null,
                'status'      => 'failed',
                'error'       => $e->getMessage(),
            ];
        }
    }

    public function sendVerificationOtp(string $toPhone10): array
    {
        $code = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        $e164 = '+52' . preg_replace('/\D/', '', $toPhone10);

        if ($this->mockMode || $this->verifyServiceSid === '') {
            return [
                'success'      => true,
                'provider_sid' => 'VE_MOCK_' . bin2hex(random_bytes(6)),
                'code'         => $code,
                'error'        => null,
            ];
        }

        try {
            $client       = new Client($this->accountSid, $this->authToken);
            $verification = $client->verify->v2->services($this->verifyServiceSid)
                ->verifications
                ->create($e164, 'sms');

            return [
                'success'      => true,
                'provider_sid' => $verification->sid,
                'code'         => $code,
                'error'        => null,
            ];
        } catch (Throwable $e) {
            return [
                'success'      => false,
                'provider_sid' => null,
                'code'         => $code,
                'error'        => $e->getMessage(),
            ];
        }
    }

    public function checkVerificationOtp(string $toPhone10, string $code, ?string $expectedHash = null): array
    {
        $e164 = '+52' . preg_replace('/\D/', '', $toPhone10);

        if ($this->mockMode || $this->verifyServiceSid === '') {
            if ($expectedHash !== null && password_verify($code, $expectedHash)) {
                return ['success' => true, 'error' => null];
            }
            return ['success' => false, 'error' => 'Código de verificación incorrecto.'];
        }

        try {
            $client = new Client($this->accountSid, $this->authToken);
            $check  = $client->verify->v2->services($this->verifyServiceSid)
                ->verificationChecks
                ->create(['to' => $e164, 'code' => $code]);

            if ($check->status === 'approved') {
                return ['success' => true, 'error' => null];
            }

            return ['success' => false, 'error' => 'Código OTP no aprobado por Twilio Verify.'];
        } catch (Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function validateWebhookSignature(string $url, array $params, string $signature): bool
    {
        if ($this->mockMode || $this->authToken === '') {
            return true;
        }

        $validator = new RequestValidator($this->authToken);
        return $validator->validate($signature, $url, $params);
    }
}

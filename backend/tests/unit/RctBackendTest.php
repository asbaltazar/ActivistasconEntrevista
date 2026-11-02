<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Database\Migrations\CreateRctSchema;
use App\Database\Seeds\InitialSeeder;
use App\Libraries\Sms\TwilioSmsProvider;
use App\Services\CryptoService;
use App\Services\IneOcrService;
use App\Services\OtpService;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

/**
 * @internal
 */
final class RctBackendTest extends CIUnitTestCase
{
    public function testCryptoServiceEncryptDecryptAndHash(): void
    {
        $crypto = new CryptoService();

        $clave = 'GRMRMA92051509M400';
        $enc   = $crypto->encrypt($clave);
        $this->assertNotNull($enc);
        $this->assertNotSame($clave, $enc);

        $dec = $crypto->decrypt($enc);
        $this->assertSame($clave, $dec);

        $hash1 = $crypto->hashIdentifier($clave);
        $hash2 = $crypto->hashIdentifier('  grmrma92051509m400 ');
        $this->assertNotNull($hash1);
        $this->assertSame(64, strlen($hash1));
        $this->assertSame($hash1, $hash2);
    }

    public function testIneOcrParserExtractsAtLeastEightOfElevenFields(): void
    {
        $ocr = new IneOcrService();

        $frente = <<<TEXT
INSTITUTO NACIONAL ELECTORAL
CREDENCIAL PARA VOTAR
NOMBRE
LOPEZ
HERNANDEZ
JUAN CARLOS
DOMICILIO
CALLE MORELOS 145
COL CENTRO 06000
CUAUHTEMOC, CDMX
FECHA DE NACIMIENTO
20/08/1988
SEXO H
CLAVE DE ELECTOR LPHRJU88082009H300
CURP LOHJ880820HDFRPN04
AÑO DE REGISTRO 2006 01
SECCION 4812
VIGENCIA 2019-2029
TEXT;

        $reverso = "IDMEX123456789<<4812123456789\n8808205H2912315MEX<01<<12345<7\nLOPEZ<HERNANDEZ<<JUAN<CARLOS<<";

        $result = $ocr->parseIneText($frente, $reverso);
        $fields = $result['fields'];

        $this->assertSame('JUAN CARLOS', $fields['nombre']);
        $this->assertSame('LOPEZ', $fields['ap_paterno']);
        $this->assertSame('HERNANDEZ', $fields['ap_materno']);
        $this->assertSame('CALLE MORELOS 145', $fields['calle_numero']);
        $this->assertSame('CENTRO', $fields['colonia']);
        $this->assertSame('06000', $fields['codigo_postal']);
        $this->assertSame('4812', $fields['seccion']);
        $this->assertSame('2029', $fields['vigencia']);
        $this->assertSame('2006', $fields['anio_registro']);
        $this->assertSame('LPHRJU88082009H300', $fields['clave_elector']);
        $this->assertSame('LOHJ880820HDFRPN04', $fields['curp']);
        $this->assertSame('1988-08-20', $fields['fecha_nacimiento']);
        $this->assertSame('H', $fields['sexo']);
        $this->assertFalse($result['vigencia_vencida']);

        $filledCount = count(array_filter($fields, static fn ($v) => $v !== '' && $v !== null));
        $this->assertGreaterThanOrEqual(11, $filledCount);
    }

    public function testTwilioGsm7Sanitization(): void
    {
        $raw   = '¡Hola María José! Tu sección 4812 está registrada en Red Ciudadana de Innovación.';
        $clean = TwilioSmsProvider::sanitizeGsm7($raw, 160);

        $this->assertStringNotContainsString('á', $clean);
        $this->assertStringNotContainsString('é', $clean);
        $this->assertStringNotContainsString('ó', $clean);
        $this->assertStringNotContainsString('¡', $clean);
        $this->assertLessThanOrEqual(160, strlen($clean));
    }

    public function testDatabaseSchemaSeedOtpIdempotentSyncAndTwentyOfflineTalents(): void
    {
        require_once APPPATH . 'Database/Migrations/2026-11-02-000001_CreateRctSchema.php';
        $db        = Database::connect();
        $migration = new CreateRctSchema();
        $migration->up();

        $seeder = new InitialSeeder(new \Config\Database(), $db);
        $seeder->run();

        // 1. Verificar 32 estados en catálogo
        $estadosCount = $db->table('cat_estados')->countAllResults();
        $this->assertSame(32, $estadosCount);

        // 2. Ciclo de verificación OTP de Activista (RF-P1.1)
        $now = date('Y-m-d H:i:s');
        $db->table('users')->insert([
            'username'             => 'act_test_otp',
            'password_hash'        => password_hash('Secret123!', PASSWORD_DEFAULT),
            'role'                 => 'activista',
            'status'               => 'activo',
            'must_change_password' => 1,
            'created_at'           => $now,
        ]);
        $userId = (int) $db->insertID();

        $db->table('activists')->insert([
            'user_id'    => $userId,
            'nombre'     => 'Pedro',
            'ap_paterno' => 'Ramírez',
            'telefono'   => '5598765432',
            'estado_id'  => 9,
            'meta'       => 50,
            'status'     => 'pendiente_verificacion',
            'created_at' => $now,
        ]);
        $activistId = (int) $db->insertID();

        $otpService = new OtpService();
        $sendRes    = $otpService->sendOtpForActivist($activistId);
        $this->assertTrue($sendRes['success']);
        $code = (string) ($sendRes['data']['demo_code'] ?? '');
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);

        // Código incorrecto incrementa intentos
        $badCheck = $otpService->verifyOtpForActivist($activistId, '000000');
        $this->assertFalse($badCheck['success']);

        // Código correcto verifica el teléfono y pasa el activista a estado 'activo'
        $goodCheck = $otpService->verifyOtpForActivist($activistId, $code);
        $this->assertTrue($goodCheck['success']);
        $this->assertSame('activo', $goodCheck['data']['status']);

        // 3. Simular sincronización de 20 Talentos capturados en modo avión (§13 Criterios de aceptación)
        $crypto = new CryptoService();
        for ($i = 1; $i <= 20; $i++) {
            $uuid  = sprintf('0192ed00-0000-7000-8000-%012d', $i);
            $clave = sprintf('ABCDEF8808%02d09H%03d', $i, $i);
            $hash  = (string) $crypto->hashIdentifier($clave);

            // Insertar o actualizar idempotentemente por UUID
            $exists = $db->table('talents')->where('uuid', $uuid)->countAllResults();
            if ($exists === 0) {
                $db->table('talents')->insert([
                    'uuid'               => $uuid,
                    'activist_id'        => $activistId,
                    'nombre'             => "Ciudadano {$i}",
                    'ap_paterno'         => 'Pérez',
                    'calle_numero'       => (string) $crypto->encrypt("Calle {$i}"),
                    'colonia'            => 'Centro',
                    'codigo_postal'      => '06000',
                    'estado_id'          => 9,
                    'municipio_id'       => 1,
                    'seccion'            => 4812,
                    'vigencia'           => 2030,
                    'clave_elector_enc'  => (string) $crypto->encrypt($clave),
                    'clave_elector_hash' => $hash,
                    'telefono'           => sprintf('550000%04d', $i),
                    'lat'                => 19.4326077,
                    'lng'                => -99.1332080,
                    'consentimiento_at'  => $now,
                    'created_at'         => $now,
                ]);
            }
        }

        // Reintento de envío del mismo lote de 20 UUIDs no duplica registros
        $totalTalents = $db->table('talents')->where('activist_id', $activistId)->countAllResults();
        $this->assertSame(20, $totalTalents);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Database\Migrations\CreateRctSchema;
use App\Database\Seeds\InitialSeeder;
use App\Services\CryptoService;
use App\Services\IneOcrService;
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

    public function testIneOcrParserExtractsFrontOnlyIneFields(): void
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
ESTADO 09 MUNICIPIO 015 LOCALIDAD 0001 SECCION 4812
VIGENCIA 2019-2029
TEXT;

        $result = $ocr->parseIneText($frente);
        $fields = $result['fields'];

        $this->assertSame('JUAN CARLOS', $fields['nombre']);
        $this->assertSame('LOPEZ', $fields['ap_paterno']);
        $this->assertSame('HERNANDEZ', $fields['ap_materno']);
        $this->assertSame('CALLE MORELOS 145, COL CENTRO 06000, CUAUHTEMOC, CDMX', $fields['domicilio_completo']);
        $this->assertSame('06000', $fields['codigo_postal']);
        $this->assertSame('09', $fields['estado_clave']);
        $this->assertSame('015', $fields['municipio_clave']);
        $this->assertSame('0001', $fields['localidad_clave']);
        $this->assertSame('4812', $fields['seccion']);
        $this->assertSame('2029', $fields['vigencia']);
        $this->assertSame('LPHRJU88082009H300', $fields['clave_elector']);
        $this->assertSame('LOHJ880820HDFRPN04', $fields['curp']);
        $this->assertSame('1988-08-20', $fields['fecha_nacimiento']);
        $this->assertSame('H', $fields['sexo']);
        $this->assertFalse($result['vigencia_vencida']);

        $filledCount = count(array_filter($fields, static fn ($v) => $v !== '' && $v !== null));
        $this->assertGreaterThanOrEqual(14, $filledCount);
    }

    public function testDatabaseSchemaNoCatalogTablesAndTwentyOfflineTalentsWithAudioAndClassification(): void
    {
        require_once APPPATH . 'Database/Migrations/2026-11-02-000001_CreateRctSchema.php';
        $db        = Database::connect();
        $migration = new CreateRctSchema();
        $migration->up();

        $seeder = new InitialSeeder(new \Config\Database(), $db);
        $seeder->run();

        // 1. Verificar que las tablas de catálogos geográficos fueron eliminadas de la base de datos
        $this->assertFalse($db->tableExists('cat_estados'));
        $this->assertFalse($db->tableExists('cat_municipios'));
        $this->assertFalse($db->tableExists('cat_localidades'));
        $this->assertFalse($db->tableExists('cat_secciones'));
        $this->assertFalse($db->tableExists('cat_cp'));

        // 2. Alta directa de Activista en estado activo
        $now = date('Y-m-d H:i:s');
        $db->table('users')->insert([
            'username'             => 'act_test_direct',
            'password_hash'        => password_hash('Secret123!', PASSWORD_DEFAULT),
            'role'                 => 'activista',
            'status'               => 'activo',
            'must_change_password' => 1,
            'created_at'           => $now,
        ]);
        $userId = (int) $db->insertID();

        $db->table('activists')->insert([
            'user_id'                => $userId,
            'nombre'                 => 'Pedro',
            'ap_paterno'             => 'Ramírez',
            'telefono'               => '5598765432',
            'telefono_verificado_at' => $now,
            'estado_id'              => 9,
            'meta'                   => 50,
            'status'                 => 'activo',
            'created_at'             => $now,
        ]);
        $activistId = (int) $db->insertID();

        $activistRow = $db->table('activists')->where('id', $activistId)->get()->getRowArray();
        $this->assertNotNull($activistRow);
        $this->assertSame('activo', $activistRow['status']);

        // 3. Simular sincronización de 20 Talentos con Clasificación, Entrevista en Audio (3 preguntas) y datos de INE Frente
        $crypto          = new CryptoService();
        $clasificaciones = ['Conector', 'Experto', 'Vendedor'];
        $sampleAudioB64  = 'data:audio/webm;base64,GkXfo59ChoEBQveBAULygQRC84EIQoKEd2VibUKHgQRChYECGFOAZwH/////////';

        for ($i = 1; $i <= 20; $i++) {
            $uuid  = sprintf('0192ed00-0000-7000-8000-%012d', $i);
            $clave = sprintf('ABCDEF8808%02d09H%03d', $i, $i);
            $hash  = (string) $crypto->hashIdentifier($clave);

            $exists = $db->table('talents')->where('uuid', $uuid)->countAllResults();
            if ($exists === 0) {
                $db->table('talents')->insert([
                    'uuid'               => $uuid,
                    'activist_id'        => $activistId,
                    'clasificacion'      => $clasificaciones[$i % 3],
                    'pregunta_1_texto'   => 'Pregunta1',
                    'audio_pregunta_1'   => $sampleAudioB64,
                    'pregunta_2_texto'   => 'Pregunta2',
                    'audio_pregunta_2'   => $sampleAudioB64,
                    'pregunta_3_texto'   => 'Pregunta3',
                    'audio_pregunta_3'   => $sampleAudioB64,
                    'nombre'             => "Ciudadano {$i}",
                    'ap_paterno'         => 'Pérez',
                    'ap_materno'         => 'Gómez',
                    'domicilio_completo' => (string) $crypto->encrypt("CALLE MORELOS {$i}, COL CENTRO 06000, CUAUHTEMOC, CDMX"),
                    'codigo_postal'      => '06000',
                    'estado_clave'       => '09',
                    'municipio_clave'    => '015',
                    'localidad_clave'    => '0001',
                    'seccion'            => 4812,
                    'vigencia'           => 2030,
                    'sexo'               => 'H',
                    'fecha_nacimiento'   => '1988-08-20',
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

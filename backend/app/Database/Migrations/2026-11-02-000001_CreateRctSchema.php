<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migración principal del esquema de Red Ciudadana de Talentos.
 * Compatible con MariaDB 10.5 (sin funciones exclusivas de MySQL 8; coordenadas en DECIMAL(10,7) indexadas).
 * Sin tablas de catálogos geográficos (cat_cp, cat_estados, cat_municipios, cat_localidades, cat_secciones).
 */
class CreateRctSchema extends Migration
{
    public function up(): void
    {
        /** @var \CodeIgniter\Database\BaseConnection<object, object> $db */
        $db = $this->db;

        // Eliminar tablas de catálogos si existían previamente
        foreach (['cat_cp', 'cat_secciones', 'cat_localidades', 'cat_municipios', 'cat_estados'] as $oldCatTable) {
            if ($db->tableExists($oldCatTable)) {
                $this->forge->dropTable($oldCatTable, true);
            }
        }

        // 1. users
        if ($db->tableExists('users') && ! $db->fieldExists('password_hash', 'users')) {
            $this->forge->dropTable('users', true);
        }
        $this->forge->addField([
            'id'                   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'username'             => ['type' => 'VARCHAR', 'constraint' => 80],
            'email'                => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'password_hash'        => ['type' => 'VARCHAR', 'constraint' => 255],
            'role'                 => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'activista'], // superadmin, consulta, activista
            'status'               => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'activo'],
            'must_change_password' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'last_login_at'        => ['type' => 'DATETIME', 'null' => true],
            'created_at'           => ['type' => 'DATETIME', 'null' => true],
            'updated_at'           => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'           => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('username');
        $this->forge->createTable('users', true);

        // 2. auth_tokens (Bearer tokens 30 días)
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'user_id'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'device_uuid'  => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'token_hash'   => ['type' => 'CHAR', 'constraint' => 64],
            'name'         => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => 'api-token'],
            'expires_at'   => ['type' => 'DATETIME'],
            'last_used_at' => ['type' => 'DATETIME', 'null' => true],
            'revoked_at'   => ['type' => 'DATETIME', 'null' => true],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('token_hash');
        $this->forge->addKey('user_id');
        $this->forge->createTable('auth_tokens', true);

        // 3. activists
        $this->forge->addField([
            'id'                     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'user_id'                => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'nombre'                 => ['type' => 'VARCHAR', 'constraint' => 100],
            'ap_paterno'             => ['type' => 'VARCHAR', 'constraint' => 100],
            'ap_materno'             => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'telefono'               => ['type' => 'CHAR', 'constraint' => 10],
            'telefono_verificado_at' => ['type' => 'DATETIME', 'null' => true],
            'estado_id'              => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 9],
            'municipio_id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'meta'                   => ['type' => 'INT', 'constraint' => 11, 'default' => 50],
            'fecha_limite'           => ['type' => 'DATE', 'null' => true],
            'status'                 => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'activo'],
            'created_at'             => ['type' => 'DATETIME', 'null' => true],
            'updated_at'             => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'             => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('telefono');
        $this->forge->addKey('user_id');
        $this->forge->addKey('estado_id');
        $this->forge->createTable('activists', true);

        // 4. talents
        if ($db->tableExists('talents') && ! $db->fieldExists('clasificacion', 'talents')) {
            $this->forge->dropTable('talents', true);
        }
        $this->forge->addField([
            'id'                 => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'uuid'               => ['type' => 'CHAR', 'constraint' => 36],
            'activist_id'        => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'clasificacion'      => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'Conector'], // Conector, Experto, Vendedor
            'pregunta_1_texto'   => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'audio_pregunta_1'   => ['type' => 'LONGTEXT', 'null' => true], // Audio grabado respuesta 1 (Data URI / Base64 en BD)
            'pregunta_2_texto'   => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'audio_pregunta_2'   => ['type' => 'LONGTEXT', 'null' => true], // Audio grabado respuesta 2 (Data URI / Base64 en BD)
            'pregunta_3_texto'   => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'audio_pregunta_3'   => ['type' => 'LONGTEXT', 'null' => true], // Audio grabado respuesta 3 (Data URI / Base64 en BD)
            'nombre'             => ['type' => 'VARCHAR', 'constraint' => 100],
            'ap_paterno'         => ['type' => 'VARCHAR', 'constraint' => 100],
            'ap_materno'         => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'domicilio_completo' => ['type' => 'TEXT'], // Cifrado en reposo (Domicilio completo como aparece en la credencial)
            'codigo_postal'      => ['type' => 'CHAR', 'constraint' => 5], // Extraído del domicilio de la credencial
            'estado_clave'       => ['type' => 'VARCHAR', 'constraint' => 10], // Clave numérica del estado en la INE (ej. 09)
            'municipio_clave'    => ['type' => 'VARCHAR', 'constraint' => 10], // Clave numérica del municipio en la INE (ej. 001)
            'localidad_clave'    => ['type' => 'VARCHAR', 'constraint' => 10], // Clave numérica de la localidad en la INE (ej. 0001)
            'seccion'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true], // Clave numérica de la sección
            'vigencia'           => ['type' => 'SMALLINT', 'constraint' => 4, 'unsigned' => true],
            'anio_registro'      => ['type' => 'SMALLINT', 'constraint' => 4, 'unsigned' => true, 'null' => true],
            'clave_elector_enc'  => ['type' => 'TEXT'], // Cifrado en reposo
            'clave_elector_hash' => ['type' => 'CHAR', 'constraint' => 64], // SHA-256 + pepper
            'curp_enc'           => ['type' => 'TEXT', 'null' => true], // Cifrado en reposo
            'curp_hash'          => ['type' => 'CHAR', 'constraint' => 64, 'null' => true], // SHA-256 + pepper
            'fecha_nacimiento'   => ['type' => 'DATE', 'null' => true],
            'sexo'               => ['type' => 'CHAR', 'constraint' => 1, 'null' => true], // H, M
            'telefono'           => ['type' => 'CHAR', 'constraint' => 10],
            'email'              => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'whatsapp'           => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'lat'                => ['type' => 'DECIMAL', 'constraint' => '10,7'],
            'lng'                => ['type' => 'DECIMAL', 'constraint' => '10,7'],
            'geocode_precision'  => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'ROOFTOP'],
            'geocode_source'     => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'google'],
            'captura_lat'        => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true],
            'captura_lng'        => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true],
            'captura_at'         => ['type' => 'DATETIME', 'null' => true],
            'ocr_confianza'      => ['type' => 'LONGTEXT', 'null' => true], // JSON
            'consentimiento_at'  => ['type' => 'DATETIME'],
            'aviso_version'      => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => '1.0'],
            'validacion'         => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'pendiente'], // pendiente, validado, observado, rechazado
            'observaciones'      => ['type' => 'TEXT', 'null' => true],
            'created_at'         => ['type' => 'DATETIME', 'null' => true],
            'updated_at'         => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'         => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('uuid');
        $this->forge->addUniqueKey('clave_elector_hash');
        $this->forge->addKey('curp_hash');
        $this->forge->addKey('telefono');
        $this->forge->addKey('activist_id');
        $this->forge->addKey('clasificacion');
        $this->forge->addKey(['estado_clave', 'municipio_clave', 'seccion']);
        $this->forge->addKey('codigo_postal');
        $this->forge->addKey('validacion');
        $this->forge->addKey(['lat', 'lng']);
        $this->forge->createTable('talents', true);

        // 5. talent_files
        $this->forge->addField([
            'id'         => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'talent_id'  => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'tipo'       => ['type' => 'VARCHAR', 'constraint' => 20], // selfie, ine_frente, firma
            'path'       => ['type' => 'VARCHAR', 'constraint' => 255],
            'mime'       => ['type' => 'VARCHAR', 'constraint' => 50],
            'size'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'sha256'     => ['type' => 'CHAR', 'constraint' => 64],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('talent_id');
        $this->forge->createTable('talent_files', true);

        // 6. devices
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'activist_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'device_uuid' => ['type' => 'VARCHAR', 'constraint' => 64],
            'user_agent'  => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'last_sync_at'=> ['type' => 'DATETIME', 'null' => true],
            'revoked_at'  => ['type' => 'DATETIME', 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('activist_id');
        $this->forge->addKey('device_uuid');
        $this->forge->createTable('devices', true);

        // 7. sync_log
        $this->forge->addField([
            'id'          => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'activist_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'device_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'talent_uuid' => ['type' => 'CHAR', 'constraint' => 36],
            'status'      => ['type' => 'VARCHAR', 'constraint' => 20],
            'error'       => ['type' => 'TEXT', 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('talent_uuid');
        $this->forge->addKey('activist_id');
        $this->forge->createTable('sync_log', true);

        // 8. geocode_cache
        $this->forge->addField([
            'id'                => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'query_hash'        => ['type' => 'CHAR', 'constraint' => 64],
            'query_text'        => ['type' => 'VARCHAR', 'constraint' => 255],
            'lat'               => ['type' => 'DECIMAL', 'constraint' => '10,7'],
            'lng'               => ['type' => 'DECIMAL', 'constraint' => '10,7'],
            'precision_type'    => ['type' => 'VARCHAR', 'constraint' => 30],
            'formatted_address' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at'        => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('query_hash');
        $this->forge->createTable('geocode_cache', true);

        // 9. audit_log
        $this->forge->addField([
            'id'         => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'user_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'action'     => ['type' => 'VARCHAR', 'constraint' => 80],
            'entity'     => ['type' => 'VARCHAR', 'constraint' => 50],
            'entity_id'  => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'payload'    => ['type' => 'LONGTEXT', 'null' => true], // JSON
            'ip'         => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'user_agent' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('user_id');
        $this->forge->addKey('action');
        $this->forge->createTable('audit_log', true);

        // 10. exports
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'user_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'filtros'    => ['type' => 'LONGTEXT', 'null' => true], // JSON
            'formato'    => ['type' => 'VARCHAR', 'constraint' => 15], // xlsx, csv, pdf, kml, geojson
            'path'       => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'total_rows' => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'status'     => ['type' => 'VARCHAR', 'constraint' => 25, 'default' => 'completado'],
            'expires_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('user_id');
        $this->forge->createTable('exports', true);

        // 11. settings
        if ($db->tableExists('settings') && ! $db->fieldExists('setting_key', 'settings')) {
            $this->forge->dropTable('settings', true);
        }
        $this->forge->addField([
            'setting_key'   => ['type' => 'VARCHAR', 'constraint' => 100],
            'setting_value' => ['type' => 'LONGTEXT', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('setting_key', true);
        $this->forge->createTable('settings', true);
    }

    public function down(): void
    {
        $tables = [
            'settings',
            'exports',
            'audit_log',
            'geocode_cache',
            'sync_log',
            'devices',
            'talent_files',
            'talents',
            'activists',
            'auth_tokens',
            'users',
        ];

        foreach ($tables as $table) {
            $this->forge->dropTable($table, true);
        }
    }
}

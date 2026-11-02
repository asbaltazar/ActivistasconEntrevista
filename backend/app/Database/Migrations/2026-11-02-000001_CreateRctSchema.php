<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateRctSchema extends Migration
{
    public function up(): void
    {
        // 1. cat_estados
        $this->forge->addField([
            'id'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'clave'  => ['type' => 'CHAR', 'constraint' => 2],
            'nombre' => ['type' => 'VARCHAR', 'constraint' => 100],
            'abrev'  => ['type' => 'VARCHAR', 'constraint' => 10],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('clave');
        $this->forge->createTable('cat_estados', true);

        // 2. cat_municipios
        $this->forge->addField([
            'id'        => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'estado_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'clave'     => ['type' => 'CHAR', 'constraint' => 3],
            'nombre'    => ['type' => 'VARCHAR', 'constraint' => 150],
            'lat'       => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true],
            'lng'       => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('estado_id');
        $this->forge->createTable('cat_municipios', true);

        // 3. cat_localidades
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'municipio_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'clave'        => ['type' => 'CHAR', 'constraint' => 4],
            'nombre'       => ['type' => 'VARCHAR', 'constraint' => 150],
            'lat'          => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true],
            'lng'          => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('municipio_id');
        $this->forge->createTable('cat_localidades', true);

        // 4. cat_secciones
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'estado_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'municipio_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'seccion'      => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true],
            'tipo'         => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'urbana'],
            'lat'          => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true],
            'lng'          => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true],
            'geojson'      => ['type' => 'LONGTEXT', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['estado_id', 'seccion']);
        $this->forge->addKey('municipio_id');
        $this->forge->createTable('cat_secciones', true);

        // 5. cat_cp (SEPOMEX)
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'codigo_postal'=> ['type' => 'CHAR', 'constraint' => 5],
            'asentamiento' => ['type' => 'VARCHAR', 'constraint' => 150],
            'tipo_asenta'  => ['type' => 'VARCHAR', 'constraint' => 60, 'default' => 'Colonia'],
            'estado_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'municipio_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'ciudad'       => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'lat'          => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true],
            'lng'          => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('codigo_postal');
        $this->forge->addKey('municipio_id');
        $this->forge->createTable('cat_cp', true);

        // 6. users
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

        // 7. auth_tokens (Bearer tokens 30 días)
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

        // 8. activists
        $this->forge->addField([
            'id'                     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'user_id'                => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'nombre'                 => ['type' => 'VARCHAR', 'constraint' => 100],
            'ap_paterno'             => ['type' => 'VARCHAR', 'constraint' => 100],
            'ap_materno'             => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'telefono'               => ['type' => 'CHAR', 'constraint' => 10],
            'telefono_verificado_at' => ['type' => 'DATETIME', 'null' => true],
            'estado_id'              => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'municipio_id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'meta'                   => ['type' => 'INT', 'constraint' => 11, 'default' => 50],
            'fecha_limite'           => ['type' => 'DATE', 'null' => true],
            'status'                 => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'pendiente_verificacion'], // pendiente_verificacion, activo, suspendido
            'created_at'             => ['type' => 'DATETIME', 'null' => true],
            'updated_at'             => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'             => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('telefono');
        $this->forge->addKey('user_id');
        $this->forge->addKey('estado_id');
        $this->forge->createTable('activists', true);

        // 9. activist_otps (control de intentos, vigencia 10 min y cooldown 60s)
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'activist_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'telefono'    => ['type' => 'CHAR', 'constraint' => 10],
            'code_hash'   => ['type' => 'VARCHAR', 'constraint' => 255],
            'provider_sid'=> ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'attempts'    => ['type' => 'TINYINT', 'constraint' => 2, 'default' => 0],
            'expires_at'  => ['type' => 'DATETIME'],
            'verified_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('activist_id');
        $this->forge->createTable('activist_otps', true);

        // 10. talents
        $this->forge->addField([
            'id'                 => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'uuid'               => ['type' => 'CHAR', 'constraint' => 36],
            'activist_id'        => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'nombre'             => ['type' => 'VARCHAR', 'constraint' => 100],
            'ap_paterno'         => ['type' => 'VARCHAR', 'constraint' => 100],
            'ap_materno'         => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'calle_numero'       => ['type' => 'TEXT'], // Cifrado en reposo (Encryption CI4)
            'colonia'            => ['type' => 'VARCHAR', 'constraint' => 150],
            'codigo_postal'      => ['type' => 'CHAR', 'constraint' => 5],
            'estado_id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'municipio_id'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'localidad_id'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'localidad_texto'    => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'seccion'            => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true],
            'vigencia'           => ['type' => 'SMALLINT', 'constraint' => 4, 'unsigned' => true],
            'anio_registro'      => ['type' => 'SMALLINT', 'constraint' => 4, 'unsigned' => true, 'null' => true],
            'clave_elector_enc'  => ['type' => 'TEXT'],
            'clave_elector_hash' => ['type' => 'CHAR', 'constraint' => 64],
            'curp_enc'           => ['type' => 'TEXT', 'null' => true],
            'curp_hash'          => ['type' => 'CHAR', 'constraint' => 64, 'null' => true],
            'fecha_nacimiento'   => ['type' => 'DATE', 'null' => true],
            'sexo'               => ['type' => 'CHAR', 'constraint' => 1, 'null' => true], // H / M
            'telefono'           => ['type' => 'CHAR', 'constraint' => 10],
            'email'              => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'whatsapp'           => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'lat'                => ['type' => 'DECIMAL', 'constraint' => '10,7'],
            'lng'                => ['type' => 'DECIMAL', 'constraint' => '10,7'],
            'geocode_precision'  => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'ROOFTOP'],
            'geocode_source'     => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'google'], // google, manual, gps
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
        $this->forge->addKey(['lat', 'lng']);
        $this->forge->addKey('seccion');
        $this->forge->addKey('municipio_id');
        $this->forge->addKey('activist_id');
        $this->forge->addKey('curp_hash');
        $this->forge->addKey('telefono');
        $this->forge->createTable('talents', true);

        // 11. talent_files
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'talent_id'  => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'tipo'       => ['type' => 'VARCHAR', 'constraint' => 20], // selfie, ine_frente, ine_reverso, firma
            'path'       => ['type' => 'VARCHAR', 'constraint' => 255],
            'mime'       => ['type' => 'VARCHAR', 'constraint' => 60],
            'size'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'sha256'     => ['type' => 'CHAR', 'constraint' => 64],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('talent_id');
        $this->forge->createTable('talent_files', true);

        // 12. devices
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'activist_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'device_uuid'  => ['type' => 'VARCHAR', 'constraint' => 64],
            'user_agent'   => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'last_sync_at' => ['type' => 'DATETIME', 'null' => true],
            'revoked_at'   => ['type' => 'DATETIME', 'null' => true],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['activist_id', 'device_uuid']);
        $this->forge->createTable('devices', true);

        // 13. sync_log
        $this->forge->addField([
            'id'          => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'activist_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'device_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'talent_uuid' => ['type' => 'CHAR', 'constraint' => 36],
            'status'      => ['type' => 'VARCHAR', 'constraint' => 30],
            'error'       => ['type' => 'TEXT', 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('activist_id');
        $this->forge->addKey('talent_uuid');
        $this->forge->createTable('sync_log', true);

        // 14. sms_campaigns
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'user_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'nombre'     => ['type' => 'VARCHAR', 'constraint' => 150],
            'plantilla'  => ['type' => 'VARCHAR', 'constraint' => 300],
            'filtros'    => ['type' => 'LONGTEXT', 'null' => true], // JSON
            'total'      => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'enviados'   => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'status'     => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'pendiente'], // pendiente, procesando, completada, error
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('sms_campaigns', true);

        // 15. sms_messages
        $this->forge->addField([
            'id'           => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'talent_id'    => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'telefono'     => ['type' => 'CHAR', 'constraint' => 10],
            'plantilla'    => ['type' => 'VARCHAR', 'constraint' => 80, 'default' => 'bienvenida'],
            'cuerpo'       => ['type' => 'VARCHAR', 'constraint' => 320],
            'campaign_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'provider_id'  => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'status'       => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'queued'], // queued, sent, delivered, failed, optout
            'error'        => ['type' => 'TEXT', 'null' => true],
            'sent_at'      => ['type' => 'DATETIME', 'null' => true],
            'delivered_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('talent_id');
        $this->forge->addKey('campaign_id');
        $this->forge->addKey('provider_id');
        $this->forge->createTable('sms_messages', true);

        // 16. sms_optouts
        $this->forge->addField([
            'telefono'   => ['type' => 'CHAR', 'constraint' => 10],
            'motivo'     => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'BAJA'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('telefono', true);
        $this->forge->createTable('sms_optouts', true);

        // 17. geocode_cache
        $this->forge->addField([
            'id'                => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'query_hash'        => ['type' => 'CHAR', 'constraint' => 64],
            'query_text'        => ['type' => 'VARCHAR', 'constraint' => 255],
            'lat'               => ['type' => 'DECIMAL', 'constraint' => '10,7'],
            'lng'               => ['type' => 'DECIMAL', 'constraint' => '10,7'],
            'precision_type'    => ['type' => 'VARCHAR', 'constraint' => 40],
            'formatted_address' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at'        => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('query_hash');
        $this->forge->createTable('geocode_cache', true);

        // 18. audit_log
        $this->forge->addField([
            'id'         => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'user_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'action'     => ['type' => 'VARCHAR', 'constraint' => 80],
            'entity'     => ['type' => 'VARCHAR', 'constraint' => 60],
            'entity_id'  => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'payload'    => ['type' => 'LONGTEXT', 'null' => true], // JSON
            'ip'         => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('user_id');
        $this->forge->addKey('action');
        $this->forge->createTable('audit_log', true);

        // 19. exports
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

        // 20. settings
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
            'settings', 'exports', 'audit_log', 'geocode_cache',
            'sms_optouts', 'sms_messages', 'sms_campaigns',
            'sync_log', 'devices', 'talent_files', 'talents',
            'activist_otps', 'activists', 'auth_tokens', 'users',
            'cat_cp', 'cat_secciones', 'cat_localidades', 'cat_municipios', 'cat_estados',
        ];
        foreach ($tables as $table) {
            $this->forge->dropTable($table, true);
        }
    }
}


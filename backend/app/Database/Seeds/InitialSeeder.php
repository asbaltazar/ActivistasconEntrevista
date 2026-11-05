<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Seeder inicial para Red Ciudadana de Talentos:
 * - Parámetros del sistema (Aviso de Privacidad LFPDPPP, metas por clasificación: 50 Conector, 50 Experto, 50 Vendedor)
 * - Usuarios iniciales: SuperAdmin, Consulta y Activista de demostración
 * (Las tablas de catálogos geográficos fueron eliminadas por requerimiento del cliente).
 */
class InitialSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        // 1. Configuración inicial (settings)
        $settings = [
            [
                'setting_key'   => 'aviso_privacidad_version',
                'setting_value' => '1.0',
                'updated_at'    => $now,
            ],
            [
                'setting_key'   => 'aviso_privacidad_texto',
                'setting_value' => 'Red Ciudadana de Talentos es responsable del tratamiento de sus datos personales (identificación, domicilio, datos de credencial INE, fotografía facial y entrevista en audio) con la finalidad de verificar su identidad, clasificar su perfil de talento y ubicar su sección electoral. Usted puede ejercer sus derechos ARCO conforme a la LFPDPPP. Consulte el aviso integral en activistas.solytecmx.com/aviso',
                'updated_at'    => $now,
            ],
            [
                'setting_key'   => 'meta_global_default',
                'setting_value' => '2500',
                'updated_at'    => $now,
            ],
            [
                'setting_key'   => 'meta_activista_default',
                'setting_value' => '150',
                'updated_at'    => $now,
            ],
            [
                'setting_key'   => 'meta_conector_default',
                'setting_value' => '50',
                'updated_at'    => $now,
            ],
            [
                'setting_key'   => 'meta_experto_default',
                'setting_value' => '50',
                'updated_at'    => $now,
            ],
            [
                'setting_key'   => 'meta_vendedor_default',
                'setting_value' => '50',
                'updated_at'    => $now,
            ],
        ];

        foreach ($settings as $row) {
            $exists = $this->db->table('settings')->where('setting_key', $row['setting_key'])->countAllResults();
            if ($exists === 0) {
                $this->db->table('settings')->insert($row);
            } else {
                $this->db->table('settings')->where('setting_key', $row['setting_key'])->update([
                    'setting_value' => $row['setting_value'],
                    'updated_at'    => $now,
                ]);
            }
        }

        // 2. Usuarios iniciales
        if ($this->db->table('users')->countAllResults() === 0) {
            // Super Admin
            $this->db->table('users')->insert([
                'id'                   => 1,
                'username'             => 'superadmin',
                'email'                => 'admin@solytecmx.com',
                'password_hash'        => password_hash('Admin2026!', PASSWORD_DEFAULT),
                'role'                 => 'superadmin',
                'status'               => 'activo',
                'must_change_password' => 0,
                'created_at'           => $now,
                'updated_at'           => $now,
            ]);

            // Usuario Consulta (Solo lectura + exportación)
            $this->db->table('users')->insert([
                'id'                   => 2,
                'username'             => 'consulta',
                'email'                => 'consulta@solytecmx.com',
                'password_hash'        => password_hash('Consulta2026!', PASSWORD_DEFAULT),
                'role'                 => 'consulta',
                'status'               => 'activo',
                'must_change_password' => 0,
                'created_at'           => $now,
                'updated_at'           => $now,
            ]);

            // Activista Demo
            $this->db->table('users')->insert([
                'id'                   => 3,
                'username'             => 'activista1',
                'email'                => 'activista1@solytecmx.com',
                'password_hash'        => password_hash('Activista2026!', PASSWORD_DEFAULT),
                'role'                 => 'activista',
                'status'               => 'activo',
                'must_change_password' => 0,
                'created_at'           => $now,
                'updated_at'           => $now,
            ]);

            $this->db->table('activists')->insert([
                'id'                     => 1,
                'user_id'                => 3,
                'nombre'                 => 'Carlos',
                'ap_paterno'             => 'Hernández',
                'ap_materno'             => 'López',
                'telefono'               => '5512345678',
                'domicilio'              => 'Av. Venustiano Carranza 450, Centro, San Luis Potosí',
                'telefono_verificado_at' => $now,
                'estado_id'              => 24,
                'municipio_id'           => 28,
                'region_trabajo'         => 1,
                'meta'                   => 150,
                'meta_conector'          => 50,
                'meta_experto'           => 50,
                'meta_vendedor'          => 50,
                'fecha_limite'           => date('Y-m-d', strtotime('+30 days')),
                'status'                 => 'activo',
                'created_at'             => $now,
                'updated_at'             => $now,
            ]);
        }
    }
}

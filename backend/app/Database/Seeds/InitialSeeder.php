<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class InitialSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        // 1. 32 Estados de México
        $estados = [
            ['id' => 1,  'clave' => '01', 'nombre' => 'Aguascalientes',                  'abrev' => 'AGS'],
            ['id' => 2,  'clave' => '02', 'nombre' => 'Baja California',                 'abrev' => 'BC'],
            ['id' => 3,  'clave' => '03', 'nombre' => 'Baja California Sur',             'abrev' => 'BCS'],
            ['id' => 4,  'clave' => '04', 'nombre' => 'Campeche',                        'abrev' => 'CAMP'],
            ['id' => 5,  'clave' => '05', 'nombre' => 'Coahuila de Zaragoza',            'abrev' => 'COAH'],
            ['id' => 6,  'clave' => '06', 'nombre' => 'Colima',                          'abrev' => 'COL'],
            ['id' => 7,  'clave' => '07', 'nombre' => 'Chiapas',                         'abrev' => 'CHIS'],
            ['id' => 8,  'clave' => '08', 'nombre' => 'Chihuahua',                       'abrev' => 'CHIH'],
            ['id' => 9,  'clave' => '09', 'nombre' => 'Ciudad de México',                'abrev' => 'CDMX'],
            ['id' => 10, 'clave' => '10', 'nombre' => 'Durango',                         'abrev' => 'DGO'],
            ['id' => 11, 'clave' => '11', 'nombre' => 'Guanajuato',                      'abrev' => 'GTO'],
            ['id' => 12, 'clave' => '12', 'nombre' => 'Guerrero',                        'abrev' => 'GRO'],
            ['id' => 13, 'clave' => '13', 'nombre' => 'Hidalgo',                         'abrev' => 'HGO'],
            ['id' => 14, 'clave' => '14', 'nombre' => 'Jalisco',                         'abrev' => 'JAL'],
            ['id' => 15, 'clave' => '15', 'nombre' => 'México',                          'abrev' => 'MEX'],
            ['id' => 16, 'clave' => '16', 'nombre' => 'Michoacán de Ocampo',             'abrev' => 'MICH'],
            ['id' => 17, 'clave' => '17', 'nombre' => 'Morelos',                         'abrev' => 'MOR'],
            ['id' => 18, 'clave' => '18', 'nombre' => 'Nayarit',                         'abrev' => 'NAY'],
            ['id' => 19, 'clave' => '19', 'nombre' => 'Nuevo León',                      'abrev' => 'NL'],
            ['id' => 20, 'clave' => '20', 'nombre' => 'Oaxaca',                          'abrev' => 'OAX'],
            ['id' => 21, 'clave' => '21', 'nombre' => 'Puebla',                          'abrev' => 'PUE'],
            ['id' => 22, 'clave' => '22', 'nombre' => 'Querétaro',                       'abrev' => 'QRO'],
            ['id' => 23, 'clave' => '23', 'nombre' => 'Quintana Roo',                    'abrev' => 'QROO'],
            ['id' => 24, 'clave' => '24', 'nombre' => 'San Luis Potosí',                 'abrev' => 'SLP'],
            ['id' => 25, 'clave' => '25', 'nombre' => 'Sinaloa',                         'abrev' => 'SIN'],
            ['id' => 26, 'clave' => '26', 'nombre' => 'Sonora',                          'abrev' => 'SON'],
            ['id' => 27, 'clave' => '27', 'nombre' => 'Tabasco',                         'abrev' => 'TAB'],
            ['id' => 28, 'clave' => '28', 'nombre' => 'Tamaulipas',                      'abrev' => 'TAMS'],
            ['id' => 29, 'clave' => '29', 'nombre' => 'Tlaxcala',                        'abrev' => 'TLAX'],
            ['id' => 30, 'clave' => '30', 'nombre' => 'Veracruz de Ignacio de la Llave', 'abrev' => 'VER'],
            ['id' => 31, 'clave' => '31', 'nombre' => 'Yucatán',                         'abrev' => 'YUC'],
            ['id' => 32, 'clave' => '32', 'nombre' => 'Zacatecas',                       'abrev' => 'ZAC'],
        ];

        if ($this->db->table('cat_estados')->countAllResults() === 0) {
            $this->db->table('cat_estados')->insertBatch($estados);
        }

        // 2. Municipios representativos
        $municipios = [
            ['id' => 1,  'estado_id' => 9,  'clave' => '015', 'nombre' => 'Cuauhtémoc',           'lat' => 19.4326077, 'lng' => -99.1332080],
            ['id' => 2,  'estado_id' => 9,  'clave' => '014', 'nombre' => 'Benito Juárez',        'lat' => 19.3983990, 'lng' => -99.1576610],
            ['id' => 3,  'estado_id' => 9,  'clave' => '003', 'nombre' => 'Coyoacán',             'lat' => 19.3467000, 'lng' => -99.1617000],
            ['id' => 4,  'estado_id' => 14, 'clave' => '039', 'nombre' => 'Guadalajara',          'lat' => 20.6596988, 'lng' => -103.3496092],
            ['id' => 5,  'estado_id' => 14, 'clave' => '120', 'nombre' => 'Zapopan',              'lat' => 20.7213950, 'lng' => -103.3887920],
            ['id' => 6,  'estado_id' => 19, 'clave' => '039', 'nombre' => 'Monterrey',            'lat' => 25.6866142, 'lng' => -100.3161126],
            ['id' => 7,  'estado_id' => 6,  'clave' => '002', 'nombre' => 'Colima',               'lat' => 19.2452340, 'lng' => -103.7240870],
            ['id' => 8,  'estado_id' => 6,  'clave' => '007', 'nombre' => 'Manzanillo',           'lat' => 19.0544000, 'lng' => -104.3188000],
            ['id' => 9,  'estado_id' => 15, 'clave' => '106', 'nombre' => 'Toluca',               'lat' => 19.2826098, 'lng' => -99.6556653],
            ['id' => 10, 'estado_id' => 21, 'clave' => '114', 'nombre' => 'Puebla',               'lat' => 19.0414398, 'lng' => -98.2062727],
        ];

        if ($this->db->table('cat_municipios')->countAllResults() === 0) {
            $this->db->table('cat_municipios')->insertBatch($municipios);
        }

        // 3. Localidades
        $localidades = [
            ['id' => 1, 'municipio_id' => 1, 'clave' => '0001', 'nombre' => 'Cuauhtémoc Centro',  'lat' => 19.4326077, 'lng' => -99.1332080],
            ['id' => 2, 'municipio_id' => 2, 'clave' => '0001', 'nombre' => 'Del Valle Centro',   'lat' => 19.3850000, 'lng' => -99.1650000],
            ['id' => 3, 'municipio_id' => 4, 'clave' => '0001', 'nombre' => 'Guadalajara Centro', 'lat' => 20.6596988, 'lng' => -103.3496092],
            ['id' => 4, 'municipio_id' => 6, 'clave' => '0001', 'nombre' => 'Monterrey Centro',   'lat' => 25.6866142, 'lng' => -100.3161126],
            ['id' => 5, 'municipio_id' => 7, 'clave' => '0001', 'nombre' => 'Colima Centro',      'lat' => 19.2452340, 'lng' => -103.7240870],
        ];

        if ($this->db->table('cat_localidades')->countAllResults() === 0) {
            $this->db->table('cat_localidades')->insertBatch($localidades);
        }

        // 4. Secciones electorales con polígonos GeoJSON de muestra
        $secciones = [
            [
                'id' => 1, 'estado_id' => 9, 'municipio_id' => 1, 'seccion' => 4812, 'tipo' => 'urbana',
                'lat' => 19.4326077, 'lng' => -99.1332080,
                'geojson' => json_encode([
                    'type' => 'Polygon',
                    'coordinates' => [[[-99.140, 19.428], [-99.126, 19.428], [-99.126, 19.438], [-99.140, 19.438], [-99.140, 19.428]]],
                ]),
            ],
            [
                'id' => 2, 'estado_id' => 9, 'municipio_id' => 2, 'seccion' => 4120, 'tipo' => 'urbana',
                'lat' => 19.3983990, 'lng' => -99.1576610,
                'geojson' => json_encode([
                    'type' => 'Polygon',
                    'coordinates' => [[[-99.165, 19.392], [-99.150, 19.392], [-99.150, 19.404], [-99.165, 19.404], [-99.165, 19.392]]],
                ]),
            ],
            [
                'id' => 3, 'estado_id' => 14, 'municipio_id' => 4, 'seccion' => 1205, 'tipo' => 'urbana',
                'lat' => 20.6596988, 'lng' => -103.3496092,
                'geojson' => json_encode([
                    'type' => 'Polygon',
                    'coordinates' => [[[-103.356, 20.654], [-103.342, 20.654], [-103.342, 20.665], [-103.356, 20.665], [-103.356, 20.654]]],
                ]),
            ],
            [
                'id' => 4, 'estado_id' => 19, 'municipio_id' => 6, 'seccion' => 1101, 'tipo' => 'urbana',
                'lat' => 25.6866142, 'lng' => -100.3161126,
                'geojson' => json_encode([
                    'type' => 'Polygon',
                    'coordinates' => [[[-100.323, 25.680], [-100.309, 25.680], [-100.309, 25.693], [-100.323, 25.693], [-100.323, 25.680]]],
                ]),
            ],
            [
                'id' => 5, 'estado_id' => 6, 'municipio_id' => 7, 'seccion' => 101, 'tipo' => 'urbana',
                'lat' => 19.2452340, 'lng' => -103.7240870,
                'geojson' => json_encode([
                    'type' => 'Polygon',
                    'coordinates' => [[[-103.732, 19.239], [-103.716, 19.239], [-103.716, 19.251], [-103.732, 19.251], [-103.732, 19.239]]],
                ]),
            ],
        ];

        if ($this->db->table('cat_secciones')->countAllResults() === 0) {
            $this->db->table('cat_secciones')->insertBatch($secciones);
        }

        // 5. Códigos Postales (SEPOMEX)
        $cps = [
            ['codigo_postal' => '06000', 'asentamiento' => 'Centro',             'tipo_asenta' => 'Colonia', 'estado_id' => 9,  'municipio_id' => 1, 'ciudad' => 'Ciudad de México', 'lat' => 19.4326077, 'lng' => -99.1332080],
            ['codigo_postal' => '06700', 'asentamiento' => 'Roma Norte',         'tipo_asenta' => 'Colonia', 'estado_id' => 9,  'municipio_id' => 1, 'ciudad' => 'Ciudad de México', 'lat' => 19.4194000, 'lng' => -99.1625000],
            ['codigo_postal' => '03100', 'asentamiento' => 'Del Valle Centro',   'tipo_asenta' => 'Colonia', 'estado_id' => 9,  'municipio_id' => 2, 'ciudad' => 'Ciudad de México', 'lat' => 19.3850000, 'lng' => -99.1650000],
            ['codigo_postal' => '44100', 'asentamiento' => 'Guadalajara Centro', 'tipo_asenta' => 'Colonia', 'estado_id' => 14, 'municipio_id' => 4, 'ciudad' => 'Guadalajara',      'lat' => 20.6596988, 'lng' => -103.3496092],
            ['codigo_postal' => '64000', 'asentamiento' => 'Monterrey Centro',   'tipo_asenta' => 'Colonia', 'estado_id' => 19, 'municipio_id' => 6, 'ciudad' => 'Monterrey',        'lat' => 25.6866142, 'lng' => -100.3161126],
            ['codigo_postal' => '28000', 'asentamiento' => 'Colima Centro',      'tipo_asenta' => 'Colonia', 'estado_id' => 6,  'municipio_id' => 7, 'ciudad' => 'Colima',           'lat' => 19.2452340, 'lng' => -103.7240870],
        ];

        if ($this->db->table('cat_cp')->countAllResults() === 0) {
            $this->db->table('cat_cp')->insertBatch($cps);
        }

        // 6. Configuración inicial (settings)
        $settings = [
            [
                'setting_key'   => 'aviso_privacidad_version',
                'setting_value' => '1.0',
                'updated_at'    => $now,
            ],
            [
                'setting_key'   => 'aviso_privacidad_texto',
                'setting_value' => 'Red Ciudadana de Talentos es responsable del tratamiento de sus datos personales (identificación, domicilio, datos de credencial INE y fotografía facial) con la finalidad de verificar su identidad, ubicar su sección electoral y enviarle confirmación e información ciudadana vía SMS. Usted puede ejercer sus derechos ARCO o darse de baja enviando BAJA al SMS recibido. Consulte el aviso integral en activistas.solytecmx.com/aviso',
                'updated_at'    => $now,
            ],
            [
                'setting_key'   => 'meta_global_default',
                'setting_value' => '2500',
                'updated_at'    => $now,
            ],
            [
                'setting_key'   => 'meta_activista_default',
                'setting_value' => '50',
                'updated_at'    => $now,
            ],
            [
                'setting_key'   => 'sms_bienvenida_plantilla',
                'setting_value' => 'Hola {nombre}, tu registro en Red Ciudadana (Sec {seccion}) fue exitoso. Aviso: https://activistas.solytecmx.com/aviso Responde BAJA para cancelar.',
                'updated_at'    => $now,
            ],
        ];

        if ($this->db->table('settings')->countAllResults() === 0) {
            $this->db->table('settings')->insertBatch($settings);
        }

        // 7. Usuarios iniciales
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

            // Usuario Consulta
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

            // Activista de demostración (verificado)
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
                'telefono_verificado_at' => $now,
                'estado_id'              => 9,
                'municipio_id'           => 1,
                'meta'                   => 50,
                'fecha_limite'           => date('Y-m-d', strtotime('+30 days')),
                'status'                 => 'activo',
                'created_at'             => $now,
                'updated_at'             => $now,
            ]);
        }
    }
}

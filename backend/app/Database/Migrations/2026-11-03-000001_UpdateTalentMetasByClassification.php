<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Actualiza las metas por Activista a 50 Conector, 50 Experto y 50 Vendedor (150 total)
 * tanto en la tabla `activists` como en la tabla `settings`.
 */
class UpdateTalentMetasByClassification extends Migration
{
    public function up(): void
    {
        /** @var \CodeIgniter\Database\BaseConnection<object, object> $db */
        $db = $this->db;

        if ($db->tableExists('activists')) {
            $fieldsToAdd = [];
            if (! $db->fieldExists('meta_conector', 'activists')) {
                $fieldsToAdd['meta_conector'] = ['type' => 'INT', 'constraint' => 11, 'default' => 50, 'after' => 'meta'];
            }
            if (! $db->fieldExists('meta_experto', 'activists')) {
                $fieldsToAdd['meta_experto'] = ['type' => 'INT', 'constraint' => 11, 'default' => 50, 'after' => 'meta_conector'];
            }
            if (! $db->fieldExists('meta_vendedor', 'activists')) {
                $fieldsToAdd['meta_vendedor'] = ['type' => 'INT', 'constraint' => 11, 'default' => 50, 'after' => 'meta_experto'];
            }
            if (count($fieldsToAdd) > 0) {
                $this->forge->addColumn('activists', $fieldsToAdd);
            }

            // Actualizar activistas con meta anterior de 50 al nuevo esquema de 150 (50 Conector + 50 Experto + 50 Vendedor)
            $db->table('activists')
                ->where('meta', 50)
                ->update([
                    'meta'          => 150,
                    'meta_conector' => 50,
                    'meta_experto'  => 50,
                    'meta_vendedor' => 50,
                ]);
        }

        if ($db->tableExists('settings')) {
            $now          = date('Y-m-d H:i:s');
            $metaSettings = [
                'meta_conector_default'  => '50',
                'meta_experto_default'   => '50',
                'meta_vendedor_default'  => '50',
                'meta_activista_default' => '150',
            ];

            foreach ($metaSettings as $key => $val) {
                $exists = $db->table('settings')->where('setting_key', $key)->countAllResults();
                if ($exists > 0) {
                    $db->table('settings')->where('setting_key', $key)->update([
                        'setting_value' => $val,
                        'updated_at'    => $now,
                    ]);
                } else {
                    $db->table('settings')->insert([
                        'setting_key'   => $key,
                        'setting_value' => $val,
                        'updated_at'    => $now,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        /** @var \CodeIgniter\Database\BaseConnection<object, object> $db */
        $db = $this->db;

        if ($db->tableExists('settings')) {
            $db->table('settings')->whereIn('setting_key', [
                'meta_conector_default',
                'meta_experto_default',
                'meta_vendedor_default',
            ])->delete();
        }
    }
}


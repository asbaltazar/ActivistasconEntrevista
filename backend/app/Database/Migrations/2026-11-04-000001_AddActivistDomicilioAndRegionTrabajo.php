<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Agrega `domicilio` y `region_trabajo` (INT) a la tabla `activists`,
 * y vincula `region_trabajo` (INT) en la tabla `talents` con la región del Activista que lo registró.
 */
class AddActivistDomicilioAndRegionTrabajo extends Migration
{
    public function up(): void
    {
        /** @var \CodeIgniter\Database\BaseConnection<object, object> $db */
        $db = $this->db;

        if ($db->tableExists('activists')) {
            $activistCols = [];
            if (! $db->fieldExists('domicilio', 'activists')) {
                $activistCols['domicilio'] = [
                    'type'       => 'VARCHAR',
                    'constraint' => 300,
                    'null'       => true,
                    'after'      => 'telefono',
                ];
            }
            if (! $db->fieldExists('region_trabajo', 'activists')) {
                $activistCols['region_trabajo'] = [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'default'    => 1,
                    'null'       => false,
                    'after'      => 'municipio_id',
                ];
            }
            if (count($activistCols) > 0) {
                $this->forge->addColumn('activists', $activistCols);
            }
        }

        if ($db->tableExists('talents')) {
            if (! $db->fieldExists('region_trabajo', 'talents')) {
                $this->forge->addColumn('talents', [
                    'region_trabajo' => [
                        'type'       => 'INT',
                        'constraint' => 11,
                        'null'       => true,
                        'default'    => 1,
                        'after'      => 'activist_id',
                    ],
                ]);
            }

            // Sincronizar la región de trabajo de cada talento con la de su activista
            $db->query('UPDATE talents t INNER JOIN activists a ON a.id = t.activist_id SET t.region_trabajo = a.region_trabajo WHERE t.region_trabajo IS NULL OR t.region_trabajo = 0');
        }
    }

    public function down(): void
    {
        /** @var \CodeIgniter\Database\BaseConnection<object, object> $db */
        $db = $this->db;

        if ($db->tableExists('talents') && $db->fieldExists('region_trabajo', 'talents')) {
            $this->forge->dropColumn('talents', 'region_trabajo');
        }

        if ($db->tableExists('activists')) {
            $dropCols = [];
            if ($db->fieldExists('domicilio', 'activists')) {
                $dropCols[] = 'domicilio';
            }
            if ($db->fieldExists('region_trabajo', 'activists')) {
                $dropCols[] = 'region_trabajo';
            }
            if (count($dropCols) > 0) {
                $this->forge->dropColumn('activists', $dropCols);
            }
        }
    }
}


<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

class QueueWork extends BaseCommand
{
    protected $group       = 'RCT';
    protected $name        = 'queue:work';
    protected $description = 'Limpia exportaciones expiradas y procesa tareas diferidas (para cron cada minuto en Hostinger).';

    /**
     * @param array<int, string> $params
     */
    public function run(array $params): void
    {
        $db  = Database::connect();
        $now = date('Y-m-d H:i:s');

        // Limpiar archivos de exportación vencidos
        $expired = $db->table('exports')
            ->where('expires_at <', $now)
            ->where('status !=', 'expirado')
            ->get()
            ->getResultArray();

        $cleanedCount = 0;
        foreach ($expired as $exp) {
            if (!empty($exp['path'])) {
                $fullPath = rtrim(WRITEPATH, '/\\') . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string) $exp['path']);
                if (is_file($fullPath)) {
                    @unlink($fullPath);
                }
            }
            $db->table('exports')->where('id', (int) $exp['id'])->update(['status' => 'expirado']);
            $cleanedCount++;
        }

        CLI::write("RCT QueueWork completado: {$cleanedCount} exportaciones expiradas limpiadas.", 'green');
    }
}

<?php

declare(strict_types=1);

namespace App\Commands;

use App\Libraries\Sms\TwilioSmsProvider;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

class QueueWork extends BaseCommand
{
    protected $group       = 'RCT';
    protected $name        = 'queue:work';
    protected $description = 'Procesa lotes de SMS masivos en cola y limpia exportaciones expiradas (para cron cada minuto en Hostinger).';

    /**
     * @param array<int, string> $params
     */
    public function run(array $params): void
    {
        $db  = Database::connect();
        $now = date('Y-m-d H:i:s');

        // 1. Procesar hasta 50 mensajes SMS en estado 'queued'
        $queued = $db->table('sms_messages')
            ->where('status', 'queued')
            ->orderBy('id', 'ASC')
            ->limit(50)
            ->get()
            ->getResultArray();

        $provider  = new TwilioSmsProvider();
        $sentCount = 0;

        foreach ($queued as $msg) {
            $phone = (string) $msg['telefono'];
            $isOpt = $db->table('sms_optouts')->where('telefono', $phone)->countAllResults() > 0;
            if ($isOpt) {
                $db->table('sms_messages')->where('id', (int) $msg['id'])->update(['status' => 'optout']);
                continue;
            }

            $res = $provider->sendSms($phone, (string) $msg['cuerpo']);
            $db->table('sms_messages')->where('id', (int) $msg['id'])->update([
                'provider_id' => $res['provider_id'],
                'status'      => $res['status'],
                'error'       => $res['error'],
                'sent_at'     => $res['success'] ? $now : null,
            ]);

            if ($res['success'] && !empty($msg['campaign_id'])) {
                $sentCount++;
                $db->query('UPDATE sms_campaigns SET enviados = enviados + 1 WHERE id = ?', [(int) $msg['campaign_id']]);
            }
        }

        // Actualizar campañas sin pendientes a 'completada'
        $processingCampaigns = $db->table('sms_campaigns')->where('status', 'procesando')->get()->getResultArray();
        foreach ($processingCampaigns as $camp) {
            $pendingForCamp = $db->table('sms_messages')
                ->where('campaign_id', (int) $camp['id'])
                ->where('status', 'queued')
                ->countAllResults();
            if ($pendingForCamp === 0) {
                $db->table('sms_campaigns')->where('id', (int) $camp['id'])->update(['status' => 'completada']);
            }
        }

        // 2. Limpiar archivos de exportación vencidos
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

        CLI::write("RCT QueueWork completado: {$sentCount} SMS enviados, {$cleanedCount} exportaciones expiradas limpiadas.", 'green');
    }
}

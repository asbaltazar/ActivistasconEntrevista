<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Libraries\Sms\TwilioSmsProvider;
use CodeIgniter\HTTP\ResponseInterface;

class AdminSmsController extends BaseApiController
{
    public function listCampaigns(): ResponseInterface
    {
        $rows = $this->db->table('sms_campaigns c')
            ->select('c.*, u.username')
            ->join('users u', 'u.id = c.user_id', 'left')
            ->orderBy('c.id', 'DESC')
            ->limit(50)
            ->get()
            ->getResultArray();

        $optoutsCount = $this->db->table('sms_optouts')->countAllResults();
        $todayCount   = $this->db->table('sms_messages')
            ->where('created_at >=', date('Y-m-d 00:00:00'))
            ->countAllResults();

        return $this->apiResponse($rows, [
            'optouts_total' => $optoutsCount,
            'sent_today'    => $todayCount,
            'daily_limit'   => (int) (env('TWILIO_DAILY_LIMIT') ?? 5000),
        ]);
    }

    public function showCampaign(int $id): ResponseInterface
    {
        $campaign = $this->db->table('sms_campaigns')->where('id', $id)->get()->getRowArray();
        if ($campaign === null) {
            return $this->apiResponse(null, null, ['Campaña SMS no encontrada.'], 404);
        }

        $messages = $this->db->table('sms_messages')
            ->where('campaign_id', $id)
            ->orderBy('id', 'DESC')
            ->limit(200)
            ->get()
            ->getResultArray();

        $campaign['messages'] = $messages;
        return $this->apiResponse($campaign);
    }

    public function createCampaign(): ResponseInterface
    {
        if (($deny = $this->requireWriteRole()) !== null) {
            return $deny;
        }

        $input     = $this->inputData();
        $nombre    = trim((string) ($input['nombre'] ?? ''));
        $plantilla = trim((string) ($input['plantilla'] ?? ''));
        $filtros   = isset($input['filtros']) && is_array($input['filtros']) ? $input['filtros'] : [];

        if ($nombre === '' || $plantilla === '') {
            return $this->apiResponse(null, null, ['El nombre de la campaña y la plantilla del mensaje son obligatorios.'], 422);
        }

        // Verificar límite diario de envíos
        $dailyLimit = (int) (env('TWILIO_DAILY_LIMIT') ?? 5000);
        $sentToday  = $this->db->table('sms_messages')
            ->where('created_at >=', date('Y-m-d 00:00:00'))
            ->countAllResults();

        if ($sentToday >= $dailyLimit) {
            return $this->apiResponse(null, null, ["Se ha alcanzado el límite diario de envíos SMS ({$dailyLimit})."], 429);
        }

        // Obtener lista de exclusión (STOP/BAJA)
        $optoutRows = $this->db->table('sms_optouts')->select('telefono')->get()->getResultArray();
        $optoutMap  = [];
        foreach ($optoutRows as $or) {
            $optoutMap[(string) $or['telefono']] = true;
        }

        // Filtrar Talentos destinatarios
        $builder = $this->db->table('talents')
            ->select('id, nombre, seccion, telefono')
            ->where('deleted_at IS NULL')
            ->where('validacion !=', 'rechazado');

        if (!empty($filtros['estado_id'])) {
            $builder->where('estado_id', (int) $filtros['estado_id']);
        }
        if (!empty($filtros['municipio_id'])) {
            $builder->where('municipio_id', (int) $filtros['municipio_id']);
        }
        if (!empty($filtros['seccion'])) {
            $builder->where('seccion', (int) $filtros['seccion']);
        }
        if (!empty($filtros['activist_id'])) {
            $builder->where('activist_id', (int) $filtros['activist_id']);
        }

        $recipients = $builder->get()->getResultArray();
        $eligible   = [];
        foreach ($recipients as $rec) {
            $tel = (string) $rec['telefono'];
            if (!isset($optoutMap[$tel])) {
                $eligible[] = $rec;
            }
        }

        $now    = date('Y-m-d H:i:s');
        $userId = (int) ($this->currentUser()['id'] ?? 1);

        $this->db->table('sms_campaigns')->insert([
            'user_id'    => $userId,
            'nombre'     => $nombre,
            'plantilla'  => $plantilla,
            'filtros'    => json_encode($filtros, JSON_UNESCAPED_UNICODE),
            'total'      => count($eligible),
            'enviados'   => 0,
            'status'     => count($eligible) > 50 ? 'procesando' : 'completada',
            'created_at' => $now,
        ]);
        $campaignId = (int) $this->db->insertID();

        $provider  = new TwilioSmsProvider();
        $sentCount = 0;

        foreach ($eligible as $idx => $rec) {
            $firstName = explode(' ', trim((string) $rec['nombre']))[0] ?? (string) $rec['nombre'];
            $body      = strtr($plantilla, [
                '{nombre}'  => $firstName,
                '{seccion}' => (string) $rec['seccion'],
            ]);
            $cleanBody = TwilioSmsProvider::sanitizeGsm7($body);

            // Procesar los primeros 50 de forma inmediata y encolar el resto para `php spark queue:work`
            if ($idx < 50) {
                $res = $provider->sendSms((string) $rec['telefono'], $cleanBody);
                if ($res['success']) {
                    $sentCount++;
                }
                $this->db->table('sms_messages')->insert([
                    'talent_id'   => (int) $rec['id'],
                    'telefono'    => (string) $rec['telefono'],
                    'plantilla'   => 'campana',
                    'cuerpo'      => $cleanBody,
                    'campaign_id' => $campaignId,
                    'provider_id' => $res['provider_id'],
                    'status'      => $res['status'],
                    'error'       => $res['error'],
                    'sent_at'     => $res['success'] ? $now : null,
                    'created_at'  => $now,
                ]);
            } else {
                $this->db->table('sms_messages')->insert([
                    'talent_id'   => (int) $rec['id'],
                    'telefono'    => (string) $rec['telefono'],
                    'plantilla'   => 'campana',
                    'cuerpo'      => $cleanBody,
                    'campaign_id' => $campaignId,
                    'status'      => 'queued',
                    'created_at'  => $now,
                ]);
            }
        }

        $this->db->table('sms_campaigns')->where('id', $campaignId)->update([
            'enviados' => $sentCount,
            'status'   => $sentCount >= count($eligible) ? 'completada' : 'procesando',
        ]);

        $this->logAudit('sms.campaign_create', 'sms_campaigns', $campaignId, [
            'nombre'   => $nombre,
            'total'    => count($eligible),
            'enviados' => $sentCount,
        ]);

        return $this->apiResponse([
            'id'       => $campaignId,
            'nombre'   => $nombre,
            'total'    => count($eligible),
            'enviados' => $sentCount,
            'status'   => $sentCount >= count($eligible) ? 'completada' : 'procesando',
        ], null, null, 201);
    }

    public function listOptouts(): ResponseInterface
    {
        $rows = $this->db->table('sms_optouts')
            ->orderBy('created_at', 'DESC')
            ->get()
            ->getResultArray();
        return $this->apiResponse($rows);
    }

    /**
     * POST /api/v1/webhooks/twilio/status
     * Callback de estado de entrega de Twilio (valida X-Twilio-Signature).
     */
    public function webhookStatus(): ResponseInterface
    {
        $params    = $this->inputData();
        $signature = $this->request->getHeaderLine('X-Twilio-Signature');
        $provider  = new TwilioSmsProvider();

        if (!$provider->validateWebhookSignature((string) current_url(), $params, $signature)) {
            return $this->apiResponse(null, null, ['Firma X-Twilio-Signature inválida.'], 403);
        }

        $messageSid    = trim((string) ($params['MessageSid'] ?? $params['SmsSid'] ?? ''));
        $messageStatus = strtolower(trim((string) ($params['MessageStatus'] ?? $params['SmsStatus'] ?? '')));
        $errorCode     = isset($params['ErrorCode']) ? (string) $params['ErrorCode'] : null;

        if ($messageSid !== '' && $messageStatus !== '') {
            $updates = ['status' => $messageStatus];
            if ($messageStatus === 'delivered') {
                $updates['delivered_at'] = date('Y-m-d H:i:s');
            }
            if ($errorCode !== null) {
                $updates['error'] = 'Twilio Error ' . $errorCode;
            }
            $this->db->table('sms_messages')
                ->where('provider_id', $messageSid)
                ->update($updates);
        }

        return $this->apiResponse(['received' => true]);
    }

    /**
     * POST /api/v1/webhooks/twilio/inbound
     * Callback de mensajes entrantes STOP / BAJA (RF-P11: lista de exclusión automática).
     */
    public function webhookInbound(): ResponseInterface
    {
        $params    = $this->inputData();
        $signature = $this->request->getHeaderLine('X-Twilio-Signature');
        $provider  = new TwilioSmsProvider();

        if (!$provider->validateWebhookSignature((string) current_url(), $params, $signature)) {
            return $this->apiResponse(null, null, ['Firma X-Twilio-Signature inválida.'], 403);
        }

        $fromRaw = (string) ($params['From'] ?? '');
        $bodyRaw = strtoupper(trim((string) ($params['Body'] ?? '')));
        $digits  = (string) preg_replace('/\D/', '', $fromRaw);
        $phone10 = strlen($digits) >= 10 ? substr($digits, -10) : $digits;

        $optoutKeywords = ['STOP', 'BAJA', 'CANCELAR', 'UNSUBSCRIBE', 'ALTO', 'FIN'];
        $firstWord      = explode(' ', $bodyRaw)[0] ?? '';

        if (strlen($phone10) === 10 && in_array($firstWord, $optoutKeywords, true)) {
            $exists = $this->db->table('sms_optouts')->where('telefono', $phone10)->countAllResults();
            if ($exists === 0) {
                $this->db->table('sms_optouts')->insert([
                    'telefono'   => $phone10,
                    'motivo'     => $firstWord,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }

            // Cancelar cualquier mensaje en cola para este teléfono
            $this->db->table('sms_messages')
                ->where('telefono', $phone10)
                ->where('status', 'queued')
                ->update(['status' => 'optout']);

            return $this->apiResponse([
                'optout'   => true,
                'telefono' => $phone10,
                'keyword'  => $firstWord,
            ]);
        }

        return $this->apiResponse([
            'optout'   => false,
            'telefono' => $phone10,
        ]);
    }
}

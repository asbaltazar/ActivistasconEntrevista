<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Services\CryptoService;
use CodeIgniter\HTTP\ResponseInterface;

class SyncController extends BaseApiController
{
    private CryptoService $crypto;

    public function __construct()
    {
        parent::__construct();
        $this->crypto = new CryptoService();
    }

    /**
     * POST /api/v1/sync/talents
     * Alta o actualización idempotente por uuid (acepta un registro individual o un lote en `talents`).
     */
    public function syncTalents(): ResponseInterface
    {
        $activist = $this->currentActivist();
        $user     = $this->currentUser();

        if ($activist === null && ($user['role'] ?? '') !== 'superadmin') {
            return $this->apiResponse(null, null, ['Solo un Activista activo puede sincronizar Talentos.'], 403);
        }

        if ($activist !== null && ($activist['status'] ?? '') === 'suspendido') {
            return $this->apiResponse(null, null, ['Su cuenta de Activista está suspendida.'], 403);
        }

        $input   = $this->inputData();
        $isBatch = isset($input['talents']) && is_array($input['talents']);
        $items   = $isBatch ? $input['talents'] : [$input];

        $activistId = $activist !== null ? (int) $activist['id'] : (int) ($input['activist_id'] ?? 1);
        $deviceUuid = trim((string) ($input['device_uuid'] ?? $this->request->getHeaderLine('X-Device-UUID')));
        $deviceId   = null;

        if ($deviceUuid !== '' && $activist !== null) {
            $devRow = $this->db->table('devices')
                ->where('activist_id', $activistId)
                ->where('device_uuid', $deviceUuid)
                ->get()
                ->getRowArray();
            if ($devRow !== null) {
                $deviceId = (int) $devRow['id'];
                $this->db->table('devices')
                    ->where('id', $deviceId)
                    ->update(['last_sync_at' => date('Y-m-d H:i:s')]);
            }
        }

        $results     = [];
        $hasCreated  = false;
        $hasConflict = false;

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $res       = $this->processSingleTalent($item, $activistId, $deviceId);
            $results[] = $res;

            if (($res['http_status'] ?? 200) === 201) {
                $hasCreated = true;
            }
            if (($res['http_status'] ?? 200) === 409) {
                $hasConflict = true;
            }
        }

        if (!$isBatch) {
            $single = $results[0] ?? ['http_status' => 422, 'error' => 'Datos vacíos'];
            $code   = (int) ($single['http_status'] ?? 200);
            if ($code >= 400) {
                return $this->apiResponse(
                    $single['data'] ?? null,
                    ['uuid' => $single['uuid'] ?? null],
                    [$single['error'] ?? 'Error al sincronizar el registro.'],
                    $code
                );
            }
            return $this->apiResponse($single['data'], ['idempotent' => $single['idempotent'] ?? false, 'warnings' => $single['warnings'] ?? []], null, $code);
        }

        return $this->apiResponse([
            'results' => $results,
        ], [
            'total'         => count($results),
            'has_conflicts' => $hasConflict,
        ], null, $hasCreated ? 201 : 200);
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function processSingleTalent(array $item, int $activistId, ?int $deviceId): array
    {
        $now               = date('Y-m-d H:i:s');
        $uuid              = strtolower(trim((string) ($item['uuid'] ?? '')));
        $clasificacion     = trim((string) ($item['clasificacion'] ?? 'Conector'));
        $pregunta1Txt      = trim((string) ($item['pregunta_1_texto'] ?? env('RCT_PREGUNTA_1') ?? 'Pregunta1'));
        $audioPregunta1    = !empty($item['audio_pregunta_1']) ? (string) $item['audio_pregunta_1'] : null;
        $pregunta2Txt      = trim((string) ($item['pregunta_2_texto'] ?? env('RCT_PREGUNTA_2') ?? 'Pregunta2'));
        $audioPregunta2    = !empty($item['audio_pregunta_2']) ? (string) $item['audio_pregunta_2'] : null;
        $pregunta3Txt      = trim((string) ($item['pregunta_3_texto'] ?? env('RCT_PREGUNTA_3') ?? 'Pregunta3'));
        $audioPregunta3    = !empty($item['audio_pregunta_3']) ? (string) $item['audio_pregunta_3'] : null;
        $nombre            = trim((string) ($item['nombre'] ?? ''));
        $apPaterno         = trim((string) ($item['ap_paterno'] ?? ''));
        $apMaterno         = trim((string) ($item['ap_materno'] ?? ''));
        $domicilioCompleto = trim((string) ($item['domicilio_completo'] ?? $item['calle_numero'] ?? ''));
        $cp                = trim((string) ($item['codigo_postal'] ?? ''));
        $estadoClave       = trim((string) ($item['estado_clave'] ?? $item['estado_id'] ?? '09'));
        $municipioClave    = trim((string) ($item['municipio_clave'] ?? $item['municipio_id'] ?? '001'));
        $localidadClave    = trim((string) ($item['localidad_clave'] ?? $item['localidad_id'] ?? '0001'));
        $seccion           = (int) ($item['seccion'] ?? 0);
        $rawVigencia       = trim((string) ($item['vigencia'] ?? date('Y')));
        if (preg_match('/(\d{4})\s*$/', $rawVigencia, $vm)) {
            $vigencia = (int) $vm[1];
        } else {
            $vigencia = (int) $rawVigencia ?: (int) date('Y');
        }
        $anioRegistro      = !empty($item['anio_registro']) ? (int) $item['anio_registro'] : null;
        $claveElector      = strtoupper(trim((string) ($item['clave_elector'] ?? '')));
        $curp              = strtoupper(trim((string) ($item['curp'] ?? '')));
        $rawFechaNac       = !empty($item['fecha_nacimiento']) ? trim((string) $item['fecha_nacimiento']) : '';
        if (preg_match('/^(\d{2})[\/\-](\d{2})[\/\-](\d{4})$/', $rawFechaNac, $fm)) {
            $fechaNac = "{$fm[3]}-{$fm[2]}-{$fm[1]}";
        } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawFechaNac)) {
            $fechaNac = $rawFechaNac;
        } else {
            $fechaNac = null;
        }
        $sexo              = !empty($item['sexo']) ? strtoupper(substr(trim((string) $item['sexo']), 0, 1)) : null;
        $telefono          = (string) preg_replace('/\D/', '', (string) ($item['telefono'] ?? ''));
        $email             = !empty($item['email']) ? trim((string) $item['email']) : null;
        $whatsapp          = isset($item['whatsapp']) ? ((bool) $item['whatsapp'] ? 1 : 0) : 1;
        $lat               = isset($item['lat']) ? (float) $item['lat'] : 19.4326077;
        $lng               = isset($item['lng']) ? (float) $item['lng'] : -99.1332080;
        $geoPrecision      = trim((string) ($item['geocode_precision'] ?? 'ROOFTOP'));
        $geoSource         = trim((string) ($item['geocode_source'] ?? 'google'));
        $capturaLat        = isset($item['captura_lat']) && $item['captura_lat'] !== '' ? (float) $item['captura_lat'] : null;
        $capturaLng        = isset($item['captura_lng']) && $item['captura_lng'] !== '' ? (float) $item['captura_lng'] : null;
        $capturaAt         = !empty($item['captura_at']) ? date('Y-m-d H:i:s', strtotime((string) $item['captura_at'])) : $now;
        $consentAt         = !empty($item['consentimiento_at']) ? date('Y-m-d H:i:s', strtotime((string) $item['consentimiento_at'])) : $now;
        $avisoVer          = trim((string) ($item['aviso_version'] ?? '1.0'));
        $ocrConfianza      = isset($item['ocr_confianza'])
            ? (is_string($item['ocr_confianza']) ? $item['ocr_confianza'] : json_encode($item['ocr_confianza']))
            : null;

        if (!in_array($clasificacion, ['Conector', 'Experto', 'Vendedor'], true)) {
            $clasificacion = 'Conector';
        }

        // Validaciones
        if ($uuid === '' || strlen($uuid) < 16) {
            return $this->logAndReturnSyncError($activistId, $deviceId, $uuid ?: 'missing-uuid', 'El identificador UUID del cliente es obligatorio.', 422);
        }
        if ($nombre === '' || $apPaterno === '') {
            return $this->logAndReturnSyncError($activistId, $deviceId, $uuid, 'El nombre y apellido paterno son obligatorios.', 422);
        }
        if (!preg_match('/^[A-Z]{6}\d{8}[HM]\d{3}$/', $claveElector)) {
            return $this->logAndReturnSyncError($activistId, $deviceId, $uuid, 'Formato de Clave de Elector inválido (debe tener 18 caracteres: 6 letras + 8 dígitos + H/M + 3 dígitos).', 422);
        }
        if ($curp !== '' && !preg_match('/^[A-Z]{4}\d{6}[HM][A-Z]{5}[A-Z0-9]\d$/', $curp)) {
            return $this->logAndReturnSyncError($activistId, $deviceId, $uuid, 'Formato de CURP inválido (18 caracteres).', 422);
        }
        if (!preg_match('/^\d{10}$/', $telefono)) {
            return $this->logAndReturnSyncError($activistId, $deviceId, $uuid, 'El teléfono celular debe tener 10 dígitos.', 422);
        }
        if (!preg_match('/^\d{5}$/', $cp)) {
            return $this->logAndReturnSyncError($activistId, $deviceId, $uuid, 'El código postal debe tener 5 dígitos.', 422);
        }
        if ($seccion < 1 || $seccion > 9999) {
            return $this->logAndReturnSyncError($activistId, $deviceId, $uuid, 'La sección electoral debe tener entre 1 y 4 dígitos.', 422);
        }

        $claveHash = (string) $this->crypto->hashIdentifier($claveElector);
        $curpHash  = $curp !== '' ? $this->crypto->hashIdentifier($curp) : null;

        // 1. Verificar si ya existe por UUID (Idempotencia RF-A5)
        $existingByUuid = $this->db->table('talents')
            ->where('uuid', $uuid)
            ->get()
            ->getRowArray();

        // 2. Verificar duplicado por clave_elector_hash en otro UUID distinto (RF-A7: rechazar con 409)
        $dupClaveQuery = $this->db->table('talents')
            ->where('clave_elector_hash', $claveHash)
            ->where('deleted_at IS NULL');
        if ($existingByUuid !== null) {
            $dupClaveQuery->where('id !=', (int) $existingByUuid['id']);
        }
        $dupClave = $dupClaveQuery->get()->getRowArray();

        if ($dupClave !== null) {
            return $this->logAndReturnSyncError(
                $activistId,
                $deviceId,
                $uuid,
                'La Clave de Elector ya se encuentra registrada en otro Talento (duplicado rechazado).',
                409,
                ['duplicate_field' => 'clave_elector', 'existing_uuid' => $dupClave['uuid']]
            );
        }

        // 3. Detectar advertencias de posible duplicado por CURP o teléfono
        $warnings = [];
        if ($curpHash !== null) {
            $dupCurpQ = $this->db->table('talents')->where('curp_hash', $curpHash)->where('deleted_at IS NULL');
            if ($existingByUuid !== null) {
                $dupCurpQ->where('id !=', (int) $existingByUuid['id']);
            }
            if ($dupCurpQ->countAllResults() > 0) {
                $warnings[] = 'La CURP capturada ya aparece en otro registro.';
            }
        }
        $dupTelQ = $this->db->table('talents')->where('telefono', $telefono)->where('deleted_at IS NULL');
        if ($existingByUuid !== null) {
            $dupTelQ->where('id !=', (int) $existingByUuid['id']);
        }
        if ($dupTelQ->countAllResults() > 0) {
            $warnings[] = 'El número de teléfono ya está asociado a otro Talento.';
        }

        $actRow        = $this->currentActivist();
        if ($actRow === null || (int) ($actRow['id'] ?? 0) !== $activistId) {
            $actRow = $this->db->table('activists')->where('id', $activistId)->get()->getRowArray();
        }
        $regionTrabajo = (int) ($actRow['region_trabajo'] ?? 1);

        $rowPayload = [
            'uuid'               => $uuid,
            'activist_id'        => $activistId,
            'region_trabajo'     => $regionTrabajo,
            'clasificacion'      => $clasificacion,
            'pregunta_1_texto'   => $pregunta1Txt,
            'audio_pregunta_1'   => $audioPregunta1,
            'pregunta_2_texto'   => $pregunta2Txt,
            'audio_pregunta_2'   => $audioPregunta2,
            'pregunta_3_texto'   => $pregunta3Txt,
            'audio_pregunta_3'   => $audioPregunta3,
            'nombre'             => $nombre,
            'ap_paterno'         => $apPaterno,
            'ap_materno'         => $apMaterno !== '' ? $apMaterno : null,
            'domicilio_completo' => (string) $this->crypto->encrypt($domicilioCompleto),
            'codigo_postal'      => $cp,
            'estado_clave'       => $estadoClave,
            'municipio_clave'    => $municipioClave,
            'localidad_clave'    => $localidadClave,
            'seccion'            => $seccion,
            'vigencia'           => $vigencia,
            'anio_registro'      => $anioRegistro,
            'clave_elector_enc'  => (string) $this->crypto->encrypt($claveElector),
            'clave_elector_hash' => $claveHash,
            'curp_enc'           => $curp !== '' ? $this->crypto->encrypt($curp) : null,
            'curp_hash'          => $curpHash,
            'fecha_nacimiento'   => $fechaNac,
            'sexo'               => $sexo,
            'telefono'           => $telefono,
            'email'              => $email,
            'whatsapp'           => $whatsapp,
            'lat'                => $lat,
            'lng'                => $lng,
            'geocode_precision'  => $geoPrecision,
            'geocode_source'     => in_array($geoSource, ['google', 'manual', 'gps'], true) ? $geoSource : 'google',
            'captura_lat'        => $capturaLat,
            'captura_lng'        => $capturaLng,
            'captura_at'         => $capturaAt,
            'ocr_confianza'      => $ocrConfianza,
            'consentimiento_at'  => $consentAt,
            'aviso_version'      => $avisoVer,
            'updated_at'         => $now,
        ];

        if ($existingByUuid !== null) {
            $talentId = (int) $existingByUuid['id'];
            if (($existingByUuid['validacion'] ?? 'pendiente') !== 'validado') {
                $this->db->table('talents')
                    ->where('id', $talentId)
                    ->update($rowPayload);
            }

            if (!empty($item['files']) && is_array($item['files'])) {
                $this->saveBase64FilesForTalent($talentId, $uuid, $item['files']);
            }

            $this->db->table('sync_log')->insert([
                'activist_id' => $activistId,
                'device_id'   => $deviceId,
                'talent_uuid' => $uuid,
                'status'      => 'idempotent_ok',
                'created_at'  => $now,
            ]);

            return [
                'uuid'        => $uuid,
                'http_status' => 200,
                'idempotent'  => true,
                'warnings'    => $warnings,
                'data'        => [
                    'id'         => $talentId,
                    'uuid'       => $uuid,
                    'status'     => 'sincronizado',
                    'idempotent' => true,
                    'warnings'   => $warnings,
                ],
            ];
        }

        // Crear nuevo registro
        $rowPayload['validacion'] = 'pendiente';
        $rowPayload['created_at'] = $now;

        $this->db->table('talents')->insert($rowPayload);
        $talentId = (int) $this->db->insertID();

        if (!empty($item['files']) && is_array($item['files'])) {
            $this->saveBase64FilesForTalent($talentId, $uuid, $item['files']);
        }

        $this->db->table('sync_log')->insert([
            'activist_id' => $activistId,
            'device_id'   => $deviceId,
            'talent_uuid' => $uuid,
            'status'      => 'sincronizado',
            'created_at'  => $now,
        ]);

        $this->logAudit('talent.sync_create', 'talents', $talentId, [
            'uuid'          => $uuid,
            'clasificacion' => $clasificacion,
            'seccion'       => $seccion,
        ]);

        return [
            'uuid'        => $uuid,
            'http_status' => 201,
            'idempotent'  => false,
            'warnings'    => $warnings,
            'data'        => [
                'id'         => $talentId,
                'uuid'       => $uuid,
                'status'     => 'sincronizado',
                'idempotent' => false,
                'warnings'   => $warnings,
            ],
        ];
    }

    /**
     * @param array<string, mixed>|null $extraData
     * @return array<string, mixed>
     */
    private function logAndReturnSyncError(
        int $activistId,
        ?int $deviceId,
        string $uuid,
        string $errorMsg,
        int $httpStatus,
        ?array $extraData = null
    ): array {
        $this->db->table('sync_log')->insert([
            'activist_id' => $activistId,
            'device_id'   => $deviceId,
            'talent_uuid' => substr($uuid, 0, 36),
            'status'      => 'error',
            'error'       => $errorMsg,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);

        return [
            'uuid'        => $uuid,
            'http_status' => $httpStatus,
            'error'       => $errorMsg,
            'data'        => $extraData,
        ];
    }

    /**
     * POST /api/v1/talents/{uuid}/files
     * Subida autenticada de imágenes fuera de public_html (`writable/uploads/talents/{uuid}/`).
     */
    public function uploadFiles(string $uuid): ResponseInterface
    {
        $talent = $this->db->table('talents')
            ->where('uuid', strtolower(trim($uuid)))
            ->where('deleted_at IS NULL')
            ->get()
            ->getRowArray();

        if ($talent === null) {
            return $this->apiResponse(null, null, ['Talento no encontrado para asociar archivos.'], 404);
        }

        $talentId  = (int) $talent['id'];
        $targetDir = rtrim(WRITEPATH, '/\\') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'talents' . DIRECTORY_SEPARATOR . $talent['uuid'];
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0750, true);
        }

        $validTypes = ['selfie', 'ine_frente', 'firma'];
        $savedFiles = [];
        $now        = date('Y-m-d H:i:s');

        foreach ($validTypes as $tipo) {
            $file = $this->request->getFile($tipo);
            if ($file !== null && $file->isValid() && !$file->hasMoved()) {
                $mime = $file->getMimeType();
                $ext  = match ($mime) {
                    'image/webp' => 'webp',
                    'image/png'  => 'png',
                    default      => 'jpg',
                };
                $fileName = "{$tipo}.{$ext}";
                $fullPath = $targetDir . DIRECTORY_SEPARATOR . $fileName;
                if (is_file($fullPath)) {
                    @unlink($fullPath);
                }
                $file->move($targetDir, $fileName);

                $relPath = 'uploads/talents/' . $talent['uuid'] . '/' . $fileName;
                $size    = (int) filesize($fullPath);
                $sha256  = (string) hash_file('sha256', $fullPath);

                $this->upsertTalentFile($talentId, $tipo, $relPath, $mime, $size, $sha256, $now);
                $savedFiles[] = ['tipo' => $tipo, 'size' => $size, 'sha256' => $sha256];
            }
        }

        $input     = $this->inputData();
        $base64Map = [];
        foreach ($validTypes as $tipo) {
            if (!empty($input[$tipo]) && is_string($input[$tipo])) {
                $base64Map[$tipo] = $input[$tipo];
            }
        }
        if (count($base64Map) > 0) {
            $extra      = $this->saveBase64FilesForTalent($talentId, (string) $talent['uuid'], $base64Map);
            $savedFiles = array_merge($savedFiles, $extra);
        }

        return $this->apiResponse([
            'talent_uuid' => $talent['uuid'],
            'files'       => $savedFiles,
        ], null, null, 201);
    }

    /**
     * @param array<string, mixed> $filesMap
     * @return array<int, array<string, mixed>>
     */
    private function saveBase64FilesForTalent(int $talentId, string $uuid, array $filesMap): array
    {
        $validTypes = ['selfie', 'ine_frente', 'firma'];
        $targetDir  = rtrim(WRITEPATH, '/\\') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'talents' . DIRECTORY_SEPARATOR . $uuid;
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0750, true);
        }

        $saved = [];
        $now   = date('Y-m-d H:i:s');

        foreach ($validTypes as $tipo) {
            if (empty($filesMap[$tipo]) || !is_string($filesMap[$tipo])) {
                continue;
            }
            $rawStr = $filesMap[$tipo];
            $mime   = 'image/jpeg';
            if (preg_match('/^data:(image\/[a-zA-Z0-9+.-]+);base64,(.+)$/', $rawStr, $m)) {
                $mime   = strtolower($m[1]);
                $rawStr = $m[2];
            }
            $bytes = base64_decode($rawStr, true);
            if ($bytes === false || strlen($bytes) === 0) {
                continue;
            }
            $ext = match ($mime) {
                'image/webp' => 'webp',
                'image/png'  => 'png',
                default      => 'jpg',
            };
            $fileName = "{$tipo}.{$ext}";
            $fullPath = $targetDir . DIRECTORY_SEPARATOR . $fileName;
            file_put_contents($fullPath, $bytes);

            $relPath = 'uploads/talents/' . $uuid . '/' . $fileName;
            $size    = strlen($bytes);
            $sha256  = hash('sha256', $bytes);

            $this->upsertTalentFile($talentId, $tipo, $relPath, $mime, $size, $sha256, $now);
            $saved[] = ['tipo' => $tipo, 'size' => $size, 'sha256' => $sha256];
        }

        return $saved;
    }

    private function upsertTalentFile(int $talentId, string $tipo, string $relPath, string $mime, int $size, string $sha256, string $now): void
    {
        $existing = $this->db->table('talent_files')
            ->where('talent_id', $talentId)
            ->where('tipo', $tipo)
            ->get()
            ->getRowArray();

        if ($existing !== null) {
            $this->db->table('talent_files')
                ->where('id', (int) $existing['id'])
                ->update([
                    'path'       => $relPath,
                    'mime'       => $mime,
                    'size'       => $size,
                    'sha256'     => $sha256,
                    'created_at' => $now,
                ]);
        } else {
            $this->db->table('talent_files')->insert([
                'talent_id'  => $talentId,
                'tipo'       => $tipo,
                'path'       => $relPath,
                'mime'       => $mime,
                'size'       => $size,
                'sha256'     => $sha256,
                'created_at' => $now,
            ]);
        }
    }

    /**
     * GET /api/v1/me/talents (RF-A6: Mis Talentos)
     */
    public function myTalents(): ResponseInterface
    {
        $activist = $this->currentActivist();
        if ($activist === null) {
            return $this->apiResponse([], ['total' => 0]);
        }

        $search     = trim((string) ($this->request->getGet('q') ?? ''));
        $validacion = trim((string) ($this->request->getGet('validacion') ?? ''));

        $builder = $this->db->table('talents')
            ->where('activist_id', (int) $activist['id'])
            ->where('deleted_at IS NULL');

        if ($validacion !== '') {
            $builder->where('validacion', $validacion);
        }

        if ($search !== '') {
            $builder->groupStart()
                ->like('nombre', $search)
                ->orLike('ap_paterno', $search)
                ->orLike('ap_materno', $search)
                ->orLike('clasificacion', $search)
                ->orLike('telefono', $search)
                ->groupEnd();
        }

        $rows = $builder->orderBy('id', 'DESC')->limit(200)->get()->getResultArray();
        $list = [];

        foreach ($rows as $row) {
            $list[] = [
                'id'                 => (int) $row['id'],
                'uuid'               => (string) $row['uuid'],
                'clasificacion'      => (string) ($row['clasificacion'] ?? 'Conector'),
                'nombre'             => (string) $row['nombre'],
                'ap_paterno'         => (string) $row['ap_paterno'],
                'ap_materno'         => $row['ap_materno'],
                'domicilio_completo' => $this->crypto->decrypt((string) $row['domicilio_completo']),
                'codigo_postal'      => (string) $row['codigo_postal'],
                'estado_clave'       => (string) $row['estado_clave'],
                'municipio_clave'    => (string) $row['municipio_clave'],
                'localidad_clave'    => (string) $row['localidad_clave'],
                'seccion'            => (int) $row['seccion'],
                'vigencia'           => (int) $row['vigencia'],
                'clave_elector'      => $this->crypto->decrypt((string) $row['clave_elector_enc']),
                'curp'               => $this->crypto->decrypt($row['curp_enc'] !== null ? (string) $row['curp_enc'] : null),
                'telefono'           => (string) $row['telefono'],
                'lat'                => (float) $row['lat'],
                'lng'                => (float) $row['lng'],
                'validacion'         => (string) $row['validacion'],
                'observaciones'      => $row['observaciones'],
                'editable'           => ($row['validacion'] ?? 'pendiente') !== 'validado',
                'created_at'         => $row['created_at'],
            ];
        }

        return $this->apiResponse($list, ['total' => count($list)]);
    }

    /**
     * PUT /api/v1/me/talents/{uuid} (RF-A6: Edición si no está bloqueado por validación)
     */
    public function updateMyTalent(string $uuid): ResponseInterface
    {
        $activist = $this->currentActivist();
        if ($activist === null) {
            return $this->apiResponse(null, null, ['Solo aplica para activistas.'], 403);
        }

        $talent = $this->db->table('talents')
            ->where('uuid', strtolower(trim($uuid)))
            ->where('activist_id', (int) $activist['id'])
            ->where('deleted_at IS NULL')
            ->get()
            ->getRowArray();

        if ($talent === null) {
            return $this->apiResponse(null, null, ['Talento no encontrado.'], 404);
        }

        if (($talent['validacion'] ?? '') === 'validado') {
            return $this->apiResponse(null, null, ['Este Talento ya fue validado y no puede editarse.'], 422);
        }

        $input         = $this->inputData();
        $input['uuid'] = $talent['uuid'];
        $res           = $this->processSingleTalent($input, (int) $activist['id'], null);

        if (($res['http_status'] ?? 200) >= 400) {
            return $this->apiResponse(null, null, [$res['error'] ?? 'Error al actualizar.'], (int) $res['http_status']);
        }

        if (($talent['validacion'] ?? '') === 'observado') {
            $this->db->table('talents')
                ->where('id', (int) $talent['id'])
                ->update(['validacion' => 'pendiente']);
        }

        return $this->apiResponse($res['data']);
    }
}

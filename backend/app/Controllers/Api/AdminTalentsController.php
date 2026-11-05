<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Services\CryptoService;
use CodeIgniter\HTTP\ResponseInterface;

class AdminTalentsController extends BaseApiController
{
    private CryptoService $crypto;

    public function __construct()
    {
        parent::__construct();
        $this->crypto = new CryptoService();
    }

    public function index(): ResponseInterface
    {
        $page            = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage         = min(100, max(10, (int) ($this->request->getGet('per_page') ?? 25)));
        $activistId      = (int) ($this->request->getGet('activist_id') ?? 0);
        $regionTrabajo   = (int) ($this->request->getGet('region_trabajo') ?? 0);
        $clasificacion   = trim((string) ($this->request->getGet('clasificacion') ?? ''));
        $estadoClave     = trim((string) ($this->request->getGet('estado_clave') ?? $this->request->getGet('estado_id') ?? ''));
        $municipioClave  = trim((string) ($this->request->getGet('municipio_clave') ?? $this->request->getGet('municipio_id') ?? ''));
        $localidadClave  = trim((string) ($this->request->getGet('localidad_clave') ?? ''));
        $seccion         = (int) ($this->request->getGet('seccion') ?? 0);
        $cp              = trim((string) ($this->request->getGet('codigo_postal') ?? ''));
        $validacion      = trim((string) ($this->request->getGet('validacion') ?? ''));
        $vigenciaVencida = (string) ($this->request->getGet('vigencia_vencida') ?? '');
        $fechaDesde      = trim((string) ($this->request->getGet('fecha_desde') ?? ''));
        $fechaHasta      = trim((string) ($this->request->getGet('fecha_hasta') ?? ''));
        $search          = trim((string) ($this->request->getGet('q') ?? ''));

        $builder = $this->db->table('talents t')
            ->select('t.*, a.nombre as act_nombre, a.ap_paterno as act_paterno, a.ap_materno as act_materno, COALESCE(t.region_trabajo, a.region_trabajo, 1) as act_region_trabajo')
            ->join('activists a', 'a.id = t.activist_id', 'left')
            ->where('t.deleted_at IS NULL');

        if ($activistId > 0) {
            $builder->where('t.activist_id', $activistId);
        }
        if ($regionTrabajo > 0) {
            $builder->where('COALESCE(t.region_trabajo, a.region_trabajo, 1)', $regionTrabajo);
        }
        if ($clasificacion !== '') {
            $builder->where('t.clasificacion', $clasificacion);
        }
        if ($estadoClave !== '' && $estadoClave !== '0') {
            $builder->where('t.estado_clave', $estadoClave);
        }
        if ($municipioClave !== '' && $municipioClave !== '0') {
            $builder->where('t.municipio_clave', $municipioClave);
        }
        if ($localidadClave !== '' && $localidadClave !== '0') {
            $builder->where('t.localidad_clave', $localidadClave);
        }
        if ($seccion > 0) {
            $builder->where('t.seccion', $seccion);
        }
        if ($cp !== '') {
            $builder->where('t.codigo_postal', $cp);
        }
        if ($validacion !== '') {
            $builder->where('t.validacion', $validacion);
        }
        if ($vigenciaVencida === '1' || $vigenciaVencida === 'true') {
            $builder->where('t.vigencia <', (int) date('Y'));
        }
        if ($fechaDesde !== '') {
            $builder->where('t.created_at >=', $fechaDesde . ' 00:00:00');
        }
        if ($fechaHasta !== '') {
            $builder->where('t.created_at <=', $fechaHasta . ' 23:59:59');
        }
        if ($search !== '') {
            $searchHash = $this->crypto->hashIdentifier($search);
            $builder->groupStart()
                ->like('t.nombre', $search)
                ->orLike('t.ap_paterno', $search)
                ->orLike('t.ap_materno', $search)
                ->orLike('t.clasificacion', $search)
                ->orLike('t.telefono', $search);
            if ($searchHash !== null) {
                $builder->orWhere('t.clave_elector_hash', $searchHash)
                    ->orWhere('t.curp_hash', $searchHash);
            }
            $builder->groupEnd();
        }

        $total = $builder->countAllResults(false);
        $rows  = $builder->orderBy('t.id', 'DESC')
            ->limit($perPage, ($page - 1) * $perPage)
            ->get()
            ->getResultArray();

        $currentYear = (int) date('Y');
        $data        = [];

        foreach ($rows as $row) {
            $data[] = $this->formatTalentRow($row, $currentYear, false);
        }

        return $this->apiResponse($data, [
            'page'     => $page,
            'per_page' => $perPage,
            'total'    => $total,
        ]);
    }

    public function show(int $id): ResponseInterface
    {
        $row = $this->db->table('talents t')
            ->select('t.*, a.nombre as act_nombre, a.ap_paterno as act_paterno, a.ap_materno as act_materno, COALESCE(t.region_trabajo, a.region_trabajo, 1) as act_region_trabajo')
            ->join('activists a', 'a.id = t.activist_id', 'left')
            ->where('t.id', $id)
            ->where('t.deleted_at IS NULL')
            ->get()
            ->getRowArray();

        if ($row === null) {
            return $this->apiResponse(null, null, ['Talento no encontrado.'], 404);
        }

        $files = $this->db->table('talent_files')
            ->where('talent_id', $id)
            ->get()
            ->getResultArray();

        $formatted          = $this->formatTalentRow($row, (int) date('Y'), true);
        $formatted['files'] = array_map(static fn ($f) => [
            'id'         => (int) $f['id'],
            'tipo'       => (string) $f['tipo'],
            'mime'       => (string) $f['mime'],
            'size'       => (int) $f['size'],
            'sha256'     => (string) $f['sha256'],
            'url'        => '/api/v1/admin/files/' . (int) $f['id'],
            'created_at' => $f['created_at'],
        ], $files);

        $this->logAudit('talent.view', 'talents', $id);

        return $this->apiResponse($formatted);
    }

    public function update(int $id): ResponseInterface
    {
        if (($deny = $this->requireWriteRole()) !== null) {
            return $deny;
        }

        $row = $this->db->table('talents')
            ->where('id', $id)
            ->where('deleted_at IS NULL')
            ->get()
            ->getRowArray();

        if ($row === null) {
            return $this->apiResponse(null, null, ['Talento no encontrado.'], 404);
        }

        $input   = $this->inputData();
        $now     = date('Y-m-d H:i:s');
        $updates = ['updated_at' => $now];

        $simpleFields = [
            'clasificacion', 'nombre', 'ap_paterno', 'ap_materno', 'codigo_postal',
            'estado_clave', 'municipio_clave', 'localidad_clave',
            'seccion', 'vigencia', 'anio_registro', 'fecha_nacimiento', 'sexo',
            'telefono', 'email', 'whatsapp', 'lat', 'lng', 'validacion', 'observaciones',
        ];

        foreach ($simpleFields as $field) {
            if (array_key_exists($field, $input)) {
                $updates[$field] = $input[$field];
            }
        }

        if (!empty($input['domicilio_completo'])) {
            $updates['domicilio_completo'] = (string) $this->crypto->encrypt((string) $input['domicilio_completo']);
        }

        if (!empty($input['clave_elector'])) {
            $clave = strtoupper(trim((string) $input['clave_elector']));
            if (!preg_match('/^[A-Z]{6}\d{8}[HM]\d{3}$/', $clave)) {
                return $this->apiResponse(null, null, ['Formato de Clave de Elector inválido.'], 422);
            }
            $hash = (string) $this->crypto->hashIdentifier($clave);
            $dup  = $this->db->table('talents')
                ->where('clave_elector_hash', $hash)
                ->where('id !=', $id)
                ->where('deleted_at IS NULL')
                ->countAllResults();
            if ($dup > 0) {
                return $this->apiResponse(null, null, ['La Clave de Elector ya existe en otro Talento.'], 409);
            }
            $updates['clave_elector_enc']  = (string) $this->crypto->encrypt($clave);
            $updates['clave_elector_hash'] = $hash;
        }

        if (array_key_exists('curp', $input)) {
            $curp = strtoupper(trim((string) $input['curp']));
            $updates['curp_enc']  = $curp !== '' ? $this->crypto->encrypt($curp) : null;
            $updates['curp_hash'] = $curp !== '' ? $this->crypto->hashIdentifier($curp) : null;
        }

        $this->db->table('talents')->where('id', $id)->update($updates);
        $this->logAudit('talent.update', 'talents', $id, ['validacion' => $updates['validacion'] ?? $row['validacion']]);

        return $this->apiResponse(['id' => $id, 'updated' => true, 'validacion' => $updates['validacion'] ?? $row['validacion']]);
    }

    public function delete(int $id): ResponseInterface
    {
        if (($deny = $this->requireWriteRole()) !== null) {
            return $deny;
        }

        $row = $this->db->table('talents')->where('id', $id)->where('deleted_at IS NULL')->get()->getRowArray();
        if ($row === null) {
            return $this->apiResponse(null, null, ['Talento no encontrado.'], 404);
        }

        $this->db->table('talents')->where('id', $id)->update(['deleted_at' => date('Y-m-d H:i:s')]);
        $this->logAudit('talent.delete', 'talents', $id);

        return $this->apiResponse(['id' => $id, 'deleted' => true]);
    }

    /**
     * POST /api/v1/admin/talents/merge (RF-P2: Fusión de duplicados)
     */
    public function merge(): ResponseInterface
    {
        if (($deny = $this->requireWriteRole()) !== null) {
            return $deny;
        }

        $input       = $this->inputData();
        $primaryId   = (int) ($input['primary_id'] ?? 0);
        $secondaryId = (int) ($input['secondary_id'] ?? 0);

        if ($primaryId <= 0 || $secondaryId <= 0 || $primaryId === $secondaryId) {
            return $this->apiResponse(null, null, ['Debe especificar primary_id y secondary_id distintos.'], 422);
        }

        $primary   = $this->db->table('talents')->where('id', $primaryId)->where('deleted_at IS NULL')->get()->getRowArray();
        $secondary = $this->db->table('talents')->where('id', $secondaryId)->where('deleted_at IS NULL')->get()->getRowArray();

        if ($primary === null || $secondary === null) {
            return $this->apiResponse(null, null, ['Uno de los registros a fusionar no existe.'], 404);
        }

        // Transferir archivos faltantes del secundario al primario
        $secFiles = $this->db->table('talent_files')->where('talent_id', $secondaryId)->get()->getResultArray();
        foreach ($secFiles as $sf) {
            $existsInPri = $this->db->table('talent_files')
                ->where('talent_id', $primaryId)
                ->where('tipo', $sf['tipo'])
                ->countAllResults();
            if ($existsInPri === 0) {
                $this->db->table('talent_files')
                    ->where('id', (int) $sf['id'])
                    ->update(['talent_id' => $primaryId]);
            }
        }

        $this->db->table('talents')->where('id', $secondaryId)->update([
            'deleted_at'    => date('Y-m-d H:i:s'),
            'observaciones' => 'Fusionado con Talento ID #' . $primaryId,
        ]);

        $this->logAudit('talent.merge', 'talents', $primaryId, ['merged_from' => $secondaryId]);

        return $this->apiResponse([
            'merged'       => true,
            'primary_id'   => $primaryId,
            'secondary_id' => $secondaryId,
        ]);
    }

    /**
     * GET /api/v1/admin/files/{id}
     * Sirve imágenes de INE / selfie almacenadas fuera de public_html y registra la consulta en auditoría.
     */
    public function serveFile(int $id): ResponseInterface
    {
        $file = $this->db->table('talent_files')->where('id', $id)->get()->getRowArray();
        if ($file === null) {
            return $this->apiResponse(null, null, ['Archivo no encontrado.'], 404);
        }

        $this->logAudit('file.view_ine', 'talent_files', $id, [
            'talent_id' => (int) $file['talent_id'],
            'tipo'      => $file['tipo'],
        ]);

        $fullPath = rtrim(WRITEPATH, '/\\') . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string) $file['path']);

        if (is_file($fullPath)) {
            $bytes = (string) file_get_contents($fullPath);
            return $this->response
                ->setStatusCode(200)
                ->setHeader('Content-Type', (string) $file['mime'])
                ->setHeader('Cache-Control', 'private, max-age=300')
                ->setBody($bytes);
        }

        $label = strtoupper((string) $file['tipo']);
        $svg   = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="480" height="300" viewBox="0 0 480 300">
  <rect width="480" height="300" fill="#1e293b" rx="12"/>
  <text x="240" y="140" fill="#f8fafc" font-family="sans-serif" font-size="22" text-anchor="middle">EVIDENCIA AUTENTICADA ({$label})</text>
  <text x="240" y="175" fill="#94a3b8" font-family="sans-serif" font-size="14" text-anchor="middle">Talento #{$file['talent_id']} · Protegido fuera de public_html</text>
</svg>
SVG;
        return $this->response
            ->setStatusCode(200)
            ->setHeader('Content-Type', 'image/svg+xml')
            ->setBody($svg);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function formatTalentRow(array $row, int $currentYear, bool $includeAudio = false): array
    {
        $vigencia = (int) $row['vigencia'];
        $item     = [
            'id'                 => (int) $row['id'],
            'uuid'               => (string) $row['uuid'],
            'activist_id'        => (int) $row['activist_id'],
            'activist_nombre'    => trim(($row['act_nombre'] ?? '') . ' ' . ($row['act_paterno'] ?? '') . ' ' . ($row['act_materno'] ?? '')),
            'region_trabajo'     => (int) ($row['act_region_trabajo'] ?? $row['region_trabajo'] ?? 1),
            'clasificacion'      => (string) ($row['clasificacion'] ?? 'Conector'),
            'pregunta_1_texto'   => $row['pregunta_1_texto'] ?? 'Pregunta1',
            'pregunta_2_texto'   => $row['pregunta_2_texto'] ?? 'Pregunta2',
            'pregunta_3_texto'   => $row['pregunta_3_texto'] ?? 'Pregunta3',
            'tiene_audios'       => !empty($row['audio_pregunta_1']) && !empty($row['audio_pregunta_2']) && !empty($row['audio_pregunta_3']),
            'nombre'             => (string) $row['nombre'],
            'ap_paterno'         => (string) $row['ap_paterno'],
            'ap_materno'         => $row['ap_materno'],
            'nombre_completo'    => trim($row['nombre'] . ' ' . $row['ap_paterno'] . ' ' . ($row['ap_materno'] ?? '')),
            'domicilio_completo' => $this->crypto->decrypt((string) $row['domicilio_completo']) ?? (string) $row['domicilio_completo'],
            'codigo_postal'      => (string) $row['codigo_postal'],
            'estado_clave'       => (string) $row['estado_clave'],
            'municipio_clave'    => (string) $row['municipio_clave'],
            'localidad_clave'    => (string) $row['localidad_clave'],
            'seccion'            => (int) $row['seccion'],
            'vigencia'           => $vigencia,
            'vigencia_vencida'   => $vigencia > 0 && $vigencia < $currentYear,
            'anio_registro'      => $row['anio_registro'] !== null ? (int) $row['anio_registro'] : null,
            'clave_elector'      => $this->crypto->decrypt((string) $row['clave_elector_enc']),
            'curp'               => $this->crypto->decrypt($row['curp_enc'] !== null ? (string) $row['curp_enc'] : null),
            'fecha_nacimiento'   => $row['fecha_nacimiento'],
            'sexo'               => $row['sexo'],
            'telefono'           => (string) $row['telefono'],
            'email'              => $row['email'],
            'whatsapp'           => (bool) ((int) ($row['whatsapp'] ?? 1) === 1),
            'lat'                => (float) $row['lat'],
            'lng'                => (float) $row['lng'],
            'geocode_precision'  => (string) $row['geocode_precision'],
            'geocode_source'     => (string) $row['geocode_source'],
            'captura_lat'        => $row['captura_lat'] !== null ? (float) $row['captura_lat'] : null,
            'captura_lng'        => $row['captura_lng'] !== null ? (float) $row['captura_lng'] : null,
            'captura_at'         => $row['captura_at'],
            'ocr_confianza'      => !empty($row['ocr_confianza']) ? json_decode((string) $row['ocr_confianza'], true) : null,
            'consentimiento_at'  => $row['consentimiento_at'],
            'aviso_version'      => (string) $row['aviso_version'],
            'validacion'         => (string) $row['validacion'],
            'observaciones'      => $row['observaciones'],
            'created_at'         => $row['created_at'],
            'updated_at'         => $row['updated_at'],
        ];

        if ($includeAudio) {
            $item['audio_pregunta_1'] = $row['audio_pregunta_1'];
            $item['audio_pregunta_2'] = $row['audio_pregunta_2'];
            $item['audio_pregunta_3'] = $row['audio_pregunta_3'];
        }

        return $item;
    }
}

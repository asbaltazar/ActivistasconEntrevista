<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Services\CryptoService;
use CodeIgniter\HTTP\ResponseInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class AdminReportsController extends BaseApiController
{
    private CryptoService $crypto;

    public function __construct()
    {
        parent::__construct();
        $this->crypto = new CryptoService();
    }

    /**
     * GET /api/v1/admin/dashboard (RF-P3: Monitoreo de metas)
     */
    public function dashboard(): ResponseInterface
    {
        $todayStart = date('Y-m-d 00:00:00');
        $weekStart  = date('Y-m-d 00:00:00', strtotime('-7 days'));
        $cutoff48h  = date('Y-m-d H:i:s', strtotime('-48 hours'));

        $totalTalents = $this->db->table('talents')->where('deleted_at IS NULL')->countAllResults();
        $todayTalents = $this->db->table('talents')->where('deleted_at IS NULL')->where('created_at >=', $todayStart)->countAllResults();
        $weekTalents  = $this->db->table('talents')->where('deleted_at IS NULL')->where('created_at >=', $weekStart)->countAllResults();

        $valRows = $this->db->table('talents')
            ->select('validacion, COUNT(*) as total')
            ->where('deleted_at IS NULL')
            ->groupBy('validacion')
            ->get()
            ->getResultArray();

        $byValidacion = ['pendiente' => 0, 'validado' => 0, 'observado' => 0, 'rechazado' => 0];
        foreach ($valRows as $vr) {
            $k = (string) $vr['validacion'];
            if (isset($byValidacion[$k])) {
                $byValidacion[$k] = (int) $vr['total'];
            }
        }

        $activists = $this->db->table('activists a')
            ->select('a.*, u.username, e.nombre as estado_nombre')
            ->join('users u', 'u.id = a.user_id', 'left')
            ->join('cat_estados e', 'e.id = a.estado_id', 'left')
            ->where('a.deleted_at IS NULL')
            ->get()
            ->getResultArray();

        $countsByActivist = [];
        $cntRows = $this->db->table('talents')
            ->select('activist_id, COUNT(*) as total, MAX(created_at) as last_sync')
            ->where('deleted_at IS NULL')
            ->groupBy('activist_id')
            ->get()
            ->getResultArray();
        foreach ($cntRows as $cr) {
            $countsByActivist[(int) $cr['activist_id']] = [
                'total'     => (int) $cr['total'],
                'last_sync' => $cr['last_sync'],
            ];
        }

        $metaGlobal  = 0;
        $ranking     = [];
        $inactivos48 = [];
        $semaforoSummary = ['verde' => 0, 'amarillo' => 0, 'rojo' => 0];

        $nowTs = time();

        foreach ($activists as $act) {
            $actId       = (int) $act['id'];
            $meta        = max(1, (int) $act['meta']);
            $metaGlobal += $meta;
            $registrados = $countsByActivist[$actId]['total'] ?? 0;
            $lastSync    = $countsByActivist[$actId]['last_sync'] ?? null;
            $porcentaje  = round(min(100, ($registrados / $meta) * 100), 1);

            // Cálculo de ritmo esperado vs tiempo transcurrido hasta fecha_limite
            $createdTs  = !empty($act['created_at']) ? (int) strtotime((string) $act['created_at']) : ($nowTs - 86400);
            $deadlineTs = !empty($act['fecha_limite']) ? (int) strtotime((string) $act['fecha_limite'] . ' 23:59:59') : ($nowTs + 86400 * 30);
            $totalSpan  = max(86400, $deadlineTs - $createdTs);
            $elapsed    = max(0, min($totalSpan, $nowTs - $createdTs));
            $expectedPct = round(($elapsed / $totalSpan) * 100, 1);

            if ($porcentaje >= $expectedPct || $porcentaje >= 80) {
                $semaforo = 'verde';
            } elseif ($porcentaje >= ($expectedPct * 0.6)) {
                $semaforo = 'amarillo';
            } else {
                $semaforo = 'rojo';
            }
            $semaforoSummary[$semaforo]++;

            $item = [
                'id'              => $actId,
                'nombre_completo' => trim($act['nombre'] . ' ' . $act['ap_paterno'] . ' ' . ($act['ap_materno'] ?? '')),
                'username'        => $act['username'],
                'estado_nombre'   => $act['estado_nombre'] ?? '',
                'status'          => $act['status'],
                'meta'            => $meta,
                'registrados'     => $registrados,
                'porcentaje'      => $porcentaje,
                'ritmo_esperado'  => $expectedPct,
                'semaforo'        => $semaforo,
                'last_sync_at'    => $lastSync,
                'fecha_limite'    => $act['fecha_limite'],
            ];

            $ranking[] = $item;

            if ($lastSync === null || $lastSync < $cutoff48h) {
                $inactivos48[] = $item;
            }
        }

        usort($ranking, static fn ($a, $b) => $b['registrados'] <=> $a['registrados']);

        if ($metaGlobal === 0) {
            $metaGlobal = 2500;
        }
        $porcentajeGlobal = round(min(100, ($totalTalents / $metaGlobal) * 100), 1);

        // Serie de los últimos 7 días
        $dailyRows = $this->db->table('talents')
            ->select('SUBSTR(created_at, 1, 10) as fecha, COUNT(*) as total')
            ->where('deleted_at IS NULL')
            ->where('created_at >=', $weekStart)
            ->groupBy('SUBSTR(created_at, 1, 10)')
            ->orderBy('fecha', 'ASC')
            ->get()
            ->getResultArray();

        return $this->apiResponse([
            'kpis' => [
                'total'             => $totalTalents,
                'hoy'               => $todayTalents,
                'semana'            => $weekTalents,
                'meta_global'       => $metaGlobal,
                'porcentaje_global' => $porcentajeGlobal,
                'validados'         => $byValidacion['validado'],
                'pendientes'        => $byValidacion['pendiente'],
                'observados'        => $byValidacion['observado'],
                'rechazados'        => $byValidacion['rechazado'],
                'total_activistas'  => count($activists),
            ],
            'semaforo_resumen' => $semaforoSummary,
            'ranking'          => $ranking,
            'inactivos_48h'    => $inactivos48,
            'tendencia_diaria' => $dailyRows,
        ]);
    }

    /**
     * GET /api/v1/admin/reports/{tipo} (RF-P5: Reportes)
     */
    public function reports(string $tipo): ResponseInterface
    {
        $tipo        = strtolower(trim($tipo));
        $currentYear = (int) date('Y');

        switch ($tipo) {
            case 'activista':
                $rows = $this->db->table('activists a')
                    ->select('a.id, a.nombre, a.ap_paterno, a.meta, a.status, e.nombre as estado, COUNT(t.id) as registrados, SUM(CASE WHEN t.validacion = \'validado\' THEN 1 ELSE 0 END) as validados')
                    ->join('cat_estados e', 'e.id = a.estado_id', 'left')
                    ->join('talents t', 't.activist_id = a.id AND t.deleted_at IS NULL', 'left')
                    ->where('a.deleted_at IS NULL')
                    ->groupBy('a.id, a.nombre, a.ap_paterno, a.meta, a.status, e.nombre')
                    ->orderBy('registrados', 'DESC')
                    ->get()
                    ->getResultArray();
                return $this->apiResponse($rows, ['tipo' => $tipo]);

            case 'territorial':
            case 'estado':
                $rows = $this->db->table('talents t')
                    ->select('e.nombre as estado, m.nombre as municipio, COUNT(t.id) as total, SUM(CASE WHEN t.validacion = \'validado\' THEN 1 ELSE 0 END) as validados')
                    ->join('cat_estados e', 'e.id = t.estado_id', 'left')
                    ->join('cat_municipios m', 'm.id = t.municipio_id', 'left')
                    ->where('t.deleted_at IS NULL')
                    ->groupBy('t.estado_id, t.municipio_id, e.nombre, m.nombre')
                    ->orderBy('total', 'DESC')
                    ->get()
                    ->getResultArray();
                return $this->apiResponse($rows, ['tipo' => $tipo]);

            case 'seccion':
                $rows = $this->db->table('talents t')
                    ->select('t.seccion, e.nombre as estado, m.nombre as municipio, COUNT(t.id) as total, SUM(CASE WHEN t.validacion = \'validado\' THEN 1 ELSE 0 END) as validados')
                    ->join('cat_estados e', 'e.id = t.estado_id', 'left')
                    ->join('cat_municipios m', 'm.id = t.municipio_id', 'left')
                    ->where('t.deleted_at IS NULL')
                    ->groupBy('t.seccion, t.estado_id, t.municipio_id, e.nombre, m.nombre')
                    ->orderBy('total', 'DESC')
                    ->get()
                    ->getResultArray();
                return $this->apiResponse($rows, ['tipo' => $tipo]);

            case 'cp':
                $rows = $this->db->table('talents t')
                    ->select('t.codigo_postal, t.colonia, e.nombre as estado, COUNT(t.id) as total')
                    ->join('cat_estados e', 'e.id = t.estado_id', 'left')
                    ->where('t.deleted_at IS NULL')
                    ->groupBy('t.codigo_postal, t.colonia, e.nombre')
                    ->orderBy('total', 'DESC')
                    ->get()
                    ->getResultArray();
                return $this->apiResponse($rows, ['tipo' => $tipo]);

            case 'fecha':
                $rows = $this->db->table('talents')
                    ->select('SUBSTR(created_at, 1, 10) as fecha, COUNT(*) as total')
                    ->where('deleted_at IS NULL')
                    ->groupBy('SUBSTR(created_at, 1, 10)')
                    ->orderBy('fecha', 'ASC')
                    ->get()
                    ->getResultArray();
                return $this->apiResponse($rows, ['tipo' => $tipo]);

            case 'cobertura':
                $rows = $this->db->table('cat_secciones s')
                    ->select('s.seccion, s.tipo, e.nombre as estado, m.nombre as municipio, COUNT(t.id) as talentos_registrados')
                    ->join('cat_estados e', 'e.id = s.estado_id', 'left')
                    ->join('cat_municipios m', 'm.id = s.municipio_id', 'left')
                    ->join('talents t', 't.seccion = s.seccion AND t.deleted_at IS NULL', 'left')
                    ->groupBy('s.id, s.seccion, s.tipo, e.nombre, m.nombre')
                    ->orderBy('talentos_registrados', 'DESC')
                    ->get()
                    ->getResultArray();
                return $this->apiResponse($rows, ['tipo' => $tipo]);

            case 'vigencias':
                $rows = $this->db->table('talents t')
                    ->select('t.id, t.uuid, t.nombre, t.ap_paterno, t.ap_materno, t.seccion, t.vigencia, t.telefono, e.nombre as estado')
                    ->join('cat_estados e', 'e.id = t.estado_id', 'left')
                    ->where('t.deleted_at IS NULL')
                    ->where('t.vigencia <', $currentYear)
                    ->orderBy('t.vigencia', 'ASC')
                    ->get()
                    ->getResultArray();
                return $this->apiResponse($rows, ['tipo' => $tipo, 'current_year' => $currentYear]);

            case 'duplicados':
                $dupPhones = $this->db->table('talents')
                    ->select('telefono, COUNT(*) as repeticiones')
                    ->where('deleted_at IS NULL')
                    ->groupBy('telefono')
                    ->having('COUNT(*) >', 1)
                    ->get()
                    ->getResultArray();

                $dupCurps = $this->db->table('talents')
                    ->select('curp_hash, COUNT(*) as repeticiones')
                    ->where('deleted_at IS NULL')
                    ->where('curp_hash IS NOT NULL')
                    ->groupBy('curp_hash')
                    ->having('COUNT(*) >', 1)
                    ->get()
                    ->getResultArray();

                $groups = [];
                foreach ($dupPhones as $dp) {
                    $items = $this->db->table('talents')
                        ->select('id, uuid, nombre, ap_paterno, ap_materno, telefono, seccion, created_at')
                        ->where('telefono', $dp['telefono'])
                        ->where('deleted_at IS NULL')
                        ->get()
                        ->getResultArray();
                    $groups[] = [
                        'criterio'     => 'Teléfono duplicado (' . $dp['telefono'] . ')',
                        'repeticiones' => (int) $dp['repeticiones'],
                        'registros'    => $items,
                    ];
                }
                foreach ($dupCurps as $dc) {
                    $items = $this->db->table('talents')
                        ->select('id, uuid, nombre, ap_paterno, ap_materno, telefono, seccion, created_at')
                        ->where('curp_hash', $dc['curp_hash'])
                        ->where('deleted_at IS NULL')
                        ->get()
                        ->getResultArray();
                    $groups[] = [
                        'criterio'     => 'CURP duplicada',
                        'repeticiones' => (int) $dc['repeticiones'],
                        'registros'    => $items,
                    ];
                }

                return $this->apiResponse($groups, ['tipo' => $tipo]);

            default:
                return $this->apiResponse(null, null, ["Tipo de reporte '{$tipo}' no soportado."], 422);
        }
    }

    /**
     * GET /api/v1/admin/map/points (RF-P4: Puntos para mapa con MarkerClusterer, Heatmap y polígonos de secciones)
     */
    public function mapPoints(): ResponseInterface
    {
        $bbox            = trim((string) ($this->request->getGet('bbox') ?? ''));
        $activistId      = (int) ($this->request->getGet('activist_id') ?? 0);
        $estadoId        = (int) ($this->request->getGet('estado_id') ?? 0);
        $municipioId     = (int) ($this->request->getGet('municipio_id') ?? 0);
        $seccion         = (int) ($this->request->getGet('seccion') ?? 0);
        $validacion      = trim((string) ($this->request->getGet('validacion') ?? ''));
        $includePolygons = (string) ($this->request->getGet('include_polygons') ?? '0') === '1';

        $builder = $this->db->table('talents t')
            ->select('t.id, t.uuid, t.activist_id, t.nombre, t.ap_paterno, t.seccion, t.colonia, t.codigo_postal, t.lat, t.lng, t.validacion, a.nombre as act_nombre, a.ap_paterno as act_paterno')
            ->join('activists a', 'a.id = t.activist_id', 'left')
            ->where('t.deleted_at IS NULL');

        if ($bbox !== '') {
            $parts = array_map('floatval', explode(',', $bbox));
            if (count($parts) === 4) {
                [$minLng, $minLat, $maxLng, $maxLat] = $parts;
                $builder->where('t.lat >=', $minLat)
                    ->where('t.lat <=', $maxLat)
                    ->where('t.lng >=', $minLng)
                    ->where('t.lng <=', $maxLng);
            }
        }

        if ($activistId > 0) {
            $builder->where('t.activist_id', $activistId);
        }
        if ($estadoId > 0) {
            $builder->where('t.estado_id', $estadoId);
        }
        if ($municipioId > 0) {
            $builder->where('t.municipio_id', $municipioId);
        }
        if ($seccion > 0) {
            $builder->where('t.seccion', $seccion);
        }
        if ($validacion !== '') {
            $builder->where('t.validacion', $validacion);
        }

        $rows   = $builder->limit(5000)->get()->getResultArray();
        $points = [];

        foreach ($rows as $r) {
            $points[] = [
                'id'          => (int) $r['id'],
                'uuid'        => (string) $r['uuid'],
                'activist_id' => (int) $r['activist_id'],
                'activist'    => trim(($r['act_nombre'] ?? '') . ' ' . ($r['act_paterno'] ?? '')),
                'nombre'      => trim($r['nombre'] . ' ' . $r['ap_paterno']),
                'seccion'     => (int) $r['seccion'],
                'colonia'     => (string) $r['colonia'],
                'cp'          => (string) $r['codigo_postal'],
                'lat'         => (float) $r['lat'],
                'lng'         => (float) $r['lng'],
                'validacion'  => (string) $r['validacion'],
            ];
        }

        $polygons = [];
        if ($includePolygons) {
            $secRows = $this->db->table('cat_secciones')
                ->where('geojson IS NOT NULL')
                ->get()
                ->getResultArray();
            foreach ($secRows as $sr) {
                $polygons[] = [
                    'seccion'   => (int) $sr['seccion'],
                    'estado_id' => (int) $sr['estado_id'],
                    'geometry'  => json_decode((string) $sr['geojson'], true),
                ];
            }
        }

        return $this->apiResponse([
            'points'   => $points,
            'polygons' => $polygons,
        ], ['total' => count($points)]);
    }

    /**
     * GET /api/v1/admin/exports
     */
    public function listExports(): ResponseInterface
    {
        $rows = $this->db->table('exports e')
            ->select('e.*, u.username')
            ->join('users u', 'u.id = e.user_id', 'left')
            ->orderBy('e.id', 'DESC')
            ->limit(50)
            ->get()
            ->getResultArray();

        return $this->apiResponse($rows);
    }

    /**
     * POST /api/v1/admin/exports (RF-P6: Exportación Excel, CSV, PDF, KML, GeoJSON con auditoría)
     */
    public function createExport(): ResponseInterface
    {
        $input   = $this->inputData();
        $formato = strtolower(trim((string) ($input['formato'] ?? 'xlsx')));
        $filtros = isset($input['filtros']) && is_array($input['filtros']) ? $input['filtros'] : [];

        if (!in_array($formato, ['xlsx', 'csv', 'pdf', 'kml', 'geojson'], true)) {
            return $this->apiResponse(null, null, ['Formato de exportación no válido (use xlsx, csv, pdf, kml o geojson).'], 422);
        }

        $builder = $this->db->table('talents t')
            ->select('t.*, a.nombre as act_nombre, a.ap_paterno as act_paterno, e.nombre as estado_nombre, m.nombre as municipio_nombre')
            ->join('activists a', 'a.id = t.activist_id', 'left')
            ->join('cat_estados e', 'e.id = t.estado_id', 'left')
            ->join('cat_municipios m', 'm.id = t.municipio_id', 'left')
            ->where('t.deleted_at IS NULL');

        if (!empty($filtros['activist_id'])) {
            $builder->where('t.activist_id', (int) $filtros['activist_id']);
        }
        if (!empty($filtros['estado_id'])) {
            $builder->where('t.estado_id', (int) $filtros['estado_id']);
        }
        if (!empty($filtros['municipio_id'])) {
            $builder->where('t.municipio_id', (int) $filtros['municipio_id']);
        }
        if (!empty($filtros['seccion'])) {
            $builder->where('t.seccion', (int) $filtros['seccion']);
        }
        if (!empty($filtros['codigo_postal'])) {
            $builder->where('t.codigo_postal', (string) $filtros['codigo_postal']);
        }
        if (!empty($filtros['validacion'])) {
            $builder->where('t.validacion', (string) $filtros['validacion']);
        }

        $rows = $builder->orderBy('t.id', 'ASC')->get()->getResultArray();

        $exportDir = rtrim(WRITEPATH, '/\\') . DIRECTORY_SEPARATOR . 'exports';
        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0750, true);
        }

        $fileName = 'talentos_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . $formato;
        $fullPath = $exportDir . DIRECTORY_SEPARATOR . $fileName;
        $relPath  = 'exports/' . $fileName;

        $this->buildExportFile($formato, $rows, $fullPath);

        $now       = date('Y-m-d H:i:s');
        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
        $userId    = (int) ($this->currentUser()['id'] ?? 1);

        $this->db->table('exports')->insert([
            'user_id'    => $userId,
            'filtros'    => json_encode($filtros, JSON_UNESCAPED_UNICODE),
            'formato'    => $formato,
            'path'       => $relPath,
            'total_rows' => count($rows),
            'status'     => 'completado',
            'expires_at' => $expiresAt,
            'created_at' => $now,
        ]);

        $exportId = (int) $this->db->insertID();
        $this->logAudit('export.create', 'exports', $exportId, [
            'formato'    => $formato,
            'filtros'    => $filtros,
            'total_rows' => count($rows),
        ]);

        return $this->apiResponse([
            'id'           => $exportId,
            'formato'      => $formato,
            'total_rows'   => count($rows),
            'status'       => 'completado',
            'download_url' => '/api/v1/admin/exports/' . $exportId,
            'expires_at'   => $expiresAt,
        ], null, null, 201);
    }

    /**
     * GET /api/v1/admin/exports/{id}
     */
    public function downloadExport(int $id): ResponseInterface
    {
        $exp = $this->db->table('exports')->where('id', $id)->get()->getRowArray();
        if ($exp === null) {
            return $this->apiResponse(null, null, ['Exportación no encontrada.'], 404);
        }

        $fullPath = rtrim(WRITEPATH, '/\\') . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string) $exp['path']);
        if (!is_file($fullPath)) {
            return $this->apiResponse(null, null, ['El archivo de exportación ya expiró o no está disponible.'], 404);
        }

        $this->logAudit('export.download', 'exports', $id, ['formato' => $exp['formato']]);

        $formato = (string) $exp['formato'];
        $mime    = match ($formato) {
            'xlsx'    => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'csv'     => 'text/csv; charset=UTF-8',
            'pdf'     => 'application/pdf',
            'kml'     => 'application/vnd.google-earth.kml+xml',
            'geojson' => 'application/geo+json',
            default   => 'application/octet-stream',
        };

        $fileName = basename($fullPath);
        return $this->response
            ->setStatusCode(200)
            ->setHeader('Content-Type', $mime)
            ->setHeader('Content-Disposition', 'attachment; filename="' . $fileName . '"')
            ->setBody((string) file_get_contents($fullPath));
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function buildExportFile(string $formato, array $rows, string $fullPath): void
    {
        $headers = [
            'ID', 'UUID', 'Nombre', 'Apellido Paterno', 'Apellido Materno',
            'Teléfono', 'Calle y Número', 'Colonia', 'CP', 'Estado', 'Municipio',
            'Sección', 'Vigencia', 'Clave de Elector', 'CURP', 'Latitud', 'Longitud',
            'Validación', 'Activista', 'Fecha Registro',
        ];

        $normalized = [];
        foreach ($rows as $r) {
            $normalized[] = [
                (int) $r['id'],
                (string) $r['uuid'],
                (string) $r['nombre'],
                (string) $r['ap_paterno'],
                (string) ($r['ap_materno'] ?? ''),
                (string) $r['telefono'],
                (string) ($this->crypto->decrypt((string) $r['calle_numero']) ?? ''),
                (string) $r['colonia'],
                (string) $r['codigo_postal'],
                (string) ($r['estado_nombre'] ?? ''),
                (string) ($r['municipio_nombre'] ?? ''),
                (int) $r['seccion'],
                (int) $r['vigencia'],
                (string) ($this->crypto->decrypt((string) $r['clave_elector_enc']) ?? ''),
                (string) ($this->crypto->decrypt($r['curp_enc'] !== null ? (string) $r['curp_enc'] : null) ?? ''),
                (float) $r['lat'],
                (float) $r['lng'],
                (string) $r['validacion'],
                trim(($r['act_nombre'] ?? '') . ' ' . ($r['act_paterno'] ?? '')),
                (string) $r['created_at'],
            ];
        }

        if ($formato === 'xlsx') {
            $spreadsheet = new Spreadsheet();
            $sheet       = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Talentos');
            $sheet->fromArray($headers, null, 'A1');
            if (count($normalized) > 0) {
                $sheet->fromArray($normalized, null, 'A2');
            }
            $writer = new Xlsx($spreadsheet);
            $writer->save($fullPath);
            return;
        }

        if ($formato === 'csv') {
            $fp = fopen($fullPath, 'wb');
            if ($fp !== false) {
                fwrite($fp, "\xEF\xBB\xBF"); // UTF-8 BOM para Excel
                fputcsv($fp, $headers);
                foreach ($normalized as $line) {
                    fputcsv($fp, $line);
                }
                fclose($fp);
            }
            return;
        }

        if ($formato === 'pdf') {
            $html = '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><style>'
                . 'body{font-family:DejaVu Sans,sans-serif;font-size:10px;color:#1e293b;}'
                . 'h1{font-size:15px;margin-bottom:8px;}'
                . 'table{width:100%;border-collapse:collapse;}'
                . 'th,td{border:1px solid #cbd5e1;padding:4px;text-align:left;}'
                . 'th{background:#f1f5f9;}'
                . '</style></head><body>'
                . '<h1>Red Ciudadana de Talentos — Reporte de Padrón (' . count($normalized) . ' registros)</h1>'
                . '<table><thead><tr><th>ID</th><th>Nombre Completo</th><th>Teléfono</th><th>Estado/Municipio</th><th>Sección</th><th>CP</th><th>Validación</th></tr></thead><tbody>';

            foreach ($rows as $r) {
                $fullName = htmlspecialchars(trim($r['nombre'] . ' ' . $r['ap_paterno'] . ' ' . ($r['ap_materno'] ?? '')));
                $loc      = htmlspecialchars(($r['estado_nombre'] ?? '') . ' / ' . ($r['municipio_nombre'] ?? ''));
                $html .= "<tr><td>{$r['id']}</td><td>{$fullName}</td><td>{$r['telefono']}</td><td>{$loc}</td><td>{$r['seccion']}</td><td>{$r['codigo_postal']}</td><td>{$r['validacion']}</td></tr>";
            }
            $html .= '</tbody></table></body></html>';

            $options = new Options();
            $options->set('isRemoteEnabled', false);
            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'landscape');
            $dompdf->render();
            file_put_contents($fullPath, $dompdf->output());
            return;
        }

        if ($formato === 'geojson') {
            $features = [];
            foreach ($rows as $r) {
                $features[] = [
                    'type'       => 'Feature',
                    'geometry'   => [
                        'type'        => 'Point',
                        'coordinates' => [(float) $r['lng'], (float) $r['lat']],
                    ],
                    'properties' => [
                        'id'         => (int) $r['id'],
                        'uuid'       => (string) $r['uuid'],
                        'nombre'     => trim($r['nombre'] . ' ' . $r['ap_paterno']),
                        'seccion'    => (int) $r['seccion'],
                        'cp'         => (string) $r['codigo_postal'],
                        'validacion' => (string) $r['validacion'],
                    ],
                ];
            }
            file_put_contents($fullPath, json_encode([
                'type'     => 'FeatureCollection',
                'features' => $features,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return;
        }

        if ($formato === 'kml') {
            $kml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
                . '<kml xmlns="http://www.opengis.net/kml/2.2"><Document><name>Red Ciudadana de Talentos</name>';
            foreach ($rows as $r) {
                $name = htmlspecialchars(trim($r['nombre'] . ' ' . $r['ap_paterno']));
                $desc = htmlspecialchars("Sección: {$r['seccion']} | CP: {$r['codigo_postal']} | Estatus: {$r['validacion']}");
                $kml .= "<Placemark><name>{$name}</name><description>{$desc}</description><Point><coordinates>{$r['lng']},{$r['lat']},0</coordinates></Point></Placemark>";
            }
            $kml .= '</Document></kml>';
            file_put_contents($fullPath, $kml);
        }
    }

    /**
     * GET /api/v1/admin/audit (RF-P9: Bitácora de auditoría)
     */
    public function audit(): ResponseInterface
    {
        $page    = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = min(100, max(10, (int) ($this->request->getGet('per_page') ?? 30)));
        $action  = trim((string) ($this->request->getGet('action') ?? ''));

        $builder = $this->db->table('audit_log l')
            ->select('l.*, u.username, u.role')
            ->join('users u', 'u.id = l.user_id', 'left');

        if ($action !== '') {
            $builder->like('l.action', $action);
        }

        $total = $builder->countAllResults(false);
        $rows  = $builder->orderBy('l.id', 'DESC')
            ->limit($perPage, ($page - 1) * $perPage)
            ->get()
            ->getResultArray();

        return $this->apiResponse($rows, [
            'page'     => $page,
            'per_page' => $perPage,
            'total'    => $total,
        ]);
    }

    /**
     * GET /api/v1/admin/settings (RF-P10: Configuración)
     */
    public function getSettings(): ResponseInterface
    {
        $rows = $this->db->table('settings')->get()->getResultArray();
        $map  = [];
        foreach ($rows as $r) {
            $map[(string) $r['setting_key']] = $r['setting_value'];
        }
        return $this->apiResponse($map);
    }

    /**
     * PUT /api/v1/admin/settings
     */
    public function updateSettings(): ResponseInterface
    {
        if (($deny = $this->requireWriteRole()) !== null) {
            return $deny;
        }

        $input = $this->inputData();
        $now   = date('Y-m-d H:i:s');

        foreach ($input as $key => $val) {
            if (!is_string($key) || !is_scalar($val)) {
                continue;
            }
            $exists = $this->db->table('settings')->where('setting_key', $key)->countAllResults();
            if ($exists > 0) {
                $this->db->table('settings')->where('setting_key', $key)->update([
                    'setting_value' => (string) $val,
                    'updated_at'    => $now,
                ]);
            } else {
                $this->db->table('settings')->insert([
                    'setting_key'   => $key,
                    'setting_value' => (string) $val,
                    'updated_at'    => $now,
                ]);
            }
        }

        $this->logAudit('settings.update', 'settings', null, ['keys' => array_keys($input)]);
        return $this->getSettings();
    }
}

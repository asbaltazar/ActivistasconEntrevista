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

        // Leer metas por clasificación configuradas en settings
        $settingsRows = $this->db->table('settings')
            ->whereIn('setting_key', [
                'meta_conector_default',
                'meta_experto_default',
                'meta_vendedor_default',
                'meta_activista_default',
                'meta_global_default',
            ])
            ->get()
            ->getResultArray();

        $settingsMap = [];
        foreach ($settingsRows as $sr) {
            $settingsMap[(string) $sr['setting_key']] = (string) $sr['setting_value'];
        }

        $defConector = max(1, (int) ($settingsMap['meta_conector_default'] ?? 50));
        $defExperto  = max(1, (int) ($settingsMap['meta_experto_default'] ?? 50));
        $defVendedor = max(1, (int) ($settingsMap['meta_vendedor_default'] ?? 50));

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
            ->select('a.*, u.username')
            ->join('users u', 'u.id = a.user_id', 'left')
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

        // Conteo por activista y clasificación (Conector, Experto, Vendedor)
        $clasifByActivist = [];
        $globalByClasif   = [
            'Conector' => 0,
            'Experto'  => 0,
            'Vendedor' => 0,
        ];
        $clasifRows = $this->db->table('talents')
            ->select('activist_id, clasificacion, COUNT(*) as total')
            ->where('deleted_at IS NULL')
            ->where('validacion !=', 'rechazado')
            ->groupBy('activist_id, clasificacion')
            ->get()
            ->getResultArray();

        foreach ($clasifRows as $clr) {
            $aId = (int) $clr['activist_id'];
            $cat = (string) ($clr['clasificacion'] ?? 'Conector');
            $cnt = (int) $clr['total'];
            if (!isset($clasifByActivist[$aId])) {
                $clasifByActivist[$aId] = ['Conector' => 0, 'Experto' => 0, 'Vendedor' => 0];
            }
            if (isset($clasifByActivist[$aId][$cat])) {
                $clasifByActivist[$aId][$cat] = $cnt;
            }
            if (isset($globalByClasif[$cat])) {
                $globalByClasif[$cat] += $cnt;
            }
        }

        $metaGlobal         = 0;
        $metaGlobalConector = 0;
        $metaGlobalExperto  = 0;
        $metaGlobalVendedor = 0;
        $ranking            = [];
        $inactivos48        = [];
        $semaforoSummary    = ['verde' => 0, 'amarillo' => 0, 'rojo' => 0];

        $nowTs = time();

        foreach ($activists as $act) {
            $actId        = (int) $act['id'];
            $metaConector = max(1, (int) ($act['meta_conector'] ?? $defConector));
            $metaExperto  = max(1, (int) ($act['meta_experto'] ?? $defExperto));
            $metaVendedor = max(1, (int) ($act['meta_vendedor'] ?? $defVendedor));
            $meta         = $metaConector + $metaExperto + $metaVendedor;

            $metaGlobal         += $meta;
            $metaGlobalConector += $metaConector;
            $metaGlobalExperto  += $metaExperto;
            $metaGlobalVendedor += $metaVendedor;

            $registrados = $countsByActivist[$actId]['total'] ?? 0;
            $lastSync    = $countsByActivist[$actId]['last_sync'] ?? null;
            $porcentaje  = round(min(100, ($registrados / $meta) * 100), 1);

            $conReg = $clasifByActivist[$actId]['Conector'] ?? 0;
            $expReg = $clasifByActivist[$actId]['Experto'] ?? 0;
            $venReg = $clasifByActivist[$actId]['Vendedor'] ?? 0;

            $createdTs   = !empty($act['created_at']) ? (int) strtotime((string) $act['created_at']) : ($nowTs - 86400);
            $deadlineTs  = !empty($act['fecha_limite']) ? (int) strtotime((string) $act['fecha_limite'] . ' 23:59:59') : ($nowTs + 86400 * 30);
            $totalSpan   = max(86400, $deadlineTs - $createdTs);
            $elapsed     = max(0, min($totalSpan, $nowTs - $createdTs));
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
                'id'                   => $actId,
                'nombre_completo'      => trim($act['nombre'] . ' ' . $act['ap_paterno'] . ' ' . ($act['ap_materno'] ?? '')),
                'username'             => $act['username'],
                'domicilio'            => $act['domicilio'] ?? null,
                'region_trabajo'       => (int) ($act['region_trabajo'] ?? 1),
                'estado_nombre'        => 'Estado ' . str_pad((string) ($act['estado_id'] ?? 9), 2, '0', STR_PAD_LEFT),
                'status'               => $act['status'],
                'meta'                 => $meta,
                'meta_conector'        => $metaConector,
                'meta_experto'         => $metaExperto,
                'meta_vendedor'        => $metaVendedor,
                'conector_registrados' => $conReg,
                'experto_registrados'  => $expReg,
                'vendedor_registrados' => $venReg,
                'conector_porcentaje'  => round(min(100, ($conReg / $metaConector) * 100), 1),
                'experto_porcentaje'   => round(min(100, ($expReg / $metaExperto) * 100), 1),
                'vendedor_porcentaje'  => round(min(100, ($venReg / $metaVendedor) * 100), 1),
                'registrados'          => $registrados,
                'porcentaje'           => $porcentaje,
                'ritmo_esperado'       => $expectedPct,
                'semaforo'             => $semaforo,
                'last_sync_at'         => $lastSync,
                'fecha_limite'         => $act['fecha_limite'],
            ];

            $ranking[] = $item;

            if ($lastSync === null || $lastSync < $cutoff48h) {
                $inactivos48[] = $item;
            }
        }

        usort($ranking, static fn ($a, $b) => $b['registrados'] <=> $a['registrados']);

        if ($metaGlobal === 0) {
            $metaGlobal         = $defConector + $defExperto + $defVendedor;
            $metaGlobalConector = $defConector;
            $metaGlobalExperto  = $defExperto;
            $metaGlobalVendedor = $defVendedor;
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
                'total'                  => $totalTalents,
                'hoy'                    => $todayTalents,
                'semana'                 => $weekTalents,
                'meta_global'            => $metaGlobal,
                'meta_por_activista'     => $defConector + $defExperto + $defVendedor,
                'meta_conector_default'  => $defConector,
                'meta_experto_default'   => $defExperto,
                'meta_vendedor_default'  => $defVendedor,
                'porcentaje_global'      => $porcentajeGlobal,
                'validados'              => $byValidacion['validado'],
                'pendientes'             => $byValidacion['pendiente'],
                'observados'             => $byValidacion['observado'],
                'rechazados'             => $byValidacion['rechazado'],
                'total_activistas'       => count($activists),
            ],
            'clasificaciones_resumen' => [
                'Conector' => [
                    'registrados'        => $globalByClasif['Conector'],
                    'meta_por_activista' => $defConector,
                    'meta_global'        => $metaGlobalConector,
                    'porcentaje'         => round(min(100, ($globalByClasif['Conector'] / max(1, $metaGlobalConector)) * 100), 1),
                ],
                'Experto' => [
                    'registrados'        => $globalByClasif['Experto'],
                    'meta_por_activista' => $defExperto,
                    'meta_global'        => $metaGlobalExperto,
                    'porcentaje'         => round(min(100, ($globalByClasif['Experto'] / max(1, $metaGlobalExperto)) * 100), 1),
                ],
                'Vendedor' => [
                    'registrados'        => $globalByClasif['Vendedor'],
                    'meta_por_activista' => $defVendedor,
                    'meta_global'        => $metaGlobalVendedor,
                    'porcentaje'         => round(min(100, ($globalByClasif['Vendedor'] / max(1, $metaGlobalVendedor)) * 100), 1),
                ],
            ],
            'semaforo_resumen' => $semaforoSummary,
            'ranking'          => $ranking,
            'inactivos_48h'    => $inactivos48,
            'tendencia_diaria' => $dailyRows,
        ]);
    }

    /**
     * Parsea un filtro que puede venir como entero, cadena separada por comas ("1,2,3") o arreglo ([1, 2]).
     * Si viene vacío o "todos", devuelve arreglo vacío (= todos).
     *
     * @param mixed $raw
     * @return list<int>
     */
    private function parseMultiIntList($raw): array
    {
        if ($raw === null || $raw === '' || $raw === 'todos' || $raw === 'all') {
            return [];
        }
        $items = is_array($raw) ? $raw : explode(',', (string) $raw);
        $out   = [];
        foreach ($items as $v) {
            $n = (int) trim((string) $v);
            if ($n > 0 && !in_array($n, $out, true)) {
                $out[] = $n;
            }
        }
        return $out;
    }

    /**
     * Aplica filtros de uno/varios/todos los Activistas y una/varias/todas las Regiones de Trabajo.
     *
     * @param \CodeIgniter\Database\BaseBuilder $builder
     * @param list<int>                         $activistIds
     * @param list<int>                         $regiones
     */
    private function applyActivistAndRegionFilters(
        $builder,
        array $activistIds,
        array $regiones,
        string $talentAlias = 't',
        string $activistAlias = 'a'
    ): void {
        if (count($activistIds) > 0) {
            $builder->whereIn("{$talentAlias}.activist_id", $activistIds);
        }
        if (count($regiones) > 0) {
            $inList = implode(',', array_map('intval', $regiones));
            $builder->where("COALESCE({$talentAlias}.region_trabajo, {$activistAlias}.region_trabajo, 1) IN ({$inList})", null, false);
        }
    }

    /**
     * Obtiene catálogo de activistas y regiones de trabajo disponibles para los selectores de filtros.
     *
     * @return array{activists: list<array<string, mixed>>, regiones: list<int>}
     */
    private function getFilterCatalogs(): array
    {
        $actRows = $this->db->table('activists a')
            ->select('a.id, a.nombre, a.ap_paterno, a.ap_materno, a.region_trabajo, a.domicilio, u.username')
            ->join('users u', 'u.id = a.user_id', 'left')
            ->where('a.deleted_at IS NULL')
            ->orderBy('a.region_trabajo', 'ASC')
            ->orderBy('a.nombre', 'ASC')
            ->get()
            ->getResultArray();

        $activists = [];
        $regSet    = [];

        foreach ($actRows as $ar) {
            $reg = max(1, (int) ($ar['region_trabajo'] ?? 1));
            $regSet[$reg] = true;
            $activists[] = [
                'id'              => (int) $ar['id'],
                'nombre_completo' => trim($ar['nombre'] . ' ' . $ar['ap_paterno'] . ' ' . ($ar['ap_materno'] ?? '')),
                'username'        => (string) ($ar['username'] ?? ''),
                'region_trabajo'  => $reg,
                'domicilio'       => $ar['domicilio'] ?? null,
            ];
        }

        $talRegRows = $this->db->table('talents')
            ->select('DISTINCT region_trabajo', false)
            ->where('deleted_at IS NULL')
            ->where('region_trabajo IS NOT NULL')
            ->get()
            ->getResultArray();

        foreach ($talRegRows as $tr) {
            $r = (int) ($tr['region_trabajo'] ?? 0);
            if ($r > 0) {
                $regSet[$r] = true;
            }
        }

        $regiones = array_keys($regSet);
        sort($regiones, SORT_NUMERIC);

        return [
            'activists' => $activists,
            'regiones'  => $regiones,
        ];
    }

    /**
     * GET /api/v1/admin/reports/{tipo} (RF-P5: Reportes)
     */
    public function reports(string $tipo): ResponseInterface
    {
        $tipo        = strtolower(trim($tipo));
        $currentYear = (int) date('Y');
        $activistIds = $this->parseMultiIntList($this->request->getGet('activist_ids') ?? $this->request->getGet('activist_id'));
        $regiones    = $this->parseMultiIntList($this->request->getGet('regiones') ?? $this->request->getGet('region_trabajo'));
        $catalogs    = $this->getFilterCatalogs();
        $metaBase    = [
            'tipo'         => $tipo,
            'activist_ids' => $activistIds,
            'regiones'     => $regiones,
            'catalogs'     => $catalogs,
        ];

        switch ($tipo) {
            case 'clasificacion':
                $builder = $this->db->table('talents t')
                    ->select('t.clasificacion, COUNT(t.id) as total, SUM(CASE WHEN t.validacion = \'validado\' THEN 1 ELSE 0 END) as validados')
                    ->join('activists a', 'a.id = t.activist_id', 'left')
                    ->where('t.deleted_at IS NULL');
                $this->applyActivistAndRegionFilters($builder, $activistIds, $regiones);
                $rows = $builder
                    ->groupBy('t.clasificacion')
                    ->orderBy('total', 'DESC')
                    ->get()
                    ->getResultArray();
                return $this->apiResponse($rows, $metaBase);

            case 'activista':
                $builder = $this->db->table('activists a')
                    ->select('a.id, CONCAT_WS(\' \', a.nombre, a.ap_paterno, a.ap_materno) as activista, a.region_trabajo, a.domicilio, a.meta, a.status, COUNT(t.id) as registrados, SUM(CASE WHEN t.validacion = \'validado\' THEN 1 ELSE 0 END) as validados')
                    ->join('talents t', 't.activist_id = a.id AND t.deleted_at IS NULL', 'left')
                    ->where('a.deleted_at IS NULL');
                if (count($activistIds) > 0) {
                    $builder->whereIn('a.id', $activistIds);
                }
                if (count($regiones) > 0) {
                    $builder->whereIn('a.region_trabajo', $regiones);
                }
                $rows = $builder
                    ->groupBy('a.id, a.nombre, a.ap_paterno, a.ap_materno, a.region_trabajo, a.domicilio, a.meta, a.status')
                    ->orderBy('registrados', 'DESC')
                    ->get()
                    ->getResultArray();
                return $this->apiResponse($rows, $metaBase);

            case 'region':
                $builder = $this->db->table('talents t')
                    ->select('COALESCE(t.region_trabajo, a.region_trabajo, 1) as region_trabajo, COUNT(DISTINCT t.activist_id) as activistas, COUNT(t.id) as total, SUM(CASE WHEN t.validacion = \'validado\' THEN 1 ELSE 0 END) as validados')
                    ->join('activists a', 'a.id = t.activist_id', 'left')
                    ->where('t.deleted_at IS NULL');
                $this->applyActivistAndRegionFilters($builder, $activistIds, $regiones);
                $rows = $builder
                    ->groupBy('COALESCE(t.region_trabajo, a.region_trabajo, 1)')
                    ->orderBy('region_trabajo', 'ASC')
                    ->get()
                    ->getResultArray();
                return $this->apiResponse($rows, $metaBase);

            case 'territorial':
            case 'estado':
                $builder = $this->db->table('talents t')
                    ->select('t.estado_clave, t.municipio_clave, t.localidad_clave, COALESCE(t.region_trabajo, a.region_trabajo, 1) as region_trabajo, COUNT(t.id) as total, SUM(CASE WHEN t.validacion = \'validado\' THEN 1 ELSE 0 END) as validados')
                    ->join('activists a', 'a.id = t.activist_id', 'left')
                    ->where('t.deleted_at IS NULL');
                $this->applyActivistAndRegionFilters($builder, $activistIds, $regiones);
                $rows = $builder
                    ->groupBy('t.estado_clave, t.municipio_clave, t.localidad_clave, COALESCE(t.region_trabajo, a.region_trabajo, 1)')
                    ->orderBy('total', 'DESC')
                    ->get()
                    ->getResultArray();
                return $this->apiResponse($rows, $metaBase);

            case 'seccion':
            case 'cobertura':
                $builder = $this->db->table('talents t')
                    ->select('t.seccion, t.estado_clave, t.municipio_clave, COALESCE(t.region_trabajo, a.region_trabajo, 1) as region_trabajo, COUNT(t.id) as total, SUM(CASE WHEN t.validacion = \'validado\' THEN 1 ELSE 0 END) as validados')
                    ->join('activists a', 'a.id = t.activist_id', 'left')
                    ->where('t.deleted_at IS NULL');
                $this->applyActivistAndRegionFilters($builder, $activistIds, $regiones);
                $rows = $builder
                    ->groupBy('t.seccion, t.estado_clave, t.municipio_clave, COALESCE(t.region_trabajo, a.region_trabajo, 1)')
                    ->orderBy('total', 'DESC')
                    ->get()
                    ->getResultArray();
                return $this->apiResponse($rows, $metaBase);

            case 'cp':
                $builder = $this->db->table('talents t')
                    ->select('t.codigo_postal, t.estado_clave, COALESCE(t.region_trabajo, a.region_trabajo, 1) as region_trabajo, COUNT(t.id) as total')
                    ->join('activists a', 'a.id = t.activist_id', 'left')
                    ->where('t.deleted_at IS NULL');
                $this->applyActivistAndRegionFilters($builder, $activistIds, $regiones);
                $rows = $builder
                    ->groupBy('t.codigo_postal, t.estado_clave, COALESCE(t.region_trabajo, a.region_trabajo, 1)')
                    ->orderBy('total', 'DESC')
                    ->get()
                    ->getResultArray();
                return $this->apiResponse($rows, $metaBase);

            case 'fecha':
                $builder = $this->db->table('talents t')
                    ->select('SUBSTR(t.created_at, 1, 10) as fecha, COUNT(*) as total')
                    ->join('activists a', 'a.id = t.activist_id', 'left')
                    ->where('t.deleted_at IS NULL');
                $this->applyActivistAndRegionFilters($builder, $activistIds, $regiones);
                $rows = $builder
                    ->groupBy('SUBSTR(t.created_at, 1, 10)')
                    ->orderBy('fecha', 'ASC')
                    ->get()
                    ->getResultArray();
                return $this->apiResponse($rows, $metaBase);

            case 'vigencias':
                $builder = $this->db->table('talents t')
                    ->select('t.id, t.uuid, t.nombre, t.ap_paterno, t.ap_materno, t.clasificacion, t.seccion, t.vigencia, t.telefono, t.estado_clave, CONCAT_WS(\' \', a.nombre, a.ap_paterno, a.ap_materno) as activista, COALESCE(t.region_trabajo, a.region_trabajo, 1) as region_trabajo')
                    ->join('activists a', 'a.id = t.activist_id', 'left')
                    ->where('t.deleted_at IS NULL')
                    ->where('t.vigencia <', $currentYear);
                $this->applyActivistAndRegionFilters($builder, $activistIds, $regiones);
                $rows = $builder
                    ->orderBy('t.vigencia', 'ASC')
                    ->get()
                    ->getResultArray();
                return $this->apiResponse($rows, array_merge($metaBase, ['current_year' => $currentYear]));

            case 'duplicados':
                $dupPhonesBuilder = $this->db->table('talents t')
                    ->select('t.telefono, COUNT(*) as repeticiones')
                    ->join('activists a', 'a.id = t.activist_id', 'left')
                    ->where('t.deleted_at IS NULL');
                $this->applyActivistAndRegionFilters($dupPhonesBuilder, $activistIds, $regiones);
                $dupPhones = $dupPhonesBuilder
                    ->groupBy('t.telefono')
                    ->having('COUNT(*) >', 1)
                    ->get()
                    ->getResultArray();

                $dupCurpsBuilder = $this->db->table('talents t')
                    ->select('t.curp_hash, COUNT(*) as repeticiones')
                    ->join('activists a', 'a.id = t.activist_id', 'left')
                    ->where('t.deleted_at IS NULL')
                    ->where('t.curp_hash IS NOT NULL');
                $this->applyActivistAndRegionFilters($dupCurpsBuilder, $activistIds, $regiones);
                $dupCurps = $dupCurpsBuilder
                    ->groupBy('t.curp_hash')
                    ->having('COUNT(*) >', 1)
                    ->get()
                    ->getResultArray();

                $groups = [];
                foreach ($dupPhones as $dp) {
                    $itemsBuilder = $this->db->table('talents t')
                        ->select('t.id, t.uuid, t.nombre, t.ap_paterno, t.ap_materno, t.telefono, t.seccion, CONCAT_WS(\' \', a.nombre, a.ap_paterno, a.ap_materno) as activista, COALESCE(t.region_trabajo, a.region_trabajo, 1) as region_trabajo, t.created_at')
                        ->join('activists a', 'a.id = t.activist_id', 'left')
                        ->where('t.telefono', $dp['telefono'])
                        ->where('t.deleted_at IS NULL');
                    $this->applyActivistAndRegionFilters($itemsBuilder, $activistIds, $regiones);
                    $items = $itemsBuilder->get()->getResultArray();
                    $groups[] = [
                        'criterio'     => 'Teléfono duplicado (' . $dp['telefono'] . ')',
                        'repeticiones' => (int) $dp['repeticiones'],
                        'registros'    => $items,
                    ];
                }
                foreach ($dupCurps as $dc) {
                    $itemsBuilder = $this->db->table('talents t')
                        ->select('t.id, t.uuid, t.nombre, t.ap_paterno, t.ap_materno, t.telefono, t.seccion, CONCAT_WS(\' \', a.nombre, a.ap_paterno, a.ap_materno) as activista, COALESCE(t.region_trabajo, a.region_trabajo, 1) as region_trabajo, t.created_at')
                        ->join('activists a', 'a.id = t.activist_id', 'left')
                        ->where('t.curp_hash', $dc['curp_hash'])
                        ->where('t.deleted_at IS NULL');
                    $this->applyActivistAndRegionFilters($itemsBuilder, $activistIds, $regiones);
                    $items = $itemsBuilder->get()->getResultArray();
                    $groups[] = [
                        'criterio'     => 'CURP duplicada',
                        'repeticiones' => (int) $dc['repeticiones'],
                        'registros'    => $items,
                    ];
                }

                return $this->apiResponse($groups, $metaBase);

            default:
                return $this->apiResponse(null, null, ["Tipo de reporte '{$tipo}' no soportado."], 422);
        }
    }

    /**
     * GET /api/v1/admin/map/points (RF-P4: Puntos para mapa)
     */
    public function mapPoints(): ResponseInterface
    {
        $bbox          = trim((string) ($this->request->getGet('bbox') ?? ''));
        $activistId    = (int) ($this->request->getGet('activist_id') ?? 0);
        $clasificacion = trim((string) ($this->request->getGet('clasificacion') ?? ''));
        $estadoClave   = trim((string) ($this->request->getGet('estado_clave') ?? ''));
        $seccion       = (int) ($this->request->getGet('seccion') ?? 0);
        $validacion    = trim((string) ($this->request->getGet('validacion') ?? ''));

        $builder = $this->db->table('talents t')
            ->select('t.id, t.uuid, t.activist_id, t.clasificacion, t.nombre, t.ap_paterno, t.seccion, t.codigo_postal, t.estado_clave, t.municipio_clave, t.lat, t.lng, t.validacion, a.nombre as act_nombre, a.ap_paterno as act_paterno')
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
        if ($clasificacion !== '') {
            $builder->where('t.clasificacion', $clasificacion);
        }
        if ($estadoClave !== '' && $estadoClave !== '0') {
            $builder->where('t.estado_clave', $estadoClave);
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
                'id'            => (int) $r['id'],
                'uuid'          => (string) $r['uuid'],
                'activist_id'   => (int) $r['activist_id'],
                'activist'      => trim(($r['act_nombre'] ?? '') . ' ' . ($r['act_paterno'] ?? '')),
                'clasificacion' => (string) ($r['clasificacion'] ?? 'Conector'),
                'nombre'        => trim($r['nombre'] . ' ' . $r['ap_paterno']),
                'seccion'       => (int) $r['seccion'],
                'cp'            => (string) $r['codigo_postal'],
                'estado_clave'  => (string) $r['estado_clave'],
                'lat'           => (float) $r['lat'],
                'lng'           => (float) $r['lng'],
                'validacion'    => (string) $r['validacion'],
            ];
        }

        return $this->apiResponse([
            'points'   => $points,
            'polygons' => [],
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
            ->select('t.*, a.nombre as act_nombre, a.ap_paterno as act_paterno, a.ap_materno as act_materno, a.domicilio as act_domicilio, COALESCE(t.region_trabajo, a.region_trabajo, 1) as act_region_trabajo')
            ->join('activists a', 'a.id = t.activist_id', 'left')
            ->where('t.deleted_at IS NULL');

        $activistIds = $this->parseMultiIntList($filtros['activist_ids'] ?? $filtros['activist_id'] ?? null);
        $regiones    = $this->parseMultiIntList($filtros['regiones'] ?? $filtros['region_trabajo'] ?? null);
        $this->applyActivistAndRegionFilters($builder, $activistIds, $regiones);

        if (!empty($filtros['clasificacion'])) {
            $builder->where('t.clasificacion', (string) $filtros['clasificacion']);
        }
        if (!empty($filtros['estado_clave'])) {
            $builder->where('t.estado_clave', (string) $filtros['estado_clave']);
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
            'ID', 'UUID', 'Clasificación', 'Nombre', 'Apellido Paterno', 'Apellido Materno',
            'Teléfono', 'Domicilio Completo', 'CP', 'Clave Estado', 'Clave Municipio', 'Clave Localidad',
            'Sección', 'Vigencia', 'Clave de Elector', 'CURP', 'Latitud', 'Longitud',
            'Validación', 'Activista', 'Región de Trabajo', 'Fecha Registro',
        ];

        $normalized = [];
        foreach ($rows as $r) {
            $actFullName   = trim(($r['act_nombre'] ?? '') . ' ' . ($r['act_paterno'] ?? '') . ' ' . ($r['act_materno'] ?? ''));
            $regionTrabajo = (int) ($r['act_region_trabajo'] ?? $r['region_trabajo'] ?? 1);

            $normalized[] = [
                (int) $r['id'],
                (string) $r['uuid'],
                (string) ($r['clasificacion'] ?? 'Conector'),
                (string) $r['nombre'],
                (string) $r['ap_paterno'],
                (string) ($r['ap_materno'] ?? ''),
                (string) $r['telefono'],
                (string) ($this->crypto->decrypt((string) $r['domicilio_completo']) ?? ''),
                (string) $r['codigo_postal'],
                (string) $r['estado_clave'],
                (string) $r['municipio_clave'],
                (string) $r['localidad_clave'],
                (int) $r['seccion'],
                (int) $r['vigencia'],
                (string) ($this->crypto->decrypt((string) $r['clave_elector_enc']) ?? ''),
                (string) ($this->crypto->decrypt($r['curp_enc'] !== null ? (string) $r['curp_enc'] : null) ?? ''),
                (float) $r['lat'],
                (float) $r['lng'],
                (string) $r['validacion'],
                $actFullName,
                $regionTrabajo,
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
                fwrite($fp, "\xEF\xBB\xBF");
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
                . '<table><thead><tr><th>ID</th><th>Clasificación</th><th>Nombre Completo</th><th>Teléfono</th><th>Edo/Mun/Loc</th><th>Sección</th><th>CP</th><th>Activista</th><th>Región</th><th>Validación</th></tr></thead><tbody>';

            foreach ($rows as $r) {
                $fullName    = htmlspecialchars(trim($r['nombre'] . ' ' . $r['ap_paterno'] . ' ' . ($r['ap_materno'] ?? '')));
                $loc         = htmlspecialchars("{$r['estado_clave']}/{$r['municipio_clave']}/{$r['localidad_clave']}");
                $clasif      = htmlspecialchars((string) ($r['clasificacion'] ?? 'Conector'));
                $actFullName = htmlspecialchars(trim(($r['act_nombre'] ?? '') . ' ' . ($r['act_paterno'] ?? '') . ' ' . ($r['act_materno'] ?? '')));
                $regTrab     = (int) ($r['act_region_trabajo'] ?? $r['region_trabajo'] ?? 1);
                $html .= "<tr><td>{$r['id']}</td><td>{$clasif}</td><td>{$fullName}</td><td>{$r['telefono']}</td><td>{$loc}</td><td>{$r['seccion']}</td><td>{$r['codigo_postal']}</td><td>{$actFullName}</td><td>{$regTrab}</td><td>{$r['validacion']}</td></tr>";
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
                $actFullName   = trim(($r['act_nombre'] ?? '') . ' ' . ($r['act_paterno'] ?? '') . ' ' . ($r['act_materno'] ?? ''));
                $regionTrabajo = (int) ($r['act_region_trabajo'] ?? $r['region_trabajo'] ?? 1);
                $features[] = [
                    'type'       => 'Feature',
                    'geometry'   => [
                        'type'        => 'Point',
                        'coordinates' => [(float) $r['lng'], (float) $r['lat']],
                    ],
                    'properties' => [
                        'id'             => (int) $r['id'],
                        'uuid'           => (string) $r['uuid'],
                        'clasificacion'  => (string) ($r['clasificacion'] ?? 'Conector'),
                        'nombre'         => trim($r['nombre'] . ' ' . $r['ap_paterno']),
                        'seccion'        => (int) $r['seccion'],
                        'cp'             => (string) $r['codigo_postal'],
                        'activista'      => $actFullName,
                        'region_trabajo' => $regionTrabajo,
                        'validacion'     => (string) $r['validacion'],
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
                $name          = htmlspecialchars(trim($r['nombre'] . ' ' . $r['ap_paterno']));
                $actFullName   = trim(($r['act_nombre'] ?? '') . ' ' . ($r['act_paterno'] ?? '') . ' ' . ($r['act_materno'] ?? ''));
                $regionTrabajo = (int) ($r['act_region_trabajo'] ?? $r['region_trabajo'] ?? 1);
                $desc          = htmlspecialchars("Clasificación: {$r['clasificacion']} | Activista: {$actFullName} | Región: {$regionTrabajo} | Sección: {$r['seccion']} | CP: {$r['codigo_postal']} | Estatus: {$r['validacion']}");
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
        $map  = [
            'aviso_privacidad_version' => '1.0',
            'meta_global_default'      => '2500',
            'meta_conector_default'    => '50',
            'meta_experto_default'     => '50',
            'meta_vendedor_default'    => '50',
            'meta_activista_default'   => '150',
        ];
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

        if (isset($input['meta_conector_default']) || isset($input['meta_experto_default']) || isset($input['meta_vendedor_default'])) {
            $con = max(1, (int) ($input['meta_conector_default'] ?? 50));
            $exp = max(1, (int) ($input['meta_experto_default'] ?? 50));
            $ven = max(1, (int) ($input['meta_vendedor_default'] ?? 50));
            $input['meta_conector_default']  = (string) $con;
            $input['meta_experto_default']   = (string) $exp;
            $input['meta_vendedor_default']  = (string) $ven;
            $input['meta_activista_default'] = (string) ($con + $exp + $ven);

            // Actualizar metas por clasificación de los activistas activos
            $this->db->table('activists')
                ->where('deleted_at IS NULL')
                ->update([
                    'meta_conector' => $con,
                    'meta_experto'  => $exp,
                    'meta_vendedor' => $ven,
                    'meta'          => $con + $exp + $ven,
                    'updated_at'    => $now,
                ]);
        }

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

<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Services\GeocodeService;
use App\Services\IneOcrService;
use CodeIgniter\HTTP\ResponseInterface;

class CatalogsController extends BaseApiController
{
    public function index(): ResponseInterface
    {
        $scope     = (string) ($this->request->getGet('scope') ?? 'zona');
        $estadoId  = (int) ($this->request->getGet('estado_id') ?? 0);
        $activist  = $this->currentActivist();

        if ($estadoId === 0 && $scope === 'zona' && $activist !== null) {
            $estadoId = (int) $activist['estado_id'];
        }

        $estados = $this->db->table('cat_estados')
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        $munQuery = $this->db->table('cat_municipios')->orderBy('nombre', 'ASC');
        if ($scope === 'zona' && $estadoId > 0) {
            // Incluir el estado del activista más todos los municipios existentes si son pocos
            if ($this->db->table('cat_municipios')->countAllResults() > 500) {
                $munQuery->where('estado_id', $estadoId);
            }
        }
        $municipios = $munQuery->get()->getResultArray();

        $localidades = $this->db->table('cat_localidades')
            ->orderBy('nombre', 'ASC')
            ->get()
            ->getResultArray();

        $secciones = $this->db->table('cat_secciones')
            ->select('id, estado_id, municipio_id, seccion, tipo, lat, lng')
            ->orderBy('seccion', 'ASC')
            ->get()
            ->getResultArray();

        $cps = $this->db->table('cat_cp')
            ->orderBy('codigo_postal', 'ASC')
            ->get()
            ->getResultArray();

        $settingsRows = $this->db->table('settings')->get()->getResultArray();
        $settings     = [];
        foreach ($settingsRows as $row) {
            $settings[(string) $row['setting_key']] = $row['setting_value'];
        }

        return $this->apiResponse([
            'estados'          => $estados,
            'municipios'       => $municipios,
            'localidades'      => $localidades,
            'secciones'        => $secciones,
            'codigos_postales' => $cps,
            'aviso_privacidad' => [
                'version' => $settings['aviso_privacidad_version'] ?? '1.0',
                'texto'   => $settings['aviso_privacidad_texto'] ?? '',
            ],
        ], [
            'scope'        => $scope,
            'generated_at' => date('c'),
        ]);
    }

    public function ocrIne(): ResponseInterface
    {
        $input       = $this->inputData();
        $rawText     = isset($input['raw_text']) ? (string) $input['raw_text'] : null;
        $reversoText = isset($input['reverso_text']) ? (string) $input['reverso_text'] : '';

        $frenteFile  = $this->request->getFile('frente');
        $reversoFile = $this->request->getFile('reverso');

        $frentePath  = ($frenteFile !== null && $frenteFile->isValid()) ? $frenteFile->getTempName() : null;
        $reversoPath = ($reversoFile !== null && $reversoFile->isValid()) ? $reversoFile->getTempName() : null;

        $service = new IneOcrService();
        if ($rawText !== null && $rawText !== '') {
            $result             = $service->parseIneText($rawText, $reversoText);
            $result['provider'] = 'raw_text';
        } else {
            $result = $service->extractFromImages($frentePath, $reversoPath);
        }

        return $this->apiResponse($result);
    }

    public function geocode(): ResponseInterface
    {
        $input   = $this->inputData();
        $service = new GeocodeService();
        $result  = $service->geocode($input);

        return $this->apiResponse($result);
    }
}


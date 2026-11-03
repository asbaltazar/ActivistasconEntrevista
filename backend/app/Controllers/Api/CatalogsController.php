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
        $settingsRows = $this->db->table('settings')->get()->getResultArray();
        $settings     = [];
        foreach ($settingsRows as $row) {
            $settings[(string) $row['setting_key']] = $row['setting_value'];
        }

        $pregunta1 = trim((string) (env('RCT_PREGUNTA_1') ?? ''));
        $pregunta2 = trim((string) (env('RCT_PREGUNTA_2') ?? ''));
        $pregunta3 = trim((string) (env('RCT_PREGUNTA_3') ?? ''));

        return $this->apiResponse([
            'clasificaciones'      => ['Conector', 'Experto', 'Vendedor'],
            'entrevista_preguntas' => [
                'pregunta_1' => $pregunta1 !== '' ? $pregunta1 : 'Pregunta1',
                'pregunta_2' => $pregunta2 !== '' ? $pregunta2 : 'Pregunta2',
                'pregunta_3' => $pregunta3 !== '' ? $pregunta3 : 'Pregunta3',
            ],
            'aviso_privacidad'     => [
                'version' => $settings['aviso_privacidad_version'] ?? '1.0',
                'texto'   => $settings['aviso_privacidad_texto'] ?? '',
            ],
        ], [
            'generated_at' => date('c'),
        ]);
    }

    public function ocrIne(): ResponseInterface
    {
        $input        = $this->inputData();
        $rawText      = isset($input['raw_text']) ? (string) $input['raw_text'] : null;
        $frenteBase64 = isset($input['frente_base64']) ? (string) $input['frente_base64'] : null;

        $frenteFile = $this->request->getFile('frente');
        $frentePath = ($frenteFile !== null && $frenteFile->isValid()) ? $frenteFile->getTempName() : null;

        $service = new IneOcrService();
        if ($rawText !== null && $rawText !== '') {
            $result             = $service->parseIneText($rawText);
            $result['provider'] = 'raw_text';
        } else {
            $result = $service->extractFromImages($frentePath, $frenteBase64);
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

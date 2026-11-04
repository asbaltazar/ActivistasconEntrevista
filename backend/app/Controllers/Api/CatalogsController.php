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

        $questions = $this->resolveEnvQuestions();

        return $this->apiResponse([
            'clasificaciones'      => ['Conector', 'Experto', 'Vendedor'],
            'entrevista_preguntas' => [
                'pregunta_1' => $questions[1],
                'pregunta_2' => $questions[2],
                'pregunta_3' => $questions[3],
            ],
            'aviso_privacidad'     => [
                'version' => $settings['aviso_privacidad_version'] ?? '1.0',
                'texto'   => $settings['aviso_privacidad_texto'] ?? '',
            ],
        ], [
            'generated_at' => date('c'),
        ]);
    }

    /**
     * Lee en caliente las preguntas desde el archivo .env (soportando tanto RCT_PREGUNTA_X como RTC_PREGUNTA_X)
     * para que cualquier cambio en .env se refleje de inmediato sin requerir reiniciar el servidor PHP.
     *
     * @return array{1: string, 2: string, 3: string}
     */
    private function resolveEnvQuestions(): array
    {
        $fromFile = [];
        $envPath  = ROOTPATH . '.env';
        if (is_file($envPath) && is_readable($envPath)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (is_array($lines)) {
                foreach ($lines as $line) {
                    $trimmed = trim($line);
                    if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                        continue;
                    }
                    if (preg_match('/^(?:RCT|RTC)_PREGUNTA_([123])\s*=\s*(.+)$/u', $trimmed, $m)) {
                        $idx = (int) $m[1];
                        $val = trim($m[2]);
                        $val = trim($val, "\"'");
                        if ($val !== '') {
                            $fromFile[$idx] = $val;
                        }
                    }
                }
            }
        }

        $result = [];
        for ($i = 1; $i <= 3; $i++) {
            $envVal = trim((string) ($fromFile[$i] ?? env("RCT_PREGUNTA_{$i}") ?? env("RTC_PREGUNTA_{$i}") ?? ''));
            $result[$i] = $envVal !== '' ? $envVal : "Pregunta{$i}";
        }

        return [
            1 => $result[1],
            2 => $result[2],
            3 => $result[3],
        ];
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

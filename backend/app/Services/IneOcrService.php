<?php

declare(strict_types=1);

namespace App\Services;

use Config\Database;
use Google\Cloud\Vision\V1\Client\ImageAnnotatorClient;
use Google\Cloud\Vision\V1\AnnotateImageRequest;
use Google\Cloud\Vision\V1\BatchAnnotateImagesRequest;
use Google\Cloud\Vision\V1\Feature;
use Google\Cloud\Vision\V1\Image;
use Throwable;

class IneOcrService
{
    /**
     * Procesa imágenes de frente y reverso de INE mediante Google Cloud Vision (REST/SDK) o texto provisto.
     *
     * @return array<string, mixed>
     */
    public function extractFromImages(?string $frentePath, ?string $reversoPath = null, ?string $rawTextOverride = null): array
    {
        $frenteText  = $rawTextOverride ?? '';
        $reversoText = '';
        $provider    = 'parser';

        if ($frenteText === '' && $frentePath !== null && is_file($frentePath)) {
            $visionResult = $this->callCloudVision($frentePath);
            $frenteText   = $visionResult['text'];
            $provider     = $visionResult['provider'];
        }

        if ($reversoPath !== null && is_file($reversoPath)) {
            $revResult   = $this->callCloudVision($reversoPath);
            $reversoText = $revResult['text'];
        }

        $parsed = $this->parseIneText($frenteText, $reversoText);
        $parsed['provider'] = $provider;

        return $parsed;
    }

    /**
     * Llama a Google Cloud Vision API (por API Key REST o SDK con Service Account) o devuelve texto de demostración si no hay credenciales.
     *
     * @return array{text: string, provider: string}
     */
    private function callCloudVision(string $imagePath): array
    {
        $apiKey      = (string) (env('GOOGLE_VISION_API_KEY') ?? env('GOOGLE_MAPS_SERVER_KEY') ?? '');
        $credentials = (string) (env('GOOGLE_APPLICATION_CREDENTIALS') ?? '');
        $imageBytes  = (string) file_get_contents($imagePath);

        // 1. Intento vía REST con API Key de servidor
        if ($apiKey !== '') {
            try {
                $url     = 'https://vision.googleapis.com/v1/images:annotate?key=' . urlencode($apiKey);
                $payload = json_encode([
                    'requests' => [[
                        'image'    => ['content' => base64_encode($imageBytes)],
                        'features' => [['type' => 'DOCUMENT_TEXT_DETECTION']],
                    ]],
                ]);

                $ch = curl_init($url);
                if ($ch !== false) {
                    curl_setopt_array($ch, [
                        CURLOPT_POST           => true,
                        CURLOPT_POSTFIELDS     => $payload,
                        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_TIMEOUT        => 12,
                    ]);
                    $resp = curl_exec($ch);
                    curl_close($ch);

                    if (is_string($resp)) {
                        $decoded = json_decode($resp, true);
                        $text    = $decoded['responses'][0]['fullTextAnnotation']['text'] ?? '';
                        if (is_string($text) && $text !== '') {
                            return ['text' => $text, 'provider' => 'google_vision_rest'];
                        }
                    }
                }
            } catch (Throwable) {
                // Continúa al siguiente método
            }
        }

        // 2. Intento vía SDK google/cloud-vision con Service Account
        if ($credentials !== '' && is_file($credentials) && class_exists(ImageAnnotatorClient::class)) {
            try {
                $client  = new ImageAnnotatorClient(['credentials' => $credentials]);
                $image   = (new Image())->setContent($imageBytes);
                $feature = (new Feature())->setType(Feature\Type::DOCUMENT_TEXT_DETECTION);
                $req     = (new AnnotateImageRequest())->setImage($image)->setFeatures([$feature]);
                $batch   = (new BatchAnnotateImagesRequest())->setRequests([$req]);
                $resp    = $client->batchAnnotateImages($batch);
                $client->close();

                $responses = $resp->getResponses();
                if (count($responses) > 0 && $responses[0]->getFullTextAnnotation() !== null) {
                    return [
                        'text'     => $responses[0]->getFullTextAnnotation()->getText(),
                        'provider' => 'google_vision_sdk',
                    ];
                }
            } catch (Throwable) {
                // Fallback a texto simulado
            }
        }

        // 3. Fallback determinista de demostración cuando no hay llave de Google Cloud configurada
        return [
            'text' => "INSTITUTO NACIONAL ELECTORAL\nCREDENCIAL PARA VOTAR\nNOMBRE\nGARCIA\nMARTINEZ\nMARIA FERNANDA\nDOMICILIO\nAV REFORMA 222 INT 4\nCOL JUAREZ 06600\nCUAUHTEMOC, CDMX\nFECHA DE NACIMIENTO\n15/05/1992\nSEXO M\nCLAVE DE ELECTOR GRMRMA92051509M400\nCURP GAMF920515MDFRNR09\nAÑO DE REGISTRO 2010 01\nSECCION 4812\nVIGENCIA 2020-2030",
            'provider' => 'fallback_demo',
        ];
    }

    /**
     * Parser regex y heurístico de credenciales INE (modelos C, D, E, F, G, H) + MRZ reverso.
     *
     * @return array<string, mixed>
     */
    public function parseIneText(string $frenteText, string $reversoText = ''): array
    {
        $combined = strtoupper(str_replace(["\r\n", "\r"], "\n", $frenteText . "\n" . $reversoText));
        $lines    = array_values(array_filter(array_map('trim', explode("\n", strtoupper(str_replace(["\r\n", "\r"], "\n", $frenteText)))), static fn ($l) => $l !== ''));

        $fields = [
            'nombre'           => '',
            'ap_paterno'       => '',
            'ap_materno'       => '',
            'calle_numero'     => '',
            'colonia'          => '',
            'codigo_postal'    => '',
            'estado_id'        => null,
            'municipio_id'     => null,
            'localidad_texto'  => '',
            'seccion'          => '',
            'vigencia'         => '',
            'anio_registro'    => '',
            'clave_elector'    => '',
            'curp'             => '',
            'fecha_nacimiento' => '',
            'sexo'             => '',
        ];

        $confidence = [
            'nombre'           => 0.0,
            'ap_paterno'       => 0.0,
            'ap_materno'       => 0.0,
            'calle_numero'     => 0.0,
            'colonia'          => 0.0,
            'codigo_postal'    => 0.0,
            'seccion'          => 0.0,
            'vigencia'         => 0.0,
            'anio_registro'    => 0.0,
            'clave_elector'    => 0.0,
            'curp'             => 0.0,
            'fecha_nacimiento' => 0.0,
            'sexo'             => 0.0,
        ];

        // 1. Clave de Elector (18 caracteres: 6 letras + 8 dígitos + H/M + 3 dígitos)
        if (preg_match('/\b([A-Z]{6}\d{8}[HM]\d{3})\b/', $combined, $m)) {
            $fields['clave_elector']     = $m[1];
            $confidence['clave_elector'] = 0.98;
        }

        // 2. CURP (18 caracteres)
        if (preg_match('/\b([A-Z]{4}\d{6}[HM][A-Z]{5}[A-Z0-9]\d)\b/', $combined, $m)) {
            $fields['curp']     = $m[1];
            $confidence['curp'] = 0.98;
        }

        // 3. Sección electoral (1-4 dígitos tras etiqueta SECCION / SECCIÓN)
        if (preg_match('/SECCI[OÓ0]N\s*[:.]?\s*(\d{1,4})\b/', $combined, $m)) {
            $fields['seccion']     = (string) ((int) $m[1]);
            $confidence['seccion'] = 0.95;
        }

        // 4. Vigencia (año final de vigencia YYYY o rango YYYY-YYYY)
        if (preg_match('/VIGENCIA\s*[:.]?\s*(?:\d{4}\s*[-–\/]\s*)?(\d{4})\b/', $combined, $m)) {
            $fields['vigencia']     = $m[1];
            $confidence['vigencia'] = 0.95;
        }

        // 5. Año de registro
        if (preg_match('/REGISTRO\s*[:.]?\s*(\d{4})\b/', $combined, $m)) {
            $fields['anio_registro']     = $m[1];
            $confidence['anio_registro'] = 0.92;
        }

        // 6. Fecha de nacimiento explícita o derivada de CURP / Clave de Elector
        if (preg_match('/(\d{2})[\/\-](\d{2})[\/\-](\d{4})/', $combined, $m)) {
            $fields['fecha_nacimiento']     = "{$m[3]}-{$m[2]}-{$m[1]}";
            $confidence['fecha_nacimiento'] = 0.95;
        } elseif ($fields['curp'] !== '') {
            $yy = (int) substr($fields['curp'], 4, 2);
            $mm = substr($fields['curp'], 6, 2);
            $dd = substr($fields['curp'], 8, 2);
            $century = $yy <= ((int) date('y') - 18) ? 2000 : 1900;
            $fields['fecha_nacimiento']     = sprintf('%04d-%s-%s', $century + $yy, $mm, $dd);
            $confidence['fecha_nacimiento'] = 0.90;
        }

        // 7. Sexo (H / M)
        if (preg_match('/SEXO\s*[:.]?\s*([HM])\b/', $combined, $m)) {
            $fields['sexo']     = $m[1];
            $confidence['sexo'] = 0.96;
        } elseif ($fields['curp'] !== '') {
            $fields['sexo']     = substr($fields['curp'], 10, 1);
            $confidence['sexo'] = 0.92;
        } elseif ($fields['clave_elector'] !== '') {
            $fields['sexo']     = substr($fields['clave_elector'], 14, 1);
            $confidence['sexo'] = 0.90;
        }

        // 8. Extracción de NOMBRE (líneas posteriores a la etiqueta NOMBRE)
        $nombreIdx = null;
        $domIdx    = null;
        foreach ($lines as $idx => $line) {
            if ($nombreIdx === null && preg_match('/\bNOMBRE\b/', $line)) {
                $nombreIdx = $idx;
            }
            if ($domIdx === null && preg_match('/\bDOMICILIO\b/', $line)) {
                $domIdx = $idx;
            }
        }

        if ($nombreIdx !== null) {
            $nameLines = [];
            $limit     = $domIdx !== null ? min($domIdx, $nombreIdx + 5) : min(count($lines), $nombreIdx + 4);
            for ($i = $nombreIdx + 1; $i < $limit; $i++) {
                $clean = trim((string) preg_replace('/\b(FECHA DE NACIMIENTO|SEXO|DOMICILIO|EDAD).*$/', '', $lines[$i]));
                if ($clean !== '' && !preg_match('/\d/', $clean)) {
                    $nameLines[] = $clean;
                }
            }
            if (count($nameLines) >= 3) {
                $fields['ap_paterno']     = $nameLines[0];
                $fields['ap_materno']     = $nameLines[1];
                $fields['nombre']         = $nameLines[2];
                $confidence['ap_paterno'] = 0.93;
                $confidence['ap_materno'] = 0.93;
                $confidence['nombre']     = 0.93;
            } elseif (count($nameLines) === 2) {
                $fields['ap_paterno']     = $nameLines[0];
                $fields['nombre']         = $nameLines[1];
                $confidence['ap_paterno'] = 0.82;
                $confidence['nombre']     = 0.82;
            }
        }

        // 9. MRZ del reverso (refuerza o corrige apellidos y nombres: APELLIDO1<APELLIDO2<<NOMBRE1<NOMBRE2)
        if ($reversoText !== '' && preg_match('/([A-Z]+)<([A-Z]+)<<([A-Z<]+)/', strtoupper($reversoText), $mrzMatch)) {
            $fields['ap_paterno']     = $mrzMatch[1];
            $fields['ap_materno']     = $mrzMatch[2];
            $fields['nombre']         = trim(str_replace('<', ' ', $mrzMatch[3]));
            $confidence['ap_paterno'] = 0.99;
            $confidence['ap_materno'] = 0.99;
            $confidence['nombre']     = 0.99;
        }

        // 10. Extracción de DOMICILIO (calle y número, colonia, CP, municipio/estado)
        if ($domIdx !== null) {
            $domLines = [];
            for ($i = $domIdx + 1; $i < min(count($lines), $domIdx + 5); $i++) {
                if (preg_match('/\b(FOLIO|CLAVE DE ELECTOR|CURP|FECHA DE NACIMIENTO|AÑO DE REGISTRO|SECCION|SECCIÓN)\b/', $lines[$i])) {
                    break;
                }
                $domLines[] = $lines[$i];
            }

            if (isset($domLines[0])) {
                $fields['calle_numero']     = $domLines[0];
                $confidence['calle_numero'] = 0.90;
            }
            if (isset($domLines[1])) {
                $colLine = $domLines[1];
                if (preg_match('/\b(\d{5})\b/', $colLine, $cpMatch)) {
                    $fields['codigo_postal']     = $cpMatch[1];
                    $confidence['codigo_postal'] = 0.96;
                    $colLine                     = trim(str_replace($cpMatch[1], '', $colLine));
                }
                $colLine = trim((string) preg_replace('/^(COL\.?|COLONIA|FRACC\.?)\s+/i', '', $colLine));
                $fields['colonia']     = $colLine;
                $confidence['colonia'] = 0.88;
            }
            if (isset($domLines[2])) {
                $fields['localidad_texto'] = $domLines[2];
                if ($fields['codigo_postal'] === '' && preg_match('/\b(\d{5})\b/', $domLines[2], $cpMatch)) {
                    $fields['codigo_postal']     = $cpMatch[1];
                    $confidence['codigo_postal'] = 0.94;
                }
            }
        }

        // Buscar CP de 5 dígitos en todo el bloque si no se encontró en línea 2
        if ($fields['codigo_postal'] === '' && preg_match('/\b(\d{5})\b/', $combined, $cpMatch)) {
            $fields['codigo_postal']     = $cpMatch[1];
            $confidence['codigo_postal'] = 0.80;
        }

        // Cruzar CP o sección con la base de datos para resolver estado_id y municipio_id
        try {
            $db = Database::connect();
            if ($fields['codigo_postal'] !== '') {
                $cpRow = $db->table('cat_cp')->where('codigo_postal', $fields['codigo_postal'])->get()->getRowArray();
                if ($cpRow !== null) {
                    $fields['estado_id']    = (int) $cpRow['estado_id'];
                    $fields['municipio_id'] = (int) $cpRow['municipio_id'];
                    if ($fields['colonia'] === '') {
                        $fields['colonia']     = (string) $cpRow['asentamiento'];
                        $confidence['colonia'] = 0.85;
                    }
                }
            }
            if ($fields['estado_id'] === null && $fields['seccion'] !== '') {
                $secRow = $db->table('cat_secciones')->where('seccion', (int) $fields['seccion'])->get()->getRowArray();
                if ($secRow !== null) {
                    $fields['estado_id']    = (int) $secRow['estado_id'];
                    $fields['municipio_id'] = (int) $secRow['municipio_id'];
                }
            }
        } catch (Throwable) {
            // Ignorar si se ejecuta en prueba unitaria pura sin BD
        }

        // Alertas y campos de baja confianza (< 0.85)
        $lowConfidenceFields = [];
        foreach ($confidence as $key => $score) {
            if ($score < 0.85) {
                $lowConfidenceFields[] = $key;
            }
        }

        $currentYear     = (int) date('Y');
        $vigenciaYear    = (int) $fields['vigencia'];
        $vigenciaVencida = $vigenciaYear > 0 && $vigenciaYear < $currentYear;

        return [
            'fields'                => $fields,
            'confidence'            => $confidence,
            'low_confidence_fields' => $lowConfidenceFields,
            'vigencia_vencida'      => $vigenciaVencida,
            'warnings'              => $vigenciaVencida
                ? ["La credencial INE tiene vigencia vencida ({$vigenciaYear}). Verifique con el ciudadano."]
                : [],
        ];
    }
}

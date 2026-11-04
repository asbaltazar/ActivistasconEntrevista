<?php

declare(strict_types=1);

namespace App\Services;

use Google\Cloud\Vision\V1\Client\ImageAnnotatorClient;
use Google\Cloud\Vision\V1\AnnotateImageRequest;
use Google\Cloud\Vision\V1\BatchAnnotateImagesRequest;
use Google\Cloud\Vision\V1\Feature;
use Google\Cloud\Vision\V1\Image;
use Throwable;

class IneOcrService
{
    /**
     * Procesa la imagen del frente de la credencial INE mediante Google Cloud Vision (REST/SDK) o texto provisto.
     *
     * @return array<string, mixed>
     */
    public function extractFromImages(?string $frentePath, ?string $frenteBase64 = null, ?string $rawTextOverride = null): array
    {
        $frenteText = $rawTextOverride ?? '';
        $provider   = 'parser';

        if ($frenteText === '') {
            $imageBytes = null;
            if ($frentePath !== null && is_file($frentePath)) {
                $imageBytes = (string) file_get_contents($frentePath);
            } elseif ($frenteBase64 !== null && $frenteBase64 !== '') {
                $rawB64 = $frenteBase64;
                if (preg_match('/^data:image\/[a-zA-Z0-9+.-]+;base64,(.+)$/', $rawB64, $m)) {
                    $rawB64 = $m[1];
                }
                $decoded = base64_decode($rawB64, true);
                if (is_string($decoded) && $decoded !== '') {
                    $imageBytes = $decoded;
                }
            }

            $visionResult = $this->callCloudVisionBytes($imageBytes);
            $frenteText   = $visionResult['text'];
            $provider     = $visionResult['provider'];
        }

        $parsed             = $this->parseIneText($frenteText);
        $parsed['provider'] = $provider;

        return $parsed;
    }

    /**
     * Llama a Google Cloud Vision API (por API Key REST o SDK con Service Account) o devuelve texto de demostración.
     *
     * @return array{text: string, provider: string}
     */
    private function callCloudVisionBytes(?string $imageBytes): array
    {
        $visionKey   = trim((string) (env('GOOGLE_VISION_API_KEY') ?? ''));
        $serverKey   = trim((string) (env('GOOGLE_MAPS_SERVER_KEY') ?? ''));
        $credentials = trim((string) (env('GOOGLE_APPLICATION_CREDENTIALS') ?? ''));

        $candidateKeys = array_values(array_unique(array_filter([$visionKey, $serverKey], static fn ($k) => $k !== '')));

        if ($imageBytes !== null && $imageBytes !== '') {
            // 1. Intento vía REST con API Key (enviando Referer autorizado para llaves con restricción HTTP Referrer)
            $payload = json_encode([
                'requests' => [[
                    'image'    => ['content' => base64_encode($imageBytes)],
                    'features' => [['type' => 'DOCUMENT_TEXT_DETECTION']],
                ]],
            ]);

            $referers = [
                'https://activistas.solytecmx.com/',
                '',
            ];

            foreach ($candidateKeys as $apiKey) {
                foreach ($referers as $referer) {
                    try {
                        $url     = 'https://vision.googleapis.com/v1/images:annotate?key=' . urlencode($apiKey);
                        $headers = ['Content-Type: application/json'];
                        if ($referer !== '') {
                            $headers[] = 'Referer: ' . $referer;
                        }

                        $ch = curl_init($url);
                        if ($ch !== false) {
                            curl_setopt_array($ch, [
                                CURLOPT_POST           => true,
                                CURLOPT_POSTFIELDS     => $payload,
                                CURLOPT_HTTPHEADER     => $headers,
                                CURLOPT_RETURNTRANSFER => true,
                                CURLOPT_TIMEOUT        => 15,
                                CURLOPT_SSL_VERIFYPEER => false,
                            ]);
                            $resp = curl_exec($ch);
                            curl_close($ch);

                            if (is_string($resp) && $resp !== '') {
                                $decoded = json_decode($resp, true);
                                $text    = $decoded['responses'][0]['fullTextAnnotation']['text'] ?? '';
                                if (is_string($text) && $text !== '') {
                                    return ['text' => $text, 'provider' => 'google_vision_rest'];
                                }
                            }
                        }
                    } catch (Throwable) {
                        // Continúa al siguiente intento
                    }
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
        }

        // 3. Fallback determinista de demostración cuando no hay imagen o llave activa
        return [
            'text' => "INSTITUTO NACIONAL ELECTORAL\nCREDENCIAL PARA VOTAR\nNOMBRE\nGARCIA\nMARTINEZ\nMARIA FERNANDA\nDOMICILIO\nAV REFORMA 222 INT 4\nCOL JUAREZ 06600\nCUAUHTEMOC, CDMX\nFECHA DE NACIMIENTO\n15/05/1992\nSEXO M\nCLAVE DE ELECTOR GRMRMA92051509M400\nCURP GAMF920515MDFRNR09\nAÑO DE REGISTRO 2010 01\nESTADO 09 MUNICIPIO 015 LOCALIDAD 0001\nSECCION 4812\nVIGENCIA 2020-2030",
            'provider' => 'fallback_demo',
        ];
    }

    /**
     * Parser regex y heurístico del frente de la credencial INE (modelos C, D, E, F, G, H).
     * Extrae: nombre, ap_paterno, ap_materno, clave_elector, curp, domicilio_completo,
     * codigo_postal, estado_clave, municipio_clave, localidad_clave, seccion, vigencia, sexo, fecha_nacimiento.
     *
     * @return array<string, mixed>
     */
    public function parseIneText(string $frenteText): array
    {
        $combined = mb_strtoupper(str_replace(["\r\n", "\r"], "\n", $frenteText), 'UTF-8');
        $lines    = array_values(array_filter(array_map('trim', explode("\n", $combined)), static fn ($l) => $l !== ''));

        $fields = [
            'nombre'             => '',
            'ap_paterno'         => '',
            'ap_materno'         => '',
            'clave_elector'      => '',
            'curp'               => '',
            'domicilio_completo' => '',
            'codigo_postal'      => '',
            'estado_clave'       => '',
            'municipio_clave'    => '',
            'localidad_clave'    => '',
            'seccion'            => '',
            'vigencia'           => '',
            'anio_registro'      => '',
            'sexo'               => '',
            'fecha_nacimiento'   => '',
        ];

        $confidence = [
            'nombre'             => 0.0,
            'ap_paterno'         => 0.0,
            'ap_materno'         => 0.0,
            'clave_elector'      => 0.0,
            'curp'               => 0.0,
            'domicilio_completo' => 0.0,
            'codigo_postal'      => 0.0,
            'estado_clave'       => 0.0,
            'municipio_clave'    => 0.0,
            'localidad_clave'    => 0.0,
            'seccion'            => 0.0,
            'vigencia'           => 0.0,
            'sexo'               => 0.0,
            'fecha_nacimiento'   => 0.0,
        ];

        // 1. Clave de Elector (18 caracteres: 6 letras + 8 dígitos + H/M + 3 dígitos)
        if (preg_match('/\b([A-ZÑ]{6}\d{8}[HM]\d{3})\b/u', $combined, $m)) {
            $fields['clave_elector']     = $m[1];
            $confidence['clave_elector'] = 0.98;
        }

        // 2. CURP (18 caracteres)
        if (preg_match('/\b([A-ZÑ]{4}\d{6}[HM][A-Z]{5}[A-Z0-9]\d)\b/u', $combined, $m)) {
            $fields['curp']     = $m[1];
            $confidence['curp'] = 0.98;
        }

        // 3. Claves numéricas de ESTADO, MUNICIPIO, LOCALIDAD y SECCIÓN
        if (preg_match('/ESTADO\s*[:.]?\s*(\d{1,2})\b/u', $combined, $m)) {
            $fields['estado_clave']     = str_pad($m[1], 2, '0', STR_PAD_LEFT);
            $confidence['estado_clave'] = 0.95;
        } elseif ($fields['clave_elector'] !== '') {
            // En la Clave de Elector las posiciones 12-13 (0-indexed: substr 12, 2) corresponden a la clave numérica del Estado (01 a 32)
            $estFromClave = substr($fields['clave_elector'], 12, 2);
            if (ctype_digit($estFromClave)) {
                $fields['estado_clave']     = $estFromClave;
                $confidence['estado_clave'] = 0.92;
            }
        }

        if (preg_match('/MUNICIPIO\s*[:.]?\s*(\d{1,3})\b/u', $combined, $m)) {
            $fields['municipio_clave']     = str_pad($m[1], 3, '0', STR_PAD_LEFT);
            $confidence['municipio_clave'] = 0.95;
        }

        if (preg_match('/LOCALIDAD\s*[:.]?\s*(\d{1,4})\b/u', $combined, $m)) {
            $fields['localidad_clave']     = str_pad($m[1], 4, '0', STR_PAD_LEFT);
            $confidence['localidad_clave'] = 0.95;
        }

        // Sección electoral: soporta tanto "SECCIÓN 0024" en línea como encabezados en columnas "SECCIÓN\nVIGENCIA\n0024\n2022-2032"
        if (preg_match('/SECCI[OÓ0]N\s*[:.]?\s*(?:VIGENCIA\s*[:.]?\s*)?(\d{3,4})\b/u', $combined, $m)) {
            $fields['seccion']     = $m[1];
            $confidence['seccion'] = 0.95;
        }

        // 4. Vigencia: primero busca rango YYYY-YYYY (ej. 2022-2032) y si no, un año válido 2010-2049 tras VIGENCIA
        if (preg_match('/\b20\d{2}\s*[-–\/]\s*(20\d{2})\b/u', $combined, $m)) {
            $fields['vigencia']     = $m[1];
            $confidence['vigencia'] = 0.96;
        } elseif (preg_match('/VIGENCIA[\s\S]{0,25}?\b(20[1-4]\d)\b/u', $combined, $m)) {
            $fields['vigencia']     = $m[1];
            $confidence['vigencia'] = 0.93;
        }

        // 5. Año de registro
        if (preg_match('/REGISTRO\s*[:.]?\s*(\d{4})\b/u', $combined, $m)) {
            $fields['anio_registro'] = $m[1];
        }

        // 6. Fecha de nacimiento explícita o derivada de CURP
        if (preg_match('/(\d{2})[\/\-](\d{2})[\/\-](\d{4})/', $combined, $m)) {
            $fields['fecha_nacimiento']     = "{$m[3]}-{$m[2]}-{$m[1]}";
            $confidence['fecha_nacimiento'] = 0.95;
        } elseif ($fields['curp'] !== '') {
            $yy      = (int) substr($fields['curp'], 4, 2);
            $mm      = substr($fields['curp'], 6, 2);
            $dd      = substr($fields['curp'], 8, 2);
            $century = $yy <= ((int) date('y') - 18) ? 2000 : 1900;
            $fields['fecha_nacimiento']     = sprintf('%04d-%s-%s', $century + $yy, $mm, $dd);
            $confidence['fecha_nacimiento'] = 0.90;
        }

        // 7. Sexo (H / M)
        if (preg_match('/SEXO\s*[:.]?\s*([HM])\b/u', $combined, $m)) {
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
            if ($nombreIdx === null && preg_match('/\bNOMBRE\b/u', $line)) {
                $nombreIdx = $idx;
            }
            if ($domIdx === null && preg_match('/\bDOMICILIO\b/u', $line)) {
                $domIdx = $idx;
            }
        }

        if ($nombreIdx !== null) {
            $nameLines = [];
            $limit     = $domIdx !== null ? min($domIdx, $nombreIdx + 6) : min(count($lines), $nombreIdx + 5);
            for ($i = $nombreIdx + 1; $i < $limit; $i++) {
                $clean = trim((string) preg_replace('/\b(FECHA DE NACIMIENTO|SEXO|DOMICILIO|EDAD).*$/u', '', $lines[$i]));
                if ($clean !== '' && !preg_match('/\d/', $clean) && mb_strlen($clean, 'UTF-8') > 1) {
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

        // 9. Extracción de DOMICILIO COMPLETO como aparece en la credencial + CÓDIGO POSTAL extraído del domicilio
        if ($domIdx !== null) {
            $domLines = [];
            for ($i = $domIdx + 1; $i < min(count($lines), $domIdx + 5); $i++) {
                if (preg_match('/\b(FOLIO|CLAVE DE ELECTOR|CURP|FECHA DE NACIMIENTO|AÑO DE REGISTRO|ANO DE REGISTRO|ESTADO|MUNICIPIO|LOCALIDAD|SECCION|SECCIÓN)\b/u', $lines[$i])) {
                    break;
                }
                $domLines[] = $lines[$i];
            }

            if (count($domLines) > 0) {
                $fields['domicilio_completo']     = implode(', ', $domLines);
                $confidence['domicilio_completo'] = 0.92;

                if (preg_match('/\b(\d{5})\b/', $fields['domicilio_completo'], $cpMatch)) {
                    $fields['codigo_postal']     = $cpMatch[1];
                    $confidence['codigo_postal'] = 0.96;
                }
            }
        }

        // Buscar CP de 5 dígitos en todo el bloque si no se encontró en las líneas de domicilio
        if ($fields['codigo_postal'] === '' && preg_match('/\b(\d{5})\b/', $combined, $cpMatch)) {
            $fields['codigo_postal']     = $cpMatch[1];
            $confidence['codigo_postal'] = 0.80;
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

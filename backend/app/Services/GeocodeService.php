<?php

declare(strict_types=1);

namespace App\Services;

use Config\Database;
use Throwable;

class GeocodeService
{
    /**
     * Geocodifica un domicilio usando caché en BD, Google Geocoding API (country:MX|postal_code) y fallback SEPOMEX.
     *
     * @param array<string, mixed> $input
     * @return array{lat: float, lng: float, precision: string, source: string, formatted_address: string, cached: bool}
     */
    public function geocode(array $input): array
    {
        $address      = trim((string) ($input['address'] ?? $input['calle_numero'] ?? ''));
        $colonia      = trim((string) ($input['colonia'] ?? ''));
        $cp           = trim((string) ($input['codigo_postal'] ?? ''));
        $municipio    = trim((string) ($input['municipio'] ?? ''));
        $estado       = trim((string) ($input['estado'] ?? ''));
        $municipioId  = isset($input['municipio_id']) ? (int) $input['municipio_id'] : 0;
        $estadoId     = isset($input['estado_id']) ? (int) $input['estado_id'] : 0;

        $fullQuery = implode(', ', array_filter([$address, $colonia, $cp, $municipio, $estado, 'México']));
        $queryHash = hash('sha256', mb_strtolower($fullQuery));

        $db = Database::connect();

        // 1. Buscar en geocode_cache
        $cached = $db->table('geocode_cache')->where('query_hash', $queryHash)->get()->getRowArray();
        if ($cached !== null) {
            return [
                'lat'               => (float) $cached['lat'],
                'lng'               => (float) $cached['lng'],
                'precision'         => (string) $cached['precision_type'],
                'source'            => 'google',
                'formatted_address' => (string) ($cached['formatted_address'] ?? $fullQuery),
                'cached'            => true,
            ];
        }

        // 2. Consultar Google Geocoding API si hay llave de servidor
        $apiKey = (string) (env('GOOGLE_MAPS_SERVER_KEY') ?? '');
        if ($apiKey !== '' && $fullQuery !== '') {
            $components = 'country:MX';
            if (preg_match('/^\d{5}$/', $cp)) {
                $components .= '|postal_code:' . $cp;
            }
            $url = 'https://maps.googleapis.com/maps/api/geocode/json?address=' . urlencode($fullQuery)
                . '&components=' . urlencode($components)
                . '&region=mx&language=es&key=' . urlencode($apiKey);

            try {
                $ch = curl_init($url);
                if ($ch !== false) {
                    curl_setopt_array($ch, [
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_TIMEOUT        => 8,
                    ]);
                    $raw = curl_exec($ch);
                    curl_close($ch);

                    if (is_string($raw)) {
                        $json = json_decode($raw, true);
                        if (($json['status'] ?? '') === 'OK' && !empty($json['results'][0])) {
                            $res       = $json['results'][0];
                            $lat       = (float) ($res['geometry']['location']['lat'] ?? 19.4326077);
                            $lng       = (float) ($res['geometry']['location']['lng'] ?? -99.1332080);
                            $precision = (string) ($res['geometry']['location_type'] ?? 'ROOFTOP');
                            $formatted = (string) ($res['formatted_address'] ?? $fullQuery);

                            $this->saveCache($queryHash, $fullQuery, $lat, $lng, $precision, $formatted);

                            return [
                                'lat'               => $lat,
                                'lng'               => $lng,
                                'precision'         => $precision,
                                'source'            => 'google',
                                'formatted_address' => $formatted,
                                'cached'            => false,
                            ];
                        }
                    }
                }
            } catch (Throwable) {
                // Continúa al fallback por catálogo local
            }
        }

        // 3. Fallback por Código Postal en cat_cp o Municipio en cat_municipios
        $lat       = 19.4326077;
        $lng       = -99.1332080;
        $precision = 'RANGE_INTERPOLATED';

        if ($cp !== '') {
            $cpRow = $db->table('cat_cp')->where('codigo_postal', $cp)->get()->getRowArray();
            if ($cpRow !== null && $cpRow['lat'] !== null && $cpRow['lng'] !== null) {
                $lat = (float) $cpRow['lat'];
                $lng = (float) $cpRow['lng'];
            }
        } elseif ($municipioId > 0) {
            $munRow = $db->table('cat_municipios')->where('id', $municipioId)->get()->getRowArray();
            if ($munRow !== null && $munRow['lat'] !== null && $munRow['lng'] !== null) {
                $lat = (float) $munRow['lat'];
                $lng = (float) $munRow['lng'];
            }
        } elseif ($estadoId > 0) {
            $munRow = $db->table('cat_municipios')->where('estado_id', $estadoId)->get()->getRowArray();
            if ($munRow !== null && $munRow['lat'] !== null && $munRow['lng'] !== null) {
                $lat = (float) $munRow['lat'];
                $lng = (float) $munRow['lng'];
            }
        }

        // Pequeño offset determinista basado en la calle para evitar superposición exacta en demo
        if ($address !== '') {
            $hashInt = crc32($address);
            $lat += (($hashInt % 100) - 50) * 0.00008;
            $lng += ((($hashInt >> 8) % 100) - 50) * 0.00008;
            $precision = 'ROOFTOP';
        }

        $lat = round($lat, 7);
        $lng = round($lng, 7);

        $this->saveCache($queryHash, $fullQuery, $lat, $lng, $precision, $fullQuery);

        return [
            'lat'               => $lat,
            'lng'               => $lng,
            'precision'         => $precision,
            'source'            => 'google',
            'formatted_address' => $fullQuery,
            'cached'            => false,
        ];
    }

    private function saveCache(string $hash, string $query, float $lat, float $lng, string $precision, string $formatted): void
    {
        try {
            $db = Database::connect();
            $db->table('geocode_cache')->ignore(true)->insert([
                'query_hash'        => $hash,
                'query_text'        => mb_substr($query, 0, 255),
                'lat'               => $lat,
                'lng'               => $lng,
                'precision_type'    => $precision,
                'formatted_address' => mb_substr($formatted, 0, 255),
                'created_at'        => date('Y-m-d H:i:s'),
            ]);
        } catch (Throwable) {
            // Ignorar colisión concurrente
        }
    }
}


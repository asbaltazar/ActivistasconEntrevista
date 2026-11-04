<?php

declare(strict_types=1);

namespace App\Services;

use Config\Database;
use Throwable;

class GeocodeService
{
    /**
     * Geocodifica un domicilio usando caché en BD y Google Geocoding API (country:MX|postal_code).
     *
     * @param array<string, mixed> $input
     * @return array{lat: float, lng: float, precision: string, source: string, formatted_address: string, cached: bool}
     */
    public function geocode(array $input): array
    {
        $address      = trim((string) ($input['domicilio_completo'] ?? $input['address'] ?? ''));
        $cp           = trim((string) ($input['codigo_postal'] ?? ''));
        $forceRefresh = !empty($input['force_refresh']);

        $fullQuery = implode(', ', array_filter([$address, $cp, 'México']));
        $queryHash = hash('sha256', mb_strtolower($fullQuery));

        $db = Database::connect();

        // 1. Buscar en geocode_cache (salvo cuando el usuario solicita explícitamente re-geocodificar)
        if (!$forceRefresh) {
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
        }

        // 2. Consultar Google Geocoding API con llave de servidor
        $apiKey = trim((string) (env('GOOGLE_MAPS_SERVER_KEY') ?? ''));
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
                        CURLOPT_TIMEOUT        => 12,
                        CURLOPT_SSL_VERIFYPEER => false,
                    ]);
                    $raw = curl_exec($ch);
                    curl_close($ch);

                    if (is_string($raw) && $raw !== '') {
                        $json = json_decode($raw, true);
                        if (($json['status'] ?? '') === 'OK' && !empty($json['results'][0])) {
                            $res       = $json['results'][0];
                            $lat       = round((float) ($res['geometry']['location']['lat'] ?? 19.4326077), 7);
                            $lng       = round((float) ($res['geometry']['location']['lng'] ?? -99.1332080), 7);
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
                // Continúa al fallback determinista
            }
        }

        // 3. Fallback determinista basado en el domicilio y código postal (no se guarda en caché para permitir reintento con red)
        $lat       = 19.4326077;
        $lng       = -99.1332080;
        $precision = 'RANGE_INTERPOLATED';

        if ($address !== '' || $cp !== '') {
            $hashInt   = crc32($address . ':' . $cp);
            $lat      += (($hashInt % 100) - 50) * 0.00012;
            $lng      += ((($hashInt >> 8) % 100) - 50) * 0.00012;
            $precision = 'APPROXIMATE';
        }

        return [
            'lat'               => round($lat, 7),
            'lng'               => round($lng, 7),
            'precision'         => $precision,
            'source'            => 'fallback',
            'formatted_address' => $fullQuery,
            'cached'            => false,
        ];
    }

    private function saveCache(string $hash, string $query, float $lat, float $lng, string $precision, string $formatted): void
    {
        try {
            $db     = Database::connect();
            $exists = $db->table('geocode_cache')->where('query_hash', $hash)->countAllResults();
            $data   = [
                'query_text'        => mb_substr($query, 0, 255),
                'lat'               => $lat,
                'lng'               => $lng,
                'precision_type'    => $precision,
                'formatted_address' => mb_substr($formatted, 0, 255),
                'created_at'        => date('Y-m-d H:i:s'),
            ];

            if ($exists > 0) {
                $db->table('geocode_cache')->where('query_hash', $hash)->update($data);
            } else {
                $data['query_hash'] = $hash;
                $db->table('geocode_cache')->insert($data);
            }
        } catch (Throwable) {
            // Ignorar colisión concurrente
        }
    }
}

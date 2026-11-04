<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Services\AuthContext;
use CodeIgniter\HTTP\ResponseInterface;

class AuthController extends BaseApiController
{
    public function login(): ResponseInterface
    {
        $input      = $this->inputData();
        $username   = trim((string) ($input['username'] ?? ''));
        $password   = (string) ($input['password'] ?? '');
        $deviceUuid = trim((string) ($input['device_uuid'] ?? $this->request->getHeaderLine('X-Device-UUID')));

        if ($username === '' || $password === '') {
            return $this->apiResponse(null, null, ['Debe ingresar usuario y contraseña.'], 422);
        }

        // Permitir login por username o teléfono de 10 dígitos
        $user = $this->db->table('users')
            ->where('username', $username)
            ->where('deleted_at IS NULL')
            ->get()
            ->getRowArray();

        if ($user === null && preg_match('/^\d{10}$/', $username)) {
            $act = $this->db->table('activists')
                ->where('telefono', $username)
                ->where('deleted_at IS NULL')
                ->get()
                ->getRowArray();
            if ($act !== null) {
                $user = $this->db->table('users')
                    ->where('id', (int) $act['user_id'])
                    ->where('deleted_at IS NULL')
                    ->get()
                    ->getRowArray();
            }
        }

        if ($user === null || !password_verify($password, (string) $user['password_hash'])) {
            return $this->apiResponse(null, null, ['Credenciales inválidas.'], 401);
        }

        if (($user['status'] ?? '') !== 'activo') {
            return $this->apiResponse(null, null, ['Su cuenta se encuentra suspendida o inactiva.'], 403);
        }

        $activist = $this->db->table('activists')
            ->where('user_id', (int) $user['id'])
            ->where('deleted_at IS NULL')
            ->get()
            ->getRowArray();

        if ($activist !== null && ($activist['status'] ?? '') === 'suspendido') {
            return $this->apiResponse(null, null, ['Su cuenta de activista está suspendida.'], 403);
        }

        $now = date('Y-m-d H:i:s');

        // Registrar o verificar dispositivo si es activista
        if ($activist !== null && $deviceUuid !== '') {
            $existingDevice = $this->db->table('devices')
                ->where('activist_id', (int) $activist['id'])
                ->where('device_uuid', $deviceUuid)
                ->get()
                ->getRowArray();

            if ($existingDevice !== null) {
                if (!empty($existingDevice['revoked_at'])) {
                    return $this->apiResponse(null, null, ['Este dispositivo ha sido revocado.'], 403);
                }
                $this->db->table('devices')
                    ->where('id', (int) $existingDevice['id'])
                    ->update([
                        'user_agent'   => mb_substr($this->request->getUserAgent()->getAgentString(), 0, 250),
                        'last_sync_at' => $now,
                    ]);
            } else {
                $this->db->table('devices')->insert([
                    'activist_id'  => (int) $activist['id'],
                    'device_uuid'  => $deviceUuid,
                    'user_agent'   => mb_substr($this->request->getUserAgent()->getAgentString(), 0, 250),
                    'last_sync_at' => $now,
                    'created_at'   => $now,
                ]);
            }
        }

        // Generar token de 30 días (RF-A1)
        $plainToken = bin2hex(random_bytes(32));
        $tokenHash  = hash('sha256', $plainToken);
        $expiresAt  = date('Y-m-d H:i:s', strtotime('+30 days'));

        $this->db->table('auth_tokens')->insert([
            'user_id'      => (int) $user['id'],
            'device_uuid'  => $deviceUuid !== '' ? $deviceUuid : null,
            'token_hash'   => $tokenHash,
            'name'         => $deviceUuid !== '' ? 'pwa-token' : 'panel-token',
            'expires_at'   => $expiresAt,
            'last_used_at' => $now,
            'created_at'   => $now,
        ]);

        $this->db->table('users')
            ->where('id', (int) $user['id'])
            ->update(['last_login_at' => $now]);

        AuthContext::set($user, $activist, $tokenHash);
        $this->logAudit('auth.login', 'users', (int) $user['id'], ['device_uuid' => $deviceUuid]);

        return $this->apiResponse([
            'token'      => $plainToken,
            'expires_at' => $expiresAt,
            'user'       => $this->formatUserPayload($user, $activist),
        ]);
    }

    public function logout(): ResponseInterface
    {
        $tokenHash = AuthContext::tokenHash();
        if ($tokenHash !== null) {
            $this->db->table('auth_tokens')
                ->where('token_hash', $tokenHash)
                ->update(['revoked_at' => date('Y-m-d H:i:s')]);
        }

        $this->logAudit('auth.logout', 'users', $this->currentUser()['id'] ?? null);
        AuthContext::clear();

        return $this->apiResponse(['logged_out' => true]);
    }

    public function changePassword(): ResponseInterface
    {
        $user        = $this->currentUser();
        $input       = $this->inputData();
        $newPassword = (string) ($input['new_password'] ?? '');
        $curPassword = (string) ($input['current_password'] ?? '');

        if ($user === null) {
            return $this->apiResponse(null, null, ['No autenticado.'], 401);
        }

        if (mb_strlen($newPassword) < 8) {
            return $this->apiResponse(null, null, ['La nueva contraseña debe tener al menos 8 caracteres.'], 422);
        }

        if ((int) ($user['must_change_password'] ?? 0) === 0 && $curPassword !== '') {
            if (!password_verify($curPassword, (string) $user['password_hash'])) {
                return $this->apiResponse(null, null, ['La contraseña actual es incorrecta.'], 422);
            }
        }

        $now = date('Y-m-d H:i:s');
        $this->db->table('users')
            ->where('id', (int) $user['id'])
            ->update([
                'password_hash'        => password_hash($newPassword, PASSWORD_DEFAULT),
                'must_change_password' => 0,
                'updated_at'           => $now,
            ]);

        $this->logAudit('auth.password_change', 'users', (int) $user['id']);

        return $this->apiResponse([
            'must_change_password' => false,
            'message'              => 'Contraseña actualizada correctamente.',
        ]);
    }

    public function me(): ResponseInterface
    {
        $user     = $this->currentUser();
        $activist = $this->currentActivist();

        if ($user === null) {
            return $this->apiResponse(null, null, ['No autenticado.'], 401);
        }

        return $this->apiResponse($this->formatUserPayload($user, $activist));
    }

    public function progress(): ResponseInterface
    {
        $activist = $this->currentActivist();
        if ($activist === null) {
            return $this->apiResponse([
                'meta'              => 150,
                'meta_conector'     => 50,
                'meta_experto'      => 50,
                'meta_vendedor'     => 50,
                'registrados'       => 0,
                'validados'         => 0,
                'pendientes'        => 0,
                'observados'        => 0,
                'rechazados'        => 0,
                'porcentaje'        => 0.0,
                'por_clasificacion' => [
                    'Conector' => ['registrados' => 0, 'meta' => 50, 'porcentaje' => 0.0],
                    'Experto'  => ['registrados' => 0, 'meta' => 50, 'porcentaje' => 0.0],
                    'Vendedor' => ['registrados' => 0, 'meta' => 50, 'porcentaje' => 0.0],
                ],
            ]);
        }

        $activistId   = (int) $activist['id'];
        $metaConector = max(1, (int) ($activist['meta_conector'] ?? 50));
        $metaExperto  = max(1, (int) ($activist['meta_experto'] ?? 50));
        $metaVendedor = max(1, (int) ($activist['meta_vendedor'] ?? 50));
        $meta         = $metaConector + $metaExperto + $metaVendedor;

        $rows = $this->db->table('talents')
            ->select('validacion, COUNT(*) as total')
            ->where('activist_id', $activistId)
            ->where('deleted_at IS NULL')
            ->groupBy('validacion')
            ->get()
            ->getResultArray();

        $counts = [
            'pendiente' => 0,
            'validado'  => 0,
            'observado' => 0,
            'rechazado' => 0,
        ];
        $total = 0;

        foreach ($rows as $r) {
            $val = (string) $r['validacion'];
            $cnt = (int) $r['total'];
            if (isset($counts[$val])) {
                $counts[$val] = $cnt;
            }
            $total += $cnt;
        }

        $clasifRows = $this->db->table('talents')
            ->select('clasificacion, COUNT(*) as total')
            ->where('activist_id', $activistId)
            ->where('deleted_at IS NULL')
            ->where('validacion !=', 'rechazado')
            ->groupBy('clasificacion')
            ->get()
            ->getResultArray();

        $byClasif = [
            'Conector' => 0,
            'Experto'  => 0,
            'Vendedor' => 0,
        ];
        foreach ($clasifRows as $cr) {
            $cat = (string) ($cr['clasificacion'] ?? 'Conector');
            if (isset($byClasif[$cat])) {
                $byClasif[$cat] = (int) $cr['total'];
            }
        }

        $efectivos  = $total - $counts['rechazado'];
        $porcentaje = round(min(100, ($efectivos / $meta) * 100), 1);

        return $this->apiResponse([
            'activist_id'           => $activistId,
            'status'                => $activist['status'],
            'meta'                  => $meta,
            'meta_conector'         => $metaConector,
            'meta_experto'          => $metaExperto,
            'meta_vendedor'         => $metaVendedor,
            'fecha_limite'          => $activist['fecha_limite'],
            'registrados'           => $total,
            'validados'             => $counts['validado'],
            'pendientes_validacion' => $counts['pendiente'],
            'observados'            => $counts['observado'],
            'rechazados'            => $counts['rechazado'],
            'porcentaje'            => $porcentaje,
            'por_clasificacion'     => [
                'Conector' => [
                    'registrados' => $byClasif['Conector'],
                    'meta'        => $metaConector,
                    'porcentaje'  => round(min(100, ($byClasif['Conector'] / $metaConector) * 100), 1),
                ],
                'Experto' => [
                    'registrados' => $byClasif['Experto'],
                    'meta'        => $metaExperto,
                    'porcentaje'  => round(min(100, ($byClasif['Experto'] / $metaExperto) * 100), 1),
                ],
                'Vendedor' => [
                    'registrados' => $byClasif['Vendedor'],
                    'meta'        => $metaVendedor,
                    'porcentaje'  => round(min(100, ($byClasif['Vendedor'] / $metaVendedor) * 100), 1),
                ],
            ],
        ]);
    }

    /**
     * @param array<string, mixed>      $user
     * @param array<string, mixed>|null $activist
     * @return array<string, mixed>
     */
    private function formatUserPayload(array $user, ?array $activist): array
    {
        $payload = [
            'id'                   => (int) $user['id'],
            'username'             => (string) $user['username'],
            'email'                => $user['email'],
            'role'                 => (string) $user['role'],
            'status'               => (string) $user['status'],
            'must_change_password' => (bool) ((int) ($user['must_change_password'] ?? 0) === 1),
            'activist'             => null,
        ];

        if ($activist !== null) {
            $metaCon = (int) ($activist['meta_conector'] ?? 50);
            $metaExp = (int) ($activist['meta_experto'] ?? 50);
            $metaVen = (int) ($activist['meta_vendedor'] ?? 50);
            $payload['activist'] = [
                'id'            => (int) $activist['id'],
                'nombre'        => (string) $activist['nombre'],
                'ap_paterno'    => (string) $activist['ap_paterno'],
                'ap_materno'    => $activist['ap_materno'],
                'telefono'      => (string) $activist['telefono'],
                'estado_id'     => (int) $activist['estado_id'],
                'municipio_id'  => $activist['municipio_id'] !== null ? (int) $activist['municipio_id'] : null,
                'meta'          => $metaCon + $metaExp + $metaVen,
                'meta_conector' => $metaCon,
                'meta_experto'  => $metaExp,
                'meta_vendedor' => $metaVen,
                'fecha_limite'  => $activist['fecha_limite'],
                'status'        => (string) $activist['status'],
            ];
        }

        return $payload;
    }
}

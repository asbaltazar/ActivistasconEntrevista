<?php

declare(strict_types=1);

namespace App\Filters;

use App\Services\AuthContext;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Database;
use Config\Services;

class AuthFilter implements FilterInterface
{
    /**
     * @param array<string>|null $arguments
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        $authHeader = $request->getHeaderLine('Authorization');
        $plainToken = '';

        if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
            $plainToken = trim($matches[1]);
        } elseif ($request instanceof IncomingRequest) {
            $queryToken = $request->getGet('token');
            if (is_string($queryToken) && $queryToken !== '') {
                $plainToken = trim($queryToken);
            }
        }

        if ($plainToken === '') {
            return $this->unauthorized('Token de autenticación requerido.');
        }

        $tokenHash = hash('sha256', $plainToken);
        $db        = Database::connect();
        $now       = date('Y-m-d H:i:s');

        $tokenRow = $db->table('auth_tokens')
            ->where('token_hash', $tokenHash)
            ->where('revoked_at IS NULL')
            ->where('expires_at >=', $now)
            ->get()
            ->getRowArray();

        if ($tokenRow === null) {
            return $this->unauthorized('Sesión inválida o expirada.');
        }

        $user = $db->table('users')
            ->where('id', (int) $tokenRow['user_id'])
            ->where('deleted_at IS NULL')
            ->get()
            ->getRowArray();

        if ($user === null || ($user['status'] ?? '') !== 'activo') {
            return $this->unauthorized('Usuario inactivo o suspendido.');
        }

        // Actualizar last_used_at
        $db->table('auth_tokens')
            ->where('id', (int) $tokenRow['id'])
            ->update(['last_used_at' => $now]);

        // Cargar perfil de activista si corresponde
        $activist = $db->table('activists')
            ->where('user_id', (int) $user['id'])
            ->where('deleted_at IS NULL')
            ->get()
            ->getRowArray();

        if ($activist !== null) {
            if (($activist['status'] ?? '') === 'suspendido') {
                return $this->forbidden('Su cuenta de activista se encuentra suspendida.');
            }
            // Verificar si el dispositivo fue revocado
            if (!empty($tokenRow['device_uuid'])) {
                $device = $db->table('devices')
                    ->where('activist_id', (int) $activist['id'])
                    ->where('device_uuid', (string) $tokenRow['device_uuid'])
                    ->get()
                    ->getRowArray();
                if ($device !== null && !empty($device['revoked_at'])) {
                    return $this->unauthorized('Este dispositivo ha sido revocado por el administrador.');
                }
            }
        }

        // Validar roles permitidos si se especificaron en el filtro (ej. auth:superadmin,consulta)
        if (is_array($arguments) && count($arguments) > 0) {
            $userRole = (string) ($user['role'] ?? '');
            if (!in_array($userRole, $arguments, true)) {
                return $this->forbidden('No tiene permisos para realizar esta operación.');
            }
        }

        AuthContext::set($user, $activist, $tokenHash);

        return null;
    }

    /**
     * @param array<string>|null $arguments
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return $response;
    }

    private function unauthorized(string $message): ResponseInterface
    {
        return Services::response()
            ->setStatusCode(401)
            ->setJSON([
                'data'   => null,
                'meta'   => null,
                'errors' => [$message],
            ]);
    }

    private function forbidden(string $message): ResponseInterface
    {
        return Services::response()
            ->setStatusCode(403)
            ->setJSON([
                'data'   => null,
                'meta'   => null,
                'errors' => [$message],
            ]);
    }
}


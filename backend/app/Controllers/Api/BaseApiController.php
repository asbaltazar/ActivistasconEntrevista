<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Services\AuthContext;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Database;
use Throwable;

abstract class BaseApiController extends BaseController
{
    /** @var BaseConnection<mixed, mixed> */
    protected BaseConnection $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    /**
     * Devuelve la respuesta JSON estándar `{ data, meta, errors }` definida en el PRD §8.
     *
     * @param array<string, mixed>|null $meta
     * @param array<int, string>|null   $errors
     */
    protected function apiResponse(
        mixed $data = null,
        ?array $meta = null,
        ?array $errors = null,
        int $status = 200
    ): ResponseInterface {
        return $this->response
            ->setStatusCode($status)
            ->setJSON([
                'data'   => $data,
                'meta'   => $meta,
                'errors' => $errors,
            ]);
    }

    /**
     * Obtiene el cuerpo de la petición como arreglo asociativo (JSON o form-data).
     *
     * @return array<string, mixed>
     */
    protected function inputData(): array
    {
        try {
            $json = $this->request->getJSON(true);
            if (is_array($json) && count($json) > 0) {
                return $json;
            }
        } catch (Throwable) {
            // No es JSON, continuar a post/raw
        }

        $post = $this->request->getPost();
        if (is_array($post) && count($post) > 0) {
            return $post;
        }

        $raw = $this->request->getRawInput();
        return is_array($raw) ? $raw : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function currentUser(): ?array
    {
        return AuthContext::user();
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function currentActivist(): ?array
    {
        return AuthContext::activist();
    }

    /**
     * Verifica que el usuario autenticado tenga rol de escritura (no solo 'consulta').
     */
    protected function requireWriteRole(): ?ResponseInterface
    {
        $user = $this->currentUser();
        if (($user['role'] ?? '') === 'consulta') {
            return $this->apiResponse(null, null, ['El rol Capturista/Consulta solo tiene permisos de lectura.'], 403);
        }
        return null;
    }

    /**
     * Registra un evento en la bitácora de auditoría (`audit_log`).
     *
     * @param array<string, mixed> $payload
     */
    protected function logAudit(string $action, string $entity, string|int|null $entityId = null, array $payload = []): void
    {
        try {
            $user = $this->currentUser();
            $this->db->table('audit_log')->insert([
                'user_id'    => $user !== null ? (int) $user['id'] : null,
                'action'     => $action,
                'entity'     => $entity,
                'entity_id'  => $entityId !== null ? (string) $entityId : null,
                'payload'    => count($payload) > 0 ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null,
                'ip'         => $this->request->getIPAddress(),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (Throwable) {
            // No interrumpir flujo principal si falla el log
        }
    }
}

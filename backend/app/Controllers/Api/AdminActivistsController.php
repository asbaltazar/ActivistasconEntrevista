<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use CodeIgniter\HTTP\ResponseInterface;

class AdminActivistsController extends BaseApiController
{
    public function index(): ResponseInterface
    {
        $search   = trim((string) ($this->request->getGet('q') ?? ''));
        $status   = trim((string) ($this->request->getGet('status') ?? ''));
        $estadoId = (int) ($this->request->getGet('estado_id') ?? 0);
        $page     = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage  = min(100, max(10, (int) ($this->request->getGet('per_page') ?? 25)));

        $builder = $this->db->table('activists a')
            ->select('a.*, u.username, u.email, u.last_login_at, u.must_change_password')
            ->join('users u', 'u.id = a.user_id', 'left')
            ->where('a.deleted_at IS NULL');

        if ($status !== '') {
            $builder->where('a.status', $status);
        }
        if ($estadoId > 0) {
            $builder->where('a.estado_id', $estadoId);
        }
        if ($search !== '') {
            $builder->groupStart()
                ->like('a.nombre', $search)
                ->orLike('a.ap_paterno', $search)
                ->orLike('a.ap_materno', $search)
                ->orLike('a.telefono', $search)
                ->orLike('u.username', $search)
                ->groupEnd();
        }

        $total = $builder->countAllResults(false);
        $rows  = $builder->orderBy('a.id', 'DESC')
            ->limit($perPage, ($page - 1) * $perPage)
            ->get()
            ->getResultArray();

        $talentStats = [];
        $statRows = $this->db->table('talents')
            ->select('activist_id, COUNT(*) as total, SUM(CASE WHEN validacion = \'validado\' THEN 1 ELSE 0 END) as validados, MAX(created_at) as last_capture_at')
            ->where('deleted_at IS NULL')
            ->groupBy('activist_id')
            ->get()
            ->getResultArray();

        foreach ($statRows as $sr) {
            $talentStats[(int) $sr['activist_id']] = [
                'registrados'     => (int) $sr['total'],
                'validados'       => (int) $sr['validados'],
                'last_capture_at' => $sr['last_capture_at'],
            ];
        }

        $data = [];
        foreach ($rows as $row) {
            $actId       = (int) $row['id'];
            $meta        = max(1, (int) $row['meta']);
            $registrados = $talentStats[$actId]['registrados'] ?? 0;
            $validados   = $talentStats[$actId]['validados'] ?? 0;
            $porcentaje  = round(min(100, ($registrados / $meta) * 100), 1);

            $devices = $this->db->table('devices')
                ->where('activist_id', $actId)
                ->where('revoked_at IS NULL')
                ->get()
                ->getResultArray();

            $data[] = [
                'id'                     => $actId,
                'user_id'                => (int) $row['user_id'],
                'username'               => (string) $row['username'],
                'email'                  => $row['email'],
                'nombre'                 => (string) $row['nombre'],
                'ap_paterno'             => (string) $row['ap_paterno'],
                'ap_materno'             => $row['ap_materno'],
                'nombre_completo'        => trim($row['nombre'] . ' ' . $row['ap_paterno'] . ' ' . ($row['ap_materno'] ?? '')),
                'telefono'               => (string) $row['telefono'],
                'telefono_verificado_at' => $row['telefono_verificado_at'],
                'estado_id'              => (int) $row['estado_id'],
                'municipio_id'           => $row['municipio_id'] !== null ? (int) $row['municipio_id'] : null,
                'meta'                   => (int) $row['meta'],
                'fecha_limite'           => $row['fecha_limite'],
                'status'                 => (string) $row['status'],
                'registrados'            => $registrados,
                'validados'              => $validados,
                'porcentaje'             => $porcentaje,
                'last_login_at'          => $row['last_login_at'],
                'last_capture_at'        => $talentStats[$actId]['last_capture_at'] ?? null,
                'active_devices'         => count($devices),
                'created_at'             => $row['created_at'],
            ];
        }

        return $this->apiResponse($data, [
            'page'     => $page,
            'per_page' => $perPage,
            'total'    => $total,
        ]);
    }

    public function create(): ResponseInterface
    {
        if (($deny = $this->requireWriteRole()) !== null) {
            return $deny;
        }

        $input       = $this->inputData();
        $nombre      = trim((string) ($input['nombre'] ?? ''));
        $apPaterno   = trim((string) ($input['ap_paterno'] ?? ''));
        $apMaterno   = trim((string) ($input['ap_materno'] ?? ''));
        $telefono    = (string) preg_replace('/\D/', '', (string) ($input['telefono'] ?? ''));
        $email       = !empty($input['email']) ? trim((string) $input['email']) : null;
        $estadoId    = (int) ($input['estado_id'] ?? 9);
        $municipioId = !empty($input['municipio_id']) ? (int) $input['municipio_id'] : null;
        $meta        = max(1, (int) ($input['meta'] ?? 50));
        $fechaLimite = !empty($input['fecha_limite']) ? trim((string) $input['fecha_limite']) : date('Y-m-d', strtotime('+30 days'));

        if ($nombre === '' || $apPaterno === '') {
            return $this->apiResponse(null, null, ['El nombre y apellido paterno son obligatorios.'], 422);
        }
        if (!preg_match('/^\d{10}$/', $telefono)) {
            return $this->apiResponse(null, null, ['El teléfono celular del Activista debe tener exactamente 10 dígitos.'], 422);
        }

        $existsTel = $this->db->table('activists')
            ->where('telefono', $telefono)
            ->where('deleted_at IS NULL')
            ->countAllResults();

        if ($existsTel > 0) {
            return $this->apiResponse(null, null, ['El número de teléfono ya está registrado con otro Activista.'], 409);
        }

        // Generar username único y contraseña temporal
        $baseUser = strtolower((string) preg_replace('/[^a-zA-Z0-9]/', '', substr($nombre, 0, 3) . $apPaterno)) ?: 'act';
        $username = substr($baseUser, 0, 12) . substr($telefono, -4);
        if ($this->db->table('users')->where('username', $username)->countAllResults() > 0) {
            $username .= random_int(10, 99);
        }

        $tempPassword = 'Act' . random_int(1000, 9999) . '!';
        $now          = date('Y-m-d H:i:s');

        $this->db->table('users')->insert([
            'username'             => $username,
            'email'                => $email,
            'password_hash'        => password_hash($tempPassword, PASSWORD_DEFAULT),
            'role'                 => 'activista',
            'status'               => 'activo',
            'must_change_password' => 1,
            'created_at'           => $now,
            'updated_at'           => $now,
        ]);
        $userId = (int) $this->db->insertID();

        // Alta directa en estado 'activo'
        $this->db->table('activists')->insert([
            'user_id'                => $userId,
            'nombre'                 => $nombre,
            'ap_paterno'             => $apPaterno,
            'ap_materno'             => $apMaterno !== '' ? $apMaterno : null,
            'telefono'               => $telefono,
            'telefono_verificado_at' => $now,
            'estado_id'              => $estadoId,
            'municipio_id'           => $municipioId,
            'meta'                   => $meta,
            'fecha_limite'           => $fechaLimite,
            'status'                 => 'activo',
            'created_at'             => $now,
            'updated_at'             => $now,
        ]);
        $activistId = (int) $this->db->insertID();

        $this->logAudit('activist.create', 'activists', $activistId, ['username' => $username, 'telefono' => $telefono, 'meta' => $meta]);

        return $this->apiResponse([
            'id'                 => $activistId,
            'user_id'            => $userId,
            'username'           => $username,
            'temporary_password' => $tempPassword,
            'telefono'           => $telefono,
            'status'             => 'activo',
            'whatsapp_url'       => 'https://wa.me/52' . $telefono . '?text=' . rawurlencode("Hola {$nombre}, tus credenciales de Red Ciudadana son:\nUsuario: {$username}\nContraseña temporal: {$tempPassword}\nApp: https://activistas.solytecmx.com"),
        ], null, null, 201);
    }

    public function update(int $id): ResponseInterface
    {
        if (($deny = $this->requireWriteRole()) !== null) {
            return $deny;
        }

        $activist = $this->db->table('activists')
            ->where('id', $id)
            ->where('deleted_at IS NULL')
            ->get()
            ->getRowArray();

        if ($activist === null) {
            return $this->apiResponse(null, null, ['Activista no encontrado.'], 404);
        }

        $input        = $this->inputData();
        $now          = date('Y-m-d H:i:s');
        $newTelefono  = isset($input['telefono']) ? (string) preg_replace('/\D/', '', (string) $input['telefono']) : (string) $activist['telefono'];
        $phoneChanged = $newTelefono !== (string) $activist['telefono'];

        if ($phoneChanged) {
            if (!preg_match('/^\d{10}$/', $newTelefono)) {
                return $this->apiResponse(null, null, ['El teléfono debe tener 10 dígitos.'], 422);
            }
            $dup = $this->db->table('activists')
                ->where('telefono', $newTelefono)
                ->where('id !=', $id)
                ->where('deleted_at IS NULL')
                ->countAllResults();
            if ($dup > 0) {
                return $this->apiResponse(null, null, ['El teléfono ya pertenece a otro Activista.'], 409);
            }
        }

        $updates = [
            'nombre'       => isset($input['nombre']) ? trim((string) $input['nombre']) : $activist['nombre'],
            'ap_paterno'   => isset($input['ap_paterno']) ? trim((string) $input['ap_paterno']) : $activist['ap_paterno'],
            'ap_materno'   => array_key_exists('ap_materno', $input) ? trim((string) $input['ap_materno']) : $activist['ap_materno'],
            'telefono'     => $newTelefono,
            'estado_id'    => isset($input['estado_id']) ? (int) $input['estado_id'] : (int) $activist['estado_id'],
            'municipio_id' => array_key_exists('municipio_id', $input) ? (!empty($input['municipio_id']) ? (int) $input['municipio_id'] : null) : $activist['municipio_id'],
            'meta'         => isset($input['meta']) ? max(1, (int) $input['meta']) : (int) $activist['meta'],
            'fecha_limite' => array_key_exists('fecha_limite', $input) ? $input['fecha_limite'] : $activist['fecha_limite'],
            'status'       => isset($input['status']) ? (string) $input['status'] : $activist['status'],
            'updated_at'   => $now,
        ];

        $this->db->table('activists')->where('id', $id)->update($updates);

        if (isset($input['status'])) {
            $userStatus = $input['status'] === 'suspendido' ? 'suspendido' : 'activo';
            $this->db->table('users')->where('id', (int) $activist['user_id'])->update(['status' => $userStatus, 'updated_at' => $now]);
        }

        $this->logAudit('activist.update', 'activists', $id, ['phone_changed' => $phoneChanged]);

        return $this->apiResponse([
            'id'            => $id,
            'phone_changed' => $phoneChanged,
            'status'        => $updates['status'],
        ]);
    }

    public function delete(int $id): ResponseInterface
    {
        if (($deny = $this->requireWriteRole()) !== null) {
            return $deny;
        }

        $activist = $this->db->table('activists')
            ->where('id', $id)
            ->where('deleted_at IS NULL')
            ->get()
            ->getRowArray();

        if ($activist === null) {
            return $this->apiResponse(null, null, ['Activista no encontrado.'], 404);
        }

        $now = date('Y-m-d H:i:s');
        $this->db->table('activists')->where('id', $id)->update([
            'status'     => 'suspendido',
            'deleted_at' => $now,
        ]);
        $this->db->table('users')->where('id', (int) $activist['user_id'])->update([
            'status'     => 'suspendido',
            'deleted_at' => $now,
        ]);
        $this->db->table('auth_tokens')->where('user_id', (int) $activist['user_id'])->update(['revoked_at' => $now]);
        $this->db->table('devices')->where('activist_id', $id)->update(['revoked_at' => $now]);

        $this->logAudit('activist.delete', 'activists', $id);

        return $this->apiResponse(['deleted' => true, 'id' => $id]);
    }

    public function resetPassword(int $id): ResponseInterface
    {
        if (($deny = $this->requireWriteRole()) !== null) {
            return $deny;
        }

        $activist = $this->db->table('activists')
            ->where('id', $id)
            ->where('deleted_at IS NULL')
            ->get()
            ->getRowArray();

        if ($activist === null) {
            return $this->apiResponse(null, null, ['Activista no encontrado.'], 404);
        }

        $user = $this->db->table('users')->where('id', (int) $activist['user_id'])->get()->getRowArray();
        if ($user === null) {
            return $this->apiResponse(null, null, ['Usuario asociado no encontrado.'], 404);
        }

        $tempPassword = 'Act' . random_int(1000, 9999) . '!';
        $now          = date('Y-m-d H:i:s');

        $this->db->table('users')->where('id', (int) $user['id'])->update([
            'password_hash'        => password_hash($tempPassword, PASSWORD_DEFAULT),
            'must_change_password' => 1,
            'updated_at'           => $now,
        ]);

        // Revocar tokens previos
        $this->db->table('auth_tokens')->where('user_id', (int) $user['id'])->update(['revoked_at' => $now]);

        $this->logAudit('activist.reset_password', 'activists', $id);

        return $this->apiResponse([
            'id'                 => $id,
            'username'           => $user['username'],
            'temporary_password' => $tempPassword,
            'whatsapp_url'       => 'https://wa.me/52' . $activist['telefono'] . '?text=' . rawurlencode("Hola {$activist['nombre']}, tu nueva contraseña temporal es: {$tempPassword} (Usuario: {$user['username']})"),
        ]);
    }

    public function revokeDevices(int $id): ResponseInterface
    {
        if (($deny = $this->requireWriteRole()) !== null) {
            return $deny;
        }

        $activist = $this->db->table('activists')->where('id', $id)->get()->getRowArray();
        if ($activist === null) {
            return $this->apiResponse(null, null, ['Activista no encontrado.'], 404);
        }

        $now = date('Y-m-d H:i:s');
        $this->db->table('devices')->where('activist_id', $id)->update(['revoked_at' => $now]);
        $this->db->table('auth_tokens')->where('user_id', (int) $activist['user_id'])->update(['revoked_at' => $now]);

        $this->logAudit('activist.revoke_devices', 'activists', $id);

        return $this->apiResponse(['revoked' => true, 'activist_id' => $id]);
    }
}

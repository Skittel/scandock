<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Person who signed in via Entra ID. Users are created on first login.
 */
class UserModel extends Model
{
    protected $table         = 'users';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['tenant_id', 'entra_oid', 'upn', 'name', 'group_ids', 'collapsed_accounts', 'theme', 'last_login_at'];

    /**
     * Creates or updates the user from validated ID token claims.
     *
     * @param list<string> $groupIds
     */
    public function upsertFromLogin(int $tenantId, string $oid, string $upn, string $name, array $groupIds): array
    {
        $data = [
            'tenant_id'     => $tenantId,
            'entra_oid'     => $oid,
            'upn'           => strtolower($upn),
            'name'          => $name,
            'group_ids'     => json_encode(array_values($groupIds)),
            'last_login_at' => date('Y-m-d H:i:s'),
        ];

        $existing = $this->where('tenant_id', $tenantId)->where('entra_oid', $oid)->first();
        if ($existing !== null) {
            $this->update($existing['id'], $data);

            return $this->find($existing['id']);
        }

        return $this->find($this->insert($data));
    }

    /**
     * @param list<string> $groupIds
     */
    public function updateGroups(int $userId, array $groupIds): void
    {
        $this->update($userId, ['group_ids' => json_encode(array_values($groupIds))]);
    }

    /**
     * IDs of the FTP account sections the user collapsed on the scan page.
     *
     * @return list<int>
     */
    public static function collapsedAccounts(array $user): array
    {
        return array_map('intval', json_decode($user['collapsed_accounts'] ?? '[]', true) ?: []);
    }

    /**
     * @return list<string>
     */
    public static function groupIds(array $user): array
    {
        return json_decode($user['group_ids'] ?? '[]', true) ?: [];
    }
}

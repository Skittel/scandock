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
    protected $allowedFields = ['tenant_id', 'entra_oid', 'upn', 'name', 'group_ids', 'groups_synced_at', 'collapsed_accounts', 'account_views', 'theme', 'last_login_at'];

    /**
     * Creates or updates the user from validated ID token claims. With
     * $groupIds null (groups could not be read) the stored groups are kept.
     * Person entries of FTP accounts that still only know the UPN are bound
     * to the user's object ID.
     *
     * @param list<string>|null $groupIds
     */
    public function upsertFromLogin(int $tenantId, string $oid, string $upn, string $name, ?array $groupIds): array
    {
        $data = [
            'tenant_id'     => $tenantId,
            'entra_oid'     => $oid,
            'upn'           => strtolower($upn),
            'name'          => $name,
            'last_login_at' => date('Y-m-d H:i:s'),
        ];
        if ($groupIds !== null) {
            $data['group_ids']        = json_encode(array_values($groupIds));
            $data['groups_synced_at'] = date('Y-m-d H:i:s');
        }

        $this->db->query(
            "UPDATE ftp_account_members m JOIN ftp_accounts a ON a.id = m.ftp_account_id
             SET m.member_oid = ? WHERE a.tenant_id = ? AND m.member_type = 'user' AND m.member_oid IS NULL AND m.member_ref = ?",
            [$oid, $tenantId, strtolower($upn)]
        );

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
        $this->update($userId, ['group_ids' => json_encode(array_values($groupIds)), 'groups_synced_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * Whether the stored group memberships are too old to be trusted (e.g.
     * Microsoft Graph has not been reachable for a while).
     */
    public static function groupsStale(array $user): bool
    {
        return empty($user['groups_synced_at'])
            || strtotime($user['groups_synced_at']) < time() - config('ScanDock')->groupsMaxAge;
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
     * IDs of the FTP accounts the user shows as a list instead of tiles.
     *
     * @return list<int>
     */
    public static function listAccounts(array $user): array
    {
        return array_map('intval', json_decode($user['account_views'] ?? '[]', true) ?: []);
    }

    /**
     * @return list<string>
     */
    public static function groupIds(array $user): array
    {
        // Safe state: outdated memberships grant no group-based rights.
        if (self::groupsStale($user)) {
            return [];
        }

        return json_decode($user['group_ids'] ?? '[]', true) ?: [];
    }
}

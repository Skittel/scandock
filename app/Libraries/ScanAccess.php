<?php

namespace App\Libraries;

use App\Models\FtpAccountModel;
use App\Models\UserModel;

/**
 * Decides which scans and features a signed-in user may access.
 *
 * Scans of public FTP accounts are visible to every user of the tenant.
 * Scans of private accounts are visible only to assigned users and groups –
 * admin rights do not grant access to private scans.
 */
class ScanAccess
{
    /**
     * As long as no admin group is configured, every user is an admin.
     */
    public function isAdmin(array $tenant, array $user): bool
    {
        if (empty($tenant['admin_group_id'])) {
            return true;
        }

        return in_array(strtolower($tenant['admin_group_id']), UserModel::groupIds($user), true);
    }

    /**
     * IDs of the FTP accounts whose scans the user may see.
     *
     * @return list<int>
     */
    public function visibleAccountIds(array $tenant, array $user): array
    {
        $groups = UserModel::groupIds($user);
        $rows   = model(FtpAccountModel::class)
            ->select('id')
            ->where('tenant_id', $tenant['id'])
            ->groupStart()
                ->where('visibility', 'public')
                ->orWhereIn('id', static function ($builder) use ($user, $groups) {
                    $builder->select('ftp_account_id')->from('ftp_account_members')
                        ->groupStart()
                            ->where('member_type', 'user')->where('member_ref', strtolower($user['upn']))
                        ->groupEnd();
                    if ($groups !== []) {
                        $builder->orGroupStart()
                            ->where('member_type', 'group')->whereIn('member_ref', $groups)
                        ->groupEnd();
                    }

                    return $builder;
                })
            ->groupEnd()
            ->findAll();

        return array_map('intval', array_column($rows, 'id'));
    }

    public function canAccessScan(array $tenant, array $user, array $scan): bool
    {
        return (int) $scan['tenant_id'] === (int) $tenant['id']
            && $scan['ftp_account_id'] !== null
            && in_array((int) $scan['ftp_account_id'], $this->visibleAccountIds($tenant, $user), true);
    }
}

<?php

namespace App\Libraries;

use App\Models\FtpAccountModel;
use App\Models\UserModel;

/**
 * Decides which scans and features a signed-in user may access.
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
        $accounts = model(FtpAccountModel::class)->where('tenant_id', $tenant['id']);

        if (! $this->isAdmin($tenant, $user)) {
            $groups = UserModel::groupIds($user);
            $accounts->whereIn('id', static function ($builder) use ($user, $groups) {
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
            });
        }

        return array_map('intval', array_column($accounts->select('id')->findAll(), 'id'));
    }

    public function canAccessScan(array $tenant, array $user, array $scan): bool
    {
        if ((int) $scan['tenant_id'] !== (int) $tenant['id']) {
            return false;
        }
        if ($this->isAdmin($tenant, $user)) {
            return true;
        }

        return $scan['ftp_account_id'] !== null
            && in_array((int) $scan['ftp_account_id'], $this->visibleAccountIds($tenant, $user), true);
    }
}

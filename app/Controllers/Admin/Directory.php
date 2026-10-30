<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\FtpAccountMemberModel;
use App\Models\UserModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Autocomplete for users and groups when assigning them to FTP accounts.
 * Combines the Entra directory (if permitted) with locally known users and groups.
 */
class Directory extends BaseController
{
    public function search(): ResponseInterface
    {
        $tenant = $this->tenant();
        $query  = trim((string) $this->request->getGet('q'));
        if (mb_strlen($query) < 2) {
            return $this->response->setJSON(['results' => [], 'directory' => null]);
        }

        $directory = service('directory');
        $users     = $directory->searchUsers($tenant, $query);
        $groups    = $directory->searchGroups($tenant, $query);

        $results = array_merge($users ?? [], $groups ?? [], $this->localMatches((int) $tenant['id'], $query));

        // De-duplicate by type and reference, directory entries win.
        $unique = [];
        foreach ($results as $item) {
            if ($item['ref'] !== '') {
                $unique[$item['type'] . ':' . $item['ref']] ??= $item;
            }
        }

        return $this->response->setJSON([
            'results'   => array_slice(array_values($unique), 0, 20),
            'directory' => $users !== null || $groups !== null,
        ]);
    }

    /**
     * Users who signed in before and groups already used in this tenant.
     */
    private function localMatches(int $tenantId, string $query): array
    {
        $like  = strtolower($query);
        $users = model(UserModel::class)->where('tenant_id', $tenantId)
            ->groupStart()->like('name', $like)->orLike('upn', $like)->groupEnd()
            ->orderBy('name')->findAll(10);

        $groups = model(FtpAccountMemberModel::class)
            ->select('ftp_account_members.member_ref, ftp_account_members.label')
            ->join('ftp_accounts', 'ftp_accounts.id = ftp_account_members.ftp_account_id')
            ->where('ftp_accounts.tenant_id', $tenantId)
            ->where('member_type', 'group')
            ->groupStart()->like('ftp_account_members.label', $like)->orLike('member_ref', $like)->groupEnd()
            ->groupBy('member_ref, ftp_account_members.label')
            ->findAll(10);

        return array_merge(
            array_map(static fn ($u) => ['type' => 'user', 'ref' => $u['upn'], 'label' => $u['name'], 'detail' => $u['upn']], $users),
            array_map(static fn ($g) => ['type' => 'group', 'ref' => $g['member_ref'], 'label' => $g['label'] ?: $g['member_ref'], 'detail' => 'Gruppe'], $groups)
        );
    }
}

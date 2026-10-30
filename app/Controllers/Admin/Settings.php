<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\TenantModel;
use App\Models\UserModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Tenant settings: admin group and retention period.
 */
class Settings extends BaseController
{
    private const GUID = '/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/';

    public function index(): string
    {
        return $this->render('admin/settings', [
            'userGroups' => UserModel::groupIds($this->currentUser()),
        ]);
    }

    public function save(): ResponseInterface
    {
        $tenant    = $this->tenant();
        $groupId   = strtolower(trim((string) $this->request->getPost('admin_group_id')));
        $retention = (int) $this->request->getPost('retention_days');

        if ($groupId !== '' && preg_match(self::GUID, $groupId) !== 1) {
            return redirect()->back()->withInput()->with('error', 'Die Objekt-ID der Admin-Gruppe hat nicht das erwartete Format.');
        }
        if ($retention < 1 || $retention > 999) {
            return redirect()->back()->withInput()->with('error', 'Die Aufbewahrungsdauer muss zwischen 1 und 999 Tagen liegen.');
        }
        // Prevent admins from locking themselves out.
        if ($groupId !== '' && ! in_array($groupId, UserModel::groupIds($this->currentUser()), true)
            && $this->request->getPost('confirm_lockout') !== '1') {
            return redirect()->back()->withInput()->with('error', 'Sie sind nicht Mitglied dieser Gruppe und würden Ihre Admin-Rechte verlieren. Zum Bestätigen das Häkchen setzen.');
        }

        model(TenantModel::class)->update($tenant['id'], [
            'admin_group_id' => $groupId === '' ? null : $groupId,
            'retention_days' => $retention,
        ]);

        return redirect()->to('admin/settings')->with('notice', 'Einstellungen gespeichert.');
    }
}

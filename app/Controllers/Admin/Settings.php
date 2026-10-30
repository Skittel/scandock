<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\TenantModel;
use App\Models\UserModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Tenant settings: admin group, retention period and Entra client secret renewal.
 */
class Settings extends BaseController
{
    private const GUID = '/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/';

    public function index(): string
    {
        return $this->render('admin/settings', [
            'userGroups' => UserModel::groupIds($this->currentUser()),
            'maxExpiry'  => date('Y-m-d', strtotime('+' . config('ScanKai')->secretMaxLifetimeDays . ' days')),
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
            return redirect()->back()->withInput()->with('error', 'Laut Ihrer letzten Anmeldung sind Sie nicht Mitglied dieser Gruppe und würden Ihre Admin-Rechte verlieren. Gruppenmitgliedschaften übermittelt Microsoft nur bei der Anmeldung: Nach Änderungen bitte ab- und wieder anmelden. Zum Bestätigen das Häkchen setzen.');
        }

        model(TenantModel::class)->update($tenant['id'], [
            'admin_group_id' => $groupId === '' ? null : $groupId,
            'retention_days' => $retention,
        ]);

        return redirect()->to('admin/settings')->with('notice', 'Einstellungen gespeichert.');
    }

    /**
     * Replaces the Entra client secret after checking it with Microsoft.
     */
    public function renewSecret(): ResponseInterface
    {
        $tenant  = $this->tenant();
        $secret  = trim((string) $this->request->getPost('entra_client_secret'));
        $expires = trim((string) $this->request->getPost('entra_secret_expires_at'));

        if ($secret === '') {
            return redirect()->to('admin/settings#secret')->with('error', 'Bitte den Wert des neuen geheimen Clientschlüssels eintragen.');
        }
        if (($error = TenantModel::validateSecretExpiry($expires)) !== null) {
            return redirect()->to('admin/settings#secret')->with('error', $error);
        }
        $error = service('entra')->verifyClientSecret($tenant['entra_tenant_id'], $tenant['entra_client_id'], $secret);
        if ($error !== null) {
            return redirect()->to('admin/settings#secret')->with('error', 'Microsoft meldet: ' . $error);
        }

        model(TenantModel::class)->update($tenant['id'], [
            'entra_client_secret'     => TenantModel::encryptSecret($secret),
            'entra_secret_expires_at' => $expires,
        ]);

        return redirect()->to('admin/settings#secret')->with('notice', 'Der neue Clientschlüssel funktioniert und wurde gespeichert. Den alten Schlüssel können Sie in Entra jetzt löschen.');
    }
}

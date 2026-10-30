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

    public function index(): ResponseInterface|string
    {
        // Continue a save that needed a silent sign-in to refresh the groups.
        $pending = session('pending_settings');
        if (is_array($pending)) {
            session()->remove('pending_settings');

            return $this->applySettings($pending, true);
        }

        return $this->render('admin/settings', [
            'userGroups' => UserModel::groupIds($this->currentUser()),
            'maxExpiry'  => date('Y-m-d', strtotime('+' . config('ScanDock')->secretMaxLifetimeDays . ' days')),
        ]);
    }

    public function save(): ResponseInterface
    {
        return $this->applySettings($this->request->getPost(['admin_group_id', 'retention_days', 'confirm_lockout']), false);
    }

    /**
     * Validates and stores the settings. If the user does not seem to be a
     * member of the new admin group, the group memberships are synchronised
     * with Microsoft first (directly or via a silent sign-in).
     */
    private function applySettings(array $input, bool $afterSync): ResponseInterface
    {
        $tenant    = $this->tenant();
        $user      = $this->currentUser();
        $groupId   = strtolower(trim((string) ($input['admin_group_id'] ?? '')));
        $retention = (int) ($input['retention_days'] ?? 0);
        $confirmed = ($input['confirm_lockout'] ?? '') === '1';

        if ($groupId !== '' && preg_match(self::GUID, $groupId) !== 1) {
            return redirect()->to('admin/settings')->withInput()->with('error', 'Die Objekt-ID der Admin-Gruppe hat nicht das erwartete Format.');
        }
        if ($retention < 1 || $retention > 999) {
            return redirect()->to('admin/settings')->withInput()->with('error', 'Die Aufbewahrungsdauer muss zwischen 1 und 999 Tagen liegen.');
        }

        // Prevent admins from locking themselves out.
        if ($groupId !== '' && ! $confirmed && ! in_array($groupId, UserModel::groupIds($user), true)) {
            if (! $afterSync) {
                $fresh = $this->groupsFromGraph();
                if ($fresh === null) {
                    // Access token expired: refresh the groups by a silent sign-in and continue afterwards.
                    session()->set(['pending_settings' => $input, 'login_return' => '/admin/settings']);

                    return redirect()->to('auth/login?silent=1');
                }
                model(UserModel::class)->updateGroups((int) $user['id'], $fresh);
                $user = model(UserModel::class)->find($user['id']);
            }
            if (! in_array($groupId, UserModel::groupIds($user), true)) {
                return redirect()->to('admin/settings')->withInput()->with('error', 'Ihre Gruppen wurden gerade mit Microsoft abgeglichen: Sie sind nicht Mitglied dieser Gruppe und würden Ihre Admin-Rechte verlieren. Bitte die Objekt-ID prüfen oder zum Bestätigen das Häkchen setzen.');
            }
        }

        model(TenantModel::class)->update($tenant['id'], [
            'admin_group_id' => $groupId === '' ? null : $groupId,
            'retention_days' => $retention,
        ]);

        return redirect()->to('admin/settings')->with('notice', 'Einstellungen gespeichert.');
    }

    /**
     * Current group IDs from Microsoft Graph, or null if the access token of
     * this session is missing or expired.
     *
     * @return list<string>|null
     */
    private function groupsFromGraph(): ?array
    {
        $token = session('graph_token');
        if (! is_string($token) || (int) session('graph_token_expiry') <= time()) {
            return null;
        }

        return service('entra')->fetchGroupIds($token);
    }

    /**
     * Saves the display name and, if given, a new logo.
     */
    public function saveBranding(): ResponseInterface
    {
        $tenant = $this->tenant();
        $name   = trim((string) $this->request->getPost('name'));
        if ($name === '' || mb_strlen($name) > 190) {
            return redirect()->to('admin/settings#branding')->withInput()->with('error', 'Bitte einen Firmennamen mit höchstens 190 Zeichen angeben.');
        }
        model(TenantModel::class)->update($tenant['id'], ['name' => $name]);

        $file = $this->request->getFile('logo');
        if ($file !== null && $file->getError() !== UPLOAD_ERR_NO_FILE) {
            $error = service('tenantBranding')->store($tenant, $file);
            if ($error !== null) {
                return redirect()->to('admin/settings#branding')->with('error', $error);
            }
        }

        return redirect()->to('admin/settings#branding')->with('notice', 'Firmenname und Logo gespeichert.');
    }

    public function deleteLogo(): ResponseInterface
    {
        service('tenantBranding')->delete($this->tenant());

        return redirect()->to('admin/settings#branding')->with('notice', 'Das Logo wurde entfernt.');
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

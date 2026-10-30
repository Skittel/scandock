<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\FtpAccountMemberModel;
use App\Models\FtpAccountModel;
use App\Models\ScanModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Admin pages for FTP accounts: credentials, visibility (public/private) and
 * the users/groups that may see the scans of private accounts.
 */
class FtpAccounts extends BaseController
{
    private const GUID = '/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/';

    public function index(): string
    {
        $tenant   = $this->tenant();
        $accounts = model(FtpAccountModel::class)->forTenant((int) $tenant['id']);
        $counts   = [];
        foreach ($accounts as $account) {
            $counts[$account['id']] = model(ScanModel::class)->where('ftp_account_id', $account['id'])->countAllResults();
        }

        return $this->render('admin/ftp_index', ['accounts' => $accounts, 'counts' => $counts]);
    }

    public function create(): ResponseInterface
    {
        $label = trim((string) $this->request->getPost('label'));
        if ($label === '' || mb_strlen($label) > 190) {
            return redirect()->back()->with('error', 'Bitte eine Bezeichnung (max. 190 Zeichen) angeben, z. B. den Standort des Kopierers.');
        }
        $visibility = $this->request->getPost('visibility') === 'public' ? 'public' : 'private';
        $account    = service('ftpAccountManager')->create($this->tenant(), $label, $visibility);

        return redirect()->to('admin/ftp/' . $account['public_id'])
            ->with('notice', 'Das FTP-Konto wurde angelegt.')
            ->with('reveal_password', $account['public_id']);
    }

    public function show(string $id): string
    {
        $account = $this->findAccount($id);
        $config  = config('ScanKai');

        return $this->render('admin/ftp_show', [
            'account'  => $account,
            // The password is shown in plain text only right after it was created.
            'password' => session()->getFlashdata('reveal_password') === $id ? FtpAccountModel::plainPassword($account) : null,
            'members'  => model(FtpAccountMemberModel::class)->forAccount((int) $account['id']),
            'host'     => $config->ftpHost,
            'port'     => $config->ftpPort,
            'tlsPort'  => $config->ftpImplicitTlsPort,
        ]);
    }

    public function regeneratePassword(string $id): ResponseInterface
    {
        service('ftpAccountManager')->regeneratePassword($this->findAccount($id));

        return redirect()->to('admin/ftp/' . $id)
            ->with('notice', 'Neues Passwort erzeugt. Bitte am Kopierer eintragen.')
            ->with('reveal_password', $id);
    }

    /**
     * Returns the plain password on request (eye icon on the account page).
     */
    public function revealPassword(string $id): ResponseInterface
    {
        $account = $this->findAccount($id);

        return $this->response
            ->setHeader('Cache-Control', 'no-store')
            ->setJSON(['password' => FtpAccountModel::plainPassword($account)]);
    }

    public function toggle(string $id): ResponseInterface
    {
        $account = $this->findAccount($id);
        model(FtpAccountModel::class)->update($account['id'], ['active' => $account['active'] ? 0 : 1]);

        return redirect()->to('admin/ftp/' . $id)->with('notice', $account['active'] ? 'Konto gesperrt.' : 'Konto aktiviert.');
    }

    public function visibility(string $id): ResponseInterface
    {
        $account    = $this->findAccount($id);
        $visibility = $this->request->getPost('visibility') === 'public' ? 'public' : 'private';
        model(FtpAccountModel::class)->update($account['id'], ['visibility' => $visibility]);

        return redirect()->to('admin/ftp/' . $id)->with('notice', $visibility === 'public'
            ? 'Das Konto ist jetzt öffentlich: Alle Benutzer sehen die Scans.'
            : 'Das Konto ist jetzt privat: Nur die eingetragenen Personen und Gruppen sehen die Scans.');
    }

    public function delete(string $id): ResponseInterface
    {
        service('ftpAccountManager')->delete($this->findAccount($id));

        return redirect()->to('admin/ftp')->with('notice', 'Das FTP-Konto und seine Scans wurden gelöscht.');
    }

    public function addMember(string $id): ResponseInterface
    {
        $account = $this->findAccount($id);
        $type  = (string) $this->request->getPost('member_type');
        $ref   = strtolower(trim((string) $this->request->getPost('member_ref')));
        $label = trim((string) $this->request->getPost('label'));

        // Free text without a selection from the suggestions: infer the type.
        if ($type === '') {
            $type = preg_match(self::GUID, $ref) === 1 ? 'group' : 'user';
        }

        if ($type === 'user' && filter_var($ref, FILTER_VALIDATE_EMAIL) === false) {
            return redirect()->back()->with('error', 'Bitte eine Person oder Gruppe aus den Vorschlägen wählen oder den Anmeldenamen (z. B. name@firma.de) bzw. die Objekt-ID einer Gruppe eingeben.');
        }
        if ($type === 'group' && preg_match(self::GUID, $ref) !== 1) {
            return redirect()->back()->with('error', 'Bitte die Objekt-ID der Entra-Gruppe angeben.');
        }
        if (! in_array($type, ['user', 'group'], true)) {
            return redirect()->back();
        }

        $members = model(FtpAccountMemberModel::class);
        $exists  = $members->where(['ftp_account_id' => $account['id'], 'member_type' => $type, 'member_ref' => $ref])->countAllResults() > 0;
        if (! $exists) {
            $members->insert([
                'ftp_account_id' => $account['id'],
                'member_type'    => $type,
                'member_ref'     => $ref,
                'label'          => mb_substr($label !== '' ? $label : $ref, 0, 190),
            ]);
        }

        return redirect()->to('admin/ftp/' . $id)->with('notice', 'Berechtigung hinzugefügt.');
    }

    public function removeMember(string $id, string $memberId): ResponseInterface
    {
        $account = $this->findAccount($id);
        model(FtpAccountMemberModel::class)->where('ftp_account_id', $account['id'])->where('public_id', $memberId)->delete();

        return redirect()->to('admin/ftp/' . $id)->with('notice', 'Berechtigung entfernt.');
    }

    private function findAccount(string $id): array
    {
        $account = model(FtpAccountModel::class)->findForTenant((int) $this->tenant()['id'], $id);
        if ($account === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $account;
    }
}

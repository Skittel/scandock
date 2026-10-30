<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\FtpAccountMemberModel;
use App\Models\FtpAccountModel;
use App\Models\ScanModel;
use App\Models\UserModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Admin pages for FTP accounts and the people/groups that may see their scans.
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
        $account = service('ftpAccountManager')->create($this->tenant(), $label);

        return redirect()->to('admin/ftp/' . $account['id'])->with('notice', 'Das FTP-Konto wurde angelegt.');
    }

    public function show(int $id): string
    {
        $account = $this->findAccount($id);
        $config  = config('ScanKai');

        return $this->render('admin/ftp_show', [
            'account'  => $account,
            'password' => FtpAccountModel::plainPassword($account),
            'members'  => model(FtpAccountMemberModel::class)->forAccount($id),
            'users'    => model(UserModel::class)->where('tenant_id', $this->tenant()['id'])->orderBy('name')->findAll(),
            'host'     => $config->ftpHost,
            'port'     => $config->ftpPort,
            'tlsPort'  => $config->ftpImplicitTlsPort,
        ]);
    }

    public function regeneratePassword(int $id): ResponseInterface
    {
        service('ftpAccountManager')->regeneratePassword($this->findAccount($id));

        return redirect()->to('admin/ftp/' . $id)->with('notice', 'Neues Passwort erzeugt. Bitte am Kopierer eintragen.');
    }

    public function toggle(int $id): ResponseInterface
    {
        $account = $this->findAccount($id);
        model(FtpAccountModel::class)->update($id, ['active' => $account['active'] ? 0 : 1]);

        return redirect()->to('admin/ftp/' . $id)->with('notice', $account['active'] ? 'Konto gesperrt.' : 'Konto aktiviert.');
    }

    public function delete(int $id): ResponseInterface
    {
        service('ftpAccountManager')->delete($this->findAccount($id));

        return redirect()->to('admin/ftp')->with('notice', 'Das FTP-Konto und seine Scans wurden gelöscht.');
    }

    public function addMember(int $id): ResponseInterface
    {
        $this->findAccount($id);
        $type  = (string) $this->request->getPost('member_type');
        $ref   = strtolower(trim((string) $this->request->getPost('member_ref')));
        $label = trim((string) $this->request->getPost('label'));

        if ($type === 'user' && filter_var($ref, FILTER_VALIDATE_EMAIL) === false) {
            return redirect()->back()->with('error', 'Bitte den Anmeldenamen (UPN, z. B. name@firma.de) der Person angeben.');
        }
        if ($type === 'group' && preg_match(self::GUID, $ref) !== 1) {
            return redirect()->back()->with('error', 'Bitte die Objekt-ID der Entra-Gruppe angeben.');
        }
        if (! in_array($type, ['user', 'group'], true)) {
            return redirect()->back();
        }

        $members = model(FtpAccountMemberModel::class);
        $exists  = $members->where(['ftp_account_id' => $id, 'member_type' => $type, 'member_ref' => $ref])->countAllResults() > 0;
        if (! $exists) {
            $members->insert([
                'ftp_account_id' => $id,
                'member_type'    => $type,
                'member_ref'     => $ref,
                'label'          => mb_substr($label !== '' ? $label : $ref, 0, 190),
            ]);
        }

        return redirect()->to('admin/ftp/' . $id)->with('notice', 'Berechtigung hinzugefügt.');
    }

    public function removeMember(int $id, int $memberId): ResponseInterface
    {
        $this->findAccount($id);
        model(FtpAccountMemberModel::class)->where('ftp_account_id', $id)->delete($memberId);

        return redirect()->to('admin/ftp/' . $id)->with('notice', 'Berechtigung entfernt.');
    }

    private function findAccount(int $id): array
    {
        $account = model(FtpAccountModel::class)->findForTenant((int) $this->tenant()['id'], $id);
        if ($account === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $account;
    }
}

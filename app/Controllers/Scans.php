<?php

namespace App\Controllers;

use App\Models\FtpAccountModel;
use App\Models\ScanModel;
use App\Models\UserModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Lists, previews, downloads and deletes the scans a user may access.
 */
class Scans extends BaseController
{
    /**
     * Scan list grouped by FTP account (one collapsible section per account).
     */
    public function index(): string
    {
        $tenant     = $this->tenant();
        $user       = $this->currentUser();
        $accountIds = service('scanAccess')->visibleAccountIds($tenant, $user);
        $accounts   = $accountIds === [] ? [] : model(FtpAccountModel::class)->whereIn('id', $accountIds)->orderBy('label')->findAll();

        $sections = [];
        $scans    = model(ScanModel::class);
        foreach ($accounts as $account) {
            $group      = 'a' . $account['id'];
            $sections[] = [
                'account' => $account,
                'scans'   => $scans->where('tenant_id', $tenant['id'])->where('ftp_account_id', $account['id'])
                    ->orderBy('received_at', 'DESC')->paginate(24, $group),
                'total'   => $scans->pager->getTotal($group),
                'group'   => $group,
            ];
        }

        return $this->render('scans/index', [
            'sections'  => $sections,
            'pager'     => $scans->pager,
            'collapsed' => UserModel::collapsedAccounts($user),
            'expires'   => (int) $tenant['retention_days'],
        ]);
    }

    /**
     * Stores which account sections the user collapsed (sent by the page as JSON).
     */
    public function saveCollapsed(): ResponseInterface
    {
        $user    = $this->currentUser();
        $visible = service('scanAccess')->visibleAccountIds($this->tenant(), $user);
        $ids     = array_map('intval', (array) ($this->request->getJSON(true)['collapsed'] ?? []));
        $ids     = array_values(array_unique(array_intersect($ids, $visible)));

        model(UserModel::class)->update($user['id'], ['collapsed_accounts' => json_encode($ids)]);

        return $this->response->setJSON(['ok' => true]);
    }

    public function view(int $id): ResponseInterface
    {
        return $this->sendFile($this->findScan($id), 'inline');
    }

    public function download(int $id): ResponseInterface
    {
        return $this->sendFile($this->findScan($id), 'attachment');
    }

    public function thumbnail(int $id): ResponseInterface
    {
        $scan = $this->findScan($id);
        $path = service('scanStore')->thumbnailPath($scan);
        if (! is_file($path)) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->response->setHeader('Content-Type', 'image/png')
            ->setHeader('Cache-Control', 'private, max-age=86400')
            ->setBody(file_get_contents($path));
    }

    public function delete(int $id): ResponseInterface
    {
        service('scanStore')->delete($this->findScan($id));

        return redirect()->back()->with('notice', 'Der Scan wurde gelöscht.');
    }

    private function findScan(int $id): array
    {
        $scan = model(ScanModel::class)->find($id);
        if ($scan === null || ! service('scanAccess')->canAccessScan($this->tenant(), $this->currentUser(), $scan)) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $scan;
    }

    private function sendFile(array $scan, string $disposition): ResponseInterface
    {
        $path = service('scanStore')->filePath($scan);
        if (! is_file($path)) {
            throw PageNotFoundException::forPageNotFound();
        }
        $name = preg_replace('/[^\w.\- ]+/u', '_', $scan['original_name']);

        return $this->response
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', $disposition . '; filename="' . addcslashes($name, '"\\') . '"; filename*=UTF-8\'\'' . rawurlencode($scan['original_name']))
            ->setHeader('Content-Length', (string) filesize($path))
            ->setHeader('Cache-Control', 'private, no-store')
            ->setBody(file_get_contents($path));
    }
}

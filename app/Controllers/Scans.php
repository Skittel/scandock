<?php

namespace App\Controllers;

use App\Models\FtpAccountModel;
use App\Models\ScanModel;
use App\Models\ScanRejectionModel;
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
                    ->orderBy('received_at', 'DESC')->orderBy('id', 'DESC')->paginate(24, $group),
                'total'   => $scans->pager->getTotal($group),
                'group'   => $group,
            ];
        }

        return $this->render('scans/index', [
            'sections'  => $sections,
            'pending'   => service('scanStore')->pendingUploads($accountIds),
            'rejected'  => model(ScanRejectionModel::class)->recentByAccount($accountIds),
            'pager'     => $scans->pager,
            'collapsed' => UserModel::collapsedAccounts($user),
            'listViews' => UserModel::listAccounts($user),
            'expires'   => (int) $tenant['retention_days'],
        ]);
    }

    /**
     * Small fingerprint of the visible scans and pending uploads. The page
     * polls it and reloads the list when it changes.
     */
    public function status(): ResponseInterface
    {
        $tenant     = $this->tenant();
        $accountIds = service('scanAccess')->visibleAccountIds($tenant, $this->currentUser());
        $pending    = service('scanStore')->pendingUploads($accountIds);
        $latest     = $accountIds === [] ? null : model(ScanModel::class)
            ->select('COUNT(*) AS n, MAX(id) AS max_id')
            ->where('tenant_id', $tenant['id'])->whereIn('ftp_account_id', $accountIds)
            ->get()->getRowArray();

        $pendingKey = array_map(static fn ($files) => array_map(static fn ($f) => $f['name'] . ':' . $f['state'], $files), $pending);
        $rejected   = array_map(static fn ($rows) => array_column($rows, 'id'), model(ScanRejectionModel::class)->recentByAccount($accountIds));

        return $this->response->setHeader('Cache-Control', 'no-store')->setJSON([
            'fingerprint' => md5(json_encode([$accountIds, $latest, $pendingKey, $rejected])),
            'pending'     => array_sum(array_map('count', $pending)),
        ]);
    }

    /**
     * Stores tile or list view for one account section (JSON request).
     */
    public function saveView(): ResponseInterface
    {
        $user    = $this->currentUser();
        $data    = (array) $this->request->getJSON(true);
        $account = (int) ($data['account'] ?? 0);
        if (! in_array($account, service('scanAccess')->visibleAccountIds($this->tenant(), $user), true)) {
            return $this->response->setStatusCode(404)->setJSON(['ok' => false]);
        }

        $lists = array_values(array_diff(UserModel::listAccounts($user), [$account]));
        if (($data['view'] ?? '') === 'list') {
            $lists[] = $account;
        }
        model(UserModel::class)->update($user['id'], ['account_views' => json_encode($lists)]);

        return $this->response->setJSON(['ok' => true]);
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
        $scan = $this->findScan($id);

        return $this->sendFile($scan, service('scanStore')->viewPath($scan), 'inline');
    }

    public function download(int $id): ResponseInterface
    {
        $scan = $this->findScan($id);

        return $this->sendFile($scan, service('scanStore')->filePath($scan), 'attachment');
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

    /**
     * Deletes several scans at once (checkboxes in the list view). Scans the
     * user may not access are skipped.
     */
    public function deleteSelected(): ResponseInterface
    {
        $ids = array_unique(array_map('intval', (array) $this->request->getPost('ids')));
        if ($ids === []) {
            return redirect()->back()->with('warning', 'Es wurden keine Scans ausgewählt.');
        }

        $tenant = $this->tenant();
        $user   = $this->currentUser();
        $store  = service('scanStore');
        $access = service('scanAccess');
        $count  = 0;
        foreach (model(ScanModel::class)->whereIn('id', array_slice($ids, 0, 500))->findAll() as $scan) {
            if ($access->canAccessScan($tenant, $user, $scan)) {
                $store->delete($scan);
                $count++;
            }
        }

        return redirect()->back()->with('notice', $count === 1 ? '1 Scan wurde gelöscht.' : $count . ' Scans wurden gelöscht.');
    }

    private function findScan(int $id): array
    {
        $scan = model(ScanModel::class)->find($id);
        if ($scan === null || ! service('scanAccess')->canAccessScan($this->tenant(), $this->currentUser(), $scan)) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $scan;
    }

    private function sendFile(array $scan, string $path, string $disposition): ResponseInterface
    {
        if (! is_file($path)) {
            throw PageNotFoundException::forPageNotFound();
        }
        $name = preg_replace('/[^\w.\- ]+/u', '_', $scan['original_name']);
        if (str_ends_with($path, '.view.pdf')) {
            $name = pathinfo($name, PATHINFO_FILENAME) . '.pdf';
        }

        return $this->response
            ->setHeader('Content-Type', \App\Libraries\ScanStore::mimeType($path))
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setHeader('Content-Disposition', $disposition . '; filename="' . addcslashes($name, '"\\') . '"; filename*=UTF-8\'\'' . rawurlencode($scan['original_name']))
            ->setHeader('Content-Length', (string) filesize($path))
            ->setHeader('Cache-Control', 'private, no-store')
            ->setBody(file_get_contents($path));
    }
}

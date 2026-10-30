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
            $group      = 'a' . $account['public_id'];
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
        $account = $this->publicAccountMap(service('scanAccess')->visibleAccountIds($this->tenant(), $user))[(string) ($data['account'] ?? '')] ?? null;
        if ($account === null) {
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
        $map     = $this->publicAccountMap($visible);
        $ids     = array_values(array_unique(array_filter(array_map(
            static fn ($publicId) => $map[(string) $publicId] ?? null,
            (array) ($this->request->getJSON(true)['collapsed'] ?? [])
        ))));

        model(UserModel::class)->update($user['id'], ['collapsed_accounts' => json_encode($ids)]);

        return $this->response->setJSON(['ok' => true]);
    }

    public function view(string $id): ResponseInterface
    {
        $scan = $this->findScan($id);

        return $this->sendFile($scan, service('scanStore')->viewFile($scan), true);
    }

    public function download(string $id): ResponseInterface
    {
        $scan = $this->findScan($id);

        return $this->sendFile($scan, service('scanStore')->originalFile($scan), false);
    }

    public function thumbnail(string $id): ResponseInterface
    {
        $data = service('scanStore')->thumbnailData($this->findScan($id));
        if ($data === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->response->setHeader('Content-Type', 'image/png')
            ->setHeader('Cache-Control', 'private, max-age=86400')
            ->setBody($data);
    }

    public function delete(string $id): ResponseInterface
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
        $ids = array_unique(array_filter(array_map('strval', (array) $this->request->getPost('ids'))));
        if ($ids === []) {
            return redirect()->back()->with('warning', 'Es wurden keine Scans ausgewählt.');
        }

        $tenant = $this->tenant();
        $user   = $this->currentUser();
        $store  = service('scanStore');
        $access = service('scanAccess');
        $count  = 0;
        foreach (model(ScanModel::class)->whereIn('public_id', array_slice($ids, 0, 500))->findAll() as $scan) {
            if ($access->canAccessScan($tenant, $user, $scan)) {
                $store->delete($scan);
                $count++;
            }
        }

        return redirect()->back()->with('notice', $count === 1 ? '1 Scan wurde gelöscht.' : $count . ' Scans wurden gelöscht.');
    }

    /**
     * Maps public IDs of the given accounts to their database IDs.
     *
     * @param list<int> $accountIds
     *
     * @return array<string, int>
     */
    private function publicAccountMap(array $accountIds): array
    {
        if ($accountIds === []) {
            return [];
        }
        $rows = model(FtpAccountModel::class)->select('id, public_id')->whereIn('id', $accountIds)->findAll();

        return array_map('intval', array_column($rows, 'id', 'public_id'));
    }

    private function findScan(string $publicId): array
    {
        $scan = model(ScanModel::class)->where('public_id', $publicId)->first();
        if ($scan === null || ! service('scanAccess')->canAccessScan($this->tenant(), $this->currentUser(), $scan)) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $scan;
    }

    /**
     * Streams a stored file. Encrypted files are decrypted into RAM first and
     * the copy is removed once the response has been sent.
     *
     * @param array{path: string, mime: string, name: string} $file
     */
    private function sendFile(array $scan, array $file, bool $inline): ResponseInterface
    {
        if (! is_file($file['path'])) {
            throw PageNotFoundException::forPageNotFound();
        }
        $plain = service('scanStore')->plaintext($scan, $file['path']);
        if ($plain['cleanup'] !== null) {
            register_shutdown_function([\App\Libraries\FileCrypto::class, 'removeDirectory'], $plain['cleanup']);
        }

        $name     = preg_replace('/[\x00-\x1F\/\\\\"]+/u', '_', $file['name']);
        $response = $this->response->download($plain['path'], null)
            ->setFileName($name)
            ->setContentType($file['mime'], '')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setHeader('Cache-Control', 'private, no-store');

        return $inline ? $response->inline() : $response;
    }
}

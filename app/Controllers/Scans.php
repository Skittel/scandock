<?php

namespace App\Controllers;

use App\Models\FtpAccountModel;
use App\Models\ScanModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Lists, previews, downloads and deletes the scans a user may access.
 */
class Scans extends BaseController
{
    public function index(): string
    {
        $tenant     = $this->tenant();
        $accountIds = service('scanAccess')->visibleAccountIds($tenant, $this->currentUser());
        $filter     = (int) $this->request->getGet('account');

        $scans = [];
        if ($accountIds !== []) {
            $query = model(ScanModel::class)->where('tenant_id', $tenant['id'])
                ->whereIn('ftp_account_id', $filter > 0 && in_array($filter, $accountIds, true) ? [$filter] : $accountIds)
                ->orderBy('received_at', 'DESC');
            $scans = $query->paginate(48);
        }

        $accounts = $accountIds === [] ? [] : model(FtpAccountModel::class)->whereIn('id', $accountIds)->orderBy('label')->findAll();

        return $this->render('scans/index', [
            'scans'    => $scans,
            'pager'    => $accountIds === [] ? null : model(ScanModel::class)->pager,
            'accounts' => array_column($accounts, null, 'id'),
            'filter'   => $filter,
            'expires'  => (int) $tenant['retention_days'],
        ]);
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

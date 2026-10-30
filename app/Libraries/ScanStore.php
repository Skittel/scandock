<?php

namespace App\Libraries;

use App\Models\FtpAccountModel;
use App\Models\ScanModel;
use App\Models\TenantModel;
use Config\ScanKai;

/**
 * Moves uploaded files into storage, renders thumbnails and deletes scans.
 */
class ScanStore
{
    public function __construct(private readonly ScanKai $config)
    {
    }

    public function filePath(array $scan): string
    {
        return $this->tenantDirectory((int) $scan['tenant_id']) . '/' . $scan['stored_name'] . '.pdf';
    }

    public function thumbnailPath(array $scan): string
    {
        return $this->tenantDirectory((int) $scan['tenant_id']) . '/' . $scan['stored_name'] . '.png';
    }

    /**
     * Imports all completed uploads of all FTP accounts.
     *
     * @return int number of imported scans
     */
    public function ingestAll(): int
    {
        $count    = 0;
        $manager  = service('ftpAccountManager');

        foreach (model(FtpAccountModel::class)->findAll() as $account) {
            $dir = $manager->incomingDirectory((int) $account['id']);
            if (! is_dir($dir)) {
                continue;
            }
            foreach (scandir($dir) ?: [] as $name) {
                $path = $dir . '/' . $name;
                // ProFTPD writes uploads in progress as hidden ".in.*" files.
                if ($name[0] === '.' || ! is_file($path) || is_link($path)) {
                    continue;
                }
                if (time() - filemtime($path) < $this->config->ingestMinAge) {
                    continue;
                }
                try {
                    if ($this->ingestFile($account, $path, $name)) {
                        $count++;
                    }
                } catch (\Throwable $e) {
                    log_message('error', 'Ingest of {file} failed: {msg}', ['file' => $path, 'msg' => $e->getMessage()]);
                }
            }
        }

        return $count;
    }

    /**
     * Deletes scans that are older than the retention period of their tenant.
     *
     * @return int number of deleted scans
     */
    public function purgeExpired(): int
    {
        $count = 0;
        foreach (model(TenantModel::class)->findAll() as $tenant) {
            $cutoff = date('Y-m-d H:i:s', strtotime('-' . max(1, (int) $tenant['retention_days']) . ' days'));
            $scans  = model(ScanModel::class)->where('tenant_id', $tenant['id'])->where('received_at <', $cutoff)->findAll();
            foreach ($scans as $scan) {
                $this->delete($scan);
                $count++;
            }
        }

        return $count;
    }

    public function delete(array $scan): void
    {
        @unlink($this->filePath($scan));
        @unlink($this->thumbnailPath($scan));
        model(ScanModel::class)->delete($scan['id']);
    }

    private function ingestFile(array $account, string $path, string $name): bool
    {
        $handle = fopen($path, 'rb');
        $magic  = $handle === false ? '' : fread($handle, 5);
        if ($handle !== false) {
            fclose($handle);
        }
        if ($magic !== '%PDF-') {
            log_message('warning', 'Discarding non-PDF upload {file} of FTP account {id}', ['file' => $name, 'id' => $account['id']]);
            @unlink($path);

            return false;
        }

        $storedName = self::uuid();
        $scan       = [
            'tenant_id'      => $account['tenant_id'],
            'ftp_account_id' => $account['id'],
            'original_name'  => mb_substr($name, 0, 255),
            'stored_name'    => $storedName,
            'size'           => filesize($path),
            'sha256'         => hash_file('sha256', $path),
            'has_thumbnail'  => 0,
            'received_at'    => date('Y-m-d H:i:s', filemtime($path)),
        ];

        $dir = $this->tenantDirectory((int) $account['tenant_id']);
        if (! is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        // Copy first and remove the upload only after the record exists, so
        // a failure never loses a scan. Copying also makes www-data the owner.
        $target = $this->filePath($scan);
        $umask  = umask(0027);
        $copied = copy($path, $target);
        umask($umask);
        if (! $copied) {
            log_message('error', 'Could not copy upload {file}', ['file' => $path]);

            return false;
        }

        $scan['has_thumbnail'] = $this->renderThumbnail($target, $this->thumbnailPath($scan)) ? 1 : 0;
        model(ScanModel::class)->insert($scan);
        unlink($path);

        return true;
    }

    /**
     * Renders the first page as PNG using pdftoppm (poppler-utils).
     */
    private function renderThumbnail(string $pdf, string $png): bool
    {
        $base = substr($png, 0, -4);
        $cmd  = sprintf(
            'timeout 30 pdftoppm -png -f 1 -l 1 -scale-to 480 -singlefile %s %s 2>/dev/null',
            escapeshellarg($pdf),
            escapeshellarg($base)
        );
        exec($cmd, $output, $exitCode);

        return $exitCode === 0 && is_file($png);
    }

    private function tenantDirectory(int $tenantId): string
    {
        return rtrim($this->config->storagePath, '/') . '/' . $tenantId;
    }

    private static function uuid(): string
    {
        $bytes    = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}

<?php

namespace App\Libraries;

use App\Models\FtpAccountModel;
use App\Models\ScanModel;
use App\Models\ScanRejectionModel;
use App\Models\TenantModel;
use Config\ScanKai;

/**
 * Checks uploaded files, moves them into storage, renders thumbnails and
 * deletes scans. Rejected files are deleted and logged with the reason.
 */
class ScanStore
{
    public function __construct(private readonly ScanKai $config)
    {
    }

    public function filePath(array $scan): string
    {
        return $this->tenantDirectory((int) $scan['tenant_id']) . '/' . $scan['stored_name'] . '.' . ($scan['file_ext'] ?? 'pdf');
    }

    /**
     * File to show in the browser: the original, or a PDF rendition for TIFF
     * (most browsers cannot display TIFF).
     */
    public function viewPath(array $scan): string
    {
        $view = $this->tenantDirectory((int) $scan['tenant_id']) . '/' . $scan['stored_name'] . '.view.pdf';

        return ($scan['file_ext'] ?? 'pdf') === 'tif' && is_file($view) ? $view : $this->filePath($scan);
    }

    public static function mimeType(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'jpg'   => 'image/jpeg',
            'png'   => 'image/png',
            'tif'   => 'image/tiff',
            default => 'application/pdf',
        };
    }

    public function thumbnailPath(array $scan): string
    {
        return $this->tenantDirectory((int) $scan['tenant_id']) . '/' . $scan['stored_name'] . '.thumb.png';
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
     * Files that are still being uploaded or waiting for the import, per FTP
     * account. Used to show placeholders right after an upload.
     *
     * @param list<int> $accountIds
     *
     * @return array<int, list<array{name: string, state: string, size: int}>>
     */
    public function pendingUploads(array $accountIds): array
    {
        $manager = service('ftpAccountManager');
        $pending = [];

        foreach ($accountIds as $accountId) {
            $dir = $manager->incomingDirectory((int) $accountId);
            foreach (is_dir($dir) ? (scandir($dir) ?: []) : [] as $name) {
                $path = $dir . '/' . $name;
                if ($name === '.' || $name === '..' || ! is_file($path)) {
                    continue;
                }
                // HiddenStores: ".in.<name>." while the transfer is running.
                if (preg_match('/^\.in\.(.+)\.$/s', $name, $m) === 1) {
                    $pending[$accountId][] = ['name' => $m[1], 'state' => 'uploading', 'size' => (int) filesize($path)];
                } elseif ($name[0] !== '.') {
                    $pending[$accountId][] = ['name' => $name, 'state' => 'processing', 'size' => (int) filesize($path)];
                }
            }
        }

        return $pending;
    }

    /**
     * Deletes scans that are older than the retention period of their tenant.
     *
     * @return int number of deleted scans
     */
    public function purgeExpired(): int
    {
        $count = 0;
        model(ScanRejectionModel::class)->where('created_at <', date('Y-m-d H:i:s', strtotime('-30 days')))->delete();

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
        @unlink($this->tenantDirectory((int) $scan['tenant_id']) . '/' . $scan['stored_name'] . '.view.pdf');
        model(ScanModel::class)->delete($scan['id']);
    }

    private function ingestFile(array $account, string $path, string $name): bool
    {
        $type   = FileInspector::typeForName($name);
        $reason = $type === null
            ? sprintf('Dateityp „.%s“ ist nicht erlaubt (erlaubt: PDF, JPG, PNG, TIFF).', mb_substr(pathinfo($name, PATHINFO_EXTENSION) ?: '?', 0, 10))
            : (new FileInspector())->inspect($path, $type);

        if ($reason !== null) {
            $this->reject($account, $path, $name, $reason);

            return false;
        }

        $storedName = self::uuid();
        $scan       = [
            'tenant_id'      => $account['tenant_id'],
            'ftp_account_id' => $account['id'],
            'original_name'  => mb_substr($name, 0, 255),
            'stored_name'    => $storedName,
            'file_ext'       => $type,
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
        if ($copied && $type === 'tif') {
            $this->renderTiffView($target, substr($target, 0, -4) . '.view.pdf');
        }
        umask($umask);
        if (! $copied) {
            log_message('error', 'Could not copy upload {file}', ['file' => $path]);

            return false;
        }

        $scan['has_thumbnail'] = $this->renderThumbnail($target, $type, $this->thumbnailPath($scan)) ? 1 : 0;
        model(ScanModel::class)->insert($scan);
        unlink($path);

        return true;
    }

    /**
     * Deletes a rejected upload and logs why, so users see it on the scan page.
     */
    private function reject(array $account, string $path, string $name, string $reason): void
    {
        model(ScanRejectionModel::class)->insert([
            'tenant_id'      => $account['tenant_id'],
            'ftp_account_id' => $account['id'],
            'original_name'  => mb_substr($name, 0, 255),
            'size'           => (int) @filesize($path),
            'reason'         => $reason,
        ]);
        log_message('warning', 'Rejected upload {file} of FTP account {id}: {reason}', ['file' => $name, 'id' => $account['id'], 'reason' => $reason]);
        @unlink($path);
    }

    /**
     * Renders the first page as PNG: pdftoppm for PDFs, ImageMagick for images
     * (with the input format forced, so no other ImageMagick coder is used).
     */
    private function renderThumbnail(string $file, string $type, string $png): bool
    {
        if ($type === 'pdf') {
            $cmd = sprintf(
                'timeout 30 pdftoppm -png -f 1 -l 1 -scale-to 480 -singlefile %s %s 2>/dev/null',
                escapeshellarg($file),
                escapeshellarg(substr($png, 0, -4))
            );
        } else {
            $format = ['jpg' => 'jpeg', 'png' => 'png', 'tif' => 'tiff'][$type];
            $cmd    = sprintf(
                'timeout 30 convert -limit memory 256MiB -limit disk 1GiB %s -auto-orient -thumbnail 480x480 png:%s 2>/dev/null',
                escapeshellarg($format . ':' . $file . '[0]'),
                escapeshellarg($png)
            );
        }
        exec($cmd, $output, $exitCode);

        return $exitCode === 0 && is_file($png);
    }

    /**
     * Creates a PDF rendition of a TIFF for viewing in the browser. tiff2pdf
     * keeps the original compression (e.g. CCITT G4); ImageMagick is the
     * fallback for TIFFs it cannot handle (e.g. 16 bits per sample).
     */
    private function renderTiffView(string $tiff, string $pdf): void
    {
        exec(sprintf('timeout 60 tiff2pdf -o %s %s >/dev/null 2>&1', escapeshellarg($pdf), escapeshellarg($tiff)), $out, $code);
        if ($code !== 0 || ! is_file($pdf) || filesize($pdf) < 100) {
            exec(sprintf(
                'timeout 120 convert -limit memory 256MiB -limit disk 1GiB %s -depth 8 -compress zip pdf:%s >/dev/null 2>&1',
                escapeshellarg('tiff:' . $tiff),
                escapeshellarg($pdf)
            ), $out, $code);
        }
        if ($code !== 0) {
            @unlink($pdf);
            log_message('warning', 'Could not create PDF view of {file}', ['file' => $tiff]);
        }
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

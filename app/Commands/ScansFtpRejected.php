<?php

namespace App\Commands;

use App\Libraries\ScanFileName;
use App\Models\FtpAccountModel;
use App\Models\ScanRejectionModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Records an upload that the FTP server aborted because it exceeded the size
 * limit, so it is reported like any other rejected file (bar on the scan
 * page, journal). Called by /usr/local/sbin/scandock-ftplog.
 */
class ScansFtpRejected extends BaseCommand
{
    protected $group       = 'ScanDock';
    protected $name        = 'scans:ftp-rejected';
    protected $description = 'Records an upload aborted by the FTP server (used by scandock-ftplog).';
    protected $usage       = 'scans:ftp-rejected <ftp user> <bytes> <file name>';

    public function run(array $params)
    {
        [$username, $bytes, $name] = array_pad($params, 3, '');
        $account = model(FtpAccountModel::class)->where('username', (string) $username)->first();
        if ($account === null || $name === '') {
            CLI::error('Unknown FTP account or missing file name.');

            return EXIT_USER_INPUT;
        }

        $display = ScanFileName::forDisplay(basename((string) $name));
        $limit   = intdiv(config('ScanDock')->maxUploadBytes, 1048576);
        $reason  = sprintf('Die Datei ist größer als %d MB, der FTP-Server hat die Übertragung abgebrochen.', $limit);

        model(ScanRejectionModel::class)->insert([
            'tenant_id'      => $account['tenant_id'],
            'ftp_account_id' => $account['id'],
            'original_name'  => $display,
            'size'           => (int) $bytes,
            'reason'         => $reason,
        ]);
        service('audit')->record((int) $account['tenant_id'], 'scan.rejected', [
            'file'    => $display,
            'account' => $account['label'],
            'reason'  => $reason,
        ], 'System (FTP)');

        return EXIT_SUCCESS;
    }
}

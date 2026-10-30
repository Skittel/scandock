<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Deletes scans past their tenant's retention period. Run daily.
 */
class ScansPurge extends BaseCommand
{
    protected $group       = 'ScanDock';
    protected $name        = 'scans:purge';
    protected $description = 'Deletes scans that are older than the retention period.';

    public function run(array $params)
    {
        $count = service('scanStore')->purgeExpired();
        CLI::write('Deleted ' . $count . ' expired scan(s).');

        // Application logs (one file per day) are kept for 90 days.
        $logs = 0;
        foreach (glob(WRITEPATH . 'logs/log-*.log') ?: [] as $file) {
            if (filemtime($file) < strtotime('-90 days') && @unlink($file)) {
                $logs++;
            }
        }
        if ($logs > 0) {
            CLI::write('Deleted ' . $logs . ' old log file(s).');
        }

        return EXIT_SUCCESS;
    }
}

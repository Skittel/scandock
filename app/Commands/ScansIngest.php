<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Imports completed FTP uploads. Triggered by the upload watcher right after
 * an upload and every 20 seconds by a systemd timer as a fallback.
 */
class ScansIngest extends BaseCommand
{
    protected $group       = 'ScanDock';
    protected $name        = 'scans:ingest';
    protected $description = 'Imports completed FTP uploads into storage.';

    public function run(array $params)
    {
        // Only one import at a time; a second run waits and then picks up
        // whatever is left.
        $lock = fopen(WRITEPATH . 'ingest.lock', 'c');
        if ($lock === false || ! flock($lock, LOCK_EX)) {
            CLI::error('Could not acquire the ingest lock.');

            return EXIT_ERROR;
        }

        try {
            $count = service('scanStore')->ingestAll();
            if ($count > 0) {
                CLI::write('Imported ' . $count . ' scan(s).');
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        return EXIT_SUCCESS;
    }
}

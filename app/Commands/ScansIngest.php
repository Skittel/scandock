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
        // ScanStore::ingestAll() serialises concurrent imports itself.
        $count = service('scanStore')->ingestAll();
        if ($count > 0) {
            CLI::write('Imported ' . $count . ' scan(s).');
        }

        return EXIT_SUCCESS;
    }
}

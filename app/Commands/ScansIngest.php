<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Imports completed FTP uploads. Run every minute by a systemd timer.
 */
class ScansIngest extends BaseCommand
{
    protected $group       = 'ScanKai';
    protected $name        = 'scans:ingest';
    protected $description = 'Imports completed FTP uploads into storage.';

    public function run(array $params)
    {
        $count = service('scanStore')->ingestAll();
        if ($count > 0) {
            CLI::write('Imported ' . $count . ' scan(s).');
        }

        return EXIT_SUCCESS;
    }
}

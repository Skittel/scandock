<?php

namespace App\Commands;

use App\Models\ScanModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Encrypts scans that were stored before encryption at rest was introduced.
 */
class ScansEncrypt extends BaseCommand
{
    protected $group       = 'ScanKai';
    protected $name        = 'scans:encrypt';
    protected $description = 'Encrypts all scans that are still stored unencrypted.';

    public function run(array $params)
    {
        $store = service('scanStore');
        $done  = 0;
        $fail  = 0;
        foreach (model(ScanModel::class)->where('encrypted', 0)->findAll() as $scan) {
            if (! is_file($store->filePath($scan))) {
                CLI::write('Missing file for scan ' . $scan['public_id'] . ', skipped.', 'yellow');
                $fail++;

                continue;
            }
            $store->encryptExisting($scan) ? $done++ : $fail++;
        }
        CLI::write(sprintf('Encrypted %d scan(s), %d failed or skipped.', $done, $fail), $fail > 0 ? 'yellow' : 'green');

        return $fail > 0 ? EXIT_ERROR : EXIT_SUCCESS;
    }
}

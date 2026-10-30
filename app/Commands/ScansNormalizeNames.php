<?php

namespace App\Commands;

use App\Libraries\ScanFileName;
use App\Models\FtpAccountModel;
use App\Models\ScanModel;
use App\Models\TenantModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Applies the file name rules (ScanFileName) to scans stored before they
 * existed. Each rename is recorded in the tenant's journal.
 */
class ScansNormalizeNames extends BaseCommand
{
    protected $group       = 'ScanDock';
    protected $name        = 'scans:normalize-names';
    protected $description = 'Renames existing scans according to the file name rules.';
    protected $usage       = 'scans:normalize-names --tenant <slug> [--dry-run]';
    protected $options     = [
        '--tenant'  => 'Only this tenant (required)',
        '--dry-run' => 'Only show what would be renamed',
    ];

    public function run(array $params)
    {
        $tenant = model(TenantModel::class)->findBySlug((string) CLI::getOption('tenant'));
        if ($tenant === null) {
            CLI::error('Unknown tenant. Usage: ' . $this->usage);

            return EXIT_USER_INPUT;
        }
        $dryRun = CLI::getOption('dry-run') !== null;
        $scans  = model(ScanModel::class)->where('tenant_id', $tenant['id'])->where('ftp_account_id IS NOT NULL')->orderBy('id')->findAll();
        $count  = 0;

        foreach ($scans as $scan) {
            $new = ScanFileName::forScan($scan['original_name'], (int) $scan['ftp_account_id']);
            if ($new === $scan['original_name']) {
                continue;
            }
            CLI::write(sprintf('%s  →  %s', $scan['original_name'], $new));
            if (! $dryRun) {
                model(ScanModel::class)->update($scan['id'], ['original_name' => $new]);
                service('audit')->record((int) $tenant['id'], 'scan.renamed', [
                    'old'     => $scan['original_name'],
                    'new'     => $new,
                    'account' => model(FtpAccountModel::class)->find($scan['ftp_account_id'])['label'] ?? '–',
                ], 'Betreiber (CLI)');
            }
            $count++;
        }
        CLI::write(($dryRun ? 'Would rename ' : 'Renamed ') . $count . ' of ' . count($scans) . ' scan(s).', 'green');

        return EXIT_SUCCESS;
    }
}

<?php

namespace App\Commands;

use App\Models\FtpAccountModel;
use App\Models\ScanModel;
use App\Models\TenantModel;
use App\Models\UserModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Lists all tenants with some key figures.
 */
class TenantList extends BaseCommand
{
    protected $group       = 'ScanDock';
    protected $name        = 'tenant:list';
    protected $description = 'Lists all tenants.';

    public function run(array $params)
    {
        $rows = [];
        foreach (model(TenantModel::class)->orderBy('slug')->findAll() as $t) {
            $rows[] = [
                $t['slug'], $t['name'], $t['status'],
                model(UserModel::class)->where('tenant_id', $t['id'])->countAllResults(),
                model(FtpAccountModel::class)->where('tenant_id', $t['id'])->countAllResults(),
                model(ScanModel::class)->where('tenant_id', $t['id'])->countAllResults(),
                $t['retention_days'],
                self::secretInfo($t),
            ];
        }
        CLI::table($rows, ['Slug', 'Name', 'Status', 'Users', 'FTP', 'Scans', 'Retention', 'Secret expires']);

        return EXIT_SUCCESS;
    }

    private static function secretInfo(array $tenant): string
    {
        $days = TenantModel::secretDaysLeft($tenant);
        if ($days === null) {
            return '-';
        }
        $label = $tenant['entra_secret_expires_at'] . ' (' . $days . 'd)';
        if ($days < 0) {
            return CLI::color($label . ' EXPIRED', 'red');
        }

        return $days <= config('ScanDock')->secretWarningDays ? CLI::color($label, 'yellow') : $label;
    }
}

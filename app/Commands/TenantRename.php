<?php

namespace App\Commands;

use App\Models\TenantModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Changes the display name of a tenant.
 */
class TenantRename extends BaseCommand
{
    protected $group       = 'ScanKai';
    protected $name        = 'tenant:rename';
    protected $description = 'Changes the display name (company name) of a tenant.';
    protected $usage       = 'tenant:rename <slug> "Display name"';

    public function run(array $params)
    {
        $tenants = model(TenantModel::class);
        $tenant  = $tenants->findBySlug(strtolower($params[0] ?? ''));
        $name    = trim($params[1] ?? '');
        if ($tenant === null || $name === '' || mb_strlen($name) > 190) {
            CLI::error('Usage: ' . $this->usage);

            return EXIT_USER_INPUT;
        }
        $tenants->update($tenant['id'], ['name' => $name]);
        CLI::write('Display name of ' . $tenant['slug'] . ' is now "' . $name . '".', 'green');

        return EXIT_SUCCESS;
    }
}

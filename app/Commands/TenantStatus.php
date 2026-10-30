<?php

namespace App\Commands;

use App\Models\TenantModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Disables or re-enables a tenant (web sign-in and FTP).
 */
class TenantStatus extends BaseCommand
{
    protected $group       = 'ScanDock';
    protected $name        = 'tenant:status';
    protected $description = 'Disables or enables a tenant.';
    protected $usage       = 'tenant:status <slug> disable|enable';

    public function run(array $params)
    {
        $tenants = model(TenantModel::class);
        $tenant  = $tenants->findBySlug(strtolower($params[0] ?? ''));
        $action  = $params[1] ?? '';
        if ($tenant === null || ! in_array($action, ['disable', 'enable'], true)) {
            CLI::error('Usage: ' . $this->usage);

            return EXIT_USER_INPUT;
        }
        if ($action === 'enable' && empty($tenant['entra_client_secret'])) {
            CLI::error('Tenant has not finished setup.');

            return EXIT_USER_INPUT;
        }
        $tenants->update($tenant['id'], ['status' => $action === 'disable' ? 'disabled' : 'active']);
        CLI::write('Tenant ' . $tenant['slug'] . ' is now ' . ($action === 'disable' ? 'disabled' : 'active') . '.', 'green');

        return EXIT_SUCCESS;
    }
}

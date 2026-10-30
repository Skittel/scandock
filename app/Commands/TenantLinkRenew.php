<?php

namespace App\Commands;

use App\Models\TenantModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Creates a new start link and invalidates the previous one. For an active
 * tenant the link allows renewing the Entra connection (e.g. expired client
 * secret); sign-in and FTP keep working meanwhile.
 */
class TenantLinkRenew extends BaseCommand
{
    protected $group       = 'ScanDock';
    protected $name        = 'tenant:link-renew';
    protected $description = 'Creates a new start link for a tenant (invalidates the previous one).';
    protected $usage       = 'tenant:link-renew <slug>';

    public function run(array $params)
    {
        $tenants = model(TenantModel::class);
        $tenant  = $tenants->findBySlug(strtolower($params[0] ?? ''));
        if ($tenant === null) {
            CLI::error('Unknown tenant.');

            return EXIT_USER_INPUT;
        }
        if ($tenant['status'] === 'disabled') {
            CLI::error('Tenant is disabled. Enable it first with tenant:status.');

            return EXIT_USER_INPUT;
        }

        $token = $tenants->issueSetupToken((int) $tenant['id']);
        service('audit')->record((int) $tenant['id'], 'tenant.link', [], 'Betreiber (CLI)');
        CLI::write('Start link: ' . CLI::color(TenantLink::url($tenant['slug'], $token), 'yellow'));
        if ($tenant['status'] === 'active') {
            CLI::write('Tenant stays active; the link becomes invalid after the next successful sign-in through it.');
        }

        return EXIT_SUCCESS;
    }
}

<?php

namespace App\Commands;

use App\Models\TenantModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Shows the current start link of a tenant.
 */
class TenantLink extends BaseCommand
{
    protected $group       = 'ScanDock';
    protected $name        = 'tenant:link';
    protected $description = 'Shows the current start link of a tenant.';
    protected $usage       = 'tenant:link <slug>';

    public function run(array $params)
    {
        $tenants = model(TenantModel::class);
        $tenant  = $tenants->findBySlug(strtolower($params[0] ?? ''));
        if ($tenant === null) {
            CLI::error('Unknown tenant.');

            return EXIT_USER_INPUT;
        }

        $token = $tenants->currentSetupToken($tenant);
        if ($token === null) {
            CLI::write('No valid start link (setup finished or link already used).');
            CLI::write('Create a new one with: scandock tenant:link-renew ' . $tenant['slug']);

            return EXIT_SUCCESS;
        }

        CLI::write('Start link: ' . CLI::color(self::url($tenant['slug'], $token), 'yellow'));

        return EXIT_SUCCESS;
    }

    public static function url(string $slug, string $token): string
    {
        return 'https://' . service('tenancy')->hostFor($slug) . '/setup/' . $token;
    }
}

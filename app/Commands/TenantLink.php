<?php

namespace App\Commands;

use App\Models\TenantModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Issues a new start link. Puts an active tenant back into setup so the
 * Entra connection can be changed (users stay, sign-in is blocked meanwhile).
 */
class TenantLink extends BaseCommand
{
    protected $group       = 'ScanKai';
    protected $name        = 'tenant:link';
    protected $description = 'Issues a new start link for a tenant (invalidates the previous one).';
    protected $usage       = 'tenant:link <slug> [--reset]';
    protected $options     = ['--reset' => 'Also allow this for an active tenant (puts it back into setup)'];

    public function run(array $params)
    {
        $tenants = model(TenantModel::class);
        $tenant  = $tenants->findBySlug(strtolower($params[0] ?? ''));
        if ($tenant === null) {
            CLI::error('Unknown tenant.');

            return EXIT_USER_INPUT;
        }
        if ($tenant['status'] !== 'setup') {
            if (! CLI::getOption('reset')) {
                CLI::error('Tenant is ' . $tenant['status'] . '. Use --reset to put it back into setup (sign-in and FTP stop until setup is finished).');

                return EXIT_USER_INPUT;
            }
            $tenants->update($tenant['id'], ['status' => 'setup']);
        }

        $token = $tenants->issueSetupToken((int) $tenant['id']);
        CLI::write('Start link: ' . CLI::color('https://' . service('tenancy')->hostFor($tenant['slug']) . '/setup/' . $token, 'yellow'));

        return EXIT_SUCCESS;
    }
}

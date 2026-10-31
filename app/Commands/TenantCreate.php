<?php

namespace App\Commands;

use App\Libraries\Tenancy;
use App\Models\TenantModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\ScanDock;

/**
 * Creates a tenant with vhost and certificate and prints the start link.
 */
class TenantCreate extends BaseCommand
{
    protected $group       = 'ScanDock';
    protected $name        = 'tenant:create';
    protected $description = 'Creates a tenant (vhost, Let\'s Encrypt certificate) and prints its start link.';
    protected $usage       = 'tenant:create <slug> [--name "Display name"]';
    protected $arguments   = ['slug' => 'Subdomain, e.g. "firma" for firma.scandock.cloud'];
    protected $options     = ['--name' => 'Display name of the customer (defaults to the slug)'];

    public function run(array $params)
    {
        $slug     = strtolower(trim($params[0] ?? ''));
        $tenancy  = service('tenancy');
        $tenants  = model(TenantModel::class);

        if (! Tenancy::isValidSlug($slug) || $tenancy->isReservedSlug($slug)) {
            CLI::error('Invalid or reserved slug: "' . $slug . '". Use a-z, 0-9 and "-".');

            return EXIT_USER_INPUT;
        }
        if ($tenants->findBySlug($slug) !== null) {
            CLI::error('Tenant "' . $slug . '" already exists. Show its start link with tenant:link, create a new one with tenant:link-renew.');

            return EXIT_USER_INPUT;
        }

        $host = $tenancy->hostFor($slug);
        CLI::write('Requesting certificate and creating vhost for ' . $host . ' ...');
        exec('sudo -n /usr/local/sbin/scandock-site add ' . escapeshellarg($host) . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            CLI::error("Vhost/certificate setup failed:\n" . implode("\n", $output));

            return EXIT_ERROR;
        }

        $id = $tenants->insert([
            'slug'           => $slug,
            'name'           => CLI::getOption('name') ?: $slug,
            'status'         => 'setup',
            'retention_days' => config(ScanDock::class)->defaultRetentionDays,
        ]);
        $token = $tenants->issueSetupToken((int) $id);

        CLI::write('Tenant created.', 'green');
        CLI::write('Start link: ' . CLI::color('https://' . $host . '/setup/' . $token, 'yellow'));

        return EXIT_SUCCESS;
    }
}

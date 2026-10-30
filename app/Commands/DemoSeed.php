<?php

namespace App\Commands;

use App\Models\AuditLogModel;
use App\Models\FtpAccountMemberModel;
use App\Models\FtpAccountModel;
use App\Models\ScanRejectionModel;
use App\Models\TenantModel;
use App\Models\UserModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\ScanDock;

/**
 * Resets the demo tenant ("Muster GmbH") and fills it with fictitious data:
 * users, two devices, scans, a rejected file and journal entries. The tenant
 * itself must exist (scandock tenant:create demo --name "Muster GmbH").
 * The demo has no Microsoft sign-in; see Libraries\DemoAccess.
 */
class DemoSeed extends BaseCommand
{
    protected $group       = 'ScanDock';
    protected $name        = 'demo:seed';
    protected $description = 'Resets the demo tenant and fills it with fictitious data.';

    private const ADMIN_GROUP = 'd0d0d0d0-0000-4000-8000-000000000001';
    private const GF_GROUP    = 'd0d0d0d0-0000-4000-8000-000000000002';

    public function run(array $params)
    {
        $config  = config(ScanDock::class);
        $tenants = model(TenantModel::class);
        $tenant  = $tenants->findBySlug($config->demoSlug);
        if ($tenant === null) {
            CLI::error('Demo tenant "' . $config->demoSlug . '" does not exist. Create it first with tenant:create.');

            return EXIT_USER_INPUT;
        }
        $id = (int) $tenant['id'];

        // --- reset
        foreach (model(FtpAccountModel::class)->where('tenant_id', $id)->findAll() as $account) {
            service('ftpAccountManager')->delete($account);
        }
        model(UserModel::class)->where('tenant_id', $id)->delete();
        model(ScanRejectionModel::class)->where('tenant_id', $id)->delete();
        model(AuditLogModel::class)->where('tenant_id', $id)->delete();

        // --- tenant settings (fictitious Entra values, never used for sign-in)
        $tenants->update($id, [
            'name'                    => 'Muster GmbH',
            'status'                  => 'active',
            'setup_token_hash'        => null,
            'setup_token_encrypted'   => null,
            'entra_tenant_id'         => 'd0d0d0d0-0000-4000-8000-0000000000aa',
            'entra_client_id'         => 'd0d0d0d0-0000-4000-8000-0000000000bb',
            'entra_client_secret'     => TenantModel::encryptSecret('demo-secret-not-valid'),
            'entra_secret_expires_at' => date('Y-m-d', strtotime('+700 days')),
            'admin_group_id'          => self::ADMIN_GROUP,
            'retention_days'          => 30,
        ]);
        $tenant = $tenants->find($id);
        $this->storeLogo($tenant);

        // --- users (memberships never expire for the demo)
        $users  = model(UserModel::class);
        $forever = '2099-12-31 23:59:59';
        $people = [
            ['anna.admin@muster-gmbh.example', 'Anna Admin', [self::ADMIN_GROUP]],
            ['bernd.beispiel@muster-gmbh.example', 'Bernd Beispiel', []],
            ['clara.chef@muster-gmbh.example', 'Clara Chef', [self::GF_GROUP]],
        ];
        foreach ($people as $i => [$upn, $name, $groups]) {
            $users->insert([
                'tenant_id'        => $id,
                'entra_oid'        => sprintf('d0d0d0d0-0000-4000-8000-1000000000%02d', $i + 1),
                'upn'              => $upn,
                'name'             => $name,
                'group_ids'        => json_encode($groups),
                'groups_synced_at' => $forever,
                'last_login_at'    => date('Y-m-d H:i:s'),
            ]);
        }
        $anna = 'Anna Admin (anna.admin@muster-gmbh.example)';
        $audit = service('audit');
        $audit->record($id, 'tenant.setup_completed', [], $anna);
        $audit->record($id, 'settings.admin_group', ['old' => 'keine (alle sind Admins)', 'new' => self::ADMIN_GROUP], $anna);

        // --- devices
        $manager = service('ftpAccountManager');
        $front   = $manager->create($tenant, 'Kopierer Empfang', 'public');
        $gf      = $manager->create($tenant, 'Kopierer Geschäftsführung', 'private');
        $audit->record($id, 'ftp.created', ['account' => 'Kopierer Empfang', 'visibility' => 'öffentlich'], $anna);
        $audit->record($id, 'ftp.created', ['account' => 'Kopierer Geschäftsführung', 'visibility' => 'privat'], $anna);
        $members = model(FtpAccountMemberModel::class);
        $members->insert(['ftp_account_id' => $gf['id'], 'member_type' => 'group', 'member_ref' => self::GF_GROUP, 'label' => 'Geschäftsführung']);
        $members->insert(['ftp_account_id' => $gf['id'], 'member_type' => 'user', 'member_ref' => 'anna.admin@muster-gmbh.example',
            'member_oid' => 'd0d0d0d0-0000-4000-8000-100000000001', 'label' => 'Anna Admin']);
        $audit->record($id, 'ftp.member_added', ['account' => 'Kopierer Geschäftsführung', 'type' => 'Gruppe', 'member' => 'Geschäftsführung'], $anna);
        $audit->record($id, 'ftp.member_added', ['account' => 'Kopierer Geschäftsführung', 'type' => 'Person', 'member' => 'Anna Admin (anna.admin@muster-gmbh.example)'], $anna);

        // --- scans: copy demo PDFs into the upload directories and import them
        $pdfs = glob(ROOTPATH . 'resources/demo/pdfs/*.pdf') ?: [];
        sort($pdfs);
        foreach ($pdfs as $i => $pdf) {
            $account = in_array(basename($pdf), ['Protokoll Gesellschafter.pdf', 'Vertrag Leasing.pdf', 'Vertrag Wartung.pdf', '20261030-073001.pdf'], true) ? $gf : $front;
            $target  = $manager->incomingDirectory((int) $account['id']) . '/' . basename($pdf);
            copy($pdf, $target);
            touch($target, time() - (count($pdfs) - $i) * 2700);
        }
        // one rejected file for the warning bar
        file_put_contents($manager->incomingDirectory((int) $front['id']) . '/Notizen.docx', "PK\x03\x04 demo");
        touch($manager->incomingDirectory((int) $front['id']) . '/Notizen.docx', time() - 60);
        $count = service('scanStore')->ingestAll();

        CLI::write('Demo tenant reset: 3 users, 2 devices, ' . $count . ' scans, 1 rejected file.', 'green');

        return EXIT_SUCCESS;
    }

    private function storeLogo(array $tenant): void
    {
        $branding = service('tenantBranding');
        $branding->delete($tenant);
        $dir = rtrim(config(ScanDock::class)->storagePath, '/') . '/' . $tenant['id'] . '/branding';
        if (! is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        $name = 'logo-' . bin2hex(random_bytes(8)) . '.svg';
        copy(ROOTPATH . 'resources/demo/logo.svg', $dir . '/' . $name);
        model(TenantModel::class)->update($tenant['id'], ['logo_file' => $name]);
    }
}

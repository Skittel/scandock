<?php

namespace App\Commands;

use App\Models\TenantModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Records a receive block/release of the disk guard in every tenant's journal.
 * Called by /usr/local/sbin/scandock-diskguard.
 */
class SystemReceiveBlock extends BaseCommand
{
    protected $group       = 'ScanDock';
    protected $name        = 'system:receive-block';
    protected $description = 'Records that receiving scans was blocked or released (used by the disk guard).';
    protected $usage       = 'system:receive-block on|off <free GB>';

    public function run(array $params)
    {
        $state = $params[0] ?? '';
        if (! in_array($state, ['on', 'off'], true)) {
            CLI::error('Usage: ' . $this->usage);

            return EXIT_USER_INPUT;
        }
        $free = number_format((float) ($params[1] ?? 0), 1, ',', '.');
        foreach (model(TenantModel::class)->whereIn('status', ['active', 'setup'])->findAll() as $tenant) {
            service('audit')->record((int) $tenant['id'], $state === 'on' ? 'system.receive_blocked' : 'system.receive_released', ['free' => $free], 'System');
        }

        return EXIT_SUCCESS;
    }
}

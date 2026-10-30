<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Customer logo shown in the header next to the ScanKai logo.
 */
class AddTenantLogo extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('tenants', [
            'logo_file' => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true, 'after' => 'name', 'comment' => 'File name in storage/<tenant>/branding'],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('tenants', 'logo_file');
    }
}

<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Per-tenant key for encrypting scan files at rest. The key itself is stored
 * encrypted with the application key (encryption.key in .env).
 */
class AddScanEncryption extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('tenants', [
            'storage_key' => ['type' => 'TEXT', 'null' => true, 'after' => 'entra_secret_expires_at', 'comment' => 'Encrypted per-tenant file key'],
        ]);
        $this->forge->addColumn('scans', [
            'encrypted' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'after' => 'file_ext'],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('scans', 'encrypted');
        $this->forge->dropColumn('tenants', 'storage_key');
    }
}

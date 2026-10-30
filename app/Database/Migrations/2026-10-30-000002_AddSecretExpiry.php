<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Stores when the tenant's Entra client secret expires, so admins can be warned.
 */
class AddSecretExpiry extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('tenants', [
            'entra_secret_expires_at' => ['type' => 'DATE', 'null' => true, 'after' => 'entra_client_secret'],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('tenants', 'entra_secret_expires_at');
    }
}

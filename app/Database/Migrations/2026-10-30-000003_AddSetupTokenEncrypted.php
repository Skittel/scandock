<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Keeps the start link token encrypted so the operator can display it again.
 * Lookups still use the hash.
 */
class AddSetupTokenEncrypted extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('tenants', [
            'setup_token_encrypted' => ['type' => 'TEXT', 'null' => true, 'after' => 'setup_token_hash'],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('tenants', 'setup_token_encrypted');
    }
}

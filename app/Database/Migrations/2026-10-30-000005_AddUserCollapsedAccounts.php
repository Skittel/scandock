<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Remembers which FTP account sections a user collapsed on the scan page.
 */
class AddUserCollapsedAccounts extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('users', [
            'collapsed_accounts' => ['type' => 'TEXT', 'null' => true, 'after' => 'group_ids', 'comment' => 'JSON list of collapsed FTP account IDs'],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('users', 'collapsed_accounts');
    }
}

<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Remembers per user and FTP account whether scans are shown as tiles or list.
 */
class AddUserAccountViews extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('users', [
            'account_views' => ['type' => 'TEXT', 'null' => true, 'after' => 'collapsed_accounts', 'comment' => 'JSON map account ID => "list"'],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('users', 'account_views');
    }
}

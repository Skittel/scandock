<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Light/dark mode preference per user ("auto" follows the operating system).
 */
class AddUserTheme extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('users', [
            'theme' => ['type' => 'ENUM', 'constraint' => ['auto', 'light', 'dark'], 'default' => 'auto', 'after' => 'collapsed_accounts'],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('users', 'theme');
    }
}

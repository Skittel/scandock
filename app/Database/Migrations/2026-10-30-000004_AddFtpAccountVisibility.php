<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * FTP accounts are either public (every user of the tenant sees the scans)
 * or private (only assigned users and groups). Existing accounts without
 * members become public, accounts with members private.
 */
class AddFtpAccountVisibility extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('ftp_accounts', [
            'visibility' => ['type' => 'ENUM', 'constraint' => ['public', 'private'], 'default' => 'private', 'after' => 'label'],
        ]);
        $this->db->query(
            "UPDATE ftp_accounts a SET a.visibility = IF(
                EXISTS (SELECT 1 FROM ftp_account_members m WHERE m.ftp_account_id = a.id), 'private', 'public')"
        );
    }

    public function down(): void
    {
        $this->forge->dropColumn('ftp_accounts', 'visibility');
    }
}

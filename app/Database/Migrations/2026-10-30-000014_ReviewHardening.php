<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Review follow-up:
 * - scans.deleted_at: a scan whose files could not be removed stays marked
 *   as deleted (hidden) and the daily purge retries the removal
 * - users.groups_synced_at: age of the stored group memberships
 * - ftp_account_members.member_oid: persons are matched by the immutable
 *   Entra object ID instead of the UPN
 */
class ReviewHardening extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('scans', [
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addColumn('users', [
            'groups_synced_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'group_ids'],
        ]);
        $this->forge->addColumn('ftp_account_members', [
            'member_oid' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true, 'after' => 'member_ref'],
        ]);
        // Bind existing person entries to users that already signed in.
        $this->db->query(
            "UPDATE ftp_account_members m
             JOIN ftp_accounts a ON a.id = m.ftp_account_id
             JOIN users u ON u.tenant_id = a.tenant_id AND u.upn = m.member_ref
             SET m.member_oid = u.entra_oid
             WHERE m.member_type = 'user' AND m.member_oid IS NULL"
        );
    }

    public function down(): void
    {
        $this->forge->dropColumn('ftp_account_members', 'member_oid');
        $this->forge->dropColumn('users', 'groups_synced_at');
        $this->forge->dropColumn('scans', 'deleted_at');
    }
}

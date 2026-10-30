<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use Config\ScanDock;

/**
 * Recreates the ProFTPD view after the rename to ScanDock: new upload path
 * and the current database user as definer.
 */
class RecreateFtpUsersView extends Migration
{
    public function up(): void
    {
        $incoming = rtrim(config(ScanDock::class)->incomingPath, '/') . '/';
        $this->db->query('DROP VIEW IF EXISTS ftp_users');
        $this->db->query(
            'CREATE VIEW ftp_users AS
             SELECT a.username AS userid,
                    a.password_hash AS passwd,
                    CONCAT(' . $this->db->escape($incoming) . ', a.id) AS homedir
             FROM ftp_accounts a
             JOIN tenants t ON t.id = a.tenant_id
             WHERE a.active = 1 AND t.status = \'active\''
        );
    }

    public function down(): void
    {
    }
}

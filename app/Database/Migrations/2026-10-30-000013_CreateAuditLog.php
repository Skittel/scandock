<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Admin journal: configuration changes, FTP account changes and file events
 * per tenant (sign-ins, views and downloads are not logged).
 */
class CreateAuditLog extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'tenant_id'  => ['type' => 'INT', 'unsigned' => true],
            'user_id'    => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'actor'      => ['type' => 'VARCHAR', 'constraint' => 190, 'comment' => 'Name/UPN at the time, or System/Betreiber'],
            'category'   => ['type' => 'ENUM', 'constraint' => ['settings', 'ftp', 'scans', 'system']],
            'action'     => ['type' => 'VARCHAR', 'constraint' => 40],
            'details'    => ['type' => 'TEXT', 'null' => true, 'comment' => 'JSON'],
            'ip'         => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'created_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['tenant_id', 'id']);
        $this->forge->addForeignKey('tenant_id', 'tenants', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('audit_log');
    }

    public function down(): void
    {
        $this->forge->dropTable('audit_log', true);
    }
}

<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Scans can be PDF, JPEG, PNG or TIFF files; rejected uploads are logged so
 * users can see why a file was deleted.
 */
class AddImageScansAndRejections extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('scans', [
            'file_ext' => ['type' => 'VARCHAR', 'constraint' => 5, 'default' => 'pdf', 'after' => 'stored_name'],
        ]);

        $this->forge->addField([
            'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'tenant_id'      => ['type' => 'INT', 'unsigned' => true],
            'ftp_account_id' => ['type' => 'INT', 'unsigned' => true],
            'original_name'  => ['type' => 'VARCHAR', 'constraint' => 255],
            'size'           => ['type' => 'BIGINT', 'unsigned' => true, 'default' => 0],
            'reason'         => ['type' => 'VARCHAR', 'constraint' => 255],
            'created_at'     => ['type' => 'DATETIME'],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['ftp_account_id', 'created_at']);
        $this->forge->addForeignKey('tenant_id', 'tenants', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('ftp_account_id', 'ftp_accounts', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('scan_rejections');
    }

    public function down(): void
    {
        $this->forge->dropTable('scan_rejections', true);
        $this->forge->dropColumn('scans', 'file_ext');
    }
}

<?php

namespace App\Database\Migrations;

use App\Libraries\PublicId;
use CodeIgniter\Database\Migration;

/**
 * Random public IDs for everything that appears in URLs.
 */
class AddPublicIds extends Migration
{
    private const TABLES = ['scans', 'ftp_accounts', 'ftp_account_members'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            $this->forge->addColumn($table, [
                'public_id' => ['type' => 'CHAR', 'constraint' => PublicId::LENGTH, 'null' => true, 'after' => 'id'],
            ]);
            foreach ($this->db->table($table)->select('id')->get()->getResultArray() as $row) {
                $this->db->table($table)->where('id', $row['id'])->update(['public_id' => PublicId::generate()]);
            }
            $this->db->query("ALTER TABLE {$table} MODIFY public_id CHAR(" . PublicId::LENGTH . ') NOT NULL');
            $this->db->query("ALTER TABLE {$table} ADD UNIQUE KEY {$table}_public_id (public_id)");
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            $this->forge->dropColumn($table, 'public_id');
        }
    }
}

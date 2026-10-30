<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Initial schema: tenants, users, FTP accounts with members, scans and the
 * read-only view used by ProFTPD for authentication.
 */
class CreateInitialSchema extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'                   => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'slug'                 => ['type' => 'VARCHAR', 'constraint' => 63],
            'name'                 => ['type' => 'VARCHAR', 'constraint' => 190],
            'status'               => ['type' => 'ENUM', 'constraint' => ['setup', 'active', 'disabled'], 'default' => 'setup'],
            'setup_token_hash'     => ['type' => 'CHAR', 'constraint' => 64, 'null' => true],
            'entra_tenant_id'      => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'entra_client_id'      => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'entra_client_secret'  => ['type' => 'TEXT', 'null' => true, 'comment' => 'Encrypted'],
            'admin_group_id'       => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'retention_days'       => ['type' => 'SMALLINT', 'unsigned' => true, 'default' => 30],
            'created_at'           => ['type' => 'DATETIME', 'null' => true],
            'updated_at'           => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('slug');
        $this->forge->createTable('tenants');

        $this->forge->addField([
            'id'            => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'tenant_id'     => ['type' => 'INT', 'unsigned' => true],
            'entra_oid'     => ['type' => 'CHAR', 'constraint' => 36],
            'upn'           => ['type' => 'VARCHAR', 'constraint' => 190, 'comment' => 'Lowercase UPN or email'],
            'name'          => ['type' => 'VARCHAR', 'constraint' => 190],
            'group_ids'     => ['type' => 'TEXT', 'null' => true, 'comment' => 'JSON list of Entra group IDs from last login'],
            'last_login_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['tenant_id', 'entra_oid']);
        $this->forge->addForeignKey('tenant_id', 'tenants', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('users');

        $this->forge->addField([
            'id'                 => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'tenant_id'          => ['type' => 'INT', 'unsigned' => true],
            'label'              => ['type' => 'VARCHAR', 'constraint' => 190],
            'username'           => ['type' => 'VARCHAR', 'constraint' => 64],
            'password_hash'      => ['type' => 'VARCHAR', 'constraint' => 255, 'comment' => 'crypt() SHA-512 for ProFTPD'],
            'password_encrypted' => ['type' => 'TEXT', 'comment' => 'Encrypted plain password for display'],
            'active'             => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'         => ['type' => 'DATETIME', 'null' => true],
            'updated_at'         => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('username');
        $this->forge->addForeignKey('tenant_id', 'tenants', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('ftp_accounts');

        $this->forge->addField([
            'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'ftp_account_id' => ['type' => 'INT', 'unsigned' => true],
            'member_type'    => ['type' => 'ENUM', 'constraint' => ['user', 'group']],
            'member_ref'     => ['type' => 'VARCHAR', 'constraint' => 190, 'comment' => 'Lowercase UPN for users, object ID for groups'],
            'label'          => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['ftp_account_id', 'member_type', 'member_ref']);
        $this->forge->addForeignKey('ftp_account_id', 'ftp_accounts', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('ftp_account_members');

        $this->forge->addField([
            'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'tenant_id'      => ['type' => 'INT', 'unsigned' => true],
            'ftp_account_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'original_name'  => ['type' => 'VARCHAR', 'constraint' => 255],
            'stored_name'    => ['type' => 'CHAR', 'constraint' => 36, 'comment' => 'UUID, file name in storage'],
            'size'           => ['type' => 'BIGINT', 'unsigned' => true],
            'sha256'         => ['type' => 'CHAR', 'constraint' => 64],
            'has_thumbnail'  => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'received_at'    => ['type' => 'DATETIME'],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('stored_name');
        $this->forge->addKey(['tenant_id', 'received_at']);
        $this->forge->addForeignKey('tenant_id', 'tenants', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('ftp_account_id', 'ftp_accounts', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('scans');

        // ProFTPD only sees active accounts of active tenants.
        $this->db->query(
            "CREATE OR REPLACE VIEW ftp_users AS
             SELECT a.username AS userid,
                    a.password_hash AS passwd,
                    CONCAT('/srv/scankai/incoming/', a.id) AS homedir
             FROM ftp_accounts a
             JOIN tenants t ON t.id = a.tenant_id
             WHERE a.active = 1 AND t.status = 'active'"
        );
    }

    public function down(): void
    {
        $this->db->query('DROP VIEW IF EXISTS ftp_users');
        $this->forge->dropTable('scans', true);
        $this->forge->dropTable('ftp_account_members', true);
        $this->forge->dropTable('ftp_accounts', true);
        $this->forge->dropTable('users', true);
        $this->forge->dropTable('tenants', true);
    }
}

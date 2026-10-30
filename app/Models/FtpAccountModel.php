<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * FTP account used by a copier to upload scans. Authenticated by ProFTPD
 * through the database view `ftp_users`.
 */
class FtpAccountModel extends Model
{
    protected $table         = 'ftp_accounts';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['tenant_id', 'label', 'username', 'password_hash', 'password_encrypted', 'active'];

    public function forTenant(int $tenantId): array
    {
        return $this->where('tenant_id', $tenantId)->orderBy('label')->findAll();
    }

    public function findForTenant(int $tenantId, int $id): ?array
    {
        return $this->where('tenant_id', $tenantId)->where('id', $id)->first();
    }

    public static function plainPassword(array $account): string
    {
        return service('encrypter')->decrypt(base64_decode($account['password_encrypted'], true));
    }
}

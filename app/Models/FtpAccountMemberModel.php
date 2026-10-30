<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Grants a person (by UPN) or an Entra group access to the scans of an FTP account.
 */
class FtpAccountMemberModel extends Model
{
    protected $table         = 'ftp_account_members';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $updatedField  = '';
    protected $allowedFields = ['ftp_account_id', 'member_type', 'member_ref', 'label'];

    public function forAccount(int $accountId): array
    {
        return $this->where('ftp_account_id', $accountId)->orderBy('member_type')->orderBy('label')->findAll();
    }
}

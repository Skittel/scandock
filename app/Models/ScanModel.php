<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * A scanned document received via FTP.
 */
class ScanModel extends Model
{
    protected $table         = 'scans';
    protected $returnType    = 'array';
    protected $useTimestamps  = true;
    protected $useSoftDeletes = true;
    protected $deletedField   = 'deleted_at';
    protected $updatedField  = '';
    protected $beforeInsert  = ['assignPublicId'];
    protected $allowedFields = ['public_id', 
        'tenant_id', 'ftp_account_id', 'original_name', 'stored_name', 'file_ext', 'encrypted', 'size', 'sha256',
        'has_thumbnail', 'received_at',
    ];

    protected function assignPublicId(array $data): array
    {
        return \App\Libraries\PublicId::assign($data);
    }
}

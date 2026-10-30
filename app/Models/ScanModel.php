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
    protected $useTimestamps = true;
    protected $updatedField  = '';
    protected $allowedFields = [
        'tenant_id', 'ftp_account_id', 'original_name', 'stored_name', 'file_ext', 'size', 'sha256',
        'has_thumbnail', 'received_at',
    ];
}

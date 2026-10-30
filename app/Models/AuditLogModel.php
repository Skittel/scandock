<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Entries of the admin journal.
 */
class AuditLogModel extends Model
{
    protected $table         = 'audit_log';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $updatedField  = '';
    protected $allowedFields = ['tenant_id', 'user_id', 'actor', 'category', 'action', 'details', 'ip'];
}

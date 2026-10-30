<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * An upload that was deleted during import, with the reason.
 */
class ScanRejectionModel extends Model
{
    protected $table         = 'scan_rejections';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $updatedField  = '';
    protected $allowedFields = ['tenant_id', 'ftp_account_id', 'original_name', 'size', 'reason'];

    /**
     * Rejections of the last $seconds for the given accounts, newest first.
     *
     * @param list<int> $accountIds
     *
     * @return array<int, list<array>> grouped by FTP account ID
     */
    public function recentByAccount(array $accountIds, int $seconds = 3600): array
    {
        if ($accountIds === []) {
            return [];
        }
        $rows = $this->whereIn('ftp_account_id', $accountIds)
            ->where('created_at >=', date('Y-m-d H:i:s', time() - $seconds))
            ->orderBy('id', 'DESC')->findAll();

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int) $row['ftp_account_id']][] = $row;
        }

        return $grouped;
    }
}

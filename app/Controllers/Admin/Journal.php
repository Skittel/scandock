<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\AuditLog;
use App\Models\AuditLogModel;

/**
 * Admin journal: who changed what and which files were received or deleted.
 */
class Journal extends BaseController
{
    public function index(): string
    {
        $category = (string) $this->request->getGet('category');
        $model    = model(AuditLogModel::class)->where('tenant_id', $this->tenant()['id']);
        if (isset(AuditLog::CATEGORIES[$category])) {
            $model->where('category', $category);
        }

        return $this->render('admin/journal', [
            'entries'    => $model->orderBy('id', 'DESC')->paginate(50),
            'pager'      => $model->pager,
            'category'   => $category,
            'categories' => AuditLog::CATEGORIES,
        ]);
    }
}

<?php

namespace App\Controllers;

use App\Models\UserModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Personal settings of the signed-in user.
 */
class Account extends BaseController
{
    /**
     * Stores the light/dark mode preference. Works as a normal form post and
     * as a background request from the user menu.
     */
    public function theme(): ResponseInterface
    {
        $theme = (string) ($this->request->getPost('theme') ?? $this->request->getJSON(true)['theme'] ?? '');
        if (! in_array($theme, ['auto', 'light', 'dark'], true)) {
            $theme = 'auto';
        }
        model(UserModel::class)->update(session('user_id'), ['theme' => $theme]);

        if ($this->request->isAJAX()) {
            return $this->response->setJSON(['theme' => $theme]);
        }

        return redirect()->back();
    }
}

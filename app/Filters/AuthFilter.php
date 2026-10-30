<?php

namespace App\Filters;

use App\Models\UserModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Requires a signed-in user of the current tenant and keeps the user's group
 * memberships current (refreshed from Entra every few minutes).
 */
class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $tenant  = service('tenancy')->current();
        $session = session();

        if ($tenant === null || (int) $session->get('tenant_id') !== (int) $tenant['id'] || ! $session->get('user_id')) {
            return redirect()->to('/auth/login');
        }

        $user = model(UserModel::class)->find($session->get('user_id'));
        if ($user === null) {
            $session->destroy();

            return redirect()->to('/auth/login');
        }
        service('groupSync')->refreshIfStale($tenant, $user);

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}

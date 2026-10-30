<?php

namespace App\Filters;

use App\Models\UserModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Requires admin rights in the current tenant. Must run after AuthFilter.
 */
class AdminFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $tenant = service('tenancy')->current();
        $user   = model(UserModel::class)->find(session('user_id'));

        if ($tenant === null || $user === null || ! service('scanAccess')->isAdmin($tenant, $user)) {
            return service('response')->setStatusCode(403)->setBody(view('message', [
                'title'   => 'Keine Berechtigung',
                'message' => 'Für diesen Bereich benötigen Sie Administratorrechte.',
            ]));
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}

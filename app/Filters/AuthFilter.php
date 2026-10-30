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
        $demo    = service('demoAccess');

        // Demo tenant: automatic sign-in (switch users with ?demo_user=<upn>).
        if ($demo->isDemo($tenant) && $demo->isAllowed($request->getIPAddress())) {
            $wanted = $request->getGet('demo_user');
            if ($wanted !== null || (int) $session->get('tenant_id') !== (int) $tenant['id'] || ! $session->get('user_id')) {
                if ($demo->signIn($tenant, is_string($wanted) ? $wanted : null) === null) {
                    return service('response')->setStatusCode(503)->setBody(view('message', [
                        'title' => 'Demo nicht eingerichtet', 'message' => 'Der Demo-Kunde hat noch keine Benutzer.',
                    ]));
                }
            }

            return null;
        }

        if ($tenant === null || (int) $session->get('tenant_id') !== (int) $tenant['id'] || ! $session->get('user_id')) {
            return redirect()->to('/auth/login');
        }

        $user = model(UserModel::class)->find($session->get('user_id'));
        if ($user === null) {
            $session->destroy();

            return redirect()->to('/auth/login');
        }
        $user = service('groupSync')->refreshIfStale($tenant, $user);

        // Groups could not be refreshed and are outdated: renew them with a
        // silent sign-in (normal page views only, at most every 10 minutes).
        if (UserModel::groupsStale($user) && $request->getMethod() === 'GET' && ! $request->isAJAX()
            && (int) $session->get('silent_reauth_at') < time() - 600) {
            $session->set(['silent_reauth_at' => time(), 'login_return' => '/' . ltrim($request->getUri()->getPath(), '/')]);

            return redirect()->to('/auth/login?silent=1');
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}

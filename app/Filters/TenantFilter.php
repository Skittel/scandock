<?php

namespace App\Filters;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Requires a known tenant for the requested host.
 *
 * Arguments: "setup" allows tenants that are still in setup only,
 * "any" allows setup and active tenants, default allows active tenants only.
 */
class TenantFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $tenant = service('tenancy')->current();
        if ($tenant === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $mode = $arguments[0] ?? 'active';
        $ok   = match ($mode) {
            'setup' => $tenant['status'] === 'setup',
            'any'   => in_array($tenant['status'], ['setup', 'active'], true),
            default => $tenant['status'] === 'active',
        };

        if (! $ok) {
            return service('response')->setStatusCode(403)->setBody(view('message', [
                'title'   => $tenant['status'] === 'setup' ? 'Noch nicht eingerichtet' : 'Nicht verfügbar',
                'message' => $tenant['status'] === 'setup'
                    ? 'Dieser Zugang wird gerade eingerichtet. Bitte verwenden Sie den Start-Link, den Sie erhalten haben.'
                    : 'Dieser Zugang ist derzeit nicht verfügbar.',
            ]));
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}

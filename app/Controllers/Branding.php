<?php

namespace App\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Serves the customer logo of the current tenant.
 */
class Branding extends BaseController
{
    public function logo(): ResponseInterface
    {
        $branding = service('tenantBranding');
        $path     = $branding->path($this->tenant());
        if ($path === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->response
            ->setHeader('Content-Type', $branding->mimeType($path))
            ->setHeader('Cache-Control', 'public, max-age=86400')
            // SVGs must never run scripts, even when opened directly.
            ->setHeader('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; sandbox")
            ->setBody(file_get_contents($path));
    }
}

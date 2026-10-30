<?php

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;

/**
 * Entry point of tenant hosts. The base domain serves the static landing page
 * from /landing via its own vhost.
 */
class Home extends BaseController
{
    public function index(): ResponseInterface|string
    {
        $tenant = $this->tenant();
        if ($tenant === null) {
            return $this->response->setStatusCode(404)->setBody(view('message', [
                'title'   => 'Unbekannter Zugang',
                'message' => 'Unter dieser Adresse ist kein ScanKai-Zugang eingerichtet.',
            ]));
        }

        return redirect()->to('/scans');
    }
}

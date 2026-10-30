<?php

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;

/**
 * Entry point: landing page on the base domain, scan list on tenant hosts.
 */
class Home extends BaseController
{
    public function index(): ResponseInterface|string
    {
        $tenant = $this->tenant();
        if ($tenant === null) {
            if (service('tenancy')->slugFromHost($this->request->getServer('HTTP_HOST') ?? '') !== null) {
                return $this->response->setStatusCode(404)->setBody(view('message', [
                    'title'   => 'Unbekannter Zugang',
                    'message' => 'Unter dieser Adresse ist kein ScanKai-Zugang eingerichtet.',
                ]));
            }

            return view('landing');
        }

        return redirect()->to('/scans');
    }
}

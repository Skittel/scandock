<?php

namespace App\Controllers;

use App\Libraries\EntraOidc;
use App\Models\TenantModel;
use App\Models\UserModel;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;

/**
 * Sign-in with the customer's Microsoft Entra ID.
 */
class Auth extends BaseController
{
    public function login(): ResponseInterface
    {
        $tenant  = $this->tenant();
        $session = session();

        if ($tenant['status'] === 'setup' && ! $this->validSetupToken($tenant)) {
            return $this->response->setStatusCode(403)->setBody(view('message', [
                'title'   => 'Noch nicht eingerichtet',
                'message' => 'Bitte verwenden Sie den Start-Link, den Sie erhalten haben.',
            ]));
        }
        if (empty($tenant['entra_client_id'])) {
            return $this->response->setStatusCode(503)->setBody(view('message', [
                'title'   => 'Anmeldung nicht konfiguriert',
                'message' => 'Für diesen Zugang ist noch keine Entra-Anmeldung hinterlegt.',
            ]));
        }

        $flow = service('entra')->beginLogin($tenant, site_url('auth/callback'));
        $session->set('oidc', [
            'state'    => $flow['state'],
            'nonce'    => $flow['nonce'],
            'verifier' => $flow['verifier'],
        ]);

        return redirect()->to($flow['url']);
    }

    public function callback(): ResponseInterface|string
    {
        $tenant  = $this->tenant();
        $session = session();
        $flow    = $session->get('oidc');
        $session->remove('oidc');

        try {
            if (! is_array($flow) || ! hash_equals($flow['state'], (string) $this->request->getGet('state'))) {
                throw new RuntimeException('Ungültiger oder abgelaufener Anmeldevorgang. Bitte erneut versuchen.');
            }
            if ($this->request->getGet('error')) {
                throw new RuntimeException('Entra meldet: ' . strtok((string) $this->request->getGet('error_description'), "\r\n"));
            }
            if ($tenant['status'] === 'setup' && ! $this->validSetupToken($tenant)) {
                throw new RuntimeException('Der Start-Link ist nicht mehr gültig.');
            }

            $claims = service('entra')->completeLogin(
                $tenant,
                (string) $this->request->getGet('code'),
                site_url('auth/callback'),
                $flow['verifier'],
                $flow['nonce']
            );
        } catch (RuntimeException $e) {
            log_message('warning', 'Login failed for tenant {slug}: {msg}', ['slug' => $tenant['slug'], 'msg' => $e->getMessage()]);

            return $this->loginFailed($tenant, $e->getMessage());
        }

        $groupIds = EntraOidc::groupIds($claims);
        $user     = model(UserModel::class)->upsertFromLogin(
            (int) $tenant['id'],
            strtolower((string) $claims['oid']),
            (string) ($claims['preferred_username'] ?? $claims['email'] ?? $claims['upn'] ?? ''),
            (string) ($claims['name'] ?? $claims['preferred_username'] ?? ''),
            $groupIds ?? []
        );

        // A successful sign-in through the start link completes the setup and invalidates the link.
        if ($tenant['status'] === 'setup' || $this->validSetupToken($tenant)) {
            model(TenantModel::class)->update($tenant['id'], ['status' => 'active']);
            model(TenantModel::class)->clearSetupToken((int) $tenant['id']);
            $session->remove('setup_token');
            $session->setFlashdata('notice', 'Die Anmeldung mit Microsoft Entra ID funktioniert. Die Einrichtung ist abgeschlossen, der Start-Link ist nun ungültig.');
        }
        if ($groupIds === null) {
            $session->setFlashdata('warning', 'Ihr Konto ist Mitglied in zu vielen Gruppen; Entra hat keine Gruppenliste übermittelt. Gruppenbasierte Rechte greifen daher nicht.');
        }

        $session->regenerate(true);
        $session->set(['tenant_id' => (int) $tenant['id'], 'user_id' => (int) $user['id']]);

        return redirect()->to('/scans');
    }

    public function logout(): string
    {
        session()->destroy();

        return view('message', [
            'title'   => 'Abgemeldet',
            'message' => 'Sie wurden von ScanKai abgemeldet.',
            'link'    => ['url' => site_url('auth/login'), 'label' => 'Erneut anmelden'],
        ]);
    }

    private function validSetupToken(array $tenant): bool
    {
        $token = session('setup_token');

        return is_string($token) && model(TenantModel::class)->findBySetupToken($tenant['slug'], $token) !== null;
    }

    private function loginFailed(array $tenant, string $message): ResponseInterface|string
    {
        if ($tenant['status'] === 'setup' && is_string(session('setup_token'))) {
            return redirect()->to('setup/' . session('setup_token') . '#test')->with('login_error', $message);
        }

        return view('message', [
            'title'   => 'Anmeldung fehlgeschlagen',
            'message' => $message,
            'link'    => ['url' => site_url('auth/login'), 'label' => 'Erneut versuchen'],
        ]);
    }
}

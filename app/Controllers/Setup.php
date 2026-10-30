<?php

namespace App\Controllers;

use App\Models\TenantModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Onboarding wizard reached through the start link. Collects the values of
 * the customer's Entra app registration and tests the sign-in. After the
 * first successful sign-in the start link stops working.
 */
class Setup extends BaseController
{
    private const GUID = '/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/';

    public function show(string $token): string
    {
        $tenant = $this->tenantForToken($token);

        return view('setup/wizard', [
            'tenant'      => $tenant,
            'token'       => $token,
            'redirectUri' => site_url('auth/callback'),
            'logoutUri'   => site_url('/'),
            'configured'  => ! empty($tenant['entra_client_secret']),
            'errors'      => session()->getFlashdata('errors') ?? [],
            'loginError'  => session()->getFlashdata('login_error'),
        ]);
    }

    public function save(string $token): ResponseInterface
    {
        $tenant = $this->tenantForToken($token);
        $tenantId = trim((string) $this->request->getPost('entra_tenant_id'));
        $clientId = trim((string) $this->request->getPost('entra_client_id'));
        $secret   = trim((string) $this->request->getPost('entra_client_secret'));

        $errors = [];
        if (preg_match(self::GUID, $tenantId) !== 1) {
            $errors[] = 'Die Verzeichnis-ID (Tenant-ID) hat nicht das erwartete Format.';
        }
        if (preg_match(self::GUID, $clientId) !== 1) {
            $errors[] = 'Die Anwendungs-ID (Client-ID) hat nicht das erwartete Format.';
        }
        if ($secret === '' && empty($tenant['entra_client_secret'])) {
            $errors[] = 'Bitte den Wert des geheimen Clientschlüssels eintragen.';
        }
        if ($errors !== []) {
            return redirect()->to('setup/' . $token)->withInput()->with('errors', $errors);
        }

        $data = [
            'entra_tenant_id' => strtolower($tenantId),
            'entra_client_id' => strtolower($clientId),
        ];
        if ($secret !== '') {
            $data['entra_client_secret'] = TenantModel::encryptSecret($secret);
        }
        model(TenantModel::class)->update($tenant['id'], $data);

        return redirect()->to('setup/' . $token . '#test');
    }

    /**
     * Starts a sign-in that activates the tenant on success.
     */
    public function test(string $token): ResponseInterface
    {
        $tenant = $this->tenantForToken($token);
        if (empty($tenant['entra_client_secret'])) {
            return redirect()->to('setup/' . $token);
        }
        session()->set('setup_token', $token);

        return redirect()->to('auth/login');
    }

    private function tenantForToken(string $token): array
    {
        $slug   = service('tenancy')->slugFromHost($this->request->getServer('HTTP_HOST') ?? '');
        $tenant = $slug === null ? null : model(TenantModel::class)->findBySetupToken($slug, $token);
        if ($tenant === null) {
            throw PageNotFoundException::forPageNotFound('Dieser Start-Link ist ungültig oder wurde bereits verwendet.');
        }

        return $tenant;
    }
}

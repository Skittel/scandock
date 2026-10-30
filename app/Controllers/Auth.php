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

        $silent = $this->request->getGet('silent') === '1';
        $flow   = service('entra')->beginLogin($tenant, site_url('auth/callback'), $silent);
        $session->set('oidc', [
            'state'    => $flow['state'],
            'nonce'    => $flow['nonce'],
            'verifier' => $flow['verifier'],
            'silent'   => $silent,
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
            // A silent sign-in that needs user interaction is repeated with a prompt.
            if (! empty($flow['silent']) && in_array($this->request->getGet('error'), ['login_required', 'interaction_required', 'consent_required'], true)) {
                return redirect()->to('auth/login');
            }
            if ($this->request->getGet('error')) {
                throw new RuntimeException('Entra meldet: ' . strtok((string) $this->request->getGet('error_description'), "\r\n"));
            }
            if ($tenant['status'] === 'setup' && ! $this->validSetupToken($tenant)) {
                throw new RuntimeException('Der Start-Link ist nicht mehr gültig.');
            }

            $result = service('entra')->completeLogin(
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

        $claims   = $result['claims'];
        $groupIds = $this->collectGroupIds($claims, $result['access_token']);
        $user     = model(UserModel::class)->upsertFromLogin(
            (int) $tenant['id'],
            strtolower((string) $claims['oid']),
            (string) ($claims['preferred_username'] ?? $claims['email'] ?? $claims['upn'] ?? ''),
            (string) ($claims['name'] ?? $claims['preferred_username'] ?? ''),
            $groupIds
        );

        // A successful sign-in through the start link completes the setup and invalidates the link.
        if ($tenant['status'] === 'setup' || $this->validSetupToken($tenant)) {
            model(TenantModel::class)->update($tenant['id'], ['status' => 'active']);
            model(TenantModel::class)->clearSetupToken((int) $tenant['id']);
            service('audit')->record((int) $tenant['id'], 'tenant.setup_completed', [], $user['name'] . ' (' . $user['upn'] . ')');
            $session->remove('setup_token');
            $session->setFlashdata('notice', 'Die Anmeldung mit Microsoft Entra ID funktioniert. Die Einrichtung ist abgeschlossen, der Start-Link ist nun ungültig.');
        }
        if ($groupIds === null) {
            $session->setFlashdata('warning', 'Ihre Gruppenmitgliedschaften konnten nicht von Microsoft abgerufen werden. Gruppenbasierte Rechte greifen daher nicht.');
        }

        cache()->save('groupsync_' . (int) $user['id'], time(), 300);

        $return = $session->get('login_return');
        $session->remove('login_return');
        $session->regenerate(true);
        $session->set([
            'tenant_id'          => (int) $tenant['id'],
            'user_id'            => (int) $user['id'],
            'graph_token'        => $result['access_token'],
            'graph_token_expiry' => $result['expires_at'],
        ]);

        return redirect()->to(is_string($return) && str_starts_with($return, '/') && ! str_starts_with($return, '//') ? $return : '/scans');
    }

    public function logout(): string
    {
        session()->destroy();

        return view('message', [
            'title'   => 'Abgemeldet',
            'message' => 'Sie wurden von ScanDock abgemeldet.',
            'link'    => ['url' => site_url('auth/login'), 'label' => 'Erneut anmelden'],
        ]);
    }

    /**
     * Groups from Microsoft Graph, combined with the optional groups claim.
     * Returns null if neither source delivered a list.
     *
     * @return list<string>|null
     */
    private function collectGroupIds(array $claims, ?string $accessToken): ?array
    {
        $fromClaim = EntraOidc::groupIds($claims);
        $fromGraph = $accessToken === null ? null : service('entra')->fetchGroupIds($accessToken);
        if ($fromClaim === null && $fromGraph === null) {
            return null;
        }

        return array_values(array_unique(array_merge($fromClaim ?? [], $fromGraph ?? [])));
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

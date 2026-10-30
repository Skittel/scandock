<?php

namespace App\Libraries;

use App\Models\TenantModel;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use RuntimeException;
use UnexpectedValueException;

/**
 * Minimal OpenID Connect client for Microsoft Entra ID (authorization code
 * flow with PKCE). Every ScanKai tenant uses its own app registration in its
 * own Entra tenant.
 */
class EntraOidc
{
    private const LOGIN_HOST = 'https://login.microsoftonline.com';
    private const GRAPH_HOST = 'https://graph.microsoft.com/v1.0';

    /**
     * User.Read lets ScanKai read the signed-in user's own group memberships
     * from Microsoft Graph. It is granted by default and needs no admin consent.
     */
    private const SCOPES = 'openid profile email User.Read';

    /**
     * Builds the authorization URL and returns it together with the values
     * that must be kept in the session until the callback.
     *
     * With $silent the user is not prompted if a Microsoft session exists.
     *
     * @return array{url: string, state: string, nonce: string, verifier: string}
     */
    public function beginLogin(array $tenant, string $redirectUri, bool $silent = false): array
    {
        $state    = bin2hex(random_bytes(16));
        $nonce    = bin2hex(random_bytes(16));
        $verifier = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        $query = http_build_query([
            'client_id'             => $tenant['entra_client_id'],
            'response_type'         => 'code',
            'redirect_uri'          => $redirectUri,
            'response_mode'         => 'query',
            'scope'                 => self::SCOPES,
            'state'                 => $state,
            'nonce'                 => $nonce,
            'code_challenge'        => $challenge,
            'code_challenge_method' => 'S256',
            'prompt'                => $silent ? 'none' : 'select_account',
        ]);

        return [
            'url'      => $this->endpoint($tenant, 'oauth2/v2.0/authorize') . '?' . $query,
            'state'    => $state,
            'nonce'    => $nonce,
            'verifier' => $verifier,
        ];
    }

    /**
     * Exchanges the authorization code. Returns the validated ID token claims
     * and the access token for Microsoft Graph.
     *
     * @return array{claims: array, access_token: ?string, expires_at: int}
     *
     * @throws RuntimeException on any failure
     */
    public function completeLogin(array $tenant, string $code, string $redirectUri, string $verifier, string $nonce): array
    {
        $secret = model(TenantModel::class)->clientSecret($tenant);
        if ($secret === null) {
            throw new RuntimeException('No client secret configured.');
        }

        $response = service('curlrequest', ['timeout' => 15, 'http_errors' => false])
            ->post($this->endpoint($tenant, 'oauth2/v2.0/token'), [
                'form_params' => [
                    'client_id'     => $tenant['entra_client_id'],
                    'client_secret' => $secret,
                    'grant_type'    => 'authorization_code',
                    'code'          => $code,
                    'redirect_uri'  => $redirectUri,
                    'code_verifier' => $verifier,
                    'scope'         => self::SCOPES,
                ],
            ]);

        $body = json_decode($response->getBody(), true) ?: [];
        if ($response->getStatusCode() !== 200 || empty($body['id_token'])) {
            $error = $body['error_description'] ?? $body['error'] ?? 'HTTP ' . $response->getStatusCode();
            throw new RuntimeException(self::describeError($error));
        }

        return [
            'claims'       => $this->validateIdToken($tenant, $body['id_token'], $nonce),
            'access_token' => $body['access_token'] ?? null,
            'expires_at'   => time() + (int) ($body['expires_in'] ?? 0) - 60,
        ];
    }

    /**
     * Reads the IDs of all groups the signed-in user belongs to (direct and
     * nested, security and Microsoft 365 groups) from Microsoft Graph.
     *
     * @return list<string>|null null if Graph could not be queried
     */
    public function fetchGroupIds(string $accessToken): ?array
    {
        $client = service('curlrequest', ['timeout' => 15, 'http_errors' => false]);
        $url    = self::GRAPH_HOST . '/me/transitiveMemberOf?$select=id&$top=999';
        $ids    = [];

        for ($page = 0; $url !== null && $page < 20; $page++) {
            $response = $client->get($url, ['headers' => ['Authorization' => 'Bearer ' . $accessToken]]);
            if ($response->getStatusCode() !== 200) {
                log_message('warning', 'Graph group lookup failed: HTTP {code}', ['code' => $response->getStatusCode()]);

                return null;
            }
            $body = json_decode($response->getBody(), true) ?: [];
            foreach ($body['value'] ?? [] as $object) {
                if (($object['@odata.type'] ?? '') === '#microsoft.graph.group' && ! empty($object['id'])) {
                    $ids[] = strtolower($object['id']);
                }
            }
            $url = $body['@odata.nextLink'] ?? null;
        }

        return array_values(array_unique($ids));
    }

    /**
     * Verifies signature, issuer, audience, tenant and nonce of an ID token.
     */
    public function validateIdToken(array $tenant, string $idToken, string $nonce): array
    {
        JWT::$leeway = 120;

        try {
            $claims = (array) JWT::decode($idToken, $this->keys($tenant));
        } catch (UnexpectedValueException $e) {
            // Keys may have rotated: fetch them again once.
            try {
                $claims = (array) JWT::decode($idToken, $this->keys($tenant, true));
            } catch (UnexpectedValueException $e) {
                throw new RuntimeException('Invalid ID token: ' . $e->getMessage(), 0, $e);
            }
        }

        $expectedIssuer = self::LOGIN_HOST . '/' . $tenant['entra_tenant_id'] . '/v2.0';
        if (($claims['iss'] ?? '') !== $expectedIssuer) {
            throw new RuntimeException('Unexpected token issuer.');
        }
        if (($claims['aud'] ?? '') !== $tenant['entra_client_id']) {
            throw new RuntimeException('Unexpected token audience.');
        }
        if (strcasecmp($claims['tid'] ?? '', $tenant['entra_tenant_id']) !== 0) {
            throw new RuntimeException('Token belongs to another Entra tenant.');
        }
        if (! hash_equals($nonce, (string) ($claims['nonce'] ?? ''))) {
            throw new RuntimeException('Nonce mismatch.');
        }
        if (empty($claims['oid'])) {
            throw new RuntimeException('Token has no object ID.');
        }

        return $claims;
    }

    /**
     * Checks a client secret by requesting an app-only token (client
     * credentials). Needs no API permissions.
     *
     * @return string|null error message, null if the secret works
     */
    public function verifyClientSecret(string $entraTenantId, string $clientId, string $secret): ?string
    {
        $response = service('curlrequest', ['timeout' => 15, 'http_errors' => false])
            ->post(self::LOGIN_HOST . '/' . rawurlencode($entraTenantId) . '/oauth2/v2.0/token', [
                'form_params' => [
                    'client_id'     => $clientId,
                    'client_secret' => $secret,
                    'grant_type'    => 'client_credentials',
                    'scope'         => 'https://graph.microsoft.com/.default',
                ],
            ]);
        if ($response->getStatusCode() === 200) {
            return null;
        }
        $body = json_decode($response->getBody(), true) ?: [];

        return self::describeError($body['error_description'] ?? $body['error'] ?? 'HTTP ' . $response->getStatusCode());
    }

    /**
     * Turns an Entra error description into a short German message.
     */
    public static function describeError(string $description): string
    {
        $codes = [
            'AADSTS7000222' => 'Der geheime Clientschlüssel ist abgelaufen.',
            'AADSTS7000215' => 'Der geheime Clientschlüssel ist ungültig. Bitte den Wert (nicht die Geheimnis-ID) eintragen.',
            'AADSTS700016'  => 'Die Anwendungs-ID (Client) wurde in diesem Verzeichnis nicht gefunden.',
            'AADSTS90002'   => 'Die Verzeichnis-ID (Mandant) wurde nicht gefunden.',
            'AADSTS900023'  => 'Die Verzeichnis-ID (Mandant) ist ungültig.',
            'AADSTS50011'   => 'Die Umleitungs-URI stimmt nicht mit der App-Registrierung überein.',
        ];
        foreach ($codes as $code => $message) {
            if (str_contains($description, $code . ':')) {
                return $message . ' (' . $code . ')';
            }
        }

        return strtok($description, "\r\n");
    }

    /**
     * Extracts the group IDs from the claims. Returns null when Entra sent a
     * group overage indicator instead of the list (more than 200 groups).
     *
     * @return list<string>|null
     */
    public static function groupIds(array $claims): ?array
    {
        if (isset($claims['_claim_names']) && isset(((array) $claims['_claim_names'])['groups'])) {
            return null;
        }

        return array_values(array_map('strtolower', (array) ($claims['groups'] ?? [])));
    }

    private function endpoint(array $tenant, string $path): string
    {
        return self::LOGIN_HOST . '/' . rawurlencode($tenant['entra_tenant_id']) . '/' . $path;
    }

    private function keys(array $tenant, bool $forceRefresh = false): array
    {
        $cacheKey = 'entra_jwks_' . preg_replace('/[^a-z0-9]/', '', strtolower($tenant['entra_tenant_id']));
        $jwks     = $forceRefresh ? null : cache($cacheKey);

        if (! is_array($jwks)) {
            $response = service('curlrequest', ['timeout' => 10, 'http_errors' => false])
                ->get($this->endpoint($tenant, 'discovery/v2.0/keys'));
            $jwks = json_decode($response->getBody(), true);
            if ($response->getStatusCode() !== 200 || ! isset($jwks['keys'])) {
                throw new RuntimeException('Could not load signing keys from Entra.');
            }
            cache()->save($cacheKey, $jwks, 86400);
        }

        return JWK::parseKeySet($jwks, 'RS256');
    }
}

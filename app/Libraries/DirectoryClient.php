<?php

namespace App\Libraries;

use App\Models\TenantModel;

/**
 * Searches users and groups in the customer's Entra directory via Microsoft
 * Graph with an app-only token (client credentials). Requires the application
 * permissions User.Read.All and Group.ReadBasic.All with admin consent; without
 * them the search methods return null and callers fall back to local data.
 * After a 401/403 the cached token is discarded, so a newly granted consent
 * takes effect within two minutes.
 */
class DirectoryClient
{
    private const GRAPH = 'https://graph.microsoft.com/v1.0';

    /**
     * @return list<array{type: string, ref: string, label: string, detail: string}>|null
     */
    public function searchUsers(array $tenant, string $query): ?array
    {
        $q    = self::searchTerm($query);
        $body = $this->get($tenant, '/users?$search=' . rawurlencode('"displayName:' . $q . '" OR "userPrincipalName:' . $q . '" OR "mail:' . $q . '"')
            . '&$select=id,displayName,userPrincipalName,mail&$top=10&$count=true', 'users');
        if ($body === null) {
            return null;
        }

        return array_map(static fn (array $u) => [
            'type'   => 'user',
            'ref'    => strtolower($u['userPrincipalName'] ?? ''),
            'label'  => (string) ($u['displayName'] ?? $u['userPrincipalName'] ?? ''),
            'detail' => strtolower($u['userPrincipalName'] ?? ''),
        ], $body['value'] ?? []);
    }

    /**
     * @return list<array{type: string, ref: string, label: string, detail: string}>|null
     */
    public function searchGroups(array $tenant, string $query): ?array
    {
        $q    = self::searchTerm($query);
        $body = $this->get($tenant, '/groups?$search=' . rawurlencode('"displayName:' . $q . '"')
            . '&$select=id,displayName,groupTypes,securityEnabled&$top=10&$count=true', 'groups');
        if ($body === null) {
            return null;
        }

        return array_map(static fn (array $g) => [
            'type'   => 'group',
            'ref'    => strtolower($g['id']),
            'label'  => (string) ($g['displayName'] ?? $g['id']),
            'detail' => in_array('Unified', $g['groupTypes'] ?? [], true) ? 'Microsoft-365-Gruppe' : 'Sicherheitsgruppe',
        ], $body['value'] ?? []);
    }

    /**
     * Whether the last attempt showed that the directory search is not permitted.
     */
    public function isUnavailable(array $tenant, string $kind): bool
    {
        return cache($this->cacheKey($tenant, 'denied_' . $kind)) === true;
    }

    private function get(array $tenant, string $path, string $kind): ?array
    {
        if ($this->isUnavailable($tenant, $kind)) {
            return null;
        }
        $token = $this->appToken($tenant);
        if ($token === null) {
            return null;
        }

        $response = service('curlrequest', ['timeout' => 10, 'http_errors' => false])->get(self::GRAPH . $path, [
            'headers' => ['Authorization' => 'Bearer ' . $token, 'ConsistencyLevel' => 'eventual'],
        ]);
        if ($response->getStatusCode() === 200) {
            return json_decode($response->getBody(), true) ?: [];
        }
        if (in_array($response->getStatusCode(), [401, 403], true)) {
            // Permission missing: drop the token (it may predate a new admin
            // consent) and pause briefly before trying again.
            cache()->delete($this->cacheKey($tenant, 'apptoken'));
            cache()->save($this->cacheKey($tenant, 'denied_' . $kind), true, 120);
        }
        log_message('info', 'Directory search ({kind}) failed for {slug}: HTTP {code}', [
            'kind' => $kind, 'slug' => $tenant['slug'], 'code' => $response->getStatusCode(),
        ]);

        return null;
    }

    private function appToken(array $tenant): ?string
    {
        $key   = $this->cacheKey($tenant, 'apptoken');
        $token = cache($key);
        if (is_string($token)) {
            return $token;
        }
        $secret = model(TenantModel::class)->clientSecret($tenant);
        if ($secret === null || empty($tenant['entra_tenant_id'])) {
            return null;
        }

        $response = service('curlrequest', ['timeout' => 10, 'http_errors' => false])
            ->post('https://login.microsoftonline.com/' . rawurlencode($tenant['entra_tenant_id']) . '/oauth2/v2.0/token', [
                'form_params' => [
                    'client_id'     => $tenant['entra_client_id'],
                    'client_secret' => $secret,
                    'grant_type'    => 'client_credentials',
                    'scope'         => 'https://graph.microsoft.com/.default',
                ],
            ]);
        $body = json_decode($response->getBody(), true) ?: [];
        if ($response->getStatusCode() !== 200 || empty($body['access_token'])) {
            return null;
        }
        cache()->save($key, $body['access_token'], max(60, (int) ($body['expires_in'] ?? 3600) - 300));

        return $body['access_token'];
    }

    private function cacheKey(array $tenant, string $name): string
    {
        return 'directory_' . (int) $tenant['id'] . '_' . $name;
    }

    /**
     * Strips characters that would break the $search expression.
     */
    private static function searchTerm(string $query): string
    {
        return trim(preg_replace('/["\\\\]+/', ' ', mb_substr($query, 0, 64)));
    }
}

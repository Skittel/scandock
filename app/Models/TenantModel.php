<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Customer (tenant). Each tenant is reachable on <slug>.<baseDomain>.
 */
class TenantModel extends Model
{
    protected $table          = 'tenants';
    protected $returnType     = 'array';
    protected $useTimestamps  = true;
    protected $allowedFields  = [
        'slug', 'name', 'status', 'setup_token_hash', 'entra_tenant_id', 'entra_client_id',
        'entra_client_secret', 'admin_group_id', 'retention_days',
    ];

    public function findBySlug(string $slug): ?array
    {
        return $this->where('slug', $slug)->first();
    }

    /**
     * Finds a tenant that is still in setup by its plain setup token.
     */
    public function findBySetupToken(string $slug, string $token): ?array
    {
        return $this->where('slug', $slug)
            ->where('status', 'setup')
            ->where('setup_token_hash', hash('sha256', $token))
            ->first();
    }

    /**
     * Creates a new setup token, stores its hash and returns the plain token.
     */
    public function issueSetupToken(int $tenantId): string
    {
        $token = bin2hex(random_bytes(24));
        $this->update($tenantId, ['setup_token_hash' => hash('sha256', $token)]);

        return $token;
    }

    /**
     * Returns the decrypted Entra client secret or null if none is stored.
     */
    public function clientSecret(array $tenant): ?string
    {
        if (empty($tenant['entra_client_secret'])) {
            return null;
        }

        return service('encrypter')->decrypt(base64_decode($tenant['entra_client_secret'], true));
    }

    public static function encryptSecret(string $secret): string
    {
        return base64_encode(service('encrypter')->encrypt($secret));
    }
}

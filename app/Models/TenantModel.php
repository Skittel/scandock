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
        'slug', 'name', 'status', 'setup_token_hash', 'setup_token_encrypted', 'entra_tenant_id', 'entra_client_id',
        'entra_client_secret', 'entra_secret_expires_at', 'admin_group_id', 'retention_days',
    ];

    public function findBySlug(string $slug): ?array
    {
        return $this->where('slug', $slug)->first();
    }

    /**
     * Finds a tenant by its plain setup token. Active tenants can get a new
     * token too (e.g. to replace an expired client secret).
     */
    public function findBySetupToken(string $slug, string $token): ?array
    {
        return $this->where('slug', $slug)
            ->whereIn('status', ['setup', 'active'])
            ->where('setup_token_hash', hash('sha256', $token))
            ->first();
    }

    /**
     * Days until the client secret expires (negative when expired), or null if unknown.
     */
    public static function secretDaysLeft(array $tenant): ?int
    {
        if (empty($tenant['entra_secret_expires_at'])) {
            return null;
        }
        $today   = new \DateTimeImmutable('today');
        $expires = new \DateTimeImmutable($tenant['entra_secret_expires_at']);

        return (int) $today->diff($expires)->format('%r%a');
    }

    /**
     * Creates a new setup token, stores its hash and returns the plain token.
     */
    public function issueSetupToken(int $tenantId): string
    {
        $token = bin2hex(random_bytes(24));
        $this->update($tenantId, [
            'setup_token_hash'      => hash('sha256', $token),
            'setup_token_encrypted' => self::encryptSecret($token),
        ]);

        return $token;
    }

    /**
     * Returns the current plain setup token, or null if there is none.
     */
    public function currentSetupToken(array $tenant): ?string
    {
        if (empty($tenant['setup_token_hash']) || empty($tenant['setup_token_encrypted'])) {
            return null;
        }

        return service('encrypter')->decrypt(base64_decode($tenant['setup_token_encrypted'], true));
    }

    /**
     * Invalidates the start link.
     */
    public function clearSetupToken(int $tenantId): void
    {
        $this->update($tenantId, ['setup_token_hash' => null, 'setup_token_encrypted' => null]);
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

    /**
     * Validates an expiry date (Y-m-d) entered for a client secret.
     *
     * @return string|null error message, null if valid
     */
    public static function validateSecretExpiry(string $date): ?string
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
            return 'Bitte das Ablaufdatum des geheimen Clientschlüssels angeben.';
        }
        if ($parsed <= new \DateTimeImmutable('today')) {
            return 'Das Ablaufdatum des Clientschlüssels liegt in der Vergangenheit.';
        }
        if ($parsed > new \DateTimeImmutable('+' . config('ScanKai')->secretMaxLifetimeDays . ' days')) {
            return 'Das Ablaufdatum liegt unplausibel weit in der Zukunft. Bitte das Datum aus Entra übernehmen.';
        }

        return null;
    }

    public static function encryptSecret(string $secret): string
    {
        return base64_encode(service('encrypter')->encrypt($secret));
    }
}

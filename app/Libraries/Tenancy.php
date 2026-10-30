<?php

namespace App\Libraries;

use App\Models\TenantModel;
use Config\ScanKai;

/**
 * Resolves the tenant of the current request from the host name.
 */
class Tenancy
{
    private bool $resolved  = false;
    private ?array $tenant  = null;

    public function __construct(private readonly ScanKai $config)
    {
    }

    /**
     * Returns the slug for a host like "beecken.scankai.site", or null if the
     * host is the bare base domain or does not belong to ScanKai.
     */
    public function slugFromHost(string $host): ?string
    {
        $host   = strtolower(preg_replace('/:\d+$/', '', $host));
        $suffix = '.' . $this->config->baseDomain;
        if (! str_ends_with($host, $suffix)) {
            return null;
        }
        $slug = substr($host, 0, -strlen($suffix));

        return self::isValidSlug($slug) ? $slug : null;
    }

    /**
     * Tenant of the current request or null (base domain, unknown slug).
     */
    public function current(): ?array
    {
        if (! $this->resolved) {
            $this->resolved = true;
            $slug           = $this->slugFromHost(service('request')->getServer('HTTP_HOST') ?? '');
            $this->tenant   = $slug === null ? null : model(TenantModel::class)->findBySlug($slug);
        }

        return $this->tenant;
    }

    /**
     * Drops the cached tenant, e.g. after it was updated.
     */
    public function refresh(): void
    {
        $this->resolved = false;
    }

    public function isReservedSlug(string $slug): bool
    {
        return in_array($slug, $this->config->reservedSlugs, true);
    }

    public static function isValidSlug(string $slug): bool
    {
        return preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/', $slug) === 1;
    }

    public function hostFor(string $slug): string
    {
        return $slug . '.' . $this->config->baseDomain;
    }
}

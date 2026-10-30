<?php

namespace App\Libraries;

use App\Models\UserModel;
use Config\ScanDock;

/**
 * Demo tenant: reachable only from the operator's addresses and without
 * Microsoft sign-in (users are signed in automatically). The allowed
 * addresses are configured in .env (scandock.demoAllowedIps), never in the
 * public repository.
 */
class DemoAccess
{
    public function __construct(private readonly ScanDock $config)
    {
    }

    public function isDemo(?array $tenant): bool
    {
        return $tenant !== null && $this->config->demoSlug !== '' && $tenant['slug'] === $this->config->demoSlug;
    }

    public function isAllowed(string $ip): bool
    {
        foreach (array_filter(array_map('trim', explode(',', $this->config->demoAllowedIps))) as $range) {
            if (self::matches($ip, $range)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Signs in a demo user: the requested one (?demo_user=<upn>) or the
     * configured default (an admin).
     */
    public function signIn(array $tenant, ?string $upn): ?array
    {
        $users = model(UserModel::class)->where('tenant_id', $tenant['id']);
        $user  = $upn !== null && $upn !== ''
            ? $users->where('upn', strtolower($upn))->first()
            : $users->where('upn', $this->config->demoDefaultUser)->first();
        if ($user === null) {
            return null;
        }
        session()->regenerate(true);
        session()->set(['tenant_id' => (int) $tenant['id'], 'user_id' => (int) $user['id']]);

        return $user;
    }

    /**
     * IPv4/IPv6 address in a single address or CIDR range.
     */
    public static function matches(string $ip, string $range): bool
    {
        [$net, $bits] = array_pad(explode('/', $range, 2), 2, null);
        $ipBin  = @inet_pton($ip);
        $netBin = @inet_pton($net);
        if ($ipBin === false || $netBin === false || strlen($ipBin) !== strlen($netBin)) {
            return false;
        }
        $bits = $bits === null ? strlen($ipBin) * 8 : (int) $bits;
        $bytes = intdiv($bits, 8);
        if (substr($ipBin, 0, $bytes) !== substr($netBin, 0, $bytes)) {
            return false;
        }
        $rest = $bits % 8;
        if ($rest === 0) {
            return true;
        }
        $mask = chr((0xFF << (8 - $rest)) & 0xFF);

        return ($ipBin[$bytes] & $mask) === ($netBin[$bytes] & $mask);
    }
}

<?php

namespace App\Libraries;

use App\Models\AuditLogModel;
use App\Models\UserModel;
use CodeIgniter\HTTP\IncomingRequest;

/**
 * Writes and describes entries of the admin journal.
 *
 * Logged: configuration changes, FTP account changes, received/rejected/
 * deleted scans and operator actions. Not logged: sign-in, viewing and
 * downloading, personal preferences.
 */
class AuditLog
{
    /**
     * Action code => [category, German description with {placeholders}].
     */
    public const ACTIONS = [
        'settings.admin_group'   => ['settings', 'Admin-Gruppe geändert: {old} → {new}'],
        'settings.retention'     => ['settings', 'Aufbewahrung geändert: {old} → {new} Tage'],
        'settings.name'          => ['settings', 'Firmenname geändert: „{old}“ → „{new}“'],
        'settings.logo'          => ['settings', 'Logo hochgeladen'],
        'settings.logo_deleted'  => ['settings', 'Logo entfernt'],
        'settings.secret'        => ['settings', 'Clientschlüssel der Entra-App erneuert (läuft ab am {expires})'],
        'ftp.created'            => ['ftp', 'FTP-Konto „{account}“ angelegt ({visibility})'],
        'ftp.deleted'            => ['ftp', 'FTP-Konto „{account}“ gelöscht, dabei {scans} Scan(s) gelöscht'],
        'ftp.password'           => ['ftp', 'Neues Passwort für FTP-Konto „{account}“ erzeugt'],
        'ftp.activated'          => ['ftp', 'FTP-Konto „{account}“ aktiviert'],
        'ftp.deactivated'        => ['ftp', 'FTP-Konto „{account}“ gesperrt'],
        'ftp.visibility'         => ['ftp', 'FTP-Konto „{account}“: Sichtbarkeit {old} → {new}'],
        'ftp.member_added'       => ['ftp', 'FTP-Konto „{account}“: {type} „{member}“ hinzugefügt'],
        'ftp.member_removed'     => ['ftp', 'FTP-Konto „{account}“: {type} „{member}“ entfernt'],
        'scan.received'          => ['scans', 'Scan „{file}“ eingegangen ({account}, {size})'],
        'scan.rejected'          => ['scans', 'Datei „{file}“ abgelehnt und gelöscht ({account}): {reason}'],
        'scan.deleted'           => ['scans', 'Scan „{file}“ gelöscht ({account})'],
        'scan.expired'           => ['scans', 'Scan „{file}“ nach Ablauf der Aufbewahrung gelöscht ({account})'],
        'tenant.setup_completed' => ['system', 'Einrichtung abgeschlossen, Anmeldung über Entra aktiv'],
        'tenant.renamed'         => ['system', 'Firmenname geändert: „{old}“ → „{new}“'],
        'tenant.status'          => ['system', 'Zugang {status}'],
        'tenant.link'            => ['system', 'Neuer Start-Link erzeugt'],
    ];

    public const CATEGORIES = [
        'settings' => 'Einstellungen',
        'ftp'      => 'FTP-Konten',
        'scans'    => 'Scans',
        'system'   => 'System',
    ];

    /**
     * Records an action. Without an explicit actor the signed-in user of the
     * current web request is used, otherwise "System".
     */
    public function record(int $tenantId, string $action, array $details = [], ?string $actor = null): void
    {
        if (! isset(self::ACTIONS[$action])) {
            throw new \InvalidArgumentException('Unknown audit action ' . $action);
        }

        $userId  = null;
        $ip      = null;
        $request = service('request');
        if ($actor === null && $request instanceof IncomingRequest) {
            $user = session('user_id') ? model(UserModel::class)->find(session('user_id')) : null;
            if ($user !== null) {
                $userId = (int) $user['id'];
                $actor  = $user['name'] . ' (' . $user['upn'] . ')';
            }
            $ip = $request->getIPAddress();
        }

        model(AuditLogModel::class)->insert([
            'tenant_id' => $tenantId,
            'user_id'   => $userId,
            'actor'     => mb_substr($actor ?? 'System', 0, 190),
            'category'  => self::ACTIONS[$action][0],
            'action'    => $action,
            'details'   => json_encode($details, JSON_UNESCAPED_UNICODE),
            'ip'        => $ip,
        ]);
    }

    /**
     * German one-line description of an entry.
     */
    public static function describe(array $entry): string
    {
        $template = self::ACTIONS[$entry['action']][1] ?? $entry['action'];
        $details  = json_decode($entry['details'] ?? '{}', true) ?: [];

        return preg_replace_callback('/\{(\w+)\}/', static function ($m) use ($details) {
            $value = $details[$m[1]] ?? '–';

            return is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE);
        }, $template);
    }
}

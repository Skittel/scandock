<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Application specific settings for ScanDock.
 */
class ScanDock extends BaseConfig
{
    /**
     * Application version (major.minor.patch, each 0-999).
     */
    public string $version = '0.0.19';

    /**
     * Logo and homepage of the operator, shown in the header next to the
     * ScanDock logo. Leave the logo empty to hide it.
     */
    public string $operatorLogo = '/operator-logo.svg';
    public string $operatorName = 'Skittel';
    public string $operatorUrl  = 'https://www.skittel.de/';

    /**
     * Domain below which every tenant gets its own subdomain.
     */
    public string $baseDomain = 'scandock.cloud';

    /**
     * Subdomains that can never be used as tenant slugs.
     *
     * @var list<string>
     */
    public array $reservedSlugs = [
        'www', 'ftp', 'ftps', 'sftp', 'admin', 'api', 'mail', 'smtp', 'imap', 'pop',
        'autodiscover', 'autoconfig', 'setup', 'static', 'cdn', 'status', 'test', 'dev',
    ];

    /**
     * Connection data shown to admins for configuring the copiers.
     */
    public string $ftpHost         = 'ftp.scandock.cloud';
    public int $ftpPort            = 21;
    public int $ftpImplicitTlsPort = 990;

    /**
     * Directory that ProFTPD writes uploads into (one subdirectory per FTP account).
     */
    public string $incomingPath = '/srv/scandock/incoming';

    /**
     * Directory where ingested scans and their thumbnails are stored.
     */
    public string $storagePath = '/srv/scandock/storage';

    /**
     * Default retention for new tenants in days.
     */
    public int $defaultRetentionDays = 30;

    /**
     * Admins are warned this many days before the Entra client secret expires.
     */
    public int $secretWarningDays = 30;

    /**
     * Latest accepted expiry date of a client secret, in days from today.
     */
    public int $secretMaxLifetimeDays = 1100;

    /**
     * Minimum age in seconds of an uploaded file before it is ingested.
     * ProFTPD writes uploads under a hidden name (HiddenStores) and renames
     * them when complete, so a short delay is sufficient.
     */
    public int $ingestMinAge = 1;

    /**
     * Let's Encrypt settings for tenant certificates.
     */
    public string $letsEncryptEmail   = 'info@skywebhosting.de';
    public string $letsEncryptWebroot = '/var/www/letsencrypt';

    /**
     * Apache vhost settings for tenant sites.
     */
    public string $apacheSitesPath = '/etc/apache2/sites-available';
    public string $documentRoot    = '/var/www/scandock/public';
}

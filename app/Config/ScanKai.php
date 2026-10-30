<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Application specific settings for ScanKai.
 */
class ScanKai extends BaseConfig
{
    /**
     * Application version (major.minor.patch, each 0-999).
     */
    public string $version = '0.0.8';

    /**
     * Domain below which every tenant gets its own subdomain.
     */
    public string $baseDomain = 'scankai.site';

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
    public string $ftpHost         = 'ftp.scankai.site';
    public int $ftpPort            = 21;
    public int $ftpImplicitTlsPort = 990;

    /**
     * Directory that ProFTPD writes uploads into (one subdirectory per FTP account).
     */
    public string $incomingPath = '/srv/scankai/incoming';

    /**
     * Directory where ingested scans and their thumbnails are stored.
     */
    public string $storagePath = '/srv/scankai/storage';

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
     * Guards against files that are still being written.
     */
    public int $ingestMinAge = 10;

    /**
     * Let's Encrypt settings for tenant certificates.
     */
    public string $letsEncryptEmail   = 'info@skywebhosting.de';
    public string $letsEncryptWebroot = '/var/www/letsencrypt';

    /**
     * Apache vhost settings for tenant sites.
     */
    public string $apacheSitesPath = '/etc/apache2/sites-available';
    public string $documentRoot    = '/var/www/scankai/public';
}

# ScanKai

Scan-to-cloud gateway for copiers and multifunction printers.

Many copiers cannot scan to SharePoint or to SMB shares in the cloud, and a lot
of them only speak plain FTP. ScanKai gives every customer a web portal on its
own subdomain (`<slug>.scankai.site`). Copiers upload PDFs via FTP or FTPS,
users sign in with their Microsoft 365 account (Entra ID), preview and download
the scans. Scans are deleted automatically after a configurable number of days
(default 30) or manually.

## Features

- Multi-tenant: one subdomain per customer, Let's Encrypt certificate per host
- Tenant onboarding through a one-time start link with a step-by-step guide for
  the Entra app registration; the link stops working after the first
  successful sign-in
- Sign-in with Microsoft Entra ID (OpenID Connect, authorization code + PKCE)
- All users are admins until an Entra admin group is configured
- FTP accounts per copier: plain FTP, explicit FTPS (port 21) and implicit FTPS
  (port 990); upload-only (no listing, download, rename or delete), PDF only
- Access to scans per FTP account for persons (UPN) and Entra groups
- PDF thumbnails, inline preview, download, delete
- Automatic deletion after the retention period
- Warning for admins 30 days before the Entra client secret expires; admins
  renew the secret in the settings (checked against Microsoft before saving)

## Stack

CodeIgniter 4 · PHP 8.4+ · Apache · MariaDB · ProFTPD (mod_sql, mod_tls) ·
poppler-utils · certbot

## Operations

```bash
scankai tenant:create <slug> --name "Customer"   # vhost + certificate + start link
scankai tenant:link <slug>                       # show current start link
scankai tenant:link-renew <slug>                 # new start link (tenant stays active)
scankai tenant:list
scankai tenant:status <slug> disable|enable
scankai scans:ingest                             # run by systemd timer every 20 s
scankai scans:purge                              # run by systemd timer daily
```

The static landing page for the base domain and `www` lives in [`landing/`](landing/).
The logo sources are in [`resources/logo/`](resources/logo/).

`scankai` is a wrapper that runs `php spark` as `www-data`. Server configuration
(ProFTPD, AppArmor, systemd units, vhost helper, fail2ban) lives in
[`deploy/`](deploy/).

## Versioning

`major.minor.patch`, each part 0–999. Current version: see `app/Config/ScanKai.php`.

## License

MIT with attribution – free for private and commercial use. If you use ScanKai,
reference https://github.com/Skittel/scankai in your README. See [LICENSE](LICENSE).

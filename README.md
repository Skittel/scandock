# ScanDock

Scan-to-cloud gateway for copiers and multifunction printers.

Many copiers cannot scan to SharePoint or to SMB shares in the cloud, and a lot
of them only speak plain FTP. ScanDock gives every customer a web portal on its
own subdomain (`<slug>.scandock.cloud`). Copiers upload PDFs via FTP or FTPS,
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
  (port 990); upload-only (no listing, download, rename or delete); max. 50 MB
  per file, enforced by the FTP server (aborted uploads are reported like other
  rejected files; typical scans are 0.5–5 MB, ScanDock is not a file sharing
  platform); file names are
  normalised (umlauts transliterated, e.g. ä → ae, ß → ss; only a-z, A-Z,
  0-9; max. 30 characters, longer names are cut to 26 characters plus a
  4-digit counter; extension max. 4 characters)
- Accepted files: PDF, JPEG, PNG, TIFF. Every upload is inspected: content
  must match the extension, no HTML/script code (also inside compressed PDF
  streams), no active PDF content (JavaScript, embedded files, launch
  actions, …), file must be readable. Rejected files are deleted and shown
  with the reason for one hour
- FTP accounts are public (all users see the scans) or private (only assigned
  persons and Entra groups, with directory autocomplete); admin rights do not
  grant access to private scans
- Customer logo and company name in the header (set by admins)
- Placeholder with file name and status while an upload is running or being
  processed; the scan page refreshes itself
- Scans, thumbnails and TIFF renditions are encrypted at rest (libsodium
  secretstream, one key per tenant, keys wrapped with the app key); plaintext
  intermediates only in RAM (/dev/shm)
- Random public IDs in all URLs (no database IDs)
- Disk guard: below 10 GB free space the FTP server is stopped and all users
  see a warning; receiving resumes automatically at 11 GB
- Admin journal per tenant: configuration and FTP account changes, received,
  rejected and deleted scans, operator actions (no sign-ins/downloads)
- PDF thumbnails, inline preview, download, delete; list view with multi-select
  for deleting or downloading as ZIP
- Automatic deletion after the retention period
- Warning for admins 30 days before the Entra client secret expires; admins
  renew the secret in the settings (checked against Microsoft before saving)

## Stack

CodeIgniter 4 · PHP 8.4+ · Apache · MariaDB · ProFTPD (mod_sql, mod_tls) ·
poppler-utils · certbot

## Operations

```bash
scandock tenant:create <slug> --name "Customer"   # vhost + certificate + start link
scandock tenant:link <slug>                       # show current start link
scandock tenant:link-renew <slug>                 # new start link (tenant stays active)
scandock tenant:list
scandock tenant:rename <slug> "Display name"
scandock tenant:status <slug> disable|enable
scandock scans:ingest                             # run by the upload watcher (inotify) right
                                                 # after each upload, timer every 20 s as fallback
scandock scans:purge                              # run by systemd timer daily
scandock scans:encrypt                            # encrypt scans stored before 0.0.18
```

The static landing page for the base domain and `www` lives in [`landing/`](landing/).
The logo sources are in [`resources/logo/`](resources/logo/).

Backups: `deploy/sbin/scandock-backup` (cron daily 03:00, `deploy/cron/scandock-backup`)
writes `/backup/<yyyymmdd-hhiiss>.tgz` with database dump, scans, project (incl.
`.env`) and server configuration, plus a log with the same name and a summary. The newest 30 backups are kept.

`scandock` is a wrapper that runs `php spark` as `www-data`. Server configuration
(ProFTPD, AppArmor, systemd units, vhost helper, fail2ban) lives in
[`deploy/`](deploy/).

## Versioning

`major.minor.patch`, each part 0–999. Current version: see `app/Config/ScanDock.php`.

## License

MIT with attribution – free for private and commercial use. If you use ScanDock,
reference https://github.com/Skittel/scandock in your README. See [LICENSE](LICENSE).

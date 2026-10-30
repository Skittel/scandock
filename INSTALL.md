# Installing ScanDock on an empty Ubuntu server

> **⚠️ UNTESTED.** This guide was written from the configuration of the
> production server (Ubuntu 26.04 LTS, ScanDock 0.0.29). It has **not** been
> run end-to-end on a fresh machine yet. Expect to adjust details; please
> report or fix anything that does not work.

ScanDock is meant for copiers and multifunction printers (typical scans
0.5–5 MB, limit 50 MB). It is not a file sharing platform.

All server configuration files referenced below live in [`deploy/`](deploy/).
Commands are run as a user with `sudo`.

---

## 0. Prerequisites

- Ubuntu 26.04 LTS (PHP 8.5 from the distribution; ScanDock needs PHP ≥ 8.4
  with `sodium` and `intl`), root access, public IPv4 (IPv6 optional).
- A domain with DNS records pointing to the server:
  `example.com`, `*.example.com` (A and, if available, AAAA).
  Tenants are reached at `<slug>.example.com`, FTP at `ftp.example.com`.
- An e-mail address for Let's Encrypt.
- Per customer: a Microsoft Entra tenant in which an admin can create an
  app registration (the start link guides through it).

Throughout this guide replace:

| Placeholder | Meaning |
|---|---|
| `example.com` | your base domain (production: `scandock.cloud`) |
| `admin@example.com` | Let's Encrypt contact |
| `203.0.113.10` | IP addresses allowed to administrate the server (SSH etc.) |

## 1. Packages

```bash
sudo apt-get update
sudo apt-get install -y \
  apache2 libapache2-mod-php8.5 \
  php8.5 php8.5-cli php8.5-mysql php8.5-intl php8.5-mbstring php8.5-curl \
  php8.5-xml php8.5-zip php8.5-gd \
  mariadb-server composer git unzip \
  certbot \
  proftpd-core proftpd-mod-mysql \
  poppler-utils qpdf libtiff-tools imagemagick inotify-tools \
  fail2ban ufw
```

## 2. System user and directories

```bash
sudo groupadd scandock
sudo useradd -r -g scandock -d /srv/scandock -s /usr/sbin/nologin scandock
sudo usermod -aG scandock www-data

sudo mkdir -p /srv/scandock/incoming /srv/scandock/storage /var/www/letsencrypt/.well-known/acme-challenge /backup
sudo chown scandock:scandock /srv/scandock/incoming && sudo chmod 2770 /srv/scandock/incoming
sudo chown www-data:www-data /srv/scandock/storage  && sudo chmod 0750 /srv/scandock/storage
sudo chmod 0755 /srv/scandock
sudo chmod 0700 /backup

id scandock   # note uid and gid, needed for ProFTPD (step 8)
```

## 3. Code

```bash
sudo mkdir -p /var/www/scandock && sudo chown "$USER":www-data /var/www/scandock
git clone https://github.com/Skittel/scandock.git /var/www/scandock
cd /var/www/scandock
composer install --no-dev --optimize-autoloader
sudo chown -R www-data:www-data writable
sudo find writable -type d -exec chmod 2775 {} + ; sudo find writable -type f -exec chmod 664 {} +
```

### Adapt the domain

The production domain `scandock.cloud` appears in these files – replace it
with your domain:

```bash
grep -rln 'scandock\.cloud' app/Config deploy
# app/Config/ScanDock.php, deploy/apache/000-redirect-http.conf,
# deploy/apparmor/local-proftpd, deploy/proftpd/proftpd.conf, deploy/sbin/scandock-site
```

In `app/Config/ScanDock.php` also check `letsEncryptEmail`, `operatorLogo`,
`operatorName`, `operatorUrl` and the limits (`maxUploadBytes`,
`minFreeBytes`, `defaultRetentionDays`). In `deploy/sbin/scandock-site` set
`EMAIL`, in `deploy/fail2ban/scandock.conf` replace `ADMIN_IPS_HERE` with
your admin addresses.

## 4. Database

```bash
APP_PW=$(openssl rand -base64 30 | tr -d '/+=' | cut -c1-32)
FTP_PW=$(openssl rand -base64 30 | tr -d '/+=' | cut -c1-32)
sudo mariadb <<SQL
CREATE DATABASE scandock CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'scandock'@'localhost' IDENTIFIED BY '$APP_PW';
GRANT ALL PRIVILEGES ON scandock.* TO 'scandock'@'localhost';
CREATE USER 'scandock_ftp'@'127.0.0.1' IDENTIFIED BY '$FTP_PW';
FLUSH PRIVILEGES;
SQL
sudo install -d -m 700 /root/.secrets
printf 'DB_APP_USER=scandock\nDB_APP_PASS=%s\nDB_FTP_USER=scandock_ftp\nDB_FTP_PASS=%s\n' "$APP_PW" "$FTP_PW" \
  | sudo tee /root/.secrets/scandock-db.env >/dev/null
sudo chmod 600 /root/.secrets/scandock-db.env
```

ProFTPD connects via TCP to `127.0.0.1` (a Unix socket does not work after
the chroot), therefore the FTP user is created for `127.0.0.1`.

## 5. Application configuration (`.env`)

```bash
cd /var/www/scandock
KEY=$(php -r 'echo "hex2bin:".bin2hex(random_bytes(32));')
cat > .env <<EOF
CI_ENVIRONMENT = production

app.baseURL = 'https://example.com/'
app.forceGlobalSecureRequests = true

database.default.hostname = localhost
database.default.database = scandock
database.default.username = scandock
database.default.password = $APP_PW
database.default.DBDriver = MySQLi
database.default.charset = utf8mb4
database.default.DBCollat = utf8mb4_unicode_ci

encryption.key = $KEY

session.driver = 'CodeIgniter\Session\Handlers\FileHandler'
session.cookieName = 'scandock_session'
session.expiration = 28800
cookie.secure = true
cookie.httponly = true
cookie.samesite = 'Lax'
EOF
chmod 640 .env && sudo chgrp www-data .env
```

> **The `encryption.key` protects all stored scans** (each tenant's file key
> is wrapped with it). Without it the scans cannot be decrypted. Store a copy
> outside the server (e.g. password manager).

Create the schema and give the FTP user read access to its view:

```bash
sudo -u www-data php spark migrate
sudo mariadb -e "GRANT SELECT ON scandock.ftp_users TO 'scandock_ftp'@'127.0.0.1'; FLUSH PRIVILEGES;"
```

## 6. Apache and PHP

```bash
D=/var/www/scandock/deploy
sudo a2enmod rewrite ssl headers
sudo a2dissite 000-default
sudo install -m 644 $D/apache/000-redirect-http.conf /etc/apache2/sites-available/
sudo install -m 644 $D/apache/zz-scandock-security.conf /etc/apache2/conf-available/
sudo a2ensite 000-redirect-http && sudo a2enconf zz-scandock-security
sudo install -m 644 $D/apache/php-99-scandock.ini /etc/php/8.5/apache2/conf.d/99-scandock.ini
sudo install -m 644 $D/apache/php-99-scandock.ini /etc/php/8.5/cli/conf.d/99-scandock.ini
sudo systemctl restart apache2
```

Port 80 only answers Let's Encrypt challenges and redirects everything else
to HTTPS. Each host gets its own HTTPS vhost (next step). The security conf
is named `zz-…` so it loads after Ubuntu's `security.conf`.

## 7. Helper scripts, CLI and certificates

```bash
sudo install -m 755 $D/sbin/scandock-site /usr/local/sbin/
sudo install -m 755 $D/sbin/scandock /usr/local/bin/        # CLI wrapper: runs spark as www-data
sudo install -m 440 $D/sudoers/scandock /etc/sudoers.d/ && sudo visudo -cf /etc/sudoers.d/scandock
sudo install -m 755 $D/letsencrypt/scandock-reload.sh /etc/letsencrypt/renewal-hooks/deploy/

# landing page (base domain + www) and FTP certificate
sudo /usr/local/sbin/scandock-site add example.com
sudo /usr/local/sbin/scandock-site add www.example.com
sudo certbot certonly --webroot -w /var/www/letsencrypt -d ftp.example.com \
  --email admin@example.com --agree-tos --no-eff-email -n
```

`scandock-site` creates the vhost and the certificate for one host; tenant
hosts are added automatically by `scandock tenant:create`. The www-data user
may run exactly this script via sudo.

## 8. FTP server (ProFTPD)

```bash
source <(sudo cat /root/.secrets/scandock-db.env)
sudo cp /etc/proftpd/proftpd.conf /etc/proftpd/proftpd.conf.dist
sed "s/CHANGE_ME_PASSWORD/$DB_FTP_PASS/" $D/proftpd/proftpd.conf | sudo tee /etc/proftpd/proftpd.conf >/dev/null
sudo chmod 600 /etc/proftpd/proftpd.conf
```

In `/etc/proftpd/proftpd.conf` replace `999, 1001` in both `SQLNamedQuery`
lines with the uid and gid of the `scandock` user (step 2).

Ubuntu confines ProFTPD and qpdf with AppArmor; ScanDock needs a few
additional rules:

```bash
sudo install -m 644 $D/apparmor/local-proftpd /etc/apparmor.d/local/proftpd
sudo install -m 644 $D/apparmor/local-qpdf    /etc/apparmor.d/local/qpdf
sudo apparmor_parser -r /etc/apparmor.d/proftpd /etc/apparmor.d/qpdf
sudo proftpd -t && sudo systemctl restart proftpd
```

What the configuration does: virtual users from the database view, upload
only (no listing content, download, rename, delete), plain FTP and explicit
FTPS on port 21, implicit FTPS on 990, passive ports 40000–40100, 50 MB per
file, brute force protection (mod_ban) and an upload log for aborted uploads.

## 9. Background services

```bash
sudo install -m 755 $D/sbin/scandock-upload-watch $D/sbin/scandock-diskguard \
  $D/sbin/scandock-ftplog $D/sbin/scandock-backup /usr/local/sbin/
sudo install -m 644 $D/systemd/scandock-* /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now scandock-upload-watch.service \
  scandock-ingest.timer scandock-purge.timer scandock-diskguard.timer scandock-ftplog.timer
sudo install -m 644 $D/cron/scandock-backup /etc/cron.d/scandock-backup
sudo install -m 644 $D/fail2ban/scandock.conf /etc/fail2ban/jail.d/ && sudo systemctl restart fail2ban
sudo install -m 644 $D/logrotate/scandock-proftpd /etc/logrotate.d/scandock-proftpd
```

| Service | Purpose |
|---|---|
| `scandock-upload-watch` | imports an upload ~1 s after it is complete (inotify) |
| `scandock-ingest.timer` | fallback import every 20 s |
| `scandock-purge.timer` | daily 03:15: retention, failed deletions, orphaned files, old journal entries, application logs > 90 days |
| `scandock-diskguard.timer` | every minute: < 10 GB free → stop FTP, warning for all users; ≥ 11 GB → resume |
| `scandock-ftplog.timer` | every 15 s: reports uploads aborted by the FTP server (> 50 MB) |
| cron `scandock-backup` | daily 03:00: backup to `/backup`, keeps 30 |

## 10. Firewall

```bash
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow from 203.0.113.10          # admin access (repeat per address/network)
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw allow 21/tcp
sudo ufw allow 990/tcp
sudo ufw allow 40000:40100/tcp
sudo ufw enable
```

Make sure your own SSH connection comes from an allowed address before
enabling the firewall.

## 11. First customer

```bash
scandock tenant:create <slug> --name "Company name"
```

This issues the certificate, creates the vhost and prints a **start link**.
The customer's Entra admin opens it and follows the wizard (app registration,
redirect URI, client secret, permissions). After the first successful sign-in
the link expires. Then an admin creates FTP accounts in the web interface and
enters host, port, user name and password on the copier.

Other commands: `scandock list` (group ScanDock), e.g. `tenant:list`,
`tenant:link`, `tenant:link-renew`, `tenant:rename`, `tenant:status`.

## 12. Checks

```bash
systemctl is-active apache2 proftpd mariadb fail2ban scandock-upload-watch \
  scandock-ingest.timer scandock-purge.timer scandock-diskguard.timer scandock-ftplog.timer
sudo certbot renew --dry-run
curl -I https://example.com/
sudo /usr/local/sbin/scandock-backup && sudo ls /backup
```

Upload a small PDF with the credentials of an FTP account; it should appear
on the scan page within a few seconds.

## 13. Updating

```bash
cd /var/www/scandock
git pull
composer install --no-dev --optimize-autoloader
sudo -u www-data php spark migrate
```

Compare `deploy/` with the installed copies and reinstall changed files
(then `daemon-reload` / restart the affected service).

## 14. Restore from a backup (outline)

1. Install packages, user and directories as above.
2. Extract the archive to `/` (contains code with `.env`, scans, configuration,
   certificates, secrets).
3. `sudo mariadb < database.sql` and `sudo mariadb < database-users.sql`
   (both inside the archive).
4. `sudo apparmor_parser -r /etc/apparmor.d/proftpd /etc/apparmor.d/qpdf`,
   `sudo systemctl daemon-reload`, enable the services (step 9), restart
   Apache and ProFTPD.

# Betrieb und CLI (nur Betreiber)

Die CLI ist nur für den Betreiber des Servers zugänglich (SSH), nicht für Kunden.

Alle Befehle per SSH auf dem Server ausführen. `scandock` ist ein Wrapper, der `php spark` als Benutzer `www-data` startet (`/usr/local/bin/scandock`).

## Kunden

| Befehl | Zweck |
|---|---|
| `scandock tenant:create <slug> --name "Firma"` | Legt einen Kunden an: Let's-Encrypt-Zertifikat, Apache-vHost, Datenbankeintrag. Gibt den Start-Link aus. |
| `scandock tenant:link <slug>` | Zeigt den aktuellen Start-Link an. |
| `scandock tenant:link-renew <slug>` | Erzeugt einen neuen Start-Link, der alte wird ungültig. Bei aktiven Kunden laufen Anmeldung und FTP weiter, z. B. zum Erneuern eines abgelaufenen Clientschlüssels. |
| `scandock tenant:list` | Übersicht aller Kunden: Status, Benutzer, FTP-Konten, Scans, Aufbewahrung, Ablauf des Clientschlüssels (gelb: ≤ 30 Tage, rot: abgelaufen). |
| `scandock tenant:rename <slug> "Firmenname"` | Ändert den Anzeigenamen (Firmenname) eines Kunden. Kunden-Admins können ihn auch selbst unter *Einstellungen → Firma* ändern. |
| `scandock tenant:status <slug> disable` | Sperrt einen Kunden (Weboberfläche und FTP). |
| `scandock tenant:status <slug> enable` | Entsperrt einen Kunden. |

Regeln für Slugs: `a-z`, `0-9`, `-`, max. 63 Zeichen. Gesperrt sind u. a. `www`, `ftp`, `admin`, `api`, `mail`, `setup`, `test`.

Der Start-Link wird ungültig, sobald sich jemand darüber erfolgreich mit Entra angemeldet hat.

## Scans

| Befehl | Zweck |
|---|---|
| `scandock scans:ingest` | Importiert fertige FTP-Uploads. Läuft automatisch direkt nach jedem Upload (Dienst `scandock-upload-watch`), zusätzlich alle 20 Sekunden als Rückfallebene (`scandock-ingest.timer`). |
| `scandock scans:encrypt` | Verschlüsselt Scans, die noch unverschlüsselt gespeichert sind (Umstellung auf 0.0.18). Prüft jede Datei per Prüfsumme, bevor das Original gelöscht wird. Wirkt auf **alle** Kunden. |
| `scandock scans:normalize-names --tenant <slug> [--dry-run]` | Benennt vorhandene Scans nach den Dateinamen-Regeln um (Umlaute, nur a–z/A–Z/0–9, max. 30 Zeichen). Jede Umbenennung steht im Journal. |
| `scandock scans:purge` | Löscht Scans nach Ablauf der Aufbewahrungsfrist. Läuft täglich um 03:15 Uhr (`scandock-purge.timer`). |

## Datenbank

| Befehl | Zweck |
|---|---|
| `scandock migrate` | Spielt neue Datenbank-Migrationen ein (nach einem Update). |
| `scandock migrate:status` | Zeigt den Stand der Migrationen. |

## Server (root)

| Befehl | Zweck |
|---|---|
| `sudo /usr/local/sbin/scandock-site add <host>` | vHost und Zertifikat für einen Hostnamen anlegen (wird von `tenant:create` aufgerufen). |
| `sudo /usr/local/sbin/scandock-site remove <host>` | vHost und Zertifikat entfernen. |
| `systemctl list-timers 'scandock-*'` | Status der Timer. |
| `journalctl -u scandock-ingest -n 50` | Log des Imports. |
| `journalctl -t scandock-ftplog` / `sudo tail /var/log/proftpd/stor.log` | Upload-Protokoll des FTP-Servers. Abbrüche wegen mehr als 50 MB (Code 552) meldet `scandock-ftplog` alle 15 s an ScanDock. |
| `journalctl -t scandock-diskguard` | Log des Speicherwächters (Sperre/Freigabe des FTP-Empfangs). |
| `cat /run/scandock/ftp-blocked` | Existiert, solange der Empfang wegen Speichermangel gesperrt ist. |
| `sudo /usr/local/sbin/scandock-diskguard` | Speicherwächter sofort ausführen (läuft sonst jede Minute; Sperre unter 10 GB, Freigabe ab 11 GB). |
| `systemctl status scandock-upload-watch` | Status des Dateiwächters, der den Import sofort startet. |
| `sudo tail -f /var/log/proftpd/proftpd.log` | FTP-Log (Anmeldungen, Fehler). |
| `sudo fail2ban-client status proftpd` | Gesperrte IPs (FTP). |
| `sudo fail2ban-client set proftpd unbanip <ip>` | IP entsperren. |

## Datensicherung

| Befehl / Ort | Zweck |
|---|---|
| `sudo /usr/local/sbin/scandock-backup` | Sicherung sofort ausführen |
| `/etc/cron.d/scandock-backup` | automatisch täglich um 03:00 Uhr |
| Aufbewahrung | die letzten 30 Sicherungen; ältere werden nach einer erfolgreichen Sicherung gelöscht |
| `/backup/<yyyymmdd-hhiiss>.tgz` | Archiv: Datenbank-Dump inkl. DB-Benutzer, Scans (verschlüsselt), Projekt inkl. `.env` (Schlüssel!), Server-Konfiguration, Zertifikate, Zugangsdaten |
| `/backup/<yyyymmdd-hhiiss>.log` | Protokoll aller Schritte mit Zusammenfassung (Status OK/FEHLER, Größe, Anzahl Dateien, Scans, Dauer, freier Platz, SHA-256) |
| `sudo tail -12 /backup/$(sudo ls /backup \| grep log$ \| tail -1)` | Zusammenfassung der letzten Sicherung |

**Wiederherstellen** (Kurzform): Archiv nach `/` entpacken, `mariadb < database.sql`, `mariadb < database-users.sql`, Dienste neu starten. Ohne die `.env` aus dem Archiv lassen sich die Scans nicht entschlüsseln. Archiv und Schlüssel gehören zusammen und müssen entsprechend geschützt werden.

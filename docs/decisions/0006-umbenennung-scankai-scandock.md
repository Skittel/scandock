# Entscheidung: Umbenennung ScanKai → ScanDock

*2026-10-30 · Version 0.0.19 · Vorgabe des Projektinhabers*

| | alt | neu |
|---|---|---|
| Produktname | ScanKai | **ScanDock** |
| Domain | scankai.site | **scandock.cloud** (Kunden: `<slug>.scandock.cloud`) |
| FTP-Host | ftp.scankai.site | **ftp.scandock.cloud** |
| Code | /var/www/scankai | /var/www/scandock |
| Daten | /srv/scankai | /srv/scandock |
| Systembenutzer/-gruppe | scankai | scandock (uid/gid unverändert) |
| Datenbank | scankai (Benutzer scankai, scankai_ftp) | scandock (Benutzer scandock, scandock_ftp; neue Passwörter in `/root/.secrets/scandock-db.env`) |
| CLI | `scankai …` | `scandock …` |
| Dienste | scankai-* | scandock-ingest, scandock-purge, scandock-upload-watch |
| Hilfsskript | /usr/local/sbin/scankai-site | /usr/local/sbin/scandock-site |
| Config-Klasse | Config\ScanKai | Config\ScanDock |
| Server-Hostname | eu01scankai | eu01scandock (eu01.scandock.cloud) |
| Wiki-Bücher | scankai, scankai-ZE7 | scandock, scandock-UCB |
| GitHub | Skittel/scankai | Skittel/scandock (Umbenennung durch Projektinhaber) |

## Ablauf
1. Vollständige Sicherung nach `/root/backup/pre-rename-*` (DB-Dump und Dateien).
2. Dienste gestoppt, Benutzer und Gruppe umbenannt, Verzeichnisse verschoben, Datenbank kopiert.
3. Code umbenannt (danach kommt „scankai“ nur noch in der Dokumentation vor), Logo mit „Dock“ neu erzeugt.
4. Migration legt die FTP-View mit neuem Pfad und neuem Definer neu an.
5. Neue Let's-Encrypt-Zertifikate für scandock.cloud, www, ftp und Kunde 1.
6. Test: Web, Weiterleitung zu Entra, FTPS über IPv4/IPv6, Import, Prüfung, alle 25 bestehenden verschlüsselten Scans lesbar.
7. Altes entfernt: vHosts, Zertifikate, Datenbank und DB-Benutzer, Skripte, Units.

Die Verschlüsselungsschlüssel sind unverändert (gleiche `.env`). Die FTP-Benutzernamen und -Passwörter bleiben gleich, nur der Host ist neu.

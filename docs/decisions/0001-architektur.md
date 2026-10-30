# Entscheidung: Architektur ScanKai 0.0.1

*2026-10-30*

## FTP-Server: ProFTPD mit MariaDB
- **Gewählt:** ProFTPD 1.3.9 mit mod_sql (virtuelle Benutzer aus der Datenbank) und mod_tls.
- **Begründung:** Kann unverschlüsseltes FTP, explizites FTPS (Port 21) und implizites FTPS (Port 990). Konten verwaltet die Web-App direkt in der Datenbank, ohne Systembenutzer. pure-ftpd kann kein implizites FTPS.
- ProFTPD liest nur die View `ftp_users` mit einem eigenen DB-Benutzer, der nur Leserechte hat. Gesperrte Konten und Kunden, die nicht aktiv sind, können sich nicht anmelden.
- Passwörter: SHA-512-crypt für ProFTPD. Zusätzlich verschlüsselt gespeichert, damit Admins sie anzeigen können (Vorgabe).

## FTP nur zum Hochladen
- Erlaubt sind nur STOR/STOU sowie Verzeichniswechsel. Lesen, Löschen, Umbenennen, Verzeichnisse anlegen usw. sind gesperrt.
- LIST liefert eine leere Liste ohne Dateinamen. Das ist besser verträglich mit Kopierern, die nach dem Upload ein LIST schicken.
- Nur `*.pdf`, max. 200 MB pro Datei, kein Überschreiben, Uploads werden als versteckte Datei geschrieben, bis sie vollständig sind.
- Schutz vor Brute-Force: mod_ban (3 Fehlversuche in 10 Minuten führen zu 1 Stunde Sperre) und fail2ban.
- Begründung: Bei unverschlüsseltem FTP kann das Passwort mitgelesen werden. Ein Angreifer kann damit keine Scans abrufen.

## FTP-Host
- Gemeinsamer Host `ftp.scankai.site` mit eigenem Let's-Encrypt-Zertifikat, da ProFTPD Zertifikate pro Hostname nur schlecht unterstützt. Der Slug `ftp` und weitere Namen sind für Kunden gesperrt.

## Import der Scans
- Ein systemd-Timer importiert alle 20 Sekunden fertige Uploads: PDF-Signatur prüfen, in den Speicher kopieren, Vorschaubild (pdftoppm) erzeugen, Datenbankeintrag schreiben und erst dann die Quelle löschen. So geht bei einem Fehler kein Scan verloren.
- Ein täglicher Timer um 03:15 löscht Scans nach Ablauf der Frist (Standard 30 Tage, pro Kunde einstellbar).

## Anmeldung: Entra ID
- Jeder Kunde legt eine eigene App-Registrierung für einen einzelnen Mandanten an. Der Start-Link führt mit Anleitung durch die Einrichtung.
- OpenID Connect mit Autorisierungscode und PKCE. Geprüft werden Signatur, Aussteller, Zielgruppe, Mandant und Nonce des ID-Tokens.
- Gruppen kommen aus dem `groups`-Claim (Tokenkonfiguration). Bei mehr als 200 Gruppen übermittelt Entra keine Liste, dann gibt es einen Hinweis.
- Die erste erfolgreiche Anmeldung aktiviert den Kunden und macht den Start-Link ungültig. Gespeichert wird nur ein Hash des Tokens.

## Rechte
- Ohne Admin-Gruppe ist jeder angemeldete Benutzer Admin. Sobald eine Gruppe eingetragen ist, sind nur deren Mitglieder Admins. Ein Schutz verhindert, dass man sich versehentlich selbst aussperrt.
- Admins sehen alle Scans. Andere Benutzer sehen nur Scans der FTP-Konten, denen sie als Person (UPN) oder über eine Entra-Gruppe zugeordnet sind.

## Server
- Pro Hostname ein Apache-vHost und ein Let's-Encrypt-Zertifikat (HTTP-01, webroot). Port 80 beantwortet nur die Prüfungen von Let's Encrypt und leitet alles andere auf HTTPS weiter.
- Privilegierte Aktionen erledigt nur `/usr/local/sbin/scankai-site`, das den Hostnamen streng prüft. www-data darf genau dieses Skript per sudo ausführen.
- Firewall öffentlich: 80, 443, 21, 990, 40000–40100/tcp (freigegeben durch den Projektinhaber).
- Server-Konfiguration als Kopie im Repo unter `deploy/`.

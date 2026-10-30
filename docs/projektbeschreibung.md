# Projektbeschreibung ScanDock

*Stand: 2026-10-30 · Version 0.0.29 (erster Kunde aktiv; bis 0.0.18 hieß das Projekt ScanKai)*

## Problem
Kunden mit Kopierern/MFPs ziehen in die Cloud (Microsoft 365). Selbst neue Geräte können nicht nach SharePoint oder auf SMB-Freigaben in der Cloud scannen. Viele Geräte können außerdem nur unverschlüsseltes FTP.

## Lösung
ScanDock ist ein mandantenfähiger Scan-Eingang:
1. Am Kopierer wird ein FTP-Ziel eingerichtet (FTP unverschlüsselt oder FTPS), mit Zugangsdaten von ScanDock.
2. Gescannte Dokumente (PDF, JPEG, PNG, TIFF) landen auf dem ScanDock-Server. Jede Datei wird auf echten Inhalt und Schadcode (HTML/Skript, aktive PDF-Inhalte) geprüft, andere Dateien werden gelöscht.
3. Benutzer melden sich mit ihrem Microsoft-365-Konto (Entra ID) unter `<slug>.scandock.cloud` an, sehen die PDFs mit Vorschau und laden sie herunter.
4. Dateien werden nach n Tagen automatisch oder vorher manuell gelöscht.

## Abgrenzung
ScanDock ist für **Kopierer und Multifunktionsgeräte** gedacht, nicht als Plattform zum Dateiaustausch. Übliche Scans sind 0,5 bis 5 MB groß, die Grenze von 50 MB liegt beim Zehnfachen.

## Mandanten
- Jeder Kunde hat einen Slug, z. B. `kunde.scandock.cloud` .
- Der Betreiber legt Kunden per CLI über SSH an. Dabei entstehen der vHost mit Let's-Encrypt-Zertifikat und ein Start-Link.
- Der Start-Link führt den Kunden durch die Anbindung an Entra: Anleitung, die einzutragenden Werte (z. B. Redirect-URI) und die Eingabe der Werte aus Entra (Tenant-ID, Client-ID, Secret).
- Sobald die Anmeldung über Entra einmal funktioniert hat, ist der Start-Link ungültig.

## Rollen
- Anfangs sind alle Benutzer Admins.
- In den Einstellungen kann eine Entra-Gruppe als Admin-Gruppe festgelegt werden.
- Admins legen FTP-Konten an (Anzeige: Host, Port, Zugangsdaten). Ein Konto ist **öffentlich** (alle sehen die Scans) oder **privat** (nur zugeordnete Personen und Entra-Gruppen, Auswahl mit Autovervollständigung).
- Admins verwalten alle Einstellungen, sehen private Scans aber nur, wenn sie selbst zugeordnet sind.

## Weitere Funktionen
- Admin-Journal mit allen Änderungen und Datei-Vorgängen (ohne Anmeldungen/Downloads), nur für Admins.
- Listenansicht mit Mehrfachauswahl zum Löschen oder Herunterladen als ZIP.
- Scans verschlüsselt gespeichert (ein Schlüssel pro Kunde), Pseudo-IDs in allen URLs.
- Tägliche lokale Datensicherung (letzte 30), externe Sicherung als ToDo.
- Maximal 50 MB pro Datei, durchgesetzt vom FTP-Server. Abgebrochene Uploads werden wie andere abgelehnte Dateien angezeigt. Unter 10 GB freiem Speicher wird der Empfang automatisch gesperrt (Hinweis für alle Benutzer).
- Dateinamen werden normalisiert (Umlaute → ae/oe/ue/ss, nur a–z/A–Z/0–9, max. 30 Zeichen ohne Endung).

## Eckdaten
| Punkt | Wert |
|---|---|
| Domain | `scandock.cloud`, Wildcard `*.scandock.cloud` → <Server-IP> |
| SSL | Let's Encrypt, ein Zertifikat pro Hostname (HTTP-01), kein Wildcard |
| Stack | CodeIgniter 4, Apache, PHP 8.5, MariaDB 11.8, ProFTPD |
| FTP | `ftp.scandock.cloud`, Port 21 (FTP/FTPS explizit), 990 (FTPS implizit), nur Hochladen; PDF/JPEG/PNG/TIFF mit Inhaltsprüfung |
| Aufbewahrung | Standard 30 Tage, pro Kunde einstellbar |
| Code | https://github.com/Skittel/scandock (öffentlich), Code und Doku auf Englisch |
| Lizenz | MIT plus Pflicht zum Verweis auf das GitHub-Repo in der README |
| Versionierung | n.n.n, jede Stelle 0–999 |

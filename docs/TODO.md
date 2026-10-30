# ToDo

Offene Aufgaben für ScanDock. Erledigte Punkte werden mit Datum abgehakt und ins Journal übernommen.
Quelle der Punkte aus dem Review: [Auswertung ChatGPT-Review 30.10.26](reviews/2026-10-30-auswertung-chatgpt-review.md). Laut Projektinhaber werden sie **derzeit nicht benötigt** (Stand 2026-10-30).

## Offen

### Externe Datensicherung (außerhalb des Servers)
*Aufgenommen 2026-10-30 · Priorität hoch*
- Ziel außerhalb des Servers bzw. Rechenzentrums (z. B. Storage Box, S3-kompatibler Speicher, zweiter Server).
- **Verschlüsselt übertragen und ablegen**, mit einem **eigenen, unabhängig verwalteten Schlüssel**: Das Archiv enthält die `.env` mit dem Scan-Schlüssel und alle Zugangsdaten.
- **Schlüssel getrennt aufbewahren**: `encryption.key` zusätzlich an anderem Ort hinterlegen (z. B. Passwort-Manager).
- Eigene Aufbewahrung festlegen (z. B. täglich 30 Tage, monatlich 12 Monate).
- Erfolg überwachen und bei Fehlern benachrichtigen (setzt Mailversand voraus).
- Offen: Zielsystem und Zugangsdaten durch den Projektinhaber.

### Automatischer Wiederherstellungstest der Sicherungen
*Aus Review 4.2c*
Wöchentlich: Archiv in den RAM entpacken, Datenbank in eine Testdatenbank einspielen, eine Stichprobe von Scans mit dem Schlüssel aus dem Archiv entschlüsseln und mit der SHA-256 vergleichen, Ergebnis ins Log.

### Hinweis zur Backup-Aufbewahrung für Kunden
*Aus Review 4.3a*
Gelöschte Scans bleiben bis zu 30 Tage in den Sicherungen, insgesamt also bis zu ca. 60 Tage gespeichert. In die Kundeninformation (Datenschutz/AVV) aufnehmen, ggf. die Backup-Aufbewahrung verkürzen.

### Automatisierte Tests und CI
*Aus Review 6*
PHPUnit-Tests für Verschlüsselung, Import (inkl. vollem Datenträger, Abbruch, Neustart), Berechtigungen (Objekt-ID, veraltete Gruppen), Löschen/Wiederholung, Dateinamen. Dazu GitHub Actions mit PHPUnit und PHPStan bei jedem Push.

### Monitoring und Benachrichtigung
*Aus Review 7*
Überwachung von FTP-Dienst, Webanwendung, Import, Löschlauf, Datensicherung, Speicherwächter und Zertifikaten. Bei Fehlern Mail an den Betreiber. Setzt einen Mailversand (SMTP) voraus.

### Gerätestatus je FTP-Konto
*Aus Review 3.3b und 7*
Letzter erfolgreicher Upload, letzte Verbindung mit FTP oder FTPS, Hinweis bei ungewöhnlich langer Inaktivität.

### IP-Freigabeliste je FTP-Konto
*Aus Review 3.3c*
Optional erlaubte IP-Adressen je Konto (Prüfung durch ProFTPD per SQL). Senkt das Risiko mitgelesener Passwörter bei unverschlüsseltem FTP.

### Fehlgeschlagene FTP-Anmeldungen im Admin-Journal
*Aus Review 3.3d*
Sperren durch mod_ban/fail2ban zusätzlich im Journal des betroffenen Kunden anzeigen.

### Speicherquoten je Kunde
*Aus Review 5 und 7*
Begrenzung von Speicherplatz und Dokumentenanzahl pro Kunde.

### Ressourcenlimits und parallele Importe
*Aus Review 5*
Speicherlimit für alle Hilfsprogramme (pdftoppm, qpdf: systemd `MemoryMax`). Optional eine Seitengrenze. Kleine feste Anzahl paralleler Importe, damit eine große Datei eines Kunden die anderen nicht aufhält.

### Weitere Funktionstests
*Aus Review 5*
Sehr große PDF (nahe 50 MB), viele gleichzeitige Uploads, gleicher Dateiname zweimal innerhalb von ca. 1 s (wird derzeit vom FTP-Server abgelehnt), Neustart während des Imports, Microsoft Graph nicht erreichbar.

### SharePoint-/OneDrive-Export
*Aus Review 7*
Optionales Ablegen der Scans in SharePoint/OneDrive. Braucht zusätzliche Graph-Berechtigungen und ein Konzept je Kunde.

### Download-Protokoll (optional je Kunde)
*Aus Review 7*
Bewusst nicht im Journal. Nur auf Kundenwunsch zuschaltbar.

## Erledigt
- 2026-10-30 · Dateinamen: max. 30 Zeichen (sonst 26 + Zähler), Umlaute → ae/oe/ue/ss (0.0.27).
- 2026-10-30 · Dateinamen von FTP normalisieren: nur a–z, A–Z, 0–9, max. 20 Zeichen, sonst 16 + Zähler, Endung max. 4 Zeichen (0.0.26).
- 2026-10-30 · Review-Punkte 1–4: kein Datenverlust bei vollem Datenträger, verlässliches Löschen, Objekt-ID statt UPN, Höchstalter Gruppen, Temp-Dateien im RAM, Composer-Metadaten; Speicherwächter (10 GB), max. 50 MB pro Datei (0.0.25).
- 2026-10-30 · Tägliche lokale Datensicherung nach `/backup` um 03:00 Uhr, die letzten 30 werden behalten (0.0.21/0.0.22).

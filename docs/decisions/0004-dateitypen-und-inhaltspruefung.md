# Entscheidung: Erlaubte Dateitypen und Inhaltsprüfung

*2026-10-30 · Version 0.0.15 · Anlass: Virus in einer PDF-Datei mit HTML-Inhalt*

## Erlaubt
PDF, JPEG (`.jpg`, `.jpeg`), PNG, TIFF (`.tif`, `.tiff`). Alle anderen Dateien werden beim Import sofort gelöscht.

Der PDF-Filter in ProFTPD wurde dafür entfernt. Sonst würde der FTP-Server unerwünschte Dateien schon beim Upload ablehnen, und ScanKai könnte nicht anzeigen, dass und warum eine Datei verworfen wurde.

## Prüfungen beim Import (`App\Libraries\FileInspector`)
1. **Endung erlaubt?**
2. **Datei nicht leer?**
3. **Signatur passt zur Endung:** `%PDF-`, JPEG `FF D8 FF`, PNG-Signatur, TIFF `II*` / `MM*`. Bei Abweichung wird genannt, was tatsächlich erkannt wurde (z. B. HTML-Dokument, Windows-Programm, ZIP/Office, E-Mail/MHT).
4. **Kein HTML- oder Skript-Code** im Rohinhalt: `<html`, `<script`, `<iframe`, `<object`, `<!doctype html`, `<meta http-equiv`, `<?php`, `javascript:`, `vbscript:`, `MIME-Version: 1.0`, `Content-Type: text/html`. Es werden bewusst nur lange, eindeutige Muster verwendet. Kurze Muster wie `<svg` würden in komprimierten Bilddaten zufällig vorkommen.
5. **PDF:**
   - `pdfinfo` muss die Datei lesen können.
   - Struktur über `qpdf --json` ohne Stream-Daten, inklusive Objekten in komprimierten Objekt-Streams. Abgelehnt bei `/JavaScript`, `/JS`, `/Launch`, `/EmbeddedFile`, `/RichMedia`, `/XFA`, `/SubmitForm`, `/ImportData`, `/GoToE`.
   - Entpackte Streams (`qpdf --qdf`) werden erneut auf HTML/Skript geprüft.
6. **Bilder:** `getimagesize` muss ein gültiges Bild des erwarteten Typs liefern.

## Anzeige
- Abgelehnte Dateien werden gelöscht und in `scan_rejections` protokolliert. Der Grund steht eine Stunde lang als Balken im Abschnitt des FTP-Kontos (nur für Benutzer, die dieses Konto sehen). Die Einträge bleiben 30 Tage gespeichert.
- TIFF: Für die Anzeige im Browser wird eine PDF-Fassung erzeugt (`tiff2pdf`, Rückfallebene ImageMagick). Heruntergeladen wird immer das Original.

## Getestet
14 präparierte Dateien, darunter HTML als PDF, PDF mit angehängtem HTML, PDF-JavaScript (auch versteckt im Objekt-Stream), HTML in einem komprimierten Stream, EXE als JPG und JPEG mit Skript-Kommentar: alle korrekt erkannt. Keine Fehlalarme bei 20 Demo-PDFs, einer 29-MB-Bild-PDF und Rausch-Bildern (JPEG, PNG, TIFF).

## Kein Virenscanner
Entscheidung des Projektinhabers (2026-10-30): **kein ClamAV.** Begründung: Ein Angreifer braucht zumindest gültige FTP-Zugangsdaten. Die Inhaltsprüfung deckt Tarnung (falsche Endung, HTML/Skript) und aktive PDF-Inhalte ab.

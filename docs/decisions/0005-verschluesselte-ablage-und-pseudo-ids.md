# Entscheidung: Verschlüsselte Ablage und Pseudo-IDs

*2026-10-30 · Version 0.0.18 · Vorgabe des Projektinhabers*

## Verschlüsselung der Dateien
- **Ziel:** Dateien liegen nur verschlüsselt auf dem Laufwerk. Es geht nicht um Ende-zu-Ende-Verschlüsselung, der Server kann die Dateien zum Anzeigen entschlüsseln.
- **Verfahren:** libsodium secretstream (XChaCha20-Poly1305), in Blöcken von 64 KiB, damit große Dateien nicht in den Arbeitsspeicher geladen werden müssen. Dateiformat: `SKE1` + Header + Blöcke, Dateiendung `.enc`.
- **Schlüssel:** einer pro Kunde (`tenants.storage_key`), selbst verschlüsselt mit dem Anwendungsschlüssel `encryption.key` aus `/var/www/scankai/.env`. Er wird beim ersten Gebrauch erzeugt.
- **Verschlüsselt werden:** Original (`<uuid>.<ext>.enc`), Vorschaubild (`<uuid>.thumb.png.enc`), TIFF-Ansicht (`<uuid>.view.pdf.enc`). Firmenlogos bleiben unverschlüsselt.
- **Unverschlüsselt kurzzeitig:** Die Datei liegt nur im FTP-Eingang, bis zum Import (ca. 1 Sekunde). Vorschaubild und Ansicht entstehen in `/dev/shm` (RAM). Zum Anzeigen und Herunterladen wird ebenfalls in den RAM entschlüsselt, die Kopie wird danach gelöscht.
- **Bestehende Scans:** `scankai scans:encrypt` verschlüsselt sie und prüft vor dem Löschen der unverschlüsselten Datei die SHA-256-Prüfsumme. Bei Kunde 1 wurden 25 Scans umgestellt und einzeln geprüft.
- **Wichtig für Backups:** Ohne `encryption.key` aus der `.env` sind alle Scans **unwiederbringlich** verloren. `.env`, Datenbank und `/srv/scankai/storage` gehören immer zusammen in die Sicherung.

## Pseudo-IDs in URLs
- Scans, FTP-Konten und Berechtigungseinträge haben eine zufällige `public_id` (16 Zeichen aus `a–z` ohne l/o und `2–9`, ca. 80 Bit).
- Sie wird verwendet in URLs (`/scans/<id>/view`, `/admin/ftp/<id>`), Formularen, Blätter-Parametern (`?page_a<id>`) und Hintergrund-Anfragen (Auf-/Zuklappen, Ansicht, Mehrfach-Löschen).
- Datenbank-IDs erscheinen nicht mehr nach außen. Aus den Nummern lässt sich nicht ablesen, wie viele Scans oder Konten es gibt.

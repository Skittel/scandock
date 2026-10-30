# Entscheidung: Admin-Journal

*2026-10-30 · Version 0.0.23 · Vorgabe des Projektinhabers*

Jeder Kunde hat ein Journal unter **Menü → Journal** (`/admin/journal`). **Nur Admins** sehen es, normale Benutzer erhalten 403 und haben keinen Menüpunkt.

## Protokolliert
| Bereich | Vorgänge |
|---|---|
| Einstellungen | Admin-Gruppe (alt → neu), Aufbewahrung (alt → neu), Firmenname (alt → neu), Logo hochgeladen/entfernt, Clientschlüssel erneuert (mit Ablaufdatum) |
| FTP-Konten | angelegt (Sichtbarkeit), gelöscht (mit Anzahl gelöschter Scans), neues Passwort, gesperrt/aktiviert, Sichtbarkeit (alt → neu), Person/Gruppe hinzugefügt/entfernt |
| Scans | eingegangen (Konto, Größe), abgelehnt und gelöscht (Grund), von Hand gelöscht (auch Mehrfach-Löschen, je Datei ein Eintrag), nach Ablauf der Aufbewahrung gelöscht |
| System | Einrichtung abgeschlossen, Aktionen des Betreibers per CLI (Umbenennen, Sperren/Entsperren, neuer Start-Link) |

Je Eintrag: Zeitpunkt, Bereich, Beschreibung, wer (Name und UPN zum Zeitpunkt der Aktion, „System (FTP)“, „System“ oder „Betreiber (CLI)“) und die IP-Adresse bei Web-Aktionen.

## Nicht protokolliert
Anmeldungen, Ansehen und Herunterladen von Scans (auch als ZIP), persönliche Einstellungen (Darstellung, Auf-/Zuklappen, Listen-/Kachelansicht).

## Technik
Tabelle `audit_log` (tenant_id, user_id, actor, category, action, details als JSON, ip, created_at). Die Texte entstehen aus Aktionscode und Details (`App\\Libraries\\AuditLog::ACTIONS`). Einträge werden **ein Jahr** aufbewahrt. Der tägliche Löschlauf (03:15 Uhr) entfernt ältere (`Config\\ScanDock::$auditRetentionDays = 365`, seit 0.0.24).

## Ebenfalls in 0.0.23: ZIP-Download
In der Listenansicht lädt „Ausgewählte herunterladen (n)“ die markierten Scans als ZIP: entschlüsselt im RAM, eindeutige Dateinamen, höchstens 1 GB, nur Scans mit Zugriffsrecht. Downloads erscheinen bewusst nicht im Journal.

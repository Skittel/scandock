# Testprotokoll: Abschlusstest ScanDock 0.0.20

*2026-10-30 · Testkunde `zz-final` mit eigenen Test-Sitzungen, danach gelöscht · Kunde 1 nur lesend geprüft*

## Infrastruktur
| Prüfung | Ergebnis |
|---|---|
| Dienste apache2, proftpd, mariadb, fail2ban, scandock-upload-watch, scandock-ingest.timer, scandock-purge.timer | alle aktiv |
| Firewall: öffentlich 21, 80, 443, 990, 40000–40100/tcp, alles für Admin-IPs | ok |
| Lauschende Ports nach außen | 21, 22, 80, 443, 990 |
| Zertifikate scandock.cloud, www, ftp, Kunde 1 | gültig bis 28.01.2027, automatische Verlängerung aktiv |
| fail2ban-Jails | proftpd, sshd |

## FTP
| Prüfung | Ergebnis |
|---|---|
| Upload FTP unverschlüsselt (IPv4), FTPS explizit (IPv4), FTPS implizit Port 990 (IPv6) | ok |
| Falsches Passwort | abgewiesen |
| Herunterladen, Löschen, Ordner anlegen | verboten |
| Auflisten | liefert keine Dateinamen |
| Import ab Upload inkl. Prüfung, Vorschaubild, Verschlüsselung | 2–3 Sekunden |

## Inhaltsprüfung und Verschlüsselung
| Prüfung | Ergebnis |
|---|---|
| HTML-Datei als `.pdf` | gelöscht („keine echte PDF-Datei, erkannt: HTML-Dokument“) |
| `.docx` | gelöscht („Dateityp nicht erlaubt“) |
| Balken für gelöschte Dateien in der Oberfläche | angezeigt |
| Alle Scans verschlüsselt, keine Klartext-Dateien im Speicher | ok |
| Download bitgenau gleich dem Original (SHA-256) | ok |
| Entschlüsselte Kopien im RAM nach Auslieferung entfernt | ok |
| Alle 25 Scans von Kunde 1 lesbar | ok (bei der Umbenennung geprüft) |

## Rechte
| Prüfung | Ergebnis |
|---|---|
| Ohne Anmeldung | Weiterleitung zum Login |
| Admin: öffentliche Scans ja, private (nicht eingetragen) nein, Admin-Bereich ja | ok |
| Eingetragenes Mitglied: private Scans | ok |
| Normaler Benutzer: private Scans und Admin-Bereich | verweigert |
| Datenbank-ID in URL | 404, Links enthalten nur Pseudo-IDs |
| Sitzung eines anderen Kunden | wird nicht anerkannt |

## Admin-Funktionen
FTP-Konto anlegen (Passwort einmal im Klartext, danach verdeckt, per Auge abrufbar), Person hinzufügen, Sichtbarkeit umschalten, Verzeichnissuche, Einstellungen speichern, Mehrfach-Löschen, Konto löschen, Darstellung speichern, Abmelden, CLI `tenant:list` / `tenant:link`: **alle ok**.

## Sicherheit
| Prüfung | Ergebnis |
|---|---|
| POST ohne CSRF-Token | abgewiesen, nichts gelöscht |
| HSTS, Session-Cookie Secure + HttpOnly | ok |
| HTTP → HTTPS | ok |
| Unbekannte Subdomain | 404 |
| Ungültiger Start-Link | 404 |
| Server-Header ohne Version | **Fehler gefunden und behoben** (0.0.20): Ubuntus `security.conf` überschrieb `ServerTokens Prod`. Die Datei heißt jetzt `zz-scandock-security.conf` und wird zuletzt geladen. |

## Nicht automatisiert geprüft
- Bedienung im Browser (Klicks, JavaScript-Interaktion): kein Browser auf dem Server, Rückmeldung vom Projektinhaber: „es funktioniert alles“.
- Sperre nach Fehlversuchen (mod_ban/fail2ban): nicht ausgelöst, um den Server nicht selbst zu sperren. Konfiguration geprüft.

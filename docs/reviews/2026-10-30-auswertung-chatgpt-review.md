# Auswertung: ChatGPT-Review vom 30.10.26

*Erstellt 2026-10-30 · bezieht sich auf das ChatGPT-Review vom 30.10.26 (Text im privaten Wiki des Betreibers) (Version 0.0.24)*

Ziel ist ein besseres Produkt, nicht die Frage, wer etwas falsch gemacht hat. Jeder Punkt wurde **am tatsächlichen Code der Version 0.0.24** geprüft, nicht nur gelesen.

**Bewertungsstufen:** ✅ zutreffend · 🟡 teilweise zutreffend / schon teilweise gelöst · ⚪ bewusste Anforderung bzw. nicht relevant · ❌ nicht zutreffend

## Umsetzungsstand (Version 0.0.25, 2026-10-30)

| Punkt | Status | Getestet |
|---|---|---|
| 2.1 Schreibfehler beim Verschlüsseln | ✅ umgesetzt: jeder Schreibvorgang geprüft, `.part`-Datei + Umbenennen, `fsync`, SHA-256-Gegenprüfung vor Löschen des Originals | voller Datenträger (tmpfs 1 MB / 200 KB): erkannt, keine Reste, Upload bleibt im Eingang und wird nach Freigabe vollständig importiert; abgeschnittene Datei erkannt |
| 2.2 Löschfehler | ✅ umgesetzt: Scan wird nur als gelöscht markiert, wenn Dateien bleiben; täglicher Wiederholungsversuch; Abgleich verwaister Dateien (> 1 h alt) | unlöschbare Datei (`chattr +i`): Scan ausgeblendet, Datei bleibt, nach Freigabe beim nächsten Lauf entfernt; verwaiste Dateien entfernt, laufende `.part` bleibt |
| 2.3 Temp-Datei der PDF-Prüfung | ✅ umgesetzt: RAM (`/dev/shm`), AppArmor angepasst | – |
| 2.4 Import gegen DB-Fehler | ✅ umgesetzt: DB-Eintrag im geschützten Block, bei Fehler Dateien entfernt, Upload bleibt | – |
| 2.5 Veraltete Gruppen | ✅ umgesetzt: Gruppen älter als 1 h geben keine Rechte; stille Neuanmeldung (max. alle 10 min), Hinweisbalken | 2 h alte Gruppen: kein Zugriff, kein Admin; Neuanmeldung ausgelöst, keine Schleife |
| 2.6 Logo-Dateiname | ✅ umgesetzt: zufälliger Name | Upload ok |
| 3.1 Alle sind Admins | ✅ Hinweisbalken für Admins, solange keine Admin-Gruppe gesetzt ist | angezeigt |
| 3.2 UPN → Objekt-ID | ✅ umgesetzt: Objekt-ID aus Verzeichnisvorschlag, bekanntem Benutzer oder bei erster Anmeldung gebunden | fremde Person mit wiederverwendetem UPN: kein Zugriff; umbenannte Person: Zugriff bleibt; Bindung bei Erstanmeldung (auch Groß-/Kleinschreibung) |
| 6 Composer/PHP/Lizenzkennung | ✅ `skittel/scandock`, PHP `^8.4`, `LicenseRef-ScanDock-MIT-Attribution` | `composer validate` |

**Zusätzlich auf Wunsch des Projektinhabers:**
- **Speicherwächter:** Unter 10 GB frei stoppt ein Root-Timer (jede Minute) den FTP-Server. Alle Benutzer sehen einen roten Balken, das Journal jedes Kunden bekommt einen Eintrag. Ab 11 GB gibt er automatisch wieder frei. *Getestet mit künstlich hoher Schwelle: FTP gestoppt, Verbindung abgewiesen, Balken angezeigt, Freigabe ok (dabei war FTP für ca. 1 s auch für Kunde 1 gesperrt, im Journal sichtbar).*
- **Maximal 50 MB pro Datei:** Größere Dateien werden gelöscht und wie falsche Endungen gemeldet (Balken + Journal). Harte FTP-Obergrenze: 1 GB, darüber bricht der Upload ab. *Getestet mit 59,4 MB.*

**Bei der Umsetzung gefunden und behoben:** Logmeldungen nutzten den in CodeIgniter reservierten Platzhalter `{file}`, dadurch stand im Log die PHP-Datei statt des Upload-Namens.

**Weiter offen:** Laut Projektinhaber derzeit nicht benötigt, alle Punkte stehen in der [ToDo-Liste](../TODO.md).

---

## Kurzfazit
Das Review ist **überwiegend zutreffend und nützlich**. Die drei HOCH-Befunde stimmen. Der wichtigste ist echt: Bei vollem Datenträger kann ein Scan verloren gehen. Zwei weitere Punkte sind wichtiger, als das Review sie einordnet: die Identifikation von Personen per UPN (Risiko bei einer Wiederverwendung des UPN) und fehlende automatisierte Tests. Einige Empfehlungen betreffen bewusste Anforderungen des Projektinhabers (alle sind anfangs Admins, unverschlüsseltes FTP, MIT mit Namensnennung) oder sind bereits umgesetzt.

---

## 2. Kritische und wichtige Befunde

| # | Review-Punkt | Bewertung | Befund im Code | Vorschlag |
|---|---|---|---|---|
| 2.1 | **FileCrypto: Schreibfehler nicht erkannt** (HOCH) | ✅ zutreffend, **höchste Priorität** | `encryptFile()` prüft die Rückgabewerte von `fwrite()`/`fclose()` nicht. Beim Import wird die verschlüsselte Datei **nicht** gegengeprüft. Diese Prüfung gibt es nur bei der einmaligen Umstellung `scans:encrypt`. Bei vollem Datenträger entsteht eine abgeschnittene `.enc`-Datei, der Datenbankeintrag wird geschrieben und **das Original im FTP-Eingang gelöscht** → Scan verloren. | Schreibvorgänge prüfen, in eine temporäre Datei schreiben und erst nach erfolgreicher Prüfung umbenennen (atomar). Nach dem Verschlüsseln probeweise entschlüsseln und die SHA-256 vergleichen, erst dann das Original löschen. |
| 2.2 | **ScanStore: Löschfehler ignoriert** (HOCH) | ✅ zutreffend | `delete()` nutzt `@unlink()` und löscht den Datenbankeintrag immer. Bleibt eine Datei liegen, ist sie „verwaist“: Sie ist zwar verschlüsselt, aber die zugesagte Löschfrist ist verletzt, und niemand bemerkt es. | Löscherfolg prüfen. Bei Fehler den Datenbankeintrag behalten oder als „Löschung ausstehend“ markieren, der tägliche Lauf versucht es erneut. Zusätzlich ein wöchentlicher Abgleich Speicher ↔ Datenbank, der verwaiste Dateien meldet bzw. entfernt. |
| 2.3 | **FileInspector: unverschlüsselte Temp-Datei im Cache** (HOCH) | ✅ zutreffend, Einstufung eher MITTEL | Die PDF-Prüfung schreibt eine entpackte Kopie nach `writable/cache/pdfscan*`, also auf die Platte, und löscht sie anschließend wieder. Das widerspricht der Zusage „Zwischenschritte nur im RAM“. Der unverschlüsselte Upload liegt allerdings ohnehin bis zu ca. 1 s im FTP-Eingang. Das lässt sich bei FTP nicht vermeiden. | Temp-Datei ins RAM-Arbeitsverzeichnis (`/dev/shm`) verlegen, die AppArmor-Regel für qpdf entsprechend anpassen. Kleiner Aufwand. |
| 2.4 | **Import nicht vollständig gegen DB-/Dateifehler abgesichert** (MITTEL) | 🟡 teilweise | Die Reihenfolge stimmt bereits: verschlüsseln → DB-Eintrag → erst dann Original löschen. Schlägt der DB-Eintrag fehl, wirft CodeIgniter eine Ausnahme (`DBDebug = true`), das Original bleibt erhalten und wird später erneut importiert. Es bleiben aber verschlüsselte **Dateileichen** zurück. Der eigentliche Verlustfall ist 2.1. | Mit 2.1 und 2.2 erledigt: Bei Fehler nach dem Verschlüsseln die erzeugten Dateien wieder entfernen, und der Abgleich findet Reste. |
| 2.5 | **GroupSync: veraltete Gruppen bei Graph-Ausfall** (MITTEL) | ✅ zutreffend | Ist Graph nicht erreichbar, bleiben die zuletzt bekannten Gruppen beliebig lange gültig (Sitzung bis 8 h, danach neue Anmeldung). Wer in Entra aus einer Gruppe entfernt wurde, behält in dieser Zeit seine Rechte. | Zeitpunkt der letzten erfolgreichen Abfrage speichern. Ist er älter als z. B. 1 h, gelten Gruppenrechte als **nicht vorhanden** (sicherer Zustand), mit Hinweis an den Benutzer. Persönliche Zuordnungen und öffentliche Konten bleiben davon unberührt. |
| 2.6 | **TenantBranding: Kollision bei Logo-Dateinamen** (NIEDRIG) | ✅ zutreffend, sogar etwas schlimmer | Dateiname `logo-<Sekunde>`. Zwei Uploads in derselben Sekunde mit gleicher Endung: Der zweite überschreibt den ersten, und das anschließende Entfernen des „alten“ Logos löscht dann **das gerade gespeicherte**. Praktisch sehr selten. | Zufälligen Dateinamen verwenden. Einzeiler. |

## 3. Sicherheit und Berechtigungen

| # | Review-Punkt | Bewertung | Befund / Begründung | Vorschlag |
|---|---|---|---|---|
| 3.1 | Ohne Admin-Gruppe sind alle Admins | ⚪ bewusste Anforderung | So gewünscht („am Anfang sind alle User Admins“). In den Einstellungen steht ein Hinweis, und ein Schutz verhindert, dass man sich selbst aussperrt. Kunde 1 hat bereits eine Admin-Gruppe. | Kein Umbau. Kleine Verbesserung: Solange keine Admin-Gruppe gesetzt ist, zeigt ein gelber Hinweisbalken allen Admins, dass jeder angemeldete Benutzer Admin ist. |
| 3.2 | Personen werden per UPN statt Objekt-ID identifiziert | ✅ zutreffend, **wichtiger als im Review dargestellt** | Personen-Freigaben auf private FTP-Konten werden über den UPN abgeglichen. Wird ein Benutzer umbenannt, verliert er den Zugriff. Gravierender: Wird ein UPN später **an eine andere Person vergeben** (z. B. `info@`, Nachfolger mit gleichem Namen), erhält diese Zugriff auf private Scans. Benutzer selbst sind schon über die Objekt-ID gespeichert, nur die Freigaben nicht. | Freigaben zusätzlich mit der Objekt-ID speichern: Bei Auswahl aus dem Verzeichnis ist sie bekannt, bei freier Eingabe wird sie bei der ersten Anmeldung der Person festgeschrieben. Danach nur noch über die Objekt-ID abgleichen. Der UPN dient nur noch der Anzeige. |
| 3.3a | Unverschlüsseltes FTP | ⚪ bewusste Anforderung | Notwendig für ältere Geräte. Abgesichert: Konten können nur hochladen, Passwörter sind zufällig, nur bestimmte Dateitypen, Inhaltsprüfung. | – |
| 3.3b | Unverschlüsselte Konten kennzeichnen | 🟡 sinnvoll | Heute ist nicht sichtbar, ob ein Gerät FTP oder FTPS nutzt. Das ProFTPD-Log enthält die Information. | Je FTP-Konto anzeigen: letzte Verbindung mit FTP oder FTPS (siehe Gerätestatus unter 7). |
| 3.3c | IP-Freigabeliste je Konto | 🟡 optional sinnvoll | Viele Kunden haben feste IPs. Das senkt das Risiko mitgelesener Passwörter deutlich. | Optionales Feld „erlaubte IP-Adressen“ je Konto (ProFTPD prüft per SQL). Mittlerer Aufwand. |
| 3.3d | Fehlversuche protokollieren und begrenzen | 🟡 bereits umgesetzt | Begrenzung: mod_ban (3 Fehlversuche in 10 Minuten → 1 h Sperre) und fail2ban. Protokoll: ProFTPD-Log auf dem Server. Im Admin-Journal fehlt es. | Optional: Sperren ins Admin-Journal übernehmen. |

## 4. Verschlüsselung, Aufbewahrung und Backups

| # | Review-Punkt | Bewertung | Begründung | Vorschlag |
|---|---|---|---|---|
| 4.1 | Fehlerbehandlung beim Verschlüsseln | ✅ siehe 2.1 | – | – |
| 4.2a | Backup zusätzlich mit unabhängigem Schlüssel verschlüsseln | ✅ zutreffend für die externe Sicherung | Lokal (`/backup`, nur root) bringt es wenig, weil der Schlüssel auf demselben Server läge. Für die externe Sicherung ist es Pflicht. | In den ToDo-Punkt „Externe Datensicherung“ aufnehmen (steht dort schon sinngemäß). |
| 4.2b | Getrenntes Speicherziel | ✅ bereits als ToDo erfasst | – | – |
| 4.2c | Wiederherstellung regelmäßig testen | ✅ zutreffend | Bisher einmal manuell (Datenbank). Scans und Schlüssel wurden nicht aus dem Archiv geprüft. | Wöchentlicher automatischer Prüflauf: Archiv in den RAM entpacken, Datenbank in eine Testdatenbank einspielen, Stichprobe von Scans mit dem Schlüssel aus dem Archiv entschlüsseln und mit der SHA-256 vergleichen, Ergebnis ins Log. |
| 4.2d | Backup- und Dokumentaufbewahrung trennen | ✅ zutreffend | Siehe 4.3. | – |
| 4.3a | Gelöschte Scans bleiben in Backups | ✅ zutreffend und rechtlich relevant | Ein nach 30 Tagen gelöschter Scan steckt noch bis zu 30 Tage in den Sicherungen. Effektiv werden Dokumente also bis zu ca. 60 Tage aufbewahrt. | Für die Kundeninformation (Datenschutz/AVV) dokumentieren. Optional die Backup-Aufbewahrung verkürzen. |
| 4.3b | Fehlgeschlagene Löschungen wiederholen | ✅ siehe 2.2 | – | – |

## 5. Stabilität und Dateiverarbeitung

| Begrenzung | Stand heute | Bewertung / Vorschlag |
|---|---|---|
| Maximale Dateigröße | ✅ vorhanden: 200 MB je Upload (ProFTPD) | – |
| Seitenzahl / Verarbeitungszeit | 🟡 Zeitlimits vorhanden (pdfinfo 30 s, qpdf 60 s, Vorschau 30 s, TIFF 60–120 s), keine Seitengrenze | Ausreichend, da nur die erste Seite gerendert wird. Optional eine Seitengrenze (z. B. 2.000). |
| Arbeitsspeicher für Vorschau | 🟡 ImageMagick ist begrenzt (256 MiB), pdftoppm und qpdf nicht | Für alle Hilfsprogramme ein Speicherlimit setzen (`ulimit` / systemd `MemoryMax` für den Import-Dienst). |
| Gleichzeitige Verarbeitung | 🟡 Der Import läuft bewusst **nacheinander** (Sperrdatei). Das verhindert eine Überlastung, aber eine sehr große Datei eines Kunden verzögert alle anderen | Für die aktuelle Größe akzeptabel. Später: kleine feste Anzahl paralleler Import-Prozesse. |
| Speicherquoten je Kunde | ❌ nicht vorhanden | Sinnvoll vor weiteren Kunden (siehe 7). |
| Voller Datenträger | ✅ Problem real (siehe 2.1) | Zusätzlich: Import pausiert unter z. B. 2 GB freiem Speicher, Uploads bleiben im Eingang, Warnung. |

**Empfohlene Funktionstests**

| Test | Stand |
|---|---|
| Sehr große PDF | 🟡 29 MB getestet, 200 MB nicht |
| Mehrseitige TIFF | ✅ getestet (LZW, G4, 16 Bit) |
| Viele gleichzeitige Uploads | ❌ nicht getestet |
| Identische Dateinamen | 🟡 Dateinamen werden auf dem Server per UUID gespeichert, es wird also nichts überschrieben. **Aber:** Schickt ein Gerät denselben Namen erneut, bevor der erste importiert ist (< ca. 1 s), lehnt FTP den zweiten Upload ab (`AllowOverwrite off`). Praktisch selten, sollte aber getestet werden. |
| Datenträger voll | ❌ nicht getestet – führt heute zu Datenverlust (2.1) |
| Abbruch während der Verschlüsselung | ❌ nicht getestet |
| Neustart während des Imports | ❌ nicht getestet (Erwartung: Original bleibt erhalten, ggf. Dateileiche) |
| Graph nicht erreichbar | ❌ nicht getestet (siehe 2.5) |
| Fehler bei automatischer Löschung | ❌ nicht getestet (siehe 2.2) |

## 6. Dokumentation und Projektqualität

| Punkt | Bewertung | Befund |
|---|---|---|
| Verweise auf ScanKai | ❌ im Code nicht zutreffend | `git grep scankai` findet 0 Treffer. ScanKai erscheint nur in Wiki-Seiten als Historie, das ist bewusst so. |
| Composer-Metadaten | ✅ zutreffend | `composer.json` enthält noch `codeigniter4/appstarter`, „CodeIgniter4 starter app“ und die Homepage codeigniter.com. |
| PHP-Version | ✅ zutreffend | README: PHP 8.4+, `composer.json`: `^8.2`. Vereinheitlichen auf `^8.4`. |
| Lizenz | 🟡 Lizenz bewusst gewollt, Metadaten falsch | Die Namensnennungspflicht ist eine Vorgabe des Projektinhabers, der Lizenztext heißt deshalb ehrlich „MIT License with Attribution“. `composer.json` meldet aber `"license": "MIT"` (geerbt). Das ist irreführend. Besser: `"license": "LicenseRef-ScanDock-MIT-Attribution"` oder `"proprietary"`. Hinweis: GitHub erkennt die Lizenz dadurch nicht als Standard-MIT. |
| Automatisierte Tests | ✅ zutreffend, **wichtig** | Es gibt keine automatisierten Tests. Alle bisherigen Tests liefen manuell per Skript gegen das Live-System, mit Testkunden. Das hat funktioniert, ist aber nicht wiederholbar. Bei der Arbeit sind mehrfach Fehler erst im Test aufgefallen (AppArmor, Dateirechte, Dateinamen-Kollision bei PNG-Vorschau). |
| CI | ✅ zutreffend | GitHub Actions: PHPUnit + PHPStan bei jedem Push. Braucht keine Freigaben auf dem Server. |

## 7. Vorschläge für zukünftige Funktionen

| Vorschlag | Einschätzung |
|---|---|
| **Gerätestatus** (letzter Upload, FTP/FTPS, Inaktivität) | Sehr nützlich, geringer Aufwand. Beantwortet die häufigste Supportfrage „Scannt das Gerät noch?“. |
| **SharePoint-/OneDrive-Export** | Fachlich naheliegend, da das Ursprungsproblem „Scan nach SharePoint“ war. Braucht zusätzliche Graph-Berechtigungen (Sites/Files) und ein Konzept pro Kunde. Größerer Ausbau. |
| **Mandantenquoten** | Sinnvoll vor weiteren Kunden. |
| **Download-Protokoll** | Wurde bewusst ausgeschlossen. Optional pro Kunde zuschaltbar, falls ein Kunde es verlangt. |
| **Betriebsmonitoring** | Wichtig: Heute merkt niemand automatisch, wenn Import, Löschlauf oder Backup ausfallen. Setzt einen Mailversand oder externes Monitoring voraus. |

---

## Empfohlene Reihenfolge

1. **Datenverlust verhindern** (2.1, 2.4, voller Datenträger): Schreibprüfung, atomares Schreiben, Gegenprüfung vor Löschung des Originals, Import-Pause bei wenig Speicher.
2. **Freigaben per Objekt-ID** (3.2).
3. **Löschung verlässlich** (2.2): Wiederholung und Abgleich verwaister Dateien.
4. **Kleinkram mit Sicherheitsbezug**: Temp-Datei in RAM (2.3), Logo-Dateiname (2.6), Höchstalter der Gruppen (2.5), Hinweis „alle sind Admins“ (3.1).
5. **Automatisierte Tests + CI** inkl. der Fehlerfälle aus Abschnitt 5, Metadaten (Composer, PHP-Version, Lizenzkennung).
6. **Betrieb**: Monitoring/Benachrichtigung, automatischer Wiederherstellungstest, externe Sicherung (ToDo), Hinweis zur Backup-Aufbewahrung für Kunden.
7. **Erweiterungen**: Gerätestatus, IP-Freigabe je Konto, Quoten, SharePoint-Export.

Punkte 1–4 lassen sich als Patch-Versionen umsetzen. Für 5–7 ist eine Abstimmung zur Priorität sinnvoll.

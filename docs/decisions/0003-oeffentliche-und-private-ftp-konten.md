# Entscheidung: Öffentliche und private FTP-Konten

*2026-10-30 · Version 0.0.6 · Vorgabe des Projektinhabers*

## Regeln
- Jedes FTP-Konto ist **öffentlich** (alle Benutzer des Kunden sehen die Scans) oder **privat** (nur eingetragene Personen und Entra-Gruppen).
- **Admins** dürfen alle Einstellungen sehen und ändern: Konten, Zugangsdaten, Sichtbarkeit, Mitglieder, Einstellungen. Sie sehen öffentliche Scans, **private Scans aber nur, wenn sie selbst oder eine ihrer Gruppen eingetragen sind.**
- Neue Konten sind standardmäßig privat, öffentlich ist per Auswahl beim Anlegen möglich. Bestehende Konten ohne Mitglieder wurden bei der Migration öffentlich, Konten mit Mitgliedern privat.
- Wird ein privates Konto öffentlich, bleiben die eingetragenen Mitglieder gespeichert und gelten wieder, sobald das Konto erneut privat wird.

## Zuordnung mit Autovervollständigung
- Ein einziges Eingabefeld für Personen und Gruppen, Vorschläge erscheinen ab 2 Zeichen.
- Quellen:
  1. **Entra-Verzeichnis** über Microsoft Graph mit App-Token (Client Credentials). Optional, benötigt die Anwendungsberechtigungen `User.Read.All` und `Group.ReadBasic.All` mit Administratorzustimmung. Ohne Berechtigung pausiert die Abfrage für 10 Minuten.
  2. **Lokal:** Benutzer, die sich schon angemeldet haben, und Gruppen, die bereits zugeordnet sind.
- Freie Eingabe bleibt möglich: Eine E-Mail-Adresse wird als Person (UPN) gespeichert, eine GUID als Gruppe.
- Begründung für die Anwendungsberechtigungen statt delegierter: Die Suche läuft serverseitig und unabhängig von der Sitzung des Admins, und normale Benutzer müssen bei der Anmeldung keinen zusätzlichen Berechtigungen zustimmen.

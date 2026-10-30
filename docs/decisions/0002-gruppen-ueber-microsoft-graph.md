# Entscheidung: Gruppen über Microsoft Graph statt Tokenkonfiguration

*2026-10-30 · Version 0.0.5*

## Ausgangslage
Bis 0.0.4 kamen die Gruppen nur aus dem `groups`-Claim des ID-Tokens (Tokenkonfiguration der App-Registrierung). Nachteile:
- muss vom Kunden zusätzlich eingerichtet werden und wird leicht vergessen
- „Sicherheitsgruppen“ enthält keine Microsoft-365-Gruppen
- maximal 200 Gruppen, sonst kommt gar keine Liste
- Änderungen werden erst bei der nächsten Anmeldung sichtbar

## Optionen
| Variante | Berechtigung | Admin-Zustimmung | Bewertung |
|---|---|---|---|
| Gruppen-Claim (Tokenkonfiguration) | keine | nein | Nachteile siehe oben |
| Graph `/me/transitiveMemberOf` | **User.Read** (delegiert) | nein (empfohlen, damit keine Abfrage beim Benutzer) | nur eigene Gruppen, alle Gruppenarten, auch verschachtelt |
| Graph mit Group.Read.All / GroupMember.Read.All | erhöht | ja | darf alle Gruppen des Mandanten lesen, mehr als nötig |

## Entscheidung
- Bei der Anmeldung wird `User.Read` angefordert. ScanKai liest die Gruppen des Benutzers über `/me/transitiveMemberOf` und vereinigt sie mit einem eventuell vorhandenen Gruppen-Claim.
- **Abgleich beim Speichern:** Ist der Admin laut Datenbank nicht Mitglied der neuen Admin-Gruppe, werden seine Gruppen sofort bei Microsoft abgefragt, solange das Zugriffstoken der Sitzung (ca. 1 Stunde) noch gilt. Ist es abgelaufen, folgt eine stille Anmeldung (`prompt=none`), danach wird das Speichern automatisch fortgesetzt. Ist er dann immer noch kein Mitglied, wird gewarnt.
- Der Gruppen-Claim ist optional, eine Tokenkonfiguration ist nicht mehr nötig.
- Gespeichert werden nur Gruppen-IDs. Gruppennamen liefert User.Read nicht.

# Airmius Testplan: erst Free, dann Premium

Diese Datei erklärt, wie die Use-Case-Testmatrix sinnvoll getestet wird. Die eigentliche Matrix liegt in:

- `docs/Airmius_Use_Cases_Testmatrix.csv`
- `docs/Airmius_Use_Cases_Testmatrix_testfreundlich_20260505-131824.xlsx`

## Ziel

Nicht direkt alles auf einmal testen. Erst prüfen, ob ein kostenloses Konto sauber funktioniert. Danach Premium aktivieren und prüfen, ob zusätzliche Funktionen freigeschaltet werden, ohne Free-Funktionen kaputtzumachen.

## Empfohlene Testkonten

| Konto | Zweck |
| --- | --- |
| Gast ohne Login | Öffentliche Seiten, Preise, Blog, rechtliche Seiten, Registrierung |
| Free-Nutzerkonto | Profil, Feed, Suche, Freunde, Nachrichten, Einstellungen |
| Free-Vereinsadmin | Verein/Team-Grundfunktionen und Plan-Limits |
| Premium-Nutzerkonto | Nutzerfunktionen mit Premium-Plan |
| Premium-Vereinsadmin | Mitgliederverwaltung, Beiträge, SEPA, DATEV, Imports/Exports |
| Admin-Konto | Adminbereiche, Moderation, Rechnungen, Systemverwaltung |

## Reihenfolge

### 1. Free: Gast & Registrierung

Teste zuerst ohne Login:

- Startseite, Preise, Vereine, Blog, Jobs, Marketplace
- Sprache ändern
- Registrierung mit E-Mail
- Login, Logout, Passwort vergessen
- OAuth-Registrierung, falls verfügbar

Bestanden, wenn öffentliche Seiten stabil laden, Navigation funktioniert und Registrierung/Login sauber weiterleiten.

### 2. Free: Nutzerkonto testen

Danach mit einem normalen kostenlosen Konto:

- Profil bearbeiten
- Profilbild, Adresse, Sprache, Sicherheit
- Feed lesen, Post erstellen, kommentieren, liken, hilfreich markieren
- Suche, Benachrichtigungen, Freunde, Folgen, Nachrichten

Bestanden, wenn alle Free-Funktionen nutzbar sind und Premium-Funktionen nur als Hinweis oder sauber gesperrt erscheinen.

### 3. Free: Verein/Team testen

Mit Free-Konto einen Testverein und ein Team prüfen:

- Verein/Team erstellen oder öffnen
- Mitglieder einladen, soweit Free erlaubt
- Events und Basisverwaltung testen
- Plan-Limits bewusst erreichen

Bestanden, wenn Free-Limits verständlich angezeigt werden und nichts abstürzt.

### 4. Premium: Nutzerfunktionen testen

Premium für ein Nutzerkonto aktivieren:

- Premium-Funktionen freischalten
- Rechnung/Zahlung prüfen
- Kündigung oder Ablauf testen, falls vorgesehen
- Danach Free-Funktionen erneut kurz prüfen

Bestanden, wenn Premium mehr erlaubt, aber bestehende Free-Funktionen unverändert funktionieren.

### 5. Premium: Verein/Team testen

Premium für Verein aktivieren:

- Mitgliederverwaltung vollständig testen
- Beiträge, Rechnungen, SEPA, DATEV
- Import/Export mit gültigen und ungültigen Dateien
- Bankabgleich und offene Zahlungen

Bestanden, wenn Premium-Funktionen aktiv sind, korrekte Daten speichern und Fehler verständlich angezeigt werden.

### 6. Admin & System testen

Zum Schluss Admin- und Hintergrundprozesse:

- Adminbereiche öffnen
- Moderation, Sperren, Meldungen
- Abo-Pläne, Rechnungen, E-Mail-Inhalte
- Cron/Webhook-Prozesse mit Testdaten

Bestanden, wenn Adminfunktionen nur für Admins erreichbar sind und Aktionen nachvollziehbar protokolliert werden.

## Wie du die Matrix nutzt

1. In der Spalte `Testphase` nach Phase filtern.
2. Mit `1 - Free: Gast & Registrierung` starten.
3. Pro Zeile `Voraussetzung`, `Testdaten` und `Testschritte` abarbeiten.
4. In `Status` nur `Offen`, `Bestanden` oder `Fehler` eintragen.
5. Bei Fehlern in `Fehler/Notiz` Route, Konto, Eingabe und Screenshot-Hinweis notieren.

## Wichtig

Free-Test bedeutet nicht, dass jede Funktion kostenlos verfügbar sein muss. Bei Premium-Funktionen ist im Free-Test bestanden, wenn die Funktion sauber gesperrt ist, ein Upgrade-Hinweis erscheint oder keine unerlaubte Speicherung möglich ist.

Premium-Test bedeutet nicht nur Freischaltung. Es muss auch geprüft werden, ob Rechnungen, Limits, Benachrichtigungen und Berechtigungen korrekt bleiben.

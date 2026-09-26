# Vereins-UX: finale 12-Punkte-Nachprüfung

Stand: 22. September 2026

1. **Rollenaktion:** „Rolle ändern“ nutzt eine aktive Primärfarbe und bleibt direkt im Mitgliedseintrag erreichbar.
2. **Cockpit-Wortlaut:** Leere Aufgaben verweisen konsistent auf „Finanzen“.
3. **Event-Erstellung:** Kein automatischer Tastaturstart verdeckt mehr das Formular.
4. **Fester Vereinskontext:** Der vorgegebene Verein zählt nicht als veränderbarer Filter.
5. **Filter zurücksetzen:** Der feste Vereinskontext bleibt nach dem Zurücksetzen erhalten.
6. **Team-Erstellung:** „Team erstellen“ ist ohne Namen deaktiviert.
7. **Nächster Team-Schritt:** Ein Hinweis erklärt bereits im Formular, dass anschließend Mitglieder und Rollen eingerichtet werden.
8. **Finanzkennzahlen:** Die Cockpit-Karten stehen kompakt in zwei Spalten.
9. **Mitteilungen:** Der Leerzustand bietet genau eine primäre Erstellen-Aktion.
10. **Teamkader:** Rolle und Rollenmenü stehen platzsparend in derselben Zeile.
11. **Einrichtung:** Bestehende Vereine sehen zunächst nur den wichtigsten offenen Schritt; neue Vereine behalten ihre drei Startschritte.
12. **Berichte:** Der ausgewählte Jahreszeitraum zeigt echte Einnahmen, Ausgaben und das daraus berechnete Ergebnis aus den vorhandenen API-Summen.

## Technische Prüfung

- `flutter analyze --no-pub`: ohne Befund
- Test „new club starts with status and focused first actions“: bestanden
- Vollständige Flutter-Test-Suite: 339 Tests bestanden

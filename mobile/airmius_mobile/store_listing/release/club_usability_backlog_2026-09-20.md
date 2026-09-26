# Vereinsrolle: gebündelte Usability-Verbesserungen

Stand: 20.09.2026. Ursprünglich auf einem Android-Gerät mit Airmius 1.0.59 (VersionCode 144) geprüft. Die vier Punkte sind in 1.0.60 (VersionCode 145) umgesetzt, im internen Google-Play-Test veröffentlicht und auf dem Gerät verifiziert.

## Bestätigte Probleme

1. **Finanzen hat den falschen Seitenkontext (hoch).** Vom Vereins-Cockpit führt „Finanzen“ zum Finanzabschnitt der Mitgliederverwaltung, aber App-Bar und Seitentitel lauten weiter „Mitglieder & Beiträge“. Der aktuelle Abschnitt muss im Titel sichtbar werden. Umsetzung voraussichtlich in `lib/screens/club_membership_management_screen.dart`; Einstieg mit `initialSection: 'finance'` beibehalten.
2. **Technischer Leerzustand im Teamkalender (mittel).** Ohne Termine erscheint „Keine Teamtermine aus der API vorhanden.“. Nutzerfreundlich wäre „Noch keine Teamtermine vorhanden.“ mit einer passenden Aktion für Berechtigte. Textschlüssel `teamDetail.noEvents` in `lib/core/airmius_l10n.dart`, Darstellung in `lib/screens/team_detail_screen.dart`. Alle unterstützten Sprachen prüfen.
3. **„Team löschen“ zu prominent (hoch).** In Teamdetails steht die destruktive Aktion direkt neben „Neu laden“ und erscheint unabhängig vom gewählten Abschnitt. Sie gehört in ein Team-Verwaltungsmenü oder auf die Profil-/Einstellungsseite, mit bestehender Bestätigung. Der normale Aufgabenbereich soll ohne Löschschaltfläche enden. Umsetzung in `lib/screens/team_detail_screen.dart`.
4. **Vereinszugang über „Arbeitsbereiche“ macht einen Umweg (mittel).** Die Vereinskarte öffnet `WorkspaceDetailScreen`; „Bereich auswählen“ öffnet nur einen weiteren Selektor, und der eigentliche Einstieg ins Vereins-Cockpit liegt unten auf der langen Seite. Für einen einzelnen Verein sollte die Karte direkt ins Cockpit führen oder ein klarer Button „Vereins-Cockpit öffnen“ oberhalb der Detaildaten stehen. Einstieg in `lib/screens/workspace_center_screen.dart`, Ziel in `lib/screens/workspace_detail_screen.dart`. Mehrere Vereine und Rollenwechsel berücksichtigen.

## Prüfung auf dem Gerät

- Vereins-Cockpit: Mitglieder, Teams, Termine und Finanzen sind direkt erreichbar; kompakte Kennzahlen werden angezeigt.
- Mitglieder: Suche nach Mitgliedsnummer `MV-1055` lieferte den passenden Eintrag und filterte andere Mitglieder aus.
- Teams: Teamliste und neue Bereichsauswahl öffnen; Wechsel von Kader zu Kalender funktioniert.
- Teamkalender: Leerzustand und „Kalender öffnen“ geprüft; der Link führt zu „Events & Training“ mit verständlicher leerer Ansicht und „Event erstellen“.
- Finanzen: „Rechnung erstellen“, „Zahlung erfassen“, Rechnungszeitraum und Kassenbuch sind sichtbar. Es wurden keine Buchungen erstellt und keine Team- oder Mitgliedsdaten verändert.

## Umsetzung und Abnahme

- Der Finanztitel folgt nun dem tatsächlich verwendeten Abschnitt `payments`; der Einzelverein öffnet aus „Arbeitsbereiche“ direkt das Vereins-Cockpit. Teamtermine haben einen verständlichen Leerzustand in DE/EN/FR/AR. „Team löschen“ liegt in der App-Bar-Menüaktion und behält die Bestätigung.
- Gezielte Widget-Tests für Einstieg, dynamischen Finanztitel, Kalender-Leerzustand und Position der Löschaktion bestehen. `flutter analyze --no-pub` ist fehlerfrei; die vollständige Flutter-Testsuite besteht mit 336 Tests.
- Die Play-installierte Version 145 wurde auf dem Handy geprüft: Die Vereinskarte öffnet das Cockpit direkt, „Finanzen“ erscheint als Seitentitel, „Team löschen“ liegt ausschließlich im Verwaltungsmenü und der Teamkalender sagt „Noch keine Teamtermine vorhanden.“. Eine vollständige Prüfung aller Formulare und Berechtigungsvarianten bleibt außerhalb dieses Änderungssatzes.

Weitere Formulare wie Rechnungserstellung, Teamlöschung und echte Einladung wurden in dieser Runde nicht abgeschickt; über deren vollständige Funktion lässt sich aus dieser UI-Prüfung noch keine Aussage treffen.

# Vereins-UX: Nachprüfung der 12 Punkte

Stand: 22. September 2026

1. **Finanz-Tab bei schmalen Displays:** Der Haupttab heißt kompakt „Finanzen“. Die ausführliche Seitenüberschrift „Beiträge & Zahlungen“ bleibt erhalten. Der bestehende 390-Pixel-/Großschrift-Test prüft den kurzen Tab.
2. **Sportart im Vereinsprofil:** API-Werte wie `strassenlauf` werden als „Straßenlauf“ angezeigt und beim Speichern wieder in den erwarteten API-Wert umgewandelt.
3. **Passende Event-Beispiele:** Training, Treffen, Spiel und öffentliches Event besitzen jeweils einen zum Typ passenden Titelhinweis.
4. **Nicht nutzbare Rollenaktion:** „Rolle ändern“ wird nur angezeigt, wenn eine echte Änderungsfunktion vorhanden ist; während des Speicherns ist sie gesperrt.
5. **Doppelte Kader-Aktion:** Ein Schnellzugriff wird ausgeblendet, sobald der zugehörige Bereich bereits geöffnet ist. Das gilt auch für Einladen und Kalender.
6. **Verständliche Finanzbegriffe:** „Buchung hinzufügen“ heißt jetzt konkret „Einnahme oder Ausgabe eintragen“. „Zahlung erfassen“ bleibt der Rechnung zugeordnet.
7. **Aktive und gesamte Mitglieder:** Die Kennzahlen erklären die Bedeutung aktiver Mitglieder und zeigen den berechneten Anteil an der Gesamtzahl.
8. **Prüfstatus im Cockpit:** Der Status ist kompakt und kann für die ausführliche Erklärung aufgeklappt werden.
9. **Cockpit-Verwaltung:** Die zusätzliche große Überschrift wurde entfernt. Organisation und Kommunikation strukturieren den Bereich direkt.
10. **Dateiinformationen:** Technische MIME-Werte werden in der Dateiliste als „Bild“, „Video“, „PDF“ oder „Datei“ mit Größe dargestellt.
11. **Kennzahlen mit Einordnung:** Mitglieder- und Finanzzahlen haben erklärende Hinweise und nennen bei offenen Rechnungen den nächsten sinnvollen Schritt.
12. **Leerer Ankündigungsbereich:** Der Leerzustand erklärt, was Ankündigungen leisten und wie die erste Nachricht erstellt wird.

## Technische Prüfung

- `flutter analyze --no-pub`: ohne Befund
- Gezielter Großschrift-Test des Finanz-Tabs: bestanden
- Vollständige Flutter-Test-Suite: 339 Tests bestanden

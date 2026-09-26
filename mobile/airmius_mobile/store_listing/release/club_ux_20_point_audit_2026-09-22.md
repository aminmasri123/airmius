# Vereinsbereich: UX-Audit der 20 Verbesserungen

Stand: 22. September 2026

1. **Lesbare Mitglieder-Navigation:** Die Hauptnavigation zeigt nur „Mitglieder“ und „Beiträge & Zahlungen“. Seltenere Bereiche liegen unter „Weitere Funktionen“.
2. **Eindeutige Bedeutung von „Beiträge“:** Einstellungen heißen jetzt „Vereins-Posts“ und „Team-Posts“; Finanzen heißen „Beiträge & Zahlungen“.
3. **Kürzeres Cockpit:** Die doppelte Navigationsreihe wurde entfernt. Das Cockpit beginnt mit drei konkreten Aufgaben.
4. **Nachvollziehbare Kennzahlen:** Der Leerlauftext erklärt, welche offenen Aufgaben er umfasst. Die Finanzansicht trennt erwartete Beiträge von bereits erstellten Rechnungen.
5. **Konkreter Prüfstatus:** Der Status nennt jetzt, welche Verwaltung bereits funktioniert und dass die öffentliche Bestätigung noch aussteht.
6. **Ruhigere Mitgliedskarten:** Karten zeigen nur Name, Mitgliedstyp und Saldo. Nummer, Zahlungsart und Aktionen erscheinen nach Antippen.
7. **Finanzen mit Handlungsübersicht:** Oberhalb der Liste stehen Anzahl und Summe offener Rechnungen samt nächstem Schritt.
8. **Lesbare Dateinamen:** Technische Hash-Namen werden als „Bild ohne Namen“ oder „Datei ohne Namen“ dargestellt.
9. **Beschriftete Dateiaktionen:** Dateien und Ordner verwenden Menüs mit „Informationen“, „Freigeben“, „Umbenennen“, „Herunterladen“ und „Löschen“.
10. **Strukturiertes Vereinsprofil:** Stammdaten bleiben sofort sichtbar; Adresse und Sichtbarkeit sind einklappbare Bereiche. Technische Sportwerte werden lokalisiert.
11. **Keine abgeschnittenen Adressfelder:** Straße und Hausnummer stehen jeweils in einer eigenen vollen Zeile.
12. **Rollen ohne Dauer-Dropdown:** Die Teamrolle wird über die klare Aktion „Rolle ändern“ geöffnet.
13. **Häufige Teamaktionen zuerst:** „Mitglied einladen“, „Kalender“ und „Kader“ stehen vor den Detailkennzahlen.
14. **Passender Event-Start:** „Event erstellen“ öffnet ein allgemeines Treffen statt automatisch ein Training.
15. **Kompaktere Formulare:** Lange Profilbereiche sind gruppiert und einklappbar; seltene Mitgliedsfunktionen wurden aus der Hauptnavigation entfernt.
16. **Sicheres Veröffentlichen:** Vor sofortiger Veröffentlichung zeigt die App die Zielgruppe an und verlangt eine Bestätigung.
17. **Eindeutige Bereichsnamen:** Die frühere zweite „Vereinsübersicht“ heißt jetzt „Kennzahlen“.
18. **Handlungsfähige Leerzustände:** Leere Mitglieder-, Team-, Event-, Mitteilungs- und Dokumentansichten erklären den Zustand und bieten die passende Erstaktion.
19. **Erfolg im Kontext:** Nach Einladung, Team- oder Event-Erstellung bleibt das Ergebnis im Arbeitskontext und bietet eine direkte Folgeaktion.
20. **Einheitliche visuelle Hierarchie:** Pro Bereich gibt es eine hervorgehobene Hauptaktion; ergänzende Aktionen bleiben visuell sekundär.

## Verifikation

- `flutter analyze --no-pub`: keine Befunde
- `flutter test --no-pub`: 339 Tests bestanden
- Sprachen der neuen Texte: Deutsch, Englisch, Französisch und Arabisch

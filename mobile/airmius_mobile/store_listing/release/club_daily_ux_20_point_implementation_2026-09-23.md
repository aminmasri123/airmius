# Vereinsalltag: Umsetzung der 20 UX-Punkte

Stand: 23. September 2026

1. Der Prüfstatus erscheint im Cockpit nur einmal.
2. Das Cockpit zeigt zunächst nur Prüfstatus, Kopfbereich, Heute und Schnellaktionen.
3. Die Finanzübersicht ist auf drei kompakte Werte reduziert.
4. Offene Rechnungen, offene Summe und Periodenergebnis sind direkt handlungsfähig.
5. Der erledigte Leerzustand ist kurz und eindeutig formuliert.
6. Die wichtigste Schnellaktion passt sich offenen Rechnungen oder Anträgen an.
7. Weitere Verwaltungsbereiche erscheinen als kompakte Zeilen.
8. Berichte heißen eindeutig „Berichte & Auswertung“ und sind von Finanzen getrennt.
9. Das Menü zeigt für Vereinsrollen nur relevante Hauptbereiche.
10. Menüpunkte sind unter „Vereinsverwaltung“ geordnet.
11. Doppelte Einstiege zu Teams, Terminen und Dateien wurden entfernt.
12. Die Arbeitsbereichsauswahl verwendet nur noch eine Auswahloberfläche.
13. Die Arbeitsbereichszahlen unterscheiden eigene, aktive und weitere Bereiche.
14. Arbeitsbereiche werden in kompakten einzeiligen Karten dargestellt.
15. „Heute“ zeigt den echten nächsten Termin und ungelesene Mitteilungen.
16. Die globale Suche findet berechtigte Rechnungen und öffnet deren Finanzbereich.
17. Drei Schnellaktionen lassen sich je Verein frei konfigurieren.
18. Vereinsbezogene Oberflächen verwenden den verständlicheren Begriff „Termin“.
19. Der Prüfstatus erklärt, was bereits nutzbar ist und wo das Ergebnis erscheint.
20. Einrichtung und seltene Verwaltungsbereiche bleiben nach der Startphase eingeklappt.

## Verifikation

- `flutter analyze --no-pub`: keine Befunde
- `flutter test --no-pub`: 340 Tests bestanden
- Relevante Laravel-Featuretests: 31 Tests bestanden
- Neue Texte liegen auf Deutsch, Englisch, Französisch und Arabisch vor.

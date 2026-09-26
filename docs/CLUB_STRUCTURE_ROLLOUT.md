# Einführung von Vereinsorganisation und Gremien

## Freigabeprinzip

Die Tabellen für Abteilungen, Standorte, Trainingsgruppen, Mannschaftszuordnungen, Gremien und Verantwortlichkeiten sind additiv. Bestehende Mannschaften bleiben ohne automatische Zuordnung erhalten. Historische Organisations- oder Amtsinformationen dürfen nicht aus Namen, Sportarten oder aktuellen Mitgliedschaften abgeleitet werden.

Der strikt lesende Prüfer erzeugt ausschließlich aggregierte Anzahlen:

```bash
php artisan airmius:audit-club-structure --json
php artisan airmius:audit-club-structure --with-data --json
php artisan airmius:audit-club-structure --with-data --evidence=/geschuetzter/pfad/evidence.json --strict
```

Ohne `--with-data` wird keine Datenbank gelesen. Mit `--with-data` prüft der Befehl Tabellen und Spalten, Vereinsgrenzen von Organisationsverknüpfungen, die Übereinstimmung zwischen Trainingsgruppe und Mannschaft, gültige Gremientypen und Zeiträume sowie genau eine vereinszugehörige Person je Verantwortlichkeit. Er schreibt keine Daten und gibt weder Vereins-, Datensatz- oder Personenkennungen noch Namen, Anschriften, Kontakte oder Freitexte aus.

## Migrationsprobe

1. Freigegebene, datenschutzgerecht geschützte Staging-Kopie und wiederherstellbares Backup bereitstellen.
2. Additive Migrationen ausführen und den Prüfer mit `--with-data` starten.
3. Aggregierte Fehler im Quellsystem fachlich klären. Keine automatische Zuordnung oder Korrektur durch den Prüfer.
4. Rollback in einer separaten Probe testen. Vorhandene Mannschaften und Vereinsmitgliedschaften müssen unverändert bleiben.
5. Dieselbe Migrationsfolge erneut ausführen und die aggregierten Ergebnisse vergleichen.

## Oberflächenabnahme

Die Vorlage `resources/release/club_structure_evidence.template.json` verlangt Web-Mobile, Web-Desktop, Android und iOS sowie sieben Abläufe:

- Organisation verwalten und intern/öffentlich anzeigen;
- Mannschaft einer konsistenten Abteilung, Trainingsgruppe und einem Standort zuordnen;
- Gremien und Verantwortlichkeiten verwalten und intern/öffentlich anzeigen;
- interne oder externe Person eindeutig zuordnen;
- Vereinsgrenzen und Bearbeitungsrechte nachweisen.

Jeder Ablauf muss als berechtigte Verwaltungsperson, lesendes Mitglied und soweit zutreffend als öffentliche Person geprüft werden. Abbruch, Validierungsfehler, RTL und erneutes Laden gehören zur Abnahme.

## Evidenz und Go/No-Go

Die lokale Evidenzdatei bleibt außerhalb des Repositorys. Zulässig sind nur kurze Referenzen aus Buchstaben, Zahlen, Punkt, Doppelpunkt, Unterstrich und Bindestrich. URLs, Dateipfade, Namen, Kontakte, Screenshots, Freitext und Zugangsdaten sind nicht zulässig.

`--strict` liefert nur dann Erfolg, wenn der Runtime-Audit fehlerfrei ist, Backup, Migrationsprobe und Rollback bestätigt sind, alle Oberflächen und Abläufe `passed` melden und Produkt sowie Engineering freigegeben haben. Andernfalls bleibt die Entscheidung `no-go`.

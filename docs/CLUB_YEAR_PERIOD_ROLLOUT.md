# Einführung der Vereinsjahre

Diese Anleitung gilt für die additive Einführung von Geschäfts-, Beitrags- und Sportjahren. Sie erlaubt keine automatische Rückdatierung bestehender Rechnungen, Bankvorgänge, Buchungen, Termine oder Mannschaften.

## Grundsatz für Bestandsdaten

Die Migrationen legen ausschließlich neue Tabellen und nullable Fremdschlüssel an. Historische Datensätze bleiben `null` und werden in Berichten sowie Saisonplanungen als unzugeordnet ausgewiesen. Ein Datum ist kein ausreichender Nachweis für eine historische Periodenzuordnung. Falls ein Verein Bestandsdaten zuordnen möchte, muss dies in einem gesonderten, fachlich freigegebenen Vorgang mit dokumentierter Auswahl erfolgen.

Der Audit ist immer lesend:

```bash
php artisan airmius:audit-club-year-periods --json
php artisan airmius:audit-club-year-periods --with-data --json
```

Die erste Variante prüft Repository und Freigabevertrag. `--with-data` prüft zusätzlich aggregiert:

- ob alle additiven Tabellen und Spalten vorhanden sind,
- ob gespeicherte Fremdschlüssel zum selben Verein und zum richtigen Periodentyp gehören,
- ob sich Perioden desselben Typs innerhalb eines Vereins überschneiden,
- wie viele Perioden und historisch unzugeordnete Vorgänge vorhanden sind.

Der Bericht enthält keine Vereins-, Personen-, Rechnungs- oder Termin-IDs und verändert keine Zeile.

## Kontrollierte Reihenfolge

1. Datenbank-Backup erstellen und Wiederherstellung in einer isolierten Umgebung prüfen.
2. Migrationen auf einer aktuellen anonymisierten oder freigegebenen Kopie ausführen.
3. `airmius:audit-club-year-periods --with-data --json` ausführen und alle Fehler beheben. Unzugeordnete Bestände sind erwartete Inventarwerte und kein Fehler.
4. Web Mobile und Web Desktop in DE/EN/FR/AR prüfen: Periodenverwaltung, Periodenberichte, historisch unzugeordnete Darstellung und Mannschafts-Saisonplanung.
5. Dieselben vier Wege in Android und iOS prüfen. Die App muss eine Mannschaft bewusst unzugeordnet lassen können und darf beim Auswählen eines Sportjahrs keine Termine verändern.
6. Rollback der additiven Migrationen nur auf einer isolierten Kopie proben. Vor einem echten Rollback müssen alle neuen Referenzen fachlich gesichert sein.
7. Produkt- und Engineering-Freigabe mit kurzen, nicht sensiblen Evidenzreferenzen dokumentieren.
8. Erst danach den strengen Audit ausführen und die Einführung freigeben.

## Evidenzvertrag

Die Vorlage liegt unter `resources/release/club_year_period_evidence.template.json`. Sie wird in einen geschützten lokalen Evidenzordner kopiert und dort ausgefüllt. Screenshots, Pfade, URLs, Kontaktdaten, Vereinsnamen, Zugangsdaten und sonstige personenbezogene Daten gehören nicht in die JSON-Datei. Zulässig sind ausschließlich kurze Referenzschlüssel.

```bash
php artisan airmius:audit-club-year-periods \
  --with-data \
  --evidence=/geschuetzter/pfad/club-year-period-evidence.json \
  --strict \
  --json
```

`decision=go` wird nur ausgegeben, wenn Schema und Bestandsreferenzen konsistent sind, keine Überschneidung besteht, Backup/Dry-run/Rollback bestätigt wurden, alle vier Oberflächen und alle vier Kernwege bestanden sind und Produkt sowie Engineering zugestimmt haben. Fehlende reale Browser- oder Gerätenachweise bleiben ausdrücklich `no-go`.

## Abbruchkriterien

Die Einführung wird gestoppt bei falschen Vereins- oder Typreferenzen, Periodenüberschneidungen, fehlendem Backup, fehlgeschlagenem Wiederherstellungstest, Veränderungen historischer Zuordnungen, nicht nachvollziehbaren Berichtsdifferenzen, Terminänderungen durch eine Mannschaftsauswahl oder einem Fehler auf einer der vier Oberflächen.

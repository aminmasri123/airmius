# Einführung eigener Daten und Nummernkreise

Diese Anleitung gilt für die additive Einführung eigener Felder, Kategorien und Nummernkreise. Sie erlaubt keine automatische Änderung bestehender Mitglieds-, Rechnungs-, Beleg-, Spenden-, Inventar- oder Shopnummern.

## Kontrollierte Reihenfolge

1. Datenbank sichern und die Wiederherstellung in einer isolierten Umgebung prüfen.
2. Migrationen auf einer aktuellen anonymisierten oder ausdrücklich freigegebenen MySQL-Kopie ausführen.
3. `php artisan airmius:audit-club-number-ranges --with-data --json` ausführen. Duplikate, Kollisionen und ungültige Referenzen sind Abbruchkriterien. Fehlende Standardkreise müssen fachlich entschieden werden.
4. Den integrierten Parallelitätstest auf dieser isolierten MySQL-Kopie mit der ID eines dort vorhandenen Vereins ausführen. Der Befehl startet zwei getrennte PHP-Prozesse und damit zwei getrennte Datenbankverbindungen, prüft zwei unterschiedliche lückenlose Reservierungen und entfernt seine temporären Nummernkreis-, Vergabe- und Auditdaten wieder:

```bash
php artisan airmius:verify-club-number-range-concurrency \
  --club=VEREINS_ID_DER_ISOLIERTEN_KOPIE \
  --confirm-isolated \
  --json
```

   Nur ein Ergebnis mit `contract=club-number-range-mysql-concurrency.v1`, `passed=true` und ausschließlich `pass` in `checks` darf als MySQL-Konkurrenznachweis verwendet werden. Der Befehl verweigert die Ausführung in Produktion sowie ohne `--confirm-isolated`. Ein Fehler muss Zähler, Vergabe und Fachdatensatz gemeinsam zurückrollen.
5. Web Mobile und Web Desktop in DE/EN/FR/AR prüfen: Konfiguration, Wertepflege, Standardvergabe und unverändertes Fallback ohne Standardkreis.
6. Dieselben Wege auf einem realen Android- und iOS-Gerät prüfen. Unklare Schreibfehler dürfen weder automatisch wiederholt noch offline gespeichert werden.
7. Rollback der additiven Migrationen nur auf einer isolierten Kopie proben. Vor einem echten Rollback müssen neue Feldwerte, Kategorien, Vergaben und kanonische Zahlungsnummern fachlich gesichert sein.
8. Produkt- und Engineering-Freigabe mit kurzen, nicht sensiblen Referenzschlüsseln dokumentieren.
9. Erst dann den strikten Audit mit der ausgefüllten Evidenzdatei ausführen.

## Evidenzvertrag

Die Vorlage `resources/release/club_number_range_evidence.template.json` wird in einen geschützten lokalen Evidenzordner kopiert. Screenshots, Dateipfade, URLs, Zugangsdaten, Namen, Nummern oder andere Identifikatoren gehören nicht in die JSON-Datei. Zulässig sind nur kurze Referenzschlüssel.

```bash
php artisan airmius:audit-club-number-ranges \
  --with-data \
  --evidence=/geschuetzter/pfad/club-number-range-evidence.json \
  --strict \
  --json
```

`decision=go` wird nur ausgegeben, wenn der lesende Bestandsaudit vollständig grün ist und Migration, MySQL-Konkurrenztest, Web Mobile, Web Desktop, Android, iOS, alle vier Kernabläufe sowie Produkt und Engineering bestätigt sind.

## Abbruchkriterien

- doppelte Bestandsnummern innerhalb eines Vereins und Geltungsbereichs,
- Kollisionen zwischen Vergaben und gespeicherten Fachnummern,
- ungültige Vereins-, Geltungsbereichs- oder Fachobjektreferenzen,
- fehlerhafte oder inaktive Standardkreise,
- wiederholte Nummern unter paralleler MySQL-Last,
- Teilzustände nach Fehler oder Rollback,
- Überschreiben bestehender Nummern,
- automatische Wiederholung oder Offline-Speicherung einer nicht eindeutig bestätigten Schreibaktion,
- fehlgeschlagener Browser- oder Realgeräteweg.

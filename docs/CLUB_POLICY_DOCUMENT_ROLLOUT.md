# Einführung versionierter Vereinsdokumente

Die Einführung bleibt gestoppt, bis der strikte Read-only-Prüfer `go` meldet. Der Prüfer verändert keine Daten und gibt ausschließlich aggregierte Zähler aus.

## Ablauf

1. Datenbank sichern und Wiederherstellung auf einer isolierten Kopie prüfen.
2. Migrationen `000025` und `000026` auf dieser Kopie ausführen und Rollback proben.
3. `php artisan airmius:audit-club-policy-documents --with-data --json` ausführen. Ungültige Datei- oder Regelverweise und überlappende Fassungen müssen null sein. Nicht verknüpfte Bestandsregeln sind zulässiger, ausdrücklich unveränderter Bestand.
4. Web mobil, Web Desktop, Android und iOS anhand aller Journeys der Evidenzvorlage abnehmen. Es dürfen nur Referenzkennungen, keine Dateipfade, Mitgliedsdaten oder Screenshots in die JSON-Datei eingetragen werden.
5. Produkt- und Engineering-Freigabe dokumentieren und anschließend `php artisan airmius:audit-club-policy-documents --with-data --evidence=/geprüfter/pfad/evidence.json --strict` ausführen.
6. Nur bei Exitcode `0` und Entscheidung `go` kontrolliert einführen. Bei Fehlern Rollout stoppen; additive Spalten und Tabellen bleiben bis zur geklärten Wiederholung unangetastet.

## Abnahmewege

- Öffentliche Fassungen sind bei gelisteten Vereinen sichtbar, interne Fassungen bleiben geschützt.
- Mitglieder lesen interne und öffentliche Fassungen; Vereinsfremde erhalten keinen internen Download.
- Vereinsverwaltung legt Fassungen an, bearbeitet sie und löscht ausschließlich nicht verwendete Fassungen.
- Beitragsregeln lassen sich nur mit passenden Beitragsmodellfassungen desselben Vereins und eines deckenden Zeitraums verknüpfen.
- Historische Zuordnungen bleiben nach späteren Fassungen, Regeländerungen und erneuter Anmeldung erhalten.

Die Vorlage liegt unter `resources/release/club_policy_document_evidence.template.json`. Produktionsmigration, Veröffentlichung und Freigaben erfolgen außerhalb dieses Repository-Schritts.

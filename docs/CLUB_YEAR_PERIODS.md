# Geschäfts-, Beitrags- und Sportjahre

## Datenmodell

`club_year_periods` speichert benannte Zeiträume für genau einen Verein. Erlaubte Typen sind:

- `business`: Geschäftsjahre für Buchhaltung und Berichte,
- `contribution`: Beitragsjahre für Mitgliedsbeiträge,
- `sport`: Sportjahre beziehungsweise Saisons.

Jeder Zeitraum besitzt ein Start- und Enddatum. Zeiträume verschiedener Typen dürfen sich vollständig überschneiden und dadurch voneinander abweichende Jahresgrenzen abbilden. Zeiträume desselben Typs dürfen sich einschließlich ihrer Grenztage nicht überschneiden.

Neue Vereinsrechnungen, Bankvorgänge und manuelle Kassen- oder Bankbuchungen erhalten beim Anlegen ein optionales Geschäftsjahr. Automatisch erzeugte Beitragsrechnungen erhalten zusätzlich ein Beitragsjahr; manuelle Rechnungen werden nicht fälschlich als Beitrag gewertet. Neue Vereins- und Mannschaftstermine erhalten ein Sportjahr. Bestehende Datensätze bleiben leer. Gespeicherte Zuordnungen werden bei einer späteren Datumsänderung nicht neu berechnet.

## API und Berechtigungen

`GET /api/v1/clubs/{club}/year-periods` liefert die nach Typ und Beginn sortierten Vereinsjahre sowie den aus dem aktuellen Datum berechneten Status `past`, `current` oder `upcoming`. Vereinsverwaltung und Mitglieder dürfen lesen. Anlegen, Ändern und Löschen über `/year-periods` ist ausschließlich mit der bestehenden Vereinsberechtigung `update` erlaubt.

Verschachtelte Zeiträume eines anderen Vereins werden als nicht gefunden behandelt. Gleichnamige Zeiträume sind innerhalb eines Typs und Vereins unzulässig. Jede Schreibaktion erzeugt einen `club.year_period.*`-Auditeintrag, der keine Namen oder Datumswerte enthält.

`GET /api/v1/clubs/{club}/year-periods/report?type={type}&period_id={id|unassigned}` liefert eine gefilterte Auswertung. Geschäftsjahre fassen Rechnungen, Bankvorgänge und manuelle Buchungen zusammen. Beitragsjahre fassen nur Beitragsrechnungen zusammen. Sportjahre zählen Termine nach Status und Art. Der Antwortblock `unassigned` weist historische Datensätze ohne gespeicherte Zuordnung immer getrennt aus. Der Server leitet für diese Datensätze keine Periode aus dem Datum ab.

Berichte dürfen ausschließlich Personen mit `finance.view` lesen. Die angeforderte Periode muss zum Verein und zum gewählten Typ gehören. Verwendete Zeiträume sind über Anwendung und restriktive Fremdschlüssel gegen Löschen geschützt.

Rechnungs-, Buchungs-, Bankvorgangs- und Terminantworten enthalten die gespeicherte Perioden-ID sowie bei geladenen Detaildaten Name und Grenzen. `null` bleibt fachlich sichtbar und bedeutet „historisch unzugeordnet“; es löst keine Datumsableitung aus.

Mannschaften können optional genau einem Sportjahr ihres Vereins zugeordnet werden. Fremde Vereinszeiträume und Zeiträume eines anderen Typs werden abgewiesen. Die Team-Alltags- und Wettbewerbs-API liefert die gewählte Periode, den Auswahlstatus sowie getrennte Zahlen für historisch unzugeordnete und außerhalb der Auswahl liegende Termine. Änderungen werden als `club.sport_year.team_assigned` protokolliert.

## Web

Das Vereinsprofil zeigt die drei Jahrestypen getrennt mit Namen, Datumsgrenzen und aktuellem Status. Der Abschnitt wird ausschließlich für Vereinsmitglieder und Vereinsverwaltung geladen. Berechtigte Personen können Zeiträume anlegen, bearbeiten und nach Bestätigung löschen. Personen mit Finanzleserecht können jede Periode oder den unzugeordneten historischen Bestand auswerten. Die Web-Oberfläche besitzt programmatische Feldnamen und Texte in DE/EN/FR/AR.

In der Mannschaftsverwaltung kann ein Sportjahr gewählt oder die Mannschaft bewusst unzugeordnet gelassen werden. Die Auswahl steuert nur die Saisonplanungskennzahlen und ändert keine bestehenden Termine. Mannschaftslisten zeigen den gespeicherten Namen oder den historischen unzugeordneten Zustand.

## Native App

Die Vereinsorganisation verlinkt für Mitglieder und Vereinsverwaltung eine eigene Ansicht der Vereinsjahre. Sie gruppiert die vom Server gelieferten Zeiträume nach Typ und zeigt Status sowie Datumsgrenzen. Berechtigte Vereinsverwaltung kann Zeiträume in einem nativen Dialog anlegen, bearbeiten und nach Bestätigung löschen.

Pflichtname und Datumsreihenfolge werden vor dem Versand geprüft. Serverseitige Überschneidungs- und Vereinsregeln bleiben maßgeblich. Schreibaktionen werden weder offline gespeichert noch nach einem Verbindungsabbruch automatisch wiederholt. Personen mit Finanzleserecht können dieselben Periodenberichte wie im Web öffnen. Termindetails zeigen das gespeicherte Sportjahr oder den historischen unzugeordneten Zustand. Die Oberfläche steht in DE/EN/FR/AR bereit.

Die native Mannschaftsbearbeitung lädt die Sportjahre des Vereins, speichert eine ausdrückliche Auswahl und zeigt das verwendete Sportjahr in der Saisonplanung. Ein unzugeordneter Zustand bleibt auswählbar und wird sichtbar benannt.

## Saisonplanung

Hat eine Mannschaft ein Sportjahr gewählt, zählen Team-Alltag und Wettbewerbsplanung ausschließlich Termine mit derselben gespeicherten `sport_year_period_id`. Die Auswertung leitet keine Zuordnung aus dem Termindatum ab. Termine anderer Perioden und historisch unzugeordnete Termine erscheinen als getrennte Kennzahlen. Operative Funktionen wie nächster Termin und Anwesenheit bleiben vereins- und mannschaftsweit unverändert.

Ohne Mannschaftszuordnung gilt aus Kompatibilitätsgründen die bisherige Planung über alle Mannschaftstermine. Die Antwort kennzeichnet diesen Zustand mit `period_selection=unassigned`. Eine spätere Auswahl verändert weder bestehende Termine noch deren eingefrorene Periodenzuordnung.

## Export

Der DATEV-Export verändert seine standardisierte Spaltenstruktur nicht. Im Buchungstext werden das gespeicherte Geschäftsjahr und bei Beitragsrechnungen das gespeicherte Beitragsjahr vorangestellt. Nicht zugeordnete historische Datensätze werden ausdrücklich als `GJ unzugeordnet` beziehungsweise `BJ unzugeordnet` exportiert. Rücklastschriftgebühren verwenden den Geschäftsjahr-Verweis ihrer Buchung.

## Noch folgende Arbeit

T014a bis T014d4 bilden Verwaltung, nicht rückwirkende Zuordnungen, Periodenberichte, sichtbare Periodenbezüge sowie Mannschafts- und Saisonplanung ab. T014e1 ergänzt den datensparsamen Read-only-Bestandsaudit und den versionierten Rolloutvertrag. Die genaue Staging-, Browser- und Realgerätefolge steht in `docs/CLUB_YEAR_PERIOD_ROLLOUT.md`.

T014e2 und T014e3 bleiben offen: Backup/Wiederherstellung und additive Migration müssen auf einer freigegebenen Staging-Kopie geprüft werden; anschließend folgen echte Web-Mobile-, Web-Desktop-, Android- und iOS-Abnahmen sowie Produkt-/Engineering-Freigabe. Es wurde keine produktive Migration ausgeführt.

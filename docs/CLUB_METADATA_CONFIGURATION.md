# Eigene Datenfelder, Kategorien und Nummernkreise

Stand: 24.09.2026 – Backend-Grundlage T015a und Werteanbindung T015b1

## Umfang

Die additive Metadatenkonfiguration stellt drei vereinsgebundene Bausteine bereit:

- eigene Felddefinitionen für Mitglieder, Mannschaften, Termine und Inventargegenstände,
- Kategorien für Mitglieder, Mannschaften, Termine, Inventar, Dokumente und Finanzen,
- Nummernkreise für Mitglieder, Rechnungen, Belege, Spenden und Inventar.

T015a speichert Konfigurationen und explizit angeforderte Nummernvergaben. T015b1 ergänzt typsicher geprüfte Feldwerte und Kategoriezuordnungen für interne sowie externe Mitglieder, Mannschaften, Termine und Inventargegenstände. Die vorhandenen Mitglieds-, Rechnungs- und Shopnummern werden noch nicht umgestellt und arbeiten daher unverändert weiter.

## Schutz und Berechtigungen

Alle Endpunkte erfordern die bestehende `update`-Berechtigung des Vereins. Vereinsmitglieder ohne Verwaltungsrecht und Personen anderer Vereine erhalten keinen Zugriff auf die Konfiguration. Untergeordnete Datensätze werden zusätzlich gegen die Vereins-ID der Route geprüft.

Eigene Felder können als sensibel markiert werden. Konfiguration und Werte sind über die dedizierte API ausschließlich für die bestehende Vereinsverwaltung lesbar. Feldschlüssel, Bezeichnungen, Auswahlwerte, Kategorienamen, Präfixe, Feldwerte und vergebene Nummern werden nicht in Auditdaten übernommen. Das Audit enthält nur den technischen Entitätstyp. Spätere Web-/App-Oberflächen und Exporte müssen dieselbe Grenze einhalten.

## Feldwerte und Kategoriezuordnungen

`GET|PUT /api/v1/clubs/{club}/metadata/subjects/{subjectType}/{subjectId}` liest beziehungsweise ersetzt die aktiven Werte und Kategoriezuordnungen eines Vereinsobjekts. Zulässige feste Zieltypen sind `member`, `external_member`, `team`, `event` und `inventory_item`. Der Server löst jedes Ziel innerhalb des Vereins auf; frei übertragbare Modellklassen sind nicht erlaubt.

Die Werte werden anhand der gespeicherten Definition normalisiert und geprüft:

- Text bis 1.000 und Langtext bis 10.000 Zeichen,
- Dezimalzahlen mit bis zu zwölf Vor- und vier Nachkommastellen,
- echte ISO-Daten im Format `YYYY-MM-DD`,
- echte JSON-Boolean-Werte,
- exakte Werte aus der konfigurierten Auswahlliste.

Ein `PUT` ersetzt alle aktiven optionalen Werte und Zuordnungen. Aktive Pflichtfelder müssen enthalten sein. Inaktive historische Werte und Kategoriezuordnungen bleiben erhalten und sichtbar, damit eine Konfigurationsänderung keine Bestandsdaten still löscht. Verwendete Felddefinitionen können weder gelöscht noch in Typ, Schlüssel oder Geltungsbereich umgedeutet werden; bei verwendeten Kategorien ist der Geltungsbereich gesperrt.

Beim Löschen eines Mitgliedsobjekts, einer Mannschaft, eines Termins oder Inventargegenstands werden dessen generische Metadaten entfernt. Auch das Entfernen oder der Austritt eines internen Mitglieds aus einem Verein löscht die zu diesem Verein gehörenden Werte und Zuordnungen innerhalb derselben Transaktion.

## Nummernvergabe

Eine Vergabe läuft in einer Datenbanktransaktion und sperrt den Nummernkreis während der Fortschreibung. Jede Anfrage braucht einen UUID-Idempotenzschlüssel. Eine Wiederholung mit demselben Schlüssel liefert dieselbe Vergabe und erhöht den Zähler nicht erneut.

Nummernkreise können dauerhaft zählen oder jährlich auf ihre Startnummer zurückgesetzt werden. Jährliche Nummernkreise müssen `{YYYY}` oder `{YY}` im Präfix oder Suffix enthalten, damit Werte verschiedener Jahre eindeutig bleiben. Nach der ersten Vergabe sind Geltungsbereich, Format, Startnummer und Rücksetzregel gesperrt; Name und Aktivstatus bleiben änderbar. Ein Nummernkreis mit Vergaben kann nicht gelöscht werden.

Je Verein und Geltungsbereich kann genau ein aktiver Nummernkreis ausdrücklich als Standard zugeordnet werden. Diese Zuordnung schaltet keine Fachfunktion um. Sie dient als geprüfte Voraussetzung für die nachfolgenden Integrationsschritte und schützt den Kreis zusätzlich gegen Löschen oder Änderung seines Geltungsbereichs. Die Read-only-Einführungsprüfung ist in `docs/CLUB_NUMBER_RANGE_ADOPTION.md` beschrieben.

SQLite-Verhalten ist automatisiert geprüft. Ein echter Konkurrenztest mit parallelen Verbindungen unter der vorgesehenen MySQL-Version bleibt Bestandteil von T015e.

## API

- `GET /api/v1/clubs/{club}/metadata`
- `POST|PUT|DELETE /api/v1/clubs/{club}/metadata/custom-fields[/{customField}]`
- `POST|PUT|DELETE /api/v1/clubs/{club}/metadata/categories[/{category}]`
- `POST|PUT|DELETE /api/v1/clubs/{club}/metadata/number-ranges[/{numberRange}]`
- `POST /api/v1/clubs/{club}/metadata/number-ranges/{numberRange}/allocate`
- `PUT|DELETE /api/v1/clubs/{club}/metadata/number-ranges/{numberRange}/default`
- `GET|PUT /api/v1/clubs/{club}/metadata/subjects/{subjectType}/{subjectId}`

Die Vergabeantwort enthält die erzeugte Nummer, damit ein später angebundener Fachvorgang sie atomar übernehmen kann. Diese fachliche Anbindung wird erst in T015b vorgenommen; ein Aufruf des Endpunkts allein erstellt weder Mitglied noch Rechnung oder Inventargegenstand.

## Angebundene Nummernwege

T015b2b bindet ausdrücklich erzeugte interne Mitgliedsnummern, manuelle Vereinsrechnungen, wiederkehrende Beitragsrechnungen und manuelle Admin-Rechnungen mit Vereinsbezug an ihren jeweiligen Standardkreis an. Die Vergabe und das Schreiben des Fachdatensatzes laufen in derselben Transaktion. Jede verwendete Vergabe speichert anschließend nur den technischen Zieltyp und die Ziel-ID; ihre Nummer bleibt unveränderlich.

Ohne Standardkreis verwenden diese Wege weiterhin ihre bisherigen Formate. Eine im Admin-Rechnungsformular ausdrücklich eingegebene Nummer wird ebenfalls unverändert übernommen. Fachlich fest definierte Sondernummern, etwa Gebührenweiterbelastungen, werden nicht durch einen allgemeinen Standardkreis ersetzt.

Vor dem Schreiben wird die nächste Nummer gegen Mitgliedsnummern desselben Vereins beziehungsweise vorhandene Rechnungsnummern geprüft. Bei einer Kollision werden Fachänderung, Vergabe, Audit und Zählerfortschritt vollständig zurückgerollt. Automatische Beitragsrechnungen sperren den Verein, prüfen den Abrechnungszeitraum erneut und erzeugen bei parallelem oder wiederholtem Lauf höchstens eine Rechnung. Der echte Mehrverbindungsnachweis unter MySQL bleibt Teil von T015e.

T015b2c ergänzt getrennte kanonische Beleg- und Spendennummern, ohne Bank- oder Freitextreferenzen umzudeuten. Leere Inventar- und Shop-SKUs sowie vereinsbezogene Shoprechnungen und -gutschriften verwenden ihren Standardkreis. Manuell gesetzte Werte bleiben unverändert; Kollisionen rollen Vergabe, Zähler und Fachdatensatz gemeinsam zurück.

# Rücklastschriften aus CSV und ISO-20022-XML importieren

Dieser Import ordnet bestätigte Bankrückgaben einem ausdrücklich ausgewählten,
bereits exportierten SEPA-Lauf zu. Der bestehende Import positiver Bankzahlungen
bleibt ein eigener Ablauf. Dies ist keine direkte Bankanbindung.

## CSV-Dateiformat

UTF-8, maximal 2 MiB und 200 Datenzeilen. Semikolon, Komma oder Tabulator als
Trennzeichen; Dezimalwerte bei Komma als Trennzeichen entsprechend in Anführungszeichen setzen.
Im Standardformat sind die folgenden sechs Spalten erforderlich; zusätzlich ist
`iban` erlaubt. Ohne ausdrückliche Zuordnung werden unbekannte Spalten abgewiesen.
Doppelte oder leere Überschriften werden immer abgewiesen.

```csv
end_to_end_id;booking_date;amount;currency;reference;reason
R-1;2026-10-11;-80,00;EUR;BANK-RETURN-1;MS03
```

Das Beispiel ist fiktiv. Datum, Betrag, Referenz und Grund müssen aus den tatsächlichen
Bankunterlagen übernommen werden.

- `end_to_end_id`: eingefrorene Rechnungsnummer im gewählten Lauf. Dieselbe Nummer
  kann in einem späteren Wiederholungslauf vorkommen; deshalb ist die Laufwahl zwingend.
- `booking_date`: `JJJJ-MM-TT` oder `TT.MM.JJJJ`, nicht vor Einzug beziehungsweise
  zugeordnetem Zahlungseingang und nicht in der Zukunft.
- `amount`: negativer exakter Einzugsbetrag, höchstens zwei Nachkommastellen,
  keine Tausendertrennzeichen. Nur EUR.
- `reference`: eindeutige Bankreferenz, maximal 180 Zeichen nach Großschreibung.
- `reason`: Rückgabegrund, maximal 2000 Zeichen.
- `iban`: optional; falls angegeben, muss sie dem eingefrorenen Schuldnerkonto entsprechen.

## ISO-20022-XML

Zusätzlich werden die benötigten Rückgabedaten aus `pain.002`, `camt.053` und
`camt.054` Dateien bis 2 MiB und 200 erkannten Rückgaben gelesen. Die Erkennung
erfolgt aus dem XML-Inhalt und dem ISO-20022-Namensraum, nicht aus Dateiname oder
MIME-Typ. Der Import ersetzt keine vollständige XSD-Validierung einer Bankdatei.
DTD-/Entity-Deklarationen, fremde Namensräume und andere Dokumenttypen werden
abgewiesen; externe Ressourcen werden nicht geladen.

Für `pain.002` werden ausschließlich Transaktionen mit `TxSts=RJCT` verarbeitet.
End-to-End-ID, ursprünglicher EUR-Betrag, Rückgabegrund und gegebenenfalls
Schuldner-IBAN stammen aus der ursprünglichen Transaktionsreferenz. Als Datum gilt
vorrangig das ursprüngliche Einzugsdatum, danach ein transaktionsbezogener
Annahmezeitpunkt und zuletzt der Erstellungszeitpunkt der Bankmeldung. Eine
ursprüngliche Instruktions-ID dient als Referenz; fehlt sie, wird die Kombination
aus Bank-Meldungs-ID und End-to-End-ID verwendet.

Bei `camt.053/054` werden nur Sollbuchungen (`DBIT`) mit einer ausdrücklichen
`RtrInf`-Struktur verarbeitet. Buchungsdatum, transaktionsbezogener Betrag,
Währung, End-to-End-ID, Bankreferenz und Rückgabegrund müssen vorhanden sein.
Ein Betrag auf Eintragsebene wird nur verwendet, wenn der Eintrag genau eine
Rückgabetransaktion enthält. XML-Beträge werden für die gemeinsame Prüfung intern
als negativer Rückgabebetrag normalisiert.

Die Unterstützung bedeutet keine beliebige Interpretation bankspezifischer XML-
Erweiterungen oder Sammelbuchungen. Nicht eindeutig auf eine einzelne exportierte
Laufposition abbildbare Daten bleiben in der Vorschau ungültig oder werden als
nicht unterstütztes Dokument abgewiesen.

## Ablauf und Schutz vor Fehlbuchungen

1. Exportierten Lauf öffnen und CSV-/XML-Vorschau laden.
2. Jede Zeile anhand der Bankunterlagen prüfen. Eine fehlerhafte Zeile verhindert den gesamten Import.
3. Bereits gebuchte Zahlungseingänge zunächst mit der jeweiligen Laufposition verknüpfen.
   Ohne verknüpften Eingang verlangt der Import eine zusätzliche Bestätigung und nimmt keine Zahlung zurück.
4. Rückgaben ausdrücklich bestätigen und importieren.

### Abweichende Banküberschriften zuordnen

Für CSV bieten Web und App nach der Dateiauswahl „Datei prüfen / Spalten zuordnen“.
XML-Felder sind durch den jeweiligen ISO-20022-Dokumenttyp festgelegt und werden
nicht manuell zugeordnet. Eine CSV-Datei darf höchstens
50 Spalten enthalten. Die Kopfzeile muss die erste Zeile sein; Vorbemerkungen oder
Summenzeilen sind nicht Teil dieses Formats. Die Spaltenprüfung liefert nur
Überschriften und Zeilenanzahl, keine Buchung und keine Zahlungsdatenzeilen.

Jedes Pflichtfeld muss einer eigenen Spalte zugeordnet werden. Die IBAN bleibt
optional. Alle übrigen Spalten werden namentlich angezeigt und müssen ausdrücklich
vom Import ausgeschlossen werden. Eine Gebühren-Spalte wird dadurch nicht verbucht;
Gebühren sind weiterhin ein separater offener Prozess. Ein Betrag einschließlich
Gebühren wird weiterhin abgewiesen, wenn er nicht dem Einzugsbetrag entspricht.

Die Vorschau zeigt ausgeschlossene Spalten erneut. Änderungen an Zuordnung oder
Ausschlussbestätigung verwerfen in der Oberfläche die Vorschau. Beim endgültigen
Import verwendet der Server ausschließlich die im verschlüsselten Vorschautoken
gespeicherte Zuordnung; nachträglich mitgesendete Zuordnungen werden abgewiesen.
Alle Betrags-, Datums-, Referenz- und Positionsprüfungen gelten unverändert.

Die Vorschau gilt 15 Minuten und ist an Datei, Person, Lauf und geprüften Zustand
gebunden. Veränderte Daten erfordern eine neue Vorschau. Der Import speichert alle
Zeilen gemeinsam in einer Datenbanktransaktion. Eine exakt identische Wiederholung
wird als bereits erfasst behandelt; abweichende Rückgaben werden abgewiesen.
Andere Teilzahlungen bleiben erhalten. Ein erneuter Einzug erfordert weiterhin die
separate Wiederholungsfreigabe und einen neuen SEPA-Lauf.

## Prüfnachweis

Die finale fokussierte Zahlungs-/SEPA-Regression besteht mit 105 Tests und 1385
Assertions. Drei eigene XML-Verhaltenstests mit 50 Assertions decken `pain.002`,
`camt.053`, `camt.054`, Fremdnamensräume, DTD/Entities, Habenbuchungen und nicht
abgewiesene Status ab. Web-Komponententests und isolierter Web-Build sind grün.
Die fokussierte native Importsuite besteht mit acht Tests; die vollständige
Flutter-Suite mit 388 Tests und die statische Analyse ohne Befund.
Die vollständige Backend-Regression gegen den isolierten aktuellen Web-Build
umfasst 1213 bestandene und vier übersprungene Tests mit 29971 Assertions.

Fachliche Referenzen sind die EPC-Erläuterung zu SEPA-Lastschriften und
R-Transaktionen sowie die EPC-Leitlinie zu SDD-Rückgabegründen. Die konkrete
Produktivfreigabe muss weiterhin gegen die Dokumentation und Beispieldateien des
eingesetzten Kreditinstituts erfolgen.

## Grenzen dieses Zwischenstands

- Web-Oberfläche, API und native Flutter-Importoberfläche vorhanden. In der App
  erscheint der Import bei exportierten Läufen für Personen mit Finanzverwaltungsrecht.
  Dateien werden direkt als Multipart-Upload übertragen; keine Offline-Warteschlange
  und keine automatische Wiederholung. Nach einem Importfehler muss eine neue Vorschau
  geladen werden. Geräte- und Browserabnahme bleiben gesondert erforderlich.
- CSV-Spaltenzuordnung sowie strikter Parser für `pain.002` und explizite
  Rückgabetransaktionen in `camt.053/054` vorhanden. Weitere bankspezifische
  Erweiterungen und uneindeutige Sammelbuchungen benötigen eigene geprüfte Profile.
- Gebühren und Weiterbelastungen bleiben getrennte Prozesse. Zusammengefasste
  Rückgabebeträge einschließlich Gebühren werden nicht als Einzugsbetrag akzeptiert.
- Rückgaben werden in der SEPA-Ergebnishistorie gespeichert; es entstehen keine
  zusätzlichen `BankTransaction`-Einträge im allgemeinen Bankimport.
- Browser-, reale Bank- und MySQL-Konkurrenzabnahme stehen aus. Keine produktive
  Migration oder Veröffentlichung durch diesen Arbeitsschritt.

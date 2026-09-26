# Rücklastschriftgebühren weiterbelasten – Entwurfsprozess

## Status

Vorschläge, Rücknahme und Vier-Augen-Freigabe mit gesonderter Rechnung sind als
Backend/API implementiert. Ein Entwurf erzeugt keine Forderung. Erst die
bestätigte Freigabe erstellt genau eine eigene Rechnung. Web- und native
App-Bedienung sind ergänzt. Für Rechnungen mit Zahlungs- oder Bankvorgängen ist
zusätzlich ein Backend-Ablauf für Gutschrift, PDF und Erstattungsnachweis ergänzt;
dessen Web-/App-Bedienung und reale fachliche Abnahmen bleiben offen. Die
Erfassung einer Grundlage ist kein Nachweis ihrer Zulässigkeit.

Die additive Migration `2026_09_23_000007_create_sepa_fee_recharges.php` wurde nicht
produktiv ausgeführt. Vor der Migration liefern die neuen Endpunkte 503; die
Laufübersicht signalisiert `fee_recharge_drafts_available: false`.

## Vorschlagen

`POST /api/v1/clubs/{club}/sepa-batches/{batch}/items/{item}/fee-recharges`

Erforderlich sind Finanzverwaltungsrechte, SEPA-/Zahlungsverfolgungs- und
Rechnungstarifrechte sowie eine exportierte, zurückgegebene Position mit
gebuchter Gebühr. Die ursprüngliche Rechnung muss weiterhin demselben Verein und
einem bestehenden Mitgliedskonto zugeordnet sein.

Pflichtfelder:

- `confirmed`: ausdrückliche Bestätigung des Vorschlags.
- `request_id`: UUID für genau diesen logischen Vorschlag.
- `expected_revision`: zuletzt gelesene Gebührenkorrekturrevision, anfänglich 0.
- `amount_cents`: vorgeschlagene Weiterbelastung; mindestens 1 Cent, höchstens
  der aktuelle tatsächlich gebuchte Gebührenbetrag. Teilbeträge sind möglich.
- `due_date`: vorgeschlagene Fälligkeit, bei Neuerstellung frühestens heute.
- `basis`: dokumentierte Grundlage für die spätere Prüfung, bis 2000 Zeichen.
- `reason`: Begründung des konkreten Vorgangs, bis 2000 Zeichen.

Der Server speichert den Gebührenstand, ursprüngliche Rechnung, zugehöriges
Mitglied, Betrag, Fälligkeit und Begründungen als Snapshot. Er errät keinen
abweichenden Beitragszahler. Korrekturen der ursprünglichen Gebühr ändern einen
bestehenden Vorschlag nicht. Eine spätere Freigabe muss deshalb den gesamten
aktuellen Stand erneut prüfen und veraltete Vorschläge ablehnen.

Pro Rückgabe kann nur ein aktiver Vorschlag bestehen. Dieselbe UUID mit
identischen Daten liefert den gespeicherten Vorschlag; abweichende Daten oder ein
zweiter aktiver Vorschlag werden abgewiesen. Auch nach Rücknahme oder Ablauf der
vorgeschlagenen Fälligkeit liefert eine identische Wiederholung den vorhandenen
Stand, statt einen neuen Vorschlag anzulegen. Es erfolgt kein Versand.

## Zurücknehmen

`POST /api/v1/clubs/{club}/sepa-batches/{batch}/items/{item}/fee-recharges/{proposal}/cancel`

Erfordert Finanzverwaltungsrechte, `confirmed` und eine nicht leere Begründung
`reason` mit höchstens 2000 Zeichen. Nur ein Entwurf kann zurückgenommen werden.
Der Datensatz bleibt mit Person, Zeitpunkt und Begründung erhalten; die aktive
Reservierung wird freigegeben. Eine identische Wiederholung durch dieselbe Person
ist wirkungslos. Abweichende Wiederholungen und fremde Vereins-/Positionskontexte
werden abgewiesen. Ein neuer Vorschlag braucht eine neue UUID.

Vorschlag und Rücknahme laufen mit der bestehenden Sperrreihenfolge
Verein → Lauf → Position → Ergebnis. Speicherung, Reservierung und Audit sind
atomar. Die Tests mit SQLite beweisen kein reales MySQL-Konkurrenzverhalten.

## Lesen und Weiterentwicklung

Die berechtigte Laufübersicht liefert `fee_recharges` am jeweiligen Ergebnis.
Diese Liste enthält Entwürfe und zurückgenommene Vorschläge; Betrag und Status der
ursprünglichen Beitragsrechnung bleiben unverändert. Mitglieder erhalten daraus
keine neue Portalrechnung.

Noch umzusetzen sind reale Browser-, Geräte-, MySQL-, Buchhaltungs- und fachliche
Abnahmen. Die nachfolgend beschriebenen Backend-Abläufe und Oberflächen sind
technisch umgesetzt und automatisiert geprüft.

## Freigabe und Rechnung

Die additive Migration `2026_09_23_000008_approve_sepa_fee_recharges.php` ergänzt
Rechnungsverknüpfung, Freigabeperson/-zeitpunkt, Kontierung und Prüfhinweis. Sie
wurde nicht produktiv ausgeführt. `fee_recharge_approvals_available` signalisiert
getrennt von Entwürfen die Verfügbarkeit dieses Schritts.

`POST /api/v1/clubs/{club}/sepa-batches/{batch}/items/{item}/fee-recharges/{proposal}/approve`

Erfordert Finanzverwaltungsrechte und die Tarifrechte für SEPA, Zahlungsverfolgung
und Rechnungen. Pflichtfelder: `confirmed`, `basis_confirmed` (je ausdrücklich
bestätigt) und `revenue_account` (eigenes fachlich festgelegtes Konto aus 1–20
Ziffern). Es gibt keinen voreingestellten Kontowert. Das Konto darf nicht dem
Bankkonto entsprechen, auch nicht mit zusätzlichen führenden Nullen.

Die freigebende Person muss vom gespeicherten Vorschlagsersteller verschieden
sein. Bei erstmaliger Freigabe müssen Vorschlag/Reservierung, Gebührenrevision,
aktueller Gebührenbetrag, ursprüngliche Rechnung, Vereins-/Mitgliedszuordnung und
Fälligkeit weiterhin passen. Eine veraltete Grundlage wird nicht stillschweigend
übernommen. Die nächste Bearbeitung besteht dann aus Rücknahme und neuem Vorschlag.

Freigabe, neue Rechnung und Audit laufen in einer Transaktion. Die Rechnung
verwendet den separaten Nummernkreis `AIR-FEE-{Verein-ID}-{Vorschlag-ID}`, Quelle
`sepa_fee_recharge`, den vorgeschlagenen Betrag und Empfänger. Der ursprüngliche
Mitgliedsbeitrag bleibt unverändert. Dieselbe Freigabeperson mit unverändertem
Konto kann eine unklare Anfrage wiederholen; sie erhält dieselbe Rechnungsverknüpfung.
Andere Wiederholungsdaten werden abgewiesen. Die aktive Reservierung bleibt nach
Freigabe bestehen und verhindert eine zweite Weiterbelastung derselben Rückgabe.
Es werden weder Zahlung noch Benachrichtigung oder Bankdatei erzeugt.

Die vorhandene Zahlungslogik verarbeitet Teil-/Restzahlungen. Allgemeine
Modellbearbeitung darf Rechnungsbetrag, Empfänger, Nummer, Quelle, Inhalt oder
Fälligkeit nicht überschreiben und die Rechnung weder löschen noch stornieren.
Eine direkte Bezahltmarkierung ohne deckende Zahlungseingänge, ein unpassender
Zwischenstatus und Wiederöffnung trotz vollständiger Zahlung sind ebenfalls
gesperrt. Der gesonderte Vier-Augen-Storno ohne Zahlungs-/Bankvorgänge und der
Gutschriftablauf mit solchen Vorgängen sind unten beschrieben. Ein freigegebener
Vorschlag kann nicht über den Entwurfs-Rücknahmeendpunkt aufgehoben werden.

Der bestehende CSV-Zahlungsexport verwendet für Zahlungseingänge dieser Rechnung
das bei Freigabe festgehaltene Konto und kennzeichnet sie als Gebührenweiterbelastung.
Fehlende/ungültige Kontierung oder nachträgliche Gleichheit zum Bankkonto verhindert
den Export. Es handelt sich weiterhin um den vorhandenen CSV-Zahlungsexport,
nicht um eine abgenommene Übernahme in eine konkrete Buchhaltungssoftware.

Eine spätere Gebührenkorrektur setzt für freigegebene Weiterbelastungen
`review_required: true`. Sie ändert keine ausgestellte Rechnung und erzeugt nicht
automatisch eine Gutschrift oder Erstattung. Die Finanzverwaltung kann danach den
kontrollierten Gutschriftablauf ausdrücklich beantragen. Oberflächen müssen den
Prüfhinweis vor produktiver Einführung sichtbar machen.

Nach produktiver Nutzung benötigen Rückbau oder Datenreparatur einen eigenen
Sicherungs-/Abgleichplan. Die Migrationstests prüfen den Rückbau vor Freigaben.

## Vier-Augen-Storno ohne Zahlungs-/Bankvorgänge

Die additive Migration `2026_09_23_000009_create_sepa_fee_recharge_voids.php`
protokolliert jeden Stornoantrag getrennt. Sie wurde nicht produktiv ausgeführt.
Die Laufübersicht signalisiert `fee_recharge_voids_available` und liefert die
`void_requests` je Weiterbelastung einschließlich zurückgenommener Anträge.

Endpunkte (jeweils POST unter dem bisherigen Positionspfad):

- `/fee-recharges/{proposal}/void-requests`: `confirmed`, `request_id` (UUID),
  `reason` (nicht leer, höchstens 2000 Zeichen).
- `/fee-recharges/{proposal}/void-requests/{voidRequest}/approve`: `confirmed`;
  Freigabe nur durch eine andere berechtigte Person als den Antragsteller.
- `/fee-recharges/{proposal}/void-requests/{voidRequest}/withdraw`: `confirmed`
  und `reason`; Rücknahme des Antrags ohne Änderung der Rechnung.

Alle Endpunkte prüfen Finanzverwaltungsrechte und die Tarifrechte für SEPA,
Zahlungsverfolgung und Rechnungen. Vor verfügbarer Migration liefern sie 503.

Ein Stornoantrag setzt eine freigegebene Weiterbelastung mit unveränderter,
offener/überfälliger Rechnung voraus. Rechnung/Verein/Mitglied/Betrag/Quelle müssen
zusammenpassen. Jegliche Zahlungsdatensätze, zugeordnete Bankumsätze, frühere direkte
SEPA-Exportmarkierung oder eine Position in einem nicht stornierten Lastschriftlauf
sperren diesen Ablauf. Das gilt auch für Zahlungsdatensätze ohne aktuell bezahlten
Status; deren Klärung ist ein separater Vorgang. Bereits stornierte Laufentwürfe
ohne Export verhindern diesen Storno nicht.

Pro Weiterbelastung ist höchstens ein Stornoantrag aktiv. Antragstellung allein
ändert weder Forderung noch Zahlungsannahme. Bei Freigabe werden sämtliche
Voraussetzungen unter Rechnungssperre erneut geprüft. Ein zwischenzeitlicher
Zahlungseingang verhindert die Freigabe; der Antrag kann dann zurückgenommen
werden, um den Erstattungsweg zu prüfen.

Freigabe setzt die Rechnung kontrolliert auf `cancelled`, erhält Betrag, Nummer,
Inhalt und Rechnungsverknüpfung und setzt die Weiterbelastung auf `voided`. Die
aktive Weiterbelastungsreservierung wird frei; ein neuer, separat zu prüfender
Vorschlag ist möglich. Der bestehende Prüfhinweis ist damit aufgelöst. Es entsteht
keine Auszahlung, Einnahme, Ausgabe, Bankdatei oder Benachrichtigung. Ein Betrag
kann anschließend nicht über eine allgemeine Statusänderung wieder geöffnet oder
als neue Zahlung auf die stornierte Rechnung gebucht werden.

Antrag, Freigabe/Rücknahme und Audit sind transaktional. Ein Fehler beim Audit
rollt auch den Rechnungsstatus zurück. Identische Wiederholungen behalten denselben
Datensatz; abweichende Wiederholungen werden abgewiesen. Rücknahme erhält
Antragsteller, Begründung, prüfende Person, Zeitpunkt und Rücknahmebegründung. Eine
neue Anfrage nach Rücknahme benötigt eine neue UUID.

Noch offen: reale MySQL-Konkurrenztests sowie die fachliche, Browser- und reale
Geräteabnahme.
Dieser interne Storno erzeugt noch kein gesondertes PDF-Stornodokument.

## Gutschrift und Erstattung bei Zahlungs-/Bankvorgängen

Die additive Migration `2026_09_24_000010_create_sepa_fee_recharge_credits.php`
legt Gutschriftanträge, Freigabe, Nummer, Erstattungsbedarf und Banknachweis
getrennt ab. Sie wurde nicht produktiv ausgeführt. Die Laufübersicht signalisiert
`fee_recharge_credits_available` und liefert `credit_requests` in der Historie.

Endpunkte unter dem bisherigen Positions- und Weiterbelastungspfad:

- `POST /credit-requests`: Antrag mit `confirmed`, UUID `request_id` und
  Begründung. Er ist nur für eine freigegebene Gebührenrechnung möglich, bei der
  mindestens ein Zahlungsdatensatz, Bankumsatz, direkter SEPA-Export oder aktiver
  Lastschriftlauf existiert. Ohne solche Vorgänge ist der einfachere Storno zu
  verwenden.
- `POST /credit-requests/{credit}/approve`: Vier-Augen-Freigabe durch eine andere
  berechtigte Person. Sie prüft Rechnung, Empfänger, Betrag und Vorgänge erneut,
  erhält sämtliche Belege, setzt die Forderung kontrolliert auf storniert und
  vergibt `AIR-GS-FEE-{Verein}-{Antrag}`. Aus bezahlten Zahlungsdatensätzen wird
  der Erstattungsbedarf eingefroren. Ohne Zahlung ist der Vorgang abgeschlossen;
  mit Zahlung bleibt die Erstattung ausdrücklich ausstehend.
- `POST /credit-requests/{credit}/withdraw`: begründete Rücknahme eines noch nicht
  freigegebenen Antrags; die Historie bleibt erhalten.
- `GET /credit-requests/{credit}/document`: separates PDF mit Verein, Empfänger,
  Ursprungsrechnung, Gutschriftbetrag, Begründung und Erstattungsstatus. Das PDF
  braucht vor produktiver Nutzung eine rechtlich-fachliche Prüfung.
- `GET /credit-requests/{credit}/document-link`: gibt nach derselben
  Finanzberechtigungsprüfung einen fünf Minuten gültigen signierten HTTPS-Link
  für den externen App-PDF-Viewer zurück. Der Link enthält keine API-Zugangsdaten;
  eine veränderte URL verliert ihre Signaturgültigkeit.
- `POST /credit-requests/{credit}/refund`: dokumentiert erst nach der tatsächlichen
  Erstattung Buchungsdatum, eindeutige Bankreferenz und den unveränderlichen
  Erstattungsbetrag. Eine exakt passende vorhandene Bankausgabe kann verknüpft
  werden; andernfalls wird eine eigene Ausgabe erzeugt. Der Endpunkt löst keine
  Überweisung aus und darf daher nicht als Zahlungsdienst verstanden werden.

UUIDs und Wiederholungen sind zustandsgebunden. Eine identische Wiederholung
liefert denselben Antrag, dieselbe Gutschrift oder dieselbe Erstattungsbuchung;
abweichende Daten werden abgewiesen. Gutschriftfreigabe und Audit sowie
Erstattungsbuchung und Audit laufen jeweils transaktional. Nach der Gutschrift
sind die zugehörigen Zahlungsevidenzen und die Erstattungs-Bankausgabe gegen
allgemeine Bearbeitung geschützt. Ursprünglicher Mitgliedsbeitrag und dessen
Rücklastschrift bleiben unverändert.

Die fokussierte Zahlungs-/SEPA-Regression umfasst 102 bestandene Tests mit 1324
Assertions. Der finale Oberflächenstand besteht zusätzlich die gezielte
Web-/Backend-/Lokalisierungsregression mit 116 Tests und 2992 Assertions. Reale
MySQL-Konkurrenz, tatsächlicher Buchhaltungsimport sowie Browser-, Geräte- und
fachliche Abnahme bleiben separate Schritte.

Die vollständige Backend-Regression gegen den finalen isolierten Web-Build
umfasst 1210 bestandene und vier übersprungene Tests mit 29921 Assertions.

## Web-Oberfläche

Die Web-Laufübersicht zeigt Entwürfe und freigegebene beziehungsweise stornierte
Vorgänge einschließlich Empfänger, ursprünglicher Rechnungsnummer, Gebührenstand,
Grundlage, Begründung, Rechnung, Kontierung, Stornohistorie und Prüfhinweis. Eine
lesende Finanzrolle erhält keine Aktionsfelder.

Vorschlag, Rücknahme, Freigabe und Stornoaktionen sind an die jeweiligen
Serverfreigaben gebunden. Eine Person sieht keine Freigabe des eigenen Vorschlags
und keine Freigabe des eigenen Stornoantrags. Das Backend bleibt für alle Rechte-
und Vier-Augen-Prüfungen maßgeblich. Das Konto der Weiterbelastung hat keinen
Standardwert und die Grundlage muss vor der Rechnungsfreigabe getrennt bestätigt
werden.

Vorschläge und Stornoanträge verwenden im Browser eine UUID. Nach einem unklaren
Transportfehler werden Pfad und Inhalt eingefroren. Eine weitere Übermittlung
erfordert eine neue ausdrückliche Bestätigung und sendet dieselben Daten. Es gibt
keinen automatischen Retry. Alternativ lädt die Oberfläche den aktuellen
Serverstand; erst dieser neue Stand gibt das Formular wieder frei.

Die Oberfläche zeigt außerdem Gutschriftanträge und deren Status, Nummer, PDF,
offenen Erstattungsbetrag und dokumentierten Banknachweis. Finanzverwaltung kann
einen Antrag stellen, einen fremden Antrag freigeben, einen Antrag zurücknehmen
und nach tatsächlicher Auszahlung die Erstattung mit Datum, Referenz und optional
einer bereits vorhandenen Ausgabe-ID dokumentieren. Der Hinweis stellt klar, dass
die Aktion selbst keine Überweisung ausführt.

Zustands- und serverseitige Komponentenrenderprüfungen sowie ein isolierter
Web-Build sind bestanden. Sie ersetzen keine echte Browser-, Tastatur-, RTL- oder
fachliche Abnahme.

## Native App-Oberfläche

Die native SEPA-Laufansicht übernimmt die getrennten Serverfreigaben für Entwurf,
Freigabe und Storno. Sie zeigt Vorschläge und Forderungsstatus lesend an und öffnet
für die Finanzverwaltung einen eigenen Arbeitsbildschirm. Dort stehen Vorschlag,
begründete Rücknahme, Vier-Augen-Freigabe mit gesonderter Grundlagenbestätigung
und Konto sowie Antrag, fremde Freigabe und Rücknahme eines Stornos zur Verfügung.
Eigene Vorschläge und eigene Stornoanträge bieten keine Freigabeaktion an. Eine
fehlende Benutzer-ID gilt nicht als Nachweis einer zweiten Person.

Vorschläge und Stornoanträge erhalten eine UUID. Scheitert die Übertragung mit
unklarem Ausgang, friert die App Aktion, Vorschlags-/Storno-ID und Payload ein.
Eine Wiederholung verlangt eine neue ausdrückliche Bestätigung und sendet exakt
diese Daten. Alternativ schließt „Aktuellen Stand laden“ den Dialog und lädt die
Laufübersicht erneut. Automatische Wiederholungen und die Offline-Warteschlange
werden für diese Schreibaktionen nicht verwendet.

Gutschriftantrag, Fremdfreigabe, Rücknahme und Erstattungsnachweis folgen demselben
Bestätigungs- und Wiederholungsmodell. Antragshistorie, Gutschriftnummer, offener
Erstattungsbetrag und Banknachweis bleiben lesbar. Für den PDF-Abruf fordert die
App einen kurzlebigen signierten HTTPS-Link an, prüft ihn vor dem Öffnen und gibt
keine API-Anmeldedaten an den externen Viewer weiter.

26 fokussierte Widget-/Integrationstests und die vollständige Flutter-Suite mit
387 Tests sind bestanden; die statische Flutter-Analyse ist ohne Befund. Diese
Prüfungen ersetzen keine reale Android-/iOS-, RTL-, Bedienungs- oder fachliche
Abnahme.

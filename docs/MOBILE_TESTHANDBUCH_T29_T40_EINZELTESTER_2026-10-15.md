# Mobiles Testhandbuch T29-T40 fuer einen Einzeltester

Stand: 15.10.2026. Ziel: reproduzierbare manuelle Abnahme der Android-App mit mehreren Geraeten und Testkonten.

## 1. Regeln vor dem Start

Teste niemals Loeschungen, Zahlungen, Erstattungen, Auszahlungen, Webhooks oder Massenbenachrichtigungen mit echten Produktivdaten. Nutze Testvereine, Testprodukte und den Zahlungsanbieter im Testmodus.

Ergebnis je Fall:

- `BESTANDEN`: Alle Schritte und Gegenproben stimmen.
- `FEHLER`: Verhalten weicht ab. Screenshot, Uhrzeit, Konto, Geraet, App-Version und letzte Aktion notieren.
- `BLOCKIERT`: Rolle, Testdaten, Provider oder technischer Zugriff fehlen. Nicht als bestanden werten.
- `NICHT ANGEBOTEN`: Funktion existiert sichtbar nicht. Als Funktionsluecke dokumentieren, keinen erfundenen Ersatz akzeptieren.

Vor jedem Fall notieren: Fall-ID, Datum/Uhrzeit, App-Version, Android-Version, Konto, Rolle, Verein, Netzwerk und Ausgangsdaten. Nach jeder Mutation App aktualisieren; bei Paritaetsfaellen danach Web pruefen.

## 2. Konten- und Geraetematrix

- `G1 / Konto A`: Vereinsbesitzer von Verein A; zusaetzlich Sponsor A und Anbieter, soweit freigegeben.
- `G2 / Konto B`: aktives internes Mitglied, Sportler und Kaeufer in Verein A.
- `G3 / Konto C`: zweites aktives Mitglied in Verein A; keine Verwaltungsrechte.
- `G4 / Konto D`: fremde Person oder Sponsor B; keine Mitgliedschaft in Verein A.
- `Konto E`: Super-Admin. Nur fuer T29-Pruefung und T33 verwenden.
- `Konto F`: eingeschraenkter Fachadmin, Support oder Elternkonto je Test.
- Zwei Kinderkonten aus zwei getrennten Familien fuer T32.

Auf allen Geraeten dieselbe App-Version installieren. Unter `Profil -> Einstellungen` Sprache und Theme kontrollieren. Push-Berechtigung erlauben. Fuer negative Tests Berechtigungen spaeter in Android unter `Einstellungen -> Apps -> Airmius -> Berechtigungen` entziehen.

## 3. Wiederkehrende Navigation

1. App starten und anmelden.
2. Oben rechts `Menue` (drei Linien) antippen.
3. Gewuenschtes Modul unter Schnellzugriff oder `Alle Module` antippen.
4. Fehlt ein Modul, zuerst Rolle und Tarif pruefen. Nicht automatisch als UI-Fehler melden.
5. Fuer einen Kontowechsel vollstaendig abmelden; keine zwei Identitaeten in derselben Sitzung mischen.

## T29 Sponsorprofil, Partnerschaften und Kampagnen

Einstieg als Sponsor: `Menue -> Sponsoren`. Erwartete Ueberschrift: `Sponsor-Cockpit`. Als normaler Nutzer oeffnet `Sponsoren` nur die oeffentliche Sponsoransicht.

### T29-01 Markenprofil [App + Web]

1. G1 als Sponsor A: `Menue -> Sponsoren -> Profil anlegen` oder `Profil bearbeiten`.
2. Markenname, rechtlicher Name, Land, Register-/USt.-Angaben, Kontakt, E-Mail und Website ausfuellen.
3. Beide Zustimmungen aktivieren und `Profil speichern` antippen.
4. App komplett schliessen, neu starten und Sponsor-Cockpit erneut oeffnen.
5. Im Web mit demselben Konto `/sponsor-cockpit` oeffnen und Werte vergleichen.

Erwartung: Werte bleiben identisch gespeichert; Status wird durch Bearbeitung nicht eigenmaechtig `verifiziert`; leere Pflichtfelder und ungueltige E-Mail/URL zeigen Feldfehler.

### T29-02 Logos [App + Web]

1. `Profil bearbeiten` oeffnen und getrennte helle/dunkle Logo-URLs eintragen.
2. Speichern; Sponsorprofil und eine sichtbare Kampagne in hellem und dunklem Theme oeffnen.
3. Eine ungueltige URL, Nicht-Bild-URL und unerreichbare URL testen.

Erwartung: Passendes Logo ohne Verzerrung; kein Absturz; verstaendliche Validierung oder sauberer Bildplatzhalter.

### T29-03 Pruefung [App + Admin]

1. Sponsor A aktualisiert das Profil; Status muss `In Pruefung` zeigen.
2. G1 darf keine Selbstverifikation angeboten bekommen.
3. Konto E: `Menue -> Admin -> Sponsoren`, Sponsor A oeffnen, mit Notiz ablehnen.
4. Sponsor A aktualisiert und liest Status/Notiz; danach Admin genehmigt.

Erwartung: Nur Admin entscheidet; Status und Notiz erscheinen beim richtigen Sponsor; Sponsor B sieht nichts davon.

### T29-04 Partnerschaften [zwei Konten]

1. Sponsor A: `Sponsor-Cockpit -> Partnerschaften`, Eintrag aufklappen.
2. Verein, Laufzeit, Paket, Rechte, Vereinbarung und Verlängerungsfrist notieren.
3. Sponsor B auf G4 anmelden und denselben Datensatz suchen bzw. alten Direktlink/Push verwenden.

Erwartung: Sponsor A sieht nur zulaessige Partnerschaften; Sponsor B sieht weder Vertrag, Budget noch Leistung.

### T29-05 Anfrage/Kontakt [App]

1. `Menue -> Sponsoren -> Oeffentlich ansehen`, Partnerprofil oeffnen.
2. Nur den tatsaechlich sichtbaren Kontaktweg verwenden und eine eindeutig benannte Testanfrage senden.
3. Empfaengerkonto pruefen und doppelte Zustellung ausschliessen.

Erwartung: Genau eine nachvollziehbare Anfrage. Fehlt ein spezieller Anfrageweg, `NICHT ANGEBOTEN` protokollieren.

### T29-06 Leistungen [App + Rechte]

1. Sponsor-Cockpit -> Partnerschaft aufklappen -> Plus neben `Vereinbarte Leistungen`.
2. Bezeichnung, Ort, Fälligkeit und Status `Geplant` speichern.
3. Drei-Punkte-Menue der Leistung -> `Bearbeiten`; Status `Erfuellt` ohne Nachweis speichern versuchen.
4. Nachweis ergaenzen, speichern, App neu starten.
5. Mit unberechtigtem Konto dieselbe Partnerschaft oeffnen; Bearbeiten/Loeschen duerfen nicht erscheinen.
6. Berechtigt `Loeschen` antippen, Dialog einmal abbrechen und danach bestaetigen.

Erwartung: Nachweis ist bei `Erfuellt` Pflicht; Aenderung bleibt gespeichert; Rechte und Loeschbestaetigung greifen.

### T29-07 Kampagne erstellen [App]

1. Sponsor-Cockpit -> `Kampagne erstellen`.
2. Name/Motiv, Ziel-URL, Budget, Start und Ende eingeben.
3. Als Entwurf speichern und zur Kampagnenliste zurueckkehren.

Erwartung: Entwurf gehoert Sponsor A, wird nicht ausgespielt und bleibt nach Neustart erhalten.

### T29-08 Kampagnenfreigabe [App + Admin]

1. Entwurf zur Pruefung senden.
2. Admin genehmigt; Sponsor aktiviert, pausiert und beendet nacheinander.
3. Nach jedem Schritt oeffentliche Ausspielung auf G2 pruefen.

Erwartung: Nur gueltige Statuswechsel; pausierte, abgelehnte, zukuenftige und beendete Kampagnen werden nicht aktiv ausgespielt.

### T29-09 Kennzahlen [mehrere Geraete]

1. Ausgangswerte fuer Impressionen, Klicks, Budget und CTR fotografieren.
2. Kampagne auf G2 kontrolliert einmal anzeigen und einmal anklicken.
3. Sponsor-Cockpit aktualisieren; CTR rechnerisch pruefen: Klicks geteilt durch Impressionen mal 100.
4. Sponsor B pruefen.

Erwartung: Plausible, nicht negative Zaehler; keine fremden Kennzahlen.

### T29-10 Conversion/Einwilligung [App + technischer Test]

1. G2: `Profil -> Einstellungen -> Datenschutz einstellen`; Messung erlauben.
2. Unterstuetztes Testereignis einmal ausloesen und Kennzahl pruefen.
3. Dasselbe Ereignis mit identischer Referenz erneut senden lassen.
4. Messzustimmung widerrufen und Ereignis wiederholen.

Erwartung: Wiederholung wird dedupliziert; ohne Einwilligung keine unzulaessige personenbezogene Messung. Ohne Testevent-Zugang `BLOCKIERT`.

### T29-11 Agenturbriefing/Assets [App]

1. Sponsor-Cockpit -> `Kampagnenmaterial & Auftraege`.
2. Vorhandenes Asset und Agenturbriefing oeffnen; Status und Dateizugriff pruefen.
3. Mit Sponsor B denselben Zugriff versuchen.

Erwartung: Nur eigene Dateien/Auftraege; fehlende Bearbeitungsaktion als Luecke dokumentieren.

### T29-12 Budget/Ziel-URL [App]

1. Kampagne mit `javascript:`, leerem Host oder fehlerhafter URL speichern versuchen.
2. Kleines Testbudget setzen, kontrolliert ausschoepfen, erneut ausspielen/klicken.
3. Kampagnenstatus aktualisieren.

Erwartung: Nur HTTP/HTTPS; keine unbemerkte Budgetueberschreitung; Kampagne stoppt oder zeigt klaren Grenzstatus.

## T30 Marketplace: Kaeufer und Anbieter

Einstieg: `Menue -> Marketplace`. Fuer Anbieterbereiche je nach Rolle `Marketplace -> Anbieter/Verkaufen` oder `Menue -> Commerce`.

### T30-01 bis T30-04 Katalog, Merkliste, Warenkorb und Preis

- `T30-01`: Suche/Filter oeffnen, Produkt und Anbieter antippen, Preis/Bestand/Varianten vergleichen. Erwartung: nur freigegebene Produkte, keine internen Anbieter-/Auszahlungsdaten.
- `T30-02`: Herz/Merken zweimal testen; gekauften Artikel bewerten, als Nichtkaeufer Gegenprobe. Erwartung: persoenliche Merkliste; unberechtigte Bewertung blockiert.
- `T30-03`: Zwei Artikel in den Warenkorb, Menge aendern, einen entfernen, App neu starten. Erwartung: Positionen, Mengen und Summe bleiben korrekt.
- `T30-04`: Warenkorb -> Checkout; Gutschein, Versandland/-art und Steuerangaben variieren. Erwartung: jede Summe serverseitig neu berechnet; ungueltiger Gutschein ohne Rabatt.

### T30-05 bis T30-10 Bestellung

- `T30-05`: Liefer-/Rechnungsadresse ausfuellen, Bedingungen aktivieren, Testzahlung ausfuehren. Erwartung: genau eine Bestellung, richtiger Status und Dokumente.
- `T30-06`: Beim Testprovider abbrechen, zur App zurueck, Bestellung pruefen, erneut zahlen. Erwartung: nicht faelschlich bezahlt und keine Doppelbestellung.
- `T30-07`: Ueberweisung waehlen; Empfaenger, Referenz und Betrag fotografieren. Vor Adminbestaetigung `offen`, danach genau einmal `bezahlt`.
- `T30-08`: Abmelden -> `Oeffentlicher Bereich -> Marketplace`; Gastcheckout nur testen, wenn sichtbar. Statuslink mit veraendertem Token oeffnen. Erwartung: nur gueltiger Token zeigt Bestellung.
- `T30-09`: Angemeldet `Marketplace -> Bestellungen`; eigene Bestellung und Rechnung/Gutschrift oeffnen. Fremde Bestell-ID nur kontrolliert ueber Testlink pruefen. Erwartung: kein Fremdzugriff.
- `T30-10`: Bestellung -> `Problem melden`, `Retoure` oder `Stornieren`; Grund eingeben und Status verfolgen. Erwartung: Fristen/Status klar, keine doppelte Erstattung.

### T30-11 bis T30-17 Anbieter

- `T30-11`: `Marketplace -> Anbieter werden`; Antrag absenden. Vor Freigabe kein Verkäuferbereich, danach sichtbar.
- `T30-12`: Anbieterprofil/Standort anlegen, bearbeiten, oeffentliche Ansicht pruefen. Keine Bank-/Auszahlungsdaten oeffentlich.
- `T30-13`: Produkt als Entwurf anlegen, einreichen, Ablehnung/Grund und Freigabe testen. Nur freigegebene Produkte im Katalog.
- `T30-14`: Bestand 1; G2 und G3 gleichzeitig kaufen. Hoechstens eine erfolgreiche Bestellung, Bestand nie negativ.
- `T30-15`: Auszahlungsdaten und Testguthaben; Auszahlung anfordern, Admin freigeben und erneut versuchen. Keine Doppelauszahlung.
- `T30-16`: Als Teamverantwortlicher `Teamsammelbestellung`; Mitglieder, Groessen, Mengen erfassen und Lieferantenexport pruefen. Nur berechtigte Person darf Export sehen.
- `T30-17`: Website-Service oeffnen, Anfrage einmal absenden, Bestaetigung/Status pruefen. Keine automatische kostenpflichtige Aktivierung.

## T31 Abos, Tarife und Outfit

Einstieg: `Menue -> Abos & Rechnungen`; Outfit: `Menue -> Outfit-Abos`.

- `T31-01`: Angebote oeffnen, Person/Verein, Laufzeit, Preis und Limits vergleichen; passenden Plan waehlen. Zuordnung muss vor Zahlung eindeutig sein.
- `T31-02`: Testcheckout abschliessen, Bestaetigung und freigeschaltete Funktion oeffnen. Freigabe erst beim korrekten Zahlungsstatus.
- `T31-03`: Kontingent bis Grenze nutzen und ein weiteres Objekt anlegen. Erwartung: Server blockiert/Upgrade; bestehende Daten bleiben.
- `T31-04`: Aktives Abo -> `Kuendigen` oder `Verlaengern`; Dialog pruefen und Wirksamkeitsdatum kontrollieren. Zugang bis Endtermin korrekt.
- `T31-05`: Fehlzahlung im Testprovider simulieren; Hinweis oeffnen, Zahlungsmethode korrigieren. Keine Doppelabbuchung.
- `T31-06`: `Abos & Rechnungen -> Rechnungen`; Plattform-Abo-Rechnung und Vereinsbeitragsrechnung vergleichen. Beide klar getrennt.
- `T31-07`: `Outfit-Abos`; Plan, Groesse, Adresse und Bedingungen waehlen, Testabschluss. Variante und Empfaenger korrekt.
- `T31-08`: Outfit-Lieferung oeffnen, Versand/Zahlung pruefen, angebotene Aenderung/Pause/Kuendigung ausfuehren. Lieferung und Abo behalten getrennte Status.

## T32 Eltern und Jugendschutz

Einstieg angemeldet: `Menue -> Eltern & Jugendschutz`; ohne Konto ueber den Elternzugangslink bzw. Eltern-Login.

- `T32-01`: E-Mail-Code anfordern; gueltigen, falschen und abgelaufenen Code testen. Zugang nur mit gueltigem Code.
- `T32-02`: Offene Kinderanfrage antippen; einmal ablehnen, neu anlegen, dann zustimmen. Kinderkonto zeigt klaren Warte-/Freigabestatus.
- `T32-03`: Freigegebenes Kind -> `Zustimmung widerrufen`; altes Geraet und alte Links pruefen. Schutzwirkung sofort bzw. regelkonform.
- `T32-04`: Zwischen Kind 1 und Kind 2 wechseln; Team, Termine, Einwilligungen vergleichen; fremde Kind-ID Gegenprobe. Keine Datenvermischung.
- `T32-05`: Sorgebeziehung einladen; anderes Elternkonto nimmt an/ab. Primaere Beziehung aendern/widerrufen. E-Mail allein darf Beziehung nicht beweisen.
- `T32-06`: Angebotenen Weg `Eigenes Elternkonto erstellen` verwenden; danach Kinderliste pruefen. Keine doppelten Kinderprofile.
- `T32-07`: Konto D als fremder Erwachsener oeffnet Minderjaehrigenprofil und versucht Chat/Einladung. Nur freigegebener Kontaktweg funktioniert.
- `T32-08`: Nur mit steuerbarer Testzeit/Geburtsdatum: Altersgrenze erreichen, neu anmelden und Rechte pruefen. Sonst `BLOCKIERT`.

## T33 Plattformadministration

Einstieg Konto E: `Menue -> Admin`. Mit normalem Konto muss `Admin` fehlen oder Zugriff verweigert werden.

- `T33-01`: Jeden sichtbaren Adminbereich einmal oeffnen; mit Konto B Gegenprobe. Keine normale Person darf hinein.
- `T33-02`: `Admin -> Vereine`; Verein suchen -> Status -> Zielstatus; Dialog erst abbrechen, dann bestaetigen. Alter/neuer Status und Vereinsname klar.
- `T33-03`: Vereinspruefung oeffnen; Notiz + Ablehnung/Freigabe; Besitzeransicht auf G1 pruefen.
- `T33-04`: Nur Testverein: administratives Loeschen oeffnen, exakte Phrase pruefen, abbrechen. Endgueltig nur in isolierter Umgebung testen.
- `T33-05`: `Admin -> Nutzer`; suchen/anlegen, Stammdaten/Rolle bearbeiten, sperren/entsperren; Anmeldung des Kontos pruefen.
- `T33-06`: Inaktivitaetswarnung senden; Testkonto loeschen; Selbstloeschung des Admins versuchen. Richtiger Empfaenger, Selbstloeschung blockiert.
- `T33-07`: `Rollen`; Fachrolle erstellen, minimale Rechte zuweisen, Konto F testen. Keine Rechte ausserhalb der Auswahl.
- `T33-08`: `Trainerantraege`; filtern, Notiz, genehmigen/ablehnen; Antragsteller bekommt richtigen Hinweis.
- `T33-09`: `Moderation`; Meldung oeffnen, Entscheidung, Einspruch. Zielinhalt und Audit muessen stimmen.
- `T33-10`: `Redaktion/Blog`; Kategorie und Beitrag mit Bild/Uebersetzung, Vorschau, veroeffentlichen, zurueckziehen. Native fehlende Editoraktion als Luecke notieren.
- `T33-11`: `Medien`; globale Bildquelle aendern/hochladen; Web/App pruefen; falschen Dateityp testen.
- `T33-12`: `Sportarten/Badges/Regeln`; Testdatensatz erstellen, bearbeiten, deaktivieren/loeschen; bestehende Referenzen pruefen.
- `T33-13`: `Backoffice`; Abo/Rechnung/Zahlung suchen und zulaessige Aktion ausfuehren. Konto/Verein und Buchung kontrollieren.
- `T33-14`: `Commerce`; Produkt, Bestellung, Gutschein, Versand, Steuer jeweils oeffnen und erlaubte Aktion pruefen; Kaeuferansicht vergleichen.
- `T33-15`: Testerstattung/-auszahlung bestaetigen und Aktion wiederholen. Zweiter Versuch darf keine zweite Buchung erzeugen.
- `T33-16`: `Sponsoren`; Profil/Kampagne pruefen, Status aendern, Sponsor- und oeffentliche Ansicht vergleichen.
- `T33-17`: `Support/Mail`; Ticket antworten, Status setzen, Versand pruefen. Keine fremden Tickets oder falschen Empfaenger.
- `T33-18`: `Vertraege/Provider`; Testvertrag/Kosten aendern, Konto F gegenpruefen. Keine Geheimnisse sichtbar.
- `T33-19`: `Operations/Analyse`; Zeitraum/Filter setzen, Kennzahlen pruefen. Kleine Gruppen duerfen keine Personenableitung erlauben.
- `T33-20`: `Systemeinstellungen`; harmlose Testeinstellung aendern, neu laden, Wirkung pruefen; Konto F darf sie nicht aendern.
- `T33-21`: Webliste mit mehr als einer Seite vorbereiten; Suche, Filter, Export und Detailaktion in App wiederholen. Jede Abweichung einzeln erfassen.

## T34 Vereins- und Datenschutzgrenzen

Fuer alle Faelle Verein A und B mit unterschiedlichen Konten verwenden. Keine echten Fremd-IDs aus Produktion nutzen.

- `T34-01`: Objekt in B anlegen; Konto A sucht in Listen/Suche und verwendet kontrollierten Test-Direktlink. Lesen/Aendern/Loeschen/Export muss scheitern.
- `T34-02`: In einem A-Testlink Mitglied-/Team-/Datei-/Rechnungs-ID durch B-Test-ID ersetzen. Jede Aktion muss Eltern- und Vereinszuordnung pruefen.
- `T34-03`: Hohen Tarif ohne Fachrecht sowie Fachrecht ohne erforderlichen Tarif getrennt testen. Tarif darf nie Berechtigung ersetzen.
- `T34-04`: Ausgeloggt oeffentliches Vereinsprofil; interne Tabs/Downloads versuchen; danach als Mitglied vergleichen.
- `T34-05`: A oeffnen, zu B und anderem Konto wechseln; Zurueck-Taste, Suche und kurz Offline testen. Keine alten privaten A-Daten.
- `T34-06`: Formular als berechtigte Person oeffnen; auf zweitem Geraet Recht entziehen; erstes Geraet speichert. Server muss ablehnen.
- `T34-07`: Recht entziehen; alten Datei-/Freigabelink und laufende Benachrichtigung erneut nutzen. Kein weiterer Zugriff/Echtzeitinhalt.
- `T34-08`: Erfordert Proxy/API-Werkzeug: versteckte Rollen-/Besitzerfelder mitsenden. Nur technisch testen, in App allein `BLOCKIERT`.
- `T34-09`: CSRF/XSS ist Web-/Techniktest; mobil nur HTML-/Skripttext als normaler Inhalt speichern und gefahrlose Anzeige pruefen.
- `T34-10`: Profile, Fehlermeldungen, Teilen-Links und externe Browserziele ansehen. Keine Passwoerter, Tokens oder internen Geheimnisse.

## T35 Eingaben, Fehler und Bedienbarkeit

Diese 16 Varianten auf mindestens je einem Formular, einer Liste, einem Detail, einem Dialog und Upload pro wichtigem Modul wiederholen.

- `T35-01`: Pflichtfelder leer/Leerzeichen, minimale/maximale/zu lange Werte; danach korrigieren. Einheitliche Feldfehler.
- `T35-02`: Umlaute, Akzente, Emoji und 100+ Zeichen ohne Leerzeichen. Speichern, Liste/Export pruefen; keine Ueberlagerung.
- `T35-03`: `1,50`, `1.50`, Null, negativ, Extremwert, ungueltiges Datum und Schaltjahr. Werte/Rundung oder klare Fehler.
- `T35-04`: Kleinstes Geraet und Querformat; Tastatur/Dialog oeffnen. Speichern/Abbrechen erreichbar, Scrollen moeglich.
- `T35-05`: Android -> Anzeige -> Schriftgroesse maximal; Titel, Hilfetext, Buttons in mehreren Modulen pruefen.
- `T35-06`: Mit Hardwaretastatur/Tab navigieren, Dialog schliessen. Sinnvolle Reihenfolge, keine Fokusfalle.
- `T35-07`: TalkBack aktivieren; Icons, Felder, Fehler und Status vorlesen. Jeder Button hat sinnvollen Namen/Zustand.
- `T35-08`: Hell/Dunkel, DE/EN und lange Texte. Kontrast lesbar, keine Rohschluessel; RTL nur bei aktiv unterstuetztem Arabisch.
- `T35-09`: Android-Netz auf langsam drosseln; Speichern mehrfach antippen. Ladezustand, nur eine Aktion.
- `T35-10`: Flugmodus waehrend Laden/Speichern; Meldung, Entwurf und `Erneut versuchen` pruefen. Kein erfundener Erfolg.
- `T35-11`: 401/403/404/409/422/429/5xx benoetigen Testserver/Proxy. App zeigt passende Meldung, keinen Stacktrace/Loop.
- `T35-12`: Formular aendern, System-Zurueck/Schliessen; erneut oeffnen. Kein stilles Speichern; Verlustwarnung/Entwurf eindeutig.
- `T35-13`: Leere, eine und mehrseitige Liste; Suche/Sortierung/Filter, letztes Element. Keine stille Begrenzung.
- `T35-14`: Gleiches Objekt auf G1/G2 bearbeiten und nacheinander speichern. Konflikt muss definiert sein, kein unbemerkter Mischzustand.
- `T35-15`: Kamera/Fotos/Dateien/Standort erlauben, verweigern, spaeter aendern. Kein Absturz, sinnvolle Alternative.
- `T35-16`: Alte unterstuetzte App mit lokalen Daten, Update installieren, Sitzung/Navigation/Daten pruefen. Vorher Backup/Screenshots.

## T36 Hintergrundprozesse und Technik

Diese Phase kann ein Einzeltester nicht vollstaendig durch App-Klicks abnehmen. App-seitig pruefbar sind T36-06, T36-08, T36-09, T36-11 und T36-13 teilweise. T36-01 bis -05, -07, -10, -12, -14 und -15 benoetigen Entwicklerzugang/Testumgebung.

- `T36-01`: Jede registrierte Route technisch einem Fachfall zuordnen; App-Menue allein ist kein Nachweis.
- `T36-02`: Jede Web-/Flutter-Flaeche als Produkt, Demo, intern oder veraltet klassifizieren und echte API-Persistenz nachweisen.
- `T36-03`: Scheduler mit faelligen Daten zweimal ausfuehren; Wirkung genau einmal.
- `T36-04`: Queue/Outbox unterbrechen und fortsetzen; keine verlorene oder doppelte irreversible Wirkung.
- `T36-05`: Test-Webhooks gueltig, doppelt, verspaetet und falsch signiert; nur gueltige Statusuebergaenge.
- `T36-06`: Provider-Timeout simulieren; App-Ablauf wiederholen. Klare Stoerung, konsistente Daten, spaeterer Wiederanlauf.
- `T36-07`: Echtzeitkanal mit berechtigtem/fremdem Konto, danach Recht entziehen. Keine fremden neuen Daten.
- `T36-08`: Abgemeldet `Oeffentlicher Bereich`; Verzeichnisse, Blog, Jobs, Preise, Rechtstexte in Sprachen mobil pruefen.
- `T36-09`: Geteilte Direkt-URL, Datei/Video, Fehlerseite und Redirect mobil pruefen. Kein Loop/falscher Inhalt.
- `T36-10`: Wartungsmodus und Backup-Wiederherstellung nur isoliert technisch testen.
- `T36-11`: Grosse Testlisten laden; Lade-, Such- und Scrollzeit mit Bildschirmaufnahme messen; keine Datenabschneidung.
- `T36-12`: Automatisierte Tests tatsaechlich ausfuehren; Dateiexistenz ist kein Bestehen.
- `T36-13`: Objekt im Web aendern/loeschen; App aktualisieren, erneut aktualisieren, Konto/Verein wechseln. Geloeschtes/Fremdes darf nicht wiederkehren.
- `T36-14`: Externe Vereins-API mit begrenztem/widerrufenem Token technisch testen.
- `T36-15`: Healthcheck, CORS, CSRF, signierte Dateien und Rate-Limit technisch testen. Auf Handy nur sichtbare Folgen pruefen.

## T37 vollstaendige Nutzerreisen

- `T37-01`: G2 neu registrieren/verifizieren -> `Vereine & Teams -> Verein finden -> Antrag` -> G1 nimmt auf -> Team zuweisen -> G2 Team/Training oeffnen. Keine Adminrechte fuer G2.
- `T37-02`: G1 erstellt Vereinsrechnung im Web -> G2 tippt Push -> Rechnung/PDF -> Testzahlung -> beide Ansichten vergleichen. Kein 403, gleicher Restbetrag.
- `T37-03`: G1 erstellt Rechnung in App -> G2 oeffnet Nachricht im Web -> PDF/Zahlung -> App aktualisieren. Identischer Stand.
- `T37-04`: G1 Teams A, dann Verein B; Suche und Details pruefen. Keine Vermischung/Rundlaufnavigation.
- `T37-05`: Aufgabe mit Frist/Anhang im Web -> G2 kommentiert/erledigt App -> G1 prueft Kalender/Status Web. Gemeinsamer Stand.
- `T37-06`: `Menue -> Vereinskalender`; Ansichten/Frist, Detail, Zurueck. Zeitraum und Auswahl bleiben.
- `T37-07`: G2 mit maximaler Schrift -> Vereine verwalten, Rechnungsauswahl, Aufgabenmodal. Alles lesbar/bedienbar.
- `T37-08`: G1 legt Testverein B an; getrennte Mitglieder/Teams/Aufgaben; wechseln/suchen. Keine Datenmischung.
- `T37-09`: G1 importiert externes Mitglied -> Einladung mit G2 annehmen/verknuepfen -> Rechnung/Team/Mitgliedskarte. Eine Identitaet, Historie bleibt.
- `T37-10`: Trainer erstellt/veroeffentlicht Plan -> G2 protokolliert -> Trainer Feedback/Woche. Alles korrekt verbunden.
- `T37-11`: Sponsorprofil/Kampagne -> Adminfreigabe -> kontrollierte Anzeige/Kennzahlen. Sponsor B isoliert.
- `T37-12`: Minderjaehrigenkonto -> Elternzustimmung -> Vereinsaufnahme -> Widerruf. Schutzregeln durchgehend.
- `T37-13`: G1 beantragt Vereinsloeschung -> Vorstandsnachricht -> innerhalb 30 Tagen zuruecknehmen -> Termin pruefen. Kein Datenverlust.
- `T37-14`: Konto E `Admin -> Vereine` Status aendern/bestaetigen -> Web und Konto F vergleichen. Serverseitige Rechte identisch.
- `T37-15`: Testprodukt bestellen/bezahlen -> Problem/Retoure -> Adminerstattung -> Bestellung/Zahlung/Dokumente. Alle Status stimmen.

## T38 Fahrgemeinschaften

Einstieg: `Menue -> Fahrgemeinschaften`.

- `T38-01`: Suche/Filter/Eventbezug setzen, Fahrt antippen. Abfahrt, Ziel, Zeit, Fahrer und freie Plaetze korrekt.
- `T38-02`: Plus/`Fahrt anbieten`; Start, Ziel, Zeitpunkt, Plaetze und optionale Hinweise; negativ/Ende vor Start testen; speichern.
- `T38-03`: G2 Fahrt -> `Platz anfragen`; G1 Fahrer nimmt an, zweites Mal lehnt andere Anfrage ab. Status/Restplaetze korrekt.
- `T38-04`: Einen Platz; G2/G3 nahezu gleichzeitig anfragen, Fahrer beide akzeptieren versuchen. Hoechstens eine Zusage.
- `T38-05`: Fahrer Drei-Punkte -> bearbeiten; G2 aktualisiert; danach absagen/loeschen. Nicht weiter buchbar, Teilnehmer informiert.
- `T38-06`: G2 `Teilnahme zurueckziehen`; Fahrt aktualisieren. Platz steigt genau um eins, Rueckmeldung vorhanden.
- `T38-07`: Angenommener Mitfahrer sieht nur erlaubte Kontakt-/Treffpunktdaten; Konto D nicht. Nach Entzug keine dauerhafte Freigabe.

## T39 Support und Hilfe

Einstieg: `Menue -> Support`.

- `T39-01`: Support mit Sportler, Besitzer, Sponsor und Admin oeffnen. Alle normalen Rollen erreichen Hilfe ohne Adminrecht.
- `T39-02`: Betreff/Beschreibung/Kategorie/Prioritaet, optional Verein; senden. Genau eine Bestaetigung und ein Vorgang.
- `T39-03`: `Meine Tickets`; neues Ticket antippen, Ausgangstext und Status pruefen.
- `T39-04`: Supportkonto antwortet; Nutzer oeffnet Hinweis und Ticket, antwortet. Anhaenge nur als verfuegbar werten, wenn Button existiert.
- `T39-05`: Pflichtfelder leer, Netz beim Senden aus, erneut senden, fremde Ticket-ID Gegenprobe. Keine Doppelung/Fremddaten.
- `T39-06`: Ticket mit Verein A, dann zu B wechseln. Sichtbarkeit und Bearbeitungsrecht bleiben korrekt getrennt.
- `T39-07`: Kategorie `Sicherheit & Schutz`, Testmeldung senden; nur zustaendige Supportrolle darf Inhalt sehen.

## T40 oeffentliche Inhalte

App-Einstieg abgemeldet: Loginseite -> `Oeffentlicher Bereich`. Angemeldet je nach Build: `Menue -> Oeffentlicher Bereich`. Cookie/Indexierung sind Browsertests.

- `T40-01`: `Blog & Medien`; Kategorie, Beitrag, Sprache, Bild, Links. Nur veroeffentlicht und vollstaendig.
- `T40-02`: Vereine, Sponsoren, Sportarten, Staedte und Events oeffnen, suchen/filtern, Details. Keine Verwaltungsdaten.
- `T40-03`: Oeffentliches Vereinsprofil -> sichtbarer Aufnahmeantrag; absenden; gueltigen und veraenderten Statuslink im mobilen Browser. Falscher Token ohne Zugriff.
- `T40-04`: Oeffentlichen Kurs/Gastbuchung nur testen, wenn sichtbar; Angaben, Testzahlung/Status, spaetere Kontozuordnung. Keine Kontoübernahme.
- `T40-05`: `Rechtliches`; Impressum, Datenschutz, AGB, Widerruf, Community, Jugendschutz, Cookies und Melden; DE/EN, schmal/quer. Keine leeren/falschen Seiten.
- `T40-06` [Browser]: Chrome Inkognito -> Website; `Nur notwendige`, neu laden; Schild/Footer -> Auswahl aendern/widerrufen. Anmeldung bleibt moeglich.
- `T40-07`: Oeffentlichen Link per Nachricht an G2 teilen; in App/Browser oeffnen; geschuetzte Aktion -> anmelden. Danach richtiges Ziel.
- `T40-08`: Admin erstellt Entwurf, merkt URL, zieht Inhalt zurueck; ausgeloggt alte URL/Suche, Adminansicht vergleichen. Gast sieht keinen Entwurf.
- `T40-09` [Browser/technisch]: `/robots.txt`, `/sitemap.xml`, `/blog/rss.xml`, `/site.webmanifest`; Sprache, URL, Inhaltstyp; Entwurf/private Gegenprobe. Nicht als App-Menue erwarten.

## 4. Fehlerbericht-Vorlage

```text
Fall-ID:
Ergebnis: FEHLER / BLOCKIERT
App-Version und Build:
Geraet / Android:
Konto und Rolle:
Verein / Team:
Ausgangsdaten:
Exakte Klickfolge:
Erwartet:
Tatsaechlich:
Uhrzeit und Zeitzone:
Netzwerk:
Screenshot / Bildschirmaufnahme:
Nach Neustart reproduzierbar: Ja / Nein
Web zeigt denselben Fehler: Ja / Nein / Nicht relevant
```

## 5. Empfohlene Reihenfolge fuer einen Einzeltester

1. Tag 1: Kontenmatrix und T34-Basisgrenzen.
2. Tag 2: T29 Sponsoren.
3. Tag 3: T30 Marketplace.
4. Tag 4: T31 Abos und Outfit.
5. Tag 5: T32 Elternschutz und T38 Fahrgemeinschaften.
6. Tag 6: T33 Admin mit Testdaten.
7. Tag 7: T35 Bedienbarkeit auf zwei Geraeten.
8. Tag 8: T37 Ende-zu-Ende-Reisen.
9. Tag 9: T39 Support und T40 oeffentliche Inhalte.
10. T36 gemeinsam mit Entwicklung/Serverzugang; technische Faelle nie allein anhand der App abhaken.


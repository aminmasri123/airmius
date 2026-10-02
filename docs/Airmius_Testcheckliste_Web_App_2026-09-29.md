# Airmius: Testcheckliste für Web und Flutter

Stand: 29.09.2026. Grundlage: lokaler Quellstand `7db50738f79b0c8d0204855f3eba8bca9e6e0014` einschließlich der vorhandenen lokalen Änderungen. Diese Datei beschreibt Prüfaufträge, keine bereits bestandenen Tests.

**444 nummerierte Testfälle in 40 Bereichen**, jeweils mit Schritten und erwartetem Ergebnis. Gemeinsame Voraussetzungen stehen direkt vor der jeweiligen Tabelle.

## Umfang und Verwendung

Diese Arbeitsliste verbindet rollenbezogene Nutzungsabläufe mit Fehlerfällen, Berechtigungen, Geräteprüfungen und technischen Gegenprüfungen. Ergänzend enthält [das technische Inventar](Airmius_Testinventar_2026-09-29.md) jede zum Erstellungszeitpunkt registrierte HTTP-Route sowie alle gefundenen Web-Seiten, Flutter-Screen-Dateien, Testdateien, geplanten Befehle und Broadcast-Kanäle. Das Inventar ist eine Auffangliste für Detailfunktionen, die bei einer alleinigen Menüprüfung übersehen würden.

Eine endliche Liste kann nicht jede mögliche Eingabe oder Kombination vorwegnehmen. Vollständigkeit bedeutet hier: alle gefundenen Funktionsflächen erfassen, deren Aktionen prüfen, offene Zuordnungen sichtbar lassen und die beschriebenen Varianten systematisch anwenden. Weder eine vorhandene Route noch eine Testdatei oder ein Screenname beweist eine fertig nutzbare Funktion.

**Jede Tabellenzeile ist ein eigener Testfall.** Der Einstieg und die Voraussetzungen des jeweiligen Abschnitts gelten zusätzlich zu den nummerierten Schritten. Bei mehreren genannten Varianten jede Variante separat protokollieren. Nach einer Änderung aktualisieren oder die Seite erneut öffnen und den gespeicherten Zustand prüfen. Bei gemeinsamen Daten zusätzlich mit einem zweiten berechtigten Konto kontrollieren.

**Plattformen:** Jeden zutreffenden Fall getrennt im Desktop-Web, mobilen Web und in der Flutter-App durchführen. Android und iOS getrennt erfassen, sofern die jeweilige App angeboten wird. Ein fehlender App-Bildschirm ist bei vereinbarter Web/App-Gleichheit eine Funktionslücke und kein bestandener Test. API-Prüfungen ersetzen keine Bedienprüfung.

**Ergebniswerte:** Offen, Bestanden, Fehler, Blockiert, Funktionslücke, Nicht zutreffend. „Nicht zutreffend“ benötigt einen Grund, etwa eine absichtlich ausgeschlossene Rolle. Fehlende Testkonten oder Provider machen einen Fall „Blockiert“. Ein einzelner bestandener Web-Durchlauf schließt den App-Fall nicht ab.

**Testprotokoll je Durchführung:** Fall-ID, Plattform, Build/Commit, Datum, Konto/Rolle, aktiver Verein/Team, Tarif, Voraussetzungen/Testdaten, konkrete Variante, tatsächliches Ergebnis, Ergebniswert, Fehlerreferenz und Beleg. Keine Passwörter oder Zugangstokens im Protokoll speichern.

## Testkonten und Daten

- Zwei unabhängige Vereine A und B; pro Verein zwei Teams und mehrere Mitglieder, darunter Personen mit gleichem Namen.
- Sportler ohne Verein, Mitglied in A, Mitglied in A und B, ehemaliges Mitglied und minderjähriges Mitglied.
- Trainer nur in einem Team, Trainer in mehreren Teams, Trainer ohne Team und Trainer mit entzogenen Rechten.
- Vereinsbesitzer, Vereinsadministrator, Manager, Finanzverantwortlicher, Medienverantwortlicher und normales Vereinsmitglied als getrennte Konten.
- Sponsor A und Sponsor B; Käufer, Anbieter sowie berechtigte Kursautoren als zusätzliche Testrollen.
- Plattformadministrator/Super-Admin und eingeschränkte administrative Rolle; Elternkonten mit einem beziehungsweise mehreren zugeordneten Kindern.
- Leere, kleine und mehrseitige Datenbestände; ähnliche Rechnungen mit unterschiedlichen Mitgliedern, Nummern, Beträgen und Statuswerten.
- Testzahlungen, Test-Mailadressen, Testdateien und kontrollierbare Testzeit. Zeitversatz, Löschungen und Provider-Rückmeldungen ausschließlich an dafür vorgesehenen Testdaten prüfen.

## Lesepfad nach Rolle

| Rolle | Vollständig abzuarbeitende Abschnitte |
| --- | --- |
| Alle angemeldeten Rollen | 01–07, zutreffende gemeinsame Angebote in 13–14 und 30–31 sowie 34–40 |
| Sportler | 08–14 und 38; Teilnahmefunktionen aus 23–27; eigene Rechnungen aus 20; gegebenenfalls 32 |
| Trainer | 08–16, 23–27; Vereinsverwaltung nur mit gesondertem Vereinsrecht |
| Verein | 17–28, 23–27 einschließlich Mitglieder/Finanzen; 29–31 soweit freigeschaltet |
| Sponsor | 29–31 sowie allgemeine Kommunikation, Dateien, Suche und Profil |
| Eltern/Erziehungsberechtigte | 32; zusätzliche Vereinsrechte nicht aus der Elternrolle ableiten |
| Plattformadministration | 33 sowie technische Gegenprüfungen in 34–37 |
| Entwickler/Tester | Zusätzlich jede Zeile im technischen Inventar zuordnen und abnehmen |

## 01. Registrierung, Anmeldung und Sitzung

Voraussetzung: abgemeldetes Testgerät; Zugriff auf Testpostfach. Einstieg: Login/Registrierung der jeweiligen Plattform. Varianten mit bestehendem Konto separat ausführen.

| Offen | ID | Szenario und Schritte | Erwartetes Ergebnis |
| --- | --- | --- | --- |
| [ ] | T01-01 | Registrierung: 1. Registrieren öffnen. 2. Alle Pflichtfelder und erforderlichen Zustimmungen ausfüllen. 3. Absenden und Bestätigung öffnen. | Genau ein Konto entsteht; Einrichtung und Verifikation werden korrekt fortgesetzt. |
| [ ] | T01-02 | Validierung: 1. Pflichtfeld auslassen. 2. Ungültige E-Mail, bestehende E-Mail und unterschiedliche Passwörter einzeln absenden. 3. Eingaben korrigieren. | Verständliche Feldfehler; keine ungewollten Konten; übrige Eingaben bleiben erhalten. |
| [ ] | T01-03 | Minderjährige: 1. Altersgrenzen mit je einem Tag davor/danach testen. 2. Erziehungsberechtigten angeben. 3. Registrierung fortsetzen. | Der zutreffende Zustimmungsprozess erscheint; Geburtsdatum umgeht keine Beschränkungen. |
| [ ] | T01-04 | E-Mail-Verifikation: 1. Unbestätigt anmelden. 2. Bestätigung erneut anfordern. 3. Gültigen, abgelaufenen und erneut verwendeten Link öffnen. | Korrekte Zustände und erneute Anforderung; kein fremdes Konto wird bestätigt. |
| [ ] | T01-05 | Anmelden: 1. Gültige Zugangsdaten eingeben. 2. Anmelden. 3. Rollenstartseite und aktiven Arbeitsbereich prüfen. | Richtige Person und erlaubte Module; kein Konto- oder Vereinswechsel ohne Auswahl. |
| [ ] | T01-06 | Fehlanmeldung: 1. Falsches Passwort und unbekannte Adresse testen. 2. Wiederholt absenden. 3. Nach der angezeigten Wartezeit korrekt anmelden. | Verständlicher Fehler und wirksame Begrenzung; keine vertraulichen Kontodaten. |
| [ ] | T01-07 | Abmelden: 1. Profilmenü öffnen. 2. Abmelden wählen. 3. Zurück-Taste, alten Link und erneuten App-Start prüfen. | Geschützte Inhalte und Sitzungsdaten bleiben nach Logout unzugänglich. |
| [ ] | T01-08 | Passwort zurücksetzen: 1. Passwort vergessen öffnen. 2. Test-Mail anfordern. 3. Link verwenden und anschließend erneut/abgelaufen testen. | Neues Passwort funktioniert; alte/ungültige Rücksetzlinks funktionieren nicht. |
| [ ] | T01-09 | Passwort ändern: 1. Einstellungen → Sicherheit öffnen. 2. Altes und neues Passwort eingeben. 3. Abmelden und neu anmelden. | Änderung dauerhaft; falsches bisheriges Passwort wird abgewiesen. |
| [ ] | T01-10 | Zwei-Faktor-Anmeldung: 1. Sicherheit → 2FA aktivieren und bestätigen. 2. Neu anmelden. 3. Gültigen, falschen, abgelaufenen sowie Wiederherstellungscode testen. | Zweiter Faktor tatsächlich erforderlich; verbrauchte Einmalcodes nicht wiederverwendbar. |
| [ ] | T01-11 | Geräte/Sitzungen: 1. Zwei Geräte anmelden. 2. Eine beziehungsweise andere Sitzungen widerrufen. 3. Auf dem betroffenen Gerät eine geschützte Aktion ausführen. | Widerruf wirksam; verbleibende erlaubte Sitzung bleibt nutzbar. |
| [ ] | T01-12 | Sitzungsablauf: 1. Bearbeitung beginnen. 2. Sitzung kontrolliert ablaufen lassen. 3. Speichern und erneut anmelden. | Klarer Loginbedarf; keine falsche Erfolgsmeldung oder unbemerkte Doppelanlage. |
| [ ] | T01-13 | Gesperrtes/unvollständiges Konto: 1. Entsprechenden Kontostatus vorbereiten. 2. Anmelden. 3. Geschützten Direktlink öffnen. | Sperre beziehungsweise Einrichtungspflicht gilt auch ohne Navigation über das Menü. |
| [ ] | T01-14 | Externer Login: 1. Einen tatsächlich angebotenen Anbieter wählen. 2. Zustimmung erteilen oder abbrechen. 3. Mit bereits bekannter E-Mail wiederholen. | Richtige Kontozuordnung; keine Dublette oder Übernahme ohne bestätigte Identität. |

## 02. Profil, Einstellungen und Datenschutz

Voraussetzung: angemeldetes Konto. Einstieg: Profil → Einstellungen; im Web auch `/settings`. Nur tatsächlich angebotene Einstellungen als bedienbar werten.

| Offen | ID | Szenario und Schritte | Erwartetes Ergebnis |
| --- | --- | --- | --- |
| [ ] | T02-01 | Stammdaten: 1. Profil öffnen. 2. Namen, Beschreibung und Kontaktdaten bearbeiten. 3. Speichern und neu laden. | Werte dauerhaft und korrekt dargestellt; Änderungen an geschützten Feldern kontrolliert. |
| [ ] | T02-02 | Profilbild: 1. Bild wählen. 2. Hochladen/ersetzen. 3. Entfernen und auf anderem Gerät prüfen. | Aktuelles Bild beziehungsweise Platzhalter; keine veraltete private Kopie sichtbar. |
| [ ] | T02-03 | Adresse: 1. Adressbereich öffnen. 2. Land und abweichende Adressformate erfassen. 3. Speichern. | Keine verlorenen Felder; länderspezifische Validierung nachvollziehbar. |
| [ ] | T02-04 | Sprache/Design: 1. Deutsch auf Englisch wechseln. 2. Helles/dunkles/Systemdesign testen. 3. App neu starten. | Auswahl bleibt erhalten; Übersetzungen, Kontraste und Zahlenformate stimmen. |
| [ ] | T02-05 | Profil-Sichtbarkeit: 1. Öffentlichkeit einschränken. 2. Als Freund, Fremder und Gast öffnen. 3. Globale Suche verwenden. | Nur freigegebene Daten sichtbar, einschließlich Vorschaubildern und Suchtreffern. |
| [ ] | T02-06 | Einwilligungen: 1. Datenschutz öffnen. 2. Optionale Tracking-/Analysezustimmungen einzeln ändern. 3. Web/App neu laden. | Unabhängige, dauerhafte Auswahl; Widerruf wirkt kanalübergreifend. |
| [ ] | T02-07 | Auskunft/Berichtigung: 1. Datenschutz → verfügbare Datenanfrage öffnen. 2. Export oder Korrektur anfordern. 3. Ergebnis/Status prüfen. | Nur eigene Daten; verständlicher Bearbeitungsstatus und zugriffsgeschützter Download. |
| [ ] | T02-08 | Daten löschen, Konto behalten: 1. Entsprechenden Datenschutzprozess öffnen. 2. Bestätigung anfordern und ausführen. 3. Anmeldung und Datenbestand prüfen. | Beschriebene Daten entfernt; Konto bleibt wie angekündigt nutzbar. |
| [ ] | T02-09 | Konto löschen: 1. Kontolöschung öffnen. 2. Falschen, dann gültigen Bestätigungscode verwenden. 3. Anmeldung/alte Links erneut testen. | Nur bestätigte zulässige Löschung; Sperrgründe klar; kein fremdes Konto betroffen. |
| [ ] | T02-10 | Einstellungs-Tabs: 1. Mehrere Tabs nacheinander öffnen. 2. Direkten Link mit `?tab=...` öffnen. 3. Fehler beim Nachladen simulieren. | Richtiger Tab und Inhalt; Eingaben bleiben bei Ladefehler erhalten. |
| [ ] | T02-11 | Fußnavigation/Module: 1. Navigationseinstellungen öffnen. 2. Erlaubte Ziele ändern. 3. Konto wechseln. | Auswahl nutzerbezogen; keine unerlaubten Module durch Personalisierung freischaltbar. |
| [ ] | T02-12 | API-Zugang, falls freigegeben: 1. Tokenbereich öffnen. 2. Token mit begrenzten Rechten erstellen. 3. Widerrufen und erneut verwenden. | Rechte begrenzt; widerrufenes Token unbrauchbar; Geheimnis nicht öffentlich sichtbar. |

## 03. Navigation, Suche und Arbeitsbereiche

Voraussetzung: jede Rolle separat sowie ein Konto mit mehreren Rollen/Vereinen. Einstieg: Seitenleiste/App-Menü, Arbeitsbereichsauswahl und globale Suche.

| Offen | ID | Szenario und Schritte | Erwartetes Ergebnis |
| --- | --- | --- | --- |
| [ ] | T03-01 | Seitenleiste: 1. Menü öffnen. 2. Jeden sichtbaren Eintrag einzeln öffnen. 3. Zurück navigieren. | Passendes Ziel, korrekte Markierung, keine Schleife oder leere Seite. |
| [ ] | T03-02 | Sportlernavigation: 1. Als Sportler anmelden. 2. Teams/Vereine, Training und Ernährung suchen. 3. Berechtigte Module öffnen. | Erlaubte Kernbereiche erreichbar, auch ohne Vereinsverwaltungsrolle. |
| [ ] | T03-03 | Vereinsnavigation: 1. Als Verein anmelden. 2. Support, Events, E-Learning, Feed, Team, Challenge, Dateien, Marketplace und Blog einzeln prüfen. 3. To-dos/Kalender öffnen. | Jeder berechtigte Eintrag verfügbar; To-dos und Kalender öffnen ihren eigenen Bereich. |
| [ ] | T03-04 | Adminnavigation: 1. Super-Admin anmelden. 2. Admin öffnen. 3. Mit eingeschränkter Rolle und normalem Verein wiederholen. | Anzeige und Zugriff passen zu Rechten; kein Adminzugang durch Rollenbezeichnung allein. |
| [ ] | T03-05 | Vereinswechsel: 1. A auswählen. 2. Datenbereich öffnen. 3. Zu B wechseln und zurück. | Listen, Kennzahlen, Erstellerformulare und Aktionen verwenden den aktiven Verein. |
| [ ] | T03-06 | Verein ohne Daten: 1. Leeren Verein beziehungsweise Vereinsrolle ohne Mitgliedschaft verwenden. 2. Teams/Kalender/Finanzen öffnen. 3. Suche ausführen. | Verständlicher Leerzustand; kein Rückfall auf sämtliche Vereinsdaten. |
| [ ] | T03-07 | Globale Suche: 1. Ein Zeichen, dann mindestens zwei eingeben. 2. Nach Person, Verein, Team, Event, Kurs, Produkt, Datei und Rechnung suchen. 3. Treffer öffnen. | Passende zulässige Ergebnisse; richtige Details; sensible fremde Treffer ausgeschlossen. |
| [ ] | T03-08 | Suche ohne Treffer: 1. Unbekannten Begriff eingeben. 2. Sehr langen Begriff/Sonderzeichen testen. 3. Suche leeren. | Stabile Darstellung, klare Rückmeldung und keine unkontrollierte Anfrageflut. |
| [ ] | T03-09 | Rollenänderung: 1. Seite offen halten. 2. Recht mit zweitem Konto entziehen. 3. Aktualisieren und Aktion wiederholen. | Server verweigert widerrufene Rechte; Menü aktualisiert sich. |
| [ ] | T03-10 | Gespeicherte Ansichten: 1. Unterstützte Liste filtern. 2. Ansicht speichern/umbenennen. 3. Wieder öffnen und löschen. | Persönlicher Filter korrekt wiederhergestellt; fremde Ansichten nicht veränderbar. |

## 04. Feed, Stories, Freunde und Meldungen

Voraussetzung: zwei verbundene und ein fremdes Testkonto. Einstieg: Feed beziehungsweise Freunde. Web: `/feed`, `/friends`.

| Offen | ID | Szenario und Schritte | Erwartetes Ergebnis |
| --- | --- | --- | --- |
| [ ] | T04-01 | Beitrag: 1. Erstellen öffnen. 2. Text und erlaubte Sichtbarkeit eingeben. 3. Veröffentlichen und beim Empfänger öffnen. | Genau ein Beitrag; Sichtbarkeit eingehalten. |
| [ ] | T04-02 | Beitragsmedien: 1. Bild/Video ergänzen. 2. Vorschau prüfen. 3. Veröffentlichen und im Feed/Detail öffnen. | Medien laden korrekt; keine abgeschnittenen Bedienelemente oder verlorenen Beschriftungen. |
| [ ] | T04-03 | Bearbeiten/Löschen: 1. Eigenen Beitrag bearbeiten. 2. Speichern. 3. Löschung erst abbrechen, dann bestätigen. | Änderungen bleiben; abgebrochene Löschung wirkungslos; bestätigte wirkt überall. |
| [ ] | T04-04 | Kommentare: 1. Kommentieren. 2. Eigene angebotene Bearbeitungs-/Löschaktion ausführen. 3. Als fremde Person dieselbe Aktion versuchen. | Zulässige Änderungen funktionieren; fremde Inhalte geschützt. |
| [ ] | T04-05 | Reaktionen: 1. Like/angebotene Reaktion setzen. 2. Wieder entfernen. 3. Schnell mehrfach tippen und aktualisieren. | Konsistente Zähler ohne mehrfach gezählte identische Reaktion. |
| [ ] | T04-06 | Stories: 1. Story erstellen. 2. Mit anderem Konto ansehen/reagieren. 3. Ablauf und eigene Löschung prüfen. | Sichtbarkeit und Ablauf korrekt; abgelaufene Medien nicht unbegrenzt erreichbar. |
| [ ] | T04-07 | Freunde/Einladungen: 1. Anfrage senden. 2. Annehmen, ablehnen und zurückziehen jeweils separat testen. 3. Beziehung entfernen. | Richtige Status/Zähler; keine doppelte Beziehung. |
| [ ] | T04-08 | Folgen: 1. Unterstütztes Profil öffnen. 2. Folgen und entfolgen. 3. Feed und Sichtbarkeit prüfen. | Konsistente Beziehung; private Daten nicht allein durch Folgen freigegeben. |
| [ ] | T04-09 | Melden/Blockieren: 1. Inhalt oder Profil melden. 2. Grund absenden. 3. Angebotene Blockierung und Gegenkonto prüfen. | Meldung nachvollziehbar; vorhandene Kontaktsperren greifen. |
| [ ] | T04-10 | Feed laden: 1. Mehrere Seiten scrollen. 2. Aktualisieren. 3. Filter/Sprachen wechseln. | Keine Duplikate oder fremden privaten Inhalte; neue Beiträge erscheinen. |
| [ ] | T04-11 | Moderationseinspruch: 1. Eigene Moderationsentscheidung öffnen. 2. Verfügbaren Einspruch mit Begründung senden. 3. Entscheidung/Status lesen. | Einspruch dem richtigen Vorgang zugeordnet; fremde Meldungen nicht einsehbar. |

## 05. Nachrichten und Gruppenchats

Voraussetzung: drei Testpersonen, davon eine außerhalb des Zielteams. Einstieg: Nachrichtensymbol → Unterhaltung; Web `/conversations`.

| Offen | ID | Szenario und Schritte | Erwartetes Ergebnis |
| --- | --- | --- | --- |
| [ ] | T05-01 | Direktchat: 1. Berechtigten Empfänger wählen. 2. Nachricht schreiben. 3. Auf zweitem Gerät lesen/antworten. | Richtiger Empfänger, persistente Nachricht und nachvollziehbarer Lesestatus. |
| [ ] | T05-02 | Chat-Einladung: 1. Unterhaltung mit erforderlicher Einladung starten. 2. Annahme beziehungsweise Ablehnung testen. 3. Schreibrechte prüfen. | Teilnahme nur nach dem vorgesehenen Zustimmungsprozess. |
| [ ] | T05-03 | Gruppenchat: 1. Gruppe erstellen. 2. Teilnehmer hinzufügen. 3. Als Teilnehmer öffnen. | Richtige Mitglieder; keine unberechtigte Person automatisch aufgenommen. |
| [ ] | T05-04 | Gruppenverwaltung: 1. Namen/zulässige Einstellungen ändern. 2. Teilnehmer entfernen. 3. Eigentümerschaft übertragen. | Nur berechtigte Verwaltung; entfernte Person verliert Zugriff. |
| [ ] | T05-05 | Anhänge: 1. Erlaubte Datei senden. 2. Beim Empfänger öffnen. 3. Fremden Direktlinkzugriff testen. | Datei vollständig; dieselben Rechte wie die Unterhaltung. |
| [ ] | T05-06 | Reaktion/Löschen: 1. Auf Nachricht reagieren. 2. Eigene Nachricht löschen oder lokal ausblenden. 3. Auf beiden Konten vergleichen. | Angezeigter Unterschied zwischen Ausblenden und Löschen wird eingehalten. |
| [ ] | T05-07 | Stumm/Gelesen: 1. Chat stummschalten. 2. Neue Nachricht senden. 3. Lesen und Zähler prüfen. | Einstellungen und Ungelesen-Zähler konsistent. |
| [ ] | T05-08 | Verlassen: 1. Gruppenchat mit mindestens zwei verbleibenden Personen verlassen. 2. Alten Link öffnen und erneuten Abruf/Versand versuchen. 3. In einer separaten Gruppe als letztes Mitglied über Web und App verlassen. 4. Datenbankzustand von Unterhaltung, Mitgliedschaften und Nachrichten prüfen. | Keine fortgesetzte Teilnahme ohne Berechtigung. Solange mindestens eine Person bleibt, bleiben Gruppe und Verlauf erhalten. Verlässt das letzte Mitglied die Gruppe, werden Unterhaltung, Mitgliedschaften und Nachrichten vollständig gelöscht. |
| [ ] | T05-09 | Verbindungsausfall: 1. Nachricht bei Netzunterbrechung senden. 2. Netz herstellen. 3. Wiederholung/Empfang prüfen. | Kein stiller Verlust oder unbemerkter Doppelversand; Sendezustand klar. |
| [ ] | T05-10 | Direktchat löschen/blockieren: 1. A und B erzeugen Verlauf; A löscht den Chat im Web. 2. Prüfen, dass B den vollständigen Verlauf behält und A den Chat nicht mehr in der Liste/über alten Nachrichtenlink sieht. 3. B sendet neu; bei A darf nur der neue Abschnitt wieder erscheinen. 4. Ablauf in der App wiederholen. 5. A blockiert B im Chat; Senden in beide Richtungen, Freundschaft und offene Einladung prüfen, danach entblockieren und Privatsphäre-Regel gegenprüfen. | Löschen wirkt nur für A und synchron auf A-Geräten; B verliert nichts. Neue Nachricht öffnet den Chat ohne alten Verlauf. Blockierung greift in Web/API/App identisch und hebt die Freundschaft auf. |

## 06. Benachrichtigungen und Direktlinks

Voraussetzung: Testpostfächer und Geräte mit erlaubten/verweigerten Pushrechten. Einstieg: Glocke, Push oder Test-Mail. Jeden unterstützten Nachrichtentyp einzeln durchführen.

| Offen | ID | Szenario und Schritte | Erwartetes Ergebnis |
| --- | --- | --- | --- |
| [ ] | T06-01 | Zentrum: 1. Nachricht erzeugen. 2. Glocke öffnen. 3. Gelesen/ungelesen, alle gelesen und Löschen prüfen. | Richtige Liste und Zähler; nur eigene Nachrichten veränderbar. |
| [ ] | T06-02 | Mitgliedsrechnung: 1. Verein stellt Sportler Rechnung. 2. Sportler klickt Benachrichtigung. 3. Rechnung öffnen. | Eigene Rechnung erreichbar, kein 403 und kein falscher Einstellungs-Tab. Webziel `/settings?tab=billing#billing`. |
| [ ] | T06-03 | Weitere Rechnungsnachrichten: 1. Erinnerung, bezahlt und Zahlungseingang einzeln auslösen. 2. Jeweilige Nachricht öffnen. 3. Identität/Status vergleichen. | Richtige Rechnung und aktueller Stand für den berechtigten Empfänger. |
| [ ] | T06-04 | Fachlinks: 1. Team-, Vereins-, Aufgaben-, Event-, Chat-, Freundschafts- und Lernnachrichten erzeugen. 2. Jede öffnen. 3. Objekt prüfen. | Objektbezogenes Ziel; keine unpassende Start-/Einstellungsseite. |
| [ ] | T06-05 | Appzustände: 1. App im Vordergrund, Hintergrund und beendet testen. 2. Push anklicken. 3. Zurück navigieren. | Gleicher fachlicher Zielinhalt und sinnvoller Zurückweg. |
| [ ] | T06-06 | Ausgeloggt: 1. Abmelden. 2. Benachrichtigungslink öffnen. 3. Als richtige Person anmelden. | Berechtigtes Ziel nach Login; keine Daten vor Anmeldung. |
| [ ] | T06-07 | Falsches Konto/Verein: 1. Nachricht für A mit B öffnen. 2. Arbeitsbereich wechseln. 3. Rechte kontrollieren. | Kein Zugriff durch fremden Link; erforderlicher Wechsel erklärt. |
| [ ] | T06-08 | Gelöschtes Ziel/Altlink: 1. Ziel löschen oder Rechte entziehen. 2. Alte Nachricht öffnen. 3. Ungültigen Link testen. | Verständlicher Zustand statt Absturz; kein Ausweichen auf fremde Daten. |
| [ ] | T06-09 | Präferenzen/Ruhezeiten: 1. Kategorien und Kanäle einstellen. 2. Passende Nachrichten auslösen. 3. Ruhezeit und Sicherheitsmeldung prüfen. | Versand folgt der Kategorie-/Ruhezeitregel einschließlich definierter Sicherheitsausnahmen. |
| [ ] | T06-10 | Gerät und Konto wechseln: 1. Push erlauben/verweigern. 2. Konto abmelden und anderes anmelden. 3. An altes Konto senden. | Keine Pushinhalte des vorherigen Kontos auf dessen abgemeldeter Sitzung. |
| [ ] | T06-11 | Wiederholung: 1. Dasselbe Ereignis erneut zustellen lassen. 2. Liste/Push prüfen. 3. Auf zweitem Gerät lesen. | Definierte Deduplizierung und konsistenter Lesestatus. |

## 07. Dateien und Dokumentzugriff

Voraussetzung: private Datei, Teamdatei, Vereinsdatei und externer Freigabelink. Einstieg: Dateien; Web `/files`.

| Offen | ID | Szenario und Schritte | Erwartetes Ergebnis |
| --- | --- | --- | --- |
| [ ] | T07-01 | Upload: 1. Datei hinzufügen. 2. Erlaubte Datei wählen. 3. Hochladen und Vorschau/Download öffnen. | Inhalt, Dateiname, Größe und Zuordnung korrekt. |
| [ ] | T07-02 | Uploadgrenzen: 1. Leere, zu große und unzulässige Datei einzeln wählen. 2. Hochladen. 3. Mit erlaubter Datei fortfahren. | Verständliche Fehler; keine unvollständige freigegebene Datei. |
| [ ] | T07-03 | Ordner: 1. Ordner erstellen. 2. Umbenennen/zuordnen. 3. Leeren und belegten Ordner löschen versuchen. | Konsistente Struktur und erklärter Umgang mit enthaltenen Dateien. |
| [ ] | T07-04 | Datei bearbeiten/löschen: 1. Metadaten ändern. 2. Speichern. 3. Löschen und alten Download prüfen. | Zulässige Änderung dauerhaft; gelöschter Inhalt nicht weiter freigegeben. |
| [ ] | T07-05 | Teilen: 1. Datei/Ordner für Person, Team oder Verein freigeben. 2. Als Empfänger öffnen. 3. Als Außenstehender testen. | Nur der gewählte Empfängerkreis erhält Zugriff. |
| [ ] | T07-06 | Öffentlicher Link: 1. Unterstützten Freigabelink erzeugen. 2. Ausgeloggt öffnen. 3. Ablauf/Widerruf testen. | Zugriff exakt entsprechend Freigabe und Gültigkeit. |
| [ ] | T07-07 | Beziehungswechsel: 1. Datei über Team/Event/Aufgabe freigeben. 2. Empfänger entfernen. 3. Alten Link öffnen. | Aktuelle Rechte gelten auch für gespeicherte Links. |
| [ ] | T07-08 | Speicherlimit/Unterbrechung: 1. Limit erreichen oder Upload unterbrechen. 2. Erneut versuchen. 3. Speicheranzeige vergleichen. | Kein falscher Erfolg; konsistentes Kontingent und verständliche Wiederholung. |

## 08. Sportler: Verein, Team und eigener Mitgliedsbereich

Voraussetzung: Sportlerkonto; Vereinsaufnahme und Teameinladung als Testdaten. Einstieg: Vereine & Teams beziehungsweise Einstellungen → Vereine verwalten.

| Offen | ID | Szenario und Schritte | Erwartetes Ergebnis |
| --- | --- | --- | --- |
| [ ] | T08-01 | Verein entdecken: 1. Team/Verein finden öffnen. 2. Nach Ort/Sport/Name suchen. 3. Öffentliches Profil öffnen. | Nur laut Sichtbarkeitsregel öffentliche Informationen; keine internen Mitglieder-/Finanzdaten. |
| [ ] | T08-02 | Verifikationsstatus: 1. Vereine in Prüfung, verifiziert, abgelehnt und nicht öffentlich vorbereiten. 2. Suche/Verzeichnis als Gast und Sportler prüfen. 3. Regeln mit Produktvorgabe vergleichen. | Status und Sichtbarkeit nicht unbelegt gleichsetzen; abweichende Suchwege dokumentieren. |
| [ ] | T08-03 | Beitrittsantrag: 1. Vereinsprofil → Beitreten öffnen. 2. Pflichtangaben/Dokumente ausfüllen. 3. Absenden und Status öffnen. | Genau ein Antrag; noch keine unberechtigte Mitgliedschaft. |
| [ ] | T08-04 | Antragsverlauf: 1. Nachforderung erhalten und beantworten. 2. Warteliste, Annahme, Ablehnung getrennt prüfen. 3. Benachrichtigung öffnen. | Richtiger Status, nachvollziehbare Rückmeldung und passender Folgezugriff. |
| [ ] | T08-05 | Antrag zurückziehen: 1. Offenen Antrag öffnen. 2. Zurückziehen. 3. Vereinsseite erneut öffnen. | Kein weiter bearbeitbarer alter Antrag; definierter neuer Antrag möglich. |
| [ ] | T08-06 | Einladung: 1. Team-/Vereinslink öffnen. 2. Annehmen oder ablehnen. 3. Wiederholt, abgelaufen und unter falschem Konto testen. | Kein unbeabsichtigter Beitritt; richtige Mitgliedszuordnung. |
| [ ] | T08-07 | Eigene Mitgliedschaft: 1. Mitgliedschaft öffnen. 2. Stammdaten, Typ und Status prüfen. 3. Zulässige Änderung beantragen. | Nur eigene Daten änderbar; genehmigungspflichtige Änderung nicht direkt aktiv. |
| [ ] | T08-08 | Mitgliedskarte: 1. Karte öffnen. 2. Berechtigten Scan durchführen. 3. Code erneuern und alten Code testen. | Karte personengebunden; alter Code nach Erneuerung ungültig. |
| [ ] | T08-09 | Pause/Austritt: 1. Mitgliedschaft → angebotenen Antrag öffnen. 2. Datum/Grund senden. 3. Vor und nach Wirksamkeitsdatum prüfen. | Antrag, Fristen, Beiträge und Zugriff folgen dem vorgesehenen Lebenszyklus. |
| [ ] | T08-10 | Mehrere Vereine: 1. A und B öffnen. 2. Eigene Termine/Rechnungen vergleichen. 3. Aus A austreten. | B bleibt unberührt; A-Daten nur nach verbleibenden zulässigen Rechten verfügbar. |
| [ ] | T08-11 | Widerspruch gegen Entfernung: 1. Unterstützte Entfernungsmeldung öffnen. 2. Widerspruch erfassen. 3. Bearbeitungsstatus prüfen. | Vorgesehener Prozess dokumentiert; kein automatischer Zugriff auf fremde Verwaltungsdaten. |

## 09. Sportler: Sportprofil, Training und Fortschritt

Voraussetzung: Sportler, zugewiesener Trainingsplan und berechtigter Trainer. Einstieg: Sportprofil/Training, Web `/training`.

| Offen | ID | Szenario und Schritte | Erwartetes Ergebnis |
| --- | --- | --- | --- |
| [ ] | T09-01 | Sportprofil: 1. Sportart hinzufügen. 2. Niveau, Fähigkeiten und Sichtbarkeit pflegen. 3. Speichern/Profilansicht prüfen. | Sportartspezifische Angaben korrekt und gemäß Sichtbarkeit angezeigt. |
| [ ] | T09-02 | Aktivität: 1. Manuelle Aktivität anlegen. 2. Dauer/Datum/Werte erfassen. 3. Bearbeiten und löschen. | Werte korrekt; ungültige Zahlen abgewiesen; Fortschritt konsistent. |
| [ ] | T09-03 | Plan lesen: 1. Zugewiesenen Plan öffnen. 2. Zeitraum und Einheit wählen. 3. Übungsdetails öffnen. | Richtiger Sportler/Zeitraum; vollständige Einheiten und Hinweise. |
| [ ] | T09-04 | Training protokollieren: 1. Einheit/Protokoll öffnen. 2. Tatsächliche Werte erfassen. 3. Speichern und Details erneut öffnen. | Protokoll dauerhaft und korrekt zugeordnet. |
| [ ] | T09-05 | Entwurf: 1. Protokoll teilweise ausfüllen. 2. Als Entwurf speichern. 3. Fortsetzen oder verwerfen. | Entwurf nicht als abgeschlossene Leistung gezählt; Eingaben bleiben erhalten. |
| [ ] | T09-06 | Protokoll korrigieren: 1. Eigenen Eintrag öffnen. 2. Zulässige Werte ändern/löschen. 3. Auswertung prüfen. | Historie und Summen stimmen; fremde Einträge geschützt. |
| [ ] | T09-07 | Trainerfeedback: 1. Trainer gibt Feedback. 2. Sportler öffnet Hinweis. 3. Protokoll und Feedback lesen. | Richtiger Empfänger; keine Daten anderer Sportler. |
| [ ] | T09-08 | Verfügbarkeit: 1. Training → Verfügbarkeit öffnen. 2. Verfügbar/abwesend samt Zeitraum speichern. 3. Bearbeiten/löschen und Traineransicht prüfen. | Nur berechtigte Trainer sehen die zulässigen Angaben. |
| [ ] | T09-09 | Fortschritt: 1. Mehrere Protokolle anlegen. 2. Zeitraum/Sportart filtern. 3. Kennzahlen mit Ausgangswerten vergleichen. | Korrekte Einheiten, Summen und verständlicher Leerzustand. |
| [ ] | T09-10 | Anbieter verbinden: 1. Sport-Apps öffnen. 2. Angebotenen Provider verbinden und synchronisieren. 3. Trennen/erneut verbinden. | Kein Token im UI; keine doppelten Aktivitäten; Widerruf wirkt. |
| [ ] | T09-11 | KI-Plan, falls freigegeben: 1. Ziele/Parameter eingeben. 2. Vorschau erzeugen. 3. Prüfen und ausdrücklich übernehmen. | Kein unbestätigter Plan; Fehler, Limits und Herkunft verständlich. |

## 10. Sportler: Ernährung und Trinken

Voraussetzung: Sportlerkonto mit Ernährungsbereich. Einstieg: Ernährung; Web `/nutrition`.

| Offen | ID | Szenario und Schritte | Erwartetes Ergebnis |
| --- | --- | --- | --- |
| [ ] | T10-01 | Tagesübersicht: 1. Ernährung öffnen. 2. Vor-/Folgetag wählen. 3. Ziele/Summen vergleichen. | Richtiger Tag; keine Vermischung zwischen Tagen oder Konten. |
| [ ] | T10-02 | Mahlzeit: 1. Mahlzeit hinzufügen. 2. Lebensmittel und Menge wählen. 3. Speichern. | Nährwerte und Tagessumme folgen der eingegebenen Menge. |
| [ ] | T10-03 | Lebensmittelsuche: 1. Namen suchen. 2. Produkt auswählen. 3. Ohne Treffer und bei Netzfehler wiederholen. | Eindeutige Produkte und klarer Fehler-/Leerzustand. |
| [ ] | T10-04 | Barcode: 1. Scan/Barcodeeingabe öffnen. 2. Bekannten und unbekannten Code testen. 3. Kamerazugriff verweigern. | Kein falsches Lebensmittel; nutzbarer Alternativweg oder klare Meldung. |
| [ ] | T10-05 | Bildanalyse: 1. Mahlzeitenbild auswählen. 2. Analyse prüfen/korrigieren. 3. Erst danach speichern. | Schätzung nicht als sicherer Messwert behandelt; keine ungeprüfte automatische Buchung. |
| [ ] | T10-06 | Korrigieren: 1. Mahlzeit bearbeiten. 2. Menge ändern. 3. Löschen und Tageswerte vergleichen. | Summen korrekt neu berechnet. |
| [ ] | T10-07 | Ziele/Wasser: 1. Zulässiges Ziel einstellen. 2. Wasser erfassen. 3. Tageswechsel und Erinnerungen prüfen. | Werte dauerhaft; Einheiten/Tag stimmen; Erinnerung folgt Einstellung. |
| [ ] | T10-08 | Privatheit: 1. Daten als Sportler anlegen. 2. Mit anderem Sportler/Trainer aufrufen. 3. Direkten Zugriff testen. | Keine automatische Freigabe sensibler Daten durch gemeinsame Teamzugehörigkeit. |

## 11. Sportler: Sportkarte, Routen und Tracking

Voraussetzung: Testgerät mit und ohne Standortrecht. Einstieg: Sportkarte/Routen; Web `/sport-map`.

| Offen | ID | Szenario und Schritte | Erwartetes Ergebnis |
| --- | --- | --- | --- |
| [ ] | T11-01 | Karte: 1. Karte öffnen. 2. Standort erlauben/verweigern. 3. Orte suchen und Details öffnen. | Karte bedienbar; verweigertes Recht führt nicht zum Absturz. |
| [ ] | T11-02 | Route anlegen: 1. Neue Route öffnen. 2. Punkte und erlaubte Angaben erfassen. 3. Speichern und erneut öffnen. | Verlauf und Daten erhalten; Sichtbarkeit beachtet. |
| [ ] | T11-03 | Route verwalten: 1. Eigene Route bearbeiten. 2. Duplizieren. 3. Kopie löschen. | Original unverändert; Kopie und Berechtigungen korrekt. |
| [ ] | T11-04 | Track: 1. Aufzeichnung starten. 2. Punkte aufzeichnen. 3. Beenden und Zusammenfassung öffnen. | Dauer/Distanz/Verlauf plausibel; kein unbeabsichtigter Dauertrack. |
| [ ] | T11-05 | Unterbrechung: 1. Track im Hintergrund/bei Displaysperre führen. 2. GPS/Netz kurz verlieren. 3. Zurückkehren. | Datenlücken ehrlich dargestellt; kein unbemerkter Verlust oder erfundener Verlauf. |
| [ ] | T11-06 | Routenverknüpfung: 1. Route an Training/Event hängen. 2. Als Teilnehmer öffnen. 3. Zugriffsrecht entziehen. | Richtige Route; Verknüpfung umgeht keine Sichtbarkeit. |
| [ ] | T11-07 | Ort/Vorschlag: 1. Unterstützten Ort oder Routenvorschlag erfassen. 2. Speichern. 3. Freigabe-/Sichtbarkeitsstatus prüfen. | Nur vorgesehene Veröffentlichung; Koordinaten und Angaben validiert. |

## 12. Sportler und Trainer: Matching und Recruiting

Voraussetzung: Anbieter und Bewerber in getrennten Konten. Einstieg: Sport-Matching, Jobs beziehungsweise Recruiting-Pipeline.

| Offen | ID | Szenario und Schritte | Erwartetes Ergebnis |
| --- | --- | --- | --- |
| [ ] | T12-01 | Matching suchen: 1. Angebote öffnen. 2. Ort/Sport/Zeitraum filtern. 3. Angebot öffnen. | Passende sichtbare Angebote; keine internen Bewerberdaten. |
| [ ] | T12-02 | Matching anlegen: 1. Angebot erstellen. 2. Ort, Zeit und Kapazität ausfüllen. 3. Veröffentlichen. | Gültiges Angebot mit korrektem Eigentümer. |
| [ ] | T12-03 | Bewerben: 1. Teilnahme anfragen. 2. Anfrage als Anbieter annehmen/ablehnen. 3. Bewerberstatus prüfen. | Kapazität und Teilnahme korrekt; keine doppelte Zusage. |
| [ ] | T12-04 | Rückzug/Absage: 1. Eigene Bewerbung zurückziehen. 2. Anderen Durchlauf als Veranstalter absagen. 3. Empfänger prüfen. | Freie Plätze und Hinweise aktualisiert; klare Zuständigkeit. |
| [ ] | T12-05 | Anwesenheit/No-show: 1. Erlaubten Termin öffnen. 2. Teilnahme erfassen. 3. Korrektur prüfen. | Nur zuständige Person kann fremde Teilnahme verändern. |
| [ ] | T12-06 | Jobinteresse: 1. Öffentliche Stelle öffnen. 2. Interesse mit Testdaten senden. 3. Eingang beim zuständigen Verein/Anbieter prüfen. | Bewerbung landet ausschließlich bei zuständigen Empfängern. |
| [ ] | T12-07 | Pipeline: 1. Bewerbung öffnen. 2. Angebotenen Status ändern und Chat starten. 3. Ablehnen/entfernen separat prüfen. | Status/Chat korrekt; keine fremden Bewerbungen sichtbar. |

## 13. Challenges, Badges und Motivation

Voraussetzung: Challenge-Ersteller und Teilnehmer. Einstieg: Challenges/Badges.

| Offen | ID | Szenario und Schritte | Erwartetes Ergebnis |
| --- | --- | --- | --- |
| [ ] | T13-01 | Challenge anlegen: 1. Erstellen öffnen. 2. Ziel, Zeitraum und Sichtbarkeit wählen. 3. Speichern. | Gültige Challenge; Zeitraum/Ziel nachvollziehbar. |
| [ ] | T13-02 | Beitreten/Einladung: 1. Challenge öffnen. 2. Beitreten beziehungsweise Einladung beantworten. 3. Teilnehmerliste prüfen. | Keine doppelte Teilnahme; Sichtbarkeit eingehalten. |
| [ ] | T13-03 | Check-in: 1. Erlaubten Tag wählen. 2. Fortschritt erfassen. 3. Erneut absenden/korrigieren. | Kein mehrfach gezählter identischer Check-in; Datumsregeln greifen. |
| [ ] | T13-04 | Kommentare: 1. Kommentar senden. 2. Als Teilnehmer lesen. 3. Als Außenstehender öffnen. | Nur berechtigter Kreis; korrekte Zuordnung. |
| [ ] | T13-05 | Abschluss/Absage: 1. Ziel oder Ende erreichen. 2. Ergebnis prüfen. 3. Separate Challenge absagen. | Richtiger Endzustand, keine Fortschreibung abgesagter Wettbewerbe. |
| [ ] | T13-06 | Punkte/Badges: 1. Relevante Aktivität abschließen. 2. Auszeichnung öffnen. 3. Ereignis erneut verarbeiten lassen. | Vergabe nachvollziehbar und nicht doppelt; privater Fortschritt geschützt. |

## 14. Alle Rollen: E-Learning als Teilnehmer

Voraussetzung: veröffentlichter freier/bezahlter Kurs und eingeschriebener Teilnehmer. Einstieg: E-Learning → Meine Kurse; Web `/learning/my-courses`.

| Offen | ID | Szenario und Schritte | Erwartetes Ergebnis |
| --- | --- | --- | --- |
| [ ] | T14-01 | Kurs entdecken: 1. Katalog filtern. 2. Kursdetails öffnen. 3. Preis/Voraussetzungen ansehen. | Veröffentlichte, zugängliche Angebote mit zutreffenden Angaben. |
| [ ] | T14-02 | Einschreiben: 1. Freien Kurs buchen. 2. Separat kostenpflichtigen Testkurs buchen. 3. Meine Kurse prüfen. | Genau eine Einschreibung; kostenpflichtige Freigabe erst nach vorgesehenem Zahlungsstatus. |
| [ ] | T14-03 | Lektion: 1. Lektion öffnen. 2. Medien abspielen/pausieren. 3. Verlassen und fortsetzen. | Fortschritt gespeichert; geschützte Medien nicht öffentlich zugänglich. |
| [ ] | T14-04 | Abschluss: 1. Lektion abschließen. 2. Auf anderem Gerät öffnen. 3. Noch gesperrte Lektion testen. | Konsistenter Fortschritt und korrekte Freigaberegeln. |
| [ ] | T14-05 | Notizen/Kommentare: 1. Private Notiz speichern. 2. Kommentar senden. 3. Mit anderem Konto vergleichen. | Private Notiz privat; Kommentar nur im vorgesehenen Kreis sichtbar. |
| [ ] | T14-06 | Aufgabe: 1. Kursaufgabe öffnen. 2. Text/erlaubten Anhang abgeben. 3. Bewertung lesen. | Richtige Abgabe und Teilnehmerzuordnung; Fristen/Dateigrenzen eingehalten. |
| [ ] | T14-07 | Quiz: 1. Quiz beantworten. 2. Absenden. 3. Fehlversuch, Bestehen und erlaubten Wiederholungsversuch testen. | Korrekte Bewertung/Versuchsgrenzen; keine Lösung vorzeitig offengelegt. |
| [ ] | T14-08 | Bewertung/Zertifikat: 1. Berechtigten Kurs bewerten. 2. Abschluss erreichen. 3. Zertifikat herunterladen/verifizieren. | Bewertung und Nachweis nur nach vorgesehenen Voraussetzungen. |
| [ ] | T14-09 | Entzogener Zugang: 1. Einschreibung widerrufen lassen. 2. Alten Kurs-/Videolink öffnen. 3. Neue Anmeldung prüfen. | Widerruf wirkt auf Inhalt und direkte Medienwege. |

## 15. Trainer: Cockpit, Planung und Betreuung

Voraussetzung: Trainer mit zugewiesenem Team und mehreren Sportlern; separates unberechtigtes Trainerkonto. Einstieg: Trainer-Cockpit/Training.

| Offen | ID | Szenario und Schritte | Erwartetes Ergebnis |
| --- | --- | --- | --- |
| [ ] | T15-01 | Trainerstatus: 1. Trainerrolle beantragen, falls erforderlich. 2. Prüfstatus verfolgen. 3. Nach Freigabe anmelden. | Rolle erst entsprechend Genehmigung aktiv; Ablehnung nachvollziehbar. |
| [ ] | T15-02 | Cockpit: 1. Team/Woche auswählen. 2. Offene Aktionen und Kennzahlen öffnen. 3. Zu anderem berechtigten Team wechseln. | Daten und Aktionen beziehen sich auf die Auswahl. |
| [ ] | T15-03 | Plan erstellen: 1. Neuer Trainingsplan. 2. Person/Team, Zeitraum und Ziel wählen. 3. Entwurf speichern. | Gültiger eigener Entwurf, noch keine unbeabsichtigte Veröffentlichung. |
| [ ] | T15-04 | Plan bearbeiten: 1. Einheiten hinzufügen. 2. Reihenfolge/Werte ändern oder Einheit entfernen. 3. Speichern. | Vollständiger konsistenter Plan ohne verlorene Einheiten. |
| [ ] | T15-05 | Veröffentlichen: 1. Entwurf prüfen. 2. Veröffentlichen. 3. Als Sportler öffnen. | Richtige Zielgruppe erhält exakt diesen Plan. |
| [ ] | T15-06 | Kopie/Vorlage: 1. Plan oder Einheit duplizieren. 2. Als Vorlage speichern. 3. Neuen Plan daraus erzeugen. | Original bleibt erhalten; Termine und Zielpersonen bewusst gesetzt. |
| [ ] | T15-07 | Übergabe: 1. Unterstützte Planübergabe wählen. 2. Zulässigen Trainer bestimmen. 3. Rechte auf beiden Konten prüfen. | Eigentum/Betreuung nach vorgesehener Regel; fremde Trainer nicht auswählbar. |
| [ ] | T15-08 | Übungsbibliothek: 1. Übung suchen/anlegen. 2. Bearbeiten und Plan zuordnen. 3. Eigene Übung löschen versuchen. | Verknüpfte Pläne bleiben konsistent; Nutzung/Berechtigung nachvollziehbar. |
| [ ] | T15-09 | Trainingseinheit: 1. Sitzung planen. 2. Zeit/Teilnehmer ändern. 3. Nach Durchführung dokumentieren. | Planung und Protokoll korrekt verknüpft. |
| [ ] | T15-10 | Verpasst/Feedback: 1. Verpasste Einheit markieren. 2. Sportlerprotokoll öffnen. 3. Feedback senden. | Status und Rückmeldung korrekt; keine Leistung ohne Durchführung gutgeschrieben. |
| [ ] | T15-11 | Belastung/Verfügbarkeit: 1. Auswertungen öffnen. 2. Person/Zeitraum wählen. 3. Berechtigung und zugrunde liegende Daten prüfen. | Hinweise nachvollziehbar; sensible Daten nur entsprechend Freigaben. |
| [ ] | T15-12 | Trainer entfernen: 1. Trainerzuordnung entziehen. 2. Offenen Plan aktualisieren. 3. Bearbeitung/Export versuchen. | Ehemaliger Trainer verliert nicht mehr erlaubten Zugriff. |

## 16. Trainer und berechtigte Autoren: Lernstudio

Voraussetzung: explizite Autorenrechte; Trainerrolle allein nicht als Berechtigung voraussetzen. Einstieg: Lernstudio, Web `/learning/studio`; fehlende mobile Aktionen als Lücke erfassen.

| Offen | ID | Szenario und Schritte | Erwartetes Ergebnis |
| --- | --- | --- | --- |
| [ ] | T16-01 | Kursentwurf: 1. Kurs anlegen. 2. Titel, Beschreibung und Preisoptionen setzen. 3. Speichern. | Entwurf dauerhaft und noch nicht ungewollt öffentlich. |
| [ ] | T16-02 | Struktur: 1. Abschnitte/Lektionen hinzufügen. 2. Reihenfolge ändern. 3. Lektion bearbeiten/löschen. | Teilnehmeransicht folgt gespeicherter Struktur. |
| [ ] | T16-03 | Medien: 1. Kursmaterial hochladen. 2. Vorschau ansehen. 3. Fehlerformat und Uploadabbruch testen. | Nur geeignete vollständige Medien freigegeben. |
| [ ] | T16-04 | Prüfungen: 1. Quiz/Aufgabe erstellen. 2. Teilnehmer abgeben lassen. 3. Bewertung/Feedback speichern. | Kriterien, Ergebnis und Empfänger stimmen. |
| [ ] | T16-05 | Teilnehmerzugang: 1. Einschreibung zuweisen. 2. Teilnahmebestätigung öffnen. 3. Zugang widerrufen. | Richtige Person; Widerruf schützt Kurs und Medien. |
| [ ] | T16-06 | Kommentare: 1. Teilnehmerkommentar öffnen. 2. Antworten/moderieren. 3. Sichtbarkeit prüfen. | Autorisierte Moderation ohne fremde private Notizen. |
| [ ] | T16-07 | Gutscheine/Berichte: 1. Unterstützten Coupon anlegen. 2. Einlösung testen. 3. Kurs-/Angebotsbericht exportieren. | Bedingungen und Zahlen korrekt; Export nur für Berechtigte. |
| [ ] | T16-08 | Veröffentlichung/Qualität: 1. Verfügbare Freigabeaktion ausführen. 2. Adminprüfung durchführen. 3. Web und App vergleichen. | Gleicher Freigabestatus; fehlende native Qualitätsprüfung als Lücke protokollieren. |

## 17. Verein: Einrichtung, Profil und Cockpit

Voraussetzung: Vereinsbesitzer und eingeschränkter Vereinsverantwortlicher. Einstieg: Vereins-Cockpit/Vereine verwalten; Web `/club-cockpit` und `/clubs/{club}`.

| Offen | ID | Szenario und Schritte | Erwartetes Ergebnis |
| --- | --- | --- | --- |
| [ ] | T17-01 | Erster Verein: 1. Verein registrieren öffnen. 2. Pflicht-/Registerdaten ausfüllen. 3. Absenden und Status prüfen. | Verein mit korrektem Besitzer und Prüfstatus; keine automatische Plattformrolle. |
| [ ] | T17-02 | Zweiter Verein: 1. Mit bestehendem Vereinskonto Verein registrieren wählen. 2. B anlegen. 3. Zwischen A/B wechseln. | Beide Vereine getrennt verwaltbar; A nicht überschrieben. |
| [ ] | T17-03 | Profil: 1. Stammdaten, Kontakt, Sportart und Ort bearbeiten. 2. Speichern. 3. Öffentliche/private Ansicht vergleichen. | Sichtbarkeitsregeln und getrennte Bearbeitungsrechte eingehalten. |
| [ ] | T17-04 | Branding: 1. Vereinslogo/Bilder ändern. 2. Helles/dunkles Design prüfen. 3. App/Web neu öffnen. | Passendes aktuelles Bild, kein Layoutbruch. |
| [ ] | T17-05 | Verifikationsdaten: 1. Registerangaben/Nachweise ergänzen. 2. Einreichen. 3. Prüfhinweise ansehen. | Keine Selbstverifikation; Rückmeldung und Status nachvollziehbar. |
| [ ] | T17-06 | Cockpitkennzahlen: 1. Verein wählen. 2. Mitglieder/Beiträge/Termine öffnen. 3. Detailzahlen vergleichen. | Kennzahlen und Detailbereich gehören zum gleichen Verein. |
| [ ] | T17-07 | Schnellaktionen: 1. Auswahl bearbeiten. 2. Bis zu fünf Aktionen markieren. 3. Speichern, neu laden und entfernen/ersetzen. | Neue Auswahl funktioniert; maximal fünf; Zustand bleibt erhalten. |
| [ ] | T17-08 | Schnellaktionsziele: 1. Mitglied hinzufügen, Kalender und To-dos auswählen. 2. Jede Aktion öffnen. 3. Zurückkehren. | Direkter passender Ablauf im aktiven Verein. |
| [ ] | T17-09 | Module/Tarif: 1. Erlaubte Moduleinstellungen öffnen. 2. Modul aktivieren/deaktivieren. 3. Menü und Direktlink prüfen. | Sichtbarkeit und serverseitige Freigabe stimmen überein. |

## 18. Verein: Mitglieder, Import und Anträge

Voraussetzung: Mitgliederverwaltungsrecht in A, Testimport und nicht registrierte Testperson. Einstieg: Mitglieder & Beiträge; Web `/club-memberships`.

| Offen | ID | Szenario und Schritte | Erwartetes Ergebnis |
| --- | --- | --- | --- |
| [ ] | T18-01 | Mitglied anlegen: 1. Mitglied hinzufügen/anlegen öffnen. 2. Person und notwendige Daten erfassen. 3. Speichern. | Person eindeutig A zugeordnet; App bietet einen erreichbaren Erstellungsweg. |
| [ ] | T18-02 | Bestehendes Konto: 1. Vorhandene Person wählen. 2. Aufnahme ausführen. 3. Profil/Einladung kontrollieren. | Kein zweites Benutzerkonto und kein unzulässiger Direktbeitritt. |
| [ ] | T18-03 | Externes Mitglied: 1. Ohne Benutzerkonto anlegen. 2. Daten bearbeiten. 3. Einladung senden. | Datensatz bleibt nutzbar; Verknüpfung erst mit passender Identität. |
| [ ] | T18-04 | Einladungstoken: 1. Link erzeugen. 2. Richtige Person anmelden/annehmen lassen. 3. Falsche Person/abgelaufenen Link testen. | Keine Übernahme fremder Mitgliedsdaten. |
| [ ] | T18-05 | Importvorschau: 1. Import öffnen. 2. Beispieldatei auswählen/Spalten zuordnen. 3. Vorschau lesen. | Alle Zeilen und Fehler nachvollziehbar; Vorschau importiert noch nichts. |
| [ ] | T18-06 | Import durchführen: 1. Gültige Vorschau bestätigen. 2. Ergebnis prüfen. 3. Liste und Anzahl vergleichen. | Korrekte Datensätze/Fehlerbericht; keine still verlorenen Zeilen. |
| [ ] | T18-07 | Importfehler: 1. Dubletten, leere Felder, Umlaute und falsche Datumsformate testen. 2. Korrigieren. 3. Datei erneut importieren. | Keine unbeabsichtigten Doppelmitglieder; Fehler zeilenbezogen. |
| [ ] | T18-08 | Zusammenführen: 1. Externe Dublette und passendes Konto wählen. 2. Zusammenführung prüfen/bestätigen. 3. Beiträge, Beziehungen und Historie kontrollieren. | Keine falsche Person; Beziehungen erhalten und nur eine Mitgliedschaft. |
| [ ] | T18-09 | Suche/Filter/Seiten: 1. Nach Status/Name filtern. 2. Weitere Seiten öffnen. 3. Mitglied bearbeiten und zurückkehren. | Auswahl bleibt nachvollziehbar; keine still abgeschnittene Gesamtliste. |
| [ ] | T18-10 | Mitgliedsdaten: 1. Datensatz öffnen. 2. Typ, Eintritt und zulässige Felder ändern. 3. Mitgliedsnummer prüfen/zuweisen. | Eindeutige Nummern und gültige Daten; Fremdverein geschützt. |
| [ ] | T18-11 | Anträge bearbeiten: 1. Antrag öffnen. 2. Rückfrage, Warteliste, Annahme und Ablehnung jeweils testen. 3. Sportlerseite vergleichen. | Nachvollziehbarer Lebenszyklus und richtige Benachrichtigung. |
| [ ] | T18-12 | Sammelaktion: 1. Mehrere Mitglieder wählen. 2. Erlaubte Aktion/Vorschau prüfen. 3. Bestätigen und Einzelergebnisse kontrollieren. | Nur gewählte zulässige Personen betroffen; Teilfehler sichtbar. |
| [ ] | T18-13 | Interessenten: 1. Interessent erfassen. 2. Bearbeiten/weiterverfolgen. 3. Archivieren. | Getrennt von aktiven Mitgliedern; korrekter Status und Vereinsbezug. |
| [ ] | T18-14 | Entfernen/Austritt: 1. Mitglied entfernen oder Antrag bearbeiten. 2. Wirksamkeitsdatum beachten. 3. Teams, Dokumente, Rechnungen und Historie prüfen. | Kein ungewollter Verlust anderer Vereinsbeziehungen; Folgezustände korrekt. |
| [ ] | T18-15 | Beziehungen/Qualifikationen: 1. Familie/Bezugsperson oder Qualifikation erfassen. 2. Gültigkeit/primäre Beziehung ändern. 3. Auf berechtigten Ansichten prüfen. | Beziehung personengenau; eingeschränkte Daten bleiben geschützt. |
| [ ] | T18-16 | Mitgliedshistorie: 1. Timeline öffnen. 2. Erlaubten Eintrag anlegen/entfernen. 3. Änderungen und Absender kontrollieren. | Historie nachvollziehbar; fremde Vereinsnotizen nicht sichtbar. |

## 19. Verein: Mitgliedschaftstypen und Beitragsregeln

Voraussetzung: Finanz-/Beitragsrecht, bekannte Beispielwerte und Zeiträume. Einstieg: Mitglieder & Beiträge → Typen/Beitragsregeln/Einstellungen.

| Offen | ID | Szenario und Schritte | Erwartetes Ergebnis |
| --- | --- | --- | --- |
| [ ] | T19-01 | Mitgliedschaftstyp: 1. Typ anlegen. 2. Betrag/Intervall und angebotene Bedingungen setzen. 3. Mitglied zuordnen. | Beitrag korrekt; bestehende Zuordnungen nicht versehentlich verändert. |
| [ ] | T19-02 | Regel bearbeiten: 1. Beitragsregel öffnen. 2. Gültigkeit/Betrag ändern. 3. Vorschau oder nächste Rechnung prüfen. | Änderung wirkt zum richtigen Zeitpunkt; alte Rechnungen bleiben nachvollziehbar. |
| [ ] | T19-03 | Anteilig: 1. Eintritt/Austritt mitten im Zeitraum vorbereiten. 2. Beitrag berechnen. 3. Mit erwarteter Teilperiode vergleichen. | Richtige Rundung und zeitanteilige Berechnung. |
| [ ] | T19-04 | Komponenten/Rabatte: 1. Verfügbare Zuschläge/Ermäßigungen zuordnen. 2. Rechnung erzeugen. 3. Einzelkomponenten prüfen. | Keine Doppelberechnung; Betrag und Kontierung nachvollziehbar. |
| [ ] | T19-05 | Wechsel/Pause: 1. Typwechsel/Pause beantragen. 2. Genehmigen. 3. Vor/nach Stichtag berechnen. | Neue Regel nicht zu früh oder doppelt angewandt. |
| [ ] | T19-06 | Wiederkehrende Beiträge: 1. Fällige Periode vorbereiten. 2. Automatischen Rechnungslauf ausführen lassen. 3. Wiederholen. | Pro vorgesehener Forderung nur eine Rechnung. |
| [ ] | T19-07 | Mahnregel: 1. Regel mit Fristen speichern. 2. Überfällige/offene/bezahlte Rechnung prüfen. 3. Lauf wiederholen. | Nur berechtigte fällige Mahnungen, keine doppelten Gebühren. |
| [ ] | T19-08 | Aufnahmeformular: 1. Formular/Pflichtnachweise konfigurieren. 2. Antrag als Mitglied öffnen. 3. Fehlenden Nachweis testen. | Konfiguration tatsächlich wirksam; alte Anträge nachvollziehbar. |

## 20. Verein und Sportler: Rechnungen und Zahlungen

Voraussetzung: A-Finanzverantwortlicher, Rechnungsempfänger und außenstehender Sportler; ähnliche Rechnungsbezeichnungen. Einstieg Verein: Mitglieder & Beiträge → Rechnungen/Zahlungen. Einstieg Mitglied: Einstellungen → Abrechnung oder Rechnungsbenachrichtigung.

| Offen | ID | Szenario und Schritte | Erwartetes Ergebnis |
| --- | --- | --- | --- |
| [ ] | T20-01 | Unbezahlte Rechnung: 1. Erstellen öffnen. 2. Mitglied, Betrag, Zweck und Frist wählen. 3. Als offen speichern. | Genau eine offene Rechnung mit eindeutiger Nummer und richtigem Empfänger. |
| [ ] | T20-02 | Bereits bezahlt: 1. Rechnung erstellen. 2. Bezahlt auswählen sowie Methode/Datum/angebotene Referenz erfassen. 3. Speichern. | Rechnung und Zahlungsbuchung stimmen; kein zusätzlich offener Betrag. |
| [ ] | T20-03 | Erstellungsdialog: 1. Auf kleinem Handy öffnen. 2. Alle Felder mit Tastatur bedienen. 3. Validieren/speichern. | Lesbare Abschnitte; Fehler und Speichern erreichbar; kein Überlappen. |
| [ ] | T20-04 | Rechnungsauswahl: 1. Zahlung erfassen öffnen. 2. Zwei ähnlich benannte Rechnungen suchen. 3. Empfänger, Nummer, Betrag und Restbetrag vergleichen. | Rechnungen auch mobil eindeutig unterscheidbar, nicht nur abgeschnittene Titel. |
| [ ] | T20-05 | Vollzahlung: 1. Offene Rechnung wählen. 2. Restbetrag und Zahlungsmethode erfassen. 3. Speichern und Abrechnung prüfen. | Rest null und bezahlter Status; Zahlungsdaten dauerhaft. |
| [ ] | T20-06 | Teilzahlung: 1. Einen Teilbetrag buchen. 2. Restbetrag prüfen. 3. Rest bezahlen. | Teilbezahlt/offen korrekt; Summe der Zahlungen und Rest stimmen. |
| [ ] | T20-07 | Ungültige Zahlung: 1. Null, negativ, zu hoch und ungültiges Datum getrennt eingeben. 2. Absenden. 3. Buchungen kontrollieren. | Validierung oder ausdrücklich unterstützter Überzahlungsprozess; keine still falsche Buchung. |
| [ ] | T20-08 | Mehrfachspeichern: 1. Zahlung absenden. 2. Doppelt tippen/Request wiederholen. 3. Ledger/Saldo prüfen. | Keine unbeabsichtigte doppelte Zahlung. |
| [ ] | T20-09 | Zahlung korrigieren: 1. Berechtigt Zahlung öffnen. 2. Unterstützte Korrektur ausführen. 3. Status/Audit prüfen. | Rechnung, Restbetrag und nachvollziehbare Korrektur konsistent. |
| [ ] | T20-10 | Erinnerung: 1. Überfällige Rechnung wählen. 2. Erinnerung senden. 3. Mitglied öffnet Nachricht. | Richtige offene Rechnung; kein 403 und kein falscher Tab. |
| [ ] | T20-11 | PDF/Download: 1. Eigene Rechnung öffnen. 2. Herunterladen. 3. Empfänger, Betrag, Zeitraum und Zahlungsangaben vergleichen. | Korrektes Dokument; kein fremder Download mit geänderter ID. |
| [ ] | T20-12 | Statusänderung: 1. Erlaubte Statusaktion wählen. 2. Abbrechen, dann bestätigen. 3. Zahlungssumme/Status vergleichen. | Kein widersprüchlicher Zahlungsstand; unzulässige Übergänge abgewiesen. |
| [ ] | T20-13 | Unberechtigter Zugriff: 1. Rechnung A mit fremdem Mitglied öffnen. 2. ID/Vereins-ID ändern. 3. Download/Zahlungsaktion testen. | Kein Datenzugriff und keine Zahlungserfassung außerhalb der Rechte. |
| [ ] | T20-14 | Austritt und Rechnung: 1. Offene eigene Rechnung anlegen. 2. Mitgliedschaft beenden. 3. Berechtigten Rechnungsweg prüfen. | Vorgesehener eigener Abrechnungszugriff bleibt konsistent; keine Verwaltungsrechte nötig. |

## 21. Verein: Buchhaltung, Bank, SEPA und Exporte

Voraussetzung: Finanzrecht, kontrollierte Testbuchungen und Testbankdateien. Einstieg: Mitglieder & Beiträge/Finanzen → jeweilige Fachfunktion. Nicht jede Fachfunktion hat bereits auf beiden Plattformen einen belegten Bedienweg; fehlenden Einstieg protokollieren.

| Offen | ID | Szenario und Schritte | Erwartetes Ergebnis |
| --- | --- | --- | --- |
| [ ] | T21-01 | Kassenbuch: 1. Einnahme/Ausgabe erfassen. 2. Konto, Datum, Kategorie und Beleg zuordnen. 3. Saldo prüfen. | Nachvollziehbare korrekte Buchung, keine fremde Kontierung. |
| [ ] | T21-02 | Beleg: 1. Beleg hochladen. 2. Erfasste Daten prüfen/korrigieren. 3. Bestätigen und Buchung öffnen. | Beleg und Buchung korrekt verknüpft, kein doppelter Import. |
| [ ] | T21-03 | Bankvorschau: 1. Bankdatei wählen. 2. Spalten/Zahlungen zuordnen. 3. Vorschau ohne Bestätigung verlassen. | Keine Buchung durch Vorschau; unklare Treffer nicht automatisch bestätigt. |
| [ ] | T21-04 | Bankabgleich: 1. Vorschau prüfen. 2. Richtige Rechnung bestätigen. 3. Gleiche Datei erneut einlesen. | Richtige Zuordnung; keine doppelten Zahlungen. |
| [ ] | T21-05 | Spenden/Vorauszahlungen: 1. Jeweils vorgesehenen Vorgang erfassen. 2. Mitglied/Konto zuordnen. 3. Auswertung prüfen. | Spende, Vorschuss und Beitrag nicht unbemerkt vermischt. |
| [ ] | T21-06 | Budget: 1. Budget anlegen. 2. Unterbudget/Genehmigung soweit angeboten bearbeiten. 3. Buchungen zuordnen. | Summen und Genehmigungen konsistent; Budgetgrenzen nachvollziehbar. |
| [ ] | T21-07 | Fördermittel: 1. Förderprogramm anlegen. 2. Erlaubte Statusfolge durchlaufen. 3. Ungültigen Rücksprung versuchen. | Nur zulässige Übergänge und korrekte Beträge/Zuordnung. |
| [ ] | T21-08 | SEPA-Stammdaten: 1. Einstellungen/Mandate öffnen. 2. Gültige Testdaten speichern. 3. Fehlende/ungültige Daten prüfen. | Kein ungültiger Einzug; Mandat und Zahlungspflichtiger eindeutig. |
| [ ] | T21-09 | SEPA-Lauf: 1. Fällige Forderungen auswählen. 2. Lauf erstellen/genehmigen. 3. Export prüfen. | Nur berechtigte gültige Forderungen; korrekte Beträge und Einmaligkeit. |
| [ ] | T21-10 | Vorankündigung: 1. Hinweise vorbereiten. 2. Senden. 3. Zustellung/Fehler und Wiederholung prüfen. | Richtige Empfänger/Fristen; keine unbeabsichtigten doppelten Hinweise. |
| [ ] | T21-11 | Abrechnung/Rücklastschrift: 1. Laufposition ausgleichen beziehungsweise zurückgeben. 2. Rücklaufdatei vorprüfen/importieren. 3. Rechnung/Zahlung vergleichen. | Status, Restbetrag und Bankereignis konsistent. |
| [ ] | T21-12 | Wiederholung/Abbruch: 1. Rückgabe erneut versuchen beziehungsweise Lauf stornieren. 2. Bestätigen. 3. Erneuten Export prüfen. | Kein doppelter Einzug; Abbruchzustand eindeutig. |
| [ ] | T21-13 | Bankgebühr: 1. Rückgabegebühr erfassen. 2. Gebührenkorrektur durchführen. 3. Buchungshistorie prüfen. | Gebühren und Korrekturen getrennt nachvollziehbar. |
| [ ] | T21-14 | Gebührenweitergabe: 1. Weitergabe vorschlagen. 2. Genehmigen oder abbrechen. 3. Forderung prüfen. | Freigabeprozess wirksam; keine doppelte Gebührenforderung. |
| [ ] | T21-15 | Gebührenstorno/Gutschrift: 1. Storno-/Gutschriftsantrag stellen. 2. Zurückziehen oder genehmigen. 3. Dokument/Erstattung prüfen. | Korrekte Gegenbuchung und geschütztes Dokument; keine doppelte Erstattung. |
| [ ] | T21-16 | DATEV/SEPA-Export: 1. Einstellungen und Zeitraum wählen. 2. Export auslösen. 3. Format, Konten, Beträge und Zeichensatz prüfen. | Vollständiger fachlich konsistenter Export nur für berechtigten Verein. |
| [ ] | T21-17 | Jahresabschluss/Bericht: 1. Jahresperiode auswählen. 2. Bericht erstellen. 3. Mit Buchungen und Vorperiode abstimmen. | Zeiträume/Summen stimmen; abgeschlossene Perioden wie vorgesehen geschützt. |

## 22. Verein: Organisation, Rollen und Nachweise

Voraussetzung: Besitzer und Personen mit gezielt unterschiedlichen Fachrechten. Einstieg: Vereinsverwaltung → Organisation/Zugriffe/Metadaten/Dokumente.

| Offen | ID | Szenario und Schritte | Erwartetes Ergebnis |
| --- | --- | --- | --- |
| [ ] | T22-01 | Struktur: 1. Abteilung/Standort/Trainingsgruppe anlegen. 2. Bearbeiten. 3. Mit abhängigen Teams löschen versuchen. | Konsistente Zugehörigkeiten; Abhängigkeiten verständlich behandelt. |
| [ ] | T22-02 | Rollen: 1. Vereinsrolle definieren. 2. Rechte/Person zuweisen. 3. Als Person Aktionen testen. | Nur definierte Rechte innerhalb des Vereins. |
| [ ] | T22-03 | Rechte entziehen: 1. Einzelrecht/Rolle entfernen. 2. Person aktualisiert offene Seite. 3. API-Aktion wiederholen. | Entzug serverseitig wirksam, auch bei gecachter Ansicht. |
| [ ] | T22-04 | Delegation: 1. Befristete Delegation erstellen. 2. Während/nach Frist testen. 3. Vorzeitig widerrufen. | Rechte nur im genehmigten Zeitraum/Umfang. |
| [ ] | T22-05 | Übergabe/Zugriffsprüfung: 1. Übergabeprüfung öffnen. 2. Vorschlag einreichen. 3. Berechtigte Genehmigung ausführen. | Verantwortlichkeiten nachvollziehbar; keine Selbsteskalation. |
| [ ] | T22-06 | Gremien: 1. Gremium anlegen. 2. Amt/Person/Laufzeit zuordnen. 3. Zuordnung ändern/entfernen. | Amtszeit und Rechte nicht mit unbegrenzter Plattformberechtigung vermischt. |
| [ ] | T22-07 | Metadaten: 1. Kategorie/Zusatzfeld anlegen. 2. An geeignetem Datensatz befüllen. 3. Typ ändern/löschen versuchen. | Validierung und vorhandene Werte konsistent. |
| [ ] | T22-08 | Nummernkreise: 1. Bereich/Standard wählen. 2. Nummern mehrfach und parallel vergeben. 3. Beleg-/Mitgliedsnummern vergleichen. | Eindeutige Nummern; kein falscher Nummernkreis oder Reset. |
| [ ] | T22-09 | Stammdatenantrag: 1. Änderungsantrag stellen. 2. Genehmigen/ablehnen getrennt testen. 3. Ursprungsdaten vergleichen. | Änderung erst nach vorgesehener Freigabe aktiv. |
| [ ] | T22-10 | Richtliniendokument: 1. Dokument anlegen/hochladen. 2. Version/Gültigkeit bearbeiten. 3. Als berechtigtes Mitglied herunterladen. | Richtige aktuelle Version; fremde/gesperrte Dokumente geschützt. |
| [ ] | T22-11 | Vereinsjahre: 1. Periode erstellen. 2. Daten zuordnen und Zeitraum ändern. 3. Überlappung/Löschen prüfen. | Gültige Perioden und konsistente Berichte. |
| [ ] | T22-12 | Audit: 1. Rechte-/Stammdatenänderung durchführen. 2. Historie öffnen. 3. Akteur, Zeit, Objekt und Änderung prüfen. | Aussagekräftiges vereinsbezogenes Protokoll ohne Geheimnisse. |

## 23. Verein und Trainer: Teams und Gruppen

Voraussetzung: A/B mit je zwei Teams. Einstieg: Teams → Teamdetails; Web `/teams`.

| Offen | ID | Szenario und Schritte | Erwartetes Ergebnis |
| --- | --- | --- | --- |
| [ ] | T23-01 | Team anlegen: 1. Neues Team öffnen. 2. Verein, Name und Sportart wählen. 3. Speichern. | Nur erlaubter Verein auswählbar; Team korrekt angelegt. |
| [ ] | T23-02 | Teamprofil: 1. Team bearbeiten. 2. Bild/Angaben ändern. 3. Öffentliche und interne Sicht vergleichen. | Nur erlaubte Felder/Informationen sichtbar. |
| [ ] | T23-03 | Mitglieder zuordnen: 1. Mitglieder hinzufügen öffnen. 2. Zulässige Person auswählen. 3. Rolle setzen und speichern. | Eindeutige Zugehörigkeit; keine fremden Mitglieder durch ID-Manipulation. |
| [ ] | T23-04 | Einladen/Beitritt: 1. Einladung oder Beitrittsanfrage erzeugen. 2. Annehmen/ablehnen. 3. Mitgliederliste prüfen. | Zustimmung und Rollen korrekt; keine Dublette. |
| [ ] | T23-05 | Teamrolle ändern: 1. Mitglied auswählen. 2. Trainer/andere erlaubte Rolle setzen. 3. Rechte des Kontos prüfen. | Rechte ausschließlich im erlaubten Teamkontext. |
| [ ] | T23-06 | Transfer: 1. Wechsel zwischen Teams beantragen. 2. Genehmigen oder ablehnen. 3. Zuordnung/Historie prüfen. | Richtige Wirksamkeit; keine fremde Vereinsübertragung. |
| [ ] | T23-07 | Entfernen: 1. Mitglied aus Team entfernen. 2. Chat/Dateien/Training öffnen lassen. 3. Vereinsmitgliedschaft prüfen. | Teamrechte entzogen; Verein bleibt entsprechend gewählter Aktion erhalten. |
| [ ] | T23-08 | Teamalltag: 1. Anwesenheit/Statistik/angebotene Auswertung öffnen. 2. Zeitraum wechseln. 3. Mit Einzelereignissen vergleichen. | Richtige Zahlen und keine fremden Sportlerdaten. |
| [ ] | T23-09 | Teamkasse/Strafen: 1. Verfügbare Regel anlegen. 2. Gebühr erfassen. 3. Bezahlen/stornieren. | Nur Berechtigte; eindeutige Forderung und konsistenter Status. |
| [ ] | T23-10 | Team löschen: 1. Testteam mit Abhängigkeiten wählen. 2. Löschen abbrechen, dann bestätigen. 3. Verbundene Daten prüfen. | Definierte Behandlung abhängiger Daten; keine fremden Teams betroffen. |
| [ ] | T23-11 | Navigation: 1. Teamseite in App öffnen. 2. Alle verfügbaren Zusatzaktionen prüfen. 3. Zurückgehen. | Kein sinnloser „Weitere Teamfunktionen“-Rundlauf auf dieselbe Seite. |

## 24. Verein und zugewiesene Mitglieder: gemeinsame To-dos

Voraussetzung: Manager, zugewiesenes Mitglied, weiteres Teammitglied und Außenstehender. Einstieg: Seitenleiste → To-dos oder Vereins-Cockpit; Web `/club-cockpit?panel=tasks`.

| Offen | ID | Szenario und Schritte | Erwartetes Ergebnis |
| --- | --- | --- | --- |
| [ ] | T24-01 | Aufgabe erstellen: 1. Hinzufügen öffnen. 2. Titel/Beschreibung eingeben. 3. Speichern und Liste aktualisieren. | Genau eine neue Aufgabe im richtigen Verein. |
| [ ] | T24-02 | Formular-Tabs: 1. Grunddaten, Planung und Checkliste durchgehen. 2. Zwischen Tabs wechseln. 3. Mit Tastatur/Fehlern speichern. | Eingaben bleiben; Pflichtfehler auffindbar; Dialog auf kleinem Display bedienbar. |
| [ ] | T24-03 | Zuweisen: 1. Verantwortlichen und Team auswählen. 2. Speichern. 3. Als Empfänger öffnen. | Nur zulässige Personen/Teams; richtige Zuständigkeit. |
| [ ] | T24-04 | Sichtbarkeit: 1. Jede angebotene Sichtbarkeit einzeln wählen. 2. Mit Verantwortlichem, Teamkollegen und Außenstehendem prüfen. 3. Direktlink versuchen. | Leserechte genau entsprechend Auswahl, auch bei Kommentaren/Anhängen. |
| [ ] | T24-05 | Priorität/Frist: 1. Priorität und Fälligkeit setzen. 2. Ändern beziehungsweise entfernen. 3. Liste und Kalender vergleichen. | Korrekte Priorität und einheitliche Frist; keine veralteten Kalendereinträge. |
| [ ] | T24-06 | Checkliste: 1. Punkte hinzufügen. 2. Einzelne abhaken. 3. Neu laden und korrigieren. | Zustand und Fortschritt gespeichert, keine verlorenen Punkte. |
| [ ] | T24-07 | Status: 1. Offen → in Bearbeitung → erledigt ändern, soweit angeboten. 2. Wieder öffnen. 3. Filter/Zähler prüfen. | Status, Abschluss und Wiederöffnung konsistent. |
| [ ] | T24-08 | Kommentar: 1. Berechtigt kommentieren. 2. Als andere berechtigte Person lesen. 3. Fremden Zugriff testen. | Richtige Person/Zeit; nur zulässiger Kreis. |
| [ ] | T24-09 | Anhang: 1. Datei anhängen. 2. Empfänger lädt sie. 3. Berechtigt entfernen und alten Link testen. | Korrekte Datei, gleiche Zugriffsgrenzen, Entfernung wirksam. |
| [ ] | T24-10 | Zusammenarbeit: 1. Aufgabe auf zwei Geräten öffnen. 2. Unterschiedliche Felder nahezu gleichzeitig ändern. 3. Neu laden. | Keine unbemerkte widersprüchliche Speicherung; Konfliktverhalten dokumentiert. |
| [ ] | T24-11 | Löschen/Rechteverlust: 1. Löschung abbrechen. 2. Bestätigt löschen. 3. Mit früherem Ersteller nach Austritt Aktion versuchen. | Abbruch unverändert; gelöschte Aufgabe weg; ehemalige Rechte reichen nicht. |
| [ ] | T24-12 | Systemaufgaben und eigene Aufgaben: 1. Offene Anträge/Beiträge erzeugen. 2. Eigene Aufgabe anlegen. 3. Beide Bereiche und jeweilige Erledigungsaktion prüfen. | Fachliche offene Vorgänge nicht durch Abhaken einer beliebigen Aufgabe verfälscht. |

## 25. Verein, Trainer und Sportler: Kalender, Events und Anwesenheit

Voraussetzung: Termin A, Teamtermin A, öffentlicher Termin, privater Termin B und Aufgabe mit Frist. Einstieg: Kalender beziehungsweise Events; Web `/club-cockpit?panel=calendar` und `/events`.

**Bekannter Unterschied:** Web-Code bietet Tag/Woche/Jahr, Flutter-Code Tag/Woche/Monat. Monatsansicht Web und Jahresansicht App sind als offene Paritätsfälle zu prüfen, nicht als bereits vorhanden anzunehmen.

| Offen | ID | Szenario und Schritte | Erwartetes Ergebnis |
| --- | --- | --- | --- |
| [ ] | T25-01 | Kalenderziel: 1. Seitenleiste → Kalender öffnen. 2. Überschrift/Ansicht prüfen. 3. Zurück und über Schnellaktion öffnen. | Kalenderansicht statt bloßer Events-/Trainingsliste; gleicher aktiver Verein. |
| [ ] | T25-02 | Ansichten: 1. Tag/Woche/Monat/Jahr einzeln suchen. 2. Jede verfügbare Ansicht wählen. 3. Fehlende Ansicht je Plattform protokollieren. | Vorhandene Ansichten korrekt; gewünschte fehlende Ansichten als Funktionslücke erfasst. |
| [ ] | T25-03 | Blättern: 1. Vor/zurück und Heute benutzen. 2. Monats-/Jahreswechsel prüfen. 3. 28./29./30./31. und Schaltjahr testen. | Richtiger Zeitraum; kein übersprungener Monat durch ungültigen Tag. |
| [ ] | T25-04 | Termine und Fristen: 1. Event und Aufgabenfrist am gleichen Tag anlegen. 2. Kalender öffnen. 3. Beide Einträge antippen. | Beide eindeutig unterscheidbar und mit korrektem Detailziel. |
| [ ] | T25-05 | Friständerung: 1. Aufgabe verschieben/erledigen/löschen. 2. Kalender aktualisieren. 3. Web/App vergleichen. | Keine veraltete Frist; Erledigtfilter entsprechend Regel. |
| [ ] | T25-06 | Event erstellen: 1. Neu öffnen. 2. Titel, Verein/Team, Ort, Beginn/Ende und Sichtbarkeit setzen. 3. Speichern. | Gültiger Termin im richtigen Kontext; Ende vor Beginn abgewiesen. |
| [ ] | T25-07 | Event ändern/absagen: 1. Zeit/Ort ändern. 2. Als Teilnehmer prüfen. 3. Absagen und Benachrichtigung öffnen. | Änderung/Absage überall konsistent; keine falsche aktive Teilnahme. |
| [ ] | T25-08 | Teilnahme: 1. Zusagen/absagen. 2. Erlaubten Rückzug/Ersatz testen. 3. Veranstalterliste vergleichen. | Aktueller Status, Kapazität und Ersatzperson korrekt. |
| [ ] | T25-09 | Kapazität: 1. Letzten Platz mit zwei Konten gleichzeitig anfragen. 2. Ergebnis prüfen. 3. Stornieren und neu buchen. | Kein unbeabsichtigtes Überbuchen; frei werdender Platz korrekt. |
| [ ] | T25-10 | Check-in: 1. Berechtigten Code erzeugen/scannen. 2. Erneut und mit falschem Event testen. 3. Anwesenheit prüfen. | Genau ein passender Check-in; fremde/ungültige Codes abgewiesen. |
| [ ] | T25-11 | Anwesenheit korrigieren: 1. Trainer erfasst Teilnahme. 2. Korrigiert mit Grund soweit gefordert. 3. Historie öffnen. | Änderung berechtigt und nachvollziehbar. |
| [ ] | T25-12 | Diskussion/Entscheidung: 1. Kommentar oder angebotene Abstimmung anlegen. 2. Teilnehmer antworten/abstimmen lassen. 3. Entscheidung schließen. | Teilnehmerkreis und Abstimmungsgrenzen eingehalten. |
| [ ] | T25-13 | Zeitzone/Dauer: 1. Mehrtägigen Termin und Sommerzeitgrenze anlegen. 2. In anderer Zeitzone öffnen. 3. Tageszuordnung prüfen. | Zeiten und Dauer fachlich konsistent; ganztägige Werte nicht verschoben. |
| [ ] | T25-14 | Isolation: 1. A-Kalender öffnen. 2. Nach B-Einträgen suchen. 3. Event-/Aufgaben-ID direkt ändern. | Keine privaten fremden Termine oder Aufgaben, auch nicht in Metadaten. |
| [ ] | T25-15 | Serien/Externe Kalender: 1. Angebotene Wiederholung oder Kalenderexport suchen. 2. Falls vorhanden Folge-/Einzeländerung und Zugriff testen. 3. Andernfalls Funktionsstatus erfassen. | Keine erfundene Serien-/Synchronisationsfunktion; bestehende Exporte respektieren Rechte/Widerruf. |

## 26. Verein: Mitteilungen, Newsletter und Umfragen

Voraussetzung: Kommunikationsrecht, Empfänger unterschiedlicher Teams und Newsletterzustimmungen. Einstieg: Vereinskommunikation/Mitteilungen/Umfragen.

| Offen | ID | Szenario und Schritte | Erwartetes Ergebnis |
| --- | --- | --- | --- |
| [ ] | T26-01 | Mitteilung: 1. Entwurf erstellen. 2. Zielgruppe wählen. 3. Veröffentlichen. | Genau der gewählte zulässige Empfängerkreis erhält die Mitteilung. |
| [ ] | T26-02 | Planen/Zurückziehen: 1. Mitteilung für später planen. 2. Vor Termin ändern oder zurückziehen. 3. Zustellzeit prüfen. | Keine vorzeitige oder widerrufene Veröffentlichung. |
| [ ] | T26-03 | Lesebestätigung: 1. Mitglied öffnet Mitteilung. 2. Gelesen bestätigen, soweit angeboten. 3. Übersicht prüfen. | Person/Status korrekt; keine personenbezogene Auswertung für Unberechtigte. |
| [ ] | T26-04 | Newsletteranmeldung: 1. Anmeldung und gegebenenfalls Bestätigung durchführen. 2. Nachricht senden. 3. Abmelden und erneut senden. | Zustellregeln/Abmeldung wirksam; kein Versand an abgemeldete Empfänger. |
| [ ] | T26-05 | Zustellfehler/Sperrliste: 1. Bounce vorbereiten. 2. Sperre setzen. 3. Nächsten Versand prüfen. | Fehler sichtbar; gesperrte Adresse nicht unkontrolliert erneut angeschrieben. |
| [ ] | T26-06 | Umfrage: 1. Frage/Optionen/Sichtbarkeit anlegen. 2. Abstimmen lassen. 3. Ergebnis prüfen. | Stimmenzahl und Teilnehmerkreis stimmen. |
| [ ] | T26-07 | Umfrage schließen: 1. Bearbeiten/schließen. 2. Weitere Stimme versuchen. 3. Löschen und Altlink prüfen. | Geschlossene Umfrage akzeptiert keine unzulässigen Stimmen. |
| [ ] | T26-08 | KI-Entwurf: 1. Angebotene Formulierungshilfe aufrufen. 2. Ergebnis bearbeiten. 3. Erst nach bewusster Bestätigung versenden. | Keine automatische Nachricht an Mitglieder durch bloßes Generieren. |

## 27. Verein: Inventar, Beschaffung, Ehrenamt und Sitzungen

Voraussetzung: jeweilige Fachberechtigung. Einstieg: zugehöriger Bereich in Vereinsverwaltung. Bei nur vorhandener API oder Beispieloberfläche den Bedienweg als offen erfassen.

| Offen | ID | Szenario und Schritte | Erwartetes Ergebnis |
| --- | --- | --- | --- |
| [ ] | T27-01 | Inventar: 1. Artikel anlegen. 2. Bestand/Standort bearbeiten. 3. Bestand und Historie prüfen. | Plausibler Bestand; nur zuständiger Verein betroffen. |
| [ ] | T27-02 | Ausleihe: 1. Gegenstand ausleihen/anfragen. 2. Genehmigen oder ablehnen. 3. Zurückgeben. | Verfügbarkeit und ausleihende Person konsistent. |
| [ ] | T27-03 | QR: 1. Artikelcode scannen. 2. Code neu ausstellen/widerrufen. 3. Alten Code testen. | Richtiger Artikel; alter Code nicht weiterhin gültig. |
| [ ] | T27-04 | Bewegung/Schaden: 1. Bestandsbewegung buchen. 2. Schaden melden. 3. Bestand/Zustand vergleichen. | Kein negativer unzulässiger Bestand; Meldung nachvollziehbar. |
| [ ] | T27-05 | Wartung: 1. Wartung anlegen. 2. Status ändern. 3. Verfügbarkeit im Wartungszeitraum prüfen. | Gesperrter Gegenstand nicht unbemerkt ausleihbar. |
| [ ] | T27-06 | Ressourcenbuchung: 1. Plätze/Hallen/Räume suchen. 2. Testslot buchen, sofern echter Ablauf vorhanden. 3. Neustart und Konfliktbuchung prüfen. | Persistente konfliktgeprüfte Buchung erforderlich; Demo allein zählt nicht. |
| [ ] | T27-07 | Beschaffung: 1. Antrag anlegen. 2. Genehmigen/ablehnen. 3. Bestellung und Wareneingang erfassen. | Status, Kosten und Eingänge nachvollziehbar; keine unberechtigte Freigabe. |
| [ ] | T27-08 | Ehrenamtsprofil: 1. Verfügbarkeit/Fähigkeiten erfassen. 2. Bearbeiten. 3. Berechtigten Planerzugriff prüfen. | Nur freigegebene personenbezogene Angaben verfügbar. |
| [ ] | T27-09 | Schichten: 1. Einsatz anlegen. 2. Anmelden/Warteliste testen. 3. Freigeben/Tauschen/Vertretung einzeln prüfen. | Kapazität, Zuständigkeit und Überschneidungen korrekt. |
| [ ] | T27-10 | Dienststunden: 1. Verpflichtung und Stunden erfassen. 2. Bestätigen/korrigieren. 3. Befreiung und gesperrte Periode prüfen. | Saldo nachvollziehbar; bestätigte Stunden nicht still überschrieben. |
| [ ] | T27-11 | Sitzung: 1. Gremium/Sitzung anlegen. 2. Empfänger und Entscheidungen erfassen. 3. Berechtigte Zustellung prüfen. | Nur vorgesehene Teilnehmer; keine fremden Sitzungsdaten. |
| [ ] | T27-12 | Beschluss: 1. Abstimmung öffnen. 2. Berechtigt abstimmen. 3. Schließen und Ergebnis/Protokoll prüfen. | Stimmberechtigung und Endzustand korrekt. |
| [ ] | T27-13 | Arbeitsautomation: 1. Unterstützten Auftrag anlegen. 2. Ausführung/Fehler beobachten. 3. Wiederholung auslösen. | Auftrag nachvollziehbar und ohne doppelte fachliche Wirkung. |
| [ ] | T27-14 | Fachbereiche ohne belegten Menüweg: 1. Wettkampf-/Saisonplanung, Familienkalender und weitere Katalogbereiche im Inventar suchen. 2. Persistenten Nutzerablauf nachweisen. 3. Fehlenden Ablauf als Lücke erfassen. | Kein Readiness-Katalog oder Demonstrationsscreen als fertige Funktion gezählt. |

## 28. Verein: Löschung und Rücknahme

Voraussetzung: isolierter Testverein, Besitzer, Präsident/Vorstand und Nichtbesitzer; steuerbare Testzeit. Einstieg: Vereinsverwaltung → Verein löschen. Vorhandene Löschsperren sind Bestandteil des Tests.

| Offen | ID | Szenario und Schritte | Erwartetes Ergebnis |
| --- | --- | --- | --- |
| [ ] | T28-01 | Bestätigung: 1. Löschdialog öffnen. 2. Falschen/fehlenden Bestätigungstext senden. 3. Vorgeschriebenen Text korrekt eingeben. | Löschung nur nach genauer gültiger Bestätigung beantragt. |
| [ ] | T28-02 | Berechtigung: 1. Als Nichtbesitzer Antrag versuchen. 2. Als Besitzer wiederholen. 3. Rechte direkt gegenprüfen. | Nur vorgesehene Besitzer-/Adminberechtigung erlaubt den Vorgang. |
| [ ] | T28-03 | Bedenkzeit: 1. Löschung beantragen. 2. Datum/Status prüfen. 3. Vor Ablauf erneut öffnen. | Angekündigte 30-Tage-Frist und Rücknahmemöglichkeit sichtbar; keine vorzeitige Löschung. |
| [ ] | T28-04 | Information: 1. Antrag stellen. 2. Postfächer/Nachrichten von Besitzer und zuständigen Vorstandsrollen prüfen. 3. Hinweise öffnen. | Betroffene Verantwortliche informiert; Ziel und Frist korrekt. |
| [ ] | T28-05 | Zurücknehmen: 1. Während Frist abbrechen. 2. Testzeit über ursprünglichen Termin setzen. 3. Verein prüfen. | Verein/Daten bleiben; alte geplante Löschung unwirksam. |
| [ ] | T28-06 | Wiederholter Antrag: 1. Antrag doppelt senden. 2. Frist vergleichen. 3. Nach Rücknahme neu beantragen. | Wiederholung verschiebt bestehende Frist nicht unbemerkt; neuer gültiger Antrag nachvollziehbar. |
| [ ] | T28-07 | Löschsperre: 1. Abrechnungsdaten/aktive relevante Verpflichtung vorbereiten. 2. Beantragen beziehungsweise Fälligkeit erreichen. 3. Sperrgrund prüfen. | Keine unzulässige Löschung trotz Sperre; Grund verständlich. |
| [ ] | T28-08 | Endgültige Löschung: 1. Freigegebenen Testverein fällig stellen. 2. Löschlauf ausführen lassen. 3. Dateien, Beziehungen, alte Links und Verein B kontrollieren. | Zugehörige löschbare Daten entfernt; andere Vereine und persönliche Konten unberührt. |
| [ ] | T28-09 | Besitzerwechsel/Fehler: 1. Während Frist Besitzer wechseln. 2. Separat Ausführungsfehler simulieren. 3. Status/Wiederholung prüfen. | Alter Antrag nicht unberechtigt fortgeführt; fehlgeschlagene Löschung konsistent wiederholbar. |

## 29. Sponsor: Profil, Partnerschaften und Kampagnen

Voraussetzung: Sponsor A/B, Partnerverein und gegebenenfalls prüfender Admin. Einstieg: Sponsor-Cockpit, Kampagnen/Commerce; Web `/sponsor-cockpit`.

| Offen | ID | Szenario und Schritte | Erwartetes Ergebnis |
| --- | --- | --- | --- |
| [ ] | T29-01 | Markenprofil: 1. Anlegen/bearbeiten öffnen. 2. Name, Kontakt, Website, Register-/Pflichtangaben erfassen. 3. Speichern und Web/App vergleichen. | Eigenes Profil dauerhaft; fehlende native Pflichtfelder als Paritätslücke sichtbar. |
| [ ] | T29-02 | Logos: 1. Unterstützte helle/dunkle Logos setzen. 2. Profil und Werbung öffnen. 3. Fehlerhafte URL/Datei testen. | Passende Darstellung und verständliche Validierung. |
| [ ] | T29-03 | Prüfung: 1. Profil einreichen/aktualisieren. 2. Adminentscheidung vorbereiten. 3. Hinweis/Status als Sponsor lesen. | Keine Selbstverifikation; korrekter Status und Prüfhinweis. |
| [ ] | T29-04 | Partnerschaften: 1. Eigene Partnerschaft öffnen. 2. Verein, Laufzeit und Vereinbarungen prüfen. 3. Als Sponsor B öffnen versuchen. | Nur eigene zulässige Partnerschaften; fremde Vertrags-/Budgetdaten geschützt. |
| [ ] | T29-05 | Anfrage/Kontakt: 1. Partnerprofil öffnen. 2. Tatsächlich angebotenen Kontakt-/Anfrageweg benutzen. 3. Empfänger prüfen. | Zustellung nachvollziehbar; fehlende spezielle Sponsorenanfrage als Lücke, nicht erfundener Button. |
| [ ] | T29-06 | Leistungen: 1. Berechtigt Sponsoring-Leistung/Deliverable erfassen. 2. Status/Nachweis ändern. 3. Partneransicht prüfen. | Vereinbarung, Verantwortlicher und Erfüllung nachvollziehbar. |
| [ ] | T29-07 | Kampagne erstellen: 1. Kampagne erstellen öffnen. 2. Motiv, Ziel-URL, Budget und Zeitraum erfassen. 3. Entwurf speichern. | Gültiger Entwurf im richtigen Konto; noch keine ungewollte Ausspielung. |
| [ ] | T29-08 | Kampagnenfreigabe: 1. Prüfung/Freigabeprozess durchlaufen. 2. Aktivierung, Pause und Ende testen. 3. Ausspielung vergleichen. | Zustände und Zeitgrenzen beachtet; abgelehnte/pausierte Werbung nicht aktiv. |
| [ ] | T29-09 | Kennzahlen: 1. Kontrollierte Impression/Klick erzeugen. 2. Dashboard aktualisieren. 3. CTR, Budget und Zeitraum vergleichen. | Plausible Zähler und Berechnung; kein Zugriff auf andere Sponsoren. |
| [ ] | T29-10 | Conversion/Einwilligung: 1. Unterstütztes Testereignis erzeugen. 2. Erneut senden. 3. Mit widerrufener Messzustimmung vergleichen. | Definierte Deduplizierung und Einwilligungsregeln eingehalten. |
| [ ] | T29-11 | Agenturbriefing/Assets: 1. Verfügbaren Auftrag oder Asset öffnen/erfassen. 2. Status verfolgen. 3. Datei/Kontaktberechtigung prüfen. | Nur eigene Aufträge und Dateien; nicht vorhandene Bearbeitung klar dokumentiert. |
| [ ] | T29-12 | Budgetgrenze/Ziel-URL: 1. Budget ausschöpfen oder ungültige Ziel-URL angeben. 2. Aktivierung/Klick testen. 3. Status prüfen. | Grenzen und erlaubte URLs kontrolliert; keine unbemerkte Budgetüberschreitung. |

## 30. Alle Rollen: Marketplace, Käufer und Anbieter

Voraussetzung: freigegebenes Testprodukt, Käufer und Anbieter; Zahlungsanbieter im Testbetrieb. Einstieg: Marketplace/Commerce und Warenkorb.

| Offen | ID | Szenario und Schritte | Erwartetes Ergebnis |
| --- | --- | --- | --- |
| [ ] | T30-01 | Katalog: 1. Produkte filtern/suchen. 2. Produkt- und Anbieterprofil öffnen. 3. Preis/Bestand/Varianten prüfen. | Öffentliche Angebote korrekt, keine Entwürfe oder fremden Verkäuferdaten. |
| [ ] | T30-02 | Merkliste/Bewertung: 1. Produkt merken/entfernen. 2. Als berechtigter Käufer bewerten. 3. Unberechtigte Bewertung versuchen. | Persönliche Liste; Bewertungsregeln eingehalten. |
| [ ] | T30-03 | Warenkorb: 1. Produkte hinzufügen. 2. Menge ändern/Artikel entfernen. 3. Neu laden. | Richtige Produkte, Mengen und Summen. |
| [ ] | T30-04 | Preisberechnung: 1. Gutschein, Versand und Steueroptionen wählen. 2. Gültige/ungültige Kombinationen testen. 3. Endbetrag prüfen. | Serverberechneter korrekter Gesamtpreis; keine unberechtigten Rabatte. |
| [ ] | T30-05 | Checkout: 1. Liefer-/Rechnungsangaben ausfüllen. 2. Vorgeschriebene Bestätigungen setzen. 3. Mit angebotener Methode im Testbetrieb bezahlen. | Genau eine Bestellung; passende Dokumente und Status. |
| [ ] | T30-06 | Zahlungsabbruch: 1. Provider abbrechen oder Fehler simulieren. 2. Zurück zur App/Web. 3. Erneut versuchen. | Keine fälschlich bezahlte Bestellung; sicherer Wiederholungsweg. |
| [ ] | T30-07 | Überweisung: 1. Überweisung wählen. 2. Referenz/Empfänger/Betrag prüfen. 3. Zahlung kontrolliert bestätigen lassen. | Vor Bestätigung offen; danach genau einmal bezahlt. |
| [ ] | T30-08 | Gastbestellung: 1. Ausgeloggt verfügbaren Gastcheckout starten. 2. Bestellen. 3. Statuslink mit gültigem/ungültigem Token öffnen. | Bestellung nur mit gültigem Zugang sichtbar; kein Konto vorausgesetzt, wenn Gastweg angeboten. |
| [ ] | T30-09 | Bestellverlauf: 1. Eigene Bestellung öffnen. 2. Rechnung/Gutschrift laden. 3. Fremde Bestell-ID versuchen. | Nur eigene Dokumente; korrekte Inhalte. |
| [ ] | T30-10 | Storno/Problem/Retoure: 1. Je einen zulässigen Vorgang starten. 2. Begründung erfassen. 3. Bearbeitungsstatus/Antwort prüfen. | Richtiger Ablauf, Fristen und Status; keine doppelte Erstattung. |
| [ ] | T30-11 | Anbieterbewerbung: 1. Anbieterantrag ausfüllen. 2. Prüfung verfolgen. 3. Nach Freigabe Verkäuferbereich öffnen. | Rechte erst entsprechend Freigabe. |
| [ ] | T30-12 | Anbieterprofil/Standorte: 1. Profil/Standort erfassen. 2. Bearbeiten. 3. Öffentliche Ansicht prüfen. | Eigene zutreffende Angaben, keine vertraulichen Auszahlungsdaten öffentlich. |
| [ ] | T30-13 | Angebot pflegen: 1. Produkt einreichen. 2. Bearbeiten/Status ändern. 3. Moderation und Veröffentlichung prüfen. | Nur freigegebene Produkte sichtbar; Ablehnungsgrund nachvollziehbar. |
| [ ] | T30-14 | Bestand: 1. Restbestand vorbereiten. 2. Zwei parallele Käufe versuchen. 3. Bestand/Bestellungen prüfen. | Kein unkontrollierter Überverkauf oder negativer Bestand. |
| [ ] | T30-15 | Auszahlung: 1. Eigene Auszahlungsdaten hinterlegen. 2. Auszahlung anfordern. 3. Freigabe/bezahlt prüfen. | Nur berechtigtes Guthaben; richtige Empfänger und keine Doppelauszahlung. |
| [ ] | T30-16 | Teamsammelbestellung: 1. Berechtigt Sammelauftrag erstellen. 2. Mitgliederpositionen erfassen. 3. Lieferantenexport vergleichen. | Richtige Team-/Personenzuordnung, Größen/Mengen und geschützter Export. |
| [ ] | T30-17 | Website-Service: 1. Angebotene Anfrage ausfüllen. 2. Absenden. 3. Eingangsbestätigung/Status prüfen. | Genau ein zugeordneter Auftrag; keine ungewollte kostenpflichtige Aktivierung. |

## 31. Alle Rollen: Abos, Tarife und Outfit

Voraussetzung: Konto/Verein mit passendem Tarif und Testanbieter. Einstieg: Preise/Abos, Einstellungen → Abrechnung, gegebenenfalls Outfit-Abo.

| Offen | ID | Szenario und Schritte | Erwartetes Ergebnis |
| --- | --- | --- | --- |
| [ ] | T31-01 | Tarifwahl: 1. Angebote öffnen. 2. Rolle, Laufzeit und Umfang vergleichen. 3. Passenden Tarif wählen. | Korrekte Zuordnung zu Person oder Verein; Preis und Umfang eindeutig. |
| [ ] | T31-02 | Abschluss: 1. Checkout durchführen. 2. Bestätigung öffnen. 3. Freigeschaltete Funktion testen. | Freigabe passend zum Zahlungs-/Abozustand. |
| [ ] | T31-03 | Limits: 1. Kontingent bis Grenze nutzen. 2. Weitere Anlage versuchen. 3. Upgrade/Verlängerung prüfen. | Server prüft Grenze; keine bestehenden Daten still gelöscht. |
| [ ] | T31-04 | Kündigung/Verlängerung: 1. Abo öffnen. 2. Kündigen oder verlängern. 3. Vor/nach Wirksamkeitsdatum prüfen. | Sichtbarer Endtermin und korrekter Zugang. |
| [ ] | T31-05 | Fehlzahlung: 1. Verlängerungsfehler vorbereiten. 2. Hinweis öffnen. 3. Zahlung korrigieren. | Status/Frist verständlich; keine doppelte Abbuchung. |
| [ ] | T31-06 | Aborechnung: 1. Abrechnung öffnen. 2. Richtige Rechnung herunterladen. 3. Mitgliedsbeitragsrechnung daneben prüfen. | Plattformabo und Vereinsbeitrag eindeutig getrennt. |
| [ ] | T31-07 | Outfit einrichten: 1. Angebotenen Plan/Größe/Adresse wählen. 2. Testabschluss ausführen. 3. Abodetails prüfen. | Richtige Varianten und Empfänger; kein unbestätigter Abschluss. |
| [ ] | T31-08 | Outfitlieferung: 1. Lieferung öffnen. 2. Versand-/Zahlungsstatus prüfen. 3. Unterstützte Änderung/Kündigung ausführen. | Lieferung und Abo folgen ihrem jeweiligen Zustand. |

## 32. Ergänzende Rolle: Eltern und Jugendschutz

Voraussetzung: zwei getrennte Familien, mehrere Kinder und kontrollierte Zustimmungstokens. Einstieg: Elternportal beziehungsweise Eltern & Jugendschutz; Web `/eltern-login`.

| Offen | ID | Szenario und Schritte | Erwartetes Ergebnis |
| --- | --- | --- | --- |
| [ ] | T32-01 | Elternzugang: 1. E-Mail/Code anfordern. 2. Gültigen Code eingeben. 3. Falschen/abgelaufenen Code testen. | Zugang nur für verifizierte berechtigte Person. |
| [ ] | T32-02 | Zustimmung: 1. Kinderanfrage öffnen. 2. Zustimmen/ablehnen separat testen. 3. Kinderkonto prüfen. | Zutreffende Freigabe und verständlicher Wartezustand. |
| [ ] | T32-03 | Widerruf: 1. Bestehende Zustimmung öffnen. 2. Widerrufen. 3. Kinderkonto und alte Links prüfen. | Widerruf wirkt entsprechend Schutzregeln. |
| [ ] | T32-04 | Mehrere Kinder: 1. Kind wechseln. 2. Zugängliche Daten vergleichen. 3. Fremde Kind-ID öffnen. | Keine Vermischung und kein fremder Familienzugriff. |
| [ ] | T32-05 | Beziehungseinladung: 1. Sorgebeziehung einladen. 2. Annehmen/ablehnen. 3. Primäre Beziehung ändern/widerrufen. | Beziehung nicht allein durch beliebige E-Mail behauptbar. |
| [ ] | T32-06 | Eigenes Elternkonto: 1. Verfügbaren Kontoumwandlungsweg öffnen. 2. Konto erstellen. 3. Kinderzuordnungen prüfen. | Richtige bestehende Beziehungen; keine doppelten Kinderkonten. |
| [ ] | T32-07 | Minderjährigenkommunikation: 1. Als fremder Erwachsener Kontakt/Profil öffnen. 2. Chat/Einladung versuchen. 3. Freigegebenen Kontaktweg testen. | Tatsächliche Schutzregeln greifen in Web, API und App. |
| [ ] | T32-08 | Altersübergang: 1. Geburtstag/Grenze in Testzeit erreichen. 2. Neu anmelden. 3. Zustimmungen und Rechte prüfen. | Vorhersehbarer, konsistenter Übergang ohne fremde Datenfreigabe. |

## 33. Ergänzende Rolle: Plattformadministration

Voraussetzung: Super-Admin und eingeschränkte Fachadministratoren. Einstieg: Admin → jeweiliges Modul. Jede im Web vorhandene Aktion mit der nativen App vergleichen; Menüeintrag allein genügt nicht.

| Offen | ID | Szenario und Schritte | Erwartetes Ergebnis |
| --- | --- | --- | --- |
| [ ] | T33-01 | Adminzugang: 1. Adminmenü öffnen. 2. Jeden erlaubten Bereich aufrufen. 3. Mit normalem Konto Direktlink testen. | Berechtigte Verwaltung erreichbar; normale Konten ausgeschlossen. |
| [ ] | T33-02 | Vereinsstatus: 1. Verein suchen. 2. Status anklicken und Zielstatus wählen. 3. Bestätigungsmodal abbrechen, dann bestätigen. | Vereinsname sowie alter/neuer Status klar; Änderung nur nach Bestätigung. |
| [ ] | T33-03 | Vereinsprüfung: 1. Nachweise/Prüfstatus öffnen. 2. Notiz und Freigabe/Ablehnung erfassen. 3. Besitzeransicht prüfen. | Richtiger Status, Notiz und Berechtigung in Web/App. |
| [ ] | T33-04 | Vereinslöschung administrativ: 1. Testverein wählen. 2. Vorgeschriebene genaue Bestätigung prüfen. 3. Löschfolge und Abhängigkeiten kontrollieren. | Administrativer Sonderweg ausdrücklich vom Besitzerprozess unterscheidbar. |
| [ ] | T33-05 | Nutzerverwaltung: 1. Nutzer suchen/anlegen. 2. Stammdaten/Rolle bearbeiten. 3. Sperren/entsperren. | Rechte und Kontostatus serverseitig wirksam. |
| [ ] | T33-06 | Nutzerlöschung/Warnung: 1. Warnung/Inaktivitätshinweis senden. 2. Testkonto löschen. 3. Selbstlöschung über Adminweg versuchen. | Richtiger Empfänger; unzulässige Selbstlöschung verhindert. |
| [ ] | T33-07 | Rollen/Berechtigungen: 1. Rolle anlegen/bearbeiten. 2. Personen zuweisen. 3. Mit Fachadministrator prüfen. | Keine unbeabsichtigte Rechteausweitung; Web/App-Abweichung erfasst. |
| [ ] | T33-08 | Traineranträge: 1. Antrag filtern/öffnen. 2. Notiz erfassen. 3. Genehmigen/ablehnen. | Richtige Rollenfolge und Benachrichtigung. |
| [ ] | T33-09 | Moderation: 1. Meldung/Flag öffnen. 2. Zulässige Entscheidung treffen. 3. Einspruch bearbeiten. | Inhalt/Konto korrekt betroffen; nachvollziehbare Entscheidung. |
| [ ] | T33-10 | Blog/CMS: 1. Beitrag/Kategorie anlegen. 2. Rich-Text, Bilder, Vorschau und Übersetzung prüfen. 3. Veröffentlichen/zurückziehen. | Öffentliche Ansicht korrekt; fehlende native Editoraktionen als Lücke. |
| [ ] | T33-11 | Medien: 1. Globale Bildquelle ändern/hochladen. 2. Darstellung in Web/App prüfen. 3. Fehlerdatei testen. | Richtige Bilder; Upload und Berechtigung zuverlässig. |
| [ ] | T33-12 | Sportarten/Badges/Regeln: 1. Datensatz erstellen. 2. Bearbeiten. 3. Löschen beziehungsweise Regel deaktivieren. | Konsistente Referenzen und wirksame Konfiguration. |
| [ ] | T33-13 | Abos/Rechnungen/Zahlungen: 1. Suchen/filtern. 2. Zulässige Zuordnung, Verlängerung oder Zahlungsbestätigung ausführen. 3. Empfänger- und Buchungsseite prüfen. | Kein falsches Konto/Verein; konsistente Finanzzustände. |
| [ ] | T33-14 | Commerce: 1. Produkte/Bestellungen/Gutscheine/Versand/Steuern öffnen. 2. Jeweilige zulässige Aktion ausführen. 3. Käufer-/Anbieteransicht vergleichen. | Fachlich konsistente Änderungen; jede Detailaktion zusätzlich im Inventar prüfen. |
| [ ] | T33-15 | Erstattung/Auszahlung: 1. Berechtigten Testvorgang prüfen. 2. Aktion bestätigen. 3. Wiederholen und Buchungen vergleichen. | Keine doppelte Auszahlung/Erstattung; Audit vorhanden. |
| [ ] | T33-16 | Sponsoren/Kampagnen: 1. Profil/Kampagne prüfen. 2. Status ändern. 3. Sponsor- und öffentliche Ansicht prüfen. | Freigabe und Budget-/Ausspielregeln konsistent. |
| [ ] | T33-17 | Support/Mail: 1. Ticket/Nachricht öffnen. 2. Berechtigte Antwort/Statusaktion ausführen. 3. Empfänger und Versandstatus prüfen. | Nur vorgesehene Empfänger; keine fremden Supportdaten. |
| [ ] | T33-18 | Verträge/Providerkosten: 1. Vertrag/Kosten erfassen oder ändern. 2. Fristen/Beträge prüfen. 3. Eingeschränkte Rolle testen. | Fachrechte und Summen korrekt; keine öffentlich sichtbaren Zugangsdaten. |
| [ ] | T33-19 | Operations/Analyse: 1. Dashboard filtern. 2. Fall/Zeitraum öffnen. 3. Kennzahlen und Kleingruppenschutz prüfen. | Aussagekräftige Daten ohne unzulässige personenbezogene Ableitung. |
| [ ] | T33-20 | Systemeinstellungen: 1. Erlaubte Einstellung ändern. 2. Speichern/neu laden. 3. Wirkung und Rechte prüfen. | Dauerhafte validierte Einstellung; sensible Werte geschützt. |
| [ ] | T33-21 | Paritätsvergleich: 1. Jede Webliste mit mehr als einer Seite vorbereiten. 2. Suche, Filter, Export und Detailaktion nativ wiederholen. 3. Unterschiede protokollieren. | Keine still verkürzten Datenbestände; fehlende Aktionen als Lücke. |

## 34. Rollen-, Vereins- und Datenschutzgrenzen

Voraussetzung: vollständige Kontenmatrix A/B. Diese Gegenprüfungen gelten zusätzlich für jeden schreibenden und lesenden Fachablauf. Entwickler prüfen direkte Requests in einer Testumgebung; Nutzer bedienen nur ihre Testkonten.

| Offen | ID | Szenario und Schritte | Erwartetes Ergebnis |
| --- | --- | --- | --- |
| [ ] | T34-01 | Fremder Verein: 1. Objekt in B anlegen. 2. Mit A Liste/Suche/Direktlink/API aufrufen. 3. Bearbeiten/löschen/exportieren versuchen. | Keine privaten fremden Daten; keine fremde Mutation. |
| [ ] | T34-02 | Unterobjekte: 1. URL mit A verwenden. 2. Mitglied-/Team-/Datei-/Rechnungs-ID aus B einsetzen. 3. Alle relevanten Methoden prüfen. | Eltern- und Objektzugehörigkeit serverseitig geprüft. |
| [ ] | T34-03 | Tarif statt Recht: 1. Hochwertigen Tarif ohne Fachrecht verwenden. 2. Fachaktion aufrufen. 3. Recht ohne erforderlichen Tarif separat prüfen. | Tarif und Berechtigung nicht gleichgesetzt. |
| [ ] | T34-04 | Öffentlich/intern: 1. Öffentliches Vereinsprofil öffnen. 2. Interne Tabs/Downloads anfragen. 3. Als berechtigtes Mitglied vergleichen. | Öffentliche Auffindbarkeit gewährt keine internen Verwaltungsrechte. |
| [ ] | T34-05 | Arbeitsbereichcache: 1. A öffnen. 2. Zu B und anderem Konto wechseln. 3. Offline/Zurück/Suche prüfen. | Keine zwischengespeicherten privaten Daten aus altem Kontext. |
| [ ] | T34-06 | Rechte während Bearbeitung: 1. Formular öffnen. 2. Mitgliedschaft/Recht entziehen. 3. Speichern/Upload fortsetzen. | Server lehnt nicht mehr berechtigte Aktion ab. |
| [ ] | T34-07 | Export/Datei/Echtzeit: 1. Rechte ändern. 2. Alte Downloadlinks und abonnierte Kanäle verwenden. 3. Neue Ereignisse auslösen. | Zugriffsschutz umfasst alle Ausgabekanäle. |
| [ ] | T34-08 | Feldmanipulation: 1. Gültige Testanfrage aufzeichnen. 2. Nicht angebotene Rollen-/Status-/Besitzerfelder ergänzen. 3. Serverzustand prüfen. | Keine Rechte-/Statuseskalation über versteckte Eingabefelder. |
| [ ] | T34-09 | Formularsicherheit: 1. Fremden Ursprung/fehlenden CSRF-Schutz im Test nachstellen. 2. HTML-/Skripttext speichern. 3. Anzeige prüfen. | Unzulässige Anfragen abgewehrt; Nutzereingaben führen keinen Code aus. |
| [ ] | T34-10 | Sitzungs-/Tokengeheimnisse: 1. Profile/Fehlermeldungen/Logs kontrollieren. 2. Externe Verbindungen öffnen. 3. Freigabelinks prüfen. | Keine Passwörter, Provider-Tokens oder unnötigen internen Identifikatoren offengelegt. |

## 35. Für jeden Bereich: Eingaben, Fehler und Bedienbarkeit

Voraussetzung: je mindestens ein Formular, eine Liste, ein Detail, ein Modal und ein Upload aus jedem Modul. Die folgenden Varianten jeweils an allen passenden Fällen ausführen und mit der ursprünglichen Fall-ID verknüpfen.

| Offen | ID | Szenario und Schritte | Erwartetes Ergebnis |
| --- | --- | --- | --- |
| [ ] | T35-01 | Pflicht/Länge: 1. Leerzeichen/leere Pflichtfelder senden. 2. Minimal-, Maximal- und Überlänge testen. 3. Korrigieren. | Einheitliche verständliche Validierung auf Client und Server. |
| [ ] | T35-02 | Zeichen: 1. Umlaute, Akzente, Emoji und lange ungetrennte Wörter eingeben. 2. Speichern. 3. Liste/Export öffnen. | Keine kaputten Zeichen, abgeschnittene Identität oder Layoutüberlagerung. |
| [ ] | T35-03 | Zahlen/Datum: 1. Deutsches Dezimalkomma und englischen Punkt testen. 2. Grenzwerte und ungültige Daten senden. 3. Summen prüfen. | Richtige Werte, Einheiten und Rundung; Fehler verständlich. |
| [ ] | T35-04 | Kleine Displays: 1. Schmale Handybreite und Querformat verwenden. 2. Dialog/Tastatur öffnen. 3. Alle Aktionen bedienen. | Inhalte passen; Felder/Buttons nicht verdeckt; Scrollen möglich. |
| [ ] | T35-05 | Große Schrift: 1. Systemschrift/Zoom stark erhöhen. 2. Vereine verwalten und weitere Seiten öffnen. 3. Titel, Hilfetext und Aktionen prüfen. | Lesbar ohne Überlagerung; wesentliche Informationen und Bedienung bleiben zugänglich. |
| [ ] | T35-06 | Tastatur/Fokus: 1. Nur Tastatur verwenden. 2. Modal öffnen, Felder wechseln, schließen. 3. Fokusposition prüfen. | Sinnvolle Reihenfolge und Fokusführung; keine Tastaturfalle. |
| [ ] | T35-07 | Screenreader: 1. TalkBack/VoiceOver beziehungsweise Web-Screenreader aktivieren. 2. Icons/Formulare bedienen. 3. Fehler/Status vorlesen lassen. | Bedienelemente und Zustände sinnvoll benannt. |
| [ ] | T35-08 | Kontrast/Sprache: 1. Hell/Dunkel und DE/EN testen. 2. Lange Übersetzungen prüfen. 3. RTL für tatsächlich unterstützte Sprache testen. | Lesbare, korrekte Layouts ohne fehlende Übersetzungsschlüssel. |
| [ ] | T35-09 | Langsames Netz: 1. Datenladen drosseln. 2. Aktion auslösen. 3. Mehrfach tippen und Antwort abwarten. | Ladezustand sichtbar; keine Doppelaktion oder irreführender Erfolg. |
| [ ] | T35-10 | Offline/Timeout: 1. Netz beim Laden/Speichern unterbrechen. 2. Meldung lesen. 3. Erneut versuchen. | Eingaben soweit möglich erhalten; kein erfundener Offline-Erfolg. |
| [ ] | T35-11 | Serverfehler: 1. 401, 403, 404, 409, 422, 429 und 5xx kontrolliert auslösen. 2. Rückmeldung prüfen. 3. Wiederherstellung testen. | Fehler passend erklärt, keine Endlosschleife oder sensiblen Stacktraces. |
| [ ] | T35-12 | Abbruch/Zurück: 1. Daten ändern. 2. Modal/Seite verlassen oder System-Zurück drücken. 3. Wieder öffnen. | Kein stilles ungewolltes Speichern; Entwurfs-/Verlustverhalten eindeutig. |
| [ ] | T35-13 | Leere/große Liste: 1. Null, einen und mehrere Seiten Daten testen. 2. Suchen/sortieren/filtern. 3. Letztes Element prüfen. | Alle zugänglichen Daten erreichbar; kein unbemerkter fester Ausschnitt. |
| [ ] | T35-14 | Gleichzeitige Bearbeitung: 1. Gleiches Objekt auf zwei Geräten öffnen. 2. Unterschiedliche Änderungen speichern. 3. Stand vergleichen. | Definiertes Konfliktverhalten und keine unbemerkte Inkonsistenz. |
| [ ] | T35-15 | Gerätefunktionen: 1. Kamera, Fotos, Dateien und Standort jeweils erlauben/verweigern. 2. Berechtigung später ändern. 3. Funktion erneut öffnen. | Sinnvolle Alternative/Meldung; keine Abstürze oder erzwungene unnötige Rechte. |
| [ ] | T35-16 | Update: 1. Alte unterstützte App mit Daten verwenden. 2. Neue Version installieren. 3. Sitzung, Navigation und lokale Daten prüfen. | Kein Datenverlust; notwendige erneute Anmeldung verständlich. |

## 36. Entwickler: Hintergrundprozesse und technische Vollständigkeit

Voraussetzung: kontrollierte Testumgebung. Einstieg: [technisches Inventar](Airmius_Testinventar_2026-09-29.md). Befehle/Provider nur im Testbetrieb ausführen. Dies sind ergänzende technische Tests, keine erfundenen App-Menüs.

| Offen | ID | Szenario und Schritte | Erwartetes Ergebnis |
| --- | --- | --- | --- |
| [ ] | T36-01 | Routenabdeckung: 1. Jede Inventarroute einem Fachfall zuordnen. 2. Alle Methoden und Controlleraktionen prüfen. 3. Unzugeordnete Aktion als neuen Fachfall aufnehmen. | Keine registrierte Route ohne dokumentierte Entscheidung; Zuordnung allein ist kein Testnachweis. |
| [ ] | T36-02 | UI-Abdeckung: 1. Jede Web-/Flutter-Datei im Inventar klassifizieren. 2. Einstieg/Buttons/Tabs prüfen. 3. API-Persistenz und Rollen nachweisen. | Produkt-, Demo-, interne und veraltete Flächen getrennt dokumentiert. |
| [ ] | T36-03 | Wiederkehrende Läufe: 1. Jeden inventarisierten Schedulerbefehl mit fälligen Testdaten ausführen. 2. Vor Frist und erneut testen. 3. Wirkung protokollieren. | Pünktliche fachliche Wirkung ohne Doppelverarbeitung. |
| [ ] | T36-04 | Warteschlange/Outbox: 1. Verarbeitung unterbrechen. 2. Wiederholen. 3. Ereignis/Benachrichtigung/Status vergleichen. | Kein stiller Verlust oder doppelte irreversible Fachwirkung. |
| [ ] | T36-05 | Zahlungswebhooks: 1. Gültige Testsignatur senden. 2. Doppelte, verspätete und falsch signierte Nachricht senden. 3. Buchungen vergleichen. | Nur authentische erlaubte Statusübergänge, keine Doppelzahlung. |
| [ ] | T36-06 | Externe Provider: 1. Timeout/Ausfall/abgelaufene Verbindung simulieren. 2. Fachablauf ausführen. 3. Wiederanlauf prüfen. | Klare Störung und konsistente Daten, keine Zugangsdaten im Fehler. |
| [ ] | T36-07 | Echtzeitkanäle: 1. Jeden Broadcast-Kanal berechtigt abonnieren. 2. Mit fremder Person testen. 3. Rechte widerrufen. | Keine fremden Chat-, Vereins-, Team- oder Benachrichtigungsdaten. |
| [ ] | T36-08 | Öffentliche Seiten: 1. Verzeichnisse, Blog, Jobs, Preise und rechtliche Seiten als Gast öffnen. 2. Sprache/Links/Formulare testen. 3. Mobil wiederholen. | Verfügbare Seiten vollständig und erreichbar; keine internen Daten. |
| [ ] | T36-09 | Auslieferung: 1. Direkte URL neu laden. 2. Datei/Video herunterladen. 3. Cache/Fehlerseite/Redirect prüfen. | Richtige Antworten und Inhalte, keine Weiterleitungsschleifen. |
| [ ] | T36-10 | Wartung/Backup: 1. Testwartungsmodus aktivieren. 2. Zulässige Zugänge prüfen. 3. Testbackup separat wiederherstellen und Daten prüfen. | Verständliche Wartung; nachgewiesene Wiederherstellbarkeit ohne Produktivänderung. |
| [ ] | T36-11 | Performance: 1. Große realistische Testbestände laden. 2. Suche, Scrollen und Kernaktionen messen. 3. Lange Abfragen/Timeouts erfassen. | Vereinbarte Antwortzeit-/Payloadziele eingehalten; keine Datenverkürzung als „Optimierung“. |
| [ ] | T36-12 | Bestehende Tests: 1. Relevante Testdateien im Inventar wählen. 2. In isolierter Testumgebung ausführen. 3. Ergebnis dem Fachfall zuordnen. | Tatsächlicher Lauf dokumentiert; Dateiexistenz nicht als bestanden gewertet. |
| [ ] | T36-13 | Mobile Metadaten/Synchronisierung: 1. Versions-/Funktionsmetadaten laden. 2. Unterstützte Synchronisierung mit Änderungen/gelöschten Objekten ausführen. 3. Wiederholung und Konto-/Vereinswechsel prüfen. | Aktueller konsistenter Zustand, keine wiederhergestellten gelöschten oder fremden Daten. |
| [ ] | T36-14 | Externe Vereins-API: 1. Integration mit berechtigtem begrenztem Token aufrufen. 2. Mitglied lesen/anlegen/ändern. 3. Fremden Verein, widerrufenes Token und Wiederholung testen. | Tokenumfang und Vereinsgrenze wirksam; eindeutige Datensätze und Fehler. |
| [ ] | T36-15 | Technische Zugänge: 1. Healthcheck, CORS/OPTIONS, CSRF und signierten Speicherzugriff prüfen. 2. Ungültige Herkunft/Signatur/Ablauf testen. 3. Antwort und Rate-Limit vergleichen. | Technische Verfügbarkeit ohne Preisgabe interner Details oder Umgehung von Schreib-/Dateirechten. |

## 37. Pflichtregressionen und vollständige Nutzerreisen

Voraussetzung: mehrere Geräte/Rollen; vorherige Abschnitte liefern die Einzelschritte. Jeden Ablauf von Anfang bis Ende ohne vorbereitende Direktänderung des Zwischenzustands durchführen.

| Offen | ID | Szenario und Schritte | Erwartetes Ergebnis |
| --- | --- | --- | --- |
| [ ] | T37-01 | Neuer Sportler: 1. Registrieren/verifizieren. 2. Verein beantragen und aufnehmen. 3. Teambeitritt und Training öffnen. | Durchgängiger Ablauf ohne Adminrechte für den Sportler. |
| [ ] | T37-02 | Rechnung bis Zahlung: 1. Verein erstellt Rechnung im Web. 2. Sportler öffnet Push in App und lädt Rechnung. 3. Zahlung erfassen und beide Ansichten prüfen. | Kein 403, richtiger Abrechnungstab, übereinstimmender Restbetrag. |
| [ ] | T37-03 | Umgekehrte Plattformen: 1. Rechnung in App erstellen. 2. Nachricht im Web öffnen. 3. PDF und Zahlung aktualisieren. | Identisches fachliches Ergebnis auf beiden Wegen. |
| [ ] | T37-04 | Teamisolation: 1. Verein A öffnet Teams und jede Zusatzfunktion. 2. Verein B als Gegenprobe verwenden. 3. Suche und Direktlinks prüfen. | Keine fremde Vereinsverwaltung; keine Rundlaufnavigation. |
| [ ] | T37-05 | Zusammenarbeit: 1. A erstellt Aufgabe mit Frist/Anhang im Web. 2. Mitglied kommentiert und erledigt in App. 3. Kalender/Status im Web prüfen. | Gemeinsamer konsistenter Stand, ohne fremde Sichtbarkeit. |
| [ ] | T37-06 | Kalendernavigation: 1. Kalender über Seitenleiste öffnen. 2. Ansichten und Fristeintrag wählen. 3. Von Details zurückkehren. | Richtiger Kalender, erhaltene Auswahl, korrekte Detailziele. |
| [ ] | T37-07 | Mobile Lesbarkeit: 1. Als Sportler Vereine verwalten öffnen. 2. Große Schrift aktivieren. 3. Zusätzlich Rechnungsauswahl und Aufgabenmodal bedienen. | Vollständige verständliche Identität/Texte und erreichbare Aktionen. |
| [ ] | T37-08 | Zweiter Verein: 1. Besitzer legt B an. 2. Mitglieder/Team/Aufgabe getrennt erstellen. 3. Wechseln und nach fremden Daten suchen. | Beide Vereine getrennt, keine Vermischung von Aktionen oder Cache. |
| [ ] | T37-09 | Import bis Nutzung: 1. Externes Mitglied importieren. 2. Einladung annehmen und Konto verknüpfen. 3. Rechnung/Team/Karte öffnen. | Eine konsistente Identität ohne verlorene historische Zuordnungen. |
| [ ] | T37-10 | Trainerablauf: 1. Plan erstellen/veröffentlichen. 2. Sportler protokolliert. 3. Trainer gibt Feedback und prüft Wochenübersicht. | Planung, Leistung und Rückmeldung korrekt verbunden. |
| [ ] | T37-11 | Sponsorablauf: 1. Markenprofil und Kampagne erstellen. 2. Berechtigt prüfen/freigeben. 3. Kontrollierte Ausspielung/Kennzahlen ansehen. | Richtige Freigabe und isolierte Sponsorendaten. |
| [ ] | T37-12 | Elternablauf: 1. Minderjährigenkonto erstellen. 2. Elternzustimmung und Vereinsaufnahme durchführen. 3. Zustimmung widerrufen. | Schutzregeln über den gesamten Ablauf wirksam. |
| [ ] | T37-13 | Vereinslöschung zurücknehmen: 1. Besitzer beantragt. 2. Vorstand erhält Nachricht. 3. Innerhalb 30 Tagen zurücknehmen und nach Termin prüfen. | Informierte Verantwortliche und wirksame Rücknahme ohne Datenverlust. |
| [ ] | T37-14 | Administrator nativ: 1. Als Super-Admin App-Admin öffnen. 2. Vereinsstatus ändern und bestätigen. 3. Webstatus und eingeschränkte Rolle prüfen. | Native Aktion korrekt; gleicher Status und serverseitige Rechte. |
| [ ] | T37-15 | Kauf bis Erstattung: 1. Testprodukt bestellen/bezahlen. 2. Problem/Retoure melden. 3. Genehmigte Erstattung und Dokumente prüfen. | Bestell-, Zahlungs- und Dokumentstatus stimmen überein. |

## 38. Sportler und Teams: Fahrgemeinschaften

Voraussetzung: Fahrer, zwei Mitfahrer und gegebenenfalls verknüpfter Termin. Einstieg: Fahrgemeinschaften/Rides; Web `/rides`.

| Offen | ID | Szenario und Schritte | Erwartetes Ergebnis |
| --- | --- | --- | --- |
| [ ] | T38-01 | Fahrt finden: 1. Fahrten öffnen. 2. Verfügbare Filter und Eventbezug wählen. 3. Fahrt öffnen. | Richtige sichtbare Fahrt mit Abfahrt, Ziel, Zeit und freien Plätzen. |
| [ ] | T38-02 | Fahrt anbieten: 1. Neue Fahrt öffnen. 2. Strecke, Zeitpunkt, Plätze und zulässige Angaben eintragen. 3. Speichern. | Gültiges Angebot im richtigen Kontext; keine negativen Plätze oder falschen Zeiten. |
| [ ] | T38-03 | Platz anfragen: 1. Fahrt auswählen. 2. Platz anfragen. 3. Als Fahrer annehmen beziehungsweise ablehnen. | Passender Status und richtige verbleibende Kapazität. |
| [ ] | T38-04 | Letzter Platz: 1. Einen freien Platz vorbereiten. 2. Zwei Personen gleichzeitig anfragen lassen. 3. Zusagen prüfen. | Keine unkontrollierte Überbuchung. |
| [ ] | T38-05 | Fahrt ändern/absagen: 1. Angebot bearbeiten. 2. Mitfahreransicht prüfen. 3. Fahrt absagen. | Teilnehmer sehen aktuellen Stand; kein weiter buchbarer abgesagter Termin. |
| [ ] | T38-06 | Rückzug: 1. Eigene Teilnahme zurückziehen. 2. Fahrt erneut öffnen. 3. Freie Plätze vergleichen. | Korrekte Kapazität und nachvollziehbare Rückmeldung. |
| [ ] | T38-07 | Kontaktdaten: 1. Fahrt als Mitfahrer öffnen. 2. Mit Fremdkonto vergleichen. 3. Teilnahme/Recht entziehen. | Kontakt-/Standortdaten nur gemäß Freigaberegeln; keine dauerhafte Fremdfreigabe. |

## 39. Alle Rollen: Support und Hilfe

Voraussetzung: normales Konto und zuständiger Supportbearbeiter. Einstieg: Seitenleiste → Support; Web `/support`.

| Offen | ID | Szenario und Schritte | Erwartetes Ergebnis |
| --- | --- | --- | --- |
| [ ] | T39-01 | Support öffnen: 1. Menü → Support wählen. 2. Verfügbare Kontaktwege und Hilfen öffnen. 3. Mit jeder Rolle wiederholen. | Passender Hilfebereich erreichbar, auch ohne Plattform-Adminrecht. |
| [ ] | T39-02 | Kontaktformular: 1. Betreff und Beschreibung ausfüllen. 2. Absenden. 3. Eingangsbestätigung prüfen. | Ein klar zugeordneter Vorgang; kein stiller Verlust. |
| [ ] | T39-03 | Ticket: 1. Neues Ticket erstellen. 2. Eigene Ticketliste öffnen. 3. Details/Status prüfen. | Richtiger eigener Vorgang und verständlicher Bearbeitungsstand. |
| [ ] | T39-04 | Rückmeldung: 1. Support antwortet über den angebotenen Weg. 2. Nutzer öffnet Hinweis. 3. Verfügbare Antwort-/Anhangfunktion prüfen. | Richtiger Empfänger; nur tatsächlich vorhandene Dialogfunktionen als verfügbar werten. |
| [ ] | T39-05 | Fehler/Privatheit: 1. Pflichtangaben auslassen oder Versand unterbrechen. 2. Erneut senden. 3. Fremde Ticket-ID öffnen. | Verständliche Validierung; keine doppelte Anfrage oder fremde Supportdaten. |
| [ ] | T39-06 | Vereinskontext: 1. Anfrage aus Verein A stellen. 2. Zu B wechseln. 3. Sichtbarkeit/Bearbeitungsrecht prüfen. | Persönlicher und vereinsbezogener Vorgang korrekt getrennt. |
| [ ] | T39-07 | Sicherheitsmeldung: 1. Angebotenen Sicherheits-/Schutzmeldeweg öffnen. 2. Testmeldung absenden. 3. Zuständigkeit, Bestätigung und eingeschränkten Zugriff prüfen. | Vertraulicher Vorgang bei zuständigen Bearbeitern; nicht öffentlich oder für andere Vereine sichtbar. |

## 40. Besucher und alle Rollen: öffentliche Inhalte

Voraussetzung: ausgeloggter Browser und angemeldete Vergleichssitzung; veröffentlichte und unveröffentlichte Testinhalte. Einstieg: öffentliche Navigation, Blog, Verzeichnisse und rechtliche Seiten.

| Offen | ID | Szenario und Schritte | Erwartetes Ergebnis |
| --- | --- | --- | --- |
| [ ] | T40-01 | Blog lesen: 1. Blog/Kategorie öffnen. 2. Beitrag auswählen. 3. Sprache, Bilder und weiterführende Links prüfen. | Nur veröffentlichter Inhalt; korrekte Übersetzung und vollständige Darstellung. |
| [ ] | T40-02 | Verzeichnisse: 1. Vereine, Sponsoren, Sportarten, Städte und Events einzeln öffnen. 2. Suchen/filtern. 3. Detailseite wählen. | Öffentliche Sichtbarkeit und korrekte Detailziele; keine internen Verwaltungsdaten. |
| [ ] | T40-03 | Öffentlicher Antrag: 1. Verfügbares Vereinsaufnahmeformular ohne Konto öffnen. 2. Antrag senden. 3. Statuslink gültig/ungültig testen. | Richtiger Verein, geschützter Antragsstatus und kein Zugriff mit falschem Token. |
| [ ] | T40-04 | Öffentliche Kursbuchung: 1. Verfügbaren Gastbuchungsweg öffnen. 2. Erforderliche Angaben senden. 3. Status und spätere Kontozuordnung prüfen. | Eine korrekte Buchung ohne unberechtigte Kursfreigabe oder Kontoübernahme. |
| [ ] | T40-05 | Rechtliche Seiten: 1. Impressum, Datenschutz, AGB, Widerruf und angebotene Schutz-/Communityseiten öffnen. 2. Sprache wechseln. 3. Mobile Darstellung prüfen. | Seiten erreichbar und vollständig; Links zeigen keine falsche Sprache oder leeren Inhalt. |
| [ ] | T40-06 | Cookieauswahl: 1. Neue Browsersitzung öffnen. 2. Optionale Kategorien akzeptieren/ablehnen. 3. Auswahl später widerrufen. | Auswahl bleibt und wirkt wie beschrieben; notwendige Anmeldung weiter möglich. |
| [ ] | T40-07 | Teilen/Anmeldung: 1. Öffentlichen Link teilen. 2. In App beziehungsweise Browser öffnen. 3. Geschützte Folgeaktion starten und anmelden. | Öffentlicher Inhalt erreichbar; geschützte Aktion nach Anmeldung am richtigen Ziel. |
| [ ] | T40-08 | Unveröffentlichter Inhalt: 1. Entwurf oder zurückgezogenen Inhalt vorbereiten. 2. Alte URL und Suche als Gast verwenden. 3. Autoransicht vergleichen. | Kein unbeabsichtigter Entwurfszugriff; sinnvoller nicht verfügbarer Zustand. |
| [ ] | T40-09 | Öffentliche Feeds/Indexierung: 1. RSS, Sitemap, Robots und Webmanifest öffnen. 2. Sprache, URLs und Metadaten prüfen. 3. Entwürfe/private Inhalte als Gegenprobe verwenden. | Technisch gültige öffentliche Ausgabe; keine privaten Inhalte über Indexierungswege. |

## Nachgewiesene offene Punkte und Prüfgrenzen

Aktualisierung am 01.10.2026: Codekorrekturen und automatisierte Nachweise sind unten getrennt von der weiterhin erforderlichen Live-Abnahme aufgeführt. Details: [Nachprüfung](PARITY_RECHECK_2026-10-01.md).

1. **Kalenderansichten angeglichen:** Web und App bieten jetzt `day`, `week`, `month`, `year`; Monats-/Jahreswechsel werden auf gültige Tage begrenzt. Automatisierte Kalenderprüfungen vorhanden. Live-Nachweis über T25-02/T37-06 bleibt separat.
2. **Native Adminparität teilweise geschlossen:** Native Kursqualitätsprüfung und Fachrollen-Zugänge sind ergänzt; Redaktion und Verträge erhalten Suche/Seitenwechsel. Vollständiger Rich-Text-Editor sowie restliche Listen-/Exportparität bleiben offen; Details in [Native Admin Coverage](native-admin-parity.md). T16-08 und T33-07/T33-10/T33-21 nicht pauschal als live abgenommen markieren.
3. **Facility-Einstieg angebunden:** Die lokalen Beispieldaten wurden durch den vorhandenen API-basierten Inventar-/Buchungsweg ersetzt. Zeitraumwahl, Speichern, Konfliktprüfung und erneutes Laden sind automatisiert geprüft. Serienbuchungen und eine vollständige grafische Ressourcenplanung sind dadurch nicht implementiert. Andere `suite`-Dateien wurden nicht pauschal bewertet.
4. **Sponsorprofil ergänzt:** Der native Editor enthält rechtliche Angaben und explizite Zustimmungen sowie Korrekturen bei vorhandenen Profilen. API-/Widgettests prüfen die Kontozustände und Fehlerfälle; die Live-Abnahme T29-01 bleibt separat.
5. **Bekannte veraltete Webpfade korrigiert:** Die Self-Service-Gap-Matrix verwendet die registrierten Pfade `/settings`, `/club-inventory`, `/notifications`, `/files`. Ein Regressionstest vergleicht Dokumentation und Routen. Der Namenspräfix `auth.` ist kein URL-Präfix; echte `/api/v1/auth/...`- und OAuth-Routen bleiben unverändert.
6. **Reale Integrationen bleiben auszuführen:** Push, E-Mail, Kamera, GPS, Dateiauswahl, Zahlungen, Bankdateien und Store-Updates benötigen reale beziehungsweise kontrollierte Integrationstests. Die Erstellung dieser Liste führt diese Vorgänge nicht aus.
7. **Direktchat-Datenschutz implementiert:** Web und App bieten jetzt „Chat für mich löschen“ sowie Blockieren/Entblockieren direkt in den Chat-Einstellungen. Die serverseitige Verlaufsschranke erhält den Verlauf des Gegenkontos, blendet alte Nachrichten für das löschende Konto einschließlich Direktlinks aus und lässt den Chat erst mit neuen Nachrichten wieder erscheinen. API- und Sicherheitstests decken Einseitigkeit, Wiedererscheinen und die gleiche Freundschaftsbereinigung beim mobilen Blockieren ab; die geräteübergreifende Live-Abnahme bleibt T05-10.
8. **Leere Gruppenchats werden bereinigt:** Beim Verlassen prüft der Server den aktuellen Mitgliederstand innerhalb einer gesperrten Transaktion. Verlässt das letzte Mitglied die Gruppe, wird die Unterhaltung unabhängig vom Client-Flag gelöscht; Datenbank-Kaskaden entfernen Mitgliedschaften und Nachrichten. Bleibt mindestens eine Person übrig, bleiben Gruppe und Verlauf erhalten. Automatisierte API-Regressionstests decken beide Fälle ab; die Web-/App-Live-Abnahme bleibt T05-08.

## Quellen und Abschlussregel

Als Grundlage wurden unter anderem Routen in `routes/`, die Webnavigation in `resources/js/composables/useAirmiusShellNavigation.js`, die Appnavigation in `mobile/airmius_mobile/lib/screens/shell_screen.dart`, Kalender-/Aufgaben-/Sponsoroberflächen, `app/Services/GlobalSearchService.php` und vorhandene fachliche Testverträge ausgewertet. Frühere Handbücher dienten als Suchhilfe und wurden nicht als Beweis vollständiger Implementierung übernommen.

Die folgenden drei Ebenen werden getrennt abgeschlossen:

- **Inventar vollständig erfasst:** Anzahl und Einträge stimmen mit dem festgehaltenen Quellstand überein.
- **Szenarien vollständig zugeordnet:** Jede Inventaraktion besitzt einen passenden Fachfall oder einen begründeten Status; neue Detailaktionen erhalten ergänzende IDs.
- **Release tatsächlich abgenommen:** Alle anwendbaren Plattform-/Rollenvarianten wurden ausgeführt; Fehler, Blockaden und Funktionslücken sind behoben oder ausdrücklich als verbleibend dokumentiert.

Ein Release gilt durch dieses Dokument nicht automatisch als getestet. Bei neuen Routen, Screens, Rollen oder Geschäftsregeln Inventar und zugehörige Szenarien gemeinsam aktualisieren.

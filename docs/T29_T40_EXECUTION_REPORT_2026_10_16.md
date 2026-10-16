# T29-T40 Ausfuehrungsbericht Web und App

Stand: 16.10.2026  
Geraet: Xiaomi 23127PN0CG, Android 16, App `com.airmius.app`, VersionCode 287  
Konto: Sponsor-Organisation `Aida2`, Rolle `Inhaber`; zusaetzlich Vereinsverwaltung mit eingeschraenktem Plattform-Adminzugriff

## Statusregeln

- `BESTANDEN`: auf dem echten Geraet oder durch einen ausgefuehrten automatisierten Test nachgewiesen.
- `TEILWEISE`: ein sicherer Teil wurde nachgewiesen; weitere Rollen, Provider oder Geraete fehlen.
- `BLOCKIERT`: mit dem vorhandenen Konto, Datenbestand oder ohne isolierten Testprovider nicht gefahrlos ausfuehrbar.
- `NICHT ANGEBOTEN`: die Funktion wird im aktuellen Konto/Datenbestand nicht angeboten.
- Ein sichtbarer Menueeintrag allein gilt nicht als bestanden.

## Ergebnis je Phase

| Phase | Status | Ausgefuehrte Nachweise | Offene manuelle Gegenproben |
| --- | --- | --- | --- |
| T29 Sponsoren | TEILWEISE | Das echte Sponsor-Konto `Aida2` wurde auf dem Xiaomi geprueft. Profil, native Logoauswahl, Verifizierungsstatus, Teamzugang, Partnerschaft, Kampagnenformular, Kennzahlen und Materialeinstieg wurden geoeffnet. Quellstand gliedert das Cockpit in `Uebersicht`, `Kampagnen`, `Partnerschaften`, `Material` und `Team`. | Adminentscheidung, Sponsor-B-Gegenprobe, ausspielbare Kampagne, Messereignisse, Budgetausschoepfung und fremde Assets benoetigen weitere Konten und kontrollierte Testdaten. |
| T30 Marketplace | TEILWEISE | T30-01 Produkt-/Anbieterdetail geladen. T30-03 Warenkorb blieb nach App-Neustart erhalten. T30-04 Server-Summe 35,10 EUR + 4,90 EUR Versand = 40,00 EUR; Steuer 6,38 EUR ausgewiesen. Testartikel anschliessend entfernt. | T30-02 und T30-05-17 benoetigen gekaufte Produkte, Testzahlungsanbieter, Gasttoken, Anbieter-/Adminrollen oder parallele Kaeufer. Keine echte Zahlung ausgeloest. |
| T31 Abos/Outfit | TEILWEISE | Abo-Center geladen; persoenliche/Vereinsabos, Status, Enddatum sowie Tabs fuer Plaene, Zahlungen und Rechnungen vorhanden. Outfit-Center mit Uebersicht/Plaene/Abos geladen. | T31-02-06 benoetigen isolierte Testzahlung und Limits. T31-07/08 sind im aktuellen Bestand `NICHT ANGEBOTEN`, weil keine Outfit-Plaene verfuegbar sind. |
| T32 Eltern/Jugendschutz | BLOCKIERT | Rollen- und Paritaetsvertraege automatisiert geprueft. Minderjaehrigenschutz fuer Fahrgemeinschaften durch Regressionstest abgesichert. | Elternportal ist fuer das aktuelle Konto nicht sichtbar. Zwei Familien, Kinderkonten und kontrollierte Zustimmungstokens fehlen fuer T32-01-08. |
| T33 Plattformadministration | TEILWEISE | Admin-Arbeitsbereich geladen. Das aktuelle eingeschraenkte Konto sieht nur `Produktanalyse`; nicht erlaubte Super-Adminbereiche werden nicht angeboten. | T33-02-21 benoetigen Super-Admin, Fachadministratoren und isolierte Testobjekte. Loeschungen, Zahlungen und Auszahlungen wurden nicht produktiv veraendert. |
| T34 Vereins-/Datenschutzgrenzen | TEILWEISE | Vertrags- und Policy-Tests fuer Vereins-/Objektgrenzen erfolgreich; aktuelles Konto zeigt nur erlaubten Adminumfang. | Direkte A/B-ID-Gegenproben, Rechteentzug waehrend Bearbeitung, alte Downloadlinks, Proxy-/CSRF-/XSS-Tests benoetigen zwei getrennte Konten und Testproxy. |
| T35 Eingaben/Bedienbarkeit | TEILWEISE | Leere Supportanfrage zeigte Feldvalidierung; Sponsorprofiltests decken 401/403/422/500, schmale Displays, Tastatur-Inset, Abbruch und vier Sprachen ab. Mobile Analyse ohne Befund. | Maximale Systemschrift, TalkBack, Hardwaretastatur, langsames Netz, Flugmodus, Kamera/Dateien/Standort und echtes App-Update bleiben Geraetemanualtests. |
| T36 Technik | TEILWEISE | Web-Build erfolgreich. Der aktuelle T29-T40-Vertragslauf bestand 41 Tests mit 401 Assertions. | 129 datenbankgestuetzte Tests sind in dieser Umgebung wegen fehlendem PHP-SQLite-Treiber blockiert. Scheduler, Queue, Webhooks, Providerausfall, Backup-Restore, Last und externe API brauchen isolierte Infrastruktur. |
| T37 Nutzerreisen | BLOCKIERT | Gemeinsame Web-/App-Routen und Deep-Link-Vertraege automatisiert geprueft. | T37-01-15 benoetigen mehrere Rollen/Geraete sowie Zahlungs-, Push-, E-Mail-, Eltern-, Sponsor- und Adminablaeufe. Mit nur einem Konto kein belastbarer Ende-zu-Ende-Nachweis. |
| T38 Fahrgemeinschaften | FEHLER BEHOBEN | Live-App zeigte Ladefehler. Ursache: Modul sichtbar, Policy liess aber nur globale Spieler-/Elternrollen zu. Policy akzeptiert jetzt Vereinsinhaber, aktive Mitglieder und Teammitglieder; Minderjaehrige ohne Einwilligung bleiben gesperrt. App zeigt bereinigte API-Fehlerdetails. | Neue App-/Backend-Version bereitstellen und T38-01-07 danach mit Fahrer plus zwei Mitfahrern wiederholen. Paralleler letzter Platz bleibt Mehrgeraetetest. |
| T39 Support | TEILWEISE | Support fuer das normale Konto erreichbar; bestehendes eigenes Ticket sichtbar. Leeres Formular erzeugte klare Feldfehler und keinen Vorgang. | Antwort/Anhang, Fremd-ID, Vereinswechsel, Offline-Wiederholung und Sicherheitsmeldung benoetigen Supportkonto und Verein B. |
| T40 Oeffentliche Inhalte | TEILWEISE | Oeffentliche Inhalte und Indexierungswege sind durch Phasenvertraege abgedeckt. | Ausloggen wurde vermieden, damit die einzige Geraetesitzung erhalten bleibt. Cookie-Widerruf, Gastantraege/-buchung, veraenderte Tokens und unveroeffentlichte Inhalte bleiben Browser-/Gasttests. |

## Behobene Punkte

1. Sponsor-Cockpit Web und App: lange Einzelseite durch fuenf klare, horizontal erreichbare Bereiche ersetzt.
2. Sponsor-Cockpit App: lokalisierte Tabbezeichnungen fuer Deutsch, Englisch, Franzoesisch und Arabisch.
3. Fahrgemeinschaften Backend: aktive Vereinsmitglieder, Teammitglieder und Vereinsinhaber erhalten den vorgesehenen Zugriff.
4. Fahrgemeinschaften Jugendschutz: ausstehende oder fehlende Einwilligung bei Minderjaehrigen bleibt eine harte serverseitige Sperre.
5. Fahrgemeinschaften App: konkrete, nutzergeeignete API-Fehlermeldung plus Wiederholen statt eines nichtssagenden Ladefehlers.
6. Regression: eigener Widget-Test fuer die Sponsor-Cockpit-Tabs hinzugefuegt.
7. Sponsor-Kampagnen App: neue Kampagnen starten jetzt standardmaessig als Entwurf; Zahlung muss bewusst eingeschaltet werden.
8. Sponsor-Material App: eigener Kontext `Kampagnen & Auftraege` mit nur den relevanten Bereichen statt irrefuehrender `Shop-Verwaltung`.
9. Sponsor-Aktivitaeten App: `profile.logo_updated` wird in DE/EN/FR/AR als lesbarer Aktivitaetstext angezeigt.

## T29 Detailabnahme auf dem echten Android-Geraet

| ID | Status | Ausgefuehrter Nachweis | Ergebnis / Restbedarf |
| --- | --- | --- | --- |
| T29-01 | TEILWEISE | `Sponsor-Cockpit -> Profil bearbeiten` geoeffnet. Markenname, rechtlicher Name, Land, Register-/USt.-Angaben, Kontakt, E-Mail, Website und beide Zustimmungen sind vorhanden. App wurde vollstaendig beendet und neu gestartet; Sitzung, Organisation `Aida2` und Sponsor-Cockpit blieben erhalten. | Bestehende produktionsnahe Stammdaten wurden nicht veraendert. Datenmutation und Webvergleich sind durch Quell-/Widgettests abgedeckt, aber nicht als neue Live-Mutation ausgefuehrt. |
| T29-02 | TEILWEISE | Hauptlogo sowie getrennte Logos fuer helle/dunkle Hintergruende vorhanden. Android-Dateiauswahl wurde erfolgreich geoeffnet und auf Bildquellen begrenzt. | Profil ist noch nicht oeffentlich freigegeben und es existiert keine sichtbare Kampagne; Theme-Darstellung und echte Fehlerdatei bleiben blockiert. |
| T29-03 | BLOCKIERT | Sponsor sieht `Oeffentliche Verifizierung` und `Deine Angaben werden geprueft`; keine Selbstfreigabe vorhanden. Antrag zeigt aktiven Sponsorzugang. | Ablehnung/Notiz/Genehmigung benoetigen Konto E mit Adminrecht. |
| T29-04 | BLOCKIERT | Eigene Partnerschaft `Aida2 / Airmius Plattform / Aktiv` geoeffnet. | Dieser Datensatz besitzt keine Laufzeit-, Paket-, Rechte- oder Vertragsdaten. Sponsor B fuer Fremdzugriffsgegenprobe fehlt. |
| T29-05 | NICHT ANGEBOTEN | `Oeffentlich ansehen` geoeffnet. Oeffentliche Sponsorzahl ist 0; es werden keine aktiven Sponsorprofile oder Kontaktaktionen angeboten. | Erst nach Profilfreigabe und mit einem aktiven Partnerprofil testbar. |
| T29-06 | BLOCKIERT | Partnerschaft aufgeklappt; `Vereinbarte Leistungen (0)` sichtbar. | Fuer die Plattformpartnerschaft wird kein Plus/Bearbeiten angeboten. Ein verwaltbarer Vereinsvertrag und ein unberechtigtes Gegenkonto fehlen. |
| T29-07 | FEHLER BEHOBEN | Kampagnenformular mit Name, Motiv, Ziel-URL, Zeitraum, Ziel, Platzierung, Format, Gesamt-/Tagesbudget und Interessen geoeffnet. | Installierte Version 287 schaltet `Zahlung jetzt starten` standardmaessig ein. Im Quellstand ist der sichere Entwurf nun Standard; gezielter Widgettest bestanden. |
| T29-08 | BLOCKIERT | Statusdarstellung und Statusaktionen sind im Code-/Vertragstest vorhanden. | Kein gespeicherter freizugebender Entwurf, kein Admin und keine oeffentliche Ausspielung vorhanden. |
| T29-09 | BLOCKIERT | Cockpit zeigt aktuelle Baseline `0 Klicks`, `0 aktive Kampagnen`, `1 offene Freigabe`. | Fuer Impression, Klick, CTR und Sponsor-B-Isolation fehlen aktive Kampagne sowie G2/G4. |
| T29-10 | BLOCKIERT | Consent-/Conversion-Vertraege im Quellstand vorhanden. | Kein kontrollierter Testevent-Zugang und kein G2-Konto in dieser Sitzung; PHP-Featurelauf zusaetzlich durch fehlenden SQLite-Treiber blockiert. |
| T29-11 | FEHLER BEHOBEN | `Creative-Assets (0)` und `Agenturbriefings (0)` sichtbar. In Version 287 fuehrt `Creative-Assets -> Oeffnen` irrefuehrend zur `Shop-Verwaltung`. | Quellstand zeigt nun `Kampagnen & Auftraege` und nur `Werbung`/`Website`. Datei-/Fremdzugriff bleibt ohne Testasset und Sponsor B blockiert. |
| T29-12 | TEILWEISE | Mindestbudget 10 EUR wird angezeigt; Client und Server begrenzen Zieladressen auf HTTP/HTTPS. Beide Readiness-Vertragstests bestanden. | Echte Budgetausschoepfung und Ausspielstopp wurden ohne Testzahlung/aktive Kampagne nicht ausgeloest. |

Weitere Live-Beobachtung: In `Letzte Aktivitaeten` zeigte Version 287 mehrfach den Rohschluessel `sponsorCockpit.activity.profile.logo_updated`. Die Lokalisierung ist im Quellstand fuer Deutsch, Englisch, Franzoesisch und Arabisch ergaenzt.

## Ausgefuehrte Befehle

- `npm run build`: erfolgreich.
- `flutter analyze` fuer Sponsor-Cockpit, Lokalisierung und Fahrgemeinschaften: keine Befunde.
- `flutter test test/sponsor_profile_test.dart test/preferences_test.dart test/airmius_preferences_store_io_test.dart`: 30/30 erfolgreich.
- Erweiterter Sponsor-Tab-Testlauf: 22/22 erfolgreich.
- Gezielter Sponsor-Kampagnen-Widgettest: 1/1 erfolgreich.
- Sponsor-Profil-/Cockpit-Widgettests: 22/22 erfolgreich.
- T29 Readiness-Unitvertraege: 7/7 erfolgreich, 67 Assertions im kombinierten Lauf vor den datenbankgestuetzten Tests.
- PHP-Unit-Vertraege T29-T40: 41 Tests, 401 Assertions erfolgreich.
- PHP-Syntax und Pint fuer die Ride-Policy und Featuretests: erfolgreich.

## Release-Hinweis

Das angeschlossene Handy verwendet noch VersionCode 287. Die hier behobenen Punkte sind im Quellstand und in den Tests enthalten, aber erst nach Backend-Deployment und einer neu gebauten App-Version auf diesem Geraet sichtbar. Es wurde kein Kampagnenentwurf dauerhaft angelegt und keine produktive Zahlung, Auszahlung, Erstattung, Kuendigung oder Loeschung ausgefuehrt. Der vollstaendige Flutter-Gesamtlauf hat 33 bereits bestehende, fachfremde Fehler; die gezielten Sponsorlaeufe sind erfolgreich. Datenbankgestuetzte PHP-Featuretests bleiben wegen des auf diesem Host fehlenden SQLite-Treibers blockiert.

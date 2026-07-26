# AIRMIUS Produkt-Readiness-Audit

Stand: 2026-07-26

## Entscheidung

AIRMIUS ist als technisch funktionsreiche, geschlossene Pilot-Beta einsetzbar, aber noch nicht fuer einen oeffentlichen Produktivstart oder eine breite bezahlte Vermarktung startklar.

Technische Basis: **gruen**. Produkt-, Lokalisierungs-, Provider-, Realgeraete-, Legal- und Betriebsfreigabe: **nicht vollstaendig**.

## Nachweise dieses Audits

- `php artisan test`: 548 bestanden, 4 uebersprungen, 5.367 Assertions.
- `flutter test`: 210 bestanden; `flutter analyze`: keine Befunde.
- Die arabische Light-/Trail-Großschrift-Gate prüft jetzt 64 zentrale Parity-/Funktions-Suites einschließlich Navigation, Offline-Sync, Input, RTL-Formatierung, Datenschutzberechtigungen, Session-Sicherheit, State-Feedback, Maps, Uploads, Overlays, Formularschema, Analytics, Entitlements, Push-Deep-Links, geschütztes Deep-Link-/Consent-Routing, End-to-End-Journeys, Auth-/Guardian-Gates, Ressourcen-/Helferplanung, Draft-Recovery, Freigabe-Workflows, Verfügbarkeit, rollenbasierte Home-Widgets, API-Mapping, Store-Assets, Vereinsprofil, Job-Queue, Audit-Timeline, Sponsor-CRM, Marketplace-/Mitgliederservice, Orte/Karten, Lernen/Zertifikate, Gesundheit/Vorfälle, Vereinsregeln, Moderation/Audit, Analytics/Reports, API-State-UX und Content Publishing.
- Zusätzlich sind UI-Coverage und generische Aktionsrückmeldungen als palette-aware Auditflächen eingebunden; Coverage-Untertitel und Statuswerte sind in DE/EN/FR/AR getestet.
- Die sichtbare Sport-App-Navigation öffnet das Integrationscenter direkt; der öffentliche Marketplace kommuniziert verfügbare Gast- und Anbieterflows ohne Roadmap-Platzhalter und bleibt in DE/EN/FR/AR konsistent.
- Die UI-Coverage-Auditseite ist in DE/EN/FR/AR lokalisiert, leitet ihre Modul-/Ops-Zahlen aus dem aktuellen Modulregister ab und kennzeichnet API-Verknüpfung bzw. Prüfstatus transparent.
- Die Web-Einstellungen kennzeichnen Google Fit und Strava als nutzbare Synchronisationsflows und verwenden dafür konsistente DE/EN/FR/AR-Texte ohne falsche „später“-Versprechen.
- Die KI-Trainingsplanung benennt die automatische Einheitentyp-Zuordnung als aktive Funktion und verwendet dafür konsistente Übersetzungen.
- Die mobile Sport-Integrations-API liefert statusbasierte Übersetzungsschlüssel für Anbieter- und Kontostatus und vermeidet den irreführenden „vorbereitet“-Text beim Strava-Import.
- Die öffentliche Preis-Seite lokalisiert Zielgruppen, Plan-/Checkout-Texte und dynamische Währungsformate über die aktive Sprache.
- Öffentliche Events, Sportarten und Sponsoren verwenden jetzt lokalisierte SEO-/Filter-/Leerzustände, regionale Datumsformate, zugängliche Feldbeschriftungen und eine konsistente Partner-/Sponsorensprache in DE/EN/FR/AR.
- Die öffentliche Zertifikatsprüfung und der Kursdetail-/Lernraum-Flow übersetzen zentrale Status-, Inhalts-, Formular- und Abschlussaktionen; Kursgeld und Zertifikatsdaten folgen dem aktiven Sprach-/Länderformat.
- Ernährung nutzt für Wasser, KI-Fotoanalyse und Fortschrittsfeedback semantische AIRMIUS-Akzentfarben; globale Layout-Feedbacks, Benachrichtigungszeiten sowie Event-, Commerce-, Vereins- und Teamdetails folgen der aktiven Sprache und Region.
- Gamification und Werbeagentur übersetzen jetzt auch Datenkarten, Pakete, XP-Demo, Datenschutz-/Jugendschutz-Hinweise und Anfrageformular inklusive vorausgefüllter Anfrageziele in DE/EN/FR/AR.
- Authentifizierte Benachrichtigungen, Meine-Kurse, Badges, Arbeitsbereiche und Überweisungs-Checkout verwenden lokalisierte Status-, Datums-, Formular- und Screenreader-Texte.
- Blog-Listen und -Detailseiten, Eltern-Login, Berechtigungsfehler, Profil-/Rechts-/Datenschutz-/AGB-Einstiege nutzen jetzt ebenfalls aktive Übersetzungen und regionale Blog-Datumsformate; dynamische Fachinhalte bleiben serverseitig unverändert.
- Trainingsprotokoll-Details und die Nutzerverwaltung verwenden jetzt eigene DE/EN/FR/AR-Schlüssel für Status, Plan-Ist-Vergleich, Messwerte, Feedback, Sperrfristen und Accessibility-Labels; dynamische Trainings- und Kontodaten bleiben serverseitig unverändert.
- Der eingeloggte Trainingsarbeitsbereich lokalisiert Sportarten, Plan-/KI-Schritte, Einheitenstatus, Datums-/Zeit-/Distanzformate und Trainingsdokumentation zentral; Log-Erfassung, Plan-Wizard und KI-Vorschau verwenden dadurch dieselben DE/EN/FR/AR-Begriffe.
- Dateien und Chat haben jetzt lokalisierte Lade-, Leer-, Fehler-, Upload-, Freigabe-, Such- und Nachrichtenstatus; Aktionen bleiben auch auf kleinen Flächen verständlich und der Dateimanager zeigt keine irreführenden Speicherwerte während des Ladens.
- Commerce nutzt für Shop-Tabs, Checkout-Anbieter, Bestell-/Versand-/Erstattungs-/Auszahlungsstatus sowie Geld- und Datumsformate die aktive Sprache und Region; die Oberfläche führt Käufe, Abos, Warenkorb und Rechnungen mit klaren Zuständen zusammen.
- Nutzerverwaltung und Lernstudio übernehmen zentrale Status-, Inaktivitäts-, Kurskategorie-, Niveau-, Lektionstyp- und Upload-Fehlertexte aus DE/EN/FR/AR; Admin-Datumswerte verwenden das regionale Format der aktiven Sprache.
- Der Freunde-Bereich übersetzt Einladungen, Annahme/Ablehnung, Meldung, Entfernen, Leerzustände und zugängliche Aktionslabels zentral in DE/EN/FR/AR.
- Vereins-/Team-Aktionen verwenden jetzt einen gemeinsamen DE/EN/FR/AR-Katalog für Registrierung, Einladungen, Beitrittsanfragen, Rollen, Sponsoren, Jobs und destruktive Bestätigungen; Wizard-Schritte und Bearbeitungstabs reagieren auf Sprachwechsel.
- Vereins- und Teamprofile verwenden nun regionale Datums-/Geldformate, sprachwechselreaktive Rollen- und Strafkassenstatus sowie lokalisierte Beitritts-, Austritts- und Buchungsbestätigungen; Trainingsstatistik, Mitgliedschaftsantrag und sichtbare Leerzustände bleiben dabei theme-aware.
- Die Admin-Vereinsprüfung und das Provider-Kosten-Dashboard verwenden jetzt aktive Sprache und Region für Status, Hinweise, Zahlen und Geldwerte; Warnchips nutzen semantische Theme-Tokens statt fester Schwarz-/Weiß-Kontraste.
- Betriebskosten und Verträge verwenden jetzt regionale Datums-/Geldformate, lokalisierte Fristen-/Status-/Löschzustände, sichere Pagination ohne `v-html` und getrennte Summen je Währung statt irreführender EUR-Mischsummen.
- Die Gamification-Regelverwaltung nutzt jetzt regionale Zahlenformate, lokalisierte Rollen-/Kennzahlen-/Risikozustände und semantische Warnfarben für alle vier unterstützten Sprachen.
- Die Media-Guidelines-Zentrale lokalisiert Suche, Upload-Editor, Login-Slider, Feedback, Tabellen- und Leerzustände; Entfernungsaktionen verwenden semantische Theme-Farben.
- Die Mail-Zentrale lokalisiert Versand-/Queue-/Audit-Zustände, formatiert Zeitpunkte regional, schützt Pagination vor unkontrolliertem HTML und verwendet semantische Statusfarben; Mail-Geheimnisse bleiben 2FA-/Audit-geschützt.
- Die Systemsettings lokalisieren KI-Tokenstatus und Warnmeldungen, formatieren Token-Abläufe regional und behalten die 2FA-/Berechtigungsgrenzen für sensible Einstellungen bei.
- Die Sportartenverwaltung lokalisiert Filter, Nutzungskennzahlen und Erstell-/Löschzustände, formatiert Zahlen regional und macht die nicht rückgängig machbare Löschung explizit.
- Die Blog-Kategorienverwaltung lokalisiert Formulare, Tabellen- und Leerzustände, formatiert Kennzahlen regional und rendert Pagination-Labels ohne `v-html`.
- Fahrgemeinschaften zeigen Fahrer-/Anfrage-/Sitzplatz- und Datenschutzstatus lokalisiert, formatieren Abfahrtsdaten regional und machen private Treffpunkt-/Kontaktdaten erst nach Freigabe sichtbar; der Outfit-Checkout verwendet dieselben regionalen Geld-/Datumsformate und verständliche Liefer-/Problemstatus.
- Die Admin-Moderation bestätigt das dauerhafte Entfernen gemeldeter Inhalte, übermittelt die Entfernung explizit an den Server und zeigt Meldungs-, Flag- und Warnzustände regional formatiert in DE/EN/FR/AR.
- Events verwenden für Freikontingent, Wizard-Schritte, Validierungsfehler, Kalenderwochentage und Datumsformate nun aktive Übersetzungen statt fest verdrahteter deutscher Texte.
- Die Zahlungszentrale nutzt lokalisierte Status-/Zahlungsarten, sichere Löschbestätigung sowie verständliche Leer- und Erfassungszustände in DE/EN/FR/AR.
- Dashboard, Ernährung, Gast-Marketplace, Abo-Rechnungen, Vereinsfinanzen und Admin-Commerce formatieren Geld, Zahlen und Datumswerte jetzt nach der aktiven Sprache; zentrale Fehler-/Leerzustände und Statusbezeichnungen bleiben in DE/EN/FR/AR verständlich.
- Die Subscription-Administration übersetzt Zielgruppen, Abo-Status, unbegrenzte Limits und Banküberweisungszustände; Plan-Deaktivierungen und offene Zahlungen verwenden lokalisierte Bestätigungsdialoge.
- `npm run build`: erfolgreich, 976 Module transformiert.
- Web-Sprachdateien: 964 deutsche Referenzschluessel; EN, FR und AR nach Reparatur mit exakt gleicher Schluesselmenge. Zusaetzlich besitzen alle vier Kataloge 3.540 identische `auto`-UI-Ausgangsschluessel.
- Arabisch vor der Reparatur: 935 von 962 Top-Level-Eintraegen waren betroffen. Nach lokaler Wiederherstellung: 964 Referenzschluessel, 61.070 arabische Zeichen, keine Fragezeichenfolgen/Ersatzzeichen und keine Platzhalterabweichungen.
- Statisches Vue-UI-Inventar: 3.003 unterschiedliche sichtbare Texte aus Seiten, Layouts, Komponenten und statischen Textattributen; nach der Umsetzung fehlen davon in EN, FR und AR jeweils 0.
- Flutter-Realgeraet-Evidence fuer Android/iOS ist laut Release-Dokumentation offen.
- Juristischer Sign-off ist laut `docs/LEGAL_REVIEW_PACK.md` offen.

## Zielgruppenbewertung

### Sportler: bedingt beta-tauglich

Vorhanden sind Registrierung, Profil/Sport-CV, Feed, Freunde, Chat, Events, Training, Dateien, Benachrichtigungen, Motivation/Gamification-Grundlagen und Mitgliedschaftsanfragen. Das ist eine brauchbare Pilotbasis.

Vor Verkauf fehlen vor allem ein klarer taeglicher Kernnutzen, verlaessliche native Push-Zustellung, vollstaendige produktive Fitnessanbieter-Synchronisation und belastbare Leistungsentwicklung. Die mobile Sportintegrationsseite und der sichere normalisierte Importvertrag sind nun API-gebunden; OAuth-/Partnerfreigaben und echte native Health-Bridge-Abnahmen bleiben externe Betriebs- und Realgeraete-Gates. Der Minderjaehrigen-/Elternflow ist als geschuetzter, getesteter Consent- und sicherer Aggregat-Überblick umgesetzt; Realgeraete-, Jugendschutz- und Rechtsabnahme bleiben offen. Der Funktionsumfang ist breit, aber das Nutzenversprechen fuer den einzelnen Sportler noch nicht scharf genug.

### Trainer: bedingt beta-tauglich

Vorhanden sind Teamverwaltung, Trainingsplaene/-logs, Anwesenheit, Events, Kommunikation und Rollen. Damit kann ein Trainer-Pilot arbeiten.

Verkaufsentscheidend fehlen weiterhin produktive Fitnessanbieter-Synchronisation und ein vollständig nachgewiesener Wochenworkflow. Trainingsvorlagen, Übungsbibliothek, Trainer-zu-Sportler-Feedback, Belastungsansichten, Verletzungs-/Pausenstatus, QR-Check-in sowie der normalisierte Sportdatenimport sind inzwischen API-gebunden umgesetzt; diese Bereiche brauchen vor dem Verkauf weiterhin Realgeräte-, Provider- und Fachabnahme.

### Vereine: beste Produktbasis, aber noch nicht produktionsreif

Vorhanden sind Verein/Teams, Rollen, Mitgliederantraege, Import, externe Mitglieder, Rechnungen/Zahlungsstatus, SEPA-/Bankabgleich-Grundlagen, Dateien, Events, Sponsoren, Audit-Grundlagen und Plan-Gates. Hier liegt der staerkste kurzfristige Kundennutzen.

Vor breitem Verkauf fehlen bzw. brauchen Abschluss: Familien-/Beitragsgruppen und Ermaessigungen, vollstaendiger Ein-/Austritts-Lifecycle, rechtliche Freigabe digitaler Unterschriften, wiederkehrende Provider-Abos, automatische Rueckerstattungen/Auszahlungen, echte Push-/Digest-Kommunikation sowie verifizierte DATEV-/SEPA-Produktionsablaeufe. Einwilligungen werden im Mitgliedsantrag jetzt versioniert und mit optionaler Namensbestätigung sowie Audit-Metadaten gespeichert; die rechtliche Wirksamkeit bleibt bis zur Fachabnahme offen. Migration, Support und Datenqualitaet muessen als Onboardingprozess operationalisiert werden.

## Lokalisierung und Rueckuebersetzung

Die deutsche Datei `resources/js/lang/de.json` ist die alleinige fachliche Referenz. Der geforderte Prozess ist:

1. Deutsch nach EN/FR/AR uebersetzen.
2. Platzhalter, Zahlen, URLs, Marken- und Sportbegriffe unveraendert pruefen.
3. Jede Zieluebersetzung unabhaengig nach Deutsch rueckuebersetzen.
4. Bedeutung gegen den deutschen Originaltext vergleichen; keine Ziel-zu-Ziel-Uebersetzung verwenden.
5. Danach manuelle Sichtpruefung im echten UI, bei Arabisch zusaetzlich RTL, Zahlen, Icons, Tabellen, Dialoge und abgeschnittene Texte.

Aktueller Befund nach der Umsetzung:

- Schluesselparitaet ist repariert: DE/EN/FR/AR jeweils 964 Schluessel.
- Das automatische sichtbare UI-Inventar hat in DE/EN/FR/AR jeweils 3.540 Schluessel; das statische Audit findet unter 3.003 aktuell sichtbaren Vue-Texten jeweils 0 fehlende EN-/FR-/AR-Uebersetzungen.
- Die defekten franzoesischen und arabischen Mojibake-Schluessel wurden auf die deutschen Originalschluessel zurueckgefuehrt.
- Platzhalter-, HTML-Entitaets-, E-Mail-/Pfad- und Korruptionspruefungen sind gruen. Arabisch enthaelt keine `????`- oder Ersatzzeichenfolgen mehr; die Sportartenliste ist ebenfalls repariert.
- Ein unabhaengiger lokaler Rueckuebersetzungsversuch wurde verworfen, weil das einzige vorhandene 4B-Modell Zieltexte trotz deutscher Zielanweisung teilweise unveraendert kopierte. Dieser Versuch ist kein gueltiger Semantiknachweis. Google Translate war trotz Einwilligung durch die Ausfuehrungsumgebung gesperrt.
- Semantische Vollstaendigkeit bleibt deshalb trotz vollstaendiger technischer Abdeckung nicht abschliessend bewiesen. Die gefundenen Ausreisser (unter anderem `Entfolgen`, `Anrede`, `XP`, SEPA-Begriffe, E-Mail-Beispiele und Bildpfade) wurden gezielt korrigiert; eine fachliche Muttersprachlerabnahme und echte RTL-Sichtpruefung bleiben erforderlich.
- Dynamisch in JavaScript zusammengesetzte Texte, Laufzeitdaten vom Backend sowie responsives Abschneiden koennen durch den statischen Vue-Audit nicht vollstaendig bewiesen werden.

## Priorisierte fehlende Funktionen und Abschlussarbeiten

### P0 – vor jeder oeffentlichen Beta

- Arabische Fachstichprobe per Rueckuebersetzung protokollieren und RTL-Screens manuell abnehmen (technische Textreparatur ist abgeschlossen).
- Den neuen sichtbaren UI-Inventar-Audit als verpflichtendes CI-Gate ausfuehren und dynamisch zusammengesetzte Laufzeittexte weiter in explizite i18n-Schluessel ueberfuehren.
- Android-/iOS-Realgeraetetests fuer Login, Push, Upload, Deep Links, Chat und Hintergrundverhalten abschliessen.
- Echte Mail-, Queue-, Reverb-, Storage-, Backup-/Restore- und Monitoring-Probe auf Staging.
- Stripe/PayPal-Sandbox-End-to-End inklusive Webhook, Abbruch, Doppelzustellung, Rueckerstattung und Rechnungsabgleich.
- Legal-/Datenschutz-/Jugendschutz-Sign-off und reale Pflichtangaben.
- Manueller rollenbasierter Smoke-Test mit Sportler, Trainer, Verein-Admin und Elternteil.

### P1 – fuer ein verkaufbares Vereins-/Trainerprodukt

- [x] Wiederkehrende Event-/Trainingstermine sind im mobilen Erstellungsdialog mit Enddatum, Wochentagen und serverseitiger Entitlement-Prüfung verfügbar; Trainingsvorlagen können API-gebunden gespeichert und mit neuen Zeiträumen instanziiert werden, während Übungsbibliothek, Entwicklungsfeedback und Belastungsanalyse ebenfalls API-gebunden verfügbar sind.
- [x] Team-Strafkasse ist im mobilen Teamdetail API-gebunden: berechtigte Teamverantwortliche verwalten Regeln und Gebühren, Mitglieder sehen nur eigene Einträge.
- [x] Der Team-Kalender lädt kommende Termine über die geschützte Events-API, zeigt Datum/Ort responsiv und öffnet die vorhandene Event-Detailansicht.
- [x] Teamdateien sind im Teamdetail an den geschützten Datei-Workspace gebunden und öffnen die serverseitige Vorschau sowie den vollständigen Team-Dateimanager.
- [x] Der Team-Chat lädt die teambezogene Konversation API-gebunden, zeigt Statusdaten und öffnet den geschützten Chat-Detailflow; die Eröffnung bleibt serverseitig mitgliedschaftsgeprüft.
- [x] Die globale Dateifreigabe im Dateimanager öffnet eine echte Dateiauswahl und erzeugt Links ausschließlich über den geschützten API-Endpunkt.
- [x] Die Benachrichtigungszentrale unterstützt „Alle als gelesen markieren“ über den geschützten API-Endpunkt und behält lokale/Einzelstatus-Fehlerzustände verständlich bei.
- [x] Event-Abstimmungen sind über geschützte API-Routen vollständig nutzbar: Verantwortliche erstellen Fragen mit mehreren Optionen, sichtbare Mitglieder stimmen ab und sehen Ergebnis sowie eigenen Status; private Events prüfen die Sichtbarkeit serverseitig.
- [x] Vereinsumfragen sind im geschützten Vereinsprofil verfügbar: Verantwortliche wählen alle aktiven Mitglieder oder ein Team als Zielgruppe, können ein prozentuales Quorum setzen und Umfragen schließen; Mitglieder sehen nur freigegebene Umfragen und die anonymisierte Auswertung.
- [x] Vereinsankündigungen sind im geschützten Vereinsprofil verfügbar: Verantwortliche veröffentlichen Nachrichten für alle aktiven Mitglieder oder ein Team; Mitglieder erhalten eine In-App-/Push-Benachrichtigung und können die Lesebestätigung direkt speichern.
- [x] Der mobile Vereinsbereich zeigt Mitgliedschaftsstatus und bindet Pausenanfrage sowie einen terminierten, managerbestätigten Austrittsantrag an die serverseitigen Prüfungen; offene Rechnungen werden blockiert, der Scheduler setzt abgelaufene Mitgliedschaften auf „ehemalig“ und entfernt Teamzugriffe.
- [x] Die digitale Mitgliedskarte ist API-gebunden: aktive Mitglieder erhalten kurzlebige, rotierbare Codes mit Minimaldaten, sehen daraus einen QR-Code und können ihn vor Ort vorzeigen; berechtigte Vereinsverantwortliche scannen oder prüfen die Karte und speichern die Event-Anwesenheit mit Audit-Methode.
- [x] Die Trainings-Übungsbibliothek ist API-gebunden: persönliche, Team- und vereinsbezogene Übungen lassen sich berechtigt anlegen, suchen, bearbeiten, entfernen und als unveränderliche Momentaufnahme in Trainingspläne übernehmen; bestehende Pläne bleiben bei späteren Bibliotheksänderungen stabil.
- [x] Die Trainingsfortschrittsansicht liefert für Sportler und berechtigte Trainer eine datensparsame Zeitraumübersicht mit Einheiten, Umfang, RPE-/Schmerzwerten, wöchentlicher Belastung und Warnsignalen; private Trainer-/Teamdaten werden serverseitig ausgeschlossen.
- [x] Der Trainingsstatus ist API-gebunden: Sportler können Verfügbarkeit, eingeschränkte Belastbarkeit, Krankheit, Verletzung/Schonung und Pausen mit Zeitraum und Sichtbarkeit verwalten; Trainer sehen nur freigegebene Statusdaten ohne persönliche Hinweise, Team- und Fremdzugriffe werden serverseitig begrenzt.
- [x] Der Minderjährigen-/Elternflow ist API- und UI-gebunden: verknüpfte Guardians verwalten Zustimmung und Widerruf, und der neue Kinderüberblick zeigt nach Freigabe ausschließlich kommende Termine sowie 28-Tage-Trainingssummen. Private Notizen, Nachrichten, Straßenadressen und Detailprofile werden serverseitig nicht ausgeliefert; Consent-, Fremdzugriffs- und Widget-Tests decken die Schutzgrenzen ab. Realgeräte-, Jugendschutz- und Rechtsabnahme bleiben P0.
- [x] Sportintegrationen sind im mobilen MVP API-gebunden und als eigener, übersetzter Modul-/Drawer-Einstieg erreichbar: Providerstatus, verschlüsselte Kontoverbindungen, Besitzerprüfung, Synchronisationsstatus sowie ein idempotenter normalisierter Aktivitätsimport mit optionalen GPS-Samples und GPX-Vertragsdaten sind erreichbar. Produktive OAuth-Clientdaten, Garmin-/Mi-Fitness-Partnerfreigaben und native HealthKit-/Health-Connect-Abnahme bleiben externe Betriebs- und Realgeräte-Gates.
- [x] Die globale Suche liefert nun sichtbarkeitsgeprüfte Treffer für Personen, Vereine, Teams, Events, Kurse, Produkte und Dateien; die mobile Oberfläche filtert diese Typen lokalisiert und öffnet den passenden Detail- oder Arbeitsbereich.
- [x] Geschützte Event-Deep-Links prüfen zuerst die Sitzung, laden danach das konkrete Event über die Events-API und öffnen direkt die Detailseite; Gäste sehen ein Auth-Gate, fehlende Berechtigung eine lokale, lokalisierte Wiederholen-Ansicht.
- [x] Geschützte Feed-Deep-Links prüfen zuerst die Sitzung, laden danach den konkreten Beitrag über `/api/v1/posts/{post}` und öffnen die echte Detailansicht; die Post-Policy schützt private und moderierte Inhalte vor URL-basiertem Zugriff.
- [x] Mitgliedschafts-Deep-Links laden den konkreten Antrag über seine ID, lösen den Vereinskontext auf und zeigen anschließend nur den exakt passenden Vereinsdatensatz; kein stiller Fallback auf einen anderen Antrag.
- [x] Team-Einladungslinks laden über einen geschützten Token die konkrete Einladung; Vorschau, Annahme und Ablehnung prüfen eingeladene E-Mail, offenen Status und Vereinslimit serverseitig und invalidieren den Token nach der Antwort.
- [x] Freundschafts- und Vereins-Einladungslinks laden ihre konkrete, geschützte Vorschau; E-Mail-Bindung, Ablauf/Status und einmalige Annahme oder Ablehnung werden serverseitig geprüft, statt einen allgemeinen Einladungs-Fallback zu öffnen.
- [x] Einzelne Nachrichten-Deep-Links laden die konkrete, geschützte Nachricht und öffnen anschließend die zugehörige Konversation; Teilnehmer-, Gruppenbeitritts- und persönliche Ausblendregeln werden serverseitig geprüft.
- [x] Profil-Deep-Links mit Benutzer-ID laden den privacy-gefilterten Sportprofil-Datensatz und öffnen die echte Profilseite; private Profile bleiben datensparsam.
- [x] Das Vereinsmanagement bietet einen lokalisierten Rollen-Editor für Mitglieder; Rollenwechsel werden serverseitig mit Akteur, vorheriger/neuer Rolle und Zeitstempel im Audit-Tab protokolliert.
- [x] Beitragsregeln unterstützen im MVP jetzt Standard-, Familien- und Sonderbeiträge sowie prozentuale oder feste Rabatte; die effektive Antragsvorschau speichert Grundbetrag, Rabatt und Endbetrag nachvollziehbar. Haushalts-/Familiengruppen können über einen vereinsbezogenen, sicheren Schlüssel mehreren Mitgliedern zugeordnet werden; ab zwei aktiven Mitgliedern berechnet der Server Beitrag und Intervall für die gesamte Gruppe neu. Terminierte Austrittsanträge mit Managerfreigabe, Schuldenprüfung und Scheduler sind im MVP umgesetzt; versionierte digitale Zustimmung mit optionaler Namensbestätigung und Dokument-Hash ist ebenfalls umgesetzt. Rechtlicher Sign-off sowie vollständige Kündigungs-/Signaturworkflows bleiben als externe Fach-/Betriebsgates offen.
- [x] Serverseitige Benachrichtigungskanäle, Ruhezeiten, Push-Filter und ein täglicher E-Mail-Digest für ungelesene In-App-Meldungen sind im MVP umgesetzt; produktive SMTP-/Mail-Provider-Abnahme bleibt als Betriebs-Gate offen. Ankündigungen, Lesebestätigungen sowie vereinsweite Umfragen mit Zielgruppen, Quorum und Auswertung sind im mobilen MVP umgesetzt.
- [x] Vereins-Onboarding ist im MVP teilweise verbessert: geschützter Import-Preview-Endpunkt, normalisierte Zeilen, vorhandene Konten/Externe, Dubletten-/Fehlerhinweise und eine optionale Spaltenzuordnung mit Bestätigung vor dem Schreiben sind umgesetzt. Der persönliche App-Einstieg zeigt zusätzlich die serverseitige Konto-Checkliste und verlinkt in den echten Fortschrittsbereich; externe Mitglieder können im Workspace bearbeitet, eingeladen und entfernt werden. Testmigration und persönliche Begleitung bleiben offen.
- [x] Vereinsbezogene Berechtigungen sind im MVP serverseitig durchgesetzt: Rollen erhalten sichere Standardrechte, einzelne Rechte können pro Mitglied und Verein explizit gewährt oder entzogen werden, Finanzzugriffe sind von der Mitgliederverwaltung getrennt und Änderungen landen im Audit-Log. Der mobile Dialog ist in DE/EN/FR/AR lokalisiert; fachliche Rollenabnahme und Realgeräte-Smoke-Test bleiben P0.
- [x] Das eingeloggte Support-Hilfecenter erstellt API-gebundene Tickets, zeigt den eigenen Verlauf und übersetzt Statuswerte; Support-Mitarbeiter verwalten Tickets zusätzlich über eine berechtigungsgeprüfte SLA-/Eskalationsansicht mit Zuständigkeit, Priorität, Status, Frist und interner Notiz. SLA-Reporting über mehrere Mandanten und In-App-Onboarding bleiben als Ausbau offen.

### P2 – Wachstum und Skalierung

- Oeffentliche SEO-Seiten fuer Vereine, Sportarten, Events und Staedte, SSR und strukturierte Daten.
- Verifiziertes Anbieter-/Sponsor-Onboarding, Steuerdaten, Auszahlungen und erweitertes Ads-Reporting.
- Finale Google-Fit-/Garmin-/Mi-Fitness-Anbindungen.
- Produktanalytics mit Consent: Aktivierung, Retention, Team-/Vereinsnutzung, Conversion und Abwanderungsgruende.

## Empfohlene Markteintrittspositionierung

Nicht gleichzeitig alle Module und Zielgruppen verkaufen. Der glaubwuerdigste Einstieg ist **Vereinsbetrieb plus Traineralltag** fuer wenige Pilotvereine; Sportler erhalten die kostenlose Begleit-App.

- Startsegment: kleine und mittlere Vereine mit Excel-Mitgliederlisten und hohem Kommunikationsaufwand.
- Kaufversprechen: weniger Verwaltungszeit, klare Beitraege/Zahlungsstaende, Teamkommunikation und Trainingsorganisation an einem Ort.
- Pilotangebot: Datenmigration, Einrichtung, Schulung und 6–8 Wochen begleitete Einfuehrung.
- Erfolgsnachweise: eingesparte Verwaltungsstunden, aktive Mitgliederquote, Trainingsrueckmeldungen, offene Zahlungen und Supportaufwand.
- Erst nach wiederholbar erfolgreichem Onboarding Preisplaene, Marketplace, Ads und weitere Zielgruppen offensiv skalieren.

## Go/No-Go

- Geschlossene Pilot-Beta mit deutschsprachigen Vereinen: **Go mit Auflagen**.
- Bezahlte breite Beta in DE: **No-Go bis P0 abgeschlossen**.
- Mehrsprachiger Launch inklusive Arabisch: **No-Go bis fachliche Rueckuebersetzungs- und RTL-Sichtpruefung protokolliert sind**.
- App-Store-/Play-Store-Public-Launch: **No-Go**, solange Realgeraete-, Store- und Legal-Evidence fehlen.

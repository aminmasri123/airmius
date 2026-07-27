# Airmius Mobile Store Release Notes

Use this file to prepare release notes for Play Console, App Store Connect, TestFlight and internal release communication.

## Release candidate

- App: Airmius Mobile
- Version: `1.0.15+16` (working-tree release candidate; pragmatic navigation, drink quick entry and camera-based meal photo estimates)
- Android application ID: `com.airmius.app`
- iOS bundle ID: `com.airmius.app`
- API environment: `https://airmius.com`
- New in 1.0.15+16: Main navigation now focuses on Training, Teams, Feed, Nutrition and Profile. Nutrition asks whether to open Nutrition or Drink tracking, Drink tracking supports quick water entries, and meal photo estimates can use either the camera or gallery.
- Fresh release build is available for Play Console upload; artifact details below match the freshly built 1.0.15+16 AAB.
- Release owner:
- Release date:
- Previous local release AAB (before the final UI-polish patch; do not upload as the final build): `build/app/outputs/bundle/release/app-release.aab` (25.07.2026 16:56, 70.2 MiB / 73,650,123 bytes)
- Previous AAB SHA-256: `2ff66fff6496fe46d2cee30f5c771a2ee8312beccf6a714898d2887715f92af5`
- Fresh final AAB: `build/app/outputs/bundle/release/app-release.aab` (27.07.2026 14:24 CEST, 84,938,241 bytes; Version 1.0.15+16).
- Fresh AAB SHA-256: `e0d5b421ccab683e252d4ac022d9aad7f676ff2b8cd6481336b9bc4be82398b5`.
- Source verification: `flutter analyze` (no issues).
- Fresh artifact verification: `jarsigner -verify` exited successfully (`jar verified`); the self-signed upload-key and JarInputStream warnings are informational for this Android App Bundle.
- Backend verification: `php artisan test --filter=MobileAuthSecurityTest` (5 passed, 45 assertions); `composer validate --strict` passed. Composer's online vulnerability audit remains an external-network gate.
- Hostinger deployment gate: the matching `bootstrap/app.php` change is live. The invalid Origin login probe returns HTTP 422 (`auth.failed`) and the preflight returns HTTP 204 with the expected CORS headers; a real account/device smoke test remains the final external check.

## Gebündelter Änderungsentwurf (Arbeitsstand)

Dieser Abschnitt beschreibt den aktuellen Sammel-Release. Das frische AAB ist gebaut und signiert; vor dem Play-Console-Upload bleiben die üblichen Store-, Review-, Datenschutz- und Realgeräte-Gates.

- Die produktive API-Adresse der Android-App verwendet jetzt `https://airmius.com`. Dadurch schlägt der Login nicht mehr wegen des nicht auflösbaren alten Hosts `app.airmius.com` fehl; bestehende Social-Login-Rückruflinks über den alten Host bleiben kompatibel.
- Auch Debug-Android-Builds verwenden ohne explizites `AIRMIUS_API_BASE_URL` den produktiven HTTPS-Origin statt `http://localhost`, das auf einem echten Gerät auf das Gerät selbst zeigen würde.
- Der versionierte Login-Endpunkt ist zusätzlich von der SPA-CSRF-Prüfung ausgenommen: Browser-Origin- und Flutter-Web-Anfragen werden nicht mehr vor der eigentlichen Zugangsdatenprüfung mit HTTP 419 abgebrochen; die Anmeldung bleibt durch Rate-Limit und Bearer-Token geschützt.
- Bei unterbrochener oder zu langsamer Verbindung liefert die App nun eine verständliche, lokalisierte Login-Fehlermeldung statt eines unklaren generischen Fehlers; Requests werden nach 20 Sekunden sauber abgebrochen.
- Die API-Konfiguration normalisiert zusätzlich alte Alias- und bereits versionierte Eingaben automatisch, damit weder `app.airmius.com` noch ein doppelter `/api/v1/api/v1`-Pfad die Anmeldung unterbrechen kann.
- Social-Login-Callbacks von Produktionsdomains akzeptieren ausschließlich HTTPS; HTTP-Token-Callbacks werden blockiert und durch Regressionstests abgesichert.
- Diagnose-, Datei- und Safety-Ansichten zeigen alte `/friends/...`-Quellen jetzt als kanonische `/api/v1/...`-Mobile-Routen; dadurch werden keine falschen Serverpfade mehr weitergegeben.
- Die Auth-Recovery-Seite öffnet Passwort-Reset und E-Mail-Verifizierung jetzt als echte API-Flows statt Demo-Dialogen; die Schnellaktionen, Panels und Switches folgen der aktiven Farbpalette und bleiben auf kleinen/hellen/RTL-Ansichten bedienbar.
- Die Sicherheitsübersicht ist jetzt aus dem erreichbaren Auth-Bereich „Konto & Sicherheit“ geöffnet; ihre Filter, Statusgruppen, Flows und Aktionen sind in Deutsch, Englisch, Französisch und Arabisch lokalisiert und für kleine Arabic-RTL-Ansichten getestet.
- Konto-Aktionen respektieren jetzt den angeforderten Einstiegsbereich: Passwort-, Sicherheits- und Kontolöschungs-Aktionen springen direkt zum passenden Abschnitt statt nur an den Seitenanfang.
- Die Web-Chat-Ansicht ist durchgehend in Deutsch, Englisch, Französisch und Arabisch nutzbar: Suche, Gruppenverwaltung, Einladungen, Meldungen, Reaktionen, Anhänge, Leerzustände und Screenreader-Beschriftungen folgen jetzt dem aktiven Sprachkatalog.
- Der Web-Dateimanager übernimmt Übersetzungen, semantische Theme-Farben und klare Tast-/Touch-Beschriftungen für Suche, Sortierung, Speicherstatus, Upload, Freigabe, Umbenennen, Löschen und Download.
- Shop-, Warenkorb- und Outfit-Übersichten verwenden lokalisierte Artikel-, Preis-, Zahlungs-, Leer- und Checkout-Texte sowie interpolierte Mengen, Länder und Anbieter in allen vier Sprachen.
- Das Learning-Studio nutzt für Kursanlage, Kursübersicht, Analytics, Publish-Check, Tabs, Kapitel und Lektionen lokalisierte Eingaben, Statuswerte, Aktionen und Leerzustände in allen vier Sprachen.
- Die Studio-Bereiche für Landingpage, Gutscheine, Quiz, Aufgaben, Teilnehmer und Fragen-Inbox sind ebenfalls vollständig lokalisiert und mit klaren Screenreader-Beschriftungen versehen.
- Externe Lern-, Medien-, Bewerbungs- und Rechtslinks öffnen neue Tabs jetzt mit `noopener noreferrer`, um das ursprüngliche Fenster gegen Tabnabbing abzusichern.
- Das Trainer-Cockpit ordnet seine Navigation auf kleinen Bildschirmen automatisch um: Tabs brechen lesbar um, während breite Ansichten kompakt horizontal bleiben.
- Der webbasierte Trainingsplan-Dialog lokalisiert jetzt auch Rhythmus, Ziel, Zeitraum, Freigabe, Sportlerauswahl und erste Einheit; die beschädigte arabische Darstellung wurde durch korrektes RTL-Arabisch ersetzt.

- Einheitliches, responsives Designsystem mit mehreren Farbpaletten, Hell/Dunkel/System-Modus und besser lesbaren Textstufen.
- Hauptbereiche übernehmen AppBar- und Oberflächenfarben jetzt aus der aktiven Palette, sodass Hell-, Dunkel- und Kontrastmodus auch beim Wechsel zwischen Seiten konsistent bleiben.
- Der Community-Feed verwendet nun ebenfalls dynamische Theme-Neutralfarben für Karten, Eingabefelder, Text, Metadaten und Rahmen; dadurch bleibt er in allen Farbpaletten lesbar.
- Die globale Suche nutzt aktive Palettefarben für Floating Action Button und Filter; Modul-Launcher und Moduldetailkarten übernehmen denselben Akzent statt eines festen Blauwerts.
- Die Verzeichnis-Suche startet ohne künstliche Demoanfrage, sucht nach kurzer Eingabepause automatisch, zeigt lokalisierte Lade-/Fehler-/Leerzustände und bleibt in allen vier Sprachen, RTL, großen Schriftstufen sowie den aktiven Farbpaletten bedienbar.
- Marketplace-Preise, Bewertungen, Verfügbarkeit, Warenkorb, Checkout, Bestellungen und Problemmeldungen verwenden jetzt ebenfalls aktive Theme-Farben; die französische Light-/Trail-Darstellung bleibt mit großer Schrift lesbar.
- Primäraktionen in den erreichbaren Vereins-, Team-, Trainings-, Datei-, Marketplace-, Commerce-, Sponsor-, Blog-, Freunde-, Fahrgemeinschafts- und Profilbereichen folgen ebenfalls dem aktiven Palette-Akzent statt eines festen Blauwerts.
- Team-, Freunde-, Badges-, Blog-, Sponsoren- und Ernährungsseiten verwenden neutrale Texte, Flächen und Ränder aus dem aktiven Theme statt fest verdrahteter Dark-Theme-Werte.
- Vereinsstatus, Rollen, Warnhinweise und Mitgliedschaftsaktionen verwenden jetzt ebenfalls die aktive Theme-Farbskala; der Vereinsworkflow bleibt in hellen, dunklen und kontrastreichen Paletten lesbar.
- Bedienungshilfen für große Schrift, RTL/Arabisch, größere Touch-Ziele, Tastaturfokus, Semantics und klarere Fehlermeldungen.
- Login mit lokaler E-Mail-/Passwortvalidierung und verständlichen, übersetzten Hinweisen.
- Authentifizierungsfehler für Login, Registrierung, 2FA, Netzwerk und Server werden jetzt in allen vier Sprachen lokalisiert; technische Exception-Texte werden nicht mehr direkt angezeigt.
- Der Konto-/Sicherheitsbereich ist vollständig lokalisiert; Passwort-Hilfe, 2FA, E-Mail-Verifizierung, Profilabschluss, Support und Kontolöschung öffnen echte Seiten statt Platzhalteraktionen.
- Datenschutz, E-Mail-Verifizierung und 2FA verwenden für Schutzstatus, Warnungen und Fehler jetzt adaptive Theme-Tokens; der 2FA-Workflow ist zusätzlich in arabischer RTL-Light-Ansicht mit großer Schrift geprüft.
- Das App-Onboarding ist vollständig mehrsprachig für Deutsch, Englisch, Französisch und Arabisch; Rollen, Startbereiche, Berechtigungsgründe und Folgeaktionen bleiben in allen hellen/dunklen Farbpaletten lesbar und sind mit großer RTL-Schrift geprüft.
- Der Onboarding-Abschluss speichert Rolle, Startbereich und die gewählten Berechtigungen jetzt lokal mit Zeitstempel, setzt den Abschlussstatus und verhindert ein Fortfahren ohne Datenschutz-/AGB-Zustimmung; Lade-, Erfolgs- und Fehlerzustände sind lokalisiert.
- Operations- und Release-Hub verwenden adaptive Oberflächen-, Akzent- und Statusfarben; die arabische Light-/Champion-Darstellung bleibt bei 1,35× Schrift bedienbar.
- Commerce/Seller, Training/Events, Sportprofile, Sportkarte, Lernbereich, Abos, Badges, Freunde, Chat, Dateien, Blog und Sponsoren verwenden semantische Theme-Tokens für Erfolg, Warnung und Fehler statt fester Dark-Theme-Farben.
- Arbeitsbereiche, Teams, Einstellungen, Guardian, Sportintegrationen und Vereinsdarstellung verwenden ebenfalls die aktive Palette; schwarze Schatten und Statusfarben bleiben in hellen, dunklen und kontrastreichen Modi lesbar.
- Navigation Menu, Offline Sync Cache, Input-/Keyboard-Accessibility und Localization/RTL-Format-Parity verwenden nun aktive Theme-Tokens für Hintergrund, Text, Statusfarben, Chips, Switches und Karten; eine arabische Light-/Trail-Großschriftprüfung ist enthalten.
- Brand-Theme-Token, Geräte-/Datenschutzberechtigungen, Session-/Token-Sicherheit und State-Feedback verwenden nun ebenfalls aktive Theme-Tokens; die vier Flows sind unter Arabisch, hellem Trail-Theme und 1,35× Schrift geprüft.
- Karten-/Standort-/Routen-, Medien-Upload-, Modal-/Sheet- und mobile Formular-Schema-Flows bleiben mit responsiven Chips, Fallbacks, Uploadstatus, Overlay-Regeln und Fehlermustern palette-aware und RTL-tauglich.
- Analytics-Dashboard, Subscription-/Entitlement-Gates und Push-/Deep-Link-Routing nutzen adaptive KPI-, Status-, Permission-, Badge- und Upgrade-Farben; die Accessibility-Gate deckt alle drei Seiten ab.
- Mobile Table Actions, Provider-/Webhook-Operations, Systemstatus/Incident-Kommunikation und gespeicherte Ansichten/Suchalarme übernehmen aktive Palette-Tokens und bleiben im RTL-Großschrift-Gate lesbar.
- Deep-Link-Routing, Einladungszugriffe und Consent-Signaturversionierung sind als geschützte, palette-aware Prüfseiten enthalten; End-to-End-Journeys, Auth-/Guardian-Gates, Helfer-/Ressourcenplanung, Exact Page Flows, Draft-Recovery und bereichsübergreifende Freigaben erweitern den geprüften Sammelumfang auf 36 Suiten.
- Der geprüfte Sammelumfang wurde auf 64 Suites erweitert: Sponsor-CRM, Store-/Release-Konfiguration, visuelles Fortschrittsaudit, Sitzungs-/Beschlusslog, Inventar/Ausleihe, Trainingsperiodisierung, Mitgliederfeedback, Richtlinien-Rollout, Laravel-Bindings, Vereinsumfragen, Service-/HTTP-Transport, Token-/Repository-/Datenmodell-Sicherheit, Onboarding-Berechtigungen, Kampagnenverwaltung, Rollen-/Workspace-Wechsel, Mitgliederservice, Marketplace-Erfüllung, Orte/Karten, Lernen/Zertifikate, Gesundheit/Vorfälle, Vereinsregeln, Moderation/Audit, Analytics/Reports, API-Loading-/Empty-/Error-/Offline-Zustände und Content Publishing. Alle neuen Suites sind palette-aware, für arabische RTL-Großschrift getestet und verschachtelte Scroll-/Chip-Layouts bleiben ohne Renderfehler.
- Coverage-/Action-Feedback-Seiten, Permission-Onboarding, Profil-Edit-Fehler und Mitgliedsanfrage-Status verwenden ebenfalls aktive Theme-/Semantikfarben; Coverage-Status und Untertitel sind in Deutsch, Englisch, Französisch und Arabisch lokalisiert.
- Sport-Apps & Gesundheitsdaten öffnen aus dem MVP-Drawer jetzt direkt das API-gebundene Integrationscenter; der öffentliche Marketplace beschreibt Gast-Checkout, Login-Bestellungen und Anbieterangebote nun als verfügbar und lokalisiert diese Aussage in DE/EN/FR/AR.
- Die UI-Coverage-Prüffläche ist jetzt in DE/EN/FR/AR vollständig beschriftet, zählt Module/Ops dynamisch und zeigt API-Verknüpfung bzw. Prüfstatus statt eines veralteten „kommt später“-Hinweises.
- Die Web-Einstellungen beschreiben Google-Fit-/Strava-Synchronisation nun als verfügbare sichere Sync-Funktion; veraltete „später“-Hinweise wurden in DE/EN/FR/AR entfernt.
- Die KI-Trainingsplanung beschreibt die automatische Zuordnung von Einheitentypen jetzt als aktive Funktion und bleibt in allen vier Web-Sprachen konsistent.
- Die mobile Sport-Integrations-API liefert jetzt lokale Übersetzungsschlüssel für Anbieter- und Kontostatus; Strava wird nicht mehr fälschlich als „vorbereitet“ beschrieben.
- Die öffentliche Preis-Seite nutzt Übersetzungen auch für Zielgruppen, Pläne, Checkout-Texte und dynamische Preise; Währungen werden nach der gewählten Sprache formatiert.
- Öffentliche Events, Sportarten und Sponsoren verwenden jetzt lokalisierte SEO-/Filter-/Leerzustände, regionale Datumsformate, zugängliche Feldbeschriftungen und eine konsistente Partner-/Sponsorensprache in DE/EN/FR/AR.
- Die öffentliche Zertifikatsprüfung und der Kursdetail-/Lernraum-Flow übersetzen zentrale Status-, Inhalts-, Formular- und Abschlussaktionen; Kursgeld und Zertifikatsdaten folgen dem aktiven Sprach-/Länderformat.
- Gamification und Werbeagentur übersetzen jetzt auch Datenkarten, Pakete, XP-Demo, Datenschutz-/Jugendschutz-Hinweise und Anfrageformular inklusive vorausgefüllter Anfrageziele in DE/EN/FR/AR.
- Authentifizierte Benachrichtigungen, Meine-Kurse, Badges, Arbeitsbereiche und Überweisungs-Checkout verwenden lokalisierte Status-, Datums-, Formular- und Screenreader-Texte.
- Blog-Listen und -Detailseiten, Eltern-Login, Berechtigungsfehler, Profil-/Rechts-/Datenschutz-/AGB-Einstiege nutzen jetzt ebenfalls aktive Übersetzungen und regionale Blog-Datumsformate; dynamische Fachinhalte bleiben serverseitig unverändert.
- Trainingsprotokoll-Details und die Nutzerverwaltung verwenden jetzt eigene DE/EN/FR/AR-Schlüssel für Status, Plan-Ist-Vergleich, Messwerte, Feedback, Sperrfristen und Accessibility-Labels; dynamische Trainings- und Kontodaten bleiben serverseitig unverändert.
- Verfügbarkeit/Abwesenheit, rollenbasierte Home-Widgets, Laravel-API-Mapping, Store-Release-Assets, öffentliche Vereinsprofil-Vorschau, Job-Queue-Monitor und Audit-Timeline nutzen ebenfalls aktive Farbtoken; ein arabischer 1,35×-RTL-Overflow im Kontaktpanel wurde durch responsive Textführung behoben.
- Die Registrierung stapelt Adressfelder auf kleinen Displays und verwendet ein expandierendes Geschlechts-Dropdown; dadurch bleibt der arabische RTL-Fluss auch bei 1,25× Schrift ohne Overflow bedienbar.
- Der Outfit-Abonnement-Checkout stapelt Land/PLZ und Straße/Hausnummer auf schmalen Displays; damit bleibt die kostenpflichtige Anmeldung auch mit großer arabischer RTL-Schrift gut lesbar.
- Profil- und Kontoverwaltung mit Profilbild, Passwortwechsel, Sitzungsverwaltung sowie geschütztem Konto-Löschablauf.
- E-Mail-Verifizierung, Zwei-Faktor-Sicherheit, Wiederherstellungscodes und sichere Authentifizierungs-Gates.
- Übersetzungsabdeckung für Deutsch, Englisch, Französisch und Arabisch mit neutralem Fallback bei fehlenden Einträgen.
- Die sichtbare Modulnavigation sowie zentrale Feed-, Such-, Status-, Datei- und Mitgliedschaftstexte sind auch auf Arabisch vollständig lesbar; französische Modulnamen verwenden korrekte Akzente.
- Maturity-/Sicherheitsübersicht und Medienrichtlinien sind mit echten API-Daten bzw. echten Zielseiten verbunden und für arabische RTL-Großschrift getestet.
- Der Profil-Vervollständigungs-Gate für neue Konten ist jetzt in allen vier Sprachen verfügbar und übernimmt helle, dunkle sowie kontrastreiche Paletten einschließlich Minderjährigen-/Erziehungsberechtigten-Hinweisen.
- Externe Zahlungs-, Beleg- und Marketplace-Links werden vor dem Öffnen auf sichere HTTP(S)-Schemas, Host und fehlende eingebettete Zugangsdaten geprüft.
- Auch Zahlungs-, Lern- und Rechtslinks verwenden jetzt dieselbe zentrale URL-Prüfung und blockieren eingebettete Zugangsdaten sowie ungültige Schemes.
- Bilder, Avatare, Vereinslogos, Galerie- und Videomedien aus API-Antworten werden ebenfalls normalisiert und nur über erlaubte HTTP(S)-Quellen geladen; fehlerhafte Medien erhalten verständliche Fallbacks.
- Deep-Link-Vorschauen zeigen Status-, Event-, Nachrichten- und Wiederholungs-Hinweise jetzt ebenfalls lokalisiert in Deutsch, Englisch, Französisch und Arabisch.
- Geschützte Event-Deep-Links laden nach der Sitzungsprüfung direkt die konkrete Eventdetailseite aus der API; Gäste erhalten ein Auth-Gate und bei fehlenden Rechten eine lokale Wiederholen-Ansicht statt einer allgemeinen Eventliste.
- Geschützte Feed-Deep-Links laden nach der Sitzungsprüfung den konkreten Beitrag aus der API und öffnen die echte Detailansicht; Sichtbarkeit und Moderationsstatus werden serverseitig geprüft, statt private Beiträge aus der URL zu übernehmen.
- Geschützte Chat- und Benachrichtigungs-Deep-Links laden vor dem Öffnen Titel, Zugriff und Inhalt aus der API; ungültige oder fremde IDs führen zu einer lokalisierten Fehler-/Wiederholen-Ansicht statt zu einem allgemeinen Bereich.
- Einzelne Nachrichten-Deep-Links öffnen jetzt zuerst eine geschützte Vorschau und danach genau die zugehörige Konversation; Teilnehmer-, Gruppenbeitritts- und persönliche Ausblendregeln werden serverseitig geprüft.
- Profil-Deep-Links mit Benutzer-ID laden den privacy-gefilterten Sportprofil-Datensatz und öffnen die echte Profilseite; private Profile liefern nur eine neutrale, datensparsame Darstellung.
- Mitgliedschafts-Deep-Links öffnen jetzt den konkreten Antrag über seine ID und gleichen ihn anschließend mit dem passenden Vereinsdatensatz ab; fremde oder nicht mehr vorhandene Anträge werden nicht durch den ersten Vereinsantrag ersetzt.
- Team-Einladungslinks mit Token öffnen nach Authentifizierung eine echte Vorschau; Annahme und Ablehnung laufen über geschützte API-Aktionen mit Prüfung der eingeladenen E-Mail, des offenen Status und des Vereinslimits und werden nach der Antwort ungültig.
- Freundschafts- und Vereins-Einladungslinks werden ebenfalls direkt aufgelöst: Die App zeigt Absender/Verein und Rolle aus der geschützten API und bietet lokalisierte Annahme-/Ablehnungsaktionen; fremde, abgelaufene oder bereits beantwortete Token bleiben gesperrt.
- Benutzerprofile und Medienvorschauen melden Bildinhalte für Screenreader; Avatar-, Galerie- und Video-Fallbacks bleiben auch bei fehlerhaften Quellen verständlich bedienbar.
- Offline-Synchronisation mit Schutz vor dem Persistieren von Passwort-, Authentifizierungs-, Sitzungs- und Löschvorgängen.
- Zusätzliche UI-/API-Flows für Vereine, Teams, Training, Events, Nachrichten, Marketplace, Dateien, Support, Admin und Benachrichtigungen.
- Die Event-Erstellung nutzt jetzt die serverseitige Berechtigung für wiederkehrende Termine und bietet tägliche, wöchentliche, zweiwöchentliche oder monatliche Serien mit Enddatum und Wochentagen lokalisiert an; nicht berechtigte Konten sehen keine versteckte Scheinoption.
- Die Commerce-Verwaltung bietet jetzt einen geschützten, lokalisierten CSV-Export der Bestellungen mit Dateiauswahl und Kopier-Fallback für Plattformen ohne Speicherdialog.
- Teamdetails enthalten jetzt eine API-gebundene Strafkasse für Regeln, offene Gebühren sowie „bezahlt“/„storniert“; Teammitglieder sehen nur eigene Gebühren, Teamverantwortliche erhalten die Verwaltungsaktionen.
- Der Team-Kalender lädt kommende Termine jetzt über die geschützte Events-API, zeigt Datum und Ort responsiv und öffnet die vollständige Event-Detailansicht ohne Platzhalterdaten.
- Teamdateien werden im Teamdetail jetzt über den geschützten Datei-Workspace geladen, mit Typ/Größe angezeigt und direkt in der serverseitigen Vorschau geöffnet; der vollständige Dateimanager startet im Teamkontext.
- Der Team-Chat lädt jetzt ausschließlich die angeforderte Teamkonversation über einen serverseitig geprüften `team_id`-Scope, zeigt letzte Nachricht und Mitgliederzahl und öffnet die bestehende Chat-Detailansicht; berechtigte Mitglieder können den Chat serverseitig eröffnen.
- Der globale Freigabe-Link im Dateimanager öffnet jetzt eine echte Dateiauswahl und kopiert erst nach erfolgreicher, geschützter API-Erstellung den zeitlich begrenzten Link.
- Die Benachrichtigungszentrale bietet jetzt eine geschützte „Alle als gelesen markieren“-Aktion mit optimistischem UI-Zustand, lokalisiertem Fehlerfeedback und bestehender Einzelstatus-Steuerung.
- Event-Details enthalten jetzt echte, geschützte Abstimmungen: Verantwortliche erstellen Fragen mit mehreren Optionen, sichtbare Mitglieder stimmen direkt ab und sehen Ergebnis, Abschlussstatus und die eigene Auswahl.
- Vereinsprofile enthalten jetzt geschützte Umfragen mit Zielgruppe „alle Mitglieder“ oder „Team“, optionalem Quorum, anonymisierter Auswertung, eigener Stimme und Abschlussaktion für Verantwortliche.
- Vereinsprofile enthalten jetzt geschützte Ankündigungen mit Zielgruppe „alle Mitglieder“ oder „Team“, In-App-/Push-Hinweis und direkter Lesebestätigung pro Mitglied.
- Das Vereinsmanagement enthält jetzt einen lokalisierten Rollen-Editor; Rollenwechsel werden mit Akteur, vorheriger/neuer Rolle und Zeitstempel im Audit-Verlauf angezeigt.
- Das eingeloggte Support-Hilfecenter erstellt jetzt echte API-Tickets und zeigt den persönlichen Verlauf mit lokalisierten Statuswerten; Gastanfragen bleiben beim sicheren Kontaktfluss.
- Support-Mitarbeiter erhalten jetzt eine geschützte SLA-/Eskalationsansicht mit offenen, dringenden und überfälligen Tickets, Zuständigkeit, Status, Priorität, internen Notizen und lokalisierter Oberfläche.
- Benachrichtigungskanäle und Ruhezeiten werden jetzt über die geschützte Settings-API synchronisiert; serverseitige Push-Auslieferung respektiert deaktivierte Nutzerkanäle und Ruhezeiten.
- Ein täglicher, lokalisierter E-Mail-Digest fasst ungelesene In-App-Benachrichtigungen zusammen, protokolliert Versandstatus und verhindert doppelte Zustellung; deaktivierte E-Mail-Präferenzen werden respektiert.
- Der Vereinsmitgliederimport zeigt vor dem Schreiben eine geschützte Vorschau mit gültigen Zeilen, Dubletten, Fehlergründen und vorhandenen Konten; der Manager bestätigt den Import erst danach.
- Wenn eine Importdatei keine sichere E-Mail-Spalte enthält, kann der Manager die wichtigen Spalten mobil zuordnen; dieselbe Zuordnung wird anschließend beim bestätigten Schreibimport verwendet.
- Beitragsregeln unterstützen jetzt mobile Standard-, Familien- und Sonderbeiträge sowie prozentuale oder feste Rabatte; Mitgliedsanträge zeigen Grundbetrag, Rabatt und effektiven Endbetrag transparent an.
- Familien-/Haushaltsgruppen können jetzt vereinsbezogen per sicherem Schlüssel zugeordnet werden; ab zwei aktiven Mitgliedern werden Familienbeitrag und Intervall automatisch für die gesamte Gruppe neu berechnet.
- Der persönliche Onboarding-Einstieg zeigt jetzt die serverseitige Konto-Checkliste mit echtem Fortschritt, lokalisierten offenen Schritten und direktem Übergang zur Fortschrittsseite.
- Externe Mitglieder können jetzt in der geschützten Vereinsverwaltung bearbeitet, eingeladen, entfernt und einer Familiengruppe zugeordnet werden; Beiträge und Intervalle werden bei einer vollständigen Gruppe neu berechnet.
- Vereinsbezogene Berechtigungen sind jetzt feingranular: sichere Rollenstandards, explizite Mitglieds-/Finanzrechte pro Verein, serverseitige Durchsetzung, Audit-Log und ein lokalisierter mobiler Berechtigungsdialog.
- Vereinsprofile zeigen jetzt den echten Mitgliedschaftsstatus und bieten für berechtigte Mitglieder eine sichere Pausenanfrage mit Zeitraum sowie den API-gebundenen Vereinsaustritt mit Owner-/Schuldenprüfung.
- Vereinsaustritte laufen jetzt als terminierte, managerbestätigte Anträge mit Begründung, Prüfung offener Rechnungen, automatischer Umstellung auf „ehemalig“ und Entfernung aus Teams.
- Die digitale Mitgliedskarte ist jetzt API-gebunden: kurzlebige, rotierbare Check-in-Codes zeigen nur Minimaldaten, werden als QR-Code angezeigt und können von berechtigten Vereinsverantwortlichen direkt in der Kamera gescannt und einem Event zugeordnet werden.
- Die Trainings-Übungsbibliothek ist jetzt API-gebunden: persönliche, Team- und Vereinsübungen können gesucht, sicher verwaltet und direkt als stabile Momentaufnahme in Trainingspläne übernommen werden.
- Die Trainingsfortschrittsseite zeigt jetzt API-gebundene Einheiten-, Umfangs- und Belastungstrends mit verständlichen Warnsignalen; private Trainingsdaten bleiben auch im Trainerblick ausgeblendet.
- Der Trainingsbereich enthält jetzt einen API-gebundenen Verfügbarkeitsstatus für verfügbar, eingeschränkt, Pause, verletzt oder krank mit Zeitraum, Sichtbarkeit und optionaler persönlicher Notiz; private Hinweise werden serverseitig geschützt, Team-/Traineransichten bleiben datensparsam.
- Trainingspläne können jetzt als unabhängige Vorlagen gespeichert, im eigenen Vorlagenbereich geladen und mit optionalem Zeitraum wieder als bearbeitbarer Entwurf instanziiert werden; Sichtbarkeit und Zuweisungen bleiben serverseitig geprüft.
- Der neue Bereich „Sport-Apps & Gesundheitsdaten“ zeigt Providerstatus, sichere Kontoverbindungen, Synchronisationsstatus und letzte Aktivitäten; normalisierte Importe mit optionalen GPS-Samples sind idempotent API-gebunden und in allen vier Sprachen erreichbar.
- „Sport-Apps & Gesundheitsdaten“ ist zusätzlich als eigener, übersetzter Modul-/Drawer-Einstieg erreichbar und nicht mehr nur über die Einstellungen verborgen.
- Der versionierte Mobile-Meta-Vertrag meldet Sportintegrationen jetzt explizit mit Providerstatus, Konten, Sync, normalisiertem Import sowie GPS-/GPX-Fähigkeiten; Clients können die Funktion sicher erkennen.
- Die globale Suche liefert jetzt sichtbarkeitsgeprüfte Treffer für Personen, Vereine, Teams, Events, Kurse, Produkte und Dateien; mobile Treffer öffnen direkt den passenden Detail- oder Arbeitsbereich.
- Der Eltern-/Guardian-Bereich bietet jetzt einen sicheren Kinderüberblick: Nach Zustimmung erscheinen ausschließlich anstehende Termine und 28-Tage-Trainingssummen; private Notizen, Nachrichten, Straßenadressen und Detailprofile werden nicht ausgeliefert.
- „Upload starten“ im erreichbaren Datei-Operationsbereich öffnet jetzt direkt den echten Datei-Picker und den serverseitigen Upload-Flow des Datei-Managers statt einer UI-Ergebnis-/Platzhalterseite.
- Öffentliche Vereinsauswahl für Mitgliedschaftsanträge mit serverseitig geprüfter Sichtbarkeit und ohne Offenlegung sensibler Finanz-/Mitgliederdaten.
- Echter Mitgliedschaftsantrag mit Vereinsauswahl, Zahlungs-/Beitragsoptionen, Einwilligungen, Validierung, Fehlerzuständen und API-Übermittlung.
- Mitgliedschafts-Einwilligungen werden jetzt mit stabiler Dokumentversion, Zeitstempel, Bestätigungsmethode und optionaler Namensbestätigung im Antragsstatus nachvollziehbar angezeigt.
- Echter Status-/Rückzugsablauf für Mitgliedschaftsanträge sowie API-basierter Vereins-Posteingang mit Prüfen, Genehmigen, Ablehnen und Notiz.
- Die erreichbare Mitgliedsanfrage-Statusseite ist vollständig in Deutsch, Englisch, Französisch und Arabisch lokalisiert; Vereinsname und Status kommen aus der API, der Zeitverlauf und die Aktionen bleiben auch mit großer RTL-Schrift responsiv.
- Die Vereins-Inbox für Mitgliedsanträge ist jetzt vollständig lokalisiert, filtert anhand echter API-Statuswerte und bleibt mit großen arabischen RTL-Schriften ohne Material- oder Layout-Warnungen bedienbar.
- Vereinsverwaltung lädt jetzt den tatsächlich verwaltbaren Verein, speichert Antragsschalter, Feldmodi, Zahlarten und Dokumentverknüpfungen über die geschützte API.
- Die Membership-Admin-Seite lokalisiert Feldkatalog, Bereiche und Prüfentscheidungen in vier Sprachen und stapelt Kennzahlen auf schmalen RTL-Ansichten ergonomisch.
- Vereins-Profilbearbeitung lädt und speichert Name, Sportart, Adresse, Sichtbarkeit und Posting-Regeln des berechtigten Vereins über die API.
- Mitglieder-, Beitrags- und Finanz-Einstiege verwenden die vollständige, berechtigungsgeprüfte Managementseite statt statischer Beispielzeilen.
- Altersfreigaben zeigen jetzt den echten kontobezogenen Fortschritt, Checklisten und Sicherheitsbereiche über die geschützte Maturity-API, übersetzt in vier Sprachen.
- Medienrichtlinien führen zu den realen Datei-, Datenschutz-, Guardian- und Support-Flows; statische Dokument-/Sichtbarkeitsvorschauen wurden aus den Vereins-Einstiegen entfernt.
- Legacy-Operations- und Vereins-Unterseiten öffnen nun die vorhandenen API-Zentren für Teams, Training, Mitgliedschaften, Chat, Marketplace, Billing, Rollen und Plattform-Moderation.
- Öffentliche Interessenanfragen verwenden jetzt ein übersetztes Formular mit Validierung, Datenschutzeinwilligung, rate-limitiertem Gast-Endpunkt und sichtbarem Erfolgs-/Fehlerzustand.
- Der öffentliche Anfrage-Flow zeigt keine internen Kategorienamen mehr; Anfragearten und der Einstieg zum Standortvorschlag sind in Deutsch, Englisch, Französisch und Arabisch lokalisiert.
- Öffentliche Blog-, Sponsor-, Vereins-, Marketplace- und Lernkatalog-Abfragen verwenden jetzt einen gemeinsamen IP-basierten Rate-Limiter gegen automatisiertes Auslesen.
- Fehlende Admin-Berechtigungs- und Vereinsprofil-Ladehinweise sind in allen vier unterstützten Sprachen ergänzt.
- Nicht eingeloggte Profil-Fallbacks zeigen keine erfundene lokale E-Mail-Adresse mehr.
- Der Release-Fallback für API- und Medien-URLs verwendet jetzt konsistent `https://app.airmius.com`, passend zu App-Links, Runbook und Release-Skripten.
- Der öffentliche Standortflow nutzt auf kleinen RTL-/Großschrift-Ansichten automatisch umbrechende Auswahlchips; dekorierte Panels verankern interaktive ListTiles jetzt korrekt für sichtbare Ripple- und Auswahlzustände.
- Inhaltsmeldungen und die Profilbearbeitung verwenden jetzt echte Übersetzungen in Deutsch, Englisch, Französisch und Arabisch; das Profil-Dropdown bleibt bei großer RTL-Schrift auf kleinen Ansichten ohne Überlauf.
- Gemeinsame Bestätigungsdialoge verwenden ohne eigene Beschriftung jetzt einen neutralen, lokalisierten „Bestätigen“-Button statt eines falschen „Zurückziehen“-Texts.
- Die Sportauswahl im Feed-Komposer lädt jetzt echte Sportarten und Skills aus der API; die erweiterten Dropdowns bleiben auch bei großer arabischer RTL-Schrift ohne Überlauf bedienbar.
- Geschützte Deep Links prüfen die aktive Sitzung vor dem Öffnen eines API-Screens und zeigen Gästen stattdessen einen lokalisierten Authentifizierungs-Gate ohne API-Datenzugriff.
- Profil-Hero, Profil-Tabs, Vereins-Wizard, Dateiaktion und Feed-Senden berechnen den Vordergrundkontrast jetzt aus der aktiven Palette; Schließen-/Kopieren-Aktionen sowie die Story-Navigation haben zusätzlich lokalisierte Tooltips für Screenreader und große Touch-Ziele.
- Die Dashboard-Dateikachel verwendet jetzt geschützte Daily-Flow-API-Daten für Dateianzahl und Speicherverbrauch statt fester Beispielwerte; leere oder nicht erreichbare Live-Daten werden klar mit Wiederholen-Aktion angezeigt.
- Der Datei-Manager blendet Speicherverbrauch und Limit aus, solange der Server keine echten Nutzungsdaten liefert; ein irreführender 1-GB-Fallback während Laden/Fehler wurde entfernt.
- Deep-Link-Routing verwendet für Erkennungs- und Fallback-Karten die aktive Farbpalette; unbekannte Links sowie sichtbare Routing-Ziele und Beschreibungen sind jetzt auch auf Französisch und Arabisch verständlich lokalisiert.
- Der Drawer-Einstieg „Nachrichten“ öffnet jetzt zuverlässig die echte Chat-Inbox statt der Benachrichtigungszentrale; dieser Pfad ist zusätzlich per UI-Regressionstest abgesichert.
- Die Legacy-Einstiege „Rollen & Rechte“ und „Gamification-Regeln“ öffnen das gemeinsame API-Adminzentrum jetzt direkt im jeweils passenden Tab statt irreführend bei „Nutzer“ zu starten.
- Zentrale Outfit-Abo-Begriffe wie Plan, Anfrage, Vertrag und Abonnement sind jetzt auch in Französisch und Arabisch übersetzt statt auf englische Fallbacks zurückzufallen.
- Datei-Bereiche, Dateinamen, Serverstatus und Lesefehler sind in Französisch und Arabisch ebenfalls sichtbar lokalisiert.
- Dateioperationen, Vorschau, Freigabe- und Token-Zustände sind jetzt in Französisch und Arabisch vollständig abgedeckt, einschließlich Upload, Download und Teilen.
- Mitgliedschaftsstatus, Rückzug, Antragsfehler und Inbox-Aktionen sind jetzt ebenfalls vollständig auf Französisch und Arabisch übersetzt.
- Der Feed-Bildproxy ist jetzt durch Sanctum und die Post-Sichtbarkeitsrichtlinie geschützt; private Bilder werden weder Gästen noch unberechtigten Konten ausgeliefert.
- Feed-Bildantworten geben keine öffentliche Storage-URL mehr aus; die Mobile-App lädt den geschützten Proxy mit dem aktuellen Bearer-Token und verhindert damit direkte Speicher-Bypässe.
- Alte Nutzer-, Gastpreis-, Gastblog- und Public-Growth-Einstiege zeigen keine lokalen Demo-Karten mehr, sondern öffnen die echten permission-/API-gebundenen Zentren.
- Commerce-, Outfit-, Mail- und System-Admin-Oberflächen haben jetzt vollständige französische und arabische Übersetzungen für Formulare, Statuswerte, Dialoge und Aktionen.
- Das Gastportal öffnet jetzt ein echtes, API-basiertes Vereinsverzeichnis mit Suche nach Verein, Sport und Ort; es zeigt nur verifizierte, gelistete und datensparsame Vereinsdaten und führt sicher zum Kontaktfluss.
- Standortvorschläge und Korrekturen laufen jetzt über ein lokalisiertes, validiertes Gastformular mit Moderationshinweis und API-Übermittlung statt einer UI-Demo.
- Das Gastportal führt jetzt zu echten, sicheren öffentlichen Bereichen für Blog, Partner, Lernen, Marketplace, Kontakt und Recht; interne Operations-FABs wurden aus öffentlichen Einstiegen entfernt.
- Top-Inhalte laden veröffentlichte Blog- und Partnerdaten aus der API und bieten übersetzte Filter-, Lade-, Fehler- und Leerzustände.
- Zertifikate können öffentlich per Code über einen sicheren, abgeschlossenen Lernstatus geprüft werden; sensible Kontodaten und der geschützte PDF-Download bleiben verborgen.
- Der öffentliche Marketplace lädt veröffentlichte Angebote sicher aus der API, unterstützt Suche und Kategorien und führt Interessierte über ein echtes Kontaktformular zum Anbieter.
- Der öffentliche Lernbereich lädt veröffentlichte Kurse aus der API, unterstützt Suche/Kategorien und verknüpft Zertifikatsprüfung und Kursinteresse mit echten Flows statt statischer Beispielkarten.
- Legacy-Einstiege für Badges, Fahrgemeinschaften, Routen, Trainingslogs, Mahlzeiten, Skill-Empfehlungen, Datenrechte und Blog öffnen jetzt die echten API-Zentren statt lokaler UI-Demos.
- Legacy-Einstiege für Sportprofile, Einstellungen und Recht öffnen nun ebenfalls die API-gebundenen Zentren ohne Operations-FABs oder lokale Speicherdemos.
- Die öffentliche Detailansicht ist vollständig übersetzt und zeigt keine Roadmap-/Demo-Versprechen mehr; jede Aktion führt in einen aktuellen öffentlichen Katalog oder sicheren Kontaktfluss.
- Das eingeloggte Dashboard und der Tagesflow laden Trainings-, Routen-, Ernährungs-, Wasser- und Hinweisdaten aus dem geschützten Daily-Flow-Endpunkt; leere oder offline Daten werden klar dargestellt statt mit Beispielwerten gefüllt.
- Der Tagesflow ist als zugänglicher Schnellstart erreichbar, zeigt Fortschritt und Coach-Hinweis aus der API und öffnet die echten Trainings-, Karten-, Ernährungs- und Benachrichtigungsbereiche.
- Rollen, Gamification-Regeln und Nutzer sind als permission-geschützte API-Einstiege sichtbar; Outfit-Abos bleiben als eigener, echter Nutzerbereich erreichbar.
- Nachrichten unterstützen jetzt echte, authentifizierte Dateianhänge bis 10 MB pro Datei (maximal fünf), inklusive lokalisierter Auswahl, Vorschauchips, Entfernen und serverseitiger Berechtigungsprüfung.
- Nachrichtenkarten zeigen jetzt die echte serverseitige Mitgliederzahl mit korrekter Singular-/Plural-Lokalisierung statt eines festen Demo-Werts.
- Arbeitsbereiche zeigen jetzt serverseitige Vereine, Teams, Rollen und offene Teameinladungen; Einladungen können direkt aus dem Detailbereich angenommen werden, ohne Demo-Zahlen oder Schein-Token.
- Workspace-Kontextwechsel öffnen jetzt die echten API-Zentren für Gast, Tagesflow, Trainer, Verein und Administration; lokale Schalter ohne Persistenz wurden entfernt und Workspace-Einstellungen führen in die persistente Einstellungsseite.
- Workspace-Detailbereiche zeigen nur noch serverseitig bekannte Berechtigungen oder einen klaren Hinweis, wenn diese Daten nicht geliefert wurden; Rollen-/Audit-Bezeichnungen und Nachrichten-Typen sind in allen vier Sprachen lokalisiert.
- Der Feed-Composer übersetzt Zielgruppe, Beitragstyp, Sport-/Teamauswahl, Medienhinweise und Upload-Fehler in Deutsch, Englisch, Französisch und Arabisch.
- Öffentliche Nutzerprofile zeigen jetzt nur noch serverseitige Sportprofildaten; Freundschaftsanfragen, Annahme/Ablehnung, Entfernen, vorausgewählte Nachrichten und Profilmeldungen laufen über geschützte API-Aktionen statt lokaler Demo-Zustände.
- Dashboard-Kompatibilitätseinstiege für Freunde und Nachrichten öffnen jetzt dieselben API-Zentren wie die Hauptnavigation; Beispielkontakte, Beispielnachrichten und UI-only-Sendeaktionen wurden entfernt.
- Der Vereinsbereich bietet jetzt direkt eine lokalisierte Suche nach Vereinen und Teams, damit neue Mitgliedschaftsanträge auch aus einem leeren Vereinsbereich ergonomisch gestartet werden können.
- Modulübersichten zeigen im normalen MVP-Modus keine erfundenen Kennzahlen oder Beispielzeilen mehr; jeder sichtbare Einstieg führt in das zuständige API-Center mit echten Lade-, Leer- und Fehlerzuständen.
- API-Fehlermeldungen in Vereins-, Team-, Antrag-, Feed- und Update-Aktionen geben keine rohen Serverantworten mehr aus, sondern sichere, verständliche Nutzerhinweise.
- Technische SQL-, Framework-, Dateipfad- und KI-Exceptiontexte werden weder von der App noch von den Trainings-/Ernährungs-Controllern an Nutzer durchgereicht; Details bleiben serverseitig protokolliert.
- Die zentralen Bereiche Ernährung, Fahrgemeinschaften, Sportkarte, Abos, Commerce, Lernen, Marketplace und Freunde verwenden ebenfalls sichere, lokalisierte Fehlerhinweise ohne rohe Exception-Texte.
- Der Mitgliedschaftsantrag verwendet jetzt stabile interne Auswahlwerte für Mitgliedschaft, Zahlart und Intervall; Adress- und Bankfelder sind in allen vier Sprachen lokalisiert und die Formularflächen passen sich der aktiven Palette an.
- Teameinladungen laden nach dem Widget-Lifecycle zuverlässig echte API-Daten, zeigen Status, Rolle und Aktionen übersetzt in Deutsch, Englisch, Französisch und Arabisch und bleiben mit RTL, großer Schrift und aktiver Palette bedienbar.
- Antragstatus, Vereins-Inbox, Chatblasen, Datei-Vorschau, Upload-Aktion, Dashboard-Aktion und Admin-FAB übernehmen nun adaptive Akzent-, Flächen-, Rahmen- und Textfarben statt fester Dark-Theme-Werte.
- Datei-Freigaben erzeugen jetzt über einen geschützten API-Endpunkt echte, auf 1–30 Tage begrenzte Links mit gehashtem Token und Rate-Limit; die App kopiert den Link erst nach erfolgreicher Serverantwort.
- Ordner-Freigaben sind jetzt ebenfalls echt: Die App lädt die Freundesliste, lässt eine Zielperson auswählen und kopiert den gesamten Ordnerbaum serverseitig nur bei bestehender Freundschaft, inklusive Benachrichtigung und Rate-Limit.
- Die separate Freigabeansicht zeigt keinen erfundenen Token oder simulierte Passwort-/Ablauf-Schalter mehr; mit einem echten Link öffnet, lädt oder kopiert sie den geprüften HTTP(S)-Link, ohne Link bleibt ein klarer Leerzustand.
- Die Dateivorschau zeigt jetzt echte Backend-Metadaten und bietet nur noch Download/Freigabe-Aktionen; lokale Schalter für angebliche Antrags- oder Profilverknüpfungen sowie doppelte Test-Link-Aktionen wurden entfernt.
- Android-Release-Builds verweigern jetzt den Build, wenn kein expliziter Release-Key über `android/key.properties` konfiguriert ist; ein versehentliches Debug-signiertes AAB ist damit ausgeschlossen.

Vor dem Play-Console-Upload bleiben die Release-Prüfungen und der manuelle Android-Gerätetest als unabhängige Freigabegates bestehen.
Der Sammel-Release verwendet den erhöhten `versionCode` `11`, damit der Upload auch dann akzeptiert wird, wenn Version `1.0.9+10` bereits intern getestet oder in Play Console verwendet wurde.

Web-Sammelstand (26.07.2026):

- Der eingeloggte Trainingsbereich ist ergonomischer für tägliche Nutzung: zentrale Sport-, Status-, Plan-, KI- und Dokumentationsbegriffe folgen DE/EN/FR/AR, Datum/Zeit/Distanz passen sich der Sprache an und leere sowie fehlerhafte Zustände nennen die nächste sinnvolle Aktion.
- Fahrgemeinschaften, Outfit-Checkout und Admin-Moderation führen Datenschutz-/Statusfeedback, regionale Datums-/Geldformate und bestätigte destruktive Aktionen konsistent zusammen; Event-Wizard und Zahlungszentrale verwenden lokalisierte Validierungs-, Status- und Leerzustände in DE/EN/FR/AR.
- Dashboard, Ernährung, Gast-Marketplace, Vereinsfinanzen, Admin-Commerce und Abo-Rechnungen verwenden regionale Zahlen-/Datumsformate sowie lokalisierte Fehler-, Leer- und Statuszustände; Subscription-Planaktionen fragen vor Änderungen verständlich nach.
- Dateien und Chat verwenden lokalisierte Upload-, Suche-, Freigabe-, Leer-, Fehler- und Nachrichtenstatus; wichtige Aktionen sind mit klaren Touch-Zielen und verständlichen Beschriftungen erreichbar.
- Commerce lokalisiert Shop-Navigation, Checkout-Anbieter, Bestell-/Versand-/Erstattungs-/Auszahlungsstatus sowie Geld- und Datumsformate für Deutsch, Englisch, Französisch und Arabisch.
- Freundschaftsanfragen, Annahme/Ablehnung, Meldung, Entfernen und Leerzustände sind im Web-Freunde-Bereich ebenfalls zentral in allen vier Sprachen beschrieben.
- Vereins-/Team-Aktionen verwenden jetzt einen gemeinsamen DE/EN/FR/AR-Katalog für Registrierung, Einladungen, Beitrittsanfragen, Rollen, Sponsoren, Jobs und destruktive Bestätigungen; Wizard-Schritte und Bearbeitungstabs reagieren auf Sprachwechsel.
- Ernährung nutzt für Wasser, KI-Fotoanalyse und Fortschrittsfeedback semantische AIRMIUS-Akzentfarben; globale Layout-Feedbacks, Benachrichtigungszeiten sowie Event-, Commerce-, Vereins- und Teamdetails folgen der aktiven Sprache und Region.

English summary:

- Unified responsive design system with multiple palettes, light/dark/system modes and clearer type scales.
- Main areas now derive their app-bar and surface colors from the active palette, keeping light, dark and high-contrast navigation consistent across page changes.
- Team, friends, badges, blog, sponsor and nutrition pages now derive neutral text, surface and border colors from the active theme instead of fixed dark-theme values.
- Club status, role, warning and membership actions now also use the active theme color scale, keeping the club workflow readable in light, dark and high-contrast palettes.
- Accessibility improvements for large text, RTL/Arabic, touch targets, keyboard focus, semantics and error feedback.
- Local login validation with translated, actionable messages.
- Profile and account management for profile photos, password changes, sessions and protected account deletion.
- Email verification, two-factor security, recovery codes and authentication gates.
- The account/security hub is fully localized; password recovery, 2FA, email verification, profile completion, support and account deletion now open real screens instead of placeholder actions.
- Privacy, email verification and two-factor security now use adaptive theme tokens for protection status, warnings and errors; the 2FA workflow is also covered in Arabic RTL light mode with large text.
- Registration stacks address fields on compact screens and uses an expanded gender dropdown, keeping Arabic RTL usable at 1.25× text without overflow.
- The outfit-subscription checkout stacks country/postal code and street/house number on narrow screens, keeping paid enrollment readable with large Arabic RTL text.
- German, English, French and Arabic localization with a neutral fallback for incomplete entries.
- The profile-completion gate for new accounts is now localized in all four languages and follows light, dark and high-contrast palettes, including minor/guardian guidance.
- External payment, document and marketplace links are validated for safe HTTP(S) schemes, hosts and the absence of embedded credentials before opening.
- Payment, learning and legal links now use the same centralized URL validation and reject embedded credentials and invalid schemes.
- Images, avatars, club logos, galleries and video media from API responses are normalized and loaded only from permitted HTTP(S) sources, with clear fallbacks for invalid media.
- Deep-link previews now localize status, event, messaging and retry feedback in German, English, French and Arabic as well.
- User profiles and media previews now expose image content to screen readers; avatar, gallery and video fallbacks remain understandable when sources fail.
- Offline sync now refuses to persist password, authentication, session and deletion requests.
- Additional UI/API flows for clubs, teams, training, events, messaging, marketplace, files, support, admin and notifications.
- Training plans can now be saved as independent, server-scoped templates and instantiated later with optional dates as editable drafts; assignments and visibility remain permission checked.
- The new “Sport apps & health data” area shows provider status, protected accounts, synchronization state and recent activities; normalized imports with optional GPS samples are idempotent, API-backed and localized in all four supported languages.
- “Sport apps & health data” is also available as its own localized module/drawer entry instead of being reachable only through settings.
- The versioned mobile meta contract now explicitly advertises sport integrations, account sync, normalized imports and GPS/GPX capabilities so clients can detect the feature safely.
- Global search now returns visibility-checked people, clubs, teams, events, courses, products and files; mobile results open the matching detail or workspace flow directly.
- The directory search starts empty instead of issuing a fabricated demo query, debounces live input, and keeps loading, error and empty states localized and palette-aware across all four languages, RTL and large text.
- Marketplace prices, reviews, availability, cart, checkout, orders and issue states now also use the active theme colors; the French light/trail layout remains readable with large text.
- Protected event deep links now load the concrete event detail from the API after the session check; guests see an authentication gate and denied requests show a localized retry state instead of a generic event list.
- Protected feed deep links now load the concrete post from the API after the session check and open the real detail screen; visibility and moderation are enforced server-side instead of trusting post data from the URL.
- Protected chat and notification deep links now resolve title, access and content through the API before opening; invalid or foreign IDs show a localized error/retry state instead of a generic section.
- Individual message deep links now load a protected preview and then the exact conversation; participant, group-join and per-user hide rules are enforced server-side.
- Profile links with a user ID now load the privacy-filtered sport profile and open the real profile detail; private profiles expose only a neutral, data-minimized view.
- Membership deep links now resolve the concrete application by ID and match it to the club-scoped API record; foreign or missing applications are never replaced by the first club request.
- Guardians can now open a protected child overview after consent, showing only upcoming schedules and 28-day training aggregates; private notes, messages, street addresses and detailed profiles stay out of the API response.
- Team calendars and team files now load from protected, team-scoped APIs; dates, locations, file metadata and detail navigation remain responsive and accessible.
- Team chat now requests only the selected team scope through the protected API, shows the latest message and member count, and opens the existing realtime chat detail flow with server-side membership checks.
- The file manager share action now opens a real file picker and copies a time-limited protected link only after the API has created it successfully.
- The notifications center now provides a protected “mark all as read” action with optimistic UI feedback, localized error handling and the existing per-notification read-state control.
- Event details now include real protected polls: event managers can create multi-option questions, visible members can vote directly, and everyone sees the result, status and their own selection.
- Club profiles now include protected polls for all members or a selected team, with optional quorum, privacy-safe results, personal voting state and a manager close action.
- Club profiles now include protected announcements for all active members or a selected team, with in-app/push notification delivery and a per-member read confirmation.
- Club management now includes a localized member role editor; role changes are shown in an audit history with actor, previous/new role and timestamp.
- The authenticated support centre now creates real API-backed tickets and shows the user's history with localized status values; guest requests continue through the protected contact flow.
- Club contribution rules now support mobile standard, family and special contributions plus percentage or fixed discounts; membership applications expose the base amount, discount and effective total transparently.
- Club managers can assign a safe club-scoped family/household key; once two active members share it, the family contribution and billing interval are recalculated for the complete group.
- The personal onboarding entry now uses the server-side account checklist, shows real progress and localized open steps, and links directly to the progress center.
- External members can now be edited, invited and removed from the protected club workspace, assigned to a family group, and recalculated when the group reaches the active-member threshold.
- If an import file has no confidently detected email column, managers can map the important columns on mobile; the confirmed write uses the same mapping.
- Support staff now have a protected SLA/escalation view for open, urgent and overdue tickets, assignees, status, priority and internal notes, with a localized mobile surface.
- The reachable file-operations “Start upload” action now opens the real file picker and server-backed file-manager upload flow instead of a UI-only result/placeholder page.
- The reachable membership-request status page is fully localized in German, English, French and Arabic; club name and status come from the API, while the timeline and actions remain responsive with large RTL text.
- The club membership inbox is now fully localized, filters by real API status values and remains usable with large Arabic RTL text without Material or layout warnings.
- Membership administration now localizes its field catalogue, sections and review decisions in four languages and wraps metrics ergonomically on narrow RTL layouts.
- Public interest requests now use a translated, validated, rate-limited guest contact flow with clear success and error states.
- The public interest flow no longer exposes internal category identifiers; request types and the location-suggestion entry are localized in German, English, French and Arabic.
- The guest portal now opens a real API-backed club directory with club, sport and location search; it exposes only verified, listed and privacy-safe club data and routes safely to the contact flow.
- Location suggestions and corrections now use a translated, validated guest form and the moderation contact API instead of a UI-only demo.
- The guest portal now links to real, safe public areas for blog, partners, learning, marketplace, contact and legal information; internal operations actions were removed from public entry points.
- Top content loads published blog and partner records from the API with translated filter, loading, error and empty states.
- Certificates can be verified publicly by code against an active, completed learning record; sensitive account data and the protected PDF download remain hidden.
- The public marketplace now loads published offers safely from the API, supports search and categories, and routes interested visitors to a real provider contact form.
- The public learning area now loads published courses from the API, supports search/categories, and connects certificate verification and course interest to real flows instead of static sample cards.
- Legacy entry points for badges, carpools, routes, training logs, meals, skill recommendations, data rights and blog now open real API centers instead of local UI demos.
- Legacy entry points for sport profiles, settings and legal documents now open API-backed centres without operations FABs or local-only save demos.
- The public detail view is fully localized and no longer shows roadmap/demo promises; each action opens a current public catalogue or safe contact flow.
- The authenticated dashboard and Today Flow now load training, route, nutrition, hydration and notification data from the protected daily-flow endpoint; empty/offline data is shown honestly instead of placeholder values.
- Today Flow is available as an accessible quick start, renders API progress and coach context, and opens the real training, map, nutrition and notification centres.
- Roles, gamification rules and user administration are visible as permission-scoped API entry points; outfit subscriptions remain a separate real member area.
- Messages now support real authenticated file attachments up to 10 MB per file (maximum five), with localized picking, removable preview chips and server-side permission checks.
- Conversation cards now show the real server-side member count with correct singular/plural localization instead of a fixed demo value.
- Workspaces now show server-derived clubs, teams, roles and pending team invitations; invitations can be accepted directly from the detail view without demo counts or placeholder tokens.
- Workspace context switches now open the real API-backed guest, Today Flow, trainer, club and admin centres; non-persistent local switches were removed and workspace settings lead to the persistent settings page.
- Workspace detail views show only server-provided permissions or an honest unavailable-data notice; role/audit labels and message types are localized in all four supported languages.
- The feed composer now localizes audience, post type, sport/team selection, media guidance and upload errors in German, English, French and Arabic.
- The feed composer now loads real sports and skills from the API; its expanded dropdowns remain usable with large Arabic RTL text without overflow.
- Protected deep links now verify the active session before constructing an API screen and show guests a localized authentication gate without fetching protected data.
- Profile hero/tabs, club wizard, file actions and feed sending now derive foreground contrast from the active palette; close/copy actions and story navigation also expose localized tooltips for assistive technologies and large touch targets.
- The dashboard file card now uses protected Daily Flow API data for file count and storage usage instead of fixed sample values; empty or unavailable live data is shown honestly with a retry action.
- The file manager hides storage usage and limits until the server provides real usage data; the misleading 1 GB loading/error fallback has been removed.
- Deep-link routing now uses the active palette for recognition and fallback cards; unknown links plus visible routing destinations and descriptions are clearly localized in French and Arabic.
- The drawer's “Messages” entry now reliably opens the real chat inbox instead of the notification centre; this path is covered by a UI regression test as well.
- The legacy “Roles & permissions” and “Gamification rules” entries now open the shared API-backed admin centre on their matching tab instead of landing misleadingly on “Users”.
- Core outfit subscription terms such as plan, request, contract and subscription are now translated in French and Arabic instead of falling back to English.
- File scopes, file names, backend status and read errors are also visibly localized in French and Arabic.
- File operations, previews, sharing and token states are now fully covered in French and Arabic, including upload, download and sharing actions.
- Membership status, withdrawal, application errors and inbox actions are now fully translated in French and Arabic as well.
- The feed image proxy is now protected by Sanctum and the post visibility policy; private images are no longer served to guests or unauthorized accounts.
- Public user profiles now show server-provided sports data only; friend requests, accept/decline, removal, preselected messages and profile reports use protected API actions instead of local demo state.
- Dashboard compatibility entries for friends and messaging now open the same API-backed centres as the main navigation; sample contacts, sample messages and UI-only send actions were removed.
- The club area now offers a localized search entry for clubs and teams, so a new membership request can be started comfortably even when the personal club list is empty.
- Module overviews no longer show invented metrics or sample rows in the normal MVP mode; every visible entry opens the responsible API-backed centre with real loading, empty and error states.
- API errors in club, team, application, feed and update actions no longer expose raw server responses; users receive safe, understandable feedback instead.
- SQL, framework, filesystem and AI exception details are no longer passed to users by the app or training/nutrition controllers; technical details remain server-side for diagnostics.
- Nutrition, carpools, sport map, subscriptions, commerce, learning, marketplace and friends now use safe, localized error feedback without raw exception text as well.
- Der Datei- und Upload-Bereich verwendet jetzt adaptive Hell-/Dunkel-Kontraste für Ordner, Dateien, Filter, Eingaben, Speicheranzeige und Fehlermeldungen; leere und fehlerhafte Zustände bieten klare nächste Aktionen.
- Modulstartseiten zeigen im normalen MVP jetzt keine statischen Beispielzeilen mehr: jede Seite bietet eine lokalisierte, große Öffnen-Aktion direkt zum echten API-Bereich; Deutsch bleibt deutsch, während Englisch, Französisch und Arabisch sauber auf ihre Übersetzungen bzw. den neutralen Fallback zurückgreifen.
- Vereins-, Team-, Profil-, Training-/Event-, Feed-, Nachrichten-, Sportkarten-, Marketplace-, Commerce-, Lern-, Abo-, Outfit- und Jugendschutzdetails übernehmen neutrale Text-, Eingabe-, Flächen- und Randfarben aus dem aktiven Theme.
- Auch Mitgliedschaftsverwaltung, Chatdetail, neue Unterhaltung, Antrag-/Inbox-Workflows, Suche, Checkout, Produkt-/Profil-/Blog-/Sponsor-Details, Lernlektionen und Dateioperationen sind jetzt für Hellmodus, große Schrift und klare Touch-Ziele abgestimmt.
- Die Modulnamen sind nun vollständig für Deutsch, Englisch, Französisch und Arabisch abgedeckt; fehlende Labels fallen nicht mehr unerwartet auf Deutsch oder Englisch zurück.
- Native Deep Links zeigen jetzt lokalisierte Routing-Audits, sichere Fallbacks, API-Detailvorschauen und adaptive Kontraste in allen vier unterstützten Sprachen.
- Vereins-, Finanz-, Commerce-, Trainingsplan-, Rollen-, Admin- und Einladungsdetails übernehmen nun auch adaptive AppBar-, Scaffold-, Eingabe- und Dropdown-Flächen statt fester Dark-Theme-Farben.
- Profil-Sicherheit, Datenschutz/Einwilligung und Vereinsprofilbearbeitung folgen jetzt denselben adaptiven Theme-Tokens für Formulare, Panels, Ränder und Statushinweise.
- Die Benachrichtigungs-/Update-Zentrale nutzt adaptive Chips, Flächen, Icon- und Textfarben auch bei hellen Paletten.
- Die Benachrichtigungszentrale lokalisiert Titel, Filter, Nachrichtenstatus und Push-Einstieg jetzt auch korrekt auf Arabisch; Kennzahlen stapeln sich bei großer RTL-Schrift automatisch.
- Alte Einstiege für Event-Anwesenheit und Trainingsplan-Details öffnen jetzt die API-gebundenen Event- sowie Trainingsplan-/Log-Zentren; statische Demo-Karten und Scheinaktionen wurden entfernt. Der arabische Event-/Trainings-Titel ist ebenfalls lokalisiert.
- Arabische Kernnavigation, Suche, Feed-, Mitgliedschafts- und Statusbeschriftungen sind jetzt vollständig übersetzt; Feed-Lade- und Engagementzeilen umbrechen bei großer RTL-Schrift ohne Overflow.
- Der Dashboard-Ladefehler zeigt jetzt nur noch verständliche API-Nutzerhinweise statt roher Exception-/Servertexte.
- Auch der Trainingsplan-Bildupload verwendet bei unbekannten Netzwerkfehlern einen sicheren lokalisierten Fallback statt roher Exceptiontexte.
- Membership applications now use stable internal values for membership, payment and interval choices; address and bank fields are localized in all four languages and form surfaces follow the active palette.
- Team invitations now load real API data after the widget lifecycle, localize status, role and actions in German, English, French and Arabic, and remain usable with RTL, large text and the active palette.
- Vereins- und Teamprofile übernehmen regionale Datums-/Geldformate sowie sprachwechselreaktive Rollen-, Trainings- und Strafkassenstatus; Beitritts-, Austritts- und Buchungsbestätigungen sind in DE/EN/FR/AR lokalisiert.
- Die Admin-Vereinsprüfung und das Provider-Kosten-Dashboard verwenden aktive Sprache/Region für Status, Hinweise, Zahlen und Geldwerte sowie semantische Warnfarben für helle und dunkle Paletten.
- Betriebskosten und Verträge zeigen regionale Datums-/Geldformate, lokalisierte Fristen-/Status-/Löschzustände und getrennte Summen je Währung; Pagination rendert keine unkontrollierten HTML-Labels mehr.
- Gamification-Regeln zeigen lokalisierte Admin-Texte, Rollen, Kennzahlen und Balance-Hinweise mit regionaler Zahlenformatierung und theme-aware Warnfarben.
- Die Media-Guidelines-Zentrale zeigt lokalisierte Suche, Upload-/Slider-Steuerung, Feedback und Tabellenzustände; Entfernen-Aktionen folgen dem aktiven Theme.
- Das Mailcenter zeigt lokalisierte Versand-, Queue- und Audit-Zustände, regionale Zeitformate sowie sichere Pagination ohne unkontrolliertes HTML und semantische Statusfarben.
- Die Systemsettings zeigen lokalisierte KI-Tokenstatus und Warnungen mit regionalen Ablaufdaten; sensible Einstellungen bleiben 2FA- und berechtigungsgebunden.
- Die Sportartenverwaltung zeigt lokalisierte Filter, Nutzungszahlen sowie klare Erstell-/Löschzustände mit regionaler Zahlenformatierung.
- Die Blog-Kategorienverwaltung zeigt lokalisierte Formulare und Zustände, regionale Kennzahlen und sichere Pagination ohne unkontrolliertes HTML.
- Application status, club inbox, chat bubbles, file preview, upload action, dashboard action and admin FAB now use adaptive accent, surface, border and text colors instead of fixed dark-theme values.
- File sharing now uses a protected API endpoint with a rate-limited, hashed token and a 1–30 day expiry; the app copies a link only after the server creates it.
- Folder sharing is now real as well: the app loads the friend list, lets the user choose a recipient and copies the complete folder tree server-side only for an existing friendship, with notification and rate limiting.
- The standalone shared-file view no longer fabricates tokens or simulates password/expiry switches; with a real link it opens, downloads or copies the validated HTTP(S) URL, and without one it shows a clear empty state.
- The file preview now shows backend metadata and only offers real download/share actions; local switches for pretend application/profile linkage and duplicate test-link actions were removed.
- Android release builds now refuse to proceed without an explicit release key configured through `android/key.properties`, preventing an accidentally debug-signed AAB.
- Native deep links now provide localized routing audits, secure fallbacks, API detail previews and adaptive contrast in all four supported languages.
- Club, finance, commerce, training-plan, role, admin and invitation details now also use adaptive app-bar, scaffold, input and dropdown surfaces instead of fixed dark-theme colors.
- Profile security, privacy/consent and club profile editing now use the same adaptive theme tokens for forms, panels, borders and status feedback.
- The notifications/update centre now uses adaptive chip, surface, icon and text colors across light palettes as well.
- The notification centre now localizes titles, filters, read states and the push entry correctly in Arabic; metrics wrap automatically with large RTL text.
- Legacy event-attendance and training-plan detail links now open the API-backed event and plan/log centres; static demo cards and placeholder actions were removed, and the Arabic event/training title is localized.
- Core Arabic navigation, search, feed, membership and status labels are now translated; feed loading and engagement rows wrap safely with large RTL text instead of overflowing.
- Dashboard loading failures now show understandable API user messages instead of raw exception or server text.
- Training-plan image uploads also use a safe localized fallback for unknown network errors instead of raw exception text.

## Public store release notes

German:

```text
Sammel-Update für Airmius Mobile: responsives Design mit Hell-/Dunkelmodus, großen Texten und RTL-Unterstützung, sichere Konto- und 2FA-Flows, API-basierte Dashboard-, Tagesflow- und Workspace-Daten sowie lokalisierte Feed- und Nachrichtenfunktionen. Datei- und Ordnerfreigaben, Chat-Dateien, Team-, Freundschafts- und Vereins-Einladungen sowie Feed-Medien lassen sich jetzt direkt und berechtigungsgeprüft verwalten; Modulstartseiten und Detailbereiche passen Aktionen, Kontraste, Speicheranzeigen und Fehlermeldungen an Sprache und Theme an.
```

English:

```text
Major Airmius Mobile update: responsive light/dark UI with large text and RTL support, secure account and two-factor flows, API-backed dashboard, Today Flow and workspaces, plus localized feed and messaging features. File and folder sharing, chat files, team, friend and club invitations, and feed media can now be managed directly with permission checks; module launchers and detail surfaces adapt actions, contrast, storage status and error feedback to the selected language and theme.
```

French:

```text
Mise à jour majeure d’Airmius Mobile : interface responsive avec modes clair/sombre, grands textes et prise en charge RTL, parcours de compte et 2FA sécurisés, tableau de bord, flux du jour et espaces de travail alimentés par l’API, ainsi que des fonctions localisées pour le fil et la messagerie. Le partage de fichiers et dossiers, les fichiers de chat, les invitations d’équipe, d’amitié et de club ainsi que les médias du fil sont gérables directement avec contrôle des permissions.
```

## TestFlight / internal tester notes

```text
Please test the core mobile flows: login, clubs, membership request, request withdrawal, notifications, messages, events/training, documents, invoices, profile, language/theme persistence and deep links.

Known release gates still require evidence: Flutter analyze, Android/iOS release builds, screenshots, real API QA, secure token storage QA, domain verification, localization QA and privacy/legal sign-off.
```

## Play Console release notes checklist

- German release notes added.
- English release notes added if listing locale is enabled.
- No private backend URLs or credentials included.
- No unverified claims such as "fully certified" or "100% complete".
- Known test limitations documented only in internal/reviewer notes, not public marketing copy.

## App Store Connect release notes checklist

- What's New text added.
- TestFlight notes added.
- Reviewer notes reference the review account runbook.
- No credentials committed to repository files.
- Privacy-sensitive features match App Store Privacy labels.

## Evidence to attach

- Screenshot/export of Play Console release notes.
- Screenshot/export of App Store Connect release notes.
- TestFlight notes confirmation.
- Internal release owner approval.

## Pass criteria

- Public release notes are accurate and do not overpromise.
- Internal tester notes list the exact flows and open evidence gates.
- Reviewer notes link conceptually to the safe store review account.
- No secrets, credentials or private data are included.

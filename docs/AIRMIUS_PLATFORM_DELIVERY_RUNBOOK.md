# Airmius Platform Delivery Runbook

Stand: 9. August 2026

Dieses Runbook beschreibt den sicheren Betrieb der umgesetzten Plattformbasis. Es ergänzt den [Optimierungsplan 2030](AIRMIUS_OPTIMIERUNGSPLAN_2030.md) und ist für Entwicklung, Release, SRE und Datenschutzkoordination bestimmt.

## 1. Umgesetzter Stand

- rollen- und workspaceabhängige App-Shell mit fünf primären Bereichen;
- gemeinsamer Sprachwechsel für Web und API mit `X-App-Locale`, Nutzerpräferenz und `Accept-Language`;
- DE, EN, FR und AR sowie RTL-Shell für Arabisch;
- semantische Servertexte für Authentifizierung, Training, Events, Chat, Verein, Team, Mitglieder und Dateien sowie `Content-Language`-/Textrichtungs-Header auch auf Fehlerantworten;
- empfängerbezogene In-App- und Push-Texte: Benachrichtigungen werden in der gespeicherten Sprache des Empfängers erzeugt und behalten semantische i18n-Metadaten für künftiges Neu-Rendern;
- zentraler Notification Router mit serverseitiger Themen- und Transportpräferenz, Ruhezeiten, Prioritäten und atomarer Deduplizierung; Web und Mobile verwenden denselben Vertrag, kritische Sicherheitsmeldungen bleiben zustellbar und normale Pushs werden während der Ruhezeit verzögert;
- ressourcenschonendes Notification Center: Realtime ist der Fast Path, ein koaleszierter 60-Sekunden-Fallback ersetzt das zusätzliche 5-Sekunden-Polling und reduziert die regelmäßigen Seitenabfragen um rund 92 %;
- versionierter API-Vertrag mit Request-ID, Modul- und Verarbeitungszweck-Headern;
- Idempotenz für authentifizierte API-Mutationen;
- transaktionale Domain-Outbox für Events, Community, Commerce, Learning und Sprache;
- Least-Privilege-Rollen `platform_engineer` und `security_admin`;
- lazy Inertia-Props, koaleszierte Partial Reloads und Realtime-Fast-Path;
- inkrementelle Alttext-Übersetzung: Vollscan nur beim Sprachwechsel, danach nur geänderte DOM-Teilbäume;
- ressourcenschonende Locale-Auslieferung: semantische Kerntexte und 3.515 automatische Legacy-Fallbacks liegen in getrennten dynamischen Chunks; der Kern erhält beim ersten Render Priorität, automatische Texte werden erst im Browser-Leerlauf ergänzt und für Deutsch überhaupt nicht übertragen;
- sichere HTTP-Auslieferung: content-gehashte Vite-Assets werden ein Jahr unveränderlich gecacht und per Brotli beziehungsweise Deflate komprimiert; das Build-Manifest bleibt revalidierungspflichtig, `/api/v1/meta` nutzt einen sprachvarianten ETag mit fünf Minuten Cachezeit und alle anderen API-Antworten sind standardmäßig `private, no-store`;
- konstante Chat-Preview-Kosten: Web und Mobile verwenden denselben Loader für die letzte sichtbare Nachricht; auch 25 Unterhaltungen bleiben bei höchstens fünf SELECTs, während Hide-, Moderations- und Gruppenbeitrittsgrenzen gemeinsam durchgesetzt werden;
- ressourcenschonender Sportler-Tagesflow: Web-Dashboard und Mobile-API teilen personalisierte Trainings-, Ernährungs-, Wasser- und Wochenkontinuitätsdaten; bereits geladene Mahlzeiten, Ziel, Route und Datei-Aggregate werden im Web wiederverwendet, während der eigenständige Service mit sichtbarem Plan höchstens zwölf SELECTs benötigt;
- rollenbasiertes Team Home: Termin, Rückmeldungen, Fahrten, Aufgaben und Gebühren werden ohne Polling per abbrechbarer AJAX-Projektion geladen; operative Rollen sehen notwendige Namen ohne Kontaktadressen, Mitglieder nur eigene Gebühren und externe Vereinsmitglieder keine teaminternen Fahrtdaten; der Vertrag bleibt bei höchstens 14 SELECTs und 200 saisonnahen Terminen;
- gemeinsamer Training-End-to-End-Vertrag: Web und Mobile schreiben Logs und Einträge über dieselbe transaktionale Action, Feedback verwendet einen gemeinsamen Rollen-/Benachrichtigungsservice und Mobile-Listen erzwingen `private`-, `trainer`- und `team`-Sichtbarkeit bereits in der Datenbankabfrage; Plan-Deep-Links werden serverseitig geprüft und öffnen die Dokumentation vorausgewählt;
- asynchrone Training-zu-Leveling-Integration: Nur abgeschlossene Einheiten mit autorisiertem Planbezug oder eigenem, abgeschlossenem GPS-Track erzeugen ein minimiertes Outbox-Ereignis. Der Listener prüft Aggregate-Typ, Empfänger, Zeitpunkt und GPS-Eigentum erneut, vergibt idempotent höchstens einmal täglich 8 XP und sendet genau eine DE/EN/FR/AR-Benachrichtigung. Notizen, Wellness-, Kalorien-, Körper- und Leistungswerte gelangen weder in das Ereignis noch in die XP-Entscheidung;
- zusammengesetzte Hot-Path-Indizes für Notification Center, Chat-Mitgliedschaften, Eventlisten/-Erinnerungen, minütliche Push-Zustellung und Domain-Outbox; der alte Outbox-Index wird beim Upgrade erst nach erfolgreichem Aufbau des neuen Dispatch-Index entfernt;
- begrenzte öffentliche Kataloge: E-Learning liefert höchstens 24 Kurse pro Seite und filtert per partiellem Inertia/AJAX-Reload; Marketplace und E-Learning verwenden vier geordnete Gastkatalog-Indizes statt mit wachsendem Bestand vollständige Tabellen beziehungsweise Payloads zu verarbeiten;
- versionierter Query-Plan-Vertrag `query-plan-readiness.v1`: 25 repräsentative, ausschließlich lesende und auf höchstens 50 Zeilen begrenzte Abfragefamilien prüfen exakte Indexreihenfolgen sowie MySQL-/MariaDB-`EXPLAIN ANALYZE`; Evidenz enthält weder SQL, Bindings, Ergebniszeilen, Planinhalte, Verbindungsdetails noch personenbezogene Daten;
- versionierter Provider-Vertrag `provider-smoke-readiness.v1`: SMTP, Firebase, Stripe-Testmodus und PayPal-Sandbox werden über einen gemeinsamen stagingbegrenzten Auditor geprüft; Zahlungs-Connectivity erzeugt keinen Kauf, Ziele kommen ausschließlich aus geschützten Dateien und Bericht/Evidenz enthalten weder Empfänger, Gerätetoken, Credentials, Payloads, Antworten noch Fehlerdetails;
- versionierter Observability-Vertrag `observability-slo-readiness.v1`: neun verantwortete SLO-Signale verbinden synthetische Gastverfügbarkeit/Core-Web-Vitals, API-Fehler und -Latenz, Queue, Webhooks, Mail, Push und Offsite-Backup mit festen Zielen und Alert-Fristen; Evidenz akzeptiert nur kurze Artefaktreferenzen, reales RUM nur eingewilligt und ab fünf Personen;
- versionierter Cross-Device-Vertrag `cross-device-experience.v1`: Web-Mobile, Web-Desktop, Android und iOS werden mit DE/EN/FR/AR/RTL, acht Kernreisen und sieben Assistive-Technology-Gruppen verbunden; Android/iOS benötigen denselben 19-Punkte-Realgerätevertrag statt einer verkürzten Smoke-Liste, lokale Evidenz kann Mobile-/Plattformmanifeste nie selbst freigeben;
- versionierter Governance-Vertrag `governance-assurance.v1`: Die drei vorhandenen Legal-, DPIA- und unabhängigen Pentest-Gates teilen exakte Releasebindung, getrennte Freigaberollen, nicht aussetzbare Abnahme, 12 Legal-Bereiche, 10 risikoreiche Verarbeitungsfamilien, 9 Art.-35/36-Bausteine sowie 16 Web-/API-/Mobile-/Business-/Privacy-Pentestbereiche; lokale Evidenz speichert weder Reviewer noch Findings und kann niemals selbst freigeben;
- datensparsame Performance-Telemetrie: eine produktionsaktivierte, request-gebundene Messung erfasst nur Dauer, DB-Anzahl/-Zeit, Speicherdelta, Status, benannte Route und Request-ID; SQL, Bindings, URLs, Parameter, IPs und Nutzerkennungen werden weder gesammelt noch geloggt;
- datenschutzorientierte Produktanalyse: ein eigener `analytics.view`-Workspace verdichtet bestehende Fachdaten für Aktivierung, aktive Nutzung, 7-Tage-Bindung, Training, Teams, Käufe und Kündigungen; separate Einwilligung, Volljährigkeit, Mindestkohorten, Feldunterdrückung und ein festes Abfragebudget werden serverseitig erzwungen, ohne Rohereignistabelle, Tracking-SDK oder zusätzliche Cookies;
- öffentlicher Discovery-Layer für Vereine, Events, Sportarten und Sportstädte: SSR-Metadaten, kanonische URLs, JSON-LD und Sitemap-Einträge verwenden ausschließlich verifizierte, freigegebene Organisationsdaten und minimierte Eventfelder; verborgene Teams werden weder benannt noch gezählt, Listen sind hart begrenzt und DE/EN/FR/AR einschließlich RTL teilen denselben Vertrag;
- gehärtete Gastoberfläche: alle 22 öffentlichen Vue-Seiten besitzen einen fokussierbaren Hauptbereich und eine Skip-Navigation; das mobile Menü hält den Tastaturfokus, schließt mit Escape und stellt die Scrollposition wieder her. Filter und Pagination laden per Inertia nur veränderte Props, teure Marketplace-, Blog-, Job-, Vereins- und Discovery-Abfragen bleiben lazy, Kartenbilder dekodieren asynchron und werden außerhalb des sichtbaren Bereichs verzögert geladen;
- private Gastbestellseiten: Token-URLs werden weder als Canonical noch als OpenGraph-URL veröffentlicht, senden server- und clientseitig `noindex`, `no-store` und `no-referrer` und sind gedrosselt. Sitemap, RSS und robots.txt verwenden öffentliche Cachegrenzen, ETags und bedingte 304-Antworten; Sitemap und RSS werden zusätzlich zehn Minuten serverseitig wiederverwendet;
- automatische Aufbewahrungsbereinigung für Delivery-Daten;
- sichere Organisationskontexte für direkte und teamvermittelte Vereinszugriffe;
- durchgängig lokalisierte Trainingsplanung: Zielgruppenwahl im Web sowie Log-, Plan-, Ausfall- und Feedback-Benachrichtigungen verwenden semantische DE/EN/FR/AR-Schlüssel; Empfänger werden pro Vorgang gebündelt statt einzeln nachgeladen;
- ressourcenschonender Trainingsarbeitsraum: Übersicht, Logs, Wochenansicht, Analyse, Plankarten, Leerzustände und Aktionen verwenden sofort verfügbare DE/EN/FR/AR-Texte aus dem ausschließlich mit der Trainingsseite geladenen Chunk. Wochenpositionen lassen sich per Drag-and-drop und Tastatur verschieben, Berechtigungen werden vor der Interaktion berücksichtigt, Fortschritte sind semantisch ausgezeichnet und ein KI-Plan-Refresh lädt per Inertia nur `plans` und `aiCapabilities` statt Logs, Aktivitäten, Teams und Routen erneut abzufragen;
- vollständig lokalisierte operative Workflows für Editorial, Rollen-Workspace, Guardian, Vereinsprüfung, Vereinsumfragen, Ankündigungen, Support, Kontosicherheit, Feed, Chat, Gamification und Benachrichtigungs-Digests; das responsive AJAX-Supportcenter lädt Verlauf und Operations-Arbeitsliste ausschließlich bei Bedarf, bricht überholte Requests ab und pollt nicht; nutzerbezogene Meldungen und Vereinsprüfungs-E-Mails verwenden die gespeicherte Empfängersprache;
- zentraler Admin-Operations-Falleingang mit getrennten Platform-, Trust-&-Safety- und Revenue-Arbeitsräumen; pro Quelle höchstens acht datensparsame Fälle, feste Querybudgets, abbrechbares Lazy Loading ohne Polling, rollenabhängige Fachziele, minimierte Audit-Timeline und DE/EN/FR/AR einschließlich RTL;
- gemeinsame globale Command Palette für Web und Mobile: sichtbarkeitsgeprüfte, fair verteilte Inhaltstreffer plus 27 berechtigungsabhängige Funktionsziele mit vollständigen Web-URLs und stabilen nativen Mobile-Schlüsseln, rollenbegrenzte E-Mail-Suche, höchstens 16 SELECTs, abbrechbare 300-ms-Webrequests, 320-ms-Mobile-Debouncing ohne Leeraufruf, eigener Rate-Limiter, private No-Store-Antworten sowie DE/EN/FR/AR und RTL;
- Privacy Center v2 für Web und Mobile: drei getrennte optionale Einwilligungen, Datenrechte und minimierte verbundene Anbieter in einer Oberfläche; der native Client verwendet einen eigenen `private/no-store`-Vertrag mit höchstens drei SELECTs statt des vollständigen Einstellungs-/Billing-Payloads und erhält keine Provider-Tokens, Scopes, externen Kennungen, Anzeigenamen oder Anbieter-E-Mail-Adressen;
- tab-spezifische Web-Einstellungen: Der Erstaufruf liefert nur Basis-Props; Billing, Aktivitäten, Rollen, Sportprofile, Integrationen und Privacy-Provider werden per Inertia-Partial-Request erst bei Bedarf aufgelöst. Bereits geladene, tabübergreifende Props werden in der laufenden Sitzung wiederverwendet, direkte Tab-URLs bleiben vollständig, und Lade-/Fehlerzustände erhalten den bestehenden Formularzustand ohne Polling;
- gemeinsamer DSGVO-Datenlöschungsvertrag für Web, API und Mobile: serverdefinierte Kategorien, automatisch erkannte Passwort-/Social-Login-Bestätigung, an die Auswahl gebundene Einmalcodes, gedrosselte Endpunkte und empfängerbezogene DE/EN/FR/AR-E-Mails;
- konsolidierter Commerce-Käufervertrag für Web und Mobile: Warenkorb, Checkout, Bestellaktionen, Verkäuferantworten und Bestellbenachrichtigungen verwenden semantische DE/EN/FR/AR-Schlüssel; eine ungenutzte zweite Warenkorb-Implementierung wurde entfernt;
- vereinheitlichter öffentlicher Commerce-Katalog `commerce-card.v1` für Produkte, Kurse und Outfit-Abos: höchstens eine begrenzte SELECT-Abfrage je Art, minimierte öffentliche Felder, gemeinsamer responsiver Gastbereich, Inertia-Partial-Reload, DE/EN/FR/AR/RTL sowie locale-sensitive ETag-/304-Auslieferung ohne Aufweichung der privaten API-Cachegrenze;
- konsolidierter Admin-Commerce-Workspace: Die zentrale Seite wurde von 2.078 auf 441 Zeilen reduziert und setzt die vorhandenen Fachpanels als einzige UI-Quelle ein; die aktiven Commerce- und Outfit-Komponenten sind auf logische RTL-Klassen und semantische Sprachschlüssel umgestellt;
- konsolidierter Outfit-Abo-Workspace: Die zentrale Adminseite wurde von 1.784 auf 703 Zeilen reduziert; Anfrage-, Zahlungs- und Problembenachrichtigungen sowie Web-/API-Antworten werden in DE, EN, FR oder AR in der Sprache des jeweiligen Empfängers erzeugt;
- konsolidierte Learning-Journey: Web, Mobile/API, Studio und bezahlter Marketplace-Zugang verwenden gemeinsame Services für Einschreibung, Drip-Freigabe, Fortschritt, Quiz, Abschluss und Zertifikate; abgeschlossene Kurse regressieren bei später ergänzten Inhalten nicht, leere Kurse stellen kein Zertifikat aus und Fachbenachrichtigungen sind empfängerbezogen lokalisiert sowie dedupliziert;
- transaktionale Konto-Abo-Aktivierung: Webhooks, Web-Backoffice und Mobile/API verwenden denselben gesperrten Aktivierungsservice; wiederholte Zahlungsbestätigungen erhöhen Coupons nur einmal und erzeugen weder doppelte Abos noch doppelte Rechnungsbestätigungen; In-App-Mitteilungen und Abo-Rechnungs-E-Mails unterstützen DE, EN, FR und AR;
- konsolidierter Konto-Abo-Lebenszyklus: Web, Mobile/API und Admin-Backoffice verwenden denselben transaktionalen Service für Kündigung, Wiederaufnahme, Laufzeitverlängerung und Fälligkeitsabschluss; Stripe und PayPal werden zentral synchronisiert, während ein Providerfehler lokal protokolliert und ohne kostenlose Laufzeitverlängerung behandelt wird;
- vertragsgetreue Entitlements: vorgemerkte Kündigungen behalten Zugriff exakt bis zum Vertragsende, `past_due` nur innerhalb der Zahlungsfrist und abgelaufene, gesperrte oder endgültig gekündigte Abos gewähren keine Premiumfunktionen; normale Nutzer können nur eine vorgemerkte Kündigung zurücknehmen, manuelle Monatsverlängerungen benötigen `subscriptions.manage`;
- Least-Privilege für Vereins-Abos: Owner, Vereins-Admins, Manager und Finanzverantwortliche dürfen das eigene Vereins-Abo verwalten; Academy Manager und normale Mitglieder nicht; empfangsbezogene Lifecycle-Mitteilungen werden dedupliziert in DE, EN, FR oder AR erzeugt;
- ressourcenschonender Abo-Admin: Nutzer und Vereine werden serverseitig gesucht und auf 100 Treffer begrenzt; Mutationen laden per Inertia-Partial-Reload nur betroffene Props nach und destruktive Aktionen besitzen eine explizite Bestätigung;
- gemeinsamer Recruiting-Vertrag für Web und Mobile: ausschließlich veröffentlichte Stellen, datensparsame öffentliche Karten ohne Kontakt-E-Mail, gefilterte `http`-/`https`-Bewerbungslinks, gedrosselte und idempotente Interessenbekundungen sowie asynchrone Benachrichtigungen;
- gemeinsamer Recruiting-Pipeline-Vertrag für Web, API und Mobile: rollen- und vereinsgebundene Bearbeitung, serverseitige Filter und Kennzahlen, sieben nachvollziehbare Status, interne Notizen, explizite Datenschutzeinwilligung, pseudonymisierte IP-Adressen sowie Export-, Lösch- und Aufbewahrungsregeln;
- integrierter Recruiting-Opportunity-Vertrag: Sport-/Erfahrungskriterien, feldweise und zweckgebundene Profilfreigabe, separate In-App-Kontakteinwilligung, erklärbarer assistiver Abgleich ohne automatische Entscheidung, serverseitig begrenzte Statusübergänge, bewerbungsbezogener Chat im bestehenden Chatmodul und Benachrichtigungsübergabe in den vorhandenen Mitgliedschaftsprozess; das Abfragebudget bleibt bei wachsender Bewerberzahl konstant;
- gemeinsamer Mitglieder-Lifecycle: Web und Mobile/API verwenden eine transaktionale Statusmaschine für Antrag, Rücknahme, Pause, terminierten Austritt, Freigabe und Ablehnung. Typspezifische Pflichtdokumente, Beitragsvorschau und normalisierte SEPA-Übernahme folgen einem Vertrag; alle Übergänge werden ohne Antrags-, Dokument-, Signatur-, IP- oder User-Agent-Inhalte auditiert. Im Web ersetzt der geprüfte Austrittsantrag die sofortige irreversible Trennung;
- gemeinsamer Refund-/Return-/Payout-Vertrag: Web, API, Mobile und Retouren schreiben in ein idempotentes Erstattungsledger, Providerfehler bleiben lokal wirkungslos, Restbeträge werden unter Sperre geprüft, Mehrfach-Restock wird verhindert und Verkäuferauszahlungen werden vor Zahlung korrigiert oder nach Zahlung als Recovery-Fall sichtbar markiert;
- gemeinsamer Auszahlungs-Lifecycle: Seller-Web/API und Admin-Web/API wählen auszahlbare Orders ausschließlich innerhalb derselben gesperrten Transaktion, erzeugen die Referenz aus der tatsächlich angelegten ID und verwenden dieselben Währungs-, Status-, Recovery- und Auditregeln; Admin-Kandidaten werden speicherbegrenzt in 250er-Blöcken gelesen, Seller-Summen bleiben bei drei Aggregatabfragen und vorbereitete beziehungsweise ausgeführte Auszahlungen benachrichtigen in DE/EN/FR/AR;
- ressourcenschonender Sponsor-/Agency-Workspace `growth-workspace.v1`: vorhandene Briefings, Sponsor-Deals, Creative-Assets, Kampagnen und aggregierte Outcomes sind tenant-sicher verbunden; Agency-Kontakte und Freitexte bleiben ausgeschlossen, Listen und Suchergebnisse sind hart begrenzt, Kennzahlen werden in SQL aggregiert, das Gesamtbudget bleibt bei höchstens 16 SELECTs und ein Inertia-Partial-Refresh lädt ausschließlich das Workspace-Prop; alle neuen Antworten/Oberflächen besitzen DE/EN/FR/AR-/RTL-Verträge, deren seitenspezifische Texte nur mit dem lazy Sponsor-Chunk geladen werden;
- integrierter Werbeagentur-Vertrag: Web und Mobile senden an denselben gedrosselten und idempotenten API-Workflow; eine explizite Einwilligung, dokumentierte Statuswechsel, Selbstauskunft, Selbstlöschung und automatische Aufbewahrungsbereinigung schützen Anfrage- und Kontaktdaten;
- ressourcenschonende Ads-Auslieferung: Frequenz- und Conversion-Signale werden unabhängig von der Kandidatenzahl gebündelt geladen, wiederholte Einstellungen pro Request zwischengespeichert, Creatives gemeinsam ausgewertet, Standard-CTAs lokalisiert und externe Ziele auf sichere `http`-/`https`-URLs begrenzt;
- versionierter Revenue-Trust-Vertrag: neue Verkäufer- und Sponsorfreigaben sind an nachvollziehbare Pflichtangaben gekoppelt, Self-Service-Sponsoren bleiben bis zur Prüfung privat, Auszahlungsfreigaben dokumentieren Prüfer und Zeitpunkt und das Sponsor-Reporting liefert ausschließlich gebündelte 28-Tage-Aggregate ohne Tracking-Kennungen;
- Mobile-Härtung für rollenbasierte Adminbereiche, eingebettete Zugriffssperren und Marketplace-Direktzugriff auf den Warenkorb.
- zentraler Mobile-Modulzielvertrag für alle 34 produktiv sichtbaren Module: Shell, Drawer und Persona-Home verwenden dieselbe API-gestützte Zuordnung; Recruiting und Trainingsplanung besitzen explizite Ziele, Sponsorrollen öffnen das Sponsor-Cockpit und nur Rollen-Startseiten werden ohne zusätzlichen Launcher-Tap weitergeleitet. Der generische Detail-Fallback bleibt auf ausgeblendete Entwicklersuiten begrenzt;
- zentraler Kernreisevertrag `critical-journeys.v1`: Sportler-Tag, Trainer-Woche, Mitglieder-Lifecycle, Sponsor-Messung und Recruiting-zu-Team referenzieren reale Web-/API-Schritte, fünf beteiligte Personas, Fachdomäne, Verantwortliche und Datenschutzgrenzen; der vollständige Rollenvertrag enthält zusätzlich die getrennte Plattform-Admin-Persona. Alle 550 zweckgebundenen API-Routen lösen eindeutig zu einem Modul auf; öffentliche Produkt-APIs senden denselben Governance-Vertrag. Das Mobile-Meta erhält nur die minimierte technische Journey-Projektion ohne interne RACI-, Webroute- oder Privacy-Details;
- zustandsloser Stufenrollout `staged-rollout.v1` für vier angemeldete Arbeitsräume: erlaubte 0/5/25/100-Stufen, stabile HMAC-Zuordnung, globale und featurebezogene Kill-Switches, serverseitig autorisierter Pilot-Override und DE/EN/FR/AR-Fallbacks ohne Rollout-Tabelle, Cookie oder Tracking; alle Gast- und öffentlichen API-Flächen bleiben außerhalb der Actor-Zuordnung und behalten ihre Cache-, SEO-, DSGVO-, RTL- und Accessibility-Verträge;

## 2. Release-Reihenfolge

Die Migrationen sind additiv. Der Anwendungscode darf erst nach erfolgreicher Migration aktiviert werden.

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan optimize
php artisan queue:restart
```

Danach müssen genau ein Laravel-Scheduler und ausreichend Queue-Worker aktiv sein. Ohne Queue-Worker bleiben Outbox-Ereignisse sicher gespeichert, werden aber nicht an nachgelagerte Verbraucher ausgeliefert.

Die Migration `2026_08_08_000006_add_hot_path_query_indexes` ändert keine Fachdaten, baut jedoch Indizes auf potenziell großen Tabellen. Sie ist außerhalb der Spitzenlast auszuführen. Vor dem Produktionslauf müssen SRE/DBA Tabellenumfang und freie Kapazität prüfen; bei großen MySQL-Tabellen ist die im Hosting freigegebene Online-DDL-Strategie zu verwenden. Der automatisierte SQLite-Lauf hat Up und Down vollständig verifiziert, ersetzt aber keinen `EXPLAIN ANALYZE` auf dem produktionsgleichen MySQL-/MariaDB-Staging.

Die Migration `2026_08_08_000007_add_product_analytics_consent_to_users` ergänzt ausschließlich Opt-in-Felder mit sicherem Standard `false` sowie den Kohortenindex. Bestehende Konten werden dadurch nicht eingewilligt. Der Code darf in Produktion ausgerollt werden, während `PRODUCT_ANALYTICS_ENABLED=false` bleibt; die Freischaltung erfolgt erst nach den in Abschnitt 8 genannten Freigaben.

Die Migration `2026_08_08_000008_add_public_discovery_indexes` ergänzt vier zusammengesetzte Indizes für öffentliche Vereins-, Sport-, Stadt- und Eventabfragen. Sie ändert keine Fachdaten, kann auf großen produktiven Tabellen aber DDL-Last erzeugen. Deshalb gelten dieselben DBA-, Kapazitäts- und Online-DDL-Vorgaben wie für die Hot-Path-Migration; die tatsächliche Indexnutzung ist im produktionsnahen MySQL-/MariaDB-Staging mit `EXPLAIN ANALYZE` zu bestätigen.

Die Migration `2026_08_08_000009_add_revenue_trust_fields` ergänzt additive, nullable Vertrags-/Steuerfelder sowie zwei Sponsor- und einen Auszahlungsprüfindex. Bestehende administrativ gepflegte Sponsoren bleiben zur Rückwärtskompatibilität verifiziert; ausschließlich neue oder erneut gespeicherte Self-Service-Profile wechseln in den aktuellen Prüfvertrag. Der Rollout muss die Indexanlage auf `sponsors` außerhalb der Spitzenlast vornehmen und vor Aktivierung des neuen Codes abgeschlossen sein. Steuer-, Register- und Zahlungsdaten dürfen nicht in Logs, öffentliche Exporte oder Release-Evidenz übernommen werden.

Die Migration `2026_08_09_000008_add_guest_catalog_query_indexes` ergänzt zwei geordnete Zugriffspfade für den öffentlichen Marketplace und zwei für den öffentlichen E-Learning-Katalog. Sie verändert keine Fachdaten. Die Anlage erfolgt nach DBA-Kapazitätsprüfung und freigegebener Online-DDL-Strategie außerhalb der Spitzenlast; erst danach darf der neue Anwendungscode aktiviert werden.

### 2.1 Einheitlicher Release-Preflight

Der zentrale, nur lesende Einstieg bündelt Repository-, Produktionskonfigurations- und externe Evidence-Gates, ohne Tests oder Provideraufrufe unnötig zu wiederholen:

```bash
composer preflight
php artisan airmius:release-preflight --json
php artisan airmius:release-preflight --with-operations --json
php artisan airmius:release-preflight --strict
```

Der normale Lauf gibt `1` nur bei einem tatsächlich fehlgeschlagenen automatischen oder bereits negativ bewerteten externen Gate zurück. Offene Nachweise bleiben als `PENDING` sichtbar, damit lokale Entwicklungs- und CI-Prüfungen nicht durch organisatorisch noch nicht mögliche Arbeiten blockiert werden. `--strict` ist das verbindliche finale Go/No-Go: Auch jeder offene oder in dieser Umgebung übersprungene Nachweis führt zu Exit-Code `1`. `--with-operations` ergänzt die bestehenden Liveprüfungen für Datenbank, Queue, Scheduler, Webhooks, Mail, Push und Backup; dieser Modus gehört in Staging beziehungsweise Produktion, nicht in jeden Commit-Lauf.

Die zwölf nicht automatisierbaren Plattformgates mit Verantwortlichkeiten stehen in `resources/release/platform_release_gates.json`. Ein Gate darf nur mit nicht-sensitiven Ticket-, Artefakt- oder Freigabereferenzen auf `passed` beziehungsweise begründet auf `waived` gesetzt werden; dafür sind `reviewed_by` und `reviewed_at` verpflichtend. Zugangsdaten, Tokens, personenbezogene Testdaten und vollständige Headerausgaben gehören weder in dieses Manifest noch in Release-Tickets. Die detaillierten Mobile-/Store-Nachweise bleiben im bestehenden Mobile-Manifest; der zentrale Preflight liest dessen Status lediglich zusammengefasst.

Mobile-Evidenz wird zweistufig geführt. `store_listing/release/release_evidence_manifest.json` bleibt die versionierte, autoritative Freigabebasis und darf nur nach Review geändert werden. Linux-Läufe schreiben mit `scripts/sync_linux_release_evidence_manifest.sh` ausschließlich die ignorierte Datei `release_evidence/release_evidence_manifest.local.json`; sie kann den Preflight informieren, aber niemals ein Store- oder Human-Gate freigeben. Der Sync akzeptiert keine bloß vorhandenen Logs: Analyse, Version, Konfiguration, Builds, Signatur/Integrität und Bundle-Prüfung benötigen jeweils explizite Erfolgsmuster und nicht leere Artefakte. Vor jedem lokalen Android-Lauf sind auszuführen:

```bash
scripts/assert_linux_android_release_prerequisites.sh
scripts/assert_release_version_consistency.sh
scripts/sync_linux_release_evidence_manifest.sh
```

Für Version `1.0.33+77` sind die lokalen Voraussetzungen, `flutter analyze` und der signierte Android-Release-Build nachgewiesen, also 3 von 22 Mobile-Gates. Das AAB und das APK wurden zusätzlich als ZIP, mit Android-Signatur und Mindest-SDK 24 geprüft. Die übrigen 19 Gates bleiben im autoritativen Manifest bewusst offen; insbesondere ersetzen lokale Artefakte weder Store-Paketierung noch Review, Realgerät, iOS-Signierung oder Datenschutzfreigabe.

Flutter 3.44 unterstützt bei AGP 9 ältere Kotlin-Plugins nur über den dokumentierten Übergangsmodus. Deshalb bleiben `android.builtInKotlin=false` und `android.newDsl=false` gesetzt. Die App wird erst zusammen mit Flutter 3.47 oder neuer und nach nachgewiesener Kompatibilität aller Plugins auf Built-in Kotlin migriert; eine Warnung allein rechtfertigt weder ein Dependency-Override noch eine ungeprüfte Plugin-Fork.

Die Produktionsgrenzen dieses Hosts sind nun auf den Produktnamen, die vollständige HTTPS-Domain und sichere Session-Cookies korrigiert; der automatisierte Konfigurationscheck ist grün. Die Ausgabe nennt bei Abweichungen weiterhin ausschließlich betroffene Variablennamen und zeigt keine Konfigurationswerte oder Geheimnisse. Der Gesamtentscheid bleibt bis zu den zwölf externen Plattform-Evidence-Gates bewusst `no_go`.

Der Stufenrollout wird mit `php artisan airmius:audit-staged-rollout --json` geprüft. Die produktive Bedienung, Reihenfolge, Beobachtungszeiten, Kill-Switches, datensparsame Evidenz und der Gastseiten-Smoke-Test stehen im [Staged-Rollout-Runbook](STAGED_ROLLOUT_RUNBOOK.md). `--strict` bleibt bis zur real beobachteten 5/25/100-Freigabe absichtlich rot.

### 2.2 Produktionsnahe Query-Pläne

Der sichere Standardlauf kompiliert alle 25 begrenzten SELECT-Verträge und prüft die exakten Indexreihenfolgen. Autoritative Laufzeitpläne werden ausschließlich auf der release-identischen Staging-Instanz mit `APP_ENV=staging`, MySQL oder MariaDB und einem nicht-sensitiven DBA-Artefaktverweis erzeugt:

```bash
php artisan airmius:audit-query-plans \
  --connection=mysql \
  --analyze \
  --online-ddl-reference=DBA-RELEASE-ARTEFAKT \
  --json --strict
```

`--analyze` ist in einer als Produktion konfigurierten Anwendung gesperrt. Jede registrierte Abfrage ist ausschließlich lesend, verwendet neutrale Bindings und liefert höchstens 50 Zeilen; trotzdem wird der Lauf außerhalb der Staging-Spitzenlast ausgeführt. Die Ausgabe nennt nur stabile Check-IDs und Status, niemals Connection-Namen, SQL, Bindings, Ergebniszeilen, Plantext, Fehlerdetails oder personenbezogene Werte. `resources/release/query_plan_evidence.template.json` definiert den Evidenzvertrag; lokale Ergebnisse gehören nur in die ignorierte Datei `resources/release/query_plan_evidence.local.json`.

Ein grüner Auditor ersetzt nicht die DBA-Sichtprüfung der spezialisierten Join-, Rollen-, Locking- und Chunking-Pfade, die im Plattform-Gate `mysql_query_plans` ausdrücklich aufgeführt sind. Das Gate wird erst nach dieser Review und dem versionsgebundenen Online-DDL-Nachweis freigegeben.

### 2.3 HTTP-Auslieferung

Die Apache-Regeln in `public/.htaccess` benötigen `mod_headers` und `mod_filter` sowie bevorzugt `mod_brotli`; ohne Brotli wird bei vorhandenem `mod_deflate` gzip verwendet. Ein vorgeschalteter Nginx-, CDN- oder Hosting-Proxy muss dieselben Grenzen beibehalten:

| Antwort | Verbindliche Cache-Regel |
|---|---|
| `/build/assets/*` | `public, max-age=31536000, immutable`; ausschließlich content-gehashte Vite-Dateien |
| `/build/manifest.json` | `no-cache, must-revalidate`; niemals immutable |
| anonymes `/api/v1/meta` ohne Cookie/Authorization | `public, max-age=300`, sprachabhängiges `Vary`, schwacher ETag, `304` bei Treffer |
| API mit Cookie, Authorization, Nutzerkontext oder Fehler | `private, no-store, no-cache, must-revalidate, max-age=0` |
| explizit private Downloads | private Browser-Cachezeit des jeweiligen Controllers; niemals Shared Cache |

Ein Browser-Origin, den Sanctum als stateful behandelt, bleibt auch beim Meta-Endpunkt privat. `ETag`, `Content-Language`, `X-Request-Id` und die API-Vertragsheader sind für erlaubte CORS-Origins lesbar. Neue öffentliche Cachefreigaben dürfen nur nach Datenklassifikation und einem Contract-Test in `EnforceApiCachePolicy::PUBLIC_REFERENCE_ROUTES` ergänzt werden; eine pfadbasierte pauschale Freigabe ist nicht zulässig.

Nach jedem Proxy- oder Webserverwechsel sind mindestens diese Fälle gegen die echte Staging-URL zu prüfen:

```bash
audit_order_path_file="$(mktemp)"
chmod 600 "$audit_order_path_file"
# In die Datei genau einen aktuellen relativen Gastbestellpfad schreiben,
# beispielsweise /checkout/guest-commerce/<order>/<token>/success.
php artisan airmius:audit-staging-http \
  --base-url=https://staging.example \
  --guest-order-path-file="$audit_order_path_file" \
  --json --strict
```

Der versionierte Vertrag `staging-http-delivery.v1` führt 19 begrenzte Requests aus und prüft TLS/HSTS, CSP, MIME-Schutz, komprimierte immutable App-Assets, das revalidierte Manifest, öffentliches API-Caching samt `304`, private Authorization-/Fehlerantworten, CORS, robots/Sitemap/RSS, drei repräsentative Gastseiten, den Marketplace-Partial-Reload und eine echte tokenisierte Gastbestellseite. Ohne Gastbestellpfad bleibt nur dieser Check `pending`; `--strict` ist dann rot. Ein ungültiger Basiswert beendet den Lauf vor dem ersten Request. Bericht und Fehlertexte enthalten weder Basis-/Gast-URL noch Headerwerte, Bodies, ETags, Zugangsdaten oder Tokens. Die Vorlage `resources/release/staging_http_delivery_evidence.template.json` ist ausschließlich für nicht-sensitive Referenzen bestimmt. Der lokale Pfad kann in der ignorierten Datei `resources/release/staging_http_delivery_evidence.local.json` dokumentiert werden; der echte Token gehört nicht hinein. Die geschützte temporäre Eingabedatei ist nach dem Lauf vom Operator sicher zu entfernen.

Die repräsentativen Gastseiten, der AJAX-Filter und die private Gastbestellung sind bei jedem Release verpflichtend. Ein grüner eingeloggter Workspace oder Providerlauf darf einen offenen Gastseiten-, Core-Web-Vitals-, Crawler-, RTL- oder Accessibility-Nachweis nicht überstimmen.

### 2.4 Provider-Smoke-Tests

Der Standardlauf ist rein prüfend und führt keine externen Aufrufe aus. Der Live-Lauf ist ausschließlich in `APP_ENV=staging` erlaubt, akzeptiert für Stripe nur Testschlüssel und für PayPal ausschließlich Sandbox-Konfiguration. Die Connectivity-Prüfung erzeugt weder Checkout noch Zahlung; Checkout, signierte Webhooks, Retry, Refund, Gutschrift, Payout und Reconciliation benötigen zusätzlich eine geprüfte, nicht-sensitive Evidenzreferenz.

```bash
smtp_target_file="$(mktemp)"
fcm_target_file="$(mktemp)"
chmod 600 "$smtp_target_file" "$fcm_target_file"
# Jeweils genau ein dediziertes Testpostfach beziehungsweise ein Token
# eines zustimmenden Testgeräts in die geschützte Datei schreiben.
php artisan airmius:audit-providers \
  --live \
  --smtp-mailer=smtp_support \
  --smtp-recipient-file="$smtp_target_file" \
  --fcm-token-file="$fcm_target_file" \
  --smtp-receipt-reference=MAIL-RELEASE-ARTEFAKT \
  --fcm-receipt-reference=FCM-RELEASE-ARTEFAKT \
  --payment-evidence-reference=PAYMENT-RELEASE-ARTEFAKT \
  --json --strict
```

Die beiden Eingabedateien müssen unmittelbar nach dem Lauf vom Operator sicher entfernt werden. Weder ihre Pfade noch Inhalte dürfen in CI-Logs, Tickets oder `resources/release/provider_smoke_evidence.local.json` gelangen. Der 16-stellige neutrale Receipt-Code verbindet ausschließlich den Lauf mit der manuellen Postfach-/Geräteprüfung. SMTP-Fallback und erneuter Versand, Push-Retry und Geräteinvalidierung, Webhook-Signaturen sowie idempotente Refund-/Payout-Reconciliation bleiben zusätzlich durch lokale Vertragstests abgesichert.

### 2.5 Observability und SLOs

Der repositoryweite Standardlauf ist schnell und greift weder auf Runtime noch externe Dashboards zu. In der release-identischen Staging-Umgebung werden anschließend der begrenzte Operations-Monitor, mindestens 24 Stunden Evidenz und der ownergebundene Gate verbunden:

```bash
php artisan airmius:audit-observability --json
php artisan airmius:audit-observability --with-runtime --json --strict
```

Der Runtime-Lauf verlangt aktivierte Performance-Telemetrie, externes Error Monitoring, eine workerbasierte Queue, Push-Monitoring und ein privates Offsite-Backup. Verbindliche Ziele sind 99,9 Prozent Verfügbarkeit, höchstens 1 Prozent API-/Webhook-5xx, Server-/DB-p95 von höchstens 1.000/500 ms, null stale Queue-/Push-Einträge, höchstens 2/5 Prozent Mail-/Push-Fehler und ein höchstens 30 Stunden altes Offsite-Backup samt Restore-Nachweis. Vereine, Marketplace und E-Learning besitzen zusätzlich p75-Budgets von LCP 2.500 ms, INP 200 ms und CLS 0,1.

`resources/release/observability_evidence.template.json` verlangt je Signal kurze Dashboard-, Alert-Test- und Verifikationsreferenzen. Direkte Dashboard-URLs, Hosts, Pfade, Querystrings, Header, Bodies, Traces, Tokens, Secrets und Personen-/Geräte-/Vereins-/Bestellkennungen werden abgelehnt. Gastmessungen erfolgen bevorzugt synthetisch; Real-User-Monitoring benötigt Einwilligung und Mindestgruppe fünf. Die lokale, ignorierte Evidenz kann den externen Gate nicht selbst freigeben. Ausführliche Reaktion und Schwellenwerte stehen im [Operations Monitoring Runbook](OPERATIONS_MONITORING_RUNBOOK.md).

### 2.6 Cross-Device, Sprache und Accessibility

Der schnelle Koordinationslauf prüft Versionen, Web-/Mobile-Artefakte, das bestehende Mobile-Manifest, die Evidenzmatrix und die exakte Parität zwischen Android-/iOS-Vorlagen und dem Realgerätevalidator:

```bash
php artisan airmius:audit-cross-device --json
php artisan airmius:audit-cross-device --json --strict
```

Die Matrix umfasst vier Plattformen (`web_mobile`, `web_desktop`, `android`, `ios`), DE/EN/FR/AR mit echtem RTL, acht Reisen von Gast-Discovery/Checkout bis Refund-/Payout-Reconciliation und sieben Accessibility-Gruppen von Keyboard/NVDA/JAWS bis TalkBack/VoiceOver, 200/400-Prozent-Zoom, Reflow, Forced Colors und reduzierte Bewegung. Der native Android-/iOS-Vertrag verlangt zusätzlich exakt 19 Punkte: Build, sichere Sitzung, Push, Eventdateien, Deep Links, Route→Training/Event, Event-Log, Recruiting-Einwilligung/Handoff, Refund-Doppelschutz, Payout/Recovery, GPS-Eigentum, vier Sprachen/RTL, Assistive Technology, 200-Prozent-Text und Privacy Review.

`resources/release/cross_device_evidence.template.json` speichert nur Status und kurze Artefaktreferenzen. Roh-URLs/-Pfade, Gerätekennungen, Kontakte, Kontodaten, Tokens, Secrets, Zahlungsdaten, Testeridentität und Freitext sind verboten. Reviewer und Freigaben bleiben ausschließlich in den autoritativen Mobile-/Plattformmanifesten. Ein vollständiges lokales JSON kann offene Realgeräte-, Muttersprachler- oder WCAG-Gates daher nicht hochstufen. Die physische Ausführung steht im [Mobile Real Device Runbook](../mobile/airmius_mobile/store_listing/release/real_device_smoke_test_runbook.md).

### 2.7 Legal, DPIA und unabhängiger Penetrationstest

Der gemeinsame Koordinationslauf verwendet ausschließlich die drei bestehenden autoritativen Plattformgates und legt keine parallele Freigabequelle an:

```bash
php artisan airmius:audit-governance --json
php artisan airmius:audit-governance --json --strict
php artisan airmius:audit-legal-readiness --json
```

Der technische Lauf besteht mit acht Repository-Prüfungen. Legal, die datenschutzbeauftragte Person und ein unabhängiger Security Assessor bleiben getrennte Freigaberollen; Release Management verwaltet nur Manifest und Gesamtentscheid. Für diese drei Gates ist `waived` ein Fehler. Alle zwölf Legal-Bereiche, zehn Airmius-Verarbeitungsfamilien, neun DPIA-Abschnitte, 16 Pentest-Scope-Gruppen und acht Abnahmeregeln müssen die exakte Release-Version abdecken. Kritische und hohe Pentest-Befunde benötigen Behebung und unabhängigen Retest.

`resources/release/governance_evidence.template.json` akzeptiert nur kurze, nicht sensitive Referenzen. Namen, Kontakte, Rohberichte, Findings, Exploitdetails, URLs, Pfade, Requests/Responses, Identifikatoren, Zahlungsdaten und Secrets sind verboten und werden nicht ausgegeben. Vollständiger Ablauf, Rollen und offizielle DSGVO-/EDPB-/OWASP-/BSI-Grundlagen stehen im [Governance Release Runbook](GOVERNANCE_RELEASE_RUNBOOK.md).

## 3. Scheduler und Queue

Neue geplante Aufgaben:

| Aufgabe | Takt | Zweck |
|---|---:|---|
| `airmius:dispatch-domain-outbox` | jede Minute | wartende oder wiederholbare Outbox-Ereignisse einreihen |
| `airmius:mobile-push-dispatch --limit=500` | jede Minute | fällige, präferenz- und ruhezeitgeprüfte Push-Zustellungen ausliefern |
| `airmius:send-notification-digests` | täglich 18:00 | lokalisierte E-Mail-Zusammenfassung für freigegebene Empfänger senden |
| `airmius:send-subscription-invoice-emails` | täglich 08:15 | fällige Abo-Rechnungen einmalig und in der Sprache des Empfängers versenden |
| `airmius:process-subscription-lifecycle` | täglich 08:45 | fällige Konto- und Vereins-Abos idempotent beenden und Entitlements entziehen |
| `airmius:prune-ad-events --limit=1000` | täglich 03:45 | abgelaufene Analytics-/Ads-Ereignisse in begrenzten Batches löschen |
| `airmius:prune-website-requests --limit=1000` | täglich 03:48 | abgelaufene, nicht aktiv bearbeitete Werbeagentur-Anfragen begrenzt löschen |
| `airmius:prune-recruiting-interests --limit=1000` | täglich 03:50 | abgelaufene Bewerbungsinteressen datensparsam und begrenzt löschen |
| `airmius:prune-expired-stories --limit=500` | stündlich | abgelaufene Stories samt Medien in begrenzten Batches entfernen |
| `airmius:prune-platform-delivery --outbox-days=30 --failed-outbox-days=90 --limit=1000` | täglich 03:55 | abgelaufene Idempotenzdaten sowie alte ausgelieferte/fehlgeschlagene Events begrenzt löschen |

Prüfung ohne produktiven Cache-Lock:

```bash
CACHE_STORE=array php artisan schedule:list
php artisan airmius:dispatch-domain-outbox --limit=100
```

Aufbewahrung:

- abgelaufene Idempotenzantworten werden entfernt;
- Agentur-Anfragen werden regulär zwölf Monate, abgeschlossene oder stornierte Anfragen sechs Monate nach dem letzten Statuswechsel vorgehalten; aktive Projekte werden nicht automatisiert entfernt;
- Recruiting-Interessen erhalten ein explizites Löschdatum; Statuswechsel setzen die sechsmonatige Aufbewahrungsfrist neu;
- veröffentlichte Outbox-Ereignisse werden nach 30 Tagen entfernt;
- endgültig fehlgeschlagene Outbox-Ereignisse werden nach 90 Tagen entfernt.

Jeder Retention-Job besitzt eine nicht-destruktive `--dry-run`-Vorschau. Vor manuellen Läufen und im Incident Drill werden ausschließlich aggregierte Anzahlen geprüft; die produktiven Scheduler-Einträge bleiben durch `--limit`, `chunkById` und `withoutOverlapping` ressourcenschonend.

## 4. Betriebsindikatoren

Mindestens folgende Werte überwachen:

| Signal | Warnung | Kritisch |
|---|---:|---:|
| ältestes wartendes Outbox-Ereignis | > 2 Minuten | > 10 Minuten |
| wiederholbare Outbox-Ereignisse | > 20 | > 100 |
| endgültig fehlgeschlagene Events/24 h | > 0 | > 10 |
| API-Fehlerquote | > 1 % | > 3 % |
| p95 API-Latenz | > 500 ms | > 1.000 ms |
| DB-Abfragen pro Request | > 100 | Route untersuchen / Release bei Regression stoppen |
| kumulierte DB-Zeit pro Request | > 500 ms | Queryplan und Indexnutzung prüfen |
| Queue-Alter | > 60 s | > 300 s |
| SELECTs für 25 Chat-Previews | > 5 | Release stoppen |
| immutable Vite-Assets ohne korrekten Cache-Header | > 0 | Release stoppen |
| private API-Antworten mit öffentlicher Cachefreigabe | > 0 | sofortiger Incident |
| SELECTs pro Produktanalyse-Dashboard | > 10 | Release stoppen und Queryplan prüfen |
| SELECTs pro öffentlicher Sportstadt-Detailseite | > 12 | Release stoppen und Discovery-Queryplan prüfen |
| SELECTs pro Sportler-`daily-flow` mit sichtbarem Plan | > 12 | Release stoppen und Projection/Prefetch prüfen |
| SELECTs pro Team-`daily-life` mit sichtbarem Termin | > 14 | Release stoppen und Rollenprojektion/Queryplan prüfen |

Outbox-Nutzlasten dürfen keine Post-Inhalte, Lerninhalte, Zahlungsdaten oder Gesundheitsrohdaten enthalten. Neue Event-Typen benötigen einen Contract-Test auf Datenminimierung.

## 5. Rollback

1. vorherigen Anwendungscode erneut ausrollen;
2. Queue-Worker mit `php artisan queue:restart` neu starten;
3. additive Tabellen und Rollen zunächst bestehen lassen;
4. neue Scheduler-Einträge mit dem Code-Rollback entfernen;
5. wartende Outbox-Ereignisse nicht manuell löschen; nach Korrektur erneut zustellen;
6. Datenmigrationen erst in einem separaten, geprüften Release zurückbauen.

Ein Code-Rollback benötigt deshalb kein destruktives Datenbank-Rollback.

## 6. Abnahme-Checks

Stand dieses Releases:

| Check | Ergebnis |
|---|---|
| PHP-Gesamtsuite | 920 bestanden, 4 bewusst übersprungen, 24.050 Assertions; einschließlich vollständiger DE/EN/FR/AR-Trainingsoberfläche, tastaturbedienbarer Wochenplanung und verifiziertem Inertia-Teilreload, manipulationssicherer KI-Trainingsplan-Freigabe für Web und Mobile, modularer barrierearmer Trainingsdialoge, mehrsprachigem Gast-SEO/-Sitemap-Vertrag, `governance-assurance.v1`, `cross-device-experience.v1`, `observability-slo-readiness.v1`, `provider-smoke-readiness.v1`, `query-plan-readiness.v1`, begrenztem AJAX-E-Learning-Gastkatalog, `staging-http-delivery.v1`, `staged-rollout.v1`, `club-pilot.v1`, `security-privacy-acceptance.v1`, `localized-experience.v1`, automatisierter WCAG-2.2-AA-Baseline, `critical-journeys.v1`, vollständiger API-Zweckzuordnung, eigenständigem Sponsor-Persona, Sponsor-/Agency-Growth-Workspace, vereinheitlichtem Commerce-Katalog, Mobile-Navigation, gehärteter Release-Evidenz, Training-zu-Leveling, Vereins-Onboarding, gemeinsamem Mitglieder-Lifecycle, zentralem Admin-Operations-Falleingang, mandantenbezogenem AJAX-Supportcenter, globaler Command Palette, Privacy Center v2 und tabgenauen Lazy Settings |
| Kritische Journey-/Policy-Verträge | fünf priorisierte Reisen mit mindestens fünf realen Schritten und fünf beteiligten Personas einschließlich Sponsor, vollständiger Sechs-Persona-Rollenvertrag, 550 eindeutig verantwortete API-Routen, parametrisierte/überlappende Pfadauflösung, minimiertes Mobile-Meta und korrekte öffentliche sowie authentifizierte Governance-Header mit 4 neuen Tests und 1.293 Assertions plus Rollen-/Mobile-/Privacy-/Cache-/Gastregression verifiziert |
| Flutter Analyze | aktueller Wiederholungslauf über eine schreibbare, lokale SDK-Kopie ohne Befund |
| Flutter-Tests | 251 bestanden; vollständiger aktueller Lauf über die installierte SDK mit einem ressourcenschonenden Testprozess |
| Vite-Produktionsbuild | erfolgreich, 1.043 Module verarbeitet; 180 Manifest-Einträge, acht getrennte dynamische Kern-/Auto-Locale-Chunks plus seitenspezifische Sponsor- und Trainingskataloge |
| Globale Command Palette | gemeinsame Web-/Mobile-Suche für Inhalte und 27 berechtigungsabhängige Funktionsziele, vollständige Web-URLs und stabile native Modulschlüssel, E-Mail-Privacy, Sichtbarkeit, höchstens 16 SELECTs, Rate-Limit, No-Store, Request-Abbruch, 300/320-ms-Debouncing, Tastaturführung sowie DE/EN/FR/AR und RTL mit 12 Backendtests und 189 Assertions plus nativem Flutter-Navigationstest verifiziert |
| Privacy Center v2 | Sichtbarkeit, drei getrennte Einwilligungen, Export, Berichtigung, partielle Löschung und minimierte Login-/Sportanbieter in Web/Mobile; gastgeschützter `private/no-store`-Endpoint ohne Provider-Geheimnisse oder externe Kennungen und mit höchstens drei SELECTs durch 4 Backendtests und 34 Assertions plus native Widget-/DE-/EN-/FR-/AR-Verträge verifiziert |
| Lazy Settings | schlanker Erstaufruf, tabgenaue Partial-Props, sitzungsbezogene Wiederverwendung geteilter Daten, vollständige Direktlinks, Form-State-Erhalt sowie barrierearme Lade-/Fehler-/Retry-Zustände; Query-Isolation und DE/EN/FR/AR-Verträge automatisiert verifiziert |
| Sportler-Heute-Vertrag | personalisierte Kalorien-/Wasser-/Trainingsziele, Plan-vs.-Ist, priorisierte Aktion, private Feldisolation, DE/EN/FR/AR/RTL, sichtbarer Vue-Einbau und höchstens zwölf SELECTs mit 7 Tests und 294 Assertions verifiziert |
| Team-Home-Vertrag | Manager-/Mitglieder-/Vereinsgast-Sicht, Kontakt-/Guardian-/Gebühren-/Fahrtdaten-Isolation, reale API-Ziele, DE/EN/FR/AR/RTL, abbrechbares AJAX ohne Polling, sichtbarer Vue-Einbau und höchstens 14 SELECTs mit 4 Tests und 105 Assertions verifiziert |
| Training-End-to-End-Vertrag | Plan → Log → Feedback über gemeinsame Web-/Mobile-Services, servergeprüfter Plan-Deep-Link, empfängerbezogene AR-Benachrichtigung sowie `private`-/`trainer`-/`team`-Isolation mit 3 Workflowtests und 49 Assertions verifiziert |
| Training → Leveling | verifizierter Web-Plan- oder Mobile-GPS-Abschluss über transaktionale Outbox; idempotente 8 XP mit Tageslimit 1, erneute Eigentums-/Zeit-/Empfängerprüfung, keine Gesundheitsdaten im Event und deduplizierte DE/EN/FR/AR-Benachrichtigung mit 5 Tests und 55 Assertions verifiziert |
| Vereins-Onboarding und AJAX-Supportcenter | automatisch aus echten Daten berechnete Neun-Schritt-Führung in Web/Mobile mit festem Neun-SELECT-Budget; abbrechbare Ticket-Requests ohne Polling, berechtigungsgeprüfter Ticket-Mandant und Bearbeiter, getrennte Reaktions-/Lösungs-SLA, private interne Notizen, globale und vereinsbezogene Support-Sicht sowie DE/EN/FR/AR mit 19 Tests und 711 Assertions verifiziert |
| Route-Training-Vertrag | Route → Planposition → Log sowie optional eigener GPS-Track über einen gemeinsamen Service; explizite Planfreigabe, kein Sichtbarkeits-Leak über Logs, koordinatenfreie Trainingspayloads, begrenzte Options-API, Route/Track-Konsistenz und konstantes Route-Querybudget mit 4 Tests und 45 Assertions verifiziert |
| Route-Event-Vertrag | Route → Event → Trainingsdokumentation in Web und Mobile; zentraler Event-Sichtbarkeitsscope, koordinatenfreie Start-/Zielreferenzen, geschütztes Geometrie-Nachladen und Fremdrouten-Blockade mit 3 Tests und 56 Assertions verifiziert |
| Event-Kontext-Vertrag | Event-Dateien über bestehenden File Service; sechs sichere Detailreferenzen, paginierter Workspace, erneut autorisierte Vorschau, gemeinsamer Event-/Datei-/Ordnerscope, rollenrichtige Mobile-Aktionen sowie Chat- und vorausgefüllte Trainingsübergänge mit 3 Tests und 48 Assertions verifiziert |
| Recruiting-Opportunity-Vertrag | Stelle → zweckgebundene Sportprofilfreigabe → erklärbarer assistiver Abgleich → gültiger Status → Bewerbungs-Chat → Mitgliedschaftsübergabe; Gast-, Mandanten-, Datenminimierungs-, Widerrufs- und konstante Querygrenzen in Web/API/Mobile und DE/EN/FR/AR mit 6 Tests und 61 Assertions verifiziert |
| Mitglieder-Lifecycle | Antrag → Rücknahme/Pause → Freigabe/Ablehnung → terminierter Austritt über einen gemeinsamen Web-/API-Service; typspezifische Dokumente, SEPA-Parität, Schuldenblockade, Scheduler, laufender Profilstatus und minimierter Audit Trail automatisiert verifiziert |
| Refund-/Payout-Reconciliation | gemeinsames Ledger für direkte Erstattungen und Retouren, sichere Provider-Retries, Doppel-Submit- und Mehrfach-Restock-Schutz, Gutschrift, Learning-Entitlement-Entzug, Payout-Korrektur beziehungsweise Recovery-Hinweis sowie Privacy-Export mit 6 Tests und 58 Assertions verifiziert |
| Marketplace-Auszahlungs-Lifecycle | ein transaktionaler Service für Seller-Web/API und Admin-Web/API, echte ID-basierte Referenzen ohne Race-Lookup, exklusive Auftragszuordnung, Währungsgrenzen, Recovery-Sperre, Audit, empfängerbezogene DE/EN/FR/AR-Benachrichtigung, drei konstante Summary-Abfragen und speicherbegrenzte Kandidatenverarbeitung mit 4 Tests und 39 Assertions verifiziert |
| Vereinheitlichter Commerce-Katalog | `commerce-card.v1` für Produkt/Kurs/Outfit in Web und öffentlicher API; minimierte Felder, höchstens zwölf Karten und ein SELECT je Art, Moderationsschutz, responsiver Gastbereich mit Partial-Reload, echte Login-Rücksprungziele, DE/EN/FR/AR/RTL sowie locale-sensitive ETag-/304-Policy mit 4 Vertragstests und 73 Assertions plus Gast-/Checkout-Regression verifiziert |
| Sponsor-/Agency-Growth-Workspace | `growth-workspace.v1` verbindet Briefing → Deal → Asset → Kampagne → Ergebnis ohne zweite Datenhaltung; Owner-/Vereins-/Global-Isolation, minimierte Briefingfelder, Limits 50/12/24/8, höchstens 16 SELECTs, private No-Store-API, Partial-Refresh ohne Polling sowie DE/EN/FR/AR/RTL mit seitenspezifischem Lazy-Katalog, 3 Vertragstests und 236 Assertions plus Revenue-/Agency-/Rollenregression verifiziert |
| WCAG-2.2-AA-Baseline | zentrale native und programmatische Dialoge mit Namen, DE/EN/FR/AR, Fokusfalle/-rückgabe; reduzierte Bewegung, Hochkontrast, Fokus-/Ankerziele, mindestens 32-Pixel-Systembuttons, alle Vue-Bildalternativen, Icon-Aktionen, Formularfehler sowie 21 explizite Gastseiten-Buttons und 72 programmatisch benannte Gastformularfelder mit 8 neuen Tests und 63 Assertions verifiziert; menschliche Assistive-Technology-Abnahme bleibt eigener Release-Gate |
| DE/EN/FR/AR-/RTL-Vertrag | `localized-experience.v1` umfasst fünf Kernreisen, neun Gastflächen, 29 Systemmail-Vorlagen und 16 Kernbenachrichtigungen; 217 Mail-Schlüssel je Locale mit vollständiger Key-/Platzhalterparität, arabischer Schrift- und Kodierungsprüfung, regionalen Datums-/Währungsformaten sowie logischen Gast-Shell-Utilities mit 7 Tests und 208 Assertions verifiziert; Muttersprachler-/reale RTL-Abnahme bleibt eigener Release-Gate |
| Security-/Privacy-/Retention-Vertrag | `security-privacy-acceptance.v1` inventarisiert fünf Datenklassen, acht Zwecke, sechs technische Kontrollgruppen, fünf explizite Gastschutzgruppen, sechs sichere Retention-Jobs und sechs Incident-Rollen/-Phasen. Der repository-only Drill besteht mit 7 Checks, speichert weder Daten noch Secrets und setzt DPIA/Pentest nicht automatisch auf bestanden. Produktive CSP enthält kein `unsafe-eval`; Composer- und npm-Audit melden nach gezielten Paketupdates 0 bekannte Vulnerabilities |
| Legal-/DPIA-/Pentest-Governance | `governance-assurance.v1` koordiniert die drei bestehenden No-Go-Gates ohne Doppelstatus. 12 Legal-Bereiche, 10 Hochrisiko-Verarbeitungsfamilien, 9 Art.-35/36-Bausteine, 16 Pentest-Scope-Gruppen und 8 Abnahmeregeln sind mit Rollen-/Release-/No-Waiver- und Datenhygienevertrag umgesetzt; 7 Tests mit 134 Assertions verifizieren lokale Nicht-Autorität, getrennte Review-Rollen, Raw-Report-/Finding-Abwehr und gehärtete Legal-Version/-Datumsprüfung. Externe Freigaben bleiben offen |
| Vereins-Pilot-Vertrag | `club-pilot.v1` begrenzt den Pilot auf drei bis fünf Vereine und sechs bis acht Wochen; Gast-Discovery/-Einstieg, drei Kernreisen, sechs aggregierte KPI-Ziele, fünf Checkpoints, RACI, Support und Rollback sind verbunden. Der optionale Datencheck gibt keine Club-/User-IDs aus, und lokale Evidenz verwirft direkte Identifikatorfelder sowie unvollständige Scheinerfolge; 6 neue Tests plus Gast-/Onboarding-/Preflight-Regression sind grün. Der reale Pilot bleibt externer Gate |
| Stufenrollout-Vertrag | `staged-rollout.v1` steuert vier angemeldete Arbeitsräume über stabile 0/5/25/100-Zuordnung, Kill-Switches, autorisierten Pilot-Override und lokalisierte Fallbacks. 39 benannte Gast-/Public-Routen bleiben ohne Actor-Bucketing, Cookie oder Tracking und behalten ihre Cache-/SEO-/DSGVO-Verträge; 7 neue Tests mit 400 Assertions sowie Arbeitsraum-, Gast- und Preflight-Regression sind grün. Reale Stufenbeobachtung bleibt externer Gate |
| Gastseiten-UX/Delivery | 22 fokussierbare Hauptbereiche, Skip-Navigation und tastaturfeste Mobile-Navigation; Marketplace- und nun auch auf 24 Ergebnisse begrenzte E-Learning-Filter/Pagination per partiellem Inertia/AJAX-Reload, vier Katalogindizes, lazy Serverprops, native Bildpriorisierung, private Token-Seiten ohne Canonical/Cache/Referrer sowie ETag-/304-Verträge für robots/Sitemap/RSS mit 8 Tests und 230 Assertions verifiziert |
| Mobile-Modulnavigation | ein Zielvertrag für alle 34 sichtbaren Module, direkte Persona-Homes, rollenrichtiges Sponsor-Ziel, explizites Recruiting/Trainingsplanung und produktionsgesperrter Demo-Fallback mit 3 PHP-Vertragstests und 28 Assertions sowie einem nativen Flutter-Vertrag verifiziert |
| Mobile-Release-Evidenz | Version `1.0.33+77`, App-IDs und lokale Toolchain konsistent; signiertes AAB und APK mit ZIP-/Signatur-/SDK-24-Integritätsprüfung erzeugt; 3 von 22 Gates lokal nachgewiesen, autoritative Store-/Human-Gates bleiben offen |
| Evidence-Integrität | lokaler Sync schreibt nur ein ignoriertes, nicht autoritatives Manifest; explizite versionsgebundene Erfolgsmuster und nicht leere Build-/Bundle-Artefakte verhindern Scheinevidenz; 6 Vertragstests sichern Pipeline und Preflight-Einbindung |
| HTTP-Delivery-Vertrag | 7 lokale Tests für immutable Assets, Kompression, ETag/304, CORS sowie Cookie-, Authorization-, Auth- und Fehler-Isolation bestanden; zusätzlich 4 Tests mit 39 Assertions für den produktionsnahen `staging-http-delivery.v1`-Auditor einschließlich realer Gastflächen, Partial Reload, tokenisierter Gastbestellseite, Fail-Closed-Eingaben und geheimnisfreier Evidenz |
| Query-Plan-/Hot-Path-Vertrag | konstantes Chat-Budget, bestehende Hot-Path-Indizes sowie `query-plan-readiness.v1` mit 25 begrenzten, ausschließlich lesenden Queryfamilien, exakten Indexreihenfolgen, stagingbegrenztem MySQL-/MariaDB-Analysemodus und vollständig datenminimierter Evidenz; der neue Auditor besteht mit 4 Tests und 32 Assertions, die produktionsnahe DBA-Abnahme bleibt extern |
| Provider-Smoke-/Resilience-Vertrag | `provider-smoke-readiness.v1` prüft SMTP-/Firebase-Zustellung, Stripe-Testkonto und PayPal-Sandbox mit stagingbegrenzten, geheimnisfreien Eingaben und ohne Zahlungsanlage; Fallback, Retry, Signatur, Tokeninvalidierung, Refund-Idempotenz und lokale Zustandsisolation bleiben ausführbar. Die neue Orchestrierung besteht mit 6 Tests und 62 Assertions; reale Postfach-, Geräte- und Payment-Workflow-Evidenz bleibt extern |
| Observability-/SLO-Vertrag | `observability-slo-readiness.v1` versioniert neun Signale und Alert-Fristen für Verfügbarkeit, API-Fehler/Latenz, Gast-Core-Web-Vitals, Queue, Webhooks, Mail, Push und Offsite-Backup; Repository-/Runtime-Trennung, verbotene Rohdaten, synthetische Gastmessung sowie eingewilligtes Mindestgruppen-RUM sind mit 5 neuen Tests und 63 Assertions verifiziert. Echte Dashboards, Alarmproben und 24-Stunden-Evidenz bleiben extern |
| Cross-Device-Vertrag | `cross-device-experience.v1` koordiniert Web-Mobile/-Desktop, Android/iOS, DE/EN/FR/AR/RTL, acht Kernreisen und sieben Accessibility-Gruppen; ein versionsgebundener 19-Punkte-Validator lehnt die alte fünfteilige Realgeräte-Scheinevidenz ab. Manifestvorrang, Referenzhygiene und vollständige Achsenparität sind mit 5 neuen Tests und 77 Assertions sowie 33 bestehenden Mobile-/Gast-/Lokalisierungs-/WCAG-Tests verifiziert; physische Geräte und Human Review bleiben extern |
| Produktanalyse-Privacy-Vertrag | separate Einwilligung, Minor-Ausschluss, Mindestgruppen, Aggregat-Isolation, höchstens zehn SELECTs, Least-Privilege und DE/EN/FR/AR mit 6 Tests und 246 Assertions verifiziert |
| Public-Discovery-/SEO-Vertrag | Freigabe- und Feldminimierung, versteckte Teams, SSR/JSON-LD, Sitemap, DE/EN/FR/AR-Parität, vier Indizes und höchstens zwölf SELECTs mit 8 Tests und 457 Assertions verifiziert |
| Performance-Telemetrie | Runtime mit echten Query-Events sowie Sampling-, Schwellenwert- und Datenminimierungsvertrag verifiziert; drei Tests verhindern SQL-/Requestdaten-Leaks |
| Release-Preflight | 10 automatisierte Checks bestanden, 0 Fehler, 206 erforderliche Implementierungs-/Test-/CI-Artefakte vorhanden; 13 externe Nachweisgruppen einschließlich Mobile-Evidenz bleiben pending. Schneller Sammelcheck für Repository, Produktion, Security/Privacy/Governance, Club-Pilot, Stufenrollout, HTTP-/Query-Plan-/Provider-/Observability-/Cross-Device-Automation, Mobile-Manifest und zwölf ownergebundene Plattformgates einschließlich menschlicher WCAG-, Muttersprachler-/RTL-, Legal-, DPIA-, Pentest-, realer Pilot- und 5/25/100-Abnahme; Strict-, Rollentrennungs- und Geheimnisfreiheit-Vertrag bleibt verifiziert |
| Composer-Manifest | gültig |
| Scheduler-Auflösung | mit `CACHE_STORE=array` erfolgreich |
| Whitespace-/Patch-Prüfung | `git diff --check` erfolgreich |

Die native Suite wurde vollständig ausgeführt. Analyse und alle 251 Flutter-Tests sind grün; zusätzlich wurden ein signiertes AAB und APK gebaut und lokal geprüft. Echte Android-/iOS-Geräte, iOS-Signierung, Store-Uploads und Providerintegrationen bleiben separate externe Gates.

## 7. Mehrsprachigkeit

Aktiv unterstützt sind `de`, `en`, `fr` und `ar`; Arabisch wird RTL gerendert. Locale-Katalog-Parität, Platzhalter, Sprachpriorität, Fehlerantworten und Mobile-Kernübersetzungen sind automatisiert getestet.

Der serverseitige Vertrag `localized-experience.v1` ergänzt die Frontend-Kataloge: `User::preferredLocale()` bindet auch asynchron zugestellte Benachrichtigungen an die normalisierte Empfängersprache. Das zentrale Template-System deckt 29 Vorlagen ab; 16 direkt aufgebaute Kernmails verwenden dieselbe Text-, Datums- und Währungsformatierung. Der Audit prüft insgesamt 217 flache Mail-Schlüssel pro Locale, Key-/Platzhalterparität, arabische Schrift und Korruptionsmarker. Gastnavigation, Subnavigation und Footer verwenden ausschließlich logische Start-/End-Ausrichtung. Diese technischen Gates erlauben keine automatische menschliche Freigabe; `native_localization_qa` bleibt bis zu dokumentierter Rückübersetzung, Overflow- und realer RTL-Prüfung `pending`.

Die Sprachpakete besitzen zwei Stufen. Der semantische Kern wird vor dem Mount geladen; die automatische Legacy-Übersetzung ist Progressive Enhancement und folgt per `requestIdleCallback`. Gegenüber den vorherigen monolithischen Locale-Chunks sank die render-blockierende Gzip-Größe von 125,33 auf 75,21 KiB für Deutsch (−40 %) sowie von 150,98–172,92 auf 77,97–88,50 KiB für EN/FR/AR (rund −48 %). Deutsch lädt den redundanten Auto-Chunk nicht. Manifest-Tests begrenzen jeden Kern-Chunk auf unter 310 KB und sichern die dynamische Trennung ab.

Das CI-Gate `php artisan airmius:i18n-audit --fail-on-regression` unterscheidet zwischen Rohkandidaten, durch den geprüften Laufzeitkatalog abgedeckten Texten und tatsächlich offenen Stellen. Aktueller Stand:

- 2.864 Vue-Stellen und 10 PHP-Stellen sind durch den vier­sprachigen `auto`-Katalog abgedeckt;
- der Vue- und PHP-Audit ist mit jeweils 0 offenen Kandidaten sauber; 58 juristische Controller-Kandidaten werden durch den getrennten serverseitigen Legal-Katalog abgedeckt, ohne die renderkritischen Frontend-Pakete aufzublähen;
- `localized-legal-content.v1` liefert alle zehn öffentlichen Rechts-/Rechteseiten in DE/EN/FR/AR aus. 354 server-only Quellbausteine besitzen Key-/Platzhalterparität sowie automatische Zahlen-, Pfad-, Paragraphen-, Marken-, Langtext- und Kodierungsprüfung; konfigurierte Identitäts- und Kontaktdaten bleiben unverändert, Arabisch erhält RTL und fehlende Katalogeinträge fallen sicher auf Deutsch zurück;
- Freundschaften, Fahrgemeinschaften und Event-Wettkampfantworten sowie Gastnavigation, Marketplace-Checkout und Auszahlungsbereich besitzen jetzt semantische DE/EN/FR/AR-Verträge;
- Marketplace-Filter, Zahlungswege und Vertrauenshinweise, Team-Organizer/Teamgebühren, Sport-App-Integrationen und Reifegrad-Onboarding werden bereits serverseitig in der ausgehandelten Sprache erzeugt;
- die aktiven Admin-Commerce-, Outfit-Abo- und Konto-Abo-Oberflächen weisen keine offenen Audit-Kandidaten mehr auf;
- Medienrichtlinien, Mitglieder-Lebenszyklus, Learning-Studio, Guardian-Flows, Rechnungen, Trainings-Ernährung, Editorial, Rollen-Workspace, Support und Kontosicherheit besitzen gemeinsame DE/EN/FR/AR-Kataloge; Commerce-, Outfit-, Guardian-, Rechnungs-, Support-, Chat-, Vereins- und Profilbenachrichtigungen werden in der Empfängersprache gespeichert;
- Vue-Skripte werden getrennt vom gerenderten Template geprüft, damit Operatoren wie `>=` nicht fälschlich als sichtbarer Text zählen.

Der Audit `php artisan airmius:i18n-audit` meldet keine offene technisch messbare Lokalisierungsschuld mehr:

1. Verein, Team, Mitglieder, Dateien, Marketplace, Learning, Ernährung und die operativen Plattform-Workflows sind im semantischen Kern abgeschlossen; neue Regressionen sind vertraglich blockiert;
2. stabile Legacy-API-Werte bleiben als maschinenlesbares `message` erhalten und liefern zusätzlich ein lokalisiertes `message_text`;
3. rechtliche Langtexte sind technisch migriert, bleiben aber bis zur getrennten fachjuristischen und muttersprachlichen DE/EN/FR/AR-Abnahme ein externes No-Go-Gate.

Jede Migration ersetzt sichtbare Literale durch semantische Keys, ergänzt DE/EN/FR/AR gemeinsam und erhält einen RTL- oder Contract-Test. Der Audit-Wert darf pro Release nicht steigen.

## 8. Datenschutz- und Rollenfreigabe

- `super_admin`: Break-Glass und globale Governance, nicht für Tagesarbeit;
- `platform_engineer`: technische Plattformpflege ohne automatische Inhalts- oder Finanzrechte;
- `security_admin`: Sicherheits- und Berechtigungsverwaltung ohne globale Geschäftsrechte;
- Fachrollen bleiben an Organisation und Ressource gebunden;
- sensible Verarbeitung benötigt einen dokumentierten Purpose und deny-by-default bei Zwecküberschreitung.

Die Produktanalyse bleibt standardmäßig deaktiviert. Freigabeparameter:

```dotenv
PRODUCT_ANALYTICS_ENABLED=false
PRODUCT_ANALYTICS_CONSENT_VERSION=product-analytics-v1
PRODUCT_ANALYTICS_MIN_GROUP_SIZE=5
```

`PRODUCT_ANALYTICS_ENABLED=true` ist nur zulässig, wenn Product und DSB den konkreten Zweck, die Kennzahldefinitionen, den versionierten Einwilligungstext und eine gegebenenfalls erforderliche DPIA dokumentiert freigegeben haben. Der Mindestwert darf nie unter fünf liegen. Die Werbeeinwilligungen dürfen nicht als Produktanalyse-Einwilligung interpretiert werden. Änderungen der Einwilligung sind im Aktivitätsaudit nachvollziehbar; Export und partielle Datenlöschung schließen die neuen Felder ein.

Vor Pilotbetrieb bleiben eine organisatorische DPIA-Freigabe, ein externer Penetrationstest und eine native sprachliche Abnahme erforderlich. Diese Punkte können nicht durch automatisierte Repository-Tests ersetzt werden.

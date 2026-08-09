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
- datensparsame Performance-Telemetrie: eine produktionsaktivierte, request-gebundene Messung erfasst nur Dauer, DB-Anzahl/-Zeit, Speicherdelta, Status, benannte Route und Request-ID; SQL, Bindings, URLs, Parameter, IPs und Nutzerkennungen werden weder gesammelt noch geloggt;
- datenschutzorientierte Produktanalyse: ein eigener `analytics.view`-Workspace verdichtet bestehende Fachdaten für Aktivierung, aktive Nutzung, 7-Tage-Bindung, Training, Teams, Käufe und Kündigungen; separate Einwilligung, Volljährigkeit, Mindestkohorten, Feldunterdrückung und ein festes Abfragebudget werden serverseitig erzwungen, ohne Rohereignistabelle, Tracking-SDK oder zusätzliche Cookies;
- öffentlicher Discovery-Layer für Vereine, Events, Sportarten und Sportstädte: SSR-Metadaten, kanonische URLs, JSON-LD und Sitemap-Einträge verwenden ausschließlich verifizierte, freigegebene Organisationsdaten und minimierte Eventfelder; verborgene Teams werden weder benannt noch gezählt, Listen sind hart begrenzt und DE/EN/FR/AR einschließlich RTL teilen denselben Vertrag;
- gehärtete Gastoberfläche: alle 22 öffentlichen Vue-Seiten besitzen einen fokussierbaren Hauptbereich und eine Skip-Navigation; das mobile Menü hält den Tastaturfokus, schließt mit Escape und stellt die Scrollposition wieder her. Filter und Pagination laden per Inertia nur veränderte Props, teure Marketplace-, Blog-, Job-, Vereins- und Discovery-Abfragen bleiben lazy, Kartenbilder dekodieren asynchron und werden außerhalb des sichtbaren Bereichs verzögert geladen;
- private Gastbestellseiten: Token-URLs werden weder als Canonical noch als OpenGraph-URL veröffentlicht, senden server- und clientseitig `noindex`, `no-store` und `no-referrer` und sind gedrosselt. Sitemap, RSS und robots.txt verwenden öffentliche Cachegrenzen, ETags und bedingte 304-Antworten; Sitemap und RSS werden zusätzlich zehn Minuten serverseitig wiederverwendet;
- automatische Aufbewahrungsbereinigung für Delivery-Daten;
- sichere Organisationskontexte für direkte und teamvermittelte Vereinszugriffe;
- durchgängig lokalisierte Trainingsplanung: Zielgruppenwahl im Web sowie Log-, Plan-, Ausfall- und Feedback-Benachrichtigungen verwenden semantische DE/EN/FR/AR-Schlüssel; Empfänger werden pro Vorgang gebündelt statt einzeln nachgeladen;
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

### 2.1 Einheitlicher Release-Preflight

Der zentrale, nur lesende Einstieg bündelt Repository-, Produktionskonfigurations- und externe Evidence-Gates, ohne Tests oder Provideraufrufe unnötig zu wiederholen:

```bash
composer preflight
php artisan airmius:release-preflight --json
php artisan airmius:release-preflight --with-operations --json
php artisan airmius:release-preflight --strict
```

Der normale Lauf gibt `1` nur bei einem tatsächlich fehlgeschlagenen automatischen oder bereits negativ bewerteten externen Gate zurück. Offene Nachweise bleiben als `PENDING` sichtbar, damit lokale Entwicklungs- und CI-Prüfungen nicht durch organisatorisch noch nicht mögliche Arbeiten blockiert werden. `--strict` ist das verbindliche finale Go/No-Go: Auch jeder offene oder in dieser Umgebung übersprungene Nachweis führt zu Exit-Code `1`. `--with-operations` ergänzt die bestehenden Liveprüfungen für Datenbank, Queue, Scheduler, Webhooks, Mail, Push und Backup; dieser Modus gehört in Staging beziehungsweise Produktion, nicht in jeden Commit-Lauf.

Die zehn nicht automatisierbaren Plattformgates mit Verantwortlichkeiten stehen in `resources/release/platform_release_gates.json`. Ein Gate darf nur mit nicht-sensitiven Ticket-, Artefakt- oder Freigabereferenzen auf `passed` beziehungsweise begründet auf `waived` gesetzt werden; dafür sind `reviewed_by` und `reviewed_at` verpflichtend. Zugangsdaten, Tokens, personenbezogene Testdaten und vollständige Headerausgaben gehören weder in dieses Manifest noch in Release-Tickets. Die detaillierten Mobile-/Store-Nachweise bleiben im bestehenden Mobile-Manifest; der zentrale Preflight liest dessen Status lediglich zusammengefasst.

Mobile-Evidenz wird zweistufig geführt. `store_listing/release/release_evidence_manifest.json` bleibt die versionierte, autoritative Freigabebasis und darf nur nach Review geändert werden. Linux-Läufe schreiben mit `scripts/sync_linux_release_evidence_manifest.sh` ausschließlich die ignorierte Datei `release_evidence/release_evidence_manifest.local.json`; sie kann den Preflight informieren, aber niemals ein Store- oder Human-Gate freigeben. Der Sync akzeptiert keine bloß vorhandenen Logs: Analyse, Version, Konfiguration, Builds, Signatur/Integrität und Bundle-Prüfung benötigen jeweils explizite Erfolgsmuster und nicht leere Artefakte. Vor jedem lokalen Android-Lauf sind auszuführen:

```bash
scripts/assert_linux_android_release_prerequisites.sh
scripts/assert_release_version_consistency.sh
scripts/sync_linux_release_evidence_manifest.sh
```

Für Version `1.0.33+77` sind die lokalen Voraussetzungen, `flutter analyze` und der signierte Android-Release-Build nachgewiesen, also 3 von 22 Mobile-Gates. Das AAB und das APK wurden zusätzlich als ZIP, mit Android-Signatur und Mindest-SDK 24 geprüft. Die übrigen 19 Gates bleiben im autoritativen Manifest bewusst offen; insbesondere ersetzen lokale Artefakte weder Store-Paketierung noch Review, Realgerät, iOS-Signierung oder Datenschutzfreigabe.

Flutter 3.44 unterstützt bei AGP 9 ältere Kotlin-Plugins nur über den dokumentierten Übergangsmodus. Deshalb bleiben `android.builtInKotlin=false` und `android.newDsl=false` gesetzt. Die App wird erst zusammen mit Flutter 3.47 oder neuer und nach nachgewiesener Kompatibilität aller Plugins auf Built-in Kotlin migriert; eine Warnung allein rechtfertigt weder ein Dependency-Override noch eine ungeprüfte Plugin-Fork.

Die Produktionsgrenzen dieses Hosts sind nun auf den Produktnamen, die vollständige HTTPS-Domain und sichere Session-Cookies korrigiert; der automatisierte Konfigurationscheck ist grün. Die Ausgabe nennt bei Abweichungen weiterhin ausschließlich betroffene Variablennamen und zeigt keine Konfigurationswerte oder Geheimnisse. Der Gesamtentscheid bleibt bis zu den elf externen Plattform-Evidence-Gates bewusst `no_go`.

### 2.2 HTTP-Auslieferung

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
curl -I https://staging.example/build/assets/DATEINAME-AUS-MANIFEST.js
curl -I https://staging.example/build/manifest.json
curl -I https://staging.example/api/v1/meta
curl -I -H 'If-None-Match: W/"<etag>"' https://staging.example/api/v1/meta
curl -I -H 'Authorization: Bearer <test-token>' https://staging.example/api/v1/meta
```

Erwartet werden beim vierten Aufruf `304` und beim fünften Aufruf `private, no-store`. Test-Token und Headerausgaben dürfen nicht in Tickets oder dauerhafte Logs kopiert werden.

## 3. Scheduler und Queue

Neue geplante Aufgaben:

| Aufgabe | Takt | Zweck |
|---|---:|---|
| `airmius:dispatch-domain-outbox` | jede Minute | wartende oder wiederholbare Outbox-Ereignisse einreihen |
| `airmius:mobile-push-dispatch --limit=500` | jede Minute | fällige, präferenz- und ruhezeitgeprüfte Push-Zustellungen ausliefern |
| `airmius:send-notification-digests` | täglich 18:00 | lokalisierte E-Mail-Zusammenfassung für freigegebene Empfänger senden |
| `airmius:send-subscription-invoice-emails` | täglich 08:15 | fällige Abo-Rechnungen einmalig und in der Sprache des Empfängers versenden |
| `airmius:process-subscription-lifecycle` | täglich 08:45 | fällige Konto- und Vereins-Abos idempotent beenden und Entitlements entziehen |
| `airmius:prune-website-requests --limit=1000` | täglich 03:48 | abgelaufene, nicht aktiv bearbeitete Werbeagentur-Anfragen begrenzt löschen |
| `airmius:prune-recruiting-interests --limit=1000` | täglich 03:50 | abgelaufene Bewerbungsinteressen datensparsam und begrenzt löschen |
| `airmius:prune-platform-delivery` | täglich 03:55 | abgelaufene Idempotenzdaten sowie alte ausgelieferte/fehlgeschlagene Events löschen |

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
| PHP-Gesamtsuite | 840 bestanden, 4 bewusst übersprungen, 17.304 Assertions; einschließlich automatisierter WCAG-2.2-AA-Baseline, `critical-journeys.v1`, vollständiger API-Zweckzuordnung, eigenständiger Sponsor-Persona, Sponsor-/Agency-Growth-Workspace, vereinheitlichtem Commerce-Katalog, Mobile-Navigation, gehärteter Release-Evidenz, Training-zu-Leveling, Vereins-Onboarding, gemeinsamem Mitglieder-Lifecycle, zentralem Admin-Operations-Falleingang, mandantenbezogenem AJAX-Supportcenter, globaler Command Palette, Privacy Center v2 und tabgenauen Lazy Settings |
| Kritische Journey-/Policy-Verträge | fünf priorisierte Reisen mit mindestens fünf realen Schritten und fünf beteiligten Personas einschließlich Sponsor, vollständiger Sechs-Persona-Rollenvertrag, 550 eindeutig verantwortete API-Routen, parametrisierte/überlappende Pfadauflösung, minimiertes Mobile-Meta und korrekte öffentliche sowie authentifizierte Governance-Header mit 4 neuen Tests und 1.293 Assertions plus Rollen-/Mobile-/Privacy-/Cache-/Gastregression verifiziert |
| Flutter Analyze | aktueller Wiederholungslauf über eine schreibbare, lokale SDK-Kopie ohne Befund |
| Flutter-Tests | 251 bestanden; vollständiger aktueller Lauf über die installierte SDK mit einem ressourcenschonenden Testprozess |
| Vite-Produktionsbuild | erfolgreich, 1.034 Module verarbeitet; acht getrennte dynamische Kern-/Auto-Locale-Chunks plus seitenspezifischer Sponsor-Katalog |
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
| Gastseiten-UX/Delivery | 22 fokussierbare Hauptbereiche, Skip-Navigation und tastaturfeste Mobile-Navigation; partielle Filter-/Pagination-Reloads, lazy Serverprops, native Bildpriorisierung, private Token-Seiten ohne Canonical/Cache/Referrer sowie ETag-/304-Verträge für robots/Sitemap/RSS mit 6 Tests und 197 Assertions verifiziert |
| Mobile-Modulnavigation | ein Zielvertrag für alle 34 sichtbaren Module, direkte Persona-Homes, rollenrichtiges Sponsor-Ziel, explizites Recruiting/Trainingsplanung und produktionsgesperrter Demo-Fallback mit 3 PHP-Vertragstests und 28 Assertions sowie einem nativen Flutter-Vertrag verifiziert |
| Mobile-Release-Evidenz | Version `1.0.33+77`, App-IDs und lokale Toolchain konsistent; signiertes AAB und APK mit ZIP-/Signatur-/SDK-24-Integritätsprüfung erzeugt; 3 von 22 Gates lokal nachgewiesen, autoritative Store-/Human-Gates bleiben offen |
| Evidence-Integrität | lokaler Sync schreibt nur ein ignoriertes, nicht autoritatives Manifest; explizite versionsgebundene Erfolgsmuster und nicht leere Build-/Bundle-Artefakte verhindern Scheinevidenz; 6 Vertragstests sichern Pipeline und Preflight-Einbindung |
| HTTP-Delivery-Vertrag | 7 Tests für immutable Assets, Kompression, ETag/304, CORS sowie Cookie-, Authorization-, Auth- und Fehler-Isolation bestanden |
| Hot-Path-Query-Vertrag | konstantes Chat-Budget und neun zusammengesetzte Indexverträge mit 71 Assertions; Migration und Einzel-Rollback auf separater SQLite-Datenbank erfolgreich |
| Produktanalyse-Privacy-Vertrag | separate Einwilligung, Minor-Ausschluss, Mindestgruppen, Aggregat-Isolation, höchstens zehn SELECTs, Least-Privilege und DE/EN/FR/AR mit 6 Tests und 246 Assertions verifiziert |
| Public-Discovery-/SEO-Vertrag | Freigabe- und Feldminimierung, versteckte Teams, SSR/JSON-LD, Sitemap, DE/EN/FR/AR-Parität, vier Indizes und höchstens zwölf SELECTs mit 8 Tests und 457 Assertions verifiziert |
| Performance-Telemetrie | Runtime mit echten Query-Events sowie Sampling-, Schwellenwert- und Datenminimierungsvertrag verifiziert; drei Tests verhindern SQL-/Requestdaten-Leaks |
| Release-Preflight | 7 automatisierte Checks bestanden, 0 Fehler, 122 erforderliche Implementierungs-/Test-/CI-Artefakte vorhanden; 12 externe Nachweisgruppen einschließlich Mobile-Evidenz bleiben pending. Schneller Sammelcheck für Repository, Produktion, Mobile-Manifest und elf ownergebundene Plattformgates einschließlich menschlicher WCAG-Abnahme; Strict- und Geheimnisfreiheit-Vertrag mit fünf Tests und 64 Assertions verifiziert |
| Composer-Manifest | gültig |
| Scheduler-Auflösung | mit `CACHE_STORE=array` erfolgreich |
| Whitespace-/Patch-Prüfung | `git diff --check` erfolgreich |

Die native Suite wurde vollständig ausgeführt. Analyse und alle 250 Flutter-Tests sind grün; zusätzlich wurden ein signiertes AAB und APK gebaut und lokal geprüft. Echte Android-/iOS-Geräte, iOS-Signierung, Store-Uploads und Providerintegrationen bleiben separate externe Gates.

## 7. Mehrsprachigkeit

Aktiv unterstützt sind `de`, `en`, `fr` und `ar`; Arabisch wird RTL gerendert. Locale-Katalog-Parität, Platzhalter, Sprachpriorität, Fehlerantworten und Mobile-Kernübersetzungen sind automatisiert getestet.

Die Sprachpakete besitzen zwei Stufen. Der semantische Kern wird vor dem Mount geladen; die automatische Legacy-Übersetzung ist Progressive Enhancement und folgt per `requestIdleCallback`. Gegenüber den vorherigen monolithischen Locale-Chunks sank die render-blockierende Gzip-Größe von 125,33 auf 75,21 KiB für Deutsch (−40 %) sowie von 150,98–172,92 auf 77,97–88,50 KiB für EN/FR/AR (rund −48 %). Deutsch lädt den redundanten Auto-Chunk nicht. Manifest-Tests begrenzen jeden Kern-Chunk auf unter 310 KB und sichern die dynamische Trennung ab.

Das CI-Gate `php artisan airmius:i18n-audit --fail-on-regression` unterscheidet zwischen Rohkandidaten, durch den geprüften Laufzeitkatalog abgedeckten Texten und tatsächlich offenen Stellen. Aktueller Stand:

- 2.874 Vue-Stellen und 12 PHP-Stellen sind durch den vier­sprachigen `auto`-Katalog abgedeckt;
- der Vue-Audit ist mit 0 offenen Kandidaten sauber; außerhalb des separat geprüften `LegalPageController` bestehen auch im PHP-Code 0 offene Kandidaten;
- die verbleibenden 58 PHP-Kandidaten sind ausschließlich juristische Überschriften im `LegalPageController`; die Baseline blockiert jede Erhöhung dieses isolierten Bestands;
- Freundschaften, Fahrgemeinschaften und Event-Wettkampfantworten sowie Gastnavigation, Marketplace-Checkout und Auszahlungsbereich besitzen jetzt semantische DE/EN/FR/AR-Verträge;
- Marketplace-Filter, Zahlungswege und Vertrauenshinweise, Team-Organizer/Teamgebühren, Sport-App-Integrationen und Reifegrad-Onboarding werden bereits serverseitig in der ausgehandelten Sprache erzeugt;
- die aktiven Admin-Commerce-, Outfit-Abo- und Konto-Abo-Oberflächen weisen keine offenen Audit-Kandidaten mehr auf;
- Medienrichtlinien, Mitglieder-Lebenszyklus, Learning-Studio, Guardian-Flows, Rechnungen, Trainings-Ernährung, Editorial, Rollen-Workspace, Support und Kontosicherheit besitzen gemeinsame DE/EN/FR/AR-Kataloge; Commerce-, Outfit-, Guardian-, Rechnungs-, Support-, Chat-, Vereins- und Profilbenachrichtigungen werden in der Empfängersprache gespeichert;
- Vue-Skripte werden getrennt vom gerenderten Template geprüft, damit Operatoren wie `>=` nicht fälschlich als sichtbarer Text zählen.

Der Audit `php artisan airmius:i18n-audit` meldet außerhalb der juristischen Inhalte keine offene Lokalisierungsschuld mehr:

1. Verein, Team, Mitglieder, Dateien, Marketplace, Learning, Ernährung und die operativen Plattform-Workflows sind im semantischen Kern abgeschlossen; neue Regressionen sind vertraglich blockiert;
2. stabile Legacy-API-Werte bleiben als maschinenlesbares `message` erhalten und liefern zusätzlich ein lokalisiertes `message_text`;
3. rechtliche Langtexte werden nur zusammen mit fachjuristischer und muttersprachlicher Abnahme migriert.

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

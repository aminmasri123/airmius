# Airmius Platform Delivery Runbook

Stand: 8. August 2026

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
- zusammengesetzte Hot-Path-Indizes für Notification Center, Chat-Mitgliedschaften, Eventlisten/-Erinnerungen, minütliche Push-Zustellung und Domain-Outbox; der alte Outbox-Index wird beim Upgrade erst nach erfolgreichem Aufbau des neuen Dispatch-Index entfernt;
- datensparsame Performance-Telemetrie: eine produktionsaktivierte, request-gebundene Messung erfasst nur Dauer, DB-Anzahl/-Zeit, Speicherdelta, Status, benannte Route und Request-ID; SQL, Bindings, URLs, Parameter, IPs und Nutzerkennungen werden weder gesammelt noch geloggt;
- datenschutzorientierte Produktanalyse: ein eigener `analytics.view`-Workspace verdichtet bestehende Fachdaten für Aktivierung, aktive Nutzung, 7-Tage-Bindung, Training, Teams, Käufe und Kündigungen; separate Einwilligung, Volljährigkeit, Mindestkohorten, Feldunterdrückung und ein festes Abfragebudget werden serverseitig erzwungen, ohne Rohereignistabelle, Tracking-SDK oder zusätzliche Cookies;
- automatische Aufbewahrungsbereinigung für Delivery-Daten;
- sichere Organisationskontexte für direkte und teamvermittelte Vereinszugriffe;
- durchgängig lokalisierte Trainingsplanung: Zielgruppenwahl im Web sowie Log-, Plan-, Ausfall- und Feedback-Benachrichtigungen verwenden semantische DE/EN/FR/AR-Schlüssel; Empfänger werden pro Vorgang gebündelt statt einzeln nachgeladen;
- vollständig lokalisierte operative Workflows für Editorial, Rollen-Workspace, Guardian, Vereinsprüfung, Vereinsumfragen, Ankündigungen, Support, Kontosicherheit, Feed, Chat, Gamification und Benachrichtigungs-Digests; nutzerbezogene Meldungen und Vereinsprüfungs-E-Mails verwenden die gespeicherte Empfängersprache;
- gemeinsamer DSGVO-Datenlöschungsvertrag für Web, API und Mobile: serverdefinierte Kategorien, automatisch erkannte Passwort-/Social-Login-Bestätigung, an die Auswahl gebundene Einmalcodes, gedrosselte Endpunkte und empfängerbezogene DE/EN/FR/AR-E-Mails;
- konsolidierter Commerce-Käufervertrag für Web und Mobile: Warenkorb, Checkout, Bestellaktionen, Verkäuferantworten und Bestellbenachrichtigungen verwenden semantische DE/EN/FR/AR-Schlüssel; eine ungenutzte zweite Warenkorb-Implementierung wurde entfernt;
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
- ressourcenschonender Sponsor-Workspace: Kennzahlen werden in SQL aggregiert, Listen und Suchergebnisse sind hart begrenzt, Vereins- und Sponsorensuche laufen serverseitig und alle neuen Antworten/Oberflächen besitzen DE/EN/FR/AR-Verträge;
- integrierter Werbeagentur-Vertrag: Web und Mobile senden an denselben gedrosselten und idempotenten API-Workflow; eine explizite Einwilligung, dokumentierte Statuswechsel, Selbstauskunft, Selbstlöschung und automatische Aufbewahrungsbereinigung schützen Anfrage- und Kontaktdaten;
- ressourcenschonende Ads-Auslieferung: Frequenz- und Conversion-Signale werden unabhängig von der Kandidatenzahl gebündelt geladen, wiederholte Einstellungen pro Request zwischengespeichert, Creatives gemeinsam ausgewertet, Standard-CTAs lokalisiert und externe Ziele auf sichere `http`-/`https`-URLs begrenzt;
- Mobile-Härtung für rollenbasierte Adminbereiche, eingebettete Zugriffssperren und Marketplace-Direktzugriff auf den Warenkorb.

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

Der aktuelle Hostlauf erkennt korrekt ein No-Go, weil die Umgebung als `production` läuft, aber noch `APP_NAME`, eine vollständige HTTPS-`APP_URL` und sichere Session-Cookies korrigiert werden müssen. Die Ausgabe nennt ausschließlich die betroffenen Variablennamen und zeigt keine Konfigurationswerte oder Geheimnisse. Diese Einstellungen dürfen erst zusammen mit der echten TLS-Domain angepasst werden; ein voreilig aktiviertes Secure-Cookie auf der aktuellen HTTP-Adresse würde Sitzungen unbrauchbar machen.

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
| PHP-Gesamtsuite | 712 bestanden, 4 bewusst übersprungen, 12.116 Assertions |
| Flutter Analyze | letzter verifizierter Baseline-Stand ohne Befund; aktueller Wiederholungslauf auf diesem Host durch nicht ausführbare Snap-Runtime blockiert |
| Flutter-Tests | letzter verifizierter Baseline-Stand: 248 bestanden; aktueller Wiederholungslauf auf diesem Host durch nicht ausführbare Snap-Runtime blockiert |
| Vite-Produktionsbuild | erfolgreich, 1.024 Module verarbeitet; acht getrennte dynamische Locale-Chunks |
| HTTP-Delivery-Vertrag | 7 Tests für immutable Assets, Kompression, ETag/304, CORS sowie Cookie-, Authorization-, Auth- und Fehler-Isolation bestanden |
| Hot-Path-Query-Vertrag | konstantes Chat-Budget und neun zusammengesetzte Indexverträge mit 71 Assertions; Migration und Einzel-Rollback auf separater SQLite-Datenbank erfolgreich |
| Produktanalyse-Privacy-Vertrag | separate Einwilligung, Minor-Ausschluss, Mindestgruppen, Aggregat-Isolation, höchstens zehn SELECTs, Least-Privilege und DE/EN/FR/AR mit 6 Tests und 246 Assertions verifiziert |
| Performance-Telemetrie | Runtime mit echten Query-Events sowie Sampling-, Schwellenwert- und Datenminimierungsvertrag verifiziert; drei Tests verhindern SQL-/Requestdaten-Leaks |
| Release-Preflight | schneller Sammelcheck für Repository, Produktion, Mobile-Manifest und zehn ownergebundene externe Gates; Strict- und Geheimnisfreiheit-Vertrag mit fünf Tests und 64 Assertions verifiziert |
| Composer-Manifest | gültig |
| Scheduler-Auflösung | mit `CACHE_STORE=array` erfolgreich |
| Whitespace-/Patch-Prüfung | `git diff --check` erfolgreich |

Der aktuelle Host stellt Flutter und Dart ausschließlich als Snap bereit, darf Snap-Prozesse in dieser Ausführungsumgebung jedoch nicht starten. Deshalb müssen Analyse und Widget-Tests des neuen Recruiting-Screens im Flutter-CI-Job wiederholt werden; PHP-Vertragstests und der Web-Produktionsbuild sind grün.

## 7. Mehrsprachigkeit

Aktiv unterstützt sind `de`, `en`, `fr` und `ar`; Arabisch wird RTL gerendert. Locale-Katalog-Parität, Platzhalter, Sprachpriorität, Fehlerantworten und Mobile-Kernübersetzungen sind automatisiert getestet.

Die Sprachpakete besitzen zwei Stufen. Der semantische Kern wird vor dem Mount geladen; die automatische Legacy-Übersetzung ist Progressive Enhancement und folgt per `requestIdleCallback`. Gegenüber den vorherigen monolithischen Locale-Chunks sank die render-blockierende Gzip-Größe von 125,33 auf 75,21 KiB für Deutsch (−40 %) sowie von 150,98–172,92 auf 77,97–88,50 KiB für EN/FR/AR (rund −48 %). Deutsch lädt den redundanten Auto-Chunk nicht. Manifest-Tests begrenzen jeden Kern-Chunk auf unter 310 KB und sichern die dynamische Trennung ab.

Das CI-Gate `php artisan airmius:i18n-audit --fail-on-regression` unterscheidet zwischen Rohkandidaten, durch den geprüften Laufzeitkatalog abgedeckten Texten und tatsächlich offenen Stellen. Aktueller Stand:

- 2.873 Vue-Stellen und 12 PHP-Stellen sind durch den vier­sprachigen `auto`-Katalog abgedeckt;
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

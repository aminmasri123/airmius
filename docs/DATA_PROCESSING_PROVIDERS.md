# Airmius AVV, DPA und Subprozessorenliste

Stand: 2026-07-17

Diese Datei ist die interne Nachweis- und VVT-Hilfe für Auftragsverarbeiter und wichtige technische Dienstleister. Sie ersetzt keine anwaltliche Prüfung, hilft aber dabei, Nachweise, Subprocessor-Prüfungen und Datenschutzhinweise sauber zu dokumentieren.

## MVP-Status nach Kategorie

| Kategorie | Aktueller Dienst/Status | Konfiguration im Projekt | Status fuer MVP |
| --- | --- | --- | --- |
| Hosting | Hostinger | Server-/Datenbankbetrieb ausserhalb des Codes, Rechtstexte nennen Hostinger | Aktiv vorgesehen, DPA-Nachweis liegt als PDF vor |
| Storage/CDN/Backups | Cloudflare R2/CDN | `FILESYSTEM_DISK=r2`, `UPLOAD_DISK=r2`, `BACKUP_DISK=r2` | Aktiv vorgesehen, Customer DPA liegt als PDF vor |
| Mail | SMTP/Log, konkrete Produktiv-SMTP-Anbieter noch einzutragen | `MAIL_MAILER`, `MAIL_*_MAILER`, optionale Laravel-Mailer `ses`, `postmark`, `resend` | Vor Livegang konkreten Anbieter, DPA und Region eintragen |
| Payments | Stripe, PayPal, Bankueberweisung ohne externen Payment-Provider | `STRIPE_*`, `PAYPAL_*`, Checkout/Webhook-Code | Stripe/PayPal nur produktiv aktivieren, wenn DPA/Datenschutznotiz geprueft ist |
| Analytics/Tracking | Keine externen Analytics-, Marketing-, Retargeting- oder Pixel-Skripte aktiv eingebunden | `docs/COOKIE_TRACKING_CONSENT.md`, `resources/js/services/privacyConsent.js` | Keine Analytics-Subprozessoren im MVP; neue Tags erst nach Opt-in und Dokumentation |
| Push | FCM und APNS vorbereitet, Dispatch aktuell projektintern simuliert | `MOBILE_PUSH_PROVIDER=fcm`, `FCM_*`, `APNS_*`, `MobilePushDeliveryService` | Vor echter Auslieferung Google Firebase/Apple APNS als Empfaenger dokumentieren |
| Realtime | Laravel Reverb self-hosted, Pusher optional vorbereitet | `BROADCAST_CONNECTION=reverb`, `PUSHER_*` | Reverb kein externer Subprozessor; Pusher nur nach Dokumentation aktivieren |
| Karten/Routing | OpenStreetMap Tiles, Esri Satellite Tiles, GraphHopper, OSRM, optional Mapbox | `SPORT_MAP_*`, `GRAPHHOPPER_API_KEY`, `MAPBOX_ACCESS_TOKEN` | Externe Kartendienste in Datenschutz genannt; Produktiv-Auswahl pruefen |
| KI | IONOS AI Model Hub primaer, OpenAI fallback, Google Gemini optional | `AIRMIUS_AI_*`, `IONOS_AI_*`, `OPENAI_*`, `GOOGLE_GEMINI_*` | Nur mit passendem Vertrag/DPA und Feature-Consent produktiv aktivieren |
| Social/OAuth/Fitness | Google Login/Fit, Microsoft Login, Strava vorbereitet | `GOOGLE_*`, `MICROSOFT_*`, `STRAVA_*` | Nur aktivieren, wenn App-Konfiguration und Datenschutztexte final sind |
| Monitoring | Provider noch nicht festgelegt | `ERROR_MONITORING_DSN` | Vor Aktivierung Anbieter und DPA eintragen |

## Nachweisablage

Lege aktuelle Vertrags- und Compliance-Dokumente intern ab, zum Beispiel:

- `docs/legal/provider/hostinger-avv-dpa.pdf`
- `docs/legal/provider/cloudflare-customer-dpa.pdf`
- `docs/legal/provider/cloudflare-compliance-certificates.pdf`
- `docs/legal/provider/stripe-dpa.pdf`
- `docs/legal/provider/paypal-privacy-or-dpa.pdf`
- `docs/legal/provider/mail-provider-dpa.pdf`
- `docs/legal/provider/firebase-dpa.pdf`
- `docs/legal/provider/apple-developer-terms.pdf`
- `docs/legal/provider/ionos-ai-dpa.pdf`
- `docs/legal/provider/openai-dpa.pdf`

Wichtig: PDFs hier nicht blind versionieren, wenn sie sensible Vertragsdaten enthalten. Alternativ intern außerhalb des Git-Repos speichern und hier nur Fundort/Datum dokumentieren.

## Hostinger

- Anbieter: Hostinger
- Rolle: Auftragsverarbeiter
- Zweck: Hosting der Airmius-Plattform, Serverbetrieb, Datenbank/Applikation, technische Logs
- Betroffene: Nutzer, Vereine, Trainer, Eltern, Käufer, Anbieter, Admins
- Datenarten: Konto-, Profil-, Kommunikations-, Vereins-, Rechnungs-, Bestell-, Sicherheits- und Logdaten, soweit in der Applikation verarbeitet
- AVV/DPA: Nach Anbieterantwort gilt der AVV bereits durch Konto- bzw. Vertragsannahme als abgeschlossen
- Nachweis: aktuelle AVV/DPA-PDF speichern
- Subprocessor: Hostinger-Unterauftragsverarbeiter regelmäßig prüfen
- Datenschutzerklärung: Hostinger als Hosting-Anbieter und Auftragsverarbeiter nennen

## Cloudflare

- Anbieter: Cloudflare
- Rolle: Auftragsverarbeiter für R2/CDN/DNS/Security-Leistungen, soweit eingesetzt
- Zweck: Objektspeicher, Medienauslieferung, Performance, Sicherheit, CDN-Auslieferung
- Betroffene: Nutzer, Vereine, Gäste, Käufer, Anbieter, Admins
- Datenarten: Medien-Dateien, technische Abrufdaten, IP-Adresse beim Abruf, CDN-/Security-/Metadaten je nach Cloudflare-Konfiguration
- DPA: Nach Anbieterantwort ist der Cloudflare Customer DPA bei Self-Serve-Kunden Bestandteil der Self-Serve Subscription Agreement
- Drittlandbezug: Cloudflare ist ein US-Anbieter; Absicherung nach Anbieterangabe über DPA, SCCs, EU-U.S. Data Privacy Framework, Swiss-U.S. DPF und UK Extension
- EU-only Hinweis: Eine verbindliche Zusicherung, dass alle Cloudflare-Daten und Metadaten ausschließlich in der EU bleiben, sollte erst gemacht werden, wenn passende Data Localization Suite Funktionen wie Regional Services, Metadata Boundary oder Geo Key Manager gebucht und aktiviert sind
- Nachweis: Cloudflare Customer DPA und verfügbare Compliance-Zertifikate speichern
- Subprocessor: Cloudflare-Unterauftragsverarbeiter regelmäßig prüfen
- Datenschutzerklärung: Cloudflare R2/CDN nennen, aber keine pauschale EU-only Garantie formulieren

## Mail

- Anbieter: noch nicht final festgelegt; Code unterstuetzt SMTP, SES, Postmark, Resend, Log und Array.
- Rolle: je nach Anbieter in der Regel Auftragsverarbeiter.
- Zweck: Transaktionsmails, Registrierung, Passwort, Guardian-Consent, Rechnungen, Support, Security, Marketplace und Legal-Kommunikation.
- Betroffene: Nutzer, Eltern, Vereinsverantwortliche, Kaeufer, Anbieter, Admins, Supportkontakte.
- Datenarten: E-Mail-Adresse, Name, Mailinhalt, technische Versanddaten, Zustellstatus.
- Projektkonfiguration: `MAIL_MAILER`, `MAIL_SYSTEM_MAILER`, `MAIL_BILLING_MAILER`, `MAIL_SUPPORT_MAILER`, `MAIL_MARKETPLACE_MAILER`, `MAIL_ACADEMY_MAILER`, `MAIL_SECURITY_MAILER`, `MAIL_PARTNERS_MAILER`, `MAIL_LEGAL_MAILER`.
- MVP-Regel: Produktiv-SMTP erst aktivieren, wenn Anbietername, DPA/AVV, Datenregion, Bounce-/Log-Aufbewahrung und Supportkontakt dokumentiert sind.

## Payments

- Anbieter: Stripe, PayPal; Bankueberweisung bleibt ohne externen Payment-Provider.
- Rolle: Stripe/PayPal sind je nach Verarbeitung eigener Verantwortlicher und/oder Auftragsverarbeiter; genaue Rolle mit Anbieterunterlagen pruefen.
- Zweck: Checkout, Zahlungsabwicklung, Aboverwaltung, Webhooks, Erstattung, Rechnungs- und Zahlungsstatus.
- Betroffene: Nutzer, Kaeufer, Vereins-/Abo-Zahler, Anbieter.
- Datenarten: Name, E-Mail, Warenkorb/Plan, Betrag, Waehrung, Checkout-/Payment-ID, Status, Webhook-Payload, Rechnungskontext.
- Projektkonfiguration: `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`, `PAYPAL_CLIENT_ID`, `PAYPAL_CLIENT_SECRET`, `PAYPAL_WEBHOOK_ID`, `PAYPAL_COMMERCE_WEBHOOK_ID`, `PAYPAL_OUTFIT_WEBHOOK_ID`, `PAYPAL_MODE`.
- MVP-Regel: Live-Modus erst aktivieren, wenn echte Webhook-Signaturen, DPA/Datenschutzhinweise und Zahlungsdatenfluss dokumentiert sind.

## Analytics und Tracking

- Anbieter: derzeit keiner aktiv eingebunden.
- Projektstand: Das Weblayout enthaelt keine externen Analytics-, Marketing-, Retargeting- oder Pixel-Skripte.
- Consent-Grenze: Clientseitige Landingpage-Events an `dataLayer` oder `gtag` sind technisch blockiert, solange `ads_measurement_consent` nicht aktiv ist.
- Dokumentation: `docs/COOKIE_TRACKING_CONSENT.md`.
- MVP-Regel: Neue Analytics- oder Marketing-Anbieter erst nach konkreter Benennung auf der Cookie-Seite, DPA/Datentransfer-Pruefung und aktivem Opt-in aktivieren.

## Mobile Push

- Anbieter: FCM/Google Firebase und Apple APNS sind vorbereitet; der aktuelle Dispatch-Service markiert Deliveries intern als gesendet und ruft noch keinen externen Push-Endpunkt auf.
- Rolle: Google/Apple je nach Pushdienst und Plattformbedingungen als Empfaenger bzw. Dienstleister pruefen.
- Zweck: Push-Benachrichtigungen fuer Chat, Events, Training, Commerce und Club/Billing.
- Betroffene: Mobile Nutzer.
- Datenarten: Device-Token, Plattform, App-Version, Sprache, Notification-Payload, Deep Link, Versandstatus.
- Projektkonfiguration: `MOBILE_PUSH_PROVIDER`, `FCM_PROJECT_ID`, `FCM_SERVER_KEY`, `FCM_CREDENTIALS`, `APNS_KEY_ID`, `APNS_TEAM_ID`, `APNS_BUNDLE_ID`, `APNS_PRIVATE_KEY`.
- MVP-Regel: Externen Pushversand erst aktivieren, wenn Firebase/APNS-Dokumentation, Aufbewahrung, Opt-in/Opt-out und Loeschung bei Logout getestet sind.

## Realtime

- Anbieter: Laravel Reverb ist self-hosted vorgesehen; Pusher ist optional konfigurierbar.
- Zweck: Realtime-Chat, Notifications und Events.
- Datenarten: Kanalnamen, Verbindungsdaten, Eventpayloads.
- Projektkonfiguration: `BROADCAST_CONNECTION`, `REVERB_*`, `PUSHER_*`.
- MVP-Regel: Reverb ist kein externer Subprozessor, solange self-hosted betrieben. Pusher erst aktivieren, wenn Pusher als Anbieter dokumentiert und rechtlich geprueft ist.

## Karten, Routing und Sportdaten

- Anbieter: OpenStreetMap Tiles, Esri Satellite Tiles, GraphHopper, OSRM, optional Mapbox.
- Zweck: Kartenanzeige, Routenplanung, Routengenerator, Distanz-/Profilberechnung.
- Datenarten: IP/technische Abrufdaten, Start-/Zielpunkte, Wegpunkte, Sportart/Routingprofil, optional Standortdaten.
- Projektkonfiguration: `SPORT_MAP_TILE_URL`, `SPORT_MAP_SATELLITE_TILE_URL`, `SPORT_MAP_ROUTING_PROVIDER`, `SPORT_MAP_ROUTE_GENERATOR_PROVIDER`, `SPORT_MAP_OSRM_BASE_URL`, `SPORT_MAP_GRAPHHOPPER_BASE_URL`, `GRAPHHOPPER_API_KEY`, `MAPBOX_ACCESS_TOKEN`.
- MVP-Regel: Produktive Anbieterwahl und Datenfluss im Datenschutztext halten; fuer sensible Live-Standortdaten ausdrueckliche Nutzeraktion/Einwilligung beibehalten.

## KI-Anbieter

- Anbieter: IONOS AI Model Hub primaer, OpenAI fallback, Google Gemini optional.
- Zweck: optionale KI-Funktionen fuer Ernaehrungsbildanalyse, Trainingsplaene und vergleichbare Assistenzfunktionen.
- Datenarten: Prompt, technische Kontextdaten, optional verkleinerte/EXIF-bereinigte Bilder, KI-Antwort, Nutzungs-/Fehlerdaten.
- Projektkonfiguration: `AIRMIUS_AI_*`, `IONOS_AI_*`, `OPENAI_*`, `GOOGLE_GEMINI_*`.
- MVP-Regel: Produktiv nur nach Anbieter-DPA, Datenregion-/Drittlandpruefung und ausdruecklichem Feature-Consent; Rohbilder sollen nicht dauerhaft gespeichert werden, solange `AIRMIUS_AI_STORE_UPLOADS=false`.

## Social Login, Fitness-Integrationen und externe Datenquellen

- Anbieter: Google Login, Google Fit, Microsoft Login, Strava, Open Food Facts.
- Zweck: Login, Sport-/Fitness-Integration, Lebensmittelabfrage.
- Datenarten: OAuth-Kennung, E-Mail/Profile-Basisdaten, Fit-/Sportdaten nach Scope, Lebensmittel-Suchanfragen.
- Projektkonfiguration: `GOOGLE_CLIENT_ID`, `GOOGLE_FIT_CLIENT_ID`, `MICROSOFT_CLIENT_ID`, `STRAVA_CLIENT_ID`, `OPEN_FOOD_FACTS_BASE_URL`.
- MVP-Regel: Nur die minimal notwendigen OAuth-Scopes verwenden und Anbieter erst in Store-/Datenschutzangaben freischalten, wenn sie produktiv genutzt werden.

## Monitoring und Betrieb

- Anbieter: noch nicht festgelegt.
- Zweck: Fehleranalyse, Performance, Queue-/Job-Fehler, Webhook-/Mailversand-Monitoring.
- Datenarten: technische Fehlerdaten, Request-Kontext, User-ID nur soweit noetig, keine Secrets.
- Projektkonfiguration: `ERROR_MONITORING_DSN`.
- MVP-Regel: Vor Aktivierung Anbieter, Datenminimierung, Scrubbing, Region, DPA und Loeschfrist dokumentieren.

## Subprocessor-Prüfprotokoll

| Datum | Anbieter | Quelle geprüft | Änderung | Maßnahme | Verantwortlich |
| --- | --- | --- | --- | --- | --- |
| 2026-05-04 | Hostinger | AVV/DPA-Antwort + Anbieterinfos | AVV gilt per Kontoannahme | PDF/Nachweis ablegen, VVT ergänzen | Airmius |
| 2026-05-04 | Cloudflare | DPA-Antwort + Anbieterinfos | DPA in Self-Serve Agreement, SCCs/DPF genannt | Datenschutzerklärung entschärft, EU-only nur mit Data Localization | Airmius |
| 2026-07-17 | Mail | `.env.example`, `config/mail.php`, `config/airmius_mail.php` | Konkreter Produktiv-SMTP-Anbieter noch nicht festgelegt | Vor Livegang Anbietername, DPA, Region und Log-Aufbewahrung eintragen | Airmius |
| 2026-07-17 | Stripe/PayPal | `.env.example`, `config/services.php`, Checkout/Webhook-Code | Payment-Provider vorbereitet | Live-Modus erst nach DPA/Datenschutz- und Webhook-Signaturpruefung | Airmius |
| 2026-07-17 | Analytics/Tracking | Weblayout, `docs/COOKIE_TRACKING_CONSENT.md` | Keine externen Analytics-/Marketing-Tags aktiv | Neue Anbieter nur mit Opt-in, Cookie-Seite und DPA aufnehmen | Airmius |
| 2026-07-17 | Push | `.env.example`, `MobilePushDeliveryService` | FCM/APNS vorbereitet, externer Versand noch nicht aktiv | Vor echter Auslieferung Firebase/APNS dokumentieren und Opt-out testen | Airmius |

## Prüfintervall

- Vor Beta: alle eingesetzten Anbieter prüfen und Nachweise speichern.
- Danach: mindestens alle 6 Monate Subprocessor-Listen und Datenschutzbedingungen prüfen.
- Zusätzlich prüfen, wenn neue Dienste eingebunden werden, z. B. Analytics, E-Mail-Provider, Payment-Provider, Ads-Tracking oder externe KI-/Moderationsdienste.

## VVT-Kurzbaustein

Für das Verzeichnis von Verarbeitungstätigkeiten sollten je Dienstleister mindestens diese Punkte gepflegt werden:

- Name und Kontaktdaten des Dienstleisters
- Rolle: Auftragsverarbeiter, eigener Verantwortlicher oder gemeinsamer Verantwortlicher
- Zweck der Verarbeitung
- Kategorien betroffener Personen
- Kategorien personenbezogener Daten
- Datenstandort und Drittlandbezug
- Rechtsgrundlage und AVV/DPA-Nachweis
- Unterauftragsverarbeiter/Subprocessor
- technische und organisatorische Maßnahmen
- Lösch- und Aufbewahrungslogik

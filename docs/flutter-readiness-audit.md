# Airmius Flutter Readiness Audit

Stand: 2026-05-17

## Kurzurteil

Die Webapp ist nach den aktuellen Reparaturen stabiler, mobilfreundlicher und deutlich besser fuer eine Flutter-Migration vorbereitet. Fuer Flutter gibt es jetzt eine versionierte `/api/v1`-Grundlage mit JSON-Vertraegen fuer Auth, Me/Locale, Mobile-UserCard, Settings, Notifications, Uploads, Clubs/Membership, Feed/Stories, Teams, Chat, Events, Training, Sportkarte/Routen/Tracking/Sportplaetze, Commerce, Subscriptions, Stripe-/PayPal-Redirect-Checkout und Admin-Commerce-Spezialaktionen. Eine direkte 1:1-Konvertierung der Vue-Seiten bleibt trotzdem nicht sinnvoll; Flutter sollte gegen diese API als native App neu aufgebaut werden.

## Direkt Repariert

- Alle nativen Browser-Dialoge (`window.confirm`, `window.prompt`, `alert`, globale `confirm/prompt`) sind aus `resources/js/Pages`, `resources/js/Components` und `resources/js/services` entfernt.
- Ein zentraler In-App-Dialog-Service ersetzt Confirm- und Prompt-Flows.
- Sprachdateien `de`, `en`, `fr`, `ar` haben identische Key-Sets.
- Alle literal verwendeten `t(...)`/`te(...)` Keys sind jetzt vorhanden.
- UTF-8/Mojibake-Pruefung in `resources/js` ist sauber.
- Die sehr schweren Commerce-Screens rendern inaktive Top-Level-Tabs jetzt per `v-if` nicht mehr mit.
- Teams- und Events-Wizard/Edit-Bereiche rendern inaktive Schritte jetzt per `v-if`.
- Die globale Auto-Uebersetzung laeuft fuer Deutsch nicht mehr bei jeder DOM-Mutation ueber den kompletten Inertia-Root.
- Eine versionierte Mobile/API-Schicht `/api/v1` wurde ergaenzt.
- JSON Resources fuer User, Clubs, Club-Mitglieder, Membership-Requests, Dateien, Rechnungen, Zahlungen, Teams, Posts, Conversations, Messages, Events, Training und Commerce stabilisieren die Flutter-Datenvertraege.
- Auth/Login/Logout, Sprache, Settings, Uploads, Club/Membership, Feed, Teams, Chat-Messages/Typing, Events, Trainingsplaene/-logs sowie Commerce-Produkte/-Bestellungen sind als API-Routen verfuegbar.
- User-Responses liefern jetzt ein kompaktes `user_card`-Objekt fuer Mobile/Flutter-Header, Listen und Profil-Teaser.
- Story-Upload schliesst den Web-Modal sofort beim Start, zeigt einen Upload-Hinweis und danach eine Feedback-Nachricht.
- Stories sind fuer Flutter als API-Routen verfuegbar: List, Upload, Viewed, React und Delete.
- Subscription-Plans, Subscription-Overview, Bankueberweisung-Checkout, Checkout-Abbruch, Abo-Kuendigung/-Verlaengerung und Admin-Mark-Paid sind als Mobile-API verfuegbar.
- Stripe und PayPal liefern in der Mobile-API jetzt einen klaren `payment_action` Redirect-Vertrag mit Checkout-URL, Return-URL und Cancel-URL.
- PaymentCheckout-Responses beschreiben Bankueberweisung und Provider-Redirects einheitlich fuer Flutter.
- Notifications sind als Mobile-API verfuegbar: Liste, Unread Count, Mark Read, Mark All Read und Delete.
- Komoot-aehnliche Sportkarten-Grundlage ist verfuegbar: Routen planen, Navigation-Cues erzeugen, Strecken tracken, Trackpunkte anhaengen, Tracks abschliessen und Community-Sportplaetze eintragen/finden.
- Die Webapp hat eine eigene Sportkarte-Seite, damit der grosse Training-Screen nicht weiter aufgeblaeht wird.
- Flutter bekommt 17 neue `/api/v1`-Routen fuer Sport-Routen, Sport-Tracks und Sportplaetze inklusive Nearby-Suche.
- Admin-Commerce hat schlanke Mobile-Actions fuer Dashboard-Zusammenfassung, Katalog, Coupons, Add-ons, Produktstatus, Versandstatus, Seller-Antraege, Website-Anfragen, Ads-Kampagnenstatus, Tax-/Shipping-Rates, Refunds, Rechnungs-/Gutschrift-Dokumente, Export, Payout-Paid und Payout-Profilstatus.
- CommerceOrder-Responses enthalten jetzt Rechnungsnummern, Gutschriften und Issue-/Refund-Status fuer mobile Admin-Flows.
- Refunds pruefen jetzt den offenen Restbetrag statt nur den urspruenglichen Bestellbetrag.
- Oeffentliche Rechtstexte nutzen konfigurierbare Legal-Kontaktdaten und geben keine internen Platzhalter mehr aus.
- Rollen-Workspaces zeigen keine sichtbaren "UI fehlt noch"-Zustaende mehr, sondern verlinken auf vorhandene Arbeitsbereiche.

## Groesste Screens

| Bereich | Datei | Bewertung |
| --- | --- | --- |
| Nutzer-Commerce | `resources/js/Pages/Auth/Dashboard/Commerce/Index.vue` | Sehr gross, aber Top-Level-Tabs rendern nun lazy. Fuer Flutter in Shop, Cart, Seller, Ads, Payouts, Orders splitten. |
| Admin-Commerce | `resources/js/Pages/Auth/Dashboard/Admin/Commerce/Index.vue` | Sehr gross, aber Top-Level-Tabs rendern nun lazy. Fuer Flutter in Marketplace, Orders, Ads, Tax/Shipping, Payouts splitten. |
| Teams | `resources/js/Pages/Auth/Dashboard/Teams/Index.vue` | Grosse Vereins-/Team-/Wizard-Datei. Edit- und Wizard-Abschnitte sind optimiert, sollte spaeter in ClubList, TeamList, MemberRoleEditor, CreateClubFlow zerlegt werden. |
| Chat | `resources/js/Pages/Auth/Dashboard/Chat/Index.vue` | Funktional stark, aber Flutter braucht eigene API/WebSocket-Vertraege und Message-Windowing. |
| Training | `resources/js/Pages/Auth/Dashboard/Training/Index.vue` | Viele Workflows in einem Screen. Fuer Flutter in Dashboard, Logs, Plans, Calendar, Modals splitten. Sportkarte/Tracking ist jetzt bewusst ausgelagert. |
| Sportkarte | `resources/js/Pages/Auth/Dashboard/SportMap/Index.vue` | Neuer eigenstaendiger Screen fuer Routenplanung, Browser-Tracking und Sportplaetze. Fuer Flutter in RoutePlanner, TrackRecorder, PlaceDirectory und PlaceForm splitten. |
| Events | `resources/js/Pages/Auth/Dashboard/Events/Index.vue` | Wizard-Schritte sind optimiert. Fuer Flutter Calendar/List/CreateFlow trennen. |
| Settings | `resources/js/Pages/Auth/Dashboard/Settings/Index.vue` | Bereits tabweise mit `v-if`; fuer Flutter in einzelne Settings-Routen/Screens zerlegen. |
| ClubMemberships | `resources/js/Pages/Auth/Dashboard/ClubMemberships/Index.vue` | Bereits tabweise mit `v-if`; API-Vertraege fuer Mitglieder, Rechnungen, SEPA/DATEV fehlen. |

## Flutter Blocker

1. API-Abdeckung: Die wichtigsten Mobile-Endpunkte existieren, inklusive Settings, Notifications, Uploads, Club/Membership, Stories, Sportkarte/Routen/Tracking/Sportplaetze, Subscription-Checkout fuer Bank/Stripe/PayPal, Refunds, Dokumentlinks/-Downloads, Export und Admin-Commerce-Spezialaktionen.
2. Controller-Groesse: `CommerceCheckoutController` mit ueber 4000 Zeilen und mehrere weitere Controller ueber 1000 Zeilen sind schwer testbar und sollten weiter in Services/Actions zerlegt werden.
3. Datenvertraege: Core-Resources und Mobile-Spezialvertraege existieren jetzt; fuer eine langfristig sehr saubere Architektur sollten sie spaeter in dedizierte Request-/Response-DTOs und Actions ausgelagert werden.
4. Zahlungen und Checkout: Bankueberweisung, Stripe und PayPal sind API-faehig. Die Flutter-App muss die Redirects bzw. Provider-SDKs, Deep Links und Return-Screens nativ integrieren.
5. Echtzeit/Chat: Flutter braucht klare Reverb/Echo-Kanalnamen, Auth-Regeln, Pagination und Attachment-Vertraege.
6. Internationalisierung: Key-Paritaet ist repariert, aber viele UI-Texte sind weiterhin direkt in Vue-Templates. Fuer Flutter sollten diese schrittweise in strukturierte Locale-Dateien wandern.
7. Admin-Flows: Einige Admin-Bereiche sind fuer Desktop-Dichte gebaut. Mobile Flutter braucht Bottom Sheets, Stepper und reduzierte Listenansichten.

## Aktueller Status

Die Webapp funktioniert nach dieser Runde besser, grosse Screens sind lazy gerendert, native Browser-Dialoge sind ersetzt, Sprachdateien sind synchron, Legal-Platzhalter sind entfernt und die Mobile-API deckt die kritischen Flutter-Startbereiche ab. Die Sportkarte liefert jetzt die Komoot-aehnliche Produktgrundlage fuer Routen, Tracking und Community-Sportplaetze. Fuer Flutter ist das Backend jetzt migrationsbereit gegen `/api/v1`. Was danach noch fehlt, ist die eigentliche Flutter-App mit nativen Screens, Provider-Deep-Links, Push-/Realtime-Anbindung, nativer GPS-Hintergrundaufzeichnung und UI-Zerlegung in Bottom Sheets, Dialog-Flows und kleinere Feature-Screens.

# Airmius Flutter Readiness Audit

Stand: 2026-05-17

## Kurzurteil

Die Webapp ist nach den aktuellen Reparaturen stabiler und leichter zu bedienen, aber noch nicht bereit fuer eine direkte Flutter-Konvertierung. Der groesste verbleibende Blocker ist nicht Vue, sondern die Architektur: Die Funktionen haengen fast komplett an Inertia-Webseiten und Web-Controllern. `routes/api.php` enthaelt aktuell nur `/api/user`, also fehlen versionierte mobile API-Vertraege fuer Feed, Teams, Chat, Commerce, Training, Events, Settings, Uploads, Zahlungen und Admin-Flows.

## Direkt Repariert

- Alle nativen Browser-Dialoge (`window.confirm`, `window.prompt`, `alert`, globale `confirm/prompt`) sind aus `resources/js/Pages`, `resources/js/Components` und `resources/js/services` entfernt.
- Ein zentraler In-App-Dialog-Service ersetzt Confirm- und Prompt-Flows.
- Sprachdateien `de`, `en`, `fr`, `ar` haben identische Key-Sets.
- Alle literal verwendeten `t(...)`/`te(...)` Keys sind jetzt vorhanden.
- UTF-8/Mojibake-Pruefung in `resources/js` ist sauber.
- Die sehr schweren Commerce-Screens rendern inaktive Top-Level-Tabs jetzt per `v-if` nicht mehr mit.
- Teams- und Events-Wizard/Edit-Bereiche rendern inaktive Schritte jetzt per `v-if`.
- Die globale Auto-Uebersetzung laeuft fuer Deutsch nicht mehr bei jeder DOM-Mutation ueber den kompletten Inertia-Root.

## Groesste Screens

| Bereich | Datei | Bewertung |
| --- | --- | --- |
| Nutzer-Commerce | `resources/js/Pages/Auth/Dashboard/Commerce/Index.vue` | Sehr gross, aber Top-Level-Tabs rendern nun lazy. Fuer Flutter in Shop, Cart, Seller, Ads, Payouts, Orders splitten. |
| Admin-Commerce | `resources/js/Pages/Auth/Dashboard/Admin/Commerce/Index.vue` | Sehr gross, aber Top-Level-Tabs rendern nun lazy. Fuer Flutter in Marketplace, Orders, Ads, Tax/Shipping, Payouts splitten. |
| Teams | `resources/js/Pages/Auth/Dashboard/Teams/Index.vue` | Grosse Vereins-/Team-/Wizard-Datei. Edit- und Wizard-Abschnitte sind optimiert, sollte spaeter in ClubList, TeamList, MemberRoleEditor, CreateClubFlow zerlegt werden. |
| Chat | `resources/js/Pages/Auth/Dashboard/Chat/Index.vue` | Funktional stark, aber Flutter braucht eigene API/WebSocket-Vertraege und Message-Windowing. |
| Training | `resources/js/Pages/Auth/Dashboard/Training/Index.vue` | Viele Workflows in einem Screen. Fuer Flutter in Dashboard, Logs, Plans, Calendar, Modals splitten. |
| Events | `resources/js/Pages/Auth/Dashboard/Events/Index.vue` | Wizard-Schritte sind optimiert. Fuer Flutter Calendar/List/CreateFlow trennen. |
| Settings | `resources/js/Pages/Auth/Dashboard/Settings/Index.vue` | Bereits tabweise mit `v-if`; fuer Flutter in einzelne Settings-Routen/Screens zerlegen. |
| ClubMemberships | `resources/js/Pages/Auth/Dashboard/ClubMemberships/Index.vue` | Bereits tabweise mit `v-if`; API-Vertraege fuer Mitglieder, Rechnungen, SEPA/DATEV fehlen. |

## Flutter Blocker

1. API-Abdeckung: Mobile kann aktuell nicht sauber gegen JSON-Endpunkte arbeiten.
2. Controller-Groesse: `CommerceCheckoutController` mit ueber 4000 Zeilen und mehrere weitere Controller ueber 1000 Zeilen sind schwer testbar und schwer in mobile Use-Cases zu uebersetzen.
3. Datenvertraege: Es fehlen API Resources/DTOs fuer stabile Response-Formate.
4. Uploads und Zahlungen: Web-Flows, Redirects und Webhooks muessen fuer Mobile getrennt dokumentiert und getestet werden.
5. Echtzeit/Chat: Flutter braucht klare Reverb/Echo-Kanalnamen, Auth-Regeln, Pagination und Attachment-Vertraege.
6. Internationalisierung: Key-Paritaet ist repariert, aber viele UI-Texte sind weiterhin direkt in Vue-Templates. Fuer Flutter sollten diese schrittweise in strukturierte Locale-Dateien wandern.
7. Admin-Flows: Einige Admin-Bereiche sind fuer Desktop-Dichte gebaut. Mobile Flutter braucht Bottom Sheets, Stepper und reduzierte Listenansichten.

## Aktueller Status

Die Webapp sollte nach dieser Runde besser funktionieren und performanter auf grossen Screens reagieren. Fuer Flutter ist sie bereit fuer eine geplante Migration, aber nicht fuer eine 1:1-Konvertierung ohne API-Phase.

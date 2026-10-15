# Phasen T29-T40: Web-/App-Paritaet

Stand: 15.10.2026

## Ergebnis

Die fachlichen Module T29 bis T40 verwenden in Web und App dieselben serverseitigen Daten, Statuswechsel und Berechtigungsgrenzen. Native Oberflaechen sind keine statischen Menueeintraege: Sie rufen die API auf und zeigen Lade-, Leer-, Fehler- und Berechtigungszustaende.

| Phase | Web | App | Gemeinsamer Vertrag |
| --- | --- | --- | --- |
| T29 Sponsoren | Kampagnen, Profile, Partnerschaften, Leistungen | Sponsor-Cockpit, Kampagneneditor, Leistungen mit Anlegen/Bearbeiten/Loeschen | Sponsor-Workspace und Sponsor-Management-API |
| T30 Marketplace | Katalog, Warenkorb, Checkout, Anbieter | Marketplace, Warenkorb, Checkout, Anbieter-/Bestellbereiche | Commerce-API und serverseitige Preisberechnung |
| T31 Abos/Outfit | Tarife, Abrechnung, Outfit | Abo-Center, Abrechnung, Outfit | Subscription-, Billing- und Outfit-API |
| T32 Eltern | Elternportal und Zustimmung | Elternzugang, Kinderwechsel, Zustimmung/Widerruf | Guardian-/Consent-API |
| T33 Administration | Admin-Fachmodule | Berechtigungsgesteuerter Admin-Arbeitsbereich mit Fachscreens | Admin-API und identische Policies |
| T34 Datenschutzgrenzen | Policies, Direktlinks, Exporte | Token-, Cache-, Datei- und Kontextgrenzen | Serverseitige Vereins- und Objektpruefung |
| T35 Bedienbarkeit | Responsive Formulare, Fehlerzustaende | Tastatur, grosse Schrift, Offline-/Fehlerzustaende | Gemeinsame Validierungsregeln |
| T36 Technik | Scheduler, Provider, Webhooks, oeffentliche Auslieferung | Synchronisierung, Metadaten und Wiederanlauf | Technischer Backendvertrag; kein kuenstliches App-Menue |
| T37 Nutzerreisen | Web-Einstiege und Bearbeitung | Push-/Deep-Link-Ziele und native Fortsetzung | Journey-Registry und Mobile-Deep-Link-Resolver |
| T38 Fahrgemeinschaften | `/rides` | Fahrgemeinschaftsplaner | Rides-API mit Kapazitaets- und Rechtepruefung |
| T39 Support | `/support` | Hilfe-/Ticketbereich und Admin-Tickets | Support-API mit Nutzer-/Vereinsgrenzen |
| T40 Oeffentlich | Gastseiten, Cookie-Auswahl, RSS/Sitemap/Manifest | Gastportal, Rechtstexte, Datenschutz-/Mess-Einwilligungen | Oeffentliche Datenquellen und Privacy-API |

## Bewusste Plattformunterschiede

`robots.txt`, Sitemap, RSS und Webmanifest sind Auslieferungsformate fuer Browser, Suchmaschinen und Feed-Reader. Sie werden daher nicht als kuenstliche App-Seiten dupliziert. Die App stellt stattdessen die fachlichen Inhalte nativ bereit. Cookie-Einstellungen des Browsers bleiben im Browser; die App besitzt eigene, kontogebundene Einwilligungen fuer Personalisierung, Messung und Produktanalyse.

## Neu geschlossen

- Sponsorleistungen koennen in der App angelegt, bearbeitet und geloescht werden.
- Die App erhaelt pro Partnerschaft explizite Bearbeiten-/Loeschen-Rechte; ausgeblendete Aktionen werden serverseitig nochmals geprueft.
- Erfuellte Leistungen verlangen wie im Web einen Nachweis.
- Die Sammelpruefung `Phase29To40WebMobileParityContractTest` verhindert, dass eine Phase spaeter unbemerkt nur auf einer Plattform weiterentwickelt wird.

## Noch als reale Abnahme erforderlich

Zahlungsprovider, Push-Zustellung, gleichzeitige Buchungen, Offline-/Updateverhalten und externe E-Mail-Zustellung muessen mit Testkonten und Testprovidern auf echten Geraeten durchlaufen werden. Vor diesem Nachweis ist eine pauschale Aussage "10/10 fehlerfrei" nicht belastbar.

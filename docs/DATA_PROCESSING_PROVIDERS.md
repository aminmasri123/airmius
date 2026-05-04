# Airmius AVV, DPA und Dienstleister-Nachweise

Stand: 2026-05-04

Diese Datei ist die interne Nachweis- und VVT-Hilfe für Auftragsverarbeiter und wichtige technische Dienstleister. Sie ersetzt keine anwaltliche Prüfung, hilft aber dabei, Nachweise, Subprocessor-Prüfungen und Datenschutzhinweise sauber zu dokumentieren.

## Nachweisablage

Lege aktuelle Vertrags- und Compliance-Dokumente intern ab, zum Beispiel:

- `docs/legal/provider/hostinger-avv-dpa.pdf`
- `docs/legal/provider/cloudflare-customer-dpa.pdf`
- `docs/legal/provider/cloudflare-compliance-certificates.pdf`
- `docs/legal/provider/stripe-dpa.pdf`
- `docs/legal/provider/paypal-privacy-or-dpa.pdf`

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

## Subprocessor-Prüfprotokoll

| Datum | Anbieter | Quelle geprüft | Änderung | Maßnahme | Verantwortlich |
| --- | --- | --- | --- | --- | --- |
| 2026-05-04 | Hostinger | AVV/DPA-Antwort + Anbieterinfos | AVV gilt per Kontoannahme | PDF/Nachweis ablegen, VVT ergänzen | Airmius |
| 2026-05-04 | Cloudflare | DPA-Antwort + Anbieterinfos | DPA in Self-Serve Agreement, SCCs/DPF genannt | Datenschutzerklärung entschärft, EU-only nur mit Data Localization | Airmius |

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

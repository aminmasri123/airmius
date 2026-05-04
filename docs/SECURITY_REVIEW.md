# Airmius Security Kurzprüfung

Stand: 2026-05-04

Diese Datei ist eine technische Kurzprüfung, kein externer Penetrationstest. Der aktuelle Stand wirkt als Beta-Basis ordentlich, muss vor einem öffentlichen Produktivstart aber noch gehärtet und mit echten Provider-Daten getestet werden.

## Geprüft

- Laravel Tests: zuletzt erfolgreich.
- Frontend Build: zuletzt erfolgreich.
- `npm audit --audit-level=high`: keine bekannten Vulnerabilities.
- `composer audit --no-interaction`: keine bekannten Security Advisories.
- Middleware-Kette geprüft:
  - Wartungsmodus.
  - Account-Sperre.
  - Profilvervollständigung.
  - Elternzustimmung.
- Sensible User-Felder sind im Model verborgen:
  - Passwort.
  - Remember Token.
  - Two-Factor-Daten.
- Debug-Ausgabe im Profilformular entfernt.
- Webhook-Routen sind von CSRF ausgenommen und müssen über Provider-Signaturen geschützt werden.

## Vor Beta prüfen

- `APP_ENV=production` nur auf Produktivsystem setzen.
- `APP_DEBUG=false` auf Produktivsystem setzen.
- `APP_URL=https://deine-domain.de` setzen.
- `SESSION_SECURE_COOKIE=true` setzen, sobald HTTPS aktiv ist.
- Google/Microsoft OAuth Redirect-URLs exakt auf die echte Domain setzen.
- Stripe- und PayPal-Webhooks mit echten Secrets/Webhook-IDs testen.
- Admin-Konten mit Zwei-Faktor-Authentifizierung absichern.
- Rollen und Berechtigungen für Adminbereiche manuell testen.
- Elternzustimmung, Profilvervollständigung und Wartungsmodus mit echten Rollen testen.
- Moderationsflows mit harmlosen Testfällen prüfen.
- Datei-Uploads auf erlaubte Dateitypen, Größe und Sichtbarkeit prüfen.

## Vor Produktion härten

- HTTPS erzwingen und HSTS aktivieren.
- Security Header setzen:
  - Content-Security-Policy.
  - X-Frame-Options oder `frame-ancestors`.
  - Referrer-Policy.
  - Permissions-Policy.
- Rate-Limits für öffentliche und sensible Endpunkte prüfen:
  - Login.
  - Registrierung.
  - Eltern-Code.
  - Kontakt/Melden.
  - Checkout.
  - Webhooks.
- Backup-Konzept für Datenbank und Storage testen.
- Logging und Alarmierung für Fehler, Webhooks, Payments und Moderation aktivieren.
- Rechtliche Platzhalter in den Legal-Seiten ersetzen.
- Echte Zahlungs- und Rückerstattungsabläufe in Stripe/PayPal Sandbox testen.
- Cloudflare/R2 Storage-Regeln prüfen:
  - Private Dateien privat halten.
  - Öffentliche Profilbilder bewusst freigeben.
  - Keine sensiblen Dokumente über erratbare URLs ausliefern.
  - Cloudflare nicht pauschal als EU-only formulieren, solange Data Localization Suite, Regional Services, Metadata Boundary oder Geo Key Manager nicht verbindlich gebucht und aktiviert sind.
  - DPA/SCCs/DPF und Cloudflare-Subprocessor in `docs/DATA_PROCESSING_PROVIDERS.md` dokumentieren.
- AVV/DPA-Nachweise pflegen:
  - Hostinger-AVV/DPA-PDF bzw. Nachweis sichern.
  - Cloudflare Customer DPA und Compliance-Dokumente sichern.
  - Subprocessor mindestens alle 6 Monate prüfen.
  - VVT-Einträge für Hostinger, Cloudflare, Stripe, PayPal und weitere Dienstleister aktuell halten.
- DSGVO-Aufbewahrung und Inaktivität prüfen:
  - `last_seen_at` wird für angemeldete Nutzer höchstens alle 15 Minuten aktualisiert.
  - Der Scheduler `airmius:process-inactive-accounts` versendet nach ca. 12 und 18 Monaten Inaktivität Warnungen.
  - Nach ca. 24 Monaten wird das Konto zur Anonymisierung vorgemerkt; nach ca. 30 weiteren Tagen erfolgt die Anonymisierung.
  - Eine erneute Anmeldung setzt Warnungen und geplante Anonymisierung zurück.
  - Anonymisierung entfernt Profilangaben, Tokens, Social-/Sport-Verknüpfungen, Profilbilder und persönliche Medien/Inhalte soweit technisch vorgesehen.
  - Rechnungs-, Zahlungs-, Bestell-, Vereins- und Nachweisdaten bleiben erhalten, soweit gesetzliche oder berechtigte Aufbewahrungsgründe bestehen.
  - Der Laravel Scheduler muss in Produktion laufen, sonst greifen diese Fristen nicht automatisch.

## Restrisiko

Ein sicheres System entsteht nicht durch einen einzelnen Check, sondern durch laufende Tests, Updates, Monitoring und klare Betriebsprozesse. Für einen öffentlichen Go-live ist ein externer Security-Review sinnvoll.

# Airmius Security Kurzprüfung

Stand: 2026-07-17

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
- Webhook-Routen sind von CSRF ausgenommen und ueber Provider-Signaturen geschuetzt:
  - Stripe nutzt `STRIPE_WEBHOOK_SECRET`, HMAC-SHA256 und ein konfigurierbares Toleranzfenster.
  - PayPal nutzt die Provider-Verifikation `/v1/notifications/verify-webhook-signature` mit dem jeweiligen Webhook-ID-Kontext fuer Abo, Commerce und Outfit.
- Globale Security-Header sind per `App\Http\Middleware\ApplySecurityHeaders` aktiv:
  - `Content-Security-Policy` mit `default-src 'self'`, `object-src 'none'` und `frame-ancestors 'none'`.
  - `X-Frame-Options: DENY`.
  - `Referrer-Policy: strict-origin-when-cross-origin`.
  - `X-Content-Type-Options: nosniff`.
  - `Permissions-Policy` mit gesperrter Kamera/Mikrofon/USB und erlaubter Geolocation nur fuer die eigene Origin.
  - `Strict-Transport-Security` bei HTTPS-Requests.
  - `unsafe-eval` ist aus der produktiven CSP entfernt und wird ausschließlich bei erkanntem lokalen Vite-Devserver ergänzt.
- Plattform-Adminrollen `super_admin`, `admin` und `system_admin` muessen bestaetigte 2FA haben:
  - Web-Adminbereich wird sonst zu `/settings?tab=security` umgeleitet.
  - Mobile/API-Adminrouten liefern `403` mit Code `admin_two_factor_required`.

## Vor Beta prüfen

- `APP_ENV=production` nur auf Produktivsystem setzen.
- `APP_DEBUG=false` auf Produktivsystem setzen.
- `APP_URL=https://deine-domain.de` setzen.
- `SESSION_SECURE_COOKIE=true` setzen, sobald HTTPS aktiv ist.
- Google/Microsoft OAuth Redirect-URLs exakt auf die echte Domain setzen.
- Stripe- und PayPal-Webhooks mit echten Sandbox-Secrets/Webhook-IDs gegen Provider-Testevents pruefen.
- Admin-Konten mit Zwei-Faktor-Authentifizierung anlegen und Recovery-Codes sicher ablegen.
- Rollen und Berechtigungen für Adminbereiche manuell testen.
- Elternzustimmung, Profilvervollständigung und Wartungsmodus mit echten Rollen testen.
- Moderationsflows mit harmlosen Testfällen prüfen.
- Datei-Uploads auf erlaubte Dateitypen, Größe und Sichtbarkeit prüfen.

## Vor Produktion härten

- HTTPS auf dem Server erzwingen und HSTS im Produktivbetrieb mit echter Domain pruefen.
- Content-Security-Policy nach jeder neuen externen Integration pruefen und enger ziehen, sobald keine Inline-Skripte mehr benoetigt werden.
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

## Technischer Drill 9. August 2026

- `php artisan airmius:audit-security-privacy` besteht mit sieben automatisierten Struktur- und Betriebschecks.
- Alle sechs Retention-Bereiche besitzen `--dry-run` sowie harte Limits oder 50er-Chunks; die geplanten Jobs laufen mit `withoutOverlapping`.
- Der Drill deckt Gast-Bestelltoken, öffentliche Einwilligungsformulare, Rate-Limits, Discovery-Datensparsamkeit und Cookie-/Trackinggrenzen ausdrücklich ab.
- Nach gezielten Updates auf Guzzle 7.15.3, CommonMark 2.9.0, PostCSS 8.5.26, nanoid 3.3.18, concurrently 9.2.4, shell-quote 1.9.0 und socket.io-parser 4.2.7 melden `composer audit` und `npm audit --audit-level=high` keine bekannten Befunde.
- Vollständige Suite: 858 bestanden, 4 bewusst übersprungen, 17.674 Assertions. Produktionsbuild: 1.033 Module, 180 Manifest-Einträge.
- DPIA und unabhängiger Penetrationstest bleiben in `resources/release/platform_release_gates.json` bewusst `pending`.

Der Ablauf und die Evidenzregeln stehen im [Security-/Privacy-Incident-Runbook](SECURITY_PRIVACY_INCIDENT_RUNBOOK.md).

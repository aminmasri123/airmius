# Cookie- und Tracking-Consent

Stand: 2026-07-17

## Aktueller Web-Stand

- Das Inertia-Root-Layout bindet keine externen Analytics-, Marketing-, Retargeting- oder Pixel-Skripte ein.
- Notwendige Cookies und lokale Speicherwerte bleiben erlaubt: Login-Session, CSRF/XSRF, Sprache, Theme, Dashboard-Anordnung und vergleichbare Bedienungswerte.
- Gastnutzer erhalten im Inertia-Payload standardmaessig `privacyConsent.ads_measurement_consent=false` und `privacyConsent.ads_personalization_consent=false`.
- Eingeloggte Nutzer erhalten ihre gespeicherten Consent-Werte aus `users.ads_measurement_consent` und `users.ads_personalization_consent`.
- Landingpage-Events an `dataLayer` oder `gtag` werden durch `resources/js/services/privacyConsent.js` blockiert, solange `ads_measurement_consent` nicht aktiv ist.

## Technische Grenze

Optionale Messung darf nur laufen, wenn beide Bedingungen erfuellt sind:

1. Der Anbieter ist auf `/cookies` konkret benannt.
2. Die passende Einwilligung ist gespeichert.

Fuer Conversion- oder Kampagnenmessung ist `ads_measurement_consent=true` erforderlich. Fuer personalisierte Ausspielung oder Retargeting ist `ads_personalization_consent=true` erforderlich.

## Pruefpunkte vor neuen Tags

- Anbieter in Datenschutz, Cookie-Seite und Subprozessorenliste ergaenzen.
- Consent-Opt-in im UI eindeutig machen.
- Tracking-Code nur hinter `canTrackMarketingEvent(page.props)` oder einer strengeren Consent-Pruefung ausfuehren.
- Widerruf testen: Nach Widerruf duerfen keine neuen optionalen Tracking-Signale entstehen.
- `php artisan test tests/Feature/CookieTrackingConsentTest.php` und `npm run build` ausfuehren.

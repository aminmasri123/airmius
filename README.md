# AIRMIUS

AIRMIUS ist eine All-in-one-Plattform fuer Sportler, Trainer und Vereine. Der MVP verbindet Web/Inertia und Flutter ueber dieselbe Laravel API fuer Login, Profile, Feed, Chat, Vereine, Teams, Events, Training, Dateien, Notifications und Vereinsverwaltung.

## Lokales Setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
```

Fuer lokale Tests wird SQLite empfohlen. Stelle sicher, dass die PHP-Erweiterungen `pdo_sqlite` und `sqlite3` installiert sind. Alternativ muss `phpunit.xml` auf eine MySQL-Testdatenbank zeigen.

## Web Starten

```bash
php artisan serve
npm run dev
php artisan queue:work
php artisan reverb:start
```

Wichtige lokale URLs:

- Web: `http://127.0.0.1:8000`
- API: `http://127.0.0.1:8000/api/v1`
- Reverb: siehe `REVERB_*` in `.env`

## Flutter Starten

```bash
cd mobile/airmius_mobile
flutter pub get
flutter run --dart-define=AIRMIUS_API_BASE_URL=http://127.0.0.1:8000

```

Web-Runner fuer schnelle Tests:

```bash
flutter run -d web-server --dart-define=AIRMIUS_API_BASE_URL=https://airmius.com
```

## Tests und Build

```bash
php artisan test
npm run build
cd mobile/airmius_mobile && flutter analyze
cd mobile/airmius_mobile && flutter test
```

Release-Blocker sind alle roten Ergebnisse in diesen vier Checks, ausser sie sind in `docs/MVP_SMOKE_TEST_CHECKLIST.md` ausdruecklich als nicht-blockierend dokumentiert.

Der laufende Fortschritt wird in `docs/AIRMIUS_MIKRO_CHECKLISTE_MVP.md` gepflegt. Nach jeder weiteren Bearbeitung werden dort die erledigten Punkte mit `[x]` markiert.

Testrollen und sichere lokale Testaccount-Regeln stehen in `docs/TEST_ACCOUNTS.md`.

## Wichtige Env-Bereiche

- App/API: `APP_URL`, `AIRMIUS_API_BASE_URL`, `SANCTUM_STATEFUL_DOMAINS`
- Realtime: `BROADCAST_CONNECTION`, `REVERB_*`, `VITE_REVERB_*`
- Storage/Uploads: `FILESYSTEM_DISK`, `UPLOAD_DISK`, `R2_*`, `UPLOAD_URL`
- Mail: `MAIL_*`, getrennte Kategorien fuer Support, Billing, Legal, Security
- Payments: `STRIPE_*`, `PAYPAL_*`
- Maps/Sportkarte: `SPORT_MAP_*`, `GRAPHHOPPER_API_KEY`
- Push/Mobile: `AIRMIUS_MOBILE_*`, `MOBILE_PUSH_*`, `FCM_*`, `APNS_*`
- Legal/Billing: `LEGAL_*`, `AIRMIUS_BILLING_*`

## MVP Smoke Reihenfolge

1. Login und Registrierung im Web testen.
2. Login und Registrierung in Flutter testen.
3. Profil aktualisieren.
4. Verein erstellen und mobil bearbeiten.
5. Mitgliedsantrag stellen, anzeigen, zurueckziehen.
6. Feed-Beitrag erstellen, kommentieren, liken, melden.
7. Chat-Konversation oeffnen und Nachricht senden.
8. Team erstellen, Mitglied einladen, Anwesenheit pruefen.
9. Event erstellen, teilnehmen, verlassen.
10. Datei hochladen, Ordner erstellen, Datei loeschen.
11. Notifications lesen/ungelesen setzen.
12. Rechnungen ueber `/api/v1/billing/invoices` abrufen.

## Deploy Kurznotizen

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan config:cache
php artisan view:cache
php artisan queue:restart
```

Vor Produktivbetrieb: `APP_ENV=production`, `APP_DEBUG=false`, HTTPS/HSTS, echte Payment-Webhooks, Backups, Monitoring und finale juristische Pruefung aktivieren.
# Hostinger: Login-Patch sicher einspielen

Der mobile Native-Login erreicht `https://airmius.com` bereits. Der Browser-/Flutter-Web-Login wird aktuell noch vor der Zugangsdatenprüfung mit HTTP 419 abgewiesen.

## 1. Datei hochladen

Die lokale Datei

`bootstrap/app.php`

aus diesem Projekt muss die gleichnamige Datei im Hostinger-Projektverzeichnis ersetzen. Keine anderen Dateien löschen.

Die Änderung nimmt ausschließlich `api/v1/auth/login` von der SPA-CSRF-Prüfung aus. Bearer-Token, Rate-Limit und die eigentliche Login-Prüfung bleiben aktiv.

## 2. Cache leeren

Falls SSH verfügbar ist, im Projektverzeichnis ausführen:

```bash
php artisan optimize:clear
```

Ohne SSH genügt der Datei-Upload; der nächste PHP-Request verwendet normalerweise die aktualisierte Bootstrap-Datei.

## 3. Prüfen

Ein absichtlich ungültiger Login mit Browser-Origin darf danach nicht mehr 419 liefern:

```bash
curl -i -X POST https://airmius.com/api/v1/auth/login \
  -H 'Origin: https://airmius.com' \
  -H 'Content-Type: application/json' \
  --data '{"email":"probe@example.invalid","password":"invalid"}'
```

Erwartet: HTTP `422` mit `auth.failed`. HTTP `419` bedeutet, dass die Serverdatei noch nicht aktualisiert wurde.

## Sicherheit

Keine Zugangsdaten, Tokens oder Produktionsdaten in Support-Nachrichten oder Screenshots teilen. Nach dem Upload zuerst den absichtlich ungültigen Test ausführen; erst danach mit einem echten Konto anmelden.

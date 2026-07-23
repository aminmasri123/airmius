# Mobile Push Notifications

Stand: 20.07.2026

## Architektur

Die Flutter-App fordert nach ausdrücklichem Opt-in einen echten Firebase-Messaging-Token an und registriert ihn über `POST /api/v1/mobile/push-devices`. Laravel legt Benachrichtigungen zunächst als `queued` ab. Der Scheduler ruft jede Minute `airmius:mobile-push-dispatch --limit=500` auf. Erst eine erfolgreiche FCM- oder Expo-Antwort setzt eine Zustellung auf `sent`; Providerfehler werden als `failed` gespeichert.

iOS wird ebenfalls über FCM ausgeliefert; Firebase leitet Nachrichten über die im Firebase-Projekt hinterlegte APNs-Konfiguration weiter.

## Serverkonfiguration

1. Im Firebase-Projekt ein Servicekonto mit der minimal notwendigen Firebase-Messaging-Berechtigung anlegen.
2. Die JSON-Datei außerhalb von Repository und Webroot mit Dateirechten `0600` ablegen.
3. Produktionswerte setzen:

```dotenv
FIREBASE_CREDENTIALS=/secure/path/airmius-firebase-service-account.json
FIREBASE_PROJECT_ID=airmius-app
```

4. Queue-Worker und Laravel-Scheduler dauerhaft über Supervisor/systemd betreiben.
5. Einen Testnutzer und ein reales Gerät registrieren und anschließend `php artisan airmius:mobile-push-dispatch` ausführen.

Die Servicekonto-Datei darf niemals committed, in Logs ausgegeben oder über das Web ausgeliefert werden.

## Flutter/Firebase-Konfiguration

Im Verzeichnis `mobile/airmius_mobile` gegen das richtige Firebase-Projekt ausführen:

```bash
flutterfire configure
```

Danach müssen Android-Paketname, iOS-Bundle-ID, APNs-Key beziehungsweise Zertifikat und die Firebase-App-IDs mit den signierten Store-Apps übereinstimmen. Die erzeugten nicht geheimen Firebase-Optionen dürfen versioniert werden; Servicekonto- und APNs-Private-Keys nicht.

## Release-Abnahme

Auf mindestens einem echten Android- und iOS-Gerät prüfen:

1. Opt-in und Betriebssystemberechtigung.
2. Tokenregistrierung und Tokenwechsel nach Neuinstallation.
3. Nachricht im Vordergrund, Hintergrund und bei beendeter App.
4. Deep Link für Event, Chat, Training, Bestellung und Vereinsabrechnung.
5. Opt-out und serverseitige Deaktivierung des Tokens.
6. Providerfehler erscheinen als `failed` und niemals als `sent`.

Ohne diese Geräteabnahme ist Push technisch implementiert, aber nicht für den öffentlichen Release freigegeben.

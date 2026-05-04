# Airmius Smoke-Check-Bericht

Stand: 04.05.2026

## Ergebnis

Die Plattform ist technisch grundsätzlich start- und baubar. Die vollständige automatisierte Testsuite läuft aktuell jedoch nicht fehlerfrei durch. Das bedeutet: Build und Routenstruktur sind in Ordnung, aber vor einer Beta-Freigabe sollten die fehlgeschlagenen Tests und die betroffenen Flows geprüft oder aktualisiert werden.

## Durchgeführte Prüfungen

- `npm run build`: erfolgreich.
- `php artisan about --only=environment`: erfolgreich.
- `php artisan route:list`: erfolgreich, 273 Routen erkannt.
- `php artisan test`: fehlgeschlagen.
- Handbuch-Generator `scripts/create_user_manual.php`: Syntaxprüfung erfolgreich.
- Testmatrix-Generator `scripts/create_use_case_test_matrix.php`: Syntaxprüfung erfolgreich.

## Wichtigste Hinweise aus den Tests

- Die Startseiten-Prüfung wirft im Test eine 500-Antwort, weil im Testkontext die Tabelle `settings` nicht gefunden wird.
- Einige Jetstream/Fortify-Standardtests passen nicht mehr zu den angepassten Airmius-Flows, zum Beispiel Login-Redirect auf `/feed` statt `/dashboard`.
- Mehrere Konto-, Passwort-, Registrierung- und Zwei-Faktor-Tests schlagen fehl und sollten entweder an die neue Airmius-Logik angepasst oder fachlich überprüft werden.
- API-Token- und E-Mail-Verifizierungs-Tests werden übersprungen, weil diese Features aktuell deaktiviert sind.

## Empfehlung für die Beta

- Erst die echten Blocker prüfen: Registrierung, Login, Profilvervollständigung, Elternzustimmung, Passwort-Reset, Konto löschen, Wartungsmodus.
- Danach die Testfälle an die neue Produktlogik anpassen, damit `php artisan test` wieder als verlässlicher Qualitätsindikator genutzt werden kann.
- Die erzeugte Testmatrix für manuelle Tests auf Smartphone, Tablet und Laptop verwenden.

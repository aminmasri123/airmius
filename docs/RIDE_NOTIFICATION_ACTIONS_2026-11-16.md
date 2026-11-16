# Mitfahranfragen aus Benachrichtigungen

## Ergänzung: Benachrichtigung nach dem Löschen

`ride.deleted` öffnet jetzt einen eigenen Absagebildschirm mit einer Schaltfläche zur Fahrtensuche. Die gelöschte Fahrt wird dabei nicht erneut geladen. Das gilt auch für bestehende Löschbenachrichtigungen mit der früheren Fahrt-URL und für den bisherigen Benachrichtigungsdetailbildschirm. Andere alte Fahrtlinks erklären HTTP 404 als fehlende Fahrt; HTTP 401/403, Netzwerkfehler und Serverfehler erhalten getrennte Meldungen.

Lokal geprüft: 19 Backend-Tests mit 506 Assertions und 16 Flutter-Tests bestanden, einschließlich Wechsel vom Absagebildschirm zur Fahrtensuche. Diese Ergänzung ist noch nicht veröffentlicht und benötigt ein App-Update. Die vorherigen drei Änderungen zum Laden und Bearbeiten von Anfragen wurden auf Hostinger eingespielt und dort mit zurückgerollten Testdaten geprüft.

## Ursache und Änderung

`ride.*`-Benachrichtigungen enthielten eine Fahrt-ID, hatten aber keinen passenden mobilen Kontextlink. Der allgemeine Fallback öffnete nur den Benachrichtigungstext.

Die Normalisierung liefert jetzt `airmius://rides/{id}`. Auch vorhandene Benachrichtigungen werden beim Lesen normalisiert. Die App repariert den bisherigen Fallback zusätzlich anhand von `ride_id` und unterstützt sowohl diesen Link als auch `/rides?ride={id}`. Der bisherige Benachrichtigungsdetailbildschirm öffnet bei Fahrtnachrichten ebenfalls die Fahrt.

Die neue Fahrtansicht lädt den aktuellen Zustand über `GET /api/v1/rides/{ride}`. Nur der Fahrer erhält offene Anfragen. Annehmen und Ablehnen aktualisieren die Ansicht aus der Serverantwort, ohne sie zu schließen. Bei voller Fahrt bleibt Ablehnen möglich. Fehlgeschlagene Aktionen zeigen die Servermeldung und behalten die Anfrage sichtbar; Aktualisieren lädt den aktuellen Stand erneut.

Die neue GET-Route verwendet die Sichtbarkeitsregeln der RidePolicy. Bestehende serverseitige Regeln für Entscheidungen und Sitzplatzkapazität bleiben wirksam.

## Lokale Prüfung

- PHP: MobileRideApiTest, RideFeatureTest, RideAddressTest — 18 Tests, 503 Assertions bestanden.
- Flutter: ride_notification_test, trainer_notification_routing_test, sport_matching_notification_test, ride_address_test — 10 Tests bestanden.
- Abgedeckt: bisheriger Benachrichtigungsbildschirm, historische Links, zwei Anfragen nacheinander annehmen/ablehnen, sofortiger neuer Zustand, Fehleranzeige, keine Entscheidungsbuttons für Mitfahrer, verbotene fremde Entscheidungen, private Fahrt und gelöschte Fahrt.

## Veröffentlichung und Gerätetest

Zuerst die Backend-Änderungen einschließlich neuer GET-Route bereitstellen, anschließend die aktualisierte App veröffentlichen. Es ist keine Datenbankmigration für diese Änderung erforderlich. Diese Änderung wurde lokal getestet und noch nicht auf dem Produktivserver oder einem echten Telefon geprüft.

Für den Gerätetest eine zukünftige Fahrt mit drei Plätzen einschließlich Fahrer anlegen. Zwei Testkonten stellen Anfragen. Als Fahrer die Benachrichtigung öffnen, eine Anfrage annehmen und die zweite ablehnen. Beide Entscheidungen müssen ohne erneutes Öffnen möglich sein; nach Aktualisieren bleiben die Zustände erhalten. Mit einem fremden Konto dürfen keine offenen Anfragen oder privaten Fahrtdaten sichtbar werden.

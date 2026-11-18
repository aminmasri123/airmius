# Mobile Lese-Endpunkte: Chat und Profil

## Beobachtung

Die bereitgestellten Produktionsprotokolle zeigen erfolgreiche Chatlisten mit 437–438 SQL-Abfragen und 8,16–8,99 Sekunden Gesamtzeit sowie ein Profil mit 504 Abfragen und 1,10 Sekunden Gesamtzeit. Die SQL-Ausführung beansprucht dabei nur 105–217 ms beziehungsweise 165 ms. Diese Logs allein erklären die übrige Zeit nicht vollständig.

## Änderung im lokalen Code

- Die Chatliste lädt Rollen, direkte Berechtigungen, die jeweils neuesten zwölf persönlichen Abzeichen und Punkte aller beteiligten Nutzer gebündelt. Unterschiedliche Model-Instanzen desselben Nutzers behalten ihre jeweiligen Gesprächs-Pivots.
- Die Punkteaggregation berücksichtigt weiterhin den Nutzertyp (Sportler/Trainer), persönliche und ältere Einträge ohne `owner_type`, sowie getrennte Tageswerte. Vereins- und Teampunktekonten werden nicht einbezogen.
- Die Gruppenrechte der Antwort verwenden die bereits geladenen Gesprächsmitgliedschaften. Die serverseitigen Regeln für Aktionen bleiben unverändert.
- Wiederholte Vereinsrechte und Avatar-Vorschaubildprüfungen werden ausschließlich während der Serialisierung einer Chatlisten- oder Profilantwort wiederverwendet. Der Cache wird per `finally` entfernt. Es gibt keinen konten- oder anfragenübergreifenden Berechtigungscache.

## Messung und Prüfung

Isolierte lokale SQLite-Testdaten, keine Produktionsmessung:

- Vorher: eine Unterhaltung 27, acht Unterhaltungen 131 SQL-Abfragen.
- Nachher: eine Unterhaltung 16, acht Unterhaltungen 15 SQL-Abfragen.
- Profil mit einem normalen Vereinsbeitritt: 67 SQL-Abfragen nach der Änderung.
- Fünf neue Tests prüfen Abfragegrenzen, identische Punktewerte, zwölf Abzeichen je Nutzer, Berechtigungsänderungen zwischen Anfragen sowie Gruppenrollen und Nachrichten-Sichtbarkeit.
- Zusammen mit dem mobilen Profilvertrag: 6 Tests / 55 Assertions bestanden.
- Breitere Prüfung von ChatSecurityTest, GamificationServiceTest, ClubProfileSplitPermissionsTest, ClubPermissionsTest und ClubPermissionDelegationTest zusammen mit den zuerst vier neuen Tests: 51 von 52 Tests bestanden. Der vorhandene Test `test_delete_only_member_permission_can_remove_a_member_but_cannot_edit_one` erwartet eine unmittelbar entfernte Vereinsmitgliedschaft. Er schlägt identisch mit der unveränderten ClubPermissions-Version aus HEAD fehl. Dieses separate Verhalten wurde nicht geändert.

## Bereitstellung

Noch nicht auf Hostinger veröffentlicht. Kein App-Update und keine Datenbankmigration nötig. Vor einer Veröffentlichung die aktuellen Serverdateien vergleichen und sichern; die neun folgenden Dateien gehören gemeinsam zur Änderung:

- app/Http/Controllers/Api/V1/ChatController.php
- app/Http/Controllers/Api/V1/MeController.php
- app/Http/Resources/Api/V1/ConversationResource.php
- app/Http/Resources/Api/V1/UserResource.php
- app/Models/User.php
- app/Services/ApiUserPayloadLoader.php
- app/Services/GamificationService.php
- app/Support/ClubPermissions.php
- app/Support/Performance/ResourceReadCache.php

Danach mit einem angemeldeten Testkonto Chat und Profil prüfen und die neuen Performance-Logs mit vergleichbaren Konten vergleichen. Eine feste Produktionslatenz wurde noch nicht nachgewiesen.

## Erneuter Mitfahrfehler

Am 18. November antwortete `GET /api/v1/rides/1` wieder mit HTTP 405. Auf Hostinger fehlten alle drei zuvor ergänzten Teile: GET-Route, `RideController::show` und `RidePolicy::view`; es gab keinen Routen-Cache. Die bereits freigegebene Korrektur wurde erneut mit Dateisicherungen unter `storage/app/private/ride-fix-20261118-192107` ergänzt. Anschließend antwortete die Route ohne Anmeldung korrekt mit HTTP 401.

Der isolierte Servertest bestätigte zwei offene Anfragen, Annahme, Ablehnung, Erhalt nach erneutem Laden sowie verweigerten Zugriff und Entscheidungen für einen fremden Nutzer. Alle erzeugten Nutzer und Fahrtdaten wurden in derselben Transaktion zurückgerollt; verbleibende Testnutzer: 0. Welcher Bereitstellungsschritt die drei Ergänzungen überschrieben hatte, wurde nicht nachgewiesen. Nach künftigen Bereitstellungen diesen Endpunkt erneut prüfen: HTTP 401 ohne Anmeldung ist erwartbar, HTTP 405 bedeutet eine fehlende GET-Route.

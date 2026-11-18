# Fahrgemeinschaften: Kostenvereinbarung

## Verhalten

- Web und App verlangen beim Anlegen die Auswahl **Kostenlos** oder **Kostenbeteiligung**. Bei Kostenbeteiligung sind ein positiver Betrag und eine Währung erforderlich.
- Der Betrag gilt pro Person für die angebotene Strecke. Die Oberfläche erläutert enthaltene vereinbarte Maut-/Fährkosten und die direkte Bezahlung beim Fahrer. Airmius verarbeitet keine Zahlung.
- Die vorgeschlagene Währung stammt aus dem **Kontoland des Fahrers**, nicht aus Sprache oder Fahrtziel. Für internationale Fahrten bleibt sie auswählbar. Ohne bekanntes Kontoland muss eine Währung gewählt werden. Es findet keine automatische Umrechnung statt.
- Währungskatalog: Unicode CLDR 49, 255 Länder-/Regionszuordnungen und 153 aktive Währungen, Datenstand 18.11.2026. Quelle und Stichtag stehen in `resources/data/ride-currencies.json`. Unterschiedliche Nachkommastellen werden berücksichtigt.
- Neue Texte sind auf Deutsch, Englisch, Französisch und Arabisch vorhanden. Web und Backend verwenden dieselbe Übersetzungsdatei; die App enthält die entsprechende Dart-Kopie.
- Kosten erscheinen in der Übersicht und in den Fahrtdetails. Vor der Mitfahranfrage muss der Nutzer die angezeigten Bedingungen ausdrücklich bestätigen.
- Der Server speichert Betrag, Währung und Preisversion je Mitfahrer. Eine Preisänderung überschreibt die bestehende Vereinbarung nicht und kennzeichnet offene sowie angenommene Anfragen zur erneuten Bestätigung. Bereits angenommene Plätze bleiben reserviert.
- Der Mitfahrer sieht alten und neuen Preis und kann zustimmen oder seine Teilnahme/Anfrage zurückziehen. Ohne Bestätigung kann der Fahrer eine noch offene Anfrage nicht freigeben. Veraltete Bestätigungen werden abgewiesen.
- Preisbestätigungen sind auf den eigenen aktiven Mitfahrer-Datensatz beschränkt und unterliegen zusätzlich den Sichtbarkeitsrechten der Fahrt. Der Fahrer erhält nach neuer Bestätigung eine Benachrichtigung.
- Bestandsfahrten ohne Preisangabe werden ausdrücklich als **nicht angegeben** angezeigt. Sie werden nicht rückwirkend als kostenlos eingestuft. Alte Clients können neu bepreiste Fahrten ohne ausdrückliche Bestätigung nicht anfragen.

## Lokale Prüfung

Erfolgreich am 18.11.2026:

- `RidePricingFlowTest`, `MobileRideApiTest`, `RideAddressTest`: **32 Tests, 513 Assertions**. API und Web-HTTP-Abläufe, Kontoland, internationale Beträge, Validierungsfehler, Preisversionen, Freigabe, Bestätigung, Austritt und fremder Zugriff.
- Flutter `ride_pricing_test.dart`, `ride_notification_test.dart`, `ride_address_test.dart`: **19 Tests**. Auf einem simulierten 390×844-Gerät unter anderem Zustimmung vor Anfrage, alte/neue Kosten, Bestätigung, Ablehnung, Pflichtauswahl und Währungssuche.
- Node `ridePricing.test.mjs`, `rideLocations.test.mjs`: **4 Tests**. Internationale Preisformatierung und Übersetzungen, alter/neuer Preis, Auswahlfelder sowie vorhandene Adresssuche.
- Vite-Produktionsbuild erfolgreich. Dart-Analyse ohne Fehler oder Warnungen; bestehende Stilhinweise in den großen Bildschirmdateien bleiben getrennt davon bestehen.

Die Tests verwenden eine lokale SQLite-Testdatenbank beziehungsweise simulierte API-Antworten. Eine Prüfung mit dem produktiven Hostinger-System oder einem installierten neuen Android-Build ist damit nicht behauptet.

## Veröffentlichung

1. Backend inklusive Migration `2026_11_18_000003_add_ride_cost_agreements.php`, Daten-/Sprachdateien und Routen gemeinsam veröffentlichen; vorher Datenbanksicherung erstellen.
2. Migration ausführen, Laravel-Konfigurations-/Routencache entsprechend dem bestehenden Deployment erneuern. Alle vom Vite-Build erzeugten Assets zusammen mit der Manifestdatei veröffentlichen.
3. Erst danach einen neuen App-Build mit dem aktualisierten Client veröffentlichen.
4. Mit zwei Testkonten auf dem Zielsystem eine bepreiste Fahrt anlegen, anfragen, freigeben, Preis ändern und erneut bestätigen beziehungsweise zurückziehen. Ein fremdes Konto darf keine Preisvereinbarung bestätigen.

Stand: lokal implementiert und geprüft; noch nicht auf Hostinger veröffentlicht und noch kein neuer Android-Release gebaut.

## Nachprüfung: leere Währungsauswahl

Die Auswahl war ausschließlich vom Katalog aus der Serverantwort abhängig. Bei einer älteren oder unvollständigen Antwort ohne `pricing_catalog.currencies` blieb sie leer. Web und App besitzen jetzt einen eingebundenen CLDR-Ersatzkatalog. Der Währungsvorschlag wird dann weiterhin aus dem Kontoland ermittelt; ein vorhandener Serverkatalog hat Vorrang. Der mobile Suchfilter zeigt beim Öffnen mit einer bereits ausgewählten Währung wieder alle Einträge an und akzeptiert auch kleingeschriebene Suchbegriffe.

Regressionstest: fehlender Serverkatalog, über 100 auswählbare Währungen, Kontoländer DE/MA/JP/KW, unbekanntes Kontoland und Wechsel von MAD zu JPY. **6 Flutter-Tests und 4 Web-Tests erfolgreich**, Web-Build erfolgreich. Diese Nachbesserung ist ebenfalls noch lokal.

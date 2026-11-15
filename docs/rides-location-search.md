# Fahrgemeinschaften: Adressvorschläge und Umkreis

Web und App verwenden den authentifizierten Endpunkt `/rides/locations` bzw.
`/api/v1/rides/locations`. Vorschläge starten ab drei Zeichen nach 450 ms.
Der Server begrenzt Aufrufe auf 30 pro Minute und speichert erfolgreiche
Ergebnisse zwölf Stunden zwischen. Schlüssel und Anbieterfehler werden nicht
an Clients weitergegeben. Bei Ausfällen bleibt die manuelle Adresseingabe möglich.

## Konfiguration und Auslieferung

- `php artisan migrate --force`
- `GRAPHHOPPER_API_KEY` wird standardmäßig auch für Geocoding verwendet.
  Alternativ `RIDES_GEOCODING_API_KEY` setzen.
- `RIDES_GEOCODING_URL` kann auf einen kompatiblen Geocoding-Dienst zeigen.
  Standard: `https://graphhopper.com/api/1/geocode`.
- Nach Änderungen an der Serverkonfiguration den Laravel-Konfigurationscache
  wie beim bestehenden Deployment erneuern.
- `php artisan rides:geocode-areas --limit=100` ergänzt kommende bestehende
  Fahrten. Für weitere Chargen den ausgegebenen Wert `--after-id=...` verwenden.
  Nicht auflösbare Orte bleiben in der Textsuche auffindbar.
- Den aktualisierten Webbuild und einen neuen App-Build ausliefern.

Ein selbst betriebener GraphHopper-Routingserver stellt nicht automatisch
Geocoding bereit. Deshalb sind Geocoding-URL und -Schlüssel unabhängig von
der Routingkonfiguration. Der gewählte Anbieter benötigt eine Freigabe für
Geocoding; seine Nutzungsgrenzen gelten weiterhin.

## Suchverhalten und Datenschutz

Ohne Umkreis gelten die bestehenden PLZ-/Stadtfilter. Für 5, 10 oder 25 km
muss pro ausgefülltem Suchfeld ein Ortsvorschlag gewählt werden. Eine Änderung
des Textes verwirft die vorige Ortsauswahl. Beide Enden werden mit UND verknüpft.

Die Berechnung verwendet Luftlinie (Haversine), keine Fahrstrecke. Gespeichert
werden ausschließlich die vom Geocoder aufgelösten öffentlichen Ortsmitten
von PLZ/Stadt/Land, unabhängig von der privaten Straße. Diese Koordinaten
werden nicht in Fahrtantworten ausgegeben. Straßen und Hausnummern bleiben
bis zur bestätigten Mitfahrt geschützt. Treffer ohne aufgelöste Ortsmitte
werden aus der Umkreissuche ausgeschlossen; Fahrer sehen dazu einen Hinweis.

Die Datenbank grenzt zunächst die geografische Breite ein, anschließend wird
die genaue Luftliniendistanz serverseitig geprüft. Bestehende Sichtbarkeitsregeln
gelten vor der räumlichen Filterung unverändert.

Anbieterschnittstelle: https://docs.graphhopper.com/openapi/geocoding

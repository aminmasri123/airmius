# GraphHopper später selbst hosten

Standard bleibt `SPORT_MAP_GRAPHHOPPER_MODE=cloud`. Cloud verwendet weiterhin
`SPORT_MAP_GRAPHHOPPER_BASE_URL` und `GRAPHHOPPER_API_KEY`.

Für einen vorbereiteten eigenen Server:

```dotenv
SPORT_MAP_ROUTING_PROVIDER=graphhopper
SPORT_MAP_ROUTE_GENERATOR_PROVIDER=graphhopper
SPORT_MAP_GRAPHHOPPER_MODE=self_hosted
SPORT_MAP_GRAPHHOPPER_SELF_HOSTED_URL=https://routing.example.com
SPORT_MAP_GRAPHHOPPER_SELF_HOSTED_TOKEN=
```

URL ohne `/route` angeben. Optionalen Bearer-Token nur verwenden, wenn der
Reverse-Proxy des eigenen Servers ihn prüft. GraphHopper richtet diese
Authentifizierung nicht selbst ein. Für produktiven Betrieb HTTPS verwenden.
Der Cloud-Schlüssel wird niemals im Self-Hosted-Modus mitgeschickt und der
Self-Hosted-Token niemals im Cloud-Modus. Weiterleitungen werden nicht verfolgt.

Der eigene Server muss die Profile aus `config/sport_map.php` bereitstellen:
`foot`, `hike`, `bike`, `mtb`. Bei anderen Profilnamen diese Zuordnung anpassen.
Rundkurse verwenden `algorithm=round_trip` und `ch.disable=true`; der Server
muss diese Berechnung unterstützen. Kartendaten für Europa und Marokko müssen
separat importiert und aktualisiert werden. Kartenkacheln bleiben unabhängig.

Nach Konfigurationsänderung `php artisan config:cache` ausführen und langlebige
Anwendungsprozesse gegebenenfalls neu starten. Zunächst auf einer Testumgebung
A-nach-B und Rundkurse in beiden Regionen samt Vorschau/Speichern testen.
Kapazität und Importbedarf vor dem Kauf eines Servers messen.

Rückwechsel: Modus auf `cloud` setzen, gültigen Cloud-Schlüssel sicherstellen
und Konfigurationscache erneuern. Die App-API bleibt unverändert; für diesen
Wechsel ist kein neues App-Build nötig. Es gibt keinen automatischen Wechsel
bei Fehlern und keine Speicherung einer Luftlinie als berechnete Strecke.

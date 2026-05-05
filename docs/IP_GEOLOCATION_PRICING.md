# IP-Geolocation fuer Abo-Preise

Airmius kann Preise und Waehrungen je Land anzeigen. Fuer eingeloggte Nutzer wird bevorzugt das gespeicherte Land genutzt. Fuer nicht eingeloggte Besucher wird das Land aus der IP abgeleitet.

## Reihenfolge der Erkennung

1. Gespeichertes Nutzerland, falls eingeloggt
2. CDN-/Hosting-Header:
   - `CF-IPCountry`
   - `CloudFront-Viewer-Country`
   - `X-Vercel-IP-Country`
   - `X-AppEngine-Country`
   - `X-Country-Code`
3. Optionaler GeoIP-API-Endpunkt aus `.env`
4. Browser-Sprache als grober Fallback
5. Fallback `DE`

## Empfohlen auf Hosting

Wenn Cloudflare genutzt wird, kommt `CF-IPCountry` automatisch mit. Dann ist keine externe API notwendig.

Ohne Cloudflare kann in `.env` ein GeoIP-Endpunkt gesetzt werden:

```env
GEOIP_API_URL=https://ipapi.co/{ip}/json/
```

Der Platzhalter `{ip}` wird automatisch durch die Besucher-IP ersetzt. Die API sollte JSON mit einem dieser Felder liefern:

- `country_code`
- `countryCode`
- `country`

## Laenderpreise verwalten

Im Adminbereich unter `Abos` kann pro Plan ein Laenderpreis gepflegt werden:

- Land: `DE`, `CH`, `MA`, `US`
- Waehrung: `EUR`, `CHF`, `MAD`, `USD`
- Monatsbetrag in Cent/Kleinsteinheit
- Jahresbetrag in Cent/Kleinsteinheit
- Aktiv/Inaktiv

Wenn fuer ein Land kein eigener Preis existiert, nutzt Airmius den Standardpreis des Plans.

Wenn ein Laenderpreis existiert, aber inaktiv ist, ist der Plan fuer dieses Land nicht verfuegbar.

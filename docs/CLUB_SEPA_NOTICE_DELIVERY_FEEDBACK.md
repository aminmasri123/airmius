# SEPA-Vorabinformationen: Zustell- und Bounce-Rückmeldungen

## Zweck und Statusgrenzen

Beim erfolgreichen Mailversand speichert Airmius die Transportannahme und Nachrichten-ID. Der davon getrennte Providerstatus beginnt mit `pending` und wird durch Postmark auf `delivered` oder `bounced` gesetzt. `delivered` ist eine Zustellmeldung des Mailanbieters, kein Lese- oder Empfangsnachweis der Person. Ein späterer Bounce hat Vorrang vor einer früheren Zustellmeldung.

Die offiziellen Pakete `symfony/postmark-mailer` und `symfony/http-client` stellen den Postmark-API-Transport bereit. Seine endgültige Provider-Nachrichten-ID wird für die Callback-Zuordnung gespeichert.

Der bestehende Lastschriftlauf bleibt nach vollständiger Transportannahme freigegeben. Eine spätere Provider-Rückmeldung ändert weder den Laufstatus noch bereits erzeugte Bankdateien. Verwaltungspersonen sehen den Providerstatus in Web und App und müssen Bounces fachlich bearbeiten. Abweichende Beitragszahler bleiben bis zu einer eigenen Zahlerverwaltung im bestätigten manuellen Versandweg.

## Postmark einrichten

1. Migrationen in der vorgesehenen Release-Pipeline ausführen.
2. Den Billing-Absender auf den Postmark-API-Transport und eine dauerhafte Worker-Queue stellen. Eine aktive Absenderüberschreibung in der Verwaltungsdatenbank muss ebenfalls `postmark` verwenden:

   ```dotenv
   MAIL_BILLING_MAILER=postmark
   QUEUE_CONNECTION=database
   ```

3. Einen dedizierten, zufälligen Benutzernamen und ein langes Passwort setzen:

   ```dotenv
   POSTMARK_API_KEY=...
   POSTMARK_WEBHOOK_USERNAME=...
   POSTMARK_WEBHOOK_PASSWORD=...
   POSTMARK_WEBHOOK_IPS=
   ```

4. In Postmark getrennte Delivery- und Bounce-Webhooks auf `https://<host>/webhooks/mail/postmark` konfigurieren und dieselben HTTP-Basic-Zugangsdaten hinterlegen.
5. `POSTMARK_WEBHOOK_IPS` nur mit einer geprüften, kommagetrennten Allowlist befüllen. Bei Reverse Proxies muss Laravel die richtige Proxykette kennen, bevor die Client-IP als Schutz verwendet wird.
6. Konfiguration cachen beziehungsweise Worker neu starten, ohne Zugangsdaten in Logs oder Nachweisen abzulegen.

Der Endpunkt antwortet bei gültigen, aber unbekannten Nachrichten oder abweichenden Empfängern absichtlich nur mit `ignored`. Ereignisse werden anhand stabiler, gehashter Merkmale dedupliziert. Gespeichert werden Ereignistyp, Zeit, Bounce-Klasse und technische Klassifikation; E-Mail-Adresse, Providerbeschreibung und der vollständige Webhook-Body werden nicht in der Ereignisspur abgelegt.

## Produktive Abnahme

T007d3 darf erst vollständig abgehakt werden, wenn die folgenden Nachweise in einer releasegleichen Umgebung erbracht sind:

- getrennte Delivery- und Bounce-Webhooks sind beim verwendeten Postmark-Server registriert und zeigen auf den vorgesehenen Endpunkt;
- dauerhafter Queue-Worker verarbeitet genau eine freigegebene Testnachricht;
- die gespeicherte Nachrichten-ID stimmt mit Postmark überein;
- Delivery-Webhook setzt `provider_status=delivered` und eine Wiederholung erzeugt kein zweites Ereignis;
- eine kontrollierte Bounce-Adresse setzt `provider_status=bounced` und zeigt die Bounce-Klasse in Web und App;
- falsche Basic-Auth und, falls aktiv, eine nicht erlaubte IP werden abgewiesen;
- bei aktiver IP-Allowlist ist die produktive Trusted-Proxy-Kette geprüft, sodass weder Proxy-IP noch ungeprüfte Weiterleitungsheader als Client-IP gelten;
- Queue-Neustart und wiederholte Providerzustellung versenden keine zweite Nachricht;
- die zuständige Person bestätigt den Umgang mit Bounces und dem manuellen Weg für abweichende Beitragszahler.

Nachweise dürfen nur nicht-sensitive Referenzen enthalten. Keine Zugangsdaten, E-Mail-Adressen, Webhook-Bodies oder Nachrichteninhalte ablegen.

## Go/No-Go-Prüfer

Die Vorlage `resources/release/club_sepa_notice_delivery_evidence.template.json` wird außerhalb des Repositorys kopiert und nach der freigegebenen Staging-Abnahme ausgefüllt. Zulässig sind nur kurze interne Nachweiskennungen wie `SEPA-MAIL-QA-2026-09-25`; URLs, Pfade, Zugangsdaten, Empfänger, Nachrichten-IDs, UUID-/lange Hex-Providerkennungen und personenbezogene Daten gehören nicht in die Datei.

Der Prüfer verändert keine Daten und versendet keine E-Mail:

```bash
php artisan airmius:audit-club-sepa-notice-delivery --with-runtime --evidence=/geschuetzter/pfad/evidence.json --strict
```

Für eine maschinenlesbare Pipeline-Ausgabe kommt `--json` hinzu. Ohne `--with-runtime` wird nur der Repository-Vertrag geprüft und die Entscheidung bleibt `no-go`. Mit `--with-runtime` werden der tatsächlich wirksame Billing-Mailer, verdeckt vorhandene Postmark-/Webhook-Zugangsdaten, die Queue-Verbindung, erforderliche Tabellen und aggregierte Providerzustände geprüft. Historische Queue-Fehler und ein konfigurierter Queue-Treiber beweisen keinen laufenden Worker; deshalb verlangt der Evidenzvertrag weiterhin die reale Testzustellung, einen kontrollierten Bounce und den Worker-Neustart.

Ein `go` bestätigt ausschließlich, dass automatisierte Prüfungen und die bezeichneten manuellen Nachweise vollständig sind. `delivered` bleibt eine Provider-Zustellmeldung und kein Lese- oder persönlicher Empfangsnachweis.

Der Vertrag `club-sepa-notice-delivery-rollout.v2` verlangt deshalb eigene Nachweise für die Provider-Webhookregistrierung und die Proxy-/IP-Behandlung. Ein älterer v1-Nachweis bleibt absichtlich `no-go`; der Prüfer wertet weder eine vorhandene API-Taste noch eine konfigurierte Allowlist als Beleg für diese externen Zustände.

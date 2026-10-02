# Abschlussnachweise Editor, Listen und Integrationen

Stand: 02.10.2026. Dieser Bericht unterscheidet Implementierung, kontrollierte Integration und reale externe Abnahme. Es wurde weder gepusht noch eine App veröffentlicht.

## Kontrolliert ausgeführte Integrationen

- [x] Laravel-SMTP über echten Loopback-TCP: Annahme, Empfängerablehnung und die tatsächliche mobile Verifizierungsbenachrichtigung mit signiertem Link. 3 Tests / 17 Assertions. Der Empfänger bindet ausschließlich an `127.0.0.1`, leitet nichts weiter und akzeptiert ausschließlich synthetische `example.test`-Adressen. Kein externer E-Mail-Provider wurde dadurch abgenommen.
- [x] Bankdatei-/SEPA-, Webhook-, Push- und Upload-Verträge: 103 Tests / 1314 Assertions mit isolierten Testdaten. Providerantworten sind in diesen Tests kontrolliert; keine Bankeinreichung, keine echte Belastung, keine externe Push-Zustellung.
- [x] Versionsbindung von Gerätevorlagen, Validator, Registry und ausstehenden Manifest-Gates auf die Quellversion `1.0.71+157` korrigiert. Bestehende historische Releaseartefakte bleiben unverändert. Kein ausstehender Gate wurde als bestanden umgewertet. Diese Quellversion ist keine Aussage über den zuletzt veröffentlichten Store-Build.
- [x] Cross-Device- und Evidence-Integrity-Verträge: 15 Tests / 233 Assertions erfolgreich.
- [x] `assert_release_version_consistency.sh` erfolgreich.

Ausführbare Befehle:

```bash
RUN_LOCAL_SMTP_TESTS=1 php artisan test --compact --filter=LocalSmtpTransportIntegrationTest
php artisan test --compact --filter='ClubMembershipBankReconciliationServiceTest|ClubMembershipSepaServiceTest|ClubSepaBatchTest|ClubSepaSettlementTest|PaymentWebhookSignatureTest|ProviderWebhookAndBankIdempotencyTest|MobilePushDeliveryServiceTest|UploadValidationTest'
php artisan test --compact --filter='CrossDeviceReadinessTest|MobileReleaseEvidenceIntegrityContractTest'
bash mobile/airmius_mobile/scripts/assert_release_version_consistency.sh
php artisan airmius:audit-cross-device --json --strict
```

Der letzte Befehl bleibt absichtlich `no_go`: 5 automatische Prüfungen bestanden, 5 externe Nachweise ausstehend. Ohne `RUN_LOCAL_SMTP_TESTS=1` werden die drei socketbasierten SMTP-Tests ausdrücklich übersprungen, nicht als bestanden gemeldet.

## Reale Abnahme: tatsächlich festgestellte Grenzen

- [ ] Android: `adb devices -l` ausgeführt, kein verbundenes Gerät. Der bestehende Android-Smoke-Lauf im Modus `--preflight-only` ist mit Fehlerstatus abgebrochen. Datenschutzarmes Vorprüfungsprotokoll: `/tmp/airmius-real-device-probe-2026-10-02/prerequisite-check.log`. Kein Gerätetest, keine Installation und kein Screenshot als Erfolg behauptet.
- [ ] iOS: kein iOS-Testzugang bereitgestellt; keine macOS-/TestFlight-Abnahme in dieser Linux-Umgebung durchgeführt.
- [ ] Push: benötigt angemeldetes Testkonto und registriertes Gerät; Vordergrund/Hintergrund, Zielnavigation und Logout-Invalidierung wirklich prüfen.
- [ ] E-Mail-Provider: freigegebener Testempfänger und Testumgebung fehlen; SMTP-Loopback ersetzt keine externe Zustellung, DNS-/TLS-/Spamprüfung.
- [ ] Kamera, GPS und OS-Dateiauswahl: physisches Gerät und Erlauben/Ablehnen/Widerrufen/Abbruch-Prüfung fehlen. Widgettests sind kein Gerätenachweis.
- [ ] Zahlungen: autorisierte Provider-Sandbox und Testkonto fehlen. Kein Produktionscheckout und keine Belastung ausgelöst.
- [ ] Bank: keine freigegebene echte/anonymisierte Bankdatei bzw. Bank-Testumgebung bereitgestellt. Automatisierte synthetische Import-/Exporttests ersetzen keine fachliche Bankabnahme.
- [ ] Store-Update: kein neuer Build hochgeladen, installiert oder veröffentlicht. Update über eine bestehende Installation sowie Sitzungs-/Datenbestandserhalt müssen am freigegebenen Testrelease nachgewiesen werden.

Der Nutzer wurde nach Testumgebung, Testempfängern und USB-Debugging-Gerät gefragt. Eine fehlende Antwort wird nicht als Freigabe von produktiven Integrationsaktionen behandelt.

## Übergreifende Release-Grenzen

Die zwölf bisherigen Widgetfehler wurden untersucht: überwiegend veraltete API-Fixtures/Selektoren sowie ein echter fehlender Material-Container im Rechnungsdialog. Die Berechtigungsprüfungen wurden nicht aufgeweicht; versehentlich global berechtigte Vereins-Testkonten wurden an den tatsächlichen Rollenvertrag angepasst. Im isolierten Vergleich bestehen 298/298 Widgettests (`/tmp/airmius-sidecar-isolated-widget-tests.log`). Dieser Vergleich ersetzte die zwei damals noch in Bearbeitung befindlichen Adminscreens durch deren bisherigen Stand; er ersetzt deshalb nicht den abschließenden gemeinsamen Lauf.

Der vollständige Release-Preflight meldete zusätzlich vier nicht übersetzte Attribute in `CountrySelect.vue` und einen nicht im Rollout-Register geführten Endpoint `api.v1.admin.operations`. Diese Befunde werden nicht durch lokale SMTP- oder Widgettests aufgehoben. Unabhängige Legal-, Accessibility-, Penetrationstest- und Pilotfreigaben bleiben in ihren bestehenden Gates offen.

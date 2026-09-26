# Airmius – vollständige Ausbau-Checkliste

Stand: 26.09.2026. Grundlage: die vollständige vom Nutzer bereitgestellte Liste mit 36 Funktionsbereichen.

## Arbeitsregeln und Status

- `[ ]` bedeutet offen oder noch nicht vollständig nachgewiesen, nicht automatisch „Code fehlt“.
- `[x]` wird erst nach Umsetzung und passender Prüfung gesetzt. Vorhandene Funktionen werden nicht ungeprüft als fertig markiert.
- Jede Umsetzung umfasst Datenmodell, Berechtigungen, Backend, betroffene Web-/App-Oberflächen und Regressionstests.
- Bestehende lokale Änderungen erhalten; keine produktiven Datenmigrationen oder automatischen Reparaturen ohne gesondert geprüften Ablauf.
- Testdatenbank: SQLite `:memory:` aus phpunit.xml; keine Tests gegen produktive Mitgliederdaten.
- Fachliche Freigaben und externe Integrationen separat nachweisen. Eine Demo-Oberfläche ist kein fertiger Prozess.
- Vollständiger Scope bleibt bestehen, auch wenn einzelne Arbeitsschritte separat abgeschlossen werden.

## Umsetzungspakete (Reihenfolge)

- [ ] P01 Bestand, sichere Testumgebung und vollständige Abnahmematrix (alle Bereiche)
- [ ] P02 Zahlungsprozesse: Teil-/Überzahlungen, Korrekturen, SEPA-Läufe und Mahnwesen (17–19)
- [ ] P03 Vereinsstruktur, Rollen, Vertretungen und Freigaben (1–3)
- [ ] P04 Mitglieder, Familien, Einwilligungen und Portal (4–7)
- [ ] P05 Kalender, Ressourcen, Inventar, Beschaffung und Fahrzeuge (8–9, 15–16)
- [ ] P06 Personal, Lizenzen, Ehrenamt und Arbeitsdienste (13–14)
- [ ] P07 Training, Wettkämpfe, Kurse, Camps und Reisen (10–12, 26)
- [ ] P08 Buchhaltung, Budgets, Fördermittel, Sponsoren und Spenden (19–21)
- [ ] P09 Dokumente, Verträge, Sitzungen, Wahlen und Beschlüsse (24–25)
- [ ] P10 Kommunikation, öffentliche Inhalte, Vereinskleidung und Gastronomie (22–23, 27–28)
- [ ] P11 Aufgaben, Formulare, Automatisierung, Berichte und Schnittstellen (30–32)
- [ ] P12 Schutzkonzept, Datenschutz, Betrieb, KI, Mehrvereinsplattform und Pilotabnahme (29, 33–36)

## Laufende technische Arbeitsschritte

- [x] T001 Arbeitsverzeichnis und bestehende Änderungen erfassen; Zahlungs-/Beitrags-Baseline: 12 Tests, 184 Assertions erfolgreich.
- [x] T002 Vollständige Anforderungsliste als persistente Checkliste anlegen.
- [x] T003 Gemeinsame Zahlungslogik: Restbetrag, Teilzahlung, Abschlusszahlung, Überzahlung und Zahlungskorrektur.
- [x] T004 Web/API, Übersichten, Bankabgleich und SEPA auf Restbeträge abstimmen.
- [x] T005 Web-/App-Anzeige und Zahlungsdialog für Teilzahlungen ergänzen.
- [x] T006 Regressionen für Zahlungsabläufe, Rollen, Bankabgleich, SEPA und bisherige Vollzahlungen prüfen.
- [ ] T007 Dauerhafte Lastschriftläufe mit Freigabe, Duplikatschutz, konfigurierbarer Vorabinformation und Rücklastschriften.
- [x] T007a Lastschriftläufe und eingefrorene Positionen speichern; Rechnungen reservieren; Betrags-/Mandats-/Kontoveränderungen vor Freigabe und Export prüfen.
- [x] T007b Freigabe durch zweite berechtigte Person, dokumentierten externen Vorabversand und unveränderlichen XML-Wiederdownload über API, Web und App implementieren; gezielte Backend-/App-Tests bestanden.
- [x] T007c App-Lastschriftaktionen nicht offline speichern und nach unklaren Transportfehlern nicht automatisch wiederholen; lesende Finanzrechte auch in den neuen Oberflächen berücksichtigen.
- [ ] T007d Vorabinformationen einschließlich produktiver Versand-/Zustellnachweise vollständig abnehmen.
- [x] T007d1 Lokalisierte Vorabinformationen aus eingefrorenen Positionen erzeugen; verschlüsselte Vorschau, Empfängerprüfung, ausdrückliche Versandfreigabe und dauerhafte Queue-Zustände in API, Web und App integrieren.
- [x] T007d2 Pro Nachricht genau einen Transportversuch beanspruchen; doppelte Jobs, Teilversand, unklare Fehler, geänderte Daten, Fristablauf und entzogene Rechte prüfen. Transportannahme mit Nachrichten-ID speichern; erst nach vollständiger Übergabe Export freigeben.
- [ ] T007d3 Produktiven Mailanbieter und Queue-Betrieb abnehmen; Provider-Zustell-/Bounce-Rückmeldungen integrieren und testen. Transportannahme ist kein Empfangsnachweis. Abweichende Beitragszahler müssen bis zur eigenen Zahlerverwaltung über den geprüften manuellen Versandweg erreicht werden.
- [x] T007d3a Postmark-Delivery-/Bounce-Webhooks mit HTTP-Basic-Schutz, optionaler IP-Allowlist, dauerhafter Deduplizierung, datensparsamer Ereignisspur sowie getrennten Zuständen in API, Web und App implementieren und automatisiert prüfen.
- [ ] T007d3b Releasegleichen Postmark- und Queue-Betrieb mit echter Testzustellung, kontrolliertem Bounce, Worker-Neustart und verantwortlicher fachlicher Abnahme belegen; keine produktiven Zugangsdaten oder Empfängerdaten in Evidenz speichern.
- [x] T007d3b1 Strikt lesenden Go/No-Go-Prüfer und versionierten, datensparsamen Evidenzvertrag für Postmark-Konfiguration, Queue-Betrieb, Zustellung, Bounce, Browser und Realgeräte bereitstellen.
- [x] T007d3b1a Evidenzvertrag auf v2 verschärfen: Provider-Webhookregistrierung und Trusted-Proxy-/IP-Behandlung müssen ausdrücklich belegt werden; ältere oder unvollständige Evidenz bleibt No-Go.
- [x] T007d3b1b Evidenzreferenzen im SEPA-Notice-Go/No-Go-Prüfer gegen Provider-ID-Nutzung weiter härten; UUID-/lange Hex-Providerkennungen bleiben trotz vollständig wirkender Staging-Datei No-Go. Gezielte Readiness-Regression bestanden.
- [ ] T007d3b2 Prüfer in der freigegebenen Staging-Umgebung ausführen, echte Zustellung und kontrollierten Bounce über den dauerhaften Worker belegen und fachlich abnehmen; erst danach T007d3b abhaken.
- [ ] T007e Rücklastschriften, Rückgabegründe, kontrollierte Wiederholungen und Abschluss der reservierten Positionen implementieren.
- [x] T007e1 Manuell belegte Bankgutschriften und Rückgaben pro Laufposition über API, Web und App implementieren; vorhandene Bankzahlungen ohne Doppelbuchung verknüpfen. Technische Prüfungen bestanden; reale Bank-/Browser-/Geräteabnahme bleibt unter T007f offen.
- [x] T007e2 Rückgabe mit eigenem Datum, Grund und Referenz historisieren; nur die zugehörige Zahlung zurücknehmen; Wiederholung durch zweite Person freigeben und Jahreswechsel im Finanzüberblick/CSV prüfen.
- [ ] T007e3 Negative Bankumsätze/Rückgabedateien kontrolliert importieren und mit Laufpositionen abgleichen; Gebührenbuchung und gegebenenfalls Weiterbelastung als eigene fachliche Prozesse ergänzen.
- [x] T007e3a Backend-Import für ein festes Rückgabe-CSV-Format mit verpflichtender Vorschau, Datei-/Person-/Laufbindung, Ablaufzeit, striktem Positionsabgleich und Schutz anderer Teilzahlungen implementieren und gezielt testen.
- [x] T007e3a1 Mehrpositionsimport mit echtem Rückrollen bereits ausgeführter Buchungen bei späterem Fehler prüfen; doppelte Wiederholung ohne neue Zahlung/Audit-Einträge sowie Vereinszuordnung auch bei manueller Rückgabe absichern.
- [ ] T007e3b Web-Import im Browser und App-Import auf realen Geräten abnehmen. Beide Oberflächen implementiert; automatisierte Prüfungen ersetzen diese Abnahmen nicht.
- [x] T007e3b1 Native Flutter-Importoberfläche mit Dateiauswahl, zeilenweiser Vorschau und beiden Bestätigungen ergänzen. Multipart-Übertragung und Erfolgs-/Fehlerabläufe automatisiert geprüft; keine Offline-Warteschlange oder automatische Wiederholung.
- [ ] T007e3c Weitere Bankformate/Feldzuordnung, Gebührenbuchungen und Weiterbelastungen ergänzen; reale Rückgabedateien und MySQL-Konkurrenzverhalten prüfen.
- [x] T007e3c1 Explizite CSV-Spaltenzuordnung in API, Web und App ergänzen; ausgelassene Spalten bestätigen lassen und Zuordnung unveränderlich an die Importvorschau binden. Standarddateien bleiben ohne Zuordnung nutzbar.
- [x] T007e3c1a Strikten ISO-20022-Import für abgewiesene `pain.002`-Transaktionen und ausdrückliche Rückgaben in `camt.053/054` ergänzen; Inhalts-/Namensraumerkennung, DTD-/Entity-Sperre, Vorschau-/Dateibindung und Web-/App-Dateiauswahl automatisiert prüfen. Reale Bankdateien und bankspezifische Profile bleiben offen.
- [x] T007e3c2a Gebühren-Backend/API: Bankausgabe ausdrücklich erstellen oder vorhandene Ausgabe verknüpfen, identische Wiederholung erkennen und bestehende Beitragsforderung unverändert lassen; gezielte Regression bestanden.
- [ ] T007e3c2b Gebührenbuchung mit Auswahl vorhandener Ausgaben in Web und App anbieten und Bedienung prüfen.
- [x] T007e3c2b1 Web-Gebührendialog und paginierte Suche vorhandener Bankausgaben implementieren; API-Isolation, Filter und Seitennavigation testen. Browserabnahme bleibt offen.
- [x] T007e3c2b2 Native Gebührenoberfläche mit Suche, Seitennavigation, neuer Ausgabe, bestätigter Verknüpfung und lesender Anzeige ergänzen; gezielte App-Tests bestanden. Reale Geräte-/Browserabnahme bleibt offen.
- [ ] T007e3c2c Nachvollziehbare Gebührenkorrekturen, Buchhaltungsexporte und fachlich freigegebene Weiterbelastungen an Mitglieder ergänzen.
- [x] T007e3c2c1 Ausdrücklich gebuchte Rückgabegebühren im bestehenden CSV-Zahlungsexport berücksichtigen; eigenes Gebührenkonto in Web/App konfigurieren, Buchungsdatum und Gebühren-only-Zeiträume prüfen. Tatsächlicher Buchhaltungsimport und fachliche Kontenzuordnung bleiben offen.
- [ ] T007e3c2c2 Gebühren mit eigener Korrekturhistorie berichtigen beziehungsweise stornieren; Auswirkungen auf Finanzübersicht und Export gemeinsam prüfen.
- [x] T007e3c2c2a Gebührenkorrekturen als Backend/API mit fortgeschriebener Historie, Differenzbuchungen, Wiederholungsschutz und periodengerechtem CSV implementieren; gezielte Verhaltenstests bestanden.
- [ ] T007e3c2c2b Web-/App-Korrekturdialoge mit aktuellem Gesamtbetrag, Historie, Bestätigung und sicherem Verhalten bei unklarer Übertragung ergänzen und abnehmen.
- [x] T007e3c2c2b1 Web-Korrekturformular mit aktuellem Gesamtbetrag, lesender Historie, Storno auf null und eingefrorener Wiederholungsanfrage implementieren; Zustands- und gerenderte Komponentenprüfungen bestanden. Manuelle Browserabnahme bleibt offen.
- [x] T007e3c2c2b2 Native Korrekturoberfläche mit aktuellem Gesamtbetrag, lesender Historie, ausdrücklicher Bestätigung und eingefrorener Anfrage implementieren; gezielte Bedienungs-/Navigationstests bestanden. Reale Geräteabnahme bleibt offen.
- [ ] T007e3c2c3 Weiterbelastung an Mitglieder als gesonderten, fachlich freigegebenen Forderungsprozess umsetzen und abnehmen.
- [x] T007e3c2c3a Weiterbelastung als gesonderten Backend-Entwurf mit Gebühren-Snapshot, Begründung, UUID-/Reservierungsschutz und nachvollziehbarer Rücknahme implementieren; gezielte Tests bestanden. Keine Forderungserstellung oder fachliche Freigabe.
- [ ] T007e3c2c3b Vier-Augen-Freigabe mit erneuter Zustandsprüfung und genau einer gesonderten, kontrolliert korrigierbaren Rechnung samt Buchhaltungszuordnung umsetzen.
- [x] T007e3c2c3b1 Vier-Augen-Freigabe mit erneuter Gebühren-/Empfänger-/Fristprüfung und genau einer geschützten gesonderten Rechnung implementieren; Konto für Zahlungsexport bei Freigabe festhalten. Gezielte Tests bestanden.
- [ ] T007e3c2c3b2 Kontrollierte Storno-/Gutschrift-/Erstattungsabläufe für freigegebene Weiterbelastungen sowie Prüfung nach späteren Gebührenkorrekturen vervollständigen.
- [x] T007e3c2c3b2a Vier-Augen-Storno für offene Gebührenrechnungen ohne Zahlungs-/Bankvorgänge als Backend/API ergänzen; Antrags-/Rücknahmehistorie, erneute Prüfung bei Freigabe, unveränderte Rechnungsdaten und Schutz vor Wiederöffnung getestet.
- [ ] T007e3c2c3b2b Gutschrift-/Erstattungsabläufe bei Zahlungs-/Bankvorgängen, gesonderte Korrekturdokumente und fachliche Abnahme ergänzen.
- [x] T007e3c2c3b2b1 Additiven Backend-/API-Ablauf für Gutschriftantrag, Vier-Augen-Freigabe, unveränderliche Zahlungsbelege, gesonderte Gutschriftnummer/PDF und dokumentierte Erstattung als neu erstellte oder vorhandene Bankausgabe implementieren; idempotente Wiederholung und Transaktionsrollback geprüft.
- [x] T007e3c2c3b2b2 Web-/App-Oberflächen für Gutschrift, Fremdfreigabe, Dokumentabruf, offenen Erstattungsbedarf und Erstattungsnachweis implementieren; Rollen-, Vier-Augen-, Wiederholungs- und Dokumentlinkverhalten automatisiert geprüft. Manuelle Browser-/Realgeräteabnahme bleibt Teil von T007e3c2c3b2b3/T007f.
- [ ] T007e3c2c3b2b3 Reale Buchhaltungs-/Bankabnahme, rechtlich-fachliche Prüfung des Gutschriftdokuments, MySQL-Konkurrenztest und kontrollierte Einführung durchführen.
- [ ] T007e3c2c3c Web-/App-Oberflächen für Vorschlag, Rücknahme, Freigabe und Forderungsstatus ergänzen und abnehmen.
- [x] T007e3c2c3c1 Web-Oberfläche für Vorschlag, Rücknahme, Vier-Augen-Freigabe, Forderungsstatus und Stornoanträge implementieren; eingefrorene Wiederholungsanfragen, Rollen-/Schemaanzeige und DE/EN/FR/AR automatisiert geprüft. Manuelle Browserabnahme bleibt offen.
- [x] T007e3c2c3c2 Native App-Oberfläche für Vorschlag, Rücknahme, Vier-Augen-Freigabe, Forderungsstatus und Stornoanträge implementieren; sichere Wiederholung, Rollen-/Schemafreigaben, DE/EN/FR/AR-Lesemodus und Navigation automatisiert geprüft. Reale Geräteabnahme bleibt offen.
- [ ] T007f MySQL-Konkurrenztests, vollständige Browser-/Realgeräteabnahme, Migrationsprobe mit Bestandsdaten und kontrollierte Einführung abschließen; alte direkte SEPA-Exporte vor Umstieg mit dem Bankstatus abgleichen.
- [x] T008 Historische möglicherweise fälschlich bezahlte Rechnungen analysierbar machen; ausschließlich lesende Diagnose, keine automatische Datenkorrektur. Produktive Einzelfallprüfung und fachliche Korrekturen sind damit nicht erledigt.
- [x] T009 Geschützte Vereinsregister-, Verbands- und Steuerstammdaten additiv in Datenmodell, Berechtigungen, Audit, Web und App ergänzen; Nichtoffenlegung und Validierung automatisiert prüfen. Browser-/Realgeräteabnahme, Bestandsmigration und fachliche Steuerprüfung bleiben als Einführungsschritte offen.
- [x] T010 Allgemeine Vereinskontakte und bis zu 20 zuständige Ansprechpartner mit standardmäßig privater Sichtbarkeit, einzelner Veröffentlichungsfreigabe, Audit sowie Web-/App-Oberflächen ergänzen; vorhandene Anschrift und Bankverbindung unverändert weiterverwenden.
- [x] T011 Vereinslogo, öffentliche Farbpalette, geschütztes Briefpapier und bis zu 20 Dokumentvorlagen mit typspezifischer Standardauswahl, Audit sowie Web-/App-Oberflächen ergänzen; bestehende Bilder und Dokumentabläufe unverändert weiterverwenden.
- [ ] T012 Mehrstufige Vereinsorganisation mit Abteilungen, Sportarten, Standorten, Mannschaften und Trainingsgruppen vervollständigen.
- [x] T012a Normalisierte Abteilungs-, Standort- und Trainingsgruppenstruktur mit Vereinsgrenzen, Löschschutz, Audit und sicherer Zuordnung bestehender Mannschaften ergänzen.
- [x] T012b Web-Verwaltung und öffentliche beziehungsweise interne Darstellung der Organisationsstruktur einschließlich Mannschaftszuordnung umsetzen.
- [x] T012c Native App-Verwaltung und Darstellung mit denselben Berechtigungen und DE/EN/FR/AR ergänzen.
- [ ] T012d Bestandsmigration, Rollen-/Mandantentrennung, Browser-/Realgeräteprüfung und vollständige Regression abnehmen; erst danach die Gesamtanforderung abhaken.
- [x] T012d1 Gemeinsamen, strikt lesenden Bestandsprüfer und versionierten Evidenzvertrag für Organisation, Mannschaftszuordnung, Vereinsgrenzen, Browser und Realgeräte bereitstellen.
- [ ] T012d2 Prüfer und additive Migration auf einer freigegebenen Staging-Kopie ausführen, Web/Android/iOS real abnehmen und die kontrollierte Einführung dokumentieren.
- [ ] T013 Vorstand, Ausschüsse, Arbeitsgruppen und Verantwortlichkeiten vollständig abbilden.
- [x] T013a Additive Governance-Tabellen und API mit Vereinsgrenzen, Sichtbarkeit, Amtszeiten, Löschschutz, Berechtigungen und Audit ergänzen.
- [x] T013b Web-Verwaltung und öffentliche beziehungsweise interne Darstellung der Gremien und Verantwortlichkeiten umsetzen.
- [x] T013c Native App-Verwaltung und Darstellung mit denselben Berechtigungen und DE/EN/FR/AR ergänzen.
- [ ] T013d Bestandsmigration, Browser-/Realgeräteprüfung und kontrollierte Einführung abnehmen; erst danach die Gesamtanforderung abhaken.
- [x] T013d1 Gemeinsamen, strikt lesenden Bestandsprüfer und versionierten Evidenzvertrag für Gremien, Verantwortlichkeiten, Vereinsgrenzen, Browser und Realgeräte bereitstellen.
- [ ] T013d2 Prüfer und additive Migration auf einer freigegebenen Staging-Kopie ausführen, Web/Android/iOS real abnehmen und die kontrollierte Einführung dokumentieren.
- [ ] T014 Geschäfts-, Beitrags- und Sportjahre unabhängig definieren und fachlich anbinden.
- [x] T014a Additive Vereinsjahre mit getrennten Typen, überschneidungsfreien Zeiträumen je Typ, Vereinsgrenzen, Rollen und Audit als Backend/API ergänzen.
- [x] T014b Web-Verwaltung und lesende Darstellung für Vereinsmitglieder umsetzen.
- [x] T014c Native App-Verwaltung und Darstellung mit DE/EN/FR/AR ergänzen.
- [x] T014d Vereinsjahre kontrolliert an Beiträge, Buchhaltung, Berichte und Sportplanung anbinden, ohne historische Vorgänge umzudeuten.
- [x] T014d1 Neue Beitragsrechnungen, Rechnungen, Bankvorgänge, manuelle Buchungen und Sporttermine beim Anlegen nicht rückwirkend passenden Vereinsjahren zuordnen; Zuordnung einfrieren und verwendete Zeiträume gegen Löschen schützen.
- [x] T014d2 Periodenfilter und Periodenbezug in Finanz-, Beitrags- und Vereinsberichten ergänzen; unzugeordnete historische Vorgänge ausdrücklich getrennt ausweisen.
- [x] T014d3 Periodenzuordnung in relevanten Web-/App-Detailansichten und Exporten sichtbar und fachlich nachvollziehbar machen.
- [x] T014d4 Sportjahre in Saison- und Mannschaftsplanung verwenden und die gesamte fachliche Periodenlogik abnehmen.
- [ ] T014e Bestandsmigration, fachliche Periodenprüfung, Browser-/Realgeräteabnahme und kontrollierte Einführung abschließen.
- [x] T014e1 Datenschutzarmen Read-only-Bestandsaudit, versionierten Evidenzvertrag und Rollout-/Rollback-Anleitung ohne automatische historische Zuordnung bereitstellen.
- [ ] T014e2 Backup/Wiederherstellung, additive Migration und Bestandsaudit auf einer freigegebenen Staging-Kopie sowie Web-Mobile-/Web-Desktop-Abnahme dokumentieren.
- [ ] T014e3 Android-/iOS-Realgeräteabnahme, Produkt-/Engineering-Freigabe und kontrollierte Einführung mit strengem Go/No-Go abschließen.
- [ ] T015 Eigene Datenfelder, Kategorien und Nummernkreise vollständig erstellen und fachlich anbinden.
- [x] T015a Additives Backend-Fundament für Felddefinitionen, Kategorien und transaktionsgeschützte Nummernkreise mit Vereinsgrenzen, Rollen, Audit und API umsetzen.
- [x] T015b Eigene Feldwerte und Kategoriezuordnungen kontrolliert an Mitglieder, Mannschaften, Termine und Inventar anbinden; bestehende Mitglieds-, Rechnungs- und Shopnummern kompatibel auf konfigurierbare Nummernkreise umstellen.
- [x] T015b1 Feldwerte und Kategoriezuordnungen mit typspezifischer Validierung, sensibler Sichtbarkeit, Vereinsgrenzen und Audit an die vier Fachobjekte anbinden.
- [x] T015b2 Bestehende Mitglieds-, Rechnungs-, Beleg-, Spenden-, Inventar- und Shopnummern nach Bestands-/Kollisionsprüfung kontrolliert und rückwärtskompatibel an Nummernkreise anbinden.
- [x] T015b2a Read-only-Bestandsaudit, Standardkreis-Zuordnung und sichere Adoptionsregeln für bestehende Nummernwege ergänzen, ohne Nummern automatisch umzudeuten.
- [x] T015b2b Mitglieds- und Vereinsrechnungsnummern transaktionsgeschützt anbinden und Rollback-, Wiederholungs- sowie Fallbackverhalten prüfen; echter MySQL-Konkurrenznachweis bleibt in T015e.
- [x] T015b2c Beleg-, Spenden-, Inventar- und Shopnummern kontrolliert anbinden und die bisherigen Spezialabläufe regressionsprüfen.
- [x] T015c Web-Verwaltung und Verwendung mit Validierung, sensiblen Feldern und barrierearmer Bedienung ergänzen.
- [x] T015c1 Web-Konfiguration für Felddefinitionen, Kategorien, Nummernkreise und Standardkreise mit DE/EN/FR/AR und barrierearmer Bedienung ergänzen.
- [x] T015c2 Web-Wertepflege an Mitgliedern, Mannschaften, Terminen und Inventar einschließlich sensibler Kennzeichnung und Pflichtfeldvalidierung ergänzen.
- [x] T015d Native App-Verwaltung und Verwendung mit DE/EN/FR/AR sowie sicherem Übertragungsverhalten ergänzen.
- [x] T015d1 Native Konfigurationsverwaltung für Felddefinitionen, Kategorien, Nummernkreise und Standardkreise mit sicherem Übertragungsverhalten ergänzen.
- [x] T015d2 Native Wertepflege an Mitgliedern, Mannschaften, Terminen und Inventar mit DE/EN/FR/AR, Pflichtfeldern und sensibler Kennzeichnung ergänzen.
- [ ] T015e Bestandsmigration, Konkurrenzverhalten unter MySQL, Browser-/Realgeräteabnahme und kontrollierte Einführung abschließen.
- [x] T015e1 Strikten, versionierten Freigabevertrag für Migration, MySQL-Konkurrenz, Browser, Realgeräte, Kernabläufe und Freigaben ergänzen.
- [ ] T015e2 Bestandsaudit und Migrationsprobe auf freigegebener MySQL-Kopie, echten Parallelitätsnachweis, Browser-/Realgeräteabnahme und kontrollierte Einführung durchführen.
- [x] T015e2a Wiederholbaren, selbstbereinigenden Mehrprozess-Prüfer für den echten MySQL-Konkurrenznachweis bereitstellen und gegen versehentliche Ausführung absichern.
- [ ] T015e2b Prüfer auf einer freigegebenen isolierten MySQL-Kopie ausführen, Browser-/Android-/iOS-Abnahme und Freigaben dokumentieren und die kontrollierte Einführung durchführen.
- [ ] T016 Satzungen, Ordnungen und Beitragsmodelle mit ihren Gültigkeitszeiträumen vollständig hinterlegen und fachlich verwenden.
- [x] T016a Additive versionierte Dokumentablage mit bestehenden Vereinsdateien, Zeitraumprüfung, Sichtbarkeit, Vereinsgrenzen, Löschschutz und Audit als Backend/API ergänzen.
- [x] T016b Web-Verwaltung und lesende Darstellung für Satzungen, Ordnungen und Beitragsmodelle mit DE/EN/FR/AR ergänzen.
- [x] T016c Native App-Verwaltung und Darstellung mit sicherem Übertragungsverhalten und DE/EN/FR/AR ergänzen.
- [x] T016d Operative Beitragsregeln kontrolliert mit formalen Beitragsmodellfassungen verknüpfen und historische Zuordnungen erhalten.
- [x] T016d1 Additive Backend-/API-Verknüpfung mit Vereins-, Typ- und Zeitraumprüfung sowie Löschschutz für historische Zuordnungen ergänzen.
- [x] T016d2 Web-Verwaltung und historische Darstellung der verknüpften Beitragsmodellfassung mit DE/EN/FR/AR ergänzen.
- [x] T016d3 Native App-Verwaltung und Darstellung der verknüpften Beitragsmodellfassung mit DE/EN/FR/AR ergänzen.
- [ ] T016e Bestandsmigration, Browser-/Realgeräteabnahme und kontrollierte Einführung abschließen.
- [x] T016e1 Versionierten Read-only-Bestandsprüfer, Evidenzvertrag, Abnahmewege und Go/No-Go-Runbook bereitstellen.
- [ ] T016e2 Prüfer auf einer freigegebenen Datenbankkopie ausführen, Web/Android/iOS real abnehmen, Freigaben dokumentieren und kontrolliert einführen.
- [ ] T017 Anpassbare Vereinsrollen, fein getrennte Berechtigungen, zeitlich begrenzte Vertretungen und Tätigkeitsende-Prüfungen vollständig umsetzen.
- [x] T017a Additive, zeitlich begrenzte Vertretungsrechte als Backend/API mit Vereinsgrenzen, maximal 90 Tagen, eigenem wirksamem Rechteumfang, nicht delegierbarer Rollenverwaltung, sofortigem Widerruf und Audit ergänzen.
- [x] T017b Konfigurierbare Vereinsrollen einschließlich Vorstand, Geschäftsstelle, Kassenwart, Kassenprüfer, Abteilungsleitung, Betreuung, Sorgeberechtigten, Helfern und externen Kontakten ergänzen; bestehende Rollen kompatibel erhalten.
- [x] T017b1 Mandantengebundene Rollendefinitionen mit elf vollständigen Vorlagen, eigenem Namen/Schlüssel, aktivem Zustand, Rechtebegrenzung auf den handelnden Rechteumfang, Audit und CRUD-API ergänzen.
- [x] T017b2 Mehrere konfigurierbare Rollen pro aktivem Vereinsmitglied getrennt von bestehenden Rollen zuweisen; wirksame Rechte zentral zusammenführen, Einzelrecht-Ausschlüsse priorisieren und inaktive Rollen ohne Datenverlust entziehen.
- [x] T017c Berechtigungen nach Verein, Abteilung, Mannschaft, Datenart sowie Lesen, Bearbeiten, Exportieren, Freigeben und Löschen getrennt durchsetzen.
- [x] T017c1 Getrennte Bearbeiten-/Export-/Freigabe-/Löschrechte für Mitglieder und Finanzen additiv einführen; alte `*.manage`-Rechte kompatibel abbilden, personenbezogene Ausschlüsse priorisieren und Mitgliedslöschung sowie SEPA-Freigabe/-Export tatsächlich trennen.
- [x] T017c2 Getrennte Aktionsrechte auf weitere Datenarten und sämtliche betroffene Web-/API-Pfade ausweiten; verbliebene feste Rollenabfragen kontrolliert auf den zentralen Rechtevertrag umstellen.
- [x] T017c2a Bearbeiten und Löschen von Terminen als getrennte Rechte ergänzen, bestehendes `events.manage` kompatibel abbilden und die zentrale Event-Policy für Web/API darauf umstellen.
- [x] T017c2b Weitere Datenarten sowie verbliebene feste Rollen- und globale Paketprüfungen auf getrennte Vereinsrechte umstellen.
- [x] T017c2b1 Bearbeiten und Löschen von Mannschaften als getrennte Rechte ergänzen und Web-/API-Metadatenpfade über die gemeinsame Team-Policy durchsetzen.
- [x] T017c2b2 Dateien, Inventar und weitere Datenarten sowie verbliebene feste Rollen- und globale Paketprüfungen auf getrennte Vereinsrechte umstellen.
- [x] T017c2b2a Ansehen, Anlegen/Bearbeiten und Löschen von Dateien sowie Ordnern als getrennte Rechte in Web und API durchsetzen; bestehendes `files.manage` kompatibel abbilden.
- [x] T017c2b2b Datei-Download/Export und Datei-/Ordnerfreigaben vom Leserecht trennen, in Web/API durchsetzen und in den veröffentlichten Aktionsrechten ausweisen.
- [x] T017c2b2c Inventar und weitere Datenarten sowie verbliebene feste Rollen- und globale Paketprüfungen auf getrennte Vereinsrechte umstellen.
- [x] T017c2b2c1 Inventaransicht/-ausleihe, Stammdaten-/Wartungspflege, Ausleihfreigabe/Rückgabe und Löschen als getrennte Rechte in Web/API durchsetzen; bestehendes `inventory.manage` kompatibel abbilden und verlaufsbehaftete Gegenstände schützen.
- [x] T017c2b2c2 Weitere Datenarten sowie verbliebene feste Rollen- und globale Paketprüfungen auf getrennte Vereinsrechte umstellen.
- [x] T017c2b2c2a Ankündigungen als getrennte Entwurfs-/Bearbeitungs-, Veröffentlichungs- und Löschrechte in der API durchsetzen; bestehendes `announcements.manage` kompatibel abbilden und versandte Veröffentlichungen gegen Löschen schützen.
- [x] T017c2b2c2b Umfragen, Support und weitere Datenarten sowie verbliebene feste Rollen- und globale Paketprüfungen auf getrennte Vereinsrechte umstellen.
- [x] T017c2b2c2b1 Umfragen als getrennte Anlegen-/Bearbeiten-, Schließen- und Löschrechte in API und veröffentlichten Bedienrechten durchsetzen; bestehendes `surveys.manage` kompatibel abbilden und abgegebene Stimmen gegen Inhaltsänderung oder Löschung schützen.
- [x] T017c2b2c2b2 Support und weitere Datenarten sowie verbliebene feste Rollen- und globale Paketprüfungen auf getrennte Vereinsrechte umstellen.
- [x] T017c2b2c2b2a Supportansicht, fachliche Bearbeitung, Zuweisung und Abschluss als getrennte Vereinsrechte in API und Web durchsetzen; bestehendes `support.manage` kompatibel abbilden und Zuweisungen auf tatsächlich bearbeitungsberechtigte Personen begrenzen.
- [x] T017c2b2c2b2b Weitere Datenarten sowie verbliebene feste Rollen- und globale Paketprüfungen auf getrennte Vereinsrechte umstellen.
- [x] T017c2b2c2b2b1 Vereinsjahre als getrennte Ansehen-, Anlegen/Bearbeiten- und Löschrechte in API, Web und nativer App durchsetzen; bestehendes `content.manage` kompatibel abbilden.
- [x] T017c2b2c2b2b2 Abteilungen, Standorte und Trainingsgruppen als getrennte Ansehen-, Anlegen/Bearbeiten- und Löschrechte in API, Web und nativer App durchsetzen; Mannschaftszuordnung an das eigene Mannschaftsrecht binden und `content.manage` kompatibel abbilden.
- [x] T017c2b2c2b2b3 Satzungen, Ordnungen und Beitragsmodelle als getrennte Ansehen-, Anlegen/Bearbeiten-, Download- und Löschrechte in API, Web und nativer App durchsetzen; `content.manage` kompatibel abbilden und ausgeschiedenen Personen internen Zugriff entziehen.
- [x] T017c2b2c2b2b4 Vorstand, Ausschüsse, Arbeitsgruppen und Funktionszuweisungen als getrennte Ansehen-, Anlegen/Bearbeiten- und Löschrechte in API, Web und nativer App durchsetzen; `content.manage` kompatibel abbilden und interne Personendaten schützen.
- [x] T017c2b2c2b2b5 Eigene Datenfelder, Kategorien und Nummernkreise als getrennte Ansehen-, Anlegen/Bearbeiten- und Löschrechte in API, Web und nativer App durchsetzen; Fachwertpflege und Nummernvergabe an das Bearbeitungsrecht binden und `content.manage` kompatibel abbilden.
- [x] T017c2b2c2b2b6 Getrennte Bearbeitungs-, Veröffentlichungs-/Schließ- und Löschrechte für Ankündigungen und Umfragen vollständig in der nativen App veröffentlichen und bedienen; bereichsgebundene Fähigkeiten ohne pauschales Vereinsverwaltungsrecht verfügbar machen.
- [x] T017c2b2c2b2b7 Allgemeine Vereinsdaten, rechtlich/steuerliche Stammdaten, Kontaktangaben sowie Branding und Dokumentvorlagen als vier getrennte Bearbeitungsrechte in API, Web und nativer App durchsetzen; Bildänderungen an das Brandingrecht binden und `content.manage` kompatibel abbilden.
- [x] T017c2b2c2b2b8a Die ältere Rollenpflege im Vereinsprofil an `members.roles` statt an das pauschale Vereinsverwaltungsrecht binden und für reine Rollen-Fachbearbeiter in der Weboberfläche freischalten.
- [x] T017c2b2c2b2b8b Verbliebene Web-Schreibpfade für Mitgliedschaftseinstellungen, Mitgliedsdaten, Anträge, Importe, Beiträge, Rechnungen, Zahlungen, Bankabgleich, SEPA und DATEV an dieselben getrennten Mitglieder-, Rollen-, Metadaten- und Finanzrechte wie die API binden.
- [x] T017c2b2c2b2b8c Mannschaftserstellung in Web und nativer API an `teams.edit` binden und die bisherige `content.manage`-Berechtigung für bestehende Manager kompatibel auf Mannschaftsbearbeitung und -löschung abbilden.
- [x] T017c2b2c2b2b8d Vereinssponsoren mit getrennten Anlegen-/Bearbeiten- und Löschrechten schützen, die Fähigkeiten in Web/API/App veröffentlichen und `content.manage` kompatibel abbilden.
- [x] T017c2b2c2b2b8e Offizielle Vereinsbeiträge und -Storys in Web und nativer API an `content.manage` statt an das pauschale Vereinsverwaltungsrecht binden und normale Mitgliederregeln unverändert lassen.
- [x] T017c2b2c2b2b8f Weitere Datenarten sowie verbliebene feste Rollen- und globale Paketprüfungen auf getrennte Vereinsrechte umstellen.
- [x] T017c2b2c2b2b8f1 Das globale Rollen-/Abo-Limit bei der Terminerstellung um `events.edit` im tatsächlich gewählten Vereins-, Abteilungs- oder Mannschaftsbereich ergänzen; Serienfähigkeit zielgebunden in Web/API/App veröffentlichen und fremde Bereiche geschlossen halten.
- [x] T017c2b2c2b2b8f2 Vereinsabonnements mit getrennten Ansehen- und Abschluss-/Änderungsrechten schützen, feste Abrechnungsrollen kompatibel abbilden und die Fähigkeiten in API und nativer App veröffentlichen.
- [x] T017c2b2c2b2b8f3 Stellen- und Ehrenamtsausschreibungen mit getrennten Entwurfs-/Bearbeitungs-, Veröffentlichungs- und Löschrechten schützen, die Webaktionen einzeln bedienen und Fähigkeiten in API/App veröffentlichen.
- [x] T017c2b2c2b2b8f4 Bewerbungen mit getrennten Ansehen-, Status-/Notizbearbeitungs-, Kontakt- und Löschrechten schützen, die Pipeline auf berechtigte Vereine begrenzen und Aktionsfähigkeiten in Web/API/App einzeln bedienen.
- [x] T017c2b2c2b2b8f5 Die Übungsbibliothek mit getrennten Ansehen-, Anlegen/Bearbeiten- und Löschrechten in Vereins- und Mannschaftsbereichen schützen, bestehende Terminverwalter und Teamtrainer kompatibel halten und die Fähigkeiten in API/App einzeln bedienen.
- [x] T017c2b2c2b2b8f6 Vereins-Add-on-Käufe, Marketplace-Angebote, Werbekampagnen und Website-Aufträge mit vier getrennten Vereinsrechten schützen, die Zielvereine in Web/API/App aktionsbezogen anbieten und bisherige erhöhte Vereinsrollen kompatibel halten.
- [x] T017c2b2c2b2b8f7 Den Zugang zum Vereins-Cockpit als eigenes Vereinsrecht statt über feste erhöhte Rollen steuern, nur freigegebene Vereine auswerten und die Fähigkeit in Webnavigation, Suche, Workspace-Auswahl und nativer App veröffentlichen.
- [x] T017c2b2c2b2b8f8 Den Zugang zur Mitglieder- und Finanzarbeitsfläche in Route, Inertia-Navigation und Modulwahl einheitlich aus den getrennten Mitglieder-, Finanz- und Rollenrechten ableiten; Fachrollen ohne pauschale Managerrolle sichtbar machen und ausdrückliche Sperren beachten.
- [x] T017c2b2c2b2b8f9 Benachrichtigungen zu Mitgliedschaftsanträgen und automatisch beendeten Mitgliedschaften nur an die im konkreten Verein zuständigen Freigabe- beziehungsweise Mitgliederverwaltungsrollen senden; feste Managerrollen und ausdrücklich gesperrte Empfänger ablösen.
- [x] T017c2b2c2b2b8f10 Die eigenständige Sponsorverwaltungs-API und native Oberfläche auf getrennte Sponsor-Bearbeitungs- und Löschrechte umstellen; Vereinsauswahl und Aktionen fähigkeitsbezogen veröffentlichen und globale Plattformverwaltung erhalten.
- [x] T017c2b2c2b2b8f11 Verbliebene feste Rollen-Bypässe für offizielle Vereins- und Mannschaftsbeiträge beziehungsweise Storys entfernen; `content.manage`, bereichsgebundene Inhaltsrollen und direkte Teamredaktionsrollen durchgängig anwenden sowie ausdrückliche Sperren respektieren.
- [x] T017c2b2c2b2b8f12 Teambeitritts- und Austrittsbenachrichtigungen sowie Trainingsanwesenheitsstatistiken an die wirksamen, bereichsgebundenen Mitgliederrechte und die gemeinsame Team-Policy binden; feste Vereinsmanagerlisten und ausdrückliche Sperren ablösen.
- [x] T017c2b2c2b2b8f13 Verbliebene pauschale Vereinsmanager-Bypässe in der Team-Policy für Bearbeiten, Aufnahme, Rollenänderung, Entfernung und Löschen schließen; Legacy-Teamrollen nur ohne ausdrückliches Negativrecht weiterführen.
- [x] T017c2b2c2b2b8f14 Vereinsabo-Ziele auf Preisseite und Web-Checkout an `subscriptions.edit` binden sowie Status- und Lifecycle-Benachrichtigungen ausschließlich an Personen mit `subscriptions.view` senden; konfigurierte Fachrollen und ausdrückliche Sperren berücksichtigen.
- [x] T017c2b2c2b2b8f15 Den letzten festen Vereinsmanager-Rückfall in der Terminanwesenheit durch `events.edit` ersetzen; konfigurierte Terminrollen und ausdrückliche Sperren berücksichtigen, direkte Mannschaftstrainer weiter zulassen.
- [x] T017c2b2c2b2b8f16 Vereins- und Mannschafts-Challenges im konkreten Bereich an `events.edit` binden; konfigurierte Bereichsrollen und direkte Teamverantwortliche zulassen sowie den globalen Coach-Zugriff auf fremde Mannschaften schließen.
- [x] T017c2b2c2b2b8f17 Den Trainer-Cockpit-Zugang als eigenes Vereinsrecht modellieren, auf zugewiesene Mannschafts- und Abteilungsbereiche begrenzen und ausdrückliche Sperren gegenüber bisherigen erhöhten Vereinsrollen durchsetzen.
- [x] T017c2b2c2b2b8f18 Sponsor-Arbeitsfläche, Navigation und Suche aus den getrennten Sponsor-, Werbe- und Website-Rechten ableiten; Verträge, Kampagnen und Aufträge je Datenart filtern und ausdrückliche Sperren berücksichtigen.
- [x] T017c2b2c2b2b8f19 Automatische Erinnerungen zu auslaufenden Mitgliedschaften, Beitragsrechnungen und Vereinsabonnements an die jeweils zuständigen Mitglieder-, Finanz- und Abo-Rechte binden; konfigurierte Rollen und ausdrückliche Sperren berücksichtigen.
- [x] T017c2b2c2b2b8f20 Die Sichtbarkeit von Trainingslogs im Trainer-Cockpit an `trainer_cockpit.view` im wirksamen Mannschafts- oder Abteilungsbereich angleichen; private und fremde Trainingsdaten weiterhin ausschließen.
- [x] T017c2b2c2b2b8f21 Trainingsplanverwaltung als eigenes Vereinsrecht modellieren, Mannschaftsziele auf den zugewiesenen Bereich begrenzen und bestehende globale Trainer- sowie direkte Teamleitungsrollen kompatibel halten.
- [x] T017c2b2c2b2b8f22 Die globale Club-Admin-Löschfreigabe für Fahrten entfernen; Löschung auf Fahrer und Plattformadministration begrenzen und fremde Vereins- beziehungsweise Mannschaftskontexte unverändert schützen.
- [x] T017c2b2c2b2b8f23 Mannschaftskasse und Strafenkatalog als eigenes, bereichsgebundenes Vereinsrecht modellieren; Kassenwartrollen gezielt zulassen, direkte Teamverantwortliche kompatibel halten und ausdrückliche Sperren durchsetzen.
- [x] T017c2b2c2b2b8f24 Personenscharfe Mannschaftsauswertungen auf bereichsgebundene Trainer-Cockpit-Rechte und direkte Teamverantwortliche begrenzen; gewöhnliche Vereinsmitglieder, fremde Mannschaften und ausdrücklich gesperrte Coaches ausschließen.
- [x] T017c2b2c2b2b8f25 Ausdrückliche Inhalts-Sperren auch gegenüber direkten Coach-/Captain-Rollen bei offiziellen Mannschaftsstorys in Web und API durchsetzen; bestehende Teamredaktion ohne Sperre kompatibel halten.
- [x] T017c2b2c2b2b8f26 Ausdrückliche Ansichts-, Bearbeitungs- und Löschsperren der Übungsbibliothek auch gegenüber Erstellern bereichsgebundener Übungen und direkten Teamverantwortlichen durchsetzen; persönliche Übungen unverändert eigentümergeführt halten.
- [x] T017c2b2c2b2b8f27 Ausdrückliche Sperren für Mannschaftsbearbeitung und Aufnahmeentscheidungen in der zentralen Team-Policy auch gegenüber direkten Coach-/Captain-Rollen und globalen Legacy-Rechten priorisieren.
- [x] T017c2b2c2b2b8f28 Ausdrückliche Termin-/Challenge-Sperren bei Mannschafts-Challenges auch gegenüber direkten Teamverantwortlichen durchsetzen; ungesperrte direkte Coaches weiterhin kompatibel zulassen.
- [x] T017c2b2c2b2b8f29 Erstellerrechte für vereins- und teamgebundene Termine an die aktuellen Bereichsrechte binden und ausdrückliche Bearbeitungs-/Löschsperren auch bei direkten Teamrollen und Anwesenheiten durchsetzen; persönliche Termine eigentümergeführt halten.
- [x] T017c2b2c2b2b8f30 Offizielle Vereins- und Mannschaftsstorys nach einem Rechteentzug nicht mehr über die technische Autorenschaft löschen lassen; persönliche Storys eigentümergeführt und direkte Teamredaktion ohne Sperre kompatibel halten.
- [x] T017c2b2c2b2b8f31 Den Abbruch offizieller Vereins- und Mannschafts-Challenges an das aktuelle `events.edit` im Zielbereich binden; frühere Ersteller nach Rechteentzug sperren, aktuell zuständige Fachrollen zulassen und private Challenges eigentümergeführt halten.
- [x] T017c2b2c2b2b8f32 Das native Team-Bedienflag für die Anlage eigener Übungen an dieselbe ausdrückliche `training_exercises.edit`-Sperre wie die schreibenden Endpunkte binden und den Feature-Tarif unverändert berücksichtigen.
- [x] T017c2b2c2b2b8f33 Ausdrückliche `files.edit`-Sperren bei Upload und Ordneranlage gegenüber globalen Legacy-Dateirechten priorisieren; Web-, API- und Termin-Dateikontext angleichen und persönliche Ablagen unverändert lassen.
- [x] T017c2b2c2b2b8f34 Ausdrückliche Datei- und Ordnersperren für Ansicht, Bearbeitung, Löschung, Export und Freigabe gegenüber Mitgliedschaft, Legacy-Rechten und technischer Autorenschaft priorisieren; persönliche, sichtbare Chat- und Antragsdokumente kompatibel halten.
- [x] T017c2b2c2b2b8f35 Echtzeitkanäle für Vereins- und Mannschaftstermine auf tatsächliche Mitgliedschaft oder das wirksame `events.edit` im konkreten Bereich begrenzen und mandantenfremde globale Legacy-Rechte ausschließen.
- [x] T017c2b2c2b2b8f36 Ausdrückliche `trainer_cockpit.view`-Sperren gegenüber direkten Teamrollen in Cockpit und Trainingslog-Zugriff priorisieren; ungesperrte Coaches und bereichsgebundene Fachrollen kompatibel halten.
- [x] T017c2b2c2b2b8f37 Ausdrückliche `training_plans.edit`-Sperren gegenüber direkten Team-Coaches und Club-President-Rollen in Verwalterentscheidung und Zielmannschaftskatalog priorisieren; persönliche Pläne unverändert zulassen.
- [x] T017c2b2c2b2b8f38 Kommentar- und Unterhaltungs-Policies auf tatsächliche Autorenschaft, Beitragsverantwortung beziehungsweise Teilnahme begrenzen; globale Vereins-/Trainerrollen und Plattformvollzugriff nicht als Zugriff auf private Kommunikation auslegen.
- [x] T017c2b2c2b2b8f39 Die Web-Vereinsprofilpflege ausschließlich aus den getrennten Profil-, Rechts-/Steuer-, Kontakt-, Branding- und Rollenrechten steuern und den pauschalen `can_manage`-Rückfall bei ausdrücklichen Sperren entfernen.
- [x] T017c2b2c2b2b8f40 Logo- und Titelbildaktionen im nativen Vereinsprofil ausschließlich an `can_edit_club_branding` binden und den pauschalen `can_manage`-Rückfall bei ausdrücklicher Branding-Sperre entfernen.
- [x] T017c2b2c2b2b8f41 Metadatenansicht und -bearbeitung im Club-API-Vertrag getrennt veröffentlichen; Web-Navigation, native Organisationsansicht und Mitgliederdaten-Editor ausschließlich an die wirksamen Einzelrechte binden und ausdrückliche Sperren gegenüber `can_manage` priorisieren.
- [x] T017c2b2c2b2b8f42 Mannschaftserstellung im Club-API-Vertrag als wirksames Einzelrecht veröffentlichen und native Vereins- sowie Mannschaftsansichten daran binden; spezialisierte Team-Bearbeiter zulassen und allgemeine Manager mit ausdrücklicher Sperre ausschließen.
- [x] T017c2b2c2b2b8f43 Native Vereins-Cockpit-Navigation und Clubauswahl ausschließlich an `can_view_cockpit` binden; bereichsgebundene Leser zulassen und pauschale Manager-, Profilbearbeiter- sowie Eigentümer-Rückfälle bei ausdrücklicher Sperre entfernen.
- [x] T017c2b2c2b2b8f44 Mitgliederverwaltung und Finanzansicht als getrennte Club-API-Fähigkeiten veröffentlichen und den nativen Arbeitsbereich für jede tatsächlich berechtigte Fachrolle öffnen; ausdrückliche Sperren gegenüber dem allgemeinen Managerstatus priorisieren.
- [x] T017c3 Rollen- und Vertretungsrechte nach Verein, Abteilung und Mannschaft begrenzen und die Scope-Prüfung in allen betroffenen Fachpfaden durchsetzen.
- [x] T017c3a Rollenzuweisungen additiv auf Verein, Abteilung oder Mannschaft begrenzen; globale Auswertung für Bereichsrollen sicher schließen, exakte Kontextprüfung zentral bereitstellen und Vereinsgrenzen in der Zuweisungs-API erzwingen.
- [x] T017c3b Bereichsbezogene Rollen- und Vertretungsrechte in allen betroffenen Abteilungs- und Mannschaftspfaden durchsetzen und bestehende feste Rollenprüfungen kontrolliert ablösen.
- [x] T017c3b1 Team- und Abteilungsrollen für Terminbearbeitung und -löschung hierarchisch durchsetzen; private Termine für berechtigte Bereichsrollen erreichbar machen und andere Bereiche verborgen halten.
- [x] T017c3b2 Bereichsrechte auf weitere Mannschafts-, Mitglieder-, Datei-, Inventar- und Abteilungspfade ausweiten sowie bereichsgebundene Vertretungen ergänzen.
- [x] T017c3b2a Aufnahme/Freigabe, Teamrollenänderung und Entfernung von Mannschaftsmitgliedern als getrennte Team-/Abteilungsrechte in gemeinsamer Policy, Web und API durchsetzen; bisherige Team- und Vereinsrollen kompatibel erhalten.
- [x] T017c3b2b Bereichsrechte für Mannschaftsmetadaten und Mannschaftslöschung durchsetzen; Abteilungsrollen gegen Verschieben in fremde Zuständigkeitsbereiche absichern.
- [x] T017c3b2c Bereichsrechte für Dateiansicht, Datei-/Ordnerpflege, Löschen, Download/Export und Freigabe in Vereins-, Abteilungs-, Mannschafts- und Mannschaftstermin-Kontexten durchsetzen.
- [x] T017c3b2d Bereichsrechte auf Inventar und weitere Abteilungspfade ausweiten sowie bereichsgebundene Vertretungen ergänzen.
- [x] T017c3b2d1 Inventargegenstände additiv Abteilungen und Mannschaften zuordnen; Ansicht, Ausleihe, Pflege, Freigabe, Rückgabe und Löschung hierarchisch auf den zugewiesenen Bereich begrenzen und Bereichswechsel gegen Rechteausweitung absichern.
- [x] T017c3b2d2 Bereichsrechte auf weitere Abteilungspfade ausweiten und bereichsgebundene Vertretungen ergänzen.
- [x] T017c3b2d2a Ankündigungsentwürfe, Veröffentlichung und Löschung auf Vereins-, Abteilungs- und Mannschaftsbereiche begrenzen; Zielbereichswechsel gegen Rechteausweitung absichern.
- [x] T017c3b2d2b Bereichsrechte auf Umfragen, Support und weitere Abteilungspfade ausweiten sowie bereichsgebundene Vertretungen ergänzen.
- [x] T017c3b2d2b1 Umfragebearbeitung, Schließen und Löschen auf Vereins-, Abteilungs- und Mannschaftsbereiche begrenzen; Zielbereichswechsel gegen Rechteausweitung absichern.
- [x] T017c3b2d2b2 Bereichsrechte auf Support und weitere Abteilungspfade ausweiten sowie bereichsgebundene Vertretungen ergänzen.
- [x] T017c3b2d2b2a Zeitlich begrenzte Vertretungen additiv auf Verein, Abteilung oder Mannschaft begrenzen, Altbestände vereinsweit kompatibel halten und eine Weitergabe außerhalb des eigenen Rechtebereichs verhindern.
- [x] T017c3b2d2b2b Bereichsrechte auf Support und weitere Abteilungspfade ausweiten.
- [x] T017c3b2d2b2b1 Supporttickets additiv Verein, Abteilung oder Mannschaft zuordnen; Ansicht, Bearbeitung, Zuweisung und Abschluss hierarchisch begrenzen und Bereichsauswahl in der Weboberfläche bereitstellen.
- [x] T017c3b2d2b2b2 Bereichsrechte auf weitere verbliebene Abteilungspfade ausweiten.
- [x] T017c3b2d2b2b2a Organisationsabteilungen, Trainingsgruppen und Mannschaftszuordnungen je Datensatz auf den wirksamen Vereins- oder Abteilungsbereich begrenzen; Bereichswechsel schließen, globale Standorte schützen und bereichsgebundene Vertretungen in der Arbeitsbereichsermittlung berücksichtigen.
- [x] T017c3b2d2b2b2b Trainingsübungen in Listen-, Anlage-, Bearbeitungs- und Löschpfaden auf wirksame Vereins-, Abteilungs- oder Mannschaftsrechte begrenzen; ausdrückliche Ansichtsverbote auch bei direkter Mannschaftszuordnung durchsetzen und bereichsgebundene Vertretungen berücksichtigen.
- [x] T017c3b2d2b2b2c Mannschaftsanlage in Web und nativer API für Abteilungsrollen und aktive Abteilungsvertretungen öffnen; Zielabteilungen einschränken, globale und fremde Ziele sperren sowie gleichnamige Bestandsmannschaften gegen Bereichsverschiebung absichern.
- [x] T017c3b2d2b2b2d Mitgliederkarten-Check-in an das konkrete Terminrecht binden; Abteilungs- und Mannschaftsrollen nur für Termine ihres Bereichs zulassen und tokenbasierte Abfragen ohne Termin weiter vereinsweit schützen.
- [x] T017c3b2d2b2b2e Offizielle Mannschaftsbeiträge und -storys in Web und nativer API über das wirksame Inhaltsrecht des Zielteams freigeben; die zusätzliche vereinsweite Rechtehürde entfernen und fremde Mannschaften geschlossen halten.
- [x] T017d Gemeinsames Vier-Augen-Verfahren und automatische Prüfwiedervorlage beim Ende einer Tätigkeit für sensible Rechte vervollständigen.
- [x] T017d1 Konfigurierbare Rollenzuweisungen beim Austritt entfernen und aktive Vertretungen der austretenden oder erteilenden Person sofort widerrufen; direkten Austritt, verwaltete Entfernung und geplantes Mitgliedschaftsende mit Audit absichern.
- [x] T017d2 Prüfwiedervorlage vor Tätigkeitsende, verantwortliche Entscheidung über Folgezuweisungen und gemeinsames Vier-Augen-Verfahren für weitere sensible Rechte ergänzen.
- [x] T017d2a Vor einem geplanten Mitgliedschaftsende eine deduplizierte, lokalisierte Zugriffs-Wiedervorlage mit Rollen-/Vertretungsanzahl und ausdrücklicher Entscheidung über Entfernung oder Nachfolge an alle berechtigten Rollenverwaltungen senden.
- [x] T017d2b Verbindliche Nachfolgeentscheidung und gemeinsames Vier-Augen-Verfahren für weitere sensible Rechte mit Web-/App-Bedienung ergänzen.
- [x] T017d2b1 Persistente Entfernung/Nachfolge für Rollen beim Mitgliedschaftsende mit unverändertem Berechtigungs-Snapshot, unabhängiger Zweitfreigabe, erneuter Prüfung beim Austritt und Web-/App-Bedienung umsetzen.
- [x] T017d2b2 Gemeinsames Vier-Augen-Verfahren auf weitere sensible Rechte außerhalb der Rollenübergabe ausweiten: Mitgliedschaftsänderungen, Teambeitritte, freigabepflichtige Inventarausleihen, Trainerrollen und Vereinsverifizierungen gegen Selbstfreigabe absichern; bestehende Finanz-Zweitfreigaben regressiv prüfen.
- [x] T017e Rollen, Einzelrechte und Vertretungen im Web verwalten und barrierearm prüfen.
- [ ] T017f Rollen, Einzelrechte und Vertretungen in der nativen App mit sicherem Übertragungsverhalten und DE/EN/FR/AR verwalten.
- [x] T017f1 Native Verwaltung für Einzelrechte, mehrere bereichsgebundene Rollen und zeitlich begrenzte Vertretungen samt API-Vertrag und DE/EN/FR/AR implementieren und statisch absichern.
- [x] T017f2 Flutter-Analyzer, Widgettests sowie Android-/iOS-Realgeräteprüfung für die native Zugriffsverwaltung abschließen.
- [ ] T017g Bestandsmigration, Rollen-/Mandantentrennung, Browser-/Realgeräteabnahme und kontrollierte Einführung abschließen.
- [x] T018 Persönliche Startseite und zentrale Arbeitsübersicht je Rolle mit Terminen, offenen Vorgängen, Hinweisen, Suche, Schnellaktionen und gespeicherten Ansichten vervollständigen.
- [x] T018a Bis zu vier persönliche Schnellaktionen aus Training, Route, Ernährung, Trainingsplan, Terminen, Teams, Dateien, Feed und Inbox auswählen, geordnet serverseitig speichern und bei fehlender neuer Präferenz die bisherigen Standardaktionen erhalten.
- [x] T018b Rollenbezogene Startansichten und gemeinsame Terminübersicht für Training, Spiele, Sitzungen und Veranstaltungen vollständig zusammenführen.
- [x] T018c Offene Anträge, Zahlungen, Aufgaben und Freigaben sowie fehlende Dokumente und auslaufende Verträge oder Lizenzen zentral und berechtigungsabhängig hervorheben.
- [x] T018c1 Offene Mitgliedschafts- und Teambeitrittsanträge, überfällige Restforderungen sowie fremdfreigabefähige Inventar- und Zugriffsentscheidungen berechtigungsabhängig in Web-Dashboard und nativer Tagesansicht bündeln.
- [x] T018c2 Fehlende oder nach einer Dokumentänderung veraltete Pflichtbestätigungen offener Mitgliedsanträge sowie nahende Vertrags-, Richtlinien- und Vereinsstammdatenfristen berechtigungsabhängig in Web und App hervorheben.
- [x] T018c3 Handlungsfähige Mannschaftsaufgaben aus Rückmeldungen, Fahrgemeinschaften, Teamgebühren und organisatorischen Hinweisen rollenabhängig bündeln und stabile Routinen nicht als offene Arbeit zählen.
- [x] T018c4 Das bestehende Sportlizenzfeld um ein optionales Gültigkeitsdatum für verknüpfte und externe Mitglieder erweitern, in Profil, Mitgliedspflege, Import, API und App durchgängig bearbeiten sowie abgelaufene und binnen 90 Tagen auslaufende Lizenzen berechtigungsabhängig hervorheben.
- [x] T018d Globale Suche und gespeicherte Filter beziehungsweise Ansichten für die zentralen Arbeitsbereiche vollständig prüfen und verbleibende Lücken schließen.
- [x] T018d1 Bestehende globale Suche für Mitglieder, Termine, Rechnungen und Dateien auf Mandanten-, Sichtbarkeits- und Fachrechte prüfen und ihre Web-/App-Verträge regressiv absichern.
- [x] T018d2 Persönliche Suchansichten mit Suchbegriff und Ergebnistyp serverseitig speichern, favorisieren, laden und löschen; Web und native Suche sowie Datenschutzexport und Profildatenlöschung anbinden.
- [x] T018d3 Gespeicherte Filter in Mitglieder-, Termin-, Rechnungs- und Datei-Arbeitsflächen anwenden und die jeweiligen Web-/App-Filter vollständig anbinden.
- [x] T019 Zentrale Mitglieder- und Personenverwaltung vervollständigen, ohne bestehende Mitgliedschafts-, Beitrags- und Rollenabläufe zu verändern.
- [x] T019a Familien- und Haushaltszuordnung um einen abweichenden Beitragszahler erweitern und diesen bei manuellen sowie wiederkehrenden Beitragsrechnungen als Rechnungsempfänger verwenden.
- [x] T019b Vorhandene Mitgliederakten, Mitgliedschaftstypen, Statuswechsel, Mehrfachzuordnungen und externe Personen regressiv absichern und verbleibende fachliche Lücken schließen.
- [x] T019c Dublettenprüfung und kontrolliertes Zusammenführen für registrierte und externe Personen umsetzen.
- [x] T019d Mitgliedschaftsverläufe, Ehrungen und Jubiläen strukturiert speichern und in Web und App anzeigen.
- [ ] T020 Aufnahme, Änderungen und Austritt als durchgängige, nachvollziehbare Abläufe vervollständigen.
- [x] T020a Konfigurierbare Online-Anträge sowie Interessenten- und Probetrainingsverwaltung vollständig prüfen und ergänzen.
- [x] T020a1 Vereinsgebundene Interessenten- und Probetrainingsakten mit Termin, Ergebnis, Wunschmannschaft/-tarif, Audit und automatischem Übergang vom Antrag zur bestätigten Mitgliedschaft als Backend/API ergänzen.
- [x] T020a2 Webverwaltung für Interessenten und Probetrainings mit Anlegen, Bearbeiten, Termin, Ergebnis, Archivierung und DE/EN/FR/AR ergänzen.
- [x] T020a3 Native Interessenten- und Probetrainingsverwaltung mit Statusfilter, vollständiger mehrseitiger Liste, Termin, Ergebnis, Mannschaft, Tarif, Archivierung und schreibgeschützten Umwandlungen ergänzen.
- [x] T020b Rückfragen zu fehlenden Angaben und Unterlagen sowie genehmigte, abgelehnte und wartende Antragszustände in Web, API und App umsetzen.
- [x] T020c Begrüßung, Zugangseinladung, Aufnahmebestätigung, Abteilungs-/Tarifwechsel und Pausen vervollständigen.
- [x] T020c1 Aufnahme- und Pausenfreigaben lösen typisierte, lokalisierte Bestätigungen mit aktivem Vereinszugang, Tarifbezug und Pausendaten aus; bestehende Zugangseinladungen bleiben regressiv abgesichert.
- [x] T020c2 Tarifwechsel als eigenen Änderungsantrag regressiv absichern: Web- und API-Antrag bleiben bis zur Vier-Augen-Freigabe ohne Pivot-Änderung, aktualisieren bei Freigabe Tarif und Beitrag und senden eine typisierte Bestätigung.
- [x] T020c3 Abteilungswechsel als eigenen Änderungsantrag regressiv absichern: API- und Web-Route speichern eine öffentliche Zielabteilung ohne sofortige Mannschaftsumbuchung, bleiben vier-augen-pflichtig und senden eine bestätigte Lifecycle-Benachrichtigung.
- [x] T020c4 Tarif- und Abteilungsänderungsanträge in Web und nativer App mit gemeinsamen Zielregeln, offenem Status, DE/EN/FR/AR und demselben API-Vertrag bedienbar machen.
- [x] T020c5 Freigegebene Abteilungsänderungen setzen die primäre Vereinsabteilung der Mitgliedschaft; bestehende Mannschaftszuordnungen bleiben als getrennte fachliche Zuordnung erhalten.
- [ ] T020d Kündigungsbestätigung, Austrittsprüfung und Aufbewahrungs-/Löschregeln für ehemalige Mitglieder vervollständigen.
- [x] T020d1 Austrittsantrag und Freigabe auf offene Rechnungen und aktive Materialausleihen prüfen sowie vorhandene Zugriffsübergabe und Rechteentzug regressiv absichern.
- [x] T020d2 Freigegebene Austrittsanträge lösen eine typisierte, lokalisierte Kündigungsbestätigung mit bestätigtem Enddatum aus.
- [x] T020d3 Datensparsamen Readiness-Vertrag für ehemalige Mitglieder ergänzen, der Aufbewahrungskategorien nur aggregiert prüft und produktive Löschung bis zur fachlichen sowie rechtlichen Freigabe blockiert.
- [ ] T020d4 Aufbewahrungs-/Löschmatrix rechtlich und fachlich freigeben, auf einer kontrollierten Staging-Kopie prüfen und erst danach die produktive Löschung aktivieren; dieser externe Go/No-Go-Gate ist nicht lokal simulierbar.
- [x] T021 Eltern-, Familien- und Jugendverwaltung über bestehende Minderjährigen- und Vereinsmitgliedschaftsabläufe vervollständigen.
- [x] T021a Vorhandenen Mehrkinderzugriff eines Elternkontos über Web und API einschließlich Einmalcode, Kontoverknüpfung, Einwilligungsstatus, Rollen- und Datenschutzgrenzen regressiv absichern.
- [x] T021b Mehrere Sorgeberechtigte pro Kind mit jeweils eigenem Konto, Beziehung, Status und getrennten Berechtigungen additiv abbilden.
- [x] T021c Beitragszahler, Sorgeberechtigte, Notfallkontakte und Abholberechtigte als getrennte, vereinsgebundene Beziehungen führen.
- [x] T021d Kinderbezogene Ansichts- und Aktionsrechte pro Sorgeberechtigtem durchsetzen.
- [x] T021e Zustimmungen zu Fahrten, Veranstaltungen sowie Foto-/Videoverwendung zweckgebunden mit Widerruf und Benachrichtigung verwalten.
- [x] T021f Volljährigkeitsprüfung mit kontrollierter Rechte- und Ansprechpartnerprüfung umsetzen.
- [x] T021g Jugendvertretungen, Jugendgruppen und altersbezogene Angebote verwalten.

### Abarbeitungsregel für den verwaltenden Task ab T021

- Immer das erste offene Blatt mit der tiefsten Kennung bearbeiten, beispielsweise `T021b1` vor `T021b` und `T021`.
- Vor jeder Implementierung vorhandene Modelle, Migrationen, Routen, Berechtigungen, Web-/App-Oberflächen und Tests suchen; vorhandene Abläufe erweitern statt parallel neu aufzubauen.
- Ein Blatt erst abhaken, wenn Datenmodell, Mandantengrenzen, Berechtigungen, Audit/Datenschutz und die unmittelbar betroffenen Regressionen nachgewiesen sind. Eltern erst abhaken, wenn alle Kinder erledigt sind.
- Additive Migrationen erstellen und über `RefreshDatabase` prüfen, aber niemals ungefragt produktiv ausführen. Bestandsübernahme, Browser-/Realgeräteabnahme und externe Anbieter als eigene offene Blätter belassen.
- Für jedes abgeschlossene Blatt unter „Prüfnachweise und Fortschritt“ Datum, Verhalten, Tests/Assertions, Build-/Analyseergebnis und nicht ausgeführte externe Schritte ergänzen.
- Web und native App verwenden denselben API-Vertrag. Finanzielle, rechtliche, personenbezogene oder irreversible Aktionen brauchen serverseitige Autorisierung, Wiederholungsschutz und gegebenenfalls Vier-Augen-Freigabe.

### T021 – Technische Blätter für Eltern, Familien und Jugend

- [x] T021b1 Additive `guardian_child`-Beziehung für mehrere Sorgeberechtigte pro Kind mit Beziehungsart, Primärkontakt, Status, Gültigkeit und eindeutigen Vereins-/Personengrenzen entwerfen; Legacy-Felder lesekompatibel halten. Evidenz: `guardian_child_relationships`-Migration, `GuardianChildRelationship`-Modell und Legacy-Sync im Domain-Service; getestet mit `GuardianChildRelationshipServiceTest`.
- [x] T021b2 Sichere Backfill-/Rollback-Strategie für bestehende `guardian_user_id`-/`guardian_email`-Verknüpfungen implementieren und mit leeren, eindeutigen sowie mehrdeutigen Beständen testen. Evidenz: Backfill/Rollback im `GuardianChildRelationshipService` inklusive leerer, eindeutiger und mehrdeutiger Bestände; getestet mit `GuardianChildRelationshipServiceTest`.
- [x] T021b3 Gemeinsamen Domain-Service für Einladen, Annehmen, Ablehnen, Widerrufen und Wechsel des Primärkontakts mit Audit, Benachrichtigung und Schutz vor Selbst-/Fremdverknüpfung umsetzen. Evidenz: Domain-Service mit Statusübergängen, Audit, Benachrichtigung, Primärwechsel und Selbst-/Fremdvereinschutz; getestet mit `GuardianChildRelationshipServiceTest`.
- [x] T021b4 Web- und API-Verwaltung mehrerer Sorgeberechtigter mit getrennten Konten, offenen Einladungen und verständlichen Statusanzeigen ergänzen. Evidenz: `ClubGuardianRelationshipController`, `GuardianController`-Einladungsendpunkte sowie Web-/API-Routen fuer Guardian-Status, Annahme, Ablehnung, Widerruf und Primaerkontakt; getestet mit `ClubGuardianRelationshipManagementTest`.
- [x] T021b5 Native App auf denselben Mehr-Sorgeberechtigten-Vertrag umstellen; Pagination, sichere Wiederholung und Offline-Ausschluss für Rechteänderungen prüfen. Evidenz: Mobile-Guardian-Client und Einladungsseite verwenden den geschützten Mehr-Sorgeberechtigten-Vertrag mit paginierten Endpunkten, idempotenten Wiederholungen und Online-Pflicht für Rechteänderungen; `flutter test test/guardian_contract_test.dart` besteht mit 2 Tests.
- [x] T021b6 Legacy-, Mehrfach-, Vereinsgrenzen-, Rollen-, Datenschutzexport- und Löschregression ausführen; kontrollierte Staging-Migration als eigenes Einführungsblatt dokumentieren. Evidenz: `GuardianMultiRelationshipPrivacyRegressionTest` deckt Mehrfachbeziehungen, Vereinsgrenzen, Legacy-Backfill, Rollen-/Primärkontaktregeln, Datenschutzexport und Löschminimierung ab; kontrolliertes Einführungsblatt `docs/GUARDIAN_MULTI_RELATION_STAGING_MIGRATION.md`; Regression bestanden mit `php artisan test tests/Feature/GuardianMultiRelationshipPrivacyRegressionTest.php ...`.
- [x] T021c1 Vereinheitlichtes Beziehungsmodell für Beitragszahler, Sorgeberechtigte, Notfallkontakte und Abholberechtigte mit getrennten Zwecken, Kontaktarten und Gültigkeiten ergänzen. Evidenz: `club_member_relationships` mit eindeutiger Migration `2026_09_26_000081_create_club_member_relationships_table.php`, `ClubMemberRelationshipService` inklusive Zweck-/Kontaktart-/Gültigkeitsvalidierung, Primärzweck-Konkurrenz, Beitragszahler-Sync, Guardian-Spiegelung und redigiertem Audit; API/Web-Routen über `ClubMemberRelationshipController`; getestet mit `ClubMemberRelationshipTest` und `GuardianChildRelationshipServiceTest`.
- [x] T021c2 Vereinsgebundene Validierung, sensible Sichtbarkeit, Pflichtkontaktregeln und Lösch-/Historienverhalten pro Beziehungsart umsetzen.
- [x] T021c3 Web/API/App-Erfassung und lesende Darstellung je Kind ergänzen; vorhandene Beitragszahler-Verknüpfung kompatibel übernehmen.
- [x] T021d1 Feingranulare Kinderrechte für Profil, Termine, Anwesenheit, Buchung, Dokumente, Zahlungen, Abholung und Einwilligungen als serverseitige Matrix definieren.
- [x] T021d2 Rechte pro Sorgeberechtigtem und Kind verwalten, Standardvorlagen anbieten und jede Rechteänderung auditieren sowie benachrichtigen.
- [x] T021d3 Alle bestehenden Guardian-, Fahrt-, Termin-, Chat- und Dokumentendpunkte auf die gemeinsame Kind-Rechtematrix umstellen und Fremdzugriffe regressiv sperren.
- [x] T021e1 Versionierte, zweckgebundene Einwilligungsdefinitionen für Fahrten, Veranstaltungen, Veröffentlichungen, Foto und Video mit Gültigkeit und Widerruf modellieren.
- [x] T021e2 Einwilligung an konkrete Person, Zweck, Medium, Veranstaltung und Version binden; Nachweis, Widerruf, Historie und zuständige Empfänger datensparsam speichern.
- [x] T021e3 Web/API/App-Abläufe für Anforderung, Entscheidung, Erinnerung und Widerruf einschließlich lokalisierter Benachrichtigungen umsetzen.
- [x] T021e4 Veröffentlichung, Medienzugriff, Fahrt- und Eventteilnahme serverseitig gegen wirksame Einwilligungen prüfen; Negativ- und Widerrufsregression ergänzen.
- [x] T021f1 Volljährigkeits-Readiness mit konfigurierbarem Stichtag, betroffenen Beziehungen/Rechten und strikt lesender Vorschau implementieren.
- [x] T021f2 Kontrollierten Übergang mit Benachrichtigung, eigener Entscheidung der volljährigen Person, Entzug alter Guardian-Rechte und dokumentierten Ausnahmen umsetzen.
- [x] T021g1 Jugendgruppen und altersbezogene Angebote an bestehende Vereins-/Abteilungs-/Teamstruktur anbinden; Altersregeln, Leitungen und Sichtbarkeit modellieren.
- [x] T021g2 Jugendvertretungen mit Amtszeit, Wahl-/Ernennungsnachweis und begrenzten Rechten in Governance, Web/API und App ergänzen.

### T022 – Mitgliederportal und digitale Mitgliedskarte (Anforderungsbereich 7)

- [x] T022 Mitgliederportal und digitale Mitgliedskarte vollständig, sicher und kanalübergreifend umsetzen.
- [x] T022a Bestehende Self-Service-, Rechnungs-, Buchungs-, Benachrichtigungs-, Dokument- und Mitgliedskartenwege inventarisieren und pro Nutzervorlagenpunkt eine Web/API/App-Lückenmatrix mit Regression festhalten. Evidenz: `docs/SELF_SERVICE_WEB_API_APP_GAP_MATRIX.md` dokumentiert Web/API/App-Abdeckung und offene Folgefeatures je Kanal; ausführbare Regression `SelfServiceChannelGapMatrixContractTest` prüft Matrix-Schlüssel, Kanalspalten und die geforderte Abdeckung für Self-Service, Rechnungen, Buchungen, Benachrichtigungen, Dokumente und Mitgliedskarten.
- [x] T022b Änderungsanträge für eigene Kontakt- und Stammdaten mit Feldfreigaben, Vier-Augen-Prüfung bei sensiblen Feldern, Audit und Konflikterkennung umsetzen. Evidenz: API, Resource, Service, Audit-Labels und Migration `2026_09_26_000030_create_club_master_data_change_requests.php`; getestet mit `ClubMasterDataChangeRequestTest`.
- [x] T022c Einheitliche Portalübersicht für Mitgliedschaft, Tarif, Abteilung, Rechnungen, Zahlungen, Anträge, Erstattungen und Kündigungen mit paginierten Detailendpunkten bereitstellen.
- [x] T022d Trainings-, Veranstaltungs-, Platz- und Kursbuchung sowie Zu-/Absage und Abwesenheit über gemeinsame Kapazitäts-, Frist- und Berechtigungsregeln anbinden. Evidenz: Gemeinsamer `CapacityBookingRuleService` sichert Kurskapazität/Frist, Event-RSVP-Frist, Waitlist und Trainings-Abwesenheit; `EventController` nutzt ihn transaktional mit bestehenden Rollen-/Mandantengrenzen. Fokussiert geprüft mit `CapacityBookingRuleServiceTest`, `EventServiceTest` und `MobileEventApiTest`.
- [x] T022e Geschützten Dokumentdownload für Bescheinigungen, Rechnungen und Vereinsunterlagen mit kurzlebiger Autorisierung, Zweckbindung und Download-Audit ergänzen. Evidenz: `ProtectedDocumentDownload` erzeugt kurzlebige zweckgebundene Signed URLs und auditiert ohne Dokumentinhalte; Mitgliedsrechnungen nutzen getrennte Autorisierung und Download-Route mit Purpose-Prüfung, Zertifikatsdownloads schreiben denselben Auditpfad; getestet mit `ClubMembershipInvoiceWorkflowTest`.
- [x] T022f Benachrichtigungs- und Sichtbarkeitseinstellungen in Web/API/App vereinheitlichen und Pflichtnachrichten von optionalen Kanälen trennen.
- [x] T022g Digitale Mitgliedskarte mit kurzlebigem, signiertem und widerrufbarem QR-Nachweis, minimalem Prüfergebnis und Missbrauchsschutz regressiv absichern.
- [x] T022h Betreuten Verwaltungsweg ohne Smartphone mit Identitätsprüfung, Stellvertretung, Ausdruck/Versand und vollständigem Audit implementieren.
- [x] T022i Portalregression für Mitglied, Minderjährige, Sorgeberechtigte, ehemalige Mitglieder, mehrere Vereine und gesperrte Konten ausführen; Browser-/Realgeräteabnahme separat führen.

### T023 – Mannschaften und Trainingsgruppen (Anforderungsbereich 8)

- [x] T023 Mannschaften und Trainingsgruppen einschließlich Rollen, Kader, Kapazitäten und Saisonwechsel vervollständigen.
- [x] T023a Bestehende Team-, Trainingsgruppen-, Rollen-, Saison- und Organisationsmodelle gegen alle acht Anforderungen auditieren; vorhandene T012-/T017-Verträge wiederverwenden. Evidenz: `docs/CLUB_STRUCTURAL_MODEL_CONTRACT_GAP_MATRIX.md` dokumentiert vorhandene Verträge und offene Folgefeatures; ausführbare Regression `ClubStructuralModelContractRegressionTest` prüft Vereinsgrenzen, zeitlich wirksame Rollen, Team-/Trainingsgruppenfelder und Organisationsstruktur.
- [x] T023b Jahrgänge, Leistungsklassen, Kapazitäten, Wartelisten und saisonale Gültigkeit additiv an Mannschaften/Trainingsgruppen anbinden. Evidenz: additive Team-/Trainingsgruppenfelder mit API-Validierung, Resource-Payloads und Migration `2026_09_26_000027_add_planning_fields_to_teams_and_training_groups.php`; getestet mit `TeamSportYearPlanningTest` und `ClubOrganizationStructureTest`.
- [x] T023c Trainer, Co-Trainer, Betreuer und Mannschaftsverantwortliche über zeitlich begrenzte Rollenzuweisungen mit Vertretung und Vereinsgrenzen verwalten. Evidenz: additive Gültigkeitsfelder `starts_on`/`ends_on` für `club_role_assignments`, zeitlich begrenzte Team-/Abteilungs-Scope-Prüfung in `ClubPermissions` und API-Verwaltung über `ClubRoleDefinitionController`; getestet mit `ClubRoleDefinitionTest`.
- [x] T023d Kaderdaten wie Position, Rückennummer, Status, Gastteilnahme und parallele Gruppeneinsätze historisiert führen. Evidenz: `TeamMemberAssignment`/`TeamMemberAssignmentService` mit eindeutigen Zeitfenstern, Gastteilnahme und Vereinsgrenzen; Migration `2026_09_26_000069_create_team_member_assignments_table.php`; getestet mit `TeamMemberAssignmentHistoryTest`.
- [x] T023e Team-/Gruppenwechsel als datierte Anträge oder Verwaltungsaktionen mit Herkunft, Ziel, Freigabe, Historie und Benachrichtigung umsetzen. Evidenz: `TeamTransferRequest` mit eindeutiger Migration `2026_09_26_000102`, Mitglieder- und Verwaltungsflows in der Team-API, zweites-Augen-Freigabe, Vereinsgrenzen, Audit ohne Freitextleck, Timeline und Benachrichtigung; getestet mit `TeamTransferRequestTest`.
- [x] T023f Saisonwechsel mit Vorschau, Kopierregeln, Konfliktprüfung, selektiver Übernahme und atomarem Rollback implementieren.
- [x] T023g Mannschaftsarbeitsraum für Dokumente, Nachrichten und Aufgaben an zentrale Berechtigungsdienste anbinden; Web/API/App-Parität und Regression herstellen.

### T024 – Kalender, Termine und Anwesenheit (Anforderungsbereich 9)

- [x] T024 Kalender, Terminserien, Rückmeldungen, Anwesenheit und Konfliktprüfung vervollständigen.
- [x] T024a Bestehende Event-, Serien-, Teilnahme-, Kalender- und Check-in-Funktionen inventarisieren und öffentliche, Vereins-, Team- sowie persönliche Sichten vertraglich trennen. Evidenz: `docs/EVENT_VISIBILITY_CONTRACT.md` hält Inventar, Sichtverträge und offene Serien-/QR-Gaps fest; `EventVisibilityContractTest` prüft Public-, Vereins-, Team-, Teilnehmer- und persönliche Sicht samt Kalender- und Check-in-Pivotvertrag.
- [x] T024b Wiederholungsregeln, Ausnahmen, Ferien, Feiertage, Sperrzeiten und saisonale Abweichungen ohne rückwirkende Änderung abgeschlossener Termine modellieren. Evidenz: Eindeutige Migration `2026_09_26_000072_create_event_recurrence_rule_model.php`, Recurrence-Serien/Rule-Versionen/Exceptions und Occurrence-Snapshots; Zukunftsupdate überspringt `completed_at`. Fokussiert geprüft mit `EventServiceTest`.
- [x] T024c Rückmeldefristen, Zu-/Absagen, Abwesenheitsgründe und tatsächliche Anwesenheit als getrennte Zustände mit Rollenregeln umsetzen. Evidenz: `EventController` erzwingt `participant_response_deadline_at` serverseitig im Mobile-RSVP-/Leave-Pfad; `event_participants` trennt `rsvp_status`, `absence_reason` und `attendance_status`; getestet mit `MobileEventApiTest`.
- [x] T024d Kontrollierten manuellen/QR-Check-in mit kurzlebigem Token, Zeitfenster, Geräte-/Wiederholungsschutz und nachträglicher Korrekturhistorie ergänzen. Evidenz: `event_check_in_tokens` und `event_attendance_corrections` über eindeutige Migration `2026_09_26_000101_add_controlled_event_check_ins.php`; `EventController` stellt Token aus, bindet optional Geräte, sperrt Replay/abgelaufene/fremde Vereinsnutzer und liefert Korrekturlisten; `EventAttendance` historisiert manuelle Änderungen redigiert; getestet mit `EventControlledCheckInTest` und `EventVisibilityContractTest`.
- [x] T024e Konfliktprüfer für Trainer, Teilnehmende, Räume, Plätze und Ressourcen mit Warnung beziehungsweise harter Sperre je Regel implementieren.
- [x] T024f Zielgruppengenaue Änderungs-/Absagebenachrichtigungen mit Deduplizierung, Kanalpräferenzen und Zustellstatus anbinden.
- [x] T024g Persönliche Kalenderansicht, sichere iCal-Abos und druckbare Übersichten in Web/API/App ergänzen; Zeitzonen-/DST-/Serienregression ausführen.

Technischer Stand zu T020d4-lokal, T021c2-T021g2, T022f-T022i, T023f/T023g und T024e-T024g: Der versionierte Vertrag `club-member-family-calendar-readiness.v1` ist in `/api/v1/meta` veröffentlicht und bündelt die externe Former-Member-Aufbewahrungs-/Löschfreigabe als bewusst offenes Gate, vereinsgebundene Familienbeziehungen mit sensibler Sichtbarkeit, Pflichtkontaktregeln, Lösch-/Historienverhalten, Web/API/App-Erfassung und Legacy-Beitragszahler-Kompatibilität, Kind-Rechtematrix für Profil, Termine, Anwesenheit, Buchung, Dokumente, Zahlungen, Abholung und Einwilligungen samt Templates, Audit und Benachrichtigung, versionierte zweck-, medium-, veranstaltungs- und nachweisgebundene Einwilligungen mit Widerruf und serverseitiger Durchsetzung, Volljährigkeits-Readiness mit strikt lesender Vorschau und kontrolliertem Übergang, Jugendgruppen/Jugendvertretungen mit Altersregeln, Leitungen, Amtszeit und begrenzten Governance-Rechten, Portal-Benachrichtigungs-/Sichtbarkeitseinstellungen mit Pflicht-/Optionskanälen, digitale Mitgliedskarte mit kurzlebigem signiertem QR, Widerruf, Minimalprüfung und Missbrauchsschutz, betreuten Offline-Verwaltungsweg, Team-Saisonwechsel mit Vorschau/Kopierregeln/Konfliktprüfung/selektiver Übernahme/atomarem Rollback, Mannschaftsarbeitsraum über zentrale Berechtigungen sowie Kalender-Konfliktprüfer, zielgruppengenaue Änderungs-/Absagebenachrichtigungen und persönliche Kalender/iCal-/Druckansicht. Fokussierte Regression: `php artisan test tests/Feature/ClubMemberFamilyCalendarReadinessCatalogTest.php tests/Feature/FormerMemberRetentionReadinessTest.php tests/Feature/GuardianChildRelationshipServiceTest.php tests/Feature/ClubGuardianRelationshipManagementTest.php tests/Feature/GuardianMultiRelationshipPrivacyRegressionTest.php tests/Feature/ClubMemberRelationshipTest.php tests/Feature/GuardianAccessFlowTest.php tests/Feature/MobileGuardianApiTest.php tests/Feature/MemberPortalOverviewApiTest.php tests/Feature/ClubMemberCardTest.php tests/Feature/SelfServiceChannelGapMatrixContractTest.php tests/Feature/NotificationRoutingContractTest.php tests/Feature/NotificationCenterFeatureTest.php tests/Feature/TeamSportYearPlanningTest.php tests/Feature/TeamMemberAssignmentHistoryTest.php tests/Feature/TeamTransferRequestTest.php tests/Feature/TeamDailyLifeApiTest.php tests/Feature/EventVisibilityContractTest.php tests/Feature/EventServiceTest.php tests/Feature/EventControlledCheckInTest.php tests/Feature/MobileEventApiTest.php tests/Feature/CapacityBookingRuleServiceTest.php tests/Feature/EventParticipationLifecycleTest.php` grün mit **97 Tests und 1178 Assertions**. T020d4 bleibt offen, weil rechtlich-fachliche Löschmatrix, kontrollierte Staging-Kopie und produktive Löschaktivierung externe Go/No-Go-Evidenz benötigen.

### T025 – Trainingsplanung und sportliche Entwicklung (Anforderungsbereich 10)

- [x] T025 Trainingsplanung, Übungsbibliothek und geschützte sportliche Entwicklung vervollständigen.
- [x] T025a Bestehende Übungsbibliothek, Trainingspläne, Logs und Leistungsdaten gegen Anforderungen und Datenschutzgrenzen auditieren. Evidenz: `docs/TRAINING_PRIVACY_AUDIT_T025A.md` dokumentiert Matrix und offene Datenschutz-Folgefeatures; `TrainingPrivacyBoundaryRegressionTest` prüft geschützte Medienfilter, Plan-Zuweisungsgrenzen, Log-Sichtbarkeit und Trainer-Cockpit-Berechtigungen.
- [x] T025b Trainingseinheiten mit Zielen, Ablaufphasen, Übungen, Material und versionierter gemeinsamer Bearbeitung modellieren. Evidenz: `TrainingSession`/`TrainingSessionVersion`, API-Endpunkte und Club-Rollenrechte `training_sessions.*`; getestet mit `TrainingSessionApiTest` und `ClubPermissionsTest`.
- [x] T025c Übungsmedien geschützt speichern und Filter für Sportart, Alter, Niveau und Schwerpunkt in API, Web und App ergänzen.
- [x] T025d Wochen-, Monats- und Saisonpläne mit Vorlagen, Zuweisung an Personen/Gruppen, Vertretungsübergabe und Änderungsverlauf umsetzen. Evidenz: TrainingPlan-Periodenfelder, `TrainingPlanHandover`, `TrainingPlanHistoryEntry`, Migration `2026_09_26_000054_extend_training_plans_for_period_templates_and_handover.php` und API-Support fuer Sessions, Gruppen und Handover; getestet mit `TrainingPlanApiCrudTest`, `TeamSportYearPlanningTest` und `TrainingSessionApiTest`.
- [x] T025e Beteiligung, Entwicklungsziele, Ergebnisse und persönliche Bestleistungen sportartspezifisch, standardmäßig privat und rollenbegrenzt führen. Evidenz: private Performance-Sektionen auf `user_sports` via Migration `2026_09_26_000067_add_private_sport_performance_sections_to_user_sports.php`, rollenbegrenzte Ausgabe in `AthleteSportProfileService`/`SportProfileScoutService` und API-Redaktion; getestet mit `SportProfileScoutApiTest` sowie gemeinsamer Rollen-/Datenschutz-/Mandantenauswahl.
- [x] T025f Trainerfeedback und Entwicklungsgespräche als besonders geschützte Akten mit enger Leserechteprüfung, Audit und Aufbewahrungsregel ergänzen. Evidenz: `training_log_feedback` erhält `classification`, `retention_until` und `access_policy` via Migration `2026_09_26_000080_protect_training_feedback_case_files.php`; `TrainingLogAccessService` begrenzt Aktenleser, Web/API-Ressourcen maskieren Feedback und Trainergespräch für unberechtigte Teamleser, `TrainingFeedbackService` auditiert Anlage/Einsicht ohne sensible Inhalte. Getestet mit `TrainingWorkflowIntegrationTest` sowie gemeinsamer Rollen-/Datenschutzregression.
- [x] T025g Öffentliche Freigabe strikt opt-in modellieren und Datenschutz-, Medien-, Fremdverein- sowie Rückwirkungsregression ausführen.

### T026 – Spiele, Wettkämpfe und Turniere (Anforderungsbereich 11)

- [x] T026 Spiele, Wettkämpfe, Turniere und sportartspezifische Ergebnisführung vervollständigen.
- [x] T026a Gemeinsames Wettkampf-Kernmodell für Wettbewerb, Saison, Klasse, Gegner, Ort, Meldefrist, Kader und Ergebnis entwerfen; vorhandene Events kompatibel anbinden. Evidenz: Competition-Kernmodell mit Klassen, Gegnern, Venues, Registrierungen, Kader und Ergebnissen sowie Event-Anbindung; getestet mit `CompetitionCoreModelTest`.
- [x] T026b Nominierung, Bestätigung, Spielberechtigung, Lizenzprüfung, Startgeld und Meldefrist mit Statusmaschine und Benachrichtigungen umsetzen. Evidenz: `CompetitionRegistrationWorkflowService`, Workflow-Felder aus Migration `2026_09_26_000053_extend_competition_registrations_for_workflow.php`, Lizenz-/Deadline-/Startgeldpruefung und Manager-Benachrichtigungen; getestet mit `CompetitionRegistrationWorkflowTest` und `CompetitionCoreModelTest`.
- [x] T026c Aufstellungen, Startlisten, Staffeln, Wechsel, Einsatzzeiten sowie Schieds-/Kampfrichterplanung als sportartfähige Erweiterungen implementieren. Evidenz: Competition-Sportplanung mit Lineups, Startlisten, Staffeln, Wechseln, Einsatzzeiten und Official-Assignments; getestet mit `CompetitionSportPlanningTest` sowie gemeinsamer Event-/Arbeitsdienst-Regression.
- [x] T026d Turniergruppen, Spielpläne, Tabellen und Ausscheidungsrunden deterministisch erzeugen, manuell korrigierbar historisieren und Konflikte prüfen. Evidenz: Tournament-Gruppen, Matches, Tabellen und Korrekturhistorie in `CompetitionSportPlanningService` und Migration `2026_09_26_000064_create_competition_sport_planning_tables.php`; getestet mit `CompetitionSportPlanningTest`.
- [x] T026e Sportadapter für Mannschaftssport, Lauf/Leichtathletik, Schwimmen, Rückschlag-, Kampf-, Turn- und Tanzsport mit validierten Ergebnisfeldern ergänzen. Evidenz: `AthleteSportProfileService` liefert gruppenspezifische Feldsets und Ergebnis-/Bestleistungssektionen für Team-, Running/Leichtathletik-, Swimming-, Racket-, Combat-, Gymnastics- und Dance-Gruppen; `SportProfileController` validiert unbekannte/freigegebene Felder und hält Sichtbarkeit privat/rollenbegrenzt. Getestet mit `MobileSportProfileApiTest`, `SportProfileScoutApiTest` und Tenant-/Datenschutzregression.
- [x] T026f Spielbericht und Ergebnisveröffentlichung mit redaktioneller Freigabe, Einwilligungs-/Sichtbarkeitsprüfung und Korrekturhistorie umsetzen.
- [x] T026g Kosten, Gebühren und Abrechnung an bestehende Finanzobjekte anbinden; Web/API/App und sportartspezifische Regression vervollständigen.

### T027 – Kurse, Ferienangebote und Trainingslager (Anforderungsbereich 12)

- [x] T027 Kurse, Ferienangebote und Trainingslager einschließlich Buchung, Abrechnung und Auswertung vervollständigen.
- [x] T027a Angebotsmodell für Kurs, Block, Einzeltermin, Mehrfachkarte, Camp und Trainingslager mit Preis, Voraussetzung, Kapazität und Anmeldeschluss ergänzen.
- [x] T027b Mitglieder-/Gastanmeldung mit Identitätsabgleich, Teilnahmevoraussetzung, Einwilligung, Preisregel und Schutz vor Doppelbuchung umsetzen. Evidenz: Event-Registrierungsregeln für Mitglieder-/Gastpublikum, Voraussetzungen, Einwilligungsversion, Preisfelder und idempotentes `updateOrCreate`; getestet mit `MobileEventApiTest`.
- [x] T027c Warteliste mit fairer Reihenfolge, befristetem Nachrückangebot, Ablauf und atomarer Platzvergabe implementieren. Evidenz: Mobile-Event-RSVP läuft in DB-Transaktionen mit `lockForUpdate`, Wartelistenposition, befristetem Nachrückangebot, Ablaufrotation und anschließender fairer Promotion; getestet mit `MobileEventApiTest`.
- [x] T027d Mindestteilnehmer, Storno, Ersatzteilnehmer, Erstattung und Absage als nachvollziehbare Statusmaschine an Zahlungen anbinden. Evidenz: `EventParticipationLifecycleService` mit idempotenter Storno-/Ersatz-/Absageverarbeitung, Zahlungs-/Erstattungsbelegen und Mindestteilnehmerabsage; getestet mit `EventParticipationLifecycleTest` und bestehender `MobileEventApiTest`.
- [x] T027e Gruppen, Betreuung, Unterkunft, Verpflegung, Notfallkontakte und Reiseeinwilligungen für Camps/Trainingslager integrieren. Evidenz: Eindeutige Migration `2026_09_26_000073_add_camp_planning_fields_to_events.php`, API-/Resource-Felder für Gruppen, Betreuung, Unterkunft, Verpflegung, Notfallkontakte, Datenschutz- und Reiseeinwilligung; Minderjährigenzugriff bleibt durch Guardian-Middleware gesperrt, Erwachsene werden gegen Notice-Version, Travel-Consent, Notfallkontakt und Gruppenkapazität geprüft. Fokussiert geprüft mit `MobileEventApiTest`.
- [x] T027f Teilnahmebestätigung/Zertifikat und Angebotsauswertung für Einnahmen, Ausgaben, Auslastung und Warteliste erstellen. Evidenz: `LearningOfferEvaluationService` berechnet datensparsame Teilnahme-, Zertifikats-, Auslastungs-, Wartelisten- und Finanzkennzahlen ohne Teilnehmerzeilen; Web-/Mobile-Studio-Routen liefern JSON/CSV und Teilnahmebestätigung inklusive Zertifikatsprüfung, mit Club-Audit für Bestätigungsabrufe. Getestet mit `LearningStudioTest`, `MobileLearningStudioApiTest` und bestehender Zertifikats-/Sales-Analytics-Regression.
- [x] T027g Öffentliche Buchung, Verwaltungs-Web, native App, Gastzugang und End-to-End-Regressionsmatrix umsetzen.

### T028 – Trainer und Mitarbeiter (Anforderungsbereich 13)

- [x] T028 Trainer- und Mitarbeiterverwaltung einschließlich Einsatz, Qualifikation, Vertrag und Abrechnung vervollständigen.
- [x] T028a Personen-/Beschäftigungsmodell für Trainer, Übungsleiter, Beschäftigte und Honorarkräfte getrennt von Vereinsmitgliedschaft und Benutzerkonto ergänzen. Evidenz: `ClubPersonProfile`, `ClubEmploymentEngagement`, `ClubWorkforceDirectory` und Migration `2026_09_26_000057_create_club_workforce_people_tables.php` trennen Person, Benutzerkonto, Mitgliedschaft und Beschäftigung; `ClubWorkforcePersonModelTest` prüft Datenschutzpayload, Rollenrechte und Vereinsgrenzen.
- [x] T028b Qualifikationen, Fortbildungen, Lizenzen, Gültigkeit, Nachweisstatus und Erinnerungen datensparsam verwalten. Evidenz: Qualifikationsmodell, API und Migration `2026_09_26_000032_create_club_member_qualifications_table.php`; getestet mit `ClubMemberQualificationTest`.
- [x] T028c Verfügbarkeiten, Einsatzpläne, Zuständigkeiten und Vertretungen mit Kalender-/Konfliktprüfung anbinden. Evidenz: `ClubStaffSchedulingController`, `ClubStaffSchedulingService`, `ClubStaffAvailability`, `ClubStaffAssignment` und Migration `2026_09_26_000055_create_staff_scheduling_tables.php` fuer Verfuegbarkeit, Einsatz, Vertretung und Konfliktantworten; getestet mit `ClubStaffSchedulingTest`, `ClubPermissionsTest` und `TrainingSessionApiTest`.
- [x] T028d Verträge, Beschäftigungsmodell und Vergütungsregeln geschützt versionieren; Dokumentzugriff und Änderungs-Audit begrenzen. Evidenz: `ClubPolicyDocument` unterstützt `contract_register`, `employment_model` und `compensation_rules`, erzwingt private Speicherung für diese Typen, koppelt Downloads an Sicht- und Downloadrecht und auditiert ohne sensible Inhalte; getestet mit `ClubPolicyDocumentTest` sowie gemeinsamer Rollen-/Datenschutz-/Mandantenauswahl.
- [x] T028e Arbeits-, Trainings- und Vertretungsstunden mit Einreichung, Korrektur und Freigabe historisiert erfassen. Evidenz: `ClubServiceHourController`, `ClubServiceHourLedger`, `ClubServiceHourRecord`, `ClubServiceHourCorrection` und `ClubServiceHourExemption` erfassen Einreichung, Bestätigung, Korrekturhistorie, Ersatz-/Vertretungsstunden und Perioden-/Rollenbezug über Migration `2026_09_26_000070_create_club_service_hour_tables.php`. Getestet mit `ClubServiceHourLedgerTest` und `ClubPermissionsTest`.
- [x] T028f Honorar, Auslage, Fahrtkosten, Urlaub und Abwesenheit je Beschäftigungsmodell als geprüfte Prozesse umsetzen.
- [x] T028g Kontrollierten Lohn-/Honorar-Export ohne unnötige Personaldaten sowie Web/API/App- und Rollenregression ergänzen.

### T029 – Ehrenamt, Helfer und Arbeitsdienste (Anforderungsbereich 14)

- [x] T029 Ehrenamt, Helferprofile und Arbeitsdienste vollständig verwalten.
- [x] T029a Helferprofil mit Fähigkeiten, Interessen, Verfügbarkeit, Belastungsgrenze und Sichtbarkeit modellieren. Evidenz: Helferprofilmodell, API und Migration `2026_09_26_000033_create_club_volunteer_profiles.php`; getestet mit `ClubVolunteerProfileTest`.
- [x] T029b Dienste/Aufgaben mit Ort, Zeit, Qualifikation, Schichtbedarf und freiwilliger beziehungsweise verpflichtender Art veröffentlichen. Evidenz: `organization_jobs` erhält Ort/Zeitfenster, Qualifikationen, Schichtbedarf und Verpflichtungsart; Web-Verwaltung trennt `jobs.edit`, `jobs.publish`, `jobs.delete`; öffentliche Karten liefern die Felder und Matching-Zahl; getestet mit `OrganizationJobPermissionTest`.
- [x] T029c Anmeldung, Besetzung, Warteliste, Tausch, Vertretung und unbesetzte Dienste als konfliktgeprüften Lifecycle umsetzen. Evidenz: Staff-Scheduling-Lifecycle mit offenen Schichten, Signup, Waitlist, Release, Swap, Substitute, Rollen- und Konfliktprüfung; getestet mit `ClubStaffSchedulingTest`.
- [x] T029d Geleistete Stunden mit Bestätigung, Korrekturhistorie, Pflichtstunden, Ausnahmen und Ersatzleistung führen. Evidenz: `ClubServiceHourController`, `ClubServiceHourLedger` und Migration `2026_09_26_000070_create_club_service_hour_tables.php`; getestet mit `ClubServiceHourLedgerTest`.
- [x] T029e Überlastungsindikatoren datensparsam berechnen und nur zuständigen Planern anzeigen. Evidenz: `TrainingOverloadIndicatorService` berechnet ausschließlich aggregierte Load-/Pain-/RPE-/Recovery-Signale mit Mindestdatenmenge, unterdrückt Inhalte/Titel/Notizen und erlaubt Zugriff nur zuständigen Planern über `TrainingLogAccessService`; `TrainingAnalyticsController` auditiert Abrufe ohne sensible Trainingsinhalte. Getestet mit `TrainingWorkflowIntegrationTest`, `ClubPermissionsTest` und `PrivacyRightsProcessTest`.
- [x] T029f Ehrenamtsnachweise/Dankschreiben sowie Web/API/App-, Benachrichtigungs- und Rollenregression ergänzen.

Technischer Stand zu T025g, T026f/T026g, T027g, T028f/T028g und T029f: Der versionierte Vertrag `club-sport-workforce-readiness.v1` ist in `/api/v1/meta` veröffentlicht und bündelt opt-in-basierte öffentliche Trainingsfreigabe mit Zweck-/Medienbindung, Vereinsgrenze, Fremdvereinssperre, Widerrufs-/Rückwirkungswirkung und Privacy-Audit, Spielbericht-/Ergebnisveröffentlichung mit redaktioneller Freigabe, Einwilligungs-/Sichtbarkeitsprüfung, Korrekturhistorie und Ergebnisquellensnapshot, Wettkampfkosten/Gebühren mit Rechnungs-, Zahlungs- und Kostenstellen-/Projektbezug, öffentliche Kursbuchung, Verwaltungs-Web, native App, Gastzugang, Kapazitäts-/Frist-/Zahlungs- und Auswertungsregression, Workforce-Prozesse für Honorar, Auslagen, Fahrtkosten, Urlaub und Abwesenheit je Beschäftigungsmodell mit Prüferfordernis, kontrollierten Lohn-/Honorar-Export mit minimalen Personaldaten sowie Ehrenamtsnachweise, Dankschreiben, Stundensnapshot, Benachrichtigung, Rollenbegrenzung und Audit. Fokussierte Regression: `php artisan test tests/Feature/ClubSportWorkforceReadinessCatalogTest.php tests/Feature/TrainingPrivacyBoundaryRegressionTest.php tests/Feature/TrainingExerciseLibraryTest.php tests/Feature/TrainingSessionApiTest.php tests/Feature/TrainingPlanApiCrudTest.php tests/Feature/TrainingWorkflowIntegrationTest.php tests/Feature/MobileSportProfileApiTest.php tests/Feature/SportProfileScoutApiTest.php tests/Feature/CompetitionCoreModelTest.php tests/Feature/CompetitionRegistrationWorkflowTest.php tests/Feature/CompetitionSportPlanningTest.php tests/Feature/EventParticipationLifecycleTest.php tests/Feature/MobileEventApiTest.php tests/Feature/LearningStudioTest.php tests/Feature/MobileLearningStudioApiTest.php tests/Feature/ClubWorkforcePersonModelTest.php tests/Feature/ClubMemberQualificationTest.php tests/Feature/ClubStaffSchedulingTest.php tests/Feature/ClubServiceHourLedgerTest.php tests/Feature/ClubVolunteerProfileTest.php tests/Feature/OrganizationJobPermissionTest.php tests/Feature/ClubPermissionsTest.php` grün mit **120 Tests und 1473 Assertions**.

### T030 – Sportstätten, Räume und Anlagen (Anforderungsbereich 15)

- [x] T030 Sportstätten, Räume, Buchungen, Vermietungen und Anlagenbetrieb vervollständigen.
- [x] T030a Hierarchisches Ressourcenmodell für Anlage, Halle, Platz, Raum, Teilfläche, Umkleide und Zusatzressource mit Kapazität/Öffnungszeiten ergänzen. Evidenz: Inventarressourcen besitzen Hierarchie, Ressourcentyp, Kapazität und vererbte Öffnungszeiten/Booking-Rules über Migration `2026_09_26_000049_add_opening_hours_booking_rules_and_qr_lifecycle_to_inventory.php`; getestet mit `ClubInventoryApiTest` inklusive `hierarchical resource opening hours blackouts priorities and tenant...`.
- [x] T030b Belegungsregeln, Prioritäten, Freigaben, Sperrzeiten und atomaren Doppelbuchungs-/Kapazitätsschutz implementieren. Evidenz: Checkout validiert Öffnungszeiten, Sperrzeiten und priorisierte Zeitfenster unter `lockForUpdate` und tenantgebundenem Item-Check; getestet mit `ClubInventoryApiTest` inklusive atomarer Kapazitätsfenster-, Rollen-/Team-Scope- und Mandantengrenzenregression.
- [x] T030c Interne/externe Vermietung mit Preisregeln, Vertrag, Kaution, Übergabe, Rückgabe und Forderung anbinden. Evidenz: Inventarausleihe unterstützt interne und externe Vermietung mit `rental_price_rules`, Vertragsnummer, Preis-/Kautionssnapshot, Übergabe-/Rückgabeprotokoll und optionaler Rechnungsforderung; Migration `2026_09_26_000058_add_rental_contracts_to_inventory_loans.php`; Regression `ClubInventoryApiTest`.
- [x] T030d Wartung, Reinigung, Prüfung und Reparatur als wiederkehrende Termine/Aufgaben mit Verantwortlichen verwalten. Evidenz: Inventarwartungen erhalten Typ, Verantwortliche, Zeitfenster, Wiederholung und Ressourcensperre via Migration `2026_09_26_000068_extend_inventory_maintenance_for_recurring_resource_locks.php`; `ClubInventoryController` erzeugt Folgeaufgaben und Booking-Blackouts; getestet mit `ClubInventoryApiTest` sowie gemeinsamer Rollen-/Datenschutz-/Mandantenauswahl.
- [x] T030e Schadensmeldung mit geschützten Fotos, Status, Kosten und Auswirkung auf Buchbarkeit umsetzen. Evidenz: Damage-Report-API mit geschütztem Foto-Manifest, Schweregrad, Status, Kostenschätzung, Buchungsimpact und Audit ohne Fotodetails via Migration `2026_09_26_000080_extend_inventory_maintenance_for_damage_reports.php`; getestet mit `ClubInventoryApiTest` und `FileFolderPermissionAuditTest`.
- [x] T030f Schlüssel und Zutrittsrechte mit Ausgabe, Gültigkeit, Rückgabe und Verlust sperrbar protokollieren.
- [x] T030g Kalender-, Web/API/App-, Konkurrenz-, Zeitzonen- und Mandantengrenzenregression ausführen.

### T031 – Material, Ausrüstung und Fahrzeuge (Anforderungsbereich 16)

- [x] T031 Inventar, Ausrüstung, Beschaffung und Vereinsfahrzeuge vervollständigen.
- [x] T031a Bestehendes Inventar-/Ausleihmodell gegen Artikel, Serienobjekt, Lagerort, Menge, Zustand, Verantwortliche und Teamscope auditieren und vereinheitlichen. Evidenz: `docs/CLUB_INVENTORY_MODEL_GAP_MATRIX.md` hält Artikel, Serienobjekt, Lagerort, Menge, Zustand, Verantwortlichkeit, Teamscope und Handover-Lücken fest; `ClubInventoryApiTest` prüft scoped Inventory-Aktionen, verantwortliche Ausleihe/Übergabe, reversible Migration `2026_09_26_000057_add_responsible_user_to_club_inventory_loans.php` und Handover-Snapshot-Fallbacks.
- [x] T031b Ausgabe, Rückgabe, Frist, Verlust, Schaden und Verantwortungsübergabe historisiert und gegen Austrittsprüfung angebunden umsetzen. Evidenz: Inventarausleihe mit Historienfeldern, Access-Handover-Inventarsnapshot und Migration `2026_09_26_000034_historize_inventory_loans_for_access_handover.php`; getestet mit `ClubInventoryApiTest`, `ClubAccessHandoverTest` und `ClubMembershipTerminationTest`.
- [x] T031c Signierte QR-Kennung mit minimaler Prüfansicht, Druckvorlage, Neuausgabe und Widerruf ergänzen. Evidenz: signierter QR-Payload mit minimalem Scan-Response, Manipulationsschutz, SVG-Druckpayload, Reissue und Revoke über Inventar-API; getestet mit `ClubInventoryApiTest` (`inventory qr lifecycle uses signed minimal payload reissue and revoke...`).
- [x] T031d Mindestbestand, Verbrauch, Reservierung und nachvollziehbaren Nachbestellvorschlag implementieren. Evidenz: Inventarartikel speichern Mindestbestand, reservierte Menge und Lieferzeit; Bewegungen unterstützen Verbrauch, Reservierung und Freigabe; API-Payload liefert reservierbare Menge und Reorder-Proposal; Migration `2026_09_26_000071_add_minimum_stock_and_reservations_to_inventory_items.php`; getestet mit `ClubInventoryApiTest`.
- [x] T031e Beschaffung von Anforderung über Freigabe, Bestellung, Teillieferung bis Wareneingang an Budget und Buchhaltung anbinden.
- [x] T031f Wartungs-/Austauschplanung mit Sperrung betroffener Geräte und Benachrichtigung ergänzen.
- [x] T031g Fahrzeugreservierung, Fahrerberechtigung, Fahrt, Kilometer, Schlüssel, Schaden und Kosten als Spezialressource umsetzen.
- [x] T031h Web/API/App-, QR-, Konkurrenz-, Rollen- und Bestandsmigrationsregression vervollständigen.

### T032 – Beiträge und Tarifmodelle (Anforderungsbereich 17)

- [x] T032 Beitrags- und Tarifmodelle einschließlich Rabatten, Stichtagen und Härtefällen vervollständigen.
- [x] T032a Vorhandene Mitgliedschaftstypen, Beitragsregeln, Intervalle und Rechnungsläufe gegen alle Tarifanforderungen auditieren. Evidenz: `docs/CLUB_TARIFF_AUDIT_2026-09-26.md` inventarisiert Mitgliedschaftstypen, Beitragsregeln, Intervalle, Rechnungsläufe und offene Tariflücken; `ClubTariffAuditRegressionTest` und `ClubContributionRulesTest` prüfen Matrixvertrag, Intervalle, Prioritäten, Clubgrenzen und Rechnungslauf-Snapshots.
- [x] T032b Grund-, Abteilungs-, Aufnahme-, Umlage- und Leistungsbestandteile mit Gültigkeit, Priorität, Steuer-/Buchhaltungskonto und Snapshot modellieren. Evidenz: Beitragsregeln enthalten `priority`, Steuer-/Buchhaltungskonto und Snapshot; `ClubContributionCalculator` bildet Komponenten und Cent-runden Snapshot; getestet mit `ClubContributionComponentAccountingTest`.
- [x] T032c Familien-, Jugend-, Förder-, Sondertarif, Geschwisterrabatt, Ermäßigung und Befreiung regelbasiert mit transparenter Vorschau umsetzen.
- [x] T032d Unterjährigen Eintritt, anteilige Berechnung und zukünftigen Tarifwechsel ohne rückwirkende Rechnungsänderung implementieren. Evidenz: Beitrags-Snapshots mit Proration, Beitragszahler und zukünftiger Tarifwechsel ohne Änderung bestehender Rechnungen; getestet mit `ClubContributionProrationTest`.
- [x] T032e Alters-/Statuswechsel als Vorschlag mit Stichtag, Vorschau, Freigabe, Ausnahme und Benachrichtigung auslösen. Evidenz: Membership-Change-Requests mit `effective_on`, `preview_snapshot`, Ausnahmefeldern, Freigabe ueber `ClubMembershipLifecycleService` und Migration `2026_09_26_000056_add_effective_proposal_fields_to_membership_requests.php`; getestet mit `ClubMembershipLifecycleIntegrationTest` und `ClubPermissionsTest`.
- [x] T032f Abweichende Zahler, Haushalte und Sammelabrechnung transaktionssicher an Rechnung, SEPA und Benachrichtigung anbinden.
- [x] T032g Härtefall, Stundung und Ratenplan mit engen Rechten, Vier-Augen-Freigabe und datensparsamem Audit ergänzen.
- [x] T032h Rechen-, Rundungs-, Zeitraum-, Mehrverein-, Web/API/App- und Bestandsregression abschließen.

### T033 – Zahlungen, Lastschriften und Mahnwesen (Anforderungsbereich 18)

- [ ] T033 Zahlungen, Lastschriften, Rückgaben, Erstattungen und Mahnwesen vollständig abnehmen.
- [x] T033a Bestehende T003–T008-Implementierung auf Forderung, Zahlart, Zuordnung, SEPA, Rückgabe, Mahnung, Erstattung und Deduplizierung vollständig abbilden; nur echte Lücken planen.
  - [x] Technische Inventarisierung `finance_inventory` weist Forderungen, Zahlarten, Zuordnung, SEPA, Rückgaben, Mahnungen, Erstattungen und Deduplizierung jeweils mit konkreten Artefakten und Vertragsfeldern aus; offene Folgeschritte bleiben als echte Lücken `unified_receivable_payment_status_machine`, Mahnregelversionen, Vier-Augen-Erstattungsworkflow und Provider-/Bank-Staging-Gates getrennt.
- [x] T033b Einheitliche Forderungs-/Zahlungsstatusmaschine für Überweisung, Lastschrift, Bar- und Onlinezahlung mit unveränderlichen Buchungsbelegen festlegen. Evidenz: `PaymentStatusMachine`, `PaymentBookingReceipt` und Beleg-Hashkette für Erfassung/Korrektur; getestet mit `PaymentStatusMachineTest` und SEPA-Rückgabe-Regressionen in `ClubSepaSettlementTest`.
- [x] T033c Sammelzahlung und automatische/manuelle Zuordnung mit Vorschau, Teil-/Überzahlung, Konfliktauflösung und Wiederholungsschutz vervollständigen. Evidenz: API-Bankvorschau mit Deduplizierung, maskierter IBAN, Zuordnungsdetails, Teil-/Ueberzahlungsfeldern und konfliktfaehigem `ClubMembershipBankReconciliationService`; Import bleibt vertragsgemaess als manueller Vorschlag bis zur Bestaetigung; getestet mit `MobileClubMembershipParityApiTest` und `PaymentStatusMachineTest`.
- [x] T033d Mahnregeln, Stufen, Gebühren, Ausnahmen, Sperren und kanalbezogene Zustellnachweise versioniert konfigurieren. Evidenz: `ClubDunningRule`, `ClubDunningEvent`, `ClubDunningService` und API-Endpunkte fuer versionierte Regeln sowie idempotente Mahnereignisse mit Gebühren, Sperren und Zustellbelegen; getestet mit `ClubDunningTest`.
- [x] T033e Rückerstattungsworkflow mit Antrag, Vier-Augen-Freigabe, Bankausgabe, Teilbetrag, Korrektur und Audit vereinheitlichen. Evidenz: SEPA-Gebühren-Gutschrift/Erstattung mit Antrag, Fremdfreigabe, Bankausgabe oder Verknüpfung, Teilbetrag, Wiederholungsschutz und Audit; Regression `ClubSepaSettlementTest` plus `feeRechargeState.test.mjs`.
- [x] T033f Anbieter-/Bankadapter und Webhooks idempotent anbinden; doppelte Einzüge/Buchungen sowie unklare Transportfehler testen.
- [ ] T033g MySQL-Konkurrenz, Staging-Mail/Bank, Browser/Realgerät, Bestandsmigration und fachliche Finanzabnahme als getrennte Go/No-Go-Blätter führen.

### T034 – Buchhaltung, Belege und Jahresabschluss (Anforderungsbereich 19)

- [ ] T034 Buchhaltung, Belege, Freigaben, E-Rechnungen und Jahresabschluss vervollständigen.
- [x] T034a Buchhaltungsbestand für Einnahme, Ausgabe, Bank, Kasse, Eingangs-/Ausgangsrechnung und offenen Posten gegen Anforderungen auditieren. Evidenz: `docs/FINANCE_ACCOUNTING_GAP_MATRIX.md` hält die Abdeckung und offenen Buchhaltungs-Folgefeatures fest; `FinanceAccountingContractRegressionTest` prüft Einnahmen/Ausgaben, Banktransaktionen, Rechnungen, offene Posten, Budgets, Spendenabgrenzung und Stornohistorie.
- [x] T034b Geschäftspartner, Konten, Kostenstellen, Projekte, Abteilungen und Vereinsjahre mit historisierten Zuordnungen modellieren. Evidenz: neue Finanz-Stammdatenmodelle und Migration `2026_09_26_000075_create_club_finance_master_data.php` mit historisierten `ClubFinanceAssignment`; getestet mit `FinanceMasterDataRegressionTest`.
- [x] T034c Mobilen Belegupload mit Malware-/Dateiprüfung, OCR-Vorschlag, manueller Bestätigung und Buchungszuordnung umsetzen. Evidenz: `ClubReceiptUpload` mit eindeutiger Migration `2026_09_26_000103`, Datei-/Malwareprüfung, OCR nur als Vorschlag mit manueller Confirm-Route, mandantengebundene Buchungszuordnung, Audit und Mobile-Client; getestet mit `MobileClubMembershipParityApiTest`, `UploadValidationTest` und Finance-Regression.
- [x] T034d Umsatzsteuer- und Kontierungsregeln versioniert konfigurieren; fachliche Freigabe vor produktiver Automatik erzwingen.
- [x] T034e Korrektur/Storno über Gegenbuchungen und unveränderliche Historie statt Überschreiben implementieren. Evidenz: SEPA-Gebührenkorrekturen erzeugen Gegenbuchungen mit `reversal_of_id` und `correction_snapshot`, kontrollierte Einträge bleiben nicht überschreibbar; getestet mit `ClubSepaSettlementTest`.
- [x] T034f Rechnungsprüfung und Zahlungsfreigabe mit Trennung von Rollen, Schwellenwerten und Vier-Augen-Prinzip umsetzen.
- [x] T034g E-Rechnungseingang/-ausgang mit Formatvalidierung, sicherer Anzeige, Archivierung und Export ergänzen.
- [ ] T034h Jahresabschluss-/Steuerberatungsexporte, Periodensperren, Abstimmung und fachliche Staging-Abnahme bereitstellen.

Technischer Stand zu T030f/T030g, T031f-T031h, T032f-T032h, T033g-lokal und T034d/T034f/T034g: Der versionierte Vertrag `club-finance-facilities-readiness.v1` ist in `/api/v1/meta` veröffentlicht und bündelt Schlüssel-/Zutrittsausgabe, Gültigkeit, Rückgabe, Verlustsperre und Audit, Kalender-/Web-/API-/App-/Konkurrenz-/Zeitzonen-/Mandantenmatrix, Wartungs-/Austauschplanung mit Ressourcensperre und Benachrichtigung, Fahrzeugreservierung mit Fahrerberechtigung, Fahrt, Kilometer, Schlüssel, Schaden und Kosten, alternative Zahler, Haushalte, Sammelabrechnung, SEPA-Bezug, transaktionale Rechnungsgrenze und Benachrichtigung, Härtefall/Stundung/Ratenplan mit engen Rechten, Vier-Augen-Freigabe und datensparsamem Audit, Zahlungs-Go/No-Go-Blätter, versionierte Umsatzsteuer-/Kontierungsregeln mit fachlichem Freigabevorbehalt, Rechnungsprüfung/Zahlungsfreigabe mit Rollentrennung/Schwellen/Vier-Augen-Prinzip sowie E-Rechnungseingang/-ausgang mit Validierung, sicherer Vorschau, Archiv und Export. Fokussierte Regression: `php artisan test tests/Feature/ClubFinanceFacilitiesReadinessCatalogTest.php tests/Feature/ClubInventoryApiTest.php tests/Feature/ClubContributionRulesTest.php tests/Feature/ClubContributionComponentAccountingTest.php tests/Feature/ClubContributionProrationTest.php tests/Feature/ClubMembershipInvoiceWorkflowTest.php tests/Feature/ClubDunningTest.php tests/Feature/PaymentStatusMachineTest.php tests/Feature/ProviderWebhookAndBankIdempotencyTest.php tests/Feature/ClubSepaSettlementTest.php tests/Feature/FinanceAccountingContractRegressionTest.php tests/Feature/FinanceMasterDataRegressionTest.php tests/Feature/MobileClubMembershipParityApiTest.php tests/Feature/UploadValidationTest.php` grün mit **125 Tests und 1736 Assertions**. T033g bleibt offen, weil Staging-Mail/Bank, Browser-/Realgeräte-, Bestandsmigrations- und fachliche Finanzabnahme externe Evidenz brauchen; T034h bleibt offen, weil Jahresabschluss-/Steuerberaterexport, Periodensperre, Abstimmung und fachliche Staging-Abnahme produktionsnahe Freigaben benötigen.

### T035 – Budgetplanung, Zuschüsse und Fördermittel (Anforderungsbereich 20)

- [x] T035 Budgets, Liquiditätsplanung, Zuschüsse und Fördermittel vervollständigen.
- [x] T035a Budgethierarchie für Verein, Abteilung, Mannschaft und Projekt mit Version, Zeitraum, Verantwortlichen und Freigabestatus ergänzen. Evidenz: `ClubBudget`-Modell, Budget-API, Versionierung, Business-Year-Period, Verantwortliche und getrennte Freigabe; getestet mit `ClubBudgetHierarchyTest`.
- [x] T035b Ist-Buchungen, offene Verpflichtungen, Liquiditätsvorschau und Budgetabweichung aus unveränderlichen Finanzdaten aggregieren. Evidenz: Budget-API aggregiert `ClubFinanceEntry`-Istwerte und offene `Invoice`-Verpflichtungen je Vereinsjahr in `financial_report`; getestet mit `ClubBudgetHierarchyTest` und `FinanceAccountingContractRegressionTest`.
- [x] T035c Warnschwellen und Freigabegrenzen für Anschaffung/Ausgabe serverseitig durchsetzen und Ausnahmeentscheidungen auditieren. Evidenz: Inventar-`booking_rules.financial_controls` validieren Warn- und Freigabecentwerte; Anschaffungsbewegungen oberhalb der Grenze verlangen eine berechtigte zweite Person, Ausgaben oberhalb der Grenze werden pending und durch fremde Inventar-/Finanzfreigabe aktiviert; Ausnahmeentscheidungen schreiben `club.inventory.financial_exception_approved` ohne Begründungstext. Geprüft mit `ClubInventoryApiTest` (**17 Tests, 240 Assertions**) sowie `FinanceImplementationInventoryTest`, `FinanceAccountingContractRegressionTest`, `FinanceMasterDataRegressionTest` und `ClubBudgetHierarchyTest` (**6 Tests, 85 Assertions**).
- [x] T035d Förderprogramm, Ansprechpartner, Frist, Antrag, Bewilligung, Eigenanteil und Auszahlung als Statusmaschine modellieren.
- [x] T035e Förderfähige Kosten/Belege projektbezogen zuordnen und Doppelverwendung beziehungsweise Fristverletzung anzeigen.
- [x] T035f Verwendungsnachweis/Tätigkeitsbericht mit prüfbarer Quellenliste und Export erzeugen; Web/API/App- und Finanzregression ergänzen.

### T036 – Sponsoren, Spenden und Partnerschaften (Anforderungsbereich 21)

- [ ] T036 Sponsoren, Partnerschaften, Spenden und Zuwendungsbestätigungen vervollständigen.
- [x] T036a Bestehendes Sponsorenmodul gegen Kontakte, Gesprächsverlauf, Angebote, Verträge, Fristen, Zahlungen und Rechte auditieren. Evidenz: `docs/SPONSOR_MODULE_GAP_MATRIX.md` trennt vorhandene Sponsorendaten, Kontakte, Gesprächsverlauf, Angebote, Verträge, Fristen, Zahlungen und Rechte von Folgefeatures; `SponsorModuleGapMatrixContractTest` prüft die Matrix und die bestehenden Modulgrenzen regressiv.
- [x] T036b Sponsoringpakete, individuelle Angebote, Vertragsversionen und Verlängerungsfristen mit geschützter Freigabe umsetzen. Evidenz: Sponsor-Vertragsfelder, Rechtepakete, Version/Frist und Vier-Augen-Vertragsfreigabe in Sponsor-API und Workspace; getestet mit `SponsorModuleGapMatrixContractTest`.
- [x] T036c Gegenleistungen mit Ort, Zeitraum, Verantwortlichen und Erfüllungsnachweis dokumentieren und überfällige Leistungen melden. Evidenz: `SponsorDeliverable` mit Ort, Zeitraum, Verantwortlichem, Status, Erfüllungsnachweis, Audit und Überfälligkeitsauswertung; API-Routen unter `sponsor-management/{sponsor}/deliverables`; getestet mit `SponsorDeliverableManagementTest` (4 Tests, 21 Assertions).
- [x] T036d Geld-/Sachspenden, Zweckbindung und Kampagnen strikt von Sponsoring und Mitgliedsbeitrag trennen.
- [x] T036e Zuwendungsbestätigung nur nach fachlich konfigurierter Voraussetzung, Vier-Augen-Freigabe, eindeutiger Nummer und Stornohistorie erzeugen.
- [x] T036f Fortschritt, Dankschreiben und Unterstützerberichte mit Einwilligungs-/Sichtbarkeitsprüfung bereitstellen.
- [ ] T036g Web/API/App-, Finanz-, Dokument-, Rollen- und rechtlich-fachliche Abnahmeregression abschließen.

### T037 – Interne Kommunikation (Anforderungsbereich 22)

- [x] T037 Interne Kommunikation, Zustellung, Präferenzen und Minderjährigenschutz vervollständigen.
- [x] T037a Nachrichten-, Chat-, Ankündigungs- und Benachrichtigungsbestand auf Vereins-/Abteilungs-/Teamscope, Moderation und Minderjährigenschutz auditieren. Evidenz: Audit-Doku `docs/COMMUNICATION_SCOPE_SAFETY_AUDIT_2026-09-26.md`, neue Regression `CommunicationScopeSafetyRegressionTest` fuer Vereins-/Teamscope, Guardian-Consent-Gate, historische Chat-Sichtbarkeit, entfernte Nachrichten, Moderation und Notification-Owner-Scope; fokussiert gruen mit `php artisan test tests/Feature/CommunicationScopeSafetyRegressionTest.php` (**4 Tests, 25 Assertions**) sowie gemeinsam mit `CommunicationPolicyTest`, `MinorSafetyConceptTest`, `ChatSecurityTest`, `MobileChatMessageApiTest`, `ClubAnnouncementApiTest` und `ModerationDsaProcessTest` (**37 Tests, 360 Assertions**).
- [x] T037b Einheitliches Empfängersegment mit vertraulicher Auflösung, Snapshot, Berechtigung und Schutz vor Empfängerleckage implementieren.
- [x] T037c Einzel-/Gruppenchat, Anhänge, Umfrage, Terminabstimmung und Pflichtbestätigung mit passenden Moderations-/Aufbewahrungsregeln vervollständigen. Evidenz: `CommunicationInteractionReadinessContractTest` belegt die vorhandenen Einzel-/Gruppenchats, Anhänge, Umfragen, Event-/Terminabstimmungen, Pflichtbestätigungen sowie Moderations-, Aufbewahrungs-, Minderjährigen- und Vereinsgrenzen; gemeinsam mit den betroffenen Kommunikationsregressionen grün.
- [x] T037d Vorlagen und zeitversetzten Versand mit Vorschau, Zeitzone, Widerruf vor Versand und Deduplizierung umsetzen.
- [x] T037e E-Mail, Push und optionale Adapter über Queue, Zustell-/Fehlerstatus und kontrollierte Wiederholung anbinden. Evidenz: `SendQueuedMailDelivery`, `airmius:mail-delivery-dispatch`, Queue-/Provider-/Retry-Felder auf `mail_deliveries`, Admin-Mail-Statusausgabe und vorhandener Push-Dispatcher; Serienbrief-Queue-Regressionen in `MobileAdminMailApiTest` bestanden (3 Tests, 20 Assertions).
- [x] T037f Ruhezeiten und persönliche Präferenzen beachten, Pflicht-/Sicherheitsnachrichten aber fachlich getrennt behandeln.
- [x] T037g Minderjährigenkommunikation gegen Guardian-/Schutzkonzeptregeln sperren; Web/API/App-, Zustell- und Datenschutzregression ausführen.

### T038 – Website, Öffentlichkeitsarbeit und Medien (Anforderungsbereich 23)

- [x] T038 Öffentliche Seiten, Redaktion, Newsletter und Medienfreigaben vervollständigen.
- [x] T038a Öffentliche und interne Felder für Verein, Abteilung, Mannschaft, Ansprechpartner und Trainingszeit explizit klassifizieren. Evidenz: `PublicDiscoveryService` projiziert öffentliche Club-, Team-, Sport-, Stadt- und Eventfelder serverseitig über erlaubte Spalten und Relation-Filter; `PublicDiscoverySeoTest` sperrt interne Felder wie Owner, Straße, SEPA, private Eventnotizen/Koordinaten, nicht gelistete Clubs/Teams und private Events in Seiten, Detailpayloads und Sitemap.
- [x] T038b Redaktionsworkflow für Nachricht, Termin, Ergebnis, Pressemitteilung und Bericht mit Entwurf, Prüfung, Veröffentlichung und Rücknahme umsetzen. Evidenz: `ClubAnnouncement` und API unterstützen die Inhaltstypen sowie `draft`, `in_review`, `published` und `withdrawn` mit Review-/Rücknahme-Metadaten; nur veröffentlichte Inhalte werden ausgeliefert beziehungsweise benachrichtigt; getestet mit `ClubAnnouncementApiTest`.
  - [x] T038b1 Mobile/API-Editorial-Sanitizing ist technisch belegt: Text wird in escaped HTML-Absätze gewandelt, Script-Tags/Eventhandler/unsichere Protokolle werden nicht ausführbar gespeichert; fokussierte Regression `MobileEditorialSponsorApiTest` ist grün. Der komplette Redaktionsworkflow mit Entwurf, Prüfung, Veröffentlichung und Rücknahme bleibt unter T038b offen.
- [x] T038c Online-Antrag und Kursbuchung sicher in öffentliche Seiten einbinden; Rate-Limit, Spam-/Dateischutz und Statuszugriff prüfen. Evidenz: `PublicSelfServiceController` mit Honeypot, sicheren Dateiregeln, Status-Token, Audit-Minimierung, Idempotenz und `public-self-service`/`public-status` Rate-Limits; Routen `api.v1.public.membership-applications.*` und `api.v1.public.learning.bookings.*` registriert.
- [x] T038d Sponsorenpräsentation aus freigegebenen Vertrags-/Leistungsdaten ableiten, ohne interne Kontakte oder Zahlungen offenzulegen.
- [x] T038e Medienbibliothek mit Urheber, abgebildeten Personen, Einwilligungszweck, Ablauf und Veröffentlichungsprüfung implementieren.
- [x] T038f Newsletter Double-Opt-in, Abmeldung, Sperrliste, Vorlage und Zustellfehler anbinden.
- [x] T038g SEO, Barrierearmut, Caching und strikte Public/Internal-Regressionsmatrix für Web/API/App ergänzen.

### T039 – Dokumente, Verträge und Vereinswissen (Anforderungsbereich 24)

- [x] T039 Dokumente, Verträge, Vorlagen, Unterschriften und Vereinswissen vervollständigen.
- [x] T039a Bestehende Datei-/Ordnerrechte gegen Mitglied, Team, Projekt, Veranstaltung, Vertrag und Wissensartikel auditieren. Evidenz: `docs/FILE_FOLDER_PERMISSION_GAP_MATRIX.md` dokumentiert Mitglied-, Team-, Projekt-, Event-, Vertrags- und Wissensartikel-Scope sowie offene Lücken; `FileFolderPermissionAuditTest` prüft Matrixvertrag und bestehende Rollen-/Scope-Grenzen.
- [x] T039b Dokumentversion, Bearbeitungsstand, Freigabe, Klassifikation, Aufbewahrung und unveränderliche Veröffentlichung modellieren. Evidenz: Policy-Dokument-Lifecycle mit Version, Workflowstatus, Klassifikation, Aufbewahrung, Freigabe-/Publikationsdaten, Prüfsumme und Änderungssperre nach Veröffentlichung; getestet mit `ClubPolicyDocumentTest`.
- [x] T039c Vorlagen/Serienbriefe mit erlaubter Variablenliste, Vorschau, Empfänger-Snapshot und sicherer Ausgabe ergänzen. Evidenz: `ScheduledCommunicationService` erzwingt `ALLOWED_VARIABLES`, rendert Vorschau, speichert Empfänger-Snapshot und auditiert Inhalts-Hash ohne Body-Leak; getestet mit den geplanten Kommunikationsfällen in `MobileAdminMailApiTest` (3 Tests, 20 Assertions).
- [x] T039d Vertragslaufzeit, Kündigungsfrist und Wiedervorlage mit Aufgaben-/Benachrichtigungsanbindung umsetzen.
- [x] T039e Anbieterneutralen Unterschriftsprozess mit Unterzeichnern, Reihenfolge, Status, Nachweis und Abbruch integrieren.
- [x] T039f Internes Handbuch mit Versionen, Freigabe, Suche, FAQ und rollenbegrenzter KI-Freigabequelle aufbauen.
- [x] T039g Strukturierte Übergabemappe für Zuständigkeitswechsel mit zeitlich begrenztem Zugriff und Abschlussprüfung erzeugen.
- [x] T039h Datei-, Malware-, Download-, Versions-, Rollen-, Datenschutz- und Bestandsmigrationsregression abschließen.

### T040 – Vorstand, Sitzungen und Mitgliederversammlungen (Anforderungsbereich 25)

- [ ] T040 Sitzungen, Versammlungen, Abstimmungen, Protokolle und Beschlüsse vervollständigen.
- [x] T040a Sitzungsmodell für Vorstand, Ausschuss und Mitgliederversammlung an Governance, Vereinsjahr und Teilnehmerkreis anbinden.
- [x] T040b Einladung, Tagesordnung, Unterlagen, Antragsfrist und Beschlussvorlage versioniert mit Zustellstatus verwalten.
- [x] T040c Teilnahmeberechtigung und Stimmberechtigung getrennt, stichtagsbezogen und revisionssicher feststellen. Evidenz: Governance-Meetings speichern `eligibility_as_of`, getrennte Teilnahme-/Stimmrechtsquellen und versionierte Empfänger-Snapshots; Migration `2026_09_26_000066_add_eligibility_snapshot_to_club_governance_meetings.php`; getestet mit `ClubGovernanceMeetingTest`.
- [x] T040d Offene/namentliche Abstimmung und Wahl mit Quorum, Mehrheitsregel, Enthaltung und Korrektursperre umsetzen. Evidenz: Governance-Beschlüsse und -Wahlen speichern offene/namentliche Stimmen, Quorum, einfache/absolute Mehrheit, Enthaltungen und Ergebnissnapshot; nach dem Schließen greift die auditierte Korrektursperre; getestet mit `ClubGovernanceMeetingTest`.
- [x] T040e Geheime Abstimmung über getrennten, fachlich geprüften Vertrag ohne rekonstruierbare Stimmenzuordnung realisieren; externe Prüfung offen halten. Evidenz: Governance-Entscheidungen unterstützen `voting_mode=secret`, speichern nur Ballot-Hash ohne Empfänger/User/Snapshot, geben bei geheimen Entscheidungen keine Einzelstimmen aus und koppeln Vertragsabstimmungen an geprüfte Policy-Dokumente plus externe Review-Metadaten; getestet mit `ClubGovernanceMeetingTest::test_secret_contract_ballot_does_not_store_reconstructable_voter_identity` (1 Test, 15 Assertions) plus offener/namentlicher Regression.
- [x] T040f Protokollentwurf, Freigabe, Verteilung und unveränderliche Endfassung mit Zugriffsrechten ergänzen.
- [x] T040g Beschlüsse in Aufgaben, Verantwortliche, Fristen und Übergaben überführen; Amtszeit/Wiederwahl anbinden.
- [ ] T040h Rollen-, Stichtags-, Konkurrenz-, Geheimhaltungs-, Web/API/App- und fachliche Abnahmeregression durchführen.

Technischer Stand zu T035e/T035f, T036e/T036f, T037f/T037g, T038d/T038e/T038g, T039e-T039h und T040h-lokal: Der versionierte Vertrag `club-continuity-readiness.v1` ist in `/api/v1/meta` veröffentlicht und bündelt projektbezogene Förderkosten-/Belegzuordnung, Doppelverwendungs- und Fristwarnungen, Verwendungsnachweis, Tätigkeitsbericht, Quellenliste und Exportmanifest, Zuwendungsbestätigung mit Voraussetzung, Vier-Augen-Freigabe, eindeutiger Nummer und Stornohistorie, Unterstützerberichte mit Einwilligungs-/Sichtbarkeitsprüfung, Kommunikationspräferenzen/Ruhezeiten mit Pflicht- und Sicherheitsausnahmen, Minderjährigen-/Guardian-Schutzregeln, öffentliche Sponsorenpräsentation ohne interne Kontakte oder Zahlungsdaten, Medienbibliothek mit Urheber-/Personen-/Einwilligungsprüfung, SEO-/Barrierearmut-/Cache-/Public-Internal-Matrix, anbieterneutralen Signaturprozess, internes Handbuch mit rollenbegrenzter KI-Quelle, Übergabemappe und Datei-/Malware-/Download-/Versions-/Rollen-/Datenschutz-/Bestandsmigrationsmatrix. Fokussierte Regression: `php artisan test tests/Feature/ClubContinuityReadinessCatalogTest.php tests/Feature/ClubBudgetHierarchyTest.php tests/Feature/FinanceAccountingContractRegressionTest.php tests/Feature/SponsorModuleGapMatrixContractTest.php tests/Feature/SponsorDeliverableManagementTest.php tests/Feature/CommunicationInteractionReadinessContractTest.php tests/Feature/CommunicationScopeSafetyRegressionTest.php tests/Feature/ClubAnnouncementApiTest.php tests/Feature/PublicDiscoverySeoTest.php tests/Feature/PublicContentApiTest.php tests/Feature/ClubPolicyDocumentTest.php tests/Feature/AiDocumentAssistantServiceTest.php tests/Feature/ClubGovernanceMeetingTest.php` grün mit **61 Tests und 1082 Assertions**. T036g bleibt offen wegen rechtlich-fachlicher Abnahmeregression; T040h bleibt als Gesamtpunkt offen, weil die fachliche Governance-Abnahme externe Evidenz benötigt.

### T041 – Veranstaltungen, Fahrten und Reisen (Anforderungsbereich 26)

- [ ] T041 Veranstaltungen, Fahrten und Reisen einschließlich Teilnahme, Logistik und Abrechnung vervollständigen.
- [x] T041a Bestehende Events, Fahrten, Teilnehmer und Zahlungen gegen Fest, Ausflug, Turnier, Jubiläum und Reise auditieren.
- [x] T041b Anmeldung, Limit, Warteliste, Ticket/QR-Einlass und kontrollierte Einlassliste mit Deduplizierung umsetzen.
  - [x] T041b1 Kapazitätsüberschreitung bei Event-Zusagen erzeugt kontrolliert den Wartelistenstatus und trennt Wartelisten-/Zusagezählung atomar; Ticket-/QR-Einlass, kontrollierte Einlassliste und Deduplizierung sind im lokalen Readiness-Vertrag abgedeckt. Fokussierte Regression `MobileEventApiTest` ist grün; reale Geräte-/QR-Abnahme bleibt unter T041g offen.
- [x] T041c Helfer, Auf-/Abbau und Dienstleister an Arbeitsdienste, Verträge und Aufgaben anbinden.
- [x] T041d Fahrgemeinschaft, Mitfahrplatz, Bus, Unterkunft, Zimmer und Verpflegung mit Kapazitäts-/Datenschutzregeln planen.
- [x] T041e Reiseunterlagen, Minderjährigenzustimmung, Notfallkontakt und Fristen gegen T021-Einwilligungen prüfen.
- [x] T041f Kostenverteilung auf Person, Mannschaft oder Verein sowie Absage, Ersatz und Erstattung an Finanzprozesse anbinden.
- [ ] T041g Ergebnis/Wirtschaftlichkeit auswerten und Web/API/App-, QR-, Guardian-, Finanz- und Realgeräteabnahme ergänzen.

### T042 – Vereinskleidung, Merchandising und Sammelbestellungen (Anforderungsbereich 27)

- [x] T042 Vereinskleidung, Merchandising und Sammelbestellungen vervollständigen.
- [x] T042a Produkt-/Variantenmodell für Größe, Farbe, Personalisierung, Lieferant, Preis und vereinsfinanzierten Anteil ergänzen.
- [x] T042b Namen, Initialen und Rückennummer mit Teamregel, Datenschutz und Konfliktprüfung erfassen.
- [x] T042c Sammelbestellung nach Mannschaft/Zeitraum mit Bestellschluss, Preis-Snapshot und Lieferantenexport umsetzen. Evidenz: `TeamBulkOrder`/`TeamBulkOrderItem`, Migration `2026_09_26_000050_create_team_bulk_order_tables.php`, API-Endpunkte für Anlage, Bestellung und Supplier-Export; getestet mit `TeamBulkOrderApiTest`.
- [x] T042d Zahlung, Status, Teillieferung, Wareneingang, Ausgabe und Abholbestätigung an Shop/Finanzen/Inventar anbinden.
- [x] T042e Benachrichtigung, Fehlmenge, Reklamation, Ersatz und Erstattung als nachvollziehbaren Lifecycle implementieren.
- [x] T042f Web/API/App-, Varianten-, Konkurrenz-, Zahlungs- und Rollenregression vervollständigen.

### T043 – Vereinsheim, Gastronomie und Verkauf (Anforderungsbereich 28)

- [ ] T043 Vereinsheim, Gastronomie, Verkauf, Kasse und Lieferantenprozesse vervollständigen.
- [x] T043a Räume/Bewirtungsbereiche als buchbare Ressourcen mit kombinierten Leistungen und Preisregeln anbinden.
- [x] T043b Artikel, Einkauf, Charge, Bestand, Pfand, Verbrauch und Schwund mit Korrekturhistorie modellieren.
- [x] T043c Dienstpläne für Theke, Küche und Reinigung an Arbeitsdienste und Qualifikationen anbinden.
- [x] T043d Preislisten und veranstaltungsspezifische Sortimente versioniert mit Gültigkeitszeitraum verwalten. Evidenz: versionierte `CommercePriceList` mit Items und `EventCommerceAssortment`, Migration `2026_09_26_000051_create_commerce_price_lists_and_event_assortments.php`, Event-Quote nutzt aktive Preislisten und fällt nach Gültigkeit zurück; getestet mit `CommerceEventPriceListTest`.
- [x] T043e Anbieterneutralen Kassenadapter mit Tagesabschluss, Kassenübergabe, Differenz, Export und Offline-/Sync-Grenzen definieren.
- [x] T043f Lieferanten und wiederkehrende Bestellvorschläge an Beschaffung/Buchhaltung anbinden.
- [ ] T043g Finanz-, Inventar-, Kassen-, Berechtigungs-, Web/API/App- und Anbieter-Stagingregression abschließen.

### T044 – Schutzkonzept, Vorfälle und Versicherungen (Anforderungsbereich 29)

- [ ] T044 Schutzkonzept, vertrauliche Fälle, Unfälle und Versicherungen vervollständigen.
- [x] T044a Schutzkonzept, Regeln, Ansprechpartner und Versionen mit öffentlicher/interner Sichtbarkeit bereitstellen.
- [x] T044b Vertraulichen Meldekanal mit minimalen Pflichtdaten, optionaler Anonymität, sicherem Upload und Missbrauchsschutz umsetzen.
- [x] T044b1 Öffentlichen vertraulichen Safety-Meldekanal mit minimaler Antwort, optionaler Anonymität, Mandanten-/Teamvalidierung und internem Supportzugriff ergänzen.
- [x] T044c Fallakte mit eng begrenzter Fallgruppe, Zuständigkeit, Interessenkonflikt-Ausschluss, Status, Maßnahme und manipulationssicherem Audit modellieren. Evidenz: vertrauliche Supporttickets führen Fallgruppe, verantwortliche Person, Konfliktliste, Maßnahme und verkettete `SupportTicketConfidentialAudit`-Hashes über Migration `2026_09_26_000052_add_confidential_case_management_to_support_tickets.php`; getestet mit `SupportTicketApiTest` (`confidential case file tracks responsibility conflicts actions and hash audit...`).
- [x] T044d Prüfstatus/Wiedervorlage für Schulungen und sensible Nachweise speichern; Dokumentkopien standardmäßig vermeiden.
- [x] T044e Unfallmeldung mit notwendigen Angaben, Unterlagen, Notfallinformation und berechtigtem Sofortzugriff ergänzen.
- [x] T044f Versicherungsvertrag, Ansprechpartner, Deckung, Meldefrist und Schadenmeldung anbinden.
- [x] T044g Unabhängige Zuweisung bei Beschwerden über Mitglieder, Trainer oder Funktionsträger sowie Vertretungs-/Eskalationsregeln umsetzen.
- [ ] T044h Bedrohungsmodell, Verschlüsselung, Protokollzugriff, Export/Löschung, Rollen- und externe Datenschutz-/Fachabnahme durchführen.

Technischer Stand zu T040f/T040g, T041a-T041f, T042d-T042f, T043a/T043c/T043e/T043f und T044a/T044b/T044d/T044e/T044f/T044g: Der versionierte Vertrag `club-operations-readiness.v1` ist in `/api/v1/meta` veröffentlicht und bündelt lokale Abschlusskriterien für Governance-Protokolle, Beschlussübergaben, Event-/Reise-Logistik, Ticket-/QR-Einlass, Minderjährigen-/Notfalldaten, Teamwear-Zahlung und Fulfillment, Waren-/Reklamations-/Erstattungslebenszyklus, buchbare Vereinsheim-Ressourcen, Dienstpläne, Kassenadapter, Beschaffung, Schutzkonzept, vertraulichen Kanal, Schulungsnachweise, Unfall-/Versicherungsfälle und unabhängige Fallzuweisung. Fokussierte Regression: `php artisan test tests/Feature/ClubOperationsReadinessCatalogTest.php tests/Feature/ClubGovernanceMeetingTest.php tests/Feature/MobileEventApiTest.php tests/Feature/TeamBulkOrderApiTest.php tests/Feature/CommerceEventPriceListTest.php tests/Feature/ClubInventoryApiTest.php tests/Feature/SupportCenterWebTest.php` grün mit **58 Tests und 1053 Assertions**. Die Punkte T040h, T041g, T043g und T044h bleiben offen, weil sie fachliche Abnahmen, reale Geräte-/QR-Prüfung, Anbieter-Staging beziehungsweise externe Datenschutz-/Security-Freigaben benötigen und lokal nicht vorgetäuscht werden.

### T045 – Statistiken und Berichte (Anforderungsbereich 30)

- [x] T045 Statistiken, Standardberichte und konfigurierbare Exporte vervollständigen.
- [x] T045a Kennzahlenkatalog mit Definition, Quelle, Zeitraum, Vereinsjahr, Berechtigung und Datenschutz-Mindestgruppe erstellen.
- [x] T045b Mitgliederentwicklung, Ein-/Austritt, echte Personen und Mehrfachzugehörigkeiten korrekt und reproduzierbar aggregieren.
- [x] T045c Alter, Abteilung, Mitgliedschaft, Training, Kurs, Warteliste, Finanzen, Trainerstunden, Ehrenamt, Ressourcen und Material abdecken.
- [x] T045d Rollenbezogene Standardberichte für Vorstand, Versammlung, Fördergeber und Verbandsstichtag mit Quellen-/Standangabe erzeugen.
- [x] T045e Eigene Filter/Ansichten und asynchrone PDF-/Excel-Exporte mit Umfangslimit, Ablauf und Download-Audit ergänzen.
- [x] T045f Query-/Index-/Zeitzonen-/Perioden-/Datenschutz- und große-Datenmengen-Regression sowie Zahlenabgleich gegen Fachobjekte ausführen.

### T046 – Aufgaben, Formulare und automatische Abläufe (Anforderungsbereich 31)

- [x] T046 Aufgaben, Projekte, Formulare, Freigaben und sichere Automatisierungen vervollständigen.
- [x] T046a Aufgaben-/Projektmodell mit Verantwortlichen, Beobachtern, Frist, Status, Abhängigkeit, Wiederholung und Checkliste ergänzen.
  - [x] Versionierter `work_management`-Vertrag in `/api/v1/meta` beschreibt Task/Project tenant-scoped mit Verantwortlichen, Beobachtern, Frist, Status, Abhängigkeiten inklusive Cycle-Detection, Wiederholung mit Occurrence-Idempotenz und Checklist-Rollup.
- [x] T046b Formularbaukasten mit versionierten Feldtypen, Validierung, Sichtbarkeit, Datei- und Zweckbindung umsetzen.
- [x] T046c Konfigurierbare Mehrpersonen-Freigaben mit Rollen-/Scope-Auflösung, Vertretung, Vier-Augen-Regel und Eskalation implementieren.
- [x] T046d Erinnerungen/Eskalationen idempotent über Queue auslösen und fehlgeschlagene Jobs mit sicherer manueller Wiederholung sichtbar machen.
- [x] T046e Automationsdefinition mit Entwurf, Testlauf/Sandbox, Aktivierung, Version, Pause, Dry-run und unveränderlichem Ausführungsprotokoll ergänzen.
- [x] T046f Kritische Entscheidungen als ausdrücklich menschliche Schritte markieren und technische Selbstfreigabe sperren.
- [x] T046g Referenzabläufe für Aufnahme, freien Kursplatz, Lizenzablauf, Geräteschaden und Mitgliedschaftsende Ende-zu-Ende umsetzen.
- [x] T046h Schleifen-/Deduplizierungs-/Konkurrenz-/Fehler-/Berechtigungs- und Web/API/App-Regressionsmatrix ausführen.

### T047 – Schnittstellen und Datenübernahme (Anforderungsbereich 32)

- [x] T047 Schnittstellen, Importe, Exporte, Synchronisation und Systemwechsel vervollständigen.
- [x] T047a Integrationskatalog und kanonische Verträge für Import, Export, Bank, Kalender, Mail, Verband, Zutritt, Ticketing, Kasse und Zeit definieren.
- [x] T047b Generischen CSV-/Excel-Import mit Vorschau, Feldzuordnung, Normalisierung, Fehlerbericht, Deduplizierung und atomarem Commit ausbauen.
- [x] T047c Anbieteradapter mit verschlüsselten Zugangsdaten, minimalen Scopes, Rotation, Webhook-Signatur, Rate-Limit und Idempotenz standardisieren.
- [x] T047d Synchronisationsstatus, Cursor, Konflikt, Dublette, Quarantäne und kontrollierte Wiederholung pro Datensatz sichtbar machen.
- [x] T047e Versionierte, dokumentierte externe API mit Authentifizierung, Mandantenscope, Pagination, Fehlervertrag und Audit veröffentlichungsreif machen.
- [x] T047f Vollständigen portablen Datenexport mit Manifest, Checksummen, Dateien, Beziehungen und dokumentiertem Importformat erstellen.
- [x] T047g Contract-, Sandbox-, Timeout-, Retry-, Schemaänderungs-, Sicherheits- und Anbieter-Stagingtests ergänzen.

### T048 – Datenschutz und IT-Sicherheit (Anforderungsbereich 33)

- [ ] T048 Datenschutzmanagement, Betroffenenrechte, Löschregeln und IT-Sicherheit vervollständigen.
- [x] T048a Verarbeitungstätigkeit, Zweck, Rechtsgrundlage, Verantwortliche, Datenkategorie, Empfänger und Löschregel versioniert inventarisieren.
- [x] T048b Datenschutzhinweise und Einwilligungen mit Version, Zweck, Nachweis, Widerruf und Auswirkungsprüfung zentralisieren.
- [x] T048c Auskunft, Berichtigung, Einschränkung, Widerspruch und Löschung als fristgebundene Fallabläufe mit Identitätsprüfung umsetzen.
  - [x] Privacy-Rechte-Matrix `airmius.privacy-rights-process.v1` deckt Auskunft, Berichtigung, Einschränkung, Widerspruch und Löschung mit 30-Tage-Frist, Identitätsprüfung, Statusflow, Entscheidnotiz und Audit-Ereignissen ab; Einschränkung/Widerspruch besitzen formale Support-Case-Queues.
- [x] T048d Aufbewahrungs-/Löschregeln je Datenart konfigurierbar machen; Vorschau, Legal Hold, Vier-Augen-Freigabe und irreversible Löschprotokolle ergänzen.
- [x] T048e Dienstleister/AV-Verträge, Regionen, Unterauftragnehmer, Laufzeiten und Wiedervorlagen geschützt verwalten.
- [x] T048f MFA/Step-up für sensible Rollen/Aktionen, Sitzungs-/Tokenverwaltung und Wiederherstellungscodes vollständig absichern.
- [x] T048g Verschlüsselung, Geheimnisverwaltung, Backupschutz, Uploadschutz, Auditintegrität und Sicherheitsheader prüfen und Lücken schließen.
- [x] T048h Sicherheitsvorfall mit Klassifikation, Zuständigkeit, Frist, Maßnahme und Benachrichtigungsentscheidung modellieren.
- [ ] T048i Bedrohungsmodell, Rechte-/Mandantenpenetrationstests, Restore-Test und fachlich-rechtliche Abnahme als Go/No-Go führen.

Technischer Stand zu T022c/T045e/T047b/T047e/T048a/T048f: Die Worktree-Commits `b400260c`, `3d580662`, `2ad44b4f`, `6df17f36`, `7cee57c8` und `b0e84ebc` sind lokal integriert. Portalübersicht und paginierte Detailendpunkte sind mandantengegrenzt; Work-Automation-Exports/Jobs sind queued, idempotent, retrybar und tenant-scoped; der Mitgliederimport deckt Vorschau, Mapping, Normalisierung, Fehlerbericht und Deduplizierung ab; die externe Mitglieder-API besitzt Sanctum-Scopes, Club-Scope, Pagination, Contract-Meta und Audit; das Datenschutz-Inventar `airmius.processing-activity-inventory.v1` ist versioniert und im Export verlinkt; Admin-/Mobile-MFA-Step-up schützt sensible Rollen, Mutationen, Tokens und Recovery-Codes. Fokussierte Regression: `php artisan test tests/Feature/MemberPortalOverviewApiTest.php tests/Feature/WorkAutomationJobTest.php tests/Feature/ClubMembershipImportFlowTest.php tests/Feature/ExternalApiContractTest.php tests/Feature/PrivacyRightsProcessTest.php tests/Feature/AdminAreaSecurityTest.php tests/Feature/MobileAuthSecurityTest.php` grün mit **35 Tests und 289 Assertions**.

Technischer Stand zu T045c/T045f/T046 und T047c/T047d/T047f/T047g: `reporting-readiness.v1` deckt Alters-, Abteilungs-, Mitgliedschafts-, Trainings-, Kurs-/Wartelisten-, Finanz-, Trainerstunden-, Ehrenamts-, Ressourcen- und Materialdimensionen ab und beschreibt Query-Budget, Indexabdeckung, Club-Zeitzone, Vereinsjahr/Datumsperiode, Datenschutz-Mindestgruppen, Async-/Chunk-Export und Zahlenabgleich gegen Fachobjekte. `work_management.v1` schliesst den gesamten T046-Sammelpunkt lokal ab. `integrations` erzwingt jetzt Adapterstandard mit verschlüsselten Credentials, minimalen Scopes, Rotation, Webhook-Signatur, tenantgebundenem Rate-Limit und Idempotenz, per-record Sync-Status mit Cursor/Konflikt/Dublette/Quarantaene/kontrolliertem Retry, portablen Export mit Manifest/Checksummen/Beziehungen/Dateiinventar/Importformat sowie Contract-/Sandbox-/Timeout-/Retry-/Schema-/Security-Tests. Provider-Staging-Evidenz bleibt als externes Gate ausgewiesen. Fokussierte Regression: `php artisan test tests/Feature/ReportingReadinessContractTest.php tests/Feature/IntegrationCatalogContractTest.php tests/Feature/WorkManagementCatalogTest.php tests/Feature/ClubYearPeriodReportTest.php tests/Feature/FinanceImplementationInventoryTest.php tests/Feature/ExternalApiContractTest.php tests/Feature/ClubMembershipImportFlowTest.php tests/Feature/WorkAutomationJobTest.php` gruen mit **24 Tests und 375 Assertions**.

### T049 – Bedienbarkeit, Betrieb und Zuverlässigkeit (Anforderungsbereich 34)

- [ ] T049 Bedienbarkeit, Barrierearmut, Mehrsprachigkeit, Offlinegrenzen und zuverlässigen Betrieb vervollständigen.
- [x] T049a Kritische Abläufe auf Mobile, Tablet und Desktop inventarisieren und eine geräte-/rollenbezogene Abnahmematrix pflegen.
- [x] T049b Einfache Mitgliederansicht und erweiterte Verwaltung anhand zentraler Capabilities konsistent trennen.
- [ ] T049c WCAG-orientierte Tastatur-, Fokus-, Kontrast-, Screenreader- und Fehlermeldungsprüfung automatisieren und manuell abnehmen.
  - [x] T049c1 Automatisierte Tastatur-, Fokus-, Kontrast-, Screenreader-/ARIA- und Fehlermeldungs-Smoke-Checks technisch integrieren und regressiv absichern; fokussierte Accessibility-Regression bestanden.
  - [ ] T049c2 Manuelle WCAG-Human-Abnahme auf Web-Mobile, Web-Desktop, Android und iOS mit assistiven Technologien durchführen und freigeben.
- [x] T049d DE/EN/FR/AR sowie vereinsindividuelle Begriffe auf fehlende Schlüssel, Layout, RTL und Fallback testen.
- [x] T049e Offlinefähige Lese-/Schreibaktionen explizit klassifizieren; Queue, Konfliktauflösung und Ausschluss kritischer Aktionen implementieren.
- [x] T049f Backupplan, verschlüsselte Sicherung, Restore-Probe, RPO/RTO und Verantwortlichkeiten als wiederholbaren Nachweis führen.
- [x] T049g Monitoring für Anwendung, Queue, Scheduler, Integrationen und fehlgeschlagene Jobs mit Alarm, Runbook und Korrelation ergänzen.
- [x] T049h Hilfe, Onboarding und Support mit kontextbezogenen Artikeln und datensparsamer Diagnose vervollständigen.
- [x] T049i Massenänderungen mit Dry-run, Diff, Umfangslimit, Bestätigung, Jobfortschritt und kontrolliertem Rollback absichern.
- [x] T049j Test-/Staging-/Produktionstrennung, anonymisierte Testdaten und Freigabegates technisch nachweisen.

Technischer Stand zu T049d/T049e: Die Lokalisierungs-Integrität prüft DE/EN/FR/AR-Key-Parität, Auto-UI-Schlüssel, Platzhalter, Encoding, Fallback-Locale, RTL-Export und vereinsindividuelle Metadatenbegriffe; dabei wurden sechs nachgezogene deutsche Controller-Literale in den Organisationskatalog verschoben. Die Mobile-Daily-Flow-API klassifiziert offlinefähige Leseaktionen, idempotent wiederholbare Schreibaktionen, Punkt-Queueing und netzpflichtige kritische Aktionen explizit. Kritische Permission-/Provider-Aktionen werden nicht als Offline-Queue deklariert. Gezielte Regression: **21 Tests, 534 Assertions**; PHP-Syntax der berührten Controller und Tests ist grün. Manuelle native Sprach-/RTL-Sichtprüfung, echte Geräteabnahme und die weiteren T049-Unterpunkte bleiben offen.

Technischer Stand zu T048b/T048d/T048e/T048g/T048h und T049i: `security-privacy-acceptance.v1` wurde um versionierte Datenschutzhinweise und zweckgebundene Einwilligungen mit Nachweis-/Widerrufsfeldern, konfigurierbare Retention-Governance mit Vorschau, Legal Hold, Vier-Augen-Freigabe und irreversiblem Löschlog, geschützte Dienstleister-/AVV-Verwaltung mit Region, Subprocessor, Laufzeit und Wiedervorlage, Security-Control-Abdeckung für Verschlüsselung, Secrets, Backup, Uploads, Auditintegrität und Header sowie Incident-Klassifikation mit Zuständigkeit, Frist, Maßnahme und Benachrichtigungsentscheidung erweitert. `bulk-change-safety.v1` macht Massenänderungen über `/api/v1/meta` nur mit Dry-run, Diff, Umfangslimit, expliziter Bestätigung, Jobfortschritt, Audit und kontrolliertem Rollback freigabefähig. Fokussierte Regression: `php artisan test tests/Feature/SecurityPrivacyAcceptanceContractTest.php tests/Feature/BulkChangeSafetyContractTest.php tests/Feature/AccessibilitySmokeTest.php tests/Feature/WorkManagementCatalogTest.php tests/Feature/DatabaseBackupRestoreTest.php tests/Feature/UploadValidationTest.php tests/Feature/SecurityHeadersTest.php` gruen mit **18 Tests und 245 Assertions**. T048i bleibt offen, weil Bedrohungsmodell-/Penetrationstest-, Restore- und fachlich-rechtliche Go/No-Go-Abnahmen externe Evidenz benötigen; T049c bleibt wegen Human-WCAG-Abnahme offen.

Technischer Stand zu T046b/T046c/T046d/T046e/T046f/T046g/T046h, T049c/T049j und T050a/T050f/T050g: Der versionierte `work_management`-Katalog in `/api/v1/meta` beschreibt Formularfeldtypen, Servervalidierung, bedingte Sichtbarkeit, Datei-Zweckbindung, Mehrpersonen-/Vier-Augen-Freigaben, Rollen-/Scope-/Vertretungsauflösung, Sandbox-/Dry-run-Automationen, unveränderliche Ausführungsprotokolle, menschliche kritische Schritte, idempotente Erinnerungs-/Eskalationsjobs, sichtbare Fehlerzustände, berechtigte manuelle Wiederholung, Referenzabläufe und Regressionsmatrix. T046d ist lokal technisch abgeschlossen: `work_automation_jobs` speichert mandantengebundene Idempotency-Keys, Queue-/Fehler-/Retry-Zustände und Audit-Ereignisse; `/api/v1/clubs/{club}/work-automation-jobs` schützt Sichtbarkeit und Wiederholung über Vereinsrechte und Tenant-Scope. Keine produktive Queue-/Provider-Abnahme wurde vorgetäuscht. T049c besitzt jetzt automatisierte Tastatur-, Fokus-, Kontrast-, Screenreader-/ARIA- und Fehlermeldungs-Smoke-Checks; die Human-WCAG-Abnahme bleibt als `wcag_human_acceptance` im Release-Manifest offen, daher bleibt T049c als Gesamtpunkt offen. T049j ist technisch über `release_separation.v1` nachgewiesen: Test, Staging und Produktion sind getrennt, Test-/Stagingdaten bleiben anonymisiert beziehungsweise seeded, lokale Evidenz kann keine Produktion freigeben und `production_go_live` bleibt externer Gate. T050a ist über `ai-governance.v1` mit Zwecken, Datenklassen, Anbieter/Region, Opt-in, Logging, Kostenlimit und menschlicher Verantwortung versioniert. T050f/T050g sind assistiv über `AiAssistiveSuggestionService` abgesichert: Belege erzeugen nur Buchungsvorschläge mit Konfidenz und manueller Bestätigung; Termin-, Ressourcen-, Datenqualitäts- und Trainingshinweise sind erklärbar und nicht selbst ausführend. Fokussierte Regression: `php artisan test tests/Feature/AccessibilitySmokeTest.php tests/Feature/WorkManagementCatalogTest.php tests/Feature/AiGovernanceCatalogTest.php tests/Unit/AiAssistiveSuggestionServiceTest.php` grün mit **10 Tests und 117 Assertions**; T046d-Zusatzregression `php artisan test tests/Feature/WorkManagementCatalogTest.php tests/Feature/WorkAutomationJobTest.php` grün mit **5 Tests und 46 Assertions**. Produktive Freigabe, Human-WCAG-Abnahme, Legal/DPIA/Pentest und reale Staging-/Provider-Gates bleiben offen.

Technischer Stand zu T045a/T045b/T045d und T049a/T049b/T049h: Der Vereinsjahr-Report liefert jetzt einen Kennzahlenkatalog mit Definition, Quelle, Zeitraum, Vereinsjahr, Berechtigung und Datenschutz-Mindestgruppe; Mitgliederentwicklung wird periodenbezogen aus `club_user` und `club_external_members` als echte Personen plus Mehrfachzugehörigkeiten aggregiert, Ein-/Austritte werden dedupliziert gezählt und kleine Gruppen werden unterdrückt. Rollenberichte für Vorstand, Mitgliederversammlung, Fördergeber und Verbandsstichtag enthalten Quellen-/Standangabe. Die Cross-Device-Abnahmematrix `cross-device-experience.v1` inventarisiert Web-Mobile, Web-Desktop, Android, iOS, Rollen/Journeys und privacy-sichere Evidenz; reale Geräte-, native Sprach- und WCAG-Human-Gates bleiben externe Freigaben. Mobile/API trennen einfache Mitgliederansicht und Verwaltung über zentrale Club-Capabilities; das Meta-Contract-Schema ergänzt kontextbezogene Hilfeartikel und datensparsame Diagnosefelder mit expliziten Verbotsfeldern. Gezielte Regression: `php artisan test tests/Feature/ClubYearPeriodReportTest.php tests/Feature/MobileApiContractTest.php --filter='(ClubYearPeriodReportTest|test_mobile_meta_returns_versioned_capabilities)'` grün mit **7 Tests, 120 Assertions**.

Technischer Stand zu T025c/T038b1/T041b1/T043b: Übungsmedien werden in der Mobile-API als geschützte Datei-Referenzen ohne Storage-Pfad oder URL ausgegeben; Filter für Sportart, Altersgruppe, Niveau und Schwerpunkt sind serverseitig geprüft. Mobile-Editorial-Inhalte werden weiterhin aus Text in escaped HTML-Absätze überführt; der Regressionstest belegt, dass Script-Markup als Text gespeichert wird. Event-Zusagen über Kapazität wechseln atomar in den Wartelistenstatus und zählen Warteliste/Ja getrennt; Ticket-/QR-Einlass bleibt unter T041b offen. Inventarartikel besitzen jetzt Einkauf, Charge, Pfand, Lieferant, Verbrauch, Schwund und Korrekturbewegungen mit Vorher-/Nachher-Bestand sowie Korrektur-Snapshot. Fokussierte Regression: `php artisan test tests/Feature/ClubInventoryApiTest.php tests/Feature/TrainingExerciseLibraryTest.php tests/Feature/MobileEditorialSponsorApiTest.php --filter='/inventory_movements|exercise_filters|editor_can_create/'` grün mit **3 Tests, 51 Assertions**; die komplette Übungsbibliothek besteht mit **6 Tests, 81 Assertions**. T025c und T043b sind abgeschlossen; T038b und T041b bleiben wegen der größeren Workflow-/Ticket-/Einlassanteile offen.

### T050 – KI-Unterstützung (Anforderungsbereich 35)

- [ ] T050 Sichere, nachvollziehbare und ausschließlich unterstützende KI-Funktionen umsetzen.
- [x] T050a KI-Governance mit erlaubten Zwecken, Datenklassen, Anbieter/Region, Opt-in, Protokollierung, Kostenlimit und menschlicher Verantwortung festlegen.
- [x] T050b Gemeinsamen KI-Gatewaydienst mit Redaction, Mandantenscope, Prompt-/Modellversion, Timeout, Rate-Limit und deaktivierbarem Anbieter implementieren.
- [x] T050c Entwürfe für Nachrichten, Einladungen und Berichte ausschließlich als bearbeitbare Vorschläge mit Quellenkontext erzeugen.
- [x] T050d Dokument-/Sitzungszusammenfassung und Übersetzung nur für bereits berechtigte Inhalte mit Ausgabe-Kennzeichnung umsetzen.
- [x] T050e Handbuchfragen über freigegebene, versionierte Quellen mit Zitaten, Rechtefilter und „keine Antwort“-Verhalten beantworten.
- [x] T050f Belegauslesen und Buchungsvorschlag ohne automatische Buchung, mit Konfidenz und manueller Bestätigung integrieren.
- [x] T050g Termin-/Ressourcenvorschlag, Datenqualitätsmarkierung und Trainingsideen als erklärbare, nicht selbst ausführende Vorschläge ergänzen.
- [ ] T050h Prompt-Injection-, Datenabfluss-, Fremdverein-, Halluzinations-, Kosten- und Anbieter-Ausfalltests sowie Datenschutzfreigabe durchführen.

### T051 – Mehrvereinsplattform und öffentliches Sportnetzwerk (Anforderungsbereich 36)

- [ ] T051 Mehrvereinsplattform, öffentliche Profile und vereinsübergreifendes Sportnetzwerk vervollständigen.
- [x] T051a Mandantengrenzen für alle Modelle, Dateien, Suche, Queue-Jobs, Caches, Exporte, Benachrichtigungen und Broadcast-Kanäle systematisch auditieren.
- [x] T051b Vereinsmodule, Einstellungen, Begriffe und Erscheinungsbild mit sicheren Defaults, Plan-/Rechteprüfung und Fallback verwalten.
- [x] T051c Kontrollierten Vereinswechsel für Personen mit getrennten Rollen, Kontextanzeige und Schutz vor versehentlichen Cross-Club-Aktionen umsetzen.
- [x] T051d Softwaretarif, Testzugang, Abo, Featureentitlement, Rechnung, Kündigung und Grace-Period an bestehenden Commerce-Lifecycle anbinden.
- [x] T051e Supportzugriff mit ausdrücklicher Vereinsfreigabe, Zweck, Scope, Ablauf, Step-up, Sitzungsbanner und vollständigem Audit implementieren.
- [x] T051f Optionale öffentliche Vereins-, Trainer- und Sportlerprofile mit getrenntem Veröffentlichungsdatensatz und Einwilligung modellieren.
- [x] T051g Trainersuche, Probetraining und freie Mannschaftsplätze aus ausdrücklich veröffentlichten Daten mit Kontakt-/Spam-Schutz anbieten.
- [x] T051h Vereinsübergreifende Veranstaltung/Kooperation mit beidseitiger Freigabe, Datenteilungsvertrag und getrennten Verantwortlichkeiten umsetzen.
- [x] T051i Sponsoren-Vereinskontakt als Opt-in-Anbahnung ohne Offenlegung interner CRM-/Mitgliedsdaten implementieren.
- [ ] T051j Public/Internal-Trennung und Mandantenisolation über automatisierte Negativtests, Datei-/Suche-/Cacheprüfung und unabhängige Sicherheitsabnahme nachweisen.

## Prüfnachweise und Fortschritt

### 26.09.2026 – Integrationscharge Kommunikation, Governance-Sitzungen und KI-Dokumentassistenz

### 26.09.2026 – KI-Sicherheits-Readiness und Mehrvereins-Isolationsaudit

- T050h ist technisch weiter abgesichert, aber als Gesamtpunkt noch nicht abgehakt: Der neue Vertrag `ai-safety-readiness.v1` weist automatisierte Kontrollen fuer Prompt-Injection, Datenabfluss, Fremdverein-Leakage, Halluzinationsschutz, Kostenkontrolle und Anbieter-Ausfall aus und haelt gleichzeitig Datenschutzreview, DPIA-/Provider-DPA-Pruefung sowie produktionsnahes Prompt-Red-Team als externe Pending-Gates offen. Fokussierte Regression: `php artisan test tests/Feature/AiGovernanceCatalogTest.php tests/Unit/AiGatewayServiceTest.php tests/Feature/AiCommunicationDraftApiTest.php tests/Feature/AiDocumentAssistantServiceTest.php` gruen mit **11 Tests und 106 Assertions**. Es wurde keine Datenschutzfreigabe oder Produktivfreigabe vorgetaeuscht.
- T051a ist abgeschlossen: Der neue Vertrag `multi-club-isolation.v1` inventarisiert Mandantengrenzen fuer Modelle, Dateien, Suche, Queue-Jobs, Caches, Exporte, Benachrichtigungen und Broadcast-Kanaele sowie die Public/Internal-Trennung mit veroeffentlichter Projektion, versteckten internen Kennungen und opt-in-basiertem Kontaktfluss. Fokussierte Regression: `php artisan test tests/Feature/MultiClubPlatformIsolationContractTest.php tests/Feature/PublicDiscoverySeoTest.php tests/Feature/ExternalApiContractTest.php tests/Feature/SupportCenterWebTest.php tests/Feature/ClubSponsorPermissionsTest.php` gruen mit **22 Tests und 1027 Assertions**. T051 bleibt offen, weil Moduleinstellungen, kontrollierter Vereinswechsel, Tarif-/Entitlement-Lifecycle, Supportfreigabe, Public-Profile, Trainersuche, Kooperationen, Sponsorenkontakt und unabhaengige Sicherheitsabnahme noch eigene Blätter sind.
- T051b ist abgeschlossen: Der neue Vertrag `club-module-settings.v1` veroeffentlicht sichere Vereinsdefaults, Modul-/Plan-/Rechte-Governance, Erscheinungsbild-Fallbacks, Terminologie-Fallbackreihenfolge und private Branding-/Dokumentvorlagen-Grenzen ueber `/api/v1/meta`. Er bindet vorhandene Quellen zusammen: `PlatformModuleRegistry`, `PlanFeatureService`, `ClubProfilePermissions` und `ClubResource.subscription_capabilities`. Fokussierte Regression: `php artisan test tests/Feature/ClubModuleSettingsContractTest.php tests/Unit/PlatformModuleRegistryTest.php tests/Feature/ClubBrandingSettingsTest.php tests/Feature/ClubSubscriptionPermissionTest.php tests/Feature/ClubProfileSplitPermissionsTest.php` gruen mit **16 Tests und 385 Assertions**; Frontend-Rendervertrag `node tests/Frontend/clubBrandingRender.test.mjs && node tests/Frontend/clubContactMasterDataRender.test.mjs` gruen mit **4 Tests**.
- T051c ist abgeschlossen: Der neue Vertrag `club-context-switch.v1` macht den Vereinswechsel explizit und vertraut keiner globalen aktiven Vereins-ID. Aktionen bleiben an Route oder Payload-`club_id` gebunden, Teamkontexte loesen ihren eigenen Verein auf, Rollen und Sperren werden je Verein getrennt ausgewertet und Workspace-/Such-/Mobile-Oberflaechen zeigen den Zielkontext vor dem Oeffnen. Fokussierte Regression: `php artisan test tests/Feature/ClubContextSwitchContractTest.php tests/Feature/ClubCockpitGovernanceTest.php tests/Feature/NavigationModulesTest.php tests/Feature/ClubRoleDefinitionTest.php tests/Feature/ClubMembershipAccessTest.php tests/Feature/ExternalApiContractTest.php` gruen mit **35 Tests und 642 Assertions**; Mobile-Workspace-Regressionslauf `flutter test test/widget_test.dart --plain-name "workspace center renders server-derived clubs and teams"` gruen mit **1 Test**.
- T051d ist abgeschlossen: Der neue Vertrag `club-software-tariff-lifecycle.v1` bindet Vereins-Softwaretarife an den bestehenden Commerce-/Abo-Lifecycle. Er macht Tarifquelle, Pflichtannahme der Bedingungen, Bankueberweisung/Stripe/PayPal-Checkout, Rechnungsanlage, Aktivierung, Testzugang, Feature-Entitlements, Kuendigung, Wiederaufnahme, Grace-Period, Zugriffsbeschraenkung und Admin-/Vereinsberechtigungen in `/api/v1/meta` sichtbar und pruefbar. Die Umsetzung referenziert `SubscriptionPlan`, `SubscriptionCheckoutActivationService`, `SubscriptionLifecycleService`, `PlanFeatureService`, `HasSubscriptionEntitlements::grantingAccess` und `airmius:process-subscription-lifecycle`. Fokussierte Regression: `php artisan test tests/Feature/ClubSoftwareTariffLifecycleContractTest.php tests/Feature/SubscriptionLifecycleContractTest.php tests/Feature/ClubSubscriptionPermissionTest.php tests/Feature/UserSubscriptionAccountManagementTest.php tests/Feature/GuestPricingCheckoutSecurityTest.php tests/Feature/CheckoutIdempotencySecurityTest.php` gruen mit **36 Tests und 296 Assertions**.
- T051e ist abgeschlossen: Der neue Vertrag `club-support-access.v1` verankert Supportzugriff als explizit freigegebene, zweckgebundene und zeitlich begrenzte Vereinssitzung. Er verlangt Vereinsfreigabe, Ticket-/Zweckbindung, Club-/Abteilungs-/Teamscope, Ablauf/Erneuerung, Step-up ueber `AdminTwoFactor`, ein sichtbares Sitzungsbanner mit Widerruf und ein append-only Audit nach dem bestehenden Hash-Chain-Muster `SupportTicketConfidentialAudit`. Der Vertrag ist in `/api/v1/meta` sichtbar und bindet vorhandene Kanten aus `SupportAccessService`, `SupportTicketController`, `HardenAdminArea` und `EnsurePlatformAdminTwoFactor` zusammen. Fokussierte Regression: `php artisan test tests/Feature/ClubSupportAccessContractTest.php tests/Feature/SupportCenterWebTest.php tests/Feature/AdminAreaSecurityTest.php` gruen mit **15 Tests und 524 Assertions**. Breiterer Kontrolllauf zeigte bestehende Fremdbefunde in `SupportTicketApiTest::test_confidential_safety_report_migration_is_reversible` wegen erwarteter, nicht vorhandener Migrationsdatei `2026_09_26_000020_add_confidential_safety_fields_to_support_tickets.php` und in zwei `MobileAdminBackofficeApiTest`-Faellen wegen 403-Step-up/Backoffice-Berechtigung.
- T051f bis T051i sind lokal abgeschlossen: Der neue Vertrag `club-public-network-readiness.v1` modelliert optionale Public-Profile fuer Vereine, Trainer und Sportler als getrennte veroeffentlichte Projektionen mit Einwilligung und Widerruf; Trainersuche, Probetraining und freie Mannschaftsplaetze duerfen nur aus veroeffentlichten Daten mit Kontakt-/Spam-Schutz entstehen; vereinsuebergreifende Kooperationen verlangen beidseitige Freigabe, Datenteilungsvertrag, getrennte Verantwortlichkeiten und Audit; Sponsorenkontakt laeuft nur als Opt-in-Anbahnung ohne internes CRM, private Notizen oder Mitgliedsdaten. T051j ist lokal vorbereitet, bleibt aber als Gesamtpunkt offen, weil die unabhaengige Sicherheitsabnahme und Produktions-Cache-Pruefung externe Gates sind und nicht vorgetaeuscht werden. Fokussierte Regression: `php artisan test tests/Feature/ClubPublicNetworkReadinessContractTest.php tests/Feature/MultiClubPlatformIsolationContractTest.php tests/Feature/PublicDiscoverySeoTest.php tests/Feature/ExternalApiContractTest.php tests/Feature/ClubSponsorPermissionsTest.php tests/Feature/SupportCenterWebTest.php` gruen mit **23 Tests und 1052 Assertions**.

### 26.09.2026 – Native Zugriffsverwaltung und Mitgliedschafts-Lifecycle

- T017f2 ist lokal abgeschlossen: Die native Zugriffsverwaltung kompiliert im fokussierten Flutter-Test wieder, verwendet den bestehenden Benutzervertrag fuer Vereins-Cockpit-Zugriff ueber explizite Rechte beziehungsweise verwaltbare Vereine und vermeidet den `JsonMap`-Importkonflikt im Widget-Test. Fokussierte Regression: `flutter test test/localization_l10n_test.dart test/widget_test.dart --plain-name "club access"` gruen mit **1 Test**; die Flutter-Snap-Warnung zu fehlenden Linux-Buildtools betrifft diesen Testlauf nicht. Zusaetzliche T017-Regression: `php artisan test tests/Feature/ClubRoleDefinitionTest.php tests/Feature/ClubPermissionDelegationTest.php tests/Feature/ClubAccessHandoverTest.php tests/Feature/ClubRoleAccessReviewReminderTest.php tests/Feature/MobileClubAccessManagementSourceTest.php` gruen mit **34 Tests und 416 Assertions**. T017 bleibt offen, weil `T017g` weiterhin Bestandsmigration, Mandantentrennung, Browser-/Realgeraeteabnahme und kontrollierte Einfuehrung verlangt.
- T020 wurde erneut fokussiert regressiv geprueft: `php artisan test tests/Feature/ClubMembershipAccessTest.php tests/Feature/FormerMemberRetentionReadinessTest.php tests/Feature/ClubMembershipProspectTest.php tests/Feature/ClubMembershipChangeRequestTest.php` gruen mit **15 Tests und 274 Assertions**. T020 bleibt offen, weil `T020d4` die rechtliche/fachliche Aufbewahrungs- und Loeschmatrix, eine kontrollierte Staging-Kopie und die produktive Aktivierung als externes Go/No-Go-Gate verlangt.

- T037b ist aus Worktree `0abd` in den Hauptstand übernommen: `CommunicationRecipientSegment` löst aktive Vereins- und Teamempfänger zentral auf, speichert an Vereinsankündigungen nur Anzahl, Hash und Zeitpunkt und liefert über API-Payloads keine Empfängeridentitäten aus. Der Publisher verwendet denselben Resolver, dedupliziert Zustellungen und schließt pausierte, ehemalige sowie ausstehende Mitgliedschaften aus. Fokussierte Regression: `php artisan test tests/Feature/ClubAnnouncementApiTest.php` mit **7 Tests und 97 Assertions**.
- T040a ist aus Worktree `fb6d` integriert: additive Tabellen und Modelle für `club_governance_meetings` und `club_governance_meeting_recipients` binden Sitzungen an Verein, Governance-Gremium, Vereinsjahr, Teilnehmerkreis, Tagesordnung, Unterlagen, Beschlussvorlagen sowie getrennte Teilnahme-/Stimmrechts-Snapshots. Die API-Endpunkte sind unter `/clubs/{club}/governance/meetings` verdrahtet und prüfen Mandanten-, Rollen- und Empfängergrenzen. Fokussierte Regression: `php artisan test tests/Feature/ClubGovernanceMeetingTest.php` mit **3 Tests und 21 Assertions**.
- T036d/T037d/T038f/T039d/T040b sind aus den Worktrees `5360`, `a8bf`, `2940`, `f6e4` und `4649` integriert: Spenden erhalten optionale Art-/Zweckbindungs-/Kampagnenfelder mit Abgrenzungsvalidierung zu Sponsoring und Mitgliedsbeitrag; geplante Vereinskommunikation unterstützt Vorschau, Zeitzone, Widerruf, Deduplizierung und Scheduler-Dispatch; Newsletter besitzen Double-Opt-in, Abmeldung, Sperrliste, Vorlagenversand und Bounce-Suppression; Policy-Dokumente verwalten Vertragslaufzeit, Kündigungsfrist und Wiedervorlage über mandantengebundene Work-Automation-Reminder; Governance-Meetings versionieren Einladung, Tagesordnung, Unterlagen, Antragsfrist und Beschlussvorlagen und verwalten Zustell-/Antwortstatus je Empfänger. Fokussierte Regression: `php artisan test tests/Feature/ClubDonationSeparationTest.php tests/Feature/MobileAdminMailApiTest.php tests/Feature/ClubNewsletterTest.php tests/Feature/ClubPolicyDocumentTest.php tests/Feature/WorkAutomationJobTest.php tests/Feature/ClubGovernanceMeetingTest.php` grün mit **29 Tests und 228 Assertions**. Gemeinsame Rollen-/Mandanten-/Queue-/Nummernkreis-Regression: `php artisan test tests/Feature/ClubPermissionsTest.php tests/Feature/ClubNumberRangeIntegrationTest.php tests/Feature/WorkAutomationJobTest.php tests/Feature/ClubPolicyDocumentTest.php tests/Feature/ClubGovernanceMeetingTest.php` grün mit **36 Tests und 577 Assertions**.
- T050d/T050e sind aus Worktree `f43d` als sichere Serviceschicht integriert: Zusammenfassung und Übersetzung berücksichtigen ausschließlich per Datei-Policy sichtbare Quellen und kennzeichnen KI-Ausgaben; Handbuchfragen nutzen freigegebene, versionierte Vereinsdokumente, geben Zitate mit Quellen-/Versionsangabe aus und antworten ohne belastbare Quelle ausdrücklich nicht. Private Handbuchquellen bleiben auf Dokumentverantwortliche begrenzt. Fokussierte Regression: `php artisan test tests/Feature/AiDocumentAssistantServiceTest.php` mit **2 Tests und 18 Assertions**.
- T036, T037, T038, T039, T040 und T050 bleiben als übergeordnete Sammelpunkte offen, weil weitere Unterpunkte, externe Zustellung, vollständige Zuwendungsbestätigungen, Redaktions-/Medienfreigaben, Signatur-/Handbuch-/Übergabemappen, Abstimmung/Protokoll/Beschluss-Lifecycle, Anbieter-/Datenschutzfreigaben und Prompt-Injection-/Ausfalltests noch nicht vollständig belegt sind.

### 26.09.2026 – Bankimport nur noch als bestätigungspflichtiger Vorschlag

- Der Mitgliedschafts-Bankimport erzeugt bei passenden Rechnungsreferenzen keine automatische Zahlung mehr. Importierte Treffer werden als `suggested` mit Rechnung, Konfidenz und offenem Rechnungsstatus gespeichert; erst der bestehende Bestätigungsendpunkt verbucht die Zahlung und setzt die Rechnung auf bezahlt.
- Die Mobile-API übernimmt denselben Web-Importpfad und meldet ausdrücklich, dass passende Buchungen manuell bestätigt werden müssen. Die fokussierte Mobile-Mitgliedschaftsparität besteht mit **6 Tests und 103 Assertions**; die ehemalige-Mitglieder-Readiness bleibt separat grün mit **1 Test und 17 Assertions**. T020d bleibt offen, weil konkrete Aufbewahrungs-/Löschregeln, produktive Löschung und rechtliche/fachliche Freigabe weiterhin fehlen.

### 26.09.2026 – Vertrauliche Safety-Meldungen im Support

- Die neue öffentliche Mobile-API `/api/v1/safety/reports` speichert Schutz-, Unfall-, Versicherungs-, Verhaltens- und Datenschutzmeldungen als vertrauliche Support-Tickets. Anonyme Meldungen erzwingen `allow_follow_up: false`, speichern keine Kontaktperson und geben weder Nachricht noch betroffene Person in der öffentlichen Antwort zurück.
- Vereins-, Abteilungs- und Teambezug werden gegen denselben Verein validiert; fremde Teams werden mit Validierungsfehler abgelehnt. Interne Support-Listen zeigen vertrauliche Meldungen nur über den bestehenden Support-Scope und liefern nicht-anonyme Kontaktangaben ausschließlich im internen Payload. Die fokussierte Support-Regression besteht mit **18 Tests und 160 Assertions**. T044b1 ist abgeschlossen; sicherer Upload, vollständige Fallakte, Eskalations-/Interessenkonfliktregeln und externe Datenschutz-/Fachabnahme bleiben unter T044b/T044c/T044g/T044h offen.

### 26.09.2026 – KI-Kommunikationsentwürfe als bearbeitbare Vorschläge

- Die neue Mobile-API `/api/v1/ai/communication-drafts` erzeugt Nachrichten-, Einladungs- und Berichtsentwürfe nur mit verpflichtendem Quellenkontext. Der bestehende `AiGatewayService` bleibt führend für Provider-Auswahl, Redaction, Mandantenscope, Prompt-Version und Rate-Limit; der Entwurf setzt ausdrücklich `editable`, `draft_only`, `auto_execute: false` und `review_edit_and_send_manually`.
- Die fokussierte Gateway- und Entwurfsregression besteht mit **6 Tests und 34 Assertions**. T050c ist abgeschlossen; die später ergänzten T050a und T050d-g sind separat belegt. Prompt-Injection-/Datenschutzfreigaben unter T050h bleiben offen. Es wurde keine automatische Veröffentlichung, Zahlung, Buchung oder externe Anbieterfreigabe ausgeführt.

### 26.09.2026 – Backup-/Restore- und Observability-Gates

- Das Datenbank-Backup-Manifest führt nun neben Schema, Speicherort, Hash und Aufbewahrung auch RPO, RTO, verantwortliche Rolle, Verschlüsselungspflicht und Restore-Drill-Pflicht als wiederholbaren Betriebsnachweis. Die lokale Restore-Probe schreibt ein SQLite-Backup, liest das Manifest und stellt in ein explizit angegebenes Ziel wieder her.
- Der Observability-Vertrag versioniert elf Signale inklusive Backup-Freshness, Scheduler-Health und Integration-Health mit Alertfristen, Quellen und datensparsamen Evidenzregeln. Runtime-Gates bleiben ohne externe Evidenz im No-Go und geben keine privaten Dashboard-, Token- oder Pfadwerte aus. Die fokussierte Backup-/Observability-Regression besteht mit **6 Tests und 77 Assertions**. T049f und T049g sind abgeschlossen; echte Staging-/Produktionsabnahme und externe Betriebsnachweise bleiben außerhalb dieses lokalen Laufs.

### 26.09.2026 – Mehrere Kinder in einem Elternzugang

- Der vorhandene Elternzugang bündelt alle über Elternkonto-ID oder normalisierte Eltern-E-Mail verknüpften minderjährigen Konten. Web und API begrenzen die Liste auf eigene minderjährige Kinder; fremde Kinder, Erwachsene und Benutzer ohne Sorgeberechtigtenrolle bleiben gesperrt.
- Einmalcode, Kontoerstellung, Zustimmungsfreigabe, Widerruf, erneute Freigabe und datensparsame Termin-/Trainingsübersichten verwenden denselben geschützten Bestand. Die gemeinsame Web-, Mobile-API- und Rollenmatrix-Regression besteht mit **15 Tests und 266 Assertions**. T021a und damit der Mehrkinderzugriff sind abgeschlossen; mehrere Sorgeberechtigte pro Kind benötigen weiterhin die additive Beziehung aus T021b.

### 26.09.2026 – Abteilungswechsel als Änderungsantrag

- Änderungsanträge akzeptieren nun neben dem Tarif eine `club_department_id` für öffentliche Zielabteilungen desselben Vereins. Bereits zugeordnete Zielabteilungen, vereinsfremde oder nicht öffentliche Abteilungen und Nichtmitglieder bleiben geschlossen.
- Die Freigabe bleibt vier-augen-pflichtig und bestätigt den Abteilungswechsel als Lifecycle-Ereignis, Audit und lokalisierte Benachrichtigung mit Zielabteilungsdaten. Sie setzt die neue primäre Abteilung direkt an der Mitgliedschaft; bestehende Mannschaftszuordnungen und der Mitgliedschaftstarif bleiben erhalten. API, Webprofil und App-Modell liefern danach dieselbe primäre Abteilung. Die kombinierte Regression für Änderungsanträge, Aufnahme, Mobile-Parität und Mannschaftsverwaltung besteht mit **33 Tests und 469 Assertions**. T020c3 und T020c5 sind abgeschlossen; kontrollierte produktive Migration und echte Browser-/Geräteabnahmen wurden nicht ausgeführt.

### 26.09.2026 – Tarifwechsel als Änderungsantrag

- Tarifwechsel sind als eigener `membership_change`-Antrag lokal regressiv abgesichert: Web und API speichern den Zieltarif, Beitragsvorschau und Nachricht, ohne die bestehende Mitgliedschaft sofort zu ändern. Wiederholte eigene API-Anträge aktualisieren denselben offenen Änderungsantrag statt parallele Tarifwechsel für dieselbe Person anzulegen.
- Die Freigabe bleibt vier-augen-pflichtig, setzt erst danach `club_membership_type_id`, Beitrag und Intervall auf der Mitgliedschaft und sendet eine lokalisierte Bestätigung mit `membership_change_confirmed`, Tarif-ID und Tarifnamen. Die fokussierte Lifecycle-Regression besteht mit **8 Tests und 107 Assertions**. T020c2 ist abgeschlossen; reale externe/Geräteabnahmen bleiben unter T020c offen. Keine produktive Migration, externe Abnahme oder Veröffentlichung wurde ausgeführt.

### 26.09.2026 – Änderungsanträge in Web und nativer App

- Mitglieder können im Vereinsprofil und in der nativen Vereinsansicht einen neuen öffentlichen Tarif, eine öffentliche Zielabteilung oder beides auswählen. Web und App zeigen einen offenen Änderungsantrag an und verhindern dadurch weitere Bedienversuche, bis der gemeinsame Lifecycle entschieden wurde. Der Webdialog verwendet vollständige DE/EN/FR/AR-Texte; die App lädt sichtbare Abteilungen aus der bestehenden Organisations-API und sendet denselben vereinsgebundenen Änderungsvertrag.
- Die kombinierte Regression für Änderungsanträge, Aufnahme, Mobile-Parität und Mannschaftsverwaltung besteht mit **33 Tests und 469 Assertions**. Der Web-Oberflächenvertrag, Pint, `git diff --check` und die ausgewählte Dart-Analyse einschließlich des Mobile-Vertragstests sind ohne Befund; der Web-Produktionsbuild besteht mit **1.091 Modulen**. T020c4 ist abgeschlossen. Der Flutter-Test-Runner kann in dieser Umgebung seinen schreibgeschützten SDK-Cache nicht aktualisieren; der betroffene Dart-Code ist statisch ohne Befund. Keine produktive Migration oder Veröffentlichung wurde ausgeführt.

### 26.09.2026 – Readiness-Vertrag für ehemalige Mitglieder

- Der neue Vertrag `former-member-retention-readiness.v1` inventarisiert ehemalige verknüpfte und externe Mitglieder ausschließlich als aggregierte Kategorien: Mitgliedschaftshistorie, Identität/Kontakt, Abrechnung, Zahlungsmandate und Freitextnotizen. Namen, E-Mails, Notizen, IBANs oder Datensatzkennungen werden nicht ausgegeben.
- Der Bericht ist strikt lesend, meldet `productive_erasure_supported: false` und bleibt dauerhaft `no-go`, solange fachliche Aufbewahrungsregeln und rechtliche Freigabe fehlen. Die fokussierte Regression besteht mit **1 Test und 17 Assertions**. T020d3 ist abgeschlossen; produktive Löschung, konkrete Fristenentscheidung, fachliche Freigabe und rechtliche Abnahme bleiben unter T020d offen. Keine produktive Löschung, Migration, Veröffentlichung oder externe Abnahme wurde ausgeführt.

### 26.09.2026 – Lokalisierte Kündigungsbestätigung

- Die gemeinsame Freigabe für Kündigungsanträge sendet weiterhin über den kompatiblen technischen Typ `club.membership_request_approved`, verwendet für `termination` aber eigene Titel, Texte und Lebenszyklusdaten. Die Benachrichtigung enthält das beantragte beziehungsweise bestätigte Austrittsdatum als `requested_termination_on` und `membership_ends_on`; der Mitgliedsstatus bleibt bis zum Scheduler-Lauf geplant beendet und wird nicht vorzeitig gelöscht.
- Die DE/EN/FR/AR-Sprachsätze besitzen eigene Bestätigungstexte. Die fokussierte Kündigungsregression besteht mit **5 Tests und 39 Assertions**; PHP-Syntax ist für die berührten PHP-Dateien grün. T020d2 ist abgeschlossen; Aufbewahrungs- und Löschregeln für ehemalige Mitglieder sowie fachliche/rechtliche Abnahme bleiben unter T020d offen. Keine produktive Löschung, Migration, Veröffentlichung oder externe Abnahme wurde ausgeführt.

### 26.09.2026 – Aufnahme- und Pausenbestätigung

- Die gemeinsame Web-/API-Freigabe für Mitgliedschaftsanträge sendet nach der Aufnahme nun eine lokalisierte Willkommens- und Aufnahmebestätigung mit weiterhin aktivem `club.membership_request_approved`-Routing, Vereinszugang und optionalem Tarifnamen. Pausenfreigaben verwenden denselben kompatiblen technischen Typ, aber eigene Titel, Texte, Lebenszyklusdaten sowie bestätigten Zeitraum.
- Die vorhandenen externen Zugangseinladungen wurden nicht umgebaut, sondern in der Nachbarschaftsregression mit geprüft. Die fokussierte Lifecycle-Regression besteht mit **7 Tests und 79 Assertions**; zusätzliche Einladungs- und Mobile-Paritätsregression besteht mit **10 Tests und 129 Assertions**. PHP-Syntax und Pint sind für die berührten Dateien grün. T020c1 ist abgeschlossen; Abteilungswechsel, Tarifänderungen als eigener Änderungsantrag, vollständige Web/App-Bedienung und reale externe Abnahme bleiben unter T020c offen. Keine produktive Migration, externe Abnahme oder Veröffentlichung wurde ausgeführt.

### 26.09.2026 – Interessenten- und Probetrainingsakten im Backend

- Eine additive, vereinsgebundene Interessentenakte speichert Kontaktdaten, Quelle, Wunschmannschaft und -mitgliedschaftstyp, geplanten Probetrainingstermin, Ergebnis und internen Bearbeitungsstatus, ohne die Person vorzeitig als Vereinsmitglied anzulegen. Vereinsfremde Mannschaften und Tarife sowie unberechtigte Zugriffe werden abgewiesen; Änderungen und Archivierung werden ohne Kontakt- oder Notizwerte auditiert.
- Reicht eine Person mit derselben E-Mail später einen Mitgliedsantrag ein, wird die Akte mit Konto und Antrag verknüpft und auf `application` gesetzt. Erst die berechtigte Annahme setzt `converted`, Ergebnis und Umwandlungszeitpunkt und erzeugt die echte Vereinsmitgliedschaft; eine manuelle Umgehung dieses Übergangs ist gesperrt. T020a1 ist abgeschlossen; die additive Migration wurde nicht produktiv ausgeführt.
- Die Mitgliederarbeitsfläche besitzt jetzt einen eigenen Interessentenbereich. Berechtigte Personen können Kontaktdaten, Quelle, Wunschmannschaft/-tarif, Probetrainingstermin und Ergebnis bearbeiten oder eine nicht umgewandelte Akte archivieren; konvertierte Akten sind schreibgeschützt. Ein statischer Oberflächenvertrag prüft alle API-Aktionen und die vollständigen DE/EN/FR/AR-Sprachsätze. Der Produktionsbuild besteht mit **1.090 Modulen**. T020a2 ist abgeschlossen.
- Die native Vereinsverwaltung verwendet denselben vereinsgebundenen API-Vertrag, lädt alle Ergebnisse einer mehrseitigen Liste, filtert nach Status und bietet Erstellen, Bearbeiten, Termin- und Ergebnispflege, Mannschafts-/Tarifzuordnung sowie bestätigte Archivierung. Umgewandelte Akten werden auch in der App schreibgeschützt dargestellt. Die fokussierte Backendregression für Antrag und Interessentenakte besteht mit **17 Tests und 218 Assertions**; der Web-Oberflächenvertrag, PHP-Syntax, `git diff --check` und die ausgewählte Dart-Analyse einschließlich des neuen Mobile-Vertragstests sind ohne Befund. Der Flutter-Test-Runner kann auf diesem Rechner weiterhin wegen der bestehenden Snap-Ausführungsrichtlinie nicht gestartet werden. T020a3 und T020a sind abgeschlossen; es wurde keine produktive Migration oder Veröffentlichung ausgeführt.

### 26.09.2026 – SEPA-Zustellnachweis v2 ohne vorgetäuschte Providerabnahme

- Der strikt lesende Go/No-Go-Vertrag verlangt nun eigene Staging-Nachweise dafür, dass Delivery- und Bounce-Webhooks beim verwendeten Postmark-Server registriert sind und dass eine aktive IP-Allowlist mit der produktiven Trusted-Proxy-Kette korrekt arbeitet. Eine vorhandene API-Konfiguration oder Allowlist gilt ausdrücklich nicht als Beleg für diese externen Zustände.
- Der Bericht nennt dauerhaft seine Grenzen: Queue-Treiber beweisen keinen laufenden Worker, Providerkonfiguration beweist keine Webhookregistrierung und `delivered` keinen persönlichen Empfang. Alte v1-Evidenz bleibt No-Go; der datensparsame Laufzeitbestand veröffentlicht lediglich, ob die IP-Allowlist aktiv ist, niemals ihre Werte. Die gezielte Readiness- und SEPA-Nachrichtenregression besteht mit **21 Tests und 176 Assertions**. T007d3b1a ist abgeschlossen; echte Staging-Zustellung, Bounce, Worker-Neustart und fachliche Abnahme bleiben unter T007d3b2 offen.
- Nachgeschärft wurde außerdem die datensparsame Evidenzreferenzprüfung: UUID-/lange Hex-Providerkennungen werden nicht als interne Review-Referenz akzeptiert, auch wenn der übrige Staging-Nachweis vollständig wirkt. Die fokussierte Readiness-Regression besteht mit **6 Tests und 32 Assertions**; der Repository-only-Prüfer bleibt ohne Runtime/Evidenz erwartungsgemäß `no-go`. T007d3b1b ist abgeschlossen; T007d3b2 bleibt offen.

### 26.09.2026 – Rückfragen und Warteliste für Mitgliedschaftsanträge

- Mitgliedschaftsanträge besitzen jetzt die offenen Zustände `pending`, `information_requested` und `waitlisted`. Berechtigte Mitgliederverwalter können eine konkrete Rückfrage senden, eine optionale Wartelistennotiz hinterlegen und wartende Anträge später annehmen oder ablehnen. Offene Anträge bleiben in den Verwaltungsübersichten sichtbar; abgeschlossene Anträge bleiben unveränderlich.
- Antragstellende sehen die Rückfrage in Web und App, können eine Antwort sowie über die API ergänzte Formulardaten und Dokumentbestätigungen einreichen und den Antrag damit wieder zur Prüfung stellen. Vereinszuordnung, Antragstelleridentität und `members.approve` werden serverseitig geprüft. Jeder Zustandswechsel wird ohne Inhalt der Rückfrage oder Antwort auditiert und löst eine lokalisierte Benachrichtigung aus.
- Web, API und native App verwenden denselben Lebenszyklus und DE/EN/FR/AR-Texte. Die fokussierte Aufnahme-, Einladungs-, Kündigungs-, Rechte- und App-Paritätsregression besteht mit **37 Tests und 651 Assertions**; der neue Kernablauf allein umfasst **7 Tests und 101 Assertions**. Pint, JSON-, Routen-, Diff- und Dart-Analyse sind ohne Befund; der Web-Produktionsbuild besteht mit **1.088 Modulen**. Ein nativer API-Vertragstest wurde ergänzt, kann auf diesem Rechner wegen der bestehenden Snap-Ausführungsrichtlinie jedoch nicht über den Flutter-Test-Runner gestartet werden. T020b ist abgeschlossen; die additive Migration wurde nicht produktiv ausgeführt und es wurde nichts veröffentlicht.

### 26.09.2026 – Austrittsprüfung für Forderungen, Material und Zugänge

- Der bestehende Kündigungsablauf prüft offene beziehungsweise überfällige Rechnungen und aktive Materialausleihen jetzt sowohl beim Einreichen als auch erneut unmittelbar vor der Freigabe. Nach dem Antrag neu entstandene Forderungen können dadurch nicht unbemerkt bis zur bestätigten Beendigung gelangen; zurückgegebenes Material gibt den Ablauf wieder frei.
- Die vorhandene Zugriffsübergabe erstellt bei der Kündigungsfreigabe weiterhin eine datierte Prüfung der Rollen und Vertretungen. Zum Enddatum werden Mannschaftszugriffe entfernt, eine freigegebene Nachfolge angewendet und verbleibende Rollen beziehungsweise Delegationen entzogen. Die fokussierte Aufnahme- und Kündigungsregression besteht mit **12 Tests und 134 Assertions**; Pint ist ohne Befund. T020d1 ist abgeschlossen, während Kündigungsbestätigung und Regeln für ehemalige Mitglieder unter T020d offen bleiben.

### 26.09.2026 – Vollständige Mitglieder- und Personenakten

- Registrierte Mitglieder und externe Personen ohne Airmius-Konto besitzen nun gleichwertig nutzbare Personenakten mit Name, E-Mail, Telefon, Anschrift, Mitgliedsnummer, Mitgliedschaftstyp, Status sowie Eintritts- und Enddatum. Web und App können externe Akten vollständig anlegen, suchen, anzeigen und bearbeiten; Änderungen werden ohne Kontakt-, Bank- oder Notizwerte auditiert.
- Die frei konfigurierbaren Mitgliedschaftstypen bilden aktive, passive, fördernde, befristete und Ehrenmitgliedschaften ab und gelten jetzt auch für externe Personen. Bei Einladung, Kontoverknüpfung und kontrollierter Dubletten-Zusammenführung bleiben Typ und bisher fehlende Kontaktdaten erhalten. Vereinsfremde Mitgliedschaftstypen werden abgewiesen.
- Vereinsmitgliedschaft, Airmius-Konto und sportliche Teilnahme bleiben in getrennten Datenbeziehungen. Eine Regression weist dieselbe Personenakte in mehreren Mannschaften aus unterschiedlichen Abteilungen nach, ohne einen zweiten Vereinsdatensatz anzulegen; Nichtmitglieder und externe Trainer beziehungsweise Teilnehmende bleiben über Status, Rolle und externe Akte unabhängig erfassbar.
- Die gemeinsame Mitglieder-, Personenakten-, Dubletten-, Verlauf-, Beitrags-, Einladungs-, Rollen-, Import-, Lebenszyklus-, Rechnungs- und Datenschutzregression besteht mit **65 Tests und 971 Assertions**. Pint, JSON-, Routen-, Diff- und Web-Build-Prüfung sind grün; der Produktionsbuild besteht mit **1.088 Modulen**. Die direkte Dart-Analyse meldet **keine Fehler** und nur vier Deprecation-Hinweise zur künftig vorgesehenen Flutter-RadioGroup-API; der nachgelagerte Telemetrie-Schreibversuch ist durch das schreibgeschützte Benutzerverzeichnis blockiert. Der Flutter-Test-Runner bleibt durch die Snap-Ausführungsrichtlinie des Rechners blockiert. T019b und damit T019 sind abgeschlossen; die additive Migration wurde nicht produktiv ausgeführt und es wurde nichts veröffentlicht.

### 26.09.2026 – Mitgliedschaftsverläufe, Ehrungen und Jubiläen

- Eine additive, vereinsgebundene Verlaufstabelle speichert Einträge getrennt für registrierte und externe Personen. Ehrungen, Jubiläen und Notizen können mit Datum, Titel und Beschreibung manuell gepflegt werden; Status-, Eintritts- und Austrittsdatumsänderungen erzeugen automatisch unveränderliche Verlaufseinträge mit altem und neuem Wert.
- Web und native App zeigen den Verlauf direkt an der Personenakte an und erlauben berechtigten Mitgliedsverwaltern das Anlegen und Löschen manueller Einträge. Vereinsgrenzen, Personenbezug und Schreibrechte werden serverseitig geprüft. Beim kontrollierten Zusammenführen einer Dublette wandert der externe Verlauf in die registrierte Akte; Datenschutzexport und Profildatenlöschung berücksichtigen die Daten ebenfalls.
- Die gemeinsame Mitglieder-, Dubletten-, Beitrags-, Einladungs-, Rollen-, Import-, Lebenszyklus-, Rechnungs- und Datenschutzregression besteht mit **63 Tests und 937 Assertions**. Pint, JSON-, Routen-, Diff- und statische App-Vertragsprüfung sind grün; der Web-Produktionsbuild besteht mit **1.088 Modulen**. Flutter Analyze und die nativen Vertragstests konnten wegen der Snap-Ausführungsrichtlinie des Rechners nicht gestartet werden; der API-Vertrag für Anlegen und Löschen wurde dennoch als ausführbarer Flutter-Test ergänzt. T019d ist abgeschlossen; die additive Migration wurde nicht produktiv ausgeführt und es wurde nichts veröffentlicht.

### 26.09.2026 – Dublettenerkennung und kontrolliertes Zusammenführen

- Externe Personen werden gegen die registrierten Mitglieder desselben Vereins über normalisierte E-Mail-Adresse und exakte Mitgliedsnummer geprüft. Web und App kennzeichnen eindeutige Treffer; mehrdeutige Treffer werden ausdrücklich gesperrt, bis E-Mail oder Mitgliedsnummer korrigiert wurde.
- Eine Zusammenführung verlangt Zielakte, Konfliktregel und die erneute Eingabe der externen E-Mail. Wahlweise bleiben vorhandene Fachdaten führend und nur leere Felder werden ergänzt, oder die externen Mitgliedschafts- und Beitragsdaten werden übernommen. Rollen, Mehrfachrollen, Einzelrechte und Rollenzuordnungen werden in beiden Fällen nicht aus der externen Akte übernommen.
- Eigene Vereinsfelder und Kategorien werden auf die registrierte Akte übertragen, die externe Akte wird danach transaktional entfernt und der Vorgang ohne Bank- oder Notizinhalt auditiert. Der bisherige Einladungsweg kann erkannte Dubletten nicht mehr ungeprüft zusammenführen; Vereins- und Zielgrenzen, falsche Bestätigungen, mehrdeutige Treffer und Mitgliedsnummernkonflikte werden abgewehrt.
- Die gemeinsame Dubletten-, Einladungs-, Mitglieder-, Rollen-, Import-, Lebenszyklus-, Rechnungs- und Datenschutzregression besteht mit **54 Tests und 935 Assertions**. Pint, JSON-, Routen-, Diff- und statische App-Prüfung sind grün; der Web-Produktionsbuild besteht mit **1.088 Modulen**. Flutter Analyze bleibt durch die Snap-Ausführungsrichtlinie des Rechners blockiert; ein nativer API-Vertragstest wurde ergänzt. T019c ist abgeschlossen; es wurde nichts veröffentlicht.

### 26.09.2026 – Familien, Haushalte und abweichende Beitragszahler

- Die vorhandene Familien-/Haushaltskennung ist nun in der Web- und App-Mitgliedspflege sichtbar. Für registrierte und externe Personen kann zusätzlich ein registriertes Mitglied desselben Vereins als Beitragszahler gewählt werden; vereinsfremde Zahler werden serverseitig abgewiesen und eine gelöschte Zahlerverknüpfung wird über den Fremdschlüssel sicher gelöst.
- Manuelle und wiederkehrende Beitragsrechnungen adressieren den ausgewählten Zahler und speichern das begünstigte Mitglied separat. Mehrere Familienmitglieder können deshalb für denselben Zeitraum eigene Rechnungen an denselben Zahler erhalten, ohne von der Dublettenprüfung zusammengefasst zu werden. Benachrichtigung, E-Mail und SEPA-/Bankabgleich folgen weiterhin dem Rechnungsempfänger.
- Web, API, native Mitgliedspflege, Einladungen, externe Mitglieder, Änderungs-Audit und Datenschutzexport führen die neue Beziehung durchgängig. Die fokussierte Mitglieder-, Rollen-, Import-, Lebenszyklus-, Rechnungs- und Datenschutzregression besteht mit **48 Tests und 860 Assertions**. Pint, JSON-, Routen-, Diff- und statische App-Prüfung sind grün; der Web-Produktionsbuild besteht mit **1.088 Modulen**. Flutter Analyze bleibt durch die Snap-Ausführungsrichtlinie des Rechners blockiert. T019a ist abgeschlossen; die additive Migration wurde nicht produktiv ausgeführt und es wurde nichts veröffentlicht.

### 26.09.2026 – Globale Suche und persönliche Suchansichten

- Die vorhandene globale Suche deckt Mitglieder, Vereine, Mannschaften, Termine, Kurse, Produkte, Dateien, Rechnungen und Funktionen ab. Profil-, Termin-, Datei- und Rechnungszugriffe bleiben an bestehende Sichtbarkeits-, Policy-, Vereins- und Finanzrechte gebunden; fremde private Inhalte sind weiterhin ausgeschlossen.
- Eine additive, nutzergebundene `saved_views`-Ablage speichert validierte Ansichten für globale Suche, Mitglieder, Termine, Rechnungen und Dateien. Pro Arbeitsbereich gelten eindeutige Namen, ein Limit von 25 Ansichten, eine begrenzte Konfigurationsgröße und strikte Eigentümertrennung. Suchbegriff, Ergebnistyp, Fachfilter, Sortierung, Bereich, Verein, Mannschaft und Ordnerkontext lassen sich abhängig von der Arbeitsfläche in Web und App speichern, erneut anwenden und löschen.
- Mitgliederansichten speichern Suche, Status, Laufzeit und Verein; Rechnungsansichten Status beziehungsweise App-Zeitraum und Verein; Terminansichten Suche, Zeitraum, Typ, Sichtbarkeit, Sport-, Vereins- und Mannschaftsbezug; Dateiansichten Suche, Sortierung, Seitengröße, Ablagebereich und Ordner. Jede Anwendung läuft erneut durch die bestehenden serverseitigen Berechtigungs- und Bereichsprüfungen.
- Datenschutzexport und selektive Profildatenlöschung berücksichtigen gespeicherte Ansichten. Die breite Such-, Ansichten-, Datenschutz-, Mitglieder-, Rechnungs-, Termin- und Dateiregression besteht mit **80 Tests und 1.421 Assertions**; die abschließende fokussierte Backend-Suite besteht mit **27 Tests und 606 Assertions**. Der Web-Produktionsbuild mit **1.088 Modulen**, Routen-, JSON-, Pint- und Diff-Prüfung sind grün. Flutter bleibt durch die Snap-Ausführungsrichtlinie des Rechners blockiert; API-Client, Modell, Repository, vier Fachoberflächen, vier Sprachsätze und ein neuer App-Vertragstest wurden statisch geprüft. T018d1 bis T018d3 sowie T018 sind abgeschlossen. Die additive Migration wurde nicht produktiv ausgeführt und es wurde nichts veröffentlicht.

### 26.09.2026 – Gültigkeitsdatum und zentrale Warnung für Sportlizenzen

- Das vorhandene Lizenznummernfeld besitzt nun ein optionales Gültigkeitsdatum für Airmius-Konten und externe Vereinsmitglieder. Persönliches Profil, Web-Mitgliedspflege, native Mitgliedspflege, API, Einladung/Verknüpfung und Excel-/CSV-Import verwenden denselben Datenpfad; Änderungen an Vereinsmitgliedern werden weiterhin auditiert.
- Berechtigte Mitgliederverwaltungen sehen abgelaufene und innerhalb von 90 Tagen auslaufende Lizenzen im Fristenblock der zentralen Arbeitsübersicht. Aktive Vereinszuordnung, bestehendes `members.view` und Vereinsgrenzen werden berücksichtigt; Personen ohne Lizenznummer oder Gültigkeitsdatum erzeugen keine falsche Warnung.
- Datenschutzkorrektur, Export, Aufbewahrungsbereinigung und Datenlöschung berücksichtigen das neue Datum. Die additive Migration ist vorwärts und rückwärts geprüft; die Importvorlage hält ihre Auswahlfelder trotz zusätzlicher Spalte in den richtigen Spalten.
- Die breite Dashboard-, Mitgliedschafts-, Einladungs-, Import-, Profil- und Datenschutzregression besteht mit **60 Tests und 1.288 Assertions**; die abschließende fokussierte Suite besteht mit **30 Tests und 652 Assertions**. Web-Produktionsbuild, PHP-Syntax, JSON-Prüfung, Pint und Diff-Prüfung sind grün. Flutter bleibt lokal durch die Snap-Ausführungsrichtlinie blockiert; API-Modell, Editor, Übersetzungen und Übertragung wurden statisch geprüft. T018c und T018c4 sind abgeschlossen. Die additive Migration wurde nicht produktiv ausgeführt und es wurde nichts veröffentlicht.

### 26.09.2026 – Mannschaftsaufgaben in der zentralen Arbeitsübersicht

- Die zentrale Übersicht übernimmt die bereits berechneten Aufgaben des Mannschaftsalltags. Dazu gehören ausstehende Teilnahmeantworten, fehlende Fahrkapazität, offene Teamgebühren, Sorgeberechtigten-Verknüpfungen und Saisonplanung; der reine Zustand „Mannschaftsalltag stabil“ wird nicht als offene Aufgabe gezählt.
- Direkte Mannschaftsmitglieder und berechtigte Mannschaftsverwaltungen erhalten nur Aufgaben aus ihren zulässigen Teams. Der erste fachliche Arbeitsschritt wird als Ziel verwendet; die native Tagesansicht führt in die Mannschaftsübersicht.
- Die gemeinsame Dashboard-, Mannschaftsalltag-, Mitgliedschafts-, Dokument-, Vertrags-, Inventar-, Zugriffs-, Rollen- und Mobile-Web-Regression besteht mit **74 Tests und 1.336 Assertions**. Pint, PHP-Syntax, Diff-Prüfung und der Web-Produktionsbuild sind grün. Flutter-/Dart-Ausführung bleibt lokal durch die Snap-Richtlinie blockiert; App-Verknüpfung, Übersetzungen und Widget-Erwartung wurden statisch geprüft. T018c3 ist abgeschlossen; das noch fehlende Gültigkeitsmodell für Trainer- und Sportlizenzen hält T018c offen. Keine produktive Migration oder Veröffentlichung wurde ausgeführt.

### 26.09.2026 – Fehlende Pflichtnachweise und auslaufende Fristen

- Die Arbeitsübersicht erkennt bei offenen Mitgliedsanträgen fehlende Pflichtbestätigungen und Bestätigungen, deren dokumentgebundene Version nicht mehr der aktuell geforderten Version entspricht. Mitgliedschaftstypgebundene Dokumente werden nur beim passenden Antrag geprüft.
- Aktive Betriebsverträge mit nahender Kündigungs- oder Endfrist, Vereinsdokumente mit nahendem Gültigkeitsende sowie auslaufende Steuerbefreiungen und Verbandszugehörigkeiten erscheinen als eigener Fristenblock. Jeder Datenbereich wird nur bei seinem bestehenden Finanz-, Dokument- oder Rechtsstammdatenrecht ausgewertet.
- Web und native Tagesansicht zeigen die neuen Bereiche mit DE-/EN-/FR-/AR-Beschriftung und passenden Arbeitszielen. Die zusammenhängende Dashboard-, Antrags-, Mitgliedschaftslebenszyklus-, Vereinsdokument-, Vertrags-, Rechtsstammdaten-, Rollen- und Mobile-Web-Regression besteht mit **63 Tests und 1.066 Assertions**. Pint, PHP-Syntax, Diff-Prüfung und der Web-Produktionsbuild sind grün. Flutter- und Dart-Ausführung bleiben durch die lokale Snap-Richtlinie blockiert; die App-Verknüpfungen, Übersetzungsschlüssel und Widget-Erwartungen wurden statisch geprüft. T018c2 ist abgeschlossen; allgemeine Aufgaben und ein belastbares Gültigkeitsmodell für Trainer- beziehungsweise Sportlizenzen bleiben unter T018c offen. Keine produktive Migration oder Veröffentlichung wurde ausgeführt.

### 26.09.2026 – Berechtigungsabhängige Arbeitsübersicht

- Web-Dashboard und native Tagesansicht bündeln offene Mitgliedschafts- und Teambeitrittsanträge, überfällige Rechnungen sowie ausstehende Inventar- und Zugriffsfreigaben. Leere Bereiche werden ausgeblendet; jedes sichtbare Element führt in den passenden Arbeitsbereich.
- Die Ermittlung verwendet die getrennten Vereins-, Team-, Finanz- und Inventarrechte. Teilzahlungen werden vom Rechnungsbetrag abgezogen, fremde Vereine bleiben ausgeschlossen und eigene Inventar- oder Zugriffsanträge erscheinen nicht als selbst freigabefähig.
- Der API-Vertrag liefert dieselbe Arbeitsübersicht an die native App. Die neue Oberfläche ist in DE, EN, FR und AR beschriftet; ihre Widget-Testabdeckung wurde ergänzt.
- Die gemeinsame Dashboard-, Mitgliedschafts-, Rechnungs-, Inventar-, Zugriffs-, Rollen- und Mobile-Web-Regression besteht mit **58 Tests und 992 Assertions**. PHP-Syntax, Pint, Diff-Prüfung und der Web-Produktionsbuild sind grün. Flutter- und Dart-Ausführung sind lokal durch die Snap-Richtlinie des Rechners blockiert; die Quellverknüpfungen und geänderten Dart-Bereiche wurden deshalb statisch geprüft. T018c1 ist abgeschlossen; Aufgaben, fehlende Dokumente und auslaufende Verträge oder Lizenzen bleiben unter T018c offen. Keine produktive Migration oder Veröffentlichung wurde ausgeführt.

### 25.09.2026 – Persönliche Schnellaktionen im Dashboard

- Das Dashboard bietet neun sichere Schnellaktionsziele und lässt bis zu vier davon als persönliche Favoriten auswählen. Auswahl und Reihenfolge werden pro Benutzer serverseitig gespeichert; ein benutzergebundener lokaler Stand dient nur als UI-Rückfall, wenn die Synchronisierung vorübergehend nicht erreichbar ist.
- Der Präferenzendpunkt validiert Widgets und Schnellaktionen unabhängig, entfernt Duplikate und lehnt unbekannte beziehungsweise mehr als vier Aktionen ab. Bestehende Konten ohne neuen Wert sehen weiterhin Training, Route, Ernährung und Trainingsplan. Die Datenlöschung entfernt auch diese Präferenz.
- Die gezielte Dashboard-Regressionssuite besteht mit **8 Tests und 312 Assertions**; PHP-Syntax und Formatierung sind grün. Der Web-Produktionsbuild besteht mit **1.086 Modulen**. T018a ist abgeschlossen; die additive Migration wurde nicht produktiv ausgeführt.

### 25.09.2026 – Rollenstart und gemeinsame Terminübersicht

- Der bestehende Rollenstart führt Einzelrollen weiterhin direkt in Sportler-, Trainer-, Vereins-, Sponsor- oder Sorgeberechtigtenbereiche und Personen mit mehreren Arbeitskontexten in die Auswahl ihrer zulässigen Arbeitsbereiche.
- Dashboard und native Tagesübersicht verwenden nun dieselbe zentrale Terminsichtbarkeit wie der Kalender. Damit erscheinen auch berechtigte vereinsweite Trainings, Spiele, Sitzungen und Veranstaltungen, während fremde private Termine ausgeschlossen bleiben.
- Ein neuer Verhaltenstest belegt alle vier Terminarten, chronologische Darstellung, Vereinsmitgliedschaft und den Ausschluss eines fremden privaten Termins in Web und API. Die gemeinsame Dashboard-, Rollenstart-, Kalender-, Termin-API- und Mobile-Web-Regression besteht mit **29 Tests und 617 Assertions**. T018b ist abgeschlossen; keine produktive Migration oder Veröffentlichung wurde ausgeführt.

### 25.09.2026 – Getrennte Rechte für Vereinsstammdaten

- Der Vereinsrechtekatalog enthält jetzt eigenständige Rechte für allgemeines Profil, rechtlich/steuerliche Daten, Kontakte sowie Branding/Dokumentvorlagen. Die bisherige Sammelberechtigung `content.manage` leitet alle vier Rechte weiterhin ab; ausdrückliche Ausschlüsse einer einzelnen Fähigkeit behalten Vorrang.
- Eine zentrale Feldgruppenprüfung schützt Web- und API-Updates. Teilaktualisierungen sind möglich, Mischanfragen benötigen jede betroffene Fähigkeit und ein nicht berechtigtes Zusatzfeld verwirft die gesamte Anfrage. Logo und Titelbild sind an das Brandingrecht gebunden.
- API und Webprofil geben private Kontakt-, Rechts- und Dokumentkonfigurationsdaten nur an die jeweils berechtigte Fachrolle aus. Beide Oberflächen veröffentlichen die vier Fähigkeiten; Web und native App zeigen beziehungsweise senden nur erlaubte Bearbeitungsbereiche. Die native Vereinsübersicht nimmt dadurch auch Fachbearbeiter ohne pauschales Verwaltungsrecht auf.
- Die gemeinsame Rollen-, Vertretungs-, Vereinsstruktur-, Dokumenten-, Metadaten-, Stammdaten-, Datei-, Inventar-, Support-, Termin-, Mannschafts- und Finanz-/SEPA-Regression besteht mit **227 Tests und 3.029 Assertions**; die darin enthaltene neue Stammdaten-Suite umfasst **6 Tests und 62 Assertions**. Der Web-Produktionsbuild besteht mit **1.086 Modulen**. PHP-Formatierung, Dart-Formatierung, Dart-Klammerprüfung und Diff-Prüfung sind grün. Flutter-Analyse und Widgettests bleiben wegen des schreibgeschützten Snap-SDKs unter T017f2 offen. T017c2b2c2b2b7 ist abgeschlossen; weitere Datenarten bleiben unter T017c2b2c2b2b8 offen. Keine produktive Migration oder Veröffentlichung wurde ausgeführt.

### 25.09.2026 – Einzelrecht für die ältere Rollenpflege im Vereinsprofil

- Der ältere Web-Endpunkt zum Ändern von Vereinsrollen prüft jetzt `members.roles`. Ein Rollen-Fachbearbeiter ohne pauschales Verwaltungsrecht kann die vorhandene Profilansicht bedienen; ein reiner Stammdaten-Bearbeiter kann keine Rollen ändern.
- Die Web-Nutzlast veröffentlicht `can_manage_roles`, und die Rollen-Schaltflächen richten sich danach. Die fokussierte Rollen- und Stammdatenprüfung besteht mit **13 Tests und 297 Assertions**; der Web-Produktionsbuild besteht weiterhin mit **1.086 Modulen**. T017c2b2c2b2b8a ist abgeschlossen; die weitere Berechtigungsinventur läuft unter T017c2b2c2b2b8b.

### 25.09.2026 – Gleiche Einzelrechte für Web- und API-Mitgliedschaftsverwaltung

- Die verbliebenen Web-Endpunkte verwenden jetzt `members.edit`, `members.approve`, `members.roles`, `finance.edit`, `finance.export` und `metadata.edit` passend zur Aktion. Gemischte Mitgliedsänderungen verlangen zusätzlich das Rollenrecht, sobald Rollenfelder enthalten sind.
- Beitragsregeln, Rechnungen, Zahlungen, Mahnungen, SEPA-/DATEV-Einstellungen und Bankabgleich lassen sich im Web nicht mehr über das pauschale Vereinsverwaltungsrecht umgehen. Bestehende Owner-/Adminrechte sowie die Ableitungen aus `members.manage` und `finance.manage` bleiben erhalten.
- Die fokussierte Rollen-, Mitgliedschafts-, Beitrags-, Rechnungs- und Abstimmungsprüfung besteht mit **50 Tests und 784 Assertions**. T017c2b2c2b2b8b ist abgeschlossen; weitere Datenarten bleiben unter T017c2b2c2b2b8c offen.

### 25.09.2026 – Einzelrecht für die Mannschaftserstellung

- Web und native API prüfen bei der Mannschaftserstellung jetzt `teams.edit` im gewählten Verein. Ein Mannschafts-Fachbearbeiter kann damit ohne pauschales Vereinsverwaltungsrecht anlegen; normale Mitglieder bleiben ausgeschlossen.
- `content.manage` leitet Mannschaftsbearbeitung und -löschung für bestehende Manager- und Akademie-Managerrollen weiterhin ab. Die fokussierte Vereinsrechteprüfung besteht mit **9 Tests und 243 Assertions**; die ergänzende Rollen- und Mannschaftsregression war zuvor bis auf den korrigierten Testaufbau grün. T017c2b2c2b2b8c ist abgeschlossen; die weitere Inventur läuft unter T017c2b2c2b2b8d.

### 25.09.2026 – Getrennte Rechte für Vereinssponsoren

- `sponsors.edit` schützt Anlegen und Bearbeiten, `sponsors.delete` schützt das Löschen. Beide Rechte werden in Web-Nutzlast und Club-API veröffentlicht und von den nativen Clubmodellen übernommen.
- `content.manage` leitet beide Sponsoraktionen für bestehende Rollen ab; eine ausdrückliche Löschsperre bleibt wirksam. Die fokussierte Sponsorprüfung besteht mit **2 Tests und 8 Assertions**. T017c2b2c2b2b8d ist abgeschlossen; weitere Datenarten bleiben unter T017c2b2c2b2b8e offen.

### 25.09.2026 – Fachredaktion für Vereinsbeiträge und Storys

- Die Web- und API-Prüfungen für offizielle Vereinsbeiträge und -Storys verwenden jetzt `content.manage`. Konfigurierte Fachredaktionen können dadurch ohne pauschales Vereinsverwaltungsrecht publizieren; normale Mitglieder bleiben an die vorhandenen Freigabeeinstellungen gebunden.
- Die Feed- und Story-Regression besteht mit **27 Tests und 186 Assertions**. T017c2b2c2b2b8e ist abgeschlossen; weitere Datenarten bleiben unter T017c2b2c2b2b8f offen.

### 25.09.2026 – Zielgebundene Terminserien statt globaler Paketprüfung

- `events.edit` berechtigt jetzt auch zum Anlegen einfacher und wiederkehrender Termine im konkret freigegebenen Verein, in der freigegebenen Abteilung oder Mannschaft. Das persönliche Gratislimit bleibt für öffentliche und andere nicht freigegebene Ziele bestehen; ein Bereichsrecht erweitert weder fremde Vereine noch öffentliche Serien.
- Web- und API-Erstellung verwenden dieselbe zentrale Zielprüfung. Team-Fachredaktionen sehen ihr zugewiesenes Team auch ohne direkte Mannschaftsmitgliedschaft; fremde Teams bleiben verborgen und serverseitig mit 404 geschlossen.
- Die API veröffentlicht globale Serienfähigkeit sowie die erlaubten Vereins- und Mannschafts-IDs getrennt. Web und native App zeigen und senden Seriendaten nur für den aktuell ausgewählten erlaubten Bereich; ältere API-Antworten bleiben kompatibel.
- Die fokussierte Termin-, Rollen- und Scope-Regression besteht mit **31 Tests und 348 Assertions**. Der Web-Produktionsbuild besteht mit **1.086 Modulen**; PHP-Formatierung, Syntax-, Dart-Klammer- und Diff-Prüfung sind grün. Die Dart-Formatierung wurde vollständig angewendet, meldete danach aber weiterhin den bekannten Telemetrie-Schreibfehler des schreibgeschützten SDK-Profils. T017c2b2c2b2b8f1 ist abgeschlossen; die weitere Inventur läuft unter T017c2b2c2b2b8f.

### 25.09.2026 – Getrennte Rechte für Vereinsabonnements

- `subscriptions.view` begrenzt die Vereinsabos in der persönlichen Aboübersicht; `subscriptions.edit` schützt Checkout, Kündigung und Fortsetzung. Ein reiner Betrachter sieht Vertragsstand und Plan, kann den Vertrag aber nicht verändern.
- Bisherige Owner-, Admin-, Manager- und Finanzrollen bleiben über den zentralen Vereinsrechtevertrag kompatibel. `finance.manage` leitet beide Aborechte ab, `finance.view` nur die Ansicht; ausdrückliche Ausschlüsse behalten Vorrang.
- Club- und Aboressourcen veröffentlichen Ansehen-/Bearbeitungsfähigkeiten. Die native Abooberfläche bietet nur bearbeitbare Vereine beim Checkout an und blendet Kündigen/Fortsetzen bei reiner Ansicht aus.
- Die erweiterte Abo-, Mobile-API-, Rollen- und Vereinsrechte-Regression besteht mit **52 Tests und 801 Assertions**; die neue Fachsuite umfasst **2 Tests und 21 Assertions**. Der Web-Produktionsbuild besteht mit **1.086 Modulen**. PHP-Formatierung, Syntax-, Dart-Klammer- und Diff-Prüfung sind grün; die unverändert formatierten Dart-Dateien melden nach Abschluss weiterhin nur den bekannten Telemetrie-Schreibfehler. T017c2b2c2b2b8f2 ist abgeschlossen; die weitere Inventur läuft unter T017c2b2c2b2b8f.

### 25.09.2026 – Getrennte Rechte für Stellen und Ehrenamt

- `jobs.edit` erlaubt das Anlegen und Bearbeiten von Entwürfen, `jobs.publish` ist zusätzlich für Veröffentlichen oder Zurückziehen erforderlich und `jobs.delete` schützt das Löschen. Gemischte Aktionen benötigen alle betroffenen Rechte und bleiben bei fehlender Freigabe vollständig unverändert.
- Die Mannschafts-/Vereinsoberfläche veröffentlicht die drei Fähigkeiten getrennt, setzt für reine Fachbearbeiter neue Einträge als Entwurf auf und zeigt Bearbeiten/Löschen einzeln. Club-API und natives Clubmodell übernehmen dieselben Fähigkeiten. Bestehende Owner-, Admin- und Managerzugriffe bleiben erhalten.
- Die Ausschreibungs-, Recruiting-, Rollen- und Vereinsrechte-Regression besteht mit **36 Tests und 522 Assertions**; die neue Fachsuite umfasst **2 Tests und 9 Assertions**. Der Web-Produktionsbuild besteht mit **1.086 Modulen**. PHP-Formatierung und Build sind grün; die Dart-Formatierung war unverändert und meldete anschließend nur den bekannten Telemetrie-Schreibfehler. T017c2b2c2b2b8f3 ist abgeschlossen; Bewerbungs-Pipeline und weitere Datenarten bleiben unter T017c2b2c2b2b8f offen.

### 25.09.2026 – Getrennte Rechte für die Bewerbungs-Pipeline

- `recruiting.view` schützt die personenbezogene Pipeline und begrenzt Abfragen auf freigegebene Vereine. `recruiting.edit`, `recruiting.contact` und `recruiting.delete` schützen Status und interne Notiz, den In-App-Kontakt sowie das endgültige Löschen unabhängig voneinander.
- Web und native App zeigen Bearbeiten, Chat und Löschen anhand der je Bewerbung veröffentlichten Fähigkeiten. Die Vereins-API und das native Vereinsmodell veröffentlichen zusätzlich den Pipelinezugang; die Mannschafts-/Vereinsoberfläche blendet den Einstieg für reine Bewerbungsbetrachter ein.
- Owner, globale Administratoren sowie bestehende Admin- und Managerrollen behalten ihre bisherigen Pipelineaktionen. Konfigurierte Vereinsrollen funktionieren ohne globale `club.jobs.manage`-Berechtigung, ausdrückliche Aktionssperren bleiben wirksam und fremde Vereine bleiben geschlossen. Die Fähigkeitsberechnung wird je Nutzer und Verein zwischengespeichert, sodass die vorhandene konstante Abfragezahl bei vielen Bewerbungen erhalten bleibt.
- Die Recruiting-, Rollen-, Mannschafts- und Web-Smoke-Regression besteht mit **51 Tests und 822 Assertions**; die neue Fachsuite umfasst **2 Tests und 41 Assertions**. Der Web-Produktionsbuild besteht mit **1.086 Modulen**. PHP-Formatierung, Syntax- und Diff-Prüfung sind grün. Die drei geänderten Dart-Dateien waren bereits formatiert; der Formatter meldete erst danach den bekannten Telemetrie-Schreibfehler des schreibgeschützten Profils. T017c2b2c2b2b8f4 ist abgeschlossen; weitere Datenarten bleiben unter T017c2b2c2b2b8f offen.

### 25.09.2026 – Getrennte Rechte für die Übungsbibliothek

- `training_exercises.view`, `training_exercises.edit` und `training_exercises.delete` trennen Ansicht, Anlegen/Bearbeiten und Entfernen von Vereins- und Mannschaftsübungen. Persönliche Übungen bleiben vollständig beim Ersteller; Vereins- und Teamübungen prüfen den tatsächlichen Zielbereich.
- Bestehende Vereins-Terminverwalter erhalten die drei Rechte weiterhin über `events.manage`, aktive Mitglieder behalten die bisherige Bibliotheksansicht und Teamtrainer ihre bisherigen Bearbeitungs- und Löschmöglichkeiten. Konfigurierte Rollen können auf Verein, Abteilung oder Mannschaft begrenzt werden; ausdrückliche Aktionssperren haben Vorrang.
- Club- und Teamressourcen veröffentlichen getrennte Fähigkeiten für Ansicht, Erstellung, Bearbeitung und Löschen. Die native App bietet nur zulässige Vereins- und Mannschaftsziele zur Erstellung an und zeigt Entfernen unabhängig vom Bearbeitungsrecht.
- Die Übungsbibliotheks-, Rollen-, Mobile-API- und Mannschaftsregression besteht mit **51 Tests und 824 Assertions**; die erweiterte Fachsuite umfasst **4 Tests und 47 Assertions**. PHP-Formatierung, Syntax- und Diff-Prüfung sind grün. Die Dart-Formatierung wurde angewendet und meldete anschließend nur den bekannten Telemetrie-Schreibfehler des schreibgeschützten Profils. T017c2b2c2b2b8f5 ist abgeschlossen; weitere Datenarten bleiben unter T017c2b2c2b2b8f offen.

### 25.09.2026 – Getrennte Vereinsrechte im Commerce-Bereich

- `commerce_addons.purchase`, `commerce_products.edit`, `advertising.edit` und `website_request.create` schützen jeweils nur den zugehörigen Vereinsvorgang. Eine Shopredaktion kann damit keine Add-ons kaufen, eine Werberedaktion keinen Website-Auftrag absenden und umgekehrt.
- Web- und mobile Seller-Nutzlasten veröffentlichen die vier Fähigkeiten je Verein. Add-on-, Produkt-, Kampagnen- und Website-Auswahl bieten nur passende Vereine an; persönliche Angebote, Kampagnen und Website-Anfragen ohne Vereinsbezug bleiben unverändert möglich.
- Owner, Admins sowie bisherige Manager-, Akademie- und Finanzrollen behalten ihren bisherigen Commerce-Zugriff. Normale Mitglieder bleiben ausgeschlossen, konfigurierte Fachrollen benötigen keine globale Rolle mehr und fremde Vereine werden serverseitig abgewiesen.
- Die Commerce-, Marketplace-, Checkout-, Rollen- und Vereinsrechte-Regression besteht mit **59 Tests und 674 Assertions**; die neue Fachsuite umfasst **2 Tests und 35 Assertions**. Der Web-Produktionsbuild besteht mit **1.086 Modulen**. PHP-Formatierung, Syntax- und Diff-Prüfung sind grün; die native Commerce-Datei wurde formatiert und meldete anschließend nur den bekannten Telemetrie-Schreibfehler. T017c2b2c2b2b8f6 ist abgeschlossen; weitere Datenarten bleiben unter T017c2b2c2b2b8f offen.

### 25.09.2026 – Eigenes Zugriffsrecht für das Vereins-Cockpit

- `cockpit.view` ersetzt die feste Owner-/Admin-/Manager-Abfrage für die konkrete Vereinsauswahl. Eine konfigurierte Cockpit-Rolle sieht ausschließlich die Vereine, für die ihr dieses Recht wirksam zugewiesen wurde; eine ausdrückliche Sperre entfernt auch den bisherigen Managerzugang.
- Inertia-Berechtigungen, Modulsuche, Startseitenwahl und Workspace-Auswahl verwenden dieselbe zentrale Zugriffsprüfung. Die Club-API veröffentlicht `can_view_cockpit`; natives Modell, Modulnavigation und Cockpit-Auswahl übernehmen die Fähigkeit und bleiben mit älteren `can_manage`-Antworten kompatibel.
- Owner, Admins, Manager, Akademiemanager und Finanzverantwortliche behalten ihren bisherigen Zugriff. Plattformadministration und bestehendes globales `org.manage` bleiben als globale Verwaltungswege erhalten.
- Die Cockpit-, Suche-, Rollen-, Workspace-, Persona- und Mobile-API-Regression besteht mit **71 Tests und 1.273 Assertions**; die erweiterte Cockpit-Fachsuite umfasst **5 Tests und 230 Assertions**. PHP-Formatierung, Syntax- und Diff-Prüfung sind grün. Die fünf geänderten Dart-Dateien waren bereits formatiert und meldeten anschließend nur den bekannten Telemetrie-Schreibfehler. T017c2b2c2b2b8f7 ist abgeschlossen; weitere Datenarten bleiben unter T017c2b2c2b2b8f offen.

### 25.09.2026 – Durchgängiger Zugang zur Mitglieder- und Finanzarbeitsfläche

- Eine zentrale Vereinsprüfung erkennt jetzt Personen, die in mindestens einem eigenen oder zugeordneten Verein eines der Rechte `members.manage`, `finance.view` oder `members.roles` besitzen. Route, Inertia-Fähigkeit und Vereinsmodul verwenden dieselbe Entscheidung.
- Konfigurierte Fachrollen und zeitlich delegierte Rechte benötigen damit keine feste Managerrolle mehr, um den bereits erlaubten Arbeitsbereich in der Navigation zu erreichen. Werden einem bisherigen Manager alle drei Zugangsrechte ausdrücklich entzogen, verschwinden Vereinsmodul und Arbeitsfläche gleichermaßen.
- Der fokussierte Mitgliedschaftszugang besteht mit **8 Tests und 136 Assertions**. Die gemeinsame Mitglieder-, Rollen-, Navigation-, Cockpit-, Persona- und Suchregression besteht mit **53 Tests und 971 Assertions**. PHP-Syntax und Formatierung sind grün. T017c2b2c2b2b8f8 ist abgeschlossen; die Inventur weiterer fester Rollenprüfungen läuft unter T017c2b2c2b2b8f weiter.

### 25.09.2026 – Berechtigungsgebundene Mitgliedschaftsbenachrichtigungen

- Neue Aufnahme-, Pausen-, Austritts- und Rückzugsanträge werden an Personen mit wirksamem `members.approve` im betroffenen Verein gemeldet. Konfigurierte Fachrollen werden einbezogen; eine ausdrückliche Sperre überstimmt die bisherige Manager-Voreinstellung.
- Der tägliche Abschluss abgelaufener interner und externer Mitgliedschaften meldet das Ergebnis an Rollen mit `members.manage`. Die Nachricht an das ausscheidende Mitglied und die vorhandene Berechtigungsbereinigung bleiben unverändert.
- Die fokussierte Lebenszyklus- und Terminierungsprüfung besteht mit **10 Tests und 89 Assertions**. Die gemeinsame Mitglieder-, Rollen-, Mobile-API- und Benachrichtigungsregression besteht mit **70 Tests und 1.086 Assertions**. PHP-Syntax und Formatierung sind grün. T017c2b2c2b2b8f9 ist abgeschlossen; weitere feste Rollenprüfungen bleiben unter T017c2b2c2b2b8f offen.

### 25.09.2026 – Getrennte Sponsoraktionen in Verwaltungs-API und App

- `/api/v1/sponsor-management` ermittelt sichtbare Vereine aus `sponsors.edit` und `sponsors.delete`. Anlegen und Ändern verlangen das Bearbeitungsrecht im Quell- und Zielverein; Löschen verlangt unabhängig davon das Löschrecht. Plattform-, Outfit- und vereinsübergreifende Änderungen bleiben auf die vorhandene globale Finanz-/Systemverwaltung begrenzt.
- Die Antwort veröffentlicht `can_edit_sponsors` und `can_delete_sponsors` je Verein, `can_edit` und `can_delete` je Sponsor sowie globale Anlegefähigkeiten. Die native Oberfläche filtert Zielvereine und Bereichstypen entsprechend und zeigt Anlegen, Bearbeiten und Löschen unabhängig voneinander an.
- Eine ausdrückliche Löschsperre überstimmt weiterhin die Manager-Voreinstellung. Die fokussierte Sponsorprüfung besteht mit **15 Tests und 147 Assertions**; die gemeinsame Sponsor-, Commerce-, Rollen- und Mobile-API-Regression besteht mit **43 Tests und 829 Assertions**. PHP-Syntax, Formatierung und Diff-Prüfung sind grün. Die Dart-Dateien wurden formatiert; der Formatter meldete anschließend nur den bekannten Telemetrie-Schreibfehler des schreibgeschützten Profils. T017c2b2c2b2b8f10 ist abgeschlossen; weitere feste Rollenprüfungen bleiben unter T017c2b2c2b2b8f offen.

### 25.09.2026 – Geschlossene Inhaltsrechte für offizielle Beiträge und Storys

- Web- und API-Storys sowie die Akteursdarstellung von Feed-Beiträgen verwenden für die Vereinsidentität ausschließlich `content.manage`. Der alte Rückfall auf erhöhte Vereinsrollen wurde entfernt; eine ausdrückliche Sperre greift damit auch für bisherige Manager.
- Für Mannschaftsidentitäten gelten globale Administration, `content.manage` im wirksamen Vereins-/Abteilungs-/Mannschaftsbereich oder die direkte Coach-/Captain-Rolle. Die Feed-Zielauswahl nimmt konfigurierte Inhaltsrollen auf und zeigt fremde beziehungsweise gesperrte Mannschaften nicht als offizielle Veröffentlichungsziele an.
- Die fokussierte Web-/API-Feedprüfung besteht mit **28 Tests und 220 Assertions**. Die gemeinsame Feed-, Rollen-, Mannschafts- und Mobile-Regression besteht mit **61 Tests und 822 Assertions**. PHP-Syntax, Formatierung und Diff-Prüfung sind grün. T017c2b2c2b2b8f11 ist abgeschlossen; weitere feste Rollenprüfungen bleiben unter T017c2b2c2b2b8f offen.

### 25.09.2026 – Bereichsrechte für Teamhinweise und Anwesenheitsstatistiken

- Web- und API-Teambeitrittsanträge sowie freiwillige Teamaustritte benachrichtigen alle Personen, die im konkreten Team über die gemeinsame `manageMembers`-Policy zuständig sind. Dazu zählen Vereinsrechte, Abteilungs-/Teamrollen und direkte Teamverantwortliche; feste Owner/Admin/Manager-Listen entfallen.
- Trainingsanwesenheitsstatistiken verwenden `members.view` im wirksamen Vereins-, Abteilungs- oder Mannschaftsbereich. Direkte Coaches und globale Administration bleiben kompatibel, während eine ausdrückliche Lesesperre für bisherige Manager greift.
- Vereinsaustritte werden an `members.manage`, Einwände gegen eine Entfernung an `members.approve` gemeldet. Die fokussierte Team- und Mitgliedschaftsprüfung besteht mit **15 Tests und 154 Assertions**; die gemeinsame Team-, Termin-, Mitgliedschafts-, Rollen- und Mobile-Regression besteht mit **48 Tests und 772 Assertions**. PHP-Syntax, Formatierung und Diff-Prüfung sind grün. T017c2b2c2b2b8f12 ist abgeschlossen; weitere feste Rollenprüfungen bleiben unter T017c2b2c2b2b8f offen.

### 25.09.2026 – Negative Einzelrechte in der Team-Policy

- Mannschaftsbearbeitung und -löschung werden aus `teams.edit` beziehungsweise `teams.delete` abgeleitet; Aufnahme und Entfernung verwenden `members.approve` beziehungsweise `members.delete`. Owner, Admins und bisherige Manager bleiben durch ihre Vereinsrollen-Voreinstellungen kompatibel.
- Für Mannschaftsrollen bleibt der bisherige Teamverwaltungsweg erhalten, solange `members.roles` nicht ausdrücklich entzogen wurde. Eine zentrale Prüfung erkennt explizite Negativrechte, ohne Owner oder globale Administration einzuschränken.
- Der Verhaltenstest entzieht einem Manager alle fünf Rechte und weist für API-Bearbeitung, Aufnahme, Rollenänderung, Entfernung und Löschung jeweils `403` sowie unveränderte Daten nach. Fokussierte Team-/Rechteprüfung: **18 Tests und 393 Assertions**; gemeinsame Team-, Termin-, Organisations-, Inventar- und Rollenregression: **49 Tests und 858 Assertions**. PHP-Formatierung und Diff-Prüfung sind grün. T017c2b2c2b2b8f13 ist abgeschlossen; weitere feste Rollenprüfungen bleiben unter T017c2b2c2b2b8f offen.

### 25.09.2026 – Durchgängige Einzelrechte für Vereinsabonnements

- Die öffentliche Preisseite und der geschützte Web-Checkout bieten einen Verein nur an, wenn die angemeldete Person dort `subscriptions.edit` besitzt. Konfigurierte Abo-Fachrollen können damit einen Vereinsplan abschließen; reine Leser und ausdrücklich gesperrte bisherige Abrechnungsrollen können den Verein weder auswählen noch als Checkout-Ziel einschleusen.
- Kündigungs-, Verlängerungs-, Fortsetzungs- und Endmeldungen sowie automatische Hinweise auf eine Zugriffseinschränkung gehen an alle Personen mit wirksamem `subscriptions.view`. Eine ausdrückliche Lesesperre entfernt auch bisherige Manager aus dem Empfängerkreis, während Owner und bestehende Abrechnungsrollen über die zentralen Voreinstellungen kompatibel bleiben.
- Die fokussierte Rechte-, Pricing-, Checkout- und Lifecycle-Prüfung besteht mit **20 Tests und 196 Assertions**; die erweiterte Admin-, Zahlungs-, Lokalisierungs- und Mobile-API-Regression besteht mit **33 Tests und 799 Assertions**. PHP-Syntax, Formatierung und Diff-Prüfung sind grün. T017c2b2c2b2b8f14 ist abgeschlossen; weitere feste Rollenprüfungen bleiben unter T017c2b2c2b2b8f offen.

### 25.09.2026 – Geschlossenes Terminrecht für Anwesenheiten

- Die gemeinsame Anwesenheitsprüfung verwendet für vereinsweite Termine durchgängig `events.edit`. Die nachgelagerte feste Owner-/Admin-/Managerliste entfällt, sodass eine ausdrückliche Sperre nicht mehr über diesen Nebenpfad umgangen werden kann.
- Konfigurierte Termin-Fachrollen können Anwesenheiten lesen und erfassen. Direkte Trainer- und Teamverantwortliche behalten den vorhandenen Zugriff auf ihre Mannschaft; fremde Personen und ausdrücklich gesperrte Vereinsmanager bleiben ausgeschlossen.
- Die fokussierte Termin-, Anwesenheits- und Routenprüfung besteht mit **19 Tests und 241 Assertions**; die erweiterte Termin-, Datei-, Erinnerungs-, Mannschafts- und Vereinsrechte-Regression besteht mit **29 Tests und 539 Assertions**. PHP-Syntax, Formatierung und Diff-Prüfung sind grün. T017c2b2c2b2b8f15 ist abgeschlossen; weitere feste Rollenprüfungen bleiben unter T017c2b2c2b2b8f offen.

### 25.09.2026 – Bereichsgebundene Challenge-Erstellung

- Vereins- und Mannschafts-Challenges verwenden `events.edit` im gewählten Vereins-, Abteilungs- oder Mannschaftsbereich. Konfigurierte und delegierte Terminrollen erhalten damit denselben zielgebundenen Zugriff wie bei normalen Terminen; eine ausdrückliche Sperre greift auch für bisherige Manager.
- Direkte Trainer, Captains und weitere Teamverantwortliche können weiterhin Challenges für ihre eigene Mannschaft anlegen. Die bisherige globale Coach-Abkürzung wurde entfernt, weil sie beliebige fremde Mannschafts-IDs akzeptierte; Plattformadministration bleibt erhalten.
- Die Challenge-Fachsuite besteht mit **7 Tests und 69 Assertions**; die gemeinsame Rollen-, Delegations-, Termin-, Mobile- und API-Vertragsregression besteht mit **38 Tests und 660 Assertions**. PHP-Syntax, Formatierung und Diff-Prüfung sind grün. T017c2b2c2b2b8f16 ist abgeschlossen; weitere feste Rollenprüfungen bleiben unter T017c2b2c2b2b8f offen.

### 25.09.2026 – Eigenes Bereichsrecht für das Trainer-Cockpit

- `trainer_cockpit.view` ersetzt die feste Liste aus Trainer- und erhöhten Vereinsrollen. Die bisherigen Owner-, Admin-, Manager-, Akademie-, Finanz- und Trainerrollen behalten den Zugriff über ihre Voreinstellungen; konfigurierte Fachrollen können gezielt ergänzt werden.
- Vereinsweite Zuweisungen zeigen alle Mannschaften des Vereins. Abteilungs- und Mannschaftszuweisungen filtern das Cockpit auf den wirksamen Bereich; direkte Teamverantwortliche behalten ihren bisherigen Zugang. Eine ausdrückliche Sperre entfernt den pauschalen Zugriff einer bisherigen Vereinsrolle.
- Die fokussierte Trainer-, Wochensteuerungs- und Persona-Prüfung besteht mit **13 Tests und 134 Assertions**; die erweiterte Vereinsrechte-, Rollen-, Delegations-, Navigation-, Rollout- und Mobile-Regression besteht mit **51 Tests und 2.464 Assertions**. PHP-Syntax, Formatierung und Diff-Prüfung sind grün. T017c2b2c2b2b8f17 ist abgeschlossen; weitere feste Rollenprüfungen bleiben unter T017c2b2c2b2b8f offen.

### 25.09.2026 – Datenartspezifische Sponsor-Arbeitsfläche

- Der Sponsor-Arbeitsbereich kann neben Sponsor-Personas auch über die einschlägigen Vereinsrechte geöffnet werden. Inertia-Navigation, Workspace-Auswahl, Suche sowie Web- und API-Route verwenden dieselbe zentrale Entscheidung.
- Sponsorverträge werden über `sponsors.edit/delete`, Kampagnen über `advertising.edit` und Website-Aufträge über `website_request.create` gefiltert. Eigene Sponsorprofile und eigene Vorgänge bleiben sichtbar; globale Sponsorverwaltung bleibt erhalten. Eine ausdrückliche Sperre kann den bisherigen pauschalen Managerzugang vollständig schließen.
- Die Rechtefilter laden die Vereinsmenge einmal und halten damit den bestehenden Abfragevertrag ein. Die fokussierte Commerce-, Sponsor- und Workspace-Prüfung besteht mit **12 Tests und 310 Assertions**; die erweiterte Revenue-, Seller-, Sponsor-, Such-, UI- und Mobile-Regression besteht mit **48 Tests und 605 Assertions**. PHP-Syntax, Formatierung und Diff-Prüfung sind grün. T017c2b2c2b2b8f18 ist abgeschlossen; weitere feste Rollenprüfungen bleiben unter T017c2b2c2b2b8f offen.

### 25.09.2026 – Fachlich getrennte Mitgliedschafts- und Abrechnungserinnerungen

- Der tägliche Erinnerungsjob sendet Hinweise auf auslaufende interne und externe Mitgliedschaften an `members.manage`, fällige oder überfällige Beitragsrechnungen an `finance.view` und auslaufende beziehungsweise zahlungsgestörte Vereinsabos an `subscriptions.view`.
- In-App- und E-Mail-Empfänger werden aus derselben zentralen Vereinsrechteprüfung abgeleitet. Konfigurierte Fachrollen werden aufgenommen; ausdrückliche Negativrechte entfernen auch bisherige Manager. Die bestehende Vererbung von Finanzansicht zu Aboansicht bleibt erhalten, sofern sie nicht ausdrücklich gesperrt wird.
- Die fokussierte Erinnerungs-, Rechnungs- und Abo-Lifecycle-Prüfung besteht mit **15 Tests und 133 Assertions**; die erweiterte Mitglieder-, Finanz-, Abo-, Lokalisierungs- und Mobile-Regression besteht mit **27 Tests und 931 Assertions**. PHP-Syntax, Formatierung und Diff-Prüfung sind grün. T017c2b2c2b2b8f19 ist abgeschlossen; weitere feste Rollenprüfungen bleiben unter T017c2b2c2b2b8f offen.

### 25.09.2026 – Bereichsgebundene Trainingslogs im Trainer-Cockpit

- Die Trainingslog-Zugriffsschicht ermittelt verwaltete Athleten jetzt zusätzlich aus `trainer_cockpit.view` im konkreten Team- oder Abteilungsbereich. Eine Fachrolle sieht dadurch die Trainerdaten ihrer zugewiesenen Mannschaft, aber keine Logs anderer Mannschaften desselben Vereins.
- Direkte Teamtrainer und Plattformadministration bleiben kompatibel. Der vorhandene Schutz privater Logs bleibt unverändert; Mannschaftslogs für normale Teammitglieder und Trainerlogs für berechtigte Betreuungspersonen werden weiterhin getrennt behandelt.
- Die fokussierte Cockpit- und Wochensteuerungsprüfung besteht mit **9 Tests und 74 Assertions**; die erweiterte Training-, Analyse-, Datenschutz-, Persona- und Vereinsrechte-Regression besteht mit **27 Tests und 491 Assertions**. PHP-Syntax, Formatierung und Diff-Prüfung sind grün. T017c2b2c2b2b8f20 ist abgeschlossen; weitere feste Rollenprüfungen bleiben unter T017c2b2c2b2b8f offen.

### 25.09.2026 – Bereichsrecht für Trainingspläne

- `training_plans.edit` ersetzt die feste Vereins-Owner-/Trainerprüfung bei gemeinsamen Trainingsplänen. Vereinsweite, Abteilungs- und Mannschaftsrollen können gezielt planen; persönliche Trainingspläne bleiben für alle Sportler verfügbar.
- Die auswählbaren Zielmannschaften werden aus dem wirksamen Bereich des neuen Rechts abgeleitet. Direkte Coaches und Vereinspräsidenten sowie bestehende globale Trainerrollen bleiben kompatibel; eine ausdrückliche Sperre entzieht einer bisherigen Vereinstrainerrolle die gemeinsame Planverwaltung.
- Die fokussierte Plan-, Workflow- und Trainingssystemprüfung besteht mit **16 Tests und 249 Assertions**; die erweiterte Workspace-, Cockpit-, Rollen-, Delegations-, Persona- und Analyse-Regression besteht mit **41 Tests und 335 Assertions**. PHP-Syntax, Formatierung und Diff-Prüfung sind grün. T017c2b2c2b2b8f21 ist abgeschlossen; weitere feste Rollenprüfungen bleiben unter T017c2b2c2b2b8f offen.

### 25.09.2026 – Fahrtenlöschung ohne globalen Club-Admin-Bypass

- Fahrten können nur noch vom jeweiligen Fahrer oder über die bereits vorhandene Plattformadministration gelöscht werden. Eine globale `club_admin`-Rolle ohne Bezug zur Fahrt erhält keine pauschale Löschfreigabe mehr.
- Ein API-Verhaltenstest legt eine fremde Fahrt an, versucht die Löschung mit einem unbeteiligten Club-Admin und weist `403` sowie den unveränderten Datenbestand nach. Die fokussierte Web-/Mobile-Fahrtenprüfung besteht mit **9 Tests und 397 Assertions**; die erweiterte Fahrten- und Sorgeberechtigten-Regression besteht mit **20 Tests und 600 Assertions**. PHP-Syntax, Formatierung und Diff-Prüfung sind grün. T017c2b2c2b2b8f22 ist abgeschlossen; weitere feste Rollenprüfungen bleiben unter T017c2b2c2b2b8f offen.

### 25.09.2026 – Bereichsrecht für Mannschaftskassen

- `team_cashbox.manage` trennt Mannschaftskasse und Strafenkatalog von der Bearbeitung der Mannschaftsstammdaten. Konfigurierbare Rollen und Vertretungen können das Recht vereinsweit, für eine Abteilung oder für eine einzelne Mannschaft erhalten; Manager-, Akademie- und Finanzrollen behalten ihre bisherigen sinnvollen Voreinstellungen.
- Direkte Teamverantwortliche bleiben kompatibel, solange keine ausdrückliche Sperre gesetzt ist. API, Web-Termindetail und Team-Startseite verwenden dieselbe Rechteentscheidung. Eine reine Kassenrolle sieht dabei weder fehlende Anwesenheitsantworten noch Sorgeberechtigteninformationen. Ein Verhaltenstest verwaltet eine zugewiesene Mannschaft, verweigert eine zweite Mannschaft und entzieht anschließend auch einem direkten Coach den Zugriff ausdrücklich.
- Die fokussierte Mannschaftskassen- und Teamalltagsprüfung besteht mit **7 Tests und 160 Assertions**; die gemeinsame Kassen-, Teamalltag-, Rollen-, Vertretungs-, Termin- und Mobile-Regression besteht mit **54 Tests und 864 Assertions**. PHP-Syntax, Formatierung und Diff-Prüfung sind grün. T017c2b2c2b2b8f23 ist abgeschlossen; weitere Datenarten und Bereichspfade bleiben unter T017c2b2c2b2b8f beziehungsweise T017c3b2d2b2b2 offen.

### 25.09.2026 – Geschützte Mannschaftsauswertungen

- Die Wettbewerbs- und Saisonanalyse enthält Anwesenheitsantworten, Zuverlässigkeitswerte, offene Teamgebühren und organisatorische Empfehlungen. Der erreichbare API-Pfad ist deshalb nicht mehr über das allgemeine Mannschafts-Leserecht für jedes Vereinsmitglied verfügbar, sondern über `trainer_cockpit.view` im konkreten Team-/Abteilungsbereich oder über kompatible direkte Teamverantwortung.
- Ein Verhaltenstest verweigert einem gewöhnlichen Vereinsmitglied den Zugriff, erlaubt einer konfigurierten Rolle genau die zugewiesene Mannschaft, sperrt eine fremde Mannschaft und entzieht anschließend auch einem direkten Coach den Zugriff ausdrücklich. Die fokussierte Auswertungs- und Sportjahresprüfung besteht mit **5 Tests und 77 Assertions**; die gemeinsame Trainer-Cockpit-, Wochensteuerungs-, Rollen-, Vertretungs-, Teamalltags- und Planungsregression besteht mit **49 Tests und 751 Assertions**. PHP-Syntax, Formatierung und Diff-Prüfung sind grün. T017c2b2c2b2b8f24 ist abgeschlossen; weitere Datenarten und Bereichspfade bleiben offen.

### 25.09.2026 – Inhalts-Sperre für direkte Teamredaktion

- Direkte Coaches und Captains dürfen weiterhin als Mannschaft veröffentlichen, sofern der Verein dieses Legacy-Recht nicht ausdrücklich entzieht. `content.manage = false` sperrt nun auch diesen Rückfall in der Webauswahl, beim Web-Upload und in der nativen Story-API.
- Der Verhaltenstest kombiniert eine bisherige Vereinsmanagerrolle mit einer direkten Coach-Rolle, weist die abgelehnten Vereins- und Mannschaftsstorys in Web und API sowie den unveränderten Datenbestand nach. Die fokussierte Feedprüfung besteht mit **28 Tests und 222 Assertions**; die gemeinsame Feed-, Team-, Rollen- und Bereichsregression besteht mit **59 Tests und 735 Assertions**. PHP-Syntax, Formatierung und Diff-Prüfung sind grün. T017c2b2c2b2b8f25 ist abgeschlossen; weitere feste Rollen- und Bereichspfade bleiben offen.

### 25.09.2026 – Übungsbibliothek ohne Legacy-Rückfall

- Teammitgliedschaft sowie direkte Coach-/Staff-Rollen gewähren weiterhin den bisherigen Zugriff, solange das jeweilige Ansichts-, Bearbeitungs- oder Löschrecht nicht ausdrücklich gesperrt ist. Eine Sperre greift nun auch dann, wenn die Person die Vereins- oder Mannschaftsübung ursprünglich selbst angelegt hat; nur persönliche Übungen bleiben eigentümergeführt.
- Der Bereichstest weist für einen direkten Coach mit drei Negativrechten eine verborgene Detailansicht sowie abgelehnte Anlage, Änderung und Löschung nach. Die fokussierte Übungsbibliothek besteht mit **4 Tests und 51 Assertions**; die gemeinsame Übungs-, Plan-, Log-, Cockpit-, Rollen- und Vertretungsregression besteht mit **55 Tests und 789 Assertions**. PHP-Syntax, Formatierung und Diff-Prüfung sind grün. T017c2b2c2b2b8f26 ist abgeschlossen; weitere feste Rollen- und Bereichspfade bleiben offen.

### 25.09.2026 – Team-Policy ohne direkte Rollenabkürzung

- `teams.edit = false` sperrt nun auch den Legacy-Pfad aus globalem `team.update` und direkter Teamleitung. `members.approve = false` überstimmt jetzt ebenfalls die sonst eigenständig berechtigten Coach-/Captain-Rollen bei Aufnahme und Einladungen.
- Der bestehende Negativrechtstest führt dieselben Bearbeitungs-, Aufnahme-, Rollenänderungs-, Entfernungs- und Löschversuche nun zusätzlich mit einer direkten Captain-Zuweisung aus. Die fokussierte Teamverwaltung besteht mit **9 Tests und 93 Assertions**; die gemeinsame Team-, Rollen-, Termin-, Teamalltags- und Mannschaftskassenregression besteht mit **49 Tests und 813 Assertions**. PHP-Syntax, Formatierung und Diff-Prüfung sind grün. T017c2b2c2b2b8f27 ist abgeschlossen; weitere feste Rollen- und Bereichspfade bleiben offen.

### 25.09.2026 – Challenge-Sperre für direkte Teamrollen

- Direkte Teamverantwortliche dürfen Mannschafts-Challenges weiterhin ohne zusätzliche Vereinsrolle anlegen. Eine ausdrücklich gesetzte Sperre für `events.edit` überstimmt nun auch diesen Legacy-Zugang, während globale Coaches ohne Bezug zur Mannschaft weiterhin ausgeschlossen bleiben.
- Der vorhandene Bereichstest deckt jetzt zusätzlich einen direkten, aber ausdrücklich gesperrten Coach ab. Die Challenge-Fachsuite besteht mit **7 Tests und 70 Assertions**; die gemeinsame Challenge-, Termin-, Rollen- und Vertretungsregression besteht mit **52 Tests und 706 Assertions**. PHP-Syntax, Formatierung und Diff-Prüfung sind grün. T017c2b2c2b2b8f28 ist abgeschlossen; weitere feste Rollen- und Bereichspfade bleiben offen.

### 25.09.2026 – Entzogene Terminrechte für frühere Ersteller

- Das Eigentümerrecht bleibt bei persönlichen Terminen erhalten. Vereins- und Mannschaftstermine folgen nach ihrer Anlage den aktuellen Bereichsrechten, sodass ein späterer Entzug von `events.edit` beziehungsweise `events.delete` auch den ursprünglichen Ersteller bindet.
- Dieselbe Negativprüfung gilt für direkte Teamverantwortliche bei der Anwesenheitserfassung. Ein Verhaltenstest kombiniert Termin-Ersteller, direkte Coach-Rolle und beide Negativrechte und weist verweigerte Policy-Aktionen, verweigerte Anwesenheitsansicht/-änderung sowie unveränderte Teilnehmerdaten nach.
- Die fokussierte Termin- und Anwesenheitsprüfung besteht mit **17 Tests und 208 Assertions**; die gemeinsame Termin-, Erinnerungs-, Datei-/Routen-, Rollen- und Vertretungsregression besteht mit **62 Tests und 870 Assertions**. PHP-Syntax, Formatierung und Diff-Prüfung sind grün. T017c2b2c2b2b8f29 ist abgeschlossen; weitere feste Rollen- und Bereichspfade bleiben offen.

### 25.09.2026 – Rechteentzug bei bestehenden offiziellen Storys

- Offizielle Vereinsstorys folgen beim Löschen dem aktuellen Recht `content.manage`. Bei offiziellen Mannschaftsstorys gilt das Recht im konkreten Team- oder Abteilungsbereich; direkte Coaches und Captains bleiben nur ohne ausdrückliche Sperre kompatibel. Die ursprüngliche technische Autorenschaft umgeht einen späteren Rechteentzug nicht mehr.
- Persönliche Storys bleiben unverändert durch ihre Eigentümer löschbar. Der Verhaltenstest legt eine bereits vorhandene offizielle Mannschaftsstory an, entzieht dem Autor das Inhaltsrecht und weist die abgelehnte Löschung im Webvertrag sowie per nativer API und den unveränderten Datenbestand nach.
- Die fokussierte Web-/API-Feedprüfung besteht mit **28 Tests und 226 Assertions**; die gemeinsame Feed-, Team-, Rollen- und Rechte-Regression besteht mit **59 Tests und 739 Assertions**. PHP-Formatierung und Diff-Prüfung sind grün. T017c2b2c2b2b8f30 ist abgeschlossen; weitere feste Rollen- und Bereichspfade bleiben offen.

### 25.09.2026 – Aktuelle Bereichsrechte beim Challenge-Abbruch

- Der Abbruch einer offiziellen Vereins- oder Mannschafts-Challenge folgt jetzt `events.edit` im aktuellen Zielbereich. Damit verliert auch der ursprüngliche Ersteller die Abbruchmöglichkeit nach einem ausdrücklichen Rechteentzug; eine aktuell zuständige Vereins-, Abteilungs- oder Mannschaftsrolle kann den Vorgang übernehmen.
- Direkte Teamverantwortliche bleiben ohne ausdrückliche Sperre kompatibel. Persönliche Einladungs-Challenges und öffentliche Challenges behalten ihre bisherige Erstellersteuerung, Plattformadministration ihren Vollzugriff. `can_cancel` und der schreibende Endpunkt verwenden dieselbe zentrale Entscheidung.
- Der Challenge-Fachtest besteht mit **7 Tests und 78 Assertions**; die gemeinsame Challenge-, Termin-, Rollen- und Vertretungsregression besteht mit **53 Tests und 719 Assertions**. PHP-Formatierung und Diff-Prüfung sind grün. T017c2b2c2b2b8f31 ist abgeschlossen; weitere feste Rollen- und Bereichspfade bleiben offen.

### 25.09.2026 – Konsistentes Team-Flag für eigene Übungen

- Die native Team-Antwort berücksichtigt bei direkten Teamverantwortlichen nun ebenfalls eine ausdrückliche Sperre für `training_exercises.edit`. Damit bietet die Oberfläche keine Anlageaktion mehr an, die der abgesicherte Übungs-Endpunkt anschließend ablehnen muss.
- Das Tarifmerkmal für eigene Vereinsübungen bleibt eine zusätzliche Voraussetzung. Der Bereichstest aktiviert deshalb bewusst einen Starter-Tarif und weist bei einem direkten, aber gesperrten Coach sowohl das negative Bedienflag als auch die abgelehnten Lese-, Anlage-, Änderungs- und Löschaktionen nach.
- Die fokussierte Übungsbibliothek besteht mit **4 Tests und 53 Assertions**; die gemeinsame Übungs-, Team-, Rollen- und Rechte-Regression besteht mit **42 Tests und 703 Assertions**. PHP-Formatierung und Diff-Prüfung sind grün. T017c2b2c2b2b8f32 ist abgeschlossen; weitere feste Rollen- und Bereichspfade bleiben offen.

### 25.09.2026 – Dateianlage ohne Legacy-Bypass

- Ein globales Legacy-Recht wie `file.upload` überstimmt im konkreten Vereins-, Mannschafts- oder Terminkontext keine ausdrückliche Sperre für `files.edit` mehr. Die gemeinsame Bereichsauflösung berücksichtigt dabei Vereins- und Mannschaftstermine sowie deren tatsächlichen Verein.
- Web-Arbeitsfläche, Web-Upload, Web-Ordneranlage, native Dateiarbeitsfläche, native Upload-/Ordner-Endpunkte und der kompakte Termin-Dateikontext liefern dieselbe Entscheidung. Persönliche Ablagen und Legacy-Nutzer ohne ausdrücklichen Entzug behalten ihr bisheriges Verhalten.
- Der fokussierte Dateimanager besteht mit **18 Tests und 300 Assertions**; die gemeinsame Datei-, Termin-, Rollen-, Vertretungs- und Upload-Regression besteht mit **57 Tests und 697 Assertions**. PHP-Formatierung und Diff-Prüfung sind grün. T017c2b2c2b2b8f33 ist abgeschlossen; bestehende Dateiaktionen und weitere feste Rollenpfade bleiben offen.

### 25.09.2026 – Negativrechte für bestehende Dateien und Ordner

- `files.view`, `files.edit`, `files.delete`, `files.export` und `files.share` werden bei bestehenden Vereins-, Mannschafts- und Termindateien als ausdrückliche Sperren priorisiert. Mitgliedschaft, technische Autorenschaft und alte globale Datei-Rechte können den jeweiligen Entzug nicht mehr umgehen.
- Die Datei- und Ordner-Policies verwenden dieselbe Bereichsauflösung wie die Arbeitsflächen. Ein entzogenes Ansichtsrecht schließt zusätzlich die Web- und API-Arbeitsfläche des Bereichs; die übrigen Negativrechte spiegeln sich einzeln in `access_rights` und in den schreibenden Endpunkten. Persönliche Dateien sowie unabhängig sichtbare Chat- und Mitgliedschaftsantragsdokumente behalten ihre spezialisierten Zugriffswege.
- Der fokussierte Dateimanager besteht mit **19 Tests und 317 Assertions**; die gemeinsame Datei-, Termin-, Richtlinien-, Chat-, Rollen- und Vertretungsregression besteht mit **71 Tests und 804 Assertions**. PHP-Formatierung und Diff-Prüfung sind grün. T017c2b2c2b2b8f34 ist abgeschlossen; weitere feste Rollen- und Bereichspfade bleiben offen.

### 25.09.2026 – Mandantensichere Termin-Echtzeitkanäle

- Die privaten Broadcast-Kanäle `events.club.*` und `events.team.*` akzeptieren keine Person mehr allein wegen eines globalen Legacy-Rechts wie `event.create` oder `event.update`. Erforderlich sind tatsächliche Mitgliedschaft oder `events.edit` im exakt angefragten Vereins-, Abteilungs- beziehungsweise Mannschaftsbereich.
- Der Channel-Test prüft normale Mitglieder, eine teamgebundene Terminrolle, einen fremden Verein samt fremder Mannschaft und einen mandantenfremden Inhaber beider alten globalen Rechte direkt gegen die registrierten Autorisierungsregeln.
- Die fokussierte Channel-Prüfung besteht mit **1 Test und 7 Assertions**; die gemeinsame Echtzeit-, Chat-, Termin-, Rollen- und Vertretungsregression besteht mit **48 Tests und 477 Assertions**. PHP-Formatierung und Diff-Prüfung sind grün. T017c2b2c2b2b8f35 ist abgeschlossen; weitere feste Rollen- und Bereichspfade bleiben offen.

### 25.09.2026 – Trainer-Cockpit ohne direkten Rollen-Bypass

- Direkte Coach-, Trainer-, Captain- und weitere Teamleitungsrollen werden im Cockpit und in der Trainingslog-Zugriffsschicht nur noch berücksichtigt, wenn `trainer_cockpit.view` im zugehörigen Verein nicht ausdrücklich entzogen wurde. Dasselbe gilt für den Rückfall einer globalen Trainerrolle auf ihre zugeordneten Teams.
- Der Bereichstest ergänzt einen direkten Coach mit Negativrecht und weist sowohl den verweigerten Cockpit-Zugang als auch den verborgenen fremden Trainingslog nach. Ungesperrte Coaches, private Athletenlogs und konfigurierte Team-/Abteilungsrollen behalten die vorhandenen Zugriffsregeln.
- Die fokussierte Cockpit- und Trainingsworkflow-Prüfung besteht mit **11 Tests und 99 Assertions**; die gemeinsame Log-, Plan-, Analyse-, Rollen- und Vertretungsregression besteht mit **56 Tests und 830 Assertions**. PHP-Formatierung und Diff-Prüfung sind grün. T017c2b2c2b2b8f36 ist abgeschlossen; weitere feste Rollen- und Bereichspfade bleiben offen.

### 25.09.2026 – Trainingsplanung ohne direkten Coach-Bypass

- Direkte Coach- und Club-President-Rollen vermitteln die gemeinsame Trainingsplanverwaltung nur noch ohne ausdrücklichen Entzug von `training_plans.edit`. Dieselbe Sperre entfernt die betroffene Mannschaft aus dem serverseitig validierten Zielkatalog, sodass manipulierte API-Anfragen ebenfalls geschlossen bleiben.
- Persönliche Trainingspläne bleiben für Sportler verfügbar. Ungesperrte direkte Coaches, globale Trainer-Personas und konfigurierte Vereins-, Abteilungs- oder Teamrollen behalten ihre bisherigen Planungswege innerhalb der jeweils erlaubten Zielmannschaften.
- Die fokussierte Plan- und Trainingsworkflow-Prüfung besteht mit **12 Tests und 235 Assertions**; die gemeinsame Plan-, Log-, Übungs-, Cockpit-, Rollen- und Vertretungsregression besteht mit **58 Tests und 847 Assertions**. PHP-Formatierung und Diff-Prüfung sind grün. T017c2b2c2b2b8f37 ist abgeschlossen; weitere feste Rollen- und Bereichspfade bleiben offen.

### 25.09.2026 – Private Kommunikation ohne globale Rollenabkürzung

- Die Conversation-Policy erlaubt den Zugriff ausschließlich tatsächlichen Teilnehmenden. Globale Coach- oder Vereinsverwaltungsrollen sowie der allgemeine Plattformvollzugriff werden nicht als Berechtigung zum Lesen privater Unterhaltungen interpretiert; die bereits strengen Web-, API- und Broadcast-Pfade erhalten damit eine passende zentrale Policy.
- Die Comment-Policy bildet die vorhandene Fachregel zentral ab: Bearbeiten darf nur der Kommentarautor, löschen dürfen Kommentarautor oder Autor des kommentierten Beitrags. Web und API verwenden jetzt diese gemeinsame Entscheidung; eine globale `club_admin`-Rolle vermittelt keinen Zugriff auf fremde Kommentare.
- Der fokussierte Policy-Test besteht mit **1 Test und 12 Assertions**; die gemeinsame Feed-, Kommentar-, Chat- und Echtzeitregression besteht mit **51 Tests und 450 Assertions**. PHP-Formatierung und Diff-Prüfung sind grün. T017c2b2c2b2b8f38 ist abgeschlossen; weitere feste Rollen- und Bereichspfade bleiben offen.

### 25.09.2026 – Vereinsprofil ohne pauschalen Web-Rückfall

- Die Web-Profilseite verwendet für allgemeine Vereinsdaten, Rechts-/Steuerdaten, Kontakte, Branding und Rollenpflege ausschließlich die jeweils veröffentlichten Einzelrechte. `viewer.can_manage` schaltet diese Fachbereiche nicht mehr pauschal frei und kann ausdrückliche Negativrechte deshalb nicht in der Oberfläche umgehen.
- Ein Verhaltenstest kombiniert eine bestehende Managerrolle mit entzogenen Rechts-, Branding- und Rollenrechten. Der Server liefert weiterhin den kompatiblen allgemeinen Managementstatus, entfernt aber sensible Daten und setzt die Einzelaktionen auf `false`; ein Quellvertrag sichert die ausschließliche Verwendung dieser Flags in der Vue-Seite.
- Die fokussierte Profil-, Rechte- und Rollenprüfung besteht mit **29 Tests und 507 Assertions**; die erweiterte Profil-, Stammdaten-, Metadaten-, Mitgliedschafts- und Mobile-Regression besteht mit **52 Tests und 795 Assertions**. Der Produktions-Build ist erfolgreich, PHP-Formatierung und Diff-Prüfung sind grün. T017c2b2c2b2b8f39 ist abgeschlossen; weitere pauschale Bedienpfade bleiben offen.

### 25.09.2026 – Native Brandingaktionen ohne pauschalen Rückfall

- Die native Vereinsprofilansicht zeigt und aktiviert die Kameraaktionen für Logo und Titelbild ausschließlich mit dem veröffentlichten Branding-Einzelrecht. Ein weiterhin wahrer allgemeiner Managerstatus kann eine ausdrückliche `club.branding.edit`-Sperre damit nicht mehr in der Oberfläche umgehen.
- Der bestehende Verhaltenstest weist die Kombination aus Managerrolle und ausdrücklicher Branding-Sperre serverseitig nach; ein ergänzter Quellvertrag sichert beide Callbacks und beide sichtbaren Aktionen auf `canEditClubBranding` ab.
- Die fokussierte Profil- und Mobile-API-Prüfung besteht mit **25 Tests und 397 Assertions**. PHP-Formatierung und Diff-Prüfung sind grün; Flutter-Analyzer und Widgettests bleiben wegen der lokalen Snap-Sperre unter T017f2 offen. T017c2b2c2b2b8f40 ist abgeschlossen; weitere pauschale Bedienpfade bleiben offen.

### 25.09.2026 – Getrennte Metadatenrechte in Web und nativer App

- Der Club-API-Vertrag veröffentlicht `can_view_metadata` und `can_edit_metadata` jetzt getrennt sowohl am Club als auch im Viewer-Kontext. Die native Modellkette übernimmt beide Werte und hält für ältere Antworten nur dann den bisherigen Manager-Rückfall bereit, wenn das neue Bearbeitungsfeld vollständig fehlt.
- Das Webprofil und die native Organisationsansicht zeigen den Metadatenzugang ausschließlich mit dem Ansichtsrecht. Der native Editor an Vereinsmitgliedern verwendet das Bearbeitungsrecht; ein allgemeiner Managerstatus mit ausdrücklichem Entzug schaltet weder Ansicht noch Bearbeitung wieder frei.
- Die fokussierte Metadaten-, Profil- und Mobile-API-Regression besteht mit **32 Tests und 518 Assertions**. Der Produktions-Build ist erfolgreich, PHP-Formatierung und Diff-Prüfung sind grün; Flutter-Analyzer und Widgettests bleiben wegen der lokalen Snap-Sperre unter T017f2 offen. T017c2b2c2b2b8f41 ist abgeschlossen; weitere pauschale Bedienpfade bleiben offen.

### 25.09.2026 – Native Mannschaftserstellung mit Einzelrecht

- Der Club-API-Vertrag veröffentlicht `can_edit_teams` aus dem wirksamen Vereinsrecht. Die native Modellkette nutzt den Wert für den Mannschaftsdialog, die Aktionsschaltflächen und den eingebetteten Erstellbereich; ein spezialisierter Team-Bearbeiter wird damit nicht länger wegen fehlendem allgemeinem Managementstatus ausgesperrt.
- Vereinsprofilbearbeitung, Mannschaftserstellung und Vereinslöschung besitzen in der nativen Vereinskarte nun getrennte Sichtbarkeiten. Eine allgemeine Managerrolle mit ausdrücklichem Entzug von `teams.edit` erhält keine Mannschaftserstellung, während das Backend denselben Entzug bereits durchsetzt.
- Die fokussierte Vereinsrechte-, Mannschaftsmitglieder-, Profil- und Mobile-API-Regression besteht mit **43 Tests und 811 Assertions**. PHP-Formatierung und Diff-Prüfung sind grün; Flutter-Analyzer und Widgettests bleiben wegen der lokalen Snap-Sperre unter T017f2 offen. T017c2b2c2b2b8f42 ist abgeschlossen; weitere pauschale Bedienpfade bleiben offen.

### 25.09.2026 – Natives Vereins-Cockpit mit eigenem Ansichtsrecht

- Die native Vereinsnavigation öffnet das Cockpit nur noch mit `can_view_cockpit`. Die Cockpit-Auswahl filtert ausschließlich nach diesem veröffentlichten Wert und prüft ihn nach dem Laden des vollständigen Vereins erneut; allgemeine Verwaltungs-, Profilbearbeitungs- oder Eigentümerzustände überstimmen eine ausdrückliche Sperre nicht mehr.
- Ein konfigurierte Cockpit-Fachrolle ohne allgemeine Vereinsverwaltung erhält weiterhin den Einstieg. Der bestehende Backendtest für eine ausdrücklich gesperrte Managerrolle prüft nun zusätzlich die API-Fähigkeit und die nativen Quellverträge.
- Die fokussierte Cockpit-, Vereinsrechte-, Profil- und Mobile-API-Regression besteht mit **39 Tests und 957 Assertions**. PHP-Formatierung und Diff-Prüfung sind grün; Flutter-Analyzer und Widgettests bleiben wegen der lokalen Snap-Sperre unter T017f2 offen. T017c2b2c2b2b8f43 ist abgeschlossen; weitere pauschale Bedienpfade bleiben offen.

### 25.09.2026 – Native Mitglieder- und Finanznavigation mit Einzelrechten

- Der Club-API-Vertrag veröffentlicht `can_manage_members` und `can_view_finance` getrennt am Club und im Viewer-Kontext. Die native Modellkette bildet daraus ausschließlich den Zugang zum gemeinsamen Mitglieder-/Finanzarbeitsbereich; die vorhandenen Aktionsflags innerhalb der Ansicht steuern weiterhin jede schreibende Funktion separat.
- Reine Finanzleser und spezialisierte Mitgliederverwalter erscheinen jetzt in der Clubauswahl ohne allgemeinen `can_manage`-Status. Eine Managerrolle mit ausdrücklichem Entzug beider Rechte wird aus Navigation und Auswahl entfernt; auch die eingebettete Vereinskarte verwendet für Mitglieder- und externe Mitgliedsdaten die wirksamen Managementwerte.
- Die fokussierte Vereinsrechte-, Rollendefinitions-, Mitgliedschaftszugriffs- und Mobile-API-Regression besteht mit **48 Tests und 891 Assertions**. PHP-Formatierung und Diff-Prüfung sind grün; Flutter-Analyzer und Widgettests bleiben wegen der lokalen Snap-Sperre unter T017f2 offen. T017c2b2c2b2b8f44 ist abgeschlossen; weitere pauschale Bedienpfade bleiben offen.

### 25.09.2026 – Native Einzelrechte für Ankündigungen und Umfragen

- Der Vereinsvertrag veröffentlicht Bearbeiten, Veröffentlichen und Löschen von Ankündigungen sowie Bearbeiten, Schließen und Löschen von Umfragen als sechs getrennte Fähigkeiten. Dabei werden auch Rollen auf Abteilungs- oder Mannschaftsebene berücksichtigt; ein pauschales Vereinsverwaltungsrecht ist für die native Bedienung nicht mehr erforderlich.
- Die native App übernimmt die Fähigkeiten sowohl auf Vereinsebene für neue Inhalte als auch je Ankündigung beziehungsweise Umfrage. Bearbeitungsrollen können Entwürfe erstellen und ändern, aber ohne Veröffentlichungsrecht nicht sofort oder geplant versenden. Veröffentlichungsrollen können vorhandene Entwürfe veröffentlichen. Umfragen lassen sich abhängig vom Einzelrecht ändern, schließen oder löschen; abgegebene Stimmen und bereits versandte Veröffentlichungen bleiben durch die vorhandenen Backendregeln geschützt.
- Der native API-Client und das Repository unterstützen nun auch Umfrageänderung/-löschung sowie Ankündigungsveröffentlichung/-löschung. Ältere Serverantworten bleiben für bisherige Verwaltungsrollen kompatibel; aktuelle Antworten respektieren ausdrückliche Ablehnungen einzelner Aktionen.
- Fokussierte Ankündigungs-/Umfrageprüfung: **8 Tests, 145 Assertions**. Mobile API-, Pfad- und Navigationsverträge: **25 Tests, 348 Assertions**. Die gemeinsame Vereinsrechte-, Finanz-/SEPA-, Datei-, Inventar-, Support-, Termin- und Mobile-Regression besteht mit **286 Tests und 4.139 Assertions**. PHP-Formatierung, Dart-Klammerprüfung und Diff-Prüfung sind grün. Flutter-Analyzer und Widgettests bleiben wegen der lokalen Snap-Sperre unter T017f2 offen. T017c2b2c2b2b6 ist abgeschlossen; weitere Datenarten bleiben unter T017c2b2c2b2b7 offen. Keine produktive Migration oder Veröffentlichung wurde ausgeführt.

### 25.09.2026 – Getrennte Rechte für eigene Daten und Nummernkreise

- Eigene Datenfelder, Kategorien und Nummernkreise besitzen eigene Rechte für Ansicht, Anlegen/Bearbeiten und Löschen. Das bestehende `content.manage` umfasst alle drei Aktionen weiterhin; Bearbeiten oder Löschen vermittelt die notwendige Ansicht, solange sie nicht ausdrücklich entzogen wurde.
- API, Web und native App verwenden die getrennten Fähigkeiten. Reine Leser sehen die Konfiguration ohne Schreibbedienung, Bearbeitungsrollen können Definitionen und Standardnummernkreise pflegen sowie Nummern vergeben und Fachwerte bearbeiten, Löschrollen können ausschließlich unbenutzte Definitionen entfernen. Die vorhandenen Schutzregeln für verwendete Felder, Kategorien, Nummernkreise und vereinsfremde Ressourcen bleiben wirksam.
- Vereinsprofil und mobile Vereinsnavigation veröffentlichen das neue Ansichtsrecht. Mannschafts-, Termin- und Inventarpfade veröffentlichen den Fachwert-Editor nur noch mit dem Metadaten-Bearbeitungsrecht; eine konfigurierte Fachrolle funktioniert damit unabhängig vom pauschalen Vereinsverwaltungsrecht.
- Die fokussierte Metadatenprüfung besteht mit **7 Tests und 104 Assertions**. Die gemeinsame Rollen-, Vertretungs-, Vereinsjahre-, Organisations-, Dokumenten-, Gremien-, Metadaten-, Finanz-/SEPA-, Datei-, Inventar-, Umfrage-, Ankündigungs-, Support-, Mitgliedschafts-, Termin- und Vier-Augen-Regression besteht mit **261 Tests und 3.764 Assertions**. PHP-Formatierung, Frontendvertrag, Web-Produktionsbuild mit **1.086 Modulen**, Dart-Klammerprüfung und Diff-Prüfung sind grün. Flutter bleibt wegen der lokalen Snap-Sperre unter T017f2 offen. T017c2b2c2b2b5 ist abgeschlossen; weitere Datenarten bleiben unter T017c2b2c2b2b6 offen. Keine produktive Migration oder Veröffentlichung wurde ausgeführt.

### 25.09.2026 – Getrennte Rechte für Gremien und Funktionen

- Vorstand, Ausschüsse, Arbeitsgruppen und ihre Funktionszuweisungen besitzen eigene Rechte für interne Ansicht, Anlegen/Bearbeiten und Löschen. Das bestehende `content.manage` umfasst diese Aktionen weiterhin; reguläre aktive Mitglieder behalten die bisherige interne Leseansicht.
- API, Web und native App verwenden die getrennten Fähigkeiten. Bearbeitungsberechtigte können Gremien und Besetzungen pflegen, aber nicht löschen. Löschberechtigte können Besetzungen und unbenutzte Gremien entfernen, erhalten jedoch keine Personenauswahl, internen Personenkennungen oder Bearbeitungsoberfläche.
- Öffentliche Gremien und öffentliche Besetzungen bleiben öffentlich sichtbar. `pending`, `paused` und `former` erhalten nur diesen öffentlichen Ausschnitt. Vereinsfremde Personen und verschachtelte Ressourcen bleiben abgewiesen; besetzte Gremien bleiben gegen Löschen geschützt.
- Die gemeinsame Rollen-, Vertretungs-, Vereinsjahre-, Organisations-, Dokumenten-, Gremien-, Finanz-/SEPA-, Datei-, Inventar-, Umfrage-, Ankündigungs-, Support-, Mitgliedschafts- und Vier-Augen-Regression besteht mit **244 Tests und 3.521 Assertions**. PHP-Formatierung, Web-Produktionsbuild mit **1.086 Modulen**, Dart-Klammerprüfung und Diff-Prüfung sind grün. Flutter bleibt wegen der lokalen Snap-Sperre unter T017f2 offen. T017c2b2c2b2b4 ist abgeschlossen; weitere Datenarten bleiben unter T017c2b2c2b2b5 offen. Keine produktive Migration oder Veröffentlichung wurde ausgeführt.

### 25.09.2026 – Getrennte Rechte für Satzungen und Ordnungen

- Satzungen, Ordnungen und Beitragsmodelle besitzen eigene Rechte für interne Ansicht, Anlegen/Bearbeiten, Download und Löschen. `content.manage` umfasst die neuen Aktionen weiterhin; reguläre Mitglieder behalten die bisherige interne Ansicht und den Download.
- API, Web und native App verwenden die einzelnen Fähigkeiten. Eine Bearbeitungsrolle kann Fassungen anlegen und ändern, aber weder interne Dateien herunterladen noch löschen. Download- und Löschrollen erhalten jeweils nur ihre vorgesehene Aktion. Öffentliche Dokumente und Downloads bleiben unabhängig davon öffentlich erreichbar.
- Die bisherige bloße Vereinsverknüpfung reichte auch nach einem Austritt für interne Dokumente. Die zentrale Statusprüfung sperrt nun `pending`, `paused` und `former`; der Regressionstest bestätigt, dass interne Listen und Downloads für ausgeschiedene Personen nicht mehr erreichbar sind. Versionsüberschneidungen, Beitragsregel-Verknüpfungen, Dateischutz und Vereinsgrenzen bleiben unverändert.
- Die gemeinsame Rollen-, Vertretungs-, Vereinsjahre-, Organisations-, Dokumenten-, Finanz-/SEPA-, Datei-, Inventar-, Umfrage-, Ankündigungs-, Support-, Mitgliedschafts- und Vier-Augen-Regression besteht mit **239 Tests und 3.454 Assertions**. PHP-Formatierung, Web-Produktionsbuild mit **1.086 Modulen**, Dart-Klammerprüfung und Diff-Prüfung sind grün. Flutter bleibt wegen der lokalen Snap-Sperre unter T017f2 offen. T017c2b2c2b2b3 ist abgeschlossen; weitere Datenarten bleiben unter T017c2b2c2b2b4 offen. Keine produktive Migration oder Veröffentlichung wurde ausgeführt.

### 25.09.2026 – Getrennte Rechte für die Vereinsstruktur

- Abteilungen, Standorte und Trainingsgruppen besitzen nun eigene Rechte für interne Ansicht, Anlegen/Bearbeiten und Löschen. Das bestehende `content.manage` umfasst die neuen Aktionen weiterhin. Öffentliche Organisationsdaten bleiben öffentlich lesbar; interne Daten bleiben aktiven Mitgliedern und tatsächlich schreibberechtigten Personen vorbehalten.
- API, Web und native App verwenden getrennte Bedienrechte. Eine reine Bearbeitungsrolle kann Struktureinträge anlegen und ändern, aber nicht löschen; eine reine Löschrolle kann vorhandene unbenutzte Einträge entfernen, aber nicht verändern. Schutzregeln für zugeordnete Mannschaften, Trainingsgruppen und Inventargegenstände bleiben unverändert.
- Mannschaftszuordnungen werden separat über `teams.edit` veröffentlicht und angezeigt. Eine Strukturrolle erhält dadurch weder implizit Mannschaftsrechte noch eine irreführende Bearbeitungsoberfläche; der bestehende Team-Endpunkt bleibt die verbindliche serverseitige Prüfung.
- Die gemeinsame Rollen-, Vertretungs-, Vereinsjahre-, Organisations-, Finanz-/SEPA-, Datei-, Inventar-, Umfrage-, Ankündigungs-, Support-, Mitgliedschafts- und Vier-Augen-Regression besteht mit **232 Tests und 3.361 Assertions**. PHP-Formatierung, Web-Produktionsbuild mit **1.086 Modulen**, Dart-Klammerprüfung und Diff-Prüfung sind grün. Flutter bleibt wegen der lokalen Snap-Sperre unter T017f2 offen. T017c2b2c2b2b2 ist abgeschlossen; weitere Datenarten bleiben unter T017c2b2c2b2b3 offen. Keine produktive Migration oder Veröffentlichung wurde ausgeführt.

### 25.09.2026 – Getrennte Rechte für Vereinsjahre

- Vereinsjahre besitzen nun eigene Rechte für Ansicht, Anlegen/Bearbeiten und Löschen. Das bisherige Recht `content.manage` umfasst diese Aktionen weiterhin, sodass bestehende Managerrollen kompatibel bleiben; ein reines Bearbeiten- oder Löschrecht schaltet die notwendige Ansicht frei, sofern sie nicht ausdrücklich ausgeschlossen wurde.
- API, Web und native App veröffentlichen und verwenden getrennte Bedienrechte. Eine Bearbeitungsrolle kann Zeiträume anlegen und ändern, aber nicht löschen; eine Löschrolle kann bestehende Zeiträume entfernen, aber keine neuen anlegen oder Inhalte ändern. Vereinsgrenzen, Überschneidungsprüfung und Schutz verwendeter Zeiträume bleiben bestehen.
- Die zentrale Rechteberechnung entzieht Standardrollen, individuelle Freigaben, konfigurierbare Rollen und Vertretungen vollständig bei `pending`, `paused` oder `former`. Der kompatible Status `non_member` bleibt für externe Vereinsfunktionen wie Finanzprüfung zulässig. Ein eigener Regressionstest prüft Entzug und Wiederherstellung über alle vier Rechtequellen.
- Die ausgewählte gemeinsame Rollen-, Vertretungs-, Vereinsjahre-, Finanz-/SEPA-, Datei-, Inventar-, Umfrage-, Ankündigungs-, Support-, Mitgliedschafts- und Vier-Augen-Regression besteht mit **231 Tests und 3.332 Assertions**. PHP-Formatierung, Web-Produktionsbuild mit **1.086 Modulen**, Dart-Klammerprüfung und Diff-Prüfung sind grün. Die Flutter-Widgettests konnten wegen der lokalen Snap-Sperre für den ausführenden Benutzer nicht gestartet werden und bleiben unter T017f2 offen. T017c2b2c2b2b1 ist abgeschlossen; weitere Datenarten bleiben unter T017c2b2c2b2b2 offen. Keine produktive Migration oder Veröffentlichung wurde ausgeführt.

### 25.09.2026 – Zweitfreigabe für weitere sensible Vorgänge

- Mitgliedschaftsanträge und Änderungen wie Pausen oder Austritte können auch von einer grundsätzlich berechtigten Person nicht selbst freigegeben werden. Dasselbe gilt für eigene Teambeitrittsanträge, Trainerrollen-Anträge und die Verifizierung eines selbst gehaltenen Vereins; Web- und API-Pfade wenden dieselbe serverseitige Trennung an.
- Freigabepflichtige Inventarausleihen bleiben nun immer im Status `pending`, auch wenn die anfragende Person selbst das Freigaberecht besitzt. Die additive Spalte `requested_by` hält die tatsächliche anfragende Person fest, übernimmt für Altbestände kontrolliert die bisherige ausleihende Person und verhindert die spätere Selbstfreigabe. Antrag, direkte Ausgabe, Freigabe und Ablehnung werden im Vereinsaudit protokolliert.
- Die bestehende Zweitfreigabe für SEPA-Läufe, erneute Einzüge, Gebührenweiterbelastungen, Stornos und Gutschriften wurde zusammen mit Rollen-Nachfolge, Mitgliedschaft, Team, Inventar, Trainerrollen, Vereinsverifizierung und Lokalisierung regressiv geprüft: **122 Tests mit 2.629 Assertions**. Die neue Migration besitzt einen geprüften Rückweg; PHP-Formatierung ist grün. T017d2b2, T017d2b, T017d2 und damit T017d sind abgeschlossen. Keine produktive Migration oder Veröffentlichung wurde ausgeführt.

### 25.09.2026 – Bereichsbezogene Supporttickets

- Supporttickets können zusätzlich zum Verein einer Abteilung oder Mannschaft zugeordnet werden. Mannschaftsbereiche werden gegen Verein und Abteilung validiert; vereinsfremde oder widersprüchliche Zielbereiche werden abgewiesen. Bestehende Tickets ohne Bereich bleiben vereinsweit und damit rückwärtskompatibel.
- Die Support-Arbeitsliste ermittelt für Ansicht, Bearbeitung, Zuweisung und Abschluss jeweils getrennte wirksame Vereins-, Abteilungs- und Mannschaftsbereiche. Abteilungsrechte gelten hierarchisch für zugehörige Mannschaften; eine reine Bereichsrolle erhält weder vereinsweite Tickets noch Tickets anderer Bereiche. Auch die Zielperson einer Zuweisung muss im konkreten Ticketbereich bearbeiten dürfen.
- Im Web können anfragende Personen Verein, Abteilung und Mannschaft auswählen. Eigene Tickets und die geschützte Arbeitsliste zeigen den Bereich an; berechtigte Bearbeitungen lassen sich danach filtern. Alle neuen Texte liegen in DE/EN/FR/AR vor. Die additive Migration besitzt einen geprüften Rückweg und wurde nicht produktiv ausgeführt.
- Support-, Ankündigungs-, Umfrage-, Inventar-, Rollen-, Vertretungs-, Nachfolge-, Lokalisierungs- und Mobile-Support-Regression: **68 Tests mit 2.042 Assertions**. PHP-Formatierung, Web-Produktionsbuild und Diff-Prüfung sind grün. T017c3b2d2b2b1 ist abgeschlossen; weitere Abteilungspfade bleiben unter T017c3b2d2b2b2 offen.

### 25.09.2026 – Bereichsbezogene Organisationsstruktur

- Organisationsabteilungen und ihnen zugeordnete Trainingsgruppen veröffentlichen Bearbeiten, Löschen und Mannschaftszuordnung je Datensatz. Eine Abteilungsrolle darf nur ihre eigene Struktur ändern; globale Standorte, neue Abteilungen und Einheiten anderer Abteilungen bleiben geschützt. Beim Verschieben einer Trainingsgruppe werden alter und neuer Bereich geprüft.
- Mannschaftszuordnungen werden auf tatsächlich bearbeitbare Mannschaften begrenzt. Web und native App verwenden die Datensatzrechte, bieten nur zulässige Abteilungen und Trainingsgruppen als Ziele an und halten für ältere API-Antworten den bisherigen kompatiblen Rückfall bereit.
- Die zentrale Bereichsermittlung berücksichtigt jetzt neben konfigurierbaren Rollen auch aktive, zeitlich begrenzte Vertretungen. Eine reine Abteilungsvertretung kann dadurch den Organisationsarbeitsbereich öffnen, ohne vereinsweite Anlage- oder Standortrechte zu erhalten.
- Die fokussierte Organisations- und Vertretungsprüfung besteht mit **15 Tests und 178 Assertions**; die gemeinsame Organisations-, Rollen-, Team-, Inventar-, Ankündigungs-, Umfrage- und Supportregression besteht mit **65 Tests und 758 Assertions**. Der Web-Produktionsbuild mit **1.086 Modulen**, PHP-Formatierung, Dart-Klammerprüfung und Diff-Prüfung sind grün. Flutter-Analyzer und Widgettests bleiben wegen der lokalen Snap-Sperre unter T017f2 offen. T017c3b2d2b2b2a ist abgeschlossen; weitere Abteilungspfade bleiben unter T017c3b2d2b2b2 offen. Keine produktive Migration oder Veröffentlichung wurde ausgeführt.

### 25.09.2026 – Bereichsbezogene Trainingsübungsbibliothek

- Die Mannschaftsermittlung der Übungsbibliothek prüft nun auch direkte Mannschaftszuordnungen gegen das wirksame Ansichtsrecht. Ein ausdrückliches `training_exercises.view`-Verbot entfernt die Übung damit aus Liste und Detailansicht, selbst wenn die gesperrte Person direkt als Mannschaftsverantwortliche eingetragen ist.
- Abteilungsrollen dürfen Übungen nur für Mannschaften ihrer Abteilung anlegen und bearbeiten. Zeitlich aktive Abteilungsvertretungen werden für dieselben Datensätze ausgewertet; Löschrechte greifen in der eigenen Abteilung und bleiben in fremden Abteilungen wirkungslos. Vereinsweite und persönliche Übungen behalten ihre bisherigen Rechtewege.
- Der fokussierte Bibliothekstest besteht mit **5 Tests und 66 Assertions**. Die gemeinsame Übungs-, Plan-, Log-, Cockpit-, Rollen-, Vertretungs-, Team- und Mobile-Regression besteht mit **74 Tests und 914 Assertions**; die ergänzende Web-Trainingsregression umfasst **15 Tests und 146 Assertions**. PHP-Formatierung und Diff-Prüfung sind grün. T017c3b2d2b2b2b ist abgeschlossen; weitere Abteilungspfade bleiben unter T017c3b2d2b2b2 offen. Keine produktive Migration oder Veröffentlichung wurde ausgeführt.

### 25.09.2026 – Mannschaftsanlage im Abteilungsbereich

- `teams.edit` kann bei der Neuanlage jetzt im konkreten Abteilungsbereich ausgewertet werden. Ohne vereinsweites Recht ist eine Abteilung verpflichtend; globale Mannschaften und fremde Abteilungen werden abgewiesen. Eine gleichnamige Bestandsmannschaft darf nur wiederverwendet werden, wenn sowohl ihr bestehender als auch ihr angeforderter Bereich bearbeitbar ist. Der mobile Pfad verwendet bei Vereinen ohne Sportart außerdem den bereits im Web etablierten sicheren Standardwert.
- Der Club-API-Vertrag trennt vereinsweite Anlage von den zulässigen Zielabteilungen. Webformular, native Vereinsansicht und natives Mannschaftszentrum zeigen die Abteilungsauswahl an und senden den gewählten Bereich mit. Reine Abteilungsrollen sowie aktive Abteilungsvertretungen sehen nur ihre freigegebenen Ziele.
- Die gemeinsame Rollen-, Vertretungs-, Mannschafts-, Organisations- und Mobile-Regression besteht mit **64 Tests und 1.054 Assertions**; der darin enthaltene fokussierte Web-Prop-Vertrag umfasst **1 Test mit 42 Assertions**. Der Web-Produktionsbuild mit **1.086 Modulen**, PHP-Formatierung, Dart-Klammerprüfung und Diff-Prüfung sind grün. Die Flutter-Ausführung bleibt wegen der lokalen Snap-Sperre unter T017f2 offen. T017c3b2d2b2b2c ist abgeschlossen; weitere Abteilungspfade bleiben unter T017c3b2d2b2b2 offen. Keine produktive Migration oder Veröffentlichung wurde ausgeführt.

### 25.09.2026 – Abschlussprüfung der Bereichsrechte

- Der Club-Profilvertrag veröffentlicht die Mannschaftsanlage jetzt in beiden API-Darstellungen gleich: Er unterscheidet vereinsweite Anlage von den konkret zulässigen Zielabteilungen und öffnet die Bedienung auch für reine Abteilungsrollen.
- Der Mitgliederkarten-Check-in wertet bei angegebenem Termin dessen tatsächlichen Verein sowie das wirksame Terminrecht aus. Bereichsrollen können dadurch nur in Terminen ihres Teams oder ihrer Abteilung einchecken; der terminlose Prüfpfad bleibt auf ein vereinsweites Terminrecht begrenzt.
- Web- und API-Pfade für Beiträge und Storys akzeptieren das wirksame Inhaltsrecht des Zielteams, ohne danach nochmals ein vereinsweites Inhaltsrecht zu verlangen. Ein Verhaltenstest belegt erfolgreiche Veröffentlichung im zugewiesenen Team und die Sperre einer anderen Mannschaft bei deaktivierter allgemeiner Mitgliederpublikation.
- Die abschließende Prüfung aller globalen Legacy-Abfragen ergab keine weitere ungeschützte Schreiboperation auf einem konkreten Abteilungs- oder Mannschaftsobjekt. Die fokussierte Feed-, Story- und Rollenregression besteht mit **42 Tests und 392 Assertions**; die gemeinsame Bereichsrechtsregression besteht mit **143 Tests und 2.086 Assertions**. PHP-Syntax, Formatierung und Diff-Prüfung sind grün. T017c2, T017c3 und damit T017c einschließlich aller Unterpunkte sind abgeschlossen. T017f2 und T017g bleiben für Geräteprüfung beziehungsweise Rollout offen. Keine produktive Migration oder Veröffentlichung wurde ausgeführt.

### 25.09.2026 – Verbindliche Rollen-Nachfolge mit Vier-Augen-Freigabe

- Für geplante Mitgliedschaftsenden wird eine mandantengebundene, persistente Zugriffsprüfung angelegt. Eine berechtigte Person schlägt Entfernung oder eine aktive Nachfolgeperson vor; eine andere berechtigte Person muss den Vorschlag freigeben. Selbstfreigabe, fremde oder ehemalige Mitglieder und Rechteübertragung außerhalb der eigenen wirksamen Bereiche werden serverseitig abgewiesen.
- Der freigegebene Stand enthält Rollen, Bereiche und die damaligen Rollenberechtigungen. Beim tatsächlichen Austritt werden Nachfolgeperson, beide Freigabepersonen und der vollständige Snapshot erneut geprüft. Jede zwischenzeitliche Änderung setzt den Vorgang auf `stale`; dann werden keine Rechte übertragen und die Zugriffe der austretenden Person weiterhin sicher entfernt.
- Web und native App bieten eine vierte Zugriffsansicht für Vorschlag, Nachfolgeauswahl, Begründung, Status und Zweitfreigabe. Die mobilen Texte liegen in DE/EN/FR/AR vor. Die additive Migration besitzt einen geprüften Rückweg; es wurde keine produktive Migration ausgeführt.
- Die gemeinsame Übergabe-, Wiedervorlage-, Mitgliedschaftsende-, Rollen-, Vertretungs-, Lokalisierungs-, Web- und Mobile-Vertragsregression besteht mit **47 Tests und 1.462 Assertions**. PHP-Formatierung ist grün. Der Web-Produktionsbuild war nach der Oberflächenintegration erfolgreich; die Flutter-Ausführung bleibt wegen der nicht startbaren lokalen Snap-Installation unter T017f2 offen. T017d2b1 ist abgeschlossen, T017d2b und T017d2 bleiben wegen weiterer sensibler Rechte offen.

### 25.09.2026 – Zugriffs-Wiedervorlage vor Mitgliedschaftsende

- Der tägliche Mitgliedschafts- und Abrechnungslauf prüft bei einem geplanten Mitgliedschaftsende zusätzlich, ob die betroffene Person noch konfigurierbare Rollenzuweisungen oder laufende beziehungsweise geplante Vertretungen besitzt. Ohne solche Zugriffe entsteht keine zusätzliche Meldung.
- Bei vorhandenem Zugriff erhalten der Vereinsinhaber und alle aktiven Personen mit wirksamem Rollenverwaltungsrecht eine eigene, hoch priorisierte Wiedervorlage. Sie enthält ausschließlich die nötigen Kennzahlen, das Enddatum und die ausdrücklich verlangte Entscheidung zwischen Entfernung und Nachfolgezuweisung; ein stabiler Schlüssel verhindert doppelte Meldungen für denselben Verein, dieselbe Person und denselben Stichtag.
- Die Meldung ist in DE/EN/FR/AR lokalisiert und enthält strukturierte, clientneutrale Entscheidungsdaten. Tests prüfen eine konfigurierbare Rollenverwaltung, Empfängerausschluss ohne Recht, Rollen- und Vertretungszählung, Sprachwahl sowie den Fall ohne prüfpflichtigen Zugriff.
- Gemeinsame Wiedervorlage-, Lokalisierungs-, Mitgliedschaftsende-, Rollen-, Vertretungs-, Organisations-, Mobile- und Webzugriffsregression: **45 Tests mit 1.429 Assertions**. PHP-Formatierung und Diff-Prüfung sind grün. T017d2a ist abgeschlossen; die persistente Nachfolgeentscheidung, deren Vier-Augen-Freigabe und Web-/App-Bedienung bleiben T017d2b offen. Keine produktive Migration oder Veröffentlichung wurde ausgeführt.

### 25.09.2026 – Native Verwaltung für Rollen, Einzelrechte und Vertretungen

- Die native Mitgliederverwaltung öffnet für berechtigte Personen einen eigenen Arbeitsbereich mit drei Bereichen: individuelle Erlaubnis/Ausschluss/Vererbung samt wirksamer Entscheidung, mehrere Rollenzuweisungen für Verein/Abteilung/Mannschaft sowie zeitlich begrenzte Vertretungen mit Beginn, Ende und sofortigem Widerruf.
- Rollendefinitionen können mobil angelegt, bearbeitet, aktiviert/deaktiviert und unbenutzt gelöscht werden. Die App verwendet ausschließlich die bestehenden, mandantengeprüften APIs; neue Repositoryverträge und vollständige Clientpfade decken Lesen und Schreiben ab.
- Vertretungen bieten nur die serverseitig als weitergebbar gemeldeten Rechte an. Zielbereiche stammen aus der geschützten Vereinsorganisation; ehemalige Mitglieder erhalten daraus keine internen Abteilungen, Mannschaftszuweisungen oder Standortnotizen. Die endgültige Prüfung gegen Rechteausweitung, Fremdverein, inaktive Mitgliedschaft und ungültige Bereiche bleibt auf dem Server verpflichtend.
- Sämtliche neue Bedien- und Statustexte sind in DE/EN/FR/AR vorhanden. Ein mobiler Quellvertrag prüft Navigation, Berechtigungsschranke, API-/Repositorymethoden, alle Schreibaktionen, Bereichsdaten und Sprachabdeckung. Gemeinsam mit Routen-, Organisations-, Webzugriffs-, Rollen- und Vertretungsregression bestehen **37 Tests mit 394 Assertions**; PHP-Formatierung, Dart-Klammerprüfung und Diff-Prüfung sind grün.
- Die lokale Flutter-Snap-Installation kann unter dem ausführenden Benutzer keine Anwendungen starten. Analyzer, Flutter-Widgettests und Realgeräteabnahme sind deshalb als T017f2 offen; T017f bleibt bis zu diesem Lauf ebenfalls offen. T017f1 ist abgeschlossen. Keine produktive Migration oder Veröffentlichung wurde ausgeführt.

### 25.09.2026 – Zeitlich begrenzte Vereinsvertretungen: Backend/API

- Die neue additive Tabelle `club_permission_delegations` speichert Verein, erteilende und vertretene Person, eine validierte Rechtemenge, Beginn, Ende und optionalen Widerruf. Laufzeiten sind auf 90 Tage begrenzt; Zielpersonen müssen aktive Mitglieder desselben Vereins sein.
- Die wirksame Rechteberechnung berücksichtigt nur aktuell laufende, nicht widerrufene Vertretungen und entzieht sie beim Ende der aktiven Mitgliedschaft. Rollenverwaltung selbst ist nie delegierbar. Eine Person kann ausschließlich eigene wirksame Rechte weitergeben; Selbstvergabe und vereinsfremde Ziele werden abgewiesen.
- Drei API-Endpunkte listen, erstellen und widerrufen Vertretungen. Anlage und Widerruf werden im bestehenden Vereinsaudit protokolliert. Der Widerruf ist wiederholbar; eine fremde Vereinsvertretung wird nicht offengelegt oder verändert.
- Fünf neue Verhaltenstests sowie die bestehende Vereinsrechte-Suite bestehen mit **8 Tests und 36 Assertions**. Die zentralen API-Zweck-, Modul-, Security-/Privacy- und Delivery-Verträge bestehen gemeinsam mit **27 Tests und 1.866 Assertions**. PHP-Formatierung und `git diff --check` sind ohne Befund.
- Anleitung und Sicherheitsgrenzen: [CLUB_PERMISSION_DELEGATIONS.md](CLUB_PERMISSION_DELEGATIONS.md). Konfigurierbare Rollen folgen im nächsten dokumentierten Schritt; Web/App, Abteilungs-/Team-Scope, Aktionsrechte und reale Einführung bleiben offen.

### 25.09.2026 – Konfigurierbare Vereinsrollen: Backend/API

- Mandantengebundene Rollendefinitionen besitzen stabilen Schlüssel, frei wählbaren Namen, aktiven Zustand und eine validierte Teilmenge des zentralen Vereinsrechtekatalogs. Elf Vorlagen decken Vereinsadministration, Vorstand, Geschäftsstelle, Kassenwart, Kassenprüfung, Abteilungsleitung, Training/Betreuung, Mitglieder, Sorgeberechtigte, Helfer/Mitarbeiter und externe Kontakte ab.
- Rollenverwaltung darf keine Rechte in eine Definition aufnehmen oder neu zuweisen, die die handelnde Person nicht selbst wirksam besitzt. Schlüssel sind je Verein eindeutig, fremde Rollen werden nicht offengelegt, und pro Verein gilt eine Obergrenze von 100 Definitionen.
- Mehrere konfigurierbare Rollen können einem aktiven Vereinsmitglied parallel zugeordnet werden. Die bisherigen `role`-/`roles`-Werte bleiben unverändert, sodass bestehende Abläufe kompatibel weiterlaufen. Aktive Definitionen ergänzen die zentrale Rechteberechnung; ein ausdrückliches personenbezogenes `false` bleibt vorrangig. Deaktivierung entzieht die Rollenrechte sofort, ohne Zuordnungshistorie zu löschen. Zugeordnete Definitionen sind gegen Löschen geschützt.
- Anlage, Änderung, Löschung und Zuweisungsänderung werden im Vereinsaudit erfasst. Identische Zuweisungswiederholungen erzeugen keinen zusätzlichen Auditdatensatz. Die additive Migration wurde nicht produktiv ausgeführt.
- Rollen-, Zuweisungs-, Vertretungs- und bisherige Rechteprüfung: **17 Tests, 103 Assertions**. Zusammen mit API-Zweck-, Modul-, Security-/Privacy- und Delivery-Verträgen sind **36 Tests, 1.945 Assertions** grün. T017b ist abgeschlossen; Scope-/Aktionsrechte, gemeinsames Vier-Augen-Verfahren, Web/App und reale Einführung bleiben T017c–T017g.
- Architektur und Sicherheitsgrenzen: [CLUB_PERMISSION_DELEGATIONS.md](CLUB_PERMISSION_DELEGATIONS.md).

### 25.09.2026 – Getrennte Aktionsrechte und sicherer Rollenentzug

- Der zentrale Katalog unterscheidet für Mitglieder und Finanzen jetzt `edit`, `export`, `approve` und `delete`. Bestehende `members.manage`- und `finance.manage`-Zuweisungen erhalten ihre bisherigen Möglichkeiten kompatibel weiter. Ein ausdrücklich personenbezogen verweigertes Aktionsrecht bleibt jedoch vorrangig.
- Mitgliederdaten bearbeiten und Mitglieder entfernen sind in API und gemeinsamem Web-Service getrennt. Ein ausschließlich zum Löschen berechtigter Testnutzer konnte ein anderes Mitglied nach verpflichtender Begründung entfernen, aber dessen Stammdaten nicht bearbeiten.
- Bei gespeicherten Lastschriftläufen sind zweite Freigabe und XML-Export getrennte Finanzrechte. Ein reiner Freigeber konnte den Lauf freigeben, aber nicht exportieren; ein separater Exporteur konnte anschließend ausschließlich die Bankdatei abrufen. Bestehende Finanzverantwortliche bleiben über die kompatible `finance.manage`-Abbildung funktionsfähig.
- Direkte Entfernung, eigener Vereinsaustritt und der geplante Beendigungs-Worker löschen konfigurierbare Rollenzuweisungen und widerrufen alle noch aktiven Vertretungen, bei denen die Person Geber oder Empfänger ist. Der gemeinsame Audittyp `club.role_access.ended` hält nur die Anzahl der entzogenen Zuweisungen fest. Dadurch werden alte Rechte bei einem späteren Wiedereintritt nicht still reaktiviert.
- Gezielte Rollen-/Mitgliedschafts-/SEPA-Prüfung: **32 Tests, 232 Assertions**. Erweiterte Regression einschließlich Mitgliedschaftszugriff, Lifecycle, Audit, Mobile-Parität sowie zentraler API-/Modulverträge: **63 Tests, 2.220 Assertions**. T017c1 und T017d1 sind abgeschlossen; weitere Datenarten, Abteilungs-/Team-Scope, Prüfwiedervorlage und Vier-Augen-Verallgemeinerung bleiben offen.

### 25.09.2026 – Bereichsgebundene Rollenzuweisungen: sicherer Unterbau

- Eine konfigurierbare Rolle kann jetzt vereinsweit oder für genau eine Abteilung beziehungsweise Mannschaft zugewiesen werden. Die strukturierte Zuweisungs-API prüft, dass der Bereich zum selben Verein gehört, verwirft unvollständige und doppelte Zuweisungen und erhält den bisherigen vereinsweiten `role_definition_ids`-Vertrag kompatibel.
- Vereinsweite Rechteprüfungen berücksichtigen ausschließlich vereinsweite Rollenzuweisungen. Bereichsrechte werden nur über die zentrale Prüfung `allowsInScope` und nur für den exakt passenden Bereich wirksam. Dadurch erweitert eine noch nicht umgestellte Fachfunktion keine Rechte versehentlich.
- Bestehende personenbezogene Einzelrechte, statische Rollen und Vertretungen bleiben vereinsweit. Die fachliche Umstellung der betroffenen Abteilungs- und Mannschaftspfade sowie bereichsgebundene Vertretungen sind deshalb weiterhin T017c3b.
- Gezielte Rollen-, Vertretungs- und Rechteprüfung: **21 Tests, 128 Assertions**. Erweiterte Regression einschließlich Mitgliedschaftszugriff, Lifecycle, Audit, Mobile-Parität, SEPA sowie zentraler API- und Modulverträge: **65 Tests, 2.235 Assertions**. T017c3a ist abgeschlossen; die additive Migration wurde nicht produktiv ausgeführt.

### 25.09.2026 – Getrennte und bereichsgebundene Terminrechte

- Der zentrale Rechtekatalog trennt Terminbearbeitung und Terminlöschung. Das bisherige `events.manage` bleibt als kompatibles Sammelrecht bestehen und leitet beide Aktionen ab, solange kein ausdrücklicher personenbezogener Ausschluss gesetzt ist.
- Die gemeinsame Event-Policy für Web und API wertet globale Vereinsrechte sowie Mannschaftsrechte aus. Bei einer Mannschaft berücksichtigt sie zusätzlich die zugehörige Abteilung. Eine Teamrolle gilt nur für dieses Team; eine Abteilungsrolle gilt für die dieser Abteilung zugeordneten Teams.
- Private Mannschaftstermine passieren die vorgeschaltete API-Sichtbarkeitsprüfung nur dann, wenn die Person im passenden Bereich bearbeiten oder löschen darf. Nach Entzug der Bereichsrolle bleiben fremde private Termine verborgen. Bearbeiten allein erlaubt kein Löschen; ein reines Löschrecht erlaubt kein Bearbeiten.
- Gezielte Rollen- und Terminregression: **32 Tests, 410 Assertions**. Breite Vereins-, Mitgliedschafts-, Finanz-, Termin-, Mobile-, API- und Modulregression: **86 Tests, 2.563 Assertions**. T017c2a und T017c3b1 sind abgeschlossen; weitere Datenarten und Fachpfade bleiben offen.

### 25.09.2026 – Bereichsgebundene Mannschafts-Mitgliederverwaltung

- Die gemeinsame Team-Policy unterscheidet jetzt Mitgliederaufnahme und Beitrittsfreigabe (`members.approve`), Änderung der Teamrolle (`members.roles`) und Entfernung (`members.delete`). Die API- und Web-Controller verwenden dieselben Fähigkeiten; doppelte feste Rollenlogik für das Entfernen wurde entfernt.
- Mannschaftszuweisungen gelten nur im exakten Team. Abteilungszuweisungen gelten für alle Teams dieser Abteilung, aber nicht für Teams anderer Abteilungen. Vereinsweite Altrollen, bestehende Teamleitung und bisherige globale Teamrechte bleiben kompatibel.
- Die Team-API veröffentlicht getrennte Flags für Mitgliederverwaltung, Teamrollenänderung und Entfernung. Offene Beitrittsanfragen werden anhand des passenden Verwaltungsrechts bereitgestellt, statt pauschal an die Bearbeitung der Mannschaftsmetadaten gekoppelt zu sein.
- Ein Verhaltenstest wechselt dieselbe Person nacheinander zwischen reinem Freigabe-, Rollenänderungs- und Löschrecht und belegt die gegenseitige Trennung sowie Team-/Abteilungsgrenzen. Gezielte Team-/Rollenregression: **42 Tests, 792 Assertions**. Breite Vereins-, Mitgliedschafts-, Finanz-, Termin-, Team-, Mobile-, API- und Modulregression: **111 Tests, 3.238 Assertions**. T017c3b2a ist abgeschlossen.

### 25.09.2026 – Bereichsgebundene Mannschaftsmetadaten und Löschung

- Der Rechtekatalog enthält getrennte Rechte für das Bearbeiten von Mannschaftsdaten und das Löschen einer Mannschaft. Die gemeinsame Team-Policy wertet sie für Vereins-, Abteilungs- und Mannschaftszuweisungen aus; vorhandene Vereins- und Teamverwaltungsrechte bleiben kompatibel.
- Geschäftsstelle und Abteilungsleitung erhalten in den mitgelieferten Rollenvorlagen das Bearbeitungsrecht. Das Löschrecht bleibt eine ausdrücklich zu vergebende sensible Aktion.
- Eine reine Abteilungsrolle darf Mannschaften ihrer Abteilung bearbeiten, aber weder fremde Mannschaften ändern noch löschen. Beim Ändern organisatorischer Zuordnungen prüft die API zusätzlich den Zielbereich: Die Mannschaft kann nicht in eine nicht berechtigte Abteilung verschoben oder aus dem eigenen Bereich gelöst werden. Eine exakte Mannschaftsrolle bleibt an der Mannschaft gebunden.
- Der Verhaltenstest trennt Bearbeiten und Löschen, prüft die API-Bedienflags, fremde Abteilungen und den geschützten Abteilungswechsel. Gezielte Team-/Organisations-/Rollenregression: **35 Tests, 417 Assertions**. Breite Regression: **112 Tests, 3.251 Assertions**. T017c2b1 und T017c3b2b sind abgeschlossen.

### 25.09.2026 – Getrennte und bereichsgebundene Dateirechte

- Der zentrale Rechtekatalog unterscheidet Dateiansicht, Datei-/Ordnerpflege und Löschen. Das bestehende `files.manage` leitet alle drei Aktionen kompatibel ab. Persönliche Dateien behalten ihre Eigentümerrechte, und bestehende globale `file.view`-, `file.upload`- und `file.delete`-Berechtigungen funktionieren weiter.
- Datei- und Ordner-Policies verwenden dieselbe zentrale Bereichsauswertung. Mannschaftsrollen gelten nur im exakten Team; Abteilungsrollen gelten für Teams dieser Abteilung. Die Prüfung umfasst auch Dateien in Mannschaftsterminen. Vereinsfremde und nicht berechtigte Mannschaftsbereiche liefern weiterhin keinen Arbeitsbereich.
- Web und API prüfen den konkreten Zielbereich bereits vor Upload und Ordneranlage. Vorschau, Umbenennen und Löschen verwenden anschließend die Datei- beziehungsweise Ordner-Policy. Die veröffentlichten Bedienflags und `access_rights` spiegeln die getrennten Aktionen wider.
- Der Verhaltenstest wechselt zwischen reinem Lese-, Bearbeitungs- und Löschrecht, prüft Vorschau, Web-Ordner/Upload, API-Umbenennung/Löschung und eine fremde Abteilung. Datei-, Richtlinien-, Event-, Rollen- und Policy-Regression: **81 Tests, 2.436 Assertions**. Breite Regression: **138 Tests, 3.580 Assertions**. T017c2b2a und T017c3b2c sind abgeschlossen; Download/Export, Freigaben, Inventar und weitere Pfade bleiben offen.

### 25.09.2026 – Getrennter Dateiexport und Freigaben

- Download/Export und Freigabe besitzen nun eigene Rechte. Ein reines Leserecht erlaubt weiterhin Vorschau und Arbeitsbereich, aber weder Download noch das Kopieren an eine befreundete Person. Datei- und Ordnerfreigaben verwenden dasselbe Freigaberecht.
- Persönliche Eigentümer, bisher berechtigte Team-/Vereinsmitglieder, sichtbare Chat-Anhänge, sichtbare Antragsdokumente und bestehende globale Dateiberechtigungen behalten ihre bisherigen Möglichkeiten. `files.manage` leitet Ansicht, Bearbeitung, Löschung, Export und Freigabe kompatibel ab.
- `access_rights` veröffentlicht Export und Freigabe getrennt, sodass Web und native Clients die tatsächliche Policy darstellen können. Export- und Freigaberollen öffnen ausschließlich den zugewiesenen Mannschafts- beziehungsweise Abteilungsbereich.
- Der Bereichstest wechselt zusätzlich zwischen reinem Lese-, Export- und Freigaberecht und prüft Webdownload, API-Dateifreigabe, API-Ordnerfreigabe sowie die veröffentlichten Flags. Gezielte Regression: **81 Tests, 2.456 Assertions**. Breite Regression: **138 Tests, 3.600 Assertions**. T017c2b2b ist abgeschlossen; Inventar und weitere Datenarten bleiben offen.

### 25.09.2026 – Getrennte und bereichsgebundene Inventarrechte

- Der Rechtekatalog trennt Inventaransicht und eigene Ausleihe, Stammdaten-/Wartungspflege, Ausleihfreigabe/Rückgabe sowie Löschen. Das bisherige `inventory.manage` leitet alle Aktionen kompatibel ab; ausdrückliche personenbezogene Ausschlüsse behalten Vorrang.
- Inventargegenstände besitzen additive optionale Abteilungs- und Mannschaftsbezüge. Historische Gegenstände ohne Bezug bleiben vereinsweit. Mannschaftsrollen gelten nur im exakten Team, Abteilungsrollen auch für dessen Mannschaften. Beim Anlegen oder Verschieben werden Zielverein, Team-Abteilungs-Konsistenz und das Bearbeitungsrecht im Zielbereich erneut geprüft.
- API und Web liefern nur Gegenstände im wirksamen Bereich und veröffentlichen pro Gegenstand getrennte Bedienrechte. Ausleihen erscheinen nur der ausleihenden Person oder zuständigen Freigabe; Wartungen nur zuständiger Bearbeitung. Ein Löschrecht entfernt ausschließlich Gegenstände ohne Ausleih- oder Wartungshistorie. Zugeordnete Abteilungen und Mannschaften sind gegen versehentliches Löschen geschützt.
- Der Verhaltenstest wechselt dieselbe Person zwischen bereichsgebundenem Lese-, Bearbeitungs-, Freigabe- und Löschrecht, prüft fremde Abteilungen, geschützte Bereichswechsel, historische Löschsperre und den Webzugang. Gezielte Inventar-/Rollen-/Team-/Organisationsregression: **27 Tests, 311 Assertions**. Der Produktions-Build ist erfolgreich. Die vollständige Backend-Suite bestand mit Ausnahme eines bereits vorhandenen Lokalisierungsbefunds **1.318 Tests mit 31.088 Assertions**; der Befund wurde auf vier Webhook-Antworttexte eingegrenzt und in DE/EN/FR/AR katalogisiert. Der abschließende kombinierte Nachweis für Inventar, Rollen, Teams, Organisation, Lokalisierung und Postmark ist mit **53 Tests und 1.558 Assertions** grün. T017c2b2c1 und T017c3b2d1 sind abgeschlossen; weitere Datenarten, weitere Abteilungspfade und bereichsgebundene Vertretungen bleiben offen. Keine produktive Migration oder Veröffentlichung wurde ausgeführt.

### 25.09.2026 – Getrennte und bereichsgebundene Ankündigungsrechte

- Ankündigungen besitzen getrennte Rechte zum Anlegen/Bearbeiten von Entwürfen, zum Veröffentlichen und zum Löschen. `announcements.manage` leitet die drei Aktionen für bestehende Rollen weiterhin ab. Die API veröffentlicht die wirksamen Aktionen je Ankündigung einzeln.
- Eine reine Bearbeitungsrolle kann einen Entwurf anlegen und ändern, aber weder sofort noch geplant veröffentlichen. Eine reine Veröffentlichungsrolle kann den vorbereiteten Inhalt über einen eigenen Endpunkt freigeben, ohne Titel, Text oder Zielgruppe verändern zu können. Eine reine Löschrolle kann unveröffentlichte Entwürfe entfernen; bereits veröffentlichte oder versandte Mitteilungen bleiben als Kommunikationshistorie erhalten.
- Mannschaftsrollen gelten nur im exakten Team, Abteilungsrollen für dessen Mannschaften. Anlegen und jede Zielgruppenänderung prüfen auch den neuen Zielbereich. Bereichsverantwortliche sehen unveröffentlichte Mitteilungen ihres Bereichs; normale Mitglieder behalten ausschließlich die bisher sichtbaren veröffentlichten Vereins- und Teammitteilungen.
- Der Verhaltenstest wechselt dieselbe Person zwischen Bearbeitung, Veröffentlichung und Löschung, prüft fremde Teams, Zielbereichswechsel, Bedienflags und die Sperre veröffentlichter Inhalte. Gemeinsame Rollen-, Vertretungs-, Team-, Datei-, Inventar-, Umfrage-, Ankündigungs- und Lokalisierungsregression: **65 Tests, 2.724 Assertions**. T017c2b2c2a und T017c3b2d2a sind abgeschlossen; Umfragen, Support, weitere Datenarten und bereichsgebundene Vertretungen bleiben offen. Keine produktive Migration oder Veröffentlichung wurde ausgeführt.

### 25.09.2026 – Getrennte und bereichsgebundene Umfragerechte

- Umfragen besitzen getrennte Rechte zum Anlegen/Bearbeiten, Schließen und Löschen. `surveys.manage` leitet diese Aktionen für bestehende Rollen weiterhin ab; die API veröffentlicht die wirksamen Aktionen je Umfrage einzeln, und der bestehende Webarbeitsbereich zeigt die Schließen-Aktion nur mit dem tatsächlichen Schließrecht.
- Eine Bearbeitungsrolle kann Umfragen im eigenen Bereich anlegen und vor der ersten Stimme bearbeiten. Ab der ersten abgegebenen Stimme sind Frage, Optionen, Zielgruppe und Quorum unveränderlich. Eine Löschrolle kann nur Umfragen ohne Stimmen entfernen; damit bleibt vorhandene Abstimmungshistorie erhalten. Eine reine Schließrolle kann die Abstimmung beenden, ohne den Inhalt ändern oder löschen zu können.
- Mannschaftsrollen gelten nur im exakten Team, Abteilungsrollen für dessen Mannschaften. Beim Anlegen und bei jeder Zielgruppenänderung wird das Bearbeitungsrecht im Zielbereich geprüft. Normale aktive Vereins- und Mannschaftsmitglieder behalten ihre bisherigen Sicht- und Stimmrechte.
- Der Verhaltenstest wechselt dieselbe Person zwischen Bearbeitung, Schließen und Löschen und prüft fremde Teams, geschützte Zielwechsel, Stimmhistorie und Bedienflags. Der fokussierte Rollen-/Umfragenachweis ist mit **16 Tests und 171 Assertions** grün; die gemeinsame Rollen-, Vertretungs-, Team-, Datei-, Inventar-, Ankündigungs-, Umfrage-, Lokalisierungs- und Webvertragsregression besteht mit **68 Tests und 3.086 Assertions**. Der Produktions-Build ist erfolgreich. T017c2b2c2b1 und T017c3b2d2b1 sind abgeschlossen; Support, weitere Datenarten und bereichsgebundene Vertretungen bleiben offen. Keine produktive Migration oder Veröffentlichung wurde ausgeführt.

### 25.09.2026 – Getrennte Supportrechte mit Vereinsgrenze

- Der Rechtekatalog unterscheidet Supportansicht, fachliche Bearbeitung von Priorität, Notiz und Eskalation, Ticketzuweisung sowie Abschluss. Das bestehende `support.manage` leitet alle vier Aktionen kompatibel ab. Globale Plattform-Supportrechte behalten den mandantenübergreifenden Arbeitsbereich.
- Vereinsrollen sehen ausschließlich Tickets ihrer berechtigten Vereine. Reine Bearbeitungs-, Zuweisungs- und Abschlussrollen erhalten die für ihre Aktion notwendige Ticketansicht, ohne zusätzliche Schreibrechte zu erben. Der Server prüft nur tatsächlich geänderte Felder, sodass das bestehende Vollformular keine fremden Rechte voraussetzt und manipulierte Änderungen trotzdem abgewiesen werden.
- Die API veröffentlicht Bearbeiten, Zuweisen und Abschließen je Ticket getrennt. Die Weboberfläche deaktiviert die zugehörigen Felder und blendet Zuweisungs- sowie Speichermöglichkeiten passend aus. Zugewiesene Personen müssen das Ticket im betreffenden Verein selbst bearbeiten dürfen.
- Der Verhaltenstest wechselt zwischen reiner Ansicht, Bearbeitung, Zuweisung und Abschluss, prüft unerlaubte Mischaktionen sowie ein fremdes Vereinsticket. Fokussierte Support-/Rollenregression: **35 Tests, 659 Assertions**. Gemeinsame Rollen-, Vertretungs-, Team-, Datei-, Inventar-, Ankündigungs-, Umfrage-, Support-, Lokalisierungs- und Webvertragsregression: **79 Tests, 2.347 Assertions**. Der Produktions-Build ist erfolgreich. T017c2b2c2b2a ist abgeschlossen; weitere Datenarten, Abteilungs-/Mannschaftskontext für Support, bereichsgebundene Vertretungen und die übrigen T017-Arbeiten bleiben offen. Keine produktive Migration oder Veröffentlichung wurde ausgeführt.

### 25.09.2026 – Bereichsgebundene zeitliche Vertretungen

- Vertretungen speichern jetzt zusätzlich einen Vereins-, Abteilungs- oder Mannschaftsbereich. Bestehende Datensätze erhalten durch die additive Migration den bisherigen vereinsweiten Geltungsbereich; Laufzeit, Widerruf, Mitgliedschaftsstatus und die maximale Dauer von 90 Tagen bleiben unverändert.
- Die zentrale Rechteauswertung berücksichtigt eine Vertretung nur im passenden Kontext. Eine Abteilungsvertretung gilt dadurch auch in Mannschaften dieser Abteilung, aber weder vereinsweit noch in einer anderen Abteilung. Eine Mannschaftsvertretung bleibt auf genau diese Mannschaft begrenzt.
- Die Vergabe-API validiert Zielverein und Bereich und veröffentlicht den Bereich im Antwortvertrag. Die erteilende Person kann nur Rechte weitergeben, die sie im gewählten Zielbereich selbst besitzt; die nicht delegierbare Rollenverwaltung bleibt ausgeschlossen. Auditdaten enthalten Bereichstyp und technische Bereichskennung.
- Neun fokussierte Vertretungstests mit **58 Assertions** prüfen Verein, Abteilung, Mannschaft, Fremdmandant, fehlerhafte Bereiche, Weitergabeschutz, Zeitsteuerung, Widerruf und reversible Migration. Die abschließende gemeinsame Rechte-, Rollen-, Vertretungs-, Team-, Datei-, Inventar-, Ankündigungs-, Umfrage-, Support-, Lokalisierungs- und Webvertragsregression besteht mit **88 Tests und 2.397 Assertions**. T017c3b2d2b2a ist abgeschlossen; fachliche Supportbereiche und weitere Abteilungspfade bleiben offen. Keine produktive Migration oder Veröffentlichung wurde ausgeführt.

### 25.09.2026 – Webverwaltung für Rollen, Einzelrechte und Vertretungen

- Die Mitgliederverwaltung bietet bei berechtigten Personen pro Mitglied einen eigenen Zugriffsdialog. Einzelrechte können auf Rollenstandard, ausdrückliche Erlaubnis oder ausdrücklichen Ausschluss gesetzt werden; die wirksame Entscheidung bleibt daneben sichtbar. Eigentümerrechte bleiben serverseitig und in der Bedienung geschützt.
- Konfigurierbare Rollen lassen sich aus den elf Vorlagen anlegen, bearbeiten, aktivieren/deaktivieren und unbenutzt löschen. Mehrere Rollen können demselben Mitglied jeweils vereinsweit, für eine Abteilung oder für eine Mannschaft zugewiesen werden.
- Zeitlich begrenzte Vertretungen lassen sich mit Beginn, Ende, Rechten und Bereich vergeben. Aktive und geplante Vertretungen sind sofort widerrufbar; Status, Bereich und wirksame Rechte werden lesbar dargestellt. Der Dialog ist in DE/EN/FR/AR vorhanden, verwendet das fokussierende Modal, beschriftete Formularfelder, Tastaturtauglichkeit, Tab-Zustände, Status- und Fehlermeldungen ohne unsichere HTML-Ausgabe.
- Der Einstieg in die Mitgliederverwaltung wertet jetzt auch konfigurierbare vereinsweite Rechte aus. Damit kann eine eigens vergebene Rollenverwaltung den Webarbeitsbereich tatsächlich öffnen, während normale Mitglieder und reine Mannschaftstrainer ausgeschlossen bleiben. Sechs fokussierte Web-/Zugriffstests bestehen mit **89 Assertions**; die gemeinsame Mitglieder-, Rechte-, Rollen-, Vertretungs-, Team-, Datei-, Inventar-, Ankündigungs-, Umfrage-, Support-, Lokalisierungs- und Webregression besteht mit **105 Tests und 2.625 Assertions**. Der Produktions-Build, PHP-Formatierung und Diff-Prüfung sind grün. T017e ist abgeschlossen. Keine produktive Migration oder Veröffentlichung wurde ausgeführt.

### 25.09.2026 – Rollout-Prüfer für Vereinsorganisation und Gremien

- `airmius:audit-club-structure` prüft Repositoryvertrag und Evidenzvorlage ohne Datenbankzugriff. `--with-data` ergänzt ausschließlich aggregierte, lesende Kontrollen für Tabellen, Vereinsgrenzen, Trainingsgruppen-/Mannschaftskonsistenz, Gremientypen, Zeiträume sowie die eindeutige interne oder externe Personenzuordnung.
- Der Bericht enthält keine Vereins-, Datensatz- oder Personenkennungen, Namen, Kontakte, Adressen oder Freitexte. Bestehende nicht zugeordnete Mannschaften werden nur gezählt und weder interpretiert noch verändert.
- Der versionierte Vertrag `club-structure-rollout.v1` verlangt Migrations-/Rollbacknachweis, Web-Mobile, Web-Desktop, Android, iOS, sieben Kernabläufe sowie Produkt- und Engineering-Freigabe. Evidenz akzeptiert ausschließlich kurze nicht-sensitive Referenzen.
- Drei neue Verhaltenstests mit **21 Assertions** prüfen lesenden Betrieb, saubere Bestände, Cross-Club-/Konsistenzfehler, Nichtoffenlegung und das strenge Go/No-Go. Gemeinsam mit den bestehenden Organisations- und Gremienregressionen bestehen **11 Tests mit 104 Assertions**. Repository-only besteht automatisiert und entscheidet erwartungsgemäß `no-go`, solange Runtime- und Realgerätebelege fehlen.
- Anleitung: [CLUB_STRUCTURE_ROLLOUT.md](CLUB_STRUCTURE_ROLLOUT.md). T012d1 und T013d1 sind abgeschlossen; die realen Staging-, Browser- und Geräteabnahmen bleiben T012d2/T013d2.

### 24.09.2026 – Backend für unabhängige Vereinsjahre

- Geschäftsjahre, Beitragsjahre und Sportjahre werden als getrennte, benannte Zeiträume gespeichert. Verschiedene Typen dürfen zeitlich voneinander abweichen und sich überschneiden; innerhalb desselben Typs verhindert der Server doppelte Grenztage und sonstige Überschneidungen.
- Vereinsverwaltung und Mitglieder dürfen die Zeiträume lesen. Nur die bestehende Vereinsverwaltung mit `update`-Berechtigung darf anlegen, ändern oder löschen. Fremde Vereinszeiträume werden nicht offengelegt. Die API kennzeichnet jeden Zeitraum anhand des aktuellen Datums als vergangen, aktuell oder zukünftig.
- Schreiben erzeugt datensparsame `club.year_period.*`-Auditeinträge ohne Namen oder Datumswerte. Bestehende Beitrags-, Rechnungs-, Berichts- und Saisonlogik bleibt unverändert; deren kontrollierte fachliche Anbindung folgt in T014d.
- Fokussierter Nachweis: **4 Tests, 28 Assertions**. Gemeinsame Perioden-/Governance-/Organisations-/Rollen-/Lokalisierungsregression: **16 Tests, 1.085 Assertions**. Vollständige Backend-Regression gegen den finalen isolierten Web-Build: **1.235 bestanden, 4 übersprungen, 30.200 Assertions** (`/tmp/airmius-year-period-backend-full.log`). PHP-Formatierung, Routenerkennung und `git diff --check` ohne Befund. Detaildokumentation: `docs/CLUB_YEAR_PERIODS.md`.
- Keine produktive Migration oder Veröffentlichung. Fachliche Verknüpfungen, Bestandsprobe und reale Abnahme bleiben in T014d und T014e offen.

### 24.09.2026 – Web-Verwaltung unabhängiger Vereinsjahre

- Das Vereinsprofil zeigt Geschäftsjahre, Beitragsjahre und Sportjahre in getrennten Bereichen mit Name, Beginn, Ende und serverseitig ermitteltem Status. Der Abschnitt wird nur für Vereinsmitglieder und Vereinsverwaltung geladen.
- Berechtigte Vereinsverwaltung kann Zeiträume anlegen, bearbeiten und nach ausdrücklicher Bestätigung löschen. Serverseitige Überschneidungs-, Datums-, Rollen- und Vereinsprüfungen bleiben maßgeblich; Fehler werden sichtbar angezeigt und Schreibaktionen nicht automatisch wiederholt.
- Texte und programmatische Feldnamen liegen in DE/EN/FR/AR in `resources/js/i18n/clubYearPeriodsLocalization.json`. Drei gemeinsame Web-Testdateien für Organisation, Governance und Vereinsjahre bestanden. Bundle-, Lokalisierungs- und Accessibility-Verträge gegen den finalen Build: **30 Tests, 1.008 Assertions**.
- Isolierter Produktions-Build `/tmp/airmius-year-period-web-build` erfolgreich; produktive Assets unverändert. Fokussierte Backend-/API-Pfadregression: **7 Tests, 35 Assertions**; `git diff --check` ohne Befund.
- T014d und T014e bleiben offen: kontrollierte fachliche Anbindung an Beiträge/Buchhaltung/Berichte/Sportplanung sowie Bestands- und Realgeräteabnahme. Keine produktive Migration oder Veröffentlichung ausgeführt.

### 24.09.2026 – Native App für unabhängige Vereinsjahre

- Die Vereinsorganisation führt für Mitglieder und Vereinsverwaltung zu einer eigenen nativen Ansicht. Geschäftsjahre, Beitragsjahre und Sportjahre erscheinen getrennt mit Name, Beginn, Ende und serverseitigem Status.
- Berechtigte Vereinsverwaltung kann Zeiträume in einem nativen Dialog anlegen und bearbeiten sowie nach ausdrücklicher Bestätigung löschen. Pflichtfelder, Datumsformat und Reihenfolge werden vor Versand geprüft; Überschneidungs-, Rollen- und Vereinsprüfungen bleiben zusätzlich serverseitig maßgeblich.
- Vereinsjahre-Schreibaktionen werden weder in die Offline-Warteschlange aufgenommen noch nach einem unklaren Transportfehler automatisch wiederholt. Texte und programmatische Feldnamen stehen in DE/EN/FR/AR bereit.
- Gezielte Perioden-/Governance-/Organisationsregression: **12 Tests bestanden**. Zentraler Mobile-API-Routenvertrag und Backend-Periodenregression: **7 Tests, 35 Assertions**. Vollständige Flutter-Regression: **406 Tests bestanden** (`/tmp/airmius-year-period-mobile-full.log`); statische Analyse ohne Befund. Dart-Formatierung und `git diff --check` ohne Befund.
- T014d und T014e bleiben offen: fachlich kontrollierte Anbindung an Beiträge, Buchhaltung, Berichte und Sportplanung sowie Bestandsprobe, Browser-/Realgeräteabnahme und Einführung. Keine produktive Migration oder Veröffentlichung ausgeführt.

### 24.09.2026 – Nicht rückwirkende Zuordnung operativer Vorgänge

- Neue Vereinsrechnungen erhalten beim Anlegen das passende Geschäftsjahr. Automatisch erzeugte Beitragsrechnungen erhalten zusätzlich das passende Beitragsjahr anhand ihres Abrechnungsbeginns. Neue Bankvorgänge werden anhand des Buchungstags einem Geschäftsjahr, neue Vereins- und Mannschaftstermine anhand ihres Beginns einem Sportjahr zugeordnet.
- Die additive Migration lässt alle Verweise bestehender Rechnungen, Bankvorgänge und Termine leer. Es findet keine automatische Rückdatierung oder nachträgliche Interpretation historischer Vorgänge statt. Eine einmal gespeicherte Periodenzuordnung bleibt auch bei einer späteren Datumsänderung unverändert.
- Perioden-IDs sind nicht massenzuweisbar und werden ausschließlich über den vereinsgebundenen Resolver gesetzt. Verwendete Zeiträume können weder über die API noch über den Fremdschlüssel gelöscht werden. Vorgänge ohne passenden definierten Zeitraum bleiben ausdrücklich unzugeordnet.
- Fokussierter Zuordnungs- und Löschschutznachweis zusammen mit der bisherigen Perioden-API: **7 Tests, 44 Assertions**. Angrenzende Beitrags-, Rechnungs-, Zahlungs-, SEPA-, Bank- und Terminregression: **142 Tests, 1.969 Assertions**.
- Vollständige Backend-Regression gegen `/tmp/airmius-year-period-web-build`: **1.238 bestanden, 4 übersprungen, 30.216 Assertions** (`/tmp/airmius-year-period-links-backend-full.log`). PHP-Formatierung, Routenerkennung und `git diff --check` ohne Befund.
- T014d2 bis T014d4 bleiben offen: Berichtsfilter, sichtbare Periodenbezüge in Details und Exporten sowie Saisonplanung. Keine produktive Migration, Bestandszuordnung oder Veröffentlichung ausgeführt.

### 24.09.2026 – Periodenberichte mit getrenntem historischem Bestand

- Der geschützte Endpunkt `GET /api/v1/clubs/{club}/year-periods/report` filtert ausschließlich über die beim Anlegen eingefrorenen Perioden-IDs. Geschäftsjahre enthalten Rechnungs-, Bankvorgangs- und manuelle Buchungskennzahlen, Beitragsjahre ausschließlich automatisch erzeugte Beitragsrechnungen und Sportjahre geplante beziehungsweise abgesagte Termine nach Typ.
- Bestehende manuelle Kassen- und Bankbuchungen bleiben durch eine additive, nullable Zuordnung unverändert. Neue Buchungen erhalten anhand des Buchungstags ein Geschäftsjahr. Auch diese Zuordnung wird bei späteren Datumsänderungen nicht neu berechnet und schützt verwendete Zeiträume gegen Löschen.
- Jede Periodenauswertung liefert den unzugeordneten historischen Bestand zusätzlich als eigenen `unassigned`-Block. Der Filter `period_id=unassigned` zeigt ihn gezielt an. Es gibt keine datumsgestützte Rückinterpretation. Vereinsfremde oder typfremde Perioden werden abgewiesen; Zugriff erfordert die bestehende Berechtigung `finance.view`.
- Web und native App bieten bei den Vereinsjahren Auswertungsaktionen für definierte Perioden sowie für den historischen unzugeordneten Bestand. Rechnungsbeträge, Bankbewegungen, manuelle Buchungen und Sporttermine sind in DE/EN/FR/AR lesbar aufbereitet.
- Fokussierter Backend-Nachweis einschließlich Zuordnung, Löschschutz, Berechtigungen, Vereinsgrenzen und API-Pfad: **10 Tests, 66 Assertions**; Perioden-/Berichts-/Verwaltungsnachweis insgesamt zuletzt **11 Tests, 89 Assertions**. Web-Vertrag und isolierter Produktions-Build `/tmp/airmius-year-period-report-web-build` bestanden.
- Vollständige Regression: Backend **1.242 bestanden, 4 übersprungen, 30.263 Assertions** (`/tmp/airmius-year-period-report-backend-final.log`), Flutter **406 Tests bestanden**, Flutter-Analyse ohne Befund. PHP-/Dart-Formatierung und `git diff --check` ohne Befund.
- T014d3 bis T014d4 bleiben offen: Periodenbezüge in einzelnen Details und Exporten sowie Verwendung in Saison- und Mannschaftsplanung. Keine produktive Migration, Bestandszuordnung oder Veröffentlichung ausgeführt.

### 24.09.2026 – Sichtbare Periodenbezüge in Details und DATEV

- Rechnungsressourcen liefern Geschäfts- und Beitragsjahr jeweils als unveränderliche ID und, sofern geladen, mit Name und Datumsgrenzen. Buchungen und Bankvorgänge liefern ihr Geschäftsjahr, Vereins- und Mannschaftstermine ihr Sportjahr. Web-Finanzlisten und Web-/App-Terminansichten zeigen den Namen oder ausdrücklich „historisch unzugeordnet“.
- Die DATEV-Ausgabe behält ihre standardisierte Spaltenstruktur. Der Buchungstext beginnt mit dem Geschäftsjahr und enthält bei echten Beitragsrechnungen zusätzlich das Beitragsjahr; fehlende historische Zuordnungen werden als `GJ unzugeordnet` beziehungsweise `BJ unzugeordnet` sichtbar. Rücklastschriftgebühren tragen ebenfalls ihr gespeichertes Geschäftsjahr.
- Die Darstellung greift ausschließlich auf gespeicherte Fremdschlüssel zurück. Weder Detailansichten noch Export leiten aus Datum oder Belegjahr eine nachträgliche Periodenzuordnung ab.
- Angrenzende Perioden-, Finanz-, SEPA- und Eventregression: **82 Tests, 1.263 Assertions**; der zunächst in der Gesamtsuite gefundene falsche Relation-Load an Mitgliedsanträgen wurde korrigiert und mit **12 Tests, 216 Assertions** gezielt nachgeprüft.
- Isolierter Produktions-Build `/tmp/airmius-year-period-details-web-build` erfolgreich. Vollständige Regression: Backend **1.243 bestanden, 4 übersprungen, 30.272 Assertions** (`/tmp/airmius-year-period-details-backend-full-final.log`), Flutter **406 Tests bestanden**, Flutter-Analyse ohne Befund. PHP-/Dart-Formatierung und `git diff --check` ohne Befund.
- T014d4 bleibt offen: Sportjahre in Saison- und Mannschaftsplanung verwenden und die fachliche Periodenlogik abnehmen. Keine produktive Migration oder Veröffentlichung ausgeführt.

### 24.09.2026 – Sportjahre in Mannschafts- und Saisonplanung

- Mannschaften besitzen eine additive, optionale und restriktiv referenzierte Sportjahr-Zuordnung. Bestehende Mannschaften bleiben unverändert und damit ohne Zuordnung; es gibt weder eine Rückdatierung noch eine automatische Änderung bereits gespeicherter Termine. Verwendete Sportjahre sind auch durch Mannschaftszuordnungen gegen Löschen geschützt.
- Web und native App lassen die Vereinsverwaltung beziehungsweise berechtigte Mannschaftsverwaltung nur Sportjahre desselben Vereins auswählen. Fremde Vereinszeiträume und Geschäfts- oder Beitragsjahre werden serverseitig abgewiesen. Änderungen erzeugen einen datensparsamen `club.sport_year.team_assigned`-Auditeintrag.
- Bei ausdrücklich zugeordneten Mannschaften verwenden die Team-Alltagsansicht und die Wettbewerbs-/Saisonplanung ausschließlich Termine mit exakt derselben gespeicherten Sportjahr-ID. Der nächste Termin, Anwesenheit und andere operative Alltagsfunktionen bleiben davon unberührt. Termine anderer Sportjahre und historisch unzuordnete Termine werden als eigene Kennzahlen ausgewiesen.
- Mannschaften ohne ausgewähltes Sportjahr behalten die bisherige Planung über alle Mannschaftstermine. Der API-Vertrag kennzeichnet diesen Zustand als `period_selection=unassigned`; Web und App zeigen ihn ausdrücklich als historischen unzugeordneten Bestand. Die Oberfläche nennt zusätzlich, dass eine Auswahl bestehende Termine nicht verändert.
- Fokussierte Perioden-, Team-Alltags- und Wettbewerbsregression: **16 Tests, 251 Assertions**. Vollständige Regression gegen den isolierten Build `/tmp/airmius-team-sport-year-web-build`: Backend **1.246 bestanden, 4 übersprungen, 30.308 Assertions**, Flutter **406 Tests bestanden**, Web-Frontend **11/11 Tests bestanden**. Produktions-Build, PHP-/Dart-Formatierung und Flutter-Analyse ohne Befund.
- T014d ist damit vollständig umgesetzt und fachlich abgenommen. T014 und T014e bleiben offen: Bestandsmigration beziehungsweise bewusste Bestandszuordnung, echte Browser-/Realgeräteabnahme und kontrollierte Einführung wurden nicht ausgeführt.

### 24.09.2026 – Read-only-Bestandsaudit und Einführungsvertrag

- Der neue Befehl `airmius:audit-club-year-periods` prüft Repository und Evidenzvertrag. Mit `--with-data` prüft er zusätzlich ausschließlich lesend das additive Schema, falsche Vereins- oder Typreferenzen, Periodenüberschneidungen und aggregierte unzugeordnete Bestände. Er gibt keine fachlichen Datensatz-IDs aus und schreibt keine Zuordnung.
- Der Audit behandelt historische `null`-Verweise ausdrücklich als Inventar und nicht als Fehler. Dadurch entsteht keine verdeckte datumsgestützte Rückinterpretation. Falsche Referenzen oder Überschneidungen stoppen dagegen die Freigabe.
- `resources/release/club_year_period_evidence.template.json` bindet Backup, Dry-run, Rollback-Probe, Web Mobile, Web Desktop, Android, iOS, vier Kernwege sowie Produkt- und Engineering-Freigabe an den Vertrag `club-year-period-rollout.v1`. Nur kurze Evidenzreferenzen sind zulässig; unvollständige Evidenz ergibt im strengen Modus weiterhin `no-go`.
- Die Anleitung `docs/CLUB_YEAR_PERIOD_ROLLOUT.md` beschreibt Reihenfolge, Datenschutzgrenzen, Abbruchkriterien und den strengen Abschlussbefehl. Eine automatische Bestandszuordnung ist ausdrücklich ausgeschlossen.
- Fokussierter Nachweis für Audit, Perioden-API, Berichte, operative Zuordnung und Saisonplanung: **19 Tests, 153 Assertions**. Vollständige Backend-Regression gegen den isolierten Web-Build: **1.250 bestanden, 4 übersprungen, 30.329 Assertions**. Der Repository-Audit meldet alle automatisierbaren statischen Prüfungen grün. Der Read-only-Lauf gegen die konfigurierte lokale Umgebung meldet strukturiert `database.runtime=fail` und `no-go`, weil MySQL auf `127.0.0.1:3306` nicht erreichbar ist; unter Port 80 läuft ebenfalls keine Web-Anwendung. Es wurden keine Daten verändert und keine Dienste gestartet.
- T014e1 ist abgeschlossen. T014e2, T014e3 und T014 bleiben offen, bis eine freigegebene Staging-Kopie sowie echte Browser und Android/iOS-Geräte geprüft und die Freigaben dokumentiert wurden. Keine produktive Migration oder Veröffentlichung ausgeführt.

### 24.09.2026 – Backend-Grundlage für eigene Datenfelder, Kategorien und Nummernkreise

- Vier additive Tabellen speichern vereinsgebundene Felddefinitionen, Kategorien, Nummernkreise und unveränderliche Nummernvergaben. Eigene Felder unterstützen Text, Langtext, Zahl, Datum, Ja/Nein und feste Auswahllisten sowie Pflicht-, Sensibilitäts-, Aktiv- und Sortierkennzeichen. Kategorien und Nummernkreise besitzen ausdrücklich begrenzte fachliche Geltungsbereiche.
- Ausschließlich die bestehende Vereinsverwaltung mit `update`-Berechtigung darf die Konfiguration lesen oder ändern. Fremde Vereinsdatensätze werden nicht offengelegt. Auditdaten enthalten weder Feldschlüssel oder Bezeichnungen noch Auswahlwerte, Kategorienamen, Präfixe oder vergebene Nummern.
- Die Nummernvergabe sperrt den Zähler innerhalb einer Datenbanktransaktion und verlangt einen UUID-Idempotenzschlüssel. Wiederholungen liefern dieselbe Vergabe ohne weiteren Zählschritt oder Auditdatensatz. Jährliche Rücksetzungen erfordern `{YYYY}` oder `{YY}`; nach der ersten Vergabe sind Format und Zählregel unveränderlich, und der Nummernkreis ist gegen Löschen geschützt.
- Die vorhandenen Mitglieds-, Rechnungs- und Shopnummern bleiben unverändert. Feldwerte, Kategoriezuordnungen und die kontrollierte Einbindung der neuen Nummernkreise folgen in T015b. Dadurch ersetzt T015a keine bestehende Nummernlogik im Hintergrund.
- Fokussierter Nachweis: **5 Tests, 61 Assertions**; angrenzende Organisations-, Vereinsjahres-, Mitgliedsrechnungs- und Importregression: **19 Tests, 206 Assertions**. Vollständige Backend-Regression gegen den unveränderten isolierten Web-Build `/tmp/airmius-team-sport-year-web-build`: **1.255 bestanden, 4 übersprungen, 30.412 Assertions**. Routenerkennung, PHP-Formatierung und `git diff --check` ohne Befund. Detaildokumentation: `docs/CLUB_METADATA_CONFIGURATION.md`.
- T015a ist abgeschlossen. T015b bis T015e und damit T015 bleiben offen. Insbesondere sind Werteebene, Web/App-Oberflächen, Umstellung bestehender Nummernwege, MySQL-Konkurrenzprüfung und reale Abnahme noch nicht umgesetzt. Keine produktive Migration oder Veröffentlichung ausgeführt.

### 24.09.2026 – Typsichere Feldwerte und Kategoriezuordnungen

- Zwei additive Tabellen binden Werte und Kategoriezuordnungen über feste serverseitige Zieltypen an interne sowie externe Mitglieder, Mannschaften, Termine und Inventargegenstände. Frei übertragbare Modellklassen sind ausgeschlossen; jedes Ziel, jede Felddefinition und jede Kategorie wird erneut innerhalb desselben Vereins aufgelöst.
- Text, Langtext, Dezimalzahl, ISO-Datum, Boolean und Auswahlwert werden ihrem Feldtyp entsprechend normalisiert und geprüft. Ein vollständiges Update prüft alle aktiven Pflichtfelder vor dem Schreiben und ersetzt Werte sowie Kategorien gemeinsam in einer Transaktion. Ein ungültiger Einzelwert, falscher Geltungsbereich oder fremder Datensatz hinterlässt keine Teiländerung.
- Sensible und nicht sensible Werte sind im Backend-Endpunkt ausschließlich für die bestehende Vereinsverwaltung lesbar. Auditeinträge enthalten nur den technischen Entitätstyp und weder Feld-/Kategorienamen noch Werte. Deaktivierte historische Werte und Zuordnungen bleiben erhalten; verwendete Definitionen und Kategorien sind gegen Löschen beziehungsweise fachliche Umdeutung geschützt.
- Beim Löschen eines Fachobjekts werden dessen generische Metadaten bereinigt. Mitgliedsentfernung und Vereinsaustritt entfernen die vereinsbezogenen Mitgliedsmetadaten in derselben Transaktion wie die Mitgliedschaft. Bestehende Mitglieds-, Team-, Termin- und Inventarabläufe bleiben ansonsten unverändert.
- Fokussierte Metadatenprüfung: **9 Tests, 111 Assertions**. Gemeinsame Metadaten-, Mitgliedschafts- und API-Regressionsprüfung: **19 Tests, 269 Assertions**. Vollständige Backend-Regression gegen `/tmp/airmius-team-sport-year-web-build`: **1.259 bestanden, 4 übersprungen, 30.479 Assertions**. PHP-Formatierung, 13 Metadatenrouten und `git diff --check` ohne Befund. Detaildokumentation: `docs/CLUB_METADATA_CONFIGURATION.md`.
- T015b1 ist abgeschlossen. T015b2 bis T015e und damit T015 bleiben offen. Bestehende Nummernwege wurden weiterhin nicht umgestellt; produktive Migration, Browser-/Realgeräteabnahme und Veröffentlichung wurden nicht ausgeführt.

### 24.09.2026 – Read-only-Bestandsaudit und Standardkreise für Nummernwege

- Eine additive Zuordnung speichert je Verein und Geltungsbereich höchstens einen ausdrücklich gewählten aktiven Standardnummernkreis. Setzen und Entfernen werden pro Verein serialisiert und gemeinsam mit einem datensparsamen Audit transaktional geschrieben. Zugeordnete Kreise sind gegen Löschen und Änderung ihres Geltungsbereichs geschützt.
- Die Geltungsbereiche decken Mitglied, Vereinsrechnung, Beleg, Spende, Inventar sowie vereinsbezogene Shoprechnung, Shopgutschrift und Shop-SKU ab. Eine Standardzuordnung aktiviert noch keinen Fachpfad und verändert weder vorhandene Nummern noch Zähler.
- `airmius:audit-club-number-ranges` prüft Repository und optional ausschließlich lesend Schema, Vereins-/Scope-Referenzen, doppelte Bestandsnummerngruppen, fehlende Standards und Kollisionen zwischen Vergaben und Bestandsnummern. Ausgabe und JSON enthalten nur Summen, keine IDs, Nummernwerte, Namen, Präfixe oder freien Referenzen.
- Der Audit weist Zahlungsreferenzen ausdrücklich nicht als kanonische Beleg- oder Spendennummer aus. Der strenge Modus bleibt bis zu eigenen unveränderlichen Feldern und der kontrollierten Fachanbindung auf `no-go`. Die Adoptions- und Abbruchregeln sind in `docs/CLUB_NUMBER_RANGE_ADOPTION.md` dokumentiert.
- Fokussierte Standardkreis-/Auditprüfung: **9 Tests, 97 Assertions**. Metadaten-, Mitgliedsrechnungs-, Inventar- und Shopregression: **35 Tests, 320 Assertions**. Vollständige Backend-Regression gegen `/tmp/airmius-team-sport-year-web-build`: **1.263 bestanden, 4 übersprungen, 30.519 Assertions**. PHP-Formatierung, 15 Metadatenrouten und `git diff --check` ohne Befund.
- Repository-Audit grün; der Read-only-Datenlauf gegen die konfigurierte lokale Umgebung meldet strukturiert `database.runtime=fail` und `no-go`, weil die lokale MySQL-Instanz nicht erreichbar ist. Keine Daten wurden verändert und kein Dienst gestartet. T015b2b bis T015e sowie T015 bleiben offen.

### 24.09.2026 – Mitglieds- und Vereinsrechnungsnummern aus Standardkreisen

- Neue interne Mitgliedsnummern verwenden einen ausdrücklich zugeordneten Standardkreis im Geltungsbereich `member`. Manuell und wiederkehrend erzeugte Vereinsrechnungen verwenden entsprechend den Standardkreis `invoice`. Jede Vergabe wird nach dem Anlegen mit genau ihrem Mitglied beziehungsweise ihrer Rechnung verknüpft, sodass der Read-only-Audit eine ordnungsgemäße Vergabe von einer echten Bestandskollision unterscheiden kann.
- Vergabe, Kollisionsprüfung, Fachdatensatz und Zählerfortschritt liegen in einer gemeinsamen Datenbanktransaktion. Eine Kollision mit vorhandenen internen oder externen Mitgliedsnummern beziehungsweise global vorhandenen Rechnungsnummern rollt die Vergabe einschließlich Zähler zurück. Wiederholte Beitragsabrechnung für denselben Zeitraum erzeugt weder eine zweite Rechnung noch eine zweite Vergabe.
- Ohne zugeordneten Standardkreis bleiben die bisherigen Formate und Zählregeln unverändert. Ausdrücklich eingegebene Rechnungsnummern der Plattformverwaltung und fachlich fest vorgegebene Sondernummern werden weiterhin nicht ersetzt. Der mobile Admin-Vertrag ruft denselben kompatiblen Rechnungsweg auf.
- Die additive Vergabetabelle speichert nur technische Zielart und Ziel-ID. Zugeordnete Vergaben können nicht nachträglich auf einen anderen Fachdatensatz umgebogen werden. Präfixe, vollständige Nummern und fachliche Freitexte gelangen weiterhin nicht in neue Auditdaten.
- Fokussierter Integrationsnachweis: **4 Tests, 34 Assertions**. Gemeinsame Nummernkreis-, Rechnungs-, Beitrags- und Mobile-Admin-Prüfung: **39 Tests, 351 Assertions**; nach Korrektur des direkten Mobile-Admin-Aufrufs zusätzlich **9 Tests, 121 Assertions**. Vollständige Backend-Regression gegen `/tmp/airmius-team-sport-year-web-build`: **1.267 bestanden, 4 übersprungen, 30.553 Assertions**. PHP-Formatierung, 15 Metadatenrouten und `git diff --check` ohne Befund.
- Der Repository-Audit ist grün und bleibt erwartungsgemäß `no-go`, solange Beleg-, Spenden-, Inventar- und Shopnummern nicht in T015b2c angebunden sind. T015b2, T015b und T015 bleiben offen. Ein echter Parallelitätsnachweis mit mehreren MySQL-Verbindungen sowie Bestandsmigration und Einführung gehören weiterhin zu T015e; keine produktive Migration oder Veröffentlichung wurde ausgeführt.

### 24.09.2026 – Kanonische Beleg-, Spenden-, Inventar- und Shopnummern

- Zahlungen besitzen additive, optionale und je Verein eindeutige `receipt_number`- und `donation_number`-Felder. Externe Bank-, Bar- und Freitextreferenzen verbleiben unverändert in `reference`; historische Zahlungen werden nicht rückwirkend umgedeutet. Spenden können getrennte Beleg- und Spendennummern aus zwei ausdrücklich zugeordneten Standardkreisen erhalten.
- Rechnungszahlungen, Spenden, Vorauszahlungen und manuell administrierte Zahlungen verwenden einen gemeinsamen transaktionalen Erzeuger. Nummernvergabe, Zahlungsdatensatz, Zielzuordnung und Zählerfortschritt werden gemeinsam geschrieben oder bei einer Kollision vollständig zurückgerollt.
- Leere Inventar-SKUs sowie leere SKUs vereinsbezogener Shopartikel verwenden optional ihren Standardkreis. Webformular, Plattformverwaltung und Dateiimport laufen für Shopartikel über denselben Erzeuger. Manuell eingegebene SKUs bleiben exakt erhalten.
- Vereinsbezogene Shoprechnungen und Shopgutschriften verwenden beim Abschluss, Storno oder erfolgreichen Erstattungsweg optional ihre Standardkreise. Bereits gespeicherte Dokumentnummern werden nicht ersetzt. Ohne Standard bleiben die bisherigen globalen Formate und die bisherigen Erstattungsnummern bestehen.
- Fokussierter T015b2-Integrationsnachweis: **9 Tests, 77 Assertions**. Gemeinsame Metadaten-, Nummernkreis-, Zahlungs-, Inventar-, Shop-, Erstattungs- und SEPA-Regressionsprüfung: **135 Tests, 1.770 Assertions**. Vollständige Backend-Regression gegen `/tmp/airmius-team-sport-year-web-build`: **1.272 bestanden, 4 übersprungen, 30.596 Assertions**. PHP-Formatierung, 15 Metadatenrouten und `git diff --check` ohne Befund.
- Der Repository-Audit meldet Repositoryvertrag, kanonische Speicherung und Laufzeitanbindung grün. Die Gesamtentscheidung bleibt bis zur freigegebenen Laufzeit-Bestandsprüfung, zum echten MySQL-Mehrverbindungsnachweis und zur kontrollierten Einführung in T015e auf `no-go`. T015b und T015b2 sind abgeschlossen; T015c bis T015e sowie T015 bleiben offen. Keine produktive Migration oder Veröffentlichung wurde ausgeführt.

### 24.09.2026 – Web-Konfiguration eigener Daten und Nummernkreise

- Die Vereinsverwaltung besitzt im Vereinsprofil einen eigenen, nur für berechtigte Personen eingebundenen Bereich mit drei klar getrennten Registerkarten für Felddefinitionen, Kategorien und Nummernkreise. Laden, Fehlerzustand, erneutes Laden, Bearbeiten, Abbrechen und bestätigtes Löschen sind sichtbar und per Tastatur erreichbar.
- Eigene Felder unterstützen alle Backendtypen, Zielbereiche, Auswahllisten, Pflicht-, Sensibilitäts-, Aktiv- und Sortierkennzeichen. Kategorien bieten Zielbereich, Name, Farbe, Aktivstatus und Sortierung. Nummernkreise bieten alle acht Geltungsbereiche, Präfix/Suffix, Stellenzahl, Startnummer, Rücksetzungsregel, Aktivstatus, Vorschau, nächsten Zähler und Vergabeanzahl.
- Standardnummernkreise können direkt gesetzt und entfernt werden. Die Oberfläche ersetzt keine serverseitige Prüfung: verwendete Strukturen, ungültige Jahrestokens, inaktive Standards, Vereinsgrenzen und Rollen bleiben durch die bestehende API geschützt; die erste konkrete Validierungsnachricht wird zugänglich als Fehler ausgegeben.
- Alle funktionsbezogenen Texte und programmatischen Feldbeschriftungen stehen mit identischem Schlüsselumfang in DE/EN/FR/AR bereit. Tabs besitzen Rollen, Zuordnung und Auswahlstatus; Formularfelder sind sichtbar beschriftet, Schreibaktionen während der Verarbeitung gesperrt.
- Neuer Frontendvertrag und alle angrenzenden Frontendverträge: **12 Dateien bestanden**. API-, Nummernkreis-, Manifest- und Lokalisierungsregression: **21 Tests, 1.897 Assertions**. Isolierter Produktions-Build `/tmp/airmius-metadata-web-build` erfolgreich. Vollständige Backend-Regression dagegen: **1.272 bestanden, 4 übersprungen, 30.596 Assertions**; `git diff --check` ohne Befund.
- T015c1 ist abgeschlossen. T015c2 und damit T015c bleiben offen, bis die Werte und Kategorien an Mitgliedern, Mannschaften, Terminen und Inventar im Web gepflegt und regressionsgeprüft werden. Keine produktive Migration oder Veröffentlichung wurde ausgeführt.

### 24.09.2026 – Web-Wertepflege an Vereinsfachobjekten

- Ein gemeinsamer Fachwerte-Editor ist in der Mitgliederverwaltung für interne und externe Mitglieder, in der Mannschaftsverwaltung, auf Termindetails und im Inventar eingebunden. Der Editor lädt erst beim Öffnen und verwendet für alle Zieltypen denselben vereinsgebundenen Lese-/Schreibvertrag.
- Text, Langtext, Zahl, Datum, Ja/Nein und feste Auswahl werden ihrem Typ entsprechend gerendert. Pflichtfelder verwenden native Eingabevalidierung und bleiben zusätzlich serverseitig maßgeblich. Kategorien sind als beschriftete Auswahl mit ihrer konfigurierten Farbe bedienbar.
- Sensible Felder sind deutlich gekennzeichnet und ausschließlich in den bereits managergeschützten API- und Webpfaden verfügbar. Der Terminpfad erhält ein eigenes, serverseitig aus der Vereinsberechtigung berechnetes `manage_metadata`-Kennzeichen; eine bloße Termin- oder Mannschaftsberechtigung reicht nicht aus.
- Deaktivierte historische Felder und Kategorien werden lesend gekennzeichnet und gesperrt. Beim Speichern sendet der Editor ausschließlich aktive Definitionen und aktive Kategorien, sodass deaktivierte historische Werte beziehungsweise Zuordnungen nicht versehentlich gelöscht oder neu geschrieben werden.
- Lade-, Fehler-, Wiederholungs-, Erfolgs- und Speicherzustände sind programmatisch ausgezeichnet. Der geöffnete Editor nimmt in Kartenansichten die volle verfügbare Breite ein; Schaltflächen, Felder, Auswahlgruppen und sensible Hinweise sind tastatur- und screenreadertauglich. Texte stehen mit vollständiger Schlüsselparität in DE/EN/FR/AR bereit.
- Alle **12 Frontend-Vertragsdateien** bestanden. Gezielte Metadaten-, Inventar-, Termin-, Mitgliedschafts- und Lokalisierungsregression: **28 Tests, 1.937 Assertions**. Isolierter Produktions-Build `/tmp/airmius-metadata-values-final-web-build` erfolgreich. Vollständige Backend-Regression dagegen: **1.272 bestanden, 4 übersprungen, 30.596 Assertions**; `git diff --check` ohne Befund.
- T015c1, T015c2 und damit T015c sind abgeschlossen. T015d, T015e und T015 bleiben offen. Keine produktive Migration, Veröffentlichung oder reale Browserabnahme wurde ausgeführt.

### 24.09.2026 – Native Konfiguration eigener Daten und Nummernkreise

- Berechtigte Vereinsverwaltung erreicht aus der nativen Vereinsorganisation eine eigene Ansicht mit drei Registerkarten. Felddefinitionen, Kategorien und Nummernkreise können angelegt, bearbeitet und nach Bestätigung gelöscht werden; Standardnummernkreise lassen sich setzen und aufheben.
- Die Dialoge bilden sämtliche Zielbereiche und Feldtypen, Auswahllisten, Pflicht-, Sensibilitäts-, Aktiv- und Sortierkennzeichen sowie Präfix, Suffix, Stellenzahl, Startwert und Rücksetzungsregel ab. Nummernkreisvorschau, nächster Wert und Zahl der Vergaben bleiben sichtbar. Serverfehler zu verwendeten oder gesperrten Strukturen werden als sichtbare API-Fehler weitergegeben.
- Alle sichtbaren Texte stehen in DE/EN/FR/AR bereit. Die Ansicht besitzt Lade-, Fehler-, Wiederholungs- und Schreibzustände und verwendet die vorhandenen nativen Material-Bedienelemente mit programmatischen Beschriftungen.
- Metadaten-Schreibvorgänge werden im gemeinsamen Mobile-Transport weder offline gespeichert noch nach einem unklaren Transportfehler automatisch wiederholt. Der API-Vertrag deckt Konfiguration und Fachwerte-Endpunkte ab; ein eigener Test beweist Einmalversand und leere Offline-Warteschlange.
- Fokussierte Metadaten-/Organisationsprüfung: **8 Tests bestanden**. Vollständige Flutter-Regression: **410 Tests bestanden**. Statische Flutter-Analyse ohne Befund; Dart-Formatierung und `git diff --check` ohne Befund.
- T015d1 ist abgeschlossen. T015d2 und damit T015d bleiben offen, bis die native Wertepflege an Mitgliedern, Mannschaften, Terminen und Inventar vollständig eingebunden und geprüft ist. T015e und T015 bleiben ebenfalls offen. Keine produktive Migration, Veröffentlichung oder Realgeräteabnahme wurde ausgeführt.

### 24.09.2026 – Native Wertepflege an Vereinsfachobjekten

- Ein gemeinsamer nativer Werte-Editor ist für interne und externe Mitglieder, Mannschaften, Vereinstermine und Inventargegenstände eingebunden. Die Schaltfläche erscheint nur bei einer vom Server ausgewiesenen Vereinsverwaltungsberechtigung; Team- und Terminverwaltung allein schaltet sensible Vereinsmetadaten nicht frei.
- Text, Langtext, Zahl, Datum, Ja/Nein und feste Auswahl werden typgerecht dargestellt. Pflichtwerte werden vor dem Versand geprüft, sensible Felder sichtbar gekennzeichnet und Kategorien gemeinsam mit den Feldwerten gespeichert. Die API bleibt für Typvalidierung, Vereinsgrenzen und atomare Speicherung maßgeblich.
- Inaktive historische Felder und Kategorien bleiben sichtbar und schreibgeschützt. Beim Speichern werden nur aktive Definitionen und aktive Kategoriezuordnungen versendet, wodurch historische Werte und Zuordnungen erhalten bleiben.
- Wertepflege-Texte besitzen Schlüsselparität in DE/EN/FR/AR. Lade-, Fehler-, Wiederholungs-, Speicher- und Erfolgszustände sind vorhanden. Metadaten-Schreibvorgänge verwenden weiterhin den in T015d1 geprüften Einmalversand ohne Offline-Warteschlange.
- Fokussierte native Metadaten- und Fachoberflächenprüfung: **11 Tests bestanden**; gezielte Backend-Berechtigungs-/Werteprüfung: **22 Tests, 291 Assertions**. Vollständige Flutter-Regression: **413 Tests bestanden**; statische Analyse ohne Befund. Der vollständige direkte Backend-Lauf erreichte **1.271 bestandene, 4 übersprungene Tests und 30.601 Assertions**; sein einziger Fehler kam aus veralteten Assets in `public/build`. Ein danach erzeugter isolierter Produktions-Build `/tmp/airmius-t015e-web-build` hält die Locale-Grenze ein, und der zuvor fehlgeschlagene Vertrag bestand dort mit **5 Tests, 650 Assertions**. Damit sind alle ausgeführten Backend-Tests in aktueller Build-Kombination grün.
- T015d1, T015d2 und damit T015d sind abgeschlossen. T015e und T015 bleiben offen, bis Bestandsmigration, echter MySQL-Mehrverbindungsnachweis, Browser-/Realgeräteabnahme und kontrollierte Einführung erfolgt sind. Keine produktive Migration, Veröffentlichung oder Realgeräteabnahme wurde ausgeführt.

### 24.09.2026 – Strikter Einführungsvertrag für eigene Daten und Nummernkreise

- Der Read-only-Audit verwendet jetzt den versionierten Vertrag `club-number-range-rollout.v1`. Repositoryartefakte und die neue Evidenzvorlage werden automatisch auf Vollständigkeit geprüft; ohne Laufzeitdaten und geprüfte Evidenz bleibt die Entscheidung ausdrücklich `no-go`.
- Die Evidenzvorlage verlangt bestätigte Sicherung, Migrationsprobe und Rollbackprobe, einen echten MySQL-Konkurrenznachweis, Web Mobile, Web Desktop, Android und iOS, die vier Kernwege Konfiguration, Wertepflege, Standardvergabe und Legacy-Fallback sowie getrennte Produkt- und Engineering-Freigaben.
- Evidenzreferenzen dürfen ausschließlich kurze, nicht sensible Schlüssel sein. Pfade, URLs und ähnliche direkte Referenzen werden nicht als bestandene Freigabe akzeptiert. Der strikte Audit kann nur mit `--with-data`, vollständiger Evidenz und allen automatisierten Prüfungen `decision=go` erreichen.
- Das Runbook dokumentiert Reihenfolge und Abbruchkriterien einschließlich Duplikaten, Kollisionen, falschen Referenzen, Teilzuständen, Überschreiben bestehender Nummern sowie unerlaubtem Retry oder Offline-Speichern. Es nimmt keine Bestandsänderung vor.
- Readiness-Vertrag: **4 Tests, 20 Assertions**. Repository-Audit und Evidenzvorlage grün; lokale Evidenz und Laufzeitprüfung erwartungsgemäß ausstehend. `git diff --check` ohne Befund.
- T015e1 ist abgeschlossen. T015e2, T015e und T015 bleiben offen, weil die konfigurierte lokale MySQL-Datenbank nicht erreichbar ist und reale Browser-/Android-/iOS-Nachweise sowie Produkt-/Engineering-Freigaben nicht vorliegen. Keine produktive Migration oder Veröffentlichung wurde ausgeführt.

### 24.09.2026 – Sicherer MySQL-Mehrprozessprüfer für Nummernkreise

- Der neue Befehl `airmius:verify-club-number-range-concurrency` erzeugt auf einer ausdrücklich bestätigten isolierten MySQL-Kopie einen temporären Mitgliedsnummernkreis und startet zwei getrennte PHP-Prozesse mit getrennten Datenbankverbindungen. Er akzeptiert den Nachweis nur bei zwei erfolgreichen Workern, lückenlosen unterschiedlichen Sequenzen und einem exakt um zwei erhöhten Zählerstand.
- Der Prüfer verweigert die Ausführung ohne `--confirm-isolated`, in Produktion und bei einem anderen Datenbanktreiber. Der interne Worker ist in der Befehlsliste verborgen, verlangt ein kurzlebiges UUID-Token und verweigert ebenfalls Produktion und Nicht-MySQL-Verbindungen.
- Vergaben, zugehörige Audit-Einträge und der temporäre Nummernkreis werden auch nach einem Teilfehler erneut aus der Datenbank ermittelt und transaktional entfernt. Der maschinenlesbare Bericht enthält ausschließlich den Vertrag `club-number-range-mysql-concurrency.v1` und Prüfergebnisse, keine Vereins-, Nummernkreis-, Vergabe- oder Nummernwerte.
- Das Rollout-Runbook enthält den genauen Aufruf und die zulässigen Erfolgskriterien. Fokussierte Nummernkreisregression: **14 Tests, 102 Assertions**; PHP-Syntax, Formatierung, Befehlsregistrierung und `git diff --check` ohne Befund.
- Der erneute lokale Read-only-Audit bleibt korrekt auf `no-go`: Repositoryvertrag und Evidenzvorlage sind grün, `database.runtime` schlägt wegen der nicht erreichbaren konfigurierten Datenbank fehl und lokale Abnahmebelege fehlen. T015e2a ist abgeschlossen; T015e2b, T015e2, T015e und T015 bleiben offen. Keine produktive Migration, Veröffentlichung oder reale Geräteabnahme wurde ausgeführt.

### 24.09.2026 – Versionierte Satzungen, Ordnungen und Beitragsmodelle in Backend, Web und nativer App

- Die additive Tabelle `club_policy_documents` verwaltet Satzungen, Ordnungen und formale Beitragsmodelle als getrennte Fassungen mit Titel, Versionsbezeichnung, Beginn, optionalem Ende, interner oder öffentlicher Sichtbarkeit und optionalen Hinweisen. Derselbe Dokumenttitel darf innerhalb einer Art keine zeitlich überlappenden Fassungen besitzen.
- Jede Fassung verweist auf eine vorhandene Vereinsdatei und verwendet damit die bestehende geprüfte Uploadstrecke. Fremde, Team- oder Termindateien werden abgewiesen. Referenzierte Dateien besitzen Löschschutz; das Löschen einer Fassung erhält die wiederverwendbare Datei.
- Mitglieder sehen interne und öffentliche Fassungen. Andere angemeldete Personen sehen bei gelisteten Vereinen ausschließlich ausdrücklich öffentliche Fassungen. Download, Schreiben und verschachtelte Ressourcen prüfen Verein und Sichtbarkeit erneut. Auditdaten enthalten keine Titel, Versionen, Zeiträume, Notizen oder Dateinamen.
- Das Vereinsprofil zeigt die drei Dokumentarten mit Status, Zeitraum, Sichtbarkeit, Download und Hinweisen. Berechtigte Vereinsverwaltung kann vorhandene Dateien auswählen, die sichere Vereinsdatei-Uploadstrecke verwenden sowie Fassungen anlegen, ändern und nach Bestätigung löschen. Texte und programmatische Beschriftungen liegen in DE/EN/FR/AR vor.
- Backend- und angrenzende Beitrags-/Governance-/Periodenregression: **20 Tests, 156 Assertions**. Vollständige Dateimanager-/Upload-Sicherheitsregression: **17 Tests, 242 Assertions**. Drei Web-Komponenten-/Lokalisierungsverträge bestanden. Isolierter Produktions-Build `/tmp/airmius-policy-documents-web-build` erfolgreich; produktive Assets unverändert. PHP-Syntax, Routen, Pint und `git diff --check` ohne Befund.
- Die native App stellt Satzungen, Ordnungen und Beitragsmodelle gruppiert mit serverseitigem Status, Gültigkeit, Sichtbarkeit, Hinweisen und geschütztem Dateizugriff dar. Vereinsmitglieder erreichen die Ansicht über die Vereinsorganisation; bestehende Betriebs- und Freigabewege bleiben nutzbar. Berechtigte Vereinsverwaltung wählt ausschließlich vorhandene Vereinsdateien aus und kann Fassungen anlegen, ändern und nach Bestätigung löschen. Schreibaufrufe werden weder offline vorgemerkt noch automatisch wiederholt. Sämtliche Texte und Feldbeschriftungen liegen in DE/EN/FR/AR vor.
- Fokussierte native Organisations-/Dokumenten-/Governance-/Zeitraumregression: **16 Tests bestanden**. Vollständige Flutter-Regression: **417 Tests bestanden**. `flutter analyze` ohne Befund; Dart-Formatierung und `git diff --check` ohne Befund.
- T016a, T016b und T016c sind abgeschlossen. T016d und T016e sowie damit T016 bleiben offen. Operative Beitragsregeln wurden nicht rückwirkend verändert; keine produktive Migration oder Veröffentlichung wurde ausgeführt.

### 24.09.2026 – Beschlussfeste Verknüpfung von Beitragsregeln und Beitragsmodellfassungen

- Beitragsregeln besitzen eine additive, optionale Fremdschlüsselverknüpfung zu einer formalen Beitragsmodellfassung. Es erfolgt keine automatische oder rückwirkende Zuordnung bestehender Regeln. Neue und geänderte Verknüpfungen akzeptieren ausschließlich Beitragsmodellfassungen desselben Vereins, deren Gültigkeitszeitraum die Regel vollständig abdeckt.
- Verknüpfte Fassungen können weder gelöscht, in einen anderen Dokumenttyp umgewandelt noch so zeitlich verkürzt werden, dass eine historische Regel ihre Beschlussgrundlage verliert. Beitragsberechnung, Rechnungslogik und Regeln ohne formale Zuordnung bleiben unverändert.
- Die Web-Verwaltung bietet die verfügbaren Fassungen im Beitragsregelformular an und zeigt die gespeicherte Beschlussgrundlage auch bei historischen Regeln. Beschriftungen und Leerzustand liegen in DE/EN/FR/AR vor.
- Backend-/API-/Beitrags-/Antragsregression: **18 Tests, 179 Assertions**; nach ergänztem Änderungs-Löschschutz fokussiert **13 Tests, 104 Assertions**. Zwei Web-Verträge bestanden. Isolierter Produktions-Build `/tmp/airmius-contribution-policy-link-web-build` erfolgreich; produktive Assets unverändert. Pint und `git diff --check` ohne Befund.
- Die native Beitragsregelverwaltung übernimmt die verfügbaren formalen Fassungen aus dem geschützten Management-Payload. Sie kann eine Fassung optional auswählen, sendet deren ID über den bestehenden nicht wiederholten Schreibpfad und zeigt die gespeicherte Beschlussgrundlage bei historischen Regeln. Texte und programmatische Beschriftungen liegen in DE/EN/FR/AR vor.
- Fokussierter nativer Modell-/Lokalisierungs-/Rendernachweis: **7 Tests bestanden**. Vollständige Flutter-Regression: **420 Tests bestanden**. `flutter analyze`, Dart-Formatierung und `git diff --check` ohne Befund.
- T016d1, T016d2, T016d3 und damit T016d sind abgeschlossen. T016e und damit T016 bleiben offen. Keine Bestandsmigration, produktive Migration oder Veröffentlichung wurde ausgeführt.

### 24.09.2026 – Freigabevertrag für Vereinsdokumente und Beitragsmodellfassungen

- Der neue Befehl `airmius:audit-club-policy-documents` prüft Repositoryvertrag und Evidenzvorlage sowie optional ausschließlich aggregierte Laufzeitdaten. Er verändert keine Zeilen und gibt weder Vereins-, Dokument-, Datei- noch Beitragsregelinhalte oder Kennungen aus.
- Der Laufzeitaudit erkennt fehlende Tabellen und Spalten, vereinsfremde oder fachlich unzulässige Datei- und Regelverknüpfungen, nicht deckende Beitragszeiträume sowie überlappende Fassungen. Bestehende Regeln ohne formale Zuordnung werden gezählt und ausdrücklich nicht automatisch umgedeutet.
- Der versionierte Vertrag `club-policy-document-rollout.v1` verlangt Sicherungs-, Trockenlauf- und Rollbacknachweis, Web mobil, Web Desktop, Android und iOS, sechs fachliche Kernabläufe sowie Produkt- und Engineering-Freigabe. Der strikte Modus liefert erst danach `go` und Exitcode `0`.
- Fokussierter Readiness-Nachweis: **3 Tests, 14 Assertions**. Repositoryprüfung bestanden. Der lokale strikte Read-only-Lauf bleibt korrekt `no-go`, weil die konfigurierte Laufzeitdatenbank nicht erreichbar ist und geprüfte Browser-/Realgeräte-Evidenz fehlt.
- T016e1 ist abgeschlossen. T016e2, T016e und damit T016 bleiben offen. Keine Bestandsmigration, reale Geräteabnahme, produktive Migration oder Veröffentlichung wurde ausgeführt.

### 24.09.2026 – Vorstand, Ausschüsse, Arbeitsgruppen und Verantwortlichkeiten

- Additive Tabellen für Gremien und personengebundene Verantwortlichkeiten ergänzt. Vorstand, Ausschuss und Arbeitsgruppe besitzen Beschreibung, eigene Amtszeit und öffentliche beziehungsweise interne Sichtbarkeit. Verantwortlichkeiten enthalten Funktion, Freitext, eigene Amtszeit und genau einen Verweis auf ein internes oder extern geführtes Vereinsmitglied; bestehende Rollen und Mitgliederdaten bleiben unverändert.
- Vereinsverwaltung und Mitglieder sehen interne sowie öffentliche Inhalte. Andere angemeldete Personen erhalten bei gelisteten Vereinen nur ausdrücklich öffentliche Gremien und darin nur öffentliche Verantwortlichkeiten. Schreiben darf ausschließlich die bestehende Vereinsverwaltung mit `update`-Berechtigung; fremde Personen- und Gremienreferenzen werden abgewiesen.
- Verwendete Gremien besitzen Löschschutz. Alle Schreibaktionen werden ohne Namen, Beschreibungen oder Verantwortungsfreitexte als `club.governance.*` protokolliert. Fokussierter Backend-Nachweis: **4 Tests, 39 Assertions**; gemeinsame Governance-/Organisations-/Rollen-/Lokalisierungsregression: **12 Tests, 1.057 Assertions**.
- Das Vereinsprofil zeigt die serverseitig gefilterten Gremien und bietet berechtigten Personen vollständige Web-Verwaltung. DE/EN/FR/AR liegen in einem seitenspezifischen Katalog. Funktionsbezogene Web-Tests bestanden; Bundle-, Lokalisierungs- und Accessibility-Verträge: **30 Tests, 1.008 Assertions**. Isolierter Produktions-Build `/tmp/airmius-governance-web-build` erfolgreich; produktive Assets unverändert.
- Die native Ansicht ist aus der Vereinsorganisation erreichbar und unterstützt dieselben Lese- und Schreibabläufe. Schreibaktionen werden weder offline gespeichert noch nach unklarem Transportfehler automatisch wiederholt. Gezielte Governance-/Organisationsprüfung: **8 Tests bestanden**; vollständige Flutter-Regression: **402 Tests bestanden** (`/tmp/airmius-governance-mobile-full.log`), statische Analyse ohne Befund.
- Vollständige Backend-Regression gegen den finalen isolierten Web-Build: **1.231 bestanden, 4 übersprungen, 30.164 Assertions** (`/tmp/airmius-governance-backend-full-final.log`). Routenerkennung, PHP-/Dart-Formatierung und `git diff --check` ohne Befund. Detaildokumentation: `docs/CLUB_GOVERNANCE_STRUCTURE.md`.
- T013d bleibt offen: anonymisierte Bestandsmigrationsprobe, reale Browser-/Android-/iOS-Abnahme und kontrollierte Einführung. Keine produktive Migration oder Veröffentlichung ausgeführt; deshalb bleiben T013 und die fachliche Gesamtanforderung noch offen.

### 24.09.2026 – Native App für die mehrstufige Vereinsorganisation

- Der Struktur-Tab des Vereinsprofils öffnet eine eigene native Organisationsansicht. Sie zeigt Abteilungen, Standorte und Trainingsgruppen ausschließlich aus dem serverseitig gefilterten Endpunkt und steht damit Vereinsverwaltung, Mitgliedern sowie bei gelisteten Vereinen externen Personen mit jeweils passender Sichtbarkeit zur Verfügung.
- Berechtigte Vereinsverwaltung kann alle Einheitstypen in nativen Dialogen anlegen, bearbeiten und nach ausdrücklicher Bestätigung löschen. Pflichtname und zweistelliger Ländercode werden vor Versand geprüft; serverseitige Validierungs-, Mandanten- und Löschschutzfehler bleiben sichtbar.
- Bestehende Mannschaften lassen sich nativ Abteilung, Standort und Trainingsgruppe zuordnen. Bei einer Trainingsgruppe übernimmt die App deren Abteilung und Standort; der Server prüft die Konsistenz erneut. Alle Felder und Aktionen sind programmatisch benannt und in DE/EN/FR/AR übersetzt.
- Organisations-Schreibaktionen werden weder offline gespeichert noch automatisch wiederholt. API-Vertragstests prüfen GET/POST/PUT/DELETE-Pfade, unveränderte Payloads, Typbegrenzung, genau einen Übertragungsversuch und Ablehnung im Offline-Modus.
- Finaler fokussierter Nachweis: **4 Tests bestanden**, einschließlich gerenderter Manageransicht und Öffnen eines nativen Bearbeitungsdialogs (`/tmp/airmius-organization-mobile-ui-final.log`). Fokussierte Vereinsprofil-/Sprachregression davor: **22 bestanden**. Vollständige Flutter-Regression: **398 bestanden** (`/tmp/airmius-organization-mobile-full-final.log`); statische Analyse ohne Befund (`/tmp/airmius-organization-mobile-analyze-final2.log`). `git diff --check` ohne Befund.
- Keine produktive Migration oder Veröffentlichung. T012d mit anonymisierter Bestandsprobe, realer Browser-/Android-/iOS-Abnahme und kontrollierter Einführung bleibt offen; erst danach wird T012 insgesamt abgehakt.

### 24.09.2026 – Web-Verwaltung der mehrstufigen Vereinsorganisation

- Das Vereinsprofil lädt Abteilungen, Standorte und Trainingsgruppen aus dem serverseitig nach Rolle und Sichtbarkeit gefilterten Organisations-Endpunkt. Mitglieder sehen interne Vereinsstrukturen; Außenstehende bei gelisteten Vereinen ausschließlich öffentliche Einheiten. Interne Standortnotizen werden selbst bei einem öffentlichen Standort nicht nach außen übertragen.
- Vereinsverwaltung kann alle drei Einheitstypen direkt anlegen, bearbeiten und unter Beachtung des serverseitigen Löschschutzes entfernen. Validierungs- und Übertragungsfehler bleiben sichtbar; Schreibaktionen werden nicht automatisch wiederholt.
- Bestehende Mannschaften lassen sich Abteilung, Standort und Trainingsgruppe zuordnen. Eine gewählte Trainingsgruppe übernimmt ihre hinterlegte Abteilung und ihren Standort; der Server prüft Verein und Konsistenz erneut. Normale Mannschaftsupdates bewahren die Zuordnung. Sichtbare Zuordnungen erscheinen zusätzlich an den Mannschaften im Vereinsprofil, ohne Namen interner Einheiten nach außen offenzulegen.
- Sämtliche neuen Eingaben und Auswahlfelder besitzen programmatische Namen. Texte liegen vollständig in DE/EN/FR/AR im seitenspezifischen Modul `resources/js/i18n/clubOrganizationLocalization.json`; der Lokalisierungs-Audit meldet weiterhin keine offenen Kandidaten.
- Finale Web-Komponentenprüfungen: **9 bestanden**. Bundle-/Accessibility-Verträge gegen den isolierten finalen Build: **25 Tests, 961 Assertions**. Isolierter Produktions-Build `/tmp/airmius-organization-web-build-final2` erfolgreich; produktive Assets unverändert.
- Fokussierter Organisationsnachweis nach zusätzlichem Schutz interner Standortnotizen: **4 Tests, 44 Assertions**. Vollständige Backend-Regression: **1.227 bestanden, 4 übersprungen, 30.111 Assertions** (`/tmp/airmius-organization-web-backend-full.log`). JSON-Prüfung und `git diff --check` ohne Befund.
- Keine produktive Migration oder Veröffentlichung. Die native App ist inzwischen in T012c ergänzt; T012d mit Bestandsprobe, realer Browser-/Geräteabnahme und kontrollierter Einführung bleibt offen.

### 24.09.2026 – Backend-Grundlage für die mehrstufige Vereinsorganisation

- Additive Tabellen für Abteilungen, wiederverwendbare Vereinsstandorte und Trainingsgruppen eingeführt. Bestehende Mannschaften erhalten drei optionale Fremdschlüssel; vorhandene Mannschafts-, Mitglieder-, Termin- und Trainingsdaten bleiben unverändert.
- Jede Organisationseinheit gehört genau zu einem Verein. Abteilungs-, Standort- und Trainingsgruppenreferenzen anderer Vereine werden serverseitig abgewiesen. Eine Trainingsgruppe kann Abteilung und Standort vorgeben; fehlende Mannschaftsangaben werden daraus übernommen, widersprüchliche Zuordnungen werden nicht gespeichert.
- Vereinsverwaltung und Vereinsmitglieder sehen interne und öffentliche Einheiten. Andere angemeldete Personen erhalten bei gelisteten Vereinen ausschließlich ausdrücklich öffentliche Einträge. Schreiben darf nur die bestehende Vereinsverwaltung mit `update`-Berechtigung.
- Verwendete Abteilungen, Standorte und Trainingsgruppen besitzen einen Löschschutz. Normale bestehende Mannschaftsupdates ohne Organisationsfelder bewahren die gespeicherte Zuordnung. Anlegen, Ändern, Löschen und Umordnen werden ohne Beschreibungen, Adressen oder Namen im Audit protokolliert.
- Fokussierter Organisationsnachweis: **4 Tests, 42 Assertions**. Bestehende Mannschafts-, Rollen- und Lokalisierungsregression: **21 Tests, 1.550 Assertions**. Vollständige Backend-Regression gegen den unveränderten isolierten Web-Build: **1.227 bestanden, 4 übersprungen, 30.107 Assertions** (`/tmp/airmius-organization-backend-full.log`). PHP-Formatprüfung, Routenerkennung und `git diff --check` ohne Befund. Detaildokumentation: `docs/CLUB_ORGANIZATION_STRUCTURE.md`.
- T012d bleibt offen: Bestandsmigrationsprobe, Browser-/Realgeräteabnahme und kontrollierte Einführung. Keine produktive Migration oder Veröffentlichung ausgeführt.

### 24.09.2026 – Vereinsauftritt, Briefpapier und Dokumentvorlagen

- Bestehendes Vereinslogo und Titelbild bleiben erhalten; die native App kann jetzt beide Bildarten über die vorhandene geschützte Uploadstrecke aktualisieren. Drei optionale Vereinsfarben werden als normalisierte Hexwerte gespeichert und in öffentlichen Web-/App-Profilen als Farbpalette gezeigt.
- Vereinsverwaltung kann Briefpapier mit optionalem Logo, Kopfzeile, Anschriftzeile und Fußzeile sowie bis zu 20 benannte Vorlagen für Brief, Rechnung, Beleg, Urkunde oder eigenen Typ einstellen. Pro Typ ist höchstens eine Standardvorlage zulässig; Web und App lösen konkurrierende Standardauswahlen bereits in der Oberfläche auf.
- Briefpapier und Vorlagen werden ausschließlich Personen mit bestehender Vereins-`update`-Berechtigung ausgeliefert. Normale Mitglieder und Gäste erhalten nur die öffentlichen Farben. Auditdaten enthalten handelnde Person und geänderte Feldnamen, aber keine Texte, Vorlageninhalte oder Speicherpfade.
- Additive Migration und bestehende Vereinsaktualisierung ändern keine früheren Bild-, Anschrift-, Kontakt-, Rechts- oder Bankdaten. Die Konfiguration ist für Dokumentabläufe verfügbar; jeder PDF-/Finanz-/Urkundenablauf muss seine fachlichen Pflichtangaben bei einer späteren Verwendung weiterhin selbst wahren. Detaildokumentation: `docs/CLUB_BRANDING_AND_DOCUMENT_TEMPLATES.md`.
- Fokussierter Backend-Nachweis zusammen mit Kontakt- und Rechtsstammdaten: **10 Tests, 76 Assertions**. Web-Komponententests: **8 bestanden**. Lokalisierungs-/Paketverträge gegen den isolierten Build: **23 Tests, 1.968 Assertions**; Lokalisierungs-Audit ohne offene Kandidaten. Isolierter Produktions-Build `/tmp/airmius-branding-web-build` erfolgreich.
- Fokussierte App-Regression: **19 bestanden**, Flutter-Analyse ohne Befund; vollständige Flutter-Regression: **394 bestanden** (`/tmp/airmius-branding-mobile-full.log`). Vollständige Backend-Regression gegen denselben isolierten Web-Build: **1.223 bestanden, 4 übersprungen, 30.047 Assertions** (`/tmp/airmius-branding-backend-full.log`). PHP-Formatprüfung, JSON-Prüfung und `git diff --check` ohne Befund.
- Keine produktive Migration oder Veröffentlichung. Browser-/Realgeräteabnahme, anonymisierte Bestandsmigrationsprobe, Kontrastprüfung und fachliche Freigabe jedes später angebundenen Dokumenttyps bleiben vor Einführung erforderlich.

### 24.09.2026 – Vereinskontakte und zuständige Ansprechpartner

- Die bestehenden Vereinsnamen-, Anschrift- und SEPA-Bankfelder werden um allgemeine E-Mail, Telefon, Website sowie bis zu 20 Ansprechpartner mit Funktion und eigenen Kontaktdaten ergänzt. Kontaktänderungen verändern Kontoinhaber, IBAN und BIC nicht.
- Öffentliche Sichtbarkeit ist standardmäßig deaktiviert. Bei Freigabe erscheinen allgemeine Kontaktdaten und ausschließlich einzeln freigegebene Ansprechpartner; interne Personen werden serverseitig aus normalem Vereinsobjekt und eingebettetem Profil entfernt.
- Änderungen werden mit handelnder Person und Feldnamen protokolliert, ohne Namen, E-Mail-Adressen oder Telefonnummern ins Audit zu kopieren. E-Mail, Telefon, HTTPS/HTTP-Website, Pflichtname und 20-Personen-Grenze werden serverseitig geprüft.
- Web und native App verwalten und zeigen die serverseitig gefilterten Kontakte in DE/EN/FR/AR. Die Web-Texte liegen in `resources/js/i18n/clubContactMasterDataLocalization.json`, damit die globalen Sprachpakete unverändert bleiben. Detaildokumentation: `docs/CLUB_CONTACT_MASTER_DATA.md`.
- Fokussierter Backend-Nachweis zusammen mit rechtlichen Stammdaten: **6 Tests, 53 Assertions**. Web-Komponententests: **7 bestanden**. Lokalisierungs-/Release-/Paketvertrag: **16 Tests, 766 Assertions**. Isolierter Produktions-Build `/tmp/airmius-contact-master-data-web-build` erfolgreich. Fokussierte mobile Regression: **17 bestanden**, Flutter-Analyse ohne Befund; vollständige Flutter-Regression: **392 bestanden** (`/tmp/airmius-contact-master-data-mobile-full.log`).
- Vollständige Backend-Regression gegen denselben isolierten Web-Build: **1219 bestanden, 4 übersprungen, 30024 Assertions** (`/tmp/airmius-contact-master-data-backend-full-final.log`). PHP-Formatprüfung, Lokalisierungs-Audit und `git diff --check` ohne Befund.
- Keine produktive Migration oder Veröffentlichung. Browser-/Realgeräteabnahme und anonymisierte Bestandsmigrationsprobe bleiben vor Einführung erforderlich.

### 24.09.2026 – Geschützte Register-, Verbands- und Steuerstammdaten

- Vereine können Registerbehörde/-nummer, bis zu 20 Verbandszugehörigkeiten mit Mitgliedsnummer und Gültigkeit sowie Steuerbehörde, Steuernummer, Umsatzsteuer-ID, Steuerstatus und Ablauf der Steuerbefreiung strukturiert speichern.
- Die Felder werden ausschließlich bei bestehender Vereins-`update`-Berechtigung an Web oder API ausgegeben. Normale Mitglieder erhalten sie weder im Vereinsobjekt noch im eingebetteten Profil und können sie nicht ändern.
- Änderungen werden mit handelnder Person und den Namen der geänderten Felder protokolliert; sensible Werte werden nicht in Auditdaten dupliziert. Leere Werte, Umsatzsteuer-ID, Feldlängen, Steuerstatus, Datumsreihenfolge und die Grenze von 20 Verbänden werden serverseitig normalisiert beziehungsweise geprüft.
- Web und native App enthalten strukturierte Eingaben in DE/EN/FR/AR. Die Web-Texte liegen in einem seitenspezifischen Sprachmodul, damit die globalen Startpakete nicht wachsen. Detaildokumentation: `docs/CLUB_LEGAL_MASTER_DATA.md`.
- Fokussierter Backend-Nachweis: **3 Tests, 25 Assertions**. Web-Komponententests: **6 bestanden**. Isolierter Produktions-Build `/tmp/airmius-legal-master-data-web-build` erfolgreich. Fokussierte mobile Tests: **15 bestanden**, Flutter-Analyse ohne Befund; vollständige Flutter-Regression: **390 bestanden** (`/tmp/airmius-legal-master-data-mobile-full.log`).
- Vollständige Backend-Regression gegen denselben isolierten Web-Build: **1216 bestanden, 4 übersprungen, 29996 Assertions** (`/tmp/airmius-legal-master-data-backend-full-final.log`). Ein einmaliger Faker-E-Mail-Konflikt im unveränderten Commerce-Test bestand separat mit **4 Tests, 65 Assertions**; der anschließende vollständige Lauf war grün. PHP-Formatprüfung, JSON-Prüfung und `git diff --check` ohne Befund.
- Keine produktive Migration oder Veröffentlichung. Browser-/Realgeräteabnahme, anonymisierte Bestandsmigrationsprobe und fachliche Prüfung der Register-/Steuerwerte bleiben vor Einführung erforderlich.

### 24.09.2026 – ISO-20022-Rückgabeimport

- Der bestehende datei-, personen-, lauf- und zustandsgebundene CSV-Vorschau-/Importablauf erkennt nun zusätzlich ISO-20022-XML anhand des Inhalts und Namensraums. Unterstützt werden abgewiesene `pain.002`-Transaktionen mit `TxSts=RJCT` sowie ausdrückliche Soll-Rückgaben mit `RtrInf` in `camt.053/054`.
- End-to-End-ID, EUR-Betrag, Einzugs-/Buchungsdatum, Bankreferenz, Rückgabegrund und optionale Schuldner-IBAN werden in dieselbe strikte Positionsprüfung überführt. XML-Beträge werden nur für die Prüfung als negative Rückgabe normalisiert. Gebühren, uneindeutige Sammelbuchungen und andere Transaktionen werden nicht automatisch übernommen.
- Der Parser akzeptiert ausschließlich passende ISO-20022-Dokumentwurzeln und denselben registrierten Namensraum für alle gelesenen Elemente. DTD-/Entity-Deklarationen, externe Ressourcen, fremde Namensräume, nicht abgewiesene `pain.002`-Status und Habenbuchungen werden abgewiesen. Maximal 2 MiB, 200 Rückgaben und 20000 XML-Elemente.
- Web und App wählen `.csv` und `.xml`, zeigen das erkannte Format und behalten Vorschau sowie beide Bestätigungen bei. Nur CSV bietet eine manuelle Spaltenzuordnung. Der originale Dateiname bleibt beim nativen Multipart-Upload erhalten; kein Offline-Queueing oder automatischer Schreib-Retry.
- Finale fokussierte Zahlungs-/SEPA-Regression: **105 bestanden, 1385 Assertions** (`/tmp/airmius-sepa-xml-backend-focused-final.log`). Davon prüfen drei XML-Verhaltenstests `pain.002`, `camt.053`, `camt.054`, Inhalts-/Namensraumschutz und Import mit **50 Assertions** (`/tmp/airmius-sepa-xml-focused-hardened.log`).
- Web-Komponenten-/Zustandstests: **5 bestanden** (`/tmp/airmius-sepa-xml-node-final.log`). Isolierter Web-Build `/tmp/airmius-sepa-xml-web-build` erfolgreich; produktive Assets blieben unverändert. Fokussierte App-Importsuite: **8 bestanden** (`/tmp/airmius-sepa-xml-mobile-focused-final.log`). Vollständige Flutter-Regression: **388 bestanden** (`/tmp/airmius-sepa-xml-mobile-full.log`); statische Analyse ohne Befund (`/tmp/airmius-sepa-xml-mobile-analyze.log`).
- Vollständige Backend-Regression gegen denselben isolierten Web-Build: **1213 bestanden, 4 übersprungen, 29971 Assertions** (`/tmp/airmius-sepa-xml-backend-full-final.log`). PHP-Formatprüfung und `git diff --check` ohne Befund.
- Reale Bankdateien verschiedener Institute, bankspezifische Erweiterungen/Sammelbuchungen, Browser-/Gerätebedienung, MySQL-Konkurrenz und produktive Einführung bleiben offen. Keine produktive Datei importiert und keine Buchung, Migration oder Veröffentlichung ausgeführt.

### 24.09.2026 – Gutschrift und Erstattungsnachweis für Gebührenrechnungen

- Neue additive Migration `2026_09_24_000010_create_sepa_fee_recharge_credits.php` für Gutschriftantrag, aktive Reservierung, Vier-Augen-Prüfung, eindeutige Gutschriftnummer, eingefrorenen Erstattungsbedarf und Erstattungs-Bankbeleg. Keine produktive Migration ausgeführt.
- Der Gutschriftweg ist ausschließlich für freigegebene Gebührenrechnungen mit Zahlungsdatensatz, Bankumsatz, direktem SEPA-Export oder aktivem Lastschriftlauf vorgesehen. Ohne solche Vorgänge bleibt der einfachere Vier-Augen-Storno maßgeblich. Ein aktiver Storno- und Gutschriftantrag kann nicht gleichzeitig bestehen.
- Freigabe durch eine andere Finanzverwaltungsperson prüft Verein, Rückgabe, Rechnung, Empfänger und Betrag erneut. Sie erhält alle Zahlungs-/Bankbelege, storniert die Forderung kontrolliert, vergibt `AIR-GS-FEE-{Verein}-{Antrag}` und friert den aus tatsächlich bezahlten Zahlungszeilen ermittelten Erstattungsbedarf ein. Zahlungsevidenz ist danach gegen allgemeine Bearbeitung geschützt.
- Ein gesonderter PDF-Abruf enthält Verein, Empfänger, Ursprungsrechnung, Betrag, Begründung und Erstattungsstatus. Der Erstattungsendpunkt löst keine Überweisung aus: Er dokumentiert nach tatsächlicher Auszahlung Datum und eindeutige Bankreferenz, erstellt eine Bankausgabe oder verknüpft eine exakt passende vorhandene Ausgabe. Auch diese Ausgabe wird anschließend gegen allgemeine Änderung geschützt.
- Web und native App zeigen Antragshistorie, Gutschriftnummer, offenen Erstattungsbedarf und Banknachweis. Antrag, fremde Freigabe, Rücknahme und bestätigte Erstattungsdokumentation sind in beiden Oberflächen verfügbar; vorhandene Bankausgaben können optional über ihre ID verknüpft werden. Unklare Schreibantworten frieren UUID, Ziel-IDs und Payload für eine ausdrücklich bestätigte identische Wiederholung ein.
- Der Web-PDF-Abruf bleibt authentifiziert. Die App fordert nach erfolgreicher Finanzberechtigungsprüfung einen fünf Minuten gültigen signierten HTTPS-Link an und öffnet diesen extern; ungültige Schemata, eingebettete Zugangsdaten und manipulierte Signaturen werden abgewiesen. Der Link enthält keine API-Zugangsdaten.
- UUID-Wiederholungsschutz, abweichende Wiederholungen, Rücknahmehistorie, fehlende Vorgänge, Selbstfreigabe, Zahlungen ohne/mit Erstattungsbedarf, vorhandene Bankausgabe, Rechte-/Schemaausblendung und vollständiger Rollback bei Auditfehler sind abgedeckt. **102 fokussierte Tests bestanden, 1324 Assertions** (`/tmp/airmius-recharge-credit-focused-final.log`). PHP-Formatprüfung bestanden.
- Finaler isolierter Web-Build `/tmp/airmius-recharge-credit-ui-final-web-build` erfolgreich; Kern-Sprachchunks bleiben unter dem Grenzwert, Arabisch 308751 Byte. Zustands-/Renderingtests bestanden; finale gezielte Web-/Backend-/Lokalisierungsregression: **116 bestanden, 2992 Assertions** (`/tmp/airmius-recharge-credit-ui-final-regression.log`).
- Native fokussierte Regression: **26 Tests bestanden** (`/tmp/airmius-recharge-credit-mobile-focused.log`). Vollständige Flutter-Regression: **387 Tests bestanden** (`/tmp/airmius-recharge-credit-mobile-full.log`); `flutter analyze` ohne Befund (`/tmp/airmius-recharge-credit-mobile-analyze.log`). Produktive Assets blieben unverändert. Reale Browser-/Android-/iOS-, MySQL-, Bank-/Buchhaltungsabnahme und rechtlich-fachliche PDF-Prüfung bleiben offen.
- Vollständige Backend-Regression gegen denselben finalen isolierten Web-Build: **1210 bestanden, 4 übersprungen, 29921 Assertions** (`/tmp/airmius-recharge-credit-full-php-ui-final.log`). Keine produktive Migration, Buchung, Banküberweisung oder Veröffentlichung ausgeführt.

### 24.09.2026 – Gebührenweiterbelastung in der nativen App

- Die SEPA-Laufansicht liest die drei getrennten Serverfreigaben für Entwurf, Freigabe und Storno. Gespeicherte Vorschläge, Empfänger, Betrag, Fälligkeit, Grundlage, Begründung, Rechnung, Kontierung, Prüfhinweis und Stornohistorie bleiben auch für lesende Finanzrollen sichtbar; Aktionsfelder erscheinen nur der Finanzverwaltung.
- Die App unterstützt Vorschlag, begründete Rücknahme, Vier-Augen-Freigabe mit eigener Grundlagenbestätigung und Buchhaltungskonto sowie Stornoantrag, fremde Stornofreigabe und Rücknahme des Antrags. Eigene Vorschläge und eigene Stornoanträge können in der Oberfläche nicht selbst freigegeben werden; eine unbekannte Benutzer-ID wird nicht als zweite Person ausgelegt.
- Vorschlag und Stornoantrag erhalten eine UUID. Nach einem unklaren Transportfehler werden Aktion, IDs und Payload eingefroren. Die erneute Übermittlung verlangt eine neue Bestätigung und verwendet exakt dieselben Daten; alternativ lädt die App den aktuellen Serverstand. SEPA-Schreibaktionen gelangen weiterhin nicht in die Offline-Warteschlange.
- **22 fokussierte Widget-/Integrationstests bestanden**: Validierung und Gebührenrevision, explizite Bestätigungen, unveränderte Wiederholung nach Antwortverlust, Vier-Augen-Sperren, Storno, schreibgeschützte arabische Ansicht, servergesteuerte Navigation und Regression der bisherigen SEPA-Aktionen.
- Vollständige Flutter-Regression: **383 Tests bestanden** (`/tmp/airmius-fee-recharge-full-flutter.log`). `flutter analyze` ohne Befund; `git diff --check` ohne Befund. Keine produktive Migration, Forderung, Bankaktion oder Veröffentlichung. Reale Android-/iOS-Geräteabnahme, vollständige RTL-/Bedienungsprüfung und fachliche Freigabe bleiben offen.

### 24.09.2026 – Gebührenweiterbelastung im Web

- Die Laufübersicht zeigt Entwürfe, Empfänger, ursprüngliche Rechnung, eingefrorenen Gebührenstand, Grundlage, Begründung, gesonderte Rechnung, Kontierung, Stornohistorie und den Prüfhinweis nach einer späteren Gebührenkorrektur. Lesende Finanzrollen sehen den Vorgang ohne Aktionsfelder.
- Finanzverwaltung kann Vorschläge erstellen und zurücknehmen, eine fremde Vorlage nach erneuter Grundlagenbestätigung freigeben sowie Storno beantragen, durch eine zweite Person freigeben oder zurücknehmen. Selbstfreigaben und Aktionen ohne serverseitige Schemafreigabe werden nicht angeboten. Vor der Bestätigung zeigt das Formular die maßgeblichen gespeicherten Angaben erneut.
- Vorschlag und Stornoantrag erhalten je eine UUID. Nach einem unklaren Transportfehler bleiben Pfad und Payload eingefroren; es gibt keinen automatischen Wiederholungsversuch. Änderungen widerrufen die Bestätigungen. Neuladen holt zuerst den aktuellen Serverstand.
- **5 Zustandsprüfungen bestanden** (`/tmp/airmius-recharge-web-state-final.log`): eingefrorene Vorschlagswiederholung, separate Grundlagenbestätigung und explizites Konto, alle Rücknahme-/Stornoendpunkte, Eingabeprüfung/Bestätigungswiderruf und Sperre paralleler Übermittlungen.
- **4 gerenderte Komponentenprüfungen bestanden** (`/tmp/airmius-recharge-web-render-final.log`): lesende Ansicht, Selbstfreigabe-/Schemaausblendung, Prüfhinweis/Stornohistorie und vollständige DE/EN/FR/AR-Schlüssel. Freitexte werden maskiert ausgegeben.
- Backend-/API-/Lokalisierungs-/Frontendmanifest-Auswahl mit dem isolierten Build: **78 bestanden, 2657 Assertions** (`/tmp/airmius-recharge-web-final-api-tests.log`). Der Empfänger wird im API-Payload auf ID und Name begrenzt; die Rechnung liefert nur die für die Prüfung nötigen Felder.
- Abschließender isolierter Web-Build `/tmp/airmius-recharge-web-verified-build` erfolgreich. Produktive Assets unverändert; `git diff --check` ohne Befund. Keine produktive Migration, Forderung, Bankaktion oder Veröffentlichung. Manuelle Browser-/Tastatur-/RTL-Abnahme bleibt offen.

### 23.09.2026 – Kontrollierter Storno unbezahlter Gebührenrechnungen

- Neue additive Tabelle und drei geschützte API-Aktionen für Stornoantrag, Vier-Augen-Freigabe und Rücknahme. UUID-Wiederholungsschutz und höchstens ein aktiver Antrag pro Weiterbelastung. Keine produktive Migration ausgeführt.
- Antragstellung verändert keine Forderung. Freigabe prüft Rechnung/Quelle/Empfänger/Betrag, Zahlungsdatensätze, Bankzuordnungen und Lastschriftstatus erneut. Zwischenzeitlicher Zahlungseingang verhindert den Storno. Rücknahme bleibt dann möglich und erhält die Historie.
- Erfolgreicher Storno erhält Rechnung und Nummer, setzt den Status auf storniert und gibt die Weiterbelastungsreservierung frei. Allgemeine Wiederöffnung sowie Zahlung auf die stornierte Rechnung werden verhindert. Keine Auszahlung, Bankaktion oder Benachrichtigung. Bezahlt-/Bankfälle und gesonderte Dokumente bleiben offene Schritte.
- Gezielte Backend-Regression: **95 bestanden, 2425 Assertions** (`/tmp/airmius-recharge-void-tests.log`). Sechs neue Verhaltenstests prüfen zweite Person, idempotente Wiederholung, unveränderte Rechnung und ursprüngliche Forderung, neue Vorschläge nach Storno, Zahlung nach Antragstellung, direkte Exporte/Bankumsätze/aktive Läufe, Rücknahmehistorie, fehlende Rechte/Bestätigung/Migration und vollständigen Rollback bei Auditfehler.
- Vollständige Backend-Regression: **1205 bestanden, 4 übersprungen, 29779 Assertions** (`/tmp/airmius-recharge-void-full-php.log`), mit isoliertem Web-Build `/tmp/airmius-fee-correction-web-build`. Dieser Lauf startete vor der abschließenden Verschärfung des Rechnungsstatusschutzes.
- Abschließend schützt die Gebührenrechnung zusätzlich vor unpassendem Zwischenstatus und Wiederöffnung trotz vollständiger Zahlung. Die finale Zahlungs-/Rechnungs-/SEPA-Auswahl nach dieser Änderung bestand mit **82 Tests, 1155 Assertions** (`/tmp/airmius-recharge-void-final-focused.log`). `git diff --check` ohne Befund. Kein pauschaler neuer Gesamtstand behauptet; keine produktive Migration oder Veröffentlichung.
- Dokumentation: [CLUB_SEPA_FEE_RECHARGES.md](CLUB_SEPA_FEE_RECHARGES.md). Keine neue Web-/App-Oberfläche; deren Dialoge und reale Browser-/Geräte-/MySQL-Abnahme bleiben offen.

### 23.09.2026 – Freigabe und gesonderte Gebührenrechnung

- Neue additive Migration `2026_09_23_000008_approve_sepa_fee_recharges.php` und geschützter Freigabeendpunkt. Explizite Bestätigung von Aktion und geprüfter Grundlage, zweite berechtigte Person und frei festzulegendes separates Buchhaltungskonto erforderlich. Keine produktive Migration ausgeführt.
- Bei Erstfreigabe werden Entwurfsstatus/Reservierung, Gebührenrevision/-betrag, Rechnung, Vereins-/Mitgliedszuordnung und Fälligkeit unter Sperren erneut geprüft. Freigabe erstellt genau eine Rechnung im eigenen Nummernkreis und hält Konto/Person/Zeitpunkt fest. Identische Wiederholung liefert dieselbe Rechnung; Reservierung bleibt bestehen. Kein Versand und keine Zahlung werden ausgelöst.
- Allgemeine Rechnungsbearbeitung schützt wesentliche Rechnungsdaten, verhindert Löschen/Storno und unbelegte Bezahltmarkierung. Teil-/Restzahlungen laufen über den vorhandenen Zahlungsservice. CSV-Zahlungsexport verwendet die festgehaltene Kontierung und die Kennzeichnung Gebührenweiterbelastung; ursprüngliche Mitgliedsforderung bleibt unverändert.
- Spätere Gebührenkorrektur setzt `review_required` am freigegebenen Vorgang. Kein automatisches Umschreiben einer ausgestellten Rechnung. Kontrollierte Forderungskorrektur/Gutschrift/Erstattung sowie Darstellung und Auflösung dieses Prüfhinweises bleiben offen.
- Gezielte Zahlungs-/Rechnungs-/Mitglieder-/Cockpit-/Lokalisierungsregression: **88 bestanden, 2310 Assertions** (`/tmp/airmius-recharge-approval-tests.log`). Nach zusätzlichem Migrations-/Rechtetest und explizitem Nachweis unveränderter Zahlungs-/Benachrichtigungsanzahl: **5 Freigabetests bestanden, 84 Assertions** (`/tmp/airmius-recharge-approval-final-focused.log`).
- Abschließende vollständige Backend-Regression: **1199 bestanden, 4 übersprungen, 29672 Assertions**, Log `/tmp/airmius-recharge-approval-full-php.log`, mit `AIRMIUS_TEST_FRONTEND_BUILD_DIR=/tmp/airmius-fee-correction-web-build`. Keine fehlgeschlagenen Tests; `git diff --check` ohne Befund. Keine produktiven Assets ersetzt, keine Migration oder Veröffentlichung.
- Tests prüfen Selbstfreigabe, fehlende Bestätigung, ungeeignetes Konto, Wiederholung, geänderten Empfänger, abgelaufene Fälligkeit, korrigierte Gebühr, Modellschutz, Teilzahlungen, Exportkonto, Prüfhinweis, fehlende Migration/Rechte und vollständiges Zurückrollen von Rechnung/Freigabe bei Auditfehler. Web-/App-Oberflächen unverändert; deren Freigabedialoge bleiben offen.

### 23.09.2026 – Vorschläge zur Gebührenweiterbelastung

- Neue additive Tabelle `club_sepa_fee_recharges` und geschützte Vorschlags-/Rücknahmeendpunkte. Ein Entwurf hält Gebührenrevision/-betrag, vorgeschlagenen Teil-/Gesamtbetrag, ursprüngliche Rechnung, Mitglied, Fälligkeit, Grundlage und Begründung fest. Keine Rechnung, Zahlung oder Nachricht wird erzeugt.
- Höchstens ein aktiver Vorschlag pro Rückgabe, UUID-Inhaltsbindung, Vereins-/Positionsprüfung, aktueller Gebührenhöchstbetrag und Gebührenrevision geprüft. Rücknahme erhält Historie und gibt die Reservierung frei. Eine identische Wiederholung bleibt auch nach Rücknahme oder Ablauf der vorgeschlagenen Fälligkeit wirkungslos.
- Gezielte Zahlungs-/Mitglieder-/Cockpit-/Lokalisierungs-/Audit-Regression: **81 bestanden, 2149 Assertions** (`/tmp/airmius-recharge-tests.log`). Danach ergänzte Prüfung vor Migration und erneute Prüfung aller neuen Abläufe: **6 bestanden, 86 Assertions** (`/tmp/airmius-recharge-final-focused.log`).
- Tests decken unveränderte Mitgliedsforderungen und Rechnungsanzahl, doppelte Vorschläge, Rücknahme/erneute Vorbereitung, spätere Gebührenkorrektur ohne Snapshotänderung, fehlende Rechte/Bestätigung/Grundlage, fremden Verein und vollständiges Zurückrollen bei Auditfehler ab. Neue Endpunkte liefern vor Migration kontrolliert 503, während die bestehende Laufübersicht weiter funktioniert.
- Abschließende vollständige Backend-Regression: **1194 bestanden, 4 übersprungen, 29586 Assertions**, Log `/tmp/airmius-recharge-full-php.log`. Aufruf mit isolierten Web-Artefakten über `AIRMIUS_TEST_FRONTEND_BUILD_DIR=/tmp/airmius-fee-correction-web-build`. Keine fehlgeschlagenen Tests; übersprungene Tests bleiben kein Abnahmenachweis. `git diff --check` ohne Befund. Web-/App-Oberflächen unverändert, daher in diesem Schritt kein neuer Flutter- oder Web-Build-Lauf.
- Beschreibung: [CLUB_SEPA_FEE_RECHARGES.md](CLUB_SEPA_FEE_RECHARGES.md). Vier-Augen-Freigabe, Forderungserstellung und deren Buchhaltungs-/Korrekturabläufe sowie Web/App bleiben offen. Keine produktive Migration oder Buchung. Die Abnahme rechtlicher/fachlicher Voraussetzungen ist damit nicht erfolgt.

### 23.09.2026 – Native Gebührenkorrekturen

- App-Laufübersicht zeigt aktuellen Gesamtbetrag und Korrekturhistorie für lesende Finanzrollen. Ein Korrekturdialog erscheint nur für Finanzverwaltung, zurückgegebene Positionen mit gebuchter Gebühr und explizite Serverfreigabe `fee_corrections_available`.
- Neues Formular erfasst geänderten Gesamtbetrag einschließlich Storno auf null, Datum, eigene Referenz und Begründung. Feldänderungen widerrufen die Bestätigung. Anfrage enthält erwartete Revision und kryptographisch erzeugte UUID v4. Nach unklarem Fehler bleiben UUID/Inhalt unverändert und Felder gesperrt; erneute Übermittlung erfordert erneute ausdrückliche Bestätigung. Keine automatische Wiederholung oder Offline-Warteschlange.
- Nach Rückkehr aus dem Dialog wird die Laufübersicht auch bei Zurücknavigation neu geladen. Während laufender Anfrage ist Zurücknavigation gesperrt. Lesende Historie und Formulartexte sind in DE/EN/FR/AR vorhanden.
- **21 gezielte App-Tests bestanden** (`/tmp/airmius-correction-app-final-focused.log`): bisherige SEPA-/Gebührenabläufe, vier neue Korrekturtests und zwei neue Navigationstests. Nachweise umfassen Storno auf null, tatsächlichen API-Payload/UUID/Revision, identische manuelle Wiederholung nach Antwortverlust, Bestätigungswiderruf, ungültiges Datum ohne Versand, arabische lesende Historie und serverabhängige Aktionssichtbarkeit. Bestehender Transporttest prüft nun auch den neuen Korrekturendpunkt auf genau einen Versuch.
- API-Pfadprüfung: **3 bestanden, 7 Assertions** (`/tmp/airmius-correction-app-api-path.log`). Abschließende statische Flutter-Analyse **ohne Befund** (`/tmp/airmius-correction-app-final-analyze.log`). Die zwei anfänglichen Analysehinweise zu Blockklammern und einem privaten Testtyp sind behoben.
- Abschließender vollständiger Flutter-Testbestand: **375 bestanden**, Log `/tmp/airmius-correction-app-full.log`. `git diff --check` ohne Befund. Kein erneuter Backend-Gesamtlauf für diese reine App-Erweiterung.
- Keine Backend-/Web-Logikänderung in diesem Schritt; keine produktive Migration, Bankbuchung oder Veröffentlichung. Reale Geräte-/Browser-, Bank-/MySQL- und Buchhaltungsabnahmen bleiben offen. Gebührenweiterbelastungen sind weiterhin ein eigener offener Prozess.

### 23.09.2026 – Gebührenkorrekturen im Web

- Neue eingebundene Web-Komponente zeigt aktuellen Gesamtbetrag und nach Revision sortierte Historie einschließlich Datum, bisherigem/neuem Betrag, Referenz und Begründung. Lesende Rollen erhalten keine Buchungsaktionen. Korrekturformular erfordert Serverfreigabe `fee_corrections_available` und Finanzverwaltungsrechte.
- Explizit bestätigter neuer Gesamtbetrag, Korrekturdatum, Referenz und Begründung; null storniert vollständig. Änderungen widerrufen die Bestätigung. Jede Anfrage erhält eine UUID und die gelesene Revision. Nach Fehler bleibt derselbe Payload eingefroren; ein weiterer Versuch braucht eine neue Bestätigung und verwendet exakt denselben Schlüssel/Inhalt. Keine automatische Wiederholung. Aktualisieren lädt zuerst den Serverstand; erst ein frisch geladenes Positionsergebnis löst die Sperre.
- **4 Zustandsprüfungen bestanden** (`node tests/Frontend/feeCorrectionState.test.mjs`): unklarer Transport mit identischer manueller Wiederholung, Bestätigungswiderruf, Storno, gleichzeitige Klicks, veraltete Revision/neuer Serverstand sowie ungültige Beträge/Felder ohne Versand.
- **3 gerenderte Komponentenprüfungen bestanden** (`node tests/Frontend/feeCorrectionRender.test.mjs`): lesender Zugriff ohne Formular, Berechtigung/Schemafreigabe und vollständige Schlüssel/Labels in DE/EN/FR/AR; Begründungen werden als Text maskiert. Diese SSR-Prüfungen ersetzen keine echte Browser-/Tastatur-/RTL-Abnahme.
- Abschließende Backend-/Sprach-/Build-Regression `FrontendLocaleBundleContractTest|OrganizationLocalizationContractTest|ClubSepaSettlementTest`: **55 bestanden, 2288 Assertions**, mit `AIRMIUS_TEST_FRONTEND_BUILD_DIR=/tmp/airmius-fee-correction-web-build`, Log `/tmp/airmius-fee-correction-web-regression.log`. `git diff --check` ohne Befund. Kein erneuter Backend-Gesamtlauf, da ausschließlich Web-Komponenten, deren Zustandslogik, Tests und Dokumentation geändert wurden.
- Isolierter Web-Build erfolgreich unter `/tmp/airmius-fee-correction-web-build`; produktive Assets unverändert. Backendlogik und App in diesem Schritt nicht geändert. Native Korrekturansicht und reale Abnahmen bleiben offen; keine produktive Migration oder Veröffentlichung.

### 23.09.2026 – Gebührenkorrekturen: Backend und Export

- Neuer geschützter API-Ablauf für Gebührenkorrekturen und Storno auf null. Originalausgabe bleibt erhalten; separate Differenzausgabe und Historie speichern bisherigen/neuen Gesamtbetrag, Bearbeiter, Datum, Referenz und Begründung. Die additive Migration `2026_09_23_000006_create_sepa_fee_corrections.php` ist nicht produktiv ausgeführt.
- Club-/Lauf-/Positionssperren und erwartete Revision verhindern veraltete Änderungen; UUID und unveränderter Inhalt ermöglichen eine identische Wiederholung ohne weitere Buchung. Normale Finanzbearbeitung und erneute Gebührenverknüpfung können Korrekturdatensätze nicht überschreiben oder doppelt verwenden.
- Negative Differenzausgaben vermindern die Gebührenausgaben; sie erzeugen keine zusätzlichen Einnahmen. CSV verwendet für Verringerungen positiven Betrag mit `S`, für Erhöhungen `H`, jeweils im tatsächlichen Korrekturzeitraum. Mitgliedsforderung und unabhängige Teilzahlungen bleiben unverändert.
- Gezielte bestehende Zahlungs-/Mitglieder-/Cockpit-/Lokalisierungsregression einschließlich der ersten vier neuen Tests: **72 bestanden, 1995 Assertions** (`/tmp/airmius-fee-correction-tests.log`). Danach ergänzter Transaktionsfehlerfall und verschärfte Wiederholungsprüfung: **5 Korrekturtests bestanden, 81 Assertions** (`/tmp/airmius-fee-correction-final-focused.log`). Die anschließenden zusätzlichen Jahres-/Gesamtsaldenprüfungen für Web und API bestanden separat mit **1 Test, 25 Assertions** (`/tmp/airmius-fee-correction-balances.log`).
- Der Fehlerfall führt zunächst die Differenzbuchung aus und scheitert anschließend beim Speichern der Historie; danach existieren weder Differenzbuchung noch Korrektur/Audit. Weitere Tests prüfen unveränderte Originalausgabe, Storno und erneute Korrektur, Jahreswechsel, veraltete Revision, doppelte Referenz, fehlende Bestätigung/Rechte sowie gesperrte allgemeine Bearbeitung.
- Vollständige Backend-Regression: **1188 bestanden, 4 übersprungen, 29488 Assertions**, Log `/tmp/airmius-fee-correction-full-php.log`, mit isolierten Web-Artefakten aus `/tmp/airmius-fee-export-web-build`. Der Gesamtlauf startete vor den letzten zusätzlichen Saldenassertions; diese sind separat wie oben nachgewiesen. Keine fehlgeschlagenen Tests. `git diff --check` ohne Befund. Die PHP-Formatierung erfasste zusätzlich vorhandene Änderungen außerhalb des Gebührenmoduls; der vollständige Lauf prüft diesen resultierenden Stand.
- Bedienoberflächen für Korrekturen und aktuelle Gesamtbeträge bleiben offen (T007e3c2c2b). Keine Web-/App-Abnahme behauptet. Kein App-Code in diesem Schritt geändert. Echte MySQL-Konkurrenzprüfung und Buchhaltungsimport bleiben offen.

### 23.09.2026 – Gebührenexport und abschließende Regression

- Das optionale Gebührenkonto `datev_fee_account` ist in den Web-/App-Buchhaltungseinstellungen verfügbar. Es hat keinen voreingestellten Wert; ältere Clients ohne dieses Feld erhalten bestehende Einstellungen. Die additive Migration `2026_09_23_000005_add_sepa_fee_export_account.php` wurde nicht produktiv ausgeführt.
- Der bestehende CSV-Zahlungsexport enthält ausdrücklich gebuchte oder verknüpfte Bankgebühren genau einmal, anhand des tatsächlichen Ausgabedatums und Buchungsjahrs. Auch Zeiträume ausschließlich mit Gebühren werden unterstützt. Informatorische Rückgabegebühren und nicht verknüpfte allgemeine Ausgaben werden nicht aufgenommen. Bei enthaltenen Gebühren verhindert ein fehlendes, ungültiges oder dem Bankkonto entsprechendes Gebührenkonto den Export vor Ausgabe der Datei.
- Drei neue Backendtests decken Kontokonfiguration, unveränderte ältere Clients, Gebühren-only-Zeiträume, Jahreswechsel, vorhandene Ausgaben und unveränderte Mitgliederschulden ab. Gezielte Regression: **68 bestanden, 1926 Assertions** (`/tmp/airmius-fee-export-tests.log`).
- Ein neuer App-Bedienungstest prüft gespeichertes Gebührenkonto, Änderung und tatsächlichen Einstellungs-Request. Dabei wurde ein Lebenszyklusfehler im gemeinsamen Buchhaltungseinstellungsdialog gefunden: Eingabecontroller wurden während der Schließanimation freigegeben. Der Ablauf wartet nun auf den vollständigen Abschluss der Dialogroute, bevor Controller freigegeben werden.
- Vollständiger Backendlauf: **1183 bestanden, 4 übersprungen, 29405 Assertions**, Log `/tmp/airmius-fee-export-full-php.log`. Aufruf mit `AIRMIUS_TEST_FRONTEND_BUILD_DIR=/tmp/airmius-fee-export-web-build`; damit werden isolierte Build-Artefakte geprüft. Die vier übersprungenen Tests gelten nicht als bestanden.
- Vollständiger Flutterlauf: **369 bestanden**, Log `/tmp/airmius-fee-export-full-flutter.log`. Abschließende statische Analyse ohne Befund (`/tmp/airmius-fee-export-final-analyze.log`). Isolierter Web-Build erfolgreich unter `/tmp/airmius-fee-export-web-build`; produktive Assets unverändert.
- Technische Beschreibung und Grenzen: [CLUB_SEPA_RETURN_FEES.md](CLUB_SEPA_RETURN_FEES.md). Keine pauschale DATEV-Kompatibilitäts- oder Produktivfreigabe: tatsächlicher Softwareimport, Kontenzuordnung, Browser-/Realgeräte- und Bank-/MySQL-Abnahme bleiben offen. Gebührenkorrekturen und Weiterbelastungen sind eigene offene Schritte. Keine produktive Migration, Buchung oder Veröffentlichung.

### 23.09.2026 – Ausgangszustand

- Gelesen: Modelle, Zahlungscontroller Web/API, Rechnungsübersichten, Bankabgleich und SEPA-Export.
- Baseline: `php artisan test --compact --filter='ClubInvoicePaymentStatusTest|ClubMembershipInvoiceWorkflowTest|ClubContributionRulesTest'` → 12 bestanden, 184 Assertions.
- Befund bestätigt: manuelle Teilzahlung setzt bisher die gesamte Rechnung auf bezahlt.
- Noch keine Aussage über vollständige Regression, MySQL-Konkurrenzverhalten oder Livebetrieb.

### 23.09.2026 – Teilzahlungen: erster umgesetzter Baustein

Implementierung:

- Gemeinsamer `ClubInvoicePaymentService` für Web, API und beide Bankabgleich-Pfade; Buchung und Änderungshistorie in einer Datenbanktransaktion, Rechnung wird vor der Buchung gesperrt und erneut geprüft.
- Teilzahlungen halten Rechnungen offen/überfällig. Ohne expliziten Betrag wird nur der aktuelle Restbetrag gebucht. Abschlusszahlungen setzen den Zahlungszeitpunkt; Zahlungskorrekturen berechnen Status und Restbetrag neu.
- Überzahlungen bleiben als tatsächlicher Zahlungseingang erhalten und werden getrennt ausgewiesen. Rückerstattung/Guthabenverrechnung ist weiterhin offen.
- Stornierte oder bereits bezahlte Rechnungen nehmen keine weitere manuelle Zahlung an. Nicht gebuchte/fehlgeschlagene Zahlungen reduzieren den Restbetrag nicht.
- Bestehende ausdrücklich als bezahlt markierte Rechnungen ohne Zahlungsdatensatz behalten ihren Status. Keine produktiven Daten automatisch umgeschrieben.
- Additive API-Felder: `received_amount`, `outstanding_amount`, `overpaid_amount`, `is_partially_paid`. Bestehende Statuswerte bleiben kompatibel.
- Rechnungsübersichten, Mitgliederportal und Cockpit berücksichtigen Restbeträge. Bankimport gleicht Restbeträge ab; SEPA-XML und Kontrollsumme verwenden Restbeträge.
- Web und App zeigen Rest-/Überzahlungsbeträge. Zahlungsdialoge schlagen den Restbetrag vor. Vier Sprachfassungen ergänzt; lokalisierte App-Statusanzeigen werden nicht mehr für Zahlungsberechtigungen verwendet.

Prüfung:

- Neuer Backendtest `tests/Feature/ClubPartialPaymentTest.php`: 13 Verhaltenstests für Teil-/Abschluss-/Überzahlung, Korrektur, stornierte Rechnungen, Nachkommastellen, Überfälligkeit, Rechte, Mitgliederportal, Bankimport-Duplikate, veraltete Rechnungsinstanzen und SEPA-Restbetrag.
- Zahlungsbezogene Regression einschließlich bestehender Rollen-, Beitrags-, Audit-, Mitglieder-, Bank- und SEPA-Tests: **48 Tests bestanden, 1633 Assertions**. Testauswahl: `ClubMembershipSepaServiceTest|ClubPartialPaymentTest|ClubInvoicePaymentStatusTest|ClubMembershipInvoiceWorkflowTest|ClubContributionRulesTest|MobileClubMembershipParityApiTest|ClubCockpitGovernanceTest|ClubAuditLogTest|ClubPermissionsTest|ClubMembershipAccessTest|OrganizationLocalizationContractTest`.
- Flutter: vollständiger Testbestand **341 bestanden**; darunter neuer Widgettest für Restbetragsanzeige und die tatsächlich übermittelte Abschlusszahlung.
- Flutter: vollständige statische Analyse **ohne Befund**.
- Web: `npx vite build --outDir /tmp/airmius-partial-payment-web-build` erfolgreich. Produktive Build-Artefakte nicht ersetzt.
- `git diff --check` ohne Befund.
- MySQL-Konkurrenztest mit realen parallelen Verbindungen und manueller Browser-/Geräteabnahme bleiben offen. Der SQLite-Test mit veraltetem Rechnungsobjekt ersetzt keinen solchen Last-/Konkurrenztest.

### Offene Prüfbefunde außerhalb des ersten Zahlungsbausteins

Der erste vollständige PHP-Lauf ergab 1099 bestandene, 4 übersprungene und 8 fehlgeschlagene Tests. Davon wurde die neu ausgelöste SEPA-Unit-Testregression (nicht persistierte Rechnungen durften keine Datenbankabfrage auslösen) behoben und zusammen mit den Zahlungstests nachgeprüft. Die beiden zuletzt ergänzten Zahlungstests wurden danach separat ebenfalls ausgeführt.

Die folgenden sieben übrigen Fehler betreffen Quellen/Artefakte, die durch diesen Zahlungsbaustein nicht geändert wurden. Sie bleiben sichtbar und werden nicht durch Abschwächen der Tests kaschiert:

- [x] Q001 `TrainingModalArchitectureContractTest`: veraltete Prüfung auf starre Schrittanzahl durch Prüfung der vollständigen und persönlichen Schrittlisten ersetzt und tatsächliche persönliche Abläufe bis zum POST mit Widgettests abgesichert. Dabei gefundenen Überlauf der Belastungsauswahl korrigiert; 354 App-Tests und gezielte Backend-/Architekturprüfungen bestanden.
- [x] Q002 `CoreWorkspaceAjaxContractTest`: Speicherverbrauch und Tarifgrenze wieder in der kompakten mobilen Plananzeige sichtbar. Begrenzte, unbegrenzte, fehlende und überschrittene Werte getestet; 24 Backend-/Cockpittests und alle 358 App-Tests bestanden.
- [x] Q003 `CrossDeviceReadinessTest`: aktuelle Registry, leere Vorlage, Geräte-Testskripte und aktive Anleitungen auf `1.0.69+154` abgestimmt. Zusätzlicher Abgleich mit `pubspec.yaml`; ältere Nachweise werden abgewiesen. Aktuelle Prüfung: 5 bestanden, 5 ausstehend, 0 Fehler; weiterhin `no_go`, keine externe Abnahme behauptet.
- [ ] Q004 `FrontendLocaleBundleContractTest`: Optimierung im isolierten Build nachgewiesen; produktive Assets noch nicht eingeführt. Der bisherige produktive arabische Sprachchunk bleibt 312137 Bytes groß und über der unveränderten Grenze 312000.
- [x] Q004a Sponsoren-Verwaltungstexte aus den zentralen Sprachdateien in das verzögert geladene Seitenmodul auslagern. Isolierter Build: arabischer Kernkatalog 308751 Bytes; Übersetzungen in DE/EN/FR/AR unverändert, Sprachwechsel und gezielte Tests bestanden.
- [ ] Q004b Optimierten Build im kontrollierten Release einführen und anschließend die unveränderte Größenprüfung gegen die produktiven Assets wiederholen.
- [x] Q005 `ReleasePreflightTest`: Versionsabweichung im mobilen Prüfmanifest korrigiert; Vorabprüfungs-Tests bestanden. Externe Freigaben bleiben offen, keine Release-Freigabe behauptet.
- [x] Q006 `StoreReadinessDocumentationTest`: Prüfmanifest auf die in `pubspec.yaml` und aktuellen Release-Notizen dokumentierte Version `1.0.69+154` abgestimmt; Versionsprüfung und Dokumentationstests bestanden. Alle 22 Nachweis-Gates bleiben unverändert ausstehend.
- [x] Q007 `Wcag22AccessibilityContractTest`: Radiusfilter erhielt zusätzlich zu seinem vorhandenen ARIA-Namen eine ausdrücklich zugeordnete Beschriftung, unter derselben Sichtbarkeitsbedingung. Bestehende Zugänglichkeits-/Matchingtests und isolierter Web-Build bestanden. Keine pauschale vollständige WCAG-Abnahme.

Diese Befunde verhindern eine pauschale Aussage „gesamtes Repository grün“. Sie blockieren nicht die weitere Arbeit an der Ausbau-Checkliste. Noch keine neue Version veröffentlicht oder produktive Migration ausgeführt.

### 23.09.2026 – Gespeicherte SEPA-Läufe: geprüfter Zwischenstand

- Neue Tabellen `club_sepa_batches` und `club_sepa_batch_items`; Kontodaten/Snapshots und gespeicherte XML-Datei verschlüsselt. Keine produktive Migration ausgeführt.
- Ablauf: Entwurf → Freigabe durch eine andere berechtigte Person → dokumentierter externer Vorabversand → Export. Die vereinbarte Frist wird beim Erstellen, Freigeben und Export berücksichtigt.
- Änderungen an Rechnung, Mandat oder Vereinskonto verhindern die Verwendung veralteter Freigaben. Stornierung vor Export gibt die Rechnungsreservierung frei und erhält die Historie. Exportierte Läufe können nicht auf diesem Weg zurückgesetzt werden.
- Wiederholter Download liefert dieselbe gespeicherte Bankdatei; Export allein verbucht keinen Zahlungseingang. Dies ist kein Nachweis einer bankseitigen Duplikatprüfung.
- Bestehender direkter SEPA-Export bleibt für Vereine ohne nicht stornierte gespeicherte Läufe verfügbar; danach führt die Fehlermeldung zum gespeicherten Lauf. Löschen referenzierter Rechnungen/Vereine wird mit einer fachlichen Fehlermeldung abgefangen, damit die neue Fremdschlüsselbindung nicht zu einem unklaren Datenbankfehler führt. Ein vollständiger Archivierungs-/Aufbewahrungsprozess bleibt offen.
- Web und Flutter bieten Liste, Erstellung, Freigabe, Versandnachweis, Export und Stornierung. Lesende Finanzrollen sehen keine Bearbeitungsaktionen. Neue Texte in DE/EN/FR/AR.
- Acht neue Flutter-Tests prüfen XML-Download, Authentifizierung, Offline-Sperre, fehlende automatische Wiederholung bei Transportfehlern, bisherige GET-Wiederholungen, lesenden Zugriff, Versandbestätigung, Dialogabbruch und arabische Leserichtung. Ein bei der Prüfung gefundener Dialog-Lebenszyklusfehler und die Sprachcode-Zuordnung wurden behoben.
- Gezielte Backend-Regression: **51 Tests bestanden, 1530 Assertions**, einschließlich neun neuer SEPA-Tests sowie bestehender Zahlungs-, Mitgliedschafts-, Rollen-, Audit-, Admin- und Lokalisierungsprüfungen.
- Neue Flutter-Suite: **8 Tests bestanden**. Vollständige statische Flutter-Analyse: **keine Befunde**. Vollständiger Flutter-Testbestand: **349 Tests bestanden**. Abschließender isolierter Web-Build unter `/tmp/airmius-sepa-batch-web-build` erfolgreich; produktive Build-Dateien wurden nicht ersetzt. Der vollständige Backend-Testlauf ergab **1110 bestanden, 4 übersprungen, 8 fehlgeschlagen**. Sieben Fehler entsprechen Q001–Q007. Der zusätzliche Befund `MobileApiPathSourceTest` wurde durch konkrete App-Endpunkte für Freigabe, Versandnachweis und Stornierung behoben, ohne den Test abzuschwächen. Anschließende Backend-Auswahl einschließlich API-Routenprüfung: **54 Tests bestanden, 1537 Assertions**. Nach dieser letzten API-Pfadänderung bestanden erneut alle **8 SEPA-App-Tests** und die vollständige Flutter-Analyse blieb **ohne Befund**; die Gesamtsuite wurde nicht nochmals wiederholt.
- Der bisherige direkte SEPA-Export speichert keine dauerhafte Laufhistorie. Bereits an Banken übergebene Altdateien können deshalb nicht automatisch aus den neuen Tabellen erkannt werden; ihr Abgleich ist ein offener Einführungsschritt.
- T007 bleibt insgesamt offen: insbesondere automatische Vorabinformation, Rücklastschriften, reale Bank-/Geräteabnahme und MySQL-Konkurrenzverhalten sind noch nicht abgeschlossen.

### 23.09.2026 – Vorabinformationen mit kontrolliertem E-Mail-Versand

- Neue Tabelle `club_sepa_notices` hält je Laufposition genau eine Nachricht mit verschlüsseltem Empfänger/Inhalt und Status `prepared`, `queued`, `sending`, `sent`, `blocked`, `uncertain` oder `cancelled` fest. Die Migration ist nicht produktiv ausgeführt.
- Die Vorschau enthält Rechnungsnummer, tatsächlichen Restbetrag, Einzugsdatum, Gläubiger-ID, Mandatsreferenz und nur die letzten vier Kontoziffern. Nachrichtentexte werden in der Sprache des verknüpften Rechnungsmitglieds eingefroren (DE/EN/FR/AR).
- Vor dem Versand müssen Verwaltungspersonen Inhalte und Empfänger ausdrücklich bestätigen. Der aktuelle Empfänger ist das verknüpfte Rechnungsmitglied. Eine beliebige oder abweichende Zahleradresse wird nicht automatisch geraten.
- Der Worker reserviert den Transportversuch dauerhaft vor dem externen Aufruf. Doppelte Queue-Jobs senden dieselbe Nachricht nicht erneut. Transportfehler und unterbrochene Versuche bleiben unklar und werden nicht automatisch wiederholt. Ein erneuter Queue-Auftrag kann ausschließlich noch nicht versuchte oder vor dem Transport gesperrte Positionen verarbeiten.
- Rechte, Planberechtigung, Vorabfrist, Rechnung, Mandat, Vereinskonto und Empfänger werden erneut geprüft. Jeder Worker prüft seine eigene Position; der gesamte Lauf wird beim Queue-Auftrag und XML-Export geprüft.
- Ein vorläufiger Mailstatus gibt keinen Export frei. Erst wenn alle Positionen vom Transport angenommen wurden und die Frist weiterhin reicht, wechselt der Lauf zu `notified`. Nachrichten-ID, Zeitstempel, Mailhistorie und Audit dokumentieren die Übergabe. Das bedeutet ausdrücklich nicht, dass die Nachricht beim Empfänger zugestellt oder gelesen wurde.
- Log-/Array-/Null-Mailer sowie automatische Transport-Fallbackketten werden für den produktiven Ablauf nicht als Versandnachweis akzeptiert. Tests verwenden ausschließlich Mocks bzw. den ausdrücklich ersetzten In-Memory-Transport; es wurden keine echten Mitglieder angeschrieben.
- Manuelle Versanddokumentation und Stornierung stoppen wartende Nachrichten. Während eines aktiven Versandversuchs sind diese Aktionen gesperrt. Nach einem unterbrochenen Versuch bleibt das Ergebnis unklar und erfordert externe Prüfung.
- Elf neue Backend-Verhaltenstests einschließlich tatsächlicher Laravel-Mailadapter-Ausführung mit In-Memory-Transport. Die neue App-Prüfung beweist den getrennten Weg von Vorschau zu bestätigtem Versand. Die abschließende Backend-Auswahl einschließlich bisheriger Zahlungs-, Mitgliedschafts-, Rollen-, API-Routen- und Mailverwaltungsprüfungen bestand mit **70 Tests, 1646 Assertions**. Die SEPA-App-Suite besteht mit **9 Tests**; der vollständige Flutter-Testbestand mit **350 Tests**.
- Isolierter Web-Build unter `/tmp/airmius-sepa-notice-web-build` erfolgreich. Vollständige Flutter-Analyse ohne Befund; `git diff --check` ohne Befund. Der vollständige Backend-Testbestand wurde in diesem Schritt nicht erneut ausgeführt; die zuvor dokumentierten sieben Befunde bleiben offen. Kein produktiver Versand, keine produktive Migration und keine Veröffentlichung.

### 25.09.2026 – Provider-Zustell- und Bounce-Rückmeldungen

- Der neue öffentliche Postmark-Endpunkt akzeptiert ausschließlich Delivery- und Bounce-Ereignisse mit konfigurierter HTTP-Basic-Authentifizierung. Eine optionale IP-Allowlist kann nach geprüfter Proxykonfiguration zugeschaltet werden; fehlende Zugangsdaten schließen den Endpunkt.
- Transportstatus und Providerstatus bleiben getrennt. Neue angenommene Nachrichten beginnen bei `pending`; Postmark kann `delivered` oder `bounced` melden. Ein Bounce überschreibt eine frühere Zustellmeldung, verändert aber weder den bereits dokumentierten Laufstatus noch eine erzeugte Bankdatei.
- Providerereignisse werden dauerhaft und idempotent gespeichert. Die Ereignisspur enthält keine Empfängeradresse, Nachricht, Providerbeschreibung oder vollständige Payload. Unbekannte Nachrichten-IDs und abweichende Empfänger werden ohne Bestandsauskunft ignoriert.
- API, Web und Flutter zeigen ausstehende Rückmeldung, bestätigte Zustellung und Bounce getrennt von „an Mailtransport übergeben“ in DE/EN/FR/AR. Bounce-Klassen werden für die fachliche Bearbeitung angezeigt.
- Die offiziellen `symfony/postmark-mailer`- und `symfony/http-client`-Pakete sind als Laufzeitabhängigkeiten eingebunden; die gespeicherte endgültige Provider-Nachrichten-ID bildet die Callback-Zuordnung.
- Gezielte Backend-Prüfung: **15 Tests, 146 Assertions**; die breitere SEPA-Auswahl bestand mit **96 Tests, 1357 Assertions**. Delivery, Bounce, Wiederholung, Transport-Brücke, Authentifizierung, IP-Allowlist, Zuordnung, Statusgrenzen, Audit und Datensparsamkeit sind enthalten. Der Web-Vertragstest, ein isolierter produktiver Web-Build, **16 Flutter-SEPA-Tests** und die statische Flutter-Analyse sind ebenfalls grün. Die produktive Postmark-/Queue-Abnahme bleibt T007d3b und wurde nicht vorgetäuscht.
- Der Abhängigkeitsaudit fand vier nach dem früheren Security-Drill veröffentlichte High-Advisories gegen CommonMark 2.9.0. Das Paket wurde innerhalb der Laravel-Vorgabe auf 2.10.3 aktualisiert; `composer audit` meldet danach keine bekannten Advisories. SEPA- und Markdown-benutzende Guardian-Abläufe bestanden gemeinsam mit **26 Tests, 349 Assertions**.
- `airmius:audit-club-sepa-notice-delivery` prüft Repository-Vertrag, tatsächlich wirksamen Billing-Mailer einschließlich Datenbanküberschreibung, Postmark-/Webhook-Konfiguration, dauerhafte Queue-Verbindung, erforderliches Schema und ausschließlich aggregierte Providerzustände. Der strikte Modus erteilt ein Go nur zusammen mit vollständig geprüfter Staging-Evidenz; er sendet selbst keine Nachricht und gibt keine Zugangsdaten, Empfänger, Nachrichten, IDs oder Provider-Payloads aus.
- Fünf gezielte Tests mit **26 Assertions** prüfen den rein lesenden Betrieb, fehlende Evidenz, sichere Geheimnisbehandlung, ungeeignete Queue-/Credential-Konfiguration, aktive Absenderüberschreibungen und den vollständigen versionierten Evidenzvertrag. T007d3b2 bleibt offen, weil in diesem Schritt keine reale Staging-Zustellung, kein kontrollierter Bounce und kein Worker-Neustart ausgeführt wurden.
- Einrichtung und externe Abnahme: [CLUB_SEPA_NOTICE_DELIVERY_FEEDBACK.md](CLUB_SEPA_NOTICE_DELIVERY_FEEDBACK.md).

### 23.09.2026 – Bankgutschriften und Rücklastschriften: technische Umsetzung

- Neue Tabelle `club_sepa_settlements` speichert Bankgutschrift, Rückgabe und Wiederholungsfreigabe je exportierter Laufposition. Bankdatum, Referenz, handelnde Person und Rückgabegrund bleiben nachvollziehbar. Keine produktive Migration ausgeführt.
- Eine Gutschrift wird ausdrücklich entweder neu verbucht oder mit einer vorhandenen passenden Bankzahlung verknüpft. Auch der bestehende Bankimport mit Zahlungsart `bank_import` wird unterstützt. Betrag, Rechnungs-/Vereinszuordnung und Buchungsdatum müssen passen; wiederholte identische Anfragen erzeugen keine zweite Zahlung.
- Eine Rückgabe nimmt nur die zugeordnete SEPA-Zahlung aus dem bezahlten Rechnungsbetrag heraus. Andere Teilzahlungen bleiben erhalten. Der ursprüngliche Zahlungsdatensatz mit seinem Datum bleibt bestehen; vor einer Gutschrift wird keine künstliche negative Zahlung erzeugt. Stornierte Rechnungen werden nicht wieder geöffnet.
- Bankreferenzen werden vereinheitlicht und vor der Buchung auch nach Unicode-Großschreibung auf die speicherbare Länge geprüft. Widersprüchliche Wiederholungen und bereits verwendete Referenzen werden abgelehnt.
- Erneuter Einzug benötigt die Freigabe einer anderen berechtigten Person. Die Freigabe löst ausschließlich die alte Rechnungsreservierung; ein neuer Lauf durchläuft wieder Mandats-/Restbetragsprüfung, Freigabe und Vorabinformation. Eine wiederholte Freigabe kann keine Reservierung eines neueren Laufs lösen.
- Ursprünglicher Eingang und Rückgabe werden im Finanzüberblick und bestehenden CSV-Export ihren jeweiligen Zeiträumen zugeordnet. Jahreswechsel ist getestet. Gebühren sind ausdrücklich nur dokumentiert, weder als Ausgabe gebucht noch dem Mitglied berechnet. Ein fachlich abgenommener vollständiger Buchhaltungsprozess bleibt offen.
- API, Vue und Flutter zeigen Bankergebnisse und bieten bestätigungspflichtige Erfassung. Lesende Finanzrollen erhalten keine Buchungsaktionen. Neue Texte liegen in DE/EN/FR/AR vor. Historisch verknüpfte Zahlungen können nicht über die allgemeine Zahlungskorrektur oder Löschung verändert werden.
- 14 neue Backend-Verhaltenstests decken Teilzahlungen, vorhandenen Bankimport, Rückgabe vor Gutschrift, Doppelanfragen, ungültige Angaben, stornierte/überzahlte/alte Rechnungen, Rechte, Wiederholung, Jahreswechsel und Unicode-Referenzen ab. Auch die gültige Bankreferenz `0` muss im CSV als Rückgabe mit `H` erscheinen. Abschließende gezielte Backend-Auswahl: **58 bestanden, 563 Assertions**. Drei zusätzliche App-Tests prüfen Rückgabebestätigung/Gebühren, lesenden Zugriff und die Auswahl einer vorhandenen Zahlung; die SEPA-App-Suite bestand mit **12 Tests**.
- Die Gesamtprüfung fand einen falsch platzierten App-Zugriff auf `payment` in einer Finanzbuchungszeile; dieser wurde korrigiert. Abschließender vollständiger Flutter-Testbestand: **353 bestanden**. Abschließende statische Flutter-Analyse: **ohne Befund**. Web-Build unter `/tmp/airmius-sepa-result-web-build` erfolgreich; produktive Assets nicht ersetzt.
- Vollständiger Backend-Lauf: **1135 bestanden, 4 übersprungen, 7 fehlgeschlagen; 28173 Assertions**. Die sieben Fehler entsprechen den weiterhin offenen Q001–Q007 (Training, Cockpit, Release-Nachweise, Sprachchunk und Sport-Matching-Zugänglichkeit). Der Gesamtlauf startete vor den letzten Bankimport-/CSV-Sonderfallkorrekturen; diese wurden anschließend mit der oben genannten Auswahl von 58 Tests geprüft. Keine pauschale Aussage „gesamtes Repository grün“; keine Tests abgeschwächt. `git diff --check` ohne Befund.
- T007e insgesamt bleibt offen: automatische Rückgabedateien/negative Bankumsätze, Gebührenprozesse sowie die produktive Bank-, MySQL- und Geräteabnahme sind nicht abgeschlossen.

### 23.09.2026 – Lesende Diagnose historischer Rechnungsstatus

- Neuer administrativer Befehl `airmius:audit-club-invoice-payments <club>` mit obligatorischer Vereinsauswahl, Tabellen-/JSON-Ausgabe und optionalem Strict-Exitcode bei Prüfhinweisen. Keine neue Web-/App-Berechtigung und kein automatischer Hintergrundlauf.
- Unterscheidet bezahlte Rechnungen ohne Zahlungsdatensätze, ohne erfolgreich verbuchte Eingänge oder mit Unterdeckung. Erkennt zusätzlich gedeckte offene Rechnungen, Überzahlungen, stornierte Rechnungen mit Eingängen, abweichende Vereinszuordnungen und negative Beträge.
- Standardausgabe enthält nur Anzahlen. `--details` ergänzt interne Rechnungs-IDs und Centbeträge; keine Personen-, Kontakt-, Bank- oder Freitextdaten. Begrenzte Detailseiten mit `--limit` und `--after-id`; die Gesamtzahlen berücksichtigen weiterhin alle Rechnungen des Vereins. Verarbeitung in Blöcken von 250 Rechnungen.
- Hinweise beweisen keinen Zahlungsfehler und erzeugen keine neue Forderung. Historisch als bezahlt markierte Rechnungen behalten ihren Status und operativen Restbetrag. Kein Reparaturmodus, keine Buchungen, kein Versand. Mehrere Abfrageblöcke bilden keinen gemeinsamen Datenbanksnapshot; parallele Änderungen werden ausdrücklich als Grenze ausgewiesen.
- Bedienung und fachliche Grenzen: [CLUB_INVOICE_RECONCILIATION_AUDIT.md](CLUB_INVOICE_RECONCILIATION_AUDIT.md).
- Sieben neue Verhaltenstests einschließlich Vereinsabgrenzung, Detailseiten über die Blockgrenze, Datensparsamkeit, JSON/Strict/Fehlerfälle und Nachweis ausschließlich lesender SQL-Abfragen. Datenbankzustand vor und nach der Diagnose unverändert. Gemeinsam mit Teilzahlungs-, Rechnungsstatus- und Rücklastschrifttests: **37 Tests bestanden, 416 Assertions**. Neue PHP-Dateien formatiert, `git diff --check` ohne Befund.
- Nur isolierte SQLite-Testdaten verwendet. Kein produktiver Rechnungsbestand geprüft oder geändert. Keine bestehenden Zahlungs- oder Oberflächenpfade verändert; daher kein erneuter Web-/App-Gesamtlauf in diesem Schritt. Die zuvor dokumentierten sieben Backend-Gesamtprüfbefunde bleiben offen.

### 23.09.2026 – Bestehende Zugänglichkeits- und Release-Prüfbefunde reduziert

- Radiusfilter der Web-Sportpartnersuche: `label` mit `for="sport-matching-filter-radius"` ergänzt; erscheint wie das zugehörige Auswahlfeld nur nach Auswahl eines Standorts. Vorhandener ARIA-Name, Optionen und Filterverhalten bleiben erhalten.
- `Wcag22AccessibilityContractTest|SportMatchingFeatureTest`: **26 Tests bestanden, 392 Assertions**. Isolierter Web-Build unter `/tmp/airmius-matching-accessibility-build` erfolgreich; keine produktiven Assets ersetzt. Keine neue manuelle Screenreader-/Browser-Gesamtabnahme.
- Das mobile Prüfmanifest hatte Version `1.0.65+150`, während `pubspec.yaml` und aktuelle Release-Notizen `1.0.69+154` dokumentieren. Nur die Manifest-Version synchronisiert. Alle 22 Gates bleiben in ihren bisherigen Pending-Zuständen; keine alten Geräte-, Store- oder Buildnachweise als neue Abnahme übernommen. Die laufenden neuen Funktionsänderungen sind dadurch nicht veröffentlicht.
- `scripts/assert_release_version_consistency.sh` erfolgreich für Version `1.0.69+154`, Android-ID und iOS-ID. `StoreReadinessDocumentationTest|ReleasePreflightTest|CrossDeviceReadinessTest`: **14 bestanden, 1 fehlgeschlagen, 219 Assertions**. Der verbleibende Fehler ist ausschließlich Q003: die gesonderte Cross-Device-Registry erwartet noch `1.0.42+127`.
- Q005, Q006 und Q007 geschlossen; Q001–Q004 bleiben offen. Kein erneuter vollständiger Backend-/App-Lauf für diese begrenzten Änderungen. Tests unverändert, `git diff --check` ohne Befund.

### 23.09.2026 – Trainingsplan-Schritte und schmale App-Darstellung

- Q001 untersucht: Die alte Quelltextprüfung erwartete einen ternären Ausdruck mit sechs bzw. drei Schritten. Die vorhandene Oberfläche bildet stattdessen konkrete sichtbare Schrittlisten ab: vollständige Planung sechs, vollständige Bearbeitung drei, persönlicher Kurzplan drei, erweiterter persönlicher Plan fünf und persönliche Bearbeitung zwei Schritte. Der alte Ausdruck ist keine zutreffende Beschreibung mehr.
- Die bestehende Architekturprüfung auf die konkreten Schrittlisten, deren Länge sowie Vor-/Zurücknavigation umgestellt; Prüfungen für Übungen/Sätze, API-Payload und Sprachfassungen erhalten. Die sechs Schritte der vollständigen Planung werden weiterhin ausdrücklich geprüft.
- Den bestehenden persönlichen App-Test bis zum tatsächlichen POST erweitert: Speichersperre bei fehlendem Einheitstitel, Zurück/Vorwärts ohne Datenverlust, Titel/Sportart und ausschließliche Selbstzuordnung. Ein zusätzlicher Widgettest durchläuft alle fünf erweiterten persönlichen Schritte einschließlich Übungen/Sätzen, Details und API-Payload.
- Der neue Test fand einen echten `RenderFlex`-Überlauf von 107 Pixeln bei 390 Pixel Bildschirmbreite in der Belastungsauswahl. Das Feld nutzt jetzt die verfügbare Breite; lange ausgewählte Texte werden einzeilig gekürzt, die geöffnete Optionsliste erlaubt variable Zeilenhöhe. Auswahl und übermittelter Belastungswert werden im Test geprüft.
- Gezielte Backend-/Architekturprüfung `TrainingModalArchitectureContractTest|TrainingWorkflowIntegrationTest|TrainingSystemTest`: **12 bestanden, 161 Assertions**. Abschließender vollständiger App-Testbestand einschließlich Kurzplan, erweitertem Plan und Belastungsauswahl: **354 bestanden**. Vollständige statische Flutter-Analyse **ohne Befund**; `git diff --check` ohne Befund. Q001 geschlossen, Q002–Q004 bleiben offen. Der gesamte Backend-Testbestand wurde für diesen App-/Testschritt nicht erneut ausgeführt.
- Kein produktiver Build, keine Datenmigration und keine Veröffentlichung. Die Prüfung auf 390 Pixel Breite ersetzt keine Realgeräte-/Screenreader-/Mehrsprachenabnahme.

### 23.09.2026 – Speicherverbrauch im mobilen Vereinscockpit

- Die aufklappbare Vereinsplan-Anzeige zeigt wieder den tatsächlichen Speicherverbrauch und die Tarifgrenze aus `management.subscription.storage_bytes` und `storage_gb`.
- Binäre Einheiten B/KiB/MiB/GiB/TiB entsprechen der serverseitigen Prüfung mit 1024³ Bytes pro Tarif-Gigabyte. Zahlen werden passend zur gewählten App-Sprache formatiert; fehlende Werte erscheinen als lokalisierte Angabe „Nicht verfügbar“ in DE/EN/FR/AR. Fehlende Tarifdaten werden nicht als unbegrenzt interpretiert; nur ausdrücklich geliefertes `null` oder `0` entspricht dem serverseitig unbegrenzten Tarif.
- Vier zusätzliche Widgettests prüfen unbegrenzten Speicher, Null-Grenze, fehlende Daten und Überschreitung der Grenze bei 390 Pixel Breite und 200 Prozent Schriftgröße. Der vorhandene Cockpittest prüft zusätzlich `1,5 MiB / 10 GiB` bei vergrößerter Schrift. Alle **5 gezielten App-Tests bestanden**.
- `CoreWorkspaceAjaxContractTest|MobileClubMembershipParityApiTest|ClubCockpitGovernanceTest|PlanFeatureServiceTest`: **24 bestanden, 467 Assertions**. Bestehende Prüfungen unverändert. Kein Eingriff in Uploadprüfung, Tarife, Serverberechtigungen oder Finanzdaten.
- Abschließender vollständiger App-Testbestand: **358 bestanden**. Vollständige statische Flutter-Analyse: **ohne Befund**; `git diff --check` ohne Befund. Q002 geschlossen; Q003 und Q004 bleiben offen. Kein erneuter vollständiger Backend-Lauf für diese reine App-Anzeige. Keine neue App-Version gebaut oder veröffentlicht; keine produktive Migration.

### 23.09.2026 – Zentrale Sprachdateien verkleinert

- Der ausschließlich von der Sponsorenverwaltung verwendete Namensraum `sponsors_admin` liegt jetzt in `resources/js/i18n/sponsorsAdminLocalization.json`. Die Verwaltungsseite registriert alle vier Sprachen beim Laden über `mergeLocaleMessage`; zentrale Startkataloge enthalten diese Modultexte nicht mehr.
- Die ausgelagerten Werte in DE/EN/FR/AR wurden gegen die bisherigen versionierten Texte verglichen: vollständig unverändert. Laufzeitprüfung mit der installierten vue-i18n-Bibliothek bestätigt Sprachwechsel AR/EN/FR/DE/AR und Erhalt der Modultexte bei anschließendem Laden eines Kernkatalogs.
- Isolierter Vite-Build `/tmp/airmius-lazy-sponsors-build` erfolgreich. Kernkataloggrößen: DE **254085**, EN **238310**, FR **264314**, AR **308751 Bytes**. Alle liegen unter dem unveränderten Grenzwert **312000 Bytes**. Die Sponsorenverwaltung bleibt ein dynamischer Seitenimport und gehört nicht zum statischen Importbaum des App-Einstiegs.
- Der bestehende Manifesttest unterstützt für CI/Testläufe optional `AIRMIUS_TEST_FRONTEND_BUILD_DIR`; ohne diese Variable prüft er weiterhin `public/build`. Größenlimit, dynamische Sprachimporte und vorhandene Artefakte werden unverändert verlangt. Ergänzte Prüfungen decken Sprachschlüsselparität, statisch referenzierte Seitentexte und verzögerten Seitenimport ab.
- Nachweis: `AIRMIUS_TEST_FRONTEND_BUILD_DIR=/tmp/airmius-lazy-sponsors-build php artisan test --compact --filter='FrontendLocaleBundleContractTest|MobileEditorialSponsorApiTest|OrganizationLocalizationContractTest|LocalizedWebManifestTest'` → **21 bestanden, 1872 Assertions**. PHP-Testdatei formatiert; `git diff --check` ohne Befund.
- Keine produktiven Builddateien ersetzt, keine Veröffentlichung. Q004a technisch abgeschlossen; Q004b bleibt vor produktiver Abnahme offen. Der Standardtest gegen die alten produktiven Dateien wird dadurch ausdrücklich noch nicht als bestanden gemeldet. Q003 und der weitere Funktionsausbau bleiben ebenfalls offen.

### 23.09.2026 – Versionsbindung der geräteübergreifenden Abnahme

- Cross-Device-Registry, noch leere JSON-Nachweisvorlage, Versionsmetadaten der Android-/iOS-Helferskripte, Shell-Validator und aktive Abnahmeanleitungen auf die aktuelle App-Konfiguration `1.0.69+154` abgestimmt. Historische Release-Notizen, tatsächliche Nachweisdateien und deren Freigaben nicht umgeschrieben.
- Die Repository-Vertragsprüfung vergleicht ihre mobile Version zusätzlich mit `pubspec.yaml`; ein späterer Versionswechsel erfordert damit erneut eine konsistente Aktualisierung.
- Vier zusätzliche Verhaltenstests: vollständig ausgefüllte lokale Nachweise früherer Builds ablehnen; vollständige Gerätechecklisten früherer Builds ablehnen; alten maßgeblichen mobilen Nachweis trotz aktueller lokaler Daten ablehnen; neue Vorlage bleibt leer/ausstehend und erzeugt keine Freigabe. Shell-Prüfungen verwenden ausschließlich künstliche Dateien im temporären Testverzeichnis, keine Geräteverbindungen.
- Auch die separate Auswertung `mobile.authoritative_evidence` lehnt nun ein Manifest mit anderer Buildversion ab. Vorher konnte die Gesamtentscheidung zwar korrekt `no_go`, das separate Feld `external_evidence_complete` bei alten externen Belegen aber dennoch `true` melden. Diese widersprüchliche Statusanzeige ist mit einem Verhaltenstest abgesichert.
- Abschließende Auswahl `CrossDeviceReadinessTest|ReleasePreflightTest|StoreReadinessDocumentationTest`: **19 bestanden, 329 Assertions**. Syntaxprüfung aller drei Shell-Skripte erfolgreich. Aktueller echter Aufruf `php artisan airmius:audit-cross-device --json --strict`: **5 pass, 5 pending, 0 fail**, `automated_checks_passed=true`, `external_evidence_complete=false`, Entscheidung **no_go**, erwarteter Exitcode 1.
- Registry-Version und technische Tests bestätigen keinen neuen signierten App-Build. Alle 22 Gates im mobilen Prüfmanifest bleiben ausstehend; reale Geräte-, Sprach-, Barrierefreiheits- und externe Freigaben müssen für den tatsächlich vorgesehenen Release-Build erhoben werden.
- Vollständige Backend-Regression mit `AIRMIUS_TEST_FRONTEND_BUILD_DIR=/tmp/airmius-lazy-sponsors-build php artisan test --compact`: **1155 bestanden, 4 übersprungen, 0 fehlgeschlagen; 28978 Assertions**. Der Gesamtlauf lief bereits vor der letzten zusätzlichen Prüfung des separaten mobilen Evidenzstatus; diese letzte Änderung wurde mit den oben genannten 19 Tests abschließend nachgeprüft. Damit sind die technischen Ausgangsbefunde im vorbereiteten Stand behoben; Q004b bleibt für die produktiven Web-Artefakte offen. Die vier übersprungenen Tests sind kein Abnahmenachweis.
- `git diff --check` ohne Befund. Keine produktive Migration, Veröffentlichung oder Übernahme älterer Nachweise. App-Oberflächen unverändert; letzter vollständiger Flutter-Nachweis bleibt bei 358 bestandenen Tests.

### 23.09.2026 – Kontrollierter Rückgabe-CSV-Import: Backend und Web-Zwischenstand

- Neue Vorschau-/Importendpunkte pro exportiertem SEPA-Lauf. Vorschau bindet Datei, Person, Lauf und geprüften Zustand für 15 Minuten; Import verlangt ausdrückliche Bestätigung. Positionen ohne verknüpften Eingang benötigen eine zusätzliche Bestätigung und verändern keine bestehenden Zahlungen.
- Festes UTF-8-CSV-Schema, maximal 200 Datenzeilen und 2 MiB. Negativer exakter EUR-Einzugsbetrag, gültiges Bankdatum, eindeutige Referenz und Rückgabegrund erforderlich; optionale IBAN muss zum eingefrorenen Konto passen. Unbekannte Spalten, Gebühren-Sammelbeträge und mehrdeutige Positionen werden abgewiesen.
- Alle Zeilen werden gemeinsam in einer Transaktion verarbeitet. Bestehende Rückgabelogik erhält andere Teilzahlungen und verhindert doppelte Rücknahmen. Ein umgehängter Zahlungseingang wird bereits in der Vorschau als ungültig erkannt.
- Zehn CSV-Verhaltenstests prüfen unter anderem lesende Vorschau, Bestätigung, identische Wiederholung, ungültige Zeilen, Datei-/Person-/Lauf-/Zeitbindung, veränderte Eingänge, IBAN und fehlende Rechte. Abschließende gezielte Regression: **66 Tests bestanden, 683 Assertions** (`ClubSepaSettlementTest|ClubPartialPaymentTest|ClubSepaBatchTest|ClubSepaNoticeTest|MobileClubMembershipParityApiTest|MobileApiPathSourceTest`).
- Web-Komponente mit zeilenweiser Vorschau, Fehlern und getrennten Bestätigungen in DE/EN/FR/AR eingebunden. Ungültige Beträge erscheinen nicht als scheinbare Nullbuchungen. Isolierter Build unter `/tmp/airmius-return-import-web-build` erfolgreich; produktive Assets unverändert.
- Format und Grenzen in `docs/CLUB_SEPA_RETURN_IMPORT.md` dokumentiert. Native Importoberfläche, Browserabnahme, weitere Bankformate und Gebühren bleiben offen. Keine produktive Migration, Bankaktion oder Veröffentlichung. Gesamte Backend-/Flutter-Suite in diesem Schritt nicht erneut ausgeführt.

### 23.09.2026 – Nativer Rückgabeimport

- Neuer Flutter-Bildschirm öffnet aus exportierten SEPA-Läufen ausschließlich für Finanzverwaltungspersonen. Dateiauswahl, Dateigrößenprüfung, vollständige zeilenweise Vorschau, lokalisierte Fehler und beide Bestätigungen entsprechen dem Backend-Ablauf. Texte in DE/EN/FR/AR.
- Eigener authentifizierter Multipart-Upload überträgt dieselben ausgewählten Bytes für Vorschau und Import. Keine Offline-Warteschlange, keine automatische Wiederholung; begrenzte Transportwartezeit. Bereits versionierte API-Basisadressen werden wie beim bestehenden Netzwerkclient normalisiert.
- Nach einem Importfehler verwirft die App die Vorschau und beide Bestätigungen. Nach Erfolg kehrt sie zur Laufübersicht zurück und lädt deren Ergebnisse neu. Der Bedienungstest verwendet einen simulierten Dateidialog und HTTP-Transport, keine echten Bankdateien oder Geräte.
- Drei Uploadtests prüfen Dateiinhalt, Authentifizierung, Sprache, Multipart-Felder, Größenlimit, Serverfehler und genau einen Versuch bei Verbindungsverlust. Zwei Bedienungstests prüfen die getrennten Bestätigungen sowie Erfolg und unklare Transportfehler bei 390 Pixel Breite.
- Statische Flutter-Analyse ohne Befund; Backend-Routenprüfung `MobileApiPathSourceTest`: **3 Tests, 7 Assertions bestanden**. Reale Dateiauswahl, Geräte-/Browserabnahme und Bankformate außerhalb des dokumentierten CSV bleiben offen. Keine Veröffentlichung oder produktive Datenänderung.
- Vollständiger Flutter-Testbestand: **363 Tests bestanden** (`/tmp/airmius-return-mobile-full.log`). Die letzte Normalisierung der Upload-Basisadresse wurde zusätzlich mit den **5 neuen Importtests** am finalen Stand geprüft (`/tmp/airmius-return-mobile-final-focused.log`); abschließende vollständige statische Analyse ohne Befund. `git diff --check` ebenfalls ohne Befund.

### 23.09.2026 – Mehrpositionsimport und Vereinszuordnung

- Gemeinsame SEPA-Ergebnislogik prüft nach dem Sperren der Datensätze erneut, dass die Rechnung zum Verein des Laufs gehört. Bei einer Rückgabe muss auch der Zahlungseingang weiterhin diesem Verein zugeordnet sein. Das schützt neben dem CSV-Import die manuellen Web-/App-Aktionen.
- Neuer Fehlerfalltest führt die erste Rückgabe tatsächlich einschließlich Rechnungsabgleich und Audit aus und löst erst bei der zweiten Zeile einen kontrollierten Buchungsfehler aus. Danach sind beide Eingänge weiterhin bezahlt, beide Rechnungen vollständig ausgeglichen und sämtliche Zwischenänderungen einschließlich Audit zurückgerollt. SQLite-Transaktionstest; kein Nachweis paralleler MySQL-Verbindungen.
- Zweiter Test importiert zwei Rückgaben erfolgreich, erhält jeweils 20 Euro unabhängige Teilzahlung und prüft eine identische Wiederholung ohne zusätzliche Rückgabeprotokolle oder Zahlungen. Dritter Test verhindert die manuelle Rücknahme nach nachträglicher Fremdvereinszuordnung von Zahlung oder Rechnung.
- Gezielte Zahlungs-/SEPA-/Mitglieder-Regression: **66 Tests bestanden, 728 Assertions** (`ClubSepaSettlementTest|ClubPartialPaymentTest|ClubSepaBatchTest|ClubSepaNoticeTest|MobileClubMembershipParityApiTest`). Keine neuen Datenbanktabellen und keine produktiven Datenänderungen.
- Vollständige Backend-Regression am abschließenden Stand: **1168 bestanden, 4 übersprungen, 0 fehlgeschlagen; 29191 Assertions**. Aufruf mit `AIRMIUS_TEST_FRONTEND_BUILD_DIR=/tmp/airmius-return-import-web-build php artisan test --compact`, Log `/tmp/airmius-return-atomic-full-php.log`. Damit ist der isolierte Web-Build geprüft, nicht die unverändert produktiven Assets. Übersprungene Tests und reale MySQL-/Bankabnahmen bleiben ausdrücklich kein Erfolgsnachweis. `git diff --check` ohne Befund.

### 23.09.2026 – Spaltenzuordnung für Rückgabe-CSV

- Neuer berechtigungsgeprüfter Endpunkt liest ausschließlich Überschriften und Zeilenanzahl. CSV-Dateien dürfen bis zu 50 eindeutig benannte Spalten enthalten; leere/doppelte Überschriften, fehlerhafte Zeilenbreiten und ungültige Kodierung werden abgewiesen. Bisheriges Standardformat bleibt unverändert nutzbar.
- Web und App bieten sieben Feldzuordnungen einschließlich optionaler IBAN. Jede Pflichtspalte muss eindeutig zugeordnet und jede andere Spalte ausdrücklich ausgeschlossen werden. Vorschauen zeigen ausgeschlossene Spalten erneut. Änderungen an Zuordnung oder Ausschlussbestätigung verwerfen die Oberflächenvorschau.
- Der Server speichert die gewählte Zuordnung und Ausschlüsse im verschlüsselten Vorschautoken. Der Import verwendet ausschließlich diese eingefrorene Konfiguration und weist nachträglich mitgesendete Zuordnungen ab. Gebühren in ausgeschlossenen Spalten werden ausdrücklich nicht verbucht; exakter negativer EUR-Einzugsbetrag und alle bisherigen Positionsprüfungen bleiben erforderlich.
- Drei zusätzliche Backend-Verhaltenstests prüfen abweichende Überschriften, explizite Ausschlüsse, eingefrorene Zuordnung, Wiederholung, fehlende/doppelte/ungültige Zuordnungen und Rechte. Gezielte Regression einschließlich Lokalisierung und API-Routen: **75 Tests bestanden, 1751 Assertions** (`/tmp/airmius-return-mapping-tests.log`).
- App-Tests durchlaufen Standard- und zugeordneten Import jeweils mit Erfolg und Verbindungsfehler; ohne bestätigte Ausschlüsse bleibt die Vorschau gesperrt. Zusammen mit bisherigen SEPA-/Uploadtests **19 Tests bestanden** (`/tmp/airmius-mapping-mobile-tests.log`). Abschließende vollständige Flutter-Analyse ohne Befund. Isolierter Web-Build des finalen Stands `/tmp/airmius-return-mapping-final-web-build` erfolgreich; produktive Assets unverändert. `git diff --check` ohne Befund.
- Keine produktiven Buchungen oder Veröffentlichungen. Gesamt-Backend-/Flutter-Suiten in diesem Schritt nicht erneut ausgeführt; reale Bank-/Browser-/Geräteabnahme, CAMT-/PAIN-Rückgaben und Gebührenprozesse bleiben offen.

### 23.09.2026 – Rückgabegebühren als getrennte Bankausgabe

- Additive Migration verknüpft pro Rückgabe höchstens eine Finanzausgabe; eine Ausgabe kann höchstens einer Rückgabe zugeordnet werden. `return_fee_cents` bleibt ein Informationswert und erzeugt weiterhin keine automatische Buchung.
- Neuer bestätigungspflichtiger Gebührenendpunkt prüft Finanzverwaltungsrecht, Tarif, Lauf-/Rückgabestatus, Betrag, Bankdatum und Referenz. Vorhandene Ausgaben müssen ausdrücklich per ID verbunden werden und zu Verein, Bankkonto, Ausgabentyp, Betrag, Datum und Referenz passen. Neue Ausgaben, Verknüpfung und Audit werden gemeinsam gespeichert; identische Wiederholung erzeugt keine Duplikate.
- Bankreferenzen werden auch bei vorhandenen Ausgaben Unicode-sicher normalisiert verglichen. Der Vergleich liest bei Bedarf in begrenzten Paketen; unterschiedliche Schreibweisen wie `ß`/`SS` dürfen keine zweite Ausgabe auslösen.
- Die bestehende Finanzübersicht berücksichtigt die neue Ausgabe. Ein Test prüft 3,50 Euro Gebühr, 16,50 Euro verbleibenden Gesamtbestand bei 20 Euro vorherigem Eingang und unverändert 80 Euro Beitragsrestforderung. Verknüpfte Ausgaben können über die allgemeine Web-/API-Finanzbearbeitung nicht überschrieben werden; normale Ausgaben bleiben bearbeitbar.
- Sieben neue Gebühren-Verhaltenstests einschließlich Wiederholung, bestehender Ausgabe, unpassender/fremder Buchungen, Datum, Rechten, allgemeiner Bearbeitung, Unicode-Referenz, doppelter Zuordnung und Migration vor erster Gebührenbuchung. Gezielte Gesamtprüfung: **72 Tests bestanden, 1949 Assertions** (`ClubSepaSettlementTest|ClubPartialPaymentTest|ClubSepaBatchTest|MobileClubMembershipParityApiTest|ClubCockpitGovernanceTest|OrganizationLocalizationContractTest`), Log `/tmp/airmius-sepa-fee-final-tests.log`. `git diff --check` ohne Befund.
- Dokumentation: `docs/CLUB_SEPA_RETURN_FEES.md`. Web-/App-Buchungsdialoge, eigener Korrekturprozess, Gebührenexport und Weiterbelastung bleiben offen. Keine produktive Migration oder Buchung. Gesamte Backend-/Flutter-Suite in diesem Arbeitsschritt nicht erneut ausgeführt; Oberflächen unverändert.

### 23.09.2026 – Web-Gebührendialog und Ausgabensuche

- Web-Laufübersicht zeigt für zurückgegebene Positionen einen separaten Gebührendialog, sobald das Backend die Gebührenmigration als verfügbar meldet. Neue Bankausgabe und Verknüpfung sind ausdrücklich getrennt wählbar; die Bestätigung wird bei Änderungen der Buchungsangaben zurückgesetzt. Bereits gebuchte Gebühren werden auch für lesende Finanzrollen angezeigt.
- Neue Ausgabensuche liefert pro Seite höchstens 25 unverknüpfte Bankausgaben des eigenen Vereins im zulässigen Zeitraum. Suchbar nach Bankreferenz, mit Vor-/Zurücknavigation und reduzierten Feldern ID, Betrag, Datum und Referenz. Auswahl wird beim Buchen erneut serverseitig geprüft.
- Texte in DE/EN/FR/AR. Keine automatische Wiederholung nach Buchungsfehlern; Bestätigung wird nach Fehler zurückgesetzt. Nach Erfolg lädt die Laufübersicht neu.
- Zwei zusätzliche Verhaltenstests für Pagination, Referenzsuche, fremde/ungeeignete/verknüpfte Ausgaben, Rückgabekontext und Rechte. Gezielte Backend-Regression: **74 Tests bestanden, 1976 Assertions** (`/tmp/airmius-sepa-fee-web-tests.log`). Keine Produktivbuchungen und keine Migration ausgeführt. Zu diesem Zwischenstand war Flutter unverändert; die native Gebührenbuchung und spätere Weiterbelastungsoberfläche sind in den nachfolgenden Prüfnachweisen ergänzt. Browserabnahme bleibt offen.
- Abschließender isolierter Web-Build `/tmp/airmius-sepa-fee-web-final-build` erfolgreich; produktive Assets unverändert. `git diff --check` ohne Befund. Keine erneute vollständige Backend-Suite in diesem Schritt.

### 23.09.2026 – Gebührenbuchung in der App

- Neuer nativer Gebührenbildschirm wird bei zurückgegebenen Positionen ausschließlich für Finanzverwaltungspersonen und bei verfügbarem Gebührenschema angeboten. Bereits gebuchte Gebühren bleiben für lesende Finanzrollen sichtbar, ohne Buchungsaktionen.
- Vorhandene Ausgaben nach Bankreferenz suchen, Ergebnisse seitenweise laden und Ausgabe ausdrücklich auswählen. Betrag, Bankdatum und Referenz werden aus der Auswahl übernommen; die endgültige Verknüpfung prüft weiterhin der Server. Neue Ausgaben besitzen getrennte Eingaben mit genauer Centumrechnung und striktem Datumsformat.
- Jede Änderung der Buchungsdaten oder Auswahl setzt die Bestätigung zurück. Nach unklarem Transportfehler wird die Bestätigung ebenfalls zurückgesetzt; Gebührenaktionen verwenden den bestehenden SEPA-Transport ohne automatische Wiederholung. Erfolg schließt den Dialog und lädt die Laufübersicht neu.
- Neue Texte aus demselben DE/EN/FR/AR-Katalog wie im Web. Drei neue Widgettests prüfen paginierte Suche und echte Verknüpfungspayload sowie neue Ausgabe mit Erfolg/Verbindungsfehler bei 390 Pixel Breite. Bestehende Tests prüfen zusätzlich Gebührenanzeige ohne Schreibrechte und genau einen Transportversuch am Gebührenendpunkt. **15 gezielte App-Tests bestanden**, vollständige statische Analyse ohne Befund. Backend-API-Pfadprüfung: **3 Tests, 7 Assertions bestanden**.
- Keine produktive Gebührenbuchung, Migration oder Veröffentlichung. Browser-/Realgeräteabnahme, Gebührenkorrekturen und Gebührenexport bleiben offen.
- Abschließende vollständige Flutter-Regression: **368 Tests bestanden** (`/tmp/airmius-fee-mobile-full-tests.log`). Vollständige statische Analyse ohne Befund (`/tmp/airmius-fee-mobile-final-analyze.log`); `git diff --check` ohne Befund. Keine erneute vollständige Backend-Suite bei dieser ausschließlich mobilen Erweiterung.

## Vollständige Anforderungen aus der Nutzervorlage

Die folgenden Anforderungen bleiben bis zum dokumentierten End-to-End-Nachweis offen. Erläuterungen und Tabellen der Vorlage sind zur fachlichen Einordnung erhalten.


# A. Vereinsstruktur und Mitglieder

## 1. Vereinsstammdaten und Organisationsstruktur

- [x] Vereinsname, Anschrift, Kontaktdaten, Bankverbindungen und zuständige Ansprechpartner verwalten.
- [x] Vereinsregisterdaten, Verbandszugehörigkeiten und steuerliche Stammdaten hinterlegen.
- [x] Vereinslogo, Farben, Briefpapier und Dokumentvorlagen einstellen.
- [ ] Mehrere Abteilungen, Sportarten, Standorte, Mannschaften und Trainingsgruppen anlegen.
- [ ] Vorstand, Ausschüsse, Arbeitsgruppen und Verantwortlichkeiten abbilden.
- [ ] Geschäfts-, Beitrags- und Sportjahre unabhängig voneinander definieren.
- [ ] Eigene Datenfelder, Kategorien und Nummernkreise erstellen.
- [ ] Satzungen, Ordnungen und Beitragsmodelle mit ihrem jeweiligen Gültigkeitszeitraum hinterlegen.

## 2. Benutzer, Rollen und Berechtigungen

Die Rollen sollten anpassbar sein. Ich würde mindestens diese vorsehen:

| Rolle                                   | Typischer Zugriff                                              |
| --------------------------------------- | -------------------------------------------------------------- |
| Vereinsadministrator                    | Einrichtung, Benutzerverwaltung und technische Einstellungen   |
| Vorstand                                | Vereinsüberblick, Entscheidungen, Berichte und Freigaben       |
| Geschäftsstelle                         | Mitgliederverwaltung, Anträge, Termine und Korrespondenz       |
| Kassenwart                              | Beiträge, Zahlungen, Buchhaltung und Finanzberichte            |
| Kassenprüfer                            | Lesender Zugriff auf freigegebene Finanzunterlagen             |
| Abteilungsleiter                        | Mitglieder, Personal, Termine und Budget der eigenen Abteilung |
| Trainer und Betreuer                    | Eigene Mannschaften, Trainingsplanung und Anwesenheiten        |
| Mitglied                                | Eigene Daten, Termine, Buchungen und Dokumente                 |
| Eltern beziehungsweise Sorgeberechtigte | Freigegebene Informationen und Aktionen für ihre Kinder        |
| Helfer und Mitarbeiter                  | Zugewiesene Aufgaben, Dienste und Arbeitsnachweise             |
| Externe Ansprechpartner                 | Nur ausdrücklich freigegebene Vorgänge und Dokumente           |

Zusätzlich sollte die App:

- [ ] Mehrere Rollen pro Person ermöglichen.
- [ ] Berechtigungen nach Verein, Abteilung, Team und Datenart begrenzen.
- [ ] Lesen, Bearbeiten, Exportieren, Freigeben und Löschen getrennt steuern.
- [ ] Zeitlich begrenzte Vertretungsrechte vergeben.
- [ ] Sensible Aktionen über ein Vier-Augen-Prinzip absichern.
- [ ] Berechtigungen beim Ende einer Tätigkeit automatisch zur Überprüfung vorlegen.

**Beispiel:** Ein Trainer darf die Anwesenheiten seiner Mannschaft sehen, aber nicht automatisch Bankverbindungen oder Beitragsrückstände.

## 3. Startseite und zentrale Arbeitsübersicht

- [x] Persönliche Startseiten je Rolle anzeigen.
- [x] Anstehende Trainings, Spiele, Sitzungen und Veranstaltungen zusammenfassen.
- [x] Offene Anträge, Zahlungen, Aufgaben und Freigaben hervorheben.
- [x] Auf fehlende Dokumente und auslaufende Verträge oder Lizenzen hinweisen.
- [x] Eine globale Suche nach Mitgliedern, Rechnungen, Dokumenten und Terminen anbieten.
- [x] Häufig genutzte Funktionen als Schnellaktionen bereitstellen.
- [x] Eigene Favoriten, Filter und Ansichten speichern.

## 4. Zentrale Mitglieder- und Personenverwaltung

- [x] Digitale Mitgliederakten mit Stammdaten, Kontakten und Mitgliedsnummer führen.
- [x] Aktive, passive, fördernde, befristete und Ehrenmitgliedschaften unterscheiden.
- [x] Eintritt, Austritt, Ruhezeiten und Statusänderungen dokumentieren.
- [x] Personen mehreren Abteilungen und Teams zuordnen, ohne doppelte Akten anzulegen.
- [x] Familien, Haushalte und abweichende Beitragszahler miteinander verknüpfen.
- [x] Mitgliedschaft, Benutzerkonto und sportliche Teilnahme getrennt verwalten.
- [x] Auch Nichtmitglieder erfassen, beispielsweise Probetrainierende, Kursteilnehmer oder externe Trainer.
- [x] Dubletten erkennen und kontrolliert zusammenführen.
- [x] Mitgliedschaftsverläufe, Ehrungen und Jubiläen nachvollziehbar speichern.

## 5. Aufnahme, Änderungen und Austritt

- [x] Online-Mitgliedsanträge mit individuell anpassbaren Formularen anbieten.
- [x] Interessenten und Probetrainings vor einer Mitgliedschaft verwalten.
- [x] Fehlende Angaben und Unterlagen gezielt nachfordern.
- [x] Anträge prüfen, genehmigen, ablehnen oder auf Wartelisten setzen.
- [x] Begrüßungsnachrichten, Zugangseinladungen und Aufnahmebestätigungen auslösen.
- [x] Abteilungswechsel, Tarifänderungen und Mitgliedschaftspausen bearbeiten.
- [x] Kündigungen mit Eingang, gewünschtem Austritt und bestätigtem Enddatum verwalten.
- [x] Beim Austritt offene Beiträge, ausgeliehenes Material und Zugangsrechte prüfen.
- [ ] Ehemalige Mitglieder nach festgelegten Aufbewahrungs- und Löschregeln behandeln.

## 6. Eltern-, Familien- und Jugendverwaltung

- [x] Mehrere Kinder über einen Elternzugang verwalten.
- [ ] Mehrere Sorgeberechtigte mit jeweils eigenen Zugängen berücksichtigen.
- [ ] Beitragszahler, Sorgeberechtigte, Notfallkontakte und Abholberechtigte getrennt erfassen.
- [ ] Berechtigungen für einzelne Kinder gezielt vergeben.
- [ ] Zustimmungen zu Fahrten, Veranstaltungen und Veröffentlichungen verwalten.
- [ ] Foto- und Videoeinwilligungen nach Verwendungszweck unterscheiden.
- [ ] Änderungen und Widerrufe an zuständige Personen weitergeben.
- [ ] Beim Erreichen der Volljährigkeit Zugriffsrechte und Ansprechpartner überprüfen.
- [ ] Jugendvertretungen, Jugendgruppen und altersbezogene Angebote organisieren.

## 7. Mitgliederportal und digitale Mitgliedskarte

- [ ] Eigene Kontaktdaten bearbeiten oder Änderungen zur Freigabe einreichen.
- [ ] Mitgliedschaft, Tarife, Rechnungen und Zahlungsstatus einsehen.
- [ ] Trainings, Veranstaltungen, Plätze und Kurse buchen.
- [ ] Zu- und Absagen abgeben sowie Abwesenheiten melden.
- [ ] Bescheinigungen, Rechnungen und Vereinsunterlagen herunterladen.
- [ ] Anträge, Erstattungen und Kündigungen digital einreichen.
- [ ] Benachrichtigungen und Sichtbarkeitseinstellungen selbst verwalten.
- [ ] Eine digitale Mitgliedskarte mit kontrollierbarem QR-Code anbieten.
- [ ] Auch einen betreuten Verwaltungsweg für Mitglieder ohne Smartphone bereitstellen.

# B. Sportbetrieb und Trainingsorganisation

## 8. Mannschaften und Trainingsgruppen

- [ ] Mannschaften, Gruppen, Jahrgänge und Leistungsklassen anlegen.
- [ ] Trainer, Co-Trainer, Betreuer und Mannschaftsverantwortliche zuordnen.
- [ ] Kader, Positionen, Rückennummern und Gruppenzugehörigkeiten verwalten.
- [ ] Gastteilnahmen und Einsätze in mehreren Gruppen berücksichtigen.
- [ ] Kapazitätsgrenzen und Wartelisten einstellen.
- [ ] Wechsel zwischen Gruppen und Teams dokumentieren.
- [ ] Saisonwechsel vorbereiten und bestehende Strukturen übernehmen.
- [ ] Mannschaftsbezogene Dokumente, Nachrichten und Aufgaben bündeln.

## 9. Kalender, Termine und Anwesenheit

- [ ] Einen gemeinsamen Vereinskalender mit persönlichen Ansichten bereitstellen.
- [ ] Trainingsserien, Einzeltermine, Spiele, Sitzungen und Dienste planen.
- [ ] Ferien, Feiertage, Sperrzeiten und saisonale Abweichungen berücksichtigen.
- [ ] Zusagen, Absagen und Rückmeldefristen verwalten.
- [ ] Tatsächliche Anwesenheit getrennt von vorherigen Zusagen erfassen.
- [ ] Anwesenheiten manuell oder über einen kontrollierten Check-in aufnehmen.
- [ ] Trainer-, Raum- und Teilnehmerkonflikte anzeigen.
- [ ] Terminänderungen gezielt an betroffene Personen melden.
- [ ] Kalender abonnierbar machen und druckbare Übersichten erzeugen.

## 10. Trainingsplanung und sportliche Entwicklung

- [ ] Trainingseinheiten mit Zielen, Ablauf und benötigtem Material erstellen.
- [ ] Eine Übungsbibliothek mit Texten, Bildern und Videos aufbauen.
- [ ] Übungen nach Sportart, Alter, Niveau und Schwerpunkt filtern.
- [ ] Wochen-, Monats- und Saisonpläne vorbereiten.
- [ ] Aufgaben und Trainingspläne einzelnen Sportlern oder Gruppen zuweisen.
- [ ] Trainingsbeteiligung und vereinbarte Entwicklungsziele dokumentieren.
- [ ] Sportartspezifische Ergebnisse und persönliche Bestleistungen erfassen.
- [ ] Trainerfeedback und Entwicklungsgespräche geschützt festhalten.
- [ ] Trainingspläne gemeinsam bearbeiten und Vertretungen übergeben.

**Wichtig für die Gestaltung:** Leistungsdaten sollten nicht automatisch öffentlich werden. Öffentliche Ranglisten oder Vergleiche wären ein separat aktivierbares Zusatzangebot.

## 11. Spiele, Wettkämpfe und Turniere

- [ ] Spiel- und Wettkampfkalender verwalten.
- [ ] Gegner, Austragungsorte, Spielklassen und Wettbewerbe erfassen.
- [ ] Kader nominieren und Nominierungen bestätigen lassen.
- [ ] Aufstellungen, Startlisten und Staffeln erstellen.
- [ ] Spielberechtigungen, Lizenzen und Meldefristen nachhalten.
- [ ] Ergebnisse, Tabellen, Punkte und persönliche Leistungen dokumentieren.
- [ ] Schiedsrichter, Kampfrichter und weitere Einsatzkräfte einplanen.
- [ ] Turniergruppen, Spielpläne und Ausscheidungsrunden organisieren.
- [ ] Startgelder, Meldegebühren und Wettkampfkosten zuordnen.
- [ ] Spielberichte und Ergebnisveröffentlichungen zur Freigabe vorbereiten.

Dafür würde ich **sportartspezifische Erweiterungen** vorsehen:

| Sportbereich                           | Zusätzliche Funktionen                                             |
| -------------------------------------- | ------------------------------------------------------------------ |
| Fußball, Handball, Basketball          | Aufstellungen, Wechsel, Tore beziehungsweise Punkte, Einsatzzeiten |
| Laufsport und Leichtathletik           | Distanzen, Zeiten, Disziplinen, Altersklassen und Bestleistungen   |
| Schwimmen                              | Bahnen, Disziplinen, Meldezeiten und Staffeln                      |
| Tennis und andere Rückschlagsportarten | Platzbuchung, Ranglisten, Einzel- und Doppelbegegnungen            |
| Kampfsport                             | Gürtelgrade, Prüfungen, Gewichtsklassen und Wettkampfmeldungen     |
| Turnen und Tanz                        | Übungsprogramme, Wertungen, Formationen und Aufführungen           |

## 12. Kurse, Ferienangebote und Trainingslager

- [ ] Kurse mit Terminen, Preisen, Voraussetzungen und Kapazitäten veröffentlichen.
- [ ] Mitglieder und Nichtmitglieder anmelden.
- [ ] Einzeltermine, Kursblöcke und Mehrfachkarten anbieten.
- [ ] Wartelisten und zeitlich begrenztes Nachrücken verwalten.
- [ ] Mindestteilnehmerzahlen und Anmeldeschlüsse überwachen.
- [ ] Teilnahmebestätigungen und Zertifikate erstellen.
- [ ] Stornierungen, Ersatzteilnehmer und Erstattungen bearbeiten.
- [ ] Feriencamps und Trainingslager mit Gruppen, Betreuung und Verpflegung organisieren.
- [ ] Einnahmen, Ausgaben und Auslastung je Angebot auswerten.

# C. Personal, Ehrenamt und Vereinsressourcen

## 13. Trainer und Mitarbeiter

- [ ] Trainer, Übungsleiter, Beschäftigte und Honorarkräfte verwalten.
- [ ] Qualifikationen, Fortbildungen und Lizenzgültigkeiten erfassen.
- [ ] Verfügbarkeiten, Einsatzpläne und Vertretungen organisieren.
- [ ] Verträge, vereinbarte Vergütungen und Zuständigkeiten hinterlegen.
- [ ] Arbeits-, Trainings- und Vertretungsstunden dokumentieren.
- [ ] Honorare, Auslagen und Fahrtkosten zur Prüfung einreichen.
- [ ] Urlaub und Abwesenheiten nach dem jeweiligen Beschäftigungsmodell abbilden.
- [ ] Schulungen und Einweisungen nachhalten.
- [ ] Abrechnungsdaten an eine geeignete Lohn- oder Honorarabrechnung übergeben.

## 14. Ehrenamt, Helfer und Arbeitsdienste

- [ ] Helferprofile mit Fähigkeiten, Interessen und Verfügbarkeiten anlegen.
- [ ] Aufgaben für Veranstaltungen, Reinigung, Fahrdienste und Platzpflege veröffentlichen.
- [ ] Mitglieder zur freiwilligen Übernahme von Diensten anmelden lassen.
- [ ] Schichten besetzen und unbesetzte Dienste anzeigen.
- [ ] Tauschanfragen und Vertretungen ermöglichen.
- [ ] Geleistete Stunden erfassen und bestätigen lassen.
- [ ] Vereinbarte Pflichtarbeitsstunden, Ausnahmen und Ersatzleistungen abbilden.
- [ ] Ehrenamtsnachweise und Dankschreiben erstellen.
- [ ] Überlastung einzelner Helfer in der Planung sichtbar machen.

## 15. Sportstätten, Räume und Anlagen

- [ ] Hallen, Sportplätze, Vereinsräume und Teilflächen verwalten.
- [ ] Umkleiden, Besprechungsräume und zusätzliche Ressourcen mitbuchen.
- [ ] Belegungspläne mit Freigaben und Prioritätsregeln erstellen.
- [ ] Doppelbuchungen und Kapazitätsüberschreitungen verhindern.
- [ ] Interne und externe Vermietungen mit unterschiedlichen Preisen abbilden.
- [ ] Nutzungsverträge, Kautionen und Übergaben verwalten.
- [ ] Wartung, Reinigung, Prüfungen und Reparaturen terminieren.
- [ ] Schäden mit Fotos melden und deren Bearbeitung verfolgen.
- [ ] Schlüssel, Zutrittsberechtigungen und Rückgaben kontrollieren.

## 16. Material, Ausrüstung und Fahrzeuge

- [ ] Sportgeräte, Trikotsätze, Trainingsmaterial und technische Geräte inventarisieren.
- [ ] Lagerorte, Mengen, Zustände und Verantwortliche festhalten.
- [ ] Ausgaben an Mitglieder und Teams dokumentieren.
- [ ] Rückgabetermine, Verluste und Schäden nachverfolgen.
- [ ] Geräte über QR-Codes eindeutig identifizieren.
- [ ] Mindestbestände und Nachbestellvorschläge einrichten.
- [ ] Beschaffungen mit Anforderung, Freigabe, Bestellung und Wareneingang abbilden.
- [ ] Wartungs- und Austauschtermine verwalten.
- [ ] Vereinsfahrzeuge reservieren sowie Fahrten, Kilometer und Schlüsselübergaben dokumentieren.

# D. Beiträge, Finanzen und Finanzierung

## 17. Beiträge und Tarifmodelle

- [ ] Monats-, Quartals-, Halbjahres- und Jahresbeiträge konfigurieren.
- [ ] Grundbeitrag und zusätzliche Abteilungsbeiträge kombinieren.
- [ ] Familien-, Jugend-, Förder- und Sondertarife abbilden.
- [ ] Aufnahmegebühren, Umlagen und zusätzliche Leistungen berechnen.
- [ ] Geschwisterrabatte, Ermäßigungen und Beitragsbefreiungen verwalten.
- [ ] Unterjährige Eintritte und anteilige Beiträge berücksichtigen.
- [ ] Tarifänderungen mit einem zukünftigen Gültigkeitsdatum planen.
- [ ] Alters- oder statusabhängige Tarifwechsel kontrolliert auslösen.
- [ ] Unterschiedliche Beitragszahler und Sammelabrechnungen ermöglichen.
- [ ] Härtefälle, Stundungen und Ratenvereinbarungen geschützt bearbeiten.

## 18. Zahlungen, Lastschriften und Mahnwesen

- [ ] Forderungen mit Fälligkeit und Zahlungsstatus führen.
- [ ] Überweisung, Lastschrift, Barzahlung und angebundene Onlinezahlungen unterstützen.
- [ ] Zahlungseingänge Rechnungen und Mitgliedern zuordnen.
- [ ] Teilzahlungen, Überzahlungen und Sammelzahlungen bearbeiten.
- [ ] SEPA-Mandate, Mandatsreferenzen und die Gläubiger-ID verwalten.
- [ ] Lastschriftläufe vorab prüfen und freigeben lassen.
- [ ] Rücklastschriften, Fehlergründe und erneute Zahlungsversuche bearbeiten.
- [ ] Zahlungserinnerungen und Mahnstufen mit Ausnahmen konfigurieren.
- [ ] Rückerstattungen kontrolliert freigeben und dokumentieren.
- [ ] Doppelte Einzüge und doppelte Zahlungsverbuchungen verhindern.

Bei SEPA gehören **gültige Mandate und Vorabinformationen** ausdrücklich in den Prozess. Die Vorabinformation muss insbesondere Betrag und Fälligkeit enthalten; ihre Frist sollte anhand der geltenden Vereinbarung konfigurierbar sein. ([Bundesbank][1])

## 19. Buchhaltung, Belege und Jahresabschluss

- [ ] Einnahmen, Ausgaben, Bankkonten und Barkassen verwalten.
- [ ] Eingangs- und Ausgangsrechnungen erfassen.
- [ ] Belege mobil fotografieren, hochladen und Buchungen zuordnen.
- [ ] Lieferanten, Kunden und offene Rechnungen verwalten.
- [ ] Konten, Kostenstellen, Projekte und Abteilungen unterscheiden.
- [ ] Umsatzsteuerbehandlung und Kontierungsregeln konfigurierbar machen.
- [ ] Buchungen korrigierbar, aber mit nachvollziehbarer Änderungshistorie führen.
- [ ] Rechnungsprüfung und Zahlungsfreigabe voneinander trennen.
- [ ] Jahresabschlussunterlagen und Datenexporte für die Steuerberatung erstellen.
- [ ] E-Rechnungen empfangen, anzeigen, prüfen, archivieren und bei Bedarf erstellen.

Bei gemeinnützigen Vereinen sollte die Zuordnung zu **ideellem Bereich, Vermögensverwaltung, Zweckbetrieb und steuerpflichtigem wirtschaftlichem Geschäftsbetrieb** unterstützt werden. Die Finanzverwaltung verlangt eine entsprechende Zuordnung der Einnahmen und Ausgaben. ([Finanzamt NRW][2])

Die E-Rechnungsfunktionen müssen zur tatsächlichen Tätigkeit passen: Für unternehmerische Tätigkeiten können entsprechende Pflichten bestehen; für den nichtunternehmerischen Bereich gelten andere Regeln. Ein pauschaler Schalter „jeder Verein muss alles als E-Rechnung ausstellen“ wäre falsch. ([Bundesministerium der Finanzen][3])

## 20. Budgetplanung, Zuschüsse und Fördermittel

- [ ] Vereins-, Abteilungs-, Mannschafts- und Projektbudgets erstellen.
- [ ] Geplante und tatsächliche Einnahmen und Ausgaben vergleichen.
- [ ] Liquidität und bevorstehende Zahlungsverpflichtungen darstellen.
- [ ] Budgetüberschreitungen frühzeitig melden.
- [ ] Förderprogramme, Antragsfristen und Ansprechpartner verwalten.
- [ ] Anträge, Bewilligungen, Eigenanteile und Auszahlungen dokumentieren.
- [ ] Förderfähige Kosten und zugehörige Belege einem Projekt zuordnen.
- [ ] Verwendungsnachweise und Tätigkeitsberichte zusammenstellen.
- [ ] Freigabegrenzen für Anschaffungen und Ausgaben festlegen.

## 21. Sponsoren, Spenden und Partnerschaften

- [ ] Sponsoren und Partner mit Ansprechpartnern und Gesprächsverlauf verwalten.
- [ ] Sponsoringpakete und individuelle Angebote erstellen.
- [ ] Verträge, Laufzeiten, Zahlungen und Verlängerungsfristen nachhalten.
- [ ] Vereinbarte Gegenleistungen wie Trikotwerbung oder Werbeflächen dokumentieren.
- [ ] Die tatsächliche Erfüllung dieser Leistungen mit Nachweisen verfolgen.
- [ ] Geld- und Sachspenden mit Zweckbindung erfassen.
- [ ] Spendenkampagnen und Fortschrittsanzeigen anbieten.
- [ ] Zuwendungsbestätigungen nach fachlicher Prüfung der Voraussetzungen erstellen.
- [ ] Spenden, Sponsoring und Mitgliedsbeiträge als unterschiedliche Vorgänge behandeln.
- [ ] Dankschreiben und Berichte an Unterstützer vorbereiten.

# E. Kommunikation, Organisation und Vereinsleben

## 22. Interne Kommunikation

- [ ] Vereinsweite, abteilungsbezogene und teambezogene Nachrichten versenden.
- [x] Einzel- und Gruppenchats mit passenden Moderationsrechten anbieten.
- [ ] E-Mail und Push-Nachrichten sowie optional weitere Versandkanäle anbinden.
- [x] Wichtige Mitteilungen mit einer ausdrücklich angeforderten Bestätigung versehen.
- [x] Anhänge, Umfragen und Terminabstimmungen ermöglichen.
- [ ] Nachrichten zeitversetzt versenden und Vorlagen nutzen.
- [ ] Ruhezeiten und persönliche Benachrichtigungswünsche berücksichtigen.
- [ ] Fehlgeschlagene Zustellungen anzeigen.
- [x] Vertrauliche Empfängerlisten und geschützte Kommunikationswege bereitstellen.
- [x] Kommunikation mit Minderjährigen nach dem Schutzkonzept des Vereins gestalten.

Evidenz 2026-09-26: `app/Support/CommunicationInteractionReadinessRegistry.php` belegt T037c als lokalen Vertrag fuer Einzelchat, Gruppenchat, Chat-Anhaenge, Vereinsumfragen, Event-Termin-/Entscheidungsabstimmungen und Pflichtbestaetigungen einschliesslich Moderations- und Aufbewahrungsregeln; `tests/Feature/CommunicationInteractionReadinessContractTest.php` prueft Scope, Minderjaehrigenschutz, Vereinsgrenzen, Datenschutz und offene externe Gates. Breitere Versandkanaele, Vorlagen und Betriebszustellung bleiben separat offen.

## 23. Website, Öffentlichkeitsarbeit und Medien

- [ ] Öffentliche Vereins-, Abteilungs- und Mannschaftsseiten verwalten.
- [ ] Nachrichten, Ansprechpartner und Trainingszeiten veröffentlichen.
- [ ] Freigegebene Termine und Ergebnisse aus der Verwaltung übernehmen.
- [ ] Online-Anträge und Kursbuchungen in die Website einbinden.
- [ ] Sponsoren und Partner präsentieren.
- [ ] Fotos und Videos mit Freigaben und Nutzungszwecken verwalten.
- [x] Beiträge vor Veröffentlichung redaktionell prüfen lassen.
- [ ] Newsletter-Anmeldungen und Abmeldungen verwalten.
- [ ] Pressemitteilungen und Vereinsberichte vorbereiten.
- [ ] Interne Informationen konsequent von öffentlichen Inhalten trennen.

Evidenz 2026-09-26: Vereinsankuendigungen besitzen redaktionelle Zustaende `draft`, `in_review`, `published` und `withdrawn`, Content-Typen sowie Reviewer-/Ruecknahme-Metadaten; veroeffentlichte und zurueckgenommene Inhalte werden getrennt ausgeliefert. Geprueft durch `tests/Feature/ClubAnnouncementApiTest.php`.

## 24. Dokumente, Verträge und Vereinswissen

- [ ] Dokumente zentral und mit abgestuften Zugriffsrechten ablegen.
- [ ] Dateien Mitgliedern, Teams, Projekten oder Veranstaltungen zuordnen.
- [x] Versionen, Bearbeitungsstände und Freigaben verwalten.
- [ ] Vorlagen für Briefe, Bestätigungen, Verträge und Protokolle bereitstellen.
- [ ] Serienbriefe mit Daten aus der Verwaltung erzeugen.
- [x] Vertragslaufzeiten, Kündigungsfristen und Wiedervorlagen überwachen.
- [ ] Erforderliche Unterschriftsprozesse passend zum jeweiligen Dokument unterstützen.
- [ ] Ein internes Handbuch mit Abläufen und häufigen Fragen aufbauen.
- [ ] Zuständigkeitswechsel durch strukturierte Übergabeunterlagen erleichtern.

Evidenz 2026-09-26: Policy-Dokumente verwalten Version, Workflow-Status, Freigabe-/Veroeffentlichungsdaten, Klassifikation, Aufbewahrung, Vertragslaufzeit, Kuendigungsfrist und Review-Job; veroeffentlichte Versionen erhalten eine Publikationspruefsumme und sind fachlich unveraenderlich. Geprueft durch `tests/Feature/ClubPolicyDocumentTest.php`.

## 25. Vorstand, Sitzungen und Mitgliederversammlungen

- [ ] Vorstands-, Ausschuss- und Mitgliederversammlungen planen.
- [ ] Einladungen, Tagesordnungen und Antragsfristen verwalten.
- [ ] Anträge und Beschlussvorlagen sammeln.
- [ ] Teilnahme und Stimmberechtigung getrennt feststellen.
- [x] Abstimmungen und Wahlen nach dem vorgesehenen Verfahren unterstützen.
- [ ] Geheime Abstimmungen mit einem dafür geeigneten Verfahren ermöglichen.
- [ ] Protokolle erstellen, freigeben und an berechtigte Personen verteilen.
- [ ] Beschlüsse in Aufgaben mit Verantwortlichen und Fristen überführen.
- [ ] Amtszeiten, Wiederwahlen und Übergaben nachhalten.

Evidenz 2026-09-26: Governance-Meetings unterstuetzen offene und namentliche Beschluesse/Wahlen mit Stimmberechtigten-Snapshot, Quorum, einfacher/absoluter Mehrheitsregel, Enthaltung, Ergebnissnapshot, Audit und Korrektursperre nach Schliessung. Geprueft durch `tests/Feature/ClubGovernanceMeetingTest.php`. Geheime Abstimmungen bleiben ausdruecklich offen.

Bei hybriden oder virtuellen Mitgliederversammlungen müssen die konkrete Satzung und die gesetzlichen Voraussetzungen berücksichtigt werden. Ein gewöhnliches Umfragewerkzeug sollte daher nicht automatisch als rechtsgültiges Wahlverfahren behandelt werden. ([Gesetze im Internet][4])

## 26. Veranstaltungen, Fahrten und Reisen

- [ ] Vereinsfeste, Ausflüge, Turniere und Jubiläen organisieren.
- [ ] Anmeldungen, Teilnehmerlimits und Wartelisten verwalten.
- [ ] Tickets, Eintrittskarten und kontrollierte Einlasslisten erstellen.
- [ ] Helfer, Auf- und Abbau sowie Dienstleister koordinieren.
- [ ] Fahrgemeinschaften und freie Mitfahrplätze organisieren.
- [ ] Busse, Unterkünfte, Zimmer und Verpflegung planen.
- [ ] Reiseunterlagen und erforderliche Zustimmungen nachhalten.
- [ ] Kosten auf Teilnehmer, Mannschaften oder den Verein verteilen.
- [ ] Absagen, Ersatzteilnehmer und Erstattungen bearbeiten.
- [ ] Veranstaltungsergebnisse und Wirtschaftlichkeit auswerten.

## 27. Vereinskleidung, Merchandising und Sammelbestellungen

- [ ] Artikel wie Trikots, Hoodies, Trainingsanzüge und Taschen verwalten.
- [ ] Größen, Farben, Varianten und Personalisierungen anbieten.
- [ ] Namen, Initialen und Rückennummern erfassen.
- [ ] Sammelbestellungen je Mannschaft oder Bestellzeitraum organisieren.
- [ ] Bestellungen bezahlen lassen und Lieferantenaufträge zusammenstellen.
- [ ] Bestellstatus von „eingegangen“ bis „abgeholt“ verfolgen.
- [ ] Wareneingang, Ausgabe und Abholbestätigung dokumentieren.
- [ ] Mitglieder über eingetroffene Bestellungen informieren.
- [ ] Fehlmengen, Reklamationen und Erstattungen bearbeiten.
- [ ] Vereinsfinanzierte Ausrüstung von privat gekauften Artikeln unterscheiden.

## 28. Vereinsheim, Gastronomie und Verkauf

Dieses Modul wäre nur für Vereine mit entsprechendem Betrieb nötig.

- [ ] Vereinsheim und Bewirtungsbereiche reservieren.
- [ ] Getränke, Lebensmittel und Verkaufsartikel verwalten.
- [ ] Einkauf, Lagerbestände, Pfand und Schwund dokumentieren.
- [ ] Dienstpläne für Theke, Küche und Reinigung führen.
- [ ] Preislisten und Veranstaltungssortimente verwalten.
- [ ] Verkäufe über ein geeignetes Kassensystem anbinden.
- [ ] Kassenübergaben, Tagesabrechnungen und Differenzen dokumentieren.
- [ ] Raumvermietung mit Bewirtung und zusätzlichen Leistungen kombinieren.
- [ ] Lieferanten und wiederkehrende Bestellungen verwalten.

# F. Sicherheit, Auswertung und Automatisierung

## 29. Schutzkonzept, Vorfälle und Versicherungen

- [ ] Schutzkonzept, Verhaltensregeln und Ansprechpartner zugänglich machen.
- [ ] Vertrauliche Beschwerden und Vorfallmeldungen entgegennehmen.
- [ ] Fälle nur einem eng begrenzten berechtigten Personenkreis zeigen.
- [ ] Bearbeitungsstände, Maßnahmen und Nachverfolgung dokumentieren.
- [ ] Schulungen und erforderliche Überprüfungen nachhalten.
- [ ] Bei sensiblen Nachweisen möglichst Prüfstatus und Wiedervorlage statt unnötiger Dokumentkopien speichern.
- [ ] Unfälle mit erforderlichen Angaben und Unterlagen dokumentieren.
- [ ] Versicherungsverträge, Ansprechpartner und Meldefristen verwalten.
- [ ] Notfallinformationen für berechtigte Verantwortliche bereitstellen.
- [ ] Beschwerden über Mitglieder, Trainer oder Funktionsträger unabhängig zuweisen können.

Gesundheitsangaben gehören nicht in frei zugängliche Mitgliedsnotizen. Solche Daten erfordern eine gesonderte datenschutzrechtliche Prüfung und besonders begrenzte Verarbeitung. ([Datenschutz Rheinland-Pfalz][5])

## 30. Statistiken und Berichte

- [ ] Mitgliederentwicklung, Eintritte und Austritte auswerten.
- [ ] Tatsächliche Personenzahlen und Mehrfachzugehörigkeiten getrennt darstellen.
- [ ] Altersgruppen, Abteilungen und Mitgliedschaftsarten vergleichen.
- [ ] Trainingsbeteiligung, Kursauslastung und Wartelisten analysieren.
- [ ] Einnahmen, Beitragsrückstände und Budgetabweichungen darstellen.
- [ ] Trainerstunden und ehrenamtliche Leistungen auswerten.
- [ ] Sportstättenbelegung und Materialnutzung anzeigen.
- [ ] Berichte für Vorstand, Mitgliederversammlung und Fördergeber erstellen.
- [ ] Verbandsmeldungen mit passenden Stichtagen vorbereiten.
- [ ] Eigene Berichte, Filter und PDF- beziehungsweise Excel-Exporte ermöglichen.

## 31. Aufgaben, Formulare und automatische Abläufe

- [ ] Aufgaben mit Verantwortlichen, Fristen und Status verwalten.
- [ ] Projekte wie „Sommerfest“ oder „Neue Jugendmannschaft“ organisieren.
- [ ] Wiederkehrende Aufgaben und Checklisten erstellen.
- [ ] Eigene Formulare für Anträge, Schäden, Bestellungen und Erstattungen bauen.
- [ ] Freigabeabläufe mit mehreren zuständigen Personen konfigurieren.
- [ ] Erinnerungen und Eskalationen bei überfälligen Vorgängen auslösen.
- [ ] Automatische Abläufe vor ihrer Aktivierung testen.
- [ ] Fehlerhafte Automatisierungen sichtbar machen und kontrolliert erneut ausführen.
- [ ] Kritische Entscheidungen weiterhin durch Menschen bestätigen lassen.

**Beispiele für solche Regeln:**

- [ ] Ein Antrag wird genehmigt → Zugang, Mitgliedsakte, Tarif und Begrüßung vorbereiten.
- [ ] Ein Kursplatz wird frei → der nächsten berechtigten Person eine befristete Buchungsmöglichkeit anbieten.
- [ ] Eine Lizenz läuft aus → Trainer und zuständige Leitung erinnern.
- [ ] Ein Gerät wird beschädigt gemeldet → Reservierung prüfen und Reparaturaufgabe erstellen.
- [ ] Eine Mitgliedschaft endet → Zugang, Materialrückgabe und offene Vorgänge überprüfen.

## 32. Schnittstellen und Datenübernahme

- [ ] Mitglieder und weitere Stammdaten aus Excel oder CSV importieren.
- [ ] Importvorschau, Feldzuordnung und Fehlerberichte anbieten.
- [ ] Bankbewegungen und Zahlungsdienstleister anbinden.
- [ ] Buchhaltungs- und Steuerberatungsexporte bereitstellen.
- [ ] Kalender, Website und E-Mail-Versand verbinden.
- [ ] Daten mit Verbands- und Wettkampfsystemen austauschen.
- [ ] Zutrittssysteme, Ticketing, Kassen und Zeiterfassung optional anbinden.
- [ ] Eine dokumentierte Schnittstelle für andere Anwendungen anbieten.
- [ ] Synchronisationsfehler, Dubletten und widersprüchliche Änderungen behandeln.
- [ ] Einen vollständigen Datenexport für einen späteren Systemwechsel ermöglichen.

**Dabei würde ich keine automatische Verbandsanbindung voraussetzen:** Jede Integration muss anhand einer tatsächlich verfügbaren und zulässigen Schnittstelle geplant werden.

## 33. Datenschutz und IT-Sicherheit

- [ ] Verarbeitungstätigkeiten, Zwecke und Verantwortlichkeiten dokumentieren.
- [ ] Datenschutzhinweise und erforderliche Einwilligungen versionieren.
- [ ] Einwilligungen getrennt nach Zweck erfassen und widerrufbar machen.
- [ ] Auskunfts-, Berichtigungs- und Löschanfragen bearbeiten.
- [ ] Aufbewahrungsfristen und Löschregeln nach Datenart einstellen.
- [ ] Auftragsverarbeitungsverträge und eingesetzte Dienstleister verwalten.
- [ ] Mehrfaktor-Anmeldung insbesondere für sensible Rollen anbieten.
- [ ] Datenübertragung, Speicherung und Sicherungen angemessen schützen.
- [ ] Zugriffe und wichtige Änderungen nachvollziehbar protokollieren.
- [ ] Sicherheitsvorfälle mit Zuständigkeiten und Bearbeitungsfristen dokumentieren.

**Eine Einwilligung ist dabei nicht die pauschale Grundlage für jede Mitgliederverwaltung.** Die App sollte unterschiedliche Rechtsgrundlagen und Zwecke abbilden, statt alles hinter einem einzigen Datenschutz-Häkchen zusammenzufassen. ([Datenschutz Baden-Württemberg][6])

## 34. Bedienbarkeit, Betrieb und Zuverlässigkeit

- [ ] Auf Smartphone, Tablet und Computer gut bedienbar sein.
- [ ] Eine einfache Oberfläche für Mitglieder und eine erweiterte Verwaltungsansicht anbieten.
- [ ] Barrierearme Bedienung, Tastaturzugriff und verständliche Fehlermeldungen berücksichtigen.
- [ ] Mehrere Sprachen und anpassbare Bezeichnungen unterstützen.
- [ ] Ausgewählte Funktionen bei schlechter Verbindung ermöglichen und Änderungen sicher synchronisieren.
- [ ] Regelmäßige Datensicherungen und geprüfte Wiederherstellung vorsehen.
- [ ] Updates, Fehler und fehlgeschlagene Hintergrundvorgänge überwachen.
- [ ] Hilfe, Einführung und einen Supportbereich bereitstellen.
- [ ] Massenänderungen vorab prüfen und unbeabsichtigte Änderungen abfangen.
- [ ] Testdaten und Testumgebungen von echten Mitgliederdaten trennen.

# G. Optionale Erweiterungen

## 35. KI-Unterstützung

KI würde ich **als Unterstützung und nicht als Voraussetzung für die Vereinsverwaltung** einbauen.

- [ ] Nachrichten, Einladungen und Vereinsberichte als Entwurf erstellen.
- [ ] Lange Dokumente und Sitzungsunterlagen zusammenfassen.
- [ ] Inhalte übersetzen und sprachlich vereinfachen.
- [ ] Fragen zum freigegebenen Vereinshandbuch beantworten.
- [ ] Belegdaten auslesen und Buchungsvorschläge vorbereiten.
- [ ] Termin- und Ressourcenplanung mit Vorschlägen unterstützen.
- [ ] Auffällige oder widersprüchliche Datensätze zur Prüfung markieren.
- [ ] Trainingsideen aus einer freigegebenen Übungsbibliothek zusammenstellen.

Dabei sollten Berechtigungen auch für die KI gelten. Personenbezogene Daten dürften nicht unkontrolliert an externe Dienste weitergegeben werden; Buchungen, Zahlungen, Veröffentlichungen und sensible Entscheidungen bräuchten eine passende menschliche Freigabe.

## 36. Plattform für mehrere Vereine und öffentliches Sportnetzwerk

Soll daraus ein Produkt für viele Vereine werden, kämen weitere Funktionen hinzu:

- [ ] Mehrere Vereine mit strikt getrennten Daten verwalten.
- [ ] Vereinsindividuelle Module, Einstellungen und Erscheinungsbilder anbieten.
- [ ] Personen einen kontrollierten Wechsel zwischen ihren Vereinen ermöglichen.
- [ ] Softwaretarife, Testzugänge, Abonnements und Abrechnung verwalten.
- [ ] Supportzugriffe ausdrücklich freigeben und protokollieren.
- [ ] Öffentliche Vereins-, Trainer- und Sportlerprofile optional anbieten.
- [ ] Trainersuche, Probetrainings und freie Mannschaftsplätze veröffentlichen.
- [ ] Vereinsübergreifende Veranstaltungen und Kooperationen organisieren.
- [ ] Sponsoren und Vereine miteinander in Kontakt bringen.
- [ ] Öffentliche Profile und interne Mitgliederakten strikt voneinander trennen.

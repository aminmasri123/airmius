# AIRMIUS Produkt-Readiness-Audit

Stand: 2026-07-20

## Entscheidung

AIRMIUS ist als technisch funktionsreiche, geschlossene Pilot-Beta einsetzbar, aber noch nicht fuer einen oeffentlichen Produktivstart oder eine breite bezahlte Vermarktung startklar.

Technische Basis: **gruen**. Produkt-, Lokalisierungs-, Provider-, Realgeraete-, Legal- und Betriebsfreigabe: **nicht vollstaendig**.

## Nachweise dieses Audits

- `php artisan test --compact`: 374 bestanden, 7 uebersprungen, 3.853 Assertions.
- `npm run build`: erfolgreich, 976 Module transformiert.
- Web-Sprachdateien: 964 deutsche Referenzschluessel; EN, FR und AR nach Reparatur mit exakt gleicher Schluesselmenge. Zusaetzlich besitzen alle vier Kataloge 3.540 identische `auto`-UI-Ausgangsschluessel.
- Arabisch vor der Reparatur: 935 von 962 Top-Level-Eintraegen waren betroffen. Nach lokaler Wiederherstellung: 964 Referenzschluessel, 61.070 arabische Zeichen, keine Fragezeichenfolgen/Ersatzzeichen und keine Platzhalterabweichungen.
- Statisches Vue-UI-Inventar: 3.003 unterschiedliche sichtbare Texte aus Seiten, Layouts, Komponenten und statischen Textattributen; nach der Umsetzung fehlen davon in EN, FR und AR jeweils 0.
- Flutter-Realgeraet-Evidence fuer Android/iOS ist laut Release-Dokumentation offen.
- Juristischer Sign-off ist laut `docs/LEGAL_REVIEW_PACK.md` offen.

## Zielgruppenbewertung

### Sportler: bedingt beta-tauglich

Vorhanden sind Registrierung, Profil/Sport-CV, Feed, Freunde, Chat, Events, Training, Dateien, Benachrichtigungen, Motivation/Gamification-Grundlagen und Mitgliedschaftsanfragen. Das ist eine brauchbare Pilotbasis.

Vor Verkauf fehlen vor allem ein klarer taeglicher Kernnutzen, verlaessliche native Push-Zustellung, echte Fitnessanbieter-Synchronisation, belastbare Leistungsentwicklung und ein vollstaendig getesteter Minderjaehrigen-/Elternflow. Der Funktionsumfang ist breit, aber das Nutzenversprechen fuer den einzelnen Sportler noch nicht scharf genug.

### Trainer: bedingt beta-tauglich

Vorhanden sind Teamverwaltung, Trainingsplaene/-logs, Anwesenheit, Events, Kommunikation und Rollen. Damit kann ein Trainer-Pilot arbeiten.

Verkaufsentscheidend fehlen wiederkehrende Trainingsserien, Vorlagen/Uebungsbibliothek, Trainer-zu-Sportler-Feedback, belastbare Entwicklungs-/Belastungsansichten, Verletzungs-/Pausenstatus, QR-Check-in und ein klarer Wochenworkflow. Diese Luecken verhindern aktuell eine starke Positionierung als Trainerwerkzeug.

### Vereine: beste Produktbasis, aber noch nicht produktionsreif

Vorhanden sind Verein/Teams, Rollen, Mitgliederantraege, Import, externe Mitglieder, Rechnungen/Zahlungsstatus, SEPA-/Bankabgleich-Grundlagen, Dateien, Events, Sponsoren, Audit-Grundlagen und Plan-Gates. Hier liegt der staerkste kurzfristige Kundennutzen.

Vor breitem Verkauf fehlen bzw. brauchen Abschluss: Familien-/Beitragsgruppen und Ermaessigungen, vollstaendiger Ein-/Austritts-Lifecycle, digitale Unterschriften, wiederkehrende Provider-Abos, automatische Rueckerstattungen/Auszahlungen, echte Push-/Digest-Kommunikation, Umfragen/Lesebestaetigungen sowie verifizierte DATEV-/SEPA-Produktionsablaeufe. Migration, Support und Datenqualitaet muessen als Onboardingprozess operationalisiert werden.

## Lokalisierung und Rueckuebersetzung

Die deutsche Datei `resources/js/lang/de.json` ist die alleinige fachliche Referenz. Der geforderte Prozess ist:

1. Deutsch nach EN/FR/AR uebersetzen.
2. Platzhalter, Zahlen, URLs, Marken- und Sportbegriffe unveraendert pruefen.
3. Jede Zieluebersetzung unabhaengig nach Deutsch rueckuebersetzen.
4. Bedeutung gegen den deutschen Originaltext vergleichen; keine Ziel-zu-Ziel-Uebersetzung verwenden.
5. Danach manuelle Sichtpruefung im echten UI, bei Arabisch zusaetzlich RTL, Zahlen, Icons, Tabellen, Dialoge und abgeschnittene Texte.

Aktueller Befund nach der Umsetzung:

- Schluesselparitaet ist repariert: DE/EN/FR/AR jeweils 964 Schluessel.
- Das automatische sichtbare UI-Inventar hat in DE/EN/FR/AR jeweils 3.540 Schluessel; das statische Audit findet unter 3.003 aktuell sichtbaren Vue-Texten jeweils 0 fehlende EN-/FR-/AR-Uebersetzungen.
- Die defekten franzoesischen und arabischen Mojibake-Schluessel wurden auf die deutschen Originalschluessel zurueckgefuehrt.
- Platzhalter-, HTML-Entitaets-, E-Mail-/Pfad- und Korruptionspruefungen sind gruen. Arabisch enthaelt keine `????`- oder Ersatzzeichenfolgen mehr; die Sportartenliste ist ebenfalls repariert.
- Ein unabhaengiger lokaler Rueckuebersetzungsversuch wurde verworfen, weil das einzige vorhandene 4B-Modell Zieltexte trotz deutscher Zielanweisung teilweise unveraendert kopierte. Dieser Versuch ist kein gueltiger Semantiknachweis. Google Translate war trotz Einwilligung durch die Ausfuehrungsumgebung gesperrt.
- Semantische Vollstaendigkeit bleibt deshalb trotz vollstaendiger technischer Abdeckung nicht abschliessend bewiesen. Die gefundenen Ausreisser (unter anderem `Entfolgen`, `Anrede`, `XP`, SEPA-Begriffe, E-Mail-Beispiele und Bildpfade) wurden gezielt korrigiert; eine fachliche Muttersprachlerabnahme und echte RTL-Sichtpruefung bleiben erforderlich.
- Dynamisch in JavaScript zusammengesetzte Texte, Laufzeitdaten vom Backend sowie responsives Abschneiden koennen durch den statischen Vue-Audit nicht vollstaendig bewiesen werden.

## Priorisierte fehlende Funktionen und Abschlussarbeiten

### P0 – vor jeder oeffentlichen Beta

- Arabische Fachstichprobe per Rueckuebersetzung protokollieren und RTL-Screens manuell abnehmen (technische Textreparatur ist abgeschlossen).
- Den neuen sichtbaren UI-Inventar-Audit als verpflichtendes CI-Gate ausfuehren und dynamisch zusammengesetzte Laufzeittexte weiter in explizite i18n-Schluessel ueberfuehren.
- Android-/iOS-Realgeraetetests fuer Login, Push, Upload, Deep Links, Chat und Hintergrundverhalten abschliessen.
- Echte Mail-, Queue-, Reverb-, Storage-, Backup-/Restore- und Monitoring-Probe auf Staging.
- Stripe/PayPal-Sandbox-End-to-End inklusive Webhook, Abbruch, Doppelzustellung, Rueckerstattung und Rechnungsabgleich.
- Legal-/Datenschutz-/Jugendschutz-Sign-off und reale Pflichtangaben.
- Manueller rollenbasierter Smoke-Test mit Sportler, Trainer, Verein-Admin und Elternteil.

### P1 – fuer ein verkaufbares Vereins-/Trainerprodukt

- Wiederkehrende Trainingstermine, Vorlagen, Uebungsbibliothek und Entwicklungsfeedback.
- Beitragsgruppen, Familienbeitraege, Ermaessigungen, Austritt/Kündigung und digitale Zustimmung/Unterschrift.
- Push, E-Mail-Digest, Ankuendigungen, Lesebestaetigungen und Umfragen.
- Sauberes Vereins-Onboarding: Importvorschau, Dubletten, Mapping, Fehlerkorrektur, Testmigration und persoenliche Begleitung.
- Rollen-/Berechtigungseditor pro Verein mit nachvollziehbarem Audit-Log.
- Support-/Hilfecenter mit Tickets, SLA, Statuskommunikation und In-App-Onboarding.

### P2 – Wachstum und Skalierung

- Oeffentliche SEO-Seiten fuer Vereine, Sportarten, Events und Staedte, SSR und strukturierte Daten.
- Verifiziertes Anbieter-/Sponsor-Onboarding, Steuerdaten, Auszahlungen und erweitertes Ads-Reporting.
- Finale Google-Fit-/Garmin-/Mi-Fitness-Anbindungen.
- Produktanalytics mit Consent: Aktivierung, Retention, Team-/Vereinsnutzung, Conversion und Abwanderungsgruende.

## Empfohlene Markteintrittspositionierung

Nicht gleichzeitig alle Module und Zielgruppen verkaufen. Der glaubwuerdigste Einstieg ist **Vereinsbetrieb plus Traineralltag** fuer wenige Pilotvereine; Sportler erhalten die kostenlose Begleit-App.

- Startsegment: kleine und mittlere Vereine mit Excel-Mitgliederlisten und hohem Kommunikationsaufwand.
- Kaufversprechen: weniger Verwaltungszeit, klare Beitraege/Zahlungsstaende, Teamkommunikation und Trainingsorganisation an einem Ort.
- Pilotangebot: Datenmigration, Einrichtung, Schulung und 6–8 Wochen begleitete Einfuehrung.
- Erfolgsnachweise: eingesparte Verwaltungsstunden, aktive Mitgliederquote, Trainingsrueckmeldungen, offene Zahlungen und Supportaufwand.
- Erst nach wiederholbar erfolgreichem Onboarding Preisplaene, Marketplace, Ads und weitere Zielgruppen offensiv skalieren.

## Go/No-Go

- Geschlossene Pilot-Beta mit deutschsprachigen Vereinen: **Go mit Auflagen**.
- Bezahlte breite Beta in DE: **No-Go bis P0 abgeschlossen**.
- Mehrsprachiger Launch inklusive Arabisch: **No-Go bis fachliche Rueckuebersetzungs- und RTL-Sichtpruefung protokolliert sind**.
- App-Store-/Play-Store-Public-Launch: **No-Go**, solange Realgeraete-, Store- und Legal-Evidence fehlen.

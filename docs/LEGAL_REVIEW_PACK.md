# AIRMIUS Legal Review Pack

Stand: 2026-10-15

Dieses Dokument ist ein technisches Review-Paket fuer die juristische Freigabe. Es ersetzt keine Rechtsberatung und behauptet keine anwaltliche Finalfreigabe.

## Oeffentliche Rechteseiten

- Impressum: `/impressum`, Route `legal.imprint`, Controller `LegalPageController::imprint`
- Datenschutz: `/datenschutz`, Route `policy.show`, Controller `LegalPageController::privacy`
- Kontoloeschung: `/konto-loeschen`, Route `legal.account-deletion`, Controller `LegalPageController::accountDeletion`
- Vereinsloeschung: `/verein-loeschen`, Route `legal.club-deletion`, Controller `LegalPageController::clubDeletion`
- Partielle Datenloeschung: `/daten-loeschen`, Route `legal.data-erasure`, Controller `LegalPageController::dataErasure`
- AGB: `/agb`, Route `terms.show`, Controller `LegalPageController::terms`
- Community-Richtlinien: `/community-richtlinien`, Route `legal.community`, Controller `LegalPageController::community`
- Jugendschutz: `/jugendschutz`, Route `legal.minors`, Controller `LegalPageController::minors`
- Cookies: `/cookies`, Route `legal.cookies`, Controller `LegalPageController::cookies`
- Widerruf: `/widerruf`, Route `legal.withdrawal`, Controller `LegalPageController::withdrawal`
- Kontakt und Melden: `/kontakt-und-melden`, Route `legal.reporting`, Controller `LegalPageController::reporting`

## Pflichtdaten vor Livegang

- `LEGAL_PROVIDER_NAME`
- `LEGAL_STREET`
- `LEGAL_CITY`
- `LEGAL_COUNTRY`
- `LEGAL_EMAIL`
- `LEGAL_SUPPORT_EMAIL`
- `LEGAL_PRIVACY_EMAIL`
- `LEGAL_LEGAL_EMAIL`
- `LEGAL_PHONE`
- `LEGAL_REPRESENTATIVE`
- `LEGAL_REGISTER`
- `LEGAL_VAT_ID`
- `LEGAL_SUPERVISORY_AUTHORITY`
- `LEGAL_CONTENT_RESPONSIBLE`

Zusaetzlich muessen die Billing-/Rechnungsdaten in den Admin-Settings bzw. `config/airmius.php` zu den Impressumsdaten passen.

## Unterlagen im Repository

- `docs/legal/cloudflare_customer_DPAv3-German.pdf`
- `docs/legal/Hostinger DPA-AVV Anhang zur Datenverarbeitung.pdf`
- `docs/DATA_PROCESSING_PROVIDERS.md`
- `docs/SECURITY_REVIEW.md`
- `docs/USER_CASES.md`

## Juristische Pruefpunkte

- Anbieteridentitaet, Registerangaben, USt-ID, Vertreter und ladungsfaehige Anschrift final bestaetigen.
- Datenschutztext gegen tatsaechlich eingesetzte Anbieter, Regionen, AVV/DPA und internationale Transfers pruefen.
- Minderjaehrigenkonzept gegen Art. 8 DSGVO, nationales Recht und Plattformfunktionen pruefen.
- AGB gegen reale Leistungsbeschreibung, Rollen von Vereinen, Trainern, Sportlern, Eltern, Marketplace-Anbietern und Sponsoren pruefen.
- Widerrufsbelehrung fuer digitale Dienste, Add-ons, Marketplace, Ads und Website-/Agenturleistungen finalisieren.
- Community-Richtlinien und Meldeprozesse gegen DSA-/Moderationspflichten pruefen.
- Cookie-/Tracking-Seite gegen die tatsaechlich aktiven Cookies, Ads-Signale, Conversion-Messung und Consent-Flows pruefen.
- Auftragsverarbeiterliste gegen produktive Anbieter und Subprozessoren aktualisieren.
- Konto- und Vereinsloeschung getrennt gegen Art. 17 und Art. 28 Abs. 3 lit. g DSGVO pruefen: Wer ist fuer welche Daten Verantwortlicher, und welche Vereinsdaten darf Airmius nach Vertragsende noch aus eigenem Rechtsgrund speichern?
- Bei geloeschtem Mitgliedskonto bleiben offene Vereins-Inventarausleihen erhalten, die direkte Kontoverknuepfung wird geloest und fuer aktive Rueckgabe-/Verlustfaelle ein Namensnachweis gespeichert; ausstehende Anfragen werden storniert. Bei unkomplizierter bestaetigter Rueckgabe ohne offene Kaution, Rechnung, Mietpreis, Vertrag oder Schaden wird der Namensnachweis automatisch entfernt. Fuer Verlustfaelle und weiterhin offene Ansprueche Rechtsgrundlage, Zugriff, Zweckbindung, erforderliche Aufbewahrungsdauer und spaetere Loeschung mit Vereinsverantwortlichen und Datenschutzberatung pruefen.
- Pruefen, ob das Finanzdatenpaket und die Eigentuemerklaerung als Uebergabeprozess genuegen. Das ZIP ist kein vollstaendiges oder GoBD-konformes Langzeitarchiv; die Verantwortung des Vereins fuer erforderliche Originalunterlagen muss eindeutig beschrieben sein.
- Produktentscheidung vom 15.10.2026: Der Verein archiviert seine erforderlichen Originalunterlagen selbst; Airmius bietet keinen eigenen revisionssicheren Vereinsarchivdienst an. Die technische Export-/Supportfreigabe ersetzt weder die Archivpruefung durch den Verein noch die rechtliche Freigabe des Verfahrens.
- Fuer Airmius-eigene Rechnungen und Vereinsunterlagen die tatsaechlich anwendbaren Kategorien und Fristen aus § 147 AO, § 257 HGB und § 14b UStG getrennt bewerten. Die Leitfaeden nennen grundsaetzlich zehn Jahre fuer Buecher/Abschluesse, acht fuer Buchungsbelege/Unternehmerrechnungen und sechs fuer weitere erfasste Unterlagen; Jahresendbeginn, Uebergangsrecht, Verlaengerungen und Anwendbarkeit auf den konkreten Verein sind vor Freigabe fachlich zu pruefen. Eine pauschale Zehnjahresfrist fuer alle Daten waere falsch.
- Fuer Airmius-eigene Belege ist derzeit kein automatischer Fristablauf-Loechs- oder Anonymisierungslauf erkennbar. Vor Produktionsfreigabe eine verantwortliche Stelle, dokumentierte Frist-/Ausnahmepruefung, Zugriffsbeschraenkung und einen nachweisbaren anschliessenden Bereinigungsprozess festlegen. Die oeffentliche Kontoseite sagt diesen fehlenden Automatismus jetzt ausdruecklich an.
- Manuelle Art.-17-DSGVO-Ersuchen duerfen nicht an fehlendem Kontozugriff oder einer verlorenen Konto-E-Mail scheitern. Die oeffentliche Kontoseite beschreibt nun alternative Kontaktaufnahme, verhaeltnismaessige Identitaetspruefung nach Art. 12 Abs. 6 sowie Antwort und moegliche Verlaengerung nach Art. 12 Abs. 3 und 4 DSGVO. Vor Freigabe betrieblich nachweisen: erreichbares Datenschutzpostfach, dokumentierter Eingang, zustaendige Person, Fristenueberwachung, Entscheidung und Antwortnachweis. Die Seite selbst ersetzt diesen Prozess nicht. [EDPB-Leitfaden](https://www.edpb.europa.eu/sme/be-compliant/respect-individuals-rights_en).
- 30-taegige Bedenkzeit, E-Mail-Code, Besitzerwechsel, Supportfreigabe, Ausnahmen bei gesetzlichen Pflichten und Daten anderer Personen gegen die reale technische Loeschung pruefen.
- Produktions-Backups einschliesslich Hostinger und Objektspeicher verifizieren: maximale Aufbewahrung, Zugriffsbeschraenkung, Wiederherstellungsabgleich und Information auf beiden Loeschseiten muessen zur gelebten Praxis passen.
- Nutzerbestaetigung vom 15.10.2026: Die maximale Aufbewahrung aller produktiven Backups einschliesslich Hostinger, R2, Versionen und manueller Kopien ist noch nicht verifiziert. Keine feste Loeschfrist fuer Sicherungen behaupten oder den Backup-Punkt als abgenommen markieren.
- `BACKUP_RETENTION_DAYS` wird derzeit lediglich ins Backup-Manifest geschrieben; `airmius:backup-database` und der Scheduler loeschen keine abgelaufenen Kopien. Vor Freigabe die tatsaechliche Lifecycle-Regel und Versionen/Objektsperren im Bucket sowie Hostinger- und manuelle Kopien nachweisen. Zusaetzliche Monats-/Jahresvollkopien nur nach dokumentierter Datenschutz- und Restore-Pruefung; sonst waere die genannte Frist irrefuehrend.
- Die lokale R2-Konfiguration mit `APP_ENV=production` lieferte am 15.10.2026 unter `backups/` keine Objekte; ein lesender Abruf der Lifecycle-Regeln scheiterte mit `AccessDenied`. Das ist kein Beleg fuer den Zustand des tatsaechlichen Produktionshosts. Die Freigabe erfordert eine getrennte Pruefung des dortigen Schedulers, Ziel-Buckets, letzten erfolgreichen Backups, Lifecycle-Regeln und etwaiger weiterer Kopien.
- Cloudflare-Dashboard am 15.10.2026 lesend geprueft: Im einzigen angezeigten R2-Bucket `airmius-storage` ergab die Praefixsuche `backups/` keine Objekte. Als einzige Objekt-Lebenszyklusregel war das Abbrechen unvollstaendiger Uploads nach sieben Tagen sichtbar; es gab keine Bucket-Sperrregel. Die benutzerdefinierte Domain `cdn.airmius.com` hatte aktiven oeffentlichen Zugriff. Das belegt weder eine funktionierende Backup-Kette noch die maximale Aufbewahrung bei Hostinger oder in manuellen Kopien. Vor Freigabe Ziel-Bucket und letzte erfolgreiche Sicherung auf dem Produktionshost sowie Zugriffsgrenzen fuer private Uploads verifizieren.
- Deployment-Abnahme: Am 15.10.2026 lieferten die isolierten lokalen Seiten `/verein-loeschen` und `/konto-loeschen` vollstaendige Inhalte. Auf `airmius.com` lieferte `/verein-loeschen` dagegen 404 und `/konto-loeschen` noch die alte Kurzfassung. Nach Rechts-, Backup- und AVV-Freigabe beide oeffentlichen Produktivseiten, Web-Footer und App-Gastzugang sowie den tatsaechlichen Loeschablauf erneut pruefen; lokale Tests ersetzen diese Abnahme nicht.
- Der lesende Smoke-Check `php scripts/check-public-deletion-guides.php "$BASE_URL"` prueft fuer beide Pfade HTML und DE/EN/FR/AR-JSON ohne Anmeldung. Die isolierte Vorschau bestand am 15.10.2026 mit 10/10; `https://airmius.com` bestand nur die alte Konto-HTML-Seite (1/10). Erst nach Freigabe und Deployment gegen die tatsaechliche Produktiv-URL erneut ausfuehren. Ein gruenes HTTP-Ergebnis ersetzt keine rechtliche, visuelle oder reale Datenloeschungs-Abnahme.
- Die App buendelt beide vollstaendigen Loeschleitfaeden in DE/EN/FR/AR und bevorzugt bei Verfuegbarkeit die aktuelle oeffentliche Web-Fassung. Bei fehlender oder unvollstaendiger Serverantwort zeigt sie den gebuendelten Stand mit einem Hinweis auf moegliche Veraltung. Die Assets werden mit `node scripts/export-mobile-deletion-guides.mjs "$BASE_URL"` erzeugt; `node scripts/export-mobile-deletion-guides.mjs "$BASE_URL" --check` muss vor jedem App-Release gegen die freigegebene Backend-Version bestehen. Eine spaetere Web-Aenderung aktualisiert bereits installierte Offline-Kopien nicht automatisch. Deshalb rechtlich wesentliche Aenderungen auch ueber den App-Release-/Kommunikationsprozess behandeln.

## Mehrsprachige Freigabe

- Deutsch ist die einzige in Code und Katalog gepflegte Ausgangsfassung; `localized-legal-content.v1` liefert EN, FR und AR bereits serverseitig aus und faellt bei fehlender Abdeckung sicher auf Deutsch zurueck.
- Fuer jede der elf Seiten sind Titel, Abschnitte, Hinweise und Aktionen in EN/FR/AR gegen die deutsche Quelle zu pruefen. Zahlen, Daten, DSGVO-/DDG-Verweise, Pfade und Anbieter-/Produktnamen duerfen sich nicht veraendern.
- Anbietername, Anschrift, Register-, Steuer-, Telefon- und E-Mail-Angaben werden als geschuetzte Werte unveraendert eingesetzt; nur der umgebende Rechtstext wird lokalisiert.
- Arabisch ist zusaetzlich visuell in RTL auf Mobiltelefon und Desktop zu pruefen. Navigation, Fokusreihenfolge, Links, gemischte E-Mail-/Telefonwerte und Satzzeichen muessen lesbar bleiben.
- `translationComplete=true` bestaetigt nur technische Katalogabdeckung. Es ersetzt weder juristische noch muttersprachliche Freigabe; das externe `legal_release_approval` bleibt bis zu dieser Pruefung offen.
- Die Entwuerfe werden mit `scripts/translate_visible_ui_offline.py --source-scope legal --retranslate-identical` ueber das lokal installierte Modell erzeugt. Es werden nur statische deutsche Controller-Bausteine verarbeitet, keine konfigurierten Anbieter-, Adress-, Kontakt- oder Nutzerdaten und kein externer Uebersetzungsdienst.
- `scripts/merge_visible_ui_translations.php` schreibt daraus die vier kleinen, serverseitigen Kataloge unter `resources/legal/`. Lokale Fortschrittsdateien bleiben ignoriert; nur die technisch auditierten Kataloge gehoeren ins Release.

## Sign-off

- Interne Geschaeftsfuehrungs- und Produktfreigabe: Amin Masri, Geschaeftsfuehrer, `a.masri@airmius.com`
- Juristische Datenschutzpruefung durch: noch keine externe pruefende Person oder Kanzlei benannt
- Datum der Freigabe: offen
- Freigegebene Version/Commit: offen
- Offene Auflagen: offen

Die beiden Loeschseiten sind lokal und in App-Tests erreichbar. Am 15.10.2026 zeigte der oeffentliche Host fuer `/verein-loeschen` noch 404 und fuer `/konto-loeschen` eine aeltere Fassung. Eine lokale technische Pruefung ist deshalb **keine** produktive oder juristische Freigabe.

Die Freigabe wird versionsgebunden in der Produktionsumgebung hinterlegt:

```dotenv
LEGAL_APPROVED_BY="Name/Kanzlei"
LEGAL_APPROVED_AT=2026-08-09
LEGAL_APPROVED_VERSION="2026-08-09"
LEGAL_EXPECTED_VERSION="2026-08-09"
LEGAL_APPROVAL_CONDITIONS="Keine"
```

`LEGAL_APPROVED_VERSION` und `LEGAL_EXPECTED_VERSION` müssen identisch sein und dem aktuellen versionierten Release-Vertrag entsprechen. `LEGAL_APPROVED_AT` muss ein gültiges, nicht zukünftiges Datum im Format `YYYY-MM-DD` sein. Bei jeder rechtlich relevanten Änderung an Produkt, Tracking, Anbietern, Minderjährigen-, Zahlungs-, Marketplace-, Recruiting-, KI-, Gesundheits- oder Standortprozessen ist eine neue Freigabe erforderlich.

## Technische Verifikation

- `php artisan test tests/Feature/LegalPagesTest.php`
- `php artisan test tests/Feature/LocalizedLegalGuestPagesTest.php`
- `php artisan test --compact`
- `npm run build`
- `php artisan airmius:audit-legal-readiness` (muss in der Release-Pipeline Exit-Code 0 liefern)
- `php artisan airmius:audit-governance --json --strict` (bleibt bis zu Legal-, DPIA- und unabhängiger Pentest-Freigabe rot)

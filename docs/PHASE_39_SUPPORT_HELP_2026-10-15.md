# Phase 39: Support und Hilfe

Stand: 2026-10-15

| ID | Abdeckung | Ergebnis / Restpruefung |
| --- | --- | --- |
| T39-01 | Der Supportbereich ist im Web unter `/support` und in der App fuer jedes angemeldete Konto erreichbar. Die interne Arbeitsliste erscheint nur mit Supportrecht. | Navigation mit Sportler, Trainer, Vereinsrolle und Sponsor manuell gegenpruefen. |
| T39-02 | Betreff, Beschreibung, Kategorie, Prioritaet und optionaler Vereinskontext werden serverseitig validiert und als genau ein Ticket gespeichert. Die Oberflaechen zeigen eine Bestaetigung und die Ticketnummer. | Timeout und Wiederholung mit realer Netzwerkdrosselung pruefen. |
| T39-03 | Eigene Tickets zeigen Status, Kategorie, Fristen, Vereinskontext und einen geschuetzten Nachrichtenverlauf. | Web/App mit demselben Konto vergleichen. |
| T39-04 | Support und anfragende Person koennen im Ticketverlauf antworten. Supportantworten benachrichtigen nur den Antragsteller; Antworten des Antragstellers benachrichtigen zugewiesene oder verantwortliche Bearbeitende. | Push-Zustellung auf zwei Geraeten pruefen. Anhaenge sind bewusst noch nicht angeboten und werden in der UI entsprechend benannt. |
| T39-05 | Leere, zu kurze und ungueltige Angaben liefern Validierungsfehler. Fremde Ticket-IDs sind fuer Details und Antworten gesperrt; geschlossene Tickets nehmen keine Nachricht an. | Offline-Abbruch auf einem echten Geraet pruefen. |
| T39-06 | Persoenliche Tickets haben keinen Verein. Vereinsbezogene Tickets speichern Verein, Abteilung und Team nur innerhalb einer gueltigen Mitgliedschaft; Support-Arbeitslisten bleiben mandantenbegrenzt. | Wechsel A/B mit zwei Vereinen manuell pruefen. |
| T39-07 | `Sicherheit & Schutz` erzeugt einen vertraulichen, vereinsbezogenen Fall mit Auditspur. Konfliktpersonen und unzustaendige Supportrollen erhalten keinen Zugriff. | Eskalationsweg mit einer zustaendigen Schutzrolle in der Testumgebung pruefen. |

## Behobene Luecken

1. Ticketstatus war sichtbar, es gab aber keinen echten Dialog. Geschuetzte Detail- und Antwort-Endpunkte sowie ein Nachrichtenverlauf sind nun vorhanden.
2. `last_reply_at` wurde zuvor auch durch interne Statuspflege simuliert. Echte Antworten werden jetzt getrennt gespeichert und loesen zielgerichtete Benachrichtigungen aus.
3. Die Sicherheitskategorie war im normalen Supportcenter nicht waehlbar. Sie erzeugt jetzt einen vertraulichen Fall mit Vereinsbindung und Auditspur.
4. Web und App oeffnen eigene Tickets jetzt als Verlauf und erlauben Antworten. Nicht vorhandene Anhaenge werden nicht als fertige Funktion dargestellt.

## Manueller Ablauf

1. Mit einem normalen Konto im Web die Seitenleiste und `Support` oeffnen; in der App das Menue und `Support` waehlen.
2. Ohne Vereinsauswahl ein technisches Ticket mit Betreff und mindestens zehn Zeichen Beschreibung absenden. Bestaetigung und Eintrag unter `Meine Tickets` pruefen.
3. Den Verlauf oeffnen. Ein fremdes Testkonto darf dieselbe Ticket-ID weder lesen noch beantworten.
4. Als Supportbearbeiter die Arbeitsliste oeffnen, das Ticket aufrufen und antworten. Beim Antragsteller Benachrichtigung und Nachrichtentext pruefen.
5. Als Antragsteller antworten. Der Status muss auf `In Bearbeitung` wechseln; die zustaendige Person erhaelt eine Benachrichtigung.
6. Das Ticket schliessen und eine weitere Antwort versuchen. Die API muss mit 422 ablehnen und darf keine Nachricht speichern.
7. Im Konto mit zwei Vereinen je ein Ticket fuer Verein A und B erstellen. Kontext und Arbeitsrechte nach dem Vereinswechsel vergleichen.
8. `Sicherheit & Schutz` waehlen, einen Verein angeben und absenden. Nur eine zustaendige Supportrolle darf den vertraulichen Fall in ihrer Arbeitsliste sehen.

## Supporteingang und Benachrichtigungen (2026-11-16)

- Neue Tickets benachrichtigen berechtigte Supportpersonen und standardmaessig Plattformadministratoren. Vereinsanfragen beruecksichtigen den tatsaechlichen Vereins-/Abteilungs-/Teamzugriff; normale Mitglieder erhalten keine Eingangsmeldung.
- Unter `Support -> Support steuern` gibt es getrennte Eingaenge fuer allgemeinen Support und `Sicherheit & Schutz`. Listen, Kennzahlen, Details, Antworten, Zuweisungen und Empfaengerlisten schuetzen vertrauliche Faelle serverseitig. Der allgemeine Betriebsueberblick enthaelt keine vertraulichen Supportfaelle.
- Schutzfaelle benoetigen die ausdruecklich vergebene Berechtigung `support.safety` zusaetzlich zum passenden Supportbereich. Der allgemeine Admin-Vollzugriff ersetzt diese Freigabe nicht. Konfliktpersonen bleiben ausgeschlossen.
- Die Rolle `safety_support` enthaelt `support.tickets` und `support.safety`. Sie wird keiner bestehenden Person automatisch zugewiesen. Super-Admins koennen im Supportbereich gezielt ein vorhandenes Konto per E-Mail fuer die Schutzrolle anmelden; dieser feste Schritt ist durch die bestehende Admin-2FA-/Step-up-Pruefung geschuetzt. Allgemeine Selbstzuweisung beliebiger Rollen bleibt gesperrt.
- Administratoren koennen ihre zusaetzlichen Meldungen zu neuen Anfragen im Supportbereich individuell abschalten. Die Einstellung `notification_channels.support_admin_alerts` ist standardmaessig aktiv. Zuweisungs- und Antwortmeldungen werden dadurch nicht deaktiviert.
- Web und Android bieten `Mir zuweisen` sowie eine Auswahl berechtigter Bearbeiter. Im Web wird die Auswahl beim Oeffnen des Ticketverlaufs geladen. Zuweisungen benachrichtigen den Bearbeiter; weitere Antworten gehen an weiterhin berechtigte zugewiesene/verantwortliche Personen.
- Neue Eingangsmeldungen und Zuweisungen enthalten keine Betreff-/Beschreibungstexte oder Reporteridentitaeten. Benachrichtigungslinks oeffnen das konkrete geschuetzte Ticket, auch nativ. Push bleibt von den vorhandenen Geraete-/Kontoeinstellungen abhaengig.
- Ohne zugewiesene Schutzperson gibt es keinen allgemeinen Admin-Fallback fuer vertrauliche Inhalte. Vor Freigabe dieses Eingangs mindestens eine Schutzperson einrichten.

Einrichtung auf einer neuen Installation:

```bash
php artisan migrate --force
php artisan airmius:configure-support --safety-user=schutzkonto@example.test --dry-run
php artisan airmius:configure-support --safety-user=schutzkonto@example.test
```

Alternativ: Rolle und Berechtigungen in der Rollenverwaltung anlegen; anschliessend im Supportbereich das gewuenschte vorhandene Schutzkonto eintragen. Die Migration ist idempotent zu dieser manuellen Einrichtung. Bestehende Rollen bleiben erhalten.

Pruefnachweis: 66 gezielte Backendtests, 1.129 Assertions; sechs native Routing-/Benachrichtigungstests; Web-Produktionsbuild erfolgreich. Die geaenderten nativen Modelle, Screens, Deep Links und Tests wurden ohne Analysebefund geprueft. Android-Version: `1.0.72+158`.

Live-Abnahme am 2026-11-16: PHP-Dateien und beide Build-Verzeichnisse nach Ausgangsvergleich und lokaler Sicherung aktualisiert. Support-Arbeitsliste und standardmaessig aktiver Admin-Schalter wurden im angemeldeten Browser geladen. Die ausdruecklich benannte Schutzperson wurde ueber den neuen Super-Admin-Schritt angemeldet; die Rollenverwaltung bestaetigt ein zugeordnetes Konto mit `support.tickets` und `support.safety`. Es wurden keine zusaetzlichen produktiven Testtickets oder Nachrichten versandt. Die Android-APK wurde erfolgreich signiert gebaut; Installation und Push-Zustellung auf einem echten Geraet bleiben die manuelle Abnahme.

Erweiterte Regression: 254 Tests / 5.244 Assertions, davon ein Fehler, elf fehlgeschlagene und zwei uebersprungene Tests. Alle zwoelf Fehler wurden mit denselben Testmethoden gegen einen isolierten unveraenderten Git-Ausgangsstand reproduziert (15 Tests / 1.792 Assertions). Betroffen sind bestehende Admin-Redirect-Erwartungen, Guardian-Einladung, ein externes Mitglied ohne Pflicht-E-Mail, Rechnungs-Snapshot-Reihenfolge, Frontend-/Rechtstextlokalisierung, ein veralteter Rollen-API-Pfad sowie Sponsor-Antrags-/Workspace-Tests. Sie wurden im Rahmen dieser Supportaenderung nicht veraendert.

### Nachkorrektur: modellgebundener Ausloeser (2026-11-16)

Nach der Meldung fehlender In-App-Benachrichtigungen zeigte der erneute FTPS-Vergleich: Observer und Benachrichtigungsservice waren weiterhin vorhanden, aber `AppServiceProvider.php` enthielt die Registrierung des Ticket-Observers nicht mehr. Der aktuelle Serverstand dieser Datei entsprach in diesem Punkt dem alten Ausgangsstand. Wer oder welcher Vorgang die Datei ersetzt hat, wurde nicht ermittelt.

Der Observer wird deshalb jetzt mit `#[ObservedBy([SupportTicketObserver::class])]` am Ticketmodell registriert. Die zusaetzliche Providerregistrierung entfaellt lokal, um doppelte Ausloeser zu vermeiden. 50 Support-/Benachrichtigungstests mit 987 Assertions bestehen ohne diese Providerregistrierung. Auf Produktion wurde ausschliesslich das zuvor gesicherte Ticketmodell aktualisiert und bytegenau zurueckgelesen. Kein App-Update erforderlich. Fehlende Meldungen alter Tickets werden nicht automatisch nachgesendet; der echte Versand muss mit einer neuen Anfrage bestaetigt werden.

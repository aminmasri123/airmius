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

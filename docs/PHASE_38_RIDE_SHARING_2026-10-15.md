# Phase 38: Fahrgemeinschaften

Stand: 2026-10-15

| ID | Abdeckung | Ergebnis / Restpruefung |
| --- | --- | --- |
| T38-01 | Web und App zeigen Strecke, Abfahrt, Ziel, Belegung, freie Plaetze und optionalen Termin; kommende, eigene, vergangene und Terminfilter sind vorhanden. | Automatisierter Vertrag vorhanden; Filter auf echtem Geraet pruefen. |
| T38-02 | Start/Ziel muessen verschieden sein, Abfahrt mindestens zehn Minuten in der Zukunft, Plaetze 1 bis 20; Verein, Team und Termin werden serverseitig auf Zugriff und Kontext geprueft. | Negativwerte, Fremd-IDs und vergangene Zeit im Testkonto pruefen. |
| T38-03 | Anfrage, Annahme und Ablehnung existieren in Web und App; Fahrer und Antragsteller erhalten Status und Benachrichtigung. | Mit zwei Konten und echter Push-Zustellung pruefen. |
| T38-04 | Anfrage und Annahme sperren die aktuelle Fahrtzeile. Die Kapazitaet wird aus dem gesperrten aktuellen Datensatz berechnet; Mitgliedschaft ist pro Fahrt/Person eindeutig. | Zwei parallele Anfragen im Lasttest ausloesen. |
| T38-05 | Nur der Fahrer darf bearbeiten oder loeschen. Beteiligte werden ueber Aenderung oder Loeschung informiert; geloeschte Fahrten sind nicht mehr buchbar. | Push und alte Direktansicht auf zwei Geraeten pruefen. |
| T38-06 | Offene Anfrage kann zurueckgezogen, angenommene Teilnahme verlassen werden. Danach steigt `available_seats` sofort. | Web/App-Neuladen und Zaehler vergleichen. |
| T38-07 | Exakte Adresse, Hinweis, Kontakt und Teilnehmerliste sind nur fuer Fahrer, angenommene Mitfahrer oder den berechtigten Bearbeiter sichtbar. Nach Rueckzug, Entfernung oder Austritt werden sie nicht mehr ausgeliefert. | Fremdkonto und ehemaliges Mitglied als Gegenprobe verwenden. |

## Behobene Luecken

1. Der vorhandene Datenbankbezug `event_id` war in Formularen, API-Antworten und Filtern nicht erreichbar. Web und App koennen eine Fahrt nun mit einem sichtbaren Termin verbinden und danach filtern.
2. Ein Termin aus einem fremden Verein oder Team wird serverseitig abgewiesen.
3. Die Kapazitaetspruefung verwendete nach der Datenbanksperre zuvor noch die eventuell veraltete Sitzplatzzahl des Request-Objekts. Sie verwendet nun den gesperrten aktuellen Datensatz.
4. `available_seats` wird serverseitig einheitlich geliefert, statt in jeder Oberflaeche anders berechnet zu werden.

## Manueller Ablauf

1. Als Fahrer Web `/rides` oder in der App `Fahrgemeinschaften` oeffnen und `Fahrt anbieten` waehlen.
2. Sichtbarkeit, optionalen Termin, Start, Ziel, Abfahrt, Plaetze und Treffpunkt erfassen und speichern.
3. Mitfahrer 1 oeffnet dieselbe Fahrt. Vor Annahme duerfen Strasse, Hausnummer, Treffpunkthinweis und Kontakt nicht sichtbar sein.
4. `Platz anfragen` waehlen und optional eine Nachricht senden. Der Button muss den offenen Status anzeigen und darf keine zweite Anfrage erzeugen.
5. Als Fahrer die Anfrage annehmen. Erst jetzt prueft Mitfahrer 1 die privaten Angaben.
6. Fuer den letzten Platz gleichzeitig mit Mitfahrer 2 und 3 anfragen. Nur eine weitere Annahme darf erfolgreich sein.
7. Einen Mitfahrer austreten lassen. Nach Neuladen muss genau ein Platz mehr frei sein und der ehemalige Mitfahrer darf die privaten Angaben nicht mehr erhalten.
8. Als Fahrer Zeit oder Strecke aendern und danach die Fahrt loeschen. Beteiligte muessen benachrichtigt werden; die alte Fahrt darf nicht mehr buchbar sein.

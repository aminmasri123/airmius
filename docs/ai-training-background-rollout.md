# KI-Trainingspläne: Hintergrundaufträge und Übungsanleitungen

## Bereitstellung

Backend vor Web-Build und App veröffentlichen. Alte Clients können den synchronen Preview-Endpunkt weiterhin verwenden.

1. Migrationen ausführen: `php artisan migrate --force`.
2. Konfigurationscache aktualisieren: `php artisan config:cache`.
3. Auf Hostinger Shared Hosting ist ein separater minütlicher PHP-Cron-Job eingerichtet: `/usr/bin/php /home/u270834523/domains/airmius.com/public_html/airmius/artisan queue:work training_ai --queue=training-ai --once --tries=1 --timeout=600`. Jeder Start verarbeitet höchstens einen Auftrag und beendet sich danach. Der bestehende Scheduler-Cron bleibt separat. Ausgabe unter Hostinger → Cron-Jobs → Output anzeigen. Bei größerem Aufkommen auf überwachte dauerhafte Worker umstellen; ein Start pro Minute begrenzt den Durchsatz und Aufträge laufen nach drei Minuten Wartezeit ab.
4. Bestehende Worker nach Code-Updates neu starten (`php artisan queue:restart`). Der normale Mail-Worker verarbeitet die separate Queue nicht.
5. Der Laravel-Scheduler muss laufen. Er entfernt temporäre Vorschauen nach 24 Stunden täglich. Abruf ist bereits nach 24 Stunden gesperrt.
6. Web-Assets und neue App ausliefern, sobald der Worker geprüft ist.

Worker-Timeout 600 Sekunden, Datenbank-Queue retry_after 900 Sekunden. Ein Auftrag startet nur einmal, auch bei erneuter Zustellung. Ohne Start nach 3 Minuten zeigt der Statusabruf einen Fehler; laufende Aufträge werden nach 12 Minuten ohne Abschluss als fehlgeschlagen angezeigt. Die App begrenzt einzelne Statusabfragen auf 20 Sekunden und die Polling-Zeit auf 12 Minuten. Ein verspäteter, noch nicht gestarteter Auftrag generiert keinen Plan mehr.

## Verhalten

Clients senden `async: true` an den bestehenden Preview-Endpunkt. Dieser prüft Berechtigung und Sportprofil vor dem Einreihen. Pro Konto ist nur ein aktiver Auftrag zulässig. Die Antwort enthält UUID und Status, nicht den Plan. `GET /training/ai/generations/latest` (mobil mit `/api/v1` davor) ermöglicht Wiederaufnahme. Einzelabrufe akzeptieren ebenfalls nur Aufträge des angemeldeten Kontos. Kontext und Ergebnis sind verschlüsselt gespeichert. Eine abgeschlossene Vorschau erhält beim Abruf einen frischen, kontogebundenen Sicherheitsnachweis; die ausdrückliche Bestätigung zum Speichern bleibt notwendig.

Vorschauen werden beim erneuten Öffnen der KI-Erstellung angeboten. Die Wiederherstellung gilt 24 Stunden. Es gibt keine automatische Veröffentlichung und keine Fertig-Push-Benachrichtigung. Bei Verbindungsfehlern kann die Seite erneut geöffnet werden; keine zweite Generierung ist erforderlich.

Jede neue KI-Einheit muss konkrete Schritte mit Phase, Übung, Umfang, Ausführung, Intensität, Pause und Zeitbudget enthalten. Die vollständigen Anleitungen werden in das bestehende editierbare Beschreibungsfeld übertragen. Dadurch bleiben Speichern und ältere Ansichten kompatibel. Fehlende Schritte, reine Sammelbegriffe, fehlende Mengen, falsche Einheitenanzahl und unstimmige Gesamtdauern werden abgewiesen. Diese Strukturprüfung ersetzt keine sportfachliche Qualitätsbewertung. Bestehende Pläne werden nicht nachträglich verändert.

## Live-Abnahme

- Einen kurzen Plan erzeugen, Seite verlassen, App beenden und erneut öffnen: laufender Auftrag bzw. fertige Vorschau muss erscheinen.
- Doppelt klicken: nur ein Auftrag und eine Generierung.
- Als anderes Konto dieselbe UUID abrufen: 404, keinerlei fremde Inhalte.
- Alle Übungen aufklappen; Lauf-ABC muss benannte Übungen mit Umfang, Pausen und Ausführung enthalten.
- Vorschau als Entwurf speichern, erneut öffnen und Anleitungen vergleichen.
- Worker anhalten: Auftrag bleibt zunächst wartend und endet nach 3 Minuten im erklärten Fehlerzustand.
- Providerfehler und KI-Limit: verständliche Fehlermeldung, keine automatische Wiederholung des Jobs.

Zur Laufzeitanalyse werden die Dauer jedes Anbieteraufrufs und die Gesamtdauer des Auftrags protokolliert. Die Umstellung beseitigt die lange offene Clientanfrage; sie beweist noch keine kürzere KI-Rechenzeit. Dafür die neuen Messwerte im Livebetrieb vergleichen.

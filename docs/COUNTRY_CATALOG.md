# Länderauswahl

Profil, Registrierung, Vereinsprofil und Mitgliedsadressen verwenden eine durchsuchbare Auswahl. Gespeichert wird weiterhin der zweistellige Code, angezeigt wird der Ländername in der eingestellten Sprache (DE, EN, FR, AR).

Die Grundliste enthält 249 ISO-3166-1-Einträge (Länder und Gebiete) sowie Kosovo mit dem zusätzlichen Code XK. Quelle der Codes: Debian iso-codes; Namen: ICU/CLDR. Standardreferenz: https://www.iso.org/iso-3166-country-codes.html.

## Ergänzen

Mit der Berechtigung `system.manage`: ein Länderfeld öffnen, **Land ergänzen** wählen, eindeutigen zweistelligen Code und deutschen/englischen Namen eingeben, speichern. Die Ergänzung steht anschließend in Web und App zur Verfügung. Bestehende Standardcodes können nicht überschrieben werden. Ein zusätzlicher Eintrag kann über denselben Code aktualisiert werden.

Normale Nutzer dürfen die zentrale Liste nicht verändern. Die serverseitige Prüfung gilt unabhängig von der Sichtbarkeit des Buttons. Ergänzungen liegen unter `country_catalog.CODE` in der vorhandenen Tabelle `settings`; keine Migration erforderlich.

## Offline und Aktualisierung

Die vollständige Grundliste wird mit Web und Flutter ausgeliefert. Serverergänzungen werden beim Öffnen des Formulars geladen; ohne Verbindung bleiben die Grundliste und bereits im Formular geladene Ergänzungen nutzbar. Bereits gespeicherte unbekannte Codes werden nicht automatisch durch DE ersetzt.

`node scripts/generate-country-catalog.mjs [iso_3166-1.json]` aktualisiert beide identischen JSON-Dateien. Danach Katalogtests ausführen. Änderungen an Flutter benötigen eine neue App-Version. Diese Änderung schaltet keine zusätzlichen Versandländer, Steuertarife oder Zahlungsanbieter frei.

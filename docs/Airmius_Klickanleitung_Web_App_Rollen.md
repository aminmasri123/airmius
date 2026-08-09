# Airmius Klickanleitung: Web und App

Stand: 01.08.2026

## Zweck dieser Anleitung

Diese Anleitung führt durch die sichtbaren Funktionen der Airmius-Webanwendung und der Flutter-App. Sie ist als gemeinsamer Testleitfaden für vier Geräte gedacht: zwei Handys und zwei Laptops. Die Klickwege sind für die vier Perspektiven Sportler, Trainer, Verein – operative Vereinsansicht – und Vereinsverwaltung – Mitglieder und Finanzen – beschrieben. Ergänzend ist am Ende die optionale Plattform-Admin-Sicht dokumentiert.

Die App zeigt Menüpunkte nur dann an, wenn das angemeldete Konto die passende Rolle, Berechtigung und gegebenenfalls den passenden Abo-Plan besitzt. Fehlt ein Menüpunkt, ist das daher nicht automatisch ein Fehler. Rolle, Verein, Team und Plan im Testprotokoll notieren.

## 1. Geräte und Testkonten vorbereiten

### 1.1 Empfohlene Geräteaufteilung

1. Handy 1: Flutter-App mit dem Konto `sportler@airmius.test`.
2. Handy 2: Flutter-App mit dem Konto `trainer@airmius.test`.
3. Laptop 1: Webbrowser mit dem Konto `verein@airmius.test` als Verein-Admin oder Verein-Owner.
4. Laptop 2: Webbrowser mit dem Konto `sportler@airmius.test` oder `trainer@airmius.test` für die Gegenkontrolle.

Für den Test eines Vereinsadministrators und eines Trainers dürfen die Konten nicht identisch sein. Wenn du zusätzlich einen Plattformadministrator prüfen möchtest, öffne dafür ein privates Browserfenster auf Laptop 2 oder melde dich nach dem ersten Durchlauf ab.

### 1.2 Voraussetzungen

1. Web öffnen und prüfen, dass die Web-App erreichbar ist.
2. App auf beiden Handys öffnen und prüfen, dass beide dieselbe Umgebung/API verwenden.
3. Pro Gerät Datum und Uhrzeit automatisch einstellen.
4. Auf dem Browser Cookies und JavaScript erlauben.
5. Für Push-Tests App-Benachrichtigungen, Kamera, Standort und Dateien nur dann erlauben, wenn die Funktion getestet wird.
6. Einen Testverein, mindestens ein Team, einen Trainer und einen Sportler vorbereiten.
7. Den Sportler im Testverein und im Testteam verknüpfen; den Trainer im Team als Coach verknüpfen.
8. Für Finanztests eine Testrechnung oder einen Testbeitrag anlegen. Keine echten Bank- oder Zahlungsdaten verwenden.

### 1.3 Sicherheitsregeln

1. Keine produktiven Passwörter in die Dokumentation schreiben.
2. Keine echten IBANs, Ausweisnummern, Gesundheitsdaten oder privaten Fotos verwenden.
3. SEPA-, DATEV-, Stripe- und PayPal-Funktionen nur mit Testdaten und Testumgebung ausführen.
4. Vor dem Löschen eines Vereins, Teams, Beitrags oder Dokuments prüfen, dass es ein Testobjekt ist.
5. Jede Änderung mit Rolle, Gerät, Browser/App-Version, Uhrzeit und Ergebnis dokumentieren.

### 1.4 Android-Entwicklungsmodus und Geräteanschluss

Der Entwicklungsmodus ist sinnvoll, wenn wir echte App-Fehler, API-Verbindungen, Berechtigungsdialoge oder Abstürze nachvollziehen müssen. Für einen normalen manuellen Klicktest ist er nicht zwingend erforderlich. Am einfachsten ist der Anschluss eines Android-Handys; ein iPhone benötigt in der Regel einen Mac mit Xcode und kann hier nicht per ADB untersucht werden.

Android vorbereiten:

1. Auf dem Handy „Einstellungen“ > „Über das Telefon“ öffnen.
2. „Build-Nummer“ beziehungsweise „Buildnummer“ siebenmal antippen.
3. Zurück zu „System“ oder „Weitere Einstellungen“ gehen.
4. „Entwickleroptionen“ öffnen.
5. „USB-Debugging“ einschalten.
6. Handy mit einem Daten-USB-Kabel anschließen; ein reines Ladekabel reicht nicht.
7. Auf dem Handy den USB-Modus „Dateiübertragung“ auswählen.
8. Den Dialog „USB-Debugging zulassen?“ mit „Zulassen“ bestätigen. Nur auf dem eigenen Testrechner „Immer zulassen“ aktivieren.
9. Am Rechner prüfen, ob das Handy in der Geräteliste erscheint.
10. Erst danach die App auf dem Handy starten oder mit Flutter neu installieren.

Wenn das Gerät nicht erscheint:

1. USB-Kabel und USB-Port wechseln.
2. Handy entsperren und den Autorisierungsdialog erneut bestätigen.
3. In den Entwickleroptionen „USB-Debugging-Autorisierungen widerrufen“ wählen und das Kabel neu verbinden.
4. Auf Windows den passenden OEM-USB-Treiber installieren; auf Linux meist nur die ADB-Regel/Berechtigung prüfen.
5. Am Rechner den Befehl `adb devices -l` ausführen. Status `device` bedeutet verbunden, `unauthorized` bedeutet: Autorisierung auf dem Handy bestätigen.

Für die lokale API:

1. Nicht `http://localhost:8000` in der App verwenden; auf einem echten Handy zeigt `localhost` auf das Handy selbst.
2. Für einen Android-Emulator `http://10.0.2.2:8000` verwenden.
3. Für ein echtes Handy die lokale LAN-IP des Rechners verwenden, zum Beispiel `http://192.168.x.x:8000`, und Handy sowie Rechner in dasselbe WLAN bringen.
4. Alternativ die konfigurierte Test-/Produktionsadresse verwenden, zum Beispiel `https://airmius.com`.
5. Beim Start der App die Adresse mit `--dart-define=AIRMIUS_API_BASE_URL=...` setzen.
6. Wenn die Fehlermeldung „API ist nicht erreichbar“ erscheint, zuerst API-Adresse, WLAN, HTTPS/CORS und laufenden Laravel-Server prüfen.

Wichtig: Der Entwicklungsmodus gibt keinen automatischen Zugriff auf private Handy-Inhalte. Für die Diagnose brauchen wir entweder eine ADB-Verbindung, einen Logauszug oder einen Screenshot/Screen-Record des konkreten Fehlers.

## 2. Die Navigation verstehen

### 2.1 Web auf Laptop oder Desktop

1. Nach dem Login erscheint links die Seitenleiste.
2. Auf einem kleinen Bildschirm zuerst oben links auf das Hamburger-Symbol klicken.
3. Oben rechts befinden sich die Suche, Nachrichten, Benachrichtigungen und das Benutzerprofil.
4. Das Airmius-Logo führt zurück zur rollenabhängigen Startseite.
5. In der Seitenleiste Gruppen wie „Mein Sport“, „Trainer & Team“, „Vereinsverwaltung“, „Sponsoring & Reichweite“ und „Admin“ öffnen oder schließen.
6. In Listen zuerst den Verein oder das Team auswählen, wenn mehrere vorhanden sind.
7. Nach jeder Änderung auf „Speichern“, „Anlegen“, „Senden“, „Annehmen“ oder „Bestätigen“ klicken und die Erfolgs-/Fehlermeldung prüfen.

### 2.2 App auf dem Handy

1. Nach dem Login erscheint unten eine personalisierte Navigationsleiste.
2. Oben links auf das Airmius-Logo tippen, um zur rollenabhängigen Startseite zurückzukehren.
3. Oben auf die Lupe tippen, um global zu suchen.
4. Oben auf das Nachrichten-Symbol tippen, um Chats zu öffnen.
5. Oben auf die Glocke tippen, um Benachrichtigungen zu öffnen.
6. Oben auf das Profil-Symbol tippen, um Profil, Einstellungen oder Abmelden zu öffnen.
7. Oben rechts auf das Menü-Symbol tippen, um den vollständigen Modulbereich zu öffnen.
8. Im Menü zuerst die Schnellbereiche „Training“, „Vereine“, „Feed“ und „Ernährung“ prüfen; darunter stehen die für die Rolle freigegebenen Module.
9. Mit der Zurück-Taste des Handys erst ein geöffnetes Modul, dann den vorherigen Bereich und zuletzt den Feed verlassen.

### 2.3 Die unteren App-Schaltflächen je Rolle

Sportler sehen standardmäßig: „Training“, „Vereine & Teams“, „Feed“, „Ernährung“ und „Profil“.

Trainer sehen standardmäßig: „Trainer-Cockpit“, „Training“, „Vereine & Teams“, „Nachrichten“ und „Profil“.

Vereinsverantwortliche sehen standardmäßig: „Vereins-Cockpit“, „Vereine & Teams“, „Events“, „Dateien“ und „Profil“.

Bei mehreren Arbeitsbereichen kann die App stattdessen „Arbeitsbereiche“ anzeigen. Die fünf Schaltflächen können in den Einstellungen angepasst werden; nicht jedes Modul muss dauerhaft unten liegen, weil es zusätzlich über das Menü erreichbar ist.

## 3. Allgemeiner Starttest für jedes Konto

### 3.1 Registrierung

Web:

1. Abmelden oder ein privates Browserfenster öffnen.
2. Auf der Gastseite auf „Registrieren“ klicken.
3. Name, E-Mail-Adresse, Passwort und die Pflichtfelder ausfüllen.
4. Geburtsdatum sorgfältig prüfen; bei Minderjährigen die Eltern-E-Mail eintragen.
5. Datenschutz, AGB und erforderliche Einwilligungen prüfen.
6. Auf „Registrieren“ klicken.
7. Die Verifizierungs-E-Mail öffnen und den Bestätigungslink anklicken.
8. Zurück zur Web-App gehen und anmelden.

App:

1. App öffnen und auf „Registrieren“ tippen.
2. Dieselben Pflichtfelder ausfüllen.
3. Auf „Konto erstellen“ tippen.
4. Prüfen, dass ein Validierungsfehler verständlich angezeigt wird, wenn ein Pflichtfeld fehlt.
5. E-Mail bestätigen und zur App zurückkehren.
6. Mit dem neuen Konto anmelden.

Erwartet: Das Konto wird nicht als vollständig eingerichtet behandelt, solange Pflichtdaten oder E-Mail-Verifizierung fehlen. Bei einem Minderjährigen erscheint gegebenenfalls der Bereich „Eltern & Jugendschutz“ beziehungsweise eine Warteseite für die Zustimmung.

### 3.2 Login, Logout und Passwort

1. Web oder App öffnen.
2. E-Mail und Passwort eingeben.
3. Auf „Anmelden“ beziehungsweise „Login“ klicken oder tippen.
4. Prüfen, ob die richtige Rollenstartseite erscheint.
5. Über Profilbild oder Profilblase „Abmelden“ auswählen.
6. Erneut anmelden.
7. Unter „Einstellungen“ beziehungsweise „Sicherheit“ das Passwort ändern.
8. Abmelden und mit dem neuen Passwort anmelden.
9. Optional „Passwort vergessen“ testen: E-Mail anfordern, Link öffnen und neues Passwort setzen.

### 3.3 Profil und persönliche Einstellungen

Web: Profilbild oben rechts öffnen, „Einstellungen“ auswählen.

App: Profil unten öffnen, danach „Einstellungen“ auswählen.

1. Tab „Profil“ öffnen.
2. Vorname, Nachname, Profilbild und öffentliche Beschreibung prüfen.
3. Änderungen speichern.
4. Tab „Adresse“ öffnen und nur Testdaten eintragen.
5. Tab „Sprache“ öffnen und Deutsch auswählen; danach optional Englisch oder Französisch testen.
6. Tab „Design“ öffnen und helles/dunkles Design prüfen.
7. Tab beziehungsweise Bereich „Datenschutz“ öffnen: Profil-/Kontaktgrenzen, Anzeigenpersonalisierung, Conversion-Messung und anonyme Produktverbesserung einzeln prüfen. Datenauskunft, Berichtigung und „Daten löschen, Konto behalten“ öffnen; verbundene Login-/Sportanbieter müssen ohne Tokens, Scopes, externe Kennungen oder Anbieter-E-Mail-Adressen sichtbar sein.
8. Tab „Sicherheit“ öffnen und Passwort, Zwei-Faktor-Authentifizierung und andere Sitzungen prüfen.
9. Tab „Aktivitäten“ öffnen; eine manuelle Aktivität anlegen, bearbeiten und wieder löschen.
10. Tab „Sportprofil“ öffnen; eine Sportart hinzufügen, Erfahrungslevel, Status, Kennzahlen und Sichtbarkeit speichern.
11. Tab „Sport-Apps & Gesundheitsdaten“ öffnen; nur einen Test-Provider verbinden oder eine vorhandene Verbindung prüfen.
12. Im Web mehrere datenreiche Tabs nacheinander öffnen. Beim ersten Öffnen darf kurz ein angekündigter Ladehinweis erscheinen; beim Zurückwechseln soll der bereits geladene Inhalt ohne erneute Wartezeit sichtbar sein. Einen Tab zusätzlich über eine direkte URL mit `?tab=...` aufrufen und prüfen, dass sein Inhalt vollständig erscheint.

Erwartet: Nach dem Speichern bleiben die Werte beim Neuladen erhalten. Private Kennzahlen sind für andere Konten nicht sichtbar, wenn die Sichtbarkeit auf „privat“ steht. Web und App zeigen denselben Stand der drei optionalen Einwilligungen; ein Widerruf bleibt nach dem Neuladen wirksam. Ein fehlgeschlagener Tab-Request lässt den bisherigen Inhalt und noch nicht gespeicherte Formulareingaben stehen und bietet „Erneut versuchen“ an.

## 4. Gemeinsame Funktionen für alle Rollen

### 4.1 Globale Suche

Web: oben rechts „Globale Suche“ anklicken oder `⌘/Ctrl + K` drücken. App: oben auf die Lupe tippen.

1. Mindestens zwei Zeichen eingeben.
2. Nach einer Funktion wie „Training“ oder „Support“ sowie nach einem Sportler, Verein, Team, Event, Kurs, Produkt und einer sichtbaren Datei suchen.
3. Im Web mit Pfeiltasten durch die Ergebnisse gehen und mit Enter öffnen; mit Escape schließen.
4. Ein Ergebnis antippen beziehungsweise anklicken und prüfen, dass die richtige Detailseite öffnet.
5. Bei einem Team, falls angeboten, auf „Beitreten“ klicken.
6. Prüfen, dass bei weniger als zwei Zeichen kein Serveraufruf und kein Ergebnis erscheint.
7. Datenschutz-Gegenprobe: Mit einem normalen Konto nach der E-Mail-Adresse eines anderen Nutzers suchen; darüber darf kein Profil auffindbar sein.
8. Rechte-Gegenprobe: Mit einem normalen Konto nach „Nutzer“ oder „Rollen“ suchen; administrative Ziele dürfen erst mit `users.view` beziehungsweise `users.assign_roles` erscheinen.

### 4.2 Nachrichten und Chat

Web: oben auf das Nachrichten-Symbol oder links auf „Nachrichten“ klicken. App: oben auf Nachrichten oder unten auf „Nachrichten“ tippen.

1. Eine vorhandene Unterhaltung öffnen.
2. Nachricht schreiben und senden.
3. Prüfen, ob der Empfänger die Nachricht erhält.
4. Eine neue Unterhaltung starten.
5. Wenn verfügbar, weitere Teilnehmer hinzufügen.
6. Nachricht lesen, zurückgehen und den Ungelesen-Zähler prüfen.
7. Eine Nachricht oder Unterhaltung nur in der Testumgebung löschen oder verlassen.

Gegenprüfung: Auf dem zweiten Gerät mit dem Empfängerkonto anmelden. Prüfen, ob der Chat-Zähler steigt und die Nachricht in Echtzeit oder nach Aktualisierung erscheint.

### 4.3 Benachrichtigungen

1. Oben auf die Glocke klicken oder tippen.
2. Eine neue Benachrichtigung öffnen.
3. Prüfen, ob der Link zum richtigen Verein, Team, Event oder Chat führt.
4. Den Bereich „Benachrichtigungen“ vollständig öffnen.
5. Eine einzelne Benachrichtigung als gelesen markieren.
6. „Alle als gelesen markieren“ ausführen.
7. Prüfen, ob der Zähler verschwindet.
8. Push-Benachrichtigung auf dem Handy testen, wenn Push für die Umgebung eingerichtet ist.

### 4.4 Dateien

Web: Seitenleiste „Dateien“ öffnen. Trainer öffnen „Teamdateien“; Vereinsverantwortliche öffnen Dateien aus dem Vereinsbereich.

1. Speicherbereich prüfen.
2. Bereich auswählen: „Persönlich“, „Team“, „Verein“ oder „Event“.
3. „Ordner erstellen“ klicken, Namen eingeben und speichern.
4. Ordner öffnen.
5. „Datei hochladen“ klicken, eine ungefährliche Testdatei auswählen und Upload abwarten.
6. Datei öffnen oder Vorschau anzeigen.
7. Datei umbenennen.
8. Datei über „Teilen“ mit einem Testnutzer, Team oder Verein teilen.
9. Auf dem zweiten Gerät prüfen, ob die Freigabe sichtbar ist.
10. Datei löschen: Löschvorgang bestätigen und prüfen, dass sie nicht mehr in der Liste erscheint.

App: Menü-Symbol, „Dateien“ öffnen und dieselben Schritte mit „Hochladen“, „Ordner“ und „Teilen“ durchführen.

### 4.5 Marketplace, Commerce und Abos

1. Seitenleiste oder App-Menü „Marketplace“ öffnen.
2. Produkt, Kurs oder Camp auswählen.
3. Details und Anbieterinformationen prüfen.
4. „In den Warenkorb“ auswählen.
5. Warenkorb öffnen und Menge prüfen.
6. Checkout starten.
7. In der Testumgebung eine Testzahlung oder Überweisung auswählen.
8. Bestellstatus öffnen und prüfen.
9. „Commerce“ öffnen, falls die Rolle Anbieter- oder Verwaltungsrechte besitzt.
10. Als Anbieter ein Produkt oder eine Kampagne nur als Testentwurf anlegen.
11. Unter „Abos & Rechnungen“ den aktiven Plan, Rechnung und Zahlungsstatus prüfen.
12. Eine Rechnung herunterladen.
13. Kündigung oder Pausierung nur für ein Test-Abo durchführen und die Bestätigung prüfen.

## 5. Ansicht Sportler – vollständiger Klickleitfaden

### 5.1 Sportler-Startseite öffnen

Web:

1. Mit dem Sportlerkonto anmelden.
2. In der Seitenleiste „Mein Sport“ öffnen.
3. Auf „Feed“ klicken oder oben auf das Airmius-Logo klicken.
4. Prüfen, dass der Tagesflow, persönliche Hinweise und die eigenen Vereine sichtbar sind.

App:

1. Mit dem Sportlerkonto anmelden.
2. Unten „Feed“ oder das Airmius-Logo tippen.
3. Prüfen, dass unten „Training“, „Vereine & Teams“, „Feed“, „Ernährung“ und „Profil“ erscheinen.
4. Menü öffnen und kontrollieren, dass die zusätzlichen Sportler-Module sichtbar sind.

### 5.2 Sportarten, Skills und Profil-CV

1. Web: „Einstellungen“ > Tab „Sportprofil“ öffnen. App: „Profil“ > „Einstellungen“ > „Sportprofil“.
2. „Sportart hinzufügen“ auswählen.
3. Sportart und Disziplin auswählen.
4. Status, Erfahrungslevel, Ziele und Leistungskennzahlen eintragen.
5. Für jede Kennzahl die Sichtbarkeit auf privat, eingeschränkt oder öffentlich setzen.
6. Speichern.
7. Ein anderes Sportlerprofil öffnen und prüfen, welche Informationen sichtbar sind.
8. Bei einem anderen Profil auf „Skill bestätigen“ beziehungsweise „Empfehlung schreiben“ klicken, falls die Funktion angezeigt wird.
9. Eigene erhaltene Empfehlung öffnen und annehmen oder ablehnen.
10. Lizenznummer nur mit einer Testnummer speichern.

### 5.3 Feed, Stories und soziale Aktionen

1. „Mein Sport“ > „Feed“ öffnen; in der App unten „Feed“ tippen.
2. Zwischen den Feed-Filtern „Alle“, „Vereine“, „Training“ und weiteren sichtbaren Filtern wechseln.
3. Im Composer einen kurzen Testbeitrag schreiben.
4. Optional Bild oder Testdatei anhängen.
5. „Veröffentlichen“ klicken.
6. Den eigenen Beitrag öffnen.
7. Beitrag bearbeiten und speichern.
8. Beitrag liken und als hilfreich markieren.
9. Kommentar schreiben, bearbeiten und wieder löschen.
10. Eine Story ansehen, wenn eine Test-Story vorhanden ist.
11. Den eigenen Testbeitrag löschen und die Sicherheitsabfrage bestätigen.
12. Einen fremden Testbeitrag über das Drei-Punkte-Menü melden; als Grund „Testmeldung“ verwenden, wenn vorgesehen.

Gegenprüfung: Auf dem zweiten Gerät prüfen, ob der Beitrag, Like, Kommentar und die Benachrichtigung sichtbar sind.

### 5.4 Training und Trainingspläne

Web: „Mein Sport“ > „Training“. App: unten „Training“.

1. Trainingsübersicht öffnen.
2. Wochenansicht und geplante Einheiten prüfen.
3. Einen Trainingsplan öffnen.
4. Plan, Einheiten, Übungen, Hinweise und Fortschritt prüfen.
5. Eine Einheit öffnen und „Training dokumentieren“ auswählen.
6. Dauer, Belastung/RPE, Notizen und Ergebnis eintragen.
7. Optional eine Übung aus der Übungsbibliothek auswählen.
8. Speichern.
9. Einen Trainingslog öffnen und Details prüfen.
10. Einen Testlog bearbeiten oder löschen, falls die Schaltfläche angezeigt wird.
11. Offenes Trainer-Feedback öffnen und die Rückmeldung prüfen.

### 5.5 Ernährung

Web: „Mein Sport“ > „Ernährung“. App: unten „Ernährung“ oder Menü > „Ernährung“.

1. Tagesübersicht öffnen.
2. „Mahlzeit erfassen“ oder „Schnell erfassen“ auswählen.
3. Mahlzeit, Menge, Kalorien und Makronährstoffe eintragen.
4. Speichern und den Tageswert prüfen.
5. „Lebensmittel finden“ öffnen und nach einem Testlebensmittel suchen.
6. Optional Barcode-/Bildsuche nur mit Testdaten prüfen.
7. „Wasser“ beziehungsweise „Trinken“ öffnen.
8. Wassermenge erfassen und Tagesziel prüfen.
9. Rezept- oder Trainingsvorschläge öffnen und einen Vorschlag übernehmen.
10. Einen Testeintrag bearbeiten und löschen.
11. Unter „Ziel“ das persönliche Ernährungsziel prüfen und speichern.

### 5.6 Sportkarte: Route, Track und Live-Tracking

1. Seitenleiste „Mein Sport“ > „Sportkarte“ öffnen; App-Menü > „Sportkarte“.
2. „Route planen“ öffnen.
3. Kartenpunkte setzen und Punkte bei Bedarf entfernen.
4. Route speichern und Namen vergeben.
5. „Tracks“ öffnen und einen manuellen Testtrack anlegen.
6. „Orte“ oder „Sportorte“ öffnen und einen Testort speichern.
7. Standortberechtigung nur für diesen Test erlauben.
8. „Live-Tracking“ starten.
9. Live-Position, Zeit, Distanz und Geschwindigkeit prüfen.
10. Tracking beenden und Ergebnis speichern oder verwerfen.
11. Route öffnen, bearbeiten, exportieren oder löschen, sofern die Schaltfläche angezeigt wird.

### 5.7 Sport-Matching

1. Seitenleiste „Mein Sport“ > „Sport-Matching“ öffnen; App-Menü > „Sport-Matching“.
2. Modus „Sportpartner“ oder „Teamgegner“ auswählen.
3. Sportart, Ort/Radius, Termin und Niveau eingeben.
4. „Suchen“ klicken.
5. Ein Ergebnis öffnen und Anfrage senden.
6. Eine eingehende Anfrage annehmen oder ablehnen.
7. Bei Team-Matching ein Testteam auswählen.
8. Ergebnis und Benachrichtigung prüfen.

### 5.8 Freunde

1. „Mein Sport“ > „Freunde“ öffnen.
2. E-Mail-Adresse eines Testkontos eingeben.
3. „Einladen“ klicken.
4. Auf dem zweiten Konto die Freundschaftsanfrage öffnen.
5. „Annehmen“ oder „Ablehnen“ wählen.
6. Nach Annahme den Freund in der Freundesliste prüfen.
7. Freundschaft über das Menü beenden, falls erforderlich.
8. Ein Profil nur in der Testumgebung melden.

### 5.9 Vereine und Mitgliedschaft

1. „Vereine & Teams“ öffnen; App unten „Vereine & Teams“ tippen.
2. Nach dem Testverein suchen.
3. Vereinsprofil, Logo, Sportarten, Teams und öffentliche Informationen prüfen.
4. Ein Team öffnen und Kader sowie Termine ansehen.
5. „Mitgliedschaft anfragen“ oder „Beitreten“ klicken.
6. Nachricht/Begründung eintragen, falls angeboten, und Anfrage senden.
7. Anfrage unter den eigenen Vereinsanträgen prüfen.
8. Anfrage bei Bedarf zurückziehen.
9. Auf dem Vereins-Admin-Gerät prüfen, ob die Anfrage in „Mitglieder & Finanzen“ > „Anfragen“ erscheint.

### 5.10 Events, Teilnahme und Fahrgemeinschaften

1. „Mein Sport“ > „Events“ öffnen; App-Menü „Events & Training“ öffnen.
2. Listen- und Kalenderansicht prüfen.
3. Eventfilter für Zeitraum, Verein, Team und Sportart testen.
4. Eventdetails öffnen.
5. RSVP „Zusage“, „Vielleicht“ oder „Absage“ auswählen.
6. Kommentar oder Rückfrage schreiben, falls verfügbar.
7. Wenn angeboten, „Fahrgemeinschaft“ öffnen.
8. Fahrt anbieten oder Mitfahrt suchen.
9. Treffpunkt, Plätze und Sicherheitsinformationen prüfen.
10. RSVP oder Mitfahrt auf dem zweiten Gerät gegenprüfen.

### 5.11 Kurse, Badges, Outfit-Abo und Marketplace

1. „Meine Kurse“ öffnen.
2. Kurs auswählen, Lektion öffnen und Lernfortschritt speichern.
3. Nach Abschluss Zertifikat öffnen oder Verifizierung prüfen.
4. „Meine Badges“ öffnen, Badge und Fortschritt prüfen.
5. „Outfit-Abo“ öffnen.
6. Style-Profil speichern.
7. Lieferübersicht prüfen.
8. Abo für Testplan pausieren, fortsetzen oder kündigen.
9. „Marketplace“ öffnen und einen Testkurs oder ein Produkt in den Warenkorb legen.

## 6. Ansicht Trainer – vollständiger Klickleitfaden

### 6.1 Trainer-Cockpit öffnen

Web:

1. Mit dem Trainerkonto anmelden.
2. In der Seitenleiste „Trainer & Team“ öffnen.
3. „Trainer-Cockpit“ klicken.
4. Wenn mehrere Arbeitsbereiche vorhanden sind, zuerst „Arbeitsbereiche“ öffnen und „Trainer & Team“ auswählen.

App:

1. Mit dem Trainerkonto anmelden.
2. Unten „Trainer-Cockpit“ tippen.
3. Alternativ Menü-Symbol > „Trainer-Cockpit“ öffnen.
4. Prüfen, dass unten „Trainer-Cockpit“, „Training“, „Vereine & Teams“, „Nachrichten“ und „Profil“ sichtbar sind.

### 6.2 Cockpit-Tabs und Wochensteuerung

1. Tab „Übersicht“ öffnen.
2. Bereitschafts-/Readiness-Wert, Teams, Athleten, kommende Events und offenes Feedback prüfen.
3. Bereich „Nächste Schritte“ öffnen und eine Aktion anklicken.
4. Tab „Teams“ öffnen.
5. Team auswählen und Kader, Rollen und letzte Aktivität prüfen.
6. Tab „Feedback“ öffnen.
7. Offenes Feedback eines Sportlers öffnen.
8. Rückmeldung schreiben und speichern.
9. Tab „Planung“ öffnen.
10. Überfällige Planpunkte und kommende Termine prüfen.
11. Wochenkennzahlen wie Einheiten, Belastung, durchschnittliches RPE und Trend prüfen.
12. Risikosportler öffnen und nur sachliche Testnotizen hinterlassen.

### 6.3 Teams verwalten

1. „Trainer & Team“ > „Teams“ öffnen; App Menü > „Teams“.
2. Vorhandenes Team auswählen oder „Team erstellen“ klicken.
3. Vereinszuordnung, Teamname, Sportart, Saison und Beschreibung eintragen.
4. Logo und Titelbild als Testbild hochladen.
5. Speichern.
6. „Mitglieder einladen“ öffnen.
7. Test-E-Mail oder vorhandenes Testkonto auswählen.
8. Teamrolle „Coach“, „Captain“ oder „Player“ auswählen.
9. Einladung senden.
10. Auf dem Sportlergerät Einladung öffnen und annehmen.
11. Zurück im Trainerkonto Team öffnen und Mitgliedschaft prüfen.
12. Teamrolle ändern und speichern.
13. Mitglied entfernen nur mit einem Testkonto prüfen.
14. Offene Beitrittsanfragen öffnen und annehmen oder ablehnen.

### 6.4 Training planen und dokumentieren

1. „Trainer & Team“ > „Trainingsplanung“ öffnen.
2. „Trainingsplan erstellen“ klicken.
3. Titel, Ziel, Zeitraum, Team und Hinweise eintragen.
4. „Plan speichern“ klicken.
5. Eine Einheit hinzufügen.
6. Datum, Dauer, Intensität und Übung auswählen.
7. Übungsbibliothek öffnen und eine Testübung übernehmen.
8. Einheit speichern.
9. Plan einem Team oder Sportler zuweisen.
10. Auf dem Sportlergerät prüfen, ob der Plan erscheint.
11. Trainer auf „Training dokumentieren“ gehen und eine durchgeführte Einheit eintragen.
12. Sportlergerät öffnen und Log prüfen.
13. Feedback am Log hinterlassen.
14. AI-Planvorschau nur mit unkritischen Testdaten prüfen; vor dem Speichern Vorschau kontrollieren.
15. Plan oder Einheit bearbeiten und löschen nur mit Testdaten prüfen.

### 6.5 Events und Anwesenheit

1. „Events & Anwesenheit“ öffnen.
2. „Event erstellen“ klicken.
3. Schritt „Grunddaten“: Titel, Sportart, Verein/Team und Eventtyp eintragen.
4. Schritt „Zeit“: Start, Ende, Zeitzone und Wiederholung prüfen.
5. Schritt „Details“: Ort, Beschreibung, Kapazität und Teilnahmeoptionen eintragen.
6. Schritt „Review“ öffnen und alle Eingaben prüfen.
7. Event speichern.
8. Event öffnen und Tab „Anwesenheit“ auswählen.
9. Zusagen, Absagen und offene Antworten prüfen.
10. Anwesenheit für Testteilnehmer markieren.
11. Eventkommentar schreiben und als Trainer antworten.
12. Auf dem Sportlergerät RSVP ändern und die Anwesenheitsliste aktualisieren.

### 6.6 Team-Kommunikation und Dateien

1. „Nachrichten“ öffnen.
2. Teamchat öffnen oder neue Unterhaltung starten.
3. Nachricht senden und Reaktion/Lesestatus prüfen.
4. „Teamdateien“ öffnen.
5. Testordner anlegen.
6. Trainingsplan oder Test-PDF hochladen.
7. Datei mit Team oder Einzelperson teilen.
8. Auf dem Sportlergerät Zugriff prüfen.
9. Datei umbenennen und anschließend löschen.

### 6.7 Trainerwissen, Skills und Angebote

1. Feed öffnen und einen Trainingshinweis als Testbeitrag veröffentlichen.
2. Kommentar oder Übungshinweis hinzufügen.
3. Sportlerprofil öffnen.
4. Skill bestätigen und Empfehlung schreiben, wenn erlaubt.
5. „Kurse“ oder „Marketplace“ öffnen.
6. Ein Testangebot wie Kurs, Camp oder Trainingsplan als Entwurf einreichen.
7. Status „Entwurf“, „In Prüfung“ oder „Aktiv“ prüfen.
8. Keine realen Preise oder Verkaufsdaten verwenden.

## 7. Ansicht Verein – operative Vereinsansicht

### 7.1 Vereins-Cockpit öffnen

Web:

1. Mit dem Vereins-Admin- oder Owner-Konto anmelden.
2. Seitenleiste „Vereinsverwaltung“ öffnen.
3. „Vereins-Cockpit“ klicken.
4. Bei mehreren Vereinen im Vereinsauswahlfeld den Testverein auswählen.

App:

1. Mit dem Vereinskonto anmelden.
2. Unten „Vereins-Cockpit“ tippen.
3. Alternativ Menü-Symbol > „Vereins-Cockpit“ öffnen.
4. Testverein auswählen, wenn mehrere Vereine angeboten werden.

### 7.2 Cockpit prüfen

1. Mitgliederzahl und Planlimit prüfen.
2. Teamzahl und Teamlimit prüfen.
3. Offene Beträge und offene Rechnungen prüfen.
4. Offene Mitglieds- und Teamanfragen prüfen.
5. Kommende Vereins- und Teamtermine öffnen.
6. Speicherverbrauch prüfen.
7. Unter „Nächste Aufgaben“ zuerst „Anfragen prüfen“ öffnen.
8. Danach „Rechnungen klären“, „SEPA vervollständigen“ und „Vereinsstruktur pflegen“ prüfen.
9. Einen Termin im Kalender öffnen.
10. Prüfen, ob gesperrte Planfunktionen einen verständlichen Upgrade-Hinweis anzeigen.

### 7.3 Verein anlegen und Vereinsprofil pflegen

1. „Vereine & Teams“ öffnen.
2. „Verein registrieren“ klicken.
3. Schritt 1: Vereinsname, Kurzname und Kontaktdaten eintragen.
4. Schritt 2: Sportarten, Standort, Beschreibung und öffentliche Sichtbarkeit eintragen.
5. Schritt 3: Verantwortliche, Plan und Bestätigung prüfen.
6. „Verein erstellen“ klicken.
7. Vereinsprofil öffnen.
8. Vereinslogo und Titelbild hochladen.
9. Vereinsnummer und offiziellen Status nur mit Testwerten pflegen.
10. Speichern.
11. Öffentliche Vorschau öffnen und kontrollieren, welche Daten sichtbar sind.
12. Verein bearbeiten und anschließend erneut speichern.
13. Löschen nur mit einem eigens angelegten Testverein und nach Bestätigung des Datenverlusts prüfen.

### 7.4 Teams, Rollen und Einladungen

1. „Vereinsverwaltung“ > „Vereine & Teams“ öffnen.
2. Testverein aufklappen.
3. „Team erstellen“ klicken.
4. Teamname, Sportart, Saison und Beschreibung eintragen.
5. Team speichern.
6. Trainer als „Coach“ einladen.
7. Sportler als „Player“ einladen.
8. Einladungsstatus prüfen.
9. Eingehende Teamanfrage annehmen oder ablehnen.
10. Vereinsrolle einer Person prüfen und ändern, wenn die Berechtigung vorhanden ist.
11. Teamrolle ändern.
12. Teamdateien, Teamtermine und Teamprofil öffnen.

### 7.5 Events, Kommunikation und Dateien

1. „Events & Anwesenheit“ öffnen.
2. Vereins- oder Teamevent erstellen.
3. Event veröffentlichen.
4. Teilnehmer und Anwesenheit prüfen.
5. Vereinsbeitrag im Feed veröffentlichen.
6. Vereinschat öffnen und Nachricht an Team oder Mitglied senden.
7. „Dateien“ öffnen und einen Vereinsordner anlegen.
8. Satzung oder Testdokument hochladen.
9. Dokument mit dem Verein oder ausgewählten Team teilen.
10. Zugriff auf dem Sportler- oder Trainergerät prüfen.
11. Testdokument umbenennen und löschen.

### 7.6 Sponsoren und Vereinsangebote

1. Vereinsbereich oder „Sponsoring & Reichweite“ öffnen.
2. „Sponsoren“ auswählen.
3. Sponsorprofil, Kontakt und Paket prüfen.
4. Kampagne oder Vereinsangebot als Testentwurf vorbereiten.
5. Ziel-URL, Laufzeit und Beschreibung prüfen.
6. Speichern und Freigabestatus kontrollieren.
7. Unter „Marketplace“ oder „Commerce“ eine Vereinswebsite-/Werbeanfrage nur als Testanfrage anlegen.
8. Anfrage und Status im Vereins- oder Adminbereich prüfen.

## 8. Vereinsverwaltung – Mitglieder- und Finanzsicht

### 8.1 Mitglieder & Finanzen öffnen

Web:

1. Seitenleiste „Vereinsverwaltung“ öffnen.
2. „Mitglieder & Finanzen“ klicken.
3. Den Testverein auswählen.
4. Die Tabs „Mitglieder“, „Anfragen“, „Beitragsregeln“, „Rechnungen“, „Finanzen“, „Audit“ und „SEPA & DATEV“ prüfen.

App:

1. Menü-Symbol öffnen.
2. „Vereins-Cockpit“ und danach das Mitglieder-/Finanzmodul öffnen, sofern die Rolle es anzeigt.
3. Falls nur das Cockpit angezeigt wird, vom Cockpit über „Anfragen“, „Mitglieder“ oder die Vereinsverwaltung weitergehen.
4. Bei fehlender Funktion Rolle und Abo-Plan prüfen.

### 8.2 Mitglied hinzufügen und Mitgliedsdaten pflegen

1. Tab „Mitglieder“ öffnen.
2. „Mitglied hinzufügen“ klicken.
3. Name und E-Mail eines Testkontos eingeben.
4. Vereinsrolle auswählen.
5. Status auswählen: Vereinsmitglied, Nichtmitglied, In Prüfung, Pausiert oder Ehemalig.
6. Mitgliedsnummer und Sportlizenznummer nur als Testnummer eintragen.
7. Beitrag, Intervall und nächstes Rechnungsdatum eintragen.
8. Optional Eintrittsdatum, Ende, SEPA-Testdaten und interne Notiz eintragen.
9. „Speichern“ klicken.
10. Mitgliedszeile öffnen und prüfen, ob alle Werte gespeichert wurden.
11. Mitgliedsnummer automatisch generieren, wenn die Schaltfläche angeboten wird.
12. Status ändern und speichern.

### 8.3 Externe Mitglieder per E-Mail oder Import

1. „Mitglied hinzufügen“ öffnen.
2. Für eine externe Person E-Mail-Adresse und Name eingeben.
3. „Einladung senden“ aktivieren.
4. Einladung senden.
5. Auf dem Empfängerkonto den Einladungslink öffnen.
6. Anmeldung oder Verknüpfung mit dem Airmius-Konto durchführen.
7. Zur Vereinsverwaltung zurückkehren und Verknüpfungsstatus prüfen.
8. Für mehrere Personen „Excel-Vorlage“ herunterladen.
9. Vorlage mit Testdaten ausfüllen.
10. „Importieren“ klicken und Datei auswählen.
11. Vorschau und Fehlerhinweise prüfen.
12. Import bestätigen.
13. Prüfen, ob neue externe Mitglieder erscheinen.
14. Optional „Einladungen senden“ im Import aktivieren und den Status prüfen.

### 8.4 Beitrittsanfragen bearbeiten

1. Tab „Anfragen“ öffnen.
2. Offene Vereins- und Teamanfragen prüfen.
3. Antrag öffnen und Profil, Verein, Team und Nachricht kontrollieren.
4. „Annehmen“ klicken.
5. Rolle und Teamzuordnung prüfen.
6. Alternativ „Ablehnen“ klicken und Begründung eintragen, wenn angeboten.
7. Auf dem Sportlergerät Benachrichtigung und neuen Mitgliedsstatus prüfen.
8. Eine vom Sportler zurückgezogene Anfrage prüfen.
9. Mit einem aktiven Testmitglied im Web-Vereinsprofil „Verein verlassen“ öffnen, gewünschtes Austrittsdatum und optionalen Grund eingeben und den Antrag senden. Das Mitglied darf dadurch noch nicht sofort aus Verein oder Teams entfernt werden.
10. Als Vereinsverantwortlicher den terminierten Austrittsantrag im Tab „Anfragen“ prüfen und freigeben. Ein Test mit offener Rechnung muss vorher blockiert werden.
11. Zusätzlich eine Mitgliedschaftspause beantragen und freigeben. Im Audit-Tab müssen Einreichung und Entscheidung mit Status, Zeitpunkt und handelndem Konto, aber ohne Formular-, Dokument-, Signatur-, IP- oder User-Agent-Inhalte erscheinen.

### 8.5 Beitragsregeln und Mitgliedschaftstypen

1. Tab „Beitragsregeln“ öffnen.
2. „Mitgliedschaftstyp hinzufügen“ klicken.
3. Name, Beschreibung, öffentlich/aktiv und Sortierung eintragen.
4. „Typ speichern“ klicken.
5. „Neue Beitragsregel“ öffnen.
6. Mitgliedschaftstyp, Gültigkeit, Intervall und Betrag eintragen.
7. Optional Altersgrenzen, Rabattfaktor und Notiz ergänzen.
8. „Regel speichern“ klicken.
9. Historische Regeln prüfen.
10. Beitragstyp einem Testmitglied zuordnen.

### 8.6 Rechnungen

1. Tab „Rechnungen“ öffnen.
2. Filter „Offen“, „Bezahlt“, „Überfällig“ und „Storniert“ testen.
3. Eine Rechnung für ein Testmitglied erstellen.
4. Betrag, Titel, Fälligkeit und Zeitraum prüfen.
5. Rechnung speichern.
6. Rechnung öffnen und Status auf „Bezahlt“ setzen, wenn ein Testzahlungseingang vorhanden ist.
7. Zahlung über „Zahlung erfassen“ dokumentieren.
8. Bei einer offenen Testrechnung „Erinnerung/Mahnung senden“ testen.
9. Überfällige Rechnung prüfen.
10. Rechnungsdetails auf dem Sportlerkonto prüfen, falls für das Mitglied freigegeben.

### 8.7 Finanzen und Kassenbuch

1. Tab „Finanzen“ öffnen.
2. Einnahme oder Ausgabe als Testbuchung erfassen.
3. Konto, Kategorie, Titel, Betrag, Buchungsdatum und Referenz eintragen.
4. Beschreibung ergänzen.
5. „Speichern“ klicken.
6. Kassenbuch öffnen und Buchung prüfen.
7. Bankbestand, Kassenbestand und Gesamtbestand prüfen.
8. Testbuchung bearbeiten oder löschen, sofern vorgesehen.
9. Bank-CSV-Import öffnen und eine anonymisierte Testdatei auswählen.
10. Automatische Zuordnung prüfen.
11. Einen vorgeschlagenen Bankumsatz manuell bestätigen.

### 8.8 SEPA und DATEV/SKR42

1. Tab „SEPA & DATEV“ öffnen.
2. Test-Gäubiger-ID und Test-Kontodaten eintragen.
3. SEPA-Einstellungen speichern.
4. Prüfen, welche Mitglieder ein aktives Testmandat besitzen.
5. „SEPA-Lastschrift exportieren“ klicken.
6. Exportdatei nur in der Testumgebung herunterladen und prüfen.
7. DATEV-Beraternummer, Mandantennummer und Testkonten eintragen.
8. Zeitraum auswählen.
9. „DATEV/SKR42 exportieren“ klicken.
10. Export öffnen und Spalten, Beträge und Konten prüfen.
11. Bei gesperrter Funktion den Plan-Hinweis dokumentieren. SEPA-, Bankabgleich- und DATEV-Export sind planabhängig.

### 8.9 Audit und Planlimits

1. Tab „Audit“ öffnen.
2. Mitgliedsänderung, Rechnung, Zahlung, Import und Rollenänderung suchen.
3. Zeitpunkt, handelndes Konto und Änderung prüfen.
4. Im Vereins-Cockpit den Planbalken und Speicher-/Mitgliederlimits prüfen.
5. Eine Funktion über dem Planlimit nur anzeigen oder abbrechen lassen; keine Umgehung versuchen.

## 9. Optional: Plattform-Admin-Sicht

Diese Sicht ist nur mit einem ausdrücklichen Plattform-Admin-Konto zu testen.

### 9.1 Web-Navigation

1. Seitenleiste „Admin“ öffnen.
2. „Users“ öffnen: Nutzer suchen, Profil öffnen, Status prüfen und Testnutzer bearbeiten.
3. „Rollen & Rechte“ öffnen: Rollen, Berechtigungen und Audit prüfen.
4. „Blogs“ und „Blog-Kategorien“ öffnen: Testentwurf anlegen und Status prüfen.
5. „Bildmaße“ öffnen: Medienrichtlinien prüfen.
6. „Payments“, „Invoices“, „Betriebskosten“, „Abos“, „Abo-Rechnungen“ und „Commerce“ öffnen.
7. „Outfit-Abos“, „Sponsors“, „Moderation“, „Sportarten“, „Vereinsprüfung“, „Gamification“, „Badges“, „Mail-Zentrale“, „Provider-Kosten“ und „Settings“ prüfen.
8. Keine echte Löschung oder echte Auszahlung durchführen.

### 9.2 App-Navigation

1. Menü-Symbol öffnen.
2. „Admin“ auswählen.
3. Je nach Berechtigung „Backoffice“, „Commerce“, „Outfit-Abos“, „Plattform-Admin“ oder „Admin-Center“ öffnen.
4. „Nutzer“, „Rollen & Rechte“ und „Gamification-Regeln“ nur mit Testwerten prüfen.
5. Prüfen, dass ein Sportler- oder Trainerkonto beim Öffnen eines Adminmoduls eine Berechtigungsfehlermeldung erhält.

## 10. Paralleltest mit zwei Handys und zwei Laptops

### 10.1 Empfohlene Zuordnung

1. Handy 1 / App Sportler: `sportler@airmius.test`.
2. Handy 2 / App Trainer: `trainer@airmius.test`.
3. Laptop 1 / Web Verein: `verein@airmius.test`.
4. Laptop 2 / Web Gegenkonto: abwechselnd Sportler, Trainer oder Plattform-Admin.

### 10.2 Durchlauf A: Mitgliedsantrag

1. Handy 1: „Vereine & Teams“ > Testverein > „Mitgliedschaft anfragen“.
2. Handy 1: Antrag senden.
3. Laptop 1: „Vereinsverwaltung“ > „Mitglieder & Finanzen“ > „Anfragen“.
4. Laptop 1: Antrag öffnen und „Annehmen“ klicken.
5. Handy 1: Benachrichtigung öffnen.
6. Handy 1: neuen Vereinsstatus prüfen.

### 10.3 Durchlauf B: Training und Feedback

1. Handy 2: „Trainer-Cockpit“ > „Planung“ > Testtraining öffnen.
2. Handy 2: Training oder Event anlegen.
3. Handy 1: „Training“ öffnen und Plan prüfen.
4. Handy 1: Training dokumentieren.
5. Handy 2: „Trainer-Cockpit“ > „Feedback“ öffnen.
6. Handy 2: Feedback schreiben.
7. Laptop 2: Sportlerkonto öffnen und Feedback-Benachrichtigung prüfen.

### 10.4 Durchlauf C: Event und Anwesenheit

1. Laptop 1: „Events & Anwesenheit“ > Event erstellen.
2. Handy 1: Event öffnen und „Zusage“ wählen.
3. Handy 2: „Events & Anwesenheit“ öffnen.
4. Handy 2: Anwesenheit prüfen.
5. Handy 2: Teamkommentar schreiben.
6. Laptop 1: Event aktualisieren und Kommentar prüfen.

### 10.5 Durchlauf D: Datei und Freigabe

1. Laptop 1: „Dateien“ > Vereinsordner > Testdatei hochladen.
2. Laptop 1: Datei mit dem Testteam teilen.
3. Handy 2: „Teamdateien“ öffnen und Datei anzeigen.
4. Handy 1: Zugriff ebenfalls prüfen.
5. Laptop 1: Datei umbenennen.
6. Handy 2: neuen Dateinamen prüfen.
7. Laptop 1: Testdatei löschen.

### 10.6 Durchlauf E: Rechnung und Benachrichtigung

1. Laptop 1: „Mitglieder & Finanzen“ > „Rechnungen“ > Testrechnung erstellen.
2. Laptop 1: Rechnung offen speichern.
3. Handy 1: Benachrichtigung oder Mitgliedsbereich prüfen.
4. Laptop 1: Zahlung erfassen und Status „Bezahlt“ prüfen.
5. Handy 1: aktualisierten Status nach Aktualisierung prüfen.

## 11. Testprotokoll: So dokumentierst du jeden Schritt

Für jede getestete Funktion eine Zeile ausfüllen:

1. Datum und Uhrzeit.
2. Rolle und E-Mail-Testkonto.
3. Gerät: Handy 1, Handy 2, Laptop 1 oder Laptop 2.
4. Web oder App; Browsername oder App-Version.
5. Exakter Klickweg, zum Beispiel „Seitenleiste > Trainer & Team > Trainer-Cockpit > Feedback“.
6. Erwartetes Ergebnis.
7. Tatsächliches Ergebnis.
8. Status: Offen, Bestanden, Fehler oder Nachtest.
9. Screenshot oder Bildschirmaufnahme.
10. Fehlertext, HTTP-Status oder Zeitpunkt der fehlenden Benachrichtigung.

Wenn ein Menüpunkt fehlt, zusätzlich festhalten: Rolle, Verein, Team, Permission, Abo-Plan und ob der Punkt auf Web und App gleichermaßen fehlt.

## 12. Abschluss-Checklisten

### Sportler

- [ ] Login, Logout und Passwortänderung
- [ ] Profil, Sportart, Skill und Sichtbarkeit
- [ ] Feed, Beitrag, Kommentar, Like, hilfreich und Meldung
- [ ] Training, Plan, Log und Feedback
- [ ] Ernährung, Wasser, Lebensmittel und Ziel
- [ ] Sportkarte, Route, Track und Standortberechtigung
- [ ] Sport-Matching und Freunde
- [ ] Vereinssuche, Mitgliedsantrag und Rückzug
- [ ] Event-RSVP und Fahrgemeinschaft
- [ ] Dateien, Kurse, Badges, Marketplace und Outfit-Abo
- [ ] Nachrichten und Benachrichtigungen

### Trainer

- [ ] Trainer-Cockpit: Übersicht, Teams, Feedback und Planung
- [ ] Team erstellen, Bild, Rollen und Einladungen
- [ ] Teambeitrittsanfrage annehmen/ablehnen
- [ ] Trainingsplan und Übung erstellen
- [ ] Training dokumentieren und Feedback geben
- [ ] Event erstellen und Anwesenheit pflegen
- [ ] Teamchat und Teamdateien
- [ ] Sportler-Skills bestätigen
- [ ] Kurs oder Trainingsangebot als Testentwurf

### Verein und Vereinsverwaltung

- [ ] Vereinsprofil, Logo, Titelbild und öffentliche Vorschau
- [ ] Verein, Teams, Rollen und Einladungen
- [ ] Vereins-Cockpit und Planlimits
- [ ] Mitglieder hinzufügen, importieren und einladen
- [ ] Anfragen annehmen/ablehnen
- [ ] Mitgliedschaftstypen und Beitragsregeln
- [ ] Rechnungen, Zahlungen und Mahnungen
- [ ] Finanzen, Kassenbuch und Bankabgleich
- [ ] SEPA- und DATEV-Testexport
- [ ] Audit-Log
- [ ] Events, Kommunikation und Vereinsdateien
- [ ] Sponsoren, Kampagnen und Vereinsangebote

### Abschluss

1. Auf allen vier Geräten abmelden.
2. Testdateien, Testbeiträge, Testevents und Testrechnungen löschen oder als Testdaten markieren.
3. Keine echte Zahlungs- oder Bankdatei im Download-Ordner liegen lassen.
4. Fehlerliste anlegen und nach Blocker, wichtig und kosmetisch sortieren.
5. Diese Anleitung bei neuen Menüpunkten oder Rollenberechtigungen aktualisieren.

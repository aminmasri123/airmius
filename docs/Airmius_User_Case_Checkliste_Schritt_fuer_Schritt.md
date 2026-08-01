# Airmius Use-Case-Testplan: Schritt für Schritt

Stand: 01.08.2026

## So wird dieser Testplan benutzt

Dieser Testplan prüft Airmius nicht nur nach Menüs, sondern nach echten Abläufen. Jeder Use Case hat ein Ziel, Voraussetzungen, Testkonto und Testgerät, genaue Klickschritte, ein erwartetes Ergebnis und eine Gegenprüfung. Einen Use Case erst als „Bestanden“ markieren, wenn auch das erwartete Ergebnis eingetreten ist.

### Geräteaufteilung

1. Handy 1 / App Sportler: `sportler@airmius.test`.
2. Handy 2 / App Trainer: `trainer@airmius.test`.
3. Laptop 1 / Web Verein: `verein@airmius.test` als `club_admin` oder `club_owner`.
4. Laptop 2 / Web Gegenkonto: je nach Use Case Sportler, Trainer oder Plattform-Admin.

### Testdaten

1. Testverein: „Airmius Testverein“.
2. Testteam: „Airmius Testteam“.
3. Sportler: „Mika Sportler“.
4. Trainer: „Tina Trainer“.
5. Verein-Admin: „Vera Verein“.
6. Eine ungefährliche Testdatei, ein Testbild, eine Testrechnung und eine anonymisierte Bank-CSV.

### Ergebnis je Use Case eintragen

1. Status: Offen, Bestanden, Fehler oder Nachtest.
2. Gerät und Browser/App-Version.
3. Konto und Rolle.
4. Exakter Klickweg.
5. Erwartetes Ergebnis und tatsächliches Ergebnis.
6. Screenshot, Bildschirmaufnahme oder Fehlertext.

## UC-01: Neues Benutzerkonto registrieren

### Ziel

Ein neuer Nutzer legt ein Konto an, erhält die Verifizierungsaufforderung und kann danach den Einrichtungsprozess starten.

### Voraussetzungen

1. Eine noch nicht verwendete Test-E-Mail-Adresse liegt vor.
2. Das Postfach kann geöffnet werden.
3. Kein aktives Konto mit dieser E-Mail ist bereits angemeldet.

### Web – genaue Schritte

1. Laptop 2 öffnen und die öffentliche Airmius-Seite aufrufen.
2. Auf „Registrieren“ klicken.
3. Vorname und Nachname eingeben.
4. Test-E-Mail-Adresse eingeben.
5. Sicheres Testpasswort zweimal eingeben.
6. Geburtsdatum eintragen.
7. Pflichtfelder, Datenschutz und erforderliche Einwilligungen prüfen.
8. Auf „Registrieren“ klicken.
9. Prüfen, ob eine erfolgreiche Registrierung oder ein verständlicher Validierungsfehler erscheint.
10. Verifizierungs-E-Mail öffnen.
11. Auf den Bestätigungslink klicken.
12. Zurück zur Web-App wechseln.
13. Mit dem neuen Konto anmelden.

### App – genaue Schritte

1. Handy 1 öffnen und die Airmius-App starten.
2. Auf „Registrieren“ tippen.
3. Die Pflichtfelder genauso ausfüllen wie im Web.
4. Ein absichtlich leeres Pflichtfeld testen.
5. Prüfen, ob die App den Fehler am richtigen Feld anzeigt.
6. Formular mit korrekten Testdaten erneut absenden.
7. E-Mail verifizieren.
8. Zur App zurückkehren und anmelden.

### Erwartetes Ergebnis

Das Konto wird angelegt. Bei falschen oder fehlenden Angaben erscheint eine verständliche Fehlermeldung. Nach der E-Mail-Bestätigung kann sich der Nutzer anmelden und sieht den Einrichtungs- oder Rollenstartbereich.

### Nachweis und Gegenprüfung

1. Bestätigungs-E-Mail und Startseite dokumentieren.
2. Prüfen, dass Web und App dasselbe Konto verwenden.
3. Status eintragen: ________.

## UC-02: Benutzer meldet sich an und ab

### Ziel

Ein vorhandener Nutzer kann sich sicher anmelden, landet in seiner richtigen Rollenansicht und kann sich wieder abmelden.

### Voraussetzungen

1. Konto ist angelegt und E-Mail ist bestätigt.
2. Konto ist aktiv und nicht gesperrt.
3. Testpasswort ist bekannt.

### Web – genaue Schritte

1. Laptop 2 öffnen.
2. Airmius-Webseite aufrufen.
3. Auf „Anmelden“ klicken.
4. E-Mail des Sportlerkontos eingeben.
5. Passwort eingeben.
6. Auf „Anmelden“ klicken.
7. Prüfen, ob „Mein Sport“ oder „Feed“ als Sportlerstartseite erscheint.
8. Oben rechts das Benutzerprofil öffnen.
9. „Abmelden“ klicken.
10. Prüfen, dass wieder die öffentliche Seite oder Login-Seite erscheint.

### App – genaue Schritte

1. Handy 1 öffnen.
2. E-Mail und Passwort eingeben.
3. Auf „Anmelden“ tippen.
4. Prüfen, ob unten „Training“, „Vereine & Teams“, „Feed“, „Ernährung“ und „Profil“ erscheinen.
5. Auf das Profil-Symbol tippen.
6. „Abmelden“ auswählen.
7. Abmeldung bestätigen.

### Erwartetes Ergebnis

Das Konto wird angemeldet, die passende Rollenansicht wird angezeigt und nach dem Abmelden ist kein geschützter Bereich mehr erreichbar.

### Gegenprüfung

1. Nach dem Logout die Zurück-Taste benutzen.
2. Prüfen, dass keine privaten Daten ohne erneuten Login erscheinen.
3. Status eintragen: ________.

## UC-03: Passwort wiederherstellen und Sicherheit prüfen

### Ziel

Ein Nutzer kann ein vergessenes Passwort zurücksetzen und seine Kontosicherheit verwalten.

### Voraussetzungen

1. Zugriff auf die Test-E-Mail.
2. Ein aktives Testkonto.

### Web – genaue Schritte

1. Login-Seite öffnen.
2. „Passwort vergessen“ klicken.
3. Test-E-Mail eingeben.
4. „Link senden“ klicken.
5. E-Mail öffnen und den Link anklicken.
6. Neues Testpasswort zweimal eingeben.
7. Passwort speichern.
8. Zur Login-Seite zurückkehren.
9. Mit dem neuen Passwort anmelden.
10. „Einstellungen“ > „Sicherheit“ öffnen.
11. Passwortänderung erneut mit einem zweiten Testpasswort prüfen.
12. Zwei-Faktor-Authentifizierung prüfen, wenn sie für die Umgebung aktiviert ist.
13. Andere Browser-Sitzungen anzeigen und gegebenenfalls abmelden.

### App – genaue Schritte

1. Login-Bildschirm öffnen.
2. „Passwort vergessen“ antippen.
3. E-Mail senden und den Link aus dem Postfach öffnen.
4. Neues Passwort speichern.
5. Zur App zurückkehren und anmelden.

### Erwartetes Ergebnis

Der alte Zugang funktioniert nicht mehr, das neue Passwort funktioniert. Sicherheits- und Sitzungseinstellungen werden verständlich angezeigt.

### Status

Bestanden / Fehler / Nachtest: ________. Fehlertext: ____________________.

## UC-04: Profil und Sportprofil vervollständigen

### Ziel

Ein Sportler vervollständigt persönliche Daten, Sportarten, Skills, Ziele und Sichtbarkeit.

### Voraussetzungen

1. Sportler ist angemeldet.
2. Mindestens eine Test-Sportart ist verfügbar.

### Web – genaue Schritte

1. Oben rechts das Profil öffnen.
2. „Einstellungen“ klicken.
3. Tab „Profil“ öffnen.
4. Name, Profilbild und Beschreibung eintragen.
5. „Speichern“ klicken.
6. Tab „Adresse“ öffnen.
7. Nur Testadresse eintragen und speichern.
8. Tab „Sportprofil“ öffnen.
9. „Sportart hinzufügen“ klicken.
10. Sportart und Disziplin auswählen.
11. Erfahrungslevel, Status und Ziel eintragen.
12. Eine Leistungskennzahl als Testwert eintragen.
13. Sichtbarkeit der Kennzahl auf „privat“ setzen.
14. Sportprofil speichern.
15. Öffentliche Profilansicht öffnen.
16. Prüfen, dass die private Kennzahl nicht öffentlich sichtbar ist.

### App – genaue Schritte

1. Unten „Profil“ antippen.
2. „Einstellungen“ öffnen.
3. „Sportprofil“ auswählen.
4. Sportart, Disziplin, Level und Ziel anlegen.
5. Sichtbarkeit auf privat setzen.
6. Speichern.
7. Profil neu laden und die Werte prüfen.

### Erwartetes Ergebnis

Alle gespeicherten Werte bleiben nach Neuladen erhalten. Private Kennzahlen sind für andere Konten nicht sichtbar.

### Gegenprüfung

1. Laptop 2 mit einem anderen Testkonto öffnen.
2. Sportlerprofil aufrufen.
3. Sichtbare und nicht sichtbare Felder vergleichen.
4. Status: ________.

## UC-05: Sportler sucht einen Verein und stellt einen Mitgliedsantrag

### Ziel

Ein Sportler findet einen Verein, prüft dessen Profil und stellt eine Mitgliedschaftsanfrage.

### Voraussetzungen

1. Sportlerkonto ist angemeldet.
2. Testverein ist öffentlich sichtbar oder über die Suche erreichbar.
3. Sportler ist noch nicht Mitglied oder hat eine offene Testanfrage.

### Web – genaue Schritte

1. Laptop 2 mit dem Sportlerkonto öffnen.
2. Oben rechts in das Suchfeld klicken.
3. „Airmius Testverein“ eingeben.
4. Warten, bis Suchergebnisse erscheinen.
5. Das Ergebnis mit dem Typ „Verein“ anklicken.
6. Vereinsname, Beschreibung, Sportarten, Standort und öffentliche Daten prüfen.
7. Zum Bereich „Mitglied werden“ oder „Beitreten“ gehen.
8. „Mitgliedschaft anfragen“ anklicken.
9. Falls vorhanden, Team und Nachricht auswählen/eingeben.
10. Antrag absenden.
11. Erfolgsnachricht und Status „In Prüfung“ dokumentieren.
12. „Vereine & Teams“ öffnen.
13. Prüfen, ob der Testverein unter eigenen Anfragen erscheint.
14. Antrag öffnen.
15. „Anfrage zurückziehen“ nur als separaten Test prüfen.

### App – genaue Schritte

1. Handy 1 öffnen und als Sportler anmelden.
2. Unten „Vereine & Teams“ antippen.
3. Im Suchfeld „Airmius Testverein“ eingeben.
4. Testverein antippen.
5. Profil und Teams prüfen.
6. „Mitgliedschaft anfragen“ oder „Beitreten“ antippen.
7. Team auswählen, wenn eine Auswahl erscheint.
8. Nachricht eingeben und absenden.
9. Status der Anfrage öffnen.

### Erwartetes Ergebnis

Der Verein wird gefunden. Der Antrag wird einmalig gespeichert und steht im Status „In Prüfung“. Der Verein erhält eine neue Anfrage beziehungsweise Benachrichtigung.

### Gegenprüfung auf Laptop 1

1. Laptop 1 mit dem Verein-Admin öffnen.
2. „Vereinsverwaltung“ > „Mitglieder & Finanzen“ anklicken.
3. Tab „Anfragen“ öffnen.
4. Sportlerantrag suchen.
5. Name, Antrag, Team und Zeit prüfen.
6. Status: ________.

## UC-06: Verein prüft und akzeptiert den Mitgliedsantrag

### Ziel

Der Verein entscheidet über den Antrag und der Sportler erhält den neuen Mitgliedsstatus.

### Voraussetzungen

1. UC-05 ist erfolgreich abgeschlossen.
2. Verein-Admin besitzt Berechtigung für Mitglieder.

### Web – genaue Schritte

1. Laptop 1 öffnen und mit dem Verein-Admin anmelden.
2. Seitenleiste „Vereinsverwaltung“ öffnen.
3. „Mitglieder & Finanzen“ anklicken.
4. Testverein auswählen.
5. Tab „Anfragen“ öffnen.
6. Offenen Antrag des Sportlers anklicken.
7. Profil und Anfrage prüfen.
8. „Annehmen“ klicken.
9. Vereinsrolle „Mitglied“ bestätigen.
10. Falls angeboten, Team auswählen.
11. Entscheidung speichern.
12. Tab „Mitglieder“ öffnen.
13. Sportler suchen.
14. Status „Vereinsmitglied“ prüfen.

### App – genaue Schritte

1. Handy 2 oder Vereins-App öffnen.
2. „Vereins-Cockpit“ antippen.
3. „Anfragen“ auswählen.
4. Sportlerantrag öffnen.
5. „Annehmen“ antippen.
6. Rolle und Team bestätigen.
7. Mitgliederliste öffnen.

### Erwartetes Ergebnis

Der Antrag verschwindet aus den offenen Anfragen. Der Sportler ist aktives Vereinsmitglied und erhält eine Benachrichtigung.

### Gegenprüfung auf Handy 1

1. Sportler-App öffnen.
2. Glocke antippen.
3. Annahmebenachrichtigung öffnen.
4. Vereinsprofil und Mitgliedsstatus prüfen.
5. Status: ________.

## UC-07: Trainer erstellt ein Team und lädt Personen ein

### Ziel

Ein Trainer erstellt ein Team, weist Rollen zu und lädt einen Sportler ein.

### Voraussetzungen

1. Trainer ist dem Testverein zugeordnet.
2. Trainer besitzt Team-Berechtigung.
3. Sportlerkonto und Testverein existieren.

### Web – genaue Schritte

1. Laptop 2 mit dem Trainerkonto öffnen.
2. Seitenleiste „Trainer & Team“ öffnen.
3. „Teams“ anklicken.
4. „Team erstellen“ anklicken.
5. Verein auswählen.
6. Teamname „Airmius Testteam“ eingeben.
7. Sportart und Beschreibung eingeben.
8. Saison oder Zeitraum eintragen, falls vorhanden.
9. Testlogo hochladen.
10. „Team speichern“ anklicken.
11. Teamprofil öffnen.
12. „Mitglieder einladen“ anklicken.
13. Sportlerkonto oder Sportler-E-Mail auswählen.
14. Rolle „Player“ auswählen.
15. „Einladung senden“ anklicken.

### App – genaue Schritte

1. Handy 2 öffnen.
2. Unten „Vereine & Teams“ oder Menü > „Teams“ antippen.
3. „Team erstellen“ antippen.
4. Verein, Teamname, Sportart und Beschreibung eingeben.
5. Speichern.
6. Team öffnen.
7. „Einladen“ antippen.
8. Sportler auswählen.
9. Rolle „Player“ wählen und Einladung senden.

### Erwartetes Ergebnis

Das Team erscheint im Verein. Der Sportler erhält eine Einladung mit dem Teamnamen, Verein und der Rolle.

### Gegenprüfung

1. Handy 1 mit dem Sportlerkonto öffnen.
2. Glocke oder „Vereine & Teams“ öffnen.
3. Einladung prüfen.
4. Einladung annehmen.
5. Trainergerät aktualisieren und Mitglied im Kader prüfen.
6. Status: ________.

## UC-08: Teammitglied nimmt Einladung an und Rolle wird geändert

### Ziel

Ein eingeladener Sportler tritt dem Team bei; der Trainer kann die Teamrolle kontrollieren.

### Voraussetzungen

1. UC-07 hat eine offene Einladung erzeugt.

### Schritte Sportler – App

1. Handy 1 als Sportler öffnen.
2. Glocke antippen.
3. Team-Einladung öffnen.
4. Verein und Team prüfen.
5. „Annehmen“ antippen.
6. „Vereine & Teams“ öffnen.
7. Team auswählen.
8. Teamprofil und Rolle „Player“ prüfen.

### Schritte Trainer – Web

1. Laptop 2 als Trainer öffnen.
2. „Trainer & Team“ > „Teams“ öffnen.
3. „Airmius Testteam“ anklicken.
4. Mitgliederliste öffnen.
5. Sportler und Rolle prüfen.
6. Rolle auf „Captain“ ändern, wenn die Rolle für den Test freigegeben ist.
7. „Speichern“ anklicken.
8. Sportlergerät aktualisieren und neue Rolle prüfen.
9. Rolle wieder auf „Player“ zurückstellen.

### Erwartetes Ergebnis

Einladung, Eintritt, Kaderzugehörigkeit und Rollenänderung sind auf beiden Geräten konsistent.

### Status

Bestanden / Fehler / Nachtest: ________.

## UC-09: Trainer erstellt Trainingsplan und Sportler dokumentiert Training

### Ziel

Der Trainer plant eine Einheit. Der Sportler sieht sie, dokumentiert die Durchführung und der Trainer gibt Feedback.

### Voraussetzungen

1. Trainer und Sportler sind im selben Team.
2. Testteam ist aktiv.

### Schritte Trainer – Web

1. Laptop 2 mit dem Trainerkonto öffnen.
2. „Trainer & Team“ > „Trainingsplanung“ anklicken.
3. „Trainingsplan erstellen“ anklicken.
4. Titel und Ziel eingeben.
5. Testteam auswählen.
6. Zeitraum und Hinweise eingeben.
7. „Plan speichern“ anklicken.
8. „Einheit hinzufügen“ anklicken.
9. Datum, Dauer und Intensität eintragen.
10. Eine Testübung aus der Übungsbibliothek auswählen.
11. Einheit speichern.
12. Plan dem Sportler oder Team zuweisen.

### Schritte Sportler – App

1. Handy 1 öffnen.
2. Unten „Training“ antippen.
3. Geplante Testeinheit öffnen.
4. Übungen, Dauer und Hinweise prüfen.
5. „Training dokumentieren“ antippen.
6. Dauer und Belastung/RPE eintragen.
7. Notiz „Test durchgeführt“ eingeben.
8. Training speichern.
9. Trainingslog öffnen und gespeicherte Werte prüfen.

### Schritte Trainer – Gegenprüfung

1. Trainergerät aktualisieren.
2. „Trainer-Cockpit“ > „Feedback“ öffnen.
3. Den neuen Trainingslog anklicken.
4. Feedback eingeben.
5. Feedback speichern.

### Erwartetes Ergebnis

Der Plan ist beim Sportler sichtbar. Der Trainingslog ist beim Trainer sichtbar. Feedback wird beim Sportler als offen oder gelesen angezeigt.

### Status

Bestanden / Fehler / Nachtest: ________.

## UC-10: Trainer erstellt Event und Sportler sagt zu

### Ziel

Ein Trainer erstellt einen Termin. Sportler antworten darauf und der Trainer sieht die Anwesenheit.

### Voraussetzungen

1. Trainer besitzt Event-Berechtigung.
2. Ein Team oder Verein ist verfügbar.

### Schritte Web Trainer

1. Laptop 2 als Trainer öffnen.
2. „Events & Anwesenheit“ anklicken.
3. „Event erstellen“ anklicken.
4. Schritt „Grunddaten“ öffnen.
5. Titel, Sportart, Verein und Testteam eingeben.
6. Schritt „Zeit“ öffnen.
7. Start, Ende und Zeitzone eintragen.
8. Schritt „Details“ öffnen.
9. Ort, Beschreibung und Teilnahmeoptionen eintragen.
10. Schritt „Review“ öffnen.
11. Angaben prüfen.
12. Event speichern und veröffentlichen.

### Schritte App Sportler

1. Handy 1 öffnen.
2. Menü > „Events & Training“ öffnen.
3. Testevent auswählen.
4. Datum, Uhrzeit, Ort und Team prüfen.
5. „Zusage“ antippen.
6. Optional Kommentar oder Rückfrage schreiben.
7. Event schließen und erneut öffnen.
8. Zusagestatus prüfen.

### Schritte Trainer

1. Trainergerät aktualisieren.
2. Event öffnen.
3. Anwesenheitsliste öffnen.
4. Sportler mit „Zusage“ prüfen.
5. Bei einem Testtermin Anwesenheit markieren.
6. Speichern.

### Erwartetes Ergebnis

Event, RSVP und Anwesenheit sind auf Sportler-, Trainer- und Vereinsansicht konsistent.

### Status

Bestanden / Fehler / Nachtest: ________.

## UC-11: Sportler erstellt Feed-Beitrag und reagiert auf Inhalt

### Ziel

Ein Nutzer veröffentlicht einen Beitrag, kommentiert, liked, markiert hilfreich und meldet einen Testinhalt.

### Voraussetzungen

1. Sportler ist angemeldet.
2. Feed-Berechtigung ist vorhanden.

### Schritte Web

1. Laptop 2 als Sportler öffnen.
2. Seitenleiste „Mein Sport“ > „Feed“ anklicken.
3. Im Composer den Testtext eingeben.
4. Testbild oder ungefährliche Datei anhängen.
5. „Veröffentlichen“ anklicken.
6. Eigenen Beitrag suchen.
7. Beitrag öffnen.
8. „Bearbeiten“ anklicken, Text ändern und speichern.
9. Beitrag liken.
10. „Hilfreich“ anklicken.
11. Kommentar schreiben und absenden.
12. Kommentar bearbeiten.
13. Kommentar löschen.
14. Beitrag über das Drei-Punkte-Menü löschen.
15. Löschbestätigung bestätigen.

### Schritte App

1. Handy 1 öffnen.
2. Unten „Feed“ antippen.
3. Testbeitrag schreiben und veröffentlichen.
4. Beitrag öffnen.
5. Like, hilfreich und Kommentar testen.
6. Über das Beitragsmenü einen Testbeitrag melden.
7. Testgrund auswählen und absenden.

### Erwartetes Ergebnis

Der Beitrag ist sichtbar, Bearbeiten/Löschen betrifft nur eigene Inhalte, Reaktionen werden gezählt und eine Meldung wird an die Moderation weitergegeben.

### Gegenprüfung

1. Mit einem zweiten Konto den Feed öffnen.
2. Sichtbarkeit, Like, Kommentar und Benachrichtigung prüfen.
3. Status: ________.

## UC-12: Trainer und Sportler schreiben im Chat

### Ziel

Zwei Nutzer tauschen eine Nachricht aus und sehen den Lesestatus sowie den Ungelesen-Zähler.

### Voraussetzungen

1. Sportler und Trainer sind aktiv.
2. Beide Konten dürfen Nachrichten verwenden.

### Schritte Sportler – App

1. Handy 1 öffnen.
2. Oben auf „Nachrichten“ tippen.
3. Vorhandenen Trainerchat öffnen oder neue Unterhaltung starten.
4. Nachricht „Testnachricht Sportler“ eingeben.
5. Senden antippen.
6. Prüfen, ob die Nachricht im Chat erscheint.

### Schritte Trainer – Web

1. Laptop 2 als Trainer öffnen.
2. Oben das Nachrichten-Symbol anklicken.
3. Den Chat öffnen.
4. Nachricht lesen.
5. „Testantwort Trainer“ eingeben.
6. Antwort senden.

### Gegenprüfung

1. Sportlergerät aktualisieren.
2. Ungelesen-Zähler prüfen.
3. Antwort öffnen und Lesestatus prüfen.
4. Gruppenchat und Teilnehmerverwaltung testen, falls angezeigt.

### Erwartetes Ergebnis

Nachrichten werden dem richtigen Gespräch zugeordnet. Ungelesen-Zähler und Lesestatus aktualisieren sich.

### Status

Bestanden / Fehler / Nachtest: ________.

## UC-13: Verein teilt eine Datei mit dem Team

### Ziel

Der Verein lädt ein Dokument hoch, teilt es mit einem Team und Sportler/Trainer können es öffnen.

### Voraussetzungen

1. Verein und Team existieren.
2. Test-PDF oder Testbild liegt bereit.

### Schritte Verein – Web

1. Laptop 1 öffnen und als Verein-Admin anmelden.
2. Seitenleiste „Dateien“ anklicken.
3. Bereich „Verein“ auswählen.
4. „Ordner erstellen“ anklicken.
5. Ordnername „Testdokumente“ eingeben.
6. Ordner speichern und öffnen.
7. „Datei hochladen“ anklicken.
8. Testdatei auswählen.
9. Upload abwarten.
10. Datei öffnen oder Vorschau anzeigen.
11. „Teilen“ anklicken.
12. Zieltyp „Team“ auswählen.
13. Testteam auswählen.
14. Freigabe speichern.

### Schritte Trainer – App

1. Handy 2 öffnen.
2. Menü > „Dateien“ oder „Teamdateien“ öffnen.
3. Testteam auswählen.
4. Ordner „Testdokumente“ öffnen.
5. Datei antippen.
6. Inhalt oder Vorschau prüfen.

### Schritte Sportler

1. Handy 1 öffnen.
2. „Vereine & Teams“ > Testteam öffnen.
3. Teamdateien öffnen.
4. Datei anzeigen.
5. Datei herunterladen, falls angeboten.

### Erwartetes Ergebnis

Nur freigegebene Personen sehen das Dokument. Umbenennen, Teilen und Löschen funktionieren gemäß Berechtigung.

### Status

Bestanden / Fehler / Nachtest: ________.

## UC-14: Sportler findet Sportpartner und verwaltet Freunde

### Ziel

Ein Sportler sucht einen passenden Sportpartner, sendet eine Anfrage und nimmt eine Freundschaftsanfrage an.

### Voraussetzungen

1. Zwei Sportler-Testkonten existieren.
2. Sportart, Ort und Testprofile sind angelegt.

### Schritte Sport-Matching

1. Handy 1 als Sportler öffnen.
2. Menü > „Sport-Matching“ öffnen.
3. Modus „Sportpartner“ auswählen.
4. Sportart auswählen.
5. Radius und Niveau eingeben.
6. „Suchen“ antippen.
7. Ergebnis öffnen.
8. Anfrage senden.
9. Zweites Sportlerkonto öffnen.
10. Anfrage annehmen oder ablehnen.

### Schritte Freunde

1. Web oder App > „Freunde“ öffnen.
2. E-Mail des zweiten Testkontos eingeben.
3. „Einladen“ anklicken oder antippen.
4. Zweites Konto öffnen.
5. Freundschaftsanfrage annehmen.
6. Freundesliste öffnen.
7. Freundschaft prüfen.
8. Menü der Freundschaft öffnen und Meldung/Entfernen nur mit Testdaten prüfen.

### Erwartetes Ergebnis

Matching-Anfragen und Freundschaften erscheinen beim richtigen Empfänger und erzeugen Benachrichtigungen.

### Status

Bestanden / Fehler / Nachtest: ________.

## UC-15: Sportler erfasst Ernährung und Wasser

### Ziel

Ein Sportler erfasst Mahlzeit, Wasser und ein Ernährungsziel; die Tagesübersicht wird aktualisiert.

### Schritte Web

1. Laptop 2 als Sportler öffnen.
2. „Mein Sport“ > „Ernährung“ anklicken.
3. „Schnell erfassen“ anklicken.
4. Titel „Testmahlzeit“ eingeben.
5. Menge und Kalorien eingeben.
6. Makronährstoffe prüfen.
7. Mahlzeit speichern.
8. „Wasser“ oder „Trinken“ öffnen.
9. Testmenge eintragen.
10. Wasserziel öffnen und Testziel speichern.
11. „Lebensmittel finden“ öffnen.
12. Testlebensmittel suchen und Ergebnis prüfen.
13. Mahlzeit bearbeiten.
14. Mahlzeit wieder löschen.

### Schritte App

1. Handy 1 öffnen.
2. Unten „Ernährung“ antippen.
3. Mahlzeit anlegen und speichern.
4. Wasser über „Trinken“ hinzufügen.
5. Tageszusammenfassung prüfen.

### Erwartetes Ergebnis

Die Tageswerte erhöhen sich nach dem Speichern und verringern sich nach dem Löschen. Ziel und Einträge bleiben nach erneutem Öffnen erhalten.

### Status

Bestanden / Fehler / Nachtest: ________.

## UC-16: Sportler plant eine Route und prüft Live-Tracking

### Ziel

Ein Sportler plant eine Teststrecke, speichert sie und prüft Standort-/Tracking-Funktionen.

### Voraussetzungen

1. Handy hat Standortfreigabe nur für die App-Testdauer.
2. Eine sichere Testumgebung ohne Veröffentlichung privater Orte.

### Schritte Web

1. „Mein Sport“ > „Sportkarte“ öffnen.
2. „Route planen“ anklicken.
3. Zwei oder mehr Testpunkte auf der Karte setzen.
4. Einen Punkt entfernen.
5. Route speichern.
6. Namen „Teststrecke“ eingeben.
7. Gespeicherte Route öffnen.
8. Route bearbeiten oder löschen, falls angeboten.

### Schritte App

1. Handy 1 öffnen.
2. Menü > „Sportkarte“ öffnen.
3. „Live-Tracking“ auswählen.
4. Standortberechtigung nur während der Nutzung erlauben.
5. Tracking starten.
6. Position, Zeit und Distanz prüfen.
7. Tracking stoppen.
8. Ergebnis speichern oder verwerfen.
9. Route oder Track öffnen.

### Erwartetes Ergebnis

Kartenpunkte, Route, Track und Live-Werte werden korrekt angezeigt. Nach dem Stoppen läuft das Tracking nicht weiter.

### Status

Bestanden / Fehler / Nachtest: ________.

## UC-17: Sportler nimmt an Kurs teil und erhält Badge

### Ziel

Ein Nutzer startet einen Kurs, speichert Fortschritt und sieht Badges/Zertifikate.

### Schritte

1. Web oder App > „Meine Kurse“ beziehungsweise Menü > „Kurse“ öffnen.
2. Testkurs auswählen.
3. Kursdetails und Anbieter prüfen.
4. „Kurs starten“ anklicken oder antippen.
5. Erste Lektion öffnen.
6. Inhalt bis zum Ende lesen beziehungsweise testen.
7. Lektion als abgeschlossen markieren.
8. Zur Kursübersicht zurückkehren.
9. Fortschrittsbalken prüfen.
10. „Meine Badges“ öffnen.
11. Badge und Fortschritt prüfen.
12. Bei abgeschlossenem Kurs Zertifikat öffnen.

### Erwartetes Ergebnis

Der Lernfortschritt wird gespeichert. Badge und Zertifikat erscheinen nur bei den vorgesehenen Bedingungen.

### Status

Bestanden / Fehler / Nachtest: ________.

## UC-18: Sportler verwaltet ein Outfit-Abo

### Ziel

Ein Sportler pflegt das Style-Profil und prüft Pause, Fortsetzen und Kündigung eines Test-Abos.

### Schritte

1. Seitenleiste oder App-Menü „Outfit-Abo“ öffnen.
2. Style-Profil öffnen.
3. Größen, Farben und Präferenzen mit Testwerten eintragen.
4. Style-Profil speichern.
5. Lieferübersicht öffnen.
6. Testlieferung und Status prüfen.
7. Abo-Details öffnen.
8. „Pausieren“ auswählen und bestätigen.
9. Status „Pausiert“ prüfen.
10. „Fortsetzen“ auswählen.
11. Status „Aktiv“ prüfen.
12. Kündigung nur bei einem Test-Abo öffnen und Bestätigungsdialog prüfen.

### Erwartetes Ergebnis

Style-Profil und Abo-Status bleiben nach Neuladen erhalten. Aktionen zeigen eine eindeutige Bestätigung.

### Status

Bestanden / Fehler / Nachtest: ________.

## UC-19: Käufer legt ein Marketplace-Produkt in den Warenkorb

### Ziel

Ein Käufer findet ein Angebot, legt es in den Warenkorb und prüft den Bestellstatus mit einer Testzahlung.

### Schritte

1. Web oder App > „Marketplace“ öffnen.
2. Testprodukt oder Testkurs auswählen.
3. Produktbild, Beschreibung, Preis, Anbieter und Hinweise prüfen.
4. „In den Warenkorb“ auswählen.
5. Warenkorb öffnen.
6. Menge prüfen und gegebenenfalls ändern.
7. Checkout starten.
8. Test-Zahlungsart auswählen.
9. Zahlungs- und Bestellübersicht prüfen.
10. Bestellung bestätigen.
11. Erfolgsseite oder Überweisungsanweisung öffnen.
12. Bestellstatus öffnen.
13. Bestellung und E-Mail-Bestätigung prüfen, falls eingerichtet.

### Erwartetes Ergebnis

Warenkorb, Checkout, Bestellung und Status gehören zum richtigen Konto. Bei abgebrochenem Checkout wird keine Bestellung fälschlich als bezahlt angezeigt.

### Status

Bestanden / Fehler / Nachtest: ________.

## UC-20: Verein erstellt und veröffentlicht sein Profil

### Ziel

Ein Verein legt Basisdaten, Bilder, Sichtbarkeit und öffentliche Vorschau an.

### Voraussetzungen

1. Verein-Admin darf Vereine erstellen.
2. Testlogo und Testtitelbild liegen vor.

### Schritte Web

1. Laptop 1 als Verein-Admin öffnen.
2. „Vereine & Teams“ anklicken.
3. „Verein registrieren“ anklicken.
4. Vereinsname und Kurzname eingeben.
5. Kontakt- und Standortdaten als Testdaten eingeben.
6. Sportarten auswählen.
7. Beschreibung eingeben.
8. Sichtbarkeit auf öffentlich setzen.
9. Vereinslogo und Titelbild hochladen.
10. Vereinsnummer als Testnummer eintragen.
11. „Verein erstellen“ anklicken.
12. Vereinsprofil öffnen.
13. Änderungen speichern.
14. Öffentliche Vorschau öffnen.
15. Prüfen, ob nur freigegebene Daten sichtbar sind.

### Schritte App

1. Vereins-App öffnen.
2. „Vereins-Cockpit“ antippen.
3. „Profil bearbeiten“ auswählen.
4. Basisdaten und Testbild speichern.
5. Profilvorschau öffnen.

### Erwartetes Ergebnis

Der Verein ist intern vollständig und öffentlich korrekt dargestellt.

### Status

Bestanden / Fehler / Nachtest: ________.

## UC-21: Verein fügt ein Mitglied per E-Mail hinzu

### Ziel

Der Verein erfasst ein externes Mitglied und versendet eine Einladung zur Verknüpfung.

### Voraussetzungen

1. Verein-Admin öffnet „Mitglieder & Finanzen“.
2. Test-E-Mail ist verfügbar.

### Schritte

1. Laptop 1 mit dem Vereins-Admin öffnen.
2. „Vereinsverwaltung“ > „Mitglieder & Finanzen“ öffnen.
3. Testverein auswählen.
4. Tab „Mitglieder“ öffnen.
5. „Mitglied hinzufügen“ anklicken.
6. Name und Test-E-Mail eingeben.
7. Vereinsrolle „Mitglied“ auswählen.
8. Status auswählen.
9. Mitgliedsnummer und Beitrag als Testwerte eintragen.
10. „Einladung senden“ aktivieren.
11. Mitglied speichern.
12. Externe-Mitglieder-Liste prüfen.
13. Testpostfach öffnen und Einladungslink anklicken.
14. Mit dem Testkonto anmelden oder Konto verknüpfen.
15. Zur Vereinsverwaltung zurückkehren.
16. Verknüpfung und Mitgliedsstatus prüfen.

### Erwartetes Ergebnis

Das externe Mitglied wird angelegt. Einladung und Verknüpfung sind nachvollziehbar; es entsteht kein doppeltes Mitglied.

### Status

Bestanden / Fehler / Nachtest: ________.

## UC-22: Verein importiert mehrere Mitglieder

### Ziel

Der Verein lädt eine Vorlage herunter, importiert Testmitglieder und prüft die Validierung.

### Schritte

1. „Mitglieder & Finanzen“ öffnen.
2. „Excel-Vorlage“ anklicken.
3. Vorlage herunterladen.
4. Zwei oder drei Testzeilen eintragen.
5. Eine absichtlich fehlerhafte Zeile ergänzen.
6. Datei speichern.
7. „Importieren“ anklicken.
8. Datei auswählen.
9. Vorschau und Warnungen prüfen.
10. Fehlerhafte Zeile korrigieren.
11. Import erneut starten.
12. „Einladungen senden“ nur mit Test-E-Mail aktivieren.
13. Import bestätigen.
14. Tab „Mitglieder“ öffnen.
15. Alle importierten Personen suchen.
16. Status, Rolle und Beitrag prüfen.

### Erwartetes Ergebnis

Fehlerhafte Zeilen werden verständlich gemeldet. Korrekte Zeilen werden einmalig importiert. Die Personen erscheinen in der Mitgliederliste.

### Status

Bestanden / Fehler / Nachtest: ________.

## UC-23: Verein bearbeitet Beitrittsanfrage und Mitgliedsdaten

### Ziel

Der Verein entscheidet über eine Anfrage und pflegt Status, Mitgliedsnummer, Lizenznummer und Beitrag.

### Schritte

1. Sportler mit Handy 1 eine Vereinsanfrage senden lassen.
2. Laptop 1 als Verein-Admin öffnen.
3. „Mitglieder & Finanzen“ > „Anfragen“ öffnen.
4. Anfrage öffnen.
5. „Annehmen“ anklicken.
6. Tab „Mitglieder“ öffnen.
7. Sportlerzeile suchen.
8. Bearbeiten öffnen.
9. Mitgliedsnummer als Testnummer eintragen.
10. Lizenznummer als Testnummer eintragen.
11. Beitrag und Intervall eintragen.
12. Nächstes Rechnungsdatum eintragen.
13. Eintrittsdatum und interne Notiz eintragen.
14. Status „Vereinsmitglied“ speichern.
15. Zeile erneut öffnen.
16. Alle Werte kontrollieren.

### Erwartetes Ergebnis

Die Mitgliedsdaten sind vollständig und die Änderung ist im Audit sichtbar.

### Status

Bestanden / Fehler / Nachtest: ________.

## UC-24: Verein legt Mitgliedschaftstyp und Beitragsregel an

### Ziel

Der Verein definiert einen Mitgliedschaftstyp und einen wiederkehrenden Beitrag.

### Schritte

1. „Mitglieder & Finanzen“ öffnen.
2. Tab „Beitragsregeln“ anklicken.
3. „Mitgliedschaftstyp hinzufügen“ auswählen.
4. Name „Test Standardmitgliedschaft“ eingeben.
5. Beschreibung und Sichtbarkeit eintragen.
6. „Typ speichern“ anklicken.
7. „Neue Beitragsregel“ auswählen.
8. Mitgliedschaftstyp auswählen.
9. Gültigkeitsbeginn eingeben.
10. Intervall „monatlich“ oder „jährlich“ auswählen.
11. Testbetrag eingeben.
12. Altersbereich nur mit Testwerten füllen.
13. Regel speichern.
14. Aktive und historische Regeln prüfen.
15. Testmitglied dem neuen Typ zuordnen.

### Erwartetes Ergebnis

Der Typ und die Regel erscheinen in der richtigen Liste. Die Regel ist nur innerhalb ihrer Gültigkeit aktiv.

### Status

Bestanden / Fehler / Nachtest: ________.

## UC-25: Verein erstellt Rechnung, erfasst Zahlung und sendet Erinnerung

### Ziel

Der Verein erstellt eine Testrechnung, erfasst eine Zahlung, prüft Status und versendet eine Erinnerung.

### Schritte

1. „Mitglieder & Finanzen“ > „Mitglieder“ öffnen.
2. Testmitglied auswählen.
3. „Rechnung erstellen“ anklicken.
4. Titel, Betrag, Zeitraum und Fälligkeit eingeben.
5. Rechnung speichern.
6. Tab „Rechnungen“ öffnen.
7. Neue Rechnung suchen.
8. Status „Offen“ prüfen.
9. Rechnung öffnen.
10. „Zahlung erfassen“ anklicken.
11. Testbetrag, Datum und Zahlungsmethode eintragen.
12. Zahlung speichern.
13. Status „Bezahlt“ prüfen.
14. Für eine zweite Testrechnung Status „Überfällig“ setzen.
15. „Erinnerung/Mahnung senden“ anklicken.
16. Bestätigungsnachricht und Benachrichtigung prüfen.
17. Sportlerkonto öffnen und Rechnungsinformation prüfen, falls freigegeben.

### Erwartetes Ergebnis

Rechnung und Zahlung werden korrekt verknüpft. Eine bezahlte Rechnung wird nicht erneut als offen angezeigt. Erinnerung wird protokolliert.

### Status

Bestanden / Fehler / Nachtest: ________.

## UC-26: Verein prüft Finanzen, Bankabgleich, SEPA und DATEV

### Ziel

Der Verein kontrolliert Kassenbuch, Bankimport und planabhängige Exporte mit anonymisierten Testdaten.

### Voraussetzungen

1. Keine echten Bankdaten verwenden.
2. Abo-Plan und verfügbare Berechtigungen vorher notieren.

### Kassenbuch

1. „Mitglieder & Finanzen“ > „Finanzen“ öffnen.
2. „Einnahme/Ausgabe erfassen“ anklicken.
3. Kontotyp, Kategorie, Titel, Betrag und Buchungsdatum eingeben.
4. Testreferenz und Beschreibung eingeben.
5. Speichern.
6. Kassenbuch öffnen.
7. Bestand und Buchung prüfen.

### Bankabgleich

1. „Bankabgleich“ öffnen.
2. Anonymisierte Test-CSV auswählen.
3. Import starten.
4. Vorgeschlagene Zuordnungen prüfen.
5. Eine Zuordnung manuell bestätigen.
6. Zahlung und Rechnung erneut prüfen.

### SEPA

1. Tab „SEPA & DATEV“ öffnen.
2. Test-Gläubiger-ID und Testkonto eintragen.
3. Speichern.
4. Mandat eines Testmitglieds prüfen.
5. „SEPA-Lastschrift exportieren“ anklicken.
6. Exportdatei nur in der Testumgebung öffnen.
7. Betrag, Mandatsreferenz und Status prüfen.

### DATEV/SKR42

1. Test-Beraternummer und Mandantennummer eintragen.
2. Testkonten und Zeitraum eintragen.
3. „DATEV/SKR42 exportieren“ anklicken.
4. Datei öffnen.
5. Spalten, Beträge, Konten und Datumsbereich prüfen.

### Erwartetes Ergebnis

Buchungen werden korrekt gespeichert. Planabhängige Funktionen zeigen bei fehlender Freischaltung einen verständlichen Hinweis und führen keinen unvollständigen Export aus.

### Status

Bestanden / Fehler / Nachtest: ________.

## UC-27: Verein prüft Audit-Log und Planlimits

### Ziel

Vereinsaktionen sind nachvollziehbar und Limit-/Premium-Hinweise sind verständlich.

### Schritte

1. Eine Teständerung an einem Mitglied durchführen.
2. Eine Testrechnung anlegen.
3. Eine Testzahlung erfassen.
4. „Mitglieder & Finanzen“ > „Audit“ öffnen.
5. Alle drei Aktionen suchen.
6. Zeitpunkt und handelndes Konto prüfen.
7. „Vereins-Cockpit“ öffnen.
8. Mitgliederlimit, Teamlimit und Speicherverbrauch prüfen.
9. Eine planabhängige Funktion öffnen.
10. Prüfen, ob ein Upgrade-Hinweis statt eines unklaren Fehlers erscheint.

### Erwartetes Ergebnis

Änderungen sind mit Zeit und Konto nachvollziehbar. Limits und gesperrte Funktionen werden verständlich angezeigt.

### Status

Bestanden / Fehler / Nachtest: ________.

## UC-28: Benachrichtigung führt zum richtigen Ziel

### Ziel

Ein Nutzer erhält eine Benachrichtigung zu Einladung, Antrag, Chat, Event oder Rechnung und gelangt über den Link zum richtigen Bereich.

### Schritte

1. Testevent oder Testnachricht mit dem Trainerkonto erzeugen.
2. Sportlergerät öffnen.
3. Glocke antippen.
4. Neue Benachrichtigung auswählen.
5. Zielseite prüfen.
6. Zurück zur Benachrichtigungsliste gehen.
7. Einzelne Meldung als gelesen markieren.
8. Eine zweite Meldung erzeugen.
9. „Alle als gelesen markieren“ auswählen.
10. Zähler prüfen.
11. Web-Topbar öffnen und denselben Test prüfen.

### Erwartetes Ergebnis

Jede Benachrichtigung öffnet genau den auslösenden Kontext. Der Ungelesen-Zähler ändert sich korrekt.

### Status

Bestanden / Fehler / Nachtest: ________.

## UC-29: Sportler verwaltet Einstellungen, Sprache, Datenschutz und Integrationen

### Ziel

Persönliche Einstellungen werden gespeichert und sensible Daten bleiben geschützt.

### Schritte

1. „Einstellungen“ öffnen.
2. Tab „Sprache“ öffnen und Deutsch speichern.
3. Optional Englisch auswählen und prüfen, ob sichtbare Navigation übersetzt wird.
4. Tab „Design“ öffnen und hell/dunkel wechseln.
5. Tab „Datenschutz“ öffnen.
6. Profil- und Sportdaten-Sichtbarkeit prüfen.
7. Privatsphäre speichern.
8. Tab „Sport-Apps & Gesundheitsdaten“ öffnen.
9. Testprovider verbinden oder vorhandene Verbindung anzeigen.
10. Synchronisation prüfen.
11. Testaktivität importieren.
12. Verbindung trennen, falls ein Testprovider verwendet wurde.
13. Tab „Aktivitäten“ öffnen.
14. Importierte Aktivität prüfen.
15. Aktivität bearbeiten und löschen.

### Erwartetes Ergebnis

Sprache, Design, Datenschutz und Integration bleiben nach Neuladen erhalten. Trennen entfernt den Zugriff, sofern vorgesehen.

### Status

Bestanden / Fehler / Nachtest: ________.

## UC-30: Minderjähriger Nutzer und Elternzustimmung

### Ziel

Ein minderjähriger Nutzer wird geschützt, bis ein Elternteil die Einwilligung erteilt oder ablehnt.

### Voraussetzungen

1. Minderjährigen-Testkonto mit Test-Geburtsdatum.
2. Eltern-Test-E-Mail.
3. Keine echten Familien- oder Gesundheitsdaten verwenden.

### Schritte Minderjährigenkonto

1. Registrierung mit Test-Geburtsdatum starten.
2. Eltern-E-Mail eingeben.
3. Registrierung abschließen.
4. Warteseite „Elternzustimmung“ prüfen.
5. Versuchen, eine soziale Funktion zu öffnen.
6. Prüfen, ob sie bis zur Zustimmung geschützt bleibt.

### Schritte Elternkonto

1. Elternzustimmungs-E-Mail öffnen.
2. Link anklicken.
3. Kind, Einwilligungstext und Version prüfen.
4. „Zustimmen“ anklicken.
5. Optional Elternlogin per Zugangscode prüfen.
6. Verknüpftes Kind öffnen.
7. Benachrichtigungen und Zustimmungsstatus prüfen.

### Gegenprüfung

1. Minderjährigenkonto erneut öffnen.
2. Soziale Funktion testen.
3. Elternkonto öffnen.
4. Zustimmung widerrufen.
5. Minderjährigenkonto aktualisieren.
6. Prüfen, ob Einschränkungen wieder gelten.

### Erwartetes Ergebnis

Ohne Zustimmung bleiben geschützte Funktionen eingeschränkt. Zustimmung und Widerruf ändern den Status nachvollziehbar.

### Status

Bestanden / Fehler / Nachtest: ________.

## UC-31: Verein veröffentlicht Beitrag, Event und Vereinsdokument

### Ziel

Der Verein kommuniziert eine Nachricht über Feed, Event und Dokumente an die richtigen Personen.

### Schritte

1. Laptop 1 als Verein-Admin öffnen.
2. Feed öffnen.
3. Vereinsbeitrag „Test Vereinsnews“ schreiben.
4. Beitrag veröffentlichen.
5. „Events & Anwesenheit“ öffnen.
6. Vereins- oder Teamevent mit Testdaten anlegen.
7. Event veröffentlichen.
8. „Dateien“ öffnen.
9. Testdokument in Vereinsbereich hochladen.
10. Dokument mit Team teilen.
11. Handy 1 als Sportler öffnen.
12. Feedbeitrag, Event und Dokument prüfen.
13. Trainergerät öffnen.
14. Teamzugriff und Dokument prüfen.

### Erwartetes Ergebnis

Vereinsinhalte erscheinen nur in den freigegebenen Zielgruppen. Event und Dokument sind nicht automatisch für private oder fremde Vereine sichtbar.

### Status

Bestanden / Fehler / Nachtest: ________.

## UC-32: Sponsoring- oder Commerce-Angebot als Entwurf anlegen

### Ziel

Ein Verein oder Anbieter erstellt ein Testangebot beziehungsweise eine Kampagne, ohne es versehentlich live zu schalten.

### Schritte

1. „Sponsoren“ oder „Commerce“ öffnen.
2. „Kampagne/Angebot erstellen“ auswählen.
3. Titel, Beschreibung und Test-Ziel-URL eingeben.
4. Budget oder Preis nur als Testwert eintragen.
5. Laufzeit eintragen.
6. Speichern.
7. Status „Entwurf“ oder „In Prüfung“ prüfen.
8. Vorschau öffnen.
9. Keine Live-Aktivierung ausführen.
10. Testentwurf wieder löschen oder deaktivieren.

### Erwartetes Ergebnis

Der Entwurf bleibt unsichtbar für die Öffentlichkeit, bis eine ausdrücklich erforderliche Freigabe erfolgt.

### Status

Bestanden / Fehler / Nachtest: ________.

## UC-33: Plattform-Admin prüft Nutzer, Rollen und Moderation

### Ziel

Ein Plattform-Admin kann Verwaltung und Moderation öffnen; normale Nutzer können diese Bereiche nicht öffnen.

### Voraussetzungen

1. Plattform-Admin-Testkonto ist vorhanden.
2. Moderations-Testmeldung existiert.

### Schritte Admin – Web

1. Mit Plattform-Admin anmelden.
2. Seitenleiste „Admin“ öffnen.
3. „Users“ öffnen.
4. Sportlerkonto suchen.
5. Profil und Status prüfen.
6. „Rollen & Rechte“ öffnen.
7. Testrolle und Berechtigung nur anzeigen oder mit Testwert ändern.
8. „Moderation“ öffnen.
9. Testmeldung öffnen.
10. Status und Entscheidung prüfen.
11. „Vereinsprüfung“, „Sportarten“, „Badges“ und „Gamification“ öffnen.
12. Nur Testobjekte anlegen oder bearbeiten.

### Schritte App

1. App mit Plattform-Admin öffnen.
2. Menü-Symbol antippen.
3. „Admin“ auswählen.
4. „Nutzer“, „Rollen & Rechte“ oder „Moderation“ öffnen.

### Gegenprüfung normaler Nutzer

1. Sportlerkonto öffnen.
2. Versuchen, „Admin“ oder „Rollen & Rechte“ aufzurufen.
3. Prüfen, dass Zugriff verweigert wird oder der Menüpunkt nicht erscheint.

### Erwartetes Ergebnis

Adminfunktionen sind rollen- und berechtigungsabhängig geschützt. Entscheidungen werden protokolliert.

### Status

Bestanden / Fehler / Nachtest: ________.

## UC-34: Vollständiger Paralleltest über vier Geräte

### Ziel

Ein Ende-zu-Ende-Ablauf verbindet Sportler-App, Trainer-App, Vereins-Web und Gegenkonto-Web.

### Ablauf

1. Handy 1 als Sportler anmelden.
2. Handy 2 als Trainer anmelden.
3. Laptop 1 als Verein-Admin anmelden.
4. Laptop 2 als Sportler oder Plattform-Admin anmelden.
5. Handy 1 stellt einen Mitgliedsantrag.
6. Laptop 1 nimmt den Antrag an.
7. Handy 1 prüft die Annahmebenachrichtigung.
8. Handy 2 erstellt ein Training.
9. Handy 1 dokumentiert das Training.
10. Handy 2 gibt Feedback.
11. Laptop 2 prüft das Feedback oder die Benachrichtigung.
12. Laptop 1 erstellt ein Event.
13. Handy 1 sagt zu.
14. Handy 2 prüft Anwesenheit.
15. Laptop 1 lädt eine Datei hoch und teilt sie mit dem Team.
16. Handy 2 öffnet die Teamdatei.
17. Laptop 1 erstellt eine Testrechnung.
18. Handy 1 prüft den Rechnungsstatus.
19. Laptop 1 erfasst die Testzahlung.
20. Handy 1 prüft den aktualisierten Status.
21. Alle Geräte öffnen Benachrichtigungen.
22. Alle Geräte abmelden.

### Erwartetes Ergebnis

Alle Aktionen werden zwischen Web und App korrekt synchronisiert. Keine Aktion erscheint beim falschen Verein, Team oder Benutzer.

### Status

Bestanden / Fehler / Nachtest: ________.

## UC-35: Fehler sauber aufnehmen und Nachtest durchführen

### Ziel

Jeder Fehler wird so dokumentiert, dass er reproduziert und erneut geprüft werden kann.

### Schritte

1. Use-Case-Nummer notieren.
2. Rolle und E-Mail-Testkonto notieren.
3. Gerät und Betriebssystem notieren.
4. Browsername oder App-Version notieren.
5. Internet/WLAN und API-Umgebung notieren.
6. Exakten Klickweg notieren.
7. Erwartetes Ergebnis notieren.
8. Tatsächliches Ergebnis notieren.
9. Fehlertext wörtlich kopieren.
10. Screenshot oder Bildschirmaufnahme speichern.
11. Uhrzeit und Zeitpunkt des letzten erfolgreichen Versuchs notieren.
12. Fehler einmal mit einem zweiten Konto wiederholen.
13. Fehler einmal in Web und einmal in App vergleichen.
14. Fehlerstatus auf „Fehler“ setzen.
15. Nach der Korrektur denselben Klickweg wiederholen.
16. Ergebnis auf „Bestanden“ oder „Nachtest“ setzen.

### Vorlage

Use Case: __________

Konto/Rolle: __________

Gerät/Web/App: __________

Klickweg: __________

Erwartet: __________

Tatsächlich: __________

Fehlertext: __________

Screenshot-Datei: __________

Status: __________

## UC-36: Gast besucht die öffentlichen Bereiche

### Ziel

Ein nicht angemeldeter Besucher kann die öffentlichen Funktionen ansehen und findet den Weg zu Registrierung, Login oder Kontakt.

### Schritte

1. Browser im privaten Fenster öffnen.
2. Startseite öffnen und Logo, Hauptversprechen und Einstiegsschaltflächen prüfen.
3. „Preise“ oder „Abos“ öffnen.
4. Zielgruppen Sportler und Verein prüfen.
5. „Vereine“ öffnen und nach einem Testverein suchen.
6. „Veranstaltungen“ öffnen und öffentliche Events prüfen.
7. „Marketplace“ öffnen und Produktliste prüfen.
8. Ein Marketplace-Produkt öffnen.
9. Anbieterprofil öffnen.
10. „Blog“ öffnen und Artikelliste prüfen.
11. Blogartikel öffnen.
12. „Jobs“ öffnen und Stellenliste prüfen.
13. „E-Learning“ öffnen.
14. „Gamification“ und „Top-Inhalte“ öffnen.
15. „Sponsoren“ öffnen.
16. „Werbeagentur für Vereine“ öffnen.
17. Kontakt-/Meldeformular öffnen.
18. Sprache wechseln.
19. Impressum, Datenschutz, AGB, Community-Richtlinien, Jugendschutz, Cookies und Widerruf öffnen.
20. Von jeder Seite „Registrieren“ oder „Anmelden“ öffnen.

### Erwartetes Ergebnis

Öffentliche Inhalte sind ohne Login erreichbar. Geschützte Aktionen verlangen Anmeldung. Links, Sprache und rechtliche Seiten funktionieren.

### Status

Bestanden / Fehler / Nachtest: ________.

## UC-37: Besucher liest Blogartikel und verwendet öffentliche Inhalte

### Ziel

Ein Besucher findet, filtert und öffnet Blogartikel und kann einen Artikel über eine direkte URL wieder aufrufen.

### Schritte

1. Öffentliche Seite „Blog“ öffnen.
2. Kategorien oder Filter prüfen.
3. Suchfeld verwenden, falls vorhanden.
4. Artikelkarte anklicken.
5. Titel, Autor, Datum, Kategorie, Vorschaubild und Inhalt prüfen.
6. Bilder im Artikel öffnen oder vergrößern.
7. Externe Links prüfen.
8. Zurück zur Blogliste gehen.
9. Einen Artikel über seine direkte URL öffnen.
10. Nicht vorhandenen Slug öffnen und Fehlerseite prüfen.
11. Artikel auf Handy-Browser oder App-Gastbereich öffnen.

### Erwartetes Ergebnis

Artikel, Bilder und Kategorien werden korrekt geladen. Ein ungültiger Artikel führt zu einer verständlichen Fehlerseite und nicht zu einem Serverfehler.

### Status

Bestanden / Fehler / Nachtest: ________.

## UC-38: Admin erstellt Kategorie und Blogartikel

### Ziel

Die Redaktion kann Kategorien und Blogartikel als Entwurf anlegen, Vorschau prüfen, bearbeiten und löschen.

### Voraussetzungen

1. Plattformkonto mit Blog-Berechtigung.
2. Testbild und Testtext.

### Schritte Web

1. Als Admin anmelden.
2. Seitenleiste „Admin“ öffnen.
3. „Blog-Kategorien“ anklicken.
4. „Kategorie erstellen“ anklicken.
5. Name, Slug, Beschreibung und Aktivstatus eingeben.
6. Kategorie speichern.
7. Kategorie bearbeiten und speichern.
8. „Blogs“ öffnen.
9. „Neuen Blogbeitrag erstellen“ anklicken.
10. Titel, Slug, Teaser und Hauptinhalt eingeben.
11. Kategorie auswählen.
12. Beitragsbild hochladen.
13. Über „Content-Bild hochladen“ ein Testbild in den Inhalt einfügen.
14. Status als Entwurf oder geplant speichern.
15. Vorschau öffnen.
16. Vorschau auf Desktop und Mobilbreite prüfen.
17. Beitrag bearbeiten.
18. Beitrag veröffentlichen, wenn die Oberfläche den Status anbietet.
19. Öffentliche Blogseite öffnen und Beitrag prüfen.
20. Testbeitrag löschen.
21. Testkategorie löschen, sofern sie nicht mehr verwendet wird.

### Erwartetes Ergebnis

Entwurf, Vorschau und öffentlicher Artikel unterscheiden sich korrekt. Löschen erfordert die passende Berechtigung und Bestätigung.

### Status

Bestanden / Fehler / Nachtest: ________.

## UC-39: Feed-Story erstellen, ansehen, reagieren und löschen

### Ziel

Ein Nutzer erstellt eine Story, ein anderer Nutzer sieht sie, reagiert darauf und die Story kann ablaufen oder gelöscht werden.

### Schritte

1. Sportler-App oder Web-Feed öffnen.
2. Story-Composer öffnen.
3. Testbild oder kurzen Testtext einfügen.
4. Story veröffentlichen.
5. Story-Leiste öffnen.
6. Eigene Story ansehen.
7. Zur nächsten Story wechseln.
8. Story als angesehen prüfen.
9. Reaktion auswählen.
10. Mit dem zweiten Konto den Feed öffnen.
11. Story öffnen.
12. Reaktion und Aufrufstatus prüfen.
13. Eigene Teststory löschen.
14. Prüfen, dass sie nicht mehr in der Story-Leiste erscheint.

### Erwartetes Ergebnis

Stories, Aufrufe und Reaktionen werden dem richtigen Nutzer zugeordnet. Löschen entfernt nur die eigene Teststory.

### Status

Bestanden / Fehler / Nachtest: ________.

## UC-40: Profile folgen, blockieren und empfehlen

### Ziel

Ein Nutzer kann ein anderes Profil ansehen, folgen, entfolgen, blockieren und eine Empfehlung oder Skill-Bestätigung abgeben.

### Schritte

1. Mit dem Sportlerkonto die globale Suche öffnen.
2. Testprofil suchen.
3. Profilseite öffnen.
4. Sichtbare Sportarten und Skills prüfen.
5. „Folgen“ anklicken.
6. Feed oder Profil aktualisieren und Followstatus prüfen.
7. „Entfolgen“ anklicken.
8. Einen Skill auswählen und „Bestätigen“ anklicken.
9. Empfehlung schreiben und absenden.
10. Mit dem Empfängerkonto Empfehlung öffnen.
11. Empfehlung annehmen.
12. Mit einem dritten Testprofil das ursprüngliche Profil blockieren.
13. Prüfen, ob Profil und soziale Aktionen gemäß Schutzregeln verborgen werden.
14. Blockierung wieder aufheben.

### Erwartetes Ergebnis

Follow, Empfehlung, Skill-Bestätigung, Blockierung und Aufhebung ändern den jeweiligen Status und erzeugen nur berechtigte Benachrichtigungen.

### Status

Bestanden / Fehler / Nachtest: ________.

## UC-41: Marketplace durchsuchen, Wunschliste und Anbieterprofil prüfen

### Ziel

Ein Besucher oder Käufer findet Produkte, filtert sie, speichert einen Wunsch und öffnet das Anbieterprofil.

### Schritte Web

1. Öffentliche Seite „Marketplace“ öffnen.
2. Produktarten wie Produkt, Kurs und Camp prüfen.
3. Suchfeld verwenden.
4. Kategorie- und Preisfilter verwenden, falls vorhanden.
5. Sortierung wechseln.
6. Produktdetail öffnen.
7. Bilder, Beschreibung, Preis, Liefer-/Teilnahmeinformationen und Anbieter prüfen.
8. Herz-/Wunschlisten-Schaltfläche anklicken.
9. „Wunschliste“ öffnen.
10. Produkt aus der Wunschliste entfernen.
11. Anbieterprofil anklicken.
12. Anbieterbeschreibung und weitere Angebote prüfen.

### Schritte App

1. Menü > „Marketplace“ öffnen.
2. Produkt antippen.
3. Produkt speichern oder zur Wunschliste hinzufügen, falls sichtbar.
4. Anbieterprofil öffnen.
5. Zur Produktliste zurückkehren.

### Erwartetes Ergebnis

Filter, Produktdetails, Wunschliste und Anbieterprofil bleiben konsistent. Ein Gast kann ansehen, für Kauf oder Wunschliste wird gegebenenfalls Login verlangt.

### Status

Bestanden / Fehler / Nachtest: ________.

## UC-42: Marketplace-Kauf, Gast-Checkout, Überweisung und Bestellstatus

### Ziel

Ein Käufer führt einen Testkauf durch, prüft Erfolg, Abbruch, Überweisung und den öffentlichen Bestellstatus.

### Schritte

1. Testprodukt öffnen.
2. „Kaufen“ oder „Checkout“ anklicken.
3. Käuferdaten und Test-Liefer-/Kontaktadresse eingeben.
4. Preis, Steuer, Versand und Gesamtbetrag prüfen.
5. Testzahlung auswählen oder Überweisung starten.
6. Kauf bestätigen.
7. Erfolgsseite öffnen.
8. Bestellnummer notieren.
9. Überweisungsseite öffnen, falls diese Zahlungsart gewählt wurde.
10. Bankdaten und Verwendungszweck prüfen.
11. Bestellstatus über den erhaltenen Link öffnen.
12. Status „offen“, „bezahlt“ oder „in Bearbeitung“ prüfen.
13. Einen zweiten Testcheckout starten.
14. Checkout abbrechen.
15. Prüfen, dass der abgebrochene Auftrag nicht als bezahlt erscheint.
16. Als Gast den Retouren-/Rückgabeweg öffnen, wenn das Produkt ihn anbietet.
17. Rückgabegrund als Testwert eintragen.
18. Rückgabe senden.

### Gegenprüfung

1. Admin- oder Anbieter-Konto öffnen.
2. Bestellung suchen.
3. Käufer, Betrag und Status prüfen.

### Erwartetes Ergebnis

Erfolg, Abbruch, Überweisung, Status und Rückgabe unterscheiden sich korrekt. Es wird kein doppelter Auftrag erzeugt.

### Status

Bestanden / Fehler / Nachtest: ________.

## UC-43: Marketplace-Verkäufer bewirbt sich und reicht ein Produkt ein

### Ziel

Ein Anbieter beantragt Verkäuferzugang, reicht ein Produkt ein und verfolgt den Prüfstatus.

### Voraussetzungen

1. Konto mit Anbieterfunktion oder Testkonto.
2. Testproduktdaten und Testbilder.

### Schritte

1. „Marketplace“ oder „Commerce“ öffnen.
2. „Verkäufer werden“ oder Verkäuferbereich öffnen.
3. Anbietername, Beschreibung, Kontakt und Auszahlungsdaten als Testdaten eintragen.
4. Verkäuferantrag absenden.
5. Status „In Prüfung“ prüfen.
6. Als Admin „Admin“ > „Commerce“ öffnen.
7. Verkäuferantrag suchen.
8. Antrag öffnen und Status ändern.
9. Als Anbieter aktualisieren.
10. „Eigenes Produkt einreichen“ anklicken.
11. Titel, Kategorie, Beschreibung, Preis, Steuer, Bestand und Versand eintragen.
12. Produktbilder und Testdokumente hochladen.
13. Produkt als Entwurf speichern.
14. Produkt bearbeiten.
15. Zur Prüfung einreichen.
16. Admin-Ansicht aktualisieren und Produktstatus prüfen.

### Erwartetes Ergebnis

Antrag und Produktstatus sind getrennt nachvollziehbar. Entwürfe sind nicht öffentlich, solange keine Freigabe erfolgt.

### Status

Bestanden / Fehler / Nachtest: ________.

## UC-44: Admin-Commerce verwaltet Produkt, Bestand, Steuer, Versand und Coupon

### Ziel

Der Admin konfiguriert Marketplace-Betrieb und überprüft die Auswirkungen im Checkout.

### Schritte

1. Als Plattform-Admin „Admin“ > „Commerce“ öffnen.
2. Marketplace-Visuals prüfen und Teständerung speichern.
3. Marketplace-Provisionen prüfen und Testwert speichern.
4. „Produkt anlegen“ öffnen.
5. Titel, Typ, Preis und Status eingeben.
6. Bestand eingeben.
7. Produkt speichern.
8. Bestand erhöhen.
9. Bestand verringern.
10. Produkt bearbeiten.
11. Produkt deaktivieren oder löschen, wenn es ein Testprodukt ist.
12. Versandrate anlegen.
13. Versandrate bearbeiten.
14. Steuerrate anlegen.
15. Steuerrate bearbeiten.
16. Coupon anlegen.
17. Couponwert, Gültigkeit und Bedingungen eingeben.
18. Coupon speichern und bearbeiten.
19. Commerce-Einstellungen öffnen und Testeinstellung prüfen.
20. CSV-Export öffnen und Download prüfen.
21. Öffentlichen Checkout mit Testprodukt öffnen.
22. Versand, Steuer und Coupon prüfen.

### Erwartetes Ergebnis

Bestand, Versand, Steuer, Coupon, Provision und Visuals wirken nachvollziehbar im öffentlichen Kaufprozess. Deaktivierte Produkte können nicht neu gekauft werden.

### Status

Bestanden / Fehler / Nachtest: ________.

## UC-45: Admin-Commerce bearbeitet Bestellung, Rückgabe und Auszahlung

### Ziel

Eine Bestellung wird verwaltet, bezahlt, versendet, bei Problemen bearbeitet, erstattet und als Auszahlung vorbereitet.

### Voraussetzungen

1. Testbestellung aus UC-42 existiert.
2. Keine echten Zahlungen oder Auszahlungen verwenden.

### Schritte

1. Admin > „Commerce“ öffnen.
2. Bestellung suchen.
3. Bestellansicht öffnen.
4. Rechnung herunterladen.
5. Gutschrift-Testdokument öffnen, falls eine Gutschrift vorliegt.
6. Überweisung als bezahlt markieren, falls zutreffend.
7. Versandstatus und Tracking-Testwert eintragen.
8. Bestellung speichern.
9. Eine Testproblemmeldung öffnen.
10. Issue-Status ändern.
11. Antwort an den Käufer schreiben.
12. Rückgabeantrag öffnen.
13. Rückgabestatus ändern.
14. Erstattung nur als Testaktion ausführen.
15. Auszahlungsempfänger öffnen.
16. Payout-Profil als Testprofil bearbeiten.
17. Auszahlung anlegen.
18. Auszahlung als bezahlt markieren.
19. Admin-Commerce erneut laden und Audit-/Statuswerte prüfen.

### Erwartetes Ergebnis

Bestellung, Versand, Problem, Rückgabe, Erstattung und Auszahlung besitzen getrennte nachvollziehbare Status. Dokumente laden mit der richtigen Bestellnummer.

### Status

Bestanden / Fehler / Nachtest: ________.

## UC-46: Kursteilnehmer absolviert Kurs mit Lektion, Notiz, Quiz, Aufgabe und Bewertung

### Ziel

Ein Lernender nimmt an einem Kurs teil und nutzt alle Teilnehmerfunktionen.

### Voraussetzungen

1. Öffentlicher oder geschützter Testkurs existiert.
2. Kurs enthält Lektion, Quiz und Aufgabe.

### Schritte

1. „Meine Kurse“ oder „Kurse“ öffnen.
2. Testkurs öffnen.
3. Kursdetails, Preis und Voraussetzungen prüfen.
4. „Einschreiben“ oder „Kurs starten“ anklicken.
5. Lektion öffnen.
6. Fortschritt speichern.
7. Lektion als abgeschlossen markieren.
8. Notiz zur Lektion schreiben.
9. Lernkommentar schreiben.
10. Quiz öffnen.
11. Antworten auswählen.
12. Quiz absenden.
13. Ergebnis und Wiederholungsstatus prüfen.
14. Aufgabe öffnen.
15. Testantwort oder Testdatei einreichen.
16. Abgabe prüfen.
17. Eine Bewertung mit Teststernen und Testtext abgeben.
18. Kursübersicht öffnen.
19. Fortschrittsanzeige prüfen.
20. Zertifikat öffnen.
21. Zertifikat herunterladen.
22. Öffentliche Zertifikatsprüfung mit Code öffnen.

### App

1. Menü > „Kurse“ öffnen.
2. Kurs, Lektion, Quiz und Aufgabe nacheinander öffnen.
3. Notiz, Kommentar, Fortschritt und Abschluss jeweils speichern.

### Erwartetes Ergebnis

Einschreibung, Fortschritt, Notizen, Kommentare, Quiz, Aufgabe, Bewertung und Zertifikat werden am Kurs gespeichert und dem richtigen Lernenden zugeordnet.

### Status

Bestanden / Fehler / Nachtest: ________.

## UC-47: Trainer oder Redaktion erstellt Kurs im Lernstudio

### Ziel

Ein berechtigter Nutzer baut einen Kurs mit Abschnitten, Lektionen, Medien und Reihenfolge auf.

### Voraussetzungen

1. Berechtigung für „Sportschule“, „Lernstudio“ oder Content-Management.
2. Testbilder, Testvideo oder Test-PDF.

### Schritte

1. „Sportschule“ oder „Lernstudio“ öffnen.
2. „Kurs erstellen“ anklicken.
3. Titel, Beschreibung, Zielgruppe und Status eingeben.
4. Kurs speichern.
5. Abschnitt hinzufügen.
6. Abschnittstitel und Beschreibung eingeben.
7. Lektion hinzufügen.
8. Lektionstyp, Titel und Inhalt eingeben.
9. Testasset hochladen.
10. Lektion speichern.
11. Zweite Lektion hinzufügen.
12. Lektionen per Drag-and-drop oder „Reihenfolge“ umsortieren.
13. Eine Lektion bearbeiten.
14. Eine Testlektion löschen.
15. Kursvorschau öffnen.
16. Kursstatus als Entwurf speichern.
17. Testeinschreibung vergeben.
18. Einschreibung wieder entziehen.
19. Kursreport als CSV herunterladen.

### Erwartetes Ergebnis

Kurs, Abschnitt, Lektion, Asset und Reihenfolge werden korrekt gespeichert. Entwurf bleibt nicht öffentlich.

### Status

Bestanden / Fehler / Nachtest: ________.

## UC-48: Lernstudio verwaltet Quiz, Aufgaben, Kommentare und Coupon

### Ziel

Der Kursautor erstellt Bewertungs- und Kommunikationsfunktionen sowie einen Kurscoupon.

### Schritte

1. Testkurs im Lernstudio öffnen.
2. Quiz hinzufügen.
3. Frage und Antwortoptionen eintragen.
4. Richtige Antwort und Punkte speichern.
5. Quizvorschau öffnen.
6. Testquiz als Teilnehmer absolvieren.
7. Aufgabe hinzufügen.
8. Aufgabe, Abgabefrist und Bewertungslogik eintragen.
9. Testeinreichung als Teilnehmer senden.
10. Im Lernstudio Aufgabe öffnen.
11. Abgabe bewerten.
12. Punktzahl und Feedback speichern.
13. Teilnehmerkommentar öffnen.
14. Kommentar beantworten.
15. Kommentar als erledigt markieren.
16. Kurscoupon erstellen.
17. Code, Rabatt und Gültigkeit eingeben.
18. Coupon speichern.
19. Coupon im Checkout testen.
20. Kursqualität oder Review-Status öffnen.
21. Admin-Qualitätsstatus ändern, falls die Berechtigung vorhanden ist.

### Erwartetes Ergebnis

Quiz, Aufgaben, Bewertung, Kommentare und Coupons sind sichtbar, korrekt verknüpft und nur für berechtigte Personen bearbeitbar.

### Status

Bestanden / Fehler / Nachtest: ________.

## UC-49: Verein veröffentlicht Ankündigung, Umfrage und Vereinsdokument

### Ziel

Ein Verein informiert Mitglieder, lässt abstimmen und stellt Richtlinien-/Zustimmungsdokumente bereit.

### Schritte App oder Web

1. Vereins-Cockpit öffnen.
2. „Kommunikation“ oder „Ankündigungen“ öffnen, falls sichtbar.
3. Testankündigung erstellen.
4. Zielgruppe Verein oder Team auswählen.
5. Titel, Text und optional Testbild eingeben.
6. Ankündigung veröffentlichen.
7. „Umfrage“ oder „Survey“ öffnen.
8. Frage und Antwortoptionen eingeben.
9. Ablaufdatum als Testdatum setzen.
10. Umfrage veröffentlichen.
11. Sportlerkonto öffnen.
12. Ankündigung lesen.
13. Umfrage beantworten.
14. Ergebnis oder eigene Stimme prüfen.
15. Vereinsverwaltung > „Dokumente/Policy“ öffnen.
16. Testrichtlinie hochladen.
17. Dokument als sichtbar oder erforderlich markieren.
18. Mitgliedskonto öffnen.
19. Dokument öffnen und Zustimmung abgeben, falls erforderlich.

### Erwartetes Ergebnis

Ankündigung, Umfrage, Dokument und Zustimmung erreichen nur die ausgewählte Zielgruppe. Stimmen und Zustimmungen werden versioniert oder nachvollziehbar angezeigt, wenn dies die Funktion vorsieht.

### Status

Bestanden / Fehler / Nachtest: ________.

## UC-50: Team verwaltet Anwesenheit, Verfügbarkeit und Straf-/Gebührenregeln

### Ziel

Trainer oder Teammanager prüfen Anwesenheit, Abwesenheit und Testgebühren eines Teams.

### Schritte

1. Trainerkonto > „Teams“ öffnen.
2. Testteam öffnen.
3. Anwesenheitsstatistik öffnen.
4. Zeitraum oder Event auswählen.
5. Anwesenheitswerte prüfen.
6. „Verfügbarkeit/Abwesenheit“ öffnen.
7. Testabwesenheit für einen Sportler eintragen.
8. Zeitraum und Grund eintragen.
9. Speichern.
10. Teamregeln oder „Strafen“ öffnen, falls die Funktion angezeigt wird.
11. Testregel anlegen.
12. Regeltyp, Betrag und Auslöser eingeben.
13. Regel speichern.
14. Testgebühr für ein Mitglied anlegen.
15. Gebühr als bezahlt markieren.
16. Zweite Gebühr stornieren.
17. Teamübersicht aktualisieren.

### Erwartetes Ergebnis

Statistik, Abwesenheit, Regel, Gebühr, Bezahlt- und Storniertstatus werden getrennt und richtig dargestellt.

### Status

Bestanden / Fehler / Nachtest: ________.

## UC-51: Eventchat und Fahrgemeinschaft vollständig testen

### Ziel

Teilnehmer kommunizieren im Eventchat und organisieren eine Fahrt.

### Schritte

1. Testevent öffnen.
2. „Eventchat“ öffnen.
3. Testfrage schreiben.
4. Trainer antwortet.
5. Nachricht als gelesen und optional Reaktion prüfen.
6. „Fahrgemeinschaften“ öffnen.
7. „Fahrt anbieten“ auswählen.
8. Start, Ziel, Zeit, Plätze und Treffpunkt als Testdaten eintragen.
9. Fahrt speichern.
10. Sportlerkonto öffnen.
11. Fahrt suchen.
12. „Mitfahren“ anklicken.
13. Fahrer/Teammanager öffnet Anfrage.
14. Anfrage annehmen.
15. Teilnehmerliste prüfen.
16. Mitglied entfernen oder Fahrt verlassen.
17. Fahrt bearbeiten oder löschen.

### Erwartetes Ergebnis

Eventchat, Fahranfrage, Annahme, Teilnehmerliste, Verlassen und Löschen funktionieren nachvollziehbar.

### Status

Bestanden / Fehler / Nachtest: ________.

## UC-52: Sport-App-Integration synchronisiert und importiert Aktivität

### Ziel

Ein Sportler verbindet einen Testprovider, synchronisiert Aktivitäten, importiert eine Aktivität und trennt die Verbindung.

### Schritte

1. „Einstellungen“ > „Sport-Apps & Gesundheitsdaten“ öffnen.
2. Verfügbare Provider prüfen.
3. Einen Testprovider auswählen.
4. „Verbinden“ oder „Anfrage senden“ anklicken.
5. Providerfreigabe mit Testkonto durchführen.
6. Zurück zu Airmius wechseln.
7. Verbundenes Konto prüfen.
8. „Synchronisieren“ anklicken.
9. Synchronisationsstatus prüfen.
10. Eine Testaktivität importieren.
11. „Aktivitäten“ öffnen.
12. Titel, Typ, Dauer, Distanz und Kalorien prüfen.
13. Aktivität bearbeiten.
14. Aktivität löschen.
15. Integration trennen.
16. Prüfen, dass Konto und Importstatus entfernt oder inaktiv angezeigt werden.

### Erwartetes Ergebnis

Verbindung, Synchronisation, Import, Bearbeitung, Löschung und Trennung sind nachvollziehbar. Fehlende Provider zeigen keinen unklaren Erfolg an.

### Status

Bestanden / Fehler / Nachtest: ________.

## UC-53: Sportkarte verwaltet Orte, Routen und Tracks

### Ziel

Ein Sportler speichert Sportorte, bearbeitet Routen und verwaltet Tracks vollständig.

### Schritte

1. „Sportkarte“ öffnen.
2. Testort auf der Karte auswählen.
3. „Ort speichern“ anklicken.
4. Titel, Kategorie und Beschreibung eingeben.
5. Ort speichern.
6. Ort bearbeiten.
7. Ort löschen.
8. „Route planen“ öffnen.
9. Punkte setzen.
10. Routen-Generator/Vorschlag öffnen, falls sichtbar.
11. Route speichern.
12. Route bearbeiten.
13. Route löschen.
14. „Tracks“ öffnen.
15. Trackpunkte setzen oder importieren.
16. Track speichern.
17. Track bearbeiten.
18. Track löschen.

### Erwartetes Ergebnis

Ort, Route und Track werden nicht verwechselt. Bearbeiten und Löschen betreffen nur das ausgewählte Testobjekt.

### Status

Bestanden / Fehler / Nachtest: ________.

## UC-54: Persönliches Abo, Checkout, Rechnung und Kündigung

### Ziel

Ein Sportler oder Verein wählt einen Plan, startet Checkout, prüft Rechnung und kündigt oder verlängert das Test-Abo.

### Schritte

1. Öffentliche Seite „Preise/Abos“ öffnen.
2. Zielgruppe Sportler oder Verein auswählen.
3. Planvergleich und Feature-Limits prüfen.
4. Testplan auswählen.
5. Checkout starten.
6. Test-Zahlungsart wählen: Testzahlung oder Überweisung.
7. Rechnungsdaten prüfen.
8. Checkout bestätigen.
9. Erfolgs- oder Überweisungsseite öffnen.
10. „Abos & Rechnungen“ öffnen.
11. Aktiven Plan prüfen.
12. Abo-Rechnung öffnen und herunterladen.
13. Provider-Portal öffnen, falls vorhanden.
14. Abo kündigen oder zum Periodenende beenden.
15. Status prüfen.
16. Abo als Admin oder über Testdaten erneuern.
17. Offene Testzahlung prüfen.

### Erwartetes Ergebnis

Plan, Checkout, Rechnung, Status, Providerportal, Kündigung und Verlängerung haben konsistente Zustände. Eine abgebrochene Zahlung aktiviert kein Abo.

### Status

Bestanden / Fehler / Nachtest: ________.

## UC-55: Admin verwaltet Abo-Pläne und Abo-Rechnungen

### Ziel

Der Plattform-Admin pflegt Pläne, weist sie Nutzern/Vereinen zu und bearbeitet Rechnungsstatus.

### Schritte

1. Admin > „Abos“ öffnen.
2. Plan auswählen.
3. Zielgruppe, Preis, Limits, Badge, CTA und Sichtbarkeit prüfen.
4. Einen Testwert ändern und speichern.
5. Verein einem Plan zuweisen.
6. Testnutzer einem Plan zuweisen.
7. Vereinsabo zum Periodenende kündigen.
8. Vereinsabo manuell verlängern.
9. Nutzerabo kündigen.
10. Nutzerabo verlängern.
11. „Abo-Rechnungen“ öffnen.
12. Rechnung öffnen.
13. Rechnung herunterladen.
14. Überweisung als bezahlt markieren.
15. Status und Aktivierung prüfen.

### Erwartetes Ergebnis

Planzuweisung und Rechnung verändern nur das ausgewählte Konto. Planlimits wirken im jeweiligen Nutzer-/Vereinsbereich.

### Status

Bestanden / Fehler / Nachtest: ________.

## UC-56: Admin verwaltet Outfit-Pläne, Lieferungen und Zahlungsstatus

### Ziel

Der Admin verwaltet Outfit-Pläne und bearbeitet Lieferung, Probleme und Zahlungen.

### Schritte

1. Admin > „Outfit-Abos“ öffnen.
2. Outfit-Plan erstellen.
3. Name, Preis, Beschreibung und Sichtbarkeit eintragen.
4. Plan speichern und bearbeiten.
5. Plan löschen oder deaktivieren.
6. Outfit-Visuals bearbeiten.
7. Testkunde öffnen.
8. Zahlungsstatus auf bezahlt setzen.
9. Zahlungsstatus auf offen/nicht bezahlt setzen.
10. Zahlungserinnerung senden.
11. Lieferadresse als Testadresse ändern.
12. Lieferung öffnen.
13. Status „Versendet“ setzen.
14. Status „Geliefert“ setzen.
15. Testproblem erfassen.
16. Problem aktualisieren.
17. Lieferung löschen, sofern es ein Testobjekt ist.

### Erwartetes Ergebnis

Plan-, Abonnement-, Zahlungs-, Liefer- und Problemstatus bleiben getrennt nachvollziehbar.

### Status

Bestanden / Fehler / Nachtest: ________.

## UC-57: Sponsorprofil, Kampagne und Werbeagentur-Anfrage

### Ziel

Ein Sponsor pflegt sein Profil, erstellt eine Kampagne und ein Verein stellt eine Website-/Werbeagentur-Anfrage.

### Schritte Sponsor

1. Sponsor-Cockpit öffnen.
2. Sponsorprofil öffnen.
3. Name, Logo, Beschreibung und Kontakt mit Testdaten speichern.
4. „Kampagnen & Angebote“ öffnen.
5. Testkampagne anlegen.
6. Ziel-URL, Zielgruppe, Budget und Laufzeit eintragen.
7. Kampagne als Entwurf speichern.
8. Kampagne bearbeiten.
9. Öffentliche Sponsorenseite öffnen.
10. Sichtbarkeit und Paket prüfen.

### Schritte Verein

1. „Werbeagentur für Vereine“ öffnen.
2. Vereinsdaten und Kontakt eingeben.
3. Website-/Werbeanfrage absenden.
4. Admin > Commerce öffnen.
5. Website-Anfrage suchen.
6. Status ändern und speichern.

### Erwartetes Ergebnis

Sponsorprofil und Kampagne erscheinen erst nach vorgesehenem Status öffentlich. Website-Anfrage hat einen nachvollziehbaren Bearbeitungsstatus.

### Status

Bestanden / Fehler / Nachtest: ________.

## UC-58: Admin verwaltet Moderation, Meldung und Einspruch

### Ziel

Eine Meldung wird erstellt, geprüft, entschieden und ein möglicher Einspruch bearbeitet.

### Schritte

1. Sportler erstellt eine Testmeldung zu Beitrag, Profil, Kommentar oder Freund.
2. Admin > „Moderation“ öffnen.
3. Meldung suchen.
4. Inhalt und Meldegrund öffnen.
5. Meldungsstatus ändern.
6. Maßnahme oder Entscheidung eintragen.
7. Speichern.
8. Bei Testmeldung einen Einspruch erzeugen, falls der Flow dies vorsieht.
9. Einspruch öffnen.
10. Einspruch annehmen oder ablehnen.
11. Moderationslog öffnen.
12. Prüfen, dass Zeit, Fall und Entscheidung protokolliert sind.

### Erwartetes Ergebnis

Meldungen, Flags, Entscheidungen und Einsprüche bleiben getrennt nachvollziehbar. Normale Nutzer können keine Moderationsentscheidung ändern.

### Status

Bestanden / Fehler / Nachtest: ________.

## UC-59: Admin verwaltet Nutzer, Rollen, Sportarten und Badges

### Ziel

Der Admin legt einen Testnutzer, eine Testrolle, eine Sportart und ein Badge an und prüft deren Zugriff.

### Schritte

1. Admin > „Users/Nutzer“ öffnen.
2. „Nutzer erstellen“ anklicken.
3. Testname, Test-E-Mail und Status eingeben.
4. Nutzer speichern.
5. Nutzer bearbeiten.
6. Rolle zuweisen.
7. Admin > „Rollen & Rechte“ öffnen.
8. Testrolle oder Testberechtigung anzeigen/erstellen.
9. Berechtigung speichern.
10. Als Testnutzer anmelden.
11. Sichtbare Menüs und gesperrte Aktionen prüfen.
12. Admin > „Sportarten“ öffnen.
13. Test-Sportart oder Disziplin anlegen.
14. Sportart bearbeiten.
15. Sportlerprofil öffnen und Sportart prüfen.
16. Admin > „Badges“ öffnen.
17. Testbadge anlegen.
18. Badge bearbeiten und Fortschrittsregel prüfen.
19. Testbadge löschen oder deaktivieren.

### Erwartetes Ergebnis

Rolle, Berechtigung, Sportart und Badge wirken nur dort, wo sie vorgesehen sind. Konto ohne Berechtigung erhält keinen Zugriff.

### Status

Bestanden / Fehler / Nachtest: ________.

## UC-60: Admin verwaltet Gamification und Badges

### Ziel

XP-Regeln, Badge-Regeln und Fortschritt werden angezeigt und als Testwerte geändert.

### Schritte

1. Admin > „Gamification“ öffnen.
2. Regelübersicht prüfen.
3. Testregel oder vorhandene Testregel bearbeiten.
4. Kategorie, Auslöser, XP und Tageslimit prüfen.
5. Regel speichern.
6. Mit einem Testkonto die Aktion ausführen, die XP auslöst.
7. „Meine Badges“ öffnen.
8. XP- und Badge-Fortschritt prüfen.
9. Admin > „Badges“ öffnen.
10. Testbadge bearbeiten.
11. Testregel zurücksetzen oder Testbadge deaktivieren.

### Erwartetes Ergebnis

XP und Badges werden nicht mehrfach unzulässig vergeben. Regeländerung und Status sind nachvollziehbar.

### Status

Bestanden / Fehler / Nachtest: ________.

## UC-61: Admin verwaltet Rechnungen, Zahlungen und Betriebskosten

### Ziel

Der Plattform-Admin kontrolliert interne Rechnungen, Zahlungen und laufende Betriebskosten.

### Schritte

1. Admin > „Invoices“ öffnen.
2. Testrechnung erstellen.
3. Rechnung öffnen.
4. Status auf offen, bezahlt, überfällig oder storniert setzen.
5. Rechnung löschen, nur wenn Testobjekt.
6. Admin > „Payments“ öffnen.
7. Testzahlung erfassen.
8. Zahlung öffnen.
9. Zahlung löschen, nur wenn Testobjekt.
10. Admin > „Betriebskosten“ öffnen.
11. Testvertrag anlegen.
12. Kosten, Anbieter, Zeitraum und Status eingeben.
13. Vertrag bearbeiten.
14. Vertrag löschen, nur wenn Testobjekt.
15. Provider-Kosten öffnen.
16. Kostenübersicht und Zeitraum prüfen.

### Erwartetes Ergebnis

Rechnungen, Zahlungen, Verträge und Providerkosten sind getrennt erfasst und nur für berechtigte Admins sichtbar.

### Status

Bestanden / Fehler / Nachtest: ________.

## UC-62: Admin verwaltet Mail-Zentrale und Zustellstatus

### Ziel

Der Admin prüft Mailpräferenzen, Absender, Testversand, fehlgeschlagene Zustellungen und erneuten Versand.

### Schritte

1. Admin > „Mail-Zentrale“ öffnen.
2. Mailpräferenzen prüfen.
3. Testsender-Kategorie auswählen.
4. Test-Absender speichern.
5. „Test senden“ anklicken.
6. Testpostfach prüfen.
7. Mailzustellung öffnen.
8. Status erfolgreich/fehlgeschlagen prüfen.
9. Fehlgeschlagene Testmail öffnen.
10. „Erneut senden“ anklicken.
11. Lieferung als erledigt markieren.
12. Versand- und Fehlerdetails prüfen.

### Erwartetes Ergebnis

Mailstatus und Retry bleiben nachvollziehbar. Fehler werden nicht als erfolgreich angezeigt.

### Status

Bestanden / Fehler / Nachtest: ________.

## UC-63: Datenschutz, Export, Korrektur, Einwilligungen und Konto löschen

### Ziel

Ein Nutzer kann seine Datenschutzrechte und sichere Kontolöschung ausführen.

### Schritte

1. „Einstellungen“ öffnen.
2. Datenschutzbereich öffnen.
3. Datenexport anfordern.
4. Export herunterladen oder Status prüfen.
5. Korrekturanfrage für Testdaten senden.
6. Einwilligungen anzeigen.
7. Einwilligung widerrufen.
8. Prüfen, welche Funktion dadurch eingeschränkt wird.
9. Kontolöschung öffnen.
10. Löschcode anfordern.
11. Code aus Test-E-Mail eintragen.
12. Löschung bestätigen.
13. Abmelden.
14. Erneut anmelden und prüfen, dass das Konto gelöscht, anonymisiert oder gesperrt ist, wie es die Umgebung vorsieht.

### Erwartetes Ergebnis

Export, Korrektur, Widerruf und Löschung verlangen die richtigen Sicherheitsbestätigungen. Keine fremden Daten werden angezeigt.

### Status

Bestanden / Fehler / Nachtest: ________.

## UC-64: Mobile App prüft Push, Deep Link, Sync und Offline-Status

### Ziel

Die mobile App synchronisiert Rollen-/Featuredaten, registriert Push, öffnet Deep Links und zeigt Offline-Zustände verständlich.

### Voraussetzungen

1. Android-Testgerät oder iOS-Testgerät.
2. Push-Berechtigung darf für den Test erteilt werden.
3. Für lokale API keine `localhost`-Adresse auf einem echten Handy verwenden.

### Schritte

1. App installieren oder aktualisieren.
2. App öffnen und Berechtigungs-Onboarding prüfen.
3. Benachrichtigungen erlauben.
4. Standort, Kamera und Dateien nur bei Bedarf erlauben.
5. Anmelden.
6. Rolle und freigeschaltete Module prüfen.
7. Push-Gerät registrieren lassen.
8. Mit dem zweiten Konto eine Testnachricht oder Einladung erzeugen.
9. Push antippen.
10. Prüfen, ob die App direkt das richtige Ziel öffnet.
11. App schließen und erneut über den Deep Link öffnen.
12. App-Sync ausführen oder App neu starten.
13. Datenstand prüfen.
14. Netzwerk trennen.
15. Eine unterstützte Offline-Aktion prüfen.
16. Netzwerk wiederherstellen.
17. Synchronisation und mögliche Warteschlange prüfen.
18. Abmelden.
19. Prüfen, dass private Daten nach dem Logout nicht im falschen Konto sichtbar sind.

### Erwartetes Ergebnis

Push und Deep Link führen zum richtigen Objekt. Offline-/Fehlerzustände sind verständlich und eine Wiederherstellung erzeugt keine doppelten Aktionen.

### Status

Bestanden / Fehler / Nachtest: ________.

## UC-65: Mobile Navigation und Web-Parität prüfen

### Ziel

Die wichtigsten Funktionen sind in Web und App erreichbar und die rollenabhängigen Menüs stimmen.

### Schritte

1. Sportler-Web und Sportler-App gleichzeitig öffnen.
2. Startseite vergleichen.
3. Feed in Web und App öffnen.
4. Vereine & Teams in Web und App öffnen.
5. Training in Web und App öffnen.
6. Ernährung in Web und App öffnen.
7. Profil und Einstellungen in Web und App öffnen.
8. Trainer-Web und Trainer-App gleichzeitig öffnen.
9. Trainer-Cockpit, Teams, Training, Events, Dateien und Nachrichten vergleichen.
10. Verein-Web und Vereins-App gleichzeitig öffnen.
11. Vereins-Cockpit, Teams, Events, Dateien, Mitglieder und Finanzen vergleichen.
12. Admin-Web und Admin-App vergleichen, sofern die Module freigegeben sind.
13. Fehlende Funktion notieren und prüfen, ob sie nur Web-only oder App-only vorgesehen ist.
14. Falsche Rollenfreigabe als Sicherheitsfehler markieren.

### Erwartetes Ergebnis

Web und App verwenden dieselben Daten und zeigen entsprechend der Rolle die richtigen Module. Unterschiede sind dokumentiert und begründet.

### Status

Bestanden / Fehler / Nachtest: ________.

## Vollständige Funktionsabdeckung

Vor dem Abschluss jede Funktionsgruppe abhaken:

1. [ ] Registrierung, Login, Logout, Passwort, E-Mail-Verifizierung und Zwei-Faktor-Login.
2. [ ] Profil, Foto, Status, Sprache, Design, Sichtbarkeit, Sicherheit und Sitzungen.
3. [ ] Datenschutzexport, Korrektur, Einwilligungswiderruf und Kontolöschung.
4. [ ] Sportarten, Sportprofile, Skills, Empfehlungen, Follow, Block und Scout-Suche.
5. [ ] Sport-Apps, Synchronisation, Aktivitäten, Ernährung, Wasser, Lebensmittel, Barcode und Bildanalyse.
6. [ ] Feed, Posts, Stories, Kommentare, Likes, hilfreich, Meldungen, Einsprüche und Moderation.
7. [ ] Chat, Nachrichten, Reaktionen, Lesestatus, Gruppen, Teilnehmer, Mute und Verlassen.
8. [ ] Freunde, Einladungen, Matching, Fahrgemeinschaften und Eventchat.
9. [ ] Vereine, Vereinsprofil, Sichtbarkeit, öffentliche Vorschau, Teams und Rollen.
10. [ ] Vereinsanträge, externe Mitglieder, Import/Export, Mitgliedstypen, Beitragsregeln und Dokumente.
11. [ ] Rechnungen, Zahlungen, Mahnungen, Kassenbuch, Bankabgleich, SEPA, DATEV und Audit.
12. [ ] Training, Pläne, Einheiten, Übungen, Logs, Entwürfe, Feedback, AI-Vorschau und Fehltermine.
13. [ ] Events, Kalender, Filter, Wiederholungen, RSVP, Anwesenheit und Teilnehmerkommunikation.
14. [ ] Sportkarte, Orte, Routen, Vorschläge, Tracks und Live-Tracking.
15. [ ] Kurse, Einschreibung, Lektionen, Fortschritt, Notizen, Kommentare, Quiz, Aufgaben, Bewertungen und Zertifikate.
16. [ ] Lernstudio, Abschnitte, Lektionen, Uploads, Reihenfolge, Einschreibungen, Reports, Coupons und Qualität.
17. [ ] Marketplace, Suche, Filter, Produktdetail, Anbieter, Wunschliste und öffentlicher Kauf.
18. [ ] Checkout, Stripe/PayPal-Test, Überweisung, Bestellung, Status, Rechnung, Versand, Rückgabe, Erstattung und Gutschrift.
19. [ ] Verkäuferantrag, eigene Produkte, Bestand, Steuer, Versand, Coupons, Provisionen und Auszahlungen.
20. [ ] Abos, Planlimits, Abo-Rechnungen, Kündigung, Verlängerung und Providerportal.
21. [ ] Outfit-Abo, Style-Profil, Lieferungen, Versandstatus, Lieferprobleme, Zahlung und Admin-Verwaltung.
22. [ ] Sponsoren, Sponsorprofile, Kampagnen, öffentliche Sponsorenseite und Website-Anfrage.
23. [ ] Blog, Kategorien, Artikel, Content-Bilder, Vorschau, Veröffentlichung und Löschen.
24. [ ] Admin-Nutzer, Rollen, Rechte, Sportarten, Badges, Gamification und Vereinsprüfung.
25. [ ] Admin-Zahlungen, Rechnungen, Betriebskosten, Providerkosten, Mail-Zentrale und Systemeinstellungen.
26. [ ] Elternzustimmung, Elternlogin, Kinderübersicht, Widerruf und Alters-/Content-Gates.
27. [ ] Push, Deep Links, Mobile-Sync, Offline-Fehler, Berechtigungen und Web-App-Parität.

## UC-66: Alternative Anmeldung, E-Mail-Code und Zwei-Faktor-Sicherheit

### Ziel

Alle vorgesehenen Anmeldewege funktionieren und ein Konto bleibt auch bei falschem oder fehlendem Sicherheitscode geschützt.

### Voraussetzungen

1. Ein Testkonto mit bestätigter E-Mail-Adresse verwenden.
2. Zugriff auf das Testpostfach und auf die hinterlegte Authenticator-App oder Test-2FA-Konfiguration sicherstellen.
3. Google-/Microsoft-Testkonto nur verwenden, wenn der jeweilige Provider in der Umgebung aktiviert ist.

### Web – genaue Schritte

1. Abmelden und die Login-Seite öffnen.
2. „Mit Google anmelden“ oder „Mit Microsoft anmelden“ anklicken, sofern der Button angezeigt wird.
3. Das Testkonto des Providers auswählen.
4. Die Einwilligung prüfen und den Rücksprung zu Airmius abwarten.
5. Prüfen, dass kein zweites Airmius-Konto versehentlich angelegt wird.
6. Abmelden und erneut mit E-Mail und Passwort anmelden.
7. 2FA in „Profil“ > „Einstellungen“ > „Sicherheit“ öffnen.
8. Zwei-Faktor-Authentifizierung aktivieren.
9. QR-Code oder Einrichtungsschlüssel mit der Test-Authenticator-App hinterlegen.
10. Den Bestätigungscode eingeben und Recovery-Codes sicher als Testnachweis speichern.
11. Abmelden und erneut anmelden.
12. Einen falschen 2FA-Code eingeben.
13. Prüfen, dass eine verständliche Fehlermeldung erscheint und kein Login erfolgt.
14. Den richtigen Code eingeben.
15. Einen Recovery-Code einmal verwenden.
16. Prüfen, dass ein verbrauchter Recovery-Code kein zweites Mal funktioniert.
17. Einen neuen Satz Recovery-Codes erzeugen, falls die Funktion vorhanden ist.
18. 2FA wieder deaktivieren und die Sicherheitsbestätigung durchführen.

### App – genaue Schritte

1. Die App auf dem Handy öffnen und „Anmelden“ oder „Registrieren“ prüfen.
2. Den alternativen Provider-Button testen, falls er angezeigt wird.
3. Nach erfolgreicher Rückkehr prüfen, ob der richtige Nutzername und die richtige Rolle geladen werden.
4. Unter „Profil“ > „Einstellungen“ > „Sicherheit“ den 2FA-Status öffnen.
5. Einrichtung, falschen Code, richtigen Code und Recovery-Code wie im Web testen.
6. Die App vollständig beenden und erneut öffnen.
7. Prüfen, dass die Sitzung und der Sicherheitsstatus korrekt wiederhergestellt werden.

### Erwartetes Ergebnis

Provider-Login, E-Mail-Login, 2FA, Fehlerfall und Recovery-Codes verhalten sich sicher und konsistent. Sensible Codes werden nicht im Klartext angezeigt oder in Fehlermeldungen protokolliert.

### Status

Bestanden / Fehler / Nachtest: ________. Provider: ________. 2FA: ________.

## UC-67: Öffentliche Seiten, Jobs, Kontakt, Events und Zertifikatsprüfung

### Ziel

Ein nicht angemeldeter Besucher kann alle öffentlichen Einstiege öffnen, Informationen lesen, eine Kontaktanfrage senden und ein öffentlich prüfbares Zertifikat kontrollieren.

### Web – genaue Schritte

1. Ein privates Browserfenster öffnen.
2. Die Startseite ohne Login aufrufen.
3. „Vereine“ öffnen und nach einem Testverein suchen.
4. Das öffentliche Vereinsprofil öffnen.
5. „Veranstaltungen“ öffnen und Liste, Filter und Detailseite prüfen.
6. „Jobs“ öffnen und eine Stellenbeschreibung öffnen.
7. Interesse an der Teststelle anklicken und die vorgesehenen Kontaktdaten ausfüllen.
8. Absenden und Bestätigung prüfen.
9. „Blog“ öffnen und eine Kategorie auswählen.
10. Einen Artikel öffnen und RSS-/öffentliche Linkdarstellung prüfen, falls vorhanden.
11. „Kurse“ oder „E-Learning“ öffnen und einen öffentlichen Kurs ansehen.
12. „Marketplace“ öffnen und ein Produktdetail sowie einen Anbieter öffnen.
13. „Sponsoren“ oder „Werbeagentur“ öffnen und öffentliche Inhalte prüfen.
14. „Kontakt“ öffnen.
15. Ein Pflichtfeld leer lassen und absenden.
16. Prüfen, dass der Fehler am richtigen Feld erscheint.
17. Die Kontaktanfrage mit Testdaten absenden.
18. Einen Test-Zertifikatscode in „Zertifikat prüfen“ eingeben.
19. Einen gültigen und anschließend einen ungültigen Code prüfen.
20. Zurück zur öffentlichen Startseite gehen und sicherstellen, dass keine privaten Daten sichtbar sind.

### App – genaue Schritte

1. Die App ohne Login öffnen.
2. „Blog & Medien“, „Marketplace“, „Kurse“ oder „öffentliche Inhalte“ auswählen, sofern diese Bereiche im Gastmodus angeboten werden.
3. Einen öffentlichen Eintrag öffnen und den Zurückweg testen.
4. „Support kontaktieren“ oder „Kontakt“ öffnen.
5. Pflichtfeld- und Erfolgsfall testen.
6. Die Zertifikatsprüfung öffnen und gültigen/ungültigen Code testen.
7. Prüfen, dass ein Gast keinen geschützten Vereins-, Chat- oder Mitgliederbereich öffnen kann.

### Erwartetes Ergebnis

Öffentliche Inhalte sind ohne Login erreichbar, geschützte Inhalte bleiben geschützt. Formulare zeigen Validierung und eine eindeutige Erfolgsbestätigung; Zertifikatscodes werden korrekt als gültig oder ungültig angezeigt.

### Status

Bestanden / Fehler / Nachtest: ________. Öffentliche URL: ____________________.

## UC-68: Nutzerstatus, Profilverbindungen und Empfehlungen verwalten

### Ziel

Ein Nutzer kann seinen Status, Beziehungen zu anderen Profilen und Empfehlungen nachvollziehbar verwalten.

### Voraussetzungen

1. Zwei Testkonten auf Laptop 1 und Laptop 2 oder auf zwei Handys anmelden.
2. Die Profile der beiden Konten dürfen sich gegenseitig sehen.

### Schritte Web oder App

1. Profilmenü öffnen.
2. Den Nutzerstatus auf „online“, „offline“, „im Training“ oder „abwesend“ setzen, sofern angeboten.
3. Speichern und das Profil mit dem zweiten Konto öffnen.
4. Prüfen, ob nur die freigegebene Statusinformation erscheint.
5. Im Profil des zweiten Kontos „Folgen“ auswählen.
6. Mit dem ersten Konto die Benachrichtigung öffnen.
7. „Folgen“ wieder aufheben und den Unterschied prüfen.
8. Das Profil blockieren.
9. Mit dem zweiten Konto prüfen, dass geschützte Interaktionen und Kontaktaufnahme entsprechend eingeschränkt sind.
10. Blockierung wieder aufheben.
11. Eine Profil-Empfehlung oder Skill-Empfehlung senden.
12. Mit dem zweiten Konto die Empfehlung annehmen.
13. Eine zweite Empfehlung ablehnen.
14. Einen Skill bestätigen oder „Endorsement“ ausführen, falls vorhanden.
15. Die öffentliche Profilansicht, Sichtbarkeit und Aktivitätsstatus vergleichen.
16. Prüfen, dass der Nutzer nicht doppelt in Listen oder Empfehlungen erscheint.

### Erwartetes Ergebnis

Status, Follow, Unfollow, Block, Unblock, Empfehlung und Skill-Bestätigung aktualisieren beide Konten korrekt und respektieren die Privatsphäre.

### Status

Bestanden / Fehler / Nachtest: ________.

## UC-69: Chat vollständig verwalten

### Ziel

Ein Nutzer erstellt Einzel- und Gruppenchats, verwaltet Teilnehmer, Lesestatus, Stummschaltung, Reaktionen und Nachrichtenlebenszyklus.

### Voraussetzungen

1. Drei Testkonten bereithalten: Sportler, Trainer und Verein.
2. Die Konten dürfen miteinander chatten.

### Web – genaue Schritte

1. „Nachrichten“ öffnen.
2. „Neue Unterhaltung“ anklicken.
3. Ein Einzelgespräch mit dem Trainerkonto erstellen.
4. Eine Testnachricht senden.
5. Mit dem Trainerkonto die Nachricht öffnen und als gelesen markieren.
6. Mit dem Sportlerkonto den Lesestatus prüfen.
7. Auf die Nachricht reagieren.
8. Eine weitere Nachricht verbergen und eine eigene Testnachricht löschen.
9. Eine Unterhaltung als stummgeschaltet markieren.
10. Einen Gruppenchats mit Verein, Trainer und Sportler anlegen.
11. Gruppennamen und Beschreibung ändern.
12. Einen Teilnehmer hinzufügen.
13. Einen Teilnehmer entfernen.
14. Den Eigentümer auf ein anderes berechtigtes Konto übertragen.
15. Mit dem neuen Eigentümer prüfen, ob die Verwaltungsaktionen verfügbar sind.
16. Eine Chat-Einladung annehmen.
17. Eine zweite Chat-Einladung ablehnen.
18. „Tippt gerade“ oder Präsenzstatus prüfen, falls im UI sichtbar.
19. Den Chat mit einem Konto verlassen.
20. Prüfen, dass nur berechtigte Konten die Nachrichten weiter sehen können.

### App – genaue Schritte

1. Oben auf das Nachrichten-Symbol tippen.
2. Einzelchat und Gruppenchat öffnen.
3. Nachricht senden, lesen, reagieren, verbergen und löschen.
4. Über das Drei-Punkte-Menü Chat stummschalten.
5. Teilnehmer hinzufügen, entfernen und Chat verlassen.
6. Chat-Einladung im Benachrichtigungsbereich annehmen und ablehnen.
7. App schließen, neu öffnen und prüfen, dass der ungelesene Zähler korrekt ist.

### Erwartetes Ergebnis

Alle Chataktionen aktualisieren Zähler, Lesestatus, Teilnehmer und Berechtigungen ohne doppelte Nachrichten oder Zugriff nach dem Verlassen.

### Status

Bestanden / Fehler / Nachtest: ________.

## UC-70: Dateien, Ordner, Vorschau, Download und Freigaben

### Ziel

Ein Nutzer lädt Dateien hoch, organisiert sie in Ordnern, teilt sie gezielt und prüft Vorschau, Download und Löschung.

### Voraussetzungen

1. Eine harmlose Testdatei, ein Testbild und ein Test-PDF bereithalten.
2. Einen Empfänger mit eingeschränkter Berechtigung verwenden.

### Web – genaue Schritte

1. „Dateien“ öffnen.
2. Einen neuen Ordner „Test 2026“ erstellen.
3. Den Ordner umbenennen.
4. Eine Testdatei hochladen.
5. Das Testbild in den Ordner verschieben, falls Verschieben angeboten wird.
6. Dateiname und Beschreibung bearbeiten.
7. Bild und PDF über „Vorschau“ öffnen.
8. Jede Datei herunterladen und die Datei im Downloadordner prüfen.
9. Eine Datei für ein anderes Konto freigeben.
10. Den Freigabebereich auf Nutzer, Team, Verein oder Event einschränken, sofern auswählbar.
11. Mit dem Empfängerkonto die Datei öffnen.
12. Prüfen, dass eine nicht freigegebene Datei nicht angezeigt wird.
13. Freigabe widerrufen.
14. Einen Ordner teilen und danach die Ordnerfreigabe ändern.
15. Einen Ordner mit Testinhalt löschen.
16. Eine Datei löschen und den Papierkorb-/Bestätigungsdialog prüfen.
17. Einen öffentlichen oder tokenbasierten Download öffnen, falls vorhanden.
18. Einen abgelaufenen oder falschen Download-Link testen.

### App – genaue Schritte

1. Drawer > „Dateien“ öffnen.
2. Ordner anlegen, umbenennen und öffnen.
3. Datei über Kamera, Galerie oder Dateiauswahl hochladen, falls die App die Quelle anbietet.
4. Datei öffnen, Vorschau und Download testen.
5. Teilen auswählen und Empfänger sowie Sichtbarkeit festlegen.
6. Mit dem zweiten Konto die Freigabe prüfen.
7. Freigabe entziehen und Datei löschen.
8. Netzwerkfehler während Upload simulieren und prüfen, ob ein erneuter Upload möglich ist.

### Erwartetes Ergebnis

Dateien und Ordner sind nur für berechtigte Personen sichtbar. Vorschau, Download, Freigabeänderung, Löschung und Fehlermeldungen funktionieren nachvollziehbar.

### Status

Bestanden / Fehler / Nachtest: ________. Testdatei anschließend löschen: ________.

## UC-71: Event vollständig erstellen, bearbeiten, abstimmen und abschließen

### Ziel

Ein Verein oder Trainer erstellt ein Event, veröffentlicht es, sammelt Teilnahme- und Entscheidungsdaten und schließt es sauber ab.

### Web – genaue Schritte

1. „Events“ öffnen.
2. Persönliche Eventfilter oder Standardfilter öffnen und einen Testfilter speichern.
3. „Event erstellen“ anklicken.
4. Titel, Beschreibung, Sportart, Ort, Start, Ende und Sichtbarkeit eingeben.
5. Team auswählen und Wiederholung testen, falls angeboten.
6. Event speichern und Detailseite öffnen.
7. Mit dem Sportlerkonto „Teilnehmen“, „Vielleicht“ oder „Absagen“ testen.
8. Teilnahme wieder verlassen und erneut beitreten.
9. Mit dem Veranstalterkonto Eventdaten bearbeiten.
10. Einen Kommentar schreiben und mit dem anderen Konto beantworten.
11. Eine Entscheidung/Abstimmung für das Event erstellen.
12. Mit den Teilnehmerkonten abstimmen.
13. Abstimmung schließen und Ergebnis prüfen.
14. Anwesenheitsliste öffnen.
15. Für Testteilnehmer anwesend, abwesend oder entschuldigt setzen.
16. Eventchat öffnen und eine Nachricht senden.
17. Event absagen und die Benachrichtigung prüfen.
18. Ein reines Testevent löschen und die Sicherheitsabfrage bestätigen.

### App – genaue Schritte

1. Bottom-Navigation oder Drawer > „Events & Training“ öffnen.
2. Eventliste, Filter und Detailansicht prüfen.
3. Testevent erstellen oder ein vorhandenes Event öffnen.
4. Teilnahme ändern, Kommentar schreiben, Abstimmung öffnen und abstimmen.
5. Eventchat öffnen.
6. Als Trainer/Verein Anwesenheit prüfen.
7. Event absagen und prüfen, ob Status und Push aktualisiert werden.

### Erwartetes Ergebnis

Eventstatus, Teilnehmerliste, Abstimmung, Anwesenheit, Kommentare, Chat und Benachrichtigungen bleiben über Web und App synchron.

### Status

Bestanden / Fehler / Nachtest: ________.

## UC-72: Training mit Übungen, Verfügbarkeit, AI-Plan und Analytics

### Ziel

Sportler und Trainer testen den vollständigen Trainingszyklus von Übung und Planung bis Log, Feedback, Verfügbarkeit und Analyse.

### Voraussetzungen

1. Sportler- und Trainerkonto sind demselben Testteam zugeordnet.
2. Eine kurze Testwoche verwenden, damit keine echten Trainingsdaten entstehen.

### Schritte Trainer

1. „Trainer-Cockpit“ oder „Trainingsplanung“ öffnen.
2. Übungsbibliothek öffnen.
3. Eine Testübung anlegen und speichern.
4. Übung bearbeiten und anschließend nur das Testobjekt löschen.
5. Einen Trainingsplan erstellen.
6. Die Testübung als Planposition hinzufügen.
7. Dauer, Intensität und Notiz eintragen.
8. Eine Planposition duplizieren, ändern und eine Position als verpasst markieren.
9. Plan als Vorlage speichern oder aus einer Vorlage erzeugen, sofern verfügbar.
10. Plan veröffentlichen.
11. Plan an das Testteam senden.
12. AI-Planer öffnen.
13. Eine Vorschau mit Testziel erzeugen.
14. Die Vorschau prüfen und nur bei Bedarf speichern.
15. Einen Trainingsfeedback-Eintrag an den Sportler senden.

### Schritte Sportler

1. „Training“ öffnen.
2. Den veröffentlichten Plan öffnen.
3. Verfügbarkeit oder Abwesenheit für einen Testtag eintragen.
4. Eine Einheit starten und Aktivität, Dauer, Belastung und Notiz eintragen.
5. Entwurf speichern.
6. Entwurf bearbeiten und absenden.
7. Feedback vom Trainer öffnen und beantworten.
8. Eine Einheit als verpasst markieren.
9. Trainingsanalyse öffnen.
10. Prüfen, dass Wochenwerte, Fortschritt und Belastungsdaten plausibel angezeigt werden.

### App – genaue Schritte

1. Bottom-Navigation > „Training“ öffnen.
2. Plan, Übung, Log-Entwurf, Feedback und Analytics nacheinander öffnen.
3. Den Trainingsstatus ändern und App neu laden.
4. Prüfen, dass der veröffentlichte Plan und der Log auf allen Geräten sichtbar sind.

### Erwartetes Ergebnis

Übungen, Planpositionen, Duplikate, Vorlagen, Veröffentlichung, AI-Vorschau, Verfügbarkeit, Entwurf, Log, Feedback, Fehltermin und Analytics sind rollenrichtig und synchron.

### Status

Bestanden / Fehler / Nachtest: ________.

## UC-73: Ernährung mit Suche, Barcode und Bildanalyse

### Ziel

Ein Sportler erfasst Ernährung und Wasser sowohl manuell als auch mit den vorhandenen Such- und Analysefunktionen.

### Schritte Web oder App

1. „Ernährung“ öffnen.
2. Tagesziel für Kalorien oder Makros als Testwert festlegen.
3. Eine Mahlzeit manuell erstellen.
4. Einen Lebensmittelbegriff suchen.
5. Suchergebnis auswählen und Menge ändern.
6. Eine Mahlzeit bearbeiten und löschen.
7. Wasseraufnahme in mehreren kleinen Einträgen erfassen.
8. Tagesübersicht aktualisieren.
9. Barcode-Scanner öffnen.
10. Einen Testbarcode scannen oder eine Testnummer eingeben.
11. Treffer, kein Treffer und ungültigen Barcode prüfen.
12. Fotoanalyse für eine Testmahlzeit öffnen.
13. Nur ein nicht sensibles Testbild verwenden.
14. Erkanntes Ergebnis prüfen und vor dem Speichern korrigieren.
15. Analyse abbrechen und prüfen, dass kein falscher Eintrag angelegt wird.
16. Ziel ändern und prüfen, dass die Auswertung neu berechnet wird.

### Erwartetes Ergebnis

Manuelle Erfassung, Suche, Barcode, Bildanalyse, Wasser, Zieländerung und Löschung erzeugen korrekte Tageswerte. Fehler oder fehlende Treffer werden verständlich angezeigt.

### Status

Bestanden / Fehler / Nachtest: ________. Kamera-/Fotoeinwilligung: ________.

## UC-74: Vereins-Onboarding, Mitgliedsformulare und Anforderungen

### Ziel

Ein Verein konfiguriert seinen Vereinsbereich, Mitgliedsarten, Aufnahmeformulare, Anforderungen und Rollenrechte.

### Voraussetzungen

1. Verein-Admin oder Vereinsbesitzer verwenden.
2. Nur Testfelder und Testdokumente anlegen.

### Schritte Web oder App

1. „Vereins-Cockpit“ öffnen.
2. Vereinsprofil, Logo, Bilder, Beschreibung und Kontaktdaten bearbeiten.
3. Sichtbarkeitseinstellungen öffnen.
4. Öffentliche Darstellung und Vorschau prüfen.
5. „Mitglieder & Finanzen“ öffnen.
6. Eine Mitgliedsart „Test Jugend“ anlegen.
7. Beitrag, Zahlungsintervall und Status eintragen.
8. Beitragsregel mit Start-/Enddatum erstellen.
9. Mitgliedschaftsformular-Builder öffnen.
10. Ein Pflichtfeld, ein Auswahlfeld und ein Einwilligungsfeld hinzufügen.
11. Feldreihenfolge ändern und Formular veröffentlichen.
12. Anforderungs-Builder öffnen.
13. Testdokument oder Zustimmung als Anforderung definieren.
14. Onboarding mit einem neuen Testmitglied starten.
15. Formular unvollständig absenden und Validierung prüfen.
16. Formular vollständig absenden.
17. Mit dem Verein die Anfrage öffnen und fehlende Anforderungen kontrollieren.
18. Rollen & Rechte öffnen.
19. Eine Testrolle anlegen oder bestehende Rechte ansehen.
20. Ein Recht vorübergehend ändern und mit dem betroffenen Testkonto kontrollieren.
21. Testrecht zurücksetzen.

### Erwartetes Ergebnis

Vereinsprofil, öffentliche Sichtbarkeit, Mitgliedsart, Regeln, Formulare, Anforderungen, Onboarding und Rechte arbeiten zusammen. Pflichtangaben blockieren unvollständige Anträge.

### Status

Bestanden / Fehler / Nachtest: ________.

## UC-75: Vereinsankündigung, Umfrage, Richtliniendokument und Mitgliedskarte

### Ziel

Ein Verein informiert Mitglieder, lässt sie abstimmen, stellt Richtlinien bereit und prüft eine digitale Mitgliedskarte.

### Schritte Verein

1. Vereins-Cockpit öffnen.
2. „Ankündigungen“ oder Vereinskommunikation auswählen.
3. Eine Testankündigung mit Titel, Text, Zielgruppe und optionalem Anhang erstellen.
4. Veröffentlichen.
5. Eine Testumfrage mit mindestens zwei Antwortoptionen erstellen.
6. Umfrage öffnen und Laufzeit setzen.
7. Richtliniendokument hochladen oder verknüpfen.
8. Dokumentversion und Sichtbarkeit prüfen.
9. Digitale Mitgliedskarte öffnen.
10. Mitgliedsnummer und Kartendaten prüfen.
11. QR-Code anzeigen.
12. QR-Code mit dem zweiten Gerät scannen oder den Prüfdialog öffnen.
13. Karte rotieren, falls ein Sicherheitswechsel angeboten wird.

### Schritte Mitglied

1. Mit dem Sportlerkonto „Benachrichtigungen“ öffnen.
2. Ankündigung lesen und als gelesen markieren.
3. Umfrage öffnen und abstimmen.
4. Prüfen, dass eine zweite Stimme nicht möglich ist.
5. Richtliniendokument öffnen und herunterladen.
6. Mitgliedskarte öffnen und QR-Code anzeigen.
7. Einen falschen oder abgelaufenen Code prüfen.

### Erwartetes Ergebnis

Zielgruppen sehen nur die für sie freigegebenen Inhalte. Gelesenstatus, eine einmalige Abstimmung, Dokumentversion, QR-Prüfung und Kartenrotation sind nachvollziehbar.

### Status

Bestanden / Fehler / Nachtest: ________.

## UC-76: Vereinsmitglieder, Import, externe Personen und Mitgliedslebenszyklus

### Ziel

Ein Verein verwaltet interne und externe Mitglieder inklusive Import, Einladung, Rollen, Nummern, Pause, Kündigung und Austritt.

### Schritte

1. „Mitglieder & Finanzen“ öffnen.
2. Ein externes Testmitglied manuell anlegen.
3. E-Mail-Einladung senden.
4. Mit dem Empfängerkonto Einladung annehmen.
5. Einladung mit einem zweiten Testdatensatz ablehnen.
6. Importvorlage herunterladen.
7. Eine anonymisierte Test-CSV mit gültiger und ungültiger Zeile vorbereiten.
8. Importvorschau öffnen und Fehlerzeile prüfen.
9. Nur die gültige Testzeile importieren.
10. Mitgliedsnummer vergeben und ändern.
11. Mitgliedstyp und Rolle ändern.
12. Berechtigungen des Mitglieds öffnen und ein Testrecht setzen.
13. Status auf Pause setzen und Pauseanfrage prüfen.
14. Kündigungs- oder Beendigungsanfrage als Testfall erstellen.
15. Einspruch gegen eine Entfernung als Testfall senden.
16. Austritt durchführen oder den Testdatensatz entfernen.
17. Prüfen, dass das ehemalige Mitglied keinen Vereinsbereich mehr öffnen kann.
18. Keine echten Personen- oder Bankdaten importieren.

### Erwartetes Ergebnis

Importvalidierung, Einladungen, Mitgliedsnummer, Rollen, Berechtigungen, Pause, Kündigung, Einspruch und Austritt ändern nur den vorgesehenen Datensatz und erzeugen passende Benachrichtigungen.

### Status

Bestanden / Fehler / Nachtest: ________. Testdatensätze entfernt: ________.

## UC-77: Vereinsfinanzen mit Vorauszahlung, Spende, Kassenbuch und Bankabgleich

### Ziel

Der Verein testet alle finanziellen Erfassungen ohne echte Zahlungsdaten.

### Schritte

1. „Mitglieder & Finanzen“ öffnen.
2. Eine Test-Mitgliedsrechnung erzeugen.
3. Zahlung als offen, bezahlt und überfällig prüfen.
4. Mahnung versenden und Versandstatus prüfen.
5. Eine Testzahlung erfassen und zuordnen.
6. Eine Vorauszahlung anlegen.
7. Eine Testspende anlegen.
8. Kassenbuch-/Finance-Eintrag anlegen und bearbeiten.
9. Einen anonymisierten Bank-CSV-Import öffnen.
10. Importvorschau und fehlerhafte Zeile prüfen.
11. Eine Testtransaktion bestätigen.
12. SEPA-Einstellungen prüfen und einen Export nur als Testdatei erzeugen.
13. DATEV-Einstellungen prüfen und einen Export nur als Testdatei erzeugen.
14. Einen offenen Eintrag stornieren oder den Status korrigieren.
15. Prüfen, dass nur Verein-Admins Finanzdaten sehen.
16. Testdateien und Testbuchungen nach dem Test entfernen.

### Erwartetes Ergebnis

Rechnungen, Zahlungen, Mahnungen, Vorauszahlungen, Spenden, Kassenbuch, Bankabgleich, SEPA und DATEV sind getrennt nachvollziehbar; keine echte Zahlung wird ausgelöst.

### Status

Bestanden / Fehler / Nachtest: ________. Keine Echtzahlung ausgelöst: ________.

## UC-78: Altersfreigabe, Reifegrad, Sicherheit und Guardian-Gates

### Ziel

Alters- und Sicherheitsregeln verhindern ungeeignete Inhalte und ermöglichen korrekte Elternzustimmung.

### Voraussetzungen

1. Ein erwachsenes Sportlerkonto, ein Minderjährigen-Testkonto und ein Elternkonto bereitstellen.
2. Testinhalte mit unterschiedlichen Altersfreigaben verwenden.

### Schritte

1. Minderjährigenkonto anmelden und Onboarding öffnen.
2. Altersgruppe und Content-Gate prüfen.
3. „Eltern & Jugendschutz“ öffnen.
4. Elternkontakt eintragen oder Einladung versenden.
5. Elternkonto öffnen und Zustimmung annehmen.
6. Mit dem Kind prüfen, welche Funktionen nun freigeschaltet sind.
7. Elternzustimmung widerrufen.
8. Prüfen, dass eingeschränkte Bereiche wieder blockiert werden.
9. Elternkonto öffnen und Kinderübersicht anzeigen.
10. Kinderdaten und Freigabestatus prüfen.
11. Zustimmung erneut senden.
12. Mit einem nicht berechtigten Konto versuchen, Kinderinformationen zu öffnen.
13. „Altersfreigaben“ oder „Reifegrad“ öffnen.
14. Übersicht, Motivation, Challenges, Trending/Discovery, Coach-Woche und Sicherheitsbereich prüfen.
15. Route-Analytics oder personalisierte Empfehlungen öffnen, sofern Daten vorhanden sind.
16. Ungeeigneten Inhalt melden.

### Erwartetes Ergebnis

Jugendschutz, Elternzustimmung, Widerruf, Kinderzugriff, Content-Gates und Sicherheitsansichten respektieren Alter und Berechtigung. Fremde Kinderinformationen bleiben unsichtbar.

### Status

Bestanden / Fehler / Nachtest: ________. Zustimmung widerrufen oder Testkonto bereinigt: ________.

## UC-79: Support kontaktieren, Ticket erstellen und Ticketstatus verfolgen

### Ziel

Ein Nutzer kann aus Web und App Hilfe anfordern; Support oder Admin kann das Ticket bearbeiten und der Nutzer sieht den Status.

### Schritte Nutzer

1. Profilmenü oder Hilfe-Schaltfläche öffnen.
2. „Support kontaktieren“ auswählen.
3. Kategorie, Betreff und Testbeschreibung eingeben.
4. Ein optionales Testbild anhängen.
5. Pflichtfeld leer lassen und Validierung prüfen.
6. Ticket absenden.
7. Ticketnummer und Status notieren.
8. „Meine Tickets“ öffnen.
9. Ticketdetail und Verlauf prüfen.
10. Eine Rückfrage ergänzen, falls verfügbar.

### Schritte Support/Admin

1. Support- oder Adminbereich öffnen.
2. Offene Tickets filtern.
3. Das Testticket öffnen.
4. Priorität, Kategorie und Status ändern.
5. Eine interne Notiz und eine Antwort hinterlegen, falls vorgesehen.
6. Ticket schließen.
7. Mit dem Nutzerkonto Benachrichtigung und neuen Status prüfen.
8. Ein geschlossenes Testticket erneut öffnen, falls das Produkt dies erlaubt.

### App – genaue Schritte

1. Topbar oder Drawer > „Support“ öffnen.
2. Ticket erstellen, Status ansehen und Antwort senden.
3. App neu starten und prüfen, dass die Ticketnummer erhalten bleibt.

### Erwartetes Ergebnis

Ticketstatus, Verlauf, Benachrichtigungen, Anhänge und Berechtigungen bleiben über die Rollen hinweg korrekt.

### Status

Bestanden / Fehler / Nachtest: ________.

## UC-80: Verein erstellt Jobs und Admin prüft Vereine

### Ziel

Ein Verein kann eine Teststelle veröffentlichen und verwalten; der Plattform-Admin kann eine Vereinsprüfung freigeben oder ablehnen.

### Schritte Verein

1. Vereins-Cockpit öffnen.
2. Bereich „Jobs“ oder „Stellenangebote“ öffnen.
3. Teststelle mit Titel, Beschreibung, Ort, Kontakt und Bewerbungsfrist anlegen.
4. Stelle veröffentlichen.
5. Öffentliche Stellenansicht öffnen.
6. Stelle bearbeiten.
7. Stelle schließen oder löschen, nur wenn Testobjekt.
8. Eingegangene Interessen prüfen, sofern eine Testanfrage vorhanden ist.

### Schritte Admin

1. Mit Admin-Konto anmelden.
2. „Vereinsprüfung“ öffnen.
3. Testverein auswählen.
4. Unterlagen, Profil und Prüfstatus ansehen.
5. Einen Testverein freigeben.
6. Einen zweiten Testverein ablehnen und Begründung eintragen.
7. Mit dem Vereinskonto Status und Benachrichtigung prüfen.
8. Prüfen, dass ein abgelehnter Verein keine nicht freigegebenen Funktionen erhält.

### Erwartetes Ergebnis

Stellenstatus, öffentliche Darstellung, Vereinsprüfung, Begründung und Benachrichtigung stimmen überein.

### Status

Bestanden / Fehler / Nachtest: ________.

## UC-81: Medienrichtlinien, Bildrechte und Inhaltsmeldung

### Ziel

Uploads und veröffentlichte Inhalte prüfen Richtlinien, Einwilligungen, Altersfreigabe und Meldewege.

### Schritte

1. „Medienrichtlinien“ öffnen.
2. Uploadregeln, Bildrechte, Einwilligungsregel und Altersfreigabe lesen.
3. Eine Datei ohne erforderliche Einwilligung als Testfall auswählen.
4. Upload in Feed, Dateiablage oder Kurs testen.
5. Prüfen, ob Warnung, Blockierung oder Zustimmungsschritt erscheint.
6. Einen zulässigen Testupload durchführen.
7. Sichtbarkeit und Zielgruppe kontrollieren.
8. Post, Kommentar oder Bild über „Melden“ melden.
9. Kategorie und Begründung auswählen.
10. Meldung absenden.
11. Mit Moderationskonto den Fall öffnen.
12. Inhalt freigeben, ausblenden oder entfernen.
13. Einspruch als Testfall senden.
14. Status und Benachrichtigung prüfen.
15. Testmedien und Testmeldungen nach dem Test löschen oder schließen.

### Erwartetes Ergebnis

Richtlinien sind erreichbar und Upload-, Alters-, Einwilligungs- und Meldeentscheidungen sind sichtbar und rollenrichtig umgesetzt.

### Status

Bestanden / Fehler / Nachtest: ________.

## UC-82: Arbeitsbereiche wechseln und Rechtegrenzen zwischen Rollen prüfen

### Ziel

Ein Nutzer mit mehreren Rollen kann den Arbeitsbereich wechseln; jede Rolle sieht nur die vorgesehenen Daten und Aktionen.

### Voraussetzungen

1. Ein Testkonto mit mindestens zwei Arbeitsbereichen oder Rollen verwenden.
2. Ein zweites Konto ohne Admin-/Vereinsrechte bereithalten.

### Web – genaue Schritte

1. Einloggen und „Arbeitsbereiche“ öffnen.
2. Arbeitsbereich Sportler auswählen.
3. Feed, Training, Profil und private Daten prüfen.
4. Auf „Arbeitsbereiche“ wechseln.
5. Trainer-Arbeitsbereich auswählen.
6. Trainer-Cockpit, Teamdaten und Feedback prüfen.
7. Vereins-Arbeitsbereich auswählen.
8. Mitglieder und Finanzen prüfen.
9. Zu jedem Wechsel URL, Titel, Navigation und Datenstand notieren.
10. Admin-URL direkt in einem Nicht-Admin-Konto öffnen.
11. Prüfen, dass Zugriff verweigert oder sinnvoll umgeleitet wird.
12. Eine verbotene Schreibaktion versuchen.
13. Prüfen, dass weder UI noch API die Aktion akzeptiert.

### App – genaue Schritte

1. Topbar- oder Drawer-Menü öffnen.
2. „Arbeitsbereiche“ auswählen.
3. Jede freigegebene Rolle öffnen.
4. Prüfen, dass Bottom-Navigation, Module und Startseite aktualisiert werden.
5. Ein nicht freigegebenes Modul über Suche oder Deep Link öffnen.
6. Zugriffsschutz dokumentieren.

### Erwartetes Ergebnis

Der Arbeitsbereichwechsel ändert Kontext und Berechtigungen vollständig. Ein Rollenwechsel führt nicht zu Datenlecks oder veralteten Schreibrechten.

### Status

Bestanden / Fehler / Nachtest: ________.

## UC-83: Sprache, Design, Benachrichtigungen und persönliche Einstellungen

### Ziel

Persönliche Einstellungen werden gespeichert, übersetzen sichtbare Texte und steuern Benachrichtigungen sowie Darstellung.

### Schritte Web oder App

1. „Einstellungen“ öffnen.
2. Sprache auf Deutsch ändern und speichern.
3. Sprache auf Englisch ändern und speichern.
4. Hauptnavigation, Fehlermeldung und Buttontexte prüfen.
5. Design/Theme oder Dark Mode umstellen, sofern vorhanden.
6. App/Web neu laden.
7. Benachrichtigungseinstellungen öffnen.
8. Eine Kategorie deaktivieren und speichern.
9. Mit dem zweiten Konto eine passende Testaktion erzeugen.
10. Prüfen, dass die deaktivierte Benachrichtigung nicht erscheint, während andere Kategorien weiter funktionieren.
11. Datenschutzsichtbarkeit für Profil, Aktivität und Sportdaten prüfen.
12. Zeitzone oder Region prüfen, falls verfügbar.
13. Sprache und Theme auf Teststandard zurücksetzen.

### Erwartetes Ergebnis

Einstellungen werden dauerhaft und kontobezogen gespeichert. Übersetzungen, Theme, Benachrichtigungen, Privatsphäre und Zeitdarstellung wirken in den relevanten Ansichten.

### Status

Bestanden / Fehler / Nachtest: ________.

## UC-84: Datenfehler, Ladezustand, Berechtigungsfehler und Wiederholungsaktion

### Ziel

Alle kritischen Bereiche behandeln leere Daten, langsame Verbindung, abgelaufene Sitzung und Serverfehler verständlich.

### Schritte

1. Einen Bereich ohne Datensätze öffnen, z. B. leere Wunschliste, leeres Training oder neue Dateien.
2. Empty State und angebotene nächste Aktion prüfen.
3. Netzwerk während des Ladens trennen.
4. Ladeindikator und Fehlermeldung prüfen.
5. „Erneut versuchen“ ausführen.
6. Netzwerk wiederherstellen und erneut laden.
7. Sitzung in einem zweiten Browser abmelden oder ablaufen lassen.
8. Im ersten Browser eine geschützte Aktion ausführen.
9. Prüfen, dass Login oder Weiterleitung erscheint und die Aktion nicht doppelt gespeichert wird.
10. Ein Formular mit ungültigen Werten absenden.
11. Validierung am richtigen Feld prüfen.
12. Zurück- und Vorwärtsnavigation prüfen.
13. App vollständig beenden und wieder öffnen.
14. Prüfen, dass kein Spinner dauerhaft hängen bleibt und kein privater Inhalt im falschen Konto erscheint.

### Erwartetes Ergebnis

Leere, langsame, abgelaufene und fehlerhafte Zustände sind verständlich, wiederholbar und sicher. Keine doppelte Erstellung und kein Datenverlust durch erneutes Laden.

### Status

Bestanden / Fehler / Nachtest: ________.

## UC-85: Vier-Geräte-End-to-End-Abnahme

### Ziel

Die vom Nutzer vorgesehene parallele Prüfung auf zwei Handys und zwei Laptops wird als zusammenhängender Ablauf durchgeführt.

### Geräte und Rollen

1. Handy 1: App Sportler.
2. Handy 2: App Trainer.
3. Laptop 1: Web Verein.
4. Laptop 2: Web Sportler oder Admin.

### Schritte

1. Alle vier Geräte mit dem vorgesehenen Konto anmelden.
2. Auf Handy 1 ein Sportprofil und einen Testbeitrag erstellen.
3. Auf Laptop 2 den Beitrag öffnen, kommentieren und melden, falls der Meldetest vorgesehen ist.
4. Auf Handy 1 Kommentar und Benachrichtigung prüfen.
5. Auf Laptop 1 ein Testevent erstellen.
6. Auf Handy 1 teilnehmen.
7. Auf Handy 2 Anwesenheit oder Traineransicht öffnen.
8. Auf Laptop 1 die Teilnahme bestätigen.
9. Auf Handy 1 eine Testnachricht im Eventchat senden.
10. Auf Handy 2 antworten.
11. Auf Laptop 1 eine Datei im Team teilen.
12. Auf Handy 2 die Datei öffnen und herunterladen.
13. Auf Laptop 2 eine Marketplace-Testbestellung oder Kursaktion ausführen, falls vorbereitet.
14. Auf dem zuständigen Gerät Status und Benachrichtigung prüfen.
15. Alle Geräte neu laden oder App neu starten.
16. Daten, Benachrichtigungen, Zähler, Rollen und Berechtigungen vergleichen.
17. Alle Testobjekte löschen oder auf Teststatus zurücksetzen.
18. Auf allen Geräten abmelden.

### Erwartetes Ergebnis

Ein auf einem Gerät ausgelöster Vorgang ist auf den anderen Geräten zeitnah, korrekt und ohne Berechtigungsüberschreitung sichtbar.

### Status

Bestanden / Fehler / Nachtest: ________. Geräte/Versionen: ____________________.

## UC-86: Warenkorb, Produktbewertungen, Bestellung stornieren und Dokumente

### Ziel

Ein Käufer testet den vollständigen Warenkorb- und Bestelllebenszyklus inklusive Menge, Entfernen, Bewertung, Rechnung, Gutschrift und Stornierung.

### Schritte Web oder App

1. „Marketplace“ öffnen und ein Testprodukt auswählen.
2. Produktmenge auf zwei ändern und den Warenkorb öffnen.
3. Prüfen, ob Zwischensumme, Steuer, Versand und Gesamtbetrag neu berechnet werden.
4. Ein zweites Produkt hinzufügen.
5. Menge wieder auf eins reduzieren.
6. Ein Produkt aus dem Warenkorb entfernen.
7. Warenkorb schließen und erneut öffnen.
8. „Zur Kasse“ anklicken.
9. Coupon aus UC-44 testen, falls für das Produkt gültig.
10. Checkout abbrechen und prüfen, dass der Warenkorb erhalten bleibt oder der vorgesehene Status angezeigt wird.
11. Checkout mit Testzahlung oder Überweisung abschließen.
12. „Meine Bestellungen“ öffnen.
13. Bestellung öffnen und Status, Rechnung und Dokumente prüfen.
14. Bestellung stornieren, sofern der Status dies erlaubt.
15. Stornierungsgrund als Testwert eingeben.
16. Rechnung herunterladen.
17. Gutschrift oder Rückgabedokument öffnen, falls erzeugt.
18. Nach erfolgreichem Testkauf eine Produktbewertung mit Testtext abgeben.
19. Bewertung mit dem Anbieter- oder Admin-Konto prüfen.
20. Nicht berechtigte Änderung an fremder Bewertung testen.

### Erwartetes Ergebnis

Warenkorb, Preise, Coupon, Checkout, Abbruch, Bestellung, Stornierung, Rechnung, Gutschrift und Produktbewertung werden getrennt und korrekt gespeichert. Keine fremde Bewertung oder Bestellung kann geändert werden.

### Status

Bestanden / Fehler / Nachtest: ________.

## UC-87: Verkäufer-Commerce mit Import, Standort, Kampagne und Auszahlung

### Ziel

Ein Verkäufer nutzt alle operativen Funktionen außerhalb des einfachen Produkts: Import, Anbieterprofil, Standorte, Kampagnen, Website-Anfrage und Auszahlung.

### Voraussetzungen

1. Verkäuferkonto oder freigegebener Testanbieter.
2. Nur Testdaten und keine echten Kontoverbindungen verwenden.

### Schritte

1. „Commerce“ > „Meine Produkte“ öffnen.
2. Importvorlage herunterladen.
3. Eine Test-CSV mit einer gültigen und einer fehlerhaften Produktzeile vorbereiten.
4. Importvorschau öffnen.
5. Fehlerzeile prüfen und nur die gültige Testzeile importieren.
6. Produktstatus Entwurf, Prüfung, aktiv und inaktiv nacheinander prüfen.
7. Anbieterprofil bearbeiten.
8. Anbieterbeschreibung, Kontakt und Testzahlungsinformationen speichern.
9. Anbieterstandort anlegen.
10. Adresse, Öffnungszeiten und Status eingeben.
11. Standort bearbeiten und anschließend löschen, wenn es ein Teststandort ist.
12. Kampagne erstellen.
13. Zielgruppe, Zeitraum, Budget und Status eintragen.
14. Kampagnengruppe anlegen.
15. Test-Creative oder Bild hinzufügen.
16. Kampagne bearbeiten, pausieren und wieder aktivieren.
17. Website-/Partneranfrage erstellen.
18. Anfrage im Admin-Commerce öffnen und Status sowie Antwort prüfen.
19. Auszahlungsprofil öffnen.
20. Auszahlung als Testanfrage erstellen.
21. Auszahlung nicht als echt bezahlt markieren, sondern nur den Workflow-Status prüfen.

### Erwartetes Ergebnis

Importvalidierung, Produktstatus, Anbieterprofil, Standort, Kampagne, Creative, Website-Anfrage und Auszahlung besitzen klare Status und Berechtigungen. Fehlerhafte Zeilen verändern keine gültigen Datensätze.

### Status

Bestanden / Fehler / Nachtest: ________. Echte Auszahlung ausgelöst: Nein / Fehler: ________.

## UC-88: Vereinsinventar und Ausleihe mit QR, Rückgabe und Wartung

### Ziel

Der Verein verwaltet Material, Ausleihe, Rückgabe, Schäden, QR-Code, Wartung und Audit.

### Schritte App oder Web, falls das Modul freigeschaltet ist

1. Vereins-Cockpit > „Inventar & Ausleihe“ öffnen.
2. Ein Testobjekt mit Name, Kategorie, Standort, Seriennummer und Zustand anlegen.
3. QR-Code anzeigen oder erzeugen.
4. Objekt einem Team oder Standort zuordnen.
5. Ausleihe an ein Testmitglied starten.
6. Zeitraum, Zustand, Kaution und Rückgabehinweis eintragen.
7. Ausleihe speichern und im Mitgliedskonto prüfen.
8. QR-Code mit dem zweiten Handy scannen.
9. Objektstatus und Ausleihhistorie prüfen.
10. Rückgabe öffnen.
11. Zustand, Rückgabefoto und Kommentar eintragen.
12. Testschaden oder Gebühr erfassen.
13. Rückgabe bestätigen.
14. Wartung mit Prüffrist oder Reparaturauftrag planen.
15. Admin-Benachrichtigung und Eskalation prüfen.
16. Auditverlauf mit Benutzer und Statuswechsel öffnen.
17. Objekt als verfügbar, ausgeliehen, reserviert oder kritisch prüfen.
18. Nur das Testobjekt löschen oder archivieren.

### Erwartetes Ergebnis

Objekt, Ausleihe, Rückgabe, Schaden, Foto, QR-Zugriff, Wartung und Audit sind chronologisch nachvollziehbar und nur für Vereinsberechtigte sichtbar.

### Status

Bestanden / Fehler / Nachtest: ________. Modul API-angebunden / nur UI: ________.

## UC-89: Team-Lebenszyklus, Kader, Einladungen und Wettbewerbsdaten

### Ziel

Ein Trainer oder Verein verwaltet ein Team von der Erstellung bis zur Auflösung inklusive Kader und Teamkennzahlen.

### Schritte

1. „Teams“ öffnen.
2. Ein Testteam mit Name, Sportart, Saison und Beschreibung erstellen.
3. Teamlogo oder Testbild hochladen.
4. Teamprofil öffnen und bearbeiten.
5. Sportler direkt in den Kader aufnehmen.
6. Einen Sportler als Testeinladung einladen.
7. Einladung mit dem Sportlerkonto annehmen.
8. Eine zweite Einladung ablehnen.
9. Eine öffentliche Beitrittsanfrage auslösen.
10. Mit dem Trainerkonto Beitrittsanfrage annehmen.
11. Eine zweite Anfrage ablehnen.
12. Teamrolle oder Kaderstatus ändern.
13. Ein Mitglied entfernen.
14. Anwesenheitsstatistik öffnen.
15. Daily-Life-, Wettbewerbs- oder Competitiveness-Insights öffnen, sofern Daten vorhanden sind.
16. Prüfen, dass nur Trainer und Verein interne Insights sehen.
17. Team umbenennen.
18. Testteam löschen oder archivieren und alle Folgehinweise prüfen.

### App – genaue Schritte

1. Bottom-Navigation > „Teams“ öffnen.
2. Team erstellen oder Testteam öffnen.
3. Einladung, Kader, Rollen, Beitrittsanfrage und Teamdetail prüfen.
4. App neu starten und prüfen, dass Team und Status erhalten bleiben.

### Erwartetes Ergebnis

Teamprofil, Bild, Kader, Rollen, Einladungen, Beitrittsanfragen, Statistiken und Löschung sind synchron und rollenrichtig.

### Status

Bestanden / Fehler / Nachtest: ________.

## UC-90: Admin-Plattform, inaktive Nutzer, Systemstatus und Rechte

### Ziel

Der Plattform-Admin verwaltet Nutzerstatus, Plattformdaten, Systemeinstellungen und Rechte mit nachvollziehbarem Schutz.

### Schritte

1. Admin > „Nutzer“ öffnen.
2. Testnutzer suchen und Profil öffnen.
3. Status aktiv, inaktiv, gesperrt oder wieder aktiv prüfen.
4. Eine Admin-Notiz als Testwert speichern, sofern vorhanden.
5. Inaktive-Nutzer-Liste öffnen.
6. Eine Testbenachrichtigung zur Inaktivität auslösen, sofern angeboten.
7. Rollen & Rechte öffnen.
8. Testrolle erstellen oder eine Testberechtigung ändern.
9. Mit einem Testkonto die Wirkung prüfen.
10. Berechtigung zurücksetzen.
11. „Sportarten“ öffnen und eine Test-Sportart oder Disziplin bearbeiten.
12. „Badges“ öffnen und Testbadge anlegen, bearbeiten und entfernen.
13. „Gamification“ öffnen und eine Testregel ändern.
14. „System“ oder „Plattform-Einstellungen“ öffnen.
15. Nur ungefährliche Testeinstellung ändern und speichern.
16. Systemstatus, API-/Mail-/Pushstatus und Fehlerhinweise prüfen.
17. Admin-Subscription einem Testnutzer zuweisen oder Planstatus ansehen.
18. Planlimits mit dem betroffenen Testkonto prüfen.
19. Direktzugriff als Nicht-Admin testen.
20. Alle Teständerungen zurücksetzen.

### Erwartetes Ergebnis

Status, Rollen, Rechte, Sportarten, Badges, Gamification, Systemstatus und Planlimits werden getrennt verwaltet. Nicht-Admins können keine Plattformaktion ausführen.

### Status

Bestanden / Fehler / Nachtest: ________.

## UC-91: Benachrichtigungen, Präferenzen, Lesen, Zurücksetzen und Löschen

### Ziel

Ein Nutzer verwaltet jede Benachrichtigung einzeln und global; Zähler, Push und E-Mail-Präferenzen bleiben konsistent.

### Schritte Web oder App

1. „Benachrichtigungen“ öffnen.
2. Ungelesene und gelesene Einträge unterscheiden.
3. Eine einzelne Benachrichtigung öffnen.
4. Als gelesen markieren.
5. Eine gelesene Benachrichtigung wieder als ungelesen markieren.
6. „Alle als gelesen markieren“ ausführen.
7. Prüfen, dass der Zähler auf null oder den korrekten Restwert fällt.
8. Einen Eintrag löschen.
9. Benachrichtigungseinstellungen öffnen.
10. Push, E-Mail und In-App-Kategorie getrennt konfigurieren.
11. Mit dem zweiten Konto eine passende Aktion auslösen.
12. Push- und In-App-Verhalten vergleichen.
13. Die App schließen und nach einer Testaktion neu öffnen.
14. Prüfen, dass kein alter Zähler im App-Icon oder in der Topbar bleibt.

### Erwartetes Ergebnis

Einzelstatus, globale Lesemarkierung, Löschen, Präferenzen, Push und Zähler stimmen über Web und App überein.

### Status

Bestanden / Fehler / Nachtest: ________.

## UC-92: Öffentliche Interessen, Standortvorschlag, Sponsor-Ads und Leadstatus

### Ziel

Öffentliche Besucher können Interesse oder einen Standort vorschlagen; Sponsor-/Werbekampagnen zeigen korrekte Klick- und Conversion-Zustände.

### Schritte

1. Öffentliche Seite oder Appbereich „Interesse, Ads & Sponsoren“ öffnen.
2. Filter „Leads“, „Ads“ und „Sponsors“ einzeln auswählen.
3. „Interesse senden“ öffnen.
4. Testkontakt und Anliegen eingeben.
5. Anfrage absenden und Bestätigung prüfen.
6. „Standort vorschlagen“ öffnen.
7. Teststandort, Adresse, Sportart und Kontakt eingeben.
8. Vorschlag absenden.
9. „Verein empfehlen“ öffnen und einen Testverein vorschlagen.
10. Werbeagentur-Landingpage öffnen und Leistungen, Zielgruppen und Formate prüfen.
11. Test-Kampagne mit Zeitraum, Zielgruppe und Budget öffnen oder anlegen, sofern berechtigt.
12. Sponsor-Anzeige öffnen.
13. Anzeige anklicken und Zielseite prüfen.
14. Eine Conversion-Testaktion ausführen, falls ein Testbutton vorhanden ist.
15. Mit Sponsor-/Admin-Konto Lead, Klick und Conversion öffnen.
16. Status von neu, in Bearbeitung, freigegeben und abgeschlossen prüfen.
17. Prüfen, dass eine als „UI bereit“ gekennzeichnete Aktion nicht fälschlich als echte Serveraktion gilt.

### Erwartetes Ergebnis

Leads, Standortvorschläge, Vereinsvorschläge, Ads, Klicks und Conversions zeigen einen eindeutigen Status. UI-only-Aktionen sind erkennbar und werden als Integrationslücke dokumentiert.

### Status

Bestanden / Fehler / Nachtest: ________. API-Integration vorhanden: Ja / Nein / Prüfen.

## UC-93: Android-Entwicklungsmodus, USB-Debugging und Geräteverbindung

### Ziel

Ein Testhandy wird sicher mit dem Laptop verbunden, die App wird mit der richtigen Testumgebung gestartet und die Verbindung wird nach dem Test wieder abgesichert.

### Vorbereitung am Android-Handy

1. „Einstellungen“ öffnen.
2. „Über das Telefon“ öffnen.
3. „Build-Nummer“ siebenmal antippen, bis „Sie sind jetzt Entwickler“ erscheint.
4. Zurück zu „System“ oder „Weitere Einstellungen“ gehen.
5. „Entwickleroptionen“ öffnen.
6. „USB-Debugging“ aktivieren.
7. „USB-Installation“ oder ähnliche Optionen nur aktivieren, wenn sie für den Test notwendig sind.
8. Das Handy mit einem Datenkabel am Laptop anschließen.
9. Auf dem Handy den RSA-/Computer-Fingerprint prüfen.
10. Nur auf dem eigenen Test-Laptop „Von diesem Computer immer zulassen“ aktivieren.
11. Nicht benötigte Berechtigungen wie Standort, Kamera oder Dateien nur beim konkreten App-Test erlauben.

### Verbindung am Laptop prüfen

1. Terminal oder PowerShell öffnen.
2. `adb devices -l` ausführen.
3. Prüfen, dass das eigene Handy mit Status „device“ und nicht „unauthorized“ erscheint.
4. Bei „unauthorized“ das Handy entsperren und den RSA-Dialog bestätigen.
5. Bei „offline“ USB-Kabel, USB-Modus und Debugging neu prüfen.
6. App aus Android Studio, Flutter oder der vorgesehenen Testverteilung starten.
7. Vor dem Login prüfen, welche API-Testadresse verwendet wird.
8. Auf einem echten Handy nicht `localhost` oder `127.0.0.1` verwenden.
9. Für einen Android-Emulator die vorgesehene Host-Adresse wie `10.0.2.2` verwenden.
10. Für ein echtes Handy die erreichbare LAN-IP des Entwicklungsrechners und denselben WLAN-Testzugang verwenden.
11. Prüfen, dass Firewall, HTTPS oder Zertifikat die Verbindung nicht blockieren.
12. Die App öffnen und Login, Feed, Nachrichten, Push, Upload und Logout testen.
13. `adb logcat` nur mit Testdaten und ohne Passwörter oder Tokens als sichtbaren Nachweis verwenden.

### iPhone, falls verwendet

1. „Einstellungen“ > „Datenschutz & Sicherheit“ öffnen.
2. „Entwicklermodus“ aktivieren, falls die iOS-Testinstallation dies verlangt.
3. Den Neustart bestätigen.
4. Nach dem Neustart die Entwicklerabfrage bestätigen.
5. Nur den vorgesehenen Testrechner und das eigene Testgerät verwenden.

### Abschluss und Sicherheit

1. App abmelden.
2. Testdaten und lokale Logs entfernen, soweit vorgesehen.
3. USB-Debugging deaktivieren.
4. Entwickleroptionen vollständig deaktivieren, wenn sie nicht mehr benötigt werden.
5. Bei fremdem oder verlorenem Laptop die zugelassenen Debugging-Computer widerrufen.
6. USB-Kabel abziehen.
7. Prüfen, dass die App ohne Entwicklungsserver nicht versehentlich auf eine falsche Test- oder lokale Adresse zeigt.

### Erwartetes Ergebnis

Das richtige Testgerät ist eindeutig verbunden, die App erreicht die vorgesehene Umgebung und es werden keine Zugangsdaten in Logs oder auf einem fremden Rechner hinterlassen. Nach dem Test ist USB-Debugging wieder deaktiviert.

### Status

Bestanden / Fehler / Nachtest: ________. Gerät: ________. API-Adresse: ________.

## Ergänzte Funktionsabdeckung

1. [ ] Alternative Provider-Anmeldung, E-Mail-Code, 2FA, Recovery-Code und Sitzungsprüfung.
2. [ ] Öffentliche Events, Jobs, Kontaktformular, öffentliche Kurse und Zertifikatsprüfung.
3. [ ] Nutzerstatus, Follow, Block, Empfehlungen und Skill-Bestätigung.
4. [ ] Chat-Einladung, Gruppenverwaltung, Mute, Eigentümerwechsel, Lesestatus und Reaktionen.
5. [ ] Ordner, Vorschau, Download, Freigabe, Widerruf, Token-Link und Uploadfehler.
6. [ ] Eventfilter, Bearbeitung, Absage, Löschung, Abstimmung, Kommentare und Anwesenheit.
7. [ ] Übungsbibliothek, Verfügbarkeit, Vorlagen, AI-Vorschau, Entwürfe, Fehltermine und Analytics.
8. [ ] Ernährungssuche, Barcode, Bildanalyse, Wasser, Ziele und Fehlersituationen.
9. [ ] Vereins-Onboarding, Formulare, Anforderungen, Sichtbarkeit, Rollen und Rechte.
10. [ ] Ankündigungen, Umfragen, Richtlinien, Mitgliedskarte, QR-Prüfung und Kartenrotation.
11. [ ] Importvorschau, externe Mitglieder, Einladungen, Pause, Kündigung, Einspruch und Austritt.
12. [ ] Vorauszahlung, Spende, Kassenbuch, Bankabgleich, SEPA und DATEV.
13. [ ] Altersfreigabe, Reifegrad, Guardian-Consent, Widerruf und Sicherheit.
14. [ ] Support-Tickets, Status, Verlauf, Antworten und Adminbearbeitung.
15. [ ] Jobs des Vereins und Plattformprüfung/Freigabe oder Ablehnung.
16. [ ] Medienrichtlinien, Rechte, Einwilligung, Altersprüfung und Meldung.
17. [ ] Arbeitsbereichwechsel, Rechtegrenzen und direkte URL-/Deep-Link-Sperre.
18. [ ] Sprache, Design, Benachrichtigungen, Privatsphäre und vier parallele Geräte.

## Abschluss

1. Alle Use Cases auf Status „Bestanden“ oder nachvollziehbar „Fehler/Nachtest“ setzen.
2. Keine echten Testdaten im System belassen.
3. Testbeiträge, Testevents, Testdateien und Testrechnungen löschen oder eindeutig als Test markieren.
4. Keine echten Zahlungs-, SEPA- oder Bankdateien im Download-Ordner belassen.
5. Alle Geräte abmelden.
6. Entwicklungsmodus und USB-Debugging nach dem Test wieder deaktivieren, wenn sie nicht mehr benötigt werden.

# Vollständiger App-Test – laufendes Prüfprotokoll

## Auftrag und Nachweisgrenzen

Alle vorhandenen Funktionen für Sportler, Trainer, Vereine und Sponsoren prüfen, einschließlich Kurse, Blog und Mitgliederverwaltung. Nicht auf einen Smoke-Test reduzieren. Stand: in Arbeit, keine Gesamtfreigabe.

Gerät: Xiaomi 23127PN0CG, Play-Version 1.0.40 (125), am 07.09.2026 per ADB bestätigt. Aktuelles Konto hat mehrere Rollen; ein erfolgreicher Admin-Aufruf beweist keine korrekte Trainer-/Sportler-/Sponsorberechtigung.

Lokal: PHPUnit mit SQLite :memory:, Mail array, Queue sync. Vollständiger Backendlauf vor den späteren Regressionserweiterungen: 1057 bestanden, 4 übersprungen (27237 Assertions); JUnit unter `mobile/airmius_mobile/release_evidence/full-app-qa-backend-2026-09-07.xml`. Spätere Änderungen sind unten mit gezielten Läufen dokumentiert. 312 Fluttertests wurden beim Build 125 erfolgreich ausgeführt, sind aber kein vollständiger Live-Nachweis.

Keine Zahlungen, öffentlichen Veröffentlichungen, Nachrichten an echte Mitglieder oder Löschungen produktiver Nutzerdaten für Tests. Das Anlegen separater Testkonten wurde vom Nutzer ausdrücklich erlaubt; separate Live-Rollenkonten sind noch nicht angelegt. Isolierte lokale Tests laufen unabhängig davon weiter.

## Vollständiger Funktionskatalog

Quelle: lib/core/airmius_mvp_surface.dart, lib/navigation/airmius_module_destination.dart, lib/core/airmius_persona.dart, Backend-Testklassen und API-Routen. Pro Modul sind Unteraktionen noch vollständig zu inventarisieren. Entwickler-Suiten sind keine freigegebenen Produktfunktionen und werden separat von sichtbaren Funktionen behandelt.

| Bereich | Zu prüfende Abläufe | Stand |
|---|---|---|
| Einstieg/Konto | Registrierung, Login, Wiederherstellung, 2FA, Profil, Abmelden | lokales Auth-/Recovery-Paket 17 Tests/101 Assertions; vollständiger Live-Ablauf offen |
| Arbeitsbereiche | Rollenstartseite, Wechsel, Rechteabgrenzung | offen |
| Rollen & Rechte | Anzeige/Zuweisung, unberechtigter Zugriff | offen |
| Vereins-Cockpit | Übersicht, Verwaltung, Finanzen, Berichte | offen |
| Vereine & Teams | Suche, Details, Gründung, Beitritt, Austritt | offen |
| Mitgliederverwaltung | Einladung, Antrag, Freigabe, Bearbeitung, Import/Export, Beiträge, Kündigung, Audit | lokal 27 Tests/342 Assertions grün; Live-Übersicht/Anfragen/Finanzen gelesen; positive Live-Schreibabläufe offen |
| Teams | Mitglieder, Rollen, Einladungen, Mannschaftsalltag | offen |
| Sportarten | Katalog, Auswahl, Profilverknüpfung | offen |
| Sport-Apps & Gesundheitsdaten | Verbindung, Import, Berechtigungen, Trennen | lokales Integrations-/Datenschutzpaket 15 Tests/479 Assertions; reale Anbieter und Geräteberechtigungen offen |
| Feed | Anzeigen, Beiträge, Kommentare, Medien, Melden | lokal 26 Tests/183 Assertions grün; Fremdänderungen geschützt; mobile Live-Abläufe offen |
| Nachrichten | Unterhaltung, Versand, Anhänge, Lesestatus, Zugriff | lokales Chat-Paket 22 Tests/212 Assertions; Datenschutzfix committet, nicht deployed; Live-Rollenablauf offen |
| Events & Training | Erstellen, Teilnahme, Anwesenheit, Erinnerung, Wiederholung | offen |
| Trainingsplanung | Pläne, Vorlagen, Übungen, Zuordnung, Protokolle | offen |
| Trainer-Cockpit | eigene Teams, Wochenkontrolle, Feedback, Fremddatenzugriff | offen |
| Ernährung | Einträge, Ziele, Analyse, Bildimport | Tagesübersicht, leere manuelle Eingabe und Abbruch live geprüft; positive Speicherung/Suche/Barcode/Foto offen |
| Sportkarte | Suche, Filter, Standort, Einreichen | zusammen mit Matching 16 Tests/268 Assertions lokal; reale GPS-/Kartenbedienung offen |
| Sport-Matching | Profil, Vorschläge, Filter, Kontakt | zusammen mit Sportkarte 16 Tests/268 Assertions lokal; Live-Ende-zu-Ende offen |
| Challenges | Erstellung, Einladung, Tages-/Wochen-/Einmalziele, Werte, Zurücknehmen, Kommentare | teilweise live: Tagesziel morgens, Leerwert, Zurücknehmen, Persistenz |
| Freunde | Suche, Einladung, Annehmen, Ablehnen, Entfernen | lokal 11 Tests/69 Assertions grün; Live-Einladungen und mobile Bedienung offen |
| Fahrgemeinschaften | Angebot, Suche, Beitritt, Plätze, Absage | lokal 8 Tests/394 Assertions grün; letzte Platzfreigabe und Datenschutz nach Entfernen geprüft; Handy-Ende-zu-Ende offen |
| Badges | Anzeige, Fortschritt, Vergabe | Badge-/Gamification-/Trainingpaket 12 Tests/92 Assertions lokal; Live-Punkteentwicklung offen |
| Gamification-Regeln | Berechtigung, Regeln, Fortschrittsauswirkung | offen |
| Altersfreigaben | Inhalte, Einschränkungen, Freigaben | offen |
| Kurse | Katalog, Anmeldung, Lektionen, Quiz, Fortschritt, Zertifikat | lokal Kurs-/Studio-/Sprachpaket 43 Tests/729 Assertions grün; Live-Lernerabschluss offen |
| Kursstudio | Kurs/Lektion erstellen, Reihenfolge, Veröffentlichung, Einschreibung, Bericht | privater Entwurf und Textlektion live gespeichert; übrige Live-Abläufe offen |
| Sponsoren | eigener Arbeitsbereich, Partner, Kampagnen, Auswertung, Vereinsabgrenzung | Sponsoren-/Werbepaket 24 Tests/409 Assertions lokal; Kennzahlenfix committet, nicht deployed; Live-Sponsorenkonto offen |
| Recruiting | Angebote, Bewerbungen, Verwaltung, Kontext | lokal 17 Tests/512 Assertions grün; positive/negative Verwaltungsabläufe erweitert; Handy-Ende-zu-Ende offen |
| Medienrichtlinien | Anzeige, Verwaltung, Zugriffsrechte | offen |
| Blog & Medien | öffentliche Artikel, Redaktion, Entwurf, Übersetzung, Freigabe, Revision | öffentliche Liste/Artikel live gelesen; lokales Blog-/Redaktionspaket 34 Tests/392 Assertions grün; Live-Redaktion offen |
| Nutzer | Suche, Details, Verwaltung, Rollen-/Datenschutzgrenzen | offen |
| Marketplace | Suche, Produkt, Warenkorb, Bestellung, Rückabwicklung | zusammen mit Commerce 45 Tests/393 Assertions lokal; echte Provider und Handy-Abläufe offen |
| Commerce | Verkäufer, Produkte, Bestellungen, Auszahlungen | zusammen mit Marketplace 45 Tests/393 Assertions lokal; echte Provider und Handy-Abläufe offen |
| Admin | zugängliche Unterbereiche und Schutz vor Normalnutzerzugriff | offen |
| Abos & Rechnungen | Pläne, Abschluss, Zahlung, Rechnung, Kündigung | Abo-/Outfit-Paket 46 Tests/741 Assertions lokal; reale Zahlungsabläufe offen |
| Outfit-Abos | Auswahl, Lieferdaten, Pause, Fortsetzen, Reklamation | Abo-/Outfit-Paket 46 Tests/741 Assertions lokal; Live-Vertrag/Provider/Lieferung offen |
| Eltern & Jugendschutz | Einwilligung, Kindansicht, Fremdzugriff, Widerruf | 16 Tests/237 Assertions lokal; getrennte Live-Eltern-/Kindkonten offen |
| Dateien | Upload, Vorschau, Download, Freigabe, Zugriff, Löschen eigener Testdateien | lokale Dateiverwaltung/Eventdateien 20 Tests/272 Assertions grün; Handy-Ende-zu-Ende offen |
| Einstellungen | Sprache, Darstellung, Navigation, Datenschutz, Sicherheit | Sprachwechsel DE→EN→DE live geprüft; übrige Abläufe offen |
| Suche/Benachrichtigungen/Support | globale Suche, Zielnavigation, Ticket, Hilfe | Suche 13/197, Benachrichtigungen 17/263, Support 20/526 (Tests/Assertions) lokal; Live-Zustellung/Navigation offen |

## Prüfdimensionen für jeden Ablauf

Erlaubte Rolle und unberechtigte Rolle; gültige/ungültige/leere Eingaben; Lade-/Leer-/Fehlerzustand; Speichern und erneutes Öffnen; Doppelbetätigung; Abbruch; Deutsch/Englisch sowie RTL/große Schrift; kleine Displays; offline/Wiederverbindung; API-/UI-Übereinstimmung. Externe Anbieter, Push, Zahlungen und E-Mail benötigen gesonderte Ende-zu-Ende-Nachweise.

## Laufende Ergebnisse

### Play-Entwurf auf 127 aktualisiert, Upload ausstehend

- Aktuelle Play Console (interner Test, Release 95/prepare) zeigte weiterhin einen leeren Entwurf 126 und als vorheriges Bundle 125. Entwurfsname auf **127 (1.0.42)** geändert und aktuelle DE/EN-Versionshinweise gespeichert. UI bestätigt „Änderungen gespeichert“, zwei von zwei Sprachen vorhanden.
- Kein Bundle hochgeladen, kein Rollout ausgelöst. ADB-Gerätebestand erneut leer. Browserinventar enthält nur die Play Console, keine offene Airmius-Verwaltung.
- Nächste notwendige externe Schritte: AAB 127 in den vorbereiteten Entwurf hochladen, Testversion auf dem Handy installieren und WLAN-Debugging-Verbindung bereitstellen; Airmius-Admin-Anmeldung für getrennte QA-Rollen und GitHub-Zugang für Backend-Deployment fehlen weiterhin.

### Android-Testkandidat 1.0.42 (127) gebaut

- Version und Prüfvorlagen auf 1.0.42+127 aktualisiert; DE/EN-Versionshinweise enthalten die korrigierte Fortschrittsbezeichnung. Konsistenzprüfung und CrossDeviceReadinessTest (5 Tests/77 Assertions) bestanden.
- Signierter Release-Appbundle-Build erfolgreich, 277,9 Sekunden. Artefakt: `mobile/airmius_mobile/release_evidence/artifacts/airmius-1.0.42-127-release.aab`, 90.303.393 Bytes, SHA-256 `6548c4e9ae83fd3a99502c5f816972f4d60f5eb7605487940f8626575435e6e1`. Manifestwerte com.airmius.app / 1.0.42 / 127 bestätigt.
- jarsigner: jar verified; Hinweise zu selbstsigniertem Upload-Zertifikat, fehlendem Zeitstempel und JarInputStream-/ZIP-Interpretation. Build meldet künftige KGP-/Java-Kompatibilitätswarnungen; nicht als warnungsfrei ausgewiesen.
- Neues Artefakt separat gespeichert, frühere AAB 126 erhalten. Nicht in Play hochgeladen und nicht auf dem Handy installiert. Backendcommits benötigen weiterhin separates Deployment; keine Gesamtfreigabe.

### Vollständige Flutter-Regressionsprüfung nach Menü-Korrektur

- Vollständige Flutter-Suite: **321 Tests bestanden**, etwa 87 Sekunden. Aktueller Arbeitsstand einschließlich der vier neuen Menülabel-Tests; kein Gerätesmoke oder Provider-Ende-zu-Ende-Test.
- flutter analyze fand zunächst avoid_relative_lib_imports im neuen Test. Import auf den tatsächlichen Paketnamen airmius umgestellt; erneute Analyse **No issues found**. Kein Produktcode wegen dieses Stilhinweises geändert.
- Die bestehende AAB 126 enthält die jüngste Menüänderung weiterhin nicht. Live-Zugang, neuer Build und Veröffentlichung bleiben offen; keine Gesamtfreigabe.

### Menübezeichnung „Mein Fortschritt“ lokal korrigiert

- Sichtbare Übersetzung des bestehenden Modulschlüssels Altersfreigaben in DE/EN/FR/AR auf den tatsächlichen Seitentitel abgestimmt: Mein Fortschritt / Personal progress / Ma progression / تقدمي الشخصي. Interner Schlüssel und Navigationsziel unverändert, damit gespeicherte Modulzuordnungen bestehen bleiben.
- Quelltextprüfung bestätigt Verwendung von scope.copy(module.title) in Modulabschnitten, Shell und Modulansicht. Neuer progress_module_label_test.dart: **4 Tests bestanden**, Titelgleichheit mit maturity.title in allen vier Sprachen. git diff --check bestanden.
- Diese Änderung entstand nach dem Bau der AAB 1.0.41 (126) und ist darin **nicht enthalten**. Sie benötigt einen neuen Build vor Auslieferung. Kein Upload/Deployment erfolgt; Handy-Bestätigung offen.

### Funktionsinventar korrigiert: Altersfreigaben und Medienrichtlinien

- Tatsächliches Navigationsziel des internen Modulnamens „Altersfreigaben“ ist MaturityCenterScreen: eine lesende persönliche Fortschrittsübersicht über /api/v1/maturity/overview, keine Altersfreigabeverwaltung. Die Modulübersetzungen enthalten noch Age gates/Restrictions d’âge/التحقق من العمر; diese irreführende Benennung ist als offener UX-Fehler zu korrigieren.
- MediaGuidelinesScreen ist eine Informations-/Navigationsseite mit vier Zielen: Dateien, Datenschutz, Elternbereich, Support. Keine eigene Richtlinienmutation vorhanden; die Prüfliste darf hierfür keine erfundenen Verwaltungsaktionen fordern.
- Zwei Flutter-Widgettests für diese Seiten mit arabischer RTL-Anzeige und Schriftfaktor 1,35 bestanden. Testauswahl zunächst durch falschen plain-name-Filter leer (nicht als Testnachweis gewertet), anschließend korrekt mit --name ausgeführt: **2 bestanden**. Kein nativer Linux-Build durchgeführt; Flutter-Snap meldet fehlendes clang für Linux-Anwendungen.
- Live-Navigation und die Korrektur des irreführenden Modulnamens sind noch offen; zwei Widgettests beweisen keine vollständige mobile Bedienbarkeit.

### Abgleich der Rollen- und Teamabläufe

- Funktionsmatrix auf die tatsächlich vorliegenden lokalen Teilnachweise aktualisiert. Geteilte Testpakete werden mehreren Bereichen zugeordnet und dürfen nicht addiert werden. Kein Bereich wird damit pauschal als vollständig live geprüft eingestuft.
- RoleExperienceTest, PersonaPermissionMatrixTest, TeamDailyLifeApiTest, MobileTeamInvitationApiTest und TeamMemberManagementTest: **21 bestanden, 272 Assertions** nach Sichtung der Testfälle. Nachweise für Rolleneinstieg, Arbeitsbereichsauswahl, Vereinsverwaltungsschutz, Teammitgliedschaft/-rollen, Einladungstokens und Minimierung fremder Gebühren/Elternkontakte.
- Verbleibender Schwerpunkt: getrennte Live-Rollenkonten und echte Handyabläufe über mehrere Module hinweg. Zusätzlich fehlen noch vollständige Unteraktionsinventare und gezielte Live-Nachweise für Offline, große Schrift/RTL, Medien, GPS und externe Zustellung. Testzahlen sind keine Vollständigkeitsquote.

### Sport-App-Verbindungen und Datenschutzfunktionen

- MobileSportIntegrationApiTest, PrivacyCenterTest, PrivacyRightsProcessTest und TeamSportIntegrationLocalizationContractTest nach Prüfung der Testfälle ausgeführt: **15 bestanden, 479 Assertions**.
- Abgedeckt sind normalisierter Aktivitätsimport, idempotenter Mi-Fitness-Import, verwerfbare Zusatzfelder, Schutz fremder Routen/Teams, kontogebundene Synchronisierung/Trennung, Aktivitätsbearbeitung/-löschung, minimierte Verbindungsmetadaten, Datenexport/-korrektur und Einwilligungswiderruf.
- Kein neuer Fehler; keine echten Integrationen verbunden oder getrennt. Die API-Angabe connected nach normalisiertem Import beweist keine erfolgreiche OAuth-Verbindung zu Strava/Garmin. Health-Connect-/Apple-Health-Berechtigungen, echte Anbieterimporte und Bedienung auf dem Gerät bleiben offen. Keine medizinische oder rechtliche Bewertung.

### Backend-Fixpaket gesichert, Deployment weiterhin offen

- Vollständiger Backendlauf des aktuellen Arbeitsstands: **1086 bestanden, 4 übersprungen, 27677 Assertions**, 158,80 Sekunden. Nachweis `mobile/airmius_mobile/release_evidence/backend-expanded-qa-2026-09-07.xml`. Kein vollständiger mobiler Ende-zu-Ende-Nachweis.
- Zwölf zusammengehörige Backend-Dateien separat als **8487b7aa — Fix group chat history access and sponsor outcome windows** committet. Enthält Gruppengeschichte/Zähler/Lesestatus, verständliche Ordnerbereichsfehler in vier Sprachen sowie korrekt begrenzte Sponsor-Kennzahlen und zugehörige Regressionstests. Baut auf 099c0b4a auf. Weitere lokale Testergänzungen und App-Release-Änderungen nicht in diesem Commit.
- GitHub-Remote erneut lesend geprüft: HTTPS-Anmeldung fehlt (`could not read Username`). Kein Push und kein Deployment. Ein Git-Wartungslauf meldet außerdem einen defekten Codex-Checkpoint-Ref; Commit selbst erfolgreich, HEAD und zugehöriges Tree-Objekt lesbar. Keine Git-Referenzen gelöscht oder repariert.

### Abos und Outfit-Abos: Fremde Verträge unverändert

- Mobile-Outfit-Test erweitert: Fremde Abos können weder pausiert, fortgesetzt noch gekündigt werden (403). Sämtliche gespeicherten Vertragsattribute bleiben unverändert; der Eigentümer kann den vorhandenen positiven Ablauf anschließend ausführen.
- MobileOutfitSubscriptionApiTest, OutfitSubscriptionModuleTest, SubscriptionLifecycleContractTest, UserSubscriptionAccountManagementTest, SubscriptionLocalizationContractTest und OutfitSubscriptionLocalizationContractTest: **46 bestanden, 741 Assertions**. Pint und git diff --check bestanden.
- Enthalten implementierte Mindestlaufzeiten, Kündigungszeitpunkte, bezahlten Zugang bis Vertragsende, Vereinsabrechnungsrechte, simulierte Providerereignisse und Lieferreklamationen. Keine Rechtsprüfung der Vertragsbedingungen und kein Nachweis einer echten Stripe-/PayPal-Transaktion.
- Keine produktiven Verträge verändert. Live-Bedienung, tatsächliche Providerzustellung und physische Lieferabläufe bleiben offen.

### Sportkarte und Matching: Teilnehmer kann sich nicht selbst freigeben

- Matching-Ablauf erweitert: Ein Bewerber kann seine eigene Bewerbung nicht als angenommen markieren (403), dabei entsteht kein Chat. Anschließend kann der Angebotsinhaber regulär annehmen; der bestehende Ablauf prüft die Chatverknüpfung.
- SportMatchingFeatureTest und SportMapFeatureTest: **16 bestanden, 268 Assertions**; git diff --check bestanden. Enthalten Partner-/Team-Matching, dauerhaftes Ausblenden/Wiederherstellen, Routen-/Strecken-/Sportplatz-API und Verknüpfung eines aufgezeichneten Laufs mit dem Training.
- Kein neuer Produktfehler. Routing-Anbieter und Positionsdaten sind in diesen Tests simuliert; echte GPS-Erfassung, Standortrechte, Kartendarstellung und Hintergrundtracking auf dem Handy bleiben offen. Keine realen Sportangebote veröffentlicht.

### Live-Zugang erneut geprüft; Abzeichen und Punkte lokal abgesichert

- Aktueller ADB-Gerätebestand erneut leer. Browser enthält Play-Console-Entwurf Release 95/prepare. Neu geöffnete Airmius-Anmeldeseite zeigt E-Mail-/Passwortformular, keine aktive Airmius-Admin-Sitzung. Nutzer um Anmeldung gebeten; Tab 7 geöffnet. Keine Live-QA-Konten angelegt, kein Release veröffentlicht.
- MobileBadgeApiTest, GamificationServiceTest und TrainingGamificationIntegrationTest nach Sichtung ihrer Prüfbereiche ausgeführt: **12 bestanden, 92 Assertions**. Enthalten Eigentümerschutz für Abzeichen, getrennte Nutzer-/Vereins-/Teampunktekonten, Tageslimits, doppelte Ereignisse, Trainingsabschluss und Ausschluss künftiger/fremder GPS-Protokolle.
- Keine neuen Codeänderungen oder neu entdeckten Fehler in diesem Abschnitt. Nachweise bleiben lokal; tatsächliche Darstellung und Punkteentwicklung in separaten Live-Rollenkonten sind offen.

### Elternfreigaben: Fremde Kinder und fehlende Einwilligung

- Fremdzugriffstest erweitert: Ein anderer Erziehungsberechtigter kann eine Freigabe weder erteilen noch widerrufen oder eine erneute Anfrage auslösen (404). Sämtliche gespeicherten Kindattribute bleiben unverändert; keine Benachrichtigung wird versandt.
- MobileGuardianApiTest, GuardianAccessFlowTest und MinorSafetyConceptTest: **16 bestanden, 237 Assertions**. Vorhandene Fälle prüfen Einmalcodes, Freigabe/Widerruf, Schutz geheimer Tokens, gesperrte soziale Funktionen ohne Einwilligung und private Minderjährigenprofile. Pint und git diff --check bestanden.
- Nur isolierte synthetische Konten, keine echten Kinddaten berührt. Kein neuer Produktfehler. Dies ist ein technischer Funktionsnachweis, keine rechtliche Jugendschutzprüfung. Live-Konten, Benachrichtigungszustellung und Handy-Bedienung bleiben offen.

### Marketplace: Warenkorbgrenzen und Kauf-/Verkaufsabläufe

- Käufer-Test erweitert: Fremde Warenkorbpositionen sind weder bearbeitbar noch löschbar (403). Der fremde eigene Warenkorb bleibt leer; im Eigentümerkonto bleiben Position und ursprüngliche Menge 2 erhalten.
- MobileCommerceBuyerApiTest, MobileCommerceSellerApiTest, MarketplaceTrustApiTest, MarketplacePayoutServiceTest, PublicMarketplaceTest und MarketplaceProductSubmissionTest: **45 bestanden, 393 Assertions**. Enthalten Bestellungen, geschützte Rechnungsaufrufe, Reklamation/Rückgabe, Kurszugriff/-widerruf, Verkäufergrenzen, Bestände und Auszahlungstrennung. Pint und git diff --check bestanden.
- Ausschließlich isolierte Testdaten und Zahlungszustände, keine echte Zahlung oder Auszahlung. Steuerbezogene Tests bestätigen implementiertes Verhalten, keine steuerrechtliche Prüfung. Gleichzeitige Last, echte Zahlungsanbieter und Handy-Ende-zu-Ende bleiben offen.

### Anmeldung: Mobile Sitzung nach Abmelden gesperrt

- Neuer echter Bearer-Token-API-Test (kein Sanctum actingAs): Abmelden löscht genau das verwendete Token. Nach Zurücksetzen des Test-Authentifizierungscaches erhält dasselbe Token bei /api/v1/me 401, während das Token des zweiten Testgeräts weiterhin 200 erhält und gespeichert bleibt.
- MobileAuthSecurityTest, MobilePasswordRecoveryTest, AuthenticationTest und TwoFactorEmailLoginTest: **17 bestanden, 101 Assertions**. Enthalten auch ungültige Passwörter/OTP, Einmalverwendung der Zwei-Faktor-Challenge, signierte Bestätigung und Sitzungswiderruf nach Passwort-Reset. Pint und git diff --check bestanden.
- Keine realen Zugangsdaten verändert und keine produktiven Sitzungen beendet. Kein neuer Produktfehler. Physische Gerätewechsel, Zustellung von OTP/Reset-Mails und mobile Bedienung bleiben separat offen.

### Feed: Fremde Beiträge und Kommentare schützen

- Neuer API-Test: Ein öffentlicher Beitrag ist für einen anderen Nutzer lesbar, aber nicht bearbeitbar oder löschbar. Beide Löschvarianten (DELETE und POST /delete) sowie Bearbeiten/Löschen eines fremden Kommentars liefern 403. Originalinhalte bleiben unverändert.
- MobileFeedApiTest und FeedTest: **26 bestanden, 183 Assertions**. Vorhandene Fälle umfassen zusätzlich private Teambeiträge, geschützte Bilder, Meldungen, Story-Lebenszyklus und Veröffentlichungsrechte von Vereinen/Teams. Pint und git diff --check bestanden.
- Keine öffentlichen Testbeiträge erstellt; isolierte Daten und Testmedien. Kein neuer Produktfehler in diesem Abschnitt. Reale mobile Bedienung einschließlich Medienaufnahme/-auswahl und Offline-Verhalten bleibt offen.

### Freunde: Einladungsschutz und Entfernung in beide Richtungen

- Bestehende API-Tests erweitert: Außenstehende dürfen eine fremde Einladung weder annehmen noch ablehnen oder zurückziehen (403); Status bleibt pending und es entstehen keine Freundschaften.
- Nach dem Entfernen fehlen beide Richtungen der Freundschaft. Erneutes Annehmen der bereits verbrauchten Einladung liefert 422 und stellt die entfernte Verbindung nicht wieder her.
- MobileFriendApiTest, MobileFriendInvitationApiTest und FriendInvitationTest: **11 bestanden, 69 Assertions**. Pint und git diff --check bestanden. Kein neuer Produktfehler in diesem Abschnitt.
- E-Mail-Einladungen sind hier isoliert getestet, nicht an reale Adressen verschickt. Live-Kontaktfreigabe, E-Mail-Zustellung und mobile Bedienung bleiben offen.

### Globale Suche: Private Kurse und unveröffentlichte Inhalte

- Neuer Test für Web- und mobile Such-API: Bei gleichem Suchwort wird der öffentliche veröffentlichte Kurs gefunden, während Entwurf, archivierter Kurs und veröffentlichter privater Kurs ausgeschlossen bleiben. Getrenntes Eigentümer- und Suchkonto.
- GlobalSearchTest: **13 bestanden, 197 Assertions**. Darin zusätzlich vorhandene Prüfungen für private Profile/Events, fremde Dateien, E-Mail-Suche, Modulrechte, Ergebnislimits und vier Sprachen. Pint und git diff --check bestanden.
- Kein neuer Produktfehler in diesem Abschnitt. Ergebnisse gelten für die isolierten API-/Serviceprüfungen, nicht für die vollständige mobile Suchbedienung oder jeden möglichen Inhaltstyp. Live-Suche und Zielnavigation bleiben offen.

### Support: Schutz interner Ticketfelder und Funktionsgrenzen

- Neuer API-Test: Beim Anlegen übermittelte fremde user_id, Name und E-Mail werden ignoriert; gespeichert wird der angemeldete Absender. Manipulierte Angaben für status=resolved, assigned_to, admin_note und resolved_at verändern interne Felder nicht. Das Ticket bleibt open und erscheint nicht im Konto des vorgetäuschten Absenders.
- SupportTicketApiTest, SupportCenterWebTest und MobileSupportContactTest: **20 bestanden, 526 Assertions**. Pint und git diff --check bestanden. Kein echter Supportkontakt; isolierte Tests.
- Inventargrenze: Die mobile Ticket-API bietet Erstellen und Auflisten sowie getrennte administrative Statusbearbeitung. Eigene Ticket-Antwort- und Anhang-Endpunkte sind in routes/api.php nicht vorhanden; sie sind daher nicht als bestandene mobile Funktionen ausgewiesen. last_reply_at wird bei administrativen Status-/Notizänderungen gesetzt und belegt keinen Dialogverlauf.
- Handy-Bedienung und tatsächliche Zustellung der Supportbenachrichtigungen bleiben offen. Keine vollständige App-Freigabe.

### Benachrichtigungen: Rollenübergreifender Schutz bei Sammelaktionen

- Neuer API-Test mit getrennten Konten für player, coach, club_owner und sponsor: Fremde Benachrichtigungen können weder abgerufen noch gelesen, ungelesen gesetzt oder gelöscht werden (jeweils 404).
- „Alle gelesen“ betrifft nur eigene Nicht-Chat-Benachrichtigungen. Eigene Chatmeldungen und fremde Einträge bleiben ungelesen; die gefilterte Liste eigener ungelesener Einträge wird korrekt leer.
- NotificationCenterFeatureTest, NotificationRoutingContractTest und NotificationDigestCommandTest: **17 bestanden, 263 Assertions**. Pint und git diff --check bestanden. Kein neuer Produktfehler in diesem Abschnitt.
- Die vorhandenen Tests prüfen außerdem Benachrichtigungsziele, Ruhezeiten, Push-Abwahl und Zusammenfassungen in der isolierten Umgebung. Tatsächliche Zustellung auf Android und per E-Mail wurde damit nicht bestätigt und bleibt offen.

### Sponsoren: Künftige Werbeereignisse verfälschen aktuelle Kennzahlen

- Reproduziert: Ein auf morgen datiertes sale-Ereignis erhöhte bereits sales_28d von 0 auf 1 und value_cents_28d von 1200 auf 1001199; auch CPA und ROAS änderten sich fälschlich.
- Lokal behoben: Kampagnenaggregate und Tagesverlauf verwenden dieselbe feste Zeitspanne von jetzt minus 28 Tagen bis jetzt. Künftige Ereignisse werden ausgeschlossen. Der vorhandene aktuelle Lead mit Wert 1200 bleibt enthalten.
- SponsorAgencyWorkspaceContractTest, MobileEditorialSponsorApiTest, AgencyAdsOptimizationContractTest und RevenueTrustWorkflowTest: **24 bestanden, 409 Assertions**. Pint und git diff --check bestanden. Kein Live-Werbeereignis angelegt, keine Kampagne veröffentlicht oder bezahlt.
- Diese Tests decken auch Eigentümer-/Vereinsgrenzen und die private Profilfreigabe ab; Quelltext-Vertragstests sind kein Nachweis tatsächlicher mobiler Bedienung. Live-Sponsorenkonto, Handy-Ende-zu-Ende und Deployment des neuen Fixes bleiben offen.

### Fahrgemeinschaften: Letzter Platz und Entzug privater Kontaktdaten

- Neuer API-Ablauf mit Fahrer und zwei anfragenden Mitfahrern bei insgesamt zwei Plätzen: erste Freigabe erfolgreich, wiederholte Freigabe derselben Person und Freigabe der zweiten Person bei voller Fahrt jeweils 422. Zweite Anfrage bleibt unverändert requested.
- Ein anderer Mitfahrer kann Anfragen weder bestätigen noch ablehnen oder Teilnehmer entfernen (403).
- Fahrer entfernt den ersten Mitfahrer: Teilnehmerzahl sinkt auf 1, für die entfernte Person sind Straße, privater Treffpunkt und Kontakt sofort wieder null und is_joined ist false. Der freigewordene Platz kann anschließend der zweiten Person zugeteilt werden.
- MobileRideApiTest und RideFeatureTest: **8 bestanden, 394 Assertions**. Pint und git diff --check bestanden. Kein neuer Produktfehler in diesem Abschnitt. Sequenzieller Kapazitätstest, kein Nachweis konkurrierender Datenbanktransaktionen unter Last.
- Ausschließlich isolierte Testdaten. Live-Bedienung, reale Benachrichtigungszustellung und vollständige Rollen-Ende-zu-Ende-Prüfung bleiben offen.

### Recruiting: Fremdvereinszugriff und Einstellung

- Zusätzlicher API-Test mit getrennten Vereinsverwaltern und eigenem Testbewerber: Ein fremder Verein erhält auch bei gezieltem job_id-Filter keine Bewerbungen und Statistik 0. Statusänderung, Löschung und Chatöffnung werden mit 403 abgewiesen. Gespeicherte Bewerbungsdaten bleiben exakt unverändert; kein Chat und keine Bewerberbenachrichtigung entstehen. Der zuständige Verwalter kann anschließend Status und Notiz ändern.
- Angebots-/Einstellungsablauf erweitert: erneutes Speichern von offered erzeugt keine zweite Angebotsbenachrichtigung; offered → hired erzeugt genau eine Einstellungsbenachrichtigung. Unzulässiger Rücksprung hired → reviewing wird mit 422 abgewiesen; Status bleibt hired. Keine automatische Mitgliedschaftsanfrage.
- RecruitingPipelineContractTest, RecruitingOpportunityContextTest und SportMatchingRecruitingLocalizationContractTest: **17 bestanden, 512 Assertions**. Pint und git diff --check bestanden. Nur Regressionstests ergänzt, kein neu bestätigter Produktfehler in diesem Abschnitt.
- Die zuerst fehlgeschlagene Attributprüfung wurde korrigiert: Der Ausgangszustand wird frisch aus der Datenbank gelesen, damit Datenbank-Standardwerte und Bool-Konvertierung den Vergleich nicht verfälschen.
- Isolierte SQLite-Daten, keine echten Bewerber kontaktiert. Mobile Live-Bedienung und vollständige Rollen-Ende-zu-Ende-Tests bleiben offen.

### Gruppenchat: Falsche Ungelesen-Zähler nach Wiedereintritt

- Reproduziert: Nach Lesen aller seit dem erneuten Beitritt zugänglichen Nachrichten meldete die API weiterhin 1 ungelesene Nachricht statt 0.
- Lokal korrigiert: Gemeinsamer Message-Abfragefilter `visibleSinceGroupJoin` berücksichtigt Mitgliedschaft und bei Gruppen den Beitrittszeitpunkt. Mobile und Web-Ungelesen-Zähler wenden ihn an. Die Web-Übersicht setzt außerdem nicht mehr den Zustellzeitpunkt alter unzugänglicher Nachrichten.
- Positiver und negativer Nachweis im Wiedereintrittstest: neue ungelesene Nachricht zählt 1, nach Lesen 0; Web-Zähler ebenfalls 0; alter Lesestatus und Zustellzeitpunkt unverändert. **22 Chat-Tests, 212 Assertions bestanden**. PHP-Syntaxprüfung für Message, Pint für beide Controller und den Test bestanden.
- Vollständiger Backendlauf nach diesen Änderungen: **1078 bestanden, 4 übersprungen, 27530 Assertions**, 155,68 Sekunden. JUnit: `mobile/airmius_mobile/release_evidence/backend-chat-regression-2026-09-07.xml`. Übersprungene Tests zählen nicht als bestanden.
- Live-Bedienung, Push-/Echtzeitübertragung und separate produktive QA-Rollenkonten bleiben offen. Fixes nicht deployed. Der grüne Backendlauf ist keine vollständige App-Freigabe.

### Gruppenchat: Wiedereintritt, Lesestatus und Einladungsrechte

- Weiteren Fehler reproduziert: Nach Entfernen und erneutem Beitritt über die Einladungs-API wurden alte, per Einzelaufruf gesperrte Nachrichten durch den mobilen Lesen-Endpunkt als gelesen und zugestellt markiert.
- Lokal behoben: Listenabruf, Einzelzugriff und Lesestatus nutzen denselben Beitrittszeitpunkt. Vorherige Nachrichten bleiben unverändert; neue Nachrichten werden weiterhin als gelesen markiert, ein wiederholter Lesen-Aufruf liefert 0 Änderungen.
- Einladungsrechte zusätzlich getestet: Außenstehende können eine Einladung weder annehmen noch ablehnen (403). Der Empfänger kann einmal annehmen; Wiederholungen und anschließendes Ablehnen liefern 422, ohne Beitrittszeitpunkt, Status oder Nachrichtenanzahl zu verändern.
- Nachweis: dieselben drei Chat-Testklassen jetzt **22 bestanden, 196 Assertions**. Pint und git diff --check bestanden. Keine produktiven Testnachrichten; Backendfix weiterhin nur lokal.
- Noch gesondert prüfen: Ungelesen-Zähler nach Wiedereintritt (die Zählerabfragen verwenden derzeit keine Beitrittsgrenze), reale Push-/Echtzeitübertragung und mobile Bedienung mit separaten Live-QA-Konten. Keine Gesamtfreigabe.

### Gruppenchat: Zugriff auf Nachrichten vor dem Beitritt

- Reproduzierter Datenschutzfehler: Der Einzelaufruf einer alten Gruppennachricht lieferte für ein neues Mitglied korrekt 403, die mobile Nachrichtenliste lieferte dieselbe Nachricht dennoch aus (2 statt 1 sichtbare Nachricht).
- Lokal behoben: Die Nachrichtenliste berücksichtigt bei Gruppen nun den Beitrittszeitpunkt, bevor die Ergebnisse paginiert werden. Das ursprüngliche Mitglied sieht weiterhin beide Nachrichten. Noch nicht auf den Server übertragen.
- Zusätzlicher Regressionstest: Außenstehende und über die API entfernte Mitglieder erhalten bei Unterhaltung, Nachrichtenliste, Einzelabruf, Versand, Lesestatus, Tippstatus, Reaktionen, Ausblenden und Löschen jeweils 403. Ihre Unterhaltungsliste bleibt leer; die ursprüngliche Nachricht und der Lesestatus bleiben unverändert. Die beim Entfernen regulär erzeugte Systemnachricht wird berücksichtigt.
- Nachweis: ChatSecurityTest, MobileChatRealtimeContractTest und MobileChatMessageApiTest zusammen **20 bestanden, 174 Assertions**. Pint für die beiden geänderten PHP-Dateien und git diff --check bestanden. Isolierte SQLite-Testkonten; kein echter Nachrichtenversand.
- Handy aktuell nicht erreichbar: adb devices leer; Wiederverbindung zu 192.168.0.98:41091 mit Connection refused abgewiesen. Aktuellen WLAN-Debugging-Port angefragt. Keine Live-Bestätigung dieses Fixes und keine vollständige App-Freigabe.

### Fortsetzung: verständlicher Zielordnerfehler

- Vorherige Runde war Fortschritt: fremde Datei-/Ordneraktionen und unveränderte Daten geprüft. Bestätigten UX-Befund „Unprocessable Content“ bei falschem Zielordner lokal behoben.
- UploadController verwendet einheitliche Bereichsprüfung statt dupliziertem nacktem 422-Abbruch. Fehler liefert verständlichen übersetzten Text unter `errors.folder_id`; bei falschem übergeordneten Ordner wird `parent_id` verwendet. Rechteprüfung nicht gelockert.
- Texte für DE/EN/FR/AR in file_manager-Katalogen ergänzt. Regression prüft tatsächliche API-Feldfehler und Text je Sprache; Daten bleiben weiterhin unverändert.
- FileManagerFeatureTest + EventFileContextWorkflowTest: **20 Tests, 287 Assertions bestanden**, Pint sauber für Controller, Test und vier Sprachdateien.
- Änderung liegt nach Backendcommit099c0b4a und ist noch uncommittet/nicht deployed. Kein neuer Androidbuild erforderlich für die serverseitige Meldung; tatsächliche Anzeige in der Handy-Uploadmaske dennoch noch zu verifizieren. AAB126-Upload und Live-Rollenkonten weiterhin offen.

### Fortsetzung: private Dateien und fremde Zielordner

- Vorherige Runde war Fortschritt: AAB126 geprüft und internen Release-Entwurf vorbereitet. Ohne Upload/Anmeldung abzuwarten lokale Dateiverwaltung weiter geprüft.
- Bestehenden positiven Datei-/Ordnerlebenszyklus um Fremdzugriffe ergänzt: Ordnerlöschung, Dateiumbenennung und Vorschau werden mit 403 abgewiesen. Upload in fremden privaten Ordner wird durch Bereichsprüfung mit 422 abgewiesen.
- Nach allen abgewiesenen Aktionen sind Eigentümer, Ordnername, Dateiname und genau eine ursprüngliche Datei unverändert. Danach kann Eigentümer weiterhin umbenennen und eigenen Ordner samt Inhalt löschen.
- Erste Testerwartung für fremden Upload von 403 auf den implementierten 422-Bereichsfehler korrigiert; kein bestätigter Rechtefehler. UX-Prüfpunkt: diese 422-Antwort enthält nur „Unprocessable Content“ ohne Feldhinweis; nicht behoben.
- FileManagerFeatureTest + EventFileContextWorkflowTest: **20 Tests bestanden, 272 Assertions**, Pint sauber. Storage::fake und isolierte Datenbank; keine realen Dateien gelesen/geändert/gelöscht. Handyupload, reale Dateivorschau und externe Speicher-/Netzfehler weiter offen.

### Fortsetzung: Testbuild 126 fertig, Upload offen

- Bestehenden Buildhandle 66323 abgefragt: erfolgreich abgeschlossen, 206,2 Sekunden, 90,3 MB. Nicht neu gestartet.
- Signaturprüfung `jar verified`; selbstsigniertes Uploadzertifikat und JarInputStream/ZIP-Warnungen protokolliert, keine Behauptung vollständiger Warnungsfreiheit. Packaged Manifest und Textwerte des tatsächlichen Bundle-Manifests bestätigen App-ID com.airmius.app, versionName1.0.41/versionCode126.
- Uploadkopie `mobile/airmius_mobile/release_evidence/artifacts/airmius-1.0.41-126-release.aab` erstellt; 90.304.063 Bytes, SHA-256 `4effebd4120159c3ed8b10dfa32f6b1af840b7286e0c13c7963911dd92683b3b` stimmt mit Buildartefakt überein.
- Play-Track erneut geprüft: letzter verfügbarer interner Release125. Neuen internen Entwurf geöffnet, Release-Name126(1.0.41) und Hinweise für DE/EN eingetragen; UI bestätigt 2/2 Sprachen. Als Entwurf speichern betätigt; Upload noch nicht erfolgt, kein Rollout.
- Nutzer muss lokale AAB über Uploadauswahl hochladen. Backendcommit099c0b4a benötigt weiterhin separaten Push; private Live-QA-Rollen weiterhin ohne Webadminanmeldung nicht bereitgestellt. Keine Appdeinstallation oder Überschreibung auf dem Handy.

### Fortsetzung: Android-Testkandidat 1.0.41 (126) in Erstellung

- Vorherige Runde war Fortschritt: Backend separat als 099c0b4a committet, 1074 Tests bestanden; Push mangels GitHub-Anmeldung offen.
- Nächsten internen App-Testkandidaten auf **1.0.41+126** gesetzt. Versionsregister, Smoke-Vorlagen, Manifest und Konsistenztest angepasst. Historische AAB-Nachweise in Release Notes ausdrücklich von Kandidat 126 getrennt.
- DE/EN-Storetexte aktualisiert: Kursstatus, Singularanzeigen und fehlende Trainingsdaten; keine Behauptung, dass Server-Sicherheitsfixes durch App-Upload deployed würden. Texte 215/223 Zeichen.
- Release-Version-Konsistenz erfolgreich; CrossDeviceReadinessTest **5 bestanden, 77 Assertions**. Frühere 317 Fluttertests betreffen dieselben App-Codekorrekturen vor dieser Versionsanhebung.
- Signierter AAB-Build gestartet mit Produktion-API, --release --no-pub und AIRMIUS_USE_HTTP=true. Aktive Exec-Session **66323**, mehrfach konkret geprüft, zuletzt weiterhin laufend ohne Fehlerabschluss. Nicht neu starten, sondern denselben Handle abfragen. Noch kein neues Artefakt/Hash/Upload/Handyupdate behaupten.
- Buildwarnung: file_picker, mobile_scanner, package_info_plus verwenden Kotlin Gradle Plugin; zukünftige Flutterkompatibilität gesondert prüfen. Aktueller Build hat deshalb bisher nicht abgebrochen.

### Fortsetzung: Backend-Fixes vollständig geprüft und separat committet

- Vorherige Runde war Fortschritt: Analytics-Teamrechte korrigiert. Aktuellen Git-Zustand erneut geprüft; Zweig main, Index zunächst leer. Read-only Remoteprüfung scheitert: GitHub-HTTPS-Zugang nicht angemeldet (`could not read Username`, keine Terminalprompts). Kein Push/Deployment erfolgt.
- Vollständiger Backendlauf nach allen bisherigen Sicherheits-/Datums-/Sponsorkorrekturen: **1074 bestanden, 4 übersprungen, 27430 Assertions**, 158,15 Sekunden. JUnit dauerhaft unter `mobile/airmius_mobile/release_evidence/backend-security-regression-2026-09-07.xml`.
- Gezielter lokaler Commit **099c0b4a – Fix training privacy boundaries and QA backend regressions**: vier Controller, vier bestehende Regressionstestdateien und neue MobileFullQaRoleBoundaryTest. Neun Dateien, 377 Ergänzungen/35 Löschungen. Keine Migrationen. App- und übrige QA-Änderungen bleiben uncommittet erhalten.
- Backend ist damit für den bestehenden Git-basierten Deploymentweg vorbereitet, nicht ausgeliefert. Nutzer kann Commit pushen oder GitHub-Anmeldung ermöglichen. Server-Nachprüfung der Zugriffsrechte benötigt zusätzlich QA-Rollenkonten und deren weiterhin fehlenden Admin-Webzugang. Gesamtziel bleibt offen.

### Fortsetzung: Teamkollege und trainerexklusive Analytics

- Vorherige Runde war Fortschritt: private Logs aus Trainer-Cockpit entfernt. Abweichende Analytics-Rechte gegen zentralen TrainingLogAccessService geprüft.
- **Weitere lokale Datenschutzlücke bestätigt:** normaler Teamspieler erhielt 200 für Analytics eines anderen Athleten mit `privacy_scope=trainer`, da beliebige gemeinsame Teammitgliedschaft genügte. Regression zunächst rot (erwartet 403, tatsächlich 200).
- TrainingAnalyticsController verwendet jetzt zentrale `visibleQuery` sowohl für Zugangsprüfung als auch eigentliche Datenabfrage. Teamzugehörigkeit allein hebt die Trainerfreigabe nicht mehr auf.
- Positiver Gegenfall: nach expliziter Freigabe `team` ist genau dieser Log sichtbar. Parallel vorhandener weiterer `trainer`-Log bleibt aus Titeln, Anzahl und Dauer ausgeschlossen; 45 statt 225 Minuten. Bestehende Trainer-/Privat-/Fremdkonto-Tests weiter grün.
- Kombiniert Analytics, TrainingLog CRUD, TrainingSystem, TrainingWorkflowIntegration, MobileTrainerCockpit, TrainerCockpitWeeklyControl und MobileFullQaRoleBoundary: **26 Tests bestanden, 240 Assertions**, Testformat und diff-check sauber.
- Sicherheitsrelevante Backendänderung noch nicht deployed. Keine realen Nutzer-/Trainingsdaten zur Reproduktion verwendet. Der bestehende zentrale Zugriffsdienst definiert Eigentümer-/Ersteller-/Adminausnahmen; diese wurden nicht als neue Rechte erfunden. Nachprüfung auf Server und voller Regressionslauf nach diesen letzten Datenschutzänderungen bleiben offen.

### Fortsetzung: privater Trainingslog im Trainer-Cockpit

- Vorherige Runde war Fortschritt: zukünftige Logs aus Wochenwerten und Feedback ausgeschlossen. Datenschutz-Prüfpunkt gezielt mit separatem Coach/Athlet lokal reproduziert.
- **Bestätigte Datenschutzlücke im geprüften Backendstand:** Athlet-eigener Log mit `metrics.privacy_scope=private` erschien für Teamtrainer in recentLogs und feedbackOpen; Dauer und Schmerzwerte flossen in Wochenaggregate ein. Test zunächst rot durch sichtbaren Titel `QA private log`.
- Cockpit-Listen und Wochenquery verwenden nun zentral `TrainingLogAccessService::visibleQuery($user)` zusätzlich zur bisherigen Team-/Trainerabgrenzung. Kein separates konkurrierendes Rechtesystem: Eigentümer-/Ersteller-/Adminausnahmen folgen dem bestehenden Dienst.
- Regression bestätigt nur freigegebenen Log sichtbar, 30 statt 210 Minuten, eine Einheit in Wochenstatistik und Teamkarte sowie kein privater Risikobeitrag. Direkter Feedbackversuch auf privaten Log liefert ebenfalls 403 und erzeugt keinen Feedbackdatensatz.
- Abschließend MobileTrainerCockpitApiTest + TrainerCockpitWeeklyControlTest + TrainingAnalyticsApiTest + MobileFullQaRoleBoundaryTest: **15 Tests, 136 Assertions bestanden**, Pint sauber.
- Sicherheitsrelevanter lokaler Fix noch nicht deployed. Keine tatsächliche Offenlegung auf dem Produktivserver untersucht oder behauptet; keine produktiven privaten Logs abgerufen. Server-Nachprüfung nach Auslieferung mit QA-Konten erforderlich. Andere Trainingszugriffspfade bleiben separat zu prüfen.

### Fortsetzung: Trainer-Wochenfenster und zukünftiges Feedback

- Vorherige Runde war Fortschritt: Analytics-Enddatum korrigiert. Aktuellen Trainer-Cockpit-Code geprüft und denselben fehlenden oberen Datumsrand bestätigt.
- Neue Regression mit Beginn aktueller Woche, Ende Vorwoche und morgigem Eintrag: zunächst 2 statt 1 aktuelle Einheit. Lokale Abfrage in coachWeekly bis einschließlich heute begrenzt. Vorwoche bleibt korrekt, Teamkarte enthält eine Einheit, morgiger Schmerztest erzeugt keinen aktuellen Risikohinweis.
- Nachprüfung zeigte zweiten Einfluss: morgiger abgeschlossener Log erschien weiterhin als offenes Feedback (3 statt 2), das die Bereitschaft beeinflusst. feedbackOpen begrenzt datierte Logs jetzt ebenfalls auf heute; bestehende undatierte Feedbackfälle bleiben kompatibel sichtbar.
- Abschließender Lauf MobileTrainerCockpitApiTest, TrainerCockpitWeeklyControlTest, TrainingAnalyticsApiTest, MobileFullQaRoleBoundaryTest: **14 Tests, 125 Assertions bestanden**, Pint sauber.
- Kein Deployment und keine produktiven Logänderungen. Weiterer Code-Prüfpunkt entdeckt: In Cockpit-Logabfragen ist anders als in TrainingAnalytics keine offensichtliche privacy_scope-Filterung sichtbar. Noch nicht reproduziert, nicht als bestätigter Datenabfluss ausweisen; gezielter privater Logtest als nächster Schritt.

### Fortsetzung: Datumsgrenzen bei Terminen und Trainingsauswertung

- Vorherige Runde war Fortschritt: Ernährung-Datumsfehler behoben. Verwandte Datumsverarbeitung im aktuellen API-Code geprüft; Termine validieren from/to/calendar_month bereits vor Parsing.
- Neue Terminregression: ungültiges from, unmöglicher to-Tag, Monat 13 und vollständiger Tag statt Monatsformat werden mit 422/Feldfehler abgewiesen; gültiger Septemberfilter funktioniert. Keine Events angelegt. Kombinierter Termin-/Web-/Routen-/Dateikontextlauf: **22 Tests, 337 Assertions bestanden**.
- Neuer Grenztest für Trainingsanalytics reproduziert falsche 3 statt 2 Einheiten: Bericht nennt Zeitraum 1.–7. September, zählte aber Eintrag vom 8. September mit. Alter Eintrag wurde korrekt ausgeschlossen; obere Datumsgrenze fehlte.
- `TrainingAnalyticsController` lokal um inklusive Tagesendgrenze ergänzt; Response verwendet denselben Endwert. Test bestätigt beide Randtage eingeschlossen, älterer und morgiger Eintrag ausgeschlossen und korrekte Summe von 60 Minuten.
- TrainingAnalyticsApiTest + MobileTrainerCockpitApiTest + MobileEventApiTest: **18 Tests, 173 Assertions bestanden**. Neue Testdateiänderungen Pint sauber. Keine produktiven Trainingsdaten geändert; Analyticsfix noch nicht deployed. Andere Auswertungen, Zeitzonen und parallele Mitternachtswechsel sind hiermit nicht vollständig geprüft.

### Fortsetzung: Ernährung – ungültiges Datum und private Tagesdaten

- Vorherige Runde klärte den fehlenden Live-Adminzugang erneut; unabhängig davon sichere lokale Ernährungstests erweitert. Kein globaler Stillstand.
- Neuer negativer API-Test reproduzierte **500 statt 422** bei `/api/v1/nutrition?date=not-a-date`: Carbon wurde vor einer Eingabevalidierung aufgerufen. Lokale Korrektur in `NutritionController::index`: optionalen Datumsparameter vor Parsing validieren.
- Regression bestätigt 422 mit Feldfehler bei ungültigem Datum, unmöglichem 30. Februar und relativem `tomorrow`. Wassermengen leer, negativ, null, über 5000 ml und nichtnumerisch werden abgewiesen; kein Mahlzeiteneintrag entsteht.
- Weiterer Test: Wasser vom 7. September erscheint nicht am 8. September und nicht im Konto eines anderen Nutzers. Fremde PATCH-/DELETE-Versuche liefern 403; Eigentümer und Wassermenge unverändert.
- NutritionFeatureTest und NutritionAiImageApiTest: **8 Tests bestanden, 119 Assertions**, Pint sauber. Der zuvor dokumentierte volle Backendlauf liegt vor dieser neuen Datumsänderung; kein erneuter voller Lauf behauptet.
- Nur isolierte lokale Daten. Datumsfix noch nicht deployed; keine echten Ernährungswerte verändert. Live-Testkonten benötigen weiterhin die bereits angefragte Airmius-Adminanmeldung.

### Fortsetzung: Live-Rollenkonten – Zugang erneut geprüft

- Vorherige Runde war Fortschritt: vollständige App-/Backendregression abgeschlossen. Browserbestand aktuell geprüft: nur Play Console war geöffnet, keine Airmius-Websitzung.
- Airmius-Anmeldeseite neu geöffnet (Browser-Tab 6, `https://airmius.com/login`); tatsächlicher Loginbildschirm mit E-Mail/Passwort und externen Loginoptionen bestätigt. Keine aktive Web-Adminanmeldung vorhanden.
- Backend-Kontoanlage erneut geprüft: geschützter Endpunkt `/api/v1/admin/platform/users` unterstützt private Profile und deaktivierten Zugangsdatenversand. Erlaubnis zur QA-Kontoanlage liegt vor; fehlende Anmeldung ist kein fehlendes Einverständnis, sondern der technische Zugang für die Rollenbereitstellung.
- Nutzer um eigene Anmeldung im geöffneten Tab gebeten; keine Passwörter oder Handytokens ausgelesen. Noch keine separaten Live-QA-Konten angelegt. Globale Zielerreichung weiterhin unbewiesen; lokale und andere Live-Prüfungen bleiben unabhängig möglich.

### Fortsetzung: vollständige Regression nach den Korrekturen

- Vorherige Runde war Fortschritt: bestätigte Sprachfehler in Kurs/Training lokal korrigiert. Aktueller Änderungsstand mit `git diff --check` ohne Whitespacefehler geprüft.
- Vollständige Flutter-Suite: **317 Tests bestanden**, Laufzeit etwa 64 Sekunden. Anschließendes `flutter analyze --no-pub`: **No issues found**. Damit neue Sprach-/Trainerkorrekturen gemeinsam mit allen vorhandenen Fluttertests geprüft, aber noch nicht aufs Handy ausgeliefert.
- Vollständige Backend-Suite: **1067 bestanden, 4 übersprungen, 27351 Assertions**, 178,74 Sekunden. Aktueller JUnit-Nachweis dauerhaft unter `mobile/airmius_mobile/release_evidence/full-app-qa-backend-current-2026-09-07.xml`.
- Gegenüber dem alten Gesamtlauf sind insbesondere die neuen Rollen-, Sponsor-, Mitgliedschafts- und Blogregressionen enthalten. Kein neuer Fehler im Gesamtlauf. Übersprungene Fälle zählen nicht als bestanden.
- Weiterhin keine Gesamtfreigabe: isolierte Tests/Fake-Transporte ersetzen keine getrennten Live-Rollenkonten, externe Integrationen, tatsächliche Zustellung, vollständige Handybedienung, Offline-/Neustarttests oder Nachprüfung nach Auslieferung. Funktionskatalog bleibt offen.

### Fortsetzung: bestätigte Sprachfehler lokal korrigiert

- Vorherige Runde war Fortschritt: Ernährungsvalidierung und DE/EN-Sprachwechsel live geprüft. Quellen der zuvor bestätigten Anzeigen `Draft`, `1 Lektionen`, `1 Einheiten` und `1 Übungen · 3 Soll-Sätze` untersucht.
- Kurskarte verwendet jetzt vorhandene `studio.status.*`-Übersetzungen statt englischer Titel-Schreibweise; Lektionenanzeige unterscheidet Einzahl/Mehrzahl. Andere generische Statusfelder (Fragen, Abgaben, Einschreibungen) sind damit nicht automatisch lokalisiert und bleiben zu prüfen.
- Planliste und Planübersicht unterscheiden eine Einheit von mehreren; Übungsanzeigen in Detail, Log und Vorschau unterscheiden eine Übung. Neuer `trainingPlanCountLabel` unterscheidet auch einen Soll-Satz von mehreren.
- Neue Einzahl-/Soll-Satz-Schlüssel für DE/EN/FR/AR. Dies ist keine vollständige arabische Pluralgrammatikprüfung für alle Zahlenklassen.
- Neuer Zahlentest für vier Sprachen plus Kurs-Widget-Regression (`Entwurf`, `1 Lektion`, kein `Draft`/`1 Lektionen`) und Trainingsleerzustand: **6 Tests bestanden**. Formatprüfung: 4 Dateien, keine Änderungen nötig. Flutter analyze nach Hauptänderungen sauber; danach nur zwei gleichartige Einzahl-Ausdrücke in Log/Vorschau ergänzt und mit abschließendem Widgetlauf kompiliert.
- Anfangs falscher Paketname im neuen Testimport korrigiert; kein Produktfehler. Noch kein neuer AAB-Build, keine Auslieferung aufs Handy. Live-Nachprüfung der korrigierten Anzeigen bleibt erforderlich.

### Fortsetzung: Ernährung und Sprache live

- Vorherige Runde war Fortschritt: Blog-Grenzfälle geprüft. ADB-Verbindung zum Xiaomi erneut bestätigt; installierte App zeigt noch die bekannte unkorrigierte Trainerbereitschaft (lokale Korrektur noch nicht ausgeliefert).
- Ernährung über Menü/Schnellzugriff geöffnet. Tagesübersicht mit gespeicherten Summen lädt; Mahlzeit, Lebensmittelsuche, Barcode und Fotoanalyse werden als getrennte Eingabewege angeboten.
- Manuelles Mahlzeitenformular leer abgesendet: verständlicher Fehler „Bitte gib eine Bezeichnung ein.“. Abgebrochen, Tageswerte bleiben unverändert. Kein Wasser-Schnellbutton betätigt und kein Ernährungsdatensatz angelegt.
- Einstellungen > Sprache: DE auf EN umgestellt; Language/Back und Erklärung wechseln sofort. Zurück zur Einstellungsseite: Settings, englische Kontotexte und EN sichtbar. Anschließend Deutsch wiederhergestellt und deutsche Sprachseite bestätigt.
- Keine Konto-/Sicherheitseinstellung verändert außer dem temporären, zurückgestellten Sprachwechsel. Kein vollständiger Übersetzungs-/Layoutnachweis aller Module; Neustartpersistenz, RTL, Darstellung, Footer-Anpassung und Datenschutzaktionen weiter offen.

### Fortsetzung: Blog-Veröffentlichungsrechte und öffentliche Sichtbarkeit

- Vorherige Runde war Fortschritt: Kurs-Datenschutz und Entzug einer Einschreibung geprüft. Aktuelle Redaktion/Public-Blog-Implementierung und bestehende Tests gelesen.
- Neue API-Regression: Redakteur mit view/create/update, aber ohne publish/delete kann Prüfentwurf anlegen. Veröffentlichungsversuche bei Anlage und Änderung sowie Löschung werden mit 403 abgewiesen. Datensatz bleibt review ohne Publisher; genau eine ursprüngliche Revision und ein Beitrag bleiben bestehen.
- Neue öffentliche API-Prüfung: draft, review, archived und published mit zukünftigem Termin liefern beim direkten Abruf 404; Liste enthält keinen dieser QA-Inhalte. Bereits veröffentlichter Artikel erscheint und ist abrufbar.
- Kombiniert MobileEditorialSponsorApiTest (enthält auch Sponsorregressionen), BlogPublicTest, BlogTranslationWorkflowTest und LocalizedBlogFeedTest: **34 Tests bestanden, 392 Assertions**, Pint sauber. JUnit `/tmp/airmius-editorial-qa-20260907.xml`.
- Kein neuer Produktfehler in diesen Pfaden. Keine produktiven Artikel veröffentlicht, geändert oder gelöscht. Lokale API/Web-Tests sind kein Nachweis mobiler Redaktionsbedienung, Bilddarstellung oder tatsächlicher Veröffentlichung auf dem Server; diese Prüfungen bleiben offen.

### Fortsetzung: Lernenden-Datenschutz und Einschreibungsentzug

- Vorherige Runde war Fortschritt: Mitglieder-Grenzfälle ergänzt und 27 Tests bestanden. Kurs-/Studio-Tests und aktuelle API-Implementierung gelesen.
- Bestehenden mobilen Ende-zu-Ende-API-Test erweitert: zweiter eingeschriebener Lernender erhält keine privaten Notizen des ersten und kann dessen Zertifikat weder als JSON noch als PDF abrufen (403).
- Danach Kursinhaber über tatsächlichen Studio-API-Endpunkt die abgeschlossene Einschreibung widerrufen lassen. Status cancelled bestätigt. Erster Lernender erhält gesperrte Lektion ohne Inhalt, darf keinen Fortschritt mehr schreiben und weder Zertifikatsdaten noch PDF herunterladen (403).
- Erweiterter MobileLearningApiTest: 3 Tests, 67 Assertions grün. Kombiniert mit MobileLearningStudioApiTest, LearningStudioTest, LearningCourseTranslationWorkflowTest und LearningLocalizationContractTest: **43 bestanden, 729 Assertions**, Pint sauber. JUnit `/tmp/airmius-learning-qa-20260907.xml`.
- Inhaltlich vorhandene Abdeckung umfasst auch falsche/richtige Quizantwort, Pflichtaufgabe vor Zertifikat, zeitverzögerte Lektion/Quiz, Videofortschritt, Kursstruktur, Einschreibung und Widerruf sowie Übersetzungsabläufe. Kein neuer Produktfehler in dieser Runde bestätigt.
- Grenzen: isolierte Laravel-Tests, kein Live-Kursabschluss auf dem Handy; PDF-Response geprüft, kein neues visuelles PDF-Layoutaudit. Öffentliche Zertifikatsprüfung ist ein separater bewusst öffentlicher Endpunkt und nicht mit privatem Zertifikatsdownload gleichzusetzen. Zahlung, echte E-Mail-Zustellung und Rollen-UI weiter offen.

### Fortsetzung: Mitglieder-Lebenszyklus und fremde Antrags-IDs

- Vorherige Runde war Fortschritt (Sponsorberechtigungsfehler reproduziert und lokal korrigiert). Aktuellen Arbeitsbaum gelesen; bestehende Änderungen erhalten.
- Bestehende Mitglieder-Tests inhaltlich geprüft: Anträge samt Pflichtdokumenten, Annahme/Ablehnung/Pause/Audit, Kündigung und terminierte Teamabmeldung, Importvorschau/-fehler/-duplikate, Beitrags-/Bankdaten, Rechnung/Zahlungsstatus/Erinnerung sowie Verwaltungszugriff.
- Neue API-Regression in `ClubMembershipApplicationFlowTest`: Vereinsinhaber versucht fremden Antrag unter eigener Vereins-ID zu genehmigen/abzulehnen (404), danach unter fremder Vereins-ID (403). Status und Prüfer bleiben unverändert; keine Mitgliedschaft wird angelegt.
- Richtiger Vereinsinhaber kann denselben Antrag genehmigen. Erneutes Genehmigen und anschließendes Ablehnen ergeben 422; genau eine Mitgliedschaft und ursprünglicher Prüfvermerk bleiben erhalten. Neuer Fall grün, kein Produktfehler in diesem geprüften Pfad.
- Kombinierter Lauf der sieben Mitglieder-Testklassen: **27 bestanden, 342 Assertions**, Pint sauber. JUnit: `/tmp/airmius-membership-qa-20260907.xml`.
- Nur lokale isolierte Daten, keine tatsächliche Mailzustellung/Bankübertragung und kein positiver rollengetrennter Handytest. Exporttests belegen Response/Inhalte, nicht vollständige externe Bankkompatibilität. Gleichzeitige parallele Freigabe (Race Condition) und Offline-Wiederholung bleiben ungetestet.

### Fortsetzung: globale Sponsorenverwaltung und Bereichswechsel

- Vorherige Runde war Fortschritt: Trainingsmodus und lokale Wiederaufnahme am Handy verifiziert. Nun Sponsor-API-Abdeckung geprüft und zwei fehlende Regressionsfälle ergänzt.
- Bestätigter Backendfehler: global berechtigter Verwalter (`finance.edit`) kann Vereinspartner auflisten, wird beim Update desselben Datensatzes aber mit 403 abgewiesen. Ursache: `canManageSponsor` berücksichtigte globale Rechte nur bei Partnern ohne Verein, obwohl `index` und `authorizeScope` globale Verwaltung erlauben.
- Test zuerst rot (erwartet 200, tatsächlich 403). `SponsorManagementController::canManageSponsor` lokal korrigiert: globale Berechtigung vor Vereinsprüfung; normale Nutzer bleiben auf verwaltete Vereine begrenzt.
- Positiver Test: globaler Verwalter listet fremden Vereinspartner, aktualisiert ihn und löscht anschließend einen Vereinspartner ohne eigene Vereinsmitgliedschaft. Negativer Test: Vereinsinhaber darf eigenen Partner weder auf Plattform/Outfit-Bereich noch in fremden Verein verschieben; Ursprungsdaten bleiben unverändert.
- Beim ersten Testlauf fehlte den neu angelegten Testvereinen ein Eigentümer; Fixture korrigiert. Dies war kein Produktfehler.
- Kombinierter Lauf MobileEditorialSponsorApiTest, SponsorAgencyWorkspaceContractTest und MobileFullQaRoleBoundaryTest: **14 bestanden, 340 Assertions**, Pint sauber.
- Ausschließlich isolierte lokale Daten verändert. Kein produktiver Sponsor angelegt/geändert/gelöscht. Backendkorrektur noch nicht deployed; positive Live-Rollenprüfung und Kampagnenabläufe weiter offen.

### Fortsetzung: Trainingsmodus und lokale Wiederaufnahme live

- Vorherige Runde lieferte Fortschritt: Übung mit drei Soll-Sätzen gespeichert und erneut geladen. ADB-Gerät erneut bestätigt.
- Eigene **QA Einheit** über Training starten geöffnet: drei Sätze, Sollwerte zehn Wiederholungen/90 Sekunden Pause, lokale Sicherung und Fortschritt 0/3 sichtbar.
- Ersten Ist-Wert auf acht Wiederholungen geändert. Sollwert blieb zehn; Anzeige bestätigt die Trennung. Kein Satz als durchgeführt bestätigt.
- Abbrechen bietet Weiter trainieren, Training verwerfen und Speichern & verlassen. Speichern & verlassen gewählt und anschließend dieselbe Einheit erneut gestartet.
- Automatische Wiederaufnahme bestätigt durch Hinweis „Gespeichertes Training wurde fortgesetzt.“; erster Ist-Wert weiterhin acht, Sollwert zehn, Fortschritt weiterhin 0/3. Positiver lokaler Resume-Test, kein App-Neustart-/Offline-Test.
- Danach ausschließlich den eigenen QA-Zwischenstand über Training verwerfen entfernt. Plan und Soll-Sätze bleiben für weitere Tests vorhanden. Kein Trainingsabschluss ausgelöst; Server-Trainingslog und Auswertungen in dieser Runde nicht geprüft.

### Fortsetzung: Übungsbibliothek und Einheitsbearbeitung live

- Gerät `192.168.0.98:41091` erneut verbunden bestätigt. Nur eigene Einheit **QA Einheit** im Entwurf **QA Trainingsplan 20260907** bearbeitet.
- Aktionsmenü > Bearbeiten > Training > Übungsbibliothek erfolgreich geöffnet. Starter-Vorlagen sichtbar, darunter Kniebeuge und sportartspezifische Vorlagen.
- Kniebeuge übernommen: drei Soll-Sätze mit je zehn Wiederholungen, erster Satz zeigt 90 Sekunden Pause. Übernehmen, Details und Sicher speichern erfolgreich durchlaufen.
- Planansicht danach zeigt eine Übung und drei Soll-Sätze, weiterhin Entwurf und **0 Zuweisungen**. Erneut über Bearbeiten > Training geöffnet: Kniebeuge und alle drei Vorgaben à zehn Wiederholungen erhalten. Positiver Persistenztest bestanden.
- Weiterer bestätigter deutscher Singularfehler: `1 Übungen · 3 Soll-Sätze`. Noch nicht behoben.
- Keine Trainingsergebnisse erfasst, keine Benachrichtigungen absichtlich ausgelöst und keine echten Empfänger zugewiesen. Übungsausführung, Satzänderungen/Grenzwerte, Reihenfolge und rollengetrennte Nutzung bleiben offen.

### Fortsetzung: manuelle Trainingsplanerstellung live

- Aktuellen Bildschirm/ADB-Verbindung neu geprüft. Vorherige Runde war Fortschritt (Widget-Regression zur Bereitschaftsanzeige).
- Trainer-Cockpit > Planung: überfällige Einheiten und kommende 14 Tage zeigen Leerzustände; „Trainingspläne öffnen“ führt korrekt zur Planliste.
- Vollständiger manueller Assistent (6 Schritte) mit eigenem QA-Entwurf durchlaufen: Basis, Ziel/Zeitraum, Freigabe, Einheitsdaten, Übungen/Sätze, weitere Details. Leerer Titel ließ kein Weitergehen zu; nach Titel war Schritt 2 erreichbar.
- Neuer Live-Datensatz **QA Trainingsplan 20260907**, Rhythmus wöchentlich, Status Entwurf, Ziel „Für mich selbst“, Einheit **QA Einheit**, Sportart-Voreinstellung laufen. Keine Übungen, keine KI-Nutzung, keine Empfänger ausgewählt.
- „Sicher speichern“ erfolgreich; neuer Plan in Liste als Entwurf sichtbar. Erneut geöffnet: Titel/Einheit korrekt, **0 Zuweisungen** bestätigt. Kein bestehender Plan verändert. QA-Plan bleibt für Folgetests erhalten.
- Weiterer bestätigter Sprachfehler: „1 Einheiten“ in Planliste. Nicht behoben.
- Noch offen: Übungsbibliothek und Sätze, Planbearbeitung/-kopie/-löschung, tatsächliche Zuweisung an QA-Sportler/QA-Team, Ausführung/Log/Feedback, Wiederholungsregeln und Zeitgrenzen. Der einfache Speicherweg allein ist keine vollständige Trainingsfreigabe.

### Fortsetzung: Trainer-Leerzustand in der Android-Oberfläche

- Vorherige Runde war Fortschritt (Backend-Leerzustand reproduziert und korrigiert). Quellstand erneut geprüft.
- Neuer Widgettest `trainer cockpit does not present missing data as readiness` reproduziert, dass trotz risk_level empty noch Prozentzahl/Balken angezeigt wurden (zuerst rot: 86% unerwartet vorhanden).
- Lokal korrigiert: Trainer-Hero zeigt bei empty ausschließlich lokalisiertes „Keine Daten“, weder Bereitschaftsprozent noch Fortschrittsbalken. Bestehende Darstellung bei echten Werten bleibt erhalten.
- Drei Trainer-Client-/Widgettests bestanden, einschließlich vorhandener 68%-Darstellung, Feedback-/Teamnavigation und neuer Leerzustandsprüfung bei Textskalierung 1,35. Flutter analyze ohne Befund, diff-check sauber.
- Nicht neu gebaut/veröffentlicht/installiert; Handy weiterhin 1.0.40 (125). Für Live-Nachprüfung werden Backend-Deployment und neues Android-Build benötigt. Webansicht ist separat: dort numerischer Leerwert noch nicht korrigiert. Gesamtumfang bleibt offen.

### Fortsetzung: Trainer-Cockpit und datenlose Bereitschaft

- Aktuelle ADB-Verbindung bestätigt, Trainer-Cockpit live geöffnet. Übersicht mit Team-/Athletenzahlen und Tabs Übersicht/Teams/Feedback/Planung sichtbar; Feedbacktab öffnet zugehörigen Abschnitt. Keine Trainings- oder Mitgliedsdaten verändert.
- Auffällig live: Bereitschaft 86%, Status Stabil. Codeprüfung zeigt Grundwert 86 (Gesamt) bzw. 82 (Team), auch ohne aktuelle Logs.
- **Bestätigter Fehler**, mit isoliertem Team ohne Trainingseinträge reproduziert: `test_teams_without_current_training_data_do_not_report_stable_readiness` scheiterte zuerst mit `good` statt `empty` bei 0 Sessions.
- Lokal korrigiert in TrainerCockpitController: ohne aktuelle Trainingslogs Score 0 entsprechend bestehender Leerzustandskonvention und risk_level empty, sowohl insgesamt als auch pro Team. Übersetzung für empty bereits in DE/EN/FR/AR vorhanden. Kein medizinischer Bereitschaftsnachweis; Berechnung bleibt heuristisch.
- Prüfung danach: MobileTrainerCockpitApiTest + TrainerCockpitWeeklyControlTest + MobileFullQaRoleBoundaryTest = 10 bestanden, 96 Assertions, Pint und diff-check sauber. Mit vorhandenen Daten bleiben Risiko-/Wochenkontrolltests grün.
- Nicht deployed. Die Live-App zeigt weiterhin Serverstand. UI zeigt bei empty weiterhin numerisch 0%; diese Darstellung sollte separat von „tatsächlich 0% Bereitschaft“ abgegrenzt werden. Wochen-/Team-/Trainingsaktionen und getrennte Live-Trainerrolle bleiben offen.

### Fortsetzung: rollenübergreifende API-Schutzprüfung

- Vorherige Runde war Fortschritt (Vereins-/Mitgliederansichten). Neue isolierte Regression `tests/Feature/MobileFullQaRoleBoundaryTest.php`: je ein eigener Nutzer mit ausschließlich player, coach, club_owner bzw. sponsor. Produktionsrollen aus RolesPermissionsSeeder, keine Rechte künstlich erweitert.
- Für alle vier Rollen: Challenges/Lernen/Sportarten erfolgreich erreichbar; fremde Vereinsmitglieder nicht lesbar; fremde Mitglieder weder zu Admins hochstufbar noch löschbar; Mitglieder-IDs und ursprüngliche Rolle bleiben unverändert. Redaktion und Plattform-Kontoanlage abgewiesen. Sponsor darf eigenes Workspace öffnen, aber kein Trainer-Cockpit; andere drei Rollen dürfen kein Sponsor-Workspace öffnen.
- Neuer Test: 4 bestanden, 49 Assertions. Zusammen mit PersonaPermissionMatrix, MobileTrainerCockpitApi, MobileClubMembershipParityApi, MobileEditorialSponsorApi und SponsorAgencyWorkspaceContract: **26 bestanden, 496 Assertions**, Pint sauber. JUnit zunächst `/tmp/airmius-role-qa.xml`.
- Anfangs fehlerhafte Testannahme zur Mitgliederanzahl korrigiert: Club::created fügt den Eigentümer automatisch hinzu. Regression vergleicht nun die vollständige Mitglieder-ID-Liste vor/nach Angriffen. Kein Produktfehler in diesen Fällen festgestellt.
- Grenzen: lokale API-Prüfung unter SQLite/Sanctum-Testauth, keine echte Passwortanmeldung oder Handy-Rollensitzung. Ersetzt nicht die noch fehlenden getrennten Live-Konten und End-to-End-Prüfungen.

### Fortsetzung: Verein und Mitgliederverwaltung live

- Vorherige Runde war Fortschritt (Sponsorenformular-Validierung und Blogansicht). Aktuelle ADB-Verbindung bestätigt. CUA-Bestandsaufnahme: Airmius-Webtab 5 geschlossen, nur Play Console vorhanden; keine Admin-Webanmeldung. Rollentrennung bleibt teilblockiert, andere Prüfungen gehen weiter.
- Vereins-Cockpit geöffnet: Auswahl zwischen den zwei verwaltbaren Vereinen vorhanden; aktiver Verein Flutter. Dashboard zeigt Mitglieder-/Team-/Plan-/Speicher-/Anfragen-/Rechnungswerte und Setup-Checkliste.
- „Mitglieder & Beiträge“ erreicht: korrekter aktiver Verein, acht Unterbereiche sichtbar (Übersicht, Regeln, Anfragen, Mitglieder, Einladen, Finanzen, Export, Änderungen). Übersicht unterscheidet aktive Mitglieder von verknüpften Personen.
- „Anfragen“ öffnet eine separate Inbox, lädt den Filter Neu mit 0 Anfragen und klarem Leerzustand. Keine Entscheidung ausgelöst. ACHTUNG für weitere Automatisierung: nicht alle acht Aktionen sind Inline-Tabs; nach Anfragen erst zurück navigieren und Koordinaten neu erfassen.
- „Finanzen“ lädt innerhalb der Mitgliederverwaltung Kassen-/Bank-/Gesamtübersicht. Nur gelesen, keine Zahlung, Rechnung oder Buchung ausgelöst.
- UX-Prüfpunkt: zentrale Verwaltungsaktion im Vereins-Cockpit erst nach zwei langen Scrollbewegungen hinter Kennzahlen und Einrichtungsliste erreichbar. Nicht als technischer Fehler eingestuft.
- Nicht geprüft in dieser Runde: positive Mitgliederanlage/-änderung, Einladungsversand, Import/Exportdownload, Beitragskonfiguration, Finanztransaktionen, fremder Vereinszugriff. Bestehende Mitglieder und Finanzen unverändert.

### Fortsetzung: Sponsoren und Blog live

- Vorherige Runde lieferte Fortschritt (mobile Nutzerverwaltungslücke, Web-Anmeldeweg). Aktuelle ADB-Verbindung erneut bestätigt.
- Sponsorenübersicht im bestehenden Admin-/Mehrrollenkonto: lädt, 0 aktive Partner, Filter Alle/Plattform/Vereine/Outfit sichtbar. Geschützte Sponsorverwaltung geöffnet, 0 verwaltbare Sponsoren, Anlegen-Formular erreichbar.
- Leeres Sponsorformular abgesendet: Name wird lokal mit „Pflichtfeld“ markiert. Formular anschließend ohne Speicherung geschlossen. Kein Sponsor angelegt, keine echten Vereinsdaten verändert. Positive Anlage/Bearbeitung sowie Sponsor-Cockpit mit eigener Sponsorrolle bleiben offen.
- Blog & Wissen: Liste lädt mit einem Artikel; vorhandenen Artikel geöffnet, Textinhalt wird angezeigt. Suche und Kategoriechips sichtbar, noch nicht getestet. Öffentlich vorhandene Inhalte wirken wie Testdaten (Titel `ewf2ef2e`, Artikeltext `f22f2`); Inhaltspflege erforderlich, kein durch diesen Test verursachter Datensatz, nichts geändert/gelöscht.
- Blogredaktion, Entwurf/Review/Veröffentlichung, Übersetzung und Revision bleiben live offen. Die erfolgreichen Listenaufrufe sind keine Bestätigung vollständiger Sponsoren-/Blogfunktionalität.

- Vorherige Goal-Runde unterbrochen: kein abgeschlossener Gesamttest. Diese Runde beginnt mit aktueller Bestandsaufnahme und vollständigem Backendlauf.
- Bestehende lokale Änderungen bleiben erhalten. Keine produktive Rollenänderung vorgenommen.
- Nutzer hat die Anlage eigener Testkonten ausdrücklich erlaubt. Noch keine persistenten Live-Testkonten angelegt; bestehende PHPUnit-Fixtures decken Rollen isoliert ab. Admin-Kontoanlage unterstützt private Profile und deaktivierten Zugangsdatenversand; vor Live-Anlage sind Anmeldung/Verifizierung und Rollenvergabe als kompletter Weg zu prüfen.
- Vollständiger Backendlauf abgeschlossen: **1057 bestanden, 4 übersprungen, 27237 Assertions**, 194,32 Sekunden. JUnit dauerhaft unter `mobile/airmius_mobile/release_evidence/full-app-qa-backend-2026-09-07.xml`. Keine Fehler. Übersprungen: ApiTokenPermissionsTest, CreateApiTokenTest, DeleteApiTokenTest (je ein Legacy-Token-Test), RegistrationTest::test_registration_screen_cannot_be_rendered_if_support_is_disabled. Nicht als bestanden zählen.
- Live Kurse, Konto Amin Masri: Übersicht lädt, 0 Einschreibungen/0 Zertifikate, sinnvoller Leerzustand; „Kurse entdecken“ öffnet den Katalog, der aktuell keine passenden Kurse liefert. Einschreibung/Lektion/Quiz/Zertifikat dadurch noch nicht live geprüft. Hinweis „Ändere den Suchbegriff“ trotz nicht sichtbarer Suche als UX-Prüfpunkt notiert, nicht als Funktionsfehler bestätigt.
- Nächste Schritte: getrennte persistente QA-Rollen und privaten QA-Verein vorbereiten; Kursstudio mit eigenem Kursentwurf testen; Unteraktionen gegen Routen-/Testinventar abgleichen; danach Rollen-Ende-zu-Ende-Matrix abarbeiten. Gesamtziel bleibt offen.

### Fortsetzung: Kursstudio live

### Fortsetzung: QA-Konten und mobile Nutzerverwaltung

- Aktuelle Gerätesitzung erneut bestätigt. Mobile Nutzerverwaltung geöffnet: Übersicht lädt, Nutzerkarten mit Rollen, E-Mail-/2FA-Status und Sperraktion erreichbar. Keine echten Nutzer geändert.
- **Bestätigte Funktionslücke:** `PlatformAdminScreen._users` bietet keine Kontoanlage und keine Suche/Paginierung. Backend `PlatformAdminController.dashboard` liefert `latest('id')->limit(50)`. Daher ist die mobile Verwaltung älterer Konten nicht vollständig. Backend-Kontoanlage existiert unter POST `/api/v1/admin/platform/users`, wird von diesem mobilen Screen aber nicht angebunden. Nicht als bestanden markieren.
- Weitere live sichtbare Sprachmischungen: Nutzerstatus `active`, technische Rollennamen `player`/`club_owner`. Kein Status geändert.
- Separater Airmius-Webtab geöffnet (CUA tab 5, Variable qaWeb), Menü > Anmelden, URL https://airmius.com/login. Web-Anmeldung fehlt; Nutzer asynchron um Admin-Anmeldung gebeten. Keine Zugangsdaten aus dem Handy extrahiert. Noch keine Live-Testkonten angelegt. Das ist ein Teilblocker für rollengetrennte Live-Tests, kein Gesamtstillstand: Code-, lokale API- und bestehende Geräteprüfungen können weiterlaufen.
- Vorherige Runde war Fortschritt (privater Kurs und Lektion dauerhaft gespeichert); diese Runde liefert eine weitere bestätigte Verwaltungslücke und den vorbereiteten Webzugang.

- Vorherige Runde war Fortschritt: vollständiger Backendlauf und Funktionsinventar. Aktueller Gerätestand und Arbeitsbaum erneut geprüft.
- Im bestehenden Konto Amin Masri privaten kostenlosen Entwurf **QA Gesamttest 20260907** erstellt. UI-Schalter „Kurs öffentlich anzeigen“ war aus; Status nach Speichern Draft. Keine Veröffentlichung, keine Einschreibung oder Nachricht an andere Nutzer ausgelöst.
- Standardabschnitt Start vorhanden. Textlektion **QA Textlektion** mit Inhalt „Dies ist ein privater QA Testinhalt.“ angelegt, kostenlose Vorschau aus. Speicherung erfolgreich; nach manuellem Aktualisieren bleiben Kurs und 1 Lektion sichtbar.
- Bestätigte UX-Befunde in deutscher Ansicht: englischer Kursstatus „Draft“ und falscher Singular „1 Lektionen“. Noch nicht behoben. Verantwortliche UI: `learning_studio_course_suite_screen.dart`.
- Angelegte produktive QA-Daten bleiben für Folgetests erhalten und sind nicht als echte Lernangebote gedacht. Kurs-ID noch nicht aus UI ermittelt. Vollständige Rollenprüfung sowie Lernabschluss, Quiz, Aufgabe, Zertifikat, Teilnehmerverwaltung und Veröffentlichungsablauf sind weiterhin offen.

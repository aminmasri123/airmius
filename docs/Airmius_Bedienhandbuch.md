# Airmius Bedienhandbuch

Stand: 04.05.2026

Dieses Handbuch erklärt die wichtigsten Abläufe der Airmius-Plattform für alle Rollen: Sportler, Trainer, Vereine, Eltern, Sponsoren, Anbieter, Käufer und Administratoren. Es ist als praktische Einleitung gedacht, damit neue Nutzer die Plattform bedienen und Tester alle Funktionsbereiche nachvollziehen können.

## Grundprinzip

Airmius ist als Smartphone-first Plattform gedacht. Die wichtigsten Bereiche sind über die Sidebar erreichbar. Auf dem Handy öffnet sich die Navigation über das Menü-Symbol, auf Tablet und Laptop bleibt sie als feste Seitenleiste verfügbar. Aktionen werden rollen- und rechteabhängig angezeigt.

## Erste Schritte für alle Nutzer

- Registrieren oder mit Google/Microsoft anmelden.
- Profil vervollständigen, besonders Name, Geburtsdatum und Pflichtdaten.
- Bei Minderjährigen unter 16 Jahren die Eltern-E-Mail angeben und die Zustimmung abwarten.
- Über die Sidebar zu Dashboard, Feed, Teams, Vereinen, Chat, Events, Dateien, Marketplace und Einstellungen wechseln.
- Benachrichtigungen regelmäßig prüfen, weil Einladungen, Zahlungen, Moderation und Mitgliedschaftshinweise dort erscheinen.

## Rollen und Bedienung

### Öffentlicher Besucher

Besucher können Airmius kennenlernen, Preise und öffentliche Inhalte ansehen und danach entscheiden, ob sie sich registrieren möchten.

So wird dieser Bereich bedient:

- Startseite öffnen
- Plattformversprechen verstehen
- Preise ansehen
- Vereine öffentlich suchen
- Blog lesen
- Jobs ansehen
- E-Learning-Infoseite ansehen
- Gamification-Infoseite ansehen
- Top-Inhalte ansehen
- Rechtliche Seiten lesen
- Kontakt oder Meldewege finden
- Sprache wechseln
- Registrieren
- Anmelden
- Per Google anmelden
- Per Outlook/Microsoft anmelden

Typische Seiten und technische Bereiche: /, /preise, /vereine, /blog, /blog/{slug}, /jobs, /e-learning, /gamification, /top-inhalte, /impressum, /datenschutz, /agb, /community-richtlinien, /jugendschutz, /cookies, /widerruf, /kontakt-und-melden, /user/language, ...

### Registrierter Nutzer

Registrierte Nutzer erhalten Zugriff auf die persönliche Plattformoberfläche und können Profil, Suche, Benachrichtigungen und Einstellungen nutzen.

So wird dieser Bereich bedient:

- Einloggen
- Ausloggen
- Profil vervollständigen
- Eigene Einstellungen bearbeiten
- Status ändern: online, offline, training, work
- Benachrichtigungen lesen
- Alle Benachrichtigungen als gelesen markieren
- Einzelne Benachrichtigung als gelesen markieren
- Dashboard öffnen
- Globale Suche nutzen
- Eigene Abo-Rechnungen herunterladen
- Eigenes Nutzer-Abo kündigen
- Provider-Portal für eigenes Abo öffnen, wenn verfügbar
- Google/Outlook Social Login nutzen
- Sportprogramm-Verknüpfungen verwalten
- Konto kann bei Moderationsverstößen gesperrt werden

Typische Seiten und technische Bereiche: /dashboard, /profile-completion, /settings, /subscription-invoices/{subscriptionInvoice}/download, /user-subscriptions/{subscription}/cancel, /user-subscriptions/{subscription}/provider-portal, /user/status, /notifications, /notifications/read-all, /notifications/{notification}/read, /search

### Sportler

Sportler pflegen ihr Profil, verbinden Sportarten und Fähigkeiten, nutzen Feed, Teams, Events, Chat und Marketplace.

So wird dieser Bereich bedient:

- Profil mit Sportarten und Skills pflegen
- Sportart zum Profil hinzufügen
- Skill-Level aktualisieren
- Skills anderer Nutzer bestätigen
- Empfehlungen für andere Nutzer schreiben
- Erhaltene Empfehlungen annehmen oder ablehnen
- Lizenznummer pflegen
- Profil-Sichtbarkeit steuern
- Öffentliche oder eingeschränkte Profile anderer Nutzer ansehen
- Anderen Nutzern folgen
- Follow entfernen
- Freunde verwalten
- Freundschaftsanfragen versenden
- Freundschaftsanfragen annehmen
- Freundschaftsanfragen ablehnen
- Feed nutzen
- Beiträge erstellen
- Beiträge bearbeiten
- Beiträge löschen
- Beiträge liken
- Beiträge als hilfreich markieren
- Kommentare schreiben
- Kommentare bearbeiten
- Kommentare löschen
- Inhalte melden
- Teams beitreten
- Events ansehen und teilnehmen
- Fahrgemeinschaften nutzen
- Marketplace kaufen
- Zahlungs- und Bestellprobleme melden

Typische Seiten und technische Bereiche: /users/{user}, /users/{user}/follow, /profile/sports, /profile/skills/{userSportSkill}, /users/{user}/skills/{userSportSkill}/endorse, /users/{user}/recommendations, /profile/recommendations/{profileRecommendation}/approve, /profile/recommendations/{profileRecommendation}/reject, /friends, /friends/invitations, /feed, /posts, /posts/{post}/like, /posts/{post}/helpful, /posts/{post}/comments, /reports, /teams, /events, ...

### Minderjähriger Nutzer unter 16

Minderjährige unter 16 Jahren dürfen soziale Funktionen erst nach Zustimmung eines Erziehungsberechtigten nutzen.

So wird dieser Bereich bedient:

- Registrierung mit Geburtsdatum
- Eltern-E-Mail angeben
- Elternzustimmung wird angefordert
- Bis zur Entscheidung Warteseite sehen
- Bei Zustimmung soziale Funktionen nutzen
- Bei Ablehnung eingeschränkt bleiben
- Bei Widerruf wieder eingeschränkt werden
- Weiterhin Login möglich, aber nicht alle sozialen Funktionen nutzbar

Typische Seiten und technische Bereiche: /guardian-consent/pending, /register, /login

### Eltern / Erziehungsberechtigte

Eltern können Zustimmung erteilen, ablehnen oder später widerrufen und behalten dadurch Kontrolle über die Nutzung ihrer Kinder.

So wird dieser Bereich bedient:

- Elternzustimmungsseite per Token öffnen
- Zustimmung erteilen
- Zustimmung ablehnen
- Eltern-Login mit gespeicherter E-Mail starten
- Zugangscode per E-Mail erhalten
- Code eingeben
- Verknüpfte Kinder anzeigen
- Zustimmung für Kind widerrufen
- Optional Elternkonto erstellen
- Optional bestehendes Konto als Elternkonto verknüpfen
- Elternbereich verlassen

Typische Seiten und technische Bereiche: /guardian-consent/{token}, /eltern-login, /eltern-login/code, /eltern/kinder, /eltern/konto-erstellen, /eltern/kinder/{child}/widerrufen, /eltern/logout

### Trainer

Trainer organisieren Teams, Trainings, Kommunikation und Inhalte für Sportler.

So wird dieser Bereich bedient:

- Teams erstellen
- Teams bearbeiten
- Teamprofil pflegen
- Teamlogo und Titelbild pflegen
- Mitglieder einladen
- Teambeitrittsanfragen prüfen
- Teamrollen ändern
- Mitglieder entfernen
- Events oder Trainingstermine erstellen
- Event-Kommentare nutzen
- Teamchat nutzen
- Dateien teilen
- Trainingswissen als Beiträge veröffentlichen
- Skills von Sportlern bestätigen
- Empfehlungen schreiben
- Marketplace-Angebote wie Kurse, Camps oder Trainingspläne einreichen

Typische Seiten und technische Bereiche: /teams, /teams/{team}, /teams/{team}/invite, /team-join-requests/{joinRequest}/approve, /team-join-requests/{joinRequest}/decline, /events, /conversations, /files, /commerce/products

### Verein

Vereine verwalten Organisation, Teams, Mitglieder, Beiträge, Rechnungen, Zahlungen, Dateien und digitale Zusatzdienste.

So wird dieser Bereich bedient:

- Verein erstellen
- Verein anzeigen
- Verein bearbeiten
- Vereinsbilder pflegen
- Verein löschen
- Vereinsnummer hinterlegen
- Offiziellen Vereinsstatus pflegen
- Vereinsmitglieder verwalten
- Vereinsrollen verwalten
- Personen als Mitglied, Nichtmitglied, in Prüfung oder ehemaliges Mitglied markieren
- Mitgliedsnummer manuell eintragen
- Mitgliedsnummer automatisch generieren
- Lizenznummer der Mitglieder pflegen
- Mitgliedsbeitrag pflegen
- Beitragsintervall pflegen
- Nächstes Rechnungsdatum pflegen
- Eintrittsdatum pflegen
- Mitgliedschaftsende pflegen
- Notizen zur Mitgliedschaft pflegen
- Externe Mitglieder per E-Mail anlegen
- Externe Mitglieder per Excel/CSV importieren
- Airmius-Importvorlage herunterladen
- Externe Mitglieder später einladen
- Externe Einladung per Token annehmen lassen
- SEPA-Einstellungen pflegen
- SEPA-Lastschrift-XML exportieren
- Banktransaktionen importieren
- Banktransaktionen manuell bestätigen
- DATEV/SKR42-Einstellungen pflegen
- DATEV/SKR42-CSV exportieren
- Rechnungen für Mitglieder erstellen
- Rechnungsstatus ändern
- Zahlungen erfassen
- Mahnungen senden
- Wiederkehrende Beitragsrechnungen durch Scheduler erzeugen lassen
- Bald endende Mitgliedschaften überwachen
- Offene oder bald fällige Beitragszahlungen überwachen
- Teams im Verein verwalten
- Teambeitrittsanfragen als Verein bearbeiten
- Dateien und Ordner nutzen
- Jobs veröffentlichen
- Sponsorendaten verwalten
- Add-ons buchen
- Vereinswebsite-Service anfragen
- Vereins-Abo verwalten

Typische Seiten und technische Bereiche: /clubs, /clubs/{club}, /clubs/{club}/members/{user}, /clubs/{club}/images, /clubs/{club}/jobs, /organization-jobs/{organizationJob}, /club-memberships, /club-memberships/import-template, /clubs/{club}/membership/email-members, /clubs/{club}/membership/email-members/import, /clubs/{club}/membership/sepa-settings, /clubs/{club}/membership/sepa-export, /club-external-members/{externalMember}/invite, /club-member-invitations/token/{token}/accept, /clubs/{club}/membership/{user}, /clubs/{club}/membership/{user}/member-number, /clubs/{club}/membership/{user}/invoices, /membership-invoices/{invoice}, ...

### Team

Teams bündeln Mitglieder, Kommunikation, Termine und Rollen innerhalb eines Vereins oder einer Trainingsgruppe.

So wird dieser Bereich bedient:

- Team erstellen
- Team anzeigen
- Team bearbeiten
- Teamlogo und Titelbild ändern
- Team löschen
- Nutzer einladen
- Einladung annehmen
- Externe Einladung per Token annehmen
- Beitrittsanfrage stellen
- Beitrittsanfrage annehmen
- Beitrittsanfrage ablehnen
- Teammitglied Rolle ändern
- Teammitglied entfernen
- Teamchat nutzen
- Team-Events nutzen
- Team-Dateien nutzen

Typische Seiten und technische Bereiche: /teams, /teams/{team}, /teams/{team}/images, /teams/{team}/invite, /teams/{team}/join-requests, /team-invitations/{invitation}/accept, /team-invitations/token/{token}/accept, /team-join-requests/{joinRequest}/approve, /team-join-requests/{joinRequest}/decline, /teams/{team}/members/{user}

### Sponsor

Sponsoren und Werbepartner können Kampagnen vorbereiten und über Admin-Freigaben ausspielen lassen.

So wird dieser Bereich bedient:

- Sponsorprofil im Adminbereich verwalten lassen
- Eigene Kampagne vorbereiten
- Ziel-URL hinterlegen
- Budget hinterlegen
- Beschreibung hinterlegen
- Kampagne durch Admin prüfen lassen
- Kampagne aktivieren lassen
- Impressionen erfassen lassen
- Klicks erfassen lassen
- CTR sehen
- Budgetverbrauch sehen
- Kampagne pausieren oder abschließen lassen
- Externe Zielseite verlinken

Typische Seiten und technische Bereiche: /commerce/campaigns, /ads/active, /ads/{campaign}/click, /admin/sponsors, /admin/commerce

### Marketplace-Anbieter

Anbieter verkaufen Kurse, Camps, Produkte oder Dienstleistungen über den Marketplace und erhalten Auszahlungen nach Prüfung.

So wird dieser Bereich bedient:

- Produkt, Kurs, Camp oder Dienstleistung einreichen
- Angebot automatisch moderieren lassen
- Bei Risiko Adminprüfung auslösen
- Bei schwerem Risiko automatisch abgelehnt werden
- Ablehnungsgrund sehen
- Freigegebenes Angebot öffentlich im Marketplace anzeigen
- Produktdetailseite bereitstellen
- Verkäufe erhalten
- Auszahlungsdaten hinterlegen
- Auszahlungsstatus prüfen
- Offene auszahlbare Beträge sehen
- Airmius-Provision akzeptieren
- Auszahlung durch Admin vorbereiten lassen
- Auszahlung als bezahlt markieren lassen

Typische Seiten und technische Bereiche: /commerce, /commerce/products, /commerce/products/{product}, /commerce/payout-profile, /admin/commerce

### Käufer im Marketplace

Käufer können Produkte ansehen, bezahlen und bei Problemen Unterstützung anfordern.

So wird dieser Bereich bedient:

- Marketplace-Produkte ansehen
- Produktdetailseite öffnen
- Anbieterinformationen sehen
- Preis sehen
- Zahlungsart wählen: Stripe, PayPal oder Überweisung
- AGB und Widerrufshinweise bestätigen
- Bestellung starten
- Rückkehr vom Zahlungsanbieter verarbeiten
- Überweisungsdaten sehen
- Bestellung abgeschlossen sehen
- Problem mit abgeschlossener Bestellung melden
- Admin kann Problem prüfen, lösen oder erstatten

Typische Seiten und technische Bereiche: /commerce, /commerce/products/{product}, /checkout/commerce/{order}/success, /checkout/commerce/{order}/cancel, /checkout/commerce/{order}/bank-transfer, /commerce/orders/{order}/issue

### Airmius System Admin

System-Admins steuern Plattform, Nutzer, Rollen, Zahlungen, Inhalte, Abos, Moderation und Einstellungen.

So wird dieser Bereich bedient:

- Nutzer verwalten
- Mitglieder verwalten
- Nutzer erstellen
- Nutzer bearbeiten
- Nutzer löschen
- Rollen und Berechtigungen verwalten
- Rollen erstellen
- Rollen bearbeiten
- Rollen löschen
- Permissions erstellen
- Sportarten verwalten
- Gamification-Regeln verwalten
- Moderation verwalten
- Blog verwalten
- Zahlungen verwalten
- Rechnungen verwalten
- Sponsoren verwalten
- Systemeinstellungen verwalten
- Wartungsmodus aktivieren/deaktivieren
- Abo-Pläne verwalten
- Vereins-Abos zuordnen
- Nutzer-Abos zuordnen
- Abos kündigen
- Abos verlängern
- Abo-Rechnungen herunterladen
- Banküberweisungen als bezahlt markieren
- Commerce verwalten
- Coupons verwalten
- Add-ons verwalten
- Marketplace-Produkte verwalten
- Ads-Kampagnen verwalten
- Commerce-Bestellungen verwalten
- Website-Anfragen verwalten
- Auszahlungsprofile prüfen
- Auszahlungen vorbereiten
- Auszahlungen als bezahlt markieren
- Bestellprobleme bearbeiten
- Automatische Moderationsflags bearbeiten
- Nutzermeldungen bearbeiten
- Inhalte entfernen
- Verwarnungen und gesperrte Konten überwachen

Typische Seiten und technische Bereiche: /admin/users, /admin/members, /admin/roles-permissions, /admin/gamification, /admin/sports, /admin/moderation, /admin/blogs, /admin/payments, /admin/subscriptions, /admin/subscription-invoices, /admin/commerce, /admin/invoices, /admin/sponsors, /admin/settings

## Funktionsbereiche

### Registrierung, Login und Konto

Dieser Bereich deckt Kontoanlage, Login, Social Login, Pflichtdaten, Minderjährigenschutz und Kontosicherheit ab.

So wird dieser Bereich bedient:

- Registrierung mit vollständigen Basisdaten
- Login per E-Mail/Passwort
- Login per Google
- Login per Microsoft/Outlook
- Social-Account wird verknüpft
- Fehlende Pflichtdaten nach Social Login über Profilvervollständigung nachtragen
- Minderjährigenstatus prüfen
- Elternzustimmung auslösen
- Wartungsmodus beachten
- Gesperrte Konten blockieren
- Sprache wechseln
- Passwort zurücksetzen
- Zwei-Faktor-Funktionen von Jetstream/Fortify nutzen, soweit aktiviert

### Profil und Sportprofil

Das Profil ist die digitale Identität innerhalb von Airmius und verbindet persönliche Daten, Sportarten, Skills und Sichtbarkeit.

So wird dieser Bereich bedient:

- Persönliches Profil anzeigen
- Profilinformationen bearbeiten
- Profilfoto verwalten
- Sportarten hinzufügen
- Skill-Level aktualisieren
- Skills bestätigen
- Empfehlungen schreiben
- Empfehlungen annehmen
- Empfehlungen ablehnen
- XP und Gamification-Daten sammeln
- Profil-Sichtbarkeit prüfen
- Lizenznummer pflegen

### Feed, Posts, Kommentare, Likes und Helpful

Der Feed ist der soziale Bereich für Beiträge, Kommentare, Likes, hilfreiche Inhalte und Meldungen.

So wird dieser Bereich bedient:

- Feed nach Sichtbarkeit laden
- Öffentliche Beiträge sehen
- Vereinsbeiträge sehen, wenn berechtigt
- Teambeiträge sehen, wenn berechtigt
- Eigene Beiträge sehen
- Beitrag mit Text, Bild und Anhängen erstellen
- Beitrag bearbeiten
- Beitrag löschen
- Beitrag liken
- Helpful markieren
- Kommentar erstellen
- Kommentar bearbeiten
- Kommentar löschen
- Automatische Moderation bei Erstellung und Bearbeitung
- Entfernte Inhalte ausblenden
- Inhalte melden

### Chat und Conversations

Der Chat dient der direkten Kommunikation. Konversationen werden bewusst geöffnet, um Privatsphäre zu schützen.

So wird dieser Bereich bedient:

- Chatübersicht öffnen
- Keine Konversation automatisch öffnen, Nutzer wählt aktiv
- Einzel- oder Gruppenkonversation erstellen
- Team-Hauptchats nutzen
- Nachrichten senden
- Anhänge senden
- Nachricht als gelesen markieren
- Nachricht löschen
- Reaktion setzen
- Typing-Status senden
- Gruppe verlassen
- Bei letzter Person Gruppe löschen oder Löschung bestätigen
- Neue Mitglieder sehen nur Nachrichten ab Beitritt, wenn so umgesetzt
- Automatische Moderation bei Nachrichten
- Entfernte Nachrichten ausblenden oder löschen

### Freunde und Follows

Freunde und Follows steuern soziale Verbindungen und Sichtbarkeit zwischen Nutzern.

So wird dieser Bereich bedient:

- Freundesübersicht öffnen
- Freundschaftsanfrage senden
- Freundschaftsanfrage annehmen
- Freundschaftsanfrage ablehnen
- Nutzer folgen
- Follow entfernen
- Sichtbarkeit je nach Follow/Profilstatus prüfen

### Teams

Teams bündeln Mitglieder, Kommunikation, Termine und Rollen innerhalb eines Vereins oder einer Trainingsgruppe.

So wird dieser Bereich bedient:

- Teams listen
- Teamprofil öffnen
- Team erstellen
- Team bearbeiten
- Bilder ändern
- Team löschen
- Einladen
- Einladung annehmen
- Token-Einladung annehmen
- Beitritt anfragen
- Beitrittsanfrage annehmen
- Beitrittsanfrage ablehnen
- Mitgliederrolle ändern
- Mitglied entfernen
- Teamlimit nach Vereinsplan prüfen
- Teamchat verwenden

### Vereine

Vereine verwalten Organisation, Teams, Mitglieder, Beiträge, Rechnungen, Zahlungen, Dateien und digitale Zusatzdienste.

So wird dieser Bereich bedient:

- Vereine listen
- Verein erstellen
- Vereinsprofil öffnen
- Verein bearbeiten
- Vereinsbilder ändern
- Verein löschen
- Vereinsrolle ändern
- Vereinssichtbarkeit prüfen
- Offizielle Vereinsnummer pflegen
- Vereinsjobs erstellen
- Vereinsjobs bearbeiten
- Vereinsjobs löschen
- Öffentliche Vereinsseiten anzeigen

### Mitgliederverwaltung

Die Mitgliederverwaltung unterstützt Vereine bei Stammdaten, Mitgliedsnummern, Beiträgen, Import, Rechnungen und Zahlungen.

So wird dieser Bereich bedient:

- Verwaltungsseite öffnen
- Mitglieder und externe Mitglieder anzeigen
- Teambeitrittsanfragen sehen
- Mitgliedsdaten bearbeiten
- Mitgliedsstatus setzen
- Mitgliedsnummer manuell setzen
- Mitgliedsnummer generieren
- Lizenznummer setzen
- Beitrag und Intervall setzen
- Nächstes Rechnungsdatum setzen
- Eintrittsdatum setzen
- Mitgliedschaftsende setzen
- Notizen setzen
- Externe Mitglieder einzeln per E-Mail erfassen
- Bei Erfassung entscheiden, ob Einladung versendet wird
- Bestehenden Nutzer anhand E-Mail verknüpfen
- Ohne Einladung nur externes Mitglied erfassen
- Später nachträglich einladen
- Excel/CSV importieren
- Importvorlage herunterladen
- Rechnungen erstellen
- Rechnungsstatus aktualisieren
- Zahlung erfassen
- Mahnung senden
- SEPA-Daten pflegen
- SEPA-Export erstellen
- Banktransaktionen importieren
- Banktransaktionen matchen
- DATEV-Einstellungen pflegen
- DATEV/SKR42 exportieren

### Events und Aktivitäten

Dieser Bereich gehört zum Gesamtprozess der Plattform und sollte entsprechend der jeweiligen Rolle und Berechtigung getestet werden.

So wird dieser Bereich bedient:

- Events listen
- Eventdetails anzeigen
- Event erstellen
- Event bearbeiten
- Event löschen
- An Event teilnehmen
- Event verlassen
- Event kommentieren
- Eventchat öffnen
- Aktivitäten als Kontext für Feed/Training nutzen
- Trainingstermine als Systemhinweis im Team-Hauptchat posten, soweit vorhanden/geplant

### Fahrgemeinschaften / Rides

Dieser Bereich gehört zum Gesamtprozess der Plattform und sollte entsprechend der jeweiligen Rolle und Berechtigung getestet werden.

So wird dieser Bereich bedient:

- Fahrgemeinschaften listen
- Fahrt anbieten
- Fahrt beitreten
- Fahrt verlassen
- Fahrt löschen
- Fahrer und Teilnehmer verwalten

### Dateien und Ordner

Dieser Bereich gehört zum Gesamtprozess der Plattform und sollte entsprechend der jeweiligen Rolle und Berechtigung getestet werden.

So wird dieser Bereich bedient:

- Dateiübersicht öffnen
- Datei hochladen
- Speicherlimit nach Vereinsplan prüfen
- Datei herunterladen
- Datei teilen
- Datei löschen
- Ordner erstellen
- Ordner teilen
- Ordner löschen
- Dateien in Vereinen, Teams oder Events verwenden

### Benachrichtigungen

Dieser Bereich gehört zum Gesamtprozess der Plattform und sollte entsprechend der jeweiligen Rolle und Berechtigung getestet werden.

So wird dieser Bereich bedient:

- Benachrichtigungen anzeigen
- Einzelne Benachrichtigung als gelesen markieren
- Alle Benachrichtigungen als gelesen markieren
- Chatnachrichten als Benachrichtigung senden
- Mitgliedschaftsende melden
- Beitragszahlung melden
- Abo-Ende melden
- Zahlung offen melden
- Rechnungs- und Zahlungsemails senden

### Gamification

Dieser Bereich gehört zum Gesamtprozess der Plattform und sollte entsprechend der jeweiligen Rolle und Berechtigung getestet werden.

So wird dieser Bereich bedient:

- XP für passende Aktionen vergeben
- Level und Badges nutzen
- Skill- und Profilaktivitäten berücksichtigen
- Admin pflegt Gamification-Regeln
- Öffentliche Gamification-Seite erklären

### Sportartenverwaltung

Dieser Bereich gehört zum Gesamtprozess der Plattform und sollte entsprechend der jeweiligen Rolle und Berechtigung getestet werden.

So wird dieser Bereich bedient:

- Sportarten listen
- Sportart erstellen
- Sportart bearbeiten
- Sportart löschen
- Anzahl Gruppen je Sportart anzeigen
- Sportarten in Profilen, Teams, Clubs oder Feed verwenden

### Sport-App-Integrationen

Dieser Bereich gehört zum Gesamtprozess der Plattform und sollte entsprechend der jeweiligen Rolle und Berechtigung getestet werden.

So wird dieser Bereich bedient:

- Google Fit verbinden
- Garmin vorbereiten/anfragen
- Mi Fitness vorbereiten/anfragen
- Provider-OAuth starten
- Callback speichern
- Synchronisationsstatus aktualisieren
- Verbindung löschen
- Importierte Aktivitätsdaten speichern, soweit Provider angebunden ist

### Abo-Pläne und Feature-Gates

Abo-Pläne steuern Preise, Limits und freigeschaltete Funktionen für Nutzer, Vereine und Partner.

So wird dieser Bereich bedient:

- Öffentliche Preisseite anzeigen
- Abo-Pläne nach Zielgruppe anzeigen
- Free, Starter, Club, Pro, Elite, Enterprise verwalten
- Nutzer-Abo zuordnen
- Vereins-Abo zuordnen
- Planpreise bearbeiten
- Limits bearbeiten
- Badge und CTA bearbeiten
- Plan öffentlich/nicht öffentlich setzen
- Feature-Gates prüfen:
- Mitgliederlimit
- Teamlimit
- Speicherlimit
- Import
- externe Mitglieder
- Rechnungen
- Mahnungen
- SEPA
- Bankabgleich
- DATEV
- wiederkehrende Rechnungen
- API
- Abo kündigen
- Abo zum Periodenende kündigen
- Abo verlängern
- Trial überwachen
- Past-due setzen

### Airmius-Zahlungen und Abo-Rechnungen

Abo-Pläne steuern Preise, Limits und freigeschaltete Funktionen für Nutzer, Vereine und Partner.

So wird dieser Bereich bedient:

- Subscription-Checkout starten
- Stripe-Checkout starten
- PayPal-Checkout starten
- Überweisung/Rechnung wählen
- Zahlungsreferenz erzeugen
- Zahlungsziel setzen
- Rückkehr von Stripe/PayPal verarbeiten
- Stripe Webhook verarbeiten
- PayPal Webhook verarbeiten
- Überweisung manuell als bezahlt markieren
- Abo aktivieren
- Abo-Rechnung erzeugen
- PDF-Rechnung herunterladen
- Zahlungsbestätigung senden
- Zahlungserinnerung senden
- Trial-Ablaufwarnung senden
- Kündigungsbestätigung senden
- Verlängerungsbestätigung senden

### Commerce, Add-ons und Marketplace

Commerce bündelt Add-ons, Marketplace, Zahlungsarten, Bestellungen, Probleme und Auszahlungen.

So wird dieser Bereich bedient:

- Commerce-Seite öffnen
- Add-ons anzeigen
- Add-on für Verein buchen
- Add-on privat buchen, falls erlaubt
- Add-on monatlich oder jährlich buchen
- Add-on per Stripe, PayPal oder Überweisung bezahlen
- Marketplace-Produkte anzeigen
- Produktdetails anzeigen
- Produkt kaufen
- AGB/Widerruf bestätigen
- Bestellung stornieren
- Bestellung als abgeschlossen aktivieren
- Commerce-Webhooks verarbeiten
- Commerce-Bestellbestätigung senden
- Problem melden
- Admin bearbeitet Problemfall
- Admin markiert erstattet
- Admin markiert gelöst

### Marketplace-Anbieter und Auszahlungen

Anbieter verkaufen Kurse, Camps, Produkte oder Dienstleistungen über den Marketplace und erhalten Auszahlungen nach Prüfung.

So wird dieser Bereich bedient:

- Anbieter reicht Produkt ein
- Automatische Vorprüfung
- Adminprüfung
- Freigabe
- Ablehnung mit Grund
- Archivierung
- Anbieter hinterlegt Auszahlungsprofil
- Admin prüft Auszahlungsprofil
- Admin gibt Auszahlungsprofil frei
- Admin sperrt Auszahlungsprofil
- Offene Verkaufsbeträge gruppieren
- Provision berechnen
- Auszahlung vorbereiten
- Auszahlung als bezahlt markieren
- Bestellungen mit Auszahlung verknüpfen

### Ads und Sponsoring

Sponsoren und Werbepartner können Kampagnen vorbereiten und über Admin-Freigaben ausspielen lassen.

So wird dieser Bereich bedient:

- Admin erstellt Kampagne
- Sponsor erstellt eigene Kampagne
- Kampagne bearbeiten
- Status setzen: draft, active, paused, completed
- Budget setzen
- Ziel-URL setzen
- Aktive Ad ausspielen
- Impression zählen
- Klick zählen
- Klick zur Ziel-URL weiterleiten
- CTR berechnen
- Budgetverbrauch anzeigen
- Admin-Reporting anzeigen
- Sponsor-Reporting anzeigen
- Sponsoren im Adminbereich verwalten

### Website-Service

Dieser Bereich gehört zum Gesamtprozess der Plattform und sollte entsprechend der jeweiligen Rolle und Berechtigung getestet werden.

So wird dieser Bereich bedient:

- Verein fragt Website an
- Domainwunsch erfassen
- Ziele erfassen
- Notizen erfassen
- Admin sieht Anfrage
- Status setzen:
- new
- contacted
- quoted
- in_progress
- done
- cancelled
- Website-Service ist rechtlich nur Anfrage, bis ein Angebot angenommen wird

### Blog und CMS

Dieser Bereich gehört zum Gesamtprozess der Plattform und sollte entsprechend der jeweiligen Rolle und Berechtigung getestet werden.

So wird dieser Bereich bedient:

- Öffentliche Blogliste anzeigen
- Öffentlichen Blogartikel anzeigen
- Admin-Blogliste öffnen
- Blogbeitrag erstellen
- Blogbeitrag bearbeiten
- Blogbeitrag löschen
- Status setzen:
- draft
- review
- published
- archived
- Blogartikel in Sitemap aufnehmen, wenn veröffentlicht

### Jobs

Dieser Bereich gehört zum Gesamtprozess der Plattform und sollte entsprechend der jeweiligen Rolle und Berechtigung getestet werden.

So wird dieser Bereich bedient:

- Öffentliche Jobs anzeigen
- Verein erstellt Job
- Verein bearbeitet Job
- Verein löscht Job
- Jobs in Sitemap/öffentlichen Seiten als Wachstumsmodul nutzen

### Rechtliche Seiten

Dieser Bereich gehört zum Gesamtprozess der Plattform und sollte entsprechend der jeweiligen Rolle und Berechtigung getestet werden.

So wird dieser Bereich bedient:

- Impressum anzeigen
- Datenschutz anzeigen
- AGB anzeigen
- Community-Richtlinien anzeigen
- Jugendschutz anzeigen
- Cookie-Hinweise anzeigen
- Widerruf anzeigen
- Kontakt und Melden anzeigen
- Rechtstexte berücksichtigen:
- Registrierung
- Elternzustimmung
- Vereinsverwaltung
- Zahlungen
- Marketplace
- Ads
- Website-Service
- Moderation

### Moderation, Meldungen und Sperren

Moderation schützt Nutzer durch automatische Prüfung, Meldungen, Verwarnungen und Sperren.

So wird dieser Bereich bedient:

- Automatische Textanalyse durchführen
- Kategorien erkennen:
- insult
- threat
- hate
- sexual
- spam
- Severity setzen:
- low
- medium
- high
- Medium-Risiko als Flag offen markieren
- High-Risiko Inhalt zurückhalten oder entfernen
- ModerationFlag erstellen
- AccountWarning erstellen
- Verwarnpunkte zählen
- Sperre setzen bei 2 High-Verstößen in 90 Tagen
- Sperre setzen bei 5 Warnpunkten in 90 Tagen
- Gesperrte Nutzer auf Sperrseite leiten
- Admin sieht offene Flags
- Admin sieht Nutzermeldungen
- Admin entfernt Inhalte
- Admin schließt Fälle als unkritisch
- Admin markiert Fälle als bearbeitet

### Admin: Nutzer, Rollen und Berechtigungen

Dieser Bereich gehört zum Gesamtprozess der Plattform und sollte entsprechend der jeweiligen Rolle und Berechtigung getestet werden.

So wird dieser Bereich bedient:

- Nutzerliste öffnen
- Nutzer erstellen
- Nutzer bearbeiten
- Nutzer löschen
- Mitgliederliste öffnen
- Rollen-/Permission-Seite öffnen
- Rolle erstellen
- Rolle bearbeiten
- Rolle löschen
- Permission erstellen
- Rollen Nutzern zuweisen, soweit im MemberController/RolePermissionController umgesetzt

### Admin: Rechnungen, Payments, Sponsors

Sponsoren und Werbepartner können Kampagnen vorbereiten und über Admin-Freigaben ausspielen lassen.

So wird dieser Bereich bedient:

- Admin-Rechnungen anzeigen
- Admin-Rechnung erstellen
- Admin-Rechnung löschen
- Payments anzeigen
- Payment erstellen
- Payment löschen
- Sponsoren anzeigen
- Sponsor erstellen
- Sponsor bearbeiten
- Sponsor löschen

### Admin: Einstellungen und Wartung

Dieser Bereich gehört zum Gesamtprozess der Plattform und sollte entsprechend der jeweiligen Rolle und Berechtigung getestet werden.

So wird dieser Bereich bedient:

- Systemeinstellungen anzeigen
- Wartungsmodus aktivieren
- Wartungsmodus deaktivieren
- Wartungsmeldung bearbeiten
- Bankdaten für Überweisung pflegen
- Rechtliche oder technische Einstellungen pflegen, soweit im SettingController vorhanden
- Normale Nutzer im Wartungsmodus blockieren
- Berechtigte Admins weiterarbeiten lassen

### SEO und öffentliche Technik

Dieser Bereich gehört zum Gesamtprozess der Plattform und sollte entsprechend der jeweiligen Rolle und Berechtigung getestet werden.

So wird dieser Bereich bedient:

- `robots.txt` ausliefern
- Private Bereiche für Robots sperren:
- `/admin`
- `/dashboard`
- `/settings`
- `/conversations`
- `/messages`
- `sitemap.xml` ausliefern
- Statische öffentliche Seiten in Sitemap aufnehmen
- Veröffentlichte Blogposts in Sitemap aufnehmen
- SeoHead auf öffentlichen/rechtlichen Seiten nutzen

### Webhooks und externe Provider

Dieser Bereich gehört zum Gesamtprozess der Plattform und sollte entsprechend der jeweiligen Rolle und Berechtigung getestet werden.

So wird dieser Bereich bedient:

- Stripe Subscription Webhook empfangen
- PayPal Subscription Webhook empfangen
- Stripe Commerce Webhook empfangen
- PayPal Commerce Webhook empfangen
- Webhook-Signaturen prüfen, wenn Secret konfiguriert ist
- Checkout-Session oder PayPal Order synchronisieren
- Bestellung oder Abo aktivieren
- Doppelte Aktivierung vermeiden

### Scheduler und Cron

Scheduler und Cron führen wiederkehrende Aufgaben aus, etwa Rechnungen, Erinnerungen und Statusprüfungen.

So wird dieser Bereich bedient:

- Laravel Scheduler per Cron starten
- Wiederkehrende Beitragsrechnungen erstellen
- Membership- und Billing-Erinnerungen senden
- Abo-Ende und Trial-Ende prüfen
- Offene Abo-Rechnungen erinnern
- Überfällige Rechnungen markieren

### Broadcast und Echtzeit

Dieser Bereich gehört zum Gesamtprozess der Plattform und sollte entsprechend der jeweiligen Rolle und Berechtigung getestet werden.

So wird dieser Bereich bedient:

- Userstatus über Channel `users.status` broadcasten
- Nachrichten senden
- Nachrichten löschen broadcasten
- Reaktionen auf Nachrichten broadcasten
- Typing-Status senden
- Chat-Status in UI aktualisieren

## Typische Abläufe

### Neuer Sportler ohne Elternpflicht

Sportler pflegen ihr Profil, verbinden Sportarten und Fähigkeiten, nutzen Feed, Teams, Events, Chat und Marketplace.

### Neuer Sportler unter 16

Sportler pflegen ihr Profil, verbinden Sportarten und Fähigkeiten, nutzen Feed, Teams, Events, Chat und Marketplace.

### Verein startet digital

Vereine verwalten Organisation, Teams, Mitglieder, Beiträge, Rechnungen, Zahlungen, Dateien und digitale Zusatzdienste.

### Teambeitritt mit Vereinsbezug

Vereine verwalten Organisation, Teams, Mitglieder, Beiträge, Rechnungen, Zahlungen, Dateien und digitale Zusatzdienste.

### Marketplace-Angebot bis Auszahlung

Dieser Bereich gehört zum Gesamtprozess der Plattform und sollte entsprechend der jeweiligen Rolle und Berechtigung getestet werden.

### Ads-Kampagne

Dieser Bereich gehört zum Gesamtprozess der Plattform und sollte entsprechend der jeweiligen Rolle und Berechtigung getestet werden.

### Moderationsverstoß

Moderation schützt Nutzer durch automatische Prüfung, Meldungen, Verwarnungen und Sperren.

### Airmius-Abo per Überweisung

Abo-Pläne steuern Preise, Limits und freigeschaltete Funktionen für Nutzer, Vereine und Partner.

### Airmius-Abo per Stripe/PayPal

Abo-Pläne steuern Preise, Limits und freigeschaltete Funktionen für Nutzer, Vereine und Partner.

### Vereinsbeitrag mit Bankabgleich

Vereine verwalten Organisation, Teams, Mitglieder, Beiträge, Rechnungen, Zahlungen, Dateien und digitale Zusatzdienste.

## Wichtige Systemregeln

### Moderationsregeln

Moderation schützt Nutzer durch automatische Prüfung, Meldungen, Verwarnungen und Sperren.

### Datenschutz- und Jugendschutzregeln

Dieser Bereich gehört zum Gesamtprozess der Plattform und sollte entsprechend der jeweiligen Rolle und Berechtigung getestet werden.

### Plan- und Limitregeln

Dieser Bereich gehört zum Gesamtprozess der Plattform und sollte entsprechend der jeweiligen Rolle und Berechtigung getestet werden.

### Zahlungsregeln

Dieser Bereich gehört zum Gesamtprozess der Plattform und sollte entsprechend der jeweiligen Rolle und Berechtigung getestet werden.

### Marketplace-Regeln

Dieser Bereich gehört zum Gesamtprozess der Plattform und sollte entsprechend der jeweiligen Rolle und Berechtigung getestet werden.

### Website-Service-Regeln

Dieser Bereich gehört zum Gesamtprozess der Plattform und sollte entsprechend der jeweiligen Rolle und Berechtigung getestet werden.

## Prüfung und Qualitätssicherung

Für die Beta-Phase sollte jede Funktion mindestens auf Smartphone, Tablet und Laptop geprüft werden. Besonders wichtig sind Registrierung, Login, Profilvervollständigung, Elternzustimmung, Chat, Feed, Teams, Vereine, Mitgliederverwaltung, Zahlungen, Marketplace, Adminbereiche und Wartungsmodus.

- Status in der Testmatrix markieren: Offen, Bestanden, Fehler, Nachtest.
- Fehler immer mit Rolle, Gerät, Browser, Route und Screenshot dokumentieren.
- Zahlungs-, Eltern- und Moderationsabläufe besonders sorgfältig testen.
- Bei neuen Funktionen dieses Handbuch und die Use-Case-Dokumentation aktualisieren.

# Airmius Vollständige Use-Case-Dokumentation

Stand: 2026-05-03

Diese Datei beschreibt alle aktuell erkennbaren Use Cases der Plattform aus Sicht von Nutzern, Vereinen, Admins, Gästen und Systemprozessen. Grundlage sind die vorhandenen Routen, Controller, Adminbereiche, Commerce-Funktionen, rechtlichen Seiten und geplanten Beta-Flows.

Hinweis: "Alle" bedeutet hier alle im aktuellen Code erkennbaren Produkt- und Systemfälle. Wenn später neue Controller, Routen oder Tabellen entstehen, muss diese Datei ergänzt werden.

## 1. Rollen und Akteure

### 1.1 Öffentlicher Besucher

Use Cases:

- Startseite öffnen.
- Plattformversprechen verstehen.
- Preise ansehen.
- Vereine öffentlich suchen.
- Blog lesen.
- Jobs ansehen.
- Marketplace ansehen.
- Werbeagentur für Vereine ansehen.
- E-Learning-Infoseite ansehen.
- Gamification-Infoseite ansehen.
- Top-Inhalte ansehen.
- Rechtliche Seiten lesen.
- Kontakt oder Meldewege finden.
- Sprache wechseln.
- Registrieren.
- Anmelden.
- Per Google anmelden.
- Per Outlook/Microsoft anmelden.

Relevante Routen:

- `/`
- `/preise`
- `/vereine`
- `/blog`
- `/blog/{slug}`
- `/jobs`
- `/marketplace`
- `/werbeagentur-für-vereine`
- `/e-learning`
- `/gamification`
- `/top-inhalte`
- `/impressum`
- `/datenschutz`
- `/agb`
- `/community-richtlinien`
- `/jugendschutz`
- `/cookies`
- `/widerruf`
- `/kontakt-und-melden`
- `/user/language`
- `/auth/google/redirect`
- `/auth/microsoft/redirect`

### 1.2 Registrierter Nutzer

Use Cases:

- Einloggen.
- Ausloggen.
- Profil vervollständigen.
- Eigene Einstellungen bearbeiten.
- Status ändern: online, offline, training, work.
- Benachrichtigungen lesen.
- Alle Benachrichtigungen als gelesen markieren.
- Einzelne Benachrichtigung als gelesen markieren.
- Dashboard öffnen.
- Globale Suche nutzen.
- Eigene Abo-Rechnungen herunterladen.
- Eigenes Nutzer-Abo kündigen.
- Provider-Portal für eigenes Abo öffnen, wenn verfügbar.
- Google/Outlook Social Login nutzen.
- Sportprogramm-Verknüpfungen verwalten.
- Konto kann bei Moderationsverstößen gesperrt werden.
- Bei längerer Inaktivität Warnungen per E-Mail erhalten.
- Durch erneute Anmeldung die geplante Inaktivitäts-Anonymisierung stoppen.
- Konto kann nach sehr langer Inaktivität anonymisiert werden.

Relevante Routen:

- `/dashboard`
- `/profile-completion`
- `/settings`
- `/subscription-invoices/{subscriptionInvoice}/download`
- `/user-subscriptions/{subscription}/cancel`
- `/user-subscriptions/{subscription}/provider-portal`
- `/user/status`
- `/notifications`
- `/notifications/read-all`
- `/notifications/{notification}/read`
- `/search`

### 1.3 Sportler

Use Cases:

- Profil mit Sportarten und Skills pflegen.
- Sportart zum Profil hinzufügen.
- Skill-Level aktualisieren.
- Skills anderer Nutzer bestätigen.
- Empfehlungen für andere Nutzer schreiben.
- Erhaltene Empfehlungen annehmen oder ablehnen.
- Lizenznummer pflegen.
- Profil-Sichtbarkeit steuern.
- Öffentliche oder eingeschränkte Profile anderer Nutzer ansehen.
- Anderen Nutzern folgen.
- Follow entfernen.
- Freunde verwalten.
- Freundschaftsanfragen versenden.
- Freundschaftsanfragen annehmen.
- Freundschaftsanfragen ablehnen.
- Feed nutzen.
- Beiträge erstellen.
- Beiträge bearbeiten.
- Beiträge löschen.
- Beiträge liken.
- Beiträge als hilfreich markieren.
- Kommentare schreiben.
- Kommentare bearbeiten.
- Kommentare löschen.
- Inhalte melden.
- Teams beitreten.
- Events ansehen und teilnehmen.
- Fahrgemeinschaften nutzen.
- Marketplace als Gast ansehen.
- Marketplace kaufen.
- Zahlungs- und Bestellprobleme melden.
- Sportkleidung-Abo mit Style-Profil nutzen.
- Monatliche Outfit-Lieferungen verfolgen.

Relevante Routen:

- `/users/{user}`
- `/users/{user}/follow`
- `/profile/sports`
- `/profile/skills/{userSportSkill}`
- `/users/{user}/skills/{userSportSkill}/endorse`
- `/users/{user}/recommendations`
- `/profile/recommendations/{profileRecommendation}/approve`
- `/profile/recommendations/{profileRecommendation}/reject`
- `/friends`
- `/friends/invitations`
- `/feed`
- `/posts`
- `/posts/{post}/like`
- `/posts/{post}/helpful`
- `/posts/{post}/comments`
- `/reports`
- `/teams`
- `/events`
- `/rides`
- `/commerce`
- `/outfit-subscriptions`

### 1.4 Minderjähriger Nutzer unter 16

Use Cases:

- Registrierung mit Geburtsdatum.
- Eltern-E-Mail angeben.
- Elternzustimmung wird angefordert.
- Bis zur Entscheidung Warteseite sehen.
- Bei Zustimmung soziale Funktionen nutzen.
- Bei Ablehnung eingeschränkt bleiben.
- Bei Widerruf wieder eingeschränkt werden.
- Weiterhin Login möglich, aber nicht alle sozialen Funktionen nutzbar.

Relevante Routen:

- `/guardian-consent/pending`
- `/register`
- `/login`

### 1.5 Eltern / Erziehungsberechtigte

Use Cases:

- Elternzustimmungsseite per Token öffnen.
- Zustimmung erteilen.
- Zustimmung ablehnen.
- Eltern-Login mit gespeicherter E-Mail starten.
- Zugangscode per E-Mail erhalten.
- Code eingeben.
- Verknüpfte Kinder anzeigen.
- Zustimmung für Kind widerrufen.
- Optional Elternkonto erstellen.
- Optional bestehendes Konto als Elternkonto verknüpfen.
- Elternbereich verlassen.

Relevante Routen:

- `/guardian-consent/{token}`
- `/eltern-login`
- `/eltern-login/code`
- `/eltern/kinder`
- `/eltern/konto-erstellen`
- `/eltern/kinder/{child}/widerrufen`
- `/eltern/logout`

### 1.6 Trainer

Use Cases:

- Teams erstellen.
- Teams bearbeiten.
- Teamprofil pflegen.
- Teamlogo und Titelbild pflegen.
- Mitglieder einladen.
- Teambeitrittsanfragen prüfen.
- Teamrollen ändern.
- Mitglieder entfernen.
- Events oder Trainingstermine erstellen.
- Event-Kommentare nutzen.
- Teamchat nutzen.
- Dateien teilen.
- Trainingswissen als Beiträge veröffentlichen.
- Skills von Sportlern bestätigen.
- Empfehlungen schreiben.
- Marketplace-Angebote wie Kurse, Camps oder Trainingspläne einreichen.

Relevante Routen:

- `/teams`
- `/teams/{team}`
- `/teams/{team}/invite`
- `/team-join-requests/{joinRequest}/approve`
- `/team-join-requests/{joinRequest}/decline`
- `/events`
- `/conversations`
- `/files`
- `/commerce/products`

### 1.7 Verein

Use Cases:

- Verein erstellen.
- Verein anzeigen.
- Verein bearbeiten.
- Vereinsbilder pflegen.
- Verein löschen.
- Vereinsnummer hinterlegen.
- Offiziellen Vereinsstatus pflegen.
- Vereinsmitglieder verwalten.
- Vereinsrollen verwalten.
- Personen als Mitglied, Nichtmitglied, in Prüfung oder ehemaliges Mitglied markieren.
- Mitgliedsnummer manuell eintragen.
- Mitgliedsnummer automatisch generieren.
- Lizenznummer der Mitglieder pflegen.
- Mitgliedsbeitrag pflegen.
- Beitragsintervall pflegen.
- Nächstes Rechnungsdatum pflegen.
- Eintrittsdatum pflegen.
- Mitgliedschaftsende pflegen.
- Notizen zur Mitgliedschaft pflegen.
- Externe Mitglieder per E-Mail anlegen.
- Externe Mitglieder per Excel/CSV importieren.
- Airmius-Importvorlage herunterladen.
- Externe Mitglieder später einladen.
- Externe Einladung per Token annehmen lassen.
- SEPA-Einstellungen pflegen.
- SEPA-Lastschrift-XML exportieren.
- Banktransaktionen importieren.
- Banktransaktionen manuell bestätigen.
- DATEV/SKR42-Einstellungen pflegen.
- DATEV/SKR42-CSV exportieren.
- Rechnungen für Mitglieder erstellen.
- Rechnungsstatus ändern.
- Zahlungen erfassen.
- Mahnungen senden.
- Wiederkehrende Beitragsrechnungen durch Scheduler erzeugen lassen.
- Bald endende Mitgliedschaften überwachen.
- Offene oder bald fällige Beitragszahlungen überwachen.
- Teams im Verein verwalten.
- Teambeitrittsanfragen als Verein bearbeiten.
- Dateien und Ordner nutzen.
- Jobs veröffentlichen.
- Sponsorendaten verwalten.
- Add-ons buchen.
- Werbeagentur-/Vereinswebsite-Service anfragen.
- Vereins-Abo verwalten.

Relevante Routen:

- `/clubs`
- `/clubs/{club}`
- `/clubs/{club}/members/{user}`
- `/clubs/{club}/images`
- `/clubs/{club}/jobs`
- `/organization-jobs/{organizationJob}`
- `/club-memberships`
- `/club-memberships/import-template`
- `/clubs/{club}/membership/email-members`
- `/clubs/{club}/membership/email-members/import`
- `/clubs/{club}/membership/sepa-settings`
- `/clubs/{club}/membership/sepa-export`
- `/club-external-members/{externalMember}/invite`
- `/club-member-invitations/token/{token}/accept`
- `/clubs/{club}/membership/{user}`
- `/clubs/{club}/membership/{user}/member-number`
- `/clubs/{club}/membership/{user}/invoices`
- `/membership-invoices/{invoice}`
- `/membership-invoices/{invoice}/payments`
- `/membership-invoices/{invoice}/reminder`
- `/clubs/{club}/membership/bank-transactions/import`
- `/membership-bank-transactions/{bankTransaction}/confirm`
- `/clubs/{club}/membership/datev-settings`
- `/clubs/{club}/membership/datev-export`
- `/commerce`

### 1.8 Team

Use Cases:

- Team erstellen.
- Team anzeigen.
- Team bearbeiten.
- Teamlogo und Titelbild ändern.
- Team löschen.
- Nutzer einladen.
- Einladung annehmen.
- Externe Einladung per Token annehmen.
- Beitrittsanfrage stellen.
- Beitrittsanfrage annehmen.
- Beitrittsanfrage ablehnen.
- Teammitglied Rolle ändern.
- Teammitglied entfernen.
- Teamchat nutzen.
- Team-Events nutzen.
- Team-Dateien nutzen.

Relevante Routen:

- `/teams`
- `/teams/{team}`
- `/teams/{team}/images`
- `/teams/{team}/invite`
- `/teams/{team}/join-requests`
- `/team-invitations/{invitation}/accept`
- `/team-invitations/token/{token}/accept`
- `/team-join-requests/{joinRequest}/approve`
- `/team-join-requests/{joinRequest}/decline`
- `/teams/{team}/members/{user}`

### 1.9 Sponsor

Use Cases:

- Sponsorprofil im Adminbereich verwalten lassen.
- Eigene Kampagne vorbereiten.
- Ziel-URL hinterlegen.
- Budget hinterlegen.
- Beschreibung hinterlegen.
- Kampagne durch Admin prüfen lassen.
- Kampagne aktivieren lassen.
- Impressionen erfassen lassen.
- Klicks erfassen lassen.
- CTR sehen.
- Budgetverbrauch sehen.
- Kampagne pausieren oder abschließen lassen.
- Externe Zielseite verlinken.

Relevante Routen:

- `/commerce/campaigns`
- `/ads/active`
- `/ads/{campaign}/click`
- `/admin/sponsors`
- `/admin/commerce`

### 1.10 Marketplace-Anbieter

Use Cases:

- Produkt, Kurs, Camp oder Dienstleistung einreichen.
- Angebot automatisch moderieren lassen.
- Bei Risiko Adminprüfung auslösen.
- Bei schwerem Risiko automatisch abgelehnt werden.
- Ablehnungsgrund sehen.
- Freigegebenes Angebot öffentlich im Marketplace anzeigen.
- Produktdetailseite bereitstellen.
- Verkäufe erhalten.
- Auszahlungsdaten hinterlegen.
- Auszahlungsstatus prüfen.
- Offene auszahlbare Beträge sehen.
- Airmius-Provision akzeptieren.
- Auszahlung durch Admin vorbereiten lassen.
- Auszahlung als bezahlt markieren lassen.

Relevante Routen:

- `/commerce`
- `/commerce/products`
- `/commerce/products/{product}`
- `/commerce/payout-profile`
- `/admin/commerce`

### 1.11 Käufer im Marketplace

Use Cases:

- Marketplace-Produkte ansehen.
- Produktdetailseite öffnen.
- Anbieterinformationen sehen.
- Preis sehen.
- Zahlungsart wählen: Stripe, PayPal oder Überweisung.
- AGB und Widerrufshinweise bestätigen.
- Bestellung starten.
- Rückkehr vom Zahlungsanbieter verarbeiten.
- Überweisungsdaten sehen.
- Bestellung abgeschlossen sehen.
- Problem mit abgeschlossener Bestellung melden.
- Admin kann Problem prüfen, lösen oder erstatten.

Relevante Routen:

- `/commerce`
- `/commerce/products/{product}`
- `/commerce/products/{product}`
- `/checkout/commerce/{order}/success`
- `/checkout/commerce/{order}/cancel`
- `/checkout/commerce/{order}/bank-transfer`
- `/commerce/orders/{order}/issue`

### 1.12 Airmius System Admin

Use Cases:

- Nutzer verwalten.
- Mitglieder verwalten.
- Nutzer erstellen.
- Nutzer bearbeiten.
- Nutzer löschen.
- Rollen und Berechtigungen verwalten.
- Rollen erstellen.
- Rollen bearbeiten.
- Rollen löschen.
- Permissions erstellen.
- Sportarten verwalten.
- Gamification-Regeln verwalten.
- Moderation verwalten.
- Blog verwalten.
- Zahlungen verwalten.
- Rechnungen verwalten.
- Sponsoren verwalten.
- Systemeinstellungen verwalten.
- Wartungsmodus aktivieren/deaktivieren.
- Abo-Pläne verwalten.
- Vereins-Abos zuordnen.
- Nutzer-Abos zuordnen.
- Abos kündigen.
- Abos verlängern.
- Abo-Rechnungen herunterladen.
- Banküberweisungen als bezahlt markieren.
- Commerce verwalten.
- Coupons verwalten.
- Add-ons verwalten.
- Marketplace-Produkte verwalten.
- Ads-Kampagnen verwalten.
- Commerce-Bestellungen verwalten.
- Werbeagentur-/Website-Anfragen verwalten.
- Auszahlungsprofile prüfen.
- Auszahlungen vorbereiten.
- Auszahlungen als bezahlt markieren.
- Bestellprobleme bearbeiten.
- Automatische Moderationsflags bearbeiten.
- Nutzermeldungen bearbeiten.
- Inhalte entfernen.
- Verwarnungen und gesperrte Konten überwachen.

Relevante Routen:

- `/admin/users`
- `/admin/members`
- `/admin/roles-permissions`
- `/admin/gamification`
- `/admin/sports`
- `/admin/moderation`
- `/admin/blogs`
- `/admin/payments`
- `/admin/subscriptions`
- `/admin/subscription-invoices`
- `/admin/commerce`
- `/admin/invoices`
- `/admin/sponsors`
- `/admin/settings`

## 2. Funktionsmodule

### 2.1 Registrierung, Login und Konto

Use Cases:

- Registrierung mit vollständigen Basisdaten.
- Login per E-Mail/Passwort.
- Login per Google.
- Login per Microsoft/Outlook.
- Social-Account wird verknüpft.
- Fehlende Pflichtdaten nach Social Login über Profilvervollständigung nachtragen.
- Minderjährigenstatus prüfen.
- Elternzustimmung auslösen.
- Wartungsmodus beachten.
- Gesperrte Konten blockieren.
- Sprache wechseln.
- Passwort zurücksetzen.
- Zwei-Faktor-Funktionen von Jetstream/Fortify nutzen, soweit aktiviert.

### 2.2 Profil und Sportprofil

Use Cases:

- Persönliches Profil anzeigen.
- Profilinformationen bearbeiten.
- Profilfoto verwalten.
- Sportarten hinzufügen.
- Skill-Level aktualisieren.
- Skills bestätigen.
- Empfehlungen schreiben.
- Empfehlungen annehmen.
- Empfehlungen ablehnen.
- XP und Gamification-Daten sammeln.
- Profil-Sichtbarkeit prüfen.
- Lizenznummer pflegen.

### 2.3 Feed, Posts, Kommentare, Likes und Helpful

Use Cases:

- Feed nach Sichtbarkeit laden.
- Öffentliche Beiträge sehen.
- Vereinsbeiträge sehen, wenn berechtigt.
- Teambeiträge sehen, wenn berechtigt.
- Eigene Beiträge sehen.
- Beitrag mit Text, Bild und Anhängen erstellen.
- Beitrag bearbeiten.
- Beitrag löschen.
- Beitrag liken.
- Helpful markieren.
- Kommentar erstellen.
- Kommentar bearbeiten.
- Kommentar löschen.
- Automatische Moderation bei Erstellung und Bearbeitung.
- Entfernte Inhalte ausblenden.
- Inhalte melden.

### 2.4 Chat und Conversations

Use Cases:

- Chatübersicht öffnen.
- Keine Konversation automatisch öffnen, Nutzer wählt aktiv.
- Einzel- oder Gruppenkonversation erstellen.
- Team-Hauptchats nutzen.
- Nachrichten senden.
- Anhänge senden.
- Nachricht als gelesen markieren.
- Nachricht löschen.
- Reaktion setzen.
- Typing-Status senden.
- Gruppe verlassen.
- Bei letzter Person Gruppe löschen oder Löschung bestätigen.
- Neue Mitglieder sehen nur Nachrichten ab Beitritt, wenn so umgesetzt.
- Automatische Moderation bei Nachrichten.
- Entfernte Nachrichten ausblenden oder löschen.

### 2.5 Freunde und Follows

Use Cases:

- Freundesübersicht öffnen.
- Freundschaftsanfrage senden.
- Freundschaftsanfrage annehmen.
- Freundschaftsanfrage ablehnen.
- Nutzer folgen.
- Follow entfernen.
- Sichtbarkeit je nach Follow/Profilstatus prüfen.

### 2.6 Teams

Use Cases:

- Teams listen.
- Teamprofil öffnen.
- Team erstellen.
- Team bearbeiten.
- Bilder ändern.
- Team löschen.
- Einladen.
- Einladung annehmen.
- Token-Einladung annehmen.
- Beitritt anfragen.
- Beitrittsanfrage annehmen.
- Beitrittsanfrage ablehnen.
- Mitgliederrolle ändern.
- Mitglied entfernen.
- Teamlimit nach Vereinsplan prüfen.
- Teamchat verwenden.

### 2.7 Vereine

Use Cases:

- Vereine listen.
- Verein erstellen.
- Vereinsprofil öffnen.
- Verein bearbeiten.
- Vereinsbilder ändern.
- Verein löschen.
- Vereinsrolle ändern.
- Vereinssichtbarkeit prüfen.
- Offizielle Vereinsnummer pflegen.
- Vereinsjobs erstellen.
- Vereinsjobs bearbeiten.
- Vereinsjobs löschen.
- Öffentliche Vereinsseiten anzeigen.

### 2.8 Mitgliederverwaltung

Use Cases:

- Verwaltungsseite öffnen.
- Mitglieder und externe Mitglieder anzeigen.
- Teambeitrittsanfragen sehen.
- Mitgliedsdaten bearbeiten.
- Mitgliedsstatus setzen.
- Mitgliedsnummer manuell setzen.
- Mitgliedsnummer generieren.
- Lizenznummer setzen.
- Beitrag und Intervall setzen.
- Nächstes Rechnungsdatum setzen.
- Eintrittsdatum setzen.
- Mitgliedschaftsende setzen.
- Notizen setzen.
- Externe Mitglieder einzeln per E-Mail erfassen.
- Bei Erfassung entscheiden, ob Einladung versendet wird.
- Bestehenden Nutzer anhand E-Mail verknüpfen.
- Ohne Einladung nur externes Mitglied erfassen.
- Später nachträglich einladen.
- Excel/CSV importieren.
- Importvorlage herunterladen.
- Rechnungen erstellen.
- Rechnungsstatus aktualisieren.
- Zahlung erfassen.
- Mahnung senden.
- SEPA-Daten pflegen.
- SEPA-Export erstellen.
- Banktransaktionen importieren.
- Banktransaktionen matchen.
- DATEV-Einstellungen pflegen.
- DATEV/SKR42 exportieren.

### 2.9 Events und Aktivitäten

Use Cases:

- Events listen.
- Eventdetails anzeigen.
- Event erstellen.
- Event bearbeiten.
- Event löschen.
- An Event teilnehmen.
- Event verlassen.
- Event kommentieren.
- Eventchat öffnen.
- Aktivitäten als Kontext für Feed/Training nutzen.
- Trainingstermine als Systemhinweis im Team-Hauptchat posten, soweit vorhanden/geplant.

### 2.10 Fahrgemeinschaften / Rides

Use Cases:

- Fahrgemeinschaften listen.
- Fahrt anbieten.
- Fahrt beitreten.
- Fahrt verlassen.
- Fahrt löschen.
- Fahrer und Teilnehmer verwalten.

### 2.11 Dateien und Ordner

Use Cases:

- Dateiübersicht öffnen.
- Datei hochladen.
- Speicherlimit nach Vereinsplan prüfen.
- Datei herunterladen.
- Datei teilen.
- Datei löschen.
- Ordner erstellen.
- Ordner teilen.
- Ordner löschen.
- Dateien in Vereinen, Teams oder Events verwenden.

### 2.12 Benachrichtigungen

Use Cases:

- Benachrichtigungen anzeigen.
- Einzelne Benachrichtigung als gelesen markieren.
- Alle Benachrichtigungen als gelesen markieren.
- Chatnachrichten als Benachrichtigung senden.
- Mitgliedschaftsende melden.
- Beitragszahlung melden.
- Abo-Ende melden.
- Zahlung offen melden.
- Rechnungs- und Zahlungsemails senden.

### 2.13 Gamification

Use Cases:

- XP für passende Aktionen vergeben.
- Level und Badges nutzen.
- Skill- und Profilaktivitäten berücksichtigen.
- Admin pflegt Gamification-Regeln.
- Öffentliche Gamification-Seite erklären.

### 2.14 Sportartenverwaltung

Use Cases:

- Sportarten listen.
- Sportart erstellen.
- Sportart bearbeiten.
- Sportart löschen.
- Anzahl Gruppen je Sportart anzeigen.
- Sportarten in Profilen, Teams, Clubs oder Feed verwenden.

### 2.15 Sport-App-Integrationen

Use Cases:

- Google Fit verbinden.
- Garmin vorbereiten/anfragen.
- Mi Fitness vorbereiten/anfragen.
- Provider-OAuth starten.
- Callback speichern.
- Synchronisationsstatus aktualisieren.
- Verbindung löschen.
- Importierte Aktivitätsdaten speichern, soweit Provider angebunden ist.

### 2.16 Abo-Pläne und Feature-Gates

Use Cases:

- Öffentliche Preisseite anzeigen.
- Abo-Pläne nach Zielgruppe anzeigen.
- Free, Starter, Club, Pro, Elite, Enterprise verwalten.
- Nutzer-Abo zuordnen.
- Vereins-Abo zuordnen.
- Planpreise bearbeiten.
- Limits bearbeiten.
- Badge und CTA bearbeiten.
- Plan öffentlich/nicht öffentlich setzen.
- Feature-Gates prüfen:
  - Mitgliederlimit.
  - Teamlimit.
  - Speicherlimit.
  - Import.
  - externe Mitglieder.
  - Rechnungen.
  - Mahnungen.
  - SEPA.
  - Bankabgleich.
  - DATEV.
  - wiederkehrende Rechnungen.
  - API.
- Abo kündigen.
- Abo zum Periodenende kündigen.
- Abo verlängern.
- Trial überwachen.
- Past-due setzen.

### 2.17 Airmius-Zahlungen und Abo-Rechnungen

Use Cases:

- Subscription-Checkout starten.
- Stripe-Checkout starten.
- PayPal-Checkout starten.
- Überweisung/Rechnung wählen.
- Zahlungsreferenz erzeugen.
- Zahlungsziel setzen.
- Rückkehr von Stripe/PayPal verarbeiten.
- Stripe Webhook verarbeiten.
- PayPal Webhook verarbeiten.
- Überweisung manuell als bezahlt markieren.
- Abo aktivieren.
- Abo-Rechnung erzeugen.
- PDF-Rechnung herunterladen.
- Zahlungsbestätigung senden.
- Zahlungserinnerung senden.
- Trial-Ablaufwarnung senden.
- Kündigungsbestätigung senden.
- Verlängerungsbestätigung senden.

### 2.18 Commerce, Add-ons und Marketplace

Use Cases:

- Commerce-Seite öffnen.
- Add-ons anzeigen.
- Add-on für Verein buchen.
- Add-on privat buchen, falls erlaubt.
- Add-on monatlich oder jährlich buchen.
- Add-on per Stripe, PayPal oder Überweisung bezahlen.
- Marketplace-Produkte anzeigen.
- Produktdetails anzeigen.
- Produkt kaufen.
- AGB/Widerruf bestätigen.
- Bestellung stornieren.
- Bestellung als abgeschlossen aktivieren.
- Commerce-Webhooks verarbeiten.
- Commerce-Bestellbestätigung senden.
- Problem melden.
- Admin bearbeitet Problemfall.
- Admin markiert erstattet.
- Admin markiert gelöst.

### 2.18a Sportkleidung-Abos

Use Cases:

- Nutzer öffnet Outfit-Abo-Dashboard.
- Nutzer pflegt Style-Profil mit Sportfokus, Größen, Passform, Farben und No-Gos.
- Nutzer sieht öffentliche Outfit-Abo-Pläne.
- Nutzer sieht Sponsor-Subventionen direkt im Preis.
- Nutzer wählt monatliches Sportkleidung-Abo.
- System erstellt erste geplante Lieferung.
- Nutzer pausiert Abo.
- Nutzer setzt pausiertes Abo fort.
- Nutzer kündigt Abo.
- Nutzer sieht Lieferübersicht und Tracking-Daten, sobald vorhanden.
- Scheduler plant fällige Monatslieferungen automatisch.
- Nutzer wird über geplante Lieferung benachrichtigt.
- Admin verwaltet Outfit-Abo-Pläne separat.
- Admin verknüpft Sponsor mit Outfit-Abo-Plan.
- Admin definiert Branding-Regel wie Sponsor-Logo, Vereinslogo oder individuell.
- Kaufen und Nutzen ist für eingeloggte Nutzer offen.
- Öffentliches Marketplace-Listing zeigt Outfit-Abos auch Gästen.
- Admin-Angebotserstellung und Planverwaltung laufen über eigene Permissions.

### 2.19 Marketplace-Anbieter und Auszahlungen

Use Cases:

- Anbieter reicht Produkt ein.
- Automatische Vorprüfung.
- Adminprüfung.
- Freigabe.
- Ablehnung mit Grund.
- Archivierung.
- Anbieter hinterlegt Auszahlungsprofil.
- Admin prüft Auszahlungsprofil.
- Admin gibt Auszahlungsprofil frei.
- Admin sperrt Auszahlungsprofil.
- Offene Verkaufsbeträge gruppieren.
- Provision berechnen.
- Auszahlung vorbereiten.
- Auszahlung als bezahlt markieren.
- Bestellungen mit Auszahlung verknüpfen.

### 2.20 Ads und Sponsoring

Use Cases:

- Admin erstellt Kampagne.
- Sponsor erstellt eigene Kampagne.
- Kampagne bearbeiten.
- Status setzen: draft, active, paused, completed.
- Budget setzen.
- Ziel-URL setzen.
- Aktive Ad ausspielen.
- Impression zählen.
- Klick zählen.
- Klick zur Ziel-URL weiterleiten.
- CTR berechnen.
- Budgetverbrauch anzeigen.
- Admin-Reporting anzeigen.
- Sponsor-Reporting anzeigen.
- Sponsoren im Adminbereich verwalten.

### 2.21 Werbeagentur- und Website-Service

Use Cases:

- Gast sieht die öffentliche Werbeagentur-Seite.
- Verein fragt Website, Landingpage oder Kampagne an.
- Domainwunsch erfassen.
- Ziele erfassen.
- Notizen erfassen.
- Admin sieht Anfrage.
- Status setzen:
  - new
  - contacted
  - quoted
  - in_progress
  - done
  - cancelled
- Werbeagentur-/Website-Service ist rechtlich nur Anfrage, bis ein Angebot angenommen wird.

### 2.22 Blog und CMS

Use Cases:

- Öffentliche Blogliste anzeigen.
- Öffentlichen Blogartikel anzeigen.
- Admin-Blogliste öffnen.
- Blogbeitrag erstellen.
- Blogbeitrag bearbeiten.
- Blogbeitrag löschen.
- Status setzen:
  - draft
  - review
  - published
  - archived
- Blogartikel in Sitemap aufnehmen, wenn veröffentlicht.

### 2.23 Jobs

Use Cases:

- Öffentliche Jobs anzeigen.
- Verein erstellt Job.
- Verein bearbeitet Job.
- Verein löscht Job.
- Jobs in Sitemap/öffentlichen Seiten als Wachstumsmodul nutzen.

### 2.24 Rechtliche Seiten

Use Cases:

- Impressum anzeigen.
- Datenschutz anzeigen.
- AGB anzeigen.
- Community-Richtlinien anzeigen.
- Jugendschutz anzeigen.
- Cookie-Hinweise anzeigen.
- Widerruf anzeigen.
- Kontakt und Melden anzeigen.
- Rechtstexte berücksichtigen:
  - Registrierung.
  - Elternzustimmung.
  - Vereinsverwaltung.
  - Zahlungen.
  - Marketplace.
  - Ads.
  - Werbeagentur-/Website-Service.
  - Moderation.
  - Inaktivität, Löschung, Anonymisierung und gesetzliche Aufbewahrungspflichten.
- AVV/DPA-Nachweise für Hostinger und Cloudflare intern ablegen.
- Cloudflare als US-Anbieter mit DPA, SCCs und DPF dokumentieren.
- EU-only Aussagen nur verwenden, wenn Cloudflare-Datenlokalisierung tatsächlich gebucht und aktiviert ist.
- VVT-Einträge und Subprocessor-Prüfprotokoll pflegen.

### 2.25 Moderation, Meldungen und Sperren

Use Cases:

- Automatische Textanalyse durchführen.
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
- Medium-Risiko als Flag offen markieren.
- High-Risiko Inhalt zurückhalten oder entfernen.
- ModerationFlag erstellen.
- AccountWarning erstellen.
- Verwarnpunkte zählen.
- Sperre setzen bei 2 High-Verstößen in 90 Tagen.
- Sperre setzen bei 5 Warnpunkten in 90 Tagen.
- Gesperrte Nutzer auf Sperrseite leiten.
- Admin sieht offene Flags.
- Admin sieht Nutzermeldungen.
- Admin entfernt Inhalte.
- Admin schließt Fälle als unkritisch.
- Admin markiert Fälle als bearbeitet.

### 2.26 Admin: Nutzer, Rollen und Berechtigungen

Use Cases:

- Nutzerliste öffnen.
- Nutzer erstellen.
- Nutzer bearbeiten.
- Nutzer löschen.
- Mitgliederliste öffnen.
- Rollen-/Permission-Seite öffnen.
- Rolle erstellen.
- Rolle bearbeiten.
- Rolle löschen.
- Permission erstellen.
- Rollen Nutzern zuweisen, soweit im MemberController/RolePermissionController umgesetzt.

### 2.27 Admin: Rechnungen, Payments, Sponsors

Use Cases:

- Admin-Rechnungen anzeigen.
- Admin-Rechnung erstellen.
- Admin-Rechnung löschen.
- Payments anzeigen.
- Payment erstellen.
- Payment löschen.
- Sponsoren anzeigen.
- Sponsor erstellen.
- Sponsor bearbeiten.
- Sponsor löschen.

### 2.28 Admin: Einstellungen und Wartung

Use Cases:

- Systemeinstellungen anzeigen.
- Wartungsmodus aktivieren.
- Wartungsmodus deaktivieren.
- Wartungsmeldung bearbeiten.
- Bankdaten für Überweisung pflegen.
- Rechtliche oder technische Einstellungen pflegen, soweit im SettingController vorhanden.
- Normale Nutzer im Wartungsmodus blockieren.
- Berechtigte Admins weiterarbeiten lassen.

### 2.29 SEO und öffentliche Technik

Use Cases:

- `robots.txt` ausliefern.
- Private Bereiche für Robots sperren:
  - `/admin`
  - `/dashboard`
  - `/settings`
  - `/conversations`
  - `/messages`
- `sitemap.xml` ausliefern.
- Statische öffentliche Seiten in Sitemap aufnehmen.
- Veröffentlichte Blogposts in Sitemap aufnehmen.
- SeoHead auf öffentlichen/rechtlichen Seiten nutzen.

### 2.30 Webhooks und externe Provider

Use Cases:

- Stripe Subscription Webhook empfangen.
- PayPal Subscription Webhook empfangen.
- Stripe Commerce Webhook empfangen.
- PayPal Commerce Webhook empfangen.
- Webhook-Signaturen prüfen, wenn Secret konfiguriert ist.
- Checkout-Session oder PayPal Order synchronisieren.
- Bestellung oder Abo aktivieren.
- Doppelte Aktivierung vermeiden.

### 2.31 Scheduler und Cron

Use Cases:

- Laravel Scheduler per Cron starten.
- Wiederkehrende Beitragsrechnungen erstellen.
- Membership- und Billing-Erinnerungen senden.
- Abo-Ende und Trial-Ende prüfen.
- Offene Abo-Rechnungen erinnern.
- Überfällige Rechnungen markieren.
- Inaktive Konten prüfen.
- Inaktivitätswarnungen nach ca. 12 und 18 Monaten senden.
- Konten nach ca. 24 Monaten zur Anonymisierung vormerken.
- Vorgemerkte Konten nach ca. 30 Tagen ohne Reaktion anonymisieren.

### 2.32 Broadcast und Echtzeit

Use Cases:

- Userstatus über Channel `users.status` broadcasten.
- Nachrichten senden.
- Nachrichten löschen broadcasten.
- Reaktionen auf Nachrichten broadcasten.
- Typing-Status senden.
- Chat-Status in UI aktualisieren.

## 3. End-to-End-Abläufe

### 3.1 Neuer Sportler ohne Elternpflicht

1. Nutzer registriert sich.
2. Profil wird vervollständigt.
3. Nutzer wählt Sportarten und Skills.
4. Nutzer nutzt Feed, Teams, Events und Marketplace.
5. Nutzer kann Profil sichtbar machen und Empfehlungen sammeln.

### 3.2 Neuer Sportler unter 16

1. Nutzer registriert sich.
2. System erkennt Alter unter 16.
3. Eltern-E-Mail wird gespeichert.
4. Zustimmungsmail wird verschickt.
5. Nutzer sieht Pending-Seite.
6. Eltern stimmen zu oder lehnen ab.
7. Bei Zustimmung wird Nutzung freigeschaltet.
8. Eltern können später widerrufen.

### 3.3 Verein startet digital

1. Verein registriert/erstellt sich.
2. Vereinsdaten und Vereinsnummer werden gepflegt.
3. Teams werden erstellt.
4. Mitglieder werden importiert.
5. Einladungen werden optional verschickt.
6. Mitgliedsnummern und Beiträge werden gepflegt.
7. Rechnungen und Zahlungen werden verwaltet.
8. Verein bucht passenden Abo-Plan oder Add-ons.

### 3.4 Teambeitritt mit Vereinsbezug

1. Sportler stellt Beitrittsanfrage.
2. Team/Verein sieht Anfrage.
3. Verantwortlicher nimmt an.
4. Sportler wird Teammitglied.
5. Verein markiert Mitgliedschaftsstatus.
6. Vorherige Chatnachrichten sollen für neue Mitglieder geschützt bleiben.

### 3.5 Marketplace-Angebot bis Auszahlung

1. Anbieter reicht Angebot ein.
2. Auto-Moderation prüft Inhalt.
3. Admin gibt Angebot frei.
4. Käufer kauft Angebot.
5. Zahlung wird per Webhook/Rückkehr/Überweisung abgeschlossen.
6. Bestellung wird completed.
7. Anbieter sieht offenen Auszahlungsbetrag.
8. Admin prüft Auszahlungsprofil.
9. Admin bereitet Auszahlung vor.
10. Admin zahlt aus und markiert paid.

### 3.6 Ads-Kampagne

1. Sponsor erstellt Kampagne.
2. Admin prüft.
3. Admin setzt active.
4. `/ads/active` liefert Kampagne aus.
5. Impression wird gezählt.
6. Klick wird gezählt.
7. Reporting zeigt CTR und Budget.

### 3.7 Moderationsverstoß

1. Nutzer erstellt Inhalt.
2. ModerationService analysiert Text.
3. Flag wird erstellt.
4. Verwarnung wird erstellt, wenn relevant.
5. Inhalt wird markiert oder entfernt.
6. Bei Schwelle wird Konto gesperrt.
7. Admin prüft Fall.

### 3.8 Airmius-Abo per Überweisung

1. Nutzer/Verein wählt Plan.
2. Checkout mit Überweisung wird erstellt.
3. Zahlungsreferenz und Bankdaten werden angezeigt.
4. Admin markiert Zahlung als bezahlt.
5. Abo wird aktiviert.
6. Rechnung und Bestätigung werden bereitgestellt.

### 3.9 Airmius-Abo per Stripe/PayPal

1. Nutzer/Verein wählt Plan.
2. Checkout wird gestartet.
3. Provider verarbeitet Zahlung.
4. Rückkehr oder Webhook synchronisiert Zahlung.
5. Abo wird aktiviert.
6. Rechnung wird erzeugt.

### 3.10 Vereinsbeitrag mit Bankabgleich

1. Rechnung ist offen.
2. Verein importiert Bank-CSV.
3. System sucht Treffer.
4. Sicherer Treffer wird bezahlt.
5. Unsicherer Treffer bleibt Vorschlag.
6. Verein bestätigt manuell.

## 4. Systemregeln

### 4.1 Moderationsregeln

- Low: beobachten, keine Punkte.
- Medium: Flag, 1 Warnpunkt, Adminprüfung.
- High: Inhalt zurückhalten/entfernen, 3 Warnpunkte, Adminprüfung.
- Automatische Sperre bei 5 Warnpunkten in 90 Tagen.
- Automatische Sperre bei 2 High-Verstößen in 90 Tagen.
- Sperre standardmäßig 7 Tage, bei schweren Wiederholungen 14 Tage.
- System-Admins sind von der Sperrseite ausgenommen.

### 4.2 Datenschutz- und Jugendschutzregeln

- Unter 16 nur mit Elternzustimmung für soziale Funktionen.
- Eltern-E-Mail bleibt für Zustimmung/Widerruf gespeichert, solange erforderlich.
- Eltern können über E-Mail-Code zugreifen.
- Elternkonto ist optional.
- Minderjährige sollen besonders vor Ads, Kontaktmissbrauch und problematischen Inhalten geschützt werden.
- Personenbezogene Daten werden nach Zweckbindung und Speicherbegrenzung nur so lange aktiv gehalten, wie sie für Nutzung, Vertrag, Sicherheit oder gesetzliche Pflichten erforderlich sind.
- Inaktive Konten werden gestuft behandelt: erste Warnung nach ca. 12 Monaten, zweite Warnung nach ca. 18 Monaten, Vormerkung zur Anonymisierung nach ca. 24 Monaten.
- Nach Vormerkung erhalten Nutzer eine letzte Reaktionsfrist von ca. 30 Tagen.
- Eine erneute Anmeldung setzt die Inaktivitätswarnungen und geplante Anonymisierung zurück.
- Bei Anonymisierung werden Profilangaben, Tokens, Social-/Sport-Verknüpfungen, Profilbilder, persönliche Medien und persönliche Inhalte soweit möglich entfernt oder anonymisiert.
- Rechnungs-, Zahlungs-, Bestell-, Vereins- und Nachweisdaten bleiben erhalten, soweit gesetzliche Aufbewahrungspflichten oder berechtigte Nachweisinteressen bestehen.

### 4.3 Plan- und Limitregeln

- Free begrenzt Mitglieder, Teams und Speicher.
- Starter ermöglicht Import und einfache Rechnungen.
- Club ermöglicht Mahnungen und Sponsorenbasis.
- Pro ermöglicht wiederkehrende Rechnungen, SEPA, Bankabgleich, DATEV.
- Elite ermöglicht hohe Limits/API.
- Feature-Gates müssen bei kritischen Funktionen serverseitig geprüft werden.

### 4.4 Zahlungsregeln

- Stripe, PayPal und Überweisung sind Zahlungswege.
- Überweisung braucht manuelle Bestätigung.
- Webhooks sollen Providerzahlungen auch ohne Rückkehrseite aktivieren.
- Abo-Rechnungen und Commerce-Orders sind getrennte Zahlungsbereiche.
- Marketplace-Auszahlung ist aktuell manuell vorbereitet, nicht automatisch überwiesen.

### 4.5 Marketplace-Regeln

- Anbieter bleibt grundsätzlich für Angebot verantwortlich, wenn Airmius nicht selbst Anbieter ist.
- Angebot muss geprüft werden.
- Käufer muss AGB/Widerruf bestätigen.
- Airmius kann Provision abziehen.
- Problemfälle können gemeldet und adminseitig gelöst/erstattet werden.

### 4.6 Werbeagentur-/Website-Service-Regeln

- Anfrage ist noch kein Vertrag.
- Verbindlicher Auftrag erst nach Angebot/Annahme.
- Verein bleibt für Inhalte, Rechte, Impressum und Datenschutzdaten verantwortlich, soweit nicht anders vereinbart.

## 5. Vollständige Seiten- und Bereichsliste

### Öffentlich

- Startseite
- Preise
- Marketplace
- Vereine
- Blogliste
- Blogdetail
- Jobs
- E-Learning
- Gamification
- Top-Inhalte
- Impressum
- Datenschutz
- AGB
- Community-Richtlinien
- Jugendschutz
- Cookies
- Widerruf
- Kontakt und Melden
- Robots
- Sitemap

### Authentifiziert

- Dashboard
- Profilvervollständigung
- Nutzerprofil
- Einstellungen
- Sportintegrationen
- Suche
- Feed
- Posts
- Kommentare
- Likes
- Reports
- Freunde
- Teams
- Vereine
- Mitgliederverwaltung
- Events
- Chat
- Nachrichten
- Dateien
- Ordner
- Fahrgemeinschaften
- Notifications
- Commerce
- Commerce-Produktdetail
- Commerce-Überweisung
- Subscription-Überweisung

### Eltern

- Elternzustimmung
- Eltern-Pending
- Eltern-Login
- Codeprüfung
- Kinderübersicht
- Elternkonto erstellen
- Zustimmung widerrufen

### Admin

- Nutzer
- Mitglieder
- Rollen und Berechtigungen
- Gamification
- Sportarten
- Moderation
- Blog CMS
- Payments
- Subscriptions
- Subscription Invoices
- Commerce
- Invoices
- Sponsors
- Settings

### System / Extern

- Social Redirect Google
- Social Callback Google
- Social Redirect Microsoft
- Social Callback Microsoft
- Stripe Webhook Subscription
- PayPal Webhook Subscription
- Stripe Webhook Commerce
- PayPal Webhook Commerce
- Ads Active
- Ads Click
- Scheduler Cron
- Broadcast Channels

## 6. Noch offene oder teilfertige Use Cases

Diese Fälle sind im Produktbild vorhanden, aber noch nicht vollständig produktionsreif:

- Automatische Rückerstattung über Stripe/PayPal.
- Automatische Anbieter-Auszahlung.
- Vollständiges Anbieter-Onboarding mit Steuer- und Vertragsdaten.
- Erweiterte Ads-Zielgruppen.
- Anzeigenplatzierungen in konkreten UI-Bereichen.
- Consent-Banner für Marketing/Tracking.
- Öffentliche SEO-Seiten für Sportarten und Städte.
- Inertia SSR.
- Erweiterte Audit-Logs.
- Push-Benachrichtigungen.
- Vollständige Garmin- und Mi-Fitness-API-Anbindung.
- Google-Fit-Aktivitätsmapping.
- Wiederkehrende Provider-Abos statt nur Checkout/Statuslogik.
- Familienbeiträge und Beitragsgruppen.
- Digitale Mitgliedsanträge.
- Digitale Unterschriften.
- Erweiterte Elternsteuerung.
- Erweiterte Content-Freigabe für Minderjährige.
- Echte Werbeagentur-/Website-Service-Angebotserstellung mit Rechnung/Checkout.

## 7. Pflegehinweis

Wenn neue Funktionen entstehen, diese Datei an drei Stellen aktualisieren:

- Rolle/Akteur ergänzen, falls neuer Nutzerkreis betroffen ist.
- Modul ergänzen, falls neuer Funktionsbereich entsteht.
- End-to-End-Ablauf ergänzen, falls ein neuer Prozess mehrere Module verbindet.

Zusätzlich synchron halten mit:

- `docs/FUNCTIONS_INDEX.md`
- `docs/BUSINESS_MODEL_USE_CASES.md`
- `routes/auth.php`
- `routes/admin.php`
- `routes/guest.php`
- `routes/web.php`

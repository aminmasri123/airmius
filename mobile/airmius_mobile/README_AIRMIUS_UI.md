# Airmius Mobile UI Coverage

Diese Flutter-App ist als native UI-Konvertierung der mobilen Airmius-Web-App angelegt. Backend-Daten werden spaeter ueber Laravel `/api/v1` angeschlossen.

## Native UI bereits angelegt

- Login mit Airmius-Logo, Sprache und Web-App-Optik
- Native Auth-Zusatzflows fuer Registrierung, Passwort vergessen/zuruecksetzen, Zwei-Faktor-Code, E-Mail-Verifizierung, Profil vervollstaendigen und gesperrtes Konto
- Auth-Aktionen wie Registrierung, Reset-Link, 2FA, E-Mail-Verifizierung, Profilabschluss und Supportkontakt fuehren in native UI-Aktionsflows
- Auth-Randflows aus der Web-App wie Social Login, Account-Linking, Konto-Löschcode und finale Kontolöschung sind als native UI vorbereitet
- Login bietet direkten Einstieg in Registrierung/Passwort/2FA/Profile-Completion-UI
- Native Gastseite/Public-Portal mit Landingpage, Vereine, Marketplace, E-Learning, Blog, Jobs, Sponsoren, Gamification, Werbeagentur und Legal-Links
- Native Top-Inhalte-Public-UI mit kuratierten Blog-, Kurs-, Vereins-, Marketplace- und Sponsoring-Karten
- Spezialisierte Public-Detailseiten fuer Vereine, Preise, Marketplace, E-Learning, Blog, Jobs, Sponsoren, Gamification, Werbeagentur und Legal
- Native Public-Blog-Reader-UI mit Suche, Kategorien, RSS-Einstieg, Artikelkarten, Teilen und Melden
- Native Legal-Center-UI fuer Impressum, Datenschutz, AGB, Community-Richtlinien, Jugendschutz, Cookies, Widerruf und Kontakt/Melden
- Native Public-Interest-UI fuer Jobs, Sponsoren, Werbeagentur, Preise, Marketplace, Kurse, Kontakt und Legal-Meldungen
- Public-Interest-Anfragen besitzen native Senden-Flows mit Kontaktart, Datenschutzstatus und Lead-Uebergabe
- Native Public-Standort-Einreichung fuer Vereine, Sportorte, Anbieter und Korrekturen mit Datenschutz, Kontakt und Moderationsstatus
- Login bietet direkten Einstieg in die Gastseite
- App-Shell mit Topbar, Drawer, Bottom-Navigation und globaler Suche
- Globale Suche öffnet native Treffer fuer Vereine, Teams und Personen
- Topbar-Icons fuer Suche, Nachrichten und Benachrichtigungen öffnen native Center-Screens
- Mehrsprachigkeit: DE, EN, FR, AR inklusive Flutter-Locales, Material-Delegates und RTL-Richtung
- Native Sprachzentrale fuer Sprache wechseln, RTL-Hinweis, Moduluebersetzungen und spaeteren Laravel-Sprachsync
- Dashboard, Drawer und Modul-Screens nutzen zentrale Moduluebersetzungen mit lokalisiertem Untertitel-Fallback
- Dashboard mit Vereinsbereich, Hero-Karte, Metrics und Modulraster
- Native Tagesflow-/KI-Coach-UI mit Training, Ernaehrung, Wasser, Route, Coach-Hinweis und Schnellaktionen
- Tagesflow-Aufgaben und Schnellaktionen fuehren in Training/Event, Mahlzeit und Hydration-Aktionsflow statt leere Buttons zu verwenden
- Native Arbeitsbereiche-Center-UI mit Gastseite, Dashboard, Vereinsbereich, Trainerbereich, Rollen und Einladungen
- Native Access-Operations-UI fuer Mitglieder-CRUD, Rollen erstellen/bearbeiten/loeschen, Permissions anlegen, Inaktivitaetsnotiz und Workspace-Einladungen
- Native Workspace-Detail-UI mit Kontextwechsel, Rollenpruefung, Push-Regeln, Home-Pinning, Einladung und Rollenvererbung
- Vereine & Teams mit Suche, Clubkarten, Clubprofil, Dokumentbereich, Mitgliedsantrag und Anfrage-zurueckziehen
- Mitgliedsantrag mit Personendaten, Kontaktdaten, Wohndaten, Erziehungsberechtigten, Notfallkontakt, Zahlungsdaten, Dokument-Upload-UI und Einwilligungen
- Modul-UI fuer Arbeitsbereiche, Vereins-Cockpit, Vereine & Teams, Teams, Rollen & Rechte, Sportarten, Feed, Events, Events & Training, Trainer-Cockpit, Ernaehrung, Sportkarte, Freunde, Nachrichten, Fahrgemeinschaften, Dateien, Badges, Gamification-Regeln, Kurse, Marketplace, Commerce, Sponsoren, Medienrichtlinien, Blog & Medien, Nutzer, Abos & Rechnungen, Eltern & Jugendschutz, Altersfreigaben, Outfit-Abos, Einstellungen und Admin
- Modul-Funktionszeilen sind antippbar und fuehren in native UI-Aktionsflows
- Tappable Modulzeilen und Statuskarten mit nativen Detailansichten
- Generische Platzhalter-Detailseite wurde entfernt; Suchtreffer, Modulzeilen und Center-Karten fuehren jetzt in spezifische native Detailflows oder den Moduldetail-Flow
- Detail-UI fuer Dateien, Chat, Training, Marketplace, Einstellungen, Admin, Ernaehrung, Sportkarte und Social/Freunde
- Native Benachrichtigungszentrale mit Filtern, ungelesen Status, Metriken und Detailnavigation
- Native Benachrichtigungsdetail-UI mit Read-State, Pinning, Stummschaltung, Kontextaktion und Push-Einstellungen
- Benachrichtigungsdetail deckt einzelne Read-State-Speicherung und Loeschen als native UI-Aktionen ab
- Native Benachrichtigungseinstellungen mit Push, E-Mail, Chat, Zahlungskanaelen, Ruhezeit, Prioritaet und Bulk-Aktionen
- Billing-, Guardian-, Medien-, Maturity- und Outfit-Aktionen nutzen native UI-Aktionsflows fuer spaetere sichere Laravel-Mutationen
- Native Nachrichtenzentrale mit Konversationssuche, ungelesen Status, Chatkarten und Detailnavigation
- Nachrichtenzentrale deckt Einladungen annehmen/ablehnen und Bulk-Read-State als native Mobile-Flows ab
- Native Neue-Konversation-UI mit Direktchat, Teamchat, Vereinsadmin, Eventchat, Support, Rechtepruefung, Eventverknuepfung und Dateianhaengen
- Neue-Konversation-UI deckt zusaetzlich Reaktionsrechte und Owner-Freigabe fuer neue Mitglieder ab
- Native Chat-Detail-UI mit Nachrichten, Reaktionen, Attachments, Typing, Teilnehmern, Rollen und Lesestatus
- Chat-Aktionen fuer Nachricht, Datei, Bild und Reaktion fuehren in native UI-Aktionsflows
- Chatverwaltung deckt Stummschaltung, Chat verlassen, Mitglied hinzufuegen, Mitglied entfernen, Owner uebertragen, Typing-Event, Read-State, Nachricht ausblenden, Nachricht melden und Nachricht loeschen als native UI-Flows ab
- Eigene Profilseite mit Profilstatus, Sportprofil, Sichtbarkeit, Zahlungen und Mitgliedschaften
- Profil-Sportarten, Empfehlungen und Mitgliedschaften sind antippbar und fuehren in Sportprofil-, Badge- oder Mitgliedschaftsstatus-Flows
- Native Profil-/Freundschaftsdetail-UI mit Profilvorschau, Sichtbarkeit, Empfehlungen, Freundschaft, Blockieren und Melden
- Profil-/Freundschaftsaktionen fuer Annehmen, Ablehnen, Anfrage senden, Ausblenden und Nachricht starten sind nativ verbunden
- Native Skills-/Empfehlungs-UI fuer Sport-Skills, Skill-Level, Endorsements, Empfehlungen, Recommendation-Approve und Recommendation-Reject
- Wiederverwendbarer nativer Bearbeiten-Screen fuer Profil, Datenschutz, Zahlung, Admin, Datei, Chat und Event
- Detail-Primary-Actions fuehren in passende native Formularscreens
- Native Vereins-Mitgliedschaftsverwaltung fuer Admins mit Anfragen, Formularfeld-Schaltern, Zahlrhythmus, Zahlmethode und verknuepften Dokumenten
- Native Membership-Operations-UI fuer Antrag pruefen/annehmen/ablehnen/zurueckziehen, Formularschema, Mitgliedschaftstypen, Dokumentpflicht, Uploadpflicht, Mitgliedsnummer, Status, Rollen und Erstrechnung
- Mitgliedschaftsadmin- und Beitragsverwaltungsaktionen fuer Annehmen, Ablehnen, Nachricht, Import, Rechnung, SEPA, Einladung, Zahlung, Mahnung, Bankabgleich, DATEV und Regeln besitzen native UI-Aktionsflows
- Native Mitglieder-/Beitragsverwaltung mit Mitgliedertabelle, externen Mitgliedern, Rechnungen, Zahlungen, Banktransaktionen, SEPA/DATEV und Import
- Native Mitgliederfinanz-Detail-UI mit Beitrag, Zahlrhythmus, Zahlmethode, Zahlungsabgleich, SEPA, DATEV, Dokumenten und Audit
- Native Vereins-Cockpit-UI mit Vereinsprofil, Teams, Mitgliedsanfragen, Beitraegen, Dokumenten, Rollen und Public-Sichtbarkeit
- Vereins-Cockpit-Aktionen fuehren in Mitgliedschaftsverwaltung, Teamdetail, Dateimanager, Vereinsprofil-Aktion und Beitragseditor
- Native Dateimanager-UI mit Ordnern, Upload-Aktionen, Datei-Liste, Preview-Bereich und Dokumentstatus
- Dateimanager-Upload, Ordneranlage, Datei-Download und Teilen besitzen native UI-Aktionsflows fuer spaetere Laravel-Mutationen
- Native Datei-Preview-UI mit Vorschau, Download, Teilen, Share-Link, Versionen und Vereinsdokument-Verknuepfung
- Native Shared-File-Access-UI fuer Token-Links, Ablaufdatum, Datenschutzbestaetigung, Preview, Download und Missbrauchsmeldung
- Native Marketplace-UI mit Produktsuche, Kategorien, Produktkarten, Warenkorb, Checkout-Einstieg, Bestellungen und Rueckgaben
- Native Marketplace-Produktdetail- und Checkout-UI mit Varianten, Anbieter, Fulfillment, Adresse, Zahlung und Bestellabschluss
- Native Checkout-Status-UI fuer Commerce, Subscription und Outfit mit Success, Cancel, Banktransfer, Rechnung, Beleg und Benachrichtigung
- Marketplace-Aktionen fuer Warenkorb und Checkout-Abschluss fuehren in native UI-Aktionsflows fuer spaetere Order-/Payment-API
- Marketplace-Randflows fuer Verkaeuferbewerbung, Anbieterprofil, Rueckgabeanfrage und Bestellproblem sind als native Mobile-UI vorhanden
- Native Commerce-UI fuer Anbieter mit Produkten, Orders, Coupons, Inventar, Payouts und Qualitaetsfreigabe
- Native Commerce-Operations-UI fuer Admin-Commerce-Spezialfunktionen: Coupons, Addons, Stock, Versand, Steuern, Retouren, Refunds, Payouts, Payout-Profile, Seller Applications, Website-Anfragen, Marketplace-Visuals, Provisionen und Order-Dokumente
- Native Commerce-Detail-UI mit Produkt-/Orderdaten, Fulfillment, Tracking, Produktqualitaet, Inventarwarnung, Coupon-Konfiguration und Payout-Freigabe
- Commerce-UI deckt zusaetzlich Seller-Antrag, Providerprofil, Marketplace-Ads/Kampagnen, Visuals, Retouren, Problemfaelle, Steuer- und Versandregeln ab
- Native Ads-/Kampagnen-UI mit Active-Ad-Preview, Placement, Klicktracking, Conversiontracking, Sponsor-, Werbeagentur- und Marketplace-Einstieg
- Native Sponsoren-Center-UI mit Sponsorprofilen, Paketen, Kampagnen, Kontaktanfragen und Sichtbarkeit
- Native Sponsor-Detail-UI mit Sponsorprofil, Paketwahl, Kontaktpipeline, Logo-Sichtbarkeit, Kampagnenstatus, Reporting und Antwortaktion
- Native Blog-/Medien-Center-UI mit Redaktion, Artikeln, Entwuerfen, Medienfreigaben und Richtlinien-Einstieg
- Native Content-Operations-UI fuer Blog-Kategorien, Content-Bilder, Artikelvorschau, Media-Visuals, Medienrichtlinien, Learning-Quality, Zertifikatspruefung und Sponsoren-CRUD
- Native Blog-/Medien-Detail-UI mit Artikel-Editor, Publikationsstatus, Sichtbarkeit, Medienvorschau, Bildrechten, Guardian Consent und Review-Aktion
- Native Learning-UI mit Kursfiltern, Kursfortschritt, Lektionen, Zertifikaten und Lernstudio-Einstieg
- Native Learning-Lektionsdetail-UI mit Video-Platzhalter, Quiz, Aufgabe, Kommentaren, Fortschritt und Zertifikat
- Learning-Aktionen fuer Kurs erstellen, Lektion abschliessen und Zertifikat anzeigen besitzen native UI-Aktionsflows
- Native Zertifikatspruefung fuer Public-Learning-Codes mit Code, Gueltigkeit, PDF, Teilnehmerdaten und Meldefunktion
- Native Events-/Training-Center-UI mit Wochenfilter, Planfortschritt, Events, Trainingsplaenen, Logs und Feedback-Einstieg
- Native Training-Operations-UI fuer Event erstellen/aktualisieren/absagen, Erinnerung, Teilnahme, Warteliste, Anwesenheit, Plan erstellen/freigeben/duplizieren, Trainingslog, Log-Auswertung, Coach-Feedback, Risiko-Markierung und Coach-Weekly
- Native Event-/Trainingsdetail-UI mit Teilnahme, Eventchat, Erinnerungen, Standortfreigabe, Trainingsplan, Log-Formular, RPE, Sichtbarkeit und Trainerfeedback
- Native Eventverwaltungs-UI mit Kalender, Teilnahme, Warteliste, Eventchat, Kommentaren, Absagen und Erinnerungen
- Native Event-Admin-Detail-UI mit Eventformular, Sichtbarkeit, Warteliste, Eventchat, Erinnerungen, Absage-Regeln, Teilnehmerstatus und Massenaktionsbasis
- Native Trainer-Cockpit-UI mit Athletenuebersicht, Wochenaktionen, Feedback, Risiko-Hinweisen und Planfreigabe
- Native Coach-Aktionsdetail-UI mit Prioritaet, Coach-Feedback, Athleten-Push, Abschluss, Trainingslog- und Planverknuepfung
- Native Ernaehrungs-Center-UI mit Tagesziel, Makros, Wasser, Mahlzeiten, Barcode- und KI-Fotoanalyse-Einstieg
- Native Wellbeing-Operations-UI fuer Nutrition-Ziel, Lebensmittel-Suche, Barcode, KI-Fotoanalyse, Mahlzeiten, Wasser, Sport-Routen, Route-Duplicate, Live-Tracks, Trackpunkte, Orte, Route-Analytics, Challenges und Safety Checks
- Native Ernaehrungs-Detail-UI mit Mahlzeit, Barcode-Suche, KI-Fotoanalyse, Portion, Makro-Korrektur und Tagesziel-Wirkung
- Native Sportarten-Center-UI mit Sportprofilen, Disziplinen, Leistungsdaten, Zielen und KI-Plan-Bereitschaft
- Native Sportprofil-Detail-UI mit Erfahrung, Ziel, Leistungswerten, Pulsdaten, KI-Coach-Freigaben, Coach-Sichtbarkeit, Gesundheitshinweisen und Readiness-Luecken
- Sportprofil-Detail fuehrt direkt in Skills, Endorsements und Empfehlungen
- Native Sportkarten-Center-UI mit Karten-Vorschau, Routen, Tracks, Orten, Live-Track- und Route-planen-Einstieg
- Native Sportkarten-Detail-UI mit Route, Track, GPS-Rechten, Sichtbarkeit, Teamfreigabe und Wegpunkten
- Native Admin-Center-UI mit Nutzer/Rollen, Moderation, Billing, Commerce, System, Metriken und Filterbereichen
- Admin und Einstellungen verlinken den Plattformbetrieb fuer technische Web-App-Routen wie robots.txt, sitemap.xml, Webhooks, Checkout-Rueckkehrseiten, Sprache, Wartung und Systemstatus
- Admin-Center deckt zusaetzlich Reports & Flags, Seller Applications, Badges, Sportarten, Learning Quality und Outfit Admin als native Unterbereiche ab
- Admin-Aktionen fuer neue Admin-Aufgabe, Detail speichern, Nutzer speichern, Supportfall und Kontosperre fuehren in native UI-Aktionsflows
- Native Admin-Detail-UI mit Statuswechsel, Zuweisung, Pruefliste, interner Notiz, Audit, Genehmigen/Ablehnen und Benachrichtigung
- Admin-Detail bietet kontextabhaengige Spezialaktionen fuer Inaktivitaetsnotiz, Rollen, DSGVO-Export, Club-Verifizierung, Moderation, Mark-paid, Refund, Payout, Learning-Quality, Outfit-Reminder und Systemaudit
- Native Rollen/Rechte-Center-UI mit Rollenmatrix, Permissions, Sicherheitsregeln, Audit und Rollenaktionen
- Native Rollen-/Permission-Detail-UI mit Rollenprofil, Permission-Switches, Security Gates, 2FA, Auditpflicht und Audit-Historie
- Native Nutzercenter-UI mit Nutzersuche, Rollen, Status, Profilvollstaendigkeit, Moderation und Verbindungskontext
- Nutzercenter deckt Nutzer anlegen und Inaktivitaetsnotiz als native Admin-Flows ab
- Native Nutzer-Admin-Detail-UI mit Profilfortschritt, Rollenwechsel, Verifizierung, 2FA, Sperre, DSGVO-Export, Moderationsnotiz und Audit
- Native Einstellungscenter-UI mit Profil, Sprache, Datenschutz, Push, Sicherheit, Zahlungen und Kontoaktionen
- Native Plattformbetrieb-UI fuer API-Status, CSRF/Session, Stripe/PayPal-Webhooks, Gast-Checkout, SEO, RSS, Legal-Status, Testmails, Wartung und Audit-Export
- Native Einstellungsdetail-UI mit Profil/Sportprofil, Sprache, Sichtbarkeit, DSGVO-Export, Push, E-Mail, 2FA, Biometrie und Kontoloeschung
- Native Community-Center-UI mit Freunden, Einladungen, Empfehlungen, gemeinsamen Vereinen und Einladungslink
- Native Safety-/Community-Operations-UI fuer Freundschaftstoken, Freund entfernen, Mitfahranfragen, Kontaktfreigabe, Guardian Consent, Elternlogin-Code, Kinderkonto, Widerruf, Maturity-Gates, Safety Check, Reports und Blockieren
- Community-Aktionen fuer Einladungslink, Freundschaft annehmen und Freundschaft ablehnen fuehren in native UI-Aktionsflows
- Community deckt zusaetzlich Freundschaftstoken-Annahme und Freund-entfernen als native UI-Flows ab
- Native Fahrgemeinschaften-UI mit Mitfahrten, Routen, Treffpunkten, Plaetzen, Jugendschutz und Meldefunktionen
- Native Fahrgemeinschafts-Detail-UI mit Angebot/Gesuch, Route, Treffpunkt, Plaetzen, Guardian-Freigabe, Kontaktfreigabe, Teilnehmerstatus und Melden-Aktion
- Fahrgemeinschaften decken Ride-Join, Anfrage annehmen, Anfrage ablehnen, Fahrt verlassen und Teilnehmer-/Kontaktfreigabe als native UI-Flows ab
- Native Medienrichtlinien-UI mit Bildrechten, Upload-Regeln, Guardian Consent, Sichtbarkeit und Moderation
- Medienrichtlinien-Aktionen fuer Freigabe, Regelbearbeitung und Meldung fuehren in native UI-Aktionsflows
- Native Teams-Center-UI mit Teamprofilen, Kader, Rollen, Einladungen, Beitritten, Kalender und Teamchat
- Native Team-Operations-UI fuer Kader hinzufuegen/entfernen, Teamrollen, Captain/Trainer, Einladungen, Join-Requests, Teamkalender, Anwesenheit, Teamdateien, Teamchat und Chatrechte-Sync
- Native Team-Detail-UI mit Profil, Kader, Rollen, Einladungen, Kalender, Dateien, Chat, Join-Requests und Jugendschutz-Gates
- Native Feed-Center-UI mit Beitraegen, Stories, Kommentaren, Reaktionen, Medien und Moderation
- Native Social-Operations-UI fuer Story erstellen/angesehen/Reaktion/Loeschen, Feed Discovery/Trending, Kommentarbaum, Reports, Moderationsflags und Medienfreigabe-Widerruf
- Native Feed-Beitragsdetail-UI mit Medienvorschau, Reaktionen, Kommentarbereich, Medienfreigabe, Moderation und Melden-Aktion
- Feed- und Beitragsaktionen fuer Posten, Medien, Story, Kommentar und Meldung fuehren in native UI-Aktionsflows
- Native Teams-, Rollen/Rechte-, Sportarten-, Nutzer-, Sponsoren- und Blog/Medien-Modulbereiche als Web-App-nahe Mobile-Karten
- Native Badges-/Gamification-Center-UI mit Badge-Filtern, Fortschritt, Regeluebersicht und Badge-Details
- Native Badge-Detail-UI mit Fortschritt, XP, Anforderungen, Profil-Sichtbarkeit, Push, Leaderboard-Opt-in und Achievement-Historie
- Badge-Detail fuehrt direkt in Empfehlungspruefung und Profile-Gamification-Flows
- Native Gamification-Regeln-UI mit XP, Badge-Regeln, Streaks, Leaderboard und Datenschutz-Opt-in
- Native Gamification-Regel-Detail-UI mit Regelprofil, Trigger, Belohnung, Aktivierung, Leaderboard-Opt-in, Audit und Regelhistorie
- Native Abo-/Rechnungscenter-UI mit Plaenen, aktiven Abos, offenen Zahlungen, Banktransfer, Rechnungen und Planwechsel
- Native Billing-Operations-UI fuer Admin-Zahlungen, Banktransfer-Mark-paid, Admin-Invoices, Rechnungsstatus, PDF-Download, Club-/User-Abo-Zuweisung, Kuendigung, Erneuerung, Providerkosten und Providerreferenzen
- Subscription-Checkout-Randzustaende fuer Success, Cancel und Banktransfer sind als native UI erreichbar
- Native Billing-Detail-UI mit Rechnung, Zahlung, Banktransfer, PDF, Zahlungsverlauf, Planwechsel und Kuendigung
- Billing-Aktionen fuer Zahlung erfassen, Rechnung laden, Planwechsel und Kuendigung besitzen native UI-Aktionsflows
- Native Eltern-/Jugendschutzcenter-UI mit Guardian Consent, Elternlogin, Kinderkonten, Zustimmung und Widerruf
- Guardian-Aktionen fuer Zustimmung, Widerruf und Consent-Prüfung fuehren in native UI-Aktionsflows
- Native Guardian-Consent-Detail-UI mit granularen Freigaben, Elterncode, E-Mail-Einladung, Historie und Widerruf
- Guardian-Webflows fuer Elternlogin, Elterncode-Prüfung, Kinderkonto-Erstellung und Token-/Code-Eingabe sind als native UI vorbereitet
- Native Altersfreigaben-/Maturity-UI mit Content-Gates, Altersgruppen, Guardian-Freigaben und Schutzregeln
- Maturity-Aktionen fuer Gate erstellen, Consent pruefen und Regeln bearbeiten fuehren in native UI-Aktionsflows
- Native Outfit-Abo-Center-UI mit Style-Profil, Groesse, Stil, Lieferstatus, Pause/Fortsetzen/Kuendigen und Support-Fall
- Native Outfit-Operations-UI fuer Admin-Flows: bezahlt/unbezahlt, Payment Reminder, Lieferadresse, Kuendigung, Loeschung, Lieferung versendet/zugestellt, Issue, Planverwaltung und Visuals
- Outfit-Checkout-Randzustaende fuer Success und Cancel sind als native UI erreichbar
- Outfit-Aktionen fuer Pause, Fortsetzen, Kuendigung, Tracking, Adresse und Support besitzen native UI-Aktionsflows
- Native Outfit-Lieferdetail-UI mit Lieferstatus, Tracking, Adresse, Supportfall, Rueckgabe, Pausieren und Kuendigung
- Neue Plattformmodule fuer Abos & Rechnungen, Eltern & Jugendschutz und Outfit-Abos in der Modulnavigation
- API-Kontrakt in `lib/core/api_contract.dart` mit Endpunkt-Landkarte fuer alle Hauptmodule und spaetere Laravel-Verknuepfung
- Wiederverwendbarer UI-Aktionsscreen fuer noch backendlose Mobile-Mutationen mit Entwurf, Benachrichtigung und API-Hinweis
- Keine leeren Button-Aktionen mehr in den Flutter-Screens; backendlose Mutationen werden als native UI-Aktionsflows dargestellt

## Naechste UI-Ausbaustufen

- Pro Modul spezialisierte Edit-Screens statt wiederverwendbarer Formularbasis
- Workspace-Detail mit echtem Workspace-Switching, rollenbasierten Kontexten, Einladungstoken und Kontext-Persistenz verbinden
- Auth-Flows mit echten API-Validierungszustaenden, Social Login, Remember-Me und Fehlertexten vertiefen
- Profilseite mit echten Skill-Editoren, Empfehlungen und Sichtbarkeitsmatrix vertiefen
- Profil-/Freundschaftsdetail mit echter Sichtbarkeitsmatrix, Empfehlungsfreigabe, Blockieren/Melden und Profilvorschau per API vertiefen
- Team-Detail mit echten Kaderdaten, Einladungstoken, Rolleneditor, Teamdateien, Kalender-API und Teamchat verbinden
- Feed-Detail mit echten Medienanhaengen, Kommentarbaum, Reaktionsspeicher, Moderationsworkflow und Story-Ablauf per API vertiefen
- Chat-Detail mit echtem Reaktionsspeicher, Typing-Events, Uploads, Teilnehmerrechten, Owner-Transfer und Message-Receipts vertiefen
- Neue-Konversation-Flow mit Empfaengersuche, Blocklisten, Teilnehmerrechten, Owner-Freigabe, Eventverknuepfung und Upload-API verbinden
- Notification-Center mit serverseitigem Read-State, Push-Persistenz, Bulk-Aktionen und Kanal-Preferences vertiefen
- Datei-Manager mit echtem Ordnerbaum, Preview-Layout, Download, Teilen und Vereinsdokument-Verknuepfung vertiefen
- Datei-Preview mit echten Versionen, Berechtigungen, Share-Links, Downloads und Antrag-Verknuepfung per API vertiefen
- Vereins-Mitgliedschaftsverwaltung mit echten Tabellenfiltern, SEPA-Dateigenerierung, DATEV-Konfiguration und Massenaktionen vertiefen
- Event-Detail mit echten Teilnehmerdaten, Kommentarthreads, Wartelistenlogik und Absage-Status per API vertiefen
- Eventverwaltung mit Kalenderansicht, Teilnehmer-Massenaktionen, Reminder-Regeln und Wartelistenworkflow vertiefen
- Event-Admin-Detail mit echter Kalenderansicht, Teilnehmer-Massenaktionen, Reminder-Regeln, Wartelistenworkflow und Eventchat-API verbinden
- Training-Log, Plan-Detail und Trainerfeedback mit echten Plan-Items, Attachments, Push-Reminder und Coach-API verbinden
- Tagesflow mit serverseitigem AthleteDailyFlowService, Erinnerungen und echten Tageszielen verbinden
- Trainer-Cockpit mit Coach-Analytics, Athletenfiltern und Planfreigabe-Workflows vertiefen
- Coach-Aktionsdetail mit echten Athletenlogs, Risiko-Auswertung, Feedback-Persistenz, Push und Planfreigabe-API verbinden
- Ernaehrung mit Lebensmittel-Suche, Barcode-Resultat, Fotoanalyse-Ergebnis und Ziel-Editor vertiefen
- Ernaehrung mit echter Lebensmitteldatenbank, Barcode-Resultat, KI-Fotoanalyse, Ziel-Editor und Verlaufsauswertung vertiefen
- Sportprofil-Detail mit echten Leistungswerten, Zielhistorie, Pulsdaten, Gesundheitshinweisen und KI-Readiness-Prüfung verbinden
- Sportkarte mit echter Kartenkomponente, GPS-Rechte-UI, Track-Aufzeichnung und Routeneditor vertiefen
- Sportkarte mit nativer Kartenkomponente, Standortrechten, Track-Aufzeichnung, GPX-Export und Routeneditor vertiefen
- Marketplace Checkout, Produktvarianten, Anbieterprofil, Retouren und Rechnungsdownload vertiefen
- Marketplace Produktdetail/Checkout mit echten Varianten, Inventar, Providerprofil, Rechnung, Rueckgabe und Payment-Provider vertiefen
- Commerce-Detail mit echten Orderdaten, Produktvarianten, Coupon-Editor, Inventory-Editor, Provider-Payout und Quality-Gate-API verbinden
- Sponsor-Detail mit echten Paketen, Kampagnenreporting, Kontaktpipeline, Vertrag, Logo-Upload und Public-Sponsorprofil verbinden
- Blog-/Medien-Detail mit echtem Rich-Text-Editor, Medienbibliothek, Freigabeprozess, Publikationsworkflow und Review-API verbinden
- Vereins-Cockpit mit echten Vereinsprofil-Editoren, Sichtbarkeitsregeln, Teamaktionen und Beitragsmoderation vertiefen
- Medienrichtlinien mit Medienfreigabe-Workflow, Guardian-Consent-Prüfung und Upload-Policy-Editor vertiefen
- Learning Lektion-Detail, Quiz, Aufgabenabgabe, Kommentare und Kurseditor vertiefen
- Learning mit echter Videowiedergabe, Quiz-Auswertung, Aufgabenabgabe, Kommentaren und Zertifikatsdownload vertiefen
- Admin-Screens in Rollen/Permissions, Commerce, Moderation, Subscriptions und Settings aufteilen
- Admin-Detailflows mit echten Moderationsentscheidungen, Verifizierungsworkflow, Systemaktionen, Auditlog und Benachrichtigungen vertiefen
- Rollen-/Permission-Detail mit echtem Permission-Editor, Auditlog, 2FA-Gates und rollenbasierten API-Gates verbinden
- Nutzer-Admin-Detail mit echten Profilen, Sperrworkflow, Verifizierung, Rollenvergabe, DSGVO-Export und Audit-API verbinden
- Einstellungsdetail mit echter Spracheinstellung, Push-Preferences, Datenschutzexport, Kontoaktionen, Biometrie und Security-API verbinden
- Community mit Blockieren/Melden, Freundschafts-Token, Empfehlungsfreigabe und Profilvorschau vertiefen
- Community mit echten Freundschaftstokens, Empfehlungsalgorithmus, Blocklist, Reports und Moderationsuebergabe vertiefen
- Fahrgemeinschafts-Detail mit echter Routen-/Matching-Logik, Standortrechten, Guardian-Freigaben, Kontaktfreigabe und Moderations-API verbinden
- Badge-Detail mit serverseitigen Gamification-Regeln, Achievement-Historie, XP-Events und Leaderboard-Freigaben verbinden
- Gamification-Regel-Detail mit Rule-Editor, XP-Auswertung, Achievement-Historie, Event-Triggern und Leaderboard-Freigaben verbinden
- Billing mit Checkout-Webredirect, Provider-Portal, Rechnung-PDF und Zahlungsausgleich vertiefen
- Billing-Detail mit echtem Provider-Redirect, PDF-Download, Zahlungsabgleich, Mahnungen und Kuendigungsworkflow vertiefen
- Guardian-Flows mit Token-Annahme, Code-Verifizierung und Elternkonto-Erstellung vertiefen
- Guardian-Consent-Detail mit echten Consent-Tokens, Code-Verifizierung, Widerrufshistorie und granularen API-Rechten vertiefen
- Altersfreigaben mit echten Content-Gates, Maturity-Policies und API-gestuetzter Consent-Prüfung vertiefen
- Outfit-Abos mit Lieferdetails, Adresseditor, Problem-Dialog und Admin-Lieferverwaltung vertiefen
- Outfit-Lieferdetail mit Tracking-API, Rueckgabeprozess, Supportfall, Adresseditor und Admin-Lieferstatus vertiefen
- Public-Detailseiten mit echten Listen/Einzeldatensaetzen fuer Blogartikel, Kursdetail, Produktdetail, Providerprofil und Job-Interesse vertiefen
- Public-Interest mit echter Lead-Erfassung, Kontaktformular-API, Bewerbungs-/Sponsorpipeline und Datenschutzprotokoll vertiefen

## API-Anbindung spaeter

- Auth: Token speichern, Auto-Login, Logout, Register, Passwort-Reset
- Listen: Pagination, Pull-to-refresh, Empty/Error/Loading States
- Mutationen: Optimistische UI fuer Mitgliedsantrag, Rueckzug, Uploads, Chat und Statuswechsel
- Uploads: File Picker/Image Picker an Laravel UploadController anbinden
- Suche: GlobalSearchController oder neue `/api/v1/search` Route nutzen





- Native Safety/Community-Operations-UI fuer Freundschaftseinladungen, Fahrgemeinschaften, Guardian-Consent, Elternlogin, Maturity-Gates und Safety-Reports angelegt und mit API-Kontrakt vorbereitet.


- Native Datei-Operations-UI fuer Uploads, Vereinsdokumente, Mitgliedsantrags-Dokumente, Teamdateien, Share-Links und Dokumentzwecke ergaenzt.


- Native Inbox/Chat-Operations-UI fuer Notifications, Read-State, Push-Preferences, Konversationen, Messages, Typing, Mute, Einladungen und Chat-Moderation ergaenzt.


- Native Gamification-Operations-UI fuer Badges, Admin-Badges, Regelupdates, XP-Ledger, XP-Korrektur, Streaks und Leaderboard-Datenschutz ergaenzt.


- Native Sponsor/Ads-Operations-UI fuer Sponsor-CRUD, Pakete, Commerce-Kampagnen, Kampagnenstatus, Active Ads, Clicktracking, Conversiontracking und Sponsor-Leads ergaenzt.


- Native Sport-Operations-UI fuer Sportarten, Profil-Sportarten, Ziele, Leistungsdaten, Coach-Freigabe, Admin-Sportarten und KI-Readiness ergaenzt.


- Native Legal/Support-Operations-UI fuer Impressum, Datenschutz, AGB, Jugendschutz, Widerruf, Kontakt, Meldungen, Moderation und Cookie-Hinweise ergaenzt.


- Native Account-Operations-UI fuer Login, Registrierung, OAuth, Passwort-Reset, Passwortbestaetigung, 2FA, E-Mail-Verifizierung, Profil, Datenexport, Logout und Kontoloeschung ergaenzt.


- Native Learning-Operations-UI fuer Kurse, Enrollment, Kursabschluss, Lektionen, Quizversuche, Aufgaben, Zertifikate, Public-Katalog, Public-Course-Detail und Learning-Quality ergaenzt.


- Native Marketplace-Operations-UI fuer Public Marketplace, Produktdetail, Cart, Checkout, Success/Cancel/Banktransfer, Orders, Issues, Retouren, Providerprofile und Seller-Bewerbung ergaenzt.


- Native Public-Growth-Operations-UI fuer Public Leads, Kontakt-/Standort-Einreichung, Preise, Planinteresse, Jobs, Jobinteresse, Werbeagentur, Website-Anfragen und Admin-Leadbearbeitung ergaenzt.


- Native Trust-Operations-UI fuer Club-Verifizierungen, Approve/Reject, Moderation, Flags, Reports, Inaktivitaetsnotizen und Operating Contracts ergaenzt.


- Native Search-Operations-UI fuer globale Suche, Vereinsvorschlaege, Personen, Teams, Dateien, Kurse, Events, Produkte, Autocomplete, Result-Routing und Maturity-Filter ergaenzt.


- Native Operations-Hub-UI mit Airmius-Logo, Modulfiltern, Suche und Direktzugriff auf alle Operations-Center ergaenzt; zentrale Texte fuer DE/EN/FR/AR lokalisiert.


- Native UI-Coverage-Screen fuer alle Module aus module_definition.dart ergaenzt; zeigt Modulstatus, Operations-Verknuepfung und API-spaeter-Hinweis im Airmius-Design.


- Native Release-Readiness-Screen fuer UI, Mehrsprachigkeit, Store-Struktur, API-Contract, Safety, Trust, Betrieb und offene Release-Schritte ergaenzt; in Operations Hub und Einstellungen verlinkt.


- Native App-Onboarding-UI fuer erste Nutzung, Sprache, Rolle, Verein-/Team-Kontext, Datenschutz, Guardian-Hinweise, Push, Standort, Dateien, Kamera/Fotos und spaetere Android/iOS-Berechtigungen ergaenzt.

- Native API-Connection-UI fuer Laravel-v1-Kontrakt ergaenzt: Auth, Suche, Workspaces, Vereine, Mitgliedschaft, Teams, Dateien, Chat, Social, Safety, Sport, Learning, Commerce, Billing, Admin, Public und Legal sind als API-Gruppen sichtbar.

- Native Web-Route-Parity-UI ergaenzt: Public, Auth, Club, Social, Sport, Commerce, Admin und Ops-Routen der Laravel-Webversion werden gegen vorhandene Flutter-UI-Flows und offene API-Punkte kartiert.

- Native Airmius-Design-System-UI ergaenzt: Header, Drawer/Bottom-Navigation, Club-Hero, Panels, Pills, Mitgliedsantrag-Formulare, Uploads, Tabellenersatz, Modals, Empty/Loading/Error und Action-Patterns werden als mobile Web-App-nahe Flutter-Bausteine sichtbar.

- Native System-Admin-Operations-UI ergaenzt: Mail-Center, Absender/Testmail, Mail-Resend, Providerkosten, Systemsettings, Feature-Flags, Stripe/PayPal-Webhooks, Checkout-Rueckkehrseiten, Wartungsmodus, SEO/Public-Betrieb, Audit-Export und Betriebsnotizen sind mobil vorbereitet.

- Native Vereinsdokumente-/Policy-UI ergaenzt: Vereine koennen Datenschutz, Satzung, Vereinsregeln, Medien-/Fotoregeln, Beitragsordnung, SEPA-Mandat, Guardian Consent, Widerruf, Uploadpflicht, Downloadrecht, Public-/Mitglieder-/Admin-Sichtbarkeit und Dateimanager-Verknuepfung mobil steuern.

- Native Beitragsregel-UI fuer Vereine ergaenzt: Mitgliedschaftstypen, Beitragshoehen, Zahlungsrhythmus monatlich/4 Monate/halbjaehrlich/jaehrlich/einmalig, Barzahlung, Ueberweisung, SEPA, Online Checkout, automatische Rechnung, Zahlungserinnerungen, Familienrabatt, Probemonat, Aufnahmegebuehr und Mahn-/Pausierungsflows sind mobil vorbereitet.

- Native Vereins-Anfrage-Inbox ergaenzt: neue Mitgliedschaftsanfragen, Rueckzuege, Antragstellerdaten, Wohndaten, Kontaktdaten, Mitgliedschaftstyp, Zahlungswunsch, Dokumentstatus, Admin-Benachrichtigungen, Antragsteller-Statusupdates, Annehmen, Ablehnen, Nachricht, Dateien und Membership-Ops sind mobil vorbereitet.

- Native Vereinssichtbarkeits-UI ergaenzt: Vereine koennen Public-Profil, globale Suche, Adresse, Kontaktwege, Admins, Mitgliederliste, Teams, sichtbare Beitraege, Dokumente, Beitragsinformationen, Beitrittsanfrage-Button, Sponsoren, Galerie und zielgruppenbasierte Preview mobil steuern.

- Native Vereinsprofil-Editor-UI ergaenzt: Stammdaten, Vereinsbeschreibung, Logo, Banner/Hero, Kontaktformular, Admin-Kontakte, Adresse, Trainingsorte, Sportarten, Aufnahmebedingungen, Social Links, Sponsoren-Preview, Club-Verifizierung, Profil-Audit und mobile Public Preview sind vorbereitet.

- Native Vereins-Mitgliederverwaltung ergaenzt: Mitgliederlisten, externe Mitglieder, Suche, Statusfilter, Rollenanzeige, Zahlstatus, Dokumentstatus, Mitgliedsnummer, Teamzuweisung, Import, Massenaktionen, Rollenwechsel, Zahlstatus und Statuswechsel sind als mobile Karten statt Web-Tabellen vorbereitet.

- Native Vereins-Finanzcockpit-UI ergaenzt: Beitraege, Rechnungen, Zahlungen, Banktransfer-Abgleich, SEPA-Mandate, Mahnungen, Zahlungserinnerungen, PDF/CSV/DATEV/SEPA-Exporte, Monatsabschluss und Verknuepfung zu Mitgliedern, Billing und Beitragsregeln sind mobil vorbereitet.

- Native Vereins-Teammanagement-UI ergaenzt: Teamprofile, Kader, Trainer, Captain, Einladungen, Join-Requests, Guardian-Jugendteams, Teamkalender, Teamdateien, Teamchat-Rechte, Chatrechte-Sync und Team-Erstellung sind als mobile Club-Admin-Flows vorbereitet.

- Native Vereins-Event-/Anwesenheits-UI ergaenzt: Kalender, Trainings, Teilnehmerstatus, Warteliste, Erinnerungen, Absagen, Anwesenheit, Check-in, Teamverknuepfung und Eventchat sind als mobile Club-Admin-Flows vorbereitet.

- Native Vereinsrollen-/Rechte-UI ergaenzt: Inhaber, Admins, Trainer, Finanzrollen, sensible Freigaben, Vier-Augen-Regeln und Berechtigungsmatrix sind als mobile Vereins-Security-Flows vorbereitet.

- Native Vereinsberichte-/Auswertungs-UI ergaenzt: Mitgliederentwicklung, Beitraege, Anwesenheit, Vereinsaktivitaet, Vorstandsauswertung und CSV/PDF-Export-Flows sind mobil vorbereitet.

- Native Vereinskommunikations-UI ergaenzt: Push, Chat, E-Mail, Feed, Teamnachrichten, Vorlagen, Freigaben, Empfaengergruppen und Lesestatus sind als mobile Club-Comms-Flows vorbereitet.
- Native Vereinsdatei-Upload-UI ergaenzt: Datenschutz, Regeln, Beitraege, SEPA und Formulare koennen als Upload im Vereins-Dateimanager verknuepft werden; damit wird die Link-only-Grenze der Webfunktion mobil aufgeloest.
- Native Vereins-Setup-UI ergaenzt: Profil, Sichtbarkeit, Beitraege, Rollen, Dokumente und Startfreigabe sind als mobile Admin-Onboarding-Strecke vorbereitet.

- Native Mitgliedsantrags-Formularbuilder-UI ergaenzt: Vereine koennen Personendaten, Wohndaten, Kontaktdaten, Erziehungsberechtigte, Sportdaten, Zahlungsdaten, Pflichtdokumente, Zahlungsart, Intervall und Rueckzugsmoeglichkeit als mobile Admin-Konfiguration steuern.
- Native User-Mitgliedsanfrage-Status-UI ergaenzt: User sehen Anfrage gesendet, Statusverlauf, eingereichte Daten, Dokumente, Rueckfragen, Nachreichen und Zurueckziehen als mobile Self-Service-Flows.


- Native User-Mitgliedsantragsformular-UI ergaenzt: Clubbeitritt mit Mitgliedschaftstyp, Personendaten, Sportdaten, Kontakt, Adresse, Erziehungsberechtigten, Notfallkontakt, Zahlungsart, Zahlungsintervall, IBAN, Dokumentzustimmung, Entwurf und Senden ist als mobile Formularstrecke vorbereitet.

- Native Datenschutz-/Einwilligungscenter-UI ergaenzt: Datenschutz, Vereinsregeln, Zahlungsdaten, Medienfreigabe, Minderjaehrigen-Einwilligung, Marketing-Consent und Dokumentverknuepfung sind mobil steuerbar vorbereitet.
- Native Datenrechte-UI ergaenzt: Datenauskunft, Export, Korrektur, Einschraenkung, Loeschanfrage, Datenumfang und Rueckfrage sind als Self-Service-Flow fuer Laravel-API-Prozesse vorbereitet.

- Native Support-/Helpdesk-UI ergaenzt: Tickets, Rueckfragen, Fehler, Vereinsanliegen, Prioritaeten, Geraetedaten, Screenshot-Option und Supportchat sind als mobile Support-Flows vorbereitet.
- Native Melden-/Moderations-UI ergaenzt: Beitraege, Nutzer, Vereine, Chats, Dateien, Meldegruende, Beweise, Anonymitaet und Ergebnisbenachrichtigung sind als mobile Trust-&-Safety-Flows vorbereitet.

- Native Admin-Mailcenter-UI ergaenzt: Systemmails, Transaktionsmails, Kampagnen, Templates, E-Mail, Push, In-App-Mitteilungen und Adminfreigabe sind als mobile Admin-Flows vorbereitet.
- Native Providerkosten-/Betriebsvertraege-UI ergaenzt: Webmodule OperatingContracts und ProviderCosts sind mit Providerkosten, SLA, Laufzeiten, Renewals, Risiken und Plattformbetriebsaktionen mobil abgebildet.

- Native Admin-Vereinsverifizierungen-UI ergaenzt: Webmodul ClubVerifications ist mit Inhaberpruefung, Dokumenten, Profil, Sichtbarkeit, Risiko, Rueckfragen und Freigabe mobil abgebildet.
- Native Admin-Rechnungen-/Zahlungen-UI ergaenzt: Webmodule Invoices, Payments, SubscriptionInvoices, Subscriptions, BankTransfer und Commerce-Abrechnung sind als mobile Admin-Finanzflaeche vorbereitet.

- Native Guest-Jobs-UI ergaenzt: Webmodul Guest/Jobs ist mit Karriere, Rollen, Arbeitsmodell, Remote/Teilzeit/Studentenoptionen, Bewerbung und Kontakt als mobile Public-Landing vorbereitet.
- Native Guest-Werbeagentur-UI ergaenzt: Webmodul Guest/Werbeagentur ist mit Kampagnen, Sponsoren, Vereinsreichweite, Creatives, Reporting, Paketen und Ads-Ops-Verknuepfung mobil abgebildet.

- Native Guardian-/Elternfreigaben-UI ergaenzt: Webmodule Guardian/Children, Guardian/CreateAccount, Guardian/Login, Guardian/Verify und Auth/GuardianConsent/Pending sind mit Kindkonto, Elternstatus, Vereinsbeitritt, Notfallkontakt, Training, Medien und Datenschutz mobil abgebildet.
- Native API-Token-Manager-UI ergaenzt: Webmodule API/Index und API/Partials/ApiTokenManager sind mit Tokenname, Scopes, Read/Write, Webhooks, Ablauf, Rotation und Laravel-API-Verbindung mobil vorbereitet.

- Native Legal-/Statuscenter-UI ergaenzt: PrivacyPolicy, TermsOfService, Legal/Show, Maintenance und Errors/Forbidden sind als mobile Rechts- und Systemstatus-Flows vorbereitet.
- Native Guest-Pricing-UI ergaenzt: Guest/Pricing ist mit Planvergleich, Zielgruppen, Banktransfer, Testphase, Sponsorenoption, Billing- und Subscription-Verknuepfung mobil abgebildet.

- Native Guest-Marketplace-Buyer-UI ergaenzt: Guest/Marketplace, MarketplaceProviderShow, MarketplaceWishlist, MarketplaceOrderStatus und MarketplaceBankTransfer sind mit Produkten, Providern, Wishlist, Bestellung, Ueberweisung und Support mobil abgebildet.
- Native Guest-E-Learning-/Zertifikats-UI ergaenzt: Guest/E-Learning, LearningCourseShow und LearningCertificateVerify sind mit Kursen, Kursdetail, Zertifikatscode, Gueltigkeit, Vorschau und Lernstart mobil vorbereitet.

- Native Auth-/Account-Zugang-UI ergaenzt: Auth/Login, Register, CompleteProfile, VerifyEmail, ConfirmPassword, ForgotPassword, ResetPassword, Suspended und TwoFactorChallenge sind als mobile Auth-Flows vorbereitet.
- Native Profil-/Sicherheitscenter-UI ergaenzt: Profile/Show und Profil-Partials fuer Profilinformationen, Passwort, TwoFactorAuthentication, LogoutOtherBrowserSessions und DeleteUser sind als mobile Sicherheitsflaeche abgebildet.

- Native Freunde-/Social-Graph-UI ergaenzt: Auth/Dashboard/Friends/Index ist mit Freundschaften, Anfragen, Vorschlaegen, gemeinsamen Vereinen, Blockieren, Melden und Support mobil abgebildet.
- Native Rides-/Fahrgemeinschaften-UI ergaenzt: Auth/Dashboard/Rides/Index ist mit Fahrer, Mitfahrern, Plaetzen, Treffpunkt, Kostenhinweis, Sicherheitsoptionen, Eventverknuepfung und Stornierung mobil vorbereitet.

- Native Admin-Commerce-UI ergaenzt: Auth/Dashboard/Admin/Commerce/Index ist mit Bestellungen, Produkten, Providern, Banktransfer, Refunds, Freigaben und Marketplace-Betrieb mobil abgebildet.
- Native Admin-Subscriptions-/OutfitSubscriptions-UI ergaenzt: Admin/Subscriptions und Admin/OutfitSubscriptions sind mit Abostatus, Outfit-Lieferung, Renewals, Pausen, Kuendigungen, Billing und Support mobil vorbereitet.

- Native Guest-Blog-/Public-Content-UI ergaenzt: Guest/Blog/Index, Guest/Blog/Show, Dashboard/Blogs/Index, Dashboard/Blogs/Categories und Guest/Top-Inhalte sind mit Kategorien, Top-Inhalten, Autoren, Sharing und Public Growth mobil abgebildet.
- Native Guest-Sponsors-/Gamification-UI ergaenzt: Guest/Sponsors und Guest/Gamification sind mit Sponsorlanding, Partnerprofil, Badges, Challenges, Rewards, Ads-Ops und Reporting-Hinweis mobil vorbereitet.

- Native Admin-User-Verwaltungs-UI ergaenzt: Auth/Dashboard/Users/Index, Create, Edit und Profile sind mit Rollen, Sperren, Profilstatus, Sicherheitsaktionen, Moderation und Systemadmin-Verknuepfung mobil abgebildet.
- Native Admin-Plattformeinstellungen-UI ergaenzt: Auth/Dashboard/Admin/Settings/Index ist mit Registrierung, Vereinsanfragen, Wartungsmodus, API, Public Pages, Moderation, Sprache und Systemflags mobil vorbereitet.

## Weitere Webbereiche als native Flutter-Module

- Arbeitsbereiche & Workspaces: mobile Nachbildung fuer Vereins-, Team-, Projekt- und geschuetzte Arbeitsbereiche inklusive Mitglieder, Dateien, Aufgaben und Rollen-Sichtbarkeit.
- Maturity & Media Guidelines: mobile Nachbildung fuer Minderjaehrige, Guardian-Freigaben, Medienfreigaben, Community-Regeln, Moderation und Vereinsreife.

## Guardian und Public-Systemseiten

- Guardian Access Portal: mobile Nachbildung fuer Guardian Login, Kontoerstellung, Verify, Kinderverwaltung und offene Einwilligungen aus den Guardian-Webseiten.
- Public System Pages: mobile Nachbildung fuer Welcome, Wartungsmodus, Forbidden, Datenschutz und Nutzungsbedingungen als App-taugliche Systemseiten.

## Auth-Randfaelle und Guest-Marketplace

- Auth Recovery & Security: mobile Nachbildung fuer Complete Profile, Forgot Password, Reset Password, Confirm Password, Two-Factor Challenge, Verify Email und Suspended.
- Guest Marketplace Flow: mobile Nachbildung fuer oeffentlichen Marketplace, Produktdetail, Anbieterprofil, Wishlist, Warenkorb, Bankueberweisung und Bestellstatus.

## Profilformulare und Public-Growth-Seiten

- Profile Account Forms: mobile Nachbildung fuer Profilinformationen, Passwort aktualisieren, Zwei-Faktor-Authentifizierung, Browser-Sessions und Konto-Loeschung.
- Public Growth Guest Pages: mobile Nachbildung fuer Vereine entdecken, Pricing, Jobs, Werbeagentur, Sponsoren, Top-Inhalte, E-Learning und Gamification.

## Admin-Finanzen und Dashboard-Aktionsseiten

- Admin Finance Contract Suite: mobile Nachbildung fuer Admin Invoices, Payments, Subscription Invoices, Subscriptions, Provider Costs und Operating Contracts.
- Dashboard Action Flows: mobile Nachbildung fuer User Create/Edit, Training Log Create/Show, Commerce BankTransfer, Subscription BankTransfer, Event Show, Team Profile und Training Plan Item Show.

## Content/Blog und Learning/Studio

- Content Blog Editorial Suite: mobile Nachbildung fuer Dashboard Blogs Index, Blog Categories, Blog Show, Guest Blog Index/Show, Top-Inhalte und Content-Moderation.
- Learning Studio Course Suite: mobile Nachbildung fuer MyCourses, Learning Studio, Guest E-Learning, Learning Course Show, Lesson-Detail und Certificate Verify.

## Sports/Training/Wellbeing und Gamification/Access

- Sports Training Wellbeing Suite: mobile Nachbildung fuer Sports Index, SportMap, Training Index, Training Log, Trainer Cockpit, Events, Nutrition und Rides/Carpool.
- Gamification Badges Roles Suite: mobile Nachbildung fuer Badges Index/Show/UserIndex, Gamification Rules, Roles & Permissions und Friends/Social Graph.

## Communication/Files/Notifications und Trust/Moderation/Admin

- Communication Files Notifications Suite: mobile Nachbildung fuer Chat Index/Detail/New, Notifications Index/Detail/Preferences, Files Index, File Preview und Shared File Access.
- Trust Moderation Admin Control Suite: mobile Nachbildung fuer Admin Moderation, Report Cases, Club/Provider Verification, Settings, Mail Center, User Management und Audit/Legal Status.

## Commerce/Subscriptions/Outfit und Club/Membership Lifecycle

- Commerce Subscription Outfit Suite: mobile Nachbildung fuer Commerce Index/ProductShow/Cart/BankTransfer/Checkout Status, Subscriptions/BankTransfer, OutfitSubscriptions und Outfit Delivery.
- Club Membership Lifecycle Suite: mobile Nachbildung fuer Club Profile, Club Cockpit, Teams, Membership Application, Request Inbox, Form Builder, Contribution Rules und Club Documents.

## Auth/API Entry und App Foundation

- Auth API Entry Suite: mobile Nachbildung fuer Welcome, Login, Register, Dashboard Entry, API Index, API Token Manager und API Connection.
- App Shell Localization Quality Suite: mobile Nachbildung fuer Mobile Shell, App Onboarding, Settings Center, Localization Center, Design System, Route Parity und UI Coverage.

## Public Interest/Ads/Sponsor und Finance/Billing/Payments

- Public Interest Ads Sponsor Suite: mobile Nachbildung fuer Public Interest, Public Location Submission, Club Suggestions, Werbeagentur, Ad Campaigns, Sponsor Ads und Sponsor Detail.
- Finance Billing Member Payment Suite: mobile Nachbildung fuer Billing Index/Detail, Checkout Status, Club Finance Cockpit, Member Finance, Finance Record Detail, Beitragszyklen und Zahlungsabgleich.

## Feed/Community/Social und Search/Directory/Discovery

- Feed Community Social Suite: mobile Nachbildung fuer Feed Index, Post Detail, Beitrag erstellen, Community Center, Community Safety, Friends Index, Friend Requests und Social Profile Cards.
- Search Directory Discovery Suite: mobile Nachbildung fuer Global Search, Suchergebnisse, Vereins-/Team-/Nutzerverzeichnisse und User/Club/Team Profile Discovery.

## Web-Parity Release Audit

- Web Parity Release Audit Suite: native Kontroll-UI fuer Web-zu-Flutter-Mapping, API-Readiness, Build-Gates, Design-Gates und finale Route-Parity-Prüfung.
- Bekannte technische Gates: Flutter Analyze/Build wurde noch nicht ausgefuehrt; der naechste harte Qualitaetsschritt ist ein expliziter Compile-/Analyzer-Lauf mit anschliessendem Fixpass.
- Bekannte Backend-Grenze: Alle Suiten sind UI-only und API-ready; echte Daten, Auth-State, Rollen, Persistenz und Uploads werden spaeter ueber Laravel APIs angebunden.

## Web Route Parity Matrix

- Web Route Parity Matrix Suite: native Mapping-UI, die Root/Legal, Auth, Guardian, Dashboard Core, Clubs/Membership, Commerce/Finance, Content/Learning/Growth, Sports/Social/Search und Admin/Trust den jeweiligen Flutter-Suiten zuordnet.
- Offene Abschluss-Gates bleiben bewusst sichtbar: API-Anbindung ueber Laravel, Flutter Analyze/Build und visueller Vergleich mit der mobilen Webversion.

## Mobile State/Form/Error Patterns

- Mobile State Form Error Suite: native UI-Pattern-Suite fuer Loading, Empty, Success, Dynamic Forms, Uploads, Confirm Modals, Validation Errors, Permission Errors und Offline/Retry.
- Diese Patterns gelten quer ueber alle Flutter-Suiten und sind besonders wichtig fuer die spaetere Laravel-API-Anbindung.

## API Binding Readiness und Mobile Web Fidelity

- API Binding Readiness Suite: native Kontroll-UI fuer Laravel API-Vertraege, Auth-State, Rollen, Datenbindung, Uploads, Fehler-Mapping und Optimistic UI.
- Mobile Web Fidelity Accessibility Suite: native Kontroll-UI fuer Airmius Design, Header/Search, Bottom Navigation, Cards, Modals, Scroll UX, Touch Targets, Kontrast und Accessibility.
- Diese Suiten bereiten die naechsten harten Gates vor: Laravel API-Verbindung, visueller Vergleich mit der mobilen Web-App und Accessibility-Prüfung.

## Role-Based App Experience

- Role Based App Experience Suite: native Experience-Mapping-UI fuer Gast, Spieler/Mitglied, Trainer, Guardian, Vereinsadmin, Sponsor/Provider, Plattformadmin und API/Integration.
- Diese Suite beschreibt, welche Flutter-Module je Rolle sichtbar und nutzbar sein sollen; echte Rollen, Rechte und Sichtbarkeit werden spaeter aus Laravel geliefert.

## Store/Device/QA Readiness

- Store Device QA Readiness Suite: native Kontroll-UI fuer App Logo, Splash, Datenschutz, Permissions, Android/iOS Device Matrix, Navigation Smoke Test, Form Smoke Test, Visual Regression Pass und Release Blocker Board.
- Diese Suite beschreibt die naechsten App-spezifischen Pruefgates nach der UI-Paritaet: Compile/Analyze, Geraetetests, Store-Daten, Datenschutz und visueller Vergleich.

## Auth Guard Status Parity

- Auth Guard Status Suite: native UI fuer CompleteProfile, VerifyEmail, Suspended, ConfirmPassword, TwoFactorChallenge, Forgot/ResetPassword, GuardianConsent/Pending, Guardian Login/Verify, Forbidden und Maintenance.
- Store Device QA und Auth Guard Status sind im Operations Hub, Release Readiness und Settings Center verlinkt, damit Web-Zustandsseiten in der Mobile-App nicht verloren gehen.

## Exact Page Flow Parity

- Exact Page Flow Parity Suite: native UI fuer Show-, Create-, Edit-, Detail-, Checkout-, BankTransfer- und Statusseiten aus Dashboard, Commerce, Training, Admin und Public.
- Diese Suite ergaenzt die Modulabdeckung um einzelne Web-Seitenzustaende, damit die Flutter-App nicht nur Hauptbereiche, sondern auch Unterseiten- und Formularflows abbildet.

## Navigation Menu Parity

- Navigation Menu Parity Suite: native UI fuer Airmius-Logo, dunklen Header, globale Suche, Rollen-/Workspace-Kontext, mobile Drawer-Gruppen, Modulbadges und Bottom Navigation.
- Diese Suite orientiert sich an der mobilen Web-App-Navigation und stellt sicher, dass Flutter nicht nur Einzelseiten, sondern auch die vertraute App-Shell uebernimmt.

## Mobile Table Action Parity

- Mobile Table Action Parity Suite: native UI fuer Tabellenersatz aus Vereins-, Admin-, Commerce-, Content- und Sportlisten mit Filterchips, Sortierung, Bulk-Bar, Export-CTA und Action-Sheets.
- Desktop-Tabellen werden in Flutter nicht verkleinert, sondern als mobile Kartenlisten mit Status, Kontext, Rollenlogik und sicheren Danger-Aktionen umgesetzt.

## Media Upload Attachment Parity

- Media Upload Attachment Parity Suite: native UI fuer Dateien, Kamera, Galerie, Scan, Vorschau, Uploadstatus, Datenschutz-/Zweckbindung und Dateimanager-Verknuepfung.
- Abgedeckt sind Vereinsdokumente, Mitgliedsantrag-Anlagen, Profilbilder, Club-Logos, Chat-Anhaenge, Blogmedien, Marketplace-Produktbilder und Trainingsnachweise.

## Modal Sheet Overlay Parity

- Modal Sheet Overlay Parity Suite: native UI fuer Web-Modals, Confirmations, Bottom-Sheets, Filter-Sheets, Datei-Preview, Zahlungsdialoge, Drawer, Fullscreen-Formulare und Ergebnis-Overlays.
- Lange Formulare werden mobil fullscreen mit eigenem Scrollbereich und Sticky Actions gefuehrt; Danger-Aktionen bekommen klare Warnung, Grund, Bestaetigung und Audit-Hinweis.

## Offline Sync Cache Parity

- Offline Sync Cache Parity Suite: native UI fuer Offline-Banner, Cache, lokale Drafts, Sync-Queue, Retry, Upload Resume, API-Fehler, Konflikte und Sync-Historie.
- Diese Suite bereitet die mobile Laravel-API-Anbindung vor, damit lange Formulare, Uploads, Chat, Training, Checkout und Adminentscheidungen klare Zustandskarten bekommen.

## Analytics Chart Dashboard Parity

- Analytics Chart Dashboard Parity Suite: native UI fuer Web-Reports, KPI-Karten, Mini-Charts, Trends, Zeitraumfilter, Export-CTAs und Loading/Empty/Error-Zustaende.
- Abgedeckt sind Vereinsreports, Admin-Betrieb, Commerce, Training, Community, Badges, Learning und Finanzkennzahlen als mobile Dashboard-Karten statt breite Desktop-Diagramme.

## Map Location Route Parity

- Map Location Route Parity Suite: native UI fuer Sportkarte, Routen, Events, Fahrgemeinschaften, Vereinsadresse, Public-Orte, Standortvorschlaege, Permissions, Datenschutz und Offline-Kartenzustaende.
- Standortfreigaben werden mit Zweck, Genauigkeit, Sichtbarkeit, Guardian-Regeln und Widerruf vorbereitet, damit Android/iOS-Berechtigungen spaeter sauber in die Laravel-API-Flows passen.

## Push Notification Deep Link Parity

- Push Notification Deep Link Parity Suite: native UI fuer Push Permission, In-App Inbox, E-Mail-Fallback, Chat-Benachrichtigungen, App-Badges, Ruhezeiten, Mute-Regeln und Deep-Link-Routing.
- Abgedeckt sind Mitgliedschaftsanfragen, Rueckzuege, Dokumentupdates, Chat, Events, Zahlungen, Moderation und Guardian-Freigaben mit direktem Sprungziel in der Flutter-App.

## State Feedback Parity

- State Feedback Parity Suite: native UI fuer Skeleton Loading, Empty, Error, Success, Unauthorized, Rate Limit, Retry, Validation, Cache-Hinweise und API-Feedback.
- Jede spaetere Laravel-API-Route soll in Flutter klare mobile Zustaende bekommen, damit Listen, Formulare, Details, Uploads, Checkouts und Admin-Aktionen nicht leer oder instabil wirken.

## Input Keyboard Accessibility Parity

- Input Keyboard Accessibility Parity Suite: native UI fuer mobile Keyboards, Masken, Pflichtfelder, Validierung, Fokusreihenfolge, Autofill, grosse Touch Targets und Screenreader-Hinweise.
- Abgedeckt sind Mitgliedsantrag, Account, Zahlung, Club Admin, Beitragsregeln und Training Logs, damit Web-Formulare in Flutter wirklich mobil bedienbar werden.

## Brand Theme Token Parity

- Brand Theme Token Parity Suite: native UI fuer Airmius-Logo, dunklen Header, Farben, Panels, Cards, Buttons, Inputs, Status-Pills, Overlays, Bottom Navigation und mobile Dichte.
- Diese Suite sichert die visuelle Wiedererkennbarkeit der mobilen Web-App in Flutter, damit die App nicht wie ein fremdes Standard-Theme wirkt.

## Localization RTL Format Parity

- Localization RTL Format Parity Suite: native UI fuer DE, EN, FR, AR, RTL, Datum, Waehrung, Einheiten, Formular-/API-Fehler, rechtliche Texte, Fallback-Keys und API-Locale-Sync.
- Mehrsprachigkeit wird nicht nur als uebersetzte Labels behandelt, sondern als mobile Format-, Richtungs-, Rechts- und Fehlerzustands-Paritaet vorbereitet.

## Device Permission Privacy Parity

- Device Permission Privacy Parity Suite: native UI fuer Standort, Kamera, Dateien, Fotos, Push, Biometrie, Zweckbindung, Datenschutz, Fallback-Aktionen und Store-ready Permission-Texte.
- Diese Suite verbindet Upload-, Map-, Push- und sensible Account-Flows mit verstaendlichen Berechtigungszustaenden wie erlaubt, einmalig, verweigert und noch nicht gefragt.

## End-to-End Journey Parity

- End-to-End Journey Parity Suite: native UI fuer durchgehende mobile Wege wie Verein beitreten, Antrag rueckziehen, Training planen, Training loggen, Produkt kaufen, Beitrag bezahlen, Guardian-Freigabe und Admin Review.
- Diese Suite verbindet Suche, Profile, Formulare, Uploads, Zahlungen, Benachrichtigungen, Rollen, API-Zustaende und Rueckwege zu App-Journeys statt isolierten Screens.

## Session Security Token Parity

- Session Security Token Parity Suite: native UI fuer Session Restore, Token Refresh, 2FA, Recovery Codes, Device Sessions, API Tokens, Logout, Account-Loeschung und sensible Account-Aktionen.
- Diese Suite bereitet den mobilen Auth-Lebenszyklus fuer die spaetere Laravel/Sanctum-API vor, damit Login, Tokenstatus, Geraete, Sicherheit und Logout nicht nur einzelne Screens bleiben.

## Web-App Full Conversion Control

- Web-App Conversion Control: zentrale native Flutter-Uebersicht fuer Gastbereich, Auth/Konto, Vereine/Mitgliedschaft, Community, Sport/Lernen, Admin/Betrieb und Mobile Qualitaet.
- Der Bereich dient als Produkt-Kompass fuer die weitere 1:1-Paritaet zur mobilen Web-App und markiert die API-Anbindung als naechsten technischen Schritt.

## Web-App Module Completion Suite

- Module Completion Suite: native Uebersicht fuer alle grossen Web-App-Module inklusive Gastseite, Auth/Konto, Vereine/Mitgliedschaft, Community, Sport/Lernen, Admin/Betrieb und Mobile Qualitaet.
- Jede Modulgruppe dokumentiert zentrale User-Flows, damit bei der spaeteren Laravel-API-Anbindung keine Kernfunktion der Webversion verloren geht.

## Club Visibility Rules Suite

- Club Visibility Rules Suite: native UI fuer Vereinsentscheidungen zu Profil-Sichtbarkeit, Mitglieder-/Teamanzeige, Beitragsregeln, Datenschutz, Vereinsregeln, Dokument-Upload und Admin-Benachrichtigungen.
- Die Suite bereitet die spaetere API-Verknuepfung mit Mitgliedschaftsformularen, Dateimanager, Consent-Logik und Vereinsbenachrichtigungen vor.

## Club Membership Requirements Builder

- Membership Requirements Builder: native UI fuer vereinsindividuelle Mitgliedschaftsformulare mit Personendaten, Wohndaten, Kontaktdaten, Erziehungsberechtigten, Notfallkontakt, Sportdaten, Zahlungsdaten und Dokumentpflichten.
- Vereine koennen Zahlungsrhythmus und Zahlungsart vorbereiten: monatlich, 4 Monate, 6 Monate, jaehrlich, Ueberweisung, Bar oder SEPA. Die spaetere Laravel-API speichert daraus Formularschema und Vereinsregeln.

## Club Application Inbox Suite

- Club Application Inbox Suite: native UI fuer Vereinsadmins, um neue Mitgliedschaftsanfragen, Rueckzuege, Dokumente, Rueckfragen, Entscheidungen und Benachrichtigungen zu bearbeiten.
- Die Suite bereitet spaeter Push, E-Mail-Hinweise, In-App-Badges, Audit-Verlauf und Laravel-API-Anbindung fuer Mitgliedschaftsantraege vor.

## Club Dues Payment Rules Suite

- Club Dues Payment Rules Suite: native UI fuer Beitragsgruppen, Zahlungsrhythmus, Zahlungsarten, SEPA-Mandat, Barzahlung, oeffentliche Beitragssichtbarkeit und Beitragsordnungs-Dokumente.
- Die Suite bereitet die spaetere Laravel-API-Anbindung fuer Vereinsbeitraege, Mitgliedschaftsantraege, Rechnungen, Dateimanager und Consent-Dokumente vor.

## Club Member Onboarding Acceptance Suite

- Club Member Onboarding Acceptance Suite: native UI fuer die Phase nach angenommener Mitgliedschaftsanfrage inklusive Willkommensnachricht, Teamzuweisung, Zahlungsstart, Dokumentstatus und digitaler Mitgliedskarte.
- Die Suite schliesst den mobilen Vereinsbeitrittsfluss nach der Adminentscheidung und bereitet spaeter Laravel-API, Benachrichtigungen, Rollen, Zahlungen und Mitgliedskarten vor.

## Club Document Consent File Manager Suite

- Club Document Consent File Manager Suite: native UI fuer Vereinsdokumente als Upload, Dateimanager-Zuordnung, Kategorie, Sichtbarkeit, Versionierung und Zustimmungspflicht im Mitgliedschaftsantrag.
- Die Suite bereitet Datenschutz, Satzung, Beitragsordnung, Gesundheitsnachweise, Consent-Historie und Admin-Benachrichtigungen fuer die spaetere Laravel-API vor.

## Notification Delivery Preferences Suite

- Notification Delivery Preferences Suite: native UI fuer Push, E-Mail, In-App-Badges, Digest-Modus, Ruhezeiten und thematische Zustellung fuer User, Vereinsadmins und Plattformadmins.
- Die Suite bereitet spaeter Laravel-Events, Queue-Jobs, Push-Provider, E-Mail-Templates, In-App-Badges und rollenbasierte Benachrichtigungsregeln vor.

## Global Search Directory Suite

- Global Search Directory Suite: native UI fuer die Suche nach Personen, Vereinen, Teams und oeffentlichen Profilen inklusive Filter, Trefferkarten und direkten Aktionen wie Profil ansehen, Team ansehen oder Mitgliedschaft anfragen.
- Die Suite bereitet spaeter die Laravel-API-Suche fuer Header, mobile Suche, Club-Discovery und Beitrittsnavigation vor, damit User nicht nur Personen, sondern auch Vereine finden koennen.

## Calendar Event RSVP Suite

- Calendar Event RSVP Suite: native UI fuer Vereinskalender, Trainingstermine, Vereinsereignisse, RSVP, Absagen, Anwesenheit, Erinnerungen und Fahrgemeinschaften.
- Die Suite bereitet spaeter Laravel-API, Teamfilter, Push-Erinnerungen, Trainer-Anwesenheit, Mitfahrplaetze und Kalenderstatus fuer Mitglieder und Vereinsadmins vor.

## Team Roster Role Assignment Suite

- Team Roster Role Assignment Suite: native UI fuer Teamkader, Trainer, Captains, Join-Requests, Guardian-Sichtbarkeit, Teamrechte und rollenbasierte Aktionen.
- Die Suite bereitet spaeter Laravel-API, Teammitgliedschaften, Einladungen, Rechte, Dateien, Termine, Chatrechte und Jugend-/Guardian-Sichtbarkeit vor.

## Member Self Service Center Suite

- Member Self Service Center Suite: native UI fuer aktive Mitgliedschaften, offene Anfragen, digitale Mitgliedskarte, Beitragsstatus, Dokumentaufgaben, Vereinsnachrichten und Supportzugang.
- Die Suite bereitet spaeter Laravel-API, Mitgliedschaftsstatus, QR-Karte, Zahlungsinformationen, Dokumentpflichten, Rueckfragen und User-Aufgaben pro Verein vor.

## Support Ticket Service Center Suite

- Support Ticket Service Center Suite: native UI fuer Supporttickets, Themenauswahl, Dateianhaenge, Vereinsadmin-Hinweise, Plattformeskalation, Ticketverlauf und Statuskommunikation.
- Die Suite bereitet spaeter Laravel-API, Support-Nachrichten, interne Notizen, SLA-Status, Dateien, Benachrichtigungen und Rollen-Eskalation fuer User, Vereine und Plattformadmins vor.

## Finance Invoice Receipt Center Suite

- Finance Invoice Receipt Center Suite: native UI fuer offene Beitraege, Rechnungen, Quittungen, Zahlungsstatus, Zahlung erneut versuchen, Mahnhinweise, Rueckerstattungen und Finanzverlauf.
- Die Suite bereitet spaeter Laravel-Finance-API, Beleg-PDFs, SEPA/Ueberweisung/Barzahlung, Vereinsbeitraege, User-Self-Service und Benachrichtigungen bei Faelligkeit vor.

## Feed Community Composer Suite

- Feed Community Composer Suite: native UI fuer Community-Beitraege, Zielgruppen, Medienanhaenge, Kommentare, Pinning, Meldungen und Moderationspruefung.
- Die Suite bereitet spaeter Laravel-API, Medien-Uploads, Rollenrechte, Vereins-/Team-Sichtbarkeit, Reports, Moderatorentscheidungen und Benachrichtigungen fuer den mobilen Feed vor.

## Messaging Conversation Center Suite

- Messaging Conversation Center Suite: native UI fuer private Chats, Vereinsadmin-Kanaele, Teamchats, Support-Konversationen, Dateianhaenge, Lesestatus und Meldungen.
- Die Suite bereitet spaeter Laravel-API, Nachrichtenverlauf, Rollenrechte, Dateimanager-Anhaenge, Push-Benachrichtigungen, Moderation und Support-/Vereinskontext vor.

## Profile Privacy Visibility Suite

- Profile Privacy Visibility Suite: native UI fuer Profilsichtbarkeit, Vereinsmitgliedschaften, Teamanzeige, Nachrichtenrechte, globale Suche, blockierte Nutzer, Datenexport und Loeschanfragen.
- Die Suite bereitet spaeter Laravel-API, DSGVO-Datenrechte, Consent-Historie, Privacy-Audit, Kontaktbeschraenkungen und Suchsichtbarkeit fuer mobile Userprofile vor.

## Admin Moderation Audit Queue Suite

- Admin Moderation Audit Queue Suite: native UI fuer gemeldete Inhalte, gemeldete Nachrichten, Vereinspruefungen, Support-Eskalationen, Datenschutzanfragen, Adminentscheidungen und Audit-Verlauf.
- Die Suite bereitet spaeter Laravel-API, Rollenrechte, Moderatorentscheidungen, Trust-&-Safety-Workflows, DSGVO-Faelle, Eskalationen, SLA-Status und Audit-Logs vor.

## Marketplace Order Fulfillment Suite

- Marketplace Order Fulfillment Suite: native UI fuer Clubshop, Sponsorangebote, Warenkorb, Bestellungen, Abholung, Versand, digitale Gutscheine, Rueckgaben und Bestellstatus.
- Die Suite bereitet spaeter Laravel-API, Produktkatalog, Zahlungen, Rechnungen, Lieferstatus, Abholhinweise, Supporttickets und Benachrichtigungen fuer Commerce-Flows vor.

## Sponsor Campaign Management Suite

- Sponsor Campaign Management Suite: native UI fuer Sponsoren, Kampagnen, Placements, Budgets, Creatives, Freigaben, Club-Targeting, digitale Gutscheine und Reporting.
- Die Suite bereitet spaeter Laravel-API, Ad-Ausspielung, Sponsorangebote, Feed-/Event-/Shop-Platzierungen, Moderationsfreigaben, Budgetwarnungen und Kampagnenstatistiken vor.

## Analytics Reporting KPI Suite

- Analytics Reporting KPI Suite: native UI fuer Mitgliederentwicklung, Finanzen, Community, Events, Support, Moderation, Marketplace, Ads, KPI-Trends, Zeitraumfilter und Report-Export.
- Die Suite bereitet spaeter Laravel-API, Diagrammdaten, CSV/PDF-Export, Rollenrechte, geplante Reports und Verein-/Plattform-Dashboards vor.

## Learning Course Progress Certificate Suite

- Learning Course Progress Certificate Suite: native UI fuer Kurse, Lernpfade, Lektionen, Quiz, Fortschritt, Zertifikate, Nachweise und Downloads.
- Die Suite bereitet spaeter Laravel-API, Einschreibungen, Kursfortschritt, Quizlogik, Rollenrechte, Zertifikats-PDFs, Profilnachweise und Vereins-/Admin-Lernpfade vor.

## Gamification Badge Achievement Suite

- Gamification Badge Achievement Suite: native UI fuer Badges, Rollen, Level, Trainings-Streaks, Lernnachweise, Teamleistungen, Sichtbarkeit und Erfolgsbenachrichtigungen.
- Die Suite bereitet spaeter Laravel-API, Badge-Vergabe, Rollenrechte, Lernzertifikate, Trainingsdaten, Profilanzeige, Benachrichtigungen und Gamification-Regeln vor.

## Health Incident Report Suite

- Health Incident Report Suite: native UI fuer Verletzungen, Gesundheitshinweise, medizinische Notizen, Notfallkontakte, Guardian-Benachrichtigung, Vereinsadmin-Hinweise, Supporttickets und Audit-Verlauf.
- Die Suite bereitet spaeter Laravel-API, Training-/Event-Kontext, Rollenrechte, Datenschutz, Guardian-Flows, Dokumentation und Eskalation fuer Vereins-Safety vor.

## Location Map Facility Suite

- Location Map Facility Suite: native UI fuer Vereinsorte, Trainingsstaetten, Treffpunkte, Routenplanung, Fahrgemeinschaften, Marketplace-Abholung und Standort-Sichtbarkeit.
- Die Suite bereitet spaeter Laravel-API, Kartenintegration, externe Navigation, Rollenrechte, Datenschutz, Event-/Team-Kontext, Pickup-Punkte und Benachrichtigungen vor.

## Club Member Import Export Suite

- Club Member Import Export Suite: native UI fuer CSV-Import, Feldmapping, Dublettenpruefung, externe Kontakte, Einladungen, rollenbasierte Exporte und Audit-Verlauf.
- Die Suite bereitet spaeter Laravel-API, Datei-Uploads, Validierung, Mitglieder-Migration, Datenexport, Datenschutzrechte und Vereinsadmin-Freigaben vor.

## Content Publishing CMS Suite

- Content Publishing CMS Suite: native UI fuer Blog, Vereinsnews, Newsletter, Sponsorinhalte, Entwuerfe, Vorschau, Medien, Freigaben, SEO und Publishing-Status.
- Die Suite bereitet spaeter Laravel-API, Content-Workflows, Medien-Uploads, Push-/Newsletter-Ausspielung, Sponsor-Review, oeffentliche Seiten und Audit-Verlauf vor.

## Role Workspace Switcher Suite

- Role Workspace Switcher Suite: native UI fuer rollenbasierte Arbeitsbereiche wie Mitglied, Vereinsadmin, Trainer, Guardian und Plattformadmin inklusive Startkontext, Schnellwechsel, Rollenbadges und geschuetzten Adminbereichen.
- Die Suite bereitet spaeter Laravel-API, Berechtigungen, Vereins-/Teamkontext, Guardian-Beziehungen, Adminrechte und rollenbasierte Navigation vor.

## App Onboarding Permission Suite

- App Onboarding Permission Suite: native UI fuer Erststart, Sprache, Rollenwahl, Workspace, Datenschutz, Push-Benachrichtigungen, Standort, Dateien und Kamera.
- Die Suite bereitet spaeter Flutter-native Permission-Flows, Device Token, Consent, Profilstart, rollenbasierte Navigation, Store-Erwartungen und Laravel-API-Synchronisierung vor.

## API State Empty Error Suite

- API State Empty Error Suite: native UI fuer Loading, Empty, Error, Retry, Offline, Cache, Skeletons, Fehlertexte und API-Fehlerprotokollierung.
- Die Suite bereitet spaeter Laravel-API-Anbindung, Client-State-Handling, Offline-UX, sichere Fehlermeldungen, Support-Hinweise und Synchronisationsstatus fuer alle Module vor.

- Mobile Form Validation Schema: Pflichtfelder, bedingte Regeln, Eingabemasken, Fehlertexte, Defaultwerte, Dokumentpflichten und Schema-Versionen fuer dynamische Vereinsformulare als mobile Web-App-Paritaet ergänzt.

- Native Store Release Assets: App-Icon, Splash Screen, Store-Screenshots, Store-Texte, Datenschutzangaben, Berechtigungen und Release-Gates fuer Android/iOS als mobile Web-App-Paritaet ergänzt.

- Club Public Profile Preview: Oeffentliche Vereinsseite mit Web-App-Hero, Sichtbarkeitsregeln, Kontakt, Dokumenten, Teams, Admins, Antrag-CTA und Rueckzug-Status als mobile UI ergänzt.

- Laravel API Endpoint Mapping: Web-Routen, Flutter-Screens, Laravel-v1-Endpunkte, Auth-Header, Pagination, Fehlervertrag, Offline-Queue und spaetere Client-Bindings als mobile UI-Matrix ergänzt.

- Subscription Entitlement Feature Gates: Tarife, Vereinslimits, Rollenrechte, Modulzugriff, Feature-Locks, Upgrade-Hinweise und API-ready Entitlement-Payloads als mobile Plattform-UI ergänzt.

- Audit Activity Timeline: Aktivitaeten, Vereinsaktionen, Mitgliedsantraege, Zahlungsereignisse, Rollenwechsel, Security-Events, Exporte und Aufbewahrung als mobile Timeline ergänzt.

- Integration Webhook Provider Center: Mail, Push, Payments, Storage, Maps, AI, Providerstatus, Webhooks, Secrets, Retry-Queue und Laravel Jobs als mobile Betriebs-UI ergänzt.

- System Job Queue Monitor: E-Mail, Push, Upload-Scans, Importe, Exporte, Zahlungen, Webhooks, Retry, Dead Letter, Wartungsmodus und Admin-Aktionen als mobile Queue-Ops-UI ergänzt.

- System Status Incident Center: Systemstatus, Wartungsfenster, Incident-Kommunikation, Service-Health, Nutzerhinweise, Statusseite und Admin-Eskalation als mobile Betriebs-UI ergänzt.

- Draft Autosave Recovery: Autosave, Offline-Drafts, Wiederherstellung, Konfliktvergleich, Datenschutz-Ablauf und sichere Fortsetzung langer mobiler Formulare ergänzt.

- Invitation Access Links: Einladungen, QR-Codes, Zugangslinks, Rollenbindung, Ablauf, Widerruf, Annahmestatus und Audit fuer Mitglieder, Teams, Guardians, Sponsoren und externe Kontakte ergänzt.

- Consent Signature Versioning: Dokumentversionen, Datenschutz, Satzung, Beitragsordnung, SEPA, Medienrechte, Guardian-Freigaben, digitale Bestaetigungen und Audit-Nachweise ergänzt.

- Digital Member Card Check-in: Digitale Mitgliedskarte, QR-Verifikation, Training-Check-in, Offline-Prüfung, Minimaldaten, Token-Rotation und Anwesenheits-Audit als nativer Mobile-Mehrwert ergänzt.

- Deep Link Route Resolver: Einladungen, QR-Codes, Push, E-Mail, Chat, Zahlung, Datei und Event-Links mit Auth-Gate, Workspace-Auswahl, Fallback und Routing-Audit ergänzt.

- Role Home Dashboard Widgets: Rollenbasierte Home-Widgets fuer Mitglied, Vereinsadmin, Trainer, Guardian und Plattformadmin mit Aufgaben, Statuskarten, Schnellaktionen und Priorisierung ergänzt.

- Saved Views Search Alerts: Gespeicherte Filter, Suchalarme, geteilte Listenansichten, Exporte und rollenbasierte Sichtbarkeit fuer Mitglieder, Vereine, Events, Rechnungen, Dateien, Support und Shop ergänzt.

- Cross Module Approval Workflow: Freigaben, Rueckfragen, Entscheidungen, Eskalation und Audit ueber Mitgliedschaft, Dateien, Finanzen, Content, Events und Admin-Aktionen ergänzt.

- Facility Booking Resource Scheduler: Plaetze, Hallen, Raeume, Geraete, Buchungen, Konfliktpruefung, Wartungszeiten, Rollenrechte, Zahlpflicht und Serientermine als mobile Vereinsplanung ergänzt.

- Availability Absence Planning: Verfuegbarkeit, Abwesenheiten, Guardian-Meldungen, Traineruebersicht, Gesundheitsnotizen, Erinnerungen und Anwesenheits-Sync fuer Teams ergänzt.

- Volunteer Shift Task Planner: Helferlisten, Schichten, Aufgaben, Erinnerungen, Rollenregeln, Nachweise, offene Helferbedarfe und Export fuer Events und Vereinsbetrieb ergänzt.

- Added Club Survey Poll Voting suite: mobile UI for club surveys, polls, voting rules, targeting, anonymous responses, quorum, results, export and audit.

- Added Meeting Minutes Decision Log suite: mobile UI for club meetings, agenda, minutes, decisions, linked documents, tasks, voting references and audit trail.

- Added Club Asset Inventory Checkout suite: mobile UI for club equipment, keys, jerseys, QR labels, checkout, return, maintenance, deposits, photos and audit trail.

- Added Sponsor Lead CRM Pipeline suite: mobile UI for sponsor leads, contacts, offers, packages, approvals, linked files, invoices, campaigns, reporting and renewals.

- Added Training Plan Periodization suite: mobile UI for training cycles, coach approvals, load planning, athlete scope, calendar sync, logs, progress and adjustments.

- Added Member Feedback Satisfaction suite: mobile UI for member satisfaction, complaints, ideas, coach feedback, follow-ups, trends, support links and audit handling.

- Added Legal Policy Rollout suite: mobile UI for privacy, statutes, contribution rules, SEPA, media consent, document versions, mandatory confirmations, guardian consent and audit retention.

- Added Mobile Visual Parity Progress Audit suite: mobile UI for remaining product percentages, web-app visual parity, UI coverage, API gaps, store readiness, release gates and overall completion estimate.

- Added Airmius API client foundation and Laravel API Binding Progress suite: core request/response contract for auth, search, clubs, membership applications, withdrawal, upload intents, notifications, conversations, events and invoices, plus visible API progress gates.

- Added API Data Model Repository suite and typed core models: User, Club, MembershipApplication, FileAsset, Event, Invoice, Page plus repository contracts for auth, clubs, memberships, files, events and billing.

- Added API Repository Binding suite and core repository implementations: Auth, Club, Membership, File, Event and Billing repositories now wrap AirmiusApiClient and typed models, moving the product estimate to 48% complete / 52% remaining.

- Added Auth State Token Store suite and core auth state: AirmiusSession, token store abstraction, memory token store, auth phases, restore, sign-in, user refresh, locale update and logout; product estimate now 49% complete / 51% remaining.

- Added Service Container Transport suite and core service container: environment, API client factory, auth state, token store, repository bundle, queued transport, retry/offline handling and static transport; product estimate now 50% complete / 50% remaining.

- Wired Service Container into the real AirmiusApp shell: added AirmiusServicesScope, replaced local signed-in flag with AirmiusAuthState-driven login flow, connected demo static auth transport, and updated product estimate to 51% complete / 49% remaining.

- Bound the first real app screen to repositories: ClubsScreen now loads clubs through AirmiusServicesScope -> RepositoryBundle -> ClubRepository using demo StaticTransport data, with loading/error/retry states; product estimate now 52% complete / 48% remaining.

- Bound membership application sending to repositories: ApplicationScreen now submits through AirmiusServicesScope -> MembershipRepository with loading, error handling and demo StaticTransport response; product estimate now 53% complete / 47% remaining.

- Bound membership application withdrawal to repositories: ClubProfileScreen now calls MembershipRepository.withdraw with demo StaticTransport response and user-facing error handling; product estimate now 54% complete / 46% remaining.

- Added conditional HTTP transport layer: web transport, IO transport, stub fallback, release visibility screen, and registration across Operations Hub, Release Readiness and Settings; product estimate now 56% complete / 44% remaining.

- Made real API transport selectable in AirmiusApp via dart defines: AIRMIUS_USE_HTTP switches from demo StaticTransport to AirmiusHttpTransport, AIRMIUS_API_BASE_URL sets Laravel base URL; product estimate now 57% complete / 43% remaining.

- Added Store Release Configuration suite and core store config: app metadata, bundle ID, store descriptions, support/privacy URLs, permission justifications and release gates are now centralized; product estimate now 58% complete / 42% remaining.

- Prepared native store metadata: AndroidManifest now uses Airmius app name and declares Internet, camera, notifications, location and media/file permissions; iOS Info.plist now has Airmius display/name and camera, photo library and location usage descriptions; product estimate now 59% complete / 41% remaining.

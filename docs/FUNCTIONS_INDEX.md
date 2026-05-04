# Airmius Functions Index

Stand: 2026-05-03

Diese Datei ist eine zentrale Übersicht der vorhandenen Controller-Funktionen. Sie hilft dir schnell zu sehen, wo `index`, `store`, `update`, `destroy` und Sonderfunktionen liegen.

Weitere Produkt- und Business-Planung:

- `docs/BUSINESS_MODEL_USE_CASES.md` - Use Cases, Abo-Pläne, Feature-Matrix, künftige Erlösquellen und fehlende Funktionen.

## Wichtige neue Bereiche

### Mitglieder, Beitritt, Beitrags- und Zahlungsverwaltung

`app/Http/Controllers/ClubMembershipController.php`

- `index(Request $request)` - Verwaltungsseite für Vereinsmitglieder, offene Team-Anfragen, Rechnungen und Zahlungen.
- `updateMember(Request $request, Club $club, User $user)` - Mitgliedsstatus, Mitgliedsnummer, Lizenznummer, Beitrag, Intervall, naechste automatische Rechnung, SEPA-Mandat, Eintritt, Mitgliedschaftsende und Notizen speichern.
- `storeEmailMember(Request $request, Club $club)` - Mitglied per E-Mail erfassen, optional direkt einladen oder mit bestehendem User verknuepfen.
- `importEmailMembers(Request $request, Club $club)` - Mitglieder per Excel/CSV importieren, optional direkt einladen oder verknuepfen.
- `downloadImportTemplate()` - Airmius Excel-Vorlage für den Mitgliederimport herunterladen.
- `inviteEmailMember(Request $request, ClubExternalMember $externalMember)` - externe Mitgliedschaft nachtraeglich einladen oder verknuepfen.
- `acceptExternalInvitation(Request $request, string $token)` - Einladung eines externen Vereinsmitglieds mit einem Airmius-Konto verknuepfen.
- `generateMemberNumber(Club $club, User $user)` - Plattform-Mitgliedsnummer für ein Vereinsmitglied generieren.
- `storeInvoice(Request $request, Club $club, User $user)` - Rechnung für ein Vereinsmitglied erstellen.
- `updateInvoiceStatus(Request $request, Invoice $invoice)` - Rechnung auf offen, bezahlt, überfällig oder storniert setzen.
- `recordPayment(Request $request, Invoice $invoice)` - Zahlung zu einer Rechnung erfassen und Rechnung als bezahlt markieren.
- `sendReminder(Invoice $invoice)` - Zahlungserinnerung/Mahnung als Benachrichtigung senden.
- `updateSepaSettings(Request $request, Club $club)` - SEPA-Glaeubiger-ID und Vereinskonto speichern.
- `exportSepaDebit(Club $club)` - offene Rechnungen mit aktivem Mandat als SEPA-Lastschrift-XML exportieren.
- `buildSepaDebitXml(Club $club, Collection $invoices, Collection $memberships)` - pain.008-XML für Sammellastschriften erzeugen.
- `importBankTransactions(Request $request, Club $club)` - Bank-CSV importieren, Umsaetze speichern und sichere Treffer automatisch als Zahlung verbuchen.
- `confirmBankTransaction(BankTransaction $bankTransaction)` - vorgeschlagenen Bankumsatz manuell als Zahlung bestätigen.
- `findInvoiceMatchForBankTransaction(Club $club, array $transaction)` - offene Rechnungen anhand von Rechnungsnummer, Betrag, IBAN und Name matchen.
- `updateDatevSettings(Request $request, Club $club)` - DATEV-Beraternummer, Mandantennummer sowie SKR42-Konten speichern.
- `exportDatev(Request $request, Club $club)` - bezahlte Zahlungen im Zeitraum als DATEV/SKR42-CSV exportieren.

`app/Console/Commands/SendMembershipAndBillingReminders.php`

- `handle()` - täglicher Prüflauf für bald endende Vereinsmitgliedschaften, Airmius-Abos und fällige/offene Beitragsrechnungen.
- `notifyExpiringClubMemberships(int $days)` - informiert Sportler sowie Vereinsverwaltung über bald endende Mitgliedschaften.
- `notifyDueInvoices(int $days)` - informiert Sportler sowie Vereinsverwaltung über bald fällige und überfällige Beitragszahlungen.
- `notifyExpiringSubscriptions(int $days)` - informiert Vereine und Sportler über bald endende Airmius-Abos/Testphasen.

`app/Console/Commands/GenerateRecurringContributionInvoices.php`

- `handle()` - erstellt täglich fällige automatische Beitragsrechnungen für Pro- und Elite-Vereine.
- `invoiceTitle(string $interval, Carbon $periodStart)` - erzeugt passende Rechnungstitel für Monat, Quartal, Jahr oder einmalige Beiträge.
- `periodEndFor(string $interval, Carbon $periodStart)` - berechnet den Abrechnungszeitraum.
- `nextDateFor(string $interval, Carbon $date)` - setzt den naechsten Rechnungslauf je nach Intervall.
- `nextInvoiceNumber()` - vergibt fortlaufende Rechnungsnummern für automatische Rechnungen.

### Teams und Beitrittsanfragen

`app/Http/Controllers/TeamController.php`

- `index()` - Team- und Vereinsübersicht.
- `store(Request $request)` - Team erstellen.
- `update(Request $request, Team $team)` - Teamdaten aktualisieren.
- `show(Request $request, Team $team)` - Teamprofil anzeigen.
- `updateImages(Request $request, Team $team)` - Teamlogo/Titelbild aktualisieren.
- `destroy(Team $team)` - Team löschen.
- `invite(Request $request, Team $team)` - User per E-Mail oder Konto einladen.
- `acceptInvitation(Request $request, TeamInvitation $invitation)` - Einladung annehmen.
- `acceptInvitationByToken(Request $request, string $token)` - externe Einladung per Token annehmen.
- `requestJoin(Request $request, Team $team)` - Beitrittsanfrage stellen.
- `approveJoinRequest(Request $request, TeamJoinRequest $joinRequest)` - Beitrittsanfrage annehmen.
- `declineJoinRequest(Request $request, TeamJoinRequest $joinRequest)` - Beitrittsanfrage ablehnen.
- `updateMember(Request $request, Team $team, User $user)` - Teamrolle aktualisieren.
- `removeMember(Request $request, Team $team, User $user)` - Mitglied aus Team entfernen.

### Vereine

`app/Http/Controllers/ClubController.php`

- `index()` - Vereinsübersicht.
- `store(Request $request)` - Verein erstellen.
- `show(Request $request, Club $club)` - Vereinsprofil anzeigen.
- `update(Request $request, Club $club)` - Vereinsdaten aktualisieren.
- `updateMember(Request $request, Club $club, User $user)` - Vereinsrolle aktualisieren.
- `updateImages(Request $request, Club $club)` - Vereinslogo/Titelbild aktualisieren.
- `destroy(Club $club)` - Verein löschen.

### Rechtliches

`app/Http/Controllers/LegalPageController.php`

- `imprint()` - Impressum.
- `privacy()` - Datenschutzerklaerung.
- `terms()` - AGB.
- `community()` - Community-Richtlinien.
- `minors()` - Jugendschutz und Elternzustimmung.
- `cookies()` - Cookie-Hinweise.
- `withdrawal()` - Widerrufsbelehrung.
- `reporting()` - Kontakt, Support und Inhalte melden.

### Elternzustimmung und Elternbereich

`app/Http/Controllers/GuardianConsentController.php`

- `show(string $token)` - Zustimmung/Ablehnung per Token anzeigen.
- `approve(Request $request, string $token)` - Elternzustimmung erteilen.
- `reject(Request $request, string $token)` - Elternzustimmung ablehnen.
- `pending(Request $request)` - Kind sieht Warteseite bis Entscheidung.

`app/Http/Controllers/GuardianAccessController.php`

- `create()` - Eltern-Login per E-Mail anzeigen.
- `store(Request $request)` - Zugangscode an Eltern-E-Mail senden.
- `verify()` - Code-Eingabeseite anzeigen.
- `confirm(Request $request)` - Zugangscode prüfen.
- `children(Request $request)` - verknuepfte Kinder anzeigen.
- `createAccount(Request $request)` - optionales Elternkonto-Formular anzeigen.
- `storeAccount(Request $request)` - Elternkonto erstellen oder verknuepfen.
- `revoke(Request $request, User $child)` - Zustimmung für Kind widerrufen.
- `destroy(Request $request)` - Elternbereich-Session beenden.

### Moderation und Meldungen

`app/Http/Controllers/ContentReportController.php`

- `store(Request $request)` - Inhalt melden.

`app/Http/Controllers/ModerationController.php`

- `index()` - Moderationsdashboard.
- `updateFlag(Request $request, ModerationFlag $flag)` - automatische Moderationsmarkierung bearbeiten.
- `updateReport(Request $request, ContentReport $report)` - Nutzermeldung bearbeiten.

### Wartungsmodus und Einstellungen

`app/Http/Controllers/SettingController.php`

- `index()` - Admin-Einstellungen anzeigen.
- `create()` - Standard-Resource-Action.
- `store(Request $request)` - Standard-Resource-Action.
- `show(Setting $setting)` - Standard-Resource-Action.
- `edit(Setting $setting)` - Standard-Resource-Action.
- `update(Request $request)` - Einstellungen speichern, inklusive Wartungsmodus.
- `destroy(Setting $setting)` - Standard-Resource-Action.

### Datenschutz, Inaktivität und Anonymisierung

`app/Console/Commands/ProcessInactiveAccounts.php`

- `handle(UserPrivacyRetentionService $retentionService)` - inaktive Konten prüfen, Warnungen senden, Anonymisierung vormerken und fällige Konten anonymisieren.

`app/Services/UserPrivacyRetentionService.php`

- `anonymize(User $user)` - personenbezogene Profil-, Social-, Sport-App-, Token-, Medien- und Inhaltsdaten eines inaktiven Kontos entfernen oder anonymisieren, Nachweisdaten aber erhalten.

`app/Http/Middleware/TrackUserActivity.php`

- `handle(Request $request, Closure $next)` - `last_seen_at` aktualisieren und geplante Inaktivitäts-Anonymisierung bei erneuter Nutzung zurücksetzen.

### Sportkleidung-Abos

`app/Http/Controllers/OutfitSubscriptionController.php`

- `index(Request $request)` - Nutzer-Dashboard fuer Outfit-Abos, Style-Profil und Lieferuebersicht.
- `updateProfile(Request $request)` - persoenliches Style-Profil speichern.
- `store(Request $request, OutfitSubscriptionPlan $plan)` - Outfit-Abo fuer einen Plan aktivieren.
- `pause(Request $request, OutfitSubscription $subscription)` - eigenes Outfit-Abo pausieren.
- `resume(Request $request, OutfitSubscription $subscription)` - eigenes Outfit-Abo fortsetzen.
- `cancel(Request $request, OutfitSubscription $subscription)` - eigenes Outfit-Abo kuendigen.

`app/Http/Controllers/AdminOutfitSubscriptionPlanController.php`

- `index()` - separate Admin-Verwaltung fuer Outfit-Abo-Plaene, Sponsorenrabatte und Modulstatistik.
- `store(Request $request)` - Outfit-Abo-Plan erstellen.
- `update(Request $request, OutfitSubscriptionPlan $plan)` - Outfit-Abo-Plan bearbeiten.
- `destroy(OutfitSubscriptionPlan $plan)` - Plan loeschen oder bei Historie deaktivieren.

`app/Console/Commands/PrepareOutfitDeliveries.php`

- `handle()` - faellige monatliche Outfit-Lieferungen planen und Nutzer benachrichtigen.

### Airmius Abo-Rechnungen und Zahlungs-E-Mails

`app/Http/Controllers/SubscriptionCheckoutController.php`

- `store(Request $request, SubscriptionPlan $subscriptionPlan)` - Checkout für Stripe, PayPal oder Überweisung starten und Abo-Rechnung erzeugen.
- `markBankTransferPaid(Request $request, PaymentCheckout $checkout)` - Überweisung als bezahlt markieren, Abo aktivieren und Zahlungsbestaetigung senden.
- `stripeWebhook(Request $request)` - Stripe-Zahlungsabschluss verarbeiten.
- `paypalWebhook(Request $request)` - PayPal-Zahlungsabschluss verarbeiten.

`app/Http/Controllers/SubscriptionInvoiceController.php`

- `index()` - Admin-Übersicht für Airmius Abo-Rechnungen.
- `download(Request $request, SubscriptionInvoice $subscriptionInvoice)` - Abo-Rechnung als PDF herunterladen.

`app/Console/Commands/SendSubscriptionInvoiceEmails.php`

- `handle()` - offene/überfällige Airmius Abo-Rechnungen per E-Mail erinnern und intern markieren.

`app/Console/Commands/SendMembershipAndBillingReminders.php`

- `handle()` - Mitgliedschafts-, Beitrags-, Abo-, Trial- und Zahlungserinnerungen täglich versenden.
- `notifyExpiringSubscriptions(int $days)` - Abo-/Trial-Ablaufwarnungen intern und per E-Mail senden.
- `notifySubscriptionPaymentIssues()` - abgelaufene Trials auf `past_due` setzen und Zahlung-offen-E-Mails senden.

`app/Notifications/*`

- `SubscriptionEndingSoon` - E-Mail, wenn Abo oder Testphase bald endet.
- `SubscriptionCancelled` - E-Mail-Bestätigung für Kündigungen.
- `SubscriptionRenewed` - E-Mail-Bestätigung für manuelle Verlängerungen.
- `SubscriptionPaymentIssue` - E-Mail bei abgelaufener Testphase oder offener Abo-Zahlung.

### Commerce, Coupons, Add-ons, Marketplace und Ads

`app/Http/Controllers/AdminCommerceController.php`

- `index()` - Commerce-Zentrale mit Umsatz, Coupons, Add-ons, Marketplace-Produkten und Ads-Kampagnen anzeigen.
- `storeCoupon(Request $request)` - Rabattcode erstellen.
- `updateCoupon(Request $request, SubscriptionCoupon $coupon)` - Rabattcode bearbeiten.
- `storeAddon(Request $request)` - Add-on erstellen.
- `updateAddon(Request $request, SubscriptionAddon $addon)` - Add-on bearbeiten.
- `storeProduct(Request $request)` - Marketplace-Produkt vorbereiten.
- `updateProduct(Request $request, MarketplaceProduct $product)` - Marketplace-Produkt bearbeiten.
- `storeCampaign(Request $request)` - Ads-Kampagne vorbereiten.
- `updateCampaign(Request $request, AdCampaign $campaign)` - Ads-Kampagne bearbeiten.
- `markOrderPaid(CommerceOrder $order)` - offene Commerce-Überweisung manuell als bezahlt markieren.
- `updateWebsiteRequest(Request $request, WebsiteRequest $websiteRequest)` - Website-Anfrage eines Vereins bearbeiten und Status setzen.

`app/Http/Controllers/CommerceCheckoutController.php`

- `index(Request $request)` - Nutzerseite für Add-ons, Marketplace-Produkte und eigene Bestellungen anzeigen.
- `storeAddon(Request $request, SubscriptionAddon $addon)` - Add-on-Bestellung starten.
- `storeProduct(Request $request, MarketplaceProduct $product)` - Marketplace-Bestellung starten und Provision berechnen.
- `storeOwnProduct(Request $request)` - eigenes Marketplace-Angebot als Anbieter einreichen.
- `storeOwnCampaign(Request $request)` - eigene Sponsor-/Ads-Kampagne vorbereiten.
- `storeWebsiteRequest(Request $request)` - Website-Erstellungsanfrage für Verein oder Nutzer erfassen.
- `success(Request $request, CommerceOrder $order)` - Stripe/PayPal Rueckkehr verarbeiten.
- `cancel(Request $request, CommerceOrder $order)` - Commerce-Checkout abbrechen.
- `bankTransfer(Request $request, CommerceOrder $order)` - Überweisungsdaten für Commerce-Bestellung anzeigen.
- `stripeWebhook(Request $request)` - Stripe-Commerce-Webhooks verarbeiten und bezahlte Bestellungen aktivieren.
- `paypalWebhook(Request $request)` - PayPal-Commerce-Webhooks verarbeiten und bezahlte Bestellungen aktivieren.
- `activeAd()` - aktive Ads-Kampagne ausspielen und Impression zaehlen.
- `clickAd(AdCampaign $campaign)` - Klick zaehlen und zur Ziel-URL weiterleiten.
- `activate(CommerceOrder $order)` - bezahlte Commerce-Bestellung aktivieren, inklusive Add-on-Freischaltung und Bestätigungs-E-Mail.
- `sendConfirmationEmail(CommerceOrder $order)` - Commerce-Bestätigungs-E-Mail einmalig versenden.
- `isValidStripeSignature(Request $request)` - Stripe-Webhook-Signatur prüfen.
- `isValidPayPalWebhook(Request $request)` - PayPal-Webhook-Signatur prüfen.

`app/Http/Controllers/SubscriptionPlanController.php`

- `providerPortal(Request $request, UserSubscription $subscription)` - Stripe Provider-Portal für persönliche Nutzer-Abos starten, falls verfügbar.

### Abo-Pläne, Limits und Feature-Gates

`app/Services/PlanFeatureService.php`

- `allows(Club $club, string $feature)` - prueft, ob der aktuelle Vereinsplan eine Funktion erlaubt.
- `ensureAllows(Club $club, string $feature, ?string $message = null)` - bricht mit Hinweis ab, wenn eine Funktion im Plan nicht enthalten ist.
- `canCreateTeam(Club $club)` - prueft das Teamlimit des aktuellen Vereinsplans.
- `ensureCanCreateTeam(Club $club)` - erzwingt das Teamlimit beim Erstellen neuer Teams.
- `canStoreFile(Club $club, ?UploadedFile $file = null)` - prueft Speicherverbrauch gegen den Plan.
- `ensureCanStoreFile(Club $club, ?UploadedFile $file = null)` - erzwingt Speicherlimit bei Uploads.
- `capabilities(Club $club)` - liefert UI-fähige Plan-Fähigkeiten für Buttons und Hinweise.

Enthaltene Feature-Gates:

- `sepa_export` - SEPA-XML-Export ab Pro.
- `bank_reconciliation` - Bankabgleich per CSV-Import ab Pro.
- `datev_export` - DATEV/SKR42-CSV-Export ab Pro.
- `recurring_invoices` - automatische Beitragsrechnungen ab Pro.
- `api` - API-Zugang ab Elite.

### Abo-Pläne und Business-Modell

`app/Http/Controllers/PricingController.php`

- `index()` - oeffentliche Preisseite mit aktiven Airmius Abo-Plänen nach Zielgruppe anzeigen.

`app/Http/Controllers/SubscriptionCheckoutController.php`

- `store(Request $request, SubscriptionPlan $subscriptionPlan)` - Stripe- oder PayPal-Checkout für einen kostenpflichtigen Airmius-Plan starten.
- `success(Request $request, PaymentCheckout $checkout)` - Rueckkehr vom Zahlungsanbieter verarbeiten und bezahlten Checkout synchronisieren.
- `cancel(Request $request, PaymentCheckout $checkout)` - abgebrochenen Checkout markieren.
- `bankTransfer(Request $request, PaymentCheckout $checkout)` - Zahlungsanweisung für Abo-Zahlung per Überweisung anzeigen.
- `markBankTransferPaid(Request $request, PaymentCheckout $checkout)` - offene Überweisung im Adminbereich als bezahlt markieren und Abo aktivieren.
- `stripeWebhook(Request $request)` - Stripe Webhook für abgeschlossene Checkout-Sessions verarbeiten.
- `paypalWebhook(Request $request)` - PayPal Webhook für genehmigte oder abgeschlossene Orders verarbeiten.
- `activateCheckout(PaymentCheckout $checkout)` - bezahlten Checkout als Vereins- oder Nutzer-Abo aktivieren.

`app/Http/Controllers/SubscriptionInvoiceController.php`

- `index(Request $request)` - Admin-Übersicht für Airmius Abo-Rechnungen anzeigen.
- `download(Request $request, SubscriptionInvoice $subscriptionInvoice)` - Airmius Abo-Rechnung als PDF herunterladen.
- `buildSimplePdf(SubscriptionInvoice $invoice)` - einfache PDF-Rechnung ohne externe PDF-Abhaengigkeit erzeugen.

`app/Http/Controllers/SubscriptionPlanController.php`

- `index()` - Admin-Ansicht für Abo-Pläne und Vereins-Zuordnungen anzeigen.
- `update(Request $request, SubscriptionPlan $subscriptionPlan)` - Abo-Plan-Zielgruppe, Preise, Limits, Badge, CTA und Sichtbarkeit bearbeiten.
- `assignClub(Request $request, Club $club)` - Verein einem Abo-Plan zuordnen.
- `assignUser(Request $request, User $user)` - Nutzer-Abo aktualisieren oder Plan wechseln.
- `cancelClub(Request $request, ClubSubscription $subscription)` - Vereins-Abo sofort oder zum Periodenende kündigen.
- `renewClub(Request $request, ClubSubscription $subscription)` - Vereins-Abo manuell verlaengern.
- `cancelUser(Request $request, UserSubscription $subscription)` - Nutzer-Abo sofort oder zum Periodenende kündigen.
- `renewUser(Request $request, UserSubscription $subscription)` - Nutzer-Abo manuell verlaengern.
- `cancelOwnUserSubscription(Request $request, UserSubscription $subscription)` - eigenes Nutzer-Abo zum Periodenende kündigen.

### Social Login und Sport-App-Verknüpfungen

`app/Http/Controllers/SocialAuthController.php`

- `redirect(string $provider)` - Google/Outlook OAuth starten.
- `callback(string $provider)` - Google/Outlook Callback verarbeiten, User erstellen oder verknuepfen und anmelden.

`app/Http/Controllers/SportIntegrationController.php`

- `redirect(Request $request, string $provider)` - Sport-App-Verknüpfung starten oder Anbieter vormerken.
- `callback(Request $request, string $provider)` - Google-Fit-OAuth Callback speichern.
- `sync(Request $request, ConnectedSportAccount $account)` - Synchronisationsstatus aktualisieren.
- `destroy(Request $request, ConnectedSportAccount $account)` - Sport-App-Verknüpfung entfernen.

## Alle Controller-Funktionen

### ActivityController

- `index()`
- `create()`
- `store(Request $request)`
- `show(Activity $activity)`
- `edit(Activity $activity)`
- `update(Request $request, Activity $activity)`
- `destroy(Activity $activity)`

### AttendanceController

- `index()`
- `create()`
- `store(Request $request)`
- `show(Attendance $attendance)`
- `edit(Attendance $attendance)`
- `update(Request $request, Attendance $attendance)`
- `destroy(Attendance $attendance)`

### BadgeController

- `index()`
- `create()`
- `store(Request $request)`
- `show(Badge $badge)`
- `edit(Badge $badge)`
- `update(Request $request, Badge $badge)`
- `destroy(Badge $badge)`

### BlogPostController

- `index(Request $request)`
- `store(Request $request)`
- `update(Request $request, BlogPost $blogPost)`
- `destroy(Request $request, BlogPost $blogPost)`
- `publicIndex()`
- `publicShow(BlogPost $blogPost)`

### ClubController

- `index()`
- `store(Request $request)`
- `show(Request $request, Club $club)`
- `update(Request $request, Club $club)`
- `updateMember(Request $request, Club $club, User $user)` - Mitglieds- und Lizenznummern sowie Beitragsdaten bearbeiten.
- `updateImages(Request $request, Club $club)`
- `destroy(Club $club)`

### ClubMembershipController

- `index(Request $request)`
- `updateMember(Request $request, Club $club, User $user)`
- `storeEmailMember(Request $request, Club $club)`
- `importEmailMembers(Request $request, Club $club)`
- `downloadImportTemplate()`
- `inviteEmailMember(Request $request, ClubExternalMember $externalMember)`
- `acceptExternalInvitation(Request $request, string $token)`
- `storeInvoice(Request $request, Club $club, User $user)`
- `generateMemberNumber(Club $club, User $user)`
- `updateInvoiceStatus(Request $request, Invoice $invoice)`
- `recordPayment(Request $request, Invoice $invoice)`
- `sendReminder(Invoice $invoice)`

### SendMembershipAndBillingReminders

- `handle()`

### ClubUserController

- `index()`
- `create()`
- `store(Request $request)`
- `show(ClubUser $clubUser)`
- `edit(ClubUser $clubUser)`
- `update(Request $request, ClubUser $clubUser)`
- `destroy(ClubUser $clubUser)`

### CommentController

- `index()`
- `create()`
- `store(Request $request, Post $post)`
- `show(Comment $comment)`
- `edit(Comment $comment)`
- `update(Request $request, Comment $comment)`
- `destroy(Comment $comment)`

### ContentReportController

- `store(Request $request)`

### ConversationController

- `index(Request $request)`
- `create()`
- `store(Request $request)`
- `show(Conversation $conversation)`
- `typing(Request $request, Conversation $conversation)`
- `leave(Request $request, Conversation $conversation)`
- `edit(Conversation $conversation)`
- `update(Request $request, Conversation $conversation)`
- `destroy(Conversation $conversation)`

### ConversationUserController

- `index()`
- `create()`
- `store(Request $request)`
- `show(ConversationUser $conversationUser)`
- `edit(ConversationUser $conversationUser)`
- `update(Request $request, ConversationUser $conversationUser)`
- `destroy(ConversationUser $conversationUser)`

### DashboardController

- `index()`
- `create()`
- `store(Request $request)`
- `show(string $id)`
- `edit(string $id)`
- `update(Request $request, string $id)`
- `destroy(string $id)`

### EventController

- `index(Request $request)`
- `show(Event $event)`
- `store(Request $request)`
- `update(Request $request, Event $event)`
- `destroy(Event $event)`
- `join(Request $request, Event $event)`
- `leave(Request $request, Event $event)`
- `comment(Request $request, Event $event)`
- `chat(Event $event)`

### EventParticipantController

- `index()`
- `create()`
- `store(Request $request)`
- `show(EventParticipant $eventParticipant)`
- `edit(EventParticipant $eventParticipant)`
- `update(Request $request, EventParticipant $eventParticipant)`
- `destroy(EventParticipant $eventParticipant)`

### FileController

- `index(Request $request)`
- `store(Request $request)`
- `download(File $file)`
- `destroy(File $file)`
- `share(Request $request, File $file)`

### FolderController

- `index()`
- `create()`
- `store(Request $request)`
- `show(Folder $folder)`
- `edit(Folder $folder)`
- `update(Request $request, Folder $folder)`
- `destroy(Folder $folder)`
- `share(Request $request, Folder $folder)`

### FollowController

- `store(Request $request, User $user)`
- `destroy(Request $request, User $user)`

### FriendController

- `index(Request $request)`
- `store(Request $request)`
- `accept(Request $request, FriendInvitation $invitation)`
- `decline(Request $request, FriendInvitation $invitation)`

### GamificationRuleController

- `index()`
- `update(Request $request)`

### GlobalSearchController

- `__invoke(Request $request)`

### AdminOutfitSubscriptionPlanController

- `index()`
- `store(Request $request)`
- `update(Request $request, OutfitSubscriptionPlan $plan)`
- `destroy(OutfitSubscriptionPlan $plan)`

### GuardianAccessController

- `create()`
- `store(Request $request)`
- `verify()`
- `confirm(Request $request)`
- `children(Request $request)`
- `createAccount(Request $request)`
- `storeAccount(Request $request)`
- `revoke(Request $request, User $child)`
- `destroy(Request $request)`

### GuardianConsentController

- `show(string $token)`
- `approve(Request $request, string $token)`
- `reject(Request $request, string $token)`
- `pending(Request $request)`

### InvoiceController

- `index()`
- `create()`
- `store(Request $request)`
- `show(Invoice $invoice)`
- `edit(Invoice $invoice)`
- `update(Request $request, Invoice $invoice)`
- `destroy(Invoice $invoice)`

### KontaktController

- `index()`
- `create()`
- `store(Request $request)`
- `show(string $id)`
- `edit(string $id)`
- `update(Request $request, string $id)`
- `destroy(string $id)`

### LegalPageController

- `imprint()`
- `privacy()`
- `terms()`
- `community()`
- `minors()`
- `cookies()`
- `withdrawal()`
- `reporting()`

### LikeController

- `togglePost(Post $post)`

### MemberController

- `index(Request $request)`
- `create()`
- `store(Request $request)`
- `show(string $id)`
- `edit(User $user)`
- `update(Request $request, User $user)`
- `destroy(User $user)`

### MessageController

- `store(Request $request)`
- `markAsRead(Request $request)`
- `destroy(Message $message)`
- `react(Request $request, Message $message)`

### ModerationController

- `index()`
- `updateFlag(Request $request, ModerationFlag $flag)`
- `updateReport(Request $request, ContentReport $report)`

### NotificationController

- `index(Request $request)`
- `markAsRead(Request $request, Notification $notification)`
- `markAllAsRead(Request $request)`

### OrganizationJobController

- `store(Request $request, Club $club)`
- `update(Request $request, OrganizationJob $organizationJob)`
- `destroy(Request $request, OrganizationJob $organizationJob)`
- `publicIndex()`

### OutfitSubscriptionController

- `index(Request $request)`
- `updateProfile(Request $request)`
- `store(Request $request, OutfitSubscriptionPlan $plan)`
- `pause(Request $request, OutfitSubscription $subscription)`
- `resume(Request $request, OutfitSubscription $subscription)`
- `cancel(Request $request, OutfitSubscription $subscription)`

### PaymentController

- `index()`
- `create()`
- `store(Request $request)`
- `show(Payment $payment)`
- `edit(Payment $payment)`
- `update(Request $request, Payment $payment)`
- `destroy(Payment $payment)`

### PostController

- `index()`
- `store(Request $request)`
- `update(Request $request, Post $post)`
- `destroy(Post $post)`

### PostHelpfulController

- `toggle(Request $request, Post $post)`

### ProfileGamificationController

- `storeSport(Request $request)`
- `updateSkill(Request $request, UserSportSkill $userSportSkill)`
- `endorse(Request $request, User $user, UserSportSkill $userSportSkill)`
- `recommend(Request $request, User $user)`
- `approveRecommendation(Request $request, ProfileRecommendation $profileRecommendation)`
- `rejectRecommendation(Request $request, ProfileRecommendation $profileRecommendation)`

### PlanFeatureService

- `allows(Club $club, string $feature)`
- `ensureAllows(Club $club, string $feature, ?string $message = null)`
- `canCreateTeam(Club $club)`
- `ensureCanCreateTeam(Club $club)`
- `canStoreFile(Club $club, ?UploadedFile $file = null)`
- `ensureCanStoreFile(Club $club, ?UploadedFile $file = null)`
- `capabilities(Club $club)`

### PublicClubController

- `index(Request $request)`

### PublicMarketplaceController

- `index(Request $request)`

### PricingController

- `index()`

### CommerceCheckoutController

- `index(Request $request)`
- `storeAddon(Request $request, SubscriptionAddon $addon)`
- `storeProduct(Request $request, MarketplaceProduct $product)`
- `storeOwnProduct(Request $request)`
- `storeOwnCampaign(Request $request)`
- `storeWebsiteRequest(Request $request)`
- `success(Request $request, CommerceOrder $order)`
- `cancel(Request $request, CommerceOrder $order)`
- `bankTransfer(Request $request, CommerceOrder $order)`
- `stripeWebhook(Request $request)`
- `paypalWebhook(Request $request)`
- `activeAd()`
- `clickAd(AdCampaign $campaign)`

### RideController

- `index()`
- `store(Request $request)`
- `join(Ride $ride)`
- `leave(Ride $ride)`
- `destroy(Ride $ride)`

### RideUserController

- `index()`
- `create()`
- `store(Request $request)`
- `show(RideUser $rideUser)`
- `edit(RideUser $rideUser)`
- `update(Request $request, RideUser $rideUser)`
- `destroy(RideUser $rideUser)`

### RolePermissionController

- `index(Request $request)`
- `storeRole(Request $request)`
- `updateRole(Request $request, Role $role)`
- `destroyRole(Request $request, Role $role)`
- `storePermission(Request $request)`

### SettingController

- `index()`
- `create()`
- `store(Request $request)`
- `show(Setting $setting)`
- `edit(Setting $setting)`
- `update(Request $request)`
- `destroy(Setting $setting)`

### SportAdminController

- `index()`
- `store(Request $request)`
- `update(Request $request, Sport $sport)`
- `destroy(Sport $sport)`

### SubscriptionCheckoutController

- `store(Request $request, SubscriptionPlan $subscriptionPlan)`
- `success(Request $request, PaymentCheckout $checkout)`
- `cancel(Request $request, PaymentCheckout $checkout)`
- `bankTransfer(Request $request, PaymentCheckout $checkout)`
- `markBankTransferPaid(Request $request, PaymentCheckout $checkout)`
- `stripeWebhook(Request $request)`
- `paypalWebhook(Request $request)`

### SubscriptionInvoiceController

- `index(Request $request)`
- `download(Request $request, SubscriptionInvoice $subscriptionInvoice)`

### SponsorController

- `index(Request $request)`
- `store(Request $request)`
- `update(Request $request, Sponsor $sponsor)`
- `destroy(Request $request, Sponsor $sponsor)`

### SocialAuthController

- `redirect(string $provider)`
- `callback(string $provider)`

### SportIntegrationController

- `redirect(Request $request, string $provider)`
- `callback(Request $request, string $provider)`
- `sync(Request $request, ConnectedSportAccount $account)`
- `destroy(Request $request, ConnectedSportAccount $account)`

### StatisticController

- `index()`
- `create()`
- `store(Request $request)`
- `show(Statistic $statistic)`
- `edit(Statistic $statistic)`
- `update(Request $request, Statistic $statistic)`
- `destroy(Statistic $statistic)`

### SubscriptionPlanController

- `index()`
- `update(Request $request, SubscriptionPlan $subscriptionPlan)`
- `assignClub(Request $request, Club $club)`

### TeamController

- `index()`
- `store(Request $request)`
- `update(Request $request, Team $team)`
- `show(Request $request, Team $team)`
- `updateImages(Request $request, Team $team)`
- `destroy(Team $team)`
- `invite(Request $request, Team $team)`
- `acceptInvitation(Request $request, TeamInvitation $invitation)`
- `acceptInvitationByToken(Request $request, string $token)`
- `requestJoin(Request $request, Team $team)`
- `approveJoinRequest(Request $request, TeamJoinRequest $joinRequest)`
- `declineJoinRequest(Request $request, TeamJoinRequest $joinRequest)`
- `updateMember(Request $request, Team $team, User $user)`
- `removeMember(Request $request, Team $team, User $user)`

### TeamUserController

- `index()`
- `create()`
- `store(Request $request)`
- `show(TeamUser $teamUser)`
- `edit(TeamUser $teamUser)`
- `update(Request $request, TeamUser $teamUser)`
- `destroy(TeamUser $teamUser)`

### UserBadgeController

- `index()`
- `create()`
- `store(Request $request)`
- `show(UserBadge $userBadge)`
- `edit(UserBadge $userBadge)`
- `update(Request $request, UserBadge $userBadge)`
- `destroy(UserBadge $userBadge)`

### UserController

- `index(Request $request)`
- `create()`
- `store(Request $request)`
- `show(Request $request, User $user)`
- `edit(string $id)`
- `update(Request $request, string $id)`
- `destroy(string $id)`

### UserSettingsController

- `index(Request $request)`
- `create()`
- `store(Request $request)`
- `show(string $id)`
- `edit(string $id)`
- `update(Request $request)`
- `destroy(string $id)`

### UserStatusController

- `update(Request $request)`

## Route-Bereiche

Diese Datei listet die Controller-Funktionen. Die konkrete URL-Zuordnung steht in:

- `routes/auth.php` - eingeloggte Plattformfunktionen.
- `routes/admin.php` - Adminfunktionen.
- `routes/guest.php` - oeffentliche Seiten, inklusive `/werbeagentur-fuer-vereine`.
- `routes/web.php` - Web-Routen und rechtliche/Eltern-Routen.
- `routes/api.php` - API-Routen.
- `routes/channels.php` - Broadcast-Kanaele.
- `docs/DATA_PROCESSING_PROVIDERS.md` - AVV/DPA-Nachweise, VVT-Bausteine und Subprocessor-Prüfprotokoll.

Zur technischen Kontrolle kannst du jederzeit ausfuehren:

```bash
php artisan route:list
```

Oder alle Controller-Funktionen neu suchen:

```bash
rg -n "public function" app/Http/Controllers
```


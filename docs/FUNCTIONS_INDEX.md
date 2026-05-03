# Airmius Functions Index

Stand: 2026-05-03

Diese Datei ist eine zentrale Uebersicht der vorhandenen Controller-Funktionen. Sie hilft dir schnell zu sehen, wo `index`, `store`, `update`, `destroy` und Sonderfunktionen liegen.

Weitere Produkt- und Business-Planung:

- `docs/BUSINESS_MODEL_USE_CASES.md` - Use Cases, Abo-Plaene, Feature-Matrix, kuenftige Erloesquellen und fehlende Funktionen.

## Wichtige neue Bereiche

### Mitglieder, Beitritt, Beitrags- und Zahlungsverwaltung

`app/Http/Controllers/ClubMembershipController.php`

- `index(Request $request)` - Verwaltungsseite fuer Vereinsmitglieder, offene Team-Anfragen, Rechnungen und Zahlungen.
- `updateMember(Request $request, Club $club, User $user)` - Mitgliedsstatus, Mitgliedsnummer, Lizenznummer, Beitrag, Intervall, naechste automatische Rechnung, SEPA-Mandat, Eintritt, Mitgliedschaftsende und Notizen speichern.
- `storeEmailMember(Request $request, Club $club)` - Mitglied per E-Mail erfassen, optional direkt einladen oder mit bestehendem User verknuepfen.
- `importEmailMembers(Request $request, Club $club)` - Mitglieder per Excel/CSV importieren, optional direkt einladen oder verknuepfen.
- `downloadImportTemplate()` - Airmius Excel-Vorlage fuer den Mitgliederimport herunterladen.
- `inviteEmailMember(Request $request, ClubExternalMember $externalMember)` - externe Mitgliedschaft nachtraeglich einladen oder verknuepfen.
- `acceptExternalInvitation(Request $request, string $token)` - Einladung eines externen Vereinsmitglieds mit einem Airmius-Konto verknuepfen.
- `generateMemberNumber(Club $club, User $user)` - Plattform-Mitgliedsnummer fuer ein Vereinsmitglied generieren.
- `storeInvoice(Request $request, Club $club, User $user)` - Rechnung fuer ein Vereinsmitglied erstellen.
- `updateInvoiceStatus(Request $request, Invoice $invoice)` - Rechnung auf offen, bezahlt, ueberfaellig oder storniert setzen.
- `recordPayment(Request $request, Invoice $invoice)` - Zahlung zu einer Rechnung erfassen und Rechnung als bezahlt markieren.
- `sendReminder(Invoice $invoice)` - Zahlungserinnerung/Mahnung als Benachrichtigung senden.
- `updateSepaSettings(Request $request, Club $club)` - SEPA-Glaeubiger-ID und Vereinskonto speichern.
- `exportSepaDebit(Club $club)` - offene Rechnungen mit aktivem Mandat als SEPA-Lastschrift-XML exportieren.
- `buildSepaDebitXml(Club $club, Collection $invoices, Collection $memberships)` - pain.008-XML fuer Sammellastschriften erzeugen.
- `importBankTransactions(Request $request, Club $club)` - Bank-CSV importieren, Umsaetze speichern und sichere Treffer automatisch als Zahlung verbuchen.
- `confirmBankTransaction(BankTransaction $bankTransaction)` - vorgeschlagenen Bankumsatz manuell als Zahlung bestaetigen.
- `findInvoiceMatchForBankTransaction(Club $club, array $transaction)` - offene Rechnungen anhand von Rechnungsnummer, Betrag, IBAN und Name matchen.

`app/Console/Commands/SendMembershipAndBillingReminders.php`

- `handle()` - taeglicher Prueflauf fuer bald endende Vereinsmitgliedschaften, Airmius-Abos und faellige/offene Beitragsrechnungen.
- `notifyExpiringClubMemberships(int $days)` - informiert Sportler sowie Vereinsverwaltung ueber bald endende Mitgliedschaften.
- `notifyDueInvoices(int $days)` - informiert Sportler sowie Vereinsverwaltung ueber bald faellige und ueberfaellige Beitragszahlungen.
- `notifyExpiringSubscriptions(int $days)` - informiert Vereine und Sportler ueber bald endende Airmius-Abos/Testphasen.

`app/Console/Commands/GenerateRecurringContributionInvoices.php`

- `handle()` - erstellt taeglich faellige automatische Beitragsrechnungen fuer Pro- und Elite-Vereine.
- `invoiceTitle(string $interval, Carbon $periodStart)` - erzeugt passende Rechnungstitel fuer Monat, Quartal, Jahr oder einmalige Beitraege.
- `periodEndFor(string $interval, Carbon $periodStart)` - berechnet den Abrechnungszeitraum.
- `nextDateFor(string $interval, Carbon $date)` - setzt den naechsten Rechnungslauf je nach Intervall.
- `nextInvoiceNumber()` - vergibt fortlaufende Rechnungsnummern fuer automatische Rechnungen.

### Teams und Beitrittsanfragen

`app/Http/Controllers/TeamController.php`

- `index()` - Team- und Vereinsuebersicht.
- `store(Request $request)` - Team erstellen.
- `update(Request $request, Team $team)` - Teamdaten aktualisieren.
- `show(Request $request, Team $team)` - Teamprofil anzeigen.
- `updateImages(Request $request, Team $team)` - Teamlogo/Titelbild aktualisieren.
- `destroy(Team $team)` - Team loeschen.
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

- `index()` - Vereinsuebersicht.
- `store(Request $request)` - Verein erstellen.
- `show(Request $request, Club $club)` - Vereinsprofil anzeigen.
- `update(Request $request, Club $club)` - Vereinsdaten aktualisieren.
- `updateMember(Request $request, Club $club, User $user)` - Vereinsrolle aktualisieren.
- `updateImages(Request $request, Club $club)` - Vereinslogo/Titelbild aktualisieren.
- `destroy(Club $club)` - Verein loeschen.

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
- `confirm(Request $request)` - Zugangscode pruefen.
- `children(Request $request)` - verknuepfte Kinder anzeigen.
- `createAccount(Request $request)` - optionales Elternkonto-Formular anzeigen.
- `storeAccount(Request $request)` - Elternkonto erstellen oder verknuepfen.
- `revoke(Request $request, User $child)` - Zustimmung fuer Kind widerrufen.
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

### Abo-Plaene, Limits und Feature-Gates

`app/Services/PlanFeatureService.php`

- `allows(Club $club, string $feature)` - prueft, ob der aktuelle Vereinsplan eine Funktion erlaubt.
- `ensureAllows(Club $club, string $feature, ?string $message = null)` - bricht mit Hinweis ab, wenn eine Funktion im Plan nicht enthalten ist.
- `canCreateTeam(Club $club)` - prueft das Teamlimit des aktuellen Vereinsplans.
- `ensureCanCreateTeam(Club $club)` - erzwingt das Teamlimit beim Erstellen neuer Teams.
- `canStoreFile(Club $club, ?UploadedFile $file = null)` - prueft Speicherverbrauch gegen den Plan.
- `ensureCanStoreFile(Club $club, ?UploadedFile $file = null)` - erzwingt Speicherlimit bei Uploads.
- `capabilities(Club $club)` - liefert UI-faehige Plan-Faehigkeiten fuer Buttons und Hinweise.

Enthaltene Feature-Gates:

- `sepa_export` - SEPA-XML-Export ab Pro.
- `bank_reconciliation` - Bankabgleich per CSV-Import ab Pro.
- `recurring_invoices` - automatische Beitragsrechnungen ab Pro.
- `api` - API-Zugang ab Elite.

### Abo-Plaene und Business-Modell

`app/Http/Controllers/PricingController.php`

- `index()` - oeffentliche Preisseite mit aktiven Airmius Abo-Plaenen nach Zielgruppe anzeigen.

`app/Http/Controllers/SubscriptionPlanController.php`

- `index()` - Admin-Ansicht fuer Abo-Plaene und Vereins-Zuordnungen anzeigen.
- `update(Request $request, SubscriptionPlan $subscriptionPlan)` - Abo-Plan-Zielgruppe, Preise, Limits, Badge, CTA und Sichtbarkeit bearbeiten.
- `assignClub(Request $request, Club $club)` - Verein einem Abo-Plan zuordnen.

### Social Login und Sport-App-Verknuepfungen

`app/Http/Controllers/SocialAuthController.php`

- `redirect(string $provider)` - Google/Outlook OAuth starten.
- `callback(string $provider)` - Google/Outlook Callback verarbeiten, User erstellen oder verknuepfen und anmelden.

`app/Http/Controllers/SportIntegrationController.php`

- `redirect(Request $request, string $provider)` - Sport-App-Verknuepfung starten oder Anbieter vormerken.
- `callback(Request $request, string $provider)` - Google-Fit-OAuth Callback speichern.
- `sync(Request $request, ConnectedSportAccount $account)` - Synchronisationsstatus aktualisieren.
- `destroy(Request $request, ConnectedSportAccount $account)` - Sport-App-Verknuepfung entfernen.

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

### PricingController

- `index()`

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
- `routes/guest.php` - oeffentliche Seiten.
- `routes/web.php` - Web-Routen und rechtliche/Eltern-Routen.
- `routes/api.php` - API-Routen.
- `routes/channels.php` - Broadcast-Kanaele.

Zur technischen Kontrolle kannst du jederzeit ausfuehren:

```bash
php artisan route:list
```

Oder alle Controller-Funktionen neu suchen:

```bash
rg -n "public function" app/Http/Controllers
```

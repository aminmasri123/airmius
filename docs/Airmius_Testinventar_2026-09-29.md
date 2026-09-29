# Airmius: technisches Testinventar

Stand: 29.09.2026. Ergänzung zur [rollenbezogenen Testcheckliste](Airmius_Testcheckliste_Web_App_2026-09-29.md).

Dieses Inventar erfasst den registrierten Stand vollständig innerhalb der unten genannten Quellen. Es ist keine Behauptung vollständiger Produktfunktion oder bestandener Tests. Jede Zeile bleibt offen, bis ihr fachlicher Ablauf und ihre Berechtigungen tatsächlich geprüft oder ihre Nichtanwendbarkeit begründet wurden.

## Umfang

| Quelle | Anzahl |
| --- | ---: |
| Ausformulierte Szenarien in der Hauptcheckliste | 444 |
| Registrierte HTTP-Routen, einschließlich Frameworkrouten | 1474 |
| Web-Seiten | 119 |
| Flutter-Screen-Dateien | 348 |
| Backend-Testdateien | 357 |
| Flutter-Testdateien | 45 |
| JavaScript-Testdateien | 17 |
| Geplante Befehle in routes/console.php | 30 |
| Broadcast-Kanäle in routes/channels.php | 6 |

Maschinenlesbarer Snapshot mit vollständigen Action-/Middleware-Angaben: [Inventar-JSON](Airmius_Testinventar_2026-09-29.json). Keine Zugangsdaten oder Nutzerdaten enthalten.

## Prüfprofile für jede Route

Die Abschnittsangaben sind Zuordnungsvorschläge nach URL-Familie, keine geprüfte Eins-zu-eins-Abdeckung. Insbesondere unter Sammelcontrollern jede einzelne Action zusätzlich dem konkreten Fachfall zuordnen. Die vollständige Middleware steht im JSON; Controller/Policies können weitere Rechte prüfen.

| Profil | Schritte | Erwartung |
| --- | --- | --- |
| L: Lesen | 1. Gültiges Objekt als berechtigte Person lesen. 2. Leerzustand, Filter, Pagination und ungültige ID prüfen. 3. Fremdes Objekt und ausgeloggten Zugriff testen. | Korrekte vollständige Daten gemäß Rolle/Sichtbarkeit; keine unerlaubten Inhalte. Bei öffentlicher Route muss erlaubter Gastzugriff funktionieren. |
| S: Schreiben | 1. Exakte Controlleraktion mit gültigen Testdaten ausführen. 2. Neu laden und Nebenwirkungen prüfen. 3. Ungültige Werte, Wiederholung, fremdes Objekt und entzogene Rechte testen. | Vorgesehener Zustandswechsel genau einmal; Fehler verändern keine unbeteiligten Daten. |
| D: Löschen/Widerruf | 1. Fachlichen Bestätigungsweg prüfen. 2. Zulässige Entfernung/Widerruf ausführen. 3. Abhängigkeiten, Wiederholung und fremde IDs testen. | Korrekte Entfernung/Widerruf, Fristen und Sperren; keine fremden Daten betroffen. |
| X: Spezialweg | 1. Vertrag für Webhook, Callback, signierten Link oder technische Route lesen. 2. Gültigkeit/Signatur/Ablauf testen. 3. Manipulation und Replay prüfen. | Nur vertraglich erlaubte Wirkung; öffentlicher technischer Zugang umgeht keine Authentizitätsprüfung. |

Bei jeder Route die tatsächlich registrierten Methoden einzeln prüfen. HEAD muss ohne Antwortkörper korrekt funktionieren. Ein lesend benannter GET-Pfad kann im Einzelfall eine fachliche Wirkung auslösen: Deshalb ist die Action zusätzlich maßgeblich. Bei Dateien Inhalt und Downloadrechte, bei Geldbeträgen Rundung und Idempotenz, bei Statusaktionen jeden erlaubten und verbotenen Übergang prüfen.

## HTTP-Routen

| Offen | ID | Methode und Pfad | Routenname | Controlleraktion | Abschnitte / Profil |
| --- | --- | --- | --- | --- | --- |
| [ ] | R0001 | `GET\|HEAD /` | `welcome` | `Closure` | 40 / L |
| [ ] | R0002 | `GET\|HEAD /abos` | `guest.pricing` | [PricingController@index](../app/Http/Controllers/PricingController.php) | 31 / L |
| [ ] | R0003 | `GET\|HEAD /admin/badges` | `admin.badges.index` | [BadgeController@index](../app/Http/Controllers/BadgeController.php) | 33, 34 / L |
| [ ] | R0004 | `POST /admin/badges` | `admin.badges.store` | [BadgeController@store](../app/Http/Controllers/BadgeController.php) | 33, 34 / S |
| [ ] | R0005 | `PUT /admin/badges/{badge}` | `admin.badges.update` | [BadgeController@update](../app/Http/Controllers/BadgeController.php) | 33, 34 / S |
| [ ] | R0006 | `DELETE /admin/badges/{badge}` | `admin.badges.destroy` | [BadgeController@destroy](../app/Http/Controllers/BadgeController.php) | 33, 34 / D |
| [ ] | R0007 | `GET\|HEAD /admin/blog-categories` | `blog-categories.index` | [BlogCategoryController@index](../app/Http/Controllers/BlogCategoryController.php) | 33, 34 / L |
| [ ] | R0008 | `POST /admin/blog-categories` | `blog-categories.store` | [BlogCategoryController@store](../app/Http/Controllers/BlogCategoryController.php) | 33, 34 / S |
| [ ] | R0009 | `PUT /admin/blog-categories/{blogCategory}` | `blog-categories.update` | [BlogCategoryController@update](../app/Http/Controllers/BlogCategoryController.php) | 33, 34 / S |
| [ ] | R0010 | `DELETE /admin/blog-categories/{blogCategory}` | `blog-categories.destroy` | [BlogCategoryController@destroy](../app/Http/Controllers/BlogCategoryController.php) | 33, 34 / D |
| [ ] | R0011 | `GET\|HEAD /admin/blogs` | `blogs.index` | [BlogPostController@index](../app/Http/Controllers/BlogPostController.php) | 33, 34 / L |
| [ ] | R0012 | `POST /admin/blogs` | `blogs.store` | [BlogPostController@store](../app/Http/Controllers/BlogPostController.php) | 33, 34 / S |
| [ ] | R0013 | `POST /admin/blogs/content-images` | `blogs.content-images.store` | [BlogPostController@uploadContentImage](../app/Http/Controllers/BlogPostController.php) | 33, 34 / S |
| [ ] | R0014 | `PUT /admin/blogs/{blogPost}` | `blogs.update` | [BlogPostController@update](../app/Http/Controllers/BlogPostController.php) | 33, 34 / S |
| [ ] | R0015 | `DELETE /admin/blogs/{blogPost}` | `blogs.destroy` | [BlogPostController@destroy](../app/Http/Controllers/BlogPostController.php) | 33, 34 / D |
| [ ] | R0016 | `GET\|HEAD /admin/blogs/{blogPost}/preview` | `blogs.preview` | [BlogPostController@preview](../app/Http/Controllers/BlogPostController.php) | 33, 34 / L |
| [ ] | R0017 | `POST /admin/club-subscriptions/{subscription}/cancel` | `admin.club-subscriptions.cancel` | [SubscriptionPlanController@cancelClub](../app/Http/Controllers/SubscriptionPlanController.php) | 33, 34 / S |
| [ ] | R0018 | `POST /admin/club-subscriptions/{subscription}/renew` | `admin.club-subscriptions.renew` | [SubscriptionPlanController@renewClub](../app/Http/Controllers/SubscriptionPlanController.php) | 33, 34 / S |
| [ ] | R0019 | `GET\|HEAD /admin/club-verifications` | `admin.club-verifications.index` | [ClubVerificationController@index](../app/Http/Controllers/ClubVerificationController.php) | 33, 34 / L |
| [ ] | R0020 | `PUT /admin/club-verifications/{club}/approve` | `admin.club-verifications.approve` | [ClubVerificationController@approve](../app/Http/Controllers/ClubVerificationController.php) | 33, 34 / S |
| [ ] | R0021 | `PUT /admin/club-verifications/{club}/reject` | `admin.club-verifications.reject` | [ClubVerificationController@reject](../app/Http/Controllers/ClubVerificationController.php) | 33, 34 / S |
| [ ] | R0022 | `GET\|HEAD /admin/clubs` | `admin.clubs.index` | [AdminClubController@index](../app/Http/Controllers/AdminClubController.php) | 33, 34 / L |
| [ ] | R0023 | `DELETE /admin/clubs/{club}` | `admin.clubs.destroy` | [AdminClubController@destroy](../app/Http/Controllers/AdminClubController.php) | 33, 34 / D |
| [ ] | R0024 | `PUT /admin/clubs/{club}/subscription` | `admin.clubs.subscription.update` | [SubscriptionPlanController@assignClub](../app/Http/Controllers/SubscriptionPlanController.php) | 33, 34 / S |
| [ ] | R0025 | `PATCH /admin/clubs/{club}/verification-status` | `admin.clubs.verification-status.update` | [AdminClubController@updateVerificationStatus](../app/Http/Controllers/AdminClubController.php) | 33, 34 / S |
| [ ] | R0026 | `GET\|HEAD /admin/commerce` | `admin.commerce.index` | [AdminCommerceController@index](../app/Http/Controllers/AdminCommerceController.php) | 33, 34 / L |
| [ ] | R0027 | `POST /admin/commerce/addons` | `admin.commerce.addons.store` | [AdminCommerceController@storeAddon](../app/Http/Controllers/AdminCommerceController.php) | 33, 34 / S |
| [ ] | R0028 | `PUT /admin/commerce/addons/{addon}` | `admin.commerce.addons.update` | [AdminCommerceController@updateAddon](../app/Http/Controllers/AdminCommerceController.php) | 33, 34 / S |
| [ ] | R0029 | `POST /admin/commerce/campaigns` | `admin.commerce.campaigns.store` | [AdminCommerceController@storeCampaign](../app/Http/Controllers/AdminCommerceController.php) | 33, 34 / S |
| [ ] | R0030 | `PUT /admin/commerce/campaigns/{campaign}` | `admin.commerce.campaigns.update` | [AdminCommerceController@updateCampaign](../app/Http/Controllers/AdminCommerceController.php) | 33, 34 / S |
| [ ] | R0031 | `PUT /admin/commerce/campaigns/{campaign}/status` | `admin.commerce.campaigns.status.update` | [AdminCommerceController@updateCampaignStatus](../app/Http/Controllers/AdminCommerceController.php) | 33, 34 / S |
| [ ] | R0032 | `POST /admin/commerce/coupons` | `admin.commerce.coupons.store` | [AdminCommerceController@storeCoupon](../app/Http/Controllers/AdminCommerceController.php) | 33, 34 / S |
| [ ] | R0033 | `PUT /admin/commerce/coupons/{coupon}` | `admin.commerce.coupons.update` | [AdminCommerceController@updateCoupon](../app/Http/Controllers/AdminCommerceController.php) | 33, 34 / S |
| [ ] | R0034 | `GET\|HEAD /admin/commerce/export.csv` | `admin.commerce.export.csv` | [AdminCommerceController@exportCsv](../app/Http/Controllers/AdminCommerceController.php) | 33, 34 / L |
| [ ] | R0035 | `PUT /admin/commerce/marketplace-commissions` | `admin.commerce.marketplace-commissions.update` | [AdminCommerceController@updateMarketplaceCommissions](../app/Http/Controllers/AdminCommerceController.php) | 33, 34 / S |
| [ ] | R0036 | `POST /admin/commerce/marketplace-visuals` | `admin.commerce.marketplace-visuals.update` | [AdminCommerceController@updateMarketplaceVisuals](../app/Http/Controllers/AdminCommerceController.php) | 33, 34 / S |
| [ ] | R0037 | `GET\|HEAD /admin/commerce/orders/{order}/credit-note` | `admin.commerce.orders.credit-note` | [AdminCommerceController@downloadCreditNote](../app/Http/Controllers/AdminCommerceController.php) | 33, 34 / L |
| [ ] | R0038 | `GET\|HEAD /admin/commerce/orders/{order}/invoice` | `admin.commerce.orders.invoice` | [AdminCommerceController@downloadInvoice](../app/Http/Controllers/AdminCommerceController.php) | 33, 34 / L |
| [ ] | R0039 | `PUT /admin/commerce/orders/{order}/issue` | `admin.commerce.orders.issue` | [AdminCommerceController@updateOrderIssue](../app/Http/Controllers/AdminCommerceController.php) | 33, 34 / S |
| [ ] | R0040 | `POST /admin/commerce/orders/{order}/issue/reply` | `admin.commerce.orders.issue.reply` | [AdminCommerceController@replyOrderIssue](../app/Http/Controllers/AdminCommerceController.php) | 33, 34 / S |
| [ ] | R0041 | `POST /admin/commerce/orders/{order}/mark-paid` | `admin.commerce.orders.mark-paid` | [AdminCommerceController@markOrderPaid](../app/Http/Controllers/AdminCommerceController.php) | 33, 34 / S |
| [ ] | R0042 | `POST /admin/commerce/orders/{order}/refund` | `admin.commerce.orders.refund` | [AdminCommerceController@refundOrder](../app/Http/Controllers/AdminCommerceController.php) | 33, 34 / S |
| [ ] | R0043 | `PUT /admin/commerce/orders/{order}/shipping` | `admin.commerce.orders.shipping` | [AdminCommerceController@updateShipping](../app/Http/Controllers/AdminCommerceController.php) | 33, 34 / S |
| [ ] | R0044 | `PUT /admin/commerce/payout-profiles/{profile}` | `admin.commerce.payout-profiles.update` | [AdminCommerceController@updatePayoutProfile](../app/Http/Controllers/AdminCommerceController.php) | 33, 34 / S |
| [ ] | R0045 | `PUT /admin/commerce/payouts/{payout}/paid` | `admin.commerce.payouts.paid` | [AdminCommerceController@markPayoutPaid](../app/Http/Controllers/AdminCommerceController.php) | 33, 34 / S |
| [ ] | R0046 | `POST /admin/commerce/payouts/{user}` | `admin.commerce.payouts.create` | [AdminCommerceController@createPayout](../app/Http/Controllers/AdminCommerceController.php) | 33, 34 / S |
| [ ] | R0047 | `POST /admin/commerce/products` | `admin.commerce.products.store` | [AdminCommerceController@storeProduct](../app/Http/Controllers/AdminCommerceController.php) | 33, 34 / S |
| [ ] | R0048 | `PUT /admin/commerce/products/{product}` | `admin.commerce.products.update` | [AdminCommerceController@updateProduct](../app/Http/Controllers/AdminCommerceController.php) | 33, 34 / S |
| [ ] | R0049 | `DELETE /admin/commerce/products/{product}` | `admin.commerce.products.destroy` | [AdminCommerceController@destroyProduct](../app/Http/Controllers/AdminCommerceController.php) | 33, 34 / D |
| [ ] | R0050 | `POST /admin/commerce/products/{product}/stock` | `admin.commerce.products.stock.adjust` | [AdminCommerceController@adjustProductStock](../app/Http/Controllers/AdminCommerceController.php) | 33, 34 / S |
| [ ] | R0051 | `PUT /admin/commerce/returns/{returnRequest}` | `admin.commerce.returns.update` | [AdminCommerceController@updateReturnRequest](../app/Http/Controllers/AdminCommerceController.php) | 33, 34 / S |
| [ ] | R0052 | `PUT /admin/commerce/seller-applications/{sellerApplication}` | `admin.commerce.seller-applications.update` | [AdminCommerceController@updateSellerApplication](../app/Http/Controllers/AdminCommerceController.php) | 33, 34 / S |
| [ ] | R0053 | `PUT /admin/commerce/settings` | `admin.commerce.settings.update` | [AdminCommerceController@updateCommerceSettings](../app/Http/Controllers/AdminCommerceController.php) | 33, 34 / S |
| [ ] | R0054 | `POST /admin/commerce/shipping-rates` | `admin.commerce.shipping-rates.store` | [AdminCommerceController@storeShippingRate](../app/Http/Controllers/AdminCommerceController.php) | 33, 34 / S |
| [ ] | R0055 | `PUT /admin/commerce/shipping-rates/{shippingRate}` | `admin.commerce.shipping-rates.update` | [AdminCommerceController@updateShippingRate](../app/Http/Controllers/AdminCommerceController.php) | 33, 34 / S |
| [ ] | R0056 | `POST /admin/commerce/tax-rates` | `admin.commerce.tax-rates.store` | [AdminCommerceController@storeTaxRate](../app/Http/Controllers/AdminCommerceController.php) | 33, 34 / S |
| [ ] | R0057 | `PUT /admin/commerce/tax-rates/{taxRate}` | `admin.commerce.tax-rates.update` | [AdminCommerceController@updateTaxRate](../app/Http/Controllers/AdminCommerceController.php) | 33, 34 / S |
| [ ] | R0058 | `PUT /admin/commerce/website-requests/{websiteRequest}` | `admin.commerce.website-requests.update` | [AdminCommerceController@updateWebsiteRequest](../app/Http/Controllers/AdminCommerceController.php) | 33, 34 / S |
| [ ] | R0059 | `GET\|HEAD /admin/gamification` | `gamification-rules.index` | [GamificationRuleController@index](../app/Http/Controllers/GamificationRuleController.php) | 33, 34 / L |
| [ ] | R0060 | `PUT /admin/gamification` | `gamification-rules.update` | [GamificationRuleController@update](../app/Http/Controllers/GamificationRuleController.php) | 33, 34 / S |
| [ ] | R0061 | `GET\|HEAD\|POST\|PUT\|PATCH\|DELETE\|OPTIONS /admin/inactive-users` | `admin.inactive-users.index` | `Illuminate\Routing\RedirectController` | 33, 34 / L+S+D+X |
| [ ] | R0062 | `GET\|HEAD /admin/invoices` | `invoices.index` | [InvoiceController@index](../app/Http/Controllers/InvoiceController.php) | 33, 34 / L |
| [ ] | R0063 | `POST /admin/invoices` | `invoices.store` | [InvoiceController@store](../app/Http/Controllers/InvoiceController.php) | 33, 34 / S |
| [ ] | R0064 | `DELETE /admin/invoices/{invoice}` | `invoices.destroy` | [InvoiceController@destroy](../app/Http/Controllers/InvoiceController.php) | 33, 34 / D |
| [ ] | R0065 | `PUT /admin/invoices/{invoice}/status` | `invoices.status.update` | [InvoiceController@updateStatus](../app/Http/Controllers/InvoiceController.php) | 33, 34 / S |
| [ ] | R0066 | `PUT /admin/learning/courses/{course}/quality` | `admin.learning.courses.quality.update` | [LearningStudioController@updateQuality](../app/Http/Controllers/LearningStudioController.php) | 33, 34 / S |
| [ ] | R0067 | `GET\|HEAD /admin/mail-center` | `admin.mail-center.index` | [MailCenterController@index](../app/Http/Controllers/MailCenterController.php) | 33, 34 / L |
| [ ] | R0068 | `PUT /admin/mail-center/preferences` | `admin.mail-center.preferences.update` | [MailCenterController@updatePreferences](../app/Http/Controllers/MailCenterController.php) | 33, 34 / S |
| [ ] | R0069 | `PUT /admin/mail-center/senders/{category}` | `admin.mail-center.senders.update` | [MailCenterController@updateSender](../app/Http/Controllers/MailCenterController.php) | 33, 34 / S |
| [ ] | R0070 | `POST /admin/mail-center/senders/{category}/test` | `admin.mail-center.senders.test` | [MailCenterController@testSender](../app/Http/Controllers/MailCenterController.php) | 33, 34 / S |
| [ ] | R0071 | `POST /admin/mail-center/{mailDelivery}/resend` | `admin.mail-center.resend` | [MailCenterController@resend](../app/Http/Controllers/MailCenterController.php) | 33, 34 / S |
| [ ] | R0072 | `PUT /admin/mail-center/{mailDelivery}/resolve` | `admin.mail-center.resolve` | [MailCenterController@resolve](../app/Http/Controllers/MailCenterController.php) | 33, 34 / S |
| [ ] | R0073 | `GET\|HEAD /admin/media-guidelines` | `admin.media-guidelines.index` | [MediaGuidelineController@index](../app/Http/Controllers/MediaGuidelineController.php) | 33, 34 / L |
| [ ] | R0074 | `POST /admin/media-guidelines/visuals` | `admin.media-guidelines.visuals.update` | [MediaGuidelineController@updateVisuals](../app/Http/Controllers/MediaGuidelineController.php) | 33, 34 / S |
| [ ] | R0075 | `GET\|HEAD /admin/members` | `members.index` | [MemberController@index](../app/Http/Controllers/MemberController.php) | 33, 34 / L |
| [ ] | R0076 | `POST /admin/members` | `members.store` | [MemberController@store](../app/Http/Controllers/MemberController.php) | 33, 34 / S |
| [ ] | R0077 | `GET\|HEAD /admin/members/create` | `members.create` | [MemberController@create](../app/Http/Controllers/MemberController.php) | 33, 34 / L |
| [ ] | R0078 | `PUT /admin/members/{user}` | `members.update` | [MemberController@update](../app/Http/Controllers/MemberController.php) | 33, 34 / S |
| [ ] | R0079 | `DELETE /admin/members/{user}` | `members.destroy` | [MemberController@destroy](../app/Http/Controllers/MemberController.php) | 33, 34 / D |
| [ ] | R0080 | `GET\|HEAD /admin/members/{user}/edit` | `members.edit` | [MemberController@edit](../app/Http/Controllers/MemberController.php) | 33, 34 / L |
| [ ] | R0081 | `POST /admin/members/{user}/inactivity-notice` | `admin.members.inactivity-notice` | [MemberController@sendInactivityNotice](../app/Http/Controllers/MemberController.php) | 33, 34 / S |
| [ ] | R0082 | `GET\|HEAD /admin/moderation` | `admin.moderation.index` | [ModerationController@index](../app/Http/Controllers/ModerationController.php) | 33, 34 / L |
| [ ] | R0083 | `PUT /admin/moderation/flags/{flag}` | `admin.moderation.flags.update` | [ModerationController@updateFlag](../app/Http/Controllers/ModerationController.php) | 33, 34 / S |
| [ ] | R0084 | `PUT /admin/moderation/reports/{report}` | `admin.moderation.reports.update` | [ModerationController@updateReport](../app/Http/Controllers/ModerationController.php) | 33, 34 / S |
| [ ] | R0085 | `PUT /admin/moderation/reports/{report}/appeal` | `admin.moderation.reports.appeal.update` | [ModerationController@decideReportAppeal](../app/Http/Controllers/ModerationController.php) | 33, 34 / S |
| [ ] | R0086 | `GET\|HEAD /admin/operating-contracts` | `admin.operating-contracts.index` | [OperatingContractController@index](../app/Http/Controllers/OperatingContractController.php) | 33, 34 / L |
| [ ] | R0087 | `POST /admin/operating-contracts` | `admin.operating-contracts.store` | [OperatingContractController@store](../app/Http/Controllers/OperatingContractController.php) | 33, 34 / S |
| [ ] | R0088 | `PUT /admin/operating-contracts/{operatingContract}` | `admin.operating-contracts.update` | [OperatingContractController@update](../app/Http/Controllers/OperatingContractController.php) | 33, 34 / S |
| [ ] | R0089 | `DELETE /admin/operating-contracts/{operatingContract}` | `admin.operating-contracts.destroy` | [OperatingContractController@destroy](../app/Http/Controllers/OperatingContractController.php) | 33, 34 / D |
| [ ] | R0090 | `GET\|HEAD /admin/operations` | `admin.operations.index` | [AdminOperationsController@index](../app/Http/Controllers/AdminOperationsController.php) | 33, 34 / L |
| [ ] | R0091 | `GET\|HEAD /admin/operations/data` | `admin.operations.data` | [AdminOperationsController@data](../app/Http/Controllers/AdminOperationsController.php) | 33, 34 / L |
| [ ] | R0092 | `PUT /admin/outfit-deliveries/{delivery}` | `admin.outfit-deliveries.update` | [AdminOutfitSubscriptionPlanController@updateDelivery](../app/Http/Controllers/AdminOutfitSubscriptionPlanController.php) | 33, 34 / S |
| [ ] | R0093 | `DELETE /admin/outfit-deliveries/{delivery}` | `admin.outfit-deliveries.destroy` | [AdminOutfitSubscriptionPlanController@destroyDelivery](../app/Http/Controllers/AdminOutfitSubscriptionPlanController.php) | 33, 34 / D |
| [ ] | R0094 | `POST /admin/outfit-deliveries/{delivery}/delivered` | `admin.outfit-deliveries.delivered` | [AdminOutfitSubscriptionPlanController@markDeliveryDelivered](../app/Http/Controllers/AdminOutfitSubscriptionPlanController.php) | 33, 34 / S |
| [ ] | R0095 | `PUT /admin/outfit-deliveries/{delivery}/issue` | `admin.outfit-deliveries.issue.update` | [AdminOutfitSubscriptionPlanController@updateDeliveryIssue](../app/Http/Controllers/AdminOutfitSubscriptionPlanController.php) | 33, 34 / S |
| [ ] | R0096 | `POST /admin/outfit-deliveries/{delivery}/shipped` | `admin.outfit-deliveries.shipped` | [AdminOutfitSubscriptionPlanController@markDeliveryShipped](../app/Http/Controllers/AdminOutfitSubscriptionPlanController.php) | 33, 34 / S |
| [ ] | R0097 | `POST /admin/outfit-subscription-plans` | `admin.outfit-subscription-plans.store` | [AdminOutfitSubscriptionPlanController@store](../app/Http/Controllers/AdminOutfitSubscriptionPlanController.php) | 33, 34 / S |
| [ ] | R0098 | `PUT /admin/outfit-subscription-plans/{plan}` | `admin.outfit-subscription-plans.update` | [AdminOutfitSubscriptionPlanController@update](../app/Http/Controllers/AdminOutfitSubscriptionPlanController.php) | 33, 34 / S |
| [ ] | R0099 | `DELETE /admin/outfit-subscription-plans/{plan}` | `admin.outfit-subscription-plans.destroy` | [AdminOutfitSubscriptionPlanController@destroy](../app/Http/Controllers/AdminOutfitSubscriptionPlanController.php) | 33, 34 / D |
| [ ] | R0100 | `GET\|HEAD /admin/outfit-subscriptions` | `admin.outfit-subscriptions.index` | [AdminOutfitSubscriptionPlanController@index](../app/Http/Controllers/AdminOutfitSubscriptionPlanController.php) | 33, 34 / L |
| [ ] | R0101 | `POST /admin/outfit-subscriptions/visuals` | `admin.outfit-subscriptions.visuals.update` | [AdminOutfitSubscriptionPlanController@updateVisuals](../app/Http/Controllers/AdminOutfitSubscriptionPlanController.php) | 33, 34 / S |
| [ ] | R0102 | `DELETE /admin/outfit-subscriptions/{subscription}` | `admin.outfit-subscriptions.destroy` | [AdminOutfitSubscriptionPlanController@destroySubscription](../app/Http/Controllers/AdminOutfitSubscriptionPlanController.php) | 33, 34 / D |
| [ ] | R0103 | `POST /admin/outfit-subscriptions/{subscription}/cancel` | `admin.outfit-subscriptions.cancel` | [AdminOutfitSubscriptionPlanController@cancelSubscription](../app/Http/Controllers/AdminOutfitSubscriptionPlanController.php) | 33, 34 / S |
| [ ] | R0104 | `POST /admin/outfit-subscriptions/{subscription}/mark-paid` | `admin.outfit-subscriptions.mark-paid` | [AdminOutfitSubscriptionPlanController@markSubscriptionPaid](../app/Http/Controllers/AdminOutfitSubscriptionPlanController.php) | 33, 34 / S |
| [ ] | R0105 | `POST /admin/outfit-subscriptions/{subscription}/mark-unpaid` | `admin.outfit-subscriptions.mark-unpaid` | [AdminOutfitSubscriptionPlanController@markSubscriptionUnpaid](../app/Http/Controllers/AdminOutfitSubscriptionPlanController.php) | 33, 34 / S |
| [ ] | R0106 | `POST /admin/outfit-subscriptions/{subscription}/payment-reminder` | `admin.outfit-subscriptions.payment-reminder` | [AdminOutfitSubscriptionPlanController@remindPayment](../app/Http/Controllers/AdminOutfitSubscriptionPlanController.php) | 33, 34 / S |
| [ ] | R0107 | `PUT /admin/outfit-subscriptions/{subscription}/shipping-address` | `admin.outfit-subscriptions.shipping-address.update` | [AdminOutfitSubscriptionPlanController@updateShippingAddress](../app/Http/Controllers/AdminOutfitSubscriptionPlanController.php) | 33, 34 / S |
| [ ] | R0108 | `GET\|HEAD /admin/payments` | `payments.index` | [PaymentController@index](../app/Http/Controllers/PaymentController.php) | 33, 34 / L |
| [ ] | R0109 | `POST /admin/payments` | `payments.store` | [PaymentController@store](../app/Http/Controllers/PaymentController.php) | 33, 34 / S |
| [ ] | R0110 | `DELETE /admin/payments/{payment}` | `payments.destroy` | [PaymentController@destroy](../app/Http/Controllers/PaymentController.php) | 33, 34 / D |
| [ ] | R0111 | `POST /admin/permissions` | `permissions.store` | [RolePermissionController@storePermission](../app/Http/Controllers/RolePermissionController.php) | 33, 34 / S |
| [ ] | R0112 | `GET\|HEAD /admin/product-analytics` | `admin.product-analytics.index` | [ProductAnalyticsController@index](../app/Http/Controllers/ProductAnalyticsController.php) | 33, 34 / L |
| [ ] | R0113 | `GET\|HEAD /admin/provider-costs` | `admin.provider-costs.index` | [ProviderCostController@index](../app/Http/Controllers/ProviderCostController.php) | 33, 34 / L |
| [ ] | R0114 | `POST /admin/roles` | `roles.store` | [RolePermissionController@storeRole](../app/Http/Controllers/RolePermissionController.php) | 33, 34 / S |
| [ ] | R0115 | `GET\|HEAD /admin/roles-permissions` | `roles-permissions.index` | [RolePermissionController@index](../app/Http/Controllers/RolePermissionController.php) | 33, 34 / L |
| [ ] | R0116 | `PUT /admin/roles/{role}` | `roles.update` | [RolePermissionController@updateRole](../app/Http/Controllers/RolePermissionController.php) | 33, 34 / S |
| [ ] | R0117 | `DELETE /admin/roles/{role}` | `roles.destroy` | [RolePermissionController@destroyRole](../app/Http/Controllers/RolePermissionController.php) | 33, 34 / D |
| [ ] | R0118 | `GET\|HEAD /admin/settings` | `admin.settings.index` | [SettingController@index](../app/Http/Controllers/SettingController.php) | 33, 34 / L |
| [ ] | R0119 | `PUT /admin/settings` | `admin.settings.update` | [SettingController@update](../app/Http/Controllers/SettingController.php) | 33, 34 / S |
| [ ] | R0120 | `GET\|HEAD /admin/sponsors` | `sponsors.index` | [SponsorController@index](../app/Http/Controllers/SponsorController.php) | 33, 34 / L |
| [ ] | R0121 | `POST /admin/sponsors` | `sponsors.store` | [SponsorController@store](../app/Http/Controllers/SponsorController.php) | 33, 34 / S |
| [ ] | R0122 | `PUT /admin/sponsors/{sponsor}` | `sponsors.update` | [SponsorController@update](../app/Http/Controllers/SponsorController.php) | 33, 34 / S |
| [ ] | R0123 | `DELETE /admin/sponsors/{sponsor}` | `sponsors.destroy` | [SponsorController@destroy](../app/Http/Controllers/SponsorController.php) | 33, 34 / D |
| [ ] | R0124 | `GET\|HEAD /admin/sports` | `admin.sports.index` | [SportAdminController@index](../app/Http/Controllers/SportAdminController.php) | 33, 34 / L |
| [ ] | R0125 | `POST /admin/sports` | `admin.sports.store` | [SportAdminController@store](../app/Http/Controllers/SportAdminController.php) | 33, 34 / S |
| [ ] | R0126 | `PUT /admin/sports/{sport}` | `admin.sports.update` | [SportAdminController@update](../app/Http/Controllers/SportAdminController.php) | 33, 34 / S |
| [ ] | R0127 | `DELETE /admin/sports/{sport}` | `admin.sports.destroy` | [SportAdminController@destroy](../app/Http/Controllers/SportAdminController.php) | 33, 34 / D |
| [ ] | R0128 | `POST /admin/subscription-checkouts/{checkout}/mark-paid` | `admin.subscription-checkouts.mark-paid` | [SubscriptionCheckoutController@markBankTransferPaid](../app/Http/Controllers/SubscriptionCheckoutController.php) | 33, 34 / S |
| [ ] | R0129 | `GET\|HEAD /admin/subscription-invoices` | `admin.subscription-invoices.index` | [SubscriptionInvoiceController@index](../app/Http/Controllers/SubscriptionInvoiceController.php) | 33, 34 / L |
| [ ] | R0130 | `GET\|HEAD /admin/subscription-invoices/{subscriptionInvoice}/download` | `admin.subscription-invoices.download` | [SubscriptionInvoiceController@download](../app/Http/Controllers/SubscriptionInvoiceController.php) | 33, 34 / L |
| [ ] | R0131 | `POST /admin/subscription-invoices/{subscriptionInvoice}/mark-paid` | `admin.subscription-invoices.mark-paid` | [SubscriptionInvoiceController@markPaid](../app/Http/Controllers/SubscriptionInvoiceController.php) | 33, 34 / S |
| [ ] | R0132 | `PUT /admin/subscription-plans/{subscriptionPlan}` | `admin.subscription-plans.update` | [SubscriptionPlanController@update](../app/Http/Controllers/SubscriptionPlanController.php) | 33, 34 / S |
| [ ] | R0133 | `GET\|HEAD /admin/subscriptions` | `admin.subscriptions.index` | [SubscriptionPlanController@index](../app/Http/Controllers/SubscriptionPlanController.php) | 33, 34 / L |
| [ ] | R0134 | `GET\|HEAD /admin/trainer-applications` | `admin.trainer-applications.index` | [AccountRoleApplicationController@index](../app/Http/Controllers/AccountRoleApplicationController.php) | 33, 34 / L |
| [ ] | R0135 | `PUT /admin/trainer-applications/{application}/approve` | `admin.trainer-applications.approve` | [AccountRoleApplicationController@approve](../app/Http/Controllers/AccountRoleApplicationController.php) | 33, 34 / S |
| [ ] | R0136 | `PUT /admin/trainer-applications/{application}/reject` | `admin.trainer-applications.reject` | [AccountRoleApplicationController@reject](../app/Http/Controllers/AccountRoleApplicationController.php) | 33, 34 / S |
| [ ] | R0137 | `POST /admin/user-subscriptions/{subscription}/cancel` | `admin.user-subscriptions.cancel` | [SubscriptionPlanController@cancelUser](../app/Http/Controllers/SubscriptionPlanController.php) | 33, 34 / S |
| [ ] | R0138 | `POST /admin/user-subscriptions/{subscription}/renew` | `admin.user-subscriptions.renew` | [SubscriptionPlanController@renewUser](../app/Http/Controllers/SubscriptionPlanController.php) | 33, 34 / S |
| [ ] | R0139 | `GET\|HEAD /admin/users` | `users.index` | [MemberController@index](../app/Http/Controllers/MemberController.php) | 33, 34 / L |
| [ ] | R0140 | `POST /admin/users` | `users.store` | [MemberController@store](../app/Http/Controllers/MemberController.php) | 33, 34 / S |
| [ ] | R0141 | `GET\|HEAD /admin/users/create` | `users.create` | [MemberController@create](../app/Http/Controllers/MemberController.php) | 33, 34 / L |
| [ ] | R0142 | `PUT /admin/users/{user}` | `users.update` | [MemberController@update](../app/Http/Controllers/MemberController.php) | 33, 34 / S |
| [ ] | R0143 | `DELETE /admin/users/{user}` | `users.destroy` | [MemberController@destroy](../app/Http/Controllers/MemberController.php) | 33, 34 / D |
| [ ] | R0144 | `GET\|HEAD /admin/users/{user}/edit` | `users.edit` | [MemberController@edit](../app/Http/Controllers/MemberController.php) | 33, 34 / L |
| [ ] | R0145 | `PUT /admin/users/{user}/subscription` | `admin.users.subscription.update` | [SubscriptionPlanController@assignUser](../app/Http/Controllers/SubscriptionPlanController.php) | 33, 34 / S |
| [ ] | R0146 | `GET\|HEAD /ads/active` | `ads.active` | [CommerceCheckoutController@activeAd](../app/Http/Controllers/CommerceCheckoutController.php) | 29 / L |
| [ ] | R0147 | `GET\|HEAD /ads/{campaign}/click` | `ads.click` | [CommerceCheckoutController@clickAd](../app/Http/Controllers/CommerceCheckoutController.php) | 29 / L |
| [ ] | R0148 | `POST /ads/{campaign}/conversion` | `ads.conversion` | [CommerceCheckoutController@conversionAd](../app/Http/Controllers/CommerceCheckoutController.php) | 29 / S |
| [ ] | R0149 | `GET\|HEAD /agb` | `terms.show` | [LegalPageController@terms](../app/Http/Controllers/LegalPageController.php) | 40 / L |
| [ ] | R0150 | `GET\|HEAD /api/user` | `(ohne Name)` | `Closure` | 02, 01 / L |
| [ ] | R0151 | `DELETE /api/v1/account` | `api.v1.account.destroy` | [AccountDeletionController@destroy](../app/Http/Controllers/Api/V1/AccountDeletionController.php) | 02, 01 / D |
| [ ] | R0152 | `POST /api/v1/account/deletion-code` | `api.v1.account.deletion-code` | [AccountDeletionController@sendCode](../app/Http/Controllers/Api/V1/AccountDeletionController.php) | 02, 01 / S |
| [ ] | R0153 | `GET\|HEAD /api/v1/admin/backoffice` | `api.v1.admin.backoffice.dashboard` | [AdminBackofficeController@dashboard](../app/Http/Controllers/Api/V1/AdminBackofficeController.php) | 33, 34 / L |
| [ ] | R0154 | `POST /api/v1/admin/backoffice/club-subscriptions/{subscription}/cancel` | `api.v1.admin.backoffice.club-subscriptions.cancel` | [AdminBackofficeController@cancelClubSubscription](../app/Http/Controllers/Api/V1/AdminBackofficeController.php) | 33, 34 / S |
| [ ] | R0155 | `POST /api/v1/admin/backoffice/club-subscriptions/{subscription}/renew` | `api.v1.admin.backoffice.club-subscriptions.renew` | [AdminBackofficeController@renewClubSubscription](../app/Http/Controllers/Api/V1/AdminBackofficeController.php) | 33, 34 / S |
| [ ] | R0156 | `PUT /api/v1/admin/backoffice/clubs/{club}/subscription` | `api.v1.admin.backoffice.clubs.subscription` | [AdminBackofficeController@assignClubSubscription](../app/Http/Controllers/Api/V1/AdminBackofficeController.php) | 33, 34 / S |
| [ ] | R0157 | `POST /api/v1/admin/backoffice/contracts` | `api.v1.admin.backoffice.contracts.store` | [AdminBackofficeController@storeContract](../app/Http/Controllers/Api/V1/AdminBackofficeController.php) | 33, 34 / S |
| [ ] | R0158 | `PATCH /api/v1/admin/backoffice/contracts/{operatingContract}` | `api.v1.admin.backoffice.contracts.update` | [AdminBackofficeController@updateContract](../app/Http/Controllers/Api/V1/AdminBackofficeController.php) | 33, 34 / S |
| [ ] | R0159 | `DELETE /api/v1/admin/backoffice/contracts/{operatingContract}` | `api.v1.admin.backoffice.contracts.destroy` | [AdminBackofficeController@destroyContract](../app/Http/Controllers/Api/V1/AdminBackofficeController.php) | 33, 34 / D |
| [ ] | R0160 | `POST /api/v1/admin/backoffice/invoices` | `api.v1.admin.backoffice.invoices.store` | [AdminBackofficeController@storeInvoice](../app/Http/Controllers/Api/V1/AdminBackofficeController.php) | 33, 34 / S |
| [ ] | R0161 | `DELETE /api/v1/admin/backoffice/invoices/{invoice}` | `api.v1.admin.backoffice.invoices.destroy` | [AdminBackofficeController@destroyInvoice](../app/Http/Controllers/Api/V1/AdminBackofficeController.php) | 33, 34 / D |
| [ ] | R0162 | `PATCH /api/v1/admin/backoffice/invoices/{invoice}/status` | `api.v1.admin.backoffice.invoices.status` | [AdminBackofficeController@updateInvoiceStatus](../app/Http/Controllers/Api/V1/AdminBackofficeController.php) | 33, 34 / S |
| [ ] | R0163 | `POST /api/v1/admin/backoffice/payments` | `api.v1.admin.backoffice.payments.store` | [AdminBackofficeController@storePayment](../app/Http/Controllers/Api/V1/AdminBackofficeController.php) | 33, 34 / S |
| [ ] | R0164 | `DELETE /api/v1/admin/backoffice/payments/{payment}` | `api.v1.admin.backoffice.payments.destroy` | [AdminBackofficeController@destroyPayment](../app/Http/Controllers/Api/V1/AdminBackofficeController.php) | 33, 34 / D |
| [ ] | R0165 | `PATCH /api/v1/admin/backoffice/plans/{subscriptionPlan}` | `api.v1.admin.backoffice.plans.update` | [AdminBackofficeController@updatePlan](../app/Http/Controllers/Api/V1/AdminBackofficeController.php) | 33, 34 / S |
| [ ] | R0166 | `POST /api/v1/admin/backoffice/subscription-invoices/{subscriptionInvoice}/mark-paid` | `api.v1.admin.backoffice.subscription-invoices.mark-paid` | [AdminBackofficeController@markSubscriptionInvoicePaid](../app/Http/Controllers/Api/V1/AdminBackofficeController.php) | 33, 34 / S |
| [ ] | R0167 | `POST /api/v1/admin/backoffice/transfers/{checkout}/mark-paid` | `api.v1.admin.backoffice.transfers.mark-paid` | [AdminBackofficeController@markTransferPaid](../app/Http/Controllers/Api/V1/AdminBackofficeController.php) | 33, 34 / S |
| [ ] | R0168 | `POST /api/v1/admin/backoffice/user-subscriptions/{subscription}/cancel` | `api.v1.admin.backoffice.user-subscriptions.cancel` | [AdminBackofficeController@cancelUserSubscription](../app/Http/Controllers/Api/V1/AdminBackofficeController.php) | 33, 34 / S |
| [ ] | R0169 | `POST /api/v1/admin/backoffice/user-subscriptions/{subscription}/renew` | `api.v1.admin.backoffice.user-subscriptions.renew` | [AdminBackofficeController@renewUserSubscription](../app/Http/Controllers/Api/V1/AdminBackofficeController.php) | 33, 34 / S |
| [ ] | R0170 | `PUT /api/v1/admin/backoffice/users/{user}/subscription` | `api.v1.admin.backoffice.users.subscription` | [AdminBackofficeController@assignUserSubscription](../app/Http/Controllers/Api/V1/AdminBackofficeController.php) | 33, 34 / S |
| [ ] | R0171 | `GET\|HEAD /api/v1/admin/clubs` | `api.v1.admin.clubs.index` | [AdminClubController@index](../app/Http/Controllers/AdminClubController.php) | 33, 34 / L |
| [ ] | R0172 | `DELETE /api/v1/admin/clubs/{club}` | `api.v1.admin.clubs.destroy` | [AdminClubController@destroy](../app/Http/Controllers/AdminClubController.php) | 33, 34 / D |
| [ ] | R0173 | `GET\|HEAD /api/v1/admin/commerce` | `api.v1.admin.commerce.dashboard` | [AdminCommerceController@dashboard](../app/Http/Controllers/Api/V1/AdminCommerceController.php) | 33, 34 / L |
| [ ] | R0174 | `POST /api/v1/admin/commerce/addons` | `api.v1.admin.commerce.addons.store` | [AdminCommerceController@storeAddon](../app/Http/Controllers/Api/V1/AdminCommerceController.php) | 33, 34 / S |
| [ ] | R0175 | `PATCH /api/v1/admin/commerce/addons/{addon}` | `api.v1.admin.commerce.addons.update` | [AdminCommerceController@updateAddon](../app/Http/Controllers/Api/V1/AdminCommerceController.php) | 33, 34 / S |
| [ ] | R0176 | `POST /api/v1/admin/commerce/campaigns` | `api.v1.admin.commerce.campaigns.store` | [AdminCommerceController@storeCampaign](../app/Http/Controllers/Api/V1/AdminCommerceController.php) | 33, 34 / S |
| [ ] | R0177 | `PUT /api/v1/admin/commerce/campaigns/{campaign}` | `api.v1.admin.commerce.campaigns.update` | [AdminCommerceController@updateCampaign](../app/Http/Controllers/Api/V1/AdminCommerceController.php) | 33, 34 / S |
| [ ] | R0178 | `PATCH /api/v1/admin/commerce/campaigns/{campaign}/status` | `api.v1.admin.commerce.campaigns.status` | [AdminCommerceController@updateCampaignStatus](../app/Http/Controllers/Api/V1/AdminCommerceController.php) | 33, 34 / S |
| [ ] | R0179 | `GET\|HEAD /api/v1/admin/commerce/catalog` | `api.v1.admin.commerce.catalog` | [AdminCommerceController@catalog](../app/Http/Controllers/Api/V1/AdminCommerceController.php) | 33, 34 / L |
| [ ] | R0180 | `POST /api/v1/admin/commerce/coupons` | `api.v1.admin.commerce.coupons.store` | [AdminCommerceController@storeCoupon](../app/Http/Controllers/Api/V1/AdminCommerceController.php) | 33, 34 / S |
| [ ] | R0181 | `PATCH /api/v1/admin/commerce/coupons/{coupon}` | `api.v1.admin.commerce.coupons.update` | [AdminCommerceController@updateCoupon](../app/Http/Controllers/Api/V1/AdminCommerceController.php) | 33, 34 / S |
| [ ] | R0182 | `GET\|HEAD /api/v1/admin/commerce/export` | `api.v1.admin.commerce.export` | [AdminCommerceController@export](../app/Http/Controllers/Api/V1/AdminCommerceController.php) | 33, 34 / L |
| [ ] | R0183 | `PUT /api/v1/admin/commerce/marketplace-commissions` | `api.v1.admin.commerce.marketplace-commissions.update` | [AdminCommerceController@updateMarketplaceCommissions](../app/Http/Controllers/Api/V1/AdminCommerceController.php) | 33, 34 / S |
| [ ] | R0184 | `PUT /api/v1/admin/commerce/marketplace-visuals` | `api.v1.admin.commerce.marketplace-visuals.update` | [AdminCommerceController@updateMarketplaceVisuals](../app/Http/Controllers/Api/V1/AdminCommerceController.php) | 33, 34 / S |
| [ ] | R0185 | `GET\|HEAD /api/v1/admin/commerce/orders/{order}/credit-note` | `api.v1.admin.commerce.orders.credit-note` | [AdminCommerceController@downloadCreditNote](../app/Http/Controllers/Api/V1/AdminCommerceController.php) | 33, 34 / L |
| [ ] | R0186 | `GET\|HEAD /api/v1/admin/commerce/orders/{order}/documents` | `api.v1.admin.commerce.orders.documents` | [AdminCommerceController@orderDocuments](../app/Http/Controllers/Api/V1/AdminCommerceController.php) | 33, 34 / L |
| [ ] | R0187 | `GET\|HEAD /api/v1/admin/commerce/orders/{order}/invoice` | `api.v1.admin.commerce.orders.invoice` | [AdminCommerceController@downloadInvoice](../app/Http/Controllers/Api/V1/AdminCommerceController.php) | 33, 34 / L |
| [ ] | R0188 | `PATCH /api/v1/admin/commerce/orders/{order}/issue` | `api.v1.admin.commerce.orders.issue` | [AdminCommerceController@updateOrderIssue](../app/Http/Controllers/Api/V1/AdminCommerceController.php) | 33, 34 / S |
| [ ] | R0189 | `POST /api/v1/admin/commerce/orders/{order}/issue/reply` | `api.v1.admin.commerce.orders.issue.reply` | [AdminCommerceController@replyOrderIssue](../app/Http/Controllers/Api/V1/AdminCommerceController.php) | 33, 34 / S |
| [ ] | R0190 | `POST /api/v1/admin/commerce/orders/{order}/mark-paid` | `api.v1.admin.commerce.orders.mark-paid` | [AdminCommerceController@markOrderPaid](../app/Http/Controllers/Api/V1/AdminCommerceController.php) | 33, 34 / S |
| [ ] | R0191 | `POST /api/v1/admin/commerce/orders/{order}/refund` | `api.v1.admin.commerce.orders.refund` | [AdminCommerceController@refundOrder](../app/Http/Controllers/Api/V1/AdminCommerceController.php) | 33, 34 / S |
| [ ] | R0192 | `PATCH /api/v1/admin/commerce/orders/{order}/shipping` | `api.v1.admin.commerce.orders.shipping` | [AdminCommerceController@updateOrderShipping](../app/Http/Controllers/Api/V1/AdminCommerceController.php) | 33, 34 / S |
| [ ] | R0193 | `PATCH /api/v1/admin/commerce/payout-profiles/{profile}` | `api.v1.admin.commerce.payout-profiles.update` | [AdminCommerceController@updatePayoutProfile](../app/Http/Controllers/Api/V1/AdminCommerceController.php) | 33, 34 / S |
| [ ] | R0194 | `POST /api/v1/admin/commerce/payouts/users/{user}` | `api.v1.admin.commerce.payouts.store` | [AdminCommerceController@createPayout](../app/Http/Controllers/Api/V1/AdminCommerceController.php) | 33, 34 / S |
| [ ] | R0195 | `PATCH /api/v1/admin/commerce/payouts/{payout}/paid` | `api.v1.admin.commerce.payouts.paid` | [AdminCommerceController@markPayoutPaid](../app/Http/Controllers/Api/V1/AdminCommerceController.php) | 33, 34 / S |
| [ ] | R0196 | `POST /api/v1/admin/commerce/products` | `api.v1.admin.commerce.products.store` | [AdminCommerceController@storeProduct](../app/Http/Controllers/Api/V1/AdminCommerceController.php) | 33, 34 / S |
| [ ] | R0197 | `PUT /api/v1/admin/commerce/products/{product}` | `api.v1.admin.commerce.products.update` | [AdminCommerceController@updateProduct](../app/Http/Controllers/Api/V1/AdminCommerceController.php) | 33, 34 / S |
| [ ] | R0198 | `DELETE /api/v1/admin/commerce/products/{product}` | `api.v1.admin.commerce.products.destroy` | [AdminCommerceController@destroyProduct](../app/Http/Controllers/Api/V1/AdminCommerceController.php) | 33, 34 / D |
| [ ] | R0199 | `PATCH /api/v1/admin/commerce/products/{product}/status` | `api.v1.admin.commerce.products.status` | [AdminCommerceController@updateProductStatus](../app/Http/Controllers/Api/V1/AdminCommerceController.php) | 33, 34 / S |
| [ ] | R0200 | `POST /api/v1/admin/commerce/products/{product}/stock` | `api.v1.admin.commerce.products.stock` | [AdminCommerceController@adjustProductStock](../app/Http/Controllers/Api/V1/AdminCommerceController.php) | 33, 34 / S |
| [ ] | R0201 | `PATCH /api/v1/admin/commerce/public-contact-requests/{publicContactRequest}` | `api.v1.admin.commerce.public-contact-requests.update` | [AdminCommerceController@updatePublicContactRequest](../app/Http/Controllers/Api/V1/AdminCommerceController.php) | 33, 34 / S |
| [ ] | R0202 | `PATCH /api/v1/admin/commerce/returns/{returnRequest}` | `api.v1.admin.commerce.returns.update` | [AdminCommerceController@updateReturnRequest](../app/Http/Controllers/Api/V1/AdminCommerceController.php) | 33, 34 / S |
| [ ] | R0203 | `PATCH /api/v1/admin/commerce/seller-applications/{sellerApplication}` | `api.v1.admin.commerce.seller-applications.update` | [AdminCommerceController@updateSellerApplication](../app/Http/Controllers/Api/V1/AdminCommerceController.php) | 33, 34 / S |
| [ ] | R0204 | `PUT /api/v1/admin/commerce/settings` | `api.v1.admin.commerce.settings.update` | [AdminCommerceController@updateCommerceSettings](../app/Http/Controllers/Api/V1/AdminCommerceController.php) | 33, 34 / S |
| [ ] | R0205 | `POST /api/v1/admin/commerce/shipping-rates` | `api.v1.admin.commerce.shipping-rates.store` | [AdminCommerceController@storeShippingRate](../app/Http/Controllers/Api/V1/AdminCommerceController.php) | 33, 34 / S |
| [ ] | R0206 | `PATCH /api/v1/admin/commerce/shipping-rates/{shippingRate}` | `api.v1.admin.commerce.shipping-rates.update` | [AdminCommerceController@updateShippingRate](../app/Http/Controllers/Api/V1/AdminCommerceController.php) | 33, 34 / S |
| [ ] | R0207 | `POST /api/v1/admin/commerce/tax-rates` | `api.v1.admin.commerce.tax-rates.store` | [AdminCommerceController@storeTaxRate](../app/Http/Controllers/Api/V1/AdminCommerceController.php) | 33, 34 / S |
| [ ] | R0208 | `PATCH /api/v1/admin/commerce/tax-rates/{taxRate}` | `api.v1.admin.commerce.tax-rates.update` | [AdminCommerceController@updateTaxRate](../app/Http/Controllers/Api/V1/AdminCommerceController.php) | 33, 34 / S |
| [ ] | R0209 | `PATCH /api/v1/admin/commerce/website-requests/{websiteRequest}` | `api.v1.admin.commerce.website-requests.update` | [AdminCommerceController@updateWebsiteRequest](../app/Http/Controllers/Api/V1/AdminCommerceController.php) | 33, 34 / S |
| [ ] | R0210 | `GET\|HEAD /api/v1/admin/mail` | `api.v1.admin.mail.dashboard` | [AdminMailController@dashboard](../app/Http/Controllers/Api/V1/AdminMailController.php) | 33, 34 / L |
| [ ] | R0211 | `POST /api/v1/admin/mail/deliveries/{mailDelivery}/resend` | `api.v1.admin.mail.deliveries.resend` | [AdminMailController@resend](../app/Http/Controllers/Api/V1/AdminMailController.php) | 33, 34 / S |
| [ ] | R0212 | `PUT /api/v1/admin/mail/deliveries/{mailDelivery}/resolve` | `api.v1.admin.mail.deliveries.resolve` | [AdminMailController@resolve](../app/Http/Controllers/Api/V1/AdminMailController.php) | 33, 34 / S |
| [ ] | R0213 | `PUT /api/v1/admin/mail/preferences` | `api.v1.admin.mail.preferences` | [AdminMailController@updatePreferences](../app/Http/Controllers/Api/V1/AdminMailController.php) | 33, 34 / S |
| [ ] | R0214 | `POST /api/v1/admin/mail/scheduled` | `api.v1.admin.mail.scheduled.store` | [AdminMailController@schedule](../app/Http/Controllers/Api/V1/AdminMailController.php) | 33, 34 / S |
| [ ] | R0215 | `POST /api/v1/admin/mail/scheduled/preview` | `api.v1.admin.mail.scheduled.preview` | [AdminMailController@previewScheduled](../app/Http/Controllers/Api/V1/AdminMailController.php) | 33, 34 / S |
| [ ] | R0216 | `PUT /api/v1/admin/mail/scheduled/{mailDelivery}/cancel` | `api.v1.admin.mail.scheduled.cancel` | [AdminMailController@cancelScheduled](../app/Http/Controllers/Api/V1/AdminMailController.php) | 33, 34 / S |
| [ ] | R0217 | `PUT /api/v1/admin/mail/senders/{category}` | `api.v1.admin.mail.senders.update` | [AdminMailController@updateSender](../app/Http/Controllers/Api/V1/AdminMailController.php) | 33, 34 / S |
| [ ] | R0218 | `POST /api/v1/admin/mail/senders/{category}/test` | `api.v1.admin.mail.senders.test` | [AdminMailController@testSender](../app/Http/Controllers/Api/V1/AdminMailController.php) | 33, 34 / S |
| [ ] | R0219 | `GET\|HEAD /api/v1/admin/media-guidelines` | `api.v1.admin.media-guidelines` | [MediaGuidelineController@index](../app/Http/Controllers/MediaGuidelineController.php) | 33, 34 / L |
| [ ] | R0220 | `POST /api/v1/admin/media-guidelines/visuals` | `api.v1.admin.media-guidelines.visuals` | [MediaGuidelineController@updateVisuals](../app/Http/Controllers/MediaGuidelineController.php) | 33, 34 / S |
| [ ] | R0221 | `GET\|HEAD /api/v1/admin/members` | `api.v1.admin.members.index` | [MemberController@index](../app/Http/Controllers/MemberController.php) | 33, 34 / L |
| [ ] | R0222 | `GET\|HEAD /api/v1/admin/members/{user}` | `api.v1.admin.members.edit` | [MemberController@edit](../app/Http/Controllers/MemberController.php) | 33, 34 / L |
| [ ] | R0223 | `PUT /api/v1/admin/members/{user}` | `api.v1.admin.members.update` | [MemberController@update](../app/Http/Controllers/MemberController.php) | 33, 34 / S |
| [ ] | R0224 | `DELETE /api/v1/admin/members/{user}` | `api.v1.admin.members.destroy` | [MemberController@destroy](../app/Http/Controllers/MemberController.php) | 33, 34 / D |
| [ ] | R0225 | `POST /api/v1/admin/members/{user}/inactivity-notice` | `api.v1.admin.members.inactivity-notice` | [MemberController@sendInactivityNotice](../app/Http/Controllers/MemberController.php) | 33, 34 / S |
| [ ] | R0226 | `GET\|HEAD /api/v1/admin/operations` | `api.v1.admin.operations` | [AdminInsightsController@operations](../app/Http/Controllers/Api/V1/AdminInsightsController.php) | 33, 34 / L |
| [ ] | R0227 | `GET\|HEAD /api/v1/admin/outfits` | `api.v1.admin.outfits.dashboard` | [AdminOutfitController@dashboard](../app/Http/Controllers/Api/V1/AdminOutfitController.php) | 33, 34 / L |
| [ ] | R0228 | `PUT /api/v1/admin/outfits/deliveries/{outfitDelivery}` | `api.v1.admin.outfits.deliveries.update` | [AdminOutfitController@updateDelivery](../app/Http/Controllers/Api/V1/AdminOutfitController.php) | 33, 34 / S |
| [ ] | R0229 | `DELETE /api/v1/admin/outfits/deliveries/{outfitDelivery}` | `api.v1.admin.outfits.deliveries.destroy` | [AdminOutfitController@destroyDelivery](../app/Http/Controllers/Api/V1/AdminOutfitController.php) | 33, 34 / D |
| [ ] | R0230 | `POST /api/v1/admin/outfits/deliveries/{outfitDelivery}/delivered` | `api.v1.admin.outfits.deliveries.delivered` | [AdminOutfitController@markDeliveryDelivered](../app/Http/Controllers/Api/V1/AdminOutfitController.php) | 33, 34 / S |
| [ ] | R0231 | `PUT /api/v1/admin/outfits/deliveries/{outfitDelivery}/issue` | `api.v1.admin.outfits.deliveries.issue` | [AdminOutfitController@updateDeliveryIssue](../app/Http/Controllers/Api/V1/AdminOutfitController.php) | 33, 34 / S |
| [ ] | R0232 | `POST /api/v1/admin/outfits/deliveries/{outfitDelivery}/shipped` | `api.v1.admin.outfits.deliveries.shipped` | [AdminOutfitController@markDeliveryShipped](../app/Http/Controllers/Api/V1/AdminOutfitController.php) | 33, 34 / S |
| [ ] | R0233 | `POST /api/v1/admin/outfits/plans` | `api.v1.admin.outfits.plans.store` | [AdminOutfitController@storePlan](../app/Http/Controllers/Api/V1/AdminOutfitController.php) | 33, 34 / S |
| [ ] | R0234 | `PUT /api/v1/admin/outfits/plans/{outfitSubscriptionPlan}` | `api.v1.admin.outfits.plans.update` | [AdminOutfitController@updatePlan](../app/Http/Controllers/Api/V1/AdminOutfitController.php) | 33, 34 / S |
| [ ] | R0235 | `DELETE /api/v1/admin/outfits/plans/{outfitSubscriptionPlan}` | `api.v1.admin.outfits.plans.destroy` | [AdminOutfitController@destroyPlan](../app/Http/Controllers/Api/V1/AdminOutfitController.php) | 33, 34 / D |
| [ ] | R0236 | `DELETE /api/v1/admin/outfits/subscriptions/{outfitSubscription}` | `api.v1.admin.outfits.subscriptions.destroy` | [AdminOutfitController@destroySubscription](../app/Http/Controllers/Api/V1/AdminOutfitController.php) | 33, 34 / D |
| [ ] | R0237 | `POST /api/v1/admin/outfits/subscriptions/{outfitSubscription}/cancel` | `api.v1.admin.outfits.subscriptions.cancel` | [AdminOutfitController@cancelSubscription](../app/Http/Controllers/Api/V1/AdminOutfitController.php) | 33, 34 / S |
| [ ] | R0238 | `POST /api/v1/admin/outfits/subscriptions/{outfitSubscription}/mark-paid` | `api.v1.admin.outfits.subscriptions.mark-paid` | [AdminOutfitController@markSubscriptionPaid](../app/Http/Controllers/Api/V1/AdminOutfitController.php) | 33, 34 / S |
| [ ] | R0239 | `POST /api/v1/admin/outfits/subscriptions/{outfitSubscription}/mark-unpaid` | `api.v1.admin.outfits.subscriptions.mark-unpaid` | [AdminOutfitController@markSubscriptionUnpaid](../app/Http/Controllers/Api/V1/AdminOutfitController.php) | 33, 34 / S |
| [ ] | R0240 | `POST /api/v1/admin/outfits/subscriptions/{outfitSubscription}/payment-reminder` | `api.v1.admin.outfits.subscriptions.payment-reminder` | [AdminOutfitController@remindPayment](../app/Http/Controllers/Api/V1/AdminOutfitController.php) | 33, 34 / S |
| [ ] | R0241 | `PUT /api/v1/admin/outfits/subscriptions/{outfitSubscription}/shipping-address` | `api.v1.admin.outfits.subscriptions.shipping-address` | [AdminOutfitController@updateShippingAddress](../app/Http/Controllers/Api/V1/AdminOutfitController.php) | 33, 34 / S |
| [ ] | R0242 | `POST /api/v1/admin/outfits/visuals` | `api.v1.admin.outfits.visuals.update` | [AdminOutfitController@updateVisuals](../app/Http/Controllers/Api/V1/AdminOutfitController.php) | 33, 34 / S |
| [ ] | R0243 | `GET\|HEAD /api/v1/admin/platform` | `api.v1.admin.platform.dashboard` | [PlatformAdminController@dashboard](../app/Http/Controllers/Api/V1/PlatformAdminController.php) | 33, 34 / L |
| [ ] | R0244 | `POST /api/v1/admin/platform/badges` | `api.v1.admin.platform.badges.store` | [PlatformAdminController@storeBadge](../app/Http/Controllers/Api/V1/PlatformAdminController.php) | 33, 34 / S |
| [ ] | R0245 | `PATCH /api/v1/admin/platform/badges/{badge}` | `api.v1.admin.platform.badges.update` | [PlatformAdminController@updateBadge](../app/Http/Controllers/Api/V1/PlatformAdminController.php) | 33, 34 / S |
| [ ] | R0246 | `DELETE /api/v1/admin/platform/badges/{badge}` | `api.v1.admin.platform.badges.destroy` | [PlatformAdminController@destroyBadge](../app/Http/Controllers/Api/V1/PlatformAdminController.php) | 33, 34 / D |
| [ ] | R0247 | `PATCH /api/v1/admin/platform/clubs/{club}/approve` | `api.v1.admin.platform.clubs.approve` | [PlatformAdminController@approveClub](../app/Http/Controllers/Api/V1/PlatformAdminController.php) | 33, 34 / S |
| [ ] | R0248 | `PATCH /api/v1/admin/platform/clubs/{club}/reject` | `api.v1.admin.platform.clubs.reject` | [PlatformAdminController@rejectClub](../app/Http/Controllers/Api/V1/PlatformAdminController.php) | 33, 34 / S |
| [ ] | R0249 | `PATCH /api/v1/admin/platform/clubs/{club}/verification-status` | `api.v1.admin.platform.clubs.verification-status` | [AdminClubController@updateVerificationStatus](../app/Http/Controllers/AdminClubController.php) | 33, 34 / S |
| [ ] | R0250 | `PATCH /api/v1/admin/platform/gamification-rules/{gamificationRule}` | `api.v1.admin.platform.gamification-rules.update` | [PlatformAdminController@updateGamificationRule](../app/Http/Controllers/Api/V1/PlatformAdminController.php) | 33, 34 / S |
| [ ] | R0251 | `PATCH /api/v1/admin/platform/moderation/flags/{flag}` | `api.v1.admin.platform.moderation.flags.update` | [PlatformAdminController@updateModerationFlag](../app/Http/Controllers/Api/V1/PlatformAdminController.php) | 33, 34 / S |
| [ ] | R0252 | `PATCH /api/v1/admin/platform/moderation/reports/{report}` | `api.v1.admin.platform.moderation.reports.update` | [PlatformAdminController@updateModerationReport](../app/Http/Controllers/Api/V1/PlatformAdminController.php) | 33, 34 / S |
| [ ] | R0253 | `PATCH /api/v1/admin/platform/moderation/reports/{report}/appeal` | `api.v1.admin.platform.moderation.reports.appeal` | [PlatformAdminController@decideModerationAppeal](../app/Http/Controllers/Api/V1/PlatformAdminController.php) | 33, 34 / S |
| [ ] | R0254 | `POST /api/v1/admin/platform/permissions` | `api.v1.admin.platform.permissions.store` | [PlatformAdminController@storePermission](../app/Http/Controllers/Api/V1/PlatformAdminController.php) | 33, 34 / S |
| [ ] | R0255 | `POST /api/v1/admin/platform/roles` | `api.v1.admin.platform.roles.store` | [PlatformAdminController@storeRole](../app/Http/Controllers/Api/V1/PlatformAdminController.php) | 33, 34 / S |
| [ ] | R0256 | `PATCH /api/v1/admin/platform/roles/{role}` | `api.v1.admin.platform.roles.update` | [PlatformAdminController@updateRole](../app/Http/Controllers/Api/V1/PlatformAdminController.php) | 33, 34 / S |
| [ ] | R0257 | `DELETE /api/v1/admin/platform/roles/{role}` | `api.v1.admin.platform.roles.destroy` | [PlatformAdminController@destroyRole](../app/Http/Controllers/Api/V1/PlatformAdminController.php) | 33, 34 / D |
| [ ] | R0258 | `POST /api/v1/admin/platform/sports` | `api.v1.admin.platform.sports.store` | [PlatformAdminController@storeSport](../app/Http/Controllers/Api/V1/PlatformAdminController.php) | 33, 34 / S |
| [ ] | R0259 | `PATCH /api/v1/admin/platform/sports/{sport}` | `api.v1.admin.platform.sports.update` | [PlatformAdminController@updateSport](../app/Http/Controllers/Api/V1/PlatformAdminController.php) | 33, 34 / S |
| [ ] | R0260 | `DELETE /api/v1/admin/platform/sports/{sport}` | `api.v1.admin.platform.sports.destroy` | [PlatformAdminController@destroySport](../app/Http/Controllers/Api/V1/PlatformAdminController.php) | 33, 34 / D |
| [ ] | R0261 | `POST /api/v1/admin/platform/users` | `api.v1.admin.platform.users.store` | [PlatformAdminController@storeUser](../app/Http/Controllers/Api/V1/PlatformAdminController.php) | 33, 34 / S |
| [ ] | R0262 | `PATCH /api/v1/admin/platform/users/{user}/status` | `api.v1.admin.platform.users.status` | [PlatformAdminController@updateUserStatus](../app/Http/Controllers/Api/V1/PlatformAdminController.php) | 33, 34 / S |
| [ ] | R0263 | `GET\|HEAD /api/v1/admin/product-analytics` | `api.v1.admin.product-analytics` | [AdminInsightsController@analytics](../app/Http/Controllers/Api/V1/AdminInsightsController.php) | 33, 34 / L |
| [ ] | R0264 | `POST /api/v1/admin/subscription-checkouts/{checkout}/mark-paid` | `api.v1.admin.subscription-checkouts.mark-paid` | [SubscriptionController@markCheckoutPaid](../app/Http/Controllers/Api/V1/SubscriptionController.php) | 33, 34 / S |
| [ ] | R0265 | `GET\|HEAD /api/v1/admin/support/tickets` | `api.v1.admin.support.tickets.index` | [SupportTicketController@adminIndex](../app/Http/Controllers/Api/V1/SupportTicketController.php) | 33, 34 / L |
| [ ] | R0266 | `PATCH /api/v1/admin/support/tickets/{supportTicket}` | `api.v1.admin.support.tickets.update` | [SupportTicketController@adminUpdate](../app/Http/Controllers/Api/V1/SupportTicketController.php) | 33, 34 / S |
| [ ] | R0267 | `GET\|HEAD /api/v1/admin/system` | `api.v1.admin.system.dashboard` | [AdminSystemController@dashboard](../app/Http/Controllers/Api/V1/AdminSystemController.php) | 33, 34 / L |
| [ ] | R0268 | `PUT /api/v1/admin/system/settings` | `api.v1.admin.system.settings.update` | [AdminSystemController@updateSettings](../app/Http/Controllers/Api/V1/AdminSystemController.php) | 33, 34 / S |
| [ ] | R0269 | `GET\|HEAD /api/v1/admin/trainer-applications` | `api.v1.admin.trainer-applications` | [AccountRoleApplicationController@adminIndex](../app/Http/Controllers/AccountRoleApplicationController.php) | 33, 34 / L |
| [ ] | R0270 | `PUT /api/v1/admin/trainer-applications/{application}/approve` | `api.v1.admin.trainer-applications.approve` | [AccountRoleApplicationController@approve](../app/Http/Controllers/AccountRoleApplicationController.php) | 33, 34 / S |
| [ ] | R0271 | `PUT /api/v1/admin/trainer-applications/{application}/reject` | `api.v1.admin.trainer-applications.reject` | [AccountRoleApplicationController@reject](../app/Http/Controllers/AccountRoleApplicationController.php) | 33, 34 / S |
| [ ] | R0272 | `POST /api/v1/ai/communication-drafts` | `api.v1.ai.communication-drafts` | [AiCommunicationDraftController](../app/Http/Controllers/Api/V1/AiCommunicationDraftController.php) | 26 / S |
| [ ] | R0273 | `POST /api/v1/auth/forgot-password` | `api.v1.auth.password.email` | [PasswordRecoveryController@sendResetLink](../app/Http/Controllers/Api/V1/PasswordRecoveryController.php) | 01 / S |
| [ ] | R0274 | `POST /api/v1/auth/login` | `api.v1.auth.login` | [AuthController@login](../app/Http/Controllers/Api/V1/AuthController.php) | 01 / S |
| [ ] | R0275 | `POST /api/v1/auth/logout` | `api.v1.auth.logout` | [AuthController@logout](../app/Http/Controllers/Api/V1/AuthController.php) | 01 / S |
| [ ] | R0276 | `POST /api/v1/auth/register` | `api.v1.auth.register` | [AuthController@register](../app/Http/Controllers/Api/V1/AuthController.php) | 01 / S |
| [ ] | R0277 | `GET\|HEAD /api/v1/auth/register/email` | `api.v1.auth.register.email` | [AuthController@registrationEmail](../app/Http/Controllers/Api/V1/AuthController.php) | 01 / L |
| [ ] | R0278 | `POST /api/v1/auth/reset-password` | `api.v1.auth.password.reset` | [PasswordRecoveryController@reset](../app/Http/Controllers/Api/V1/PasswordRecoveryController.php) | 01 / S |
| [ ] | R0279 | `POST /api/v1/auth/two-factor-challenge` | `api.v1.auth.two-factor.challenge` | [MobileTwoFactorChallengeController](../app/Http/Controllers/Api/V1/MobileTwoFactorChallengeController.php) | 01 / S |
| [ ] | R0280 | `POST /api/v1/auth/two-factor-challenge/email-code` | `api.v1.auth.two-factor.email-code` | [MobileTwoFactorEmailCodeController](../app/Http/Controllers/Api/V1/MobileTwoFactorEmailCodeController.php) | 01 / S |
| [ ] | R0281 | `GET\|HEAD /api/v1/auth/verify-email/{id}/{hash}` | `api.v1.auth.email.verify` | [MobileEmailVerificationController@verify](../app/Http/Controllers/Api/V1/MobileEmailVerificationController.php) | 01 / L |
| [ ] | R0282 | `GET\|HEAD /api/v1/badges` | `api.v1.badges.index` | [UserBadgeController@index](../app/Http/Controllers/Api/V1/UserBadgeController.php) | 13, 36 / L |
| [ ] | R0283 | `GET\|HEAD /api/v1/badges/{userBadge}` | `api.v1.badges.show` | [UserBadgeController@show](../app/Http/Controllers/Api/V1/UserBadgeController.php) | 13, 36 / L |
| [ ] | R0284 | `GET\|HEAD /api/v1/billing/invoices` | `api.v1.billing.invoices.index` | [SettingsController@invoices](../app/Http/Controllers/Api/V1/SettingsController.php) | 20 / L |
| [ ] | R0285 | `GET\|HEAD /api/v1/billing/invoices/{invoice}` | `api.v1.billing.invoices.show` | [SettingsController@invoice](../app/Http/Controllers/Api/V1/SettingsController.php) | 20 / L |
| [ ] | R0286 | `GET\|HEAD /api/v1/challenges` | `api.v1.challenges.index` | [ChallengeController@index](../app/Http/Controllers/Api/V1/ChallengeController.php) | 13, 36 / L |
| [ ] | R0287 | `POST /api/v1/challenges` | `api.v1.challenges.store` | [ChallengeController@store](../app/Http/Controllers/Api/V1/ChallengeController.php) | 13, 36 / S |
| [ ] | R0288 | `GET\|HEAD /api/v1/challenges/{challenge}` | `api.v1.challenges.show` | [ChallengeController@show](../app/Http/Controllers/Api/V1/ChallengeController.php) | 13, 36 / L |
| [ ] | R0289 | `POST /api/v1/challenges/{challenge}/cancel` | `api.v1.challenges.cancel` | [ChallengeController@cancel](../app/Http/Controllers/Api/V1/ChallengeController.php) | 13, 36 / S |
| [ ] | R0290 | `PUT /api/v1/challenges/{challenge}/check-ins/{date}` | `api.v1.challenges.check-ins.update` | [ChallengeController@checkin](../app/Http/Controllers/Api/V1/ChallengeController.php) | 13, 36 / S |
| [ ] | R0291 | `GET\|HEAD /api/v1/challenges/{challenge}/comments` | `api.v1.challenges.comments.index` | [ChallengeController@comments](../app/Http/Controllers/Api/V1/ChallengeController.php) | 13, 36 / L |
| [ ] | R0292 | `POST /api/v1/challenges/{challenge}/comments` | `api.v1.challenges.comments.store` | [ChallengeController@comment](../app/Http/Controllers/Api/V1/ChallengeController.php) | 13, 36 / S |
| [ ] | R0293 | `PUT /api/v1/challenges/{challenge}/invitation` | `api.v1.challenges.invitation.update` | [ChallengeController@respond](../app/Http/Controllers/Api/V1/ChallengeController.php) | 13, 36 / S |
| [ ] | R0294 | `POST /api/v1/challenges/{challenge}/join` | `api.v1.challenges.join` | [ChallengeController@join](../app/Http/Controllers/Api/V1/ChallengeController.php) | 13, 36 / S |
| [ ] | R0295 | `GET\|HEAD /api/v1/chat/conversation-invitations` | `api.v1.chat.conversation-invitations.index` | [ChatController@invitations](../app/Http/Controllers/Api/V1/ChatController.php) | 05 / L |
| [ ] | R0296 | `POST /api/v1/chat/conversation-invitations/{invitation}/accept` | `api.v1.chat.conversation-invitations.accept` | [ChatController@acceptInvitation](../app/Http/Controllers/Api/V1/ChatController.php) | 05 / S |
| [ ] | R0297 | `POST /api/v1/chat/conversation-invitations/{invitation}/decline` | `api.v1.chat.conversation-invitations.decline` | [ChatController@declineInvitation](../app/Http/Controllers/Api/V1/ChatController.php) | 05 / S |
| [ ] | R0298 | `GET\|HEAD /api/v1/chat/conversations` | `api.v1.chat.conversations.index` | [ChatController@index](../app/Http/Controllers/Api/V1/ChatController.php) | 05 / L |
| [ ] | R0299 | `POST /api/v1/chat/conversations` | `api.v1.chat.conversations.store` | [ChatController@store](../app/Http/Controllers/Api/V1/ChatController.php) | 05 / S |
| [ ] | R0300 | `GET\|HEAD /api/v1/chat/conversations/{conversation}` | `api.v1.chat.conversations.show` | [ChatController@show](../app/Http/Controllers/Api/V1/ChatController.php) | 05 / L |
| [ ] | R0301 | `PUT /api/v1/chat/conversations/{conversation}` | `api.v1.chat.conversations.update` | [ChatController@updateConversation](../app/Http/Controllers/Api/V1/ChatController.php) | 05 / S |
| [ ] | R0302 | `DELETE /api/v1/chat/conversations/{conversation}/leave` | `api.v1.chat.conversations.leave` | [ChatController@leaveConversation](../app/Http/Controllers/Api/V1/ChatController.php) | 05 / D |
| [ ] | R0303 | `POST /api/v1/chat/conversations/{conversation}/members` | `api.v1.chat.conversations.members.store` | [ChatController@inviteMembers](../app/Http/Controllers/Api/V1/ChatController.php) | 05 / S |
| [ ] | R0304 | `DELETE /api/v1/chat/conversations/{conversation}/members/{user}` | `api.v1.chat.conversations.members.destroy` | [ChatController@removeMember](../app/Http/Controllers/Api/V1/ChatController.php) | 05 / D |
| [ ] | R0305 | `GET\|HEAD /api/v1/chat/conversations/{conversation}/messages` | `api.v1.chat.messages.index` | [ChatController@messages](../app/Http/Controllers/Api/V1/ChatController.php) | 05 / L |
| [ ] | R0306 | `POST /api/v1/chat/conversations/{conversation}/messages` | `api.v1.chat.messages.store` | [ChatController@sendMessage](../app/Http/Controllers/Api/V1/ChatController.php) | 05 / S |
| [ ] | R0307 | `PUT /api/v1/chat/conversations/{conversation}/mute` | `api.v1.chat.conversations.mute` | [ChatController@muteConversation](../app/Http/Controllers/Api/V1/ChatController.php) | 05 / S |
| [ ] | R0308 | `PUT /api/v1/chat/conversations/{conversation}/owner` | `api.v1.chat.conversations.owner.update` | [ChatController@transferOwner](../app/Http/Controllers/Api/V1/ChatController.php) | 05 / S |
| [ ] | R0309 | `POST /api/v1/chat/conversations/{conversation}/read` | `api.v1.chat.conversations.read` | [ChatController@markRead](../app/Http/Controllers/Api/V1/ChatController.php) | 05 / S |
| [ ] | R0310 | `POST /api/v1/chat/conversations/{conversation}/typing` | `api.v1.chat.typing` | [ChatController@typing](../app/Http/Controllers/Api/V1/ChatController.php) | 05 / S |
| [ ] | R0311 | `GET\|HEAD /api/v1/chat/messages/{message}` | `api.v1.chat.messages.show` | [ChatController@message](../app/Http/Controllers/Api/V1/ChatController.php) | 05 / L |
| [ ] | R0312 | `DELETE /api/v1/chat/messages/{message}` | `api.v1.chat.messages.destroy` | [ChatController@deleteMessage](../app/Http/Controllers/Api/V1/ChatController.php) | 05 / D |
| [ ] | R0313 | `DELETE /api/v1/chat/messages/{message}/hide` | `api.v1.chat.messages.hide` | [ChatController@hideForMe](../app/Http/Controllers/Api/V1/ChatController.php) | 05 / D |
| [ ] | R0314 | `POST /api/v1/chat/messages/{message}/reactions` | `api.v1.chat.messages.reactions.store` | [ChatController@react](../app/Http/Controllers/Api/V1/ChatController.php) | 05 / S |
| [ ] | R0315 | `GET\|HEAD /api/v1/club-external-invitations/{token}` | `api.v1.club-external-invitations.show` | [ClubController@externalInvitationByToken](../app/Http/Controllers/Api/V1/ClubController.php) | 18, 19, 20 / L |
| [ ] | R0316 | `POST /api/v1/club-external-invitations/{token}/accept` | `api.v1.club-external-invitations.accept` | [ClubController@acceptExternalInvitation](../app/Http/Controllers/Api/V1/ClubController.php) | 18, 19, 20 / S |
| [ ] | R0317 | `POST /api/v1/club-external-invitations/{token}/decline` | `api.v1.club-external-invitations.decline` | [ClubController@declineExternalInvitation](../app/Http/Controllers/Api/V1/ClubController.php) | 18, 19, 20 / S |
| [ ] | R0318 | `GET\|HEAD /api/v1/club-members/import-template` | `api.v1.club-members.import-template` | [ClubController@downloadMemberImportTemplate](../app/Http/Controllers/Api/V1/ClubController.php) | 18, 19, 20 / L |
| [ ] | R0319 | `GET\|HEAD /api/v1/clubs` | `api.v1.clubs.index` | [ClubController@index](../app/Http/Controllers/Api/V1/ClubController.php) | 17, 18, 34 / L |
| [ ] | R0320 | `POST /api/v1/clubs` | `api.v1.clubs.store` | [ClubController@store](../app/Http/Controllers/Api/V1/ClubController.php) | 17, 18, 34 / S |
| [ ] | R0321 | `GET\|HEAD /api/v1/clubs/{club}` | `api.v1.clubs.show` | [ClubController@show](../app/Http/Controllers/Api/V1/ClubController.php) | 17, 18, 34 / L |
| [ ] | R0322 | `PUT /api/v1/clubs/{club}` | `api.v1.clubs.update` | `Closure` | 17, 18, 34 / S |
| [ ] | R0323 | `DELETE /api/v1/clubs/{club}` | `api.v1.clubs.destroy` | [ClubController@destroy](../app/Http/Controllers/Api/V1/ClubController.php) | 17, 18, 34 / D |
| [ ] | R0324 | `GET\|HEAD /api/v1/clubs/{club}/access-handover-reviews` | `api.v1.clubs.access-handover-reviews.index` | [ClubAccessHandoverController@index](../app/Http/Controllers/Api/V1/ClubAccessHandoverController.php) | 22 / L |
| [ ] | R0325 | `POST /api/v1/clubs/{club}/access-handover-reviews/{review}/approve` | `api.v1.clubs.access-handover-reviews.approve` | [ClubAccessHandoverController@approve](../app/Http/Controllers/Api/V1/ClubAccessHandoverController.php) | 22 / S |
| [ ] | R0326 | `POST /api/v1/clubs/{club}/access-handover-reviews/{review}/propose` | `api.v1.clubs.access-handover-reviews.propose` | [ClubAccessHandoverController@propose](../app/Http/Controllers/Api/V1/ClubAccessHandoverController.php) | 22 / S |
| [ ] | R0327 | `GET\|HEAD /api/v1/clubs/{club}/announcements` | `api.v1.clubs.announcements.index` | [ClubAnnouncementController@index](../app/Http/Controllers/Api/V1/ClubAnnouncementController.php) | 26 / L |
| [ ] | R0328 | `POST /api/v1/clubs/{club}/announcements` | `api.v1.clubs.announcements.store` | [ClubAnnouncementController@store](../app/Http/Controllers/Api/V1/ClubAnnouncementController.php) | 26 / S |
| [ ] | R0329 | `PUT /api/v1/clubs/{club}/announcements/{announcement}` | `api.v1.clubs.announcements.update` | [ClubAnnouncementController@update](../app/Http/Controllers/Api/V1/ClubAnnouncementController.php) | 26 / S |
| [ ] | R0330 | `DELETE /api/v1/clubs/{club}/announcements/{announcement}` | `api.v1.clubs.announcements.destroy` | [ClubAnnouncementController@destroy](../app/Http/Controllers/Api/V1/ClubAnnouncementController.php) | 26 / D |
| [ ] | R0331 | `POST /api/v1/clubs/{club}/announcements/{announcement}/publish` | `api.v1.clubs.announcements.publish` | [ClubAnnouncementController@publish](../app/Http/Controllers/Api/V1/ClubAnnouncementController.php) | 26 / S |
| [ ] | R0332 | `POST /api/v1/clubs/{club}/announcements/{announcement}/read` | `api.v1.clubs.announcements.read` | [ClubAnnouncementController@acknowledge](../app/Http/Controllers/Api/V1/ClubAnnouncementController.php) | 26 / S |
| [ ] | R0333 | `POST /api/v1/clubs/{club}/announcements/{announcement}/withdraw` | `api.v1.clubs.announcements.withdraw` | [ClubAnnouncementController@withdraw](../app/Http/Controllers/Api/V1/ClubAnnouncementController.php) | 26 / S |
| [ ] | R0334 | `POST /api/v1/clubs/{club}/bank-transactions/import` | `api.v1.clubs.bank-transactions.import` | [ClubController@importBankTransactions](../app/Http/Controllers/Api/V1/ClubController.php) | 21 / S |
| [ ] | R0335 | `POST /api/v1/clubs/{club}/bank-transactions/preview` | `api.v1.clubs.bank-transactions.preview` | [ClubController@previewBankTransactions](../app/Http/Controllers/Api/V1/ClubController.php) | 21 / S |
| [ ] | R0336 | `POST /api/v1/clubs/{club}/bank-transactions/{bankTransaction}/confirm` | `api.v1.clubs.bank-transactions.confirm` | [ClubController@confirmBankTransaction](../app/Http/Controllers/Api/V1/ClubController.php) | 21 / S |
| [ ] | R0337 | `GET\|HEAD /api/v1/clubs/{club}/billing` | `api.v1.clubs.billing` | [ClubController@billing](../app/Http/Controllers/Api/V1/ClubController.php) | 20, 21 / L |
| [ ] | R0338 | `GET\|HEAD /api/v1/clubs/{club}/budgets` | `api.v1.clubs.budgets.index` | [ClubBudgetController@index](../app/Http/Controllers/Api/V1/ClubBudgetController.php) | 21 / L |
| [ ] | R0339 | `POST /api/v1/clubs/{club}/budgets` | `api.v1.clubs.budgets.store` | [ClubBudgetController@store](../app/Http/Controllers/Api/V1/ClubBudgetController.php) | 21 / S |
| [ ] | R0340 | `PUT /api/v1/clubs/{club}/budgets/{budget}` | `api.v1.clubs.budgets.update` | [ClubBudgetController@update](../app/Http/Controllers/Api/V1/ClubBudgetController.php) | 21 / S |
| [ ] | R0341 | `PUT /api/v1/clubs/{club}/budgets/{budget}/approval` | `api.v1.clubs.budgets.approval.update` | [ClubBudgetController@approve](../app/Http/Controllers/Api/V1/ClubBudgetController.php) | 21 / S |
| [ ] | R0342 | `GET\|HEAD /api/v1/clubs/{club}/deletion` | `api.v1.clubs.deletion.show` | [ClubDeletionController@show](../app/Http/Controllers/ClubDeletionController.php) | 28 / L |
| [ ] | R0343 | `DELETE /api/v1/clubs/{club}/deletion` | `api.v1.clubs.deletion.cancel` | [ClubDeletionController@destroy](../app/Http/Controllers/ClubDeletionController.php) | 28 / D |
| [ ] | R0344 | `POST /api/v1/clubs/{club}/donations` | `api.v1.clubs.donations.store` | [ClubController@recordDonation](../app/Http/Controllers/Api/V1/ClubController.php) | 21 / S |
| [ ] | R0345 | `POST /api/v1/clubs/{club}/dunning-rules` | `api.v1.clubs.dunning-rules.store` | [ClubDunningController@storeRule](../app/Http/Controllers/Api/V1/ClubDunningController.php) | 21 / S |
| [ ] | R0346 | `PUT /api/v1/clubs/{club}/external-members/{externalMember}` | `api.v1.clubs.external-members.update` | [ClubController@updateExternalMember](../app/Http/Controllers/Api/V1/ClubController.php) | 18, 08, 22 / S |
| [ ] | R0347 | `DELETE /api/v1/clubs/{club}/external-members/{externalMember}` | `api.v1.clubs.external-members.destroy` | [ClubController@removeExternalMember](../app/Http/Controllers/Api/V1/ClubController.php) | 18, 08, 22 / D |
| [ ] | R0348 | `POST /api/v1/clubs/{club}/external-members/{externalMember}/invite` | `api.v1.clubs.external-members.invite` | [ClubController@inviteExternalMember](../app/Http/Controllers/Api/V1/ClubController.php) | 18, 08, 22 / S |
| [ ] | R0349 | `POST /api/v1/clubs/{club}/external-members/{externalMember}/merge/{user}` | `api.v1.clubs.external-members.merge` | [ClubController@mergeExternalMember](../app/Http/Controllers/Api/V1/ClubController.php) | 18, 08, 22 / S |
| [ ] | R0350 | `POST /api/v1/clubs/{club}/finance-entries` | `api.v1.clubs.finance-entries.store` | [ClubController@storeFinanceEntry](../app/Http/Controllers/Api/V1/ClubController.php) | 21 / S |
| [ ] | R0351 | `PUT /api/v1/clubs/{club}/finance-entries/{financeEntry}` | `api.v1.clubs.finance-entries.update` | [ClubController@updateFinanceEntry](../app/Http/Controllers/Api/V1/ClubController.php) | 21 / S |
| [ ] | R0352 | `GET\|HEAD /api/v1/clubs/{club}/funding-programs` | `api.v1.clubs.funding-programs.index` | [ClubFundingProgramController@index](../app/Http/Controllers/Api/V1/ClubFundingProgramController.php) | 21 / L |
| [ ] | R0353 | `POST /api/v1/clubs/{club}/funding-programs` | `api.v1.clubs.funding-programs.store` | [ClubFundingProgramController@store](../app/Http/Controllers/Api/V1/ClubFundingProgramController.php) | 21 / S |
| [ ] | R0354 | `PUT /api/v1/clubs/{club}/funding-programs/{fundingProgram}` | `api.v1.clubs.funding-programs.update` | [ClubFundingProgramController@update](../app/Http/Controllers/Api/V1/ClubFundingProgramController.php) | 21 / S |
| [ ] | R0355 | `PUT /api/v1/clubs/{club}/funding-programs/{fundingProgram}/status` | `api.v1.clubs.funding-programs.status.update` | [ClubFundingProgramController@transition](../app/Http/Controllers/Api/V1/ClubFundingProgramController.php) | 21 / S |
| [ ] | R0356 | `GET\|HEAD /api/v1/clubs/{club}/governance` | `api.v1.clubs.governance.index` | [ClubGovernanceController@index](../app/Http/Controllers/Api/V1/ClubGovernanceController.php) | 22 / L |
| [ ] | R0357 | `POST /api/v1/clubs/{club}/governance/bodies` | `api.v1.clubs.governance.bodies.store` | [ClubGovernanceController@storeBody](../app/Http/Controllers/Api/V1/ClubGovernanceController.php) | 22 / S |
| [ ] | R0358 | `PUT /api/v1/clubs/{club}/governance/bodies/{governanceBody}` | `api.v1.clubs.governance.bodies.update` | [ClubGovernanceController@updateBody](../app/Http/Controllers/Api/V1/ClubGovernanceController.php) | 22 / S |
| [ ] | R0359 | `DELETE /api/v1/clubs/{club}/governance/bodies/{governanceBody}` | `api.v1.clubs.governance.bodies.destroy` | [ClubGovernanceController@destroyBody](../app/Http/Controllers/Api/V1/ClubGovernanceController.php) | 22 / D |
| [ ] | R0360 | `POST /api/v1/clubs/{club}/governance/bodies/{governanceBody}/assignments` | `api.v1.clubs.governance.assignments.store` | [ClubGovernanceController@storeAssignment](../app/Http/Controllers/Api/V1/ClubGovernanceController.php) | 22 / S |
| [ ] | R0361 | `PUT /api/v1/clubs/{club}/governance/bodies/{governanceBody}/assignments/{assignment}` | `api.v1.clubs.governance.assignments.update` | [ClubGovernanceController@updateAssignment](../app/Http/Controllers/Api/V1/ClubGovernanceController.php) | 22 / S |
| [ ] | R0362 | `DELETE /api/v1/clubs/{club}/governance/bodies/{governanceBody}/assignments/{assignment}` | `api.v1.clubs.governance.assignments.destroy` | [ClubGovernanceController@destroyAssignment](../app/Http/Controllers/Api/V1/ClubGovernanceController.php) | 22 / D |
| [ ] | R0363 | `GET\|HEAD /api/v1/clubs/{club}/governance/meetings` | `api.v1.clubs.governance.meetings.index` | [ClubGovernanceMeetingController@index](../app/Http/Controllers/Api/V1/ClubGovernanceMeetingController.php) | 27 / L |
| [ ] | R0364 | `POST /api/v1/clubs/{club}/governance/meetings` | `api.v1.clubs.governance.meetings.store` | [ClubGovernanceMeetingController@store](../app/Http/Controllers/Api/V1/ClubGovernanceMeetingController.php) | 27 / S |
| [ ] | R0365 | `GET\|HEAD /api/v1/clubs/{club}/governance/meetings/{meeting}` | `api.v1.clubs.governance.meetings.show` | [ClubGovernanceMeetingController@show](../app/Http/Controllers/Api/V1/ClubGovernanceMeetingController.php) | 27 / L |
| [ ] | R0366 | `PUT /api/v1/clubs/{club}/governance/meetings/{meeting}` | `api.v1.clubs.governance.meetings.update` | [ClubGovernanceMeetingController@update](../app/Http/Controllers/Api/V1/ClubGovernanceMeetingController.php) | 27 / S |
| [ ] | R0367 | `POST /api/v1/clubs/{club}/governance/meetings/{meeting}/decisions` | `api.v1.clubs.governance.meetings.decisions.store` | [ClubGovernanceMeetingController@storeDecision](../app/Http/Controllers/Api/V1/ClubGovernanceMeetingController.php) | 27 / S |
| [ ] | R0368 | `POST /api/v1/clubs/{club}/governance/meetings/{meeting}/decisions/{decision}/close` | `api.v1.clubs.governance.meetings.decisions.close` | [ClubGovernanceMeetingController@closeDecision](../app/Http/Controllers/Api/V1/ClubGovernanceMeetingController.php) | 27 / S |
| [ ] | R0369 | `POST /api/v1/clubs/{club}/governance/meetings/{meeting}/decisions/{decision}/vote` | `api.v1.clubs.governance.meetings.decisions.vote` | [ClubGovernanceMeetingController@castDecisionVote](../app/Http/Controllers/Api/V1/ClubGovernanceMeetingController.php) | 27 / S |
| [ ] | R0370 | `PATCH /api/v1/clubs/{club}/governance/meetings/{meeting}/recipients/{recipient}/delivery` | `api.v1.clubs.governance.meetings.recipients.delivery` | [ClubGovernanceMeetingController@updateRecipientDelivery](../app/Http/Controllers/Api/V1/ClubGovernanceMeetingController.php) | 27 / S |
| [ ] | R0371 | `POST /api/v1/clubs/{club}/images` | `api.v1.clubs.images.update` | [ClubController@updateImages](../app/Http/Controllers/Api/V1/ClubController.php) | 17, 18, 34 / S |
| [ ] | R0372 | `GET\|HEAD /api/v1/clubs/{club}/inventory` | `api.v1.clubs.inventory.index` | [ClubInventoryController@index](../app/Http/Controllers/Api/V1/ClubInventoryController.php) | 27 / L |
| [ ] | R0373 | `POST /api/v1/clubs/{club}/inventory` | `api.v1.clubs.inventory.store` | [ClubInventoryController@store](../app/Http/Controllers/Api/V1/ClubInventoryController.php) | 27 / S |
| [ ] | R0374 | `POST /api/v1/clubs/{club}/inventory/loans/{loan}/approve` | `api.v1.clubs.inventory.loans.approve` | [ClubInventoryController@approve](../app/Http/Controllers/Api/V1/ClubInventoryController.php) | 27 / S |
| [ ] | R0375 | `POST /api/v1/clubs/{club}/inventory/loans/{loan}/reject` | `api.v1.clubs.inventory.loans.reject` | [ClubInventoryController@reject](../app/Http/Controllers/Api/V1/ClubInventoryController.php) | 27 / S |
| [ ] | R0376 | `POST /api/v1/clubs/{club}/inventory/loans/{loan}/return` | `api.v1.clubs.inventory.loans.return` | [ClubInventoryController@returnLoan](../app/Http/Controllers/Api/V1/ClubInventoryController.php) | 27 / S |
| [ ] | R0377 | `PUT /api/v1/clubs/{club}/inventory/maintenance/{maintenance}` | `api.v1.clubs.inventory.maintenance.update` | [ClubInventoryController@updateMaintenance](../app/Http/Controllers/Api/V1/ClubInventoryController.php) | 27 / S |
| [ ] | R0378 | `POST /api/v1/clubs/{club}/inventory/scan` | `api.v1.clubs.inventory.scan` | [ClubInventoryController@scan](../app/Http/Controllers/Api/V1/ClubInventoryController.php) | 27 / S |
| [ ] | R0379 | `PUT /api/v1/clubs/{club}/inventory/{item}` | `api.v1.clubs.inventory.update` | [ClubInventoryController@update](../app/Http/Controllers/Api/V1/ClubInventoryController.php) | 27 / S |
| [ ] | R0380 | `DELETE /api/v1/clubs/{club}/inventory/{item}` | `api.v1.clubs.inventory.destroy` | [ClubInventoryController@destroy](../app/Http/Controllers/Api/V1/ClubInventoryController.php) | 27 / D |
| [ ] | R0381 | `POST /api/v1/clubs/{club}/inventory/{item}/checkout` | `api.v1.clubs.inventory.checkout` | [ClubInventoryController@checkout](../app/Http/Controllers/Api/V1/ClubInventoryController.php) | 27 / S |
| [ ] | R0382 | `POST /api/v1/clubs/{club}/inventory/{item}/damage-reports` | `api.v1.clubs.inventory.damage-reports.store` | [ClubInventoryController@reportDamage](../app/Http/Controllers/Api/V1/ClubInventoryController.php) | 27 / S |
| [ ] | R0383 | `POST /api/v1/clubs/{club}/inventory/{item}/maintenance` | `api.v1.clubs.inventory.maintenance.store` | [ClubInventoryController@storeMaintenance](../app/Http/Controllers/Api/V1/ClubInventoryController.php) | 27 / S |
| [ ] | R0384 | `POST /api/v1/clubs/{club}/inventory/{item}/movements` | `api.v1.clubs.inventory.movements.store` | [ClubInventoryController@recordMovement](../app/Http/Controllers/Api/V1/ClubInventoryController.php) | 27 / S |
| [ ] | R0385 | `POST /api/v1/clubs/{club}/inventory/{item}/qr/reissue` | `api.v1.clubs.inventory.qr.reissue` | [ClubInventoryController@reissueQr](../app/Http/Controllers/Api/V1/ClubInventoryController.php) | 27 / S |
| [ ] | R0386 | `POST /api/v1/clubs/{club}/inventory/{item}/qr/revoke` | `api.v1.clubs.inventory.qr.revoke` | [ClubInventoryController@revokeQr](../app/Http/Controllers/Api/V1/ClubInventoryController.php) | 27 / S |
| [ ] | R0387 | `POST /api/v1/clubs/{club}/invoices/{invoice}/dunning-events` | `api.v1.clubs.invoices.dunning-events.store` | [ClubDunningController@record](../app/Http/Controllers/Api/V1/ClubDunningController.php) | 20, 21 / S |
| [ ] | R0388 | `POST /api/v1/clubs/{club}/leave` | `api.v1.clubs.leave` | [ClubController@leaveClub](../app/Http/Controllers/Api/V1/ClubController.php) | 18, 08, 22 / S |
| [ ] | R0389 | `GET\|HEAD /api/v1/clubs/{club}/master-data-change-requests` | `api.v1.clubs.master-data-change-requests.index` | [ClubMasterDataChangeRequestController@index](../app/Http/Controllers/Api/V1/ClubMasterDataChangeRequestController.php) | 22 / L |
| [ ] | R0390 | `POST /api/v1/clubs/{club}/master-data-change-requests` | `api.v1.clubs.master-data-change-requests.store` | [ClubMasterDataChangeRequestController@store](../app/Http/Controllers/Api/V1/ClubMasterDataChangeRequestController.php) | 22 / S |
| [ ] | R0391 | `POST /api/v1/clubs/{club}/master-data-change-requests/{changeRequest}/approve` | `api.v1.clubs.master-data-change-requests.approve` | [ClubMasterDataChangeRequestController@approve](../app/Http/Controllers/Api/V1/ClubMasterDataChangeRequestController.php) | 22 / S |
| [ ] | R0392 | `POST /api/v1/clubs/{club}/master-data-change-requests/{changeRequest}/reject` | `api.v1.clubs.master-data-change-requests.reject` | [ClubMasterDataChangeRequestController@reject](../app/Http/Controllers/Api/V1/ClubMasterDataChangeRequestController.php) | 22 / S |
| [ ] | R0393 | `GET\|HEAD /api/v1/clubs/{club}/member-card` | `api.v1.clubs.member-card.show` | [ClubMemberCardController@show](../app/Http/Controllers/Api/V1/ClubMemberCardController.php) | 18, 08, 22 / L |
| [ ] | R0394 | `POST /api/v1/clubs/{club}/member-card/rotate` | `api.v1.clubs.member-card.rotate` | [ClubMemberCardController@rotate](../app/Http/Controllers/Api/V1/ClubMemberCardController.php) | 18, 08, 22 / S |
| [ ] | R0395 | `POST /api/v1/clubs/{club}/member-card/verify` | `api.v1.clubs.member-card.verify` | [ClubMemberCardController@verify](../app/Http/Controllers/Api/V1/ClubMemberCardController.php) | 18, 08, 22 / S |
| [ ] | R0396 | `GET\|HEAD /api/v1/clubs/{club}/member-qualifications` | `api.v1.clubs.member-qualifications.index` | [ClubMemberQualificationController@index](../app/Http/Controllers/Api/V1/ClubMemberQualificationController.php) | 18, 08, 22 / L |
| [ ] | R0397 | `POST /api/v1/clubs/{club}/member-qualifications` | `api.v1.clubs.member-qualifications.store` | [ClubMemberQualificationController@store](../app/Http/Controllers/Api/V1/ClubMemberQualificationController.php) | 18, 08, 22 / S |
| [ ] | R0398 | `PUT /api/v1/clubs/{club}/member-qualifications/{qualification}` | `api.v1.clubs.member-qualifications.update` | [ClubMemberQualificationController@update](../app/Http/Controllers/Api/V1/ClubMemberQualificationController.php) | 18, 08, 22 / S |
| [ ] | R0399 | `DELETE /api/v1/clubs/{club}/member-qualifications/{qualification}` | `api.v1.clubs.member-qualifications.destroy` | [ClubMemberQualificationController@destroy](../app/Http/Controllers/Api/V1/ClubMemberQualificationController.php) | 18, 08, 22 / D |
| [ ] | R0400 | `POST /api/v1/clubs/{club}/member-timeline` | `api.v1.clubs.member-timeline.store` | [ClubMemberTimelineController@store](../app/Http/Controllers/ClubMemberTimelineController.php) | 18, 08, 22 / S |
| [ ] | R0401 | `DELETE /api/v1/clubs/{club}/member-timeline/{timelineEntry}` | `api.v1.clubs.member-timeline.destroy` | [ClubMemberTimelineController@destroy](../app/Http/Controllers/ClubMemberTimelineController.php) | 18, 08, 22 / D |
| [ ] | R0402 | `GET\|HEAD /api/v1/clubs/{club}/members` | `api.v1.clubs.members.index` | [ClubController@members](../app/Http/Controllers/Api/V1/ClubController.php) | 18, 08, 22 / L |
| [ ] | R0403 | `POST /api/v1/clubs/{club}/members/bulk` | `api.v1.clubs.members.bulk.store` | [ClubController@storeExternalMembers](../app/Http/Controllers/Api/V1/ClubController.php) | 18, 08, 22 / S |
| [ ] | R0404 | `POST /api/v1/clubs/{club}/members/import` | `api.v1.clubs.members.import` | [ClubController@importMembers](../app/Http/Controllers/Api/V1/ClubController.php) | 18, 08, 22 / S |
| [ ] | R0405 | `POST /api/v1/clubs/{club}/members/import-preview` | `api.v1.clubs.members.import-preview` | [ClubController@previewMemberImport](../app/Http/Controllers/Api/V1/ClubController.php) | 18, 08, 22 / S |
| [ ] | R0406 | `POST /api/v1/clubs/{club}/members/invite` | `api.v1.clubs.members.invite` | [ClubController@inviteMember](../app/Http/Controllers/Api/V1/ClubController.php) | 18, 08, 22 / S |
| [ ] | R0407 | `POST /api/v1/clubs/{club}/members/invite-token` | `api.v1.clubs.members.invite.token` | [ClubController@inviteMemberWithToken](../app/Http/Controllers/Api/V1/ClubController.php) | 18, 08, 22 / S |
| [ ] | R0408 | `GET\|HEAD /api/v1/clubs/{club}/members/{child}/guardians` | `api.v1.clubs.members.guardians.index` | [ClubGuardianRelationshipController@index](../app/Http/Controllers/Api/V1/ClubGuardianRelationshipController.php) | 18, 08, 22 / L |
| [ ] | R0409 | `POST /api/v1/clubs/{club}/members/{child}/guardians` | `api.v1.clubs.members.guardians.store` | [ClubGuardianRelationshipController@store](../app/Http/Controllers/Api/V1/ClubGuardianRelationshipController.php) | 18, 08, 22 / S |
| [ ] | R0410 | `POST /api/v1/clubs/{club}/members/{child}/guardians/{relationship}/accept` | `api.v1.clubs.members.guardians.accept` | [ClubGuardianRelationshipController@accept](../app/Http/Controllers/Api/V1/ClubGuardianRelationshipController.php) | 18, 08, 22 / S |
| [ ] | R0411 | `POST /api/v1/clubs/{club}/members/{child}/guardians/{relationship}/decline` | `api.v1.clubs.members.guardians.decline` | [ClubGuardianRelationshipController@decline](../app/Http/Controllers/Api/V1/ClubGuardianRelationshipController.php) | 18, 08, 22 / S |
| [ ] | R0412 | `POST /api/v1/clubs/{club}/members/{child}/guardians/{relationship}/primary` | `api.v1.clubs.members.guardians.primary` | [ClubGuardianRelationshipController@primary](../app/Http/Controllers/Api/V1/ClubGuardianRelationshipController.php) | 18, 08, 22 / S |
| [ ] | R0413 | `POST /api/v1/clubs/{club}/members/{child}/guardians/{relationship}/revoke` | `api.v1.clubs.members.guardians.revoke` | [ClubGuardianRelationshipController@revoke](../app/Http/Controllers/Api/V1/ClubGuardianRelationshipController.php) | 18, 08, 22 / S |
| [ ] | R0414 | `PUT /api/v1/clubs/{club}/members/{user}` | `api.v1.clubs.members.update` | [ClubController@updateMemberDetails](../app/Http/Controllers/Api/V1/ClubController.php) | 18, 08, 22 / S |
| [ ] | R0415 | `DELETE /api/v1/clubs/{club}/members/{user}` | `api.v1.clubs.members.destroy` | [ClubController@removeClubMember](../app/Http/Controllers/Api/V1/ClubController.php) | 18, 08, 22 / D |
| [ ] | R0416 | `POST /api/v1/clubs/{club}/members/{user}/invoices` | `api.v1.clubs.members.invoices.store` | [ClubController@createMemberInvoice](../app/Http/Controllers/Api/V1/ClubController.php) | 20, 21 / S |
| [ ] | R0417 | `POST /api/v1/clubs/{club}/members/{user}/member-number` | `api.v1.clubs.members.member-number.store` | [ClubController@generateMemberNumber](../app/Http/Controllers/Api/V1/ClubController.php) | 18, 08, 22 / S |
| [ ] | R0418 | `GET\|HEAD /api/v1/clubs/{club}/members/{user}/permissions` | `api.v1.clubs.members.permissions.show` | [ClubController@memberPermissions](../app/Http/Controllers/Api/V1/ClubController.php) | 18, 08, 22 / L |
| [ ] | R0419 | `PUT /api/v1/clubs/{club}/members/{user}/permissions` | `api.v1.clubs.members.permissions.update` | [ClubController@updateMemberPermissions](../app/Http/Controllers/Api/V1/ClubController.php) | 18, 08, 22 / S |
| [ ] | R0420 | `GET\|HEAD /api/v1/clubs/{club}/members/{user}/relationships` | `api.v1.clubs.members.relationships.index` | [ClubMemberRelationshipController@index](../app/Http/Controllers/Api/V1/ClubMemberRelationshipController.php) | 18, 08, 22 / L |
| [ ] | R0421 | `POST /api/v1/clubs/{club}/members/{user}/relationships` | `api.v1.clubs.members.relationships.store` | [ClubMemberRelationshipController@store](../app/Http/Controllers/Api/V1/ClubMemberRelationshipController.php) | 18, 08, 22 / S |
| [ ] | R0422 | `PUT /api/v1/clubs/{club}/members/{user}/role` | `api.v1.clubs.members.role.update` | [ClubController@updateMemberRole](../app/Http/Controllers/Api/V1/ClubController.php) | 18, 08, 22 / S |
| [ ] | R0423 | `GET\|HEAD /api/v1/clubs/{club}/members/{user}/role-definitions` | `api.v1.clubs.members.role-definitions.show` | [ClubRoleDefinitionController@memberAssignments](../app/Http/Controllers/Api/V1/ClubRoleDefinitionController.php) | 22 / L |
| [ ] | R0424 | `PUT /api/v1/clubs/{club}/members/{user}/role-definitions` | `api.v1.clubs.members.role-definitions.update` | [ClubRoleDefinitionController@updateMemberAssignments](../app/Http/Controllers/Api/V1/ClubRoleDefinitionController.php) | 22 / S |
| [ ] | R0425 | `GET\|HEAD /api/v1/clubs/{club}/members/{user}/volunteer-profile` | `api.v1.clubs.members.volunteer-profile.show` | [ClubVolunteerProfileController@showMember](../app/Http/Controllers/Api/V1/ClubVolunteerProfileController.php) | 27 / L |
| [ ] | R0426 | `POST /api/v1/clubs/{club}/membership-change-requests` | `api.v1.clubs.membership-change-requests.store` | [ClubController@requestMembershipChange](../app/Http/Controllers/Api/V1/ClubController.php) | 18, 08, 22 / S |
| [ ] | R0427 | `GET\|HEAD /api/v1/clubs/{club}/membership-invoices/{invoice}/download` | `api.v1.clubs.membership-invoices.download` | [ClubController@downloadMembershipInvoice](../app/Http/Controllers/Api/V1/ClubController.php) | 20, 21 / L |
| [ ] | R0428 | `POST /api/v1/clubs/{club}/membership-invoices/{invoice}/download-authorizations` | `api.v1.clubs.membership-invoices.download-authorizations.store` | [ClubController@membershipInvoiceDownloadAuthorization](../app/Http/Controllers/Api/V1/ClubController.php) | 20, 21 / S |
| [ ] | R0429 | `POST /api/v1/clubs/{club}/membership-invoices/{invoice}/dunning-events` | `api.v1.clubs.membership-invoices.dunning-events.store` | [ClubDunningController@record](../app/Http/Controllers/Api/V1/ClubDunningController.php) | 20, 21 / S |
| [ ] | R0430 | `POST /api/v1/clubs/{club}/membership-invoices/{invoice}/payments` | `api.v1.clubs.membership-invoices.payments.store` | [ClubController@recordMembershipPayment](../app/Http/Controllers/Api/V1/ClubController.php) | 20, 21 / S |
| [ ] | R0431 | `POST /api/v1/clubs/{club}/membership-invoices/{invoice}/reminder` | `api.v1.clubs.membership-invoices.reminder.store` | [ClubController@sendMembershipInvoiceReminder](../app/Http/Controllers/Api/V1/ClubController.php) | 20, 21 / S |
| [ ] | R0432 | `PUT /api/v1/clubs/{club}/membership-invoices/{invoice}/status` | `api.v1.clubs.membership-invoices.status.update` | [ClubController@updateMembershipInvoiceStatus](../app/Http/Controllers/Api/V1/ClubController.php) | 20, 21 / S |
| [ ] | R0433 | `GET\|HEAD /api/v1/clubs/{club}/membership-prospects` | `api.v1.clubs.membership-prospects.index` | [ClubMembershipProspectController@index](../app/Http/Controllers/Api/V1/ClubMembershipProspectController.php) | 18, 08, 22 / L |
| [ ] | R0434 | `POST /api/v1/clubs/{club}/membership-prospects` | `api.v1.clubs.membership-prospects.store` | [ClubMembershipProspectController@store](../app/Http/Controllers/Api/V1/ClubMembershipProspectController.php) | 18, 08, 22 / S |
| [ ] | R0435 | `PUT /api/v1/clubs/{club}/membership-prospects/{prospect}` | `api.v1.clubs.membership-prospects.update` | [ClubMembershipProspectController@update](../app/Http/Controllers/Api/V1/ClubMembershipProspectController.php) | 18, 08, 22 / S |
| [ ] | R0436 | `POST /api/v1/clubs/{club}/membership-prospects/{prospect}/archive` | `api.v1.clubs.membership-prospects.archive` | [ClubMembershipProspectController@archive](../app/Http/Controllers/Api/V1/ClubMembershipProspectController.php) | 18, 08, 22 / S |
| [ ] | R0437 | `GET\|HEAD /api/v1/clubs/{club}/membership-requests` | `api.v1.clubs.membership-requests.index` | [ClubController@membershipRequests](../app/Http/Controllers/Api/V1/ClubController.php) | 18, 08, 22 / L |
| [ ] | R0438 | `POST /api/v1/clubs/{club}/membership-requests` | `api.v1.clubs.membership-requests.store` | [ClubController@storeMembershipRequest](../app/Http/Controllers/Api/V1/ClubController.php) | 18, 08, 22 / S |
| [ ] | R0439 | `DELETE /api/v1/clubs/{club}/membership-requests` | `api.v1.clubs.membership-requests.withdraw` | [ClubController@withdrawMembershipRequest](../app/Http/Controllers/Api/V1/ClubController.php) | 18, 08, 22 / D |
| [ ] | R0440 | `POST /api/v1/clubs/{club}/membership-requests/{membershipRequest}/approve` | `api.v1.clubs.membership-requests.approve` | [ClubController@approveMembershipRequest](../app/Http/Controllers/Api/V1/ClubController.php) | 18, 08, 22 / S |
| [ ] | R0441 | `POST /api/v1/clubs/{club}/membership-requests/{membershipRequest}/decline` | `api.v1.clubs.membership-requests.decline` | [ClubController@declineMembershipRequest](../app/Http/Controllers/Api/V1/ClubController.php) | 18, 08, 22 / S |
| [ ] | R0442 | `POST /api/v1/clubs/{club}/membership-requests/{membershipRequest}/request-information` | `api.v1.clubs.membership-requests.request-information` | [ClubController@requestMembershipInformation](../app/Http/Controllers/Api/V1/ClubController.php) | 18, 08, 22 / S |
| [ ] | R0443 | `POST /api/v1/clubs/{club}/membership-requests/{membershipRequest}/respond` | `api.v1.clubs.membership-requests.respond` | [ClubController@respondToMembershipInformation](../app/Http/Controllers/Api/V1/ClubController.php) | 18, 08, 22 / S |
| [ ] | R0444 | `POST /api/v1/clubs/{club}/membership-requests/{membershipRequest}/waitlist` | `api.v1.clubs.membership-requests.waitlist` | [ClubController@waitlistMembershipRequest](../app/Http/Controllers/Api/V1/ClubController.php) | 18, 08, 22 / S |
| [ ] | R0445 | `POST /api/v1/clubs/{club}/membership/contribution-rules` | `api.v1.clubs.membership.contribution-rules.store` | [ClubController@storeContributionRule](../app/Http/Controllers/Api/V1/ClubController.php) | 19 / S |
| [ ] | R0446 | `PUT /api/v1/clubs/{club}/membership/contribution-rules/{contributionRule}` | `api.v1.clubs.membership.contribution-rules.update` | [ClubController@updateContributionRule](../app/Http/Controllers/Api/V1/ClubController.php) | 19 / S |
| [ ] | R0447 | `GET\|HEAD /api/v1/clubs/{club}/membership/datev-export` | `api.v1.clubs.membership.datev-export` | [ClubController@exportDatev](../app/Http/Controllers/Api/V1/ClubController.php) | 21 / L |
| [ ] | R0448 | `PUT /api/v1/clubs/{club}/membership/datev-settings` | `api.v1.clubs.membership.datev-settings.update` | [ClubController@updateDatevSettingsApi](../app/Http/Controllers/Api/V1/ClubController.php) | 21 / S |
| [ ] | R0449 | `GET\|HEAD /api/v1/clubs/{club}/membership/sepa-export` | `api.v1.clubs.membership.sepa-export` | [ClubController@exportSepaDebit](../app/Http/Controllers/Api/V1/ClubController.php) | 21 / L |
| [ ] | R0450 | `PUT /api/v1/clubs/{club}/membership/sepa-settings` | `api.v1.clubs.membership.sepa-settings.update` | [ClubController@updateSepaSettingsApi](../app/Http/Controllers/Api/V1/ClubController.php) | 21 / S |
| [ ] | R0451 | `PUT /api/v1/clubs/{club}/membership/settings` | `api.v1.clubs.membership.settings.update` | [ClubController@updateMembershipSettings](../app/Http/Controllers/Api/V1/ClubController.php) | 19 / S |
| [ ] | R0452 | `POST /api/v1/clubs/{club}/membership/types` | `api.v1.clubs.membership.types.store` | [ClubController@storeMembershipType](../app/Http/Controllers/Api/V1/ClubController.php) | 19 / S |
| [ ] | R0453 | `PUT /api/v1/clubs/{club}/membership/types/{membershipType}` | `api.v1.clubs.membership.types.update` | [ClubController@updateMembershipType](../app/Http/Controllers/Api/V1/ClubController.php) | 19 / S |
| [ ] | R0454 | `GET\|HEAD /api/v1/clubs/{club}/metadata` | `api.v1.clubs.metadata.index` | [ClubMetadataController@index](../app/Http/Controllers/Api/V1/ClubMetadataController.php) | 22 / L |
| [ ] | R0455 | `POST /api/v1/clubs/{club}/metadata/categories` | `api.v1.clubs.metadata.categories.store` | [ClubMetadataController@storeCategory](../app/Http/Controllers/Api/V1/ClubMetadataController.php) | 22 / S |
| [ ] | R0456 | `PUT /api/v1/clubs/{club}/metadata/categories/{category}` | `api.v1.clubs.metadata.categories.update` | [ClubMetadataController@updateCategory](../app/Http/Controllers/Api/V1/ClubMetadataController.php) | 22 / S |
| [ ] | R0457 | `DELETE /api/v1/clubs/{club}/metadata/categories/{category}` | `api.v1.clubs.metadata.categories.destroy` | [ClubMetadataController@destroyCategory](../app/Http/Controllers/Api/V1/ClubMetadataController.php) | 22 / D |
| [ ] | R0458 | `POST /api/v1/clubs/{club}/metadata/custom-fields` | `api.v1.clubs.metadata.custom-fields.store` | [ClubMetadataController@storeCustomField](../app/Http/Controllers/Api/V1/ClubMetadataController.php) | 22 / S |
| [ ] | R0459 | `PUT /api/v1/clubs/{club}/metadata/custom-fields/{customField}` | `api.v1.clubs.metadata.custom-fields.update` | [ClubMetadataController@updateCustomField](../app/Http/Controllers/Api/V1/ClubMetadataController.php) | 22 / S |
| [ ] | R0460 | `DELETE /api/v1/clubs/{club}/metadata/custom-fields/{customField}` | `api.v1.clubs.metadata.custom-fields.destroy` | [ClubMetadataController@destroyCustomField](../app/Http/Controllers/Api/V1/ClubMetadataController.php) | 22 / D |
| [ ] | R0461 | `POST /api/v1/clubs/{club}/metadata/number-ranges` | `api.v1.clubs.metadata.number-ranges.store` | [ClubMetadataController@storeNumberRange](../app/Http/Controllers/Api/V1/ClubMetadataController.php) | 22 / S |
| [ ] | R0462 | `PUT /api/v1/clubs/{club}/metadata/number-ranges/{numberRange}` | `api.v1.clubs.metadata.number-ranges.update` | [ClubMetadataController@updateNumberRange](../app/Http/Controllers/Api/V1/ClubMetadataController.php) | 22 / S |
| [ ] | R0463 | `DELETE /api/v1/clubs/{club}/metadata/number-ranges/{numberRange}` | `api.v1.clubs.metadata.number-ranges.destroy` | [ClubMetadataController@destroyNumberRange](../app/Http/Controllers/Api/V1/ClubMetadataController.php) | 22 / D |
| [ ] | R0464 | `POST /api/v1/clubs/{club}/metadata/number-ranges/{numberRange}/allocate` | `api.v1.clubs.metadata.number-ranges.allocate` | [ClubMetadataController@allocateNumber](../app/Http/Controllers/Api/V1/ClubMetadataController.php) | 22 / S |
| [ ] | R0465 | `PUT /api/v1/clubs/{club}/metadata/number-ranges/{numberRange}/default` | `api.v1.clubs.metadata.number-ranges.default.set` | [ClubMetadataController@setDefaultNumberRange](../app/Http/Controllers/Api/V1/ClubMetadataController.php) | 22 / S |
| [ ] | R0466 | `DELETE /api/v1/clubs/{club}/metadata/number-ranges/{numberRange}/default` | `api.v1.clubs.metadata.number-ranges.default.clear` | [ClubMetadataController@clearDefaultNumberRange](../app/Http/Controllers/Api/V1/ClubMetadataController.php) | 22 / D |
| [ ] | R0467 | `GET\|HEAD /api/v1/clubs/{club}/metadata/subjects/{subjectType}/{subjectId}` | `api.v1.clubs.metadata.subjects.show` | [ClubMetadataController@showSubject](../app/Http/Controllers/Api/V1/ClubMetadataController.php) | 22 / L |
| [ ] | R0468 | `PUT /api/v1/clubs/{club}/metadata/subjects/{subjectType}/{subjectId}` | `api.v1.clubs.metadata.subjects.update` | [ClubMetadataController@updateSubject](../app/Http/Controllers/Api/V1/ClubMetadataController.php) | 22 / S |
| [ ] | R0469 | `POST /api/v1/clubs/{club}/newsletter/deliveries/{delivery}/bounce` | `api.v1.clubs.newsletter.deliveries.bounce` | [ClubNewsletterController@bounce](../app/Http/Controllers/Api/V1/ClubNewsletterController.php) | 26 / S |
| [ ] | R0470 | `POST /api/v1/clubs/{club}/newsletter/send` | `api.v1.clubs.newsletter.send` | [ClubNewsletterController@send](../app/Http/Controllers/Api/V1/ClubNewsletterController.php) | 26 / S |
| [ ] | R0471 | `POST /api/v1/clubs/{club}/newsletter/subscribe` | `api.v1.clubs.newsletter.subscribe` | [ClubNewsletterController@subscribe](../app/Http/Controllers/Api/V1/ClubNewsletterController.php) | 26 / S |
| [ ] | R0472 | `POST /api/v1/clubs/{club}/newsletter/suppressions` | `api.v1.clubs.newsletter.suppressions.store` | [ClubNewsletterController@suppress](../app/Http/Controllers/Api/V1/ClubNewsletterController.php) | 26 / S |
| [ ] | R0473 | `GET\|HEAD /api/v1/clubs/{club}/organization` | `api.v1.clubs.organization.index` | [ClubOrganizationController@index](../app/Http/Controllers/Api/V1/ClubOrganizationController.php) | 22 / L |
| [ ] | R0474 | `POST /api/v1/clubs/{club}/organization/departments` | `api.v1.clubs.organization.departments.store` | [ClubOrganizationController@storeDepartment](../app/Http/Controllers/Api/V1/ClubOrganizationController.php) | 22 / S |
| [ ] | R0475 | `PUT /api/v1/clubs/{club}/organization/departments/{department}` | `api.v1.clubs.organization.departments.update` | [ClubOrganizationController@updateDepartment](../app/Http/Controllers/Api/V1/ClubOrganizationController.php) | 22 / S |
| [ ] | R0476 | `DELETE /api/v1/clubs/{club}/organization/departments/{department}` | `api.v1.clubs.organization.departments.destroy` | [ClubOrganizationController@destroyDepartment](../app/Http/Controllers/Api/V1/ClubOrganizationController.php) | 22 / D |
| [ ] | R0477 | `POST /api/v1/clubs/{club}/organization/locations` | `api.v1.clubs.organization.locations.store` | [ClubOrganizationController@storeLocation](../app/Http/Controllers/Api/V1/ClubOrganizationController.php) | 22 / S |
| [ ] | R0478 | `PUT /api/v1/clubs/{club}/organization/locations/{location}` | `api.v1.clubs.organization.locations.update` | [ClubOrganizationController@updateLocation](../app/Http/Controllers/Api/V1/ClubOrganizationController.php) | 22 / S |
| [ ] | R0479 | `DELETE /api/v1/clubs/{club}/organization/locations/{location}` | `api.v1.clubs.organization.locations.destroy` | [ClubOrganizationController@destroyLocation](../app/Http/Controllers/Api/V1/ClubOrganizationController.php) | 22 / D |
| [ ] | R0480 | `POST /api/v1/clubs/{club}/organization/training-groups` | `api.v1.clubs.organization.training-groups.store` | [ClubOrganizationController@storeTrainingGroup](../app/Http/Controllers/Api/V1/ClubOrganizationController.php) | 22 / S |
| [ ] | R0481 | `PUT /api/v1/clubs/{club}/organization/training-groups/{trainingGroup}` | `api.v1.clubs.organization.training-groups.update` | [ClubOrganizationController@updateTrainingGroup](../app/Http/Controllers/Api/V1/ClubOrganizationController.php) | 22 / S |
| [ ] | R0482 | `DELETE /api/v1/clubs/{club}/organization/training-groups/{trainingGroup}` | `api.v1.clubs.organization.training-groups.destroy` | [ClubOrganizationController@destroyTrainingGroup](../app/Http/Controllers/Api/V1/ClubOrganizationController.php) | 22 / D |
| [ ] | R0483 | `POST /api/v1/clubs/{club}/pause-requests` | `api.v1.clubs.pause-requests.store` | [ClubController@storePauseRequest](../app/Http/Controllers/Api/V1/ClubController.php) | 18, 08, 22 / S |
| [ ] | R0484 | `PUT /api/v1/clubs/{club}/payments/{payment}` | `api.v1.clubs.payments.update` | [ClubController@updatePayment](../app/Http/Controllers/Api/V1/ClubController.php) | 20, 21 / S |
| [ ] | R0485 | `GET\|HEAD /api/v1/clubs/{club}/permission-delegations` | `api.v1.clubs.permission-delegations.index` | [ClubPermissionDelegationController@index](../app/Http/Controllers/Api/V1/ClubPermissionDelegationController.php) | 22 / L |
| [ ] | R0486 | `POST /api/v1/clubs/{club}/permission-delegations` | `api.v1.clubs.permission-delegations.store` | [ClubPermissionDelegationController@store](../app/Http/Controllers/Api/V1/ClubPermissionDelegationController.php) | 22 / S |
| [ ] | R0487 | `POST /api/v1/clubs/{club}/permission-delegations/{delegation}/revoke` | `api.v1.clubs.permission-delegations.revoke` | [ClubPermissionDelegationController@revoke](../app/Http/Controllers/Api/V1/ClubPermissionDelegationController.php) | 22 / S |
| [ ] | R0488 | `GET\|HEAD /api/v1/clubs/{club}/policy-documents` | `api.v1.clubs.policy-documents.index` | [ClubPolicyDocumentController@index](../app/Http/Controllers/Api/V1/ClubPolicyDocumentController.php) | 22 / L |
| [ ] | R0489 | `POST /api/v1/clubs/{club}/policy-documents` | `api.v1.clubs.policy-documents.store` | [ClubPolicyDocumentController@store](../app/Http/Controllers/Api/V1/ClubPolicyDocumentController.php) | 22 / S |
| [ ] | R0490 | `PUT /api/v1/clubs/{club}/policy-documents/{policyDocument}` | `api.v1.clubs.policy-documents.update` | [ClubPolicyDocumentController@update](../app/Http/Controllers/Api/V1/ClubPolicyDocumentController.php) | 22 / S+X |
| [ ] | R0491 | `DELETE /api/v1/clubs/{club}/policy-documents/{policyDocument}` | `api.v1.clubs.policy-documents.destroy` | [ClubPolicyDocumentController@destroy](../app/Http/Controllers/Api/V1/ClubPolicyDocumentController.php) | 22 / D+X |
| [ ] | R0492 | `GET\|HEAD /api/v1/clubs/{club}/policy-documents/{policyDocument}/download` | `api.v1.clubs.policy-documents.download` | [ClubPolicyDocumentController@download](../app/Http/Controllers/Api/V1/ClubPolicyDocumentController.php) | 22 / L+X |
| [ ] | R0493 | `POST /api/v1/clubs/{club}/prepayments` | `api.v1.clubs.prepayments.store` | [ClubController@recordPrepayment](../app/Http/Controllers/Api/V1/ClubController.php) | 21 / S |
| [ ] | R0494 | `GET\|HEAD /api/v1/clubs/{club}/procurements` | `api.v1.clubs.procurements.index` | [ClubProcurementController@index](../app/Http/Controllers/Api/V1/ClubProcurementController.php) | 27 / L |
| [ ] | R0495 | `POST /api/v1/clubs/{club}/procurements` | `api.v1.clubs.procurements.store` | [ClubProcurementController@store](../app/Http/Controllers/Api/V1/ClubProcurementController.php) | 27 / S |
| [ ] | R0496 | `PUT /api/v1/clubs/{club}/procurements/{procurement}/approval` | `api.v1.clubs.procurements.approval.update` | [ClubProcurementController@approve](../app/Http/Controllers/Api/V1/ClubProcurementController.php) | 27 / S |
| [ ] | R0497 | `POST /api/v1/clubs/{club}/procurements/{procurement}/order` | `api.v1.clubs.procurements.order` | [ClubProcurementController@order](../app/Http/Controllers/Api/V1/ClubProcurementController.php) | 27 / S |
| [ ] | R0498 | `POST /api/v1/clubs/{club}/procurements/{procurement}/receipts` | `api.v1.clubs.procurements.receipts.store` | [ClubProcurementController@receive](../app/Http/Controllers/Api/V1/ClubProcurementController.php) | 27 / S |
| [ ] | R0499 | `POST /api/v1/clubs/{club}/receipt-uploads` | `api.v1.clubs.receipt-uploads.store` | [ClubController@uploadReceipt](../app/Http/Controllers/Api/V1/ClubController.php) | 21 / S |
| [ ] | R0500 | `POST /api/v1/clubs/{club}/receipt-uploads/{receiptUpload}/confirm` | `api.v1.clubs.receipt-uploads.confirm` | [ClubController@confirmReceipt](../app/Http/Controllers/Api/V1/ClubController.php) | 21 / S |
| [ ] | R0501 | `POST /api/v1/clubs/{club}/removal-objections` | `api.v1.clubs.removal-objections.store` | [ClubController@objectToRemoval](../app/Http/Controllers/Api/V1/ClubController.php) | 18, 08, 22 / S |
| [ ] | R0502 | `GET\|HEAD /api/v1/clubs/{club}/role-definitions` | `api.v1.clubs.role-definitions.index` | [ClubRoleDefinitionController@index](../app/Http/Controllers/Api/V1/ClubRoleDefinitionController.php) | 22 / L |
| [ ] | R0503 | `POST /api/v1/clubs/{club}/role-definitions` | `api.v1.clubs.role-definitions.store` | [ClubRoleDefinitionController@store](../app/Http/Controllers/Api/V1/ClubRoleDefinitionController.php) | 22 / S |
| [ ] | R0504 | `PUT /api/v1/clubs/{club}/role-definitions/{roleDefinition}` | `api.v1.clubs.role-definitions.update` | [ClubRoleDefinitionController@update](../app/Http/Controllers/Api/V1/ClubRoleDefinitionController.php) | 22 / S |
| [ ] | R0505 | `DELETE /api/v1/clubs/{club}/role-definitions/{roleDefinition}` | `api.v1.clubs.role-definitions.destroy` | [ClubRoleDefinitionController@destroy](../app/Http/Controllers/Api/V1/ClubRoleDefinitionController.php) | 22 / D |
| [ ] | R0506 | `GET\|HEAD /api/v1/clubs/{club}/sepa-batches` | `api.v1.clubs.sepa-batches.index` | [ClubSepaBatchController@index](../app/Http/Controllers/Api/V1/ClubSepaBatchController.php) | 21 / L |
| [ ] | R0507 | `POST /api/v1/clubs/{club}/sepa-batches` | `api.v1.clubs.sepa-batches.store` | [ClubSepaBatchController@store](../app/Http/Controllers/Api/V1/ClubSepaBatchController.php) | 21 / S |
| [ ] | R0508 | `POST /api/v1/clubs/{club}/sepa-batches/{batch}/approve` | `api.v1.clubs.sepa-batches.approve` | [ClubSepaBatchController@approve](../app/Http/Controllers/Api/V1/ClubSepaBatchController.php) | 21 / S |
| [ ] | R0509 | `POST /api/v1/clubs/{club}/sepa-batches/{batch}/cancel` | `api.v1.clubs.sepa-batches.cancel` | [ClubSepaBatchController@cancel](../app/Http/Controllers/Api/V1/ClubSepaBatchController.php) | 21 / S |
| [ ] | R0510 | `POST /api/v1/clubs/{club}/sepa-batches/{batch}/export` | `api.v1.clubs.sepa-batches.export` | [ClubSepaBatchController@export](../app/Http/Controllers/Api/V1/ClubSepaBatchController.php) | 21 / S |
| [ ] | R0511 | `POST /api/v1/clubs/{club}/sepa-batches/{batch}/items/{item}/fee` | `api.v1.clubs.sepa-batches.items.fee` | [ClubSepaBatchController@recordFee](../app/Http/Controllers/Api/V1/ClubSepaBatchController.php) | 21 / S |
| [ ] | R0512 | `POST /api/v1/clubs/{club}/sepa-batches/{batch}/items/{item}/fee-corrections` | `api.v1.clubs.sepa-batches.items.fee-corrections` | [ClubSepaBatchController@correctFee](../app/Http/Controllers/Api/V1/ClubSepaBatchController.php) | 21 / S |
| [ ] | R0513 | `GET\|HEAD /api/v1/clubs/{club}/sepa-batches/{batch}/items/{item}/fee-options` | `api.v1.clubs.sepa-batches.items.fee-options` | [ClubSepaBatchController@feeOptions](../app/Http/Controllers/Api/V1/ClubSepaBatchController.php) | 21 / L |
| [ ] | R0514 | `POST /api/v1/clubs/{club}/sepa-batches/{batch}/items/{item}/fee-recharges` | `api.v1.clubs.sepa-batches.items.fee-recharges` | [ClubSepaBatchController@proposeFeeRecharge](../app/Http/Controllers/Api/V1/ClubSepaBatchController.php) | 21 / S |
| [ ] | R0515 | `POST /api/v1/clubs/{club}/sepa-batches/{batch}/items/{item}/fee-recharges/{proposal}/approve` | `api.v1.clubs.sepa-batches.items.fee-recharges.approve` | [ClubSepaBatchController@approveFeeRecharge](../app/Http/Controllers/Api/V1/ClubSepaBatchController.php) | 21 / S |
| [ ] | R0516 | `POST /api/v1/clubs/{club}/sepa-batches/{batch}/items/{item}/fee-recharges/{proposal}/cancel` | `api.v1.clubs.sepa-batches.items.fee-recharges.cancel` | [ClubSepaBatchController@cancelFeeRecharge](../app/Http/Controllers/Api/V1/ClubSepaBatchController.php) | 21 / S |
| [ ] | R0517 | `POST /api/v1/clubs/{club}/sepa-batches/{batch}/items/{item}/fee-recharges/{proposal}/credit-requests` | `api.v1.clubs.sepa-batches.items.fee-recharges.credit.request` | [ClubSepaBatchController@requestFeeRechargeCredit](../app/Http/Controllers/Api/V1/ClubSepaBatchController.php) | 21 / S |
| [ ] | R0518 | `POST /api/v1/clubs/{club}/sepa-batches/{batch}/items/{item}/fee-recharges/{proposal}/credit-requests/{creditRequest}/approve` | `api.v1.clubs.sepa-batches.items.fee-recharges.credit.approve` | [ClubSepaBatchController@approveFeeRechargeCredit](../app/Http/Controllers/Api/V1/ClubSepaBatchController.php) | 21 / S |
| [ ] | R0519 | `GET\|HEAD /api/v1/clubs/{club}/sepa-batches/{batch}/items/{item}/fee-recharges/{proposal}/credit-requests/{creditRequest}/document` | `api.v1.clubs.sepa-batches.items.fee-recharges.credit.document` | [ClubSepaBatchController@downloadFeeRechargeCredit](../app/Http/Controllers/Api/V1/ClubSepaBatchController.php) | 21 / L |
| [ ] | R0520 | `GET\|HEAD /api/v1/clubs/{club}/sepa-batches/{batch}/items/{item}/fee-recharges/{proposal}/credit-requests/{creditRequest}/document-link` | `api.v1.clubs.sepa-batches.items.fee-recharges.credit.document-link` | [ClubSepaBatchController@feeRechargeCreditDocumentLink](../app/Http/Controllers/Api/V1/ClubSepaBatchController.php) | 21 / L |
| [ ] | R0521 | `POST /api/v1/clubs/{club}/sepa-batches/{batch}/items/{item}/fee-recharges/{proposal}/credit-requests/{creditRequest}/refund` | `api.v1.clubs.sepa-batches.items.fee-recharges.credit.refund` | [ClubSepaBatchController@recordFeeRechargeRefund](../app/Http/Controllers/Api/V1/ClubSepaBatchController.php) | 21 / S |
| [ ] | R0522 | `POST /api/v1/clubs/{club}/sepa-batches/{batch}/items/{item}/fee-recharges/{proposal}/credit-requests/{creditRequest}/withdraw` | `api.v1.clubs.sepa-batches.items.fee-recharges.credit.withdraw` | [ClubSepaBatchController@withdrawFeeRechargeCredit](../app/Http/Controllers/Api/V1/ClubSepaBatchController.php) | 21 / S |
| [ ] | R0523 | `POST /api/v1/clubs/{club}/sepa-batches/{batch}/items/{item}/fee-recharges/{proposal}/void-requests` | `api.v1.clubs.sepa-batches.items.fee-recharges.void.request` | [ClubSepaBatchController@requestFeeRechargeVoid](../app/Http/Controllers/Api/V1/ClubSepaBatchController.php) | 21 / S |
| [ ] | R0524 | `POST /api/v1/clubs/{club}/sepa-batches/{batch}/items/{item}/fee-recharges/{proposal}/void-requests/{voidRequest}/approve` | `api.v1.clubs.sepa-batches.items.fee-recharges.void.approve` | [ClubSepaBatchController@approveFeeRechargeVoid](../app/Http/Controllers/Api/V1/ClubSepaBatchController.php) | 21 / S |
| [ ] | R0525 | `POST /api/v1/clubs/{club}/sepa-batches/{batch}/items/{item}/fee-recharges/{proposal}/void-requests/{voidRequest}/withdraw` | `api.v1.clubs.sepa-batches.items.fee-recharges.void.withdraw` | [ClubSepaBatchController@withdrawFeeRechargeVoid](../app/Http/Controllers/Api/V1/ClubSepaBatchController.php) | 21 / S |
| [ ] | R0526 | `POST /api/v1/clubs/{club}/sepa-batches/{batch}/items/{item}/retry` | `api.v1.clubs.sepa-batches.items.retry` | [ClubSepaBatchController@authorizeItemRetry](../app/Http/Controllers/Api/V1/ClubSepaBatchController.php) | 21 / S |
| [ ] | R0527 | `POST /api/v1/clubs/{club}/sepa-batches/{batch}/items/{item}/return` | `api.v1.clubs.sepa-batches.items.return` | [ClubSepaBatchController@returnItem](../app/Http/Controllers/Api/V1/ClubSepaBatchController.php) | 21 / S |
| [ ] | R0528 | `POST /api/v1/clubs/{club}/sepa-batches/{batch}/items/{item}/settle` | `api.v1.clubs.sepa-batches.items.settle` | [ClubSepaBatchController@settleItem](../app/Http/Controllers/Api/V1/ClubSepaBatchController.php) | 21 / S |
| [ ] | R0529 | `POST /api/v1/clubs/{club}/sepa-batches/{batch}/notice` | `api.v1.clubs.sepa-batches.notice` | [ClubSepaBatchController@notice](../app/Http/Controllers/Api/V1/ClubSepaBatchController.php) | 21 / S |
| [ ] | R0530 | `POST /api/v1/clubs/{club}/sepa-batches/{batch}/notices` | `api.v1.clubs.sepa-batches.notices.prepare` | [ClubSepaBatchController@prepareNotices](../app/Http/Controllers/Api/V1/ClubSepaBatchController.php) | 21 / S |
| [ ] | R0531 | `POST /api/v1/clubs/{club}/sepa-batches/{batch}/notices/send` | `api.v1.clubs.sepa-batches.notices.send` | [ClubSepaBatchController@sendNotices](../app/Http/Controllers/Api/V1/ClubSepaBatchController.php) | 21 / S |
| [ ] | R0532 | `POST /api/v1/clubs/{club}/sepa-batches/{batch}/returns/columns` | `api.v1.clubs.sepa-batches.returns.columns` | [ClubSepaBatchController@returnColumns](../app/Http/Controllers/Api/V1/ClubSepaBatchController.php) | 21 / S |
| [ ] | R0533 | `POST /api/v1/clubs/{club}/sepa-batches/{batch}/returns/import` | `api.v1.clubs.sepa-batches.returns.import` | [ClubSepaBatchController@importReturns](../app/Http/Controllers/Api/V1/ClubSepaBatchController.php) | 21 / S |
| [ ] | R0534 | `POST /api/v1/clubs/{club}/sepa-batches/{batch}/returns/preview` | `api.v1.clubs.sepa-batches.returns.preview` | [ClubSepaBatchController@previewReturns](../app/Http/Controllers/Api/V1/ClubSepaBatchController.php) | 21 / S |
| [ ] | R0535 | `POST /api/v1/clubs/{club}/service-hour-exemptions` | `api.v1.clubs.service-hour-exemptions.store` | [ClubServiceHourController@storeExemption](../app/Http/Controllers/Api/V1/ClubServiceHourController.php) | 27 / S |
| [ ] | R0536 | `POST /api/v1/clubs/{club}/service-hour-records` | `api.v1.clubs.service-hour-records.store` | [ClubServiceHourController@storeRecord](../app/Http/Controllers/Api/V1/ClubServiceHourController.php) | 27 / S |
| [ ] | R0537 | `POST /api/v1/clubs/{club}/service-hour-records/{record}/confirm` | `api.v1.clubs.service-hour-records.confirm` | [ClubServiceHourController@confirmRecord](../app/Http/Controllers/Api/V1/ClubServiceHourController.php) | 27 / S |
| [ ] | R0538 | `POST /api/v1/clubs/{club}/service-hour-records/{record}/corrections` | `api.v1.clubs.service-hour-records.corrections.store` | [ClubServiceHourController@correctRecord](../app/Http/Controllers/Api/V1/ClubServiceHourController.php) | 27 / S |
| [ ] | R0539 | `POST /api/v1/clubs/{club}/service-hour-requirements` | `api.v1.clubs.service-hour-requirements.store` | [ClubServiceHourController@storeRequirement](../app/Http/Controllers/Api/V1/ClubServiceHourController.php) | 27 / S |
| [ ] | R0540 | `POST /api/v1/clubs/{club}/service-hour-requirements/{requirement}/lock` | `api.v1.clubs.service-hour-requirements.lock` | [ClubServiceHourController@lockRequirement](../app/Http/Controllers/Api/V1/ClubServiceHourController.php) | 27 / S |
| [ ] | R0541 | `GET\|HEAD /api/v1/clubs/{club}/service-hours` | `api.v1.clubs.service-hours.index` | [ClubServiceHourController@index](../app/Http/Controllers/Api/V1/ClubServiceHourController.php) | 27 / L |
| [ ] | R0542 | `GET\|HEAD /api/v1/clubs/{club}/staff-scheduling` | `api.v1.clubs.staff-scheduling.index` | [ClubStaffSchedulingController@index](../app/Http/Controllers/Api/V1/ClubStaffSchedulingController.php) | 27 / L |
| [ ] | R0543 | `POST /api/v1/clubs/{club}/staff-scheduling/assignments` | `api.v1.clubs.staff-scheduling.assignments.store` | [ClubStaffSchedulingController@storeAssignment](../app/Http/Controllers/Api/V1/ClubStaffSchedulingController.php) | 27 / S |
| [ ] | R0544 | `POST /api/v1/clubs/{club}/staff-scheduling/assignments/{assignment}/release` | `api.v1.clubs.staff-scheduling.assignments.release` | [ClubStaffSchedulingController@releaseAssignment](../app/Http/Controllers/Api/V1/ClubStaffSchedulingController.php) | 27 / S |
| [ ] | R0545 | `POST /api/v1/clubs/{club}/staff-scheduling/assignments/{assignment}/signup` | `api.v1.clubs.staff-scheduling.assignments.signup` | [ClubStaffSchedulingController@signupAssignment](../app/Http/Controllers/Api/V1/ClubStaffSchedulingController.php) | 27 / S |
| [ ] | R0546 | `POST /api/v1/clubs/{club}/staff-scheduling/assignments/{assignment}/substitute` | `api.v1.clubs.staff-scheduling.assignments.substitute` | [ClubStaffSchedulingController@substituteAssignment](../app/Http/Controllers/Api/V1/ClubStaffSchedulingController.php) | 27 / S |
| [ ] | R0547 | `POST /api/v1/clubs/{club}/staff-scheduling/assignments/{assignment}/swap` | `api.v1.clubs.staff-scheduling.assignments.swap` | [ClubStaffSchedulingController@swapAssignment](../app/Http/Controllers/Api/V1/ClubStaffSchedulingController.php) | 27 / S |
| [ ] | R0548 | `POST /api/v1/clubs/{club}/staff-scheduling/assignments/{assignment}/waitlist` | `api.v1.clubs.staff-scheduling.assignments.waitlist` | [ClubStaffSchedulingController@waitlistAssignment](../app/Http/Controllers/Api/V1/ClubStaffSchedulingController.php) | 27 / S |
| [ ] | R0549 | `POST /api/v1/clubs/{club}/staff-scheduling/availabilities` | `api.v1.clubs.staff-scheduling.availabilities.store` | [ClubStaffSchedulingController@storeAvailability](../app/Http/Controllers/Api/V1/ClubStaffSchedulingController.php) | 27 / S |
| [ ] | R0550 | `POST /api/v1/clubs/{club}/staff-scheduling/conflicts` | `api.v1.clubs.staff-scheduling.conflicts` | [ClubStaffSchedulingController@conflicts](../app/Http/Controllers/Api/V1/ClubStaffSchedulingController.php) | 27 / S |
| [ ] | R0551 | `POST /api/v1/clubs/{club}/subscriptions/{subscription}/cancel` | `api.v1.clubs.subscriptions.cancel` | [SubscriptionController@cancelClubSubscription](../app/Http/Controllers/Api/V1/SubscriptionController.php) | 31 / S |
| [ ] | R0552 | `POST /api/v1/clubs/{club}/subscriptions/{subscription}/renew` | `api.v1.clubs.subscriptions.renew` | [SubscriptionController@renewClubSubscription](../app/Http/Controllers/Api/V1/SubscriptionController.php) | 31 / S |
| [ ] | R0553 | `GET\|HEAD /api/v1/clubs/{club}/surveys` | `api.v1.clubs.surveys.index` | [ClubSurveyController@index](../app/Http/Controllers/Api/V1/ClubSurveyController.php) | 26 / L |
| [ ] | R0554 | `POST /api/v1/clubs/{club}/surveys` | `api.v1.clubs.surveys.store` | [ClubSurveyController@store](../app/Http/Controllers/Api/V1/ClubSurveyController.php) | 26 / S |
| [ ] | R0555 | `PUT /api/v1/clubs/{club}/surveys/{survey}` | `api.v1.clubs.surveys.update` | [ClubSurveyController@update](../app/Http/Controllers/Api/V1/ClubSurveyController.php) | 26 / S |
| [ ] | R0556 | `DELETE /api/v1/clubs/{club}/surveys/{survey}` | `api.v1.clubs.surveys.destroy` | [ClubSurveyController@destroy](../app/Http/Controllers/Api/V1/ClubSurveyController.php) | 26 / D |
| [ ] | R0557 | `POST /api/v1/clubs/{club}/surveys/{survey}/close` | `api.v1.clubs.surveys.close` | [ClubSurveyController@close](../app/Http/Controllers/Api/V1/ClubSurveyController.php) | 26 / S |
| [ ] | R0558 | `POST /api/v1/clubs/{club}/surveys/{survey}/vote` | `api.v1.clubs.surveys.vote` | [ClubSurveyController@vote](../app/Http/Controllers/Api/V1/ClubSurveyController.php) | 26 / S |
| [ ] | R0559 | `GET\|HEAD /api/v1/clubs/{club}/tasks` | `api.v1.clubs.tasks.index` | [ClubTaskController@index](../app/Http/Controllers/Api/V1/ClubTaskController.php) | 24, 25 / L |
| [ ] | R0560 | `POST /api/v1/clubs/{club}/tasks` | `api.v1.clubs.tasks.store` | [ClubTaskController@store](../app/Http/Controllers/Api/V1/ClubTaskController.php) | 24, 25 / S |
| [ ] | R0561 | `PUT /api/v1/clubs/{club}/tasks/{task}` | `api.v1.clubs.tasks.update` | [ClubTaskController@update](../app/Http/Controllers/Api/V1/ClubTaskController.php) | 24, 25 / S |
| [ ] | R0562 | `DELETE /api/v1/clubs/{club}/tasks/{task}` | `api.v1.clubs.tasks.destroy` | [ClubTaskController@destroy](../app/Http/Controllers/Api/V1/ClubTaskController.php) | 24, 25 / D |
| [ ] | R0563 | `POST /api/v1/clubs/{club}/tasks/{task}/attachments` | `api.v1.clubs.tasks.attachments.store` | [ClubTaskController@attach](../app/Http/Controllers/Api/V1/ClubTaskController.php) | 24, 25 / S |
| [ ] | R0564 | `DELETE /api/v1/clubs/{club}/tasks/{task}/attachments/{file}` | `api.v1.clubs.tasks.attachments.destroy` | [ClubTaskController@detach](../app/Http/Controllers/Api/V1/ClubTaskController.php) | 24, 25 / D |
| [ ] | R0565 | `POST /api/v1/clubs/{club}/tasks/{task}/comments` | `api.v1.clubs.tasks.comments.store` | [ClubTaskController@comment](../app/Http/Controllers/Api/V1/ClubTaskController.php) | 24, 25 / S |
| [ ] | R0566 | `POST /api/v1/clubs/{club}/termination-requests` | `api.v1.clubs.termination-requests.store` | [ClubController@requestMembershipTermination](../app/Http/Controllers/Api/V1/ClubController.php) | 18, 08, 22 / S |
| [ ] | R0567 | `GET\|HEAD /api/v1/clubs/{club}/volunteer-profile` | `api.v1.clubs.volunteer-profile.show` | [ClubVolunteerProfileController@showMine](../app/Http/Controllers/Api/V1/ClubVolunteerProfileController.php) | 27 / L |
| [ ] | R0568 | `PUT /api/v1/clubs/{club}/volunteer-profile` | `api.v1.clubs.volunteer-profile.update` | [ClubVolunteerProfileController@updateMine](../app/Http/Controllers/Api/V1/ClubVolunteerProfileController.php) | 27 / S |
| [ ] | R0569 | `GET\|HEAD /api/v1/clubs/{club}/work-automation-jobs` | `api.v1.clubs.work-automation-jobs.index` | [WorkAutomationJobController@index](../app/Http/Controllers/Api/V1/WorkAutomationJobController.php) | 27 / L |
| [ ] | R0570 | `POST /api/v1/clubs/{club}/work-automation-jobs` | `api.v1.clubs.work-automation-jobs.store` | [WorkAutomationJobController@store](../app/Http/Controllers/Api/V1/WorkAutomationJobController.php) | 27 / S |
| [ ] | R0571 | `POST /api/v1/clubs/{club}/work-automation-jobs/{workAutomationJob}/retry` | `api.v1.clubs.work-automation-jobs.retry` | [WorkAutomationJobController@retry](../app/Http/Controllers/Api/V1/WorkAutomationJobController.php) | 27 / S |
| [ ] | R0572 | `GET\|HEAD /api/v1/clubs/{club}/year-periods` | `api.v1.clubs.year-periods.index` | [ClubYearPeriodController@index](../app/Http/Controllers/Api/V1/ClubYearPeriodController.php) | 22 / L |
| [ ] | R0573 | `POST /api/v1/clubs/{club}/year-periods` | `api.v1.clubs.year-periods.store` | [ClubYearPeriodController@store](../app/Http/Controllers/Api/V1/ClubYearPeriodController.php) | 22 / S |
| [ ] | R0574 | `GET\|HEAD /api/v1/clubs/{club}/year-periods/report` | `api.v1.clubs.year-periods.report` | [ClubYearPeriodController@report](../app/Http/Controllers/Api/V1/ClubYearPeriodController.php) | 22 / L |
| [ ] | R0575 | `PUT /api/v1/clubs/{club}/year-periods/{yearPeriod}` | `api.v1.clubs.year-periods.update` | [ClubYearPeriodController@update](../app/Http/Controllers/Api/V1/ClubYearPeriodController.php) | 22 / S |
| [ ] | R0576 | `DELETE /api/v1/clubs/{club}/year-periods/{yearPeriod}` | `api.v1.clubs.year-periods.destroy` | [ClubYearPeriodController@destroy](../app/Http/Controllers/Api/V1/ClubYearPeriodController.php) | 22 / D |
| [ ] | R0577 | `PUT /api/v1/comments/{comment}` | `api.v1.comments.update` | [CommentController@update](../app/Http/Controllers/Api/V1/CommentController.php) | 04, 02 / S |
| [ ] | R0578 | `DELETE /api/v1/comments/{comment}` | `api.v1.comments.destroy` | [CommentController@destroy](../app/Http/Controllers/Api/V1/CommentController.php) | 04, 02 / D |
| [ ] | R0579 | `GET\|HEAD /api/v1/commerce/cart` | `api.v1.commerce.cart.show` | [CommerceCheckoutController@cart](../app/Http/Controllers/CommerceCheckoutController.php) | 30, 29 / L |
| [ ] | R0580 | `POST /api/v1/commerce/cart/checkout` | `api.v1.commerce.cart.checkout` | [CommerceCheckoutController@checkoutCart](../app/Http/Controllers/CommerceCheckoutController.php) | 30, 29 / S |
| [ ] | R0581 | `PATCH /api/v1/commerce/cart/items/{item}` | `api.v1.commerce.cart.items.update` | [CommerceCheckoutController@updateCartItem](../app/Http/Controllers/CommerceCheckoutController.php) | 30, 29 / S |
| [ ] | R0582 | `DELETE /api/v1/commerce/cart/items/{item}` | `api.v1.commerce.cart.items.destroy` | [CommerceCheckoutController@removeCartItem](../app/Http/Controllers/CommerceCheckoutController.php) | 30, 29 / D |
| [ ] | R0583 | `POST /api/v1/commerce/cart/items/{product}` | `api.v1.commerce.cart.items.store` | [CommerceCheckoutController@addCartItem](../app/Http/Controllers/CommerceCheckoutController.php) | 30, 29 / S |
| [ ] | R0584 | `GET\|HEAD /api/v1/commerce/orders` | `api.v1.commerce.orders.index` | [CommerceController@orders](../app/Http/Controllers/Api/V1/CommerceController.php) | 30, 29 / L |
| [ ] | R0585 | `GET\|HEAD /api/v1/commerce/orders/{order}` | `api.v1.commerce.orders.show` | [CommerceController@showOrder](../app/Http/Controllers/Api/V1/CommerceController.php) | 30, 29 / L |
| [ ] | R0586 | `POST /api/v1/commerce/orders/{order}/cancel` | `api.v1.commerce.orders.cancel` | [CommerceCheckoutController@cancelOrder](../app/Http/Controllers/CommerceCheckoutController.php) | 30, 29 / S |
| [ ] | R0587 | `GET\|HEAD /api/v1/commerce/orders/{order}/credit-note` | `api.v1.commerce.orders.credit-note` | [CommerceCheckoutController@downloadCreditNote](../app/Http/Controllers/CommerceCheckoutController.php) | 30, 29 / L |
| [ ] | R0588 | `GET\|HEAD /api/v1/commerce/orders/{order}/invoice` | `api.v1.commerce.orders.invoice` | [CommerceCheckoutController@downloadInvoice](../app/Http/Controllers/CommerceCheckoutController.php) | 30, 29 / L |
| [ ] | R0589 | `POST /api/v1/commerce/orders/{order}/issue` | `api.v1.commerce.orders.issue` | [CommerceCheckoutController@reportOrderIssue](../app/Http/Controllers/CommerceCheckoutController.php) | 30, 29 / S |
| [ ] | R0590 | `POST /api/v1/commerce/orders/{order}/returns` | `api.v1.commerce.orders.returns.store` | [CommerceCheckoutController@requestReturn](../app/Http/Controllers/CommerceCheckoutController.php) | 30, 29 / S |
| [ ] | R0591 | `GET\|HEAD /api/v1/commerce/products` | `api.v1.commerce.products.index` | [CommerceController@products](../app/Http/Controllers/Api/V1/CommerceController.php) | 30, 29 / L |
| [ ] | R0592 | `GET\|HEAD /api/v1/commerce/products/{product}` | `api.v1.commerce.products.show` | [CommerceController@showProduct](../app/Http/Controllers/Api/V1/CommerceController.php) | 30, 29 / L |
| [ ] | R0593 | `GET\|HEAD /api/v1/commerce/products/{product}/reviews` | `api.v1.commerce.products.reviews.index` | [CommerceController@reviews](../app/Http/Controllers/Api/V1/CommerceController.php) | 30, 29 / L |
| [ ] | R0594 | `POST /api/v1/commerce/products/{product}/reviews` | `api.v1.commerce.products.reviews.store` | [CommerceController@storeReview](../app/Http/Controllers/Api/V1/CommerceController.php) | 30, 29 / S |
| [ ] | R0595 | `POST /api/v1/commerce/products/{product}/wishlist` | `api.v1.commerce.products.wishlist.store` | [CommerceController@storeWishlist](../app/Http/Controllers/Api/V1/CommerceController.php) | 30, 29 / S |
| [ ] | R0596 | `DELETE /api/v1/commerce/products/{product}/wishlist` | `api.v1.commerce.products.wishlist.destroy` | [CommerceController@destroyWishlist](../app/Http/Controllers/Api/V1/CommerceController.php) | 30, 29 / D |
| [ ] | R0597 | `GET\|HEAD /api/v1/commerce/seller` | `api.v1.commerce.seller.show` | [CommerceCheckoutController@sellerDashboard](../app/Http/Controllers/CommerceCheckoutController.php) | 30, 29 / L |
| [ ] | R0598 | `POST /api/v1/commerce/seller/application` | `api.v1.commerce.seller.application.store` | [CommerceCheckoutController@storeSellerApplication](../app/Http/Controllers/CommerceCheckoutController.php) | 30, 29 / S |
| [ ] | R0599 | `POST /api/v1/commerce/seller/campaigns` | `api.v1.commerce.seller.campaigns.store` | [CommerceCheckoutController@storeOwnCampaign](../app/Http/Controllers/CommerceCheckoutController.php) | 30, 29 / S |
| [ ] | R0600 | `PUT /api/v1/commerce/seller/campaigns/{campaign}` | `api.v1.commerce.seller.campaigns.update` | [CommerceCheckoutController@updateOwnCampaign](../app/Http/Controllers/CommerceCheckoutController.php) | 30, 29 / S |
| [ ] | R0601 | `DELETE /api/v1/commerce/seller/campaigns/{campaign}` | `api.v1.commerce.seller.campaigns.destroy` | [CommerceCheckoutController@destroyOwnCampaign](../app/Http/Controllers/CommerceCheckoutController.php) | 30, 29 / D |
| [ ] | R0602 | `PATCH /api/v1/commerce/seller/campaigns/{campaign}/status` | `api.v1.commerce.seller.campaigns.status` | [CommerceCheckoutController@updateOwnCampaignStatus](../app/Http/Controllers/CommerceCheckoutController.php) | 30, 29 / S |
| [ ] | R0603 | `PUT /api/v1/commerce/seller/payout-profile` | `api.v1.commerce.seller.payout-profile.update` | [CommerceCheckoutController@storePayoutProfile](../app/Http/Controllers/CommerceCheckoutController.php) | 30, 29 / S |
| [ ] | R0604 | `POST /api/v1/commerce/seller/payouts` | `api.v1.commerce.seller.payouts.store` | [CommerceCheckoutController@requestPayout](../app/Http/Controllers/CommerceCheckoutController.php) | 30, 29 / S |
| [ ] | R0605 | `POST /api/v1/commerce/seller/products` | `api.v1.commerce.seller.products.store` | [CommerceCheckoutController@storeOwnProduct](../app/Http/Controllers/CommerceCheckoutController.php) | 30, 29 / S |
| [ ] | R0606 | `PUT /api/v1/commerce/seller/products/{product}` | `api.v1.commerce.seller.products.update` | [CommerceCheckoutController@updateOwnProduct](../app/Http/Controllers/CommerceCheckoutController.php) | 30, 29 / S |
| [ ] | R0607 | `DELETE /api/v1/commerce/seller/products/{product}` | `api.v1.commerce.seller.products.destroy` | [CommerceCheckoutController@destroyOwnProduct](../app/Http/Controllers/CommerceCheckoutController.php) | 30, 29 / D |
| [ ] | R0608 | `PATCH /api/v1/commerce/seller/products/{product}/status` | `api.v1.commerce.seller.products.status` | [CommerceCheckoutController@updateOwnProductStatus](../app/Http/Controllers/CommerceCheckoutController.php) | 30, 29 / S |
| [ ] | R0609 | `POST /api/v1/commerce/seller/provider-locations` | `api.v1.commerce.seller.provider-locations.store` | [CommerceCheckoutController@storeProviderLocation](../app/Http/Controllers/CommerceCheckoutController.php) | 30, 29 / S |
| [ ] | R0610 | `PUT /api/v1/commerce/seller/provider-locations/{location}` | `api.v1.commerce.seller.provider-locations.update` | [CommerceCheckoutController@updateProviderLocation](../app/Http/Controllers/CommerceCheckoutController.php) | 30, 29 / S |
| [ ] | R0611 | `DELETE /api/v1/commerce/seller/provider-locations/{location}` | `api.v1.commerce.seller.provider-locations.destroy` | [CommerceCheckoutController@destroyProviderLocation](../app/Http/Controllers/CommerceCheckoutController.php) | 30, 29 / D |
| [ ] | R0612 | `PUT /api/v1/commerce/seller/provider-profile` | `api.v1.commerce.seller.provider-profile.update` | [CommerceCheckoutController@storeProviderProfile](../app/Http/Controllers/CommerceCheckoutController.php) | 30, 29 / S |
| [ ] | R0613 | `POST /api/v1/commerce/seller/website-requests` | `api.v1.commerce.seller.website-requests.store` | [CommerceCheckoutController@storeWebsiteRequest](../app/Http/Controllers/CommerceCheckoutController.php) | 30, 29 / S |
| [ ] | R0614 | `GET\|HEAD /api/v1/commerce/team-bulk-orders` | `api.v1.commerce.team-bulk-orders.index` | [TeamBulkOrderController@index](../app/Http/Controllers/Api/V1/TeamBulkOrderController.php) | 30, 29 / L |
| [ ] | R0615 | `POST /api/v1/commerce/team-bulk-orders` | `api.v1.commerce.team-bulk-orders.store` | [TeamBulkOrderController@store](../app/Http/Controllers/Api/V1/TeamBulkOrderController.php) | 30, 29 / S |
| [ ] | R0616 | `GET\|HEAD /api/v1/commerce/team-bulk-orders/{bulkOrder}` | `api.v1.commerce.team-bulk-orders.show` | [TeamBulkOrderController@show](../app/Http/Controllers/Api/V1/TeamBulkOrderController.php) | 30, 29 / L |
| [ ] | R0617 | `POST /api/v1/commerce/team-bulk-orders/{bulkOrder}/items` | `api.v1.commerce.team-bulk-orders.items.store` | [TeamBulkOrderController@order](../app/Http/Controllers/Api/V1/TeamBulkOrderController.php) | 30, 29 / S |
| [ ] | R0618 | `GET\|HEAD /api/v1/commerce/team-bulk-orders/{bulkOrder}/supplier-export` | `api.v1.commerce.team-bulk-orders.supplier-export` | [TeamBulkOrderController@supplierExport](../app/Http/Controllers/Api/V1/TeamBulkOrderController.php) | 30, 29 / L |
| [ ] | R0619 | `GET\|HEAD /api/v1/commerce/wishlist` | `api.v1.commerce.wishlist.index` | [CommerceController@wishlist](../app/Http/Controllers/Api/V1/CommerceController.php) | 30, 29 / L |
| [ ] | R0620 | `GET\|HEAD /api/v1/dashboard/daily-flow` | `api.v1.dashboard.daily-flow` | [DashboardController@dailyFlow](../app/Http/Controllers/Api/V1/DashboardController.php) | 03, 36 / L |
| [ ] | R0621 | `POST /api/v1/editorial/categories` | `api.v1.editorial.categories.store` | [EditorialController@storeCategory](../app/Http/Controllers/Api/V1/EditorialController.php) | 33 / S |
| [ ] | R0622 | `PUT /api/v1/editorial/categories/{blogCategory}` | `api.v1.editorial.categories.update` | [EditorialController@updateCategory](../app/Http/Controllers/Api/V1/EditorialController.php) | 33 / S |
| [ ] | R0623 | `DELETE /api/v1/editorial/categories/{blogCategory}` | `api.v1.editorial.categories.destroy` | [EditorialController@destroyCategory](../app/Http/Controllers/Api/V1/EditorialController.php) | 33 / D |
| [ ] | R0624 | `GET\|HEAD /api/v1/editorial/posts` | `api.v1.editorial.posts.index` | [EditorialController@index](../app/Http/Controllers/Api/V1/EditorialController.php) | 33 / L |
| [ ] | R0625 | `POST /api/v1/editorial/posts` | `api.v1.editorial.posts.store` | [EditorialController@store](../app/Http/Controllers/Api/V1/EditorialController.php) | 33 / S |
| [ ] | R0626 | `PUT /api/v1/editorial/posts/{blogPost}` | `api.v1.editorial.posts.update` | [EditorialController@update](../app/Http/Controllers/Api/V1/EditorialController.php) | 33 / S |
| [ ] | R0627 | `DELETE /api/v1/editorial/posts/{blogPost}` | `api.v1.editorial.posts.destroy` | [EditorialController@destroy](../app/Http/Controllers/Api/V1/EditorialController.php) | 33 / D |
| [ ] | R0628 | `GET\|HEAD /api/v1/events` | `api.v1.events.index` | [EventController@index](../app/Http/Controllers/Api/V1/EventController.php) | 25 / L |
| [ ] | R0629 | `POST /api/v1/events` | `api.v1.events.store` | [EventController@store](../app/Http/Controllers/Api/V1/EventController.php) | 25 / S |
| [ ] | R0630 | `GET\|HEAD /api/v1/events/{event}` | `api.v1.events.show` | [EventController@show](../app/Http/Controllers/Api/V1/EventController.php) | 25 / L |
| [ ] | R0631 | `PUT /api/v1/events/{event}` | `api.v1.events.update` | [EventController@update](../app/Http/Controllers/Api/V1/EventController.php) | 25 / S |
| [ ] | R0632 | `DELETE /api/v1/events/{event}` | `api.v1.events.destroy` | [EventController@destroy](../app/Http/Controllers/Api/V1/EventController.php) | 25 / D |
| [ ] | R0633 | `GET\|HEAD /api/v1/events/{event}/attendance` | `api.v1.events.attendance.index` | [EventController@attendance](../app/Http/Controllers/Api/V1/EventController.php) | 25 / L |
| [ ] | R0634 | `PUT /api/v1/events/{event}/attendance` | `api.v1.events.attendance.update` | [EventController@recordAttendance](../app/Http/Controllers/Api/V1/EventController.php) | 25 / S |
| [ ] | R0635 | `GET\|HEAD /api/v1/events/{event}/attendance/corrections` | `api.v1.events.attendance.corrections.index` | [EventController@attendanceCorrections](../app/Http/Controllers/Api/V1/EventController.php) | 25 / L |
| [ ] | R0636 | `POST /api/v1/events/{event}/cancel` | `api.v1.events.cancel` | [EventController@cancel](../app/Http/Controllers/Api/V1/EventController.php) | 25 / S |
| [ ] | R0637 | `POST /api/v1/events/{event}/check-in` | `api.v1.events.check-in.store` | [EventController@checkIn](../app/Http/Controllers/Api/V1/EventController.php) | 25 / S |
| [ ] | R0638 | `POST /api/v1/events/{event}/check-in-tokens` | `api.v1.events.check-in-tokens.store` | [EventController@issueCheckInToken](../app/Http/Controllers/Api/V1/EventController.php) | 25 / S |
| [ ] | R0639 | `GET\|HEAD /api/v1/events/{event}/comments` | `api.v1.events.comments.index` | [EventController@comments](../app/Http/Controllers/Api/V1/EventController.php) | 25 / L |
| [ ] | R0640 | `POST /api/v1/events/{event}/comments` | `api.v1.events.comments.store` | [EventController@comment](../app/Http/Controllers/Api/V1/EventController.php) | 25 / S |
| [ ] | R0641 | `GET\|HEAD /api/v1/events/{event}/decisions` | `api.v1.events.decisions.index` | [EventCompetitivenessController@decisions](../app/Http/Controllers/Api/V1/EventCompetitivenessController.php) | 25 / L |
| [ ] | R0642 | `POST /api/v1/events/{event}/decisions` | `api.v1.events.decisions.store` | [EventCompetitivenessController@createDecision](../app/Http/Controllers/Api/V1/EventCompetitivenessController.php) | 25 / S |
| [ ] | R0643 | `POST /api/v1/events/{event}/decisions/{decision}/close` | `api.v1.events.decisions.close` | [EventCompetitivenessController@closeDecision](../app/Http/Controllers/Api/V1/EventCompetitivenessController.php) | 25 / S |
| [ ] | R0644 | `POST /api/v1/events/{event}/decisions/{decision}/vote` | `api.v1.events.decisions.vote` | [EventCompetitivenessController@castVote](../app/Http/Controllers/Api/V1/EventCompetitivenessController.php) | 25 / S |
| [ ] | R0645 | `POST /api/v1/events/{event}/participation` | `api.v1.events.participation.respond` | [EventController@respond](../app/Http/Controllers/Api/V1/EventController.php) | 25 / S |
| [ ] | R0646 | `DELETE /api/v1/events/{event}/participation` | `api.v1.events.participation.leave` | [EventController@leave](../app/Http/Controllers/Api/V1/EventController.php) | 25 / D |
| [ ] | R0647 | `POST /api/v1/events/{event}/participation/cancel` | `api.v1.events.participation.cancel` | [EventController@cancelParticipation](../app/Http/Controllers/Api/V1/EventController.php) | 25 / S |
| [ ] | R0648 | `POST /api/v1/events/{event}/participation/substitute` | `api.v1.events.participation.substitute` | [EventController@substituteParticipation](../app/Http/Controllers/Api/V1/EventController.php) | 25 / S |
| [ ] | R0649 | `GET\|HEAD /api/v1/external/clubs/{club}/members` | `api.v1.external.clubs.members.index` | [ExternalClubMemberController@index](../app/Http/Controllers/Api/V1/ExternalClubMemberController.php) | 36, 18 / L |
| [ ] | R0650 | `POST /api/v1/external/clubs/{club}/members` | `api.v1.external.clubs.members.store` | [ExternalClubMemberController@store](../app/Http/Controllers/Api/V1/ExternalClubMemberController.php) | 36, 18 / S |
| [ ] | R0651 | `GET\|HEAD /api/v1/external/clubs/{club}/members/{externalMember}` | `api.v1.external.clubs.members.show` | [ExternalClubMemberController@show](../app/Http/Controllers/Api/V1/ExternalClubMemberController.php) | 36, 18 / L |
| [ ] | R0652 | `PUT /api/v1/external/clubs/{club}/members/{externalMember}` | `api.v1.external.clubs.members.update` | [ExternalClubMemberController@update](../app/Http/Controllers/Api/V1/ExternalClubMemberController.php) | 36, 18 / S |
| [ ] | R0653 | `GET\|HEAD /api/v1/feed` | `api.v1.feed.index` | [FeedController@index](../app/Http/Controllers/Api/V1/FeedController.php) | 04, 02 / L |
| [ ] | R0654 | `POST /api/v1/feed` | `api.v1.feed.store` | [FeedController@store](../app/Http/Controllers/Api/V1/FeedController.php) | 04, 02 / S |
| [ ] | R0655 | `GET\|HEAD /api/v1/files` | `api.v1.files.workspace` | [UploadController@workspace](../app/Http/Controllers/Api/V1/UploadController.php) | 07 / L |
| [ ] | R0656 | `POST /api/v1/files/folders` | `api.v1.files.folders.store` | [UploadController@storeFolder](../app/Http/Controllers/Api/V1/UploadController.php) | 07 / S |
| [ ] | R0657 | `PATCH /api/v1/files/folders/{folder}` | `api.v1.files.folders.update` | [UploadController@updateFolder](../app/Http/Controllers/Api/V1/UploadController.php) | 07 / S |
| [ ] | R0658 | `DELETE /api/v1/files/folders/{folder}` | `api.v1.files.folders.destroy` | [UploadController@destroyFolder](../app/Http/Controllers/Api/V1/UploadController.php) | 07 / D |
| [ ] | R0659 | `POST /api/v1/files/folders/{folder}/share` | `api.v1.files.folders.share` | [FolderController@shareApi](../app/Http/Controllers/FolderController.php) | 07 / S |
| [ ] | R0660 | `POST /api/v1/files/upload-intents` | `api.v1.files.upload-intents.store` | [UploadController@uploadIntent](../app/Http/Controllers/Api/V1/UploadController.php) | 07 / S |
| [ ] | R0661 | `GET\|HEAD /api/v1/files/{file}/preview` | `api.v1.files.preview` | [UploadController@preview](../app/Http/Controllers/Api/V1/UploadController.php) | 07 / L |
| [ ] | R0662 | `GET\|HEAD /api/v1/friends` | `api.v1.friends.index` | [FriendController@index](../app/Http/Controllers/FriendController.php) | 04, 02 / L |
| [ ] | R0663 | `POST /api/v1/friends/invitations` | `api.v1.friends.invitations.store` | [FriendController@store](../app/Http/Controllers/FriendController.php) | 04, 02 / S |
| [ ] | R0664 | `GET\|HEAD /api/v1/friends/invitations/token/{token}` | `api.v1.friends.invitations.token.show` | [FriendController@invitationByToken](../app/Http/Controllers/FriendController.php) | 04, 02 / L |
| [ ] | R0665 | `POST /api/v1/friends/invitations/token/{token}/accept` | `api.v1.friends.invitations.token.accept` | [FriendController@acceptByToken](../app/Http/Controllers/FriendController.php) | 04, 02 / S |
| [ ] | R0666 | `POST /api/v1/friends/invitations/token/{token}/decline` | `api.v1.friends.invitations.token.decline` | [FriendController@declineByToken](../app/Http/Controllers/FriendController.php) | 04, 02 / S |
| [ ] | R0667 | `DELETE /api/v1/friends/invitations/{invitation}` | `api.v1.friends.invitations.withdraw` | [FriendController@withdraw](../app/Http/Controllers/FriendController.php) | 04, 02 / D |
| [ ] | R0668 | `POST /api/v1/friends/invitations/{invitation}/accept` | `api.v1.friends.invitations.accept` | [FriendController@accept](../app/Http/Controllers/FriendController.php) | 04, 02 / S |
| [ ] | R0669 | `POST /api/v1/friends/invitations/{invitation}/decline` | `api.v1.friends.invitations.decline` | [FriendController@decline](../app/Http/Controllers/FriendController.php) | 04, 02 / S |
| [ ] | R0670 | `DELETE /api/v1/friends/{user}` | `api.v1.friends.destroy` | [FriendController@destroy](../app/Http/Controllers/FriendController.php) | 04, 02 / D |
| [ ] | R0671 | `GET\|HEAD /api/v1/guardian/children` | `api.v1.guardian.children.index` | [GuardianController@index](../app/Http/Controllers/Api/V1/GuardianController.php) | 32 / L |
| [ ] | R0672 | `GET\|HEAD /api/v1/guardian/children/{child}` | `api.v1.guardian.children.show` | [GuardianController@child](../app/Http/Controllers/Api/V1/GuardianController.php) | 32 / L |
| [ ] | R0673 | `POST /api/v1/guardian/children/{child}/approve` | `api.v1.guardian.children.approve` | [GuardianController@approve](../app/Http/Controllers/Api/V1/GuardianController.php) | 32 / S |
| [ ] | R0674 | `POST /api/v1/guardian/children/{child}/resend` | `api.v1.guardian.children.resend` | [GuardianController@resend](../app/Http/Controllers/Api/V1/GuardianController.php) | 32 / S |
| [ ] | R0675 | `POST /api/v1/guardian/children/{child}/revoke` | `api.v1.guardian.children.revoke` | [GuardianController@revoke](../app/Http/Controllers/Api/V1/GuardianController.php) | 32 / S |
| [ ] | R0676 | `GET\|HEAD /api/v1/guardian/consent` | `api.v1.guardian.consent.show` | [GuardianController@consentStatus](../app/Http/Controllers/Api/V1/GuardianController.php) | 32 / L |
| [ ] | R0677 | `POST /api/v1/guardian/consent/resend` | `api.v1.guardian.consent.resend` | [GuardianController@resendOwnConsent](../app/Http/Controllers/Api/V1/GuardianController.php) | 32 / S |
| [ ] | R0678 | `GET\|HEAD /api/v1/guardian/invitations` | `api.v1.guardian.invitations.index` | [GuardianController@invitations](../app/Http/Controllers/Api/V1/GuardianController.php) | 32 / L |
| [ ] | R0679 | `POST /api/v1/guardian/invitations/{relationship}/accept` | `api.v1.guardian.invitations.accept` | [GuardianController@acceptInvitation](../app/Http/Controllers/Api/V1/GuardianController.php) | 32 / S |
| [ ] | R0680 | `POST /api/v1/guardian/invitations/{relationship}/decline` | `api.v1.guardian.invitations.decline` | [GuardianController@declineInvitation](../app/Http/Controllers/Api/V1/GuardianController.php) | 32 / S |
| [ ] | R0681 | `GET\|HEAD /api/v1/learning` | `api.v1.learning.index` | [LearningController@index](../app/Http/Controllers/Api/V1/LearningController.php) | 14, 16 / L |
| [ ] | R0682 | `GET\|HEAD /api/v1/learning-studio` | `api.v1.learning-studio.index` | [LearningStudioController@index](../app/Http/Controllers/LearningStudioController.php) | 16 / L |
| [ ] | R0683 | `POST /api/v1/learning-studio/courses` | `api.v1.learning-studio.courses.store` | [LearningStudioController@storeCourse](../app/Http/Controllers/LearningStudioController.php) | 16 / S |
| [ ] | R0684 | `PUT /api/v1/learning-studio/courses/{course}` | `api.v1.learning-studio.courses.update` | [LearningStudioController@updateCourse](../app/Http/Controllers/LearningStudioController.php) | 16 / S |
| [ ] | R0685 | `PUT /api/v1/learning-studio/courses/{course}/assignment-submissions/{submission}` | `api.v1.learning-studio.assignment-submissions.update` | [LearningStudioController@gradeAssignment](../app/Http/Controllers/LearningStudioController.php) | 16 / S |
| [ ] | R0686 | `POST /api/v1/learning-studio/courses/{course}/assignments` | `api.v1.learning-studio.assignments.store` | [LearningStudioController@storeAssignment](../app/Http/Controllers/LearningStudioController.php) | 16 / S |
| [ ] | R0687 | `PUT /api/v1/learning-studio/courses/{course}/comments/{comment}` | `api.v1.learning-studio.comments.update` | [LearningStudioController@resolveComment](../app/Http/Controllers/LearningStudioController.php) | 16 / S |
| [ ] | R0688 | `POST /api/v1/learning-studio/courses/{course}/comments/{comment}/replies` | `api.v1.learning-studio.comments.replies.store` | [LearningStudioController@replyComment](../app/Http/Controllers/LearningStudioController.php) | 16 / S |
| [ ] | R0689 | `POST /api/v1/learning-studio/courses/{course}/coupons` | `api.v1.learning-studio.coupons.store` | [LearningStudioController@storeCoupon](../app/Http/Controllers/LearningStudioController.php) | 16 / S |
| [ ] | R0690 | `POST /api/v1/learning-studio/courses/{course}/enrollments` | `api.v1.learning-studio.enrollments.store` | [LearningStudioController@grantEnrollment](../app/Http/Controllers/LearningStudioController.php) | 16 / S |
| [ ] | R0691 | `GET\|HEAD /api/v1/learning-studio/courses/{course}/enrollments/{enrollment}/participation-confirmation` | `api.v1.learning-studio.enrollments.participation-confirmation` | [LearningStudioController@participationConfirmation](../app/Http/Controllers/LearningStudioController.php) | 16 / L |
| [ ] | R0692 | `PUT /api/v1/learning-studio/courses/{course}/enrollments/{enrollment}/revoke` | `api.v1.learning-studio.enrollments.revoke` | [LearningStudioController@revokeEnrollment](../app/Http/Controllers/LearningStudioController.php) | 16 / S |
| [ ] | R0693 | `POST /api/v1/learning-studio/courses/{course}/lessons` | `api.v1.learning-studio.lessons.store` | [LearningStudioController@storeLesson](../app/Http/Controllers/LearningStudioController.php) | 16 / S |
| [ ] | R0694 | `PUT /api/v1/learning-studio/courses/{course}/lessons/reorder` | `api.v1.learning-studio.lessons.reorder` | [LearningStudioController@reorderLessons](../app/Http/Controllers/LearningStudioController.php) | 16 / S |
| [ ] | R0695 | `PUT /api/v1/learning-studio/courses/{course}/lessons/{lesson}` | `api.v1.learning-studio.lessons.update` | [LearningStudioController@updateLesson](../app/Http/Controllers/LearningStudioController.php) | 16 / S |
| [ ] | R0696 | `DELETE /api/v1/learning-studio/courses/{course}/lessons/{lesson}` | `api.v1.learning-studio.lessons.destroy` | [LearningStudioController@destroyLesson](../app/Http/Controllers/LearningStudioController.php) | 16 / D |
| [ ] | R0697 | `GET\|HEAD /api/v1/learning-studio/courses/{course}/offer-evaluation` | `api.v1.learning-studio.courses.offer-evaluation` | [LearningStudioController@offerEvaluation](../app/Http/Controllers/LearningStudioController.php) | 16 / L |
| [ ] | R0698 | `GET\|HEAD /api/v1/learning-studio/courses/{course}/offer-evaluation.csv` | `api.v1.learning-studio.courses.offer-evaluation.export` | [LearningStudioController@exportOfferEvaluation](../app/Http/Controllers/LearningStudioController.php) | 16 / L |
| [ ] | R0699 | `POST /api/v1/learning-studio/courses/{course}/quizzes` | `api.v1.learning-studio.quizzes.store` | [LearningStudioController@storeQuiz](../app/Http/Controllers/LearningStudioController.php) | 16 / S |
| [ ] | R0700 | `DELETE /api/v1/learning-studio/courses/{course}/quizzes/{quiz}` | `api.v1.learning-studio.quizzes.destroy` | [LearningStudioController@destroyQuiz](../app/Http/Controllers/LearningStudioController.php) | 16 / D |
| [ ] | R0701 | `GET\|HEAD /api/v1/learning-studio/courses/{course}/report.csv` | `api.v1.learning-studio.courses.report` | [LearningStudioController@exportReport](../app/Http/Controllers/LearningStudioController.php) | 16 / L |
| [ ] | R0702 | `POST /api/v1/learning-studio/courses/{course}/sections` | `api.v1.learning-studio.sections.store` | [LearningStudioController@storeSection](../app/Http/Controllers/LearningStudioController.php) | 16 / S |
| [ ] | R0703 | `POST /api/v1/learning-studio/courses/{course}/uploads` | `api.v1.learning-studio.uploads.store` | [LearningStudioController@uploadAsset](../app/Http/Controllers/LearningStudioController.php) | 16 / S |
| [ ] | R0704 | `GET\|HEAD /api/v1/learning/certificates/{certificate}` | `api.v1.learning.certificates.show` | [LearningController@certificate](../app/Http/Controllers/Api/V1/LearningController.php) | 14, 16 / L |
| [ ] | R0705 | `GET\|HEAD /api/v1/learning/certificates/{certificate}/download` | `api.v1.learning.certificates.download` | [PublicLearningController@downloadCertificate](../app/Http/Controllers/PublicLearningController.php) | 14, 16 / L |
| [ ] | R0706 | `GET\|HEAD /api/v1/learning/courses/{course}` | `api.v1.learning.courses.show` | [LearningController@show](../app/Http/Controllers/Api/V1/LearningController.php) | 14, 16 / L |
| [ ] | R0707 | `POST /api/v1/learning/courses/{course}/assignments/{assignment}/submissions` | `api.v1.learning.assignments.submissions.store` | [LearningController@submitAssignment](../app/Http/Controllers/Api/V1/LearningController.php) | 14, 16 / S |
| [ ] | R0708 | `POST /api/v1/learning/courses/{course}/enroll` | `api.v1.learning.courses.enroll` | [LearningController@enroll](../app/Http/Controllers/Api/V1/LearningController.php) | 14, 16 / S |
| [ ] | R0709 | `POST /api/v1/learning/courses/{course}/lessons/{lesson}/comments` | `api.v1.learning.lessons.comments.store` | [LearningController@storeComment](../app/Http/Controllers/Api/V1/LearningController.php) | 14, 16 / S |
| [ ] | R0710 | `PUT /api/v1/learning/courses/{course}/lessons/{lesson}/complete` | `api.v1.learning.lessons.complete` | [LearningController@completeLesson](../app/Http/Controllers/Api/V1/LearningController.php) | 14, 16 / S |
| [ ] | R0711 | `POST /api/v1/learning/courses/{course}/lessons/{lesson}/notes` | `api.v1.learning.lessons.notes.store` | [LearningController@storeNote](../app/Http/Controllers/Api/V1/LearningController.php) | 14, 16 / S |
| [ ] | R0712 | `PUT /api/v1/learning/courses/{course}/lessons/{lesson}/progress` | `api.v1.learning.lessons.progress` | [LearningController@trackProgress](../app/Http/Controllers/Api/V1/LearningController.php) | 14, 16 / S |
| [ ] | R0713 | `POST /api/v1/learning/courses/{course}/quizzes/{quiz}/attempts` | `api.v1.learning.quizzes.attempts.store` | [LearningController@submitQuiz](../app/Http/Controllers/Api/V1/LearningController.php) | 14, 16 / S |
| [ ] | R0714 | `POST /api/v1/learning/courses/{course}/reviews` | `api.v1.learning.reviews.store` | [LearningController@storeReview](../app/Http/Controllers/Api/V1/LearningController.php) | 14, 16 / S |
| [ ] | R0715 | `GET\|HEAD /api/v1/maturity/challenges` | `api.v1.maturity.challenges` | [MaturityController@challenges](../app/Http/Controllers/Api/V1/MaturityController.php) | 13, 36 / L |
| [ ] | R0716 | `GET\|HEAD /api/v1/maturity/coach-weekly` | `api.v1.maturity.coach-weekly` | [MaturityController@coachWeekly](../app/Http/Controllers/Api/V1/MaturityController.php) | 13, 36 / L |
| [ ] | R0717 | `GET\|HEAD /api/v1/maturity/feed-discovery` | `api.v1.maturity.feed-discovery` | [MaturityController@feedDiscovery](../app/Http/Controllers/Api/V1/MaturityController.php) | 13, 36 / L |
| [ ] | R0718 | `GET\|HEAD /api/v1/maturity/feed-trending` | `api.v1.maturity.feed-trending` | [MaturityController@feedTrending](../app/Http/Controllers/Api/V1/MaturityController.php) | 13, 36 / L |
| [ ] | R0719 | `GET\|HEAD /api/v1/maturity/motivation` | `api.v1.maturity.motivation` | [MaturityController@motivation](../app/Http/Controllers/Api/V1/MaturityController.php) | 13, 36 / L |
| [ ] | R0720 | `GET\|HEAD /api/v1/maturity/onboarding` | `api.v1.maturity.onboarding` | [MaturityController@onboarding](../app/Http/Controllers/Api/V1/MaturityController.php) | 13, 36 / L |
| [ ] | R0721 | `GET\|HEAD /api/v1/maturity/overview` | `api.v1.maturity.overview` | [MaturityController@overview](../app/Http/Controllers/Api/V1/MaturityController.php) | 13, 36 / L |
| [ ] | R0722 | `GET\|HEAD /api/v1/maturity/routes/{sportRoute}/analytics` | `api.v1.maturity.route-analytics` | [MaturityController@routeAnalytics](../app/Http/Controllers/Api/V1/MaturityController.php) | 13, 36 / L |
| [ ] | R0723 | `GET\|HEAD /api/v1/maturity/safety` | `api.v1.maturity.safety` | [MaturityController@safety](../app/Http/Controllers/Api/V1/MaturityController.php) | 13, 36 / L |
| [ ] | R0724 | `GET\|HEAD /api/v1/maturity/search` | `api.v1.maturity.search` | [MaturityController@search](../app/Http/Controllers/Api/V1/MaturityController.php) | 13, 36 / L |
| [ ] | R0725 | `GET\|HEAD /api/v1/maturity/viral` | `api.v1.maturity.viral` | [MaturityController@viral](../app/Http/Controllers/Api/V1/MaturityController.php) | 13, 36 / L |
| [ ] | R0726 | `GET\|HEAD /api/v1/me` | `api.v1.me.show` | [MeController@show](../app/Http/Controllers/Api/V1/MeController.php) | 02, 01 / L |
| [ ] | R0727 | `POST /api/v1/me/email/verification-notification` | `api.v1.me.email.verification.send` | [MobileEmailVerificationController@send](../app/Http/Controllers/Api/V1/MobileEmailVerificationController.php) | 02, 01 / S |
| [ ] | R0728 | `PATCH /api/v1/me/language` | `api.v1.me.language` | [MeController@updateLanguage](../app/Http/Controllers/Api/V1/MeController.php) | 02, 01 / S |
| [ ] | R0729 | `PUT /api/v1/me/password` | `api.v1.me.password.update` | [AccountSecurityController@updatePassword](../app/Http/Controllers/Api/V1/AccountSecurityController.php) | 02, 01 / S |
| [ ] | R0730 | `PUT /api/v1/me/profile` | `api.v1.me.profile.update` | [MeController@updateProfile](../app/Http/Controllers/Api/V1/MeController.php) | 02, 01 / S |
| [ ] | R0731 | `POST /api/v1/me/profile-photo` | `api.v1.me.profile-photo.update` | [AccountSecurityController@updateProfilePhoto](../app/Http/Controllers/Api/V1/AccountSecurityController.php) | 02, 01 / S |
| [ ] | R0732 | `DELETE /api/v1/me/profile-photo` | `api.v1.me.profile-photo.destroy` | [AccountSecurityController@destroyProfilePhoto](../app/Http/Controllers/Api/V1/AccountSecurityController.php) | 02, 01 / D |
| [ ] | R0733 | `GET\|HEAD /api/v1/me/sessions` | `api.v1.me.sessions.index` | [AccountSecurityController@sessions](../app/Http/Controllers/Api/V1/AccountSecurityController.php) | 02, 01 / L |
| [ ] | R0734 | `DELETE /api/v1/me/sessions/others` | `api.v1.me.sessions.destroy-others` | [AccountSecurityController@destroyOtherSessions](../app/Http/Controllers/Api/V1/AccountSecurityController.php) | 02, 01 / D |
| [ ] | R0735 | `DELETE /api/v1/me/sessions/{token}` | `api.v1.me.sessions.destroy` | [AccountSecurityController@destroySession](../app/Http/Controllers/Api/V1/AccountSecurityController.php) | 02, 01 / D |
| [ ] | R0736 | `GET\|HEAD /api/v1/me/two-factor-authentication` | `api.v1.me.two-factor.show` | [MobileTwoFactorController@show](../app/Http/Controllers/Api/V1/MobileTwoFactorController.php) | 02, 01 / L |
| [ ] | R0737 | `POST /api/v1/me/two-factor-authentication` | `api.v1.me.two-factor.store` | [MobileTwoFactorController@store](../app/Http/Controllers/Api/V1/MobileTwoFactorController.php) | 02, 01 / S |
| [ ] | R0738 | `DELETE /api/v1/me/two-factor-authentication` | `api.v1.me.two-factor.destroy` | [MobileTwoFactorController@destroy](../app/Http/Controllers/Api/V1/MobileTwoFactorController.php) | 02, 01 / D |
| [ ] | R0739 | `POST /api/v1/me/two-factor-authentication/confirm` | `api.v1.me.two-factor.confirm` | [MobileTwoFactorController@confirm](../app/Http/Controllers/Api/V1/MobileTwoFactorController.php) | 02, 01 / S |
| [ ] | R0740 | `POST /api/v1/me/two-factor-recovery-codes` | `api.v1.me.two-factor.recovery-codes` | [MobileTwoFactorController@regenerateRecoveryCodes](../app/Http/Controllers/Api/V1/MobileTwoFactorController.php) | 02, 01 / S |
| [ ] | R0741 | `POST /api/v1/membership-applications` | `api.v1.membership-applications.store` | `Closure` | 18, 19, 20 / S |
| [ ] | R0742 | `GET\|HEAD /api/v1/membership-applications/{membershipRequest}` | `api.v1.membership-applications.show` | `Closure` | 18, 19, 20 / L |
| [ ] | R0743 | `POST /api/v1/membership-applications/{membershipRequest}/withdraw` | `api.v1.membership-applications.withdraw` | `Closure` | 18, 19, 20 / S |
| [ ] | R0744 | `GET\|HEAD /api/v1/meta` | `api.v1.meta` | [MobileMetaController](../app/Http/Controllers/Api/V1/MobileMetaController.php) | 36, 35 / L |
| [ ] | R0745 | `POST /api/v1/mobile/deep-links/resolve` | `api.v1.mobile.deep-links.resolve` | [MobileDeepLinkController@resolve](../app/Http/Controllers/Api/V1/MobileDeepLinkController.php) | 06 / S |
| [ ] | R0746 | `POST /api/v1/mobile/push-devices` | `api.v1.mobile.push-devices.store` | [MobilePushDeviceController@store](../app/Http/Controllers/Api/V1/MobilePushDeviceController.php) | 06 / S |
| [ ] | R0747 | `DELETE /api/v1/mobile/push-devices/{deviceId}` | `api.v1.mobile.push-devices.destroy` | [MobilePushDeviceController@destroy](../app/Http/Controllers/Api/V1/MobilePushDeviceController.php) | 06 / D |
| [ ] | R0748 | `GET\|POST\|HEAD /api/v1/mobile/sync` | `api.v1.mobile.sync` | [MobileSyncController](../app/Http/Controllers/Api/V1/MobileSyncController.php) | 36, 35 / L+S |
| [ ] | R0749 | `GET\|HEAD /api/v1/newsletter/confirm/{token}` | `api.v1.newsletter.confirm` | [ClubNewsletterController@confirm](../app/Http/Controllers/Api/V1/ClubNewsletterController.php) | 26 / L |
| [ ] | R0750 | `GET\|HEAD /api/v1/newsletter/unsubscribe/{token}` | `api.v1.newsletter.unsubscribe` | [ClubNewsletterController@unsubscribe](../app/Http/Controllers/Api/V1/ClubNewsletterController.php) | 26 / L |
| [ ] | R0751 | `GET\|HEAD /api/v1/notifications` | `api.v1.notifications.index` | [NotificationController@index](../app/Http/Controllers/Api/V1/NotificationController.php) | 06 / L |
| [ ] | R0752 | `POST /api/v1/notifications/read-all` | `api.v1.notifications.read-all` | [NotificationController@markAllAsRead](../app/Http/Controllers/Api/V1/NotificationController.php) | 06 / S |
| [ ] | R0753 | `GET\|HEAD /api/v1/notifications/{notification}` | `api.v1.notifications.show` | [NotificationController@show](../app/Http/Controllers/Api/V1/NotificationController.php) | 06 / L |
| [ ] | R0754 | `DELETE /api/v1/notifications/{notification}` | `api.v1.notifications.destroy` | [NotificationController@destroy](../app/Http/Controllers/Api/V1/NotificationController.php) | 06 / D |
| [ ] | R0755 | `POST /api/v1/notifications/{notification}/read` | `api.v1.notifications.read` | [NotificationController@markAsRead](../app/Http/Controllers/Api/V1/NotificationController.php) | 06 / S |
| [ ] | R0756 | `POST /api/v1/notifications/{notification}/unread` | `api.v1.notifications.unread` | [NotificationController@markAsUnread](../app/Http/Controllers/Api/V1/NotificationController.php) | 06 / S |
| [ ] | R0757 | `GET\|HEAD /api/v1/nutrition` | `api.v1.nutrition.index` | [NutritionController@index](../app/Http/Controllers/Api/V1/NutritionController.php) | 10 / L |
| [ ] | R0758 | `POST /api/v1/nutrition/ai/meal-image` | `api.v1.nutrition.ai.meal-image` | [NutritionController@analyzeMealImage](../app/Http/Controllers/Api/V1/NutritionController.php) | 10 / S |
| [ ] | R0759 | `GET\|HEAD /api/v1/nutrition/foods/barcode` | `api.v1.nutrition.foods.barcode` | [NutritionController@lookupBarcode](../app/Http/Controllers/Api/V1/NutritionController.php) | 10 / L |
| [ ] | R0760 | `GET\|HEAD /api/v1/nutrition/foods/search` | `api.v1.nutrition.foods.search` | [NutritionController@searchFoods](../app/Http/Controllers/Api/V1/NutritionController.php) | 10 / L |
| [ ] | R0761 | `PATCH /api/v1/nutrition/goal` | `api.v1.nutrition.goal.update` | [NutritionController@updateGoal](../app/Http/Controllers/Api/V1/NutritionController.php) | 10 / S |
| [ ] | R0762 | `POST /api/v1/nutrition/meals` | `api.v1.nutrition.meals.store` | [NutritionController@storeMeal](../app/Http/Controllers/Api/V1/NutritionController.php) | 10 / S |
| [ ] | R0763 | `PATCH /api/v1/nutrition/meals/{nutritionMeal}` | `api.v1.nutrition.meals.update` | [NutritionController@updateMeal](../app/Http/Controllers/Api/V1/NutritionController.php) | 10 / S |
| [ ] | R0764 | `DELETE /api/v1/nutrition/meals/{nutritionMeal}` | `api.v1.nutrition.meals.destroy` | [NutritionController@destroyMeal](../app/Http/Controllers/Api/V1/NutritionController.php) | 10 / D |
| [ ] | R0765 | `POST /api/v1/nutrition/water` | `api.v1.nutrition.water.store` | [NutritionController@storeWater](../app/Http/Controllers/Api/V1/NutritionController.php) | 10 / S |
| [ ] | R0766 | `POST /api/v1/outfit-deliveries/{delivery}/issue` | `api.v1.outfit-deliveries.issue.store` | [OutfitSubscriptionController@requestDeliveryIssue](../app/Http/Controllers/OutfitSubscriptionController.php) | 31 / S |
| [ ] | R0767 | `GET\|HEAD /api/v1/outfit-subscriptions` | `api.v1.outfit-subscriptions.index` | [OutfitSubscriptionController@index](../app/Http/Controllers/OutfitSubscriptionController.php) | 31 / L |
| [ ] | R0768 | `POST /api/v1/outfit-subscriptions/plans/{plan}` | `api.v1.outfit-subscriptions.store` | [OutfitSubscriptionController@store](../app/Http/Controllers/OutfitSubscriptionController.php) | 31 / S |
| [ ] | R0769 | `PUT /api/v1/outfit-subscriptions/style-profile` | `api.v1.outfit-subscriptions.profile.update` | [OutfitSubscriptionController@updateProfile](../app/Http/Controllers/OutfitSubscriptionController.php) | 31 / S |
| [ ] | R0770 | `POST /api/v1/outfit-subscriptions/{subscription}/cancel` | `api.v1.outfit-subscriptions.cancel` | [OutfitSubscriptionController@cancel](../app/Http/Controllers/OutfitSubscriptionController.php) | 31 / S |
| [ ] | R0771 | `POST /api/v1/outfit-subscriptions/{subscription}/pause` | `api.v1.outfit-subscriptions.pause` | [OutfitSubscriptionController@pause](../app/Http/Controllers/OutfitSubscriptionController.php) | 31 / S |
| [ ] | R0772 | `POST /api/v1/outfit-subscriptions/{subscription}/resume` | `api.v1.outfit-subscriptions.resume` | [OutfitSubscriptionController@resume](../app/Http/Controllers/OutfitSubscriptionController.php) | 31 / S |
| [ ] | R0773 | `GET\|HEAD /api/v1/portal` | `api.v1.portal.show` | [MemberPortalController@show](../app/Http/Controllers/Api/V1/MemberPortalController.php) | 08, 02 / L |
| [ ] | R0774 | `GET\|HEAD /api/v1/portal/{section}` | `api.v1.portal.details` | [MemberPortalController@details](../app/Http/Controllers/Api/V1/MemberPortalController.php) | 08, 02 / L |
| [ ] | R0775 | `POST /api/v1/post-images` | `api.v1.post-images.store` | [PostImageUploadController](../app/Http/Controllers/Api/V1/PostImageUploadController.php) | 04, 02 / S |
| [ ] | R0776 | `GET\|HEAD /api/v1/posts/{post}` | `api.v1.posts.show` | [FeedController@show](../app/Http/Controllers/Api/V1/FeedController.php) | 04, 02 / L |
| [ ] | R0777 | `PUT /api/v1/posts/{post}` | `api.v1.posts.update` | [FeedController@update](../app/Http/Controllers/Api/V1/FeedController.php) | 04, 02 / S |
| [ ] | R0778 | `POST /api/v1/posts/{post}` | `api.v1.posts.update.multipart` | [FeedController@update](../app/Http/Controllers/Api/V1/FeedController.php) | 04, 02 / S |
| [ ] | R0779 | `DELETE /api/v1/posts/{post}` | `api.v1.posts.destroy` | [FeedController@destroy](../app/Http/Controllers/Api/V1/FeedController.php) | 04, 02 / D |
| [ ] | R0780 | `GET\|HEAD /api/v1/posts/{post}/comments` | `api.v1.posts.comments.index` | [CommentController@index](../app/Http/Controllers/Api/V1/CommentController.php) | 04, 02 / L |
| [ ] | R0781 | `POST /api/v1/posts/{post}/comments` | `api.v1.posts.comments.store` | [CommentController@store](../app/Http/Controllers/Api/V1/CommentController.php) | 04, 02 / S |
| [ ] | R0782 | `POST /api/v1/posts/{post}/delete` | `api.v1.posts.destroy.post` | [FeedController@destroy](../app/Http/Controllers/Api/V1/FeedController.php) | 04, 02 / S |
| [ ] | R0783 | `POST /api/v1/posts/{post}/helpful` | `api.v1.posts.helpful` | [FeedController@toggleHelpful](../app/Http/Controllers/Api/V1/FeedController.php) | 04, 02 / S |
| [ ] | R0784 | `GET\|HEAD /api/v1/posts/{post}/image` | `api.v1.posts.image` | `Closure` | 04, 02 / L |
| [ ] | R0785 | `POST /api/v1/posts/{post}/like` | `api.v1.posts.like` | [FeedController@toggleLike](../app/Http/Controllers/Api/V1/FeedController.php) | 04, 02 / S |
| [ ] | R0786 | `GET\|HEAD /api/v1/privacy` | `api.v1.privacy.show` | [PrivacyController@show](../app/Http/Controllers/Api/V1/PrivacyController.php) | 02, 01 / L |
| [ ] | R0787 | `PATCH /api/v1/privacy/correction` | `api.v1.privacy.correct` | [PrivacyController@correct](../app/Http/Controllers/Api/V1/PrivacyController.php) | 02, 01 / S |
| [ ] | R0788 | `POST /api/v1/privacy/data-erasure` | `api.v1.privacy.data-erasure.destroy` | [DataErasureController@destroy](../app/Http/Controllers/Api/V1/DataErasureController.php) | 02, 01 / S |
| [ ] | R0789 | `POST /api/v1/privacy/data-erasure-code` | `api.v1.privacy.data-erasure.code` | [DataErasureController@sendCode](../app/Http/Controllers/Api/V1/DataErasureController.php) | 02, 01 / S |
| [ ] | R0790 | `GET\|HEAD /api/v1/privacy/export` | `api.v1.privacy.export` | [PrivacyController@export](../app/Http/Controllers/Api/V1/PrivacyController.php) | 02, 01 / L |
| [ ] | R0791 | `GET\|HEAD /api/v1/privacy/processing-activities` | `api.v1.privacy.processing-activities` | [PrivacyController@processingActivities](../app/Http/Controllers/Api/V1/PrivacyController.php) | 02, 01 / L |
| [ ] | R0792 | `GET\|HEAD /api/v1/privacy/rights-process` | `api.v1.privacy.rights-process` | [PrivacyController@rightsProcess](../app/Http/Controllers/Api/V1/PrivacyController.php) | 02, 01 / L |
| [ ] | R0793 | `POST /api/v1/privacy/withdraw-consents` | `api.v1.privacy.withdraw-consents` | [PrivacyController@withdrawConsents](../app/Http/Controllers/Api/V1/PrivacyController.php) | 02, 01 / S |
| [ ] | R0794 | `POST /api/v1/public/agency/requests` | `api.v1.public.agency.requests.store` | [PublicAgencyController@store](../app/Http/Controllers/Api/V1/PublicAgencyController.php) | 40, 30, 12, 14 / S |
| [ ] | R0795 | `GET\|HEAD /api/v1/public/blog` | `api.v1.public.blog.index` | [PublicContentController@blog](../app/Http/Controllers/Api/V1/PublicContentController.php) | 40, 30, 12, 14 / L |
| [ ] | R0796 | `GET\|HEAD /api/v1/public/blog/{blogPost}` | `api.v1.public.blog.show` | [PublicContentController@blogPost](../app/Http/Controllers/Api/V1/PublicContentController.php) | 40, 30, 12, 14 / L |
| [ ] | R0797 | `GET\|HEAD /api/v1/public/clubs` | `api.v1.public.clubs.index` | [PublicClubController@indexJson](../app/Http/Controllers/PublicClubController.php) | 40, 30, 12, 14 / L |
| [ ] | R0798 | `POST /api/v1/public/clubs/{club}/membership-applications` | `api.v1.public.membership-applications.store` | [PublicSelfServiceController@storeMembershipApplication](../app/Http/Controllers/Api/V1/PublicSelfServiceController.php) | 40, 30, 12, 14 / S |
| [ ] | R0799 | `GET\|HEAD /api/v1/public/commerce/catalog` | `api.v1.public.commerce.catalog` | [PublicCommerceCatalogController](../app/Http/Controllers/PublicCommerceCatalogController.php) | 40, 30, 12, 14 / L |
| [ ] | R0800 | `POST /api/v1/public/contact` | `api.v1.public.contact.store` | [KontaktController@store](../app/Http/Controllers/KontaktController.php) | 40, 30, 12, 14 / S |
| [ ] | R0801 | `GET\|HEAD /api/v1/public/learning/bookings/status/{token}` | `api.v1.public.learning.bookings.status` | [PublicSelfServiceController@courseBookingStatus](../app/Http/Controllers/Api/V1/PublicSelfServiceController.php) | 40, 30, 12, 14 / L |
| [ ] | R0802 | `GET\|HEAD /api/v1/public/learning/certificates/{code}` | `api.v1.public.learning.certificates.verify` | [PublicLearningController@verifyCertificateJson](../app/Http/Controllers/PublicLearningController.php) | 40, 30, 12, 14 / L |
| [ ] | R0803 | `GET\|HEAD /api/v1/public/learning/courses` | `api.v1.public.learning.courses.index` | [PublicLearningController@indexJson](../app/Http/Controllers/PublicLearningController.php) | 40, 30, 12, 14 / L |
| [ ] | R0804 | `POST /api/v1/public/learning/courses/{course}/bookings` | `api.v1.public.learning.bookings.store` | [PublicSelfServiceController@storeCourseBooking](../app/Http/Controllers/Api/V1/PublicSelfServiceController.php) | 40, 30, 12, 14 / S |
| [ ] | R0805 | `GET\|HEAD /api/v1/public/marketplace` | `api.v1.public.marketplace.index` | [PublicMarketplaceController@indexJson](../app/Http/Controllers/PublicMarketplaceController.php) | 40, 30, 12, 14 / L |
| [ ] | R0806 | `GET\|HEAD /api/v1/public/membership-applications/status/{token}` | `api.v1.public.membership-applications.status` | [PublicSelfServiceController@membershipApplicationStatus](../app/Http/Controllers/Api/V1/PublicSelfServiceController.php) | 40, 30, 12, 14 / L |
| [ ] | R0807 | `GET\|HEAD /api/v1/public/recruiting/jobs` | `api.v1.public.recruiting.jobs.index` | [PublicRecruitingController@index](../app/Http/Controllers/Api/V1/PublicRecruitingController.php) | 40, 30, 12, 14 / L |
| [ ] | R0808 | `POST /api/v1/public/recruiting/jobs/{organizationJob}/interest` | `api.v1.public.recruiting.jobs.interest` | [PublicRecruitingController@submitInterest](../app/Http/Controllers/Api/V1/PublicRecruitingController.php) | 40, 30, 12, 14 / S |
| [ ] | R0809 | `GET\|HEAD /api/v1/public/sponsors` | `api.v1.public.sponsors.index` | [PublicContentController@sponsors](../app/Http/Controllers/Api/V1/PublicContentController.php) | 40, 30, 12, 14 / L |
| [ ] | R0810 | `GET\|HEAD /api/v1/recruiting-pipeline` | `api.v1.recruiting-pipeline.index` | [RecruitingPipelineController@index](../app/Http/Controllers/Api/V1/RecruitingPipelineController.php) | 12 / L |
| [ ] | R0811 | `PUT /api/v1/recruiting-pipeline/applications/{interest}` | `api.v1.recruiting-pipeline.applications.update` | [RecruitingPipelineController@update](../app/Http/Controllers/Api/V1/RecruitingPipelineController.php) | 12 / S |
| [ ] | R0812 | `DELETE /api/v1/recruiting-pipeline/applications/{interest}` | `api.v1.recruiting-pipeline.applications.destroy` | [RecruitingPipelineController@destroy](../app/Http/Controllers/Api/V1/RecruitingPipelineController.php) | 12 / D |
| [ ] | R0813 | `POST /api/v1/recruiting-pipeline/applications/{interest}/chat` | `api.v1.recruiting-pipeline.applications.chat` | [RecruitingPipelineController@chat](../app/Http/Controllers/Api/V1/RecruitingPipelineController.php) | 12 / S |
| [ ] | R0814 | `POST /api/v1/reports` | `api.v1.reports.store` | [ContentReportController@store](../app/Http/Controllers/ContentReportController.php) | 04, 33 / S |
| [ ] | R0815 | `POST /api/v1/reports/{report}/appeal` | `api.v1.reports.appeal` | [ContentReportController@appeal](../app/Http/Controllers/ContentReportController.php) | 04, 33 / S |
| [ ] | R0816 | `GET\|HEAD /api/v1/rides` | `api.v1.rides.index` | [RideController@index](../app/Http/Controllers/Api/V1/RideController.php) | 38 / L |
| [ ] | R0817 | `POST /api/v1/rides` | `api.v1.rides.store` | [RideController@store](../app/Http/Controllers/Api/V1/RideController.php) | 38 / S |
| [ ] | R0818 | `PUT /api/v1/rides/{ride}` | `api.v1.rides.update` | [RideController@update](../app/Http/Controllers/Api/V1/RideController.php) | 38 / S |
| [ ] | R0819 | `DELETE /api/v1/rides/{ride}` | `api.v1.rides.destroy` | [RideController@destroy](../app/Http/Controllers/Api/V1/RideController.php) | 38 / D |
| [ ] | R0820 | `POST /api/v1/rides/{ride}/join` | `api.v1.rides.join` | [RideController@join](../app/Http/Controllers/Api/V1/RideController.php) | 38 / S |
| [ ] | R0821 | `POST /api/v1/rides/{ride}/leave` | `api.v1.rides.leave` | [RideController@leave](../app/Http/Controllers/Api/V1/RideController.php) | 38 / S |
| [ ] | R0822 | `DELETE /api/v1/rides/{ride}/members/{user}` | `api.v1.rides.members.destroy` | [RideController@removeMember](../app/Http/Controllers/Api/V1/RideController.php) | 38 / D |
| [ ] | R0823 | `POST /api/v1/rides/{ride}/requests/{user}/approve` | `api.v1.rides.requests.approve` | [RideController@approveRequest](../app/Http/Controllers/Api/V1/RideController.php) | 38 / S |
| [ ] | R0824 | `POST /api/v1/rides/{ride}/requests/{user}/reject` | `api.v1.rides.requests.reject` | [RideController@rejectRequest](../app/Http/Controllers/Api/V1/RideController.php) | 38 / S |
| [ ] | R0825 | `GET\|HEAD /api/v1/role-applications` | `api.v1.role-applications.index` | [AccountRoleApplicationController@index](../app/Http/Controllers/AccountRoleApplicationController.php) | 09, 15 / L |
| [ ] | R0826 | `POST /api/v1/role-applications` | `api.v1.role-applications.store` | [AccountRoleApplicationController@store](../app/Http/Controllers/AccountRoleApplicationController.php) | 09, 15 / S |
| [ ] | R0827 | `POST /api/v1/safety/reports` | `api.v1.safety.reports.store` | [SupportTicketController@storeSafetyReport](../app/Http/Controllers/Api/V1/SupportTicketController.php) | 39, 32 / S |
| [ ] | R0828 | `GET\|HEAD /api/v1/saved-views` | `api.v1.saved-views.index` | [SavedViewController@index](../app/Http/Controllers/SavedViewController.php) | 03, 36 / L |
| [ ] | R0829 | `POST /api/v1/saved-views` | `api.v1.saved-views.store` | [SavedViewController@store](../app/Http/Controllers/SavedViewController.php) | 03, 36 / S |
| [ ] | R0830 | `PUT /api/v1/saved-views/{savedView}` | `api.v1.saved-views.update` | [SavedViewController@update](../app/Http/Controllers/SavedViewController.php) | 03, 36 / S |
| [ ] | R0831 | `DELETE /api/v1/saved-views/{savedView}` | `api.v1.saved-views.destroy` | [SavedViewController@destroy](../app/Http/Controllers/SavedViewController.php) | 03, 36 / D |
| [ ] | R0832 | `GET\|HEAD /api/v1/search` | `api.v1.search` | [GlobalSearchController](../app/Http/Controllers/GlobalSearchController.php) | 03, 36 / L |
| [ ] | R0833 | `GET\|HEAD /api/v1/settings` | `api.v1.settings.show` | [SettingsController@show](../app/Http/Controllers/Api/V1/SettingsController.php) | 02, 01 / L |
| [ ] | R0834 | `PATCH /api/v1/settings` | `api.v1.settings.update` | [SettingsController@update](../app/Http/Controllers/Api/V1/SettingsController.php) | 02, 01 / S |
| [ ] | R0835 | `GET\|HEAD /api/v1/sponsor-management` | `api.v1.sponsor-management.index` | [SponsorManagementController@index](../app/Http/Controllers/Api/V1/SponsorManagementController.php) | 29 / L |
| [ ] | R0836 | `POST /api/v1/sponsor-management` | `api.v1.sponsor-management.store` | [SponsorManagementController@store](../app/Http/Controllers/Api/V1/SponsorManagementController.php) | 29 / S |
| [ ] | R0837 | `PUT /api/v1/sponsor-management/{sponsor}` | `api.v1.sponsor-management.update` | [SponsorManagementController@update](../app/Http/Controllers/Api/V1/SponsorManagementController.php) | 29 / S |
| [ ] | R0838 | `DELETE /api/v1/sponsor-management/{sponsor}` | `api.v1.sponsor-management.destroy` | [SponsorManagementController@destroy](../app/Http/Controllers/Api/V1/SponsorManagementController.php) | 29 / D |
| [ ] | R0839 | `POST /api/v1/sponsor-management/{sponsor}/deliverables` | `api.v1.sponsor-management.deliverables.store` | [SponsorDeliverableManagementController@store](../app/Http/Controllers/Api/V1/SponsorDeliverableManagementController.php) | 29 / S |
| [ ] | R0840 | `PUT /api/v1/sponsor-management/{sponsor}/deliverables/{deliverable}` | `api.v1.sponsor-management.deliverables.update` | [SponsorDeliverableManagementController@update](../app/Http/Controllers/Api/V1/SponsorDeliverableManagementController.php) | 29 / S |
| [ ] | R0841 | `DELETE /api/v1/sponsor-management/{sponsor}/deliverables/{deliverable}` | `api.v1.sponsor-management.deliverables.destroy` | [SponsorDeliverableManagementController@destroy](../app/Http/Controllers/Api/V1/SponsorDeliverableManagementController.php) | 29 / D |
| [ ] | R0842 | `GET\|HEAD /api/v1/sponsor-workspace` | `api.v1.sponsor-workspace.index` | [SponsorWorkspaceController@index](../app/Http/Controllers/Api/V1/SponsorWorkspaceController.php) | 29 / L |
| [ ] | R0843 | `PUT /api/v1/sponsor-workspace/profile` | `api.v1.sponsor-workspace.profile.update` | [SponsorWorkspaceController@updateProfile](../app/Http/Controllers/Api/V1/SponsorWorkspaceController.php) | 29 / S |
| [ ] | R0844 | `GET\|HEAD /api/v1/sport-integrations` | `api.v1.sport-integrations.index` | [SportIntegrationController@index](../app/Http/Controllers/Api/V1/SportIntegrationController.php) | 40 / L |
| [ ] | R0845 | `DELETE /api/v1/sport-integrations/accounts/{account}` | `api.v1.sport-integrations.disconnect` | [SportIntegrationController@disconnect](../app/Http/Controllers/Api/V1/SportIntegrationController.php) | 40 / D |
| [ ] | R0846 | `POST /api/v1/sport-integrations/accounts/{account}/sync` | `api.v1.sport-integrations.sync` | [SportIntegrationController@sync](../app/Http/Controllers/Api/V1/SportIntegrationController.php) | 40 / S |
| [ ] | R0847 | `POST /api/v1/sport-integrations/activities/import` | `api.v1.sport-integrations.activities.import` | [SportIntegrationController@importActivity](../app/Http/Controllers/Api/V1/SportIntegrationController.php) | 40 / S |
| [ ] | R0848 | `PUT /api/v1/sport-integrations/activities/{activity}` | `api.v1.sport-integrations.activities.update` | [SportIntegrationController@updateActivity](../app/Http/Controllers/Api/V1/SportIntegrationController.php) | 40 / S |
| [ ] | R0849 | `DELETE /api/v1/sport-integrations/activities/{activity}` | `api.v1.sport-integrations.activities.destroy` | [SportIntegrationController@destroyActivity](../app/Http/Controllers/Api/V1/SportIntegrationController.php) | 40 / D |
| [ ] | R0850 | `POST /api/v1/sport-integrations/{provider}/request` | `api.v1.sport-integrations.request` | [SportIntegrationController@requestProvider](../app/Http/Controllers/Api/V1/SportIntegrationController.php) | 40 / S |
| [ ] | R0851 | `GET\|HEAD /api/v1/sport-matching` | `api.v1.sport-matching.index` | [SportMatchingController@index](../app/Http/Controllers/Api/V1/SportMatchingController.php) | 12 / L |
| [ ] | R0852 | `POST /api/v1/sport-matching` | `api.v1.sport-matching.store` | [SportMatchingController@store](../app/Http/Controllers/Api/V1/SportMatchingController.php) | 12 / S |
| [ ] | R0853 | `PUT /api/v1/sport-matching/{sportMatching}/applications/{application}` | `api.v1.sport-matching.applications.update` | [SportMatchingController@decide](../app/Http/Controllers/Api/V1/SportMatchingController.php) | 12 / S |
| [ ] | R0854 | `POST /api/v1/sport-matching/{sportMatching}/apply` | `api.v1.sport-matching.apply` | [SportMatchingController@apply](../app/Http/Controllers/Api/V1/SportMatchingController.php) | 12 / S |
| [ ] | R0855 | `PUT /api/v1/sport-matching/{sportMatching}/attendance` | `api.v1.sport-matching.attendance.update` | [SportMatchingController@updateAttendance](../app/Http/Controllers/Api/V1/SportMatchingController.php) | 12 / S |
| [ ] | R0856 | `POST /api/v1/sport-matching/{sportMatching}/attendance/no-show` | `api.v1.sport-matching.attendance.no-show` | [SportMatchingController@reportNoShow](../app/Http/Controllers/Api/V1/SportMatchingController.php) | 12 / S |
| [ ] | R0857 | `POST /api/v1/sport-matching/{sportMatching}/cancel` | `api.v1.sport-matching.cancel` | [SportMatchingController@cancel](../app/Http/Controllers/Api/V1/SportMatchingController.php) | 12 / S |
| [ ] | R0858 | `POST /api/v1/sport-matching/{sportMatching}/dismiss` | `api.v1.sport-matching.dismiss` | [SportMatchingController@dismiss](../app/Http/Controllers/Api/V1/SportMatchingController.php) | 12 / S |
| [ ] | R0859 | `POST /api/v1/sport-matching/{sportMatching}/withdraw` | `api.v1.sport-matching.withdraw` | [SportMatchingController@withdraw](../app/Http/Controllers/Api/V1/SportMatchingController.php) | 12 / S |
| [ ] | R0860 | `GET\|HEAD /api/v1/sport-places` | `api.v1.sport-places.index` | [SportMapController@places](../app/Http/Controllers/Api/V1/SportMapController.php) | 11 / L |
| [ ] | R0861 | `POST /api/v1/sport-places` | `api.v1.sport-places.store` | [SportMapController@storePlace](../app/Http/Controllers/Api/V1/SportMapController.php) | 11 / S |
| [ ] | R0862 | `GET\|HEAD /api/v1/sport-places/{sportPlace}` | `api.v1.sport-places.show` | [SportMapController@showPlace](../app/Http/Controllers/Api/V1/SportMapController.php) | 11 / L |
| [ ] | R0863 | `PATCH /api/v1/sport-places/{sportPlace}` | `api.v1.sport-places.update` | [SportMapController@updatePlace](../app/Http/Controllers/Api/V1/SportMapController.php) | 11 / S |
| [ ] | R0864 | `DELETE /api/v1/sport-places/{sportPlace}` | `api.v1.sport-places.destroy` | [SportMapController@destroyPlace](../app/Http/Controllers/Api/V1/SportMapController.php) | 11 / D |
| [ ] | R0865 | `GET\|HEAD /api/v1/sport-profiles` | `api.v1.sport-profiles.index` | [SportProfileController@index](../app/Http/Controllers/Api/V1/SportProfileController.php) | 09 / L |
| [ ] | R0866 | `GET\|HEAD /api/v1/sport-profiles/scout-search` | `api.v1.sport-profiles.scout-search` | [SportProfileController@scoutSearch](../app/Http/Controllers/Api/V1/SportProfileController.php) | 09 / L |
| [ ] | R0867 | `PUT /api/v1/sport-profiles/{sport}` | `api.v1.sport-profiles.update` | [SportProfileController@update](../app/Http/Controllers/Api/V1/SportProfileController.php) | 09 / S |
| [ ] | R0868 | `DELETE /api/v1/sport-profiles/{sport}` | `api.v1.sport-profiles.destroy` | [SportProfileController@destroy](../app/Http/Controllers/Api/V1/SportProfileController.php) | 09 / D |
| [ ] | R0869 | `POST /api/v1/sport-route-proposals` | `api.v1.sport-route-proposals.store` | [SportMapController@generateRouteProposal](../app/Http/Controllers/Api/V1/SportMapController.php) | 11 / S |
| [ ] | R0870 | `GET\|HEAD /api/v1/sport-routes` | `api.v1.sport-routes.index` | [SportMapController@routes](../app/Http/Controllers/Api/V1/SportMapController.php) | 11 / L |
| [ ] | R0871 | `POST /api/v1/sport-routes` | `api.v1.sport-routes.store` | [SportMapController@storeRoute](../app/Http/Controllers/Api/V1/SportMapController.php) | 11 / S |
| [ ] | R0872 | `GET\|HEAD /api/v1/sport-routes/{sportRoute}` | `api.v1.sport-routes.show` | [SportMapController@showRoute](../app/Http/Controllers/Api/V1/SportMapController.php) | 11 / L |
| [ ] | R0873 | `PATCH /api/v1/sport-routes/{sportRoute}` | `api.v1.sport-routes.update` | [SportMapController@updateRoute](../app/Http/Controllers/Api/V1/SportMapController.php) | 11 / S |
| [ ] | R0874 | `DELETE /api/v1/sport-routes/{sportRoute}` | `api.v1.sport-routes.destroy` | [SportMapController@destroyRoute](../app/Http/Controllers/Api/V1/SportMapController.php) | 11 / D |
| [ ] | R0875 | `POST /api/v1/sport-routes/{sportRoute}/duplicate` | `api.v1.sport-routes.duplicate` | [SportMapController@duplicateRoute](../app/Http/Controllers/Api/V1/SportMapController.php) | 11 / S |
| [ ] | R0876 | `PATCH /api/v1/sport-skills/{userSportSkill}` | `api.v1.sport-skills.update` | [SportProfileController@updateSkill](../app/Http/Controllers/Api/V1/SportProfileController.php) | 09 / S |
| [ ] | R0877 | `GET\|HEAD /api/v1/sport-tracks` | `api.v1.sport-tracks.index` | [SportMapController@tracks](../app/Http/Controllers/Api/V1/SportMapController.php) | 11 / L |
| [ ] | R0878 | `POST /api/v1/sport-tracks` | `api.v1.sport-tracks.store` | [SportMapController@storeTrack](../app/Http/Controllers/Api/V1/SportMapController.php) | 11 / S |
| [ ] | R0879 | `PATCH /api/v1/sport-tracks/{sportRouteTrack}` | `api.v1.sport-tracks.update` | [SportMapController@updateTrack](../app/Http/Controllers/Api/V1/SportMapController.php) | 11 / S |
| [ ] | R0880 | `DELETE /api/v1/sport-tracks/{sportRouteTrack}` | `api.v1.sport-tracks.destroy` | [SportMapController@destroyTrack](../app/Http/Controllers/Api/V1/SportMapController.php) | 11 / D |
| [ ] | R0881 | `POST /api/v1/sport-tracks/{sportRouteTrack}/complete` | `api.v1.sport-tracks.complete` | [SportMapController@completeTrack](../app/Http/Controllers/Api/V1/SportMapController.php) | 11 / S |
| [ ] | R0882 | `POST /api/v1/sport-tracks/{sportRouteTrack}/points` | `api.v1.sport-tracks.points` | [SportMapController@appendTrackPoints](../app/Http/Controllers/Api/V1/SportMapController.php) | 11 / S |
| [ ] | R0883 | `GET\|HEAD /api/v1/sports` | `api.v1.sports.index` | `Closure` | 40 / L |
| [ ] | R0884 | `GET\|HEAD /api/v1/stories` | `api.v1.stories.index` | [StoryController@index](../app/Http/Controllers/Api/V1/StoryController.php) | 04, 02 / L |
| [ ] | R0885 | `POST /api/v1/stories` | `api.v1.stories.store` | [StoryController@store](../app/Http/Controllers/Api/V1/StoryController.php) | 04, 02 / S |
| [ ] | R0886 | `DELETE /api/v1/stories/{story}` | `api.v1.stories.destroy` | [StoryController@destroy](../app/Http/Controllers/Api/V1/StoryController.php) | 04, 02 / D |
| [ ] | R0887 | `POST /api/v1/stories/{story}/react` | `api.v1.stories.react` | [StoryController@react](../app/Http/Controllers/Api/V1/StoryController.php) | 04, 02 / S |
| [ ] | R0888 | `POST /api/v1/stories/{story}/viewed` | `api.v1.stories.viewed` | [StoryController@viewed](../app/Http/Controllers/Api/V1/StoryController.php) | 04, 02 / S |
| [ ] | R0889 | `GET\|HEAD /api/v1/subscription-checkouts/{checkout}` | `api.v1.subscription-checkouts.show` | [SubscriptionController@checkout](../app/Http/Controllers/Api/V1/SubscriptionController.php) | 31 / L |
| [ ] | R0890 | `POST /api/v1/subscription-checkouts/{checkout}/cancel` | `api.v1.subscription-checkouts.cancel` | [SubscriptionController@cancelCheckout](../app/Http/Controllers/Api/V1/SubscriptionController.php) | 31 / S |
| [ ] | R0891 | `GET\|HEAD /api/v1/subscription-plans` | `api.v1.subscription-plans.index` | [SubscriptionController@plans](../app/Http/Controllers/Api/V1/SubscriptionController.php) | 31 / L |
| [ ] | R0892 | `POST /api/v1/subscription-plans/{subscriptionPlan}/checkout` | `api.v1.subscription-plans.checkout` | [SubscriptionController@startCheckout](../app/Http/Controllers/Api/V1/SubscriptionController.php) | 31 / S |
| [ ] | R0893 | `GET\|HEAD /api/v1/subscriptions` | `api.v1.subscriptions.index` | [SubscriptionController@index](../app/Http/Controllers/Api/V1/SubscriptionController.php) | 31 / L |
| [ ] | R0894 | `POST /api/v1/subscriptions/user/{subscription}/cancel` | `api.v1.subscriptions.user.cancel` | [SubscriptionController@cancelUserSubscription](../app/Http/Controllers/Api/V1/SubscriptionController.php) | 31 / S |
| [ ] | R0895 | `POST /api/v1/subscriptions/user/{subscription}/renew` | `api.v1.subscriptions.user.renew` | [SubscriptionController@renewUserSubscription](../app/Http/Controllers/Api/V1/SubscriptionController.php) | 31 / S |
| [ ] | R0896 | `POST /api/v1/support/contact` | `api.v1.support.contact.store` | [KontaktController@store](../app/Http/Controllers/KontaktController.php) | 39 / S |
| [ ] | R0897 | `GET\|HEAD /api/v1/support/tickets` | `api.v1.support.tickets.index` | [SupportTicketController@index](../app/Http/Controllers/Api/V1/SupportTicketController.php) | 39 / L |
| [ ] | R0898 | `POST /api/v1/support/tickets` | `api.v1.support.tickets.store` | [SupportTicketController@store](../app/Http/Controllers/Api/V1/SupportTicketController.php) | 39 / S |
| [ ] | R0899 | `GET\|HEAD /api/v1/team-invitations` | `api.v1.team-invitations.index` | [TeamController@invitations](../app/Http/Controllers/Api/V1/TeamController.php) | 23, 08 / L |
| [ ] | R0900 | `GET\|HEAD /api/v1/team-invitations/token/{token}` | `api.v1.team-invitations.token.show` | [TeamController@invitationByToken](../app/Http/Controllers/Api/V1/TeamController.php) | 23, 08 / L |
| [ ] | R0901 | `POST /api/v1/team-invitations/token/{token}/accept` | `api.v1.team-invitations.token.accept` | [TeamController@acceptInvitationByToken](../app/Http/Controllers/Api/V1/TeamController.php) | 23, 08 / S |
| [ ] | R0902 | `POST /api/v1/team-invitations/token/{token}/decline` | `api.v1.team-invitations.token.decline` | [TeamController@declineInvitationByToken](../app/Http/Controllers/Api/V1/TeamController.php) | 23, 08 / S |
| [ ] | R0903 | `GET\|HEAD /api/v1/team-invitations/{invitation}` | `api.v1.team-invitations.show` | [TeamController@invitation](../app/Http/Controllers/Api/V1/TeamController.php) | 23, 08 / L |
| [ ] | R0904 | `POST /api/v1/team-invitations/{invitation}/accept` | `api.v1.team-invitations.accept` | [TeamController@acceptInvitation](../app/Http/Controllers/Api/V1/TeamController.php) | 23, 08 / S |
| [ ] | R0905 | `POST /api/v1/team-invitations/{invitation}/decline` | `api.v1.team-invitations.decline` | [TeamController@declineInvitation](../app/Http/Controllers/Api/V1/TeamController.php) | 23, 08 / S |
| [ ] | R0906 | `POST /api/v1/team-join-requests/{joinRequest}/approve` | `api.v1.team-join-requests.approve` | [TeamController@approveJoinRequestById](../app/Http/Controllers/Api/V1/TeamController.php) | 23, 08 / S |
| [ ] | R0907 | `POST /api/v1/team-join-requests/{joinRequest}/decline` | `api.v1.team-join-requests.decline` | [TeamController@declineJoinRequestById](../app/Http/Controllers/Api/V1/TeamController.php) | 23, 08 / S |
| [ ] | R0908 | `POST /api/v1/team-transfer-requests/{transferRequest}/approve` | `api.v1.team-transfer-requests.approve` | [TeamController@approveTransferRequest](../app/Http/Controllers/Api/V1/TeamController.php) | 23, 08 / S |
| [ ] | R0909 | `POST /api/v1/team-transfer-requests/{transferRequest}/decline` | `api.v1.team-transfer-requests.decline` | [TeamController@declineTransferRequest](../app/Http/Controllers/Api/V1/TeamController.php) | 23, 08 / S |
| [ ] | R0910 | `POST /api/v1/teams` | `api.v1.teams.store.token` | [TeamController@storeWithToken](../app/Http/Controllers/Api/V1/TeamController.php) | 23, 08 / S |
| [ ] | R0911 | `GET\|HEAD /api/v1/teams` | `api.v1.teams.index` | [TeamController@index](../app/Http/Controllers/Api/V1/TeamController.php) | 23, 08 / L |
| [ ] | R0912 | `GET\|HEAD /api/v1/teams/{team}` | `api.v1.teams.show` | [TeamController@show](../app/Http/Controllers/Api/V1/TeamController.php) | 23, 08 / L |
| [ ] | R0913 | `PUT /api/v1/teams/{team}` | `api.v1.teams.update` | [TeamController@update](../app/Http/Controllers/Api/V1/TeamController.php) | 23, 08 / S |
| [ ] | R0914 | `DELETE /api/v1/teams/{team}` | `api.v1.teams.destroy` | [TeamController@destroy](../app/Http/Controllers/Api/V1/TeamController.php) | 23, 08 / D |
| [ ] | R0915 | `GET\|HEAD /api/v1/teams/{team}/attendance-stats` | `api.v1.teams.attendance-stats` | [TeamController@attendanceStats](../app/Http/Controllers/Api/V1/TeamController.php) | 23, 08 / L |
| [ ] | R0916 | `GET\|HEAD /api/v1/teams/{team}/competitiveness/insights` | `api.v1.teams.competitiveness.insights` | [TeamCompetitivenessController@insights](../app/Http/Controllers/Api/V1/TeamCompetitivenessController.php) | 23, 08 / L |
| [ ] | R0917 | `GET\|HEAD /api/v1/teams/{team}/daily-life` | `api.v1.teams.daily-life` | `Closure` | 23, 08 / L |
| [ ] | R0918 | `POST /api/v1/teams/{team}/invite` | `api.v1.teams.invite` | [TeamController@invite](../app/Http/Controllers/Api/V1/TeamController.php) | 23, 08 / S |
| [ ] | R0919 | `POST /api/v1/teams/{team}/join-requests` | `api.v1.teams.join-requests.store` | [TeamController@requestJoin](../app/Http/Controllers/Api/V1/TeamController.php) | 23, 08 / S |
| [ ] | R0920 | `POST /api/v1/teams/{team}/join-requests/{joinRequest}/approve` | `api.v1.teams.join-requests.approve` | [TeamController@approveJoinRequest](../app/Http/Controllers/Api/V1/TeamController.php) | 23, 08 / S |
| [ ] | R0921 | `POST /api/v1/teams/{team}/join-requests/{joinRequest}/decline` | `api.v1.teams.join-requests.decline` | [TeamController@declineJoinRequest](../app/Http/Controllers/Api/V1/TeamController.php) | 23, 08 / S |
| [ ] | R0922 | `POST /api/v1/teams/{team}/members` | `api.v1.teams.members.store` | [TeamController@storeMember](../app/Http/Controllers/Api/V1/TeamController.php) | 23, 08 / S |
| [ ] | R0923 | `PUT /api/v1/teams/{team}/members/{user}` | `api.v1.teams.members.update` | [TeamController@updateMember](../app/Http/Controllers/Api/V1/TeamController.php) | 23, 08 / S |
| [ ] | R0924 | `DELETE /api/v1/teams/{team}/members/{user}` | `api.v1.teams.members.destroy` | [TeamController@removeMember](../app/Http/Controllers/Api/V1/TeamController.php) | 23, 08 / D |
| [ ] | R0925 | `GET\|HEAD /api/v1/teams/{team}/penalties` | `api.v1.teams.penalties.index` | [TeamPenaltyController@index](../app/Http/Controllers/Api/V1/TeamPenaltyController.php) | 23, 08 / L |
| [ ] | R0926 | `POST /api/v1/teams/{team}/penalty-fees` | `api.v1.teams.penalty-fees.store` | [TeamPenaltyController@storeFee](../app/Http/Controllers/Api/V1/TeamPenaltyController.php) | 23, 08 / S |
| [ ] | R0927 | `POST /api/v1/teams/{team}/penalty-fees/{fee}/cancel` | `api.v1.teams.penalty-fees.cancel` | [TeamPenaltyController@cancelFee](../app/Http/Controllers/Api/V1/TeamPenaltyController.php) | 23, 08 / S |
| [ ] | R0928 | `POST /api/v1/teams/{team}/penalty-fees/{fee}/paid` | `api.v1.teams.penalty-fees.paid` | [TeamPenaltyController@markFeePaid](../app/Http/Controllers/Api/V1/TeamPenaltyController.php) | 23, 08 / S |
| [ ] | R0929 | `POST /api/v1/teams/{team}/penalty-rules` | `api.v1.teams.penalty-rules.store` | [TeamPenaltyController@storeRule](../app/Http/Controllers/Api/V1/TeamPenaltyController.php) | 23, 08 / S |
| [ ] | R0930 | `PUT /api/v1/teams/{team}/penalty-rules/{penaltyRule}` | `api.v1.teams.penalty-rules.update` | [TeamPenaltyController@updateRule](../app/Http/Controllers/Api/V1/TeamPenaltyController.php) | 23, 08 / S |
| [ ] | R0931 | `DELETE /api/v1/teams/{team}/penalty-rules/{penaltyRule}` | `api.v1.teams.penalty-rules.destroy` | [TeamPenaltyController@destroyRule](../app/Http/Controllers/Api/V1/TeamPenaltyController.php) | 23, 08 / D |
| [ ] | R0932 | `POST /api/v1/teams/{team}/transfer-requests` | `api.v1.teams.transfer-requests.store` | [TeamController@requestTransfer](../app/Http/Controllers/Api/V1/TeamController.php) | 23, 08 / S |
| [ ] | R0933 | `POST /api/v1/teams/{team}/transfers` | `api.v1.teams.transfers.store` | [TeamController@storeTransfer](../app/Http/Controllers/Api/V1/TeamController.php) | 23, 08 / S |
| [ ] | R0934 | `POST /api/v1/trainer-cockpit/logs/{trainingLog}/feedback` | `api.v1.trainer-cockpit.logs.feedback.store` | [TrainingFeedbackController@store](../app/Http/Controllers/Api/V1/TrainingFeedbackController.php) | 09, 15 / S |
| [ ] | R0935 | `GET\|HEAD /api/v1/trainer-cockpit{slash}` | `api.v1.trainer-cockpit.index` | [TrainerCockpitController@index](../app/Http/Controllers/TrainerCockpitController.php) | 09, 15 / L |
| [ ] | R0936 | `POST /api/v1/training/ai/plans` | `api.v1.training.ai.plans.store` | [TrainingController@storeAiTrainingPlan](../app/Http/Controllers/TrainingController.php) | 09, 15 / S |
| [ ] | R0937 | `POST /api/v1/training/ai/plans/preview` | `api.v1.training.ai.plans.preview` | [TrainingController@previewAiTrainingPlan](../app/Http/Controllers/TrainingController.php) | 09, 15 / S |
| [ ] | R0938 | `GET\|HEAD /api/v1/training/analytics` | `api.v1.training.analytics.index` | [TrainingAnalyticsController@index](../app/Http/Controllers/Api/V1/TrainingAnalyticsController.php) | 09, 15 / L |
| [ ] | R0939 | `GET\|HEAD /api/v1/training/availability` | `api.v1.training.availability.index` | [TrainingAvailabilityController@index](../app/Http/Controllers/Api/V1/TrainingAvailabilityController.php) | 09, 15 / L |
| [ ] | R0940 | `POST /api/v1/training/availability` | `api.v1.training.availability.store` | [TrainingAvailabilityController@store](../app/Http/Controllers/Api/V1/TrainingAvailabilityController.php) | 09, 15 / S |
| [ ] | R0941 | `PUT /api/v1/training/availability/{trainingAvailabilityStatus}` | `api.v1.training.availability.update` | [TrainingAvailabilityController@update](../app/Http/Controllers/Api/V1/TrainingAvailabilityController.php) | 09, 15 / S |
| [ ] | R0942 | `DELETE /api/v1/training/availability/{trainingAvailabilityStatus}` | `api.v1.training.availability.destroy` | [TrainingAvailabilityController@destroy](../app/Http/Controllers/Api/V1/TrainingAvailabilityController.php) | 09, 15 / D |
| [ ] | R0943 | `GET\|HEAD /api/v1/training/exercises` | `api.v1.training.exercises.index` | [TrainingExerciseController@index](../app/Http/Controllers/Api/V1/TrainingExerciseController.php) | 09, 15 / L |
| [ ] | R0944 | `POST /api/v1/training/exercises` | `api.v1.training.exercises.store` | [TrainingExerciseController@store](../app/Http/Controllers/Api/V1/TrainingExerciseController.php) | 09, 15 / S |
| [ ] | R0945 | `GET\|HEAD /api/v1/training/exercises/{trainingExercise}` | `api.v1.training.exercises.show` | [TrainingExerciseController@show](../app/Http/Controllers/Api/V1/TrainingExerciseController.php) | 09, 15 / L |
| [ ] | R0946 | `PUT /api/v1/training/exercises/{trainingExercise}` | `api.v1.training.exercises.update` | [TrainingExerciseController@update](../app/Http/Controllers/Api/V1/TrainingExerciseController.php) | 09, 15 / S |
| [ ] | R0947 | `DELETE /api/v1/training/exercises/{trainingExercise}` | `api.v1.training.exercises.destroy` | [TrainingExerciseController@destroy](../app/Http/Controllers/Api/V1/TrainingExerciseController.php) | 09, 15 / D |
| [ ] | R0948 | `POST /api/v1/training/exercises/{trainingExercise}/add-to-plan` | `api.v1.training.exercises.add-to-plan` | [TrainingExerciseController@addToPlan](../app/Http/Controllers/Api/V1/TrainingExerciseController.php) | 09, 15 / S |
| [ ] | R0949 | `GET\|HEAD /api/v1/training/logs` | `api.v1.training.logs.index` | [TrainingController@logs](../app/Http/Controllers/Api/V1/TrainingController.php) | 09, 15 / L |
| [ ] | R0950 | `POST /api/v1/training/logs` | `api.v1.training.logs.store` | [TrainingController@storeLog](../app/Http/Controllers/Api/V1/TrainingController.php) | 09, 15 / S |
| [ ] | R0951 | `GET\|HEAD /api/v1/training/logs/{trainingLog}` | `api.v1.training.logs.show` | [TrainingController@showLog](../app/Http/Controllers/Api/V1/TrainingController.php) | 09, 15 / L |
| [ ] | R0952 | `PUT /api/v1/training/logs/{trainingLog}` | `api.v1.training.logs.update` | [TrainingController@updateLog](../app/Http/Controllers/Api/V1/TrainingController.php) | 09, 15 / S |
| [ ] | R0953 | `DELETE /api/v1/training/logs/{trainingLog}` | `api.v1.training.logs.destroy` | [TrainingController@destroyLog](../app/Http/Controllers/Api/V1/TrainingController.php) | 09, 15 / D |
| [ ] | R0954 | `POST /api/v1/training/logs/{trainingLog}/feedback` | `api.v1.training.logs.feedback.store` | [TrainingFeedbackController@store](../app/Http/Controllers/Api/V1/TrainingFeedbackController.php) | 09, 15 / S |
| [ ] | R0955 | `GET\|HEAD /api/v1/training/overload-indicators` | `api.v1.training.overload-indicators.index` | [TrainingAnalyticsController@overloadIndicators](../app/Http/Controllers/Api/V1/TrainingAnalyticsController.php) | 09, 15 / L |
| [ ] | R0956 | `GET\|HEAD /api/v1/training/plans` | `api.v1.training.plans.index` | [TrainingController@plans](../app/Http/Controllers/Api/V1/TrainingController.php) | 09, 15 / L |
| [ ] | R0957 | `POST /api/v1/training/plans` | `api.v1.training.plans.store` | [TrainingController@storePlan](../app/Http/Controllers/Api/V1/TrainingController.php) | 09, 15 / S |
| [ ] | R0958 | `GET\|HEAD /api/v1/training/plans/{trainingPlan}` | `api.v1.training.plans.show` | [TrainingController@showPlan](../app/Http/Controllers/Api/V1/TrainingController.php) | 09, 15 / L |
| [ ] | R0959 | `PUT /api/v1/training/plans/{trainingPlan}` | `api.v1.training.plans.update` | [TrainingController@updatePlan](../app/Http/Controllers/Api/V1/TrainingController.php) | 09, 15 / S |
| [ ] | R0960 | `DELETE /api/v1/training/plans/{trainingPlan}` | `api.v1.training.plans.destroy` | [TrainingController@destroyPlan](../app/Http/Controllers/Api/V1/TrainingController.php) | 09, 15 / D |
| [ ] | R0961 | `POST /api/v1/training/plans/{trainingPlan}/duplicate` | `api.v1.training.plans.duplicate` | [TrainingController@duplicatePlan](../app/Http/Controllers/Api/V1/TrainingController.php) | 09, 15 / S |
| [ ] | R0962 | `POST /api/v1/training/plans/{trainingPlan}/handover` | `api.v1.training.plans.handover` | [TrainingController@handoverPlan](../app/Http/Controllers/Api/V1/TrainingController.php) | 09, 15 / S |
| [ ] | R0963 | `POST /api/v1/training/plans/{trainingPlan}/items` | `api.v1.training.plans.items.store` | [TrainingController@storePlanItem](../app/Http/Controllers/Api/V1/TrainingController.php) | 09, 15 / S |
| [ ] | R0964 | `PUT /api/v1/training/plans/{trainingPlan}/items/{trainingPlanItem}` | `api.v1.training.plans.items.update` | [TrainingController@updatePlanItem](../app/Http/Controllers/Api/V1/TrainingController.php) | 09, 15 / S |
| [ ] | R0965 | `DELETE /api/v1/training/plans/{trainingPlan}/items/{trainingPlanItem}` | `api.v1.training.plans.items.destroy` | [TrainingController@destroyPlanItem](../app/Http/Controllers/Api/V1/TrainingController.php) | 09, 15 / D |
| [ ] | R0966 | `POST /api/v1/training/plans/{trainingPlan}/items/{trainingPlanItem}/duplicate` | `api.v1.training.plans.items.duplicate` | [TrainingController@duplicatePlanItem](../app/Http/Controllers/Api/V1/TrainingController.php) | 09, 15 / S |
| [ ] | R0967 | `POST /api/v1/training/plans/{trainingPlan}/items/{trainingPlanItem}/missed` | `api.v1.training.plans.items.missed` | [TrainingController@markPlanItemMissed](../app/Http/Controllers/Api/V1/TrainingController.php) | 09, 15 / S |
| [ ] | R0968 | `POST /api/v1/training/plans/{trainingPlan}/publish` | `api.v1.training.plans.publish` | [TrainingController@publishPlan](../app/Http/Controllers/Api/V1/TrainingController.php) | 09, 15 / S |
| [ ] | R0969 | `POST /api/v1/training/plans/{trainingPlan}/template` | `api.v1.training.plans.template` | [TrainingController@createTemplate](../app/Http/Controllers/Api/V1/TrainingController.php) | 09, 15 / S |
| [ ] | R0970 | `GET\|HEAD /api/v1/training/route-options` | `api.v1.training.route-options` | [TrainingController@routeOptions](../app/Http/Controllers/Api/V1/TrainingController.php) | 09, 15 / L |
| [ ] | R0971 | `GET\|HEAD /api/v1/training/sessions` | `api.v1.training.sessions.index` | [TrainingSessionController@index](../app/Http/Controllers/Api/V1/TrainingSessionController.php) | 09, 15 / L |
| [ ] | R0972 | `POST /api/v1/training/sessions` | `api.v1.training.sessions.store` | [TrainingSessionController@store](../app/Http/Controllers/Api/V1/TrainingSessionController.php) | 09, 15 / S |
| [ ] | R0973 | `GET\|HEAD /api/v1/training/sessions/{trainingSession}` | `api.v1.training.sessions.show` | [TrainingSessionController@show](../app/Http/Controllers/Api/V1/TrainingSessionController.php) | 09, 15 / L |
| [ ] | R0974 | `PUT /api/v1/training/sessions/{trainingSession}` | `api.v1.training.sessions.update` | [TrainingSessionController@update](../app/Http/Controllers/Api/V1/TrainingSessionController.php) | 09, 15 / S |
| [ ] | R0975 | `DELETE /api/v1/training/sessions/{trainingSession}` | `api.v1.training.sessions.destroy` | [TrainingSessionController@destroy](../app/Http/Controllers/Api/V1/TrainingSessionController.php) | 09, 15 / D |
| [ ] | R0976 | `GET\|HEAD /api/v1/training/templates` | `api.v1.training.templates.index` | [TrainingController@templates](../app/Http/Controllers/Api/V1/TrainingController.php) | 09, 15 / L |
| [ ] | R0977 | `POST /api/v1/training/templates/{trainingPlan}/instantiate` | `api.v1.training.templates.instantiate` | [TrainingController@instantiateTemplate](../app/Http/Controllers/Api/V1/TrainingController.php) | 09, 15 / S |
| [ ] | R0978 | `GET\|HEAD /api/v1/uploads` | `api.v1.uploads.index` | [UploadController@index](../app/Http/Controllers/Api/V1/UploadController.php) | 07 / L |
| [ ] | R0979 | `POST /api/v1/uploads` | `api.v1.uploads.store` | [UploadController@store](../app/Http/Controllers/Api/V1/UploadController.php) | 07 / S |
| [ ] | R0980 | `PATCH /api/v1/uploads/{file}` | `api.v1.uploads.update` | [UploadController@update](../app/Http/Controllers/Api/V1/UploadController.php) | 07 / S |
| [ ] | R0981 | `DELETE /api/v1/uploads/{file}` | `api.v1.uploads.destroy` | [UploadController@destroy](../app/Http/Controllers/Api/V1/UploadController.php) | 07 / D |
| [ ] | R0982 | `POST /api/v1/uploads/{file}/share` | `api.v1.uploads.share` | [UploadController@share](../app/Http/Controllers/Api/V1/UploadController.php) | 07 / S |
| [ ] | R0983 | `GET\|HEAD /api/v1/users/me/sport-cv` | `api.v1.users.me.sport-cv` | [SportProfileController@me](../app/Http/Controllers/Api/V1/SportProfileController.php) | 04, 02 / L |
| [ ] | R0984 | `POST /api/v1/users/{user}/block` | `api.v1.users.block` | [UserSocialProfileController@block](../app/Http/Controllers/Api/V1/UserSocialProfileController.php) | 04, 02 / S |
| [ ] | R0985 | `DELETE /api/v1/users/{user}/block` | `api.v1.users.unblock` | [UserSocialProfileController@unblock](../app/Http/Controllers/Api/V1/UserSocialProfileController.php) | 04, 02 / D |
| [ ] | R0986 | `POST /api/v1/users/{user}/follow` | `api.v1.users.follow` | [UserSocialProfileController@follow](../app/Http/Controllers/Api/V1/UserSocialProfileController.php) | 04, 02 / S |
| [ ] | R0987 | `DELETE /api/v1/users/{user}/follow` | `api.v1.users.unfollow` | [UserSocialProfileController@unfollow](../app/Http/Controllers/Api/V1/UserSocialProfileController.php) | 04, 02 / D |
| [ ] | R0988 | `GET\|HEAD /api/v1/users/{user}/posts` | `api.v1.users.posts.index` | [FeedController@userPosts](../app/Http/Controllers/Api/V1/FeedController.php) | 04, 02 / L |
| [ ] | R0989 | `GET\|HEAD /api/v1/users/{user}/sport-cv` | `api.v1.users.sport-cv.show` | [SportProfileController@show](../app/Http/Controllers/Api/V1/SportProfileController.php) | 04, 02 / L |
| [ ] | R0990 | `OPTIONS /api/v1/{any}` | `api.v1.options` | `Closure` | 36, 34 / X |
| [ ] | R0991 | `GET\|HEAD /auth/{provider}/callback` | `social-auth.callback` | [SocialAuthController@callback](../app/Http/Controllers/SocialAuthController.php) | 01 / L+X |
| [ ] | R0992 | `GET\|HEAD /auth/{provider}/redirect` | `social-auth.redirect` | [SocialAuthController@redirect](../app/Http/Controllers/SocialAuthController.php) | 01 / L |
| [ ] | R0993 | `GET\|HEAD /badges` | `auth.badges.index` | [UserBadgeController@index](../app/Http/Controllers/UserBadgeController.php) | 13, 36 / L |
| [ ] | R0994 | `GET\|HEAD /badges/{userBadge}` | `auth.badges.show` | [UserBadgeController@show](../app/Http/Controllers/UserBadgeController.php) | 13, 36 / L |
| [ ] | R0995 | `GET\|HEAD /blog` | `guest.blog.index` | [BlogPostController@publicIndex](../app/Http/Controllers/BlogPostController.php) | 40 / L |
| [ ] | R0996 | `GET\|HEAD /blog/kategorie/{blogCategory}` | `guest.blog.category` | [BlogPostController@publicCategory](../app/Http/Controllers/BlogPostController.php) | 40 / L |
| [ ] | R0997 | `GET\|HEAD /blog/rss.xml` | `guest.blog.rss` | [PublicBlogFeedController](../app/Http/Controllers/PublicBlogFeedController.php) | 40 / L |
| [ ] | R0998 | `GET\|HEAD /blog/{blogPost}` | `guest.blog.show` | [BlogPostController@publicShow](../app/Http/Controllers/BlogPostController.php) | 40 / L |
| [ ] | R0999 | `GET\|POST\|HEAD /broadcasting/auth` | `(ohne Name)` | `Illuminate\Broadcasting\BroadcastController@authenticate` | 36, 34 / L+S+X |
| [ ] | R1000 | `GET\|HEAD /card` | `auth.commerce.cart.index` | [CommerceCheckoutController@cart](../app/Http/Controllers/CommerceCheckoutController.php) | 30, 29 / L |
| [ ] | R1001 | `GET\|HEAD /cart` | `auth.commerce.cart.redirect` | `Closure` | 30, 29 / L |
| [ ] | R1002 | `GET\|HEAD /challenges` | `auth.challenges.index` | [ChallengeController](../app/Http/Controllers/ChallengeController.php) | 13, 36 / L |
| [ ] | R1003 | `GET\|HEAD /checkout/commerce/{order}/bank-transfer` | `commerce-checkout.bank-transfer.show` | [CommerceCheckoutController@bankTransfer](../app/Http/Controllers/CommerceCheckoutController.php) | 30, 29 / L |
| [ ] | R1004 | `GET\|HEAD /checkout/commerce/{order}/cancel` | `commerce-checkout.cancel` | [CommerceCheckoutController@cancel](../app/Http/Controllers/CommerceCheckoutController.php) | 30, 29 / L+X |
| [ ] | R1005 | `GET\|HEAD /checkout/commerce/{order}/success` | `commerce-checkout.success` | [CommerceCheckoutController@success](../app/Http/Controllers/CommerceCheckoutController.php) | 30, 29 / L+X |
| [ ] | R1006 | `GET\|HEAD /checkout/csrf-token` | `checkout.csrf-token` | `Closure` | 30, 29 / L |
| [ ] | R1007 | `GET\|HEAD /checkout/guest-commerce/{order}/{token}/bank-transfer` | `commerce-checkout.guest.bank-transfer.show` | [CommerceCheckoutController@guestBankTransfer](../app/Http/Controllers/CommerceCheckoutController.php) | 30, 29 / L |
| [ ] | R1008 | `GET\|HEAD /checkout/guest-commerce/{order}/{token}/cancel` | `commerce-checkout.guest.cancel` | [CommerceCheckoutController@guestCancel](../app/Http/Controllers/CommerceCheckoutController.php) | 30, 29 / L+X |
| [ ] | R1009 | `GET\|HEAD /checkout/guest-commerce/{order}/{token}/success` | `commerce-checkout.guest.success` | [CommerceCheckoutController@guestSuccess](../app/Http/Controllers/CommerceCheckoutController.php) | 30, 29 / L+X |
| [ ] | R1010 | `GET\|HEAD /checkout/outfit-subscriptions/{subscription}/cancel` | `outfit-subscription-checkout.cancel` | [OutfitSubscriptionController@cancelCheckout](../app/Http/Controllers/OutfitSubscriptionController.php) | 31 / L+X |
| [ ] | R1011 | `GET\|HEAD /checkout/outfit-subscriptions/{subscription}/success` | `outfit-subscription-checkout.success` | [OutfitSubscriptionController@success](../app/Http/Controllers/OutfitSubscriptionController.php) | 31 / L+X |
| [ ] | R1012 | `GET\|HEAD /checkout/subscriptions/{checkout}/bank-transfer` | `subscription-checkout.bank-transfer.show` | [SubscriptionCheckoutController@bankTransfer](../app/Http/Controllers/SubscriptionCheckoutController.php) | 31 / L |
| [ ] | R1013 | `GET\|HEAD /checkout/subscriptions/{checkout}/cancel` | `subscription-checkout.cancel` | [SubscriptionCheckoutController@cancel](../app/Http/Controllers/SubscriptionCheckoutController.php) | 31 / L+X |
| [ ] | R1014 | `GET\|HEAD /checkout/subscriptions/{checkout}/success` | `subscription-checkout.success` | [SubscriptionCheckoutController@success](../app/Http/Controllers/SubscriptionCheckoutController.php) | 31 / L+X |
| [ ] | R1015 | `POST /checkout/subscriptions/{subscriptionPlan}` | `subscription-checkout.store` | [SubscriptionCheckoutController@store](../app/Http/Controllers/SubscriptionCheckoutController.php) | 31 / S |
| [ ] | R1016 | `GET\|HEAD /club-cockpit` | `auth.club-cockpit.index` | [ClubCockpitController@index](../app/Http/Controllers/ClubCockpitController.php) | 17, 24, 25 / L |
| [ ] | R1017 | `POST /club-external-members/{externalMember}/invite` | `auth.club-memberships.email-members.invite` | [ClubMembershipController@inviteEmailMember](../app/Http/Controllers/ClubMembershipController.php) | 18, 19, 20 / S |
| [ ] | R1018 | `GET\|HEAD /club-inventory` | `auth.club-inventory.index` | [ClubInventoryPageController@index](../app/Http/Controllers/ClubInventoryPageController.php) | 27 / L |
| [ ] | R1019 | `GET\|HEAD /club-member-invitations/token/{token}/accept` | `auth.club-member-invitations.accept` | [ClubMembershipController@acceptExternalInvitation](../app/Http/Controllers/ClubMembershipController.php) | 18, 19, 20 / L |
| [ ] | R1020 | `POST /club-membership-requests/{membershipRequest}/approve` | `auth.club-membership-requests.approve` | [ClubMembershipController@approveClubRequest](../app/Http/Controllers/ClubMembershipController.php) | 18, 19, 20 / S |
| [ ] | R1021 | `POST /club-membership-requests/{membershipRequest}/decline` | `auth.club-membership-requests.decline` | [ClubMembershipController@declineClubRequest](../app/Http/Controllers/ClubMembershipController.php) | 18, 19, 20 / S |
| [ ] | R1022 | `POST /club-membership-requests/{membershipRequest}/request-information` | `auth.club-membership-requests.request-information` | [ClubMembershipController@requestClubRequestInformation](../app/Http/Controllers/ClubMembershipController.php) | 18, 19, 20 / S |
| [ ] | R1023 | `POST /club-membership-requests/{membershipRequest}/respond` | `auth.club-membership-requests.respond` | [ClubMembershipController@respondToClubRequestInformation](../app/Http/Controllers/ClubMembershipController.php) | 18, 19, 20 / S |
| [ ] | R1024 | `POST /club-membership-requests/{membershipRequest}/waitlist` | `auth.club-membership-requests.waitlist` | [ClubMembershipController@waitlistClubRequest](../app/Http/Controllers/ClubMembershipController.php) | 18, 19, 20 / S |
| [ ] | R1025 | `GET\|HEAD /club-memberships` | `auth.club-memberships.index` | [ClubMembershipController@index](../app/Http/Controllers/ClubMembershipController.php) | 18, 19, 20 / L |
| [ ] | R1026 | `GET\|HEAD /club-memberships/import-template` | `auth.club-memberships.import-template` | [ClubMembershipController@downloadImportTemplate](../app/Http/Controllers/ClubMembershipController.php) | 18, 19, 20 / L |
| [ ] | R1027 | `GET\|HEAD /clubs` | `(ohne Name)` | [ClubController@index](../app/Http/Controllers/ClubController.php) | 17, 18, 34 / L |
| [ ] | R1028 | `POST /clubs` | `auth.clubs.store` | [ClubController@store](../app/Http/Controllers/ClubController.php) | 17, 18, 34 / S |
| [ ] | R1029 | `GET\|HEAD /clubs/{club}` | `auth.clubs.show` | [ClubController@show](../app/Http/Controllers/ClubController.php) | 17, 18, 34 / L |
| [ ] | R1030 | `PUT /clubs/{club}` | `auth.clubs.update` | [ClubController@update](../app/Http/Controllers/ClubController.php) | 17, 18, 34 / S |
| [ ] | R1031 | `DELETE /clubs/{club}` | `auth.clubs.destroy` | [ClubController@destroy](../app/Http/Controllers/ClubController.php) | 17, 18, 34 / D |
| [ ] | R1032 | `POST /clubs/{club}/club-external-members/{externalMember}/merge/{user}` | `auth.club-memberships.external-members.merge` | [ClubMembershipController@mergeExternalMember](../app/Http/Controllers/ClubMembershipController.php) | 17, 18, 34 / S |
| [ ] | R1033 | `GET\|HEAD /clubs/{club}/deletion` | `auth.clubs.deletion.show` | [ClubDeletionController@show](../app/Http/Controllers/ClubDeletionController.php) | 28 / L |
| [ ] | R1034 | `DELETE /clubs/{club}/deletion` | `auth.clubs.deletion.cancel` | [ClubDeletionController@destroy](../app/Http/Controllers/ClubDeletionController.php) | 28 / D |
| [ ] | R1035 | `POST /clubs/{club}/images` | `auth.clubs.images.update` | [ClubController@updateImages](../app/Http/Controllers/ClubController.php) | 17, 18, 34 / S |
| [ ] | R1036 | `POST /clubs/{club}/jobs` | `auth.clubs.jobs.store` | [OrganizationJobController@store](../app/Http/Controllers/OrganizationJobController.php) | 17, 18, 34 / S |
| [ ] | R1037 | `POST /clubs/{club}/member-timeline` | `auth.club-memberships.timeline.store` | [ClubMemberTimelineController@store](../app/Http/Controllers/ClubMemberTimelineController.php) | 18, 08, 22 / S |
| [ ] | R1038 | `DELETE /clubs/{club}/member-timeline/{timelineEntry}` | `auth.club-memberships.timeline.destroy` | [ClubMemberTimelineController@destroy](../app/Http/Controllers/ClubMemberTimelineController.php) | 18, 08, 22 / D |
| [ ] | R1039 | `GET\|HEAD /clubs/{club}/members/{child}/guardians` | `auth.clubs.members.guardians.index` | [ClubGuardianRelationshipController@index](../app/Http/Controllers/Api/V1/ClubGuardianRelationshipController.php) | 18, 08, 22 / L |
| [ ] | R1040 | `POST /clubs/{club}/members/{child}/guardians` | `auth.clubs.members.guardians.store` | [ClubGuardianRelationshipController@store](../app/Http/Controllers/Api/V1/ClubGuardianRelationshipController.php) | 18, 08, 22 / S |
| [ ] | R1041 | `POST /clubs/{club}/members/{child}/guardians/{relationship}/accept` | `auth.clubs.members.guardians.accept` | [ClubGuardianRelationshipController@accept](../app/Http/Controllers/Api/V1/ClubGuardianRelationshipController.php) | 18, 08, 22 / S |
| [ ] | R1042 | `POST /clubs/{club}/members/{child}/guardians/{relationship}/decline` | `auth.clubs.members.guardians.decline` | [ClubGuardianRelationshipController@decline](../app/Http/Controllers/Api/V1/ClubGuardianRelationshipController.php) | 18, 08, 22 / S |
| [ ] | R1043 | `POST /clubs/{club}/members/{child}/guardians/{relationship}/primary` | `auth.clubs.members.guardians.primary` | [ClubGuardianRelationshipController@primary](../app/Http/Controllers/Api/V1/ClubGuardianRelationshipController.php) | 18, 08, 22 / S |
| [ ] | R1044 | `POST /clubs/{club}/members/{child}/guardians/{relationship}/revoke` | `auth.clubs.members.guardians.revoke` | [ClubGuardianRelationshipController@revoke](../app/Http/Controllers/Api/V1/ClubGuardianRelationshipController.php) | 18, 08, 22 / S |
| [ ] | R1045 | `OPTIONS /clubs/{club}/members/{user}` | `auth.clubs.members.update.options` | `Closure` | 18, 08, 22 / X |
| [ ] | R1046 | `PUT /clubs/{club}/members/{user}` | `auth.clubs.members.update` | [ClubController@updateMember](../app/Http/Controllers/ClubController.php) | 18, 08, 22 / S |
| [ ] | R1047 | `GET\|HEAD /clubs/{club}/members/{user}/relationships` | `auth.clubs.members.relationships.index` | [ClubMemberRelationshipController@index](../app/Http/Controllers/Api/V1/ClubMemberRelationshipController.php) | 18, 08, 22 / L |
| [ ] | R1048 | `POST /clubs/{club}/members/{user}/relationships` | `auth.clubs.members.relationships.store` | [ClubMemberRelationshipController@store](../app/Http/Controllers/Api/V1/ClubMemberRelationshipController.php) | 18, 08, 22 / S |
| [ ] | R1049 | `POST /clubs/{club}/membership-change-requests` | `auth.club-membership-change-requests.store` | [ClubMembershipController@storeMembershipChangeRequest](../app/Http/Controllers/ClubMembershipController.php) | 18, 08, 22 / S |
| [ ] | R1050 | `POST /clubs/{club}/membership-pause-requests` | `auth.club-membership-pause-requests.store` | [ClubMembershipController@storePauseRequest](../app/Http/Controllers/ClubMembershipController.php) | 18, 08, 22 / S |
| [ ] | R1051 | `POST /clubs/{club}/membership-requests` | `auth.club-membership-requests.store` | [ClubMembershipController@storeMembershipRequest](../app/Http/Controllers/ClubMembershipController.php) | 18, 08, 22 / S |
| [ ] | R1052 | `DELETE /clubs/{club}/membership-requests` | `auth.club-membership-requests.destroy` | [ClubMembershipController@withdrawMembershipRequest](../app/Http/Controllers/ClubMembershipController.php) | 18, 08, 22 / D |
| [ ] | R1053 | `POST /clubs/{club}/membership-termination-requests` | `auth.club-membership-termination-requests.store` | [ClubMembershipController@storeTerminationRequest](../app/Http/Controllers/ClubMembershipController.php) | 18, 08, 22 / S |
| [ ] | R1054 | `POST /clubs/{club}/membership/bank-transactions/import` | `auth.club-memberships.bank-transactions.import` | [ClubMembershipController@importBankTransactions](../app/Http/Controllers/ClubMembershipController.php) | 21 / S |
| [ ] | R1055 | `POST /clubs/{club}/membership/contribution-rules` | `auth.club-memberships.contribution-rules.store` | [ClubMembershipController@storeContributionRule](../app/Http/Controllers/ClubMembershipController.php) | 19 / S |
| [ ] | R1056 | `PUT /clubs/{club}/membership/contribution-rules/{contributionRule}` | `auth.club-memberships.contribution-rules.update` | [ClubMembershipController@updateContributionRule](../app/Http/Controllers/ClubMembershipController.php) | 19 / S |
| [ ] | R1057 | `GET\|HEAD /clubs/{club}/membership/datev-export` | `auth.club-memberships.datev-export` | [ClubMembershipController@exportDatev](../app/Http/Controllers/ClubMembershipController.php) | 21 / L |
| [ ] | R1058 | `PUT /clubs/{club}/membership/datev-settings` | `auth.club-memberships.datev-settings.update` | [ClubMembershipController@updateDatevSettings](../app/Http/Controllers/ClubMembershipController.php) | 21 / S |
| [ ] | R1059 | `POST /clubs/{club}/membership/email-members` | `auth.club-memberships.email-members.store` | [ClubMembershipController@storeEmailMember](../app/Http/Controllers/ClubMembershipController.php) | 18, 08, 22 / S |
| [ ] | R1060 | `POST /clubs/{club}/membership/email-members/import` | `auth.club-memberships.email-members.import` | [ClubMembershipController@importEmailMembers](../app/Http/Controllers/ClubMembershipController.php) | 18, 08, 22 / S |
| [ ] | R1061 | `POST /clubs/{club}/membership/finance-entries` | `auth.club-memberships.finance-entries.store` | [ClubMembershipController@storeFinanceEntry](../app/Http/Controllers/ClubMembershipController.php) | 21 / S |
| [ ] | R1062 | `PUT /clubs/{club}/membership/finance-entries/{financeEntry}` | `auth.club-memberships.finance-entries.update` | [ClubMembershipController@updateFinanceEntry](../app/Http/Controllers/ClubMembershipController.php) | 21 / S |
| [ ] | R1063 | `POST /clubs/{club}/membership/leave` | `auth.club-memberships.leave` | [ClubMembershipController@leaveClub](../app/Http/Controllers/ClubMembershipController.php) | 18, 08, 22 / S |
| [ ] | R1064 | `POST /clubs/{club}/membership/removal-objection` | `auth.club-memberships.removal-objection` | [ClubMembershipController@objectToRemoval](../app/Http/Controllers/ClubMembershipController.php) | 18, 08, 22 / S |
| [ ] | R1065 | `GET\|HEAD /clubs/{club}/membership/sepa-export` | `auth.club-memberships.sepa-export` | [ClubMembershipController@exportSepaDebit](../app/Http/Controllers/ClubMembershipController.php) | 21 / L |
| [ ] | R1066 | `PUT /clubs/{club}/membership/sepa-settings` | `auth.club-memberships.sepa-settings.update` | [ClubMembershipController@updateSepaSettings](../app/Http/Controllers/ClubMembershipController.php) | 21 / S |
| [ ] | R1067 | `PUT /clubs/{club}/membership/settings` | `auth.club-memberships.settings.update` | [ClubMembershipController@updateMembershipSettings](../app/Http/Controllers/ClubMembershipController.php) | 19 / S |
| [ ] | R1068 | `POST /clubs/{club}/membership/types` | `auth.club-memberships.types.store` | [ClubMembershipController@storeMembershipType](../app/Http/Controllers/ClubMembershipController.php) | 19 / S |
| [ ] | R1069 | `PUT /clubs/{club}/membership/types/{membershipType}` | `auth.club-memberships.types.update` | [ClubMembershipController@updateMembershipType](../app/Http/Controllers/ClubMembershipController.php) | 19 / S |
| [ ] | R1070 | `PUT /clubs/{club}/membership/{user}` | `auth.club-memberships.members.update` | [ClubMembershipController@updateMember](../app/Http/Controllers/ClubMembershipController.php) | 18, 08, 22 / S |
| [ ] | R1071 | `DELETE /clubs/{club}/membership/{user}` | `auth.club-memberships.members.destroy` | [ClubMembershipController@removeMember](../app/Http/Controllers/ClubMembershipController.php) | 18, 08, 22 / D |
| [ ] | R1072 | `POST /clubs/{club}/membership/{user}/invoices` | `auth.club-memberships.invoices.store` | [ClubMembershipController@storeInvoice](../app/Http/Controllers/ClubMembershipController.php) | 20, 21 / S |
| [ ] | R1073 | `POST /clubs/{club}/membership/{user}/member-number` | `auth.club-memberships.members.member-number` | [ClubMembershipController@generateMemberNumber](../app/Http/Controllers/ClubMembershipController.php) | 18, 08, 22 / S |
| [ ] | R1074 | `POST /clubs/{club}/sponsors` | `auth.clubs.sponsors.store` | [ClubController@storeSponsor](../app/Http/Controllers/ClubController.php) | 17, 18, 34 / S |
| [ ] | R1075 | `PUT /clubs/{club}/sponsors/{sponsor}` | `auth.clubs.sponsors.update` | [ClubController@updateSponsor](../app/Http/Controllers/ClubController.php) | 17, 18, 34 / S |
| [ ] | R1076 | `DELETE /clubs/{club}/sponsors/{sponsor}` | `auth.clubs.sponsors.destroy` | [ClubController@destroySponsor](../app/Http/Controllers/ClubController.php) | 17, 18, 34 / D |
| [ ] | R1077 | `PUT /comments/{comment}` | `auth.comments.update` | [CommentController@update](../app/Http/Controllers/CommentController.php) | 04, 02 / S |
| [ ] | R1078 | `DELETE /comments/{comment}` | `auth.comments.destroy` | [CommentController@destroy](../app/Http/Controllers/CommentController.php) | 04, 02 / D |
| [ ] | R1079 | `GET\|HEAD /commerce` | `auth.commerce.index` | [CommerceCheckoutController@index](../app/Http/Controllers/CommerceCheckoutController.php) | 30, 29 / L |
| [ ] | R1080 | `POST /commerce/addons/{addon}` | `auth.commerce.addons.checkout` | [CommerceCheckoutController@storeAddon](../app/Http/Controllers/CommerceCheckoutController.php) | 30, 29 / S |
| [ ] | R1081 | `POST /commerce/campaigns` | `auth.commerce.campaigns.store` | [CommerceCheckoutController@storeOwnCampaign](../app/Http/Controllers/CommerceCheckoutController.php) | 30, 29 / S |
| [ ] | R1082 | `POST /commerce/campaigns/{campaign}` | `auth.commerce.campaigns.update` | [CommerceCheckoutController@updateOwnCampaign](../app/Http/Controllers/CommerceCheckoutController.php) | 30, 29 / S |
| [ ] | R1083 | `DELETE /commerce/campaigns/{campaign}` | `auth.commerce.campaigns.destroy` | [CommerceCheckoutController@destroyOwnCampaign](../app/Http/Controllers/CommerceCheckoutController.php) | 30, 29 / D |
| [ ] | R1084 | `POST /commerce/campaigns/{campaign}/groups` | `auth.commerce.campaigns.groups.store` | [CommerceCheckoutController@storeOwnCampaignGroup](../app/Http/Controllers/CommerceCheckoutController.php) | 30, 29 / S |
| [ ] | R1085 | `POST /commerce/campaigns/{campaign}/groups/{group}/creatives` | `auth.commerce.campaigns.groups.creatives.store` | [CommerceCheckoutController@storeOwnCampaignGroupCreatives](../app/Http/Controllers/CommerceCheckoutController.php) | 30, 29 / S |
| [ ] | R1086 | `PUT /commerce/campaigns/{campaign}/status` | `auth.commerce.campaigns.status.update` | [CommerceCheckoutController@updateOwnCampaignStatus](../app/Http/Controllers/CommerceCheckoutController.php) | 30, 29 / S |
| [ ] | R1087 | `POST /commerce/cart/checkout` | `auth.commerce.cart.checkout` | [CommerceCheckoutController@checkoutCart](../app/Http/Controllers/CommerceCheckoutController.php) | 30, 29 / S |
| [ ] | R1088 | `PUT /commerce/cart/items/{item}` | `auth.commerce.cart.items.update` | [CommerceCheckoutController@updateCartItem](../app/Http/Controllers/CommerceCheckoutController.php) | 30, 29 / S |
| [ ] | R1089 | `DELETE /commerce/cart/items/{item}` | `auth.commerce.cart.items.destroy` | [CommerceCheckoutController@removeCartItem](../app/Http/Controllers/CommerceCheckoutController.php) | 30, 29 / D |
| [ ] | R1090 | `POST /commerce/cart/items/{product}` | `auth.commerce.cart.items.store` | [CommerceCheckoutController@addCartItem](../app/Http/Controllers/CommerceCheckoutController.php) | 30, 29 / S |
| [ ] | R1091 | `GET\|HEAD /commerce/documents/{order}/{type}` | `commerce.documents.signed` | [CommerceCheckoutController@downloadSignedDocument](../app/Http/Controllers/CommerceCheckoutController.php) | 30, 29 / L+X |
| [ ] | R1092 | `POST /commerce/my-products/{product}` | `auth.commerce.my-products.update` | [CommerceCheckoutController@updateOwnProduct](../app/Http/Controllers/CommerceCheckoutController.php) | 30, 29 / S |
| [ ] | R1093 | `DELETE /commerce/my-products/{product}` | `auth.commerce.my-products.destroy` | [CommerceCheckoutController@destroyOwnProduct](../app/Http/Controllers/CommerceCheckoutController.php) | 30, 29 / D |
| [ ] | R1094 | `PUT /commerce/my-products/{product}/status` | `auth.commerce.my-products.status.update` | [CommerceCheckoutController@updateOwnProductStatus](../app/Http/Controllers/CommerceCheckoutController.php) | 30, 29 / S |
| [ ] | R1095 | `POST /commerce/orders/{order}/cancel` | `auth.commerce.orders.cancel` | [CommerceCheckoutController@cancelOrder](../app/Http/Controllers/CommerceCheckoutController.php) | 30, 29 / S |
| [ ] | R1096 | `GET\|HEAD /commerce/orders/{order}/credit-note` | `auth.commerce.orders.credit-note` | [CommerceCheckoutController@downloadCreditNote](../app/Http/Controllers/CommerceCheckoutController.php) | 30, 29 / L |
| [ ] | R1097 | `GET\|HEAD /commerce/orders/{order}/invoice` | `auth.commerce.orders.invoice` | [CommerceCheckoutController@downloadInvoice](../app/Http/Controllers/CommerceCheckoutController.php) | 30, 29 / L |
| [ ] | R1098 | `POST /commerce/orders/{order}/issue` | `auth.commerce.orders.issue` | [CommerceCheckoutController@reportOrderIssue](../app/Http/Controllers/CommerceCheckoutController.php) | 30, 29 / S |
| [ ] | R1099 | `POST /commerce/orders/{order}/returns` | `auth.commerce.orders.returns.store` | [CommerceCheckoutController@requestReturn](../app/Http/Controllers/CommerceCheckoutController.php) | 30, 29 / S |
| [ ] | R1100 | `POST /commerce/payout-profile` | `auth.commerce.payout-profile.store` | [CommerceCheckoutController@storePayoutProfile](../app/Http/Controllers/CommerceCheckoutController.php) | 30, 29 / S |
| [ ] | R1101 | `POST /commerce/payouts/request` | `auth.commerce.payouts.request` | [CommerceCheckoutController@requestPayout](../app/Http/Controllers/CommerceCheckoutController.php) | 30, 29 / S |
| [ ] | R1102 | `POST /commerce/products` | `auth.commerce.products.store` | [CommerceCheckoutController@storeOwnProduct](../app/Http/Controllers/CommerceCheckoutController.php) | 30, 29 / S |
| [ ] | R1103 | `POST /commerce/products/import` | `auth.commerce.products.import` | [CommerceCheckoutController@importOwnProducts](../app/Http/Controllers/CommerceCheckoutController.php) | 30, 29 / S |
| [ ] | R1104 | `GET\|HEAD /commerce/products/import-template` | `auth.commerce.products.import-template` | [CommerceCheckoutController@downloadProductImportTemplate](../app/Http/Controllers/CommerceCheckoutController.php) | 30, 29 / L |
| [ ] | R1105 | `GET\|HEAD /commerce/products/{product}` | `auth.commerce.products.show` | [CommerceCheckoutController@showProduct](../app/Http/Controllers/CommerceCheckoutController.php) | 30, 29 / L |
| [ ] | R1106 | `POST /commerce/products/{product}` | `auth.commerce.products.checkout` | [CommerceCheckoutController@storeProduct](../app/Http/Controllers/CommerceCheckoutController.php) | 30, 29 / S |
| [ ] | R1107 | `POST /commerce/provider-locations` | `auth.commerce.provider-locations.store` | [CommerceCheckoutController@storeProviderLocation](../app/Http/Controllers/CommerceCheckoutController.php) | 30, 29 / S |
| [ ] | R1108 | `PUT /commerce/provider-locations/{location}` | `auth.commerce.provider-locations.update` | [CommerceCheckoutController@updateProviderLocation](../app/Http/Controllers/CommerceCheckoutController.php) | 30, 29 / S |
| [ ] | R1109 | `DELETE /commerce/provider-locations/{location}` | `auth.commerce.provider-locations.destroy` | [CommerceCheckoutController@destroyProviderLocation](../app/Http/Controllers/CommerceCheckoutController.php) | 30, 29 / D |
| [ ] | R1110 | `POST /commerce/provider-profile` | `auth.commerce.provider-profile.store` | [CommerceCheckoutController@storeProviderProfile](../app/Http/Controllers/CommerceCheckoutController.php) | 30, 29 / S |
| [ ] | R1111 | `POST /commerce/seller-application` | `auth.commerce.seller-application.store` | [CommerceCheckoutController@storeSellerApplication](../app/Http/Controllers/CommerceCheckoutController.php) | 30, 29 / S |
| [ ] | R1112 | `POST /commerce/website-requests` | `auth.commerce.website-requests.store` | [CommerceCheckoutController@storeWebsiteRequest](../app/Http/Controllers/CommerceCheckoutController.php) | 30, 29 / S |
| [ ] | R1113 | `GET\|HEAD /community-richtlinien` | `legal.community` | [LegalPageController@community](../app/Http/Controllers/LegalPageController.php) | 40 / L |
| [ ] | R1114 | `POST /conversation-invitations/{invitation}/accept` | `auth.conversation-invitations.accept` | [ConversationController@acceptInvitation](../app/Http/Controllers/ConversationController.php) | 05 / S |
| [ ] | R1115 | `POST /conversation-invitations/{invitation}/decline` | `auth.conversation-invitations.decline` | [ConversationController@declineInvitation](../app/Http/Controllers/ConversationController.php) | 05 / S |
| [ ] | R1116 | `GET\|HEAD /conversations` | `auth.conversations.index` | [ConversationController@index](../app/Http/Controllers/ConversationController.php) | 05 / L |
| [ ] | R1117 | `POST /conversations` | `auth.conversations.store` | [ConversationController@store](../app/Http/Controllers/ConversationController.php) | 05 / S |
| [ ] | R1118 | `GET\|HEAD /conversations/{conversation}` | `auth.conversations.show` | [ConversationController@show](../app/Http/Controllers/ConversationController.php) | 05 / L |
| [ ] | R1119 | `PUT /conversations/{conversation}` | `auth.conversations.update` | [ConversationController@update](../app/Http/Controllers/ConversationController.php) | 05 / S |
| [ ] | R1120 | `DELETE /conversations/{conversation}/leave` | `auth.conversations.leave` | [ConversationController@leave](../app/Http/Controllers/ConversationController.php) | 05 / D |
| [ ] | R1121 | `POST /conversations/{conversation}/members` | `auth.conversations.members.store` | [ConversationController@addMembers](../app/Http/Controllers/ConversationController.php) | 05 / S |
| [ ] | R1122 | `DELETE /conversations/{conversation}/members/{user}` | `auth.conversations.members.destroy` | [ConversationController@removeMember](../app/Http/Controllers/ConversationController.php) | 05 / D |
| [ ] | R1123 | `PUT /conversations/{conversation}/mute` | `auth.conversations.mute` | [ConversationController@mute](../app/Http/Controllers/ConversationController.php) | 05 / S |
| [ ] | R1124 | `PUT /conversations/{conversation}/owner` | `auth.conversations.owner.update` | [ConversationController@transferOwner](../app/Http/Controllers/ConversationController.php) | 05 / S |
| [ ] | R1125 | `POST /conversations/{conversation}/typing` | `auth.conversations.typing` | [ConversationController@typing](../app/Http/Controllers/ConversationController.php) | 05 / S |
| [ ] | R1126 | `GET\|HEAD /cookies` | `legal.cookies` | [LegalPageController@cookies](../app/Http/Controllers/LegalPageController.php) | 40 / L |
| [ ] | R1127 | `GET\|HEAD /dashboard` | `auth.dashboard` | [DashboardController@index](../app/Http/Controllers/DashboardController.php) | 03, 36 / L |
| [ ] | R1128 | `GET\|HEAD /dashboard/maturity` | `auth.maturity.index` | [DashboardController@maturity](../app/Http/Controllers/DashboardController.php) | 03, 36 / L |
| [ ] | R1129 | `PATCH /dashboard/preferences` | `auth.dashboard.preferences.update` | [DashboardController@updatePreferences](../app/Http/Controllers/DashboardController.php) | 03, 36 / S |
| [ ] | R1130 | `GET\|HEAD /daten-loeschen` | `legal.data-erasure` | [LegalPageController@dataErasure](../app/Http/Controllers/LegalPageController.php) | 40 / L |
| [ ] | R1131 | `GET\|HEAD /datenschutz` | `policy.show` | [LegalPageController@privacy](../app/Http/Controllers/LegalPageController.php) | 40 / L |
| [ ] | R1132 | `GET\|HEAD /documents/club-sepa-fee-recharge-credits/{credit}` | `club-sepa-fee-recharge-credits.documents.signed` | [ClubSepaFeeRechargeCreditDocumentController](../app/Http/Controllers/ClubSepaFeeRechargeCreditDocumentController.php) | 21 / L+X |
| [ ] | R1133 | `GET\|HEAD /e-learning` | `guest.e-learning` | [PublicLearningController@index](../app/Http/Controllers/PublicLearningController.php) | 14, 16 / L |
| [ ] | R1134 | `GET\|HEAD /e-learning/certificates/{code}` | `guest.learning.certificates.verify` | [PublicLearningController@verifyCertificate](../app/Http/Controllers/PublicLearningController.php) | 14, 16 / L |
| [ ] | R1135 | `GET\|HEAD /e-learning/courses/{course}` | `guest.learning.courses.show` | [PublicLearningController@show](../app/Http/Controllers/PublicLearningController.php) | 14, 16 / L |
| [ ] | R1136 | `GET\|HEAD /eltern-login` | `guardian-access.create` | [GuardianAccessController@create](../app/Http/Controllers/GuardianAccessController.php) | 32 / L |
| [ ] | R1137 | `POST /eltern-login` | `guardian-access.store` | [GuardianAccessController@store](../app/Http/Controllers/GuardianAccessController.php) | 32 / S |
| [ ] | R1138 | `GET\|HEAD /eltern-login/code` | `guardian-access.verify` | [GuardianAccessController@verify](../app/Http/Controllers/GuardianAccessController.php) | 32 / L |
| [ ] | R1139 | `POST /eltern-login/code` | `guardian-access.confirm` | [GuardianAccessController@confirm](../app/Http/Controllers/GuardianAccessController.php) | 32 / S |
| [ ] | R1140 | `GET\|HEAD /eltern/kinder` | `guardian-access.children` | [GuardianAccessController@children](../app/Http/Controllers/GuardianAccessController.php) | 32 / L |
| [ ] | R1141 | `PUT /eltern/kinder/{child}/widerrufen` | `guardian-access.children.revoke` | [GuardianAccessController@revoke](../app/Http/Controllers/GuardianAccessController.php) | 32 / S |
| [ ] | R1142 | `PUT /eltern/kinder/{child}/zustimmen` | `guardian-access.children.approve` | [GuardianAccessController@approve](../app/Http/Controllers/GuardianAccessController.php) | 32 / S |
| [ ] | R1143 | `GET\|HEAD /eltern/konto-erstellen` | `guardian-access.account.create` | [GuardianAccessController@createAccount](../app/Http/Controllers/GuardianAccessController.php) | 32 / L |
| [ ] | R1144 | `POST /eltern/konto-erstellen` | `guardian-access.account.store` | [GuardianAccessController@storeAccount](../app/Http/Controllers/GuardianAccessController.php) | 32 / S |
| [ ] | R1145 | `POST /eltern/logout` | `guardian-access.destroy` | [GuardianAccessController@destroy](../app/Http/Controllers/GuardianAccessController.php) | 32 / S |
| [ ] | R1146 | `POST /email/verification-notification` | `verification.send` | `Laravel\Fortify\Http\Controllers\EmailVerificationNotificationController@store` | 01 / S |
| [ ] | R1147 | `GET\|HEAD /email/verify` | `verification.notice` | `Laravel\Fortify\Http\Controllers\EmailVerificationPromptController@__invoke` | 01 / L |
| [ ] | R1148 | `GET\|HEAD /email/verify/{id}/{hash}` | `verification.verify` | `Laravel\Fortify\Http\Controllers\VerifyEmailController@__invoke` | 01 / L |
| [ ] | R1149 | `GET\|HEAD /events` | `auth.events.index` | [EventController@index](../app/Http/Controllers/EventController.php) | 25 / L |
| [ ] | R1150 | `POST /events` | `auth.events.store` | [EventController@store](../app/Http/Controllers/EventController.php) | 25 / S |
| [ ] | R1151 | `PUT /events/default-filters` | `auth.events.default-filters.update` | [EventController@saveDefaultFilters](../app/Http/Controllers/EventController.php) | 25 / S |
| [ ] | R1152 | `GET\|HEAD /events/{event}` | `auth.events.show` | [EventController@show](../app/Http/Controllers/EventController.php) | 25 / L |
| [ ] | R1153 | `PUT /events/{event}` | `auth.events.update` | [EventController@update](../app/Http/Controllers/EventController.php) | 25 / S |
| [ ] | R1154 | `DELETE /events/{event}` | `auth.events.destroy` | [EventController@destroy](../app/Http/Controllers/EventController.php) | 25 / D |
| [ ] | R1155 | `PUT /events/{event}/attendance` | `auth.events.attendance.update` | [EventController@recordAttendance](../app/Http/Controllers/EventController.php) | 25 / S |
| [ ] | R1156 | `POST /events/{event}/cancel` | `auth.events.cancel` | [EventController@cancel](../app/Http/Controllers/EventController.php) | 25 / S |
| [ ] | R1157 | `GET\|HEAD /events/{event}/chat` | `auth.events.chat` | [EventController@chat](../app/Http/Controllers/EventController.php) | 25 / L |
| [ ] | R1158 | `POST /events/{event}/comments` | `auth.events.comments.store` | [EventController@comment](../app/Http/Controllers/EventController.php) | 25 / S |
| [ ] | R1159 | `POST /events/{event}/join` | `auth.events.join` | [EventController@join](../app/Http/Controllers/EventController.php) | 25 / S |
| [ ] | R1160 | `POST /events/{event}/leave` | `auth.events.leave` | [EventController@leave](../app/Http/Controllers/EventController.php) | 25 / S |
| [ ] | R1161 | `GET\|HEAD /feed` | `auth.feed.index` | [PostController@index](../app/Http/Controllers/PostController.php) | 04, 02 / L |
| [ ] | R1162 | `GET\|HEAD /files` | `auth.files.index` | [FileController@index](../app/Http/Controllers/FileController.php) | 07 / L |
| [ ] | R1163 | `POST /files` | `auth.files.store` | [FileController@store](../app/Http/Controllers/FileController.php) | 07 / S |
| [ ] | R1164 | `PUT /files/{file}` | `auth.files.update` | [FileController@update](../app/Http/Controllers/FileController.php) | 07 / S |
| [ ] | R1165 | `DELETE /files/{file}` | `auth.files.destroy` | [FileController@destroy](../app/Http/Controllers/FileController.php) | 07 / D |
| [ ] | R1166 | `GET\|HEAD /files/{file}/download` | `auth.files.download` | [FileController@download](../app/Http/Controllers/FileController.php) | 07 / L |
| [ ] | R1167 | `GET\|HEAD /files/{file}/preview` | `auth.files.preview` | [FileController@preview](../app/Http/Controllers/FileController.php) | 07 / L |
| [ ] | R1168 | `POST /files/{file}/share` | `auth.files.share` | [FileController@share](../app/Http/Controllers/FileController.php) | 07 / S |
| [ ] | R1169 | `POST /folders` | `auth.folders.store` | [FolderController@store](../app/Http/Controllers/FolderController.php) | 07 / S |
| [ ] | R1170 | `PUT /folders/{folder}` | `auth.folders.update` | [FolderController@update](../app/Http/Controllers/FolderController.php) | 07 / S |
| [ ] | R1171 | `DELETE /folders/{folder}` | `auth.folders.destroy` | [FolderController@destroy](../app/Http/Controllers/FolderController.php) | 07 / D |
| [ ] | R1172 | `POST /folders/{folder}/share` | `auth.folders.share` | [FolderController@share](../app/Http/Controllers/FolderController.php) | 07 / S |
| [ ] | R1173 | `GET\|HEAD /forgot-password` | `password.request` | `Laravel\Fortify\Http\Controllers\PasswordResetLinkController@create` | 01 / L |
| [ ] | R1174 | `POST /forgot-password` | `password.email` | `Laravel\Fortify\Http\Controllers\PasswordResetLinkController@store` | 01 / S |
| [ ] | R1175 | `GET\|HEAD /friends` | `auth.friends.index` | [FriendController@index](../app/Http/Controllers/FriendController.php) | 04, 02 / L |
| [ ] | R1176 | `POST /friends/invitations` | `auth.friends.invitations.store` | [FriendController@store](../app/Http/Controllers/FriendController.php) | 04, 02 / S |
| [ ] | R1177 | `GET\|HEAD /friends/invitations/token/{token}/accept` | `auth.friends.invitations.accept-by-token` | [FriendController@acceptByToken](../app/Http/Controllers/FriendController.php) | 04, 02 / L |
| [ ] | R1178 | `DELETE /friends/invitations/{invitation}` | `auth.friends.invitations.withdraw` | [FriendController@withdraw](../app/Http/Controllers/FriendController.php) | 04, 02 / D |
| [ ] | R1179 | `POST /friends/invitations/{invitation}/accept` | `auth.friends.invitations.accept` | [FriendController@accept](../app/Http/Controllers/FriendController.php) | 04, 02 / S |
| [ ] | R1180 | `POST /friends/invitations/{invitation}/decline` | `auth.friends.invitations.decline` | [FriendController@decline](../app/Http/Controllers/FriendController.php) | 04, 02 / S |
| [ ] | R1181 | `DELETE /friends/{user}` | `auth.friends.destroy` | [FriendController@destroy](../app/Http/Controllers/FriendController.php) | 04, 02 / D |
| [ ] | R1182 | `GET\|HEAD /gamification` | `guest.gamification` | `Closure` | 13, 36 / L |
| [ ] | R1183 | `GET\|HEAD /guardian-consent/pending` | `guardian-consent.pending` | [GuardianConsentController@pending](../app/Http/Controllers/GuardianConsentController.php) | 32 / L |
| [ ] | R1184 | `POST /guardian-consent/resend` | `guardian-consent.resend` | [GuardianConsentController@resend](../app/Http/Controllers/GuardianConsentController.php) | 32 / S |
| [ ] | R1185 | `GET\|HEAD /guardian-consent/{token}` | `guardian-consent.show` | [GuardianConsentController@show](../app/Http/Controllers/GuardianConsentController.php) | 32 / L |
| [ ] | R1186 | `POST /guardian-consent/{token}` | `guardian-consent.approve` | [GuardianConsentController@approve](../app/Http/Controllers/GuardianConsentController.php) | 32 / S |
| [ ] | R1187 | `DELETE /guardian-consent/{token}` | `guardian-consent.reject` | [GuardianConsentController@reject](../app/Http/Controllers/GuardianConsentController.php) | 32 / D |
| [ ] | R1188 | `GET\|HEAD /guardian-consent/{token}/approve` | `guardian-consent.approve-direct` | [GuardianConsentController@approveDirect](../app/Http/Controllers/GuardianConsentController.php) | 32 / L |
| [ ] | R1189 | `GET\|HEAD /guardian-consent/{token}/reject` | `guardian-consent.reject-direct` | [GuardianConsentController@rejectDirect](../app/Http/Controllers/GuardianConsentController.php) | 32 / L |
| [ ] | R1190 | `GET\|HEAD /home` | `auth.home` | [RoleHomeController](../app/Http/Controllers/RoleHomeController.php) | 03, 36 / L |
| [ ] | R1191 | `GET\|HEAD /impressum` | `legal.imprint` | [LegalPageController@imprint](../app/Http/Controllers/LegalPageController.php) | 40 / L |
| [ ] | R1192 | `GET\|HEAD /jobs` | `guest.jobs` | [OrganizationJobController@publicIndex](../app/Http/Controllers/OrganizationJobController.php) | 12 / L |
| [ ] | R1193 | `POST /jobs/{organizationJob}/interest` | `guest.jobs.interest` | [OrganizationJobController@submitInterest](../app/Http/Controllers/OrganizationJobController.php) | 12 / S |
| [ ] | R1194 | `GET\|HEAD /jugendschutz` | `legal.minors` | [LegalPageController@minors](../app/Http/Controllers/LegalPageController.php) | 40 / L |
| [ ] | R1195 | `GET\|HEAD /kontakt-und-melden` | `legal.reporting` | [LegalPageController@reporting](../app/Http/Controllers/LegalPageController.php) | 40 / L |
| [ ] | R1196 | `GET\|HEAD /konto-loeschen` | `legal.account-deletion` | [LegalPageController@accountDeletion](../app/Http/Controllers/LegalPageController.php) | 40 / L |
| [ ] | R1197 | `GET\|HEAD /learning/certificates/{certificate}` | `auth.learning.certificates.show` | [PublicLearningController@downloadCertificate](../app/Http/Controllers/PublicLearningController.php) | 14, 16 / L |
| [ ] | R1198 | `POST /learning/courses/{course}/assignments/{assignment}/submissions` | `auth.learning.assignments.submissions.store` | [PublicLearningController@submitAssignment](../app/Http/Controllers/PublicLearningController.php) | 14, 16 / S |
| [ ] | R1199 | `POST /learning/courses/{course}/enroll` | `auth.learning.courses.enroll` | [PublicLearningController@enroll](../app/Http/Controllers/PublicLearningController.php) | 14, 16 / S |
| [ ] | R1200 | `POST /learning/courses/{course}/lessons/{lesson}/comments` | `auth.learning.lessons.comments.store` | [PublicLearningController@storeComment](../app/Http/Controllers/PublicLearningController.php) | 14, 16 / S |
| [ ] | R1201 | `PUT /learning/courses/{course}/lessons/{lesson}/complete` | `auth.learning.lessons.complete` | [PublicLearningController@completeLesson](../app/Http/Controllers/PublicLearningController.php) | 14, 16 / S |
| [ ] | R1202 | `POST /learning/courses/{course}/lessons/{lesson}/notes` | `auth.learning.lessons.notes.store` | [PublicLearningController@storeNote](../app/Http/Controllers/PublicLearningController.php) | 14, 16 / S |
| [ ] | R1203 | `PUT /learning/courses/{course}/lessons/{lesson}/progress` | `auth.learning.lessons.progress.update` | [PublicLearningController@trackLessonProgress](../app/Http/Controllers/PublicLearningController.php) | 14, 16 / S |
| [ ] | R1204 | `GET\|HEAD /learning/courses/{course}/lessons/{lesson}/video` | `auth.learning.lessons.video` | [PublicLearningController@streamLessonVideo](../app/Http/Controllers/PublicLearningController.php) | 14, 16 / L |
| [ ] | R1205 | `POST /learning/courses/{course}/quizzes/{quiz}/attempts` | `auth.learning.quizzes.attempts.store` | [PublicLearningController@submitQuiz](../app/Http/Controllers/PublicLearningController.php) | 14, 16 / S |
| [ ] | R1206 | `POST /learning/courses/{course}/reviews` | `auth.learning.reviews.store` | [PublicLearningController@storeReview](../app/Http/Controllers/PublicLearningController.php) | 14, 16 / S |
| [ ] | R1207 | `GET\|HEAD /learning/my-courses` | `auth.learning.my-courses.index` | [PublicLearningController@myCourses](../app/Http/Controllers/PublicLearningController.php) | 14, 16 / L |
| [ ] | R1208 | `GET\|HEAD /learning/studio` | `auth.learning.studio.index` | [LearningStudioController@index](../app/Http/Controllers/LearningStudioController.php) | 16 / L |
| [ ] | R1209 | `POST /learning/studio/courses` | `auth.learning.studio.courses.store` | [LearningStudioController@storeCourse](../app/Http/Controllers/LearningStudioController.php) | 16 / S |
| [ ] | R1210 | `PUT /learning/studio/courses/{course}` | `auth.learning.studio.courses.update` | [LearningStudioController@updateCourse](../app/Http/Controllers/LearningStudioController.php) | 16 / S |
| [ ] | R1211 | `PUT /learning/studio/courses/{course}/assignment-submissions/{submission}` | `auth.learning.studio.assignment-submissions.update` | [LearningStudioController@gradeAssignment](../app/Http/Controllers/LearningStudioController.php) | 16 / S |
| [ ] | R1212 | `POST /learning/studio/courses/{course}/assignments` | `auth.learning.studio.assignments.store` | [LearningStudioController@storeAssignment](../app/Http/Controllers/LearningStudioController.php) | 16 / S |
| [ ] | R1213 | `PUT /learning/studio/courses/{course}/comments/{comment}` | `auth.learning.studio.comments.update` | [LearningStudioController@resolveComment](../app/Http/Controllers/LearningStudioController.php) | 16 / S |
| [ ] | R1214 | `POST /learning/studio/courses/{course}/comments/{comment}/replies` | `auth.learning.studio.comments.replies.store` | [LearningStudioController@replyComment](../app/Http/Controllers/LearningStudioController.php) | 16 / S |
| [ ] | R1215 | `POST /learning/studio/courses/{course}/coupons` | `auth.learning.studio.coupons.store` | [LearningStudioController@storeCoupon](../app/Http/Controllers/LearningStudioController.php) | 16 / S |
| [ ] | R1216 | `POST /learning/studio/courses/{course}/enrollments` | `auth.learning.studio.enrollments.store` | [LearningStudioController@grantEnrollment](../app/Http/Controllers/LearningStudioController.php) | 16 / S |
| [ ] | R1217 | `GET\|HEAD /learning/studio/courses/{course}/enrollments/{enrollment}/participation-confirmation` | `auth.learning.studio.enrollments.participation-confirmation` | [LearningStudioController@participationConfirmation](../app/Http/Controllers/LearningStudioController.php) | 16 / L |
| [ ] | R1218 | `PUT /learning/studio/courses/{course}/enrollments/{enrollment}/revoke` | `auth.learning.studio.enrollments.revoke` | [LearningStudioController@revokeEnrollment](../app/Http/Controllers/LearningStudioController.php) | 16 / S |
| [ ] | R1219 | `POST /learning/studio/courses/{course}/lessons` | `auth.learning.studio.lessons.store` | [LearningStudioController@storeLesson](../app/Http/Controllers/LearningStudioController.php) | 16 / S |
| [ ] | R1220 | `PUT /learning/studio/courses/{course}/lessons/reorder` | `auth.learning.studio.lessons.reorder` | [LearningStudioController@reorderLessons](../app/Http/Controllers/LearningStudioController.php) | 16 / S |
| [ ] | R1221 | `PUT /learning/studio/courses/{course}/lessons/{lesson}` | `auth.learning.studio.lessons.update` | [LearningStudioController@updateLesson](../app/Http/Controllers/LearningStudioController.php) | 16 / S |
| [ ] | R1222 | `DELETE /learning/studio/courses/{course}/lessons/{lesson}` | `auth.learning.studio.lessons.destroy` | [LearningStudioController@destroyLesson](../app/Http/Controllers/LearningStudioController.php) | 16 / D |
| [ ] | R1223 | `GET\|HEAD /learning/studio/courses/{course}/offer-evaluation` | `auth.learning.studio.courses.offer-evaluation` | [LearningStudioController@offerEvaluation](../app/Http/Controllers/LearningStudioController.php) | 16 / L |
| [ ] | R1224 | `GET\|HEAD /learning/studio/courses/{course}/offer-evaluation.csv` | `auth.learning.studio.courses.offer-evaluation.export` | [LearningStudioController@exportOfferEvaluation](../app/Http/Controllers/LearningStudioController.php) | 16 / L |
| [ ] | R1225 | `POST /learning/studio/courses/{course}/quizzes` | `auth.learning.studio.quizzes.store` | [LearningStudioController@storeQuiz](../app/Http/Controllers/LearningStudioController.php) | 16 / S |
| [ ] | R1226 | `DELETE /learning/studio/courses/{course}/quizzes/{quiz}` | `auth.learning.studio.quizzes.destroy` | [LearningStudioController@destroyQuiz](../app/Http/Controllers/LearningStudioController.php) | 16 / D |
| [ ] | R1227 | `GET\|HEAD /learning/studio/courses/{course}/report.csv` | `auth.learning.studio.courses.report` | [LearningStudioController@exportReport](../app/Http/Controllers/LearningStudioController.php) | 16 / L |
| [ ] | R1228 | `POST /learning/studio/courses/{course}/sections` | `auth.learning.studio.sections.store` | [LearningStudioController@storeSection](../app/Http/Controllers/LearningStudioController.php) | 16 / S |
| [ ] | R1229 | `POST /learning/studio/courses/{course}/uploads` | `auth.learning.studio.uploads.store` | [LearningStudioController@uploadAsset](../app/Http/Controllers/LearningStudioController.php) | 16 / S |
| [ ] | R1230 | `GET\|HEAD /login` | `login` | `Laravel\Fortify\Http\Controllers\AuthenticatedSessionController@create` | 01 / L |
| [ ] | R1231 | `POST /login` | `login.store` | `Laravel\Fortify\Http\Controllers\AuthenticatedSessionController@store` | 01 / S |
| [ ] | R1232 | `POST /logout` | `logout` | `Laravel\Fortify\Http\Controllers\AuthenticatedSessionController@destroy` | 01 / S |
| [ ] | R1233 | `GET\|HEAD /marketplace` | `guest.marketplace` | [PublicMarketplaceController@index](../app/Http/Controllers/PublicMarketplaceController.php) | 30, 29 / L |
| [ ] | R1234 | `POST /marketplace/orders/{order}/{token}/returns` | `commerce-checkout.guest.returns.store` | [CommerceCheckoutController@guestReturn](../app/Http/Controllers/CommerceCheckoutController.php) | 30, 29 / S |
| [ ] | R1235 | `GET\|HEAD /marketplace/products/{product}` | `guest.marketplace.products.show` | [PublicMarketplaceController@show](../app/Http/Controllers/PublicMarketplaceController.php) | 30, 29 / L |
| [ ] | R1236 | `POST /marketplace/products/{product}/checkout` | `guest.marketplace.products.checkout` | [PublicMarketplaceController@checkout](../app/Http/Controllers/PublicMarketplaceController.php) | 30, 29 / S |
| [ ] | R1237 | `GET\|HEAD /marketplace/providers/{type}/{id}` | `guest.marketplace.providers.show` | [PublicMarketplaceController@provider](../app/Http/Controllers/PublicMarketplaceController.php) | 30, 29 / L |
| [ ] | R1238 | `POST /membership-bank-transactions/{bankTransaction}/confirm` | `auth.club-memberships.bank-transactions.confirm` | [ClubMembershipController@confirmBankTransaction](../app/Http/Controllers/ClubMembershipController.php) | 21 / S |
| [ ] | R1239 | `PUT /membership-invoices/{invoice}` | `auth.club-memberships.invoices.update` | [ClubMembershipController@updateInvoiceStatus](../app/Http/Controllers/ClubMembershipController.php) | 20 / S |
| [ ] | R1240 | `POST /membership-invoices/{invoice}/payments` | `auth.club-memberships.invoices.payments.store` | [ClubMembershipController@recordPayment](../app/Http/Controllers/ClubMembershipController.php) | 20 / S |
| [ ] | R1241 | `POST /membership-invoices/{invoice}/reminder` | `auth.club-memberships.invoices.reminder` | [ClubMembershipController@sendReminder](../app/Http/Controllers/ClubMembershipController.php) | 20 / S |
| [ ] | R1242 | `GET\|HEAD /messages` | `auth.messages.index` | `Closure` | 05 / L |
| [ ] | R1243 | `POST /messages` | `auth.messages.store` | [MessageController@store](../app/Http/Controllers/MessageController.php) | 05 / S |
| [ ] | R1244 | `POST /messages/read` | `auth.messages.read` | [MessageController@markAsRead](../app/Http/Controllers/MessageController.php) | 05 / S |
| [ ] | R1245 | `DELETE /messages/{message}` | `auth.messages.destroy` | [MessageController@destroy](../app/Http/Controllers/MessageController.php) | 05 / D |
| [ ] | R1246 | `DELETE /messages/{message}/hide` | `auth.messages.hide` | [MessageController@hideForMe](../app/Http/Controllers/MessageController.php) | 05 / D |
| [ ] | R1247 | `POST /messages/{message}/reactions` | `auth.messages.reactions.store` | [MessageController@react](../app/Http/Controllers/MessageController.php) | 05 / S |
| [ ] | R1248 | `GET\|HEAD /notifications` | `auth.notifications.index` | [NotificationController@index](../app/Http/Controllers/NotificationController.php) | 06 / L |
| [ ] | R1249 | `POST /notifications/read-all` | `auth.notifications.read-all` | [NotificationController@markAllAsRead](../app/Http/Controllers/NotificationController.php) | 06 / S |
| [ ] | R1250 | `DELETE /notifications/{notification}` | `auth.notifications.destroy` | [NotificationController@destroy](../app/Http/Controllers/NotificationController.php) | 06 / D |
| [ ] | R1251 | `POST /notifications/{notification}/read` | `auth.notifications.read` | [NotificationController@markAsRead](../app/Http/Controllers/NotificationController.php) | 06 / S |
| [ ] | R1252 | `POST /notifications/{notification}/unread` | `auth.notifications.unread` | [NotificationController@markAsUnread](../app/Http/Controllers/NotificationController.php) | 06 / S |
| [ ] | R1253 | `GET\|HEAD /nutrition` | `auth.nutrition.index` | [NutritionController@index](../app/Http/Controllers/NutritionController.php) | 10 / L |
| [ ] | R1254 | `POST /nutrition/ai/meal-image` | `auth.nutrition.ai.meal-image` | [NutritionController@analyzeMealImage](../app/Http/Controllers/NutritionController.php) | 10 / S |
| [ ] | R1255 | `GET\|HEAD /nutrition/foods/barcode` | `auth.nutrition.foods.barcode` | [NutritionController@lookupBarcode](../app/Http/Controllers/NutritionController.php) | 10 / L |
| [ ] | R1256 | `GET\|HEAD /nutrition/foods/search` | `auth.nutrition.foods.search` | [NutritionController@searchFoods](../app/Http/Controllers/NutritionController.php) | 10 / L |
| [ ] | R1257 | `PATCH /nutrition/goal` | `auth.nutrition.goal.update` | [NutritionController@updateGoal](../app/Http/Controllers/NutritionController.php) | 10 / S |
| [ ] | R1258 | `POST /nutrition/meals` | `auth.nutrition.meals.store` | [NutritionController@storeMeal](../app/Http/Controllers/NutritionController.php) | 10 / S |
| [ ] | R1259 | `PATCH /nutrition/meals/{nutritionMeal}` | `auth.nutrition.meals.update` | [NutritionController@updateMeal](../app/Http/Controllers/NutritionController.php) | 10 / S |
| [ ] | R1260 | `DELETE /nutrition/meals/{nutritionMeal}` | `auth.nutrition.meals.destroy` | [NutritionController@destroyMeal](../app/Http/Controllers/NutritionController.php) | 10 / D |
| [ ] | R1261 | `POST /nutrition/water` | `auth.nutrition.water.store` | [NutritionController@storeWater](../app/Http/Controllers/NutritionController.php) | 10 / S |
| [ ] | R1262 | `PUT /organization-jobs/{organizationJob}` | `auth.organization-jobs.update` | [OrganizationJobController@update](../app/Http/Controllers/OrganizationJobController.php) | 12 / S |
| [ ] | R1263 | `DELETE /organization-jobs/{organizationJob}` | `auth.organization-jobs.destroy` | [OrganizationJobController@destroy](../app/Http/Controllers/OrganizationJobController.php) | 12 / D |
| [ ] | R1264 | `POST /outfit-deliveries/{delivery}/issue` | `auth.outfit-deliveries.issue.request` | [OutfitSubscriptionController@requestDeliveryIssue](../app/Http/Controllers/OutfitSubscriptionController.php) | 31 / S |
| [ ] | R1265 | `GET\|HEAD /outfit-subscriptions` | `auth.outfit-subscriptions.index` | [OutfitSubscriptionController@index](../app/Http/Controllers/OutfitSubscriptionController.php) | 31 / L |
| [ ] | R1266 | `POST /outfit-subscriptions/plans/{plan}` | `auth.outfit-subscriptions.store` | [OutfitSubscriptionController@store](../app/Http/Controllers/OutfitSubscriptionController.php) | 31 / S |
| [ ] | R1267 | `PUT /outfit-subscriptions/style-profile` | `auth.outfit-subscriptions.profile.update` | [OutfitSubscriptionController@updateProfile](../app/Http/Controllers/OutfitSubscriptionController.php) | 31 / S |
| [ ] | R1268 | `POST /outfit-subscriptions/{subscription}/cancel` | `auth.outfit-subscriptions.cancel` | [OutfitSubscriptionController@cancel](../app/Http/Controllers/OutfitSubscriptionController.php) | 31 / S |
| [ ] | R1269 | `POST /outfit-subscriptions/{subscription}/pause` | `auth.outfit-subscriptions.pause` | [OutfitSubscriptionController@pause](../app/Http/Controllers/OutfitSubscriptionController.php) | 31 / S |
| [ ] | R1270 | `POST /outfit-subscriptions/{subscription}/resume` | `auth.outfit-subscriptions.resume` | [OutfitSubscriptionController@resume](../app/Http/Controllers/OutfitSubscriptionController.php) | 31 / S |
| [ ] | R1271 | `GET\|HEAD /posts` | `auth.posts.index` | [PostController@index](../app/Http/Controllers/PostController.php) | 04, 02 / L |
| [ ] | R1272 | `POST /posts` | `auth.posts.store` | [PostController@store](../app/Http/Controllers/PostController.php) | 04, 02 / S |
| [ ] | R1273 | `PUT /posts/{post}` | `auth.posts.update` | [PostController@update](../app/Http/Controllers/PostController.php) | 04, 02 / S |
| [ ] | R1274 | `DELETE /posts/{post}` | `auth.posts.destroy` | [PostController@destroy](../app/Http/Controllers/PostController.php) | 04, 02 / D |
| [ ] | R1275 | `GET\|HEAD /posts/{post}/comments` | `auth.comments.index` | [CommentController@index](../app/Http/Controllers/CommentController.php) | 04, 02 / L |
| [ ] | R1276 | `POST /posts/{post}/comments` | `auth.comments.store` | [CommentController@store](../app/Http/Controllers/CommentController.php) | 04, 02 / S |
| [ ] | R1277 | `POST /posts/{post}/helpful` | `auth.posts.helpful` | [PostHelpfulController@toggle](../app/Http/Controllers/PostHelpfulController.php) | 04, 02 / S |
| [ ] | R1278 | `POST /posts/{post}/like` | `auth.posts.like` | [LikeController@togglePost](../app/Http/Controllers/LikeController.php) | 04, 02 / S |
| [ ] | R1279 | `GET\|HEAD\|POST\|PUT\|PATCH\|DELETE\|OPTIONS /preise` | `(ohne Name)` | `Illuminate\Routing\RedirectController` | 31 / L+S+D+X |
| [ ] | R1280 | `GET\|HEAD /profile-completion` | `auth.profile-completion.edit` | [ProfileCompletionController@edit](../app/Http/Controllers/ProfileCompletionController.php) | 02, 01 / L |
| [ ] | R1281 | `PUT /profile-completion` | `auth.profile-completion.update` | [ProfileCompletionController@update](../app/Http/Controllers/ProfileCompletionController.php) | 02, 01 / S |
| [ ] | R1282 | `PUT /profile/recommendations/{profileRecommendation}/approve` | `auth.profile.recommendations.approve` | [ProfileGamificationController@approveRecommendation](../app/Http/Controllers/ProfileGamificationController.php) | 02, 01 / S |
| [ ] | R1283 | `PUT /profile/recommendations/{profileRecommendation}/reject` | `auth.profile.recommendations.reject` | [ProfileGamificationController@rejectRecommendation](../app/Http/Controllers/ProfileGamificationController.php) | 02, 01 / S |
| [ ] | R1284 | `PUT /profile/skills/{userSportSkill}` | `auth.profile.skills.update` | [ProfileGamificationController@updateSkill](../app/Http/Controllers/ProfileGamificationController.php) | 02, 01 / S |
| [ ] | R1285 | `POST /profile/sports` | `auth.profile.sports.store` | [ProfileGamificationController@storeSport](../app/Http/Controllers/ProfileGamificationController.php) | 02, 01 / S |
| [ ] | R1286 | `GET\|HEAD /recruiting-pipeline` | `auth.recruiting-pipeline.index` | [RecruitingPipelineController@index](../app/Http/Controllers/RecruitingPipelineController.php) | 12 / L |
| [ ] | R1287 | `PUT /recruiting-pipeline/applications/{interest}` | `auth.recruiting-pipeline.applications.update` | [RecruitingPipelineController@update](../app/Http/Controllers/RecruitingPipelineController.php) | 12 / S |
| [ ] | R1288 | `DELETE /recruiting-pipeline/applications/{interest}` | `auth.recruiting-pipeline.applications.destroy` | [RecruitingPipelineController@destroy](../app/Http/Controllers/RecruitingPipelineController.php) | 12 / D |
| [ ] | R1289 | `POST /recruiting-pipeline/applications/{interest}/chat` | `auth.recruiting-pipeline.applications.chat` | [RecruitingPipelineController@chat](../app/Http/Controllers/RecruitingPipelineController.php) | 12 / S |
| [ ] | R1290 | `GET\|HEAD /register` | `register` | `Laravel\Fortify\Http\Controllers\RegisteredUserController@create` | 01 / L |
| [ ] | R1291 | `POST /register` | `register.store` | `Laravel\Fortify\Http\Controllers\RegisteredUserController@store` | 01 / S |
| [ ] | R1292 | `POST /reports` | `auth.reports.store` | [ContentReportController@store](../app/Http/Controllers/ContentReportController.php) | 04, 33 / S |
| [ ] | R1293 | `POST /reports/{report}/appeal` | `auth.reports.appeal` | [ContentReportController@appeal](../app/Http/Controllers/ContentReportController.php) | 04, 33 / S |
| [ ] | R1294 | `POST /reset-password` | `password.update` | `Laravel\Fortify\Http\Controllers\NewPasswordController@store` | 01 / S |
| [ ] | R1295 | `GET\|HEAD /reset-password` | `password.reset.query` | `Closure` | 01 / L |
| [ ] | R1296 | `GET\|HEAD /reset-password/{token}` | `password.reset` | `Laravel\Fortify\Http\Controllers\NewPasswordController@create` | 01 / L |
| [ ] | R1297 | `GET\|HEAD /rides` | `auth.rides.index` | [RideController@index](../app/Http/Controllers/RideController.php) | 38 / L |
| [ ] | R1298 | `POST /rides` | `auth.rides.store` | [RideController@store](../app/Http/Controllers/RideController.php) | 38 / S |
| [ ] | R1299 | `PUT /rides/{ride}` | `auth.rides.update` | [RideController@update](../app/Http/Controllers/RideController.php) | 38 / S |
| [ ] | R1300 | `DELETE /rides/{ride}` | `auth.rides.destroy` | [RideController@destroy](../app/Http/Controllers/RideController.php) | 38 / D |
| [ ] | R1301 | `POST /rides/{ride}/join` | `auth.rides.join` | [RideController@join](../app/Http/Controllers/RideController.php) | 38 / S |
| [ ] | R1302 | `POST /rides/{ride}/leave` | `auth.rides.leave` | [RideController@leave](../app/Http/Controllers/RideController.php) | 38 / S |
| [ ] | R1303 | `DELETE /rides/{ride}/members/{user}` | `auth.rides.members.destroy` | [RideController@removeMember](../app/Http/Controllers/RideController.php) | 38 / D |
| [ ] | R1304 | `POST /rides/{ride}/requests/{user}/approve` | `auth.rides.requests.approve` | [RideController@approveRequest](../app/Http/Controllers/RideController.php) | 38 / S |
| [ ] | R1305 | `POST /rides/{ride}/requests/{user}/reject` | `auth.rides.requests.reject` | [RideController@rejectRequest](../app/Http/Controllers/RideController.php) | 38 / S |
| [ ] | R1306 | `GET\|HEAD /robots.txt` | `robots` | `Closure` | 40 / L |
| [ ] | R1307 | `POST /role-applications` | `auth.role-applications.store` | [AccountRoleApplicationController@store](../app/Http/Controllers/AccountRoleApplicationController.php) | 09, 15 / S |
| [ ] | R1308 | `GET\|HEAD /sanctum/csrf-cookie` | `sanctum.csrf-cookie` | `Laravel\Sanctum\Http\Controllers\CsrfCookieController@show` | 36, 34 / L+X |
| [ ] | R1309 | `GET\|HEAD /saved-views` | `auth.saved-views.index` | [SavedViewController@index](../app/Http/Controllers/SavedViewController.php) | 03, 36 / L |
| [ ] | R1310 | `POST /saved-views` | `auth.saved-views.store` | [SavedViewController@store](../app/Http/Controllers/SavedViewController.php) | 03, 36 / S |
| [ ] | R1311 | `PUT /saved-views/{savedView}` | `auth.saved-views.update` | [SavedViewController@update](../app/Http/Controllers/SavedViewController.php) | 03, 36 / S |
| [ ] | R1312 | `DELETE /saved-views/{savedView}` | `auth.saved-views.destroy` | [SavedViewController@destroy](../app/Http/Controllers/SavedViewController.php) | 03, 36 / D |
| [ ] | R1313 | `GET\|HEAD /search` | `auth.search` | [GlobalSearchController](../app/Http/Controllers/GlobalSearchController.php) | 03, 36 / L |
| [ ] | R1314 | `GET\|HEAD /settings` | `auth.settings` | [UserSettingsController@index](../app/Http/Controllers/UserSettingsController.php) | 02, 01 / L |
| [ ] | R1315 | `PUT /settings` | `auth.settings.update` | [UserSettingsController@update](../app/Http/Controllers/UserSettingsController.php) | 02, 01 / S |
| [ ] | R1316 | `GET\|HEAD /settings/daten-loeschen` | `auth.settings.privacy.erasure` | [UserDataErasureController@edit](../app/Http/Controllers/UserDataErasureController.php) | 02, 01 / L |
| [ ] | R1317 | `POST /settings/daten-loeschen` | `auth.settings.privacy.erasure.destroy` | [UserDataErasureController@destroy](../app/Http/Controllers/UserDataErasureController.php) | 02, 01 / S |
| [ ] | R1318 | `POST /settings/daten-loeschen/code` | `auth.settings.privacy.erasure.code` | [UserDataErasureController@sendCode](../app/Http/Controllers/UserDataErasureController.php) | 02, 01 / S |
| [ ] | R1319 | `PATCH /settings/privacy/correction` | `auth.settings.privacy.correct` | [UserPrivacyController@correct](../app/Http/Controllers/UserPrivacyController.php) | 02, 01 / S |
| [ ] | R1320 | `GET\|HEAD /settings/privacy/export` | `auth.settings.privacy.export` | [UserPrivacyController@export](../app/Http/Controllers/UserPrivacyController.php) | 02, 01 / L |
| [ ] | R1321 | `POST /settings/privacy/withdraw-consents` | `auth.settings.privacy.withdraw-consents` | [UserPrivacyController@withdrawConsents](../app/Http/Controllers/UserPrivacyController.php) | 02, 01 / S |
| [ ] | R1322 | `POST /settings/sport-activities` | `auth.sport-activities.store` | [SportIntegrationController@storeActivity](../app/Http/Controllers/SportIntegrationController.php) | 02, 01 / S |
| [ ] | R1323 | `DELETE /settings/sport-activities` | `auth.sport-activities.destroy-all` | [SportIntegrationController@destroyActivities](../app/Http/Controllers/SportIntegrationController.php) | 02, 01 / D |
| [ ] | R1324 | `PUT /settings/sport-activities/{activity}` | `auth.sport-activities.update` | [SportIntegrationController@updateActivity](../app/Http/Controllers/SportIntegrationController.php) | 02, 01 / S |
| [ ] | R1325 | `DELETE /settings/sport-activities/{activity}` | `auth.sport-activities.destroy` | [SportIntegrationController@destroyActivity](../app/Http/Controllers/SportIntegrationController.php) | 02, 01 / D |
| [ ] | R1326 | `DELETE /settings/sport-integrations/{account}` | `auth.sport-integrations.destroy` | [SportIntegrationController@destroy](../app/Http/Controllers/SportIntegrationController.php) | 02, 01 / D |
| [ ] | R1327 | `POST /settings/sport-integrations/{account}/sync` | `auth.sport-integrations.sync` | [SportIntegrationController@sync](../app/Http/Controllers/SportIntegrationController.php) | 02, 01 / S |
| [ ] | R1328 | `GET\|HEAD /settings/sport-integrations/{provider}/callback` | `auth.sport-integrations.callback` | [SportIntegrationController@callback](../app/Http/Controllers/SportIntegrationController.php) | 02, 01 / L+X |
| [ ] | R1329 | `GET\|HEAD /settings/sport-integrations/{provider}/connect` | `auth.sport-integrations.connect` | [SportIntegrationController@redirect](../app/Http/Controllers/SportIntegrationController.php) | 02, 01 / L |
| [ ] | R1330 | `PUT /settings/sport-profiles/{sport}` | `auth.settings.sport-profiles.update` | [UserSettingsController@updateSportProfile](../app/Http/Controllers/UserSettingsController.php) | 02, 01 / S |
| [ ] | R1331 | `DELETE /settings/sport-profiles/{sport}` | `auth.settings.sport-profiles.destroy` | [UserSettingsController@destroySportProfile](../app/Http/Controllers/UserSettingsController.php) | 02, 01 / D |
| [ ] | R1332 | `POST /settings/subscription-invoices/{subscriptionInvoice}/cancel-open-payment` | `auth.settings.subscription-invoices.cancel-open-payment` | [UserSettingsController@cancelOpenPayment](../app/Http/Controllers/UserSettingsController.php) | 02, 01 / S |
| [ ] | R1333 | `DELETE /settings/subscription-invoices/{subscriptionInvoice}/open-payment` | `auth.settings.subscription-invoices.destroy-open-payment` | [UserSettingsController@destroyOpenPayment](../app/Http/Controllers/UserSettingsController.php) | 02, 01 / D |
| [ ] | R1334 | `GET\|HEAD /shared-files/{token}` | `files.shared-download` | [FileController@sharedDownload](../app/Http/Controllers/FileController.php) | 07 / L+X |
| [ ] | R1335 | `GET\|HEAD /site.webmanifest` | `site.webmanifest` | [PublicWebManifestController](../app/Http/Controllers/PublicWebManifestController.php) | 40 / L |
| [ ] | R1336 | `GET\|HEAD /sitemap.xml` | `sitemap` | `Closure` | 40 / L |
| [ ] | R1337 | `GET\|HEAD /sponsor-cockpit` | `auth.sponsor-workspace.index` | [SponsorWorkspaceController@index](../app/Http/Controllers/SponsorWorkspaceController.php) | 29 / L |
| [ ] | R1338 | `PUT /sponsor-cockpit/profile` | `auth.sponsor-workspace.profile.update` | [SponsorWorkspaceController@updateProfile](../app/Http/Controllers/SponsorWorkspaceController.php) | 29 / S |
| [ ] | R1339 | `GET\|HEAD /sponsoren` | `guest.sponsors` | [PublicSponsorController@index](../app/Http/Controllers/PublicSponsorController.php) | 29 / L |
| [ ] | R1340 | `GET\|HEAD /sport-in/{country}/{city}` | `guest.cities.show` | [PublicDiscoveryController@city](../app/Http/Controllers/PublicDiscoveryController.php) | 40 / L |
| [ ] | R1341 | `GET\|HEAD /sport-map` | `auth.sport-map.index` | [SportMapController@index](../app/Http/Controllers/SportMapController.php) | 11 / L |
| [ ] | R1342 | `GET\|HEAD /sport-matching` | `auth.sport-matching.index` | [SportMatchingController@index](../app/Http/Controllers/SportMatchingController.php) | 12 / L |
| [ ] | R1343 | `POST /sport-matching` | `auth.sport-matching.store` | [SportMatchingController@store](../app/Http/Controllers/SportMatchingController.php) | 12 / S |
| [ ] | R1344 | `PUT /sport-matching/{sportMatching}/applications/{application}` | `auth.sport-matching.applications.update` | [SportMatchingController@decide](../app/Http/Controllers/SportMatchingController.php) | 12 / S |
| [ ] | R1345 | `POST /sport-matching/{sportMatching}/apply` | `auth.sport-matching.apply` | [SportMatchingController@apply](../app/Http/Controllers/SportMatchingController.php) | 12 / S |
| [ ] | R1346 | `PUT /sport-matching/{sportMatching}/attendance` | `auth.sport-matching.attendance.update` | [SportMatchingController@updateAttendance](../app/Http/Controllers/SportMatchingController.php) | 12 / S |
| [ ] | R1347 | `POST /sport-matching/{sportMatching}/attendance/no-show` | `auth.sport-matching.attendance.no-show` | [SportMatchingController@reportNoShow](../app/Http/Controllers/SportMatchingController.php) | 12 / S |
| [ ] | R1348 | `POST /sport-matching/{sportMatching}/cancel` | `auth.sport-matching.cancel` | [SportMatchingController@cancel](../app/Http/Controllers/SportMatchingController.php) | 12 / S |
| [ ] | R1349 | `POST /sport-matching/{sportMatching}/dismiss` | `auth.sport-matching.dismiss` | [SportMatchingController@dismiss](../app/Http/Controllers/SportMatchingController.php) | 12 / S |
| [ ] | R1350 | `POST /sport-matching/{sportMatching}/withdraw` | `auth.sport-matching.withdraw` | [SportMatchingController@withdraw](../app/Http/Controllers/SportMatchingController.php) | 12 / S |
| [ ] | R1351 | `POST /sport-places` | `auth.sport-places.store` | [SportMapController@storePlace](../app/Http/Controllers/SportMapController.php) | 11 / S |
| [ ] | R1352 | `PUT /sport-places/{sportPlace}` | `auth.sport-places.update` | [SportMapController@updatePlace](../app/Http/Controllers/SportMapController.php) | 11 / S |
| [ ] | R1353 | `DELETE /sport-places/{sportPlace}` | `auth.sport-places.destroy` | [SportMapController@destroyPlace](../app/Http/Controllers/SportMapController.php) | 11 / D |
| [ ] | R1354 | `POST /sport-route-proposals` | `auth.sport-route-proposals.store` | [SportMapController@generateRouteProposal](../app/Http/Controllers/SportMapController.php) | 11 / S |
| [ ] | R1355 | `POST /sport-routes` | `auth.sport-routes.store` | [SportMapController@storeRoute](../app/Http/Controllers/SportMapController.php) | 11 / S |
| [ ] | R1356 | `PUT /sport-routes/{sportRoute}` | `auth.sport-routes.update` | [SportMapController@updateRoute](../app/Http/Controllers/SportMapController.php) | 11 / S |
| [ ] | R1357 | `DELETE /sport-routes/{sportRoute}` | `auth.sport-routes.destroy` | [SportMapController@destroyRoute](../app/Http/Controllers/SportMapController.php) | 11 / D |
| [ ] | R1358 | `POST /sport-tracks` | `auth.sport-tracks.store` | [SportMapController@storeTrack](../app/Http/Controllers/SportMapController.php) | 11 / S |
| [ ] | R1359 | `PUT /sport-tracks/{sportRouteTrack}` | `auth.sport-tracks.update` | [SportMapController@updateTrack](../app/Http/Controllers/SportMapController.php) | 11 / S |
| [ ] | R1360 | `DELETE /sport-tracks/{sportRouteTrack}` | `auth.sport-tracks.destroy` | [SportMapController@destroyTrack](../app/Http/Controllers/SportMapController.php) | 11 / D |
| [ ] | R1361 | `GET\|HEAD /sportarten` | `guest.sports` | [PublicDiscoveryController@sports](../app/Http/Controllers/PublicDiscoveryController.php) | 40 / L |
| [ ] | R1362 | `GET\|HEAD /sportarten/{sport}` | `guest.sports.show` | [PublicDiscoveryController@sport](../app/Http/Controllers/PublicDiscoveryController.php) | 40 / L |
| [ ] | R1363 | `GET\|HEAD /sportstaedte` | `guest.cities` | [PublicDiscoveryController@cities](../app/Http/Controllers/PublicDiscoveryController.php) | 40 / L |
| [ ] | R1364 | `POST /standort/anlegen` | `contact.store` | [KontaktController@store](../app/Http/Controllers/KontaktController.php) | 39, 40 / S |
| [ ] | R1365 | `GET\|HEAD /storage/{path}` | `storage.local` | `Closure` | 36, 34 / L |
| [ ] | R1366 | `PUT /storage/{path}` | `storage.local.upload` | `Closure` | 36, 34 / S |
| [ ] | R1367 | `POST /stories` | `auth.stories.store` | [StoryController@store](../app/Http/Controllers/StoryController.php) | 04, 02 / S |
| [ ] | R1368 | `DELETE /stories/{story}` | `auth.stories.destroy` | [StoryController@destroy](../app/Http/Controllers/StoryController.php) | 04, 02 / D |
| [ ] | R1369 | `POST /stories/{story}/react` | `auth.stories.react` | [StoryController@react](../app/Http/Controllers/StoryController.php) | 04, 02 / S |
| [ ] | R1370 | `POST /stories/{story}/viewed` | `auth.stories.viewed` | [StoryController@viewed](../app/Http/Controllers/StoryController.php) | 04, 02 / S |
| [ ] | R1371 | `GET\|HEAD /subscription-invoices/{subscriptionInvoice}/download` | `auth.subscription-invoices.download` | [SubscriptionInvoiceController@download](../app/Http/Controllers/SubscriptionInvoiceController.php) | 31 / L |
| [ ] | R1372 | `GET\|HEAD /support` | `auth.support.index` | [SupportCenterController@index](../app/Http/Controllers/SupportCenterController.php) | 39 / L |
| [ ] | R1373 | `GET\|HEAD /team-invitations/token/{token}/accept` | `auth.team-invitations.accept-by-token` | [TeamController@acceptInvitationByToken](../app/Http/Controllers/TeamController.php) | 23, 08 / L |
| [ ] | R1374 | `POST /team-invitations/{invitation}/accept` | `auth.team-invitations.accept` | [TeamController@acceptInvitation](../app/Http/Controllers/TeamController.php) | 23, 08 / S |
| [ ] | R1375 | `POST /team-invitations/{invitation}/decline` | `auth.team-invitations.decline` | [TeamController@declineInvitation](../app/Http/Controllers/TeamController.php) | 23, 08 / S |
| [ ] | R1376 | `OPTIONS /team-join-requests/{joinRequest}/approve` | `auth.team-join-requests.approve.options` | `Closure` | 23, 08 / X |
| [ ] | R1377 | `POST /team-join-requests/{joinRequest}/approve` | `auth.team-join-requests.approve` | [TeamController@approveJoinRequest](../app/Http/Controllers/TeamController.php) | 23, 08 / S |
| [ ] | R1378 | `OPTIONS /team-join-requests/{joinRequest}/decline` | `auth.team-join-requests.decline.options` | `Closure` | 23, 08 / X |
| [ ] | R1379 | `POST /team-join-requests/{joinRequest}/decline` | `auth.team-join-requests.decline` | [TeamController@declineJoinRequest](../app/Http/Controllers/TeamController.php) | 23, 08 / S |
| [ ] | R1380 | `OPTIONS /teams` | `auth.teams.store.options` | `Closure` | 23, 08 / X |
| [ ] | R1381 | `GET\|HEAD /teams` | `auth.teams.index` | [TeamController@index](../app/Http/Controllers/TeamController.php) | 23, 08 / L |
| [ ] | R1382 | `POST /teams` | `auth.teams.store` | [TeamController@store](../app/Http/Controllers/TeamController.php) | 23, 08 / S |
| [ ] | R1383 | `GET\|HEAD /teams/{team}` | `auth.teams.show` | [TeamController@show](../app/Http/Controllers/TeamController.php) | 23, 08 / L |
| [ ] | R1384 | `PUT /teams/{team}` | `auth.teams.update` | [TeamController@update](../app/Http/Controllers/TeamController.php) | 23, 08 / S |
| [ ] | R1385 | `DELETE /teams/{team}` | `auth.teams.destroy` | [TeamController@destroy](../app/Http/Controllers/TeamController.php) | 23, 08 / D |
| [ ] | R1386 | `GET\|HEAD /teams/{team}/attendance-stats` | `auth.teams.attendance-stats` | [TeamController@attendanceStats](../app/Http/Controllers/TeamController.php) | 23, 08 / L |
| [ ] | R1387 | `POST /teams/{team}/images` | `auth.teams.images.update` | [TeamController@updateImages](../app/Http/Controllers/TeamController.php) | 23, 08 / S |
| [ ] | R1388 | `POST /teams/{team}/invite` | `auth.teams.invite` | [TeamController@invite](../app/Http/Controllers/TeamController.php) | 23, 08 / S |
| [ ] | R1389 | `POST /teams/{team}/join-requests` | `auth.teams.join-requests.store` | [TeamController@requestJoin](../app/Http/Controllers/TeamController.php) | 23, 08 / S |
| [ ] | R1390 | `POST /teams/{team}/members` | `auth.teams.members.store` | [TeamController@storeMember](../app/Http/Controllers/TeamController.php) | 23, 08 / S |
| [ ] | R1391 | `OPTIONS /teams/{team}/members/{user}` | `auth.teams.members.update.options` | `Closure` | 23, 08 / X |
| [ ] | R1392 | `PUT /teams/{team}/members/{user}` | `auth.teams.members.update` | [TeamController@updateMember](../app/Http/Controllers/TeamController.php) | 23, 08 / S |
| [ ] | R1393 | `DELETE /teams/{team}/members/{user}` | `auth.teams.members.destroy` | [TeamController@removeMember](../app/Http/Controllers/TeamController.php) | 23, 08 / D |
| [ ] | R1394 | `GET\|HEAD /teams/{team}/penalties` | `auth.teams.penalties.index` | [TeamPenaltyController@index](../app/Http/Controllers/Api/V1/TeamPenaltyController.php) | 23, 08 / L |
| [ ] | R1395 | `POST /teams/{team}/penalty-fees` | `auth.teams.penalty-fees.store` | [TeamPenaltyController@storeFee](../app/Http/Controllers/Api/V1/TeamPenaltyController.php) | 23, 08 / S |
| [ ] | R1396 | `POST /teams/{team}/penalty-fees/{fee}/cancel` | `auth.teams.penalty-fees.cancel` | [TeamPenaltyController@cancelFee](../app/Http/Controllers/Api/V1/TeamPenaltyController.php) | 23, 08 / S |
| [ ] | R1397 | `POST /teams/{team}/penalty-fees/{fee}/paid` | `auth.teams.penalty-fees.paid` | [TeamPenaltyController@markFeePaid](../app/Http/Controllers/Api/V1/TeamPenaltyController.php) | 23, 08 / S |
| [ ] | R1398 | `POST /teams/{team}/penalty-rules` | `auth.teams.penalty-rules.store` | [TeamPenaltyController@storeRule](../app/Http/Controllers/Api/V1/TeamPenaltyController.php) | 23, 08 / S |
| [ ] | R1399 | `PUT /teams/{team}/penalty-rules/{penaltyRule}` | `auth.teams.penalty-rules.update` | [TeamPenaltyController@updateRule](../app/Http/Controllers/Api/V1/TeamPenaltyController.php) | 23, 08 / S |
| [ ] | R1400 | `DELETE /teams/{team}/penalty-rules/{penaltyRule}` | `auth.teams.penalty-rules.destroy` | [TeamPenaltyController@destroyRule](../app/Http/Controllers/Api/V1/TeamPenaltyController.php) | 23, 08 / D |
| [ ] | R1401 | `GET\|HEAD /top-inhalte` | `guest.top-inhalte` | `Closure` | 40 / L |
| [ ] | R1402 | `GET\|HEAD /trainer-cockpit` | `auth.trainer-cockpit.index` | [TrainerCockpitController@index](../app/Http/Controllers/TrainerCockpitController.php) | 09, 15 / L |
| [ ] | R1403 | `GET\|HEAD /training` | `auth.training.index` | [TrainingController@index](../app/Http/Controllers/TrainingController.php) | 09, 15 / L |
| [ ] | R1404 | `POST /training/activities` | `auth.training.activities.store` | [TrainingController@storeActivity](../app/Http/Controllers/TrainingController.php) | 09, 15 / S |
| [ ] | R1405 | `POST /training/ai/plans` | `auth.training.ai.plans.store` | [TrainingController@storeAiTrainingPlan](../app/Http/Controllers/TrainingController.php) | 09, 15 / S |
| [ ] | R1406 | `POST /training/ai/plans/preview` | `auth.training.ai.plans.preview` | [TrainingController@previewAiTrainingPlan](../app/Http/Controllers/TrainingController.php) | 09, 15 / S |
| [ ] | R1407 | `POST /training/logs` | `auth.training.logs.store` | [TrainingController@storeLog](../app/Http/Controllers/TrainingController.php) | 09, 15 / S |
| [ ] | R1408 | `GET\|HEAD /training/logs/create` | `auth.training.logs.create` | [TrainingController@createLog](../app/Http/Controllers/TrainingController.php) | 09, 15 / L |
| [ ] | R1409 | `GET\|HEAD /training/logs/{log}` | `auth.training.logs.show` | [TrainingController@showLog](../app/Http/Controllers/TrainingController.php) | 09, 15 / L |
| [ ] | R1410 | `PUT /training/logs/{log}/draft` | `auth.training.logs.draft.update` | [TrainingController@updateDraftLog](../app/Http/Controllers/TrainingController.php) | 09, 15 / S |
| [ ] | R1411 | `DELETE /training/logs/{log}/draft` | `auth.training.logs.draft.destroy` | [TrainingController@destroyDraftLog](../app/Http/Controllers/TrainingController.php) | 09, 15 / D |
| [ ] | R1412 | `POST /training/logs/{log}/feedback` | `auth.training.logs.feedback.store` | [TrainingController@storeLogFeedback](../app/Http/Controllers/TrainingController.php) | 09, 15 / S |
| [ ] | R1413 | `POST /training/plans` | `auth.training.plans.store` | [TrainingController@storePlan](../app/Http/Controllers/TrainingController.php) | 09, 15 / S |
| [ ] | R1414 | `PUT /training/plans/{plan}` | `auth.training.plans.update` | [TrainingController@updatePlan](../app/Http/Controllers/TrainingController.php) | 09, 15 / S |
| [ ] | R1415 | `DELETE /training/plans/{plan}` | `auth.training.plans.destroy` | [TrainingController@destroyPlan](../app/Http/Controllers/TrainingController.php) | 09, 15 / D |
| [ ] | R1416 | `POST /training/plans/{plan}/duplicate` | `auth.training.plans.duplicate` | [TrainingController@duplicatePlan](../app/Http/Controllers/TrainingController.php) | 09, 15 / S |
| [ ] | R1417 | `POST /training/plans/{plan}/items` | `auth.training.plans.items.store` | [TrainingController@storePlanItem](../app/Http/Controllers/TrainingController.php) | 09, 15 / S |
| [ ] | R1418 | `GET\|HEAD /training/plans/{plan}/items/{item}` | `auth.training.plans.items.show` | [TrainingController@showPlanItem](../app/Http/Controllers/TrainingController.php) | 09, 15 / L |
| [ ] | R1419 | `PUT /training/plans/{plan}/items/{item}` | `auth.training.plans.items.update` | [TrainingController@updatePlanItem](../app/Http/Controllers/TrainingController.php) | 09, 15 / S |
| [ ] | R1420 | `DELETE /training/plans/{plan}/items/{item}` | `auth.training.plans.items.destroy` | [TrainingController@destroyPlanItem](../app/Http/Controllers/TrainingController.php) | 09, 15 / D |
| [ ] | R1421 | `POST /training/plans/{plan}/items/{item}/duplicate` | `auth.training.plans.items.duplicate` | [TrainingController@duplicatePlanItem](../app/Http/Controllers/TrainingController.php) | 09, 15 / S |
| [ ] | R1422 | `POST /training/plans/{plan}/items/{item}/missed` | `auth.training.plans.items.missed` | [TrainingController@markPlanItemMissed](../app/Http/Controllers/TrainingController.php) | 09, 15 / S |
| [ ] | R1423 | `POST /training/plans/{plan}/publish` | `auth.training.plans.publish` | [TrainingController@publishPlan](../app/Http/Controllers/TrainingController.php) | 09, 15 / S |
| [ ] | R1424 | `GET\|HEAD /two-factor-challenge` | `two-factor.login` | `Laravel\Fortify\Http\Controllers\TwoFactorAuthenticatedSessionController@create` | 01 / L |
| [ ] | R1425 | `POST /two-factor-challenge` | `two-factor.login.store` | `Laravel\Fortify\Http\Controllers\TwoFactorAuthenticatedSessionController@store` | 01 / S |
| [ ] | R1426 | `POST /two-factor-challenge/email-code` | `two-factor.email.send` | [TwoFactorEmailCodeController@send](../app/Http/Controllers/TwoFactorEmailCodeController.php) | 01 / S |
| [ ] | R1427 | `POST /two-factor-challenge/email-login` | `two-factor.email.login` | [TwoFactorEmailCodeController@store](../app/Http/Controllers/TwoFactorEmailCodeController.php) | 01 / S |
| [ ] | R1428 | `GET\|HEAD /up` | `(ohne Name)` | `Closure` | 36, 34 / L |
| [ ] | R1429 | `DELETE /user` | `current-user.destroy` | [AccountDeletionController@destroy](../app/Http/Controllers/AccountDeletionController.php) | 02, 01 / D |
| [ ] | R1430 | `POST /user-subscriptions/{subscription}/cancel` | `auth.user-subscriptions.cancel` | [SubscriptionPlanController@cancelOwnUserSubscription](../app/Http/Controllers/SubscriptionPlanController.php) | 31 / S |
| [ ] | R1431 | `POST /user-subscriptions/{subscription}/provider-portal` | `auth.user-subscriptions.provider-portal` | [SubscriptionPlanController@providerPortal](../app/Http/Controllers/SubscriptionPlanController.php) | 31 / S |
| [ ] | R1432 | `GET\|HEAD /user/confirm-password` | `password.confirm` | `Laravel\Fortify\Http\Controllers\ConfirmablePasswordController@show` | 01 / L |
| [ ] | R1433 | `POST /user/confirm-password` | `password.confirm.store` | `Laravel\Fortify\Http\Controllers\ConfirmablePasswordController@store` | 01 / S |
| [ ] | R1434 | `GET\|HEAD /user/confirmed-password-status` | `password.confirmation` | `Laravel\Fortify\Http\Controllers\ConfirmedPasswordStatusController@show` | 01 / L |
| [ ] | R1435 | `POST /user/confirmed-two-factor-authentication` | `two-factor.confirm` | `Laravel\Fortify\Http\Controllers\ConfirmedTwoFactorAuthenticationController@store` | 01 / S |
| [ ] | R1436 | `POST /user/deletion-code` | `current-user.deletion-code` | [AccountDeletionController@sendCode](../app/Http/Controllers/AccountDeletionController.php) | 02, 01 / S |
| [ ] | R1437 | `POST /user/language` | `user.language.update` | [UserLanguageController](../app/Http/Controllers/UserLanguageController.php) | 02, 01 / S |
| [ ] | R1438 | `DELETE /user/other-browser-sessions` | `other-browser-sessions.destroy` | `Laravel\Jetstream\Http\Controllers\Inertia\OtherBrowserSessionsController@destroy` | 02, 01 / D |
| [ ] | R1439 | `PUT /user/password` | `user-password.update` | `Laravel\Fortify\Http\Controllers\PasswordController@update` | 02, 01 / S |
| [ ] | R1440 | `GET\|HEAD /user/profile` | `profile.show` | `Laravel\Jetstream\Http\Controllers\Inertia\UserProfileController@show` | 02, 01 / L |
| [ ] | R1441 | `PUT /user/profile-information` | `user-profile-information.update` | `Laravel\Fortify\Http\Controllers\ProfileInformationController@update` | 02, 01 / S |
| [ ] | R1442 | `DELETE /user/profile-photo` | `current-user-photo.destroy` | `Laravel\Jetstream\Http\Controllers\Inertia\ProfilePhotoController@destroy` | 02, 01 / D |
| [ ] | R1443 | `PUT /user/status` | `auth.user.status.update` | [UserStatusController@update](../app/Http/Controllers/UserStatusController.php) | 02, 01 / S |
| [ ] | R1444 | `POST /user/two-factor-authentication` | `two-factor.enable` | `Laravel\Fortify\Http\Controllers\TwoFactorAuthenticationController@store` | 02, 01 / S |
| [ ] | R1445 | `DELETE /user/two-factor-authentication` | `two-factor.disable` | `Laravel\Fortify\Http\Controllers\TwoFactorAuthenticationController@destroy` | 02, 01 / D |
| [ ] | R1446 | `GET\|HEAD /user/two-factor-qr-code` | `two-factor.qr-code` | `Laravel\Fortify\Http\Controllers\TwoFactorQrCodeController@show` | 02, 01 / L |
| [ ] | R1447 | `GET\|HEAD /user/two-factor-recovery-codes` | `two-factor.recovery-codes` | `Laravel\Fortify\Http\Controllers\RecoveryCodeController@index` | 02, 01 / L |
| [ ] | R1448 | `POST /user/two-factor-recovery-codes` | `two-factor.regenerate-recovery-codes` | `Laravel\Fortify\Http\Controllers\RecoveryCodeController@store` | 02, 01 / S |
| [ ] | R1449 | `GET\|HEAD /user/two-factor-secret-key` | `two-factor.secret-key` | `Laravel\Fortify\Http\Controllers\TwoFactorSecretKeyController@show` | 02, 01 / L |
| [ ] | R1450 | `GET\|HEAD /users/{user}` | `auth.users.show` | [UserController@show](../app/Http/Controllers/UserController.php) | 04, 02 / L |
| [ ] | R1451 | `POST /users/{user}/block` | `auth.users.block` | [UserController@block](../app/Http/Controllers/UserController.php) | 04, 02 / S |
| [ ] | R1452 | `DELETE /users/{user}/block` | `auth.users.unblock` | [UserController@unblock](../app/Http/Controllers/UserController.php) | 04, 02 / D |
| [ ] | R1453 | `POST /users/{user}/follow` | `auth.users.follow` | [FollowController@store](../app/Http/Controllers/FollowController.php) | 04, 02 / S |
| [ ] | R1454 | `DELETE /users/{user}/follow` | `auth.users.unfollow` | [FollowController@destroy](../app/Http/Controllers/FollowController.php) | 04, 02 / D |
| [ ] | R1455 | `POST /users/{user}/recommendations` | `auth.users.recommendations.store` | [ProfileGamificationController@recommend](../app/Http/Controllers/ProfileGamificationController.php) | 04, 02 / S |
| [ ] | R1456 | `POST /users/{user}/skills/{userSportSkill}/endorse` | `auth.users.skills.endorse` | [ProfileGamificationController@endorse](../app/Http/Controllers/ProfileGamificationController.php) | 04, 02 / S |
| [ ] | R1457 | `GET\|HEAD /veranstaltungen` | `guest.events` | [PublicDiscoveryController@events](../app/Http/Controllers/PublicDiscoveryController.php) | 25 / L |
| [ ] | R1458 | `GET\|HEAD /veranstaltungen/{event}` | `guest.events.show` | [PublicDiscoveryController@event](../app/Http/Controllers/PublicDiscoveryController.php) | 25 / L |
| [ ] | R1459 | `GET\|HEAD /vereine` | `guest.vereine` | [PublicClubController@index](../app/Http/Controllers/PublicClubController.php) | 40 / L |
| [ ] | R1460 | `GET\|HEAD /vereine/{club}` | `guest.clubs.show` | [PublicDiscoveryController@club](../app/Http/Controllers/PublicDiscoveryController.php) | 40 / L |
| [ ] | R1461 | `GET\|HEAD /verify-email/{id}/{hash}` | `email.verification.bridge` | [EmailVerificationController@verify](../app/Http/Controllers/EmailVerificationController.php) | 01 / L |
| [ ] | R1462 | `POST /webhooks/commerce/paypal` | `webhooks.commerce.paypal` | [CommerceCheckoutController@paypalWebhook](../app/Http/Controllers/CommerceCheckoutController.php) | 36 / S+X |
| [ ] | R1463 | `POST /webhooks/commerce/stripe` | `webhooks.commerce.stripe` | [CommerceCheckoutController@stripeWebhook](../app/Http/Controllers/CommerceCheckoutController.php) | 36 / S+X |
| [ ] | R1464 | `POST /webhooks/mail/postmark` | `webhooks.mail.postmark` | [PostmarkMailWebhookController](../app/Http/Controllers/Webhooks/PostmarkMailWebhookController.php) | 36 / S+X |
| [ ] | R1465 | `POST /webhooks/outfit-subscriptions/paypal` | `webhooks.outfit-subscriptions.paypal` | [OutfitSubscriptionController@paypalWebhook](../app/Http/Controllers/OutfitSubscriptionController.php) | 36 / S+X |
| [ ] | R1466 | `POST /webhooks/paypal` | `webhooks.paypal` | [SubscriptionCheckoutController@paypalWebhook](../app/Http/Controllers/SubscriptionCheckoutController.php) | 36 / S+X |
| [ ] | R1467 | `POST /webhooks/stripe` | `webhooks.stripe` | [SubscriptionCheckoutController@stripeWebhook](../app/Http/Controllers/SubscriptionCheckoutController.php) | 36 / S+X |
| [ ] | R1468 | `GET\|HEAD /werbeagentur-fuer-vereine` | `guest.werbeagentur` | `Closure` | 30, 29 / L |
| [ ] | R1469 | `POST /werbeagentur-fuer-vereine/anfrage` | `guest.werbeagentur.request` | [CommerceCheckoutController@storePublicWebsiteRequest](../app/Http/Controllers/CommerceCheckoutController.php) | 30, 29 / S |
| [ ] | R1470 | `GET\|HEAD /werbeagentur-für-vereine` | `(ohne Name)` | `Closure` | 30, 29 / L |
| [ ] | R1471 | `GET\|HEAD /widerruf` | `legal.withdrawal` | [LegalPageController@withdrawal](../app/Http/Controllers/LegalPageController.php) | 40 / L |
| [ ] | R1472 | `GET\|HEAD /workspaces` | `auth.workspaces.index` | [RoleWorkspaceController@index](../app/Http/Controllers/RoleWorkspaceController.php) | 03, 36 / L |
| [ ] | R1473 | `DELETE /workspaces/clubs/current` | `auth.workspaces.club.clear` | [WorkspaceContextController@clear](../app/Http/Controllers/WorkspaceContextController.php) | 03, 36 / D |
| [ ] | R1474 | `POST /workspaces/clubs/{club}` | `auth.workspaces.club.select` | [WorkspaceContextController@selectClub](../app/Http/Controllers/WorkspaceContextController.php) | 03, 36 / S |

## Web-Seiten

Je Datei prüfen: 1. Tatsächlichen Einstieg und berechtigte Rolle nachweisen. 2. Jeden Tab, Button, Menüeintrag, Filter, Link, Dialog und Leer-/Fehlerzustand ausführen. 3. Speicherung und Folgeansicht auf der anderen Plattform prüfen. Als Produktoberfläche, interne Oberfläche, Beispiel/Demo, Teilansicht oder ungenutzt klassifizieren. Ein Dateiname garantiert weder Navigation noch API-Anbindung.

| Offen | Datei | Fachfall / Ergebnis |
| --- | --- | --- |
| [ ] | [resources/js/Pages/API/Index.vue](../resources/js/Pages/API/Index.vue) | Offen |
| [ ] | [resources/js/Pages/API/Partials/ApiTokenManager.vue](../resources/js/Pages/API/Partials/ApiTokenManager.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/CompleteProfile.vue](../resources/js/Pages/Auth/CompleteProfile.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/ConfirmPassword.vue](../resources/js/Pages/Auth/ConfirmPassword.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/Dashboard/Admin/ClubVerifications/Index.vue](../resources/js/Pages/Auth/Dashboard/Admin/ClubVerifications/Index.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/Dashboard/Admin/Clubs/Index.vue](../resources/js/Pages/Auth/Dashboard/Admin/Clubs/Index.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/Dashboard/Admin/Commerce/Index.vue](../resources/js/Pages/Auth/Dashboard/Admin/Commerce/Index.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/Dashboard/Admin/Invoices/Index.vue](../resources/js/Pages/Auth/Dashboard/Admin/Invoices/Index.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/Dashboard/Admin/MailCenter/Index.vue](../resources/js/Pages/Auth/Dashboard/Admin/MailCenter/Index.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/Dashboard/Admin/Moderation/Index.vue](../resources/js/Pages/Auth/Dashboard/Admin/Moderation/Index.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/Dashboard/Admin/OperatingContracts/Index.vue](../resources/js/Pages/Auth/Dashboard/Admin/OperatingContracts/Index.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/Dashboard/Admin/Operations/Index.vue](../resources/js/Pages/Auth/Dashboard/Admin/Operations/Index.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/Dashboard/Admin/OutfitSubscriptions/Index.vue](../resources/js/Pages/Auth/Dashboard/Admin/OutfitSubscriptions/Index.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/Dashboard/Admin/Payments/Index.vue](../resources/js/Pages/Auth/Dashboard/Admin/Payments/Index.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/Dashboard/Admin/ProductAnalytics/Index.vue](../resources/js/Pages/Auth/Dashboard/Admin/ProductAnalytics/Index.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/Dashboard/Admin/ProviderCosts/Index.vue](../resources/js/Pages/Auth/Dashboard/Admin/ProviderCosts/Index.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/Dashboard/Admin/Settings/Index.vue](../resources/js/Pages/Auth/Dashboard/Admin/Settings/Index.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/Dashboard/Admin/SubscriptionInvoices/Index.vue](../resources/js/Pages/Auth/Dashboard/Admin/SubscriptionInvoices/Index.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/Dashboard/Admin/Subscriptions/Index.vue](../resources/js/Pages/Auth/Dashboard/Admin/Subscriptions/Index.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/Dashboard/Admin/TrainerApplications/Index.vue](../resources/js/Pages/Auth/Dashboard/Admin/TrainerApplications/Index.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/Dashboard/Badges/Index.vue](../resources/js/Pages/Auth/Dashboard/Badges/Index.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/Dashboard/Badges/Show.vue](../resources/js/Pages/Auth/Dashboard/Badges/Show.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/Dashboard/Badges/UserIndex.vue](../resources/js/Pages/Auth/Dashboard/Badges/UserIndex.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/Dashboard/Blogs/Categories.vue](../resources/js/Pages/Auth/Dashboard/Blogs/Categories.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/Dashboard/Blogs/Index.vue](../resources/js/Pages/Auth/Dashboard/Blogs/Index.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/Dashboard/Challenges/Index.vue](../resources/js/Pages/Auth/Dashboard/Challenges/Index.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/Dashboard/Chat/Index.vue](../resources/js/Pages/Auth/Dashboard/Chat/Index.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/Dashboard/ClubCockpit/Index.vue](../resources/js/Pages/Auth/Dashboard/ClubCockpit/Index.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/Dashboard/ClubInventory/Index.vue](../resources/js/Pages/Auth/Dashboard/ClubInventory/Index.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/Dashboard/ClubMemberships/Index.vue](../resources/js/Pages/Auth/Dashboard/ClubMemberships/Index.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/Dashboard/Clubs/Profile.vue](../resources/js/Pages/Auth/Dashboard/Clubs/Profile.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/Dashboard/Commerce/BankTransfer.vue](../resources/js/Pages/Auth/Dashboard/Commerce/BankTransfer.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/Dashboard/Commerce/Cart.vue](../resources/js/Pages/Auth/Dashboard/Commerce/Cart.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/Dashboard/Commerce/Index.vue](../resources/js/Pages/Auth/Dashboard/Commerce/Index.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/Dashboard/Commerce/ProductShow.vue](../resources/js/Pages/Auth/Dashboard/Commerce/ProductShow.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/Dashboard/Events/Index.vue](../resources/js/Pages/Auth/Dashboard/Events/Index.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/Dashboard/Events/Show.vue](../resources/js/Pages/Auth/Dashboard/Events/Show.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/Dashboard/Feed/Index.vue](../resources/js/Pages/Auth/Dashboard/Feed/Index.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/Dashboard/Files/Index.vue](../resources/js/Pages/Auth/Dashboard/Files/Index.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/Dashboard/Friends/Index.vue](../resources/js/Pages/Auth/Dashboard/Friends/Index.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/Dashboard/GamificationRules/Index.vue](../resources/js/Pages/Auth/Dashboard/GamificationRules/Index.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/Dashboard/Index.vue](../resources/js/Pages/Auth/Dashboard/Index.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/Dashboard/Learning/MyCourses.vue](../resources/js/Pages/Auth/Dashboard/Learning/MyCourses.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/Dashboard/Learning/Studio.vue](../resources/js/Pages/Auth/Dashboard/Learning/Studio.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/Dashboard/Maturity/Index.vue](../resources/js/Pages/Auth/Dashboard/Maturity/Index.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/Dashboard/MediaGuidelines/Index.vue](../resources/js/Pages/Auth/Dashboard/MediaGuidelines/Index.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/Dashboard/Notifications/Index.vue](../resources/js/Pages/Auth/Dashboard/Notifications/Index.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/Dashboard/Nutrition/Index.vue](../resources/js/Pages/Auth/Dashboard/Nutrition/Index.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/Dashboard/OutfitSubscriptions/Index.vue](../resources/js/Pages/Auth/Dashboard/OutfitSubscriptions/Index.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/Dashboard/Recruiting/Index.vue](../resources/js/Pages/Auth/Dashboard/Recruiting/Index.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/Dashboard/Rides/Index.vue](../resources/js/Pages/Auth/Dashboard/Rides/Index.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/Dashboard/RolesPermissions/Index.vue](../resources/js/Pages/Auth/Dashboard/RolesPermissions/Index.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/Dashboard/Settings/DataErasure.vue](../resources/js/Pages/Auth/Dashboard/Settings/DataErasure.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/Dashboard/Settings/Index.vue](../resources/js/Pages/Auth/Dashboard/Settings/Index.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/Dashboard/SponsorWorkspace/Index.vue](../resources/js/Pages/Auth/Dashboard/SponsorWorkspace/Index.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/Dashboard/Sponsors/Index.vue](../resources/js/Pages/Auth/Dashboard/Sponsors/Index.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/Dashboard/SportMap/Index.vue](../resources/js/Pages/Auth/Dashboard/SportMap/Index.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/Dashboard/SportMatching/Index.vue](../resources/js/Pages/Auth/Dashboard/SportMatching/Index.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/Dashboard/Sports/Index.vue](../resources/js/Pages/Auth/Dashboard/Sports/Index.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/Dashboard/Subscriptions/BankTransfer.vue](../resources/js/Pages/Auth/Dashboard/Subscriptions/BankTransfer.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/Dashboard/Support/Index.vue](../resources/js/Pages/Auth/Dashboard/Support/Index.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/Dashboard/Teams/Index.vue](../resources/js/Pages/Auth/Dashboard/Teams/Index.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/Dashboard/Teams/Profile.vue](../resources/js/Pages/Auth/Dashboard/Teams/Profile.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/Dashboard/TrainerCockpit/Index.vue](../resources/js/Pages/Auth/Dashboard/TrainerCockpit/Index.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/Dashboard/Training/Index.vue](../resources/js/Pages/Auth/Dashboard/Training/Index.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/Dashboard/Training/LogCreate.vue](../resources/js/Pages/Auth/Dashboard/Training/LogCreate.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/Dashboard/Training/LogShow.vue](../resources/js/Pages/Auth/Dashboard/Training/LogShow.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/Dashboard/Training/PlanItemShow.vue](../resources/js/Pages/Auth/Dashboard/Training/PlanItemShow.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/Dashboard/Users/Create.vue](../resources/js/Pages/Auth/Dashboard/Users/Create.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/Dashboard/Users/Edit.vue](../resources/js/Pages/Auth/Dashboard/Users/Edit.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/Dashboard/Users/Index.vue](../resources/js/Pages/Auth/Dashboard/Users/Index.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/Dashboard/Users/Profile.vue](../resources/js/Pages/Auth/Dashboard/Users/Profile.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/Dashboard/Workspaces/Index.vue](../resources/js/Pages/Auth/Dashboard/Workspaces/Index.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/ForgotPassword.vue](../resources/js/Pages/Auth/ForgotPassword.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/GuardianConsent/Pending.vue](../resources/js/Pages/Auth/GuardianConsent/Pending.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/Login.vue](../resources/js/Pages/Auth/Login.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/Register.vue](../resources/js/Pages/Auth/Register.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/ResetPassword.vue](../resources/js/Pages/Auth/ResetPassword.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/Suspended.vue](../resources/js/Pages/Auth/Suspended.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/TwoFactorChallenge.vue](../resources/js/Pages/Auth/TwoFactorChallenge.vue) | Offen |
| [ ] | [resources/js/Pages/Auth/VerifyEmail.vue](../resources/js/Pages/Auth/VerifyEmail.vue) | Offen |
| [ ] | [resources/js/Pages/Errors/Forbidden.vue](../resources/js/Pages/Errors/Forbidden.vue) | Offen |
| [ ] | [resources/js/Pages/Guardian/Children.vue](../resources/js/Pages/Guardian/Children.vue) | Offen |
| [ ] | [resources/js/Pages/Guardian/CreateAccount.vue](../resources/js/Pages/Guardian/CreateAccount.vue) | Offen |
| [ ] | [resources/js/Pages/Guardian/Login.vue](../resources/js/Pages/Guardian/Login.vue) | Offen |
| [ ] | [resources/js/Pages/Guardian/Verify.vue](../resources/js/Pages/Guardian/Verify.vue) | Offen |
| [ ] | [resources/js/Pages/Guest/Blog/Index.vue](../resources/js/Pages/Guest/Blog/Index.vue) | Offen |
| [ ] | [resources/js/Pages/Guest/Blog/Show.vue](../resources/js/Pages/Guest/Blog/Show.vue) | Offen |
| [ ] | [resources/js/Pages/Guest/Discovery/Cities.vue](../resources/js/Pages/Guest/Discovery/Cities.vue) | Offen |
| [ ] | [resources/js/Pages/Guest/Discovery/Show.vue](../resources/js/Pages/Guest/Discovery/Show.vue) | Offen |
| [ ] | [resources/js/Pages/Guest/E-Learning.vue](../resources/js/Pages/Guest/E-Learning.vue) | Offen |
| [ ] | [resources/js/Pages/Guest/Events.vue](../resources/js/Pages/Guest/Events.vue) | Offen |
| [ ] | [resources/js/Pages/Guest/Gamification.vue](../resources/js/Pages/Guest/Gamification.vue) | Offen |
| [ ] | [resources/js/Pages/Guest/Jobs.vue](../resources/js/Pages/Guest/Jobs.vue) | Offen |
| [ ] | [resources/js/Pages/Guest/LearningCertificateVerify.vue](../resources/js/Pages/Guest/LearningCertificateVerify.vue) | Offen |
| [ ] | [resources/js/Pages/Guest/LearningCourseShow.vue](../resources/js/Pages/Guest/LearningCourseShow.vue) | Offen |
| [ ] | [resources/js/Pages/Guest/Marketplace.vue](../resources/js/Pages/Guest/Marketplace.vue) | Offen |
| [ ] | [resources/js/Pages/Guest/MarketplaceBankTransfer.vue](../resources/js/Pages/Guest/MarketplaceBankTransfer.vue) | Offen |
| [ ] | [resources/js/Pages/Guest/MarketplaceOrderStatus.vue](../resources/js/Pages/Guest/MarketplaceOrderStatus.vue) | Offen |
| [ ] | [resources/js/Pages/Guest/MarketplaceProductShow.vue](../resources/js/Pages/Guest/MarketplaceProductShow.vue) | Offen |
| [ ] | [resources/js/Pages/Guest/MarketplaceProviderShow.vue](../resources/js/Pages/Guest/MarketplaceProviderShow.vue) | Offen |
| [ ] | [resources/js/Pages/Guest/MarketplaceWishlist.vue](../resources/js/Pages/Guest/MarketplaceWishlist.vue) | Offen |
| [ ] | [resources/js/Pages/Guest/Pricing.vue](../resources/js/Pages/Guest/Pricing.vue) | Offen |
| [ ] | [resources/js/Pages/Guest/Sponsors.vue](../resources/js/Pages/Guest/Sponsors.vue) | Offen |
| [ ] | [resources/js/Pages/Guest/Sportarten.vue](../resources/js/Pages/Guest/Sportarten.vue) | Offen |
| [ ] | [resources/js/Pages/Guest/Top-Inhalte.vue](../resources/js/Pages/Guest/Top-Inhalte.vue) | Offen |
| [ ] | [resources/js/Pages/Guest/Vereine.vue](../resources/js/Pages/Guest/Vereine.vue) | Offen |
| [ ] | [resources/js/Pages/Guest/Werbeagentur.vue](../resources/js/Pages/Guest/Werbeagentur.vue) | Offen |
| [ ] | [resources/js/Pages/Legal/Show.vue](../resources/js/Pages/Legal/Show.vue) | Offen |
| [ ] | [resources/js/Pages/Maintenance.vue](../resources/js/Pages/Maintenance.vue) | Offen |
| [ ] | [resources/js/Pages/PrivacyPolicy.vue](../resources/js/Pages/PrivacyPolicy.vue) | Offen |
| [ ] | [resources/js/Pages/Profile/Partials/DeleteUserForm.vue](../resources/js/Pages/Profile/Partials/DeleteUserForm.vue) | Offen |
| [ ] | [resources/js/Pages/Profile/Partials/LogoutOtherBrowserSessionsForm.vue](../resources/js/Pages/Profile/Partials/LogoutOtherBrowserSessionsForm.vue) | Offen |
| [ ] | [resources/js/Pages/Profile/Partials/TwoFactorAuthenticationForm.vue](../resources/js/Pages/Profile/Partials/TwoFactorAuthenticationForm.vue) | Offen |
| [ ] | [resources/js/Pages/Profile/Partials/UpdatePasswordForm.vue](../resources/js/Pages/Profile/Partials/UpdatePasswordForm.vue) | Offen |
| [ ] | [resources/js/Pages/Profile/Partials/UpdateProfileInformationForm.vue](../resources/js/Pages/Profile/Partials/UpdateProfileInformationForm.vue) | Offen |
| [ ] | [resources/js/Pages/Profile/Show.vue](../resources/js/Pages/Profile/Show.vue) | Offen |
| [ ] | [resources/js/Pages/TermsOfService.vue](../resources/js/Pages/TermsOfService.vue) | Offen |
| [ ] | [resources/js/Pages/Welcome.vue](../resources/js/Pages/Welcome.vue) | Offen |

## Flutter-Screen-Dateien

Je Datei prüfen: 1. Tatsächlichen Einstieg und berechtigte Rolle nachweisen. 2. Jeden Tab, Button, Menüeintrag, Filter, Link, Dialog und Leer-/Fehlerzustand ausführen. 3. Speicherung und Folgeansicht auf der anderen Plattform prüfen. Als Produktoberfläche, interne Oberfläche, Beispiel/Demo, Teilansicht oder ungenutzt klassifizieren. Ein Dateiname garantiert weder Navigation noch API-Anbindung.

| Offen | Datei | Fachfall / Ergebnis |
| --- | --- | --- |
| [ ] | [mobile/airmius_mobile/lib/screens/access_operations_screen.dart](../mobile/airmius_mobile/lib/screens/access_operations_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/account_management_screen.dart](../mobile/airmius_mobile/lib/screens/account_management_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/account_operations_screen.dart](../mobile/airmius_mobile/lib/screens/account_operations_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/ad_campaign_screen.dart](../mobile/airmius_mobile/lib/screens/ad_campaign_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/admin_backoffice_screen.dart](../mobile/airmius_mobile/lib/screens/admin_backoffice_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/admin_center_screen.dart](../mobile/airmius_mobile/lib/screens/admin_center_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/admin_club_verification_screen.dart](../mobile/airmius_mobile/lib/screens/admin_club_verification_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/admin_clubs_screen.dart](../mobile/airmius_mobile/lib/screens/admin_clubs_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/admin_commerce_center_screen.dart](../mobile/airmius_mobile/lib/screens/admin_commerce_center_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/admin_commerce_operations_screen.dart](../mobile/airmius_mobile/lib/screens/admin_commerce_operations_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/admin_detail_screen.dart](../mobile/airmius_mobile/lib/screens/admin_detail_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/admin_finance_billing_center_screen.dart](../mobile/airmius_mobile/lib/screens/admin_finance_billing_center_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/admin_finance_contract_suite_screen.dart](../mobile/airmius_mobile/lib/screens/admin_finance_contract_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/admin_insights_screen.dart](../mobile/airmius_mobile/lib/screens/admin_insights_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/admin_mail_center_screen.dart](../mobile/airmius_mobile/lib/screens/admin_mail_center_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/admin_media_screen.dart](../mobile/airmius_mobile/lib/screens/admin_media_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/admin_members_screen.dart](../mobile/airmius_mobile/lib/screens/admin_members_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/admin_moderation_audit_queue_suite_screen.dart](../mobile/airmius_mobile/lib/screens/admin_moderation_audit_queue_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/admin_platform_settings_screen.dart](../mobile/airmius_mobile/lib/screens/admin_platform_settings_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/admin_provider_contracts_screen.dart](../mobile/airmius_mobile/lib/screens/admin_provider_contracts_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/admin_subscription_outfit_center_screen.dart](../mobile/airmius_mobile/lib/screens/admin_subscription_outfit_center_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/admin_support_ticket_screen.dart](../mobile/airmius_mobile/lib/screens/admin_support_ticket_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/admin_trainer_applications_screen.dart](../mobile/airmius_mobile/lib/screens/admin_trainer_applications_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/admin_user_management_screen.dart](../mobile/airmius_mobile/lib/screens/admin_user_management_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/admin_workspace_screen.dart](../mobile/airmius_mobile/lib/screens/admin_workspace_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/airmius_design_system_screen.dart](../mobile/airmius_mobile/lib/screens/airmius_design_system_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/analytics_chart_dashboard_parity_suite_screen.dart](../mobile/airmius_mobile/lib/screens/analytics_chart_dashboard_parity_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/analytics_reporting_kpi_suite_screen.dart](../mobile/airmius_mobile/lib/screens/analytics_reporting_kpi_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/api_binding_readiness_suite_screen.dart](../mobile/airmius_mobile/lib/screens/api_binding_readiness_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/api_connection_screen.dart](../mobile/airmius_mobile/lib/screens/api_connection_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/api_data_model_repository_suite_screen.dart](../mobile/airmius_mobile/lib/screens/api_data_model_repository_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/api_repository_binding_suite_screen.dart](../mobile/airmius_mobile/lib/screens/api_repository_binding_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/api_state_empty_error_suite_screen.dart](../mobile/airmius_mobile/lib/screens/api_state_empty_error_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/api_token_manager_screen.dart](../mobile/airmius_mobile/lib/screens/api_token_manager_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/app_onboarding_permission_suite_screen.dart](../mobile/airmius_mobile/lib/screens/app_onboarding_permission_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/app_onboarding_screen.dart](../mobile/airmius_mobile/lib/screens/app_onboarding_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/app_shell_localization_quality_suite_screen.dart](../mobile/airmius_mobile/lib/screens/app_shell_localization_quality_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/application_screen.dart](../mobile/airmius_mobile/lib/screens/application_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/audit_activity_timeline_suite_screen.dart](../mobile/airmius_mobile/lib/screens/audit_activity_timeline_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/auth_account_access_center_screen.dart](../mobile/airmius_mobile/lib/screens/auth_account_access_center_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/auth_api_entry_suite_screen.dart](../mobile/airmius_mobile/lib/screens/auth_api_entry_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/auth_flows_screen.dart](../mobile/airmius_mobile/lib/screens/auth_flows_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/auth_guard_status_suite_screen.dart](../mobile/airmius_mobile/lib/screens/auth_guard_status_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/auth_recovery_security_screen.dart](../mobile/airmius_mobile/lib/screens/auth_recovery_security_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/auth_state_token_store_suite_screen.dart](../mobile/airmius_mobile/lib/screens/auth_state_token_store_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/availability_absence_planning_suite_screen.dart](../mobile/airmius_mobile/lib/screens/availability_absence_planning_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/badge_detail_screen.dart](../mobile/airmius_mobile/lib/screens/badge_detail_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/badges_center_screen.dart](../mobile/airmius_mobile/lib/screens/badges_center_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/billing_detail_screen.dart](../mobile/airmius_mobile/lib/screens/billing_detail_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/billing_operations_screen.dart](../mobile/airmius_mobile/lib/screens/billing_operations_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/blog_media_center_screen.dart](../mobile/airmius_mobile/lib/screens/blog_media_center_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/blog_media_detail_screen.dart](../mobile/airmius_mobile/lib/screens/blog_media_detail_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/brand_theme_token_parity_suite_screen.dart](../mobile/airmius_mobile/lib/screens/brand_theme_token_parity_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/calendar_event_rsvp_suite_screen.dart](../mobile/airmius_mobile/lib/screens/calendar_event_rsvp_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/carpool_center_screen.dart](../mobile/airmius_mobile/lib/screens/carpool_center_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/carpool_detail_screen.dart](../mobile/airmius_mobile/lib/screens/carpool_detail_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/certificate_verification_screen.dart](../mobile/airmius_mobile/lib/screens/certificate_verification_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/challenges_screen.dart](../mobile/airmius_mobile/lib/screens/challenges_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/chat_detail_screen.dart](../mobile/airmius_mobile/lib/screens/chat_detail_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/checkout_screen.dart](../mobile/airmius_mobile/lib/screens/checkout_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/checkout_status_screen.dart](../mobile/airmius_mobile/lib/screens/checkout_status_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/club_access_management_screen.dart](../mobile/airmius_mobile/lib/screens/club_access_management_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/club_announcement_screen.dart](../mobile/airmius_mobile/lib/screens/club_announcement_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/club_application_inbox_suite_screen.dart](../mobile/airmius_mobile/lib/screens/club_application_inbox_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/club_asset_inventory_checkout_suite_screen.dart](../mobile/airmius_mobile/lib/screens/club_asset_inventory_checkout_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/club_cockpit_screen.dart](../mobile/airmius_mobile/lib/screens/club_cockpit_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/club_communication_center_screen.dart](../mobile/airmius_mobile/lib/screens/club_communication_center_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/club_contribution_rules_screen.dart](../mobile/airmius_mobile/lib/screens/club_contribution_rules_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/club_deletion_screen.dart](../mobile/airmius_mobile/lib/screens/club_deletion_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/club_document_consent_file_manager_suite_screen.dart](../mobile/airmius_mobile/lib/screens/club_document_consent_file_manager_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/club_document_upload_manager_screen.dart](../mobile/airmius_mobile/lib/screens/club_document_upload_manager_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/club_dues_payment_rules_suite_screen.dart](../mobile/airmius_mobile/lib/screens/club_dues_payment_rules_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/club_event_attendance_screen.dart](../mobile/airmius_mobile/lib/screens/club_event_attendance_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/club_external_invitation_response_screen.dart](../mobile/airmius_mobile/lib/screens/club_external_invitation_response_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/club_finance_cockpit_screen.dart](../mobile/airmius_mobile/lib/screens/club_finance_cockpit_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/club_governance_screen.dart](../mobile/airmius_mobile/lib/screens/club_governance_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/club_inventory_qr_scanner_screen.dart](../mobile/airmius_mobile/lib/screens/club_inventory_qr_scanner_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/club_member_directory_screen.dart](../mobile/airmius_mobile/lib/screens/club_member_directory_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/club_member_finance_screen.dart](../mobile/airmius_mobile/lib/screens/club_member_finance_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/club_member_import_export_suite_screen.dart](../mobile/airmius_mobile/lib/screens/club_member_import_export_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/club_member_onboarding_acceptance_suite_screen.dart](../mobile/airmius_mobile/lib/screens/club_member_onboarding_acceptance_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/club_membership_admin_screen.dart](../mobile/airmius_mobile/lib/screens/club_membership_admin_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/club_membership_form_builder_screen.dart](../mobile/airmius_mobile/lib/screens/club_membership_form_builder_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/club_membership_lifecycle_suite_screen.dart](../mobile/airmius_mobile/lib/screens/club_membership_lifecycle_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/club_membership_management_screen.dart](../mobile/airmius_mobile/lib/screens/club_membership_management_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/club_membership_prospects_screen.dart](../mobile/airmius_mobile/lib/screens/club_membership_prospects_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/club_membership_requirements_builder_screen.dart](../mobile/airmius_mobile/lib/screens/club_membership_requirements_builder_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/club_metadata_screen.dart](../mobile/airmius_mobile/lib/screens/club_metadata_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/club_metadata_subject_screen.dart](../mobile/airmius_mobile/lib/screens/club_metadata_subject_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/club_organization_screen.dart](../mobile/airmius_mobile/lib/screens/club_organization_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/club_policy_documents_screen.dart](../mobile/airmius_mobile/lib/screens/club_policy_documents_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/club_profile_editor_screen.dart](../mobile/airmius_mobile/lib/screens/club_profile_editor_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/club_public_profile_preview_suite_screen.dart](../mobile/airmius_mobile/lib/screens/club_public_profile_preview_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/club_reports_analytics_screen.dart](../mobile/airmius_mobile/lib/screens/club_reports_analytics_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/club_request_inbox_screen.dart](../mobile/airmius_mobile/lib/screens/club_request_inbox_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/club_role_permissions_screen.dart](../mobile/airmius_mobile/lib/screens/club_role_permissions_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/club_sepa_batches_screen.dart](../mobile/airmius_mobile/lib/screens/club_sepa_batches_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/club_sepa_fee_correction_screen.dart](../mobile/airmius_mobile/lib/screens/club_sepa_fee_correction_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/club_sepa_fee_recharge_screen.dart](../mobile/airmius_mobile/lib/screens/club_sepa_fee_recharge_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/club_sepa_fee_screen.dart](../mobile/airmius_mobile/lib/screens/club_sepa_fee_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/club_sepa_return_import.dart](../mobile/airmius_mobile/lib/screens/club_sepa_return_import.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/club_setup_onboarding_screen.dart](../mobile/airmius_mobile/lib/screens/club_setup_onboarding_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/club_survey_poll_voting_suite_screen.dart](../mobile/airmius_mobile/lib/screens/club_survey_poll_voting_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/club_survey_screen.dart](../mobile/airmius_mobile/lib/screens/club_survey_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/club_tasks_screen.dart](../mobile/airmius_mobile/lib/screens/club_tasks_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/club_team_admin_screen.dart](../mobile/airmius_mobile/lib/screens/club_team_admin_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/club_visibility_rules_suite_screen.dart](../mobile/airmius_mobile/lib/screens/club_visibility_rules_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/club_visibility_settings_screen.dart](../mobile/airmius_mobile/lib/screens/club_visibility_settings_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/club_year_periods_screen.dart](../mobile/airmius_mobile/lib/screens/club_year_periods_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/clubs_screen.dart](../mobile/airmius_mobile/lib/screens/clubs_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/coach_action_detail_screen.dart](../mobile/airmius_mobile/lib/screens/coach_action_detail_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/commerce_center_screen.dart](../mobile/airmius_mobile/lib/screens/commerce_center_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/commerce_detail_screen.dart](../mobile/airmius_mobile/lib/screens/commerce_detail_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/commerce_operations_screen.dart](../mobile/airmius_mobile/lib/screens/commerce_operations_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/commerce_subscription_outfit_suite_screen.dart](../mobile/airmius_mobile/lib/screens/commerce_subscription_outfit_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/communication_files_notifications_suite_screen.dart](../mobile/airmius_mobile/lib/screens/communication_files_notifications_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/community_center_screen.dart](../mobile/airmius_mobile/lib/screens/community_center_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/consent_signature_versioning_suite_screen.dart](../mobile/airmius_mobile/lib/screens/consent_signature_versioning_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/content_blog_editorial_suite_screen.dart](../mobile/airmius_mobile/lib/screens/content_blog_editorial_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/content_operations_screen.dart](../mobile/airmius_mobile/lib/screens/content_operations_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/content_publishing_cms_suite_screen.dart](../mobile/airmius_mobile/lib/screens/content_publishing_cms_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/conversations_center_screen.dart](../mobile/airmius_mobile/lib/screens/conversations_center_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/cross_module_approval_workflow_suite_screen.dart](../mobile/airmius_mobile/lib/screens/cross_module_approval_workflow_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/daily_flow_screen.dart](../mobile/airmius_mobile/lib/screens/daily_flow_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/dashboard_action_flows_screen.dart](../mobile/airmius_mobile/lib/screens/dashboard_action_flows_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/dashboard_screen.dart](../mobile/airmius_mobile/lib/screens/dashboard_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/data_rights_request_screen.dart](../mobile/airmius_mobile/lib/screens/data_rights_request_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/deep_link_route_resolver_suite_screen.dart](../mobile/airmius_mobile/lib/screens/deep_link_route_resolver_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/device_permission_privacy_parity_suite_screen.dart](../mobile/airmius_mobile/lib/screens/device_permission_privacy_parity_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/digital_member_card_checkin_suite_screen.dart](../mobile/airmius_mobile/lib/screens/digital_member_card_checkin_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/draft_autosave_recovery_suite_screen.dart](../mobile/airmius_mobile/lib/screens/draft_autosave_recovery_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/edit_form_screen.dart](../mobile/airmius_mobile/lib/screens/edit_form_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/editorial_management_screen.dart](../mobile/airmius_mobile/lib/screens/editorial_management_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/email_verification_screen.dart](../mobile/airmius_mobile/lib/screens/email_verification_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/end_to_end_journey_parity_suite_screen.dart](../mobile/airmius_mobile/lib/screens/end_to_end_journey_parity_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/event_admin_detail_screen.dart](../mobile/airmius_mobile/lib/screens/event_admin_detail_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/event_management_screen.dart](../mobile/airmius_mobile/lib/screens/event_management_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/exact_page_flow_parity_suite_screen.dart](../mobile/airmius_mobile/lib/screens/exact_page_flow_parity_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/exercise_library_screen.dart](../mobile/airmius_mobile/lib/screens/exercise_library_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/facility_booking_resource_scheduler_suite_screen.dart](../mobile/airmius_mobile/lib/screens/facility_booking_resource_scheduler_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/feed_center_screen.dart](../mobile/airmius_mobile/lib/screens/feed_center_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/feed_community_composer_suite_screen.dart](../mobile/airmius_mobile/lib/screens/feed_community_composer_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/feed_community_social_suite_screen.dart](../mobile/airmius_mobile/lib/screens/feed_community_social_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/feed_post_detail_screen.dart](../mobile/airmius_mobile/lib/screens/feed_post_detail_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/file_manager_screen.dart](../mobile/airmius_mobile/lib/screens/file_manager_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/file_operations_screen.dart](../mobile/airmius_mobile/lib/screens/file_operations_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/file_preview_screen.dart](../mobile/airmius_mobile/lib/screens/file_preview_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/finance_billing_member_payment_suite_screen.dart](../mobile/airmius_mobile/lib/screens/finance_billing_member_payment_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/finance_invoice_receipt_center_suite_screen.dart](../mobile/airmius_mobile/lib/screens/finance_invoice_receipt_center_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/finance_record_detail_screen.dart](../mobile/airmius_mobile/lib/screens/finance_record_detail_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/footer_navigation_settings_screen.dart](../mobile/airmius_mobile/lib/screens/footer_navigation_settings_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/free_run_screen.dart](../mobile/airmius_mobile/lib/screens/free_run_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/friend_invitation_response_screen.dart](../mobile/airmius_mobile/lib/screens/friend_invitation_response_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/friends_social_graph_screen.dart](../mobile/airmius_mobile/lib/screens/friends_social_graph_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/gamification_badge_achievement_suite_screen.dart](../mobile/airmius_mobile/lib/screens/gamification_badge_achievement_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/gamification_badges_roles_suite_screen.dart](../mobile/airmius_mobile/lib/screens/gamification_badges_roles_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/gamification_operations_screen.dart](../mobile/airmius_mobile/lib/screens/gamification_operations_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/gamification_rule_detail_screen.dart](../mobile/airmius_mobile/lib/screens/gamification_rule_detail_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/gamification_rules_screen.dart](../mobile/airmius_mobile/lib/screens/gamification_rules_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/global_search_directory_suite_screen.dart](../mobile/airmius_mobile/lib/screens/global_search_directory_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/global_search_screen.dart](../mobile/airmius_mobile/lib/screens/global_search_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/guardian_access_portal_screen.dart](../mobile/airmius_mobile/lib/screens/guardian_access_portal_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/guardian_center_screen.dart](../mobile/airmius_mobile/lib/screens/guardian_center_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/guardian_child_overview_screen.dart](../mobile/airmius_mobile/lib/screens/guardian_child_overview_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/guardian_consent_detail_screen.dart](../mobile/airmius_mobile/lib/screens/guardian_consent_detail_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/guardian_family_consent_screen.dart](../mobile/airmius_mobile/lib/screens/guardian_family_consent_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/guest_ad_agency_screen.dart](../mobile/airmius_mobile/lib/screens/guest_ad_agency_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/guest_blog_content_screen.dart](../mobile/airmius_mobile/lib/screens/guest_blog_content_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/guest_club_directory_screen.dart](../mobile/airmius_mobile/lib/screens/guest_club_directory_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/guest_jobs_careers_screen.dart](../mobile/airmius_mobile/lib/screens/guest_jobs_careers_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/guest_learning_certificate_screen.dart](../mobile/airmius_mobile/lib/screens/guest_learning_certificate_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/guest_marketplace_buyer_screen.dart](../mobile/airmius_mobile/lib/screens/guest_marketplace_buyer_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/guest_marketplace_flow_screen.dart](../mobile/airmius_mobile/lib/screens/guest_marketplace_flow_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/guest_marketplace_parity_screen.dart](../mobile/airmius_mobile/lib/screens/guest_marketplace_parity_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/guest_portal_screen.dart](../mobile/airmius_mobile/lib/screens/guest_portal_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/guest_pricing_plans_screen.dart](../mobile/airmius_mobile/lib/screens/guest_pricing_plans_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/guest_sponsors_gamification_screen.dart](../mobile/airmius_mobile/lib/screens/guest_sponsors_gamification_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/health_incident_report_suite_screen.dart](../mobile/airmius_mobile/lib/screens/health_incident_report_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/http_transport_release_suite_screen.dart](../mobile/airmius_mobile/lib/screens/http_transport_release_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/input_keyboard_accessibility_parity_suite_screen.dart](../mobile/airmius_mobile/lib/screens/input_keyboard_accessibility_parity_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/integration_webhook_provider_suite_screen.dart](../mobile/airmius_mobile/lib/screens/integration_webhook_provider_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/invitation_access_link_suite_screen.dart](../mobile/airmius_mobile/lib/screens/invitation_access_link_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/laravel_api_binding_progress_suite_screen.dart](../mobile/airmius_mobile/lib/screens/laravel_api_binding_progress_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/laravel_api_endpoint_mapping_suite_screen.dart](../mobile/airmius_mobile/lib/screens/laravel_api_endpoint_mapping_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/learning_course_progress_certificate_suite_screen.dart](../mobile/airmius_mobile/lib/screens/learning_course_progress_certificate_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/learning_operations_screen.dart](../mobile/airmius_mobile/lib/screens/learning_operations_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/learning_screen.dart](../mobile/airmius_mobile/lib/screens/learning_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/learning_studio_course_suite_screen.dart](../mobile/airmius_mobile/lib/screens/learning_studio_course_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/legal_document_screen.dart](../mobile/airmius_mobile/lib/screens/legal_document_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/legal_policy_rollout_suite_screen.dart](../mobile/airmius_mobile/lib/screens/legal_policy_rollout_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/legal_status_center_screen.dart](../mobile/airmius_mobile/lib/screens/legal_status_center_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/legal_support_operations_screen.dart](../mobile/airmius_mobile/lib/screens/legal_support_operations_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/lesson_detail_screen.dart](../mobile/airmius_mobile/lib/screens/lesson_detail_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/localization_center_screen.dart](../mobile/airmius_mobile/lib/screens/localization_center_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/localization_rtl_format_parity_suite_screen.dart](../mobile/airmius_mobile/lib/screens/localization_rtl_format_parity_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/location_map_facility_suite_screen.dart](../mobile/airmius_mobile/lib/screens/location_map_facility_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/login_screen.dart](../mobile/airmius_mobile/lib/screens/login_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/map_location_route_parity_suite_screen.dart](../mobile/airmius_mobile/lib/screens/map_location_route_parity_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/marketplace_operations_screen.dart](../mobile/airmius_mobile/lib/screens/marketplace_operations_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/marketplace_order_fulfillment_suite_screen.dart](../mobile/airmius_mobile/lib/screens/marketplace_order_fulfillment_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/marketplace_screen.dart](../mobile/airmius_mobile/lib/screens/marketplace_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/maturity_center_screen.dart](../mobile/airmius_mobile/lib/screens/maturity_center_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/maturity_media_guidelines_screen.dart](../mobile/airmius_mobile/lib/screens/maturity_media_guidelines_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/meal_detail_screen.dart](../mobile/airmius_mobile/lib/screens/meal_detail_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/media_guidelines_screen.dart](../mobile/airmius_mobile/lib/screens/media_guidelines_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/media_upload_attachment_parity_suite_screen.dart](../mobile/airmius_mobile/lib/screens/media_upload_attachment_parity_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/meeting_minutes_decision_log_suite_screen.dart](../mobile/airmius_mobile/lib/screens/meeting_minutes_decision_log_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/member_card_qr_scanner_screen.dart](../mobile/airmius_mobile/lib/screens/member_card_qr_scanner_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/member_card_screen.dart](../mobile/airmius_mobile/lib/screens/member_card_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/member_feedback_satisfaction_suite_screen.dart](../mobile/airmius_mobile/lib/screens/member_feedback_satisfaction_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/member_self_service_center_suite_screen.dart](../mobile/airmius_mobile/lib/screens/member_self_service_center_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/membership_application_form_screen.dart](../mobile/airmius_mobile/lib/screens/membership_application_form_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/membership_operations_screen.dart](../mobile/airmius_mobile/lib/screens/membership_operations_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/membership_request_status_screen.dart](../mobile/airmius_mobile/lib/screens/membership_request_status_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/messaging_conversation_center_suite_screen.dart](../mobile/airmius_mobile/lib/screens/messaging_conversation_center_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/mobile_form_validation_schema_suite_screen.dart](../mobile/airmius_mobile/lib/screens/mobile_form_validation_schema_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/mobile_state_form_error_suite_screen.dart](../mobile/airmius_mobile/lib/screens/mobile_state_form_error_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/mobile_table_action_parity_suite_screen.dart](../mobile/airmius_mobile/lib/screens/mobile_table_action_parity_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/mobile_visual_parity_progress_audit_suite_screen.dart](../mobile/airmius_mobile/lib/screens/mobile_visual_parity_progress_audit_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/mobile_web_fidelity_accessibility_suite_screen.dart](../mobile/airmius_mobile/lib/screens/mobile_web_fidelity_accessibility_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/modal_sheet_overlay_parity_suite_screen.dart](../mobile/airmius_mobile/lib/screens/modal_sheet_overlay_parity_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/module_item_detail_screen.dart](../mobile/airmius_mobile/lib/screens/module_item_detail_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/module_screen.dart](../mobile/airmius_mobile/lib/screens/module_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/module_sections.dart](../mobile/airmius_mobile/lib/screens/module_sections.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/native_store_release_assets_suite_screen.dart](../mobile/airmius_mobile/lib/screens/native_store_release_assets_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/navigation_menu_parity_suite_screen.dart](../mobile/airmius_mobile/lib/screens/navigation_menu_parity_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/new_conversation_screen.dart](../mobile/airmius_mobile/lib/screens/new_conversation_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/notification_chat_operations_screen.dart](../mobile/airmius_mobile/lib/screens/notification_chat_operations_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/notification_delivery_preferences_suite_screen.dart](../mobile/airmius_mobile/lib/screens/notification_delivery_preferences_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/notification_detail_screen.dart](../mobile/airmius_mobile/lib/screens/notification_detail_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/notification_preferences_screen.dart](../mobile/airmius_mobile/lib/screens/notification_preferences_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/notifications_center_screen.dart](../mobile/airmius_mobile/lib/screens/notifications_center_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/nutrition_center_screen.dart](../mobile/airmius_mobile/lib/screens/nutrition_center_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/offline_sync_cache_parity_suite_screen.dart](../mobile/airmius_mobile/lib/screens/offline_sync_cache_parity_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/operations_hub_screen.dart](../mobile/airmius_mobile/lib/screens/operations_hub_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/outfit_delivery_detail_screen.dart](../mobile/airmius_mobile/lib/screens/outfit_delivery_detail_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/outfit_operations_screen.dart](../mobile/airmius_mobile/lib/screens/outfit_operations_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/outfit_subscription_center_screen.dart](../mobile/airmius_mobile/lib/screens/outfit_subscription_center_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/password_recovery_screen.dart](../mobile/airmius_mobile/lib/screens/password_recovery_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/permission_onboarding_screen.dart](../mobile/airmius_mobile/lib/screens/permission_onboarding_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/platform_admin_screen.dart](../mobile/airmius_mobile/lib/screens/platform_admin_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/platform_operations_screen.dart](../mobile/airmius_mobile/lib/screens/platform_operations_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/privacy_consent_center_screen.dart](../mobile/airmius_mobile/lib/screens/privacy_consent_center_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/privacy_data_erasure_sheet.dart](../mobile/airmius_mobile/lib/screens/privacy_data_erasure_sheet.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/product_detail_screen.dart](../mobile/airmius_mobile/lib/screens/product_detail_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/profile_account_forms_screen.dart](../mobile/airmius_mobile/lib/screens/profile_account_forms_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/profile_completion_gate_screen.dart](../mobile/airmius_mobile/lib/screens/profile_completion_gate_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/profile_privacy_visibility_suite_screen.dart](../mobile/airmius_mobile/lib/screens/profile_privacy_visibility_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/profile_screen.dart](../mobile/airmius_mobile/lib/screens/profile_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/profile_security_center_screen.dart](../mobile/airmius_mobile/lib/screens/profile_security_center_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/profile_skill_recommendation_screen.dart](../mobile/airmius_mobile/lib/screens/profile_skill_recommendation_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/public_blog_reader_screen.dart](../mobile/airmius_mobile/lib/screens/public_blog_reader_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/public_detail_screen.dart](../mobile/airmius_mobile/lib/screens/public_detail_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/public_growth_guest_pages_screen.dart](../mobile/airmius_mobile/lib/screens/public_growth_guest_pages_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/public_growth_hub_screen.dart](../mobile/airmius_mobile/lib/screens/public_growth_hub_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/public_growth_operations_screen.dart](../mobile/airmius_mobile/lib/screens/public_growth_operations_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/public_interest_ads_sponsor_suite_screen.dart](../mobile/airmius_mobile/lib/screens/public_interest_ads_sponsor_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/public_interest_screen.dart](../mobile/airmius_mobile/lib/screens/public_interest_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/public_location_submission_screen.dart](../mobile/airmius_mobile/lib/screens/public_location_submission_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/public_system_pages_screen.dart](../mobile/airmius_mobile/lib/screens/public_system_pages_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/public_top_content_screen.dart](../mobile/airmius_mobile/lib/screens/public_top_content_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/push_notification_deeplink_parity_suite_screen.dart](../mobile/airmius_mobile/lib/screens/push_notification_deeplink_parity_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/recruiting_pipeline_screen.dart](../mobile/airmius_mobile/lib/screens/recruiting_pipeline_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/registration_onboarding_screen.dart](../mobile/airmius_mobile/lib/screens/registration_onboarding_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/release_candidate_gate_register_screen.dart](../mobile/airmius_mobile/lib/screens/release_candidate_gate_register_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/release_evidence_center_screen.dart](../mobile/airmius_mobile/lib/screens/release_evidence_center_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/release_readiness_screen.dart](../mobile/airmius_mobile/lib/screens/release_readiness_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/report_moderation_center_screen.dart](../mobile/airmius_mobile/lib/screens/report_moderation_center_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/rides_carpool_planner_screen.dart](../mobile/airmius_mobile/lib/screens/rides_carpool_planner_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/role_based_app_experience_suite_screen.dart](../mobile/airmius_mobile/lib/screens/role_based_app_experience_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/role_home_dashboard_widget_suite_screen.dart](../mobile/airmius_mobile/lib/screens/role_home_dashboard_widget_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/role_permission_detail_screen.dart](../mobile/airmius_mobile/lib/screens/role_permission_detail_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/role_workspace_switcher_suite_screen.dart](../mobile/airmius_mobile/lib/screens/role_workspace_switcher_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/roles_permissions_screen.dart](../mobile/airmius_mobile/lib/screens/roles_permissions_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/route_detail_screen.dart](../mobile/airmius_mobile/lib/screens/route_detail_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/safety_community_operations_screen.dart](../mobile/airmius_mobile/lib/screens/safety_community_operations_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/saved_view_search_alert_suite_screen.dart](../mobile/airmius_mobile/lib/screens/saved_view_search_alert_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/search_directory_discovery_suite_screen.dart](../mobile/airmius_mobile/lib/screens/search_directory_discovery_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/search_operations_screen.dart](../mobile/airmius_mobile/lib/screens/search_operations_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/service_container_transport_suite_screen.dart](../mobile/airmius_mobile/lib/screens/service_container_transport_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/session_security_token_parity_suite_screen.dart](../mobile/airmius_mobile/lib/screens/session_security_token_parity_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/settings_center_screen.dart](../mobile/airmius_mobile/lib/screens/settings_center_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/settings_detail_screen.dart](../mobile/airmius_mobile/lib/screens/settings_detail_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/settings_preference_screens.dart](../mobile/airmius_mobile/lib/screens/settings_preference_screens.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/shared_file_access_screen.dart](../mobile/airmius_mobile/lib/screens/shared_file_access_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/shell_screen.dart](../mobile/airmius_mobile/lib/screens/shell_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/social_operations_screen.dart](../mobile/airmius_mobile/lib/screens/social_operations_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/sponsor_ads_operations_screen.dart](../mobile/airmius_mobile/lib/screens/sponsor_ads_operations_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/sponsor_campaign_management_suite_screen.dart](../mobile/airmius_mobile/lib/screens/sponsor_campaign_management_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/sponsor_cockpit_screen.dart](../mobile/airmius_mobile/lib/screens/sponsor_cockpit_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/sponsor_detail_screen.dart](../mobile/airmius_mobile/lib/screens/sponsor_detail_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/sponsor_lead_crm_pipeline_suite_screen.dart](../mobile/airmius_mobile/lib/screens/sponsor_lead_crm_pipeline_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/sponsor_management_screen.dart](../mobile/airmius_mobile/lib/screens/sponsor_management_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/sponsors_center_screen.dart](../mobile/airmius_mobile/lib/screens/sponsors_center_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/sport_integrations_screen.dart](../mobile/airmius_mobile/lib/screens/sport_integrations_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/sport_map_center_screen.dart](../mobile/airmius_mobile/lib/screens/sport_map_center_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/sport_matching_screen.dart](../mobile/airmius_mobile/lib/screens/sport_matching_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/sport_profile_detail_screen.dart](../mobile/airmius_mobile/lib/screens/sport_profile_detail_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/sports_center_screen.dart](../mobile/airmius_mobile/lib/screens/sports_center_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/sports_operations_screen.dart](../mobile/airmius_mobile/lib/screens/sports_operations_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/sports_training_wellbeing_suite_screen.dart](../mobile/airmius_mobile/lib/screens/sports_training_wellbeing_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/state_feedback_parity_suite_screen.dart](../mobile/airmius_mobile/lib/screens/state_feedback_parity_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/store_device_qa_readiness_suite_screen.dart](../mobile/airmius_mobile/lib/screens/store_device_qa_readiness_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/store_release_configuration_suite_screen.dart](../mobile/airmius_mobile/lib/screens/store_release_configuration_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/subscription_center_screen.dart](../mobile/airmius_mobile/lib/screens/subscription_center_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/subscription_entitlement_feature_gate_suite_screen.dart](../mobile/airmius_mobile/lib/screens/subscription_entitlement_feature_gate_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/support_helpdesk_screen.dart](../mobile/airmius_mobile/lib/screens/support_helpdesk_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/support_ticket_service_center_suite_screen.dart](../mobile/airmius_mobile/lib/screens/support_ticket_service_center_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/system_admin_operations_screen.dart](../mobile/airmius_mobile/lib/screens/system_admin_operations_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/system_job_queue_monitor_suite_screen.dart](../mobile/airmius_mobile/lib/screens/system_job_queue_monitor_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/system_status_incident_center_suite_screen.dart](../mobile/airmius_mobile/lib/screens/system_status_incident_center_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/team_detail_screen.dart](../mobile/airmius_mobile/lib/screens/team_detail_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/team_invitation_response_screen.dart](../mobile/airmius_mobile/lib/screens/team_invitation_response_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/team_operations_screen.dart](../mobile/airmius_mobile/lib/screens/team_operations_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/team_roster_role_assignment_suite_screen.dart](../mobile/airmius_mobile/lib/screens/team_roster_role_assignment_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/teams_center_screen.dart](../mobile/airmius_mobile/lib/screens/teams_center_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/trainer_cockpit_screen.dart](../mobile/airmius_mobile/lib/screens/trainer_cockpit_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/training_availability_screen.dart](../mobile/airmius_mobile/lib/screens/training_availability_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/training_center_screen.dart](../mobile/airmius_mobile/lib/screens/training_center_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/training_event_detail_screen.dart](../mobile/airmius_mobile/lib/screens/training_event_detail_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/training_log_detail_screen.dart](../mobile/airmius_mobile/lib/screens/training_log_detail_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/training_operations_screen.dart](../mobile/airmius_mobile/lib/screens/training_operations_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/training_plan_detail_screen.dart](../mobile/airmius_mobile/lib/screens/training_plan_detail_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/training_plan_periodization_suite_screen.dart](../mobile/airmius_mobile/lib/screens/training_plan_periodization_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/training_plan_templates_screen.dart](../mobile/airmius_mobile/lib/screens/training_plan_templates_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/training_plans_logs_screen.dart](../mobile/airmius_mobile/lib/screens/training_plans_logs_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/training_progress_screen.dart](../mobile/airmius_mobile/lib/screens/training_progress_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/trust_moderation_admin_control_suite_screen.dart](../mobile/airmius_mobile/lib/screens/trust_moderation_admin_control_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/trust_operations_screen.dart](../mobile/airmius_mobile/lib/screens/trust_operations_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/two_factor_challenge_screen.dart](../mobile/airmius_mobile/lib/screens/two_factor_challenge_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/two_factor_security_screen.dart](../mobile/airmius_mobile/lib/screens/two_factor_security_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/ui_action_result_screen.dart](../mobile/airmius_mobile/lib/screens/ui_action_result_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/ui_coverage_screen.dart](../mobile/airmius_mobile/lib/screens/ui_coverage_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/updates_center_screen.dart](../mobile/airmius_mobile/lib/screens/updates_center_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/user_admin_detail_screen.dart](../mobile/airmius_mobile/lib/screens/user_admin_detail_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/user_profile_detail_screen.dart](../mobile/airmius_mobile/lib/screens/user_profile_detail_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/users_center_screen.dart](../mobile/airmius_mobile/lib/screens/users_center_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/volunteer_shift_task_planner_suite_screen.dart](../mobile/airmius_mobile/lib/screens/volunteer_shift_task_planner_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/web_app_full_conversion_control_screen.dart](../mobile/airmius_mobile/lib/screens/web_app_full_conversion_control_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/web_app_module_completion_suite_screen.dart](../mobile/airmius_mobile/lib/screens/web_app_module_completion_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/web_parity_release_audit_suite_screen.dart](../mobile/airmius_mobile/lib/screens/web_parity_release_audit_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/web_route_parity_matrix_suite_screen.dart](../mobile/airmius_mobile/lib/screens/web_route_parity_matrix_suite_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/web_route_parity_screen.dart](../mobile/airmius_mobile/lib/screens/web_route_parity_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/wellbeing_operations_screen.dart](../mobile/airmius_mobile/lib/screens/wellbeing_operations_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/workspace_center_screen.dart](../mobile/airmius_mobile/lib/screens/workspace_center_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/workspace_collaboration_screen.dart](../mobile/airmius_mobile/lib/screens/workspace_collaboration_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/workspace_detail_screen.dart](../mobile/airmius_mobile/lib/screens/workspace_detail_screen.dart) | Offen |
| [ ] | [mobile/airmius_mobile/lib/screens/workspace_operations_center_screen.dart](../mobile/airmius_mobile/lib/screens/workspace_operations_center_screen.dart) | Offen |

## Backend-Testdateien

Vorhandene Dateien sind nur Hinweise auf automatisierte Abdeckung. Jeden relevanten Test tatsächlich ausführen und sein Ergebnis dem Fachfall zuordnen; Dateiexistenz und erfolgreiche statische Quellenprüfung sind kein End-to-End-Nachweis.

| Offen | Datei | Fachfall / Ergebnis |
| --- | --- | --- |
| [ ] | [tests/Feature/AccessibilitySmokeTest.php](../tests/Feature/AccessibilitySmokeTest.php) | Offen |
| [ ] | [tests/Feature/AdminAreaSecurityTest.php](../tests/Feature/AdminAreaSecurityTest.php) | Offen |
| [ ] | [tests/Feature/AdminClubManagementTest.php](../tests/Feature/AdminClubManagementTest.php) | Offen |
| [ ] | [tests/Feature/AdminCreatedUserProvisioningTest.php](../tests/Feature/AdminCreatedUserProvisioningTest.php) | Offen |
| [ ] | [tests/Feature/AdminInvoiceManagementTest.php](../tests/Feature/AdminInvoiceManagementTest.php) | Offen |
| [ ] | [tests/Feature/AdminOperationsCenterTest.php](../tests/Feature/AdminOperationsCenterTest.php) | Offen |
| [ ] | [tests/Feature/AdminSubscriptionManagementTest.php](../tests/Feature/AdminSubscriptionManagementTest.php) | Offen |
| [ ] | [tests/Feature/AgencyAdsOptimizationContractTest.php](../tests/Feature/AgencyAdsOptimizationContractTest.php) | Offen |
| [ ] | [tests/Feature/AiCommunicationDraftApiTest.php](../tests/Feature/AiCommunicationDraftApiTest.php) | Offen |
| [ ] | [tests/Feature/AiDocumentAssistantServiceTest.php](../tests/Feature/AiDocumentAssistantServiceTest.php) | Offen |
| [ ] | [tests/Feature/AiGovernanceCatalogTest.php](../tests/Feature/AiGovernanceCatalogTest.php) | Offen |
| [ ] | [tests/Feature/ApiPaginationContractTest.php](../tests/Feature/ApiPaginationContractTest.php) | Offen |
| [ ] | [tests/Feature/ApiRateLimitContractTest.php](../tests/Feature/ApiRateLimitContractTest.php) | Offen |
| [ ] | [tests/Feature/ApiTokenPermissionsTest.php](../tests/Feature/ApiTokenPermissionsTest.php) | Offen |
| [ ] | [tests/Feature/AthleteMotivationApiTest.php](../tests/Feature/AthleteMotivationApiTest.php) | Offen |
| [ ] | [tests/Feature/AuthenticationTest.php](../tests/Feature/AuthenticationTest.php) | Offen |
| [ ] | [tests/Feature/BlogPublicTest.php](../tests/Feature/BlogPublicTest.php) | Offen |
| [ ] | [tests/Feature/BlogTranslationWorkflowTest.php](../tests/Feature/BlogTranslationWorkflowTest.php) | Offen |
| [ ] | [tests/Feature/BroadcastChannelAuthorizationTest.php](../tests/Feature/BroadcastChannelAuthorizationTest.php) | Offen |
| [ ] | [tests/Feature/BrowserSessionsTest.php](../tests/Feature/BrowserSessionsTest.php) | Offen |
| [ ] | [tests/Feature/BulkChangeSafetyContractTest.php](../tests/Feature/BulkChangeSafetyContractTest.php) | Offen |
| [ ] | [tests/Feature/CapacityBookingRuleServiceTest.php](../tests/Feature/CapacityBookingRuleServiceTest.php) | Offen |
| [ ] | [tests/Feature/ChallengeFeatureTest.php](../tests/Feature/ChallengeFeatureTest.php) | Offen |
| [ ] | [tests/Feature/ChatSecurityTest.php](../tests/Feature/ChatSecurityTest.php) | Offen |
| [ ] | [tests/Feature/CheckAiProviderTokensCommandTest.php](../tests/Feature/CheckAiProviderTokensCommandTest.php) | Offen |
| [ ] | [tests/Feature/CheckoutIdempotencySecurityTest.php](../tests/Feature/CheckoutIdempotencySecurityTest.php) | Offen |
| [ ] | [tests/Feature/ClubAccessHandoverTest.php](../tests/Feature/ClubAccessHandoverTest.php) | Offen |
| [ ] | [tests/Feature/ClubAnnouncementApiTest.php](../tests/Feature/ClubAnnouncementApiTest.php) | Offen |
| [ ] | [tests/Feature/ClubAuditLogTest.php](../tests/Feature/ClubAuditLogTest.php) | Offen |
| [ ] | [tests/Feature/ClubBrandingSettingsTest.php](../tests/Feature/ClubBrandingSettingsTest.php) | Offen |
| [ ] | [tests/Feature/ClubBudgetHierarchyTest.php](../tests/Feature/ClubBudgetHierarchyTest.php) | Offen |
| [ ] | [tests/Feature/ClubCockpitGovernanceTest.php](../tests/Feature/ClubCockpitGovernanceTest.php) | Offen |
| [ ] | [tests/Feature/ClubContactMasterDataTest.php](../tests/Feature/ClubContactMasterDataTest.php) | Offen |
| [ ] | [tests/Feature/ClubContextSwitchContractTest.php](../tests/Feature/ClubContextSwitchContractTest.php) | Offen |
| [ ] | [tests/Feature/ClubContinuityReadinessCatalogTest.php](../tests/Feature/ClubContinuityReadinessCatalogTest.php) | Offen |
| [ ] | [tests/Feature/ClubContributionComponentAccountingTest.php](../tests/Feature/ClubContributionComponentAccountingTest.php) | Offen |
| [ ] | [tests/Feature/ClubContributionProrationTest.php](../tests/Feature/ClubContributionProrationTest.php) | Offen |
| [ ] | [tests/Feature/ClubContributionRulesTest.php](../tests/Feature/ClubContributionRulesTest.php) | Offen |
| [ ] | [tests/Feature/ClubDeletionLifecycleTest.php](../tests/Feature/ClubDeletionLifecycleTest.php) | Offen |
| [ ] | [tests/Feature/ClubDonationSeparationTest.php](../tests/Feature/ClubDonationSeparationTest.php) | Offen |
| [ ] | [tests/Feature/ClubDunningTest.php](../tests/Feature/ClubDunningTest.php) | Offen |
| [ ] | [tests/Feature/ClubFinanceFacilitiesReadinessCatalogTest.php](../tests/Feature/ClubFinanceFacilitiesReadinessCatalogTest.php) | Offen |
| [ ] | [tests/Feature/ClubFundingProgramStatusMachineTest.php](../tests/Feature/ClubFundingProgramStatusMachineTest.php) | Offen |
| [ ] | [tests/Feature/ClubGovernanceMeetingTest.php](../tests/Feature/ClubGovernanceMeetingTest.php) | Offen |
| [ ] | [tests/Feature/ClubGovernanceStructureTest.php](../tests/Feature/ClubGovernanceStructureTest.php) | Offen |
| [ ] | [tests/Feature/ClubGuardianRelationshipManagementTest.php](../tests/Feature/ClubGuardianRelationshipManagementTest.php) | Offen |
| [ ] | [tests/Feature/ClubImageApiTest.php](../tests/Feature/ClubImageApiTest.php) | Offen |
| [ ] | [tests/Feature/ClubInventoryApiTest.php](../tests/Feature/ClubInventoryApiTest.php) | Offen |
| [ ] | [tests/Feature/ClubInvoicePaymentStatusTest.php](../tests/Feature/ClubInvoicePaymentStatusTest.php) | Offen |
| [ ] | [tests/Feature/ClubInvoiceReconciliationAuditTest.php](../tests/Feature/ClubInvoiceReconciliationAuditTest.php) | Offen |
| [ ] | [tests/Feature/ClubLegalMasterDataTest.php](../tests/Feature/ClubLegalMasterDataTest.php) | Offen |
| [ ] | [tests/Feature/ClubMasterDataChangeRequestTest.php](../tests/Feature/ClubMasterDataChangeRequestTest.php) | Offen |
| [ ] | [tests/Feature/ClubMemberCardTest.php](../tests/Feature/ClubMemberCardTest.php) | Offen |
| [ ] | [tests/Feature/ClubMemberDuplicateMergeTest.php](../tests/Feature/ClubMemberDuplicateMergeTest.php) | Offen |
| [ ] | [tests/Feature/ClubMemberFamilyCalendarReadinessCatalogTest.php](../tests/Feature/ClubMemberFamilyCalendarReadinessCatalogTest.php) | Offen |
| [ ] | [tests/Feature/ClubMemberInvitationFlowTest.php](../tests/Feature/ClubMemberInvitationFlowTest.php) | Offen |
| [ ] | [tests/Feature/ClubMemberQualificationTest.php](../tests/Feature/ClubMemberQualificationTest.php) | Offen |
| [ ] | [tests/Feature/ClubMemberRecordsRegressionTest.php](../tests/Feature/ClubMemberRecordsRegressionTest.php) | Offen |
| [ ] | [tests/Feature/ClubMemberRelationshipTest.php](../tests/Feature/ClubMemberRelationshipTest.php) | Offen |
| [ ] | [tests/Feature/ClubMemberTimelineTest.php](../tests/Feature/ClubMemberTimelineTest.php) | Offen |
| [ ] | [tests/Feature/ClubMembershipAccessTest.php](../tests/Feature/ClubMembershipAccessTest.php) | Offen |
| [ ] | [tests/Feature/ClubMembershipApplicationFlowTest.php](../tests/Feature/ClubMembershipApplicationFlowTest.php) | Offen |
| [ ] | [tests/Feature/ClubMembershipChangeRequestTest.php](../tests/Feature/ClubMembershipChangeRequestTest.php) | Offen |
| [ ] | [tests/Feature/ClubMembershipImportFlowTest.php](../tests/Feature/ClubMembershipImportFlowTest.php) | Offen |
| [ ] | [tests/Feature/ClubMembershipInvoiceWorkflowTest.php](../tests/Feature/ClubMembershipInvoiceWorkflowTest.php) | Offen |
| [ ] | [tests/Feature/ClubMembershipLifecycleIntegrationTest.php](../tests/Feature/ClubMembershipLifecycleIntegrationTest.php) | Offen |
| [ ] | [tests/Feature/ClubMembershipProspectTest.php](../tests/Feature/ClubMembershipProspectTest.php) | Offen |
| [ ] | [tests/Feature/ClubMembershipTerminationTest.php](../tests/Feature/ClubMembershipTerminationTest.php) | Offen |
| [ ] | [tests/Feature/ClubMetadataConfigurationTest.php](../tests/Feature/ClubMetadataConfigurationTest.php) | Offen |
| [ ] | [tests/Feature/ClubMetadataValuesTest.php](../tests/Feature/ClubMetadataValuesTest.php) | Offen |
| [ ] | [tests/Feature/ClubModuleSettingsContractTest.php](../tests/Feature/ClubModuleSettingsContractTest.php) | Offen |
| [ ] | [tests/Feature/ClubNewsletterTest.php](../tests/Feature/ClubNewsletterTest.php) | Offen |
| [ ] | [tests/Feature/ClubNumberRangeIntegrationTest.php](../tests/Feature/ClubNumberRangeIntegrationTest.php) | Offen |
| [ ] | [tests/Feature/ClubNumberRangeReadinessTest.php](../tests/Feature/ClubNumberRangeReadinessTest.php) | Offen |
| [ ] | [tests/Feature/ClubOperationsReadinessCatalogTest.php](../tests/Feature/ClubOperationsReadinessCatalogTest.php) | Offen |
| [ ] | [tests/Feature/ClubOrganizationStructureTest.php](../tests/Feature/ClubOrganizationStructureTest.php) | Offen |
| [ ] | [tests/Feature/ClubPartialPaymentTest.php](../tests/Feature/ClubPartialPaymentTest.php) | Offen |
| [ ] | [tests/Feature/ClubPermissionDelegationTest.php](../tests/Feature/ClubPermissionDelegationTest.php) | Offen |
| [ ] | [tests/Feature/ClubPermissionsTest.php](../tests/Feature/ClubPermissionsTest.php) | Offen |
| [ ] | [tests/Feature/ClubPilotAcceptanceContractTest.php](../tests/Feature/ClubPilotAcceptanceContractTest.php) | Offen |
| [ ] | [tests/Feature/ClubPolicyDocumentReadinessTest.php](../tests/Feature/ClubPolicyDocumentReadinessTest.php) | Offen |
| [ ] | [tests/Feature/ClubPolicyDocumentTest.php](../tests/Feature/ClubPolicyDocumentTest.php) | Offen |
| [ ] | [tests/Feature/ClubProcurementWorkflowTest.php](../tests/Feature/ClubProcurementWorkflowTest.php) | Offen |
| [ ] | [tests/Feature/ClubProfileSplitPermissionsTest.php](../tests/Feature/ClubProfileSplitPermissionsTest.php) | Offen |
| [ ] | [tests/Feature/ClubPublicNetworkReadinessContractTest.php](../tests/Feature/ClubPublicNetworkReadinessContractTest.php) | Offen |
| [ ] | [tests/Feature/ClubReportingApiTest.php](../tests/Feature/ClubReportingApiTest.php) | Offen |
| [ ] | [tests/Feature/ClubRoleAccessReviewReminderTest.php](../tests/Feature/ClubRoleAccessReviewReminderTest.php) | Offen |
| [ ] | [tests/Feature/ClubRoleDefinitionTest.php](../tests/Feature/ClubRoleDefinitionTest.php) | Offen |
| [ ] | [tests/Feature/ClubSepaBatchTest.php](../tests/Feature/ClubSepaBatchTest.php) | Offen |
| [ ] | [tests/Feature/ClubSepaNoticeDeliveryReadinessTest.php](../tests/Feature/ClubSepaNoticeDeliveryReadinessTest.php) | Offen |
| [ ] | [tests/Feature/ClubSepaNoticeTest.php](../tests/Feature/ClubSepaNoticeTest.php) | Offen |
| [ ] | [tests/Feature/ClubSepaSettlementTest.php](../tests/Feature/ClubSepaSettlementTest.php) | Offen |
| [ ] | [tests/Feature/ClubServiceHourLedgerTest.php](../tests/Feature/ClubServiceHourLedgerTest.php) | Offen |
| [ ] | [tests/Feature/ClubSoftwareTariffLifecycleContractTest.php](../tests/Feature/ClubSoftwareTariffLifecycleContractTest.php) | Offen |
| [ ] | [tests/Feature/ClubSponsorPermissionsTest.php](../tests/Feature/ClubSponsorPermissionsTest.php) | Offen |
| [ ] | [tests/Feature/ClubSportWorkforceReadinessCatalogTest.php](../tests/Feature/ClubSportWorkforceReadinessCatalogTest.php) | Offen |
| [ ] | [tests/Feature/ClubStaffSchedulingTest.php](../tests/Feature/ClubStaffSchedulingTest.php) | Offen |
| [ ] | [tests/Feature/ClubStructuralModelContractRegressionTest.php](../tests/Feature/ClubStructuralModelContractRegressionTest.php) | Offen |
| [ ] | [tests/Feature/ClubStructureReadinessTest.php](../tests/Feature/ClubStructureReadinessTest.php) | Offen |
| [ ] | [tests/Feature/ClubSubscriptionPermissionTest.php](../tests/Feature/ClubSubscriptionPermissionTest.php) | Offen |
| [ ] | [tests/Feature/ClubSupportAccessContractTest.php](../tests/Feature/ClubSupportAccessContractTest.php) | Offen |
| [ ] | [tests/Feature/ClubSurveyApiTest.php](../tests/Feature/ClubSurveyApiTest.php) | Offen |
| [ ] | [tests/Feature/ClubTariffAuditRegressionTest.php](../tests/Feature/ClubTariffAuditRegressionTest.php) | Offen |
| [ ] | [tests/Feature/ClubTaskApiTest.php](../tests/Feature/ClubTaskApiTest.php) | Offen |
| [ ] | [tests/Feature/ClubVolunteerProfileTest.php](../tests/Feature/ClubVolunteerProfileTest.php) | Offen |
| [ ] | [tests/Feature/ClubWorkforcePersonModelTest.php](../tests/Feature/ClubWorkforcePersonModelTest.php) | Offen |
| [ ] | [tests/Feature/ClubWorkspaceIsolationTest.php](../tests/Feature/ClubWorkspaceIsolationTest.php) | Offen |
| [ ] | [tests/Feature/ClubYearPeriodOperationalLinkTest.php](../tests/Feature/ClubYearPeriodOperationalLinkTest.php) | Offen |
| [ ] | [tests/Feature/ClubYearPeriodReadinessTest.php](../tests/Feature/ClubYearPeriodReadinessTest.php) | Offen |
| [ ] | [tests/Feature/ClubYearPeriodReportTest.php](../tests/Feature/ClubYearPeriodReportTest.php) | Offen |
| [ ] | [tests/Feature/ClubYearPeriodTest.php](../tests/Feature/ClubYearPeriodTest.php) | Offen |
| [ ] | [tests/Feature/CommerceClubPermissionTest.php](../tests/Feature/CommerceClubPermissionTest.php) | Offen |
| [ ] | [tests/Feature/CommerceEventPriceListTest.php](../tests/Feature/CommerceEventPriceListTest.php) | Offen |
| [ ] | [tests/Feature/CommerceLocalizationContractTest.php](../tests/Feature/CommerceLocalizationContractTest.php) | Offen |
| [ ] | [tests/Feature/CommerceRefundReconciliationTest.php](../tests/Feature/CommerceRefundReconciliationTest.php) | Offen |
| [ ] | [tests/Feature/CommunicationInteractionReadinessContractTest.php](../tests/Feature/CommunicationInteractionReadinessContractTest.php) | Offen |
| [ ] | [tests/Feature/CommunicationPolicyTest.php](../tests/Feature/CommunicationPolicyTest.php) | Offen |
| [ ] | [tests/Feature/CommunicationScopeSafetyRegressionTest.php](../tests/Feature/CommunicationScopeSafetyRegressionTest.php) | Offen |
| [ ] | [tests/Feature/CompetitionCoreModelTest.php](../tests/Feature/CompetitionCoreModelTest.php) | Offen |
| [ ] | [tests/Feature/CompetitionRegistrationWorkflowTest.php](../tests/Feature/CompetitionRegistrationWorkflowTest.php) | Offen |
| [ ] | [tests/Feature/CompetitionSportPlanningTest.php](../tests/Feature/CompetitionSportPlanningTest.php) | Offen |
| [ ] | [tests/Feature/CookieTrackingConsentTest.php](../tests/Feature/CookieTrackingConsentTest.php) | Offen |
| [ ] | [tests/Feature/CoreWorkspaceAjaxContractTest.php](../tests/Feature/CoreWorkspaceAjaxContractTest.php) | Offen |
| [ ] | [tests/Feature/CreateApiTokenTest.php](../tests/Feature/CreateApiTokenTest.php) | Offen |
| [ ] | [tests/Feature/CriticalCheckoutExperienceContractTest.php](../tests/Feature/CriticalCheckoutExperienceContractTest.php) | Offen |
| [ ] | [tests/Feature/CriticalJourneyContractTest.php](../tests/Feature/CriticalJourneyContractTest.php) | Offen |
| [ ] | [tests/Feature/CrossDeviceReadinessTest.php](../tests/Feature/CrossDeviceReadinessTest.php) | Offen |
| [ ] | [tests/Feature/CrossModuleOutboxTest.php](../tests/Feature/CrossModuleOutboxTest.php) | Offen |
| [ ] | [tests/Feature/DashboardDailyFlowTest.php](../tests/Feature/DashboardDailyFlowTest.php) | Offen |
| [ ] | [tests/Feature/DatabaseBackupRestoreTest.php](../tests/Feature/DatabaseBackupRestoreTest.php) | Offen |
| [ ] | [tests/Feature/DatabaseQueryPlanAuditTest.php](../tests/Feature/DatabaseQueryPlanAuditTest.php) | Offen |
| [ ] | [tests/Feature/DeleteAccountTest.php](../tests/Feature/DeleteAccountTest.php) | Offen |
| [ ] | [tests/Feature/DeleteApiTokenTest.php](../tests/Feature/DeleteApiTokenTest.php) | Offen |
| [ ] | [tests/Feature/EmailVerificationTest.php](../tests/Feature/EmailVerificationTest.php) | Offen |
| [ ] | [tests/Feature/EventControlledCheckInTest.php](../tests/Feature/EventControlledCheckInTest.php) | Offen |
| [ ] | [tests/Feature/EventCreationPermissionTest.php](../tests/Feature/EventCreationPermissionTest.php) | Offen |
| [ ] | [tests/Feature/EventFileContextWorkflowTest.php](../tests/Feature/EventFileContextWorkflowTest.php) | Offen |
| [ ] | [tests/Feature/EventIndexTest.php](../tests/Feature/EventIndexTest.php) | Offen |
| [ ] | [tests/Feature/EventParticipationLifecycleTest.php](../tests/Feature/EventParticipationLifecycleTest.php) | Offen |
| [ ] | [tests/Feature/EventParticipationWebFlowTest.php](../tests/Feature/EventParticipationWebFlowTest.php) | Offen |
| [ ] | [tests/Feature/EventReminderCommandTest.php](../tests/Feature/EventReminderCommandTest.php) | Offen |
| [ ] | [tests/Feature/EventRouteWorkflowTest.php](../tests/Feature/EventRouteWorkflowTest.php) | Offen |
| [ ] | [tests/Feature/EventServiceTest.php](../tests/Feature/EventServiceTest.php) | Offen |
| [ ] | [tests/Feature/EventVisibilityContractTest.php](../tests/Feature/EventVisibilityContractTest.php) | Offen |
| [ ] | [tests/Feature/ExampleTest.php](../tests/Feature/ExampleTest.php) | Offen |
| [ ] | [tests/Feature/ExternalApiContractTest.php](../tests/Feature/ExternalApiContractTest.php) | Offen |
| [ ] | [tests/Feature/FeedTest.php](../tests/Feature/FeedTest.php) | Offen |
| [ ] | [tests/Feature/FileFolderPermissionAuditTest.php](../tests/Feature/FileFolderPermissionAuditTest.php) | Offen |
| [ ] | [tests/Feature/FileManagerFeatureTest.php](../tests/Feature/FileManagerFeatureTest.php) | Offen |
| [ ] | [tests/Feature/FinanceAccountingContractRegressionTest.php](../tests/Feature/FinanceAccountingContractRegressionTest.php) | Offen |
| [ ] | [tests/Feature/FinanceImplementationInventoryTest.php](../tests/Feature/FinanceImplementationInventoryTest.php) | Offen |
| [ ] | [tests/Feature/FinanceMasterDataRegressionTest.php](../tests/Feature/FinanceMasterDataRegressionTest.php) | Offen |
| [ ] | [tests/Feature/FormerMemberRetentionReadinessTest.php](../tests/Feature/FormerMemberRetentionReadinessTest.php) | Offen |
| [ ] | [tests/Feature/FriendInvitationTest.php](../tests/Feature/FriendInvitationTest.php) | Offen |
| [ ] | [tests/Feature/FrontendLocaleBundleContractTest.php](../tests/Feature/FrontendLocaleBundleContractTest.php) | Offen |
| [ ] | [tests/Feature/GamificationServiceTest.php](../tests/Feature/GamificationServiceTest.php) | Offen |
| [ ] | [tests/Feature/GlobalSearchTest.php](../tests/Feature/GlobalSearchTest.php) | Offen |
| [ ] | [tests/Feature/GovernanceReadinessTest.php](../tests/Feature/GovernanceReadinessTest.php) | Offen |
| [ ] | [tests/Feature/GuardianAccessFlowTest.php](../tests/Feature/GuardianAccessFlowTest.php) | Offen |
| [ ] | [tests/Feature/GuardianChildRelationshipServiceTest.php](../tests/Feature/GuardianChildRelationshipServiceTest.php) | Offen |
| [ ] | [tests/Feature/GuardianMultiRelationshipPrivacyRegressionTest.php](../tests/Feature/GuardianMultiRelationshipPrivacyRegressionTest.php) | Offen |
| [ ] | [tests/Feature/GuestExperienceOptimizationTest.php](../tests/Feature/GuestExperienceOptimizationTest.php) | Offen |
| [ ] | [tests/Feature/GuestPricingCheckoutSecurityTest.php](../tests/Feature/GuestPricingCheckoutSecurityTest.php) | Offen |
| [ ] | [tests/Feature/HotPathQueryContractTest.php](../tests/Feature/HotPathQueryContractTest.php) | Offen |
| [ ] | [tests/Feature/HttpDeliveryContractTest.php](../tests/Feature/HttpDeliveryContractTest.php) | Offen |
| [ ] | [tests/Feature/InertiaPayloadBudgetTest.php](../tests/Feature/InertiaPayloadBudgetTest.php) | Offen |
| [ ] | [tests/Feature/IntegrationCatalogContractTest.php](../tests/Feature/IntegrationCatalogContractTest.php) | Offen |
| [ ] | [tests/Feature/LearningCourseTranslationWorkflowTest.php](../tests/Feature/LearningCourseTranslationWorkflowTest.php) | Offen |
| [ ] | [tests/Feature/LearningLocalizationContractTest.php](../tests/Feature/LearningLocalizationContractTest.php) | Offen |
| [ ] | [tests/Feature/LearningStudioTest.php](../tests/Feature/LearningStudioTest.php) | Offen |
| [ ] | [tests/Feature/LegalPagesTest.php](../tests/Feature/LegalPagesTest.php) | Offen |
| [ ] | [tests/Feature/LocalizationAcceptanceContractTest.php](../tests/Feature/LocalizationAcceptanceContractTest.php) | Offen |
| [ ] | [tests/Feature/LocalizationIntegrityTest.php](../tests/Feature/LocalizationIntegrityTest.php) | Offen |
| [ ] | [tests/Feature/LocalizedBlogFeedTest.php](../tests/Feature/LocalizedBlogFeedTest.php) | Offen |
| [ ] | [tests/Feature/LocalizedGuestSeoTest.php](../tests/Feature/LocalizedGuestSeoTest.php) | Offen |
| [ ] | [tests/Feature/LocalizedLegalGuestPagesTest.php](../tests/Feature/LocalizedLegalGuestPagesTest.php) | Offen |
| [ ] | [tests/Feature/LocalizedWebManifestTest.php](../tests/Feature/LocalizedWebManifestTest.php) | Offen |
| [ ] | [tests/Feature/MailCenterControllerTest.php](../tests/Feature/MailCenterControllerTest.php) | Offen |
| [ ] | [tests/Feature/MarketplacePayoutServiceTest.php](../tests/Feature/MarketplacePayoutServiceTest.php) | Offen |
| [ ] | [tests/Feature/MarketplaceProductSubmissionTest.php](../tests/Feature/MarketplaceProductSubmissionTest.php) | Offen |
| [ ] | [tests/Feature/MarketplaceTrustApiTest.php](../tests/Feature/MarketplaceTrustApiTest.php) | Offen |
| [ ] | [tests/Feature/MaturityWebSessionApiTest.php](../tests/Feature/MaturityWebSessionApiTest.php) | Offen |
| [ ] | [tests/Feature/MemberIndexFeatureTest.php](../tests/Feature/MemberIndexFeatureTest.php) | Offen |
| [ ] | [tests/Feature/MemberPortalOverviewApiTest.php](../tests/Feature/MemberPortalOverviewApiTest.php) | Offen |
| [ ] | [tests/Feature/MinorSafetyConceptTest.php](../tests/Feature/MinorSafetyConceptTest.php) | Offen |
| [ ] | [tests/Feature/MobileAccountProfileTest.php](../tests/Feature/MobileAccountProfileTest.php) | Offen |
| [ ] | [tests/Feature/MobileAdminBackofficeApiTest.php](../tests/Feature/MobileAdminBackofficeApiTest.php) | Offen |
| [ ] | [tests/Feature/MobileAdminCommerceParityApiTest.php](../tests/Feature/MobileAdminCommerceParityApiTest.php) | Offen |
| [ ] | [tests/Feature/MobileAdminMailApiTest.php](../tests/Feature/MobileAdminMailApiTest.php) | Offen |
| [ ] | [tests/Feature/MobileAdminOutfitApiTest.php](../tests/Feature/MobileAdminOutfitApiTest.php) | Offen |
| [ ] | [tests/Feature/MobileAdminSystemApiTest.php](../tests/Feature/MobileAdminSystemApiTest.php) | Offen |
| [ ] | [tests/Feature/MobileApiContractTest.php](../tests/Feature/MobileApiContractTest.php) | Offen |
| [ ] | [tests/Feature/MobileApiPathSourceTest.php](../tests/Feature/MobileApiPathSourceTest.php) | Offen |
| [ ] | [tests/Feature/MobileAuthSecurityTest.php](../tests/Feature/MobileAuthSecurityTest.php) | Offen |
| [ ] | [tests/Feature/MobileBadgeApiTest.php](../tests/Feature/MobileBadgeApiTest.php) | Offen |
| [ ] | [tests/Feature/MobileChatMessageApiTest.php](../tests/Feature/MobileChatMessageApiTest.php) | Offen |
| [ ] | [tests/Feature/MobileChatRealtimeContractTest.php](../tests/Feature/MobileChatRealtimeContractTest.php) | Offen |
| [ ] | [tests/Feature/MobileClubAccessManagementSourceTest.php](../tests/Feature/MobileClubAccessManagementSourceTest.php) | Offen |
| [ ] | [tests/Feature/MobileClubExternalInvitationApiTest.php](../tests/Feature/MobileClubExternalInvitationApiTest.php) | Offen |
| [ ] | [tests/Feature/MobileClubMembershipParityApiTest.php](../tests/Feature/MobileClubMembershipParityApiTest.php) | Offen |
| [ ] | [tests/Feature/MobileCommerceBuyerApiTest.php](../tests/Feature/MobileCommerceBuyerApiTest.php) | Offen |
| [ ] | [tests/Feature/MobileCommerceSellerApiTest.php](../tests/Feature/MobileCommerceSellerApiTest.php) | Offen |
| [ ] | [tests/Feature/MobileDeepLinkContractTest.php](../tests/Feature/MobileDeepLinkContractTest.php) | Offen |
| [ ] | [tests/Feature/MobileEditorialSponsorApiTest.php](../tests/Feature/MobileEditorialSponsorApiTest.php) | Offen |
| [ ] | [tests/Feature/MobileEventApiTest.php](../tests/Feature/MobileEventApiTest.php) | Offen |
| [ ] | [tests/Feature/MobileFeedApiTest.php](../tests/Feature/MobileFeedApiTest.php) | Offen |
| [ ] | [tests/Feature/MobileFriendApiTest.php](../tests/Feature/MobileFriendApiTest.php) | Offen |
| [ ] | [tests/Feature/MobileFriendInvitationApiTest.php](../tests/Feature/MobileFriendInvitationApiTest.php) | Offen |
| [ ] | [tests/Feature/MobileFullQaRoleBoundaryTest.php](../tests/Feature/MobileFullQaRoleBoundaryTest.php) | Offen |
| [ ] | [tests/Feature/MobileGuardianApiTest.php](../tests/Feature/MobileGuardianApiTest.php) | Offen |
| [ ] | [tests/Feature/MobileLearningApiTest.php](../tests/Feature/MobileLearningApiTest.php) | Offen |
| [ ] | [tests/Feature/MobileLearningStudioApiTest.php](../tests/Feature/MobileLearningStudioApiTest.php) | Offen |
| [ ] | [tests/Feature/MobileOutfitSubscriptionApiTest.php](../tests/Feature/MobileOutfitSubscriptionApiTest.php) | Offen |
| [ ] | [tests/Feature/MobilePasswordRecoveryTest.php](../tests/Feature/MobilePasswordRecoveryTest.php) | Offen |
| [ ] | [tests/Feature/MobilePlatformAdminApiTest.php](../tests/Feature/MobilePlatformAdminApiTest.php) | Offen |
| [ ] | [tests/Feature/MobileProductionModuleNavigationContractTest.php](../tests/Feature/MobileProductionModuleNavigationContractTest.php) | Offen |
| [ ] | [tests/Feature/MobilePushDeliveryServiceTest.php](../tests/Feature/MobilePushDeliveryServiceTest.php) | Offen |
| [ ] | [tests/Feature/MobileReleaseEvidenceIntegrityContractTest.php](../tests/Feature/MobileReleaseEvidenceIntegrityContractTest.php) | Offen |
| [ ] | [tests/Feature/MobileRideApiTest.php](../tests/Feature/MobileRideApiTest.php) | Offen |
| [ ] | [tests/Feature/MobileSocialProfileApiTest.php](../tests/Feature/MobileSocialProfileApiTest.php) | Offen |
| [ ] | [tests/Feature/MobileSportIntegrationApiTest.php](../tests/Feature/MobileSportIntegrationApiTest.php) | Offen |
| [ ] | [tests/Feature/MobileSportProfileApiTest.php](../tests/Feature/MobileSportProfileApiTest.php) | Offen |
| [ ] | [tests/Feature/MobileSupportContactTest.php](../tests/Feature/MobileSupportContactTest.php) | Offen |
| [ ] | [tests/Feature/MobileTeamInvitationApiTest.php](../tests/Feature/MobileTeamInvitationApiTest.php) | Offen |
| [ ] | [tests/Feature/MobileTeamPenaltyApiTest.php](../tests/Feature/MobileTeamPenaltyApiTest.php) | Offen |
| [ ] | [tests/Feature/MobileTrainerCockpitApiTest.php](../tests/Feature/MobileTrainerCockpitApiTest.php) | Offen |
| [ ] | [tests/Feature/MobileWebSmokeTest.php](../tests/Feature/MobileWebSmokeTest.php) | Offen |
| [ ] | [tests/Feature/ModerationDsaProcessTest.php](../tests/Feature/ModerationDsaProcessTest.php) | Offen |
| [ ] | [tests/Feature/MultiClubPlatformIsolationContractTest.php](../tests/Feature/MultiClubPlatformIsolationContractTest.php) | Offen |
| [ ] | [tests/Feature/NavigationModulesTest.php](../tests/Feature/NavigationModulesTest.php) | Offen |
| [ ] | [tests/Feature/NotificationCenterFeatureTest.php](../tests/Feature/NotificationCenterFeatureTest.php) | Offen |
| [ ] | [tests/Feature/NotificationDigestCommandTest.php](../tests/Feature/NotificationDigestCommandTest.php) | Offen |
| [ ] | [tests/Feature/NotificationRoutingContractTest.php](../tests/Feature/NotificationRoutingContractTest.php) | Offen |
| [ ] | [tests/Feature/NutritionAiImageApiTest.php](../tests/Feature/NutritionAiImageApiTest.php) | Offen |
| [ ] | [tests/Feature/NutritionFeatureTest.php](../tests/Feature/NutritionFeatureTest.php) | Offen |
| [ ] | [tests/Feature/ObservabilityReadinessTest.php](../tests/Feature/ObservabilityReadinessTest.php) | Offen |
| [ ] | [tests/Feature/OperatingContractFeatureTest.php](../tests/Feature/OperatingContractFeatureTest.php) | Offen |
| [ ] | [tests/Feature/OperationalLocalizationContractTest.php](../tests/Feature/OperationalLocalizationContractTest.php) | Offen |
| [ ] | [tests/Feature/OperationsMonitoringTest.php](../tests/Feature/OperationsMonitoringTest.php) | Offen |
| [ ] | [tests/Feature/OrganizationJobPermissionTest.php](../tests/Feature/OrganizationJobPermissionTest.php) | Offen |
| [ ] | [tests/Feature/OrganizationLocalizationContractTest.php](../tests/Feature/OrganizationLocalizationContractTest.php) | Offen |
| [ ] | [tests/Feature/OutfitSubscriptionLocalizationContractTest.php](../tests/Feature/OutfitSubscriptionLocalizationContractTest.php) | Offen |
| [ ] | [tests/Feature/OutfitSubscriptionModuleTest.php](../tests/Feature/OutfitSubscriptionModuleTest.php) | Offen |
| [ ] | [tests/Feature/PasswordConfirmationTest.php](../tests/Feature/PasswordConfirmationTest.php) | Offen |
| [ ] | [tests/Feature/PasswordResetTest.php](../tests/Feature/PasswordResetTest.php) | Offen |
| [ ] | [tests/Feature/PaymentCancellationSecurityTest.php](../tests/Feature/PaymentCancellationSecurityTest.php) | Offen |
| [ ] | [tests/Feature/PaymentMvpFlowTest.php](../tests/Feature/PaymentMvpFlowTest.php) | Offen |
| [ ] | [tests/Feature/PaymentStatusMachineTest.php](../tests/Feature/PaymentStatusMachineTest.php) | Offen |
| [ ] | [tests/Feature/PaymentWebhookSignatureTest.php](../tests/Feature/PaymentWebhookSignatureTest.php) | Offen |
| [ ] | [tests/Feature/PersonaPermissionMatrixTest.php](../tests/Feature/PersonaPermissionMatrixTest.php) | Offen |
| [ ] | [tests/Feature/PlanFeatureServiceTest.php](../tests/Feature/PlanFeatureServiceTest.php) | Offen |
| [ ] | [tests/Feature/PlatformDeliveryFoundationTest.php](../tests/Feature/PlatformDeliveryFoundationTest.php) | Offen |
| [ ] | [tests/Feature/PlatformDeliveryRetentionTest.php](../tests/Feature/PlatformDeliveryRetentionTest.php) | Offen |
| [ ] | [tests/Feature/PrivacyCenterTest.php](../tests/Feature/PrivacyCenterTest.php) | Offen |
| [ ] | [tests/Feature/PrivacyRightsProcessTest.php](../tests/Feature/PrivacyRightsProcessTest.php) | Offen |
| [ ] | [tests/Feature/ProductAnalyticsPrivacyTest.php](../tests/Feature/ProductAnalyticsPrivacyTest.php) | Offen |
| [ ] | [tests/Feature/ProfileInformationTest.php](../tests/Feature/ProfileInformationTest.php) | Offen |
| [ ] | [tests/Feature/ProviderResilienceContractTest.php](../tests/Feature/ProviderResilienceContractTest.php) | Offen |
| [ ] | [tests/Feature/ProviderSmokeReadinessTest.php](../tests/Feature/ProviderSmokeReadinessTest.php) | Offen |
| [ ] | [tests/Feature/ProviderWebhookAndBankIdempotencyTest.php](../tests/Feature/ProviderWebhookAndBankIdempotencyTest.php) | Offen |
| [ ] | [tests/Feature/PublicCommerceCatalogTest.php](../tests/Feature/PublicCommerceCatalogTest.php) | Offen |
| [ ] | [tests/Feature/PublicContentApiTest.php](../tests/Feature/PublicContentApiTest.php) | Offen |
| [ ] | [tests/Feature/PublicDiscoverySeoTest.php](../tests/Feature/PublicDiscoverySeoTest.php) | Offen |
| [ ] | [tests/Feature/PublicMarketplaceTest.php](../tests/Feature/PublicMarketplaceTest.php) | Offen |
| [ ] | [tests/Feature/PublicSeoTest.php](../tests/Feature/PublicSeoTest.php) | Offen |
| [ ] | [tests/Feature/RecruitingOpportunityContextTest.php](../tests/Feature/RecruitingOpportunityContextTest.php) | Offen |
| [ ] | [tests/Feature/RecruitingPipelineContractTest.php](../tests/Feature/RecruitingPipelineContractTest.php) | Offen |
| [ ] | [tests/Feature/RecruitingPipelinePermissionTest.php](../tests/Feature/RecruitingPipelinePermissionTest.php) | Offen |
| [ ] | [tests/Feature/RegistrationTest.php](../tests/Feature/RegistrationTest.php) | Offen |
| [ ] | [tests/Feature/ReleasePreflightTest.php](../tests/Feature/ReleasePreflightTest.php) | Offen |
| [ ] | [tests/Feature/ReportingReadinessContractTest.php](../tests/Feature/ReportingReadinessContractTest.php) | Offen |
| [ ] | [tests/Feature/RevenueTrustWorkflowTest.php](../tests/Feature/RevenueTrustWorkflowTest.php) | Offen |
| [ ] | [tests/Feature/RideFeatureTest.php](../tests/Feature/RideFeatureTest.php) | Offen |
| [ ] | [tests/Feature/RoleApplicationTest.php](../tests/Feature/RoleApplicationTest.php) | Offen |
| [ ] | [tests/Feature/RoleExperienceTest.php](../tests/Feature/RoleExperienceTest.php) | Offen |
| [ ] | [tests/Feature/SavedViewTest.php](../tests/Feature/SavedViewTest.php) | Offen |
| [ ] | [tests/Feature/ScopedOrganizationPolicyTest.php](../tests/Feature/ScopedOrganizationPolicyTest.php) | Offen |
| [ ] | [tests/Feature/SecurityHeadersTest.php](../tests/Feature/SecurityHeadersTest.php) | Offen |
| [ ] | [tests/Feature/SecurityPrivacyAcceptanceContractTest.php](../tests/Feature/SecurityPrivacyAcceptanceContractTest.php) | Offen |
| [ ] | [tests/Feature/SelfServiceChannelGapMatrixContractTest.php](../tests/Feature/SelfServiceChannelGapMatrixContractTest.php) | Offen |
| [ ] | [tests/Feature/ServerLocalizationContractTest.php](../tests/Feature/ServerLocalizationContractTest.php) | Offen |
| [ ] | [tests/Feature/SettingsLazyLoadingTest.php](../tests/Feature/SettingsLazyLoadingTest.php) | Offen |
| [ ] | [tests/Feature/SponsorAgencyWorkspaceContractTest.php](../tests/Feature/SponsorAgencyWorkspaceContractTest.php) | Offen |
| [ ] | [tests/Feature/SponsorDeliverableManagementTest.php](../tests/Feature/SponsorDeliverableManagementTest.php) | Offen |
| [ ] | [tests/Feature/SponsorModuleGapMatrixContractTest.php](../tests/Feature/SponsorModuleGapMatrixContractTest.php) | Offen |
| [ ] | [tests/Feature/SportAdminControllerTest.php](../tests/Feature/SportAdminControllerTest.php) | Offen |
| [ ] | [tests/Feature/SportMapFeatureTest.php](../tests/Feature/SportMapFeatureTest.php) | Offen |
| [ ] | [tests/Feature/SportMatchingFeatureTest.php](../tests/Feature/SportMatchingFeatureTest.php) | Offen |
| [ ] | [tests/Feature/SportMatchingRecruitingLocalizationContractTest.php](../tests/Feature/SportMatchingRecruitingLocalizationContractTest.php) | Offen |
| [ ] | [tests/Feature/SportProfileScoutApiTest.php](../tests/Feature/SportProfileScoutApiTest.php) | Offen |
| [ ] | [tests/Feature/StagedRolloutAcceptanceContractTest.php](../tests/Feature/StagedRolloutAcceptanceContractTest.php) | Offen |
| [ ] | [tests/Feature/StagingHttpDeliveryAuditTest.php](../tests/Feature/StagingHttpDeliveryAuditTest.php) | Offen |
| [ ] | [tests/Feature/StoreReadinessDocumentationTest.php](../tests/Feature/StoreReadinessDocumentationTest.php) | Offen |
| [ ] | [tests/Feature/SubprocessorDocumentationTest.php](../tests/Feature/SubprocessorDocumentationTest.php) | Offen |
| [ ] | [tests/Feature/SubscriptionLifecycleContractTest.php](../tests/Feature/SubscriptionLifecycleContractTest.php) | Offen |
| [ ] | [tests/Feature/SubscriptionLocalizationContractTest.php](../tests/Feature/SubscriptionLocalizationContractTest.php) | Offen |
| [ ] | [tests/Feature/SupportCenterWebTest.php](../tests/Feature/SupportCenterWebTest.php) | Offen |
| [ ] | [tests/Feature/SupportTicketApiTest.php](../tests/Feature/SupportTicketApiTest.php) | Offen |
| [ ] | [tests/Feature/TeamBulkOrderApiTest.php](../tests/Feature/TeamBulkOrderApiTest.php) | Offen |
| [ ] | [tests/Feature/TeamCompetitivenessInsightsTest.php](../tests/Feature/TeamCompetitivenessInsightsTest.php) | Offen |
| [ ] | [tests/Feature/TeamDailyLifeApiTest.php](../tests/Feature/TeamDailyLifeApiTest.php) | Offen |
| [ ] | [tests/Feature/TeamIndexApiTest.php](../tests/Feature/TeamIndexApiTest.php) | Offen |
| [ ] | [tests/Feature/TeamMemberAssignmentHistoryTest.php](../tests/Feature/TeamMemberAssignmentHistoryTest.php) | Offen |
| [ ] | [tests/Feature/TeamMemberManagementTest.php](../tests/Feature/TeamMemberManagementTest.php) | Offen |
| [ ] | [tests/Feature/TeamSportIntegrationLocalizationContractTest.php](../tests/Feature/TeamSportIntegrationLocalizationContractTest.php) | Offen |
| [ ] | [tests/Feature/TeamSportYearPlanningTest.php](../tests/Feature/TeamSportYearPlanningTest.php) | Offen |
| [ ] | [tests/Feature/TeamTransferRequestTest.php](../tests/Feature/TeamTransferRequestTest.php) | Offen |
| [ ] | [tests/Feature/TrainerCockpitWeeklyControlTest.php](../tests/Feature/TrainerCockpitWeeklyControlTest.php) | Offen |
| [ ] | [tests/Feature/TrainingAiSafetyProofTest.php](../tests/Feature/TrainingAiSafetyProofTest.php) | Offen |
| [ ] | [tests/Feature/TrainingAnalyticsApiTest.php](../tests/Feature/TrainingAnalyticsApiTest.php) | Offen |
| [ ] | [tests/Feature/TrainingAvailabilityApiTest.php](../tests/Feature/TrainingAvailabilityApiTest.php) | Offen |
| [ ] | [tests/Feature/TrainingExerciseLibraryTest.php](../tests/Feature/TrainingExerciseLibraryTest.php) | Offen |
| [ ] | [tests/Feature/TrainingGamificationIntegrationTest.php](../tests/Feature/TrainingGamificationIntegrationTest.php) | Offen |
| [ ] | [tests/Feature/TrainingLogApiCrudTest.php](../tests/Feature/TrainingLogApiCrudTest.php) | Offen |
| [ ] | [tests/Feature/TrainingPlanApiCrudTest.php](../tests/Feature/TrainingPlanApiCrudTest.php) | Offen |
| [ ] | [tests/Feature/TrainingPrivacyBoundaryRegressionTest.php](../tests/Feature/TrainingPrivacyBoundaryRegressionTest.php) | Offen |
| [ ] | [tests/Feature/TrainingRouteWorkflowTest.php](../tests/Feature/TrainingRouteWorkflowTest.php) | Offen |
| [ ] | [tests/Feature/TrainingSessionApiTest.php](../tests/Feature/TrainingSessionApiTest.php) | Offen |
| [ ] | [tests/Feature/TrainingSystemTest.php](../tests/Feature/TrainingSystemTest.php) | Offen |
| [ ] | [tests/Feature/TrainingWorkflowIntegrationTest.php](../tests/Feature/TrainingWorkflowIntegrationTest.php) | Offen |
| [ ] | [tests/Feature/TrainingWorkspaceIndexTest.php](../tests/Feature/TrainingWorkspaceIndexTest.php) | Offen |
| [ ] | [tests/Feature/TwoFactorAuthenticationSettingsTest.php](../tests/Feature/TwoFactorAuthenticationSettingsTest.php) | Offen |
| [ ] | [tests/Feature/TwoFactorEmailLoginTest.php](../tests/Feature/TwoFactorEmailLoginTest.php) | Offen |
| [ ] | [tests/Feature/UpdatePasswordTest.php](../tests/Feature/UpdatePasswordTest.php) | Offen |
| [ ] | [tests/Feature/UploadValidationTest.php](../tests/Feature/UploadValidationTest.php) | Offen |
| [ ] | [tests/Feature/UserDataErasureTest.php](../tests/Feature/UserDataErasureTest.php) | Offen |
| [ ] | [tests/Feature/UserSportCvProfileTest.php](../tests/Feature/UserSportCvProfileTest.php) | Offen |
| [ ] | [tests/Feature/UserSubscriptionAccountManagementTest.php](../tests/Feature/UserSubscriptionAccountManagementTest.php) | Offen |
| [ ] | [tests/Feature/VerificationMailSenderTest.php](../tests/Feature/VerificationMailSenderTest.php) | Offen |
| [ ] | [tests/Feature/WaterReminderTest.php](../tests/Feature/WaterReminderTest.php) | Offen |
| [ ] | [tests/Feature/Wcag22AccessibilityContractTest.php](../tests/Feature/Wcag22AccessibilityContractTest.php) | Offen |
| [ ] | [tests/Feature/WelcomePublicTest.php](../tests/Feature/WelcomePublicTest.php) | Offen |
| [ ] | [tests/Feature/WorkAutomationJobTest.php](../tests/Feature/WorkAutomationJobTest.php) | Offen |
| [ ] | [tests/Feature/WorkManagementCatalogTest.php](../tests/Feature/WorkManagementCatalogTest.php) | Offen |
| [ ] | [tests/Feature/WorkspaceContextTest.php](../tests/Feature/WorkspaceContextTest.php) | Offen |
| [ ] | [tests/TestCase.php](../tests/TestCase.php) | Offen |
| [ ] | [tests/Unit/AiAssistiveSuggestionServiceTest.php](../tests/Unit/AiAssistiveSuggestionServiceTest.php) | Offen |
| [ ] | [tests/Unit/AiGatewayServiceTest.php](../tests/Unit/AiGatewayServiceTest.php) | Offen |
| [ ] | [tests/Unit/AirmiusRoleMatrixTest.php](../tests/Unit/AirmiusRoleMatrixTest.php) | Offen |
| [ ] | [tests/Unit/AppShellAccessibilityContractTest.php](../tests/Unit/AppShellAccessibilityContractTest.php) | Offen |
| [ ] | [tests/Unit/CarrierTrackingTest.php](../tests/Unit/CarrierTrackingTest.php) | Offen |
| [ ] | [tests/Unit/ClubInventoryWebContractTest.php](../tests/Unit/ClubInventoryWebContractTest.php) | Offen |
| [ ] | [tests/Unit/ClubMemberCardWebContractTest.php](../tests/Unit/ClubMemberCardWebContractTest.php) | Offen |
| [ ] | [tests/Unit/ClubMembershipBankReconciliationServiceTest.php](../tests/Unit/ClubMembershipBankReconciliationServiceTest.php) | Offen |
| [ ] | [tests/Unit/ClubMembershipImportPreviewContractTest.php](../tests/Unit/ClubMembershipImportPreviewContractTest.php) | Offen |
| [ ] | [tests/Unit/ClubMembershipImportServiceTest.php](../tests/Unit/ClubMembershipImportServiceTest.php) | Offen |
| [ ] | [tests/Unit/ClubMembershipSepaServiceTest.php](../tests/Unit/ClubMembershipSepaServiceTest.php) | Offen |
| [ ] | [tests/Unit/ClubProfileRulesTest.php](../tests/Unit/ClubProfileRulesTest.php) | Offen |
| [ ] | [tests/Unit/ClubSurveyWebWorkspaceContractTest.php](../tests/Unit/ClubSurveyWebWorkspaceContractTest.php) | Offen |
| [ ] | [tests/Unit/DateInputContractTest.php](../tests/Unit/DateInputContractTest.php) | Offen |
| [ ] | [tests/Unit/ExampleTest.php](../tests/Unit/ExampleTest.php) | Offen |
| [ ] | [tests/Unit/PlatformModuleRegistryTest.php](../tests/Unit/PlatformModuleRegistryTest.php) | Offen |
| [ ] | [tests/Unit/RequestPerformanceTelemetryTest.php](../tests/Unit/RequestPerformanceTelemetryTest.php) | Offen |
| [ ] | [tests/Unit/SocialAuthMobileReturnUrlTest.php](../tests/Unit/SocialAuthMobileReturnUrlTest.php) | Offen |
| [ ] | [tests/Unit/TeamLifecycleWorkspaceContractTest.php](../tests/Unit/TeamLifecycleWorkspaceContractTest.php) | Offen |
| [ ] | [tests/Unit/TrainingModalArchitectureContractTest.php](../tests/Unit/TrainingModalArchitectureContractTest.php) | Offen |
| [ ] | [tests/Unit/TrainingWorkspaceSurfaceContractTest.php](../tests/Unit/TrainingWorkspaceSurfaceContractTest.php) | Offen |

## Flutter-Testdateien

Vorhandene Dateien sind nur Hinweise auf automatisierte Abdeckung. Jeden relevanten Test tatsächlich ausführen und sein Ergebnis dem Fachfall zuordnen; Dateiexistenz und erfolgreiche statische Quellenprüfung sind kein End-to-End-Nachweis.

| Offen | Datei | Fachfall / Ergebnis |
| --- | --- | --- |
| [ ] | [mobile/airmius_mobile/test/airmius_preferences_store_io_test.dart](../mobile/airmius_mobile/test/airmius_preferences_store_io_test.dart) | Offen |
| [ ] | [mobile/airmius_mobile/test/api_contract_test.dart](../mobile/airmius_mobile/test/api_contract_test.dart) | Offen |
| [ ] | [mobile/airmius_mobile/test/api_transport_test.dart](../mobile/airmius_mobile/test/api_transport_test.dart) | Offen |
| [ ] | [mobile/airmius_mobile/test/auth_callback_security_test.dart](../mobile/airmius_mobile/test/auth_callback_security_test.dart) | Offen |
| [ ] | [mobile/airmius_mobile/test/auth_recovery_security_test.dart](../mobile/airmius_mobile/test/auth_recovery_security_test.dart) | Offen |
| [ ] | [mobile/airmius_mobile/test/challenge_feedback_test.dart](../mobile/airmius_mobile/test/challenge_feedback_test.dart) | Offen |
| [ ] | [mobile/airmius_mobile/test/challenge_input_test.dart](../mobile/airmius_mobile/test/challenge_input_test.dart) | Offen |
| [ ] | [mobile/airmius_mobile/test/challenges_test.dart](../mobile/airmius_mobile/test/challenges_test.dart) | Offen |
| [ ] | [mobile/airmius_mobile/test/club_branding_settings_test.dart](../mobile/airmius_mobile/test/club_branding_settings_test.dart) | Offen |
| [ ] | [mobile/airmius_mobile/test/club_contact_master_data_test.dart](../mobile/airmius_mobile/test/club_contact_master_data_test.dart) | Offen |
| [ ] | [mobile/airmius_mobile/test/club_deletion_test.dart](../mobile/airmius_mobile/test/club_deletion_test.dart) | Offen |
| [ ] | [mobile/airmius_mobile/test/club_governance_mobile_test.dart](../mobile/airmius_mobile/test/club_governance_mobile_test.dart) | Offen |
| [ ] | [mobile/airmius_mobile/test/club_legal_master_data_test.dart](../mobile/airmius_mobile/test/club_legal_master_data_test.dart) | Offen |
| [ ] | [mobile/airmius_mobile/test/club_metadata_api_test.dart](../mobile/airmius_mobile/test/club_metadata_api_test.dart) | Offen |
| [ ] | [mobile/airmius_mobile/test/club_metadata_mobile_test.dart](../mobile/airmius_mobile/test/club_metadata_mobile_test.dart) | Offen |
| [ ] | [mobile/airmius_mobile/test/club_metadata_subject_mobile_test.dart](../mobile/airmius_mobile/test/club_metadata_subject_mobile_test.dart) | Offen |
| [ ] | [mobile/airmius_mobile/test/club_organization_mobile_test.dart](../mobile/airmius_mobile/test/club_organization_mobile_test.dart) | Offen |
| [ ] | [mobile/airmius_mobile/test/club_policy_documents_mobile_test.dart](../mobile/airmius_mobile/test/club_policy_documents_mobile_test.dart) | Offen |
| [ ] | [mobile/airmius_mobile/test/club_receipt_upload_client_test.dart](../mobile/airmius_mobile/test/club_receipt_upload_client_test.dart) | Offen |
| [ ] | [mobile/airmius_mobile/test/club_sepa_batch_test.dart](../mobile/airmius_mobile/test/club_sepa_batch_test.dart) | Offen |
| [ ] | [mobile/airmius_mobile/test/club_sepa_fee_correction_test.dart](../mobile/airmius_mobile/test/club_sepa_fee_correction_test.dart) | Offen |
| [ ] | [mobile/airmius_mobile/test/club_sepa_fee_recharge_test.dart](../mobile/airmius_mobile/test/club_sepa_fee_recharge_test.dart) | Offen |
| [ ] | [mobile/airmius_mobile/test/club_sepa_fee_test.dart](../mobile/airmius_mobile/test/club_sepa_fee_test.dart) | Offen |
| [ ] | [mobile/airmius_mobile/test/club_year_periods_mobile_test.dart](../mobile/airmius_mobile/test/club_year_periods_mobile_test.dart) | Offen |
| [ ] | [mobile/airmius_mobile/test/content_report_dialog_test.dart](../mobile/airmius_mobile/test/content_report_dialog_test.dart) | Offen |
| [ ] | [mobile/airmius_mobile/test/external_url_test.dart](../mobile/airmius_mobile/test/external_url_test.dart) | Offen |
| [ ] | [mobile/airmius_mobile/test/free_run_draft_store_test.dart](../mobile/airmius_mobile/test/free_run_draft_store_test.dart) | Offen |
| [ ] | [mobile/airmius_mobile/test/guardian_contract_test.dart](../mobile/airmius_mobile/test/guardian_contract_test.dart) | Offen |
| [ ] | [mobile/airmius_mobile/test/localization_l10n_test.dart](../mobile/airmius_mobile/test/localization_l10n_test.dart) | Offen |
| [ ] | [mobile/airmius_mobile/test/member_duplicate_merge_api_test.dart](../mobile/airmius_mobile/test/member_duplicate_merge_api_test.dart) | Offen |
| [ ] | [mobile/airmius_mobile/test/member_timeline_api_test.dart](../mobile/airmius_mobile/test/member_timeline_api_test.dart) | Offen |
| [ ] | [mobile/airmius_mobile/test/membership_change_api_test.dart](../mobile/airmius_mobile/test/membership_change_api_test.dart) | Offen |
| [ ] | [mobile/airmius_mobile/test/membership_prospects_api_test.dart](../mobile/airmius_mobile/test/membership_prospects_api_test.dart) | Offen |
| [ ] | [mobile/airmius_mobile/test/membership_request_follow_up_api_test.dart](../mobile/airmius_mobile/test/membership_request_follow_up_api_test.dart) | Offen |
| [ ] | [mobile/airmius_mobile/test/preferences_test.dart](../mobile/airmius_mobile/test/preferences_test.dart) | Offen |
| [ ] | [mobile/airmius_mobile/test/progress_module_label_test.dart](../mobile/airmius_mobile/test/progress_module_label_test.dart) | Offen |
| [ ] | [mobile/airmius_mobile/test/saved_views_api_test.dart](../mobile/airmius_mobile/test/saved_views_api_test.dart) | Offen |
| [ ] | [mobile/airmius_mobile/test/sepa_return_import_client_test.dart](../mobile/airmius_mobile/test/sepa_return_import_client_test.dart) | Offen |
| [ ] | [mobile/airmius_mobile/test/sepa_return_import_widget_test.dart](../mobile/airmius_mobile/test/sepa_return_import_widget_test.dart) | Offen |
| [ ] | [mobile/airmius_mobile/test/social_login_state_test.dart](../mobile/airmius_mobile/test/social_login_state_test.dart) | Offen |
| [ ] | [mobile/airmius_mobile/test/team_workspace_scope_test.dart](../mobile/airmius_mobile/test/team_workspace_scope_test.dart) | Offen |
| [ ] | [mobile/airmius_mobile/test/training_count_labels_test.dart](../mobile/airmius_mobile/test/training_count_labels_test.dart) | Offen |
| [ ] | [mobile/airmius_mobile/test/training_draft_store_test.dart](../mobile/airmius_mobile/test/training_draft_store_test.dart) | Offen |
| [ ] | [mobile/airmius_mobile/test/training_live_workout_widget_test.dart](../mobile/airmius_mobile/test/training_live_workout_widget_test.dart) | Offen |
| [ ] | [mobile/airmius_mobile/test/widget_test.dart](../mobile/airmius_mobile/test/widget_test.dart) | Offen |

## JavaScript-Testdateien

Vorhandene Dateien sind nur Hinweise auf automatisierte Abdeckung. Jeden relevanten Test tatsächlich ausführen und sein Ergebnis dem Fachfall zuordnen; Dateiexistenz und erfolgreiche statische Quellenprüfung sind kein End-to-End-Nachweis.

| Offen | Datei | Fachfall / Ergebnis |
| --- | --- | --- |
| [ ] | [tests/Frontend/clubBrandingRender.test.mjs](../tests/Frontend/clubBrandingRender.test.mjs) | Offen |
| [ ] | [tests/Frontend/clubContactMasterDataRender.test.mjs](../tests/Frontend/clubContactMasterDataRender.test.mjs) | Offen |
| [ ] | [tests/Frontend/clubGovernanceRender.test.mjs](../tests/Frontend/clubGovernanceRender.test.mjs) | Offen |
| [ ] | [tests/Frontend/clubLegalMasterDataRender.test.mjs](../tests/Frontend/clubLegalMasterDataRender.test.mjs) | Offen |
| [ ] | [tests/Frontend/clubMembershipChangeRender.test.mjs](../tests/Frontend/clubMembershipChangeRender.test.mjs) | Offen |
| [ ] | [tests/Frontend/clubMembershipProspectsRender.test.mjs](../tests/Frontend/clubMembershipProspectsRender.test.mjs) | Offen |
| [ ] | [tests/Frontend/clubMetadataRender.test.mjs](../tests/Frontend/clubMetadataRender.test.mjs) | Offen |
| [ ] | [tests/Frontend/clubOrganizationRender.test.mjs](../tests/Frontend/clubOrganizationRender.test.mjs) | Offen |
| [ ] | [tests/Frontend/clubPolicyDocumentsRender.test.mjs](../tests/Frontend/clubPolicyDocumentsRender.test.mjs) | Offen |
| [ ] | [tests/Frontend/clubYearPeriodsRender.test.mjs](../tests/Frontend/clubYearPeriodsRender.test.mjs) | Offen |
| [ ] | [tests/Frontend/contributionPolicyLinkRender.test.mjs](../tests/Frontend/contributionPolicyLinkRender.test.mjs) | Offen |
| [ ] | [tests/Frontend/feeCorrectionRender.test.mjs](../tests/Frontend/feeCorrectionRender.test.mjs) | Offen |
| [ ] | [tests/Frontend/feeCorrectionState.test.mjs](../tests/Frontend/feeCorrectionState.test.mjs) | Offen |
| [ ] | [tests/Frontend/feeRechargeRender.test.mjs](../tests/Frontend/feeRechargeRender.test.mjs) | Offen |
| [ ] | [tests/Frontend/feeRechargeState.test.mjs](../tests/Frontend/feeRechargeState.test.mjs) | Offen |
| [ ] | [tests/Frontend/sepaNoticeProviderFeedbackRender.test.mjs](../tests/Frontend/sepaNoticeProviderFeedbackRender.test.mjs) | Offen |
| [ ] | [tests/Frontend/sepaReturnImportRender.test.mjs](../tests/Frontend/sepaReturnImportRender.test.mjs) | Offen |

## Geplante Befehle

Je Befehl: fällige und nicht fällige Testdaten vorbereiten; tatsächlichen Lauf ausführen; Status, Empfänger und Wiederholung kontrollieren. Den Rhythmus und Schutz vor Überlappung an der verlinkten Definition prüfen. Keine produktive Zeit vorspulen.

| Offen | Befehl | Definition | Fachfall / Ergebnis |
| --- | --- | --- | --- |
| [ ] | `airmius:publish-club-announcements` | [routes/console.php:16](../routes/console.php#L16) | T36-03 / Offen |
| [ ] | `airmius:process-club-deletions` | [routes/console.php:18](../routes/console.php#L18) | T36-03 / Offen |
| [ ] | `airmius:send-membership-billing-reminders` | [routes/console.php:20](../routes/console.php#L20) | T36-03 / Offen |
| [ ] | `airmius:process-membership-terminations` | [routes/console.php:24](../routes/console.php#L24) | T36-03 / Offen |
| [ ] | `airmius:generate-recurring-contribution-invoices` | [routes/console.php:28](../routes/console.php#L28) | T36-03 / Offen |
| [ ] | `airmius:send-subscription-invoice-emails` | [routes/console.php:32](../routes/console.php#L32) | T36-03 / Offen |
| [ ] | `airmius:send-notification-digests` | [routes/console.php:36](../routes/console.php#L36) | T36-03 / Offen |
| [ ] | `airmius:send-scheduled-communications --limit=250` | [routes/console.php:40](../routes/console.php#L40) | T36-03 / Offen |
| [ ] | `airmius:mail-delivery-dispatch --limit=500` | [routes/console.php:44](../routes/console.php#L44) | T36-03 / Offen |
| [ ] | `airmius:process-subscription-lifecycle` | [routes/console.php:48](../routes/console.php#L48) | T36-03 / Offen |
| [ ] | `airmius:sync-sport-integrations` | [routes/console.php:52](../routes/console.php#L52) | T36-03 / Offen |
| [ ] | `airmius:prepare-outfit-deliveries` | [routes/console.php:56](../routes/console.php#L56) | T36-03 / Offen |
| [ ] | `airmius:process-outfit-subscription-lifecycle` | [routes/console.php:60](../routes/console.php#L60) | T36-03 / Offen |
| [ ] | `airmius:send-outfit-payment-reminders` | [routes/console.php:64](../routes/console.php#L64) | T36-03 / Offen |
| [ ] | `airmius:send-event-reminders` | [routes/console.php:68](../routes/console.php#L68) | T36-03 / Offen |
| [ ] | `airmius:send-sport-matching-reminders` | [routes/console.php:72](../routes/console.php#L72) | T36-03 / Offen |
| [ ] | `airmius:send-water-reminders` | [routes/console.php:76](../routes/console.php#L76) | T36-03 / Offen |
| [ ] | `airmius:mobile-push-dispatch --limit=500` | [routes/console.php:80](../routes/console.php#L80) | T36-03 / Offen |
| [ ] | `airmius:send-learning-drip-notifications` | [routes/console.php:84](../routes/console.php#L84) | T36-03 / Offen |
| [ ] | `airmius:monitor-learning-health` | [routes/console.php:88](../routes/console.php#L88) | T36-03 / Offen |
| [ ] | `airmius:monitor-operations` | [routes/console.php:92](../routes/console.php#L92) | T36-03 / Offen |
| [ ] | `airmius:check-ai-provider-tokens` | [routes/console.php:96](../routes/console.php#L96) | T36-03 / Offen |
| [ ] | `airmius:process-inactive-accounts` | [routes/console.php:100](../routes/console.php#L100) | T36-03 / Offen |
| [ ] | `airmius:backup-database` | [routes/console.php:104](../routes/console.php#L104) | T36-03 / Offen |
| [ ] | `airmius:prune-ad-events --limit=1000` | [routes/console.php:108](../routes/console.php#L108) | T36-03 / Offen |
| [ ] | `airmius:prune-website-requests --limit=1000` | [routes/console.php:112](../routes/console.php#L112) | T36-03 / Offen |
| [ ] | `airmius:prune-recruiting-interests --limit=1000` | [routes/console.php:116](../routes/console.php#L116) | T36-03 / Offen |
| [ ] | `airmius:prune-expired-stories --limit=500` | [routes/console.php:120](../routes/console.php#L120) | T36-03 / Offen |
| [ ] | `airmius:dispatch-domain-outbox --limit=500` | [routes/console.php:124](../routes/console.php#L124) | T36-03 / Offen |
| [ ] | `airmius:prune-platform-delivery --outbox-days=30 --failed-outbox-days=90 --limit=1000` | [routes/console.php:128](../routes/console.php#L128) | T36-03 / Offen |

## Broadcast-Kanäle

Je Kanal: berechtigte und unberechtigte Anmeldung, Objektzugehörigkeit, Dateninhalt sowie Rechteentzug während einer offenen Verbindung prüfen.

| Offen | Kanal | Definition | Fachfall / Ergebnis |
| --- | --- | --- | --- |
| [ ] | `chat.conversation.{conversation}` | [routes/channels.php:9](../routes/channels.php#L9) | T36-07 / Offen |
| [ ] | `chat.user.{userId}` | [routes/channels.php:13](../routes/channels.php#L13) | T36-07 / Offen |
| [ ] | `notifications.user.{userId}` | [routes/channels.php:17](../routes/channels.php#L17) | T36-07 / Offen |
| [ ] | `events.team.{team}` | [routes/channels.php:21](../routes/channels.php#L21) | T36-07 / Offen |
| [ ] | `events.club.{club}` | [routes/channels.php:26](../routes/channels.php#L26) | T36-07 / Offen |
| [ ] | `users.status` | [routes/channels.php:31](../routes/channels.php#L31) | T36-07 / Offen |

## Zusätzliche Quellen für die Abschlussprüfung

- `resources/js/Components/` und `resources/js/composables/`: Die oben genannten Seiten verwenden gemeinsame Komponenten und dynamische Navigation. Deren sichtbare Aktionen im jeweiligen Seitenkontext mitprüfen.
- `mobile/airmius_mobile/lib/widgets/`, `lib/navigation/` und `lib/core/`: Gemeinsame Dialoge, Appnavigation, Deep Links, API-Clients und Berechtigungen zu den Screens mitprüfen.
- `app/Policies/`, `app/Support/`, `app/Services/`, Requests und Controller: Zusätzliche fachliche Rechte, Zustandsmaschinen und Validierung pro Action prüfen.
- Konfiguration und Feature-Rollouts: Tatsächlich aktivierte Funktionen, Tarife, externe Provider und unterstützte Plattformen im jeweiligen Testlauf festhalten.

## Abnahme

Dieses Inventar ist erst fachlich zugeordnet, wenn keine offene Zeile mehr ohne Fachfall oder begründete Nichtanwendbarkeit verbleibt. Ein Release ist erst getestet, wenn die zugeordneten Fälle mit den erforderlichen Rollen, Datenvarianten und Plattformen ausgeführt wurden. In diesem Dokument ist noch kein Ablauf als bestanden markiert.

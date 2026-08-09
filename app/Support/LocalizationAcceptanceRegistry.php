<?php

namespace App\Support;

final class LocalizationAcceptanceRegistry
{
    public const CONTRACT = 'localized-experience.v1';

    /** @return array<string, mixed> */
    public static function definitions(): array
    {
        return [
            'contract' => self::CONTRACT,
            'locales' => [
                'supported' => SupportedLocale::ALL,
                'default' => SupportedLocale::DEFAULT,
                'rtl' => SupportedLocale::RTL,
                'directions' => collect(SupportedLocale::ALL)
                    ->mapWithKeys(fn (string $locale): array => [$locale => SupportedLocale::direction($locale)])
                    ->all(),
            ],
            'critical_journeys' => [
                'athlete_day' => self::surface(
                    ['resources/js/Components/Dashboard/DashboardDailyFlowWidget.vue'],
                    ['athlete_today'],
                ),
                'coach_week' => self::surface(
                    ['resources/js/Pages/Auth/Dashboard/TrainerCockpit/Index.vue', 'resources/js/Components/Teams/TeamDailyHomeWidget.vue'],
                    ['team_home', 'team_competitiveness'],
                ),
                'member_lifecycle' => self::surface(
                    ['resources/js/Pages/Guest/Vereine.vue', 'resources/js/Pages/Auth/Dashboard/ClubCockpit/Index.vue'],
                    ['organization', 'club_onboarding'],
                ),
                'sponsor_measurement' => self::surface(
                    ['resources/js/Pages/Guest/Sponsors.vue', 'resources/js/Pages/Auth/Dashboard/SponsorWorkspace/Index.vue'],
                    ['sponsor'],
                ),
                'recruiting_to_team' => self::surface(
                    ['resources/js/Pages/Guest/Jobs.vue', 'resources/js/Pages/Auth/Dashboard/Recruiting/Index.vue'],
                    ['recruiting'],
                ),
            ],
            'guest_surfaces' => [
                'seo_delivery' => self::surface([
                    'app/Support/LocalizedPublicUrl.php',
                    'app/Support/SeoMeta.php',
                    'resources/js/Components/Guest/SeoHead.vue',
                    'resources/views/app.blade.php',
                    'routes/guest.php',
                ], ['guest_seo']),
                'navigation' => self::surface([
                    'resources/js/Components/Guest/Nav.vue',
                    'resources/js/Components/Guest/Subnav.vue',
                    'resources/js/Components/Guest/Footer.vue',
                ]),
                'club_discovery' => self::surface(['resources/js/Pages/Guest/Vereine.vue'], ['organization', 'public_discovery']),
                'recruiting' => self::surface(['resources/js/Pages/Guest/Jobs.vue'], ['recruiting']),
                'marketplace' => self::surface(['resources/js/Pages/Guest/Marketplace.vue'], ['commerce']),
                'learning' => self::surface(['resources/js/Pages/Guest/E-Learning.vue']),
                'events' => self::surface(['resources/js/Pages/Guest/Events.vue']),
                'sponsors' => self::surface(['resources/js/Pages/Guest/Sponsors.vue'], ['sponsor']),
                'agency' => self::surface(['resources/js/Pages/Guest/Werbeagentur.vue'], ['sponsor']),
                'pricing' => self::surface(['resources/js/Pages/Guest/Pricing.vue']),
            ],
            'mail' => [
                'template_keys' => array_keys(EmailTemplate::definitions()),
                'core_notifications' => [
                    'AdminInvoiceCreated',
                    'AdminInvoiceStatusUpdated',
                    'ClubInvoiceCreated',
                    'ClubRegistrationReviewRequested',
                    'ClubRegistrationSubmitted',
                    'CommerceReturnStatusUpdated',
                    'MobilePasswordResetRequested',
                    'MobileVerifyEmail',
                    'MyCustomResetPassword',
                    'OutfitDeliveryStatusUpdated',
                    'OutfitPaymentDunningNotice',
                    'OutfitPaymentExpired',
                    'OutfitPaymentReminder',
                    'TrainerApplicationStatusUpdated',
                    'TrainerRegistrationReviewRequested',
                    'VerifyEmailNotification',
                ],
                'catalogs' => ['core_mail', 'email_templates'],
                'recipient_locale_source' => 'App\\Models\\User::preferredLocale',
            ],
            'direction_contract' => [
                'server_html' => 'resources/views/app.blade.php',
                'client_runtime' => 'resources/js/app.js',
                'global_styles' => 'resources/css/app.css',
                'guest_shells' => [
                    'resources/js/Components/Guest/Nav.vue',
                    'resources/js/Components/Guest/Subnav.vue',
                    'resources/js/Components/Guest/Footer.vue',
                ],
                'layout_rule' => 'Use logical start/end and inline spacing; reserve explicit LTR for identifiers such as IBAN, URLs, and codes.',
            ],
            'manual_gate' => 'native_localization_qa',
        ];
    }

    /** @return array{sources: array<int, string>, server_catalogs: array<int, string>} */
    private static function surface(array $sources, array $serverCatalogs = []): array
    {
        return ['sources' => $sources, 'server_catalogs' => $serverCatalogs];
    }
}

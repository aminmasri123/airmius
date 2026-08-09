<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\CriticalJourneyRegistry;
use App\Support\EmailTemplate;
use App\Support\LocalizationAcceptanceRegistry;
use App\Support\LocalizationReadinessReport;
use App\Support\LocalizedMail;
use App\Support\SupportedLocale;
use DateTimeImmutable;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalizationAcceptanceContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_registry_covers_every_critical_journey_guest_experience_and_mail_channel(): void
    {
        $registry = LocalizationAcceptanceRegistry::definitions();

        $this->assertSame('localized-experience.v1', $registry['contract']);
        $this->assertSame(SupportedLocale::ALL, $registry['locales']['supported']);
        $this->assertSame(['de' => 'ltr', 'en' => 'ltr', 'fr' => 'ltr', 'ar' => 'rtl'], $registry['locales']['directions']);
        $this->assertSame(array_keys(CriticalJourneyRegistry::definitions()), array_keys($registry['critical_journeys']));
        $this->assertGreaterThanOrEqual(9, count($registry['guest_surfaces']));
        $this->assertSame(array_keys(EmailTemplate::definitions()), $registry['mail']['template_keys']);
        $this->assertCount(16, $registry['mail']['core_notifications']);
        $this->assertSame('native_localization_qa', $registry['manual_gate']);

        foreach (['critical_journeys', 'guest_surfaces'] as $group) {
            foreach ($registry[$group] as $surface) {
                foreach ($surface['sources'] as $source) {
                    $this->assertFileExists(base_path($source));
                }

                foreach ($surface['server_catalogs'] as $catalog) {
                    foreach (SupportedLocale::ALL as $locale) {
                        $this->assertFileExists(lang_path($locale.'/'.$catalog.'.php'));
                    }
                }
            }
        }
    }

    public function test_server_mail_catalogs_have_key_placeholder_encoding_and_arabic_parity(): void
    {
        $integrity = LocalizationReadinessReport::make()['server_mail_integrity'];

        $this->assertSame(['core_mail', 'email_templates'], $integrity['catalogs']);
        $this->assertSame(29, $integrity['template_count']);
        $this->assertSame(16, $integrity['core_notification_count']);
        $this->assertGreaterThan(200, $integrity['source_key_count']);
        $this->assertTrue($integrity['key_parity']);
        $this->assertTrue($integrity['placeholder_parity']);
        $this->assertTrue($integrity['arabic_has_arabic_glyphs']);
        $this->assertSame(0, $integrity['corrupt_target_values']);
    }

    public function test_user_locale_preference_is_normalized_for_queued_notifications(): void
    {
        $user = new User;
        $this->assertInstanceOf(HasLocalePreference::class, $user);

        foreach (['de-DE' => 'de', 'en_US' => 'en', 'fr-FR' => 'fr', 'ar-SA' => 'ar', 'unsupported' => 'de'] as $input => $expected) {
            $user->language = $input;
            $this->assertSame($expected, $user->preferredLocale());
        }
    }

    public function test_legacy_mail_templates_resolve_from_the_active_notification_locale(): void
    {
        $expected = [
            'de' => 'Passwort zurücksetzen',
            'en' => 'Reset password',
            'fr' => 'Réinitialiser le mot de passe',
            'ar' => 'إعادة تعيين كلمة المرور',
        ];

        foreach ($expected as $locale => $subject) {
            app()->setLocale($locale);
            $password = EmailTemplate::content('password_reset', ['expires_minutes' => 60]);
            $order = EmailTemplate::content('commerce_order_completed', [
                'name' => 'Sam',
                'order_title' => 'Pro',
                'amount' => '10 EUR',
            ]);

            $this->assertSame($subject, $password['subject']);
            $this->assertStringNotContainsString('{{ expires_minutes }}', $password['body']);
            $this->assertNotSame('', $order['subject']);
            $this->assertStringNotContainsString('{{ order_title }}', $order['body']);
        }

        app()->setLocale(SupportedLocale::DEFAULT);
    }

    public function test_core_mail_formats_copy_dates_and_money_for_each_recipient_locale(): void
    {
        $date = new DateTimeImmutable('2026-08-09 12:00:00', new \DateTimeZone('Europe/Berlin'));

        foreach (SupportedLocale::ALL as $locale) {
            $recipient = (object) ['name' => 'Sam', 'language' => $locale];
            $mail = LocalizedMail::for($recipient);

            $this->assertSame($locale, $mail->locale);
            $this->assertStringContainsString('Sam', $mail->greeting($recipient));
            $this->assertNotSame('', $mail->date($date));
            $this->assertMatchesRegularExpression('/\p{N}/u', $mail->money(10, 'EUR'));
        }

        $this->assertMatchesRegularExpression('/\p{Arabic}/u', LocalizedMail::for((object) ['language' => 'ar'])->text('verification.subject'));
    }

    public function test_core_notifications_do_not_embed_a_direct_user_facing_mail_literal(): void
    {
        $notifications = LocalizationAcceptanceRegistry::definitions()['mail']['core_notifications'];
        $literalPattern = '/->(?:subject|greeting|line|action|salutation)\(\s*[\'\"]/';

        foreach ($notifications as $notification) {
            $source = $this->source('app/Notifications/'.$notification.'.php');

            $this->assertStringContainsString('LocalizedMail', $source, $notification.' must resolve the recipient locale.');
            $this->assertDoesNotMatchRegularExpression($literalPattern, $source, $notification.' contains direct mail copy.');
            $this->assertDoesNotMatchRegularExpression('/�|\?(?:ffnen|ffentlich)/u', $source, $notification.' contains corrupt copy.');
        }
    }

    public function test_arabic_direction_is_applied_server_side_client_side_and_in_guest_shells(): void
    {
        $blade = $this->source('resources/views/app.blade.php');
        $app = $this->source('resources/js/app.js');
        $css = $this->source('resources/css/app.css');

        $this->assertStringContainsString('SupportedLocale::direction(app()->getLocale())', $blade);
        $this->assertStringContainsString('document.documentElement.dir = direction', $app);
        $this->assertStringContainsString("classList.toggle('is-rtl'", $app);
        $this->assertStringContainsString('html[dir="rtl"] body', $css);

        $physicalDirectionPattern = '/\b(?:text-(?:left|right)|(?:m|p)[lr]-|(?:left|right)-|border-[lr](?:-|\b))|direction:\s*(?:ltr|rtl)/';
        foreach (LocalizationAcceptanceRegistry::definitions()['direction_contract']['guest_shells'] as $shell) {
            $this->assertDoesNotMatchRegularExpression(
                $physicalDirectionPattern,
                $this->source($shell),
                $shell.' must use logical direction utilities.',
            );
        }

        $arabicAuto = $this->source('resources/js/lang/auto/ar.json');
        $this->assertStringNotContainsString('إغلاق Dialo', $arabicAuto);
        $this->assertStringContainsString('إغلاق مربع الحوار', $arabicAuto);
    }

    private function source(string $path): string
    {
        $source = file_get_contents(base_path($path));
        $this->assertIsString($source);

        return $source;
    }
}

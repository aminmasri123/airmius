<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Setting;
use App\Support\EmailTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Jetstream\Features;
use Tests\TestCase;

class DeleteAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_accounts_can_be_deleted(): void
    {
        if (! Features::hasAccountDeletionFeatures()) {
            $this->markTestSkipped('Account deletion is not enabled.');
        }

        $this->actingAs($user = User::factory()->create());
        Notification::fake();

        $this
            ->withSession([
                'account_deletion_confirmation' => [
                    'user_id' => $user->id,
                    'code_hash' => Hash::make('123456'),
                    'expires_at' => now()->addMinutes(15)->timestamp,
                ],
            ])
            ->delete('/user', [
                'code' => '123456',
            ]);

        $this->assertNull($user->fresh());
    }

    public function test_correct_code_must_be_provided_before_account_can_be_deleted(): void
    {
        if (! Features::hasAccountDeletionFeatures()) {
            $this->markTestSkipped('Account deletion is not enabled.');
        }

        $this->actingAs($user = User::factory()->create());
        Notification::fake();

        $this
            ->withSession([
                'account_deletion_confirmation' => [
                    'user_id' => $user->id,
                    'code_hash' => Hash::make('123456'),
                    'expires_at' => now()->addMinutes(15)->timestamp,
                ],
            ])
            ->delete('/user', [
                'code' => 'wrong-code',
            ]);

        $this->assertNotNull($user->fresh());
    }

    public function test_legacy_account_deletion_email_template_is_repaired_with_german_umlauts(): void
    {
        $templates = EmailTemplate::defaultsForValidation();
        $templates['account_deletion_code'] = [
            'subject' => 'Bestaetigungscode zur Kontoloeschung',
            'greeting' => 'Hallo,',
            'body' => 'Du kannst dein Konto loeschen. Dein Bestaetigungscode ist 778503 und 15 Minuten gueltig.',
            'action_label' => '',
        ];

        Setting::setValue(EmailTemplate::SETTINGS_KEY, json_encode($templates));

        $migration = require base_path('database/migrations/2026_08_02_000004_fix_legacy_german_account_deletion_email_template.php');
        $migration->up();

        $content = EmailTemplate::content('account_deletion_code', ['code' => '778503'], 'de');

        $this->assertSame('Bestätigungscode zur Kontolöschung', $content['subject']);
        $this->assertStringContainsString('Bestätigungscode lautet: 778503', $content['body']);
        $this->assertStringContainsString('15 Minuten gültig', $content['body']);
        $this->assertStringNotContainsString('Bestaetigung', $content['subject'].$content['body']);
        $this->assertStringNotContainsString('loesch', $content['body']);
        $this->assertStringNotContainsString('guelt', $content['body']);
    }
}

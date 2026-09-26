<?php

namespace Tests\Feature;

use App\Models\ExternalProviderUsageEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AiCommunicationDraftApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_generate_editable_communication_draft_with_source_context_only(): void
    {
        config([
            'airmius_ai.enabled' => true,
            'airmius_ai.features.communication_drafts.enabled' => true,
            'airmius_ai.features.communication_drafts.primary_provider' => 'openai',
            'airmius_ai.features.communication_drafts.fallback_provider' => null,
            'airmius_ai.providers.openai.api_key' => 'test-key',
            'airmius_ai.providers.openai.model' => 'gpt-test',
        ]);

        Http::fake([
            'api.openai.com/*' => Http::response([
                'output' => [[
                    'content' => [[
                        'type' => 'output_text',
                        'text' => json_encode([
                            'type' => 'invitation',
                            'subject' => 'Einladung zum Helferabend',
                            'body' => 'Hallo zusammen, bitte prueft den Termin und gebt Rueckmeldung.',
                            'summary' => 'Entwurf fuer die Einladung.',
                            'source_context' => [[
                                'label' => 'Terminnotiz',
                                'reference' => 'event:42',
                                'excerpt' => 'Helferabend am Freitag um 18 Uhr.',
                            ]],
                            'assumptions' => ['Empfaengerkreis muss geprueft werden.'],
                            'warnings' => ['Nicht automatisch versenden.'],
                            'next_steps' => ['Entwurf bearbeiten und manuell senden.'],
                            'requires_review' => true,
                        ]),
                    ]],
                ]],
                'usage' => [
                    'input_tokens' => 123,
                    'output_tokens' => 88,
                ],
            ]),
        ]);

        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/ai/communication-drafts', [
            'type' => 'invitation',
            'tone' => 'freundlich',
            'audience' => 'Vereinshelfer',
            'purpose' => 'Zum Helferabend einladen',
            'locale' => 'de',
            'channel' => 'email',
            'instructions' => 'Kurz halten.',
            'source_context' => [[
                'label' => 'Terminnotiz',
                'reference' => 'event:42',
                'excerpt' => 'Helferabend am Freitag um 18 Uhr.',
            ]],
        ])
            ->assertOk()
            ->assertJsonPath('data.type', 'invitation')
            ->assertJsonPath('data.subject', 'Einladung zum Helferabend')
            ->assertJsonPath('data.editable', true)
            ->assertJsonPath('data.draft_only', true)
            ->assertJsonPath('data.auto_execute', false)
            ->assertJsonPath('data.needs_user_confirmation', true)
            ->assertJsonPath('data.action_required', 'review_edit_and_send_manually')
            ->assertJsonPath('data.source_context.0.reference', 'event:42')
            ->assertJsonPath('data.warnings.0', 'Nicht automatisch versenden.');

        Http::assertSent(function (Request $request) {
            $payload = $request->data();

            return $request->url() === 'https://api.openai.com/v1/responses'
                && data_get($payload, 'model') === 'gpt-test'
                && str_contains(data_get($payload, 'input.0.content.0.text'), 'ausschließlich einen bearbeitbaren Entwurf')
                && str_contains(data_get($payload, 'input.0.content.0.text'), 'darf keine Nachricht senden');
        });

        $this->assertDatabaseHas('external_provider_usage_events', [
            'area' => 'ai',
            'provider' => 'openai',
            'service' => 'text',
            'operation' => 'communication_draft_generation',
            'status' => 'ok',
        ]);
        $this->assertSame(1, ExternalProviderUsageEvent::query()->count());
    }

    public function test_communication_draft_requires_source_context(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/ai/communication-drafts', [
            'type' => 'message',
            'audience' => 'Mitglieder',
            'purpose' => 'Informieren',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['source_context']);

        Http::assertNothingSent();
    }
}

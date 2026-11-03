<?php

namespace Tests\Feature;

use App\Services\Ai\AirmiusAiService;
use Illuminate\Support\Facades\Http;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

class TrainingAiBatchTest extends TestCase
{
    private function response(int $count): array
    {
        $item = ['title' => 'Lockerer Lauf', 'duration_minutes' => 30, 'adaptation' => '5 Minuten kürzer laufen',
            'steps' => array_map(fn ($phase) => ['phase' => $phase, 'exercise' => 'Lockeres Laufen',
                'prescription' => '10 Minuten', 'minutes' => 10, 'instruction' => 'Entspannt laufen',
                'rest' => 'Keine Pause', 'intensity' => 'Sprechtempo'], ['warmup', 'main', 'cooldown'])];
        return ['choices' => [['message' => ['content' => json_encode(['title' => 'Plan', 'items' => array_fill(0, $count, $item)])]]],
            'usage' => ['prompt_tokens' => 20, 'completion_tokens' => 50]];
    }

    private function generate(array $context, ?float $deadline = null): array
    {
        config(['airmius_ai.providers.ionos.api_key' => 'test-token', 'airmius_ai.providers.ionos.base_url' => 'https://ionos.test/v1']);
        return (new ReflectionMethod(AirmiusAiService::class, 'generateTrainingPlanBatches'))
            ->invoke(app(AirmiusAiService::class), 'ionos', $context, [], $deadline ?? microtime(true) + 540);
    }

    public function test_complete_four_week_plan_is_assembled_from_four_small_requests(): void
    {
        Http::preventStrayRequests();
        Http::fake(['ionos.test/*' => Http::response($this->response(3))]);
        $result = $this->generate(['weeks' => 4, 'sessions_per_week' => 3]);
        Http::assertSentCount(4);
        $this->assertCount(12, $result['data']['items']);
        $this->assertSame([1,1,1,2,2,2,3,3,3,4,4,4], array_column($result['data']['items'], 'week'));
        $this->assertSame(4, $result['data']['settings']['weeks']);
        $this->assertSame(200, $result['usage']['output_tokens']);
        Http::assertSent(fn ($request) => $request['max_tokens'] === 4000 && str_contains($request['messages'][0]['content'], 'Woche 4'));
    }

    public function test_partial_last_batch_stays_in_the_correct_week(): void
    {
        Http::fake(['ionos.test/*' => Http::sequence()->push($this->response(3))->push($this->response(2))]);
        $result = $this->generate(['weeks' => 1, 'sessions_per_week' => 5]);
        $this->assertCount(5, $result['data']['items']);
        $this->assertSame([1,1,1,1,1], array_column($result['data']['items'], 'week'));
    }

    public function test_missing_sessions_reject_the_whole_plan(): void
    {
        Http::fake(['ionos.test/*' => Http::sequence()->push($this->response(3))->push($this->response(1))->push($this->response(1))]);
        $this->expectException(RuntimeException::class);
        $this->generate(['weeks' => 2, 'sessions_per_week' => 3]);
    }

    public function test_expired_budget_sends_no_request(): void
    {
        Http::fake();
        try {
            $this->generate(['weeks' => 4], microtime(true) - 1);
            $this->fail('Expired budget accepted');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('Zeitlimit', $error->getMessage());
            Http::assertNothingSent();
        }
    }

    public function test_invalid_section_is_corrected_without_repeating_completed_sections(): void
    {
        $bad = $this->response(3);
        $data = json_decode($bad['choices'][0]['message']['content'], true);
        $data['items'][0]['steps'][0]['exercise'] = 'Aufwärmen';
        $bad['choices'][0]['message']['content'] = json_encode($data);
        Http::fake(['ionos.test/*' => Http::sequence()->push($this->response(3))->push($bad)->push($this->response(3))]);
        $result = $this->generate(['weeks' => 2, 'sessions_per_week' => 3]);
        Http::assertSentCount(3);
        $this->assertCount(6, $result['data']['items']);
        $this->assertSame(150, $result['usage']['output_tokens']);
        Http::assertSent(fn ($request) => str_contains($request['messages'][0]['content'], 'KORREKTUR:'));
    }
}

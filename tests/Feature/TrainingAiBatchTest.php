<?php

namespace Tests\Feature;

use App\Services\Ai\AirmiusAiService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
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

    private function generate(array $context, ?float $deadline = null, ?string $checkpointKey = null): array
    {
        config(['airmius_ai.providers.ionos.api_key' => 'test-token', 'airmius_ai.providers.ionos.base_url' => 'https://ionos.test/v1']);
        return (new ReflectionMethod(AirmiusAiService::class, 'generateTrainingPlanBatches'))
            ->invoke(app(AirmiusAiService::class), 'ionos', $context, [], $deadline ?? microtime(true) + 540, $checkpointKey);
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
        $data['items'][1]['duration_minutes'] = 45;
        unset($data['items'][2]['adaptation']);
        $bad['choices'][0]['message']['content'] = json_encode($data);
        Http::fake(['ionos.test/*' => Http::sequence()->push($this->response(3))->push($bad)->push($this->response(3))]);
        $result = $this->generate(['weeks' => 2, 'sessions_per_week' => 3]);
        Http::assertSentCount(3);
        $this->assertCount(6, $result['data']['items']);
        $this->assertSame(150, $result['usage']['output_tokens']);
        Http::assertSent(fn ($request) => str_contains($request['messages'][0]['content'], 'KORREKTUR:'));
        Http::assertSent(fn ($request) => str_contains($request['messages'][0]['content'], 'Einheit 1: Schritt 1')
            && str_contains($request['messages'][0]['content'], 'Einheit 3: adaptation fehlt'));
    }

    public function test_duration_is_calculated_from_steps_without_another_ai_request(): void
    {
        $response = $this->response(3);
        $data = json_decode($response['choices'][0]['message']['content'], true);
        $data['items'][0]['duration_minutes'] = 45;
        $data['items'][0]['steps'][1]['minutes'] = 22.5;
        $response['choices'][0]['message']['content'] = json_encode($data);
        Http::fake(['ionos.test/*' => Http::response($response)]);
        $result = $this->generate(['weeks' => 1, 'sessions_per_week' => 3]);
        $this->assertSame(43, $result['data']['items'][0]['duration_minutes']);
        $this->assertSame(22.5, $result['data']['items'][0]['steps'][1]['minutes']);
        Http::assertSentCount(1);
    }

    public function test_failed_plan_resumes_encrypted_sections_without_regenerating_them(): void
    {
        config(['cache.default' => 'array', 'airmius_ai.features.training_plan_generation.concurrency' => 1]);
        Http::fake(['ionos.test/*' => Http::sequence()->push($this->response(3))->push([], 503)->push($this->response(3))]);
        try {
            $this->generate(['weeks' => 2, 'sessions_per_week' => 3], null, 'test-user-one');
            $this->fail('Provider failure expected');
        } catch (RuntimeException) {
            $this->assertIsString(Cache::get('test-user-one'));
            $this->assertStringNotContainsString('Lockerer Lauf', Cache::get('test-user-one'));
            $this->assertNull(Cache::get('test-user-two'));
        }
        $result = $this->generate(['weeks' => 2, 'sessions_per_week' => 3], null, 'test-user-one');
        Http::assertSentCount(3);
        $this->assertCount(6, $result['data']['items']);
        $this->assertNull(Cache::get('test-user-one'));
        $this->assertSame(50, $result['usage']['output_tokens']);
    }

    public function test_following_weeks_use_compact_output_and_distinct_week_assignments(): void
    {
        config(['airmius_ai.features.training_plan_generation.concurrency' => 2]);
        Http::fake(['ionos.test/*' => Http::response($this->response(3))]);
        $result = $this->generate(['weeks' => 4, 'sessions_per_week' => 3]);
        $prompts = Http::recorded()->map(fn ($pair) => $pair[0]['messages'][0]['content']);
        $this->assertStringNotContainsString('FOLGEABSCHNITT:', $prompts[0]);
        foreach ([1 => 2, 2 => 3, 3 => 4] as $index => $week) {
            $this->assertStringContainsString('FOLGEABSCHNITT:', $prompts[$index]);
            $this->assertStringContainsString("ab Einheit 1 in Woche {$week}.", $prompts[$index]);
            $schema = explode('JSON-Schema:', $prompts[$index])[1];
            $this->assertStringNotContainsString('"convincing_explanation"', $schema);
            $this->assertStringContainsString('"steps"', $schema);
        }
        $this->assertSame([1,1,1,2,2,2,3,3,3,4,4,4], array_column($result['data']['items'], 'week'));
    }
}

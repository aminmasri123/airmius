<?php

namespace Tests\Unit;

use App\Services\Ai\AiAssistiveSuggestionService;
use Tests\TestCase;

class AiAssistiveSuggestionServiceTest extends TestCase
{
    public function test_receipt_booking_suggestion_never_books_automatically_and_explains_confidence(): void
    {
        $suggestion = app(AiAssistiveSuggestionService::class)->suggestReceiptBooking([
            'receipt_date' => '2026-09-26',
            'amount_cents' => 1299,
            'vendor' => 'Sportshop Ausruestung',
            'description' => 'Trikots und Baelle',
        ], [
            ['code' => '3400', 'label' => 'Sportmaterial', 'keywords' => ['trikot', 'baelle', 'ausruestung']],
        ]);

        $this->assertSame('receipt_booking_suggestion', $suggestion['type']);
        $this->assertFalse($suggestion['can_self_execute']);
        $this->assertTrue($suggestion['requires_manual_confirmation']);
        $this->assertGreaterThan(0.8, $suggestion['confidence']);
        $this->assertSame('3400', $suggestion['draft']['suggested_account']['code']);
        $this->assertStringContainsString('Amount', $suggestion['explanation']);
    }

    public function test_operations_suggestions_are_explainable_and_non_executing(): void
    {
        $result = app(AiAssistiveSuggestionService::class)->operationsSuggestions([
            'calendar_conflicts' => ['team-a: hall overlap'],
            'resource_shortages' => ['2 bibs missing'],
            'data_quality_flags' => ['member birthdate missing'],
            'training_context' => ['high load after matchday'],
        ]);

        $this->assertSame('operations_suggestions', $result['type']);
        $this->assertFalse($result['can_self_execute']);
        $this->assertTrue($result['requires_manual_confirmation']);
        $this->assertSame(['schedule', 'resource', 'data_quality', 'training'], collect($result['suggestions'])->pluck('kind')->all());

        foreach ($result['suggestions'] as $suggestion) {
            $this->assertFalse($suggestion['can_self_execute']);
            $this->assertTrue($suggestion['requires_manual_confirmation']);
            $this->assertNotEmpty($suggestion['explanation']);
        }
    }
}

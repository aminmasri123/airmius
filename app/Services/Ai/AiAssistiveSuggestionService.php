<?php

namespace App\Services\Ai;

final class AiAssistiveSuggestionService
{
    public function suggestReceiptBooking(array $receipt, array $candidateAccounts = []): array
    {
        $amount = isset($receipt['amount_cents']) ? (int) $receipt['amount_cents'] : null;
        $account = $this->bestAccount($receipt, $candidateAccounts);
        $confidence = $amount !== null && $account !== null ? 0.82 : 0.48;

        return [
            'type' => 'receipt_booking_suggestion',
            'can_self_execute' => false,
            'requires_manual_confirmation' => true,
            'confidence' => $confidence,
            'explanation' => $account
                ? 'Amount, receipt date, vendor hints and account keywords produced this draft.'
                : 'Insufficient account evidence; a human must choose the booking account.',
            'draft' => [
                'booking_date' => $receipt['booking_date'] ?? $receipt['receipt_date'] ?? null,
                'amount_cents' => $amount,
                'currency' => $receipt['currency'] ?? 'EUR',
                'counterparty' => $receipt['vendor'] ?? null,
                'booking_text' => $receipt['description'] ?? $receipt['vendor'] ?? 'Receipt draft',
                'suggested_account' => $account,
            ],
            'audit_event' => 'ai.receipt_booking_suggestions.suggested',
        ];
    }

    public function operationsSuggestions(array $signals): array
    {
        return [
            'type' => 'operations_suggestions',
            'can_self_execute' => false,
            'requires_manual_confirmation' => true,
            'suggestions' => array_values(array_filter([
                $this->suggestSchedule($signals),
                $this->suggestResource($signals),
                $this->suggestDataQuality($signals),
                $this->suggestTraining($signals),
            ])),
        ];
    }

    private function bestAccount(array $receipt, array $candidateAccounts): ?array
    {
        $haystack = strtolower(implode(' ', array_filter([
            $receipt['vendor'] ?? null,
            $receipt['description'] ?? null,
            $receipt['category'] ?? null,
        ])));

        foreach ($candidateAccounts as $account) {
            foreach (($account['keywords'] ?? []) as $keyword) {
                if ($keyword !== '' && str_contains($haystack, strtolower((string) $keyword))) {
                    return [
                        'code' => $account['code'] ?? null,
                        'label' => $account['label'] ?? null,
                    ];
                }
            }
        }

        return null;
    }

    private function suggestSchedule(array $signals): ?array
    {
        return empty($signals['calendar_conflicts']) ? null : $this->suggestion(
            'schedule',
            'Calendar conflicts detected; propose a reviewed alternative slot.',
            $signals['calendar_conflicts'],
        );
    }

    private function suggestResource(array $signals): ?array
    {
        return empty($signals['resource_shortages']) ? null : $this->suggestion(
            'resource',
            'Resource demand exceeds available capacity; propose reassignment before confirmation.',
            $signals['resource_shortages'],
        );
    }

    private function suggestDataQuality(array $signals): ?array
    {
        return empty($signals['data_quality_flags']) ? null : $this->suggestion(
            'data_quality',
            'Data quality markers indicate records that need manual cleanup.',
            $signals['data_quality_flags'],
        );
    }

    private function suggestTraining(array $signals): ?array
    {
        return empty($signals['training_context']) ? null : $this->suggestion(
            'training',
            'Training context can be turned into ideas, but plan changes require coach review.',
            $signals['training_context'],
        );
    }

    private function suggestion(string $kind, string $explanation, mixed $evidence): array
    {
        return [
            'kind' => $kind,
            'explanation' => $explanation,
            'evidence' => $evidence,
            'can_self_execute' => false,
            'requires_manual_confirmation' => true,
        ];
    }
}

<?php

namespace App\Services;

use App\Models\Club;
use App\Models\ClubDunningEvent;
use App\Models\ClubDunningRule;
use App\Models\Invoice;
use App\Models\User;
use App\Support\PaymentStatusMachine;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClubDunningService
{
    public const CHANNELS = ['email', 'letter', 'portal', 'sepa_notice'];
    public const DELIVERY_STATUSES = ['pending', 'sent', 'delivered', 'bounced', 'failed'];

    public function __construct(private readonly PaymentStatusMachine $statusMachine) {}

    public function createRule(Club $club, array $data, User $actor): ClubDunningRule
    {
        return DB::transaction(function () use ($club, $data, $actor) {
            $version = ((int) ClubDunningRule::query()->where('club_id', $club->id)->max('version')) + 1;

            if (($data['is_active'] ?? true) === true) {
                ClubDunningRule::query()->where('club_id', $club->id)->update(['is_active' => false]);
            }

            return ClubDunningRule::query()->create([
                'club_id' => $club->id,
                'version' => $version,
                'name' => $data['name'],
                'stages' => $this->normalizeStages($data['stages']),
                'exceptions' => $data['exceptions'] ?? [],
                'channel_requirements' => $data['channel_requirements'] ?? [],
                'is_active' => $data['is_active'] ?? true,
                'created_by' => $actor->id,
            ]);
        });
    }

    public function record(Invoice $invoice, array $data, User $actor): ClubDunningEvent
    {
        return DB::transaction(function () use ($invoice, $data, $actor) {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            $rule = $this->ruleFor($invoice, $data['rule_id'] ?? null);
            $stageNumber = (int) $data['stage'];
            $stage = collect($rule?->stages ?? [])->first(fn (array $item) => (int) ($item['stage'] ?? 0) === $stageNumber) ?? [];
            if ($rule && $stage === []) {
                throw ValidationException::withMessages(['stage' => 'The selected dunning stage does not exist in the active rule.']);
            }
            $idempotencyKey = $data['idempotency_key'] ?? $this->defaultIdempotencyKey($invoice, $stageNumber, $data);

            $existing = ClubDunningEvent::query()
                ->where('invoice_id', $invoice->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if ($existing) {
                return $existing;
            }

            if ($this->statusMachine->isImmutableClaimStatus($invoice->claim_status ?: $invoice->status)) {
                throw ValidationException::withMessages(['invoice' => 'Paid or cancelled claims cannot be dunned.']);
            }
            if (! $invoice->due_date) {
                throw ValidationException::withMessages(['invoice' => 'An invoice without a due date cannot be dunned.']);
            }
            $eligibleOn = $invoice->due_date->copy()->addDays((int) ($stage['days_after_due'] ?? 0))->startOfDay();
            $evaluatedOn = isset($data['evaluated_on'])
                ? \Illuminate\Support\Carbon::parse($data['evaluated_on'])->startOfDay()
                : now()->startOfDay();
            if ($eligibleOn->greaterThan($evaluatedOn)) {
                throw ValidationException::withMessages([
                    'stage' => 'This dunning stage is available on '.$eligibleOn->toDateString().'.',
                ]);
            }

            $nextClaimStatus = PaymentStatusMachine::CLAIM_OVERDUE;
            $this->statusMachine->assertClaimTransition($invoice->claim_status ?: $invoice->status, $nextClaimStatus);
            $invoice->forceFill([
                'status' => $invoice->status === 'cancelled' ? 'cancelled' : 'overdue',
                'claim_status' => $nextClaimStatus,
                'reminder_sent_at' => now(),
            ])->save();

            return ClubDunningEvent::query()->create([
                'club_id' => $invoice->club_id,
                'invoice_id' => $invoice->id,
                'club_dunning_rule_id' => $rule?->id,
                'rule_version' => $rule?->version,
                'stage' => $stageNumber,
                'status' => ($data['exception_reason'] ?? null) ? 'exception' : 'recorded',
                'channel' => $data['channel'] ?? ($stage['channel'] ?? 'email'),
                'delivery_status' => $data['delivery_status'] ?? 'pending',
                'delivered_at' => $data['delivered_at'] ?? null,
                'evidence_reference' => $data['evidence_reference'] ?? null,
                'fee_cents' => $data['fee_cents'] ?? ($stage['fee_cents'] ?? 0),
                'fee_invoice_number' => $data['fee_invoice_number'] ?? null,
                'blocks_service' => $data['blocks_service'] ?? ($stage['blocks_service'] ?? false),
                'is_exception' => (bool) ($data['exception_reason'] ?? false),
                'exception_reason' => $data['exception_reason'] ?? null,
                'idempotency_key' => $idempotencyKey,
                'created_by' => $actor->id,
                'payload' => [
                    'rule_stage' => $stage,
                    'note' => $data['note'] ?? null,
                ],
            ]);
        });
    }

    private function ruleFor(Invoice $invoice, ?int $ruleId): ?ClubDunningRule
    {
        $query = ClubDunningRule::query()->where('club_id', $invoice->club_id);

        return $ruleId
            ? $query->whereKey($ruleId)->firstOrFail()
            : $query->where('is_active', true)->latest('version')->first();
    }

    private function normalizeStages(array $stages): array
    {
        return collect($stages)
            ->map(fn (array $stage) => [
                'stage' => (int) $stage['stage'],
                'days_after_due' => (int) ($stage['days_after_due'] ?? 0),
                'fee_cents' => (int) ($stage['fee_cents'] ?? 0),
                'channel' => $stage['channel'] ?? 'email',
                'blocks_service' => (bool) ($stage['blocks_service'] ?? false),
            ])
            ->sortBy('stage')
            ->values()
            ->all();
    }

    private function defaultIdempotencyKey(Invoice $invoice, int $stage, array $data): string
    {
        return implode(':', [
            'invoice',
            $invoice->id,
            'dunning',
            $stage,
            $data['channel'] ?? 'email',
        ]);
    }
}

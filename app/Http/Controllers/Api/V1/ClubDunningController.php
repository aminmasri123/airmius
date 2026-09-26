<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\ClubDunningRule;
use App\Models\Invoice;
use App\Services\ClubDunningService;
use App\Support\ClubPermissions;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClubDunningController extends Controller
{
    public function __construct(private readonly ClubDunningService $dunning) {}

    public function storeRule(Request $request, Club $club)
    {
        abort_unless(ClubPermissions::allows($club, $request->user(), ClubPermissions::FINANCE_EDIT), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'stages' => ['required', 'array', 'min:1'],
            'stages.*.stage' => ['required', 'integer', 'min:1', 'max:10'],
            'stages.*.days_after_due' => ['nullable', 'integer', 'min:0', 'max:365'],
            'stages.*.fee_cents' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'stages.*.channel' => ['nullable', Rule::in(ClubDunningService::CHANNELS)],
            'stages.*.blocks_service' => ['nullable', 'boolean'],
            'exceptions' => ['nullable', 'array'],
            'channel_requirements' => ['nullable', 'array'],
        ]);

        $rule = $this->dunning->createRule($club, $data, $request->user());

        return response()->json(['data' => $this->rulePayload($rule)], 201);
    }

    public function record(Request $request, Club $club, Invoice $invoice)
    {
        abort_unless((int) $invoice->club_id === (int) $club->id, 404);
        abort_unless(ClubPermissions::allows($club, $request->user(), ClubPermissions::FINANCE_EDIT), 403);

        $data = $request->validate([
            'rule_id' => ['nullable', 'integer', 'exists:club_dunning_rules,id'],
            'stage' => ['required', 'integer', 'min:1', 'max:10'],
            'channel' => ['nullable', Rule::in(ClubDunningService::CHANNELS)],
            'delivery_status' => ['nullable', Rule::in(ClubDunningService::DELIVERY_STATUSES)],
            'delivered_at' => ['nullable', 'date'],
            'evidence_reference' => ['nullable', 'string', 'max:255'],
            'fee_cents' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'fee_invoice_number' => ['nullable', 'string', 'max:255'],
            'blocks_service' => ['nullable', 'boolean'],
            'exception_reason' => ['nullable', 'string', 'max:255'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $event = $this->dunning->record($invoice, $data, $request->user());

        return response()->json(['data' => $this->eventPayload($event->fresh())]);
    }

    private function rulePayload(ClubDunningRule $rule): array
    {
        return [
            'id' => $rule->id,
            'club_id' => $rule->club_id,
            'version' => $rule->version,
            'name' => $rule->name,
            'stages' => $rule->stages,
            'exceptions' => $rule->exceptions,
            'channel_requirements' => $rule->channel_requirements,
            'is_active' => $rule->is_active,
        ];
    }

    private function eventPayload($event): array
    {
        return [
            'id' => $event->id,
            'invoice_id' => $event->invoice_id,
            'rule_version' => $event->rule_version,
            'stage' => $event->stage,
            'status' => $event->status,
            'channel' => $event->channel,
            'delivery_status' => $event->delivery_status,
            'delivered_at' => $event->delivered_at?->toJSON(),
            'evidence_reference' => $event->evidence_reference,
            'fee_cents' => $event->fee_cents,
            'fee_invoice_number' => $event->fee_invoice_number,
            'blocks_service' => $event->blocks_service,
            'is_exception' => $event->is_exception,
            'exception_reason' => $event->exception_reason,
            'idempotency_key' => $event->idempotency_key,
        ];
    }
}

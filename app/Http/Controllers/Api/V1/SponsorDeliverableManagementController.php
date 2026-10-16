<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\Sponsor;
use App\Models\SponsorDeliverable;
use App\Models\SponsorPartnership;
use App\Services\SponsorOrganizationService;
use App\Support\ClubAuditLog;
use App\Support\ClubPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class SponsorDeliverableManagementController extends Controller
{
    public function __construct(private SponsorOrganizationService $organizations) {}

    public function store(Request $request, Sponsor $sponsor): JsonResponse
    {
        $clubId = $this->clubId($request, $sponsor);
        abort_unless($this->canEditSponsor($request, $sponsor, $clubId), 403);

        $data = $this->validated($request, $sponsor, $clubId);
        $deliverable = $sponsor->deliverables()->create($this->normalize($data, $clubId, $request));
        $this->audit($request, $deliverable, $deliverable->status === 'fulfilled' ? 'fulfilled' : 'created', ['fields' => array_keys($data)]);

        return response()->json([
            'message' => __('sponsor.flash.deliverable_created'),
            'data' => $this->payload($deliverable->load('responsible:id,name,email')),
        ], 201);
    }

    public function update(Request $request, Sponsor $sponsor, SponsorDeliverable $deliverable): JsonResponse
    {
        abort_unless((int) $deliverable->sponsor_id === (int) $sponsor->id, 404);
        $clubId = $this->clubId($request, $sponsor, $deliverable);
        abort_unless($this->canEditSponsor($request, $sponsor, $clubId), 403);

        $beforeStatus = $deliverable->status;
        $data = $this->validated($request, $sponsor, $clubId, $deliverable);
        $deliverable->update($this->normalize($data, $clubId, $request, $deliverable));
        $event = $beforeStatus !== 'fulfilled' && $deliverable->status === 'fulfilled' ? 'fulfilled' : 'updated';
        $this->audit($request, $deliverable->refresh(), $event, [
            'fields' => array_keys($data),
            'previous_status' => $beforeStatus,
        ]);

        return response()->json([
            'message' => __('sponsor.flash.deliverable_updated'),
            'data' => $this->payload($deliverable->load('responsible:id,name,email')),
        ]);
    }

    public function destroy(Request $request, Sponsor $sponsor, SponsorDeliverable $deliverable): JsonResponse
    {
        abort_unless((int) $deliverable->sponsor_id === (int) $sponsor->id, 404);
        $clubId = $this->clubId($request, $sponsor, $deliverable);
        abort_unless($this->canDeleteSponsor($request, $sponsor, $clubId), 403);

        $this->audit($request, $deliverable, 'deleted', ['status' => $deliverable->status]);
        $deliverable->delete();

        return response()->json(['message' => __('sponsor.flash.deliverable_deleted')]);
    }

    private function validated(Request $request, Sponsor $sponsor, int $clubId, ?SponsorDeliverable $deliverable = null): array
    {
        $responsibleRule = ['nullable', 'integer', Rule::exists('users', 'id')];
        if ($clubId) {
            $responsibleIds = Club::query()->findOrFail($clubId)
                ->users()
                ->pluck('users.id')
                ->push(Club::query()->whereKey($clubId)->value('owner_id'))
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();
            $responsibleRule[] = Rule::in($responsibleIds);
        }

        $data = $request->validate([
            'club_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'due_at' => ['nullable', 'date'],
            'responsible_user_id' => $responsibleRule,
            'status' => ['nullable', Rule::in(SponsorDeliverable::STATUSES)],
            'fulfillment_evidence' => ['nullable', 'string', 'max:5000'],
        ]);
        $status = $data['status'] ?? $deliverable?->status ?? 'planned';
        $evidence = array_key_exists('fulfillment_evidence', $data)
            ? $data['fulfillment_evidence']
            : $deliverable?->fulfillment_evidence;
        if ($status === 'fulfilled' && blank($evidence)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'fulfillment_evidence' => __('validation.required', ['attribute' => 'fulfillment_evidence']),
            ]);
        }

        return $data;
    }

    private function normalize(array $data, int $clubId, Request $request, ?SponsorDeliverable $deliverable = null): array
    {
        $status = $data['status'] ?? $deliverable?->status ?? 'planned';
        $data['status'] = $status;
        $data['club_id'] = $clubId;

        if ($status === 'fulfilled' && ! $deliverable?->fulfilled_at) {
            $data['fulfilled_at'] = now();
            $data['fulfilled_by'] = $request->user()->id;
        }

        if ($status !== 'fulfilled') {
            $data['fulfilled_at'] = null;
            $data['fulfilled_by'] = null;
        }

        return $data;
    }

    private function payload(SponsorDeliverable $deliverable): array
    {
        return [
            'id' => $deliverable->id,
            'sponsor_id' => $deliverable->sponsor_id,
            'club_id' => $deliverable->club_id,
            'title' => $deliverable->title,
            'location' => $deliverable->location,
            'starts_at' => $deliverable->starts_at?->toDateString(),
            'ends_at' => $deliverable->ends_at?->toDateString(),
            'due_at' => $deliverable->due_at?->toDateString(),
            'responsible_user_id' => $deliverable->responsible_user_id,
            'responsible' => $deliverable->responsible ? [
                'id' => $deliverable->responsible->id,
                'name' => $deliverable->responsible->name,
                'email' => $deliverable->responsible->email,
            ] : null,
            'status' => $deliverable->status,
            'fulfillment_evidence' => $deliverable->fulfillment_evidence,
            'fulfilled_at' => $deliverable->fulfilled_at?->toIso8601String(),
            'fulfilled_by' => $deliverable->fulfilled_by,
            'is_overdue' => $deliverable->isOverdue(),
        ];
    }

    private function audit(Request $request, SponsorDeliverable $deliverable, string $event, array $data = []): void
    {
        if (! $deliverable->club_id) {
            return;
        }

        $club = Club::query()->find($deliverable->club_id);
        if (! $club) {
            return;
        }

        ClubAuditLog::record($club, $request->user(), 'club.sponsor.deliverable.'.$event, $deliverable, array_merge([
            'sponsor_id' => $deliverable->sponsor_id,
            'deliverable_id' => $deliverable->id,
            'status' => $deliverable->status,
            'due_at' => $deliverable->due_at?->toDateString(),
            'has_evidence' => filled($deliverable->fulfillment_evidence),
        ], Arr::except($data, ['fulfillment_evidence'])));
    }

    private function clubId(Request $request, Sponsor $sponsor, ?SponsorDeliverable $deliverable = null): int
    {
        if ($sponsor->club_id) {
            return (int) $sponsor->club_id;
        }

        $clubId = (int) ($deliverable?->club_id ?? $request->integer('club_id'));
        abort_if($clubId <= 0, 422);
        abort_if($deliverable && $request->has('club_id') && $request->integer('club_id') !== $clubId, 422);
        abort_unless(SponsorPartnership::query()
            ->where('sponsor_id', $sponsor->id)
            ->where('club_id', $clubId)
            ->where('status', SponsorPartnership::STATUS_ACTIVE)
            ->exists(), 404);

        return $clubId;
    }

    private function canEditSponsor(Request $request, Sponsor $sponsor, int $clubId): bool
    {
        return $this->canManageGlobal($request)
            || ($sponsor->scope === 'platform' && $this->organizations->can($request->user(), $sponsor, 'partnerships'))
            || ClubPermissions::allows(Club::query()->findOrFail($clubId), $request->user(), ClubPermissions::SPONSORS_EDIT);
    }

    private function canDeleteSponsor(Request $request, Sponsor $sponsor, int $clubId): bool
    {
        return $this->canManageGlobal($request)
            || ($sponsor->scope === 'platform' && $this->organizations->can($request->user(), $sponsor, 'partnerships'))
            || ClubPermissions::allows(Club::query()->findOrFail($clubId), $request->user(), ClubPermissions::SPONSORS_DELETE);
    }

    private function canManageGlobal(Request $request): bool
    {
        return $request->user()->can('system.manage')
            || $request->user()->hasAnyRole(['super_admin', 'admin', 'sponsor_manager']);
    }
}

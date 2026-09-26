<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\Sponsor;
use App\Models\SponsorDeliverable;
use App\Support\ClubAuditLog;
use App\Support\ClubPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class SponsorDeliverableManagementController extends Controller
{
    public function store(Request $request, Sponsor $sponsor): JsonResponse
    {
        abort_unless($this->canEditSponsor($request, $sponsor), 403);

        $data = $this->validated($request, $sponsor);
        $deliverable = $sponsor->deliverables()->create($this->normalize($data, $sponsor, $request));
        $this->audit($request, $deliverable, $deliverable->status === 'fulfilled' ? 'fulfilled' : 'created', ['fields' => array_keys($data)]);

        return response()->json([
            'message' => __('sponsor.flash.deliverable_created'),
            'data' => $this->payload($deliverable->load('responsible:id,name,email')),
        ], 201);
    }

    public function update(Request $request, Sponsor $sponsor, SponsorDeliverable $deliverable): JsonResponse
    {
        abort_unless((int) $deliverable->sponsor_id === (int) $sponsor->id, 404);
        abort_unless($this->canEditSponsor($request, $sponsor), 403);

        $beforeStatus = $deliverable->status;
        $data = $this->validated($request, $sponsor);
        $deliverable->update($this->normalize($data, $sponsor, $request, $deliverable));
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
        abort_unless($this->canDeleteSponsor($request, $sponsor), 403);

        $this->audit($request, $deliverable, 'deleted', ['status' => $deliverable->status]);
        $deliverable->delete();

        return response()->json(['message' => __('sponsor.flash.deliverable_deleted')]);
    }

    private function validated(Request $request, Sponsor $sponsor): array
    {
        $responsibleRule = ['nullable', 'integer', Rule::exists('users', 'id')];
        if ($sponsor->club_id) {
            $responsibleIds = $sponsor->club()->firstOrFail()
                ->users()
                ->pluck('users.id')
                ->push($sponsor->club()->value('owner_id'))
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();
            $responsibleRule[] = Rule::in($responsibleIds);
        }

        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'due_at' => ['nullable', 'date'],
            'responsible_user_id' => $responsibleRule,
            'status' => ['nullable', Rule::in(SponsorDeliverable::STATUSES)],
            'fulfillment_evidence' => ['nullable', 'string', 'max:5000', 'required_if:status,fulfilled'],
        ]);
    }

    private function normalize(array $data, Sponsor $sponsor, Request $request, ?SponsorDeliverable $deliverable = null): array
    {
        $status = $data['status'] ?? $deliverable?->status ?? 'planned';
        $data['status'] = $status;
        $data['club_id'] = $sponsor->club_id;

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

    private function canEditSponsor(Request $request, Sponsor $sponsor): bool
    {
        return $this->canManageGlobal($request)
            || ($sponsor->club_id && ClubPermissions::allows($sponsor->club()->firstOrFail(), $request->user(), ClubPermissions::SPONSORS_EDIT));
    }

    private function canDeleteSponsor(Request $request, Sponsor $sponsor): bool
    {
        return $this->canManageGlobal($request)
            || ($sponsor->club_id && ClubPermissions::allows($sponsor->club()->firstOrFail(), $request->user(), ClubPermissions::SPONSORS_DELETE));
    }

    private function canManageGlobal(Request $request): bool
    {
        return $request->user()->can('finance.edit')
            || $request->user()->can('system.manage')
            || $request->user()->hasAnyRole(['super_admin', 'admin', 'sponsor_manager']);
    }
}

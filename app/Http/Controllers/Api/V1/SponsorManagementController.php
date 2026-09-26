<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\Sponsor;
use App\Models\SponsorDeliverable;
use App\Services\PlanFeatureService;
use App\Services\RevenueTrustService;
use App\Support\ClubPermissions;
use App\Support\UploadStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SponsorManagementController extends Controller
{
    public function __construct(
        private PlanFeatureService $planFeatures,
        private RevenueTrustService $revenueTrust,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorizeManagement($request);
        $global = $this->canManageGlobal($request);
        $editableClubIds = $this->clubIdsFor($request, ClubPermissions::SPONSORS_EDIT);
        $deletableClubIds = $this->clubIdsFor($request, ClubPermissions::SPONSORS_DELETE);
        $visibleClubIds = $editableClubIds->concat($deletableClubIds)->unique()->values();
        $clubs = Club::query()
            ->when(! $global, fn ($query) => $query->whereIn('id', $visibleClubIds))
            ->select(['id', 'name'])
            ->orderBy('name')
            ->limit(100)
            ->get()
            ->map(fn (Club $club) => [
                'id' => $club->id,
                'name' => $club->name,
                'can_edit_sponsors' => $global || $editableClubIds->contains($club->id),
                'can_delete_sponsors' => $global || $deletableClubIds->contains($club->id),
            ]);
        $sponsorQuery = Sponsor::query()
            ->with(['club:id,name', 'deliverables.responsible:id,name,email'])
            ->when(! $global, fn ($query) => $query->whereIn('club_id', $visibleClubIds));
        $stats = [
            'total' => (clone $sponsorQuery)->count(),
            'platform' => (clone $sponsorQuery)->where('scope', 'platform')->count(),
            'outfit_subscription' => (clone $sponsorQuery)->where('scope', 'outfit_subscription')->count(),
            'club' => (clone $sponsorQuery)->where('scope', 'club')->count(),
        ];
        $sponsors = (clone $sponsorQuery)
            ->latest('id')
            ->limit(500)
            ->get()
            ->map(fn (Sponsor $sponsor) => $this->sponsorData(
                $sponsor,
                $global || $editableClubIds->contains($sponsor->club_id),
                $global || $deletableClubIds->contains($sponsor->club_id),
            ))
            ->values();

        return response()->json([
            'data' => $sponsors,
            'clubs' => $clubs,
            'stats' => $stats,
            'can' => [
                'create' => $global || $editableClubIds->isNotEmpty(),
                'create_global' => $global,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeManagement($request);
        $data = $this->validated($request);
        $this->authorizeScope($request, $data, ClubPermissions::SPONSORS_EDIT);
        $data = $this->normalize($data);
        $this->guardContractApproval($request, null, $data);
        $data['contract_submitted_by'] = $request->user()->id;
        if (($data['verification_status'] ?? 'verified') === 'verified') {
            $data['verified_by'] = $request->user()->id;
            $data['verified_at'] = now();
        }
        $sponsor = Sponsor::query()->create($data);

        return response()->json([
            'message' => __('sponsor.flash.created'),
            'data' => $this->sponsorData($sponsor->load('club:id,name'), true, $this->canDeleteSponsor($request, $sponsor)),
        ], 201);
    }

    public function update(Request $request, Sponsor $sponsor): JsonResponse
    {
        abort_unless($this->canEditSponsor($request, $sponsor), 403);
        $data = $this->validated($request);
        $this->authorizeScope($request, $data, ClubPermissions::SPONSORS_EDIT);
        $data = $this->normalize($data);
        $this->guardContractApproval($request, $sponsor, $data);
        if (($data['verification_status'] ?? null) === 'verified') {
            $candidate = clone $sponsor;
            $candidate->forceFill($data);
            $this->revenueTrust->ensureSponsorApprovable($candidate);
            $data['verified_by'] = $request->user()->id;
            $data['verified_at'] = now();
            $data['verification_note'] = null;
        } elseif (array_key_exists('verification_status', $data)) {
            $data['verified_by'] = null;
            $data['verified_at'] = null;
        }
        $sponsor->update($data);

        return response()->json([
            'message' => __('sponsor.flash.updated'),
            'data' => $this->sponsorData(
                $sponsor->fresh('club:id,name'),
                true,
                $this->canDeleteSponsor($request, $sponsor),
            ),
        ]);
    }

    public function destroy(Request $request, Sponsor $sponsor): JsonResponse
    {
        abort_unless($this->canDeleteSponsor($request, $sponsor), 403);
        $sponsor->delete();

        return response()->json(['message' => __('sponsor.flash.deleted')]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'scope' => ['required', Rule::in(['platform', 'outfit_subscription', 'club'])],
            'club_id' => ['nullable', 'required_if:scope,club', 'integer', Rule::exists('clubs', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'registration_number' => ['nullable', 'string', 'max:120'],
            'vat_id' => ['nullable', 'string', 'max:80'],
            'verification_status' => ['nullable', Rule::in(['pending_review', 'verified', 'rejected'])],
            'verification_note' => ['nullable', 'string', 'max:2000'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url:http,https', 'max:255'],
            'logo_light' => ['nullable', 'url:http,https', 'max:2048'],
            'logo_dark' => ['nullable', 'url:http,https', 'max:2048'],
            'amount' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'package_code' => ['nullable', 'string', 'max:80'],
            'rights_package' => ['nullable', 'array'],
            'rights_package.*' => ['string', 'max:120'],
            'individual_offer_terms' => ['nullable', 'string', 'max:5000'],
            'contract_version' => ['nullable', 'string', 'max:80'],
            'renewal_notice_days' => ['nullable', 'integer', 'min:0', 'max:730'],
            'renewal_deadline' => ['nullable', 'date'],
            'contract_approval_status' => ['nullable', Rule::in(['draft', 'pending_review', 'approved', 'rejected'])],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);
    }

    private function authorizeScope(Request $request, array $data, string $permission): void
    {
        if ($data['scope'] === 'club') {
            $club = Club::query()->findOrFail($data['club_id']);
            abort_unless(
                $this->canManageGlobal($request)
                    || ClubPermissions::allows($club, $request->user(), $permission),
                403,
            );
            $this->planFeatures->ensureAllows($club, 'sponsors');

            return;
        }

        abort_unless($this->canManageGlobal($request), 403);
    }

    private function normalize(array $data): array
    {
        if ($data['scope'] !== 'club') {
            $data['club_id'] = null;
        }
        if (filled($data['country_code'] ?? null)) {
            $data['country_code'] = strtoupper((string) $data['country_code']);
        }
        $fallback = $data['logo_light'] ?? $data['logo_dark'] ?? null;
        $data['logo'] = $fallback;
        $data['logo_light'] = $data['logo_light'] ?? $fallback;
        $data['logo_dark'] = $data['logo_dark'] ?? $data['logo_light'];
        if (($data['contract_approval_status'] ?? null) !== 'approved') {
            $data['contract_approved_by'] = null;
            $data['contract_approved_at'] = null;
        }

        return $data;
    }

    private function guardContractApproval(Request $request, ?Sponsor $sponsor, array &$data): void
    {
        if (($data['contract_approval_status'] ?? null) !== 'approved') {
            return;
        }

        abort_unless($this->canApproveContract($request, $sponsor, $data), 403);

        $submittedBy = $sponsor?->contract_submitted_by;
        abort_if($submittedBy !== null && (int) $submittedBy === (int) $request->user()->id, 422, __('validation.approval_second_person'));

        $data['contract_approved_by'] = $request->user()->id;
        $data['contract_approved_at'] = now();
    }

    private function canApproveContract(Request $request, ?Sponsor $sponsor, array $data): bool
    {
        if ($request->user()->can('finance.edit') || $request->user()->can('system.manage')) {
            return true;
        }

        $clubId = $data['scope'] === 'club'
            ? (int) $data['club_id']
            : (int) ($sponsor?->club_id ?? 0);

        if ($clubId < 1) {
            return false;
        }

        $club = $sponsor?->club_id === $clubId ? $sponsor->club()->first() : Club::query()->find($clubId);

        return $club !== null && ClubPermissions::allows($club, $request->user(), ClubPermissions::FINANCE_APPROVE);
    }

    private function sponsorData(Sponsor $sponsor, bool $canEdit = false, bool $canDelete = false): array
    {
        return [
            'id' => $sponsor->id,
            'club_id' => $sponsor->club_id,
            'scope' => $sponsor->scope ?: ($sponsor->club_id ? 'club' : 'platform'),
            'name' => $sponsor->name,
            'legal_name' => $sponsor->legal_name,
            'country_code' => $sponsor->country_code,
            'registration_number' => $sponsor->registration_number,
            'vat_id' => $sponsor->vat_id,
            'verification_status' => $sponsor->verification_status,
            'verification_note' => $sponsor->verification_note,
            'contact_name' => $sponsor->contact_name,
            'email' => $sponsor->email,
            'website' => $sponsor->website,
            'logo_light' => $sponsor->logo_light,
            'logo_dark' => $sponsor->logo_dark,
            'logo_url' => UploadStorage::url($sponsor->logo),
            'amount' => $sponsor->amount,
            'package_code' => $sponsor->package_code,
            'rights_package' => $sponsor->rights_package ?? [],
            'individual_offer_terms' => $sponsor->individual_offer_terms,
            'contract_version' => $sponsor->contract_version,
            'renewal_notice_days' => $sponsor->renewal_notice_days,
            'renewal_deadline' => $sponsor->renewal_deadline?->toDateString(),
            'contract_approval_status' => $sponsor->contract_approval_status ?: 'draft',
            'contract_approved_by' => $sponsor->contract_approved_by,
            'contract_approved_at' => $sponsor->contract_approved_at?->toIso8601String(),
            'starts_at' => $sponsor->starts_at?->toDateString(),
            'ends_at' => $sponsor->ends_at?->toDateString(),
            'deliverables' => $sponsor->relationLoaded('deliverables')
                ? $sponsor->deliverables->map(fn (SponsorDeliverable $deliverable) => $this->deliverableData($deliverable))->values()
                : [],
            'deliverables_summary' => $this->deliverablesSummary($sponsor),
            'club' => $sponsor->club,
            'can_edit' => $canEdit,
            'can_delete' => $canDelete,
        ];
    }

    private function deliverableData(SponsorDeliverable $deliverable): array
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

    private function deliverablesSummary(Sponsor $sponsor): array
    {
        $deliverables = $sponsor->relationLoaded('deliverables')
            ? $sponsor->deliverables
            : $sponsor->deliverables()->get();

        return [
            'total' => $deliverables->count(),
            'open' => $deliverables->whereNotIn('status', ['fulfilled', 'waived', 'cancelled'])->count(),
            'fulfilled' => $deliverables->where('status', 'fulfilled')->count(),
            'overdue' => $deliverables->filter->isOverdue()->count(),
        ];
    }

    private function authorizeManagement(Request $request): void
    {
        abort_unless(
            $this->canManageGlobal($request)
                || ClubPermissions::allowsAnyClub($request->user(), [
                    ClubPermissions::SPONSORS_EDIT,
                    ClubPermissions::SPONSORS_DELETE,
                ]),
            403,
        );
    }

    private function canEditSponsor(Request $request, Sponsor $sponsor): bool
    {
        if ($this->canManageGlobal($request)) {
            return true;
        }

        if (! $sponsor->club_id) {
            return false;
        }

        return ClubPermissions::allows(
            $sponsor->club()->firstOrFail(),
            $request->user(),
            ClubPermissions::SPONSORS_EDIT,
        );
    }

    private function canDeleteSponsor(Request $request, Sponsor $sponsor): bool
    {
        if ($this->canManageGlobal($request)) {
            return true;
        }

        if (! $sponsor->club_id) {
            return false;
        }

        return ClubPermissions::allows(
            $sponsor->club()->firstOrFail(),
            $request->user(),
            ClubPermissions::SPONSORS_DELETE,
        );
    }

    private function canManageGlobal(Request $request): bool
    {
        return $request->user()->can('finance.edit')
            || $request->user()->can('system.manage')
            || $request->user()->hasAnyRole(['super_admin', 'admin', 'sponsor_manager']);
    }

    private function clubIdsFor(Request $request, string $permission)
    {
        $user = $request->user();

        return Club::query()
            ->where(function ($query) use ($user): void {
                $query->where('owner_id', $user->id)
                    ->orWhereHas('users', fn ($members) => $members->where('users.id', $user->id));
            })
            ->get()
            ->filter(fn (Club $club) => ClubPermissions::allows($club, $user, $permission))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();
    }
}

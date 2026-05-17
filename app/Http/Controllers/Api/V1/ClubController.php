<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ClubMemberResource;
use App\Http\Resources\Api\V1\ClubMembershipRequestResource;
use App\Http\Resources\Api\V1\ClubResource;
use App\Http\Resources\Api\V1\InvoiceResource;
use App\Http\Resources\Api\V1\PaymentResource;
use App\Http\Resources\Api\V1\TeamResource;
use App\Models\Club;
use App\Models\ClubContributionRule;
use App\Models\ClubMembershipRequest;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\PlanFeatureService;
use App\Support\ClubRoles;
use App\Support\Roles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ClubController extends Controller
{
    public function __construct(private readonly PlanFeatureService $planFeatures) {}

    public function index(Request $request)
    {
        $clubs = Club::visibleTo($request->user())
            ->withCount(['users', 'teams'])
            ->orderBy('name')
            ->paginate($this->perPage($request));

        return ClubResource::collection($clubs);
    }

    public function show(Request $request, Club $club)
    {
        $this->authorizeVisible($request, $club);

        $club->loadMissing(['teams' => fn ($query) => $query->orderBy('name')])
            ->loadCount(['users', 'teams']);

        return response()->json([
            'data' => [
                ...((new ClubResource($club))->resolve($request)),
                'teams' => TeamResource::collection($club->teams)->resolve($request),
                'capabilities' => $this->planFeatures->capabilities($club),
                'subscription' => [
                    'plan' => $club->subscriptionPlan(),
                    'member_usage' => $club->memberUsageCount(),
                    'member_limit' => $club->subscriptionPlan()?->member_limit,
                    'team_limit' => $club->subscriptionPlan()?->team_limit,
                    'storage_gb' => $club->subscriptionPlan()?->storage_gb,
                ],
            ],
        ]);
    }

    public function members(Request $request, Club $club)
    {
        $this->authorizeVisible($request, $club);

        $members = $club->users()
            ->withCount(['invoices', 'payments'])
            ->orderBy('name')
            ->paginate($this->perPage($request));

        return ClubMemberResource::collection($members);
    }

    public function billing(Request $request, Club $club)
    {
        $this->authorizeVisible($request, $club);

        $canManage = $this->canManageMembership($request, $club);

        $invoices = Invoice::query()
            ->where('club_id', $club->id)
            ->when(! $canManage, fn ($query) => $query->where('user_id', $request->user()->id))
            ->with(['club', 'user'])
            ->latest('id')
            ->paginate($this->perPage($request), ['*'], 'invoices_page');

        $payments = Payment::query()
            ->where('club_id', $club->id)
            ->when(! $canManage, fn ($query) => $query->where('user_id', $request->user()->id))
            ->with(['club', 'invoice'])
            ->latest('id')
            ->paginate($this->perPage($request), ['*'], 'payments_page');

        return response()->json([
            'data' => [
                'can_manage' => $canManage,
                'invoices' => InvoiceResource::collection($invoices)->response()->getData(true),
                'payments' => PaymentResource::collection($payments)->response()->getData(true),
            ],
        ]);
    }

    public function membershipRequests(Request $request, Club $club)
    {
        $this->authorizeVisible($request, $club);

        $canManage = $this->canManageMembership($request, $club);

        $requests = ClubMembershipRequest::query()
            ->where('club_id', $club->id)
            ->when(! $canManage, fn ($query) => $query->where('user_id', $request->user()->id))
            ->with(['club', 'user'])
            ->latest('id')
            ->paginate($this->perPage($request));

        return ClubMembershipRequestResource::collection($requests);
    }

    public function storeMembershipRequest(Request $request, Club $club)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['membership', 'pause'])],
            'club_membership_type_id' => ['nullable', Rule::exists('club_membership_types', 'id')->where('club_id', $club->id)],
            'requested_pause_from' => ['nullable', 'required_if:type,pause', 'date'],
            'requested_pause_until' => ['nullable', 'date', 'after_or_equal:requested_pause_from'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($data['type'] === 'pause') {
            abort_unless($club->member_pause_requests_enabled, 403, 'Dieser Verein erlaubt aktuell keine Pausen-Anfragen.');
            abort_unless($club->users()->where('users.id', $request->user()->id)->exists(), 403);

            $membershipRequest = DB::transaction(function () use ($request, $club, $data) {
                $membershipRequest = ClubMembershipRequest::query()->updateOrCreate(
                    [
                        'club_id' => $club->id,
                        'user_id' => $request->user()->id,
                        'type' => 'pause',
                        'status' => 'pending',
                    ],
                    [
                        'requested_pause_from' => $data['requested_pause_from'],
                        'requested_pause_until' => $data['requested_pause_until'] ?? null,
                        'message' => $data['message'] ?? null,
                    ],
                );

                $club->users()->updateExistingPivot($request->user()->id, [
                    'pause_requested_at' => now(),
                ]);

                return $membershipRequest;
            });

            return (new ClubMembershipRequestResource($membershipRequest->load(['club', 'user'])))
                ->response()
                ->setStatusCode(201);
        }

        abort_unless($club->membership_requests_enabled, 403, 'Dieser Verein nimmt aktuell keine Online-Mitgliedsanfragen an.');

        $previewRule = $this->matchingContributionRule($club, $data['club_membership_type_id'] ?? null);
        $membershipRequest = ClubMembershipRequest::query()->updateOrCreate(
            [
                'club_id' => $club->id,
                'user_id' => $request->user()->id,
                'type' => 'membership',
                'status' => 'pending',
            ],
            [
                'club_membership_type_id' => $data['club_membership_type_id'] ?? null,
                'message' => $data['message'] ?? null,
                'preview_amount' => $previewRule?->amount,
                'preview_interval' => $previewRule?->billing_interval,
            ],
        );

        return (new ClubMembershipRequestResource($membershipRequest->load(['club', 'user'])))
            ->response()
            ->setStatusCode(201);
    }

    private function authorizeVisible(Request $request, Club $club): void
    {
        abort_unless(
            Club::visibleTo($request->user())->whereKey($club->id)->exists(),
            404
        );
    }

    private function canManageMembership(Request $request, Club $club): bool
    {
        $user = $request->user();

        if ($club->owner_id === $user->id || $user->hasAnyRole(Roles::FULL_ACCESS)) {
            return true;
        }

        $memberQuery = $club->users()
            ->where('users.id', $user->id)
            ->where(function ($query) {
                ClubRoles::whereAny($query, ClubRoles::ELEVATED);
            });

        return $memberQuery->exists();
    }

    private function matchingContributionRule(Club $club, ?int $membershipTypeId): ?ClubContributionRule
    {
        return $club->contributionRules()
            ->where('is_active', true)
            ->whereDate('valid_from', '<=', now()->toDateString())
            ->where(function ($query) {
                $query
                    ->whereNull('valid_until')
                    ->orWhereDate('valid_until', '>=', now()->toDateString());
            })
            ->where(function ($query) use ($membershipTypeId) {
                $query
                    ->where('club_membership_type_id', $membershipTypeId)
                    ->orWhereNull('club_membership_type_id');
            })
            ->orderByRaw('CASE WHEN club_membership_type_id IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('valid_from')
            ->first();
    }

    private function perPage(Request $request): int
    {
        return min(max((int) $request->integer('per_page', 20), 1), 50);
    }
}

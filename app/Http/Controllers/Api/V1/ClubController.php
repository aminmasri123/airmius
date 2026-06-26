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
use App\Models\User;
use App\Services\PlanFeatureService;
use App\Support\AppNotification;
use App\Support\ClubMembershipApplication;
use App\Support\ClubRoles;
use App\Support\Roles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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
        abort_unless($this->canManageMembership($request, $club), 403);

        $members = $club->users()
            ->withCount(['invoices', 'payments'])
            ->orderBy('name')
            ->paginate($this->perPage($request));

        return ClubMemberResource::collection($members);
    }

    public function billing(Request $request, Club $club)
    {
        $this->authorizeVisible($request, $club);
        abort_unless($this->canManageMembership($request, $club), 403);

        $invoices = Invoice::query()
            ->where('club_id', $club->id)
            ->with(['club', 'user'])
            ->latest('id')
            ->paginate($this->perPage($request), ['*'], 'invoices_page');

        $payments = Payment::query()
            ->where('club_id', $club->id)
            ->with(['club', 'invoice'])
            ->latest('id')
            ->paginate($this->perPage($request), ['*'], 'payments_page');

        return response()->json([
            'data' => [
                'can_manage' => true,
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

    public function approveMembershipRequest(Request $request, Club $club, ClubMembershipRequest $membershipRequest)
    {
        $this->authorizeVisible($request, $club);
        abort_unless($this->canManageMembership($request, $club), 403);
        abort_unless($membershipRequest->club_id === $club->id, 404);
        abort_unless($membershipRequest->status === 'pending', 422, 'Diese Anfrage ist nicht mehr offen.');

        DB::transaction(function () use ($request, $club, $membershipRequest) {
            if ($membershipRequest->type === 'pause') {
                $club->users()->updateExistingPivot($membershipRequest->user_id, [
                    'membership_status' => 'paused',
                    'paused_from' => $membershipRequest->requested_pause_from,
                    'paused_until' => $membershipRequest->requested_pause_until,
                    'pause_requested_at' => null,
                ]);
            } else {
                $club->users()->syncWithoutDetaching([
                    $membershipRequest->user_id => [
                        'role' => 'member',
                        'roles' => ['member'],
                        'membership_status' => 'active',
                        'club_membership_type_id' => $membershipRequest->club_membership_type_id,
                        'contribution_amount' => $membershipRequest->preview_amount,
                        'contribution_interval' => $membershipRequest->preview_interval ?: 'none',
                        'payment_method' => $membershipRequest->preferred_payment_method,
                        'joined_on' => now()->toDateString(),
                    ],
                ]);
            }

            $membershipRequest->update([
                'status' => 'approved',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'review_note' => $request->input('review_note'),
            ]);
        });

        AppNotification::send($membershipRequest->user_id, 'club.membership_request_approved', [
            'title' => 'Anfrage angenommen',
            'body' => $club->name.' hat deine Anfrage angenommen.',
            'url' => '/clubs/'.$club->id,
            'club_id' => $club->id,
            'request_id' => $membershipRequest->id,
        ]);

        return new ClubMembershipRequestResource(
            $membershipRequest->fresh()->load(['club', 'user'])
        );
    }

    public function declineMembershipRequest(Request $request, Club $club, ClubMembershipRequest $membershipRequest)
    {
        $this->authorizeVisible($request, $club);
        abort_unless($this->canManageMembership($request, $club), 403);
        abort_unless($membershipRequest->club_id === $club->id, 404);
        abort_unless($membershipRequest->status === 'pending', 422, 'Diese Anfrage ist nicht mehr offen.');

        $data = $request->validate([
            'review_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $membershipRequest->update([
            'status' => 'declined',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_note' => $data['review_note'] ?? null,
        ]);

        AppNotification::send($membershipRequest->user_id, 'club.membership_request_declined', [
            'title' => 'Anfrage abgelehnt',
            'body' => $club->name.' hat deine Anfrage abgelehnt.',
            'url' => '/notifications',
            'club_id' => $club->id,
            'request_id' => $membershipRequest->id,
        ]);

        return new ClubMembershipRequestResource(
            $membershipRequest->fresh()->load(['club', 'user'])
        );
    }

    public function storeMembershipRequest(Request $request, Club $club)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['membership', 'pause'])],
            'club_membership_type_id' => ['nullable', Rule::exists('club_membership_types', 'id')->where('club_id', $club->id)],
            'requested_pause_from' => ['nullable', 'required_if:type,pause', 'date'],
            'requested_pause_until' => ['nullable', 'date', 'after_or_equal:requested_pause_from'],
            'message' => ['nullable', 'string', 'max:2000'],
            'application_data' => ['nullable', 'array'],
            'accepted_documents' => ['nullable', 'array'],
            'preferred_payment_method' => ['nullable', 'string', 'max:100'],
            'requested_billing_interval' => ['nullable', 'string', 'max:100'],
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

        $applicationData = $this->validatedMembershipApplicationData($request, $club, $data['application_data'] ?? []);
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
                        'application_data' => $applicationData,
                        'accepted_documents' => $data['accepted_documents'] ?? [],
                        'preferred_payment_method' => $data['preferred_payment_method'] ?? null,
                        'requested_billing_interval' => $data['requested_billing_interval'] ?? null,
                        'applicant_confirmed_at' => now(),
                        'preview_amount' => $previewRule?->amount,
                        'preview_interval' => $previewRule?->billing_interval,
                    ],
        );

        $this->notifyClubManagers($club, 'club.membership_request_created', [
            'title' => 'Neue Mitgliedschaftsanfrage',
            'body' => $request->user()->name.' moechte Mitglied bei '.$club->name.' werden.',
            'url' => '/club-memberships',
            'club_id' => $club->id,
            'membership_request_id' => $membershipRequest->id,
        ], $request->user()->id);

        return (new ClubMembershipRequestResource($membershipRequest->load(['club', 'user'])))
            ->response()
            ->setStatusCode(201);
    }

    public function withdrawMembershipRequest(Request $request, Club $club)
    {
        $this->authorizeVisible($request, $club);

        $membershipRequest = ClubMembershipRequest::query()
            ->where('club_id', $club->id)
            ->where('user_id', $request->user()->id)
            ->where('type', 'membership')
            ->where('status', 'pending')
            ->latest('id')
            ->firstOrFail();

        $membershipRequest->update([
            'status' => 'withdrawn',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        $this->notifyClubManagers($club, 'club.membership_request_withdrawn', [
            'title' => 'Mitgliedschaftsanfrage zurueckgezogen',
            'body' => $request->user()->name.' hat die Anfrage bei '.$club->name.' zurueckgezogen.',
            'url' => '/club-memberships',
            'club_id' => $club->id,
            'membership_request_id' => $membershipRequest->id,
        ], $request->user()->id);

        return new ClubMembershipRequestResource(
            $membershipRequest->fresh()->load(['club', 'user'])
        );
    }

    private function authorizeVisible(Request $request, Club $club): void
    {
        abort_unless(
            Club::visibleTo($request->user())->whereKey($club->id)->exists(),
            404
        );
    }

    private function validatedMembershipApplicationData(Request $request, Club $club, array $input): array
    {
        $applicationFields = ClubMembershipApplication::fieldsForClub($club->membership_application_fields);
        $enabledApplicationFields = collect($applicationFields)->where('mode', '!=', 'off')->values();
        $inputApplicationData = array_merge(
            ClubMembershipApplication::prefillFor($request->user()),
            $input,
        );
        $applicationData = [];
        $errors = [];

        foreach ($enabledApplicationFields as $field) {
            $key = $field['key'];
            $value = $inputApplicationData[$key] ?? null;
            $isCheckbox = ($field['type'] ?? null) === 'checkbox';
            $isEmpty = $isCheckbox ? ! (bool) $value : blank($value);

            if (($field['mode'] ?? 'off') === 'required' && $isEmpty) {
                $errors['application_data.'.$key] = $field['label'].' ist erforderlich.';
            }

            if (! $isEmpty && ($field['type'] ?? null) === 'select') {
                $allowedValues = collect($field['options'] ?? [])->pluck('value')->all();

                if ($allowedValues && ! in_array((string) $value, $allowedValues, true)) {
                    $errors['application_data.'.$key] = $field['label'].' ist ungültig.';
                    continue;
                }
            }

            if (! $isEmpty) {
                $applicationData[$key] = $isCheckbox ? (bool) $value : trim((string) $value);
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $applicationData;
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

    private function notifyClubManagers(Club $club, string $type, array $data, ?int $exceptUserId = null): void
    {
        $club->users()
            ->tap(fn ($query) => ClubRoles::whereAny($query, ClubRoles::ELEVATED))
            ->when($exceptUserId, fn ($query) => $query->where('users.id', '!=', $exceptUserId))
            ->get(['users.id'])
            ->each(fn (User $manager) => AppNotification::send($manager, $type, $data));
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

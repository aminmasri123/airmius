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
use App\Models\ClubExternalMember;
use App\Models\ClubMembershipType;
use App\Models\ClubMembershipRequest;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Team;
use App\Models\TeamJoinRequest;
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
use App\Models\BankTransaction;

class ClubController extends Controller
{
    public function __construct(private readonly PlanFeatureService $planFeatures) {}

    public function index(Request $request)
    {
        $clubs = Club::query()
            ->when($request->boolean('mine'), fn ($query) => $query->linkedToUser($request->user()))
            ->when(! $request->boolean('mine'), fn ($query) => $query->visibleTo($request->user()))
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = trim((string) $request->query('q'));
                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%")
                        ->orWhere('sport_type', 'like', "%{$search}%");
                });
            })
            ->withCount(['users', 'teams'])
            ->orderBy('name')
            ->paginate($this->perPage($request));

        return ClubResource::collection($clubs);
    }

    public function show(Request $request, Club $club)
    {
        $this->authorizeVisible($request, $club);

        $canManageMembership = $this->canManageMembership($request, $club);

        $club->loadMissing([
            'teams' => fn ($query) => $query
                ->with([
                    'users:id,name,email,profile_photo_path',
                    'joinRequests' => fn ($requestQuery) => $requestQuery
                        ->where('status', 'pending')
                        ->with('user:id,name,email,profile_photo_path'),
                ])
                ->withCount(['users', 'events'])
                ->orderBy('name'),
        ])
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
                'management' => $this->managementPayload($request, $club, $canManageMembership),
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
            Club::visibleTo($request->user())->whereKey($club->id)->exists()
                || Club::linkedToUser($request->user())->whereKey($club->id)->exists(),
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

    private function managementPayload(Request $request, Club $club, bool $canManageMembership): array
    {
        $subscription = [
            'plan' => $club->subscriptionPlan(),
            'member_usage' => $club->memberUsageCount(),
            'member_limit' => $club->subscriptionPlan()?->member_limit,
            'team_limit' => $club->subscriptionPlan()?->team_limit,
            'storage_gb' => $club->subscriptionPlan()?->storage_gb,
        ];

        $base = [
            'can_manage' => $canManageMembership,
            'subscription' => $subscription,
            'capabilities' => $this->planFeatures->capabilities($club),
            'teams' => TeamResource::collection($club->teams)->resolve($request),
        ];

        if (! $canManageMembership) {
            $membershipRequests = ClubMembershipRequest::query()
                ->where('club_id', $club->id)
                ->where('user_id', $request->user()->id)
                ->where('type', 'membership')
                ->latest('id')
                ->get()
                ->load(['club', 'user', 'membershipType']);

            return [
                ...$base,
                'membership_requests' => ClubMembershipRequestResource::collection($membershipRequests)->resolve($request),
                'club_requests' => ClubMembershipRequestResource::collection($membershipRequests)->resolve($request),
            ];
        }

        $pendingTeamJoinRequests = $club->teams
            ->flatMap(fn (Team $team) => $team->joinRequests->map(fn (TeamJoinRequest $joinRequest) => [
                'id' => $joinRequest->id,
                'team' => [
                    'id' => $team->id,
                    'name' => $team->name,
                ],
                'user' => $joinRequest->user,
                'created_at' => $joinRequest->created_at?->toJSON(),
            ]))
            ->values();

        $club->loadMissing([
            'users' => fn ($query) => $query
                ->select('users.id', 'name', 'email', 'athlete_license_number', 'profile_photo_path')
                ->withCount(['invoices', 'payments'])
                ->orderBy('name'),
            'externalMembers' => fn ($query) => $query
                ->with('linkedUser:id,name,email,profile_photo_path')
                ->orderBy('name')
                ->orderBy('email'),
            'currentSubscription.plan',
            'membershipTypes' => fn ($query) => $query->orderBy('sort_order')->orderBy('name'),
            'contributionRules' => fn ($query) => $query->with('membershipType:id,name')->orderByDesc('valid_from')->orderBy('name'),
        ]);

        $membershipRequests = ClubMembershipRequest::query()
            ->where('club_id', $club->id)
            ->where('status', 'pending')
            ->with(['club', 'user', 'membershipType:id,name'])
            ->latest('id')
            ->get();

        $invoices = Invoice::query()
            ->where('club_id', $club->id)
            ->with(['club', 'user'])
            ->latest('id')
            ->limit(60)
            ->get();

        $payments = Payment::query()
            ->where('club_id', $club->id)
            ->with(['club', 'invoice'])
            ->latest('id')
            ->limit(60)
            ->get();

        $bankTransactions = BankTransaction::query()
            ->where('club_id', $club->id)
            ->with(['invoice:id,number,title,amount,status', 'payment:id,amount,paid_at'])
            ->latest('id')
            ->limit(60)
            ->get();

        return [
            ...$base,
            'membership_statuses' => ['active', 'non_member', 'pending', 'paused', 'former'],
            'contribution_intervals' => ['none', 'monthly', 'quarterly', 'four_monthly', 'semi_yearly', 'yearly', 'once'],
            'team_roles' => Team::ROLES,
            'pending_team_join_requests' => $pendingTeamJoinRequests,
            'pending_requests' => $pendingTeamJoinRequests,
            'settings' => [
                'sepa_creditor_id' => $club->sepa_creditor_id,
                'sepa_account_holder' => $club->sepa_account_holder,
                'sepa_iban' => $club->sepa_iban,
                'sepa_bic' => $club->sepa_bic,
                'datev_consultant_number' => $club->datev_consultant_number,
                'datev_client_number' => $club->datev_client_number,
                'datev_revenue_account' => $club->datev_revenue_account,
                'datev_bank_account' => $club->datev_bank_account,
                'membership_requests_enabled' => $club->membership_requests_enabled,
                'member_pause_requests_enabled' => $club->member_pause_requests_enabled,
                'membership_application_fields' => ClubMembershipApplication::fieldsForClub($club->membership_application_fields),
                'membership_payment_methods' => ClubMembershipApplication::normalizePaymentMethods($club->membership_payment_methods),
                'membership_payment_method_options' => ClubMembershipApplication::paymentMethods(),
                'membership_application_documents' => ClubMembershipApplication::normalizeDocuments($club->membership_application_documents),
                'membership_application_document_types' => ClubMembershipApplication::documentTypes(),
            ],
            'members' => ClubMemberResource::collection($club->users)->resolve($request),
            'external_members' => $club->externalMembers->map(fn (ClubExternalMember $externalMember) => [
                'id' => $externalMember->id,
                'name' => $externalMember->name,
                'email' => $externalMember->email,
                'role' => $externalMember->role,
                'membership_status' => $externalMember->membership_status,
                'member_number' => $externalMember->member_number,
                'athlete_license_number' => $externalMember->athlete_license_number,
                'contribution_amount' => $externalMember->contribution_amount,
                'contribution_interval' => $externalMember->contribution_interval,
                'contribution_next_invoice_on' => $externalMember->contribution_next_invoice_on?->toDateString(),
                'contribution_last_invoice_at' => $externalMember->contribution_last_invoice_at?->toJSON(),
                'sepa_iban' => $externalMember->sepa_iban,
                'sepa_bic' => $externalMember->sepa_bic,
                'sepa_mandate_reference' => $externalMember->sepa_mandate_reference,
                'sepa_mandate_signed_on' => $externalMember->sepa_mandate_signed_on?->toDateString(),
                'sepa_mandate_active' => $externalMember->sepa_mandate_active,
                'joined_on' => $externalMember->joined_on?->toDateString(),
                'membership_ends_on' => $externalMember->membership_ends_on?->toDateString(),
                'membership_end_notified_at' => $externalMember->membership_end_notified_at?->toJSON(),
                'membership_notes' => $externalMember->membership_notes,
                'invitation_status' => $externalMember->invitation_status,
                'invited_at' => $externalMember->invited_at?->toJSON(),
                'linked_at' => $externalMember->linked_at?->toJSON(),
                'linked_user' => $externalMember->linkedUser,
            ])->values(),
            'membership_types' => $club->membershipTypes->map(fn (ClubMembershipType $type) => [
                'id' => $type->id,
                'name' => $type->name,
                'slug' => $type->slug,
                'description' => $type->description,
                'is_public' => $type->is_public,
                'is_active' => $type->is_active,
                'sort_order' => $type->sort_order,
            ])->values(),
            'contribution_rules' => $club->contributionRules->map(fn (ClubContributionRule $rule) => [
                'id' => $rule->id,
                'club_membership_type_id' => $rule->club_membership_type_id,
                'membership_type_name' => $rule->membershipType?->name,
                'name' => $rule->name,
                'valid_from' => $rule->valid_from?->toDateString(),
                'valid_until' => $rule->valid_until?->toDateString(),
                'billing_interval' => $rule->billing_interval,
                'amount' => $rule->amount,
                'age_min' => $rule->age_min,
                'age_max' => $rule->age_max,
                'factor_key' => $rule->factor_key,
                'factor_operator' => $rule->factor_operator,
                'factor_value' => $rule->factor_value,
                'is_active' => $rule->is_active,
                'notes' => $rule->notes,
            ])->values(),
            'membership_requests' => ClubMembershipRequestResource::collection($membershipRequests)->resolve($request),
            'club_requests' => ClubMembershipRequestResource::collection($membershipRequests)->resolve($request),
            'invoices' => InvoiceResource::collection($invoices)->resolve($request),
            'payments' => PaymentResource::collection($payments)->resolve($request),
            'bank_transactions' => $bankTransactions->map(fn (BankTransaction $transaction) => [
                'id' => $transaction->id,
                'club_id' => $transaction->club_id,
                'invoice_id' => $transaction->invoice_id,
                'payment_id' => $transaction->payment_id,
                'booking_date' => $transaction->booking_date?->toDateString(),
                'amount' => $transaction->amount,
                'currency' => $transaction->currency,
                'debtor_name' => $transaction->debtor_name,
                'debtor_iban' => $transaction->debtor_iban,
                'purpose' => $transaction->purpose,
                'status' => $transaction->status,
                'match_confidence' => $transaction->match_confidence,
                'match_reason' => $transaction->match_reason,
                'invoice' => $transaction->invoice,
                'payment' => $transaction->payment,
                'created_at' => $transaction->created_at?->toJSON(),
                'updated_at' => $transaction->updated_at?->toJSON(),
            ])->values(),
            'summary' => [
                'active_members_count' => $club->users->filter(fn (User $member) => ($member->pivot?->membership_status ?? 'active') === 'active')->count(),
                'linked_people_count' => $club->users->count(),
                'pending_membership_requests_count' => $membershipRequests->count(),
                'pending_team_join_requests_count' => $pendingTeamJoinRequests->count(),
                'open_invoice_amount' => (float) $invoices->where('status', '!=', 'paid')->sum('amount'),
                'open_invoices_count' => $invoices->where('status', '!=', 'paid')->count(),
                'sepa_ready_members_count' => $club->users->filter(fn (User $member) => (bool) ($member->pivot?->sepa_mandate_active ?? false))->count(),
                'recurring_contribution_total' => (float) $club->users->sum(fn (User $member) => (float) ($member->pivot?->contribution_amount ?? 0)),
            ],
        ];
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

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
use App\Notifications\ExternalClubMembershipInvitation;
use App\Services\ClubService;
use App\Services\PlanFeatureService;
use App\Support\AppNotification;
use App\Support\ClubMembershipApplication;
use App\Support\ClubRoles;
use App\Support\Roles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Laravel\Sanctum\PersonalAccessToken;
use Throwable;
use App\Models\BankTransaction;

class ClubController extends Controller
{
    public const CONTRIBUTION_INTERVALS = ['none', 'monthly', 'quarterly', 'four_monthly', 'semi_yearly', 'yearly', 'once'];

    public function __construct(
        private readonly PlanFeatureService $planFeatures,
        private readonly ClubService $clubService,
    ) {}

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

    public function store(Request $request)
    {
        Gate::authorize('create', Club::class);

        $club = $this->clubService->create($request->user(), $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sport_type' => ['nullable', 'string', 'max:120'],
            'is_official' => ['boolean'],
            'official_club_number' => ['nullable', 'string', 'max:120'],
            'country' => ['required', 'string', 'size:2'],
            'street' => ['nullable', 'string', 'max:255'],
            'house_number' => ['nullable', 'string', 'max:40'],
            'postal_code' => ['nullable', 'string', 'max:30'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'sepa_account_holder' => ['nullable', 'string', 'max:120'],
            'sepa_iban' => ['nullable', 'string', 'max:40'],
            'sepa_bic' => ['nullable', 'string', 'max:20'],
            'is_listed' => ['boolean'],
            'teams_are_listed' => ['boolean'],
            'members_can_post_to_club' => ['boolean'],
            'members_can_post_to_teams' => ['boolean'],
        ]));

        $club->loadCount(['users', 'teams']);

        return response()->json([
            'message' => 'Verein registriert. Der Antrag wartet jetzt auf Pruefung.',
            'data' => (new ClubResource($club))->resolve($request),
        ], 201);
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

    public function updateMemberRole(Request $request, Club $club, User $user)
    {
        $this->authorizeVisible($request, $club);
        abort_unless($this->canManageMembership($request, $club), 403);
        abort_unless($club->users()->where('users.id', $user->id)->exists(), 404);

        $data = $request->validate([
            'role' => ['required', Rule::in(ClubRoles::ALL)],
        ]);

        $roles = ClubRoles::normalize($data['role'], [$data['role']]);
        abort_if(
            $club->owner_id === $user->id && ! in_array('owner', $roles, true),
            422,
            'Der Owner kann hier nicht herabgestuft werden.'
        );

        DB::transaction(function () use ($club, $user, $roles) {
            if (in_array('owner', $roles, true)) {
                $previousOwnerId = $club->owner_id;
                $club->forceFill(['owner_id' => $user->id])->save();

                if ($previousOwnerId && $previousOwnerId !== $user->id) {
                    $club->users()->updateExistingPivot($previousOwnerId, [
                        'role' => 'admin',
                        'roles' => ['admin'],
                    ]);
                }
            }

            $club->users()->updateExistingPivot($user->id, [
                'role' => ClubRoles::primary($roles),
                'roles' => $roles,
            ]);
        });

        return response()->json([
            'data' => $this->managementPayload($request, $club->fresh(), true),
        ]);
    }

    public function inviteMember(Request $request, Club $club)
    {
        $this->authorizeVisible($request, $club);
        abort_unless($this->canManageMembership($request, $club), 403);

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'member_number' => ['nullable', 'string', 'max:120'],
            'athlete_license_number' => ['nullable', 'string', 'max:120'],
            'membership_status' => ['nullable', Rule::in(['active', 'non_member', 'pending', 'paused', 'former'])],
            'send_invitation' => ['boolean'],
        ]);

        $sendInvitation = $request->boolean('send_invitation', true);
        if ($sendInvitation) {
            $this->planFeatures->ensureCanSendMemberInvitations($club);
        }

        $email = strtolower(trim((string) $data['email']));
        $alreadyTracked = $club->users()->where('users.email', $email)->exists()
            || $club->externalMembers()->where('email', $email)->exists();

        if (! $alreadyTracked && ! $club->canAddMembers()) {
            $plan = $club->subscriptionPlan();
            $limit = $plan?->member_limit;
            throw ValidationException::withMessages([
                'email' => 'Das Mitgliederlimit des aktuellen Plans'.($plan ? ' '.$plan->name : '').' ist erreicht'.($limit ? " ({$limit} Mitglieder)." : '.'),
            ]);
        }

        $athleteLicenseNumber = trim((string) ($data['athlete_license_number'] ?? '')) ?: null;

        $memberData = [
            'role' => 'member',
            'roles' => ['member'],
            'membership_status' => $data['membership_status'] ?? 'active',
            'member_number' => trim((string) ($data['member_number'] ?? '')) ?: null,
            'contribution_amount' => null,
            'contribution_interval' => 'none',
            'contribution_next_invoice_on' => null,
            'contribution_last_invoice_at' => null,
            'sepa_iban' => null,
            'sepa_bic' => null,
            'sepa_mandate_reference' => null,
            'sepa_mandate_signed_on' => null,
            'sepa_mandate_active' => false,
            'joined_on' => now()->toDateString(),
            'membership_ends_on' => null,
            'membership_end_notified_at' => null,
            'membership_notes' => null,
        ];

        $existingUser = User::query()->where('email', $email)->first();
        $result = 'stored';

        if ($sendInvitation && $existingUser) {
            DB::transaction(function () use ($club, $existingUser, $memberData, $athleteLicenseNumber) {
                $club->users()->syncWithoutDetaching([
                    $existingUser->id => $memberData,
                ]);

                if (filled($athleteLicenseNumber)) {
                    $existingUser->forceFill([
                        'athlete_license_number' => $athleteLicenseNumber,
                    ])->save();
                }

                ClubExternalMember::query()
                    ->where('club_id', $club->id)
                    ->where('email', strtolower($existingUser->email))
                    ->delete();
            });

            AppNotification::send($existingUser, 'club.member_linked', [
                'title' => 'Du wurdest mit '.$club->name.' verknuepft',
                'body' => 'Der Verein hat dich als Mitglied hinzugefuegt.',
                'url' => '/club-memberships',
                'club_id' => $club->id,
            ]);

            $result = 'linked';
        } else {
            $externalMember = ClubExternalMember::query()->updateOrCreate(
                [
                    'club_id' => $club->id,
                    'email' => $email,
                ],
                [
                    'created_by' => $request->user()->id,
                    'name' => trim((string) ($data['name'] ?? '')) ?: null,
                    'role' => 'member',
                    'membership_status' => $memberData['membership_status'],
                    'member_number' => $memberData['member_number'],
                    'athlete_license_number' => $athleteLicenseNumber,
                    'contribution_amount' => null,
                    'contribution_interval' => 'none',
                    'contribution_next_invoice_on' => null,
                    'contribution_last_invoice_at' => null,
                    'sepa_iban' => null,
                    'sepa_bic' => null,
                    'sepa_mandate_reference' => null,
                    'sepa_mandate_signed_on' => null,
                    'sepa_mandate_active' => false,
                    'joined_on' => now()->toDateString(),
                    'membership_ends_on' => null,
                    'membership_end_notified_at' => null,
                    'membership_notes' => null,
                    'invitation_status' => $sendInvitation ? 'pending' : 'none',
                    'invitation_token' => $sendInvitation ? Str::random(64) : null,
                    'invited_at' => null,
                ],
            );

            if ($sendInvitation) {
                try {
                    Notification::route('mail', $email)
                        ->notify(new ExternalClubMembershipInvitation($externalMember->load('club')));
                    $externalMember->forceFill(['invited_at' => now()])->save();
                } catch (Throwable $exception) {
                    Log::warning('External club membership invitation mail failed via API.', [
                        'club_id' => $club->id,
                        'email' => $email,
                        'exception' => $exception::class,
                        'message' => $exception->getMessage(),
                    ]);

                    throw ValidationException::withMessages([
                        'email' => 'Die Einladung wurde vorbereitet, aber die E-Mail konnte nicht versendet werden. Bitte pruefe die SMTP-/Mail-Einstellungen oder versuche es spaeter erneut.',
                    ]);
                }
                $result = 'invited';
            }
        }

        $messages = [
            'linked' => 'Mitglied wurde direkt mit dem Verein verknuepft.',
            'invited' => 'Einladung wurde versendet.',
            'stored' => 'Externes Mitglied wurde gespeichert.',
        ];

        return response()->json([
            'message' => $messages[$result] ?? 'Einladung verarbeitet.',
            'status' => $result,
            'data' => $this->managementPayload($request, $club->fresh(), true),
        ], $result === 'stored' ? 201 : 200);
    }

    public function inviteMemberWithToken(Request $request, Club $club)
    {
        $token = (string) ($request->bearerToken() ?: $request->input('token', ''));
        $accessToken = $token !== '' ? PersonalAccessToken::findToken($token) : null;
        $user = $accessToken?->tokenable;

        abort_unless($user instanceof User, 401, 'Nicht authentifiziert. Bitte in Flutter abmelden und neu einloggen.');

        Auth::setUser($user);
        $request->setUserResolver(fn () => $user);
        $request->merge([
            'email' => $request->input('email'),
            'name' => $request->input('name'),
            'member_number' => $request->input('member_number'),
            'athlete_license_number' => $request->input('athlete_license_number'),
            'membership_status' => $request->input('membership_status', 'active'),
            'send_invitation' => $request->boolean('send_invitation', true),
        ]);

        return $this->inviteMember($request, $club);
    }

    public function updateMembershipSettings(Request $request, Club $club)
    {
        $this->authorizeVisible($request, $club);
        abort_unless($this->canManageMembership($request, $club), 403);

        $data = $request->validate([
            'membership_requests_enabled' => ['boolean'],
            'member_pause_requests_enabled' => ['boolean'],
            'membership_application_fields' => ['nullable', 'array'],
            'membership_application_fields.*' => ['nullable', Rule::in(ClubMembershipApplication::FIELD_MODES)],
            'membership_payment_methods' => ['nullable', 'array'],
            'membership_payment_methods.*' => ['string', Rule::in(collect(ClubMembershipApplication::paymentMethods())->pluck('value')->all())],
            'membership_application_documents' => ['nullable', 'array'],
            'membership_application_documents.*.id' => ['nullable', 'string', 'max:80'],
            'membership_application_documents.*.type' => ['nullable', Rule::in(ClubMembershipApplication::DOCUMENT_TYPES)],
            'membership_application_documents.*.title' => ['nullable', 'string', 'max:255'],
            'membership_application_documents.*.url' => ['nullable', 'string', 'max:1000'],
            'membership_application_documents.*.file_id' => ['nullable', 'integer', 'exists:files,id'],
            'membership_application_documents.*.file_name' => ['nullable', 'string', 'max:255'],
            'membership_application_documents.*.description' => ['nullable', 'string', 'max:1000'],
            'membership_application_documents.*.is_visible' => ['boolean'],
            'membership_application_documents.*.is_required' => ['boolean'],
        ]);

        $club->update([
            'membership_requests_enabled' => $request->has('membership_requests_enabled') ? (bool) $data['membership_requests_enabled'] : $club->membership_requests_enabled,
            'member_pause_requests_enabled' => $request->has('member_pause_requests_enabled') ? (bool) $data['member_pause_requests_enabled'] : $club->member_pause_requests_enabled,
            'membership_application_fields' => $request->has('membership_application_fields') ? ClubMembershipApplication::normalizeFieldModes($data['membership_application_fields'] ?? null) : $club->membership_application_fields,
            'membership_payment_methods' => $request->has('membership_payment_methods') ? ClubMembershipApplication::normalizePaymentMethods($data['membership_payment_methods'] ?? null) : $club->membership_payment_methods,
            'membership_application_documents' => $request->has('membership_application_documents') ? ClubMembershipApplication::normalizeDocuments($data['membership_application_documents'] ?? []) : $club->membership_application_documents,
        ]);

        return response()->json([
            'message' => 'Mitgliedschafts-Einstellungen aktualisiert.',
            'data' => $this->managementPayload($request, $club->fresh(), true),
        ]);
    }

    public function storeMembershipType(Request $request, Club $club)
    {
        $this->authorizeVisible($request, $club);
        abort_unless($this->canManageMembership($request, $club), 403);

        $club->membershipTypes()->create($this->validatedMembershipTypeData($request));

        return response()->json([
            'message' => 'Mitgliedschaftstyp gespeichert.',
            'data' => $this->managementPayload($request, $club->fresh(), true),
        ], 201);
    }

    public function updateMembershipType(Request $request, Club $club, ClubMembershipType $membershipType)
    {
        $this->authorizeVisible($request, $club);
        abort_unless($this->canManageMembership($request, $club), 403);
        abort_unless($membershipType->club_id === $club->id, 404);

        $membershipType->update($this->validatedMembershipTypeData($request));

        return response()->json([
            'message' => 'Mitgliedschaftstyp aktualisiert.',
            'data' => $this->managementPayload($request, $club->fresh(), true),
        ]);
    }

    public function storeContributionRule(Request $request, Club $club)
    {
        $this->authorizeVisible($request, $club);
        abort_unless($this->canManageMembership($request, $club), 403);

        $club->contributionRules()->create($this->validatedContributionRuleData($request, $club));

        return response()->json([
            'message' => 'Beitragsregel gespeichert.',
            'data' => $this->managementPayload($request, $club->fresh(), true),
        ], 201);
    }

    public function updateContributionRule(Request $request, Club $club, ClubContributionRule $contributionRule)
    {
        $this->authorizeVisible($request, $club);
        abort_unless($this->canManageMembership($request, $club), 403);
        abort_unless($contributionRule->club_id === $club->id, 404);

        $contributionRule->update($this->validatedContributionRuleData($request, $club));

        return response()->json([
            'message' => 'Beitragsregel aktualisiert.',
            'data' => $this->managementPayload($request, $club->fresh(), true),
        ]);
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

    public function recordMembershipPayment(Request $request, Club $club, Invoice $invoice)
    {
        $this->authorizeVisible($request, $club);
        abort_unless($this->canManageMembership($request, $club), 403);
        abort_unless((int) $invoice->club_id === (int) $club->id, 404);
        $this->planFeatures->ensureAllows($club, 'payment_tracking');

        $data = $request->validate([
            'amount' => ['nullable', 'numeric', 'min:0.01', 'max:999999.99'],
            'method' => ['nullable', 'string', Rule::in(['cash', 'bank_transfer', 'sepa_debit', 'manual'])],
            'reference' => ['nullable', 'string', 'max:255'],
            'paid_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        Payment::create([
            'club_id' => $invoice->club_id,
            'user_id' => $invoice->user_id,
            'invoice_id' => $invoice->id,
            'purpose' => 'membership_invoice',
            'amount' => $data['amount'] ?? $invoice->amount,
            'status' => 'paid',
            'method' => $data['method'] ?? 'manual',
            'reference' => $data['reference'] ?? null,
            'paid_at' => $data['paid_at'] ?? now(),
            'notes' => $data['notes'] ?? null,
        ]);

        $invoice->update([
            'status' => 'paid',
            'paid_at' => $data['paid_at'] ?? now(),
        ]);

        if ($invoice->user_id) {
            AppNotification::send((int) $invoice->user_id, 'invoice.paid', [
                'title' => 'Zahlung erfasst',
                'body' => 'Deine Zahlung fuer '.$invoice->number.' wurde markiert.',
                'url' => '/settings',
                'invoice_id' => $invoice->id,
                'club_id' => $club->id,
            ]);
        }

        return response()->json([
            'message' => 'Zahlung erfasst.',
            'data' => $this->managementPayload($request, $club->fresh(), true),
        ]);
    }

    public function recordDonation(Request $request, Club $club)
    {
        $this->authorizeVisible($request, $club);
        abort_unless($this->canManageMembership($request, $club), 403);
        $this->planFeatures->ensureAllows($club, 'payment_tracking');

        $data = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'method' => ['nullable', 'string', Rule::in(['cash', 'bank_transfer', 'sepa_debit', 'manual'])],
            'reference' => ['nullable', 'string', 'max:255'],
            'paid_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $member = $club->users()->where('users.id', $data['user_id'])->firstOrFail();

        Payment::create([
            'club_id' => $club->id,
            'user_id' => $member->id,
            'invoice_id' => null,
            'purpose' => 'donation',
            'amount' => $data['amount'],
            'status' => 'paid',
            'method' => $data['method'] ?? 'manual',
            'reference' => $data['reference'] ?? null,
            'paid_at' => $data['paid_at'] ?? now(),
            'notes' => trim('Spende'.(($data['notes'] ?? null) ? ': '.$data['notes'] : '')),
        ]);

        AppNotification::send((int) $member->id, 'donation.recorded', [
            'title' => 'Spende erfasst',
            'body' => 'Deine Spende an '.$club->name.' wurde erfasst.',
            'url' => '/settings',
            'club_id' => $club->id,
        ]);

        return response()->json([
            'message' => 'Spende erfasst.',
            'data' => $this->managementPayload($request, $club->fresh(), true),
        ]);
    }

    public function recordPrepayment(Request $request, Club $club)
    {
        $this->authorizeVisible($request, $club);
        abort_unless($this->canManageMembership($request, $club), 403);
        $this->planFeatures->ensureAllows($club, 'payment_tracking');

        $data = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'method' => ['nullable', 'string', Rule::in(['cash', 'bank_transfer', 'sepa_debit', 'manual'])],
            'reference' => ['nullable', 'string', 'max:255'],
            'paid_at' => ['nullable', 'date'],
            'coverage_note' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $member = $club->users()->where('users.id', $data['user_id'])->firstOrFail();
        $notes = collect([
            filled($data['coverage_note'] ?? null) ? 'Zeitraum: '.$data['coverage_note'] : null,
            $data['notes'] ?? null,
        ])->filter()->implode("\n");

        Payment::create([
            'club_id' => $club->id,
            'user_id' => $member->id,
            'invoice_id' => null,
            'purpose' => 'prepayment',
            'amount' => $data['amount'],
            'status' => 'paid',
            'method' => $data['method'] ?? 'manual',
            'reference' => $data['reference'] ?? null,
            'paid_at' => $data['paid_at'] ?? now(),
            'notes' => $notes ?: null,
        ]);

        AppNotification::send((int) $member->id, 'prepayment.recorded', [
            'title' => 'Vorauszahlung erfasst',
            'body' => 'Deine Vorauszahlung an '.$club->name.' wurde erfasst.',
            'url' => '/settings',
            'club_id' => $club->id,
        ]);

        return response()->json([
            'message' => 'Vorauszahlung erfasst.',
            'data' => $this->managementPayload($request, $club->fresh(), true),
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

    private function validatedMembershipTypeData(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_public' => ['boolean'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        return [
            ...$data,
            'is_public' => (bool) ($data['is_public'] ?? true),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];
    }

    private function validatedContributionRuleData(Request $request, Club $club): array
    {
        $data = $request->validate([
            'club_membership_type_id' => ['nullable', Rule::exists('club_membership_types', 'id')->where('club_id', $club->id)],
            'name' => ['required', 'string', 'max:255'],
            'valid_from' => ['required', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'billing_interval' => ['required', Rule::in(self::CONTRIBUTION_INTERVALS)],
            'amount' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'age_min' => ['nullable', 'integer', 'min:0', 'max:120'],
            'age_max' => ['nullable', 'integer', 'min:0', 'max:120'],
            'factor_key' => ['nullable', 'string', 'max:80'],
            'factor_operator' => ['nullable', 'string', 'max:20'],
            'factor_value' => ['nullable', 'string', 'max:120'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        return [
            ...$data,
            'is_active' => (bool) ($data['is_active'] ?? true),
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

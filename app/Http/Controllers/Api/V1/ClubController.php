<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\ClubMembershipController as WebClubMembershipController;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ClubFinanceEntryResource;
use App\Http\Resources\Api\V1\ClubMemberResource;
use App\Http\Resources\Api\V1\ClubMembershipRequestResource;
use App\Http\Resources\Api\V1\ClubResource;
use App\Http\Resources\Api\V1\InvoiceResource;
use App\Http\Resources\Api\V1\PaymentResource;
use App\Http\Resources\Api\V1\TeamResource;
use App\Models\BankTransaction;
use App\Models\Club;
use App\Models\ClubAnnouncement;
use App\Models\ClubContributionRule;
use App\Models\ClubExternalMember;
use App\Models\ClubFinanceEntry;
use App\Models\ClubMembershipRequest;
use App\Models\ClubMembershipType;
use App\Models\ClubMemberTimelineEntry;
use App\Models\ClubPolicyDocument;
use App\Models\ClubReceiptUpload;
use App\Models\ClubSepaSettlement;
use App\Models\Event;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Team;
use App\Models\TeamJoinRequest;
use App\Models\User;
use App\Notifications\ExternalClubMembershipInvitation;
use App\Services\ClubInvoicePaymentService;
use App\Services\ClubContributionInvoiceRunService;
use App\Services\ClubMembershipBankReconciliationService;
use App\Services\ClubMembershipLifecycleService;
use App\Services\ClubOnboardingService;
use App\Services\ClubPaymentNumberService;
use App\Services\ClubProfilePayloadService;
use App\Services\ClubReceiptUploadService;
use App\Services\ClubSepaFeeService;
use App\Services\ClubService;
use App\Services\MediaOptimizer;
use App\Services\PlanFeatureService;
use App\Support\AppNotification;
use App\Support\BillingOverview;
use App\Support\ClubAuditLog;
use App\Support\ClubMemberDuplicates;
use App\Support\ClubMembershipApplication;
use App\Support\ClubMembershipInput;
use App\Support\ClubPermissions;
use App\Support\ClubProfilePermissions;
use App\Support\ClubRoles;
use App\Support\LocalDateTime;
use App\Support\ProtectedDocumentDownload;
use App\Support\UploadStorage;
use App\Support\Validation\ClubProfileRules;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Throwable;

class ClubController extends Controller
{
    public const CONTRIBUTION_INTERVALS = ['none', 'monthly', 'quarterly', 'four_monthly', 'semi_yearly', 'yearly', 'once'];

    private const DONATION_TYPES = ['money', 'in_kind'];

    public function __construct(
        private readonly PlanFeatureService $planFeatures,
        private readonly ClubService $clubService,
        private readonly MediaOptimizer $mediaOptimizer,
        private readonly ClubProfilePayloadService $clubProfiles,
        private readonly ClubOnboardingService $clubOnboarding,
        private readonly ClubMembershipLifecycleService $membershipLifecycle,
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

        $club = $this->clubService->create($request->user(), $request->validate(ClubProfileRules::store()));

        $club->loadCount(['users', 'teams']);

        return response()->json([
            'message' => __('organization.club.registered'),
            'data' => (new ClubResource($club))->resolve($request),
        ], 201);
    }

    public function destroy(Request $request, Club $club)
    {
        Gate::authorize('delete', $club);

        $data = $request->validate(['confirmation' => ['required', 'string', 'max:100']]);
        $service = app(\App\Services\ClubDeletionService::class);
        $club = $service->request($club, $request->user(), $data['confirmation']);

        return response()->json([
            'message' => __('club_deletion.requested_title'),
            'data' => $service->status($club),
        ], 202);
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
            ->loadCount(['users', 'teams', 'posts']);

        $profilePayload = $this->clubProfiles->forViewer($club, $request->user());

        return response()->json([
            'data' => [
                ...((new ClubResource($club))->resolve($request)),
                'profile' => $profilePayload['clubProfile'],
                'posts' => $profilePayload['posts']->values(),
                'viewer' => $profilePayload['viewer'],
                'teams' => TeamResource::collection($club->teams)->resolve($request),
                'capabilities' => $this->planFeatures->capabilities($club),
                'subscription' => [
                    'plan' => $club->subscriptionPlan(),
                    'member_usage' => $club->memberUsageCount(),
                    'member_limit' => $club->subscriptionPlan()?->member_limit,
                    'team_limit' => $club->subscriptionPlan()?->team_limit,
                    'storage_bytes' => (int) $club->files()->sum('size'),
                    'storage_gb' => $club->subscriptionPlan()?->storage_gb,
                ],
                'management' => $this->managementPayload($request, $club, $canManageMembership),
            ],
        ]);
    }

    public function updateImages(Request $request, Club $club)
    {
        ClubProfilePermissions::authorizeBranding($club, $request->user());

        $request->validate([
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'cover_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ]);

        abort_unless($request->hasFile('logo') || $request->hasFile('cover_image'), 422);

        $updates = [];

        if ($request->hasFile('logo')) {
            $updates['logo'] = $this->mediaOptimizer
                ->store($request->file('logo'), 'clubs/'.$club->id.'/profile')['path'];
        }

        if ($request->hasFile('cover_image')) {
            $updates['cover_image'] = $this->mediaOptimizer
                ->store($request->file('cover_image'), 'clubs/'.$club->id.'/profile')['path'];
        }

        $previousFiles = array_filter([
            array_key_exists('logo', $updates) ? $club->logo : null,
            array_key_exists('cover_image', $updates) ? $club->cover_image : null,
        ]);

        $club->update($updates);
        ClubAuditLog::record($club, $request->user(), 'club.branding.updated', $club, [
            'changed_fields' => array_keys($updates),
        ]);
        Storage::disk(UploadStorage::disk())->delete($previousFiles);
        $club->refresh()->loadCount(['users', 'teams']);

        return response()->json([
            'message' => __('organization.club.images_updated'),
            'data' => (new ClubResource($club))->resolve($request),
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
        abort_unless(ClubPermissions::allows($club, $request->user(), ClubPermissions::MEMBERS_ROLES), 403);
        abort_unless($club->users()->where('users.id', $user->id)->exists(), 404);
        $previousRole = $club->users()->where('users.id', $user->id)->first()?->pivot?->role;
        $previousPrimaryRole = ClubRoles::primary(ClubRoles::normalize($previousRole, [$previousRole]));

        $data = $request->validate([
            'role' => ['required', Rule::in(ClubRoles::ALL)],
        ]);

        $roles = ClubRoles::normalize($data['role'], [$data['role']]);
        abort_if(
            $club->owner_id === $user->id && ! in_array('owner', $roles, true),
            422,
            __('organization.club.owner_demotion_forbidden')
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

        $nextRole = ClubRoles::primary($roles);
        if ($previousPrimaryRole !== $nextRole) {
            $previousRoleLabel = filled($previousRole)
                ? (ClubRoles::LABELS[$previousRole] ?? $previousRole)
                : 'Unbekannt';

            AppNotification::sendLocalized(
                $user,
                'club.member.role_updated',
                'organization.notifications.club_role_title',
                'organization.notifications.club_role_body',
                [
                    'club' => $club->name,
                    'previous' => AppNotification::translatedReplacement(
                        $previousRole ? 'organization.roles.club.'.$previousRole : 'organization.roles.unknown',
                        $previousRoleLabel,
                    ),
                    'next' => AppNotification::translatedReplacement(
                        'organization.roles.club.'.$nextRole,
                        ClubRoles::LABELS[$nextRole] ?? $nextRole,
                    ),
                ],
                [
                    'url' => '/notifications',
                    'mobile_url' => 'airmius://clubs/'.$club->id.'/members',
                    'club_id' => $club->id,
                    'previous_role' => $previousPrimaryRole,
                    'role' => $nextRole,
                    'roles' => $roles,
                ],
            );
        }

        ClubAuditLog::record($club, $request->user(), 'club.member.role_updated', $user, [
            'user_id' => $user->id,
            'from_role' => $previousPrimaryRole,
            'to_role' => $nextRole,
        ]);

        return response()->json([
            'data' => $this->managementPayload($request, $club->fresh(), true),
        ]);
    }

    public function memberPermissions(Request $request, Club $club, User $user)
    {
        $this->authorizeVisible($request, $club);
        abort_unless(ClubPermissions::editableBy($club, $request->user()), 403);
        abort_unless($club->users()->where('users.id', $user->id)->exists(), 404);

        return response()->json([
            'data' => $this->clubPermissionsPayload($club, $user, $request->user()),
        ]);
    }

    public function updateMemberPermissions(Request $request, Club $club, User $user)
    {
        $this->authorizeVisible($request, $club);
        abort_unless(ClubPermissions::editableBy($club, $request->user()), 403);
        abort_unless($club->users()->where('users.id', $user->id)->exists(), 404);
        abort_if($club->owner_id === $user->id, 422, __('organization.club.owner_permissions_locked'));

        $data = $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*' => ['nullable', 'boolean'],
        ]);
        $unknown = array_diff(array_keys($data['permissions']), ClubPermissions::ALL);
        abort_if($unknown, 422, __('organization.club.invalid_permission'));

        $membership = $club->users()->where('users.id', $user->id)->firstOrFail()->pivot;
        $overrides = ClubPermissions::normalizeOverrides($membership->permission_overrides);
        foreach ($data['permissions'] as $permission => $value) {
            if ($value === null) {
                unset($overrides[$permission]);
            } else {
                $overrides[$permission] = (bool) $value;
            }
        }

        $club->users()->updateExistingPivot($user->id, [
            'permission_overrides' => $overrides ?: null,
        ]);

        ClubAuditLog::record($club, $request->user(), 'club.member.permissions_updated', $user, [
            'user_id' => $user->id,
            'permission_overrides' => $overrides,
        ]);

        return response()->json([
            'message' => __('organization.club.permissions_updated'),
            'data' => $this->clubPermissionsPayload($club->fresh(), $user->fresh(), $request->user()),
        ]);
    }

    public function inviteMember(Request $request, Club $club)
    {
        $this->authorizeVisible($request, $club);
        abort_unless($this->canManageMembership($request, $club), 403);

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'country' => ['nullable', 'string', 'size:2'],
            'street' => ['nullable', 'string', 'max:255'],
            'house_number' => ['nullable', 'string', 'max:40'],
            'postal_code' => ['nullable', 'string', 'max:30'],
            'city' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', Rule::in(ClubRoles::INVITABLE)],
            'member_number' => ['nullable', 'string', 'max:120'],
            'athlete_license_number' => ['nullable', 'string', 'max:120'],
            'athlete_license_valid_until' => ['nullable', 'date_format:Y-m-d'],
            'membership_status' => ['nullable', Rule::in(['active', 'non_member', 'pending', 'paused', 'former'])],
            'club_membership_type_id' => ['nullable', Rule::exists('club_membership_types', 'id')->where('club_id', $club->id)],
            'contribution_amount' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'contribution_interval' => ['nullable', Rule::in(self::CONTRIBUTION_INTERVALS)],
            'contribution_next_invoice_on' => ['nullable', 'date'],
            'family_group_key' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9._-]+$/'],
            'contribution_payer_user_id' => [
                'nullable',
                'integer',
                Rule::exists('club_user', 'user_id')->where('club_id', $club->id),
            ],
            'invitation_expires_at' => ['nullable', 'date', 'after_or_equal:today'],
            'send_invitation' => ['boolean'],
        ]);

        $sendInvitation = $request->boolean('send_invitation', true);
        $roles = ClubRoles::normalize($data['role'] ?? 'member', [$data['role'] ?? 'member']);
        $invitationExpiresAt = $this->invitationExpiresAt($data['invitation_expires_at'] ?? null);

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
        $athleteLicenseValidUntil = $data['athlete_license_valid_until'] ?? null;

        $memberData = [
            'role' => ClubRoles::primary($roles),
            'roles' => $roles,
            'membership_status' => $data['membership_status'] ?? 'active',
            'club_membership_type_id' => $data['club_membership_type_id'] ?? null,
            'family_group_key' => $this->normalizeFamilyGroupKey($data['family_group_key'] ?? null),
            'contribution_payer_user_id' => filled($data['contribution_payer_user_id'] ?? null)
                ? (int) $data['contribution_payer_user_id']
                : null,
            'member_number' => trim((string) ($data['member_number'] ?? '')) ?: null,
            'contribution_amount' => $data['contribution_amount'] ?? null,
            'contribution_interval' => $data['contribution_interval'] ?? 'none',
            'contribution_next_invoice_on' => ClubMembershipInput::normalizedNextInvoiceDate([
                ...$data,
                'joined_on' => now()->toDateString(),
            ]),
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

        $externalMember = ClubExternalMember::query()->updateOrCreate(
            [
                'club_id' => $club->id,
                'email' => $email,
            ],
            [
                'created_by' => $request->user()->id,
                'name' => trim((string) ($data['name'] ?? '')) ?: null,
                'phone' => trim((string) ($data['phone'] ?? '')) ?: null,
                'country' => strtoupper(trim((string) ($data['country'] ?? ''))) ?: null,
                'street' => trim((string) ($data['street'] ?? '')) ?: null,
                'house_number' => trim((string) ($data['house_number'] ?? '')) ?: null,
                'postal_code' => trim((string) ($data['postal_code'] ?? '')) ?: null,
                'city' => trim((string) ($data['city'] ?? '')) ?: null,
                'role' => $memberData['role'],
                'membership_status' => $memberData['membership_status'],
                'club_membership_type_id' => $memberData['club_membership_type_id'],
                'family_group_key' => $memberData['family_group_key'],
                'contribution_payer_user_id' => $memberData['contribution_payer_user_id'],
                'member_number' => $memberData['member_number'],
                'athlete_license_number' => $athleteLicenseNumber,
                'athlete_license_valid_until' => $athleteLicenseValidUntil,
                'contribution_amount' => $memberData['contribution_amount'],
                'contribution_interval' => $memberData['contribution_interval'],
                'contribution_next_invoice_on' => $memberData['contribution_next_invoice_on'],
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
                'invitation_status' => 'none',
                'invitation_token' => null,
                'invited_at' => null,
                'invitation_expires_at' => null,
            ],
        );

        if ($sendInvitation) {
            $externalMember->issueInvitation($invitationExpiresAt);

            try {
                Notification::route('mail', $email)
                    ->notify(new ExternalClubMembershipInvitation($externalMember->load('club')));

                if ($existingUser) {
                    $webUrl = route('auth.club-member-invitations.accept', $externalMember->invitation_token);
                    $mobileUrl = 'airmius://club-member-invitations/'.$externalMember->invitation_token;
                    AppNotification::sendLocalized(
                        $existingUser,
                        'club.membership_invitation',
                        'organization.notifications.membership_invitation_title',
                        'organization.notifications.membership_invitation_body',
                        ['club' => $club->name],
                        [
                            'url' => $webUrl,
                            'action_url' => $webUrl,
                            'mobile_url' => $mobileUrl,
                            'deep_link' => $mobileUrl,
                            'club_id' => $club->id,
                            'invitation_token' => $externalMember->invitation_token,
                            'invitation_expires_at' => LocalDateTime::format($externalMember->invitation_expires_at, $existingUser),
                            'invitation_expires_at_timezone' => LocalDateTime::timezoneFor($existingUser),
                        ],
                    );
                }
            } catch (Throwable $exception) {
                Log::warning('External club membership invitation mail failed via API.', [
                    'club_id' => $club->id,
                    'email' => $email,
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);

                throw ValidationException::withMessages([
                    'email' => 'Die Einladung wurde vorbereitet, aber die E-Mail konnte nicht versendet werden. Bitte prüfe die SMTP-/Mail-Einstellungen oder versuche es später erneut.',
                ]);
            }
            $result = 'invited';
        }

        $messages = [
            'linked' => __('organization.club.member_linked_directly'),
            'invited' => __('organization.club.external_member_invited'),
            'stored' => __('organization.club.external_member_saved'),
        ];

        return response()->json([
            'message' => $messages[$result] ?? __('organization.club.invitation_processed'),
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
            'athlete_license_valid_until' => $request->input('athlete_license_valid_until'),
            'family_group_key' => $request->input('family_group_key'),
            'role' => $request->input('role'),
            'membership_status' => $request->input('membership_status', 'active'),
            'invitation_expires_at' => $request->input('invitation_expires_at'),
            'send_invitation' => $request->boolean('send_invitation', true),
        ]);

        return $this->inviteMember($request, $club);
    }

    public function updateMembershipSettings(Request $request, Club $club)
    {
        $this->authorizeVisible($request, $club);
        abort_unless($this->canManageMembership($request, $club), 403);

        $configuredDocumentTypes = $request->has('membership_application_document_types')
            ? $request->input('membership_application_document_types')
            : $club->membership_application_document_types;
        $documentTypeValues = ClubMembershipApplication::documentTypeValues($configuredDocumentTypes);

        $data = $request->validate([
            'membership_requests_enabled' => ['boolean'],
            'member_pause_requests_enabled' => ['boolean'],
            'membership_application_fields' => ['nullable', 'array'],
            'membership_application_fields.*' => ['nullable', Rule::in(ClubMembershipApplication::FIELD_MODES)],
            'membership_payment_methods' => ['nullable', 'array'],
            'membership_payment_methods.*' => ['string', Rule::in(collect(ClubMembershipApplication::paymentMethods())->pluck('value')->all())],
            'membership_application_document_types' => ['nullable', 'array'],
            'membership_application_document_types.*.value' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9_-]+$/'],
            'membership_application_document_types.*.labels' => ['required', 'array'],
            'membership_application_document_types.*.labels.de' => ['required', 'string', 'max:255'],
            'membership_application_document_types.*.labels.en' => ['nullable', 'string', 'max:255'],
            'membership_application_document_types.*.labels.fr' => ['nullable', 'string', 'max:255'],
            'membership_application_document_types.*.labels.ar' => ['nullable', 'string', 'max:255'],
            'membership_application_documents' => ['nullable', 'array'],
            'membership_application_documents.*.id' => ['nullable', 'string', 'max:80'],
            'membership_application_documents.*.membership_type_id' => ['nullable', 'integer', Rule::exists('club_membership_types', 'id')->where('club_id', $club->id)],
            'membership_application_documents.*.type' => ['nullable', Rule::in($documentTypeValues)],
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
            'membership_application_document_types' => $request->has('membership_application_document_types') ? ClubMembershipApplication::normalizeDocumentTypes($data['membership_application_document_types'] ?? null) : $club->membership_application_document_types,
            'membership_application_documents' => $request->has('membership_application_documents') ? ClubMembershipApplication::normalizeDocuments($data['membership_application_documents'] ?? [], $data['membership_application_document_types'] ?? $club->membership_application_document_types) : $club->membership_application_documents,
        ]);

        return response()->json([
            'message' => __('organization.club.membership_settings_updated'),
            'data' => $this->managementPayload($request, $club->fresh(), true),
        ]);
    }

    public function storeMembershipType(Request $request, Club $club)
    {
        $this->authorizeVisible($request, $club);
        abort_unless($this->canManageMembership($request, $club), 403);

        $club->membershipTypes()->create($this->validatedMembershipTypeData($request));

        return response()->json([
            'message' => __('organization.club.membership_type_saved'),
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
            'message' => __('organization.club.membership_type_updated'),
            'data' => $this->managementPayload($request, $club->fresh(), true),
        ]);
    }

    public function storeContributionRule(Request $request, Club $club)
    {
        $this->authorizeVisible($request, $club);
        abort_unless($this->canManageMembership($request, $club), 403);

        $club->contributionRules()->create($this->validatedContributionRuleData($request, $club));

        return response()->json([
            'message' => __('organization.club.contribution_rule_saved'),
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
            'message' => __('organization.club.contribution_rule_updated'),
            'data' => $this->managementPayload($request, $club->fresh(), true),
        ]);
    }

    public function billing(Request $request, Club $club)
    {
        $this->authorizeVisible($request, $club);
        abort_unless($this->canViewFinance($request, $club), 403);

        $invoices = Invoice::query()
            ->withSum('settledPayments', 'amount')
            ->where('club_id', $club->id)
            ->with(['club', 'user', 'membershipUser:id,name,email', 'externalMember:id,name,email', 'businessYearPeriod', 'contributionYearPeriod'])
            ->latest('id')
            ->paginate($this->perPage($request), ['*'], 'invoices_page');

        $payments = Payment::query()
            ->where('club_id', $club->id)
            ->with(['club', 'invoice', 'user:id,name,email'])
            ->latest('id')
            ->paginate($this->perPage($request), ['*'], 'payments_page');
        $invoiceSummary = BillingOverview::clubInvoiceSummary(
            Invoice::query()
                ->where('club_id', $club->id)
                ->get(['id', 'amount', 'status'])
        );

        return response()->json([
            'data' => [
                'can_manage' => true,
                'invoice_status_options' => Invoice::statusOptions(),
                'invoice_summary' => $invoiceSummary,
                'invoices' => InvoiceResource::collection($invoices)->response()->getData(true),
                'payments' => PaymentResource::collection($payments)->response()->getData(true),
                'audit_logs' => ClubAuditLog::forClub($club),
            ],
        ]);
    }

    public function recordMembershipPayment(Request $request, Club $club, Invoice $invoice)
    {
        $this->authorizeVisible($request, $club);
        abort_unless($this->canManageFinance($request, $club), 403);
        abort_unless((int) $invoice->club_id === (int) $club->id, 404);
        $this->planFeatures->ensureAllows($club, 'payment_tracking');
        abort_if($invoice->status === 'paid', 422, __('organization.club.paid_invoice_payment_forbidden'));

        $data = $request->validate([
            'amount' => ['nullable', 'numeric', 'min:0.01', 'max:999999.99'],
            'method' => ['nullable', 'string', Rule::in(['cash', 'bank_transfer', 'sepa_debit', 'manual'])],
            'reference' => ['nullable', 'string', 'max:255'],
            'paid_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        app(ClubInvoicePaymentService::class)->record($invoice, $data, $request->user());

        if ($invoice->user_id) {
            AppNotification::sendLocalized(
                (int) $invoice->user_id,
                $invoice->status === 'paid' ? 'invoice.paid' : 'invoice.payment_received',
                'organization.notifications.payment_title',
                'organization.notifications.payment_body',
                ['invoice' => $invoice->number],
                [
                    'url' => '/club-memberships?tab=payments&club_id='.$club->id.'&invoice_id='.$invoice->id,
                    'invoice_id' => $invoice->id,
                    'club_id' => $club->id,
                ],
            );
        }

        return response()->json([
            'message' => __('organization.club.payment_recorded'),
            'data' => $this->managementPayload($request, $club->fresh(), true),
        ]);
    }

    public function recordDonation(Request $request, Club $club)
    {
        $this->authorizeVisible($request, $club);
        abort_unless($this->canManageFinance($request, $club), 403);
        $this->planFeatures->ensureAllows($club, 'payment_tracking');

        $data = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'donation_type' => ['nullable', Rule::in(self::DONATION_TYPES)],
            'donation_restriction' => ['nullable', 'string', 'max:255'],
            'donation_campaign' => ['nullable', 'string', 'max:255'],
            'method' => ['nullable', 'string', Rule::in(['cash', 'bank_transfer', 'sepa_debit', 'manual'])],
            'reference' => ['nullable', 'string', 'max:255'],
            'paid_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $member = $club->users()->where('users.id', $data['user_id'])->firstOrFail();

        foreach (['donation_restriction', 'donation_campaign'] as $field) {
            if (preg_match('/\b(sponsor|sponsoring|mitgliedsbeitrag|membership|contribution)\b/i', (string) ($data[$field] ?? ''))) {
                throw ValidationException::withMessages([
                    $field => 'Spenden dürfen nicht als Sponsoring oder Mitgliedsbeitrag erfasst werden.',
                ]);
            }
        }

        $payment = app(ClubPaymentNumberService::class)->create($club, [
            'user_id' => $member->id,
            'invoice_id' => null,
            'purpose' => 'donation',
            'amount' => $data['amount'],
            'status' => 'paid',
            'method' => $data['method'] ?? 'manual',
            'reference' => $data['reference'] ?? null,
            'donation_type' => $data['donation_type'] ?? 'money',
            'donation_restriction' => filled($data['donation_restriction'] ?? null) ? trim($data['donation_restriction']) : null,
            'donation_campaign' => filled($data['donation_campaign'] ?? null) ? trim($data['donation_campaign']) : null,
            'paid_at' => $data['paid_at'] ?? now(),
            'notes' => trim('Spende'.(($data['notes'] ?? null) ? ': '.$data['notes'] : '')),
        ], $request->user());

        ClubAuditLog::record($club, $request->user(), 'club.donation.recorded', $payment, [
            'payment_id' => $payment->id,
            'donation_number' => $payment->donation_number,
            'donation_type' => $payment->donation_type,
            'donation_restriction' => $payment->donation_restriction,
            'donation_campaign' => $payment->donation_campaign,
        ]);

        AppNotification::sendLocalized(
            $member,
            'donation.recorded',
            'organization.notifications.donation_title',
            'organization.notifications.donation_body',
            ['club' => $club->name],
            [
                'url' => '/settings',
                'club_id' => $club->id,
            ],
        );

        return response()->json([
            'message' => __('organization.club.donation_recorded'),
            'data' => $this->managementPayload($request, $club->fresh(), true),
        ]);
    }

    public function recordPrepayment(Request $request, Club $club)
    {
        $this->authorizeVisible($request, $club);
        abort_unless($this->canManageFinance($request, $club), 403);
        $this->planFeatures->ensureAllows($club, 'payment_tracking');

        $data = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'donation_type' => ['nullable', Rule::in(self::DONATION_TYPES)],
            'donation_restriction' => ['nullable', 'string', 'max:255'],
            'donation_campaign' => ['nullable', 'string', 'max:255'],
            'method' => ['nullable', 'string', Rule::in(['cash', 'bank_transfer', 'sepa_debit', 'manual'])],
            'reference' => ['nullable', 'string', 'max:255'],
            'paid_at' => ['nullable', 'date'],
            'coverage_start' => ['nullable', 'date'],
            'coverage_end' => ['nullable', 'date', 'after_or_equal:coverage_start'],
            'coverage_note' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $member = $club->users()->where('users.id', $data['user_id'])->firstOrFail();
        $coverageNote = $data['coverage_note'] ?? null;
        if (! filled($coverageNote) && filled($data['coverage_start'] ?? null)) {
            $coverageNote = ($data['coverage_start'] ?? '').(filled($data['coverage_end'] ?? null) ? ' bis '.$data['coverage_end'] : '');
        }
        $notes = collect([
            filled($coverageNote) ? 'Zeitraum: '.$coverageNote : null,
            $data['notes'] ?? null,
        ])->filter()->implode("\n");

        app(ClubPaymentNumberService::class)->create($club, [
            'user_id' => $member->id,
            'invoice_id' => null,
            'purpose' => 'prepayment',
            'amount' => $data['amount'],
            'status' => 'paid',
            'method' => $data['method'] ?? 'manual',
            'reference' => $data['reference'] ?? null,
            'paid_at' => $data['paid_at'] ?? now(),
            'notes' => $notes ?: null,
        ], $request->user());

        AppNotification::sendLocalized(
            $member,
            'prepayment.recorded',
            'organization.notifications.prepayment_title',
            'organization.notifications.prepayment_body',
            ['club' => $club->name],
            [
                'url' => '/settings',
                'club_id' => $club->id,
            ],
        );

        return response()->json([
            'message' => __('organization.club.prepayment_recorded'),
            'data' => $this->managementPayload($request, $club->fresh(), true),
        ]);
    }

    public function updatePayment(Request $request, Club $club, Payment $payment)
    {
        $this->authorizeVisible($request, $club);
        abort_unless($this->canManageFinance($request, $club), 403);
        abort_unless((int) $payment->club_id === (int) $club->id, 404);
        $this->planFeatures->ensureAllows($club, 'payment_tracking');

        $data = $request->validate([
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'donation_type' => ['nullable', Rule::in(self::DONATION_TYPES)],
            'donation_restriction' => ['nullable', 'string', 'max:255'],
            'donation_campaign' => ['nullable', 'string', 'max:255'],
            'method' => ['nullable', 'string', Rule::in(['cash', 'bank_transfer', 'sepa_debit', 'manual'])],
            'reference' => ['nullable', 'string', 'max:255'],
            'paid_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $attributes = [
            'amount' => $data['amount'],
            'method' => $data['method'] ?? 'manual',
            'reference' => $data['reference'] ?? null,
            'paid_at' => $data['paid_at'] ?? now(),
            'notes' => $data['notes'] ?? null,
        ];

        if (! $payment->invoice_id && filled($data['user_id'] ?? null)) {
            $member = $club->users()->where('users.id', $data['user_id'])->firstOrFail();
            $attributes['user_id'] = $member->id;
        }

        app(ClubInvoicePaymentService::class)->correct($payment, $attributes, $request->user());

        return response()->json([
            'message' => __('organization.club.payment_updated'),
            'data' => $this->managementPayload($request, $club->fresh(), true),
        ]);
    }

    public function storeFinanceEntry(Request $request, Club $club)
    {
        $this->authorizeVisible($request, $club);
        abort_unless($this->canManageFinance($request, $club), 403);
        $this->planFeatures->ensureAllows($club, 'payment_tracking');

        $data = $this->validatedFinanceEntryData($request);

        ClubFinanceEntry::create([
            ...$data,
            'club_id' => $club->id,
            'user_id' => $request->user()->id,
        ]);

        return response()->json([
            'message' => __('organization.club.finance_entry_saved'),
            'data' => $this->managementPayload($request, $club->fresh(), true),
        ]);
    }

    public function updateFinanceEntry(Request $request, Club $club, ClubFinanceEntry $financeEntry)
    {
        $this->authorizeVisible($request, $club);
        abort_unless($this->canManageFinance($request, $club), 403);
        abort_unless((int) $financeEntry->club_id === (int) $club->id, 404);
        $this->planFeatures->ensureAllows($club, 'payment_tracking');

        app(ClubSepaFeeService::class)->updateFinanceEntry($financeEntry, $this->validatedFinanceEntryData($request));

        return response()->json([
            'message' => __('organization.club.finance_entry_updated'),
            'data' => $this->managementPayload($request, $club->fresh(), true),
        ]);
    }

    public function uploadReceipt(Request $request, Club $club, ClubReceiptUploadService $receipts)
    {
        $this->authorizeVisible($request, $club);
        abort_unless($this->canManageFinance($request, $club), 403);
        $this->planFeatures->ensureAllows($club, 'payment_tracking');

        $data = $request->validate([
            'file' => ['required', 'file', 'max:10240'],
        ]);

        $receipt = $receipts->store($club, $request->user(), $data['file']);

        return response()->json([
            'message' => 'Beleg geprüft. Bitte OCR-Vorschlag prüfen und die Buchungszuordnung bestätigen.',
            'data' => [
                'receipt_upload' => $this->receiptUploadPayload($receipt),
                'management' => $this->managementPayload($request, $club->fresh(), true),
            ],
        ], $receipt->wasRecentlyCreated ? 201 : 200);
    }

    public function confirmReceipt(Request $request, Club $club, ClubReceiptUpload $receiptUpload, ClubReceiptUploadService $receipts)
    {
        $this->authorizeVisible($request, $club);
        abort_unless($this->canManageFinance($request, $club), 403);
        abort_unless((int) $receiptUpload->club_id === (int) $club->id, 404);
        $this->planFeatures->ensureAllows($club, 'payment_tracking');

        $data = $request->validate([
            'finance_entry_id' => ['nullable', 'integer', Rule::exists('club_finance_entries', 'id')],
            'type' => ['required_without:finance_entry_id', Rule::in(ClubFinanceEntry::TYPES)],
            'account' => ['required_without:finance_entry_id', Rule::in(ClubFinanceEntry::ACCOUNTS)],
            'category' => ['nullable', 'string', 'max:120'],
            'title' => ['required_without:finance_entry_id', 'string', 'max:255'],
            'amount' => ['required_without:finance_entry_id', 'numeric', 'min:0.01', 'max:999999.99'],
            'booked_on' => ['required_without:finance_entry_id', 'date'],
            'reference' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        if (! empty($data['finance_entry_id'])) {
            abort_unless(
                ClubFinanceEntry::query()
                    ->where('club_id', $club->id)
                    ->whereKey($data['finance_entry_id'])
                    ->exists(),
                404
            );
        }

        $confirmed = $receipts->confirm($receiptUpload, $request->user(), [
            ...$data,
            'club_id' => $club->id,
            'category' => filled($data['category'] ?? null) ? trim($data['category']) : null,
            'title' => filled($data['title'] ?? null) ? trim($data['title']) : null,
            'booked_on' => $data['booked_on'] ?? now()->toDateString(),
            'reference' => filled($data['reference'] ?? null) ? trim($data['reference']) : null,
            'description' => filled($data['description'] ?? null) ? trim($data['description']) : null,
        ]);

        return response()->json([
            'message' => 'Beleg bestätigt und der Buchung zugeordnet.',
            'data' => [
                'receipt_upload' => $this->receiptUploadPayload($confirmed),
                'management' => $this->managementPayload($request, $club->fresh(), true),
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
            ->with(['club', 'user', 'membershipType', 'department'])
            ->latest('id')
            ->paginate($this->perPage($request));

        return ClubMembershipRequestResource::collection($requests);
    }

    public function approveMembershipRequest(Request $request, Club $club, ClubMembershipRequest $membershipRequest)
    {
        $this->authorizeVisible($request, $club);
        abort_unless($this->canUseClubPermission($request, $club, ClubPermissions::MEMBERS_APPROVE), 403);
        $data = $request->validate([
            'review_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $membershipRequest = $this->membershipLifecycle->approve(
            $club,
            $membershipRequest,
            $request->user(),
            $data['review_note'] ?? null,
        );

        return (new ClubMembershipRequestResource(
            $membershipRequest->fresh()->load(['club', 'user', 'membershipType'])
        ))->additional([
            'management' => $this->managementPayload($request, $club->fresh(), true),
        ]);
    }

    public function requestMembershipInformation(Request $request, Club $club, ClubMembershipRequest $membershipRequest)
    {
        $this->authorizeVisible($request, $club);
        abort_unless($this->canUseClubPermission($request, $club, ClubPermissions::MEMBERS_APPROVE), 403);
        $data = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $membershipRequest = $this->membershipLifecycle->requestInformation(
            $club,
            $membershipRequest,
            $request->user(),
            $data['message'],
        );

        return (new ClubMembershipRequestResource(
            $membershipRequest->fresh()->load(['club', 'user', 'membershipType'])
        ))->additional([
            'management' => $this->managementPayload($request, $club->fresh(), true),
        ]);
    }

    public function respondToMembershipInformation(Request $request, Club $club, ClubMembershipRequest $membershipRequest)
    {
        $this->authorizeVisible($request, $club);
        $data = $request->validate([
            'message' => ['nullable', 'string', 'max:2000'],
            'application_data' => ['nullable', 'array'],
            'accepted_documents' => ['nullable', 'array'],
            'accepted_documents.*' => ['boolean'],
        ]);

        $membershipRequest = $this->membershipLifecycle->respondToInformationRequest(
            $club,
            $membershipRequest,
            $request->user(),
            $data,
        );

        return new ClubMembershipRequestResource(
            $membershipRequest->fresh()->load(['club', 'user', 'membershipType'])
        );
    }

    public function waitlistMembershipRequest(Request $request, Club $club, ClubMembershipRequest $membershipRequest)
    {
        $this->authorizeVisible($request, $club);
        abort_unless($this->canUseClubPermission($request, $club, ClubPermissions::MEMBERS_APPROVE), 403);
        $data = $request->validate([
            'review_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $membershipRequest = $this->membershipLifecycle->waitlist(
            $club,
            $membershipRequest,
            $request->user(),
            $data['review_note'] ?? null,
        );

        return (new ClubMembershipRequestResource(
            $membershipRequest->fresh()->load(['club', 'user', 'membershipType'])
        ))->additional([
            'management' => $this->managementPayload($request, $club->fresh(), true),
        ]);
    }

    public function requestMembershipTermination(Request $request, Club $club)
    {
        $this->authorizeVisible($request, $club);
        $data = $request->validate([
            'requested_termination_on' => ['required', 'date', 'after_or_equal:today'],
            'termination_reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $membershipRequest = $this->membershipLifecycle->requestTermination(
            $club,
            $request->user(),
            $data,
        );

        return (new ClubMembershipRequestResource($membershipRequest->load(['club', 'user', 'membershipType'])))
            ->response()
            ->setStatusCode(201);
    }

    public function requestMembershipChange(Request $request, Club $club)
    {
        $this->authorizeVisible($request, $club);
        $data = $request->validate([
            'club_membership_type_id' => [
                'required_without:club_department_id',
                'nullable',
                Rule::exists('club_membership_types', 'id')
                    ->where('club_id', $club->id)
                    ->where('is_active', true)
                    ->where('is_public', true),
            ],
            'club_department_id' => [
                'required_without:club_membership_type_id',
                'nullable',
                Rule::exists('club_departments', 'id')
                    ->where('club_id', $club->id)
                    ->where('is_public', true),
            ],
            'effective_on' => ['nullable', 'date', 'after_or_equal:today'],
            'is_exception' => ['nullable', 'boolean'],
            'exception_reason' => ['nullable', 'required_if:is_exception,1,true', 'string', 'max:2000'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        $membershipRequest = $this->membershipLifecycle->requestMembershipChange(
            $club,
            $request->user(),
            $data,
        );

        return (new ClubMembershipRequestResource($membershipRequest->load(['club', 'user', 'membershipType', 'department'])))
            ->response()
            ->setStatusCode(201);
    }

    public function declineMembershipRequest(Request $request, Club $club, ClubMembershipRequest $membershipRequest)
    {
        $this->authorizeVisible($request, $club);
        abort_unless($this->canUseClubPermission($request, $club, ClubPermissions::MEMBERS_APPROVE), 403);
        $data = $request->validate([
            'review_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $membershipRequest = $this->membershipLifecycle->decline(
            $club,
            $membershipRequest,
            $request->user(),
            $data['review_note'] ?? null,
        );

        return (new ClubMembershipRequestResource(
            $membershipRequest->fresh()->load(['club', 'user', 'membershipType'])
        ))->additional([
            'management' => $this->managementPayload($request, $club->fresh(), true),
        ]);
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
            'accepted_documents.*' => ['boolean'],
            'preferred_payment_method' => ['nullable', 'string', 'max:100'],
            'requested_billing_interval' => ['nullable', 'string', 'max:100'],
            'consent_version' => ['nullable', 'string', 'max:80'],
            'consent_signature' => ['nullable', 'string', 'max:255'],
        ]);

        $membershipRequest = $data['type'] === 'pause'
            ? $this->membershipLifecycle->requestPause($club, $request->user(), $data)
            : $this->membershipLifecycle->submitMembership(
                $club,
                $request->user(),
                $data,
                $request->ip(),
                $request->userAgent(),
            );

        return (new ClubMembershipRequestResource($membershipRequest->load(['club', 'user', 'membershipType'])))
            ->response()
            ->setStatusCode(201);
    }

    public function withdrawMembershipRequest(Request $request, Club $club)
    {
        $this->authorizeVisible($request, $club);
        $data = $request->validate([
            'type' => ['nullable', 'string', Rule::in(['membership', 'termination'])],
        ]);

        $membershipRequest = $this->membershipLifecycle->withdrawMembership(
            $club,
            $request->user(),
            $data['type'] ?? 'membership',
        );

        return new ClubMembershipRequestResource(
            $membershipRequest->fresh()->load(['club', 'user', 'membershipType'])
        );
    }

    public function updateMemberDetails(Request $request, Club $club, User $user)
    {
        $this->authorizeVisible($request, $club);
        abort_unless($this->canManageMembership($request, $club), 403);
        if ($request->hasAny(['role', 'roles'])) {
            abort_unless(ClubPermissions::allows($club, $request->user(), ClubPermissions::MEMBERS_ROLES), 403);
        }
        $previousRole = $club->users()->where('users.id', $user->id)->first()?->pivot?->role;
        app(WebClubMembershipController::class)->updateMember($request, $club, $user);
        $currentRole = $club->users()->where('users.id', $user->id)->first()?->pivot?->role;
        if ($currentRole !== $previousRole) {
            ClubAuditLog::record($club, $request->user(), 'club.member.role_updated', $user, [
                'user_id' => $user->id,
                'from_role' => $previousRole,
                'to_role' => $currentRole,
            ]);
        }

        return $this->membershipManagementResponse($request, $club, __('organization.club.member_data_updated'));
    }

    public function removeClubMember(Request $request, Club $club, User $user)
    {
        $this->authorizeVisible($request, $club);
        abort_unless($this->canUseClubPermission($request, $club, ClubPermissions::MEMBERS_DELETE), 403);
        app(WebClubMembershipController::class)->removeMember($request, $club, $user);

        return $this->membershipManagementResponse($request, $club, __('organization.club.member_removed'));
    }

    public function leaveClub(Request $request, Club $club)
    {
        app(WebClubMembershipController::class)->leaveClub($request, $club);

        return response()->json(['message' => __('organization.club.left')]);
    }

    public function objectToRemoval(Request $request, Club $club)
    {
        app(WebClubMembershipController::class)->objectToRemoval($request, $club);

        return response()->json(['message' => __('organization.club.appeal_sent')], 201);
    }

    public function storePauseRequest(Request $request, Club $club)
    {
        app(WebClubMembershipController::class)->storePauseRequest($request, $club);

        return response()->json(['message' => __('organization.club.pause_request_sent')], 201);
    }

    public function storeExternalMembers(Request $request, Club $club)
    {
        $this->authorizeVisible($request, $club);
        abort_unless($this->canManageMembership($request, $club), 403);
        app(WebClubMembershipController::class)->storeEmailMember($request, $club);

        return $this->membershipManagementResponse($request, $club, __('organization.club.members_saved'), 201);
    }

    public function importMembers(Request $request, Club $club)
    {
        $this->authorizeVisible($request, $club);
        abort_unless($this->canManageMembership($request, $club), 403);
        app(WebClubMembershipController::class)->importEmailMembers($request, $club);

        return $this->membershipManagementResponse($request, $club, __('organization.club.members_imported'), 201);
    }

    public function previewMemberImport(Request $request, Club $club)
    {
        $this->authorizeVisible($request, $club);
        abort_unless($this->canManageMembership($request, $club), 403);

        return response()->json([
            'data' => app(WebClubMembershipController::class)->previewMembershipImport($request, $club),
        ]);
    }

    public function downloadMemberImportTemplate()
    {
        return app(WebClubMembershipController::class)->downloadImportTemplate();
    }

    public function updateSepaSettingsApi(Request $request, Club $club)
    {
        $this->authorizeVisible($request, $club);
        abort_unless($this->canManageFinance($request, $club), 403);
        app(WebClubMembershipController::class)->updateSepaSettings($request, $club);

        return $this->membershipManagementResponse($request, $club, __('organization.club.sepa_settings_saved'));
    }

    public function exportSepaDebit(Request $request, Club $club)
    {
        $this->authorizeVisible($request, $club);
        abort_unless($this->canUseClubPermission($request, $club, ClubPermissions::FINANCE_EXPORT), 403);

        return app(WebClubMembershipController::class)->exportSepaDebit($club);
    }

    public function inviteExternalMember(Request $request, Club $club, ClubExternalMember $externalMember)
    {
        $this->authorizeVisible($request, $club);
        abort_unless($this->canManageMembership($request, $club), 403);
        abort_unless((int) $externalMember->club_id === (int) $club->id, 404);
        app(WebClubMembershipController::class)->inviteEmailMember($request, $externalMember);

        return $this->membershipManagementResponse($request, $club, __('organization.club.invitation_processed'));
    }

    public function updateExternalMember(Request $request, Club $club, ClubExternalMember $externalMember)
    {
        $this->authorizeVisible($request, $club);
        abort_unless($this->canManageMembership($request, $club), 403);
        abort_unless((int) $externalMember->club_id === (int) $club->id, 404);

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('club_external_members', 'email')
                    ->where(fn ($query) => $query->where('club_id', $club->id))
                    ->ignore($externalMember->id),
            ],
            'phone' => ['nullable', 'string', 'max:40'],
            'country' => ['nullable', 'string', 'size:2'],
            'street' => ['nullable', 'string', 'max:255'],
            'house_number' => ['nullable', 'string', 'max:40'],
            'postal_code' => ['nullable', 'string', 'max:30'],
            'city' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', Rule::in(ClubRoles::INVITABLE)],
            'membership_status' => ['required', Rule::in(['active', 'non_member', 'pending', 'paused', 'former'])],
            'club_membership_type_id' => ['nullable', Rule::exists('club_membership_types', 'id')->where('club_id', $club->id)],
            'family_group_key' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9._-]+$/'],
            'contribution_payer_user_id' => [
                'nullable',
                'integer',
                Rule::exists('club_user', 'user_id')->where('club_id', $club->id),
            ],
            'member_number' => ['nullable', 'string', 'max:80'],
            'athlete_license_number' => ['nullable', 'string', 'max:120'],
            'athlete_license_valid_until' => ['nullable', 'date_format:Y-m-d'],
            'contribution_amount' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'contribution_interval' => ['nullable', Rule::in(self::CONTRIBUTION_INTERVALS)],
            'contribution_next_invoice_on' => ['nullable', 'date'],
            'sepa_iban' => ['nullable', 'string', 'max:40'],
            'sepa_bic' => ['nullable', 'string', 'max:20'],
            'sepa_mandate_reference' => ['nullable', 'string', 'max:255'],
            'sepa_mandate_signed_on' => ['nullable', 'date'],
            'sepa_mandate_active' => ['boolean'],
            'joined_on' => ['nullable', 'date'],
            'membership_ends_on' => ['nullable', 'date'],
            'membership_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $calculator = app(WebClubMembershipController::class);
        $auditedFields = [
            'name',
            'email',
            'phone',
            'country',
            'street',
            'house_number',
            'postal_code',
            'city',
            'membership_status',
            'club_membership_type_id',
            'member_number',
            'joined_on',
            'membership_ends_on',
        ];
        $auditBefore = $externalMember->only($auditedFields);
        $timelineBefore = [
            'membership_status' => $externalMember->membership_status,
            'joined_on' => $externalMember->joined_on?->toDateString(),
            'membership_ends_on' => $externalMember->membership_ends_on?->toDateString(),
        ];
        $previousFamilyGroupKey = $this->normalizeFamilyGroupKey($externalMember->family_group_key);
        $nextFamilyGroupKey = $this->normalizeFamilyGroupKey($data['family_group_key'] ?? null);

        $externalMember->update([
            'name' => trim((string) ($data['name'] ?? '')) ?: null,
            'email' => strtolower(trim((string) $data['email'])),
            'phone' => trim((string) ($data['phone'] ?? '')) ?: null,
            'country' => strtoupper(trim((string) ($data['country'] ?? ''))) ?: null,
            'street' => trim((string) ($data['street'] ?? '')) ?: null,
            'house_number' => trim((string) ($data['house_number'] ?? '')) ?: null,
            'postal_code' => trim((string) ($data['postal_code'] ?? '')) ?: null,
            'city' => trim((string) ($data['city'] ?? '')) ?: null,
            'role' => $data['role'] ?? $externalMember->role ?? 'member',
            'membership_status' => $data['membership_status'],
            'club_membership_type_id' => $data['club_membership_type_id'] ?? null,
            'family_group_key' => $nextFamilyGroupKey,
            'contribution_payer_user_id' => filled($data['contribution_payer_user_id'] ?? null)
                ? (int) $data['contribution_payer_user_id']
                : null,
            'member_number' => trim((string) ($data['member_number'] ?? '')) ?: null,
            'athlete_license_number' => trim((string) ($data['athlete_license_number'] ?? '')) ?: null,
            'athlete_license_valid_until' => $data['athlete_license_valid_until'] ?? null,
            'contribution_amount' => $data['contribution_amount'] ?? null,
            'contribution_interval' => $data['contribution_interval'] ?? 'none',
            'contribution_next_invoice_on' => ClubMembershipInput::normalizedNextInvoiceDate($data),
            'contribution_last_invoice_at' => null,
            'sepa_iban' => $this->normalizeIbanValue($data['sepa_iban'] ?? null),
            'sepa_bic' => $this->normalizeBicValue($data['sepa_bic'] ?? null),
            'sepa_mandate_reference' => trim((string) ($data['sepa_mandate_reference'] ?? '')) ?: null,
            'sepa_mandate_signed_on' => $data['sepa_mandate_signed_on'] ?? null,
            'sepa_mandate_active' => (bool) ($data['sepa_mandate_active'] ?? false),
            'joined_on' => $data['joined_on'] ?? null,
            'membership_ends_on' => $data['membership_ends_on'] ?? null,
            'membership_end_notified_at' => null,
            'membership_ended_at' => null,
            'membership_notes' => trim((string) ($data['membership_notes'] ?? '')) ?: null,
        ]);

        $timelineAfter = [
            'membership_status' => $externalMember->membership_status,
            'joined_on' => $externalMember->joined_on?->toDateString(),
            'membership_ends_on' => $externalMember->membership_ends_on?->toDateString(),
        ];
        $this->recordExternalTimelineChanges($club, $externalMember, $request->user(), $timelineBefore, $timelineAfter);

        $auditAfter = $externalMember->fresh()->only($auditedFields);
        $changedFields = collect($auditedFields)
            ->filter(fn (string $field) => (string) ($auditBefore[$field] ?? '') !== (string) ($auditAfter[$field] ?? ''))
            ->values()
            ->all();
        if ($changedFields !== []) {
            ClubAuditLog::record($club, $request->user(), 'club.member.external_updated', $externalMember, [
                'external_member_id' => $externalMember->id,
                'changed_fields' => $changedFields,
            ]);
        }

        $timelineAfter = [
            'membership_status' => $externalMember->membership_status,
            'joined_on' => $externalMember->joined_on?->toDateString(),
            'membership_ends_on' => $externalMember->membership_ends_on?->toDateString(),
        ];
        $this->recordExternalTimelineChanges($club, $externalMember, $request->user(), $timelineBefore, $timelineAfter);

        $auditAfter = $externalMember->fresh()->only($auditedFields);
        $changedFields = collect($auditedFields)
            ->filter(fn (string $field) => (string) ($auditBefore[$field] ?? '') !== (string) ($auditAfter[$field] ?? ''))
            ->values()
            ->all();
        if ($changedFields !== []) {
            ClubAuditLog::record($club, $request->user(), 'club.member.external_updated', $externalMember, [
                'external_member_id' => $externalMember->id,
                'changed_fields' => $changedFields,
            ]);
        }

        foreach (array_unique(array_filter([$previousFamilyGroupKey, $nextFamilyGroupKey])) as $familyGroupKey) {
            $calculator->recalculateFamilyGroupContributions($club, $familyGroupKey);
        }

        return $this->membershipManagementResponse($request, $club, __('organization.club.external_member_updated'));
    }

    private function recordExternalTimelineChanges(
        Club $club,
        ClubExternalMember $externalMember,
        User $actor,
        array $before,
        array $after,
    ): void {
        $definitions = [
            'membership_status' => ['type' => 'status_change', 'title' => __('organization.club.timeline_status_changed')],
            'joined_on' => ['type' => 'membership_date', 'title' => __('organization.club.timeline_joined_on_changed')],
            'membership_ends_on' => ['type' => 'membership_date', 'title' => __('organization.club.timeline_ends_on_changed')],
        ];

        foreach ($definitions as $field => $definition) {
            if (($before[$field] ?? null) === ($after[$field] ?? null)) {
                continue;
            }
            ClubMemberTimelineEntry::query()->create([
                'club_id' => $club->id,
                'subject_type' => 'external_member',
                'subject_id' => $externalMember->id,
                'type' => $definition['type'],
                'title' => $definition['title'],
                'occurred_on' => now()->toDateString(),
                'from_value' => $before[$field] ?? null,
                'to_value' => $after[$field] ?? null,
                'created_by' => $actor->id,
            ]);
        }
    }

    public function mergeExternalMember(
        Request $request,
        Club $club,
        ClubExternalMember $externalMember,
        User $user,
    ) {
        $this->authorizeVisible($request, $club);
        abort_unless($this->canManageMembership($request, $club), 403);

        app(WebClubMembershipController::class)->mergeExternalMember(
            $request,
            $club,
            $externalMember,
            $user,
        );

        return $this->membershipManagementResponse($request, $club, __('organization.club.duplicate_merged'));
    }

    public function removeExternalMember(Request $request, Club $club, ClubExternalMember $externalMember)
    {
        $this->authorizeVisible($request, $club);
        abort_unless($this->canUseClubPermission($request, $club, ClubPermissions::MEMBERS_DELETE), 403);
        abort_unless((int) $externalMember->club_id === (int) $club->id, 404);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
        ], [
            'reason.required' => __('organization.club.removal_reason_required'),
            'reason.string' => __('organization.club.removal_reason_string'),
            'reason.min' => __('organization.club.removal_reason_min', ['min' => 3]),
            'reason.max' => __('organization.club.removal_reason_max', ['max' => 2000]),
        ]);
        $reason = trim((string) $data['reason']);
        abort_if($reason === '', 422, __('organization.club.removal_reason_required'));

        $familyGroupKey = $this->normalizeFamilyGroupKey($externalMember->family_group_key);
        $memberName = $externalMember->name;
        $memberEmail = $externalMember->email;
        $externalMemberId = $externalMember->id;
        $externalMember->delete();

        if ($familyGroupKey !== null) {
            app(WebClubMembershipController::class)->recalculateFamilyGroupContributions($club, $familyGroupKey);
        }

        ClubAuditLog::record($club, $request->user(), 'club.member.removed', $externalMember, [
            'external_member_id' => $externalMemberId,
            'member_name' => $memberName,
            'member_email' => $memberEmail,
            'reason' => $reason,
        ]);

        return $this->membershipManagementResponse($request, $club, __('organization.club.external_member_removed'));
    }

    public function acceptExternalInvitation(Request $request, string $token)
    {
        app(WebClubMembershipController::class)->acceptExternalInvitation($request, $token);

        return response()->json(['message' => __('organization.club.membership_linked')]);
    }

    public function externalInvitationByToken(Request $request, string $token)
    {
        $externalMember = $this->pendingExternalInvitationByToken($token);
        $this->assertExternalInvitationRecipient($request, $externalMember);

        return response()->json([
            'data' => $this->externalInvitationPayload($externalMember),
        ]);
    }

    public function declineExternalInvitation(Request $request, string $token)
    {
        $externalMember = $this->pendingExternalInvitationByToken($token);
        $this->assertExternalInvitationRecipient($request, $externalMember);

        $externalMember->update([
            'invitation_status' => 'declined',
        ]);

        return response()->json([
            'data' => $this->externalInvitationPayload($externalMember->fresh(['club:id,name'])),
            'message' => __('organization.club.invitation_declined'),
        ]);
    }

    public function createMemberInvoice(Request $request, Club $club, User $user)
    {
        $this->authorizeVisible($request, $club);
        abort_unless($this->canManageFinance($request, $club), 403);
        app(WebClubMembershipController::class)->storeInvoice($request, $club, $user);

        return $this->membershipManagementResponse($request, $club, __('organization.club.invoice_created_short'), 201);
    }

    public function createExternalMemberInvoice(Request $request, Club $club, ClubExternalMember $externalMember)
    {
        $this->authorizeVisible($request, $club);
        abort_unless($this->canManageFinance($request, $club), 403);
        abort_unless((int) $externalMember->club_id === (int) $club->id, 404);
        app(WebClubMembershipController::class)->storeExternalMemberInvoice($request, $club, $externalMember);

        return $this->membershipManagementResponse($request, $club, __('organization.club.invoice_created_short'), 201);
    }

    public function previewContributionInvoiceRun(Request $request, Club $club, ClubContributionInvoiceRunService $invoiceRuns)
    {
        $this->authorizeVisible($request, $club);
        abort_unless($this->canManageFinance($request, $club), 403);
        $this->planFeatures->ensureAllows($club, 'invoices');

        $data = $request->validate([
            'run_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        return response()->json([
            'data' => $invoiceRuns->preview($club, $data['run_date'] ?? null, [
                'due_date' => $data['due_date'] ?? null,
                'title' => $data['title'] ?? null,
            ]),
        ]);
    }

    public function createContributionInvoiceRun(Request $request, Club $club, ClubContributionInvoiceRunService $invoiceRuns)
    {
        $this->authorizeVisible($request, $club);
        abort_unless($this->canManageFinance($request, $club), 403);
        $this->planFeatures->ensureAllows($club, 'invoices');

        $data = $request->validate([
            'run_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        $result = $invoiceRuns->create($club, $request->user(), $data['run_date'] ?? null, [
            'due_date' => $data['due_date'] ?? null,
            'title' => $data['title'] ?? null,
        ]);

        return $this->membershipManagementResponse(
            $request,
            $club,
            __('organization.club.invoice_created_short'),
            201,
            ['invoice_run' => $result],
        );
    }

    public function generateMemberNumber(Request $request, Club $club, User $user)
    {
        $this->authorizeVisible($request, $club);
        abort_unless($this->canManageMembership($request, $club), 403);
        app(WebClubMembershipController::class)->generateMemberNumber($club, $user, $request->user());

        return $this->membershipManagementResponse($request, $club, __('organization.club.member_number_generated'));
    }

    public function updateMembershipInvoiceStatus(Request $request, Club $club, Invoice $invoice)
    {
        $this->authorizeVisible($request, $club);
        abort_unless($this->canManageFinance($request, $club), 403);
        abort_unless((int) $invoice->club_id === (int) $club->id, 404);
        app(WebClubMembershipController::class)->updateInvoiceStatus($request, $invoice);

        return $this->membershipManagementResponse($request, $club, __('organization.club.invoice_status_updated'));
    }

    public function sendMembershipInvoiceReminder(Request $request, Club $club, Invoice $invoice)
    {
        $this->authorizeVisible($request, $club);
        abort_unless($this->canManageFinance($request, $club), 403);
        abort_unless((int) $invoice->club_id === (int) $club->id, 404);
        app(WebClubMembershipController::class)->sendReminder($invoice);

        return $this->membershipManagementResponse($request, $club, __('organization.club.payment_reminder_sent'));
    }

    public function membershipInvoiceDownloadAuthorization(Request $request, Club $club, Invoice $invoice)
    {
        $this->authorizeVisible($request, $club);
        abort_unless($this->canManageFinance($request, $club), 403);
        abort_unless((int) $invoice->club_id === (int) $club->id, 404);

        return response()->json(['data' => [
            'url' => ProtectedDocumentDownload::temporaryUrl(
                'api.v1.clubs.membership-invoices.download',
                [$club, $invoice],
                ProtectedDocumentDownload::PURPOSE_INVOICE,
            ),
            'purpose' => ProtectedDocumentDownload::PURPOSE_INVOICE,
            'expires_in_seconds' => 300,
        ]]);
    }

    public function downloadMembershipInvoice(Request $request, Club $club, Invoice $invoice)
    {
        $this->authorizeVisible($request, $club);
        abort_unless($this->canManageFinance($request, $club), 403);
        abort_unless((int) $invoice->club_id === (int) $club->id, 404);
        ProtectedDocumentDownload::assertAuthorized($request, ProtectedDocumentDownload::PURPOSE_INVOICE);
        ProtectedDocumentDownload::audit(
            $club,
            $request->user(),
            ProtectedDocumentDownload::PURPOSE_INVOICE,
            'membership_invoice',
            $invoice,
        );

        $issuedAt = ($invoice->issued_at ?? $invoice->created_at ?? now())->format('Y-m-d');
        $body = implode("\n", [
            'Airmius Mitgliedsrechnung',
            'Rechnungsnummer: '.$invoice->number,
            'Ausgestellt am: '.$issuedAt,
            'Betrag: '.$invoice->amount.' EUR',
            'Status: '.$invoice->statusLabel(),
            '',
        ]);

        return response($body, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="membership-invoice-'.$invoice->id.'.txt"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function importBankTransactions(Request $request, Club $club)
    {
        $this->authorizeVisible($request, $club);
        abort_unless($this->canManageFinance($request, $club), 403);
        app(WebClubMembershipController::class)->importBankTransactions($request, $club);

        return $this->membershipManagementResponse($request, $club, 'Bankumsätze als Vorschläge vorbereitet. Bitte bestätige passende Buchungen manuell.', 201);
    }

    public function previewBankTransactions(Request $request, Club $club)
    {
        $this->authorizeVisible($request, $club);
        abort_unless($this->canManageFinance($request, $club), 403);
        $this->planFeatures->ensureAllows($club, 'bank_reconciliation');
        $data = $request->validate([
            'file' => ['required', 'file', 'max:10240'],
        ]);
        $bank = app(ClubMembershipBankReconciliationService::class);
        $rows = [];
        $errors = [];
        $stats = ['total' => 0, 'importable' => 0, 'matched' => 0, 'suggested' => 0, 'conflict' => 0, 'duplicates' => 0, 'invalid' => 0];

        foreach ($bank->readRows($data['file']->getRealPath()) as $index => $rawRow) {
            $rowNumber = $index + 2;
            $stats['total']++;
            $transaction = $bank->transactionFromRow($rawRow);

            if (! $transaction || blank($transaction['booking_date']) || (float) $transaction['amount'] <= 0) {
                $stats['invalid']++;
                $errors[] = ['row' => $rowNumber, 'reason' => __('organization.club.bank_preview_invalid_row')];
                continue;
            }

            $duplicate = BankTransaction::query()
                ->where('club_id', $club->id)
                ->where('transaction_hash', $transaction['transaction_hash'])
                ->exists();
            $preview = $duplicate
                ? ['status' => 'duplicate', 'confidence' => 100, 'reason' => __('organization.club.bank_preview_duplicate'), 'allocations' => [], 'allocated_cents' => 0, 'unallocated_cents' => 0, 'overpaid_cents' => 0, 'conflicts' => []]
                : $bank->previewAllocations($club, $transaction);
            $status = $preview['status'] === 'unmatched' ? 'conflict' : $preview['status'];
            $stats[$duplicate ? 'duplicates' : $status]++;

            if (! $duplicate && $preview['allocations'] !== [] && $preview['conflicts'] === []) {
                $stats['importable']++;
            }

            $iban = (string) ($transaction['debtor_iban'] ?? '');
            $firstInvoice = $preview['allocations'][0]['invoice'] ?? null;
            $rows[] = [
                'row' => $rowNumber,
                'transaction_hash' => $transaction['transaction_hash'],
                'booking_date' => $transaction['booking_date'],
                'amount' => $transaction['amount'],
                'currency' => $transaction['currency'],
                'debtor_name' => $transaction['debtor_name'],
                'debtor_iban_masked' => $iban === '' ? null : '•••• '.substr($iban, -4),
                'purpose' => $transaction['purpose'],
                'status' => $preview['status'],
                'confidence' => $preview['confidence'],
                'reason' => $preview['reason'],
                'allocated_amount' => number_format($preview['allocated_cents'] / 100, 2, '.', ''),
                'unallocated_amount' => number_format($preview['unallocated_cents'] / 100, 2, '.', ''),
                'overpaid_amount' => number_format($preview['overpaid_cents'] / 100, 2, '.', ''),
                'conflicts' => $preview['conflicts'],
                'allocations' => collect($preview['allocations'])->map(fn (array $allocation) => [
                    'invoice' => [
                        'id' => $allocation['invoice']->id,
                        'number' => $allocation['invoice']->number,
                        'title' => $allocation['invoice']->title,
                        'amount' => $allocation['invoice']->amount,
                    ],
                    'amount' => number_format($allocation['amount_cents'] / 100, 2, '.', ''),
                    'outstanding_amount' => number_format($allocation['outstanding_cents'] / 100, 2, '.', ''),
                    'overpaid_amount' => number_format($allocation['overpaid_cents'] / 100, 2, '.', ''),
                    'mode' => $allocation['mode'],
                ])->values()->all(),
                'invoice' => $firstInvoice ? [
                    'id' => $firstInvoice->id,
                    'number' => $firstInvoice->number,
                    'title' => $firstInvoice->title,
                    'amount' => $firstInvoice->amount,
                ] : null,
            ];
        }

        return response()->json([
            'data' => [
                'file_name' => $data['file']->getClientOriginalName(),
                'can_import' => $stats['importable'] > 0,
                'stats' => $stats,
                'rows' => array_slice($rows, 0, 100),
                'errors' => array_slice($errors, 0, 100),
            ],
        ]);
    }

    public function confirmBankTransaction(Request $request, Club $club, BankTransaction $bankTransaction)
    {
        $this->authorizeVisible($request, $club);
        abort_unless($this->canManageFinance($request, $club), 403);
        abort_unless((int) $bankTransaction->club_id === (int) $club->id, 404);
        app(WebClubMembershipController::class)->confirmBankTransaction($bankTransaction);

        return $this->membershipManagementResponse($request, $club, __('organization.club.bank_transaction_booked'));
    }

    public function updateDatevSettingsApi(Request $request, Club $club)
    {
        $this->authorizeVisible($request, $club);
        abort_unless($this->canManageFinance($request, $club), 403);
        app(WebClubMembershipController::class)->updateDatevSettings($request, $club);

        return $this->membershipManagementResponse($request, $club, __('organization.club.datev_settings_saved'));
    }

    public function exportDatev(Request $request, Club $club)
    {
        $this->authorizeVisible($request, $club);
        abort_unless($this->canUseClubPermission($request, $club, ClubPermissions::FINANCE_EXPORT), 403);

        return app(WebClubMembershipController::class)->exportDatev($request, $club);
    }

    private function membershipManagementResponse(
        Request $request,
        Club $club,
        string $message,
        int $status = 200,
        array $extra = [],
    ) {
        return response()->json([
            'message' => $message,
            'data' => $this->managementPayload($request, $club->fresh(), true),
            ...$extra,
        ], $status);
    }

    private function authorizeVisible(Request $request, Club $club): void
    {
        abort_unless(
            Club::visibleTo($request->user())->whereKey($club->id)->exists(),
            404,
        );
    }

    private function canManageMembership(Request $request, Club $club): bool
    {
        $user = $request->user();

        return $user instanceof User
            && ClubPermissions::allows($club, $user, ClubPermissions::MEMBERS_MANAGE);
    }

    private function canUseClubPermission(Request $request, Club $club, string $permission): bool
    {
        $user = $request->user();

        return $user instanceof User && ClubPermissions::allows($club, $user, $permission);
    }

    private function canManageFinance(Request $request, Club $club): bool
    {
        $user = $request->user();

        return $user instanceof User
            && ClubPermissions::allows($club, $user, ClubPermissions::FINANCE_MANAGE);
    }

    private function canViewFinance(Request $request, Club $club): bool
    {
        $user = $request->user();

        return $user instanceof User
            && ClubPermissions::allows($club, $user, ClubPermissions::FINANCE_VIEW);
    }

    private function clubPermissionsPayload(Club $club, User $member, User $actor): array
    {
        $membership = $club->users()->where('users.id', $member->id)->firstOrFail();
        $roles = ClubRoles::normalize($membership->pivot?->role, $membership->pivot?->roles ?? []);

        return [
            'member' => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'role' => ClubRoles::primary($roles),
                'roles' => $roles,
            ],
            'catalog' => ClubPermissions::catalog(),
            'defaults' => ClubPermissions::defaultsForRoles($roles),
            'overrides' => ClubPermissions::normalizeOverrides($membership->pivot?->permission_overrides),
            'effective' => ClubPermissions::effectiveFor($club, $member),
            'editable' => ClubPermissions::editableBy($club, $actor),
        ];
    }

    private function publicMembershipTypesWithContributions(Club $club): array
    {
        $types = $club->membershipTypes()
            ->where('is_public', true)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'description', 'is_public', 'is_active', 'sort_order']);
        $rules = $club->contributionRules()
            ->effectiveOn(now()->toDateString())
            ->orderByDesc('valid_from')
            ->get(['club_membership_type_id', 'amount', 'billing_interval']);

        return $types->map(function (ClubMembershipType $type) use ($rules) {
            $rule = $rules->first(fn (ClubContributionRule $rule) => (int) $rule->club_membership_type_id === (int) $type->id
            ) ?: $rules->first(fn (ClubContributionRule $rule) => $rule->club_membership_type_id === null);

            return [
                'id' => $type->id,
                'name' => $type->name,
                'slug' => $type->slug,
                'description' => $type->description,
                'amount' => $rule?->amount,
                'billing_interval' => $rule?->billing_interval,
                'is_public' => (bool) $type->is_public,
                'is_active' => (bool) $type->is_active,
                'sort_order' => $type->sort_order,
            ];
        })->values()->all();
    }

    private function financeBalanceSummary(Club $club): array
    {
        $periodStart = now()->startOfYear();
        $periodEnd = now()->endOfYear();

        $paymentTotalsByMethod = Payment::query()
            ->where('club_id', $club->id)
            ->where('status', 'paid')
            ->selectRaw("COALESCE(method, 'manual') as payment_method, SUM(amount) as amount")
            ->groupBy('payment_method')
            ->pluck('amount', 'payment_method');

        $paymentCash = (float) ($paymentTotalsByMethod->get('cash', 0));
        $paymentBank = (float) $paymentTotalsByMethod
            ->only(['bank_transfer', 'sepa_debit'])
            ->sum(fn ($amount) => (float) $amount);
        $paymentTotal = (float) $paymentTotalsByMethod->sum(fn ($amount) => (float) $amount);
        $unassignedBalance = max(0, $paymentTotal - $paymentCash - $paymentBank);

        $entryTotals = ClubFinanceEntry::query()
            ->where('club_id', $club->id)
            ->selectRaw('account, type, SUM(amount) as amount')
            ->groupBy('account', 'type')
            ->get();

        $entryTotal = function (string $account, string $type) use ($entryTotals): float {
            $row = $entryTotals->first(fn ($item) => $item->account === $account && $item->type === $type);

            return (float) ($row?->amount ?? 0);
        };

        $incomeTotal = $paymentTotal + (float) $entryTotals
            ->where('type', 'income')
            ->sum(fn ($item) => (float) $item->amount);
        $expenseTotal = (float) $entryTotals
            ->where('type', 'expense')
            ->sum(fn ($item) => (float) $item->amount);
        $cashBalance = $paymentCash + $entryTotal('cash', 'income') - $entryTotal('cash', 'expense');
        $bankBalance = $paymentBank + $entryTotal('bank', 'income') - $entryTotal('bank', 'expense');

        $periodPaymentTotal = (float) Payment::query()
            ->where('club_id', $club->id)
            ->whereIn('status', ['paid', 'returned'])
            ->where(function ($query) use ($periodStart, $periodEnd) {
                $query
                    ->whereBetween('paid_at', [$periodStart, $periodEnd])
                    ->orWhere(function ($fallbackQuery) use ($periodStart, $periodEnd) {
                        $fallbackQuery
                            ->whereNull('paid_at')
                            ->whereBetween('created_at', [$periodStart, $periodEnd]);
                    });
            })
            ->sum('amount');

        $periodEntryTotals = ClubFinanceEntry::query()
            ->where('club_id', $club->id)
            ->whereBetween('booked_on', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->selectRaw('type, SUM(amount) as amount')
            ->groupBy('type')
            ->pluck('amount', 'type');

        $incomePeriodTotal = $periodPaymentTotal + (float) ($periodEntryTotals->get('income', 0));
        $returnsTotal = ClubSepaSettlement::returnedAmount($club->id);
        $incomeTotal += $returnsTotal;
        $expenseTotal += $returnsTotal;
        $expensePeriodTotal = (float) ($periodEntryTotals->get('expense', 0)) + ClubSepaSettlement::returnedAmount($club->id, $periodStart, $periodEnd);

        return [
            'cash_balance' => $cashBalance,
            'bank_balance' => $bankBalance,
            'unassigned_balance' => $unassignedBalance,
            'total_balance' => $cashBalance + $bankBalance + $unassignedBalance,
            'income_total' => $incomeTotal,
            'expense_total' => $expenseTotal,
            'income_period_total' => $incomePeriodTotal,
            'expense_period_total' => $expensePeriodTotal,
            'finance_period' => 'year',
            'finance_period_year' => (int) $periodStart->year,
            'finance_period_label' => __('platform.organization.this_year'),
        ];
    }

    private function managementPayload(Request $request, Club $club, bool $canManageMembership): array
    {
        $actor = $request->user();
        $effectivePermissions = $actor instanceof User
            ? ClubPermissions::effectiveFor($club, $actor)
            : array_fill_keys(ClubPermissions::ALL, false);
        $canManageMembers = (bool) ($effectivePermissions[ClubPermissions::MEMBERS_MANAGE] ?? false);
        $canViewFinance = (bool) ($effectivePermissions[ClubPermissions::FINANCE_VIEW] ?? false);
        $canAccessManagement = $canManageMembers || $canViewFinance;
        $subscription = [
            'plan' => $club->subscriptionPlan(),
            'member_usage' => $club->memberUsageCount(),
            'member_limit' => $club->subscriptionPlan()?->member_limit,
            'team_limit' => $club->subscriptionPlan()?->team_limit,
            'storage_bytes' => (int) $club->files()->sum('size'),
            'storage_gb' => $club->subscriptionPlan()?->storage_gb,
        ];

        $base = [
            'can_manage' => $canAccessManagement,
            'can_manage_members' => $canManageMembers,
            'can_manage_finance' => (bool) ($effectivePermissions[ClubPermissions::FINANCE_MANAGE] ?? false),
            'permissions' => [
                'catalog' => ClubPermissions::catalog(),
                'effective' => $effectivePermissions,
                'can_manage_finance' => (bool) ($effectivePermissions[ClubPermissions::FINANCE_MANAGE] ?? false),
                'can_edit_member_permissions' => $actor instanceof User
                    && ClubPermissions::editableBy($club, $actor),
            ],
            'subscription' => $subscription,
            'capabilities' => $this->planFeatures->capabilities($club),
            'teams' => TeamResource::collection($club->teams)->resolve($request),
            'onboarding' => $canAccessManagement ? $this->clubOnboarding->forClub($club) : null,
        ];

        if (! $canAccessManagement) {
            $membershipRequests = ClubMembershipRequest::query()
                ->where('club_id', $club->id)
                ->where('user_id', $request->user()->id)
                ->where('type', 'membership')
                ->latest('id')
                ->get()
                ->load(['club', 'user', 'membershipType', 'department']);

            return [
                ...$base,
                'settings' => [
                    'membership_requests_enabled' => (bool) $club->membership_requests_enabled,
                    'membership_application_fields' => ClubMembershipApplication::fieldsForClub($club->membership_application_fields),
                    'membership_payment_methods' => ClubMembershipApplication::normalizePaymentMethods($club->membership_payment_methods),
                    'membership_payment_method_options' => ClubMembershipApplication::paymentMethods(),
                    'membership_application_documents' => collect(ClubMembershipApplication::normalizeDocuments($club->membership_application_documents, $club->membership_application_document_types))
                        ->where('is_visible', true)
                        ->values()
                        ->all(),
                ],
                'membership_types' => $this->publicMembershipTypesWithContributions($club),
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

        $club->load([
            'users' => fn ($query) => $query
                ->select('users.id', 'name', 'email', 'phone', 'country', 'street', 'house_number', 'postal_code', 'city', 'athlete_license_number', 'athlete_license_valid_until', 'profile_photo_path')
                ->withCount(['invoices', 'payments'])
                ->orderBy('name'),
        ]);
        $club->loadMissing([
            'externalMembers' => fn ($query) => $query
                ->with(['linkedUser:id,name,email,profile_photo_path', 'membershipType:id,club_id,name'])
                ->orderBy('name')
                ->orderBy('email'),
            'memberTimelineEntries' => fn ($query) => $query
                ->with('creator:id,name')
                ->orderByDesc('occurred_on')
                ->latest('id'),
            'currentSubscription.plan',
            'membershipTypes' => fn ($query) => $query->orderBy('sort_order')->orderBy('name'),
            'contributionRules' => fn ($query) => $query->with(['membershipType:id,name', 'policyDocument:id,club_id,title,version_label,valid_from,valid_until'])->orderByDesc('valid_from')->orderBy('name'),
            'policyDocuments' => fn ($query) => $query->where('type', 'contribution_model')->orderByDesc('valid_from'),
        ]);

        $membershipRequests = ClubMembershipRequest::query()
            ->where('club_id', $club->id)
            ->whereIn('status', ['pending', 'information_requested', 'waitlisted'])
            ->with(['club', 'user', 'membershipType:id,name', 'department:id,name'])
            ->latest('id')
            ->get();

        $invoices = Invoice::query()
            ->withSum('settledPayments', 'amount')
            ->where('club_id', $club->id)
            ->with(['club', 'user', 'businessYearPeriod', 'contributionYearPeriod'])
            ->latest('id')
            ->limit(60)
            ->get();

        $payments = Payment::query()
            ->where('club_id', $club->id)
            ->with(['club', 'invoice', 'user:id,name,email'])
            ->latest('id')
            ->limit(60)
            ->get();

        $bankTransactions = BankTransaction::query()
            ->where('club_id', $club->id)
            ->with(['invoice:id,number,title,amount,status', 'payment:id,amount,paid_at', 'businessYearPeriod'])
            ->latest('id')
            ->limit(60)
            ->get();

        $financeEntries = ClubFinanceEntry::query()
            ->where('club_id', $club->id)
            ->with(['user:id,name,email', 'businessYearPeriod', 'receiptFile'])
            ->orderByDesc('booked_on')
            ->latest('id')
            ->limit(80)
            ->get();

        $receiptUploads = ClubReceiptUpload::query()
            ->where('club_id', $club->id)
            ->with(['file', 'financeEntry'])
            ->latest('id')
            ->limit(60)
            ->get();

        $financeSummary = $this->financeBalanceSummary($club);
        $invoiceSummary = BillingOverview::clubInvoiceSummary(
            Invoice::query()
                ->where('club_id', $club->id)
                ->get(['id', 'amount', 'status'])
        );
        $nextEvent = Event::query()
            ->where('club_id', $club->id)
            ->where('status', 'scheduled')
            ->where('start_time', '>=', now())
            ->orderBy('start_time')
            ->first(['id', 'title', 'start_time']);
        $unreadAnnouncementsCount = $actor instanceof User
            ? ClubAnnouncement::query()
                ->where('club_id', $club->id)
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now())
                ->whereDoesntHave('reads', fn ($query) => $query->where('user_id', $actor->id))
                ->count()
            : 0;

        if (! (bool) ($effectivePermissions[ClubPermissions::FINANCE_VIEW] ?? false)) {
            $invoices = collect();
            $payments = collect();
            $bankTransactions = collect();
            $financeEntries = collect();
            $receiptUploads = collect();
            $invoiceSummary = BillingOverview::clubInvoiceSummary(collect());
            $financeSummary = [
                'cash_balance' => 0,
                'bank_balance' => 0,
                'unassigned_balance' => 0,
                'total_balance' => 0,
                'income_total' => 0,
                'expense_total' => 0,
                'income_period_total' => 0,
                'expense_period_total' => 0,
                'finance_period' => 'year',
                'finance_period_year' => (int) now()->year,
                'finance_period_label' => __('platform.organization.this_year'),
            ];
        }

        return [
            ...$base,
            'membership_statuses' => ['active', 'non_member', 'pending', 'paused', 'former'],
            'contribution_intervals' => ['none', 'monthly', 'quarterly', 'four_monthly', 'semi_yearly', 'yearly', 'once'],
            'contribution_rule_types' => ClubContributionRule::ruleTypeOptions(),
            'contribution_discount_operators' => ClubContributionRule::discountOperatorOptions(),
            'contribution_proration_policies' => ClubContributionRule::prorationPolicyOptions(),
            'contribution_policy_documents' => $club->policyDocuments->map(fn (ClubPolicyDocument $document) => [
                'id' => $document->id,
                'title' => $document->title,
                'version_label' => $document->version_label,
                'valid_from' => $document->valid_from?->toDateString(),
                'valid_until' => $document->valid_until?->toDateString(),
            ])->values(),
            'invoice_status_options' => Invoice::statusOptions(),
            'club_roles' => ClubRoles::options(),
            'invitable_club_roles' => ClubRoles::options(ClubRoles::INVITABLE),
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
                'datev_fee_account' => $club->datev_fee_account,
                'membership_requests_enabled' => $club->membership_requests_enabled,
                'member_pause_requests_enabled' => $club->member_pause_requests_enabled,
                'membership_application_fields' => ClubMembershipApplication::fieldsForClub($club->membership_application_fields),
                'membership_payment_methods' => ClubMembershipApplication::normalizePaymentMethods($club->membership_payment_methods),
                'membership_payment_method_options' => ClubMembershipApplication::paymentMethods(),
                'membership_application_documents' => ClubMembershipApplication::normalizeDocuments($club->membership_application_documents, $club->membership_application_document_types),
                'membership_application_document_types' => ClubMembershipApplication::documentTypes($club->membership_application_document_types),
            ],
            'members' => $this->managementMembersPayload($request, $club),
            'external_members' => $club->externalMembers->map(fn (ClubExternalMember $externalMember) => [
                'id' => $externalMember->id,
                'name' => $externalMember->name,
                'email' => $externalMember->email,
                'phone' => $externalMember->phone,
                'country' => $externalMember->country,
                'street' => $externalMember->street,
                'house_number' => $externalMember->house_number,
                'postal_code' => $externalMember->postal_code,
                'city' => $externalMember->city,
                'role' => $externalMember->role,
                'membership_status' => $externalMember->membership_status,
                'club_membership_type_id' => $externalMember->club_membership_type_id,
                'membership_type' => $externalMember->membershipType,
                'family_group_key' => $externalMember->family_group_key,
                'contribution_payer_user_id' => $externalMember->contribution_payer_user_id,
                'member_number' => $externalMember->member_number,
                'athlete_license_number' => $externalMember->athlete_license_number,
                'athlete_license_valid_until' => $externalMember->athlete_license_valid_until?->toDateString(),
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
                'membership_ended_at' => $externalMember->membership_ended_at?->toJSON(),
                'membership_notes' => $externalMember->membership_notes,
                'invitation_status' => $externalMember->invitation_status,
                'invitation_token' => $externalMember->invitation_token,
                'invitation_url' => $externalMember->invitationUrl(),
                'invited_at' => $externalMember->invited_at?->toJSON(),
                'invitation_expires_at' => $externalMember->invitation_expires_at?->toJSON(),
                'linked_at' => $externalMember->linked_at?->toJSON(),
                'linked_user' => $externalMember->linkedUser,
                'duplicate_candidate' => ClubMemberDuplicates::candidate($club, $externalMember),
            ])->values(),
            'member_timeline_entries' => $club->memberTimelineEntries
                ->map(fn ($entry) => $entry->payload())
                ->values(),
            'membership_types' => $club->membershipTypes->map(fn (ClubMembershipType $type) => [
                'id' => $type->id,
                'name' => $type->name,
                'slug' => $type->slug,
                'description' => $type->description,
                'is_public' => $type->is_public,
                'is_active' => $type->is_active,
                'sort_order' => $type->sort_order,
                'application_fields' => $type->application_fields,
            ])->values(),
            'contribution_rules' => $club->contributionRules->map(fn (ClubContributionRule $rule) => [
                'id' => $rule->id,
                'club_membership_type_id' => $rule->club_membership_type_id,
                'club_policy_document_id' => $rule->club_policy_document_id,
                'policy_document' => $rule->policyDocument ? [
                    'id' => $rule->policyDocument->id,
                    'title' => $rule->policyDocument->title,
                    'version_label' => $rule->policyDocument->version_label,
                ] : null,
                'membership_type_name' => $rule->membershipType?->name,
                'name' => $rule->name,
                'valid_from' => $rule->valid_from?->toDateString(),
                'valid_until' => $rule->valid_until?->toDateString(),
                'billing_interval' => $rule->billing_interval,
                'proration_policy' => $rule->proration_policy ?: 'prorate_days',
                'proration_policy_label' => ClubContributionRule::PRORATION_POLICY_LABELS[$rule->proration_policy ?: 'prorate_days'] ?? 'Anteilig nach Tagen',
                'amount' => $rule->amount,
                'age_min' => $rule->age_min,
                'age_max' => $rule->age_max,
                'factor_key' => $rule->factor_key ?: 'standard',
                'factor_label' => ClubContributionRule::RULE_TYPE_LABELS[$rule->factor_key ?: 'standard'] ?? 'Standardbeitrag',
                'factor_operator' => $rule->factor_operator,
                'factor_operator_label' => $rule->factor_operator ? (ClubContributionRule::DISCOUNT_OPERATOR_LABELS[$rule->factor_operator] ?? $rule->factor_operator) : null,
                'factor_value' => $rule->factor_value,
                'priority' => $rule->priority,
                'tax_account' => $rule->tax_account,
                'accounting_account' => $rule->accounting_account,
                'snapshot' => $rule->snapshot,
                'is_active' => $rule->is_active,
                'notes' => $rule->notes,
            ])->values(),
            'membership_requests' => ClubMembershipRequestResource::collection($membershipRequests)->resolve($request),
            'club_requests' => ClubMembershipRequestResource::collection($membershipRequests)->resolve($request),
            'invoices' => InvoiceResource::collection($invoices)->resolve($request),
            'invoice_summary' => $invoiceSummary,
            'payments' => PaymentResource::collection($payments)->resolve($request),
            'audit_logs' => ClubAuditLog::forClub($club),
            'finance_entries' => ClubFinanceEntryResource::collection($financeEntries)->resolve($request),
            'receipt_uploads' => $receiptUploads->map(fn (ClubReceiptUpload $upload) => $this->receiptUploadPayload($upload))->values(),
            'bank_transactions' => $bankTransactions->map(fn (BankTransaction $transaction) => [
                'id' => $transaction->id,
                'club_id' => $transaction->club_id,
                'business_year_period_id' => $transaction->business_year_period_id,
                'business_year_period' => $transaction->businessYearPeriod ? [
                    'id' => $transaction->businessYearPeriod->id,
                    'name' => $transaction->businessYearPeriod->name,
                    'starts_on' => $transaction->businessYearPeriod->starts_on?->toDateString(),
                    'ends_on' => $transaction->businessYearPeriod->ends_on?->toDateString(),
                ] : null,
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
                'active_members_count' => $club->users->filter(fn (User $member) => ($member->pivot?->membership_status ?? 'active') === 'active')->count()
                    + $club->externalMembers->where('membership_status', 'active')->count(),
                'linked_people_count' => $club->users->count() + $club->externalMembers->count(),
                'pending_membership_requests_count' => $membershipRequests->count(),
                'pending_team_join_requests_count' => $pendingTeamJoinRequests->count(),
                'open_invoice_amount' => $invoiceSummary['open_amount'],
                'open_invoices_count' => $invoiceSummary['open_count'],
                'next_event_id' => $nextEvent?->id,
                'next_event_title' => $nextEvent?->title,
                'next_event_starts_at' => $nextEvent?->start_time?->toJSON(),
                'unread_announcements_count' => $unreadAnnouncementsCount,
                'sepa_ready_members_count' => $club->users->filter(fn (User $member) => (bool) ($member->pivot?->sepa_mandate_active ?? false))->count()
                    + $club->externalMembers->where('sepa_mandate_active', true)->count(),
                'recurring_contribution_total' => (float) $club->users->sum(fn (User $member) => (float) ($member->pivot?->contribution_amount ?? 0))
                    + (float) $club->externalMembers->sum(fn (ClubExternalMember $member) => (float) ($member->contribution_amount ?? 0)),
                ...$financeSummary,
            ],
        ];
    }

    private function managementMembersPayload(Request $request, Club $club): array
    {
        return $club->users->map(function (User $member) use ($request) {
            $payload = (new ClubMemberResource($member))->resolve($request);
            $payload['membership'] = [
                ...($payload['membership'] ?? []),
                'payment_method' => $member->pivot?->payment_method,
                'contribution_last_invoice_at' => $this->nullableJsonDate($member->pivot?->contribution_last_invoice_at),
                'sepa_iban' => $member->pivot?->sepa_iban,
                'sepa_bic' => $member->pivot?->sepa_bic,
                'sepa_mandate_reference' => $member->pivot?->sepa_mandate_reference,
                'sepa_mandate_signed_on' => $member->pivot?->sepa_mandate_signed_on
                    ? (string) $member->pivot->sepa_mandate_signed_on
                    : null,
                'sepa_mandate_active' => (bool) ($member->pivot?->sepa_mandate_active ?? false),
                'membership_end_notified_at' => $this->nullableJsonDate($member->pivot?->membership_end_notified_at),
                'membership_notes' => $member->pivot?->membership_notes,
            ];

            return $payload;
        })->values()->all();
    }

    private function nullableJsonDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->toJSON();
        }

        return Carbon::parse($value)->toJSON();
    }

    private function validatedFinanceEntryData(Request $request): array
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(ClubFinanceEntry::TYPES)],
            'account' => ['required', Rule::in(ClubFinanceEntry::ACCOUNTS)],
            'category' => ['nullable', 'string', 'max:120'],
            'title' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'booked_on' => ['nullable', 'date'],
            'reference' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'receipt_file_id' => ['nullable', Rule::exists('files', 'id')],
        ]);

        return [
            ...$data,
            'category' => filled($data['category'] ?? null) ? trim($data['category']) : null,
            'title' => trim($data['title']),
            'booked_on' => $data['booked_on'] ?? now()->toDateString(),
            'reference' => filled($data['reference'] ?? null) ? trim($data['reference']) : null,
            'description' => filled($data['description'] ?? null) ? trim($data['description']) : null,
            'receipt_file_id' => $data['receipt_file_id'] ?? null,
        ];
    }

    private function receiptUploadPayload(ClubReceiptUpload $upload): array
    {
        $upload->loadMissing(['file', 'financeEntry']);

        return [
            'id' => $upload->id,
            'club_id' => $upload->club_id,
            'file_id' => $upload->file_id,
            'finance_entry_id' => $upload->club_finance_entry_id,
            'status' => $upload->status,
            'scan_status' => $upload->scan_status,
            'mime_type' => $upload->mime_type,
            'size_bytes' => $upload->size_bytes,
            'ocr_suggestion' => $upload->ocr_suggestion ?: [],
            'confirmed_at' => $upload->confirmed_at?->toJSON(),
            'file' => $upload->file ? [
                'id' => $upload->file->id,
                'display_name' => $upload->file->display_name,
                'type' => $upload->file->type,
                'size' => $upload->file->size,
                'url' => $upload->file->url,
            ] : null,
            'finance_entry' => $upload->financeEntry ? [
                'id' => $upload->financeEntry->id,
                'title' => $upload->financeEntry->title,
                'amount' => $upload->financeEntry->amount,
                'booked_on' => $upload->financeEntry->booked_on?->toDateString(),
            ] : null,
            'created_at' => $upload->created_at?->toJSON(),
            'updated_at' => $upload->updated_at?->toJSON(),
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
            'application_fields' => ['nullable', 'array'],
            'application_fields.*' => ['nullable', Rule::in(ClubMembershipApplication::FIELD_MODES)],
        ]);

        return [
            ...$data,
            'is_public' => (bool) ($data['is_public'] ?? true),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'application_fields' => array_key_exists('application_fields', $data)
                ? ClubMembershipApplication::normalizeFieldModes($data['application_fields'])
                : null,
        ];
    }

    private function invitationExpiresAt(?string $date): ?Carbon
    {
        return filled($date) ? Carbon::parse($date)->endOfDay() : null;
    }

    private function validatedContributionRuleData(Request $request, Club $club): array
    {
        $data = $request->validate([
            'club_membership_type_id' => ['nullable', Rule::exists('club_membership_types', 'id')->where('club_id', $club->id)],
            'club_policy_document_id' => ['nullable', Rule::exists('club_policy_documents', 'id')->where(fn ($query) => $query->where('club_id', $club->id)->where('type', 'contribution_model'))],
            'name' => ['required', 'string', 'max:255'],
            'valid_from' => ['required', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'billing_interval' => ['required', Rule::in(self::CONTRIBUTION_INTERVALS)],
            'proration_policy' => ['nullable', Rule::in(ClubContributionRule::PRORATION_POLICIES)],
            'amount' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'age_min' => ['nullable', 'integer', 'min:0', 'max:120'],
            'age_max' => ['nullable', 'integer', 'min:0', 'max:120'],
            'factor_key' => ['nullable', Rule::in(ClubContributionRule::RULE_TYPES)],
            'factor_operator' => ['nullable', Rule::in(ClubContributionRule::DISCOUNT_OPERATORS)],
            'factor_value' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'tax_account' => ['nullable', 'string', 'max:40'],
            'accounting_account' => ['nullable', 'string', 'max:40'],
            'snapshot' => ['nullable', 'array'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $ruleType = $data['factor_key'] ?? 'standard';
        $this->validateContributionModelPeriod($data);

        if (in_array($ruleType, ['discount', 'sibling_discount', 'reduction'], true)) {
            if (blank($data['factor_operator'] ?? null)) {
                throw ValidationException::withMessages([
                    'factor_operator' => 'Rabattregeln brauchen einen Rabatt-Typ.',
                ]);
            }

            if (blank($data['factor_value'] ?? null)) {
                throw ValidationException::withMessages([
                    'factor_value' => 'Rabattregeln brauchen einen Rabattwert.',
                ]);
            }

            if (($data['factor_operator'] ?? null) === 'percent' && (float) $data['factor_value'] > 100) {
                throw ValidationException::withMessages([
                    'factor_value' => __('organization.club.contribution_discount_percent_max'),
                ]);
            }
        } elseif ($ruleType === 'exemption') {
            $data['factor_operator'] = null;
            $data['factor_value'] = null;
        } else {
            $data['factor_operator'] = null;
            $data['factor_value'] = null;
        }

        if ($ruleType === 'special' && ($data['billing_interval'] ?? null) !== 'once') {
            throw ValidationException::withMessages([
                'billing_interval' => __('organization.club.special_contribution_once'),
            ]);
        }

        return [
            ...$data,
            'factor_key' => $ruleType,
            'proration_policy' => $data['proration_policy'] ?? 'prorate_days',
            'priority' => (int) ($data['priority'] ?? 100),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }

    private function validateContributionModelPeriod(array $data): void
    {
        if (empty($data['club_policy_document_id'])) {
            return;
        }

        $document = ClubPolicyDocument::query()->findOrFail($data['club_policy_document_id']);
        $startsBeforeDocument = Carbon::parse($data['valid_from'])->lt($document->valid_from);
        $endsAfterDocument = $document->valid_until && (
            empty($data['valid_until']) || Carbon::parse($data['valid_until'])->gt($document->valid_until)
        );
        if ($startsBeforeDocument || $endsAfterDocument) {
            throw ValidationException::withMessages([
                'club_policy_document_id' => __('validation.contribution_rule_policy_period'),
            ]);
        }
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

    private function normalizeFamilyGroupKey(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '' || ! preg_match('/^[A-Za-z0-9._-]+$/', $value)) {
            return null;
        }

        return mb_strtolower($value);
    }

    private function normalizeIbanValue(?string $value): ?string
    {
        $value = strtoupper(preg_replace('/\s+/', '', trim((string) $value)));

        return $value === '' ? null : $value;
    }

    private function normalizeBicValue(?string $value): ?string
    {
        $value = strtoupper(preg_replace('/\s+/', '', trim((string) $value)));

        return $value === '' ? null : $value;
    }

    private function pendingExternalInvitationByToken(string $token): ClubExternalMember
    {
        $externalMember = ClubExternalMember::query()
            ->where('invitation_token', $token)
            ->where('invitation_status', 'pending')
            ->with('club:id,name')
            ->firstOrFail();

        if ($externalMember->invitationExpired()) {
            $externalMember->markInvitationExpired();
            abort(410, __('organization.club.invitation_expired'));
        }

        return $externalMember;
    }

    private function assertExternalInvitationRecipient(
        Request $request,
        ClubExternalMember $externalMember,
    ): void {
        abort_unless(
            filled($externalMember->email) &&
                strtolower((string) $externalMember->email) === strtolower((string) $request->user()->email),
            403
        );
    }

    private function externalInvitationPayload(ClubExternalMember $externalMember): array
    {
        return [
            'id' => $externalMember->id,
            'status' => $externalMember->invitation_status,
            'role' => $externalMember->role,
            'expires_at' => $externalMember->invitation_expires_at?->toJSON(),
            'expires_at_formatted' => LocalDateTime::format($externalMember->invitation_expires_at, $request->user(), $request),
            'expires_at_timezone' => LocalDateTime::timezoneFor($request->user(), $request),
            'club' => [
                'id' => $externalMember->club?->id,
                'name' => $externalMember->club?->name,
            ],
        ];
    }

    private function perPage(Request $request): int
    {
        return min(max((int) $request->integer('per_page', 20), 1), 50);
    }
}

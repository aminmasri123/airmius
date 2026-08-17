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
use App\Models\ClubContributionRule;
use App\Models\ClubExternalMember;
use App\Models\ClubFinanceEntry;
use App\Models\ClubMembershipRequest;
use App\Models\ClubMembershipType;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Team;
use App\Models\TeamJoinRequest;
use App\Models\User;
use App\Notifications\ExternalClubMembershipInvitation;
use App\Services\ClubMembershipLifecycleService;
use App\Services\ClubOnboardingService;
use App\Services\ClubProfilePayloadService;
use App\Services\ClubService;
use App\Services\MediaOptimizer;
use App\Services\PlanFeatureService;
use App\Support\AppNotification;
use App\Support\BillingOverview;
use App\Support\ClubAuditLog;
use App\Support\ClubMembershipApplication;
use App\Support\ClubPermissions;
use App\Support\ClubRoles;
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

        $this->clubService->delete($club);

        return response()->json([
            'message' => __('organization.club.deleted'),
        ]);
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
                    'storage_gb' => $club->subscriptionPlan()?->storage_gb,
                ],
                'management' => $this->managementPayload($request, $club, $canManageMembership),
            ],
        ]);
    }

    public function updateImages(Request $request, Club $club)
    {
        Gate::authorize('update', $club);

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
            'role' => ['nullable', Rule::in(ClubRoles::INVITABLE)],
            'member_number' => ['nullable', 'string', 'max:120'],
            'athlete_license_number' => ['nullable', 'string', 'max:120'],
            'membership_status' => ['nullable', Rule::in(['active', 'non_member', 'pending', 'paused', 'former'])],
            'family_group_key' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9._-]+$/'],
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

        $memberData = [
            'role' => ClubRoles::primary($roles),
            'roles' => $roles,
            'membership_status' => $data['membership_status'] ?? 'active',
            'family_group_key' => $this->normalizeFamilyGroupKey($data['family_group_key'] ?? null),
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

            AppNotification::sendLocalized(
                $existingUser,
                'club.member_linked',
                'organization.notifications.member_linked_title',
                'organization.notifications.member_linked_body',
                ['club' => $club->name],
                [
                    'url' => '/clubs/'.$club->id,
                    'mobile_url' => 'airmius://clubs/'.$club->id,
                    'deep_link' => 'airmius://clubs/'.$club->id,
                    'club_id' => $club->id,
                ],
            );

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
                    'role' => $memberData['role'],
                    'membership_status' => $memberData['membership_status'],
                    'family_group_key' => $memberData['family_group_key'],
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
            ->where('club_id', $club->id)
            ->with(['club', 'user'])
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

        ClubAuditLog::record($club, $request->user(), 'club.payment.recorded', $invoice, [
            'invoice_number' => $invoice->number,
            'amount' => $data['amount'] ?? $invoice->amount,
            'method' => $data['method'] ?? 'manual',
        ]);

        if ($invoice->user_id) {
            AppNotification::sendLocalized(
                (int) $invoice->user_id,
                'invoice.paid',
                'organization.notifications.payment_title',
                'organization.notifications.payment_body',
                ['invoice' => $invoice->number],
                [
                    'url' => '/settings',
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

        $payment->update($attributes);

        if ($payment->invoice_id && $payment->invoice) {
            $payment->invoice->update([
                'paid_at' => $attributes['paid_at'],
            ]);
        }

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

        $financeEntry->update($this->validatedFinanceEntryData($request));

        return response()->json([
            'message' => __('organization.club.finance_entry_updated'),
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
        $data = $request->validate([
            'review_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $membershipRequest = $this->membershipLifecycle->approve(
            $club,
            $membershipRequest,
            $request->user(),
            $data['review_note'] ?? null,
        );

        return new ClubMembershipRequestResource(
            $membershipRequest->fresh()->load(['club', 'user', 'membershipType'])
        );
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

    public function declineMembershipRequest(Request $request, Club $club, ClubMembershipRequest $membershipRequest)
    {
        $this->authorizeVisible($request, $club);
        abort_unless($this->canManageMembership($request, $club), 403);
        $data = $request->validate([
            'review_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $membershipRequest = $this->membershipLifecycle->decline(
            $club,
            $membershipRequest,
            $request->user(),
            $data['review_note'] ?? null,
        );

        return new ClubMembershipRequestResource(
            $membershipRequest->fresh()->load(['club', 'user', 'membershipType'])
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

        $membershipRequest = $this->membershipLifecycle->withdrawMembership(
            $club,
            $request->user(),
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
        abort_unless($this->canManageMembership($request, $club), 403);
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
        abort_unless($this->canManageFinance($request, $club), 403);

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
            'role' => ['nullable', Rule::in(ClubRoles::INVITABLE)],
            'membership_status' => ['required', Rule::in(['active', 'non_member', 'pending', 'paused', 'former'])],
            'family_group_key' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9._-]+$/'],
            'member_number' => ['nullable', 'string', 'max:80'],
            'athlete_license_number' => ['nullable', 'string', 'max:120'],
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
        $previousFamilyGroupKey = $this->normalizeFamilyGroupKey($externalMember->family_group_key);
        $nextFamilyGroupKey = $this->normalizeFamilyGroupKey($data['family_group_key'] ?? null);

        $externalMember->update([
            'name' => trim((string) ($data['name'] ?? '')) ?: null,
            'email' => strtolower(trim((string) $data['email'])),
            'role' => $data['role'] ?? $externalMember->role ?? 'member',
            'membership_status' => $data['membership_status'],
            'family_group_key' => $nextFamilyGroupKey,
            'member_number' => trim((string) ($data['member_number'] ?? '')) ?: null,
            'athlete_license_number' => trim((string) ($data['athlete_license_number'] ?? '')) ?: null,
            'contribution_amount' => $data['contribution_amount'] ?? null,
            'contribution_interval' => $data['contribution_interval'] ?? 'none',
            'contribution_next_invoice_on' => $data['contribution_next_invoice_on'] ?? null,
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

        foreach (array_unique(array_filter([$previousFamilyGroupKey, $nextFamilyGroupKey])) as $familyGroupKey) {
            $calculator->recalculateFamilyGroupContributions($club, $familyGroupKey);
        }

        return $this->membershipManagementResponse($request, $club, __('organization.club.external_member_updated'));
    }

    public function removeExternalMember(Request $request, Club $club, ClubExternalMember $externalMember)
    {
        $this->authorizeVisible($request, $club);
        abort_unless($this->canManageMembership($request, $club), 403);
        abort_unless((int) $externalMember->club_id === (int) $club->id, 404);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
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

    public function generateMemberNumber(Request $request, Club $club, User $user)
    {
        $this->authorizeVisible($request, $club);
        abort_unless($this->canManageMembership($request, $club), 403);
        app(WebClubMembershipController::class)->generateMemberNumber($club, $user);

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

    public function importBankTransactions(Request $request, Club $club)
    {
        $this->authorizeVisible($request, $club);
        abort_unless($this->canManageFinance($request, $club), 403);
        app(WebClubMembershipController::class)->importBankTransactions($request, $club);

        return $this->membershipManagementResponse($request, $club, 'Bankabgleich abgeschlossen.', 201);
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
        abort_unless($this->canManageFinance($request, $club), 403);

        return app(WebClubMembershipController::class)->exportDatev($request, $club);
    }

    private function membershipManagementResponse(
        Request $request,
        Club $club,
        string $message,
        int $status = 200,
    ) {
        return response()->json([
            'message' => $message,
            'data' => $this->managementPayload($request, $club->fresh(), true),
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
            ->where('status', 'paid')
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
        $expensePeriodTotal = (float) ($periodEntryTotals->get('expense', 0));

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
                ->load(['club', 'user', 'membershipType']);

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
            ->with(['club', 'invoice', 'user:id,name,email'])
            ->latest('id')
            ->limit(60)
            ->get();

        $bankTransactions = BankTransaction::query()
            ->where('club_id', $club->id)
            ->with(['invoice:id,number,title,amount,status', 'payment:id,amount,paid_at'])
            ->latest('id')
            ->limit(60)
            ->get();

        $financeEntries = ClubFinanceEntry::query()
            ->where('club_id', $club->id)
            ->with('user:id,name,email')
            ->orderByDesc('booked_on')
            ->latest('id')
            ->limit(80)
            ->get();

        $financeSummary = $this->financeBalanceSummary($club);
        $invoiceSummary = BillingOverview::clubInvoiceSummary(
            Invoice::query()
                ->where('club_id', $club->id)
                ->get(['id', 'amount', 'status'])
        );

        if (! (bool) ($effectivePermissions[ClubPermissions::FINANCE_VIEW] ?? false)) {
            $invoices = collect();
            $payments = collect();
            $bankTransactions = collect();
            $financeEntries = collect();
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
                'membership_requests_enabled' => $club->membership_requests_enabled,
                'member_pause_requests_enabled' => $club->member_pause_requests_enabled,
                'membership_application_fields' => ClubMembershipApplication::fieldsForClub($club->membership_application_fields),
                'membership_payment_methods' => ClubMembershipApplication::normalizePaymentMethods($club->membership_payment_methods),
                'membership_payment_method_options' => ClubMembershipApplication::paymentMethods(),
                'membership_application_documents' => ClubMembershipApplication::normalizeDocuments($club->membership_application_documents, $club->membership_application_document_types),
                'membership_application_document_types' => ClubMembershipApplication::documentTypes($club->membership_application_document_types),
            ],
            'members' => ClubMemberResource::collection($club->users)->resolve($request),
            'external_members' => $club->externalMembers->map(fn (ClubExternalMember $externalMember) => [
                'id' => $externalMember->id,
                'name' => $externalMember->name,
                'email' => $externalMember->email,
                'role' => $externalMember->role,
                'membership_status' => $externalMember->membership_status,
                'family_group_key' => $externalMember->family_group_key,
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
                'membership_ended_at' => $externalMember->membership_ended_at?->toJSON(),
                'membership_notes' => $externalMember->membership_notes,
                'invitation_status' => $externalMember->invitation_status,
                'invitation_token' => $externalMember->invitation_token,
                'invitation_url' => $externalMember->invitationUrl(),
                'invited_at' => $externalMember->invited_at?->toJSON(),
                'invitation_expires_at' => $externalMember->invitation_expires_at?->toJSON(),
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
                'application_fields' => $type->application_fields,
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
                'factor_key' => $rule->factor_key ?: 'standard',
                'factor_label' => ClubContributionRule::RULE_TYPE_LABELS[$rule->factor_key ?: 'standard'] ?? 'Standardbeitrag',
                'factor_operator' => $rule->factor_operator,
                'factor_operator_label' => $rule->factor_operator ? (ClubContributionRule::DISCOUNT_OPERATOR_LABELS[$rule->factor_operator] ?? $rule->factor_operator) : null,
                'factor_value' => $rule->factor_value,
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
                'active_members_count' => $club->users->filter(fn (User $member) => ($member->pivot?->membership_status ?? 'active') === 'active')->count()
                    + $club->externalMembers->where('membership_status', 'active')->count(),
                'linked_people_count' => $club->users->count() + $club->externalMembers->count(),
                'pending_membership_requests_count' => $membershipRequests->count(),
                'pending_team_join_requests_count' => $pendingTeamJoinRequests->count(),
                'open_invoice_amount' => $invoiceSummary['open_amount'],
                'open_invoices_count' => $invoiceSummary['open_count'],
                'sepa_ready_members_count' => $club->users->filter(fn (User $member) => (bool) ($member->pivot?->sepa_mandate_active ?? false))->count()
                    + $club->externalMembers->where('sepa_mandate_active', true)->count(),
                'recurring_contribution_total' => (float) $club->users->sum(fn (User $member) => (float) ($member->pivot?->contribution_amount ?? 0))
                    + (float) $club->externalMembers->sum(fn (ClubExternalMember $member) => (float) ($member->contribution_amount ?? 0)),
                ...$financeSummary,
            ],
        ];
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
            'name' => ['required', 'string', 'max:255'],
            'valid_from' => ['required', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'billing_interval' => ['required', Rule::in(self::CONTRIBUTION_INTERVALS)],
            'amount' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'age_min' => ['nullable', 'integer', 'min:0', 'max:120'],
            'age_max' => ['nullable', 'integer', 'min:0', 'max:120'],
            'factor_key' => ['nullable', Rule::in(ClubContributionRule::RULE_TYPES)],
            'factor_operator' => ['nullable', Rule::in(ClubContributionRule::DISCOUNT_OPERATORS)],
            'factor_value' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $ruleType = $data['factor_key'] ?? 'standard';

        if ($ruleType === 'discount') {
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
                    'factor_value' => 'Prozentuale Rabatte dürfen höchstens 100 Prozent betragen.',
                ]);
            }
        } else {
            $data['factor_operator'] = null;
            $data['factor_value'] = null;
        }

        if ($ruleType === 'special' && ($data['billing_interval'] ?? null) !== 'once') {
            throw ValidationException::withMessages([
                'billing_interval' => 'Sonderbeiträge müssen einmalig abgerechnet werden.',
            ]);
        }

        return [
            ...$data,
            'factor_key' => $ruleType,
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

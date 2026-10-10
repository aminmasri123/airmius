<?php

namespace App\Http\Controllers;

use App\Models\BankTransaction;
use App\Models\Club;
use App\Models\ClubContributionRule;
use App\Models\ClubExternalMember;
use App\Models\ClubFinanceEntry;
use App\Models\ClubMembershipRequest;
use App\Models\ClubMembershipType;
use App\Models\ClubMemberTimelineEntry;
use App\Models\ClubPolicyDocument;
use App\Models\ClubProcurementReceipt;
use App\Models\ClubSepaBatch;
use App\Models\ClubSepaFeeCorrection;
use App\Models\ClubSepaFeeRecharge;
use App\Models\ClubSepaSettlement;
use App\Models\ClubYearPeriod;
use App\Models\Folder;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Team;
use App\Models\TeamFee;
use App\Models\TeamJoinRequest;
use App\Models\User;
use App\Notifications\ClubInvoiceCreated;
use App\Notifications\ExternalClubMembershipInvitation;
use App\Services\ClubContributionCalculator;
use App\Services\ClubDonationDonorService;
use App\Services\ClubExternalMemberMergeService;
use App\Services\ClubFinanceBalanceService;
use App\Services\ClubFinanceScopeService;
use App\Services\ClubInvoiceCancellationService;
use App\Services\ClubInvoicePaymentService;
use App\Services\ClubMembershipLifecycleService;
use App\Services\ClubMetadataSubjectService;
use App\Services\ClubMoneyAccountService;
use App\Services\ClubNumberRangeService;
use App\Services\ClubSepaFeeService;
use App\Services\ClubService;
use App\Services\FileService;
use App\Services\PlanFeatureService;
use App\Support\AppNotification;
use App\Support\BillingOverview;
use App\Support\ClubAuditLog;
use App\Support\ClubFinanceWorkspaceReadiness;
use App\Support\ClubMemberDuplicates;
use App\Support\ClubMembershipApplication;
use App\Support\ClubMembershipInput;
use App\Support\ClubPermissions;
use App\Support\ClubRoleLifecycle;
use App\Support\ClubRoles;
use App\Support\LocalDateTime;
use App\Support\Roles;
use App\Support\TransactionalMail;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ClubMembershipController extends Controller
{
    use AuthorizesRequests;

    public const MEMBERSHIP_STATUSES = ['active', 'non_member', 'pending', 'paused', 'former'];

    public const CONTRIBUTION_INTERVALS = ['none', 'monthly', 'quarterly', 'four_monthly', 'semi_yearly', 'yearly', 'once'];

    private const IMPORT_FIELDS = [
        'name',
        'email',
        'family_group_key',
        'membership_status',
        'member_number',
        'athlete_license_number',
        'athlete_license_valid_until',
        'contribution_amount',
        'contribution_interval',
        'contribution_next_invoice_on',
        'sepa_iban',
        'sepa_bic',
        'sepa_mandate_reference',
        'sepa_mandate_signed_on',
        'sepa_mandate_active',
        'joined_on',
        'membership_ends_on',
        'membership_notes',
    ];

    public function __construct(
        private PlanFeatureService $planFeatures,
        private ClubService $clubService,
        private FileService $fileService,
        private ClubMembershipLifecycleService $membershipLifecycle,
        private ClubMetadataSubjectService $metadataSubjects,
        private ClubNumberRangeService $numberRanges,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();

        $hasFullClubAccess = $user->hasAnyRole(Roles::FULL_ACCESS);

        $clubs = Club::query()
            ->when(! $hasFullClubAccess, function ($query) use ($user) {
                $query->where(function ($clubQuery) use ($user): void {
                    $clubQuery->where('owner_id', $user->id)
                        ->orWhereHas('users', fn ($members) => $members->where('users.id', $user->id));
                });
            })
            ->with([
                'users' => fn ($query) => $query
                    ->select('users.id', 'name', 'email', 'phone', 'country', 'street', 'house_number', 'postal_code', 'city', 'athlete_license_number', 'athlete_license_valid_until', 'profile_photo_path')
                    ->withCount(['invoices', 'payments'])
                    ->orderBy('name'),
                'teams' => fn ($query) => $query
                    ->select('id', 'club_id', 'club_department_id', 'name')
                    ->with([
                        'joinRequests' => fn ($requestQuery) => $requestQuery
                            ->where('status', 'pending')
                            ->with('user:id,name,email,profile_photo_path'),
                    ])
                    ->orderBy('name'),
                'invoices' => fn ($query) => $query
                    ->withSum('settledPayments', 'amount')
                    ->with(['user:id,name,email', 'membershipUser:id,name,email', 'externalMember:id,name,email', 'businessYearPeriod', 'contributionYearPeriod', 'paymentHistory'])
                    ->latest('id')
                    ->limit(60),
                'payments' => fn ($query) => $query
                    ->with(['user:id,name,email', 'externalMember:id,name,email', 'invoice.paymentHistory', 'invoice.membershipUser', 'invoice.externalMember'])
                    ->latest('id')
                    ->limit(60),
                'financeEntries' => fn ($query) => $query
                    ->with(['user:id,name,email', 'businessYearPeriod'])
                    ->orderByDesc('booked_on')
                    ->latest('id')
                    ->limit(80),
                'bankTransactions' => fn ($query) => $query
                    ->with(['invoice:id,number,title,amount,status', 'payment:id,amount,paid_at', 'businessYearPeriod'])
                    ->latest('id')
                    ->limit(60),
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
                'membershipRequests' => fn ($query) => $query
                    ->whereIn('status', ['pending', 'information_requested', 'waitlisted'])
                    ->with(['user:id,name,email,profile_photo_path', 'membershipType:id,name', 'department:id,name'])
                    ->latest('id'),
            ])
            ->withCount(['users', 'teams'])
            ->orderBy('name')
            ->get()
            ->filter(fn (Club $club) => $hasFullClubAccess || $this->canOpenMembershipWorkspace($club, $user))
            ->map(function (Club $club) use ($user) {
                $pendingRequests = $club->teams
                    ->flatMap(fn (Team $team) => $team->joinRequests->map(fn (TeamJoinRequest $joinRequest) => [
                        'id' => $joinRequest->id,
                        'team' => [
                            'id' => $team->id,
                            'name' => $team->name,
                        ],
                        'user' => $joinRequest->user,
                        'created_at' => $joinRequest->created_at,
                    ]))
                    ->values();

                $financeSummary = app(ClubFinanceBalanceService::class)->summary($club);
                $invoiceSummary = BillingOverview::clubInvoiceSummary(
                    Invoice::query()
                        ->where('club_id', $club->id)
                        ->get(['id', 'amount', 'status'])
                );

                return [
                    'id' => $club->id,
                    'owner_id' => $club->owner_id,
                    'name' => $club->name,
                    'can_manage_access' => ClubPermissions::editableBy($club, $user),
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
                    'member_pause_max_months' => (int) ($club->member_pause_max_months ?: 1),
                    'membership_application_fields' => ClubMembershipApplication::fieldsForClub($club->membership_application_fields),
                    'membership_payment_methods' => ClubMembershipApplication::normalizePaymentMethods($club->membership_payment_methods),
                    'membership_payment_method_options' => ClubMembershipApplication::paymentMethods(),
                    'membership_application_documents' => ClubMembershipApplication::normalizeDocuments($club->membership_application_documents, $club->membership_application_document_types),
                    'membership_application_document_types' => ClubMembershipApplication::documentTypes($club->membership_application_document_types),
                    'users_count' => $club->users_count,
                    'teams_count' => $club->teams_count,
                    'subscription' => [
                        'plan' => $club->subscriptionPlan(),
                        'member_usage' => $club->memberUsageCount(),
                        'member_limit' => $club->subscriptionPlan()?->member_limit,
                        'team_limit' => $club->subscriptionPlan()?->team_limit,
                        'storage_bytes' => (int) $club->files()->sum('size'),
                        'storage_gb' => $club->subscriptionPlan()?->storage_gb,
                    ],
                    'capabilities' => $this->planFeatures->capabilities($club),
                    ...$financeSummary,
                    'invoice_summary' => $invoiceSummary,
                    'pending_requests' => $pendingRequests,
                    'teams' => $club->teams->map(fn (Team $team) => $team->only([
                        'id', 'club_id', 'club_department_id', 'name',
                    ]))->values(),
                    'members' => $club->users->map(fn (User $member) => [
                        'id' => $member->id,
                        'name' => $member->name,
                        'email' => $member->email,
                        'athlete_license_number' => $member->athlete_license_number,
                        'athlete_license_valid_until' => $member->athlete_license_valid_until?->toDateString(),
                        'profile_photo_url' => $member->profile_photo_url,
                        'profile_photo_thumb' => $member->profile_photo_thumb,
                        'pivot' => $member->pivot,
                        'invoices_count' => $member->invoices_count,
                        'payments_count' => $member->payments_count,
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
                    'contribution_policy_documents' => $club->policyDocuments->map(fn (ClubPolicyDocument $document) => [
                        'id' => $document->id,
                        'title' => $document->title,
                        'version_label' => $document->version_label,
                        'valid_from' => $document->valid_from?->toDateString(),
                        'valid_until' => $document->valid_until?->toDateString(),
                    ])->values(),
                    'club_requests' => $club->membershipRequests->map(fn (ClubMembershipRequest $request) => [
                        'id' => $request->id,
                        'type' => $request->type,
                        'status' => $request->status,
                        'message' => $request->message,
                        'information_request_message' => $request->information_request_message,
                        'information_requested_at' => $request->information_requested_at?->toJSON(),
                        'applicant_response_message' => $request->applicant_response_message,
                        'applicant_responded_at' => $request->applicant_responded_at?->toJSON(),
                        'waitlisted_at' => $request->waitlisted_at?->toJSON(),
                        'review_note' => $request->review_note,
                        'application_data' => $request->application_data ?: [],
                        'accepted_documents' => $request->accepted_documents ?: [],
                        'preferred_payment_method' => $request->preferred_payment_method,
                        'requested_billing_interval' => $request->requested_billing_interval,
                        'requested_pause_from' => $request->requested_pause_from?->toDateString(),
                        'requested_pause_until' => $request->requested_pause_until?->toDateString(),
                        'preview_amount' => $request->preview_amount,
                        'preview_interval' => $request->preview_interval,
                        'created_at' => $request->created_at,
                        'membership_type' => $request->membershipType,
                        'user' => $request->user,
                    ])->values(),
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
                        'contribution_next_invoice_on' => $externalMember->contribution_next_invoice_on,
                        'contribution_last_invoice_at' => $externalMember->contribution_last_invoice_at,
                        'sepa_iban' => $externalMember->sepa_iban,
                        'sepa_bic' => $externalMember->sepa_bic,
                        'sepa_mandate_reference' => $externalMember->sepa_mandate_reference,
                        'sepa_mandate_signed_on' => $externalMember->sepa_mandate_signed_on,
                        'sepa_mandate_active' => $externalMember->sepa_mandate_active,
                        'joined_on' => $externalMember->joined_on,
                        'membership_ends_on' => $externalMember->membership_ends_on,
                        'membership_end_notified_at' => $externalMember->membership_end_notified_at,
                        'membership_notes' => $externalMember->membership_notes,
                        'invitation_status' => $externalMember->invitation_status,
                        'invitation_token' => $externalMember->invitation_token,
                        'invitation_url' => $externalMember->invitationUrl(),
                        'invited_at' => $externalMember->invited_at,
                        'invitation_expires_at' => $externalMember->invitation_expires_at,
                        'invitation_expires_at_formatted' => LocalDateTime::format($externalMember->invitation_expires_at, $request->user(), $request),
                        'invitation_expires_at_timezone' => LocalDateTime::timezoneFor($request->user(), $request),
                        'linked_at' => $externalMember->linked_at,
                        'linked_user' => $externalMember->linkedUser,
                        'duplicate_candidate' => ClubMemberDuplicates::candidate($club, $externalMember),
                    ])->values(),
                    'member_timeline_entries' => $club->memberTimelineEntries
                        ->map(fn ($entry) => $entry->payload())
                        ->values(),
                    'invoices' => $club->invoices
                        ->map(fn (Invoice $invoice) => $this->invoicePayload($invoice))
                        ->values(),
                    'payments' => $club->payments->map(fn (Payment $payment) => [
                        ...$payment->toArray(),
                        'invoice' => $payment->invoice ? $this->invoicePayload($payment->invoice) : null,
                    ])->values(),
                    'donor_options' => ClubPermissions::allows($club, $user, ClubPermissions::FINANCE_VIEW)
                        ? app(ClubDonationDonorService::class)->options($club) : [],
                    'audit_logs' => ClubAuditLog::forClub($club),
                    'finance_entries' => $club->financeEntries->map(fn (ClubFinanceEntry $entry) => [
                        'club_money_account_id' => $entry->club_money_account_id,
                        'entry_kind' => $entry->entry_kind,
                        'team_id' => $entry->team_id,
                        'club_budget_id' => $entry->club_budget_id,
                        'club_department_id' => $entry->club_department_id,
                        'club_project_id' => $entry->club_project_id,
                        'club_cost_center_id' => $entry->club_cost_center_id,
                        'id' => $entry->id,
                        'club_id' => $entry->club_id,
                        'user_id' => $entry->user_id,
                        'type' => $entry->type,
                        'account' => $entry->account,
                        'category' => $entry->category,
                        'title' => $entry->title,
                        'amount' => $entry->amount,
                        'booked_on' => $entry->booked_on?->toDateString(),
                        'reference' => $entry->reference,
                        'description' => $entry->description,
                        'business_year_period_id' => $entry->business_year_period_id,
                        'business_year_period' => $this->yearPeriodReference($entry->businessYearPeriod),
                        'user' => $entry->user ? [
                            'id' => $entry->user->id,
                            'name' => $entry->user->name,
                            'email' => $entry->user->email,
                        ] : null,
                        'created_at' => $entry->created_at,
                        'updated_at' => $entry->updated_at,
                    ])->values(),
                    'bank_transactions' => $club->bankTransactions,
                ];
            });

        abort_if($clubs->isEmpty(), 403);

        return Inertia::render('Auth/Dashboard/ClubMemberships/Index', [
            'clubs' => $clubs,
            'membershipStatuses' => self::MEMBERSHIP_STATUSES,
            'contributionIntervals' => self::CONTRIBUTION_INTERVALS,
            'contributionRuleTypes' => ClubContributionRule::ruleTypeOptions(),
            'contributionDiscountOperators' => ClubContributionRule::discountOperatorOptions(),
            'contributionProrationPolicies' => ClubContributionRule::prorationPolicyOptions(),
            'invoiceStatusOptions' => Invoice::statusOptions(),
            'clubRoles' => ClubRoles::options(ClubRoles::INVITABLE),
            'teamRoles' => Team::ROLES,
        ]);
    }

    public static function userCanView(User $user): bool
    {
        return ClubPermissions::allowsAnyClub($user, [
            ClubPermissions::MEMBERS_MANAGE,
            ClubPermissions::FINANCE_VIEW,
            ClubPermissions::MEMBERS_ROLES,
        ]);
    }

    private function canOpenMembershipWorkspace(Club $club, User $user): bool
    {
        return ClubPermissions::allows($club, $user, ClubPermissions::MEMBERS_MANAGE)
            || ClubPermissions::allows($club, $user, ClubPermissions::FINANCE_VIEW)
            || ClubPermissions::allows($club, $user, ClubPermissions::MEMBERS_ROLES);
    }

    private function validatedFinanceEntryData(Request $request, Club $club): array
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
            'receipt_file_id' => [
                'nullable',
                Rule::exists('files', 'id')->where(fn ($query) => $query->where('club_id', $club->id)),
            ],
        ]);

        return [
            ...$data,
            ...app(ClubFinanceScopeService::class)->validate($request, $club),
            'category' => filled($data['category'] ?? null) ? trim($data['category']) : null,
            'title' => trim($data['title']),
            'booked_on' => $data['booked_on'] ?? now()->toDateString(),
            'reference' => filled($data['reference'] ?? null) ? trim($data['reference']) : null,
            'description' => filled($data['description'] ?? null) ? trim($data['description']) : null,
            'receipt_file_id' => $data['receipt_file_id'] ?? null,
        ];
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

    public function updateMember(Request $request, Club $club, User $user)
    {
        $submittedFields = array_keys($request->all());
        if (array_intersect(['role', 'roles'], $submittedFields) !== []) {
            abort_unless(ClubPermissions::allows($club, $request->user(), ClubPermissions::MEMBERS_ROLES), 403);
        }
        if (array_diff($submittedFields, ['role', 'roles']) !== []) {
            abort_unless(ClubPermissions::allows($club, $request->user(), ClubPermissions::MEMBERS_EDIT), 403);
        }
        abort_unless($submittedFields !== [], 403);

        abort_unless($club->users()->where('users.id', $user->id)->exists(), 404);

        $data = $request->validate([
            'role' => ['nullable', Rule::in(ClubController::MEMBER_ROLES)],
            'roles' => ['nullable', 'array'],
            'roles.*' => [Rule::in(ClubController::MEMBER_ROLES)],
            'membership_status' => ['required', Rule::in(self::MEMBERSHIP_STATUSES)],
            'club_membership_type_id' => ['nullable', Rule::exists('club_membership_types', 'id')->where('club_id', $club->id)],
            'family_group_key' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9._-]+$/'],
            'contribution_payer_user_id' => [
                'nullable',
                'integer',
                Rule::exists('club_user', 'user_id')->where('club_id', $club->id),
            ],
            'member_number' => ['nullable', 'string', 'max:80'],
            'athlete_license_number' => ['nullable', 'string', 'max:120'],
            'athlete_license_valid_until' => ['nullable', 'date'],
            'country' => ['nullable', 'string', 'size:2'],
            'street' => ['nullable', 'string', 'max:255'],
            'house_number' => ['nullable', 'string', 'max:40'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:255'],
            'contribution_amount' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'contribution_interval' => ['nullable', Rule::in(self::CONTRIBUTION_INTERVALS)],
            'payment_method' => ['nullable', Rule::in(collect(ClubMembershipApplication::paymentMethods())->pluck('value')->all())],
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

        $currentMembership = $club->users()
            ->where('users.id', $user->id)
            ->first()?->pivot;
        $auditBefore = $this->memberAuditSnapshot($currentMembership, $user);
        $previousFamilyGroupKey = $this->normalizeFamilyGroupKey($currentMembership?->family_group_key);
        $nextFamilyGroupKey = $this->normalizeFamilyGroupKey($data['family_group_key'] ?? null);
        $contributionPayerUserId = filled($data['contribution_payer_user_id'] ?? null)
            && (int) $data['contribution_payer_user_id'] !== (int) $user->id
                ? (int) $data['contribution_payer_user_id']
                : null;

        $previousRole = $currentMembership ? ClubRoles::primary(ClubRoles::normalize($currentMembership->role, $currentMembership->roles ?? [])) : null;

        $roles = ClubRoles::normalize($data['role'] ?? null, $data['roles'] ?? []);
        $primaryRole = ClubRoles::primary($roles);

        abort_if(
            $club->owner_id === $user->id && ! in_array('owner', $roles, true),
            422,
            'Der Owner kann hier nicht herabgestuft werden.'
        );

        $duplicateNumber = filled($data['member_number'] ?? null)
            && DB::table('club_user')
                ->where('club_id', $club->id)
                ->where('member_number', $data['member_number'])
                ->where('user_id', '!=', $user->id)
                ->exists();

        abort_if($duplicateNumber, 422, __('organization.club.member_number_duplicate'));

        DB::transaction(function () use ($club, $user, $data, $roles, $primaryRole, $contributionPayerUserId) {
            $previousOwner = null;

            if (in_array('owner', $roles, true)) {
                $previousOwnerId = $club->owner_id;
                $club->forceFill(['owner_id' => $user->id])->save();

                if ($previousOwnerId && $previousOwnerId !== $user->id) {
                    $club->users()->updateExistingPivot($previousOwnerId, [
                        'role' => 'admin',
                        'roles' => ['admin'],
                    ]);
                    $previousOwner = User::find($previousOwnerId);
                }
            }

            $club->users()->updateExistingPivot($user->id, [
                'role' => $primaryRole,
                'roles' => $roles,
                'membership_status' => $data['membership_status'],
                'family_group_key' => $this->normalizeFamilyGroupKey($data['family_group_key'] ?? null),
                'contribution_payer_user_id' => $contributionPayerUserId,
                'club_membership_type_id' => $data['club_membership_type_id'] ?? null,
                'member_number' => $data['member_number'] ?? null,
                'contribution_amount' => $data['contribution_amount'] ?? null,
                'contribution_interval' => $data['contribution_interval'] ?? 'none',
                'payment_method' => $data['payment_method'] ?? null,
                'contribution_next_invoice_on' => $this->normalizedNextInvoiceDate($data),
                'contribution_last_invoice_at' => null,
                'sepa_iban' => $this->normalizeIban($data['sepa_iban'] ?? null),
                'sepa_bic' => $this->normalizeBic($data['sepa_bic'] ?? null),
                'sepa_mandate_reference' => $data['sepa_mandate_reference'] ?? null,
                'sepa_mandate_signed_on' => $data['sepa_mandate_signed_on'] ?? null,
                'sepa_mandate_active' => (bool) ($data['sepa_mandate_active'] ?? false),
                'joined_on' => $data['joined_on'] ?? null,
                'membership_ends_on' => $data['membership_ends_on'] ?? null,
                'membership_end_notified_at' => null,
                'membership_ended_at' => null,
                'membership_notes' => $data['membership_notes'] ?? null,
            ]);

            if (in_array('owner', $roles, true)) {
                $this->clubService->assignClubOwnerRole($user);

                if ($previousOwner) {
                    $this->clubService->refreshClubOwnerRole($previousOwner);
                }
            }
        });

        if ($previousRole !== $primaryRole) {
            $previousRoleLabel = ClubRoles::LABELS[$previousRole] ?? $previousRole;
            $newRoleLabel = ClubRoles::LABELS[$primaryRole] ?? $primaryRole;

            AppNotification::sendLocalized(
                $user,
                'club.member.role_updated',
                'organization.notifications.club_role_title',
                'organization.notifications.club_role_body',
                [
                    'club' => $club->name,
                    'previous' => AppNotification::translatedReplacement(
                        $previousRole ? 'organization.roles.club.'.$previousRole : 'organization.roles.unknown',
                        $previousRoleLabel ?? 'Unbekannt',
                    ),
                    'next' => AppNotification::translatedReplacement(
                        'organization.roles.club.'.$primaryRole,
                        $newRoleLabel,
                    ),
                ],
                [
                    'url' => '/notifications',
                    'mobile_url' => 'airmius://clubs/'.$club->id.'/members',
                    'club_id' => $club->id,
                    'previous_role' => $previousRole,
                    'role' => $primaryRole,
                    'roles' => $roles,
                ],
            );
        }

        foreach (array_unique(array_filter([$previousFamilyGroupKey, $nextFamilyGroupKey])) as $familyGroupKey) {
            $this->recalculateFamilyGroupContributions($club, $familyGroupKey);
        }

        $profileUpdates = [
            'athlete_license_number' => $data['athlete_license_number'] ?? null,
            'athlete_license_valid_until' => $data['athlete_license_valid_until'] ?? null,
        ];
        foreach (['country', 'street', 'house_number', 'postal_code', 'city'] as $addressField) {
            if (! $request->exists($addressField)) {
                continue;
            }

            $value = filled($data[$addressField] ?? null) ? trim($data[$addressField]) : null;
            $profileUpdates[$addressField] = $addressField === 'country' && $value !== null
                ? strtoupper($value)
                : $value;
        }
        $user->forceFill($profileUpdates)->save();

        $updatedMembership = $club->users()
            ->where('users.id', $user->id)
            ->first()?->pivot;
        $auditAfter = $this->memberAuditSnapshot($updatedMembership, $user->fresh());
        $changes = [];

        foreach ($auditAfter as $field => $value) {
            $previous = $auditBefore[$field] ?? null;
            if ($previous === $value) {
                continue;
            }

            $changes[$field] = $field === 'membership_notes'
                ? ['from' => filled($previous), 'to' => filled($value)]
                : ['from' => $previous, 'to' => $value];
        }

        if ($changes !== []) {
            ClubAuditLog::record($club, $request->user(), 'club.member.updated', $user, [
                'user_id' => $user->id,
                'changed_fields' => array_keys($changes),
                'changes' => $changes,
            ]);
        }

        $this->recordMembershipTimelineChanges(
            $club,
            'member',
            $user->id,
            $request->user(),
            $auditBefore,
            $auditAfter,
        );

        return back()->with('success', __('organization.club.member_data_updated'));
    }

    private function recordMembershipTimelineChanges(
        Club $club,
        string $subjectType,
        int $subjectId,
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
            $from = $before[$field] ?? null;
            $to = $after[$field] ?? null;
            if ($from === $to) {
                continue;
            }

            ClubMemberTimelineEntry::query()->create([
                'club_id' => $club->id,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'type' => $definition['type'],
                'title' => $definition['title'],
                'occurred_on' => now()->toDateString(),
                'from_value' => $from,
                'to_value' => $to,
                'created_by' => $actor->id,
            ]);
        }
    }

    private function memberAuditSnapshot($membership, User $user): array
    {
        $date = static fn ($value) => $value instanceof \DateTimeInterface
            ? $value->format('Y-m-d')
            : (filled($value) ? substr((string) $value, 0, 10) : null);

        return [
            'membership_status' => $membership?->membership_status,
            'family_group_key' => $membership?->family_group_key,
            'contribution_payer_user_id' => $membership?->contribution_payer_user_id ? (int) $membership->contribution_payer_user_id : null,
            'member_number' => $membership?->member_number,
            'athlete_license_number' => $user->athlete_license_number,
            'athlete_license_valid_until' => $date($user->athlete_license_valid_until),
            'contribution_amount' => filled($membership?->contribution_amount)
                ? number_format((float) $membership->contribution_amount, 2, '.', '')
                : null,
            'contribution_interval' => $membership?->contribution_interval,
            'contribution_next_invoice_on' => $date($membership?->contribution_next_invoice_on),
            'joined_on' => $date($membership?->joined_on),
            'membership_ends_on' => $date($membership?->membership_ends_on),
            'membership_notes' => $membership?->membership_notes,
        ];
    }

    public function removeMember(Request $request, Club $club, User $user)
    {
        abort_unless(ClubPermissions::allows($club, $request->user(), ClubPermissions::MEMBERS_DELETE), 403);

        abort_unless($club->users()->where('users.id', $user->id)->exists(), 404);
        abort_if($club->owner_id === $user->id, 422, __('organization.club.owner_remove_forbidden'));

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

        DB::transaction(function () use ($club, $user, $request) {
            $teamIds = $club->teams()->pluck('id');

            DB::table('team_user')
                ->whereIn('team_id', $teamIds)
                ->where('user_id', $user->id)
                ->delete();

            $this->metadataSubjects->clear($club, 'member', $user->id);
            app(ClubRoleLifecycle::class)->clearForMembership($club, $user, $request->user());
            $club->users()->detach($user->id);
            $this->clubService->refreshClubOwnerRole($user);
        });

        ClubAuditLog::record($club, $request->user(), 'club.member.removed', $user, [
            'user_id' => $user->id,
            'member_name' => $user->name,
            'reason' => $reason,
        ]);

        AppNotification::sendLocalized(
            $user,
            'club.member_removed',
            'organization.notifications.club_member_removed_title',
            'organization.notifications.club_member_removed_body',
            ['club' => $club->name, 'reason' => $reason],
            [
                'url' => route('auth.notifications.index'),
                'mobile_url' => 'airmius://notifications',
                'club_id' => $club->id,
                'reason' => $reason,
            ],
        );

        return back()->with('success', __('organization.club.member_removed'));
    }

    public function leaveClub(Request $request, Club $club)
    {
        $user = $request->user();

        abort_unless($club->users()->where('users.id', $user->id)->exists(), 404);
        abort_if($club->owner_id === $user->id, 422, __('organization.club.owner_leave_forbidden'));

        $hasOpenDebt = Invoice::query()
            ->where('club_id', $club->id)
            ->where('user_id', $user->id)
            ->whereIn('status', ['open', 'overdue'])
            ->exists();

        if ($hasOpenDebt) {
            throw ValidationException::withMessages([
                'club' => __('organization.club.open_invoices_before_leaving'),
            ]);
        }

        DB::transaction(function () use ($club, $user) {
            $teamIds = $club->teams()->pluck('id');

            DB::table('team_user')
                ->whereIn('team_id', $teamIds)
                ->where('user_id', $user->id)
                ->delete();

            $this->metadataSubjects->clear($club, 'member', $user->id);
            app(ClubRoleLifecycle::class)->clearForMembership($club, $user, $user);
            $club->users()->detach($user->id);
            $this->clubService->refreshClubOwnerRole($user);
        });

        $this->notifyClubManagersLocalized(
            $club,
            'club.member_left',
            'organization.notifications.club_member_left_title',
            'organization.notifications.club_member_left_body',
            ['user' => $user->name, 'club' => $club->name],
            [
                'url' => route('auth.club-memberships.index'),
                'club_id' => $club->id,
                'user_id' => $user->id,
            ],
            ClubPermissions::MEMBERS_MANAGE,
            $user->id,
        );

        return back()->with('success', __('organization.club.left'));
    }

    public function objectToRemoval(Request $request, Club $club)
    {
        $data = $request->validate([
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        $membershipRequest = ClubMembershipRequest::create([
            'club_id' => $club->id,
            'user_id' => $request->user()->id,
            'type' => 'removal_objection',
            'status' => 'pending',
            'message' => $data['message'] ?? 'Ich widerspreche der Entfernung und bitte um Prüfung.',
        ]);

        $this->notifyClubManagersLocalized(
            $club,
            'club.member_removal_objection',
            'organization.notifications.removal_objection_title',
            'organization.notifications.removal_objection_body',
            ['user' => $request->user()->name, 'club' => $club->name],
            [
                'url' => route('auth.club-memberships.index'),
                'club_id' => $club->id,
                'request_id' => $membershipRequest->id,
            ],
            ClubPermissions::MEMBERS_APPROVE,
            $request->user()->id,
        );

        return back()->with('success', __('organization.club.appeal_sent'));
    }

    public function updateMembershipSettings(Request $request, Club $club)
    {
        abort_unless(ClubPermissions::allows($club, $request->user(), ClubPermissions::MEMBERS_EDIT), 403);

        $configuredDocumentTypes = $request->has('membership_application_document_types')
            ? $request->input('membership_application_document_types')
            : $club->membership_application_document_types;
        $documentTypeValues = ClubMembershipApplication::documentTypeValues($configuredDocumentTypes);

        $data = $request->validate([
            'membership_requests_enabled' => ['boolean'],
            'member_pause_requests_enabled' => ['boolean'],
            'member_pause_max_months' => ['sometimes', 'integer', 'between:1,6'],
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
            'membership_application_documents.*.file' => ['nullable', 'file', 'max:51200'],
            'membership_application_documents.*.description' => ['nullable', 'string', 'max:1000'],
            'membership_application_documents.*.is_visible' => ['boolean'],
            'membership_application_documents.*.is_required' => ['boolean'],
        ]);

        $documents = $this->storeMembershipApplicationDocumentUploads(
            $request,
            $club,
            $data['membership_application_documents'] ?? []
        );

        $club->update([
            'membership_requests_enabled' => (bool) ($data['membership_requests_enabled'] ?? false),
            'member_pause_requests_enabled' => (bool) ($data['member_pause_requests_enabled'] ?? false),
            'member_pause_max_months' => $request->has('member_pause_max_months')
                ? (int) $data['member_pause_max_months']
                : $club->member_pause_max_months,
            'membership_application_fields' => ClubMembershipApplication::normalizeFieldModes($data['membership_application_fields'] ?? null),
            'membership_payment_methods' => ClubMembershipApplication::normalizePaymentMethods($data['membership_payment_methods'] ?? null),
            'membership_application_document_types' => ClubMembershipApplication::normalizeDocumentTypes($data['membership_application_document_types'] ?? $configuredDocumentTypes),
            'membership_application_documents' => ClubMembershipApplication::normalizeDocuments($documents, $data['membership_application_document_types'] ?? $configuredDocumentTypes),
        ]);

        return back()->with('success', __('organization.club.membership_settings_updated'));
    }

    public function storeMembershipType(Request $request, Club $club)
    {
        abort_unless(ClubPermissions::allows($club, $request->user(), ClubPermissions::MEMBERS_EDIT), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_public' => ['boolean'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'application_fields' => ['nullable', 'array'],
            'application_fields.*' => ['nullable', Rule::in(ClubMembershipApplication::FIELD_MODES)],
        ], [
            'name.required' => __('organization.club.membership_type_name_required'),
        ]);

        $club->membershipTypes()->create([
            ...$data,
            'is_public' => (bool) ($data['is_public'] ?? true),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'application_fields' => array_key_exists('application_fields', $data)
                ? ClubMembershipApplication::normalizeFieldModes($data['application_fields'])
                : null,
        ]);

        return back()->with('success', __('organization.club.membership_type_saved'));
    }

    public function updateMembershipType(Request $request, Club $club, ClubMembershipType $membershipType)
    {
        abort_unless(ClubPermissions::allows($club, $request->user(), ClubPermissions::MEMBERS_EDIT), 403);
        abort_unless($membershipType->club_id === $club->id, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_public' => ['boolean'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        $membershipType->update([
            ...$data,
            'is_public' => (bool) ($data['is_public'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? false),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);

        return back()->with('success', __('organization.club.membership_type_updated'));
    }

    public function storeContributionRule(Request $request, Club $club)
    {
        abort_unless(ClubPermissions::allows($club, $request->user(), ClubPermissions::FINANCE_EDIT), 403);

        $club->contributionRules()->create($this->validatedContributionRuleData($request, $club));

        return back()->with('success', __('organization.club.contribution_rule_saved'));
    }

    public function updateContributionRule(Request $request, Club $club, ClubContributionRule $contributionRule)
    {
        abort_unless(ClubPermissions::allows($club, $request->user(), ClubPermissions::FINANCE_EDIT), 403);
        abort_unless($contributionRule->club_id === $club->id, 404);

        $contributionRule->update($this->validatedContributionRuleData($request, $club));

        return back()->with('success', __('organization.club.contribution_rule_updated'));
    }

    public function storeMembershipRequest(Request $request, Club $club)
    {
        $data = $request->validate([
            'club_membership_type_id' => ['nullable', Rule::exists('club_membership_types', 'id')->where('club_id', $club->id)],
            'application_data' => ['nullable', 'array'],
            'accepted_documents' => ['nullable', 'array'],
            'accepted_documents.*' => ['boolean'],
            'preferred_payment_method' => ['nullable', 'string', 'max:100'],
            'requested_billing_interval' => ['nullable', Rule::in(self::CONTRIBUTION_INTERVALS)],
            'message' => ['nullable', 'string', 'max:2000'],
            'consent_version' => ['nullable', 'string', 'max:80'],
            'consent_signature' => ['nullable', 'string', 'max:255'],
        ]);

        $this->membershipLifecycle->submitMembership(
            $club,
            $request->user(),
            $data,
            $request->ip(),
            $request->userAgent(),
        );

        return back()->with('success', __('organization.club.request_sent'));
    }

    public function withdrawMembershipRequest(Request $request, Club $club)
    {
        $data = $request->validate([
            'type' => ['nullable', 'string', Rule::in(['membership', 'termination'])],
        ]);

        $this->membershipLifecycle->withdrawMembership($club, $request->user(), $data['type'] ?? 'membership');

        return back()->with('success', __('organization.club.request_withdrawn'));
    }

    public function storePauseRequest(Request $request, Club $club)
    {
        $data = $request->validate([
            'requested_pause_from' => ['required', 'date'],
            'requested_pause_until' => ['nullable', 'date', 'after_or_equal:requested_pause_from'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->membershipLifecycle->requestPause($club, $request->user(), $data);

        return back()->with('success', __('organization.club.pause_request_sent'));
    }

    public function storeMembershipChangeRequest(Request $request, Club $club)
    {
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
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->membershipLifecycle->requestMembershipChange($club, $request->user(), $data);

        return back()->with('success', __('organization.club.membership_change_request_sent'));
    }

    public function storeTerminationRequest(Request $request, Club $club)
    {
        $data = $request->validate([
            'requested_termination_on' => ['required', 'date', 'after_or_equal:today'],
            'termination_reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->membershipLifecycle->requestTermination($club, $request->user(), $data);

        return back()->with('success', __('organization.club.termination_request_sent'));
    }

    public function approveClubRequest(Request $request, ClubMembershipRequest $membershipRequest)
    {
        $club = $membershipRequest->club;
        abort_unless(ClubPermissions::allows($club, $request->user(), ClubPermissions::MEMBERS_APPROVE), 403);
        $data = $request->validate([
            'review_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->membershipLifecycle->approve(
            $club,
            $membershipRequest,
            $request->user(),
            $data['review_note'] ?? null,
        );

        return back()->with('success', __('organization.club.request_approved'));
    }

    public function declineClubRequest(Request $request, ClubMembershipRequest $membershipRequest)
    {
        $club = $membershipRequest->club;
        abort_unless(ClubPermissions::allows($club, $request->user(), ClubPermissions::MEMBERS_APPROVE), 403);
        $data = $request->validate([
            'review_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->membershipLifecycle->decline(
            $club,
            $membershipRequest,
            $request->user(),
            $data['review_note'] ?? null,
        );

        return back()->with('success', __('organization.club.request_declined'));
    }

    public function requestClubRequestInformation(Request $request, ClubMembershipRequest $membershipRequest)
    {
        $club = $membershipRequest->club;
        abort_unless(ClubPermissions::allows($club, $request->user(), ClubPermissions::MEMBERS_APPROVE), 403);
        $data = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $this->membershipLifecycle->requestInformation($club, $membershipRequest, $request->user(), $data['message']);

        return back()->with('success', __('organization.club.request_information_sent'));
    }

    public function respondToClubRequestInformation(Request $request, ClubMembershipRequest $membershipRequest)
    {
        $club = $membershipRequest->club;
        $data = $request->validate([
            'message' => ['nullable', 'string', 'max:2000'],
            'application_data' => ['nullable', 'array'],
            'accepted_documents' => ['nullable', 'array'],
            'accepted_documents.*' => ['boolean'],
        ]);

        $this->membershipLifecycle->respondToInformationRequest($club, $membershipRequest, $request->user(), $data);

        return back()->with('success', __('organization.club.request_information_provided'));
    }

    public function waitlistClubRequest(Request $request, ClubMembershipRequest $membershipRequest)
    {
        $club = $membershipRequest->club;
        abort_unless(ClubPermissions::allows($club, $request->user(), ClubPermissions::MEMBERS_APPROVE), 403);
        $data = $request->validate([
            'review_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->membershipLifecycle->waitlist($club, $membershipRequest, $request->user(), $data['review_note'] ?? null);

        return back()->with('success', __('organization.club.request_waitlisted'));
    }

    public function storeEmailMember(Request $request, Club $club)
    {
        abort_unless(ClubPermissions::allows($club, $request->user(), ClubPermissions::MEMBERS_EDIT), 403);

        if ($request->has('members')) {
            $data = $request->validate([
                'send_invitation' => ['boolean'],
                'invitation_expires_at' => ['nullable', 'date', 'after_or_equal:today'],
                'members' => ['required', 'array', 'min:1', 'max:50'],
                'members.*.name' => ['nullable', 'string', 'max:255'],
                'members.*.email' => ['required', 'email', 'max:255'],
                'members.*.phone' => ['nullable', 'string', 'max:40'],
                'members.*.country' => ['nullable', 'string', 'size:2'],
                'members.*.street' => ['nullable', 'string', 'max:255'],
                'members.*.house_number' => ['nullable', 'string', 'max:40'],
                'members.*.postal_code' => ['nullable', 'string', 'max:30'],
                'members.*.city' => ['nullable', 'string', 'max:255'],
                'members.*.role' => ['nullable', Rule::in(ClubRoles::INVITABLE)],
                'members.*.membership_status' => ['required', Rule::in(self::MEMBERSHIP_STATUSES)],
                'members.*.club_membership_type_id' => ['nullable', Rule::exists('club_membership_types', 'id')->where('club_id', $club->id)],
                'members.*.family_group_key' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9._-]+$/'],
                'members.*.contribution_payer_user_id' => [
                    'nullable',
                    'integer',
                    Rule::exists('club_user', 'user_id')->where('club_id', $club->id),
                ],
                'members.*.member_number' => ['nullable', 'string', 'max:80'],
                'members.*.athlete_license_number' => ['nullable', 'string', 'max:120'],
                'members.*.athlete_license_valid_until' => ['nullable', 'date'],
                'members.*.contribution_amount' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
                'members.*.contribution_interval' => ['nullable', Rule::in(self::CONTRIBUTION_INTERVALS)],
                'members.*.payment_method' => ['nullable', Rule::in(collect(ClubMembershipApplication::paymentMethods())->pluck('value')->all())],
                'members.*.contribution_next_invoice_on' => ['nullable', 'date'],
                'members.*.sepa_iban' => ['nullable', 'string', 'max:40'],
                'members.*.sepa_bic' => ['nullable', 'string', 'max:20'],
                'members.*.sepa_mandate_reference' => ['nullable', 'string', 'max:255'],
                'members.*.sepa_mandate_signed_on' => ['nullable', 'date'],
                'members.*.sepa_mandate_active' => ['boolean'],
                'members.*.membership_ends_on' => ['nullable', 'date'],
            ]);

            if ((bool) ($data['send_invitation'] ?? false)) {
                $this->planFeatures->ensureCanSendMemberInvitations($club, count($data['members']));
            }

            $this->planFeatures->ensureCanAddManualMembers($club, $this->newEmailMemberCount($club, $data['members']));

            $stats = ['stored' => 0, 'linked' => 0, 'invited' => 0];

            foreach ($data['members'] as $memberData) {
                $memberData['invitation_expires_at'] = $data['invitation_expires_at'] ?? null;
                $result = $this->storeEmailMemberData($club, $request->user(), $memberData, (bool) ($data['send_invitation'] ?? false));
                $stats[$result]++;
            }

            return back()->with(
                'success',
                "Mitglieder gespeichert: {$stats['stored']} extern, {$stats['linked']} verknüpft, {$stats['invited']} eingeladen."
            );
        }

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'country' => ['nullable', 'string', 'size:2'],
            'street' => ['nullable', 'string', 'max:255'],
            'house_number' => ['nullable', 'string', 'max:40'],
            'postal_code' => ['nullable', 'string', 'max:30'],
            'city' => ['nullable', 'string', 'max:255'],
            'send_invitation' => ['boolean'],
            'invitation_expires_at' => ['nullable', 'date', 'after_or_equal:today'],
            'role' => ['nullable', Rule::in(ClubRoles::INVITABLE)],
            'membership_status' => ['required', Rule::in(self::MEMBERSHIP_STATUSES)],
            'club_membership_type_id' => ['nullable', Rule::exists('club_membership_types', 'id')->where('club_id', $club->id)],
            'family_group_key' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9._-]+$/'],
            'contribution_payer_user_id' => [
                'nullable',
                'integer',
                Rule::exists('club_user', 'user_id')->where('club_id', $club->id),
            ],
            'member_number' => ['nullable', 'string', 'max:80'],
            'athlete_license_number' => ['nullable', 'string', 'max:120'],
            'athlete_license_valid_until' => ['nullable', 'date'],
            'contribution_amount' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'contribution_interval' => ['nullable', Rule::in(self::CONTRIBUTION_INTERVALS)],
            'payment_method' => ['nullable', Rule::in(collect(ClubMembershipApplication::paymentMethods())->pluck('value')->all())],
            'contribution_next_invoice_on' => ['nullable', 'date'],
            'sepa_iban' => ['nullable', 'string', 'max:40'],
            'sepa_bic' => ['nullable', 'string', 'max:20'],
            'sepa_mandate_reference' => ['nullable', 'string', 'max:255'],
            'sepa_mandate_signed_on' => ['nullable', 'date'],
            'sepa_mandate_active' => ['boolean'],
            'membership_ends_on' => ['nullable', 'date'],
        ]);

        if ((bool) ($data['send_invitation'] ?? false)) {
            $this->planFeatures->ensureCanSendMemberInvitations($club);
        }

        $this->planFeatures->ensureCanAddManualMembers($club, $this->newEmailMemberCount($club, [$data]));

        $result = $this->storeEmailMemberData($club, $request->user(), $data, (bool) ($data['send_invitation'] ?? false));

        return back()->with('success', match ($result) {
            'linked' => 'Bestehender User wurde direkt mit dem Verein verknüpft.',
            'invited' => 'Externes Mitglied gespeichert und Einladung versendet.',
            default => 'Externes Mitglied ohne Einladung gespeichert.',
        });
    }

    public function importEmailMembers(Request $request, Club $club)
    {
        abort_unless(ClubPermissions::allows($club, $request->user(), ClubPermissions::MEMBERS_EDIT), 403);
        $this->planFeatures->ensureAllows($club, 'member_import');

        $data = $request->validate([
            'file' => ['required', 'file', 'max:10240'],
            'send_invitation' => ['boolean'],
        ]);

        $rows = $this->readMembershipImportRows(
            $data['file']->getRealPath(),
            $data['file']->getClientOriginalExtension(),
            $this->importMappingFromRequest($request),
        );
        $sendInvitation = (bool) ($data['send_invitation'] ?? false);
        $stats = [
            'stored' => 0,
            'linked' => 0,
            'invited' => 0,
            'skipped' => 0,
        ];
        $seenEmails = [];
        $importErrors = [];
        $members = [];

        foreach ($rows as $index => $row) {
            $rowNumber = (int) ($row['__row_number'] ?? ($index + 1));
            $email = strtolower(trim((string) ($row['email'] ?? '')));

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $stats['skipped']++;
                $importErrors[] = [
                    'row' => $rowNumber,
                    'email' => $email,
                    'reason' => 'Ungültige oder fehlende E-Mail-Adresse.',
                ];

                continue;
            }

            if (isset($seenEmails[$email])) {
                $stats['skipped']++;
                $importErrors[] = [
                    'row' => $rowNumber,
                    'email' => $email,
                    'reason' => 'Doppelte E-Mail in der Importdatei; erster Eintrag wurde verwendet.',
                ];

                continue;
            }

            $seenEmails[$email] = true;
            $members[] = [
                'name' => trim((string) ($row['name'] ?? '')) ?: null,
                'email' => $email,
                'membership_status' => $this->normalizeMembershipStatus($row['mitgliedschaft'] ?? $row['membership_status'] ?? 'active'),
                'family_group_key' => $this->normalizeFamilyGroupKey($row['familiengruppe'] ?? $row['family_group_key'] ?? null),
                'member_number' => trim((string) ($row['mitgliedsnummer'] ?? $row['member_number'] ?? '')) ?: null,
                'athlete_license_number' => trim((string) ($row['lizenznummer'] ?? $row['athlete_license_number'] ?? '')) ?: null,
                'athlete_license_valid_until' => $this->normalizeImportDate($row['lizenz_gueltig_bis'] ?? $row['athlete_license_valid_until'] ?? null),
                'contribution_amount' => $this->normalizeMoney($row['beitrag'] ?? $row['contribution_amount'] ?? null),
                'contribution_interval' => $this->normalizeContributionInterval($row['intervall'] ?? $row['contribution_interval'] ?? 'none'),
                'contribution_next_invoice_on' => $this->normalizeImportDate($row['naechste_rechnung'] ?? $row['naechsten_rechnung'] ?? $row['nächste_rechnung'] ?? $row['contribution_next_invoice_on'] ?? null),
                'sepa_iban' => $this->normalizeIban($row['iban'] ?? $row['sepa_iban'] ?? null),
                'sepa_bic' => $this->normalizeBic($row['bic'] ?? $row['sepa_bic'] ?? null),
                'sepa_mandate_reference' => trim((string) ($row['mandatsreferenz'] ?? $row['sepa_mandate_reference'] ?? '')) ?: null,
                'sepa_mandate_signed_on' => $this->normalizeImportDate($row['mandatsdatum'] ?? $row['sepa_mandate_signed_on'] ?? null),
                'sepa_mandate_active' => $this->normalizeBoolean($row['sepa_aktiv'] ?? $row['sepa_mandate_active'] ?? null),
                'joined_on' => $this->normalizeImportDate($row['eintritt'] ?? $row['joined_on'] ?? null),
                'membership_ends_on' => $this->normalizeImportDate($row['ende'] ?? $row['membership_ends_on'] ?? null),
                'membership_notes' => trim((string) ($row['notiz'] ?? $row['membership_notes'] ?? '')) ?: null,
            ];
        }

        if ($sendInvitation) {
            $this->planFeatures->ensureCanSendMemberInvitations($club, count($members));
        }

        $this->planFeatures->ensureCanAddManualMembers($club, $this->newEmailMemberCount($club, $members));

        foreach ($members as $memberData) {
            $email = $memberData['email'];

            $existingUser = User::query()->where('email', $email)->first();

            $externalMember = ClubExternalMember::updateOrCreate(
                [
                    'club_id' => $club->id,
                    'email' => $email,
                ],
                [
                    'created_by' => $request->user()->id,
                    'name' => $memberData['name'],
                    'role' => 'member',
                    'membership_status' => $memberData['membership_status'],
                    'family_group_key' => $memberData['family_group_key'],
                    'member_number' => $memberData['member_number'],
                    'athlete_license_number' => $memberData['athlete_license_number'],
                    'athlete_license_valid_until' => $memberData['athlete_license_valid_until'],
                    'contribution_amount' => $memberData['contribution_amount'],
                    'contribution_interval' => $memberData['contribution_interval'],
                    'contribution_next_invoice_on' => $memberData['contribution_next_invoice_on'],
                    'contribution_last_invoice_at' => null,
                    'sepa_iban' => $memberData['sepa_iban'],
                    'sepa_bic' => $memberData['sepa_bic'],
                    'sepa_mandate_reference' => $memberData['sepa_mandate_reference'],
                    'sepa_mandate_signed_on' => $memberData['sepa_mandate_signed_on'],
                    'sepa_mandate_active' => $memberData['sepa_mandate_active'],
                    'joined_on' => $memberData['joined_on'] ?? now()->toDateString(),
                    'membership_ends_on' => $memberData['membership_ends_on'],
                    'membership_end_notified_at' => null,
                    'membership_notes' => $memberData['membership_notes'],
                    'invitation_status' => 'none',
                    'invitation_token' => null,
                    'invited_at' => null,
                    'invitation_expires_at' => null,
                ],
            );

            if ($sendInvitation) {
                $externalMember->issueInvitation();
                $this->sendExternalMemberInvitation($externalMember, $existingUser);
                $stats['invited']++;
            } else {
                $stats['stored']++;
            }
        }

        $message = "Import fertig: {$stats['stored']} gespeichert, {$stats['linked']} verknüpft, {$stats['invited']} eingeladen, {$stats['skipped']} übersprungen.";
        $report = [
            'summary' => $message,
            'total_errors' => count($importErrors),
            'errors' => array_slice($importErrors, 0, 50),
        ];

        return back()
            ->with($members === [] ? 'error' : 'success', $members === [] ? 'Keine importierbaren Mitglieder gefunden.' : $message)
            ->with('import_report', $report);
    }

    /**
     * Parse a membership file without writing anything. The mobile client uses
     * this contract to let a manager review duplicates and invalid rows before
     * the existing import endpoint is called.
     */
    public function previewMembershipImport(Request $request, Club $club): array
    {
        abort_unless(ClubPermissions::allows($club, $request->user(), ClubPermissions::MEMBERS_EDIT), 403);
        $this->planFeatures->ensureAllows($club, 'member_import');

        $data = $request->validate([
            'file' => ['required', 'file', 'max:10240'],
            'mapping' => ['nullable'],
        ]);

        $mapping = $this->importMappingFromRequest($request);
        $importMeta = [];
        $rows = $this->readMembershipImportRows(
            $data['file']->getRealPath(),
            $data['file']->getClientOriginalExtension(),
            $mapping,
            $importMeta,
        );
        $seenEmails = [];
        $members = [];
        $errors = [];

        foreach ($rows as $index => $row) {
            $rowNumber = (int) ($row['__row_number'] ?? ($index + 1));
            $email = strtolower(trim((string) ($row['email'] ?? '')));

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = [
                    'row' => $rowNumber,
                    'email' => $email,
                    'reason' => 'Ungültige oder fehlende E-Mail-Adresse.',
                ];

                continue;
            }

            if (isset($seenEmails[$email])) {
                $errors[] = [
                    'row' => $rowNumber,
                    'email' => $email,
                    'reason' => 'Doppelte E-Mail in der Importdatei; erster Eintrag wurde verwendet.',
                ];

                continue;
            }

            $seenEmails[$email] = true;
            $existingUser = User::query()->where('email', $email)->first(['id', 'name', 'email']);
            $existingExternal = $club->externalMembers()->where('email', $email)->first(['id', 'name', 'email']);
            $members[] = [
                'row' => $rowNumber,
                'name' => trim((string) ($row['name'] ?? '')) ?: null,
                'email' => $email,
                'membership_status' => $this->normalizeMembershipStatus($row['mitgliedschaft'] ?? $row['membership_status'] ?? 'active'),
                'family_group_key' => $this->normalizeFamilyGroupKey($row['familiengruppe'] ?? $row['family_group_key'] ?? null),
                'contribution_amount' => $this->normalizeMoney($row['beitrag'] ?? $row['contribution_amount'] ?? null),
                'contribution_interval' => $this->normalizeContributionInterval($row['intervall'] ?? $row['contribution_interval'] ?? 'none'),
                'existing_user' => $existingUser ? [
                    'id' => $existingUser->id,
                    'name' => $existingUser->name,
                    'email' => $existingUser->email,
                ] : null,
                'existing_external' => $existingExternal ? [
                    'id' => $existingExternal->id,
                    'name' => $existingExternal->name,
                    'email' => $existingExternal->email,
                ] : null,
                'action' => $existingUser ? 'link_existing_user' : ($existingExternal ? 'update_external_member' : 'create_external_member'),
            ];
        }

        return [
            'file_name' => $data['file']->getClientOriginalName(),
            'total_rows' => count($rows),
            'valid_rows' => count($members),
            'error_count' => count($errors),
            'can_import' => $members !== [],
            'rows' => array_slice($members, 0, 100),
            'errors' => array_slice($errors, 0, 100),
            'columns' => $importMeta['columns'] ?? [],
            'mapping' => $importMeta['mapping'] ?? $mapping,
            'mapping_fields' => self::IMPORT_FIELDS,
            'needs_mapping' => ! array_key_exists('email', $importMeta['mapping'] ?? $mapping),
        ];
    }

    public function downloadImportTemplate()
    {
        $path = $this->buildMembershipImportTemplate();

        return response()
            ->download($path, 'airmius-mitglieder-import-vorlage.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend(true);
    }

    public function updateSepaSettings(Request $request, Club $club)
    {
        abort_unless(ClubPermissions::allows($club, $request->user(), ClubPermissions::FINANCE_EDIT), 403);
        $this->planFeatures->ensureAllows($club, 'sepa_export');

        $data = $request->validate([
            'sepa_creditor_id' => ['nullable', 'string', 'max:80'],
            'sepa_account_holder' => ['nullable', 'string', 'max:120'],
            'sepa_iban' => ['nullable', 'string', 'max:40'],
            'sepa_bic' => ['nullable', 'string', 'max:20'],
        ]);

        $club->update([
            'sepa_creditor_id' => $data['sepa_creditor_id'] ?? null,
            'sepa_account_holder' => $data['sepa_account_holder'] ?? null,
            'sepa_iban' => $this->normalizeIban($data['sepa_iban'] ?? null),
            'sepa_bic' => $this->normalizeBic($data['sepa_bic'] ?? null),
        ]);

        return back()->with('success', __('organization.club.sepa_settings_saved'));
    }

    public function exportSepaDebit(Club $club)
    {
        abort_unless(ClubPermissions::allows($club, request()->user(), ClubPermissions::FINANCE_EXPORT), 403);
        $this->planFeatures->ensureAllows($club, 'sepa_export');

        // An active controlled run must not be bypassed by the compatibility export.
        if (Schema::hasTable('club_sepa_batches')) {
            abort_if(ClubSepaBatch::where('club_id', $club->id)
                ->where('status', '!=', 'cancelled')->exists(), 422, __('sepa.use_batch'));
        }

        abort_if(blank($club->sepa_creditor_id) || blank($club->sepa_iban), 422, __('organization.club.sepa_credentials_required'));

        $invoices = Invoice::query()
            ->where('club_id', $club->id)
            ->whereIn('status', ['open', 'overdue'])
            ->where('amount', '>', 0)
            ->whereNotNull('user_id')
            ->with('user:id,name,email')
            ->withSum('settledPayments', 'amount')
            ->orderBy('due_date')
            ->get();

        $memberships = DB::table('club_user')
            ->where('club_id', $club->id)
            ->whereIn('user_id', $invoices->pluck('user_id')->filter()->unique()->values())
            ->get()
            ->keyBy('user_id');

        $exportable = $invoices
            ->filter(function (Invoice $invoice) use ($memberships) {
                $membership = $memberships->get($invoice->user_id);

                return $invoice->outstandingCents() > 0 && $membership
                    && (bool) $membership->sepa_mandate_active
                    && filled($membership->sepa_iban)
                    && filled($membership->sepa_mandate_reference)
                    && filled($membership->sepa_mandate_signed_on);
            })
            ->values();

        abort_if($exportable->isEmpty(), 422, __('organization.club.sepa_no_open_invoices'));

        $xml = $this->buildSepaDebitXml($club, $exportable, $memberships);
        $fileName = 'airmius-sepa-'.$club->id.'-'.now()->format('Ymd-His').'.xml';

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
        ]);
    }

    public function inviteEmailMember(Request $request, ClubExternalMember $externalMember)
    {
        abort_unless(ClubPermissions::allows($externalMember->club, $request->user(), ClubPermissions::MEMBERS_EDIT), 403);
        $this->planFeatures->ensureCanSendMemberInvitations($externalMember->club);

        $existingUser = User::query()
            ->where('email', strtolower($externalMember->email))
            ->first();

        abort_if(
            $existingUser && $externalMember->club->users()->where('users.id', $existingUser->id)->exists(),
            422,
            __('organization.club.duplicate_requires_review'),
        );

        $externalMember->issueInvitation();
        $this->sendExternalMemberInvitation($externalMember, $existingUser);

        return back()->with('success', __('organization.club.invitation_sent'));
    }

    public function mergeExternalMember(
        Request $request,
        Club $club,
        ClubExternalMember $externalMember,
        User $user,
    ) {
        abort_unless(ClubPermissions::allows($club, $request->user(), ClubPermissions::MEMBERS_EDIT), 403);
        abort_unless((int) $externalMember->club_id === (int) $club->id, 404);

        $data = $request->validate([
            'resolution' => ['required', Rule::in(['keep_registered', 'use_external'])],
            'confirm_email' => ['required', 'string', 'max:255'],
        ]);

        if (strtolower(trim((string) $data['confirm_email'])) !== strtolower(trim((string) $externalMember->email))) {
            throw ValidationException::withMessages([
                'confirm_email' => __('organization.club.duplicate_confirmation_invalid'),
            ]);
        }

        app(ClubExternalMemberMergeService::class)->merge(
            $club,
            $externalMember,
            $user,
            $request->user(),
            $data['resolution'],
        );

        return back()->with('success', __('organization.club.duplicate_merged'));
    }

    public function acceptExternalInvitation(Request $request, string $token)
    {
        $externalMember = ClubExternalMember::query()
            ->where('invitation_token', $token)
            ->where('invitation_status', 'pending')
            ->firstOrFail();

        abort_unless(strtolower((string) $request->user()->email) === strtolower($externalMember->email), 403);

        if ($externalMember->invitationExpired()) {
            $externalMember->markInvitationExpired();

            abort(410, __('organization.club.invitation_expired'));
        }

        DB::transaction(function () use ($externalMember, $request) {
            $roles = ClubRoles::normalize($externalMember->role, [$externalMember->role]);

            $externalMember->club->users()->syncWithoutDetaching([
                $request->user()->id => [
                    'role' => ClubRoles::primary($roles),
                    'roles' => $roles,
                    'membership_status' => $externalMember->membership_status,
                    'club_membership_type_id' => $externalMember->club_membership_type_id,
                    'family_group_key' => $externalMember->family_group_key,
                    'contribution_payer_user_id' => (int) $externalMember->contribution_payer_user_id === (int) $request->user()->id
                        ? null
                        : $externalMember->contribution_payer_user_id,
                    'member_number' => $externalMember->member_number,
                    'contribution_amount' => $externalMember->contribution_amount,
                    'contribution_interval' => $externalMember->contribution_interval ?? 'none',
                    'contribution_next_invoice_on' => $externalMember->contribution_next_invoice_on?->toDateString(),
                    'contribution_last_invoice_at' => null,
                    'sepa_iban' => $externalMember->sepa_iban,
                    'sepa_bic' => $externalMember->sepa_bic,
                    'sepa_mandate_reference' => $externalMember->sepa_mandate_reference,
                    'sepa_mandate_signed_on' => $externalMember->sepa_mandate_signed_on?->toDateString(),
                    'sepa_mandate_active' => $externalMember->sepa_mandate_active,
                    'joined_on' => $externalMember->joined_on?->toDateString() ?? now()->toDateString(),
                    'membership_ends_on' => $externalMember->membership_ends_on?->toDateString(),
                    'membership_end_notified_at' => null,
                    'membership_notes' => $externalMember->membership_notes,
                ],
            ]);

            if (filled($externalMember->athlete_license_number) || filled($externalMember->athlete_license_valid_until)) {
                $request->user()->forceFill([
                    'athlete_license_number' => $externalMember->athlete_license_number,
                    'athlete_license_valid_until' => $externalMember->athlete_license_valid_until,
                ])->save();
            }

            $user = $request->user();
            $contactUpdates = collect(['phone', 'country', 'street', 'house_number', 'postal_code', 'city'])
                ->filter(fn (string $field) => blank($user->getAttribute($field)) && filled($externalMember->getAttribute($field)))
                ->mapWithKeys(fn (string $field) => [$field => $externalMember->getAttribute($field)])
                ->all();
            if ($contactUpdates !== []) {
                $user->forceFill($contactUpdates)->save();
            }

            $externalMember->delete();
        });

        return redirect()
            ->route('auth.clubs.show', $externalMember->club_id)
            ->with('success', __('organization.club.membership_linked'));
    }

    public function storeInvoice(Request $request, Club $club, User $user)
    {
        abort_unless(ClubPermissions::allows($club, $request->user(), ClubPermissions::FINANCE_EDIT), 403);
        abort_unless($club->users()->where('users.id', $user->id)->exists(), 404);
        $this->planFeatures->ensureAllows($club, 'invoices');

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'amount' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'billing_period_start' => ['nullable', 'date'],
            'billing_period_end' => ['nullable', 'date', 'after_or_equal:billing_period_start'],
            'due_date' => ['required', 'date'],
            'waived' => ['sometimes', 'boolean'],
            'waiver_reason' => ['nullable', 'string', 'max:500'],
        ]);
        $isWaived = $request->boolean('waived');
        abort_if(! $isWaived && (float) $data['amount'] < 0.01, 422, __('validation.min.numeric', ['attribute' => 'amount', 'min' => '0.01']));

        $membership = $club->users()->where('users.id', $user->id)->firstOrFail()->pivot;
        $payer = filled($membership->contribution_payer_user_id)
            ? $club->users()->where('users.id', $membership->contribution_payer_user_id)->firstOrFail()
            : $user;

        $invoice = DB::transaction(function () use ($club, $user, $payer, $data, $request, $isWaived) {
            \App\Services\ClubInvoiceCreditService::lockClub((int) $club->id);
            \App\Services\ClubInvoiceCreditService::assertNoDuplicate((int) $club->id, (int) $user->id, null, $data);
            $allocation = $this->numberRanges->allocateDefault(
                $club,
                'invoice',
                $request->user(),
                (string) Str::uuid(),
                fn (string $number) => ! Invoice::query()->where('number', $number)->exists()
            );
            $invoice = Invoice::create([
                'club_id' => $club->id,
                'user_id' => $payer->id,
                'membership_user_id' => $user->id,
                'number' => $allocation?->formatted_number ?? $this->nextInvoiceNumber($club),
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'amount' => $data['amount'],
                'status' => $isWaived ? 'waived' : 'open',
                'claim_status' => $isWaived ? 'waived' : 'open',
                'source' => $isWaived ? 'manual_waiver' : 'manual',
                'contribution_snapshot' => $isWaived ? [
                    'waived' => true,
                    'waiver_reason' => $data['waiver_reason'] ?? null,
                    'original_amount' => $data['amount'],
                    'waived_amount' => $data['amount'],
                    'waived_by_user_id' => $request->user()->id,
                    'waived_at' => now()->toJSON(),
                ] : null,
                'billing_period_start' => $data['billing_period_start'] ?? null,
                'billing_period_end' => $data['billing_period_end'] ?? null,
                'due_date' => $data['due_date'],
                'issued_at' => now(),
            ]);
            if ($allocation) {
                $this->numberRanges->assignTo($allocation, 'invoice', $invoice->id);
            }
            app(\App\Services\ClubInvoiceCreditService::class)->apply($invoice, $request->user());
            ClubAuditLog::record($club, $request->user(), 'club.invoice.created', $invoice, [
                'invoice_number' => $invoice->number,
                'invoice_title' => $invoice->title,
                'amount' => $invoice->amount,
                'waived' => $isWaived,
                'waiver_reason' => $data['waiver_reason'] ?? null,
                'member_id' => $user->id,
                'member_name' => $user->name,
            ]);

            return $invoice;
        });

        if ($isWaived) {
            return back()->with('success', __('organization.club.invoice_created'));
        }

        AppNotification::sendLocalized(
            $payer,
            'invoice.created',
            'organization.notifications.invoice_created_title',
            'organization.notifications.invoice_created_body',
            [
                'club' => $club->name,
                'title' => $invoice->title,
                'amount' => number_format((float) $invoice->amount, 2, ',', '.'),
            ],
            [
                'url' => route('auth.club-memberships.index', [
                    'tab' => 'payments',
                    'club_id' => $club->id,
                    'invoice_id' => $invoice->id,
                ]),
                'club_id' => $club->id,
                'invoice_id' => $invoice->id,
                'invoice_kind' => 'club_invoice',
            ],
        );

        $this->sendClubInvoiceCreatedEmail($payer, $invoice->loadMissing('club'));

        return back()->with('success', __('organization.club.invoice_created'));
    }

    public function storeExternalMemberInvoice(Request $request, Club $club, ClubExternalMember $externalMember)
    {
        abort_unless(ClubPermissions::allows($club, $request->user(), ClubPermissions::FINANCE_EDIT), 403);
        abort_unless((int) $externalMember->club_id === (int) $club->id, 404);
        $this->planFeatures->ensureAllows($club, 'invoices');

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'amount' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'billing_period_start' => ['nullable', 'date'],
            'billing_period_end' => ['nullable', 'date', 'after_or_equal:billing_period_start'],
            'due_date' => ['required', 'date'],
            'waived' => ['sometimes', 'boolean'],
            'waiver_reason' => ['nullable', 'string', 'max:500'],
        ]);
        $isWaived = $request->boolean('waived');
        abort_if(! $isWaived && (float) $data['amount'] < 0.01, 422, __('validation.min.numeric', ['attribute' => 'amount', 'min' => '0.01']));

        $payer = filled($externalMember->contribution_payer_user_id)
            ? $club->users()->where('users.id', $externalMember->contribution_payer_user_id)->first()
            : null;

        $invoice = DB::transaction(function () use ($club, $externalMember, $payer, $data, $request, $isWaived) {
            \App\Services\ClubInvoiceCreditService::lockClub((int) $club->id);
            \App\Services\ClubInvoiceCreditService::assertNoDuplicate((int) $club->id, null, (int) $externalMember->id, $data);
            $allocation = $this->numberRanges->allocateDefault(
                $club,
                'invoice',
                $request->user(),
                (string) Str::uuid(),
                fn (string $number) => ! Invoice::query()->where('number', $number)->exists()
            );
            $invoice = Invoice::create([
                'club_id' => $club->id,
                'user_id' => $payer?->id,
                'club_external_member_id' => $externalMember->id,
                'number' => $allocation?->formatted_number ?? $this->nextInvoiceNumber($club),
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'amount' => $data['amount'],
                'status' => $isWaived ? 'waived' : 'open',
                'claim_status' => $isWaived ? 'waived' : 'open',
                'source' => $isWaived ? 'manual_waiver' : 'manual',
                'contribution_snapshot' => $isWaived ? [
                    'waived' => true,
                    'waiver_reason' => $data['waiver_reason'] ?? null,
                    'original_amount' => $data['amount'],
                    'waived_amount' => $data['amount'],
                    'waived_by_user_id' => $request->user()->id,
                    'waived_at' => now()->toJSON(),
                ] : null,
                'billing_period_start' => $data['billing_period_start'] ?? null,
                'billing_period_end' => $data['billing_period_end'] ?? null,
                'due_date' => $data['due_date'],
                'issued_at' => now(),
            ]);
            if ($allocation) {
                $this->numberRanges->assignTo($allocation, 'invoice', $invoice->id);
            }
            app(\App\Services\ClubInvoiceCreditService::class)->apply($invoice, $request->user());
            ClubAuditLog::record($club, $request->user(), 'club.invoice.created', $invoice, [
                'invoice_number' => $invoice->number,
                'invoice_title' => $invoice->title,
                'amount' => $invoice->amount,
                'waived' => $isWaived,
                'waiver_reason' => $data['waiver_reason'] ?? null,
                'external_member_id' => $externalMember->id,
                'member_name' => $externalMember->name ?: $externalMember->email,
            ]);

            return $invoice;
        });

        if (! $isWaived) {
            $this->sendClubInvoiceCreatedEmailToExternalMember($externalMember, $invoice->loadMissing('club', 'externalMember'));
        }

        return back()->with('success', __('organization.club.invoice_created'));
    }

    private function sendClubInvoiceCreatedEmail(User $user, Invoice $invoice): void
    {
        if (! $user->email) {
            return;
        }

        $mailer = app(TransactionalMail::class);

        $mailer->notifyWithFallback(
            $user,
            fn (array $transport) => new ClubInvoiceCreated(
                $invoice,
                $transport['mailer'],
                $transport['address'],
                $transport['name'],
            ),
            $mailer->invoicePrimaryCategory(),
            $mailer->invoiceFallbackCategory(),
            'club.invoice.created:'.$invoice->id.':'.$user->id,
            (int) config('airmius_mail.throttle_seconds.invoice_created', 21600),
            [
                'mail_type' => 'club.invoice.created',
                'invoice_id' => $invoice->id,
                'recipient_id' => $user->id,
            ],
        );
    }

    private function sendClubInvoiceCreatedEmailToExternalMember(ClubExternalMember $externalMember, Invoice $invoice): void
    {
        if (! $externalMember->email) {
            return;
        }

        $mailer = app(TransactionalMail::class);
        $transport = $mailer->transportFor($mailer->invoicePrimaryCategory());

        Notification::route('mail', $externalMember->email)
            ->notify(new ClubInvoiceCreated(
                $invoice,
                $transport['mailer'],
                $transport['address'],
                $transport['name'],
            ));
    }

    private function invoicePayload(Invoice $invoice): array
    {
        return [
            'team_id' => $invoice->team_id,
            'club_budget_id' => $invoice->club_budget_id,
            'club_department_id' => $invoice->club_department_id,
            'club_project_id' => $invoice->club_project_id,
            'club_cost_center_id' => $invoice->club_cost_center_id,
            'id' => $invoice->id,
            'club_id' => $invoice->club_id,
            'user_id' => $invoice->user_id,
            'membership_user_id' => $invoice->membership_user_id,
            'club_external_member_id' => $invoice->club_external_member_id,
            'number' => $invoice->number,
            'title' => $invoice->title,
            'description' => $invoice->description,
            'amount' => $invoice->amount,
            ...$invoice->balancePayload(),
            'status' => $invoice->status,
            'status_label' => $invoice->statusLabel(),
            'source' => $invoice->source,
            'business_year_period_id' => $invoice->business_year_period_id,
            'business_year_period' => $this->yearPeriodReference($invoice->businessYearPeriod),
            'contribution_year_period_id' => $invoice->contribution_year_period_id,
            'contribution_year_period' => $this->yearPeriodReference($invoice->contributionYearPeriod),
            'billing_period_start' => $invoice->billing_period_start?->toDateString(),
            'billing_period_end' => $invoice->billing_period_end?->toDateString(),
            'due_date' => $invoice->due_date?->toJSON(),
            'issued_at' => $invoice->issued_at?->toJSON(),
            'paid_at' => $invoice->paid_at?->toJSON(),
            'reminder_sent_at' => $invoice->reminder_sent_at?->toJSON(),
            'user' => $invoice->user ? [
                'id' => $invoice->user->id,
                'name' => $invoice->user->name,
                'email' => $invoice->user->email,
            ] : null,
            'membership_user' => $invoice->membershipUser ? [
                'id' => $invoice->membershipUser->id,
                'name' => $invoice->membershipUser->name,
                'email' => $invoice->membershipUser->email,
            ] : null,
            'member' => $invoice->externalMember ? [
                'id' => $invoice->externalMember->id,
                'name' => $invoice->externalMember->name,
                'email' => $invoice->externalMember->email,
                'is_external' => true,
            ] : ($invoice->membershipUser ? [
                'id' => $invoice->membershipUser->id,
                'name' => $invoice->membershipUser->name,
                'email' => $invoice->membershipUser->email,
                'is_external' => false,
            ] : null),
            'payments' => $invoice->paymentHistoryPayload(),
            'created_at' => $invoice->created_at?->toJSON(),
            'updated_at' => $invoice->updated_at?->toJSON(),
        ];
    }

    private function yearPeriodReference(?ClubYearPeriod $period): ?array
    {
        return $period ? [
            'id' => $period->id,
            'name' => $period->name,
            'starts_on' => $period->starts_on?->toDateString(),
            'ends_on' => $period->ends_on?->toDateString(),
        ] : null;
    }

    public function generateMemberNumber(Club $club, User $user, ?User $actor = null)
    {
        abort_unless(ClubPermissions::allows($club, $actor ?? request()->user(), ClubPermissions::METADATA_EDIT), 403);
        abort_unless($club->users()->where('users.id', $user->id)->exists(), 404);

        $actor ??= request()->user();
        DB::transaction(function () use ($club, $user, $actor) {
            $allocation = $this->numberRanges->allocateDefault(
                $club,
                'member',
                $actor,
                (string) Str::uuid(),
                fn (string $number) => ! DB::table('club_user')
                    ->where('club_id', $club->id)->where('member_number', $number)->exists()
                    && ! $club->externalMembers()->where('member_number', $number)->exists()
            );
            $club->users()->updateExistingPivot($user->id, [
                'member_number' => $allocation?->formatted_number ?? $this->nextMemberNumber($club),
            ]);
            if ($allocation) {
                $this->numberRanges->assignTo($allocation, 'member', $user->id);
            }
        });

        return back()->with('success', __('organization.club.member_number_generated'));
    }

    public function updateInvoiceStatus(Request $request, Invoice $invoice)
    {
        abort_unless(ClubPermissions::allows($invoice->club, $request->user(), ClubPermissions::FINANCE_EDIT), 403);
        $this->planFeatures->ensureAllows($invoice->club, 'payment_tracking');

        $data = $request->validate([
            'status' => ['required', Rule::in(Invoice::PAYMENT_STATUSES)],
            'cancellation_action' => ['nullable', Rule::in(ClubInvoiceCancellationService::ACTIONS)],
        ]);

        if ($data['status'] === 'cancelled') {
            app(ClubInvoiceCancellationService::class)->cancel(
                $invoice,
                $data['cancellation_action'] ?? null,
                $request->user(),
            );

            return back()->with('success', __('organization.club.invoice_status_updated'));
        }

        DB::transaction(function () use ($invoice, $data, $request) {
            \App\Services\ClubInvoiceCreditService::lockClub((int) $invoice->club_id);
            $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            $oldStatus = $locked->status;
            abort_if($locked->status === 'cancelled', 422, __('organization.club.cancelled_invoice_reopen_forbidden'));
            abort_if($locked->status === 'waived' && $data['status'] !== 'waived', 422, __('organization.club.waived_invoice_reopen_forbidden'));
            $received = $locked->receivedCents();
            $covered = $received >= (int) round((float) $locked->amount * 100);
            if ($data['status'] === 'waived') {
                $snapshot = $locked->contribution_snapshot ?: [];
                $snapshot['waived'] = true;
                $snapshot['original_amount'] ??= $locked->amount;
                $snapshot['waived_amount'] = number_format(max(0, (int) round((float) $locked->amount * 100) - $received) / 100, 2, '.', '');
                $snapshot['waived_by_user_id'] = $request->user()->id;
                $snapshot['waived_at'] = now()->toJSON();
                $locked->update(['status' => 'waived', 'claim_status' => 'waived', 'paid_at' => null, 'contribution_snapshot' => $snapshot]);
            } else {
                abort_if(($data['status'] === 'paid') !== $covered, 422, __('organization.club.invoice_status_requires_payment'));
                abort_if($data['status'] === 'overdue' && ! $locked->due_date?->isPast(), 422, __('organization.club.invoice_not_overdue'));
                app(ClubInvoicePaymentService::class)->synchronize($locked);
            }
            $invoice->refresh();
            if ($oldStatus !== $invoice->status) {
                ClubAuditLog::record($invoice->club, $request->user(), 'club.invoice.status_updated', $invoice, [
                    'invoice_number' => $invoice->number,
                    'old_status' => $oldStatus,
                    'new_status' => $invoice->status,
                ]);
            }
        });

        return back()->with('success', __('organization.club.invoice_status_updated'));
    }

    public function recordPayment(Request $request, Invoice $invoice)
    {
        abort_unless(ClubPermissions::allows($invoice->club, $request->user(), ClubPermissions::FINANCE_EDIT), 403);
        abort_if(in_array($invoice->status, ['paid', 'cancelled', 'waived'], true), 422, __('organization.club.paid_invoice_payment_forbidden'));

        $data = $request->validate([
            'amount' => ['nullable', 'numeric', 'min:0.01', 'max:999999.99'],
            'partial_payment' => ['nullable', 'boolean'],
            'method' => ['nullable', 'string', 'max:60'],
            'reference' => ['nullable', 'string', 'max:255'],
            'paid_at' => ['nullable', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
            'club_money_account_id' => ['nullable', 'integer'],
        ], [
            'paid_at.before_or_equal' => __('organization.club.payment_date_future'),
        ]);

        if (array_key_exists('partial_payment', $data) && ! $data['partial_payment']) {
            unset($data['amount']);
        } elseif (($data['partial_payment'] ?? false) && ! array_key_exists('amount', $data)) {
            throw ValidationException::withMessages([
                'amount' => __('organization.club.partial_payment_amount_required'),
            ]);
        }

        $payment = app(ClubInvoicePaymentService::class)->record($invoice, $data, $request->user());

        if ($invoice->user_id) {
            try {
                AppNotification::sendLocalized(
                    (int) $invoice->user_id,
                    $invoice->status === 'paid' ? 'invoice.paid' : 'invoice.payment_received',
                    'organization.notifications.payment_title',
                    'organization.notifications.payment_body',
                    ['invoice' => $invoice->number],
                    [
                        'url' => route('auth.club-memberships.index', [
                            'tab' => 'payments',
                            'club_id' => $invoice->club_id,
                            'invoice_id' => $invoice->id,
                        ]),
                        'club_id' => $invoice->club_id,
                        'invoice_id' => $invoice->id,
                        'invoice_kind' => 'club_invoice',
                    ],
                    ['dedupe_key' => 'invoice-payment-'.$payment->id],
                );
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return back()->with('success', __('organization.club.payment_recorded'));
    }

    public function replaceCancelledInvoice(Request $request, Invoice $invoice)
    {
        abort_unless(ClubPermissions::allows($invoice->club, $request->user(), ClubPermissions::FINANCE_EDIT), 403);
        $this->planFeatures->ensureAllows($invoice->club, 'invoices');

        $replacement = app(ClubInvoiceCancellationService::class)
            ->replaceAccidentalCancellation($invoice, $request->user())
            ->loadMissing(['club', 'user', 'externalMember']);

        if ($replacement->wasRecentlyCreated && $replacement->user) {
            AppNotification::sendLocalized(
                $replacement->user,
                'invoice.created',
                'organization.notifications.invoice_created_title',
                'organization.notifications.invoice_created_body',
                [
                    'club' => $replacement->club->name,
                    'title' => $replacement->title,
                    'amount' => number_format((float) $replacement->amount, 2, ',', '.'),
                ],
                [
                    'url' => route('auth.club-memberships.index', [
                        'tab' => 'payments',
                        'club_id' => $replacement->club_id,
                        'invoice_id' => $replacement->id,
                    ]),
                    'club_id' => $replacement->club_id,
                    'invoice_id' => $replacement->id,
                    'invoice_kind' => 'club_invoice',
                ],
            );
            $this->sendClubInvoiceCreatedEmail($replacement->user, $replacement);
        } elseif ($replacement->wasRecentlyCreated && $replacement->externalMember) {
            $this->sendClubInvoiceCreatedEmailToExternalMember($replacement->externalMember, $replacement);
        }

        return back()->with('success', __('organization.club.invoice_replacement_created'));
    }

    public function storeFinanceEntry(Request $request, Club $club)
    {
        abort_unless(ClubPermissions::allows($club, $request->user(), ClubPermissions::FINANCE_EDIT), 403);
        $this->planFeatures->ensureAllows($club, 'payment_tracking');

        ClubFinanceEntry::create([
            ...$this->validatedFinanceEntryData($request, $club),
            'club_id' => $club->id,
            'user_id' => $request->user()->id,
        ]);

        return back()->with('success', __('organization.club.finance_entry_saved'));
    }

    public function updateFinanceEntry(Request $request, Club $club, ClubFinanceEntry $financeEntry)
    {
        abort_unless(ClubPermissions::allows($club, $request->user(), ClubPermissions::FINANCE_EDIT), 403);
        abort_unless((int) $financeEntry->club_id === (int) $club->id, 404);
        abort_unless(($financeEntry->entry_kind ?? 'operating') === 'operating', 422, 'Anfangsbestände und Transfers können nicht als freie Buchung bearbeitet werden.');
        abort_if(ClubFinanceWorkspaceReadiness::ready() && TeamFee::where('club_finance_entry_id', $financeEntry->id)->exists(), 422, 'Teamzahlungen werden in der Mannschaftskasse verwaltet.');
        abort_if(ClubProcurementReceipt::where('club_finance_entry_id', $financeEntry->id)->exists(), 422, 'Beschaffungszahlungen werden im Beschaffungsablauf verwaltet.');
        $this->planFeatures->ensureAllows($club, 'payment_tracking');

        app(ClubSepaFeeService::class)->updateFinanceEntry($financeEntry, $this->validatedFinanceEntryData($request, $club));

        return back()->with('success', __('organization.club.finance_entry_updated'));
    }

    public function sendReminder(Invoice $invoice)
    {
        abort_unless(ClubPermissions::allows($invoice->club, request()->user(), ClubPermissions::FINANCE_EDIT), 403);
        abort_if($invoice->status === 'paid', 422, __('organization.club.paid_invoice_reminder_forbidden'));
        $this->planFeatures->ensureAllows($invoice->club, 'payment_reminders');

        $invoice->update([
            'status' => 'overdue',
            'reminder_sent_at' => now(),
        ]);

        ClubAuditLog::record($invoice->club, request()->user(), 'club.invoice.reminder_sent', $invoice, [
            'invoice_number' => $invoice->number,
            'member_id' => $invoice->user_id,
        ]);

        AppNotification::sendLocalized(
            (int) $invoice->user_id,
            'invoice.reminder',
            'organization.notifications.invoice_reminder_title',
            'organization.notifications.invoice_reminder_body',
            ['invoice' => $invoice->number],
            [
                'url' => route('auth.club-memberships.index', [
                    'tab' => 'payments',
                    'club_id' => $invoice->club_id,
                    'invoice_id' => $invoice->id,
                ]),
                'club_id' => $invoice->club_id,
                'invoice_id' => $invoice->id,
                'invoice_kind' => 'club_invoice',
            ],
        );

        return back()->with('success', __('organization.club.reminder_sent'));
    }

    public function importBankTransactions(Request $request, Club $club)
    {
        abort_unless(ClubPermissions::allows($club, $request->user(), ClubPermissions::FINANCE_EDIT), 403);
        $this->planFeatures->ensureAllows($club, 'bank_reconciliation');

        $data = $request->validate([
            'file' => ['required', 'file', 'max:10240'],
            'club_money_account_id' => ['nullable', 'integer'],
        ]);

        $rows = $this->readBankTransactionRows($data['file']->getRealPath());
        $accountId = app(ClubMoneyAccountService::class)->resolve(
            (int) $club->id, 'bank', $request->integer('club_money_account_id') ?: null, requireSelection: true,
        );
        abort_if($accountId && ! Schema::hasColumn('bank_transactions', 'club_money_account_id'), 503, 'Kontenmigration ist noch nicht aktiviert.');
        $stats = [
            'imported' => 0,
            'auto_matched' => 0,
            'suggested' => 0,
            'unmatched' => 0,
            'duplicates' => 0,
            'skipped' => 0,
        ];

        foreach ($rows as $row) {
            $transaction = $this->normalizeBankTransactionRow($row);

            if (! $transaction || blank($transaction['booking_date']) || (float) $transaction['amount'] <= 0) {
                $stats['skipped']++;

                continue;
            }

            if (app(ClubMoneyAccountService::class)->isDuplicateTransaction((int) $club->id, $transaction['transaction_hash'], $accountId)) {
                $stats['duplicates']++;

                continue;
            }
            $transaction['transaction_hash'] = app(ClubMoneyAccountService::class)->transactionHash($transaction['transaction_hash'], $accountId);

            $match = $this->findInvoiceMatchForBankTransaction($club, $transaction);
            $status = $this->bankTransactionImportStatus($match);

            DB::transaction(function () use ($club, $request, $transaction, $match, $status, $accountId) {
                BankTransaction::create([
                    'club_id' => $club->id,
                    ...(Schema::hasColumn('bank_transactions', 'club_money_account_id')
                        ? ['club_money_account_id' => $accountId] : []),
                    'invoice_id' => $match['invoice']?->id,
                    'payment_id' => null,
                    'imported_by' => $request->user()->id,
                    'transaction_hash' => $transaction['transaction_hash'],
                    'booking_date' => $transaction['booking_date'],
                    'amount' => $transaction['amount'],
                    'currency' => $transaction['currency'],
                    'debtor_name' => $transaction['debtor_name'],
                    'debtor_iban' => $transaction['debtor_iban'],
                    'purpose' => $transaction['purpose'],
                    'status' => $status,
                    'match_confidence' => $match['confidence'],
                    'match_reason' => $match['reason'],
                    'raw_data' => $transaction['raw_data'],
                ]);
            });

            $stats['imported']++;
            $stats[$status === 'suggested' ? 'suggested' : 'unmatched']++;
        }

        return back()->with(
            'success',
            "Bankabgleich fertig: 0 automatisch bezahlt, {$stats['suggested']} Vorschläge zur manuellen Bestätigung, {$stats['unmatched']} offen, {$stats['duplicates']} Duplikate."
        );
    }

    public function previewBankTransactions(Request $request, Club $club): array
    {
        abort_unless(ClubPermissions::allows($club, $request->user(), ClubPermissions::FINANCE_EDIT), 403);
        $this->planFeatures->ensureAllows($club, 'bank_reconciliation');

        $data = $request->validate([
            'file' => ['required', 'file', 'max:10240'],
            'club_money_account_id' => ['nullable', 'integer'],
        ]);
        $accountId = app(ClubMoneyAccountService::class)->resolve((int) $club->id, 'bank', $request->integer('club_money_account_id') ?: null, requireSelection: true);

        $rows = [];
        $errors = [];
        $stats = [
            'total' => 0,
            'importable' => 0,
            'matched' => 0,
            'suggested' => 0,
            'unmatched' => 0,
            'duplicates' => 0,
            'invalid' => 0,
        ];

        foreach ($this->readBankTransactionRows($data['file']->getRealPath()) as $index => $rawRow) {
            $rowNumber = $index + 2;
            $stats['total']++;
            $transaction = $this->normalizeBankTransactionRow($rawRow);

            if (! $transaction || blank($transaction['booking_date']) || (float) $transaction['amount'] <= 0) {
                $stats['invalid']++;
                $errors[] = [
                    'row' => $rowNumber,
                    'reason' => __('organization.club.bank_preview_invalid_row'),
                ];

                continue;
            }

            $duplicate = app(ClubMoneyAccountService::class)->isDuplicateTransaction((int) $club->id, $transaction['transaction_hash'], $accountId);
            $match = $duplicate
                ? ['invoice' => null, 'status' => 'duplicate', 'confidence' => 100, 'reason' => __('organization.club.bank_preview_duplicate')]
                : $this->findInvoiceMatchForBankTransaction($club, $transaction);

            $stats[$match['status'] === 'duplicate' ? 'duplicates' : $match['status']]++;
            if (! $duplicate) {
                $stats['importable']++;
            }

            $iban = (string) ($transaction['debtor_iban'] ?? '');
            $rows[] = [
                'row' => $rowNumber,
                'booking_date' => $transaction['booking_date'],
                'amount' => $transaction['amount'],
                'currency' => $transaction['currency'],
                'debtor_name' => $transaction['debtor_name'],
                'debtor_iban_masked' => $iban === '' ? null : '•••• '.substr($iban, -4),
                'purpose' => $transaction['purpose'],
                'status' => $match['status'],
                'confidence' => $match['confidence'],
                'reason' => $match['reason'],
                'invoice' => $match['invoice'] ? [
                    'id' => $match['invoice']->id,
                    'number' => $match['invoice']->number,
                    'title' => $match['invoice']->title,
                    'amount' => $match['invoice']->amount,
                ] : null,
            ];
        }

        return [
            'file_name' => $data['file']->getClientOriginalName(),
            'can_import' => $stats['importable'] > 0,
            'stats' => $stats,
            'rows' => array_slice($rows, 0, 100),
            'errors' => array_slice($errors, 0, 100),
        ];
    }

    public function confirmBankTransaction(BankTransaction $bankTransaction)
    {
        abort_unless(ClubPermissions::allows($bankTransaction->club, request()->user(), ClubPermissions::FINANCE_EDIT), 403);
        $this->planFeatures->ensureAllows($bankTransaction->club, 'bank_reconciliation');

        abort_if($bankTransaction->status === 'matched', 422, 'Dieser Umsatz ist bereits zugeordnet.');
        abort_unless($bankTransaction->invoice && $bankTransaction->invoice->status !== 'paid', 422, __('organization.club.bank_transaction_no_open_invoice'));

        DB::transaction(function () use ($bankTransaction) {
            $payment = $this->recordBankMatchedPayment($bankTransaction->invoice, [
                'amount' => $bankTransaction->amount,
                'booking_date' => $bankTransaction->booking_date?->toDateString(),
                'purpose' => $bankTransaction->purpose,
                'club_money_account_id' => $bankTransaction->club_money_account_id,
            ]);

            $bankTransaction->update([
                'payment_id' => $payment->id,
                'status' => 'matched',
                'match_confidence' => max((int) $bankTransaction->match_confidence, 80),
                'match_reason' => 'Manuell bestätigt',
            ]);
        });

        return back()->with('success', __('organization.club.bank_transaction_booked'));
    }

    public function updateDatevSettings(Request $request, Club $club)
    {
        abort_unless(ClubPermissions::allows($club, $request->user(), ClubPermissions::FINANCE_EDIT), 403);
        $this->planFeatures->ensureAllows($club, 'datev_export');

        $data = $request->validate([
            'datev_consultant_number' => ['nullable', 'string', 'max:20'],
            'datev_client_number' => ['nullable', 'string', 'max:20'],
            'datev_revenue_account' => ['nullable', 'string', 'max:20'],
            'datev_bank_account' => ['nullable', 'string', 'max:20'],
            'datev_fee_account' => ['sometimes', 'nullable', 'string', 'regex:/^[0-9]{1,20}$/D'],
        ]);

        $feeSettings = [];
        if (array_key_exists('datev_fee_account', $data)) {
            if (Schema::hasColumn('clubs', 'datev_fee_account')) {
                $feeSettings['datev_fee_account'] = $data['datev_fee_account'];
            } else {
                abort_if(filled($data['datev_fee_account']), 503, __('sepa.unavailable'));
            }
        }
        $club->update([
            'datev_consultant_number' => $data['datev_consultant_number'] ?? null,
            'datev_client_number' => $data['datev_client_number'] ?? null,
            'datev_revenue_account' => $data['datev_revenue_account'] ?? null,
            'datev_bank_account' => $data['datev_bank_account'] ?? null,
            ...$feeSettings,
        ]);

        return back()->with('success', __('organization.club.datev_settings_saved'));
    }

    public function exportDatev(Request $request, Club $club)
    {
        abort_unless(ClubPermissions::allows($club, $request->user(), ClubPermissions::FINANCE_EXPORT), 403);
        $this->planFeatures->ensureAllows($club, 'datev_export');

        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $from = \Carbon\Carbon::parse($data['from'] ?? now()->startOfMonth())->startOfDay();
        $to = \Carbon\Carbon::parse($data['to'] ?? now())->endOfDay();
        $revenueAccount = $club->datev_revenue_account ?: '2110';
        $bankAccount = $club->datev_bank_account ?: '1200';

        $payments = Payment::query()
            ->where('club_id', $club->id)
            ->whereIn('status', ['paid', 'returned'])
            ->whereBetween('paid_at', [$from, $to])
            ->with([
                'invoice:id,number,title,description,source,billing_period_start,billing_period_end,business_year_period_id,contribution_year_period_id',
                'invoice.businessYearPeriod:id,name',
                'invoice.contributionYearPeriod:id,name',
                'user:id,name,email',
                'externalMember:id,name,email',
            ])
            ->orderBy('paid_at')
            ->get();

        if (Schema::hasTable('club_sepa_settlements')) {
            $returns = ClubSepaSettlement::where('club_id', $club->id)->where('status', 'returned')->whereNotNull('payment_id')
                ->whereBetween('returned_on', [$from->toDateString(), $to->toDateString()])->with(['payment.invoice', 'payment.user', 'payment.externalMember'])->get();
            foreach ($returns as $returned) {
                $entry = clone $returned->payment;
                $entry->paid_at = $returned->returned_on;
                $entry->sepa_return_reference = $returned->return_reference;
                $payments->push($entry);
            }
            $payments = $payments->sortBy('paid_at')->values();
        }
        $payments->each(fn (Payment $payment) => $payment->invoice?->loadMissing(['businessYearPeriod:id,name', 'contributionYearPeriod:id,name']));

        $fees = collect();
        if (Schema::hasColumn('club_sepa_settlements', 'fee_finance_entry_id')) {
            $fees = ClubFinanceEntry::where('club_id', $club->id)->where('type', 'expense')->where('account', 'bank')
                ->whereBetween('booked_on', [$from->toDateString(), $to->toDateString()])
                ->where(function ($query) use ($club) {
                    $query->whereIn('id', ClubSepaSettlement::where('club_id', $club->id)->whereNotNull('fee_finance_entry_id')->select('fee_finance_entry_id'));
                    if (Schema::hasTable('club_sepa_fee_corrections')) {
                        $query->orWhereIn('id', ClubSepaFeeCorrection::where('club_id', $club->id)->select('finance_entry_id'));
                    }
                })
                ->with('businessYearPeriod:id,name')
                ->orderBy('booked_on')->orderBy('id')->get();
        }
        $feeAccount = $club->datev_fee_account;
        abort_if($fees->isNotEmpty() && (! preg_match('/^[0-9]{1,20}$/D', (string) $feeAccount)
            || ltrim($feeAccount, '0') === ltrim(trim($bankAccount), '0')), 422, __('sepa.fee_export_account_required'));
        abort_if($payments->isEmpty() && $fees->isEmpty(), 422, __('organization.club.datev_no_payments'));

        $rechargeAccounts = collect();
        $rechargeInvoices = $payments->filter(fn ($payment) => $payment->invoice?->source === 'sepa_fee_recharge')->pluck('invoice_id')->unique();
        if ($rechargeInvoices->isNotEmpty()) {
            abort_unless(Schema::hasColumn('club_sepa_fee_recharges', 'invoice_id'), 422, __('sepa.recharge_account'));
            $rechargeAccounts = ClubSepaFeeRecharge::where('club_id', $club->id)->where('status', 'approved')
                ->whereIn('invoice_id', $rechargeInvoices)->pluck('revenue_account', 'invoice_id');
            foreach ($rechargeInvoices as $invoiceId) {
                $account = $rechargeAccounts->get($invoiceId);
                abort_unless(preg_match('/^[0-9]{1,20}$/D', (string) $account)
                    && ltrim($account, '0') !== ltrim(trim($bankAccount), '0'), 422, __('sepa.recharge_account'));
            }
        }

        $fileName = 'airmius-datev-'.$club->id.'-'.$from->format('Ymd').'-'.$to->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($club, $payments, $fees, $revenueAccount, $bankAccount, $feeAccount, $rechargeAccounts) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'Umsatz (ohne Soll/Haben-Kz)',
                'Soll/Haben-Kennzeichen',
                'WKZ Umsatz',
                'Konto',
                'Gegenkonto (ohne BU-Schlüssel)',
                'BU-Schlüssel',
                'Belegdatum',
                'Belegfeld 1',
                'Belegfeld 2',
                'Buchungstext',
                'KOST1',
                'KOST2',
                'Mandant',
                'Berater',
            ], ';');

            foreach ($payments as $payment) {
                $invoice = $payment->invoice;
                $isReturn = $payment->sepa_return_reference !== null;
                $businessYear = 'GJ '.($invoice?->businessYearPeriod?->name ?: 'unzugeordnet');
                $contributionYear = in_array($invoice?->source, ['recurring_contribution', 'membership_contribution'], true)
                    ? 'BJ '.($invoice?->contributionYearPeriod?->name ?: 'unzugeordnet')
                    : null;
                $bookingText = trim(implode(' ', array_filter([
                    $businessYear,
                    $contributionYear,
                    $isReturn ? 'Rücklastschrift' : ($invoice?->source === 'sepa_fee_recharge' ? 'Gebührenweiterbelastung' : 'Mitgliedsbeitrag'),
                    $invoice?->number,
                    $invoice?->title,
                    $payment->user?->name ?: $payment->externalMember?->name,
                ])));

                fputcsv($handle, [
                    number_format((float) $payment->amount, 2, ',', ''),
                    $isReturn ? 'H' : 'S',
                    'EUR',
                    $bankAccount,
                    $rechargeAccounts->get($payment->invoice_id, $revenueAccount),
                    '',
                    $payment->paid_at?->format('dm') ?? now()->format('dm'),
                    $payment->sepa_return_reference ?? $invoice?->number ?? $payment->reference ?? 'PAY-'.$payment->id,
                    $payment->paid_at?->format('Y') ?? now()->format('Y'),
                    mb_substr($bookingText, 0, 60),
                    '',
                    '',
                    $club->datev_client_number ?? '',
                    $club->datev_consultant_number ?? '',
                ], ';');
            }

            foreach ($fees as $fee) {
                $bookingText = 'GJ '.($fee->businessYearPeriod?->name ?: 'unzugeordnet').' Rücklastschriftgebühr';
                fputcsv($handle, [
                    number_format(abs((float) $fee->amount), 2, ',', ''), (float) $fee->amount < 0 ? 'S' : 'H', 'EUR',
                    $bankAccount, $feeAccount, '', $fee->booked_on->format('dm'),
                    $fee->reference, $fee->booked_on->format('Y'), mb_substr($bookingText, 0, 60),
                    '', '', $club->datev_client_number ?? '', $club->datev_consultant_number ?? '',
                ], ';');
            }
            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function recalculateFamilyGroupContributions(Club $club, string $familyGroupKey): void
    {
        $familyGroupKey = $this->normalizeFamilyGroupKey($familyGroupKey);
        if ($familyGroupKey === null) {
            return;
        }

        $calculator = app(ClubContributionCalculator::class);
        $members = $club->users()
            ->wherePivot('family_group_key', $familyGroupKey)
            ->get();

        foreach ($members as $member) {
            $membershipTypeId = $member->pivot?->club_membership_type_id;
            $preview = $calculator->resolve(
                $club,
                $member,
                $membershipTypeId ? (int) $membershipTypeId : null,
                null,
                $familyGroupKey,
            );

            if ($preview === null) {
                continue;
            }

            $club->users()->updateExistingPivot($member->id, [
                'contribution_amount' => $preview['amount'],
                'contribution_interval' => $preview['interval'] ?? 'none',
                'contribution_next_invoice_on' => null,
                'contribution_last_invoice_at' => null,
            ]);
        }

        $club->externalMembers()
            ->where('family_group_key', $familyGroupKey)
            ->get()
            ->each(function (ClubExternalMember $member) use ($club, $calculator, $familyGroupKey): void {
                $preview = $calculator->resolve($club, null, null, null, $familyGroupKey);
                if ($preview === null) {
                    return;
                }

                $member->forceFill([
                    'contribution_amount' => $preview['amount'],
                    'contribution_interval' => $preview['interval'] ?? 'none',
                    'contribution_next_invoice_on' => null,
                    'contribution_last_invoice_at' => null,
                ])->save();
            });
    }

    private function attachExistingUserToClub(Club $club, User $user, array $data): void
    {
        $roles = ClubRoles::normalize($data['role'] ?? null, $data['roles'] ?? null);

        $club->users()->syncWithoutDetaching([
            $user->id => [
                'role' => ClubRoles::primary($roles),
                'roles' => $roles,
                'membership_status' => $data['membership_status'],
                'club_membership_type_id' => $data['club_membership_type_id'] ?? null,
                'family_group_key' => $this->normalizeFamilyGroupKey($data['family_group_key'] ?? null),
                'contribution_payer_user_id' => filled($data['contribution_payer_user_id'] ?? null)
                    && (int) $data['contribution_payer_user_id'] !== (int) $user->id
                        ? (int) $data['contribution_payer_user_id']
                        : null,
                'member_number' => $data['member_number'],
                'contribution_amount' => $data['contribution_amount'],
                'contribution_interval' => $data['contribution_interval'] ?? 'none',
                'contribution_next_invoice_on' => $this->normalizedNextInvoiceDate($data),
                'contribution_last_invoice_at' => null,
                'sepa_iban' => $this->normalizeIban($data['sepa_iban'] ?? null),
                'sepa_bic' => $this->normalizeBic($data['sepa_bic'] ?? null),
                'sepa_mandate_reference' => $data['sepa_mandate_reference'] ?? null,
                'sepa_mandate_signed_on' => $data['sepa_mandate_signed_on'] ?? null,
                'sepa_mandate_active' => (bool) ($data['sepa_mandate_active'] ?? false),
                'joined_on' => $data['joined_on'] ?? now()->toDateString(),
                'membership_ends_on' => $data['membership_ends_on'] ?? null,
                'membership_end_notified_at' => null,
                'membership_notes' => $data['membership_notes'],
            ],
        ]);

        if (filled($data['athlete_license_number']) || filled($data['athlete_license_valid_until'] ?? null)) {
            $user->forceFill([
                'athlete_license_number' => $data['athlete_license_number'],
                'athlete_license_valid_until' => $data['athlete_license_valid_until'] ?? null,
            ])->save();
        }

        $contactUpdates = collect(['phone', 'country', 'street', 'house_number', 'postal_code', 'city'])
            ->filter(fn (string $field) => blank($user->getAttribute($field)) && filled($data[$field] ?? null))
            ->mapWithKeys(fn (string $field) => [$field => $data[$field]])
            ->all();
        if ($contactUpdates !== []) {
            $user->forceFill($contactUpdates)->save();
        }

        ClubExternalMember::query()
            ->where('club_id', $club->id)
            ->where('email', strtolower($user->email))
            ->delete();

        if (filled($data['family_group_key'] ?? null)) {
            $this->recalculateFamilyGroupContributions($club, (string) $data['family_group_key']);
        }

        AppNotification::sendLocalized(
            $user,
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
    }

    private function notifyClubManagersLocalized(
        Club $club,
        string $type,
        string $titleKey,
        ?string $bodyKey,
        array $replace,
        array $data,
        string $permission,
        ?int $exceptUserId = null,
    ): void {
        $club->users()
            ->when($exceptUserId, fn ($query) => $query->where('users.id', '!=', $exceptUserId))
            ->get(['users.id', 'users.language'])
            ->filter(fn (User $manager) => ClubPermissions::allows($club, $manager, $permission))
            ->each(fn (User $manager) => AppNotification::sendLocalized(
                $manager,
                $type,
                $titleKey,
                $bodyKey,
                $replace,
                $data,
            ));
    }

    private function recordBankMatchedPayment(Invoice $invoice, array $transaction): Payment
    {
        $payment = app(ClubInvoicePaymentService::class)->record($invoice, [
            'amount' => $transaction['amount'] ?? null,
            'method' => 'bank_import',
            'club_money_account_id' => $transaction['club_money_account_id'] ?? null,
            'reference' => $transaction['purpose'] ?? null,
            'paid_at' => $transaction['booking_date'] ?? now(),
            'notes' => 'Automatisch per Bankabgleich zugeordnet.',
        ], request()->user());

        if ($invoice->user_id) {
            AppNotification::sendLocalized(
                (int) $invoice->user_id,
                'invoice.paid',
                'organization.notifications.payment_received_title',
                'organization.notifications.payment_received_body',
                ['invoice' => $invoice->number],
                [
                    'url' => route('auth.settings'),
                    'invoice_id' => $invoice->id,
                ],
            );
        }

        return $payment;
    }

    private function bankTransactionImportStatus(array $match): string
    {
        if ($match['invoice'] && in_array($match['status'], ['matched', 'suggested'], true)) {
            return 'suggested';
        }

        return 'unmatched';
    }

    private function readBankTransactionRows(string $path): array
    {
        $handle = fopen($path, 'r');

        if (! $handle) {
            return [];
        }

        $firstLine = fgets($handle) ?: '';
        rewind($handle);
        $delimiter = str_contains($firstLine, ';') ? ';' : (str_contains($firstLine, "\t") ? "\t" : ',');
        $tableRows = [];

        while (($values = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($values === [null] || $values === false) {
                continue;
            }

            $tableRows[] = $values;
        }

        fclose($handle);

        return $this->normalizeImportTableRows($tableRows);
    }

    private function normalizeBankTransactionRow(array $row): ?array
    {
        $amount = $this->normalizeMoney(
            $row['betrag'] ?? $row['amount'] ?? $row['umsatz'] ?? $row['wert'] ?? null
        );

        if ($amount === null) {
            return null;
        }

        $purpose = trim((string) (
            $row['verwendungszweck']
            ?? $row['purpose']
            ?? $row['referenz']
            ?? $row['reference']
            ?? $row['buchungstext']
            ?? ''
        ));
        $debtorName = trim((string) (
            $row['auftraggeber']
            ?? $row['zahler']
            ?? $row['name']
            ?? $row['debtor_name']
            ?? ''
        ));
        $debtorIban = $this->normalizeIban($row['iban'] ?? $row['debtor_iban'] ?? $row['konto'] ?? null);
        $bookingDate = $this->normalizeImportDate($row['datum'] ?? $row['date'] ?? $row['buchungstag'] ?? $row['booking_date'] ?? null);
        $currency = strtoupper(trim((string) ($row['währung'] ?? $row['waehrung'] ?? $row['currency'] ?? 'EUR'))) ?: 'EUR';

        $hashPayload = implode('|', [
            $bookingDate,
            number_format((float) $amount, 2, '.', ''),
            $currency,
            $debtorName,
            $debtorIban,
            $purpose,
        ]);

        return [
            'transaction_hash' => hash('sha256', $hashPayload),
            'booking_date' => $bookingDate,
            'amount' => $amount,
            'currency' => $currency,
            'debtor_name' => $debtorName ?: null,
            'debtor_iban' => $debtorIban,
            'purpose' => $purpose ?: null,
            'raw_data' => $row,
        ];
    }

    private function findInvoiceMatchForBankTransaction(Club $club, array $transaction): array
    {
        $openInvoices = Invoice::query()
            ->where('club_id', $club->id)
            ->whereIn('status', ['open', 'overdue'])
            ->whereNotNull('user_id')
            ->with('user:id,name,email')
            ->withSum('settledPayments', 'amount')
            ->get();

        $purpose = strtoupper((string) ($transaction['purpose'] ?? ''));
        $amount = round((float) $transaction['amount'], 2);

        $numberMatch = $openInvoices->first(fn (Invoice $invoice) => str_contains($purpose, strtoupper($invoice->number))
            && $invoice->outstandingCents() === (int) round($amount * 100));

        if ($numberMatch) {
            return [
                'invoice' => $numberMatch,
                'status' => 'matched',
                'confidence' => 100,
                'reason' => 'Rechnungsnummer und Betrag stimmen Überein.',
            ];
        }

        $sameAmountInvoices = $openInvoices
            ->filter(fn (Invoice $invoice) => $invoice->outstandingCents() === (int) round($amount * 100))
            ->values();

        if ($sameAmountInvoices->count() === 1) {
            $invoice = $sameAmountInvoices->first();
            $membership = DB::table('club_user')
                ->where('club_id', $club->id)
                ->where('user_id', $invoice->user_id)
                ->first();

            if ($membership && $transaction['debtor_iban'] && $this->normalizeIban($membership->sepa_iban ?? null) === $transaction['debtor_iban']) {
                return [
                    'invoice' => $invoice,
                    'status' => 'matched',
                    'confidence' => 95,
                    'reason' => 'Betrag und IBAN stimmen Überein.',
                ];
            }

            if ($invoice->user && $this->nameLooksSimilar($invoice->user->name, $transaction['debtor_name'] ?? '')) {
                return [
                    'invoice' => $invoice,
                    'status' => 'suggested',
                    'confidence' => 75,
                    'reason' => 'Betrag stimmt, Name wirkt passend.',
                ];
            }
        }

        return [
            'invoice' => null,
            'status' => 'unmatched',
            'confidence' => 0,
            'reason' => 'Keine eindeutige offene Rechnung gefunden.',
        ];
    }

    private function nameLooksSimilar(?string $expected, ?string $actual): bool
    {
        $expected = strtolower(preg_replace('/[^a-z0-9]+/i', '', (string) $expected));
        $actual = strtolower(preg_replace('/[^a-z0-9]+/i', '', (string) $actual));

        return $expected !== '' && $actual !== '' && (str_contains($actual, $expected) || str_contains($expected, $actual));
    }

    private function storeEmailMemberData(Club $club, User $creator, array $data, bool $sendInvitation): string
    {
        $email = strtolower(trim((string) $data['email']));
        $roles = ClubRoles::normalize($data['role'] ?? 'member', [$data['role'] ?? 'member']);
        $invitationExpiresAt = $this->invitationExpiresAt($data['invitation_expires_at'] ?? null);
        $alreadyTracked = $club->users()->where('users.email', $email)->exists()
            || $club->externalMembers()->where('email', $email)->exists();

        if (! $alreadyTracked) {
            $this->ensureClubCanAddMembers($club);
        }

        $memberData = [
            'name' => trim((string) ($data['name'] ?? '')) ?: null,
            'email' => $email,
            'phone' => trim((string) ($data['phone'] ?? '')) ?: null,
            'country' => strtoupper(trim((string) ($data['country'] ?? ''))) ?: null,
            'street' => trim((string) ($data['street'] ?? '')) ?: null,
            'house_number' => trim((string) ($data['house_number'] ?? '')) ?: null,
            'postal_code' => trim((string) ($data['postal_code'] ?? '')) ?: null,
            'city' => trim((string) ($data['city'] ?? '')) ?: null,
            'role' => ClubRoles::primary($roles),
            'roles' => $roles,
            'membership_status' => $data['membership_status'] ?? 'active',
            'club_membership_type_id' => $data['club_membership_type_id'] ?? null,
            'family_group_key' => $this->normalizeFamilyGroupKey($data['family_group_key'] ?? null),
            'contribution_payer_user_id' => filled($data['contribution_payer_user_id'] ?? null)
                ? (int) $data['contribution_payer_user_id']
                : null,
            'member_number' => trim((string) ($data['member_number'] ?? '')) ?: null,
            'athlete_license_number' => trim((string) ($data['athlete_license_number'] ?? '')) ?: null,
            'athlete_license_valid_until' => $data['athlete_license_valid_until'] ?? null,
            'contribution_amount' => $data['contribution_amount'] ?? null,
            'contribution_interval' => $data['contribution_interval'] ?? 'none',
            'payment_method' => $data['payment_method'] ?? 'bank_transfer',
            'contribution_next_invoice_on' => $this->normalizedNextInvoiceDate($data),
            'sepa_iban' => $this->normalizeIban($data['sepa_iban'] ?? null),
            'sepa_bic' => $this->normalizeBic($data['sepa_bic'] ?? null),
            'sepa_mandate_reference' => $data['sepa_mandate_reference'] ?? null,
            'sepa_mandate_signed_on' => $data['sepa_mandate_signed_on'] ?? null,
            'sepa_mandate_active' => (bool) ($data['sepa_mandate_active'] ?? false),
            'joined_on' => now()->toDateString(),
            'membership_ends_on' => $data['membership_ends_on'] ?? null,
            'membership_notes' => null,
        ];

        $existingUser = User::query()->where('email', $email)->first();

        $externalMember = ClubExternalMember::updateOrCreate(
            [
                'club_id' => $club->id,
                'email' => $email,
            ],
            [
                'created_by' => $creator->id,
                'name' => $memberData['name'],
                'phone' => $memberData['phone'],
                'country' => $memberData['country'],
                'street' => $memberData['street'],
                'house_number' => $memberData['house_number'],
                'postal_code' => $memberData['postal_code'],
                'city' => $memberData['city'],
                'role' => $memberData['role'],
                'membership_status' => $memberData['membership_status'],
                'club_membership_type_id' => $memberData['club_membership_type_id'],
                'family_group_key' => $memberData['family_group_key'],
                'contribution_payer_user_id' => $memberData['contribution_payer_user_id'],
                'member_number' => $memberData['member_number'],
                'athlete_license_number' => $memberData['athlete_license_number'],
                'athlete_license_valid_until' => $memberData['athlete_license_valid_until'],
                'contribution_amount' => $memberData['contribution_amount'],
                'contribution_interval' => $memberData['contribution_interval'],
                'payment_method' => $memberData['payment_method'],
                'contribution_next_invoice_on' => $memberData['contribution_next_invoice_on'],
                'contribution_last_invoice_at' => null,
                'sepa_iban' => $memberData['sepa_iban'],
                'sepa_bic' => $memberData['sepa_bic'],
                'sepa_mandate_reference' => $memberData['sepa_mandate_reference'],
                'sepa_mandate_signed_on' => $memberData['sepa_mandate_signed_on'],
                'sepa_mandate_active' => $memberData['sepa_mandate_active'],
                'joined_on' => $memberData['joined_on'],
                'membership_ends_on' => $memberData['membership_ends_on'],
                'membership_end_notified_at' => null,
                'invitation_status' => 'none',
                'invitation_token' => null,
                'invited_at' => null,
                'invitation_expires_at' => null,
            ],
        );

        if (filled($memberData['family_group_key'] ?? null)) {
            $this->recalculateFamilyGroupContributions($club, (string) $memberData['family_group_key']);
        }

        if ($sendInvitation) {
            $externalMember->issueInvitation($invitationExpiresAt);
            $this->sendExternalMemberInvitation($externalMember, $existingUser);

            return 'invited';
        }

        return 'stored';
    }

    private function sendExternalMemberInvitation(ClubExternalMember $externalMember, ?User $existingUser = null): void
    {
        $externalMember->loadMissing('club', 'creator');

        Notification::route('mail', $externalMember->email)
            ->notify(new ExternalClubMembershipInvitation($externalMember));

        if (! $existingUser) {
            return;
        }

        $webUrl = route('auth.club-member-invitations.accept', $externalMember->invitation_token);
        $mobileUrl = 'airmius://club-member-invitations/'.$externalMember->invitation_token;

        AppNotification::sendLocalized(
            $existingUser,
            'club.membership_invitation',
            'organization.notifications.membership_invitation_title',
            'organization.notifications.membership_invitation_body',
            ['club' => $externalMember->club->name],
            [
                'url' => $webUrl,
                'action_url' => $webUrl,
                'mobile_url' => $mobileUrl,
                'deep_link' => $mobileUrl,
                'club_id' => $externalMember->club_id,
                'invitation_token' => $externalMember->invitation_token,
                'invitation_expires_at' => LocalDateTime::format($externalMember->invitation_expires_at, $existingUser),
                'invitation_expires_at_timezone' => LocalDateTime::timezoneFor($existingUser),
            ],
        );
    }

    private function newEmailMemberCount(Club $club, array $members): int
    {
        $emails = collect($members)
            ->map(fn (array $member) => strtolower(trim((string) ($member['email'] ?? ''))))
            ->filter()
            ->unique()
            ->values();

        if ($emails->isEmpty()) {
            return 0;
        }

        $existingUserEmails = $club->users()
            ->whereIn('users.email', $emails)
            ->pluck('users.email')
            ->map(fn (string $email) => strtolower($email));

        $existingExternalEmails = $club->externalMembers()
            ->whereIn('email', $emails)
            ->pluck('email')
            ->map(fn (string $email) => strtolower($email));

        return $emails
            ->diff($existingUserEmails)
            ->diff($existingExternalEmails)
            ->count();
    }

    private function ensureClubCanAddMembers(Club $club, int $amount = 1): void
    {
        $plan = $club->subscriptionPlan();
        $limit = $plan?->member_limit;

        if (! $club->canAddMembers($amount)) {
            throw ValidationException::withMessages([
                'general' => 'Das Mitgliederlimit des aktuellen Plans'.($plan ? ' '.$plan->name : '').' ist erreicht'.($limit ? " ({$limit} Mitglieder)." : '.'),
            ]);
        }
    }

    private function readMembershipImportRows(
        string $path,
        ?string $extension,
        array $mapping = [],
        array &$meta = [],
    ): array {
        $extension = strtolower((string) $extension);

        if ($extension === 'xlsx') {
            return $this->readXlsxRows($path, $mapping, $meta);
        }

        return $this->readCsvRows($path, $mapping, $meta);
    }

    private function readCsvRows(string $path, array $mapping = [], array &$meta = []): array
    {
        $handle = fopen($path, 'r');

        if (! $handle) {
            return [];
        }

        $firstLine = fgets($handle) ?: '';
        rewind($handle);
        $delimiter = str_contains($firstLine, ';') ? ';' : (str_contains($firstLine, "\t") ? "\t" : ',');
        $tableRows = [];

        while (($values = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($values === [null] || $values === false) {
                continue;
            }

            $tableRows[] = $values;
        }

        fclose($handle);

        return $this->normalizeImportTableRows($tableRows, $mapping, $meta);
    }

    private function readXlsxRows(string $path, array $mapping = [], array &$meta = []): array
    {
        $zip = new \ZipArchive;

        if ($zip->open($path) !== true) {
            return [];
        }

        $sharedStrings = [];
        $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');

        if ($sharedStringsXml !== false) {
            $xml = simplexml_load_string($sharedStringsXml);
            foreach ($xml->si ?? [] as $string) {
                if (isset($string->t)) {
                    $sharedStrings[] = (string) $string->t;

                    continue;
                }

                $text = '';
                foreach ($string->r ?? [] as $run) {
                    $text .= (string) $run->t;
                }
                $sharedStrings[] = $text;
            }
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if ($sheetXml === false) {
            return [];
        }

        $xml = simplexml_load_string($sheetXml);
        $tableRows = [];

        foreach ($xml->sheetData->row ?? [] as $row) {
            $values = [];

            foreach ($row->c ?? [] as $cell) {
                $reference = (string) $cell['r'];
                $column = preg_replace('/\d+/', '', $reference);
                $index = $this->excelColumnIndex($column);
                $type = (string) $cell['t'];
                $value = (string) ($cell->v ?? '');

                if ($type === 's') {
                    $value = $sharedStrings[(int) $value] ?? '';
                } elseif ($type === 'inlineStr') {
                    $value = (string) ($cell->is->t ?? '');
                }

                $values[$index] = $value;
            }

            if ($values !== []) {
                ksort($values);
                $tableRows[] = $values;
            }
        }

        if ($tableRows === []) {
            return [];
        }

        return $this->normalizeImportTableRows($tableRows, $mapping, $meta);
    }

    private function normalizeImportTableRows(array $tableRows, array $mapping = [], array &$meta = []): array
    {
        $headerIndex = null;
        $headers = [];

        foreach ($tableRows as $index => $values) {
            $candidate = array_map(fn ($value) => $this->normalizeImportKey($value), $values);

            $known = array_filter(array_map(fn (string $header) => $this->importFieldForHeader($header), $candidate));
            if ($known !== [] || ($index === 0 && count(array_filter($candidate)) >= 2)) {
                $headerIndex = $index;
                $headers = $candidate;
                break;
            }
        }

        if ($headerIndex === null) {
            return [];
        }

        $suggestedMapping = [];
        foreach ($headers as $index => $header) {
            $field = $this->importFieldForHeader($header);
            if ($field !== null && ! array_key_exists($field, $suggestedMapping)) {
                $suggestedMapping[$field] = $index;
            }
        }

        $resolvedMapping = array_replace($suggestedMapping, $mapping);
        $meta = [
            'columns' => array_values($headers),
            'mapping' => $resolvedMapping,
        ];
        $rows = [];

        foreach (array_slice($tableRows, $headerIndex + 1, null, true) as $sourceIndex => $values) {
            $row = [];
            foreach ($headers as $index => $header) {
                if ($header !== '') {
                    $row[$header === 'e_mail' ? 'email' : $header] = $values[$index] ?? null;
                }
            }

            foreach ($resolvedMapping as $field => $columnIndex) {
                $row[$field] = $values[(int) $columnIndex] ?? null;
            }

            if (array_filter($row, fn ($value) => filled($value))) {
                $row['__row_number'] = $sourceIndex + 1;
                $rows[] = $row;
            }
        }

        return $rows;
    }

    private function importMappingFromRequest(Request $request): array
    {
        $raw = $request->input('mapping');
        if (is_string($raw) && trim($raw) !== '') {
            $raw = json_decode($raw, true);
            abort_unless(is_array($raw), 422, 'Die Import-Zuordnung ist ungültig.');
        }

        if (! is_array($raw)) {
            return [];
        }

        return collect($raw)
            ->filter(fn ($column, $field) => in_array((string) $field, self::IMPORT_FIELDS, true)
                && is_numeric($column)
                && (int) $column >= 0)
            ->mapWithKeys(fn ($column, $field) => [(string) $field => (int) $column])
            ->all();
    }

    private function importFieldForHeader(string $header): ?string
    {
        return match ($header) {
            'name', 'vollname', 'mitgliedsname', 'kontaktname' => 'name',
            'email', 'e_mail', 'mail', 'emailadresse', 'mailadresse' => 'email',
            'mitgliedschaft', 'membership_status', 'status' => 'membership_status',
            'familiengruppe', 'family_group_key', 'family_group', 'haushaltsgruppe' => 'family_group_key',
            'mitgliedsnummer', 'member_number', 'mitgliedernummer' => 'member_number',
            'lizenznummer', 'athlete_license_number', 'athletenlizenz' => 'athlete_license_number',
            'lizenz_gueltig_bis', 'lizenzgültigbis', 'athlete_license_valid_until' => 'athlete_license_valid_until',
            'beitrag', 'contribution_amount', 'betrag' => 'contribution_amount',
            'intervall', 'contribution_interval', 'zahlungsintervall' => 'contribution_interval',
            'naechste_rechnung', 'naechsten_rechnung', 'contribution_next_invoice_on' => 'contribution_next_invoice_on',
            'iban', 'sepa_iban' => 'sepa_iban',
            'bic', 'sepa_bic' => 'sepa_bic',
            'mandatsreferenz', 'sepa_mandate_reference' => 'sepa_mandate_reference',
            'mandatsdatum', 'sepa_mandate_signed_on' => 'sepa_mandate_signed_on',
            'sepa_aktiv', 'sepa_mandate_active' => 'sepa_mandate_active',
            'eintritt', 'joined_on' => 'joined_on',
            'ende', 'membership_ends_on' => 'membership_ends_on',
            'notiz', 'membership_notes', 'bemerkung' => 'membership_notes',
            default => null,
        };
    }

    private function normalizeFamilyGroupKey(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '' || ! preg_match('/^[A-Za-z0-9._-]+$/', $value)) {
            return null;
        }

        return mb_strtolower($value);
    }

    private function buildMembershipImportTemplate(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'airmius-members-').'.xlsx';
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/drawings/drawing1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/>
  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
  <Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>
</Types>
XML);
        $zip->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
  <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>
</Relationships>
XML);
        $zip->addFromString('docProps/core.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/"><dc:title>Airmius Mitgliederimport</dc:title></cp:coreProperties>');
        $zip->addFromString('docProps/app.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties"><Application>Airmius</Application></Properties>');
        $zip->addFromString('xl/workbook.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets><sheet name="Mitgliederimport" sheetId="1" r:id="rId1"/></sheets>
</workbook>
XML);
        $zip->addFromString('xl/_rels/workbook.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>
XML);
        $zip->addFromString('xl/styles.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="3"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="16"/><color rgb="FF000000"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts>
  <fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF111827"/><bgColor indexed="64"/></patternFill></fill></fills>
  <borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>
  <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
  <cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf><xf numFmtId="0" fontId="2" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs>
</styleSheet>
XML);

        $sheetRows = [
            ['Airmius Mitgliederimport'],
            ['Füllen Sie ab Zeile 5 die Mitglieder aus. Pflichtfeld ist E-Mail. Gleicher Familiengruppen-Schlüssel verbindet aktive Mitglieder für automatische Familienbeiträge. Mitgliedschaft: active, non_member, pending, paused, former. Intervall: none, monthly, quarterly, four_monthly, semi_yearly, yearly, once. SEPA aktiv: ja/nein.'],
            [],
            ['Name', 'E-Mail', 'Familiengruppe', 'Mitgliedschaft', 'Mitgliedsnummer', 'Lizenznummer', 'Lizenz_Gueltig_Bis', 'Beitrag', 'Intervall', 'Nächste_Rechnung', 'IBAN', 'BIC', 'Mandatsreferenz', 'Mandatsdatum', 'SEPA_Aktiv', 'Eintritt', 'Ende', 'Notiz'],
            ['Max Mustermann', 'max@example.org', 'family-7', 'active', 'MV-1001', 'LIC-2026-001', '12,50', 'monthly', '2026-06-01', 'DE02120300000000202051', '', 'MANDAT-1001', '2026-05-02', 'ja', '2026-05-02', '2027-05-01', 'Beispielzeile entfernen'],
        ];

        $sheetXml = $this->buildTemplateSheetXml($sheetRows);
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        $zip->addFromString('xl/worksheets/_rels/sheet1.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" Target="../drawings/drawing1.xml"/>
</Relationships>
XML);
        $zip->addFromString('xl/drawings/drawing1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">
  <xdr:oneCellAnchor>
    <xdr:from><xdr:col>7</xdr:col><xdr:colOff>0</xdr:colOff><xdr:row>0</xdr:row><xdr:rowOff>0</xdr:rowOff></xdr:from>
    <xdr:ext cx="1600000" cy="520000"/>
    <xdr:pic>
      <xdr:nvPicPr><xdr:cNvPr id="2" name="Airmius Logo"/><xdr:cNvPicPr/></xdr:nvPicPr>
      <xdr:blipFill><a:blip xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" r:embed="rId1"/><a:stretch><a:fillRect/></a:stretch></xdr:blipFill>
      <xdr:spPr><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></xdr:spPr>
    </xdr:pic>
    <xdr:clientData/>
  </xdr:oneCellAnchor>
</xdr:wsDr>
XML);
        $zip->addFromString('xl/drawings/_rels/drawing1.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/airmius-logo.png"/>
</Relationships>
XML);

        $logoPath = public_path('img/logo/Logo-Airmius-Quervormat.png');
        if (is_file($logoPath)) {
            $zip->addFile($logoPath, 'xl/media/airmius-logo.png');
        } else {
            $zip->addFromString('xl/media/airmius-logo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII='));
        }

        $zip->close();

        return $path;
    }

    private function buildTemplateSheetXml(array $rows): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
        $xml .= '<cols><col min="1" max="1" width="24" customWidth="1"/><col min="2" max="2" width="30" customWidth="1"/><col min="3" max="16" width="18" customWidth="1"/></cols>';
        $xml .= '<sheetData>';

        foreach ($rows as $rowIndex => $row) {
            $number = $rowIndex + 1;
            $height = $number === 1 ? ' ht="54" customHeight="1"' : '';
            $xml .= '<row r="'.$number.'"'.$height.'>';

            foreach ($row as $columnIndex => $value) {
                $cell = $this->excelColumnName($columnIndex).$number;
                $style = $number === 1 ? 1 : ($number === 4 ? 2 : 0);
                $xml .= '<c r="'.$cell.'" t="inlineStr" s="'.$style.'"><is><t>'.htmlspecialchars((string) $value, ENT_XML1).'</t></is></c>';
            }

            $xml .= '</row>';
        }

        $xml .= '</sheetData><mergeCells count="2"><mergeCell ref="A1:Q1"/><mergeCell ref="A2:Q2"/></mergeCells><dataValidations count="3"><dataValidation type="list" allowBlank="1" showDropDown="0" sqref="D5:D1000"><formula1>"active,non_member,pending,paused,former"</formula1></dataValidation><dataValidation type="list" allowBlank="1" showDropDown="0" sqref="H5:H1000"><formula1>"none,monthly,quarterly,four_monthly,semi_yearly,yearly,once"</formula1></dataValidation><dataValidation type="list" allowBlank="1" showDropDown="0" sqref="N5:N1000"><formula1>"ja,nein"</formula1></dataValidation></dataValidations><drawing r:id="rId1"/></worksheet>';

        return $xml;
    }

    private function normalizeImportKey(mixed $value): string
    {
        return ClubMembershipInput::normalizeKey($value);
    }

    private function normalizeMembershipStatus(mixed $value): string
    {
        return ClubMembershipInput::normalizeMembershipStatus($value);
    }

    private function normalizeContributionInterval(mixed $value): string
    {
        return ClubMembershipInput::normalizeContributionInterval($value);
    }

    private function normalizeMoney(mixed $value): ?float
    {
        return ClubMembershipInput::normalizeMoney($value);
    }

    private function normalizeImportDate(mixed $value): ?string
    {
        return ClubMembershipInput::normalizeDate($value);
    }

    private function invitationExpiresAt(?string $date): ?Carbon
    {
        return filled($date) ? Carbon::parse($date)->endOfDay() : null;
    }

    private function normalizedNextInvoiceDate(array $data): ?string
    {
        return ClubMembershipInput::normalizedNextInvoiceDate($data);
    }

    private function storeMembershipApplicationDocumentUploads(Request $request, Club $club, array $documents): array
    {
        foreach ($documents as $index => $document) {
            $uploadedFile = $request->file("membership_application_documents.$index.file");

            if (! $uploadedFile) {
                continue;
            }

            $this->planFeatures->ensureCanStoreFile($club, $uploadedFile);

            $folder = Folder::query()->firstOrCreate([
                'user_id' => null,
                'club_id' => $club->id,
                'team_id' => null,
                'event_id' => null,
                'parent_id' => null,
                'name' => 'Mitgliedsantrag',
            ]);

            $file = $this->fileService->upload($request->user(), $uploadedFile, [
                'club_id' => $club->id,
                'team_id' => null,
                'event_id' => null,
                'folder_id' => $folder->id,
            ]);

            $documents[$index]['file_id'] = $file->id;
            $documents[$index]['file_name'] = $file->display_name;
            $documents[$index]['url'] = route('auth.files.download', $file);
            $documents[$index]['title'] = filled($document['title'] ?? null)
                ? $document['title']
                : $file->display_name;
        }

        return $documents;
    }

    private function matchingContributionRule(Club $club, User $user, ?int $membershipTypeId): ?ClubContributionRule
    {
        $age = $user->birth_date?->age;

        return $club->contributionRules()
            ->effectiveOn(now()->toDateString())
            ->when($membershipTypeId, fn ($query) => $query->where(function ($query) use ($membershipTypeId) {
                $query->where('club_membership_type_id', $membershipTypeId)->orWhereNull('club_membership_type_id');
            }))
            ->when(! $membershipTypeId, fn ($query) => $query->whereNull('club_membership_type_id'))
            ->where(function ($query) use ($age) {
                $query->whereNull('age_min')->when($age !== null, fn ($query) => $query->orWhere('age_min', '<=', $age));
            })
            ->where(function ($query) use ($age) {
                $query->whereNull('age_max')->when($age !== null, fn ($query) => $query->orWhere('age_max', '>=', $age));
            })
            ->orderByRaw('club_membership_type_id is null')
            ->orderByDesc('valid_from')
            ->first();
    }

    private function normalizeIban(mixed $value): ?string
    {
        return ClubMembershipInput::normalizeIban($value);
    }

    private function normalizeBic(mixed $value): ?string
    {
        return ClubMembershipInput::normalizeBic($value);
    }

    private function normalizeBoolean(mixed $value): bool
    {
        return ClubMembershipInput::normalizeBoolean($value);
    }

    private function buildSepaDebitXml(Club $club, $invoices, $memberships): string
    {
        $messageId = 'AIRMIUS-'.$club->id.'-'.now()->format('YmdHis');
        $paymentId = $messageId.'-PMT';
        $controlSum = $invoices->sum(fn (Invoice $invoice) => ($invoice->outstandingCents() / 100));
        $collectionDate = now()->addDays(3)->toDateString();
        $creditorName = $club->sepa_account_holder ?: $club->name;

        $xml = new \XMLWriter;
        $xml->openMemory();
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('Document');
        $xml->writeAttribute('xmlns', 'urn:iso:std:iso:20022:tech:xsd:pain.008.001.02');
        $xml->writeAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $xml->startElement('CstmrDrctDbtInitn');

        $xml->startElement('GrpHdr');
        $xml->writeElement('MsgId', $messageId);
        $xml->writeElement('CreDtTm', now()->toIso8601String());
        $xml->writeElement('NbOfTxs', (string) $invoices->count());
        $xml->writeElement('CtrlSum', number_format($controlSum, 2, '.', ''));
        $xml->startElement('InitgPty');
        $xml->writeElement('Nm', $creditorName);
        $xml->endElement();
        $xml->endElement();

        $xml->startElement('PmtInf');
        $xml->writeElement('PmtInfId', $paymentId);
        $xml->writeElement('PmtMtd', 'DD');
        $xml->writeElement('BtchBookg', 'true');
        $xml->writeElement('NbOfTxs', (string) $invoices->count());
        $xml->writeElement('CtrlSum', number_format($controlSum, 2, '.', ''));
        $xml->startElement('PmtTpInf');
        $xml->startElement('SvcLvl');
        $xml->writeElement('Cd', 'SEPA');
        $xml->endElement();
        $xml->startElement('LclInstrm');
        $xml->writeElement('Cd', 'CORE');
        $xml->endElement();
        $xml->writeElement('SeqTp', 'RCUR');
        $xml->endElement();
        $xml->writeElement('ReqdColltnDt', $collectionDate);
        $xml->startElement('Cdtr');
        $xml->writeElement('Nm', $creditorName);
        $xml->endElement();
        $xml->startElement('CdtrAcct');
        $xml->startElement('Id');
        $xml->writeElement('IBAN', $this->normalizeIban($club->sepa_iban));
        $xml->endElement();
        $xml->endElement();
        $xml->startElement('CdtrAgt');
        $xml->startElement('FinInstnId');
        $this->writeSepaFinancialInstitution($xml, $club->sepa_bic);
        $xml->endElement();
        $xml->endElement();
        $xml->writeElement('ChrgBr', 'SLEV');
        $xml->startElement('CdtrSchmeId');
        $xml->startElement('Id');
        $xml->startElement('PrvtId');
        $xml->startElement('Othr');
        $xml->writeElement('Id', $club->sepa_creditor_id);
        $xml->startElement('SchmeNm');
        $xml->writeElement('Prtry', 'SEPA');
        $xml->endElement();
        $xml->endElement();
        $xml->endElement();
        $xml->endElement();
        $xml->endElement();

        foreach ($invoices as $invoice) {
            $membership = $memberships->get($invoice->user_id);
            $debtorName = $invoice->user?->name ?: $invoice->user?->email ?: 'Mitglied '.$invoice->user_id;

            $xml->startElement('DrctDbtTxInf');
            $xml->startElement('PmtId');
            $xml->writeElement('EndToEndId', $invoice->number);
            $xml->endElement();
            $xml->startElement('InstdAmt');
            $xml->writeAttribute('Ccy', 'EUR');
            $xml->text(number_format(($invoice->outstandingCents() / 100), 2, '.', ''));
            $xml->endElement();
            $xml->startElement('DrctDbtTx');
            $xml->startElement('MndtRltdInf');
            $xml->writeElement('MndtId', $membership->sepa_mandate_reference);
            $xml->writeElement('DtOfSgntr', $membership->sepa_mandate_signed_on);
            $xml->endElement();
            $xml->endElement();
            $xml->startElement('DbtrAgt');
            $xml->startElement('FinInstnId');
            $this->writeSepaFinancialInstitution($xml, $membership->sepa_bic);
            $xml->endElement();
            $xml->endElement();
            $xml->startElement('Dbtr');
            $xml->writeElement('Nm', $debtorName);
            $xml->endElement();
            $xml->startElement('DbtrAcct');
            $xml->startElement('Id');
            $xml->writeElement('IBAN', $this->normalizeIban($membership->sepa_iban));
            $xml->endElement();
            $xml->endElement();
            $xml->startElement('RmtInf');
            $xml->writeElement('Ustrd', trim($invoice->number.' '.$invoice->title));
            $xml->endElement();
            $xml->endElement();
        }

        $xml->endElement();
        $xml->endElement();
        $xml->endElement();
        $xml->endDocument();

        return $xml->outputMemory();
    }

    private function writeSepaFinancialInstitution(\XMLWriter $xml, mixed $bic): void
    {
        $bic = $this->normalizeBic($bic);

        if ($bic) {
            $xml->writeElement('BIC', $bic);

            return;
        }

        $xml->startElement('Othr');
        $xml->writeElement('Id', 'NOTPROVIDED');
        $xml->endElement();
    }

    private function excelColumnIndex(string $column): int
    {
        $index = 0;
        foreach (str_split($column) as $char) {
            $index = ($index * 26) + (ord(strtoupper($char)) - 64);
        }

        return max(0, $index - 1);
    }

    private function excelColumnName(int $index): string
    {
        $name = '';
        $index++;

        while ($index > 0) {
            $modulo = ($index - 1) % 26;
            $name = chr(65 + $modulo).$name;
            $index = intdiv($index - $modulo, 26);
        }

        return $name;
    }

    private function nextInvoiceNumber(Club $club): string
    {
        $next = Invoice::query()
            ->where('club_id', $club->id)
            ->whereYear('created_at', now()->year)
            ->count() + 1;

        return 'AIR-'.$club->id.'-'.now()->format('Y').'-'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    private function nextMemberNumber(Club $club): string
    {
        $next = DB::table('club_user')
            ->where('club_id', $club->id)
            ->whereNotNull('member_number')
            ->count() + 1;

        do {
            $number = 'M-'.$club->id.'-'.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
            $exists = DB::table('club_user')
                ->where('club_id', $club->id)
                ->where('member_number', $number)
                ->exists();
            $next++;
        } while ($exists);

        return $number;
    }
}

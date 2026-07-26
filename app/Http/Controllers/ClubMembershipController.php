<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\BankTransaction;
use App\Models\ClubContributionRule;
use App\Models\ClubExternalMember;
use App\Models\ClubFinanceEntry;
use App\Models\ClubMembershipRequest;
use App\Models\ClubMembershipType;
use App\Models\Folder;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Team;
use App\Models\TeamJoinRequest;
use App\Models\User;
use App\Notifications\ClubInvoiceCreated;
use App\Notifications\ExternalClubMembershipInvitation;
use App\Services\ClubService;
use App\Services\ClubContributionCalculator;
use App\Services\FileService;
use App\Services\PlanFeatureService;
use App\Support\AppNotification;
use App\Support\BillingOverview;
use App\Support\ClubAuditLog;
use App\Support\ClubRoles;
use App\Support\ClubMembershipApplication;
use App\Support\ClubMembershipInput;
use App\Support\Roles;
use App\Support\TransactionalMail;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
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
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();

        $hasFullClubAccess = $user->hasAnyRole(Roles::FULL_ACCESS);
        $hasManageableMembershipClubs = $hasFullClubAccess
            || Club::query()->where(function ($query) use ($user) {
                $this->scopeManageableMembershipClubs($query, $user);
            })->exists();

        abort_unless($hasManageableMembershipClubs, 403);

        $clubs = Club::query()
            ->when(! $hasFullClubAccess, function ($query) use ($user) {
                $this->scopeManageableMembershipClubs($query, $user);
            })
            ->with([
                'users' => fn ($query) => $query
                    ->select('users.id', 'name', 'email', 'athlete_license_number', 'profile_photo_path')
                    ->withCount(['invoices', 'payments'])
                    ->orderBy('name'),
                'teams' => fn ($query) => $query
                    ->select('id', 'club_id', 'name')
                    ->with([
                        'joinRequests' => fn ($requestQuery) => $requestQuery
                            ->where('status', 'pending')
                            ->with('user:id,name,email,profile_photo_path'),
                    ])
                    ->orderBy('name'),
                'invoices' => fn ($query) => $query
                    ->with('user:id,name,email')
                    ->latest('id')
                    ->limit(60),
                'payments' => fn ($query) => $query
                    ->with(['user:id,name,email', 'invoice:id,number,title'])
                    ->latest('id')
                    ->limit(60),
                'financeEntries' => fn ($query) => $query
                    ->with('user:id,name,email')
                    ->orderByDesc('booked_on')
                    ->latest('id')
                    ->limit(80),
                'bankTransactions' => fn ($query) => $query
                    ->with(['invoice:id,number,title,amount,status', 'payment:id,amount,paid_at'])
                    ->latest('id')
                    ->limit(60),
                'externalMembers' => fn ($query) => $query
                    ->with('linkedUser:id,name,email,profile_photo_path')
                    ->orderBy('name')
                    ->orderBy('email'),
                'currentSubscription.plan',
                'membershipTypes' => fn ($query) => $query->orderBy('sort_order')->orderBy('name'),
                'contributionRules' => fn ($query) => $query->with('membershipType:id,name')->orderByDesc('valid_from')->orderBy('name'),
                'membershipRequests' => fn ($query) => $query
                    ->where('status', 'pending')
                    ->with(['user:id,name,email,profile_photo_path', 'membershipType:id,name'])
                    ->latest('id'),
            ])
            ->withCount(['users', 'teams'])
            ->orderBy('name')
            ->get()
            ->map(function (Club $club) {
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

                $financeSummary = $this->financeBalanceSummary($club);
                $invoiceSummary = BillingOverview::clubInvoiceSummary(
                    Invoice::query()
                        ->where('club_id', $club->id)
                        ->get(['id', 'amount', 'status'])
                );

                return [
                    'id' => $club->id,
                    'name' => $club->name,
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
                    'users_count' => $club->users_count,
                    'teams_count' => $club->teams_count,
                    'subscription' => [
                        'plan' => $club->subscriptionPlan(),
                        'member_usage' => $club->memberUsageCount(),
                        'member_limit' => $club->subscriptionPlan()?->member_limit,
                        'team_limit' => $club->subscriptionPlan()?->team_limit,
                        'storage_gb' => $club->subscriptionPlan()?->storage_gb,
                    ],
                    'capabilities' => $this->planFeatures->capabilities($club),
                    ...$financeSummary,
                    'invoice_summary' => $invoiceSummary,
                    'pending_requests' => $pendingRequests,
                    'members' => $club->users->map(fn (User $member) => [
                        'id' => $member->id,
                        'name' => $member->name,
                        'email' => $member->email,
                        'athlete_license_number' => $member->athlete_license_number,
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
                    'club_requests' => $club->membershipRequests->map(fn (ClubMembershipRequest $request) => [
                        'id' => $request->id,
                        'type' => $request->type,
                        'status' => $request->status,
                        'message' => $request->message,
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
                        'role' => $externalMember->role,
                        'membership_status' => $externalMember->membership_status,
                        'family_group_key' => $externalMember->family_group_key,
                        'member_number' => $externalMember->member_number,
                        'athlete_license_number' => $externalMember->athlete_license_number,
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
                        'linked_at' => $externalMember->linked_at,
                        'linked_user' => $externalMember->linkedUser,
                    ])->values(),
                    'invoices' => $club->invoices
                        ->map(fn (Invoice $invoice) => $this->invoicePayload($invoice))
                        ->values(),
                    'payments' => $club->payments,
                    'audit_logs' => ClubAuditLog::forClub($club),
                    'finance_entries' => $club->financeEntries->map(fn (ClubFinanceEntry $entry) => [
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

        return Inertia::render('Auth/Dashboard/ClubMemberships/Index', [
            'clubs' => $clubs,
            'membershipStatuses' => self::MEMBERSHIP_STATUSES,
            'contributionIntervals' => self::CONTRIBUTION_INTERVALS,
            'contributionRuleTypes' => ClubContributionRule::ruleTypeOptions(),
            'contributionDiscountOperators' => ClubContributionRule::discountOperatorOptions(),
            'invoiceStatusOptions' => Invoice::statusOptions(),
            'clubRoles' => ClubRoles::options(ClubRoles::INVITABLE),
            'teamRoles' => Team::ROLES,
        ]);
    }

    private function scopeManageableMembershipClubs($query, User $user): void
    {
        $query->where(function ($clubQuery) use ($user) {
            $clubQuery
                ->where('owner_id', $user->id)
                ->orWhereHas('users', function ($memberQuery) use ($user) {
                    $memberQuery->where('users.id', $user->id);
                    ClubRoles::whereAny($memberQuery, ClubRoles::ELEVATED);
                });
        });
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
            'finance_period_label' => 'Dieses Jahr',
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
                'billing_interval' => 'Sonderbeitraege muessen einmalig abgerechnet werden.',
            ]);
        }

        return [
            ...$data,
            'factor_key' => $ruleType,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }

    public function updateMember(Request $request, Club $club, User $user)
    {
        $this->authorize('update', $club);

        abort_unless($club->users()->where('users.id', $user->id)->exists(), 404);

        $data = $request->validate([
            'role' => ['nullable', Rule::in(ClubController::MEMBER_ROLES)],
            'roles' => ['nullable', 'array'],
            'roles.*' => [Rule::in(ClubController::MEMBER_ROLES)],
            'membership_status' => ['required', Rule::in(self::MEMBERSHIP_STATUSES)],
            'club_membership_type_id' => ['nullable', Rule::exists('club_membership_types', 'id')->where('club_id', $club->id)],
            'family_group_key' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9._-]+$/'],
            'member_number' => ['nullable', 'string', 'max:80'],
            'athlete_license_number' => ['nullable', 'string', 'max:120'],
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
        $previousFamilyGroupKey = $this->normalizeFamilyGroupKey($currentMembership?->family_group_key);
        $nextFamilyGroupKey = $this->normalizeFamilyGroupKey($data['family_group_key'] ?? null);

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

        abort_if($duplicateNumber, 422, 'Diese Mitgliedsnummer ist in diesem Verein bereits vergeben.');

        DB::transaction(function () use ($club, $user, $data, $roles, $primaryRole) {
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

        foreach (array_unique(array_filter([$previousFamilyGroupKey, $nextFamilyGroupKey])) as $familyGroupKey) {
            $this->recalculateFamilyGroupContributions($club, $familyGroupKey);
        }

        $user->forceFill([
            'athlete_license_number' => $data['athlete_license_number'] ?? null,
        ])->save();

        return back()->with('success', 'Mitgliedsdaten aktualisiert.');
    }

    public function removeMember(Request $request, Club $club, User $user)
    {
        $this->authorize('update', $club);

        abort_unless($club->users()->where('users.id', $user->id)->exists(), 404);
        abort_if($club->owner_id === $user->id, 422, 'Der Owner kann nicht entfernt werden. Weise zuerst einem anderen Mitglied die Rolle Owner zu.');

        DB::transaction(function () use ($club, $user) {
            $teamIds = $club->teams()->pluck('id');

            DB::table('team_user')
                ->whereIn('team_id', $teamIds)
                ->where('user_id', $user->id)
                ->delete();

            $club->users()->detach($user->id);
            $this->clubService->refreshClubOwnerRole($user);
        });

        AppNotification::send($user, 'club.member_removed', [
            'title' => 'Vereinsmitgliedschaft beendet',
            'body' => 'Du wurdest aus '.$club->name.' entfernt. Wenn du das für falsch haeltst, kannst du widersprechen.',
            'url' => route('auth.notifications.index'),
            'club_id' => $club->id,
        ]);

        return back()->with('success', 'Mitglied wurde aus Verein und zugehoerigen Teams entfernt.');
    }

    public function leaveClub(Request $request, Club $club)
    {
        $user = $request->user();

        abort_unless($club->users()->where('users.id', $user->id)->exists(), 404);
        abort_if($club->owner_id === $user->id, 422, 'Owner können den Verein nicht verlassen. Weise zuerst einem anderen Mitglied die Rolle Owner zu.');

        $hasOpenDebt = Invoice::query()
            ->where('club_id', $club->id)
            ->where('user_id', $user->id)
            ->whereIn('status', ['open', 'overdue'])
            ->exists();

        if ($hasOpenDebt) {
            throw ValidationException::withMessages([
                'club' => 'Du kannst den Verein erst verlassen, wenn alle offenen Rechnungen ausgeglichen sind.',
            ]);
        }

        DB::transaction(function () use ($club, $user) {
            $teamIds = $club->teams()->pluck('id');

            DB::table('team_user')
                ->whereIn('team_id', $teamIds)
                ->where('user_id', $user->id)
                ->delete();

            $club->users()->detach($user->id);
            $this->clubService->refreshClubOwnerRole($user);
        });

        $this->notifyClubManagers($club, 'club.member_left', [
            'title' => 'Mitglied hat den Verein verlassen',
            'body' => $user->name.' hat '.$club->name.' verlassen.',
            'url' => route('auth.club-memberships.index'),
            'club_id' => $club->id,
            'user_id' => $user->id,
        ], $user->id);

        return back()->with('success', 'Du hast den Verein verlassen.');
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

        $this->notifyClubManagers($club, 'club.member_removal_objection', [
            'title' => 'Widerspruch gegen Entfernung',
            'body' => $request->user()->name.' widerspricht der Entfernung aus '.$club->name.'.',
            'url' => route('auth.club-memberships.index'),
            'club_id' => $club->id,
            'request_id' => $membershipRequest->id,
        ], $request->user()->id);

        return back()->with('success', 'Widerspruch wurde an den Verein gesendet.');
    }

    public function updateMembershipSettings(Request $request, Club $club)
    {
        $this->authorize('update', $club);

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
            'membership_application_fields' => ClubMembershipApplication::normalizeFieldModes($data['membership_application_fields'] ?? null),
            'membership_payment_methods' => ClubMembershipApplication::normalizePaymentMethods($data['membership_payment_methods'] ?? null),
            'membership_application_documents' => ClubMembershipApplication::normalizeDocuments($documents),
        ]);

        return back()->with('success', 'Mitgliedschafts-Einstellungen aktualisiert.');
    }

    public function storeMembershipType(Request $request, Club $club)
    {
        $this->authorize('update', $club);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_public' => ['boolean'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        $club->membershipTypes()->create([
            ...$data,
            'is_public' => (bool) ($data['is_public'] ?? true),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);

        return back()->with('success', 'Mitgliedschaftstyp gespeichert.');
    }

    public function storeContributionRule(Request $request, Club $club)
    {
        $this->authorize('update', $club);

        $club->contributionRules()->create($this->validatedContributionRuleData($request, $club));

        return back()->with('success', 'Beitragsregel gespeichert.');
    }

    public function storeMembershipRequest(Request $request, Club $club)
    {
        abort_unless($club->membership_requests_enabled, 403, 'Dieser Verein nimmt aktuell keine Online-Mitgliedsanfragen an.');
        abort_if($club->users()->where('users.id', $request->user()->id)->exists(), 422, 'Du bist bereits Mitglied in diesem Verein.');

        $applicationFields = ClubMembershipApplication::fieldsForClub($club->membership_application_fields);
        $enabledApplicationFields = collect($applicationFields)->where('mode', '!=', 'off')->values();
        $paymentMethods = ClubMembershipApplication::normalizePaymentMethods($club->membership_payment_methods);
        $visibleDocuments = collect(ClubMembershipApplication::normalizeDocuments($club->membership_application_documents))
            ->where('is_visible', true)
            ->values();

        $data = $request->validate([
            'club_membership_type_id' => ['nullable', Rule::exists('club_membership_types', 'id')->where('club_id', $club->id)],
            'application_data' => ['nullable', 'array'],
            'accepted_documents' => ['nullable', 'array'],
            'accepted_documents.*' => ['boolean'],
            'preferred_payment_method' => ['nullable', Rule::in($paymentMethods)],
            'requested_billing_interval' => ['nullable', Rule::in(self::CONTRIBUTION_INTERVALS)],
            'message' => ['nullable', 'string', 'max:2000'],
            'consent_version' => ['nullable', 'string', 'max:80'],
            'consent_signature' => ['nullable', 'string', 'max:255'],
        ]);

        $applicationData = [];
        $missingFields = [];
        $inputApplicationData = array_merge(
            ClubMembershipApplication::prefillFor($request->user()),
            $data['application_data'] ?? [],
        );

        foreach ($enabledApplicationFields as $field) {
            $key = $field['key'];
            $value = $inputApplicationData[$key] ?? null;
            $isCheckbox = ($field['type'] ?? null) === 'checkbox';
            $isEmpty = $isCheckbox ? ! (bool) $value : blank($value);

            if (($field['mode'] ?? 'off') === 'required' && $isEmpty) {
                $missingFields['application_data.'.$key] = $field['label'].' ist erforderlich.';
            }

            if (! $isEmpty && ($field['type'] ?? null) === 'select') {
                $allowedValues = collect($field['options'] ?? [])->pluck('value')->all();

                if ($allowedValues && ! in_array((string) $value, $allowedValues, true)) {
                    $missingFields['application_data.'.$key] = $field['label'].' ist ungültig.';
                    continue;
                }
            }

            if (! $isEmpty) {
                $applicationData[$key] = $isCheckbox ? (bool) $value : trim((string) $value);
            }
        }

        if ($missingFields) {
            throw ValidationException::withMessages($missingFields);
        }

        $acceptedDocumentIds = collect($data['accepted_documents'] ?? [])
            ->filter(fn ($accepted) => (bool) $accepted)
            ->keys()
            ->map(fn ($id) => (string) $id)
            ->all();

        $missingDocuments = [];
        $acceptedDocuments = [];
        $consentAt = now();

        foreach ($visibleDocuments as $document) {
            $isAccepted = in_array((string) $document['id'], $acceptedDocumentIds, true);

            if (($document['is_required'] ?? false) && ! $isAccepted) {
                $missingDocuments['accepted_documents.'.$document['id']] = $document['title'].' muss bestaetigt werden.';
            }

            if ($isAccepted) {
                $acceptedDocuments[] = [
                    'id' => $document['id'],
                    'type' => $document['type'],
                    'title' => $document['title'],
                    'url' => $document['url'],
                    'file_id' => $document['file_id'] ?? null,
                    'file_name' => $document['file_name'] ?? '',
                    'version' => ClubMembershipApplication::documentVersion($document),
                    'accepted_at' => $consentAt->toIso8601String(),
                ];
            }
        }

        if ($missingDocuments) {
            throw ValidationException::withMessages($missingDocuments);
        }

        $preview = app(ClubContributionCalculator::class)->resolve(
            $club,
            $request->user(),
            $data['club_membership_type_id'] ?? null,
        );

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
                'accepted_documents' => $acceptedDocuments,
                'consent_version' => trim((string) ($data['consent_version'] ?? 'membership-v1')) ?: 'membership-v1',
                'consent_signature' => filled($data['consent_signature'] ?? null) ? trim((string) $data['consent_signature']) : null,
                'consent_ip' => $request->ip(),
                'consent_user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
                'consent_at' => $consentAt,
                'preferred_payment_method' => $data['preferred_payment_method'] ?? null,
                'requested_billing_interval' => $data['requested_billing_interval'] ?? null,
                'applicant_confirmed_at' => now(),
                'preview_amount' => $preview['amount'] ?? null,
                'preview_base_amount' => $preview['base_amount'] ?? null,
                'preview_discount_amount' => $preview['discount_amount'] ?? null,
                'preview_rule_type' => $preview['rule_type'] ?? null,
                'preview_interval' => $data['requested_billing_interval'] ?? ($preview['interval'] ?? null),
            ],
        );

        $this->notifyClubManagers($club, 'club.membership_request_created', [
            'title' => 'Neue Mitgliedschaftsanfrage',
            'body' => $request->user()->name.' möchte Mitglied bei '.$club->name.' werden.',
            'url' => route('auth.club-memberships.index'),
            'club_id' => $club->id,
            'membership_request_id' => $membershipRequest->id,
        ], $request->user()->id);

        return back()->with('success', 'Mitgliedschaftsanfrage wurde an den Verein gesendet.');
    }

    public function withdrawMembershipRequest(Request $request, Club $club)
    {
        $membershipRequest = ClubMembershipRequest::query()
            ->where('club_id', $club->id)
            ->where('user_id', $request->user()->id)
            ->where('type', 'membership')
            ->where('status', 'pending')
            ->firstOrFail();

        $membershipRequest->update([
            'status' => 'withdrawn',
            'reviewed_at' => now(),
            'review_note' => 'Vom Nutzer zurueckgezogen.',
        ]);

        $this->notifyClubManagers($club, 'club.membership_request_withdrawn', [
            'title' => 'Mitgliedschaftsanfrage zurückgezogen',
            'body' => $request->user()->name.' hat die Anfrage bei '.$club->name.' zurückgezogen.',
            'url' => route('auth.club-memberships.index'),
            'club_id' => $club->id,
            'membership_request_id' => $membershipRequest->id,
        ], $request->user()->id);

        return back()->with('success', 'Mitgliedschaftsanfrage wurde zurückgezogen.');
    }

    public function storePauseRequest(Request $request, Club $club)
    {
        abort_unless($club->member_pause_requests_enabled, 403, 'Dieser Verein erlaubt aktuell keine Pausen-Anfragen.');
        abort_unless($club->users()->where('users.id', $request->user()->id)->exists(), 403);

        $data = $request->validate([
            'requested_pause_from' => ['required', 'date'],
            'requested_pause_until' => ['nullable', 'date', 'after_or_equal:requested_pause_from'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        ClubMembershipRequest::query()->updateOrCreate(
            [
                'club_id' => $club->id,
                'user_id' => $request->user()->id,
                'type' => 'pause',
                'status' => 'pending',
            ],
            $data,
        );

        $club->users()->updateExistingPivot($request->user()->id, [
            'pause_requested_at' => now(),
        ]);

        return back()->with('success', 'Pausen-Anfrage wurde an den Verein gesendet.');
    }

    public function approveClubRequest(Request $request, ClubMembershipRequest $membershipRequest)
    {
        $club = $membershipRequest->club;
        $this->authorize('update', $club);

        abort_unless($membershipRequest->status === 'pending', 422);

        DB::transaction(function () use ($request, $membershipRequest, $club) {
            if ($membershipRequest->type === 'pause') {
                $club->users()->updateExistingPivot($membershipRequest->user_id, [
                    'membership_status' => 'paused',
                    'paused_from' => $membershipRequest->requested_pause_from,
                    'paused_until' => $membershipRequest->requested_pause_until,
                    'pause_requested_at' => null,
                ]);
            } elseif ($membershipRequest->type === 'removal_objection') {
                $club->users()->syncWithoutDetaching([
                    $membershipRequest->user_id => [
                        'role' => 'member',
                        'roles' => ['member'],
                        'membership_status' => 'active',
                        'joined_on' => now()->toDateString(),
                    ],
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
                        'sepa_iban' => $this->normalizeIban($membershipRequest->application_data['sepa_iban'] ?? null),
                        'sepa_bic' => $this->normalizeBic($membershipRequest->application_data['sepa_bic'] ?? null),
                        'sepa_mandate_active' => (bool) ($membershipRequest->application_data['sepa_mandate_consent'] ?? false),
                        'joined_on' => now()->toDateString(),
                    ],
                ]);
            }

            $membershipRequest->update([
                'status' => 'approved',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);
        });

        AppNotification::send($membershipRequest->user_id, 'club.membership_request_approved', [
            'title' => 'Anfrage angenommen',
            'body' => $club->name.' hat deine Anfrage angenommen.',
            'url' => route('auth.clubs.show', $club),
            'club_id' => $club->id,
            'request_id' => $membershipRequest->id,
        ]);

        return back()->with('success', 'Anfrage wurde angenommen.');
    }

    public function declineClubRequest(Request $request, ClubMembershipRequest $membershipRequest)
    {
        $this->authorize('update', $membershipRequest->club);
        abort_unless($membershipRequest->status === 'pending', 422, 'Diese Anfrage ist nicht mehr offen.');

        $membershipRequest->update([
            'status' => 'declined',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_note' => $request->input('review_note'),
        ]);

        AppNotification::send($membershipRequest->user_id, 'club.membership_request_declined', [
            'title' => 'Anfrage abgelehnt',
            'body' => $membershipRequest->club->name.' hat deine Anfrage abgelehnt.',
            'url' => route('auth.notifications.index'),
            'club_id' => $membershipRequest->club_id,
            'request_id' => $membershipRequest->id,
        ]);

        return back()->with('success', 'Anfrage wurde abgelehnt.');
    }

    public function storeEmailMember(Request $request, Club $club)
    {
        $this->authorize('update', $club);

        if ($request->has('members')) {
            $data = $request->validate([
                'send_invitation' => ['boolean'],
                'invitation_expires_at' => ['nullable', 'date', 'after_or_equal:today'],
                'members' => ['required', 'array', 'min:1', 'max:50'],
                'members.*.name' => ['nullable', 'string', 'max:255'],
                'members.*.email' => ['required', 'email', 'max:255'],
                'members.*.role' => ['nullable', Rule::in(ClubRoles::INVITABLE)],
                'members.*.membership_status' => ['required', Rule::in(self::MEMBERSHIP_STATUSES)],
                'members.*.family_group_key' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9._-]+$/'],
                'members.*.member_number' => ['nullable', 'string', 'max:80'],
                'members.*.athlete_license_number' => ['nullable', 'string', 'max:120'],
                'members.*.contribution_amount' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
                'members.*.contribution_interval' => ['nullable', Rule::in(self::CONTRIBUTION_INTERVALS)],
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
            'send_invitation' => ['boolean'],
            'invitation_expires_at' => ['nullable', 'date', 'after_or_equal:today'],
            'role' => ['nullable', Rule::in(ClubRoles::INVITABLE)],
            'membership_status' => ['required', Rule::in(self::MEMBERSHIP_STATUSES)],
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
        $this->authorize('update', $club);
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
                    'reason' => 'Ungueltige oder fehlende E-Mail-Adresse.',
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

            if ($sendInvitation && $existingUser) {
                $this->attachExistingUserToClub($club, $existingUser, $memberData);
                $stats['linked']++;
                continue;
            }

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

                Notification::route('mail', $email)
                    ->notify(new ExternalClubMembershipInvitation($externalMember->load('club')));
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
        $this->authorize('update', $club);
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
                    'reason' => 'Ungueltige oder fehlende E-Mail-Adresse.',
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
        $this->authorize('update', $club);
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

        return back()->with('success', 'SEPA-Einstellungen gespeichert.');
    }

    public function exportSepaDebit(Club $club)
    {
        $this->authorize('update', $club);
        $this->planFeatures->ensureAllows($club, 'sepa_export');

        abort_if(blank($club->sepa_creditor_id) || blank($club->sepa_iban), 422, 'Bitte zuerst SEPA-Glaeubiger-ID und Vereins-IBAN speichern.');

        $invoices = Invoice::query()
            ->where('club_id', $club->id)
            ->where('status', 'open')
            ->where('amount', '>', 0)
            ->whereNotNull('user_id')
            ->with('user:id,name,email')
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

                return $membership
                    && (bool) $membership->sepa_mandate_active
                    && filled($membership->sepa_iban)
                    && filled($membership->sepa_mandate_reference)
                    && filled($membership->sepa_mandate_signed_on);
            })
            ->values();

        abort_if($exportable->isEmpty(), 422, 'Keine offenen Rechnungen mit aktivem SEPA-Mandat gefunden.');

        $xml = $this->buildSepaDebitXml($club, $exportable, $memberships);
        $fileName = 'airmius-sepa-'.$club->id.'-'.now()->format('Ymd-His').'.xml';

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
        ]);
    }

    public function inviteEmailMember(Request $request, ClubExternalMember $externalMember)
    {
        $this->authorize('update', $externalMember->club);
        $this->planFeatures->ensureCanSendMemberInvitations($externalMember->club);

        $existingUser = User::query()
            ->where('email', strtolower($externalMember->email))
            ->first();

        if ($existingUser) {
            DB::transaction(function () use ($externalMember, $existingUser) {
                $roles = ClubRoles::normalize($externalMember->role, [$externalMember->role]);

                $externalMember->club->users()->syncWithoutDetaching([
                    $existingUser->id => [
                        'role' => ClubRoles::primary($roles),
                        'roles' => $roles,
                        'membership_status' => $externalMember->membership_status,
                        'family_group_key' => $externalMember->family_group_key,
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

                if (filled($externalMember->athlete_license_number)) {
                    $existingUser->forceFill([
                        'athlete_license_number' => $externalMember->athlete_license_number,
                    ])->save();
                }

                $externalMember->delete();
            });

            AppNotification::send($existingUser, 'club.member_linked', [
                'title' => 'Du wurdest mit '.$externalMember->club->name.' verknüpft',
                'body' => 'Der Verein hat deine Mitgliedschaft mit deinem Airmius-Konto verbunden.',
                'url' => route('auth.club-memberships.index'),
                'club_id' => $externalMember->club_id,
            ]);

            return back()->with('success', 'Bestehender User wurde verknüpft.');
        }

        $externalMember->issueInvitation();

        Notification::route('mail', $externalMember->email)
            ->notify(new ExternalClubMembershipInvitation($externalMember->load('club')));

        return back()->with('success', 'Einladung wurde versendet.');
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

            abort(410, 'Diese Einladung ist abgelaufen.');
        }

        DB::transaction(function () use ($externalMember, $request) {
            $roles = ClubRoles::normalize($externalMember->role, [$externalMember->role]);

            $externalMember->club->users()->syncWithoutDetaching([
                $request->user()->id => [
                    'role' => ClubRoles::primary($roles),
                    'roles' => $roles,
                    'membership_status' => $externalMember->membership_status,
                    'family_group_key' => $externalMember->family_group_key,
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

            if (filled($externalMember->athlete_license_number)) {
                $request->user()->forceFill([
                    'athlete_license_number' => $externalMember->athlete_license_number,
                ])->save();
            }

            $externalMember->delete();
        });

        return redirect()
            ->route('auth.club-memberships.index')
            ->with('success', 'Vereinsmitgliedschaft wurde mit deinem Konto verknüpft.');
    }

    public function storeInvoice(Request $request, Club $club, User $user)
    {
        $this->authorize('update', $club);
        abort_unless($club->users()->where('users.id', $user->id)->exists(), 404);
        $this->planFeatures->ensureAllows($club, 'invoices');

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'due_date' => ['required', 'date'],
        ]);

        $invoice = Invoice::create([
            'club_id' => $club->id,
            'user_id' => $user->id,
            'number' => $this->nextInvoiceNumber($club),
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'amount' => $data['amount'],
            'status' => 'open',
            'source' => 'manual',
            'due_date' => $data['due_date'],
            'issued_at' => now(),
        ]);

        ClubAuditLog::record($club, $request->user(), 'club.invoice.created', $invoice, [
            'invoice_number' => $invoice->number,
            'invoice_title' => $invoice->title,
            'amount' => $invoice->amount,
            'member_id' => $user->id,
            'member_name' => $user->name,
        ]);

        AppNotification::send($user, 'invoice.created', [
            'title' => 'Neue Rechnung von '.$club->name,
            'body' => $invoice->title.' - '.number_format((float) $invoice->amount, 2, ',', '.').' EUR',
            'url' => route('auth.settings'),
            'club_id' => $club->id,
            'invoice_id' => $invoice->id,
        ]);

        $this->sendClubInvoiceCreatedEmail($user, $invoice->loadMissing('club'));

        return back()->with('success', 'Rechnung erstellt und per E-Mail verschickt.');
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

    private function invoicePayload(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'club_id' => $invoice->club_id,
            'user_id' => $invoice->user_id,
            'number' => $invoice->number,
            'title' => $invoice->title,
            'description' => $invoice->description,
            'amount' => $invoice->amount,
            'status' => $invoice->status,
            'status_label' => $invoice->statusLabel(),
            'source' => $invoice->source,
            'billing_period_start' => $invoice->billing_period_start?->toDateString(),
            'billing_period_end' => $invoice->billing_period_end?->toDateString(),
            'due_date' => $invoice->due_date?->toJSON(),
            'issued_at' => $invoice->issued_at?->toJSON(),
            'paid_at' => $invoice->paid_at?->toJSON(),
            'user' => $invoice->user ? [
                'id' => $invoice->user->id,
                'name' => $invoice->user->name,
                'email' => $invoice->user->email,
            ] : null,
            'created_at' => $invoice->created_at?->toJSON(),
            'updated_at' => $invoice->updated_at?->toJSON(),
        ];
    }

    public function generateMemberNumber(Club $club, User $user)
    {
        $this->authorize('update', $club);
        abort_unless($club->users()->where('users.id', $user->id)->exists(), 404);

        $club->users()->updateExistingPivot($user->id, [
            'member_number' => $this->nextMemberNumber($club),
        ]);

        return back()->with('success', 'Mitgliedsnummer generiert.');
    }

    public function updateInvoiceStatus(Request $request, Invoice $invoice)
    {
        $this->authorize('update', $invoice->club);
        $this->planFeatures->ensureAllows($invoice->club, 'payment_tracking');
        $oldStatus = $invoice->status;

        $data = $request->validate([
            'status' => ['required', Rule::in(Invoice::PAYMENT_STATUSES)],
        ]);

        $invoice->update([
            'status' => $data['status'],
            'paid_at' => $data['status'] === 'paid' ? ($invoice->paid_at ?? now()) : null,
        ]);

        if ($oldStatus !== $invoice->status) {
            ClubAuditLog::record($invoice->club, $request->user(), 'club.invoice.status_updated', $invoice, [
                'invoice_number' => $invoice->number,
                'old_status' => $oldStatus,
                'new_status' => $invoice->status,
            ]);
        }

        return back()->with('success', 'Rechnungsstatus aktualisiert.');
    }

    public function recordPayment(Request $request, Invoice $invoice)
    {
        $this->authorize('update', $invoice->club);

        $data = $request->validate([
            'amount' => ['nullable', 'numeric', 'min:0.01', 'max:999999.99'],
            'method' => ['nullable', 'string', 'max:60'],
            'reference' => ['nullable', 'string', 'max:255'],
            'paid_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        Payment::create([
            'club_id' => $invoice->club_id,
            'user_id' => $invoice->user_id,
            'invoice_id' => $invoice->id,
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

        ClubAuditLog::record($invoice->club, $request->user(), 'club.payment.recorded', $invoice, [
            'invoice_number' => $invoice->number,
            'amount' => $data['amount'] ?? $invoice->amount,
            'method' => $data['method'] ?? 'manual',
        ]);

        AppNotification::send((int) $invoice->user_id, 'invoice.paid', [
            'title' => 'Zahlung erfasst',
            'body' => 'Deine Zahlung für '.$invoice->number.' wurde markiert.',
            'url' => route('auth.settings'),
            'invoice_id' => $invoice->id,
        ]);

        return back()->with('success', 'Zahlung markiert.');
    }

    public function storeFinanceEntry(Request $request, Club $club)
    {
        $this->authorize('update', $club);
        $this->planFeatures->ensureAllows($club, 'payment_tracking');

        ClubFinanceEntry::create([
            ...$this->validatedFinanceEntryData($request),
            'club_id' => $club->id,
            'user_id' => $request->user()->id,
        ]);

        return back()->with('success', 'Finanzbuchung gespeichert.');
    }

    public function updateFinanceEntry(Request $request, Club $club, ClubFinanceEntry $financeEntry)
    {
        $this->authorize('update', $club);
        abort_unless((int) $financeEntry->club_id === (int) $club->id, 404);
        $this->planFeatures->ensureAllows($club, 'payment_tracking');

        $financeEntry->update($this->validatedFinanceEntryData($request));

        return back()->with('success', 'Finanzbuchung aktualisiert.');
    }

    public function sendReminder(Invoice $invoice)
    {
        $this->authorize('update', $invoice->club);
        abort_if($invoice->status === 'paid', 422, 'Bezahlte Rechnungen können nicht gemahnt werden.');
        $this->planFeatures->ensureAllows($invoice->club, 'payment_reminders');

        $invoice->update([
            'status' => 'overdue',
            'reminder_sent_at' => now(),
        ]);

        AppNotification::send((int) $invoice->user_id, 'invoice.reminder', [
            'title' => 'Zahlungserinnerung',
            'body' => 'Bitte prüfe die offene Rechnung '.$invoice->number.'.',
            'url' => route('auth.settings'),
            'invoice_id' => $invoice->id,
        ]);

        return back()->with('success', 'Mahnung gesendet.');
    }

    public function importBankTransactions(Request $request, Club $club)
    {
        $this->authorize('update', $club);
        $this->planFeatures->ensureAllows($club, 'bank_reconciliation');

        $data = $request->validate([
            'file' => ['required', 'file', 'max:10240'],
        ]);

        $rows = $this->readBankTransactionRows($data['file']->getRealPath());
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

            if (! $transaction || (float) $transaction['amount'] <= 0) {
                $stats['skipped']++;
                continue;
            }

            if (BankTransaction::query()
                ->where('club_id', $club->id)
                ->where('transaction_hash', $transaction['transaction_hash'])
                ->exists()) {
                $stats['duplicates']++;
                continue;
            }

            $match = $this->findInvoiceMatchForBankTransaction($club, $transaction);
            $status = $match['status'];
            $payment = null;

            DB::transaction(function () use ($club, $request, $transaction, $match, &$payment, &$status) {
                if ($status === 'matched' && $match['invoice']) {
                    $payment = $this->recordBankMatchedPayment($match['invoice'], $transaction);
                }

                BankTransaction::create([
                    'club_id' => $club->id,
                    'invoice_id' => $match['invoice']?->id,
                    'payment_id' => $payment?->id,
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
            $stats[$status === 'matched' ? 'auto_matched' : ($status === 'suggested' ? 'suggested' : 'unmatched')]++;
        }

        return back()->with(
            'success',
            "Bankabgleich fertig: {$stats['auto_matched']} automatisch bezahlt, {$stats['suggested']} Vorschlaege, {$stats['unmatched']} offen, {$stats['duplicates']} Duplikate."
        );
    }

    public function confirmBankTransaction(BankTransaction $bankTransaction)
    {
        $this->authorize('update', $bankTransaction->club);
        $this->planFeatures->ensureAllows($bankTransaction->club, 'bank_reconciliation');

        abort_if($bankTransaction->status === 'matched', 422, 'Dieser Umsatz ist bereits zugeordnet.');
        abort_unless($bankTransaction->invoice && $bankTransaction->invoice->status !== 'paid', 422, 'Es gibt keine offene Rechnung für diesen Umsatz.');

        DB::transaction(function () use ($bankTransaction) {
            $payment = $this->recordBankMatchedPayment($bankTransaction->invoice, [
                'amount' => $bankTransaction->amount,
                'booking_date' => $bankTransaction->booking_date?->toDateString(),
                'purpose' => $bankTransaction->purpose,
            ]);

            $bankTransaction->update([
                'payment_id' => $payment->id,
                'status' => 'matched',
                'match_confidence' => max((int) $bankTransaction->match_confidence, 80),
                'match_reason' => 'Manuell bestätigt',
            ]);
        });

        return back()->with('success', 'Bankumsatz wurde als Zahlung verbucht.');
    }

    public function updateDatevSettings(Request $request, Club $club)
    {
        $this->authorize('update', $club);
        $this->planFeatures->ensureAllows($club, 'datev_export');

        $data = $request->validate([
            'datev_consultant_number' => ['nullable', 'string', 'max:20'],
            'datev_client_number' => ['nullable', 'string', 'max:20'],
            'datev_revenue_account' => ['nullable', 'string', 'max:20'],
            'datev_bank_account' => ['nullable', 'string', 'max:20'],
        ]);

        $club->update([
            'datev_consultant_number' => $data['datev_consultant_number'] ?? null,
            'datev_client_number' => $data['datev_client_number'] ?? null,
            'datev_revenue_account' => $data['datev_revenue_account'] ?? null,
            'datev_bank_account' => $data['datev_bank_account'] ?? null,
        ]);

        return back()->with('success', 'DATEV-Einstellungen gespeichert.');
    }

    public function exportDatev(Request $request, Club $club)
    {
        $this->authorize('update', $club);
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
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$from, $to])
            ->with(['invoice:id,number,title,description,source,billing_period_start,billing_period_end', 'user:id,name,email'])
            ->orderBy('paid_at')
            ->get();

        abort_if($payments->isEmpty(), 422, 'Keine bezahlten Zahlungen im ausgewählten Zeitraum gefunden.');

        $fileName = 'airmius-datev-'.$club->id.'-'.$from->format('Ymd').'-'.$to->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($club, $payments, $revenueAccount, $bankAccount) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'Umsatz (ohne Soll/Haben-Kz)',
                'Soll/Haben-Kennzeichen',
                'WKZ Umsatz',
                'Konto',
                'Gegenkonto (ohne BU-Schluessel)',
                'BU-Schluessel',
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
                $bookingText = trim(implode(' ', array_filter([
                    'Mitgliedsbeitrag',
                    $invoice?->number,
                    $invoice?->title,
                    $payment->user?->name,
                ])));

                fputcsv($handle, [
                    number_format((float) $payment->amount, 2, ',', ''),
                    'S',
                    'EUR',
                    $bankAccount,
                    $revenueAccount,
                    '',
                    $payment->paid_at?->format('dm') ?? now()->format('dm'),
                    $invoice?->number ?? $payment->reference ?? 'PAY-'.$payment->id,
                    $payment->paid_at?->format('Y') ?? now()->format('Y'),
                    mb_substr($bookingText, 0, 60),
                    '',
                    '',
                    $club->datev_client_number ?? '',
                    $club->datev_consultant_number ?? '',
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
                'family_group_key' => $this->normalizeFamilyGroupKey($data['family_group_key'] ?? null),
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

        if (filled($data['athlete_license_number'])) {
            $user->forceFill([
                'athlete_license_number' => $data['athlete_license_number'],
            ])->save();
        }

        ClubExternalMember::query()
            ->where('club_id', $club->id)
            ->where('email', strtolower($user->email))
            ->delete();

        if (filled($data['family_group_key'] ?? null)) {
            $this->recalculateFamilyGroupContributions($club, (string) $data['family_group_key']);
        }

        AppNotification::send($user, 'club.member_linked', [
            'title' => 'Du wurdest mit '.$club->name.' verknüpft',
            'body' => 'Der Verein hat dich als Mitglied hinzugefügt.',
            'url' => route('auth.club-memberships.index'),
            'club_id' => $club->id,
        ]);
    }

    private function notifyClubManagers(Club $club, string $type, array $data, ?int $exceptUserId = null): void
    {
        $club->users()
            ->tap(fn ($query) => ClubRoles::whereAny($query, ['owner', 'admin', 'manager']))
            ->when($exceptUserId, fn ($query) => $query->where('users.id', '!=', $exceptUserId))
            ->get(['users.id'])
            ->each(fn (User $manager) => AppNotification::send($manager, $type, $data));
    }

    private function recordBankMatchedPayment(Invoice $invoice, array $transaction): Payment
    {
        $payment = Payment::create([
            'club_id' => $invoice->club_id,
            'user_id' => $invoice->user_id,
            'invoice_id' => $invoice->id,
            'amount' => $transaction['amount'] ?? $invoice->amount,
            'status' => 'paid',
            'method' => 'bank_import',
            'reference' => $transaction['purpose'] ?? null,
            'paid_at' => $transaction['booking_date'] ?? now(),
            'notes' => 'Automatisch per Bankabgleich zugeordnet.',
        ]);

        $invoice->update([
            'status' => 'paid',
            'paid_at' => $transaction['booking_date'] ?? now(),
        ]);

        if ($invoice->user_id) {
            AppNotification::send((int) $invoice->user_id, 'invoice.paid', [
                'title' => 'Zahlung eingegangen',
                'body' => 'Deine Zahlung für '.$invoice->number.' wurde per Bankabgleich erkannt.',
                'url' => route('auth.settings'),
                'invoice_id' => $invoice->id,
            ]);
        }

        return $payment;
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
        $currency = strtoupper(trim((string) ($row['Währung'] ?? $row['currency'] ?? 'EUR'))) ?: 'EUR';

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
            ->where('status', 'open')
            ->whereNotNull('user_id')
            ->with('user:id,name,email')
            ->get();

        $purpose = strtoupper((string) ($transaction['purpose'] ?? ''));
        $amount = round((float) $transaction['amount'], 2);

        $numberMatch = $openInvoices->first(fn (Invoice $invoice) => str_contains($purpose, strtoupper($invoice->number))
            && round((float) $invoice->amount, 2) === $amount);

        if ($numberMatch) {
            return [
                'invoice' => $numberMatch,
                'status' => 'matched',
                'confidence' => 100,
                'reason' => 'Rechnungsnummer und Betrag stimmen Überein.',
            ];
        }

        $sameAmountInvoices = $openInvoices
            ->filter(fn (Invoice $invoice) => round((float) $invoice->amount, 2) === $amount)
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
            'role' => ClubRoles::primary($roles),
            'roles' => $roles,
            'membership_status' => $data['membership_status'] ?? 'active',
            'family_group_key' => $this->normalizeFamilyGroupKey($data['family_group_key'] ?? null),
            'member_number' => trim((string) ($data['member_number'] ?? '')) ?: null,
            'athlete_license_number' => trim((string) ($data['athlete_license_number'] ?? '')) ?: null,
            'contribution_amount' => $data['contribution_amount'] ?? null,
            'contribution_interval' => $data['contribution_interval'] ?? 'none',
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

        if ($sendInvitation && $existingUser) {
            $this->attachExistingUserToClub($club, $existingUser, $memberData);

            return 'linked';
        }

        $externalMember = ClubExternalMember::updateOrCreate(
            [
                'club_id' => $club->id,
                'email' => $email,
            ],
                [
                    'created_by' => $creator->id,
                    'name' => $memberData['name'],
                    'role' => $memberData['role'],
                    'membership_status' => $memberData['membership_status'],
                    'family_group_key' => $memberData['family_group_key'],
                    'member_number' => $memberData['member_number'],
                    'athlete_license_number' => $memberData['athlete_license_number'],
                'contribution_amount' => $memberData['contribution_amount'],
                'contribution_interval' => $memberData['contribution_interval'],
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

            Notification::route('mail', $email)
                ->notify(new ExternalClubMembershipInvitation($externalMember->load('club')));

            return 'invited';
        }

        return 'stored';
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
    ): array
    {
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
        $zip = new \ZipArchive();

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
        $zip = new \ZipArchive();
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
            ['Fuellen Sie ab Zeile 5 die Mitglieder aus. Pflichtfeld ist E-Mail. Gleicher Familiengruppen-Schluessel verbindet aktive Mitglieder fuer automatische Familienbeitraege. Mitgliedschaft: active, non_member, pending, former. Intervall: none, monthly, quarterly, yearly, once. SEPA aktiv: ja/nein.'],
            [],
            ['Name', 'E-Mail', 'Familiengruppe', 'Mitgliedschaft', 'Mitgliedsnummer', 'Lizenznummer', 'Beitrag', 'Intervall', 'Nächste_Rechnung', 'IBAN', 'BIC', 'Mandatsreferenz', 'Mandatsdatum', 'SEPA_Aktiv', 'Eintritt', 'Ende', 'Notiz'],
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

        $xml .= '</sheetData><mergeCells count="2"><mergeCell ref="A1:P1"/><mergeCell ref="A2:P2"/></mergeCells><dataValidations count="2"><dataValidation type="list" allowBlank="1" showDropDown="0" sqref="G5:G1000"><formula1>"none,monthly,quarterly,yearly,once"</formula1></dataValidation><dataValidation type="list" allowBlank="1" showDropDown="0" sqref="M5:M1000"><formula1>"ja,nein"</formula1></dataValidation></dataValidations><drawing r:id="rId1"/></worksheet>';

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
        if (($data['contribution_interval'] ?? 'none') === 'none') {
            return null;
        }

        return $data['contribution_next_invoice_on'] ?? null;
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
        $controlSum = $invoices->sum(fn (Invoice $invoice) => (float) $invoice->amount);
        $collectionDate = now()->addDays(3)->toDateString();
        $creditorName = $club->sepa_account_holder ?: $club->name;

        $xml = new \XMLWriter();
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
            $xml->text(number_format((float) $invoice->amount, 2, '.', ''));
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

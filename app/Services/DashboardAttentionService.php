<?php

namespace App\Services;

use App\Models\Club;
use App\Models\ClubAccessHandoverReview;
use App\Models\ClubExternalMember;
use App\Models\ClubInventoryItem;
use App\Models\ClubInventoryLoan;
use App\Models\ClubMembershipRequest;
use App\Models\ClubPolicyDocument;
use App\Models\Invoice;
use App\Models\OperatingContract;
use App\Models\Team;
use App\Models\TeamJoinRequest;
use App\Models\User;
use App\Support\ClubMembershipApplication;
use App\Support\ClubPermissions;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class DashboardAttentionService
{
    public function __construct(private readonly TeamDailyLifeService $teamDailyLife) {}

    public function forUser(User $user): array
    {
        $clubs = Club::query()
            ->where(fn ($query) => $query
                ->where('owner_id', $user->id)
                ->orWhereHas('users', fn ($members) => $members
                    ->where('users.id', $user->id)
                    ->where(fn ($membership) => $membership
                        ->whereNull('club_user.membership_status')
                        ->orWhere('club_user.membership_status', 'active'))))
            ->get([
                'id', 'owner_id', 'name', 'membership_application_documents',
                'membership_application_document_types', 'tax_exemption_valid_until',
                'federation_affiliations',
            ]);

        $applications = $this->applications($user, $clubs);
        $payments = $this->payments($user, $clubs);
        $approvals = $this->approvals($user, $clubs);
        $documents = $this->missingDocuments($user, $clubs);
        $deadlines = $this->deadlines($user, $clubs);
        $tasks = $this->tasks($user, $clubs);

        return $this->payload($applications, $payments, $approvals, $documents, $deadlines, $tasks);
    }

    private function applications(User $user, Collection $clubs): array
    {
        $clubIds = $clubs
            ->filter(fn (Club $club) => ClubPermissions::allows($club, $user, ClubPermissions::MEMBERS_APPROVE))
            ->pluck('id');
        $membershipRequests = $clubIds->isEmpty()
            ? collect()
            : ClubMembershipRequest::query()
                ->whereIn('club_id', $clubIds)
                ->where('status', 'pending')
                ->get(['id', 'club_id']);
        $approvableTeams = Team::query()
            ->whereIn('club_id', $clubs->pluck('id'))
            ->with('club')
            ->get(['id', 'club_id'])
            ->filter(fn (Team $team) => ClubPermissions::allowsForTeam($team, $user, ClubPermissions::MEMBERS_APPROVE));
        $teamRequests = $approvableTeams->isEmpty()
            ? collect()
            : TeamJoinRequest::query()
                ->whereIn('team_id', $approvableTeams->pluck('id'))
                ->where('status', 'pending')
                ->get(['id', 'team_id']);
        $teamRequestClubIds = $approvableTeams
            ->whereIn('id', $teamRequests->pluck('team_id'))
            ->pluck('club_id');

        return [
            'count' => $membershipRequests->count() + $teamRequests->count(),
            'membership_count' => $membershipRequests->count(),
            'team_count' => $teamRequests->count(),
            'club_count' => $membershipRequests->pluck('club_id')->merge($teamRequestClubIds)->unique()->count(),
        ];
    }

    private function payments(User $user, Collection $clubs): array
    {
        $clubIds = $clubs
            ->filter(fn (Club $club) => ClubPermissions::allows($club, $user, ClubPermissions::FINANCE_VIEW))
            ->pluck('id');
        if ($clubIds->isEmpty()) {
            return ['count' => 0, 'amount_cents' => 0, 'club_count' => 0];
        }

        $invoices = Invoice::query()
            ->whereIn('club_id', $clubIds)
            ->whereNotIn('status', ['paid', 'cancelled', 'void'])
            ->whereNotNull('due_date')
            ->where('due_date', '<', now())
            ->withSum('settledPayments', 'amount')
            ->get(['id', 'club_id', 'amount', 'status', 'due_date']);

        return [
            'count' => $invoices->count(),
            'amount_cents' => $invoices->sum(fn (Invoice $invoice) => $invoice->outstandingCents()),
            'club_count' => $invoices->pluck('club_id')->unique()->count(),
        ];
    }

    private function approvals(User $user, Collection $clubs): array
    {
        $clubIds = $clubs->pluck('id');
        $inventory = collect();
        if (Schema::hasTable('club_inventory_loans')) {
            $approvableItemIds = ClubInventoryItem::query()
                ->whereIn('club_id', $clubIds)
                ->with('club')
                ->get()
                ->filter(fn (ClubInventoryItem $item) => ClubPermissions::allowsForInventoryItem(
                    $item,
                    $user,
                    ClubPermissions::INVENTORY_APPROVE,
                ))
                ->pluck('id');
            $inventory = $approvableItemIds->isEmpty()
                ? collect()
                : ClubInventoryLoan::query()
                    ->whereIn('club_id', $clubIds)
                    ->whereIn('club_inventory_item_id', $approvableItemIds)
                    ->where('status', 'pending')
                    ->where(fn ($query) => $query
                        ->where(fn ($explicitRequester) => $explicitRequester
                            ->whereNotNull('requested_by')
                            ->where('requested_by', '!=', $user->id))
                        ->orWhere(fn ($legacyRequester) => $legacyRequester
                            ->whereNull('requested_by')
                            ->where('borrower_id', '!=', $user->id)))
                    ->get(['id', 'club_id']);
        }

        $handovers = collect();
        if (Schema::hasTable('club_access_handover_reviews')) {
            $manageableClubIds = $clubs
                ->filter(fn (Club $club) => ClubPermissions::editableBy($club, $user))
                ->pluck('id');
            if ($manageableClubIds->isNotEmpty()) {
                $handovers = ClubAccessHandoverReview::query()
                    ->whereIn('club_id', $manageableClubIds)
                    ->where('status', 'proposed')
                    ->where('proposed_by', '!=', $user->id)
                    ->get(['id', 'club_id']);
            }
        }

        return [
            'count' => $inventory->count() + $handovers->count(),
            'inventory_count' => $inventory->count(),
            'handover_count' => $handovers->count(),
            'club_count' => $inventory->pluck('club_id')->merge($handovers->pluck('club_id'))->unique()->count(),
        ];
    }

    private function missingDocuments(User $user, Collection $clubs): array
    {
        $approvableClubs = $clubs
            ->filter(fn (Club $club) => ClubPermissions::allows($club, $user, ClubPermissions::MEMBERS_APPROVE));
        if ($approvableClubs->isEmpty()) {
            return ['count' => 0, 'request_count' => 0, 'club_count' => 0];
        }

        $requests = ClubMembershipRequest::query()
            ->whereIn('club_id', $approvableClubs->pluck('id'))
            ->where('status', 'pending')
            ->get(['id', 'club_id', 'club_membership_type_id', 'accepted_documents']);
        $clubById = $approvableClubs->keyBy('id');
        $missingCount = 0;
        $affected = collect();
        $affectedRequests = collect();

        foreach ($requests as $request) {
            $club = $clubById->get($request->club_id);
            if (! $club) {
                continue;
            }

            $required = collect(ClubMembershipApplication::normalizeDocuments(
                $club->membership_application_documents,
                $club->membership_application_document_types,
            ))
                ->filter(fn (array $document) => ($document['is_required'] ?? false)
                    && ($document['is_visible'] ?? false)
                    && (empty($document['membership_type_id'])
                        || (int) $document['membership_type_id'] === (int) $request->club_membership_type_id));
            $accepted = collect($request->accepted_documents ?: [])->keyBy(fn (array $document) => (string) ($document['id'] ?? ''));
            $requestMissing = $required->filter(function (array $document) use ($accepted) {
                $confirmation = $accepted->get((string) $document['id']);

                return ! is_array($confirmation)
                    || ($confirmation['version'] ?? null) !== ClubMembershipApplication::documentVersion($document);
            })->count();

            if ($requestMissing > 0) {
                $missingCount += $requestMissing;
                $affected->push($request->club_id);
                $affectedRequests->push($request->id);
            }
        }

        return [
            'count' => $missingCount,
            'request_count' => $affectedRequests->unique()->count(),
            'club_count' => $affected->unique()->count(),
        ];
    }

    private function deadlines(User $user, Collection $clubs): array
    {
        $canViewContracts = $user->can('finance.view')
            || $user->can('finance.edit')
            || $user->can('billing.manage')
            || $user->can('system.manage');
        $contractCount = $canViewContracts && Schema::hasTable('operating_contracts')
            ? OperatingContract::query()
                ->where('status', 'active')
                ->where(function ($query) {
                    $query->whereDate('notice_until_on', '<=', today()->addDays(60))
                        ->orWhereDate('ends_on', '<=', today()->addDays(90));
                })
                ->count()
            : 0;

        $policyClubIds = $clubs
            ->filter(fn (Club $club) => ClubPermissions::allows($club, $user, ClubPermissions::POLICY_DOCUMENTS_VIEW))
            ->pluck('id');
        $policyDocuments = $policyClubIds->isNotEmpty() && Schema::hasTable('club_policy_documents')
            ? ClubPolicyDocument::query()
                ->whereIn('club_id', $policyClubIds)
                ->whereNotNull('valid_until')
                ->whereDate('valid_until', '>=', today())
                ->whereDate('valid_until', '<=', today()->addDays(60))
                ->get(['id', 'club_id'])
            : collect();

        $legalClubs = $clubs
            ->filter(fn (Club $club) => ClubPermissions::allows($club, $user, ClubPermissions::CLUB_LEGAL_EDIT));
        $legalCount = 0;
        $legalClubIds = collect();
        foreach ($legalClubs as $club) {
            if ($club->tax_exemption_valid_until?->lte(today()->addDays(90))) {
                $legalCount++;
                $legalClubIds->push($club->id);
            }
            foreach ($club->federation_affiliations ?: [] as $affiliation) {
                $validUntil = data_get($affiliation, 'valid_until');
                if ($validUntil && Carbon::parse($validUntil)->startOfDay()->lte(today()->addDays(90))) {
                    $legalCount++;
                    $legalClubIds->push($club->id);
                }
            }
        }

        $licenseCount = 0;
        $licenseClubIdsWithWarnings = collect();
        $licenseClubIds = $clubs
            ->filter(fn (Club $club) => ClubPermissions::allows($club, $user, ClubPermissions::MEMBERS_VIEW))
            ->pluck('id');
        if ($licenseClubIds->isNotEmpty()
            && Schema::hasColumn('users', 'athlete_license_valid_until')
            && Schema::hasColumn('club_external_members', 'athlete_license_valid_until')) {
            $linkedUsers = User::query()
                ->whereNotNull('athlete_license_number')
                ->whereNotNull('athlete_license_valid_until')
                ->whereDate('athlete_license_valid_until', '<=', today()->addDays(90))
                ->whereHas('clubs', fn ($query) => $query
                    ->whereIn('clubs.id', $licenseClubIds)
                    ->where(fn ($membership) => $membership
                        ->whereNull('club_user.membership_status')
                        ->orWhere('club_user.membership_status', 'active')))
                ->pluck('id');
            $externalLicenses = ClubExternalMember::query()
                ->whereIn('club_id', $licenseClubIds)
                ->where('membership_status', 'active')
                ->whereNotNull('athlete_license_number')
                ->whereNotNull('athlete_license_valid_until')
                ->whereDate('athlete_license_valid_until', '<=', today()->addDays(90))
                ->get(['id', 'club_id']);
            $linkedLicenseClubIds = Club::query()
                ->whereIn('id', $licenseClubIds)
                ->whereHas('users', fn ($query) => $query
                    ->whereIn('users.id', $linkedUsers)
                    ->where(fn ($membership) => $membership
                        ->whereNull('club_user.membership_status')
                        ->orWhere('club_user.membership_status', 'active')))
                ->pluck('id');

            $licenseCount = $linkedUsers->count() + $externalLicenses->count();
            $licenseClubIdsWithWarnings = $linkedLicenseClubIds
                ->merge($externalLicenses->pluck('club_id'))
                ->unique();
        }

        $clubIds = $policyDocuments->pluck('club_id')
            ->merge($legalClubIds)
            ->merge($licenseClubIdsWithWarnings)
            ->unique();
        $firstClub = $clubs->firstWhere('id', $clubIds->first());

        return [
            'count' => $contractCount + $policyDocuments->count() + $legalCount + $licenseCount,
            'contract_count' => $contractCount,
            'policy_document_count' => $policyDocuments->count(),
            'legal_record_count' => $legalCount,
            'license_count' => $licenseCount,
            'club_count' => $clubIds->count(),
            'href' => $contractCount > 0
                ? route('admin.operating-contracts.index')
                : ($firstClub ? route('auth.clubs.show', $firstClub) : route('auth.dashboard')),
        ];
    }

    private function tasks(User $user, Collection $clubs): array
    {
        if ($clubs->isEmpty()) {
            return ['count' => 0, 'team_count' => 0, 'href' => null];
        }

        $memberTeamIds = Team::query()
            ->whereIn('club_id', $clubs->pluck('id'))
            ->whereHas('users', fn ($query) => $query->where('users.id', $user->id))
            ->pluck('id');
        $teams = Team::query()
            ->whereIn('club_id', $clubs->pluck('id'))
            ->with('club')
            ->get()
            ->filter(fn (Team $team) => $memberTeamIds->contains($team->id) || $user->can('update', $team))
            ->take(20);
        $tasks = collect();

        foreach ($teams as $team) {
            $dailyLife = $this->teamDailyLife->forTeam($team, $user);
            $teamTasks = collect(data_get($dailyLife, 'tasks.items', []))
                ->reject(fn (array $task) => ($task['key'] ?? null) === 'team_routine_stable');
            if ($teamTasks->isEmpty()) {
                continue;
            }

            $tasks->push([
                'team_id' => $team->id,
                'count' => $teamTasks->count(),
                'href' => data_get($dailyLife, 'today.primary_action.href'),
            ]);
        }

        return [
            'count' => $tasks->sum('count'),
            'team_count' => $tasks->pluck('team_id')->unique()->count(),
            'href' => $tasks->pluck('href')->filter()->first(),
        ];
    }

    private function payload(
        array $applications = ['count' => 0, 'membership_count' => 0, 'team_count' => 0, 'club_count' => 0],
        array $payments = ['count' => 0, 'amount_cents' => 0, 'club_count' => 0],
        array $approvals = ['count' => 0, 'inventory_count' => 0, 'handover_count' => 0, 'club_count' => 0],
        array $documents = ['count' => 0, 'request_count' => 0, 'club_count' => 0],
        array $deadlines = ['count' => 0, 'contract_count' => 0, 'policy_document_count' => 0, 'legal_record_count' => 0, 'license_count' => 0, 'club_count' => 0, 'href' => null],
        array $tasks = ['count' => 0, 'team_count' => 0, 'href' => null],
    ): array {
        $items = collect([
            [
                'key' => 'applications',
                ...$applications,
                'href' => route('auth.club-memberships.index'),
                'icon' => 'las la-user-check',
                'tone' => 'amber',
            ],
            [
                'key' => 'payments',
                ...$payments,
                'href' => route('auth.club-memberships.index'),
                'icon' => 'las la-file-invoice-dollar',
                'tone' => 'rose',
            ],
            [
                'key' => 'approvals',
                ...$approvals,
                'href' => ($approvals['inventory_count'] ?? 0) > 0
                    ? route('auth.club-inventory.index')
                    : route('auth.club-memberships.index'),
                'icon' => 'las la-clipboard-check',
                'tone' => 'sky',
            ],
            [
                'key' => 'documents',
                ...$documents,
                'href' => route('auth.club-memberships.index'),
                'icon' => 'las la-file-circle-exclamation',
                'tone' => 'amber',
            ],
            [
                'key' => 'deadlines',
                ...$deadlines,
                'href' => $deadlines['href'] ?: route('auth.dashboard'),
                'icon' => 'las la-hourglass-half',
                'tone' => 'rose',
            ],
            [
                'key' => 'tasks',
                ...$tasks,
                'href' => $tasks['href'] ?: route('auth.teams.index'),
                'icon' => 'las la-list-check',
                'tone' => 'sky',
            ],
        ])->filter(fn (array $item) => $item['count'] > 0)->values()->all();

        return [
            'total' => $applications['count'] + $payments['count'] + $approvals['count']
                + $documents['count'] + $deadlines['count'] + $tasks['count'],
            'items' => $items,
        ];
    }
}

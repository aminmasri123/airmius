<?php

namespace App\Services;

use App\Models\Sponsor;
use App\Models\SponsorActivityLog;
use App\Models\SponsorMembership;
use App\Models\User;
use App\Models\UserRoleApplication;
use App\Support\AppNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class SponsorOrganizationService
{
    public function canAccess(User $user, bool $includePending = true): bool
    {
        if ($user->can('system.manage') || $user->hasRole('sponsor_manager')) {
            return true;
        }

        return Sponsor::query()->where('owner_user_id', $user->id)->exists()
            || SponsorMembership::query()
                ->where(function (Builder $query) use ($user): void {
                    $query->where('user_id', $user->id)
                        ->orWhereRaw('LOWER(email) = ?', [mb_strtolower($user->email)]);
                })
                ->whereIn('status', $includePending
                    ? [SponsorMembership::STATUS_ACTIVE, SponsorMembership::STATUS_PENDING]
                    : [SponsorMembership::STATUS_ACTIVE])
                ->exists();
    }

    public function organizations(User $user)
    {
        if ($user->can('system.manage') || $user->hasRole('sponsor_manager')) {
            return Sponsor::query()->where('scope', 'platform')->orderBy('name')->get();
        }

        return Sponsor::query()
            ->where(function (Builder $query) use ($user): void {
                $query->where('owner_user_id', $user->id)
                    ->orWhereHas('memberships', function (Builder $membership) use ($user): void {
                        $membership->where('status', SponsorMembership::STATUS_ACTIVE)
                            ->where(function (Builder $identity) use ($user): void {
                                $identity->where('user_id', $user->id)
                                    ->orWhereRaw('LOWER(email) = ?', [mb_strtolower($user->email)]);
                            });
                    });
            })
            ->orderBy('name')
            ->get();
    }

    public function resolve(User $user, ?int $requestedId = null): ?Sponsor
    {
        $organizations = $this->organizations($user);
        if ($requestedId) {
            return $organizations->firstWhere('id', $requestedId);
        }

        return $organizations->firstWhere('owner_user_id', $user->id) ?? $organizations->first();
    }

    public function role(User $user, Sponsor $sponsor): ?string
    {
        if ($user->can('system.manage') || $user->hasRole('sponsor_manager')) {
            return SponsorMembership::ROLE_OWNER;
        }
        if ((int) $sponsor->owner_user_id === (int) $user->id) {
            return SponsorMembership::ROLE_OWNER;
        }

        return SponsorMembership::query()
            ->where('sponsor_id', $sponsor->id)
            ->where('status', SponsorMembership::STATUS_ACTIVE)
            ->where(function (Builder $query) use ($user): void {
                $query->where('user_id', $user->id)
                    ->orWhereRaw('LOWER(email) = ?', [mb_strtolower($user->email)]);
            })
            ->value('role');
    }

    public function can(User $user, Sponsor $sponsor, string $ability): bool
    {
        $role = $this->role($user, $sponsor);
        if (! $role) {
            return false;
        }

        return in_array($ability, match ($role) {
            SponsorMembership::ROLE_OWNER => ['profile', 'partnerships', 'campaigns', 'finance', 'team', 'view'],
            SponsorMembership::ROLE_ADMINISTRATOR => ['profile', 'partnerships', 'campaigns', 'team', 'view'],
            SponsorMembership::ROLE_CAMPAIGN_MANAGER => ['campaigns', 'view'],
            SponsorMembership::ROLE_FINANCE => ['finance', 'view'],
            default => ['view'],
        }, true);
    }

    public function createForApprovedApplication(UserRoleApplication $application): Sponsor
    {
        return DB::transaction(function () use ($application): Sponsor {
            $application->loadMissing('user');
            $data = $application->application_data ?? [];
            $contactEmail = mb_strtolower((string) ($data['contact_email'] ?? $application->user->email));
            $companyName = trim((string) ($data['company_name'] ?? ''));
            $sponsor = Sponsor::query()
                ->where('owner_user_id', $application->user_id)
                ->first();
            $sponsor ??= Sponsor::query()
                ->where('scope', 'platform')
                ->whereNull('owner_user_id')
                ->where(function (Builder $query) use ($contactEmail, $companyName): void {
                    $query->whereRaw('LOWER(email) = ?', [$contactEmail]);
                    if ($companyName !== '') {
                        $query->orWhere('name', $companyName);
                    }
                })
                ->first();
            $sponsor ??= new Sponsor;
            $approved = $application->status === UserRoleApplication::STATUS_APPROVED;
            $sponsor->fill([
                'scope' => 'platform',
                'club_id' => null,
                'name' => $data['company_name'] ?? $sponsor->name ?? $application->user->name,
                'legal_name' => $data['legal_name'] ?? $sponsor->legal_name,
                'country_code' => strtoupper((string) ($data['country_code'] ?? $sponsor->country_code ?? 'DE')),
                'contact_name' => $data['contact_name'] ?? $sponsor->contact_name ?? $application->user->name,
                'email' => $data['contact_email'] ?? $sponsor->email ?? $application->user->email,
                'owner_user_id' => $application->user_id,
                'website' => $data['website'] ?? $sponsor->website,
                'accepted_rules' => [
                    'legal_accuracy' => (bool) ($data['legal_accuracy'] ?? false),
                    'data_privacy' => (bool) ($data['data_privacy'] ?? false),
                ],
                'verification_status' => $approved ? 'verified' : 'pending_review',
                'verified_by' => $approved ? $application->reviewed_by : null,
                'verified_at' => $approved ? ($application->reviewed_at ?? now()) : null,
            ]);
            $sponsor->save();

            SponsorMembership::query()->updateOrCreate(
                ['sponsor_id' => $sponsor->id, 'email' => mb_strtolower($application->user->email)],
                [
                    'user_id' => $application->user_id,
                    'role' => SponsorMembership::ROLE_OWNER,
                    'status' => SponsorMembership::STATUS_ACTIVE,
                    'accepted_at' => now(),
                ],
            );
            $this->audit($sponsor, $application->user, 'organization.created', [
                'application_id' => $application->id,
            ]);

            return $sponsor;
        });
    }

    public function ensureApprovedOrganization(User $user): ?Sponsor
    {
        $existing = Sponsor::query()
            ->where('owner_user_id', $user->id)
            ->where('scope', 'platform')
            ->first();
        if ($existing) {
            return $existing;
        }

        $application = UserRoleApplication::query()
            ->where('user_id', $user->id)
            ->where('type', UserRoleApplication::TYPE_SPONSOR)
            ->where('status', UserRoleApplication::STATUS_APPROVED)
            ->latest('reviewed_at')
            ->first();

        if (! $application && $user->hasRole('sponsor')) {
            $application = UserRoleApplication::query()
                ->where('user_id', $user->id)
                ->where('type', UserRoleApplication::TYPE_SPONSOR)
                ->whereNotIn('status', [
                    UserRoleApplication::STATUS_REJECTED,
                    UserRoleApplication::STATUS_WITHDRAWN,
                ])
                ->latest('id')
                ->first();
        }

        return $application ? $this->createForApprovedApplication($application) : null;
    }

    public function invite(User $actor, Sponsor $sponsor, string $email, string $role): SponsorMembership
    {
        abort_unless($this->can($actor, $sponsor, 'team'), 403);
        abort_if($role === SponsorMembership::ROLE_OWNER, 422, 'Die Inhaberrolle wird nur über einen bestätigten Inhaberwechsel vergeben.');
        if ($this->role($actor, $sponsor) !== SponsorMembership::ROLE_OWNER && $role === SponsorMembership::ROLE_ADMINISTRATOR) {
            abort(403, 'Nur der Inhaber darf Administratoren ernennen.');
        }

        $normalizedEmail = mb_strtolower(trim($email));
        $existing = SponsorMembership::query()
            ->where('sponsor_id', $sponsor->id)
            ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
            ->first();
        abort_if(
            $existing?->status === SponsorMembership::STATUS_ACTIVE,
            422,
            'Diese Person besitzt bereits einen aktiven Zugang.',
        );
        $user = User::query()->whereRaw('LOWER(email) = ?', [$normalizedEmail])->first();
        $membership = SponsorMembership::query()->updateOrCreate(
            ['sponsor_id' => $sponsor->id, 'email' => $normalizedEmail],
            [
                'user_id' => $user?->id,
                'role' => $role,
                'status' => SponsorMembership::STATUS_PENDING,
                'invited_by' => $actor->id,
                'accepted_at' => null,
            ],
        );
        $this->audit($sponsor, $actor, 'member.invited', ['email' => $normalizedEmail, 'role' => $role]);

        if ($user) {
            AppNotification::send($user, 'sponsor.team_invitation', [
                'title' => 'Einladung zu '.$sponsor->name,
                'body' => $actor->name.' hat dich in den Sponsor-Arbeitsbereich eingeladen.',
                'url' => route('auth.sponsor-workspace.index', ['sponsor_id' => $sponsor->id]),
                'action_url' => route('auth.sponsor-workspace.index', ['sponsor_id' => $sponsor->id]),
                'mobile_url' => 'airmius://sponsors?sponsor_id='.$sponsor->id,
                'sponsor_id' => $sponsor->id,
                'membership_id' => $membership->id,
            ]);
        }

        return $membership;
    }

    public function answerInvitation(User $user, SponsorMembership $membership, bool $accept): void
    {
        abort_unless($membership->status === SponsorMembership::STATUS_PENDING, 409);
        abort_unless(mb_strtolower($membership->email) === mb_strtolower($user->email), 403);

        $membership->update([
            'user_id' => $user->id,
            'status' => $accept ? SponsorMembership::STATUS_ACTIVE : SponsorMembership::STATUS_DECLINED,
            'accepted_at' => $accept ? now() : null,
        ]);
        if ($accept && ! $user->hasRole('sponsor')) {
            $user->assignRole('sponsor');
        }
        $this->audit($membership->sponsor, $user, $accept ? 'member.joined' : 'member.declined', [
            'membership_id' => $membership->id,
        ]);
    }

    public function updateRole(User $actor, SponsorMembership $membership, string $role): void
    {
        abort_unless($this->can($actor, $membership->sponsor, 'team'), 403);
        abort_if($membership->role === SponsorMembership::ROLE_OWNER || $role === SponsorMembership::ROLE_OWNER, 422);
        if ($this->role($actor, $membership->sponsor) !== SponsorMembership::ROLE_OWNER && $role === SponsorMembership::ROLE_ADMINISTRATOR) {
            abort(403);
        }
        $previous = $membership->role;
        $membership->update(['role' => $role]);
        $this->audit($membership->sponsor, $actor, 'member.role_changed', [
            'membership_id' => $membership->id,
            'from' => $previous,
            'to' => $role,
        ]);
    }

    public function remove(User $actor, SponsorMembership $membership, bool $confirmed): void
    {
        abort_unless($confirmed, 422, 'Bestätigung erforderlich.');
        abort_unless($this->can($actor, $membership->sponsor, 'team'), 403);
        abort_if($membership->role === SponsorMembership::ROLE_OWNER, 422, 'Der Inhaber kann nicht entfernt werden.');
        if ($this->role($actor, $membership->sponsor) !== SponsorMembership::ROLE_OWNER
            && $membership->role === SponsorMembership::ROLE_ADMINISTRATOR) {
            abort(403);
        }
        $metadata = ['membership_id' => $membership->id, 'email' => $membership->email, 'role' => $membership->role];
        $sponsor = $membership->sponsor;
        $removedUser = $membership->user;
        $membership->delete();
        if ($removedUser
            && ! Sponsor::query()->where('owner_user_id', $removedUser->id)->exists()
            && ! SponsorMembership::query()->where('user_id', $removedUser->id)->where('status', SponsorMembership::STATUS_ACTIVE)->exists()
            && ! $removedUser->roleApplications()->where('type', UserRoleApplication::TYPE_SPONSOR)->where('status', UserRoleApplication::STATUS_APPROVED)->exists()) {
            $removedUser->removeRole('sponsor');
        }
        $this->audit($sponsor, $actor, 'member.removed', $metadata);
    }

    public function transferOwnership(User $actor, SponsorMembership $membership, bool $confirmed): void
    {
        abort_unless($confirmed, 422, 'Bestätigung erforderlich.');
        $sponsor = $membership->sponsor;
        abort_unless($this->role($actor, $sponsor) === SponsorMembership::ROLE_OWNER, 403);
        abort_unless(
            $membership->status === SponsorMembership::STATUS_ACTIVE && $membership->user_id,
            422,
            'Der neue Inhaber muss einen aktiven Mitarbeiterzugang besitzen.',
        );
        abort_if((int) $membership->user_id === (int) $actor->id, 422, 'Diese Person ist bereits Inhaber.');

        DB::transaction(function () use ($actor, $membership, $sponsor): void {
            SponsorMembership::query()->updateOrCreate(
                [
                    'sponsor_id' => $sponsor->id,
                    'email' => mb_strtolower($actor->email),
                ],
                [
                    'user_id' => $actor->id,
                    'role' => SponsorMembership::ROLE_ADMINISTRATOR,
                    'status' => SponsorMembership::STATUS_ACTIVE,
                    'accepted_at' => now(),
                ],
            );
            $membership->update(['role' => SponsorMembership::ROLE_OWNER]);
            $sponsor->update(['owner_user_id' => $membership->user_id]);
            $this->audit($sponsor, $actor, 'organization.owner_transferred', [
                'previous_owner_user_id' => $actor->id,
                'new_owner_user_id' => $membership->user_id,
                'membership_id' => $membership->id,
            ]);
        });

        AppNotification::send($membership->user, 'sponsor.ownership_transferred', [
            'title' => 'Du bist jetzt Inhaber von '.$sponsor->name,
            'body' => 'Die Inhaberschaft des Sponsor-Arbeitsbereichs wurde auf dich übertragen.',
            'url' => route('auth.sponsor-workspace.index', ['sponsor_id' => $sponsor->id]),
            'action_url' => route('auth.sponsor-workspace.index', ['sponsor_id' => $sponsor->id]),
            'mobile_url' => 'airmius://sponsors?sponsor_id='.$sponsor->id,
            'sponsor_id' => $sponsor->id,
        ]);
    }

    public function pendingInvitations(User $user)
    {
        return SponsorMembership::query()
            ->with('sponsor:id,name')
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($user->email)])
            ->where('status', SponsorMembership::STATUS_PENDING)
            ->latest('id')
            ->get();
    }

    public function audit(Sponsor $sponsor, ?User $actor, string $action, array $metadata = []): void
    {
        SponsorActivityLog::query()->create([
            'sponsor_id' => $sponsor->id,
            'actor_user_id' => $actor?->id,
            'action' => $action,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }
}

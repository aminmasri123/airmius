<?php

namespace App\Services;

use App\Models\Club;
use App\Models\User;
use App\Models\WebsiteRequest;
use App\Support\ClubActiveMembership;
use App\Support\ClubPermissions;
use App\Support\Roles;

class WebsiteProjectAccess
{
    public function requesterProjects(User $user)
    {
        $clubIds = Club::query()->where(fn ($q) => $q->where('owner_id', $user->id)
            ->orWhereHas('users', fn ($members) => $members->where('users.id', $user->id)))
            ->get()->filter(fn ($club) => ClubActiveMembership::allows($club, $user)
                && ClubPermissions::allows($club, $user, ClubPermissions::WEBSITE_REQUEST_CREATE))->pluck('id');

        return WebsiteRequest::query()->where(fn ($q) => $q->where(fn ($personal) => $personal->whereNull('club_id')->where('user_id', $user->id))
            ->orWhereIn('club_id', $clubIds));
    }

    public function admin(User $user): bool
    {
        return $user->hasAnyRole(Roles::FULL_ACCESS) || $user->can('subscriptions.manage')
            || $user->can('marketplace.manage') || $user->can('system.manage');
    }

    public function participant(User $user, WebsiteRequest $project): bool
    {
        return $project->club
            ? ClubActiveMembership::allows($project->club, $user)
                && ClubPermissions::allows($project->club, $user, ClubPermissions::WEBSITE_REQUEST_CREATE)
            : (int) $project->user_id === (int) $user->id;
    }

    public function view(User $user, WebsiteRequest $project): bool
    {
        return $this->participant($user, $project) || ($project->status !== 'draft' && $this->admin($user));
    }

    public function edit(User $user, WebsiteRequest $project): bool
    {
        return $this->participant($user, $project) && ! $project->processing_started_at && ! $project->withdrawn_at
            && in_array($project->status, ['draft', 'new', 'contacted', 'quoted'], true);
    }

    public function withdraw(User $user, WebsiteRequest $project): bool
    {
        return $this->participant($user, $project) && ! $project->withdrawn_at && ! in_array($project->status, ['done', 'cancelled'], true);
    }

    public function comment(User $user, WebsiteRequest $project): bool
    {
        return $this->view($user, $project) && ! in_array($project->status, ['draft', 'cancelled'], true);
    }
}

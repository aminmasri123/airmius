<?php

namespace App\Services;

use App\Models\User;
use App\Models\WebsiteRequest;
use App\Support\AppNotification;
use App\Support\Roles;

class WebsiteProjectNotifications
{
    public function send(WebsiteRequest $project, ?User $actor, string $event, string $key): void
    {
        if ($project->status === 'draft') {
            return;
        }
        $access = app(WebsiteProjectAccess::class);
        $toClub = in_array($event, ['comment', 'status'], true) && $actor && $access->admin($actor);
        $users = User::query()->with(['roles.permissions', 'permissions'])->where(function ($query) use ($project, $toClub) {
            if (! $toClub) {
                $permissions = ['subscriptions.manage', 'marketplace.manage', 'system.manage'];
                $query->whereHas('roles', fn ($roles) => $roles->whereIn('name', Roles::FULL_ACCESS)
                    ->orWhereHas('permissions', fn ($p) => $p->whereIn('name', $permissions)))
                    ->orWhereHas('permissions', fn ($p) => $p->whereIn('name', $permissions));
            } elseif ($project->club_id) {
                $query->whereIn('id', fn ($q) => $q->select('user_id')->from('club_user')->where('club_id', $project->club_id))
                    ->orWhere('id', $project->club?->owner_id);
            } else {
                $query->where('id', $project->user_id);
            }
        })->whereKeyNot($actor?->id ?? 0);
        $users->chunkById(100, function ($users) use ($project, $access, $toClub, $event, $key) {
            foreach ($users as $user) {
                if ($toClub ? ! $access->participant($user, $project) : ! $access->admin($user)) {
                    continue;
                }
                AppNotification::sendLocalized($user, 'club.website_project.'.$event,
                    'website_project.notification_title', 'website_project.notification_body', [], [
                        'website_request_id' => $project->id,
                        'url' => '/commerce/website-projects/'.$project->id,
                        'mobile_url' => 'airmius://website-projects/'.$project->id,
                    ], ['after_commit' => true, 'dedupe_key' => $key]);
            }
        });
    }
}

<?php

namespace App\Services;

use App\Models\SupportTicket;
use App\Models\User;
use App\Support\AppNotification;
use App\Support\ClubPermissions;
use App\Support\NotificationRouting;
use App\Support\Roles;
use Illuminate\Database\Eloquent\Builder;

class SupportInboxNotificationService
{
    public function __construct(private readonly SupportAccessService $access) {}

    public function created(SupportTicket $ticket): void
    {
        $this->candidates($ticket)->whereKeyNot($ticket->user_id ?? 0)->chunkById(100, function ($users) use ($ticket): void {
            foreach ($users as $user) {
                if (! $this->access->canAccessTicket($user, $ticket)) {
                    continue;
                }
                if ($user->hasAnyRole(Roles::FULL_ACCESS)
                    && ! NotificationRouting::preferencesFor($user)['support_admin_alerts']) {
                    continue;
                }
                $this->send($user, $ticket, 'new', 'new:'.$ticket->id);
            }
        });
    }

    public function assignees(SupportTicket $ticket): array
    {
        return $this->candidates($ticket)->get()->filter(
            fn (User $user) => $this->access->canAccessTicket($user, $ticket, ClubPermissions::SUPPORT_EDIT),
        )->sortBy('name')->map(fn (User $user) => $user->only(['id', 'name']))->values()->all();
    }

    private function candidates(SupportTicket $ticket): Builder
    {
        // Include explicit platform operators, admins and members of the target
        // club; the access service validates every recipient's actual scope.
        return User::query()->where(function ($query) use ($ticket): void {
            $query->whereHas('roles', fn ($roles) => $roles->whereIn('name', Roles::FULL_ACCESS)
                ->orWhereHas('permissions', fn ($permissions) => $permissions->whereIn('name', ['support.tickets', 'system.manage'])))
                ->orWhereHas('permissions', fn ($permissions) => $permissions->whereIn('name', ['support.tickets', 'system.manage']));
            if ($ticket->club_id) {
                $query->orWhereIn('id', fn ($members) => $members->select('user_id')->from('club_user')->where('club_id', $ticket->club_id))
                    ->orWhereIn('id', fn ($owners) => $owners->select('owner_id')->from('clubs')->where('id', $ticket->club_id));
            }
        })->with(['roles.permissions', 'permissions']);
    }

    public function assigned(SupportTicket $ticket): void
    {
        $user = $ticket->assigned_to ? User::find($ticket->assigned_to) : null;
        if ($user && $this->access->canAccessTicket($user, $ticket, ClubPermissions::SUPPORT_EDIT)) {
            $this->send($user, $ticket, 'assigned');
        }
    }

    private function send(User $user, SupportTicket $ticket, string $event, ?string $dedupe = null): void
    {
        // Notification previews never contain reporter identities or case text.
        AppNotification::sendLocalized($user, 'support.inbox.'.$event,
            'support.inbox.'.$event.'_title', 'support.inbox.notification_body', [], [
                'url' => '/support?tab=operations&inbox='.($ticket->is_confidential ? 'safety' : 'general').'&ticket='.$ticket->id,
                'mobile_url' => 'airmius://support?ticket='.$ticket->id,
                'support_ticket_id' => $ticket->id,
            ], ['after_commit' => true, 'dedupe_key' => $dedupe]);
    }
}

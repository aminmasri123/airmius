<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\EventResource;
use App\Models\Event;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function index(Request $request)
    {
        $events = $this->visibleEvents($request)
            ->with(['club', 'team', 'user'])
            ->withCount(['participants', 'comments'])
            ->when($request->filled('from'), fn ($query) => $query->where('start_time', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->where('start_time', '<=', $request->date('to')))
            ->orderBy('start_time')
            ->paginate($this->perPage($request));

        return EventResource::collection($events);
    }

    public function show(Request $request, Event $event)
    {
        abort_unless($this->visibleEvents($request)->whereKey($event->id)->exists(), 404);

        return new EventResource(
            $event->loadMissing(['club', 'team', 'user'])->loadCount(['participants', 'comments'])
        );
    }

    private function visibleEvents(Request $request)
    {
        $user = $request->user();
        $clubIds = $user->clubs()->pluck('clubs.id')->all();
        $teamIds = $user->teams()->pluck('teams.id')->all();

        return Event::query()->where(function ($query) use ($user, $clubIds, $teamIds) {
            $query
                ->where('visibility', 'public')
                ->orWhere('user_id', $user->id)
                ->orWhereIn('club_id', $clubIds)
                ->orWhereIn('team_id', $teamIds)
                ->orWhereHas('participants', fn ($participants) => $participants->where('users.id', $user->id));
        });
    }

    private function perPage(Request $request): int
    {
        return min(max((int) $request->integer('per_page', 20), 1), 50);
    }
}

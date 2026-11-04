<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\AppNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class TrainerResignationController extends Controller
{
    private function teams(User $user)
    {
        return $user->teams()->wherePivot('role', 'Coach');
    }

    public function index(Request $request)
    {
        $data = ['has_role' => $request->user()->hasRole('coach'),
            'teams' => $this->teams($request->user())->get(['teams.id', 'teams.name'])];
        return $request->expectsJson() ? response()->json($data) : Inertia::render('Auth/Dashboard/TrainerCockpit/Resignation', $data);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['confirmed' => ['required', 'accepted'], 'team_id' => ['nullable', 'integer', 'min:1']]);
        DB::transaction(function () use ($request, $data) {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            if (! empty($data['team_id'])) {
                $team = $this->teams($user)->where('teams.id', $data['team_id'])->first();
                abort_unless($team, 403);
                $user->teams()->updateExistingPivot($team->id, ['role' => 'Player']);
                $title = 'Trainer-Rücktritt im Team';
                $body = $user->name.' ist als Trainer von „'.$team->name.'“ zurückgetreten. Die Teammitgliedschaft bleibt als Spieler bestehen.';
                $recipients = $team->users()->wherePivotIn('role', ['Coach', 'ClubPresident'])->get();
                if ($team->club?->owner_id) {
                    $owner = User::find($team->club->owner_id);
                    if ($owner) $recipients->push($owner);
                }
            } else {
                if ($this->teams($user)->exists()) {
                    throw ValidationException::withMessages(['team_id' => 'Bitte tritt zuerst bei deinen Teams als Trainer zurück. Deine Mitgliedschaft bleibt dabei bestehen.']);
                }
                if (! $user->hasRole('coach')) return;
                $user->removeRole('coach');
                $user->roleApplications()->where('type', 'trainer')->whereIn('status', ['pending', 'approved'])
                    ->update(['status' => 'withdrawn', 'role_activated' => false]);
                $title = 'Trainerrolle aufgegeben';
                $body = $user->name.' hat die allgemeine Trainerrolle aufgegeben. Das Nutzerkonto und andere Rollen bleiben erhalten.';
                $recipients = User::permission('system.manage')->get();
            }
            $recipients->push($user);
            foreach ($recipients->unique('id') as $recipient) {
                AppNotification::send($recipient, 'role.trainer_resigned', [
                    'title' => $title, 'body' => $body, 'url' => '/notifications',
                    'team_id' => $data['team_id'] ?? null, 'actor_id' => $user->id,
                ]);
            }
        });
        return $request->expectsJson() ? response()->json(['message' => 'Der Rücktritt wurde gespeichert.']) : back()->with('success', 'Der Rücktritt wurde gespeichert.');
    }
}

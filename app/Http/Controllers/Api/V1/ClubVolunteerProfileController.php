<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\ClubVolunteerProfile;
use App\Models\User;
use App\Support\AppNotification;
use App\Support\ClubPermissions;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClubVolunteerProfileController extends Controller
{
    public function showMine(Request $request, Club $club)
    {
        $this->authorizeMember($request, $club, $request->user());

        $profile = $club->volunteerProfiles()->where('user_id', $request->user()->id)->first();

        return response()->json([
            'data' => $profile ? $this->payload($profile) : null,
            'teams' => $club->teams()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function updateMine(Request $request, Club $club)
    {
        $this->authorizeMember($request, $club, $request->user());

        $data = $this->validated($request, $club);
        $existing = $club->volunteerProfiles()->where('user_id', $request->user()->id)->first();
        $wasVisible = $existing && $existing->visibility !== ClubVolunteerProfile::VISIBILITY_PRIVATE;
        $changed = ! $existing || collect(['skills', 'interests', 'availability', 'preferred_team_ids', 'preferred_areas', 'visibility'])
            ->contains(fn (string $key) => $existing->{$key} !== $data[$key]);

        $profile = $club->volunteerProfiles()->updateOrCreate(
            ['user_id' => $request->user()->id],
            $data
        );

        if ($changed && $profile->visibility !== ClubVolunteerProfile::VISIBILITY_PRIVATE) {
            User::query()->whereKey($club->owner_id)
                ->orWhereIn('id', $club->users()->pluck('users.id'))
                ->get(['id', 'language'])
                ->filter(fn (User $manager) => (int) $manager->id !== (int) $request->user()->id
                    && $this->canManageCandidates($club, $manager))
                ->each(fn (User $manager) => AppNotification::sendLocalized(
                    $manager,
                    'club.volunteer_candidate',
                    $wasVisible ? 'volunteer_candidate.updated_title' : 'volunteer_candidate.new_title',
                    'volunteer_candidate.body',
                    ['name' => $request->user()->name, 'club' => $club->name],
                    ['club_id' => (int) $club->id, 'user_id' => (int) $request->user()->id],
                ));
        }

        return response()->json(['data' => $this->payload($profile->refresh())]);
    }

    public function candidates(Request $request, Club $club)
    {
        abort_unless($request->user() instanceof User && $this->canManageCandidates($club, $request->user()), 403);

        $activeIds = $club->users()
            ->where(fn ($query) => $query->whereNull('membership_status')->orWhere('membership_status', 'active'))
            ->pluck('users.id');

        $profiles = $club->volunteerProfiles()
            ->with('user:id,name')
            ->whereIn('user_id', $activeIds)
            ->where('visibility', '!=', ClubVolunteerProfile::VISIBILITY_PRIVATE)
            ->latest('updated_at')
            ->get();

        return response()->json(['data' => $profiles->map(fn (ClubVolunteerProfile $profile) => [
            ...$this->payload($profile),
            'name' => $profile->user?->name,
            'updated_at' => $profile->updated_at?->toIso8601String(),
        ])]);
    }

    public function showMember(Request $request, Club $club, User $user)
    {
        $profile = $club->volunteerProfiles()->where('user_id', $user->id)->firstOrFail();
        abort_unless($this->canView($request, $club, $profile), 403);

        return response()->json(['data' => $this->payload($profile)]);
    }

    private function validated(Request $request, Club $club): array
    {
        $data = $request->validate([
            'skills' => ['nullable', 'array', 'max:20'],
            'skills.*' => ['required', 'string', 'max:80', 'distinct:ignore_case'],
            'interests' => ['nullable', 'array', 'max:20'],
            'interests.*' => ['required', 'string', 'max:80', 'distinct:ignore_case'],
            'availability' => ['nullable', 'array', 'max:14'],
            'availability.*' => ['required', 'string', 'max:80', 'distinct:ignore_case'],
            'workload_limit_minutes_per_week' => ['nullable', 'integer', 'min:0', 'max:10080'],
            'visibility' => ['required', Rule::in(ClubVolunteerProfile::VISIBILITIES)],
            'preferred_team_ids' => ['nullable', 'array', 'max:50'],
            'preferred_team_ids.*' => ['integer', 'distinct', Rule::exists('teams', 'id')->where('club_id', $club->id)],
            'preferred_areas' => ['nullable', 'array', 'max:3'],
            'preferred_areas.*' => ['string', 'distinct', Rule::in(['all_teams', 'organization', 'administration'])],
        ]);

        foreach (['skills', 'interests', 'availability'] as $field) {
            $data[$field] = collect($data[$field] ?? [])
                ->map(fn (string $value) => trim($value))
                ->filter()
                ->unique(fn (string $value) => mb_strtolower($value))
                ->values()
                ->all();
        }

        $data['preferred_team_ids'] = array_map('intval', $data['preferred_team_ids'] ?? []);
        $data['preferred_areas'] = $data['preferred_areas'] ?? [];
        if (in_array('all_teams', $data['preferred_areas'], true)) {
            $data['preferred_team_ids'] = [];
        }

        if ($data['visibility'] !== ClubVolunteerProfile::VISIBILITY_PRIVATE) {
            if ($data['skills'] === [] || $data['availability'] === []
                || ($data['preferred_team_ids'] === [] && $data['preferred_areas'] === [])) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    ...($data['skills'] === [] ? ['skills' => 'Bitte mindestens eine Fähigkeit eintragen.'] : []),
                    ...($data['availability'] === [] ? ['availability' => 'Bitte Verfügbarkeit eintragen.'] : []),
                    ...($data['preferred_team_ids'] === [] && $data['preferred_areas'] === []
                        ? ['preferred_areas' => 'Bitte ein Team oder einen Aufgabenbereich wählen.'] : []),
                ]);
            }
        }

        return $data;
    }

    private function canManageCandidates(Club $club, User $user): bool
    {
        return ClubPermissions::editableBy($club, $user)
            || ClubPermissions::allows($club, $user, ClubPermissions::MEMBERS_MANAGE);
    }

    private function canView(Request $request, Club $club, ClubVolunteerProfile $profile): bool
    {
        $viewer = $request->user();
        if (! $viewer instanceof User) {
            return false;
        }

        if ((int) $profile->user_id === (int) $viewer->id) {
            return $this->isClubMember($club, $viewer);
        }

        if ($profile->visibility === ClubVolunteerProfile::VISIBILITY_PRIVATE) {
            return false;
        }

        if (ClubPermissions::editableBy($club, $viewer) || ClubPermissions::allows($club, $viewer, ClubPermissions::MEMBERS_MANAGE)) {
            return true;
        }

        return $profile->visibility === ClubVolunteerProfile::VISIBILITY_CLUB_MEMBERS
            && $this->isClubMember($club, $viewer);
    }

    private function authorizeMember(Request $request, Club $club, User $user): void
    {
        abort_unless($request->user() instanceof User && (int) $request->user()->id === (int) $user->id, 403);
        abort_unless($this->isClubMember($club, $user), 404);
    }

    private function isClubMember(Club $club, User $user): bool
    {
        return $club->users()
            ->where('users.id', $user->id)
            ->where(fn ($query) => $query->whereNull('membership_status')->orWhere('membership_status', 'active'))
            ->exists();
    }

    private function payload(ClubVolunteerProfile $profile): array
    {
        return [
            'id' => (int) $profile->id,
            'club_id' => (int) $profile->club_id,
            'user_id' => (int) $profile->user_id,
            'skills' => $profile->skills ?? [],
            'interests' => $profile->interests ?? [],
            'availability' => $profile->availability ?? [],
            'workload_limit_minutes_per_week' => $profile->workload_limit_minutes_per_week,
            'visibility' => $profile->visibility,
            'preferred_team_ids' => $profile->preferred_team_ids ?? [],
            'preferred_areas' => $profile->preferred_areas ?? [],
            'preferred_teams' => $profile->club->teams()->whereIn('id', $profile->preferred_team_ids ?? [])
                ->get(['id', 'name'])->map(fn ($team) => ['id' => (int) $team->id, 'name' => $team->name]),
        ];
    }
}

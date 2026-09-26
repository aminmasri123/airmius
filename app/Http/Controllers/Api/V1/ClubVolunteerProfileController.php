<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\ClubVolunteerProfile;
use App\Models\User;
use App\Support\ClubPermissions;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClubVolunteerProfileController extends Controller
{
    public function showMine(Request $request, Club $club)
    {
        $this->authorizeMember($request, $club, $request->user());

        $profile = $club->volunteerProfiles()->where('user_id', $request->user()->id)->first();

        return response()->json(['data' => $profile ? $this->payload($profile) : null]);
    }

    public function updateMine(Request $request, Club $club)
    {
        $this->authorizeMember($request, $club, $request->user());

        $profile = $club->volunteerProfiles()->updateOrCreate(
            ['user_id' => $request->user()->id],
            $this->validated($request)
        );

        return response()->json(['data' => $this->payload($profile->refresh())]);
    }

    public function showMember(Request $request, Club $club, User $user)
    {
        $profile = $club->volunteerProfiles()->where('user_id', $user->id)->firstOrFail();
        abort_unless($this->canView($request, $club, $profile), 403);

        return response()->json(['data' => $this->payload($profile)]);
    }

    private function validated(Request $request): array
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
        ]);

        foreach (['skills', 'interests', 'availability'] as $field) {
            $data[$field] = collect($data[$field] ?? [])
                ->map(fn (string $value) => trim($value))
                ->filter()
                ->unique(fn (string $value) => mb_strtolower($value))
                ->values()
                ->all();
        }

        return $data;
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
        ];
    }
}

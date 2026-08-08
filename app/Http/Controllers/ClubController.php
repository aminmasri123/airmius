<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Sponsor;
use App\Models\Sport;
use App\Models\User;
use App\Services\ClubProfilePayloadService;
use App\Services\ClubService;
use App\Services\MediaOptimizer;
use App\Services\PlanFeatureService;
use App\Support\AppNotification;
use App\Support\ClubRoles;
use App\Support\UploadStorage;
use App\Support\Validation\ClubProfileRules;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ClubController extends Controller
{
    use AuthorizesRequests;

    public const MEMBER_ROLES = ClubRoles::ALL;

    public function __construct(
        private ClubService $service,
        private MediaOptimizer $mediaOptimizer,
        private ClubProfilePayloadService $clubProfiles,
        private PlanFeatureService $planFeatures,
    ) {}

    public function index()
    {
        $this->authorize('viewAny', Club::class);

        $user = auth()->user();
        $clubs = Club::query()
            ->linkedToUser($user)
            ->with([
                'users:id,name,email,profile_photo_path',
                'teams' => fn ($query) => $query
                    ->whereHas('users', fn ($userQuery) => $userQuery->where('users.id', $user->id))
                    ->withCount('users')
                    ->with('users:id,name'),
            ])
            ->get()
            ->each(fn (Club $club) => $club->setAttribute('can_manage', $user->can('update', $club)));

        return Inertia::render('Auth/Dashboard/Teams/Index', [
            'clubs' => $clubs,
            'clubRoles' => self::MEMBER_ROLES,
            'sports' => Sport::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'category']),
        ]);
    }

    public function store(Request $request)
    {
        Gate::authorize('create', Club::class);

        $club = $this->service->create(auth()->user(), $request->validate(ClubProfileRules::store()));

        session(['club_id' => $club->id]);

        return back()->with('success', 'Verein registriert. Der Antrag wartet jetzt auf Prüfung.');
    }

    public function show(Request $request, Club $club)
    {
        $this->authorize('view', $club);

        return Inertia::render(
            'Auth/Dashboard/Clubs/Profile',
            $this->clubProfiles->forViewer($club, $request->user()),
        );
    }

    public function update(Request $request, Club $club)
    {
        $this->authorize('update', $club);

        $this->service->update($club, $request->validate(ClubProfileRules::update()));

        return back()->with('success', 'Club aktualisiert');
    }

    public function storeSponsor(Request $request, Club $club)
    {
        $this->authorize('update', $club);
        $this->planFeatures->ensureAllows($club, 'sponsors');

        $club->sponsors()->create($this->sponsorData($request) + [
            'scope' => 'club',
        ]);

        return back()->with('success', 'Sponsor erstellt.');
    }

    public function updateSponsor(Request $request, Club $club, Sponsor $sponsor)
    {
        $this->authorize('update', $club);
        abort_unless((int) $sponsor->club_id === (int) $club->id, 404);
        $this->planFeatures->ensureAllows($club, 'sponsors');

        $sponsor->update($this->sponsorData($request) + [
            'scope' => 'club',
            'club_id' => $club->id,
        ]);

        return back()->with('success', 'Sponsor aktualisiert.');
    }

    public function destroySponsor(Request $request, Club $club, Sponsor $sponsor)
    {
        $this->authorize('update', $club);
        abort_unless((int) $sponsor->club_id === (int) $club->id, 404);
        $this->planFeatures->ensureAllows($club, 'sponsors');

        $sponsor->delete();

        return back()->with('success', 'Sponsor gelöscht.');
    }

    private function sponsorData(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'logo_light' => ['nullable', 'string', 'max:2048'],
            'logo_dark' => ['nullable', 'string', 'max:2048'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        $fallbackLogo = ($data['logo_light'] ?? null) ?: ($data['logo_dark'] ?? null);

        return [
            ...$data,
            'logo' => $fallbackLogo,
            'logo_light' => ($data['logo_light'] ?? null) ?: $fallbackLogo,
            'logo_dark' => ($data['logo_dark'] ?? null) ?: $fallbackLogo,
        ];
    }

    public function updateMember(Request $request, Club $club, User $user)
    {
        $this->authorize('update', $club);

        abort_unless($club->users()->where('users.id', $user->id)->exists(), 404);

        $data = $request->validate([
            'role' => ['nullable', Rule::in(self::MEMBER_ROLES)],
            'roles' => ['nullable', 'array'],
            'roles.*' => [Rule::in(self::MEMBER_ROLES)],
        ]);

        $currentMembership = $club->users()->where('users.id', $user->id)->first()?->pivot;
        $previousRole = $currentMembership
            ? ClubRoles::primary(ClubRoles::normalize($currentMembership->role, $currentMembership->roles ?? []))
            : null;

        $roles = ClubRoles::normalize($data['role'] ?? null, $data['roles'] ?? []);
        $primaryRole = ClubRoles::primary($roles);

        abort_if(
            $club->owner_id === $user->id && ! in_array('owner', $roles, true),
            422,
            'Der aktuelle Owner kann nicht direkt herabgestuft werden. Weise zuerst einem anderen Mitglied die Rolle Owner zu.'
        );

        DB::transaction(function () use ($club, $user, $roles, $primaryRole) {
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
            ]);

            if (in_array('owner', $roles, true)) {
                $this->service->assignClubOwnerRole($user);

                if ($previousOwner) {
                    $this->service->refreshClubOwnerRole($previousOwner);
                }
            }
        });

        if ($previousRole !== $primaryRole) {
            $previousRoleLabel = filled($previousRole)
                ? (ClubRoles::LABELS[$previousRole] ?? $previousRole)
                : __('organization.roles.unknown');

            AppNotification::sendLocalized(
                $user,
                'club.member.role_updated',
                'organization.notifications.club_role_title',
                'organization.notifications.club_role_body',
                [
                    'club' => $club->name,
                    'previous' => filled($previousRole)
                        ? AppNotification::translatedReplacement('organization.roles.club.'.$previousRole, $previousRoleLabel)
                        : AppNotification::translatedReplacement('organization.roles.unknown', $previousRoleLabel),
                    'next' => AppNotification::translatedReplacement(
                        'organization.roles.club.'.$primaryRole,
                        ClubRoles::LABELS[$primaryRole] ?? $primaryRole,
                    ),
                ],
                [
                'url' => route('auth.club-memberships.index'),
                'club_id' => $club->id,
                'previous_role' => $previousRole,
                'role' => $primaryRole,
                'roles' => $roles,
                ],
            );
        }

        if ($request->expectsJson()) {
            return response()->json([
                'data' => [
                    'club_id' => $club->id,
                    'user_id' => $user->id,
                    'role' => $primaryRole,
                    'roles' => $roles,
                ],
            ]);
        }

        return back()->with('success', 'Vereinsrolle aktualisiert.');
    }

    public function updateImages(Request $request, Club $club)
    {
        $this->authorize('update', $club);

        $data = $request->validate([
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'cover_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ]);

        abort_unless($request->hasFile('logo') || $request->hasFile('cover_image'), 422);

        $updates = [];

        if ($request->hasFile('logo')) {
            if ($club->logo) {
                Storage::disk(UploadStorage::disk())->delete($club->logo);
            }

            $updates['logo'] = $this->mediaOptimizer->store($request->file('logo'), 'clubs/'.$club->id.'/profile')['path'];
        }

        if ($request->hasFile('cover_image')) {
            if ($club->cover_image) {
                Storage::disk(UploadStorage::disk())->delete($club->cover_image);
            }

            $updates['cover_image'] = $this->mediaOptimizer->store($request->file('cover_image'), 'clubs/'.$club->id.'/profile')['path'];
        }

        $club->update($updates);

        return back()->with('success', 'Vereinsbilder aktualisiert.');
    }

    public function destroy(Club $club)
    {
        $this->authorize('delete', $club);

        $this->service->delete($club);

        return back();
    }
}

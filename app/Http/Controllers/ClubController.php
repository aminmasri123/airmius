<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Post;
use App\Models\Sport;
use App\Models\User;
use App\Services\ClubService;
use App\Services\MediaOptimizer;
use App\Support\UploadStorage;
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

    public const MEMBER_ROLES = ['owner', 'admin', 'manager', 'member'];

    public function __construct(
        private ClubService $service,
        private MediaOptimizer $mediaOptimizer,
    ) {}

    public function index()
    {
        $this->authorize('viewAny', Club::class);

        $user = auth()->user();
        $clubs = Club::query()
            ->where(function ($query) use ($user) {
                $query->whereHas('users', fn ($userQuery) => $userQuery->where('users.id', $user->id))
                    ->orWhereHas('teams.users', fn ($userQuery) => $userQuery->where('users.id', $user->id));
            })
            ->with([
                'users:id,name,email,profile_photo_path',
                'teams' => fn ($query) => $query
                    ->whereHas('users', fn ($userQuery) => $userQuery->where('users.id', $user->id))
                    ->withCount('users')
                    ->with('users:id,name,email'),
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

        $club = $this->service->create(auth()->user(), $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sport_type' => ['nullable', 'string', 'max:120'],
            'is_official' => ['boolean'],
            'official_club_number' => ['nullable', 'required_if:is_official,true,1', 'string', 'max:120'],
            'country' => ['required', 'string', 'size:2'],
            'street' => ['nullable', 'string', 'max:255'],
            'house_number' => ['nullable', 'string', 'max:40'],
            'postal_code' => ['nullable', 'string', 'max:30'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
        ]));

        session(['club_id' => $club->id]);

        return back()->with('success', 'Club erstellt');
    }

    public function show(Request $request, Club $club)
    {
        $this->authorize('view', $club);

        $viewer = $request->user();
        $isMember = $club->users()->where('users.id', $viewer->id)->exists();
        $canManage = $viewer->can('update', $club);

        $club->loadCount(['users', 'teams', 'posts']);
        $club->load([
            'admins:id,name,email,profile_photo_path',
            'users' => fn ($query) => $query
                ->select('users.id', 'name', 'email', 'profile_photo_path')
                ->orderBy('name'),
            'teams' => fn ($query) => $query
                ->withCount('users')
                ->orderBy('name')
                ->limit(12),
        ]);

        $posts = Post::query()
            ->where('club_id', $club->id)
            ->where('moderation_status', '!=', 'removed')
            ->where(function ($query) use ($viewer, $isMember) {
                $query->where('visibility', 'public')
                    ->orWhere('user_id', $viewer->id)
                    ->when($isMember, fn ($query) => $query->orWhere('visibility', 'organization'));
            })
            ->with(['user:id,name,profile_photo_path', 'team:id,name,club_id'])
            ->withCount([
                'comments' => fn ($query) => $query->where('moderation_status', '!=', 'removed'),
                'likes',
            ])
            ->latest('id')
            ->limit(8)
            ->get();

        return Inertia::render('Auth/Dashboard/Clubs/Profile', [
            'clubProfile' => [
                'id' => $club->id,
                'name' => $club->name,
                'sport_type' => $club->sport_type,
                'is_official' => (bool) $club->is_official,
                'official_club_number' => $club->official_club_number,
                'logo' => $club->logo,
                'cover_image' => $club->cover_image,
                'country' => $club->country,
                'street' => $club->street,
                'house_number' => $club->house_number,
                'postal_code' => $club->postal_code,
                'city' => $club->city,
                'state' => $club->state,
                'users_count' => $club->users_count,
                'teams_count' => $club->teams_count,
                'posts_count' => $club->posts_count,
                'admins' => $club->admins,
                'members' => $club->users,
                'teams' => $club->teams,
            ],
            'clubRoles' => self::MEMBER_ROLES,
            'posts' => $posts,
            'viewer' => [
                'is_member' => $isMember,
                'can_manage' => $canManage,
            ],
        ]);
    }

    public function update(Request $request, Club $club)
    {
        $this->authorize('update', $club);

        $this->service->update($club, $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sport_type' => ['nullable', 'string', 'max:120'],
            'is_official' => ['boolean'],
            'official_club_number' => ['nullable', 'required_if:is_official,true,1', 'string', 'max:120'],
            'logo' => ['nullable', 'string', 'max:255'],
            'country' => ['required', 'string', 'size:2'],
            'street' => ['nullable', 'string', 'max:255'],
            'house_number' => ['nullable', 'string', 'max:40'],
            'postal_code' => ['nullable', 'string', 'max:30'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
        ]));

        return back()->with('success', 'Club aktualisiert');
    }

    public function updateMember(Request $request, Club $club, User $user)
    {
        $this->authorize('update', $club);

        abort_unless($club->users()->where('users.id', $user->id)->exists(), 404);

        $data = $request->validate([
            'role' => ['required', Rule::in(self::MEMBER_ROLES)],
        ]);

        abort_if(
            $club->owner_id === $user->id && $data['role'] !== 'owner',
            422,
            'Der aktuelle Owner kann nicht direkt herabgestuft werden. Weise zuerst einem anderen Mitglied die Rolle Owner zu.'
        );

        DB::transaction(function () use ($club, $user, $data) {
            if ($data['role'] === 'owner') {
                $previousOwnerId = $club->owner_id;

                $club->forceFill(['owner_id' => $user->id])->save();

                if ($previousOwnerId && $previousOwnerId !== $user->id) {
                    $club->users()->updateExistingPivot($previousOwnerId, ['role' => 'admin']);
                }
            }

            $club->users()->updateExistingPivot($user->id, [
                'role' => $data['role'],
            ]);
        });

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

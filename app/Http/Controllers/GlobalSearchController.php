<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Event;
use App\Models\File;
use App\Models\LearningCourse;
use App\Models\MarketplaceProduct;
use App\Models\Team;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class GlobalSearchController extends Controller
{
    public function __invoke(Request $request)
    {
        $term = trim((string) $request->input('q'));

        if (mb_strlen($term) < 2) {
            return response()->json(['results' => []]);
        }

        $like = '%'.$term.'%';
        $user = $request->user();

        $users = User::query()
            ->where('id', '!=', $user->id)
            ->when(! $user->can('user.manage'), function ($query) use ($user) {
                $query->where(function ($visibilityQuery) use ($user) {
                    $visibilityQuery
                        ->where('profile_visibility', 'public')
                        ->orWhereHas('friendships', fn ($friendships) => $friendships
                            ->where('friend_id', $user->id))
                        ->orWhereHas('followers', fn ($followers) => $followers
                            ->where('follower_id', $user->id));
                });
            })
            ->where(function ($query) use ($like) {
                $query->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like);
            })
            ->orderBy('name')
            ->limit(5)
            ->get(['id', 'name', 'profile_visibility', 'profile_photo_path'])
            ->map(fn (User $match) => [
                'type' => 'user',
                'id' => $match->id,
                'title' => $match->name,
                'subtitle' => $match->profile_visibility === 'private' ? 'Privates Profil' : 'Profil',
                'url' => route('auth.users.show', $match->id),
                'avatar_url' => $match->profile_photo_thumb ?: $match->profile_photo_url,
            ]);

        $clubs = Club::query()
            ->when(
                ! (
                    $user->hasAnyRole(Roles::FULL_ACCESS)
                    || $user->can('clubs.view')
                    || $user->can('teams.view')
                ),
                function ($query) use ($user) {
                    $query->where(function ($innerQuery) use ($user) {
                        $innerQuery->where(fn ($publicClubQuery) => $publicClubQuery->verified()->where('is_listed', true))
                            ->orWhereHas('users', fn ($memberQuery) => $memberQuery->where('users.id', $user->id));
                    });
                }
            )
            ->where('name', 'like', $like)
            ->orderBy('name')
            ->limit(5)
            ->get(['id', 'name'])
            ->map(fn (Club $club) => [
                'type' => 'club',
                'id' => $club->id,
                'title' => $club->name,
                'subtitle' => 'Verein',
                'url' => route('auth.clubs.show', $club->id),
            ]);

        $teams = Team::query()
            ->with('club:id,name')
            ->visibleTo($user)
            ->where('name', 'like', $like)
            ->orderBy('name')
            ->limit(5)
            ->get(['id', 'club_id', 'name', 'sport_type'])
            ->map(fn (Team $team) => [
                'type' => 'team',
                'id' => $team->id,
                'title' => $team->name,
                'subtitle' => trim(($team->club?->name ?? 'Team').' · '.($team->sport_type ?? '')),
                'url' => route('auth.teams.show', $team->id),
                'join_url' => route('auth.teams.join-requests.store', $team->id),
            ]);

        $clubIds = $user->clubs()->pluck('clubs.id')->all();
        $teamIds = $user->teams()->pluck('teams.id')->all();

        $events = Event::query()
            ->with(['club:id,name', 'team:id,name'])
            ->where(function ($query) use ($user, $clubIds, $teamIds) {
                $query
                    ->where('visibility', 'public')
                    ->orWhere('user_id', $user->id)
                    ->orWhereIn('club_id', $clubIds)
                    ->orWhereIn('team_id', $teamIds)
                    ->orWhereHas('participants', fn ($participants) => $participants->where('users.id', $user->id));
            })
            ->where(function ($query) use ($like) {
                $query
                    ->where('title', 'like', $like)
                    ->orWhere('location', 'like', $like)
                    ->orWhere('location_name', 'like', $like)
                    ->orWhere('location_city', 'like', $like)
                    ->orWhere('notes', 'like', $like)
                    ->orWhereHas('club', fn ($clubQuery) => $clubQuery->where('name', 'like', $like))
                    ->orWhereHas('team', fn ($teamQuery) => $teamQuery->where('name', 'like', $like));
            })
            ->orderByDesc('start_time')
            ->limit(5)
            ->get();

        $courses = LearningCourse::query()
            ->where('status', 'published')
            ->where('is_public', true)
            ->where(function ($query) use ($like) {
                $query
                    ->where('title', 'like', $like)
                    ->orWhere('subtitle', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhere('category', 'like', $like);
            })
            ->orderByDesc('published_at')
            ->limit(5)
            ->get(['id', 'title', 'subtitle', 'category', 'cover_image', 'updated_at'])
            ->map(fn (LearningCourse $course) => [
                'type' => 'course',
                'id' => $course->id,
                'title' => $course->title,
                'subtitle' => $course->subtitle ?: ($course->category ?: 'Kurs'),
                'image_url' => $course->cover_image,
                'updated_at' => $course->updated_at?->toIso8601String(),
            ]);

        $products = MarketplaceProduct::query()
            ->where('status', 'published')
            ->where('moderation_status', 'approved')
            ->where(function ($query) use ($like) {
                $query
                    ->where('title', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhere('category', 'like', $like)
                    ->orWhere('sku', 'like', $like);
            })
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get(['id', 'title', 'description', 'category', 'image_url', 'price_cents', 'currency', 'updated_at'])
            ->map(fn (MarketplaceProduct $product) => [
                'type' => 'product',
                'id' => $product->id,
                'title' => $product->title,
                'subtitle' => $product->category ?: ($product->description ?: 'Produkt'),
                'image_url' => $product->image_url,
                'price_cents' => $product->price_cents,
                'currency' => $product->currency,
                'updated_at' => $product->updated_at?->toIso8601String(),
                'url' => route('auth.commerce.products.show', $product->id),
            ]);

        $files = File::query()
            ->with(['club:id,name', 'team:id,name', 'event:id,title'])
            ->where('display_name', 'like', $like)
            ->whereDoesntHave('messages')
            ->latest('updated_at')
            ->limit(30)
            ->get()
            ->filter(fn (File $file) => Gate::forUser($user)->allows('view', $file))
            ->take(5)
            ->map(fn (File $file) => [
                'type' => 'file',
                'id' => $file->id,
                'title' => $file->display_name,
                'subtitle' => $file->team?->name
                    ?: ($file->club?->name ?: ($file->event?->title ?: 'Datei')),
                'url' => route('auth.files.preview', $file->id),
                'file_url' => $file->url,
                'mime_type' => $file->type,
                'size' => $file->size,
                'updated_at' => $file->updated_at?->toIso8601String(),
            ]);

        $eventResults = $events->map(fn (Event $event) => [
            'type' => 'event',
            'id' => $event->id,
            'title' => $event->title,
            'subtitle' => trim(implode(' · ', array_filter([
                $event->team?->name ?: $event->club?->name,
                $event->start_time?->format('d.m.Y H:i'),
                $event->location_city ?: $event->location_name,
            ]))) ?: 'Event',
            'start_time' => $event->start_time?->toJSON(),
            'status' => $event->status,
            'visibility' => $event->visibility,
            'club_id' => $event->club_id,
            'team_id' => $event->team_id,
            'url' => route('auth.events.show', $event->id),
            'updated_at' => $event->updated_at?->toIso8601String(),
        ]);

        return response()->json([
            'results' => $users
                ->concat($clubs)
                ->concat($teams)
                ->concat($eventResults)
                ->concat($courses)
                ->concat($products)
                ->concat($files)
                ->sortByDesc('updated_at')
                ->take(30)
                ->values(),
        ]);
    }
}

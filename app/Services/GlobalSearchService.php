<?php

namespace App\Services;

use App\Models\Club;
use App\Models\Event;
use App\Models\File;
use App\Models\Invoice;
use App\Models\LearningCourse;
use App\Models\MarketplaceProduct;
use App\Models\Team;
use App\Models\User;
use App\Support\ClubPermissions;
use App\Support\Roles;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

final class GlobalSearchService
{
    public const VERSION = '2026-08-09.global-search.v3';

    public const MINIMUM_LENGTH = 2;

    public const MAXIMUM_LENGTH = 100;

    public const PER_TYPE_LIMIT = 4;

    public function __construct(private readonly SearchModuleCatalog $modules) {}

    /** @return Collection<int, array<string, mixed>> */
    public function search(User $user, string $term): Collection
    {
        $like = '%'.$term.'%';
        $groups = [
            $this->modules->search($user, $term, self::PER_TYPE_LIMIT),
            $this->users($user, $like),
            $this->clubs($user, $like),
            $this->teams($user, $like),
            $this->events($user, $like),
            $this->courses($like),
            $this->products($like),
            $this->files($user, $like),
            $this->invoices($user, $like),
        ];

        return $this->interleave($groups);
    }

    /** @return Collection<int, array<string, mixed>> */
    private function users(User $user, string $like): Collection
    {
        $canSearchEmail = $user->hasAnyRole(Roles::FULL_ACCESS)
            || $user->can('user.manage')
            || $user->can('users.view');

        return User::query()
            ->whereKeyNot($user->id)
            ->when(! $canSearchEmail, function (Builder $query) use ($user): void {
                $query->where(function (Builder $visibilityQuery) use ($user): void {
                    $visibilityQuery
                        ->where('profile_visibility', 'public')
                        ->orWhere(fn (Builder $friendsOnly) => $friendsOnly
                            ->where('profile_visibility', 'friends')
                            ->whereHas('friendships', fn (Builder $friendships) => $friendships->where('friend_id', $user->id)));
                });
            })
            ->where(function (Builder $query) use ($like, $canSearchEmail): void {
                $query->where('name', 'like', $like)
                    ->when($canSearchEmail, fn (Builder $emailQuery) => $emailQuery->orWhere('email', 'like', $like));
            })
            ->orderBy('name')
            ->limit(self::PER_TYPE_LIMIT)
            ->get()
            ->filter(fn (User $match) => $canSearchEmail || $match->isProfileVisibleTo($user))
            ->map(fn (User $match): array => [
                'type' => 'user',
                'type_label' => __('search.types.user'),
                'id' => $match->id,
                'title' => $match->name,
                'subtitle' => $match->profile_visibility === 'private'
                    ? __('search.subtitles.private_profile')
                    : __('search.types.user'),
                'url' => route('auth.users.show', $match->id),
                'avatar_url' => $match->profile_photo_thumb ?: $match->profile_photo_url,
            ]);
    }

    /** @return Collection<int, array<string, mixed>> */
    private function clubs(User $user, string $like): Collection
    {
        return Club::query()
            ->visibleTo($user)
            ->where('name', 'like', $like)
            ->orderBy('name')
            ->limit(self::PER_TYPE_LIMIT)
            ->get(['id', 'name'])
            ->map(fn (Club $club): array => [
                'type' => 'club',
                'type_label' => __('search.types.club'),
                'id' => $club->id,
                'title' => $club->name,
                'subtitle' => __('search.types.club'),
                'url' => route('auth.clubs.show', $club->id),
            ]);
    }

    /** @return Collection<int, array<string, mixed>> */
    private function teams(User $user, string $like): Collection
    {
        return Team::query()
            ->with('club:id,name')
            ->visibleTo($user)
            ->where('name', 'like', $like)
            ->orderBy('name')
            ->limit(self::PER_TYPE_LIMIT)
            ->get(['id', 'club_id', 'name', 'sport_type'])
            ->map(fn (Team $team): array => [
                'type' => 'team',
                'type_label' => __('search.types.team'),
                'id' => $team->id,
                'title' => $team->name,
                'subtitle' => trim(implode(' · ', array_filter([
                    $team->club?->name ?: __('search.types.team'),
                    $team->sport_type,
                ]))),
                'url' => route('auth.teams.show', $team->id),
                'join_url' => route('auth.teams.join-requests.store', $team->id),
            ]);
    }

    /** @return Collection<int, array<string, mixed>> */
    private function events(User $user, string $like): Collection
    {
        return Event::query()
            ->with(['club:id,name', 'team:id,name'])
            ->visibleTo($user)
            ->where(function (Builder $query) use ($like): void {
                $query
                    ->where('title', 'like', $like)
                    ->orWhere('location', 'like', $like)
                    ->orWhere('location_name', 'like', $like)
                    ->orWhere('location_city', 'like', $like)
                    ->orWhereHas('club', fn (Builder $clubQuery) => $clubQuery->where('name', 'like', $like))
                    ->orWhereHas('team', fn (Builder $teamQuery) => $teamQuery->where('name', 'like', $like));
            })
            ->orderByDesc('start_time')
            ->limit(self::PER_TYPE_LIMIT)
            ->get()
            ->map(fn (Event $event): array => [
                'type' => 'event',
                'type_label' => __('search.types.event'),
                'id' => $event->id,
                'title' => $event->title,
                'subtitle' => trim(implode(' · ', array_filter([
                    $event->team?->name ?: $event->club?->name,
                    $event->start_time?->format('d.m.Y H:i'),
                    $event->location_city ?: $event->location_name,
                ]))) ?: __('search.types.event'),
                'start_time' => $event->start_time?->toJSON(),
                'status' => $event->status,
                'visibility' => $event->visibility,
                'club_id' => $event->club_id,
                'team_id' => $event->team_id,
                'url' => route('auth.events.show', $event->id),
            ]);
    }

    /** @return Collection<int, array<string, mixed>> */
    private function courses(string $like): Collection
    {
        return LearningCourse::query()
            ->where('status', 'published')
            ->where('is_public', true)
            ->where(function (Builder $query) use ($like): void {
                $query
                    ->where('title', 'like', $like)
                    ->orWhere('subtitle', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhere('category', 'like', $like);
            })
            ->orderByDesc('published_at')
            ->limit(self::PER_TYPE_LIMIT)
            ->get(['id', 'title', 'subtitle', 'category', 'cover_image'])
            ->map(fn (LearningCourse $course): array => [
                'type' => 'course',
                'type_label' => __('search.types.course'),
                'id' => $course->id,
                'title' => $course->title,
                'subtitle' => $course->subtitle ?: ($course->category ?: __('search.types.course')),
                'image_url' => $course->cover_image,
                'url' => route('guest.learning.courses.show', $course),
            ]);
    }

    /** @return Collection<int, array<string, mixed>> */
    private function products(string $like): Collection
    {
        return MarketplaceProduct::query()
            ->where('status', 'published')
            ->where('moderation_status', 'approved')
            ->where(function (Builder $query) use ($like): void {
                $query
                    ->where('title', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhere('category', 'like', $like)
                    ->orWhere('sku', 'like', $like);
            })
            ->orderByDesc('updated_at')
            ->limit(self::PER_TYPE_LIMIT)
            ->get(['id', 'title', 'description', 'category', 'image_url', 'price_cents', 'currency'])
            ->map(fn (MarketplaceProduct $product): array => [
                'type' => 'product',
                'type_label' => __('search.types.product'),
                'id' => $product->id,
                'title' => $product->title,
                'subtitle' => $product->category ?: ($product->description ?: __('search.types.product')),
                'image_url' => $product->image_url,
                'price_cents' => $product->price_cents,
                'currency' => $product->currency,
                'url' => route('auth.commerce.products.show', $product->id),
            ]);
    }

    /** @return Collection<int, array<string, mixed>> */
    private function files(User $user, string $like): Collection
    {
        $query = File::query()
            ->with(['club:id,name', 'team:id,name', 'event:id,title'])
            ->where('display_name', 'like', $like)
            ->whereDoesntHave('messages');

        if (! $user->hasAnyRole(Roles::FULL_ACCESS)) {
            $query->where(function (Builder $visible) use ($user): void {
                $visible
                    ->where(function (Builder $personal) use ($user): void {
                        $personal->where('user_id', $user->id)
                            ->whereNull('club_id')
                            ->whereNull('team_id')
                            ->whereNull('event_id');
                    })
                    ->orWhereHas('club.users', fn (Builder $members) => $members->where('users.id', $user->id))
                    ->orWhereHas('team.users', fn (Builder $members) => $members->where('users.id', $user->id))
                    ->orWhereHas('event', fn (Builder $events) => $events->visibleTo($user));
            });
        }

        return $query
            ->latest('updated_at')
            ->limit(self::PER_TYPE_LIMIT * 2)
            ->get()
            ->filter(fn (File $file): bool => Gate::forUser($user)->allows('view', $file))
            ->take(self::PER_TYPE_LIMIT)
            ->map(fn (File $file): array => [
                'type' => 'file',
                'type_label' => __('search.types.file'),
                'id' => $file->id,
                'title' => $file->display_name,
                'subtitle' => $file->team?->name
                    ?: ($file->club?->name ?: ($file->event?->title ?: __('search.types.file'))),
                'url' => route('auth.files.preview', $file->id),
                'file_url' => $file->url,
                'mime_type' => $file->type,
                'size' => $file->size,
            ])
            ->values();
    }

    /** @return Collection<int, array<string, mixed>> */
    private function invoices(User $user, string $like): Collection
    {
        $clubIds = Club::query()
            ->linkedToUser($user)
            ->get()
            ->filter(fn (Club $club): bool => (bool) (ClubPermissions::effectiveFor($club, $user)[ClubPermissions::FINANCE_VIEW] ?? false))
            ->modelKeys();

        if ($clubIds === []) {
            return collect();
        }

        return Invoice::query()
            ->with('club:id,name')
            ->whereIn('club_id', $clubIds)
            ->where(function (Builder $query) use ($like): void {
                $query->where('number', 'like', $like)
                    ->orWhere('title', 'like', $like);
            })
            ->latest('id')
            ->limit(self::PER_TYPE_LIMIT)
            ->get(['id', 'club_id', 'number', 'title', 'amount', 'status'])
            ->map(fn (Invoice $invoice): array => [
                'type' => 'invoice',
                'type_label' => __('search.types.invoice'),
                'id' => $invoice->id,
                'title' => $invoice->number ?: $invoice->title,
                'subtitle' => trim(($invoice->club?->name ?? '').' · '.number_format((float) $invoice->amount, 2, ',', '.').' €'),
                'club_id' => $invoice->club_id,
                'status' => $invoice->status,
            ]);
    }

    /**
     * @param  array<int, Collection<int, array<string, mixed>>>  $groups
     * @return Collection<int, array<string, mixed>>
     */
    private function interleave(array $groups): Collection
    {
        $results = collect();

        for ($index = 0; $index < self::PER_TYPE_LIMIT; $index++) {
            foreach ($groups as $group) {
                if ($group->has($index)) {
                    $results->push($group->get($index));
                }
            }
        }

        return $results->values();
    }
}

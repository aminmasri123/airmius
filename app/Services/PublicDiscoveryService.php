<?php

namespace App\Services;

use App\Models\Club;
use App\Models\ClubContributionRule;
use App\Models\ClubMembershipType;
use App\Models\Event;
use App\Models\Sport;
use App\Models\Team;
use App\Support\UploadStorage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class PublicDiscoveryService
{
    private const CLUB_LIMIT = 60;

    private const RELATED_LIMIT = 18;

    private const CITY_LIMIT = 100;

    /** @return Collection<int, array<string, mixed>> */
    public function clubs(array $filters): Collection
    {
        return $this->publicClubs()
            ->with([
                'membershipTypes' => fn ($query) => $query
                    ->where('is_active', true)
                    ->where('is_public', true)
                    ->orderBy('sort_order')
                    ->orderBy('name'),
                'contributionRules' => fn ($query) => $query
                    ->effectiveOn(now()->toDateString())
                    ->where('is_active', true)
                    ->orderByDesc('valid_from'),
            ])
            ->withCount('teams')
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query
                ->where('name', 'like', "%{$search}%"))
            ->when($filters['sport_type'] ?? null, fn (Builder $query, string $sport) => $query
                ->where(function (Builder $query) use ($sport): void {
                    $query->where('sport_type', 'like', "%{$sport}%")
                        ->orWhere(function (Builder $query) use ($sport): void {
                            $query->where('teams_are_listed', true)
                                ->whereHas('teams', fn (Builder $teamQuery) => $teamQuery
                                    ->where('sport_type', 'like', "%{$sport}%"));
                        });
                }))
            ->when($filters['location'] ?? null, fn (Builder $query, string $location) => $query
                ->where(function (Builder $query) use ($location): void {
                    $query->where('city', 'like', "%{$location}%")
                        ->orWhere('postal_code', 'like', "%{$location}%")
                        ->orWhere('country', 'like', "%{$location}%");
                }))
            ->orderBy('name')
            ->limit(self::CLUB_LIMIT)
            ->get($this->clubColumns())
            ->map(fn (Club $club): array => $this->clubCard($club, true));
    }

    public function events(array $filters): LengthAwarePaginator
    {
        return $this->publicEvents(true)
            ->with($this->eventRelations())
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhereHas('club', fn (Builder $clubQuery) => $this->publicClubRelation($clubQuery)
                            ->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('team', fn (Builder $teamQuery) => $teamQuery
                            ->where('name', 'like', "%{$search}%")
                            ->whereHas('club', fn (Builder $clubQuery) => $this->publicClubRelation($clubQuery)
                                ->where('teams_are_listed', true)));
                });
            })
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type))
            ->when($filters['location'] ?? null, function (Builder $query, string $location): void {
                $query->where(function (Builder $query) use ($location): void {
                    $query->where('location', 'like', "%{$location}%")
                        ->orWhere('location_name', 'like', "%{$location}%")
                        ->orWhere('location_city', 'like', "%{$location}%")
                        ->orWhereHas('club', fn (Builder $clubQuery) => $this->publicClubRelation($clubQuery)
                            ->where(function (Builder $clubQuery) use ($location): void {
                                $clubQuery->where('city', 'like', "%{$location}%")
                                    ->orWhere('country', 'like', "%{$location}%");
                            }))
                        ->orWhereHas('team.club', fn (Builder $clubQuery) => $this->publicClubRelation($clubQuery)
                            ->where('teams_are_listed', true)
                            ->where(function (Builder $clubQuery) use ($location): void {
                                $clubQuery->where('city', 'like', "%{$location}%")
                                    ->orWhere('country', 'like', "%{$location}%");
                            }));
                });
            })
            ->orderBy('start_time')
            ->paginate(24)
            ->withQueryString()
            ->through(fn (Event $event): array => $this->eventCard($event));
    }

    /** @return array{sports: Collection<int, array<string, mixed>>, categories: Collection<int, string>} */
    public function sports(array $filters, bool $withCategories = true): array
    {
        $clubCounts = $this->publicClubs()
            ->whereNotNull('sport_type')
            ->selectRaw('sport_type, COUNT(*) as aggregate')
            ->groupBy('sport_type')
            ->pluck('aggregate', 'sport_type');
        $teamCounts = Team::query()
            ->whereNotNull('sport_type')
            ->whereHas('club', fn (Builder $query) => $this->publicClubRelation($query)
                ->where('teams_are_listed', true))
            ->selectRaw('sport_type, COUNT(*) as aggregate')
            ->groupBy('sport_type')
            ->pluck('aggregate', 'sport_type');

        $query = Sport::query()->where('is_active', true);
        $sports = (clone $query)
            ->when($filters['category'] ?? null, fn (Builder $query, string $category) => $query
                ->where('category', $category))
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query
                ->where('name', 'like', "%{$search}%"))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'category'])
            ->map(fn (Sport $sport): array => [
                'id' => $sport->id,
                'name' => $sport->name,
                'slug' => $sport->slug,
                'category' => $sport->category,
                'clubs_count' => (int) ($clubCounts[$sport->slug] ?? $clubCounts[$sport->name] ?? 0),
                'teams_count' => (int) ($teamCounts[$sport->slug] ?? $teamCounts[$sport->name] ?? 0),
                'detail_url' => route('guest.sports.show', $sport->slug),
            ]);

        return [
            'sports' => $sports,
            'categories' => $withCategories ? $this->sportCategories() : collect(),
        ];
    }

    /** @return Collection<int, string> */
    public function sportCategories(): Collection
    {
        return Sport::query()
            ->where('is_active', true)
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->values();
    }

    /** @return array<string, mixed>|null */
    public function club(Club $club): ?array
    {
        if (! $this->isPublicClub($club)) {
            return null;
        }

        $club->loadCount('teams')->load([
            'membershipTypes' => fn ($query) => $query
                ->where('is_active', true)
                ->where('is_public', true)
                ->orderBy('sort_order')
                ->orderBy('name'),
            'contributionRules' => fn ($query) => $query
                ->effectiveOn(now()->toDateString())
                ->where('is_active', true)
                ->orderByDesc('valid_from'),
        ]);

        $teams = $club->teams_are_listed
            ? $club->teams()
                ->orderBy('name')
                ->limit(self::RELATED_LIMIT)
                ->get(['id', 'club_id', 'name', 'sport_type'])
                ->map(fn (Team $team): array => [
                    'id' => $team->id,
                    'name' => $team->name,
                    'sport_type' => $team->sport_type,
                ])
            : collect();

        $events = $this->publicEvents(true)
            ->where(function (Builder $query) use ($club): void {
                $query->where('club_id', $club->id)
                    ->orWhereHas('team', fn (Builder $teamQuery) => $teamQuery->where('club_id', $club->id));
            })
            ->with($this->eventRelations())
            ->orderBy('start_time')
            ->limit(12)
            ->get()
            ->map(fn (Event $event): array => $this->eventCard($event));

        return [
            'entity' => $this->clubCard($club, true),
            'stats' => [
                'teams' => $club->teams_are_listed ? (int) $club->teams_count : 0,
                'events' => $events->count(),
                'membership_types' => $club->membershipTypes->count(),
            ],
            'teams' => $teams,
            'membership_types' => $this->membershipTypes($club),
            'events' => $events,
        ];
    }

    /** @return array<string, mixed>|null */
    public function event(Event $event): ?array
    {
        if ($event->visibility !== 'public' || $event->status !== 'scheduled') {
            return null;
        }

        $event->load($this->eventRelations());

        return [
            'entity' => $this->eventCard($event),
            'stats' => [
                'max_participants' => $event->max_participants,
                'duration_minutes' => $event->end_time && $event->start_time
                    ? $event->start_time->diffInMinutes($event->end_time)
                    : null,
            ],
        ];
    }

    /** @return array<string, mixed>|null */
    public function sport(Sport $sport): ?array
    {
        if (! $sport->is_active) {
            return null;
        }

        $values = array_values(array_unique(array_filter([$sport->slug, $sport->name])));
        $clubQuery = $this->publicClubs()->where(function (Builder $query) use ($values): void {
            $query->whereIn('sport_type', $values)
                ->orWhere(function (Builder $query) use ($values): void {
                    $query->where('teams_are_listed', true)
                        ->whereHas('teams', fn (Builder $teamQuery) => $teamQuery->whereIn('sport_type', $values));
                });
        });
        $teamQuery = Team::query()
            ->whereIn('sport_type', $values)
            ->whereHas('club', fn (Builder $query) => $this->publicClubRelation($query)
                ->where('teams_are_listed', true));
        $eventQuery = $this->eventsForSport($values);

        return [
            'entity' => [
                'id' => $sport->id,
                'name' => $sport->name,
                'slug' => $sport->slug,
                'category' => $sport->category,
                'detail_url' => route('guest.sports.show', $sport->slug),
            ],
            'stats' => [
                'clubs' => (clone $clubQuery)->count(),
                'teams' => (clone $teamQuery)->count(),
                'events' => (clone $eventQuery)->count(),
            ],
            'clubs' => (clone $clubQuery)
                ->withCount('teams')
                ->orderBy('name')
                ->limit(self::RELATED_LIMIT)
                ->get($this->clubColumns())
                ->map(fn (Club $club): array => $this->clubCard($club)),
            'events' => (clone $eventQuery)
                ->with($this->eventRelations())
                ->orderBy('start_time')
                ->limit(12)
                ->get()
                ->map(fn (Event $event): array => $this->eventCard($event)),
        ];
    }

    /** @return Collection<int, array<string, mixed>> */
    public function cities(): Collection
    {
        $resolver = fn (): Collection => $this->buildCities();

        if (app()->environment('testing')) {
            return $resolver();
        }

        return Cache::remember('public-discovery:cities:v1', now()->addMinutes(15), $resolver);
    }

    /** @return array<string, mixed>|null */
    public function city(string $country, string $slug): ?array
    {
        $city = $this->cities()->first(fn (array $city): bool => $city['country_slug'] === Str::lower($country)
            && $city['slug'] === Str::lower($slug));

        if (! $city) {
            return null;
        }

        $clubQuery = $this->publicClubs()
            ->where('city', $city['name'])
            ->when($city['country'], fn (Builder $query, string $value) => $query->where('country', $value));
        $eventQuery = $this->publicEvents(true)
            ->where(function (Builder $query) use ($city): void {
                $query->where(function (Builder $query) use ($city): void {
                    $query->where('location_city', $city['name'])
                        ->when($city['country'], fn (Builder $query, string $value) => $query
                            ->where('location_country', $value));
                })->orWhereHas('club', fn (Builder $clubQuery) => $this->publicClubRelation($clubQuery)
                    ->where('city', $city['name'])
                    ->when($city['country'], fn (Builder $query, string $value) => $query->where('country', $value)))
                    ->orWhereHas('team.club', fn (Builder $clubQuery) => $this->publicClubRelation($clubQuery)
                        ->where('teams_are_listed', true)
                        ->where('city', $city['name'])
                        ->when($city['country'], fn (Builder $query, string $value) => $query->where('country', $value)));
            });
        $teamQuery = Team::query()->whereHas('club', fn (Builder $query) => $this->publicClubRelation($query)
            ->where('teams_are_listed', true)
            ->where('city', $city['name'])
            ->when($city['country'], fn (Builder $query, string $value) => $query->where('country', $value)));

        return [
            'entity' => $city,
            'stats' => [
                'clubs' => (clone $clubQuery)->count(),
                'teams' => $teamQuery->count(),
                'events' => (clone $eventQuery)->count(),
            ],
            'clubs' => (clone $clubQuery)
                ->withCount('teams')
                ->orderBy('name')
                ->limit(self::RELATED_LIMIT)
                ->get($this->clubColumns())
                ->map(fn (Club $club): array => $this->clubCard($club)),
            'events' => (clone $eventQuery)
                ->with($this->eventRelations())
                ->orderBy('start_time')
                ->limit(12)
                ->get()
                ->map(fn (Event $event): array => $this->eventCard($event)),
        ];
    }

    /** @return Collection<int, array<string, mixed>> */
    public function sitemapRoutes(): Collection
    {
        $clubRoutes = $this->publicClubs()
            ->latest('updated_at')
            ->limit(1000)
            ->get(['id', 'updated_at'])
            ->map(fn (Club $club): array => $this->sitemapEntry(
                route('guest.clubs.show', $club),
                $club->updated_at?->toAtomString(),
                '0.7',
                'weekly',
            ));
        $eventRoutes = $this->publicEvents(true)
            ->orderBy('start_time')
            ->limit(1000)
            ->get(['id', 'updated_at'])
            ->map(fn (Event $event): array => $this->sitemapEntry(
                route('guest.events.show', $event),
                $event->updated_at?->toAtomString(),
                '0.6',
                'daily',
            ));
        $sportRoutes = Sport::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->limit(500)
            ->get(['slug', 'updated_at'])
            ->map(fn (Sport $sport): array => $this->sitemapEntry(
                route('guest.sports.show', $sport->slug),
                $sport->updated_at?->toAtomString(),
                '0.7',
                'weekly',
            ));
        $cityRoutes = $this->cities()->map(fn (array $city): array => $this->sitemapEntry(
            $city['detail_url'],
            null,
            '0.6',
            'daily',
        ));

        return $clubRoutes->concat($eventRoutes)->concat($sportRoutes)->concat($cityRoutes);
    }

    private function publicClubs(): Builder
    {
        return Club::query()->verified()->where('is_listed', true);
    }

    private function publicEvents(bool $upcoming): Builder
    {
        return Event::query()
            ->where('visibility', 'public')
            ->where('status', 'scheduled')
            ->when($upcoming, fn (Builder $query) => $query->where('start_time', '>=', now()->startOfDay()));
    }

    private function publicClubRelation(Builder $query): Builder
    {
        return $query->where('verification_status', 'verified')->where('is_listed', true);
    }

    private function isPublicClub(Club $club): bool
    {
        return $club->verification_status === 'verified' && (bool) $club->is_listed;
    }

    /** @return array<int, string> */
    private function clubColumns(): array
    {
        return [
            'id', 'name', 'sport_type', 'logo', 'cover_image', 'country', 'postal_code', 'city', 'state',
            'is_official', 'official_club_number', 'membership_requests_enabled', 'verification_status',
            'is_listed', 'teams_are_listed', 'updated_at',
        ];
    }

    /** @return array<string, callable> */
    private function eventRelations(): array
    {
        return [
            'club' => fn ($query) => $query->select($this->clubColumns()),
            'team:id,name,club_id,sport_type',
            'team.club' => fn ($query) => $query->select($this->clubColumns()),
        ];
    }

    /** @return array<string, mixed> */
    private function clubCard(Club $club, bool $withMembershipTypes = false): array
    {
        $payload = [
            'id' => $club->id,
            'name' => $club->name,
            'sport_type' => $club->sport_type,
            'logo' => $club->logo,
            'logo_url' => UploadStorage::url($club->logo),
            'cover_image_url' => UploadStorage::url($club->cover_image),
            'country' => $club->country,
            'postal_code' => $club->postal_code,
            'city' => $club->city,
            'state' => $club->state,
            'is_official' => (bool) $club->is_official,
            'official_club_number' => $club->official_club_number,
            'membership_requests_enabled' => (bool) $club->membership_requests_enabled,
            'teams_count' => $club->teams_are_listed ? (int) ($club->teams_count ?? 0) : 0,
            'detail_url' => route('guest.clubs.show', $club),
            'city_url' => $this->cityUrl($club->city, $club->country),
        ];

        if ($withMembershipTypes && $club->relationLoaded('membershipTypes')) {
            $payload['membership_types'] = $this->membershipTypes($club);
        }

        return $payload;
    }

    /** @return Collection<int, array<string, mixed>> */
    private function membershipTypes(Club $club): Collection
    {
        return $club->membershipTypes->map(function (ClubMembershipType $type) use ($club): array {
            $rule = $club->contributionRules
                ->first(fn (ClubContributionRule $rule): bool => $rule->club_membership_type_id === $type->id)
                ?: $club->contributionRules->first(fn (ClubContributionRule $rule): bool => $rule->club_membership_type_id === null);

            return [
                'id' => $type->id,
                'name' => $type->name,
                'description' => $type->description,
                'amount' => $rule?->amount,
                'billing_interval' => $rule?->billing_interval,
            ];
        })->values();
    }

    /** @return array<string, mixed> */
    private function eventCard(Event $event): array
    {
        $club = $event->club ?: $event->team?->club;
        $clubVisible = $club && $this->isPublicClub($club);
        $teamVisible = $clubVisible && $club->teams_are_listed && $event->team;
        $city = $event->location_city ?: ($clubVisible ? $club->city : null);
        $country = $event->location_country ?: ($clubVisible ? $club->country : null);

        return [
            'id' => $event->id,
            'title' => $event->title,
            'type' => $event->type,
            'start_time' => $event->start_time?->toIso8601String(),
            'end_time' => $event->end_time?->toIso8601String(),
            'location' => $event->location_name ?: $event->location,
            'location_city' => $city,
            'location_country' => $country,
            'max_participants' => $event->max_participants,
            'club' => $clubVisible ? [
                'id' => $club->id,
                'name' => $club->name,
                'sport_type' => $club->sport_type,
                'city' => $club->city,
                'country' => $club->country,
                'detail_url' => route('guest.clubs.show', $club),
            ] : null,
            'team' => $teamVisible ? [
                'id' => $event->team->id,
                'name' => $event->team->name,
                'sport_type' => $event->team->sport_type,
            ] : null,
            'sport_type' => $teamVisible ? $event->team->sport_type : ($clubVisible ? $club->sport_type : null),
            'detail_url' => route('guest.events.show', $event),
            'city_url' => $this->cityUrl($city, $country),
        ];
    }

    /** @param array<int, string> $values */
    private function eventsForSport(array $values): Builder
    {
        return $this->publicEvents(true)->where(function (Builder $query) use ($values): void {
            $query->whereHas('club', fn (Builder $clubQuery) => $this->publicClubRelation($clubQuery)
                ->whereIn('sport_type', $values))
                ->orWhereHas('team', fn (Builder $teamQuery) => $teamQuery
                    ->whereIn('sport_type', $values)
                    ->whereHas('club', fn (Builder $clubQuery) => $this->publicClubRelation($clubQuery)
                        ->where('teams_are_listed', true)));
        });
    }

    /** @return Collection<int, array<string, mixed>> */
    private function buildCities(): Collection
    {
        $clubs = $this->publicClubs()
            ->whereNotNull('city')
            ->where('city', '<>', '')
            ->selectRaw('city, country, COUNT(*) as clubs_count')
            ->groupBy('city', 'country')
            ->orderByDesc('clubs_count')
            ->limit(250)
            ->get();
        $events = $this->publicEvents(true)
            ->whereNotNull('location_city')
            ->where('location_city', '<>', '')
            ->selectRaw('location_city as city, location_country as country, COUNT(*) as events_count')
            ->groupBy('location_city', 'location_country')
            ->orderByDesc('events_count')
            ->limit(250)
            ->get();

        return $clubs
            ->map(fn ($row): array => [
                'name' => trim((string) $row->city),
                'country' => $this->countryCode($row->country),
                'clubs_count' => (int) $row->clubs_count,
                'events_count' => 0,
            ])
            ->concat($events->map(fn ($row): array => [
                'name' => trim((string) $row->city),
                'country' => $this->countryCode($row->country),
                'clubs_count' => 0,
                'events_count' => (int) $row->events_count,
            ]))
            ->filter(fn (array $city): bool => $city['name'] !== '')
            ->groupBy(fn (array $city): string => Str::lower($city['name']).'|'.($city['country'] ?: 'xx'))
            ->map(function (Collection $rows): array {
                $first = $rows->first();
                $countrySlug = Str::lower($first['country'] ?: 'xx');
                $slug = Str::slug($first['name']);

                return [
                    'name' => $first['name'],
                    'country' => $first['country'],
                    'country_slug' => $countrySlug,
                    'slug' => $slug,
                    'clubs_count' => $rows->sum('clubs_count'),
                    'events_count' => $rows->sum('events_count'),
                    'detail_url' => route('guest.cities.show', ['country' => $countrySlug, 'city' => $slug]),
                ];
            })
            ->filter(fn (array $city): bool => $city['slug'] !== '')
            ->sortByDesc(fn (array $city): int => $city['clubs_count'] + $city['events_count'])
            ->take(self::CITY_LIMIT)
            ->values();
    }

    private function cityUrl(?string $city, ?string $country): ?string
    {
        $city = trim((string) $city);

        if ($city === '') {
            return null;
        }

        return route('guest.cities.show', [
            'country' => Str::lower($this->countryCode($country) ?: 'xx'),
            'city' => Str::slug($city),
        ]);
    }

    private function countryCode(?string $country): ?string
    {
        $country = Str::upper(trim((string) $country));

        return $country !== '' ? Str::limit($country, 2, '') : null;
    }

    /** @return array<string, mixed> */
    private function sitemapEntry(string $url, ?string $lastModified, string $priority, string $frequency): array
    {
        return [
            'loc' => $url,
            'lastmod' => $lastModified,
            'priority' => $priority,
            'changefreq' => $frequency,
        ];
    }
}

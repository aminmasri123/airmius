<?php

namespace App\Services;

use App\Models\OrganizationJob;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class OrganizationJobDirectoryService
{
    /** @return array{type: ?string, sport_type: string, address: string, q: string, sort: string} */
    public function filters(array $input): array
    {
        $type = $input['type'] ?? $input['role'] ?? null;
        $sort = $input['sort'] ?? 'newest';

        return [
            'type' => in_array($type, ['professional', 'volunteer'], true) ? $type : null,
            'sport_type' => trim((string) ($input['sport_type'] ?? '')),
            'address' => trim((string) ($input['address'] ?? '')),
            'q' => trim((string) ($input['q'] ?? '')),
            'sort' => in_array($sort, ['newest', 'oldest'], true) ? $sort : 'newest',
        ];
    }

    public function paginate(array $filters, int $perPage = 12): LengthAwarePaginator
    {
        $jobs = OrganizationJob::query()
            ->published()
            ->with([
                'club:id,name,logo,sport_type,country,street,house_number,postal_code,city,state',
                'sport:id,name,slug',
            ])
            ->when($filters['type'], fn ($query, $type) => $query->where('type', $type))
            ->when($filters['sport_type'], fn ($query, $sport) => $query
                ->whereHas('club', fn ($clubQuery) => $clubQuery->where('sport_type', $sport)))
            ->when($filters['address'], fn ($query, $address) => $query->where(function ($query) use ($address) {
                $query->where('location', 'like', "%{$address}%")
                    ->orWhereHas('club', fn ($clubQuery) => $clubQuery
                        ->where('city', 'like', "%{$address}%")
                        ->orWhere('postal_code', 'like', "%{$address}%")
                        ->orWhere('street', 'like', "%{$address}%")
                        ->orWhere('state', 'like', "%{$address}%")
                        ->orWhere('country', 'like', "%{$address}%"));
            }))
            ->when($filters['q'], fn ($query, $search) => $query->where(function ($query) use ($search) {
                $query->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('employment_type', 'like', "%{$search}%")
                    ->orWhereHas('club', fn ($clubQuery) => $clubQuery->where('name', 'like', "%{$search}%"));
            }));

        return match ($filters['sort']) {
            'oldest' => $jobs->oldest('published_at')->paginate($perPage),
            default => $jobs->latest('published_at')->paginate($perPage),
        };
    }

    /** @return array<string, mixed> */
    public function publicCard(OrganizationJob $job): array
    {
        return [
            'id' => $job->id,
            'title' => $job->title,
            'type' => $job->type,
            'location' => $job->location,
            'workload' => $job->workload,
            'employment_type' => $job->employment_type,
            'sport' => $job->sport?->only(['id', 'name', 'slug']),
            'minimum_experience_level' => $job->minimum_experience_level,
            'required_qualifications' => $job->required_qualifications ?? [],
            'starts_at' => $job->starts_at?->toIso8601String(),
            'ends_at' => $job->ends_at?->toIso8601String(),
            'shift_slots_required' => $job->shift_slots_required,
            'commitment_type' => $job->commitment_type,
            'matching_volunteer_profile_count' => $job->matchingVolunteerProfiles()->count(),
            'description' => $job->description,
            'application_url' => $this->safeUrl($job->application_url),
            'contact_available' => filled($job->contact_email),
            'published_at' => $job->published_at?->toIso8601String(),
            'club' => $job->club ? [
                'id' => $job->club->id,
                'name' => $job->club->name,
                'sport_type' => $job->club->sport_type,
                'city' => $job->club->city,
                'postal_code' => $job->club->postal_code,
                'country' => $job->club->country,
            ] : null,
        ];
    }

    private function safeUrl(?string $url): ?string
    {
        if (! filled($url)) {
            return null;
        }

        return in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['https', 'http'], true)
            ? $url
            : null;
    }
}

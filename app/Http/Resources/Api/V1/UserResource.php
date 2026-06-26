<?php

namespace App\Http\Resources\Api\V1;

use App\Models\User;
use App\Models\UserBadge;
use App\Services\GamificationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $roleLabel = $this->relationLoaded('roles')
            ? $this->roles->pluck('name')->first()
            : null;
        $gamification = app(GamificationService::class)->summaryFor($this->resource);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'birth_date' => $this->birth_date?->toDateString(),
            'gender' => $this->gender,
            'guardian_email' => $this->guardian_email,
            'role' => $roleLabel ?: 'Member',
            'language' => $this->language ?? 'de',
            'theme' => $this->theme,
            'country' => $this->country,
            'street' => $this->street,
            'house_number' => $this->house_number,
            'postal_code' => $this->postal_code,
            'city' => $this->city,
            'state' => $this->state,
            'bio' => $this->bio,
            'profile_photo_url' => $this->profile_photo_url,
            'profile_photo_thumb' => $this->profile_photo_thumb,
            'followers_count' => $this->followers_count,
            'following_count' => $this->following_count,
            'posts_count' => $this->posts_count,
            'sport_profiles' => $this->whenLoaded('sportProfiles', fn () => $this->sportProfiles->map(fn ($profile) => [
                'id' => $profile->id,
                'status' => $profile->status,
                'experience_level' => $profile->experience_level,
                'sport' => $profile->sport ? [
                    'id' => $profile->sport->id,
                    'name' => $profile->sport->name,
                    'slug' => $profile->sport->slug,
                    'category' => $profile->sport->category,
                ] : null,
                'performance_metrics' => $profile->performance_metrics ?? [],
            ])->values()),
            'gamification' => [
                'xp' => $gamification['xp'],
                'level' => $gamification['level'],
                'rank' => $gamification['rank'],
                'title' => $gamification['title'],
                'next_level_xp' => $gamification['next_level_xp'],
                'current_level_xp' => $gamification['current_level_xp'],
                'progress' => $gamification['progress'],
                'xp_to_next_level' => $gamification['xp_to_next_level'],
                'earned_today' => $gamification['earned_today'],
                'trust_score' => $gamification['trust_score'],
                'trust_multiplier' => $gamification['trust_multiplier'],
                'streak_days' => $gamification['streak_days'],
                'health_label' => $gamification['health_label'],
            ],
            'badges' => UserBadge::query()
                ->where('awardable_type', User::class)
                ->where('awardable_id', $this->id)
                ->with('badge:id,key,name,description,icon')
                ->latest('id')
                ->limit(12)
                ->get()
                ->pluck('badge')
                ->filter()
                ->map(fn ($badge) => [
                    'id' => $badge->id,
                    'key' => $badge->key,
                    'name' => $badge->name,
                    'description' => $badge->description,
                    'icon' => $badge->icon,
                ])
                ->values(),
            'user_card' => [
                'id' => $this->id,
                'display_name' => $this->name,
                'subtitle' => $roleLabel ?: 'Member',
                'role_label' => $roleLabel ?: 'Member',
                'avatar_url' => $this->profile_photo_url,
                'avatar_thumb' => $this->profile_photo_thumb,
                'initials' => $this->initialsFor($this->name),
                'status' => $this->status,
                'profile_visibility' => $this->profile_visibility ?? 'public',
            ],
            'privacy_status' => $this->privacy_status,
            'profile_visibility' => $this->profile_visibility ?? 'public',
            'direct_message_privacy' => $this->direct_message_privacy,
            'friend_request_privacy' => $this->friend_request_privacy,
            'ads_personalization_consent' => (bool) $this->ads_personalization_consent,
            'ads_measurement_consent' => (bool) $this->ads_measurement_consent,
            'event_radius_km' => $this->event_radius_km,
            'event_default_sport_ids' => $this->event_default_sport_ids ?? [],
            'event_default_filters' => $this->event_default_filters ?? [],
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')->values()),
            'permissions' => $this->whenLoaded('permissions', fn () => $this->permissions->pluck('name')->values()),
            'clubs' => ClubResource::collection($this->whenLoaded('clubs')),
            'teams' => TeamResource::collection($this->whenLoaded('teams')),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }

    private function initialsFor(?string $name): string
    {
        $parts = preg_split('/\s+/', trim((string) $name)) ?: [];

        $initials = collect($parts)
            ->filter()
            ->take(2)
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');

        return $initials !== '' ? $initials : '?';
    }
}

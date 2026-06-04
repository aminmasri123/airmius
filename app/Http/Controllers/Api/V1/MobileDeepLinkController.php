<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MobileDeepLinkController extends Controller
{
    public function resolve(Request $request)
    {
        $data = $request->validate([
            'url' => ['required', 'string', 'max:500'],
        ]);

        return response()->json([
            'data' => [
                'version' => '2026-06-03',
                'input' => $data['url'],
                'target' => $this->targetFor($data['url']),
            ],
        ]);
    }

    private function targetFor(string $url): array
    {
        $segments = $this->segments($url);
        $first = $segments[0] ?? 'dashboard';
        $second = $segments[1] ?? null;
        $third = $segments[2] ?? null;

        return match ($first) {
            'dashboard' => $this->target('dashboard', 'Dashboard', '/api/v1/dashboard/daily-flow', '/dashboard'),
            'profile' => $this->target('profile', 'ProfileShow', '/api/v1/users/'.($second ?? '{user}'), '/users/'.($second ?? ''), ['user' => $second]),
            'feed' => $this->target('feed_post', 'FeedPost', '/api/v1/feed', '/feed', ['post' => $second]),
            'chat' => $this->target('chat_conversation', 'ChatConversation', '/api/v1/chat/conversations/'.($second ?? '{conversation}'), '/chat?conversation='.($second ?? ''), ['conversation' => $second]),
            'events' => $this->target('event_show', 'EventShow', '/api/v1/events/'.($second ?? '{event}'), '/events/'.($second ?? ''), ['event' => $second]),
            'teams' => $this->target('team_show', 'TeamShow', '/api/v1/teams/'.($second ?? '{team}'), '/teams/'.($second ?? ''), ['team' => $second]),
            'training' => $second === 'plans'
                ? $this->target('training_plan', 'TrainingPlanShow', '/api/v1/training/plans/'.($third ?? '{trainingPlan}'), '/training', ['trainingPlan' => $third])
                : $this->target('training', 'TrainingHome', '/api/v1/training/plans', '/training'),
            'nutrition' => $this->target('nutrition', 'NutritionHome', '/api/v1/nutrition', '/nutrition'),
            'sport-routes' => $this->target('sport_route', 'SportRouteNavigation', '/api/v1/sport-routes/'.($second ?? '{sportRoute}'), '/sport-map', ['sportRoute' => $second]),
            'sport-tracks' => $this->target('sport_track', 'SportTrackReplay', '/api/v1/sport-tracks', '/sport-map', ['sportTrack' => $second]),
            'commerce' => $this->commerceTarget($segments),
            'clubs' => $this->target('club_billing', 'ClubBilling', '/api/v1/clubs/'.($second ?? '{club}').'/billing', '/club-cockpit', ['club' => $second]),
            default => $this->target('unknown', 'Dashboard', '/api/v1/mobile/sync', '/dashboard', [], false),
        };
    }

    private function commerceTarget(array $segments): array
    {
        $type = $segments[1] ?? 'products';
        $id = $segments[2] ?? null;

        if ($type === 'orders') {
            return $this->target('commerce_order', 'CommerceOrderShow', '/api/v1/commerce/orders/'.($id ?? '{order}'), '/commerce?order='.($id ?? ''), ['order' => $id]);
        }

        return $this->target('commerce_product', 'CommerceProductShow', '/api/v1/commerce/products/'.($id ?? '{product}'), '/marketplace/products/'.($id ?? ''), ['product' => $id]);
    }

    private function target(string $key, string $screen, string $apiEndpoint, string $fallbackUrl, array $params = [], bool $recognized = true): array
    {
        return [
            'recognized' => $recognized,
            'key' => $key,
            'screen' => $screen,
            'params' => array_filter($params, fn ($value) => $value !== null && $value !== ''),
            'api_endpoint' => $apiEndpoint,
            'web_fallback_url' => $fallbackUrl,
            'auth_required' => true,
        ];
    }

    private function segments(string $url): array
    {
        $parsed = parse_url($url);

        if (($parsed['scheme'] ?? null) === 'airmius') {
            $path = trim(($parsed['host'] ?? '').'/'.ltrim($parsed['path'] ?? '', '/'), '/');
        } else {
            $path = trim($parsed['path'] ?? $url, '/');
        }

        return collect(explode('/', $path))
            ->map(fn ($segment) => Str::of($segment)->trim()->toString())
            ->filter()
            ->values()
            ->all();
    }
}

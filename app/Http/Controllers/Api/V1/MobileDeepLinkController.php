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
            'profile' => $this->profileTarget($second),
            'feed', 'posts' => $this->target('feed_post', 'FeedPost', '/api/v1/feed', '/feed', ['post' => $second]),
            'chat', 'conversations' => $this->target('chat_conversation', 'ChatConversation', '/api/v1/chat/conversations/'.($second ?? '{conversation}'), '/chat?conversation='.($second ?? ''), ['conversation' => $second]),
            'messages' => $this->target('chat_message', 'ChatMessage', '/api/v1/chat/messages/'.($second ?? '{message}'), '/messages/'.($second ?? ''), ['message' => $second]),
            'events' => $this->target('event_show', 'EventShow', '/api/v1/events/'.($second ?? '{event}'), '/events/'.($second ?? ''), ['event' => $second]),
            'teams' => $this->target('team_show', 'TeamShow', '/api/v1/teams/'.($second ?? '{team}'), '/teams/'.($second ?? ''), ['team' => $second]),
            'invitations', 'team-invitations', 'club-member-invitations' => $this->invitationTarget($segments),
            'friends' => $this->friendInvitationTarget($segments),
            'training' => $second === 'plans'
                ? $this->target('training_plan', 'TrainingPlanShow', '/api/v1/training/plans/'.($third ?? '{trainingPlan}'), '/training', ['trainingPlan' => $third])
                : ($second === 'logs'
                    ? $this->target('training_log', 'TrainingLogShow', '/api/v1/training/logs/'.($third ?? '{trainingLog}'), '/training/logs/'.($third ?? ''), ['trainingLog' => $third])
                    : $this->target('training', 'TrainingHome', '/api/v1/training/plans', '/training')),
            'nutrition' => $this->target('nutrition', 'NutritionHome', '/api/v1/nutrition', '/nutrition'),
            'sport-routes' => $this->target('sport_route', 'SportRouteNavigation', '/api/v1/sport-routes/'.($second ?? '{sportRoute}'), '/sport-map', ['sportRoute' => $second]),
            'sport-tracks' => $this->target('sport_track', 'SportTrackReplay', '/api/v1/sport-tracks', '/sport-map', ['sportTrack' => $second]),
            'membership-applications' => $this->target('membership_application', 'MembershipRequestStatus', '/api/v1/membership-applications/'.($second ?? '{application}'), '/notifications', ['application' => $second]),
            'notifications' => $this->target('notifications', 'NotificationsCenter', '/api/v1/notifications', '/notifications'),
            'commerce' => $this->commerceTarget($segments),
            'clubs' => $third === 'billing'
                ? $this->target('club_billing', 'ClubBilling', '/api/v1/clubs/'.($second ?? '{club}').'/billing', '/club-cockpit', ['club' => $second])
                : ($third === 'membership-requests'
                    ? $this->target('club_membership_requests', 'ClubMembershipRequests', '/api/v1/clubs/'.($second ?? '{club}').'/membership-requests', '/club-memberships?tab=requests&club_id='.($second ?? ''), ['club' => $second])
                    : $this->target('club_show', 'ClubShow', '/api/v1/clubs/'.($second ?? '{club}'), '/clubs/'.($second ?? ''), ['club' => $second])),
            default => $this->target('unknown', 'Dashboard', '/api/v1/mobile/sync', '/dashboard', [], false),
        };
    }

    private function invitationTarget(array $segments): array
    {
        $type = $segments[0] ?? 'invitations';
        $token = $this->invitationToken($segments);

        return $this->target(
            'invitation',
            'InvitationAccept',
            '/api/v1/mobile/deep-links/resolve',
            match ($type) {
                'team-invitations' => '/team-invitations/token/'.($token ?? '').'/accept',
                'club-member-invitations' => '/club-member-invitations/token/'.($token ?? '').'/accept',
                default => '/invitations/'.($token ?? ''),
            },
            ['type' => $type, 'token' => $token],
            $token !== null && $token !== ''
        );
    }

    private function profileTarget(?string $value): array
    {
        if ($value !== null && ctype_digit($value)) {
            return $this->target(
                'profile_show',
                'ProfileShow',
                '/api/v1/users/'.$value.'/sport-cv',
                '/profile/'.$value,
                ['user' => $value]
            );
        }

        return $this->target(
            'profile',
            'ProfileShow',
            '/api/v1/users/me/sport-cv',
            '/profile/'.($value ?? ''),
            ['section' => $value]
        );
    }

    private function invitationToken(array $segments): ?string
    {
        if (($segments[1] ?? null) === 'token') {
            return $segments[2] ?? null;
        }

        return $segments[1] ?? null;
    }

    private function friendInvitationTarget(array $segments): array
    {
        if (($segments[1] ?? null) !== 'invitations') {
            return $this->target('unknown', 'Dashboard', '/api/v1/mobile/sync', '/dashboard', [], false);
        }

        $token = ($segments[2] ?? null) === 'token'
            ? ($segments[3] ?? null)
            : ($segments[2] ?? null);

        return $this->target(
            'friend_invitation',
            'FriendInvitationAccept',
            '/api/v1/friends/invitations/token/'.($token ?? '{token}'),
            '/friends/invitations/token/'.($token ?? '').'/accept',
            ['token' => $token],
            $token !== null && $token !== ''
        );
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

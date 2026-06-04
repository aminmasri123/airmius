<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CommerceOrder;
use App\Models\Event;
use App\Models\TrainingLog;
use App\Models\TrainingPlan;
use App\Support\Api\V1\ApiContract;
use App\Support\Api\V1\MobileSyncContract;
use Illuminate\Http\Request;

class MobileSyncController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'data' => [
                'server_time' => now()->toJSON(),
                'api_version' => ApiContract::VERSION,
                'contract_version' => ApiContract::CONTRACT_VERSION,
                'sync_contract_version' => MobileSyncContract::CONTRACT_VERSION,
                'locale' => [
                    'current' => $user->language ?? app()->getLocale(),
                    'supported' => ['de', 'en', 'fr', 'ar'],
                    'rtl' => ['ar'],
                ],
                'client' => $this->clientHints($request),
                'domains' => MobileSyncContract::domains(),
                'retry_policy' => MobileSyncContract::retryPolicy(),
                'permissions' => MobileSyncContract::permissions(),
                'push' => [
                    'registration' => [
                        'endpoint' => '/api/v1/mobile/push-devices',
                        'unregister_endpoint' => '/api/v1/mobile/push-devices/{device_id}',
                        'status' => 'ready',
                        'token_storage' => 'mobile_device_tokens',
                        'delivery_log' => 'mobile_push_deliveries',
                    ],
                    'channels' => MobileSyncContract::pushChannels(),
                ],
                'deep_links' => MobileSyncContract::deepLinks(),
                'design_system' => MobileSyncContract::designSystem(),
                'pending' => $this->pendingState($request),
            ],
            'meta' => ApiContract::meta($request),
        ]);
    }

    private function clientHints(Request $request): array
    {
        return [
            'platform' => $this->headerValue($request, 'X-Airmius-Platform', 'unknown'),
            'app_version' => $this->headerValue($request, 'X-Airmius-App-Version', 'unknown'),
            'build_number' => $this->headerValue($request, 'X-Airmius-Build', 'unknown'),
            'timezone' => $this->headerValue($request, 'X-Airmius-Timezone', config('app.timezone')),
            'accepts_locale' => $this->headerValue($request, 'X-App-Locale', $request->getPreferredLanguage(['de', 'en', 'fr', 'ar']) ?: 'de'),
        ];
    }

    private function pendingState(Request $request): array
    {
        $user = $request->user();

        return [
            'unread_notifications' => $user->appNotifications()
                ->where('type', '!=', 'chat.message')
                ->where('read', false)
                ->count(),
            'teams' => $user->teams()->count(),
            'clubs' => $user->clubs()->count(),
            'upcoming_events' => Event::query()
                ->where('status', 'scheduled')
                ->where('start_time', '>=', now()->startOfDay())
                ->where(function ($query) use ($user) {
                    $query->where('user_id', $user->id)
                        ->orWhereHas('participants', fn ($participants) => $participants->whereKey($user->id))
                        ->orWhereHas('team.users', fn ($members) => $members->whereKey($user->id))
                        ->orWhereHas('club.users', fn ($members) => $members->whereKey($user->id));
                })
                ->count(),
            'open_training_plans' => TrainingPlan::query()
                ->where(function ($query) use ($user) {
                    $query->where('created_by', $user->id)
                        ->orWhereHas('assignments', fn ($assignments) => $assignments->where('user_id', $user->id));
                })
                ->whereIn('status', ['draft', 'published', 'active'])
                ->count(),
            'recent_training_logs' => TrainingLog::query()
                ->where('user_id', $user->id)
                ->where('created_at', '>=', now()->subDays(14))
                ->count(),
            'open_orders' => CommerceOrder::query()
                ->where('user_id', $user->id)
                ->whereIn('status', ['pending', 'awaiting_transfer', 'processing'])
                ->count(),
        ];
    }

    private function headerValue(Request $request, string $header, string $fallback): string
    {
        $value = trim((string) $request->headers->get($header));

        return $value !== '' ? $value : $fallback;
    }
}

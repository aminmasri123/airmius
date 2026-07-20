<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\NotificationResource;
use App\Models\Notification;
use App\Support\Api\V1\ApiPagination;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $query = $request->user()
            ->appNotifications()
            ->where('type', '!=', 'chat.message')
            ->latest();

        if ($request->boolean('unread_only')) {
            $query->where('read', false);
        }

        $notifications = $query->paginate($this->perPage($request));

        return response()->json(ApiPagination::payload(
            $notifications,
            NotificationResource::collection($notifications)->resolve($request),
            [
                'unread_count' => $request->user()
                    ->appNotifications()
                    ->where('type', '!=', 'chat.message')
                    ->where('read', false)
                    ->count(),
            ],
        ));
    }

    public function show(Request $request, Notification $notification)
    {
        abort_unless($notification->user_id === $request->user()->id, 404);

        return response()->json([
            'data' => (new NotificationResource($notification))->resolve($request),
        ]);
    }

    public function markAsRead(Request $request, Notification $notification)
    {
        abort_unless($notification->user_id === $request->user()->id, 404);

        $notification->update(['read' => true]);

        return response()->json([
            'data' => (new NotificationResource($notification->fresh()))->resolve($request),
        ]);
    }

    public function markAsUnread(Request $request, Notification $notification)
    {
        abort_unless($notification->user_id === $request->user()->id, 404);

        $notification->update(['read' => false]);

        return response()->json([
            'data' => (new NotificationResource($notification->fresh()))->resolve($request),
        ]);
    }

    public function markAllAsRead(Request $request)
    {
        $request->user()
            ->appNotifications()
            ->where('type', '!=', 'chat.message')
            ->where('read', false)
            ->update(['read' => true]);

        return response()->json([
            'data' => [
                'read' => true,
                'unread_count' => 0,
            ],
        ]);
    }

    public function destroy(Request $request, Notification $notification)
    {
        abort_unless($notification->user_id === $request->user()->id, 404);

        $notification->delete();

        return response()->json([
            'data' => [
                'deleted' => true,
            ],
        ]);
    }

    private function perPage(Request $request): int
    {
        return min(max((int) $request->integer('per_page', 20), 1), 50);
    }
}

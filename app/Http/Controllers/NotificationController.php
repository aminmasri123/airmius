<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Support\NotificationRouting;
use Illuminate\Http\Request;
use Inertia\Inertia;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = $request->user()
            ->appNotifications()
            ->where('type', '!=', 'chat.message')
            ->latest()
            ->paginate(20)
            ->through(fn (Notification $notification) => $this->payload($notification));

        return Inertia::render('Auth/Dashboard/Notifications/Index', [
            'notifications' => $notifications,
        ]);
    }

    public function markAsRead(Request $request, Notification $notification)
    {
        abort_unless($notification->user_id === $request->user()->id, 403);

        $notification->update(['read' => true]);

        return back();
    }

    public function markAsUnread(Request $request, Notification $notification)
    {
        abort_unless($notification->user_id === $request->user()->id, 403);

        $notification->update(['read' => false]);

        return back();
    }

    public function markAllAsRead(Request $request)
    {
        $request->user()
            ->appNotifications()
            ->where('type', '!=', 'chat.message')
            ->where('read', false)
            ->update(['read' => true]);

        return back();
    }

    public function destroy(Request $request, Notification $notification)
    {
        abort_unless($notification->user_id === $request->user()->id, 403);

        $notification->delete();

        return back()->with('success', 'Benachrichtigung wurde gelöscht.');
    }

    private function payload(Notification $notification): array
    {
        $data = NotificationRouting::normalizeActionData(
            $notification->type,
            $notification->data ?: [],
        );
        $actionUrl = $data['action_url'] ?? ($data['url'] ?? null);

        return [
            'id' => $notification->id,
            'type' => $notification->type,
            'category' => $notification->category,
            'priority' => $notification->priority,
            'title' => $data['title'] ?? null,
            'body' => $data['body'] ?? ($data['message'] ?? null),
            'url' => $actionUrl,
            'action_url' => $actionUrl,
            'data' => $data,
            'read' => (bool) $notification->read,
            'unread' => ! (bool) $notification->read,
            'created_at' => $notification->created_at?->toJSON(),
            'updated_at' => $notification->updated_at?->toJSON(),
        ];
    }
}

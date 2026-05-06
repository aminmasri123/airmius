<?php

namespace App\Http\Controllers;

use App\Models\Notification;
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
            ->through(fn (Notification $notification) => [
                'id' => $notification->id,
                'type' => $notification->type,
                'data' => $notification->data,
                'read' => $notification->read,
                'created_at' => $notification->created_at,
            ]);

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

        return back()->with('success', 'Benachrichtigung wurde geloescht.');
    }
}

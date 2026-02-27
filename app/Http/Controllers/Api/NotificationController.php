<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function unreadCount(Request $request)
    {
        return response()->json([
            'status' => 'success',
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function unread(Request $request)
    {
        $notifications = $request->user()
            ->unreadNotifications()
            ->latest()
            ->paginate($request->integer('per_page', 10));

        return response()->json([
            'status' => 'success',
            'data' => $notifications,
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function all(Request $request)
    {
        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->paginate($request->integer('per_page', 10));

        return response()->json([
            'status' => 'success',
            'data' => $notifications,
        ]);
    }

    public function markAsRead(Request $request, string $id)
    {
        $notification = $request->user()
            ->notifications()
            ->where('id', $id)
            ->firstOrFail();

        if (is_null($notification->read_at)) {
            $notification->markAsRead();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Notification marked as read.',
        ]);
    }

    public function markAllAsRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json([
            'status' => 'success',
            'message' => 'All notifications marked as read.',
        ]);
    }
}

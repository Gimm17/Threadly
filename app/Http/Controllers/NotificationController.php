<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Get recent notifications for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $notifications = Notification::forUser(auth()->id())
            ->latest()
            ->take(20)
            ->get();

        $unreadCount = Notification::forUser(auth()->id())
            ->unread()
            ->count();

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * Mark a single notification as read.
     */
    public function markAsRead(Notification $notification): JsonResponse
    {
        $notification->markAsRead();

        return response()->json(['success' => true]);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(): JsonResponse
    {
        Notification::forUser(auth()->id())
            ->unread()
            ->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }

    /**
     * Get unread count only (for polling).
     */
    public function unreadCount(): JsonResponse
    {
        $count = Notification::forUser(auth()->id())
            ->unread()
            ->count();

        return response()->json(['unread_count' => $count]);
    }
}

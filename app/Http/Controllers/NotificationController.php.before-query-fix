<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\SessionUserResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

final class NotificationController extends Controller
{
    public function index(
        Request $request,
        SessionUserResolver $resolver
    ): JsonResponse {
        $actor = $resolver->resolve($request);

        abort_unless($actor?->is_active, 403);

        $notifications = $actor->notifications()
            ->latest()
            ->limit(20)
            ->get()
            ->map(static function (DatabaseNotification $notification): array {
                return [
                    'id' => $notification->id,
                    'title' => $notification->data['title'] ?? 'Notification',
                    'message' => $notification->data['message'] ?? '',
                    'url' => $notification->data['url'] ?? '#',
                    'read' => $notification->read_at !== null,
                    'created_at' => $notification->created_at?->toIso8601String(),
                ];
            });

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $actor->unreadNotifications()->count(),
        ]);
    }

    public function read(
        Request $request,
        string $notification,
        SessionUserResolver $resolver
    ): JsonResponse {
        $actor = $resolver->resolve($request);

        abort_unless($actor?->is_active, 403);

        // Only the owner of the notification may mark it as read.
        $record = $actor->notifications()
            ->whereKey($notification)
            ->firstOrFail();

        $record->markAsRead();

        return response()->json([
            'success' => true,
            'unread_count' => $actor->unreadNotifications()->count(),
        ]);
    }

    public function readAll(
        Request $request,
        SessionUserResolver $resolver
    ): JsonResponse {
        $actor = $resolver->resolve($request);

        abort_unless($actor?->is_active, 403);

        // Mark only this user's notifications as read.
        $actor->unreadNotifications->markAsRead();

        return response()->json([
            'success' => true,
            'unread_count' => 0,
        ]);
    }
}
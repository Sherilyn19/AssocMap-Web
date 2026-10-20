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

        // Count all unread notifications belonging to this user,
        // including notifications outside the latest 20.
        $unreadCountQuery = $actor->unreadNotifications()
            ->reorder()
            ->selectRaw('COUNT(*)')
            ->toBase();

        // Retrieve the latest notifications and unread count together.
        // Both relationships retain their ownership filters.
        $records = $actor->notifications()
            ->select('notifications.*')
            ->selectSub($unreadCountQuery, 'unread_total')
            ->limit(20)
            ->get();

        // A user with no notifications also has no unread notifications.
        $unreadCount = (int) (
            $records->first()?->getAttribute('unread_total') ?? 0
        );

        $notifications = $records->map(
            static function (DatabaseNotification $notification): array {
                return [
                    'id' => $notification->id,
                    'title' => $notification->data['title'] ?? 'Notification',
                    'message' => $notification->data['message'] ?? '',
                    'url' => $notification->data['url'] ?? '#',
                    'read' => $notification->read_at !== null,
                    'created_at' => $notification->created_at?->toIso8601String(),
                ];
            }
        );

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
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
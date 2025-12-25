<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Repositories\NotificationRepository;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    protected NotificationRepository $notificationRepo;

    public function __construct(NotificationRepository $notificationRepo)
    {
        $this->notificationRepo = $notificationRepo;
        $this->middleware('auth:sanctum');
    }

    /**
     * List notifications for current user
     */
    public function index(Request $request): JsonResponse
    {
        $notifications = $this->notificationRepo->paginateForUser(
            $request->user(),
            20,
            $request->type,
            $request->has('is_read') ? $request->boolean('is_read') : null
        );

        $unreadCount = $this->notificationRepo->getUnreadCount($request->user()->id);

        return response()->json([
            'success' => true,
            'data' => [
                'notifications' => $notifications,
                'unread_count' => $unreadCount,
            ],
        ]);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(Request $request, $id): JsonResponse
    {
        $notification = $request->user()
            ->notifications()
            ->find($id);

        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found',
            ], 404);
        }

        $this->notificationRepo->markAsRead($id);

        return response()->json([
            'success' => true,
            'message' => 'Marked as read',
            'data' => [
                'notification' => $notification->fresh(),
            ],
        ]);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $count = $this->notificationRepo->markAllAsReadForUser($request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read',
            'data' => [
                'count' => $count,
            ],
        ]);
    }

    /**
     * Get unread count
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $count = $this->notificationRepo->getUnreadCount($request->user()->id);

        return response()->json([
            'success' => true,
            'data' => [
                'unread_count' => $count,
            ],
        ]);
    }

    /**
     * Delete notification
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $notification = $request->user()
            ->notifications()
            ->find($id);

        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found',
            ], 404);
        }

        $this->notificationRepo->delete($id);

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted',
        ]);
    }
}

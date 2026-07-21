<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Services\NotificationService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Get all notifications for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $notifications = $this->notificationService->getUserNotifications($request->user());
        return response()->json($notifications);
    }

    /**
     * Mark a specific notification as read.
     */
    public function markAsRead(Request $request, Notification $notification): JsonResponse
    {
        try {
            $notification = $this->notificationService->markAsRead($notification, $request->user());
            return response()->json($notification);
        } catch (Exception $e) {
            $status = $e->getCode() ?: 403;
            return response()->json(['message' => $e->getMessage()], $status);
        }
    }

    /**
     * Mark all notifications as read.
     */
    // public function markAllAsRead(Request $request): JsonResponse
    // {
    //     $this->notificationService->markAllAsRead($request->user());
    //     return response()->json(['message' => 'All notifications marked as read.']);
    // }

    /**
     * Delete a notification.
     */
    public function destroy(Request $request, Notification $notification): JsonResponse
    {
        try {
            $this->notificationService->deleteNotification($notification, $request->user());
            return response()->json(null, 204);
        } catch (Exception $e) {
            $status = $e->getCode() ?: 403;
            return response()->json(['message' => $e->getMessage()], $status);
        }
    }
}

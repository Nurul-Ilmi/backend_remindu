<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use Exception;
use Illuminate\Support\Collection;

class NotificationService
{
    public function getUserNotifications(User $user): Collection
    {
        return $user->notifications()->latest()->get();
    }

    public function markAsRead(Notification $notification, User $user): Notification
    {
        if ($notification->user_id !== $user->id) {
            throw new Exception('Unauthorized', 403);
        }

        $notification->update(['is_read' => true]);
        return $notification;
    }

    // public function markAllAsRead(User $user): void
    // {
    //     $user->notifications()->update(['is_read' => true]);
    // }

    public function deleteNotification(Notification $notification, User $user): void
    {
        if ($notification->user_id !== $user->id) {
            throw new Exception('Unauthorized', 403);
        }

        $notification->delete();
    }
}

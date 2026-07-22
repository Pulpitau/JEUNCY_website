<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class NotificationService
{
    public function listForUser(User $user): Collection
    {
        return $user->notifications()->latest('created_at')->limit(30)->get();
    }

    public function markAsRead(User $user, Notification $notification): Notification
    {
        if ($notification->user_id !== $user->id) {
            throw new ApiException('FORBIDDEN', "Cette notification ne t'appartient pas.", 403);
        }

        $notification->update(['read' => true]);

        return $notification;
    }

    public function markAllAsRead(User $user): void
    {
        $user->notifications()->where('read', false)->update(['read' => true]);
    }
}

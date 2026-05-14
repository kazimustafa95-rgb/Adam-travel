<?php

namespace App\Services\Home;

use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Collection;

class HomeNotificationService
{
    /**
     * @return Collection<int, UserNotification>
     */
    public function listForUser(User $user, bool $unreadOnly = false, int $limit = 50): Collection
    {
        return UserNotification::query()
            ->where('user_id', $user->id)
            ->when($unreadOnly, fn ($query) => $query->where('is_read', false))
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    public function summaryForUser(User $user, int $limit = 3): array
    {
        return [
            'unread_count' => UserNotification::query()
                ->where('user_id', $user->id)
                ->where('is_read', false)
                ->count(),
            'latest' => $this->listForUser($user, false, $limit),
        ];
    }

    public function markRead(UserNotification $notification): UserNotification
    {
        if (! $notification->is_read) {
            $notification->forceFill([
                'is_read' => true,
                'read_at' => now(),
            ])->save();
        }

        return $notification->fresh();
    }

    public function markAllRead(User $user): int
    {
        return UserNotification::query()
            ->where('user_id', $user->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
                'updated_at' => now(),
            ]);
    }
}

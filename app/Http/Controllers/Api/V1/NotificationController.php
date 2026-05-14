<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\Home\IndexNotificationsRequest;
use App\Http\Resources\Api\V1\UserNotificationResource;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Home\HomeNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class NotificationController extends BaseApiController
{
    public function __construct(protected HomeNotificationService $homeNotificationService) {}

    public function index(IndexNotificationsRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $notifications = $this->homeNotificationService->listForUser(
            $user,
            (bool) $request->boolean('unread_only'),
        );
        $summary = $this->homeNotificationService->summaryForUser($user);

        return $this->success(
            data: [
                'summary' => [
                    'unread_count' => $summary['unread_count'],
                    'total_count' => $notifications->count(),
                ],
                'groups' => $this->groups($notifications),
            ],
            message: 'Notifications loaded successfully.',
        );
    }

    public function read(Request $request, UserNotification $notification): JsonResponse
    {
        $this->guardNotificationOwnership($request, $notification);

        return $this->success(
            data: (new UserNotificationResource($this->homeNotificationService->markRead($notification)))->resolve(),
            message: 'Notification marked as read successfully.',
        );
    }

    public function readAll(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $updatedCount = $this->homeNotificationService->markAllRead($user);

        return $this->success(
            data: ['updated_count' => $updatedCount],
            message: 'All notifications marked as read successfully.',
        );
    }

    /**
     * @param  Collection<int, UserNotification>  $notifications
     * @return list<array<string, mixed>>
     */
    protected function groups(Collection $notifications): array
    {
        return collect([
            [
                'label' => 'Today',
                'items' => $notifications->filter(fn (UserNotification $notification) => $notification->sent_at?->isToday() ?? false)->values(),
            ],
            [
                'label' => 'Earlier',
                'items' => $notifications->filter(fn (UserNotification $notification) => ! ($notification->sent_at?->isToday() ?? false))->values(),
            ],
        ])
            ->filter(fn (array $group) => $group['items']->isNotEmpty())
            ->map(fn (array $group): array => [
                'label' => $group['label'],
                'items' => UserNotificationResource::collection($group['items'])->resolve(),
            ])
            ->values()
            ->all();
    }

    protected function guardNotificationOwnership(Request $request, UserNotification $notification): void
    {
        if ($notification->user_id !== $request->user()?->id) {
            abort(404);
        }
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\Offline\SyncPullRequest;
use App\Http\Requests\Api\V1\Offline\SyncPushRequest;
use App\Services\Offline\SyncService;
use Illuminate\Http\JsonResponse;

class SyncController extends BaseApiController
{
    public function __construct(protected SyncService $syncService)
    {
    }

    public function index(SyncPullRequest $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $payload = $this->syncService->pull($user, $request->validated());

        return $this->success(
            data: $payload['changes'],
            message: 'Sync pull completed successfully.',
            meta: [
                'server_time' => $payload['server_time'],
                'next_cursor' => $payload['next_cursor'],
            ],
        );
    }

    public function store(SyncPushRequest $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $payload = $this->syncService->push($user, $request->validated());

        if ($payload['conflicts'] !== []) {
            return $this->error(
                message: 'Sync conflict detected.',
                errors: [
                    'conflicts' => $payload['conflicts'],
                ],
                meta: [
                    'applied' => $payload['applied'],
                ],
                status: 409,
            );
        }

        return $this->success(
            data: $payload['applied'],
            message: 'Sync push completed successfully.',
            meta: [
                'applied_count' => count(array_filter([
                    $payload['applied']['user_preference'],
                    ...$payload['applied']['saved_places'],
                ])),
            ],
        );
    }
}

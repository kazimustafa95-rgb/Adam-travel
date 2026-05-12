<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\Users\ProfileHomeService;
use App\Services\Users\ProfileService;
use App\Services\Users\UserPreferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileHomeController extends BaseApiController
{
    public function __construct(
        protected ProfileHomeService $profileHomeService,
        protected ProfileService $profileService,
        protected UserPreferenceService $preferenceService,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $this->preferenceService->ensureDefaults($user);
        $this->profileService->touchLastSeen($user);

        return $this->success(
            data: $this->profileHomeService->home($user->fresh()),
            message: 'Profile dashboard loaded successfully.',
        );
    }
}

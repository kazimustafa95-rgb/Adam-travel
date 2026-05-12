<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\Settings\UpdateSettingsRequest;
use App\Services\Users\ProfileHomeService;
use App\Services\Users\UserPreferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends BaseApiController
{
    public function __construct(
        protected UserPreferenceService $preferenceService,
        protected ProfileHomeService $profileHomeService,
    ) {
    }

    public function show(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $preference = $this->preferenceService->ensureDefaults($user);

        return $this->success(
            data: $this->profileHomeService->settingsPayload($user->fresh()->load('socialAccounts'), $preference),
            message: 'Settings loaded successfully.',
        );
    }

    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $preference = $this->preferenceService->update($user, $request->validated());

        return $this->success(
            data: $this->profileHomeService->settingsPayload($user->fresh()->load('socialAccounts'), $preference),
            message: 'Settings updated successfully.',
        );
    }
}

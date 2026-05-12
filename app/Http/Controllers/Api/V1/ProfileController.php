<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\Profile\DeleteAccountRequest;
use App\Http\Requests\Api\V1\Profile\UpdateProfileRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Services\Users\AccountDeletionService;
use App\Services\Users\ProfileService;
use App\Services\Users\UserPreferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends BaseApiController
{
    public function __construct(
        protected ProfileService $profileService,
        protected UserPreferenceService $preferenceService,
        protected AccountDeletionService $accountDeletionService,
    )
    {
    }

    public function show(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $this->preferenceService->ensureDefaults($user);
        $this->profileService->touchLastSeen($user);

        return $this->success(
            data: (new UserResource($user->fresh()->load('preference')))->resolve(),
            message: 'Profile loaded successfully.',
        );
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $updatedUser = $this->profileService->updateProfile($user, $request->validated());
        $this->preferenceService->ensureDefaults($updatedUser);

        return $this->success(
            data: (new UserResource($updatedUser->fresh()->load('preference')))->resolve(),
            message: 'Profile updated successfully.',
        );
    }

    public function destroy(DeleteAccountRequest $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $this->accountDeletionService->delete($user);

        return $this->success(
            message: 'Your account was permanently deleted successfully.',
        );
    }
}

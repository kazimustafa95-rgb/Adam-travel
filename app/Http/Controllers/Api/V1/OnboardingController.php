<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\Onboarding\UpdateOnboardingRequest;
use App\Http\Resources\Api\V1\OnboardingResource;
use App\Services\Users\ProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnboardingController extends BaseApiController
{
    public function __construct(protected ProfileService $profileService)
    {
    }

    public function show(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        return $this->success(
            data: (new OnboardingResource($user))->resolve(),
            message: 'Onboarding state loaded successfully.',
        );
    }

    public function update(UpdateOnboardingRequest $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $updatedUser = $this->profileService->updateOnboarding($user, (bool) $request->boolean('completed'));

        return $this->success(
            data: (new OnboardingResource($updatedUser))->resolve(),
            message: 'Onboarding state updated successfully.',
        );
    }
}

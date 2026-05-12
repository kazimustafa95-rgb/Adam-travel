<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\SocialAuthProvider;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Api\V1\Auth\SocialAuthRequest;
use App\Http\Resources\Api\V1\AuthSessionResource;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;

class SocialAuthController extends BaseApiController
{
    public function __construct(protected AuthService $authService)
    {
    }

    public function store(SocialAuthRequest $request, string $provider): JsonResponse
    {
        $socialProvider = SocialAuthProvider::tryFrom($provider);

        abort_if($socialProvider === null, 404);

        $session = $this->authService->socialLogin($socialProvider, $request->validated());

        return $this->success(
            data: (new AuthSessionResource($session))->resolve(),
            message: 'Signed in with '.ucfirst($socialProvider->value).' successfully.',
            meta: [
                'provider' => $socialProvider->value,
                'is_new_user' => (bool) data_get($session, 'is_new_user', false),
                'social_account_linked' => (bool) data_get($session, 'social_account_linked', false),
            ],
        );
    }
}

<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Resources\Api\V1\AuthSessionResource;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthenticatedSessionController extends BaseApiController
{
    public function __construct(protected AuthService $authService)
    {
    }

    public function store(LoginRequest $request): JsonResponse
    {
        $session = $this->authService->login($request->validated());

        return $this->success(
            data: (new AuthSessionResource($session))->resolve(),
            message: 'Signed in successfully.',
        );
    }

    public function destroy(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $this->authService->logout($user);

        return $this->success(
            message: 'Signed out successfully.',
        );
    }
}

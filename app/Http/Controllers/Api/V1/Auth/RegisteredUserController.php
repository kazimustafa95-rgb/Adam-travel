<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Http\Resources\Api\V1\AuthSessionResource;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;

class RegisteredUserController extends BaseApiController
{
    public function __construct(protected AuthService $authService)
    {
    }

    public function store(RegisterRequest $request): JsonResponse
    {
        $session = $this->authService->register($request->validated());

        return $this->success(
            data: (new AuthSessionResource($session))->resolve(),
            message: 'Account created successfully.',
            status: 201,
        );
    }
}

<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Api\V1\Auth\ForgotPasswordRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;

class PasswordResetLinkController extends BaseApiController
{
    public function store(ForgotPasswordRequest $request): JsonResponse
    {
        Password::sendResetLink($request->validated());

        return $this->success(
            message: 'If the account exists, a password reset link has been dispatched.',
        );
    }
}

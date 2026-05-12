<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Api\V1\Auth\RequestPasswordResetOtpRequest;
use App\Http\Requests\Api\V1\Auth\VerifyPasswordResetOtpRequest;
use App\Services\Auth\PasswordResetOtpService;
use Illuminate\Http\JsonResponse;

class PasswordResetOtpController extends BaseApiController
{
    public function __construct(protected PasswordResetOtpService $passwordResetOtpService)
    {
    }

    public function store(RequestPasswordResetOtpRequest $request): JsonResponse
    {
        return $this->success(
            data: $this->passwordResetOtpService->requestCode($request->validated('email')),
            message: 'If the account exists, a verification code has been dispatched.',
        );
    }

    public function verify(VerifyPasswordResetOtpRequest $request): JsonResponse
    {
        return $this->success(
            data: $this->passwordResetOtpService->verifyCode(
                email: $request->validated('email'),
                challengeId: $request->validated('challenge_id'),
                code: $request->validated('code'),
            ),
            message: 'Verification code accepted successfully.',
        );
    }
}

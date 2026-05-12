<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Api\V1\Auth\ResetPasswordRequest;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class NewPasswordController extends BaseApiController
{
    public function store(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->validated(),
            function ($user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return $this->error(
                message: 'The password reset request could not be completed.',
                errors: [
                    'token' => [__($status)],
                ],
            );
        }

        return $this->success(
            message: 'Password reset successfully.',
        );
    }
}

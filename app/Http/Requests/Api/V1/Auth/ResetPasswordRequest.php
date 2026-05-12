<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Http\Requests\Api\V1\BaseApiRequest;
use Illuminate\Validation\Rules\Password;

class ResetPasswordRequest extends BaseApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, \Illuminate\Contracts\Validation\ValidationRule|string>|string>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email', 'lowercase'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }
}

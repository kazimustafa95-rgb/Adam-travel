<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Http\Requests\Api\V1\BaseApiRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends BaseApiRequest
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
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255', 'lowercase', Rule::unique('users', 'email')],
            'password' => ['required', 'confirmed', Password::defaults()],
            'device_name' => ['required', 'string', 'max:80'],
            'device_platform' => ['nullable', Rule::in(['ios', 'android', 'web'])],
            'device_identifier' => ['nullable', 'string', 'max:191'],
        ];
    }
}

<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Http\Requests\Api\V1\BaseApiRequest;
use Illuminate\Validation\Rule;

class LoginRequest extends BaseApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string|\Illuminate\Contracts\Validation\ValidationRule>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'lowercase'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:80'],
            'device_platform' => ['nullable', Rule::in(['ios', 'android', 'web'])],
            'device_identifier' => ['nullable', 'string', 'max:191'],
        ];
    }
}

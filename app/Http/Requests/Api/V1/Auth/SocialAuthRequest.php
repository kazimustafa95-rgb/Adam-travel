<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Http\Requests\Api\V1\BaseApiRequest;
use Illuminate\Validation\Rule;

class SocialAuthRequest extends BaseApiRequest
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
            'firebase_id_token' => ['required', 'string', 'max:5000'],
            'name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'string', 'email', 'max:255', 'lowercase'],
            'device_name' => ['required', 'string', 'max:80'],
            'device_platform' => ['nullable', Rule::in(['ios', 'android', 'web'])],
            'device_identifier' => ['nullable', 'string', 'max:191'],
        ];
    }
}

<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Http\Requests\Api\V1\BaseApiRequest;

class RequestPasswordResetOtpRequest extends BaseApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'lowercase'],
        ];
    }
}

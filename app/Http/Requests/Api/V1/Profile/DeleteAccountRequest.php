<?php

namespace App\Http\Requests\Api\V1\Profile;

use App\Http\Requests\Api\V1\BaseApiRequest;

class DeleteAccountRequest extends BaseApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'current_password:sanctum'],
        ];
    }
}

<?php

namespace App\Http\Requests\Api\V1\Profile;

use App\Http\Requests\Api\V1\BaseApiRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends BaseApiRequest
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
        $userId = $this->user()?->getAuthIdentifier();

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255', 'lowercase', Rule::unique('users', 'email')->ignore($userId)],
        ];
    }
}

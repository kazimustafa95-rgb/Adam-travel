<?php

namespace App\Http\Requests\Api\V1\Onboarding;

use App\Http\Requests\Api\V1\BaseApiRequest;

class UpdateOnboardingRequest extends BaseApiRequest
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
            'completed' => ['required', 'boolean'],
        ];
    }
}

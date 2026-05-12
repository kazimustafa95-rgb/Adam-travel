<?php

namespace App\Http\Requests\Api\V1\Trips;

use App\Http\Requests\Api\V1\BaseApiRequest;

class GenerateTripSuggestionsRequest extends BaseApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'limit' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'force_refresh' => ['sometimes', 'boolean'],
        ];
    }
}

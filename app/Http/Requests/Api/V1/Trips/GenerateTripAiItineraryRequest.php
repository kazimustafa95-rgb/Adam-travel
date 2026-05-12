<?php

namespace App\Http\Requests\Api\V1\Trips;

use App\Http\Requests\Api\V1\BaseApiRequest;

class GenerateTripAiItineraryRequest extends BaseApiRequest
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
            'force_refresh' => ['sometimes', 'boolean'],
        ];
    }
}

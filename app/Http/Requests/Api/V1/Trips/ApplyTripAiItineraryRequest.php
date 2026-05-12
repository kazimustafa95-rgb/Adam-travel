<?php

namespace App\Http\Requests\Api\V1\Trips;

use App\Http\Requests\Api\V1\BaseApiRequest;

class ApplyTripAiItineraryRequest extends BaseApiRequest
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
            'trip_ai_run_id' => ['nullable', 'integer', 'exists:trip_ai_runs,id'],
        ];
    }
}

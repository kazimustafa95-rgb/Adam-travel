<?php

namespace App\Http\Requests\Api\V1\Trips;

use App\Http\Requests\Api\V1\BaseApiRequest;

class UpdateItineraryItemRequest extends BaseApiRequest
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
            'itinerary_day_id' => ['nullable', 'integer', 'exists:itinerary_days,id'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'sort_order' => ['nullable', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}

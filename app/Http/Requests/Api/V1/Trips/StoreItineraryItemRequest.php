<?php

namespace App\Http\Requests\Api\V1\Trips;

use App\Enums\ItineraryItemSource;
use App\Http\Requests\Api\V1\BaseApiRequest;
use Illuminate\Validation\Rule;

class StoreItineraryItemRequest extends BaseApiRequest
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
            'itinerary_day_id' => ['required', 'integer', 'exists:itinerary_days,id'],
            'trip_place_id' => ['required', 'integer', 'exists:trip_places,id'],
            'source' => ['nullable', Rule::in(ItineraryItemSource::values())],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'sort_order' => ['nullable', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}

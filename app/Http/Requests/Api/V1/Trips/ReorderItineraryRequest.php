<?php

namespace App\Http\Requests\Api\V1\Trips;

use App\Http\Requests\Api\V1\BaseApiRequest;

class ReorderItineraryRequest extends BaseApiRequest
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
            'version' => ['required', 'integer', 'min:1'],
            'days' => ['required', 'array', 'min:1'],
            'days.*.day_id' => ['required', 'integer', 'exists:itinerary_days,id'],
            'days.*.items' => ['required', 'array'],
            'days.*.items.*.item_id' => ['required', 'integer', 'exists:itinerary_items,id'],
            'days.*.items.*.sort_order' => ['nullable', 'integer', 'min:1'],
            'days.*.items.*.starts_at' => ['nullable', 'date'],
            'days.*.items.*.ends_at' => ['nullable', 'date', 'after_or_equal:days.*.items.*.starts_at'],
        ];
    }
}

<?php

namespace App\Http\Requests\Api\V1\Trips;

use App\Enums\SavedPlaceCategory;
use App\Http\Requests\Api\V1\BaseApiRequest;
use Illuminate\Validation\Rule;

class StoreTripPlaceRequest extends BaseApiRequest
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
            'saved_place_id' => ['required', 'integer', 'exists:saved_places,id'],
            'trip_category' => ['nullable', Rule::in(SavedPlaceCategory::values())],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}

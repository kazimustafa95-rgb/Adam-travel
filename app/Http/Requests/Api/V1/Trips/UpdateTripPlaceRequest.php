<?php

namespace App\Http\Requests\Api\V1\Trips;

use App\Enums\SavedPlaceCategory;
use App\Http\Requests\Api\V1\BaseApiRequest;
use Illuminate\Validation\Rule;

class UpdateTripPlaceRequest extends BaseApiRequest
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
            'trip_category' => ['sometimes', 'nullable', Rule::in(SavedPlaceCategory::values())],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }
}

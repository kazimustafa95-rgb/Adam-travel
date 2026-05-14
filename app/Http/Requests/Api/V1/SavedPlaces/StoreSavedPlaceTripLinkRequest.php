<?php

namespace App\Http\Requests\Api\V1\SavedPlaces;

use App\Http\Requests\Api\V1\BaseApiRequest;

class StoreSavedPlaceTripLinkRequest extends BaseApiRequest
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
            'trip_id' => ['required', 'integer', 'exists:trips,id'],
            'trip_category' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}

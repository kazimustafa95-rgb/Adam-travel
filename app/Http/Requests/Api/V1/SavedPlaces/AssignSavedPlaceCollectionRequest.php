<?php

namespace App\Http\Requests\Api\V1\SavedPlaces;

use App\Http\Requests\Api\V1\BaseApiRequest;

class AssignSavedPlaceCollectionRequest extends BaseApiRequest
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
            'saved_place_collection_id' => ['nullable', 'integer', 'exists:saved_place_collections,id'],
        ];
    }
}

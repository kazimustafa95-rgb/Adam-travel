<?php

namespace App\Http\Requests\Api\V1\SavedPlaces;

use App\Http\Requests\Api\V1\BaseApiRequest;
use Illuminate\Validation\Rule;

class StoreSavedPlaceCollectionRequest extends BaseApiRequest
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
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('saved_place_collections', 'name')->where(
                    fn ($query) => $query->where('user_id', $this->user()?->id),
                ),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'color_hex' => ['nullable', 'regex:/^#?[0-9A-Fa-f]{6}$/'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'saved_place_ids' => ['nullable', 'array'],
            'saved_place_ids.*' => ['integer', 'exists:saved_places,id'],
        ];
    }
}

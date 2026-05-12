<?php

namespace App\Http\Requests\Api\V1\SavedPlaces;

use App\Enums\SavedPlaceCategory;
use App\Http\Requests\Api\V1\BaseApiRequest;
use Illuminate\Validation\Rule;

class SavedPlaceIndexRequest extends BaseApiRequest
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
            'q' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', Rule::in(SavedPlaceCategory::values())],
            'region_label' => ['nullable', 'string', 'max:100'],
            'visibility' => ['nullable', Rule::in(['private', 'trip_shared'])],
            'is_favorite' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(['newest', 'oldest', 'name', 'favorites'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}

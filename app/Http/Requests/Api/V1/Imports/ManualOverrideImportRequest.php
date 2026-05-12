<?php

namespace App\Http\Requests\Api\V1\Imports;

use App\Enums\SavedPlaceCategory;
use App\Http\Requests\Api\V1\BaseApiRequest;
use Illuminate\Validation\Rule;

class ManualOverrideImportRequest extends BaseApiRequest
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
            'place_name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', Rule::in(SavedPlaceCategory::values())],
            'city' => ['nullable', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'provider_place_id' => ['nullable', 'string', 'max:191'],
            'summary' => ['nullable', 'string', 'max:2000'],
        ];
    }
}

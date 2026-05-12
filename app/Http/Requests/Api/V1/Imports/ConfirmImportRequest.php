<?php

namespace App\Http\Requests\Api\V1\Imports;

use App\Enums\SavedPlaceCategory;
use App\Http\Requests\Api\V1\BaseApiRequest;
use Illuminate\Validation\Rule;

class ConfirmImportRequest extends BaseApiRequest
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
            'candidate_id' => ['nullable', 'integer'],
            'title_override' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'category' => ['nullable', Rule::in(SavedPlaceCategory::values())],
            'region_label' => ['nullable', 'string', 'max:100'],
            'is_favorite' => ['sometimes', 'boolean'],
            'visibility' => ['sometimes', Rule::in(['private', 'trip_shared'])],
        ];
    }
}

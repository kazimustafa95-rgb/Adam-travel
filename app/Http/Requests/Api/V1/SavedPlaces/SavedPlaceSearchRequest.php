<?php

namespace App\Http\Requests\Api\V1\SavedPlaces;

use App\Http\Requests\Api\V1\BaseApiRequest;

class SavedPlaceSearchRequest extends BaseApiRequest
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
            'q' => ['required', 'string', 'min:2', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}

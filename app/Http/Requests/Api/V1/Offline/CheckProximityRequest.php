<?php

namespace App\Http\Requests\Api\V1\Offline;

use App\Http\Requests\Api\V1\BaseApiRequest;

class CheckProximityRequest extends BaseApiRequest
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
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'radius_meters' => ['sometimes', 'integer', 'min:100', 'max:100000'],
        ];
    }
}

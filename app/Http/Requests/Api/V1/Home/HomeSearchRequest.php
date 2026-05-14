<?php

namespace App\Http\Requests\Api\V1\Home;

use App\Http\Requests\Api\V1\BaseApiRequest;

class HomeSearchRequest extends BaseApiRequest
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
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'radius_meters' => ['nullable', 'integer', 'min:100', 'max:50000'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:25'],
        ];
    }
}

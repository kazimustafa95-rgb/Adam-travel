<?php

namespace App\Http\Requests\Api\V1\Public;

use App\Http\Requests\Api\V1\BaseApiRequest;

class GooglePlaceDetailsRequest extends BaseApiRequest
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
            'place_query' => ['required', 'string', 'max:255'],
            'region_code' => ['nullable', 'string', 'min:2', 'max:10'],
        ];
    }
}

<?php

namespace App\Http\Requests\Api\V1\Public;

use App\Http\Requests\Api\V1\BaseApiRequest;

class LocationSuggestionsRequest extends BaseApiRequest
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
            'input' => ['required', 'string', 'max:5000'],
        ];
    }
}

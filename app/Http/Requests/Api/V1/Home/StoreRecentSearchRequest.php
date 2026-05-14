<?php

namespace App\Http\Requests\Api\V1\Home;

use App\Http\Requests\Api\V1\BaseApiRequest;

class StoreRecentSearchRequest extends BaseApiRequest
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
            'q' => ['required', 'string', 'max:255'],
            'result_count' => ['nullable', 'integer', 'min:0', 'max:10000'],
        ];
    }
}

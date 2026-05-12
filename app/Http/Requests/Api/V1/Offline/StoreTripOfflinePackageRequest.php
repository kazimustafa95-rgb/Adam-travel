<?php

namespace App\Http\Requests\Api\V1\Offline;

use App\Http\Requests\Api\V1\BaseApiRequest;

class StoreTripOfflinePackageRequest extends BaseApiRequest
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
        return [];
    }
}

<?php

namespace App\Http\Requests\Api\V1\Offline;

use App\Enums\OfflinePackageStatus;
use App\Http\Requests\Api\V1\BaseApiRequest;
use Illuminate\Validation\Rule;

class OfflinePackageIndexRequest extends BaseApiRequest
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
            'status' => ['sometimes', Rule::in(OfflinePackageStatus::values())],
        ];
    }
}

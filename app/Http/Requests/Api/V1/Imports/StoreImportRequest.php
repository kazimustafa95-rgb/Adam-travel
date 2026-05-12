<?php

namespace App\Http\Requests\Api\V1\Imports;

use App\Http\Requests\Api\V1\BaseApiRequest;
use Illuminate\Contracts\Validation\Validator;

class StoreImportRequest extends BaseApiRequest
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
            'source_url' => ['nullable', 'url:http,https', 'max:2048'],
            'raw_text' => ['nullable', 'string', 'max:20000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->filled('source_url') && ! $this->filled('raw_text')) {
                $validator->errors()->add('source', 'Either source_url or raw_text is required.');
            }
        });
    }
}

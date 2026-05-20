<?php

namespace App\Http\Requests\LocationIntelligence;

use App\Support\Responses\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ResolveLocationRequest extends FormRequest
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
            'input' => ['required', 'string', 'min:2', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'input.required' => 'An input value (text, image URL, video URL, or social URL) is required.',
            'input.min'      => 'The input must be at least 2 characters.',
            'input.max'      => 'The input must not exceed 2000 characters.',
        ];
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(
            ApiResponse::error(
                message: 'You are not authorized to perform this action.',
                status: 403,
            ),
        );
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::error(
                message: 'Validation failed.',
                errors: $validator->errors()->messages(),
                status: 422,
            ),
        );
    }
}

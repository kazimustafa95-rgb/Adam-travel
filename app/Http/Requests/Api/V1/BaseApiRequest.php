<?php

namespace App\Http\Requests\Api\V1;

use App\Support\Responses\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

abstract class BaseApiRequest extends FormRequest
{
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

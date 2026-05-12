<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

abstract class BaseApiController extends Controller
{
    /**
     * @param  array<mixed>|object|null  $data
     * @param  array<mixed>  $meta
     */
    protected function success(
        array|object|null $data = null,
        string|null $message = null,
        array $meta = [],
        int $status = 200,
    ): JsonResponse {
        return ApiResponse::success($data, $message, $meta, $status);
    }

    /**
     * @param  array<mixed>|array<string, array<int, string>>  $errors
     * @param  array<mixed>  $meta
     */
    protected function error(
        string $message,
        array $errors = [],
        array $meta = [],
        int $status = 422,
    ): JsonResponse {
        return ApiResponse::error($message, $errors, $meta, $status);
    }
}

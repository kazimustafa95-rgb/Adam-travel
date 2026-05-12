<?php

namespace App\Support\Responses;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    /**
     * @param  array<mixed>|object|null  $data
     * @param  array<mixed>  $meta
     * @param  array<mixed>|array<string, array<int, string>>  $errors
     */
    public static function make(
        bool $success,
        string|null $message = null,
        array|object|null $data = null,
        array $meta = [],
        array $errors = [],
        int $status = 200,
    ): JsonResponse {
        return response()->json([
            'success' => $success,
            'message' => $message,
            'data' => $data,
            'meta' => $meta,
            'errors' => $errors,
        ], $status);
    }

    /**
     * @param  array<mixed>|object|null  $data
     * @param  array<mixed>  $meta
     */
    public static function success(
        array|object|null $data = null,
        string|null $message = null,
        array $meta = [],
        int $status = 200,
    ): JsonResponse {
        return self::make(true, $message, $data, $meta, status: $status);
    }

    /**
     * @param  array<mixed>|array<string, array<int, string>>  $errors
     * @param  array<mixed>  $meta
     */
    public static function error(
        string $message,
        array $errors = [],
        array $meta = [],
        int $status = 422,
    ): JsonResponse {
        return self::make(false, $message, null, $meta, $errors, $status);
    }
}

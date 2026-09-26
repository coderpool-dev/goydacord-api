<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;

/** Общий формат ответов API: {status: success|error, message, ...данные}. */
trait RespondsWithJson
{
    protected function successResponse(string $message, array $data = [], int $status = 200): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            ...$data,
        ], $status);
    }

    protected function errorResponse(string $message, int $status, array $extra = []): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
            ...$extra,
        ], $status);
    }
}

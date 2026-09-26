<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Ожидаемая ошибка API. Сама отдаёт ответ в общем формате: {status, message, ...extra}.
 */
class ApiException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $status,
        private readonly array $extra = [],
    ) {
        parent::__construct($message);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => $this->getMessage(),
            ...$this->extra,
        ], $this->status);
    }

    /**
     * 4xx — ошибка клиента, в лог её не пишем (true = отчёт обработан).
     * 5xx отдаём стандартному логированию Laravel (false).
     */
    public function report(): bool
    {
        return $this->status < 500;
    }
}

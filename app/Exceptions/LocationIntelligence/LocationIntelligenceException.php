<?php

namespace App\Exceptions\LocationIntelligence;

use RuntimeException;
use Throwable;

class LocationIntelligenceException extends RuntimeException
{
    /**
     * @param array<string, array<int, string>> $errors
     */
    public function __construct(
        string $message,
        private readonly int $statusCode = 422,
        private readonly array $errors = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}

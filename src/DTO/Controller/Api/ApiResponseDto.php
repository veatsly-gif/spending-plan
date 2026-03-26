<?php

declare(strict_types=1);

namespace App\DTO\Controller\Api;

final readonly class ApiResponseDto
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public int $statusCode,
        public array $payload,
    ) {
    }
}

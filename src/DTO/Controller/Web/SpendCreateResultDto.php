<?php

declare(strict_types=1);

namespace App\DTO\Controller\Web;

final readonly class SpendCreateResultDto
{
    public function __construct(
        public bool $success,
        public ?string $errorMessage = null,
    ) {
    }
}

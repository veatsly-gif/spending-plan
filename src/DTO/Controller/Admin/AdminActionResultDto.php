<?php

declare(strict_types=1);

namespace App\DTO\Controller\Admin;

final readonly class AdminActionResultDto
{
    public function __construct(
        public bool $success,
        public ?string $errorMessage = null,
    ) {
    }
}

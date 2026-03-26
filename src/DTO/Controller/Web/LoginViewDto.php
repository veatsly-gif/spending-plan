<?php

declare(strict_types=1);

namespace App\DTO\Controller\Web;

final readonly class LoginViewDto
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        public bool $shouldRedirect,
        public ?string $redirectRoute,
        public array $context = [],
    ) {
    }
}

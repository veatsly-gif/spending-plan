<?php

declare(strict_types=1);

namespace App\DTO\Controller\Web;

final readonly class LogoutDecisionDto
{
    public function __construct(
        public bool $delegateToFirewall,
    ) {
    }
}

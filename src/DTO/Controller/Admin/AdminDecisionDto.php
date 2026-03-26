<?php

declare(strict_types=1);

namespace App\DTO\Controller\Admin;

final readonly class AdminDecisionDto
{
    public function __construct(
        public bool $value,
    ) {
    }
}

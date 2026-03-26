<?php

declare(strict_types=1);

namespace App\DTO\Controller\Web;

final readonly class WebPageViewDto
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        public array $context = [],
    ) {
    }
}

<?php

declare(strict_types=1);

namespace App\DTO\Controller\Admin;

final readonly class AdminDashboardViewDto
{
    public function __construct(
        public int $pendingTelegramUsers,
    ) {
    }

    /**
     * @return array{pendingTelegramUsers: int}
     */
    public function toArray(): array
    {
        return [
            'pendingTelegramUsers' => $this->pendingTelegramUsers,
        ];
    }
}

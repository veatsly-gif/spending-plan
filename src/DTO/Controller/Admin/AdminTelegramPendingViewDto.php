<?php

declare(strict_types=1);

namespace App\DTO\Controller\Admin;

use App\Entity\TelegramUser;

final readonly class AdminTelegramPendingViewDto
{
    /**
     * @param array<int, TelegramUser> $telegramUsers
     */
    public function __construct(
        public array $telegramUsers,
    ) {
    }

    /**
     * @return array{telegramUsers: array<int, TelegramUser>}
     */
    public function toArray(): array
    {
        return [
            'telegramUsers' => $this->telegramUsers,
        ];
    }
}

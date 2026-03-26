<?php

declare(strict_types=1);

namespace App\DTO\Controller\Admin;

final readonly class AdminTelegramCreateDefaultsDto
{
    public function __construct(
        public string $username,
    ) {
    }

    /**
     * @return array{username: string}
     */
    public function toArray(): array
    {
        return [
            'username' => $this->username,
        ];
    }
}

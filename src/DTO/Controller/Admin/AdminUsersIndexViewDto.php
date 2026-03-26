<?php

declare(strict_types=1);

namespace App\DTO\Controller\Admin;

use App\Entity\User;

final readonly class AdminUsersIndexViewDto
{
    /**
     * @param array<int, User> $users
     */
    public function __construct(
        public array $users,
    ) {
    }

    /**
     * @return array{users: array<int, User>}
     */
    public function toArray(): array
    {
        return [
            'users' => $this->users,
        ];
    }
}

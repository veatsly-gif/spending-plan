<?php

declare(strict_types=1);

namespace App\DTO\Controller\Admin;

use App\Entity\User;

final readonly class AdminUserDraftDto
{
    public function __construct(
        public User $user,
    ) {
    }
}

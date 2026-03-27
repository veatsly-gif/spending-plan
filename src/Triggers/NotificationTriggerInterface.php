<?php

declare(strict_types=1);

namespace App\Triggers;

use App\Entity\User;

interface NotificationTriggerInterface
{
    public function getCode(): string;

    /**
     * @return array<string, scalar|null>|null
     */
    public function evaluate(User $admin, \DateTimeImmutable $now): ?array;
}

<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;

class AdminNotificationTriggerService
{
    public function __construct(
        private readonly NotificationTriggerRunner $triggerRunner,
    ) {
    }

    public function run(User $user, \DateTimeImmutable $now): void
    {
        if (!in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return;
        }

        $this->triggerRunner->runForAdmin($user, $now);
    }
}

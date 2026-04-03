<?php

declare(strict_types=1);

namespace App\Service\Notification;

use App\Entity\User;

final readonly class NotificationDelivery
{
    /**
     * @param array<string, scalar|null> $triggerPayload
     */
    public function __construct(
        public User $recipient,
        public string $template,
        public mixed $deliveryPayload,
        public array $triggerPayload,
        public \DateTimeImmutable $occurredAt,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\User;

final readonly class NotifyActionEvent
{
    /**
     * @param array<string, scalar|null> $payload
     */
    public function __construct(
        public User $recipient,
        public string $source,
        public string $template,
        public array $payload,
        public \DateTimeImmutable $occurredAt,
    ) {
    }
}

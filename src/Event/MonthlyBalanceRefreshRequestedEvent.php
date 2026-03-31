<?php

declare(strict_types=1);

namespace App\Event;

final readonly class MonthlyBalanceRefreshRequestedEvent
{
    public function __construct(
        public string $monthKey,
        public string $source,
        public \DateTimeImmutable $occurredAt,
    ) {
    }
}

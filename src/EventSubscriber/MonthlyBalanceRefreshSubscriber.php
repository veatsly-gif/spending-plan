<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Event\MonthlyBalanceRefreshRequestedEvent;
use App\Service\MonthlyBalanceCacheService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class MonthlyBalanceRefreshSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly MonthlyBalanceCacheService $monthlyBalanceCacheService,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            MonthlyBalanceRefreshRequestedEvent::class => 'onRefreshRequested',
        ];
    }

    public function onRefreshRequested(MonthlyBalanceRefreshRequestedEvent $event): void
    {
        $this->monthlyBalanceCacheService->refreshByMonthKey($event->monthKey, $event->occurredAt);
    }
}

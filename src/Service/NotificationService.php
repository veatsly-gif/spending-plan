<?php

declare(strict_types=1);

namespace App\Service;

use App\Event\NotifyActionEvent;
use App\Service\Notification\NotificationCenter;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: NotifyActionEvent::class)]
final class NotificationService
{
    public function __construct(
        private readonly NotificationCenter $notificationCenter,
    ) {
    }

    public function __invoke(NotifyActionEvent $event): void
    {
        $this->notificationCenter->dispatch(
            $event->recipient,
            $event->source,
            $event->template,
            $event->payload,
            $event->occurredAt
        );
    }
}

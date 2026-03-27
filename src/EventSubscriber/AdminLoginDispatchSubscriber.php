<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use App\Service\NotificationTriggerRunner;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

final class AdminLoginDispatchSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly NotificationTriggerRunner $triggerRunner,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof User) {
            return;
        }

        if (!in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return;
        }

        $this->triggerRunner->runForAdmin($user, new \DateTimeImmutable());
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\Triggers;

use App\Entity\User;
use App\Service\Notification\NotificationActionService;
use App\Service\RedisStore;
use App\Triggers\DeclarationSendTrigger;
use PHPUnit\Framework\TestCase;

final class DeclarationSendTriggerTest extends TestCase
{
    public function testReturnsPayloadForAdminWhenReminderIsActive(): void
    {
        $actionService = new NotificationActionService(new RedisStore('invalid-dsn'));
        $trigger = new DeclarationSendTrigger($actionService);

        $monthKey = $this->randomMonthKey();
        $now = new \DateTimeImmutable($monthKey.'-03 09:00:00');
        $payload = $trigger->evaluate($this->buildAdminUser(), $now);

        self::assertSame(['monthKey' => $monthKey], $payload);
    }

    public function testReturnsNullWhenMarkedDoneForCurrentMonth(): void
    {
        $actionService = new NotificationActionService(new RedisStore('invalid-dsn'));
        $trigger = new DeclarationSendTrigger($actionService);
        $monthKey = $this->randomMonthKey();
        $now = new \DateTimeImmutable($monthKey.'-03 09:00:00');

        $actionService->applyActionByUserId(
            0,
            NotificationActionService::TEMPLATE_DECLARATION_SEND_TAX_SERVICE,
            $monthKey,
            NotificationActionService::ACTION_DONE,
            $now
        );

        self::assertNull($trigger->evaluate($this->buildAdminUser(), $now->modify('+1 day')));
    }

    public function testReturnsNullForNonAdminUser(): void
    {
        $actionService = new NotificationActionService(new RedisStore('invalid-dsn'));
        $trigger = new DeclarationSendTrigger($actionService);

        $user = (new User())
            ->setUsername('test')
            ->setRoles(['ROLE_USER'])
            ->setPassword('hash');

        self::assertNull($trigger->evaluate($user, new \DateTimeImmutable($this->randomMonthKey().'-03 09:00:00')));
    }

    private function buildAdminUser(): User
    {
        return (new User())
            ->setUsername('admin')
            ->setRoles(['ROLE_ADMIN'])
            ->setPassword('hash');
    }

    private function randomMonthKey(): string
    {
        $year = random_int(2080, 2199);
        $month = random_int(1, 12);

        return sprintf('%04d-%02d', $year, $month);
    }
}

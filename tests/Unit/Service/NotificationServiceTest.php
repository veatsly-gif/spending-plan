<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\User;
use App\Event\NotifyActionEvent;
use App\Repository\TelegramUserRepository;
use App\Service\AdminPopupNotificationStore;
use App\Service\NotificationService;
use App\Service\RedisStore;
use App\Service\TelegramBotService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;

final class NotificationServiceTest extends TestCase
{
    public function testPopupNotificationIsStoredAndCanBeConsumed(): void
    {
        $popupStore = new AdminPopupNotificationStore(new RedisStore('invalid-dsn'));
        $telegramRepository = $this->createMock(TelegramUserRepository::class);
        $telegramRepository
            ->expects($this->never())
            ->method('findAuthorizedByUser');

        $botService = new TelegramBotService(new MockHttpClient(), new NullLogger(), '');

        $service = new NotificationService($popupStore, $telegramRepository, $botService);
        $admin = (new User())
            ->setUsername('admin')
            ->setRoles(['ROLE_ADMIN'])
            ->setPassword('hash');
        $now = new \DateTimeImmutable('2026-03-26 14:00:00');

        $service(new NotifyActionEvent(
            $admin,
            'pop-up',
            'spending_plan_missing_next_month',
            ['monthKey' => '2026-04', 'monthLabel' => 'April 2026'],
            $now
        ));

        $popup = $popupStore->consumeDailyPopup(0, $now);
        self::assertTrue($popup->show);
        self::assertSame('Spending Plan Reminder', $popup->title);
        self::assertSame('Please prepare April 2026 spending plans.', $popup->message);
        self::assertSame('2026-04', $popup->monthKey);
    }
}

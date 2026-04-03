<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\User;
use App\Event\NotifyActionEvent;
use App\Repository\TelegramUserRepository;
use App\Service\AdminPopupNotificationStore;
use App\Service\Notification\Delivery\PopupNotificationDeliveryHandler;
use App\Service\Notification\Delivery\TelegramNotificationDeliveryHandler;
use App\Service\Notification\NotificationActionService;
use App\Service\Notification\NotificationCenter;
use App\Service\NotificationService;
use App\Service\Notification\Template\DeclarationSendTaxServiceTemplateRenderer;
use App\Service\Notification\Template\SpendingPlanMissingNextMonthTemplateRenderer;
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
        $notificationActionService = new NotificationActionService(new RedisStore('invalid-dsn'));
        $center = new NotificationCenter(
            [
                new SpendingPlanMissingNextMonthTemplateRenderer(),
                new DeclarationSendTaxServiceTemplateRenderer(),
            ],
            [
                new PopupNotificationDeliveryHandler($popupStore),
                new TelegramNotificationDeliveryHandler($telegramRepository, $botService, $notificationActionService),
            ]
        );

        $service = new NotificationService($center);
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

    public function testBannerAliasSourceIsHandledAsPopup(): void
    {
        $popupStore = new AdminPopupNotificationStore(new RedisStore('invalid-dsn'));
        $telegramRepository = $this->createMock(TelegramUserRepository::class);
        $telegramRepository
            ->expects($this->never())
            ->method('findAuthorizedByUser');

        $botService = new TelegramBotService(new MockHttpClient(), new NullLogger(), '');
        $notificationActionService = new NotificationActionService(new RedisStore('invalid-dsn'));
        $center = new NotificationCenter(
            [
                new SpendingPlanMissingNextMonthTemplateRenderer(),
                new DeclarationSendTaxServiceTemplateRenderer(),
            ],
            [
                new PopupNotificationDeliveryHandler($popupStore),
                new TelegramNotificationDeliveryHandler($telegramRepository, $botService, $notificationActionService),
            ]
        );

        $service = new NotificationService($center);
        $admin = (new User())
            ->setUsername('admin')
            ->setRoles(['ROLE_ADMIN'])
            ->setPassword('hash');
        $now = new \DateTimeImmutable('2026-03-26 14:00:00');

        $service(new NotifyActionEvent(
            $admin,
            'banner',
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

    public function testDeclarationPopupIncludesActionButtons(): void
    {
        $popupStore = new AdminPopupNotificationStore(new RedisStore('invalid-dsn'));
        $telegramRepository = $this->createMock(TelegramUserRepository::class);
        $telegramRepository
            ->expects($this->never())
            ->method('findAuthorizedByUser');

        $botService = new TelegramBotService(new MockHttpClient(), new NullLogger(), '');
        $notificationActionService = new NotificationActionService(new RedisStore('invalid-dsn'));
        $center = new NotificationCenter(
            [
                new SpendingPlanMissingNextMonthTemplateRenderer(),
                new DeclarationSendTaxServiceTemplateRenderer(),
            ],
            [
                new PopupNotificationDeliveryHandler($popupStore),
                new TelegramNotificationDeliveryHandler($telegramRepository, $botService, $notificationActionService),
            ]
        );

        $service = new NotificationService($center);
        $admin = (new User())
            ->setUsername('admin')
            ->setRoles(['ROLE_ADMIN'])
            ->setPassword('hash');
        $now = new \DateTimeImmutable('2026-04-03 14:00:00');

        $service(new NotifyActionEvent(
            $admin,
            'pop-up',
            NotificationActionService::TEMPLATE_DECLARATION_SEND_TAX_SERVICE,
            ['monthKey' => '2026-04'],
            $now
        ));

        $popup = $popupStore->consumeDailyPopup(0, $now);
        self::assertTrue($popup->show);
        self::assertSame('Tax Declaration Reminder', $popup->title);
        self::assertSame("It's day to send a declaration to georgian tax service", $popup->message);
        self::assertSame('2026-04', $popup->monthKey);
        self::assertSame(NotificationActionService::TEMPLATE_DECLARATION_SEND_TAX_SERVICE, $popup->template);
        self::assertCount(2, $popup->actions);
        self::assertSame(NotificationActionService::ACTION_DONE, $popup->actions[0]['code'] ?? null);
        self::assertSame(NotificationActionService::ACTION_REMIND_LATER, $popup->actions[1]['code'] ?? null);
    }
}

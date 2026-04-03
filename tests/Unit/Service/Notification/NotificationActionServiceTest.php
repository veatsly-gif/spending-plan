<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Notification;

use App\Service\Notification\NotificationActionService;
use App\Service\RedisStore;
use PHPUnit\Framework\TestCase;

final class NotificationActionServiceTest extends TestCase
{
    public function testDoneActionSuppressesNotificationForMonth(): void
    {
        $service = new NotificationActionService(new RedisStore('invalid-dsn'));
        $monthKey = $this->randomMonthKey();
        $now = new \DateTimeImmutable($monthKey.'-03 10:00:00');
        $userId = random_int(100000, 999999);

        self::assertTrue($service->shouldNotify(
            $userId,
            NotificationActionService::TEMPLATE_DECLARATION_SEND_TAX_SERVICE,
            $monthKey,
            $now
        ));

        self::assertTrue($service->applyActionByUserId(
            $userId,
            NotificationActionService::TEMPLATE_DECLARATION_SEND_TAX_SERVICE,
            $monthKey,
            NotificationActionService::ACTION_DONE,
            $now
        ));

        self::assertFalse($service->shouldNotify(
            $userId,
            NotificationActionService::TEMPLATE_DECLARATION_SEND_TAX_SERVICE,
            $monthKey,
            $now->modify('+2 days')
        ));
    }

    public function testRemindLaterSuppressesUntilNextDay(): void
    {
        $service = new NotificationActionService(new RedisStore('invalid-dsn'));
        $monthKey = $this->randomMonthKey();
        $now = new \DateTimeImmutable($monthKey.'-03 10:00:00');
        $userId = random_int(100000, 999999);

        self::assertTrue($service->applyActionByUserId(
            $userId,
            NotificationActionService::TEMPLATE_DECLARATION_SEND_TAX_SERVICE,
            $monthKey,
            NotificationActionService::ACTION_REMIND_LATER,
            $now
        ));

        self::assertFalse($service->shouldNotify(
            $userId,
            NotificationActionService::TEMPLATE_DECLARATION_SEND_TAX_SERVICE,
            $monthKey,
            $now
        ));
        self::assertTrue($service->shouldNotify(
            $userId,
            NotificationActionService::TEMPLATE_DECLARATION_SEND_TAX_SERVICE,
            $monthKey,
            $now->modify('+1 day')
        ));
    }

    public function testTelegramCallbackDataRoundTrip(): void
    {
        $service = new NotificationActionService(new RedisStore('invalid-dsn'));
        $callback = $service->buildTelegramCallbackData(
            NotificationActionService::TEMPLATE_DECLARATION_SEND_TAX_SERVICE,
            '2026-04',
            NotificationActionService::ACTION_DONE
        );

        self::assertNotNull($callback);
        self::assertSame(
            [
                'templateCode' => NotificationActionService::TEMPLATE_DECLARATION_SEND_TAX_SERVICE,
                'monthKey' => '2026-04',
                'actionCode' => NotificationActionService::ACTION_DONE,
            ],
            $service->parseTelegramCallbackData((string) $callback)
        );
    }

    private function randomMonthKey(): string
    {
        $year = random_int(2080, 2199);
        $month = random_int(1, 12);

        return sprintf('%04d-%02d', $year, $month);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\NotificationTriggerExecutionStore;
use App\Service\RedisStore;
use PHPUnit\Framework\TestCase;

final class NotificationTriggerExecutionStoreTest extends TestCase
{
    public function testOnceFrequencyAllowsSingleRunOnly(): void
    {
        $store = new NotificationTriggerExecutionStore(new RedisStore('invalid-dsn'));
        $adminId = 777;
        $triggerCode = 'once_rule';
        $store->reset($adminId, $triggerCode);

        $frequency = ['mode' => 'once', 'interval_seconds' => 0];
        $now = new \DateTimeImmutable('2026-03-26 12:00:00');

        self::assertTrue($store->canRun($adminId, $triggerCode, $frequency, $now));
        $store->markRun($adminId, $triggerCode, $now);
        self::assertFalse($store->canRun($adminId, $triggerCode, $frequency, $now->modify('+1 day')));
        self::assertSame(1, $store->getExecutionCount($adminId, $triggerCode));
    }

    public function testIntervalFrequencyRespectsSecondsWindow(): void
    {
        $store = new NotificationTriggerExecutionStore(new RedisStore('invalid-dsn'));
        $adminId = 778;
        $triggerCode = 'interval_rule';
        $store->reset($adminId, $triggerCode);

        $frequency = ['mode' => 'interval', 'interval_seconds' => 3600];
        $now = new \DateTimeImmutable('2026-03-26 12:00:00');

        self::assertTrue($store->canRun($adminId, $triggerCode, $frequency, $now));
        $store->markRun($adminId, $triggerCode, $now);
        self::assertFalse($store->canRun($adminId, $triggerCode, $frequency, $now->modify('+30 minutes')));
        self::assertTrue($store->canRun($adminId, $triggerCode, $frequency, $now->modify('+1 hour')));
    }

    public function testEveryTimeFrequencyAlwaysAllowsRun(): void
    {
        $store = new NotificationTriggerExecutionStore(new RedisStore('invalid-dsn'));
        $adminId = 779;
        $triggerCode = 'every_time_rule';
        $store->reset($adminId, $triggerCode);

        $frequency = ['mode' => 'every_time', 'interval_seconds' => 0];
        $now = new \DateTimeImmutable('2026-03-26 12:00:00');
        $store->markRun($adminId, $triggerCode, $now);

        self::assertTrue($store->canRun($adminId, $triggerCode, $frequency, $now->modify('+1 second')));
        self::assertSame(1, $store->getExecutionCount($adminId, $triggerCode));
    }
}

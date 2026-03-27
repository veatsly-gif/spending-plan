<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\User;
use App\Event\NotifyActionEvent;
use App\Service\NotificationTriggerConfigLoader;
use App\Service\NotificationTriggerExecutionStore;
use App\Service\NotificationTriggerRunner;
use App\Service\RedisStore;
use App\Triggers\NotificationTriggerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class NotificationTriggerRunnerTest extends TestCase
{
    public function testDispatchesNotifyActionEventsForEachDeliveryTypeWhenTriggerMatches(): void
    {
        $projectDir = $this->createTempProjectWithConfig(
            <<<YAML
notifications:
  - code: test_trigger_rule
    type: time_based
    date:
      day_of_month_gt: 25
    triggers:
      - test_trigger
    delivery_types:
      - pop-up
      - telegram
    template: spending_plan_missing_next_month
    frequency:
      mode: every_time
YAML
        );
        $loader = new NotificationTriggerConfigLoader($projectDir, 'test-runner');
        $executionStore = new NotificationTriggerExecutionStore(new RedisStore('invalid-dsn'));
        $executionStore->reset(0, 'test_trigger_rule');
        $trigger = new TestNotificationTrigger('test_trigger', [
            'monthKey' => '2026-04',
            'monthLabel' => 'April 2026',
        ]);

        $captured = [];
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher
            ->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(static function (object $event) use (&$captured): object {
                $captured[] = $event;

                return $event;
            });

        $runner = new NotificationTriggerRunner($loader, $executionStore, $dispatcher, [$trigger]);
        $runner->runForAdmin($this->buildAdminUser(), new \DateTimeImmutable('2026-03-26 11:00:00'));

        self::assertCount(2, $captured);
        self::assertContainsOnlyInstancesOf(NotifyActionEvent::class, $captured);
        self::assertSame('pop-up', $captured[0]->source);
        self::assertSame('telegram', $captured[1]->source);
        self::assertSame('2026-04', $captured[0]->payload['monthKey'] ?? null);
        self::assertSame(1, $executionStore->getExecutionCount(0, 'test_trigger_rule'));
    }

    public function testSkipsDispatchWhenDateGateDoesNotPass(): void
    {
        $projectDir = $this->createTempProjectWithConfig(
            <<<YAML
notifications:
  - code: test_trigger_rule
    type: time_based
    date:
      day_of_month_gt: 25
    triggers:
      - test_trigger
    delivery_types:
      - pop-up
    template: spending_plan_missing_next_month
    frequency:
      mode: every_time
YAML
        );

        $loader = new NotificationTriggerConfigLoader($projectDir, 'test-runner');
        $executionStore = new NotificationTriggerExecutionStore(new RedisStore('invalid-dsn'));
        $executionStore->reset(0, 'test_trigger_rule');
        $trigger = new TestNotificationTrigger('test_trigger', [
            'monthKey' => '2026-04',
            'monthLabel' => 'April 2026',
        ]);

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher
            ->expects($this->never())
            ->method('dispatch');

        $runner = new NotificationTriggerRunner($loader, $executionStore, $dispatcher, [$trigger]);
        $runner->runForAdmin($this->buildAdminUser(), new \DateTimeImmutable('2026-03-20 11:00:00'));
        self::assertSame(0, $executionStore->getExecutionCount(0, 'test_trigger_rule'));
    }

    public function testIntervalFrequencyDispatchesOnlyOncePerWindow(): void
    {
        $projectDir = $this->createTempProjectWithConfig(
            <<<YAML
notifications:
  - code: test_interval_rule
    type: time_based
    date:
      day_of_month_gt: 25
    triggers:
      - test_trigger
    delivery_types:
      - pop-up
    template: spending_plan_missing_next_month
    frequency:
      mode: interval
      interval_seconds: 86400
YAML
        );

        $loader = new NotificationTriggerConfigLoader($projectDir, 'test-runner');
        $executionStore = new NotificationTriggerExecutionStore(new RedisStore('invalid-dsn'));
        $executionStore->reset(0, 'test_interval_rule');
        $trigger = new TestNotificationTrigger('test_trigger', [
            'monthKey' => '2026-04',
            'monthLabel' => 'April 2026',
        ]);

        $captured = [];
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher
            ->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(static function (object $event) use (&$captured): object {
                $captured[] = $event;

                return $event;
            });

        $runner = new NotificationTriggerRunner($loader, $executionStore, $dispatcher, [$trigger]);
        $runner->runForAdmin($this->buildAdminUser(), new \DateTimeImmutable('2026-03-26 10:00:00'));
        $runner->runForAdmin($this->buildAdminUser(), new \DateTimeImmutable('2026-03-26 18:00:00'));
        $runner->runForAdmin($this->buildAdminUser(), new \DateTimeImmutable('2026-03-27 10:00:01'));

        self::assertCount(2, $captured);
        self::assertSame(2, $executionStore->getExecutionCount(0, 'test_interval_rule'));
    }

    private function buildAdminUser(): User
    {
        return (new User())
            ->setUsername('admin')
            ->setRoles(['ROLE_ADMIN'])
            ->setPassword('hash');
    }

    private function createTempProjectWithConfig(string $yaml): string
    {
        $projectDir = sys_get_temp_dir().'/spending-plan-tests-'.bin2hex(random_bytes(5));
        $dir = $projectDir.'/triggers/test-runner';
        mkdir($dir, 0777, true);
        file_put_contents($dir.'/notifications.yaml', $yaml);

        return $projectDir;
    }
}

final class TestNotificationTrigger implements NotificationTriggerInterface
{
    /**
     * @param array<string, scalar|null> $payload
     */
    public function __construct(
        private readonly string $code,
        private readonly array $payload,
    ) {
    }

    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * @return array<string, scalar|null>
     */
    public function evaluate(User $admin, \DateTimeImmutable $now): ?array
    {
        return $this->payload;
    }
}

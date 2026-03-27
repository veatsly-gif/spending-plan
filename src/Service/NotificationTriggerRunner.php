<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Event\NotifyActionEvent;
use App\Triggers\NotificationTriggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class NotificationTriggerRunner
{
    /**
     * @var array<string, NotificationTriggerInterface>
     */
    private array $triggersByCode = [];

    /**
     * @param iterable<NotificationTriggerInterface> $triggers
     */
    public function __construct(
        private readonly NotificationTriggerConfigLoader $configLoader,
        private readonly NotificationTriggerExecutionStore $executionStore,
        private readonly EventDispatcherInterface $eventDispatcher,
        iterable $triggers,
    ) {
        foreach ($triggers as $trigger) {
            $this->triggersByCode[$trigger->getCode()] = $trigger;
        }
    }

    public function runForAdmin(User $admin, \DateTimeImmutable $now): void
    {
        foreach ($this->configLoader->load() as $config) {
            if ('time_based' !== mb_strtolower($config['type'])) {
                continue;
            }

            if (!$this->passesDateGate($config['date'], $now)) {
                continue;
            }

            if (!$this->executionStore->canRun((int) $admin->getId(), $config['code'], $config['frequency'], $now)) {
                continue;
            }

            $payload = $this->evaluateTriggerSet($config['triggers'], $admin, $now);
            if (null === $payload) {
                continue;
            }

            $dispatchCount = 0;
            foreach ($config['delivery_types'] as $deliveryType) {
                $this->eventDispatcher->dispatch(
                    new NotifyActionEvent(
                        $admin,
                        (string) $deliveryType,
                        $config['template'],
                        $payload,
                        $now
                    )
                );
                ++$dispatchCount;
            }

            if ($dispatchCount > 0) {
                $this->executionStore->markRun((int) $admin->getId(), $config['code'], $now);
            }
        }
    }

    /**
     * @param int|array<string, scalar> $dateConfig
     */
    private function passesDateGate(int|array $dateConfig, \DateTimeImmutable $now): bool
    {
        $day = (int) $now->format('j');
        if (is_int($dateConfig)) {
            return $day > $dateConfig;
        }

        if (isset($dateConfig['day_of_month_gt'])) {
            return $day > (int) $dateConfig['day_of_month_gt'];
        }

        if (isset($dateConfig['day_of_month_gte'])) {
            return $day >= (int) $dateConfig['day_of_month_gte'];
        }

        if (isset($dateConfig['day_of_month_lt'])) {
            return $day < (int) $dateConfig['day_of_month_lt'];
        }

        if (isset($dateConfig['day_of_month_lte'])) {
            return $day <= (int) $dateConfig['day_of_month_lte'];
        }

        return false;
    }

    /**
     * @param list<string> $triggerCodes
     *
     * @return array<string, scalar|null>|null
     */
    private function evaluateTriggerSet(
        array $triggerCodes,
        User $admin,
        \DateTimeImmutable $now,
    ): ?array {
        $payload = [];

        foreach ($triggerCodes as $triggerCode) {
            $trigger = $this->triggersByCode[$triggerCode] ?? null;
            if (null === $trigger) {
                return null;
            }

            $result = $trigger->evaluate($admin, $now);
            if (null === $result) {
                return null;
            }

            $payload = array_replace($payload, $result);
        }

        return $payload;
    }
}

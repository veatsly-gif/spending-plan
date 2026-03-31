<?php

declare(strict_types=1);

namespace App\Service;

use App\Redis\RedisDataKey;

final class NotificationTriggerExecutionStore
{
    /**
     * @param array{mode: string, interval_seconds: int} $frequency
     */
    public function canRun(
        int $adminId,
        string $triggerCode,
        array $frequency,
        \DateTimeImmutable $now,
    ): bool {
        $mode = $frequency['mode'] ?? 'every_time';
        if ('every_time' === $mode) {
            return true;
        }

        if ('once' === $mode) {
            return 0 === $this->getExecutionCount($adminId, $triggerCode);
        }

        if ('interval' !== $mode) {
            return false;
        }

        $interval = max(1, (int) ($frequency['interval_seconds'] ?? 0));
        $lastExecutedAt = $this->getLastExecutedAt($adminId, $triggerCode);
        if (null === $lastExecutedAt) {
            return true;
        }

        return $now->getTimestamp() - $lastExecutedAt >= $interval;
    }

    public function markRun(
        int $adminId,
        string $triggerCode,
        \DateTimeImmutable $now,
    ): void {
        $current = $this->getExecutionCount($adminId, $triggerCode);
        $next = $current + 1;

        $this->redisStore->setByDataKey(
            RedisDataKey::NOTIFICATION_TRIGGER_COUNT,
            $this->triggerContext($adminId, $triggerCode),
            (string) $next
        );
        $this->redisStore->setByDataKey(
            RedisDataKey::NOTIFICATION_TRIGGER_LAST,
            $this->triggerContext($adminId, $triggerCode),
            (string) $now->getTimestamp()
        );
    }

    public function getExecutionCount(int $adminId, string $triggerCode): int
    {
        $raw = $this->redisStore->getByDataKey(
            RedisDataKey::NOTIFICATION_TRIGGER_COUNT,
            $this->triggerContext($adminId, $triggerCode)
        );

        return null !== $raw && is_numeric($raw) ? (int) $raw : 0;
    }

    public function reset(int $adminId, string $triggerCode): void
    {
        $context = $this->triggerContext($adminId, $triggerCode);
        $this->redisStore->deleteByDataKey(RedisDataKey::NOTIFICATION_TRIGGER_COUNT, $context);
        $this->redisStore->deleteByDataKey(RedisDataKey::NOTIFICATION_TRIGGER_LAST, $context);
    }

    public function __construct(
        private readonly RedisStore $redisStore,
    ) {
    }

    private function getLastExecutedAt(int $adminId, string $triggerCode): ?int
    {
        $raw = $this->redisStore->getByDataKey(
            RedisDataKey::NOTIFICATION_TRIGGER_LAST,
            $this->triggerContext($adminId, $triggerCode)
        );
        if (null === $raw || !is_numeric($raw)) {
            return null;
        }

        return (int) $raw;
    }

    /**
     * @return array{adminId: string, triggerCode: string}
     */
    private function triggerContext(int $adminId, string $triggerCode): array
    {
        return [
            'adminId' => (string) $adminId,
            'triggerCode' => $triggerCode,
        ];
    }
}

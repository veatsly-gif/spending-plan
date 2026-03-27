<?php

declare(strict_types=1);

namespace App\Service;

final class NotificationTriggerExecutionStore
{
    private const COUNT_PREFIX = 'sp:trigger:count:';
    private const LAST_PREFIX = 'sp:trigger:last:';

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

        $this->redisStore->set($this->countKey($adminId, $triggerCode), (string) $next);
        $this->redisStore->set($this->lastKey($adminId, $triggerCode), (string) $now->getTimestamp());
    }

    public function getExecutionCount(int $adminId, string $triggerCode): int
    {
        $raw = $this->redisStore->get($this->countKey($adminId, $triggerCode));

        return null !== $raw && is_numeric($raw) ? (int) $raw : 0;
    }

    public function reset(int $adminId, string $triggerCode): void
    {
        $this->redisStore->delete($this->countKey($adminId, $triggerCode));
        $this->redisStore->delete($this->lastKey($adminId, $triggerCode));
    }

    public function __construct(
        private readonly RedisStore $redisStore,
    ) {
    }

    private function getLastExecutedAt(int $adminId, string $triggerCode): ?int
    {
        $raw = $this->redisStore->get($this->lastKey($adminId, $triggerCode));
        if (null === $raw || !is_numeric($raw)) {
            return null;
        }

        return (int) $raw;
    }

    private function countKey(int $adminId, string $triggerCode): string
    {
        return sprintf('%s%d:%s', self::COUNT_PREFIX, $adminId, $triggerCode);
    }

    private function lastKey(int $adminId, string $triggerCode): string
    {
        return sprintf('%s%d:%s', self::LAST_PREFIX, $adminId, $triggerCode);
    }
}

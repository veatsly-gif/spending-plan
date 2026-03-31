<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\Controller\Admin\AdminSpendingPlanPopupDto;
use App\Redis\RedisDataKey;

final class AdminPopupNotificationStore
{
    public function __construct(
        private readonly RedisStore $redisStore,
    ) {
    }

    /**
     * @param array{
     *     title: string,
     *     message: string,
     *     monthKey: string,
     *     template?: string,
     *     actions?: list<array{code: string, label: string}>
     * } $payload
     */
    public function queueDailyPopup(
        int $adminId,
        \DateTimeImmutable $now,
        string $dedupeKey,
        array $payload,
    ): void {
        $context = $this->dailyContext($adminId, $now);
        $queue = $this->decodeQueue(
            $this->redisStore->getJsonByDataKey(RedisDataKey::ADMIN_DAILY_POPUP_QUEUE, $context)
        );

        foreach ($queue as $item) {
            if (($item['dedupeKey'] ?? null) === $dedupeKey) {
                return;
            }
        }

        $queue[] = [
            'dedupeKey' => $dedupeKey,
            'title' => $payload['title'],
            'message' => $payload['message'],
            'monthKey' => $payload['monthKey'],
            'template' => (string) ($payload['template'] ?? ''),
            'actions' => $this->sanitizeActions($payload['actions'] ?? []),
        ];

        $this->redisStore->setJsonByDataKey(
            RedisDataKey::ADMIN_DAILY_POPUP_QUEUE,
            $context,
            $queue,
            $this->secondsToEndOfDay($now)
        );
    }

    public function consumeDailyPopup(
        int $adminId,
        \DateTimeImmutable $now,
    ): AdminSpendingPlanPopupDto {
        $context = $this->dailyContext($adminId, $now);
        $queue = $this->decodeQueue(
            $this->redisStore->getJsonByDataKey(RedisDataKey::ADMIN_DAILY_POPUP_QUEUE, $context)
        );
        if ([] === $queue) {
            return new AdminSpendingPlanPopupDto(false, '', '', '');
        }

        $first = array_shift($queue);
        if (null === $first || !is_array($first)) {
            return new AdminSpendingPlanPopupDto(false, '', '', '');
        }

        if ([] === $queue) {
            $this->redisStore->deleteByDataKey(RedisDataKey::ADMIN_DAILY_POPUP_QUEUE, $context);
        } else {
            $this->redisStore->setJsonByDataKey(
                RedisDataKey::ADMIN_DAILY_POPUP_QUEUE,
                $context,
                array_values($queue),
                $this->secondsToEndOfDay($now)
            );
        }

        return new AdminSpendingPlanPopupDto(
            true,
            (string) ($first['title'] ?? 'Spending Plan Reminder'),
            (string) ($first['message'] ?? 'Please prepare spending plans.'),
            (string) ($first['monthKey'] ?? ''),
            (string) ($first['template'] ?? ''),
            $this->sanitizeActions($first['actions'] ?? []),
        );
    }

    /**
     * @return array{adminId: string, date: string}
     */
    private function dailyContext(int $adminId, \DateTimeImmutable $now): array
    {
        return [
            'adminId' => (string) $adminId,
            'date' => $now->format('Y-m-d'),
        ];
    }

    private function secondsToEndOfDay(\DateTimeImmutable $now): int
    {
        return max(
            60,
            (new \DateTimeImmutable('tomorrow'))->getTimestamp() - $now->getTimestamp()
        );
    }

    /**
     * @return list<array{
     *     dedupeKey: string,
     *     title: string,
     *     message: string,
     *     monthKey: string,
     *     template: string,
     *     actions: list<array{code: string, label: string}>
     * }>
     */
    private function decodeQueue(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $queue = [];
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }

            $queue[] = [
                'dedupeKey' => (string) ($row['dedupeKey'] ?? ''),
                'title' => (string) ($row['title'] ?? ''),
                'message' => (string) ($row['message'] ?? ''),
                'monthKey' => (string) ($row['monthKey'] ?? ''),
                'template' => (string) ($row['template'] ?? ''),
                'actions' => $this->sanitizeActions($row['actions'] ?? []),
            ];
        }

        return $queue;
    }

    /**
     * @return list<array{code: string, label: string}>
     */
    private function sanitizeActions(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $actions = [];
        foreach ($raw as $item) {
            if (!is_array($item)) {
                continue;
            }

            $code = mb_strtolower(trim((string) ($item['code'] ?? '')));
            $label = trim((string) ($item['label'] ?? ''));
            if ('' === $code || '' === $label) {
                continue;
            }

            $actions[] = [
                'code' => $code,
                'label' => $label,
            ];
        }

        return $actions;
    }
}

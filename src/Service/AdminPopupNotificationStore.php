<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\Controller\Admin\AdminSpendingPlanPopupDto;

final class AdminPopupNotificationStore
{
    private const POPUP_PREFIX = 'sp:notification:popup:admin:';

    public function __construct(
        private readonly RedisStore $redisStore,
    ) {
    }

    /**
     * @param array{
     *     title: string,
     *     message: string,
     *     monthKey: string
     * } $payload
     */
    public function queueDailyPopup(
        int $adminId,
        \DateTimeImmutable $now,
        string $dedupeKey,
        array $payload,
    ): void {
        $key = $this->dailyKey($adminId, $now);
        $queue = $this->decodeQueue($this->redisStore->get($key));

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
        ];

        $this->redisStore->set(
            $key,
            (string) json_encode($queue, JSON_THROW_ON_ERROR),
            $this->secondsToEndOfDay($now)
        );
    }

    public function consumeDailyPopup(
        int $adminId,
        \DateTimeImmutable $now,
    ): AdminSpendingPlanPopupDto {
        $key = $this->dailyKey($adminId, $now);
        $queue = $this->decodeQueue($this->redisStore->get($key));
        if ([] === $queue) {
            return new AdminSpendingPlanPopupDto(false, '', '', '');
        }

        $first = array_shift($queue);
        if (null === $first || !is_array($first)) {
            return new AdminSpendingPlanPopupDto(false, '', '', '');
        }

        if ([] === $queue) {
            $this->redisStore->delete($key);
        } else {
            $this->redisStore->set(
                $key,
                (string) json_encode(array_values($queue), JSON_THROW_ON_ERROR),
                $this->secondsToEndOfDay($now)
            );
        }

        return new AdminSpendingPlanPopupDto(
            true,
            (string) ($first['title'] ?? 'Spending Plan Reminder'),
            (string) ($first['message'] ?? 'Please prepare spending plans.'),
            (string) ($first['monthKey'] ?? ''),
        );
    }

    private function dailyKey(int $adminId, \DateTimeImmutable $now): string
    {
        return sprintf('%s%d:%s', self::POPUP_PREFIX, $adminId, $now->format('Y-m-d'));
    }

    private function secondsToEndOfDay(\DateTimeImmutable $now): int
    {
        return max(
            60,
            (new \DateTimeImmutable('tomorrow'))->getTimestamp() - $now->getTimestamp()
        );
    }

    /**
     * @return list<array{dedupeKey: string, title: string, message: string, monthKey: string}>
     */
    private function decodeQueue(?string $json): array
    {
        if (null === $json || '' === trim($json)) {
            return [];
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }

        $queue = [];
        foreach ($decoded as $row) {
            if (!is_array($row)) {
                continue;
            }

            $queue[] = [
                'dedupeKey' => (string) ($row['dedupeKey'] ?? ''),
                'title' => (string) ($row['title'] ?? ''),
                'message' => (string) ($row['message'] ?? ''),
                'monthKey' => (string) ($row['monthKey'] ?? ''),
            ];
        }

        return $queue;
    }
}

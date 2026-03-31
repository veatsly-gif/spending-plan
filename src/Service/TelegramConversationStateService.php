<?php

declare(strict_types=1);

namespace App\Service;

use App\Redis\RedisDataKey;

final class TelegramConversationStateService
{
    public const MODE_GEO_TO_RUSSIAN = 'geo_to_russian';

    public function __construct(
        private readonly RedisStore $redisStore,
    ) {
    }

    public function startGeoToRussian(string $telegramId): void
    {
        $this->redisStore->setJsonByDataKey(
            RedisDataKey::TELEGRAM_CONVERSATION_STATE,
            $this->context($telegramId),
            [
                'mode' => self::MODE_GEO_TO_RUSSIAN,
                'startedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
            ],
        );
    }

    public function isGeoToRussianPending(string $telegramId): bool
    {
        $state = $this->redisStore->getJsonByDataKey(
            RedisDataKey::TELEGRAM_CONVERSATION_STATE,
            $this->context($telegramId),
        );
        if (!is_array($state)) {
            return false;
        }

        return self::MODE_GEO_TO_RUSSIAN === (($state['mode'] ?? null));
    }

    public function clear(string $telegramId): void
    {
        $this->redisStore->deleteByDataKey(
            RedisDataKey::TELEGRAM_CONVERSATION_STATE,
            $this->context($telegramId),
        );
    }

    /**
     * @return array{telegramId: string}
     */
    private function context(string $telegramId): array
    {
        return ['telegramId' => trim($telegramId)];
    }
}

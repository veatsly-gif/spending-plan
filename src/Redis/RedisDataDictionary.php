<?php

declare(strict_types=1);

namespace App\Redis;

final class RedisDataDictionary
{
    /**
     * @var array<string, array{template: string, type: string, defaultTtlSeconds: int}>
     */
    private const DEFINITIONS = [
        RedisDataKey::INCOME_RATES_LIVE->value => [
            'template' => 'income:rates:live',
            'type' => RedisDataType::JSON->value,
            'defaultTtlSeconds' => 0,
        ],
        RedisDataKey::SPENDING_PLAN_SUGGESTIONS->value => [
            'template' => 'sp:suggestions:{monthKey}',
            'type' => RedisDataType::JSON->value,
            'defaultTtlSeconds' => 0,
        ],
        RedisDataKey::NOTIFICATION_ACTION_STATE->value => [
            'template' => 'sp:notification:state:{userId}:{templateCode}:{monthKey}',
            'type' => RedisDataType::JSON->value,
            'defaultTtlSeconds' => 0,
        ],
        RedisDataKey::ADMIN_DAILY_POPUP_QUEUE->value => [
            'template' => 'sp:notification:popup:admin:{adminId}:{date}',
            'type' => RedisDataType::JSON->value,
            'defaultTtlSeconds' => 0,
        ],
        RedisDataKey::NOTIFICATION_TRIGGER_COUNT->value => [
            'template' => 'sp:trigger:count:{adminId}:{triggerCode}',
            'type' => RedisDataType::STRING->value,
            'defaultTtlSeconds' => 0,
        ],
        RedisDataKey::NOTIFICATION_TRIGGER_LAST->value => [
            'template' => 'sp:trigger:last:{adminId}:{triggerCode}',
            'type' => RedisDataType::STRING->value,
            'defaultTtlSeconds' => 0,
        ],
        RedisDataKey::MONTHLY_BALANCE_SNAPSHOT->value => [
            'template' => 'sp:balance:month:{monthKey}',
            'type' => RedisDataType::JSON->value,
            'defaultTtlSeconds' => 0,
        ],
    ];

    public function key(RedisDataKey $dataKey, array $context = []): string
    {
        $definition = $this->definition($dataKey);
        $template = $definition['template'];

        return (string) preg_replace_callback(
            '/\{([a-zA-Z][a-zA-Z0-9_]*)\}/',
            static function (array $matches) use ($context, $dataKey): string {
                $name = $matches[1] ?? '';
                if (!array_key_exists($name, $context)) {
                    throw new \InvalidArgumentException(sprintf(
                        'Missing Redis key context "%s" for %s.',
                        $name,
                        $dataKey->value
                    ));
                }

                $value = trim((string) $context[$name]);
                if ('' === $value) {
                    throw new \InvalidArgumentException(sprintf(
                        'Empty Redis key context "%s" for %s.',
                        $name,
                        $dataKey->value
                    ));
                }

                return $value;
            },
            $template
        );
    }

    public function type(RedisDataKey $dataKey): RedisDataType
    {
        $definition = $this->definition($dataKey);

        return RedisDataType::from($definition['type']);
    }

    public function defaultTtlSeconds(RedisDataKey $dataKey): int
    {
        $definition = $this->definition($dataKey);

        return max(0, (int) $definition['defaultTtlSeconds']);
    }

    /**
     * @return array{template: string, type: string, defaultTtlSeconds: int}
     */
    private function definition(RedisDataKey $dataKey): array
    {
        $definition = self::DEFINITIONS[$dataKey->value] ?? null;
        if (!is_array($definition)) {
            throw new \InvalidArgumentException(sprintf('Missing Redis definition for %s.', $dataKey->value));
        }

        return $definition;
    }
}

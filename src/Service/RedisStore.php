<?php

declare(strict_types=1);

namespace App\Service;

use App\Redis\RedisDataDictionary;
use App\Redis\RedisDataKey;
use App\Redis\RedisDataType;

final class RedisStore
{
    /**
     * @var array<string, string>
     */
    private static array $fallback = [];

    private ?\Redis $client = null;
    private bool $unavailable = false;
    private RedisDataDictionary $dictionary;

    public function __construct(
        private readonly string $redisDsn,
        ?RedisDataDictionary $dictionary = null,
    ) {
        $this->dictionary = $dictionary ?? new RedisDataDictionary();
    }

    public function get(string $key): ?string
    {
        $client = $this->getClient();
        if (null === $client) {
            return self::$fallback[$key] ?? null;
        }

        $value = $client->get($key);
        if (false === $value) {
            return null;
        }

        return (string) $value;
    }

    public function set(string $key, string $value, int $ttlSeconds = 0): void
    {
        $client = $this->getClient();
        if (null === $client) {
            self::$fallback[$key] = $value;
            return;
        }

        if ($ttlSeconds > 0) {
            $client->setex($key, $ttlSeconds, $value);
            return;
        }

        $client->set($key, $value);
    }

    public function delete(string $key): void
    {
        $client = $this->getClient();
        if (null === $client) {
            unset(self::$fallback[$key]);
            return;
        }

        $client->del($key);
    }

    public function getByDataKey(RedisDataKey $dataKey, array $context = []): ?string
    {
        return $this->get($this->dictionary->key($dataKey, $context));
    }

    public function getJsonByDataKey(RedisDataKey $dataKey, array $context = []): ?array
    {
        $this->assertDataType($dataKey, RedisDataType::JSON);

        $raw = $this->getByDataKey($dataKey, $context);
        if (null === $raw || '' === trim($raw)) {
            return null;
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    public function setByDataKey(
        RedisDataKey $dataKey,
        array $context,
        string $value,
        ?int $ttlSeconds = null,
    ): void {
        $resolvedTtl = null === $ttlSeconds
            ? $this->dictionary->defaultTtlSeconds($dataKey)
            : max(0, $ttlSeconds);

        $this->set($this->dictionary->key($dataKey, $context), $value, $resolvedTtl);
    }

    public function setJsonByDataKey(
        RedisDataKey $dataKey,
        array $context,
        array $value,
        ?int $ttlSeconds = null,
    ): void {
        $this->assertDataType($dataKey, RedisDataType::JSON);
        $encoded = (string) json_encode($value, JSON_THROW_ON_ERROR);
        $this->setByDataKey($dataKey, $context, $encoded, $ttlSeconds);
    }

    public function deleteByDataKey(RedisDataKey $dataKey, array $context = []): void
    {
        $this->delete($this->dictionary->key($dataKey, $context));
    }

    public function resolveDataKey(RedisDataKey $dataKey, array $context = []): string
    {
        return $this->dictionary->key($dataKey, $context);
    }

    private function assertDataType(RedisDataKey $dataKey, RedisDataType $expectedType): void
    {
        $actualType = $this->dictionary->type($dataKey);
        if ($actualType !== $expectedType) {
            throw new \InvalidArgumentException(sprintf(
                'Redis key %s expected %s, got %s.',
                $dataKey->value,
                $expectedType->value,
                $actualType->value
            ));
        }
    }

    private function getClient(): ?\Redis
    {
        if ($this->unavailable) {
            return null;
        }

        if (null !== $this->client) {
            return $this->client;
        }

        try {
            $parts = parse_url($this->redisDsn);
            if (!is_array($parts)) {
                $this->unavailable = true;
                return null;
            }

            $host = (string) ($parts['host'] ?? 'redis');
            $port = (int) ($parts['port'] ?? 6379);
            $password = isset($parts['pass']) ? (string) $parts['pass'] : null;
            $db = isset($parts['path']) ? (int) ltrim((string) $parts['path'], '/') : 0;

            $client = new \Redis();
            if (!@$client->connect($host, $port, 1.5)) {
                $this->unavailable = true;
                return null;
            }

            if (null !== $password && '' !== $password) {
                $client->auth($password);
            }

            if ($db > 0) {
                $client->select($db);
            }

            $this->client = $client;

            return $this->client;
        } catch (\Throwable) {
            $this->unavailable = true;

            return null;
        }
    }
}

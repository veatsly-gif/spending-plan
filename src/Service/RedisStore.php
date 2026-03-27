<?php

declare(strict_types=1);

namespace App\Service;

final class RedisStore
{
    /**
     * @var array<string, string>
     */
    private static array $fallback = [];

    private ?\Redis $client = null;
    private bool $unavailable = false;

    public function __construct(
        private readonly string $redisDsn,
    ) {
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

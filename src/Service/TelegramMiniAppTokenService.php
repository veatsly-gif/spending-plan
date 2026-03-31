<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class TelegramMiniAppTokenService
{
    public function __construct(
        #[Autowire('%kernel.secret%')]
        private string $appSecret,
    ) {
    }

    public function generateToken(
        string $telegramId,
        ?\DateTimeImmutable $now = null,
        int $ttlSeconds = 86400,
    ): string {
        $issuedAt = $now ?? new \DateTimeImmutable();

        $payload = [
            'tg' => $telegramId,
            'exp' => $issuedAt->getTimestamp() + max(60, $ttlSeconds),
        ];

        $payloadJson = (string) json_encode($payload, JSON_THROW_ON_ERROR);
        $payloadEncoded = $this->base64UrlEncode($payloadJson);
        $signature = hash_hmac('sha256', $payloadEncoded, $this->appSecret, true);

        return $payloadEncoded.'.'.$this->base64UrlEncode($signature);
    }

    public function resolveTelegramId(string $token, ?\DateTimeImmutable $now = null): ?string
    {
        $parts = explode('.', trim($token), 2);
        if (2 !== count($parts) || '' === $parts[0] || '' === $parts[1]) {
            return null;
        }

        [$payloadEncoded, $signatureEncoded] = $parts;

        $signature = $this->base64UrlDecode($signatureEncoded);
        if (null === $signature) {
            return null;
        }

        $expected = hash_hmac('sha256', $payloadEncoded, $this->appSecret, true);
        if (!hash_equals($expected, $signature)) {
            return null;
        }

        $payloadJson = $this->base64UrlDecode($payloadEncoded);
        if (null === $payloadJson) {
            return null;
        }

        $payload = json_decode($payloadJson, true);
        if (!is_array($payload)) {
            return null;
        }

        $telegramId = $payload['tg'] ?? null;
        $exp = $payload['exp'] ?? null;
        if (!is_string($telegramId) || '' === trim($telegramId) || !is_numeric($exp)) {
            return null;
        }

        $nowTs = ($now ?? new \DateTimeImmutable())->getTimestamp();
        if ((int) $exp < $nowTs) {
            return null;
        }

        return $telegramId;
    }

    private function base64UrlEncode(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $encoded): ?string
    {
        if ('' === $encoded || 1 === preg_match('/[^A-Za-z0-9_\-]/', $encoded)) {
            return null;
        }

        $padded = strtr($encoded, '-_', '+/');
        $padding = strlen($padded) % 4;
        if (0 !== $padding) {
            $padded .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($padded, true);
        if (false === $decoded) {
            return null;
        }

        return $decoded;
    }
}

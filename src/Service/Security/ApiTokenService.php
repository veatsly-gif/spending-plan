<?php

declare(strict_types=1);

namespace App\Service\Security;

use App\Entity\User;

final readonly class ApiTokenService
{
    public function __construct(
        private string $secret,
        private int $ttlSeconds,
    ) {
    }

    public function generate(User $user): string
    {
        $header = [
            'alg' => 'HS256',
            'typ' => 'JWT',
        ];

        $issuedAt = time();
        $payload = [
            'sub' => $user->getUserIdentifier(),
            'iat' => $issuedAt,
            'exp' => $issuedAt + max(60, $this->ttlSeconds),
        ];

        $headerEncoded = $this->base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR));
        $payloadEncoded = $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));
        $signature = $this->sign($headerEncoded.'.'.$payloadEncoded);

        return $headerEncoded.'.'.$payloadEncoded.'.'.$signature;
    }

    public function parseSubjectIfValid(string $token): ?string
    {
        $parts = explode('.', trim($token));
        if (count($parts) !== 3) {
            return null;
        }

        [$headerEncoded, $payloadEncoded, $providedSignature] = $parts;
        if ('' === $headerEncoded || '' === $payloadEncoded || '' === $providedSignature) {
            return null;
        }

        $expectedSignature = $this->sign($headerEncoded.'.'.$payloadEncoded);
        if (!hash_equals($expectedSignature, $providedSignature)) {
            return null;
        }

        $payloadJson = $this->base64UrlDecode($payloadEncoded);
        if (null === $payloadJson) {
            return null;
        }

        try {
            $payload = json_decode($payloadJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!is_array($payload)) {
            return null;
        }

        $subject = isset($payload['sub']) && is_string($payload['sub']) ? trim($payload['sub']) : '';
        $expiresAt = isset($payload['exp']) ? (int) $payload['exp'] : 0;

        if ('' === $subject || $expiresAt <= time()) {
            return null;
        }

        return $subject;
    }

    public function getExpiryDateTime(string $token): ?\DateTimeImmutable
    {
        $parts = explode('.', trim($token));
        if (count($parts) !== 3) {
            return null;
        }

        $payloadJson = $this->base64UrlDecode($parts[1]);
        if (null === $payloadJson) {
            return null;
        }

        try {
            $payload = json_decode($payloadJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!is_array($payload) || !isset($payload['exp'])) {
            return null;
        }

        $expiresAt = (int) $payload['exp'];
        if ($expiresAt <= 0) {
            return null;
        }

        return (new \DateTimeImmutable())->setTimestamp($expiresAt);
    }

    private function sign(string $data): string
    {
        $signature = hash_hmac('sha256', $data, $this->secret, true);

        return $this->base64UrlEncode($signature);
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): ?string
    {
        $padding = strlen($data) % 4;
        if ($padding > 0) {
            $data .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode(strtr($data, '-_', '+/'), true);

        return false === $decoded ? null : $decoded;
    }
}

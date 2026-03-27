<?php

declare(strict_types=1);

namespace App\DTO\Income;

final readonly class IncomeLiveRatesDto
{
    public function __construct(
        public string $eurGel,
        public string $usdtGel,
        public \DateTimeImmutable $updatedAt,
    ) {
    }

    /**
     * @return array{
     *     eurGel: string,
     *     usdtGel: string,
     *     updatedAt: string
     * }
     */
    public function toArray(): array
    {
        return [
            'eurGel' => $this->eurGel,
            'usdtGel' => $this->usdtGel,
            'updatedAt' => $this->updatedAt->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @param array{
     *     eurGel?: mixed,
     *     usdtGel?: mixed,
     *     updatedAt?: mixed
     * } $payload
     */
    public static function fromArray(array $payload): ?self
    {
        $eurGel = $payload['eurGel'] ?? null;
        $usdtGel = $payload['usdtGel'] ?? null;
        $updatedAt = $payload['updatedAt'] ?? null;
        if (!is_string($eurGel) || !is_numeric($eurGel)) {
            return null;
        }

        if (!is_string($usdtGel) || !is_numeric($usdtGel)) {
            return null;
        }

        if (!is_string($updatedAt)) {
            return null;
        }

        try {
            $timestamp = new \DateTimeImmutable($updatedAt);
        } catch (\Throwable) {
            return null;
        }

        return new self(
            number_format((float) $eurGel, 6, '.', ''),
            number_format((float) $usdtGel, 6, '.', ''),
            $timestamp
        );
    }
}

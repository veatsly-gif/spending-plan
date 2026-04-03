<?php

declare(strict_types=1);

namespace App\Service\Notification;

final class NotificationEnvelope
{
    /**
     * @param array<string, mixed> $channelPayloads
     */
    public function __construct(
        private readonly array $channelPayloads,
    ) {
    }

    public function payloadFor(string $deliveryType): mixed
    {
        return $this->channelPayloads[$this->normalizeDeliveryType($deliveryType)] ?? null;
    }

    private function normalizeDeliveryType(string $deliveryType): string
    {
        $normalized = mb_strtolower(trim($deliveryType));

        return match ($normalized) {
            'pop-up', 'popup', 'pop_up', 'banner' => 'popup',
            default => $normalized,
        };
    }
}

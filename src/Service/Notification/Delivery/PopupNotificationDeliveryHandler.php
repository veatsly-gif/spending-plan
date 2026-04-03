<?php

declare(strict_types=1);

namespace App\Service\Notification\Delivery;

use App\Service\AdminPopupNotificationStore;
use App\Service\Notification\NotificationDelivery;
use App\Service\Notification\NotificationDeliveryHandlerInterface;

final class PopupNotificationDeliveryHandler implements NotificationDeliveryHandlerInterface
{
    public function __construct(
        private readonly AdminPopupNotificationStore $popupStore,
    ) {
    }

    public function getDeliveryType(): string
    {
        return 'popup';
    }

    public function deliver(NotificationDelivery $notification): void
    {
        if (!is_array($notification->deliveryPayload)) {
            return;
        }

        $payload = [
            'title' => (string) ($notification->deliveryPayload['title'] ?? ''),
            'message' => (string) ($notification->deliveryPayload['message'] ?? ''),
            'monthKey' => (string) ($notification->deliveryPayload['monthKey'] ?? ''),
        ];

        if ('' === trim($payload['title']) || '' === trim($payload['message'])) {
            return;
        }

        $templateCode = (string) ($notification->deliveryPayload['template'] ?? $notification->template);
        $actions = $this->normalizeActions($notification->deliveryPayload['actions'] ?? null);

        $dedupeKey = sprintf(
            '%s:%s',
            $notification->template,
            sha1((string) json_encode($notification->triggerPayload))
        );

        $this->popupStore->queueDailyPopup(
            (int) $notification->recipient->getId(),
            $notification->occurredAt,
            $dedupeKey,
            [
                'title' => $payload['title'],
                'message' => $payload['message'],
                'monthKey' => $payload['monthKey'],
                'template' => $templateCode,
                'actions' => $actions,
            ]
        );
    }

    /**
     * @return list<array{code: string, label: string}>
     */
    private function normalizeActions(mixed $raw): array
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

<?php

declare(strict_types=1);

namespace App\Service\Notification\Delivery;

use App\Repository\TelegramUserRepository;
use App\Service\Notification\NotificationActionService;
use App\Service\Notification\NotificationDelivery;
use App\Service\Notification\NotificationDeliveryHandlerInterface;
use App\Service\TelegramBotService;

final class TelegramNotificationDeliveryHandler implements NotificationDeliveryHandlerInterface
{
    public function __construct(
        private readonly TelegramUserRepository $telegramUserRepository,
        private readonly TelegramBotService $telegramBotService,
        private readonly NotificationActionService $notificationActionService,
    ) {
    }

    public function getDeliveryType(): string
    {
        return 'telegram';
    }

    public function deliver(NotificationDelivery $notification): void
    {
        $telegramUsers = $this->telegramUserRepository->findAuthorizedByUser($notification->recipient);
        if (is_string($notification->deliveryPayload)) {
            $text = trim($notification->deliveryPayload);
            if ('' === $text) {
                return;
            }

            foreach ($telegramUsers as $telegramUser) {
                $this->telegramBotService->sendMessage($telegramUser->getTelegramId(), $text);
            }

            return;
        }

        if (!is_array($notification->deliveryPayload)) {
            return;
        }

        $text = trim((string) ($notification->deliveryPayload['text'] ?? ''));
        if ('' === $text) {
            return;
        }

        $monthKey = (string) (
            $notification->deliveryPayload['monthKey']
            ?? $notification->triggerPayload['monthKey']
            ?? ''
        );
        $buttons = $this->buildTelegramButtons(
            $notification->template,
            $monthKey,
            $notification->deliveryPayload['actions'] ?? null
        );

        foreach ($telegramUsers as $telegramUser) {
            if ([] === $buttons) {
                $this->telegramBotService->sendMessage($telegramUser->getTelegramId(), $text);
                continue;
            }

            $this->telegramBotService->sendMessageWithInlineButtons(
                $telegramUser->getTelegramId(),
                $text,
                $buttons
            );
        }
    }

    /**
     * @return list<array{label: string, callback_data: string}>
     */
    private function buildTelegramButtons(string $templateCode, string $monthKey, mixed $rawActions): array
    {
        if (!is_array($rawActions)) {
            return [];
        }

        $buttons = [];
        foreach ($rawActions as $action) {
            if (!is_array($action)) {
                continue;
            }

            $actionCode = mb_strtolower(trim((string) ($action['code'] ?? '')));
            $label = trim((string) ($action['label'] ?? ''));
            if ('' === $actionCode || '' === $label) {
                continue;
            }

            $callbackData = $this->notificationActionService->buildTelegramCallbackData($templateCode, $monthKey, $actionCode);
            if (null === $callbackData) {
                continue;
            }

            $buttons[] = [
                'label' => $label,
                'callback_data' => $callbackData,
            ];
        }

        return $buttons;
    }
}

<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Event\NotifyActionEvent;
use App\Repository\TelegramUserRepository;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: NotifyActionEvent::class)]
final class NotificationService
{
    public function __construct(
        private readonly AdminPopupNotificationStore $popupStore,
        private readonly TelegramUserRepository $telegramUserRepository,
        private readonly TelegramBotService $telegramBotService,
    ) {
    }

    public function __invoke(NotifyActionEvent $event): void
    {
        $source = $this->normalizeSource($event->source);

        if ('popup' === $source) {
            $popupPayload = $this->renderPopupPayload($event->template, $event->payload);
            if (null === $popupPayload) {
                return;
            }

            $dedupeKey = sprintf(
                '%s:%s',
                $event->template,
                sha1((string) json_encode($event->payload))
            );

            $this->popupStore->queueDailyPopup(
                (int) $event->recipient->getId(),
                $event->occurredAt,
                $dedupeKey,
                $popupPayload
            );

            return;
        }

        if ('telegram' !== $source) {
            return;
        }

        $message = $this->renderTelegramMessage($event->template, $event->payload);
        if (null === $message) {
            return;
        }

        $this->sendTelegramMessage($event->recipient, $message);
    }

    /**
     * @param array<string, scalar|null> $payload
     *
     * @return array{
     *     title: string,
     *     message: string,
     *     monthKey: string
     * }|null
     */
    private function renderPopupPayload(string $template, array $payload): ?array
    {
        if ('spending_plan_missing_next_month' !== $template) {
            return null;
        }

        $monthLabel = (string) ($payload['monthLabel'] ?? 'next month');

        return [
            'title' => 'Spending Plan Reminder',
            'message' => sprintf('Please prepare %s spending plans.', $monthLabel),
            'monthKey' => (string) ($payload['monthKey'] ?? ''),
        ];
    }

    /**
     * @param array<string, scalar|null> $payload
     */
    private function renderTelegramMessage(string $template, array $payload): ?string
    {
        if ('spending_plan_missing_next_month' !== $template) {
            return null;
        }

        $monthLabel = (string) ($payload['monthLabel'] ?? 'next month');

        return sprintf('Reminder: please prepare %s spending plans.', $monthLabel);
    }

    private function sendTelegramMessage(User $user, string $message): void
    {
        $telegramUsers = $this->telegramUserRepository->findAuthorizedByUser($user);
        foreach ($telegramUsers as $telegramUser) {
            $this->telegramBotService->sendMessage($telegramUser->getTelegramId(), $message);
        }
    }

    private function normalizeSource(string $source): string
    {
        $normalized = mb_strtolower(trim($source));
        if ('pop-up' === $normalized) {
            return 'popup';
        }

        return $normalized;
    }
}

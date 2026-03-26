<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\TelegramUser;
use App\Repository\TelegramUserRepository;

final class TelegramUpdateProcessor
{
    public function __construct(
        private readonly TelegramUserRepository $telegramUserRepository,
        private readonly TelegramBotService $telegramBotService,
    ) {
    }

    /**
     * @param array<string, mixed> $update
     */
    public function process(array $update): void
    {
        $message = $update['message'] ?? null;
        if (!is_array($message)) {
            return;
        }

        $from = $message['from'] ?? null;
        if (!is_array($from) || !isset($from['id'])) {
            return;
        }

        $telegramId = (string) $from['id'];
        $text = (string) ($message['text'] ?? '');

        $telegramUser = $this->telegramUserRepository->findOneBy(['telegramId' => $telegramId]);
        if (null === $telegramUser) {
            $telegramUser = (new TelegramUser())
                ->setTelegramId($telegramId)
                ->setFirstName((string) ($from['first_name'] ?? 'Unknown'))
                ->setLastName(isset($from['last_name']) ? (string) $from['last_name'] : null)
                ->setStatus(TelegramUser::STATUS_PENDING);

            $this->telegramUserRepository->save($telegramUser, true);
        } else {
            $telegramUser
                ->setFirstName((string) ($from['first_name'] ?? $telegramUser->getFirstName()))
                ->setLastName(isset($from['last_name']) ? (string) $from['last_name'] : $telegramUser->getLastName());
            $this->telegramUserRepository->save($telegramUser, true);
        }

        if (TelegramUser::STATUS_AUTHORIZED === $telegramUser->getStatus()) {
            $this->telegramBotService->sendMessage(
                $telegramId,
                sprintf('Welcome back, %s. Home accounting bot stub is active.', $telegramUser->getFirstName())
            );

            return;
        }

        if ('/start' === trim($text)) {
            $this->telegramBotService->sendMessage(
                $telegramId,
                "Access request created. Please wait for admin approval."
            );

            return;
        }

        $this->telegramBotService->sendMessage(
            $telegramId,
            'Your access is pending admin approval. Send /start to re-check status.'
        );
    }
}

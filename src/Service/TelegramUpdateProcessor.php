<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\TelegramUser;
use App\Repository\TelegramUserRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class TelegramUpdateProcessor
{
    public function __construct(
        private readonly TelegramUserRepository $telegramUserRepository,
        private readonly TelegramBotService $telegramBotService,
        private readonly TelegramMiniAppTokenService $miniAppTokenService,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly LoggerInterface $logger,
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

        $chat = $message['chat'] ?? null;
        $replyChatId = is_array($chat) && isset($chat['id']) ? (string) $chat['id'] : (string) $from['id'];
        $telegramId = (string) $from['id'];
        $text = (string) ($message['text'] ?? '');
        $command = mb_strtolower(trim($text));
        $this->logger->info('Telegram update received.', [
            'telegram_id' => $telegramId,
            'text' => $text,
        ]);

        $telegramUser = $this->telegramUserRepository->findOneBy(['telegramId' => $telegramId]);

        if (null !== $telegramUser && TelegramUser::STATUS_AUTHORIZED === $telegramUser->getStatus()) {
            $this->logger->info('Telegram user is authorized.', ['telegram_id' => $telegramId]);

            $miniAppUrl = $this->buildMiniAppUrl($telegramId);
            $this->telegramBotService->sendMessageWithWebAppButton(
                $replyChatId,
                '👇',
                'Add spend',
                $miniAppUrl,
            );

            return;
        }

        if ('/reg' === $command) {
            $this->logger->info('Telegram registration command received.', ['telegram_id' => $telegramId]);
            if (null === $telegramUser) {
                $telegramUser = (new TelegramUser())
                    ->setTelegramId($telegramId)
                    ->setFirstName((string) ($from['first_name'] ?? 'Unknown'))
                    ->setLastName(isset($from['last_name']) ? (string) $from['last_name'] : null)
                    ->setStatus(TelegramUser::STATUS_PENDING);
                $this->telegramUserRepository->save($telegramUser, true);

                $this->telegramBotService->sendMessage(
                    $replyChatId,
                    'Registration request sent. Please wait for admin approval.'
                );

                return;
            }

            if (TelegramUser::STATUS_REJECTED === $telegramUser->getStatus()) {
                $telegramUser
                    ->setStatus(TelegramUser::STATUS_PENDING)
                    ->setUser(null)
                    ->setAuthorizedAt(null)
                    ->setFirstName((string) ($from['first_name'] ?? $telegramUser->getFirstName()))
                    ->setLastName(isset($from['last_name']) ? (string) $from['last_name'] : $telegramUser->getLastName());
                $this->telegramUserRepository->save($telegramUser, true);

                $this->telegramBotService->sendMessage(
                    $replyChatId,
                    'Registration request sent again. Please wait for admin approval.'
                );

                return;
            }

            $telegramUser
                ->setFirstName((string) ($from['first_name'] ?? $telegramUser->getFirstName()))
                ->setLastName(isset($from['last_name']) ? (string) $from['last_name'] : $telegramUser->getLastName());
            $this->telegramUserRepository->save($telegramUser, true);

            $this->telegramBotService->sendMessage(
                $replyChatId,
                'Registration request already exists. Please wait for admin approval.'
            );

            return;
        }

        $this->logger->info('Telegram user asked without registration.', ['telegram_id' => $telegramId, 'command' => $command]);
        $this->telegramBotService->sendMessage(
            $replyChatId,
            'For registration type /reg'
        );
    }

    private function buildMiniAppUrl(string $telegramId): string
    {
        $token = $this->miniAppTokenService->generateToken($telegramId);

        $absoluteUrl = $this->urlGenerator->generate(
            'app_telegram_mini_spend',
            ['token' => $token],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        if (!$this->needsPublicHostOverride($absoluteUrl)) {
            return $absoluteUrl;
        }

        $publicHost = $this->resolveTelegramPublicHost();
        if ('' === $publicHost) {
            return $absoluteUrl;
        }

        $path = (string) parse_url($absoluteUrl, PHP_URL_PATH);
        $query = (string) parse_url($absoluteUrl, PHP_URL_QUERY);

        return $publicHost.$path.('' !== $query ? '?'.$query : '');
    }

    private function resolveTelegramPublicHost(): string
    {
        $raw = (string) (
            $_ENV['TELEGRAM_PUBLIC_HOST']
            ?? $_SERVER['TELEGRAM_PUBLIC_HOST']
            ?? getenv('TELEGRAM_PUBLIC_HOST')
            ?: ''
        );
        $host = rtrim(trim($raw), '/');
        if ('' === $host) {
            return '';
        }

        if (!str_starts_with($host, 'http://') && !str_starts_with($host, 'https://')) {
            $host = 'https://'.$host;
        }

        if (str_starts_with($host, 'http://')) {
            $host = 'https://'.substr($host, 7);
        }

        return $host;
    }

    private function needsPublicHostOverride(string $absoluteUrl): bool
    {
        $scheme = strtolower((string) parse_url($absoluteUrl, PHP_URL_SCHEME));
        $host = strtolower((string) parse_url($absoluteUrl, PHP_URL_HOST));
        if ('https' !== $scheme) {
            return true;
        }

        return '' === $host
            || 'localhost' === $host
            || '127.0.0.1' === $host
            || '0.0.0.0' === $host
            || str_ends_with($host, '.localhost');
    }
}

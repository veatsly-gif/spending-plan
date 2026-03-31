<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\Translation\DeepLTranslationResultDto;
use App\Entity\TelegramUser;
use App\Repository\TelegramUserRepository;
use App\Service\Notification\NotificationActionService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class TelegramUpdateProcessor
{
    private const BUTTON_GEO_TO_RUSSIAN = 'Geo to russian';
    private const CALLBACK_GEO_TO_RUSSIAN = 'geo_to_russian:start';

    public function __construct(
        private readonly TelegramUserRepository $telegramUserRepository,
        private readonly TelegramBotService $telegramBotService,
        private readonly NotificationActionService $notificationActionService,
        private readonly TelegramMiniAppTokenService $miniAppTokenService,
        private readonly TelegramConversationStateService $conversationStateService,
        private readonly GeorgianTextNormalizer $georgianTextNormalizer,
        private readonly DeepLTranslationService $deepLTranslationService,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, mixed> $update
     */
    public function process(array $update): void
    {
        $callbackQuery = $update['callback_query'] ?? null;
        if (is_array($callbackQuery)) {
            $this->processCallbackQuery($callbackQuery);

            return;
        }

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

            if ($this->conversationStateService->isGeoToRussianPending($telegramId)) {
                if ('/cancel' === $command) {
                    $this->conversationStateService->clear($telegramId);
                    $this->telegramBotService->sendMessage($replyChatId, 'Translation cancelled.');
                    $this->sendAuthorizedMenu($replyChatId, $telegramId);

                    return;
                }

                if (str_starts_with($command, '/')) {
                    $this->telegramBotService->sendMessage(
                        $replyChatId,
                        'Translation mode is active. Send Georgian text or send /cancel.'
                    );

                    return;
                }

                $this->handleGeoToRussianInput($replyChatId, $telegramId, $text);

                return;
            }

            $this->sendAuthorizedMenu($replyChatId, $telegramId);

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

    /**
     * @param array<string, mixed> $callbackQuery
     */
    private function processCallbackQuery(array $callbackQuery): void
    {
        $callbackQueryId = isset($callbackQuery['id']) ? (string) $callbackQuery['id'] : '';
        if ('' === trim($callbackQueryId)) {
            return;
        }

        $from = $callbackQuery['from'] ?? null;
        if (!is_array($from) || !isset($from['id'])) {
            return;
        }

        $telegramId = (string) $from['id'];
        $callbackData = trim((string) ($callbackQuery['data'] ?? ''));
        $chatId = $this->resolveCallbackChatId($callbackQuery, $telegramId);

        if (self::CALLBACK_GEO_TO_RUSSIAN === $callbackData) {
            $telegramUser = $this->telegramUserRepository->findOneBy(['telegramId' => $telegramId]);
            if (!$telegramUser instanceof TelegramUser || TelegramUser::STATUS_AUTHORIZED !== $telegramUser->getStatus()) {
                $this->telegramBotService->answerCallbackQuery($callbackQueryId, 'Action is unavailable.');

                return;
            }

            $this->conversationStateService->startGeoToRussian($telegramId);
            $this->telegramBotService->answerCallbackQuery($callbackQueryId, 'Send Georgian text.');
            $this->telegramBotService->sendMessage(
                $chatId,
                'Send Georgian text. If you use Latin letters, I will convert them to Georgian first.'
            );

            return;
        }

        $parsed = $this->notificationActionService
            ->parseTelegramCallbackData($callbackData);
        if (null === $parsed) {
            $this->telegramBotService->answerCallbackQuery($callbackQueryId, 'Action is unavailable.');

            return;
        }

        $telegramUser = $this->telegramUserRepository->findOneBy(['telegramId' => $telegramId]);
        if (
            !$telegramUser instanceof TelegramUser
            || TelegramUser::STATUS_AUTHORIZED !== $telegramUser->getStatus()
            || null === $telegramUser->getUser()
        ) {
            $this->telegramBotService->answerCallbackQuery($callbackQueryId, 'Action is unavailable.');

            return;
        }

        $applied = $this->notificationActionService->applyAction(
            $telegramUser->getUser(),
            $parsed['templateCode'],
            $parsed['monthKey'],
            $parsed['actionCode'],
            new \DateTimeImmutable(),
        );

        if (!$applied) {
            $this->telegramBotService->answerCallbackQuery($callbackQueryId, 'Action is unavailable.');

            return;
        }

        $message = NotificationActionService::ACTION_DONE === $parsed['actionCode']
            ? 'Saved. No more reminders this month.'
            : 'Okay. I will remind you tomorrow.';

        $this->telegramBotService->answerCallbackQuery($callbackQueryId, $message);
    }

    private function sendAuthorizedMenu(string $replyChatId, string $telegramId): void
    {
        $miniAppUrl = $this->buildMiniAppUrl($telegramId);
        $this->telegramBotService->sendMessageWithInlineKeyboard(
            $replyChatId,
            '👇',
            [
                [
                    [
                        'label' => 'Add spend',
                        'web_app_url' => $miniAppUrl,
                    ],
                ],
                [
                    [
                        'label' => self::BUTTON_GEO_TO_RUSSIAN,
                        'callback_data' => self::CALLBACK_GEO_TO_RUSSIAN,
                    ],
                ],
            ],
        );
    }

    private function handleGeoToRussianInput(string $replyChatId, string $telegramId, string $text): void
    {
        $normalized = $this->georgianTextNormalizer->normalize($text);
        if (!$normalized->supported) {
            $this->telegramBotService->sendMessage(
                $replyChatId,
                'I can translate only Georgian text (Mkhedruli) or Georgian text typed with Latin letters. Try again or send /cancel.'
            );

            return;
        }

        $translationResult = $this->deepLTranslationService
            ->translateGeorgianToRussian($normalized->normalizedText);

        if (!$translationResult->success || null === $translationResult->translatedText) {
            $this->handleTranslationFailure($replyChatId, $telegramId, $translationResult);

            return;
        }

        $this->conversationStateService->clear($telegramId);

        $prefix = $normalized->converted ? "Converted to Mkhedruli:\n".$normalized->normalizedText."\n\n" : '';
        $usageInfo = $this->formatUsageInfo($translationResult->usage);
        $this->telegramBotService->sendMessage(
            $replyChatId,
            $prefix."Russian translation:\n".$translationResult->translatedText."\n\n".$usageInfo
        );
        $this->sendAuthorizedMenu($replyChatId, $telegramId);
    }

    private function handleTranslationFailure(
        string $replyChatId,
        string $telegramId,
        DeepLTranslationResultDto $translationResult,
    ): void {
        $usageInfo = $this->formatUsageInfo($translationResult->usage);

        if (DeepLTranslationResultDto::ERROR_LIMIT_CLOSE === $translationResult->errorCode
            || DeepLTranslationResultDto::ERROR_QUOTA_EXCEEDED === $translationResult->errorCode
        ) {
            $this->conversationStateService->clear($telegramId);
            $this->telegramBotService->sendMessage(
                $replyChatId,
                'DeepL limit is close to exhaustion, translation is temporarily disabled.'."\n\n".$usageInfo
            );
            $this->sendAuthorizedMenu($replyChatId, $telegramId);

            return;
        }

        if (DeepLTranslationResultDto::ERROR_CONFIG === $translationResult->errorCode) {
            $this->conversationStateService->clear($telegramId);
            $this->telegramBotService->sendMessage(
                $replyChatId,
                'Translation is unavailable: DeepL API key is invalid or not configured.'
            );
            $this->sendAuthorizedMenu($replyChatId, $telegramId);

            return;
        }

        if (DeepLTranslationResultDto::ERROR_USAGE_UNAVAILABLE === $translationResult->errorCode) {
            $this->telegramBotService->sendMessage(
                $replyChatId,
                'Cannot verify DeepL usage right now, translation is paused. Try again later or send /cancel.'
            );

            return;
        }

        $this->telegramBotService->sendMessage(
            $replyChatId,
            'DeepL translation failed. Try again or send /cancel.'
        );
    }

    /**
     * @param array<string, mixed> $callbackQuery
     */
    private function resolveCallbackChatId(array $callbackQuery, string $defaultChatId): string
    {
        $message = $callbackQuery['message'] ?? null;
        if (!is_array($message)) {
            return $defaultChatId;
        }

        $chat = $message['chat'] ?? null;
        if (!is_array($chat) || !isset($chat['id'])) {
            return $defaultChatId;
        }

        return (string) $chat['id'];
    }

    private function formatUsageInfo(?\App\DTO\Translation\DeepLUsageDto $usage): string
    {
        if (null === $usage || $usage->characterLimit <= 0) {
            return 'DeepL usage: n/a';
        }

        return sprintf(
            'DeepL usage: %d / %d (%.2f%%)',
            $usage->characterCount,
            $usage->characterLimit,
            $usage->usagePercent()
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

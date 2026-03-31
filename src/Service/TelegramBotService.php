<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class TelegramBotService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly ?string $botToken,
    ) {
    }

    public function sendMessage(string $telegramId, string $text): void
    {
        $this->sendPayload([
            'chat_id' => $telegramId,
            'text' => $text,
        ]);
    }

    public function sendMessageWithWebAppButton(
        string $telegramId,
        string $text,
        string $buttonText,
        string $webAppUrl,
    ): void {
        $this->sendMessageWithInlineKeyboard(
            $telegramId,
            $text,
            [
                [
                    [
                        'label' => $buttonText,
                        'web_app_url' => $webAppUrl,
                    ],
                ],
            ],
        );
    }

    /**
     * @param list<array{label: string, callback_data: string}> $buttons
     */
    public function sendMessageWithInlineButtons(
        string $telegramId,
        string $text,
        array $buttons,
    ): void {
        $inlineButtons = [];
        foreach ($buttons as $button) {
            $label = trim((string) ($button['label'] ?? ''));
            $callbackData = trim((string) ($button['callback_data'] ?? ''));
            if ('' === $label || '' === $callbackData) {
                continue;
            }

            $inlineButtons[] = [
                'label' => $label,
                'callback_data' => $callbackData,
            ];
        }

        if ([] === $inlineButtons) {
            $this->sendMessage($telegramId, $text);

            return;
        }

        $this->sendMessageWithInlineKeyboard($telegramId, $text, [$inlineButtons]);
    }

    /**
     * @param list<list<array{label: string, callback_data?: string, web_app_url?: string}>> $rows
     */
    public function sendMessageWithInlineKeyboard(
        string $telegramId,
        string $text,
        array $rows,
    ): void {
        $inlineKeyboard = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $inlineRow = [];
            foreach ($row as $button) {
                if (!is_array($button)) {
                    continue;
                }

                $label = trim((string) ($button['label'] ?? ''));
                if ('' === $label) {
                    continue;
                }

                $callbackData = trim((string) ($button['callback_data'] ?? ''));
                $webAppUrl = trim((string) ($button['web_app_url'] ?? ''));

                if ('' !== $callbackData) {
                    $inlineRow[] = [
                        'text' => $label,
                        'callback_data' => $callbackData,
                    ];
                    continue;
                }

                if ('' !== $webAppUrl) {
                    $inlineRow[] = [
                        'text' => $label,
                        'web_app' => [
                            'url' => $webAppUrl,
                        ],
                    ];
                }
            }

            if ([] !== $inlineRow) {
                $inlineKeyboard[] = $inlineRow;
            }
        }

        if ([] === $inlineKeyboard) {
            $this->sendMessage($telegramId, $text);

            return;
        }

        $this->sendPayload([
            'chat_id' => $telegramId,
            'text' => $text,
            'reply_markup' => [
                'inline_keyboard' => $inlineKeyboard,
            ],
        ]);
    }

    public function answerCallbackQuery(string $callbackQueryId, string $text): void
    {
        $token = trim((string) $this->botToken);
        if ('' === $token) {
            $this->logger->warning('Telegram send skipped: bot token is empty.');
            return;
        }

        $response = $this->httpClient->request('POST', sprintf('https://api.telegram.org/bot%s/answerCallbackQuery', $token), [
            'json' => [
                'callback_query_id' => $callbackQueryId,
                'text' => $text,
                'show_alert' => false,
            ],
        ]);

        $statusCode = $response->getStatusCode();
        $responsePayload = $response->toArray(false);
        if (!isset($responsePayload['ok']) || true !== $responsePayload['ok']) {
            $description = is_string($responsePayload['description'] ?? null)
                ? $responsePayload['description']
                : sprintf('Unexpected Telegram API response (HTTP %d).', $statusCode);

            $this->logger->error(sprintf('Telegram callback answer failed: %s', $description), [
                'callback_query_id' => $callbackQueryId,
                'status_code' => $statusCode,
                'payload' => $responsePayload,
            ]);

            throw new \RuntimeException(sprintf('Telegram API answerCallbackQuery failed: %s', $description));
        }

        $this->logger->info('Telegram callback answer attempted.', [
            'callback_query_id' => $callbackQueryId,
            'status_code' => $statusCode,
            'payload' => $responsePayload,
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function sendPayload(array $payload): void
    {
        $token = trim((string) $this->botToken);
        if ('' === $token) {
            $this->logger->warning('Telegram send skipped: bot token is empty.');
            return;
        }

        $response = $this->httpClient->request('POST', sprintf('https://api.telegram.org/bot%s/sendMessage', $token), [
            'json' => $payload,
        ]);

        // Force request execution and surface API/network issues during webhook handling.
        $statusCode = $response->getStatusCode();
        $responsePayload = $response->toArray(false);
        if (!isset($responsePayload['ok']) || true !== $responsePayload['ok']) {
            $description = is_string($responsePayload['description'] ?? null)
                ? $responsePayload['description']
                : sprintf('Unexpected Telegram API response (HTTP %d).', $statusCode);

            $this->logger->error(sprintf('Telegram send failed: %s', $description), [
                'chat_id' => $payload['chat_id'] ?? null,
                'status_code' => $statusCode,
                'payload' => $responsePayload,
                'request_payload' => $payload,
            ]);

            throw new \RuntimeException(sprintf('Telegram API sendMessage failed: %s', $description));
        }

        $this->logger->info('Telegram send attempted.', [
            'chat_id' => $payload['chat_id'] ?? null,
            'status_code' => $statusCode,
            'text' => $payload['text'] ?? null,
            'payload' => $responsePayload,
        ]);
    }
}

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
        $this->sendPayload([
            'chat_id' => $telegramId,
            'text' => $text,
            'reply_markup' => [
                'inline_keyboard' => [
                    [
                        [
                            'text' => $buttonText,
                            'web_app' => [
                                'url' => $webAppUrl,
                            ],
                        ],
                    ],
                ],
            ],
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

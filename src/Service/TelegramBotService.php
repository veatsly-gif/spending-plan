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
        $token = trim((string) $this->botToken);
        if ('' === $token) {
            $this->logger->warning('Telegram send skipped: bot token is empty.');
            return;
        }

        $response = $this->httpClient->request('POST', sprintf('https://api.telegram.org/bot%s/sendMessage', $token), [
            'json' => [
                'chat_id' => $telegramId,
                'text' => $text,
            ],
        ]);

        // Force request execution and surface API/network issues during webhook handling.
        $statusCode = $response->getStatusCode();
        $payload = $response->toArray(false);
        if (!isset($payload['ok']) || true !== $payload['ok']) {
            $this->logger->error('Telegram send failed.', [
                'chat_id' => $telegramId,
                'status_code' => $statusCode,
                'payload' => $payload,
            ]);

            throw new \RuntimeException('Telegram API sendMessage failed.');
        }

        $this->logger->info('Telegram send attempted.', [
            'chat_id' => $telegramId,
            'status_code' => $statusCode,
            'text' => $text,
            'payload' => $payload,
        ]);
    }
}

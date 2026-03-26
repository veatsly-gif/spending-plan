<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class TelegramBotService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ?string $botToken = null,
    ) {
    }

    public function sendMessage(string $telegramId, string $text): void
    {
        $token = trim((string) $this->botToken);
        if ('' === $token) {
            return;
        }

        $this->httpClient->request('POST', sprintf('https://api.telegram.org/bot%s/sendMessage', $token), [
            'json' => [
                'chat_id' => $telegramId,
                'text' => $text,
            ],
        ]);
    }
}

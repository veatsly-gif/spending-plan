<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Service\TelegramUpdateProcessor;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class TelegramWebhookController
{
    public function __construct(
        private readonly ?string $telegramWebhookSecret = null,
    ) {
    }

    #[Route('/telegram/webhook', name: 'api_telegram_webhook', methods: ['POST'])]
    public function __invoke(Request $request, TelegramUpdateProcessor $processor): JsonResponse
    {
        $configuredSecret = trim((string) $this->telegramWebhookSecret);
        if ('' !== $configuredSecret) {
            $incomingSecret = (string) $request->headers->get('X-Telegram-Bot-Api-Secret-Token', '');
            if (!hash_equals($configuredSecret, $incomingSecret)) {
                return new JsonResponse(['ok' => false, 'reason' => 'forbidden'], 403);
            }
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return new JsonResponse(['ok' => false, 'reason' => 'invalid_payload'], 400);
        }

        $processor->process($payload);

        return new JsonResponse(['ok' => true]);
    }
}

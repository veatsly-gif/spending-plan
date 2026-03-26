<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Service\Controller\Api\TelegramWebhookControllerService;
use App\Service\TelegramUpdateProcessor;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class TelegramWebhookController
{
    public function __construct(
        private readonly TelegramWebhookControllerService $service,
        private readonly ?string $telegramWebhookSecret = null,
    ) {
    }

    #[Route('/telegram/webhook', name: 'api_telegram_webhook', methods: ['POST'])]
    public function __invoke(Request $request, TelegramUpdateProcessor $processor): JsonResponse
    {
        $dto = $this->service->handle($request, $processor, $this->telegramWebhookSecret);

        return new JsonResponse($dto->payload, $dto->statusCode);
    }
}

<?php

declare(strict_types=1);

namespace App\Service\Controller\Api;

use App\DTO\Controller\Api\ApiResponseDto;
use App\Service\TelegramUpdateProcessor;
use Symfony\Component\HttpFoundation\Request;

final class TelegramWebhookControllerService
{
    public function handle(Request $request, TelegramUpdateProcessor $processor, ?string $configuredSecret): ApiResponseDto
    {
        $secret = trim((string) $configuredSecret);
        if ('' !== $secret) {
            $incomingSecret = (string) $request->headers->get('X-Telegram-Bot-Api-Secret-Token', '');
            if (!hash_equals($secret, $incomingSecret)) {
                return new ApiResponseDto(403, ['ok' => false, 'reason' => 'forbidden']);
            }
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return new ApiResponseDto(400, ['ok' => false, 'reason' => 'invalid_payload']);
        }

        $processor->process($payload);

        return new ApiResponseDto(200, ['ok' => true]);
    }
}

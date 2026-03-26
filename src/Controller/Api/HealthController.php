<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Service\Controller\Api\HealthControllerService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class HealthController
{
    public function __construct(
        private readonly HealthControllerService $service,
    ) {
    }

    #[Route('/health', name: 'api_health', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $dto = $this->service->buildPayload();

        return new JsonResponse($dto->payload, $dto->statusCode);
    }
}

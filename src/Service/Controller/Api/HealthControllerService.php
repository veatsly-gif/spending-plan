<?php

declare(strict_types=1);

namespace App\Service\Controller\Api;

use App\DTO\Controller\Api\ApiResponseDto;

final class HealthControllerService
{
    public function buildPayload(): ApiResponseDto
    {
        return new ApiResponseDto(200, [
            'status' => 'ok',
            'service' => 'spending-plan',
        ]);
    }
}

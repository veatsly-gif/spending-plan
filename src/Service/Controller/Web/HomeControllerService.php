<?php

declare(strict_types=1);

namespace App\Service\Controller\Web;

use App\DTO\Controller\Web\WebPageViewDto;

final class HomeControllerService
{
    public function buildViewData(): WebPageViewDto
    {
        return new WebPageViewDto();
    }
}

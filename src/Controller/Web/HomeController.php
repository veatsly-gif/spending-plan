<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Service\Controller\Web\HomeControllerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    public function __construct(
        private readonly HomeControllerService $service,
    ) {
    }

    #[Route('/', name: 'web_home', methods: ['GET'])]
    public function __invoke(): Response
    {
        $dto = $this->service->buildViewData();

        return $this->render('home/index.html.twig', $dto->context);
    }
}

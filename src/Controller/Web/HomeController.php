<?php

declare(strict_types=1);

namespace App\Controller\Web;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'web_home', methods: ['GET'])]
    public function __invoke(): Response
    {
        if (null !== $this->getUser()) {
            return $this->redirectToRoute('web_spa_entry', ['path' => 'dashboard']);
        }

        return $this->redirectToRoute('web_spa_entry', ['path' => 'login']);
    }
}

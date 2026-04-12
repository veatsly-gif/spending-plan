<?php

declare(strict_types=1);

namespace App\Controller\Web\Spa;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SpaController extends AbstractController
{
    public function __construct(
        private readonly string $projectDir,
    ) {
    }

    #[Route('/app', name: 'web_spa_root', methods: ['GET'])]
    public function root(): Response
    {
        return $this->redirectToRoute('web_spa_entry', ['path' => 'login']);
    }

    #[Route('/app/{path}', name: 'web_spa_entry', requirements: ['path' => '.+'], methods: ['GET'])]
    public function entry(string $path): Response
    {
        $indexFile = $this->projectDir.'/public/spa/index.html';
        if (!is_file($indexFile)) {
            return new Response(
                'React app bundle is missing. Run: docker compose -f docker-compose.yaml run --rm --no-deps node sh -lc "npm install && npm run build"',
                Response::HTTP_SERVICE_UNAVAILABLE,
                ['Content-Type' => 'text/plain; charset=UTF-8']
            );
        }

        $content = file_get_contents($indexFile);
        if (false === $content) {
            return new Response(
                'Unable to read React app bundle.',
                Response::HTTP_INTERNAL_SERVER_ERROR,
                ['Content-Type' => 'text/plain; charset=UTF-8']
            );
        }

        return new Response($content, Response::HTTP_OK, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}

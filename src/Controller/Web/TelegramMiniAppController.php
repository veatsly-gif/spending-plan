<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Service\TelegramMiniApp\TelegramMiniAppUserResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/telegram/mini')]
final class TelegramMiniAppController extends AbstractController
{
    public function __construct(
        private readonly string $projectDir,
        private readonly TelegramMiniAppUserResolver $telegramMiniUserResolver,
    ) {
    }

    #[Route('/spend', name: 'app_telegram_mini_spend', methods: ['GET'])]
    #[Route('/spends/{id}/edit', name: 'app_telegram_mini_spend_edit', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function miniAppShell(Request $request): Response
    {
        $token = trim((string) $request->query->get('token', ''));
        if ('' === $token || null === $this->telegramMiniUserResolver->resolveUser($request)) {
            throw new AccessDeniedHttpException('Invalid mini-app token.');
        }

        $indexFile = $this->projectDir.'/public/spa/telegram.html';
        if (!is_file($indexFile)) {
            return new Response(
                'Telegram mini-app bundle is missing. Run: docker compose -f docker-compose.yaml run --rm --no-deps node sh -lc "npm install && npm run build"',
                Response::HTTP_SERVICE_UNAVAILABLE,
                ['Content-Type' => 'text/plain; charset=UTF-8']
            );
        }

        $content = file_get_contents($indexFile);
        if (false === $content) {
            return new Response(
                'Unable to read Telegram mini-app bundle.',
                Response::HTTP_INTERNAL_SERVER_ERROR,
                ['Content-Type' => 'text/plain; charset=UTF-8']
            );
        }

        $response = new Response($content, Response::HTTP_OK, ['Content-Type' => 'text/html; charset=UTF-8']);
        $this->applyNoCacheHeaders($response);

        return $response;
    }

    private function applyNoCacheHeaders(Response $response): void
    {
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
    }
}

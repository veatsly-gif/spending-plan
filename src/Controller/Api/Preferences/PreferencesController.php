<?php

declare(strict_types=1);

namespace App\Controller\Api\Preferences;

use App\Entity\User;
use App\Service\UserMetadataService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/preferences', name: 'api_preferences_')]
#[IsGranted('ROLE_USER')]
final class PreferencesController extends AbstractController
{
    public function __construct(
        private readonly UserMetadataService $userMetadataService,
    ) {
    }

    #[Route('', name: 'get', methods: ['GET'])]
    public function getPreferences(): JsonResponse
    {
        $user = $this->requireUser();
        $preferences = $this->userMetadataService->getPreferences($user);

        return $this->json([
            'success' => true,
            'preferences' => $preferences,
        ]);
    }

    #[Route('', name: 'update', methods: ['POST'])]
    public function updatePreferences(Request $request): JsonResponse
    {
        $user = $this->requireUser();
        $payload = $this->decodeJsonPayload($request);

        $allowedKeys = ['language', 'theme'];
        $preferencesToUpdate = [];

        foreach ($allowedKeys as $key) {
            if (array_key_exists($key, $payload)) {
                $preferencesToUpdate[$key] = $payload[$key];
            }
        }

        if ([] === $preferencesToUpdate) {
            return $this->json([
                'success' => false,
                'error' => 'No valid preferences to update.',
            ], 400);
        }

        if (array_key_exists('language', $preferencesToUpdate) && !in_array($preferencesToUpdate['language'], ['en', 'ru'], true)) {
            return $this->json([
                'success' => false,
                'error' => 'Invalid language value. Must be "en" or "ru".',
            ], 400);
        }

        if (array_key_exists('theme', $preferencesToUpdate) && !in_array($preferencesToUpdate['theme'], ['light', 'dark'], true)) {
            return $this->json([
                'success' => false,
                'error' => 'Invalid theme value. Must be "light" or "dark".',
            ], 400);
        }

        $metadata = $this->userMetadataService->updatePreferences($user, $preferencesToUpdate);

        return $this->json([
            'success' => true,
            'preferences' => $metadata->getPreferences(),
        ]);
    }

    private function requireUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('User is not authenticated.');
        }

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonPayload(Request $request): array
    {
        $content = trim($request->getContent());
        if ('' === $content) {
            return [];
        }

        try {
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }
}

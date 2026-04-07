<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Service\UserMetadataService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class UserPreferencesController extends AbstractController
{
    public function __construct(
        private readonly UserMetadataService $userMetadataService,
    ) {
    }

    #[Route('/user/preferences', name: 'app_user_preferences_get', methods: ['GET'])]
    public function getPreferences(): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');

        $user = $this->getUser();
        if (null === $user) {
            return new JsonResponse(['error' => 'User not authenticated'], 401);
        }

        $preferences = $this->userMetadataService->getPreferences($user);

        return new JsonResponse([
            'success' => true,
            'preferences' => $preferences,
        ]);
    }

    #[Route('/user/preferences', name: 'app_user_preferences_update', methods: ['POST'])]
    public function updatePreferences(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');

        $user = $this->getUser();
        if (null === $user) {
            return new JsonResponse(['error' => 'User not authenticated'], 401);
        }

        $data = json_decode($request->getContent(), true);
        if (null === $data || !is_array($data)) {
            return new JsonResponse(['error' => 'Invalid JSON payload'], 400);
        }

        $allowedKeys = ['language', 'theme'];
        $preferencesToUpdate = [];

        foreach ($allowedKeys as $key) {
            if (isset($data[$key])) {
                $preferencesToUpdate[$key] = $data[$key];
            }
        }

        if (empty($preferencesToUpdate)) {
            return new JsonResponse(['error' => 'No valid preferences to update'], 400);
        }

        // Validate language
        if (isset($preferencesToUpdate['language']) && !in_array($preferencesToUpdate['language'], ['en', 'ru'], true)) {
            return new JsonResponse(['error' => 'Invalid language value. Must be "en" or "ru"'], 400);
        }

        // Validate theme
        if (isset($preferencesToUpdate['theme']) && !in_array($preferencesToUpdate['theme'], ['light', 'dark'], true)) {
            return new JsonResponse(['error' => 'Invalid theme value. Must be "light" or "dark"'], 400);
        }

        $metadata = $this->userMetadataService->updatePreferences($user, $preferencesToUpdate);

        return new JsonResponse([
            'success' => true,
            'preferences' => $metadata->getPreferences(),
        ]);
    }
}

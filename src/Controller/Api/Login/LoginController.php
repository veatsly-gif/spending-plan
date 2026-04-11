<?php

declare(strict_types=1);

namespace App\Controller\Api\Login;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\Security\ApiTokenService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[Route('/login', name: 'api_login_')]
final class LoginController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ApiTokenService $apiTokenService,
    ) {
    }

    #[Route('', name: 'check', methods: ['POST'])]
    public function check(Request $request): JsonResponse
    {
        $payload = $this->decodeJsonPayload($request);
        $username = isset($payload['username']) ? mb_strtolower(trim((string) $payload['username'])) : '';
        $password = isset($payload['password']) ? (string) $payload['password'] : '';

        if ('' === $username || '' === $password) {
            return $this->json([
                'success' => false,
                'message' => 'Username and password are required.',
            ], 422);
        }

        $user = $this->userRepository->findOneBy(['username' => $username]);
        if (!$user instanceof User || !$this->passwordHasher->isPasswordValid($user, $password)) {
            return $this->json([
                'success' => false,
                'message' => 'Invalid credentials.',
            ], 401);
        }

        $token = $this->apiTokenService->generate($user);
        $expiresAt = $this->apiTokenService->getExpiryDateTime($token);

        return $this->json([
            'success' => true,
            'message' => 'Authenticated.',
            'tokenType' => 'Bearer',
            'token' => $token,
            'expiresAt' => $expiresAt?->format(\DateTimeInterface::ATOM),
            'user' => [
                'identifier' => $user->getUserIdentifier(),
                'roles' => $user->getRoles(),
            ],
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/stub', name: 'stub', methods: ['GET'])]
    public function stub(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json([
            'success' => true,
            'message' => 'Stub check passed.',
            'user' => [
                'identifier' => $user->getUserIdentifier(),
                'roles' => $user->getRoles(),
            ],
        ]);
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

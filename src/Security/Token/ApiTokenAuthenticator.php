<?php

declare(strict_types=1);

namespace App\Security\Token;

use App\Service\Security\ApiTokenService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

final class ApiTokenAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    public function __construct(
        private readonly ApiTokenService $apiTokenService,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        $pathInfo = $request->getPathInfo();
        if (!str_starts_with($pathInfo, '/api/')) {
            return false;
        }

        $authHeader = (string) $request->headers->get('Authorization', '');

        return str_starts_with($authHeader, 'Bearer ');
    }

    public function authenticate(Request $request): SelfValidatingPassport
    {
        $authHeader = (string) $request->headers->get('Authorization', '');
        $token = trim(substr($authHeader, 7));
        if ('' === $token) {
            throw new CustomUserMessageAuthenticationException('Missing API token.');
        }

        $subject = $this->apiTokenService->parseSubjectIfValid($token);
        if (null === $subject) {
            throw new CustomUserMessageAuthenticationException('Invalid or expired API token.');
        }

        return new SelfValidatingPassport(new UserBadge($subject));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse([
            'success' => false,
            'message' => $exception->getMessageKey(),
        ], Response::HTTP_UNAUTHORIZED);
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new JsonResponse([
            'success' => false,
            'message' => 'Authentication required.',
        ], Response::HTTP_UNAUTHORIZED);
    }
}

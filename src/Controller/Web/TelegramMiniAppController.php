<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\TelegramUser;
use App\Form\Web\DashboardSpendType;
use App\Repository\SpendRepository;
use App\Repository\TelegramUserRepository;
use App\Service\Controller\Web\DashboardControllerService;
use App\Service\TelegramMiniAppTokenService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/telegram/mini')]
final class TelegramMiniAppController extends AbstractController
{
    public function __construct(
        private readonly TelegramMiniAppTokenService $miniAppTokenService,
        private readonly TelegramUserRepository $telegramUserRepository,
        private readonly SpendRepository $spendRepository,
        private readonly DashboardControllerService $dashboardControllerService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('/spend', name: 'app_telegram_mini_spend', methods: ['GET', 'POST'])]
    public function spend(Request $request): Response
    {
        $token = trim((string) $request->query->get('token', ''));
        $telegramUser = $this->resolveAuthorizedTelegramUser($token);
        if (!$telegramUser instanceof TelegramUser || null === $telegramUser->getUser()) {
            throw new AccessDeniedHttpException('Invalid mini-app token.');
        }

        $draft = $this->dashboardControllerService->createSpendDraft(new \DateTimeImmutable());
        $form = $this->createForm(DashboardSpendType::class, $draft, [
            'action' => $this->generateUrl('app_telegram_mini_spend', ['token' => $token]),
            'spending_plan_choices' => $this->dashboardControllerService->getSpendPlanChoicesForDate($draft->getSpendDate()),
        ]);
        $viewData = $this->dashboardControllerService->buildViewData($telegramUser->getUser(), new \DateTimeImmutable());
        $spendListData = $this->dashboardControllerService->buildSpendListViewData($request->query->all(), new \DateTimeImmutable());
        $spendWidget = $viewData->spendWidget;
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $result = $this->dashboardControllerService->createSpend($telegramUser->getUser(), $draft);
            if (!$result->success) {
                if ($request->isXmlHttpRequest()) {
                    $response = new JsonResponse([
                        'success' => false,
                        'error' => $this->translator->trans($result->errorMessage ?? 'spend.unable_create'),
                    ], 422);
                    $this->applyNoCacheHeaders($response);

                    return $response;
                }

                $this->addFlash('error', $result->errorMessage ?? 'spend.unable_create');

                return $this->redirectToRoute('app_telegram_mini_spend', ['token' => $token]);
            }

            if ($request->isXmlHttpRequest()) {
                $defaultDraft = $this->dashboardControllerService->createSpendDraft(new \DateTimeImmutable());
                $viewData = $this->dashboardControllerService->buildViewData($telegramUser->getUser(), new \DateTimeImmutable());
                $spendWidget = $viewData->spendWidget;

                $response = new JsonResponse([
                    'success' => true,
                    'message' => $this->translator->trans('flash.spend_added'),
                    'defaults' => [
                        'amount' => '',
                        'currencyId' => (string) ($defaultDraft->getCurrency()?->getId() ?? ''),
                        'spendingPlanId' => (string) ($defaultDraft->getSpendingPlan()?->getId() ?? ''),
                        'spendDate' => $defaultDraft->getSpendDate()->format('Y-m-d'),
                        'comment' => '',
                    ],
                    'widget' => [
                        'monthSpentGel' => $spendWidget->monthSpentGel,
                        'monthLimitGel' => $spendWidget->monthLimitGel,
                        'progressPercent' => $spendWidget->monthSpendProgressPercent,
                        'progressBarPercent' => $spendWidget->monthSpendProgressBarPercent,
                        'progressTone' => $spendWidget->monthSpendProgressTone,
                        'todaySpentGel' => $spendWidget->todaySpentGel,
                        'recentSpends' => array_map(
                            static fn (\App\DTO\Controller\Web\DashboardSpendItemDto $item): array => [
                                'amount' => $item->amount,
                                'currencyCode' => $item->currencyCode,
                                'datetime' => $item->createdAtLabel,
                                'username' => $item->username,
                                'description' => trim((string) $item->comment),
                            ],
                            $spendWidget->recentSpends
                        ),
                    ],
                ]);
                $this->applyNoCacheHeaders($response);

                return $response;
            }

            $this->addFlash('success', 'flash.spend_added');

            return $this->redirectToRoute('app_telegram_mini_spend', ['token' => $token]);
        }

        if ($form->isSubmitted() && !$form->isValid() && $request->isXmlHttpRequest()) {
            $response = new JsonResponse([
                'success' => false,
                'error' => $this->collectFirstFormError($form) ?? $this->translator->trans('spend.form_invalid'),
            ], 422);
            $this->applyNoCacheHeaders($response);

            return $response;
        }

        $response = $this->render('telegram/mini_spend.html.twig', [
            'spendForm' => $form->createView(),
            'spendWidget' => $spendWidget,
            'assetBaseHost' => $this->resolvePublicHostForAssets(),
            'assetVersion' => $this->buildAssetVersion(),
            'spendList' => $spendListData,
            'token' => $token,
        ]);
        $this->applyNoCacheHeaders($response);

        return $response;
    }

    #[Route('/spends/{id}/edit', name: 'app_telegram_mini_spend_edit', methods: ['GET', 'POST'])]
    public function editSpend(int $id, Request $request): Response
    {
        $token = trim((string) $request->query->get('token', ''));
        $telegramUser = $this->resolveAuthorizedTelegramUser($token);
        if (!$telegramUser instanceof TelegramUser || null === $telegramUser->getUser()) {
            throw new AccessDeniedHttpException('Invalid mini-app token.');
        }

        $spend = $this->spendRepository->find($id);
        if (!$spend instanceof \App\Entity\Spend) {
            throw $this->createNotFoundException('Spend not found.');
        }

        $draft = $this->dashboardControllerService->createSpendDraftFromSpend($spend);
        $form = $this->createForm(DashboardSpendType::class, $draft, [
            'action' => $this->generateUrl('app_telegram_mini_spend_edit', ['id' => $id, 'token' => $token]),
            'spending_plan_choices' => $this->dashboardControllerService->getSpendPlanChoicesForDate($draft->getSpendDate()),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $result = $this->dashboardControllerService->updateSpend($spend, $draft);
            if ($result->success) {
                $this->addFlash('success', 'spend.updated');

                return $this->redirectToRoute('app_telegram_mini_spend', ['token' => $token]);
            }

            $this->addFlash('error', $result->errorMessage ?? 'spend.unable_update');
        }

        return $this->render('telegram/mini_spend_edit.html.twig', [
            'spendForm' => $form->createView(),
            'token' => $token,
            'spend' => $spend,
            'assetBaseHost' => $this->resolvePublicHostForAssets(),
            'assetVersion' => $this->buildAssetVersion(),
        ]);
    }

    #[Route('/spends/{id}/delete', name: 'app_telegram_mini_spend_delete', methods: ['POST'])]
    public function deleteSpend(int $id, Request $request): Response
    {
        $token = trim((string) $request->query->get('token', ''));
        $telegramUser = $this->resolveAuthorizedTelegramUser($token);
        if (!$telegramUser instanceof TelegramUser || null === $telegramUser->getUser()) {
            throw new AccessDeniedHttpException('Invalid mini-app token.');
        }

        $spend = $this->spendRepository->find($id);
        if (!$spend instanceof \App\Entity\Spend) {
            throw $this->createNotFoundException('Spend not found.');
        }

        if ($this->isCsrfTokenValid('delete_spend_'.$spend->getId(), (string) $request->request->get('_token'))) {
            $this->spendRepository->remove($spend, true);
            $this->addFlash('success', 'spend.deleted');
        } else {
            $this->addFlash('error', 'Invalid CSRF token.');
        }

        return $this->redirectToRoute('app_telegram_mini_spend', ['token' => $token]);
    }

    private function resolvePublicHostForAssets(): string
    {
        $raw = (string) (
            $_ENV['TELEGRAM_PUBLIC_HOST']
            ?? $_SERVER['TELEGRAM_PUBLIC_HOST']
            ?? getenv('TELEGRAM_PUBLIC_HOST')
            ?: ''
        );
        $host = rtrim(trim($raw), '/');
        if ('' === $host) {
            return '';
        }

        if (!str_starts_with($host, 'http://') && !str_starts_with($host, 'https://')) {
            $host = 'https://'.$host;
        }

        if (str_starts_with($host, 'http://')) {
            $host = 'https://'.substr($host, 7);
        }

        return $host;
    }

    private function buildAssetVersion(): string
    {
        $base = dirname(__DIR__, 3).'/public/';
        $cssMtime = @filemtime($base.'styles/app.css');
        $jsMtime = @filemtime($base.'js/dashboard-spend-form.js');
        $version = max((int) $cssMtime, (int) $jsMtime);

        if ($version <= 0) {
            return '1';
        }

        return (string) $version;
    }

    private function applyNoCacheHeaders(Response $response): void
    {
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
    }

    private function resolveAuthorizedTelegramUser(string $token): ?TelegramUser
    {
        if ('' === $token) {
            return null;
        }

        $telegramId = $this->miniAppTokenService->resolveTelegramId($token);
        if (null === $telegramId) {
            return null;
        }

        $telegramUser = $this->telegramUserRepository->findOneBy(['telegramId' => $telegramId]);
        if (!$telegramUser instanceof TelegramUser) {
            return null;
        }

        if (TelegramUser::STATUS_AUTHORIZED !== $telegramUser->getStatus()) {
            return null;
        }

        return $telegramUser;
    }

    private function collectFirstFormError(FormInterface $form): ?string
    {
        foreach ($form->getErrors(true) as $error) {
            return $error->getMessage();
        }

        return null;
    }
}

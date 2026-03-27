<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\SpendingPlanRepository;
use App\Service\SpendingPlanSuggestionCacheService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:notifications:recreate-spending-plan-trigger',
    description: 'Recreate missing-spending-plan trigger scenario by clearing plans and suggestions for a month.'
)]
final class RecreateSpendingPlanTriggerScenarioCommand extends Command
{
    public function __construct(
        private readonly SpendingPlanRepository $spendingPlanRepository,
        private readonly SpendingPlanSuggestionCacheService $suggestionCacheService,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'month',
            InputArgument::OPTIONAL,
            'Target month key in Y-m format.',
            '2026-04'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $monthKey = (string) $input->getArgument('month');

        if (1 !== preg_match('/^\d{4}-\d{2}$/', $monthKey)) {
            $io->error('Month should be provided as Y-m (example: 2026-04).');

            return Command::INVALID;
        }

        $monthStart = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $monthKey.'-01 00:00:00');
        if (!$monthStart instanceof \DateTimeImmutable) {
            $io->error('Unable to parse month.');

            return Command::INVALID;
        }

        $monthEnd = $monthStart->modify('last day of this month')->setTime(0, 0);
        $plans = $this->spendingPlanRepository->findForMonth($monthStart, $monthEnd);

        foreach ($plans as $plan) {
            $this->entityManager->remove($plan);
        }
        $this->entityManager->flush();

        $this->suggestionCacheService->clearSuggestions($monthKey);

        $io->success(sprintf(
            'Trigger scenario recreated for %s. Removed plans: %d. Removed Redis suggestions key: sp:suggestions:%s.',
            $monthKey,
            count($plans),
            $monthKey
        ));

        return Command::SUCCESS;
    }
}

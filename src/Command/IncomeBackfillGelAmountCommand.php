<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Income\IncomeBackfillService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:income:backfill-gel',
    description: 'Fill NULL income.official_rated_amount_in_gel for old rows using official rates.'
)]
final class IncomeBackfillGelAmountCommand extends Command
{
    public function __construct(
        private readonly IncomeBackfillService $incomeBackfillService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $updatedCount = $this->incomeBackfillService->backfillOlderThanOneDay(
            new \DateTimeImmutable()
        );

        $io->success(sprintf('Backfill finished. Updated rows: %d.', $updatedCount));

        return Command::SUCCESS;
    }
}

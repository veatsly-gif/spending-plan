<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Income\IncomeLiveGelFillService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:income:fill-live-gel',
    description: 'Fill NULL income.amount_in_gel using current live rates from Redis.'
)]
final class IncomeFillLiveGelAmountCommand extends Command
{
    public function __construct(
        private readonly IncomeLiveGelFillService $incomeLiveGelFillService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $updatedCount = $this->incomeLiveGelFillService->fillMissingAmountInGel();
        $io->success(sprintf('Live GEL fill finished. Updated rows: %d.', $updatedCount));

        return Command::SUCCESS;
    }
}

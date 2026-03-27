<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Income\IncomeRateService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:income:rates:refresh-live',
    description: 'Fetch live EUR/GEL and USDT/GEL rates for amount_in_gel conversion.'
)]
final class IncomeRefreshLiveRatesCommand extends Command
{
    public function __construct(
        private readonly IncomeRateService $incomeRateService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $rates = $this->incomeRateService->refreshLiveRates(new \DateTimeImmutable());
        if (null === $rates) {
            $io->error('Failed to refresh rates. Check logs and external API availability.');

            return Command::FAILURE;
        }

        $io->success(sprintf(
            'Rates refreshed: EUR/GEL=%s, USDT/GEL=%s, updated at %s.',
            $rates->eurGel,
            $rates->usdtGel,
            $rates->updatedAt->format('Y-m-d H:i:s')
        ));

        return Command::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Currency;
use App\Entity\Income;
use App\Entity\User;
use App\Repository\IncomeRepository;
use App\Service\Income\IncomeBackfillService;
use App\Service\Income\IncomeRateService;
use App\Service\RedisStore;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class IncomeBackfillServiceTest extends TestCase
{
    public function testBackfillUpdatesOlderRowsWithOfficialRates(): void
    {
        $eur = (new Currency())->setCode('EUR')->setIsCrypto(false);
        $usdt = (new Currency())->setCode('USDT')->setIsCrypto(true);
        $user = (new User())
            ->setUsername('incomer')
            ->setRoles(['ROLE_INCOMER'])
            ->setPassword('temp');

        $oldDate = new \DateTimeImmutable('2026-03-20 10:00:00');

        $incomeEur = (new Income())
            ->setUserAdded($user)
            ->setAmount('10.00')
            ->setCurrency($eur)
            ->setAmountInGel(null)
            ->setOfficialRatedAmountInGel(null)
            ->setCreatedAt($oldDate);

        $incomeUsdt = (new Income())
            ->setUserAdded($user)
            ->setAmount('20.00')
            ->setCurrency($usdt)
            ->setAmountInGel(null)
            ->setOfficialRatedAmountInGel(null)
            ->setCreatedAt($oldDate);

        $incomeRepository = $this->createMock(IncomeRepository::class);
        $incomeRepository
            ->expects(self::once())
            ->method('findWithoutOfficialRatedAmountOlderThan')
            ->willReturn([$incomeEur, $incomeUsdt]);

        $incomeRateService = new IncomeRateService(
            new MockHttpClient([
                new MockResponse((string) json_encode([
                    [
                        'currencies' => [
                            [
                                'code' => 'EUR',
                                'quantity' => 1,
                                'rate' => 3.0,
                            ],
                        ],
                    ],
                ], JSON_THROW_ON_ERROR)),
                new MockResponse((string) json_encode([
                    [
                        'currencies' => [
                            [
                                'code' => 'USD',
                                'quantity' => 1,
                                'rate' => 2.5,
                            ],
                        ],
                    ],
                ], JSON_THROW_ON_ERROR)),
            ]),
            new RedisStore('redis://invalid-host:6380'),
            new NullLogger()
        );

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $service = new IncomeBackfillService(
            $incomeRepository,
            $incomeRateService,
            $entityManager,
            new NullLogger()
        );
        $updated = $service->backfillOlderThanOneDay(new \DateTimeImmutable('2026-03-27 10:00:00'));

        self::assertSame(2, $updated);
        self::assertSame('30.0000', $incomeEur->getOfficialRatedAmountInGel());
        self::assertSame('50.0000', $incomeUsdt->getOfficialRatedAmountInGel());
    }
}

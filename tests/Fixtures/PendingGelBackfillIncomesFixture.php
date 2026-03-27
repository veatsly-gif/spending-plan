<?php

declare(strict_types=1);

namespace App\Tests\Fixtures;

use App\Entity\Income;
use App\Entity\User;
use App\Repository\CurrencyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class PendingGelBackfillIncomesFixture implements DatabaseFixtureInterface
{
    public function load(EntityManagerInterface $entityManager, ContainerInterface $container): void
    {
        $incomer = $entityManager->getRepository(User::class)->findOneBy([
            'username' => BaseUsersFixture::INCOMER_USERNAME,
        ]);
        if (!$incomer instanceof User) {
            return;
        }

        $currencyRepository = $container->get(CurrencyRepository::class);
        $eur = $currencyRepository->findOneByCode('EUR');
        $usdt = $currencyRepository->findOneByCode('USDT');
        if (null === $eur || null === $usdt) {
            return;
        }

        $oldDate = (new \DateTimeImmutable())->modify('-2 days')->setTime(9, 0);

        $eurIncome = (new Income())
            ->setUserAdded($incomer)
            ->setAmount('10.00')
            ->setCurrency($eur)
            ->setAmountInGel(null)
            ->setComment('Backfill EUR')
            ->setCreatedAt($oldDate);

        $usdtIncome = (new Income())
            ->setUserAdded($incomer)
            ->setAmount('20.00')
            ->setCurrency($usdt)
            ->setAmountInGel(null)
            ->setComment('Backfill USDT')
            ->setCreatedAt($oldDate->modify('+2 hours'));

        $entityManager->persist($eurIncome);
        $entityManager->persist($usdtIncome);
    }
}

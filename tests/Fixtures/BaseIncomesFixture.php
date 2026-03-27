<?php

declare(strict_types=1);

namespace App\Tests\Fixtures;

use App\Entity\Income;
use App\Entity\User;
use App\Repository\CurrencyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class BaseIncomesFixture implements DatabaseFixtureInterface
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
        $gel = $currencyRepository->findOneByCode('GEL');
        $eur = $currencyRepository->findOneByCode('EUR');
        if (null === $gel || null === $eur) {
            return;
        }

        $monthStart = (new \DateTimeImmutable('first day of this month'))->setTime(10, 0);
        $now = new \DateTimeImmutable();

        $first = (new Income())
            ->setUserAdded($incomer)
            ->setAmount('100.00')
            ->setCurrency($gel)
            ->setAmountInGel('100.00')
            ->setRate('1.0000')
            ->setOfficialRatedAmountInGel('100.0000')
            ->setComment('Base GEL income')
            ->setCreatedAt($monthStart);

        $second = (new Income())
            ->setUserAdded($incomer)
            ->setAmount('50.00')
            ->setCurrency($eur)
            ->setAmountInGel('150.00')
            ->setRate('3.0000')
            ->setOfficialRatedAmountInGel('150.0000')
            ->setComment('Base EUR income')
            ->setCreatedAt($now->modify('-2 hours'));

        $entityManager->persist($first);
        $entityManager->persist($second);
    }
}

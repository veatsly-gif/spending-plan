<?php

declare(strict_types=1);

namespace App\Tests\Fixtures;

use App\Entity\Currency;
use App\Entity\SpendingPlan;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class CurrentMonthSpendingPlanFixture implements DatabaseFixtureInterface
{
    public function load(EntityManagerInterface $entityManager, ContainerInterface $container): void
    {
        $monthStart = new \DateTimeImmutable('first day of this month');
        $monthEnd = $monthStart->modify('last day of this month');
        $currency = $entityManager->getRepository(Currency::class)->findOneBy(['code' => 'GEL']);
        if (!$currency instanceof Currency) {
            throw new \RuntimeException('Currency GEL not found in fixture.');
        }

        $plan = (new SpendingPlan())
            ->setName('March base plan')
            ->setPlanType(SpendingPlan::PLAN_TYPE_REGULAR)
            ->setDateFrom($monthStart)
            ->setDateTo($monthEnd)
            ->setLimitAmount('1500.00')
            ->setCurrency($currency)
            ->setWeight(0)
            ->setIsSystem(false)
            ->setNote('Fixture regular monthly spending.')
            ->touch();

        $entityManager->persist($plan);
    }
}

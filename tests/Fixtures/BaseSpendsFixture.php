<?php

declare(strict_types=1);

namespace App\Tests\Fixtures;

use App\Entity\Currency;
use App\Entity\Spend;
use App\Entity\SpendingPlan;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class BaseSpendsFixture implements DatabaseFixtureInterface
{
    public function load(EntityManagerInterface $entityManager, ContainerInterface $container): void
    {
        $user = $entityManager->getRepository(User::class)->findOneBy([
            'username' => BaseUsersFixture::TEST_USERNAME,
        ]);
        if (!$user instanceof User) {
            return;
        }

        $plan = $entityManager->getRepository(SpendingPlan::class)->findOneBy([
            'name' => 'March base plan',
        ]);
        if (!$plan instanceof SpendingPlan) {
            return;
        }

        $currency = $entityManager->getRepository(Currency::class)->findOneBy([
            'code' => 'GEL',
        ]);
        if (!$currency instanceof Currency) {
            return;
        }

        $monthStart = (new \DateTimeImmutable('first day of this month'))->setTime(0, 0);

        $first = (new Spend())
            ->setUserAdded($user)
            ->setSpendingPlan($plan)
            ->setAmount('85.00')
            ->setCurrency($currency)
            ->setSpendDate($monthStart->modify('+3 days'))
            ->setComment('Groceries basket')
            ->setCreatedAt($monthStart->modify('+3 days 09:00'));

        $second = (new Spend())
            ->setUserAdded($user)
            ->setSpendingPlan($plan)
            ->setAmount('42.50')
            ->setCurrency($currency)
            ->setSpendDate($monthStart->modify('+7 days'))
            ->setComment('Taxi and metro')
            ->setCreatedAt($monthStart->modify('+7 days 13:30'));

        $entityManager->persist($first);
        $entityManager->persist($second);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Fixtures;

use App\Entity\Currency;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class BaseCurrenciesFixture implements DatabaseFixtureInterface
{
    public function load(EntityManagerInterface $entityManager, ContainerInterface $container): void
    {
        $currencies = [
            ['code' => 'GEL', 'isCrypto' => false],
            ['code' => 'EUR', 'isCrypto' => false],
            ['code' => 'USDT', 'isCrypto' => true],
        ];

        foreach ($currencies as $row) {
            $currency = (new Currency())
                ->setCode((string) $row['code'])
                ->setIsCrypto((bool) $row['isCrypto']);

            $entityManager->persist($currency);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Fixtures;

use App\Entity\TelegramUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class PendingTelegramUserFixture implements DatabaseFixtureInterface
{
    public const TELEGRAM_ID = '1001';
    public const FIRST_NAME = 'John';
    public const LAST_NAME = 'Doe';

    public function load(EntityManagerInterface $entityManager, ContainerInterface $container): void
    {
        $entityManager->persist(
            (new TelegramUser())
                ->setTelegramId(self::TELEGRAM_ID)
                ->setFirstName(self::FIRST_NAME)
                ->setLastName(self::LAST_NAME)
                ->setStatus(TelegramUser::STATUS_PENDING)
        );
    }
}


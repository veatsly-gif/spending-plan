<?php

declare(strict_types=1);

namespace App\Tests\Fixtures;

use App\Entity\TelegramUser;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class RejectedTelegramUserFixture implements DatabaseFixtureInterface
{
    public const TELEGRAM_ID = '777';
    public const FIRST_NAME = 'Old';
    public const LAST_NAME = 'Name';

    public function load(EntityManagerInterface $entityManager, ContainerInterface $container): void
    {
        $user = $entityManager->getRepository(User::class)->findOneBy(['username' => BaseUsersFixture::TEST_USERNAME]);
        if (!$user instanceof User) {
            throw new \RuntimeException('Base user fixture must be loaded before RejectedTelegramUserFixture.');
        }

        $entityManager->persist(
            (new TelegramUser())
                ->setTelegramId(self::TELEGRAM_ID)
                ->setFirstName(self::FIRST_NAME)
                ->setLastName(self::LAST_NAME)
                ->setUser($user)
                ->setStatus(TelegramUser::STATUS_REJECTED)
                ->setAuthorizedAt(new \DateTimeImmutable('-1 day'))
        );
    }
}


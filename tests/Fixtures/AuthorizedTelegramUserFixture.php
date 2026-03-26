<?php

declare(strict_types=1);

namespace App\Tests\Fixtures;

use App\Entity\TelegramUser;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class AuthorizedTelegramUserFixture implements DatabaseFixtureInterface
{
    public const TELEGRAM_ID = '1002';
    public const FIRST_NAME = 'Alex';

    public function load(EntityManagerInterface $entityManager, ContainerInterface $container): void
    {
        $user = $entityManager->getRepository(User::class)->findOneBy(['username' => BaseUsersFixture::TEST_USERNAME]);
        if (!$user instanceof User) {
            throw new \RuntimeException('Base user fixture must be loaded before AuthorizedTelegramUserFixture.');
        }

        $entityManager->persist(
            (new TelegramUser())
                ->setTelegramId(self::TELEGRAM_ID)
                ->setFirstName(self::FIRST_NAME)
                ->setLastName(null)
                ->setUser($user)
                ->setStatus(TelegramUser::STATUS_AUTHORIZED)
                ->setAuthorizedAt(new \DateTimeImmutable('now'))
        );
    }
}


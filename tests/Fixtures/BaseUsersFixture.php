<?php

declare(strict_types=1);

namespace App\Tests\Fixtures;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class BaseUsersFixture implements DatabaseFixtureInterface
{
    public const ADMIN_USERNAME = 'admin';
    public const ADMIN_PASSWORD = 'admin';
    public const TEST_USERNAME = 'test';
    public const TEST_PASSWORD = 'temp';
    public const INCOMER_USERNAME = 'incomer';
    public const INCOMER_PASSWORD = 'income';

    public function load(EntityManagerInterface $entityManager, ContainerInterface $container): void
    {
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);

        $admin = (new User())
            ->setUsername(self::ADMIN_USERNAME)
            ->setRoles(['ROLE_ADMIN'])
            ->setPassword('temp');
        $admin->setPassword($passwordHasher->hashPassword($admin, self::ADMIN_PASSWORD));

        $user = (new User())
            ->setUsername(self::TEST_USERNAME)
            ->setRoles(['ROLE_USER'])
            ->setPassword('temp');
        $user->setPassword($passwordHasher->hashPassword($user, self::TEST_PASSWORD));

        $incomer = (new User())
            ->setUsername(self::INCOMER_USERNAME)
            ->setRoles(['ROLE_INCOMER'])
            ->setPassword('temp');
        $incomer->setPassword($passwordHasher->hashPassword($incomer, self::INCOMER_PASSWORD));

        $entityManager->persist($admin);
        $entityManager->persist($user);
        $entityManager->persist($incomer);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Fixtures;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

interface DatabaseFixtureInterface
{
    public function load(EntityManagerInterface $entityManager, ContainerInterface $container): void;
}


<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\TelegramUser;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TelegramUser>
 */
class TelegramUserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TelegramUser::class);
    }

    public function save(TelegramUser $telegramUser, bool $flush = false): void
    {
        $this->getEntityManager()->persist($telegramUser);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return list<TelegramUser>
     */
    public function findPending(): array
    {
        return $this->findBy(['status' => TelegramUser::STATUS_PENDING], ['createdAt' => 'ASC']);
    }

    public function countPending(): int
    {
        return $this->count(['status' => TelegramUser::STATUS_PENDING]);
    }
}

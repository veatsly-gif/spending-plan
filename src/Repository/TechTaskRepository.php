<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\TechTask;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TechTask>
 */
class TechTaskRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TechTask::class);
    }

    public function save(TechTask $task, bool $flush = false): void
    {
        $this->getEntityManager()->persist($task);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(TechTask $task, bool $flush = false): void
    {
        $this->getEntityManager()->remove($task);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return array{
     *     new: list<TechTask>,
     *     in_progress: list<TechTask>,
     *     in_test: list<TechTask>,
     *     done: list<TechTask>
     * }
     */
    public function findGroupedByStatus(): array
    {
        $result = [];
        foreach (TechTask::STATUSES as $status) {
            $result[$status] = $this->findByStatusOrdered($status);
        }

        return $result;
    }

    /**
     * @return list<TechTask>
     */
    public function findByStatusOrdered(string $status): array
    {
        return $this->findBy(
            ['status' => $status],
            ['position' => 'ASC', 'id' => 'ASC']
        );
    }

    public function nextPositionForStatus(string $status): int
    {
        $maxPosition = $this->createQueryBuilder('task')
            ->select('COALESCE(MAX(task.position), 0)')
            ->andWhere('task.status = :status')
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $maxPosition + 1;
    }

    public function renumberStatus(string $status, bool $flush = false): void
    {
        $tasks = $this->findByStatusOrdered($status);
        foreach ($tasks as $index => $task) {
            $task->setPosition($index + 1);
            $task->touch();
            $this->save($task, false);
        }

        if ($flush) {
            $this->flush();
        }
    }

    public function flush(): void
    {
        $this->getEntityManager()->flush();
    }
}

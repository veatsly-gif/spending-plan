<?php

declare(strict_types=1);

namespace App\Service\Controller\Admin;

use App\DTO\Controller\Admin\AdminActionResultDto;
use App\Entity\TechTask;
use App\Repository\TechTaskRepository;

final class AdminTechTaskControllerService
{
    /**
     * @return array{
     *     new: list<TechTask>,
     *     in_progress: list<TechTask>,
     *     in_test: list<TechTask>,
     *     done: list<TechTask>
     * }
     */
    public function buildBoardData(TechTaskRepository $techTaskRepository): array
    {
        return $techTaskRepository->findGroupedByStatus();
    }

    public function createDraftTask(): TechTask
    {
        return new TechTask();
    }

    public function createTask(TechTask $task, TechTaskRepository $techTaskRepository): AdminActionResultDto
    {
        if (!TechTask::isValidStatus($task->getStatus())) {
            return new AdminActionResultDto(false, 'Invalid status.');
        }

        $task->setPosition($techTaskRepository->nextPositionForStatus($task->getStatus()));
        $task->touch();

        $techTaskRepository->save($task, true);

        return new AdminActionResultDto(true);
    }

    public function updateTask(
        TechTask $task,
        string $originalStatus,
        TechTaskRepository $techTaskRepository,
    ): AdminActionResultDto {
        $currentStatus = $task->getStatus();
        if (!TechTask::isValidStatus($currentStatus)) {
            return new AdminActionResultDto(false, 'Invalid status.');
        }

        if ($originalStatus !== $currentStatus) {
            $task->setPosition($techTaskRepository->nextPositionForStatus($currentStatus));
        }
        $task->touch();

        $techTaskRepository->save($task, true);

        if ($originalStatus !== $currentStatus) {
            $techTaskRepository->renumberStatus($originalStatus, true);
        }

        return new AdminActionResultDto(true);
    }

    public function removeTask(TechTask $task, TechTaskRepository $techTaskRepository): AdminActionResultDto
    {
        $status = $task->getStatus();
        $techTaskRepository->remove($task, true);
        $techTaskRepository->renumberStatus($status, true);

        return new AdminActionResultDto(true);
    }

    /**
     * @param array<int, mixed> $orderedIds
     */
    public function moveTask(
        TechTask $task,
        string $targetStatus,
        array $orderedIds,
        TechTaskRepository $techTaskRepository,
    ): AdminActionResultDto {
        if (!TechTask::isValidStatus($targetStatus)) {
            return new AdminActionResultDto(false, 'Invalid status.');
        }

        $originalStatus = $task->getStatus();
        if ($originalStatus !== $targetStatus) {
            $task->setStatus($targetStatus);
            $task->setPosition($techTaskRepository->nextPositionForStatus($targetStatus));
            $task->touch();
            $techTaskRepository->save($task, true);
        }

        $targetTasks = $techTaskRepository->findByStatusOrdered($targetStatus);
        $tasksById = [];
        foreach ($targetTasks as $targetTask) {
            $taskId = $targetTask->getId();
            if (null !== $taskId) {
                $tasksById[$taskId] = $targetTask;
            }
        }

        $ordered = [];
        foreach ($orderedIds as $candidateId) {
            if (!is_scalar($candidateId)) {
                continue;
            }

            $normalizedId = (int) $candidateId;
            if ($normalizedId <= 0 || !isset($tasksById[$normalizedId])) {
                continue;
            }
            if (\in_array($normalizedId, $ordered, true)) {
                continue;
            }
            $ordered[] = $normalizedId;
        }

        foreach (array_keys($tasksById) as $existingTaskId) {
            if (!\in_array($existingTaskId, $ordered, true)) {
                $ordered[] = $existingTaskId;
            }
        }

        foreach ($ordered as $index => $taskId) {
            $targetTask = $tasksById[$taskId] ?? null;
            if (!$targetTask instanceof TechTask) {
                continue;
            }

            $targetTask->setPosition($index + 1);
            $targetTask->touch();
            $techTaskRepository->save($targetTask, false);
        }
        $techTaskRepository->flush();

        if ($originalStatus !== $targetStatus) {
            $techTaskRepository->renumberStatus($originalStatus, true);
        }

        return new AdminActionResultDto(true);
    }
}

<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\TechTask;
use App\Form\Admin\AdminTechTaskType;
use App\Repository\TechTaskRepository;
use App\Service\Controller\Admin\AdminTechTaskControllerService;
use Symfony\Component\Form\FormError;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/tech-tasks')]
final class AdminTechTaskController extends AbstractController
{
    public function __construct(
        private readonly AdminTechTaskControllerService $service,
    ) {
    }

    #[Route('', name: 'admin_tech_tasks_index', methods: ['GET', 'POST'])]
    public function index(Request $request, TechTaskRepository $techTaskRepository): Response
    {
        $task = $this->service->createDraftTask();
        $createForm = $this->createForm(AdminTechTaskType::class, $task);
        $createForm->handleRequest($request);

        if ($createForm->isSubmitted() && $createForm->isValid()) {
            $result = $this->service->createTask($task, $techTaskRepository);
            if (!$result->success) {
                $createForm->get('title')->addError(new FormError($result->errorMessage ?? 'Unable to create task.'));
            } else {
                $this->addFlash('success', 'Task created.');

                return $this->redirectToRoute('admin_tech_tasks_index');
            }
        }

        return $this->render('admin/tech_tasks/index.html.twig', [
            'createForm' => $createForm->createView(),
            'statuses' => TechTask::STATUSES,
            'tasksByStatus' => $this->service->buildBoardData($techTaskRepository),
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_tech_tasks_edit', methods: ['GET', 'POST'])]
    public function edit(TechTask $task, Request $request, TechTaskRepository $techTaskRepository): Response
    {
        $originalStatus = $task->getStatus();
        $form = $this->createForm(AdminTechTaskType::class, $task, [
            'include_status' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $result = $this->service->updateTask($task, $originalStatus, $techTaskRepository);
            if (!$result->success) {
                $form->get('title')->addError(new FormError($result->errorMessage ?? 'Unable to update task.'));
            } else {
                $this->addFlash('success', 'Task updated.');

                return $this->redirectToRoute('admin_tech_tasks_index');
            }
        }

        return $this->render('admin/tech_tasks/edit.html.twig', [
            'task' => $task,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_tech_tasks_delete', methods: ['POST'])]
    public function delete(TechTask $task, Request $request, TechTaskRepository $techTaskRepository): Response
    {
        if (
            !$this->isCsrfTokenValid(
                'delete_tech_task_'.$task->getId(),
                (string) $request->request->get('_token')
            )
        ) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $result = $this->service->removeTask($task, $techTaskRepository);
        if (!$result->success) {
            $this->addFlash('error', $result->errorMessage ?? 'Unable to remove task.');

            return $this->redirectToRoute('admin_tech_tasks_index');
        }

        $this->addFlash('success', 'Task removed.');

        return $this->redirectToRoute('admin_tech_tasks_index');
    }

    #[Route('/{id}/move', name: 'admin_tech_tasks_move', methods: ['POST'])]
    public function move(TechTask $task, Request $request, TechTaskRepository $techTaskRepository): JsonResponse
    {
        if (
            !$this->isCsrfTokenValid(
                'move_tech_task',
                (string) $request->request->get('_token')
            )
        ) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Invalid CSRF token.',
            ], 403);
        }

        $status = $request->request->get('status');
        if (!is_string($status)) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Invalid status.',
            ], 400);
        }

        $result = $this->service->moveTask(
            $task,
            $status,
            $request->request->all('orderedIds'),
            $techTaskRepository
        );
        if (!$result->success) {
            return new JsonResponse([
                'success' => false,
                'error' => $result->errorMessage ?? 'Unable to move task.',
            ], 400);
        }

        return new JsonResponse([
            'success' => true,
        ]);
    }
}

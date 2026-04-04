<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Entity\TechTask;
use App\Tests\Fixtures\BaseUsersFixture;
use App\Tests\Functional\DatabaseWebTestCase;

final class AdminTechTaskControllerTest extends DatabaseWebTestCase
{
    /**
     * @return list<class-string<\App\Tests\Fixtures\DatabaseFixtureInterface>>
     */
    protected function getFixturesForTest(string $testName): array
    {
        return [BaseUsersFixture::class];
    }

    public function testAdminCanCreateEditMoveAndDeleteTechTask(): void
    {
        $this->loginAs('admin');

        $crawler = $this->client->request('GET', '/admin/tech-tasks');
        self::assertResponseIsSuccessful();

        $createForm = $crawler->selectButton('Create')->form([
            'admin_tech_task[title]' => 'Implement kanban board',
            'admin_tech_task[description]' => 'Create backend endpoints and board UI',
        ]);
        $this->client->submit($createForm);

        self::assertResponseRedirects('/admin/tech-tasks');

        $this->entityManager->clear();
        $task = $this->entityManager->getRepository(TechTask::class)->findOneBy([
            'title' => 'Implement kanban board',
        ]);
        self::assertInstanceOf(TechTask::class, $task);
        self::assertSame(TechTask::STATUS_NEW, $task->getStatus());

        $crawler = $this->client->request('GET', '/admin/tech-tasks/'.$task->getId().'/edit');
        self::assertResponseIsSuccessful();

        $editForm = $crawler->selectButton('Save')->form([
            'admin_tech_task[title]' => 'Implement admin kanban board',
            'admin_tech_task[description]' => 'CRUD + drag and drop',
            'admin_tech_task[status]' => TechTask::STATUS_IN_PROGRESS,
        ]);
        $this->client->submit($editForm);

        self::assertResponseRedirects('/admin/tech-tasks');

        $this->entityManager->clear();
        $updatedTask = $this->entityManager->getRepository(TechTask::class)->find($task->getId());
        self::assertInstanceOf(TechTask::class, $updatedTask);
        self::assertSame(TechTask::STATUS_IN_PROGRESS, $updatedTask->getStatus());

        $crawler = $this->client->request('GET', '/admin/tech-tasks');
        self::assertResponseIsSuccessful();

        $html = (string) $this->client->getResponse()->getContent();
        $matched = preg_match('/csrfToken:\s*"([^"]+)"/', $html, $matches);
        self::assertSame(1, $matched);
        $moveToken = (string) ($matches[1] ?? '');

        $this->client->request(
            'POST',
            '/admin/tech-tasks/'.$updatedTask->getId().'/move',
            [
                '_token' => $moveToken,
                'status' => TechTask::STATUS_DONE,
                'orderedIds' => [(string) $updatedTask->getId()],
            ],
            [],
            ['HTTP_X-Requested-With' => 'XMLHttpRequest']
        );

        self::assertResponseIsSuccessful();
        $responseData = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($responseData);
        self::assertTrue((bool) ($responseData['success'] ?? false));

        $this->entityManager->clear();
        $movedTask = $this->entityManager->getRepository(TechTask::class)->find($task->getId());
        self::assertInstanceOf(TechTask::class, $movedTask);
        self::assertSame(TechTask::STATUS_DONE, $movedTask->getStatus());
        self::assertSame(1, $movedTask->getPosition());

        $crawler = $this->client->request('GET', '/admin/tech-tasks');
        self::assertResponseIsSuccessful();

        $deleteForm = $crawler->filter('form[action="/admin/tech-tasks/'.$movedTask->getId().'/delete"]')->form();
        $this->client->submit($deleteForm);

        self::assertResponseRedirects('/admin/tech-tasks');

        $this->entityManager->clear();
        $deletedTask = $this->entityManager->getRepository(TechTask::class)->find($task->getId());
        self::assertNull($deletedTask);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use App\Tests\Fixtures\DatabaseFixtureInterface;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class DatabaseWebTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $entityManager;
    protected Connection $connection;

    private static bool $schemaInitialized = false;

    protected function setUp(): void
    {
        parent::setUp();

        static::ensureKernelShutdown();
        $this->client = static::createClient();
        $this->client->disableReboot();

        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->connection = $this->entityManager->getConnection();

        $this->initializeSchemaIfNeeded();
        $this->startTransaction();
        $this->loadFixtures($this->getFixturesForTest($this->name()));
    }

    protected function tearDown(): void
    {
        if (isset($this->entityManager) && $this->entityManager->isOpen()) {
            $this->entityManager->clear();
        }

        if (isset($this->connection)) {
            while ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }
        }

        unset($this->entityManager, $this->connection, $this->client);

        parent::tearDown();
    }

    protected function loginAs(string $username): User
    {
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['username' => mb_strtolower($username)]);
        self::assertInstanceOf(User::class, $user);

        $this->client->loginUser($user);

        return $user;
    }

    /**
     * @return list<class-string<DatabaseFixtureInterface>>
     */
    protected function getFixturesForTest(string $testName): array
    {
        return [];
    }

    /**
     * @param list<class-string<DatabaseFixtureInterface>> $fixtures
     */
    private function loadFixtures(array $fixtures): void
    {
        foreach ($fixtures as $fixtureClass) {
            $fixture = new $fixtureClass();
            $fixture->load($this->entityManager, static::getContainer());

            // Flush each fixture to allow dependent fixtures to query inserted rows.
            $this->entityManager->flush();
        }

        $this->entityManager->clear();
    }

    private function startTransaction(): void
    {
        while ($this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }

        $this->connection->beginTransaction();
    }

    private function initializeSchemaIfNeeded(): void
    {
        if (self::$schemaInitialized) {
            return;
        }

        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        if ([] === $metadata) {
            return;
        }

        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        self::$schemaInitialized = true;
    }
}

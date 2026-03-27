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

    protected static function getKernelClass(): string
    {
        return \App\Kernel::class;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->forceTestEnvironment();

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

    private function forceTestEnvironment(): void
    {
        // PhpStorm may run PHPUnit with --no-configuration, so force Symfony test env here.
        $defaultTestDatabaseUrl = 'postgresql://app_test:app_test@postgres_test:5432/spending_plan_test?serverVersion=16&charset=utf8';
        $testDatabaseUrl = getenv('TEST_DATABASE_URL') ?: getenv('DATABASE_URL') ?: $defaultTestDatabaseUrl;
        $defaultTestRedisDsn = 'redis://redis:6379/15';
        $testRedisDsn = getenv('TEST_REDIS_DSN') ?: $defaultTestRedisDsn;

        putenv('APP_ENV=test');
        putenv('APP_DEBUG=1');
        putenv('TEST_DATABASE_URL='.$testDatabaseUrl);
        putenv('DATABASE_URL='.$testDatabaseUrl);
        putenv('TEST_REDIS_DSN='.$testRedisDsn);
        putenv('REDIS_DSN='.$testRedisDsn);
        putenv('DEFAULT_URI='.((string) (getenv('DEFAULT_URI') ?: 'http://localhost:8188')));
        putenv('APP_SECRET='.((string) (getenv('APP_SECRET') ?: 'test-secret')));
        putenv('TELEGRAM_WEBHOOK_SECRET='.((string) (getenv('TELEGRAM_WEBHOOK_SECRET') ?: 'test-webhook-secret')));
        putenv('TELEGRAM_BOT_TOKEN='.((string) (getenv('TELEGRAM_BOT_TOKEN') ?: '')));
        $_ENV['APP_ENV'] = 'test';
        $_ENV['APP_DEBUG'] = '1';
        $_ENV['TEST_DATABASE_URL'] = $testDatabaseUrl;
        $_ENV['DATABASE_URL'] = $testDatabaseUrl;
        $_ENV['TEST_REDIS_DSN'] = $testRedisDsn;
        $_ENV['REDIS_DSN'] = $testRedisDsn;
        $_ENV['DEFAULT_URI'] = (string) (getenv('DEFAULT_URI') ?: 'http://localhost:8188');
        $_ENV['APP_SECRET'] = (string) (getenv('APP_SECRET') ?: 'test-secret');
        $_ENV['TELEGRAM_WEBHOOK_SECRET'] = (string) (getenv('TELEGRAM_WEBHOOK_SECRET') ?: 'test-webhook-secret');
        $_ENV['TELEGRAM_BOT_TOKEN'] = (string) (getenv('TELEGRAM_BOT_TOKEN') ?: '');
        $_SERVER['APP_ENV'] = 'test';
        $_SERVER['APP_DEBUG'] = '1';
        $_SERVER['TEST_DATABASE_URL'] = $testDatabaseUrl;
        $_SERVER['DATABASE_URL'] = $testDatabaseUrl;
        $_SERVER['TEST_REDIS_DSN'] = $testRedisDsn;
        $_SERVER['REDIS_DSN'] = $testRedisDsn;
        $_SERVER['DEFAULT_URI'] = (string) (getenv('DEFAULT_URI') ?: 'http://localhost:8188');
        $_SERVER['APP_SECRET'] = (string) (getenv('APP_SECRET') ?: 'test-secret');
        $_SERVER['TELEGRAM_WEBHOOK_SECRET'] = (string) (getenv('TELEGRAM_WEBHOOK_SECRET') ?: 'test-webhook-secret');
        $_SERVER['TELEGRAM_BOT_TOKEN'] = (string) (getenv('TELEGRAM_BOT_TOKEN') ?: '');
    }
}

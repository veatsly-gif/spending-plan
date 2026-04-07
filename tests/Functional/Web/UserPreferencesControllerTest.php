<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Entity\User;
use App\Entity\UserMetadata;
use App\Repository\UserMetadataRepository;
use App\Tests\Fixtures\BaseCurrenciesFixture;
use App\Tests\Fixtures\BaseUsersFixture;
use App\Tests\Functional\DatabaseWebTestCase;

final class UserPreferencesControllerTest extends DatabaseWebTestCase
{
    protected function getFixturesForTest(string $testName): array
    {
        return match ($testName) {
            default => [BaseCurrenciesFixture::class, BaseUsersFixture::class],
        };
    }

    public function testGetPreferencesRequiresAuthentication(): void
    {
        $this->client->request('GET', '/user/preferences');

        self::assertResponseRedirects('/login');
    }

    public function testGetPreferencesReturnsUserPreferences(): void
    {
        $this->loginAs('test');

        // Create user metadata with preferences
        $userRepository = $this->entityManager->getRepository(User::class);
        /** @var User $user */
        $user = $userRepository->findOneBy(['username' => 'test']);

        $metadata = new UserMetadata();
        $metadata->setUser($user);
        $metadata->setPreferences(['language' => 'ru', 'theme' => 'dark']);

        $this->entityManager->persist($metadata);
        $this->entityManager->flush();

        $this->client->request('GET', '/user/preferences');

        self::assertResponseIsSuccessful();
        $response = $this->client->getResponse();
        $data = json_decode($response->getContent(), true);

        self::assertTrue($data['success']);
        self::assertSame('ru', $data['preferences']['language']);
        self::assertSame('dark', $data['preferences']['theme']);
    }

    public function testGetPreferencesCreatesDefaultWhenNotExists(): void
    {
        $this->loginAs('test');

        $this->client->request('GET', '/user/preferences');

        self::assertResponseIsSuccessful();
        $response = $this->client->getResponse();
        $data = json_decode($response->getContent(), true);

        self::assertTrue($data['success']);
        self::assertSame('en', $data['preferences']['language']);
        self::assertSame('light', $data['preferences']['theme']);

        // Verify it was persisted to database
        $this->entityManager->clear();
        $metadataRepository = $this->entityManager->getRepository(UserMetadata::class);
        /** @var User $user */
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['username' => 'test']);
        $metadata = $metadataRepository->findOneBy(['user' => $user]);

        self::assertInstanceOf(UserMetadata::class, $metadata);
        self::assertSame('en', $metadata->getPreference('language'));
        self::assertSame('light', $metadata->getPreference('theme'));
    }

    public function testUpdatePreferencesRequiresAuthentication(): void
    {
        $this->client->request('POST', '/user/preferences', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['language' => 'ru']));

        self::assertResponseRedirects('/login');
    }

    public function testUpdatePreferencesWithValidData(): void
    {
        $this->loginAs('test');

        $this->client->request('POST', '/user/preferences', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'language' => 'ru',
            'theme' => 'dark',
        ]));

        self::assertResponseIsSuccessful();
        $response = $this->client->getResponse();
        $data = json_decode($response->getContent(), true);

        self::assertTrue($data['success']);
        self::assertSame('ru', $data['preferences']['language']);
        self::assertSame('dark', $data['preferences']['theme']);

        // Verify persistence
        $this->entityManager->clear();
        $metadataRepository = $this->entityManager->getRepository(UserMetadata::class);
        /** @var User $user */
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['username' => 'test']);
        $metadata = $metadataRepository->findOneBy(['user' => $user]);

        self::assertInstanceOf(UserMetadata::class, $metadata);
        self::assertSame('ru', $metadata->getPreference('language'));
        self::assertSame('dark', $metadata->getPreference('theme'));
    }

    public function testUpdatePreferencesOnlyUpdatesAllowedKeys(): void
    {
        $this->loginAs('test');

        // First create metadata with existing preferences
        $userRepository = $this->entityManager->getRepository(User::class);
        /** @var User $user */
        $user = $userRepository->findOneBy(['username' => 'test']);

        $metadata = new UserMetadata();
        $metadata->setUser($user);
        $metadata->setPreferences(['language' => 'en', 'theme' => 'light']);

        $this->entityManager->persist($metadata);
        $this->entityManager->flush();

        // Try to update with invalid keys
        $this->client->request('POST', '/user/preferences', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'language' => 'ru',
            'invalid_key' => 'should_be_ignored',
        ]));

        self::assertResponseIsSuccessful();
        $response = $this->client->getResponse();
        $data = json_decode($response->getContent(), true);

        self::assertTrue($data['success']);
        self::assertSame('ru', $data['preferences']['language']);
        self::assertSame('light', $data['preferences']['theme']); // Should remain unchanged
        self::assertArrayNotHasKey('invalid_key', $data['preferences']);
    }

    public function testUpdatePreferencesRejectsInvalidLanguage(): void
    {
        $this->loginAs('test');

        $this->client->request('POST', '/user/preferences', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['language' => 'fr']));

        self::assertResponseStatusCodeSame(400);
        $response = $this->client->getResponse();
        $data = json_decode($response->getContent(), true);

        self::assertArrayHasKey('error', $data);
        self::assertStringContainsString('Invalid language', $data['error']);
    }

    public function testUpdatePreferencesRejectsInvalidTheme(): void
    {
        $this->loginAs('test');

        $this->client->request('POST', '/user/preferences', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['theme' => 'blue']));

        self::assertResponseStatusCodeSame(400);
        $response = $this->client->getResponse();
        $data = json_decode($response->getContent(), true);

        self::assertArrayHasKey('error', $data);
        self::assertStringContainsString('Invalid theme', $data['error']);
    }

    public function testUpdatePreferencesRejectsInvalidJson(): void
    {
        $this->loginAs('test');

        $this->client->request('POST', '/user/preferences', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], 'not-json');

        self::assertResponseStatusCodeSame(400);
        $response = $this->client->getResponse();
        $data = json_decode($response->getContent(), true);

        self::assertArrayHasKey('error', $data);
    }

    public function testUpdatePreferencesRejectsEmptyPayload(): void
    {
        $this->loginAs('test');

        $this->client->request('POST', '/user/preferences', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([]));

        self::assertResponseStatusCodeSame(400);
        $response = $this->client->getResponse();
        $data = json_decode($response->getContent(), true);

        self::assertArrayHasKey('error', $data);
    }

    public function testUpdatePreferencesPartialUpdate(): void
    {
        $this->loginAs('test');

        // Create metadata with initial preferences
        $userRepository = $this->entityManager->getRepository(User::class);
        /** @var User $user */
        $user = $userRepository->findOneBy(['username' => 'test']);

        $metadata = new UserMetadata();
        $metadata->setUser($user);
        $metadata->setPreferences(['language' => 'en', 'theme' => 'light']);

        $this->entityManager->persist($metadata);
        $this->entityManager->flush();

        // Update only theme
        $this->client->request('POST', '/user/preferences', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['theme' => 'dark']));

        self::assertResponseIsSuccessful();
        $response = $this->client->getResponse();
        $data = json_decode($response->getContent(), true);

        self::assertTrue($data['success']);
        self::assertSame('en', $data['preferences']['language']); // Should remain unchanged
        self::assertSame('dark', $data['preferences']['theme']);
    }
}

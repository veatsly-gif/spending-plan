<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Entity\User;
use App\Entity\UserMetadata;
use App\Repository\UserMetadataRepository;
use App\Tests\Fixtures\BaseCurrenciesFixture;
use App\Tests\Fixtures\BaseUsersFixture;
use App\Tests\Functional\DatabaseWebTestCase;

final class ThemeControllerTest extends DatabaseWebTestCase
{
    protected function getFixturesForTest(string $testName): array
    {
        return match ($testName) {
            default => [BaseCurrenciesFixture::class, BaseUsersFixture::class],
        };
    }

    public function testSetThemeWithLightTheme(): void
    {
        $this->loginAs('test');

        $this->client->request('GET', '/theme/light');

        self::assertResponseIsSuccessful();
        $response = $this->client->getResponse();
        $data = json_decode($response->getContent(), true);

        self::assertTrue($data['success']);
        self::assertSame('light', $data['theme']);

        // Verify cookie is set
        $cookie = $this->client->getCookieJar()->get('_theme');
        self::assertNotNull($cookie);
        self::assertSame('light', $cookie->getValue());
    }

    public function testSetThemeWithDarkTheme(): void
    {
        $this->loginAs('test');

        $this->client->request('GET', '/theme/dark');

        self::assertResponseIsSuccessful();
        $response = $this->client->getResponse();
        $data = json_decode($response->getContent(), true);

        self::assertTrue($data['success']);
        self::assertSame('dark', $data['theme']);

        // Verify cookie is set
        $cookie = $this->client->getCookieJar()->get('_theme');
        self::assertNotNull($cookie);
        self::assertSame('dark', $cookie->getValue());
    }

    public function testSetThemePersistsForAuthenticatedUser(): void
    {
        $this->loginAs('test');

        $this->client->request('GET', '/theme/dark');

        self::assertResponseIsSuccessful();

        // Verify persistence in database
        $this->entityManager->clear();
        $metadataRepository = $this->entityManager->getRepository(UserMetadata::class);
        /** @var User $user */
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['username' => 'test']);
        $metadata = $metadataRepository->findOneBy(['user' => $user]);

        self::assertInstanceOf(UserMetadata::class, $metadata);
        self::assertSame('dark', $metadata->getPreference('theme'));
    }

    public function testSetThemeRejectsInvalidTheme(): void
    {
        $this->loginAs('test');

        $this->client->request('GET', '/theme/blue');

        self::assertResponseStatusCodeSame(400);
        $response = $this->client->getResponse();
        $data = json_decode($response->getContent(), true);

        self::assertArrayHasKey('error', $data);
        self::assertStringContainsString('Invalid theme', $data['error']);
    }

    public function testSetThemeUpdatesExistingPreferences(): void
    {
        $this->loginAs('test');

        // Create user metadata with existing preferences
        $userRepository = $this->entityManager->getRepository(User::class);
        /** @var User $user */
        $user = $userRepository->findOneBy(['username' => 'test']);

        $metadata = new UserMetadata();
        $metadata->setUser($user);
        $metadata->setPreferences(['language' => 'ru', 'theme' => 'light']);

        $this->entityManager->persist($metadata);
        $this->entityManager->flush();

        // Change theme
        $this->client->request('GET', '/theme/dark');

        self::assertResponseIsSuccessful();

        // Verify both theme changed and language preserved
        $this->entityManager->clear();
        $metadataRepository = $this->entityManager->getRepository(UserMetadata::class);
        $updatedMetadata = $metadataRepository->findOneBy(['user' => $user]);

        self::assertInstanceOf(UserMetadata::class, $updatedMetadata);
        self::assertSame('dark', $updatedMetadata->getPreference('theme'));
        self::assertSame('ru', $updatedMetadata->getPreference('language')); // Should be preserved
    }

    public function testSetThemeCaseInsensitive(): void
    {
        $this->loginAs('test');

        $this->client->request('GET', '/theme/DARK');

        self::assertResponseIsSuccessful();
        $response = $this->client->getResponse();
        $data = json_decode($response->getContent(), true);

        self::assertTrue($data['success']);
        self::assertSame('dark', $data['theme']);
    }
}
